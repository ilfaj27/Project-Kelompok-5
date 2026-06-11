CREATE DATABASE Hoopball;
USE Hoopball;

/* Tabel akun pengguna */
CREATE TABLE Akun (
    ID_Akun VARCHAR(6) NOT NULL PRIMARY KEY,
    Username VARCHAR(20) NOT NULL,
    Email VARCHAR(50) NOT NULL,
    Kata_Sandi VARCHAR(50) NOT NULL,
    Role INT NOT NULL DEFAULT 3,
    Status INT NOT NULL DEFAULT 1,
    Is_Deleted BIT NOT NULL DEFAULT 0,
    Created_By VARCHAR(50) NOT NULL,
    Created_Date DATETIME NOT NULL DEFAULT GETDATE(),
    Modified_By VARCHAR(50) NULL,
    Modified_Date DATETIME NULL,
    Deleted_By VARCHAR(50) NULL,
    Deleted_Date DATETIME NULL,

    CONSTRAINT UQ_Akun_Username UNIQUE (Username),
    CONSTRAINT UQ_Akun_Email UNIQUE (Email),
    CONSTRAINT CK_Akun_Role CHECK (Role IN (1, 2, 3)),
    CONSTRAINT CK_Akun_Status CHECK (Status IN (0, 1))
);
GO

/* Tabel customer */
CREATE TABLE Customer (
    ID_Customer VARCHAR(6) NOT NULL PRIMARY KEY,
    ID_Akun VARCHAR(6) NOT NULL,
    Nama_Customer VARCHAR(20) NOT NULL,
    Jenis_Kelamin INT NOT NULL,
    Tanggal_Lahir DATE NOT NULL,
    Tempat_Lahir VARCHAR(50) NOT NULL,
    Alamat VARCHAR(100) NOT NULL,
    No_Telepon VARCHAR(15) NOT NULL,
    Status INT NOT NULL DEFAULT 1,
    Is_Deleted BIT NOT NULL DEFAULT 0,
    Created_By VARCHAR(50) NOT NULL,
    Created_Date DATETIME NOT NULL DEFAULT GETDATE(),
    Modified_By VARCHAR(50) NULL,
    Modified_Date DATETIME NULL,
    Deleted_By VARCHAR(50) NULL,
    Deleted_Date DATETIME NULL,

    CONSTRAINT UQ_Customer_Akun UNIQUE (ID_Akun),
    CONSTRAINT FK_Customer_Akun FOREIGN KEY (ID_Akun) REFERENCES Akun(ID_Akun),
    CONSTRAINT CK_Customer_JenisKelamin CHECK (Jenis_Kelamin IN (1, 2)),
    CONSTRAINT CK_Customer_Status CHECK (Status IN (0, 1))
);
GO

/* Tabel karyawan */
CREATE TABLE Karyawan (
    ID_Karyawan VARCHAR(6) NOT NULL PRIMARY KEY,
    ID_Akun VARCHAR(6) NOT NULL,
    Nama_Karyawan VARCHAR(20) NOT NULL,
    Jenis_Kelamin INT NOT NULL,
    Tanggal_Lahir DATE NOT NULL,
    Tempat_Lahir VARCHAR(50) NOT NULL,
    Jabatan INT NOT NULL,
    No_Telepon VARCHAR(15) NOT NULL,
    Status INT NOT NULL DEFAULT 1,
    Is_Deleted BIT NOT NULL DEFAULT 0,
    Created_By VARCHAR(50) NOT NULL,
    Created_Date DATETIME NOT NULL DEFAULT GETDATE(),
    Modified_By VARCHAR(50) NULL,
    Modified_Date DATETIME NULL,
    Deleted_By VARCHAR(50) NULL,
    Deleted_Date DATETIME NULL,

    CONSTRAINT UQ_Karyawan_Akun UNIQUE (ID_Akun),
    CONSTRAINT FK_Karyawan_Akun FOREIGN KEY (ID_Akun) REFERENCES Akun(ID_Akun),
    CONSTRAINT CK_Karyawan_JenisKelamin CHECK (Jenis_Kelamin IN (1, 2)),
    CONSTRAINT CK_Karyawan_Jabatan CHECK (Jabatan IN (1, 2, 3)),
    CONSTRAINT CK_Karyawan_Status CHECK (Status IN (0, 1))
);
GO

