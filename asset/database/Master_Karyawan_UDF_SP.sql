-- ============================================================================
-- HOOPBALL DATABASE - STORED PROCEDURE, UDF, DAN TRIGGER
-- ============================================================================
-- Dibuat untuk: Project Basis Data - Kelompok 5
-- Requirement: Semua CRUD Master & Transaksi menggunakan SP
--              Laporan/Dashboard menggunakan UDF
--              Trigger untuk log history dan validasi bisnis
-- ============================================================================

USE Hoopball;
GO

-- ============================================================================
-- BAGIAN 1: USER DEFINED FUNCTIONS (UDF) - UNTUK LAPORAN/DASHBOARD
-- ============================================================================

-- ----------------------------------------------------------------------------
-- UDF 1: fn_GetTotalKaryawanAktif
-- Menghitung total karyawan yang statusnya aktif
-- ----------------------------------------------------------------------------
IF OBJECT_ID('dbo.fn_GetTotalKaryawanAktif', 'FN') IS NOT NULL
    DROP FUNCTION dbo.fn_GetTotalKaryawanAktif;
GO

CREATE FUNCTION dbo.fn_GetTotalKaryawanAktif()
RETURNS INT
AS
BEGIN
    DECLARE @total INT;
    SELECT @total = COUNT(*) FROM Karyawan WHERE Status = 1 AND Is_Deleted = 0;
    RETURN ISNULL(@total, 0);
END;
GO

-- ----------------------------------------------------------------------------
-- UDF 2: fn_GetTotalKaryawanByJabatan
-- Menghitung total karyawan berdasarkan jabatan (1=Karyawan, 2=Manajer)
-- ----------------------------------------------------------------------------
IF OBJECT_ID('dbo.fn_GetTotalKaryawanByJabatan', 'FN') IS NOT NULL
    DROP FUNCTION dbo.fn_GetTotalKaryawanByJabatan;
GO

CREATE FUNCTION dbo.fn_GetTotalKaryawanByJabatan(@Jabatan INT)
RETURNS INT
AS
BEGIN
    DECLARE @total INT;
    SELECT @total = COUNT(*) 
    FROM Karyawan 
    WHERE Jabatan = @Jabatan AND Status = 1 AND Is_Deleted = 0;
    RETURN ISNULL(@total, 0);
END;
GO

-- ----------------------------------------------------------------------------
-- UDF 3: fn_GetPersentaseKaryawanAktif
-- Menghitung persentase karyawan aktif dari total karyawan
-- ----------------------------------------------------------------------------
IF OBJECT_ID('dbo.fn_GetPersentaseKaryawanAktif', 'FN') IS NOT NULL
    DROP FUNCTION dbo.fn_GetPersentaseKaryawanAktif;
GO

CREATE FUNCTION dbo.fn_GetPersentaseKaryawanAktif()
RETURNS DECIMAL(5,2)
AS
BEGIN
    DECLARE @total INT, @aktif INT, @persen DECIMAL(5,2);
    
    SELECT @total = COUNT(*) FROM Karyawan WHERE Is_Deleted = 0;
    SELECT @aktif = COUNT(*) FROM Karyawan WHERE Status = 1 AND Is_Deleted = 0;
    
    IF @total = 0
        RETURN 0.00;
    
    SET @persen = (CAST(@aktif AS DECIMAL(10,2)) / CAST(@total AS DECIMAL(10,2))) * 100;
    RETURN @persen;
END;
GO

-- ----------------------------------------------------------------------------
-- UDF 4: fn_GetKaryawanByID
-- Mengambil data karyawan lengkap berdasarkan ID (untuk detail view)
-- ----------------------------------------------------------------------------
IF OBJECT_ID('dbo.fn_GetKaryawanByID', 'FN') IS NOT NULL
    DROP FUNCTION dbo.fn_GetKaryawanByID;
GO

CREATE FUNCTION dbo.fn_GetKaryawanByID(@ID_Karyawan INT)
RETURNS TABLE
AS
RETURN
(
    SELECT 
        ID_Karyawan,
        NIK,
        Nama_Karyawan,
        Tanggal_Lahir,
        Tempat_Lahir,
        Alamat,
        CASE Jenis_Kelamin 
            WHEN 1 THEN 'Laki-laki' 
            WHEN 0 THEN 'Perempuan' 
            ELSE 'Tidak diketahui' 
        END AS Jenis_Kelamin_Label,
        Jenis_Kelamin,
        CASE Jabatan 
            WHEN 1 THEN 'Karyawan' 
            WHEN 2 THEN 'Manajer' 
            ELSE 'Tidak diketahui' 
        END AS Jabatan_Label,
        Jabatan,
        No_Telepon,
        Email,
        Username,
        Kata_Sandi,
        CASE Status 
            WHEN 1 THEN 'Aktif' 
            WHEN 0 THEN 'Nonaktif' 
            ELSE 'Tidak diketahui' 
        END AS Status_Label,
        Status,
        Photo_Profile,
        Is_Deleted,
        Created_By,
        Created_Date,
        Modified_By,
        Modified_Date,
        Deleted_By,
        Deleted_Date
    FROM Karyawan
    WHERE ID_Karyawan = @ID_Karyawan AND Is_Deleted = 0
);
GO

-- ----------------------------------------------------------------------------
-- UDF 5: fn_GetUmurKaryawan
-- Menghitung umur karyawan berdasarkan tanggal lahir
-- ----------------------------------------------------------------------------
IF OBJECT_ID('dbo.fn_GetUmurKaryawan', 'FN') IS NOT NULL
    DROP FUNCTION dbo.fn_GetUmurKaryawan;
GO

