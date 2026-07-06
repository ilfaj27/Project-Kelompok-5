-- ============================================================
-- FILE: Master_Promo_UDF_SP.sql
-- Deskripsi: Stored Procedures (SP) dan User Defined Functions (UDF)
--            untuk Master Promo (Update Terbaru)
-- ============================================================

USE Hoopball;
GO

-- ============================================================
-- DROP EXISTING OBJECTS (untuk re-run)
-- ============================================================
IF OBJECT_ID('dbo.usp_Promo_GetAll', 'P') IS NOT NULL DROP PROCEDURE dbo.usp_Promo_GetAll;
IF OBJECT_ID('dbo.usp_Promo_GetById', 'P') IS NOT NULL DROP PROCEDURE dbo.usp_Promo_GetById;
IF OBJECT_ID('dbo.usp_Promo_GetActive', 'P') IS NOT NULL DROP PROCEDURE dbo.usp_Promo_GetActive;
IF OBJECT_ID('dbo.usp_Promo_GetByStatus', 'P') IS NOT NULL DROP PROCEDURE dbo.usp_Promo_GetByStatus;
IF OBJECT_ID('dbo.usp_Promo_GetByDateRange', 'P') IS NOT NULL DROP PROCEDURE dbo.usp_Promo_GetByDateRange;
IF OBJECT_ID('dbo.usp_Promo_GetCurrentActive', 'P') IS NOT NULL DROP PROCEDURE dbo.usp_Promo_GetCurrentActive;
IF OBJECT_ID('dbo.usp_Promo_Insert', 'P') IS NOT NULL DROP PROCEDURE dbo.usp_Promo_Insert;
IF OBJECT_ID('dbo.usp_Promo_Update', 'P') IS NOT NULL DROP PROCEDURE dbo.usp_Promo_Update;
IF OBJECT_ID('dbo.usp_Promo_SoftDelete', 'P') IS NOT NULL DROP PROCEDURE dbo.usp_Promo_SoftDelete;
IF OBJECT_ID('dbo.usp_Promo_ToggleStatus', 'P') IS NOT NULL DROP PROCEDURE dbo.usp_Promo_ToggleStatus;
IF OBJECT_ID('dbo.usp_Promo_CheckDuplicate', 'P') IS NOT NULL DROP PROCEDURE dbo.usp_Promo_CheckDuplicate;
IF OBJECT_ID('dbo.usp_Promo_GetStats', 'P') IS NOT NULL DROP PROCEDURE dbo.usp_Promo_GetStats;
IF OBJECT_ID('dbo.usp_Promo_GetPaginated', 'P') IS NOT NULL DROP PROCEDURE dbo.usp_Promo_GetPaginated;
IF OBJECT_ID('dbo.usp_Promo_GetWithFilter', 'P') IS NOT NULL DROP PROCEDURE dbo.usp_Promo_GetWithFilter;
IF OBJECT_ID('dbo.usp_Promo_GetDetail', 'P') IS NOT NULL DROP PROCEDURE dbo.usp_Promo_GetDetail;
IF OBJECT_ID('dbo.usp_Promo_GetForDropdown', 'P') IS NOT NULL DROP PROCEDURE dbo.usp_Promo_GetForDropdown;
IF OBJECT_ID('dbo.usp_Promo_ValidateDate', 'P') IS NOT NULL DROP PROCEDURE dbo.usp_Promo_ValidateDate;
IF OBJECT_ID('dbo.usp_Promo_GetExpired', 'P') IS NOT NULL DROP PROCEDURE dbo.usp_Promo_GetExpired;
IF OBJECT_ID('dbo.usp_Promo_AutoDeactivateExpired', 'P') IS NOT NULL DROP PROCEDURE dbo.usp_Promo_AutoDeactivateExpired;

IF OBJECT_ID('dbo.udf_Promo_Rupiah', 'FN') IS NOT NULL DROP FUNCTION dbo.udf_Promo_Rupiah;
IF OBJECT_ID('dbo.udf_Promo_GetTotalActive', 'FN') IS NOT NULL DROP FUNCTION dbo.udf_Promo_GetTotalActive;
IF OBJECT_ID('dbo.udf_Promo_GetTotalInactive', 'FN') IS NOT NULL DROP FUNCTION dbo.udf_Promo_GetTotalInactive;
IF OBJECT_ID('dbo.udf_Promo_GetTotalAll', 'FN') IS NOT NULL DROP FUNCTION dbo.udf_Promo_GetTotalAll;
IF OBJECT_ID('dbo.udf_Promo_GetTotalExpired', 'FN') IS NOT NULL DROP FUNCTION dbo.udf_Promo_GetTotalExpired;
IF OBJECT_ID('dbo.udf_Promo_GetTotalUpcoming', 'FN') IS NOT NULL DROP FUNCTION dbo.udf_Promo_GetTotalUpcoming;
IF OBJECT_ID('dbo.udf_Promo_GetTotalRevenue', 'FN') IS NOT NULL DROP FUNCTION dbo.udf_Promo_GetTotalRevenue;
IF OBJECT_ID('dbo.udf_Promo_GetUsageCount', 'FN') IS NOT NULL DROP FUNCTION dbo.udf_Promo_GetUsageCount;
IF OBJECT_ID('dbo.udf_Promo_GetDiscountAmount', 'FN') IS NOT NULL DROP FUNCTION dbo.udf_Promo_GetDiscountAmount;
IF OBJECT_ID('dbo.udf_Promo_GetDaysRemaining', 'FN') IS NOT NULL DROP FUNCTION dbo.udf_Promo_GetDaysRemaining;
IF OBJECT_ID('dbo.udf_Promo_GetAverageDiscount', 'FN') IS NOT NULL DROP FUNCTION dbo.udf_Promo_GetAverageDiscount;
IF OBJECT_ID('dbo.udf_Promo_GetMaxDiscount', 'FN') IS NOT NULL DROP FUNCTION dbo.udf_Promo_GetMaxDiscount;
IF OBJECT_ID('dbo.udf_Promo_GetMinDiscount', 'FN') IS NOT NULL DROP FUNCTION dbo.udf_Promo_GetMinDiscount;
IF OBJECT_ID('dbo.udf_Promo_GetTotalSavings', 'FN') IS NOT NULL DROP FUNCTION dbo.udf_Promo_GetTotalSavings;
IF OBJECT_ID('dbo.udf_Promo_GetMonthlyRevenue', 'TFN') IS NOT NULL DROP FUNCTION dbo.udf_Promo_GetMonthlyRevenue;
IF OBJECT_ID('dbo.udf_Promo_GetDashboardStats', 'TFN') IS NOT NULL DROP FUNCTION dbo.udf_Promo_GetDashboardStats;
IF OBJECT_ID('dbo.udf_Promo_GetTopPromo', 'TFN') IS NOT NULL DROP FUNCTION dbo.udf_Promo_GetTopPromo;
IF OBJECT_ID('dbo.udf_Promo_GetRevenueReport', 'TFN') IS NOT NULL DROP FUNCTION dbo.udf_Promo_GetRevenueReport;
IF OBJECT_ID('dbo.udf_Promo_GetUsageReport', 'TFN') IS NOT NULL DROP FUNCTION dbo.udf_Promo_GetUsageReport;
IF OBJECT_ID('dbo.udf_Promo_GetExpiredReport', 'TFN') IS NOT NULL DROP FUNCTION dbo.udf_Promo_GetExpiredReport;
IF OBJECT_ID('dbo.udf_Promo_GetUpcomingReport', 'TFN') IS NOT NULL DROP FUNCTION dbo.udf_Promo_GetUpcomingReport;
GO

