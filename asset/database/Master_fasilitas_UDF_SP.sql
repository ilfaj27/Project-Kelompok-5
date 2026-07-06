-- ==========================================================================================
-- 1. UDF: Mengambil statistik Fasilitas Global (untuk kartu statistik atas)
-- ==========================================================================================
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

-- ==========================================================================================
-- 2. SP: Mengambil daftar lapangan aktif (untuk dropdown form select)
-- ==========================================================================================
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

-- ==========================================================================================
-- 3. SP: Cek Duplikasi Nama Fasilitas secara Global (Bukan per Lapangan)
-- ==========================================================================================
CREATE OR ALTER PROCEDURE dbo.sp_CheckFasilitasDuplicate
    @Nama_Fasilitas VARCHAR(50),
    @ID_Fasilitas INT
AS
BEGIN
    SET NOCOUNT ON;
    SELECT ID_Fasilitas 
    FROM dbo.Fasilitas_Lapangan 
    WHERE LOWER(Nama_Fasilitas) = LOWER(@Nama_Fasilitas) 
      AND ID_Fasilitas <> @ID_Fasilitas 
      AND Is_Deleted = 0;
END;
GO

-- ==========================================================================================
-- 4. SP: Ambil Detail Fasilitas Berdasarkan ID (Fasilitas Mandiri)
-- ==========================================================================================
CREATE OR ALTER PROCEDURE dbo.sp_GetFasilitasDetail
    @ID_Fasilitas INT
AS
BEGIN
    SET NOCOUNT ON;
    SELECT ID_Fasilitas, Nama_Fasilitas, Detail_Fasilitas, Stok_Total, Stok_Tersedia, Status
    FROM dbo.Fasilitas_Lapangan 
    WHERE ID_Fasilitas = @ID_Fasilitas AND Is_Deleted = 0;
END;
GO

-- ==========================================================================================
-- 5. SP: Simpan Fasilitas Baru dengan Input Stok (Create)
-- ==========================================================================================
CREATE OR ALTER PROCEDURE dbo.sp_CreateFasilitas
    @Nama_Fasilitas VARCHAR(50),
    @Detail_Fasilitas VARCHAR(50),
    @Stok_Total INT,
    @Created_By VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;
    -- Saat pertama kali dibuat, Stok_Tersedia diisi sama dengan Stok_Total
    INSERT INTO dbo.Fasilitas_Lapangan (Nama_Fasilitas, Detail_Fasilitas, Stok_Total, Stok_Tersedia, Status, Is_Deleted, Created_By, Created_Date)
    VALUES (@Nama_Fasilitas, @Detail_Fasilitas, @Stok_Total, @Stok_Total, 1, 0, @Created_By, GETDATE());
END;
GO

-- ==========================================================================================
-- 6. SP: Perbarui Data Fasilitas & Kalkulasi Stok (Update)
-- ==========================================================================================
CREATE OR ALTER PROCEDURE dbo.sp_UpdateFasilitas
    @ID_Fasilitas INT,
    @Nama_Fasilitas VARCHAR(50),
    @Detail_Fasilitas VARCHAR(50),
    @Stok_Total INT,
    @Modified_By VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;
    BEGIN TRANSACTION;

    BEGIN TRY
        DECLARE @OldStokTotal INT;
        DECLARE @OldStokTersedia INT;
        DECLARE @SelisihStok INT;

        -- 1. Ambil nilai stok lama
        SELECT @OldStokTotal = Stok_Total, @OldStokTersedia = Stok_Tersedia
        FROM dbo.Fasilitas_Lapangan
        WHERE ID_Fasilitas = @ID_Fasilitas;

        -- Hitung selisih perubahan stok total
        SET @SelisihStok = @Stok_Total - @OldStokTotal;

        -- Validasi: Stok baru tidak boleh membuat Stok_Tersedia menjadi minus
        IF (@OldStokTersedia + @SelisihStok) < 0
        BEGIN
            THROW 50002, 'Gagal mengubah stok. Jumlah fasilitas yang sedang digunakan di lapangan melebihi batas stok baru yang ditentukan.', 1;
        END

        -- 2. Update data fasilitas beserta penyesuaian stok tersedia
        UPDATE dbo.Fasilitas_Lapangan 
        SET Nama_Fasilitas = @Nama_Fasilitas,
            Detail_Fasilitas = @Detail_Fasilitas,
            Stok_Total = @Stok_Total,
            Stok_Tersedia = Stok_Tersedia + @SelisihStok,
            Modified_By = @Modified_By,
            Modified_Date = GETDATE()
        WHERE ID_Fasilitas = @ID_Fasilitas AND Is_Deleted = 0;

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        ROLLBACK TRANSACTION;
        THROW;
    END CATCH
