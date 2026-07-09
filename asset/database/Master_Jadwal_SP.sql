-- ============================================================
-- STORED PROCEDURE MASTER: Jadwal
-- ============================================================

USE Hoopball;
GO

-- 1. SP INSERT Jadwal
-- ============================================================
CREATE PROCEDURE SP_Jadwal_Insert
    @ID_Lapangan    INT,
    @Tanggal        DATE,
    @Jam_Mulai      TIME,
    @Jam_Selesai    TIME,
    @Status         INT = 1,        -- Default: 1 (Tersedia)
    @Created_By     VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;

    -- Validasi: Lapangan harus aktif dan tidak dihapus
    IF NOT EXISTS (SELECT 1 FROM Lapangan WHERE ID_Lapangan = @ID_Lapangan AND Status = 1 AND Is_Deleted = 0)
    BEGIN
        RAISERROR('Lapangan tidak ditemukan atau tidak aktif.', 16, 1);
        RETURN;
    END

    -- Validasi: Jam mulai harus lebih kecil dari jam selesai
    IF @Jam_Mulai >= @Jam_Selesai
    BEGIN
        RAISERROR('Jam mulai harus lebih kecil dari jam selesai.', 16, 1);
        RETURN;
    END

    -- Validasi: Status hanya boleh 0 atau 1
    IF @Status NOT IN (0, 1)
    BEGIN
        RAISERROR('Status hanya boleh 0 (Tidak Tersedia) atau 1 (Tersedia).', 16, 1);
        RETURN;
    END

    -- Validasi: Cek bentrok jadwal (overlap) pada lapangan dan tanggal yang sama
    IF EXISTS (
        SELECT 1 FROM Jadwal 
        WHERE ID_Lapangan = @ID_Lapangan 
          AND Tanggal = @Tanggal 
          AND Is_Deleted = 0
          AND (
              -- Kasus 1: Jam mulai baru berada dalam slot existing
              (@Jam_Mulai >= Jam_Mulai AND @Jam_Mulai < Jam_Selesai)
              OR
              -- Kasus 2: Jam selesai baru berada dalam slot existing
              (@Jam_Selesai > Jam_Mulai AND @Jam_Selesai <= Jam_Selesai)
              OR
              -- Kasus 3: Slot baru menutupi slot existing sepenuhnya
              (@Jam_Mulai <= Jam_Mulai AND @Jam_Selesai >= Jam_Selesai)
          )
    )
    BEGIN
        RAISERROR('Jadwal bentrok dengan slot yang sudah ada pada lapangan dan tanggal tersebut.', 16, 1);
        RETURN;
    END

    -- Insert data
    INSERT INTO Jadwal (ID_Lapangan, Tanggal, Jam_Mulai, Jam_Selesai, Status, Is_Deleted, Created_By, Created_Date)
    VALUES (@ID_Lapangan, @Tanggal, @Jam_Mulai, @Jam_Selesai, @Status, 0, @Created_By, GETDATE());

    -- Return ID yang baru dibuat
    SELECT SCOPE_IDENTITY() AS ID_Jadwal_Baru;
END;
GO