-- ============================================================
-- USER DEFINED FUNCTIONS (UDF) - Sumber Pengambilan Data
-- ============================================================

-- 1. UDF: Format Rupiah
CREATE FUNCTION dbo.udf_Promo_Rupiah(@amount DECIMAL(18,2))
RETURNS VARCHAR(50)
AS
BEGIN
    RETURN 'Rp ' + FORMAT(@amount, 'N0', 'id-ID');
END;
GO

-- 2. UDF: Hitung Total Promo Aktif
CREATE FUNCTION dbo.udf_Promo_GetTotalActive()
RETURNS INT
AS
BEGIN
    DECLARE @total INT;
    SELECT @total = COUNT(*) FROM Promo WHERE Is_Deleted = 0 AND Status = 1 
      AND Tanggal_Selesai >= CAST(GETDATE() AS DATE);
    RETURN ISNULL(@total, 0);
END;
GO

-- 3. UDF: Hitung Total Promo Nonaktif
CREATE FUNCTION dbo.udf_Promo_GetTotalInactive()
RETURNS INT
AS
BEGIN
    DECLARE @total INT;
    SELECT @total = COUNT(*) FROM Promo WHERE Is_Deleted = 0 AND Status = 0;
    RETURN ISNULL(@total, 0);
END;
GO

-- 4. UDF: Hitung Total Semua Promo (tidak dihapus)
CREATE FUNCTION dbo.udf_Promo_GetTotalAll()
RETURNS INT
AS
BEGIN
    DECLARE @total INT;
    SELECT @total = COUNT(*) FROM Promo WHERE Is_Deleted = 0;
    RETURN ISNULL(@total, 0);
END;
GO

-- 5. UDF: Hitung Total Promo yang Sudah Expired
CREATE FUNCTION dbo.udf_Promo_GetTotalExpired()
RETURNS INT
AS
BEGIN
    DECLARE @total INT;
    SELECT @total = COUNT(*) FROM Promo 
    WHERE Is_Deleted = 0 AND Tanggal_Selesai < CAST(GETDATE() AS DATE);
    RETURN ISNULL(@total, 0);
END;
GO

-- 6. UDF: Hitung Total Promo yang Akan Datang (Belum Mulai)
CREATE FUNCTION dbo.udf_Promo_GetTotalUpcoming()
RETURNS INT
AS
BEGIN
    DECLARE @total INT;
    SELECT @total = COUNT(*) FROM Promo 
    WHERE Is_Deleted = 0 AND Tanggal_Mulai > CAST(GETDATE() AS DATE);
    RETURN ISNULL(@total, 0);
END;
GO

-- 7. UDF: Hitung Total Revenue dari Booking yang menggunakan Promo
CREATE FUNCTION dbo.udf_Promo_GetTotalRevenue(@id_promo INT)
RETURNS DECIMAL(18,2)
AS
BEGIN
    DECLARE @revenue DECIMAL(18,2);
    SELECT @revenue = ISNULL(SUM(Total_Bayar), 0)
    FROM Booking 
    WHERE ID_Promo = @id_promo AND Status IN (0, 1, 2);
    RETURN @revenue;
END;
GO

-- 8. UDF: Hitung Jumlah Penggunaan Promo
CREATE FUNCTION dbo.udf_Promo_GetUsageCount(@id_promo INT)
RETURNS INT
AS
BEGIN
    DECLARE @count INT;
    SELECT @count = COUNT(*) FROM Booking WHERE ID_Promo = @id_promo AND Status IN (0, 1, 2);
    RETURN ISNULL(@count, 0);
END;
GO

-- 9. UDF: Hitung Total Diskon yang diberikan oleh Promo
CREATE FUNCTION dbo.udf_Promo_GetDiscountAmount(@id_promo INT)
RETURNS DECIMAL(18,2)
AS
BEGIN
    DECLARE @discount DECIMAL(18,2);
    SELECT @discount = ISNULL(SUM(p.Diskon), 0)
    FROM Booking b
    INNER JOIN Promo p ON b.ID_Promo = p.ID_Promo
    WHERE b.ID_Promo = @id_promo AND b.Status IN (0, 1, 2);
    RETURN @discount;
END;
GO

-- 10. UDF: Hitung Sisa Hari Promo
CREATE FUNCTION dbo.udf_Promo_GetDaysRemaining(@id_promo INT)
RETURNS INT
AS
BEGIN
    DECLARE @days INT;
    SELECT @days = DATEDIFF(DAY, CAST(GETDATE() AS DATE), Tanggal_Selesai)
    FROM Promo WHERE ID_Promo = @id_promo AND Is_Deleted = 0;
    RETURN ISNULL(@days, 0);
END;
GO

-- 11. UDF: Hitung Rata-rata Diskon
CREATE FUNCTION dbo.udf_Promo_GetAverageDiscount()
RETURNS DECIMAL(18,2)
AS
BEGIN
    DECLARE @avg DECIMAL(18,2);
    SELECT @avg = ISNULL(AVG(Diskon), 0)
    FROM Promo WHERE Is_Deleted = 0;
    RETURN @avg;
END;
GO

