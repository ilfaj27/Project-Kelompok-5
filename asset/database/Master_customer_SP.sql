-- ============================================================
-- STORED PROCEDURES: MASTER CUSTOMER (HoopBall)
-- ============================================================
-- Database: Hoopball
-- Tabel: Customer
-- Author: Generated for HoopBall Project
-- Date: 2026-07-09
-- ============================================================

USE Hoopball;
GO

-- ============================================================
-- 1. SP: Get All Customers (Read List with Pagination & Search)
-- ============================================================
CREATE OR ALTER PROCEDURE dbo.sp_ReadCustomerListWithCount
    @FilterStatus   VARCHAR(10) = 'all',    -- 'all', 'aktif', 'nonaktif'
    @FilterJK       INT = -1,               -- -1 = all, 0 = Perempuan, 1 = Laki-laki
    @SortBy         VARCHAR(50) = 'ID_Customer',
    @SortOrder      VARCHAR(4) = 'ASC',
    @Offset         INT = 0,
    @Limit          INT = 10,
    @Search         VARCHAR(100) = ''
AS
BEGIN
    SET NOCOUNT ON;

    -- Validasi SortBy untuk mencegah SQL Injection
    IF @SortBy NOT IN ('ID_Customer', 'Nama_Customer', 'Jenis_Kelamin', 'Alamat', 'No_Telepon', 'Email', 'Created_Date')
        SET @SortBy = 'ID_Customer';

    IF UPPER(@SortOrder) NOT IN ('ASC', 'DESC')
        SET @SortOrder = 'ASC';

    -- Hitung total data terfilter
    SELECT COUNT(*) AS TotalCount
    FROM Customer
    WHERE Is_Deleted = 0
        AND (@FilterStatus = 'all' OR 
            (@FilterStatus = 'aktif' AND Status = 1) OR 
            (@FilterStatus = 'nonaktif' AND Status = 0))
        AND (@FilterJK = -1 OR Jenis_Kelamin = @FilterJK)
        AND (@Search = '' OR 
            Nama_Customer LIKE '%' + @Search + '%' OR 
            Email LIKE '%' + @Search + '%' OR 
            No_Telepon LIKE '%' + @Search + '%' OR 
            Alamat LIKE '%' + @Search + '%');

    -- Ambil data customer
    DECLARE @sql NVARCHAR(MAX);
    SET @sql = N'
    SELECT 
        ID_Customer,
        Nama_Customer,
        Tanggal_Lahir,
        -- KALKULASI UMUR AKTIF
        DATEDIFF(YEAR, Tanggal_Lahir, GETDATE()) - 
        CASE 
            WHEN MONTH(Tanggal_Lahir) > MONTH(GETDATE()) 
                 OR (MONTH(Tanggal_Lahir) = MONTH(GETDATE()) AND DAY(Tanggal_Lahir) > DAY(GETDATE())) 
            THEN 1 
            ELSE 0 
        END AS Umur,
        Tempat_Lahir,
        Jenis_Kelamin,
        Alamat,
        No_Telepon,
        Email,
        Username,
        Status,
        Photo_Profile,
        Created_Date,
        Modified_Date
    FROM Customer
    WHERE Is_Deleted = 0
        AND (@FilterStatus = ''all'' OR 
            (@FilterStatus = ''aktif'' AND Status = 1) OR 
            (@FilterStatus = ''nonaktif'' AND Status = 0))
        AND (@FilterJK = -1 OR Jenis_Kelamin = @FilterJK)
        AND (@Search = '''' OR 
            Nama_Customer LIKE ''%'' + @Search + ''%'' OR 
            Email LIKE ''%'' + @Search + ''%'' OR 
            No_Telepon LIKE ''%'' + @Search + ''%'' OR 
            Alamat LIKE ''%'' + @Search + ''%'')
    ORDER BY ' + QUOTENAME(@SortBy) + ' ' + @SortOrder + '
    OFFSET @Offset ROWS
    FETCH NEXT @Limit ROWS ONLY;';

    EXEC sp_executesql @sql, 
        N'@FilterStatus VARCHAR(10), @FilterJK INT, @Search VARCHAR(100), @Offset INT, @Limit INT',
        @FilterStatus, @FilterJK, @Search, @Offset, @Limit;
END;
GO

-- ============================================================
-- 2. SP: Get Customer Detail by ID
-- ============================================================
CREATE OR ALTER PROCEDURE dbo.sp_GetCustomerDetail
    @ID_Customer INT
AS
BEGIN
    SET NOCOUNT ON;

    SELECT 
        ID_Customer,
        Nama_Customer,
        Tanggal_Lahir,
        Tempat_Lahir,
        Jenis_Kelamin,
        Alamat,
        No_Telepon,
        Email,
        Username,
        Status,
        Photo_Profile,
        Is_Deleted,
        Created_By,
        Created_Date,
        Modified_By,
        Modified_Date
    FROM Customer
    WHERE ID_Customer = @ID_Customer AND Is_Deleted = 0;
END;
GO

-- ============================================================
-- 3. SP: Create New Customer (Registration)
-- ============================================================
CREATE OR ALTER PROCEDURE dbo.sp_CreateCustomer
    @Nama_Customer   VARCHAR(20),
    @Tanggal_Lahir   DATE,
    @Tempat_Lahir    VARCHAR(50),
    @Jenis_Kelamin   INT,
    @Alamat          VARCHAR(100),
    @No_Telepon      VARCHAR(15),
    @Email           VARCHAR(50),
    @Username        VARCHAR(20),
    @Kata_Sandi      VARCHAR(255)
AS
BEGIN
    SET NOCOUNT ON;

    INSERT INTO Customer
    (Nama_Customer, Tanggal_Lahir, Tempat_Lahir, Jenis_Kelamin, Alamat, 
     No_Telepon, Email, Username, Kata_Sandi, Status, Is_Deleted, Created_By, Created_Date)
    VALUES
    (@Nama_Customer, @Tanggal_Lahir, @Tempat_Lahir, @Jenis_Kelamin, @Alamat,
     @No_Telepon, @Email, @Username, @Kata_Sandi, 1, 0, 'SYSTEM', GETDATE());

    -- Return ID yang baru dibuat
    SELECT SCOPE_IDENTITY() AS NewCustomerID;
END;
GO

-- ============================================================
-- 4. SP: Update Customer Biodata
-- ============================================================
CREATE OR ALTER PROCEDURE dbo.sp_UpdateCustomerBiodata
    @ID_Customer     INT,
    @Nama_Customer   VARCHAR(20),
    @Username        VARCHAR(20),
    @Jenis_Kelamin   INT,
    @Tanggal_Lahir   DATE,
    @Tempat_Lahir    VARCHAR(50),
    @Alamat          VARCHAR(100),
    @No_Telepon      VARCHAR(15),
    @Email           VARCHAR(50),
    @Modified_By     VARCHAR(50) = 'SYSTEM'
AS
BEGIN
    SET NOCOUNT ON;

    UPDATE Customer
    SET Nama_Customer = @Nama_Customer,
        Username = @Username,
        Jenis_Kelamin = @Jenis_Kelamin,
        Tanggal_Lahir = @Tanggal_Lahir,
        Tempat_Lahir = @Tempat_Lahir,
        Alamat = @Alamat,
        No_Telepon = @No_Telepon,
        Email = @Email,
        Modified_By = @Modified_By,
        Modified_Date = GETDATE()
    WHERE ID_Customer = @ID_Customer AND Is_Deleted = 0;

    SELECT @@ROWCOUNT AS RowsAffected;
END;
GO

-- ============================================================
-- 5. SP: Update Customer Password
-- ============================================================
CREATE OR ALTER PROCEDURE dbo.sp_UpdateCustomerPassword
    @ID_Customer     INT,
    @Kata_Sandi_Baru VARCHAR(255),
    @Modified_By     VARCHAR(50) = 'SYSTEM'
AS
BEGIN
    SET NOCOUNT ON;

    UPDATE Customer
    SET Kata_Sandi = @Kata_Sandi_Baru,
        Modified_By = @Modified_By,
        Modified_Date = GETDATE()
    WHERE ID_Customer = @ID_Customer AND Is_Deleted = 0;

    SELECT @@ROWCOUNT AS RowsAffected;
END;
GO

-- ============================================================
-- 6. SP: Update Customer Photo Profile
-- ============================================================
CREATE OR ALTER PROCEDURE dbo.sp_UpdateCustomerPhoto
    @ID_Customer     INT,
    @Photo_Profile   VARCHAR(255),
    @Modified_By     VARCHAR(50) = 'SYSTEM'
AS
BEGIN
    SET NOCOUNT ON;

    UPDATE Customer
    SET Photo_Profile = @Photo_Profile,
        Modified_By = @Modified_By,
        Modified_Date = GETDATE()
    WHERE ID_Customer = @ID_Customer AND Is_Deleted = 0;

    SELECT @@ROWCOUNT AS RowsAffected;
END;
GO

-- ============================================================
-- 7. SP: Update Customer Status (Toggle Aktif/Nonaktif)
-- ============================================================
CREATE OR ALTER PROCEDURE dbo.sp_UpdateStatusCustomer
    @ID_Customer     INT,
    @NewStatus       INT,           -- 0 = Nonaktif, 1 = Aktif
    @Modified_By     VARCHAR(50) = 'SYSTEM'
AS
BEGIN
    SET NOCOUNT ON;

    UPDATE Customer
    SET Status = @NewStatus,
        Modified_By = @Modified_By,
        Modified_Date = GETDATE()
    WHERE ID_Customer = @ID_Customer AND Is_Deleted = 0;

    SELECT @@ROWCOUNT AS RowsAffected;
END;
GO

-- ============================================================
-- 8. SP: Soft Delete Customer (Delete Account)
-- ============================================================
CREATE OR ALTER PROCEDURE dbo.sp_SoftDeleteCustomer
    @ID_Customer     INT,
    @Deleted_By      VARCHAR(50) = 'SYSTEM'
AS
BEGIN
    SET NOCOUNT ON;

    UPDATE Customer
    SET Is_Deleted = 1,
        Status = 0,
        Deleted_By = @Deleted_By,
        Deleted_Date = GETDATE(),
        Modified_By = @Deleted_By,
        Modified_Date = GETDATE()
    WHERE ID_Customer = @ID_Customer AND Is_Deleted = 0;

    SELECT @@ROWCOUNT AS RowsAffected;
END;
GO

-- ============================================================
-- 9. SP: Check Duplicate Customer Data
-- ============================================================
CREATE OR ALTER PROCEDURE dbo.sp_CheckCustomerDuplicate
    @Username    VARCHAR(20) = NULL,
    @Email       VARCHAR(50) = NULL,
    @No_Telepon  VARCHAR(15) = NULL,
    @ExcludeID   INT = NULL
AS
BEGIN
    SET NOCOUNT ON;

    SELECT 
        ID_Customer,
        Nama_Customer,
        Username,
        Email,
        No_Telepon
    FROM Customer
    WHERE Is_Deleted = 0
        AND (@ExcludeID IS NULL OR ID_Customer != @ExcludeID)
        AND ((@Username IS NOT NULL AND Username = @Username)
            OR (@Email IS NOT NULL AND Email = @Email)
            OR (@No_Telepon IS NOT NULL AND No_Telepon = @No_Telepon));
END;
GO

-- ============================================================
-- 10. SP: Customer Login Authentication
-- ============================================================
CREATE OR ALTER PROCEDURE dbo.sp_CustomerLogin
    @UsernameOrEmail VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;

    SELECT 
        ID_Customer,
        Nama_Customer,
        Username,
        Email,
        No_Telepon,
        Jenis_Kelamin,
        Alamat,
        Tanggal_Lahir,
        Tempat_Lahir,
        Photo_Profile,
        Kata_Sandi,
        Status
    FROM Customer
    WHERE Is_Deleted = 0 
        AND Status = 1
        AND (Username = @UsernameOrEmail OR Email = @UsernameOrEmail)
END;
GO

-- ============================================================
-- 11. SP: Get Customer Statistics
-- ============================================================
CREATE OR ALTER PROCEDURE dbo.sp_GetCustomerStats
AS
BEGIN
    SET NOCOUNT ON;

    SELECT 
        COUNT(*) AS Total,
        SUM(CASE WHEN Status = 1 THEN 1 ELSE 0 END) AS Aktif,
        SUM(CASE WHEN Status = 0 THEN 1 ELSE 0 END) AS Nonaktif
    FROM Customer
    WHERE Is_Deleted = 0;
END;
GO

-- ============================================================
-- 12. SP: Get Customer Transaction Summary
-- ============================================================
CREATE OR ALTER PROCEDURE dbo.sp_GetCustomerTransactionSummary
    @ID_Customer INT
AS
BEGIN
    SET NOCOUNT ON;

    -- Booking Selesai
    DECLARE @BookingSelesai INT;
    SELECT @BookingSelesai = COUNT(*) FROM Booking 
    WHERE ID_Customer = @ID_Customer AND Status = 2;

    -- Booking Mendatang (Berhasil & Tanggal >= Hari Ini)
    DECLARE @BookingMendatang INT;
    SELECT @BookingMendatang = COUNT(*) 
    FROM Booking B
    INNER JOIN Jadwal J ON B.ID_Jadwal = J.ID_Jadwal
    WHERE B.ID_Customer = @ID_Customer AND B.Status = 1 AND J.Tanggal >= CAST(GETDATE() AS DATE);

    -- Pesanan Alat Sukses
    DECLARE @PesananAlat INT;
    SELECT @PesananAlat = COUNT(*) FROM Beli_Alat 
    WHERE ID_Customer = @ID_Customer AND Status = 1;

    -- Total Spending
    DECLARE @TotalSpending DECIMAL(18,2);
    SELECT @TotalSpending = (
        ISNULL((SELECT SUM(Total_Bayar) FROM Booking WHERE ID_Customer = @ID_Customer AND Status IN (1, 2)), 0) +
        ISNULL((SELECT SUM(Total_Bayar) FROM Beli_Alat WHERE ID_Customer = @ID_Customer AND Status = 1), 0) +
        ISNULL((SELECT SUM(Total_Bayar) FROM Langganan WHERE ID_Customer = @ID_Customer AND Status IN (1, 2)), 0)
    );

    -- Member Aktif
    DECLARE @MemberTipe VARCHAR(20) = 'Bukan Member';
    DECLARE @MemberExpiry DATE = NULL;

    SELECT TOP 1 
        @MemberTipe = T.Nama_Tipe,
        @MemberExpiry = L.Tanggal_Selesai
    FROM Langganan L
    INNER JOIN Tipe_Member T ON L.ID_Tipe = T.ID_Tipe
    WHERE L.ID_Customer = @ID_Customer AND L.Status = 1 AND L.Tanggal_Selesai >= CAST(GETDATE() AS DATE)
    ORDER BY L.Tanggal_Selesai DESC;

    SELECT 
        @BookingSelesai AS BookingSelesai,
        @BookingMendatang AS BookingMendatang,
        @PesananAlat AS PesananAlat,
        @TotalSpending AS TotalSpending,
        @MemberTipe AS MemberTipe,
        @MemberExpiry AS MemberExpiry;
END;
GO

-- ============================================================
-- 13. SP: Get Customer Booking History
-- ============================================================
CREATE OR ALTER PROCEDURE dbo.sp_GetCustomerBookingHistory
    @ID_Customer INT
AS
BEGIN
    SET NOCOUNT ON;

    SELECT 
        B.ID_Booking,
        B.Tanggal_Booking,
        B.Metode_Pembayaran,
        B.Total_Bayar,
        B.Status AS BookingStatus,
        J.Tanggal,
        J.Jam_Mulai,
        J.Jam_Selesai,
        L.Nama_Lapangan,
        L.Harga_Sewa,
        P.Nama_Promo,
        P.Diskon
    FROM Booking B
    INNER JOIN Jadwal J ON B.ID_Jadwal = J.ID_Jadwal
    INNER JOIN Lapangan L ON J.ID_Lapangan = L.ID_Lapangan
    LEFT JOIN Promo P ON B.ID_Promo = P.ID_Promo
    WHERE B.ID_Customer = @ID_Customer
    ORDER BY J.Tanggal DESC, J.Jam_Mulai DESC;
END;
GO

-- ============================================================
-- 14. SP: Get Customer Membership History
-- ============================================================
CREATE OR ALTER PROCEDURE dbo.sp_GetCustomerMembershipHistory
    @ID_Customer INT
AS
BEGIN
    SET NOCOUNT ON;

    SELECT 
        L.ID_Langganan,
        L.Tanggal_Mulai,
        L.Tanggal_Selesai,
        L.Total_Bayar,
        L.Metode_Pembayaran,
        L.Status AS MemberStatus,
        T.Nama_Tipe,
        T.Harga_Member,
        T.Potongan_Harga
    FROM Langganan L
    INNER JOIN Tipe_Member T ON L.ID_Tipe = T.ID_Tipe
    WHERE L.ID_Customer = @ID_Customer
    ORDER BY L.Tanggal_Mulai DESC;
END;
GO

-- ============================================================
-- 15. SP: Get Customer Purchase History
-- ============================================================
CREATE OR ALTER PROCEDURE dbo.sp_GetCustomerPurchaseHistory
    @ID_Customer INT
AS
BEGIN
    SET NOCOUNT ON;

    SELECT 
        BA.ID_Beli,
        BA.Tanggal_Beli,
        BA.Metode_Pembayaran,
        BA.Total_Bayar,
        BA.Status AS PurchaseStatus
    FROM Beli_Alat BA
    WHERE BA.ID_Customer = @ID_Customer
    ORDER BY BA.Tanggal_Beli DESC;
END;
GO

-- ============================================================
-- 16. SP: Get Purchase Detail Items
-- ============================================================
CREATE OR ALTER PROCEDURE dbo.sp_GetPurchaseDetailItems
    @ID_Beli INT
AS
BEGIN
    SET NOCOUNT ON;

    SELECT 
        D.Jumlah,
        D.SubTotal,
        A.Nama_Alat,
        A.Harga_Jual
    FROM Detail_Beli_Alat D
    INNER JOIN Alat A ON D.ID_Alat = A.ID_Alat
    WHERE D.ID_Beli = @ID_Beli;
END;
GO

-- ============================================================
-- 17. SP: Restore Soft Deleted Customer
-- ============================================================
CREATE OR ALTER PROCEDURE dbo.sp_RestoreCustomer
    @ID_Customer INT,
    @Modified_By VARCHAR(50) = 'SYSTEM'
AS
BEGIN
    SET NOCOUNT ON;

    UPDATE Customer
    SET Is_Deleted = 0,
        Status = 1,
        Deleted_By = NULL,
        Deleted_Date = NULL,
        Modified_By = @Modified_By,
        Modified_Date = GETDATE()
    WHERE ID_Customer = @ID_Customer AND Is_Deleted = 1;

    SELECT @@ROWCOUNT AS RowsAffected;
END;
GO

-- ============================================================
-- 18. SP: Search Customers by Keyword
-- ============================================================
CREATE OR ALTER PROCEDURE dbo.sp_SearchCustomers
    @Keyword VARCHAR(100),
    @Limit INT = 20
AS
BEGIN
    SET NOCOUNT ON;

    SELECT TOP (@Limit)
        ID_Customer,
        Nama_Customer,
        Email,
        No_Telepon,
        Status,
        Photo_Profile
    FROM Customer
    WHERE Is_Deleted = 0
        AND (@Keyword = '' OR 
            Nama_Customer LIKE '%' + @Keyword + '%' OR 
            Email LIKE '%' + @Keyword + '%' OR 
            No_Telepon LIKE '%' + @Keyword + '%')
    ORDER BY Nama_Customer ASC;
END;
GO

-- ============================================================
-- 19. SP: Get Customer Dashboard Stats (for Admin)
-- ============================================================
CREATE OR ALTER PROCEDURE dbo.sp_GetCustomerDashboardStats
AS
BEGIN
    SET NOCOUNT ON;

    SELECT 
        -- Total counts
        COUNT(*) AS TotalCustomer,
        SUM(CASE WHEN Status = 1 THEN 1 ELSE 0 END) AS TotalAktif,
        SUM(CASE WHEN Status = 0 THEN 1 ELSE 0 END) AS TotalNonaktif,
        SUM(CASE WHEN Is_Deleted = 1 THEN 1 ELSE 0 END) AS TotalDeleted,

        -- New customers this month
        SUM(CASE WHEN MONTH(Created_Date) = MONTH(GETDATE()) AND YEAR(Created_Date) = YEAR(GETDATE()) THEN 1 ELSE 0 END) AS NewThisMonth,

        -- New customers today
        SUM(CASE WHEN CAST(Created_Date AS DATE) = CAST(GETDATE() AS DATE) THEN 1 ELSE 0 END) AS NewToday,

        -- Top customer by bookings
        (SELECT TOP 1 C.Nama_Customer 
         FROM Customer C 
         INNER JOIN Booking B ON C.ID_Customer = B.ID_Customer 
         WHERE C.Is_Deleted = 0
         GROUP BY C.ID_Customer, C.Nama_Customer 
         ORDER BY COUNT(*) DESC) AS TopCustomer
    FROM Customer
    WHERE Is_Deleted = 0;
END;
GO

-- ============================================================
-- 20. SP: Validate Customer Password (for delete account)
-- ============================================================
CREATE OR ALTER PROCEDURE dbo.sp_ValidateCustomerPassword
    @ID_Customer     INT,
    @Kata_Sandi VARCHAR(255)
AS
BEGIN
    SET NOCOUNT ON;

    SELECT 
        CASE WHEN EXISTS (
            SELECT 1 FROM Customer 
            WHERE ID_Customer = @ID_Customer 
                AND Is_Deleted = 0 
                AND Kata_Sandi = @Kata_Sandi
        ) THEN 1 ELSE 0 END AS IsValid;
END;
GO

PRINT '============================================================';
PRINT 'All Customer Stored Procedures created successfully!';
PRINT '============================================================';
PRINT '';
PRINT 'List of SPs:';
PRINT '  1. sp_ReadCustomerListWithCount  - Get paginated customer list';
PRINT '  2. sp_GetCustomerDetail          - Get single customer detail';
PRINT '  3. sp_CreateCustomer             - Register new customer';
PRINT '  4. sp_UpdateCustomerBiodata      - Update customer biodata';
PRINT '  5. sp_UpdateCustomerPassword       - Update customer password';
PRINT '  6. sp_UpdateCustomerPhoto          - Update profile photo';
PRINT '  7. sp_UpdateStatusCustomer         - Toggle active/inactive';
PRINT '  8. sp_SoftDeleteCustomer           - Soft delete account';
PRINT '  9. sp_CheckCustomerDuplicate       - Check duplicate data';
PRINT ' 10. sp_CustomerLogin                - Login authentication';
PRINT ' 11. sp_GetCustomerStats             - Get statistics';
PRINT ' 12. sp_GetCustomerTransactionSummary - Transaction summary';
PRINT ' 13. sp_GetCustomerBookingHistory    - Booking history';
PRINT ' 14. sp_GetCustomerMembershipHistory - Membership history';
PRINT ' 15. sp_GetCustomerPurchaseHistory   - Purchase history';
PRINT ' 16. sp_GetPurchaseDetailItems       - Purchase items detail';
PRINT ' 17. sp_RestoreCustomer              - Restore deleted customer';
PRINT ' 18. sp_SearchCustomers              - Search customers';
PRINT ' 19. sp_GetCustomerDashboardStats    - Admin dashboard stats';
PRINT ' 20. sp_ValidateCustomerPassword     - Validate password';
PRINT '============================================================';
GO