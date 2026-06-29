    USE Hoopball;
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


    -- SP untuk Memeriksa Duplikasi Username / Email / Telepon (Read)
    CREATE OR ALTER PROCEDURE dbo.sp_CheckCustomerDuplicate
        @Username   VARCHAR(20),
        @Email      VARCHAR(50),
        @No_Telepon VARCHAR(15)
    AS
    BEGIN
        SET NOCOUNT ON;
        SELECT Username, Email, No_Telepon 
        FROM dbo.Customer 
        WHERE (Username = @Username OR Email = @Email OR No_Telepon = @No_Telepon)
        AND Is_Deleted = 0;
    END;
    GO

    -- SP untuk Menyimpan Customer Baru (Create)
    CREATE OR ALTER PROCEDURE dbo.sp_CreateCustomer
        @Nama_Customer  VARCHAR(20),
        @Tanggal_Lahir  DATE,
        @Tempat_Lahir   VARCHAR(50),
        @Jenis_Kelamin  INT,
        @Alamat         VARCHAR(100),
        @No_Telepon     VARCHAR(15),
        @Email          VARCHAR(50),
        @Username       VARCHAR(20),
        @Kata_Sandi      VARCHAR(20)
    AS
    BEGIN
        SET NOCOUNT ON;
        INSERT INTO dbo.Customer (
            Nama_Customer, Tanggal_Lahir, Tempat_Lahir, Jenis_Kelamin, 
            Alamat, No_Telepon, Email, Username, Kata_Sandi, Status, Is_Deleted, Created_By, Created_Date
        )
        VALUES (
            @Nama_Customer, @Tanggal_Lahir, @Tempat_Lahir, @Jenis_Kelamin, 
            @Alamat, @No_Telepon, @Email, @Username, @Kata_Sandi, 1, 0, 'System', GETDATE()
        );
    END;
    GO


    -- 1. SP untuk Autentikasi Login Karyawan (Read)
CREATE OR ALTER PROCEDURE dbo.sp_AuthenticateKaryawan
    @UserInput VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;
    SELECT ID_Karyawan, Nama_Karyawan, Kata_Sandi, Jabatan, Photo_Profile 
    FROM dbo.Karyawan 
    WHERE (Username = @UserInput OR Email = @UserInput) 
      AND Status = 1 
      AND Is_Deleted = 0;
END;
GO

-- 2. SP untuk Autentikasi Login Customer (Read)
CREATE OR ALTER PROCEDURE dbo.sp_AuthenticateCustomer
    @UserInput VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;
    SELECT ID_Customer, Nama_Customer, Kata_Sandi 
    FROM dbo.Customer 
    WHERE (Username = @UserInput OR Email = @UserInput) 
      AND Status = 1 
      AND Is_Deleted = 0;
END;
GO