-- 12. UDF: Hitung Diskon Tertinggi
CREATE FUNCTION dbo.udf_Promo_GetMaxDiscount()
RETURNS DECIMAL(18,2)
AS
BEGIN
    DECLARE @max DECIMAL(18,2);
    SELECT @max = ISNULL(MAX(Diskon), 0)
    FROM Promo WHERE Is_Deleted = 0;
    RETURN @max;
END;
GO

-- 13. UDF: Hitung Diskon Terendah
CREATE FUNCTION dbo.udf_Promo_GetMinDiscount()
RETURNS DECIMAL(18,2)
AS
BEGIN
    DECLARE @min DECIMAL(18,2);
    SELECT @min = ISNULL(MIN(Diskon), 0)
    FROM Promo WHERE Is_Deleted = 0;
    RETURN @min;
END;
GO

-- 14. UDF: Hitung Total Penghematan (Total Diskon x Jumlah Penggunaan)
CREATE FUNCTION dbo.udf_Promo_GetTotalSavings(@id_promo INT)
RETURNS DECIMAL(18,2)
AS
BEGIN
    DECLARE @savings DECIMAL(18,2);
    SELECT @savings = ISNULL(SUM(p.Diskon), 0)
    FROM Booking b
    INNER JOIN Promo p ON b.ID_Promo = p.ID_Promo
    WHERE b.ID_Promo = @id_promo AND b.Status IN (0, 1, 2);
    RETURN @savings;
END;
GO

-- 15. UDF Table-Valued: Revenue Bulanan per Promo
CREATE FUNCTION dbo.udf_Promo_GetMonthlyRevenue(@year INT, @id_promo INT = NULL)
RETURNS TABLE
AS
RETURN (
    SELECT 
        MONTH(b.Created_Date) AS Bulan,
        DATENAME(MONTH, b.Created_Date) AS Nama_Bulan,
        p.Nama_Promo,
        p.Diskon,
        COUNT(*) AS Jumlah_Penggunaan,
        ISNULL(SUM(b.Total_Bayar), 0) AS Total_Revenue,
        dbo.udf_Promo_Rupiah(ISNULL(SUM(b.Total_Bayar), 0)) AS Revenue_Format,
        ISNULL(SUM(p.Diskon), 0) AS Total_Diskon_Diberikan,
        COUNT(DISTINCT b.ID_Customer) AS Jumlah_Customer
    FROM Booking b
    INNER JOIN Promo p ON b.ID_Promo = p.ID_Promo
    WHERE YEAR(b.Created_Date) = @year
      AND (@id_promo IS NULL OR b.ID_Promo = @id_promo)
      AND b.Status IN (0, 1, 2)
    GROUP BY MONTH(b.Created_Date), DATENAME(MONTH, b.Created_Date), p.Nama_Promo, p.Diskon
);
GO

-- 16. UDF Table-Valued: Dashboard Stats untuk Promo
CREATE FUNCTION dbo.udf_Promo_GetDashboardStats()
RETURNS TABLE
AS
RETURN (
    SELECT 
        (SELECT dbo.udf_Promo_GetTotalActive()) AS Total_Aktif,
        (SELECT dbo.udf_Promo_GetTotalInactive()) AS Total_Nonaktif,
        (SELECT dbo.udf_Promo_GetTotalAll()) AS Total_Semua,
        (SELECT dbo.udf_Promo_GetTotalExpired()) AS Total_Expired,
        (SELECT dbo.udf_Promo_GetTotalUpcoming()) AS Total_Akan_Datang,
        (SELECT dbo.udf_Promo_GetAverageDiscount()) AS Rata_Rata_Diskon,
        (SELECT dbo.udf_Promo_GetMaxDiscount()) AS Diskon_Tertinggi,
        (SELECT dbo.udf_Promo_GetMinDiscount()) AS Diskon_Terendah,
        (SELECT COUNT(*) FROM Booking WHERE ID_Promo IS NOT NULL AND Status IN (0,1,2)) AS Total_Penggunaan,
        (SELECT ISNULL(SUM(Diskon), 0) FROM Promo WHERE Is_Deleted = 0) AS Total_Diskon_Tersedia
);
GO

-- 17. UDF Table-Valued: Top Promo berdasarkan Penggunaan
CREATE FUNCTION dbo.udf_Promo_GetTopPromo(@top_n INT = 5)
RETURNS TABLE
AS
RETURN (
    SELECT TOP (@top_n)
        p.ID_Promo,
        p.Nama_Promo,
        p.Diskon,
        p.Tanggal_Mulai,
        p.Tanggal_Selesai,
        p.Status,
        CASE WHEN p.Status = 1 THEN 'Aktif' ELSE 'Nonaktif' END AS Status_Label,
        dbo.udf_Promo_GetUsageCount(p.ID_Promo) AS Jumlah_Penggunaan,
        dbo.udf_Promo_GetTotalRevenue(p.ID_Promo) AS Total_Revenue,
        dbo.udf_Promo_Rupiah(dbo.udf_Promo_GetTotalRevenue(p.ID_Promo)) AS Revenue_Format,
        dbo.udf_Promo_GetDiscountAmount(p.ID_Promo) AS Total_Diskon_Diberikan,
        dbo.udf_Promo_Rupiah(dbo.udf_Promo_GetDiscountAmount(p.ID_Promo)) AS Diskon_Format,
        dbo.udf_Promo_GetDaysRemaining(p.ID_Promo) AS Sisa_Hari,
        CASE 
            WHEN p.Tanggal_Selesai < CAST(GETDATE() AS DATE) THEN 'Expired'
            WHEN p.Tanggal_Mulai > CAST(GETDATE() AS DATE) THEN 'Akan Datang'
            ELSE 'Berjalan'
        END AS Kategori_Promo
    FROM Promo p
    WHERE p.Is_Deleted = 0
    ORDER BY Jumlah_Penggunaan DESC
);
GO

