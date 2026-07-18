USE Hoopball;
GO

-- ============================================================
-- USER DEFINED FUNCTIONS (UDF) - KHUSUS UNTUK DASHBOARD / LAPORAN
-- ============================================================

-- 1. UDF Scalar: Menghitung Jumlah Lapangan Aktif (Status = 1 & Is_Deleted = 0)
CREATE OR ALTER FUNCTION dbo.fn_GetTotalLapanganAktif()
RETURNS INT
AS
BEGIN
    DECLARE @Total INT;
    SELECT @Total = COUNT(*) 
    FROM Lapangan 
    WHERE Status = 1 AND Is_Deleted = 0;
    RETURN ISNULL(@Total, 0);
END;
GO

-- 2. UDF Scalar: Menghitung Jumlah Member Aktif (Status = 1 pada tabel Langganan)
CREATE OR ALTER FUNCTION dbo.fn_GetTotalMemberAktif()
RETURNS INT
AS
BEGIN
    DECLARE @Total INT;
    SELECT @Total = COUNT(DISTINCT ID_Customer) 
    FROM Langganan 
    WHERE Status = 1;
    RETURN ISNULL(@Total, 0);
END;
GO

-- 3. UDF Scalar: Menghitung Total Booking Berhasil/Selesai (Status = 1 atau 2 pada tabel Booking)
CREATE OR ALTER FUNCTION dbo.fn_GetTotalBookingBerhasil()
RETURNS INT
AS
BEGIN
    DECLARE @Total INT;
    SELECT @Total = COUNT(*) 
    FROM Booking 
    WHERE Status IN (1, 2);
    RETURN ISNULL(@Total, 0);
END;
GO

-- 4. UDF Table-Valued: Mengambil Daftar Lapangan Aktif Teratas (Limit N)
CREATE OR ALTER FUNCTION dbo.fn_GetTopLapanganAktif(@Limit INT)
RETURNS TABLE
AS
RETURN (
    SELECT TOP (@Limit) ID_Lapangan, Nama_Lapangan, Harga_Sewa, Photo_Lapangan 
    FROM Lapangan 
    WHERE Status = 1 AND Is_Deleted = 0 
    ORDER BY ID_Lapangan ASC
);
GO

-- 5. UDF Table-Valued: Mengambil Daftar Tipe Member Aktif Teratas (Limit N)
CREATE OR ALTER FUNCTION dbo.fn_GetTopTipeMemberAktif(@Limit INT)
RETURNS TABLE
AS
RETURN (
    SELECT TOP (@Limit) ID_Tipe, Nama_Tipe, Harga_Member, Potongan_Harga 
    FROM Tipe_Member 
    WHERE Status = 1 AND Is_Deleted = 0 
    ORDER BY Harga_Member ASC
);
GO

-- 6. UDF Table-Valued: Mengambil Daftar Promo Aktif Teratas Berdasarkan Diskon Terbesar (Limit N)
CREATE OR ALTER FUNCTION dbo.fn_GetTopPromoAktif(@Limit INT)
RETURNS TABLE
AS
RETURN (
    SELECT TOP (@Limit) ID_Promo, Nama_Promo, Diskon, Tanggal_Mulai, Tanggal_Selesai 
    FROM Promo 
    WHERE Status = 1 AND Is_Deleted = 0 
    ORDER BY Diskon DESC
);
GO