-- 2. SP UPDATE Jadwal
-- ============================================================
CREATE PROCEDURE SP_Jadwal_Update
    @ID_Jadwal      INT,
    @ID_Lapangan    INT = NULL,         -- NULL = tidak diubah
    @Tanggal        DATE = NULL,        -- NULL = tidak diubah
    @Jam_Mulai      TIME = NULL,        -- NULL = tidak diubah
    @Jam_Selesai    TIME = NULL,        -- NULL = tidak diubah
    @Status         INT = NULL,         -- NULL = tidak diubah
    @Modified_By    VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;

    -- Validasi: Jadwal harus ada dan belum dihapus
    IF NOT EXISTS (SELECT 1 FROM Jadwal WHERE ID_Jadwal = @ID_Jadwal AND Is_Deleted = 0)
    BEGIN
        RAISERROR('Jadwal tidak ditemukan atau sudah dihapus.', 16, 1);
        RETURN;
    END

    -- Ambil data existing
    DECLARE @Existing_ID_Lapangan INT, @Existing_Tanggal DATE, @Existing_Jam_Mulai TIME, @Existing_Jam_Selesai TIME, @Existing_Status INT;
    SELECT @Existing_ID_Lapangan = ID_Lapangan, @Existing_Tanggal = Tanggal, 
           @Existing_Jam_Mulai = Jam_Mulai, @Existing_Jam_Selesai = Jam_Selesai, @Existing_Status = Status
    FROM Jadwal WHERE ID_Jadwal = @ID_Jadwal;

    -- Set nilai default jika parameter NULL
    SET @ID_Lapangan = ISNULL(@ID_Lapangan, @Existing_ID_Lapangan);
    SET @Tanggal = ISNULL(@Tanggal, @Existing_Tanggal);
    SET @Jam_Mulai = ISNULL(@Jam_Mulai, @Existing_Jam_Mulai);
    SET @Jam_Selesai = ISNULL(@Jam_Selesai, @Existing_Jam_Selesai);
    SET @Status = ISNULL(@Status, @Existing_Status);

    -- Validasi: Lapangan harus aktif
    IF NOT EXISTS (SELECT 1 FROM Lapangan WHERE ID_Lapangan = @ID_Lapangan AND Status = 1 AND Is_Deleted = 0)
    BEGIN
        RAISERROR('Lapangan tidak ditemukan atau tidak aktif.', 16, 1);
        RETURN;
    END

    -- Validasi: Jam
    IF @Jam_Mulai >= @Jam_Selesai
    BEGIN
        RAISERROR('Jam mulai harus lebih kecil dari jam selesai.', 16, 1);
        RETURN;
    END

    -- Validasi: Status
    IF @Status NOT IN (0, 1)
    BEGIN
        RAISERROR('Status hanya boleh 0 (Tidak Tersedia) atau 1 (Tersedia).', 16, 1);
        RETURN;
    END

    -- Validasi: Cek bentrok (kecuali dengan dirinya sendiri)
    IF EXISTS (
        SELECT 1 FROM Jadwal 
        WHERE ID_Lapangan = @ID_Lapangan 
          AND Tanggal = @Tanggal 
          AND ID_Jadwal <> @ID_Jadwal
          AND Is_Deleted = 0
          AND (
              (@Jam_Mulai >= Jam_Mulai AND @Jam_Mulai < Jam_Selesai)
              OR
              (@Jam_Selesai > Jam_Mulai AND @Jam_Selesai <= Jam_Selesai)
              OR
              (@Jam_Mulai <= Jam_Mulai AND @Jam_Selesai >= Jam_Selesai)
          )
    )
    BEGIN
        RAISERROR('Jadwal bentrok dengan slot yang sudah ada pada lapangan dan tanggal tersebut.', 16, 1);
        RETURN;
    END

    -- Update data
    UPDATE Jadwal
    SET ID_Lapangan = @ID_Lapangan,
        Tanggal = @Tanggal,
        Jam_Mulai = @Jam_Mulai,
        Jam_Selesai = @Jam_Selesai,
        Status = @Status,
        Modified_By = @Modified_By,
        Modified_Date = GETDATE()
    WHERE ID_Jadwal = @ID_Jadwal;

    SELECT @@ROWCOUNT AS Rows_Affected;
END;
GO

-- 3. SP DELETE (Soft Delete) Jadwal
-- ============================================================
CREATE PROCEDURE SP_Jadwal_Delete
    @ID_Jadwal      INT,
    @Deleted_By     VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;

    -- Validasi: Jadwal harus ada dan belum dihapus
    IF NOT EXISTS (SELECT 1 FROM Jadwal WHERE ID_Jadwal = @ID_Jadwal AND Is_Deleted = 0)
    BEGIN
        RAISERROR('Jadwal tidak ditemukan atau sudah dihapus.', 16, 1);
        RETURN;
    END

    -- Validasi: Jadwal tidak boleh dihapus jika sedang digunakan di Booking aktif
    IF EXISTS (SELECT 1 FROM Booking WHERE ID_Jadwal = @ID_Jadwal AND Status IN (0, 1))
    BEGIN
        RAISERROR('Jadwal tidak dapat dihapus karena sedang digunakan dalam booking aktif.', 16, 1);
        RETURN;
    END

    -- Soft delete
    UPDATE Jadwal
    SET Is_Deleted = 1,
        Deleted_By = @Deleted_By,
        Deleted_Date = GETDATE()
    WHERE ID_Jadwal = @ID_Jadwal;

    SELECT @@ROWCOUNT AS Rows_Affected;
END;
GO

-- 4. SP SELECT (Get By ID) Jadwal
-- ============================================================
CREATE PROCEDURE SP_Jadwal_Select
    @ID_Jadwal      INT
