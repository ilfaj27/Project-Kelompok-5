-- Status Customer/Karyawan/Lapangan/Alat/Promo:
-- 0 = Nonaktif
-- 1 = Aktif

-- Status Jadwal:
-- 0 = Tidak Tersedia
-- 1 = Tersedia

-- Status Langganan:
-- 0 = Menunggu Konfirmasi
-- 1 = Aktif
-- 2 = Berakhir
-- 3 = Ditolak

CREATE DATABASE Hoopball;
GO

USE Hoopball;
GO

-- ============================================================
-- 1. TABEL MASTER: Karyawan
-- ============================================================
CREATE TABLE Karyawan (
    ID_Karyawan     INT IDENTITY(1,1) PRIMARY KEY,
    NIK             VARCHAR(16)     NOT NULL,   
    Nama_Karyawan   VARCHAR(20)     NOT NULL,
    Tanggal_Lahir   DATE            NOT NULL,
    Tempat_Lahir    VARCHAR(50)     NOT NULL,
    Alamat          VARCHAR(100)    NOT NULL,
    Jenis_Kelamin   INT             NOT NULL CHECK (Jenis_Kelamin IN (0,1)),
    Jabatan         INT             NOT NULL CHECK (Jabatan IN (1,2)),
    No_Telepon      VARCHAR(15)     NOT NULL,
    Email           VARCHAR(50)     NOT NULL,
    Username        VARCHAR(20)     NOT NULL,
    Kata_Sandi      VARCHAR(20)     NOT NULL,
    Status          INT             NOT NULL CHECK (Status IN (0,1)),
    Photo_Profile   VARCHAR(255)    NULL,
    Is_Deleted      BIT             NOT NULL DEFAULT 0,
    Created_By      VARCHAR(50)     NOT NULL,
    Created_Date    DATETIME        NOT NULL,
    Modified_By     VARCHAR(50)     NULL,
    Modified_Date   DATETIME        NULL,
    Deleted_By      VARCHAR(50)     NULL,
    Deleted_Date    DATETIME        NULL
);

ALTER TABLE Karyawan
ADD CONSTRAINT UQ_Karyawan_NIK UNIQUE (NIK);

ALTER TABLE Karyawan
ADD CONSTRAINT UQ_Karyawan_Email UNIQUE (Email);

ALTER TABLE Karyawan
ADD CONSTRAINT UQ_Karyawan_Username UNIQUE (Username);

ALTER TABLE Karyawan
ADD CONSTRAINT UQ_Karyawan_NoTelepon UNIQUE (No_Telepon);

-- Jabatan: 1 = Karyawan, 2 = Manajer
INSERT INTO Karyawan
(NIK, Nama_Karyawan, Tanggal_Lahir, Tempat_Lahir, Alamat, Jenis_Kelamin, Jabatan, No_Telepon, Email, Username, Kata_Sandi, Status, Is_Deleted, Created_By, Created_Date) VALUES
('3173011203950001', 'Rizky Pratama', '1995-03-12', 'Jakarta',    'Jl. Mawar No.1 Jakarta',      1, 2, '081211110001', 'rizky@hoopball.com', 'rizky_p', 'Pass@1234', 1, 0, 'SYSTEM', '2024-01-01 08:00:00'),
('3273012207970002', 'Sari Dewi',     '1997-07-22', 'Bandung',    'Jl. Melati No.5 Bandung',     0, 1, '081211110002', 'sari@hoopball.com',  'sari_d',  'Pass@1234', 1, 0, 'SYSTEM', '2024-01-01 08:00:00'),
('3578010511960003', 'Andi Setiawan', '1996-11-05', 'Surabaya',   'Jl. Kenanga No.10 Surabaya',  1, 1, '081211110003', 'andi@hoopball.com',  'andi_s',  'Pass@1234', 1, 0, 'SYSTEM', '2024-01-01 08:00:00'),
('3471011805940004', 'Budi Santoso',  '1994-05-18', 'Yogyakarta', 'Jl. Dahlia No.3 Yogyakarta',  1, 1, '081211110004', 'budi@hoopball.com',  'budi_s',  'Pass@1234', 1, 0, 'SYSTEM', '2024-01-01 08:00:00'),
('3374013009980005', 'Nina Rahayu',   '1998-09-30', 'Semarang',   'Jl. Anggrek No.7 Semarang',   0, 1, '081211110005', 'nina@hoopball.com',  'nina_r',  'Pass@1234', 1, 0, 'SYSTEM', '2024-01-01 08:00:00');
-- ============================================================
-- 2. TABEL MASTER: Customer
-- ============================================================
CREATE TABLE Customer (
    ID_Customer     INT IDENTITY(1,1) PRIMARY KEY,
    Nama_Customer   VARCHAR(20)     NOT NULL,
    Tanggal_Lahir   DATE            NOT NULL,
    Tempat_Lahir    VARCHAR(50)     NOT NULL,
    Jenis_Kelamin   INT             NOT NULL CHECK (Jenis_Kelamin IN (0,1)),
    Alamat          VARCHAR(100)    NOT NULL,
    No_Telepon      VARCHAR(15)     NOT NULL,
    Email           VARCHAR(50)     NOT NULL,
    Username        VARCHAR(20)     NOT NULL,
    Kata_Sandi      VARCHAR(20)     NOT NULL,
    Status          INT             NOT NULL CHECK (Status IN (0,1)),
    Photo_Profile   VARCHAR(255)    NULL,
    Is_Deleted      BIT             NOT NULL DEFAULT 0,
    Created_By      VARCHAR(50)     NOT NULL,
    Created_Date    DATETIME        NOT NULL,
    Modified_By     VARCHAR(50)     NULL,
    Modified_Date   DATETIME        NULL,
    Deleted_By      VARCHAR(50)     NULL,
    Deleted_Date    DATETIME        NULL
);