-- 18. UDF Table-Valued: Laporan Revenue Detail per Promo
CREATE FUNCTION dbo.udf_Promo_GetRevenueReport(@start_date DATE = NULL, @end_date DATE = NULL)
RETURNS TABLE
AS
RETURN (
    SELECT 
        p.ID_Promo,
        p.Nama_Promo,
        p.Diskon,
        p.Tanggal_Mulai,
        p.Tanggal_Selesai,
        p.Status,
        CASE WHEN p.Status = 1 THEN 'Aktif' ELSE 'Nonaktif' END AS Status_Label,
        dbo.udf_Promo_GetUsageCount(p.ID_Promo) AS Jumlah_Penggunaan,
        dbo.udf_Promo_GetTotalRevenue(p.ID_Promo) AS Total_Revenue,
        dbo.udf_Promo_Rupiah(dbo.udf_Promo_GetTotalRevenue(p.ID_Promo)) AS Revenue_Format,
        dbo.udf_Promo_GetDiscountAmount(p.ID_Promo) AS Total_Diskon,
        dbo.udf_Promo_Rupiah(dbo.udf_Promo_GetDiscountAmount(p.ID_Promo)) AS Diskon_Format,
        dbo.udf_Promo_GetDaysRemaining(p.ID_Promo) AS Sisa_Hari,
        CASE 
            WHEN p.Tanggal_Selesai < CAST(GETDATE() AS DATE) THEN 'Expired'
            WHEN p.Tanggal_Mulai > CAST(GETDATE() AS DATE) THEN 'Akan Datang'
            ELSE 'Berjalan'
        END AS Kategori_Promo,
        p.Created_Date,
        p.Created_By
    FROM Promo p
    WHERE p.Is_Deleted = 0
      AND (@start_date IS NULL OR p.Tanggal_Mulai >= @start_date)
      AND (@end_date IS NULL OR p.Tanggal_Selesai <= @end_date)
);
GO

-- 19. UDF Table-Valued: Laporan Penggunaan Promo
CREATE FUNCTION dbo.udf_Promo_GetUsageReport(@id_promo INT = NULL)
RETURNS TABLE
AS
RETURN (
    SELECT 
        b.ID_Booking,
        c.Nama_Customer,
        p.Nama_Promo,
        p.Diskon,
        b.Tanggal_Booking,
        b.Total_Bayar,
        dbo.udf_Promo_Rupiah(b.Total_Bayar) AS Total_Bayar_Format,
        b.Metode_Pembayaran,
        CASE b.Status 
            WHEN 0 THEN 'Menunggu Konfirmasi'
            WHEN 1 THEN 'Berhasil'
            WHEN 2 THEN 'Selesai'
            WHEN 3 THEN 'Dibatalkan'
        END AS Status_Booking
    FROM Booking b
    INNER JOIN Promo p ON b.ID_Promo = p.ID_Promo
    INNER JOIN Customer c ON b.ID_Customer = c.ID_Customer
    WHERE (@id_promo IS NULL OR b.ID_Promo = @id_promo)
      AND b.Status IN (0, 1, 2)
);
GO

-- 20. UDF Table-Valued: Laporan Promo Expired
CREATE FUNCTION dbo.udf_Promo_GetExpiredReport()
RETURNS TABLE
AS
RETURN (
    SELECT 
        p.ID_Promo,
        p.Nama_Promo,
        p.Diskon,
        p.Tanggal_Mulai,
        p.Tanggal_Selesai,
        p.Status,
        DATEDIFF(DAY, p.Tanggal_Selesai, CAST(GETDATE() AS DATE)) AS Hari_Sudah_Expired,
        dbo.udf_Promo_GetUsageCount(p.ID_Promo) AS Jumlah_Penggunaan,
        dbo.udf_Promo_GetTotalRevenue(p.ID_Promo) AS Total_Revenue,
        dbo.udf_Promo_Rupiah(dbo.udf_Promo_GetTotalRevenue(p.ID_Promo)) AS Revenue_Format
    FROM Promo p
    WHERE p.Is_Deleted = 0 AND p.Tanggal_Selesai < CAST(GETDATE() AS DATE)
);
GO

-- 21. UDF Table-Valued: Laporan Promo Akan Datang
CREATE FUNCTION dbo.udf_Promo_GetUpcomingReport()
RETURNS TABLE
AS
RETURN (
    SELECT 
        p.ID_Promo,
        p.Nama_Promo,
        p.Diskon,
        p.Tanggal_Mulai,
        p.Tanggal_Selesai,
        DATEDIFF(DAY, CAST(GETDATE() AS DATE), p.Tanggal_Mulai) AS Hari_Lagi_Mulai,
        p.Status,
        p.Created_By,
        p.Created_Date
    FROM Promo p
    WHERE p.Is_Deleted = 0 AND p.Tanggal_Mulai > CAST(GETDATE() AS DATE)
);
GO

-- ============================================================
-- STORED PROCEDURES (SP) - CRUD Operations
-- ============================================================

-- 1. SP: Get All Promo (Read)
CREATE PROCEDURE dbo.usp_Promo_GetAll
AS
BEGIN
    SET NOCOUNT ON;
    SELECT 
        ID_Promo,
        Nama_Promo,
        Diskon,
        Tanggal_Mulai,
        Tanggal_Selesai,
        Status,
        CASE WHEN Status = 1 THEN 'Aktif' ELSE 'Nonaktif' END AS Status_Label,
        Is_Deleted,
        Created_By,
        Created_Date,
        Modified_By,
        Modified_Date,
        dbo.udf_Promo_Rupiah(Diskon) AS Diskon_Format,
        dbo.udf_Promo_GetDaysRemaining(ID_Promo) AS Sisa_Hari,
        CASE 
            WHEN Tanggal_Selesai < CAST(GETDATE() AS DATE) THEN 'Expired'
            WHEN Tanggal_Mulai > CAST(GETDATE() AS DATE) THEN 'Akan Datang'
            ELSE 'Berjalan'
        END AS Kategori_Promo
    FROM Promo
    WHERE Is_Deleted = 0
    ORDER BY Tanggal_Mulai DESC;
END;
GO

-- 2. SP: Get Promo by ID (Read Detail)
CREATE PROCEDURE dbo.usp_Promo_GetById
    @id_promo INT
