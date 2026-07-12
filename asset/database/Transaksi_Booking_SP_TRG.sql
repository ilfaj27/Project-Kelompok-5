-- ============================================================
-- STORED PROCEDURES UNTUK TRANSAKSI BOOKING + DETAIL_JADWAL
-- ============================================================
-- Database: Hoopball
-- Dibuat: 2026-07-12
-- Deskripsi: SP lengkap untuk CRUD Booking + Detail_Jadwal (3 kolom)
--            Detail_Jadwal: ID_Booking, ID_Jadwal, Jumlah_Digunakan
-- ============================================================

USE Hoopball;
GO

-- ============================================================
-- 1. TRIGGER: Log History Booking (Audit Trail)
-- ============================================================
-- Trigger ini mencatat setiap perubahan pada tabel Booking
-- baik INSERT, UPDATE, maupun DELETE
-- ============================================================

IF OBJECT_ID('trg_Booking_AuditLog', 'TR') IS NOT NULL
    DROP TRIGGER trg_Booking_AuditLog;
GO

CREATE TABLE Booking_History (
    ID_History          INT IDENTITY(1,1) PRIMARY KEY,
    ID_Booking          INT             NOT NULL,
    ID_Customer         INT             NULL,
    ID_Karyawan         INT             NULL,
    ID_Jadwal           INT             NULL,
    ID_Promo            INT             NULL,
    Tanggal_Booking     DATE            NULL,
    Metode_Pembayaran   VARCHAR(20)     NULL,
    Bukti_Pembayaran    VARCHAR(255)    NULL,
    Total_Bayar         DECIMAL(18,2)   NULL,
    Status              INT             NULL,
    Created_By          VARCHAR(50)     NULL,
    Created_Date        DATETIME        NULL,
    Modified_By         VARCHAR(50)     NULL,
    Modified_Date       DATETIME        NULL,
    -- Audit fields
    Action_Type         VARCHAR(10)     NOT NULL,  -- INSERT, UPDATE, DELETE
    Action_By           VARCHAR(50)     NOT NULL,
    Action_Date         DATETIME        NOT NULL DEFAULT GETDATE(),
    Action_Description  VARCHAR(255)    NULL,
    Old_Values          NVARCHAR(MAX)   NULL,       -- JSON format untuk nilai lama
    New_Values          NVARCHAR(MAX)   NULL        -- JSON format untuk nilai baru
);
GO

CREATE TRIGGER trg_Booking_AuditLog
ON Booking
AFTER INSERT, UPDATE, DELETE
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE @ActionType VARCHAR(10);
    DECLARE @ActionBy VARCHAR(50) = SUSER_SNAME();
    DECLARE @ActionDate DATETIME = GETDATE();

    -- Tentukan jenis action
    IF EXISTS (SELECT 1 FROM inserted) AND EXISTS (SELECT 1 FROM deleted)
        SET @ActionType = 'UPDATE';
    ELSE IF EXISTS (SELECT 1 FROM inserted)
        SET @ActionType = 'INSERT';
    ELSE
        SET @ActionType = 'DELETE';

    -- Handle INSERT
    IF @ActionType = 'INSERT'
    BEGIN
        INSERT INTO Booking_History (
            ID_Booking, ID_Customer, ID_Karyawan, ID_Jadwal, ID_Promo,
            Tanggal_Booking, Metode_Pembayaran, Bukti_Pembayaran, Total_Bayar, Status,
            Created_By, Created_Date, Modified_By, Modified_Date,
            Action_Type, Action_By, Action_Date, Action_Description,
            Old_Values, New_Values
        )
        SELECT 
            i.ID_Booking, i.ID_Customer, i.ID_Karyawan, i.ID_Jadwal, i.ID_Promo,
            i.Tanggal_Booking, i.Metode_Pembayaran, i.Bukti_Pembayaran, i.Total_Bayar, i.Status,
            i.Created_By, i.Created_Date, i.Modified_By, i.Modified_Date,
            @ActionType, @ActionBy, @ActionDate,
            'Booking baru dibuat dengan status: ' + 
                CASE i.Status 
                    WHEN 0 THEN 'Menunggu Konfirmasi'
                    WHEN 1 THEN 'Berhasil'
                    WHEN 2 THEN 'Selesai'
                    WHEN 3 THEN 'Dibatalkan'
                    ELSE 'Unknown'
                END,
            NULL,
            (SELECT * FROM inserted i2 WHERE i2.ID_Booking = i.ID_Booking FOR JSON AUTO, WITHOUT_ARRAY_WRAPPER)
        FROM inserted i;
    END

    -- Handle UPDATE
    ELSE IF @ActionType = 'UPDATE'
    BEGIN
        INSERT INTO Booking_History (
            ID_Booking, ID_Customer, ID_Karyawan, ID_Jadwal, ID_Promo,
            Tanggal_Booking, Metode_Pembayaran, Bukti_Pembayaran, Total_Bayar, Status,
            Created_By, Created_Date, Modified_By, Modified_Date,
            Action_Type, Action_By, Action_Date, Action_Description,
            Old_Values, New_Values
        )
        SELECT 
            i.ID_Booking, i.ID_Customer, i.ID_Karyawan, i.ID_Jadwal, i.ID_Promo,
            i.Tanggal_Booking, i.Metode_Pembayaran, i.Bukti_Pembayaran, i.Total_Bayar, i.Status,
            i.Created_By, i.Created_Date, i.Modified_By, i.Modified_Date,
            @ActionType, @ActionBy, @ActionDate,
            CASE 
                WHEN d.Status <> i.Status THEN 
                    'Status berubah dari [' + 
                    CASE d.Status 
                        WHEN 0 THEN 'Menunggu' WHEN 1 THEN 'Berhasil' 
                        WHEN 2 THEN 'Selesai' WHEN 3 THEN 'Dibatalkan' 
                        ELSE 'Unknown' 
                    END + 
                    '] ke [' + 
                    CASE i.Status 
                        WHEN 0 THEN 'Menunggu' WHEN 1 THEN 'Berhasil' 
                        WHEN 2 THEN 'Selesai' WHEN 3 THEN 'Dibatalkan' 
                        ELSE 'Unknown' 
                    END + ']'
                WHEN d.Total_Bayar <> i.Total_Bayar THEN 
                    'Total bayar diubah dari Rp ' + FORMAT(d.Total_Bayar, 'N0') + ' ke Rp ' + FORMAT(i.Total_Bayar, 'N0')
                WHEN d.ID_Promo IS NULL AND i.ID_Promo IS NOT NULL THEN
                    'Promo ditambahkan'
                WHEN d.ID_Promo IS NOT NULL AND i.ID_Promo IS NULL THEN
                    'Promo dihapus'
                ELSE 'Data booking diperbarui'
            END,
            (SELECT * FROM deleted d2 WHERE d2.ID_Booking = d.ID_Booking FOR JSON AUTO, WITHOUT_ARRAY_WRAPPER),
            (SELECT * FROM inserted i2 WHERE i2.ID_Booking = i.ID_Booking FOR JSON AUTO, WITHOUT_ARRAY_WRAPPER)
        FROM inserted i
        INNER JOIN deleted d ON i.ID_Booking = d.ID_Booking;
    END

    -- Handle DELETE
    ELSE IF @ActionType = 'DELETE'
    BEGIN
        INSERT INTO Booking_History (
            ID_Booking, ID_Customer, ID_Karyawan, ID_Jadwal, ID_Promo,
            Tanggal_Booking, Metode_Pembayaran, Bukti_Pembayaran, Total_Bayar, Status,
            Created_By, Created_Date, Modified_By, Modified_Date,
            Action_Type, Action_By, Action_Date, Action_Description,
            Old_Values, New_Values
        )
        SELECT 
            d.ID_Booking, d.ID_Customer, d.ID_Karyawan, d.ID_Jadwal, d.ID_Promo,
            d.Tanggal_Booking, d.Metode_Pembayaran, d.Bukti_Pembayaran, d.Total_Bayar, d.Status,
            d.Created_By, d.Created_Date, d.Modified_By, d.Modified_Date,
            @ActionType, @ActionBy, @ActionDate,
            'Booking dihapus. Customer ID: ' + CAST(d.ID_Customer AS VARCHAR) + 
            ', Total: Rp ' + FORMAT(d.Total_Bayar, 'N0'),
            (SELECT * FROM deleted d2 WHERE d2.ID_Booking = d.ID_Booking FOR JSON AUTO, WITHOUT_ARRAY_WRAPPER),
            NULL
        FROM deleted d;
    END
