-- ============================================================
-- Update_Alat_Kategori_Size.sql (FIXED - COMPLETE)
-- UPDATE MASTER ALAT: Kategori + Stok per Ukuran (Size)
-- ============================================================
-- Jalankan SETELAH database Hoopball + Master_Alat_UDF_SP.sql
-- ============================================================

USE Hoopball;
GO

-- ============================================================
-- 1. KOLOM BARU: Kategori di tabel Alat
-- ============================================================
IF COL_LENGTH('Alat', 'Kategori') IS NULL
BEGIN
    ALTER TABLE Alat
    ADD Kategori VARCHAR(20) NOT NULL
        CONSTRAINT DF_Alat_Kategori DEFAULT 'Lainnya';
END
GO

IF OBJECT_ID('CK_Alat_Kategori', 'C') IS NULL
BEGIN
    ALTER TABLE Alat
    ADD CONSTRAINT CK_Alat_Kategori
    CHECK (Kategori IN ('Baju','Celana','Bola Basket','Sepatu','Headband','Kaos Kaki','Lainnya'));
END
GO

-- ============================================================
-- 2. TABEL BARU: Alat_Size (stok per ukuran)
-- ============================================================
IF OBJECT_ID('Alat_Size', 'U') IS NULL
BEGIN
    CREATE TABLE Alat_Size (
        ID_Alat_Size    INT IDENTITY(1,1) PRIMARY KEY,
        ID_Alat         INT             NOT NULL,
        Ukuran          VARCHAR(15)     NOT NULL,
        Stok            INT             NOT NULL,
        FOREIGN KEY (ID_Alat) REFERENCES Alat(ID_Alat),
        CONSTRAINT UQ_AlatSize_Alat_Ukuran UNIQUE (ID_Alat, Ukuran),
        CONSTRAINT CK_AlatSize_Stok CHECK (Stok >= 0)
    );
END
GO

-- ============================================================
-- 3. SEED DATA LAMA
-- ============================================================
UPDATE Alat
SET Kategori = CASE
    WHEN Nama_Alat LIKE '%Kaos Kaki%'   THEN 'Kaos Kaki'
    WHEN Nama_Alat LIKE '%Headband%'    THEN 'Headband'
    WHEN Nama_Alat LIKE '%Sepatu%'      THEN 'Sepatu'
    WHEN Nama_Alat LIKE '%Bola Basket%' THEN 'Bola Basket'
    WHEN Nama_Alat LIKE '%Jersey%'
      OR Nama_Alat LIKE '%Baju%'        THEN 'Baju'
    WHEN Nama_Alat LIKE '%Celana%'      THEN 'Celana'
    ELSE 'Lainnya'
END
WHERE Kategori = 'Lainnya';
GO

INSERT INTO Alat_Size (ID_Alat, Ukuran, Stok)
SELECT a.ID_Alat, 'All Size', a.Stok
FROM Alat a
WHERE NOT EXISTS (SELECT 1 FROM Alat_Size s WHERE s.ID_Alat = a.ID_Alat);
GO

-- ============================================================
-- 4. SP: Alat_Count (untuk pagination)
-- ============================================================
IF OBJECT_ID('SP_Alat_Count', 'P') IS NOT NULL DROP PROCEDURE SP_Alat_Count;
GO
CREATE PROCEDURE SP_Alat_Count
    @StatusFilter INT = NULL
AS
BEGIN
    SET NOCOUNT ON;

    SELECT COUNT(*) AS TotalCount
    FROM Alat
    WHERE Is_Deleted = 0
      AND (@StatusFilter IS NULL OR Status = @StatusFilter);
END
GO

-- ============================================================
-- 5. SP: Alat_CountByStatus (untuk statistik dashboard)
-- ============================================================
IF OBJECT_ID('SP_Alat_CountByStatus', 'P') IS NOT NULL DROP PROCEDURE SP_Alat_CountByStatus;
GO
CREATE PROCEDURE SP_Alat_CountByStatus
AS
BEGIN
    SET NOCOUNT ON;

    SELECT
        SUM(CASE WHEN Status = 1 THEN 1 ELSE 0 END) AS AktifCount,
        SUM(CASE WHEN Status = 0 THEN 1 ELSE 0 END) AS NonaktifCount,
        COUNT(*) AS TotalCount
    FROM Alat
    WHERE Is_Deleted = 0;
END
GO

