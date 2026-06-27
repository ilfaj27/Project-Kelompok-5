-- ============================================================
-- SP: CRUD KARYAWAN
-- ============================================================

-- 1.1 CREATE Karyawan
CREATE PROCEDURE SP_Karyawan_Insert
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
    @Created_By     VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;
    
    IF EXISTS (SELECT 1 FROM Karyawan WHERE NIK = @NIK AND Is_Deleted = 0)
    BEGIN
        RAISERROR('NIK sudah terdaftar!', 16, 1);
        RETURN;
    END
    
    IF EXISTS (SELECT 1 FROM Karyawan WHERE Email = @Email AND Is_Deleted = 0)
    BEGIN
        RAISERROR('Email sudah terdaftar!', 16, 1);
        RETURN;
    END
    
    IF EXISTS (SELECT 1 FROM Karyawan WHERE Username = @Username AND Is_Deleted = 0)
    BEGIN
        RAISERROR('Username sudah terdaftar!', 16, 1);
        RETURN;
    END
    
    IF EXISTS (SELECT 1 FROM Karyawan WHERE No_Telepon = @No_Telepon AND Is_Deleted = 0)
    BEGIN
        RAISERROR('No. Telepon sudah terdaftar!', 16, 1);
        RETURN;
    END

    INSERT INTO Karyawan 
    (NIK, Nama_Karyawan, Tanggal_Lahir, Tempat_Lahir, Alamat, Jenis_Kelamin, 
     Jabatan, No_Telepon, Email, Username, Kata_Sandi, Status, Photo_Profile, 
     Is_Deleted, Created_By, Created_Date)
    VALUES 
    (@NIK, @Nama_Karyawan, @Tanggal_Lahir, @Tempat_Lahir, @Alamat, @Jenis_Kelamin,
     @Jabatan, @No_Telepon, @Email, @Username, @Kata_Sandi, @Status, @Photo_Profile,
     0, @Created_By, GETDATE());
END
GO

-- 1.2 READ Karyawan (Semua / By ID)
CREATE PROCEDURE SP_Karyawan_Select
    @ID_Karyawan INT = NULL
AS
BEGIN
    SET NOCOUNT ON;
    
    SELECT ID_Karyawan, NIK, Nama_Karyawan, Tanggal_Lahir, Tempat_Lahir, 
           Alamat, Jenis_Kelamin, Jabatan, No_Telepon, Email, Username,
           Status, Photo_Profile, Is_Deleted, Created_By, Created_Date,
           Modified_By, Modified_Date, Deleted_By, Deleted_Date
    FROM Karyawan
    WHERE (@ID_Karyawan IS NULL OR ID_Karyawan = @ID_Karyawan)
      AND Is_Deleted = 0
    ORDER BY Nama_Karyawan;
END
GO

-- 1.3 UPDATE Karyawan
CREATE PROCEDURE SP_Karyawan_Update
    @ID_Karyawan    INT,
    @Nama_Karyawan  VARCHAR(20) = NULL,
    @Tanggal_Lahir  DATE = NULL,
    @Tempat_Lahir   VARCHAR(50) = NULL,
    @Alamat         VARCHAR(100) = NULL,
    @Jenis_Kelamin  INT = NULL,
    @Jabatan        INT = NULL,
    @No_Telepon     VARCHAR(15) = NULL,
    @Email          VARCHAR(50) = NULL,
    @Username       VARCHAR(20) = NULL,
    @Kata_Sandi     VARCHAR(20) = NULL,
    @Status         INT = NULL,
    @Photo_Profile  VARCHAR(255) = NULL,
    @Modified_By    VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;
    
    IF NOT EXISTS (SELECT 1 FROM Karyawan WHERE ID_Karyawan = @ID_Karyawan AND Is_Deleted = 0)
    BEGIN
        RAISERROR('Data Karyawan tidak ditemukan!', 16, 1);
        RETURN;
    END

    UPDATE Karyawan
    SET Nama_Karyawan = COALESCE(@Nama_Karyawan, Nama_Karyawan),
        Tanggal_Lahir   = COALESCE(@Tanggal_Lahir, Tanggal_Lahir),
        Tempat_Lahir    = COALESCE(@Tempat_Lahir, Tempat_Lahir),
        Alamat          = COALESCE(@Alamat, Alamat),
        Jenis_Kelamin   = COALESCE(@Jenis_Kelamin, Jenis_Kelamin),
        Jabatan         = COALESCE(@Jabatan, Jabatan),
        No_Telepon      = COALESCE(@No_Telepon, No_Telepon),
        Email           = COALESCE(@Email, Email),
        Username        = COALESCE(@Username, Username),
        Kata_Sandi      = COALESCE(@Kata_Sandi, Kata_Sandi),
        Status          = COALESCE(@Status, Status),
        Photo_Profile   = COALESCE(@Photo_Profile, Photo_Profile),
        Modified_By     = @Modified_By,
        Modified_Date   = GETDATE()
    WHERE ID_Karyawan = @ID_Karyawan;