END;
GO

-- ============================================================
-- 2. TRIGGER: Auto-Update Jadwal Status saat Booking
-- ============================================================
-- Saat booking baru dibuat (Status=0 atau 1), Jadwal.Status = 0 (Tidak Tersedia)
-- Saat booking dibatalkan (Status=3), Jadwal.Status = 1 (Tersedia)
-- ============================================================

IF OBJECT_ID('trg_Booking_SyncJadwal', 'TR') IS NOT NULL
    DROP TRIGGER trg_Booking_SyncJadwal;
GO

CREATE TRIGGER trg_Booking_SyncJadwal
ON Booking
AFTER INSERT, UPDATE
AS
BEGIN
    SET NOCOUNT ON;

    -- Jika booking baru dengan status 0 atau 1, set jadwal jadi tidak tersedia
    UPDATE J
    SET J.Status = 0,
        J.Modified_By = 'TRIGGER_AUTO',
        J.Modified_Date = GETDATE()
    FROM Jadwal J
    INNER JOIN inserted i ON J.ID_Jadwal = i.ID_Jadwal
    WHERE i.Status IN (0, 1)
      AND J.Status <> 0;

    -- Jika booking dibatalkan (status 3), set jadwal jadi tersedia lagi
    UPDATE J
    SET J.Status = 1,
        J.Modified_By = 'TRIGGER_AUTO',
        J.Modified_Date = GETDATE()
    FROM Jadwal J
    INNER JOIN inserted i ON J.ID_Jadwal = i.ID_Jadwal
    WHERE i.Status = 3
      AND J.Status <> 1;

    -- Jika booking selesai (status 2), set jadwal jadi tersedia lagi
    UPDATE J
    SET J.Status = 1,
        J.Modified_By = 'TRIGGER_AUTO',
        J.Modified_Date = GETDATE()
    FROM Jadwal J
    INNER JOIN inserted i ON J.ID_Jadwal = i.ID_Jadwal
    WHERE i.Status = 2
      AND J.Status <> 1;
END;
GO

-- ============================================================
-- 3. TRIGGER: Validasi Double Booking
-- ============================================================
-- Mencegah customer membuat booking untuk jadwal yang sama
-- dalam waktu yang bersamaan (kecuali status dibatalkan)
-- ============================================================

IF OBJECT_ID('trg_Booking_ValidasiDouble', 'TR') IS NOT NULL
    DROP TRIGGER trg_Booking_ValidasiDouble;
GO

CREATE TRIGGER trg_Booking_ValidasiDouble
ON Booking
AFTER INSERT, UPDATE
AS
BEGIN
    SET NOCOUNT ON;

    IF EXISTS (
        SELECT 1 
        FROM inserted i
        INNER JOIN Booking B ON i.ID_Customer = B.ID_Customer 
                            AND i.ID_Jadwal = B.ID_Jadwal
                            AND i.ID_Booking <> B.ID_Booking
        WHERE B.Status <> 3  -- Exclude yang sudah dibatalkan
          AND i.Status <> 3  -- Exclude yang sedang dibatalkan
    )
    BEGIN
        RAISERROR ('Customer sudah memiliki booking untuk jadwal ini. Tidak boleh double booking!', 16, 1);
        ROLLBACK TRANSACTION;
        RETURN;
    END
END;
GO

-- ============================================================
-- 4. TRIGGER: Auto-Calculate Total Bayar dengan Diskon
-- ============================================================
-- Trigger ini memastikan Total_Bayar sudah termasuk diskon promo
-- dan diskon member (jika ada)
-- ============================================================

IF OBJECT_ID('trg_Booking_AutoHitungTotal', 'TR') IS NOT NULL
    DROP TRIGGER trg_Booking_AutoHitungTotal;
GO

CREATE TRIGGER trg_Booking_AutoHitungTotal
ON Booking
INSTEAD OF INSERT
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE @BasePrice DECIMAL(18,2);
    DECLARE @PromoDiskon DECIMAL(18,2) = 0;
    DECLARE @MemberDiskon DECIMAL(18,2) = 0;
    DECLARE @FinalTotal DECIMAL(18,2);

    -- Ambil harga dasar dari lapangan
    SELECT @BasePrice = L.Harga_Sewa
    FROM inserted i
    INNER JOIN Jadwal J ON i.ID_Jadwal = J.ID_Jadwal
    INNER JOIN Lapangan L ON J.ID_Lapangan = L.ID_Lapangan;

    -- Cek diskon promo
    IF EXISTS (SELECT 1 FROM inserted WHERE ID_Promo IS NOT NULL)
    BEGIN
        SELECT @PromoDiskon = P.Diskon
        FROM inserted i
        INNER JOIN Promo P ON i.ID_Promo = P.ID_Promo
        WHERE P.Status = 1
          AND CAST(GETDATE() AS DATE) BETWEEN P.Tanggal_Mulai AND P.Tanggal_Selesai;
    END

    -- Cek diskon member (Langganan aktif)
    IF EXISTS (
        SELECT 1 FROM inserted i
        INNER JOIN Langganan Lg ON i.ID_Customer = Lg.ID_Customer
        INNER JOIN Tipe_Member Tm ON Lg.ID_Tipe = Tm.ID_Tipe
        WHERE Lg.Status = 1
          AND GETDATE() BETWEEN Lg.Tanggal_Mulai AND Lg.Tanggal_Selesai
    )
    BEGIN
        SELECT @MemberDiskon = Tm.Potongan_Harga
        FROM inserted i
        INNER JOIN Langganan Lg ON i.ID_Customer = Lg.ID_Customer
        INNER JOIN Tipe_Member Tm ON Lg.ID_Tipe = Tm.ID_Tipe
        WHERE Lg.Status = 1
          AND GETDATE() BETWEEN Lg.Tanggal_Mulai AND Lg.Tanggal_Selesai;
    END

    -- Hitung total (gunakan diskon terbesar: promo atau member, bukan keduanya)
    SET @FinalTotal = @BasePrice - CASE 
        WHEN @MemberDiskon > @PromoDiskon THEN @MemberDiskon 
        ELSE @PromoDiskon 
    END;

    IF @FinalTotal < 0 SET @FinalTotal = 0;

    -- Insert dengan total yang sudah dihitung
    INSERT INTO Booking (
        ID_Customer, ID_Karyawan, ID_Jadwal, ID_Promo,
        Tanggal_Booking, Metode_Pembayaran, Bukti_Pembayaran,
        Total_Bayar, Status, Created_By, Created_Date
    )
    SELECT 
        i.ID_Customer, i.ID_Karyawan, i.ID_Jadwal, i.ID_Promo,
        i.Tanggal_Booking, i.Metode_Pembayaran, i.Bukti_Pembayaran,
        @FinalTotal, i.Status, i.Created_By, GETDATE()
    FROM inserted i;
