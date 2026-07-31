-- Membuat Tabel Log untuk menampung riwayat perubahan transaksi
CREATE TABLE Booking_Log (
    Log_ID INT IDENTITY(1,1) PRIMARY KEY,
    ID_Booking INT,
    Aksi VARCHAR(50),
    Status_Lama INT,
    Status_Baru INT,
    Waktu_Log DATETIME DEFAULT GETDATE(),
    Pengguna VARCHAR(50)
);
GO

-- Trigger pada proses Create & Update transaksi Booking
CREATE TRIGGER trg_Booking_AfterInsertUpdate
ON Booking
AFTER INSERT, UPDATE
AS
BEGIN
    SET NOCOUNT ON;

    -- CASE 1: PROSES INSERT (Transaksi Baru)
    IF EXISTS (SELECT 1 FROM inserted) AND NOT EXISTS (SELECT 1 FROM deleted)
    BEGIN
        INSERT INTO Booking_Log (ID_Booking, Aksi, Status_Lama, Status_Baru, Pengguna)
        SELECT ID_Booking, 'INSERT', NULL, Status, Created_By
        FROM inserted;

        -- Otomatis ubah status jadwal terkait menjadi 0 (Tidak Tersedia)
        UPDATE J
        SET J.Status = 0
        FROM Jadwal J
        INNER JOIN inserted i ON J.ID_Jadwal = i.ID_Jadwal
        WHERE i.Status IN (0, 1, 2);
    END

    -- CASE 2: PROSES UPDATE (Perubahan Status Transaksi)
    IF EXISTS (SELECT 1 FROM inserted) AND EXISTS (SELECT 1 FROM deleted)
    BEGIN
        INSERT INTO Booking_Log (ID_Booking, Aksi, Status_Lama, Status_Baru, Pengguna)
        SELECT i.ID_Booking, 'UPDATE', d.Status, i.Status, COALESCE(i.Modified_By, i.Created_By)
        FROM inserted i
        INNER JOIN deleted d ON i.ID_Booking = d.ID_Booking;

        -- Jika Booking dibatalkan (Status = 3), kembalikan status jadwal terkait menjadi 1 (Tersedia)
        UPDATE J
        SET J.Status = 1
        FROM Jadwal J
        INNER JOIN inserted i ON J.ID_Jadwal = i.ID_Jadwal
        INNER JOIN deleted d ON i.ID_Booking = d.ID_Booking
        WHERE i.Status = 3 AND d.Status <> 3;

        -- Jika Booking diaktifkan kembali dari status batal
        UPDATE J
        SET J.Status = 0
        FROM Jadwal J
        INNER JOIN inserted i ON J.ID_Jadwal = i.ID_Jadwal
        INNER JOIN deleted d ON i.ID_Booking = d.ID_Booking
        WHERE i.Status IN (0, 1, 2) AND d.Status = 3;
    END
END;
GO



