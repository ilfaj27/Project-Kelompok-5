-- ============================================================
-- SQL SCHEMA & DATA DUMMY - SISTEM HOOPBALL
-- Urutan: Master Tables → Transaksi Tables
-- ============================================================

CREATE DATABASE Hoopball;
USE Hoopball;
-- ============================================================
-- 1. TABEL MASTER: Karyawan
-- ============================================================
CREATE TABLE Karyawan (
    ID_Karyawan     VARCHAR(8)      NOT NULL PRIMARY KEY,
    Nama_Karyawan   VARCHAR(20)     NOT NULL,
    Tanggal_Lahir   DATE            NOT NULL,
    Tempat_Lahir    VARCHAR(50)     NOT NULL,
    Alamat          VARCHAR(100)    NOT NULL,
    Jenis_Kelamin   INT             NOT NULL CHECK (Jenis_Kelamin IN (0,1)),
    Is_Deleted      INT             NOT NULL CHECK (Is_Deleted IN (0,1)),
    Jabatan         VARCHAR(15)     NOT NULL,
    No_Telepon      VARCHAR(15)     NOT NULL,
    Email           VARCHAR(50)     NOT NULL,
    Username        VARCHAR(20)     NOT NULL,
    Kata_Sandi      VARCHAR(20)     NOT NULL,
    Status          INT             NOT NULL CHECK (Status IN (0,1)),
    Is_Deleted2     BIT             NOT NULL DEFAULT 0,
    Created_By      VARCHAR(50)     NOT NULL,
    Created_Date    DATETIME        NOT NULL,
    Modified_By     VARCHAR(50)     NULL,
    Modified_Date   DATETIME        NULL,
    Deleted_By      VARCHAR(50)     NULL,
    Deleted_Date    DATETIME        NULL
);

INSERT INTO Karyawan (ID_Karyawan, Nama_Karyawan, Tanggal_Lahir, Tempat_Lahir, Alamat, Jenis_Kelamin, Is_Deleted, Jabatan, No_Telepon, Email, Username, Kata_Sandi, Status, Is_Deleted2, Created_By, Created_Date) VALUES
('KRY00001', 'Rizky Pratama',    '1995-03-12', 'Jakarta',   'Jl. Mawar No.1 Jakarta',       1, 0, 'Manajer',  '081211110001', 'rizky@hoopball.com',   'rizky_p',   'Pass@1234', 1, 0, 'SYSTEM', '2024-01-01 08:00:00'),
('KRY00002', 'Sari Dewi',        '1997-07-22', 'Bandung',   'Jl. Melati No.5 Bandung',      0, 0, 'Karyawan', '081211110002', 'sari@hoopball.com',    'sari_d',    'Pass@1234', 1, 0, 'SYSTEM', '2024-01-01 08:00:00'),
('KRY00003', 'Andi Setiawan',    '1996-11-05', 'Surabaya',  'Jl. Kenanga No.10 Surabaya',   1, 0, 'Karyawan', '081211110003', 'andi@hoopball.com',    'andi_s',    'Pass@1234', 1, 0, 'SYSTEM', '2024-01-01 08:00:00'),
('KRY00004', 'Budi Santoso',     '1994-05-18', 'Yogyakarta','Jl. Dahlia No.3 Yogyakarta',   1, 0, 'Karyawan', '081211110004', 'budi@hoopball.com',    'budi_s',    'Pass@1234', 1, 0, 'SYSTEM', '2024-01-01 08:00:00'),
('KRY00005', 'Nina Rahayu',      '1998-09-30', 'Semarang',  'Jl. Anggrek No.7 Semarang',    0, 0, 'Karyawan', '081211110005', 'nina@hoopball.com',    'nina_r',    'Pass@1234', 1, 0, 'SYSTEM', '2024-01-01 08:00:00');

-- ============================================================
-- 2. TABEL MASTER: Customer
-- ============================================================
CREATE TABLE Customer (
    ID_Customer     VARCHAR(8)      NOT NULL PRIMARY KEY,
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
    Is_Deleted      BIT             NOT NULL DEFAULT 0,
    Created_By      VARCHAR(50)     NOT NULL,
    Created_Date    DATETIME        NOT NULL,
    Modified_By     VARCHAR(50)     NULL,
    Modified_Date   DATETIME        NULL,
    Deleted_By      VARCHAR(50)     NULL,
    Deleted_Date    DATETIME        NULL
);

