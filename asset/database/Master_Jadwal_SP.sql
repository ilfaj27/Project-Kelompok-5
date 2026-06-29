-- ============================================================
-- SP: CRUD JADWAL
-- ============================================================

-- 7.1 CREATE Jadwal
CREATE OR ALTER PROCEDURE SP_Jadwal_Insert
    @ID_Lapangan   INT,
    @Tanggal       DATE,
    @Jam_Mulai     TIME,
    @Jam_Selesai   TIME,
    @Status        INT,
    @Created_By    VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;
    
    IF NOT EXISTS (SELECT 1 FROM Lapangan WHERE ID_Lapangan = @ID_Lapangan AND Is_Deleted = 0)
    BEGIN
        RAISERROR('Lapangan tidak ditemukan!', 16, 1);
        RETURN;
    END
    
        IF @Jam_Selesai <> '00:00' AND @Jam_Mulai >= @Jam_Selesai
    BEGIN
        RAISERROR('Jam mulai harus lebih kecil dari jam selesai!', 16, 1);
        RETURN;
    END
    
    DECLARE @JamSelesaiCompare VARCHAR(5) = CASE WHEN @Jam_Selesai = '00:00' THEN '24:00' ELSE CONVERT(VARCHAR(5), @Jam_Selesai, 108) END;
    
    IF EXISTS (SELECT 1 FROM Jadwal 
               WHERE ID_Lapangan = @ID_Lapangan 
                 AND Tanggal = @Tanggal 
                 AND Is_Deleted = 0
                 AND ID_Jadwal <> @ID_Jadwal
                 AND NOT (
                     (CASE WHEN Jam_Selesai = '00:00' THEN '24:00' ELSE CONVERT(VARCHAR(5), Jam_Selesai, 108) END) <= CONVERT(VARCHAR(5), @Jam_Mulai, 108)
                     OR CONVERT(VARCHAR(5), Jam_Mulai, 108) >= @JamSelesaiCompare
                 ))
    BEGIN
        RAISERROR('Jadwal bentrok! Sudah ada jadwal di waktu yang sama.', 16, 1);
        RETURN;
    END

    INSERT INTO Jadwal 
    (ID_Lapangan, Tanggal, Jam_Mulai, Jam_Selesai, Status, Is_Deleted, Created_By, Created_Date)
    VALUES 
    (@ID_Lapangan, @Tanggal, @Jam_Mulai, @Jam_Selesai, @Status, 0, @Created_By, GETDATE());
END
GO


-- 7.2 READ Jadwal
CREATE OR ALTER PROCEDURE SP_Jadwal_Select
    @ID_Jadwal   INT = NULL,
    @ID_Lapangan INT = NULL,
    @Tanggal     DATE = NULL,
    @Tersedia    BIT = NULL  -- NULL = semua, 1 = tersedia, 0 = tidak tersedia
AS
BEGIN
    SET NOCOUNT ON;
    
    SELECT j.ID_Jadwal, j.ID_Lapangan, l.Nama_Lapangan, j.Tanggal, 
           j.Jam_Mulai, j.Jam_Selesai, j.Status, j.Is_Deleted,
           j.Created_By, j.Created_Date, j.Modified_By, j.Modified_Date,
           j.Deleted_By, j.Deleted_Date
    FROM Jadwal j
    JOIN Lapangan l ON j.ID_Lapangan = l.ID_Lapangan
    WHERE (@ID_Jadwal IS NULL OR j.ID_Jadwal = @ID_Jadwal)
      AND (@ID_Lapangan IS NULL OR j.ID_Lapangan = @ID_Lapangan)
      AND (@Tanggal IS NULL OR j.Tanggal = @Tanggal)
      AND (@Tersedia IS NULL OR j.Status = @Tersedia)
      AND j.Is_Deleted = 0
      AND l.Is_Deleted = 0
    ORDER BY j.Tanggal, j.Jam_Mulai;
END
GO


