-- ============================================================================
-- HOOPBALL - STORED PROCEDURE & TRIGGERS
-- Transaksi Pembatalan Booking + Log History
-- ============================================================================
-- Jalankan script ini setelah database Hoopball sudah dibuat dan tabel
-- Pembatalan_Booking, Booking, dan Jadwal sudah ada.
-- ============================================================================

USE Hoopball;
GO

-- ============================================================================
-- PART 1: STORED PROCEDURE - Transaksi Pembatalan Booking
-- ============================================================================
-- SP ini menangani seluruh proses bisnis pembatalan booking:
-- 1. Validasi kepemilikan booking & batas waktu 24 jam
-- 2. Validasi status booking (tidak boleh sudah dibatalkan)
-- 3. Hitung denda 50% dan refund 50%
-- 4. Insert ke tabel Pembatalan_Booking
-- 5. Update status Booking -> 3 (Dibatalkan)
-- 6. Update status Jadwal -> 1 (Tersedia kembali)
-- 7. Return hasil transaksi
-- ============================================================================

CREATE OR ALTER PROCEDURE sp_TransaksiPembatalan
    @ID_Booking         INT,
    @ID_Customer        INT,
    @Alasan             VARCHAR(255),
    @Created_By         VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE @ID_Jadwal          INT;
    DECLARE @ID_Karyawan        INT;
    DECLARE @Total_Bayar        DECIMAL(18,2);
    DECLARE @Metode_Pembayaran  VARCHAR(20);
    DECLARE @StatusBooking      INT;
    DECLARE @Tanggal_Jadwal     DATE;
    DECLARE @Jam_Mulai          TIME;
    DECLARE @PlayDateTime       DATETIME;
    DECLARE @Now                DATETIME = GETDATE();
    DECLARE @DiffSeconds        BIGINT;
    DECLARE @Biaya_Batal        DECIMAL(18,2);
    DECLARE @Nominal_Refund     DECIMAL(18,2);
    DECLARE @ID_Pembatalan      INT;

    -- ========================================================================
    -- VALIDASI 1: Cek apakah booking ada dan milik customer yang bersangkutan
    -- ========================================================================
    SELECT 
        @ID_Jadwal = B.ID_Jadwal,
        @ID_Karyawan = B.ID_Karyawan,
        @Total_Bayar = B.Total_Bayar,
        @Metode_Pembayaran = B.Metode_Pembayaran,
        @StatusBooking = B.Status,
        @Tanggal_Jadwal = J.Tanggal,
        @Jam_Mulai = J.Jam_Mulai
    FROM Booking B
    INNER JOIN Jadwal J ON B.ID_Jadwal = J.ID_Jadwal
    WHERE B.ID_Booking = @ID_Booking AND B.ID_Customer = @ID_Customer;

    IF @ID_Jadwal IS NULL
    BEGIN
        SELECT 
            0 AS Success,
            'Data pemesanan tidak ditemukan atau bukan milik Anda.' AS Message,
            NULL AS ID_Pembatalan;
        RETURN;
    END

    -- ========================================================================
    -- VALIDASI 2: Cek apakah booking sudah dibatalkan sebelumnya
    -- ========================================================================
    IF @StatusBooking = 3
    BEGIN
        SELECT 
            0 AS Success,
            'Pemesanan ini sudah dibatalkan sebelumnya.' AS Message,
            NULL AS ID_Pembatalan;
        RETURN;
    END

    -- ========================================================================
    -- VALIDASI 3 & HITUNG BIAYA SECARA DINAMIS (Hanya Denda 50%)
    -- ========================================================================
    SET @PlayDateTime = CAST(@Tanggal_Jadwal AS DATETIME) + CAST(@Jam_Mulai AS DATETIME);
    SET @DiffSeconds = DATEDIFF(SECOND, @Now, @PlayDateTime);

    -- Batas waktu pembatalan minimal adalah 12 Jam sebelum bermain (kurang dari itu ditolak)
    IF @DiffSeconds < 43200 
    BEGIN
        SELECT 
            0 AS Success,
            'Pembatalan ditolak. Batas waktu pembatalan paling lambat adalah 12 jam sebelum jadwal bermain.' AS Message,
            NULL AS ID_Pembatalan;
        RETURN;
    END

    -- Di atas 12 Jam sebelum bermain, semua dikenakan denda FLAT 50%
    SET @Biaya_Batal = @Total_Bayar * 0.50;
    SET @Nominal_Refund = @Total_Bayar * 0.50;

    -- ========================================================================
    -- VALIDASI 4: Cek apakah sudah ada pembatalan untuk booking ini
    -- ========================================================================
    IF EXISTS (SELECT 1 FROM Pembatalan_Booking WHERE ID_Booking = @ID_Booking)
    BEGIN
        SELECT 
            0 AS Success,
            'Pengajuan pembatalan untuk booking ini sudah pernah dibuat.' AS Message,
            NULL AS ID_Pembatalan;
        RETURN;
    END

    -- ========================================================================
    -- BEGIN TRANSACTION
    -- ========================================================================
    BEGIN TRANSACTION;

    BEGIN TRY
        -- 1. Insert ke tabel Pembatalan_Booking
        INSERT INTO Pembatalan_Booking 
            (ID_Booking, ID_Karyawan, Tanggal_Batal, Alasan, 
             Biaya_Batal, Nominal_Refund, Metode_Refund, Status, 
             Created_By, Created_Date)
        VALUES 
            (@ID_Booking, @ID_Karyawan, CAST(@Now AS DATE), @Alasan,
             @Biaya_Batal, @Nominal_Refund, @Metode_Pembayaran, 0,
             @Created_By, @Now);

        SET @ID_Pembatalan = SCOPE_IDENTITY();

        -- 2. Update status Booking -> 3 (Dibatalkan)
        UPDATE Booking 
        SET Status = 3, 
            Modified_By = @Created_By, 
            Modified_Date = @Now 
        WHERE ID_Booking = @ID_Booking;

        -- 3. Update status Jadwal -> 1 (Tersedia kembali)
        UPDATE Jadwal 
        SET Status = 1, 
            Modified_By = @Created_By, 
            Modified_Date = @Now 
        WHERE ID_Jadwal = @ID_Jadwal;

        COMMIT TRANSACTION;

        SELECT 
            1 AS Success,
            'Pembatalan booking berhasil diproses. Dana refund (50%) akan segera diproses oleh operator.' AS Message,
            @ID_Pembatalan AS ID_Pembatalan,
            @Biaya_Batal AS Biaya_Batal,
            @Nominal_Refund AS Nominal_Refund;

    END TRY
    BEGIN CATCH
        ROLLBACK TRANSACTION;

        SELECT 
            0 AS Success,
            'Terjadi kesalahan sistem: ' + ERROR_MESSAGE() AS Message,
            NULL AS ID_Pembatalan;
    END CATCH
