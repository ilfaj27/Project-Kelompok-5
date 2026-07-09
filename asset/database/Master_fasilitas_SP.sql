
-- ============================================================
-- STORED PROCEDURES MASTER: Fasilitas Lapangan
-- Database: Hoopball
-- ============================================================

USE Hoopball;
GO

-- ============================================================
-- 1. SP: Get Fasilitas Detail (untuk AJAX Detail & Edit)
-- ============================================================
CREATE OR ALTER PROCEDURE dbo.sp_GetFasilitasDetail
    @ID_Fasilitas INT
AS
BEGIN
    SET NOCOUNT ON;

    SELECT 
        ID_Fasilitas,
        Nama_Fasilitas,
        Detail_Fasilitas,
        Stok_Total,
        Stok_Tersedia,
        Status,
        Is_Deleted,
        Created_By,
        Created_Date,
        Modified_By,
        Modified_Date
    FROM Fasilitas_Lapangan
    WHERE ID_Fasilitas = @ID_Fasilitas
      AND Is_Deleted = 0;
END;
GO

-- ============================================================
-- 2. SP: Check Fasilitas Duplicate (untuk validasi nama unik)
-- ============================================================
CREATE OR ALTER PROCEDURE dbo.sp_CheckFasilitasDuplicate
    @Nama_Fasilitas VARCHAR(25),
    @Exclude_ID INT = 0  -- 0 = tambah baru, >0 = edit mode (exclude diri sendiri)
AS
BEGIN
    SET NOCOUNT ON;

    SELECT COUNT(*) AS CountDuplicate
    FROM Fasilitas_Lapangan
    WHERE LOWER(Nama_Fasilitas) = LOWER(@Nama_Fasilitas)
      AND Is_Deleted = 0
      AND (@Exclude_ID = 0 OR ID_Fasilitas != @Exclude_ID);
END;
GO

-- ============================================================
-- 3. SP: Create Fasilitas Baru
-- ============================================================
CREATE OR ALTER PROCEDURE dbo.sp_CreateFasilitas
    @Nama_Fasilitas   VARCHAR(25),
    @Detail_Fasilitas VARCHAR(50),
    @Stok_Total       INT,
    @Created_By       VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;

    INSERT INTO Fasilitas_Lapangan
    (Nama_Fasilitas, Detail_Fasilitas, Stok_Total, Stok_Tersedia, Status, Is_Deleted, Created_By, Created_Date)
    VALUES
    (@Nama_Fasilitas, @Detail_Fasilitas, @Stok_Total, @Stok_Total, 1, 0, @Created_By, GETDATE());

    SELECT SCOPE_IDENTITY() AS New_ID_Fasilitas;
END;
GO

-- ============================================================
-- 4. SP: Update Fasilitas
-- ============================================================
CREATE OR ALTER PROCEDURE dbo.sp_UpdateFasilitas
    @ID_Fasilitas     INT,
    @Nama_Fasilitas   VARCHAR(25),
    @Detail_Fasilitas VARCHAR(50),
    @Stok_Total       INT,
    @Modified_By      VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE @Stok_Terpasang INT;
    DECLARE @Stok_Tersedia_Sekarang INT;

    -- Hitung stok yang sudah terpasang (digunakan di lapangan)
    SELECT @Stok_Terpasang = ISNULL(SUM(Jumlah_Digunakan), 0)
    FROM Detail_Lapangan_Fasilitas
    WHERE ID_Fasilitas = @ID_Fasilitas;

    -- Ambil stok tersedia saat ini
    SELECT @Stok_Tersedia_Sekarang = Stok_Tersedia
    FROM Fasilitas_Lapangan
    WHERE ID_Fasilitas = @ID_Fasilitas;

    -- Validasi: Stok baru tidak boleh kurang dari jumlah terpasang
    IF @Stok_Total < @Stok_Terpasang
    BEGIN
        RAISERROR('Stok baru tidak boleh kurang dari jumlah terpasang (%d unit)!', 16, 1, @Stok_Terpasang);
        RETURN;
    END

    -- Hitung selisih stok total lama vs baru
    DECLARE @Stok_Total_Lama INT;
    SELECT @Stok_Total_Lama = Stok_Total FROM Fasilitas_Lapangan WHERE ID_Fasilitas = @ID_Fasilitas;

    DECLARE @Selisih INT = @Stok_Total - @Stok_Total_Lama;

    -- Update data fasilitas
    UPDATE Fasilitas_Lapangan
    SET 
        Nama_Fasilitas   = @Nama_Fasilitas,
        Detail_Fasilitas = @Detail_Fasilitas,
        Stok_Total       = @Stok_Total,
        Stok_Tersedia    = @Stok_Tersedia_Sekarang + @Selisih, -- Sesuaikan stok tersedia
        Modified_By      = @Modified_By,
        Modified_Date    = GETDATE()
    WHERE ID_Fasilitas = @ID_Fasilitas;

    SELECT @@ROWCOUNT AS RowsAffected;
