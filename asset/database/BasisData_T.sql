-- ============================================================
-- TABEL LOG HISTORY (untuk trigger audit trail)
-- ============================================================
CREATE TABLE Log_History (
    ID_Log          INT IDENTITY(1,1) PRIMARY KEY,
    Nama_Tabel      VARCHAR(50)     NOT NULL,
    ID_Record       INT             NOT NULL,
    Aksi            VARCHAR(10)     NOT NULL,  -- INSERT, UPDATE, DELETE
    Data_Lama       NVARCHAR(MAX)   NULL,
    Data_Baru       NVARCHAR(MAX)   NULL,
    User_Aksi       VARCHAR(50)     NOT NULL,
    Waktu_Aksi      DATETIME        NOT NULL DEFAULT GETDATE()
);
GO


-- ============================================================
-- TRIGGER 1: Log History untuk Customer
-- ============================================================
CREATE TRIGGER trg_Customer_LogHistory
ON Customer
AFTER INSERT, UPDATE, DELETE
AS
BEGIN
    SET NOCOUNT ON;
    
    DECLARE @UserAksi VARCHAR(50) = SUSER_SNAME();
    
    -- INSERT
    IF EXISTS (SELECT 1 FROM inserted) AND NOT EXISTS (SELECT 1 FROM deleted)
    BEGIN
        INSERT INTO Log_History (Nama_Tabel, ID_Record, Aksi, Data_Baru, User_Aksi)
        SELECT 
            'Customer',
            i.ID_Customer,
            'INSERT',
            (SELECT i.* FOR JSON PATH, WITHOUT_ARRAY_WRAPPER),
            @UserAksi
        FROM inserted i;
    END
    
    -- UPDATE
    IF EXISTS (SELECT 1 FROM inserted) AND EXISTS (SELECT 1 FROM deleted)
    BEGIN
        INSERT INTO Log_History (Nama_Tabel, ID_Record, Aksi, Data_Lama, Data_Baru, User_Aksi)
        SELECT 
            'Customer',
            i.ID_Customer,
            'UPDATE',
            (SELECT d.* FOR JSON PATH, WITHOUT_ARRAY_WRAPPER),
            (SELECT i.* FOR JSON PATH, WITHOUT_ARRAY_WRAPPER),
            @UserAksi
        FROM inserted i
        INNER JOIN deleted d ON i.ID_Customer = d.ID_Customer;
    END
    
    -- DELETE
    IF EXISTS (SELECT 1 FROM deleted) AND NOT EXISTS (SELECT 1 FROM inserted)
    BEGIN
        INSERT INTO Log_History (Nama_Tabel, ID_Record, Aksi, Data_Lama, User_Aksi)
        SELECT 
            'Customer',
            d.ID_Customer,
            'DELETE',
            (SELECT d.* FOR JSON PATH, WITHOUT_ARRAY_WRAPPER),
            @UserAksi
        FROM deleted d;
    END
END;
GO