ALTER TABLE Customer
ADD CONSTRAINT UQ_Customer_Email UNIQUE (Email);

ALTER TABLE Customer
ADD CONSTRAINT UQ_Customer_Username UNIQUE (Username);

ALTER TABLE Customer
ADD CONSTRAINT UQ_Customer_NoTelepon UNIQUE (No_Telepon);

INSERT INTO Customer
(Nama_Customer, Tanggal_Lahir, Tempat_Lahir, Jenis_Kelamin, Alamat, No_Telepon, Email, Username, Kata_Sandi, Status, Is_Deleted, Created_By, Created_Date) VALUES
('Dimas Arya',      '2000-04-10', 'Jakarta',   1, 'Jl. Cempaka No.2 Jakarta',    '08121234001', 'dimas@mail.com',  'dimas_a',  'cust@1234', 1, 0, 'SYSTEM', '2024-01-05 09:00:00'),
('Laila Putri',     '2001-08-15', 'Bandung',   0, 'Jl. Flamboyan No.4 Bandung',  '08121234002', 'laila@mail.com',  'laila_p',  'cust@1234', 1, 0, 'SYSTEM', '2024-01-06 09:00:00'),
('Fajar Nugroho',   '1999-12-20', 'Surabaya',  1, 'Jl. Bougenville No.6 Sby',    '08121234003', 'fajar@mail.com',  'fajar_n',  'cust@1234', 1, 0, 'SYSTEM', '2024-01-07 09:00:00'),
('Mega Lestari',    '2002-02-28', 'Semarang',  0, 'Jl. Teratai No.8 Semarang',   '08121234004', 'mega@mail.com',   'mega_l',   'cust@1234', 1, 0, 'SYSTEM', '2024-01-08 09:00:00'),
('Hendra Wijaya',   '1998-06-14', 'Medan',     1, 'Jl. Wijaya No.10 Medan',      '08121234005', 'hendra@mail.com', 'hendra_w', 'cust@1234', 1, 0, 'SYSTEM', '2024-01-09 09:00:00'),
('Rini Kusuma',     '2003-03-03', 'Malang',    0, 'Jl. Semeru No.12 Malang',     '08121234006', 'rini@mail.com',   'rini_k',   'cust@1234', 1, 0, 'SYSTEM', '2024-01-10 09:00:00'),
('Yusuf Hakim',     '1997-10-11', 'Makassar',  1, 'Jl. Pantai No.14 Makassar',   '08121234007', 'yusuf@mail.com',  'yusuf_h',  'cust@1234', 1, 0, 'SYSTEM', '2024-01-11 09:00:00'),
('Putri Anggraini', '2001-01-25', 'Palembang', 0, 'Jl. Kamboja No.16 Palembang', '08121234008', 'putri@mail.com',  'putri_a',  'cust@1234', 1, 0, 'SYSTEM', '2024-01-12 09:00:00');


