-- ============================================================================
-- STORED PROCEDURE TRANSAKSI LANGGANAN - HOOPBALL
-- ============================================================================
-- Database: Hoopball
-- Dibuat: 2026-07-11
-- Fitur:
--   1. SP_CreateLangganan      - Pendaftaran langganan baru oleh customer
--   2. SP_KonfirmasiLangganan  - Konfirmasi pembayaran oleh karyawan
--   3. SP_TolakLangganan       - Penolakan langganan oleh karyawan
--   4. SP_UpdateLangganan      - Update data langganan (admin)
--   5. SP_SoftDeleteLangganan  - Soft delete langganan
--   6. SP_GetLanggananByID     - Detail langganan by ID
--   7. SP_GetLanggananByCustomer - Riwayat langganan customer
--   8. SP_GetLanggananPending  - Daftar menunggu konfirmasi
--   9. SP_GetLanggananAktif    - Daftar langganan aktif
--  10. SP_AutoExpireLangganan  - Auto update status expired
-- ============================================================================

USE Hoopball;
GO

-- ============================================================================
-- 1. TABEL LOG HISTORY (untuk trigger)
-- ============================================================================
IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'Log_Langganan_History')
BEGIN
    CREATE TABLE Log_Langganan_History (
        Log_ID              INT IDENTITY(1,1) PRIMARY KEY,
        ID_Langganan        INT             NOT NULL,
        Action_Type         VARCHAR(20)     NOT NULL,   -- INSERT, UPDATE, DELETE, CONFIRM, REJECT, EXPIRE
        Action_By           VARCHAR(50)     NOT NULL,
        Action_Date         DATETIME        NOT NULL DEFAULT GETDATE(),
        
        -- Data lama (OLD)
        Old_ID_Customer     INT             NULL,
        Old_ID_Karyawan     INT             NULL,
        Old_ID_Tipe         INT             NULL,
        Old_Tanggal_Mulai   DATE            NULL,
        Old_Tanggal_Selesai DATE            NULL,
        Old_Total_Bayar     DECIMAL(18,2)   NULL,
        Old_Metode_Pembayaran VARCHAR(20)   NULL,
        Old_Status          INT             NULL,
        
        -- Data baru (NEW)
        New_ID_Customer     INT             NULL,
        New_ID_Karyawan     INT             NULL,
        New_ID_Tipe         INT             NULL,
        New_Tanggal_Mulai   DATE            NULL,
        New_Tanggal_Selesai DATE            NULL,
        New_Total_Bayar     DECIMAL(18,2)   NULL,
        New_Metode_Pembayaran VARCHAR(20)   NULL,
        New_Status          INT             NULL,
        
        Keterangan          VARCHAR(255)    NULL,
        IP_Address          VARCHAR(50)     NULL
    );
    
    CREATE INDEX IX_Log_Langganan_History_ID_Langganan ON Log_Langganan_History(ID_Langganan);
    CREATE INDEX IX_Log_Langganan_History_Action_Date ON Log_Langganan_History(Action_Date);
    CREATE INDEX IX_Log_Langganan_History_Action_Type ON Log_Langganan_History(Action_Type);
END
GO

-- ============================================================================
-- 2. TRIGGER: TR_Langganan_AuditLog (INSERT, UPDATE, DELETE)
-- ============================================================================
-- Trigger ini mencatat SEMUA perubahan pada tabel Langganan ke Log_Langganan_History
-- ============================================================================

IF EXISTS (SELECT * FROM sys.triggers WHERE name = 'TR_Langganan_AuditLog')
    DROP TRIGGER TR_Langganan_AuditLog;
GO

CREATE TRIGGER TR_Langganan_AuditLog
ON Langganan
AFTER INSERT, UPDATE, DELETE
AS
BEGIN
    SET NOCOUNT ON;
    
    DECLARE @ActionType VARCHAR(20);
    DECLARE @ActionBy VARCHAR(50);
    
    -- Tentukan jenis aksi
    IF EXISTS (SELECT 1 FROM inserted) AND EXISTS (SELECT 1 FROM deleted)
        SET @ActionType = 'UPDATE';
    ELSE IF EXISTS (SELECT 1 FROM inserted)
        SET @ActionType = 'INSERT';
    ELSE
        SET @ActionType = 'DELETE';
    
    -- Ambil Action_By dari session/context jika tersedia, default SYSTEM
    SET @ActionBy = ISNULL(CONVERT(VARCHAR(50), SESSION_CONTEXT(N'UserName')), 'SYSTEM');
    
    -- INSERT: Catat data baru
    IF @ActionType = 'INSERT'
    BEGIN
        INSERT INTO Log_Langganan_History (
            ID_Langganan, Action_Type, Action_By,
            New_ID_Customer, New_ID_Karyawan, New_ID_Tipe,
            New_Tanggal_Mulai, New_Tanggal_Selesai,
            New_Total_Bayar, New_Metode_Pembayaran, New_Status,
            Keterangan
        )
        SELECT 
            i.ID_Langganan, @ActionType, @ActionBy,
            i.ID_Customer, i.ID_Karyawan, i.ID_Tipe,
            i.Tanggal_Mulai, i.Tanggal_Selesai,
            i.Total_Bayar, i.Metode_Pembayaran, i.Status,
            'Pendaftaran langganan baru oleh customer'
        FROM inserted i;
    END
    
    -- UPDATE: Catat data lama dan data baru
    ELSE IF @ActionType = 'UPDATE'
    BEGIN
        INSERT INTO Log_Langganan_History (
            ID_Langganan, Action_Type, Action_By,
            Old_ID_Customer, Old_ID_Karyawan, Old_ID_Tipe,
            Old_Tanggal_Mulai, Old_Tanggal_Selesai,
            Old_Total_Bayar, Old_Metode_Pembayaran, Old_Status,
            New_ID_Customer, New_ID_Karyawan, New_ID_Tipe,
            New_Tanggal_Mulai, New_Tanggal_Selesai,
            New_Total_Bayar, New_Metode_Pembayaran, New_Status,
            Keterangan
        )
        SELECT 
            i.ID_Langganan, @ActionType, @ActionBy,
            d.ID_Customer, d.ID_Karyawan, d.ID_Tipe,
            d.Tanggal_Mulai, d.Tanggal_Selesai,
            d.Total_Bayar, d.Metode_Pembayaran, d.Status,
            i.ID_Customer, i.ID_Karyawan, i.ID_Tipe,
            i.Tanggal_Mulai, i.Tanggal_Selesai,
            i.Total_Bayar, i.Metode_Pembayaran, i.Status,
            CASE 
                WHEN d.Status = 0 AND i.Status = 1 THEN 'Konfirmasi pembayaran oleh karyawan'
                WHEN d.Status = 0 AND i.Status = 3 THEN 'Penolakan langganan oleh karyawan'
                WHEN d.Status = 1 AND i.Status = 2 THEN 'Langganan expired (masa aktif habis)'
                ELSE 'Update data langganan'
            END
        FROM inserted i
        INNER JOIN deleted d ON i.ID_Langganan = d.ID_Langganan;
    END
    
    -- DELETE: Catat data yang dihapus (hard delete)
    ELSE IF @ActionType = 'DELETE'
    BEGIN
        INSERT INTO Log_Langganan_History (
            ID_Langganan, Action_Type, Action_By,
            Old_ID_Customer, Old_ID_Karyawan, Old_ID_Tipe,
            Old_Tanggal_Mulai, Old_Tanggal_Selesai,
            Old_Total_Bayar, Old_Metode_Pembayaran, Old_Status,
            Keterangan
        )
        SELECT 
            d.ID_Langganan, @ActionType, @ActionBy,
            d.ID_Customer, d.ID_Karyawan, d.ID_Tipe,
            d.Tanggal_Mulai, d.Tanggal_Selesai,
            d.Total_Bayar, d.Metode_Pembayaran, d.Status,
            'Penghapusan data langganan'
        FROM deleted d;
    END
