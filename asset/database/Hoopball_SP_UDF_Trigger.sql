
/*
============================================================
 TAMBAHAN SCRIPT HOOPBALL
 SP  : untuk proses input/update data MASTER dan TRANSAKSI
 UDF : untuk sumber data LAPORAN dan DASHBOARD
 TRG : untuk proses otomatis transaksi + log history
============================================================

CARA PAKAI:
1. Jalankan dulu script CREATE DATABASE dan CREATE TABLE Hoopball kamu.
2. Jangan jalankan "DROP DATABASE Hoopball" di bagian akhir script lama.
3. Setelah database dan tabel sudah ada, jalankan script ini.
*/

USE Hoopball;
GO

/* ============================================================
   1. TABEL LOG HISTORY
   Fungsi:
   - Menyimpan catatan perubahan data transaksi.
   - Dipakai oleh trigger agar setiap INSERT/UPDATE transaksi punya jejak riwayat.
   ============================================================ */
IF OBJECT_ID('dbo.Log_History', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.Log_History (
        ID_Log          INT IDENTITY(1,1) PRIMARY KEY,
        Nama_Tabel      VARCHAR(50)  NOT NULL,
        ID_Data         VARCHAR(50)  NOT NULL,
        Aksi            VARCHAR(20)  NOT NULL,
        Data_Lama       VARCHAR(MAX) NULL,
        Data_Baru       VARCHAR(MAX) NULL,
        Created_By      VARCHAR(50)  NOT NULL,
        Created_Date    DATETIME     NOT NULL DEFAULT GETDATE()
    );
END;
GO


-- 1. UDF: Mengambil jumlah booking pending (untuk lonceng notifikasi)
CREATE OR ALTER FUNCTION dbo.fn_GetPendingBookingCount()
RETURNS INT
AS
BEGIN
    DECLARE @Count INT;
    SELECT @Count = COUNT(*) FROM dbo.Booking WHERE Status = 0;
    RETURN ISNULL(@Count, 0);
END;
GO

-- 2. UDF: Mengambil statistik customer aktif/nonaktif
CREATE OR ALTER FUNCTION dbo.fn_GetCustomerStats()
RETURNS TABLE
AS
RETURN (
    SELECT 
        COUNT(*) AS Total,
        SUM(CASE WHEN Status = 1 THEN 1 ELSE 0 END) AS Aktif,
        SUM(CASE WHEN Status = 0 THEN 1 ELSE 0 END) AS Nonaktif
    FROM dbo.Customer 
    WHERE Is_Deleted = 0
);
GO

-- 3. SP: Mengambil foto profil karyawan yang login
CREATE OR ALTER PROCEDURE dbo.sp_GetKaryawanPhoto
    @ID_Karyawan INT
AS
BEGIN
    SET NOCOUNT ON;
    SELECT Photo_Profile FROM dbo.Karyawan WHERE ID_Karyawan = @ID_Karyawan;
END;
GO

-- 4. SP: Melakukan update status customer (Toggle Status)
CREATE OR ALTER PROCEDURE dbo.sp_UpdateStatusCustomer
    @ID_Customer INT,
    @Status INT,
    @Modified_By VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;
    UPDATE dbo.Customer
    SET Status = @Status,
        Modified_By = @Modified_By,
        Modified_Date = GETDATE()
    WHERE ID_Customer = @ID_Customer AND Is_Deleted = 0;
END;
GO

-- 5. SP: Mengambil detail satu customer berdasarkan ID
CREATE OR ALTER PROCEDURE dbo.sp_GetCustomerDetail
    @ID_Customer INT
AS
BEGIN
    SET NOCOUNT ON;
    SELECT ID_Customer, Nama_Customer, Jenis_Kelamin, Tanggal_Lahir, Tempat_Lahir, Alamat, No_Telepon, Email, Status 
    FROM dbo.Customer 
    WHERE ID_Customer = @ID_Customer AND Is_Deleted = 0;
END;
GO

-- 6. SP: Membaca list customer terpaginasi sekaligus menghitung total datanya
CREATE OR ALTER PROCEDURE dbo.sp_ReadCustomerListWithCount
    @FilterStatus VARCHAR(10),
    @FilterJK INT,
    @SortBy VARCHAR(50),
    @SortOrder VARCHAR(10),
    @Offset INT,
    @Limit INT
AS
BEGIN
    SET NOCOUNT ON;

    -- Hasil 1: Total Record terfilter (untuk penentuan jumlah halaman pagination)
    SELECT COUNT(*) AS TotalCount
    FROM dbo.Customer
    WHERE Is_Deleted = 0
      AND (@FilterStatus = 'all' OR (@FilterStatus = 'aktif' AND Status = 1) OR (@FilterStatus = 'nonaktif' AND Status = 0))
      AND (@FilterJK = -1 OR Jenis_Kelamin = @FilterJK);

    -- Hasil 2: List Data terpaginasi
    SELECT ID_Customer, Nama_Customer, Jenis_Kelamin, Tanggal_Lahir, Tempat_Lahir, Alamat, No_Telepon, Email, Status 
    FROM dbo.Customer
    WHERE Is_Deleted = 0
      AND (@FilterStatus = 'all' OR (@FilterStatus = 'aktif' AND Status = 1) OR (@FilterStatus = 'nonaktif' AND Status = 0))
      AND (@FilterJK = -1 OR Jenis_Kelamin = @FilterJK)
    ORDER BY 
        CASE WHEN @SortOrder = 'ASC' THEN
            CASE 
                WHEN @SortBy = 'Nama_Customer' THEN Nama_Customer
                WHEN @SortBy = 'Email' THEN Email
                WHEN @SortBy = 'No_Telepon' THEN No_Telepon
                WHEN @SortBy = 'Alamat' THEN Alamat
                ELSE CAST(ID_Customer AS VARCHAR)
            END
        END ASC,
        CASE WHEN @SortOrder = 'DESC' THEN
            CASE 
                WHEN @SortBy = 'Nama_Customer' THEN Nama_Customer
                WHEN @SortBy = 'Email' THEN Email
                WHEN @SortBy = 'No_Telepon' THEN No_Telepon
                WHEN @SortBy = 'Alamat' THEN Alamat
                ELSE CAST(ID_Customer AS VARCHAR)
            END
        END DESC,
        ID_Customer ASC
    OFFSET @Offset ROWS FETCH NEXT @Limit ROWS ONLY;
END;
GO