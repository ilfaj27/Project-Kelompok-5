-- ============================================================
-- DB_SP_M_Alat.sql
-- STORED PROCEDURES, USER DEFINED FUNCTIONS, & TRIGGERS
-- untuk MASTER ALAT (Tabel: Alat, Detail_Beli_Alat, Beli_Alat)
-- ============================================================
-- Catatan: Jalankan setelah database Hoopball dan tabel sudah dibuat
-- ============================================================

USE Hoopball;
GO

-- ============================================================
-- PART 1: STORED PROCEDURES (SP) - CRUD MASTER ALAT
-- ============================================================

-- --------------------------------------------------------
-- SP 1.1: INSERT Alat (Create)
-- --------------------------------------------------------
CREATE PROCEDURE SP_Alat_Insert
    @Nama_Alat   VARCHAR(25),
    @Stok        INT,
    @Harga_Alat  DECIMAL(18,2),
    @Photo_Alat  VARCHAR(255) = NULL,
    @Status      INT,
    @Created_By  VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;

    IF @Stok < 0
    BEGIN
        RAISERROR('Stok tidak boleh negatif!', 16, 1);
        RETURN;
    END

    IF @Harga_Alat < 0
    BEGIN
        RAISERROR('Harga alat tidak boleh negatif!', 16, 1);
        RETURN;
    END

    INSERT INTO Alat 
    (Nama_Alat, Stok, Harga_Alat, Photo_Alat, Status, Is_Deleted, Created_By, Created_Date)
    VALUES 
    (@Nama_Alat, @Stok, @Harga_Alat, @Photo_Alat, @Status, 0, @Created_By, GETDATE());
END
GO

-- --------------------------------------------------------
-- SP 1.2: SELECT Alat (Read)
-- --------------------------------------------------------
CREATE PROCEDURE SP_Alat_Select
    @ID_Alat INT = NULL
AS
BEGIN
    SET NOCOUNT ON;

    SELECT ID_Alat, Nama_Alat, Stok, Harga_Alat, Photo_Alat,
           Status, Is_Deleted, Created_By, Created_Date,
           Modified_By, Modified_Date, Deleted_By, Deleted_Date
    FROM Alat
    WHERE (@ID_Alat IS NULL OR ID_Alat = @ID_Alat)
      AND Is_Deleted = 0
    ORDER BY Nama_Alat;
END
GO

-- --------------------------------------------------------
-- SP 1.3: UPDATE Alat (Update)
-- --------------------------------------------------------
CREATE PROCEDURE SP_Alat_Update
    @ID_Alat     INT,
    @Nama_Alat   VARCHAR(25) = NULL,
    @Stok        INT = NULL,
    @Harga_Alat  DECIMAL(18,2) = NULL,
    @Photo_Alat  VARCHAR(255) = NULL,
    @Status      INT = NULL,
    @Modified_By VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;

    IF NOT EXISTS (SELECT 1 FROM Alat WHERE ID_Alat = @ID_Alat AND Is_Deleted = 0)
    BEGIN
        RAISERROR('Data Alat tidak ditemukan!', 16, 1);
        RETURN;
    END

    IF @Stok IS NOT NULL AND @Stok < 0
    BEGIN
        RAISERROR('Stok tidak boleh negatif!', 16, 1);
        RETURN;
    END

    IF @Harga_Alat IS NOT NULL AND @Harga_Alat < 0
    BEGIN
        RAISERROR('Harga alat tidak boleh negatif!', 16, 1);
        RETURN;
    END

    UPDATE Alat
    SET Nama_Alat   = COALESCE(@Nama_Alat, Nama_Alat),
        Stok        = COALESCE(@Stok, Stok),
        Harga_Alat  = COALESCE(@Harga_Alat, Harga_Alat),
        Photo_Alat  = COALESCE(@Photo_Alat, Photo_Alat),
        Status      = COALESCE(@Status, Status),
        Modified_By = @Modified_By,
        Modified_Date = GETDATE()
    WHERE ID_Alat = @ID_Alat;
END
GO

