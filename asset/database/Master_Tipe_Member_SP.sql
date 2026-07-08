-- ============================================================
-- STORED PROCEDURE: Tipe_Member
-- Database: Hoopball
-- ============================================================

USE Hoopball;
GO

-- ============================================================
-- 1. SP_GetAllTipeMember
--    Mengambil semua data tipe member yang aktif (tidak dihapus)
-- ============================================================
IF OBJECT_ID('dbo.SP_GetAllTipeMember', 'P') IS NOT NULL
    DROP PROCEDURE dbo.SP_GetAllTipeMember;
GO

CREATE PROCEDURE dbo.SP_GetAllTipeMember
    @Status INT = NULL
AS
BEGIN
    SET NOCOUNT ON;

    SELECT 
        ID_Tipe,
        Nama_Tipe,
        Harga_Member,
        Potongan_Harga,
        Status,
        Is_Deleted,
        Created_By,
        Created_Date,
        Modified_By,
        Modified_Date
    FROM Tipe_Member
    WHERE Is_Deleted = 0
      AND (@Status IS NULL OR Status = @Status)
    ORDER BY Nama_Tipe ASC;
END;
GO

-- ============================================================
-- 2. SP_GetTipeMemberByID
--    Mengambil data tipe member berdasarkan ID
-- ============================================================
IF OBJECT_ID('dbo.SP_GetTipeMemberByID', 'P') IS NOT NULL
    DROP PROCEDURE dbo.SP_GetTipeMemberByID;
GO

CREATE PROCEDURE dbo.SP_GetTipeMemberByID
    @ID_Tipe INT
AS
BEGIN
    SET NOCOUNT ON;

    SELECT 
        ID_Tipe,
        Nama_Tipe,
        Harga_Member,
        Potongan_Harga,
        Status,
        Is_Deleted,
        Created_By,
        Created_Date,
        Modified_By,
        Modified_Date
    FROM Tipe_Member
    WHERE ID_Tipe = @ID_Tipe
      AND Is_Deleted = 0;
END;
GO

-- ============================================================
-- 3. SP_InsertTipeMember
--    Menambah tipe member baru
-- ============================================================
IF OBJECT_ID('dbo.SP_InsertTipeMember', 'P') IS NOT NULL
    DROP PROCEDURE dbo.SP_InsertTipeMember;
GO

CREATE PROCEDURE dbo.SP_InsertTipeMember
    @Nama_Tipe      VARCHAR(20),
    @Harga_Member   DECIMAL(18,2),
    @Potongan_Harga DECIMAL(18,2),
    @Created_By     VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;

    -- Cek duplikat nama tipe
    IF EXISTS (SELECT 1 FROM Tipe_Member WHERE Nama_Tipe = @Nama_Tipe AND Is_Deleted = 0)
    BEGIN
        RAISERROR('Nama tipe member sudah terdaftar.', 16, 1);
        RETURN;
    END;

    -- Validasi harga member
    IF @Harga_Member < 0
    BEGIN
        RAISERROR('Harga member tidak boleh kurang dari 0.', 16, 1);
        RETURN;
    END;

    IF @Harga_Member < 80000
    BEGIN
        RAISERROR('Harga member minimal 80000.', 16, 1);
        RETURN;
    END;

    -- Validasi potongan harga
    IF @Potongan_Harga < 0
    BEGIN
        RAISERROR('Potongan harga tidak boleh kurang dari 0.', 16, 1);
        RETURN;
    END;

    IF @Potongan_Harga < 50000
    BEGIN
        RAISERROR('Potongan harga minimal 50000.', 16, 1);
        RETURN;
    END;

    IF @Potongan_Harga > @Harga_Member
    BEGIN
        RAISERROR('Potongan harga tidak boleh lebih besar dari harga member.', 16, 1);
        RETURN;
    END;

    INSERT INTO Tipe_Member (Nama_Tipe, Harga_Member, Potongan_Harga, Status, Is_Deleted, Created_By, Created_Date)
    VALUES (@Nama_Tipe, @Harga_Member, @Potongan_Harga, 1, 0, @Created_By, GETDATE());

    SELECT SCOPE_IDENTITY() AS ID_Tipe;
END;
GO

-- ============================================================
-- 4. SP_UpdateTipeMember
--    Mengupdate data tipe member
-- ============================================================
IF OBJECT_ID('dbo.SP_UpdateTipeMember', 'P') IS NOT NULL
    DROP PROCEDURE dbo.SP_UpdateTipeMember;
GO