/* Tabel lapangan */
CREATE TABLE Lapangan (
    ID_Lapangan VARCHAR(6) NOT NULL PRIMARY KEY,
    Nama_Lapangan VARCHAR(25) NOT NULL,
    Harga_Sewa DECIMAL(18,2) NOT NULL,
    Status INT NOT NULL DEFAULT 1,
    Is_Deleted BIT NOT NULL DEFAULT 0,
    Created_By VARCHAR(50) NOT NULL,
    Created_Date DATETIME NOT NULL DEFAULT GETDATE(),
    Modified_By VARCHAR(50) NULL,
    Modified_Date DATETIME NULL,
    Deleted_By VARCHAR(50) NULL,
    Deleted_Date DATETIME NULL,

    CONSTRAINT CK_Lapangan_Harga CHECK (Harga_Sewa >= 0),
    CONSTRAINT CK_Lapangan_Status CHECK (Status IN (0, 1))
);
GO

/* Tabel jadwal */
CREATE TABLE Jadwal (
    ID_Jadwal VARCHAR(6) NOT NULL PRIMARY KEY,
    ID_Lapangan VARCHAR(6) NOT NULL,
    Tanggal DATE NOT NULL,
    Jam_Mulai TIME NOT NULL,
    Jam_Selesai TIME NOT NULL,
    Status INT NOT NULL DEFAULT 1,
    Is_Deleted BIT NOT NULL DEFAULT 0,
    Created_By VARCHAR(50) NOT NULL,
    Created_Date DATETIME NOT NULL DEFAULT GETDATE(),
    Modified_By VARCHAR(50) NULL,
    Modified_Date DATETIME NULL,
    Deleted_By VARCHAR(50) NULL,
    Deleted_Date DATETIME NULL,

    CONSTRAINT FK_Jadwal_Lapangan FOREIGN KEY (ID_Lapangan) REFERENCES Lapangan(ID_Lapangan),
    CONSTRAINT UQ_Jadwal_Lapangan_Waktu UNIQUE (ID_Lapangan, Tanggal, Jam_Mulai, Jam_Selesai),
    CONSTRAINT CK_Jadwal_Jam CHECK (Jam_Selesai > Jam_Mulai),
    CONSTRAINT CK_Jadwal_Status CHECK (Status IN (0, 1))
);
GO

/* Tabel promo */
CREATE TABLE Promo (
    ID_Promo VARCHAR(6) NOT NULL PRIMARY KEY,
    Nama_Promo VARCHAR(15) NOT NULL,
    Diskon DECIMAL(18,2) NOT NULL,
    Tanggal_Mulai DATE NOT NULL,
    Tanggal_Selesai DATE NOT NULL,
    Status INT NOT NULL DEFAULT 1,
    Is_Deleted BIT NOT NULL DEFAULT 0,
    Created_By VARCHAR(50) NOT NULL,
    Created_Date DATETIME NOT NULL DEFAULT GETDATE(),
    Modified_By VARCHAR(50) NULL,
    Modified_Date DATETIME NULL,
    Deleted_By VARCHAR(50) NULL,
    Deleted_Date DATETIME NULL,

    CONSTRAINT CK_Promo_Diskon CHECK (Diskon >= 0),
    CONSTRAINT CK_Promo_Tanggal CHECK (Tanggal_Selesai >= Tanggal_Mulai),
    CONSTRAINT CK_Promo_Status CHECK (Status IN (0, 1))
);
GO

/* Tabel tipe member */
CREATE TABLE Tipe_Member (
    ID_Tipe VARCHAR(6) NOT NULL PRIMARY KEY,
    Nama_Tipe VARCHAR(15) NOT NULL,
    Harga_Member DECIMAL(18,2) NOT NULL,
    Potongan_Harga DECIMAL(18,2) NOT NULL,
    Status INT NOT NULL DEFAULT 1,
    Is_Deleted BIT NOT NULL DEFAULT 0,
    Created_By VARCHAR(50) NOT NULL,
    Created_Date DATETIME NOT NULL DEFAULT GETDATE(),
    Modified_By VARCHAR(50) NULL,
    Modified_Date DATETIME NULL,
    Deleted_By VARCHAR(50) NULL,
    Deleted_Date DATETIME NULL,

    CONSTRAINT CK_TipeMember_Harga CHECK (Harga_Member >= 0),
    CONSTRAINT CK_TipeMember_Potongan CHECK (Potongan_Harga >= 0),
    CONSTRAINT CK_TipeMember_Status CHECK (Status IN (0, 1))
);
GO

