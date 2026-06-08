CREATE DATABASE Hoopball;
USE Hoopball;

-- 1. Tabel Akun (Dibuat pertama karena menjadi referensi/FK)
CREATE TABLE Akun (
    ID_Akun VARCHAR(6) PRIMARY KEY,
    Username VARCHAR(20) NOT NULL,
    Email VARCHAR(50) NOT NULL,
    Kata_Sandi VARCHAR(50) NOT NULL,
    Role INT NOT NULL CHECK (Role IN (1, 2, 3)), -- 1:Admin, 2:Karyawan, 3:Customer
    Status_Akun INT NOT NULL CHECK (Status_Akun IN (0, 1)) -- 0:Nonaktif, 1:Aktif
);

-- 2. Tabel Karyawan (Memiliki FK ID_Akun)
CREATE TABLE Karyawan (
    ID_Karyawan VARCHAR(6) PRIMARY KEY,
    ID_Akun VARCHAR(6) NOT NULL,
    Nama_Karyawan VARCHAR(20) NOT NULL,
    Jenis_Kelamin INT NOT NULL CHECK (Jenis_Kelamin IN (1, 2)),
    Jabatan INT NOT NULL CHECK (Jabatan BETWEEN 1 AND 5),
    No_Telepon VARCHAR(15) NOT NULL,
    FOREIGN KEY (ID_Akun) REFERENCES Akun(ID_Akun)
);

-- 3. Tabel Customer (Memiliki FK ID_Akun)
CREATE TABLE Customer (
    ID_Customer VARCHAR(6) PRIMARY KEY,
    ID_Akun VARCHAR(6) NOT NULL,
    Nama_Customer VARCHAR(20) NOT NULL,
    Jenis_Kelamin INT NOT NULL CHECK (Jenis_Kelamin IN (1, 2)),
    Alamat VARCHAR(100) NOT NULL,
    No_Telepon VARCHAR(15) NOT NULL,
    FOREIGN KEY (ID_Akun) REFERENCES Akun(ID_Akun)
);

-- 4. Tabel Lapangan
CREATE TABLE Lapangan (
    ID_Lapangan VARCHAR(6) PRIMARY KEY,
    Nama_Lapangan VARCHAR(25) NOT NULL,
    Harga_Sewa DECIMAL(18,2) NOT NULL,
    Status INT NOT NULL CHECK (Status IN (0, 1)) -- 0:Maintenance, 1:Tersedia
);

-- 5. Tabel Promo
CREATE TABLE Promo (
    ID_Promo VARCHAR(6) PRIMARY KEY,
    Nama_Promo VARCHAR(15) NOT NULL,
    Diskon DECIMAL(18,2) NOT NULL,
    Tanggal_Mulai DATE NOT NULL,
    Tanggal_Selesai DATE NOT NULL
);

-- 20 Akun untuk Karyawan (ID AKN001-AKN020)
INSERT INTO Akun VALUES 
('AKN001','admin_hoop','admin@hoop.com','pass123',1,1),
('AKN002','staff_budi','budi@hoop.com','pass123',2,1),
('AKN003','staff_siti','siti@hoop.com','pass123',2,1),
('AKN004','staff_eko','eko@hoop.com','pass123',2,1),
('AKN005','staff_ani','ani@hoop.com','pass123',2,1),
('AKN006','staff_doni','doni@hoop.com','pass123',2,1),
('AKN007','staff_rara','rara@hoop.com','pass123',2,1),
('AKN008','staff_gani','gani@hoop.com','pass123',2,1),
('AKN009','staff_heri','heri@hoop.com','pass123',2,1),
('AKN010','staff_ina','ina@hoop.com','pass123',2,1),
('AKN011','user_ari','ari@mail.com','user123',3,1),
('AKN012','user_ben','ben@mail.com','user123',3,1),
('AKN013','user_citra','citra@mail.com','user123',3,1),
('AKN014','user_desi','desi@mail.com','user123',3,1),
('AKN015','user_erik','erik@mail.com','user123',3,1),
('AKN016','user_fani','fani@mail.com','user123',3,1),
('AKN017','user_gina','gina@mail.com','user123',3,1),
('AKN018','user_hans','hans@mail.com','user123',3,1),
('AKN019','user_ivan','ivan@mail.com','user123',3,1),
('AKN020','user_jenny','jenny@mail.com','user123',3,1);

INSERT INTO Karyawan VALUES
('KRY001','AKN001','Andi Admin',1,1,'0811'), ('KRY002','AKN002','Budi Kasir',1,3,'0812'),
('KRY003','AKN003','Siti Kasir',2,3,'0813'), ('KRY004','AKN004','Eko Staf',1,4,'0814'),
('KRY005','AKN005','Ani Kasir',2,3,'0815'), ('KRY006','AKN006','Doni Staf',1,4,'0816'),
('KRY007','AKN007','Rara Kasir',2,3,'0817'), ('KRY008','AKN008','Gani Staf',1,4,'0818'),
('KRY009','AKN009','Heri Kasir',1,3,'0819'), ('KRY010','AKN010','Ina Staf',2,4,'0820'),
('KRY011','AKN001','Hendra',1,2,'0821'), ('KRY012','AKN002','Maman',1,3,'0822'),
('KRY013','AKN003','Lili',2,3,'0823'), ('KRY014','AKN004','Joko',1,4,'0824'),
('KRY015','AKN005','Nana',2,3,'0825'), ('KRY016','AKN006','Opan',1,4,'0826'),
('KRY017','AKN007','Pipo',1,3,'0827'), ('KRY018','AKN008','Qori',2,4,'0828'),
('KRY019','AKN009','Reza',1,3,'0829'), ('KRY020','AKN010','Sasa',2,4,'0830');