AS
BEGIN
    SET NOCOUNT ON;
    SELECT 
        p.ID_Promo,
        p.Nama_Promo,
        p.Diskon,
        p.Tanggal_Mulai,
        p.Tanggal_Selesai,
        p.Status,
        CASE WHEN p.Status = 1 THEN 'Aktif' ELSE 'Nonaktif' END AS Status_Label,
        p.Is_Deleted,
        p.Created_By,
        p.Created_Date,
        p.Modified_By,
        p.Modified_Date,
        dbo.udf_Promo_Rupiah(p.Diskon) AS Diskon_Format,
        dbo.udf_Promo_GetDaysRemaining(p.ID_Promo) AS Sisa_Hari,
        dbo.udf_Promo_GetUsageCount(p.ID_Promo) AS Jumlah_Penggunaan,
        dbo.udf_Promo_GetTotalRevenue(p.ID_Promo) AS Total_Revenue,
        dbo.udf_Promo_Rupiah(dbo.udf_Promo_GetTotalRevenue(p.ID_Promo)) AS Revenue_Format,
        dbo.udf_Promo_GetDiscountAmount(p.ID_Promo) AS Total_Diskon_Diberikan,
        dbo.udf_Promo_Rupiah(dbo.udf_Promo_GetDiscountAmount(p.ID_Promo)) AS Diskon_Format,
        CASE 
            WHEN p.Tanggal_Selesai < CAST(GETDATE() AS DATE) THEN 'Expired'
            WHEN p.Tanggal_Mulai > CAST(GETDATE() AS DATE) THEN 'Akan Datang'
            ELSE 'Berjalan'
        END AS Kategori_Promo
    FROM Promo p
    WHERE p.ID_Promo = @id_promo AND p.Is_Deleted = 0;
END;
GO

-- 3. SP: Get Active Promo (Read Filtered - hanya yang masih berlaku)
CREATE PROCEDURE dbo.usp_Promo_GetActive
AS
BEGIN
    SET NOCOUNT ON;
    SELECT 
        ID_Promo,
        Nama_Promo,
        Diskon,
        Tanggal_Mulai,
        Tanggal_Selesai,
        Status,
        dbo.udf_Promo_Rupiah(Diskon) AS Diskon_Format,
        dbo.udf_Promo_GetDaysRemaining(ID_Promo) AS Sisa_Hari
    FROM Promo
    WHERE Is_Deleted = 0 AND Status = 1
      AND Tanggal_Mulai <= CAST(GETDATE() AS DATE)
      AND Tanggal_Selesai >= CAST(GETDATE() AS DATE)
    ORDER BY Diskon DESC;
END;
GO

-- 4. SP: Get Promo by Status (Read Filtered)
CREATE PROCEDURE dbo.usp_Promo_GetByStatus
    @status INT
AS
BEGIN
    SET NOCOUNT ON;
    SELECT 
        ID_Promo,
        Nama_Promo,
        Diskon,
        Tanggal_Mulai,
        Tanggal_Selesai,
        Status,
        CASE WHEN Status = 1 THEN 'Aktif' ELSE 'Nonaktif' END AS Status_Label,
        dbo.udf_Promo_Rupiah(Diskon) AS Diskon_Format,
        dbo.udf_Promo_GetDaysRemaining(ID_Promo) AS Sisa_Hari
    FROM Promo
    WHERE Is_Deleted = 0 AND Status = @status
    ORDER BY Tanggal_Mulai DESC;
END;
GO

-- 5. SP: Get Promo by Date Range
CREATE PROCEDURE dbo.usp_Promo_GetByDateRange
    @start_date DATE,
    @end_date DATE
AS
BEGIN
    SET NOCOUNT ON;
    SELECT 
        ID_Promo,
        Nama_Promo,
        Diskon,
        Tanggal_Mulai,
        Tanggal_Selesai,
        Status,
        dbo.udf_Promo_Rupiah(Diskon) AS Diskon_Format,
        dbo.udf_Promo_GetDaysRemaining(ID_Promo) AS Sisa_Hari
    FROM Promo
    WHERE Is_Deleted = 0
      AND ((Tanggal_Mulai BETWEEN @start_date AND @end_date)
           OR (Tanggal_Selesai BETWEEN @start_date AND @end_date)
           OR (Tanggal_Mulai <= @start_date AND Tanggal_Selesai >= @end_date))
    ORDER BY Tanggal_Mulai DESC;
END;
GO

-- 6. SP: Get Current Active Promo (yang sedang berjalan saat ini)
CREATE PROCEDURE dbo.usp_Promo_GetCurrentActive
AS
BEGIN
    SET NOCOUNT ON;
    SELECT 
        ID_Promo,
        Nama_Promo,
        Diskon,
        Tanggal_Mulai,
        Tanggal_Selesai,
        Status,
        dbo.udf_Promo_Rupiah(Diskon) AS Diskon_Format,
        dbo.udf_Promo_GetDaysRemaining(ID_Promo) AS Sisa_Hari,
        DATEDIFF(DAY, Tanggal_Mulai, Tanggal_Selesai) + 1 AS Durasi_Hari
    FROM Promo
    WHERE Is_Deleted = 0 AND Status = 1
      AND Tanggal_Mulai <= CAST(GETDATE() AS DATE)
      AND Tanggal_Selesai >= CAST(GETDATE() AS DATE)
    ORDER BY Diskon DESC;
END;
GO

-- 7. SP: Insert Promo (Create)
CREATE PROCEDURE dbo.usp_Promo_Insert
    @nama_promo VARCHAR(50),
    @diskon DECIMAL(18,2),
    @tanggal_mulai DATE,
    @tanggal_selesai DATE,
    @created_by VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;

    -- Validasi nama tidak boleh kosong
    IF LEN(TRIM(@nama_promo)) < 3
    BEGIN
        RAISERROR('Nama promo minimal 3 karakter.', 16, 1);
        RETURN;
    END

    -- Validasi diskon tidak boleh negatif
    IF @diskon < 0
    BEGIN
        RAISERROR('Diskon tidak boleh kurang dari 0.', 16, 1);
        RETURN;
    END

    -- Validasi tanggal mulai tidak boleh lebih besar dari tanggal selesai
    IF @tanggal_mulai > @tanggal_selesai
    BEGIN
        RAISERROR('Tanggal mulai tidak boleh lebih besar dari tanggal selesai.', 16, 1);
        RETURN;
    END

    -- Validasi tanggal mulai tidak boleh di masa lalu (opsional - bisa dihapus jika perlu)
    -- IF @tanggal_mulai < CAST(GETDATE() AS DATE)
    -- BEGIN
    --     RAISERROR('Tanggal mulai tidak boleh di masa lalu.', 16, 1);
    --     RETURN;
    -- END

    -- Cek duplikat nama promo
    IF EXISTS (SELECT 1 FROM Promo WHERE Nama_Promo = @nama_promo AND Is_Deleted = 0)
    BEGIN
        RAISERROR('Nama promo sudah terdaftar.', 16, 1);
        RETURN;
    END

    INSERT INTO Promo (Nama_Promo, Diskon, Tanggal_Mulai, Tanggal_Selesai, Status, Is_Deleted, Created_By, Created_Date)
    VALUES (@nama_promo, @diskon, @tanggal_mulai, @tanggal_selesai, 1, 0, @created_by, GETDATE());

    SELECT SCOPE_IDENTITY() AS ID_Promo_Baru;
