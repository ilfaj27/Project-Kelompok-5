USE Hoopball;
GO

-- 1. UDF untuk mengambil Detail Transaksi Booking Lapangan
CREATE OR ALTER FUNCTION dbo.fn_GetBookingReport (
    @FilterType VARCHAR(10),
    @StartDate DATE,
    @EndDate DATE,
    @ID_Lapangan INT,
    @Status INT
)
RETURNS TABLE
AS
RETURN (
    SELECT 
        b.ID_Booking,
        b.Tanggal_Booking,
        b.Metode_Pembayaran,
        b.Total_Bayar,
        b.Status,
        c.Nama_Customer,
        k.Nama_Karyawan as Nama_Karyawan_Konfirm,
        l.Nama_Lapangan,
        l.Harga_Sewa,
        j.Tanggal as Tanggal_Main,
        j.Jam_Mulai,
        j.Jam_Selesai,
        p.Nama_Promo,
        p.Diskon as Diskon_Promo,
        tm.Nama_Tipe,
        tm.Potongan_Harga as Potongan_Member,
        pb.Nominal_Refund,
        pb.Biaya_Batal
    FROM Booking b
    LEFT JOIN Customer c ON b.ID_Customer = c.ID_Customer
    LEFT JOIN Karyawan k ON b.ID_Karyawan = k.ID_Karyawan
    LEFT JOIN Jadwal j ON b.ID_Jadwal = j.ID_Jadwal
    LEFT JOIN Lapangan l ON j.ID_Lapangan = l.ID_Lapangan
    LEFT JOIN Promo p ON b.ID_Promo = p.ID_Promo
    LEFT JOIN Langganan lg ON (lg.ID_Customer = b.ID_Customer AND lg.Status = 1 
        AND b.Tanggal_Booking BETWEEN lg.Tanggal_Mulai AND lg.Tanggal_Selesai)
    LEFT JOIN Tipe_Member tm ON lg.ID_Tipe = tm.ID_Tipe
    LEFT JOIN Pembatalan_Booking pb ON b.ID_Booking = pb.ID_Booking
    WHERE b.Created_By IS NOT NULL
      AND (@ID_Lapangan IS NULL OR l.ID_Lapangan = @ID_Lapangan)
      -- SINKRONISASI: Jika filter status bernilai ALL (NULL), maka abaikan status 0 (Menunggu Konfirmasi)
      AND ((@Status IS NULL AND b.Status <> 0) OR b.Status = @Status)
      AND (
          @FilterType = 'all'
          OR (@FilterType = 'today' AND CAST(b.Tanggal_Booking AS DATE) = CAST(GETDATE() AS DATE))
          OR (@FilterType = 'week' AND b.Tanggal_Booking >= DATEADD(day, -7, CAST(GETDATE() AS DATE)))
          OR (@FilterType = 'month' AND MONTH(b.Tanggal_Booking) = MONTH(GETDATE()) AND YEAR(b.Tanggal_Booking) = YEAR(GETDATE()))
          OR (@FilterType = 'year' AND YEAR(b.Tanggal_Booking) = YEAR(GETDATE()))
          OR (@FilterType = 'custom' AND b.Tanggal_Booking BETWEEN @StartDate AND @EndDate)
      )
);
GO


