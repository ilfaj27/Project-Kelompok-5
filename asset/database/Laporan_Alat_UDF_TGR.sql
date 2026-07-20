-- ============================================================
-- UDF LAPORAN PEMBELIAN ALAT - HOOPBALL
-- 3 UDF saja, tanpa trigger, tanpa view
-- ============================================================
-- Jalankan di SSMS: Pilih database Hoopball, lalu F5
-- ============================================================

USE Hoopball;
GO

-- ============================================================
-- UDF 1: fn_GetBeliAlatReport
-- Fungsi: Ambil data transaksi pembelian alat lengkap
-- Dipakai di: Tabel detail, Card statistik, Chart, Cetak PDF/Excel
-- Parameter: filter_type, start_date, end_date, alat_filter, status_filter
-- ============================================================
CREATE OR ALTER FUNCTION dbo.fn_GetBeliAlatReport (
    @FilterType VARCHAR(50),
    @StartDate DATE,
    @EndDate DATE,
    @AlatFilter INT,      -- NULL = semua alat
    @StatusFilter INT     -- NULL = semua status
)
RETURNS TABLE
AS
RETURN
(
    SELECT 
        ba.ID_Beli,
        ba.Tanggal_Beli,
        ba.Metode_Pembayaran,
        ba.Total_Bayar,
        ba.Status,
        c.Nama_Customer,
        c.Email,
        k.Nama_Karyawan as Nama_Karyawan_Konfirm
    FROM Beli_Alat ba
    LEFT JOIN Customer c ON ba.ID_Customer = c.ID_Customer
    LEFT JOIN Karyawan k ON ba.ID_Karyawan = k.ID_Karyawan
    WHERE 
        -- Filter Periode
        (@FilterType = 'all' OR
         (@FilterType = 'today' AND CAST(ba.Tanggal_Beli AS DATE) = CAST(GETDATE() AS DATE)) OR
         (@FilterType = 'week' AND ba.Tanggal_Beli >= DATEADD(day, -7, CAST(GETDATE() AS DATE))) OR
         (@FilterType = 'month' AND MONTH(ba.Tanggal_Beli) = MONTH(GETDATE()) AND YEAR(ba.Tanggal_Beli) = YEAR(GETDATE())) OR
         (@FilterType = 'year' AND YEAR(ba.Tanggal_Beli) = YEAR(GETDATE())) OR
         (@FilterType = 'custom' AND ba.Tanggal_Beli BETWEEN @StartDate AND @EndDate))
        -- Filter Alat (cek detail)
        AND (@AlatFilter IS NULL OR 
             EXISTS (SELECT 1 FROM Detail_Beli_Alat dba 
                     WHERE dba.ID_Beli = ba.ID_Beli AND dba.ID_Alat = @AlatFilter))
        -- Filter Status
        AND (@StatusFilter IS NULL OR ba.Status = @StatusFilter)
);
GO

-- ============================================================
-- UDF 2: fn_GetBeliAlatDetailItems
-- Fungsi: Ambil detail item per transaksi (nama alat, jumlah, subtotal, ukuran)
-- Dipakai di: Kolom "Item Dibeli" tabel, Cetak PDF/Excel
-- Parameter: Sama seperti UDF 1
-- ============================================================
CREATE OR ALTER FUNCTION dbo.fn_GetBeliAlatDetailItems (
    @FilterType VARCHAR(50),
    @StartDate DATE,
    @EndDate DATE,
    @AlatFilter INT,
    @StatusFilter INT
)
RETURNS TABLE
AS
RETURN
(
    SELECT 
        dba.ID_Beli,
        a.Nama_Alat,
        dba.Jumlah,
        dba.SubTotal,
        dba.Ukuran
    FROM Detail_Beli_Alat dba
    LEFT JOIN Alat a ON dba.ID_Alat = a.ID_Alat
    LEFT JOIN Beli_Alat ba ON dba.ID_Beli = ba.ID_Beli
    WHERE 
        (@FilterType = 'all' OR
         (@FilterType = 'today' AND CAST(ba.Tanggal_Beli AS DATE) = CAST(GETDATE() AS DATE)) OR
         (@FilterType = 'week' AND ba.Tanggal_Beli >= DATEADD(day, -7, CAST(GETDATE() AS DATE))) OR
         (@FilterType = 'month' AND MONTH(ba.Tanggal_Beli) = MONTH(GETDATE()) AND YEAR(ba.Tanggal_Beli) = YEAR(GETDATE())) OR
         (@FilterType = 'year' AND YEAR(ba.Tanggal_Beli) = YEAR(GETDATE())) OR
         (@FilterType = 'custom' AND ba.Tanggal_Beli BETWEEN @StartDate AND @EndDate))
        AND (@AlatFilter IS NULL OR dba.ID_Alat = @AlatFilter)
        AND (@StatusFilter IS NULL OR ba.Status = @StatusFilter)
);
GO

-- ============================================================
-- UDF 3: fn_GetBeliAlatPopular
-- Fungsi: Ranking alat terlaris (jumlah terjual, pendapatan, stok)
-- Dipakai di: Card "Alat Terlaris" sidebar dashboard
-- Parameter: Sama seperti UDF 1
-- ============================================================
CREATE OR ALTER FUNCTION dbo.fn_GetBeliAlatPopular (
    @FilterType VARCHAR(50),
    @StartDate DATE,
    @EndDate DATE,
    @AlatFilter INT,
    @StatusFilter INT
)
RETURNS TABLE
AS
RETURN
(
    SELECT 
        a.ID_Alat,
        a.Nama_Alat,
        a.Harga_Jual,
        SUM(dba.Jumlah) as jumlah_terjual,
        SUM(dba.SubTotal) as total_pendapatan,
        a.Stok as stok_tersedia
    FROM Detail_Beli_Alat dba
    LEFT JOIN Alat a ON dba.ID_Alat = a.ID_Alat
    LEFT JOIN Beli_Alat ba ON dba.ID_Beli = ba.ID_Beli
    WHERE 
        (@FilterType = 'all' OR
         (@FilterType = 'today' AND CAST(ba.Tanggal_Beli AS DATE) = CAST(GETDATE() AS DATE)) OR
         (@FilterType = 'week' AND ba.Tanggal_Beli >= DATEADD(day, -7, CAST(GETDATE() AS DATE))) OR
         (@FilterType = 'month' AND MONTH(ba.Tanggal_Beli) = MONTH(GETDATE()) AND YEAR(ba.Tanggal_Beli) = YEAR(GETDATE())) OR
         (@FilterType = 'year' AND YEAR(ba.Tanggal_Beli) = YEAR(GETDATE())) OR
         (@FilterType = 'custom' AND ba.Tanggal_Beli BETWEEN @StartDate AND @EndDate))
        AND (@AlatFilter IS NULL OR dba.ID_Alat = @AlatFilter)
        AND (@StatusFilter IS NULL OR ba.Status = @StatusFilter)
    GROUP BY a.ID_Alat, a.Nama_Alat, a.Harga_Jual, a.Stok
);
GO

-- ============================================================
-- VERIFIKASI: Cek UDF sudah terbuat
-- ============================================================
SELECT 
    name AS UDF_Name,
    create_date AS Created,
    modify_date AS LastModified
FROM sys.objects
WHERE type = 'IF' AND name LIKE 'fn_GetBeliAlat%'
ORDER BY name;
GO