-- ============================================================
-- TRIGGER 2: Log History untuk Karyawan
-- ============================================================
CREATE TRIGGER trg_Karyawan_LogHistory
ON Karyawan
AFTER INSERT, UPDATE, DELETE
AS
BEGIN
    SET NOCOUNT ON;
    
    DECLARE @UserAksi VARCHAR(50) = SUSER_SNAME();
    
    IF EXISTS (SELECT 1 FROM inserted) AND NOT EXISTS (SELECT 1 FROM deleted)
    BEGIN
        INSERT INTO Log_History (Nama_Tabel, ID_Record, Aksi, Data_Baru, User_Aksi)
        SELECT 'Karyawan', i.ID_Karyawan, 'INSERT', 
               (SELECT i.* FOR JSON PATH, WITHOUT_ARRAY_WRAPPER), @UserAksi
        FROM inserted i;
    END
    
    IF EXISTS (SELECT 1 FROM inserted) AND EXISTS (SELECT 1 FROM deleted)
    BEGIN
        INSERT INTO Log_History (Nama_Tabel, ID_Record, Aksi, Data_Lama, Data_Baru, User_Aksi)
        SELECT 'Karyawan', i.ID_Karyawan, 'UPDATE',
               (SELECT d.* FOR JSON PATH, WITHOUT_ARRAY_WRAPPER),
               (SELECT i.* FOR JSON PATH, WITHOUT_ARRAY_WRAPPER), @UserAksi
        FROM inserted i INNER JOIN deleted d ON i.ID_Karyawan = d.ID_Karyawan;
    END
    
    IF EXISTS (SELECT 1 FROM deleted) AND NOT EXISTS (SELECT 1 FROM inserted)
    BEGIN
        INSERT INTO Log_History (Nama_Tabel, ID_Record, Aksi, Data_Lama, User_Aksi)
        SELECT 'Karyawan', d.ID_Karyawan, 'DELETE',
               (SELECT d.* FOR JSON PATH, WITHOUT_ARRAY_WRAPPER), @UserAksi
        FROM deleted d;
    END
END;
GO

-- ============================================================
-- TRIGGER 3: Log History untuk Lapangan
-- ============================================================
CREATE TRIGGER trg_Lapangan_LogHistory
ON Lapangan
AFTER INSERT, UPDATE, DELETE
AS
BEGIN
    SET NOCOUNT ON;
    DECLARE @UserAksi VARCHAR(50) = SUSER_SNAME();
    
    IF EXISTS (SELECT 1 FROM inserted) AND NOT EXISTS (SELECT 1 FROM deleted)
    BEGIN
        INSERT INTO Log_History (Nama_Tabel, ID_Record, Aksi, Data_Baru, User_Aksi)
        SELECT 'Lapangan', i.ID_Lapangan, 'INSERT',
               (SELECT i.* FOR JSON PATH, WITHOUT_ARRAY_WRAPPER), @UserAksi
        FROM inserted i;
    END
    
    IF EXISTS (SELECT 1 FROM inserted) AND EXISTS (SELECT 1 FROM deleted)
    BEGIN
        INSERT INTO Log_History (Nama_Tabel, ID_Record, Aksi, Data_Lama, Data_Baru, User_Aksi)
        SELECT 'Lapangan', i.ID_Lapangan, 'UPDATE',
               (SELECT d.* FOR JSON PATH, WITHOUT_ARRAY_WRAPPER),
               (SELECT i.* FOR JSON PATH, WITHOUT_ARRAY_WRAPPER), @UserAksi
        FROM inserted i INNER JOIN deleted d ON i.ID_Lapangan = d.ID_Lapangan;
    END
    
    IF EXISTS (SELECT 1 FROM deleted) AND NOT EXISTS (SELECT 1 FROM inserted)
    BEGIN
        INSERT INTO Log_History (Nama_Tabel, ID_Record, Aksi, Data_Lama, User_Aksi)
        SELECT 'Lapangan', d.ID_Lapangan, 'DELETE',
               (SELECT d.* FOR JSON PATH, WITHOUT_ARRAY_WRAPPER), @UserAksi
        FROM deleted d;
    END
END;
GO

