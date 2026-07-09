
-- ============================================================
-- STORED PROCEDURES MASTER: KARYAWAN
-- Database: Hoopball
-- ============================================================

USE Hoopball;
GO

-- ============================================================
-- 1. SP: INSERT KARYAWAN
-- ============================================================
CREATE PROCEDURE sp_Karyawan_Insert
    @NIK             VARCHAR(16),
    @Nama_Karyawan   VARCHAR(20),
    @Tanggal_Lahir   DATE,
    @Tempat_Lahir    VARCHAR(50),
    @Alamat          VARCHAR(100),
    @Jenis_Kelamin   INT,
    @Jabatan         INT,
    @No_Telepon      VARCHAR(15),
    @Email           VARCHAR(50),
    @Username        VARCHAR(20),
    @Kata_Sandi      VARCHAR(20),
    @Status          INT,
    @Photo_Profile   VARCHAR(255) = NULL,
    @Created_By      VARCHAR(50),
    @ID_Karyawan     INT OUTPUT
AS
BEGIN
    SET NOCOUNT ON;

    INSERT INTO Karyawan
    (NIK, Nama_Karyawan, Tanggal_Lahir, Tempat_Lahir, Alamat, Jenis_Kelamin, 
     Jabatan, No_Telepon, Email, Username, Kata_Sandi, Status, Photo_Profile,
     Is_Deleted, Created_By, Created_Date)
    VALUES
    (@NIK, @Nama_Karyawan, @Tanggal_Lahir, @Tempat_Lahir, @Alamat, @Jenis_Kelamin,
     @Jabatan, @No_Telepon, @Email, @Username, @Kata_Sandi, @Status, @Photo_Profile,
     0, @Created_By, GETDATE());

    SET @ID_Karyawan = SCOPE_IDENTITY();
END;
GO

-- ============================================================
-- 2. SP: UPDATE KARYAWAN
-- ============================================================
CREATE PROCEDURE sp_Karyawan_Update
    @ID_Karyawan     INT,
    @NIK             VARCHAR(16),
    @Nama_Karyawan   VARCHAR(20),
    @Tanggal_Lahir   DATE,
    @Tempat_Lahir    VARCHAR(50),
    @Alamat          VARCHAR(100),
    @Jenis_Kelamin   INT,
    @Jabatan         INT,
    @No_Telepon      VARCHAR(15),
    @Email           VARCHAR(50),
    @Username        VARCHAR(20),
    @Kata_Sandi      VARCHAR(20),
    @Status          INT,
    @Photo_Profile   VARCHAR(255) = NULL,
    @Modified_By     VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;

    UPDATE Karyawan
    SET NIK           = @NIK,
        Nama_Karyawan = @Nama_Karyawan,
        Tanggal_Lahir = @Tanggal_Lahir,
        Tempat_Lahir  = @Tempat_Lahir,
        Alamat        = @Alamat,
        Jenis_Kelamin = @Jenis_Kelamin,
        Jabatan       = @Jabatan,
        No_Telepon    = @No_Telepon,
        Email         = @Email,
        Username      = @Username,
        Kata_Sandi    = @Kata_Sandi,
        Status        = @Status,
        Photo_Profile = @Photo_Profile,
        Modified_By   = @Modified_By,
        Modified_Date = GETDATE()
    WHERE ID_Karyawan = @ID_Karyawan AND Is_Deleted = 0;
END;
GO

-- ============================================================
-- 3. SP: SOFT DELETE KARYAWAN
-- ============================================================
CREATE PROCEDURE sp_Karyawan_Delete
    @ID_Karyawan INT,
    @Deleted_By  VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;

    UPDATE Karyawan
    SET Is_Deleted  = 1,
        Deleted_By  = @Deleted_By,
        Deleted_Date = GETDATE()
    WHERE ID_Karyawan = @ID_Karyawan AND Is_Deleted = 0;
END;
GO

-- ============================================================
-- 4. SP: TOGGLE STATUS KARYAWAN
-- ============================================================
CREATE PROCEDURE sp_Karyawan_ToggleStatus
    @ID_Karyawan INT,
    @Modified_By VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE @CurrentStatus INT;
    DECLARE @NewStatus INT;
    DECLARE @StatusLabel VARCHAR(10);

    SELECT @CurrentStatus = Status 
    FROM Karyawan 
    WHERE ID_Karyawan = @ID_Karyawan AND Is_Deleted = 0;

    IF @CurrentStatus IS NULL
    BEGIN
        SELECT 'Tidak Ditemukan' AS StatusLabel;
        RETURN;
    END

    SET @NewStatus = CASE WHEN @CurrentStatus = 1 THEN 0 ELSE 1 END;
    SET @StatusLabel = CASE WHEN @NewStatus = 1 THEN 'Aktif' ELSE 'Nonaktif' END;

    UPDATE Karyawan
    SET Status      = @NewStatus,
        Modified_By = @Modified_By,
        Modified_Date = GETDATE()
    WHERE ID_Karyawan = @ID_Karyawan AND Is_Deleted = 0;

    SELECT @StatusLabel AS StatusLabel;