/* Tabel alat */
CREATE TABLE Alat (
    ID_Alat VARCHAR(6) NOT NULL PRIMARY KEY,
    Nama_Alat VARCHAR(25) NOT NULL,
    Stok INT NOT NULL,
    Harga_Alat DECIMAL(18,2) NOT NULL,
    Status INT NOT NULL DEFAULT 1,
    Is_Deleted BIT NOT NULL DEFAULT 0,
    Created_By VARCHAR(50) NOT NULL,
    Created_Date DATETIME NOT NULL DEFAULT GETDATE(),
    Modified_By VARCHAR(50) NULL,
    Modified_Date DATETIME NULL,
    Deleted_By VARCHAR(50) NULL,
    Deleted_Date DATETIME NULL,

    CONSTRAINT CK_Alat_Stok CHECK (Stok >= 0),
    CONSTRAINT CK_Alat_Harga CHECK (Harga_Alat >= 0),
    CONSTRAINT CK_Alat_Status CHECK (Status IN (0, 1))
);
GO

/* Tabel booking */
CREATE TABLE Booking (
    ID_Booking VARCHAR(6) NOT NULL PRIMARY KEY,
    ID_Customer VARCHAR(6) NOT NULL,
    ID_Karyawan VARCHAR(6) NOT NULL,
    ID_Jadwal VARCHAR(6) NOT NULL,
    ID_Promo VARCHAR(6) NULL,
    Tanggal_Booking DATE NOT NULL,
    Metode_Pembayaran VARCHAR(20) NOT NULL,
    Total_Bayar DECIMAL(18,2) NOT NULL,
    Status INT NOT NULL DEFAULT 1,
    Created_By VARCHAR(50) NOT NULL,
    Created_Date DATETIME NOT NULL DEFAULT GETDATE(),
    Modified_By VARCHAR(50) NULL,
    Modified_Date DATETIME NULL,

    CONSTRAINT FK_Booking_Customer FOREIGN KEY (ID_Customer) REFERENCES Customer(ID_Customer),
    CONSTRAINT FK_Booking_Karyawan FOREIGN KEY (ID_Karyawan) REFERENCES Karyawan(ID_Karyawan),
    CONSTRAINT FK_Booking_Jadwal FOREIGN KEY (ID_Jadwal) REFERENCES Jadwal(ID_Jadwal),
    CONSTRAINT FK_Booking_Promo FOREIGN KEY (ID_Promo) REFERENCES Promo(ID_Promo),
    CONSTRAINT CK_Booking_Total CHECK (Total_Bayar >= 0),
    CONSTRAINT CK_Booking_Status CHECK (Status IN (1, 2, 3, 4))
);
GO

/* Tabel langganan */
CREATE TABLE Langganan (
    ID_Langganan VARCHAR(6) NOT NULL PRIMARY KEY,
    ID_Customer VARCHAR(6) NOT NULL,
    ID_Karyawan VARCHAR(6) NOT NULL,
    ID_Tipe VARCHAR(6) NOT NULL,
    Tanggal_Mulai DATE NOT NULL,
    Tanggal_Selesai DATE NOT NULL,
    Metode_Pembayaran VARCHAR(20) NOT NULL,
    Total_Bayar DECIMAL(18,2) NOT NULL,
    Status INT NOT NULL DEFAULT 1,
    Created_By VARCHAR(50) NOT NULL,
    Created_Date DATETIME NOT NULL DEFAULT GETDATE(),
    Modified_By VARCHAR(50) NULL,
    Modified_Date DATETIME NULL,

    CONSTRAINT FK_Langganan_Customer FOREIGN KEY (ID_Customer) REFERENCES Customer(ID_Customer),
    CONSTRAINT FK_Langganan_Karyawan FOREIGN KEY (ID_Karyawan) REFERENCES Karyawan(ID_Karyawan),
    CONSTRAINT FK_Langganan_Tipe FOREIGN KEY (ID_Tipe) REFERENCES Tipe_Member(ID_Tipe),
    CONSTRAINT CK_Langganan_Tanggal CHECK (Tanggal_Selesai >= Tanggal_Mulai),
    CONSTRAINT CK_Langganan_Total CHECK (Total_Bayar >= 0),
    CONSTRAINT CK_Langganan_Status CHECK (Status IN (1, 2, 3))
);
GO

