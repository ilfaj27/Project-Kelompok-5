-- ============================================================
-- STORED PROCEDURES MASTER: LAPANGAN
-- ============================================================
-- SP yang dibuat:
-- 1. sp_GetLapanganDetail          - Ambil detail lapangan + fasilitas terpasang
-- 2. sp_CheckLapanganDuplicate     - Cek duplikasi nama lapangan
-- 3. sp_CreateLapangan             - Tambah lapangan baru + fasilitas
-- 4. sp_UpdateLapangan             - Update lapangan + fasilitas
-- 5. sp_UpdateStatusLapangan       - Toggle status aktif/maintenance
-- 6. sp_DeleteLapangan             - Soft delete lapangan
-- 7. sp_ReadLapanganListWithCount  - List lapangan dengan pagination
-- 8. fn_GetLapanganStats           - Function statistik lapangan
-- ============================================================

USE Hoopball;
GO

-- ============================================================
-- 1. FUNCTION: fn_GetLapanganStats
-- ============================================================
-- Mengembalikan statistik total, aktif, dan maintenance lapangan
-- ============================================================
CREATE OR ALTER FUNCTION dbo.fn_GetLapanganStats()
RETURNS TABLE
AS
RETURN
(
    SELECT 
        COUNT(*) AS Total,
        SUM(CASE WHEN Status = 1 THEN 1 ELSE 0 END) AS Aktif,
        SUM(CASE WHEN Status = 0 THEN 1 ELSE 0 END) AS Maintenance
    FROM Lapangan
    WHERE Is_Deleted = 0
);
GO

-- ============================================================
-- 2. SP: sp_GetLapanganDetail
-- ============================================================
-- Mengambil detail lapangan berdasarkan ID
-- Return: Result Set 1 = Data Lapangan, Result Set 2 = Fasilitas Terpasang
-- ============================================================
CREATE OR ALTER PROCEDURE dbo.sp_GetLapanganDetail
    @ID_Lapangan INT
AS
BEGIN
    SET NOCOUNT ON;

    -- Result Set 1: Data Lapangan
    SELECT 
        ID_Lapangan,
        Nama_Lapangan,
        Harga_Sewa,
        Photo_Lapangan,
        Status,
        Is_Deleted,
        Created_By,
        Created_Date,
        Modified_By,
        Modified_Date
    FROM Lapangan
    WHERE ID_Lapangan = @ID_Lapangan
      AND Is_Deleted = 0;

    -- Result Set 2: Daftar Fasilitas yang Terpasang
    SELECT 
        dlf.ID_Fasilitas,
        fl.Nama_Fasilitas,
        dlf.Jumlah_Digunakan
    FROM Detail_Lapangan_Fasilitas dlf
    INNER JOIN Fasilitas_Lapangan fl ON dlf.ID_Fasilitas = fl.ID_Fasilitas
    WHERE dlf.ID_Lapangan = @ID_Lapangan;
END;
GO

-- ============================================================
-- 3. SP: sp_CheckLapanganDuplicate
-- ============================================================
-- Mengecek apakah nama lapangan sudah terdaftar (untuk validasi)
-- Return: 1 baris jika duplikat ditemukan, kosong jika tidak
-- ============================================================
CREATE OR ALTER PROCEDURE dbo.sp_CheckLapanganDuplicate
    @Nama_Lapangan VARCHAR(25),
    @Exclude_ID INT = 0  -- ID yang dikecualikan (untuk mode edit)
AS
BEGIN
    SET NOCOUNT ON;

    SELECT TOP 1 ID_Lapangan
    FROM Lapangan
    WHERE Nama_Lapangan = @Nama_Lapangan
      AND Is_Deleted = 0
      AND (@Exclude_ID = 0 OR ID_Lapangan <> @Exclude_ID);
END;
GO

-- ============================================================
-- 4. SP: sp_CreateLapangan
-- ============================================================
-- Menambahkan lapangan baru beserta fasilitas terpasang
-- @Facilities_Json format: [{"id":1,"qty":2},{"id":3,"qty":4}]
-- ============================================================
CREATE OR ALTER PROCEDURE dbo.sp_CreateLapangan
    @Nama_Lapangan   VARCHAR(25),
    @Harga_Sewa      DECIMAL(18,2),
    @Photo_Lapangan  VARCHAR(255),
    @Created_By      VARCHAR(50),
    @Facilities_Json NVARCHAR(MAX) = NULL