-- ============================================================
-- TRIGGER 4: Log History untuk Alat
-- ============================================================
CREATE TRIGGER trg_Alat_LogHistory
ON Alat
AFTER INSERT, UPDATE, DELETE
AS
BEGIN
    SET NOCOUNT ON;
    DECLARE @UserAksi VARCHAR(50) = SUSER_SNAME();
    
    IF EXISTS (SELECT 1 FROM inserted) AND NOT EXISTS (SELECT 1 FROM deleted)
    BEGIN
        INSERT INTO Log_History (Nama_Tabel, ID_Record, Aksi, Data_Baru, User_Aksi)
        SELECT 'Alat', i.ID_Alat, 'INSERT',
               (SELECT i.* FOR JSON PATH, WITHOUT_ARRAY_WRAPPER), @UserAksi
        FROM inserted i;
    END
    
    IF EXISTS (SELECT 1 FROM inserted) AND EXISTS (SELECT 1 FROM deleted)
    BEGIN
        INSERT INTO Log_History (Nama_Tabel, ID_Record, Aksi, Data_Lama, Data_Baru, User_Aksi)
        SELECT 'Alat', i.ID_Alat, 'UPDATE',
               (SELECT d.* FOR JSON PATH, WITHOUT_ARRAY_WRAPPER),
               (SELECT i.* FOR JSON PATH, WITHOUT_ARRAY_WRAPPER), @UserAksi
        FROM inserted i INNER JOIN deleted d ON i.ID_Alat = d.ID_Alat;
    END
    
    IF EXISTS (SELECT 1 FROM deleted) AND NOT EXISTS (SELECT 1 FROM inserted)
    BEGIN
        INSERT INTO Log_History (Nama_Tabel, ID_Record, Aksi, Data_Lama, User_Aksi)
        SELECT 'Alat', d.ID_Alat, 'DELETE',
               (SELECT d.* FOR JSON PATH, WITHOUT_ARRAY_WRAPPER), @UserAksi
        FROM deleted d;
    END
END;
GO



-- ============================================================
-- TRIGGER 5: Log History untuk Booking
-- ============================================================
CREATE TRIGGER trg_Booking_LogHistory
ON Booking
AFTER INSERT, UPDATE, DELETE
AS
BEGIN
    SET NOCOUNT ON;
    DECLARE @UserAksi VARCHAR(50) = SUSER_SNAME();
    
    IF EXISTS (SELECT 1 FROM inserted) AND NOT EXISTS (SELECT 1 FROM deleted)
    BEGIN
        INSERT INTO Log_History (Nama_Tabel, ID_Record, Aksi, Data_Baru, User_Aksi)
        SELECT 'Booking', i.ID_Booking, 'INSERT',
               (SELECT i.* FOR JSON PATH, WITHOUT_ARRAY_WRAPPER), @UserAksi
        FROM inserted i;
    END
    
    IF EXISTS (SELECT 1 FROM inserted) AND EXISTS (SELECT 1 FROM deleted)
    BEGIN
        INSERT INTO Log_History (Nama_Tabel, ID_Record, Aksi, Data_Lama, Data_Baru, User_Aksi)
        SELECT 'Booking', i.ID_Booking, 'UPDATE',
               (SELECT d.* FOR JSON PATH, WITHOUT_ARRAY_WRAPPER),
               (SELECT i.* FOR JSON PATH, WITHOUT_ARRAY_WRAPPER), @UserAksi
        FROM inserted i INNER JOIN deleted d ON i.ID_Booking = d.ID_Booking;
    END
    
    IF EXISTS (SELECT 1 FROM deleted) AND NOT EXISTS (SELECT 1 FROM inserted)
    BEGIN
        INSERT INTO Log_History (Nama_Tabel, ID_Record, Aksi, Data_Lama, User_Aksi)
        SELECT 'Booking', d.ID_Booking, 'DELETE',
               (SELECT d.* FOR JSON PATH, WITHOUT_ARRAY_WRAPPER), @UserAksi
        FROM deleted d;
    END
END;
GO