END;
GO

-- ============================================================
-- 5. SP: Update Status Fasilitas (Aktif/Nonaktif Toggle)
-- ============================================================
CREATE OR ALTER PROCEDURE dbo.sp_UpdateStatusFasilitas
    @ID_Fasilitas INT,
    @Status_Baru  INT,           -- 0 = Nonaktif, 1 = Aktif
    @Modified_By  VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;

    UPDATE Fasilitas_Lapangan
    SET 
        Status        = @Status_Baru,
        Modified_By   = @Modified_By,
        Modified_Date = GETDATE()
    WHERE ID_Fasilitas = @ID_Fasilitas
      AND Is_Deleted = 0;

    SELECT @@ROWCOUNT AS RowsAffected;
END;
GO

-- ============================================================
-- 6. SP: Soft Delete Fasilitas
-- ============================================================
CREATE OR ALTER PROCEDURE dbo.sp_DeleteFasilitas
    @ID_Fasilitas INT,
    @Deleted_By   VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;

    -- Cek apakah fasilitas masih digunakan di lapangan
    DECLARE @Count_Used INT;
    SELECT @Count_Used = COUNT(*) 
    FROM Detail_Lapangan_Fasilitas 
    WHERE ID_Fasilitas = @ID_Fasilitas;

    IF @Count_Used > 0
    BEGIN
        RAISERROR('Fasilitas tidak dapat dihapus karena masih digunakan di %d lapangan!', 16, 1, @Count_Used);
        RETURN;
    END

    -- Soft delete
    UPDATE Fasilitas_Lapangan
    SET 
        Is_Deleted  = 1,
        Deleted_By  = @Deleted_By,
        Deleted_Date = GETDATE()
    WHERE ID_Fasilitas = @ID_Fasilitas;

    SELECT @@ROWCOUNT AS RowsAffected;
END;
GO

-- ============================================================
-- 7. SP: Read Fasilitas List (terpaginasi dengan filter)
-- ============================================================
CREATE OR ALTER PROCEDURE dbo.sp_ReadFasilitasListWithCount
    @Filter_Lapangan VARCHAR(10) = 'all',   -- 'all' atau ID_Lapangan
    @Filter_Status   VARCHAR(10) = 'all',   -- 'all', '0', '1'
    @Sort_Order     VARCHAR(20) = 'nama_asc',
    @Offset         INT = 0,
    @Limit          INT = 10,
    @Search         VARCHAR(50) = ''
AS
BEGIN
    SET NOCOUNT ON;

    -- Hasil 1: Total Count
    SELECT COUNT(*) AS TotalCount
    FROM Fasilitas_Lapangan f
    WHERE f.Is_Deleted = 0
      AND (@Filter_Status = 'all' OR f.Status = CAST(@Filter_Status AS INT))
      AND (@Filter_Lapangan = 'all' 
           OR EXISTS (SELECT 1 FROM Detail_Lapangan_Fasilitas d 
                      WHERE d.ID_Fasilitas = f.ID_Fasilitas 
                        AND d.ID_Lapangan = CAST(@Filter_Lapangan AS INT)))
      AND (@Search = '' OR f.Nama_Fasilitas LIKE '%' + @Search + '%');

    -- Hasil 2: Data List
    SELECT 
        f.ID_Fasilitas,
        f.Nama_Fasilitas,
        f.Detail_Fasilitas,
        f.Stok_Total,
        f.Stok_Tersedia,
        f.Status,
        f.Created_By,
        f.Created_Date,
        f.Modified_By,
        f.Modified_Date
    FROM Fasilitas_Lapangan f
    WHERE f.Is_Deleted = 0
      AND (@Filter_Status = 'all' OR f.Status = CAST(@Filter_Status AS INT))
      AND (@Filter_Lapangan = 'all' 
           OR EXISTS (SELECT 1 FROM Detail_Lapangan_Fasilitas d 
                      WHERE d.ID_Fasilitas = f.ID_Fasilitas 
                        AND d.ID_Lapangan = CAST(@Filter_Lapangan AS INT)))
      AND (@Search = '' OR f.Nama_Fasilitas LIKE '%' + @Search + '%')
    ORDER BY 
        CASE @Sort_Order
            WHEN 'nama_asc'  THEN f.Nama_Fasilitas
        END ASC,
        CASE @Sort_Order
            WHEN 'nama_desc' THEN f.Nama_Fasilitas
        END DESC,
        CASE @Sort_Order
            WHEN 'stok_desc' THEN f.Stok_Total
        END DESC,
        CASE @Sort_Order
            WHEN 'stok_asc'  THEN f.Stok_Total
        END ASC
    OFFSET @Offset ROWS
    FETCH NEXT @Limit ROWS ONLY;
