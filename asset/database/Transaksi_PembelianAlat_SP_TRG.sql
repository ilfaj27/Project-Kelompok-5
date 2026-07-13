-- ============================================================================
-- Transaksi_PembelianAlat_SP_TRG.sql
-- HOOPBALL - STORED PROCEDURE & TRIGGERS
-- Transaksi Pembelian Alat (Checkout, Batal, Log History)
-- ============================================================================
-- Jalankan SETELAH:
--   1. Master_Alat_SP.sql (tabel Alat, Beli_Alat, Detail_Beli_Alat)
--   2. Update_Alat_Kategori_Size.sql (tabel Alat_Size + kolom Kategori)
--   3. Update_DetailBeli_Ukuran.sql (kolom Ukuran di Detail_Beli_Alat)
--   4. Update_BeliAlat_StatusDitolak.sql (Status 0/1/2 di Beli_Alat)
--
-- Kalau lupa jalanin no. 3, bakal muncul error:
--   "Invalid column name 'Ukuran'" -- itu artinya kolom Ukuran belum
--   ada di Detail_Beli_Alat, jalanin dulu Update_DetailBeli_Ukuran.sql.
--
-- PENTING soal trigger lama:
--   Kalau kelompok lu SUDAH PERNAH bikin trigger bernama
--   trg_DetailBeli_AutoUpdateStok sebelumnya (yang cuma motong Alat.Stok
--   waktu ada baris baru di Detail_Beli_Alat), trigger di file ini
--   MENGGANTIKANNYA lewat CREATE OR ALTER (nama disamakan persis),
--   supaya potongan stok TIDAK DOBEL. Kalau nama trigger lama kalian
--   BEDA dari ini, cari & DROP dulu manual sebelum jalanin file ini.
-- ============================================================================

USE Hoopball;
GO

-- ============================================================================
-- PART 1: STORED PROCEDURE - Checkout & Batal Pembelian Alat
-- ============================================================================
-- Catatan desain: SP di bawah HANYA validasi + insert/delete data.
-- Potong/kembalikan stok (Alat.Stok & Alat_Size.Stok) SEPENUHNYA
-- ditangani oleh trigger trg_DetailBeliAlat_AutoUpdateStok di PART 2,
-- supaya cuma ada SATU tempat yang menyentuh kolom Stok (hindari dobel).
-- ============================================================================

-- --------------------------------------------------------------------------
-- SP_BeliAlat_Insert : bikin header transaksi baru (Status = 0 Menunggu)
-- --------------------------------------------------------------------------
CREATE OR ALTER PROCEDURE SP_BeliAlat_Insert
    @ID_Karyawan        INT,
    @ID_Customer        INT,
    @Metode_Pembayaran  VARCHAR(20),
    @Bukti_Pembayaran   VARCHAR(255) = NULL,
    @Created_By         VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;

    IF NOT EXISTS (SELECT 1 FROM Karyawan WHERE ID_Karyawan = @ID_Karyawan)
    BEGIN
        SELECT 0 AS Success, 'Karyawan tidak ditemukan.' AS Message, NULL AS New_ID_Beli;
        RETURN;
    END

    IF NOT EXISTS (SELECT 1 FROM Customer WHERE ID_Customer = @ID_Customer)
    BEGIN
        SELECT 0 AS Success, 'Customer tidak ditemukan.' AS Message, NULL AS New_ID_Beli;
        RETURN;
    END

    INSERT INTO Beli_Alat
    (ID_Karyawan, ID_Customer, Tanggal_Beli, Metode_Pembayaran, Total_Bayar,
     Bukti_Pembayaran, Status, Created_By, Created_Date)
    VALUES
    (@ID_Karyawan, @ID_Customer, CAST(GETDATE() AS DATE), @Metode_Pembayaran, 0,
     @Bukti_Pembayaran, 0, @Created_By, GETDATE());

    SELECT 1 AS Success, 'Transaksi berhasil dibuat.' AS Message, SCOPE_IDENTITY() AS New_ID_Beli;
END;
GO

-- --------------------------------------------------------------------------
-- SP_DetailBeliAlat_Insert : tambah 1 item alat ke transaksi
-- (validasi stok dulu, potong stok beneran ditangani trigger)
-- --------------------------------------------------------------------------
CREATE OR ALTER PROCEDURE SP_DetailBeliAlat_Insert
    @ID_Beli   INT,
    @ID_Alat   INT,
    @Ukuran    VARCHAR(15) = 'All Size',
    @Jumlah    INT