END;
GO

-- ============================================================
-- 5. STORED PROCEDURE: sp_Booking_Create
-- ============================================================
-- Membuat booking baru dengan validasi lengkap
-- ============================================================

IF OBJECT_ID('sp_Booking_Create', 'P') IS NOT NULL
    DROP PROCEDURE sp_Booking_Create;
GO

CREATE PROCEDURE sp_Booking_Create
    @ID_Customer        INT,
    @ID_Karyawan        INT,
    @ID_Jadwal          INT,
    @ID_Promo           INT = NULL,
    @Metode_Pembayaran  VARCHAR(20),
    @Created_By         VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;
    BEGIN TRY
        BEGIN TRANSACTION;

        -- Validasi 1: Customer exists dan aktif
        IF NOT EXISTS (SELECT 1 FROM Customer WHERE ID_Customer = @ID_Customer AND Status = 1 AND Is_Deleted = 0)
        BEGIN
            RAISERROR ('Customer tidak ditemukan atau tidak aktif.', 16, 1);
            ROLLBACK TRANSACTION;
            RETURN;
        END

        -- Validasi 2: Jadwal exists dan tersedia
        IF NOT EXISTS (SELECT 1 FROM Jadwal WHERE ID_Jadwal = @ID_Jadwal AND Status = 1 AND Is_Deleted = 0)
        BEGIN
            RAISERROR ('Jadwal tidak tersedia atau sudah dibooking.', 16, 1);
            ROLLBACK TRANSACTION;
            RETURN;
        END

        -- Validasi 3: Promo valid (jika ada)
        IF @ID_Promo IS NOT NULL
        BEGIN
            IF NOT EXISTS (
                SELECT 1 FROM Promo 
                WHERE ID_Promo = @ID_Promo 
                  AND Status = 1 
                  AND Is_Deleted = 0
                  AND CAST(GETDATE() AS DATE) BETWEEN Tanggal_Mulai AND Tanggal_Selesai
            )
            BEGIN
                RAISERROR ('Promo tidak valid atau sudah expired.', 16, 1);
                ROLLBACK TRANSACTION;
                RETURN;
            END
        END

        -- Validasi 4: Cek double booking
        IF EXISTS (
            SELECT 1 FROM Booking 
            WHERE ID_Customer = @ID_Customer 
              AND ID_Jadwal = @ID_Jadwal 
              AND Status <> 3
        )
        BEGIN
            RAISERROR ('Customer sudah memiliki booking untuk jadwal ini.', 16, 1);
            ROLLBACK TRANSACTION;
            RETURN;
        END

        -- Hitung harga dasar
        DECLARE @BasePrice DECIMAL(18,2);
        SELECT @BasePrice = L.Harga_Sewa
        FROM Jadwal J
        INNER JOIN Lapangan L ON J.ID_Lapangan = L.ID_Lapangan
        WHERE J.ID_Jadwal = @ID_Jadwal;

        -- Hitung diskon
        DECLARE @PromoDiskon DECIMAL(18,2) = 0;
        DECLARE @MemberDiskon DECIMAL(18,2) = 0;
        DECLARE @FinalTotal DECIMAL(18,2);

        -- Cek diskon promo
        IF @ID_Promo IS NOT NULL
        BEGIN
            SELECT @PromoDiskon = Diskon FROM Promo WHERE ID_Promo = @ID_Promo;
        END

        -- Cek diskon member
        SELECT @MemberDiskon = Tm.Potongan_Harga
        FROM Langganan Lg
        INNER JOIN Tipe_Member Tm ON Lg.ID_Tipe = Tm.ID_Tipe
        WHERE Lg.ID_Customer = @ID_Customer
          AND Lg.Status = 1
          AND GETDATE() BETWEEN Lg.Tanggal_Mulai AND Lg.Tanggal_Selesai;

        -- Gunakan diskon terbesar
        SET @FinalTotal = @BasePrice - CASE 
            WHEN ISNULL(@MemberDiskon, 0) > ISNULL(@PromoDiskon, 0) THEN ISNULL(@MemberDiskon, 0) 
            ELSE ISNULL(@PromoDiskon, 0) 
        END;
        IF @FinalTotal < 0 SET @FinalTotal = 0;

        -- Insert booking
        INSERT INTO Booking (
            ID_Customer, ID_Karyawan, ID_Jadwal, ID_Promo,
            Tanggal_Booking, Metode_Pembayaran, Total_Bayar,
            Status, Created_By, Created_Date
        )
        VALUES (
            @ID_Customer, @ID_Karyawan, @ID_Jadwal, @ID_Promo,
            CAST(GETDATE() AS DATE), @Metode_Pembayaran, @FinalTotal,
            0, @Created_By, GETDATE()  -- Status 0 = Menunggu Konfirmasi
        );

        DECLARE @NewBookingID INT = SCOPE_IDENTITY();

        -- Update jadwal jadi tidak tersedia (via trigger, tapi kita set juga untuk safety)
        UPDATE Jadwal SET Status = 0, Modified_By = @Created_By, Modified_Date = GETDATE()
        WHERE ID_Jadwal = @ID_Jadwal;

        COMMIT TRANSACTION;

        -- Return hasil
        SELECT 
            @NewBookingID AS ID_Booking,
            'SUCCESS' AS Status,
            'Booking berhasil dibuat dengan ID: ' + CAST(@NewBookingID AS VARCHAR) AS Message,
            @FinalTotal AS Total_Bayar;

    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;

        SELECT 
            -1 AS ID_Booking,
            'ERROR' AS Status,
            ERROR_MESSAGE() AS Message,
            0 AS Total_Bayar;
    END CATCH
END;
GO

-- ============================================================
-- 6. STORED PROCEDURE: sp_Booking_KonfirmasiPembayaran
-- ============================================================
-- Mengkonfirmasi pembayaran booking (Status 0 -> 1)
-- ============================================================

IF OBJECT_ID('sp_Booking_KonfirmasiPembayaran', 'P') IS NOT NULL
    DROP PROCEDURE sp_Booking_KonfirmasiPembayaran;
GO

CREATE PROCEDURE sp_Booking_KonfirmasiPembayaran
    @ID_Booking     INT,
    @ID_Karyawan    INT,
    @Bukti_Pembayaran VARCHAR(255) = NULL