END
GO

-- ============================================================================
-- 3. TRIGGER: TR_Langganan_ValidasiStatus
-- ============================================================================
-- Trigger ini mencegah perubahan status yang tidak valid
-- Status flow: 0 (Menunggu) -> 1 (Aktif) -> 2 (Berakhir)
--              0 (Menunggu) -> 3 (Ditolak)
-- ============================================================================

IF EXISTS (SELECT * FROM sys.triggers WHERE name = 'TR_Langganan_ValidasiStatus')
    DROP TRIGGER TR_Langganan_ValidasiStatus;
GO

CREATE TRIGGER TR_Langganan_ValidasiStatus
ON Langganan
AFTER UPDATE
AS
BEGIN
    SET NOCOUNT ON;
    
    -- Validasi flow status
    IF EXISTS (
        SELECT 1 
        FROM inserted i
        INNER JOIN deleted d ON i.ID_Langganan = d.ID_Langganan
        WHERE 
            -- Tidak boleh kembali ke Menunggu setelah dikonfirmasi/ditolak
            (d.Status IN (1, 3) AND i.Status = 0)
            OR
            -- Tidak boleh Aktif setelah Ditolak
            (d.Status = 3 AND i.Status = 1)
            OR
            -- Tidak boleh Ditolak setelah Aktif
            (d.Status = 1 AND i.Status = 3)
            OR
            -- Tidak boleh kembali ke Aktif setelah Berakhir
            (d.Status = 2 AND i.Status = 1)
    )
    BEGIN
        RAISERROR ('Transisi status langganan tidak valid!', 16, 1);
        ROLLBACK TRANSACTION;
        RETURN;
    END
    
    -- Jika status berubah menjadi Aktif (1), update Modified_Date
    IF EXISTS (
        SELECT 1 FROM inserted i
        INNER JOIN deleted d ON i.ID_Langganan = d.ID_Langganan
        WHERE d.Status = 0 AND i.Status = 1
    )
    BEGIN
        UPDATE Langganan
        SET Modified_Date = GETDATE()
        WHERE ID_Langganan IN (SELECT ID_Langganan FROM inserted WHERE Status = 1);
    END
END
GO

-- ============================================================================
-- 4. STORED PROCEDURE: SP_CreateLangganan
-- ============================================================================
-- Customer mendaftar langganan baru
-- Status awal: 0 (Menunggu Konfirmasi)
-- ============================================================================

IF EXISTS (SELECT * FROM sys.procedures WHERE name = 'SP_CreateLangganan')
    DROP PROCEDURE SP_CreateLangganan;
GO

CREATE PROCEDURE SP_CreateLangganan
    @ID_Customer         INT,
    @ID_Tipe             INT,
    @Metode_Pembayaran   VARCHAR(20),
    @Created_By          VARCHAR(50) = NULL
AS
BEGIN
    SET NOCOUNT ON;
    
    BEGIN TRY
        BEGIN TRANSACTION;
        
        -- Validasi: Customer harus aktif
        IF NOT EXISTS (
            SELECT 1 FROM Customer 
            WHERE ID_Customer = @ID_Customer 
            AND Status = 1 AND Is_Deleted = 0
        )
        BEGIN
            RAISERROR ('Customer tidak ditemukan atau akun dinonaktifkan!', 16, 1);
            ROLLBACK TRANSACTION;
            RETURN;
        END
        
        -- Validasi: Tipe Member harus aktif
        DECLARE @Harga_Member DECIMAL(18,2), @Potongan_Harga DECIMAL(18,2);
        SELECT @Harga_Member = Harga_Member, @Potongan_Harga = Potongan_Harga
        FROM Tipe_Member 
        WHERE ID_Tipe = @ID_Tipe AND Status = 1 AND Is_Deleted = 0;
        
        IF @Harga_Member IS NULL
        BEGIN
            RAISERROR ('Tipe member tidak valid atau tidak aktif!', 16, 1);
            ROLLBACK TRANSACTION;
            RETURN;
        END
        
        -- Validasi: Tidak boleh ada langganan aktif
        IF EXISTS (
            SELECT 1 FROM Langganan
            WHERE ID_Customer = @ID_Customer AND Status = 1
            AND GETDATE() BETWEEN Tanggal_Mulai AND Tanggal_Selesai
        )
        BEGIN
            RAISERROR ('Customer masih memiliki langganan aktif!', 16, 1);
            ROLLBACK TRANSACTION;
            RETURN;
        END
        
        -- Validasi: Tidak boleh ada pendaftaran pending
        IF EXISTS (
            SELECT 1 FROM Langganan
            WHERE ID_Customer = @ID_Customer AND Status = 0
        )
        BEGIN
            RAISERROR ('Customer memiliki pendaftaran yang menunggu konfirmasi!', 16, 1);
            ROLLBACK TRANSACTION;
            RETURN;
        END
        
        -- Set default karyawan (system/admin) untuk input awal
        DECLARE @ID_Karyawan INT = 2; -- Default admin/system
        
        -- Hitung tanggal
        DECLARE @Tanggal_Mulai DATE = CAST(GETDATE() AS DATE);
        DECLARE @Tanggal_Selesai DATE = DATEADD(DAY, 30, @Tanggal_Mulai);
        
        -- Set session context untuk trigger
        IF @Created_By IS NULL
            SET @Created_By = 'SYSTEM';
        
        EXEC sp_set_session_context 'UserName', @Created_By;
        
        -- Insert data langganan
        INSERT INTO Langganan (
            ID_Customer, ID_Karyawan, ID_Tipe,
            Tanggal_Mulai, Tanggal_Selesai,
            Total_Bayar, Metode_Pembayaran, Status,
            Created_By, Created_Date
        )
        VALUES (
            @ID_Customer, @ID_Karyawan, @ID_Tipe,
            @Tanggal_Mulai, @Tanggal_Selesai,
            @Harga_Member, @Metode_Pembayaran, 0,
            @Created_By, GETDATE()
        );
        
        -- Ambil ID yang baru dibuat
        DECLARE @NewID INT = SCOPE_IDENTITY();
        
        COMMIT TRANSACTION;
        
        -- Return hasil
        SELECT 
            @NewID AS ID_Langganan,
            'SUCCESS' AS Status,
            'Pendaftaran member berhasil! Menunggu konfirmasi admin.' AS Message,
            @Tanggal_Mulai AS Tanggal_Mulai,
            @Tanggal_Selesai AS Tanggal_Selesai,
            @Harga_Member AS Total_Bayar;
            
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0
            ROLLBACK TRANSACTION;
            
        SELECT 
            0 AS ID_Langganan,
            'ERROR' AS Status,
            ERROR_MESSAGE() AS Message,
            NULL AS Tanggal_Mulai,
            NULL AS Tanggal_Selesai,
            0 AS Total_Bayar;
    END CATCH