CREATE FUNCTION dbo.fn_GetUmurKaryawan(@Tanggal_Lahir DATE)
RETURNS INT
AS
BEGIN
    RETURN DATEDIFF(YEAR, @Tanggal_Lahir, GETDATE()) - 
           CASE 
               WHEN MONTH(@Tanggal_Lahir) > MONTH(GETDATE()) 
                    OR (MONTH(@Tanggal_Lahir) = MONTH(GETDATE()) AND DAY(@Tanggal_Lahir) > DAY(GETDATE())) 
               THEN 1 
               ELSE 0 
           END;
END;
GO

-- ----------------------------------------------------------------------------
-- UDF 6: fn_GetDashboardStats (Table-Valued Function)
-- Mengembalikan statistik dashboard untuk karyawan
-- ----------------------------------------------------------------------------
IF OBJECT_ID('dbo.fn_GetDashboardStats', 'FN') IS NOT NULL
    DROP FUNCTION dbo.fn_GetDashboardStats;
GO

CREATE FUNCTION dbo.fn_GetDashboardStats()
RETURNS TABLE
AS
RETURN
(
    SELECT 
        (SELECT COUNT(*) FROM Karyawan WHERE Is_Deleted = 0) AS Total_Karyawan,
        (SELECT COUNT(*) FROM Karyawan WHERE Status = 1 AND Is_Deleted = 0) AS Total_Aktif,
        (SELECT COUNT(*) FROM Karyawan WHERE Status = 0 AND Is_Deleted = 0) AS Total_Nonaktif,
        (SELECT COUNT(*) FROM Karyawan WHERE Jabatan = 2 AND Is_Deleted = 0) AS Total_Manajer,
        (SELECT COUNT(*) FROM Karyawan WHERE Jabatan = 1 AND Is_Deleted = 0) AS Total_Staff,
        (SELECT COUNT(*) FROM Karyawan WHERE Jenis_Kelamin = 1 AND Is_Deleted = 0) AS Total_Laki_Laki,
        (SELECT COUNT(*) FROM Karyawan WHERE Jenis_Kelamin = 0 AND Is_Deleted = 0) AS Total_Perempuan
);
GO

-- ----------------------------------------------------------------------------
-- UDF 7: fn_SearchKaryawan
-- Fungsi pencarian karyawan berdasarkan nama/NIK/username
-- ----------------------------------------------------------------------------
IF OBJECT_ID('dbo.fn_SearchKaryawan', 'FN') IS NOT NULL
    DROP FUNCTION dbo.fn_SearchKaryawan;
GO

CREATE FUNCTION dbo.fn_SearchKaryawan(@Keyword NVARCHAR(100))
RETURNS TABLE
AS
RETURN
(
    SELECT 
        ID_Karyawan,
        NIK,
        Nama_Karyawan,
        CASE Jabatan 
            WHEN 1 THEN 'Karyawan' 
            WHEN 2 THEN 'Manajer' 
        END AS Jabatan_Label,
        CASE Status 
            WHEN 1 THEN 'Aktif' 
            WHEN 0 THEN 'Nonaktif' 
        END AS Status_Label,
        No_Telepon,
        Email
    FROM Karyawan
    WHERE Is_Deleted = 0
      AND (
          Nama_Karyawan LIKE '%' + @Keyword + '%'
          OR NIK LIKE '%' + @Keyword + '%'
          OR Username LIKE '%' + @Keyword + '%'
          OR Email LIKE '%' + @Keyword + '%'
      )
);
GO

-- ============================================================================
-- BAGIAN 2: STORED PROCEDURES (SP) - CRUD MASTER KARYAWAN
-- ============================================================================

-- ----------------------------------------------------------------------------
-- SP 1: sp_Karyawan_Insert
-- Menambahkan karyawan baru dengan validasi
-- ----------------------------------------------------------------------------
IF OBJECT_ID('dbo.sp_Karyawan_Insert', 'P') IS NOT NULL
    DROP PROCEDURE dbo.sp_Karyawan_Insert;
GO

CREATE PROCEDURE dbo.sp_Karyawan_Insert
    @NIK            VARCHAR(16),
    @Nama_Karyawan  VARCHAR(20),
    @Tanggal_Lahir  DATE,
    @Tempat_Lahir   VARCHAR(50),
    @Alamat         VARCHAR(100),
    @Jenis_Kelamin  INT,
    @Jabatan        INT,
    @No_Telepon     VARCHAR(15),
    @Email          VARCHAR(50),
    @Username       VARCHAR(20),
    @Kata_Sandi     VARCHAR(20),
    @Status         INT = 1,
    @Photo_Profile  VARCHAR(255) = NULL,
    @Created_By     VARCHAR(50),
    @ID_Karyawan    INT OUTPUT