-- --------------------------------------------------------
-- SP 1.4: DELETE Alat (Soft Delete)
-- --------------------------------------------------------
CREATE PROCEDURE SP_Alat_Delete
    @ID_Alat    INT,
    @Deleted_By VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;

    IF NOT EXISTS (SELECT 1 FROM Alat WHERE ID_Alat = @ID_Alat AND Is_Deleted = 0)
    BEGIN
        RAISERROR('Data Alat tidak ditemukan!', 16, 1);
        RETURN;
    END

    UPDATE Alat
    SET Is_Deleted  = 1,
        Deleted_By  = @Deleted_By,
        Deleted_Date = GETDATE()
    WHERE ID_Alat = @ID_Alat;
END
GO

-- --------------------------------------------------------
-- SP 1.5: SELECT Alat dengan Filter & Pagination
-- --------------------------------------------------------

-- DROP dulu kalo udah ada
IF OBJECT_ID('SP_Alat_SelectFiltered', 'P') IS NOT NULL
    DROP PROCEDURE SP_Alat_SelectFiltered;
GO

CREATE PROCEDURE SP_Alat_SelectFiltered
    @StatusFilter    INT = NULL,
    @SortBy          VARCHAR(20) = 'nama_asc',
    @PageNumber      INT = 1,
    @PageSize        INT = 12
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE @Offset INT = (@PageNumber - 1) * @PageSize;
    DECLARE @SortSQL NVARCHAR(100);
    DECLARE @SQL NVARCHAR(MAX);

    SET @SortSQL = CASE @SortBy
        WHEN 'stok_desc'  THEN 'Stok DESC'
        WHEN 'harga_desc' THEN 'Harga_Alat DESC'
        WHEN 'harga_asc'  THEN 'Harga_Alat ASC'
        ELSE 'Nama_Alat ASC'
    END;

    SET @SQL = N'
        SELECT ID_Alat, Nama_Alat, Stok, Harga_Alat, Photo_Alat,
               Status, Is_Deleted, Created_By, Created_Date,
               Modified_By, Modified_Date, Deleted_By, Deleted_Date
        FROM Alat
        WHERE Is_Deleted = 0
          AND (@StatusFilter IS NULL OR Status = @StatusFilter)
        ORDER BY ' + @SortSQL + '
        OFFSET @Offset ROWS FETCH NEXT @PageSize ROWS ONLY;';

    EXEC sp_executesql @SQL,
        N'@StatusFilter INT, @Offset INT, @PageSize INT',
        @StatusFilter, @Offset, @PageSize;
END
GO

-- --------------------------------------------------------
-- SP 1.6: COUNT Alat (untuk pagination & statistik)
-- --------------------------------------------------------
CREATE PROCEDURE SP_Alat_Count
    @StatusFilter INT = NULL
AS
BEGIN
    SET NOCOUNT ON;

    SELECT COUNT(*) AS TotalCount
    FROM Alat
    WHERE Is_Deleted = 0
      AND (@StatusFilter IS NULL OR Status = @StatusFilter);
END
GO

-- --------------------------------------------------------
-- SP 1.7: COUNT Alat per Status (untuk stat chips)
-- --------------------------------------------------------
CREATE PROCEDURE SP_Alat_CountByStatus
AS
BEGIN
    SET NOCOUNT ON;

    SELECT 
        SUM(CASE WHEN Status = 1 THEN 1 ELSE 0 END) AS AktifCount,
        SUM(CASE WHEN Status = 0 THEN 1 ELSE 0 END) AS NonaktifCount,
        COUNT(*) AS TotalCount
    FROM Alat
    WHERE Is_Deleted = 0;
END
GO

-- --------------------------------------------------------
-- SP 1.8: CHECK DUPLICATE Nama Alat
-- --------------------------------------------------------
CREATE PROCEDURE SP_Alat_CheckDuplicate
    @Nama_Alat VARCHAR(25),
    @ExcludeID INT = NULL
AS
BEGIN
    SET NOCOUNT ON;

    SELECT ID_Alat 
    FROM Alat 
    WHERE LOWER(Nama_Alat) = LOWER(@Nama_Alat) 
      AND ID_Alat <> ISNULL(@ExcludeID, 0)
      AND Is_Deleted = 0;
END
GO


-- ============================================================
-- PART 2: USER DEFINED FUNCTIONS (UDF) - LAPORAN & DASHBOARD
-- ============================================================