-- 7.3 UPDATE Jadwal
CREATE OR ALTER PROCEDURE SP_Jadwal_Update
    @ID_Jadwal     INT,
    @ID_Lapangan   INT = NULL,
    @Tanggal       DATE = NULL,
    @Jam_Mulai     TIME = NULL,
    @Jam_Selesai   TIME = NULL,
    @Status        INT = NULL,
    @Modified_By   VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;
    
    IF NOT EXISTS (SELECT 1 FROM Jadwal WHERE ID_Jadwal = @ID_Jadwal AND Is_Deleted = 0)
    BEGIN
        RAISERROR('Data Jadwal tidak ditemukan!', 16, 1);
        RETURN;
    END

    DECLARE @CurrLapangan INT = (SELECT ID_Lapangan FROM Jadwal WHERE ID_Jadwal = @ID_Jadwal);
    DECLARE @CurrTanggal DATE = (SELECT Tanggal FROM Jadwal WHERE ID_Jadwal = @ID_Jadwal);
    DECLARE @CurrMulai TIME = (SELECT Jam_Mulai FROM Jadwal WHERE ID_Jadwal = @ID_Jadwal);
    DECLARE @CurrSelesai TIME = (SELECT Jam_Selesai FROM Jadwal WHERE ID_Jadwal = @ID_Jadwal);
    
    DECLARE @NewLapangan INT = COALESCE(@ID_Lapangan, @CurrLapangan);
    DECLARE @NewTanggal DATE = COALESCE(@Tanggal, @CurrTanggal);
    DECLARE @NewMulai TIME = COALESCE(@Jam_Mulai, @CurrMulai);
    DECLARE @NewSelesai TIME = COALESCE(@Jam_Selesai, @CurrSelesai);

        IF @NewSelesai <> '00:00' AND @NewMulai >= @NewSelesai
    BEGIN
        RAISERROR('Jam mulai harus lebih kecil dari jam selesai!', 16, 1);
        RETURN;
    END
    
    DECLARE @NewMulaiStr VARCHAR(5) = CONVERT(VARCHAR(5), @NewMulai, 108);
    DECLARE @NewSelesaiStr VARCHAR(5) = CASE WHEN @NewSelesai = '00:00' THEN '24:00' ELSE CONVERT(VARCHAR(5), @NewSelesai, 108) END;
    
    IF EXISTS (SELECT 1 FROM Jadwal 
               WHERE ID_Lapangan = @NewLapangan 
                 AND Tanggal = @NewTanggal 
                 AND ID_Jadwal <> @ID_Jadwal
                 AND Is_Deleted = 0
                 AND NOT (
                     (CASE WHEN Jam_Selesai = '00:00' THEN '24:00' ELSE CONVERT(VARCHAR(5), Jam_Selesai, 108) END) <= @NewMulaiStr
                     OR CONVERT(VARCHAR(5), Jam_Mulai, 108) >= @NewSelesaiStr
                 ))
    BEGIN
        RAISERROR('Jadwal bentrok! Sudah ada jadwal di waktu yang sama.', 16, 1);
        RETURN;
    END

    UPDATE Jadwal
    SET ID_Lapangan = @NewLapangan,
        Tanggal     = @NewTanggal,
        Jam_Mulai   = @NewMulai,
        Jam_Selesai = @NewSelesai,
        Status      = COALESCE(@Status, Status),
        Modified_By = @Modified_By,
        Modified_Date = GETDATE()
    WHERE ID_Jadwal = @ID_Jadwal;
END
GO

-- 7.4 DELETE Jadwal (Soft Delete)
CREATE OR ALTER PROCEDURE SP_Jadwal_Delete
    @ID_Jadwal   INT,
    @Deleted_By  VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;
    
    IF NOT EXISTS (SELECT 1 FROM Jadwal WHERE ID_Jadwal = @ID_Jadwal AND Is_Deleted = 0)
    BEGIN
        RAISERROR('Data Jadwal tidak ditemukan!', 16, 1);
        RETURN;
    END

    UPDATE Jadwal
    SET Is_Deleted  = 1,
        Deleted_By  = @Deleted_By,
        Deleted_Date = GETDATE()
    WHERE ID_Jadwal = @ID_Jadwal;
END
GO


CREATE OR ALTER PROCEDURE SP_Jadwal_SelectFiltered
    @StatusFilter    INT = NULL,
    @LapanganFilter  INT = NULL,
    @TanggalFilter   DATE = NULL,
    @SortBy          VARCHAR(20) = 'tanggal_desc',
    @PageNumber      INT = 1,
    @PageSize        INT = 10
AS
BEGIN
    SET NOCOUNT ON;
    
    DECLARE @Offset INT = (@PageNumber - 1) * @PageSize;
    DECLARE @SortSQL NVARCHAR(100);
    DECLARE @SQL NVARCHAR(MAX);
    
    SET @SortSQL = CASE @SortBy
        WHEN 'lapangan_asc' THEN 'l.Nama_Lapangan ASC'
        ELSE 'j.Tanggal ASC, j.Jam_Mulai ASC'
    END;
    
    SET @SQL = N'
        SELECT j.ID_Jadwal, j.ID_Lapangan, l.Nama_Lapangan, j.Tanggal,
               j.Jam_Mulai, j.Jam_Selesai, j.Status, j.Is_Deleted,
               j.Created_By, j.Created_Date, j.Modified_By, j.Modified_Date,
               j.Deleted_By, j.Deleted_Date
        FROM Jadwal j
        JOIN Lapangan l ON j.ID_Lapangan = l.ID_Lapangan
        WHERE j.Is_Deleted = 0
          AND l.Is_Deleted = 0
          AND (@StatusFilter IS NULL OR j.Status = @StatusFilter)
          AND (@LapanganFilter IS NULL OR j.ID_Lapangan = @LapanganFilter)
          AND (@TanggalFilter IS NULL OR j.Tanggal = @TanggalFilter)
        ORDER BY ' + @SortSQL + '
        OFFSET @Offset ROWS FETCH NEXT @PageSize ROWS ONLY;';
    
    EXEC sp_executesql @SQL,
        N'@StatusFilter INT, @LapanganFilter INT, @TanggalFilter DATE, @Offset INT, @PageSize INT',
        @StatusFilter, @LapanganFilter, @TanggalFilter, @Offset, @PageSize;
END
GO

CREATE OR ALTER PROCEDURE SP_Jadwal_Count
    @StatusFilter   INT = NULL,
    @LapanganFilter INT = NULL,
    @TanggalFilter  DATE = NULL
AS
BEGIN
    SET NOCOUNT ON;
    
    SELECT COUNT(*) AS t
    FROM Jadwal j
    JOIN Lapangan l ON j.ID_Lapangan = l.ID_Lapangan
    WHERE j.Is_Deleted = 0
      AND l.Is_Deleted = 0
      AND (@StatusFilter IS NULL OR j.Status = @StatusFilter)
      AND (@LapanganFilter IS NULL OR j.ID_Lapangan = @LapanganFilter)
      AND (@TanggalFilter IS NULL OR j.Tanggal = @TanggalFilter);
END
GO