/* Tabel beli alat */
CREATE TABLE Beli_Alat (
    ID_Beli VARCHAR(6) NOT NULL PRIMARY KEY,
    ID_Karyawan VARCHAR(6) NOT NULL,
    ID_Customer VARCHAR(6) NOT NULL,
    Tanggal_Beli DATE NOT NULL,
    Metode_Pembayaran VARCHAR(20) NOT NULL,
    Total_Bayar DECIMAL(18,2) NOT NULL,
    Status INT NOT NULL DEFAULT 1,
    Created_By VARCHAR(50) NOT NULL,
    Created_Date DATETIME NOT NULL DEFAULT GETDATE(),
    Modified_By VARCHAR(50) NULL,
    Modified_Date DATETIME NULL,

    CONSTRAINT FK_BeliAlat_Karyawan FOREIGN KEY (ID_Karyawan) REFERENCES Karyawan(ID_Karyawan),
    CONSTRAINT FK_BeliAlat_Customer FOREIGN KEY (ID_Customer) REFERENCES Customer(ID_Customer),
    CONSTRAINT CK_BeliAlat_Total CHECK (Total_Bayar >= 0),
    CONSTRAINT CK_BeliAlat_Status CHECK (Status IN (1, 2, 3))
);
GO

/* Tabel detail beli alat */
CREATE TABLE Detail_Beli_Alat (
    ID_Alat VARCHAR(6) NOT NULL,
    ID_Beli VARCHAR(6) NOT NULL,
    Jumlah INT NOT NULL,
    SubTotal DECIMAL(18,2) NOT NULL,

    CONSTRAINT PK_Detail_Beli_Alat PRIMARY KEY (ID_Alat, ID_Beli),
    CONSTRAINT FK_DetailBeliAlat_Alat FOREIGN KEY (ID_Alat) REFERENCES Alat(ID_Alat),
    CONSTRAINT FK_DetailBeliAlat_Beli FOREIGN KEY (ID_Beli) REFERENCES Beli_Alat(ID_Beli),
    CONSTRAINT CK_DetailBeliAlat_Jumlah CHECK (Jumlah > 0),
    CONSTRAINT CK_DetailBeliAlat_SubTotal CHECK (SubTotal >= 0)
);
GO

/* Tabel pembatalan booking */
CREATE TABLE Pembatalan_Booking (
    ID_Pembatalan VARCHAR(6) NOT NULL PRIMARY KEY,
    ID_Booking VARCHAR(6) NOT NULL,
    ID_Karyawan VARCHAR(6) NOT NULL,
    Tanggal_Batal DATE NOT NULL,
    Alasan VARCHAR(255) NOT NULL,
    Biaya_Batal DECIMAL(18,2) NOT NULL,
    Nominal_Refund DECIMAL(18,2) NOT NULL,
    Metode_Refund VARCHAR(20) NOT NULL,
    Status_Refund INT NOT NULL DEFAULT 1,
    Created_By VARCHAR(50) NOT NULL,
    Created_Date DATETIME NOT NULL DEFAULT GETDATE(),
    Modified_By VARCHAR(50) NULL,
    Modified_Date DATETIME NULL,

    CONSTRAINT UQ_Pembatalan_Booking UNIQUE (ID_Booking),
    CONSTRAINT FK_Pembatalan_Booking FOREIGN KEY (ID_Booking) REFERENCES Booking(ID_Booking),
    CONSTRAINT FK_Pembatalan_Karyawan FOREIGN KEY (ID_Karyawan) REFERENCES Karyawan(ID_Karyawan),
    CONSTRAINT CK_Pembatalan_Biaya CHECK (Biaya_Batal >= 0),
    CONSTRAINT CK_Pembatalan_Refund CHECK (Nominal_Refund >= 0),
    CONSTRAINT CK_Pembatalan_StatusRefund CHECK (Status_Refund IN (1, 2, 3))
);
GO