END
GO

-- ============================================================================
-- 5. STORED PROCEDURE: SP_KonfirmasiLangganan
-- ============================================================================
-- Karyawan mengkonfirmasi pembayaran langganan
-- Status: 0 -> 1 (Menunggu -> Aktif)
-- ============================================================================

IF EXISTS (SELECT * FROM sys.procedures WHERE name = 'SP_KonfirmasiLangganan')
    DROP PROCEDURE SP_KonfirmasiLangganan;
GO

CREATE PROCEDURE SP_KonfirmasiLangganan
    @ID_Langganan    INT,
    @ID_Karyawan     INT,
    @Modified_By     VARCHAR(50) = NULL
AS
BEGIN
    SET NOCOUNT ON;
    
    BEGIN TRY
        BEGIN TRANSACTION;
        
        -- Validasi: Langganan harus dalam status Menunggu (0)
        DECLARE @CurrentStatus INT, @ID_Customer INT;
        SELECT @CurrentStatus = Status, @ID_Customer = ID_Customer
        FROM Langganan WHERE ID_Langganan = @ID_Langganan;
        
        IF @CurrentStatus IS NULL
        BEGIN
            RAISERROR ('Data langganan tidak ditemukan!', 16, 1);
            ROLLBACK TRANSACTION;
            RETURN;
        END
        
        IF @CurrentStatus <> 0
        BEGIN
            RAISERROR ('Langganan sudah diproses sebelumnya!', 16, 1);
            ROLLBACK TRANSACTION;
            RETURN;
        END
        
        -- Validasi: Karyawan harus aktif
        IF NOT EXISTS (
            SELECT 1 FROM Karyawan 
            WHERE ID_Karyawan = @ID_Karyawan AND Status = 1 AND Is_Deleted = 0
        )
        BEGIN
            RAISERROR ('Karyawan tidak valid!', 16, 1);
            ROLLBACK TRANSACTION;
            RETURN;
        END
        
        -- Set session context untuk trigger
        IF @Modified_By IS NULL
            SET @Modified_By = 'SYSTEM';
        
        EXEC sp_set_session_context 'UserName', @Modified_By;
        
        -- Update status menjadi Aktif dan set karyawan konfirmasi
        UPDATE Langganan
        SET 
            Status = 1,
            ID_Karyawan = @ID_Karyawan,
            Modified_By = @Modified_By,
            Modified_Date = GETDATE()
        WHERE ID_Langganan = @ID_Langganan;
        
        COMMIT TRANSACTION;
        
        SELECT 
            @ID_Langganan AS ID_Langganan,
            'SUCCESS' AS Status,
            'Pembayaran langganan berhasil dikonfirmasi! Status: Aktif.' AS Message,
            @ID_Customer AS ID_Customer;
            
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0
            ROLLBACK TRANSACTION;
            
        SELECT 
            @ID_Langganan AS ID_Langganan,
            'ERROR' AS Status,
            ERROR_MESSAGE() AS Message,
            0 AS ID_Customer;
    END CATCH
END
GO

-- ============================================================================
-- 6. STORED PROCEDURE: SP_TolakLangganan
-- ============================================================================
-- Karyawan menolak langganan
-- Status: 0 -> 3 (Menunggu -> Ditolak)
-- ============================================================================

IF EXISTS (SELECT * FROM sys.procedures WHERE name = 'SP_TolakLangganan')
    DROP PROCEDURE SP_TolakLangganan;
GO

CREATE PROCEDURE SP_TolakLangganan
    @ID_Langganan    INT,
    @ID_Karyawan     INT,
    @Alasan          VARCHAR(255) = NULL,
    @Modified_By     VARCHAR(50) = NULL
AS
BEGIN
    SET NOCOUNT ON;
    
    BEGIN TRY
        BEGIN TRANSACTION;
        
        -- Validasi: Langganan harus dalam status Menunggu (0)
        DECLARE @CurrentStatus INT, @ID_Customer INT;
        SELECT @CurrentStatus = Status, @ID_Customer = ID_Customer
        FROM Langganan WHERE ID_Langganan = @ID_Langganan;
        
        IF @CurrentStatus IS NULL
        BEGIN
            RAISERROR ('Data langganan tidak ditemukan!', 16, 1);
            ROLLBACK TRANSACTION;
            RETURN;
        END
        
        IF @CurrentStatus <> 0
        BEGIN
            RAISERROR ('Hanya langganan dengan status Menunggu yang dapat ditolak!', 16, 1);
            ROLLBACK TRANSACTION;
            RETURN;
        END
        
        -- Set session context untuk trigger
        IF @Modified_By IS NULL
            SET @Modified_By = 'SYSTEM';
        
        EXEC sp_set_session_context 'UserName', @Modified_By;
        
        -- Update status menjadi Ditolak
        UPDATE Langganan
        SET 
            Status = 3,
            ID_Karyawan = @ID_Karyawan,
            Modified_By = @Modified_By,
            Modified_Date = GETDATE()
        WHERE ID_Langganan = @ID_Langganan;
        
        -- Log alasan penolakan (simpan di keterangan log)
        UPDATE Log_Langganan_History
        SET Keterangan = ISNULL(Keterangan, '') + ' | Alasan tolak: ' + ISNULL(@Alasan, 'Tidak ada alasan')
        WHERE ID_Langganan = @ID_Langganan AND Action_Type = 'UPDATE'
        AND Action_Date = (
            SELECT MAX(Action_Date) FROM Log_Langganan_History 
            WHERE ID_Langganan = @ID_Langganan
        );
        
        COMMIT TRANSACTION;
        
        SELECT 
            @ID_Langganan AS ID_Langganan,
            'SUCCESS' AS Status,
            'Langganan berhasil ditolak.' AS Message,
            @ID_Customer AS ID_Customer;
            
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0
            ROLLBACK TRANSACTION;
            
        SELECT 
            @ID_Langganan AS ID_Langganan,
            'ERROR' AS Status,
            ERROR_MESSAGE() AS Message,
            0 AS ID_Customer;
    END CATCH