CREATE PROCEDURE dbo.SP_UpdateTipeMember
    @ID_Tipe        INT,
    @Nama_Tipe      VARCHAR(20),
    @Harga_Member   DECIMAL(18,2),
    @Potongan_Harga DECIMAL(18,2),
    @Modified_By    VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;

    -- Cek apakah data exists
    IF NOT EXISTS (SELECT 1 FROM Tipe_Member WHERE ID_Tipe = @ID_Tipe AND Is_Deleted = 0)
    BEGIN
        RAISERROR('Data tipe member tidak ditemukan.', 16, 1);
        RETURN;
    END;

    -- Cek duplikat nama tipe (selain dirinya sendiri)
    IF EXISTS (SELECT 1 FROM Tipe_Member WHERE Nama_Tipe = @Nama_Tipe AND ID_Tipe <> @ID_Tipe AND Is_Deleted = 0)
    BEGIN
        RAISERROR('Nama tipe member sudah terdaftar.', 16, 1);
        RETURN;
    END;

    -- Validasi harga member
    IF @Harga_Member < 0
    BEGIN
        RAISERROR('Harga member tidak boleh kurang dari 0.', 16, 1);
        RETURN;
    END;

    IF @Harga_Member < 80000
    BEGIN
        RAISERROR('Harga member minimal 80000.', 16, 1);
        RETURN;
    END;

    -- Validasi potongan harga
    IF @Potongan_Harga < 0
    BEGIN
        RAISERROR('Potongan harga tidak boleh kurang dari 0.', 16, 1);
        RETURN;
    END;

    IF @Potongan_Harga < 50000
    BEGIN
        RAISERROR('Potongan harga minimal 50000.', 16, 1);
        RETURN;
    END;

    IF @Potongan_Harga > @Harga_Member
    BEGIN
        RAISERROR('Potongan harga tidak boleh lebih besar dari harga member.', 16, 1);
        RETURN;
    END;

    UPDATE Tipe_Member
    SET Nama_Tipe = @Nama_Tipe,
        Harga_Member = @Harga_Member,
        Potongan_Harga = @Potongan_Harga,
        Modified_By = @Modified_By,
        Modified_Date = GETDATE()
    WHERE ID_Tipe = @ID_Tipe;
END;
GO

-- ============================================================
-- 5. SP_ToggleStatusTipeMember
--    Mengubah status aktif/nonaktif tipe member
-- ============================================================
IF OBJECT_ID('dbo.SP_ToggleStatusTipeMember', 'P') IS NOT NULL
    DROP PROCEDURE dbo.SP_ToggleStatusTipeMember;
GO

CREATE PROCEDURE dbo.SP_ToggleStatusTipeMember
    @ID_Tipe INT
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE @CurrentStatus INT;

    SELECT @CurrentStatus = Status
    FROM Tipe_Member
    WHERE ID_Tipe = @ID_Tipe AND Is_Deleted = 0;

    IF @CurrentStatus IS NULL
    BEGIN
        RAISERROR('Data tipe member tidak ditemukan.', 16, 1);
        RETURN;
    END;

    UPDATE Tipe_Member
    SET Status = CASE WHEN @CurrentStatus = 1 THEN 0 ELSE 1 END
    WHERE ID_Tipe = @ID_Tipe;
END;
GO

-- ============================================================
-- 6. SP_DeleteTipeMember (Soft Delete)
--    Menghapus tipe member secara soft delete
-- ============================================================
IF OBJECT_ID('dbo.SP_DeleteTipeMember', 'P') IS NOT NULL
    DROP PROCEDURE dbo.SP_DeleteTipeMember;
GO

CREATE PROCEDURE dbo.SP_DeleteTipeMember
    @ID_Tipe     INT,
    @Deleted_By  VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;

    IF NOT EXISTS (SELECT 1 FROM Tipe_Member WHERE ID_Tipe = @ID_Tipe AND Is_Deleted = 0)
    BEGIN
        RAISERROR('Data tipe member tidak ditemukan.', 16, 1);
        RETURN;
    END;

    UPDATE Tipe_Member
    SET Is_Deleted = 1,
        Deleted_By = @Deleted_By,
        Deleted_Date = GETDATE()
    WHERE ID_Tipe = @ID_Tipe;
END;
GO

-- ============================================================
-- 7. SP_GetTipeMemberStats
--    Mengambil statistik tipe member (aktif, nonaktif, total)
-- ============================================================
IF OBJECT_ID('dbo.SP_GetTipeMemberStats', 'P') IS NOT NULL
    DROP PROCEDURE dbo.SP_GetTipeMemberStats;
GO

CREATE PROCEDURE dbo.SP_GetTipeMemberStats
AS
BEGIN
    SET NOCOUNT ON;

    SELECT 
        SUM(CASE WHEN Status = 1 THEN 1 ELSE 0 END) AS Aktif,
        SUM(CASE WHEN Status = 0 THEN 1 ELSE 0 END) AS Nonaktif,
        COUNT(*) AS Total
    FROM Tipe_Member
    WHERE Is_Deleted = 0;