AS
BEGIN
    SET NOCOUNT ON;
    BEGIN TRY
        BEGIN TRANSACTION;

        -- Validasi: Booking exists dan status Menunggu
        IF NOT EXISTS (SELECT 1 FROM Booking WHERE ID_Booking = @ID_Booking AND Status = 0)
        BEGIN
            RAISERROR ('Booking tidak ditemukan atau status bukan Menunggu Konfirmasi.', 16, 1);
            ROLLBACK TRANSACTION;
            RETURN;
        END

        -- Validasi: Karyawan exists dan aktif
        IF NOT EXISTS (SELECT 1 FROM Karyawan WHERE ID_Karyawan = @ID_Karyawan AND Status = 1 AND Is_Deleted = 0)
        BEGIN
            RAISERROR ('Karyawan tidak valid.', 16, 1);
            ROLLBACK TRANSACTION;
            RETURN;
        END

        -- Update booking
        UPDATE Booking
        SET Status = 1,
            Bukti_Pembayaran = ISNULL(@Bukti_Pembayaran, Bukti_Pembayaran),
            Modified_By = CAST(@ID_Karyawan AS VARCHAR),
            Modified_Date = GETDATE()
        WHERE ID_Booking = @ID_Booking;

        COMMIT TRANSACTION;

        SELECT 
            @ID_Booking AS ID_Booking,
            'SUCCESS' AS Status,
            'Pembayaran berhasil dikonfirmasi. Booking status: Berhasil.' AS Message;

    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;

        SELECT 
            @ID_Booking AS ID_Booking,
            'ERROR' AS Status,
            ERROR_MESSAGE() AS Message;
    END CATCH
END;
GO

-- ============================================================
-- 7. STORED PROCEDURE: sp_Booking_Batalkan
-- ============================================================
-- Membatalkan booking dengan refund 50% dan log history
-- ============================================================

IF OBJECT_ID('sp_Booking_Batalkan', 'P') IS NOT NULL
    DROP PROCEDURE sp_Booking_Batalkan;
GO

CREATE PROCEDURE sp_Booking_Batalkan
    @ID_Booking     INT,
    @ID_Karyawan    INT,
    @Alasan         VARCHAR(255)
AS
BEGIN
    SET NOCOUNT ON;
    BEGIN TRY
        BEGIN TRANSACTION;

        -- Validasi: Booking exists dan belum dibatalkan/selesai
        DECLARE @CurrentStatus INT;
        DECLARE @TotalBayar DECIMAL(18,2);
        DECLARE @ID_Jadwal INT;
        DECLARE @MetodePembayaran VARCHAR(20);

        SELECT @CurrentStatus = Status, 
               @TotalBayar = Total_Bayar,
               @ID_Jadwal = ID_Jadwal,
               @MetodePembayaran = Metode_Pembayaran
        FROM Booking 
        WHERE ID_Booking = @ID_Booking;

        IF @CurrentStatus IS NULL
        BEGIN
            RAISERROR ('Booking tidak ditemukan.', 16, 1);
            ROLLBACK TRANSACTION;
            RETURN;
        END

        IF @CurrentStatus = 3
        BEGIN
            RAISERROR ('Booking sudah dibatalkan sebelumnya.', 16, 1);
            ROLLBACK TRANSACTION;
            RETURN;
        END

        IF @CurrentStatus = 2
        BEGIN
            RAISERROR ('Booking sudah selesai, tidak dapat dibatalkan.', 16, 1);
            ROLLBACK TRANSACTION;
            RETURN;
        END

        -- Hitung refund 50%
        DECLARE @BiayaBatal DECIMAL(18,2) = @TotalBayar * 0.5;
        DECLARE @NominalRefund DECIMAL(18,2) = @TotalBayar * 0.5;

        -- Update booking status
        UPDATE Booking
        SET Status = 3,
            Modified_By = CAST(@ID_Karyawan AS VARCHAR),
            Modified_Date = GETDATE()
        WHERE ID_Booking = @ID_Booking;

        -- Insert ke Pembatalan_Booking
        INSERT INTO Pembatalan_Booking (
            ID_Booking, ID_Karyawan, Tanggal_Batal, Alasan,
            Biaya_Batal, Nominal_Refund, Metode_Refund, Status,
            Created_By, Created_Date
        )
        VALUES (
            @ID_Booking, @ID_Karyawan, GETDATE(), @Alasan,
            @BiayaBatal, @NominalRefund, @MetodePembayaran, 1,
            CAST(@ID_Karyawan AS VARCHAR), GETDATE()
        );

        -- Jadwal akan otomatis tersedia lagi via trigger trg_Booking_SyncJadwal

        COMMIT TRANSACTION;

        SELECT 
            @ID_Booking AS ID_Booking,
            'SUCCESS' AS Status,
            'Booking berhasil dibatalkan. Refund 50% (Rp ' + FORMAT(@NominalRefund, 'N0') + ') dikembalikan via ' + @MetodePembayaran AS Message,
            @NominalRefund AS Nominal_Refund;

    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;

        SELECT 
            @ID_Booking AS ID_Booking,
            'ERROR' AS Status,
            ERROR_MESSAGE() AS Message,
            0 AS Nominal_Refund;
    END CATCH
END;
GO

-- ============================================================
-- 8. STORED PROCEDURE: sp_Booking_Selesai
-- ============================================================
-- Menandai booking sebagai selesai (Status 1 -> 2)
-- Biasanya dipanggil otomatis oleh scheduler/job
-- ============================================================

IF OBJECT_ID('sp_Booking_Selesai', 'P') IS NOT NULL
    DROP PROCEDURE sp_Booking_Selesai;
GO

CREATE PROCEDURE sp_Booking_Selesai
    @ID_Booking     INT,
    @Modified_By    VARCHAR(50) = 'SYSTEM_AUTO'
AS
BEGIN
    SET NOCOUNT ON;
    BEGIN TRY
        BEGIN TRANSACTION;

        -- Validasi: Booking exists dan status Berhasil
        IF NOT EXISTS (SELECT 1 FROM Booking WHERE ID_Booking = @ID_Booking AND Status = 1)
        BEGIN
            RAISERROR ('Booking tidak ditemukan atau status bukan Berhasil.', 16, 1);
            ROLLBACK TRANSACTION;
            RETURN;
        END

        -- Update booking
        UPDATE Booking
        SET Status = 2,
            Modified_By = @Modified_By,
            Modified_Date = GETDATE()
        WHERE ID_Booking = @ID_Booking;

        COMMIT TRANSACTION;

        SELECT 
            @ID_Booking AS ID_Booking,
            'SUCCESS' AS Status,
            'Booking berhasil diselesaikan.' AS Message;

    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;

        SELECT 
            @ID_Booking AS ID_Booking,
            'ERROR' AS Status,
            ERROR_MESSAGE() AS Message;
    END CATCH
END;
GO

-- ============================================================
-- 9. STORED PROCEDURE: sp_Booking_AutoComplete
-- ============================================================
-- Auto-complete semua booking yang waktu bermain sudah lewat
-- Bisa dijadikan SQL Agent Job untuk dijalankan berkala
-- ============================================================

IF OBJECT_ID('sp_Booking_AutoComplete', 'P') IS NOT NULL
    DROP PROCEDURE sp_Booking_AutoComplete;
GO

