-- ============================================================
-- UDF 1: Hitung Total Pendapatan Berdasarkan Rentang Tanggal
-- ============================================================
CREATE FUNCTION dbo.udf_HitungTotalPendapatan
(
    @TanggalMulai DATE,
    @TanggalSelesai DATE
)
RETURNS DECIMAL(18,2)
AS
BEGIN
    DECLARE @TotalPendapatan DECIMAL(18,2);
    
    SELECT @TotalPendapatan = ISNULL(SUM(Total_Bayar), 0)
    FROM (
        SELECT Total_Bayar FROM Booking 
        WHERE Status IN (1, 2) 
          AND Tanggal_Booking BETWEEN @TanggalMulai AND @TanggalSelesai
        UNION ALL
        SELECT Total_Bayar FROM Langganan 
        WHERE Status IN (1, 2) 
          AND Tanggal_Mulai BETWEEN @TanggalMulai AND @TanggalSelesai
        UNION ALL
        SELECT Total_Bayar FROM Beli_Alat 
        WHERE Status = 1 
          AND Tanggal_Beli BETWEEN @TanggalMulai AND @TanggalSelesai
    ) AS SemuaPendapatan;
    
    RETURN @TotalPendapatan;
END;
GO

-- ============================================================
-- UDF 2: Hitung Jumlah Booking Aktif per Customer
-- ============================================================
CREATE FUNCTION dbo.udf_HitungBookingAktifCustomer
(
    @ID_Customer INT
)
RETURNS INT
AS
BEGIN
    DECLARE @JumlahBooking INT;
    
    SELECT @JumlahBooking = COUNT(*)
    FROM Booking
    WHERE ID_Customer = @ID_Customer
      AND Status IN (0, 1);
    
    RETURN @JumlahBooking;
END;
GO

-- ============================================================
-- UDF 3: Hitung Sisa Stok Alat Setelah Pembelian
-- ============================================================
CREATE FUNCTION dbo.udf_HitungSisaStokAlat
(
    @ID_Alat INT
)
RETURNS INT
AS
BEGIN
    DECLARE @SisaStok INT;
    DECLARE @TotalTerjual INT;
    
    SELECT @TotalTerjual = ISNULL(SUM(Jumlah), 0)
    FROM Detail_Beli_Alat dba
    INNER JOIN Beli_Alat ba ON dba.ID_Beli = ba.ID_Beli
    WHERE dba.ID_Alat = @ID_Alat
      AND ba.Status = 1;
    
    SELECT @SisaStok = Stok - @TotalTerjual
    FROM Alat
    WHERE ID_Alat = @ID_Alat;
    
    RETURN ISNULL(@SisaStok, 0);
END;
GO

-- ============================================================
-- UDF 4: Hitung Diskon Member Berdasarkan Tipe Member
-- ============================================================
CREATE FUNCTION dbo.udf_HitungDiskonMember
(
    @ID_Tipe INT,
    @HargaDasar DECIMAL(18,2)
)
RETURNS DECIMAL(18,2)
AS
BEGIN
    DECLARE @Potongan DECIMAL(18,2);
    
    SELECT @Potongan = ISNULL(Potongan_Harga, 0)
    FROM Tipe_Member
    WHERE ID_Tipe = @ID_Tipe
      AND Status = 1;
    
    RETURN @Potongan;
END;
GO

-- ============================================================
-- UDF 5: Cek Status Ketersediaan Jadwal
-- ============================================================
CREATE FUNCTION dbo.udf_CekKetersediaanJadwal
(
    @ID_Jadwal INT
)
RETURNS VARCHAR(20)
AS
BEGIN
    DECLARE @StatusJadwal VARCHAR(20);
    DECLARE @JadwalStatus INT;
    DECLARE @IsBooked INT;
    
    SELECT @JadwalStatus = Status 
    FROM Jadwal 
    WHERE ID_Jadwal = @ID_Jadwal;
    
    SELECT @IsBooked = COUNT(*) 
    FROM Booking 
    WHERE ID_Jadwal = @ID_Jadwal 
      AND Status IN (0, 1);
    
    IF @JadwalStatus = 0
        SET @StatusJadwal = 'Tidak Tersedia';
    ELSE IF @IsBooked > 0
        SET @StatusJadwal = 'Sudah Dibooking';
    ELSE
        SET @StatusJadwal = 'Tersedia';
    
    RETURN @StatusJadwal;
