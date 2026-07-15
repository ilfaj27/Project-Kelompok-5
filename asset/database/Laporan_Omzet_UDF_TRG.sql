-- ============================================================
-- UDF LAPORAN OMZET - WAJIB DIGUNAKAN SEBAGAI SUMBER DATA
-- ============================================================

-- ============================================================
-- 1. UDF STATISTIK OMZET PER KATEGORI
-- ============================================================

-- UDF: Statistik Omzet Booking (dengan refund & biaya batal)
CREATE OR ALTER FUNCTION dbo.fn_GetOmzetBookingStats (
    @FilterType VARCHAR(50),
    @StartDate DATE,
    @EndDate DATE
)
RETURNS TABLE
AS
RETURN
(
    SELECT 
        ISNULL(SUM(CASE WHEN b.Status IN (1,2) THEN b.Total_Bayar ELSE 0 END), 0) as omzet,
        ISNULL(SUM(pb.Nominal_Refund), 0) as total_refund,
        ISNULL(SUM(pb.Biaya_Batal), 0) as total_biaya_batal
    FROM Booking b
    LEFT JOIN Pembatalan_Booking pb ON b.ID_Booking = pb.ID_Booking
    WHERE 
        (@FilterType = 'all' OR
         (@FilterType = 'today' AND CAST(b.Tanggal_Booking AS DATE) = CAST(GETDATE() AS DATE)) OR
         (@FilterType = 'week' AND b.Tanggal_Booking >= DATEADD(day, -7, CAST(GETDATE() AS DATE))) OR
         (@FilterType = 'month' AND MONTH(b.Tanggal_Booking) = MONTH(GETDATE()) AND YEAR(b.Tanggal_Booking) = YEAR(GETDATE())) OR
         (@FilterType = 'year' AND YEAR(b.Tanggal_Booking) = YEAR(GETDATE())) OR
         (@FilterType = 'custom' AND b.Tanggal_Booking BETWEEN @StartDate AND @EndDate))
);
GO

-- UDF: Statistik Omzet Langganan
CREATE OR ALTER FUNCTION dbo.fn_GetOmzetLanggananStats (
    @FilterType VARCHAR(50),
    @StartDate DATE,
    @EndDate DATE
)
RETURNS TABLE
AS
RETURN
(
    SELECT 
        ISNULL(SUM(lg.Total_Bayar), 0) as omzet
    FROM Langganan lg
    WHERE 
        (@FilterType = 'all' OR
         (@FilterType = 'today' AND CAST(lg.Tanggal_Mulai AS DATE) = CAST(GETDATE() AS DATE)) OR
         (@FilterType = 'week' AND lg.Tanggal_Mulai >= DATEADD(day, -7, CAST(GETDATE() AS DATE))) OR
         (@FilterType = 'month' AND MONTH(lg.Tanggal_Mulai) = MONTH(GETDATE()) AND YEAR(lg.Tanggal_Mulai) = YEAR(GETDATE())) OR
         (@FilterType = 'year' AND YEAR(lg.Tanggal_Mulai) = YEAR(GETDATE())) OR
         (@FilterType = 'custom' AND lg.Tanggal_Mulai BETWEEN @StartDate AND @EndDate))
);
GO

-- UDF: Statistik Omzet Beli Alat
CREATE OR ALTER FUNCTION dbo.fn_GetOmzetBeliAlatStats (
    @FilterType VARCHAR(50),
    @StartDate DATE,
    @EndDate DATE
)
RETURNS TABLE
AS
RETURN
(
    SELECT 
        ISNULL(SUM(ba.Total_Bayar), 0) as omzet
    FROM Beli_Alat ba
    WHERE ba.Status = 1
        AND (@FilterType = 'all' OR
            (@FilterType = 'today' AND CAST(ba.Tanggal_Beli AS DATE) = CAST(GETDATE() AS DATE)) OR
            (@FilterType = 'week' AND ba.Tanggal_Beli >= DATEADD(day, -7, CAST(GETDATE() AS DATE))) OR
            (@FilterType = 'month' AND MONTH(ba.Tanggal_Beli) = MONTH(GETDATE()) AND YEAR(ba.Tanggal_Beli) = YEAR(GETDATE())) OR
            (@FilterType = 'year' AND YEAR(ba.Tanggal_Beli) = YEAR(GETDATE())) OR
            (@FilterType = 'custom' AND ba.Tanggal_Beli BETWEEN @StartDate AND @EndDate))
);
GO