/* Mencegah jadwal aktif dibooking lebih dari satu kali */
CREATE UNIQUE INDEX UQ_Booking_Jadwal_Aktif
ON Booking(ID_Jadwal)
WHERE Status <> 4;
GO

INSERT INTO Akun 
(ID_Akun, Username, Email, Kata_Sandi, Role, Status, Created_By)
VALUES
('AK0001', 'manajer', 'manajer@hoopball.com', '12345', 1, 1, 'System'),
('AK0002', 'karyawan1', 'karyawan1@hoopball.com', '12345', 2, 1, 'System'),
('AK0003', 'karyawan2', 'karyawan2@hoopball.com', '12345', 2, 1, 'System'),
('AK0004', 'raka', 'raka@gmail.com', '12345', 3, 1, 'System'),
('AK0005', 'dimas', 'dimas@gmail.com', '12345', 3, 1, 'System'),
('AK0006', 'salsa', 'salsa@gmail.com', '12345', 3, 1, 'System'),
('AK0007', 'nabila', 'nabila@gmail.com', '12345', 3, 1, 'System'),
('AK0008', 'farhan', 'farhan@gmail.com', '12345', 3, 1, 'System'),
('AK0009', 'zaki', 'zaki@gmail.com', '12345', 3, 1, 'System'),
('AK0010', 'putri', 'putri@gmail.com', '12345', 3, 1, 'System');
GO

INSERT INTO Karyawan
(ID_Karyawan, ID_Akun, Nama_Karyawan, Tempat_Lahir, Tanggal_Lahir, Jenis_Kelamin, Jabatan, No_Telepon, Status, Created_By)
VALUES
('KR0001', 'AK0001', 'Budi', 'Jakarta', '1998-05-12', 1, 3, '081111111111', 1, 'System'),
('KR0002', 'AK0002', 'Andi', 'Bandung', '1997-08-21', 1, 1, '082222222222', 1, 'System'),
('KR0003', 'AK0003', 'Siti', 'Surabaya', '1999-03-15', 2, 2, '083333333333', 1, 'System');
GO

INSERT INTO Customer
(ID_Customer, ID_Akun, Nama_Customer, Tempat_Lahir, Tanggal_Lahir, Jenis_Kelamin, Alamat, No_Telepon, Status, Created_By)
VALUES
('CS0001', 'AK0004', 'Raka', 'Bekasi', '2000-01-10', 1, 'Bekasi', '081234567801', 1, 'System'),
('CS0002', 'AK0005', 'Dimas', 'Jakarta', '1998-07-22', 1, 'Jakarta', '081234567802', 1, 'System'),
('CS0003', 'AK0006', 'Salsa', 'Depok', '2001-04-18', 2, 'Depok', '081234567803', 1, 'System'),
('CS0004', 'AK0007', 'Nabila', 'Bogor', '1999-11-05', 2, 'Bogor', '081234567804', 1, 'System'),
('CS0005', 'AK0008', 'Farhan', 'Tangerang', '2000-09-14', 1, 'Tangerang', '081234567805', 1, 'System'),
('CS0006', 'AK0009', 'Zaki', 'Cikarang', '1997-12-30', 1, 'Cikarang', '081234567806', 1, 'System'),
('CS0007', 'AK0010', 'Putri', 'Karawang', '2002-06-25', 2, 'Karawang', '081234567807', 1, 'System');
GO

INSERT INTO Lapangan
(ID_Lapangan, Nama_Lapangan, Harga_Sewa, Status, Created_By)
VALUES
('LP0001', 'Lapangan A', 150000, 1, 'System'),
('LP0002', 'Lapangan B', 175000, 1, 'System'),
('LP0003', 'Lapangan C', 200000, 1, 'System'),
('LP0004', 'Lapangan D', 225000, 1, 'System');
GO