END;
GO

-- ============================================================
-- 8. SP_GetTipeMemberWithPaging
--    Mengambil data tipe member dengan pagination
-- ============================================================
IF OBJECT_ID('dbo.SP_GetTipeMemberWithPaging', 'P') IS NOT NULL
    DROP PROCEDURE dbo.SP_GetTipeMemberWithPaging;
GO

CREATE PROCEDURE dbo.SP_GetTipeMemberWithPaging
    @PageNumber INT = 1,
    @PageSize   INT = 10,
    @Status     INT = NULL,
    @SortBy     VARCHAR(50) = 'Nama_Tipe ASC'
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE @Offset INT = (@PageNumber - 1) * @PageSize;
    DECLARE @SQL NVARCHAR(MAX);

    SET @SQL = N'
    SELECT 
        ID_Tipe,
        Nama_Tipe,
        Harga_Member,
        Potongan_Harga,
        Status,
        Is_Deleted,
        Created_By,
        Created_Date,
        Modified_By,
        Modified_Date
    FROM Tipe_Member
    WHERE Is_Deleted = 0
      AND (@Status IS NULL OR Status = @Status)
    ORDER BY ' + @SortBy + '
    OFFSET @Offset ROWS
    FETCH NEXT @PageSize ROWS ONLY;';

    EXEC sp_executesql @SQL, 
        N'@Status INT, @Offset INT, @PageSize INT',
        @Status = @Status, @Offset = @Offset, @PageSize = @PageSize;
END;
GO

-- ============================================================
-- 9. SP_GetTipeMemberCount
--    Menghitung total data tipe member (untuk pagination)
-- ============================================================
IF OBJECT_ID('dbo.SP_GetTipeMemberCount', 'P') IS NOT NULL
    DROP PROCEDURE dbo.SP_GetTipeMemberCount;
GO

CREATE PROCEDURE dbo.SP_GetTipeMemberCount
    @Status INT = NULL
AS
BEGIN
    SET NOCOUNT ON;

    SELECT COUNT(*) AS Total
    FROM Tipe_Member
    WHERE Is_Deleted = 0
      AND (@Status IS NULL OR Status = @Status);
END;
GO

-- ============================================================
-- 10. SP_RestoreTipeMember
--    Mengembalikan tipe member yang sudah di-soft-delete
-- ============================================================
IF OBJECT_ID('dbo.SP_RestoreTipeMember', 'P') IS NOT NULL
    DROP PROCEDURE dbo.SP_RestoreTipeMember;
GO

CREATE PROCEDURE dbo.SP_RestoreTipeMember
    @ID_Tipe INT
AS
BEGIN
    SET NOCOUNT ON;

    IF NOT EXISTS (SELECT 1 FROM Tipe_Member WHERE ID_Tipe = @ID_Tipe AND Is_Deleted = 1)
    BEGIN
        RAISERROR('Data tipe member yang dihapus tidak ditemukan.', 16, 1);
        RETURN;
    END;

    UPDATE Tipe_Member
    SET Is_Deleted = 0,
        Deleted_By = NULL,
        Deleted_Date = NULL,
        Status = 1
    WHERE ID_Tipe = @ID_Tipe;
END;
GO

-- ============================================================
-- 11. SP_SearchTipeMember
--    Mencari tipe member berdasarkan nama
-- ============================================================
IF OBJECT_ID('dbo.SP_SearchTipeMember', 'P') IS NOT NULL
    DROP PROCEDURE dbo.SP_SearchTipeMember;
GO

CREATE PROCEDURE dbo.SP_SearchTipeMember
    @Keyword VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;

    SELECT 
        ID_Tipe,
        Nama_Tipe,
        Harga_Member,
        Potongan_Harga,
        Status,
        Is_Deleted,
        Created_By,
        Created_Date,
        Modified_By,
        Modified_Date
    FROM Tipe_Member
    WHERE Is_Deleted = 0
      AND Nama_Tipe LIKE '%' + @Keyword + '%'
    ORDER BY Nama_Tipe ASC;
END;
GO

-- ============================================================
-- VERIFIKASI: Tampilkan daftar stored procedure yang dibuat
-- ============================================================
SELECT 
    ROUTINE_NAME AS Nama_SP,
    ROUTINE_TYPE AS Tipe
FROM INFORMATION_SCHEMA.ROUTINES
WHERE ROUTINE_SCHEMA = 'dbo'
  AND ROUTINE_NAME LIKE 'SP_%TipeMember%'
ORDER BY ROUTINE_NAME;
GO