AS
BEGIN
    SET NOCOUNT ON;
    
    BEGIN TRY
        -- Validasi: NIK harus 16 digit angka
        IF LEN(@NIK) != 16 OR @NIK LIKE '%[^0-9]%'
        BEGIN
            RAISERROR('NIK harus 16 digit angka!', 16, 1);
            RETURN;
        END
        
        -- Validasi: Umur minimal 17 tahun
        IF DATEDIFF(YEAR, @Tanggal_Lahir, GETDATE()) < 17
        BEGIN
            RAISERROR('Karyawan harus berusia minimal 17 tahun!', 16, 1);
            RETURN;
        END
        
        -- Validasi: Umur maksimal 60 tahun
        IF DATEDIFF(YEAR, @Tanggal_Lahir, GETDATE()) > 60
        BEGIN
            RAISERROR('Usia karyawan maksimal 60 tahun!', 16, 1);
            RETURN;
        END
        
        -- Validasi: Jenis Kelamin hanya 0 atau 1
        IF @Jenis_Kelamin NOT IN (0, 1)
        BEGIN
            RAISERROR('Jenis Kelamin hanya boleh 0 (Perempuan) atau 1 (Laki-laki)!', 16, 1);
            RETURN;
        END
        
        -- Validasi: Jabatan hanya 1 atau 2
        IF @Jabatan NOT IN (1, 2)
        BEGIN
            RAISERROR('Jabatan hanya boleh 1 (Karyawan) atau 2 (Manajer)!', 16, 1);
            RETURN;
        END
        
        -- Validasi: Status hanya 0 atau 1
        IF @Status NOT IN (0, 1)
        BEGIN
            RAISERROR('Status hanya boleh 0 (Nonaktif) atau 1 (Aktif)!', 16, 1);
            RETURN;
        END
        
        -- Validasi: NIK unik
        IF EXISTS(SELECT 1 FROM Karyawan WHERE NIK = @NIK AND Is_Deleted = 0)
        BEGIN
            RAISERROR('NIK sudah terdaftar di sistem!', 16, 1);
            RETURN;
        END
        
        -- Validasi: Username unik
        IF EXISTS(SELECT 1 FROM Karyawan WHERE Username = @Username AND Is_Deleted = 0)
        BEGIN
            RAISERROR('Username sudah terdaftar di sistem!', 16, 1);
            RETURN;
        END
        
        -- Validasi: Email unik
        IF EXISTS(SELECT 1 FROM Karyawan WHERE Email = @Email AND Is_Deleted = 0)
        BEGIN
            RAISERROR('Email sudah terdaftar di sistem!', 16, 1);
            RETURN;
        END
        
        -- Validasi: No Telepon unik
        IF EXISTS(SELECT 1 FROM Karyawan WHERE No_Telepon = @No_Telepon AND Is_Deleted = 0)
        BEGIN
            RAISERROR('Nomor telepon sudah terdaftar di sistem!', 16, 1);
            RETURN;
        END
        
        -- Insert data
        INSERT INTO Karyawan (
            NIK, Nama_Karyawan, Tanggal_Lahir, Tempat_Lahir, Alamat,
            Jenis_Kelamin, Jabatan, No_Telepon, Email, Username,
            Kata_Sandi, Status, Photo_Profile, Is_Deleted,
            Created_By, Created_Date
        )
        VALUES (
            @NIK, @Nama_Karyawan, @Tanggal_Lahir, @Tempat_Lahir, @Alamat,
            @Jenis_Kelamin, @Jabatan, @No_Telepon, @Email, @Username,
            @Kata_Sandi, @Status, @Photo_Profile, 0,
            @Created_By, GETDATE()
        );
        
        SET @ID_Karyawan = SCOPE_IDENTITY();
        
        SELECT 'Karyawan berhasil ditambahkan!' AS Message, @ID_Karyawan AS ID_Karyawan;
        
    END TRY
    BEGIN CATCH
        SELECT 
            ERROR_NUMBER() AS ErrorNumber,
            ERROR_MESSAGE() AS ErrorMessage,
            'Gagal menambahkan karyawan!' AS Message;
    END CATCH
END;
GO

-- ----------------------------------------------------------------------------
-- SP 2: sp_Karyawan_Update
-- Memperbarui data karyawan dengan validasi
-- ----------------------------------------------------------------------------
IF OBJECT_ID('dbo.sp_Karyawan_Update', 'P') IS NOT NULL
    DROP PROCEDURE dbo.sp_Karyawan_Update;
GO

CREATE PROCEDURE dbo.sp_Karyawan_Update
    @ID_Karyawan    INT,
    @NIK            VARCHAR(16),
    @Nama_Karyawan  VARCHAR(20),
    @Tanggal_Lahir  DATE,
    @Tempat_Lahir   VARCHAR(50),
    @Alamat         VARCHAR(100),
    @Jenis_Kelamin  INT,
    @Jabatan        INT,
    @No_Telepon     VARCHAR(15),
    @Email          VARCHAR(50),
    @Username       VARCHAR(20),
    @Kata_Sandi     VARCHAR(20),
    @Status         INT,
    @Photo_Profile  VARCHAR(255) = NULL,
    @Modified_By    VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;
    
    BEGIN TRY
        -- Cek apakah karyawan exists
        IF NOT EXISTS(SELECT 1 FROM Karyawan WHERE ID_Karyawan = @ID_Karyawan AND Is_Deleted = 0)
        BEGIN
            RAISERROR('Data karyawan tidak ditemukan!', 16, 1);
            RETURN;
        END
        
        -- Validasi: NIK harus 16 digit angka
        IF LEN(@NIK) != 16 OR @NIK LIKE '%[^0-9]%'
        BEGIN
            RAISERROR('NIK harus 16 digit angka!', 16, 1);
            RETURN;
        END
        
        -- Validasi: Umur minimal 17 tahun
        IF DATEDIFF(YEAR, @Tanggal_Lahir, GETDATE()) < 17
        BEGIN
            RAISERROR('Karyawan harus berusia minimal 17 tahun!', 16, 1);
            RETURN;
        END
        
        -- Validasi: Umur maksimal 60 tahun
        IF DATEDIFF(YEAR, @Tanggal_Lahir, GETDATE()) > 60
        BEGIN
            RAISERROR('Usia karyawan maksimal 60 tahun!', 16, 1);
            RETURN;
        END
        
        -- Validasi: NIK unik (exclude current record)
        IF EXISTS(SELECT 1 FROM Karyawan WHERE NIK = @NIK AND ID_Karyawan != @ID_Karyawan AND Is_Deleted = 0)
        BEGIN
            RAISERROR('NIK sudah terdaftar di sistem!', 16, 1);
            RETURN;
        END
        
        -- Validasi: Username unik (exclude current record)
        IF EXISTS(SELECT 1 FROM Karyawan WHERE Username = @Username AND ID_Karyawan != @ID_Karyawan AND Is_Deleted = 0)
        BEGIN
            RAISERROR('Username sudah terdaftar di sistem!', 16, 1);
            RETURN;
        END
        
        -- Validasi: Email unik (exclude current record)
        IF EXISTS(SELECT 1 FROM Karyawan WHERE Email = @Email AND ID_Karyawan != @ID_Karyawan AND Is_Deleted = 0)
        BEGIN
            RAISERROR('Email sudah terdaftar di sistem!', 16, 1);
            RETURN;
        END
        
        -- Validasi: No Telepon unik (exclude current record)
        IF EXISTS(SELECT 1 FROM Karyawan WHERE No_Telepon = @No_Telepon AND ID_Karyawan != @ID_Karyawan AND Is_Deleted = 0)
        BEGIN
            RAISERROR('Nomor telepon sudah terdaftar di sistem!', 16, 1);
            RETURN;
        END
        
        -- Update data
        UPDATE Karyawan
        SET 
            NIK = @NIK,
            Nama_Karyawan = @Nama_Karyawan,
            Tanggal_Lahir = @Tanggal_Lahir,
            Tempat_Lahir = @Tempat_Lahir,
            Alamat = @Alamat,
            Jenis_Kelamin = @Jenis_Kelamin,
            Jabatan = @Jabatan,
            No_Telepon = @No_Telepon,
            Email = @Email,
            Username = @Username,
            Kata_Sandi = @Kata_Sandi,
            Status = @Status,
            Photo_Profile = ISNULL(@Photo_Profile, Photo_Profile),
            Modified_By = @Modified_By,
            Modified_Date = GETDATE()
        WHERE ID_Karyawan = @ID_Karyawan;
        
        SELECT 'Data karyawan berhasil diperbarui!' AS Message;
        
    END TRY
    BEGIN CATCH
        SELECT 
            ERROR_NUMBER() AS ErrorNumber,
            ERROR_MESSAGE() AS ErrorMessage,
            'Gagal memperbarui data karyawan!' AS Message;
    END CATCH