END
GO

-- 1.4 DELETE Karyawan (Soft Delete)
CREATE PROCEDURE SP_Karyawan_Delete
    @ID_Karyawan INT,
    @Deleted_By  VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;
    
    IF NOT EXISTS (SELECT 1 FROM Karyawan WHERE ID_Karyawan = @ID_Karyawan AND Is_Deleted = 0)
    BEGIN
        RAISERROR('Data Karyawan tidak ditemukan!', 16, 1);
        RETURN;
    END

    UPDATE Karyawan
    SET Is_Deleted  = 1,
        Deleted_By  = @Deleted_By,
        Deleted_Date = GETDATE()
    WHERE ID_Karyawan = @ID_Karyawan;
END
GO


-- ============================================================
-- SP: CRUD CUSTOMER
-- ============================================================

-- 2.1 CREATE Customer
CREATE PROCEDURE SP_Customer_Insert
    @Nama_Customer  VARCHAR(20),
    @Tanggal_Lahir  DATE,
    @Tempat_Lahir   VARCHAR(50),
    @Jenis_Kelamin  INT,
    @Alamat         VARCHAR(100),
    @No_Telepon     VARCHAR(15),
    @Email          VARCHAR(50),
    @Username       VARCHAR(20),
    @Kata_Sandi     VARCHAR(20),
    @Status         INT,
    @Photo_Profile  VARCHAR(255) = NULL,
    @Created_By     VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;
    
    IF EXISTS (SELECT 1 FROM Customer WHERE Email = @Email AND Is_Deleted = 0)
    BEGIN
        RAISERROR('Email sudah terdaftar!', 16, 1);
        RETURN;
    END
    
    IF EXISTS (SELECT 1 FROM Customer WHERE Username = @Username AND Is_Deleted = 0)
    BEGIN
        RAISERROR('Username sudah terdaftar!', 16, 1);
        RETURN;
    END
    
    IF EXISTS (SELECT 1 FROM Customer WHERE No_Telepon = @No_Telepon AND Is_Deleted = 0)
    BEGIN
        RAISERROR('No. Telepon sudah terdaftar!', 16, 1);
        RETURN;
    END

    INSERT INTO Customer 
    (Nama_Customer, Tanggal_Lahir, Tempat_Lahir, Jenis_Kelamin, Alamat, 
     No_Telepon, Email, Username, Kata_Sandi, Status, Photo_Profile,
     Is_Deleted, Created_By, Created_Date)
    VALUES 
    (@Nama_Customer, @Tanggal_Lahir, @Tempat_Lahir, @Jenis_Kelamin, @Alamat,
     @No_Telepon, @Email, @Username, @Kata_Sandi, @Status, @Photo_Profile,
     0, @Created_By, GETDATE());
END
GO

-- 2.2 READ Customer
CREATE PROCEDURE SP_Customer_Select
    @ID_Customer INT = NULL
AS
BEGIN
    SET NOCOUNT ON;
    
    SELECT ID_Customer, Nama_Customer, Tanggal_Lahir, Tempat_Lahir, 
           Jenis_Kelamin, Alamat, No_Telepon, Email, Username,
           Status, Photo_Profile, Is_Deleted, Created_By, Created_Date,
           Modified_By, Modified_Date, Deleted_By, Deleted_Date
    FROM Customer
    WHERE (@ID_Customer IS NULL OR ID_Customer = @ID_Customer)
      AND Is_Deleted = 0
    ORDER BY Nama_Customer;