-- ============================================================
-- 3. TABEL MASTER: Lapangan
-- ============================================================
CREATE TABLE Lapangan (
    ID_Lapangan     INT IDENTITY(1,1) PRIMARY KEY,
    Nama_Lapangan   VARCHAR(25)     NOT NULL,
    Harga_Sewa      DECIMAL(18,2)   NOT NULL,
    Photo_Lapangan  VARCHAR(255)    NULL,
    Status          INT             NOT NULL CHECK (Status IN (0,1)),
    Is_Deleted      BIT             NOT NULL DEFAULT 0,
    Created_By      VARCHAR(50)     NOT NULL,
    Created_Date    DATETIME        NOT NULL,
    Modified_By     VARCHAR(50)     NULL,
    Modified_Date   DATETIME        NULL,
    Deleted_By      VARCHAR(50)     NULL,
    Deleted_Date    DATETIME        NULL
);

ALTER TABLE Lapangan
ADD CONSTRAINT CK_Lapangan_Harga CHECK (Harga_Sewa >= 0);

INSERT INTO Lapangan
(Nama_Lapangan, Harga_Sewa, Status, Is_Deleted, Created_By, Created_Date) VALUES
('Lapangan A',   80000.00,  1, 0, '2', '2024-01-02 08:00:00'),
('Lapangan B',   100000.00, 1, 0, '2', '2024-01-02 08:00:00'),
('Lapangan C',   120000.00, 1, 0, '2', '2024-01-02 08:00:00'),
('Lapangan VIP', 150000.00, 1, 0, '2', '2024-01-02 08:00:00');


-- ============================================================
-- 4. TABEL MASTER: Tipe_Member
-- ============================================================
CREATE TABLE Tipe_Member (
    ID_Tipe         INT IDENTITY(1,1) PRIMARY KEY,
    Nama_Tipe       VARCHAR(10)     NOT NULL,
    Harga_Member    DECIMAL(18,2)   NOT NULL,
    Potongan_Harga  DECIMAL(18,2)   NOT NULL,
    Status          INT             NOT NULL CHECK (Status IN (0,1)),
    Is_Deleted      BIT             NOT NULL DEFAULT 0,
    Created_By      VARCHAR(50)     NOT NULL,
    Created_Date    DATETIME        NOT NULL,
    Modified_By     VARCHAR(50)     NULL,
    Modified_Date   DATETIME        NULL,
    Deleted_By      VARCHAR(50)     NULL,
    Deleted_Date    DATETIME        NULL
);

INSERT INTO Tipe_Member
(Nama_Tipe, Harga_Member, Potongan_Harga, Status, Is_Deleted, Created_By, Created_Date) VALUES
('Silver',   100000.00, 10000.00, 1, 0, '2', '2024-01-02 08:00:00'),
('Gold',     200000.00, 20000.00, 1, 0, '2', '2024-01-02 08:00:00'),
('Platinum', 350000.00, 35000.00, 1, 0, '2', '2024-01-02 08:00:00');


-- ============================================================
-- 5. TABEL MASTER: Promo
-- ============================================================
CREATE TABLE Promo (
    ID_Promo        INT IDENTITY(1,1) PRIMARY KEY,
    Nama_Promo      VARCHAR(50)     NOT NULL,
    Diskon          DECIMAL(18,2)   NOT NULL,
    Tanggal_Mulai   DATE            NOT NULL,
    Tanggal_Selesai DATE            NOT NULL,
    Status          INT             NOT NULL CHECK (Status IN (0,1)),
    Is_Deleted      BIT             NOT NULL DEFAULT 0,
    Created_By      VARCHAR(50)     NOT NULL,
    Created_Date    DATETIME        NOT NULL,
    Modified_By     VARCHAR(50)     NULL,
    Modified_Date   DATETIME        NULL,
    Deleted_By      VARCHAR(50)     NULL,
    Deleted_Date    DATETIME        NULL
);

ALTER TABLE Promo
ADD CONSTRAINT CK_Promo_Tanggal
CHECK (Tanggal_Mulai <= Tanggal_Selesai);

INSERT INTO Promo
(Nama_Promo, Diskon, Tanggal_Mulai, Tanggal_Selesai, Status, Is_Deleted, Created_By, Created_Date)
VALUES
('Promo Hari Raya',  15000.00, '2024-03-20', '2024-04-05', 0, 0, '2', '2024-03-15 08:00:00'),
('Promo Weekend',    10000.00, '2024-04-01', '2024-06-30', 1, 0, '2', '2024-04-01 08:00:00'),
('Promo New Member', 20000.00, '2024-05-01', '2024-05-31', 0, 0, '2', '2024-05-01 08:00:00'),
('Promo Pelajar',    12000.00, '2024-06-01', '2024-12-31', 1, 0, '2', '2024-06-01 08:00:00');