-- --------------------------------------------------------
-- UDF 2.1: Hitung Sisa Stok Alat (Scalar)
-- --------------------------------------------------------
CREATE FUNCTION dbo.udf_HitungSisaStokAlat
(
    @ID_Alat INT
)
RETURNS INT
AS
BEGIN
    DECLARE @SisaStok INT;
    DECLARE @TotalTerjual INT;

    SELECT @TotalTerjual = ISNULL(SUM(Jumlah), 0)
    FROM Detail_Beli_Alat dba
    INNER JOIN Beli_Alat ba ON dba.ID_Beli = ba.ID_Beli
    WHERE dba.ID_Alat = @ID_Alat
      AND ba.Status = 1;

    SELECT @SisaStok = Stok - @TotalTerjual
    FROM Alat
    WHERE ID_Alat = @ID_Alat;

    RETURN ISNULL(@SisaStok, 0);
END;
GO

-- --------------------------------------------------------
-- UDF 2.2: Laporan Penjualan Alat per Periode (Table-Valued)
-- --------------------------------------------------------
CREATE FUNCTION dbo.udf_LaporanPenjualanAlat
(
    @TanggalMulai DATE,
    @TanggalSelesai DATE
)
RETURNS TABLE
AS
RETURN
(
    SELECT 
        a.ID_Alat,
        a.Nama_Alat,
        a.Harga_Alat,
        ISNULL(SUM(dba.Jumlah), 0) AS TotalTerjual,
        ISNULL(SUM(dba.SubTotal), 0) AS TotalPendapatan,
        a.Stok - ISNULL(SUM(dba.Jumlah), 0) AS SisaStok
    FROM Alat a
    LEFT JOIN Detail_Beli_Alat dba ON a.ID_Alat = dba.ID_Alat
    LEFT JOIN Beli_Alat ba ON dba.ID_Beli = ba.ID_Beli 
        AND ba.Status = 1
        AND ba.Tanggal_Beli BETWEEN @TanggalMulai AND @TanggalSelesai
    WHERE a.Is_Deleted = 0
    GROUP BY a.ID_Alat, a.Nama_Alat, a.Harga_Alat, a.Stok
);
GO

-- --------------------------------------------------------
-- UDF 2.3: Laporan Stok Alat Menipis (Table-Valued)
-- --------------------------------------------------------
CREATE FUNCTION dbo.udf_LaporanStokMenipis
(
    @BatasMinimal INT = 5
)
RETURNS TABLE
AS
RETURN
(
    SELECT 
        a.ID_Alat,
        a.Nama_Alat,
        a.Stok,
        @BatasMinimal AS BatasMinimal,
        a.Stok - @BatasMinimal AS Selisih,
        CASE 
            WHEN a.Stok <= 0 THEN 'Stok Habis'
            WHEN a.Stok <= @BatasMinimal THEN 'Stok Menipis'
            ELSE 'Stok Aman'
        END AS StatusStok
    FROM Alat a
    WHERE a.Is_Deleted = 0
      AND a.Stok <= @BatasMinimal
);
GO

-- --------------------------------------------------------
-- UDF 2.4: Hitung Total Pendapatan dari Penjualan Alat (Scalar)
-- --------------------------------------------------------
CREATE FUNCTION dbo.udf_HitungTotalPendapatanAlat
(
    @TanggalMulai DATE,
    @TanggalSelesai DATE
)
RETURNS DECIMAL(18,2)
AS
BEGIN
    DECLARE @Total DECIMAL(18,2);

    SELECT @Total = ISNULL(SUM(Total_Bayar), 0)
    FROM Beli_Alat
    WHERE Status = 1
      AND Tanggal_Beli BETWEEN @TanggalMulai AND @TanggalSelesai;

    RETURN @Total;
END;
GO

