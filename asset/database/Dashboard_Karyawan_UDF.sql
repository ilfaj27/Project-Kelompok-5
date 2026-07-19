USE Hoopball;
GO

-- 1. SP UNTUK MENGAMBIL PROFIL KARYAWAN
CREATE PROCEDURE sp_Karyawan_GetProfile
    @ID_Karyawan INT
AS
BEGIN
    SET NOCOUNT ON;
    SELECT Photo_Profile, Nama_Karyawan, Jabatan, Status 
    FROM Karyawan 
    WHERE ID_Karyawan = @ID_Karyawan;
END;
GO

-- 2. UDF UNTUK RINGKASAN STATISTIK & TINDAKAN PENDING (Inline TVF)
CREATE FUNCTION fn_Karyawan_GetDashboardSummary ()
RETURNS TABLE
AS
RETURN
(
    SELECT 
        (SELECT COUNT(*) FROM Customer WHERE Is_Deleted = 0) AS Total_Customer,
        (SELECT COUNT(*) FROM Booking) AS Total_Booking,
        (SELECT COUNT(*) FROM Booking WHERE CAST(Tanggal_Booking AS DATE) = CAST(GETDATE() AS DATE)) AS Total_Booking_Today,
        (SELECT COUNT(*) FROM Langganan WHERE Status = 1 AND GETDATE() BETWEEN Tanggal_Mulai AND Tanggal_Selesai) AS Member_Aktif,
        (SELECT ISNULL(SUM(Total_Bayar), 0) FROM Booking WHERE Status IN (1, 2)) AS Omzet_Booking,
        (SELECT ISNULL(SUM(Total_Bayar), 0) FROM Beli_Alat WHERE Status = 1) AS Omzet_Alat,
        (SELECT ISNULL(SUM(Total_Bayar), 0) FROM Langganan WHERE Status IN (1, 2)) AS Omzet_Langganan,
        (SELECT COUNT(*) FROM Booking WHERE Status = 0) AS Pending_Booking,
        (SELECT COUNT(*) FROM Beli_Alat WHERE Status = 0) AS Pending_Beli,
        (SELECT COUNT(*) FROM Langganan WHERE Status = 0) AS Pending_Langganan,
        (SELECT COUNT(*) FROM Alat WHERE Is_Deleted = 0 AND Status = 1 AND Stok <= 5) AS Stok_Menipis
);
GO

-- 3. UDF UNTUK TREN PENDAPATAN 14 HARI (Inline TVF menggunakan CTE)
CREATE FUNCTION fn_Karyawan_GetRevenueTrend14Days ()
RETURNS TABLE
AS
RETURN
(
    WITH DateCTE AS (
        SELECT CAST(DATEADD(DAY, -13, GETDATE()) AS DATE) AS TrendDate
        UNION ALL
        SELECT DATEADD(DAY, 1, TrendDate)
        FROM DateCTE
        WHERE TrendDate < CAST(GETDATE() AS DATE)
    )
    SELECT 
        d.TrendDate,
        ISNULL((SELECT SUM(Total_Bayar) FROM Booking WHERE Status IN (1,2) AND CAST(Tanggal_Booking AS DATE) = d.TrendDate), 0) AS Omzet_Booking,
        ISNULL((SELECT SUM(Total_Bayar) FROM Beli_Alat WHERE Status = 1 AND CAST(Tanggal_Beli AS DATE) = d.TrendDate), 0) AS Omzet_Alat
    FROM DateCTE d
);
GO

-- 4. UDF UNTUK ALAT TERLARIS
CREATE OR ALTER FUNCTION fn_Alat_GetTopSelling ()
RETURNS TABLE
AS
RETURN
(
    SELECT TOP 5 A.Nama_Alat, 'Lainnya' AS Kategori, -- Menggunakan nilai statis agar tidak memicu error kolom Kategori
                  SUM(D.Jumlah) AS TotalTerjual, SUM(D.SubTotal) AS Pendapatan
    FROM Detail_Beli_Alat D
    INNER JOIN Beli_Alat B ON D.ID_Beli = B.ID_Beli AND B.Status = 1
    INNER JOIN Alat A ON A.ID_Alat = D.ID_Alat
    WHERE A.Is_Deleted = 0
    GROUP BY A.Nama_Alat
);
GO

-- 5. UDF UNTUK ALAT KURANG LAKU
CREATE OR ALTER FUNCTION fn_Alat_GetLowSelling ()
RETURNS TABLE
AS
RETURN
(
    SELECT TOP 5 A.Nama_Alat, 'Lainnya' AS Kategori, A.Stok, -- Menggunakan nilai statis agar tidak memicu error kolom Kategori
                  ISNULL(SUM(CASE WHEN B.Status = 1 THEN D.Jumlah END), 0) AS TotalTerjual
    FROM Alat A
    LEFT JOIN Detail_Beli_Alat D ON D.ID_Alat = A.ID_Alat
    LEFT JOIN Beli_Alat B ON B.ID_Beli = D.ID_Beli
    WHERE A.Is_Deleted = 0
    GROUP BY A.Nama_Alat, A.Stok
);
GO

-- 6. UDF UNTUK LAPANGAN POPULER
CREATE FUNCTION fn_Lapangan_GetPopular ()
RETURNS TABLE
AS
RETURN
(
    SELECT L.Nama_Lapangan, COUNT(*) AS TotalBooking, SUM(B.Total_Bayar) AS Pendapatan
    FROM Booking B
    INNER JOIN Jadwal J ON B.ID_Jadwal = J.ID_Jadwal
    INNER JOIN Lapangan L ON L.ID_Lapangan = J.ID_Lapangan
    WHERE B.Status IN (1, 2)
    GROUP BY L.Nama_Lapangan
);
GO

-- 7. UDF UNTUK JAM FAVORIT BOOKING
CREATE FUNCTION fn_Booking_GetFavoriteHours ()
RETURNS TABLE
AS
RETURN
(
    SELECT TOP 6 DATEPART(HOUR, J.Jam_Mulai) AS Jam, COUNT(*) AS Jumlah
    FROM Booking B
    INNER JOIN Jadwal J ON B.ID_Jadwal = J.ID_Jadwal
    WHERE B.Status IN (1, 2)
    GROUP BY DATEPART(HOUR, J.Jam_Mulai)
);
GO