END;
GO


-- ============================================================================
-- PART 2: TRIGGER - Log History & Business Logic
-- ============================================================================

-- ============================================================================
-- 2.1 TABEL LOG: Log_Pembatalan_Booking
-- ============================================================================
IF OBJECT_ID('dbo.Log_Pembatalan_Booking', 'U') IS NULL
BEGIN
    CREATE TABLE Log_Pembatalan_Booking (
        Log_ID              INT IDENTITY(1,1) PRIMARY KEY,
        Action_Type         VARCHAR(10)     NOT NULL,
        Action_DateTime     DATETIME        NOT NULL DEFAULT GETDATE(),
        Action_By           VARCHAR(50)     NULL,

        -- Data lama (untuk UPDATE dan DELETE)
        Old_ID_Pembatalan   INT             NULL,
        Old_ID_Booking      INT             NULL,
        Old_ID_Karyawan     INT             NULL,
        Old_Tanggal_Batal   DATE            NULL,
        Old_Alasan          VARCHAR(255)    NULL,
        Old_Biaya_Batal     DECIMAL(18,2)   NULL,
        Old_Nominal_Refund  DECIMAL(18,2)   NULL,
        Old_Metode_Refund   VARCHAR(20)     NULL,
        Old_Status          INT             NULL,
        Old_Created_By      VARCHAR(50)     NULL,
        Old_Created_Date    DATETIME        NULL,
        Old_Modified_By     VARCHAR(50)     NULL,
        Old_Modified_Date   DATETIME        NULL,

        -- Data baru (untuk INSERT dan UPDATE)
        New_ID_Pembatalan   INT             NULL,
        New_ID_Booking      INT             NULL,
        New_ID_Karyawan     INT             NULL,
        New_Tanggal_Batal   DATE            NULL,
        New_Alasan          VARCHAR(255)    NULL,
        New_Biaya_Batal     DECIMAL(18,2)   NULL,
        New_Nominal_Refund  DECIMAL(18,2)   NULL,
        New_Metode_Refund   VARCHAR(20)     NULL,
        New_Status          INT             NULL,
        New_Created_By      VARCHAR(50)     NULL,
        New_Created_Date    DATETIME        NULL,
        New_Modified_By     VARCHAR(50)     NULL,
        New_Modified_Date   DATETIME        NULL
    );