AS
BEGIN
    SET NOCOUNT ON;

    IF @Jumlah <= 0
    BEGIN
        SELECT 0 AS Success, 'Jumlah beli harus lebih dari 0.' AS Message;
        RETURN;
    END

    IF NOT EXISTS (SELECT 1 FROM Beli_Alat WHERE ID_Beli = @ID_Beli AND Status = 0)
    BEGIN
        SELECT 0 AS Success, 'Transaksi tidak ditemukan atau sudah diproses.' AS Message;
        RETURN;
    END

    IF EXISTS (SELECT 1 FROM Detail_Beli_Alat WHERE ID_Beli = @ID_Beli AND ID_Alat = @ID_Alat)
    BEGIN
        SELECT 0 AS Success, 'Alat ini sudah ada di transaksi ini. Gabungkan jumlahnya sebelum checkout.' AS Message;
        RETURN;
    END

    DECLARE @Harga_Jual DECIMAL(18,2);
    DECLARE @Stok_Alat  INT;
    DECLARE @Stok_Size  INT;

    SELECT @Harga_Jual = Harga_Jual, @Stok_Alat = Stok
    FROM Alat
    WHERE ID_Alat = @ID_Alat AND Is_Deleted = 0 AND Status = 1;

    IF @Harga_Jual IS NULL
    BEGIN
        SELECT 0 AS Success, 'Alat tidak ditemukan atau sedang nonaktif.' AS Message;
        RETURN;
    END

    IF @Stok_Alat < @Jumlah
    BEGIN
        SELECT 0 AS Success, 'Stok alat tidak cukup.' AS Message;
        RETURN;
    END

    SELECT @Stok_Size = Stok FROM Alat_Size WHERE ID_Alat = @ID_Alat AND Ukuran = @Ukuran;

    IF @Stok_Size IS NULL
    BEGIN
        SELECT 0 AS Success, 'Ukuran yang dipilih tidak tersedia untuk alat ini.' AS Message;
        RETURN;
    END

    IF @Stok_Size < @Jumlah
    BEGIN
        SELECT 0 AS Success, 'Stok untuk ukuran tersebut tidak cukup.' AS Message;
        RETURN;
    END

    DECLARE @SubTotal DECIMAL(18,2) = @Harga_Jual * @Jumlah;

    BEGIN TRANSACTION;
    BEGIN TRY
        -- INSERT ini men-trigger trg_DetailBeliAlat_AutoUpdateStok
        -- yang otomatis motong Alat.Stok & Alat_Size.Stok
        INSERT INTO Detail_Beli_Alat (ID_Alat, ID_Beli, Jumlah, SubTotal, Ukuran)
        VALUES (@ID_Alat, @ID_Beli, @Jumlah, @SubTotal, @Ukuran);

        UPDATE Beli_Alat
        SET Total_Bayar = Total_Bayar + @SubTotal
        WHERE ID_Beli = @ID_Beli;

        COMMIT TRANSACTION;
        SELECT 1 AS Success, 'Item berhasil ditambahkan.' AS Message, @SubTotal AS SubTotal;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        SELECT 0 AS Success, 'Terjadi kesalahan sistem: ' + ERROR_MESSAGE() AS Message;
    END CATCH
END;
GO

-- --------------------------------------------------------------------------
-- SP_BeliAlat_Batalkan : batalkan transaksi yang MASIH "Menunggu"
-- (beda dari Tolak di pembelian.php, yang buat transaksi yang udah
--  masuk antrian konfirmasi karyawan)
-- --------------------------------------------------------------------------
CREATE OR ALTER PROCEDURE SP_BeliAlat_Batalkan
    @ID_Beli INT
AS
BEGIN
    SET NOCOUNT ON;

    IF NOT EXISTS (SELECT 1 FROM Beli_Alat WHERE ID_Beli = @ID_Beli AND Status = 0)
    BEGIN
        SELECT 0 AS Success, 'Transaksi tidak ditemukan atau sudah diproses.' AS Message;
        RETURN;
    END

    BEGIN TRANSACTION;
    BEGIN TRY
        -- DELETE ini men-trigger trg_DetailBeliAlat_AutoUpdateStok
        -- yang otomatis mengembalikan Alat.Stok & Alat_Size.Stok
        DELETE FROM Detail_Beli_Alat WHERE ID_Beli = @ID_Beli;
        DELETE FROM Beli_Alat WHERE ID_Beli = @ID_Beli;

        COMMIT TRANSACTION;
        SELECT 1 AS Success, 'Transaksi berhasil dibatalkan, stok telah dikembalikan.' AS Message;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        SELECT 0 AS Success, 'Terjadi kesalahan sistem: ' + ERROR_MESSAGE() AS Message;
    END CATCH