END;
GO

-- 8. SP: Update Promo (Update)
CREATE PROCEDURE dbo.usp_Promo_Update
    @id_promo INT,
    @nama_promo VARCHAR(50),
    @diskon DECIMAL(18,2),
    @tanggal_mulai DATE,
    @tanggal_selesai DATE,
    @modified_by VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;

    -- Validasi ID exists
    IF NOT EXISTS (SELECT 1 FROM Promo WHERE ID_Promo = @id_promo AND Is_Deleted = 0)
    BEGIN
        RAISERROR('Promo tidak ditemukan.', 16, 1);
        RETURN;
    END

    -- Validasi nama
    IF LEN(TRIM(@nama_promo)) < 3
    BEGIN
        RAISERROR('Nama promo minimal 3 karakter.', 16, 1);
        RETURN;
    END

    -- Validasi diskon
    IF @diskon < 0
    BEGIN
        RAISERROR('Diskon tidak boleh kurang dari 0.', 16, 1);
        RETURN;
    END

    -- Validasi tanggal
    IF @tanggal_mulai > @tanggal_selesai
    BEGIN
        RAISERROR('Tanggal mulai tidak boleh lebih besar dari tanggal selesai.', 16, 1);
        RETURN;
    END

    -- Cek duplikat nama (exclude current ID)
    IF EXISTS (SELECT 1 FROM Promo WHERE Nama_Promo = @nama_promo AND ID_Promo <> @id_promo AND Is_Deleted = 0)
    BEGIN
        RAISERROR('Nama promo sudah terdaftar.', 16, 1);
        RETURN;
    END

    UPDATE Promo
    SET Nama_Promo = @nama_promo,
        Diskon = @diskon,
        Tanggal_Mulai = @tanggal_mulai,
        Tanggal_Selesai = @tanggal_selesai,
        Modified_By = @modified_by,
        Modified_Date = GETDATE()
    WHERE ID_Promo = @id_promo;

    SELECT @@ROWCOUNT AS Rows_Affected;
END;
GO

-- 9. SP: Soft Delete Promo (Delete)
CREATE PROCEDURE dbo.usp_Promo_SoftDelete
    @id_promo INT,
    @deleted_by VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;

    -- Cek apakah promo sedang digunakan di Booking
    IF EXISTS (SELECT 1 FROM Booking WHERE ID_Promo = @id_promo AND Status IN (0, 1))
    BEGIN
        RAISERROR('Promo tidak dapat dihapus karena sedang digunakan dalam booking aktif.', 16, 1);
        RETURN;
    END

    UPDATE Promo
    SET Is_Deleted = 1,
        Deleted_By = @deleted_by,
        Deleted_Date = GETDATE()
    WHERE ID_Promo = @id_promo AND Is_Deleted = 0;

    SELECT @@ROWCOUNT AS Rows_Affected;
END;
GO

-- 10. SP: Toggle Status Promo (Update Status)
CREATE PROCEDURE dbo.usp_Promo_ToggleStatus
    @id_promo INT
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE @current_status INT;
    SELECT @current_status = Status FROM Promo WHERE ID_Promo = @id_promo AND Is_Deleted = 0;

    IF @current_status IS NULL
    BEGIN
        RAISERROR('Promo tidak ditemukan.', 16, 1);
        RETURN;
    END

    UPDATE Promo
    SET Status = CASE WHEN @current_status = 1 THEN 0 ELSE 1 END
    WHERE ID_Promo = @id_promo;

    SELECT CASE WHEN @current_status = 1 THEN 0 ELSE 1 END AS New_Status;
END;
GO

-- 11. SP: Check Duplicate Nama Promo
CREATE PROCEDURE dbo.usp_Promo_CheckDuplicate
    @nama_promo VARCHAR(50),
    @exclude_id INT = NULL
AS
BEGIN
    SET NOCOUNT ON;
    SELECT COUNT(*) AS Is_Duplicate
    FROM Promo
    WHERE Nama_Promo = @nama_promo 
      AND Is_Deleted = 0
      AND (@exclude_id IS NULL OR ID_Promo <> @exclude_id);
END;
GO

-- 12. SP: Validate Date Range
CREATE PROCEDURE dbo.usp_Promo_ValidateDate
    @tanggal_mulai DATE,
    @tanggal_selesai DATE
AS
BEGIN
    SET NOCOUNT ON;
    SELECT 
        CASE 
            WHEN @tanggal_mulai > @tanggal_selesai THEN 0
            WHEN DATEDIFF(DAY, @tanggal_mulai, @tanggal_selesai) < 0 THEN 0
            ELSE 1
        END AS Is_Valid,
        DATEDIFF(DAY, @tanggal_mulai, @tanggal_selesai) + 1 AS Durasi_Hari;
END;
GO

-- 13. SP: Get Expired Promo
CREATE PROCEDURE dbo.usp_Promo_GetExpired
AS
BEGIN
    SET NOCOUNT ON;
    SELECT 
        ID_Promo,
        Nama_Promo,
        Diskon,
        Tanggal_Mulai,
        Tanggal_Selesai,
        Status,
        dbo.udf_Promo_Rupiah(Diskon) AS Diskon_Format,
        DATEDIFF(DAY, Tanggal_Selesai, CAST(GETDATE() AS DATE)) AS Hari_Sudah_Expired,
        dbo.udf_Promo_GetUsageCount(ID_Promo) AS Jumlah_Penggunaan
    FROM Promo
    WHERE Is_Deleted = 0 AND Tanggal_Selesai < CAST(GETDATE() AS DATE)
    ORDER BY Tanggal_Selesai DESC;
END;
GO

-- 14. SP: Auto Deactivate Expired Promo
CREATE PROCEDURE dbo.usp_Promo_AutoDeactivateExpired
AS
BEGIN
    SET NOCOUNT ON;

    UPDATE Promo
    SET Status = 0,
        Modified_Date = GETDATE(),
        Modified_By = 'SYSTEM_AUTO'
    WHERE Is_Deleted = 0 
      AND Status = 1 
      AND Tanggal_Selesai < CAST(GETDATE() AS DATE);

    SELECT @@ROWCOUNT AS Promo_Deactivated;