-- --------------------------------------------------------
-- UDF 2.5: Dashboard Ringkasan Alat (Table-Valued)
-- --------------------------------------------------------
CREATE FUNCTION dbo.udf_DashboardRingkasanAlat
(
    @Tanggal DATE = NULL  -- NULL = hari ini
)
RETURNS TABLE
AS
RETURN
(
    SELECT 
        (SELECT COUNT(*) FROM Alat WHERE Is_Deleted = 0) AS TotalAlat,
        (SELECT COUNT(*) FROM Alat WHERE Status = 1 AND Is_Deleted = 0) AS AlatAktif,
        (SELECT COUNT(*) FROM Alat WHERE Status = 0 AND Is_Deleted = 0) AS AlatNonaktif,
        (SELECT ISNULL(SUM(Stok), 0) FROM Alat WHERE Is_Deleted = 0) AS TotalStok,
        (SELECT COUNT(*) FROM Beli_Alat WHERE Status = 1 AND (@Tanggal IS NULL OR Tanggal_Beli = @Tanggal)) AS TransaksiHariIni,
        (SELECT ISNULL(SUM(Total_Bayar), 0) FROM Beli_Alat WHERE Status = 1 AND (@Tanggal IS NULL OR Tanggal_Beli = @Tanggal)) AS PendapatanHariIni
);
GO

CREATE TABLE Log_History (
    ID_Log          INT IDENTITY(1,1) PRIMARY KEY,
    Nama_Tabel      VARCHAR(50)     NOT NULL,
    ID_Record       INT             NOT NULL,
    Aksi            VARCHAR(10)     NOT NULL,
    Data_Lama       NVARCHAR(MAX)   NULL,
    Data_Baru       NVARCHAR(MAX)   NULL,
    User_Aksi       VARCHAR(50)     NOT NULL,
    Waktu_Aksi      DATETIME        NOT NULL DEFAULT GETDATE()
);
GO

-- ============================================================
-- PART 3: TRIGGERS - LOG HISTORY & BUSINESS LOGIC
-- ============================================================

-- Pastikan tabel Log_History sudah ada (jika belum, uncomment di bawah):
-- CREATE TABLE Log_History (
--     ID_Log          INT IDENTITY(1,1) PRIMARY KEY,
--     Nama_Tabel      VARCHAR(50)     NOT NULL,
--     ID_Record       INT             NOT NULL,
--     Aksi            VARCHAR(10)     NOT NULL,
--     Data_Lama       NVARCHAR(MAX)   NULL,
--     Data_Baru       NVARCHAR(MAX)   NULL,
--     User_Aksi       VARCHAR(50)     NOT NULL,
--     Waktu_Aksi      DATETIME        NOT NULL DEFAULT GETDATE()
-- );
-- GO

-- -- --------------------------------------------------------
-- -- TRIGGER 3.1: Log History untuk Alat (INSERT, UPDATE, DELETE)
-- -- --------------------------------------------------------
-- CREATE TRIGGER trg_Alat_LogHistory
-- ON Alat
-- AFTER INSERT, UPDATE, DELETE
-- AS
-- BEGIN
--     SET NOCOUNT ON;
--     DECLARE @UserAksi VARCHAR(50) = SUSER_SNAME();

--     -- INSERT
--     IF EXISTS (SELECT 1 FROM inserted) AND NOT EXISTS (SELECT 1 FROM deleted)
--     BEGIN
--         INSERT INTO Log_History (Nama_Tabel, ID_Record, Aksi, Data_Baru, User_Aksi)
--         SELECT 
--             'Alat',
--             i.ID_Alat,
--             'INSERT',
--             (SELECT i.* FOR JSON PATH, WITHOUT_ARRAY_WRAPPER),
--             @UserAksi
--         FROM inserted i;
--     END

--     -- UPDATE
--     IF EXISTS (SELECT 1 FROM inserted) AND EXISTS (SELECT 1 FROM deleted)
--     BEGIN
--         INSERT INTO Log_History (Nama_Tabel, ID_Record, Aksi, Data_Lama, Data_Baru, User_Aksi)
--         SELECT 
--             'Alat',
--             i.ID_Alat,
--             'UPDATE',
--             (SELECT d.* FOR JSON PATH, WITHOUT_ARRAY_WRAPPER),
--             (SELECT i.* FOR JSON PATH, WITHOUT_ARRAY_WRAPPER),
--             @UserAksi
--         FROM inserted i
--         INNER JOIN deleted d ON i.ID_Alat = d.ID_Alat;
--     END