END;
GO

-- ==========================================================================================
-- 7. SP: Ubah Status Fasilitas (Toggle Status)
-- ==========================================================================================
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

-- ==========================================================================================
-- 8. SP: Soft Delete Fasilitas dengan Validasi Penggunaan (Delete)
-- ==========================================================================================
CREATE OR ALTER PROCEDURE dbo.sp_DeleteFasilitas
    @ID_Fasilitas INT,
    @Deleted_By VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;
    
    -- Validasi: Cegah penghapusan jika fasilitas ini masih terpasang di lapangan aktif
    IF EXISTS (
        SELECT 1 
        FROM dbo.Detail_Lapangan_Fasilitas 
        WHERE ID_Fasilitas = @ID_Fasilitas
    )
    BEGIN
        RAISERROR('Fasilitas tidak dapat dihapus karena masih digunakan oleh salah satu lapangan.', 16, 1);
        RETURN;
    END

    UPDATE dbo.Fasilitas_Lapangan 
    SET Is_Deleted = 1,
        Deleted_By = @Deleted_By,
        Deleted_Date = GETDATE()
    WHERE ID_Fasilitas = @ID_Fasilitas;
END;
GO

-- ==========================================================================================
-- 9. SP: Membaca list fasilitas terpaginasi (Read - PERBAIKAN GROUP BY)
-- ==========================================================================================
CREATE OR ALTER PROCEDURE dbo.sp_ReadFasilitasListWithCount
    @FilterLapangan VARCHAR(10),
    @FilterStatus VARCHAR(10),
    @SortBy VARCHAR(50),
    @Offset INT,
    @Limit INT
AS
BEGIN
    SET NOCOUNT ON;

    -- Hasil 1: Total Record terfilter (Menggunakan GROUP BY untuk keunikan data)
    SELECT COUNT(*) AS TotalCount
    FROM (
        SELECT f.ID_Fasilitas
        FROM dbo.Fasilitas_Lapangan f
        LEFT JOIN dbo.Detail_Lapangan_Fasilitas lf ON f.ID_Fasilitas = lf.ID_Fasilitas
        WHERE f.Is_Deleted = 0
          AND (@FilterLapangan = 'all' OR lf.ID_Lapangan = TRY_CAST(@FilterLapangan AS INT))
          AND (@FilterStatus = 'all' OR f.Status = TRY_CAST(@FilterStatus AS INT))
        GROUP BY f.ID_Fasilitas
    ) AS UniqueFilteredFacilities;

    -- Hasil 2: List Data terpaginasi (Menggunakan GROUP BY menggantikan DISTINCT)
    SELECT f.ID_Fasilitas, f.Nama_Fasilitas, f.Detail_Fasilitas, f.Stok_Total, f.Stok_Tersedia, f.Status
    FROM dbo.Fasilitas_Lapangan f
    LEFT JOIN dbo.Detail_Lapangan_Fasilitas lf ON f.ID_Fasilitas = lf.ID_Fasilitas
    WHERE f.Is_Deleted = 0
      AND (@FilterLapangan = 'all' OR lf.ID_Lapangan = TRY_CAST(@FilterLapangan AS INT))
      AND (@FilterStatus = 'all' OR f.Status = TRY_CAST(@FilterStatus AS INT))
    GROUP BY f.ID_Fasilitas, f.Nama_Fasilitas, f.Detail_Fasilitas, f.Stok_Total, f.Stok_Tersedia, f.Status
    ORDER BY 
        CASE WHEN @SortBy = 'nomor_asc' THEN f.ID_Fasilitas END ASC,
        CASE WHEN @SortBy = 'nomor_desc' THEN f.ID_Fasilitas END DESC,
        CASE WHEN @SortBy = 'nama_asc' THEN f.Nama_Fasilitas END ASC,
        CASE WHEN @SortBy = 'stok_desc' THEN f.Stok_Total END DESC,
        f.ID_Fasilitas ASC
    OFFSET @Offset ROWS FETCH NEXT @Limit ROWS ONLY;
END;
GO