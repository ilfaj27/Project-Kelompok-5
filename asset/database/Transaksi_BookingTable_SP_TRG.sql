-- ============================================================================
-- 1. STORED PROCEDURES (CRUD & TRANSAKSI)
-- ============================================================================

-- [READ] Mengambil foto profil karyawan
CREATE PROCEDURE sp_Karyawan_GetProfilePhoto
    @ID_Karyawan INT
AS
BEGIN
    SET NOCOUNT ON;
    SELECT Photo_Profile FROM Karyawan WHERE ID_Karyawan = @ID_Karyawan;
END;
GO

-- [UPDATE/PROCESS] Otomatis mengubah status booking yang lewat waktu menjadi 'Selesai' (2)
CREATE PROCEDURE sp_Booking_AutoComplete
AS
BEGIN
    SET NOCOUNT ON;
    BEGIN TRANSACTION;
    BEGIN TRY
        DECLARE @ExpiredBookings TABLE (ID_Booking INT);

        -- Mengambil ID Booking berstatus 'Berhasil' (1) yang jam bermainnya telah terlewati
        INSERT INTO @ExpiredBookings (ID_Booking)
        SELECT B.ID_Booking 
        FROM Booking B 
        INNER JOIN Jadwal J ON B.ID_Jadwal = J.ID_Jadwal 
        WHERE B.Status = 1 
        AND (J.Tanggal < CAST(GETDATE() AS DATE) 
             OR (J.Tanggal = CAST(GETDATE() AS DATE) 
                 AND J.Jam_Selesai <= CAST(GETDATE() AS TIME)));

        -- Update status transaksi menjadi Selesai (2)
        UPDATE Booking 
        SET Status = 2, 
            Modified_By = 'SYSTEM_AUTO', 
            Modified_Date = GETDATE() 
        WHERE ID_Booking IN (SELECT ID_Booking FROM @ExpiredBookings);

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        THROW;
    END CATCH
END;
GO

-- [UPDATE] Mengkonfirmasi pembayaran booking (Status berubah menjadi 1)
CREATE PROCEDURE sp_Booking_ConfirmPayment
    @ID_Booking INT,
    @Modified_By VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;
    UPDATE Booking 
    SET Status = 1, 
        Modified_By = @Modified_By, 
        Modified_Date = GETDATE() 
    WHERE ID_Booking = @ID_Booking AND Status = 0;
END;
GO

-- [READ] Mengambil detail satu booking untuk proses pembatalan
CREATE PROCEDURE sp_Booking_GetDetail
    @ID_Booking INT
AS
BEGIN
    SET NOCOUNT ON;
    
    SELECT 
           -- Ambil semua data bawaan dari table booking
           B.*, 
           -- Ambil data dari tabel Customer
           C.Nama_Customer, C.Email, C.No_Telepon,
           -- Ambil data dari tabel Lapangan
           L.Nama_Lapangan, L.Harga_Sewa,
           -- Ambil data dari tabel Jadwal (TANGGAL & JAM ADA DI SINI)
           J.Tanggal, J.Jam_Mulai, J.Jam_Selesai,
           -- Ambil data dari tabel Promo
           P.Nama_Promo, P.Diskon,
           -- Ambil nama admin/karyawan pencatat
           K.Nama_Karyawan as Nama_Karyawan_Input
    FROM Booking B 
    -- Melakukan relasi antar tabel (JOIN)
    INNER JOIN Customer C ON B.ID_Customer = C.ID_Customer
    INNER JOIN Jadwal J ON B.ID_Jadwal = J.ID_Jadwal 
    INNER JOIN Lapangan L ON J.ID_Lapangan = L.ID_Lapangan
    LEFT JOIN Promo P ON B.ID_Promo = P.ID_Promo
    LEFT JOIN Karyawan K ON B.ID_Karyawan = K.ID_Karyawan
    WHERE B.ID_Booking = @ID_Booking;
END;
GO


-- [CREATE/UPDATE] Memproses pembatalan booking dan pencatatan refund secara transaksional
CREATE PROCEDURE sp_Booking_CancelByKaryawan
   @ID_Booking INT,
    @ID_Karyawan INT,
    @Alasan VARCHAR(255),
    @Biaya_Batal DECIMAL(18,2),     -- Tetap dipertahankan sebagai parameter agar PHP tidak error
    @Nominal_Refund DECIMAL(18,2),   -- Tetap dipertahankan sebagai parameter agar PHP tidak error
    @Metode_Refund VARCHAR(20),
    @Modified_By VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;
    
    DECLARE @Total_Bayar DECIMAL(18,2);

    -- 1. Ambil nilai total pembayaran asli dari tabel Booking
    SELECT @Total_Bayar = Total_Bayar 
    FROM Booking 
    WHERE ID_Booking = @ID_Booking;

    -- 2. PAKSA HITUNG ULANG DI DATABASE (Mengabaikan input mentah dari PHP)
    SET @Biaya_Batal = @Total_Bayar * 0.50;
    SET @Nominal_Refund = @Total_Bayar * 0.50;

    BEGIN TRANSACTION;
    BEGIN TRY
        -- 3. Update status Booking menjadi Dibatalkan (3)
        UPDATE Booking 
        SET Status = 3, 
            Modified_By = @Modified_By, 
            Modified_Date = GETDATE() 
        WHERE ID_Booking = @ID_Booking;

        -- 4. Catat transaksi ke tabel Pembatalan_Booking dengan nilai denda yang sudah dikunci 50%
        INSERT INTO Pembatalan_Booking (
            ID_Booking, ID_Karyawan, Tanggal_Batal, Alasan, 
            Biaya_Batal, Nominal_Refund, Metode_Refund, Status, 
            Created_By, Created_Date
        ) 
        VALUES (
            @ID_Booking, @ID_Karyawan, GETDATE(), @Alasan, 
            @Biaya_Batal, @Nominal_Refund, @Metode_Refund, 1, 
            @Modified_By, GETDATE()
        );

        COMMIT TRANSACTION;
        SELECT 'SUCCESS' AS Status, 'Pembatalan berhasil diproses dengan denda 50%.' AS Message;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        SELECT 'ERROR' AS Status, ERROR_MESSAGE() AS Message;
    END CATCH