-- ============================================================
-- TRIGGER 6: Log History untuk Langganan
-- ============================================================
CREATE TRIGGER trg_Langganan_LogHistory
ON Langganan
AFTER INSERT, UPDATE, DELETE
AS
BEGIN
    SET NOCOUNT ON;
    DECLARE @UserAksi VARCHAR(50) = SUSER_SNAME();
    
    IF EXISTS (SELECT 1 FROM inserted) AND NOT EXISTS (SELECT 1 FROM deleted)
    BEGIN
        INSERT INTO Log_History (Nama_Tabel, ID_Record, Aksi, Data_Baru, User_Aksi)
        SELECT 'Langganan', i.ID_Langganan, 'INSERT',
               (SELECT i.* FOR JSON PATH, WITHOUT_ARRAY_WRAPPER), @UserAksi
        FROM inserted i;
    END
    
    IF EXISTS (SELECT 1 FROM inserted) AND EXISTS (SELECT 1 FROM deleted)
    BEGIN
        INSERT INTO Log_History (Nama_Tabel, ID_Record, Aksi, Data_Lama, Data_Baru, User_Aksi)
        SELECT 'Langganan', i.ID_Langganan, 'UPDATE',
               (SELECT d.* FOR JSON PATH, WITHOUT_ARRAY_WRAPPER),
               (SELECT i.* FOR JSON PATH, WITHOUT_ARRAY_WRAPPER), @UserAksi
        FROM inserted i INNER JOIN deleted d ON i.ID_Langganan = d.ID_Langganan;
    END
    
    IF EXISTS (SELECT 1 FROM deleted) AND NOT EXISTS (SELECT 1 FROM inserted)
    BEGIN
        INSERT INTO Log_History (Nama_Tabel, ID_Record, Aksi, Data_Lama, User_Aksi)
        SELECT 'Langganan', d.ID_Langganan, 'DELETE',
               (SELECT d.* FOR JSON PATH, WITHOUT_ARRAY_WRAPPER), @UserAksi
        FROM deleted d;
    END
END;
GO

-- ============================================================
-- TRIGGER 7: Log History untuk Beli_Alat
-- ============================================================
CREATE TRIGGER trg_Beli_Alat_LogHistory
ON Beli_Alat
AFTER INSERT, UPDATE, DELETE
AS
BEGIN
    SET NOCOUNT ON;
    DECLARE @UserAksi VARCHAR(50) = SUSER_SNAME();
    
    IF EXISTS (SELECT 1 FROM inserted) AND NOT EXISTS (SELECT 1 FROM deleted)
    BEGIN
        INSERT INTO Log_History (Nama_Tabel, ID_Record, Aksi, Data_Baru, User_Aksi)
        SELECT 'Beli_Alat', i.ID_Beli, 'INSERT',
               (SELECT i.* FOR JSON PATH, WITHOUT_ARRAY_WRAPPER), @UserAksi
        FROM inserted i;
    END
    
    IF EXISTS (SELECT 1 FROM inserted) AND EXISTS (SELECT 1 FROM deleted)
    BEGIN
        INSERT INTO Log_History (Nama_Tabel, ID_Record, Aksi, Data_Lama, Data_Baru, User_Aksi)
        SELECT 'Beli_Alat', i.ID_Beli, 'UPDATE',
               (SELECT d.* FOR JSON PATH, WITHOUT_ARRAY_WRAPPER),
               (SELECT i.* FOR JSON PATH, WITHOUT_ARRAY_WRAPPER), @UserAksi
        FROM inserted i INNER JOIN deleted d ON i.ID_Beli = d.ID_Beli;
    END
    
    IF EXISTS (SELECT 1 FROM deleted) AND NOT EXISTS (SELECT 1 FROM inserted)
    BEGIN
        INSERT INTO Log_History (Nama_Tabel, ID_Record, Aksi, Data_Lama, User_Aksi)
        SELECT 'Beli_Alat', d.ID_Beli, 'DELETE',
               (SELECT d.* FOR JSON PATH, WITHOUT_ARRAY_WRAPPER), @UserAksi
        FROM deleted d;
    END
END;
GO


