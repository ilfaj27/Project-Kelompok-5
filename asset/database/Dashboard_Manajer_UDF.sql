USE Hoopball;
GO

-- 1. UDF UNTUK RINGKASAN STATISTIK PEMILIK (Inline TVF)
CREATE OR ALTER FUNCTION fn_Pemilik_GetDashboardSummary ()
RETURNS TABLE
AS
RETURN
(
    SELECT 
        (SELECT COUNT(*) FROM Karyawan WHERE Status = 1 AND Is_Deleted = 0) AS Total_Karyawan,
        (SELECT COUNT(*) FROM Karyawan WHERE Status = 0 AND Is_Deleted = 0) AS Total_Karyawan_Nonaktif,
        (SELECT COUNT(*) FROM Customer WHERE Is_Deleted = 0) AS Total_Customer,
        (SELECT COUNT(*) FROM Customer WHERE Status = 1 AND Is_Deleted = 0) AS Total_Customer_Aktif,
        (SELECT COUNT(*) FROM Alat WHERE Is_Deleted = 0) AS Total_Alat,
        (SELECT COUNT(*) FROM Alat WHERE Status = 1 AND Is_Deleted = 0) AS Total_Alat_Aktif,
        (SELECT COUNT(*) FROM Alat WHERE Stok < 10 AND Is_Deleted = 0) AS Stok_Rendah,
        (SELECT ISNULL(SUM(Total_Bayar), 0) FROM Booking WHERE Status IN (1, 2)) AS Total_Omzet,
        (SELECT COUNT(*) FROM Booking WHERE Status IN (1, 2)) AS Total_Booking_Sukses,
        (SELECT COUNT(*) FROM Booking WHERE Status = 3) AS Total_Booking_Batal,
        (SELECT COUNT(*) FROM Booking WHERE Status = 0) AS Total_Booking_Pending,
        (SELECT COUNT(*) FROM Langganan WHERE Status = 1) AS Total_Langganan,
        (SELECT COUNT(*) FROM Beli_Alat WHERE Status = 1) AS Total_Beli_Alat,
        (SELECT ISNULL(SUM(Total_Bayar), 0) FROM Beli_Alat WHERE Status = 1) AS Total_Pendapatan_Alat
);
GO

-- 2. UDF UNTUK DAFTAR KARYAWAN TERBARU
CREATE OR ALTER FUNCTION fn_Pemilik_GetRecentKaryawan ()
RETURNS TABLE
AS
RETURN
(
    SELECT TOP 5 ID_Karyawan, Nama_Karyawan, Jabatan, No_Telepon, Status 
    FROM Karyawan 
    WHERE Is_Deleted = 0 
    ORDER BY ID_Karyawan DESC
);
GO

-- 3. UDF UNTUK ALAT STOK RENDAH
CREATE OR ALTER FUNCTION fn_Pemilik_GetLowStockAlat ()
RETURNS TABLE
AS
RETURN
(
    SELECT TOP 5 ID_Alat, Nama_Alat, Stok, Harga_Jual AS Harga_Alat 
    FROM Alat 
    WHERE Stok < 10 AND Is_Deleted = 0 
    ORDER BY Stok ASC
);
GO

-- 4. UDF UNTUK GRAFIK OMZET BULANAN
CREATE OR ALTER FUNCTION fn_Pemilik_GetMonthlyOmzet ()
RETURNS TABLE
AS
RETURN
(
    SELECT 
        MONTH(Tanggal_Booking) as bulan, 
        YEAR(Tanggal_Booking) as tahun,
        ISNULL(SUM(Total_Bayar), 0) as total 
    FROM Booking 
    WHERE Status IN (1, 2) 
    GROUP BY MONTH(Tanggal_Booking), YEAR(Tanggal_Booking)
);
GO