END
GO

-- 2.3 UPDATE Customer
CREATE PROCEDURE SP_Customer_Update
    @ID_Customer    INT,
    @Nama_Customer  VARCHAR(20) = NULL,
    @Tanggal_Lahir  DATE = NULL,
    @Tempat_Lahir   VARCHAR(50) = NULL,
    @Jenis_Kelamin  INT = NULL,
    @Alamat         VARCHAR(100) = NULL,
    @No_Telepon     VARCHAR(15) = NULL,
    @Email          VARCHAR(50) = NULL,
    @Username       VARCHAR(20) = NULL,
    @Kata_Sandi     VARCHAR(20) = NULL,
    @Status         INT = NULL,
    @Photo_Profile  VARCHAR(255) = NULL,
    @Modified_By    VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;
    
    IF NOT EXISTS (SELECT 1 FROM Customer WHERE ID_Customer = @ID_Customer AND Is_Deleted = 0)
    BEGIN
        RAISERROR('Data Customer tidak ditemukan!', 16, 1);
        RETURN;
    END

    UPDATE Customer
    SET Nama_Customer = COALESCE(@Nama_Customer, Nama_Customer),
        Tanggal_Lahir   = COALESCE(@Tanggal_Lahir, Tanggal_Lahir),
        Tempat_Lahir    = COALESCE(@Tempat_Lahir, Tempat_Lahir),
        Jenis_Kelamin   = COALESCE(@Jenis_Kelamin, Jenis_Kelamin),
        Alamat          = COALESCE(@Alamat, Alamat),
        No_Telepon      = COALESCE(@No_Telepon, No_Telepon),
        Email           = COALESCE(@Email, Email),
        Username        = COALESCE(@Username, Username),
        Kata_Sandi      = COALESCE(@Kata_Sandi, Kata_Sandi),
        Status          = COALESCE(@Status, Status),
        Photo_Profile   = COALESCE(@Photo_Profile, Photo_Profile),
        Modified_By     = @Modified_By,
        Modified_Date   = GETDATE()
    WHERE ID_Customer = @ID_Customer;
END
GO

-- 2.4 DELETE Customer (Soft Delete)
CREATE PROCEDURE SP_Customer_Delete
    @ID_Customer INT,
    @Deleted_By  VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;
    
    IF NOT EXISTS (SELECT 1 FROM Customer WHERE ID_Customer = @ID_Customer AND Is_Deleted = 0)
    BEGIN
        RAISERROR('Data Customer tidak ditemukan!', 16, 1);
        RETURN;
    END

    UPDATE Customer
    SET Is_Deleted  = 1,
        Deleted_By  = @Deleted_By,
        Deleted_Date = GETDATE()
    WHERE ID_Customer = @ID_Customer;
END
GO


-- ============================================================
-- SP: CRUD LAPANGAN
-- ============================================================

-- 3.1 CREATE Lapangan
CREATE PROCEDURE SP_Lapangan_Insert
    @Nama_Lapangan  VARCHAR(25),
    @Harga_Sewa     DECIMAL(18,2),
    @Photo_Lapangan VARCHAR(255) = NULL,
    @Status         INT,
    @Created_By     VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;
    
    IF @Harga_Sewa < 0
    BEGIN
        RAISERROR('Harga sewa tidak boleh negatif!', 16, 1);
        RETURN;
    END

    INSERT INTO Lapangan 
    (Nama_Lapangan, Harga_Sewa, Photo_Lapangan, Status, Is_Deleted, Created_By, Created_Date)
    VALUES 
    (@Nama_Lapangan, @Harga_Sewa, @Photo_Lapangan, @Status, 0, @Created_By, GETDATE());
END
GO

-- 3.2 READ Lapangan
CREATE PROCEDURE SP_Lapangan_Select
    @ID_Lapangan INT = NULL
AS
BEGIN
    SET NOCOUNT ON;
    
    SELECT ID_Lapangan, Nama_Lapangan, Harga_Sewa, Photo_Lapangan, 
           Status, Is_Deleted, Created_By, Created_Date,
           Modified_By, Modified_Date, Deleted_By, Deleted_Date
    FROM Lapangan
    WHERE (@ID_Lapangan IS NULL OR ID_Lapangan = @ID_Lapangan)
      AND Is_Deleted = 0
    ORDER BY Nama_Lapangan;