END;
GO

-- ============================================================================
-- PART 2: TRIGGER - Auto Stok, Log History & Business Logic
-- ============================================================================

-- ----------------------------------------------------------------------------
-- TRIGGER 1: trg_DetailBeliAlat_AutoUpdateStok (AFTER INSERT, DELETE)
-- Business Logic: potong stok waktu item ditambah, kembalikan stok waktu
-- item dihapus (dipakai SP_BeliAlat_Batalkan). Ini SATU-SATUNYA tempat
-- yang mengubah Alat.Stok / Alat_Size.Stok untuk transaksi pembelian.
-- ----------------------------------------------------------------------------
CREATE OR ALTER TRIGGER trg_DetailBeliAlat_AutoUpdateStok
ON Detail_Beli_Alat
AFTER INSERT, DELETE
AS
BEGIN
    SET NOCOUNT ON;

    -- Item baru ditambahkan -> kurangi stok
    IF EXISTS (SELECT 1 FROM inserted)
    BEGIN
        UPDATE A
        SET A.Stok = A.Stok - i.Jumlah
        FROM Alat A
        INNER JOIN inserted i ON A.ID_Alat = i.ID_Alat;

        UPDATE S
        SET S.Stok = S.Stok - i.Jumlah
        FROM Alat_Size S
        INNER JOIN inserted i ON S.ID_Alat = i.ID_Alat AND S.Ukuran = i.Ukuran;
    END

    -- Item dihapus (pembatalan) -> kembalikan stok
    IF EXISTS (SELECT 1 FROM deleted)
    BEGIN
        UPDATE A
        SET A.Stok = A.Stok + d.Jumlah
        FROM Alat A
        INNER JOIN deleted d ON A.ID_Alat = d.ID_Alat;

        UPDATE S
        SET S.Stok = S.Stok + d.Jumlah
        FROM Alat_Size S
        INNER JOIN deleted d ON S.ID_Alat = d.ID_Alat AND S.Ukuran = d.Ukuran;
    END
END;
GO

-- ----------------------------------------------------------------------------
-- 2.1 TABEL LOG: Log_Detail_Beli_Alat (audit lengkap tiap perubahan item)
-- ----------------------------------------------------------------------------
IF OBJECT_ID('dbo.Log_Detail_Beli_Alat', 'U') IS NULL
BEGIN
    CREATE TABLE Log_Detail_Beli_Alat (
        Log_ID              INT IDENTITY(1,1) PRIMARY KEY,
        Action_Type         VARCHAR(10)     NOT NULL,
        Action_DateTime     DATETIME        NOT NULL DEFAULT GETDATE(),
        Action_By           VARCHAR(50)     NULL,

        Old_ID_Alat         INT             NULL,
        Old_ID_Beli         INT             NULL,
        Old_Jumlah          INT             NULL,
        Old_SubTotal        DECIMAL(18,2)   NULL,
        Old_Ukuran          VARCHAR(15)     NULL,

        New_ID_Alat         INT             NULL,
        New_ID_Beli         INT             NULL,
        New_Jumlah          INT             NULL,
        New_SubTotal        DECIMAL(18,2)   NULL,
        New_Ukuran          VARCHAR(15)     NULL
    );
END
GO