END;
GO

-- ----------------------------------------------------------------------------
-- SP 3: sp_Karyawan_Delete (Soft Delete)
-- Menghapus karyawan secara soft delete
-- ----------------------------------------------------------------------------
IF OBJECT_ID('dbo.sp_Karyawan_Delete', 'P') IS NOT NULL
    DROP PROCEDURE dbo.sp_Karyawan_Delete;
GO

CREATE PROCEDURE dbo.sp_Karyawan_Delete
    @ID_Karyawan    INT,
    @Deleted_By     VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;
    
    BEGIN TRY
        -- Cek apakah karyawan exists
        IF NOT EXISTS(SELECT 1 FROM Karyawan WHERE ID_Karyawan = @ID_Karyawan AND Is_Deleted = 0)
        BEGIN
            RAISERROR('Data karyawan tidak ditemukan!', 16, 1);
            RETURN;
        END
        
        -- Soft delete: set Is_Deleted = 1 dan Status = 0
        UPDATE Karyawan
        SET 
            Is_Deleted = 1,
            Status = 0,
            Deleted_By = @Deleted_By,
            Deleted_Date = GETDATE()
        WHERE ID_Karyawan = @ID_Karyawan;
        
        SELECT 'Data karyawan telah dihapus!' AS Message;
        
    END TRY
    BEGIN CATCH
        SELECT 
            ERROR_NUMBER() AS ErrorNumber,
            ERROR_MESSAGE() AS ErrorMessage,
            'Gagal menghapus data karyawan!' AS Message;
    END CATCH
END;
GO

-- ----------------------------------------------------------------------------
-- SP 4: sp_Karyawan_ToggleStatus
-- Mengubah status aktif/nonaktif karyawan
-- ----------------------------------------------------------------------------
IF OBJECT_ID('dbo.sp_Karyawan_ToggleStatus', 'P') IS NOT NULL
    DROP PROCEDURE dbo.sp_Karyawan_ToggleStatus;
GO

CREATE PROCEDURE dbo.sp_Karyawan_ToggleStatus
    @ID_Karyawan    INT,
    @Modified_By    VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;
    
    BEGIN TRY
        DECLARE @CurrentStatus INT, @NewStatus INT;
        
        -- Cek apakah karyawan exists
        IF NOT EXISTS(SELECT 1 FROM Karyawan WHERE ID_Karyawan = @ID_Karyawan AND Is_Deleted = 0)
        BEGIN
            RAISERROR('Data karyawan tidak ditemukan!', 16, 1);
            RETURN;
        END
        
        -- Ambil status saat ini
        SELECT @CurrentStatus = Status FROM Karyawan WHERE ID_Karyawan = @ID_Karyawan;
        SET @NewStatus = CASE WHEN @CurrentStatus = 1 THEN 0 ELSE 1 END;
        
        -- Update status
        UPDATE Karyawan
        SET 
            Status = @NewStatus,
            Modified_By = @Modified_By,
            Modified_Date = GETDATE()
        WHERE ID_Karyawan = @ID_Karyawan;
        
        SELECT 
            'Status karyawan berhasil diubah!' AS Message,
            @NewStatus AS NewStatus,
            CASE WHEN @NewStatus = 1 THEN 'Aktif' ELSE 'Nonaktif' END AS StatusLabel;
        
    END TRY
    BEGIN CATCH
        SELECT 
            ERROR_NUMBER() AS ErrorNumber,
            ERROR_MESSAGE() AS ErrorMessage,
            'Gagal mengubah status karyawan!' AS Message;
    END CATCH
END;
GO

