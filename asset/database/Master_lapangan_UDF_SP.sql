-- 1. UDF: Mengambil statistik Lapangan (untuk kartu statistik atas)
CREATE OR ALTER FUNCTION dbo.fn_GetLapanganStats()
RETURNS TABLE
AS
RETURN (
    SELECT 
        COUNT(*) AS Total,
        SUM(CASE WHEN Status = 1 THEN 1 ELSE 0 END) AS Aktif,
        SUM(CASE WHEN Status = 0 THEN 1 ELSE 0 END) AS Maintenance
    FROM dbo.Lapangan 
    WHERE Is_Deleted = 0
);
GO

-- 2. SP: Cek Duplikasi Nama Lapangan
CREATE OR ALTER PROCEDURE dbo.sp_CheckLapanganDuplicate
    @Nama_Lapangan VARCHAR(50),
    @ID_Lapangan INT
AS
BEGIN
    SET NOCOUNT ON;
    SELECT ID_Lapangan 
    FROM dbo.Lapangan 
    WHERE LOWER(Nama_Lapangan) = LOWER(@Nama_Lapangan) 
      AND ID_Lapangan <> @ID_Lapangan 
      AND Is_Deleted = 0;
END;
GO

-- 3. SP: Ambil Detail Lapangan Berdasarkan ID (Untuk Edit & Detail)
CREATE OR ALTER PROCEDURE dbo.sp_GetLapanganDetail
    @ID_Lapangan INT
AS
BEGIN
    SET NOCOUNT ON;
    SELECT * FROM dbo.Lapangan WHERE ID_Lapangan = @ID_Lapangan AND Is_Deleted = 0;
END;
GO

-- 4. SP: Simpan Lapangan Baru (Create)
CREATE OR ALTER PROCEDURE dbo.sp_CreateLapangan
    @Nama_Lapangan VARCHAR(50),
    @Harga_Sewa DECIMAL(18,2),
    @Photo_Lapangan VARCHAR(255),
    @Created_By VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;
    INSERT INTO dbo.Lapangan (Nama_Lapangan, Harga_Sewa, Photo_Lapangan, Status, Is_Deleted, Created_By, Created_Date)
    VALUES (@Nama_Lapangan, @Harga_Sewa, @Photo_Lapangan, 1, 0, @Created_By, GETDATE());
END;
GO

-- 5. SP: Perbarui Data Lapangan (Update)
CREATE OR ALTER PROCEDURE dbo.sp_UpdateLapangan
    @ID_Lapangan INT,
    @Nama_Lapangan VARCHAR(50),
    @Harga_Sewa DECIMAL(18,2),
    @Photo_Lapangan VARCHAR(255),
    @Modified_By VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;
    UPDATE dbo.Lapangan 
    SET Nama_Lapangan = @Nama_Lapangan,
        Harga_Sewa = @Harga_Sewa,
        Photo_Lapangan = @Photo_Lapangan,
        Modified_By = @Modified_By,
        Modified_Date = GETDATE()
    WHERE ID_Lapangan = @ID_Lapangan AND Is_Deleted = 0;
END;
GO

-- 6. SP: Ubah Status Lapangan (Toggle Status)
CREATE OR ALTER PROCEDURE dbo.sp_UpdateStatusLapangan
    @ID_Lapangan INT,
    @Status INT,
    @Modified_By VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;
    UPDATE dbo.Lapangan 
    SET Status = @Status,
        Modified_By = @Modified_By,
        Modified_Date = GETDATE()
    WHERE ID_Lapangan = @ID_Lapangan AND Is_Deleted = 0;
END;
GO

-- 7. SP: Soft Delete Lapangan (Delete)
CREATE OR ALTER PROCEDURE dbo.sp_DeleteLapangan
    @ID_Lapangan INT,
    @Deleted_By VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;
    UPDATE dbo.Lapangan 
    SET Is_Deleted = 1,
        Deleted_By = @Deleted_By,
        Deleted_Date = GETDATE()
    WHERE ID_Lapangan = @ID_Lapangan;
END;
GO

-- 8. SP: Membaca list lapangan terpaginasi sekaligus menghitung total datanya (Read)
CREATE OR ALTER PROCEDURE dbo.sp_ReadLapanganListWithCount
    @FilterStatus VARCHAR(10),
    @SortBy VARCHAR(50),
    @Offset INT,
    @Limit INT
AS
BEGIN
    SET NOCOUNT ON;

    -- Hasil 1: Total Record terfilter
    SELECT COUNT(*) AS TotalCount
    FROM dbo.Lapangan
    WHERE Is_Deleted = 0
      AND (@FilterStatus = 'all' OR (@FilterStatus = 'aktif' AND Status = 1) OR (@FilterStatus = 'nonaktif' AND Status = 0));

    -- Hasil 2: List Data terpaginasi
    SELECT * 
    FROM dbo.Lapangan
    WHERE Is_Deleted = 0
      AND (@FilterStatus = 'all' OR (@FilterStatus = 'aktif' AND Status = 1) OR (@FilterStatus = 'nonaktif' AND Status = 0))
    ORDER BY 
        CASE WHEN @SortBy = 'nama_asc' THEN Nama_Lapangan END ASC,
        CASE WHEN @SortBy = 'harga_desc' THEN Harga_Sewa END DESC,
        CASE WHEN @SortBy = 'harga_asc' THEN Harga_Sewa END ASC,
        CASE WHEN @SortBy = 'ID_Lapangan' THEN ID_Lapangan END ASC
    OFFSET @Offset ROWS FETCH NEXT @Limit ROWS ONLY;
END;
GO