CREATE PROCEDURE sp_Booking_AutoComplete
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE @UpdatedCount INT = 0;

    BEGIN TRY
        BEGIN TRANSACTION;

        -- Update semua booking yang sudah lewat waktunya
        UPDATE B
        SET B.Status = 2,
            B.Modified_By = 'SYSTEM_AUTO',
            B.Modified_Date = GETDATE()
        FROM Booking B
        INNER JOIN Jadwal J ON B.ID_Jadwal = J.ID_Jadwal
        WHERE B.Status = 1
          AND (
              J.Tanggal < CAST(GETDATE() AS DATE)
              OR (J.Tanggal = CAST(GETDATE() AS DATE) AND J.Jam_Selesai <= CAST(GETDATE() AS TIME))
          );

        SET @UpdatedCount = @@ROWCOUNT;

        COMMIT TRANSACTION;

        SELECT 
            'SUCCESS' AS Status,
            CAST(@UpdatedCount AS VARCHAR) + ' booking berhasil diselesaikan otomatis.' AS Message,
            @UpdatedCount AS Jumlah_Updated;

    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;

        SELECT 
            'ERROR' AS Status,
            ERROR_MESSAGE() AS Message,
            0 AS Jumlah_Updated;
    END CATCH
END;
GO

-- ============================================================
-- 10. STORED PROCEDURE: sp_Booking_GetDetail
-- ============================================================
-- Mengambil detail lengkap booking dengan info customer, lapangan, dll
-- [DIUPDATE] Sekarang juga menampilkan data dari Detail_Jadwal
-- ============================================================

IF OBJECT_ID('sp_Booking_GetDetail', 'P') IS NOT NULL
    DROP PROCEDURE sp_Booking_GetDetail;
GO

CREATE PROCEDURE sp_Booking_GetDetail
    @ID_Booking     INT = NULL,
    @ID_Customer    INT = NULL,
    @Status         INT = NULL,
    @Tanggal_Dari   DATE = NULL,
    @Tanggal_Sampai DATE = NULL
AS
BEGIN
    SET NOCOUNT ON;

    SELECT 
        B.ID_Booking,
        B.ID_Customer,
        C.Nama_Customer,
        C.Email AS Email_Customer,
        C.No_Telepon AS Telepon_Customer,
        B.ID_Karyawan,
        K.Nama_Karyawan AS Nama_Karyawan_Input,
        B.ID_Jadwal,
        J.Tanggal AS Tanggal_Jadwal,
        J.Jam_Mulai,
        J.Jam_Selesai,
        L.ID_Lapangan,
        L.Nama_Lapangan,
        L.Harga_Sewa AS Harga_Dasar,
        B.ID_Promo,
        P.Nama_Promo,
        P.Diskon AS Diskon_Promo,
        B.Tanggal_Booking,
        B.Metode_Pembayaran,
        B.Bukti_Pembayaran,
        B.Total_Bayar,
        B.Status,
        CASE B.Status
            WHEN 0 THEN 'Menunggu Konfirmasi'
            WHEN 1 THEN 'Berhasil'
            WHEN 2 THEN 'Selesai'
            WHEN 3 THEN 'Dibatalkan'
            ELSE 'Unknown'
        END AS Status_Label,
        B.Created_By,
        B.Created_Date,
        B.Modified_By,
        B.Modified_Date,
        -- Cek membership aktif
        CASE WHEN Lg.ID_Langganan IS NOT NULL THEN 1 ELSE 0 END AS Is_Member,
        Tm.Nama_Tipe AS Tipe_Member,
        Tm.Potongan_Harga AS Diskon_Member,
        -- Data dari Detail_Jadwal (3 kolom: ID_Booking, ID_Jadwal, Jumlah_Digunakan)
        DJ.Jumlah_Digunakan AS Jumlah_Slot_Digunakan
    FROM Booking B
    INNER JOIN Customer C ON B.ID_Customer = C.ID_Customer
    INNER JOIN Karyawan K ON B.ID_Karyawan = K.ID_Karyawan
    INNER JOIN Jadwal J ON B.ID_Jadwal = J.ID_Jadwal
    INNER JOIN Lapangan L ON J.ID_Lapangan = L.ID_Lapangan
    LEFT JOIN Promo P ON B.ID_Promo = P.ID_Promo
    LEFT JOIN Langganan Lg ON B.ID_Customer = Lg.ID_Customer 
                          AND Lg.Status = 1 
                          AND GETDATE() BETWEEN Lg.Tanggal_Mulai AND Lg.Tanggal_Selesai
    LEFT JOIN Tipe_Member Tm ON Lg.ID_Tipe = Tm.ID_Tipe
    LEFT JOIN Detail_Jadwal DJ ON B.ID_Booking = DJ.ID_Booking AND B.ID_Jadwal = DJ.ID_Jadwal
    WHERE (@ID_Booking IS NULL OR B.ID_Booking = @ID_Booking)
      AND (@ID_Customer IS NULL OR B.ID_Customer = @ID_Customer)
      AND (@Status IS NULL OR B.Status = @Status)
      AND (@Tanggal_Dari IS NULL OR B.Tanggal_Booking >= @Tanggal_Dari)
      AND (@Tanggal_Sampai IS NULL OR B.Tanggal_Booking <= @Tanggal_Sampai)
    ORDER BY 
        CASE B.Status
            WHEN 0 THEN 0
            WHEN 1 THEN 1
            WHEN 2 THEN 2
            WHEN 3 THEN 3
        END,
        B.Tanggal_Booking DESC;
END;
GO

-- ============================================================
-- 11. STORED PROCEDURE: sp_Booking_GetHistory
-- ============================================================
-- Mengambil log history/audit trail untuk satu booking
-- ============================================================

IF OBJECT_ID('sp_Booking_GetHistory', 'P') IS NOT NULL
    DROP PROCEDURE sp_Booking_GetHistory;
GO

CREATE PROCEDURE sp_Booking_GetHistory
    @ID_Booking     INT
AS
BEGIN
    SET NOCOUNT ON;

    SELECT 
        ID_History,
        ID_Booking,
        Action_Type,
        Action_By,
        Action_Date,
        Action_Description,
        Old_Values,
        New_Values,
        Status AS Status_Akhir,
        CASE Status
            WHEN 0 THEN 'Menunggu'
            WHEN 1 THEN 'Berhasil'
            WHEN 2 THEN 'Selesai'
            WHEN 3 THEN 'Dibatalkan'
            ELSE '-'
        END AS Status_Label,
        Total_Bayar
    FROM Booking_History
    WHERE ID_Booking = @ID_Booking
    ORDER BY Action_Date DESC;
END;
GO

-- ============================================================
-- 12. STORED PROCEDURE: sp_Booking_GetStats
-- ============================================================
-- Mengambil statistik booking untuk dashboard
-- ============================================================

IF OBJECT_ID('sp_Booking_GetStats', 'P') IS NOT NULL
    DROP PROCEDURE sp_Booking_GetStats;
GO

CREATE PROCEDURE sp_Booking_GetStats
    @Tanggal_Dari   DATE = NULL,
    @Tanggal_Sampai DATE = NULL
AS
BEGIN
    SET NOCOUNT ON;

    SELECT 
        COUNT(*) AS Total_Booking,
        SUM(CASE WHEN Status = 0 THEN 1 ELSE 0 END) AS Menunggu,
        SUM(CASE WHEN Status = 1 THEN 1 ELSE 0 END) AS Berhasil,
        SUM(CASE WHEN Status = 2 THEN 1 ELSE 0 END) AS Selesai,
        SUM(CASE WHEN Status = 3 THEN 1 ELSE 0 END) AS Dibatalkan,
        SUM(CASE WHEN Status IN (1, 2) THEN Total_Bayar ELSE 0 END) AS Total_Omzet,
        SUM(CASE WHEN Status = 3 THEN Total_Bayar * 0.5 ELSE 0 END) AS Total_Refund,
        SUM(CASE WHEN Status = 3 THEN Total_Bayar * 0.5 ELSE 0 END) AS Total_Biaya_Batal
    FROM Booking
    WHERE (@Tanggal_Dari IS NULL OR Tanggal_Booking >= @Tanggal_Dari)
      AND (@Tanggal_Sampai IS NULL OR Tanggal_Booking <= @Tanggal_Sampai);