INSERT INTO Customer VALUES
('CUS001','AKN011','Ari','1','Jakarta','0851'), ('CUS002','AKN012','Ben','1','Bandung','0852'),
('CUS003','AKN013','Citra','2','Bekasi','0853'), ('CUS004','AKN014','Desi','2','Depok','0854'),
('CUS005','AKN015','Erik','1','Bogor','0855'), ('CUS006','AKN016','Fani','2','Tangerang','0856'),
('CUS007','AKN017','Gina','2','Jakarta','0857'), ('CUS008','AKN018','Hans','1','Surabaya','0858'),
('CUS009','AKN019','Ivan','1','Malang','0859'), ('CUS010','AKN020','Jenny','2','Medan','0860'),
('CUS011','AKN011','Rian','1','Solo','0861'), ('CUS012','AKN012','Lala','2','Jogja','0862'),
('CUS013','AKN013','Tomi','1','Aceh','0863'), ('CUS014','AKN014','Yuna','2','Bali','0864'),
('CUS015','AKN015','Zaki','1','Palu','0865'), ('CUS016','AKN016','Vina','2','Padang','0866'),
('CUS017','AKN017','Baim','1','Riau','0867'), ('CUS018','AKN018','Kiki','2','Jambi','0868'),
('CUS019','AKN019','Duta','1','Papua','0869'), ('CUS020','AKN020','Emi','2','Batam','0870');

INSERT INTO Lapangan (ID_Lapangan, Nama_Lapangan, Harga_Sewa, Status) VALUES
('LAP001', 'Futsal Vinyl A1', 150000.00, 1),
('LAP002', 'Futsal Vinyl A2', 150000.00, 1),
('LAP003', 'Futsal Rumput B1', 120000.00, 1),
('LAP004', 'Futsal Rumput B2', 120000.00, 0), -- Maintenance
('LAP005', 'Basket Indoor Pro', 250000.00, 1),
('LAP006', 'Basket Outdoor 1', 100000.00, 1),
('LAP007', 'Basket Outdoor 2', 100000.00, 1),
('LAP008', 'Mini Soccer A', 400000.00, 1),
('LAP009', 'Mini Soccer B', 400000.00, 1),
('LAP010', 'Voli Court Indoor', 130000.00, 1),
('LAP011', 'Badminton Court 1', 50000.00, 1),
('LAP012', 'Badminton Court 2', 50000.00, 1),
('LAP013', 'Badminton Court 3', 50000.00, 1),
('LAP014', 'Badminton Court 4', 50000.00, 1),
('LAP015', 'Badminton Court 5', 50000.00, 0), -- Maintenance
('LAP016', 'Tenis Court 1', 180000.00, 1),
('LAP017', 'Tenis Court 2', 180000.00, 1),
('LAP018', 'Multipurpose VIP', 500000.00, 1),
('LAP019', 'Futsal Interlock C1', 175000.00, 1),
('LAP020', 'Futsal Interlock C2', 175000.00, 1);

INSERT INTO Promo (ID_Promo, Nama_Promo, Diskon, Tanggal_Mulai, Tanggal_Selesai) VALUES
('PRM001', 'Tahun Baru', 20000.00, '2024-01-01', '2024-01-07'),
('PRM002', 'Imlek Hoki', 15000.00, '2024-02-01', '2024-02-15'),
('PRM003', 'Valentine', 10000.00, '2024-02-14', '2024-02-14'),
('PRM004', 'Ramadhan', 25000.00, '2024-03-10', '2024-04-10'),
('PRM005', 'Idul Fitri', 30000.00, '2024-04-11', '2024-04-20'),
('PRM006', 'Mei Hemat', 12000.00, '2024-05-01', '2024-05-31'),
('PRM007', 'Libur Sekolah', 20000.00, '2024-06-15', '2024-07-15'),
('PRM008', 'Promo Merdeka', 17000.00, '2024-08-10', '2024-08-20'),
('PRM009', 'SeptemBER', 10000.00, '2024-09-01', '2024-09-30'),
('PRM010', 'HUT Hoopball', 50000.00, '2024-10-01', '2024-10-07'),
('PRM011', 'Sumpah Pemuda', 15000.00, '2024-10-25', '2024-10-31'),
('PRM012', 'Pahlawan', 10000.00, '2024-11-10', '2024-11-10'),
('PRM013', 'Promo 11.11', 11000.00, '2024-11-11', '2024-11-11'),
('PRM014', 'Gajian Seru', 15000.00, '2024-11-25', '2024-11-30'),
('PRM015', 'Promo 12.12', 12000.00, '2024-12-12', '2024-12-12'),
('PRM016', 'Natal Ceria', 25000.00, '2024-12-20', '2024-12-26'),
('PRM017', 'Akhir Tahun', 35000.00, '2024-12-27', '2024-12-31'),
('PRM018', 'Member Baru', 5000.00, '2024-01-01', '2024-12-31'),
('PRM019', 'Flash Sale', 8000.00, '2024-05-20', '2024-05-20'),
('PRM020', 'Senin Sehat', 7000.00, '2024-01-01', '2024-01-01');

select * from Akun
select * from Karyawan

select * from Lapangan