END;
GO

-- ============================================================
-- 5. SP: GET KARYAWAN BY ID
-- ============================================================
CREATE PROCEDURE sp_Karyawan_GetByID
    @ID_Karyawan INT
AS
BEGIN
    SET NOCOUNT ON;

    SELECT 
        ID_Karyawan,
        NIK,
        Nama_Karyawan,
        Tanggal_Lahir,
        Tempat_Lahir,
        Alamat,
        Jenis_Kelamin,
        Jabatan,
        No_Telepon,
        Email,
        Username,
        Kata_Sandi,
        Status,
        Photo_Profile,
        Is_Deleted,
        Created_By,
        Created_Date,
        Modified_By,
        Modified_Date
    FROM Karyawan
    WHERE ID_Karyawan = @ID_Karyawan AND Is_Deleted = 0;
END;
GO

-- ============================================================
-- 6. SP: GET ALL KARYAWAN (DENGAN FILTER, SORTING, PAGINATION)
-- ============================================================
CREATE PROCEDURE sp_Karyawan_GetAll
    @Filter_Jabatan  INT = 0,      -- 0 = Semua, 1 = Karyawan, 2 = Manajer
    @Filter_JK       INT = -1,     -- -1 = Semua, 0 = Perempuan, 1 = Laki-laki
    @Filter_Status   INT = -1,     -- -1 = Semua, 0 = Nonaktif, 1 = Aktif
    @Sort_By         VARCHAR(30) = 'Nama_Karyawan',
    @Sort_Order      VARCHAR(4)  = 'ASC',
    @Page            INT = 1,
    @Limit           INT = 10
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE @Offset INT = (@Page - 1) * @Limit;

    -- Validasi sort_by untuk mencegah SQL injection
    IF @Sort_By NOT IN ('Nama_Karyawan', 'Jabatan', 'Jenis_Kelamin', 'No_Telepon', 
                        'Status', 'Created_Date', 'Modified_Date', 'NIK')
        SET @Sort_By = 'Nama_Karyawan';

    IF @Sort_Order NOT IN ('ASC', 'DESC')
        SET @Sort_Order = 'ASC';

    SELECT 
        ID_Karyawan,
        NIK,
        Nama_Karyawan,
        Tanggal_Lahir,
        Tempat_Lahir,
        Alamat,
        Jenis_Kelamin,
        Jabatan,
        No_Telepon,
        Email,
        Username,
        Kata_Sandi,
        Status,
        Photo_Profile,
        Created_By,
        Created_Date,
        Modified_By,
        Modified_Date
    FROM Karyawan
    WHERE Is_Deleted = 0
      AND (@Filter_Jabatan = 0 OR Jabatan = @Filter_Jabatan)
      AND (@Filter_JK = -1 OR Jenis_Kelamin = @Filter_JK)
      AND (@Filter_Status = -1 OR Status = @Filter_Status)
    ORDER BY 
        CASE WHEN @Sort_By = 'Nama_Karyawan' AND @Sort_Order = 'ASC' THEN Nama_Karyawan END ASC,
        CASE WHEN @Sort_By = 'Nama_Karyawan' AND @Sort_Order = 'DESC' THEN Nama_Karyawan END DESC,
        CASE WHEN @Sort_By = 'Jabatan' AND @Sort_Order = 'ASC' THEN Jabatan END ASC,
        CASE WHEN @Sort_By = 'Jabatan' AND @Sort_Order = 'DESC' THEN Jabatan END DESC,
        CASE WHEN @Sort_By = 'Jenis_Kelamin' AND @Sort_Order = 'ASC' THEN Jenis_Kelamin END ASC,
        CASE WHEN @Sort_By = 'Jenis_Kelamin' AND @Sort_Order = 'DESC' THEN Jenis_Kelamin END DESC,
        CASE WHEN @Sort_By = 'No_Telepon' AND @Sort_Order = 'ASC' THEN No_Telepon END ASC,
        CASE WHEN @Sort_By = 'No_Telepon' AND @Sort_Order = 'DESC' THEN No_Telepon END DESC,
        CASE WHEN @Sort_By = 'Status' AND @Sort_Order = 'ASC' THEN Status END ASC,
        CASE WHEN @Sort_By = 'Status' AND @Sort_Order = 'DESC' THEN Status END DESC,
        CASE WHEN @Sort_By = 'Created_Date' AND @Sort_Order = 'ASC' THEN Created_Date END ASC,
        CASE WHEN @Sort_By = 'Created_Date' AND @Sort_Order = 'DESC' THEN Created_Date END DESC,
        CASE WHEN @Sort_By = 'Modified_Date' AND @Sort_Order = 'ASC' THEN Modified_Date END ASC,
        CASE WHEN @Sort_By = 'Modified_Date' AND @Sort_Order = 'DESC' THEN Modified_Date END DESC,
        CASE WHEN @Sort_By = 'NIK' AND @Sort_Order = 'ASC' THEN NIK END ASC,
        CASE WHEN @Sort_By = 'NIK' AND @Sort_Order = 'DESC' THEN NIK END DESC
    OFFSET @Offset ROWS FETCH NEXT @Limit ROWS ONLY;