-- [CREATE] SP untuk membuat booking transaksi
CREATE OR ALTER PROCEDURE sp_Booking_Create
    @ID_Customer INT,
    @ID_Karyawan INT,
    @ID_Jadwal INT,
    @ID_Promo INT,
    @Metode_Pembayaran VARCHAR(20),
    @Created_By VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;
    BEGIN TRANSACTION;
    BEGIN TRY
        DECLARE @JadwalStatus INT, @HargaSewa DECIMAL(18,2), @Diskon DECIMAL(18,2) = 0, @TotalBayar DECIMAL(18,2);
        
        SELECT @JadwalStatus = J.Status, @HargaSewa = L.Harga_Sewa
        FROM Jadwal J
        INNER JOIN Lapangan L ON J.ID_Lapangan = L.ID_Lapangan
        WHERE J.ID_Jadwal = @ID_Jadwal AND J.Is_Deleted = 0;

        IF @JadwalStatus IS NULL OR @JadwalStatus = 0
        BEGIN
            THROW 50001, 'Jadwal tidak tersedia atau sudah terbooking.', 1;
        END

        -- 1. HITUNG DISKON MEMBER (MAKS 1x PER HARI)
        DECLARE @HasMember INT = 0, @MemberDiscount DECIMAL(18,2) = 0;
        DECLARE @SudahBookingHariIni INT = 0;

        SELECT @SudahBookingHariIni = COUNT(*)
        FROM Booking
        WHERE ID_Customer = @ID_Customer
          AND CAST(Created_Date AS DATE) = CAST(GETDATE() AS DATE)
          AND Status <> 3;

        IF @SudahBookingHariIni = 0
        BEGIN
            SELECT TOP 1 @HasMember = 1, @MemberDiscount = T.Potongan_Harga
            FROM Langganan L
            INNER JOIN Tipe_Member T ON L.ID_Tipe = T.ID_Tipe
            WHERE L.ID_Customer = @ID_Customer AND L.Status = 1 AND GETDATE() BETWEEN L.Tanggal_Mulai AND L.Tanggal_Selesai
            ORDER BY L.Tanggal_Selesai DESC;
        END

        IF @HasMember = 1
        BEGIN
            SET @Diskon = @MemberDiscount;
        END
        -- 2. HITUNG DISKON PROMO (MURNI PERSEN %)
        ELSE IF @ID_Promo IS NOT NULL AND @ID_Promo > 0
        BEGIN
            DECLARE @RawPromoDiskon DECIMAL(18,2) = 0;

            SELECT @RawPromoDiskon = ISNULL(Diskon, 0)
            FROM Promo 
            WHERE ID_Promo = @ID_Promo AND Status = 1 AND Is_Deleted = 0 
              AND CAST(GETDATE() AS DATE) BETWEEN Tanggal_Mulai AND Tanggal_Selesai;

            IF @RawPromoDiskon > 0
            BEGIN
                SET @Diskon = (@HargaSewa * @RawPromoDiskon) / 100.0; -- Murni Persen (%)
            END
        END

        SET @TotalBayar = @HargaSewa - @Diskon;
        IF @TotalBayar < 0 SET @TotalBayar = 0;

        INSERT INTO Booking (ID_Customer, ID_Karyawan, ID_Jadwal, ID_Promo, Tanggal_Booking, Metode_Pembayaran, Bukti_Pembayaran, Total_Bayar, Status, Created_By, Created_Date)
        VALUES (@ID_Customer, @ID_Karyawan, @ID_Jadwal, @ID_Promo, CAST(GETDATE() AS DATE), @Metode_Pembayaran, NULL, @TotalBayar, 0, @Created_By, GETDATE());

        DECLARE @New_ID_Booking INT = SCOPE_IDENTITY();
        
        COMMIT TRANSACTION;
        SELECT 'SUCCESS' AS Status, 'Booking berhasil dibuat.' AS Message, @New_ID_Booking AS ID_Booking;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        SELECT 'ERROR' AS Status, ERROR_MESSAGE() AS Message, NULL AS ID_Booking;
    END CATCH
END;
GO


-- [READ] SP untuk mendapatkan data ketersediaan slot jadwal
CREATE PROCEDURE sp_Jadwal_GetSlots
    @ID_Lapangan INT,
    @Tanggal DATE
AS
BEGIN
    SET NOCOUNT ON;
    SELECT J.ID_Jadwal, J.Jam_Mulai, J.Jam_Selesai, J.Status,
           CASE WHEN B.ID_Booking IS NOT NULL THEN 1 ELSE 0 END AS Ada_Booking
    FROM Jadwal J
    LEFT JOIN Booking B ON B.ID_Jadwal = J.ID_Jadwal AND B.Status <> 3
    WHERE J.ID_Lapangan = @ID_Lapangan AND J.Is_Deleted = 0 AND J.Tanggal = @Tanggal
    ORDER BY J.Jam_Mulai ASC;
END;
GO

-- [UPDATE] SP untuk memperbarui bukti pembayaran pada transaksi
CREATE PROCEDURE sp_Booking_UpdateBukti
    @ID_Booking INT,
    @Bukti_Pembayaran VARCHAR(255),
    @Modified_By VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;
    BEGIN TRY
        UPDATE Booking
        SET Bukti_Pembayaran = @Bukti_Pembayaran,
            Modified_By = @Modified_By,
            Modified_Date = GETDATE()
        WHERE ID_Booking = @ID_Booking;
        SELECT 'SUCCESS' AS Status, 'Bukti pembayaran berhasil diperbarui.' AS Message;
    END TRY
    BEGIN CATCH
        SELECT 'ERROR' AS Status, ERROR_MESSAGE() AS Message;
    END CATCH