--     -- DELETE (Soft Delete)
--     IF EXISTS (SELECT 1 FROM deleted) AND NOT EXISTS (SELECT 1 FROM inserted)
--     BEGIN
--         INSERT INTO Log_History (Nama_Tabel, ID_Record, Aksi, Data_Lama, User_Aksi)
--         SELECT 
--             'Alat',
--             d.ID_Alat,
--             'DELETE',
--             (SELECT d.* FOR JSON PATH, WITHOUT_ARRAY_WRAPPER),
--             @UserAksi
--         FROM deleted d;
--     END
-- END;
-- GO

-- -- --------------------------------------------------------
-- -- TRIGGER 3.2: Auto Update Stok Alat saat Pembelian (Insert Detail_Beli_Alat)
-- -- --------------------------------------------------------
-- CREATE TRIGGER trg_DetailBeli_AutoUpdateStok
-- ON Detail_Beli_Alat
-- AFTER INSERT
-- AS
-- BEGIN
--     SET NOCOUNT ON;

--     -- Kurangi stok alat berdasarkan jumlah yang dibeli
--     UPDATE Alat
--     SET Stok = Alat.Stok - i.Jumlah
--     FROM Alat
--     INNER JOIN inserted i ON Alat.ID_Alat = i.ID_Alat;

--     -- Log perubahan stok
--     INSERT INTO Log_History (Nama_Tabel, ID_Record, Aksi, Data_Baru, User_Aksi)
--     SELECT 
--         'Alat_Stok_Update',
--         i.ID_Alat,
--         'UPDATE',
--         'Stok berkurang ' + CAST(i.Jumlah AS VARCHAR) + ' unit karena pembelian ID: ' + CAST(i.ID_Beli AS VARCHAR),
--         SUSER_SNAME()
--     FROM inserted i;
-- END;
-- GO

-- -- --------------------------------------------------------
-- -- TRIGGER 3.3: Auto Kembalikan Stok saat Pembatalan/Penghapusan Detail
-- -- --------------------------------------------------------
-- CREATE TRIGGER trg_DetailBeli_AutoKembalikanStok
-- ON Detail_Beli_Alat
-- AFTER DELETE
-- AS
-- BEGIN
--     SET NOCOUNT ON;

--     -- Kembalikan stok alat jika detail dihapus
--     UPDATE Alat
--     SET Stok = Alat.Stok + d.Jumlah
--     FROM Alat
--     INNER JOIN deleted d ON Alat.ID_Alat = d.ID_Alat;

--     -- Log perubahan stok
--     INSERT INTO Log_History (Nama_Tabel, ID_Record, Aksi, Data_Baru, User_Aksi)
--     SELECT 
--         'Alat_Stok_Kembali',
--         d.ID_Alat,
--         'UPDATE',
--         'Stok bertambah ' + CAST(d.Jumlah AS VARCHAR) + ' unit karena pembatalan pembelian ID: ' + CAST(d.ID_Beli AS VARCHAR),
--         SUSER_SNAME()
--     FROM deleted d;
-- END;
-- GO

-- -- --------------------------------------------------------
-- -- TRIGGER 3.4: Auto Update Total Bayar Beli_Alat saat Insert/Update/Delete Detail
-- -- --------------------------------------------------------
-- CREATE TRIGGER trg_DetailBeli_AutoUpdateTotal
-- ON Detail_Beli_Alat
-- AFTER INSERT, UPDATE, DELETE
-- AS
-- BEGIN
--     SET NOCOUNT ON;

--     DECLARE @ID_Beli INT;

--     -- Ambil ID_Beli yang terpengaruh
--     SELECT @ID_Beli = ID_Beli FROM inserted;
--     IF @ID_Beli IS NULL SELECT @ID_Beli = ID_Beli FROM deleted;

--     -- Update Total_Bayar di tabel Beli_Alat
--     UPDATE Beli_Alat
--     SET Total_Bayar = (
--         SELECT ISNULL(SUM(SubTotal), 0) 
--         FROM Detail_Beli_Alat 
--         WHERE ID_Beli = @ID_Beli
--     )
--     WHERE ID_Beli = @ID_Beli;

--     -- Log
--     INSERT INTO Log_History (Nama_Tabel, ID_Record, Aksi, Data_Baru, User_Aksi)
--     VALUES ('Beli_Alat', @ID_Beli, 'UPDATE', 'Total bayar diupdate otomatis', SUSER_SNAME());
-- END;
-- GO