END
GO

-- ============================================================================
-- 7. STORED PROCEDURE: SP_UpdateLangganan
-- ============================================================================
-- Update data langganan (untuk perubahan data oleh admin)
-- Hanya boleh update data non-status (tanggal, metode pembayaran, dll)
-- ============================================================================

IF EXISTS (SELECT * FROM sys.procedures WHERE name = 'SP_UpdateLangganan')
    DROP PROCEDURE SP_UpdateLangganan;
GO

CREATE PROCEDURE SP_UpdateLangganan
    @ID_Langganan        INT,
    @ID_Tipe             INT = NULL,           -- NULL = tidak diubah
    @Tanggal_Mulai       DATE = NULL,
    @Tanggal_Selesai     DATE = NULL,
    @Metode_Pembayaran   VARCHAR(20) = NULL,
    @Bukti_Pembayaran    VARCHAR(255) = NULL,
    @Modified_By         VARCHAR(50) = NULL
AS
BEGIN
    SET NOCOUNT ON;
    
    BEGIN TRY
        BEGIN TRANSACTION;
        
        -- Validasi: Langganan harus ada
        IF NOT EXISTS (SELECT 1 FROM Langganan WHERE ID_Langganan = @ID_Langganan)
        BEGIN
            RAISERROR ('Data langganan tidak ditemukan!', 16, 1);
            ROLLBACK TRANSACTION;
            RETURN;
        END
        
        -- Validasi: Tidak boleh update yang sudah Berakhir (2) atau Ditolak (3)
        IF EXISTS (SELECT 1 FROM Langganan WHERE ID_Langganan = @ID_Langganan AND Status IN (2, 3))
        BEGIN
            RAISERROR ('Tidak dapat mengupdate langganan yang sudah berakhir atau ditolak!', 16, 1);
            ROLLBACK TRANSACTION;
            RETURN;
        END
        
        -- Jika tipe diubah, validasi tipe baru
        IF @ID_Tipe IS NOT NULL
        BEGIN
            IF NOT EXISTS (SELECT 1 FROM Tipe_Member WHERE ID_Tipe = @ID_Tipe AND Status = 1 AND Is_Deleted = 0)
            BEGIN
                RAISERROR ('Tipe member tidak valid!', 16, 1);
                ROLLBACK TRANSACTION;
                RETURN;
            END
            
            -- Update Total_Bayar sesuai harga tipe baru
            DECLARE @NewHarga DECIMAL(18,2);
            SELECT @NewHarga = Harga_Member FROM Tipe_Member WHERE ID_Tipe = @ID_Tipe;
            
            UPDATE Langganan SET 
                ID_Tipe = @ID_Tipe,
                Total_Bayar = @NewHarga
            WHERE ID_Langganan = @ID_Langganan;
        END
        
        -- Set session context untuk trigger
        IF @Modified_By IS NULL
            SET @Modified_By = 'SYSTEM';
        
        EXEC sp_set_session_context 'UserName', @Modified_By;
        
        -- Update data lainnya
        UPDATE Langganan
        SET 
            Tanggal_Mulai = ISNULL(@Tanggal_Mulai, Tanggal_Mulai),
            Tanggal_Selesai = ISNULL(@Tanggal_Selesai, Tanggal_Selesai),
            Metode_Pembayaran = ISNULL(@Metode_Pembayaran, Metode_Pembayaran),
            Bukti_Pembayaran = ISNULL(@Bukti_Pembayaran, Bukti_Pembayaran),
            Modified_By = @Modified_By,
            Modified_Date = GETDATE()
        WHERE ID_Langganan = @ID_Langganan;
        
        COMMIT TRANSACTION;
        
        SELECT 
            @ID_Langganan AS ID_Langganan,
            'SUCCESS' AS Status,
            'Data langganan berhasil diupdate.' AS Message;
            
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0
            ROLLBACK TRANSACTION;
            
        SELECT 
            @ID_Langganan AS ID_Langganan,
            'ERROR' AS Status,
            ERROR_MESSAGE() AS Message;
    END CATCH
END
GO

-- ============================================================================
-- 8. STORED PROCEDURE: SP_SoftDeleteLangganan
-- ============================================================================
-- Soft delete langganan (status tetap, tapi flag deleted)
-- ============================================================================

IF EXISTS (SELECT * FROM sys.procedures WHERE name = 'SP_SoftDeleteLangganan')
    DROP PROCEDURE SP_SoftDeleteLangganan;
GO

CREATE PROCEDURE SP_SoftDeleteLangganan
    @ID_Langganan    INT,
    @Deleted_By      VARCHAR(50) = NULL