END;
GO

-- ============================================================
-- 8. SP: Get Active Lapangan List (untuk dropdown filter)
-- ============================================================
CREATE OR ALTER PROCEDURE dbo.sp_GetActiveLapanganList
AS
BEGIN
    SET NOCOUNT ON;

    SELECT 
        ID_Lapangan,
        Nama_Lapangan
    FROM Lapangan
    WHERE Status = 1
      AND Is_Deleted = 0
    ORDER BY Nama_Lapangan;
END;
GO

-- ============================================================
-- 9. UDF: Get Fasilitas Statistics (Total, Aktif, Nonaktif)
-- ============================================================
CREATE OR ALTER FUNCTION dbo.fn_GetFasilitasStats()
RETURNS TABLE
AS
RETURN
(
    SELECT 
        COUNT(*) AS Total,
        SUM(CASE WHEN Status = 1 THEN 1 ELSE 0 END) AS Aktif,
        SUM(CASE WHEN Status = 0 THEN 1 ELSE 0 END) AS Nonaktif
    FROM Fasilitas_Lapangan
    WHERE Is_Deleted = 0
);
GO

-- ============================================================
-- 10. SP: Restore Stok Fasilitas (saat jadwal selesai/dibatalkan)
-- ============================================================
CREATE OR ALTER PROCEDURE dbo.sp_RestoreStokFasilitas
    @ID_Fasilitas INT,
    @Jumlah_Restore INT,
    @Modified_By VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;

    UPDATE Fasilitas_Lapangan
    SET 
        Stok_Tersedia = Stok_Tersedia + @Jumlah_Restore,
        Modified_By   = @Modified_By,
        Modified_Date = GETDATE()
    WHERE ID_Fasilitas = @ID_Fasilitas;

    SELECT @@ROWCOUNT AS RowsAffected;
END;
GO

-- ============================================================
-- 11. SP: Use Stok Fasilitas (saat booking jadwal)
-- ============================================================
CREATE OR ALTER PROCEDURE dbo.sp_UseStokFasilitas
    @ID_Fasilitas INT,
    @Jumlah_Pakai INT,
    @Modified_By VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE @Stok_Tersedia INT;
    SELECT @Stok_Tersedia = Stok_Tersedia FROM Fasilitas_Lapangan WHERE ID_Fasilitas = @ID_Fasilitas;

    IF @Stok_Tersedia < @Jumlah_Pakai
    BEGIN
        RAISERROR('Stok tidak mencukupi! Tersedia: %d, Dibutuhkan: %d', 16, 1, @Stok_Tersedia, @Jumlah_Pakai);
        RETURN;
    END

    UPDATE Fasilitas_Lapangan
    SET 
        Stok_Tersedia = Stok_Tersedia - @Jumlah_Pakai,
        Modified_By   = @Modified_By,
        Modified_Date = GETDATE()
    WHERE ID_Fasilitas = @ID_Fasilitas;

    SELECT @@ROWCOUNT AS RowsAffected;
END;
GO

-- ============================================================
-- VERIFIKASI: Cek semua SP yang dibuat
-- ============================================================
SELECT 
    ROUTINE_NAME AS Nama_SP,
    ROUTINE_TYPE AS Tipe,
    CREATED AS Tanggal_Dibuat,
    LAST_ALTERED AS Terakhir_Diubah
FROM INFORMATION_SCHEMA.ROUTINES
WHERE ROUTINE_SCHEMA = 'dbo'
  AND ROUTINE_NAME LIKE '%Fasilitas%'
ORDER BY ROUTINE_NAME;
GO