-- ============================================================
-- TRIGGER 8: Auto Update Stok Alat saat Pembelian (Insert Detail_Beli_Alat)
-- ============================================================
CREATE TRIGGER trg_DetailBeli_AutoUpdateStok
ON Detail_Beli_Alat
AFTER INSERT
AS
BEGIN
    SET NOCOUNT ON;
    
    -- Kurangi stok alat berdasarkan jumlah yang dibeli
    UPDATE Alat
    SET Stok = Alat.Stok - i.Jumlah
    FROM Alat
    INNER JOIN inserted i ON Alat.ID_Alat = i.ID_Alat;
    
    -- Log perubahan stok
    INSERT INTO Log_History (Nama_Tabel, ID_Record, Aksi, Data_Baru, User_Aksi)
    SELECT 
        'Alat_Stok_Update',
        i.ID_Alat,
        'UPDATE',
        'Stok berkurang ' + CAST(i.Jumlah AS VARCHAR) + ' unit karena pembelian ID: ' + CAST(i.ID_Beli AS VARCHAR),
        SUSER_SNAME()
    FROM inserted i;
END;
GO

-- ============================================================
-- TRIGGER 9: Auto Kembalikan Stok saat Pembatalan/Penghapusan Detail
-- ============================================================
CREATE TRIGGER trg_DetailBeli_AutoKembalikanStok
ON Detail_Beli_Alat
AFTER DELETE
AS
BEGIN
    SET NOCOUNT ON;
    
    -- Kembalikan stok alat jika detail dihapus
    UPDATE Alat
    SET Stok = Alat.Stok + d.Jumlah
    FROM Alat
    INNER JOIN deleted d ON Alat.ID_Alat = d.ID_Alat;
    
    -- Log perubahan stok
    INSERT INTO Log_History (Nama_Tabel, ID_Record, Aksi, Data_Baru, User_Aksi)
    SELECT 
        'Alat_Stok_Kembali',
        d.ID_Alat,
        'UPDATE',
        'Stok bertambah ' + CAST(d.Jumlah AS VARCHAR) + ' unit karena pembatalan pembelian ID: ' + CAST(d.ID_Beli AS VARCHAR),
        SUSER_SNAME()
    FROM deleted d;
END;
GO

-- ============================================================
-- TRIGGER 10: Auto Update Total Bayar Beli_Alat saat Insert/Update/Delete Detail
-- ============================================================
CREATE TRIGGER trg_DetailBeli_AutoUpdateTotal
ON Detail_Beli_Alat
AFTER INSERT, UPDATE, DELETE
AS
BEGIN
    SET NOCOUNT ON;
    
    DECLARE @ID_Beli INT;
    
    -- Ambil ID_Beli yang terpengaruh
    SELECT @ID_Beli = ID_Beli FROM inserted;
    IF @ID_Beli IS NULL SELECT @ID_Beli = ID_Beli FROM deleted;
    
    -- Update Total_Bayar di tabel Beli_Alat
    UPDATE Beli_Alat
    SET Total_Bayar = (
        SELECT ISNULL(SUM(SubTotal), 0) 
        FROM Detail_Beli_Alat 
        WHERE ID_Beli = @ID_Beli
    )
    WHERE ID_Beli = @ID_Beli;
    
    -- Log
    INSERT INTO Log_History (Nama_Tabel, ID_Record, Aksi, Data_Baru, User_Aksi)
    VALUES ('Beli_Alat', @ID_Beli, 'UPDATE', 'Total bayar diupdate otomatis', SUSER_SNAME());
END;
GO

-- ============================================================
-- TRIGGER 11: Auto Update Status Jadwal saat Booking Berhasil
-- ============================================================
CREATE TRIGGER trg_Booking_AutoUpdateJadwal
ON Booking
AFTER UPDATE
AS
BEGIN
    SET NOCOUNT ON;
    
    -- Jika status booking berubah menjadi Berhasil (1), update jadwal jadi tidak tersedia
    IF UPDATE(Status)
    BEGIN
        UPDATE Jadwal
        SET Status = 0  -- Tidak Tersedia
        FROM Jadwal
        INNER JOIN inserted i ON Jadwal.ID_Jadwal = i.ID_Jadwal
        WHERE i.Status = 1;
        
        -- Jika status booking berubah menjadi Dibatalkan (3), kembalikan jadwal jadi tersedia
        UPDATE Jadwal
        SET Status = 1  -- Tersedia
        FROM Jadwal
        INNER JOIN inserted i ON Jadwal.ID_Jadwal = i.ID_Jadwal
        WHERE i.Status = 3;
        
        -- Log
        INSERT INTO Log_History (Nama_Tabel, ID_Record, Aksi, Data_Baru, User_Aksi)
        SELECT 
            'Jadwal_Status',
            i.ID_Jadwal,
            'UPDATE',
            CASE 
                WHEN i.Status = 1 THEN 'Jadwal tidak tersedia karena booking berhasil'
                WHEN i.Status = 3 THEN 'Jadwal tersedia kembali karena booking dibatalkan'
                ELSE 'Status jadwal diupdate'
            END,
            SUSER_SNAME()
        FROM inserted i;
    END