END
GO

-- ============================================================================
-- TRIGGER 1: trg_Pembatalan_Booking_Log (AFTER INSERT, UPDATE, DELETE)
-- Log history lengkap untuk tabel Pembatalan_Booking
-- ============================================================================
CREATE OR ALTER TRIGGER trg_Pembatalan_Booking_Log
ON Pembatalan_Booking
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

    -- INSERT
    IF @ActionType = 'INSERT'
    BEGIN
        INSERT INTO Log_Pembatalan_Booking (
            Action_Type, Action_DateTime, Action_By,
            New_ID_Pembatalan, New_ID_Booking, New_ID_Karyawan,
            New_Tanggal_Batal, New_Alasan, New_Biaya_Batal,
            New_Nominal_Refund, New_Metode_Refund, New_Status,
            New_Created_By, New_Created_Date, New_Modified_By, New_Modified_Date
        )
        SELECT 
            @ActionType, GETDATE(), @ActionBy,
            ID_Pembatalan, ID_Booking, ID_Karyawan,
            Tanggal_Batal, Alasan, Biaya_Batal,
            Nominal_Refund, Metode_Refund, Status,
            Created_By, Created_Date, Modified_By, Modified_Date
        FROM inserted;
    END

    -- UPDATE
    IF @ActionType = 'UPDATE'
    BEGIN
        INSERT INTO Log_Pembatalan_Booking (
            Action_Type, Action_DateTime, Action_By,
            Old_ID_Pembatalan, Old_ID_Booking, Old_ID_Karyawan,
            Old_Tanggal_Batal, Old_Alasan, Old_Biaya_Batal,
            Old_Nominal_Refund, Old_Metode_Refund, Old_Status,
            Old_Created_By, Old_Created_Date, Old_Modified_By, Old_Modified_Date,
            New_ID_Pembatalan, New_ID_Booking, New_ID_Karyawan,
            New_Tanggal_Batal, New_Alasan, New_Biaya_Batal,
            New_Nominal_Refund, New_Metode_Refund, New_Status,
            New_Created_By, New_Created_Date, New_Modified_By, New_Modified_Date
        )
        SELECT 
            @ActionType, GETDATE(), @ActionBy,
            d.ID_Pembatalan, d.ID_Booking, d.ID_Karyawan,
            d.Tanggal_Batal, d.Alasan, d.Biaya_Batal,
            d.Nominal_Refund, d.Metode_Refund, d.Status,
            d.Created_By, d.Created_Date, d.Modified_By, d.Modified_Date,
            i.ID_Pembatalan, i.ID_Booking, i.ID_Karyawan,
            i.Tanggal_Batal, i.Alasan, i.Biaya_Batal,
            i.Nominal_Refund, i.Metode_Refund, i.Status,
            i.Created_By, i.Created_Date, i.Modified_By, i.Modified_Date
        FROM deleted d
        INNER JOIN inserted i ON d.ID_Pembatalan = i.ID_Pembatalan;
    END

    -- DELETE
    IF @ActionType = 'DELETE'
    BEGIN
        INSERT INTO Log_Pembatalan_Booking (
            Action_Type, Action_DateTime, Action_By,
            Old_ID_Pembatalan, Old_ID_Booking, Old_ID_Karyawan,
            Old_Tanggal_Batal, Old_Alasan, Old_Biaya_Batal,
            Old_Nominal_Refund, Old_Metode_Refund, Old_Status,
            Old_Created_By, Old_Created_Date, Old_Modified_By, Old_Modified_Date
        )
        SELECT 
            @ActionType, GETDATE(), @ActionBy,
            ID_Pembatalan, ID_Booking, ID_Karyawan,
            Tanggal_Batal, Alasan, Biaya_Batal,
            Nominal_Refund, Metode_Refund, Status,
            Created_By, Created_Date, Modified_By, Modified_Date
        FROM deleted;
    END