-- ============================================================
-- 6. TABEL MASTER: Fasilitas_Lapangan
-- ============================================================
CREATE TABLE Fasilitas_Lapangan (
    ID_Fasilitas    INT IDENTITY(1,1) PRIMARY KEY,
    ID_Lapangan     INT             NOT NULL,
    Nama_Fasilitas  VARCHAR(25)     NOT NULL,
    Detail_Fasilitas VARCHAR(50)    NOT NULL,
    Status          INT             NOT NULL CHECK (Status IN (0,1)),
    Is_Deleted      BIT             NOT NULL DEFAULT 0,
    Created_By      VARCHAR(50)     NOT NULL,
    Created_Date    DATETIME        NOT NULL,
    Modified_By     VARCHAR(50)     NULL,
    Modified_Date   DATETIME        NULL,
    Deleted_By      VARCHAR(50)     NULL,
    Deleted_Date    DATETIME        NULL,
    FOREIGN KEY (ID_Lapangan) REFERENCES Lapangan(ID_Lapangan)
);

INSERT INTO Fasilitas_Lapangan
(ID_Lapangan, Nama_Fasilitas, Detail_Fasilitas, Status, Is_Deleted, Created_By, Created_Date)
VALUES
(1, 'Bola Basket',  'Bola basket standar SNI',     1, 0, '2', '2024-01-03 08:00:00'),
(1, 'Pencahayaan',  'Lampu LED 1000 watt',         1, 0, '2', '2024-01-03 08:00:00'),
(1, 'Jenis Lantai', 'Lantai vinyl anti-slip',      1, 0, '2', '2024-01-03 08:00:00'),
(2, 'Bola Basket',  'Bola basket premium',         1, 0, '3', '2024-01-03 08:00:00'),
(2, 'Papan Skor',   'Papan skor digital',          1, 0, '3', '2024-01-03 08:00:00'),
(2, 'Jenis Ring',   'Ring basket adjustable',      1, 0, '3', '2024-01-03 08:00:00'),
(3, 'Bola Basket',  'Bola basket standar SNI',     1, 0, '4', '2024-01-03 08:00:00'),
(3, 'Pencahayaan',  'Lampu sorot 1500 watt',       1, 0, '4', '2024-01-03 08:00:00'),
(4, 'Bola Basket',  'Bola basket profesional NBA', 1, 0, '4', '2024-01-03 08:00:00'),
(4, 'Papan Skor',   'Papan skor digital wireless', 1, 0, '4', '2024-01-03 08:00:00'),
(4, 'Pencahayaan',  'Lampu LED premium 2000 watt', 1, 0, '4', '2024-01-03 08:00:00'),
(4, 'AC',           'AC central ruangan tertutup', 1, 0, '4', '2024-01-03 08:00:00');

-- ============================================================
-- 7. TABEL MASTER: Jadwal
-- ============================================================
CREATE TABLE Jadwal (
    ID_Jadwal       INT IDENTITY(1,1) PRIMARY KEY,
    ID_Lapangan     INT             NOT NULL,
    Tanggal         DATE            NOT NULL,
    Jam_Mulai       TIME            NOT NULL,
    Jam_Selesai     TIME            NOT NULL,
    Status          INT             NOT NULL CHECK (Status IN (0,1)),
    Is_Deleted      BIT             NOT NULL DEFAULT 0,
    Created_By      VARCHAR(50)     NOT NULL,
    Created_Date    DATETIME        NOT NULL,
    Modified_By     VARCHAR(50)     NULL,
    Modified_Date   DATETIME        NULL,
    Deleted_By      VARCHAR(50)     NULL,
    Deleted_Date    DATETIME        NULL,
    FOREIGN KEY (ID_Lapangan) REFERENCES Lapangan(ID_Lapangan)
);

ALTER TABLE Jadwal
ADD CONSTRAINT UQ_Jadwal_Lapangan_Waktu 
UNIQUE (ID_Lapangan, Tanggal, Jam_Mulai, Jam_Selesai);

ALTER TABLE Jadwal
ADD CONSTRAINT CK_Jadwal_Jam 
CHECK (Jam_Mulai < Jam_Selesai);