END;
GO

-- ============================================================
-- UDF 6: Hitung Biaya Pembatalan (50% dari Total Bayar)
-- ============================================================
CREATE FUNCTION dbo.udf_HitungBiayaPembatalan
(
    @TotalBayar DECIMAL(18,2)
)
RETURNS DECIMAL(18,2)
AS
BEGIN
    RETURN @TotalBayar * 0.5;
END;
GO

-- ============================================================
-- UDF 7: Format Status Booking ke Text
-- ============================================================
CREATE FUNCTION dbo.udf_FormatStatusBooking
(
    @Status INT
)
RETURNS VARCHAR(20)
AS
BEGIN
    RETURN CASE @Status
        WHEN 0 THEN 'Menunggu Konfirmasi'
        WHEN 1 THEN 'Berhasil'
        WHEN 2 THEN 'Selesai'
        WHEN 3 THEN 'Dibatalkan'
        ELSE 'Unknown'
    END;
END;
GO

-- ============================================================
-- UDF 8: Format Status Langganan ke Text
-- ============================================================
CREATE FUNCTION dbo.udf_FormatStatusLangganan
(
    @Status INT
)
RETURNS VARCHAR(20)
AS
BEGIN
    RETURN CASE @Status
        WHEN 0 THEN 'Menunggu Konfirmasi'
        WHEN 1 THEN 'Aktif'
        WHEN 2 THEN 'Berakhir'
        WHEN 3 THEN 'Ditolak'
        ELSE 'Unknown'
    END;
END;
GO


-- ============================================================
-- UDF 9: Laporan Pendapatan Harian (Table-Valued)
-- ============================================================
CREATE FUNCTION dbo.udf_LaporanPendapatanHarian
(
    @TanggalMulai DATE,
    @TanggalSelesai DATE
)
RETURNS TABLE
AS
RETURN
(
    SELECT 
        T.Tanggal,
        ISNULL(B.TotalBooking, 0) AS PendapatanBooking,
        ISNULL(L.TotalLangganan, 0) AS PendapatanLangganan,
        ISNULL(A.TotalAlat, 0) AS PendapatanAlat,
        ISNULL(B.TotalBooking, 0) + ISNULL(L.TotalLangganan, 0) + ISNULL(A.TotalAlat, 0) AS TotalPendapatan
    FROM (
        SELECT DISTINCT Tanggal_Booking AS Tanggal FROM Booking
        WHERE Tanggal_Booking BETWEEN @TanggalMulai AND @TanggalSelesai
        UNION
        SELECT DISTINCT Tanggal_Mulai FROM Langganan
        WHERE Tanggal_Mulai BETWEEN @TanggalMulai AND @TanggalSelesai
        UNION
        SELECT DISTINCT Tanggal_Beli FROM Beli_Alat
        WHERE Tanggal_Beli BETWEEN @TanggalMulai AND @TanggalSelesai
    ) T
    LEFT JOIN (
        SELECT Tanggal_Booking, SUM(Total_Bayar) AS TotalBooking
        FROM Booking
        WHERE Status IN (1, 2)
        GROUP BY Tanggal_Booking
    ) B ON T.Tanggal = B.Tanggal_Booking
    LEFT JOIN (
        SELECT Tanggal_Mulai, SUM(Total_Bayar) AS TotalLangganan
        FROM Langganan
        WHERE Status IN (1, 2)
        GROUP BY Tanggal_Mulai
    ) L ON T.Tanggal = L.Tanggal_Mulai
    LEFT JOIN (
        SELECT Tanggal_Beli, SUM(Total_Bayar) AS TotalAlat
        FROM Beli_Alat
        WHERE Status = 1
        GROUP BY Tanggal_Beli
    ) A ON T.Tanggal = A.Tanggal_Beli
);
GO