END
GO

-- 3.3 UPDATE Lapangan
CREATE PROCEDURE SP_Lapangan_Update
    @ID_Lapangan    INT,
    @Nama_Lapangan  VARCHAR(25) = NULL,
    @Harga_Sewa     DECIMAL(18,2) = NULL,
    @Photo_Lapangan VARCHAR(255) = NULL,
    @Status         INT = NULL,
    @Modified_By    VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;
    
    IF NOT EXISTS (SELECT 1 FROM Lapangan WHERE ID_Lapangan = @ID_Lapangan AND Is_Deleted = 0)
    BEGIN
        RAISERROR('Data Lapangan tidak ditemukan!', 16, 1);
        RETURN;
    END

    IF @Harga_Sewa IS NOT NULL AND @Harga_Sewa < 0
    BEGIN
        RAISERROR('Harga sewa tidak boleh negatif!', 16, 1);
        RETURN;
    END

    UPDATE Lapangan
    SET Nama_Lapangan  = COALESCE(@Nama_Lapangan, Nama_Lapangan),
        Harga_Sewa     = COALESCE(@Harga_Sewa, Harga_Sewa),
        Photo_Lapangan = COALESCE(@Photo_Lapangan, Photo_Lapangan),
        Status         = COALESCE(@Status, Status),
        Modified_By    = @Modified_By,
        Modified_Date  = GETDATE()
    WHERE ID_Lapangan = @ID_Lapangan;
END
GO

-- 3.4 DELETE Lapangan (Soft Delete)
CREATE PROCEDURE SP_Lapangan_Delete
    @ID_Lapangan INT,
    @Deleted_By  VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;
    
    IF NOT EXISTS (SELECT 1 FROM Lapangan WHERE ID_Lapangan = @ID_Lapangan AND Is_Deleted = 0)
    BEGIN
        RAISERROR('Data Lapangan tidak ditemukan!', 16, 1);
        RETURN;
    END

    UPDATE Lapangan
    SET Is_Deleted  = 1,
        Deleted_By  = @Deleted_By,
        Deleted_Date = GETDATE()
    WHERE ID_Lapangan = @ID_Lapangan;
END
GO


-- ============================================================
-- SP: CRUD ALAT
-- ============================================================

-- 4.1 CREATE Alat
CREATE PROCEDURE SP_Alat_Insert
    @Nama_Alat   VARCHAR(25),
    @Stok        INT,
    @Harga_Alat  DECIMAL(18,2),
    @Photo_Alat  VARCHAR(255) = NULL,
    @Status      INT,
    @Created_By  VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;
    
    IF @Stok < 0
    BEGIN
        RAISERROR('Stok tidak boleh negatif!', 16, 1);
        RETURN;
    END
    
    IF @Harga_Alat < 0
    BEGIN
        RAISERROR('Harga alat tidak boleh negatif!', 16, 1);
        RETURN;
    END

    INSERT INTO Alat 
    (Nama_Alat, Stok, Harga_Alat, Photo_Alat, Status, Is_Deleted, Created_By, Created_Date)
    VALUES 
    (@Nama_Alat, @Stok, @Harga_Alat, @Photo_Alat, @Status, 0, @Created_By, GETDATE());
END
GO

-- 4.2 READ Alat
CREATE PROCEDURE SP_Alat_Select
    @ID_Alat INT = NULL
AS
BEGIN
    SET NOCOUNT ON;
    
    SELECT ID_Alat, Nama_Alat, Stok, Harga_Alat, Photo_Alat,
           Status, Is_Deleted, Created_By, Created_Date,
           Modified_By, Modified_Date, Deleted_By, Deleted_Date
    FROM Alat
    WHERE (@ID_Alat IS NULL OR ID_Alat = @ID_Alat)
      AND Is_Deleted = 0
    ORDER BY Nama_Alat;
END
GO