INSERT INTO Jadwal
(ID_Lapangan, Tanggal, Jam_Mulai, Jam_Selesai, Status, Is_Deleted, Created_By, Created_Date) VALUES
(1, '2024-06-01', '08:00:00', '10:00:00', 0, 0, '2', '2024-01-03 08:00:00'),
(1, '2024-06-02', '10:00:00', '12:00:00', 0, 0, '2', '2024-01-03 08:00:00'),
(2, '2024-06-03', '13:00:00', '15:00:00', 0, 0, '3', '2024-01-03 08:00:00'),
(2, '2024-06-05', '08:00:00', '10:00:00', 0, 0, '3', '2024-01-03 08:00:00'),
(3, '2024-06-07', '15:00:00', '17:00:00', 0, 0, '4', '2024-01-03 08:00:00'),
(3, '2024-06-08', '10:00:00', '12:00:00', 0, 0, '4', '2024-01-03 08:00:00'),
(4, '2024-06-10', '19:00:00', '21:00:00', 0, 0, '4', '2024-01-03 08:00:00'),
(4, '2024-06-12', '08:00:00', '10:00:00', 0, 0, '4', '2024-01-03 08:00:00'),
(1, '2024-06-15', '13:00:00', '15:00:00', 1, 0, '2', '2024-01-03 08:00:00'),
(2, '2024-06-18', '19:00:00', '21:00:00', 1, 0, '3', '2024-01-03 08:00:00');

-- ============================================================
-- 8. TABEL TRANSAKSI: Booking
-- ============================================================
CREATE TABLE Booking (
    ID_Booking          INT IDENTITY(1,1) PRIMARY KEY,
    ID_Customer         INT             NOT NULL,
    ID_Karyawan         INT             NOT NULL,
    ID_Jadwal           INT             NOT NULL,
    ID_Promo            INT             NULL,
    Tanggal_Booking     DATE            NOT NULL,
    Metode_Pembayaran   VARCHAR(20)     NOT NULL,
    Total_Bayar         DECIMAL(18,2)   NOT NULL,
    Status              INT             NOT NULL CHECK (Status IN (0,1,2,3)),
    Created_By          VARCHAR(50)     NOT NULL,
    Created_Date        DATETIME        NOT NULL,
    Modified_By         VARCHAR(50)     NULL,
    Modified_Date       DATETIME        NULL,
    FOREIGN KEY (ID_Customer) REFERENCES Customer(ID_Customer),
    FOREIGN KEY (ID_Karyawan) REFERENCES Karyawan(ID_Karyawan),
    FOREIGN KEY (ID_Jadwal)   REFERENCES Jadwal(ID_Jadwal),
    FOREIGN KEY (ID_Promo)    REFERENCES Promo(ID_Promo)
);

 CREATE UNIQUE INDEX UQ_Booking_Jadwal_Aktif
 ON Booking(ID_Jadwal)
 WHERE Status <> 3;

-- Status: 0 = Menunggu Konfirmasi, 1 = Berhasil, 2 = Selesai, 3 = Dibatalkan
INSERT INTO Booking
(ID_Customer, ID_Karyawan, ID_Jadwal, ID_Promo, Tanggal_Booking, Metode_Pembayaran, Total_Bayar, Status, Created_By, Created_Date, Modified_By, Modified_Date) VALUES
(1, 2, 1, NULL, '2024-05-30', 'Transfer Bank', 160000.00, 2, '1', '2024-05-30 10:00:00', '2', '2024-05-30 11:00:00'),
(2, 2, 2, 2,    '2024-05-31', 'QRIS',          150000.00, 2, '2', '2024-05-31 09:00:00', '2', '2024-05-31 10:00:00'),
(3, 3, 3, NULL, '2024-06-02', 'Transfer Bank', 200000.00, 2, '3', '2024-06-02 08:00:00', '3', '2024-06-02 09:00:00'),
(4, 3, 4, 4,    '2024-06-04', 'QRIS',          188000.00, 1, '4', '2024-06-04 07:00:00', '3', '2024-06-04 08:00:00'),
(5, 4, 5, NULL, '2024-06-06', 'Transfer Bank', 240000.00, 1, '5', '2024-06-06 14:00:00', '4', '2024-06-06 15:00:00'),
(6, 4, 6, 2,    '2024-06-07', 'QRIS',          220000.00, 3, '6', '2024-06-07 09:00:00', '4', '2024-06-07 10:00:00'),
(7, 2, 7, NULL, '2024-06-09', 'Transfer Bank', 300000.00, 1, '7', '2024-06-09 18:00:00', '2', '2024-06-09 19:00:00'),
(8, 3, 8, 4,    '2024-06-11', 'QRIS',          288000.00, 0, '8', '2024-06-11 07:00:00', NULL, NULL),
(1, 2, 9, NULL, '2024-06-14', 'Transfer Bank', 160000.00, 2, '1', '2024-06-14 12:00:00', '2', '2024-06-14 13:00:00'),
(3, 5, 10, 2,   '2024-06-17', 'QRIS',          190000.00, 3, '3', '2024-06-17 18:00:00', '5', '2024-06-17 19:00:00');