-- ============================================================
-- 2. UDF JUMLAH TRANSAKSI
-- ============================================================

-- UDF: Total Transaksi per Kategori
CREATE OR ALTER FUNCTION dbo.fn_GetTransaksiCount (
    @FilterType VARCHAR(50),
    @StartDate DATE,
    @EndDate DATE
)
RETURNS TABLE
AS
RETURN
(
    SELECT 
        (SELECT COUNT(*) FROM Booking b 
         WHERE (@FilterType = 'all' OR
                (@FilterType = 'today' AND CAST(b.Tanggal_Booking AS DATE) = CAST(GETDATE() AS DATE)) OR
                (@FilterType = 'week' AND b.Tanggal_Booking >= DATEADD(day, -7, CAST(GETDATE() AS DATE))) OR
                (@FilterType = 'month' AND MONTH(b.Tanggal_Booking) = MONTH(GETDATE()) AND YEAR(b.Tanggal_Booking) = YEAR(GETDATE())) OR
                (@FilterType = 'year' AND YEAR(b.Tanggal_Booking) = YEAR(GETDATE())) OR
                (@FilterType = 'custom' AND b.Tanggal_Booking BETWEEN @StartDate AND @EndDate))
        ) as total_booking,

        (SELECT COUNT(*) FROM Langganan lg 
         WHERE (@FilterType = 'all' OR
                (@FilterType = 'today' AND CAST(lg.Tanggal_Mulai AS DATE) = CAST(GETDATE() AS DATE)) OR
                (@FilterType = 'week' AND lg.Tanggal_Mulai >= DATEADD(day, -7, CAST(GETDATE() AS DATE))) OR
                (@FilterType = 'month' AND MONTH(lg.Tanggal_Mulai) = MONTH(GETDATE()) AND YEAR(lg.Tanggal_Mulai) = YEAR(GETDATE())) OR
                (@FilterType = 'year' AND YEAR(lg.Tanggal_Mulai) = YEAR(GETDATE())) OR
                (@FilterType = 'custom' AND lg.Tanggal_Mulai BETWEEN @StartDate AND @EndDate))
        ) as total_langganan,

        (SELECT COUNT(*) FROM Beli_Alat ba 
         WHERE ba.Status = 1
            AND (@FilterType = 'all' OR
                (@FilterType = 'today' AND CAST(ba.Tanggal_Beli AS DATE) = CAST(GETDATE() AS DATE)) OR
                (@FilterType = 'week' AND ba.Tanggal_Beli >= DATEADD(day, -7, CAST(GETDATE() AS DATE))) OR
                (@FilterType = 'month' AND MONTH(ba.Tanggal_Beli) = MONTH(GETDATE()) AND YEAR(ba.Tanggal_Beli) = YEAR(GETDATE())) OR
                (@FilterType = 'year' AND YEAR(ba.Tanggal_Beli) = YEAR(GETDATE())) OR
                (@FilterType = 'custom' AND ba.Tanggal_Beli BETWEEN @StartDate AND @EndDate))
        ) as total_beli,

        (SELECT COUNT(*) FROM Booking b 
         WHERE b.Status = 3
            AND (@FilterType = 'all' OR
                (@FilterType = 'today' AND CAST(b.Tanggal_Booking AS DATE) = CAST(GETDATE() AS DATE)) OR
                (@FilterType = 'week' AND b.Tanggal_Booking >= DATEADD(day, -7, CAST(GETDATE() AS DATE))) OR
                (@FilterType = 'month' AND MONTH(b.Tanggal_Booking) = MONTH(GETDATE()) AND YEAR(b.Tanggal_Booking) = YEAR(GETDATE())) OR
                (@FilterType = 'year' AND YEAR(b.Tanggal_Booking) = YEAR(GETDATE())) OR
                (@FilterType = 'custom' AND b.Tanggal_Booking BETWEEN @StartDate AND @EndDate))
        ) as total_batal
);
GO