END;
GO

-- ============================================================
-- 13. STORED PROCEDURE: sp_Booking_Update
-- ============================================================
-- Update data booking (hanya untuk field tertentu yang boleh diubah)
-- ============================================================

IF OBJECT_ID('sp_Booking_Update', 'P') IS NOT NULL
    DROP PROCEDURE sp_Booking_Update;
GO

CREATE PROCEDURE sp_Booking_Update
    @ID_Booking         INT,
    @ID_Promo           INT = NULL,
    @Metode_Pembayaran  VARCHAR(20) = NULL,
    @Bukti_Pembayaran   VARCHAR(255) = NULL,
    @Modified_By        VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;
    BEGIN TRY
        BEGIN TRANSACTION;

        -- Validasi: Booking exists
        IF NOT EXISTS (SELECT 1 FROM Booking WHERE ID_Booking = @ID_Booking)
        BEGIN
            RAISERROR ('Booking tidak ditemukan.', 16, 1);
            ROLLBACK TRANSACTION;
            RETURN;
        END

        -- Validasi: Booking belum selesai/dibatalkan
        IF EXISTS (SELECT 1 FROM Booking WHERE ID_Booking = @ID_Booking AND Status IN (2, 3))
        BEGIN
            RAISERROR ('Booking sudah selesai atau dibatalkan, tidak dapat diubah.', 16, 1);
            ROLLBACK TRANSACTION;
            RETURN;
        END

        -- Update booking
        UPDATE Booking
        SET ID_Promo = ISNULL(@ID_Promo, ID_Promo),
            Metode_Pembayaran = ISNULL(@Metode_Pembayaran, Metode_Pembayaran),
            Bukti_Pembayaran = ISNULL(@Bukti_Pembayaran, Bukti_Pembayaran),
            Modified_By = @Modified_By,
            Modified_Date = GETDATE()
        WHERE ID_Booking = @ID_Booking;

        COMMIT TRANSACTION;

        SELECT 
            @ID_Booking AS ID_Booking,
            'SUCCESS' AS Status,
            'Booking berhasil diperbarui.' AS Message;

    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;

        SELECT 
            @ID_Booking AS ID_Booking,
            'ERROR' AS Status,
            ERROR_MESSAGE() AS Message;
    END CATCH
END;
GO

-- ============================================================
-- 14. STORED PROCEDURE: sp_Booking_Delete (Soft Delete)
-- ============================================================
-- Soft delete booking dengan alasan
-- ============================================================

IF OBJECT_ID('sp_Booking_Delete', 'P') IS NOT NULL
    DROP PROCEDURE sp_Booking_Delete;
GO

CREATE PROCEDURE sp_Booking_Delete
    @ID_Booking     INT,
    @Deleted_By     VARCHAR(50),
    @Alasan         VARCHAR(255) = NULL
AS
BEGIN
    SET NOCOUNT ON;
    BEGIN TRY
        BEGIN TRANSACTION;

        -- Validasi: Booking exists
        IF NOT EXISTS (SELECT 1 FROM Booking WHERE ID_Booking = @ID_Booking)
        BEGIN
            RAISERROR ('Booking tidak ditemukan.', 16, 1);
            ROLLBACK TRANSACTION;
            RETURN;
        END

        -- Hapus booking (hard delete karena tabel Booking tidak punya Is_Deleted)
        -- Tapi kita log dulu ke history
        DELETE FROM Booking WHERE ID_Booking = @ID_Booking;

        COMMIT TRANSACTION;

        SELECT 
            @ID_Booking AS ID_Booking,
            'SUCCESS' AS Status,
            'Booking berhasil dihapus.' AS Message;

    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;

        SELECT 
            @ID_Booking AS ID_Booking,
            'ERROR' AS Status,
            ERROR_MESSAGE() AS Message;
    END CATCH
END;
GO

-- ============================================================
-- 15. STORED PROCEDURE: sp_Booking_GetLaporanHarian
-- ============================================================
-- Laporan harian booking untuk manajemen
-- ============================================================

IF OBJECT_ID('sp_Booking_GetLaporanHarian', 'P') IS NOT NULL
    DROP PROCEDURE sp_Booking_GetLaporanHarian;
GO

CREATE PROCEDURE sp_Booking_GetLaporanHarian
    @Tanggal DATE = NULL
AS
BEGIN
    SET NOCOUNT ON;

    IF @Tanggal IS NULL SET @Tanggal = CAST(GETDATE() AS DATE);

    SELECT 
        B.ID_Booking,
        C.Nama_Customer,
        L.Nama_Lapangan,
        J.Jam_Mulai,
        J.Jam_Selesai,
        B.Metode_Pembayaran,
        B.Total_Bayar,
        B.Status,
        CASE B.Status
            WHEN 0 THEN 'Menunggu'
            WHEN 1 THEN 'Berhasil'
            WHEN 2 THEN 'Selesai'
            WHEN 3 THEN 'Dibatalkan'
        END AS Status_Label,
        K.Nama_Karyawan AS Dikonfirmasi_Oleh
    FROM Booking B
    INNER JOIN Customer C ON B.ID_Customer = C.ID_Customer
    INNER JOIN Jadwal J ON B.ID_Jadwal = J.ID_Jadwal
    INNER JOIN Lapangan L ON J.ID_Lapangan = L.ID_Lapangan
    LEFT JOIN Karyawan K ON B.Modified_By = CAST(K.ID_Karyawan AS VARCHAR)
    WHERE J.Tanggal = @Tanggal
    ORDER BY J.Jam_Mulai;
END;
GO

-- ============================================================
-- 16. STORED PROCEDURE: sp_Booking_GetByCustomer
-- ============================================================
-- Mengambil semua booking milik satu customer
-- ============================================================

IF OBJECT_ID('sp_Booking_GetByCustomer', 'P') IS NOT NULL
    DROP PROCEDURE sp_Booking_GetByCustomer;
GO

CREATE PROCEDURE sp_Booking_GetByCustomer
    @ID_Customer    INT,
    @Status         INT = NULL
AS
BEGIN
    SET NOCOUNT ON;

    SELECT 
        B.ID_Booking,
        B.ID_Jadwal,
        J.Tanggal AS Tanggal_Main,
        J.Jam_Mulai,
        J.Jam_Selesai,
        L.Nama_Lapangan,
        L.Harga_Sewa,
        B.ID_Promo,
        P.Nama_Promo,
        P.Diskon AS Diskon_Promo,
        B.Tanggal_Booking,
        B.Metode_Pembayaran,
        B.Total_Bayar,
        B.Status,
        CASE B.Status
            WHEN 0 THEN 'Menunggu Konfirmasi'
            WHEN 1 THEN 'Berhasil'
            WHEN 2 THEN 'Selesai'
            WHEN 3 THEN 'Dibatalkan'
        END AS Status_Label,
        B.Created_Date,
        B.Modified_Date,
        CASE 
            WHEN B.Status = 0 AND J.Tanggal >= CAST(GETDATE() AS DATE) THEN 1 
            ELSE 0 
        END AS Bisa_Batalkan
    FROM Booking B
    INNER JOIN Jadwal J ON B.ID_Jadwal = J.ID_Jadwal
    INNER JOIN Lapangan L ON J.ID_Lapangan = L.ID_Lapangan
    LEFT JOIN Promo P ON B.ID_Promo = P.ID_Promo
    WHERE B.ID_Customer = @ID_Customer
      AND (@Status IS NULL OR B.Status = @Status)
    ORDER BY J.Tanggal DESC, J.Jam_Mulai DESC;