-- ============================================================
-- 9. TABEL TRANSAKSI: Pembatalan_Booking
-- ============================================================
CREATE TABLE Pembatalan_Booking (
    ID_Pembatalan   INT IDENTITY(1,1) PRIMARY KEY,
    ID_Booking      INT             NOT NULL,
    ID_Karyawan     INT             NOT NULL,
    Tanggal_Batal   DATE            NOT NULL,
    Alasan          VARCHAR(255)    NOT NULL,
    Biaya_Batal     DECIMAL(18,2)   NOT NULL,
    Nominal_Refund  DECIMAL(18,2)   NOT NULL,
    Metode_Refund   VARCHAR(20)     NOT NULL,
    Status          INT             NOT NULL CHECK (Status IN (0,1)),
    Created_By      VARCHAR(50)     NOT NULL,
    Created_Date    DATETIME        NOT NULL,
    Modified_By     VARCHAR(50)     NULL,
    Modified_Date   DATETIME        NULL,
    FOREIGN KEY (ID_Booking)   REFERENCES Booking(ID_Booking),
    FOREIGN KEY (ID_Karyawan)  REFERENCES Karyawan(ID_Karyawan)
);

 ALTER TABLE Pembatalan_Booking
 ADD CONSTRAINT UQ_Pembatalan_Booking UNIQUE (ID_Booking);

-- Biaya_Batal = 50% total bayar, Nominal_Refund = 50% total bayar
INSERT INTO Pembatalan_Booking
(ID_Booking, ID_Karyawan, Tanggal_Batal, Alasan, Biaya_Batal, Nominal_Refund, Metode_Refund, Status, Created_By, Created_Date) VALUES
(6,  4, '2024-06-06', 'Ada keperluan mendadak',           110000.00, 110000.00, 'QRIS', 1, '4', '2024-06-06 10:00:00'),
(10, 5, '2024-06-16', 'Jadwal bentrok dengan acara lain', 95000.00,  95000.00,  'QRIS', 1, '5', '2024-06-16 19:00:00');


-- ============================================================
-- 10. TABEL TRANSAKSI: Langganan
-- ============================================================
CREATE TABLE Langganan (
    ID_Langganan        INT IDENTITY(1,1) PRIMARY KEY,
    ID_Customer         INT             NOT NULL,
    ID_Karyawan         INT             NOT NULL,
    ID_Tipe             INT             NOT NULL,
    Tanggal_Mulai       DATE            NOT NULL,
    Tanggal_Selesai     DATE            NOT NULL,
    Total_Bayar         DECIMAL(18,2)   NOT NULL,
    Metode_Pembayaran   VARCHAR(20)     NOT NULL,
    Status              INT             NOT NULL CHECK (Status IN (0,1,2,3)),
    Created_By          VARCHAR(50)     NOT NULL,
    Created_Date        DATETIME        NOT NULL,
    Modified_By         VARCHAR(50)     NULL,
    Modified_Date       DATETIME        NULL,
    FOREIGN KEY (ID_Customer) REFERENCES Customer(ID_Customer),
    FOREIGN KEY (ID_Karyawan) REFERENCES Karyawan(ID_Karyawan),
    FOREIGN KEY (ID_Tipe)     REFERENCES Tipe_Member(ID_Tipe)
);


-- Status: 0 = Menunggu Konfirmasi, 1 = Aktif, 2 = Berakhir, 3 = Ditolak
INSERT INTO Langganan
(ID_Customer, ID_Karyawan, ID_Tipe, Tanggal_Mulai, Tanggal_Selesai,
 Total_Bayar, Metode_Pembayaran, Status, Created_By, Created_Date)
VALUES
(1, 2, 1, '2024-04-01', '2024-04-30', 100000.00, 'Transfer Bank', 1, '1', '2024-04-01 09:00:00'),
(2, 2, 2, '2024-04-10', '2024-05-09', 200000.00, 'QRIS',          1, '2', '2024-04-10 10:00:00'),
(3, 3, 3, '2024-05-01', '2024-05-31', 350000.00, 'Transfer Bank', 1, '3', '2024-05-01 08:00:00'),
(5, 3, 1, '2024-05-15', '2024-06-14', 100000.00, 'QRIS',          1, '5', '2024-05-15 09:00:00'),
(7, 4, 2, '2024-06-01', '2024-06-30', 200000.00, 'Transfer Bank', 1, '7', '2024-06-01 10:00:00'),
(4, 4, 1, '2024-06-05', '2024-07-04', 100000.00, 'QRIS',          1, '4', '2024-06-05 09:00:00'),
(6, 5, 3, '2024-06-10', '2024-07-09', 350000.00, 'Transfer Bank', 1, '6', '2024-06-10 08:00:00'),
(8, 5, 2, '2024-06-15', '2024-07-14', 200000.00, 'QRIS',          0, '8', '2024-06-15 11:00:00');