END;
GO

-- ============================================================================
-- 2.2 TABEL LOG: Log_Booking_Status
-- ============================================================================
IF OBJECT_ID('dbo.Log_Booking_Status', 'U') IS NULL
BEGIN
    CREATE TABLE Log_Booking_Status (
        Log_ID              INT IDENTITY(1,1) PRIMARY KEY,
        Action_Type         VARCHAR(10)     NOT NULL,
        Action_DateTime     DATETIME        NOT NULL DEFAULT GETDATE(),
        Action_By           VARCHAR(50)     NULL,
        ID_Booking          INT             NOT NULL,
        Old_Status          INT             NULL,
        New_Status          INT             NULL,
        Old_Status_Label    VARCHAR(50)     NULL,
        New_Status_Label    VARCHAR(50)     NULL,
        Modified_By         VARCHAR(50)     NULL,
        Modified_Date       DATETIME        NULL
    );
END
GO

-- ============================================================================
-- TRIGGER 2: trg_Booking_Status_Log (AFTER UPDATE pada Booking)
-- Log history perubahan status booking
-- ============================================================================
CREATE OR ALTER TRIGGER trg_Booking_Status_Log
ON Booking
AFTER UPDATE
AS
BEGIN
    SET NOCOUNT ON;

    IF UPDATE(Status)
    BEGIN
        INSERT INTO Log_Booking_Status (
            Action_Type, Action_DateTime, Action_By,
            ID_Booking, Old_Status, New_Status,
            Old_Status_Label, New_Status_Label,
            Modified_By, Modified_Date
        )
        SELECT 
            'UPDATE', GETDATE(), SUSER_SNAME(),
            i.ID_Booking, d.Status, i.Status,
            CASE d.Status 
                WHEN 0 THEN 'Menunggu Konfirmasi'
                WHEN 1 THEN 'Berhasil'
                WHEN 2 THEN 'Selesai'
                WHEN 3 THEN 'Dibatalkan'
                ELSE 'Unknown'
            END,
            CASE i.Status 
                WHEN 0 THEN 'Menunggu Konfirmasi'
                WHEN 1 THEN 'Berhasil'
                WHEN 2 THEN 'Selesai'
                WHEN 3 THEN 'Dibatalkan'
                ELSE 'Unknown'
            END,
            i.Modified_By, i.Modified_Date
        FROM inserted i
        INNER JOIN deleted d ON i.ID_Booking = d.ID_Booking
        WHERE d.Status <> i.Status;
    END
END;
GO