-- ----------------------------------------------------------------------------
-- SP 5: sp_Karyawan_GetAll
-- Mengambil semua data karyawan (dengan filter, sort, dan pagination)
-- ----------------------------------------------------------------------------
IF OBJECT_ID('dbo.sp_Karyawan_GetAll', 'P') IS NOT NULL
    DROP PROCEDURE dbo.sp_Karyawan_GetAll;
GO

CREATE PROCEDURE dbo.sp_Karyawan_GetAll
    @Filter_Jabatan     INT = 0,        -- 0 = Semua, 1 = Karyawan, 2 = Manajer
    @Filter_JK          INT = -1,       -- -1 = Semua, 0 = Perempuan, 1 = Laki-laki
    @Filter_Status      INT = -1,       -- -1 = Semua, 0 = Nonaktif, 1 = Aktif
    @Sort_By            VARCHAR(50) = 'Nama_Karyawan',
    @Sort_Order         VARCHAR(4) = 'ASC',
    @Page               INT = 1,
    @Limit              INT = 10
AS
BEGIN
    SET NOCOUNT ON;
    
    DECLARE @Offset INT = (@Page - 1) * @Limit;
    DECLARE @SQL NVARCHAR(MAX);
    DECLARE @ParamDef NVARCHAR(500);
    
    SET @SQL = N'
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
    WHERE Is_Deleted = 0';
    
    -- Filter Jabatan
    IF @Filter_Jabatan > 0
        SET @SQL = @SQL + N' AND Jabatan = @Jabatan';
    
    -- Filter Jenis Kelamin
    IF @Filter_JK >= 0
        SET @SQL = @SQL + N' AND Jenis_Kelamin = @JK';
    
    -- Filter Status
    IF @Filter_Status >= 0
        SET @SQL = @SQL + N' AND Status = @Status';
    
    -- Sorting
    SET @SQL = @SQL + N' ORDER BY ' + QUOTENAME(@Sort_By) + N' ' + @Sort_Order;
    
    -- Pagination
    SET @SQL = @SQL + N' OFFSET @Offset ROWS FETCH NEXT @Limit ROWS ONLY';
    
    SET @ParamDef = N'@Jabatan INT, @JK INT, @Status INT, @Offset INT, @Limit INT';
    
    EXEC sp_executesql @SQL, @ParamDef, 
        @Jabatan = @Filter_Jabatan, 
        @JK = @Filter_JK, 
        @Status = @Filter_Status,
        @Offset = @Offset,
        @Limit = @Limit;
END;
GO

-- ----------------------------------------------------------------------------
-- SP 6: sp_Karyawan_GetByID
-- Mengambil data karyawan berdasarkan ID
-- ----------------------------------------------------------------------------
IF OBJECT_ID('dbo.sp_Karyawan_GetByID', 'P') IS NOT NULL
    DROP PROCEDURE dbo.sp_Karyawan_GetByID;
GO

CREATE PROCEDURE dbo.sp_Karyawan_GetByID
    @ID_Karyawan INT
AS
BEGIN
    SET NOCOUNT ON;
    
    SELECT * FROM dbo.fn_GetKaryawanByID(@ID_Karyawan);
END;
GO

-- ----------------------------------------------------------------------------
-- SP 7: sp_Karyawan_GetTotal
-- Mengambil total data karyawan untuk pagination
-- ----------------------------------------------------------------------------
IF OBJECT_ID('dbo.sp_Karyawan_GetTotal', 'P') IS NOT NULL
    DROP PROCEDURE dbo.sp_Karyawan_GetTotal;
GO

CREATE PROCEDURE dbo.sp_Karyawan_GetTotal
    @Filter_Jabatan     INT = 0,
    @Filter_JK          INT = -1,
    @Filter_Status      INT = -1
AS
BEGIN
    SET NOCOUNT ON;
    
    DECLARE @SQL NVARCHAR(MAX);
    DECLARE @ParamDef NVARCHAR(500);
    
    SET @SQL = N'SELECT COUNT(*) AS Total FROM Karyawan WHERE Is_Deleted = 0';
    
    IF @Filter_Jabatan > 0
        SET @SQL = @SQL + N' AND Jabatan = @Jabatan';
    
    IF @Filter_JK >= 0
        SET @SQL = @SQL + N' AND Jenis_Kelamin = @JK';
    
    IF @Filter_Status >= 0
        SET @SQL = @SQL + N' AND Status = @Status';
    
    SET @ParamDef = N'@Jabatan INT, @JK INT, @Status INT';
    
    EXEC sp_executesql @SQL, @ParamDef, 
        @Jabatan = @Filter_Jabatan, 
        @JK = @Filter_JK, 
        @Status = @Filter_Status;
END;
GO

-- ----------------------------------------------------------------------------
-- SP 8: sp_Karyawan_CheckNIK
-- Mengecek apakah NIK sudah terdaftar (untuk validasi real-time)
-- ----------------------------------------------------------------------------
IF OBJECT_ID('dbo.sp_Karyawan_CheckNIK', 'P') IS NOT NULL
    DROP PROCEDURE dbo.sp_Karyawan_CheckNIK;
GO

CREATE PROCEDURE dbo.sp_Karyawan_CheckNIK
    @NIK            VARCHAR(16),
    @Exclude_ID     INT = 0
AS
BEGIN
    SET NOCOUNT ON;
    
    IF @Exclude_ID > 0
    BEGIN
        SELECT CASE WHEN EXISTS(
            SELECT 1 FROM Karyawan 
            WHERE NIK = @NIK AND ID_Karyawan != @Exclude_ID AND Is_Deleted = 0
        ) THEN 1 ELSE 0 END AS Exists_Flag;
    END
    ELSE
    BEGIN
        SELECT CASE WHEN EXISTS(
            SELECT 1 FROM Karyawan 
            WHERE NIK = @NIK AND Is_Deleted = 0
        ) THEN 1 ELSE 0 END AS Exists_Flag;
    END