-- 4.3 UPDATE Alat
CREATE PROCEDURE SP_Alat_Update
    @ID_Alat     INT,
    @Nama_Alat   VARCHAR(25) = NULL,
    @Stok        INT = NULL,
    @Harga_Alat  DECIMAL(18,2) = NULL,
    @Photo_Alat  VARCHAR(255) = NULL,
    @Status      INT = NULL,
    @Modified_By VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;
    
    IF NOT EXISTS (SELECT 1 FROM Alat WHERE ID_Alat = @ID_Alat AND Is_Deleted = 0)
    BEGIN
        RAISERROR('Data Alat tidak ditemukan!', 16, 1);
        RETURN;
    END

    IF @Stok IS NOT NULL AND @Stok < 0
    BEGIN
        RAISERROR('Stok tidak boleh negatif!', 16, 1);
        RETURN;
    END
    
    IF @Harga_Alat IS NOT NULL AND @Harga_Alat < 0
    BEGIN
        RAISERROR('Harga alat tidak boleh negatif!', 16, 1);
        RETURN;
    END

    UPDATE Alat
    SET Nama_Alat   = COALESCE(@Nama_Alat, Nama_Alat),
        Stok        = COALESCE(@Stok, Stok),
        Harga_Alat  = COALESCE(@Harga_Alat, Harga_Alat),
        Photo_Alat  = COALESCE(@Photo_Alat, Photo_Alat),
        Status      = COALESCE(@Status, Status),
        Modified_By = @Modified_By,
        Modified_Date = GETDATE()
    WHERE ID_Alat = @ID_Alat;
END
GO

-- 4.4 DELETE Alat (Soft Delete)
CREATE PROCEDURE SP_Alat_Delete
    @ID_Alat    INT,
    @Deleted_By VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;
    
    IF NOT EXISTS (SELECT 1 FROM Alat WHERE ID_Alat = @ID_Alat AND Is_Deleted = 0)
    BEGIN
        RAISERROR('Data Alat tidak ditemukan!', 16, 1);
        RETURN;
    END

    UPDATE Alat
    SET Is_Deleted  = 1,
        Deleted_By  = @Deleted_By,
        Deleted_Date = GETDATE()
    WHERE ID_Alat = @ID_Alat;
END
GO


-- ============================================================
-- SP: CRUD PROMO
-- ============================================================

-- 5.1 CREATE Promo
CREATE PROCEDURE SP_Promo_Insert
    @Nama_Promo      VARCHAR(50),
    @Diskon          DECIMAL(18,2),
    @Tanggal_Mulai   DATE,
    @Tanggal_Selesai DATE,
    @Status          INT,
    @Created_By      VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;
    
    IF @Tanggal_Mulai > @Tanggal_Selesai
    BEGIN
        RAISERROR('Tanggal mulai tidak boleh lebih besar dari tanggal selesai!', 16, 1);
        RETURN;
    END

    INSERT INTO Promo 
    (Nama_Promo, Diskon, Tanggal_Mulai, Tanggal_Selesai, Status, Is_Deleted, Created_By, Created_Date)
    VALUES 
    (@Nama_Promo, @Diskon, @Tanggal_Mulai, @Tanggal_Selesai, @Status, 0, @Created_By, GETDATE());
END
GO

-- 5.2 READ Promo
CREATE PROCEDURE SP_Promo_Select
    @ID_Promo INT = NULL,
    @Aktif    BIT = NULL  -- NULL = semua, 1 = aktif saat ini, 0 = nonaktif/expired
AS
BEGIN
    SET NOCOUNT ON;
    
    SELECT ID_Promo, Nama_Promo, Diskon, Tanggal_Mulai, Tanggal_Selesai,
           Status, Is_Deleted, Created_By, Created_Date,
           Modified_By, Modified_Date, Deleted_By, Deleted_Date
    FROM Promo
    WHERE (@ID_Promo IS NULL OR ID_Promo = @ID_Promo)
      AND Is_Deleted = 0
      AND (@Aktif IS NULL OR 
           (@Aktif = 1 AND Status = 1 AND Tanggal_Selesai >= CAST(GETDATE() AS DATE)) OR
           (@Aktif = 0 AND (Status = 0 OR Tanggal_Selesai < CAST(GETDATE() AS DATE))))
    ORDER BY Tanggal_Mulai DESC;
