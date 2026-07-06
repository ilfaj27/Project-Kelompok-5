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

DROP DATABASE Hoopball;
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
('3173011203950001', 'Rizky Pratama',   '1995-03-12', 'Jakarta',    'Jl. Mawar No.1 Jakarta',       1, 2, '081211110001', 'rizky.pratama@gmail.com',   'rizky_p',  'Pass@1234', 1, 0, 'SYSTEM', '2024-01-01 08:00:00'),
('3273012207970002', 'Sari Dewi',       '1997-07-22', 'Bandung',    'Jl. Melati No.5 Bandung',      0, 1, '081211110002', 'sari.dewi@gmail.com',       'sari_d',   'Pass@1234', 1, 0, 'SYSTEM', '2024-01-01 08:00:00'),
('3578010511960003', 'Andi Setiawan',   '1996-11-05', 'Surabaya',   'Jl. Kenanga No.10 Surabaya',   1, 1, '081211110003', 'andi.setiawan@gmail.com',   'andi_s',   'Pass@1234', 1, 0, 'SYSTEM', '2024-01-01 08:00:00'),
('3471011805940004', 'Budi Santoso',    '1994-05-18', 'Yogyakarta', 'Jl. Dahlia No.3 Yogyakarta',   1, 1, '081211110004', 'budi.santoso@gmail.com',    'budi_s',   'Pass@1234', 1, 0, 'SYSTEM', '2024-01-01 08:00:00'),
('3374013009980005', 'Nina Rahayu',     '1998-09-30', 'Semarang',   'Jl. Anggrek No.7 Semarang',    0, 1, '081211110005', 'nina.rahayu@gmail.com',     'nina_r',   'Pass@1234', 1, 0, 'SYSTEM', '2024-01-01 08:00:00'),
('3173011502990006', 'Dimas Saputra',   '1999-02-15', 'Bekasi',     'Jl. Cendana No.12 Bekasi',     1, 1, '081211110006', 'dimas.saputra@gmail.com',   'dimas_s',  'Pass@1234', 1, 0, 'SYSTEM', '2024-01-01 08:00:00'),
('3273011008960007', 'Maya Lestari',    '1996-08-10', 'Bogor',      'Jl. Flamboyan No.8 Bogor',     0, 1, '081211110007', 'maya.lestari@gmail.com',    'maya_l',   'Pass@1234', 1, 0, 'SYSTEM', '2024-01-01 08:00:00'),
('3578012104930008', 'Fajar Hidayat',   '1993-04-21', 'Malang',     'Jl. Cemara No.15 Malang',      1, 1, '081211110008', 'fajar.hidayat@gmail.com',   'fajar_h',  'Pass@1234', 1, 0, 'SYSTEM', '2024-01-01 08:00:00'),
('3471010701950009', 'Intan Permata',   '1995-01-07', 'Solo',       'Jl. Merpati No.9 Solo',        0, 1, '081211110009', 'intan.permata@gmail.com',   'intan_p',  'Pass@1234', 1, 0, 'SYSTEM', '2024-01-01 08:00:00'),
('3374011812970010', 'Agus Firmansyah', '1997-12-18', 'Semarang',   'Jl. Rajawali No.6 Semarang',   1, 1, '081211110010', 'agus.firmansyah@gmail.com', 'agus_f',   'Pass@1234', 1, 0, 'SYSTEM', '2024-01-01 08:00:00'),
('3173012306940011', 'Yuni Kartika',    '1994-06-23', 'Depok',      'Jl. Sakura No.11 Depok',       0, 1, '081211110011', 'yuni.kartika@gmail.com',    'yuni_k',   'Pass@1234', 1, 0, 'SYSTEM', '2024-01-01 08:00:00'),
('3273011409980012', 'Rian Maulana',    '1998-09-14', 'Bandung',    'Jl. Kamboja No.2 Bandung',     1, 1, '081211110012', 'rian.maulana@gmail.com',    'rian_m',   'Pass@1234', 1, 0, 'SYSTEM', '2024-01-01 08:00:00'),
('3578010503920013', 'Lina Marlina',    '1992-03-05', 'Surabaya',   'Jl. Teratai No.4 Surabaya',    0, 1, '081211110013', 'lina.marlina@gmail.com',    'lina_m',   'Pass@1234', 1, 0, 'SYSTEM', '2024-01-01 08:00:00'),
('3471012508960014', 'Hendra Wijaya',   '1996-08-25', 'Yogyakarta', 'Jl. Wijaya No.18 Yogyakarta',  1, 1, '081211110014', 'hendra.wijaya@gmail.com',   'hendra_w', 'Pass@1234', 1, 0, 'SYSTEM', '2024-01-01 08:00:00'),
('3374010201950015', 'Putri Amelia',    '1995-01-02', 'Semarang',   'Jl. Garuda No.13 Semarang',    0, 1, '081211110015', 'putri.amelia@gmail.com',    'putri_a',  'Pass@1234', 1, 0, 'SYSTEM', '2024-01-01 08:00:00'),
('3173011108970016', 'Eko Prasetyo',    '1997-08-11', 'Jakarta',    'Jl. Mangga No.16 Jakarta',     1, 1, '081211110016', 'eko.prasetyo@gmail.com',    'eko_p',    'Pass@1234', 1, 0, 'SYSTEM', '2024-01-01 08:00:00'),
('3273010902940017', 'Fitri Handayani', '1994-02-09', 'Cirebon',    'Jl. Nusa Indah No.17 Cirebon', 0, 1, '081211110017', 'fitri.handayani@gmail.com', 'fitri_h',  'Pass@1234', 1, 0, 'SYSTEM', '2024-01-01 08:00:00'),
('3578011706990018', 'Yoga Pratama',    '1999-06-17', 'Kediri',     'Jl. Elang No.19 Kediri',       1, 1, '081211110018', 'yoga.pratama@gmail.com',    'yoga_p',   'Pass@1234', 1, 0, 'SYSTEM', '2024-01-01 08:00:00'),
('3471010303930019', 'Citra Lestari',   '1993-03-03', 'Magelang',   'Jl. Pahlawan No.20 Magelang',  0, 1, '081211110019', 'citra.lestari@gmail.com',   'citra_l',  'Pass@1234', 1, 0, 'SYSTEM', '2024-01-01 08:00:00'),
('3374012808980020', 'Arif Nugroho',    '1998-08-28', 'Purwokerto', 'Jl. Kenari No.21 Purwokerto',  1, 1, '081211110020', 'arif.nugroho@gmail.com',    'arif_n',   'Pass@1234', 1, 0, 'SYSTEM', '2024-01-01 08:00:00');

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
('Dimas Arya',      '2000-04-10', 'Jakarta',      1, 'Jl. Cempaka No.2 Jakarta',      '08121234001', 'dimas.arya@gmail.com',      'dimas_a',    'cust@1234', 1, 0, 'SYSTEM', '2024-01-05 09:00:00'),
('Laila Putri',     '2001-08-15', 'Bandung',      0, 'Jl. Flamboyan No.4 Bandung',    '08121234002', 'laila.putri@gmail.com',     'laila_p',    'cust@1234', 1, 0, 'SYSTEM', '2024-01-06 09:00:00'),
('Fajar Nugroho',   '1999-12-20', 'Surabaya',     1, 'Jl. Bougenville No.6 Surabaya', '08121234003', 'fajar.nugroho@gmail.com',   'fajar_n',    'cust@1234', 1, 0, 'SYSTEM', '2024-01-07 09:00:00'),
('Mega Lestari',    '2002-02-28', 'Semarang',     0, 'Jl. Teratai No.8 Semarang',     '08121234004', 'mega.lestari@gmail.com',    'mega_l',     'cust@1234', 1, 0, 'SYSTEM', '2024-01-08 09:00:00'),
('Hendra Wijaya',   '1998-06-14', 'Medan',        1, 'Jl. Wijaya No.10 Medan',         '08121234005', 'hendra.wijaya@gmail.com',   'hendra_w',   'cust@1234', 1, 0, 'SYSTEM', '2024-01-09 09:00:00'),
('Rini Kusuma',     '2003-03-03', 'Malang',       0, 'Jl. Semeru No.12 Malang',        '08121234006', 'rini.kusuma@gmail.com',     'rini_k',     'cust@1234', 1, 0, 'SYSTEM', '2024-01-10 09:00:00'),
('Yusuf Hakim',     '1997-10-11', 'Makassar',     1, 'Jl. Pantai No.14 Makassar',      '08121234007', 'yusuf.hakim@gmail.com',     'yusuf_h',    'cust@1234', 1, 0, 'SYSTEM', '2024-01-11 09:00:00'),
('Putri Anggraini', '2001-01-25', 'Palembang',    0, 'Jl. Kamboja No.16 Palembang',    '08121234008', 'putri.anggraini@gmail.com', 'putri_a',    'cust@1234', 1, 0, 'SYSTEM', '2024-01-12 09:00:00'),
('Ahmad Fauzan',    '1999-09-18', 'Bogor',        1, 'Jl. Melati No.18 Bogor',         '08121234009', 'ahmad.fauzan@gmail.com',    'ahmad_f',    'cust@1234', 1, 0, 'SYSTEM', '2024-01-13 09:00:00'),
('Siska Ramadhani', '2000-07-07', 'Depok',        0, 'Jl. Anggrek No.20 Depok',        '08121234010', 'siska.r@gmail.com',         'siska_r',    'cust@1234', 1, 0, 'SYSTEM', '2024-01-14 09:00:00'),
('Rizky Saputra',   '1998-05-12', 'Bekasi',       1, 'Jl. Mawar No.22 Bekasi',         '08121234011', 'rizky.saputra@gmail.com',   'rizky_s',    'cust@1234', 1, 0, 'SYSTEM', '2024-01-15 09:00:00'),
('Nabila Sari',     '2002-04-30', 'Yogyakarta',   0, 'Jl. Kenanga No.24 Yogyakarta',   '08121234012', 'nabila.sari@gmail.com',     'nabila_s',   'cust@1234', 1, 0, 'SYSTEM', '2024-01-16 09:00:00'),
('Bagus Prakoso',   '1997-11-09', 'Solo',         1, 'Jl. Merdeka No.26 Solo',         '08121234013', 'bagus.prakoso@gmail.com',   'bagus_p',    'cust@1234', 1, 0, 'SYSTEM', '2024-01-17 09:00:00'),
('Anisa Rahma',     '2001-06-22', 'Cirebon',      0, 'Jl. Cendana No.28 Cirebon',      '08121234014', 'anisa.rahma@gmail.com',     'anisa_r',    'cust@1234', 1, 0, 'SYSTEM', '2024-01-18 09:00:00'),
('Ilham Maulana',   '1999-03-15', 'Tasikmalaya',  1, 'Jl. Sudirman No.30 Tasik',       '08121234015', 'ilham.maulana@gmail.com',   'ilham_m',    'cust@1234', 1, 0, 'SYSTEM', '2024-01-19 09:00:00'),
('Vina Oktaviani',  '2003-10-05', 'Pekanbaru',    0, 'Jl. Nangka No.32 Pekanbaru',     '08121234016', 'vina.oktaviani@gmail.com',  'vina_o',     'cust@1234', 1, 0, 'SYSTEM', '2024-01-20 09:00:00'),
('Reza Kurniawan',  '1998-08-17', 'Padang',       1, 'Jl. Diponegoro No.34 Padang',    '08121234017', 'reza.kurniawan@gmail.com',  'reza_k',     'cust@1234', 1, 0, 'SYSTEM', '2024-01-21 09:00:00'),
('Cindy Maharani',  '2002-12-11', 'Pontianak',    0, 'Jl. Pahlawan No.36 Pontianak',   '08121234018', 'cindy.maharani@gmail.com',  'cindy_m',    'cust@1234', 1, 0, 'SYSTEM', '2024-01-22 09:00:00'),
('Doni Prabowo',    '1997-01-08', 'Balikpapan',   1, 'Jl. Ahmad Yani No.38 Balikpapan','08121234019', 'doni.prabowo@gmail.com',    'doni_p',     'cust@1234', 1, 0, 'SYSTEM', '2024-01-23 09:00:00'),
('Nadya Khairunnisa','2001-09-27','Banjarmasin',  0, 'Jl. Hasan Basri No.40 Banjarmasin','08121234020','nadya.kh@gmail.com',       'nadya_k',    'cust@1234', 1, 0, 'SYSTEM', '2024-01-24 09:00:00');


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
('Lapangan D',   80000.00,  1, 0, '2', '2024-01-02 08:00:00'),
('Lapangan E',   90000.00,  1, 0, '2', '2024-01-02 08:00:00'),
('Lapangan F',   100000.00, 1, 0, '2', '2024-01-02 08:00:00'),
('Lapangan G',   110000.00, 1, 0, '2', '2024-01-02 08:00:00'),
('Lapangan H',   120000.00, 1, 0, '2', '2024-01-02 08:00:00'),
('Lapangan I',   130000.00, 1, 0, '2', '2024-01-02 08:00:00'),
('Lapangan J',   140000.00, 1, 0, '2', '2024-01-02 08:00:00'),
('Lapangan K',   150000.00, 1, 0, '2', '2024-01-02 08:00:00'),
('Lapangan L',   160000.00, 1, 0, '2', '2024-01-02 08:00:00'),
('Lapangan M',   170000.00, 1, 0, '2', '2024-01-02 08:00:00'),
('Lapangan N',   180000.00, 1, 0, '2', '2024-01-02 08:00:00'),
('Lapangan O',   190000.00, 1, 0, '2', '2024-01-02 08:00:00'),
('Lapangan P',   200000.00, 1, 0, '2', '2024-01-02 08:00:00'),
('Lapangan Q',   210000.00, 1, 0, '2', '2024-01-02 08:00:00'),
('Lapangan R',   220000.00, 1, 0, '2', '2024-01-02 08:00:00'),
('Lapangan S',   230000.00, 1, 0, '2', '2024-01-02 08:00:00');


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
('Perak',            100000.00, 10000.00, 1, 0, 1, '2024-01-02 08:00:00'),
('Emas',             200000.00, 20000.00, 1, 0, 1, '2024-01-02 08:00:00'),
('Platina',          350000.00, 35000.00, 1, 0, 1, '2024-01-02 08:00:00'),
('Intan',            500000.00, 50000.00, 1, 0, 1, '2024-01-02 08:00:00'),
('Perunggu',          75000.00,  7500.00, 1, 0, 1, '2024-01-02 08:00:00'),
('Pelajar',           50000.00,  5000.00, 1, 0, 1, '2024-01-02 08:00:00'),
('Reguler',          120000.00, 12000.00, 1, 0, 1, '2024-01-02 08:00:00'),
('Premium',          250000.00, 25000.00, 1, 0, 1, '2024-01-02 08:00:00'),
('Eksekutif',        400000.00, 40000.00, 1, 0, 1, '2024-01-02 08:00:00'),
('VIP',              600000.00, 60000.00, 1, 0, 1, '2024-01-02 08:00:00'),
('Keluarga',         300000.00, 30000.00, 1, 0, 1, '2024-01-02 08:00:00'),
('Korporat',         700000.00, 70000.00, 1, 0, 1, '2024-01-02 08:00:00'),
('Akhir Pekan',      180000.00, 18000.00, 1, 0, 1, '2024-01-02 08:00:00'),
('Hari Kerja',       150000.00, 15000.00, 1, 0, 1, '2024-01-02 08:00:00'),
('Pagi',              90000.00,  9000.00, 1, 0, 1, '2024-01-02 08:00:00'),
('Malam',            170000.00, 17000.00, 1, 0, 1, '2024-01-02 08:00:00'),
('Tahunan',        1000000.00,100000.00, 1, 0, 1, '2024-01-02 08:00:00'),
('Semester',         550000.00, 55000.00, 1, 0, 1, '2024-01-02 08:00:00'),
('Triwulan',         300000.00, 30000.00, 1, 0, 1, '2024-01-02 08:00:00'),
('Dasar',             80000.00,  8000.00, 1, 0, 1, '2024-01-02 08:00:00');

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
    ID_Fasilitas     INT IDENTITY(1,1) PRIMARY KEY,
    Nama_Fasilitas   VARCHAR(25)     NOT NULL,
    Detail_Fasilitas VARCHAR(50)     NOT NULL,
    Stok_Total       INT             NOT NULL DEFAULT 0 CHECK (Stok_Total >= 0),
    Stok_Tersedia    INT             NOT NULL DEFAULT 0 CHECK (Stok_Tersedia >= 0),
    Status           INT             NOT NULL CHECK (Status IN (0,1)),
    Is_Deleted       BIT             NOT NULL DEFAULT 0,
    Created_By       VARCHAR(50)     NOT NULL,
    Created_Date     DATETIME        NOT NULL,
    Modified_By      VARCHAR(50)     NULL,
    Modified_Date    DATETIME        NULL,
    Deleted_By       VARCHAR(50)     NULL,
    Deleted_Date     DATETIME        NULL
);