END;
GO

-- ============================================================
-- TRIGGER 12: Auto Insert Pembatalan saat Booking Dibatalkan
-- ============================================================
CREATE TRIGGER trg_Booking_AutoInsertPembatalan
ON Booking
AFTER UPDATE
AS
BEGIN
    SET NOCOUNT ON;
    
    IF UPDATE(Status)
    BEGIN
        INSERT INTO Pembatalan_Booking 
        (ID_Booking, ID_Karyawan, Tanggal_Batal, Alasan, Biaya_Batal, Nominal_Refund, Metode_Refund, Status, Created_By, Created_Date)
        SELECT 
            i.ID_Booking,
            i.ID_Karyawan,  -- Petugas yang mengkonfirmasi
            GETDATE(),
            'Booking dibatalkan otomatis oleh sistem',
            i.Total_Bayar * 0.5,  -- Biaya batal 50%
            i.Total_Bayar * 0.5,  -- Refund 50%
            i.Metode_Pembayaran,
            1,  -- Status aktif
            SUSER_SNAME(),
            GETDATE()
        FROM inserted i
        WHERE i.Status = 3  -- Dibatalkan
          AND NOT EXISTS (
              SELECT 1 FROM Pembatalan_Booking pb 
              WHERE pb.ID_Booking = i.ID_Booking
          );
    END
END;
GO

-- ============================================================
-- TRIGGER 13: Validasi Stok Sebelum Insert Detail_Beli_Alat
-- ============================================================
CREATE TRIGGER trg_DetailBeli_ValidasiStok
ON Detail_Beli_Alat
INSTEAD OF INSERT
AS
BEGIN
    SET NOCOUNT ON;
    
    DECLARE @ID_Alat INT;
    DECLARE @Jumlah INT;
    DECLARE @StokTersedia INT;
    
    SELECT @ID_Alat = ID_Alat, @Jumlah = Jumlah FROM inserted;
    
    SELECT @StokTersedia = Stok FROM Alat WHERE ID_Alat = @ID_Alat;
    
    IF @StokTersedia < @Jumlah
    BEGIN
        RAISERROR('Stok tidak mencukupi! Stok tersedia: %d, Jumlah diminta: %d', 16, 1, @StokTersedia, @Jumlah);
        RETURN;
    END
    
    -- Jika stok cukup, lanjutkan insert
    INSERT INTO Detail_Beli_Alat (ID_Alat, ID_Beli, Jumlah, SubTotal)
    SELECT ID_Alat, ID_Beli, Jumlah, SubTotal FROM inserted;
END;
GO

-- ============================================================
-- TRIGGER 14: Auto Update Status Langganan saat Expired
-- ============================================================
CREATE TRIGGER trg_Langganan_AutoExpired
ON Langganan
AFTER UPDATE
AS
BEGIN
    SET NOCOUNT ON;
    
    IF UPDATE(Tanggal_Selesai) OR UPDATE(Status)
    BEGIN
        UPDATE Langganan
        SET Status = 2  -- Berakhir
        WHERE ID_Langganan IN (
            SELECT i.ID_Langganan 
            FROM inserted i
            WHERE i.Tanggal_Selesai < GETDATE()
              AND i.Status = 1  -- Masih aktif
        );
        
        INSERT INTO Log_History (Nama_Tabel, ID_Record, Aksi, Data_Baru, User_Aksi)
        SELECT 
            'Langganan',
            i.ID_Langganan,
            'UPDATE',
            'Langganan otomatis berakhir karena melewati tanggal selesai',
            SUSER_SNAME()
        FROM inserted i
        WHERE i.Tanggal_Selesai < GETDATE()
          AND i.Status = 1;
    END