END
GO

-- 5.3 UPDATE Promo
CREATE PROCEDURE SP_Promo_Update
    @ID_Promo        INT,
    @Nama_Promo      VARCHAR(50) = NULL,
    @Diskon          DECIMAL(18,2) = NULL,
    @Tanggal_Mulai   DATE = NULL,
    @Tanggal_Selesai DATE = NULL,
    @Status          INT = NULL,
    @Modified_By     VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;
    
    IF NOT EXISTS (SELECT 1 FROM Promo WHERE ID_Promo = @ID_Promo AND Is_Deleted = 0)
    BEGIN
        RAISERROR('Data Promo tidak ditemukan!', 16, 1);
        RETURN;
    END

    DECLARE @NewMulai DATE = COALESCE(@Tanggal_Mulai, (SELECT Tanggal_Mulai FROM Promo WHERE ID_Promo = @ID_Promo));
    DECLARE @NewSelesai DATE = COALESCE(@Tanggal_Selesai, (SELECT Tanggal_Selesai FROM Promo WHERE ID_Promo = @ID_Promo));
    
    IF @NewMulai > @NewSelesai
    BEGIN
        RAISERROR('Tanggal mulai tidak boleh lebih besar dari tanggal selesai!', 16, 1);
        RETURN;
    END

    UPDATE Promo
    SET Nama_Promo      = COALESCE(@Nama_Promo, Nama_Promo),
        Diskon          = COALESCE(@Diskon, Diskon),
        Tanggal_Mulai   = COALESCE(@Tanggal_Mulai, Tanggal_Mulai),
        Tanggal_Selesai = COALESCE(@Tanggal_Selesai, Tanggal_Selesai),
        Status          = COALESCE(@Status, Status),
        Modified_By     = @Modified_By,
        Modified_Date   = GETDATE()
    WHERE ID_Promo = @ID_Promo;
END
GO

-- 5.4 DELETE Promo (Soft Delete)
CREATE PROCEDURE SP_Promo_Delete
    @ID_Promo   INT,
    @Deleted_By VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;
    
    IF NOT EXISTS (SELECT 1 FROM Promo WHERE ID_Promo = @ID_Promo AND Is_Deleted = 0)
    BEGIN
        RAISERROR('Data Promo tidak ditemukan!', 16, 1);
        RETURN;
    END

    UPDATE Promo
    SET Is_Deleted  = 1,
        Deleted_By  = @Deleted_By,
        Deleted_Date = GETDATE()
    WHERE ID_Promo = @ID_Promo;
END
GO


-- ============================================================
-- SP: CRUD TIPE MEMBER
-- ============================================================

-- 6.1 CREATE Tipe Member
CREATE PROCEDURE SP_TipeMember_Insert
    @Nama_Tipe      VARCHAR(10),
    @Harga_Member   DECIMAL(18,2),
    @Potongan_Harga DECIMAL(18,2),
    @Status         INT,
    @Created_By     VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;

    INSERT INTO Tipe_Member 
    (Nama_Tipe, Harga_Member, Potongan_Harga, Status, Is_Deleted, Created_By, Created_Date)
    VALUES 
    (@Nama_Tipe, @Harga_Member, @Potongan_Harga, @Status, 0, @Created_By, GETDATE());
END
GO

-- 6.2 READ Tipe Member
CREATE PROCEDURE SP_TipeMember_Select
    @ID_Tipe INT = NULL
AS
BEGIN
    SET NOCOUNT ON;
    
    SELECT ID_Tipe, Nama_Tipe, Harga_Member, Potongan_Harga,
           Status, Is_Deleted, Created_By, Created_Date,
           Modified_By, Modified_Date, Deleted_By, Deleted_Date
    FROM Tipe_Member
    WHERE (@ID_Tipe IS NULL OR ID_Tipe = @ID_Tipe)
      AND Is_Deleted = 0
    ORDER BY Harga_Member;
END
GO

