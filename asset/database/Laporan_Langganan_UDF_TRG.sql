-- ============================================================
-- 1. STORED PROCEDURE (Master & Transaction Read)
-- ============================================================

-- Stored Procedure untuk mengambil Foto Profil Karyawan
CREATE OR ALTER PROCEDURE sp_GetKaryawanPhoto
    @ID_Karyawan INT
AS
BEGIN
    SET NOCOUNT ON;
    SELECT Photo_Profile 
    FROM Karyawan 
    WHERE ID_Karyawan = @ID_Karyawan AND Is_Deleted = 0;
END;
GO

-- Stored Procedure untuk mengambil Daftar Tipe Member yang Aktif
CREATE OR ALTER PROCEDURE sp_GetActiveTipeMember
AS
BEGIN
    SET NOCOUNT ON;
    SELECT ID_Tipe, Nama_Tipe, Harga_Member 
    FROM Tipe_Member 
    WHERE Is_Deleted = 0 
    ORDER BY ID_Tipe;
END;
GO


-- ============================================================
-- 2. USER DEFINED FUNCTION (UDF) - Laporan & Dashboard
-- ============================================================

-- UDF Utama untuk Data Mentah Laporan Langganan dengan Filter
CREATE OR ALTER FUNCTION dbo.fn_GetLanggananReport (
    @FilterType VARCHAR(50),
    @StartDate DATE,
    @EndDate DATE,
    @TipeFilter INT,
    @StatusFilter INT
)
RETURNS TABLE
AS
RETURN
(
    SELECT 
        lg.ID_Langganan,
        lg.Tanggal_Mulai,
        lg.Tanggal_Selesai,
        lg.Total_Bayar,
        lg.Metode_Pembayaran,
        -- Logika Dinamis: Jika status Aktif (1) tapi Tanggal_Selesai sudah lewat hari ini, anggap Berakhir (2)
        CASE 
            WHEN lg.Status = 1 AND CAST(lg.Tanggal_Selesai AS DATE) < CAST(GETDATE() AS DATE) THEN 2
            ELSE lg.Status
        END AS Status,
        c.Nama_Customer,
        c.Email,
        c.No_Telepon,
        k.Nama_Karyawan as Nama_Karyawan_Konfirm,
        tm.Nama_Tipe,
        tm.Harga_Member,
        tm.Potongan_Harga
    FROM Langganan lg
    LEFT JOIN Customer c ON lg.ID_Customer = c.ID_Customer
    LEFT JOIN Karyawan k ON lg.ID_Karyawan = k.ID_Karyawan
    LEFT JOIN Tipe_Member tm ON lg.ID_Tipe = tm.ID_Tipe
    WHERE 
        -- Memastikan filter status juga menggunakan status dinamis baru
        (@StatusFilter IS NULL OR 
            (CASE 
                WHEN lg.Status = 1 AND CAST(lg.Tanggal_Selesai AS DATE) < CAST(GETDATE() AS DATE) THEN 2
                ELSE lg.Status
             END) = @StatusFilter
        )
        AND (@TipeFilter IS NULL OR lg.ID_Tipe = @TipeFilter)
        AND (
            @FilterType = 'all' OR
            (@FilterType = 'today' AND CAST(lg.Tanggal_Mulai AS DATE) = CAST(GETDATE() AS DATE)) OR
            (@FilterType = 'week' AND lg.Tanggal_Mulai >= DATEADD(day, -7, CAST(GETDATE() AS DATE))) OR
            (@FilterType = 'month' AND MONTH(lg.Tanggal_Mulai) = MONTH(GETDATE()) AND YEAR(lg.Tanggal_Mulai) = YEAR(GETDATE())) OR
            (@FilterType = 'year' AND YEAR(lg.Tanggal_Mulai) = YEAR(GETDATE())) OR
            (@FilterType = 'custom' AND lg.Tanggal_Mulai BETWEEN @StartDate AND @EndDate)
        )
);
GO