-- ----------------------------------------------------------------------------
-- TRIGGER 2: trg_DetailBeliAlat_Log (AFTER INSERT, UPDATE, DELETE)
-- Log history lengkap untuk tabel Detail_Beli_Alat
-- ----------------------------------------------------------------------------
CREATE OR ALTER TRIGGER trg_DetailBeliAlat_Log
ON Detail_Beli_Alat
AFTER INSERT, UPDATE, DELETE
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE @ActionType VARCHAR(10);
    DECLARE @ActionBy VARCHAR(50) = SUSER_SNAME();

    IF EXISTS (SELECT 1 FROM inserted) AND EXISTS (SELECT 1 FROM deleted)
        SET @ActionType = 'UPDATE';
    ELSE IF EXISTS (SELECT 1 FROM inserted)
        SET @ActionType = 'INSERT';
    ELSE
        SET @ActionType = 'DELETE';

    IF @ActionType = 'INSERT'
    BEGIN
        INSERT INTO Log_Detail_Beli_Alat (
            Action_Type, Action_DateTime, Action_By,
            New_ID_Alat, New_ID_Beli, New_Jumlah, New_SubTotal, New_Ukuran
        )
        SELECT 'INSERT', GETDATE(), @ActionBy, ID_Alat, ID_Beli, Jumlah, SubTotal, Ukuran
        FROM inserted;
    END

    IF @ActionType = 'UPDATE'
    BEGIN
        INSERT INTO Log_Detail_Beli_Alat (
            Action_Type, Action_DateTime, Action_By,
            Old_ID_Alat, Old_ID_Beli, Old_Jumlah, Old_SubTotal, Old_Ukuran,
            New_ID_Alat, New_ID_Beli, New_Jumlah, New_SubTotal, New_Ukuran
        )
        SELECT 'UPDATE', GETDATE(), @ActionBy,
               d.ID_Alat, d.ID_Beli, d.Jumlah, d.SubTotal, d.Ukuran,
               i.ID_Alat, i.ID_Beli, i.Jumlah, i.SubTotal, i.Ukuran
        FROM deleted d
        INNER JOIN inserted i ON d.ID_Alat = i.ID_Alat AND d.ID_Beli = i.ID_Beli;
    END

    IF @ActionType = 'DELETE'
    BEGIN
        INSERT INTO Log_Detail_Beli_Alat (
            Action_Type, Action_DateTime, Action_By,
            Old_ID_Alat, Old_ID_Beli, Old_Jumlah, Old_SubTotal, Old_Ukuran
        )
        SELECT 'DELETE', GETDATE(), @ActionBy, ID_Alat, ID_Beli, Jumlah, SubTotal, Ukuran
        FROM deleted;
    END
END;
GO

-- ----------------------------------------------------------------------------
-- 2.2 TABEL LOG: Log_Beli_Alat_Status (histori perubahan status transaksi)
-- ----------------------------------------------------------------------------
IF OBJECT_ID('dbo.Log_Beli_Alat_Status', 'U') IS NULL
BEGIN
    CREATE TABLE Log_Beli_Alat_Status (
        Log_ID              INT IDENTITY(1,1) PRIMARY KEY,
        Action_Type         VARCHAR(10)     NOT NULL,
        Action_DateTime     DATETIME        NOT NULL DEFAULT GETDATE(),
        Action_By           VARCHAR(50)     NULL,
        ID_Beli             INT             NOT NULL,
        Old_Status          INT             NULL,
        New_Status          INT             NULL,
        Old_Status_Label    VARCHAR(50)     NULL,
        New_Status_Label    VARCHAR(50)     NULL,
        Modified_By         VARCHAR(50)     NULL,
        Modified_Date       DATETIME        NULL
    );
END
GO

-- ----------------------------------------------------------------------------
-- TRIGGER 3: trg_BeliAlat_Status_Log (AFTER UPDATE pada Beli_Alat)
-- Log history perubahan status transaksi (0=Menunggu, 1=Berhasil, 2=Ditolak)
-- ----------------------------------------------------------------------------
CREATE OR ALTER TRIGGER trg_BeliAlat_Status_Log
ON Beli_Alat
AFTER UPDATE
AS
BEGIN
    SET NOCOUNT ON;

    IF UPDATE(Status)
    BEGIN
        INSERT INTO Log_Beli_Alat_Status (
            Action_Type, Action_DateTime, Action_By,
            ID_Beli, Old_Status, New_Status,
            Old_Status_Label, New_Status_Label,
            Modified_By, Modified_Date
        )
        SELECT
            'UPDATE', GETDATE(), SUSER_SNAME(),
            i.ID_Beli, d.Status, i.Status,
            CASE d.Status WHEN 0 THEN 'Menunggu Konfirmasi' WHEN 1 THEN 'Berhasil' WHEN 2 THEN 'Ditolak' ELSE 'Unknown' END,
            CASE i.Status WHEN 0 THEN 'Menunggu Konfirmasi' WHEN 1 THEN 'Berhasil' WHEN 2 THEN 'Ditolak' ELSE 'Unknown' END,
            i.Modified_By, i.Modified_Date
        FROM inserted i
        INNER JOIN deleted d ON i.ID_Beli = d.ID_Beli
        WHERE d.Status <> i.Status;
    END