END;
GO

-- [DELETE] SP untuk melakukan penonaktifan akun customer (soft delete)
CREATE PROCEDURE sp_Customer_SoftDelete
    @ID_Customer INT,
    @Modified_By VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;
    BEGIN TRY
        UPDATE Customer
        SET Is_Deleted = 1,
            Status = 0,
            Deleted_By = @Modified_By,
            Deleted_Date = GETDATE()
        WHERE ID_Customer = @ID_Customer AND Is_Deleted = 0;
        SELECT 'SUCCESS' AS Status;
    END TRY
    BEGIN CATCH
        SELECT 'ERROR' AS Status;
    END CATCH
END;
GO

-- SP tambahan untuk memuat data master (Read Lapangan, Promo, Customer Profile, dan Active Member)
CREATE PROCEDURE sp_Lapangan_GetActive AS
BEGIN
    SET NOCOUNT ON;
    SELECT ID_Lapangan, Nama_Lapangan, Harga_Sewa, Photo_Lapangan FROM Lapangan WHERE Status = 1 AND Is_Deleted = 0;
END;
GO

CREATE PROCEDURE sp_Promo_GetActive AS
BEGIN
    SET NOCOUNT ON;
    SELECT ID_Promo, Nama_Promo, Diskon FROM Promo 
    WHERE Status = 1 AND Is_Deleted = 0 AND CAST(GETDATE() AS DATE) BETWEEN Tanggal_Mulai AND Tanggal_Selesai;
END;
GO

CREATE PROCEDURE sp_Customer_GetProfile @ID_Customer INT AS
BEGIN
    SET NOCOUNT ON;
    SELECT Nama_Customer, Photo_Profile, Is_Deleted, Status FROM Customer WHERE ID_Customer = @ID_Customer;
END;
GO

CREATE OR ALTER PROCEDURE sp_Customer_GetActiveMember 
    @ID_Customer INT 
AS
BEGIN
    SET NOCOUNT ON;
    SELECT TOP 1 
        L.ID_Langganan, 
        L.ID_Customer, 
        L.ID_Tipe, 
        L.Tanggal_Mulai, 
        L.Tanggal_Selesai, 
        L.Status AS Status_Langganan,
        T.Nama_Tipe, 
        T.Potongan_Harga, -- Kolom potongan harga tipe member (50000) ditarik dengan aman tanpa tabrakan
        T.Harga_Member 
    FROM Langganan L
    INNER JOIN Tipe_Member T ON L.ID_Tipe = T.ID_Tipe
    WHERE L.ID_Customer = @ID_Customer 
      AND L.Status = 1 
      AND GETDATE() BETWEEN L.Tanggal_Mulai AND L.Tanggal_Selesai
    ORDER BY L.Tanggal_Selesai DESC;
END;
GO

-- 1. SP untuk mendapatkan satu karyawan aktif secara default
CREATE PROCEDURE sp_Karyawan_GetDefault
AS
BEGIN
    SET NOCOUNT ON;
    SELECT TOP 1 ID_Karyawan 
    FROM Karyawan 
    WHERE Status = 1 AND Is_Deleted = 0 
    ORDER BY ID_Karyawan ASC;
END;
GO

-- 2. SP untuk memvalidasi slot jadwal sebelum transaksi diproses
CREATE PROCEDURE sp_Jadwal_ValidateSlot
    @ID_Jadwal INT
AS
BEGIN
    SET NOCOUNT ON;
    SELECT J.Status, J.ID_Lapangan, L.Harga_Sewa
    FROM Jadwal J 
    INNER JOIN Lapangan L ON J.ID_Lapangan = L.ID_Lapangan
    WHERE J.ID_Jadwal = @ID_Jadwal;
END;
GO

-- 3. SP untuk membatalkan booking (Rollback Manual)
CREATE PROCEDURE sp_Booking_SetStatusBatal
    @ID_Booking INT,
    @Modified_By VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;
    UPDATE Booking 
    SET Status = 3, 
        Modified_By = @Modified_By, 
        Modified_Date = GETDATE() 
    WHERE ID_Booking = @ID_Booking;