INSERT INTO Customer (ID_Customer, Nama_Customer, Tanggal_Lahir, Tempat_Lahir, Jenis_Kelamin, Alamat, No_Telepon, Email, Username, Kata_Sandi, Status, Is_Deleted, Created_By, Created_Date) VALUES
('CST00001', 'Dimas Arya',       '2000-04-10', 'Jakarta',    1, 'Jl. Cempaka No.2 Jakarta',     '08121234001', 'dimas@mail.com',   'dimas_a',   'cust@1234', 1, 0, 'SYSTEM', '2024-01-05 09:00:00'),
('CST00002', 'Laila Putri',      '2001-08-15', 'Bandung',    0, 'Jl. Flamboyan No.4 Bandung',   '08121234002', 'laila@mail.com',   'laila_p',   'cust@1234', 1, 0, 'SYSTEM', '2024-01-06 09:00:00'),
('CST00003', 'Fajar Nugroho',    '1999-12-20', 'Surabaya',   1, 'Jl. Bougenville No.6 Sby',     '08121234003', 'fajar@mail.com',   'fajar_n',   'cust@1234', 1, 0, 'SYSTEM', '2024-01-07 09:00:00'),
('CST00004', 'Mega Lestari',     '2002-02-28', 'Semarang',   0, 'Jl. Teratai No.8 Semarang',    '08121234004', 'mega@mail.com',    'mega_l',    'cust@1234', 1, 0, 'SYSTEM', '2024-01-08 09:00:00'),
('CST00005', 'Hendra Wijaya',    '1998-06-14', 'Medan',      1, 'Jl. Wijaya No.10 Medan',       '08121234005', 'hendra@mail.com',  'hendra_w',  'cust@1234', 1, 0, 'SYSTEM', '2024-01-09 09:00:00'),
('CST00006', 'Rini Kusuma',      '2003-03-03', 'Malang',     0, 'Jl. Semeru No.12 Malang',      '08121234006', 'rini@mail.com',    'rini_k',    'cust@1234', 1, 0, 'SYSTEM', '2024-01-10 09:00:00'),
('CST00007', 'Yusuf Hakim',      '1997-10-11', 'Makassar',   1, 'Jl. Pantai No.14 Makassar',    '08121234007', 'yusuf@mail.com',   'yusuf_h',   'cust@1234', 1, 0, 'SYSTEM', '2024-01-11 09:00:00'),
('CST00008', 'Putri Anggraini',  '2001-01-25', 'Palembang',  0, 'Jl. Kamboja No.16 Palembang',  '08121234008', 'putri@mail.com',   'putri_a',   'cust@1234', 1, 0, 'SYSTEM', '2024-01-12 09:00:00');

-- ============================================================
-- 3. TABEL MASTER: Lapangan
-- ============================================================
CREATE TABLE Lapangan (
    ID_Lapangan     VARCHAR(8)      NOT NULL PRIMARY KEY,
    Nama_Lapangan   VARCHAR(25)     NOT NULL,
    Harga_Sewa      DECIMAL(18,2)   NOT NULL,
    Status          INT             NOT NULL CHECK (Status IN (0,1)),
    Is_Deleted      BIT             NOT NULL DEFAULT 0,
    Created_By      VARCHAR(50)     NOT NULL,
    Created_Date    DATETIME        NOT NULL,
    Modified_By     VARCHAR(50)     NULL,
    Modified_Date   DATETIME        NULL,
    Deleted_By      VARCHAR(50)     NULL,
    Deleted_Date    DATETIME        NULL
);

INSERT INTO Lapangan (ID_Lapangan, Nama_Lapangan, Harga_Sewa, Status, Is_Deleted, Created_By, Created_Date) VALUES
('LPN00001', 'Lapangan A',  80000.00,  1, 0, 'KRY00002', '2024-01-02 08:00:00'),
('LPN00002', 'Lapangan B',  100000.00, 1, 0, 'KRY00002', '2024-01-02 08:00:00'),
('LPN00003', 'Lapangan C',  120000.00, 1, 0, 'KRY00002', '2024-01-02 08:00:00'),
('LPN00004', 'Lapangan VIP',150000.00, 1, 0, 'KRY00002', '2024-01-02 08:00:00');