AS
BEGIN
    SET NOCOUNT ON;
    
    BEGIN TRY
        BEGIN TRANSACTION;

        -- Insert data lapangan baru
        INSERT INTO Lapangan
        (Nama_Lapangan, Harga_Sewa, Photo_Lapangan, Status, Is_Deleted, Created_By, Created_Date)
        VALUES
        (@Nama_Lapangan, @Harga_Sewa, @Photo_Lapangan, 1, 0, @Created_By, GETDATE());

        -- Ambil ID lapangan yang baru dibuat
        DECLARE @New_ID INT = SCOPE_IDENTITY();

        -- Insert fasilitas terpasang jika ada
        IF @Facilities_Json IS NOT NULL AND LEN(@Facilities_Json) > 0
        BEGIN
            INSERT INTO Detail_Lapangan_Fasilitas (ID_Lapangan, ID_Fasilitas, Jumlah_Digunakan)
            SELECT 
                @New_ID,
                JSON_VALUE(j.value, '$.id') AS ID_Fasilitas,
                ISNULL(JSON_VALUE(j.value, '$.qty'), 1) AS Jumlah_Digunakan
            FROM OPENJSON(@Facilities_Json) AS j;
        END

        COMMIT TRANSACTION;
        
        SELECT @New_ID AS ID_Lapangan, 'SUCCESS' AS Result;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        
        THROW;
    END CATCH
END;
GO

-- ============================================================
-- 5. SP: sp_UpdateLapangan
-- ============================================================
-- Mengupdate data lapangan beserta fasilitas terpasang
-- @Facilities_Json format: [{"id":1,"qty":2},{"id":3,"qty":4}]
-- ============================================================
CREATE OR ALTER PROCEDURE dbo.sp_UpdateLapangan
    @ID_Lapangan     INT,
    @Nama_Lapangan   VARCHAR(25),
    @Harga_Sewa      DECIMAL(18,2),
    @Photo_Lapangan  VARCHAR(255),
    @Modified_By     VARCHAR(50),
    @Facilities_Json NVARCHAR(MAX) = NULL
AS
BEGIN
    SET NOCOUNT ON;
    
    BEGIN TRY
        BEGIN TRANSACTION;

        -- Update data lapangan
        UPDATE Lapangan
        SET 
            Nama_Lapangan = @Nama_Lapangan,
            Harga_Sewa = @Harga_Sewa,
            Photo_Lapangan = @Photo_Lapangan,
            Modified_By = @Modified_By,
            Modified_Date = GETDATE()
        WHERE ID_Lapangan = @ID_Lapangan
          AND Is_Deleted = 0;

        -- Hapus fasilitas lama
        DELETE FROM Detail_Lapangan_Fasilitas
        WHERE ID_Lapangan = @ID_Lapangan;

        -- Insert fasilitas baru jika ada
        IF @Facilities_Json IS NOT NULL AND LEN(@Facilities_Json) > 0
        BEGIN
            INSERT INTO Detail_Lapangan_Fasilitas (ID_Lapangan, ID_Fasilitas, Jumlah_Digunakan)
            SELECT 
                @ID_Lapangan,
                JSON_VALUE(j.value, '$.id') AS ID_Fasilitas,
                ISNULL(JSON_VALUE(j.value, '$.qty'), 1) AS Jumlah_Digunakan
            FROM OPENJSON(@Facilities_Json) AS j;
        END

        COMMIT TRANSACTION;
        
        SELECT @ID_Lapangan AS ID_Lapangan, 'SUCCESS' AS Result;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        
        THROW;
    END CATCH
END;
GO

-- ============================================================
-- 6. SP: sp_UpdateStatusLapangan
-- ============================================================
-- Mengubah status lapangan (Aktif <-> Maintenance)
-- Status: 1 = Aktif, 0 = Maintenance
-- ============================================================
CREATE OR ALTER PROCEDURE dbo.sp_UpdateStatusLapangan
    @ID_Lapangan  INT,
    @New_Status   INT,
    @Modified_By  VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;

    UPDATE Lapangan
    SET 
        Status = @New_Status,
        Modified_By = @Modified_By,
        Modified_Date = GETDATE()
    WHERE ID_Lapangan = @ID_Lapangan
      AND Is_Deleted = 0;

    SELECT @@ROWCOUNT AS Rows_Affected;