END;
GO

-- ============================================================
-- 7. SP: GET TOTAL KARYAWAN (DENGAN FILTER)
-- ============================================================
CREATE PROCEDURE sp_Karyawan_GetTotal
    @Filter_Jabatan INT = 0,
    @Filter_JK      INT = -1,
    @Filter_Status  INT = -1
AS
BEGIN
    SET NOCOUNT ON;

    SELECT COUNT(*) AS Total
    FROM Karyawan
    WHERE Is_Deleted = 0
      AND (@Filter_Jabatan = 0 OR Jabatan = @Filter_Jabatan)
      AND (@Filter_JK = -1 OR Jenis_Kelamin = @Filter_JK)
      AND (@Filter_Status = -1 OR Status = @Filter_Status);
END;
GO

-- ============================================================
-- 8. SP: CHECK NIK (DUPLICATE VALIDATION)
-- ============================================================
CREATE PROCEDURE sp_Karyawan_CheckNIK
    @NIK        VARCHAR(16),
    @Exclude_ID INT = 0
AS
BEGIN
    SET NOCOUNT ON;

    SELECT 
        CASE WHEN EXISTS (
            SELECT 1 FROM Karyawan 
            WHERE NIK = @NIK 
              AND Is_Deleted = 0 
              AND (@Exclude_ID = 0 OR ID_Karyawan != @Exclude_ID)
        ) THEN 1 ELSE 0 END AS Exists_Flag;
END;
GO

-- ============================================================
-- 9. SP: CHECK USERNAME (DUPLICATE VALIDATION)
-- ============================================================
CREATE PROCEDURE sp_Karyawan_CheckUsername
    @Username   VARCHAR(20),
    @Exclude_ID INT = 0
AS
BEGIN
    SET NOCOUNT ON;

    SELECT 
        CASE WHEN EXISTS (
            SELECT 1 FROM Karyawan 
            WHERE Username = @Username 
              AND Is_Deleted = 0 
              AND (@Exclude_ID = 0 OR ID_Karyawan != @Exclude_ID)
        ) THEN 1 ELSE 0 END AS Exists_Flag;
END;
GO

-- ============================================================
-- 10. SP: CHECK EMAIL (DUPLICATE VALIDATION)
-- ============================================================
CREATE PROCEDURE sp_Karyawan_CheckEmail
    @Email      VARCHAR(50),
    @Exclude_ID INT = 0
AS
BEGIN
    SET NOCOUNT ON;

    SELECT 
        CASE WHEN EXISTS (
            SELECT 1 FROM Karyawan 
            WHERE Email = @Email 
              AND Is_Deleted = 0 
              AND (@Exclude_ID = 0 OR ID_Karyawan != @Exclude_ID)
        ) THEN 1 ELSE 0 END AS Exists_Flag;
END;
GO

-- ============================================================
-- 11. SP: CHECK NO TELEPON (DUPLICATE VALIDATION)
-- ============================================================
CREATE PROCEDURE sp_Karyawan_CheckTelp
    @No_Telepon VARCHAR(15),
    @Exclude_ID INT = 0
AS
BEGIN
    SET NOCOUNT ON;

    SELECT 
        CASE WHEN EXISTS (
            SELECT 1 FROM Karyawan 
            WHERE No_Telepon = @No_Telepon 
              AND Is_Deleted = 0 
              AND (@Exclude_ID = 0 OR ID_Karyawan != @Exclude_ID)
        ) THEN 1 ELSE 0 END AS Exists_Flag;
END;
GO

-- ============================================================
-- 12. SP: UPDATE PHOTO PROFILE
-- ============================================================
CREATE PROCEDURE sp_Karyawan_UpdatePhoto
    @ID_Karyawan   INT,
    @Photo_Profile VARCHAR(255),
    @Modified_By   VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;

    UPDATE Karyawan
    SET Photo_Profile = @Photo_Profile,
        Modified_By   = @Modified_By,
        Modified_Date = GETDATE()
    WHERE ID_Karyawan = @ID_Karyawan AND Is_Deleted = 0;
END;
GO