INSERT INTO Fasilitas_Lapangan
(Nama_Fasilitas, Detail_Fasilitas, Stok_Total, Stok_Tersedia, Status, Is_Deleted, Created_By, Created_Date)
VALUES
('Bola Basket Standard',  'Bola karet size 7',            30,  22,  1, 0, '2', '2024-01-03 08:00:00'), -- Terpakai 8
('Bola Basket Premium',   'Bola kulit asli premium',      15,  11,  1, 0, '2', '2024-01-03 08:00:00'), -- Terpakai 4
('Pencahayaan LED',       'Lampu sorot LED lapangan',     24,  12,  1, 0, '2', '2024-01-03 08:00:00'), -- Terpakai 12
('Lantai Vinyl',          'Lantai vinyl anti-slip',       10,  9,   1, 0, '2', '2024-01-03 08:00:00'), -- Terpakai 1
('Papan Skor Digital',    'Papan skor digital utama',     5,   3,   1, 0, '2', '2024-01-03 08:00:00'), -- Terpakai 2
('Ring Basket',           'Ring basket profesional',      8,   6,   1, 0, '2', '2024-01-03 08:00:00'), -- Terpakai 2
('AC Central',            'AC central pendingin ruangan',  6,   4,   1, 0, '2', '2024-01-03 08:00:00'), -- Terpakai 2
('Rompi Latihan Merah',   'Rompi latihan tim merah',      50,  40,  1, 0, '2', '2024-01-03 08:00:00'), -- Terpakai 10
('Rompi Latihan Biru',    'Rompi latihan tim biru',       50,  40,  1, 0, '2', '2024-01-03 08:00:00'), -- Terpakai 10
('Cone Kerucut Latihan',  'Cone pembatas latihan kelincahan',100, 60,  1, 0, '2', '2024-01-03 08:00:00'), -- Terpakai 40
('Whistle Wasit',         'Peluit wasit nyaring',         10,  10,  1, 0, '2', '2024-01-03 08:00:00'), -- Stok utuh
('Papan Strategi Coach',  'Papan tulis taktis magnetik',  5,   5,   1, 0, '2', '2024-01-03 08:00:00'), -- Stok utuh
('Pompa Bola Portable',   'Pompa angin manual portable',  4,   4,   1, 0, '2', '2024-01-03 08:00:00'), -- Stok utuh
('Jaring Gawang Basket',  'Jaring nylon pengganti ring',  12,  12,  1, 0, '2', '2024-01-03 08:00:00'), -- Stok utuh
('P3K First Aid Kit',     'Kotak obat pertolongan pertama',8,   8,   1, 0, '2', '2024-01-03 08:00:00'), -- Stok utuh
('Kursi Pemain Cadangan', 'Kursi lipat besi cadangan',    40,  40,  1, 0, '2', '2024-01-03 08:00:00'), -- Stok utuh
('Sound System Speaker',  'Speaker portable bluetooth',   4,   4,   1, 0, '2', '2024-01-03 08:00:00'), -- Stok utuh
('Kipas Angin Dinding',   'Kipas angin blower dinding',   12,  12,  1, 0, '2', '2024-01-03 08:00:00'), -- Stok utuh
('Lemari Loker Pemain',   'Loker penyimpanan barang',     20,  20,  1, 0, '2', '2024-01-03 08:00:00'), -- Stok utuh
('Water Dispenser',       'Dispenser air minum galon',    6,   6,   1, 0, '2', '2024-01-03 08:00:00'); -- Stok utuh