END;
GO

-- ============================================================
-- 17. STORED PROCEDURE BARU: sp_DetailJadwal_Insert
-- ============================================================
-- Menambahkan record ke Detail_Jadwal (3 kolom)
-- ID_Booking, ID_Jadwal, Jumlah_Digunakan
-- ============================================================

IF OBJECT_ID('sp_DetailJadwal_Insert', 'P') IS NOT NULL
    DROP PROCEDURE sp_DetailJadwal_Insert;
GO

CREATE PROCEDURE sp_DetailJadwal_Insert
    @ID_Booking         INT,
    @ID_Jadwal          INT,
    @Jumlah_Digunakan   INT = 1
AS
BEGIN
    SET NOCOUNT ON;
    BEGIN TRY
        BEGIN TRANSACTION;

        -- Validasi: Booking exists
        IF NOT EXISTS (SELECT 1 FROM Booking WHERE ID_Booking = @ID_Booking)
        BEGIN
            RAISERROR ('Booking tidak ditemukan.', 16, 1);
            ROLLBACK TRANSACTION;
            RETURN;
        END

        -- Validasi: Jadwal exists
        IF NOT EXISTS (SELECT 1 FROM Jadwal WHERE ID_Jadwal = @ID_Jadwal)
        BEGIN
            RAISERROR ('Jadwal tidak ditemukan.', 16, 1);
            ROLLBACK TRANSACTION;
            RETURN;
        END

        -- Validasi: Jumlah_Digunakan harus > 0
        IF @Jumlah_Digunakan <= 0
        BEGIN
            RAISERROR ('Jumlah_Digunakan harus lebih besar dari 0.', 16, 1);
            ROLLBACK TRANSACTION;
            RETURN;
        END

        -- Cek apakah kombinasi sudah ada
        IF EXISTS (SELECT 1 FROM Detail_Jadwal WHERE ID_Booking = @ID_Booking AND ID_Jadwal = @ID_Jadwal)
        BEGIN
            RAISERROR ('Kombinasi Booking dan Jadwal sudah ada di Detail_Jadwal.', 16, 1);
            ROLLBACK TRANSACTION;
            RETURN;
        END

        -- Insert ke Detail_Jadwal
        INSERT INTO Detail_Jadwal (ID_Booking, ID_Jadwal, Jumlah_Digunakan)
        VALUES (@ID_Booking, @ID_Jadwal, @Jumlah_Digunakan);

        COMMIT TRANSACTION;

        SELECT 
            @ID_Booking AS ID_Booking,
            @ID_Jadwal AS ID_Jadwal,
            'SUCCESS' AS Status,
            'Detail Jadwal berhasil ditambahkan.' AS Message;

    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;

        SELECT 
            @ID_Booking AS ID_Booking,
            @ID_Jadwal AS ID_Jadwal,
            'ERROR' AS Status,
            ERROR_MESSAGE() AS Message;
    END CATCH
END;
GO

-- ============================================================
-- 18. STORED PROCEDURE BARU: sp_DetailJadwal_SelectByBooking
-- ============================================================
-- Menampilkan semua Detail_Jadwal untuk satu booking
-- ============================================================

IF OBJECT_ID('sp_DetailJadwal_SelectByBooking', 'P') IS NOT NULL
    DROP PROCEDURE sp_DetailJadwal_SelectByBooking;
GO

CREATE PROCEDURE sp_DetailJadwal_SelectByBooking
    @ID_Booking     INT
AS
BEGIN
    SET NOCOUNT ON;

    SELECT 
        DJ.ID_Booking,
        DJ.ID_Jadwal,
        DJ.Jumlah_Digunakan,
        J.Tanggal AS Tanggal_Jadwal,
        J.Jam_Mulai,
        J.Jam_Selesai,
        J.Status AS Status_Jadwal,
        CASE J.Status
            WHEN 0 THEN 'Tidak Tersedia'
            WHEN 1 THEN 'Tersedia'
        END AS Status_Jadwal_Label,
        L.ID_Lapangan,
        L.Nama_Lapangan,
        L.Harga_Sewa
    FROM Detail_Jadwal DJ
    INNER JOIN Jadwal J ON DJ.ID_Jadwal = J.ID_Jadwal
    INNER JOIN Lapangan L ON J.ID_Lapangan = L.ID_Lapangan
    WHERE DJ.ID_Booking = @ID_Booking
    ORDER BY J.Tanggal, J.Jam_Mulai;
END;
GO

-- ============================================================
-- 19. STORED PROCEDURE BARU: sp_DetailJadwal_SelectAll
-- ============================================================
-- Menampilkan semua Detail_Jadwal dengan filter
-- ============================================================

IF OBJECT_ID('sp_DetailJadwal_SelectAll', 'P') IS NOT NULL
    DROP PROCEDURE sp_DetailJadwal_SelectAll;
GO

CREATE PROCEDURE sp_DetailJadwal_SelectAll
    @ID_Booking     INT = NULL,
    @ID_Jadwal      INT = NULL,
    @ID_Lapangan    INT = NULL
AS
BEGIN
    SET NOCOUNT ON;

    SELECT 
        DJ.ID_Booking,
        B.ID_Customer,
        C.Nama_Customer,
        DJ.ID_Jadwal,
        DJ.Jumlah_Digunakan,
        J.Tanggal AS Tanggal_Jadwal,
        J.Jam_Mulai,
        J.Jam_Selesai,
        J.Status AS Status_Jadwal,
        L.ID_Lapangan,
        L.Nama_Lapangan,
        L.Harga_Sewa,
        B.Status AS Status_Booking,
        CASE B.Status
            WHEN 0 THEN 'Menunggu Konfirmasi'
            WHEN 1 THEN 'Berhasil'
            WHEN 2 THEN 'Selesai'
            WHEN 3 THEN 'Dibatalkan'
        END AS Status_Booking_Label
    FROM Detail_Jadwal DJ
    INNER JOIN Booking B ON DJ.ID_Booking = B.ID_Booking
    INNER JOIN Customer C ON B.ID_Customer = C.ID_Customer
    INNER JOIN Jadwal J ON DJ.ID_Jadwal = J.ID_Jadwal
    INNER JOIN Lapangan L ON J.ID_Lapangan = L.ID_Lapangan
    WHERE (@ID_Booking IS NULL OR DJ.ID_Booking = @ID_Booking)
      AND (@ID_Jadwal IS NULL OR DJ.ID_Jadwal = @ID_Jadwal)
      AND (@ID_Lapangan IS NULL OR L.ID_Lapangan = @ID_Lapangan)
    ORDER BY J.Tanggal DESC, J.Jam_Mulai ASC;
END;
GO

-- ============================================================
-- 20. STORED PROCEDURE BARU: sp_DetailJadwal_Delete
-- ============================================================
-- Menghapus record dari Detail_Jadwal
-- ============================================================