AS
BEGIN
    SET NOCOUNT ON;
    
    BEGIN TRY
        BEGIN TRANSACTION;
        
        -- Validasi: Langganan harus ada
        IF NOT EXISTS (SELECT 1 FROM Langganan WHERE ID_Langganan = @ID_Langganan)
        BEGIN
            RAISERROR ('Data langganan tidak ditemukan!', 16, 1);
            ROLLBACK TRANSACTION;
            RETURN;
        END
        
        -- Set session context untuk trigger
        IF @Deleted_By IS NULL
            SET @Deleted_By = 'SYSTEM';
        
        EXEC sp_set_session_context 'UserName', @Deleted_By;
        
        -- Soft delete dengan mengubah status menjadi Ditolak (3) jika masih Menunggu
        -- atau Biarkan jika sudah Berakhir (2)
        UPDATE Langganan
        SET 
            Status = CASE 
                WHEN Status = 0 THEN 3  -- Menunggu -> Ditolak
                WHEN Status = 1 THEN 2    -- Aktif -> Berakhir (force end)
                ELSE Status 
            END,
            Modified_By = @Deleted_By,
            Modified_Date = GETDATE()
        WHERE ID_Langganan = @ID_Langganan;
        
        COMMIT TRANSACTION;
        
        SELECT 
            @ID_Langganan AS ID_Langganan,
            'SUCCESS' AS Status,
            'Langganan berhasil dihapus (soft delete).' AS Message;
            
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0
            ROLLBACK TRANSACTION;
            
        SELECT 
            @ID_Langganan AS ID_Langganan,
            'ERROR' AS Status,
            ERROR_MESSAGE() AS Message;
    END CATCH
END
GO

-- ============================================================================
-- 9. STORED PROCEDURE: SP_GetLanggananByID
-- ============================================================================

IF EXISTS (SELECT * FROM sys.procedures WHERE name = 'SP_GetLanggananByID')
    DROP PROCEDURE SP_GetLanggananByID;
GO

CREATE PROCEDURE SP_GetLanggananByID
    @ID_Langganan INT
AS
BEGIN
    SET NOCOUNT ON;
    
    SELECT 
        L.ID_Langganan,
        L.ID_Customer,
        C.Nama_Customer,
        C.Email AS Customer_Email,
        C.No_Telepon AS Customer_Telepon,
        L.ID_Karyawan,
        K.Nama_Karyawan AS Nama_Karyawan_Konfirmasi,
        L.ID_Tipe,
        TM.Nama_Tipe,
        TM.Harga_Member,
        TM.Potongan_Harga,
        L.Tanggal_Mulai,
        L.Tanggal_Selesai,
        DATEDIFF(DAY, GETDATE(), L.Tanggal_Selesai) AS Sisa_Hari,
        L.Total_Bayar,
        L.Metode_Pembayaran,
        L.Bukti_Pembayaran,
        L.Status,
        CASE L.Status
            WHEN 0 THEN 'Menunggu Konfirmasi'
            WHEN 1 THEN 'Aktif'
            WHEN 2 THEN 'Berakhir'
            WHEN 3 THEN 'Ditolak'
        END AS Status_Label,
        L.Created_By,
        L.Created_Date,
        L.Modified_By,
        L.Modified_Date
    FROM Langganan L
    INNER JOIN Customer C ON L.ID_Customer = C.ID_Customer
    LEFT JOIN Karyawan K ON L.ID_Karyawan = K.ID_Karyawan
    INNER JOIN Tipe_Member TM ON L.ID_Tipe = TM.ID_Tipe
    WHERE L.ID_Langganan = @ID_Langganan;
END
GO

-- ============================================================================
-- 10. STORED PROCEDURE: SP_GetLanggananByCustomer
-- ============================================================================

IF EXISTS (SELECT * FROM sys.procedures WHERE name = 'SP_GetLanggananByCustomer')
    DROP PROCEDURE SP_GetLanggananByCustomer;
GO

CREATE PROCEDURE SP_GetLanggananByCustomer
    @ID_Customer INT,
    @StatusFilter INT = NULL  -- NULL = semua status
AS
BEGIN
    SET NOCOUNT ON;
    
    SELECT 
        L.ID_Langganan,
        L.ID_Tipe,
        TM.Nama_Tipe,
        TM.Harga_Member,
        TM.Potongan_Harga,
        L.Tanggal_Mulai,
        L.Tanggal_Selesai,
        DATEDIFF(DAY, GETDATE(), L.Tanggal_Selesai) AS Sisa_Hari,
        L.Total_Bayar,
        L.Metode_Pembayaran,
        L.Status,
        CASE L.Status
            WHEN 0 THEN 'Menunggu Konfirmasi'
            WHEN 1 THEN 'Aktif'
            WHEN 2 THEN 'Berakhir'
            WHEN 3 THEN 'Ditolak'
        END AS Status_Label,
        L.Created_Date,
        L.Modified_Date,
        K.Nama_Karyawan AS Dikonfirmasi_Oleh
    FROM Langganan L
    INNER JOIN Tipe_Member TM ON L.ID_Tipe = TM.ID_Tipe
    LEFT JOIN Karyawan K ON L.ID_Karyawan = K.ID_Karyawan
    WHERE L.ID_Customer = @ID_Customer
    AND (@StatusFilter IS NULL OR L.Status = @StatusFilter)
    ORDER BY 
        CASE L.Status
            WHEN 1 THEN 0  -- Aktif dulu
            WHEN 0 THEN 1  -- Menunggu
            WHEN 2 THEN 2  -- Berakhir
            WHEN 3 THEN 3  -- Ditolak
        END,
        L.Created_Date DESC;
END
GO

-- ============================================================================
-- 11. STORED PROCEDURE: SP_GetLanggananPending
-- ============================================================================
-- Daftar langganan yang menunggu konfirmasi (untuk dashboard karyawan)
-- ============================================================================

IF EXISTS (SELECT * FROM sys.procedures WHERE name = 'SP_GetLanggananPending')
    DROP PROCEDURE SP_GetLanggananPending;
GO

CREATE PROCEDURE SP_GetLanggananPending
    @PageNumber INT = 1,
    @PageSize INT = 10
AS
BEGIN
    SET NOCOUNT ON;
    
    DECLARE @Offset INT = (@PageNumber - 1) * @PageSize;
    
    -- Total count
    SELECT COUNT(*) AS Total_Count FROM Langganan WHERE Status = 0;
    
    -- Data
    SELECT 
        L.ID_Langganan,
        L.ID_Customer,
        C.Nama_Customer,
        C.Email,
        C.No_Telepon,
        L.ID_Tipe,
        TM.Nama_Tipe,
        TM.Harga_Member,
        L.Tanggal_Mulai,
        L.Tanggal_Selesai,
        L.Total_Bayar,
        L.Metode_Pembayaran,
        L.Created_Date,
        DATEDIFF(DAY, L.Created_Date, GETDATE()) AS Hari_Menunggu
    FROM Langganan L
    INNER JOIN Customer C ON L.ID_Customer = C.ID_Customer
    INNER JOIN Tipe_Member TM ON L.ID_Tipe = TM.ID_Tipe
    WHERE L.Status = 0
    ORDER BY L.Created_Date ASC
    OFFSET @Offset ROWS FETCH NEXT @PageSize ROWS ONLY;
END
GO