-- ==========================================================================================
-- TABEL PENGHUBUNG:Detail_Lapangan_Fasilitas (
-- ==========================================================================================
CREATE TABLE Detail_Lapangan_Fasilitas (
    ID_Lapangan     INT             NOT NULL,
    ID_Fasilitas    INT             NOT NULL,
    Jumlah_Digunakan INT            NOT NULL CHECK (Jumlah_Digunakan > 0),
    PRIMARY KEY (ID_Lapangan, ID_Fasilitas),
    FOREIGN KEY (ID_Lapangan) REFERENCES Lapangan(ID_Lapangan) ON DELETE CASCADE,
    FOREIGN KEY (ID_Fasilitas) REFERENCES Fasilitas_Lapangan(ID_Fasilitas)
);

INSERT INTO Detail_Lapangan_Fasilitas (ID_Lapangan, ID_Fasilitas, Jumlah_Digunakan)
VALUES
(1, 1, 2),  -- Lapangan A: 2 Bola Basket Standard
(1, 3, 4),  -- Lapangan A: 4 Pencahayaan LED
(1, 4, 1),  -- Lapangan A: 1 Lantai Vinyl
(1, 8, 10), -- Lapangan A: 10 Rompi Merah
(1, 10, 20),-- Lapangan A: 20 Cone Latihan

(2, 1, 2),  -- Lapangan B: 2 Bola Basket Standard
(2, 5, 1),  -- Lapangan B: 1 Papan Skor Digital
(2, 6, 2),  -- Lapangan B: 2 Ring Basket
(2, 10, 20),-- Lapangan B: 20 Cone Latihan

(3, 1, 2),  -- Lapangan C: 2 Bola Basket Standard
(3, 3, 4),  -- Lapangan C: 4 Pencahayaan LED

(4, 1, 2),  -- Lapangan VIP: 2 Bola Basket Standard
(4, 2, 4),  -- Lapangan VIP: 4 Bola Basket Premium
(4, 3, 4),  -- Lapangan VIP: 4 Pencahayaan LED
(4, 5, 1),  -- Lapangan VIP: 1 Papan Skor Digital
(4, 7, 2),  -- Lapangan VIP: 2 AC Central
(4, 9, 10); -- Lapangan VIP: 10 Rompi Biru

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
    Harga_Beli      DECIMAL(18,2)   NOT NULL, 
    Harga_Jual      DECIMAL(18,2)   NOT NULL, 
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
ADD CONSTRAINT CK_Alat_Harga_Beli CHECK (Harga_Beli >= 0);

ALTER TABLE Alat
ADD CONSTRAINT CK_Alat_Harga_Jual CHECK (Harga_Jual >= 0);

INSERT INTO Alat
(Nama_Alat, Stok, Harga_Beli, Harga_Jual, Status, Is_Deleted, Created_By, Created_Date) VALUES
('Bola Basket SNI',     15,  100000.00, 150000.00, 1, 0, '2', '2024-01-04 08:00:00'),
('Bola Basket Premium', 10,  180000.00, 250000.00, 1, 0, '2', '2024-01-04 08:00:00'),
('Sepatu Basket VIP',   20,  250000.00, 350000.00, 1, 0, '2', '2024-01-04 08:00:00'),
('Jersey Basket Merah',  30,  80000.00,  120000.00, 1, 0, '2', '2024-01-04 08:00:00'),
('Pelindung Lutut Pro', 25,  50000.00,  80000.00,  1, 0, '2', '2024-01-04 08:00:00'),
('Pelindung Siku',      20,  40000.00,  65000.00,  1, 0, '2', '2024-01-04 08:00:00'),
('Kaos Kaki Olahraga',  50,  15000.00,  30000.00,  1, 0, '2', '2024-01-04 08:00:00'),
('Papan Strategi Coach',12,  60000.00,  95000.00,  1, 0, '2', '2024-01-04 08:00:00'),
('Peluit Wasit Fox 40', 18,  25000.00,  45000.00,  1, 0, '2', '2024-01-04 08:00:00'),
('Pompa Bola Portable', 10,  35000.00,  60000.00,  1, 0, '2', '2024-01-04 08:00:00'),
('Jaring Ring Nylon',   22,  20000.00,  40000.00,  1, 0, '2', '2024-01-04 08:00:00'),
('Cone Mangkok Latihan',100, 7000.00,   12000.00,  1, 0, '2', '2024-01-04 08:00:00'),
('Agility Ladder 4M',   15,  75000.00,  110000.00, 1, 0, '2', '2024-01-04 08:00:00'),
('Tas Serut Tas Basket',40,  25000.00,  45000.00,  1, 0, '2', '2024-01-04 08:00:00'),
('Headband Keringat',   25,  15000.00,  25000.00,  1, 0, '2', '2024-01-04 08:00:00'),
('Wristband Karet',     35,  10000.00,  18000.00,  1, 0, '2', '2024-01-04 08:00:00'),
('Ankle Support Deker', 30,  55000.00,  90000.00,  1, 0, '2', '2024-01-04 08:00:00'),
('Botol Minum Tumbler',  28,  20000.00,  35000.00,  1, 0, '2', '2024-01-04 08:00:00'),
('Handuk Kecil Micro',  45,  12000.00,  20000.00,  1, 0, '2', '2024-01-04 08:00:00'),
('Papan Skor Meja Lipat',6,   110000.00, 185000.00, 1, 0, '2', '2024-01-04 08:00:00');

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
    FOREIGN KEY (ID_Beli) REFERENCES Beli_Alat(ID_Beli) ON DELETE CASCADE
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
SELECT 'Detail_Lapangan_Fasilitas', COUNT(*) FROM Detail_Lapangan_Fasilitas
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