-- ============================================================
-- 6. SP: Alat_CheckDuplicate (untuk validasi nama alat)
-- ============================================================
IF OBJECT_ID('SP_Alat_CheckDuplicate', 'P') IS NOT NULL DROP PROCEDURE SP_Alat_CheckDuplicate;
GO
CREATE PROCEDURE SP_Alat_CheckDuplicate
    @Nama_Alat  VARCHAR(25),
    @ExcludeID  INT = NULL
AS
BEGIN
    SET NOCOUNT ON;

    SELECT TOP 1 ID_Alat
    FROM Alat
    WHERE Nama_Alat = @Nama_Alat
      AND Is_Deleted = 0
      AND (@ExcludeID IS NULL OR ID_Alat <> @ExcludeID);
END
GO

-- ============================================================
-- 7. SP: Alat_Delete (soft delete)
-- ============================================================
IF OBJECT_ID('SP_Alat_Delete', 'P') IS NOT NULL DROP PROCEDURE SP_Alat_Delete;
GO
CREATE PROCEDURE SP_Alat_Delete
    @ID_Alat     INT,
    @Deleted_By  VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;

    IF NOT EXISTS (SELECT 1 FROM Alat WHERE ID_Alat = @ID_Alat AND Is_Deleted = 0)
    BEGIN
        RAISERROR('Data Alat tidak ditemukan!', 16, 1);
        RETURN;
    END

    UPDATE Alat
    SET Is_Deleted = 1,
        Deleted_By = @Deleted_By,
        Deleted_Date = GETDATE()
    WHERE ID_Alat = @ID_Alat;
END
GO

-- ============================================================
-- 8. SP LAMA (FIXED: backward compatibility @Harga_Alat)
-- ============================================================

-- --------------------------------------------------------
-- SP_Alat_Insert: + @Kategori, return ID baru
-- NOTE: @Harga_Alat di-map ke Harga_Jual (harga jual)
--       @Harga_Beli otomatis = @Harga_Alat (default)
-- --------------------------------------------------------
IF OBJECT_ID('SP_Alat_Insert', 'P') IS NOT NULL DROP PROCEDURE SP_Alat_Insert;
GO
CREATE PROCEDURE SP_Alat_Insert
    @Nama_Alat   VARCHAR(25),
    @Stok        INT,
    @Harga_Alat  DECIMAL(18,2),      -- backward compatibility: di-map ke Harga_Jual
    @Photo_Alat  VARCHAR(255) = NULL,
    @Status      INT,
    @Created_By  VARCHAR(50),
    @Kategori    VARCHAR(20) = 'Lainnya'
AS
BEGIN
    SET NOCOUNT ON;

    IF @Stok < 0
    BEGIN
        RAISERROR('Stok tidak boleh negatif!', 16, 1);
        RETURN;
    END

    IF @Harga_Alat < 0
    BEGIN
        RAISERROR('Harga tidak boleh negatif!', 16, 1);
        RETURN;
    END

    INSERT INTO Alat
    (Nama_Alat, Stok, Harga_Beli, Harga_Jual, Photo_Alat, Status, Kategori, Is_Deleted, Created_By, Created_Date)
    VALUES
    (@Nama_Alat, @Stok, @Harga_Alat, @Harga_Alat, @Photo_Alat, @Status, @Kategori, 0, @Created_By, GETDATE());

    SELECT SCOPE_IDENTITY() AS New_ID_Alat;
END
GO

-- --------------------------------------------------------
-- SP_Alat_Update: + @Kategori
-- NOTE: @Harga_Alat di-map ke Harga_Jual (harga jual)
-- --------------------------------------------------------
IF OBJECT_ID('SP_Alat_Update', 'P') IS NOT NULL DROP PROCEDURE SP_Alat_Update;
GO
CREATE PROCEDURE SP_Alat_Update
    @ID_Alat     INT,
    @Nama_Alat   VARCHAR(25) = NULL,
    @Stok        INT = NULL,
    @Harga_Alat  DECIMAL(18,2) = NULL,  -- backward compatibility: di-map ke Harga_Jual
    @Photo_Alat  VARCHAR(255) = NULL,
    @Status      INT = NULL,
    @Modified_By VARCHAR(50),
    @Kategori    VARCHAR(20) = NULL