-- ============================================================
-- 3. UDF CHART DATA - OMZET PER BULAN
-- ============================================================

-- UDF: Data Chart Omzet per Bulan (semua sumber)
CREATE OR ALTER FUNCTION dbo.fn_GetOmzetChartData (
    @FilterType VARCHAR(50),
    @StartDate DATE,
    @EndDate DATE
)
RETURNS TABLE
AS
RETURN
(
    SELECT 
        COALESCE(b.tahun, l.tahun, ba.tahun) as tahun,
        COALESCE(b.bulan, l.bulan, ba.bulan) as bulan,
        ISNULL(b.omzet, 0) as booking_omzet,
        ISNULL(b.refund, 0) as booking_refund,
        ISNULL(l.omzet, 0) as langganan_omzet,
        ISNULL(ba.omzet, 0) as beli_omzet
    FROM 
        (SELECT YEAR(b.Tanggal_Booking) as tahun, MONTH(b.Tanggal_Booking) as bulan,
                SUM(CASE WHEN b.Status IN (1,2) THEN b.Total_Bayar ELSE 0 END) as omzet,
                ISNULL(SUM(pb.Nominal_Refund), 0) as refund
         FROM Booking b
         LEFT JOIN Pembatalan_Booking pb ON b.ID_Booking = pb.ID_Booking
         WHERE (@FilterType = 'all' OR
                (@FilterType = 'today' AND CAST(b.Tanggal_Booking AS DATE) = CAST(GETDATE() AS DATE)) OR
                (@FilterType = 'week' AND b.Tanggal_Booking >= DATEADD(day, -7, CAST(GETDATE() AS DATE))) OR
                (@FilterType = 'month' AND MONTH(b.Tanggal_Booking) = MONTH(GETDATE()) AND YEAR(b.Tanggal_Booking) = YEAR(GETDATE())) OR
                (@FilterType = 'year' AND YEAR(b.Tanggal_Booking) = YEAR(GETDATE())) OR
                (@FilterType = 'custom' AND b.Tanggal_Booking BETWEEN @StartDate AND @EndDate))
         GROUP BY YEAR(b.Tanggal_Booking), MONTH(b.Tanggal_Booking)) b
    FULL OUTER JOIN
        (SELECT YEAR(lg.Tanggal_Mulai) as tahun, MONTH(lg.Tanggal_Mulai) as bulan,
                SUM(lg.Total_Bayar) as omzet
         FROM Langganan lg
         WHERE (@FilterType = 'all' OR
                (@FilterType = 'today' AND CAST(lg.Tanggal_Mulai AS DATE) = CAST(GETDATE() AS DATE)) OR
                (@FilterType = 'week' AND lg.Tanggal_Mulai >= DATEADD(day, -7, CAST(GETDATE() AS DATE))) OR
                (@FilterType = 'month' AND MONTH(lg.Tanggal_Mulai) = MONTH(GETDATE()) AND YEAR(lg.Tanggal_Mulai) = YEAR(GETDATE())) OR
                (@FilterType = 'year' AND YEAR(lg.Tanggal_Mulai) = YEAR(GETDATE())) OR
                (@FilterType = 'custom' AND lg.Tanggal_Mulai BETWEEN @StartDate AND @EndDate))
         GROUP BY YEAR(lg.Tanggal_Mulai), MONTH(lg.Tanggal_Mulai)) l
    ON b.tahun = l.tahun AND b.bulan = l.bulan
    FULL OUTER JOIN
        (SELECT YEAR(ba.Tanggal_Beli) as tahun, MONTH(ba.Tanggal_Beli) as bulan,
                SUM(ba.Total_Bayar) as omzet
         FROM Beli_Alat ba
         WHERE ba.Status = 1
            AND (@FilterType = 'all' OR
                (@FilterType = 'today' AND CAST(ba.Tanggal_Beli AS DATE) = CAST(GETDATE() AS DATE)) OR
                (@FilterType = 'week' AND ba.Tanggal_Beli >= DATEADD(day, -7, CAST(GETDATE() AS DATE))) OR
                (@FilterType = 'month' AND MONTH(ba.Tanggal_Beli) = MONTH(GETDATE()) AND YEAR(ba.Tanggal_Beli) = YEAR(GETDATE())) OR
                (@FilterType = 'year' AND YEAR(ba.Tanggal_Beli) = YEAR(GETDATE())) OR
                (@FilterType = 'custom' AND ba.Tanggal_Beli BETWEEN @StartDate AND @EndDate))
         GROUP BY YEAR(ba.Tanggal_Beli), MONTH(ba.Tanggal_Beli)) ba
    ON COALESCE(b.tahun, l.tahun) = ba.tahun AND COALESCE(b.bulan, l.bulan) = ba.bulan
);
GO