-- ============================================================================
-- 12. STORED PROCEDURE: SP_GetLanggananAktif
-- ============================================================================
-- Daftar langganan yang sedang aktif
-- ============================================================================

IF EXISTS (SELECT * FROM sys.procedures WHERE name = 'SP_GetLanggananAktif')
    DROP PROCEDURE SP_GetLanggananAktif;
GO

CREATE PROCEDURE SP_GetLanggananAktif
    @PageNumber INT = 1,
    @PageSize INT = 10
AS
BEGIN
    SET NOCOUNT ON;
    
    DECLARE @Offset INT = (@PageNumber - 1) * @PageSize;
    
    SELECT COUNT(*) AS Total_Count FROM Langganan WHERE Status = 1;
    
    SELECT 
        L.ID_Langganan,
        L.ID_Customer,
        C.Nama_Customer,
        C.Email,
        L.ID_Tipe,
        TM.Nama_Tipe,
        L.Tanggal_Mulai,
        L.Tanggal_Selesai,
        DATEDIFF(DAY, GETDATE(), L.Tanggal_Selesai) AS Sisa_Hari,
        L.Total_Bayar,
        L.Metode_Pembayaran,
        K.Nama_Karyawan AS Dikonfirmasi_Oleh,
        L.Modified_Date AS Tanggal_Konfirmasi
    FROM Langganan L
    INNER JOIN Customer C ON L.ID_Customer = C.ID_Customer
    INNER JOIN Tipe_Member TM ON L.ID_Tipe = TM.ID_Tipe
    LEFT JOIN Karyawan K ON L.ID_Karyawan = K.ID_Karyawan
    WHERE L.Status = 1
    ORDER BY L.Tanggal_Selesai ASC  -- Yang mau expired duluan di atas
    OFFSET @Offset ROWS FETCH NEXT @PageSize ROWS ONLY;
END
GO

-- ============================================================================
-- 13. STORED PROCEDURE: SP_AutoExpireLangganan
-- ============================================================================
-- Auto update status langganan yang sudah lewat tanggal selesai
-- Status: 1 -> 2 (Aktif -> Berakhir)
-- ============================================================================

IF EXISTS (SELECT * FROM sys.procedures WHERE name = 'SP_AutoExpireLangganan')
    DROP PROCEDURE SP_AutoExpireLangganan;
GO

CREATE PROCEDURE SP_AutoExpireLangganan
    @Modified_By VARCHAR(50) = 'SYSTEM_AUTO'
AS
BEGIN
    SET NOCOUNT ON;
    
    BEGIN TRY
        BEGIN TRANSACTION;
        
        -- Set session context untuk trigger
        EXEC sp_set_session_context 'UserName', @Modified_By;
        
        -- Update langganan yang sudah expired
        UPDATE Langganan
        SET 
            Status = 2,
            Modified_By = @Modified_By,
            Modified_Date = GETDATE()
        WHERE Status = 1
        AND Tanggal_Selesai < CAST(GETDATE() AS DATE);
        
        DECLARE @AffectedRows INT = @@ROWCOUNT;
        
        COMMIT TRANSACTION;
        
        SELECT 
            @AffectedRows AS Expired_Count,
            'SUCCESS' AS Status,
            CONCAT(@AffectedRows, ' langganan telah diupdate menjadi Berakhir.') AS Message;
            
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0
            ROLLBACK TRANSACTION;
            
        SELECT 
            0 AS Expired_Count,
            'ERROR' AS Status,
            ERROR_MESSAGE() AS Message;
    END CATCH
END
GO

-- ============================================================================
-- 14. STORED PROCEDURE: SP_GetLogHistory
-- ============================================================================
-- Melihat log history perubahan langganan
-- ============================================================================

IF EXISTS (SELECT * FROM sys.procedures WHERE name = 'SP_GetLogHistory')
    DROP PROCEDURE SP_GetLogHistory;
GO

CREATE PROCEDURE SP_GetLogHistory
    @ID_Langganan INT = NULL,      -- NULL = semua
    @Action_Type VARCHAR(20) = NULL, -- NULL = semua
    @StartDate DATE = NULL,
    @EndDate DATE = NULL,
    @PageNumber INT = 1,
    @PageSize INT = 20
AS
BEGIN
    SET NOCOUNT ON;
    
    DECLARE @Offset INT = (@PageNumber - 1) * @PageSize;
    
    -- Total count
    SELECT COUNT(*) AS Total_Count 
    FROM Log_Langganan_History
    WHERE (@ID_Langganan IS NULL OR ID_Langganan = @ID_Langganan)
    AND (@Action_Type IS NULL OR Action_Type = @Action_Type)
    AND (@StartDate IS NULL OR CAST(Action_Date AS DATE) >= @StartDate)
    AND (@EndDate IS NULL OR CAST(Action_Date AS DATE) <= @EndDate);
    
    -- Data
    SELECT 
        Log_ID,
        ID_Langganan,
        Action_Type,
        Action_By,
        Action_Date,
        
        -- Data Lama
        Old_ID_Customer,
        Old_ID_Karyawan,
        Old_ID_Tipe,
        Old_Tanggal_Mulai,
        Old_Tanggal_Selesai,
        Old_Total_Bayar,
        Old_Metode_Pembayaran,
        Old_Status,
        CASE Old_Status
            WHEN 0 THEN 'Menunggu'
            WHEN 1 THEN 'Aktif'
            WHEN 2 THEN 'Berakhir'
            WHEN 3 THEN 'Ditolak'
        END AS Old_Status_Label,
        
        -- Data Baru
        New_ID_Customer,
        New_ID_Karyawan,
        New_ID_Tipe,
        New_Tanggal_Mulai,
        New_Tanggal_Selesai,
        New_Total_Bayar,
        New_Metode_Pembayaran,
        New_Status,
        CASE New_Status
            WHEN 0 THEN 'Menunggu'
            WHEN 1 THEN 'Aktif'
            WHEN 2 THEN 'Berakhir'
            WHEN 3 THEN 'Ditolak'
        END AS New_Status_Label,
        
        Keterangan,
        IP_Address
    FROM Log_Langganan_History
    WHERE (@ID_Langganan IS NULL OR ID_Langganan = @ID_Langganan)
    AND (@Action_Type IS NULL OR Action_Type = @Action_Type)
    AND (@StartDate IS NULL OR CAST(Action_Date AS DATE) >= @StartDate)
    AND (@EndDate IS NULL OR CAST(Action_Date AS DATE) <= @EndDate)
    ORDER BY Action_Date DESC
    OFFSET @Offset ROWS FETCH NEXT @PageSize ROWS ONLY;