END;
GO

-- ----------------------------------------------------------------------------
-- SP 9: sp_Karyawan_CheckUsername
-- Mengecek apakah Username sudah terdaftar
-- ----------------------------------------------------------------------------
IF OBJECT_ID('dbo.sp_Karyawan_CheckUsername', 'P') IS NOT NULL
    DROP PROCEDURE dbo.sp_Karyawan_CheckUsername;
GO

CREATE PROCEDURE dbo.sp_Karyawan_CheckUsername
    @Username       VARCHAR(20),
    @Exclude_ID     INT = 0
AS
BEGIN
    SET NOCOUNT ON;
    
    IF @Exclude_ID > 0
        SELECT CASE WHEN EXISTS(
            SELECT 1 FROM Karyawan 
            WHERE Username = @Username AND ID_Karyawan != @Exclude_ID AND Is_Deleted = 0
        ) THEN 1 ELSE 0 END AS Exists_Flag;
    ELSE
        SELECT CASE WHEN EXISTS(
            SELECT 1 FROM Karyawan 
            WHERE Username = @Username AND Is_Deleted = 0
        ) THEN 1 ELSE 0 END AS Exists_Flag;
END;
GO

-- ----------------------------------------------------------------------------
-- SP 10: sp_Karyawan_CheckEmail
-- Mengecek apakah Email sudah terdaftar
-- ----------------------------------------------------------------------------
IF OBJECT_ID('dbo.sp_Karyawan_CheckEmail', 'P') IS NOT NULL
    DROP PROCEDURE dbo.sp_Karyawan_CheckEmail;
GO

CREATE PROCEDURE dbo.sp_Karyawan_CheckEmail
    @Email          VARCHAR(50),
    @Exclude_ID     INT = 0
AS
BEGIN
    SET NOCOUNT ON;
    
    IF @Exclude_ID > 0
        SELECT CASE WHEN EXISTS(
            SELECT 1 FROM Karyawan 
            WHERE Email = @Email AND ID_Karyawan != @Exclude_ID AND Is_Deleted = 0
        ) THEN 1 ELSE 0 END AS Exists_Flag;
    ELSE
        SELECT CASE WHEN EXISTS(
            SELECT 1 FROM Karyawan 
            WHERE Email = @Email AND Is_Deleted = 0
        ) THEN 1 ELSE 0 END AS Exists_Flag;
END;
GO

-- ----------------------------------------------------------------------------
-- SP 11: sp_Karyawan_CheckTelp
-- Mengecek apakah No Telepon sudah terdaftar
-- ----------------------------------------------------------------------------
IF OBJECT_ID('dbo.sp_Karyawan_CheckTelp', 'P') IS NOT NULL
    DROP PROCEDURE dbo.sp_Karyawan_CheckTelp;
GO

CREATE PROCEDURE dbo.sp_Karyawan_CheckTelp
    @No_Telepon     VARCHAR(15),
    @Exclude_ID     INT = 0
AS
BEGIN
    SET NOCOUNT ON;
    
    IF @Exclude_ID > 0
        SELECT CASE WHEN EXISTS(
            SELECT 1 FROM Karyawan 
            WHERE No_Telepon = @No_Telepon AND ID_Karyawan != @Exclude_ID AND Is_Deleted = 0
        ) THEN 1 ELSE 0 END AS Exists_Flag;
    ELSE
        SELECT CASE WHEN EXISTS(
            SELECT 1 FROM Karyawan 
            WHERE No_Telepon = @No_Telepon AND Is_Deleted = 0
        ) THEN 1 ELSE 0 END AS Exists_Flag;
END;
GO

-- ----------------------------------------------------------------------------
-- SP 12: sp_Karyawan_UpdatePhoto
-- Update foto profil karyawan
-- ----------------------------------------------------------------------------
IF OBJECT_ID('dbo.sp_Karyawan_UpdatePhoto', 'P') IS NOT NULL
    DROP PROCEDURE dbo.sp_Karyawan_UpdatePhoto;
GO

CREATE PROCEDURE dbo.sp_Karyawan_UpdatePhoto
    @ID_Karyawan    INT,
    @Photo_Profile  VARCHAR(255),
    @Modified_By    VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;
    
    BEGIN TRY
        IF NOT EXISTS(SELECT 1 FROM Karyawan WHERE ID_Karyawan = @ID_Karyawan AND Is_Deleted = 0)
        BEGIN
            RAISERROR('Data karyawan tidak ditemukan!', 16, 1);
            RETURN;
        END
        
        UPDATE Karyawan
        SET 
            Photo_Profile = @Photo_Profile,
            Modified_By = @Modified_By,
            Modified_Date = GETDATE()
        WHERE ID_Karyawan = @ID_Karyawan;
        
        SELECT 'Foto profil berhasil diperbarui!' AS Message;
        
    END TRY
    BEGIN CATCH
        SELECT 
            ERROR_NUMBER() AS ErrorNumber,
            ERROR_MESSAGE() AS ErrorMessage,
            'Gagal memperbarui foto profil!' AS Message;
    END CATCH
END;
GO

-- ============================================================================
-- BAGIAN 3: TABEL LOG HISTORY
-- ============================================================================

-- ----------------------------------------------------------------------------
-- Tabel Log_Karyawan untuk tracking perubahan
-- ----------------------------------------------------------------------------
IF OBJECT_ID('dbo.Log_Karyawan', 'U') IS NOT NULL
    DROP TABLE dbo.Log_Karyawan;
GO