-- ============================================================================
-- 2.3 TABEL LOG: Log_Jadwal_Status
-- ============================================================================
IF OBJECT_ID('dbo.Log_Jadwal_Status', 'U') IS NULL
BEGIN
    CREATE TABLE Log_Jadwal_Status (
        Log_ID              INT IDENTITY(1,1) PRIMARY KEY,
        Action_Type         VARCHAR(10)     NOT NULL,
        Action_DateTime     DATETIME        NOT NULL DEFAULT GETDATE(),
        Action_By           VARCHAR(50)     NULL,
        ID_Jadwal           INT             NOT NULL,
        ID_Lapangan         INT             NULL,
        Tanggal             DATE            NULL,
        Jam_Mulai           TIME            NULL,
        Jam_Selesai         TIME            NULL,
        Old_Status          INT             NULL,
        New_Status          INT             NULL,
        Old_Status_Label    VARCHAR(50)     NULL,
        New_Status_Label    VARCHAR(50)     NULL,
        Modified_By         VARCHAR(50)     NULL,
        Modified_Date       DATETIME        NULL
    );
END
GO

-- ============================================================================
-- TRIGGER 3: trg_Jadwal_Status_Log (AFTER UPDATE pada Jadwal)
-- Log history perubahan status jadwal
-- ============================================================================
CREATE OR ALTER TRIGGER trg_Jadwal_Status_Log
ON Jadwal
AFTER UPDATE
AS
BEGIN
    SET NOCOUNT ON;

    IF UPDATE(Status)
    BEGIN
        INSERT INTO Log_Jadwal_Status (
            Action_Type, Action_DateTime, Action_By,
            ID_Jadwal, ID_Lapangan, Tanggal, Jam_Mulai, Jam_Selesai,
            Old_Status, New_Status,
            Old_Status_Label, New_Status_Label,
            Modified_By, Modified_Date
        )
        SELECT 
            'UPDATE', GETDATE(), SUSER_SNAME(),
            i.ID_Jadwal, i.ID_Lapangan, i.Tanggal, i.Jam_Mulai, i.Jam_Selesai,
            d.Status, i.Status,
            CASE d.Status WHEN 0 THEN 'Tidak Tersedia' WHEN 1 THEN 'Tersedia' ELSE 'Unknown' END,
            CASE i.Status WHEN 0 THEN 'Tidak Tersedia' WHEN 1 THEN 'Tersedia' ELSE 'Unknown' END,
            i.Modified_By, i.Modified_Date
        FROM inserted i
        INNER JOIN deleted d ON i.ID_Jadwal = d.ID_Jadwal
        WHERE d.Status <> i.Status;
    END
END;
GO

-- ============================================================================
-- TRIGGER 4: trg_Pembatalan_Booking_AutoUpdate (INSTEAD OF UPDATE)
-- Business Logic: Auto-update Modified_By dan Modified_Date
-- ============================================================================
CREATE OR ALTER TRIGGER trg_Pembatalan_Booking_AutoUpdate
ON Pembatalan_Booking
INSTEAD OF UPDATE
AS
BEGIN
    SET NOCOUNT ON;

    UPDATE P
    SET 
        P.Alasan = i.Alasan,
        P.Biaya_Batal = i.Biaya_Batal,
        P.Nominal_Refund = i.Nominal_Refund,
        P.Metode_Refund = i.Metode_Refund,
        P.Status = i.Status,
        P.Modified_By = COALESCE(i.Modified_By, SUSER_SNAME()),
        P.Modified_Date = GETDATE()
    FROM Pembatalan_Booking P
    INNER JOIN inserted i ON P.ID_Pembatalan = i.ID_Pembatalan;
END;
GO


-- ============================================================================
-- PART 3: VERIFIKASI
-- ============================================================================

-- Cek Stored Procedure
SELECT 
    name AS ProcedureName,
    create_date AS CreatedDate,
    modify_date AS LastModified
FROM sys.procedures
WHERE name = 'sp_TransaksiPembatalan';

-- Cek Triggers (dengan event info dari sys.trigger_events)
SELECT 
    t.name AS TriggerName,
    OBJECT_NAME(t.parent_id) AS TableName,
    CASE t.is_instead_of_trigger WHEN 1 THEN 'INSTEAD OF' ELSE 'AFTER' END AS TriggerType,
    STUFF((
        SELECT ', ' + 
            CASE te.type 
                WHEN 1 THEN 'INSERT' 
                WHEN 2 THEN 'UPDATE' 
                WHEN 3 THEN 'DELETE' 
                ELSE 'UNKNOWN' 
            END
        FROM sys.trigger_events te
        WHERE te.object_id = t.object_id
        ORDER BY te.type
        FOR XML PATH(''), TYPE
    ).value('.', 'NVARCHAR(MAX)'), 1, 2, '') AS Events,
    t.create_date AS CreatedDate