-- 6.3 UPDATE Tipe Member
CREATE PROCEDURE SP_TipeMember_Update
    @ID_Tipe        INT,
    @Nama_Tipe      VARCHAR(10) = NULL,
    @Harga_Member   DECIMAL(18,2) = NULL,
    @Potongan_Harga DECIMAL(18,2) = NULL,
    @Status         INT = NULL,
    @Modified_By    VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;
    
    IF NOT EXISTS (SELECT 1 FROM Tipe_Member WHERE ID_Tipe = @ID_Tipe AND Is_Deleted = 0)
    BEGIN
        RAISERROR('Data Tipe Member tidak ditemukan!', 16, 1);
        RETURN;
    END

    UPDATE Tipe_Member
    SET Nama_Tipe      = COALESCE(@Nama_Tipe, Nama_Tipe),
        Harga_Member   = COALESCE(@Harga_Member, Harga_Member),
        Potongan_Harga = COALESCE(@Potongan_Harga, Potongan_Harga),
        Status         = COALESCE(@Status, Status),
        Modified_By    = @Modified_By,
        Modified_Date  = GETDATE()
    WHERE ID_Tipe = @ID_Tipe;
END
GO

-- 6.4 DELETE Tipe Member (Soft Delete)
CREATE PROCEDURE SP_TipeMember_Delete
    @ID_Tipe    INT,
    @Deleted_By VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;
    
    IF NOT EXISTS (SELECT 1 FROM Tipe_Member WHERE ID_Tipe = @ID_Tipe AND Is_Deleted = 0)
    BEGIN
        RAISERROR('Data Tipe Member tidak ditemukan!', 16, 1);
        RETURN;
    END

    UPDATE Tipe_Member
    SET Is_Deleted  = 1,
        Deleted_By  = @Deleted_By,
        Deleted_Date = GETDATE()
    WHERE ID_Tipe = @ID_Tipe;
END
GO


-- ============================================================
-- SP: CRUD JADWAL
-- ============================================================

-- 7.1 CREATE Jadwal
CREATE PROCEDURE SP_Jadwal_Insert
    @ID_Lapangan   INT,
    @Tanggal       DATE,
    @Jam_Mulai     TIME,
    @Jam_Selesai   TIME,
    @Status        INT,
    @Created_By    VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;
    
    IF NOT EXISTS (SELECT 1 FROM Lapangan WHERE ID_Lapangan = @ID_Lapangan AND Is_Deleted = 0)
    BEGIN
        RAISERROR('Lapangan tidak ditemukan!', 16, 1);
        RETURN;
    END
    
    IF @Jam_Mulai >= @Jam_Selesai
    BEGIN
        RAISERROR('Jam mulai harus lebih kecil dari jam selesai!', 16, 1);
        RETURN;
    END
    
    IF EXISTS (SELECT 1 FROM Jadwal 
               WHERE ID_Lapangan = @ID_Lapangan 
                 AND Tanggal = @Tanggal 
                 AND Jam_Mulai = @Jam_Mulai 
                 AND Jam_Selesai = @Jam_Selesai
                 AND Is_Deleted = 0)
    BEGIN
        RAISERROR('Jadwal bentrok! Sudah ada jadwal di waktu yang sama.', 16, 1);
        RETURN;
    END

    INSERT INTO Jadwal 
    (ID_Lapangan, Tanggal, Jam_Mulai, Jam_Selesai, Status, Is_Deleted, Created_By, Created_Date)
    VALUES 
    (@ID_Lapangan, @Tanggal, @Jam_Mulai, @Jam_Selesai, @Status, 0, @Created_By, GETDATE());
END
GO

-- 7.2 READ Jadwal
CREATE PROCEDURE SP_Jadwal_Select
    @ID_Jadwal   INT = NULL,
    @ID_Lapangan INT = NULL,
    @Tanggal     DATE = NULL,
    @Tersedia    BIT = NULL  -- NULL = semua, 1 = tersedia, 0 = tidak tersedia