-- ============================================================
-- 13. SP: GET KARYAWAN BY JABATAN
-- ============================================================
CREATE PROCEDURE sp_Karyawan_GetByJabatan
    @Jabatan INT
AS
BEGIN
    SET NOCOUNT ON;

    SELECT 
        ID_Karyawan,
        NIK,
        Nama_Karyawan,
        Jabatan,
        Status,
        Photo_Profile
    FROM Karyawan
    WHERE Jabatan = @Jabatan AND Is_Deleted = 0 AND Status = 1
    ORDER BY Nama_Karyawan;
END;
GO

-- ============================================================
-- 14. SP: SEARCH KARYAWAN
-- ============================================================
CREATE PROCEDURE sp_Karyawan_Search
    @Keyword VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;

    SELECT 
        ID_Karyawan,
        NIK,
        Nama_Karyawan,
        Jabatan,
        Jenis_Kelamin,
        No_Telepon,
        Email,
        Status,
        Photo_Profile
    FROM Karyawan
    WHERE Is_Deleted = 0
      AND (
          Nama_Karyawan LIKE '%' + @Keyword + '%'
          OR NIK LIKE '%' + @Keyword + '%'
          OR Email LIKE '%' + @Keyword + '%'
          OR No_Telepon LIKE '%' + @Keyword + '%'
          OR Username LIKE '%' + @Keyword + '%'
      )
    ORDER BY Nama_Karyawan;
END;
GO

-- ============================================================
-- 15. UDF: GET TOTAL KARYAWAN AKTIF
-- ============================================================
CREATE FUNCTION fn_GetTotalKaryawanAktif()
RETURNS INT
AS
BEGIN
    DECLARE @Total INT;

    SELECT @Total = COUNT(*) 
    FROM Karyawan 
    WHERE Status = 1 AND Is_Deleted = 0;

    RETURN @Total;
END;
GO

-- ============================================================
-- 16. UDF: GET TOTAL KARYAWAN BY JABATAN
-- ============================================================
CREATE FUNCTION fn_GetTotalKaryawanByJabatan(@Jabatan INT)
RETURNS INT
AS
BEGIN
    DECLARE @Total INT;

    SELECT @Total = COUNT(*) 
    FROM Karyawan 
    WHERE Jabatan = @Jabatan AND Is_Deleted = 0;

    RETURN @Total;
END;
GO

-- ============================================================
-- 17. SP: GET KARYAWAN LOGIN (Untuk autentikasi)
-- ============================================================
CREATE PROCEDURE sp_Karyawan_Login
    @Username   VARCHAR(20),
    @Kata_Sandi VARCHAR(20)
AS
BEGIN
    SET NOCOUNT ON;

    SELECT 
        ID_Karyawan,
        NIK,
        Nama_Karyawan,
        Jabatan,
        Status,
        Photo_Profile
    FROM Karyawan
    WHERE Username = @Username 
      AND Kata_Sandi = @Kata_Sandi
      AND Is_Deleted = 0
      AND Status = 1;
END;
GO

-- ============================================================
-- 18. SP: RESTORE KARYAWAN (Undo Soft Delete)
-- ============================================================
CREATE PROCEDURE sp_Karyawan_Restore
    @ID_Karyawan INT,
    @Modified_By VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;

    UPDATE Karyawan
    SET Is_Deleted  = 0,
        Deleted_By  = NULL,
        Deleted_Date = NULL,
        Modified_By  = @Modified_By,
        Modified_Date = GETDATE()
    WHERE ID_Karyawan = @ID_Karyawan AND Is_Deleted = 1;
END;
GO

-- ============================================================
-- 19. SP: GET DELETED KARYAWAN (Recycle Bin)
-- ============================================================
CREATE PROCEDURE sp_Karyawan_GetDeleted
AS
BEGIN
    SET NOCOUNT ON;

    SELECT 
        ID_Karyawan,
        NIK,
        Nama_Karyawan,
        Jabatan,
        Status,
        Deleted_By,
        Deleted_Date,
        Photo_Profile
    FROM Karyawan
    WHERE Is_Deleted = 1
    ORDER BY Deleted_Date DESC;
END;
GO

-- ============================================================
-- 20. SP: CHANGE PASSWORD
-- ============================================================
CREATE PROCEDURE sp_Karyawan_ChangePassword
    @ID_Karyawan   INT,
    @Kata_Sandi    VARCHAR(20),
    @Modified_By   VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;

    UPDATE Karyawan
    SET Kata_Sandi  = @Kata_Sandi,
        Modified_By = @Modified_By,
        Modified_Date = GETDATE()
    WHERE ID_Karyawan = @ID_Karyawan AND Is_Deleted = 0;
END;
GO

PRINT 'Semua Stored Procedure Master Karyawan berhasil dibuat!';
GO