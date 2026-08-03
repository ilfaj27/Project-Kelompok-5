
-- ============================================================
-- STORED PROCEDURES MASTER: Fasilitas Lapangan
-- Database: Hoopball
-- ============================================================

USE Hoopball;
GO

-- ============================================================
-- 1. SP: Get Fasilitas Detail (untuk AJAX Detail & Edit)
-- ============================================================
CREATE PROCEDURE dbo.sp_GetFasilitasDetail
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
CREATE PROCEDURE dbo.sp_CheckFasilitasDuplicate
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
CREATE PROCEDURE dbo.sp_CreateFasilitas
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
CREATE PROCEDURE dbo.sp_UpdateFasilitas
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
CREATE PROCEDURE dbo.sp_UpdateStatusFasilitas
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
CREATE PROCEDURE dbo.sp_DeleteFasilitas
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
USE Hoopball;
GO

CREATE PROCEDURE dbo.sp_ReadFasilitasListWithCount
   @Filter_Lapangan VARCHAR(10) = 'all',   
    @Filter_Status   VARCHAR(10) = 'all',   
    @Sort_Order     VARCHAR(20) = 'terbaru',
    @Offset         INT = 0,
    @Limit          INT = 10,
    @Search         VARCHAR(50) = ''
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE @Status_Int INT = NULL;
    IF @Filter_Status <> 'all' AND ISNUMERIC(@Filter_Status) = 1
    BEGIN
        SET @Status_Int = CAST(@Filter_Status AS INT);
    END

    DECLARE @Lapangan_Int INT = NULL;
    IF @Filter_Lapangan <> 'all' AND ISNUMERIC(@Filter_Lapangan) = 1
    BEGIN
        SET @Lapangan_Int = CAST(@Filter_Lapangan AS INT);
    END

    -- Total Count
    SELECT COUNT(*) AS TotalCount
    FROM Fasilitas_Lapangan f
    WHERE f.Is_Deleted = 0
      AND (@Status_Int IS NULL OR f.Status = @Status_Int)
      AND (@Lapangan_Int IS NULL 
           OR EXISTS (SELECT 1 FROM Detail_Lapangan_Fasilitas d 
                      WHERE d.ID_Fasilitas = f.ID_Fasilitas 
                        AND d.ID_Lapangan = @Lapangan_Int))
      AND (@Search = '' OR f.Nama_Fasilitas LIKE '%' + @Search + '%');

    -- Data List
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
      AND (@Status_Int IS NULL OR f.Status = @Status_Int)
      AND (@Lapangan_Int IS NULL 
           OR EXISTS (SELECT 1 FROM Detail_Lapangan_Fasilitas d 
                      WHERE d.ID_Fasilitas = f.ID_Fasilitas 
                        AND d.ID_Lapangan = @Lapangan_Int))
      AND (@Search = '' OR f.Nama_Fasilitas LIKE '%' + @Search + '%')
    ORDER BY 
        f.Status DESC, -- Status AKTIF (1) di atas, NONAKTIF (0) di paling bawah
        CASE @Sort_Order WHEN 'nama_asc'  THEN f.Nama_Fasilitas END ASC,
        CASE @Sort_Order WHEN 'nama_desc' THEN f.Nama_Fasilitas END DESC,
        CASE @Sort_Order WHEN 'stok_desc' THEN f.Stok_Total END DESC,
        CASE @Sort_Order WHEN 'stok_asc'  THEN f.Stok_Total END ASC,
        f.ID_Fasilitas DESC -- Default 'terbaru' (Data baru / ID terbesar di atas)
    OFFSET @Offset ROWS
    FETCH NEXT @Limit ROWS ONLY;
END;
GO

-- ============================================================
-- 8. SP: Get Active Lapangan List (untuk dropdown filter)
-- ============================================================
CREATE PROCEDURE dbo.sp_GetActiveLapanganList
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
-- 1. Pembuatan Tabel Log Riwayat Aktivitas Fasilitas Lapangan
-- ============================================================
CREATE TABLE Log_Fasilitas_Lapangan (
    Log_ID INT IDENTITY(1,1) PRIMARY KEY,
    ID_Fasilitas INT,
    Aksi VARCHAR(50),
    Nama_Fas_Lama VARCHAR(100),
    Nama_Fas_Baru VARCHAR(100),
    Stok_Total_Lama INT,
    Stok_Total_Baru INT,
    Waktu_Log DATETIME DEFAULT GETDATE(),
    Pengguna VARCHAR(100)
);
GO

-- ============================================================
-- 2. Pembuatan Trigger Log History untuk Fasilitas_Lapangan
-- ============================================================
CREATE TRIGGER trg_Fasilitas_Log
ON Fasilitas_Lapangan
AFTER INSERT, UPDATE
AS
BEGIN
    SET NOCOUNT ON;
    
    -- Operasi INSERT (Penambahan Data)
    IF EXISTS (SELECT * FROM inserted) AND NOT EXISTS(SELECT * FROM deleted)
    BEGIN
        INSERT INTO Log_Fasilitas_Lapangan 
            (ID_Fasilitas, Aksi, Nama_Fas_Lama, Nama_Fas_Baru, Stok_Total_Lama, Stok_Total_Baru, Pengguna)
        SELECT 
            ID_Fasilitas, 'INSERT', NULL, Nama_Fasilitas, NULL, Stok_Total, Created_By
        FROM inserted;
    END
    -- Operasi UPDATE atau SOFT DELETE (Perubahan/Penghapusan Data)
    ELSE IF EXISTS (SELECT * FROM inserted) AND EXISTS(SELECT * FROM deleted)
    BEGIN
        INSERT INTO Log_Fasilitas_Lapangan 
            (ID_Fasilitas, Aksi, Nama_Fas_Lama, Nama_Fas_Baru, Stok_Total_Lama, Stok_Total_Baru, Pengguna)
        SELECT 
            i.ID_Fasilitas, 
            CASE WHEN i.Is_Deleted = 1 THEN 'DELETE (SOFT)' ELSE 'UPDATE' END, 
            d.Nama_Fasilitas, 
            i.Nama_Fasilitas, 
            d.Stok_Total, 
            i.Stok_Total, 
            COALESCE(i.Modified_By, i.Deleted_By, 'SYSTEM')
        FROM inserted i
        INNER JOIN deleted d ON i.ID_Fasilitas = d.ID_Fasilitas;
    END
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