END
GO


-- ============================================================================
-- 16. STORED PROCEDURE: SP_GetLanggananList
-- ============================================================================
-- Daftar lengkap langganan dengan filter dan paging
-- ============================================================================

IF EXISTS (SELECT * FROM sys.procedures WHERE name = 'SP_GetLanggananList')
    DROP PROCEDURE SP_GetLanggananList;
GO

CREATE OR ALTER PROCEDURE SP_GetLanggananList
    @Filter_Status INT = NULL,
    @Filter_Customer VARCHAR(50) = NULL,
    @Filter_TanggalMulai DATE = NULL,
    @Filter_TanggalSelesai DATE = NULL,
    @PageNumber INT = 1,
    @PageSize INT = 10
AS
BEGIN
    SET NOCOUNT ON;
    
    DECLARE @Offset INT = (@PageNumber - 1) * @PageSize;
    
    -- Build dynamic WHERE (Count Query tetap sama)
    DECLARE @SQL NVARCHAR(MAX);
    DECLARE @ParamDef NVARCHAR(500);
    
    SET @SQL = N'
    SELECT COUNT(*) AS Total_Count 
    FROM Langganan L
    INNER JOIN Customer C ON L.ID_Customer = C.ID_Customer
    INNER JOIN Tipe_Member TM ON L.ID_Tipe = TM.ID_Tipe
    LEFT JOIN Karyawan K ON L.ID_Karyawan = K.ID_Karyawan
    WHERE 1=1';
    
    IF @Filter_Status IS NOT NULL
        SET @SQL = @SQL + N' AND L.Status = @Filter_Status';
    IF @Filter_Customer IS NOT NULL
        SET @SQL = @SQL + N' AND C.Nama_Customer LIKE ''%'' + @Filter_Customer + ''%''';
    IF @Filter_TanggalMulai IS NOT NULL
        SET @SQL = @SQL + N' AND L.Tanggal_Mulai >= @Filter_TanggalMulai';
    IF @Filter_TanggalSelesai IS NOT NULL
        SET @SQL = @SQL + N' AND L.Tanggal_Selesai <= @Filter_TanggalSelesai';
    
    SET @ParamDef = N'@Filter_Status INT, @Filter_Customer VARCHAR(50), @Filter_TanggalMulai DATE, @Filter_TanggalSelesai DATE';
    EXEC sp_executesql @SQL, @ParamDef, @Filter_Status, @Filter_Customer, @Filter_TanggalMulai, @Filter_TanggalSelesai;
    
    -- Data query (DI SINI KITA TAMBAHKAN TM.Harga_Member)
    SET @SQL = N'
    SELECT 
        L.ID_Langganan,
        L.ID_Customer,
        C.Nama_Customer,
        C.Email,
        L.ID_Tipe,
        TM.Nama_Tipe,
        TM.Harga_Member, -- <--- KITA TAMBAHKAN KOLOM INI AGAR HARGANYA MUNCUL
        L.Tanggal_Mulai,
        L.Tanggal_Selesai,
        L.Total_Bayar,
        L.Metode_Pembayaran,
        L.Status,
        CASE L.Status
            WHEN 0 THEN ''Menunggu Konfirmasi''
            WHEN 1 THEN ''Aktif''
            WHEN 2 THEN ''Berakhir''
            WHEN 3 THEN ''Ditolak''
        END AS Status_Label,
        K.Nama_Karyawan AS Nama_Karyawan_Konfirmasi,
        L.Created_Date,
        L.Modified_Date
    FROM Langganan L
    INNER JOIN Customer C ON L.ID_Customer = C.ID_Customer
    INNER JOIN Tipe_Member TM ON L.ID_Tipe = TM.ID_Tipe
    LEFT JOIN Karyawan K ON L.ID_Karyawan = K.ID_Karyawan
    WHERE 1=1';
    
    IF @Filter_Status IS NOT NULL
        SET @SQL = @SQL + N' AND L.Status = @Filter_Status';
    IF @Filter_Customer IS NOT NULL
        SET @SQL = @SQL + N' AND C.Nama_Customer LIKE ''%'' + @Filter_Customer + ''%''';
    IF @Filter_TanggalMulai IS NOT NULL
        SET @SQL = @SQL + N' AND L.Tanggal_Mulai >= @Filter_TanggalMulai';
    IF @Filter_TanggalSelesai IS NOT NULL
        SET @SQL = @SQL + N' AND L.Tanggal_Selesai <= @Filter_TanggalSelesai';
    
    SET @SQL = @SQL + N'
    ORDER BY 
        CASE L.Status
            WHEN 0 THEN 0
            WHEN 1 THEN 1
            WHEN 2 THEN 2
            WHEN 3 THEN 3
        END,
        L.Created_Date DESC
    OFFSET @Offset ROWS FETCH NEXT @PageSize ROWS ONLY';
    
    SET @ParamDef = N'@Filter_Status INT, @Filter_Customer VARCHAR(50), @Filter_TanggalMulai DATE, @Filter_TanggalSelesai DATE, @Offset INT, @PageSize INT';
    EXEC sp_executesql @SQL, @ParamDef, @Filter_Status, @Filter_Customer, @Filter_TanggalMulai, @Filter_TanggalSelesai, @Offset, @PageSize;
END
GO

-- 1. TAMBAHKAN UDF UNTUK DASHBOARD STATS (Menggantikan SP di atas)
IF EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'[dbo].[fn_Langganan_GetDashboardStats]') AND type in (N'FN', N'IF', N'TF', N'FS', N'FT'))
    DROP FUNCTION [dbo].[fn_Langganan_GetDashboardStats];
GO

CREATE FUNCTION fn_Langganan_GetDashboardStats ()
RETURNS TABLE
AS
RETURN
(
    SELECT 
        COUNT(*) AS Total_Langganan,
        SUM(CASE WHEN Status = 0 THEN 1 ELSE 0 END) AS Menunggu_Konfirmasi,
        SUM(CASE WHEN Status = 1 THEN 1 ELSE 0 END) AS Aktif,
        SUM(CASE WHEN Status = 2 THEN 1 ELSE 0 END) AS Berakhir,
        SUM(CASE WHEN Status = 3 THEN 1 ELSE 0 END) AS Ditolak,
        ISNULL(SUM(CASE WHEN Status = 1 THEN Total_Bayar ELSE 0 END), 0) AS Total_Omzet_Aktif,
        ISNULL(SUM(Total_Bayar), 0) AS Total_Omzet_Semua,
        SUM(CASE WHEN Status = 1 AND Tanggal_Selesai <= DATEADD(DAY, 7, GETDATE()) THEN 1 ELSE 0 END) AS Akan_Expired_7Hari
    FROM Langganan
);
GO