AS
BEGIN
    SET NOCOUNT ON;

    SELECT 
        j.ID_Jadwal,
        j.ID_Lapangan,
        l.Nama_Lapangan,
        l.Harga_Sewa,
        j.Tanggal,
        j.Jam_Mulai,
        j.Jam_Selesai,
        j.Status,
        CASE j.Status 
            WHEN 0 THEN 'Tidak Tersedia'
            WHEN 1 THEN 'Tersedia'
            ELSE 'Unknown'
        END AS Status_Label,
        j.Is_Deleted,
        j.Created_By,
        j.Created_Date,
        j.Modified_By,
        j.Modified_Date,
        j.Deleted_By,
        j.Deleted_Date
    FROM Jadwal j
    INNER JOIN Lapangan l ON j.ID_Lapangan = l.ID_Lapangan
    WHERE j.ID_Jadwal = @ID_Jadwal AND j.Is_Deleted = 0;
END;
GO

-- 5. SP SELECT ALL Jadwal (dengan filter opsional)
-- ============================================================
CREATE PROCEDURE SP_Jadwal_SelectAll
    @ID_Lapangan    INT = NULL,         -- Filter by lapangan
    @Tanggal        DATE = NULL,        -- Filter by tanggal
    @Status         INT = NULL,         -- Filter by status
    @Tanggal_Dari   DATE = NULL,        -- Filter range: dari
    @Tanggal_Sampai DATE = NULL,        -- Filter range: sampai
    @Is_Deleted     BIT = 0             -- Default: hanya yang belum dihapus
AS
BEGIN
    SET NOCOUNT ON;

    SELECT 
        j.ID_Jadwal,
        j.ID_Lapangan,
        l.Nama_Lapangan,
        l.Harga_Sewa,
        j.Tanggal,
        j.Jam_Mulai,
        j.Jam_Selesai,
        j.Status,
        CASE j.Status 
            WHEN 0 THEN 'Tidak Tersedia'
            WHEN 1 THEN 'Tersedia'
            ELSE 'Unknown'
        END AS Status_Label,
        j.Is_Deleted,
        j.Created_By,
        j.Created_Date,
        j.Modified_By,
        j.Modified_Date
    FROM Jadwal j
    INNER JOIN Lapangan l ON j.ID_Lapangan = l.ID_Lapangan
    WHERE j.Is_Deleted = @Is_Deleted
      AND (@ID_Lapangan IS NULL OR j.ID_Lapangan = @ID_Lapangan)
      AND (@Tanggal IS NULL OR j.Tanggal = @Tanggal)
      AND (@Status IS NULL OR j.Status = @Status)
      AND (@Tanggal_Dari IS NULL OR j.Tanggal >= @Tanggal_Dari)
      AND (@Tanggal_Sampai IS NULL OR j.Tanggal <= @Tanggal_Sampai)
    ORDER BY j.Tanggal DESC, j.Jam_Mulai ASC, l.Nama_Lapangan ASC;
END;
GO

-- 6. SP TOGGLE STATUS Jadwal
-- ============================================================
CREATE PROCEDURE SP_Jadwal_ToggleStatus
    @ID_Jadwal      INT,
    @Modified_By    VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;

    -- Validasi: Jadwal harus ada dan belum dihapus
    IF NOT EXISTS (SELECT 1 FROM Jadwal WHERE ID_Jadwal = @ID_Jadwal AND Is_Deleted = 0)
    BEGIN
        RAISERROR('Jadwal tidak ditemukan atau sudah dihapus.', 16, 1);
        RETURN;
    END

    -- Toggle status (0 -> 1, 1 -> 0)
    UPDATE Jadwal
    SET Status = CASE WHEN Status = 1 THEN 0 ELSE 1 END,
        Modified_By = @Modified_By,
        Modified_Date = GETDATE()
    WHERE ID_Jadwal = @ID_Jadwal;

    -- Return status baru
    SELECT ID_Jadwal, Status,
        CASE Status 
            WHEN 0 THEN 'Tidak Tersedia'
            WHEN 1 THEN 'Tersedia'
        END AS Status_Label
    FROM Jadwal WHERE ID_Jadwal = @ID_Jadwal;
END;
GO

-- 7. SP GET AVAILABLE SLOTS (untuk booking)
-- ============================================================
CREATE PROCEDURE SP_Jadwal_GetAvailable
    @ID_Lapangan    INT = NULL,
    @Tanggal        DATE = NULL,
    @Jam_Mulai_Dari TIME = NULL,
    @Jam_Selesai_Sampai TIME = NULL