END;
GO

-- ============================================================================
-- PART 3: VERIFIKASI
-- ============================================================================

SELECT name AS ProcedureName, create_date AS CreatedDate, modify_date AS LastModified
FROM sys.procedures
WHERE name IN ('SP_BeliAlat_Insert', 'SP_DetailBeliAlat_Insert', 'SP_BeliAlat_Batalkan');

SELECT
    t.name AS TriggerName,
    OBJECT_NAME(t.parent_id) AS TableName,
    CASE t.is_instead_of_trigger WHEN 1 THEN 'INSTEAD OF' ELSE 'AFTER' END AS TriggerType,
    STUFF((
        SELECT ', ' +
            CASE te.type WHEN 1 THEN 'INSERT' WHEN 2 THEN 'UPDATE' WHEN 3 THEN 'DELETE' ELSE 'UNKNOWN' END
        FROM sys.trigger_events te
        WHERE te.object_id = t.object_id
        ORDER BY te.type
        FOR XML PATH(''), TYPE
    ).value('.', 'NVARCHAR(MAX)'), 1, 2, '') AS Events,
    t.create_date AS CreatedDate
FROM sys.triggers t
WHERE t.parent_id IN (OBJECT_ID('Detail_Beli_Alat'), OBJECT_ID('Beli_Alat'))
ORDER BY TableName, TriggerName;

SELECT name AS TableName, create_date AS CreatedDate
FROM sys.tables
WHERE name LIKE 'Log_%Alat%'
ORDER BY name;
GO

-- ============================================================================
-- PART 4: CONTOH PENGGUNAAN
-- ============================================================================
/*
-- 1. Bikin header transaksi
EXEC SP_BeliAlat_Insert
    @ID_Karyawan = 2, @ID_Customer = 5,
    @Metode_Pembayaran = 'QRIS', @Bukti_Pembayaran = 'bukti_123.jpg',
    @Created_By = 'Kasir1';
-- Balikin New_ID_Beli, misal 9

-- 2. Tambah tiap item keranjang
EXEC SP_DetailBeliAlat_Insert @ID_Beli = 9, @ID_Alat = 4, @Ukuran = 'M', @Jumlah = 2;
EXEC SP_DetailBeliAlat_Insert @ID_Beli = 9, @ID_Alat = 1, @Ukuran = 'All Size', @Jumlah = 1;

-- 3. Kalau customer batal sebelum checkout kelar / sebelum karyawan konfirmasi
EXEC SP_BeliAlat_Batalkan @ID_Beli = 9;

-- Cek log
SELECT * FROM Log_Detail_Beli_Alat ORDER BY Log_ID DESC;
SELECT * FROM Log_Beli_Alat_Status ORDER BY Log_ID DESC;

-- Konfirmasi & Tolak transaksi TETAP pakai query yang sudah ada di pembelian.php
-- (UPDATE Status = 1 untuk konfirmasi; Status = 2 + kembalikan stok manual untuk tolak
--  -- karena Tolak TIDAK menghapus baris Detail_Beli_Alat, trigger stok di atas
--  TIDAK ikut campur di alur Tolak, jadi kode reject yang sudah ada di pembelian.php
--  aman dipakai terus tanpa perubahan).
*/
GO


-- ============================================================
-- Update_DetailBeli_Ukuran.sql
-- OPTION A: Simpan UKURAN (size) yang dibeli customer sebagai
--           catatan di detail pembelian.
-- ============================================================
-- Konteks:
--   - Master Alat sudah punya tabel Alat_Size (stok per ukuran)
--     dari Update_Alat_Kategori_Size.sql.
--   - Di sini kita HANYA menambah kolom Ukuran ke Detail_Beli_Alat
--     supaya karyawan tahu ukuran apa yang harus diserahkan.
--   - Stok tetap dipotong dari total Alat.Stok lewat trigger lama
--     (trg_DetailBeli_AutoUpdateStok) — TIDAK diubah.
-- ============================================================
-- Jalankan SETELAH database Hoopball + Update_Alat_Kategori_Size.sql
-- ============================================================

-- ------------------------------------------------------------
-- 1. Tambah kolom Ukuran (nullable, default 'All Size')
--    Nullable + default supaya data pembelian lama tetap valid.
-- ------------------------------------------------------------
IF COL_LENGTH('Detail_Beli_Alat', 'Ukuran') IS NULL
BEGIN
    ALTER TABLE Detail_Beli_Alat
    ADD Ukuran VARCHAR(15) NULL
        CONSTRAINT DF_DetailBeli_Ukuran DEFAULT 'All Size';