AS
BEGIN
    SET NOCOUNT ON;

    IF NOT EXISTS (SELECT 1 FROM Alat WHERE ID_Alat = @ID_Alat AND Is_Deleted = 0)
    BEGIN
        RAISERROR('Data Alat tidak ditemukan!', 16, 1);
        RETURN;
    END

    IF @Stok IS NOT NULL AND @Stok < 0
    BEGIN
        RAISERROR('Stok tidak boleh negatif!', 16, 1);
        RETURN;
    END

    IF @Harga_Alat IS NOT NULL AND @Harga_Alat < 0
    BEGIN
        RAISERROR('Harga tidak boleh negatif!', 16, 1);
        RETURN;
    END

    UPDATE Alat
    SET Nama_Alat   = COALESCE(@Nama_Alat, Nama_Alat),
        Stok        = COALESCE(@Stok, Stok),
        Harga_Beli  = COALESCE(@Harga_Alat, Harga_Beli),
        Harga_Jual  = COALESCE(@Harga_Alat, Harga_Jual),
        Photo_Alat  = COALESCE(@Photo_Alat, Photo_Alat),
        Status      = COALESCE(@Status, Status),
        Kategori    = COALESCE(@Kategori, Kategori),
        Modified_By = @Modified_By,
        Modified_Date = GETDATE()
    WHERE ID_Alat = @ID_Alat;
END
GO

-- --------------------------------------------------------
-- SP_Alat_Select: + kolom Kategori, alias Harga_Alat
-- --------------------------------------------------------
IF OBJECT_ID('SP_Alat_Select', 'P') IS NOT NULL DROP PROCEDURE SP_Alat_Select;
GO
CREATE PROCEDURE SP_Alat_Select
    @ID_Alat INT = NULL
AS
BEGIN
    SET NOCOUNT ON;

    SELECT ID_Alat, Nama_Alat, Kategori, Stok,
           Harga_Jual AS Harga_Alat,  -- alias untuk backward compatibility PHP
           Harga_Beli, Harga_Jual, Photo_Alat,
           Status, Is_Deleted, Created_By, Created_Date,
           Modified_By, Modified_Date, Deleted_By, Deleted_Date
    FROM Alat
    WHERE (@ID_Alat IS NULL OR ID_Alat = @ID_Alat)
      AND Is_Deleted = 0
    ORDER BY Nama_Alat;
END
GO

-- --------------------------------------------------------
-- SP_Alat_SelectFiltered: + kolom Kategori, alias Harga_Alat
-- --------------------------------------------------------
IF OBJECT_ID('SP_Alat_SelectFiltered', 'P') IS NOT NULL DROP PROCEDURE SP_Alat_SelectFiltered;
GO
CREATE PROCEDURE SP_Alat_SelectFiltered
    @StatusFilter    INT = NULL,
    @SortBy          VARCHAR(20) = 'nama_asc',
    @PageNumber      INT = 1,
    @PageSize        INT = 12
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE @Offset INT = (@PageNumber - 1) * @PageSize;
    DECLARE @SortSQL NVARCHAR(100);
    DECLARE @SQL NVARCHAR(MAX);

    SET @SortSQL = CASE @SortBy
        WHEN 'stok_desc'  THEN 'Stok DESC'
        WHEN 'harga_desc' THEN 'Harga_Jual DESC'  -- mapped from harga_desc
        WHEN 'harga_asc'  THEN 'Harga_Jual ASC'   -- mapped from harga_asc
        WHEN 'hargabeli_desc' THEN 'Harga_Beli DESC'
        WHEN 'hargabeli_asc'  THEN 'Harga_Beli ASC'
        WHEN 'hargajual_desc' THEN 'Harga_Jual DESC'
        WHEN 'hargajual_asc'  THEN 'Harga_Jual ASC'
        ELSE 'Nama_Alat ASC'
    END;

    SET @SQL = N'
        SELECT ID_Alat, Nama_Alat, Kategori, Stok,
               Harga_Jual AS Harga_Alat,  -- alias untuk backward compatibility PHP
               Harga_Beli, Harga_Jual, Photo_Alat,
               Status, Is_Deleted, Created_By, Created_Date,
               Modified_By, Modified_Date, Deleted_By, Deleted_Date
        FROM Alat
        WHERE Is_Deleted = 0
          AND (@StatusFilter IS NULL OR Status = @StatusFilter)
        ORDER BY ' + @SortSQL + '
        OFFSET @Offset ROWS FETCH NEXT @PageSize ROWS ONLY;';

    EXEC sp_executesql @SQL,
        N'@StatusFilter INT, @Offset INT, @PageSize INT',
        @StatusFilter, @Offset, @PageSize;