END;
GO

-- 15. SP: Get Statistics
CREATE PROCEDURE dbo.usp_Promo_GetStats
AS
BEGIN
    SET NOCOUNT ON;
    SELECT 
        dbo.udf_Promo_GetTotalActive() AS Total_Aktif,
        dbo.udf_Promo_GetTotalInactive() AS Total_Nonaktif,
        dbo.udf_Promo_GetTotalAll() AS Total_Semua,
        dbo.udf_Promo_GetTotalExpired() AS Total_Expired,
        dbo.udf_Promo_GetTotalUpcoming() AS Total_Akan_Datang,
        dbo.udf_Promo_GetAverageDiscount() AS Rata_Rata_Diskon,
        dbo.udf_Promo_GetMaxDiscount() AS Diskon_Tertinggi,
        dbo.udf_Promo_GetMinDiscount() AS Diskon_Terendah;
END;
GO

-- 16. SP: Get Paginated Data
CREATE PROCEDURE dbo.usp_Promo_GetPaginated
    @page INT = 1,
    @limit INT = 10,
    @sort_by VARCHAR(50) = 'Tanggal_Mulai DESC'
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE @offset INT = (@page - 1) * @limit;
    DECLARE @sql NVARCHAR(MAX);

    SET @sql = N'
    SELECT 
        ID_Promo,
        Nama_Promo,
        Diskon,
        Tanggal_Mulai,
        Tanggal_Selesai,
        Status,
        CASE WHEN Status = 1 THEN ''Aktif'' ELSE ''Nonaktif'' END AS Status_Label,
        dbo.udf_Promo_Rupiah(Diskon) AS Diskon_Format,
        dbo.udf_Promo_GetDaysRemaining(ID_Promo) AS Sisa_Hari,
        CASE 
            WHEN Tanggal_Selesai < CAST(GETDATE() AS DATE) THEN ''Expired''
            WHEN Tanggal_Mulai > CAST(GETDATE() AS DATE) THEN ''Akan Datang''
            ELSE ''Berjalan''
        END AS Kategori_Promo
    FROM Promo
    WHERE Is_Deleted = 0
    ORDER BY ' + @sort_by + '
    OFFSET ' + CAST(@offset AS VARCHAR) + ' ROWS
    FETCH NEXT ' + CAST(@limit AS VARCHAR) + ' ROWS ONLY';

    EXEC sp_executesql @sql;
END;
GO

-- 17. SP: Get With Filter
CREATE PROCEDURE dbo.usp_Promo_GetWithFilter
    @status INT = NULL,
    @kategori VARCHAR(20) = NULL,
    @sort_by VARCHAR(50) = 'Tanggal_Mulai DESC',
    @page INT = 1,
    @limit INT = 10
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE @offset INT = (@page - 1) * @limit;

    SELECT 
        ID_Promo,
        Nama_Promo,
        Diskon,
        Tanggal_Mulai,
        Tanggal_Selesai,
        Status,
        CASE WHEN Status = 1 THEN 'Aktif' ELSE 'Nonaktif' END AS Status_Label,
        dbo.udf_Promo_Rupiah(Diskon) AS Diskon_Format,
        dbo.udf_Promo_GetDaysRemaining(ID_Promo) AS Sisa_Hari,
        CASE 
            WHEN Tanggal_Selesai < CAST(GETDATE() AS DATE) THEN 'Expired'
            WHEN Tanggal_Mulai > CAST(GETDATE() AS DATE) THEN 'Akan Datang'
            ELSE 'Berjalan'
        END AS Kategori_Promo
    FROM Promo
    WHERE Is_Deleted = 0
      AND (@status IS NULL OR Status = @status)
      AND (@kategori IS NULL OR 
           (@kategori = 'Expired' AND Tanggal_Selesai < CAST(GETDATE() AS DATE)) OR
           (@kategori = 'Akan Datang' AND Tanggal_Mulai > CAST(GETDATE() AS DATE)) OR
           (@kategori = 'Berjalan' AND Tanggal_Mulai <= CAST(GETDATE() AS DATE) AND Tanggal_Selesai >= CAST(GETDATE() AS DATE)))
    ORDER BY 
        CASE WHEN @sort_by = 'Nama_Promo ASC' THEN Nama_Promo END ASC,
        CASE WHEN @sort_by = 'Nama_Promo DESC' THEN Nama_Promo END DESC,
        CASE WHEN @sort_by = 'Diskon DESC' THEN Diskon END DESC,
        CASE WHEN @sort_by = 'Diskon ASC' THEN Diskon END ASC,
        CASE WHEN @sort_by = 'Tanggal_Mulai DESC' THEN Tanggal_Mulai END DESC,
        CASE WHEN @sort_by = 'Tanggal_Mulai ASC' THEN Tanggal_Mulai END ASC
    OFFSET @offset ROWS FETCH NEXT @limit ROWS ONLY;
END;
GO

-- 18. SP: Get Detail dengan semua informasi
CREATE PROCEDURE dbo.usp_Promo_GetDetail
    @id_promo INT
AS
BEGIN
    SET NOCOUNT ON;

    -- Info Promo
    SELECT 
        p.ID_Promo,
        p.Nama_Promo,
        p.Diskon,
        p.Tanggal_Mulai,
        p.Tanggal_Selesai,
        p.Status,
        CASE WHEN p.Status = 1 THEN 'Aktif' ELSE 'Nonaktif' END AS Status_Label,
        p.Created_By,
        p.Created_Date,
        p.Modified_By,
        p.Modified_Date,
        dbo.udf_Promo_Rupiah(p.Diskon) AS Diskon_Format,
        dbo.udf_Promo_GetDaysRemaining(p.ID_Promo) AS Sisa_Hari,
        dbo.udf_Promo_GetUsageCount(p.ID_Promo) AS Jumlah_Penggunaan,
        dbo.udf_Promo_GetTotalRevenue(p.ID_Promo) AS Total_Revenue,
        dbo.udf_Promo_Rupiah(dbo.udf_Promo_GetTotalRevenue(p.ID_Promo)) AS Revenue_Format,
        dbo.udf_Promo_GetDiscountAmount(p.ID_Promo) AS Total_Diskon_Diberikan,
        dbo.udf_Promo_Rupiah(dbo.udf_Promo_GetDiscountAmount(p.ID_Promo)) AS Diskon_Format,
        CASE 
            WHEN p.Tanggal_Selesai < CAST(GETDATE() AS DATE) THEN 'Expired'
            WHEN p.Tanggal_Mulai > CAST(GETDATE() AS DATE) THEN 'Akan Datang'
            ELSE 'Berjalan'
        END AS Kategori_Promo
    FROM Promo p
    WHERE p.ID_Promo = @id_promo AND p.Is_Deleted = 0;

    -- Riwayat Penggunaan Promo
    SELECT 
        b.ID_Booking,
        c.Nama_Customer,
        b.Tanggal_Booking,
        b.Total_Bayar,
        dbo.udf_Promo_Rupiah(b.Total_Bayar) AS Total_Bayar_Format,
        b.Metode_Pembayaran,
        CASE b.Status 
            WHEN 0 THEN 'Menunggu Konfirmasi'
            WHEN 1 THEN 'Berhasil'
            WHEN 2 THEN 'Selesai'
            WHEN 3 THEN 'Dibatalkan'
        END AS Status_Booking
    FROM Booking b
    INNER JOIN Customer c ON b.ID_Customer = c.ID_Customer
    WHERE b.ID_Promo = @id_promo AND b.Status IN (0, 1, 2)
    ORDER BY b.Created_Date DESC;