INSERT INTO Jadwal
(ID_Jadwal, ID_Lapangan, Tanggal, Jam_Mulai, Jam_Selesai, Status, Created_By)
VALUES
('JD0001', 'LP0001', '2026-06-15', '08:00', '10:00', 1, 'System'),
('JD0002', 'LP0001', '2026-06-15', '10:00', '12:00', 1, 'System'),
('JD0003', 'LP0001', '2026-06-15', '13:00', '15:00', 1, 'System'),
('JD0004', 'LP0001', '2026-06-15', '15:00', '17:00', 1, 'System'),
('JD0005', 'LP0002', '2026-06-15', '08:00', '10:00', 1, 'System'),
('JD0006', 'LP0002', '2026-06-15', '10:00', '12:00', 1, 'System'),
('JD0007', 'LP0002', '2026-06-15', '13:00', '15:00', 1, 'System'),
('JD0008', 'LP0002', '2026-06-15', '15:00', '17:00', 1, 'System'),
('JD0009', 'LP0003', '2026-06-16', '08:00', '10:00', 1, 'System'),
('JD0010', 'LP0003', '2026-06-16', '10:00', '12:00', 1, 'System'),
('JD0011', 'LP0003', '2026-06-16', '13:00', '15:00', 1, 'System'),
('JD0012', 'LP0003', '2026-06-16', '15:00', '17:00', 1, 'System'),
('JD0013', 'LP0004', '2026-06-16', '08:00', '10:00', 1, 'System'),
('JD0014', 'LP0004', '2026-06-16', '10:00', '12:00', 1, 'System'),
('JD0015', 'LP0004', '2026-06-16', '13:00', '15:00', 1, 'System'),
('JD0016', 'LP0004', '2026-06-16', '15:00', '17:00', 1, 'System'),
('JD0017', 'LP0001', '2026-06-17', '08:00', '10:00', 1, 'System'),
('JD0018', 'LP0002', '2026-06-17', '10:00', '12:00', 1, 'System'),
('JD0019', 'LP0003', '2026-06-17', '13:00', '15:00', 1, 'System'),
('JD0020', 'LP0004', '2026-06-17', '15:00', '17:00', 1, 'System');
GO

INSERT INTO Promo
(ID_Promo, Nama_Promo, Diskon, Tanggal_Mulai, Tanggal_Selesai, Status, Created_By)
VALUES
('PR0001', 'PROMO10', 10000, '2026-06-01', '2026-06-30', 1, 'System'),
('PR0002', 'HEMAT20', 20000, '2026-06-01', '2026-06-30', 1, 'System'),
('PR0003', 'WEEKEND', 15000, '2026-06-01', '2026-07-15', 1, 'System'),
('PR0004', 'HOOPDAY', 25000, '2026-06-10', '2026-07-10', 1, 'System');
GO

INSERT INTO Tipe_Member
(ID_Tipe, Nama_Tipe, Harga_Member, Potongan_Harga, Status, Created_By)
VALUES
('TM0001', 'Silver', 100000, 10000, 1, 'System'),
('TM0002', 'Gold', 150000, 20000, 1, 'System'),
('TM0003', 'Platinum', 200000, 30000, 1, 'System');
GO

INSERT INTO Alat
(ID_Alat, Nama_Alat, Stok, Harga_Alat, Status, Created_By)
VALUES
('AT0001', 'Bola Basket', 20, 250000, 1, 'System'),
('AT0002', 'Knee Pad', 15, 75000, 1, 'System'),
('AT0003', 'Arm Sleeve', 25, 50000, 1, 'System'),
('AT0004', 'Jersey Basket', 30, 120000, 1, 'System'),
('AT0005', 'Sepatu Basket', 10, 450000, 1, 'System'),
('AT0006', 'Tas Olahraga', 12, 180000, 1, 'System');
GO

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


SELECT * FROM Akun;
SELECT * FROM Customer;
SELECT * FROM Karyawan;
SELECT * FROM Lapangan;
SELECT * FROM Jadwal;
SELECT * FROM Promo;
SELECT * FROM Tipe_Member;
SELECT * FROM Alat;
SELECT * FROM Booking;
SELECT * FROM Langganan;
SELECT * FROM Beli_Alat;
SELECT * FROM Detail_Beli_Alat;
SELECT * FROM Pembatalan_Booking;

drop table Akun
drop table Karyawan
drop table Customer

drop table Fasilitas_Lapangan
drop database Hoopbal
drop database Hoopball
