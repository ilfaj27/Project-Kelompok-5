
/*
============================================================
 TAMBAHAN SCRIPT HOOPBALL
 SP  : untuk proses input/update data MASTER dan TRANSAKSI
 UDF : untuk sumber data LAPORAN dan DASHBOARD
 TRG : untuk proses otomatis transaksi + log history
============================================================

CARA PAKAI:
1. Jalankan dulu script CREATE DATABASE dan CREATE TABLE Hoopball kamu.
2. Jangan jalankan "DROP DATABASE Hoopball" di bagian akhir script lama.
3. Setelah database dan tabel sudah ada, jalankan script ini.
*/

USE Hoopball;
GO

/* ============================================================
   1. TABEL LOG HISTORY
   Fungsi:
   - Menyimpan catatan perubahan data transaksi.
   - Dipakai oleh trigger agar setiap INSERT/UPDATE transaksi punya jejak riwayat.
   ============================================================ */
IF OBJECT_ID('dbo.Log_History', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.Log_History (
        ID_Log          INT IDENTITY(1,1) PRIMARY KEY,
        Nama_Tabel      VARCHAR(50)  NOT NULL,
        ID_Data         VARCHAR(50)  NOT NULL,
        Aksi            VARCHAR(20)  NOT NULL,
        Data_Lama       VARCHAR(MAX) NULL,
        Data_Baru       VARCHAR(MAX) NULL,
        Created_By      VARCHAR(50)  NOT NULL,
        Created_Date    DATETIME     NOT NULL DEFAULT GETDATE()
    );
END;
GO


/* ============================================================
   2. STORED PROCEDURE MASTER
   Catatan:
   - Data master tidak diinput langsung dengan INSERT biasa dari aplikasi.
   - Aplikasi memanggil EXEC sp_Insert... atau EXEC sp_Update...
   ============================================================ */

-- MASTER: Karyawan
CREATE OR ALTER PROCEDURE dbo.sp_InsertKaryawan
    @NIK VARCHAR(16),
    @Nama_Karyawan VARCHAR(20),
    @Tanggal_Lahir DATE,
    @Tempat_Lahir VARCHAR(50),
    @Alamat VARCHAR(100),
    @Jenis_Kelamin INT,
    @Jabatan INT,
    @No_Telepon VARCHAR(15),
    @Email VARCHAR(50),
    @Username VARCHAR(20),
    @Kata_Sandi VARCHAR(20),
    @Status INT,
    @Created_By VARCHAR(50),
    @Photo_Profile VARCHAR(255) = NULL
AS
BEGIN
    SET NOCOUNT ON;

    INSERT INTO dbo.Karyawan
    (NIK, Nama_Karyawan, Tanggal_Lahir, Tempat_Lahir, Alamat, Jenis_Kelamin, Jabatan,
     No_Telepon, Email, Username, Kata_Sandi, Status, Photo_Profile, Is_Deleted, Created_By, Created_Date)
    VALUES
    (@NIK, @Nama_Karyawan, @Tanggal_Lahir, @Tempat_Lahir, @Alamat, @Jenis_Kelamin, @Jabatan,
     @No_Telepon, @Email, @Username, @Kata_Sandi, @Status, @Photo_Profile, 0, @Created_By, GETDATE());
END;
GO

CREATE OR ALTER PROCEDURE dbo.sp_UpdateKaryawan
    @ID_Karyawan INT,
    @NIK VARCHAR(16),
    @Nama_Karyawan VARCHAR(20),
    @Tanggal_Lahir DATE,
    @Tempat_Lahir VARCHAR(50),
    @Alamat VARCHAR(100),
    @Jenis_Kelamin INT,
    @Jabatan INT,
    @No_Telepon VARCHAR(15),
    @Email VARCHAR(50),
    @Username VARCHAR(20),
    @Status INT,
    @Modified_By VARCHAR(50),
    @Photo_Profile VARCHAR(255) = NULL
AS
BEGIN
    SET NOCOUNT ON;

    UPDATE dbo.Karyawan
    SET NIK = @NIK,
        Nama_Karyawan = @Nama_Karyawan,
        Tanggal_Lahir = @Tanggal_Lahir,
        Tempat_Lahir = @Tempat_Lahir,
        Alamat = @Alamat,
        Jenis_Kelamin = @Jenis_Kelamin,
        Jabatan = @Jabatan,
        No_Telepon = @No_Telepon,
        Email = @Email,
        Username = @Username,
        Status = @Status,
        Photo_Profile = @Photo_Profile,
        Modified_By = @Modified_By,
        Modified_Date = GETDATE()
    WHERE ID_Karyawan = @ID_Karyawan
      AND Is_Deleted = 0;
END;
GO


-- MASTER: Customer
CREATE OR ALTER PROCEDURE dbo.sp_InsertCustomer
    @Nama_Customer VARCHAR(20),
    @Tanggal_Lahir DATE,
    @Tempat_Lahir VARCHAR(50),
    @Jenis_Kelamin INT,
    @Alamat VARCHAR(100),
    @No_Telepon VARCHAR(15),
    @Email VARCHAR(50),
    @Username VARCHAR(20),
    @Kata_Sandi VARCHAR(20),
    @Status INT,
    @Created_By VARCHAR(50),
    @Photo_Profile VARCHAR(255) = NULL
AS
BEGIN
    SET NOCOUNT ON;

    INSERT INTO dbo.Customer
    (Nama_Customer, Tanggal_Lahir, Tempat_Lahir, Jenis_Kelamin, Alamat, No_Telepon,
     Email, Username, Kata_Sandi, Status, Photo_Profile, Is_Deleted, Created_By, Created_Date)
    VALUES
    (@Nama_Customer, @Tanggal_Lahir, @Tempat_Lahir, @Jenis_Kelamin, @Alamat, @No_Telepon,
     @Email, @Username, @Kata_Sandi, @Status, @Photo_Profile, 0, @Created_By, GETDATE());
END;
GO

CREATE OR ALTER PROCEDURE dbo.sp_UpdateCustomer
    @ID_Customer INT,
    @Nama_Customer VARCHAR(20),
    @Tanggal_Lahir DATE,
    @Tempat_Lahir VARCHAR(50),
    @Jenis_Kelamin INT,
    @Alamat VARCHAR(100),
    @No_Telepon VARCHAR(15),
    @Email VARCHAR(50),
    @Username VARCHAR(20),
    @Status INT,
    @Modified_By VARCHAR(50),
    @Photo_Profile VARCHAR(255) = NULL
AS
BEGIN
    SET NOCOUNT ON;

    UPDATE dbo.Customer
    SET Nama_Customer = @Nama_Customer,
        Tanggal_Lahir = @Tanggal_Lahir,
        Tempat_Lahir = @Tempat_Lahir,
        Jenis_Kelamin = @Jenis_Kelamin,
        Alamat = @Alamat,
        No_Telepon = @No_Telepon,
        Email = @Email,
        Username = @Username,
        Status = @Status,
        Photo_Profile = @Photo_Profile,
        Modified_By = @Modified_By,
        Modified_Date = GETDATE()
    WHERE ID_Customer = @ID_Customer
      AND Is_Deleted = 0;
END;
GO


-- MASTER: Lapangan
CREATE OR ALTER PROCEDURE dbo.sp_InsertLapangan
    @Nama_Lapangan VARCHAR(25),
    @Harga_Sewa DECIMAL(18,2),
    @Status INT,
    @Created_By VARCHAR(50),
    @Photo_Lapangan VARCHAR(255) = NULL
AS
BEGIN
    SET NOCOUNT ON;

    INSERT INTO dbo.Lapangan
    (Nama_Lapangan, Harga_Sewa, Photo_Lapangan, Status, Is_Deleted, Created_By, Created_Date)
    VALUES
    (@Nama_Lapangan, @Harga_Sewa, @Photo_Lapangan, @Status, 0, @Created_By, GETDATE());
END;
GO

CREATE OR ALTER PROCEDURE dbo.sp_UpdateLapangan
    @ID_Lapangan INT,
    @Nama_Lapangan VARCHAR(25),
    @Harga_Sewa DECIMAL(18,2),
    @Status INT,
    @Modified_By VARCHAR(50),
    @Photo_Lapangan VARCHAR(255) = NULL
AS
BEGIN
    SET NOCOUNT ON;

    UPDATE dbo.Lapangan
    SET Nama_Lapangan = @Nama_Lapangan,
        Harga_Sewa = @Harga_Sewa,
        Photo_Lapangan = @Photo_Lapangan,
        Status = @Status,
        Modified_By = @Modified_By,
        Modified_Date = GETDATE()
    WHERE ID_Lapangan = @ID_Lapangan
      AND Is_Deleted = 0;
END;
GO


-- MASTER: Tipe Member
CREATE OR ALTER PROCEDURE dbo.sp_InsertTipeMember
    @Nama_Tipe VARCHAR(10),
    @Harga_Member DECIMAL(18,2),
    @Potongan_Harga DECIMAL(18,2),
    @Status INT,
    @Created_By VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;

    INSERT INTO dbo.Tipe_Member
    (Nama_Tipe, Harga_Member, Potongan_Harga, Status, Is_Deleted, Created_By, Created_Date)
    VALUES
    (@Nama_Tipe, @Harga_Member, @Potongan_Harga, @Status, 0, @Created_By, GETDATE());
END;
GO

CREATE OR ALTER PROCEDURE dbo.sp_UpdateTipeMember
    @ID_Tipe INT,
    @Nama_Tipe VARCHAR(10),
    @Harga_Member DECIMAL(18,2),
    @Potongan_Harga DECIMAL(18,2),
    @Status INT,
    @Modified_By VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;

    UPDATE dbo.Tipe_Member
    SET Nama_Tipe = @Nama_Tipe,
        Harga_Member = @Harga_Member,
        Potongan_Harga = @Potongan_Harga,
        Status = @Status,
        Modified_By = @Modified_By,
        Modified_Date = GETDATE()
    WHERE ID_Tipe = @ID_Tipe
      AND Is_Deleted = 0;
END;
GO


-- MASTER: Promo
CREATE OR ALTER PROCEDURE dbo.sp_InsertPromo
    @Nama_Promo VARCHAR(50),
    @Diskon DECIMAL(18,2),
    @Tanggal_Mulai DATE,
    @Tanggal_Selesai DATE,
    @Status INT,
    @Created_By VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;

    IF @Tanggal_Mulai > @Tanggal_Selesai
        THROW 50001, 'Tanggal mulai promo tidak boleh lebih besar dari tanggal selesai.', 1;

    INSERT INTO dbo.Promo
    (Nama_Promo, Diskon, Tanggal_Mulai, Tanggal_Selesai, Status, Is_Deleted, Created_By, Created_Date)
    VALUES
    (@Nama_Promo, @Diskon, @Tanggal_Mulai, @Tanggal_Selesai, @Status, 0, @Created_By, GETDATE());
END;
GO

CREATE OR ALTER PROCEDURE dbo.sp_UpdatePromo
    @ID_Promo INT,
    @Nama_Promo VARCHAR(50),
    @Diskon DECIMAL(18,2),
    @Tanggal_Mulai DATE,
    @Tanggal_Selesai DATE,
    @Status INT,
    @Modified_By VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;

    IF @Tanggal_Mulai > @Tanggal_Selesai
        THROW 50002, 'Tanggal mulai promo tidak boleh lebih besar dari tanggal selesai.', 1;

    UPDATE dbo.Promo
    SET Nama_Promo = @Nama_Promo,
        Diskon = @Diskon,
        Tanggal_Mulai = @Tanggal_Mulai,
        Tanggal_Selesai = @Tanggal_Selesai,
        Status = @Status,
        Modified_By = @Modified_By,
        Modified_Date = GETDATE()
    WHERE ID_Promo = @ID_Promo
      AND Is_Deleted = 0;
END;
GO


-- MASTER: Fasilitas Lapangan
CREATE OR ALTER PROCEDURE dbo.sp_InsertFasilitasLapangan
    @ID_Lapangan INT,
    @Nama_Fasilitas VARCHAR(25),
    @Detail_Fasilitas VARCHAR(50),
    @Status INT,
    @Created_By VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;

    INSERT INTO dbo.Fasilitas_Lapangan
    (ID_Lapangan, Nama_Fasilitas, Detail_Fasilitas, Status, Is_Deleted, Created_By, Created_Date)
    VALUES
    (@ID_Lapangan, @Nama_Fasilitas, @Detail_Fasilitas, @Status, 0, @Created_By, GETDATE());
END;
GO

CREATE OR ALTER PROCEDURE dbo.sp_UpdateFasilitasLapangan
    @ID_Fasilitas INT,
    @ID_Lapangan INT,
    @Nama_Fasilitas VARCHAR(25),
    @Detail_Fasilitas VARCHAR(50),
    @Status INT,
    @Modified_By VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;

    UPDATE dbo.Fasilitas_Lapangan
    SET ID_Lapangan = @ID_Lapangan,
        Nama_Fasilitas = @Nama_Fasilitas,
        Detail_Fasilitas = @Detail_Fasilitas,
        Status = @Status,
        Modified_By = @Modified_By,
        Modified_Date = GETDATE()
    WHERE ID_Fasilitas = @ID_Fasilitas
      AND Is_Deleted = 0;
END;
GO


-- MASTER: Jadwal
CREATE OR ALTER PROCEDURE dbo.sp_InsertJadwal
    @ID_Lapangan INT,
    @Tanggal DATE,
    @Jam_Mulai TIME,
    @Jam_Selesai TIME,
    @Status INT,
    @Created_By VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;

    IF @Jam_Mulai >= @Jam_Selesai
        THROW 50003, 'Jam mulai harus lebih kecil dari jam selesai.', 1;

    INSERT INTO dbo.Jadwal
    (ID_Lapangan, Tanggal, Jam_Mulai, Jam_Selesai, Status, Is_Deleted, Created_By, Created_Date)
    VALUES
    (@ID_Lapangan, @Tanggal, @Jam_Mulai, @Jam_Selesai, @Status, 0, @Created_By, GETDATE());
END;
GO

CREATE OR ALTER PROCEDURE dbo.sp_UpdateJadwal
    @ID_Jadwal INT,
    @ID_Lapangan INT,
    @Tanggal DATE,
    @Jam_Mulai TIME,
    @Jam_Selesai TIME,
    @Status INT,
    @Modified_By VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;

    IF @Jam_Mulai >= @Jam_Selesai
        THROW 50004, 'Jam mulai harus lebih kecil dari jam selesai.', 1;

    UPDATE dbo.Jadwal
    SET ID_Lapangan = @ID_Lapangan,
        Tanggal = @Tanggal,
        Jam_Mulai = @Jam_Mulai,
        Jam_Selesai = @Jam_Selesai,
        Status = @Status,
        Modified_By = @Modified_By,
        Modified_Date = GETDATE()
    WHERE ID_Jadwal = @ID_Jadwal
      AND Is_Deleted = 0;
END;
GO


-- MASTER: Alat
CREATE OR ALTER PROCEDURE dbo.sp_InsertAlat
    @Nama_Alat VARCHAR(25),
    @Stok INT,
    @Harga_Alat DECIMAL(18,2),
    @Status INT,
    @Created_By VARCHAR(50),
    @Photo_Alat VARCHAR(255) = NULL
AS
BEGIN
    SET NOCOUNT ON;

    INSERT INTO dbo.Alat
    (Nama_Alat, Stok, Harga_Alat, Photo_Alat, Status, Is_Deleted, Created_By, Created_Date)
    VALUES
    (@Nama_Alat, @Stok, @Harga_Alat, @Photo_Alat, @Status, 0, @Created_By, GETDATE());
END;
GO

CREATE OR ALTER PROCEDURE dbo.sp_UpdateAlat
    @ID_Alat INT,
    @Nama_Alat VARCHAR(25),
    @Stok INT,
    @Harga_Alat DECIMAL(18,2),
    @Status INT,
    @Modified_By VARCHAR(50),
    @Photo_Alat VARCHAR(255) = NULL
AS
BEGIN
    SET NOCOUNT ON;

    UPDATE dbo.Alat
    SET Nama_Alat = @Nama_Alat,
        Stok = @Stok,
        Harga_Alat = @Harga_Alat,
        Photo_Alat = @Photo_Alat,
        Status = @Status,
        Modified_By = @Modified_By,
        Modified_Date = GETDATE()
    WHERE ID_Alat = @ID_Alat
      AND Is_Deleted = 0;
END;
GO


/* ============================================================
   3. STORED PROCEDURE TRANSAKSI
   Catatan:
   - Insert/update transaksi wajib lewat SP.
   - Beberapa proses otomatis dilanjutkan oleh trigger.
   ============================================================ */

-- TRANSAKSI: Booking
CREATE OR ALTER PROCEDURE dbo.sp_InsertBooking
    @ID_Customer INT,
    @ID_Karyawan INT,
    @ID_Jadwal INT,
    @Tanggal_Booking DATE,
    @Metode_Pembayaran VARCHAR(20),
    @Total_Bayar DECIMAL(18,2),
    @Created_By VARCHAR(50),
    @Status INT = 0,
    @ID_Promo INT = NULL
AS
BEGIN
    SET NOCOUNT ON;

    -- Cek agar jadwal yang sudah dipesan tidak bisa dipakai lagi.
    IF EXISTS (
        SELECT 1
        FROM dbo.Booking
        WHERE ID_Jadwal = @ID_Jadwal
          AND Status <> 3
    )
        THROW 51001, 'Jadwal ini sudah memiliki booking aktif.', 1;

    -- Cek jadwal masih tersedia.
    IF NOT EXISTS (
        SELECT 1
        FROM dbo.Jadwal
        WHERE ID_Jadwal = @ID_Jadwal
          AND Status = 1
          AND Is_Deleted = 0
    )
        THROW 51002, 'Jadwal tidak tersedia.', 1;

    INSERT INTO dbo.Booking
    (ID_Customer, ID_Karyawan, ID_Jadwal, ID_Promo, Tanggal_Booking,
     Metode_Pembayaran, Total_Bayar, Status, Created_By, Created_Date)
    VALUES
    (@ID_Customer, @ID_Karyawan, @ID_Jadwal, @ID_Promo, @Tanggal_Booking,
     @Metode_Pembayaran, @Total_Bayar, @Status, @Created_By, GETDATE());
END;
GO

CREATE OR ALTER PROCEDURE dbo.sp_UpdateStatusBooking
    @ID_Booking INT,
    @Status INT,
    @Modified_By VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;

    UPDATE dbo.Booking
    SET Status = @Status,
        Modified_By = @Modified_By,
        Modified_Date = GETDATE()
    WHERE ID_Booking = @ID_Booking;
END;
GO


-- TRANSAKSI: Pembatalan Booking
CREATE OR ALTER PROCEDURE dbo.sp_InsertPembatalanBooking
    @ID_Booking INT,
    @ID_Karyawan INT,
    @Tanggal_Batal DATE,
    @Alasan VARCHAR(255),
    @Metode_Refund VARCHAR(20),
    @Created_By VARCHAR(50),
    @Status INT = 0
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE @Total_Bayar DECIMAL(18,2);

    SELECT @Total_Bayar = Total_Bayar
    FROM dbo.Booking
    WHERE ID_Booking = @ID_Booking;

    IF @Total_Bayar IS NULL
        THROW 52001, 'Data booking tidak ditemukan.', 1;

    INSERT INTO dbo.Pembatalan_Booking
    (ID_Booking, ID_Karyawan, Tanggal_Batal, Alasan, Biaya_Batal,
     Nominal_Refund, Metode_Refund, Status, Created_By, Created_Date)
    VALUES
    (@ID_Booking, @ID_Karyawan, @Tanggal_Batal, @Alasan,
     @Total_Bayar * 0.5, @Total_Bayar * 0.5, @Metode_Refund, @Status, @Created_By, GETDATE());
END;
GO

CREATE OR ALTER PROCEDURE dbo.sp_UpdateStatusPembatalanBooking
    @ID_Pembatalan INT,
    @Status INT,
    @Modified_By VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;

    UPDATE dbo.Pembatalan_Booking
    SET Status = @Status,
        Modified_By = @Modified_By,
        Modified_Date = GETDATE()
    WHERE ID_Pembatalan = @ID_Pembatalan;
END;
GO


-- TRANSAKSI: Langganan
CREATE OR ALTER PROCEDURE dbo.sp_InsertLangganan
    @ID_Customer INT,
    @ID_Karyawan INT,
    @ID_Tipe INT,
    @Tanggal_Mulai DATE,
    @Tanggal_Selesai DATE,
    @Metode_Pembayaran VARCHAR(20),
    @Created_By VARCHAR(50),
    @Status INT = 0
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE @Harga_Member DECIMAL(18,2);

    IF @Tanggal_Mulai > @Tanggal_Selesai
        THROW 53001, 'Tanggal mulai langganan tidak boleh lebih besar dari tanggal selesai.', 1;

    SELECT @Harga_Member = Harga_Member
    FROM dbo.Tipe_Member
    WHERE ID_Tipe = @ID_Tipe
      AND Status = 1
      AND Is_Deleted = 0;

    IF @Harga_Member IS NULL
        THROW 53002, 'Tipe member tidak aktif atau tidak ditemukan.', 1;

    INSERT INTO dbo.Langganan
    (ID_Customer, ID_Karyawan, ID_Tipe, Tanggal_Mulai, Tanggal_Selesai,
     Total_Bayar, Metode_Pembayaran, Status, Created_By, Created_Date)
    VALUES
    (@ID_Customer, @ID_Karyawan, @ID_Tipe, @Tanggal_Mulai, @Tanggal_Selesai,
     @Harga_Member, @Metode_Pembayaran, @Status, @Created_By, GETDATE());
END;
GO

CREATE OR ALTER PROCEDURE dbo.sp_UpdateStatusLangganan
    @ID_Langganan INT,
    @Status INT,
    @Modified_By VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;

    UPDATE dbo.Langganan
    SET Status = @Status,
        Modified_By = @Modified_By,
        Modified_Date = GETDATE()
    WHERE ID_Langganan = @ID_Langganan;
END;
GO


-- TRANSAKSI: Beli Alat
CREATE OR ALTER PROCEDURE dbo.sp_InsertBeliAlat
    @ID_Karyawan INT,
    @ID_Customer INT,
    @Tanggal_Beli DATE,
    @Metode_Pembayaran VARCHAR(20),
    @Created_By VARCHAR(50),
    @Status INT = 0
AS
BEGIN
    SET NOCOUNT ON;

    -- Total_Bayar diisi 0 dulu.
    -- Nanti akan dihitung otomatis oleh trigger setelah detail pembelian dimasukkan.
    INSERT INTO dbo.Beli_Alat
    (ID_Karyawan, ID_Customer, Tanggal_Beli, Metode_Pembayaran, Total_Bayar,
     Status, Created_By, Created_Date)
    VALUES
    (@ID_Karyawan, @ID_Customer, @Tanggal_Beli, @Metode_Pembayaran, 0,
     @Status, @Created_By, GETDATE());
END;
GO

CREATE OR ALTER PROCEDURE dbo.sp_UpdateStatusBeliAlat
    @ID_Beli INT,
    @Status INT,
    @Modified_By VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;

    UPDATE dbo.Beli_Alat
    SET Status = @Status,
        Modified_By = @Modified_By,
        Modified_Date = GETDATE()
    WHERE ID_Beli = @ID_Beli;
END;
GO

CREATE OR ALTER PROCEDURE dbo.sp_InsertDetailBeliAlat
    @ID_Beli INT,
    @ID_Alat INT,
    @Jumlah INT
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE @Harga_Alat DECIMAL(18,2);

    SELECT @Harga_Alat = Harga_Alat
    FROM dbo.Alat
    WHERE ID_Alat = @ID_Alat
      AND Status = 1
      AND Is_Deleted = 0;

    IF @Harga_Alat IS NULL
        THROW 54001, 'Alat tidak aktif atau tidak ditemukan.', 1;

    INSERT INTO dbo.Detail_Beli_Alat
    (ID_Alat, ID_Beli, Jumlah, SubTotal)
    VALUES
    (@ID_Alat, @ID_Beli, @Jumlah, @Harga_Alat * @Jumlah);
END;
GO

CREATE OR ALTER PROCEDURE dbo.sp_UpdateDetailBeliAlat
    @ID_Beli INT,
    @ID_Alat INT,
    @Jumlah INT
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE @Harga_Alat DECIMAL(18,2);

    SELECT @Harga_Alat = Harga_Alat
    FROM dbo.Alat
    WHERE ID_Alat = @ID_Alat
      AND Status = 1
      AND Is_Deleted = 0;

    IF @Harga_Alat IS NULL
        THROW 54002, 'Alat tidak aktif atau tidak ditemukan.', 1;

    UPDATE dbo.Detail_Beli_Alat
    SET Jumlah = @Jumlah,
        SubTotal = @Harga_Alat * @Jumlah
    WHERE ID_Beli = @ID_Beli
      AND ID_Alat = @ID_Alat;
END;
GO


/* ============================================================
   4. USER DEFINED FUNCTION UNTUK LAPORAN DAN DASHBOARD
   Catatan:
   - Laporan/dashboard jangan SELECT langsung dari tabel di aplikasi.
   - Aplikasi cukup SELECT dari function.
   Contoh:
   SELECT * FROM dbo.fn_LaporanSewaLapangan('2024-06-01', '2024-06-30');
   ============================================================ */

CREATE OR ALTER FUNCTION dbo.fn_LaporanSewaLapangan
(
    @Tanggal_Mulai DATE,
    @Tanggal_Selesai DATE
)
RETURNS TABLE
AS
RETURN
(
    SELECT
        b.ID_Booking,
        b.Tanggal_Booking,
        c.Nama_Customer,
        k.Nama_Karyawan,
        l.Nama_Lapangan,
        j.Tanggal AS Tanggal_Main,
        j.Jam_Mulai,
        j.Jam_Selesai,
        p.Nama_Promo,
        b.Metode_Pembayaran,
        b.Total_Bayar,
        CASE b.Status
            WHEN 0 THEN 'Menunggu Konfirmasi'
            WHEN 1 THEN 'Berhasil'
            WHEN 2 THEN 'Selesai'
            WHEN 3 THEN 'Dibatalkan'
        END AS Status_Booking
    FROM dbo.Booking b
    INNER JOIN dbo.Customer c ON b.ID_Customer = c.ID_Customer
    INNER JOIN dbo.Karyawan k ON b.ID_Karyawan = k.ID_Karyawan
    INNER JOIN dbo.Jadwal j ON b.ID_Jadwal = j.ID_Jadwal
    INNER JOIN dbo.Lapangan l ON j.ID_Lapangan = l.ID_Lapangan
    LEFT JOIN dbo.Promo p ON b.ID_Promo = p.ID_Promo
    WHERE b.Tanggal_Booking BETWEEN @Tanggal_Mulai AND @Tanggal_Selesai
);
GO

CREATE OR ALTER FUNCTION dbo.fn_LaporanLangganan
(
    @Tanggal_Mulai DATE,
    @Tanggal_Selesai DATE
)
RETURNS TABLE
AS
RETURN
(
    SELECT
        lg.ID_Langganan,
        c.Nama_Customer,
        k.Nama_Karyawan,
        tm.Nama_Tipe,
        lg.Tanggal_Mulai,
        lg.Tanggal_Selesai,
        lg.Metode_Pembayaran,
        lg.Total_Bayar,
        CASE lg.Status
            WHEN 0 THEN 'Menunggu Konfirmasi'
            WHEN 1 THEN 'Aktif'
            WHEN 2 THEN 'Berakhir'
            WHEN 3 THEN 'Ditolak'
        END AS Status_Langganan
    FROM dbo.Langganan lg
    INNER JOIN dbo.Customer c ON lg.ID_Customer = c.ID_Customer
    INNER JOIN dbo.Karyawan k ON lg.ID_Karyawan = k.ID_Karyawan
    INNER JOIN dbo.Tipe_Member tm ON lg.ID_Tipe = tm.ID_Tipe
    WHERE lg.Tanggal_Mulai BETWEEN @Tanggal_Mulai AND @Tanggal_Selesai
);
GO

CREATE OR ALTER FUNCTION dbo.fn_LaporanPembelianAlat
(
    @Tanggal_Mulai DATE,
    @Tanggal_Selesai DATE
)
RETURNS TABLE
AS
RETURN
(
    SELECT
        ba.ID_Beli,
        ba.Tanggal_Beli,
        c.Nama_Customer,
        k.Nama_Karyawan,
        a.Nama_Alat,
        dba.Jumlah,
        dba.SubTotal,
        ba.Metode_Pembayaran,
        ba.Total_Bayar,
        CASE ba.Status
            WHEN 0 THEN 'Menunggu Konfirmasi'
            WHEN 1 THEN 'Berhasil'
        END AS Status_Pembelian
    FROM dbo.Beli_Alat ba
    INNER JOIN dbo.Customer c ON ba.ID_Customer = c.ID_Customer
    INNER JOIN dbo.Karyawan k ON ba.ID_Karyawan = k.ID_Karyawan
    INNER JOIN dbo.Detail_Beli_Alat dba ON ba.ID_Beli = dba.ID_Beli
    INNER JOIN dbo.Alat a ON dba.ID_Alat = a.ID_Alat
    WHERE ba.Tanggal_Beli BETWEEN @Tanggal_Mulai AND @Tanggal_Selesai
);
GO

CREATE OR ALTER FUNCTION dbo.fn_LaporanOmset
(
    @Tanggal_Mulai DATE,
    @Tanggal_Selesai DATE
)
RETURNS TABLE
AS
RETURN
(
    SELECT
        'Sewa Lapangan' AS Sumber_Omset,
        SUM(CASE WHEN b.Status IN (1,2) THEN b.Total_Bayar ELSE 0 END) AS Total_Masuk,
        SUM(CASE WHEN b.Status = 3 THEN ISNULL(pb.Nominal_Refund, 0) ELSE 0 END) AS Total_Refund,
        SUM(CASE WHEN b.Status IN (1,2) THEN b.Total_Bayar ELSE 0 END)
        -
        SUM(CASE WHEN b.Status = 3 THEN ISNULL(pb.Nominal_Refund, 0) ELSE 0 END) AS Omset_Bersih
    FROM dbo.Booking b
    LEFT JOIN dbo.Pembatalan_Booking pb ON b.ID_Booking = pb.ID_Booking
    WHERE b.Tanggal_Booking BETWEEN @Tanggal_Mulai AND @Tanggal_Selesai

    UNION ALL

    SELECT
        'Langganan Member' AS Sumber_Omset,
        SUM(CASE WHEN Status = 1 THEN Total_Bayar ELSE 0 END) AS Total_Masuk,
        0 AS Total_Refund,
        SUM(CASE WHEN Status = 1 THEN Total_Bayar ELSE 0 END) AS Omset_Bersih
    FROM dbo.Langganan
    WHERE Tanggal_Mulai BETWEEN @Tanggal_Mulai AND @Tanggal_Selesai

    UNION ALL

    SELECT
        'Pembelian Alat' AS Sumber_Omset,
        SUM(CASE WHEN Status = 1 THEN Total_Bayar ELSE 0 END) AS Total_Masuk,
        0 AS Total_Refund,
        SUM(CASE WHEN Status = 1 THEN Total_Bayar ELSE 0 END) AS Omset_Bersih
    FROM dbo.Beli_Alat
    WHERE Tanggal_Beli BETWEEN @Tanggal_Mulai AND @Tanggal_Selesai
);
GO

CREATE OR ALTER FUNCTION dbo.fn_DashboardRingkasan
(
    @Tanggal_Mulai DATE,
    @Tanggal_Selesai DATE
)
RETURNS TABLE
AS
RETURN
(
    SELECT
        (SELECT COUNT(*) FROM dbo.Customer WHERE Status = 1 AND Is_Deleted = 0) AS Total_Customer_Aktif,
        (SELECT COUNT(*) FROM dbo.Lapangan WHERE Status = 1 AND Is_Deleted = 0) AS Total_Lapangan_Aktif,
        (SELECT COUNT(*) FROM dbo.Booking WHERE Tanggal_Booking BETWEEN @Tanggal_Mulai AND @Tanggal_Selesai) AS Total_Booking,
        (SELECT COUNT(*) FROM dbo.Langganan WHERE Tanggal_Mulai BETWEEN @Tanggal_Mulai AND @Tanggal_Selesai) AS Total_Langganan,
        (SELECT COUNT(*) FROM dbo.Beli_Alat WHERE Tanggal_Beli BETWEEN @Tanggal_Mulai AND @Tanggal_Selesai) AS Total_Pembelian_Alat,
        (
            ISNULL((SELECT SUM(Total_Bayar) FROM dbo.Booking WHERE Status IN (1,2) AND Tanggal_Booking BETWEEN @Tanggal_Mulai AND @Tanggal_Selesai), 0)
            +
            ISNULL((SELECT SUM(Total_Bayar) FROM dbo.Langganan WHERE Status = 1 AND Tanggal_Mulai BETWEEN @Tanggal_Mulai AND @Tanggal_Selesai), 0)
            +
            ISNULL((SELECT SUM(Total_Bayar) FROM dbo.Beli_Alat WHERE Status = 1 AND Tanggal_Beli BETWEEN @Tanggal_Mulai AND @Tanggal_Selesai), 0)
            -
            ISNULL((SELECT SUM(Nominal_Refund) FROM dbo.Pembatalan_Booking WHERE Status = 1 AND Tanggal_Batal BETWEEN @Tanggal_Mulai AND @Tanggal_Selesai), 0)
        ) AS Omset_Bersih
);
GO


/* ============================================================
   5. TRIGGER TRANSAKSI
   Catatan:
   - Trigger berjalan otomatis setelah INSERT/UPDATE/DELETE.
   - Dipakai untuk proses bisnis yang cocok:
     1) Booking mengunci / membuka jadwal.
     2) Pembatalan yang disetujui mengubah status booking menjadi dibatalkan.
     3) Detail pembelian alat otomatis mengurangi/mengembalikan stok.
     4) Semua transaksi dicatat ke Log_History.
   ============================================================ */

CREATE OR ALTER TRIGGER dbo.trg_Booking_AfterInsertUpdate
ON dbo.Booking
AFTER INSERT, UPDATE
AS
BEGIN
    SET NOCOUNT ON;

    -- Jika booking aktif dibuat/diubah, jadwal otomatis menjadi tidak tersedia.
    UPDATE j
    SET j.Status = 0,
        j.Modified_By = COALESCE(i.Modified_By, i.Created_By, 'TRIGGER'),
        j.Modified_Date = GETDATE()
    FROM dbo.Jadwal j
    INNER JOIN inserted i ON j.ID_Jadwal = i.ID_Jadwal
    WHERE i.Status IN (0,1,2);

    -- Jika booking dibatalkan, jadwal otomatis tersedia lagi.
    UPDATE j
    SET j.Status = 1,
        j.Modified_By = COALESCE(i.Modified_By, i.Created_By, 'TRIGGER'),
        j.Modified_Date = GETDATE()
    FROM dbo.Jadwal j
    INNER JOIN inserted i ON j.ID_Jadwal = i.ID_Jadwal
    WHERE i.Status = 3
      AND NOT EXISTS (
          SELECT 1
          FROM dbo.Booking b
          WHERE b.ID_Jadwal = j.ID_Jadwal
            AND b.Status <> 3
      );

    -- Log history untuk INSERT dan UPDATE Booking.
    INSERT INTO dbo.Log_History
    (Nama_Tabel, ID_Data, Aksi, Data_Lama, Data_Baru, Created_By, Created_Date)
    SELECT
        'Booking',
        CAST(i.ID_Booking AS VARCHAR(50)),
        CASE WHEN d.ID_Booking IS NULL THEN 'INSERT' ELSE 'UPDATE' END,
        CASE WHEN d.ID_Booking IS NULL THEN NULL ELSE
            CONCAT('Status Lama=', d.Status, ', Total Lama=', d.Total_Bayar, ', Jadwal Lama=', d.ID_Jadwal)
        END,
        CONCAT('Status Baru=', i.Status, ', Total Baru=', i.Total_Bayar, ', Jadwal Baru=', i.ID_Jadwal),
        COALESCE(i.Modified_By, i.Created_By, 'TRIGGER'),
        GETDATE()
    FROM inserted i
    LEFT JOIN deleted d ON i.ID_Booking = d.ID_Booking;
END;
GO

CREATE OR ALTER TRIGGER dbo.trg_PembatalanBooking_AfterInsertUpdate
ON dbo.Pembatalan_Booking
AFTER INSERT, UPDATE
AS
BEGIN
    SET NOCOUNT ON;

    -- Jika pembatalan disetujui/status 1, status Booking otomatis menjadi dibatalkan.
    UPDATE b
    SET b.Status = 3,
        b.Modified_By = COALESCE(i.Modified_By, i.Created_By, 'TRIGGER'),
        b.Modified_Date = GETDATE()
    FROM dbo.Booking b
    INNER JOIN inserted i ON b.ID_Booking = i.ID_Booking
    WHERE i.Status = 1;

    -- Log history untuk INSERT dan UPDATE Pembatalan_Booking.
    INSERT INTO dbo.Log_History
    (Nama_Tabel, ID_Data, Aksi, Data_Lama, Data_Baru, Created_By, Created_Date)
    SELECT
        'Pembatalan_Booking',
        CAST(i.ID_Pembatalan AS VARCHAR(50)),
        CASE WHEN d.ID_Pembatalan IS NULL THEN 'INSERT' ELSE 'UPDATE' END,
        CASE WHEN d.ID_Pembatalan IS NULL THEN NULL ELSE
            CONCAT('Status Lama=', d.Status, ', Refund Lama=', d.Nominal_Refund)
        END,
        CONCAT('Status Baru=', i.Status, ', Refund Baru=', i.Nominal_Refund),
        COALESCE(i.Modified_By, i.Created_By, 'TRIGGER'),
        GETDATE()
    FROM inserted i
    LEFT JOIN deleted d ON i.ID_Pembatalan = d.ID_Pembatalan;
END;
GO

CREATE OR ALTER TRIGGER dbo.trg_Langganan_AfterInsertUpdate
ON dbo.Langganan
AFTER INSERT, UPDATE
AS
BEGIN
    SET NOCOUNT ON;

    -- Log history untuk transaksi Langganan.
    INSERT INTO dbo.Log_History
    (Nama_Tabel, ID_Data, Aksi, Data_Lama, Data_Baru, Created_By, Created_Date)
    SELECT
        'Langganan',
        CAST(i.ID_Langganan AS VARCHAR(50)),
        CASE WHEN d.ID_Langganan IS NULL THEN 'INSERT' ELSE 'UPDATE' END,
        CASE WHEN d.ID_Langganan IS NULL THEN NULL ELSE
            CONCAT('Status Lama=', d.Status, ', Total Lama=', d.Total_Bayar)
        END,
        CONCAT('Status Baru=', i.Status, ', Total Baru=', i.Total_Bayar),
        COALESCE(i.Modified_By, i.Created_By, 'TRIGGER'),
        GETDATE()
    FROM inserted i
    LEFT JOIN deleted d ON i.ID_Langganan = d.ID_Langganan;
END;
GO

CREATE OR ALTER TRIGGER dbo.trg_BeliAlat_AfterInsertUpdate
ON dbo.Beli_Alat
AFTER INSERT, UPDATE
AS
BEGIN
    SET NOCOUNT ON;

    -- Log history untuk transaksi Beli_Alat.
    INSERT INTO dbo.Log_History
    (Nama_Tabel, ID_Data, Aksi, Data_Lama, Data_Baru, Created_By, Created_Date)
    SELECT
        'Beli_Alat',
        CAST(i.ID_Beli AS VARCHAR(50)),
        CASE WHEN d.ID_Beli IS NULL THEN 'INSERT' ELSE 'UPDATE' END,
        CASE WHEN d.ID_Beli IS NULL THEN NULL ELSE
            CONCAT('Status Lama=', d.Status, ', Total Lama=', d.Total_Bayar)
        END,
        CONCAT('Status Baru=', i.Status, ', Total Baru=', i.Total_Bayar),
        COALESCE(i.Modified_By, i.Created_By, 'TRIGGER'),
        GETDATE()
    FROM inserted i
    LEFT JOIN deleted d ON i.ID_Beli = d.ID_Beli;
END;
GO

CREATE OR ALTER TRIGGER dbo.trg_DetailBeliAlat_AfterInsertUpdateDelete
ON dbo.Detail_Beli_Alat
AFTER INSERT, UPDATE, DELETE
AS
BEGIN
    SET NOCOUNT ON;

    /*
      Selisih stok:
      - INSERT detail: stok dikurangi jumlah baru.
      - UPDATE detail: stok menyesuaikan selisih jumlah.
      - DELETE detail: stok dikembalikan.
    */
    ;WITH Perubahan AS (
        SELECT
            COALESCE(i.ID_Alat, d.ID_Alat) AS ID_Alat,
            SUM(ISNULL(i.Jumlah, 0) - ISNULL(d.Jumlah, 0)) AS Selisih_Jumlah
        FROM inserted i
        FULL OUTER JOIN deleted d
            ON i.ID_Alat = d.ID_Alat
           AND i.ID_Beli = d.ID_Beli
        GROUP BY COALESCE(i.ID_Alat, d.ID_Alat)
    )
    UPDATE a
    SET a.Stok = a.Stok - p.Selisih_Jumlah,
        a.Modified_By = 'TRIGGER_DETAIL_BELI',
        a.Modified_Date = GETDATE()
    FROM dbo.Alat a
    INNER JOIN Perubahan p ON a.ID_Alat = p.ID_Alat;

    -- Total_Bayar pada Beli_Alat dihitung ulang otomatis dari detail.
    ;WITH BeliTerdampak AS (
        SELECT ID_Beli FROM inserted
        UNION
        SELECT ID_Beli FROM deleted
    )
    UPDATE ba
    SET ba.Total_Bayar = ISNULL(x.Total_Detail, 0),
        ba.Modified_By = 'TRIGGER_DETAIL_BELI',
        ba.Modified_Date = GETDATE()
    FROM dbo.Beli_Alat ba
    INNER JOIN BeliTerdampak bt ON ba.ID_Beli = bt.ID_Beli
    OUTER APPLY (
        SELECT SUM(SubTotal) AS Total_Detail
        FROM dbo.Detail_Beli_Alat dba
        WHERE dba.ID_Beli = ba.ID_Beli
    ) x;

    -- Log history untuk Detail_Beli_Alat.
    INSERT INTO dbo.Log_History
    (Nama_Tabel, ID_Data, Aksi, Data_Lama, Data_Baru, Created_By, Created_Date)
    SELECT
        'Detail_Beli_Alat',
        CONCAT(COALESCE(i.ID_Beli, d.ID_Beli), '-', COALESCE(i.ID_Alat, d.ID_Alat)),
        CASE
            WHEN d.ID_Beli IS NULL THEN 'INSERT'
            WHEN i.ID_Beli IS NULL THEN 'DELETE'
            ELSE 'UPDATE'
        END,
        CASE WHEN d.ID_Beli IS NULL THEN NULL ELSE
            CONCAT('Jumlah Lama=', d.Jumlah, ', Subtotal Lama=', d.SubTotal)
        END,
        CASE WHEN i.ID_Beli IS NULL THEN NULL ELSE
            CONCAT('Jumlah Baru=', i.Jumlah, ', Subtotal Baru=', i.SubTotal)
        END,
        'TRIGGER_DETAIL_BELI',
        GETDATE()
    FROM inserted i
    FULL OUTER JOIN deleted d
        ON i.ID_Alat = d.ID_Alat
       AND i.ID_Beli = d.ID_Beli;
END;
GO


/* ============================================================
   6. CONTOH PEMANGGILAN
   Bagian ini contoh saja. Jalankan kalau ingin testing.
   ============================================================ */

-- Contoh tambah customer lewat SP:
-- EXEC dbo.sp_InsertCustomer
--     @Nama_Customer = 'Tes Customer',
--     @Tanggal_Lahir = '2001-01-01',
--     @Tempat_Lahir = 'Jakarta',
--     @Jenis_Kelamin = 1,
--     @Alamat = 'Jl. Contoh',
--     @No_Telepon = '081299999999',
--     @Email = 'tescustomer@mail.com',
--     @Username = 'tes_customer',
--     @Kata_Sandi = 'cust@1234',
--     @Status = 1,
--     @Created_By = 'ADMIN';

-- Contoh ambil data laporan dari UDF:
-- SELECT * FROM dbo.fn_LaporanSewaLapangan('2024-06-01', '2024-06-30');
-- SELECT * FROM dbo.fn_LaporanLangganan('2024-06-01', '2024-06-30');
-- SELECT * FROM dbo.fn_LaporanPembelianAlat('2024-06-01', '2024-06-30');
-- SELECT * FROM dbo.fn_LaporanOmset('2024-06-01', '2024-06-30');
-- SELECT * FROM dbo.fn_DashboardRingkasan('2024-06-01', '2024-06-30');

-- Contoh cek log history:
-- SELECT * FROM dbo.Log_History ORDER BY ID_Log DESC;
GO