END;
GO

-- ============================================================
-- TRIGGER 15: Log History untuk Promo
-- ============================================================
CREATE TRIGGER trg_Promo_LogHistory
ON Promo
AFTER INSERT, UPDATE, DELETE
AS
BEGIN
    SET NOCOUNT ON;
    DECLARE @UserAksi VARCHAR(50) = SUSER_SNAME();
    
    IF EXISTS (SELECT 1 FROM inserted) AND NOT EXISTS (SELECT 1 FROM deleted)
    BEGIN
        INSERT INTO Log_History (Nama_Tabel, ID_Record, Aksi, Data_Baru, User_Aksi)
        SELECT 'Promo', i.ID_Promo, 'INSERT',
               (SELECT i.* FOR JSON PATH, WITHOUT_ARRAY_WRAPPER), @UserAksi
        FROM inserted i;
    END
    
    IF EXISTS (SELECT 1 FROM inserted) AND EXISTS (SELECT 1 FROM deleted)
    BEGIN
        INSERT INTO Log_History (Nama_Tabel, ID_Record, Aksi, Data_Lama, Data_Baru, User_Aksi)
        SELECT 'Promo', i.ID_Promo, 'UPDATE',
               (SELECT d.* FOR JSON PATH, WITHOUT_ARRAY_WRAPPER),
               (SELECT i.* FOR JSON PATH, WITHOUT_ARRAY_WRAPPER), @UserAksi
        FROM inserted i INNER JOIN deleted d ON i.ID_Promo = d.ID_Promo;
    END
    
    IF EXISTS (SELECT 1 FROM deleted) AND NOT EXISTS (SELECT 1 FROM inserted)
    BEGIN
        INSERT INTO Log_History (Nama_Tabel, ID_Record, Aksi, Data_Lama, User_Aksi)
        SELECT 'Promo', d.ID_Promo, 'DELETE',
               (SELECT d.* FOR JSON PATH, WITHOUT_ARRAY_WRAPPER), @UserAksi
        FROM deleted d;
    END
END;
GO

-- ============================================================
-- TRIGGER 16: Log History untuk Jadwal
-- ============================================================
CREATE TRIGGER trg_Jadwal_LogHistory
ON Jadwal
AFTER INSERT, UPDATE, DELETE
AS
BEGIN
    SET NOCOUNT ON;
    DECLARE @UserAksi VARCHAR(50) = SUSER_SNAME();
    
    IF EXISTS (SELECT 1 FROM inserted) AND NOT EXISTS (SELECT 1 FROM deleted)
    BEGIN
        INSERT INTO Log_History (Nama_Tabel, ID_Record, Aksi, Data_Baru, User_Aksi)
        SELECT 'Jadwal', i.ID_Jadwal, 'INSERT',
               (SELECT i.* FOR JSON PATH, WITHOUT_ARRAY_WRAPPER), @UserAksi
        FROM inserted i;
    END
    
    IF EXISTS (SELECT 1 FROM inserted) AND EXISTS (SELECT 1 FROM deleted)
    BEGIN
        INSERT INTO Log_History (Nama_Tabel, ID_Record, Aksi, Data_Lama, Data_Baru, User_Aksi)
        SELECT 'Jadwal', i.ID_Jadwal, 'UPDATE',
               (SELECT d.* FOR JSON PATH, WITHOUT_ARRAY_WRAPPER),
               (SELECT i.* FOR JSON PATH, WITHOUT_ARRAY_WRAPPER), @UserAksi
        FROM inserted i INNER JOIN deleted d ON i.ID_Jadwal = d.ID_Jadwal;
    END
    
    IF EXISTS (SELECT 1 FROM deleted) AND NOT EXISTS (SELECT 1 FROM inserted)
    BEGIN
        INSERT INTO Log_History (Nama_Tabel, ID_Record, Aksi, Data_Lama, User_Aksi)
        SELECT 'Jadwal', d.ID_Jadwal, 'DELETE',
               (SELECT d.* FOR JSON PATH, WITHOUT_ARRAY_WRAPPER), @UserAksi
        FROM deleted d;
    END