-- ============================================================
-- UDF 10: Dashboard Ringkasan Transaksi (Table-Valued)
-- ============================================================
CREATE FUNCTION dbo.udf_DashboardRingkasanTransaksi
(
    @Tanggal DATE = NULL  -- NULL = hari ini
)
RETURNS TABLE
AS
RETURN
(
    SELECT 
        (SELECT COUNT(*) FROM Booking WHERE Status = 0 AND (@Tanggal IS NULL OR Tanggal_Booking = @Tanggal)) AS BookingMenunggu,
        (SELECT COUNT(*) FROM Booking WHERE Status = 1 AND (@Tanggal IS NULL OR Tanggal_Booking = @Tanggal)) AS BookingBerhasil,
        (SELECT COUNT(*) FROM Booking WHERE Status = 2 AND (@Tanggal IS NULL OR Tanggal_Booking = @Tanggal)) AS BookingSelesai,
        (SELECT COUNT(*) FROM Booking WHERE Status = 3 AND (@Tanggal IS NULL OR Tanggal_Booking = @Tanggal)) AS BookingDibatalkan,
        (SELECT COUNT(*) FROM Langganan WHERE Status = 0 AND (@Tanggal IS NULL OR Tanggal_Mulai = @Tanggal)) AS LanggananMenunggu,
        (SELECT COUNT(*) FROM Langganan WHERE Status = 1 AND (@Tanggal IS NULL OR Tanggal_Mulai = @Tanggal)) AS LanggananAktif,
        (SELECT COUNT(*) FROM Beli_Alat WHERE Status = 1 AND (@Tanggal IS NULL OR Tanggal_Beli = @Tanggal)) AS PembelianAlat,
        (SELECT ISNULL(SUM(Total_Bayar), 0) FROM Booking WHERE Status IN (1,2) AND (@Tanggal IS NULL OR Tanggal_Booking = @Tanggal)) AS TotalPendapatanBooking,
        (SELECT ISNULL(SUM(Total_Bayar), 0) FROM Langganan WHERE Status IN (1,2) AND (@Tanggal IS NULL OR Tanggal_Mulai = @Tanggal)) AS TotalPendapatanLangganan,
        (SELECT ISNULL(SUM(Total_Bayar), 0) FROM Beli_Alat WHERE Status = 1 AND (@Tanggal IS NULL OR Tanggal_Beli = @Tanggal)) AS TotalPendapatanAlat
);
GO

-- ============================================================
-- UDF 11: Laporan Penjualan Alat per Periode (Table-Valued)
-- ============================================================
CREATE FUNCTION dbo.udf_LaporanPenjualanAlat
(
    @TanggalMulai DATE,
    @TanggalSelesai DATE
)
RETURNS TABLE
AS
RETURN
(
    SELECT 
        a.ID_Alat,
        a.Nama_Alat,
        a.Harga_Alat,
        ISNULL(SUM(dba.Jumlah), 0) AS TotalTerjual,
        ISNULL(SUM(dba.SubTotal), 0) AS TotalPendapatan,
        a.Stok - ISNULL(SUM(dba.Jumlah), 0) AS SisaStok
    FROM Alat a
    LEFT JOIN Detail_Beli_Alat dba ON a.ID_Alat = dba.ID_Alat
    LEFT JOIN Beli_Alat ba ON dba.ID_Beli = ba.ID_Beli 
        AND ba.Status = 1
        AND ba.Tanggal_Beli BETWEEN @TanggalMulai AND @TanggalSelesai
    WHERE a.Is_Deleted = 0
    GROUP BY a.ID_Alat, a.Nama_Alat, a.Harga_Alat, a.Stok
);
GO