END
GO

-- ============================================================
-- 9. SP BARU: Alat_Size
-- ============================================================

-- --------------------------------------------------------
-- SP_AlatSize_SelectByAlat: ambil stok per ukuran 1 alat
-- --------------------------------------------------------
IF OBJECT_ID('SP_AlatSize_SelectByAlat', 'P') IS NOT NULL DROP PROCEDURE SP_AlatSize_SelectByAlat;
GO
CREATE PROCEDURE SP_AlatSize_SelectByAlat
    @ID_Alat INT
AS
BEGIN
    SET NOCOUNT ON;

    SELECT ID_Alat_Size, ID_Alat, Ukuran, Stok
    FROM Alat_Size
    WHERE ID_Alat = @ID_Alat
    ORDER BY ID_Alat_Size;
END
GO

-- --------------------------------------------------------
-- SP_AlatSize_DeleteByAlat: hapus semua ukuran 1 alat
-- --------------------------------------------------------
IF OBJECT_ID('SP_AlatSize_DeleteByAlat', 'P') IS NOT NULL DROP PROCEDURE SP_AlatSize_DeleteByAlat;
GO
CREATE PROCEDURE SP_AlatSize_DeleteByAlat
    @ID_Alat INT
AS
BEGIN
    SET NOCOUNT ON;

    DELETE FROM Alat_Size WHERE ID_Alat = @ID_Alat;
END
GO

-- --------------------------------------------------------
-- SP_AlatSize_Insert: tambah 1 baris ukuran + stok
-- --------------------------------------------------------
IF OBJECT_ID('SP_AlatSize_Insert', 'P') IS NOT NULL DROP PROCEDURE SP_AlatSize_Insert;
GO
CREATE PROCEDURE SP_AlatSize_Insert
    @ID_Alat INT,
    @Ukuran  VARCHAR(15),
    @Stok    INT
AS
BEGIN
    SET NOCOUNT ON;

    IF @Stok < 0
    BEGIN
        RAISERROR('Stok per ukuran tidak boleh negatif!', 16, 1);
        RETURN;
    END

    IF NOT EXISTS (SELECT 1 FROM Alat WHERE ID_Alat = @ID_Alat AND Is_Deleted = 0)
    BEGIN
        RAISERROR('Data Alat tidak ditemukan!', 16, 1);
        RETURN;
    END

    INSERT INTO Alat_Size (ID_Alat, Ukuran, Stok)
    VALUES (@ID_Alat, @Ukuran, @Stok);
END
GO

-- ============================================================
-- CONTOH PENGGUNAAN
-- ============================================================
/*
-- Insert alat baru dengan kategori
EXEC SP_Alat_Insert
    @Nama_Alat = 'Jersey Home 2026',
    @Stok = 30,
    @Harga_Alat = 150000,
    @Photo_Alat = 'asset/image/jersey.jpg',
    @Status = 1,
    @Created_By = 'SYSTEM',
    @Kategori = 'Baju';

-- Insert stok per ukuran
EXEC SP_AlatSize_Insert @ID_Alat = 21, @Ukuran = 'S',  @Stok = 5;
EXEC SP_AlatSize_Insert @ID_Alat = 21, @Ukuran = 'M',  @Stok = 10;
EXEC SP_AlatSize_Insert @ID_Alat = 21, @Ukuran = 'L',  @Stok = 10;
EXEC SP_AlatSize_Insert @ID_Alat = 21, @Ukuran = 'XL', @Stok = 5;

-- Lihat stok per ukuran
EXEC SP_AlatSize_SelectByAlat @ID_Alat = 21;
*/
GO

-- ============================================================
-- Update_DetailBeli_Ukuran.sql (FIXED)
-- ============================================================

USE Hoopball;
GO

-- ------------------------------------------------------------
-- 1. Tambah kolom Ukuran (nullable, default 'All Size')
-- ------------------------------------------------------------
IF COL_LENGTH('Detail_Beli_Alat', 'Ukuran') IS NULL
BEGIN
    ALTER TABLE Detail_Beli_Alat
    ADD Ukuran VARCHAR(15) NULL
        CONSTRAINT DF_DetailBeli_Ukuran DEFAULT 'All Size';
END
GO

-- ------------------------------------------------------------
-- 2. Backfill data lama
-- ------------------------------------------------------------
UPDATE Detail_Beli_Alat
SET Ukuran = 'All Size'
WHERE Ukuran IS NULL;
GO