END
GO

-- ------------------------------------------------------------
-- 2. Backfill data lama: yang belum punya ukuran -> 'All Size'
-- ------------------------------------------------------------
UPDATE Detail_Beli_Alat
SET Ukuran = 'All Size'
WHERE Ukuran IS NULL;
GO

-- ------------------------------------------------------------
-- CATATAN untuk tim:
--   Primary Key Detail_Beli_Alat saat ini (ID_Alat, ID_Beli).
--   Karena satu transaksi bisa beli 1 alat dalam 2 ukuran berbeda
--   (misal Jersey S dan Jersey M dalam 1 checkout), maka baris kedua
--   akan bentrok PK. PHP pembelian_alat.php sudah diatur untuk
--   MENGGABUNGKAN item dengan (id_alat + ukuran) yang sama, dan tetap
--   membuat baris terpisah untuk ukuran berbeda -> ini melanggar PK.
--
--   Solusi paling aman TANPA memecah PK: PHP menjumlahkan qty per
--   (ID_Alat, ID_Beli) menjadi satu baris, dan menyimpan detail ukuran
--   pada baris tsb. Namun bila diinginkan multi-ukuran per alat dalam
--   satu nota, jalankan bagian OPSIONAL di bawah untuk memasukkan
--   Ukuran ke dalam Primary Key.
--
--   >>> Secara default kami TIDAK menjalankan bagian opsional ini,
--       karena mengubah PK berdampak ke trigger & halaman lain.
--       PHP sudah menangani kasus umum dengan aman.
-- ------------------------------------------------------------

/*  ===== OPSIONAL: jadikan Ukuran bagian dari Primary Key =====
    Hanya jalankan bila tim setuju & sudah mengetes trigger terkait.

ALTER TABLE Detail_Beli_Alat DROP CONSTRAINT PK__Detail_B__...; -- ganti nama PK asli
ALTER TABLE Detail_Beli_Alat
    ALTER COLUMN Ukuran VARCHAR(15) NOT NULL;
ALTER TABLE Detail_Beli_Alat
    ADD CONSTRAINT PK_Detail_Beli_Alat PRIMARY KEY (ID_Alat, ID_Beli, Ukuran);
*/
GO

-- ============================================================
-- Update_BeliAlat_StatusDitolak.sql
-- Tambah status 2 = DITOLAK untuk Beli_Alat
-- ============================================================
-- Kenapa perlu:
--   Constraint lama: CHECK (Status IN (0,1)) -> cuma Menunggu & Berhasil.
--   Akibatnya tombol "Tolak/Batalkan" lama terpaksa set Status balik ke 0,
--   sehingga pembelian yang ditolak muncul lagi sebagai "Menunggu" dan
--   bisa dikonfirmasi ulang (stok bisa balik dua kali). Ini bug.
--
-- Setelah script ini:
--   0 = Menunggu Konfirmasi
--   1 = Berhasil (dikonfirmasi karyawan)
--   2 = Ditolak (karyawan menolak, stok dikembalikan)
--
-- Jalankan SEKALI di SSMS sebelum pakai pembelian.php versi baru.
-- ============================================================

-- 1. Cari & hapus CHECK constraint lama di kolom Status (namanya auto-generate)
DECLARE @cname SYSNAME;
SELECT TOP 1 @cname = cc.name
FROM sys.check_constraints cc
WHERE cc.parent_object_id = OBJECT_ID('Beli_Alat')
  AND cc.definition LIKE '%Status%';

IF @cname IS NOT NULL
BEGIN
    EXEC('ALTER TABLE Beli_Alat DROP CONSTRAINT [' + @cname + ']');
    PRINT 'Constraint lama dihapus: ' + @cname;
END
GO

-- 2. Pasang constraint baru yang mengizinkan status 2 (Ditolak)
ALTER TABLE Beli_Alat
ADD CONSTRAINT CK_BeliAlat_Status CHECK (Status IN (0, 1, 2));
GO

PRINT 'Selesai. Status Beli_Alat sekarang: 0=Menunggu, 1=Berhasil, 2=Ditolak.';
GO

SELECT name FROM sys.triggers WHERE parent_id = OBJECT_ID('Detail_Beli_Alat');