-- ============================================================
-- 4. TABEL MASTER: Tipe_Member
-- ============================================================
CREATE TABLE Tipe_Member (
    ID_Tipe         VARCHAR(8)      NOT NULL PRIMARY KEY,
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

INSERT INTO Tipe_Member (ID_Tipe, Nama_Tipe, Harga_Member, Potongan_Harga, Status, Is_Deleted, Created_By, Created_Date) VALUES
('TM000001', 'Silver',   100000.00, 10000.00, 1, 0, 'KRY00002', '2024-01-02 08:00:00'),
('TM000002', 'Gold',     200000.00, 20000.00, 1, 0, 'KRY00002', '2024-01-02 08:00:00'),
('TM000003', 'Platinum', 350000.00, 35000.00, 1, 0, 'KRY00002', '2024-01-02 08:00:00');

-- ============================================================
-- 5. TABEL MASTER: Promo
-- ============================================================
CREATE TABLE Promo (
    ID_Promo        VARCHAR(8)      NOT NULL PRIMARY KEY,
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

ALTER TABLE Alat ADD Foto_Alat VARCHAR(255) NULL;

/* Tabel booking */
CREATE TABLE Booking (
    ID_Booking          VARCHAR(8)      NOT NULL PRIMARY KEY,
    ID_Customer         VARCHAR(8)      NOT NULL,
    ID_Karyawan         VARCHAR(8)      NULL,
    ID_Jadwal           VARCHAR(8)      NOT NULL,
    ID_Promo            VARCHAR(8)      NULL,
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

-- Status: 0=Menunggu Konfirmasi, 1=Berhasil, 2=Selesai, 3=Dibatalkan
INSERT INTO Booking (ID_Booking, ID_Customer, ID_Karyawan, ID_Jadwal, ID_Promo, Tanggal_Booking, Metode_Pembayaran, Total_Bayar, Status, Created_By, Created_Date, Modified_By, Modified_Date) VALUES
('BKG00001', 'CST00001', 'KRY00002', 'JDW00001', NULL,       '2024-05-30', 'Transfer Bank', 160000.00, 2, 'CST00001', '2024-05-30 10:00:00', 'KRY00002', '2024-05-30 11:00:00'),
('BKG00002', 'CST00002', 'KRY00002', 'JDW00002', 'PRO00002', '2024-05-31', 'QRIS',          150000.00, 2, 'CST00002', '2024-05-31 09:00:00', 'KRY00002', '2024-05-31 10:00:00'),
('BKG00003', 'CST00003', 'KRY00003', 'JDW00003', NULL,       '2024-06-02', 'Transfer Bank', 200000.00, 2, 'CST00003', '2024-06-02 08:00:00', 'KRY00003', '2024-06-02 09:00:00'),
('BKG00004', 'CST00004', 'KRY00003', 'JDW00004', 'PRO00004', '2024-06-04', 'QRIS',          188000.00, 1, 'CST00004', '2024-06-04 07:00:00', 'KRY00003', '2024-06-04 08:00:00'),
('BKG00005', 'CST00005', 'KRY00004', 'JDW00005', NULL,       '2024-06-06', 'Transfer Bank', 240000.00, 1, 'CST00005', '2024-06-06 14:00:00', 'KRY00004', '2024-06-06 15:00:00'),
('BKG00006', 'CST00006', 'KRY00004', 'JDW00006', 'PRO00002', '2024-06-07', 'QRIS',          220000.00, 3, 'CST00006', '2024-06-07 09:00:00', 'KRY00004', '2024-06-07 10:00:00'),
('BKG00007', 'CST00007', 'KRY00002', 'JDW00007', NULL,       '2024-06-09', 'Transfer Bank', 300000.00, 1, 'CST00007', '2024-06-09 18:00:00', 'KRY00002', '2024-06-09 19:00:00'),
('BKG00008', 'CST00008', 'KRY00003', 'JDW00008', 'PRO00004', '2024-06-11', 'QRIS',          288000.00, 0, 'CST00008', '2024-06-11 07:00:00', NULL,        NULL),
('BKG00009', 'CST00001', 'KRY00002', 'JDW00009', NULL,       '2024-06-14', 'Transfer Bank', 160000.00, 2, 'CST00001', '2024-06-14 12:00:00', 'KRY00002', '2024-06-14 13:00:00'),
('BKG00010', 'CST00003', 'KRY00005', 'JDW00010', 'PRO00002', '2024-06-17', 'QRIS',          190000.00, 3, 'CST00003', '2024-06-17 18:00:00', 'KRY00005', '2024-06-17 19:00:00');

-- ============================================================
-- 9. TABEL TRANSAKSI: Pembatalan_Booking
-- ============================================================
CREATE TABLE Pembatalan_Booking (
    ID_Pembatalan   VARCHAR(8)      NOT NULL PRIMARY KEY,
    ID_Booking      VARCHAR(8)      NOT NULL,
    ID_Karyawan     VARCHAR(8)      NOT NULL,
    Tanggal_Batal   DATE            NOT NULL,
    Alasan          VARCHAR(255)    NOT NULL,
    Biaya_Batal     DECIMAL(18,2)   NOT NULL,
    Nominal_Refund  DECIMAL(18,2)   NOT NULL,
    Metode_Refund   VARCHAR(20)     NOT NULL,
    Status          INT             NOT NULL CHECK (Status IN (0,1)),
    Is_Deleted      BIT             NOT NULL DEFAULT 0,
    Created_By      VARCHAR(50)     NOT NULL,
    Created_Date    DATETIME        NOT NULL,
    Modified_By     VARCHAR(50)     NULL,
    Modified_Date   DATETIME        NULL,
    FOREIGN KEY (ID_Booking)   REFERENCES Booking(ID_Booking),
    FOREIGN KEY (ID_Karyawan)  REFERENCES Karyawan(ID_Karyawan)
);

-- Biaya_Batal = 50% total bayar, Nominal_Refund = 50% total bayar
INSERT INTO Pembatalan_Booking (ID_Pembatalan, ID_Booking, ID_Karyawan, Tanggal_Batal, Alasan, Biaya_Batal, Nominal_Refund, Metode_Refund, Status, Is_Deleted, Created_By, Created_Date) VALUES
('PBT00001', 'BKG00006', 'KRY00004', '2024-06-06', 'Ada keperluan mendadak',         110000.00, 110000.00, 'QRIS',          1, 0, 'KRY00004', '2024-06-06 10:00:00'),
('PBT00002', 'BKG00010', 'KRY00005', '2024-06-16', 'Jadwal bentrok dengan acara lain', 95000.00,  95000.00, 'QRIS',          1, 0, 'KRY00005', '2024-06-16 19:00:00');

-- ============================================================
-- 10. TABEL TRANSAKSI: Langganan
-- ============================================================
CREATE TABLE Langganan (
    ID_Langganan        VARCHAR(8)      NOT NULL PRIMARY KEY,
    ID_Customer         VARCHAR(8)      NOT NULL,
    ID_Tipe             VARCHAR(8)      NOT NULL,
    Tanggal_Mulai       DATE            NOT NULL,
    Tanggal_Selesai     DATE            NOT NULL,
    Total_Bayar         DECIMAL(18,2)   NOT NULL,
    Metode_Pembayaran   VARCHAR(20)     NOT NULL,
    Status              INT             NOT NULL CHECK (Status IN (0,1)),
    Created_By          VARCHAR(50)     NOT NULL,
    Created_Date        DATETIME        NOT NULL,
    Modified_By         VARCHAR(50)     NULL,
    Modified_Date       DATETIME        NULL,
    FOREIGN KEY (ID_Customer) REFERENCES Customer(ID_Customer),
    FOREIGN KEY (ID_Tipe)     REFERENCES Tipe_Member(ID_Tipe)
);

INSERT INTO Langganan (ID_Langganan, ID_Customer, ID_Tipe, Tanggal_Mulai, Tanggal_Selesai, Total_Bayar, Metode_Pembayaran, Status, Created_By, Created_Date) VALUES
('LGN00001', 'CST00001', 'TM000001', '2024-04-01', '2024-04-30', 100000.00, 'Transfer Bank', 1, 'CST00001', '2024-04-01 09:00:00'),
('LGN00002', 'CST00002', 'TM000002', '2024-04-10', '2024-05-09', 200000.00, 'QRIS',          1, 'CST00002', '2024-04-10 10:00:00'),
('LGN00003', 'CST00003', 'TM000003', '2024-05-01', '2024-05-31', 350000.00, 'Transfer Bank', 1, 'CST00003', '2024-05-01 08:00:00'),
('LGN00004', 'CST00005', 'TM000001', '2024-05-15', '2024-06-14', 100000.00, 'QRIS',          1, 'CST00005', '2024-05-15 09:00:00'),
('LGN00005', 'CST00007', 'TM000002', '2024-06-01', '2024-06-30', 200000.00, 'Transfer Bank', 1, 'CST00007', '2024-06-01 10:00:00'),
('LGN00006', 'CST00004', 'TM000001', '2024-06-05', '2024-07-04', 100000.00, 'QRIS',          1, 'CST00004', '2024-06-05 09:00:00'),
('LGN00007', 'CST00006', 'TM000003', '2024-06-10', '2024-07-09', 350000.00, 'Transfer Bank', 1, 'CST00006', '2024-06-10 08:00:00'),
('LGN00008', 'CST00008', 'TM000002', '2024-06-15', '2024-07-14', 200000.00, 'QRIS',          0, 'CST00008', '2024-06-15 11:00:00');

-- ============================================================
-- 11. TABEL MASTER: Alat
-- ============================================================
CREATE TABLE Alat (
    ID_Alat         VARCHAR(8)      NOT NULL PRIMARY KEY,
    Nama_Alat       VARCHAR(25)     NOT NULL,
    Stok            INT             NOT NULL,
    Harga_Alat      DECIMAL(18,2)   NOT NULL,
    Status          INT             NOT NULL CHECK (Status IN (0,1)),
    Is_Deleted      BIT             NOT NULL DEFAULT 0,
    Created_By      VARCHAR(50)     NOT NULL,
    Created_Date    DATETIME        NOT NULL,
    Modified_By     VARCHAR(50)     NULL,
    Modified_Date   DATETIME        NULL,
    Deleted_By      VARCHAR(50)     NULL,
    Deleted_Date    DATETIME        NULL
);

INSERT INTO Alat (ID_Alat, Nama_Alat, Stok, Harga_Alat, Status, Is_Deleted, Created_By, Created_Date) VALUES
('ALT00001', 'Bola Basket SNI',       15, 150000.00, 1, 0, 'KRY00002', '2024-01-04 08:00:00'),
('ALT00002', 'Bola Basket Premium',   10, 250000.00, 1, 0, 'KRY00002', '2024-01-04 08:00:00'),
('ALT00003', 'Sepatu Basket',         20, 350000.00, 1, 0, 'KRY00002', '2024-01-04 08:00:00'),
('ALT00004', 'Jersey Basket',         30, 120000.00, 1, 0, 'KRY00002', '2024-01-04 08:00:00'),
('ALT00005', 'Pelindung Lutut',       25, 80000.00,  1, 0, 'KRY00002', '2024-01-04 08:00:00');

-- ============================================================
-- 12. TABEL TRANSAKSI: Beli_Alat
-- ============================================================
CREATE TABLE Beli_Alat (
    ID_Beli             VARCHAR(8)      NOT NULL PRIMARY KEY,
    ID_Karyawan         VARCHAR(8)      NOT NULL,
    ID_Customer         VARCHAR(8)      NOT NULL,
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

TRUNCATE TABLE Jadwal;

INSERT INTO Jadwal (ID_Jadwal, ID_Lapangan, Tanggal, Jam_Mulai, Jam_Selesai, Status, Is_Deleted, Created_By, Created_Date) VALUES
('JD0001', 'LP0001', '2026-06-15', '08:00', '10:00', 1, 0, 'System', GETDATE()),
('JD0002', 'LP0001', '2026-06-15', '10:30', '12:00', 1, 0, 'System', GETDATE()), -- 1.5 jam
('JD0003', 'LP0001', '2026-06-15', '13:00', '16:00', 1, 0, 'System', GETDATE()), -- 3 jam
('JD0004', 'LP0002', '2026-06-15', '09:00', '11:00', 1, 0, 'System', GETDATE()),
('JD0005', 'LP0002', '2026-06-15', '15:00', '16:00', 1, 0, 'System', GETDATE()), -- 1 jam
('JD0006', 'LP0003', '2026-06-15', '18:00', '20:00', 1, 0, 'System', GETDATE()),
('JD0007', 'LP0001', '2026-06-16', '07:00', '09:00', 1, 0, 'System', GETDATE()),
('JD0008', 'LP0001', '2026-06-16', '16:00', '18:00', 1, 0, 'System', GETDATE()),
('JD0009', 'LP0002', '2026-06-16', '19:00', '21:00', 1, 0, 'System', GETDATE()),
('JD0010', 'LP0003', '2026-06-16', '20:00', '22:00', 1, 0, 'System', GETDATE()),
('JD0011', 'LP0001', '2026-06-17', '08:00', '11:00', 1, 0, 'System', GETDATE()), -- 3 jam
('JD0012', 'LP0002', '2026-06-17', '13:00', '15:00', 1, 0, 'System', GETDATE()),
('JD0013', 'LP0003', '2026-06-17', '15:30', '17:00', 1, 0, 'System', GETDATE()), -- 1.5 jam
('JD0014', 'LP0001', '2026-06-18', '09:00', '10:00', 1, 0, 'System', GETDATE()), -- 1 jam
('JD0015', 'LP0002', '2026-06-18', '10:00', '12:00', 1, 0, 'System', GETDATE()),
('JD0016', 'LP0003', '2026-06-18', '14:00', '16:00', 1, 0, 'System', GETDATE()),
('JD0017', 'LP0001', '2026-06-19', '18:00', '21:00', 1, 0, 'System', GETDATE()), -- 3 jam
('JD0018', 'LP0002', '2026-06-19', '19:00', '21:00', 1, 0, 'System', GETDATE()),
('JD0019', 'LP0003', '2026-06-19', '07:00', '09:00', 1, 0, 'System', GETDATE()),
('JD0020', 'LP0001', '2026-06-20', '08:00', '10:00', 1, 0, 'System', GETDATE()),
('JD0021', 'LP0002', '2026-06-20', '10:00', '12:00', 1, 0, 'System', GETDATE()),
('JD0022', 'LP0003', '2026-06-20', '12:30', '14:00', 1, 0, 'System', GETDATE()), -- 1.5 jam
('JD0023', 'LP0001', '2026-06-21', '15:00', '17:00', 1, 0, 'System', GETDATE()),
('JD0024', 'LP0002', '2026-06-21', '16:00', '18:00', 1, 0, 'System', GETDATE()),
('JD0025', 'LP0003', '2026-06-21', '19:00', '22:00', 1, 0, 'System', GETDATE()), -- 3 jam
('JD0026', 'LP0001', '2026-06-22', '07:00', '08:00', 1, 0, 'System', GETDATE()), -- 1 jam
('JD0027', 'LP0002', '2026-06-22', '09:00', '11:00', 1, 0, 'System', GETDATE()),
('JD0028', 'LP0003', '2026-06-22', '13:00', '15:00', 1, 0, 'System', GETDATE()),
('JD0029', 'LP0001', '2026-06-23', '18:00', '20:00', 1, 0, 'System', GETDATE()),
('JD0030', 'LP0002', '2026-06-23', '20:00', '22:00', 1, 0, 'System', GETDATE()),
('JD0031', 'LP0003', '2026-06-24', '08:00', '10:00', 1, 0, 'System', GETDATE()),
('JD0032', 'LP0001', '2026-06-24', '10:30', '12:00', 1, 0, 'System', GETDATE()), -- 1.5 jam
('JD0033', 'LP0002', '2026-06-24', '14:00', '16:00', 1, 0, 'System', GETDATE()),
('JD0034', 'LP0003', '2026-06-25', '16:00', '19:00', 1, 0, 'System', GETDATE()), -- 3 jam
('JD0035', 'LP0001', '2026-06-25', '19:00', '21:00', 1, 0, 'System', GETDATE()),
('JD0036', 'LP0002', '2026-06-26', '07:00', '09:00', 1, 0, 'System', GETDATE()),
('JD0037', 'LP0003', '2026-06-26', '09:30', '11:00', 1, 0, 'System', GETDATE()), -- 1.5 jam
('JD0038', 'LP0001', '2026-06-26', '13:00', '15:00', 1, 0, 'System', GETDATE()),
('JD0039', 'LP0002', '2026-06-27', '15:00', '16:00', 1, 0, 'System', GETDATE()), -- 1 jam
('JD0040', 'LP0003', '2026-06-27', '18:00', '20:00', 1, 0, 'System', GETDATE()),
('JD0041', 'LP0001', '2026-06-28', '08:00', '11:00', 1, 0, 'System', GETDATE()), -- 3 jam
('JD0042', 'LP0002', '2026-06-28', '11:00', '13:00', 1, 0, 'System', GETDATE()),
('JD0043', 'LP0003', '2026-06-28', '14:00', '16:00', 1, 0, 'System', GETDATE()),
('JD0044', 'LP0001', '2026-06-29', '16:30', '18:00', 1, 0, 'System', GETDATE()), -- 1.5 jam
('JD0045', 'LP0002', '2026-06-29', '19:00', '21:00', 1, 0, 'System', GETDATE()),
('JD0046', 'LP0003', '2026-06-30', '07:00', '09:00', 1, 0, 'System', GETDATE()),
('JD0047', 'LP0001', '2026-06-30', '09:00', '10:00', 1, 0, 'System', GETDATE()), -- 1 jam
('JD0048', 'LP0002', '2026-06-30', '13:00', '15:00', 1, 0, 'System', GETDATE()),
('JD0049', 'LP0003', '2026-06-30', '16:00', '18:00', 1, 0, 'System', GETDATE()),
('JD0050', 'LP0001', '2026-07-01', '19:00', '22:00', 1, 0, 'System', GETDATE()); -- 3 jam


-- Memasukkan 3 data dummy lapangan agar Foreign Key terpenuhi
INSERT INTO Lapangan (ID_Lapangan, Nama_Lapangan, Harga_Sewa, Status, Is_Deleted, Created_By, Created_Date) VALUES
('LP0001', 'HoopBall Pro Court 1', 150000.00, 1, 0, 'System', GETDATE()),
('LP0002', 'HoopBall Pro Court 2', 150000.00, 1, 0, 'System', GETDATE()),
('LP0003', 'HoopBall Outdoor Arena', 100000.00, 1, 0, 'System', GETDATE());

INSERT INTO Promo
(ID_Promo, Nama_Promo, Diskon, Tanggal_Mulai, Tanggal_Selesai, Status, Created_By)
VALUES
('PR0001', 'PROMO10', 10000, '2026-06-01', '2026-06-30', 1, 'System'),
('PR0002', 'HEMAT20', 20000, '2026-06-01', '2026-06-30', 1, 'System'),
('PR0003', 'WEEKEND', 15000, '2026-06-01', '2026-07-15', 1, 'System'),
('PR0004', 'HOOPDAY', 25000, '2026-06-10', '2026-07-10', 1, 'System');
GO

-- ============================================================
-- 13. TABEL TRANSAKSI: Detail_Beli_Alat
-- ============================================================
CREATE TABLE Detail_Beli_Alat (
    ID_Alat         VARCHAR(8)      NOT NULL,
    ID_Beli         VARCHAR(8)      NOT NULL,
    Jumlah          INT             NOT NULL,
    SubTotal        DECIMAL(18,2)   NOT NULL,
    PRIMARY KEY (ID_Alat, ID_Beli),
    FOREIGN KEY (ID_Alat) REFERENCES Alat(ID_Alat),
    FOREIGN KEY (ID_Beli) REFERENCES Beli_Alat(ID_Beli)
);

INSERT INTO Alat
(ID_Alat, Nama_Alat, Stok, Harga_Alat, Foto_Alat, Status, Created_By)
VALUES
('AT0001', 'Bola Basket', 20, 250000, NULL, 1, 'System'),
('AT0002', 'Knee Pad', 15, 75000, NULL, 1, 'System'),
('AT0003', 'Arm Sleeve', 25, 50000, NULL, 1, 'System'),
('AT0004', 'Jersey Basket', 30, 120000, NULL, 1, 'System'),
('AT0005', 'Sepatu Basket', 10, 450000, NULL, 1, 'System'),
('AT0006', 'Tas Olahraga', 12, 180000, NULL, 1, 'System');
GO

TRUNCATE TABLE Alat;

-- Pastikan tabel Alat sudah kosong (opsional jika baru buat)
-- TRUNCATE TABLE Alat;

INSERT INTO Alat (ID_Alat, Nama_Alat, Stok, Harga_Alat, Status, Is_Deleted, Created_By, Created_Date, Foto_Alat) VALUES
('AL0001', 'Spalding NBA Official', 15, 850000.00, 1, 0, 'System', GETDATE(), NULL),
('AL0002', 'Molten BG5000 Size 7', 20, 1200000.00, 1, 0, 'System', GETDATE(), NULL),
('AL0003', 'Wilson Evolution', 12, 950000.00, 1, 0, 'System', GETDATE(), NULL),
('AL0004', 'Nike LeBron 20', 8, 2500000.00, 1, 0, 'System', GETDATE(), NULL),
('AL0005', 'Adidas Harden Vol 7', 10, 2200000.00, 1, 0, 'System', GETDATE(), NULL),
('AL0006', 'Under Armour Curry 10', 5, 2300000.00, 1, 0, 'System', GETDATE(), NULL),
('AL0007', 'Puma MB.02', 7, 2100000.00, 1, 0, 'System', GETDATE(), NULL),
('AL0008', 'Nike Elite Socks Crew', 50, 150000.00, 1, 0, 'System', GETDATE(), NULL),
('AL0009', 'Adidas Creator Socks', 45, 120000.00, 1, 0, 'System', GETDATE(), NULL),
('AL0010', 'Stance NBA Socks', 30, 180000.00, 1, 0, 'System', GETDATE(), NULL),
('AL0011', 'McDavid Knee Pad', 25, 450000.00, 1, 0, 'System', GETDATE(), NULL),
('AL0012', 'Nike Pro Compression Leg', 20, 650000.00, 1, 0, 'System', GETDATE(), NULL),
('AL0013', 'Zamst A2-DX Ankle Brace', 10, 850000.00, 1, 0, 'System', GETDATE(), NULL),
('AL0014', 'Bauerfeind Sports Knee', 8, 1200000.00, 1, 0, 'System', GETDATE(), NULL),
('AL0015', 'Nike Swoosh Headband', 40, 80000.00, 1, 0, 'System', GETDATE(), NULL),
('AL0016', 'Jordan Jumpman Wristband', 35, 75000.00, 1, 0, 'System', GETDATE(), NULL),
('AL0017', 'Jersey Latihan Reversible', 20, 150000.00, 1, 0, 'System', GETDATE(), NULL),
('AL0018', 'Celana Basket Dry-Fit', 25, 130000.00, 1, 0, 'System', GETDATE(), NULL),
('AL0019', 'Nike Hoops Backpack', 15, 750000.00, 1, 0, 'System', GETDATE(), NULL),
('AL0020', 'Adidas Duffle Bag', 12, 600000.00, 1, 0, 'System', GETDATE(), NULL),
('AL0021', 'Gatorade Squeeze Bottle', 50, 85000.00, 1, 0, 'System', GETDATE(), NULL),
('AL0022', 'Nike Hyperfuel Bottle', 40, 120000.00, 1, 0, 'System', GETDATE(), NULL),
('AL0023', 'SKLZ Double Double', 5, 450000.00, 1, 0, 'System', GETDATE(), NULL),
('AL0024', 'Dribble Specs Glasses', 15, 75000.00, 1, 0, 'System', GETDATE(), NULL),
('AL0025', 'Agility Ladder', 10, 120000.00, 1, 0, 'System', GETDATE(), NULL),
('AL0026', 'Tarmak Bola Size 6', 15, 250000.00, 1, 0, 'System', GETDATE(), NULL),
('AL0027', 'Tarmak Bola Size 5', 10, 250000.00, 1, 0, 'System', GETDATE(), NULL),
('AL0028', 'Papan Strategi Basket', 8, 180000.00, 1, 0, 'System', GETDATE(), NULL),
('AL0029', 'Pompa Bola Portable', 30, 45000.00, 1, 0, 'System', GETDATE(), NULL),
('AL0030', 'Jarum Pompa Bola (Isi 5)', 100, 15000.00, 1, 0, 'System', GETDATE(), NULL),
('AL0031', 'Handuk Olahraga Micro', 40, 65000.00, 1, 0, 'System', GETDATE(), NULL),
('AL0032', 'Shoe Deodorizer Spray', 20, 55000.00, 1, 0, 'System', GETDATE(), NULL),
('AL0033', 'Grip Powder Basket', 15, 70000.00, 1, 0, 'System', GETDATE(), NULL),
('AL0034', 'Peluit Fox 40 Classic', 25, 90000.00, 1, 0, 'System', GETDATE(), NULL),
('AL0035', 'Stopwatch Digital Casio', 5, 250000.00, 1, 0, 'System', GETDATE(), NULL),
('AL0036', 'Rompi Tim (Set 12)', 10, 350000.00, 1, 0, 'System', GETDATE(), NULL),
('AL0037', 'Cone Marker (Isi 50)', 8, 150000.00, 1, 0, 'System', GETDATE(), NULL),
('AL0038', 'Kinesio Tape Roll', 30, 60000.00, 1, 0, 'System', GETDATE(), NULL),
('AL0039', 'Ice Bag Kompres', 15, 45000.00, 1, 0, 'System', GETDATE(), NULL),
('AL0040', 'Nike KD 15', 6, 2400000.00, 1, 0, 'System', GETDATE(), NULL),
('AL0041', 'Anta KT 8', 5, 1800000.00, 1, 0, 'System', GETDATE(), NULL),
('AL0042', 'Li-Ning Way of Wade', 4, 2600000.00, 1, 0, 'System', GETDATE(), NULL),
('AL0043', 'Peak Tony Parker 9', 7, 1500000.00, 1, 0, 'System', GETDATE(), NULL),
('AL0044', 'Rigorer AR1', 8, 1600000.00, 1, 0, 'System', GETDATE(), NULL),
('AL0045', 'Arm Sleeve Shooting', 25, 85000.00, 1, 0, 'System', GETDATE(), NULL),
('AL0046', 'Mouthguard Shock Doctor', 12, 250000.00, 1, 0, 'System', GETDATE(), NULL),
('AL0047', 'Resistance Band Set', 10, 120000.00, 1, 0, 'System', GETDATE(), NULL),
('AL0048', 'Jump Rope Speed', 15, 65000.00, 1, 0, 'System', GETDATE(), NULL),
('AL0049', 'Tas Sepatu Hoops', 20, 110000.00, 1, 0, 'System', GETDATE(), NULL),
('AL0050', 'Jaring Ring Basket Iron', 5, 180000.00, 1, 0, 'System', GETDATE(), NULL);

INSERT INTO Booking
(ID_Booking, ID_Customer, ID_Karyawan, ID_Jadwal, ID_Promo, Tanggal_Booking, Metode_Pembayaran, Total_Bayar, Status, Created_By)
VALUES
('BK0001', 'CS0001', 'KR0002', 'JD0001', 'PR0001', '2026-06-10', 'QRIS', 140000, 2, 'Customer'),
('BK0002', 'CS0002', 'KR0002', 'JD0002', NULL, '2026-06-10', 'Transfer Bank', 150000, 2, 'Customer'),
('BK0003', 'CS0003', 'KR0003', 'JD0003', 'PR0002', '2026-06-11', 'QRIS', 130000, 3, 'Customer'),
('BK0004', 'CS0004', 'KR0002', 'JD0004', NULL, '2026-06-11', 'Transfer Bank', 150000, 4, 'Customer'),
('BK0005', 'CS0005', 'KR0003', 'JD0005', 'PR0003', '2026-06-12', 'QRIS', 160000, 2, 'Customer'),
('BK0006', 'CS0006', 'KR0002', 'JD0006', NULL, '2026-06-12', 'Transfer Bank', 175000, 1, 'Customer'),
('BK0007', 'CS0007', 'KR0003', 'JD0007', 'PR0004', '2026-06-12', 'QRIS', 150000, 2, 'Customer'),
('BK0008', 'CS0001', 'KR0002', 'JD0008', NULL, '2026-06-13', 'QRIS', 175000, 4, 'Customer'),
('BK0009', 'CS0002', 'KR0003', 'JD0009', 'PR0001', '2026-06-13', 'Transfer Bank', 190000, 2, 'Customer'),
('BK0010', 'CS0003', 'KR0002', 'JD0010', NULL, '2026-06-14', 'QRIS', 200000, 1, 'Customer');
GO

INSERT INTO Langganan
(ID_Langganan, ID_Customer, ID_Karyawan, ID_Tipe, Tanggal_Mulai, Tanggal_Selesai, Metode_Pembayaran, Total_Bayar, Status, Created_By)
VALUES
('LG0001', 'CS0001', 'KR0002', 'TM0001', '2026-06-01', '2026-07-01', 'QRIS', 100000, 2, 'Customer'),
('LG0002', 'CS0002', 'KR0002', 'TM0002', '2026-06-02', '2026-07-02', 'Transfer Bank', 150000, 2, 'Customer'),
('LG0003', 'CS0003', 'KR0003', 'TM0003', '2026-06-03', '2026-07-03', 'QRIS', 200000, 2, 'Customer'),
('LG0004', 'CS0004', 'KR0003', 'TM0001', '2026-06-04', '2026-07-04', 'Transfer Bank', 100000, 2, 'Customer'),
('LG0005', 'CS0005', 'KR0002', 'TM0002', '2026-06-05', '2026-07-05', 'QRIS', 150000, 1, 'Customer');
GO

INSERT INTO Beli_Alat
(ID_Beli, ID_Karyawan, ID_Customer, Tanggal_Beli, Metode_Pembayaran, Total_Bayar, Status, Created_By)
VALUES
('BA0001', 'KR0002', 'CS0001', '2026-06-10', 'QRIS', 325000, 2, 'Customer'),
('BA0002', 'KR0003', 'CS0002', '2026-06-11', 'Transfer Bank', 170000, 2, 'Customer'),
('BA0003', 'KR0002', 'CS0003', '2026-06-12', 'QRIS', 450000, 2, 'Customer'),
('BA0004', 'KR0003', 'CS0004', '2026-06-13', 'Transfer Bank', 300000, 1, 'Customer'),
('BA0005', 'KR0002', 'CS0005', '2026-06-14', 'QRIS', 180000, 2, 'Customer');
GO

INSERT INTO Detail_Beli_Alat
(ID_Alat, ID_Beli, Jumlah, SubTotal)
VALUES
('AT0001', 'BA0001', 1, 250000),
('AT0002', 'BA0001', 1, 75000),
('AT0003', 'BA0002', 1, 50000),
('AT0004', 'BA0002', 1, 120000),
('AT0005', 'BA0003', 1, 450000),
('AT0001', 'BA0004', 1, 250000),
('AT0003', 'BA0004', 1, 50000),
('AT0006', 'BA0005', 1, 180000);
GO

INSERT INTO Pembatalan_Booking
(ID_Pembatalan, ID_Booking, ID_Karyawan, Tanggal_Batal, Alasan, Biaya_Batal, Nominal_Refund, Metode_Refund, Status_Refund, Created_By)
VALUES
('PB0001', 'BK0004', 'KR0002', '2026-06-12', 'Customer tidak bisa hadir', 75000, 75000, 'Transfer Bank', 2, 'Customer'),
('PB0002', 'BK0008', 'KR0003', '2026-06-14', 'Jadwal bertabrakan dengan kegiatan lain', 87500, 87500, 'QRIS', 2, 'Customer');
GO

SELECT 'Akun' AS Nama_Tabel, COUNT(*) AS Jumlah_Data FROM Akun
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


drop table Fasilitas_Lapangan
drop database Hoopball