-- UDF untuk Kebutuhan Card Dashboard/Statistik Ringkasan Laporan
CREATE OR ALTER FUNCTION dbo.fn_GetLanggananStats (
    @FilterType VARCHAR(50),
    @StartDate DATE,
    @EndDate DATE,
    @TipeFilter INT,
    @StatusFilter INT
)
RETURNS TABLE
AS
RETURN
(
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN Status = 1 THEN 1 ELSE 0 END) as aktif,
        SUM(CASE WHEN Status = 2 THEN 1 ELSE 0 END) as berakhir,
        SUM(CASE WHEN Status = 3 THEN 1 ELSE 0 END) as ditolak,
        ISNULL(SUM(Total_Bayar), 0) as pendapatan
    FROM dbo.fn_GetLanggananReport(@FilterType, @StartDate, @EndDate, @TipeFilter, @StatusFilter)
);
GO

-- UDF untuk Data Diagram Line Chart (Trend Langganan Bulanan)
CREATE OR ALTER FUNCTION dbo.fn_GetLanggananChartData (
    @FilterType VARCHAR(50),
    @StartDate DATE,
    @EndDate DATE,
    @TipeFilter INT,
    @StatusFilter INT
)
RETURNS TABLE
AS
RETURN
(
    SELECT 
        MONTH(Tanggal_Mulai) as bulan,
        YEAR(Tanggal_Mulai) as tahun,
        SUM(CASE WHEN Status = 1 THEN 1 ELSE 0 END) as aktif,
        SUM(CASE WHEN Status = 2 THEN 1 ELSE 0 END) as berakhir,
        SUM(CASE WHEN Status = 3 THEN 1 ELSE 0 END) as ditolak
    FROM dbo.fn_GetLanggananReport(@FilterType, @StartDate, @EndDate, @TipeFilter, @StatusFilter)
    GROUP BY MONTH(Tanggal_Mulai), YEAR(Tanggal_Mulai)
);
GO


-- ============================================================
-- 3. AUDIT LOG TABLE & TRIGGER HISTORY FOR TRANSACTIONS
-- ============================================================

-- Tabel untuk menampung rekaman perubahan data Langganan
IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='Log_Langganan' AND xtype='U')
BEGIN
    CREATE TABLE Log_Langganan (
        Log_ID INT IDENTITY(1,1) PRIMARY KEY,
        ID_Langganan INT,
        Aksi VARCHAR(10),
        Tanggal_Aksi DATETIME DEFAULT GETDATE(),
        Status_Lama INT,
        Status_Baru INT,
        Nominal_Bayar DECIMAL(18,2),
        Pengguna VARCHAR(100)
    );
END;
GO

-- Trigger untuk mendeteksi insert dan update status transaksi langganan
CREATE OR ALTER TRIGGER trg_Langganan_LogHistory
ON Langganan
AFTER INSERT, UPDATE
AS
BEGIN
    SET NOCOUNT ON;
    
    -- Kasus: Deteksi Insert Transaksi Baru
    IF NOT EXISTS(SELECT * FROM deleted)
    BEGIN
        INSERT INTO Log_Langganan (ID_Langganan, Aksi, Status_Lama, Status_Baru, Nominal_Bayar, Pengguna)
        SELECT ID_Langganan, 'INSERT', NULL, Status, Total_Bayar, SYSTEM_USER
        FROM inserted;
    END
    -- Kasus: Deteksi Update Transaksi (Perubahan Status Pembayaran/Berakhir)
    ELSE
    BEGIN
        INSERT INTO Log_Langganan (ID_Langganan, Aksi, Status_Lama, Status_Baru, Nominal_Bayar, Pengguna)
        SELECT i.ID_Langganan, 'UPDATE', d.Status, i.Status, i.Total_Bayar, SYSTEM_USER
        FROM inserted i
        INNER JOIN deleted d ON i.ID_Langganan = d.ID_Langganan;
    END
END;
GO