END;
GO

-- 19. SP: Get For Dropdown (hanya aktif dan masih berlaku)
CREATE PROCEDURE dbo.usp_Promo_GetForDropdown
AS
BEGIN
    SET NOCOUNT ON;
    SELECT 
        ID_Promo,
        Nama_Promo,
        Diskon,
        dbo.udf_Promo_Rupiah(Diskon) AS Diskon_Format,
        Tanggal_Selesai,
        dbo.udf_Promo_GetDaysRemaining(ID_Promo) AS Sisa_Hari
    FROM Promo
    WHERE Is_Deleted = 0 AND Status = 1
      AND Tanggal_Mulai <= CAST(GETDATE() AS DATE)
      AND Tanggal_Selesai >= CAST(GETDATE() AS DATE)
    ORDER BY Diskon DESC;
END;
GO

-- ============================================================
-- CONTOH PENGGUNAAN
-- ============================================================
/*
-- READ ALL
EXEC dbo.usp_Promo_GetAll;

-- READ BY ID
EXEC dbo.usp_Promo_GetById @id_promo = 1;

-- READ ACTIVE
EXEC dbo.usp_Promo_GetActive;

-- READ BY STATUS
EXEC dbo.usp_Promo_GetByStatus @status = 1;

-- READ BY DATE RANGE
EXEC dbo.usp_Promo_GetByDateRange @start_date = '2024-01-01', @end_date = '2024-12-31';

-- READ CURRENT ACTIVE
EXEC dbo.usp_Promo_GetCurrentActive;

-- CREATE
EXEC dbo.usp_Promo_Insert 
    @nama_promo = 'Promo Spesial Lebaran',
    @diskon = 25000.00,
    @tanggal_mulai = '2024-04-01',
    @tanggal_selesai = '2024-04-15',
    @created_by = 'ADMIN';

-- UPDATE
EXEC dbo.usp_Promo_Update 
    @id_promo = 1,
    @nama_promo = 'Promo Hari Raya Updated',
    @diskon = 20000.00,
    @tanggal_mulai = '2024-03-20',
    @tanggal_selesai = '2024-04-10',
    @modified_by = 'ADMIN';

-- SOFT DELETE
EXEC dbo.usp_Promo_SoftDelete 
    @id_promo = 1,
    @deleted_by = 'ADMIN';

-- TOGGLE STATUS
EXEC dbo.usp_Promo_ToggleStatus @id_promo = 1;

-- CHECK DUPLICATE
EXEC dbo.usp_Promo_CheckDuplicate @nama_promo = 'Promo Weekend', @exclude_id = NULL;

-- VALIDATE DATE
EXEC dbo.usp_Promo_ValidateDate @tanggal_mulai = '2024-04-01', @tanggal_selesai = '2024-04-15';

-- GET EXPIRED
EXEC dbo.usp_Promo_GetExpired;

-- AUTO DEACTIVATE EXPIRED
EXEC dbo.usp_Promo_AutoDeactivateExpired;

-- GET STATS
EXEC dbo.usp_Promo_GetStats;

-- GET PAGINATED
EXEC dbo.usp_Promo_GetPaginated @page = 1, @limit = 5, @sort_by = 'Tanggal_Mulai DESC';

-- GET WITH FILTER
EXEC dbo.usp_Promo_GetWithFilter @status = 1, @kategori = 'Berjalan', @sort_by = 'Diskon DESC', @page = 1, @limit = 10;

-- GET DETAIL
EXEC dbo.usp_Promo_GetDetail @id_promo = 1;

-- GET DROPDOWN
EXEC dbo.usp_Promo_GetForDropdown;

-- UDF EXAMPLES
SELECT dbo.udf_Promo_Rupiah(15000);
SELECT dbo.udf_Promo_GetTotalActive();
SELECT dbo.udf_Promo_GetTotalInactive();
SELECT dbo.udf_Promo_GetTotalAll();
SELECT dbo.udf_Promo_GetTotalExpired();
SELECT dbo.udf_Promo_GetTotalUpcoming();
SELECT dbo.udf_Promo_GetTotalRevenue(1);
SELECT dbo.udf_Promo_GetUsageCount(1);
SELECT dbo.udf_Promo_GetDiscountAmount(1);
SELECT dbo.udf_Promo_GetDaysRemaining(1);
SELECT dbo.udf_Promo_GetAverageDiscount();
SELECT dbo.udf_Promo_GetMaxDiscount();
SELECT dbo.udf_Promo_GetMinDiscount();
SELECT dbo.udf_Promo_GetTotalSavings(1);

-- TABLE-VALUED UDF
SELECT * FROM dbo.udf_Promo_GetMonthlyRevenue(2024, NULL);
SELECT * FROM dbo.udf_Promo_GetDashboardStats();
SELECT * FROM dbo.udf_Promo_GetTopPromo(5);
SELECT * FROM dbo.udf_Promo_GetRevenueReport(NULL, NULL);
SELECT * FROM dbo.udf_Promo_GetUsageReport(1);
SELECT * FROM dbo.udf_Promo_GetExpiredReport();
SELECT * FROM dbo.udf_Promo_GetUpcomingReport();
*/
GO