IF OBJECT_ID('sp_DetailJadwal_Delete', 'P') IS NOT NULL
    DROP PROCEDURE sp_DetailJadwal_Delete;
GO

CREATE PROCEDURE sp_DetailJadwal_Delete
    @ID_Booking     INT,
    @ID_Jadwal      INT
AS
BEGIN
    SET NOCOUNT ON;
    BEGIN TRY
        BEGIN TRANSACTION;

        -- Validasi: Record exists
        IF NOT EXISTS (SELECT 1 FROM Detail_Jadwal WHERE ID_Booking = @ID_Booking AND ID_Jadwal = @ID_Jadwal)
        BEGIN
            RAISERROR ('Data Detail Jadwal tidak ditemukan.', 16, 1);
            ROLLBACK TRANSACTION;
            RETURN;
        END

        -- Delete record
        DELETE FROM Detail_Jadwal 
        WHERE ID_Booking = @ID_Booking AND ID_Jadwal = @ID_Jadwal;

        COMMIT TRANSACTION;

        SELECT 
            @ID_Booking AS ID_Booking,
            @ID_Jadwal AS ID_Jadwal,
            'SUCCESS' AS Status,
            'Detail Jadwal berhasil dihapus.' AS Message;

    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;

        SELECT 
            @ID_Booking AS ID_Booking,
            @ID_Jadwal AS ID_Jadwal,
            'ERROR' AS Status,
            ERROR_MESSAGE() AS Message;
    END CATCH
END;
GO

-- ============================================================
-- 21. STORED PROCEDURE BARU: sp_DetailJadwal_Update
-- ============================================================
-- Update Jumlah_Digunakan pada Detail_Jadwal
-- ============================================================

IF OBJECT_ID('sp_DetailJadwal_Update', 'P') IS NOT NULL
    DROP PROCEDURE sp_DetailJadwal_Update;
GO

CREATE PROCEDURE sp_DetailJadwal_Update
    @ID_Booking         INT,
    @ID_Jadwal          INT,
    @Jumlah_Digunakan   INT
AS
BEGIN
    SET NOCOUNT ON;
    BEGIN TRY
        BEGIN TRANSACTION;

        -- Validasi: Record exists
        IF NOT EXISTS (SELECT 1 FROM Detail_Jadwal WHERE ID_Booking = @ID_Booking AND ID_Jadwal = @ID_Jadwal)
        BEGIN
            RAISERROR ('Data Detail Jadwal tidak ditemukan.', 16, 1);
            ROLLBACK TRANSACTION;
            RETURN;
        END

        -- Validasi: Jumlah_Digunakan harus > 0
        IF @Jumlah_Digunakan <= 0
        BEGIN
            RAISERROR ('Jumlah_Digunakan harus lebih besar dari 0.', 16, 1);
            ROLLBACK TRANSACTION;
            RETURN;
        END

        -- Update record
        UPDATE Detail_Jadwal
        SET Jumlah_Digunakan = @Jumlah_Digunakan
        WHERE ID_Booking = @ID_Booking AND ID_Jadwal = @ID_Jadwal;

        COMMIT TRANSACTION;

        SELECT 
            @ID_Booking AS ID_Booking,
            @ID_Jadwal AS ID_Jadwal,
            'SUCCESS' AS Status,
            'Detail Jadwal berhasil diperbarui.' AS Message;

    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;

        SELECT 
            @ID_Booking AS ID_Booking,
            @ID_Jadwal AS ID_Jadwal,
            'ERROR' AS Status,
            ERROR_MESSAGE() AS Message;
    END CATCH
END;
GO

-- ============================================================
-- VERIFIKASI: Cek semua object yang sudah dibuat
-- ============================================================
SELECT 
    'Stored Procedure' AS Object_Type,
    name AS Object_Name,
    create_date AS Created_Date
FROM sys.procedures
WHERE name LIKE 'sp_Booking%' OR name LIKE 'sp_DetailJadwal%'
UNION ALL
SELECT 
    'Trigger' AS Object_Type,
    name AS Object_Name,
    create_date AS Created_Date
FROM sys.triggers
WHERE name LIKE 'trg_Booking%'
UNION ALL
SELECT 
    'Table' AS Object_Type,
    name AS Object_Name,
    create_date AS Created_Date
FROM sys.tables
WHERE name LIKE 'Booking%' OR name = 'Detail_Jadwal'
ORDER BY Object_Type, Object_Name;
GO

PRINT '============================================================';
PRINT 'SETUP SELESAI - Stored Procedures & Triggers untuk Booking';
PRINT '             + Detail_Jadwal (3 Kolom)';
PRINT '============================================================';
PRINT '';
PRINT 'Daftar Object yang dibuat:';
PRINT '  Tables:';
PRINT '    - Booking_History (tabel log audit)';
PRINT '    - Detail_Jadwal (ID_Booking, ID_Jadwal, Jumlah_Digunakan)';
PRINT '  Triggers:';
PRINT '    - trg_Booking_AuditLog (log history INSERT/UPDATE/DELETE)';
PRINT '    - trg_Booking_SyncJadwal (sinkronisasi status jadwal)';
PRINT '    - trg_Booking_ValidasiDouble (cegah double booking)';
PRINT '    - trg_Booking_AutoHitungTotal (auto hitung total dengan diskon)';
PRINT '  Stored Procedures (Booking):';
PRINT '    - sp_Booking_Create (buat booking baru)';
PRINT '    - sp_Booking_KonfirmasiPembayaran (konfirmasi pembayaran)';
PRINT '    - sp_Booking_Batalkan (batalkan + refund 50%)';
PRINT '    - sp_Booking_Selesai (tandai selesai)';
PRINT '    - sp_Booking_AutoComplete (auto-complete batch)';
PRINT '    - sp_Booking_GetDetail [UPDATED] (detail booking + Detail_Jadwal)';
PRINT '    - sp_Booking_GetHistory (audit trail)';
PRINT '    - sp_Booking_GetStats (statistik dashboard)';
PRINT '    - sp_Booking_Update (update booking)';
PRINT '    - sp_Booking_Delete (hapus booking)';
PRINT '    - sp_Booking_GetLaporanHarian (laporan harian)';
PRINT '    - sp_Booking_GetByCustomer (booking per customer)';
PRINT '  Stored Procedures (Detail_Jadwal) [BARU]:';
PRINT '    - sp_DetailJadwal_Insert (tambah record Detail_Jadwal)';
PRINT '    - sp_DetailJadwal_SelectByBooking (tampil per booking)';
PRINT '    - sp_DetailJadwal_SelectAll (tampil semua dengan filter)';
PRINT '    - sp_DetailJadwal_Delete (hapus record)';
PRINT '    - sp_DetailJadwal_Update (update Jumlah_Digunakan)';
PRINT '';
PRINT 'Cara penggunaan contoh:';
PRINT '  EXEC sp_Booking_Create @ID_Customer=1, @ID_Karyawan=2, @ID_Jadwal=15, @Metode_Pembayaran="Transfer Bank", @Created_By="SYSTEM";';
PRINT '  EXEC sp_DetailJadwal_Insert @ID_Booking=1, @ID_Jadwal=1, @Jumlah_Digunakan=1;';
PRINT '  EXEC sp_DetailJadwal_SelectByBooking @ID_Booking=1;';
PRINT '============================================================';
GO