END;
GO




-- ============================================================
-- CONTOH PENGGUNAAN UDF
-- ============================================================

-- Scalar Function
SELECT dbo.udf_HitungTotalPendapatan('2024-05-01', '2024-06-30') AS TotalPendapatan;
SELECT dbo.udf_HitungBookingAktifCustomer(1) AS BookingAktifCustomer1;
SELECT dbo.udf_HitungSisaStokAlat(1) AS SisaStokBolaBasket;
SELECT dbo.udf_HitungDiskonMember(2, 100000) AS DiskonGold;
SELECT dbo.udf_CekKetersediaanJadwal(9) AS StatusJadwal9;
SELECT dbo.udf_HitungBiayaPembatalan(200000) AS BiayaBatal;
SELECT dbo.udf_FormatStatusBooking(1) AS StatusBooking;
SELECT dbo.udf_FormatStatusLangganan(2) AS StatusLangganan;

-- Table-Valued Function (pakai SELECT)
SELECT * FROM dbo.udf_LaporanPendapatanHarian('2024-05-01', '2024-06-30');
SELECT * FROM dbo.udf_DashboardRingkasanTransaksi(NULL);  -- Hari ini
SELECT * FROM dbo.udf_DashboardRingkasanTransaksi('2024-06-10');  -- Tanggal spesifik
SELECT * FROM dbo.udf_LaporanPenjualanAlat('2024-05-01', '2024-06-30');
SELECT * FROM dbo.udf_LaporanBookingPerLapangan('2024-05-01', '2024-06-30');
SELECT * FROM dbo.udf_LaporanCustomerAktif(1);
SELECT * FROM dbo.udf_LaporanJadwalTersedia('2024-06-15', NULL);
SELECT * FROM dbo.udf_LaporanJadwalTersedia('2024-06-15', 1);
SELECT * FROM dbo.udf_LaporanPembatalanBooking('2024-06-01', '2024-06-30');
SELECT * FROM dbo.udf_LaporanPromoAktif(NULL);
SELECT * FROM dbo.udf_LaporanStokMenipis(5);
SELECT * FROM dbo.udf_LaporanMemberAktif(NULL);

-- ============================================================
-- CONTOH PENGGUNAAN TRIGGER (Otomatis jalan saat CRUD)
-- ============================================================

-- Test Trigger Log History
INSERT INTO Customer (Nama_Customer, Tanggal_Lahir, Tempat_Lahir, Jenis_Kelamin, Alamat, No_Telepon, Email, Username, Kata_Sandi, Status, Is_Deleted, Created_By, Created_Date)
VALUES ('Test User', '2000-01-01', 'Jakarta', 1, 'Jl. Test', '08129999999', 'test@mail.com', 'test_user', 'test123', 1, 0, 'SYSTEM', GETDATE());

-- Lihat log history
SELECT * FROM Log_History WHERE Nama_Tabel = 'Customer' ORDER BY Waktu_Aksi DESC;

-- Test Trigger Auto Update Stok
-- Insert pembelian baru (stok akan otomatis berkurang)
-- Pastikan stok cukup dulu!

-- Test Trigger Pembatalan
UPDATE Booking SET Status = 3 WHERE ID_Booking = 1;
-- Otomatis akan insert ke Pembatalan_Booking dan kembalikan jadwal