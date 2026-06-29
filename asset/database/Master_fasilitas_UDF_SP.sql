-- 1. UDF: Mengambil statistik Fasilitas (untuk kartu statistik atas)
CREATE OR ALTER FUNCTION dbo.fn_GetFasilitasStats()
RETURNS TABLE
AS
RETURN (
    SELECT 
        COUNT(*) AS Total,
        SUM(CASE WHEN Status = 1 THEN 1 ELSE 0 END) AS Aktif,
        SUM(CASE WHEN Status = 0 THEN 1 ELSE 0 END) AS Nonaktif
    FROM dbo.Fasilitas_Lapangan 
    WHERE Is_Deleted = 0
);
GO

-- 2. SP: Mengambil daftar lapangan aktif (untuk dropdown form select)
CREATE OR ALTER PROCEDURE dbo.sp_GetActiveLapanganList
AS
BEGIN
    SET NOCOUNT ON;
    SELECT ID_Lapangan, Nama_Lapangan, Harga_Sewa 
    FROM dbo.Lapangan 
    WHERE Is_Deleted = 0 AND Status = 1 
    ORDER BY Nama_Lapangan;
END;
GO


-- 3. SP: Cek Duplikasi Fasilitas di Lapangan yang Sama
CREATE OR ALTER PROCEDURE dbo.sp_CheckFasilitasOnCourtDuplicate
    @Nama_Fasilitas VARCHAR(50),
    @ID_Lapangan INT,
    @ID_Fasilitas INT
AS
BEGIN
    SET NOCOUNT ON;
    SELECT ID_Fasilitas 
    FROM dbo.Fasilitas_Lapangan 
    WHERE Nama_Fasilitas = @Nama_Fasilitas 
      AND ID_Lapangan = @ID_Lapangan 
      AND ID_Fasilitas <> @ID_Fasilitas 
      AND Is_Deleted = 0;
END;
GO

-- 4. SP: Ambil Detail/Edit Fasilitas Berdasarkan ID (Join dengan Lapangan)
CREATE OR ALTER PROCEDURE dbo.sp_GetFasilitasDetail
    @ID_Fasilitas INT
AS
BEGIN
    SET NOCOUNT ON;
    SELECT f.*, l.Nama_Lapangan, l.Harga_Sewa 
    FROM dbo.Fasilitas_Lapangan f 
    JOIN dbo.Lapangan l ON f.ID_Lapangan = l.ID_Lapangan 
    WHERE f.ID_Fasilitas = @ID_Fasilitas AND f.Is_Deleted = 0;
END;
GO

-- 5. SP: Simpan Fasilitas Baru (Create)
CREATE OR ALTER PROCEDURE dbo.sp_CreateFasilitas
    @ID_Lapangan INT,
    @Nama_Fasilitas VARCHAR(50),
    @Detail_Fasilitas VARCHAR(50),
    @Created_By VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;
    INSERT INTO dbo.Fasilitas_Lapangan (ID_Lapangan, Nama_Fasilitas, Detail_Fasilitas, Status, Is_Deleted, Created_By, Created_Date)
    VALUES (@ID_Lapangan, @Nama_Fasilitas, @Detail_Fasilitas, 1, 0, @Created_By, GETDATE());
END;
GO

-- 6. SP: Perbarui Data Fasilitas (Update)
CREATE OR ALTER PROCEDURE dbo.sp_UpdateFasilitas
    @ID_Fasilitas INT,
    @ID_Lapangan INT,
    @Nama_Fasilitas VARCHAR(50),
    @Detail_Fasilitas VARCHAR(50),
    @Modified_By VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;
    UPDATE dbo.Fasilitas_Lapangan 
    SET ID_Lapangan = @ID_Lapangan,
        Nama_Fasilitas = @Nama_Fasilitas,
        Detail_Fasilitas = @Detail_Fasilitas,
        Modified_By = @Modified_By,
        Modified_Date = GETDATE()
    WHERE ID_Fasilitas = @ID_Fasilitas AND Is_Deleted = 0;
END;
GO

-- 7. SP: Ubah Status Fasilitas (Toggle Status)
CREATE OR ALTER PROCEDURE dbo.sp_UpdateStatusFasilitas
    @ID_Fasilitas INT,
    @Status INT,
    @Modified_By VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;
    UPDATE dbo.Fasilitas_Lapangan 
    SET Status = @Status,
        Modified_By = @Modified_By,
        Modified_Date = GETDATE()
    WHERE ID_Fasilitas = @ID_Fasilitas AND Is_Deleted = 0;
END;
GO

-- 8. SP: Soft Delete Fasilitas (Delete)
CREATE OR ALTER PROCEDURE dbo.sp_DeleteFasilitas
    @ID_Fasilitas INT,
    @Deleted_By VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;
    UPDATE dbo.Fasilitas_Lapangan 
    SET Is_Deleted = 1,
        Deleted_By = @Deleted_By,
        Deleted_Date = GETDATE()
    WHERE ID_Fasilitas = @ID_Fasilitas;
END;
GO

-- 9. SP: Membaca list fasilitas terpaginasi sekaligus menghitung total datanya (Read)
CREATE OR ALTER PROCEDURE dbo.sp_ReadFasilitasListWithCount
    @FilterLapangan VARCHAR(10),
    @FilterStatus VARCHAR(10),
    @SortBy VARCHAR(50),
    @Offset INT,
    @Limit INT
AS
BEGIN
    SET NOCOUNT ON;

    -- Hasil 1: Total Record terfilter
    SELECT COUNT(*) AS TotalCount
    FROM dbo.Fasilitas_Lapangan f
    JOIN dbo.Lapangan l ON f.ID_Lapangan = l.ID_Lapangan
    WHERE f.Is_Deleted = 0
      AND (@FilterLapangan = 'all' OR f.ID_Lapangan = TRY_CAST(@FilterLapangan AS INT)) -- MENGGUNAKAN TRY_CAST AGAR AMAN
      AND (@FilterStatus = 'all' OR f.Status = TRY_CAST(@FilterStatus AS INT))

    -- Hasil 2: List Data terpaginasi
    SELECT f.*, l.Nama_Lapangan, l.Harga_Sewa 
    FROM dbo.Fasilitas_Lapangan f
    JOIN dbo.Lapangan l ON f.ID_Lapangan = l.ID_Lapangan
    WHERE f.Is_Deleted = 0
      AND (@FilterLapangan = 'all' OR f.ID_Lapangan = TRY_CAST(@FilterLapangan AS INT)) -- MENGGUNAKAN TRY_CAST AGAR AMAN
      AND (@FilterStatus = 'all' OR f.Status = TRY_CAST(@FilterStatus AS INT))
    ORDER BY 
        CASE WHEN @SortBy = 'nomor_asc' THEN f.ID_Fasilitas END ASC,
        CASE WHEN @SortBy = 'nomor_desc' THEN f.ID_Fasilitas END DESC,
        CASE WHEN @SortBy = 'nama_asc' THEN f.Nama_Fasilitas END ASC,
        CASE WHEN @SortBy = 'lapangan_asc' THEN l.Nama_Lapangan END ASC,
        f.ID_Fasilitas ASC
    OFFSET @Offset ROWS FETCH NEXT @Limit ROWS ONLY;
END;
GO