AS
BEGIN
    SET NOCOUNT ON;

    SELECT 
        j.ID_Jadwal,
        j.ID_Lapangan,
        l.Nama_Lapangan,
        l.Harga_Sewa,
        j.Tanggal,
        j.Jam_Mulai,
        j.Jam_Selesai,
        j.Status,
        CASE 
            WHEN b.ID_Booking IS NOT NULL THEN 'Terbooking'
            ELSE 'Tersedia'
        END AS Ketersediaan
    FROM Jadwal j
    INNER JOIN Lapangan l ON j.ID_Lapangan = l.ID_Lapangan
    LEFT JOIN Booking b ON j.ID_Jadwal = b.ID_Jadwal AND b.Status IN (0, 1) -- Booking aktif (Menunggu/Berhasil)
    WHERE j.Is_Deleted = 0
      AND j.Status = 1  -- Hanya yang status Tersedia
      AND (@ID_Lapangan IS NULL OR j.ID_Lapangan = @ID_Lapangan)
      AND (@Tanggal IS NULL OR j.Tanggal = @Tanggal)
      AND (@Jam_Mulai_Dari IS NULL OR j.Jam_Mulai >= @Jam_Mulai_Dari)
      AND (@Jam_Selesai_Sampai IS NULL OR j.Jam_Selesai <= @Jam_Selesai_Sampai)
      AND b.ID_Booking IS NULL  -- Hanya yang belum dibooking
    ORDER BY j.Tanggal, j.Jam_Mulai, l.Nama_Lapangan;
END;
GO

-- 8. SP BULK GENERATE (Generate semua slot untuk 1 lapangan + 1 tanggal)
-- ============================================================
CREATE PROCEDURE SP_Jadwal_BulkGenerate
    @ID_Lapangan    INT,
    @Tanggal        DATE,
    @Jam_Mulai_Awal TIME = '08:00',     -- Default jam mulai pertama
    @Jam_Mulai_Akhir TIME = '23:00',    -- Default jam mulai terakhir
    @Durasi_Jam     INT = 1,            -- Durasi per slot dalam jam
    @Status         INT = 1,
    @Created_By     VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;

    -- Validasi: Lapangan harus aktif
    IF NOT EXISTS (SELECT 1 FROM Lapangan WHERE ID_Lapangan = @ID_Lapangan AND Status = 1 AND Is_Deleted = 0)
    BEGIN
        RAISERROR('Lapangan tidak ditemukan atau tidak aktif.', 16, 1);
        RETURN;
    END

    DECLARE @CurrentJam TIME = @Jam_Mulai_Awal;
    DECLARE @InsertedCount INT = 0;
    DECLARE @SkippedCount INT = 0;

    WHILE @CurrentJam <= @Jam_Mulai_Akhir
    BEGIN
        DECLARE @JamSelesai TIME = DATEADD(HOUR, @Durasi_Jam, CAST(@CurrentJam AS DATETIME));

        -- Cek apakah slot sudah ada
        IF NOT EXISTS (
            SELECT 1 FROM Jadwal 
            WHERE ID_Lapangan = @ID_Lapangan 
              AND Tanggal = @Tanggal 
              AND Jam_Mulai = @CurrentJam
              AND Is_Deleted = 0
        )
        BEGIN
            INSERT INTO Jadwal (ID_Lapangan, Tanggal, Jam_Mulai, Jam_Selesai, Status, Is_Deleted, Created_By, Created_Date)
            VALUES (@ID_Lapangan, @Tanggal, @CurrentJam, @JamSelesai, @Status, 0, @Created_By, GETDATE());
            SET @InsertedCount = @InsertedCount + 1;
        END
        ELSE
        BEGIN
            SET @SkippedCount = @SkippedCount + 1;
        END

        SET @CurrentJam = DATEADD(HOUR, @Durasi_Jam, CAST(@CurrentJam AS DATETIME));
    END

    SELECT @InsertedCount AS Slot_Dibuat, @SkippedCount AS Slot_Dilewati;
END;
GO

-- ============================================================
-- VERIFIKASI: Tampilkan semua SP yang baru dibuat
-- ============================================================
SELECT 
    ROUTINE_NAME AS Nama_SP,
    ROUTINE_TYPE AS Tipe,
    CREATED AS Tanggal_Dibuat
FROM INFORMATION_SCHEMA.ROUTINES
WHERE ROUTINE_NAME LIKE 'SP_Jadwal%'
ORDER BY ROUTINE_NAME;
GO