-- ============================================================
-- UDF 12: Laporan Booking per Lapangan (Table-Valued)
-- ============================================================
CREATE FUNCTION dbo.udf_LaporanBookingPerLapangan
(
    @TanggalMulai DATE,
    @TanggalSelesai DATE
)
RETURNS TABLE
AS
RETURN
(
    SELECT 
        l.ID_Lapangan,
        l.Nama_Lapangan,
        l.Harga_Sewa,
        COUNT(b.ID_Booking) AS TotalBooking,
        SUM(CASE WHEN b.Status = 1 THEN 1 ELSE 0 END) AS BookingBerhasil,
        SUM(CASE WHEN b.Status = 2 THEN 1 ELSE 0 END) AS BookingSelesai,
        SUM(CASE WHEN b.Status = 3 THEN 1 ELSE 0 END) AS BookingDibatalkan,
        ISNULL(SUM(b.Total_Bayar), 0) AS TotalPendapatan
    FROM Lapangan l
    LEFT JOIN Jadwal j ON l.ID_Lapangan = j.ID_Lapangan
    LEFT JOIN Booking b ON j.ID_Jadwal = b.ID_Jadwal
        AND b.Tanggal_Booking BETWEEN @TanggalMulai AND @TanggalSelesai
    WHERE l.Is_Deleted = 0
    GROUP BY l.ID_Lapangan, l.Nama_Lapangan, l.Harga_Sewa
);
GO

-- ============================================================
-- UDF 13: Laporan Customer Aktif (Table-Valued)
-- ============================================================
CREATE FUNCTION dbo.udf_LaporanCustomerAktif
(
    @MinimalBooking INT = 1
)
RETURNS TABLE
AS
RETURN
(
    SELECT 
        c.ID_Customer,
        c.Nama_Customer,
        c.Email,
        c.No_Telepon,
        COUNT(b.ID_Booking) AS TotalBooking,
        COUNT(l.ID_Langganan) AS TotalLangganan,
        COUNT(ba.ID_Beli) AS TotalPembelianAlat,
        ISNULL(SUM(b.Total_Bayar), 0) + 
        ISNULL(SUM(l.Total_Bayar), 0) + 
        ISNULL(SUM(ba.Total_Bayar), 0) AS TotalTransaksi
    FROM Customer c
    LEFT JOIN Booking b ON c.ID_Customer = b.ID_Customer AND b.Status IN (1, 2)
    LEFT JOIN Langganan l ON c.ID_Customer = l.ID_Customer AND l.Status IN (1, 2)
    LEFT JOIN Beli_Alat ba ON c.ID_Customer = ba.ID_Customer AND ba.Status = 1
    WHERE c.Is_Deleted = 0
    GROUP BY c.ID_Customer, c.Nama_Customer, c.Email, c.No_Telepon
    HAVING COUNT(b.ID_Booking) >= @MinimalBooking
);
GO

-- ============================================================
-- UDF 14: Laporan Jadwal Tersedia per Tanggal (Table-Valued)
-- ============================================================
CREATE FUNCTION dbo.udf_LaporanJadwalTersedia
(
    @Tanggal DATE,
    @ID_Lapangan INT = NULL  -- NULL = semua lapangan
)
RETURNS TABLE
AS
RETURN
(
    SELECT 
        j.ID_Jadwal,
        l.Nama_Lapangan,
        j.Tanggal,
        j.Jam_Mulai,
        j.Jam_Selesai,
        j.Status AS StatusJadwal,
        CASE 
            WHEN j.Status = 0 THEN 'Tidak Tersedia'
            WHEN EXISTS (
                SELECT 1 FROM Booking b 
                WHERE b.ID_Jadwal = j.ID_Jadwal AND b.Status IN (0, 1)
            ) THEN 'Sudah Dibooking'
            ELSE 'Tersedia'
        END AS Keterangan
    FROM Jadwal j
    INNER JOIN Lapangan l ON j.ID_Lapangan = l.ID_Lapangan
    WHERE j.Tanggal = @Tanggal
      AND j.Is_Deleted = 0
      AND (@ID_Lapangan IS NULL OR j.ID_Lapangan = @ID_Lapangan)
);
GO