-- ============================================================
-- 4. UDF TRANSAKSI TERBARU (TOP 5)
-- ============================================================

-- UDF: 5 Booking Terbaru
CREATE OR ALTER FUNCTION dbo.fn_GetRecentBooking (
    @FilterType VARCHAR(50),
    @StartDate DATE,
    @EndDate DATE
)
RETURNS TABLE
AS
RETURN
(
    SELECT TOP 5 
        b.ID_Booking, 
        b.Tanggal_Booking, 
        b.Total_Bayar, 
        b.Status, 
        b.Metode_Pembayaran,
        c.Nama_Customer, 
        l.Nama_Lapangan
    FROM Booking b
    LEFT JOIN Customer c ON b.ID_Customer = c.ID_Customer
    LEFT JOIN Jadwal j ON b.ID_Jadwal = j.ID_Jadwal
    LEFT JOIN Lapangan l ON j.ID_Lapangan = l.ID_Lapangan
    WHERE (@FilterType = 'all' OR
           (@FilterType = 'today' AND CAST(b.Tanggal_Booking AS DATE) = CAST(GETDATE() AS DATE)) OR
           (@FilterType = 'week' AND b.Tanggal_Booking >= DATEADD(day, -7, CAST(GETDATE() AS DATE))) OR
           (@FilterType = 'month' AND MONTH(b.Tanggal_Booking) = MONTH(GETDATE()) AND YEAR(b.Tanggal_Booking) = YEAR(GETDATE())) OR
           (@FilterType = 'year' AND YEAR(b.Tanggal_Booking) = YEAR(GETDATE())) OR
           (@FilterType = 'custom' AND b.Tanggal_Booking BETWEEN @StartDate AND @EndDate))
    ORDER BY b.Tanggal_Booking DESC
);
GO

-- UDF: 5 Langganan Terbaru
CREATE OR ALTER FUNCTION dbo.fn_GetRecentLangganan (
    @FilterType VARCHAR(50),
    @StartDate DATE,
    @EndDate DATE
)
RETURNS TABLE
AS
RETURN
(
    SELECT TOP 5 
        lg.ID_Langganan, 
        lg.Tanggal_Mulai, 
        lg.Total_Bayar, 
        lg.Status,
        c.Nama_Customer, 
        tm.Nama_Tipe
    FROM Langganan lg
    LEFT JOIN Customer c ON lg.ID_Customer = c.ID_Customer
    LEFT JOIN Tipe_Member tm ON lg.ID_Tipe = tm.ID_Tipe
    WHERE (@FilterType = 'all' OR
           (@FilterType = 'today' AND CAST(lg.Tanggal_Mulai AS DATE) = CAST(GETDATE() AS DATE)) OR
           (@FilterType = 'week' AND lg.Tanggal_Mulai >= DATEADD(day, -7, CAST(GETDATE() AS DATE))) OR
           (@FilterType = 'month' AND MONTH(lg.Tanggal_Mulai) = MONTH(GETDATE()) AND YEAR(lg.Tanggal_Mulai) = YEAR(GETDATE())) OR
           (@FilterType = 'year' AND YEAR(lg.Tanggal_Mulai) = YEAR(GETDATE())) OR
           (@FilterType = 'custom' AND lg.Tanggal_Mulai BETWEEN @StartDate AND @EndDate))
    ORDER BY lg.Tanggal_Mulai DESC
);
GO