-- -- --------------------------------------------------------
-- -- TRIGGER 3.5: Validasi Stok Sebelum Insert Detail_Beli_Alat
-- -- --------------------------------------------------------
-- CREATE TRIGGER trg_DetailBeli_ValidasiStok
-- ON Detail_Beli_Alat
-- INSTEAD OF INSERT
-- AS
-- BEGIN
--     SET NOCOUNT ON;

--     DECLARE @ID_Alat INT;
--     DECLARE @Jumlah INT;
--     DECLARE @StokTersedia INT;

--     SELECT @ID_Alat = ID_Alat, @Jumlah = Jumlah FROM inserted;

--     SELECT @StokTersedia = Stok FROM Alat WHERE ID_Alat = @ID_Alat;

--     IF @StokTersedia < @Jumlah
--     BEGIN
--         RAISERROR('Stok tidak mencukupi! Stok tersedia: %d, Jumlah diminta: %d', 16, 1, @StokTersedia, @Jumlah);
--         RETURN;
--     END

--     -- Jika stok cukup, lanjutkan insert
--     INSERT INTO Detail_Beli_Alat (ID_Alat, ID_Beli, Jumlah, SubTotal)
--     SELECT ID_Alat, ID_Beli, Jumlah, SubTotal FROM inserted;
-- END;
-- GO


-- ============================================================
-- PART 4: CONTOH PENGGUNAAN
-- ============================================================
/*

-- A. STORED PROCEDURES

-- Insert Alat Baru
EXEC SP_Alat_Insert 
    @Nama_Alat = 'Bola Basket Pro',
    @Stok = 20,
    @Harga_Alat = 300000,
    @Photo_Alat = 'asset/image/bola_pro.jpg',
    @Status = 1,
    @Created_By = 'SYSTEM';

-- Select Semua Alat
EXEC SP_Alat_Select;

-- Select Alat by ID
EXEC SP_Alat_Select @ID_Alat = 1;

-- Update Alat
EXEC SP_Alat_Update
    @ID_Alat = 1,
    @Nama_Alat = 'Bola Basket SNI Updated',
    @Stok = 25,
    @Harga_Alat = 160000,
    @Modified_By = 'ADMIN';

-- Soft Delete Alat
EXEC SP_Alat_Delete @ID_Alat = 1, @Deleted_By = 'ADMIN';

-- Select dengan Filter & Pagination
EXEC SP_Alat_SelectFiltered 
    @StatusFilter = 1, 
    @SortBy = 'harga_desc', 
    @PageNumber = 1, 
    @PageSize = 12;

-- Count Alat
EXEC SP_Alat_Count @StatusFilter = 1;

-- Count per Status
EXEC SP_Alat_CountByStatus;

-- Check Duplicate
EXEC SP_Alat_CheckDuplicate @Nama_Alat = 'Bola Basket SNI', @ExcludeID = NULL;


-- B. USER DEFINED FUNCTIONS

-- Hitung sisa stok alat (scalar)
SELECT dbo.udf_HitungSisaStokAlat(1) AS SisaStok;

-- Laporan penjualan alat per periode (table-valued)
SELECT * FROM dbo.udf_LaporanPenjualanAlat('2024-05-01', '2024-06-30');

-- Laporan stok menipis (table-valued)
SELECT * FROM dbo.udf_LaporanStokMenipis(5);

-- Hitung total pendapatan alat (scalar)
SELECT dbo.udf_HitungTotalPendapatanAlat('2024-05-01', '2024-06-30') AS TotalPendapatan;

-- Dashboard ringkasan alat (table-valued)
SELECT * FROM dbo.udf_DashboardRingkasanAlat(NULL);  -- hari ini
SELECT * FROM dbo.udf_DashboardRingkasanAlat('2024-06-10');  -- tanggal spesifik


-- C. TRIGGERS (Otomatis jalan saat CRUD)

-- Test Log History
INSERT INTO Alat (Nama_Alat, Stok, Harga_Alat, Photo_Alat, Status, Is_Deleted, Created_By, Created_Date)
VALUES ('Test Alat', 10, 100000, NULL, 1, 0, 'SYSTEM', GETDATE());

-- Lihat log history
SELECT * FROM Log_History WHERE Nama_Tabel = 'Alat' ORDER BY Waktu_Aksi DESC;

-- Test Auto Update Stok
-- Insert pembelian baru (stok akan otomatis berkurang)
-- Pastikan stok cukup dulu!

-- Test Validasi Stok (akan error kalo stok gak cukup)
-- INSERT INTO Detail_Beli_Alat (ID_Alat, ID_Beli, Jumlah, SubTotal) VALUES (1, 99, 9999, 999999);

*/
GO