END;
GO

-- 4. SP untuk memuat daftar fasilitas lapangan aktif
CREATE PROCEDURE sp_Fasilitas_GetActive
AS
BEGIN
    SET NOCOUNT ON;
    SELECT ID_Lapangan, Nama_Fasilitas 
    FROM Fasilitas_Lapangan 
    WHERE Status = 1 AND Is_Deleted = 0;
END;
GO

-- 5. SP untuk mendukung fungsi otomasi jadwal (Generate Jadwal)
CREATE PROCEDURE sp_Otomasi_GetLapanganAktif
AS
BEGIN
    SET NOCOUNT ON;
    SELECT ID_Lapangan FROM Lapangan WHERE Status = 1 AND Is_Deleted = 0;
END;
GO

CREATE PROCEDURE sp_Otomasi_CekJadwal
    @ID_Lapangan INT,
    @Tanggal DATE,
    @Jam_Mulai TIME,
    @Jam_Selesai TIME
AS
BEGIN
    SET NOCOUNT ON;
    SELECT ID_Jadwal 
    FROM Jadwal 
    WHERE ID_Lapangan = @ID_Lapangan 
      AND Tanggal = @Tanggal 
      AND Jam_Mulai = @Jam_Mulai 
      AND Jam_Selesai = @Jam_Selesai;
END;
GO

CREATE PROCEDURE sp_Otomasi_InsertJadwal
    @ID_Lapangan INT,
    @Tanggal DATE,
    @Jam_Mulai TIME,
    @Jam_Selesai TIME,
    @Created_By VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;
    INSERT INTO Jadwal(ID_Lapangan, Tanggal, Jam_Mulai, Jam_Selesai, Status, Is_Deleted, Created_By, Created_Date) 
    VALUES(@ID_Lapangan, @Tanggal, @Jam_Mulai, @Jam_Selesai, 1, 0, @Created_By, GETDATE());
END;
GO


-- UDF untuk menampilkan ringkasan data profil/dashboard milik customer tertentu
CREATE FUNCTION fn_Dashboard_GetCustomerStats (@ID_Customer INT)
RETURNS TABLE
AS
RETURN
(
    SELECT 
        COUNT(DISTINCT B.ID_Booking) AS Total_Booking,
        COALESCE(SUM(B.Total_Bayar), 0) AS Total_Pengeluaran,
        COUNT(DISTINCT L.ID_Langganan) AS Total_Langganan,
        (SELECT TOP 1 TM.Nama_Tipe 
         FROM Langganan LN 
         INNER JOIN Tipe_Member TM ON LN.ID_Tipe = TM.ID_Tipe 
         WHERE LN.ID_Customer = @ID_Customer AND LN.Status = 1 AND GETDATE() BETWEEN LN.Tanggal_Mulai AND LN.Tanggal_Selesai
        ) AS Status_Membership
    FROM Customer C
    LEFT JOIN Booking B ON C.ID_Customer = B.ID_Customer AND B.Status <> 3
    LEFT JOIN Langganan L ON C.ID_Customer = L.ID_Customer AND L.Status = 1
    WHERE C.ID_Customer = @ID_Customer
    GROUP BY C.ID_Customer
);
GO

-- UDF untuk menampilkan laporan penggunaan lapangan pada dashboard management
CREATE FUNCTION fn_Report_CourtUtilization ()
RETURNS TABLE
AS
RETURN
(
    SELECT 
        L.ID_Lapangan,
        L.Nama_Lapangan,
        COUNT(B.ID_Booking) AS Jumlah_Booking_Sukses,
        COALESCE(SUM(B.Total_Bayar), 0) AS Total_Pendapatan_Sewa,
        COUNT(PB.ID_Pembatalan) AS Jumlah_Pembatalan
    FROM Lapangan L
    LEFT JOIN Jadwal J ON L.ID_Lapangan = J.ID_Lapangan
    LEFT JOIN Booking B ON J.ID_Jadwal = B.ID_Jadwal AND B.Status IN (1, 2)
    LEFT JOIN Pembatalan_Booking PB ON B.ID_Booking = PB.ID_Booking
    WHERE L.Is_Deleted = 0
    GROUP BY L.ID_Lapangan, L.Nama_Lapangan
);
GO