-- 2. UDF untuk mengambil data Statistik Dashboard Laporan
CREATE OR ALTER FUNCTION dbo.fn_GetBookingStats (
    @FilterType VARCHAR(10),
    @StartDate DATE,
    @EndDate DATE,
    @ID_Lapangan INT,
    @Status INT
)
RETURNS TABLE
AS
RETURN (
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN b.Status = 1 THEN 1 ELSE 0 END) as berhasil,
        SUM(CASE WHEN b.Status = 2 THEN 1 ELSE 0 END) as selesai,
        SUM(CASE WHEN b.Status = 3 THEN 1 ELSE 0 END) as batal,
        SUM(CASE WHEN b.Status = 0 THEN 1 ELSE 0 END) as menunggu,
        -- Menggunakan Gross Omzet (Status 1, 2, 3) agar rumus ($omzet_bersih = $omzet_kotor - $refund) di PHP bernilai akurat
        SUM(CASE WHEN b.Status IN (1, 2, 3) THEN b.Total_Bayar ELSE 0 END) as omzet,
        -- Total dana yang dikembalikan ke customer
        SUM(CASE WHEN b.Status = 3 THEN ISNULL(pb.Nominal_Refund, 0) ELSE 0 END) as refund
    FROM Booking b
    LEFT JOIN Pembatalan_Booking pb ON b.ID_Booking = pb.ID_Booking
    LEFT JOIN Jadwal j ON b.ID_Jadwal = j.ID_Jadwal
    LEFT JOIN Lapangan l ON j.ID_Lapangan = l.ID_Lapangan
    WHERE b.Created_By IS NOT NULL
      AND (@ID_Lapangan IS NULL OR l.ID_Lapangan = @ID_Lapangan)
      -- SINKRONISASI: Jika filter status bernilai ALL (NULL), maka abaikan status 0 (Menunggu Konfirmasi)
      AND ((@Status IS NULL AND b.Status <> 0) OR b.Status = @Status)
      AND (
          @FilterType = 'all'
          OR (@FilterType = 'today' AND CAST(b.Tanggal_Booking AS DATE) = CAST(GETDATE() AS DATE))
          OR (@FilterType = 'week' AND b.Tanggal_Booking >= DATEADD(day, -7, CAST(GETDATE() AS DATE)))
          OR (@FilterType = 'month' AND MONTH(b.Tanggal_Booking) = MONTH(GETDATE()) AND YEAR(b.Tanggal_Booking) = YEAR(GETDATE()))
          OR (@FilterType = 'year' AND YEAR(b.Tanggal_Booking) = YEAR(GETDATE()))
          OR (@FilterType = 'custom' AND b.Tanggal_Booking BETWEEN @StartDate AND @EndDate)
      )
);
GO

-- 4. UDF untuk mengambil Data Grafik/Trend Booking per Bulan
CREATE OR ALTER FUNCTION dbo.fn_GetBookingChartData (
    @FilterType VARCHAR(10),
    @StartDate DATE,
    @EndDate DATE,
    @ID_Lapangan INT,
    @Status INT
)
RETURNS TABLE
AS
RETURN (
    SELECT 
        MONTH(b.Tanggal_Booking) as bulan,
        YEAR(b.Tanggal_Booking) as tahun,
        SUM(CASE WHEN b.Status = 1 THEN 1 ELSE 0 END) as berhasil,
        SUM(CASE WHEN b.Status = 2 THEN 1 ELSE 0 END) as selesai,
        SUM(CASE WHEN b.Status = 3 THEN 1 ELSE 0 END) as batal,
        SUM(CASE WHEN b.Status = 0 THEN 1 ELSE 0 END) as menunggu
    FROM Booking b
    LEFT JOIN Jadwal j ON b.ID_Jadwal = j.ID_Jadwal
    LEFT JOIN Lapangan l ON j.ID_Lapangan = l.ID_Lapangan
    WHERE b.Created_By IS NOT NULL
      AND (@ID_Lapangan IS NULL OR l.ID_Lapangan = @ID_Lapangan)
      -- SINKRONISASI: Jika filter status bernilai ALL (NULL), maka abaikan status 0 (Menunggu Konfirmasi)
      AND ((@Status IS NULL AND b.Status <> 0) OR b.Status = @Status)
      AND (
          @FilterType = 'all'
          OR (@FilterType = 'today' AND CAST(b.Tanggal_Booking AS DATE) = CAST(GETDATE() AS DATE))
          OR (@FilterType = 'week' AND b.Tanggal_Booking >= DATEADD(day, -7, CAST(GETDATE() AS DATE)))
          OR (@FilterType = 'month' AND MONTH(b.Tanggal_Booking) = MONTH(GETDATE()) AND YEAR(b.Tanggal_Booking) = YEAR(GETDATE()))
          OR (@FilterType = 'year' AND YEAR(b.Tanggal_Booking) = YEAR(GETDATE()))
          OR (@FilterType = 'custom' AND b.Tanggal_Booking BETWEEN @StartDate AND @EndDate)
      )
    GROUP BY MONTH(b.Tanggal_Booking), YEAR(b.Tanggal_Booking)
);
GO