AS
BEGIN
    SET NOCOUNT ON;
    
    SELECT j.ID_Jadwal, j.ID_Lapangan, l.Nama_Lapangan, j.Tanggal, 
           j.Jam_Mulai, j.Jam_Selesai, j.Status, j.Is_Deleted,
           j.Created_By, j.Created_Date, j.Modified_By, j.Modified_Date,
           j.Deleted_By, j.Deleted_Date
    FROM Jadwal j
    JOIN Lapangan l ON j.ID_Lapangan = l.ID_Lapangan
    WHERE (@ID_Jadwal IS NULL OR j.ID_Jadwal = @ID_Jadwal)
      AND (@ID_Lapangan IS NULL OR j.ID_Lapangan = @ID_Lapangan)
      AND (@Tanggal IS NULL OR j.Tanggal = @Tanggal)
      AND (@Tersedia IS NULL OR j.Status = @Tersedia)
      AND j.Is_Deleted = 0
      AND l.Is_Deleted = 0
    ORDER BY j.Tanggal, j.Jam_Mulai;
END
GO

-- 7.3 UPDATE Jadwal
CREATE PROCEDURE SP_Jadwal_Update
    @ID_Jadwal     INT,
    @ID_Lapangan   INT = NULL,
    @Tanggal       DATE = NULL,
    @Jam_Mulai     TIME = NULL,
    @Jam_Selesai   TIME = NULL,
    @Status        INT = NULL,
    @Modified_By   VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;
    
    IF NOT EXISTS (SELECT 1 FROM Jadwal WHERE ID_Jadwal = @ID_Jadwal AND Is_Deleted = 0)
    BEGIN
        RAISERROR('Data Jadwal tidak ditemukan!', 16, 1);
        RETURN;
    END

    DECLARE @CurrLapangan INT = (SELECT ID_Lapangan FROM Jadwal WHERE ID_Jadwal = @ID_Jadwal);
    DECLARE @CurrTanggal DATE = (SELECT Tanggal FROM Jadwal WHERE ID_Jadwal = @ID_Jadwal);
    DECLARE @CurrMulai TIME = (SELECT Jam_Mulai FROM Jadwal WHERE ID_Jadwal = @ID_Jadwal);
    DECLARE @CurrSelesai TIME = (SELECT Jam_Selesai FROM Jadwal WHERE ID_Jadwal = @ID_Jadwal);
    
    DECLARE @NewLapangan INT = COALESCE(@ID_Lapangan, @CurrLapangan);
    DECLARE @NewTanggal DATE = COALESCE(@Tanggal, @CurrTanggal);
    DECLARE @NewMulai TIME = COALESCE(@Jam_Mulai, @CurrMulai);
    DECLARE @NewSelesai TIME = COALESCE(@Jam_Selesai, @CurrSelesai);

    IF @NewMulai >= @NewSelesai
    BEGIN
        RAISERROR('Jam mulai harus lebih kecil dari jam selesai!', 16, 1);
        RETURN;
    END
    
    IF EXISTS (SELECT 1 FROM Jadwal 
               WHERE ID_Lapangan = @NewLapangan 
                 AND Tanggal = @NewTanggal 
                 AND Jam_Mulai = @NewMulai 
                 AND Jam_Selesai = @NewSelesai
                 AND ID_Jadwal <> @ID_Jadwal
                 AND Is_Deleted = 0)
    BEGIN
        RAISERROR('Jadwal bentrok! Sudah ada jadwal di waktu yang sama.', 16, 1);
        RETURN;
    END

    UPDATE Jadwal
    SET ID_Lapangan = @NewLapangan,
        Tanggal     = @NewTanggal,
        Jam_Mulai   = @NewMulai,
        Jam_Selesai = @NewSelesai,
        Status      = COALESCE(@Status, Status),
        Modified_By = @Modified_By,
        Modified_Date = GETDATE()
    WHERE ID_Jadwal = @ID_Jadwal;
END
GO

-- 7.4 DELETE Jadwal (Soft Delete)
CREATE PROCEDURE SP_Jadwal_Delete
    @ID_Jadwal   INT,
    @Deleted_By  VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;
    
    IF NOT EXISTS (SELECT 1 FROM Jadwal WHERE ID_Jadwal = @ID_Jadwal AND Is_Deleted = 0)
    BEGIN
        RAISERROR('Data Jadwal tidak ditemukan!', 16, 1);
        RETURN;
    END

    UPDATE Jadwal
    SET Is_Deleted  = 1,
        Deleted_By  = @Deleted_By,
        Deleted_Date = GETDATE()
    WHERE ID_Jadwal = @ID_Jadwal;
END
GO