END;
GO

-- ============================================================
-- 7. SP: sp_DeleteLapangan
-- ============================================================
-- Soft delete lapangan (menandai Is_Deleted = 1)
-- ============================================================
CREATE OR ALTER PROCEDURE dbo.sp_DeleteLapangan
    @ID_Lapangan INT,
    @Deleted_By  VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;

    UPDATE Lapangan
    SET 
        Is_Deleted = 1,
        Deleted_By = @Deleted_By,
        Deleted_Date = GETDATE()
    WHERE ID_Lapangan = @ID_Lapangan
      AND Is_Deleted = 0;

    SELECT @@ROWCOUNT AS Rows_Affected;
END;
GO

-- ============================================================
-- 8. SP: sp_ReadLapanganListWithCount
-- ============================================================
-- Mengambil list lapangan dengan pagination, filter, dan search
-- Return: Result Set 1 = Total Count, Result Set 2 = Data
-- ============================================================
CREATE OR ALTER PROCEDURE dbo.sp_ReadLapanganListWithCount
    @FilterStatus VARCHAR(20) = 'all',  -- 'all', 'aktif', 'nonaktif'
    @SortBy       VARCHAR(20) = 'ID_Lapangan',
    @Offset       INT = 0,
    @Limit        INT = 8,
    @Search       VARCHAR(50) = ''
AS
BEGIN
    SET NOCOUNT ON;

    -- Result Set 1: Total Count (untuk pagination)
    SELECT COUNT(*) AS TotalCount
    FROM Lapangan
    WHERE Is_Deleted = 0
      AND (@FilterStatus = 'all' 
           OR (@FilterStatus = 'aktif' AND Status = 1)
           OR (@FilterStatus = 'nonaktif' AND Status = 0))
      AND (@Search = '' OR Nama_Lapangan LIKE '%' + @Search + '%');

    -- Result Set 2: Data Lapangan Terpaginasi
    SELECT 
        ID_Lapangan,
        Nama_Lapangan,
        Harga_Sewa,
        Photo_Lapangan,
        Status,
        Is_Deleted,
        Created_By,
        Created_Date,
        Modified_By,
        Modified_Date
    FROM Lapangan
    WHERE Is_Deleted = 0
      AND (@FilterStatus = 'all' 
           OR (@FilterStatus = 'aktif' AND Status = 1)
           OR (@FilterStatus = 'nonaktif' AND Status = 0))
      AND (@Search = '' OR Nama_Lapangan LIKE '%' + @Search + '%')
    ORDER BY 
        CASE 
            WHEN @SortBy = 'nama_asc' THEN Nama_Lapangan 
        END ASC,
        CASE 
            WHEN @SortBy = 'harga_desc' THEN CAST(Harga_Sewa AS VARCHAR(20))
        END DESC,
        CASE 
            WHEN @SortBy = 'harga_asc' THEN CAST(Harga_Sewa AS VARCHAR(20))
        END ASC,
        CASE 
            WHEN @SortBy = 'ID_Lapangan' THEN CAST(ID_Lapangan AS VARCHAR(20))
        END ASC
    OFFSET @Offset ROWS
    FETCH NEXT @Limit ROWS ONLY;
END;
GO

-- ============================================================
-- VERIFIKASI SP & FUNCTION
-- ============================================================
PRINT '=== Stored Procedures Lapangan berhasil dibuat ===';
PRINT '';
PRINT 'Daftar SP yang tersedia:';
PRINT '  1. dbo.fn_GetLapanganStats()           - Function statistik';
PRINT '  2. dbo.sp_GetLapanganDetail            - Detail lapangan + fasilitas';
PRINT '  3. dbo.sp_CheckLapanganDuplicate       - Cek duplikasi nama';
PRINT '  4. dbo.sp_CreateLapangan               - Tambah lapangan baru';
PRINT '  5. dbo.sp_UpdateLapangan               - Update lapangan';
PRINT '  6. dbo.sp_UpdateStatusLapangan         - Toggle status';
PRINT '  7. dbo.sp_DeleteLapangan               - Soft delete';
PRINT '  8. dbo.sp_ReadLapanganListWithCount    - List dengan pagination';
GO