FROM sys.triggers t
WHERE t.parent_id IN (
    OBJECT_ID('Pembatalan_Booking'),
    OBJECT_ID('Booking'),
    OBJECT_ID('Jadwal')
)
ORDER BY TableName, TriggerName;

-- Cek Tabel Log
SELECT 
    t.name AS TableName,
    t.create_date AS CreatedDate
FROM sys.tables t
WHERE t.name LIKE 'Log_%'
ORDER BY t.name;
GO


-- 1. UDF UNTUK MENGAMBIL RIWAYAT BOOKING CUSTOMER (Dashboard Data Source)
IF OBJECT_ID('dbo.fn_Booking_GetCustomerHistory', 'IF') IS NOT NULL
    DROP FUNCTION dbo.fn_Booking_GetCustomerHistory;
GO

CREATE FUNCTION fn_Booking_GetCustomerHistory (@ID_Customer INT)
RETURNS TABLE
AS
RETURN
(
    SELECT B.ID_Booking, B.ID_Jadwal, B.Tanggal_Booking, B.Metode_Pembayaran, B.Total_Bayar, B.Status AS StatusBooking,
           J.Tanggal, J.Jam_Mulai, J.Jam_Selesai, L.Nama_Lapangan, L.Photo_Lapangan, L.Harga_Sewa,
           P.Nominal_Refund, P.Biaya_Batal, P.Status AS StatusRefund
    FROM Booking B
    INNER JOIN Jadwal J ON B.ID_Jadwal = J.ID_Jadwal
    INNER JOIN Lapangan L ON J.ID_Lapangan = L.ID_Lapangan
    LEFT JOIN Pembatalan_Booking P ON B.ID_Booking = P.ID_Booking
    WHERE B.ID_Customer = @ID_Customer
);
GO

-- 2. SP UNTUK CEK MEMBER AKTIF (Menghindari raw query di PHP)
IF EXISTS (SELECT * FROM sys.procedures WHERE name = 'sp_Customer_GetActiveMember')
    DROP PROCEDURE sp_Customer_GetActiveMember;
GO

CREATE PROCEDURE sp_Customer_GetActiveMember
    @ID_Customer INT
AS
BEGIN
    SET NOCOUNT ON;
    SELECT TOP 1 L.ID_Langganan, L.ID_Tipe, L.Tanggal_Mulai, L.Tanggal_Selesai, L.Status, T.Nama_Tipe 
    FROM Langganan L
    INNER JOIN Tipe_Member T ON L.ID_Tipe = T.ID_Tipe
    WHERE L.ID_Customer = @ID_Customer AND L.Status = 1
    AND GETDATE() BETWEEN L.Tanggal_Mulai AND L.Tanggal_Selesai;
END;
GO


USE Hoopball;
GO

-- 1. SP Baru: Mengambil Detail Pembatalan berdasarkan ID
CREATE OR ALTER PROCEDURE sp_Pembatalan_GetByID
    @ID_Pembatalan INT
AS
BEGIN
    SET NOCOUNT ON;
    SELECT P.ID_Pembatalan, P.ID_Booking, P.Tanggal_Batal, P.Alasan, 
           P.Biaya_Batal, P.Nominal_Refund, P.Metode_Refund, P.Status AS StatusRefund,
           P.Created_Date, P.Modified_Date,
           B.Total_Bayar AS Total_Booking_Awal, B.Metode_Pembayaran AS Metode_Bayar_Awal,
           C.Nama_Customer, C.Email, C.No_Telepon,
           L.Nama_Lapangan,
           J.Tanggal, J.Jam_Mulai, J.Jam_Selesai,
           K.Nama_Karyawan AS Nama_Karyawan_Proses
    FROM Pembatalan_Booking P
    INNER JOIN Booking B ON P.ID_Booking = B.ID_Booking
    INNER JOIN Customer C ON B.ID_Customer = C.ID_Customer
    INNER JOIN Jadwal J ON B.ID_Jadwal = J.ID_Jadwal
    INNER JOIN Lapangan L ON J.ID_Lapangan = L.ID_Lapangan
    LEFT JOIN Karyawan K ON P.ID_Karyawan = K.ID_Karyawan
    WHERE P.ID_Pembatalan = @ID_Pembatalan;