-- | Part       | Isi                                                         | Jumlah    |
-- | ---------- | ----------------------------------------------------------- | --------- |
-- |   Part 1   | Stored Procedures (SP)   — CRUD Alat                        | 8 SP      |
-- |   Part 2   | User Defined Functions (UDF)   — Laporan & Dashboard Alat   | 5 UDF     |
-- |   Part 3   | Triggers — Log History + Business Logic Alat                | 5 Trigger |
-- |   Part 4   | Contoh Penggunaan   — Query test                            | —         |


-- Detail SP
-- | No | Nama SP                  | Fungsi                                               |
-- | -- | ------------------------ | ---------------------------------------------------- |
-- | 1  | `SP_Alat_Insert`         | Tambah alat baru (validasi stok & harga gak negatif) |
-- | 2  | `SP_Alat_Select`         | Tampilkan alat (by ID atau semua, yang aktif aja)    |
-- | 3  | `SP_Alat_Update`         | Edit alat (validasi, COALESCE buat field opsional)   |
-- | 4  | `SP_Alat_Delete`         | Soft delete alat (Is\_Deleted = 1)                   |
-- | 5  | `SP_Alat_SelectFiltered` | Tampilkan dengan filter status + sort + pagination   |
-- | 6  | `SP_Alat_Count`          | Hitung total alat (untuk pagination)                 |
-- | 7  | `SP_Alat_CountByStatus`  | Hitung alat aktif/nonaktif/total (buat stat chips)   |
-- | 8  | `SP_Alat_CheckDuplicate` | Cek nama alat sudah ada atau belum                   |


-- Detail UDF
-- | No | Nama UDF                        | Tipe         | Fungsi                                                            |
-- | -- | ------------------------------- | ------------ | ----------------------------------------------------------------- |
-- | 1  | `udf_HitungSisaStokAlat`        | Scalar       | Hitung stok alat setelah dikurangi yang terjual                   |
-- | 2  | `udf_LaporanPenjualanAlat`      | Table-Valued | Laporan penjualan per periode (terjual, pendapatan, sisa stok)    |
-- | 3  | `udf_LaporanStokMenipis`        | Table-Valued | Laporan alat yang stoknya menipis (bisa set batas minimal)        |
-- | 4  | `udf_HitungTotalPendapatanAlat` | Scalar       | Hitung total pendapatan dari penjualan alat per periode           |
-- | 5  | `udf_DashboardRingkasanAlat`    | Table-Valued | Ringkasan dashboard alat (total, aktif, stok, transaksi hari ini) |


-- Detail Trigger
-- | No | Nama Trigger                        | Event                             | Fungsi                                                         |
-- | -- | ----------------------------------- | --------------------------------- | -------------------------------------------------------------- |
-- | 1  | `trg_Alat_LogHistory`               | INSERT/UPDATE/DELETE              | Catat semua perubahan tabel Alat ke Log\_History (JSON format) |
-- | 2  | `trg_DetailBeli_AutoUpdateStok`     | AFTER INSERT Detail               | Otomatis kurangi stok alat saat ada pembelian                  |
-- | 3  | `trg_DetailBeli_AutoKembalikanStok` | AFTER DELETE Detail               | Otomatis kembalikan stok saat detail dihapus                   |
-- | 4  | `trg_DetailBeli_AutoUpdateTotal`    | AFTER INSERT/UPDATE/DELETE Detail | Auto update Total\_Bayar di Beli\_Alat                         |
-- | 5  | `trg_DetailBeli_ValidasiStok`       | INSTEAD OF INSERT Detail          | Validasi stok cukup sebelum insert (error kalo gak cukup)      |