USE Hoopball;
GO

-- UDF Baru: Mengambil List Transaksi Langganan untuk Dashboard Kelola (Karyawan)
CREATE OR ALTER FUNCTION dbo.fn_Langganan_GetList (
    @FilterStatus INT,
    @SearchKeyword VARCHAR(50),
    @FilterTanggal DATE
)
RETURNS TABLE
AS
RETURN
(
    SELECT 
        L.ID_Langganan, L.ID_Customer, C.Nama_Customer, C.Email, L.ID_Tipe, TM.Nama_Tipe, TM.Harga_Member,
        L.Tanggal_Mulai, L.Tanggal_Selesai, L.Total_Bayar, L.Metode_Pembayaran, L.Status,
        K.Nama_Karyawan AS Nama_Karyawan_Konfirmasi, L.Created_Date, L.Modified_Date, L.Bukti_Pembayaran
    FROM Langganan L
    INNER JOIN Customer C ON L.ID_Customer = C.ID_Customer
    INNER JOIN Tipe_Member TM ON L.ID_Tipe = TM.ID_Tipe
    LEFT JOIN Karyawan K ON L.ID_Karyawan = K.ID_Karyawan
    WHERE (@FilterStatus IS NULL OR L.Status = @FilterStatus)
      AND (@FilterTanggal IS NULL OR L.Tanggal_Mulai >= @FilterTanggal)
      AND (@SearchKeyword = '' OR C.Nama_Customer LIKE '%' + @SearchKeyword + '%' OR TM.Nama_Tipe LIKE '%' + @SearchKeyword + '%')
);
GO


-- 2. TAMBAHKAN SP UNTUK MENGAMBIL DATA TIPE MEMBER AKTIF
IF EXISTS (SELECT * FROM sys.procedures WHERE name = 'SP_TipeMember_GetActive')
    DROP PROCEDURE SP_TipeMember_GetActive;
GO

CREATE PROCEDURE SP_TipeMember_GetActive
AS
BEGIN
    SET NOCOUNT ON;
    SELECT ID_Tipe, Nama_Tipe, Harga_Member, Potongan_Harga, Status
    FROM Tipe_Member
    WHERE Status = 1 AND Is_Deleted = 0
    ORDER BY Harga_Member ASC;
END;
GO

-- 3. TAMBAHKAN SP UNTUK UPDATE BUKTI PEMBAYARAN LANGGANAN
IF EXISTS (SELECT * FROM sys.procedures WHERE name = 'SP_Langganan_UpdateBukti')
    DROP PROCEDURE SP_Langganan_UpdateBukti;
GO

CREATE PROCEDURE SP_Langganan_UpdateBukti
    @ID_Langganan INT,
    @Bukti_Pembayaran VARCHAR(255)
AS
BEGIN
    SET NOCOUNT ON;
    UPDATE Langganan 
    SET Bukti_Pembayaran = @Bukti_Pembayaran 
    WHERE ID_Langganan = @ID_Langganan;
END;
GO

-- ============================================================================
-- CONTOH PENGGUNAAN / TESTING
-- ============================================================================
/*
-- 1. Customer mendaftar langganan baru
EXEC SP_CreateLangganan 
    @ID_Customer = 1,
    @ID_Tipe = 2,
    @Metode_Pembayaran = 'Transfer Bank',
    @Created_By = 'dimas_a';

-- 2. Karyawan melihat daftar pending
EXEC SP_GetLanggananPending @PageNumber = 1, @PageSize = 10;

-- 3. Karyawan konfirmasi pembayaran
EXEC SP_KonfirmasiLangganan 
    @ID_Langganan = 9,
    @ID_Karyawan = 2,
    @Modified_By = 'rizky_p';

-- 4. Cek detail langganan
EXEC SP_GetLanggananByID @ID_Langganan = 9;

-- 5. Cek riwayat customer
EXEC SP_GetLanggananByCustomer @ID_Customer = 1, @StatusFilter = NULL;

-- 6. Auto expire langganan yang sudah lewat
EXEC SP_AutoExpireLangganan;

-- 7. Cek log history
EXEC SP_GetLogHistory 
    @ID_Langganan = NULL,
    @Action_Type = NULL,
    @StartDate = '2024-01-01',
    @EndDate = '2024-12-31',
    @PageNumber = 1,
    @PageSize = 20;

-- 8. Dashboard stats
EXEC SP_GetDashboardStats;

-- 9. Update data langganan
EXEC SP_UpdateLangganan 
    @ID_Langganan = 9,
    @Metode_Pembayaran = 'QRIS',
    @Modified_By = 'rizky_p';

-- 10. Tolak langganan
EXEC SP_TolakLangganan 
    @ID_Langganan = 9,
    @ID_Karyawan = 2,
    @Alasan = 'Bukti pembayaran tidak valid',
    @Modified_By = 'rizky_p';
*/
GO

PRINT '=== STORED PROCEDURE TRANSAKSI LANGGANAN BERHASIL DIBUAT ===';
PRINT 'Daftar SP:';
PRINT '  1. SP_CreateLangganan      - Pendaftaran langganan baru';
PRINT '  2. SP_KonfirmasiLangganan  - Konfirmasi pembayaran';
PRINT '  3. SP_TolakLangganan       - Penolakan langganan';
PRINT '  4. SP_UpdateLangganan      - Update data langganan';
PRINT '  5. SP_SoftDeleteLangganan  - Soft delete';
PRINT '  6. SP_GetLanggananByID     - Detail by ID';
PRINT '  7. SP_GetLanggananByCustomer - Riwayat customer';
PRINT '  8. SP_GetLanggananPending  - Daftar menunggu';
PRINT '  9. SP_GetLanggananAktif    - Daftar aktif';
PRINT ' 10. SP_AutoExpireLangganan  - Auto update expired';
PRINT ' 11. SP_GetLogHistory        - Log audit history';
PRINT ' 12. SP_GetDashboardStats    - Statistik dashboard';
PRINT ' 13. SP_GetLanggananList     - Daftar dengan filter';
PRINT '';
PRINT 'Daftar Trigger:';
PRINT '  1. TR_Langganan_AuditLog   - Log history INSERT/UPDATE/DELETE';
PRINT '  2. TR_Langganan_ValidasiStatus - Validasi flow status';
PRINT '';
PRINT 'Tabel Log: Log_Langganan_History';