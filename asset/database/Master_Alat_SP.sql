-- ============================================================
-- STORED PROCEDURES: MASTER ALAT
-- ============================================================
-- Schema sesuai dengan database HoopBall:
-- Alat: ID_Alat, Nama_Alat, Stok, Harga_Beli, Harga_Jual, 
--       Photo_Alat, Status, Is_Deleted, Created_By, Created_Date,
--       Modified_By, Modified_Date, Deleted_By, Deleted_Date
-- ============================================================

USE Hoopball;
GO

-- ============================================================
-- 1. SP_Alat_SelectAll - Ambil semua alat (tidak dihapus)
-- ============================================================
CREATE OR ALTER PROCEDURE SP_Alat_SelectAll
AS
BEGIN
    SET NOCOUNT ON;
    SELECT 
        ID_Alat,
        Nama_Alat,
        Stok,
        Harga_Beli,
        Harga_Jual,
        Photo_Alat,
        Status,
        Is_Deleted,
        Created_By,
        Created_Date,
        Modified_By,
        Modified_Date,
        Deleted_By,
        Deleted_Date
    FROM Alat
    WHERE Is_Deleted = 0
    ORDER BY ID_Alat DESC;
END;
GO

-- ============================================================
-- 2. SP_Alat_Select - Ambil alat by ID
-- ============================================================
CREATE OR ALTER PROCEDURE SP_Alat_Select
    @ID_Alat INT
AS
BEGIN
    SET NOCOUNT ON;
    SELECT 
        ID_Alat,
        Nama_Alat,
        Stok,
        Harga_Beli,
        Harga_Jual,
        Photo_Alat,
        Status,
        Is_Deleted,
        Created_By,
        Created_Date,
        Modified_By,
        Modified_Date,
        Deleted_By,
        Deleted_Date
    FROM Alat
    WHERE ID_Alat = @ID_Alat AND Is_Deleted = 0;
END;
GO

-- ============================================================
-- 3. SP_Alat_Insert - Tambah alat baru
-- ============================================================
CREATE OR ALTER PROCEDURE SP_Alat_Insert
    @Nama_Alat      VARCHAR(25),
    @Stok           INT,
    @Harga_Beli     DECIMAL(18,2),
    @Harga_Jual     DECIMAL(18,2),
    @Photo_Alat     VARCHAR(255) = NULL,
    @Status         INT = 1,
    @Created_By     VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;

    INSERT INTO Alat
    (Nama_Alat, Stok, Harga_Beli, Harga_Jual, Photo_Alat, Status, Is_Deleted, Created_By, Created_Date)
    VALUES
    (@Nama_Alat, @Stok, @Harga_Beli, @Harga_Jual, @Photo_Alat, @Status, 0, @Created_By, GETDATE());

    SELECT SCOPE_IDENTITY() AS New_ID_Alat;
END;
GO

-- ============================================================
-- 4. SP_Alat_Update - Update data alat
-- ============================================================
CREATE OR ALTER PROCEDURE SP_Alat_Update
    @ID_Alat        INT,
    @Nama_Alat      VARCHAR(25) = NULL,
    @Stok           INT = NULL,
    @Harga_Beli     DECIMAL(18,2) = NULL,
    @Harga_Jual     DECIMAL(18,2) = NULL,
    @Photo_Alat     VARCHAR(255) = NULL,
    @Status         INT = NULL,
    @Modified_By    VARCHAR(50) = NULL
AS
BEGIN
    SET NOCOUNT ON;

    UPDATE Alat
    SET 
        Nama_Alat   = ISNULL(@Nama_Alat, Nama_Alat),
        Stok        = ISNULL(@Stok, Stok),
        Harga_Beli  = ISNULL(@Harga_Beli, Harga_Beli),
        Harga_Jual  = ISNULL(@Harga_Jual, Harga_Jual),
        Photo_Alat  = ISNULL(@Photo_Alat, Photo_Alat),
        Status      = ISNULL(@Status, Status),
        Modified_By = @Modified_By,
        Modified_Date = GETDATE()
    WHERE ID_Alat = @ID_Alat AND Is_Deleted = 0;

    SELECT @@ROWCOUNT AS RowsAffected;
END;
GO

-- ============================================================
-- 5. SP_Alat_Delete - Soft delete alat
-- ============================================================
CREATE OR ALTER PROCEDURE SP_Alat_Delete
    @ID_Alat        INT,
    @Deleted_By     VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;

    UPDATE Alat
    SET 
        Is_Deleted = 1,
        Deleted_By = @Deleted_By,
        Deleted_Date = GETDATE()
    WHERE ID_Alat = @ID_Alat AND Is_Deleted = 0;

    SELECT @@ROWCOUNT AS RowsAffected;
END;
GO

-- ============================================================
-- 6. SP_Alat_CheckDuplicate - Cek nama alat duplikat
-- ============================================================
CREATE OR ALTER PROCEDURE SP_Alat_CheckDuplicate
    @Nama_Alat      VARCHAR(25),
    @ExcludeID      INT = 0
AS
BEGIN
    SET NOCOUNT ON;

    SELECT TOP 1 ID_Alat
    FROM Alat
    WHERE Nama_Alat = @Nama_Alat 
      AND Is_Deleted = 0
      AND ID_Alat <> @ExcludeID;
END;
GO

-- ============================================================
-- 7. SP_Alat_Count - Hitung total alat (untuk pagination)
-- ============================================================
CREATE OR ALTER PROCEDURE SP_Alat_Count
    @StatusFilter   INT = NULL