-- ============================================================
-- UDF 15: Laporan Pembatalan Booking (Table-Valued)
-- ============================================================
CREATE FUNCTION dbo.udf_LaporanPembatalanBooking
(
    @TanggalMulai DATE,
    @TanggalSelesai DATE
)
RETURNS TABLE
AS
RETURN
(
    SELECT 
        pb.ID_Pembatalan,
        b.ID_Booking,
        c.Nama_Customer,
        l.Nama_Lapangan,
        j.Tanggal AS TanggalMain,
        j.Jam_Mulai,
        j.Jam_Selesai,
        b.Total_Bayar,
        pb.Biaya_Batal,
        pb.Nominal_Refund,
        pb.Alasan,
        k.Nama_Karyawan AS PetugasPembatalan,
        pb.Tanggal_Batal
    FROM Pembatalan_Booking pb
    INNER JOIN Booking b ON pb.ID_Booking = b.ID_Booking
    INNER JOIN Customer c ON b.ID_Customer = c.ID_Customer
    INNER JOIN Jadwal j ON b.ID_Jadwal = j.ID_Jadwal
    INNER JOIN Lapangan l ON j.ID_Lapangan = l.ID_Lapangan
    INNER JOIN Karyawan k ON pb.ID_Karyawan = k.ID_Karyawan
    WHERE pb.Tanggal_Batal BETWEEN @TanggalMulai AND @TanggalSelesai
);
GO

-- ============================================================
-- UDF 16: Laporan Promo Aktif (Table-Valued)
-- ============================================================
CREATE FUNCTION dbo.udf_LaporanPromoAktif
(
    @Tanggal DATE = NULL  -- NULL = hari ini
)
RETURNS TABLE
AS
RETURN
(
    SELECT 
        p.ID_Promo,
        p.Nama_Promo,
        p.Diskon,
        p.Tanggal_Mulai,
        p.Tanggal_Selesai,
        DATEDIFF(DAY, ISNULL(@Tanggal, GETDATE()), p.Tanggal_Selesai) AS SisaHari,
        CASE 
            WHEN p.Tanggal_Mulai <= ISNULL(@Tanggal, GETDATE()) 
                 AND p.Tanggal_Selesai >= ISNULL(@Tanggal, GETDATE()) 
            THEN 'Aktif'
            WHEN p.Tanggal_Mulai > ISNULL(@Tanggal, GETDATE()) 
            THEN 'Akan Datang'
            ELSE 'Berakhir'
        END AS StatusPromo
    FROM Promo p
    WHERE p.Is_Deleted = 0
);
GO

-- ============================================================
-- UDF 17: Laporan Stok Alat Menipis (Table-Valued)
-- ============================================================
CREATE FUNCTION dbo.udf_LaporanStokMenipis
(
    @BatasMinimal INT = 5
)
RETURNS TABLE
AS
RETURN
(
    SELECT 
        a.ID_Alat,
        a.Nama_Alat,
        a.Stok,
        @BatasMinimal AS BatasMinimal,
        a.Stok - @BatasMinimal AS Selisih,
        CASE 
            WHEN a.Stok <= 0 THEN 'Stok Habis'
            WHEN a.Stok <= @BatasMinimal THEN 'Stok Menipis'
            ELSE 'Stok Aman'
        END AS StatusStok
    FROM Alat a
    WHERE a.Is_Deleted = 0
      AND a.Stok <= @BatasMinimal
);
GO

-- ============================================================
-- UDF 18: Laporan Member Aktif (Table-Valued)
-- ============================================================
CREATE FUNCTION dbo.udf_LaporanMemberAktif
(
    @Tanggal DATE = NULL
)
RETURNS TABLE
AS
RETURN
(
    SELECT 
        l.ID_Langganan,
        c.Nama_Customer,
        t.Nama_Tipe,
        t.Harga_Member,
        t.Potongan_Harga,
        l.Tanggal_Mulai,
        l.Tanggal_Selesai,
        DATEDIFF(DAY, ISNULL(@Tanggal, GETDATE()), l.Tanggal_Selesai) AS SisaHari,
        CASE 
            WHEN l.Tanggal_Selesai < ISNULL(@Tanggal, GETDATE()) THEN 'Expired'
            WHEN DATEDIFF(DAY, ISNULL(@Tanggal, GETDATE()), l.Tanggal_Selesai) <= 7 THEN 'Akan Berakhir'
            ELSE 'Aktif'
        END AS StatusMembership
    FROM Langganan l
    INNER JOIN Customer c ON l.ID_Customer = c.ID_Customer
    INNER JOIN Tipe_Member t ON l.ID_Tipe = t.ID_Tipe
    WHERE l.Status = 1
);
GO