-- UDF Pendukung Laporan: Mengambil Daftar Lapangan Aktif untuk Opsi Filter
CREATE OR ALTER FUNCTION dbo.fn_GetActiveLapangan ()
RETURNS TABLE
AS
RETURN
(
    SELECT ID_Lapangan, Nama_Lapangan 
    FROM Lapangan 
    WHERE Status = 1 AND Is_Deleted = 0
);
GO


-- SP: Create Booking
CREATE PROCEDURE sp_InsertBooking
    @ID_Customer INT,
    @ID_Karyawan INT,
    @ID_Jadwal INT,
    @ID_Promo INT,
    @Tanggal_Booking DATE,
    @Metode_Pembayaran VARCHAR(20),
    @Bukti_Pembayaran VARCHAR(255),
    @Total_Bayar DECIMAL(18,2),
    @Status INT,
    @Created_By VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;
    INSERT INTO Booking (ID_Customer, ID_Karyawan, ID_Jadwal, ID_Promo, Tanggal_Booking, Metode_Pembayaran, Bukti_Pembayaran, Total_Bayar, Status, Created_By, Created_Date)
    VALUES (@ID_Customer, @ID_Karyawan, @ID_Jadwal, @ID_Promo, @Tanggal_Booking, @Metode_Pembayaran, @Bukti_Pembayaran, @Total_Bayar, @Status, @Created_By, GETDATE());
END;
GO

-- SP: Update Booking Status
CREATE PROCEDURE sp_UpdateBookingStatus
    @ID_Booking INT,
    @Status INT,
    @Modified_By VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;
    UPDATE Booking
    SET Status = @Status,
        Modified_By = @Modified_By,
        Modified_Date = GETDATE()
    WHERE ID_Booking = @ID_Booking;
END;
GO


-- Tabel Log Riwayat Perubahan Status Booking
CREATE TABLE Booking_Status_Log (
    Log_ID INT IDENTITY(1,1) PRIMARY KEY,
    ID_Booking INT,
    Old_Status INT,
    New_Status INT,
    Changed_By VARCHAR(50),
    Changed_Date DATETIME DEFAULT GETDATE()
);
GO

-- Trigger: 1) Mengubah status ketersediaan Jadwal Lapangan otomatis & 2) Logging Perubahan
CREATE TRIGGER trg_AfterBookingTransaction
ON Booking
AFTER INSERT, UPDATE
AS
BEGIN
    SET NOCOUNT ON;

    -- Update Jadwal Lapangan menjadi TIDAK TERSEDIA (Status = 0) jika Booking Berhasil/Selesai (Status = 1 atau 2)
    UPDATE j
    SET j.Status = 0
    FROM Jadwal j
    INNER JOIN inserted i ON j.ID_Jadwal = i.ID_Jadwal
    WHERE i.Status IN (1, 2);

    -- Kembalikan status Jadwal Lapangan menjadi TERSEDIA (Status = 1) jika Booking Dibatalkan (Status = 3)
    UPDATE j
    SET j.Status = 1
    FROM Jadwal j
    INNER JOIN inserted i ON j.ID_Jadwal = i.ID_Jadwal
    WHERE i.Status = 3;

    -- Pencatatan Riwayat Perubahan Status (Log History)
    IF EXISTS (SELECT 1 FROM deleted)
    BEGIN
        INSERT INTO Booking_Status_Log (ID_Booking, Old_Status, New_Status, Changed_By, Changed_Date)
        SELECT i.ID_Booking, d.Status, i.Status, COALESCE(i.Modified_By, 'SYSTEM'), GETDATE()
        FROM inserted i
        INNER JOIN deleted d ON i.ID_Booking = d.ID_Booking
        WHERE i.Status <> d.Status;
    END
    ELSE
    BEGIN
        INSERT INTO Booking_Status_Log (ID_Booking, Old_Status, New_Status, Changed_By, Changed_Date)
        SELECT i.ID_Booking, NULL, i.Status, i.Created_By, GETDATE()
        FROM inserted i;
    END
END;
GO