-- UDF: 5 Pembelian Alat Terbaru
CREATE OR ALTER FUNCTION dbo.fn_GetRecentBeliAlat (
    @FilterType VARCHAR(50),
    @StartDate DATE,
    @EndDate DATE
)
RETURNS TABLE
AS
RETURN
(
    SELECT TOP 5 
        ba.ID_Beli, 
        ba.Tanggal_Beli, 
        ba.Total_Bayar, 
        ba.Metode_Pembayaran,
        c.Nama_Customer
    FROM Beli_Alat ba
    LEFT JOIN Customer c ON ba.ID_Customer = c.ID_Customer
    WHERE ba.Status = 1
        AND (@FilterType = 'all' OR
             (@FilterType = 'today' AND CAST(ba.Tanggal_Beli AS DATE) = CAST(GETDATE() AS DATE)) OR
             (@FilterType = 'week' AND ba.Tanggal_Beli >= DATEADD(day, -7, CAST(GETDATE() AS DATE))) OR
             (@FilterType = 'month' AND MONTH(ba.Tanggal_Beli) = MONTH(GETDATE()) AND YEAR(ba.Tanggal_Beli) = YEAR(GETDATE())) OR
             (@FilterType = 'year' AND YEAR(ba.Tanggal_Beli) = YEAR(GETDATE())) OR
             (@FilterType = 'custom' AND ba.Tanggal_Beli BETWEEN @StartDate AND @EndDate))
    ORDER BY ba.Tanggal_Beli DESC
);
GO

-- ============================================================
-- 5. UDF UNTUK CETAK PDF & EXCEL (Data Lengkap)
-- ============================================================

-- UDF: Data Booking Lengkap untuk Cetak
CREATE OR ALTER FUNCTION dbo.fn_GetBookingReport (
    @FilterType VARCHAR(50),
    @StartDate DATE,
    @EndDate DATE
)
RETURNS TABLE
AS
RETURN
(
    SELECT 
        b.ID_Booking, 
        b.Tanggal_Booking, 
        b.Total_Bayar, 
        b.Status, 
        b.Metode_Pembayaran,
        c.Nama_Customer, 
        l.Nama_Lapangan
    FROM Booking b
    LEFT JOIN Customer c ON b.ID_Customer = c.ID_Customer
    LEFT JOIN Jadwal j ON b.ID_Jadwal = j.ID_Jadwal
    LEFT JOIN Lapangan l ON j.ID_Lapangan = l.ID_Lapangan
    WHERE (@FilterType = 'all' OR
           (@FilterType = 'today' AND CAST(b.Tanggal_Booking AS DATE) = CAST(GETDATE() AS DATE)) OR
           (@FilterType = 'week' AND b.Tanggal_Booking >= DATEADD(day, -7, CAST(GETDATE() AS DATE))) OR
           (@FilterType = 'month' AND MONTH(b.Tanggal_Booking) = MONTH(GETDATE()) AND YEAR(b.Tanggal_Booking) = YEAR(GETDATE())) OR
           (@FilterType = 'year' AND YEAR(b.Tanggal_Booking) = YEAR(GETDATE())) OR
           (@FilterType = 'custom' AND b.Tanggal_Booking BETWEEN @StartDate AND @EndDate))
);
GO

-- UDF: Data Langganan Lengkap untuk Cetak
CREATE OR ALTER FUNCTION dbo.fn_GetLanggananReportOmzet (
    @FilterType VARCHAR(50),
    @StartDate DATE,
    @EndDate DATE
)
RETURNS TABLE
AS
RETURN
(
    SELECT 
        lg.ID_Langganan, 
        lg.Tanggal_Mulai, 
        lg.Total_Bayar, 
        lg.Status, 
        lg.Metode_Pembayaran,
        c.Nama_Customer, 
        tm.Nama_Tipe
    FROM Langganan lg
    LEFT JOIN Customer c ON lg.ID_Customer = c.ID_Customer
    LEFT JOIN Tipe_Member tm ON lg.ID_Tipe = tm.ID_Tipe
    WHERE (@FilterType = 'all' OR
           (@FilterType = 'today' AND CAST(lg.Tanggal_Mulai AS DATE) = CAST(GETDATE() AS DATE)) OR
           (@FilterType = 'week' AND lg.Tanggal_Mulai >= DATEADD(day, -7, CAST(GETDATE() AS DATE))) OR
           (@FilterType = 'month' AND MONTH(lg.Tanggal_Mulai) = MONTH(GETDATE()) AND YEAR(lg.Tanggal_Mulai) = YEAR(GETDATE())) OR
           (@FilterType = 'year' AND YEAR(lg.Tanggal_Mulai) = YEAR(GETDATE())) OR
           (@FilterType = 'custom' AND lg.Tanggal_Mulai BETWEEN @StartDate AND @EndDate))
);
GO