CREATE TABLE Log_Karyawan (
    Log_ID          INT IDENTITY(1,1) PRIMARY KEY,
    ID_Karyawan     INT             NOT NULL,
    Action_Type     VARCHAR(20)     NOT NULL,   -- INSERT, UPDATE, DELETE, TOGGLE
    NIK_Old         VARCHAR(16)     NULL,
    Nama_Old        VARCHAR(20)     NULL,
    Jabatan_Old     INT             NULL,
    Status_Old      INT             NULL,
    NIK_New         VARCHAR(16)     NULL,
    Nama_New        VARCHAR(20)     NULL,
    Jabatan_New     INT             NULL,
    Status_New      INT             NULL,
    Changed_By      VARCHAR(50)     NOT NULL,
    Changed_Date    DATETIME        NOT NULL DEFAULT GETDATE(),
    IP_Address      VARCHAR(50)     NULL
);
GO

-- ============================================================================
-- BAGIAN 4: TRIGGERS UNTUK LOG HISTORY
-- ============================================================================

-- ----------------------------------------------------------------------------
-- Trigger 1: trg_Karyawan_Audit_Insert
-- Log saat insert karyawan baru
-- ----------------------------------------------------------------------------
IF OBJECT_ID('dbo.trg_Karyawan_Audit_Insert', 'TR') IS NOT NULL
    DROP TRIGGER dbo.trg_Karyawan_Audit_Insert;
GO

CREATE TRIGGER dbo.trg_Karyawan_Audit_Insert
ON Karyawan
AFTER INSERT
AS
BEGIN
    SET NOCOUNT ON;
    
    INSERT INTO Log_Karyawan (
        ID_Karyawan, Action_Type,
        NIK_New, Nama_New, Jabatan_New, Status_New,
        Changed_By, Changed_Date
    )
    SELECT 
        i.ID_Karyawan, 'INSERT',
        i.NIK, i.Nama_Karyawan, i.Jabatan, i.Status,
        i.Created_By, GETDATE()
    FROM inserted i;
END;
GO

-- ----------------------------------------------------------------------------
-- Trigger 2: trg_Karyawan_Audit_Update
-- Log saat update data karyawan
-- ----------------------------------------------------------------------------
IF OBJECT_ID('dbo.trg_Karyawan_Audit_Update', 'TR') IS NOT NULL
    DROP TRIGGER dbo.trg_Karyawan_Audit_Update;
GO

CREATE TRIGGER dbo.trg_Karyawan_Audit_Update
ON Karyawan
AFTER UPDATE
AS
BEGIN
    SET NOCOUNT ON;
    
    -- Hanya log jika ada perubahan data (bukan hanya Modified_Date)
    IF EXISTS(
        SELECT 1 FROM deleted d 
        JOIN inserted i ON d.ID_Karyawan = i.ID_Karyawan
        WHERE d.NIK != i.NIK 
           OR d.Nama_Karyawan != i.Nama_Karyawan
           OR d.Jabatan != i.Jabatan
           OR d.Status != i.Status
           OR d.Jenis_Kelamin != i.Jenis_Kelamin
           OR d.No_Telepon != i.No_Telepon
           OR d.Email != i.Email
           OR d.Alamat != i.Alamat
    )
    BEGIN
        INSERT INTO Log_Karyawan (
            ID_Karyawan, Action_Type,
            NIK_Old, Nama_Old, Jabatan_Old, Status_Old,
            NIK_New, Nama_New, Jabatan_New, Status_New,
            Changed_By, Changed_Date
        )
        SELECT 
            d.ID_Karyawan, 'UPDATE',
            d.NIK, d.Nama_Karyawan, d.Jabatan, d.Status,
            i.NIK, i.Nama_Karyawan, i.Jabatan, i.Status,
            ISNULL(i.Modified_By, 'SYSTEM'), GETDATE()
        FROM deleted d
        JOIN inserted i ON d.ID_Karyawan = i.ID_Karyawan;
    END
END;
GO

-- ----------------------------------------------------------------------------
-- Trigger 3: trg_Karyawan_Audit_Delete
-- Log saat soft delete karyawan
-- ----------------------------------------------------------------------------
IF OBJECT_ID('dbo.trg_Karyawan_Audit_Delete', 'TR') IS NOT NULL
    DROP TRIGGER dbo.trg_Karyawan_Audit_Delete;
GO

CREATE TRIGGER dbo.trg_Karyawan_Audit_Delete
ON Karyawan
AFTER UPDATE
AS
BEGIN
    SET NOCOUNT ON;
    
    -- Deteksi soft delete (Is_Deleted berubah dari 0 ke 1)
    IF EXISTS(
        SELECT 1 FROM deleted d 
        JOIN inserted i ON d.ID_Karyawan = i.ID_Karyawan
        WHERE d.Is_Deleted = 0 AND i.Is_Deleted = 1
    )
    BEGIN
        INSERT INTO Log_Karyawan (
            ID_Karyawan, Action_Type,
            NIK_Old, Nama_Old, Jabatan_Old, Status_Old,
            Changed_By, Changed_Date
        )
        SELECT 
            d.ID_Karyawan, 'DELETE',
            d.NIK, d.Nama_Karyawan, d.Jabatan, d.Status,
            ISNULL(i.Deleted_By, 'SYSTEM'), GETDATE()
        FROM deleted d
        JOIN inserted i ON d.ID_Karyawan = i.ID_Karyawan
        WHERE d.Is_Deleted = 0 AND i.Is_Deleted = 1;
    END
END;
GO

-- ----------------------------------------------------------------------------
-- Trigger 4: trg_Karyawan_Audit_Toggle
-- Log saat toggle status karyawan
-- ----------------------------------------------------------------------------
IF OBJECT_ID('dbo.trg_Karyawan_Audit_Toggle', 'TR') IS NOT NULL
    DROP TRIGGER dbo.trg_Karyawan_Audit_Toggle;
GO