-- ============================================================
-- 11. TABEL MASTER: Alat
-- ============================================================
CREATE TABLE Alat (
    ID_Alat         INT IDENTITY(1,1) PRIMARY KEY,
    Nama_Alat       VARCHAR(25)     NOT NULL,
    Stok            INT             NOT NULL,
    Harga_Alat      DECIMAL(18,2)   NOT NULL,
    Photo_Alat      VARCHAR(255)    NULL,
    Status          INT             NOT NULL CHECK (Status IN (0,1)),
    Is_Deleted      BIT             NOT NULL DEFAULT 0,
    Created_By      VARCHAR(50)     NOT NULL,
    Created_Date    DATETIME        NOT NULL,
    Modified_By     VARCHAR(50)     NULL,
    Modified_Date   DATETIME        NULL,
    Deleted_By      VARCHAR(50)     NULL,
    Deleted_Date    DATETIME        NULL
);

ALTER TABLE Alat
ADD CONSTRAINT CK_Alat_Stok CHECK (Stok >= 0);

ALTER TABLE Alat
ADD CONSTRAINT CK_Alat_Harga CHECK (Harga_Alat >= 0);

INSERT INTO Alat
(Nama_Alat, Stok, Harga_Alat, Status, Is_Deleted, Created_By, Created_Date) VALUES
('Bola Basket SNI',     15, 150000.00, 1, 0, '2', '2024-01-04 08:00:00'),
('Bola Basket Premium', 10, 250000.00, 1, 0, '2', '2024-01-04 08:00:00'),
('Sepatu Basket',       20, 350000.00, 1, 0, '2', '2024-01-04 08:00:00'),
('Jersey Basket',       30, 120000.00, 1, 0, '2', '2024-01-04 08:00:00'),
('Pelindung Lutut',     25, 80000.00,  1, 0, '2', '2024-01-04 08:00:00');

-- ============================================================
-- 12. TABEL TRANSAKSI: Beli_Alat
-- ============================================================
CREATE TABLE Beli_Alat (
    ID_Beli             INT IDENTITY(1,1) PRIMARY KEY,
    ID_Karyawan         INT             NOT NULL,
    ID_Customer         INT             NOT NULL,
    Tanggal_Beli        DATE            NOT NULL,
    Metode_Pembayaran   VARCHAR(20)     NOT NULL,
    Total_Bayar         DECIMAL(18,2)   NOT NULL,
    Status              INT             NOT NULL CHECK (Status IN (0,1)),
    Created_By          VARCHAR(50)     NOT NULL,
    Created_Date        DATETIME        NOT NULL,
    Modified_By         VARCHAR(50)     NULL,
    Modified_Date       DATETIME        NULL,
    FOREIGN KEY (ID_Karyawan)  REFERENCES Karyawan(ID_Karyawan),
    FOREIGN KEY (ID_Customer)  REFERENCES Customer(ID_Customer)
);

INSERT INTO Beli_Alat
(ID_Karyawan, ID_Customer, Tanggal_Beli, Metode_Pembayaran, Total_Bayar, Status, Created_By, Created_Date) VALUES
(2, 1, '2024-05-10', 'Transfer Bank', 300000.00, 1, '1', '2024-05-10 10:00:00'),
(3, 2, '2024-05-12', 'QRIS',          370000.00, 1, '2', '2024-05-12 11:00:00'),
(4, 3, '2024-05-15', 'Transfer Bank', 500000.00, 1, '3', '2024-05-15 09:00:00'),
(2, 4, '2024-05-20', 'QRIS',          240000.00, 1, '4', '2024-05-20 14:00:00'),
(3, 5, '2024-06-01', 'Transfer Bank', 450000.00, 1, '5', '2024-06-01 10:00:00'),
(5, 6, '2024-06-05', 'QRIS',          150000.00, 1, '6', '2024-06-05 11:00:00'),
(2, 7, '2024-06-08', 'Transfer Bank', 700000.00, 1, '7', '2024-06-08 13:00:00'),
(4, 8, '2024-06-10', 'QRIS',          200000.00, 1, '8', '2024-06-10 15:00:00');