END;
GO

-- [READ] Mengambil total jumlah data booking terfilter untuk keperluan pagination
CREATE PROCEDURE sp_Booking_GetCount
    @FilterStatus INT,
    @FilterCustomer VARCHAR(100),
    @FilterTanggal DATE
AS
BEGIN
    SET NOCOUNT ON;
    SELECT COUNT(*) as total 
    FROM Booking B
    INNER JOIN Customer C ON B.ID_Customer = C.ID_Customer
    INNER JOIN Jadwal J ON B.ID_Jadwal = J.ID_Jadwal
    INNER JOIN Lapangan L ON J.ID_Lapangan = L.ID_Lapangan
    WHERE (@FilterStatus = -1 OR B.Status = @FilterStatus)
      AND (@FilterCustomer = '' OR C.Nama_Customer LIKE '%' + @FilterCustomer + '%')
      AND (@FilterTanggal IS NULL OR CAST(B.Tanggal_Booking AS DATE) = @FilterTanggal);
END;
GO

-- [READ] Mengambil daftar data booking terfilter (dengan offset dan limit pagination)
CREATE PROCEDURE sp_Booking_GetPagedList
    @FilterStatus INT,
    @FilterCustomer VARCHAR(100),
    @FilterTanggal DATE,
    @Offset INT,
    @Limit INT
AS
BEGIN
    SET NOCOUNT ON;
    SELECT B.ID_Booking, B.ID_Customer, B.ID_Karyawan, B.ID_Jadwal, B.ID_Promo, 
           B.Tanggal_Booking, B.Metode_Pembayaran, B.Bukti_Pembayaran, B.Total_Bayar, B.Status,
           B.Created_Date, B.Modified_Date,
           C.Nama_Customer, C.Email, C.No_Telepon,
           L.Nama_Lapangan, L.Harga_Sewa,
           J.Tanggal, J.Jam_Mulai, J.Jam_Selesai,
           P.Nama_Promo, P.Diskon,
           K.Nama_Karyawan as Nama_Karyawan_Input
    FROM Booking B
    INNER JOIN Customer C ON B.ID_Customer = C.ID_Customer
    INNER JOIN Jadwal J ON B.ID_Jadwal = J.ID_Jadwal
    INNER JOIN Lapangan L ON J.ID_Lapangan = L.ID_Lapangan
    LEFT JOIN Promo P ON B.ID_Promo = P.ID_Promo
    LEFT JOIN Karyawan K ON B.ID_Karyawan = K.ID_Karyawan
    WHERE (@FilterStatus = -1 OR B.Status = @FilterStatus)
      AND (@FilterCustomer = '' OR C.Nama_Customer LIKE '%' + @FilterCustomer + '%')
      AND (@FilterTanggal IS NULL OR CAST(B.Tanggal_Booking AS DATE) = @FilterTanggal)
    ORDER BY 
        CASE 
            WHEN B.Status = 0 THEN 0
            WHEN B.Status = 1 THEN 1
            WHEN B.Status = 2 THEN 2
            WHEN B.Status = 3 THEN 3
        END ASC,
        B.Tanggal_Booking DESC
    OFFSET @Offset ROWS FETCH NEXT @Limit ROWS ONLY;
END;
GO

-- ============================================================================
-- 2. USER DEFINED FUNCTION (SUMBER DATA DASHBOARD STATISTIK)
-- ============================================================================
CREATE FUNCTION fn_Booking_GetDashboardStats (
    @FilterStatus INT,
    @FilterCustomer VARCHAR(100),
    @FilterTanggal DATE
)
RETURNS TABLE
AS
RETURN
(
    SELECT 
        COUNT(*) AS Total,
        SUM(CASE WHEN B.Status = 0 THEN 1 ELSE 0 END) AS Menunggu,
        SUM(CASE WHEN B.Status = 1 THEN 1 ELSE 0 END) AS Berhasil,
        SUM(CASE WHEN B.Status = 2 THEN 1 ELSE 0 END) AS Selesai,
        SUM(CASE WHEN B.Status = 3 THEN 1 ELSE 0 END) AS Dibatalkan,
        SUM(CASE WHEN B.Status IN (1, 2) THEN B.Total_Bayar ELSE 0 END) AS Total_Omzet,
        SUM(CASE WHEN B.Status = 3 THEN B.Total_Bayar * 0.5 ELSE 0 END) AS Total_Refund
    FROM Booking B
    INNER JOIN Customer C ON B.ID_Customer = C.ID_Customer
    INNER JOIN Jadwal J ON B.ID_Jadwal = J.ID_Jadwal
    INNER JOIN Lapangan L ON J.ID_Lapangan = L.ID_Lapangan
    WHERE (@FilterStatus = -1 OR B.Status = @FilterStatus)
      AND (@FilterCustomer = '' OR C.Nama_Customer LIKE '%' + @FilterCustomer + '%')
      AND (@FilterTanggal IS NULL OR CAST(B.Tanggal_Booking AS DATE) = @FilterTanggal)
);
GO