CREATE TRIGGER dbo.trg_Karyawan_Audit_Toggle
ON Karyawan
AFTER UPDATE
AS
BEGIN
    SET NOCOUNT ON;
    
    -- Deteksi perubahan status saja (bukan soft delete)
    IF EXISTS(
        SELECT 1 FROM deleted d 
        JOIN inserted i ON d.ID_Karyawan = i.ID_Karyawan
        WHERE d.Status != i.Status 
          AND d.Is_Deleted = 0 AND i.Is_Deleted = 0
    )
    BEGIN
        INSERT INTO Log_Karyawan (
            ID_Karyawan, Action_Type,
            Status_Old, Status_New,
            Changed_By, Changed_Date
        )
        SELECT 
            d.ID_Karyawan, 'TOGGLE',
            d.Status, i.Status,
            ISNULL(i.Modified_By, 'SYSTEM'), GETDATE()
        FROM deleted d
        JOIN inserted i ON d.ID_Karyawan = i.ID_Karyawan
        WHERE d.Status != i.Status 
          AND d.Is_Deleted = 0 AND i.Is_Deleted = 0;
    END
END;
GO

-- ============================================================================
-- BAGIAN 5: CONTOH PENGGUNAAN / TEST
-- ============================================================================

-- ----------------------------------------------------------------------------
-- Test UDF
-- ----------------------------------------------------------------------------
PRINT '=== TEST UDF ===';
SELECT dbo.fn_GetTotalKaryawanAktif() AS Total_Aktif;
SELECT dbo.fn_GetTotalKaryawanByJabatan(2) AS Total_Manajer;
SELECT dbo.fn_GetPersentaseKaryawanAktif() AS Persentase_Aktif;
SELECT * FROM dbo.fn_GetDashboardStats();
SELECT * FROM dbo.fn_GetKaryawanByID(1);
SELECT dbo.fn_GetUmurKaryawan('1995-03-12') AS Umur_Rizky;
SELECT * FROM dbo.fn_SearchKaryawan('Rizky');

-- ----------------------------------------------------------------------------
-- Test SP: GetAll dengan filter
-- ----------------------------------------------------------------------------
PRINT '=== TEST SP GetAll ===';
EXEC sp_Karyawan_GetAll 
    @Filter_Jabatan = 0, 
    @Filter_JK = -1, 
    @Filter_Status = -1,
    @Sort_By = 'Nama_Karyawan',
    @Sort_Order = 'ASC',
    @Page = 1,
    @Limit = 10;

-- ----------------------------------------------------------------------------
-- Test SP: GetTotal
-- ----------------------------------------------------------------------------
PRINT '=== TEST SP GetTotal ===';
EXEC sp_Karyawan_GetTotal 
    @Filter_Jabatan = 0, 
    @Filter_JK = -1, 
    @Filter_Status = -1;

-- ----------------------------------------------------------------------------
-- Test SP: Check NIK
-- ----------------------------------------------------------------------------
PRINT '=== TEST SP CheckNIK ===';
EXEC sp_Karyawan_CheckNIK @NIK = '3173011203950001', @Exclude_ID = 0;

-- ----------------------------------------------------------------------------
-- Test SP: GetByID
-- ----------------------------------------------------------------------------
PRINT '=== TEST SP GetByID ===';
EXEC sp_Karyawan_GetByID @ID_Karyawan = 1;

-- ----------------------------------------------------------------------------
-- Test SP: Insert (uncomment untuk test)
-- ----------------------------------------------------------------------------
/*
DECLARE @NewID INT;
EXEC sp_Karyawan_Insert
    @NIK = '3173011203950099',
    @Nama_Karyawan = 'Test Karyawan',
    @Tanggal_Lahir = '2000-01-01',
    @Tempat_Lahir = 'Jakarta',
    @Alamat = 'Jl. Test No. 1',
    @Jenis_Kelamin = 1,
    @Jabatan = 1,
    @No_Telepon = '081299999999',
    @Email = 'test@hoopball.com',
    @Username = 'test_karyawan',
    @Kata_Sandi = 'Test@1234',
    @Status = 1,
    @Created_By = 'SYSTEM',
    @ID_Karyawan = @NewID OUTPUT;
*/

-- ----------------------------------------------------------------------------
-- Test SP: Update (uncomment untuk test)
-- ----------------------------------------------------------------------------
/*
EXEC sp_Karyawan_Update
    @ID_Karyawan = 6,
    @NIK = '3173011203950099',
    @Nama_Karyawan = 'Test Karyawan Updated',
    @Tanggal_Lahir = '2000-01-01',
    @Tempat_Lahir = 'Jakarta',
    @Alamat = 'Jl. Test No. 1 Updated',
    @Jenis_Kelamin = 1,
    @Jabatan = 1,
    @No_Telepon = '081299999999',
    @Email = 'test@hoopball.com',
    @Username = 'test_karyawan',
    @Kata_Sandi = 'Test@1234',
    @Status = 1,
    @Modified_By = 'SYSTEM';
*/

-- ----------------------------------------------------------------------------
-- Test SP: Toggle Status (uncomment untuk test)
-- ----------------------------------------------------------------------------
/*
EXEC sp_Karyawan_ToggleStatus @ID_Karyawan = 6, @Modified_By = 'SYSTEM';
*/

-- ----------------------------------------------------------------------------
-- Test SP: Delete (uncomment untuk test)
-- ----------------------------------------------------------------------------
/*
EXEC sp_Karyawan_Delete @ID_Karyawan = 6, @Deleted_By = 'SYSTEM';
*/

-- ----------------------------------------------------------------------------
-- Cek Log History
-- ----------------------------------------------------------------------------
PRINT '=== LOG HISTORY ===';
SELECT * FROM Log_Karyawan ORDER BY Changed_Date DESC;

-- ============================================================================
-- SELESAI - MASTER KARYAWAN
-- ============================================================================