AS
BEGIN
    SET NOCOUNT ON;

    SELECT COUNT(*) AS TotalCount
    FROM Alat
    WHERE Is_Deleted = 0
      AND (@StatusFilter IS NULL OR Status = @StatusFilter);
END;
GO

-- ============================================================
-- 8. SP_Alat_CountByStatus - Hitung alat per status
-- ============================================================
CREATE OR ALTER PROCEDURE SP_Alat_CountByStatus
AS
BEGIN
    SET NOCOUNT ON;

    SELECT 
        COUNT(*) AS TotalCount,
        SUM(CASE WHEN Status = 1 THEN 1 ELSE 0 END) AS AktifCount,
        SUM(CASE WHEN Status = 0 THEN 1 ELSE 0 END) AS NonaktifCount
    FROM Alat
    WHERE Is_Deleted = 0;
END;
GO

-- ============================================================
-- 9. SP_Alat_SelectFiltered - Ambil alat dengan filter & sort
-- ============================================================
CREATE OR ALTER PROCEDURE SP_Alat_SelectFiltered
    @StatusFilter   INT = NULL,
    @SortBy         VARCHAR(20) = 'nama_asc',
    @PageNumber     INT = 1,
    @PageSize       INT = 12
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE @Offset INT = (@PageNumber - 1) * @PageSize;

    SELECT 
        ID_Alat,
        Nama_Alat,
        Stok,
        Harga_Beli,
        Harga_Jual,
        Photo_Alat,
        Status,
        Is_Deleted,
        Created_By,
        Created_Date,
        Modified_By,
        Modified_Date
    FROM Alat
    WHERE Is_Deleted = 0
      AND (@StatusFilter IS NULL OR Status = @StatusFilter)
    ORDER BY
        CASE WHEN @SortBy = 'nama_asc'  THEN Nama_Alat END ASC,
        CASE WHEN @SortBy = 'nama_desc' THEN Nama_Alat END DESC,
        CASE WHEN @SortBy = 'stok_desc' THEN Stok END DESC,
        CASE WHEN @SortBy = 'stok_asc'  THEN Stok END ASC,
        CASE WHEN @SortBy = 'harga_desc' THEN Harga_Jual END DESC,
        CASE WHEN @SortBy = 'harga_asc'  THEN Harga_Jual END ASC,
        CASE WHEN @SortBy = 'id_desc'    THEN ID_Alat END DESC
    OFFSET @Offset ROWS FETCH NEXT @PageSize ROWS ONLY;
END;
GO

-- ============================================================
-- 10. SP_Alat_ToggleStatus - Toggle status aktif/nonaktif
-- ============================================================
CREATE OR ALTER PROCEDURE SP_Alat_ToggleStatus
    @ID_Alat        INT,
    @Modified_By    VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE @CurrentStatus INT;
    SELECT @CurrentStatus = Status FROM Alat WHERE ID_Alat = @ID_Alat AND Is_Deleted = 0;

    IF @CurrentStatus IS NULL
    BEGIN
        SELECT 0 AS Success, 'Alat tidak ditemukan' AS Message;
        RETURN;
    END

    DECLARE @NewStatus INT = CASE WHEN @CurrentStatus = 1 THEN 0 ELSE 1 END;

    UPDATE Alat
    SET 
        Status = @NewStatus,
        Modified_By = @Modified_By,
        Modified_Date = GETDATE()
    WHERE ID_Alat = @ID_Alat AND Is_Deleted = 0;

    SELECT 
        1 AS Success,
        CASE WHEN @NewStatus = 1 THEN 'Aktif' ELSE 'Nonaktif' END AS NewStatusText,
        @NewStatus AS NewStatusValue;
END;
GO

-- ============================================================
-- 11. SP_Alat_Restore - Restore alat yang dihapus (opsional)
-- ============================================================
CREATE OR ALTER PROCEDURE SP_Alat_Restore
    @ID_Alat        INT,
    @Modified_By    VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;

    UPDATE Alat
    SET 
        Is_Deleted = 0,
        Deleted_By = NULL,
        Deleted_Date = NULL,
        Modified_By = @Modified_By,
        Modified_Date = GETDATE()
    WHERE ID_Alat = @ID_Alat AND Is_Deleted = 1;

    SELECT @@ROWCOUNT AS RowsAffected;
END;
GO

-- ============================================================
-- 12. SP_Alat_SelectDeleted - Ambil alat yang sudah dihapus
-- ============================================================
CREATE OR ALTER PROCEDURE SP_Alat_SelectDeleted
AS
BEGIN
    SET NOCOUNT ON;

    SELECT 
        ID_Alat,
        Nama_Alat,
        Stok,
        Harga_Beli,
        Harga_Jual,
        Photo_Alat,
        Status,
        Is_Deleted,
        Created_By,
        Created_Date,
        Deleted_By,
        Deleted_Date
    FROM Alat
    WHERE Is_Deleted = 1
    ORDER BY Deleted_Date DESC;
END;
GO

-- ============================================================
-- VERIFIKASI SP
-- ============================================================
SELECT 
    ROUTINE_NAME AS Nama_SP,
    ROUTINE_TYPE AS Tipe
FROM INFORMATION_SCHEMA.ROUTINES
WHERE ROUTINE_SCHEMA = 'dbo'
  AND ROUTINE_NAME LIKE 'SP_Alat%'
ORDER BY ROUTINE_NAME;
GO