-- ============================================================
-- 13. TABEL TRANSAKSI: Detail_Beli_Alat
-- ============================================================
CREATE TABLE Detail_Beli_Alat (
    ID_Alat         INT             NOT NULL,
    ID_Beli         INT             NOT NULL,
    Jumlah          INT             NOT NULL,
    SubTotal        DECIMAL(18,2)   NOT NULL,
    PRIMARY KEY (ID_Alat, ID_Beli),
    FOREIGN KEY (ID_Alat) REFERENCES Alat(ID_Alat),
    FOREIGN KEY (ID_Beli) REFERENCES Beli_Alat(ID_Beli)
);

ALTER TABLE Detail_Beli_Alat
ADD CONSTRAINT CK_Detail_Jumlah CHECK (Jumlah > 0);

ALTER TABLE Detail_Beli_Alat
ADD CONSTRAINT CK_Detail_SubTotal CHECK (SubTotal >= 0);

INSERT INTO Detail_Beli_Alat
(ID_Alat, ID_Beli, Jumlah, SubTotal)
VALUES
(1, 1, 2, 300000.00),
(2, 2, 1, 250000.00),
(4, 2, 1, 120000.00),
(3, 3, 1, 350000.00),
(1, 3, 1, 150000.00),
(4, 4, 2, 240000.00),
(2, 5, 1, 250000.00),
(4, 5, 1, 120000.00),
(5, 5, 1, 80000.00),
(1, 6, 1, 150000.00),
(3, 7, 2, 700000.00),
(5, 8, 1, 80000.00),
(4, 8, 1, 120000.00);

-- ============================================================
-- VERIFIKASI JUMLAH DATA
-- ============================================================
-- Karyawan          : 5  data
-- Customer          : 8  data
-- Lapangan          : 4  data
-- Tipe_Member       : 3  data
-- Promo             : 4  data
-- Fasilitas_Lapangan: 12 data
-- Jadwal            : 10 data
-- Booking           : 10 data
-- Pembatalan_Booking: 2  data
-- Langganan         : 8  data
-- Alat              : 5  data
-- Beli_Alat         : 8  data
-- Detail_Beli_Alat  : 13 data
-- ============================================================
-- TOTAL DATA MASTER      : 41 data
-- TOTAL DATA TRANSAKSI   : 41 data
-- ============================================================
SELECT 'Fasilitas_Lapangan' AS Nama_Tabel, COUNT(*) AS Jumlah_Data FROM Fasilitas_Lapangan
UNION ALL
SELECT 'Customer', COUNT(*) FROM Customer
UNION ALL
SELECT 'Karyawan', COUNT(*) FROM Karyawan
UNION ALL
SELECT 'Lapangan', COUNT(*) FROM Lapangan
UNION ALL
SELECT 'Jadwal', COUNT(*) FROM Jadwal
UNION ALL
SELECT 'Promo', COUNT(*) FROM Promo
UNION ALL
SELECT 'Tipe_Member', COUNT(*) FROM Tipe_Member
UNION ALL
SELECT 'Alat', COUNT(*) FROM Alat
UNION ALL
SELECT 'Booking', COUNT(*) FROM Booking
UNION ALL
SELECT 'Langganan', COUNT(*) FROM Langganan
UNION ALL
SELECT 'Beli_Alat', COUNT(*) FROM Beli_Alat
UNION ALL
SELECT 'Detail_Beli_Alat', COUNT(*) FROM Detail_Beli_Alat
UNION ALL
SELECT 'Pembatalan_Booking', COUNT(*) FROM Pembatalan_Booking;
GO

SELECT * FROM Customer;
SELECT * FROM Karyawan;
SELECT * FROM Lapangan;
SELECT * FROM Fasilitas_Lapangan;
SELECT * FROM Jadwal;
SELECT * FROM Promo;
SELECT * FROM Tipe_Member;
SELECT * FROM Alat;
SELECT * FROM Booking;
SELECT * FROM Langganan;
SELECT * FROM Beli_Alat;
SELECT * FROM Detail_Beli_Alat;
SELECT * FROM Pembatalan_Booking;

BACKUP DATABASE Hoopball
TO DISK = 'C:\Program Files\Microsoft SQL Server\MSSQL17.MSSQLSERVER\MSSQL\Backup\Hoopball_Kel05.bak'
WITH INIT;