END;
GO

-- 2. SP Baru: Konfirmasi Pembayaran Refund oleh Karyawan
CREATE OR ALTER PROCEDURE sp_Pembatalan_ConfirmRefund
    @ID_Pembatalan INT,
    @Modified_By VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;
    UPDATE Pembatalan_Booking 
    SET Status = 1, 
        Modified_By = @Modified_By, 
        Modified_Date = GETDATE() 
    WHERE ID_Pembatalan = @ID_Pembatalan AND Status = 0;
END;
GO

-- 3. UDF Baru: Mengambil List Pengajuan Pembatalan Terfilter untuk Dashboard Kelola (Karyawan)
CREATE OR ALTER FUNCTION dbo.fn_Pembatalan_GetList (
    @FilterStatus INT,
    @SearchKeyword VARCHAR(50),
    @FilterTanggal DATE
)
RETURNS TABLE
AS
RETURN
(
    SELECT P.ID_Pembatalan, P.ID_Booking, P.Tanggal_Batal, P.Alasan, 
           P.Biaya_Batal, P.Nominal_Refund, P.Metode_Refund, P.Status AS StatusRefund,
           C.Nama_Customer, C.Email, L.Nama_Lapangan,
           J.Tanggal, J.Jam_Mulai, J.Jam_Selesai
    FROM Pembatalan_Booking P
    INNER JOIN Booking B ON P.ID_Booking = B.ID_Booking
    INNER JOIN Customer C ON B.ID_Customer = C.ID_Customer
    INNER JOIN Jadwal J ON B.ID_Jadwal = J.ID_Jadwal
    INNER JOIN Lapangan L ON J.ID_Lapangan = L.ID_Lapangan
    WHERE (@FilterStatus IS NULL OR P.Status = @FilterStatus)
      AND (@FilterTanggal IS NULL OR CAST(P.Tanggal_Batal AS DATE) = @FilterTanggal)
      AND (@SearchKeyword = '' OR C.Nama_Customer LIKE '%' + @SearchKeyword + '%' OR L.Nama_Lapangan LIKE '%' + @SearchKeyword + '%')
);
GO

-- ============================================================================
-- PART 4: CONTOH PENGGUNAAN SP
-- ============================================================================
/*
-- Contoh 1: Pembatalan berhasil (booking ID 8, customer ID 1)
EXEC sp_TransaksiPembatalan 
    @ID_Booking = 8, 
    @ID_Customer = 1, 
    @Alasan = 'Ada keperluan mendadak', 
    @Created_By = 'Dimas Arya';

-- Contoh 2: Pembatalan gagal - booking sudah dibatalkan
EXEC sp_TransaksiPembatalan 
    @ID_Booking = 6, 
    @ID_Customer = 6, 
    @Alasan = 'Jadwal bentrok', 
    @Created_By = 'Rini Kusuma';

-- Contoh 3: Pembatalan gagal - booking tidak ditemukan
EXEC sp_TransaksiPembatalan 
    @ID_Booking = 999, 
    @ID_Customer = 1, 
    @Alasan = 'Testing', 
    @Created_By = 'Test User';

-- Cek log history
SELECT * FROM Log_Pembatalan_Booking ORDER BY Log_ID DESC;
SELECT * FROM Log_Booking_Status ORDER BY Log_ID DESC;
SELECT * FROM Log_Jadwal_Status ORDER BY Log_ID DESC;
*/
GO