-- UDF: Data Beli Alat Lengkap untuk Cetak
CREATE OR ALTER FUNCTION dbo.fn_GetBeliAlatReport (
    @FilterType VARCHAR(50),
    @StartDate DATE,
    @EndDate DATE
)
RETURNS TABLE
AS
RETURN
(
    SELECT 
        ba.ID_Beli, 
        ba.Tanggal_Beli, 
        ba.Total_Bayar, 
        ba.Metode_Pembayaran,
        c.Nama_Customer
    FROM Beli_Alat ba
    LEFT JOIN Customer c ON ba.ID_Customer = c.ID_Customer
    WHERE ba.Status = 1
        AND (@FilterType = 'all' OR
             (@FilterType = 'today' AND CAST(ba.Tanggal_Beli AS DATE) = CAST(GETDATE() AS DATE)) OR
             (@FilterType = 'week' AND ba.Tanggal_Beli >= DATEADD(day, -7, CAST(GETDATE() AS DATE))) OR
             (@FilterType = 'month' AND MONTH(ba.Tanggal_Beli) = MONTH(GETDATE()) AND YEAR(ba.Tanggal_Beli) = YEAR(GETDATE())) OR
             (@FilterType = 'year' AND YEAR(ba.Tanggal_Beli) = YEAR(GETDATE())) OR
             (@FilterType = 'custom' AND ba.Tanggal_Beli BETWEEN @StartDate AND @EndDate))
);
GO

-- ============================================================
-- 6. AUDIT LOG TABLE & TRIGGER FOR OMZET
-- ============================================================

-- Tabel Log untuk perubahan data Omzet/Booking
IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='Log_Omzet' AND xtype='U')
BEGIN
    CREATE TABLE Log_Omzet (
        Log_ID INT IDENTITY(1,1) PRIMARY KEY,
        ID_Booking INT,
        Aksi VARCHAR(10),
        Tanggal_Aksi DATETIME DEFAULT GETDATE(),
        Status_Lama INT,
        Status_Baru INT,
        Nominal_Bayar DECIMAL(18,2),
        Nominal_Refund DECIMAL(18,2),
        Pengguna VARCHAR(100)
    );
END;
GO

-- Trigger untuk log perubahan Booking (insert/update)
CREATE OR ALTER TRIGGER trg_Booking_LogOmzet
ON Booking
AFTER INSERT, UPDATE
AS
BEGIN
    SET NOCOUNT ON;

    IF NOT EXISTS(SELECT * FROM deleted)
    BEGIN
        INSERT INTO Log_Omzet (ID_Booking, Aksi, Status_Lama, Status_Baru, Nominal_Bayar, Nominal_Refund, Pengguna)
        SELECT i.ID_Booking, 'INSERT', NULL, i.Status, i.Total_Bayar, 0, SYSTEM_USER
        FROM inserted i;
    END
    ELSE
    BEGIN
        INSERT INTO Log_Omzet (ID_Booking, Aksi, Status_Lama, Status_Baru, Nominal_Bayar, Nominal_Refund, Pengguna)
        SELECT i.ID_Booking, 'UPDATE', d.Status, i.Status, i.Total_Bayar, 
               ISNULL((SELECT Nominal_Refund FROM Pembatalan_Booking WHERE ID_Booking = i.ID_Booking), 0),
               SYSTEM_USER
        FROM inserted i
        INNER JOIN deleted d ON i.ID_Booking = d.ID_Booking;
    END
END;
GO