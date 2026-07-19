-- ============================================================================
-- Master_Alat_SP.sql
-- HOOPBALL - MASTER ALAT: SEMUA STORED PROCEDURE
-- ============================================================================
-- Isi file ini CUMA stored procedure. Struktur tabel (kolom Kategori,
-- tabel Alat_Size) ada di file terpisah: Master_Alat_DATABASE.sql.
--
-- WAJIB jalankan Master_Alat_DATABASE.sql DULU sebelum file ini, karena
-- beberapa SP di bawah pakai kolom Kategori & tabel Alat_Size.
--
-- Semua pakai CREATE OR ALTER, jadi aman dijalankan ulang kapan saja.
--
-- Isi:
--   PART 1  - SP untuk tabel Alat
--             (Insert, Update, Select, SelectFiltered, CheckDuplicate,
--              Delete, Count, CountByStatus)
--   PART 2  - SP untuk tabel Alat_Size
--             (Insert, DeleteByAlat, SelectByAlat)
--   PART 3  - Verifikasi
--   PART 4  - Contoh penggunaan
-- ============================================================================

USE Hoopball;
GO

-- ============================================================================
-- PART 1: STORED PROCEDURE - Alat
-- ============================================================================

-- --------------------------------------------------------
-- SP_Alat_Insert
-- --------------------------------------------------------
CREATE OR ALTER PROCEDURE SP_Alat_Insert
    @Nama_Alat   VARCHAR(25),
    @Stok        INT,
    @Harga_Beli  DECIMAL(18,2),
    @Harga_Jual  DECIMAL(18,2),
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

    IF @Harga_Beli < 0 OR @Harga_Jual < 0
    BEGIN
        RAISERROR('Harga beli/jual tidak boleh negatif!', 16, 1);
        RETURN;
    END

    IF @Harga_Jual < @Harga_Beli
    BEGIN
        RAISERROR('Harga jual tidak boleh lebih kecil dari harga beli!', 16, 1);
        RETURN;
    END

    INSERT INTO Alat
    (Nama_Alat, Stok, Harga_Beli, Harga_Jual, Photo_Alat, Status, Kategori, Is_Deleted, Created_By, Created_Date)
    VALUES
    (@Nama_Alat, @Stok, @Harga_Beli, @Harga_Jual, @Photo_Alat, @Status, @Kategori, 0, @Created_By, GETDATE());

    -- Return ID alat yang baru dibuat (dipakai PHP untuk insert Alat_Size)
    SELECT SCOPE_IDENTITY() AS New_ID_Alat;
END
GO

-- --------------------------------------------------------
-- SP_Alat_Update
-- --------------------------------------------------------
CREATE OR ALTER PROCEDURE SP_Alat_Update
    @ID_Alat     INT,
    @Nama_Alat   VARCHAR(25) = NULL,
    @Stok        INT = NULL,
    @Harga_Beli  DECIMAL(18,2) = NULL,
    @Harga_Jual  DECIMAL(18,2) = NULL,
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

    IF (@Harga_Beli IS NOT NULL AND @Harga_Beli < 0)
       OR (@Harga_Jual IS NOT NULL AND @Harga_Jual < 0)
    BEGIN
        RAISERROR('Harga beli/jual tidak boleh negatif!', 16, 1);
        RETURN;
    END

    -- Cek harga jual vs harga beli pakai nilai baru (kalau dikirim) atau nilai lama
    IF EXISTS (
        SELECT 1 FROM Alat
        WHERE ID_Alat = @ID_Alat
          AND COALESCE(@Harga_Jual, Harga_Jual) < COALESCE(@Harga_Beli, Harga_Beli)
    )
    BEGIN
        RAISERROR('Harga jual tidak boleh lebih kecil dari harga beli!', 16, 1);
        RETURN;
    END

    UPDATE Alat
    SET Nama_Alat   = COALESCE(@Nama_Alat, Nama_Alat),
        Stok        = COALESCE(@Stok, Stok),
        Harga_Beli  = COALESCE(@Harga_Beli, Harga_Beli),
        Harga_Jual  = COALESCE(@Harga_Jual, Harga_Jual),
        Photo_Alat  = COALESCE(@Photo_Alat, Photo_Alat),
        Status      = COALESCE(@Status, Status),
        Kategori    = COALESCE(@Kategori, Kategori),
        Modified_By = @Modified_By,
        Modified_Date = GETDATE()
    WHERE ID_Alat = @ID_Alat;
END
GO

-- --------------------------------------------------------
-- SP_Alat_Select (single / all, dipakai utk edit_id & detail_id juga)
-- --------------------------------------------------------
CREATE OR ALTER PROCEDURE SP_Alat_Select
    @ID_Alat INT = NULL
AS
BEGIN
    SET NOCOUNT ON;

    SELECT ID_Alat, Nama_Alat, Kategori, Stok, Harga_Beli, Harga_Jual, Photo_Alat,
           Status, Is_Deleted, Created_By, Created_Date,
           Modified_By, Modified_Date, Deleted_By, Deleted_Date
    FROM Alat
    WHERE (@ID_Alat IS NULL OR ID_Alat = @ID_Alat)
      AND Is_Deleted = 0
    ORDER BY Nama_Alat;
END
GO

-- --------------------------------------------------------
-- SP_Alat_SelectFiltered (grid utama dengan paging + sort)
-- --------------------------------------------------------
CREATE OR ALTER PROCEDURE SP_Alat_SelectFiltered
    @StatusFilter    INT = NULL,
    @KategoriFilter  VARCHAR(50) = NULL,
    @Search          VARCHAR(100) = NULL,
    @SortBy          VARCHAR(20) = 'nama_asc',
    @PageNumber      INT = 1,
    @PageSize        INT = 12
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE @Offset INT = (@PageNumber - 1) * @PageSize;
    DECLARE @SortSQL NVARCHAR(100);
    DECLARE @SQL NVARCHAR(MAX);

    -- Tentukan Sorting
    SET @SortSQL = CASE @SortBy
        WHEN 'stok_desc'        THEN 'Stok DESC'
        WHEN 'harga_jual_desc'  THEN 'Harga_Jual DESC'
        WHEN 'harga_jual_asc'   THEN 'Harga_Jual ASC'
        WHEN 'harga_beli_desc'  THEN 'Harga_Beli DESC'
        WHEN 'harga_beli_asc'   THEN 'Harga_Beli ASC'
        ELSE 'Nama_Alat ASC'
    END;

    -- Dynamic SQL supaya pencarian lebih fleksibel
    SET @SQL = N'
        SELECT ID_Alat, Nama_Alat, Kategori, Stok, Harga_Beli, Harga_Jual, Photo_Alat,
               Status, Is_Deleted, Created_By, Created_Date,
               Modified_By, Modified_Date, Deleted_By, Deleted_Date
        FROM Alat
        WHERE Is_Deleted = 0
          AND (@StatusFilter IS NULL OR Status = @StatusFilter)
          AND (@KategoriFilter IS NULL OR @KategoriFilter = '''' OR Kategori = @KategoriFilter)
          AND (@Search IS NULL OR @Search = '''' OR Nama_Alat LIKE ''%'' + @Search + ''%'')
        ORDER BY ' + @SortSQL + '
        OFFSET @Offset ROWS FETCH NEXT @PageSize ROWS ONLY;';

    EXEC sp_executesql @SQL,
        N'@StatusFilter INT, @KategoriFilter VARCHAR(50), @Search VARCHAR(100), @Offset INT, @PageSize INT',
        @StatusFilter, @KategoriFilter, @Search, @Offset, @PageSize;
END

-- --------------------------------------------------------
-- SP_Alat_CheckDuplicate : cek nama alat sudah dipakai atau belum
-- (dipanggil sebelum Insert/Update; @ExcludeID dipakai waktu Edit
--  supaya nama alat itu sendiri tidak dianggap "duplikat")
-- --------------------------------------------------------
CREATE OR ALTER PROCEDURE SP_Alat_CheckDuplicate
    @Nama_Alat  VARCHAR(25),
    @ExcludeID  INT = 0
AS
BEGIN
    SET NOCOUNT ON;

    SELECT TOP 1 ID_Alat, Nama_Alat
    FROM Alat
    WHERE Nama_Alat = @Nama_Alat
      AND Is_Deleted = 0
      AND ID_Alat <> @ExcludeID;
END
GO

-- --------------------------------------------------------
-- SP_Alat_Delete : soft delete (Is_Deleted = 1)
-- --------------------------------------------------------
CREATE OR ALTER PROCEDURE SP_Alat_Delete
    @ID_Alat     INT,
    @Deleted_By  VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;

    IF NOT EXISTS (SELECT 1 FROM Alat WHERE ID_Alat = @ID_Alat AND Is_Deleted = 0)
    BEGIN
        RAISERROR('Data Alat tidak ditemukan atau sudah dihapus!', 16, 1);
        RETURN;
    END

    UPDATE Alat
    SET Is_Deleted   = 1,
        Deleted_By   = @Deleted_By,
        Deleted_Date = GETDATE()
    WHERE ID_Alat = @ID_Alat;
END
GO

-- --------------------------------------------------------
-- SP_Alat_Count : total data (utk paging), bisa difilter status
-- --------------------------------------------------------
CREATE OR ALTER PROCEDURE SP_Alat_Count
    @StatusFilter   INT = NULL,
    @KategoriFilter VARCHAR(50) = NULL,
    @Search         VARCHAR(100) = NULL
AS
BEGIN
    SET NOCOUNT ON;

    SELECT COUNT(*) AS TotalCount
    FROM Alat
    WHERE Is_Deleted = 0
      AND (@StatusFilter IS NULL OR Status = @StatusFilter)
      AND (@KategoriFilter IS NULL OR @KategoriFilter = '' OR Kategori = @KategoriFilter)
      AND (@Search IS NULL OR @Search = '' OR Nama_Alat LIKE '%' + @Search + '%');
END
GO

-- --------------------------------------------------------
-- SP_Alat_CountByStatus : statistik dashboard (Total/Aktif/Nonaktif sekaligus)
-- --------------------------------------------------------
CREATE OR ALTER PROCEDURE SP_Alat_CountByStatus
AS
BEGIN
    SET NOCOUNT ON;

    SELECT
        COUNT(*) AS TotalCount,
        SUM(CASE WHEN Status = 1 THEN 1 ELSE 0 END) AS AktifCount,
        SUM(CASE WHEN Status = 0 THEN 1 ELSE 0 END) AS NonaktifCount
    FROM Alat
    WHERE Is_Deleted = 0;
END
GO

-- ============================================================================
-- PART 2: STORED PROCEDURE - Alat_Size
-- ============================================================================

-- --------------------------------------------------------
-- SP_AlatSize_Insert : tambah 1 baris ukuran+stok utk 1 alat
-- --------------------------------------------------------
CREATE OR ALTER PROCEDURE SP_AlatSize_Insert
    @ID_Alat  INT,
    @Ukuran   VARCHAR(15),
    @Stok     INT
AS
BEGIN
    SET NOCOUNT ON;

    IF @Stok < 0
    BEGIN
        RAISERROR('Stok ukuran tidak boleh negatif!', 16, 1);
        RETURN;
    END

    IF EXISTS (SELECT 1 FROM Alat_Size WHERE ID_Alat = @ID_Alat AND Ukuran = @Ukuran)
    BEGIN
        UPDATE Alat_Size SET Stok = @Stok WHERE ID_Alat = @ID_Alat AND Ukuran = @Ukuran;
    END
    ELSE
    BEGIN
        INSERT INTO Alat_Size (ID_Alat, Ukuran, Stok) VALUES (@ID_Alat, @Ukuran, @Stok);
    END
END
GO

-- --------------------------------------------------------
-- SP_AlatSize_DeleteByAlat : hapus semua baris ukuran milik 1 alat
-- (dipanggil alat.php sebelum insert ulang ukuran waktu Save/Edit)
-- --------------------------------------------------------
CREATE OR ALTER PROCEDURE SP_AlatSize_DeleteByAlat
    @ID_Alat INT
AS
BEGIN
    SET NOCOUNT ON;
    DELETE FROM Alat_Size WHERE ID_Alat = @ID_Alat;
END
GO

-- --------------------------------------------------------
-- SP_AlatSize_SelectByAlat : ambil semua ukuran+stok milik 1 alat
-- --------------------------------------------------------
CREATE OR ALTER PROCEDURE SP_AlatSize_SelectByAlat
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

-- ============================================================================
-- PART 3: VERIFIKASI
-- ============================================================================
SELECT name AS ProcedureName, create_date, modify_date
FROM sys.procedures
WHERE name IN (
    'SP_Alat_Insert','SP_Alat_Update','SP_Alat_Select','SP_Alat_SelectFiltered',
    'SP_Alat_CheckDuplicate','SP_Alat_Delete','SP_Alat_Count','SP_Alat_CountByStatus',
    'SP_AlatSize_Insert','SP_AlatSize_DeleteByAlat','SP_AlatSize_SelectByAlat'
)
ORDER BY name;
GO

-- ============================================================================
-- PART 4: CONTOH PENGGUNAAN
-- ============================================================================
/*
-- Cek duplikat nama sebelum insert/update
EXEC SP_Alat_CheckDuplicate @Nama_Alat = 'Bola Basket SNI', @ExcludeID = 0;

-- Insert alat baru
EXEC SP_Alat_Insert
    @Nama_Alat = 'Jersey Home 2026', @Stok = 30,
    @Harga_Beli = 90000, @Harga_Jual = 150000,
    @Photo_Alat = 'asset/image/jersey.jpg', @Status = 1,
    @Created_By = 'SYSTEM', @Kategori = 'Baju';
-- lalu simpan stok per ukuran pakai New_ID_Alat yang dikembalikan:
EXEC SP_AlatSize_Insert @ID_Alat = 21, @Ukuran = 'M', @Stok = 15;
EXEC SP_AlatSize_Insert @ID_Alat = 21, @Ukuran = 'L', @Stok = 15;

-- Lihat stok per ukuran suatu alat
EXEC SP_AlatSize_SelectByAlat @ID_Alat = 21;

-- Soft delete
EXEC SP_Alat_Delete @ID_Alat = 21, @Deleted_By = 'SYSTEM';

-- Statistik dashboard & paging
EXEC SP_Alat_CountByStatus;
EXEC SP_Alat_Count @StatusFilter = 1;
*/
GO

USE Hoopball;
GO

-- 1. TIBAN SP_Alat_Count
CREATE OR ALTER PROCEDURE SP_Alat_Count
    @StatusFilter   INT = NULL,
    @KategoriFilter VARCHAR(50) = NULL,
    @Search         VARCHAR(100) = NULL
AS
BEGIN
    SET NOCOUNT ON;

    SELECT COUNT(*) AS TotalCount
    FROM Alat
    WHERE Is_Deleted = 0
      AND (@StatusFilter IS NULL OR Status = @StatusFilter)
      AND (@KategoriFilter IS NULL OR @KategoriFilter = '' OR Kategori = @KategoriFilter)
      AND (@Search IS NULL OR @Search = '' OR Nama_Alat LIKE '%' + @Search + '%');
END
GO

-- 2. TIBAN SP_Alat_SelectFiltered
CREATE OR ALTER PROCEDURE SP_Alat_SelectFiltered
    @StatusFilter    INT = NULL,
    @KategoriFilter  VARCHAR(50) = NULL,
    @Search          VARCHAR(100) = NULL,
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
        WHEN 'stok_desc'        THEN 'Stok DESC'
        WHEN 'harga_jual_desc'  THEN 'Harga_Jual DESC'
        WHEN 'harga_jual_asc'   THEN 'Harga_Jual ASC'
        WHEN 'harga_beli_desc'  THEN 'Harga_Beli DESC'
        WHEN 'harga_beli_asc'   THEN 'Harga_Beli ASC'
        ELSE 'Nama_Alat ASC'
    END;

    SET @SQL = N'
        SELECT ID_Alat, Nama_Alat, Kategori, Stok, Harga_Beli, Harga_Jual, Photo_Alat,
               Status, Is_Deleted, Created_By, Created_Date,
               Modified_By, Modified_Date, Deleted_By, Deleted_Date
        FROM Alat
        WHERE Is_Deleted = 0
          AND (@StatusFilter IS NULL OR Status = @StatusFilter)
          AND (@KategoriFilter IS NULL OR @KategoriFilter = '''' OR Kategori = @KategoriFilter)
          AND (@Search IS NULL OR @Search = '''' OR Nama_Alat LIKE ''%'' + @Search + ''%'')
        ORDER BY ' + @SortSQL + '
        OFFSET @Offset ROWS FETCH NEXT @PageSize ROWS ONLY;';

    EXEC sp_executesql @SQL,
        N'@StatusFilter INT, @KategoriFilter VARCHAR(50), @Search VARCHAR(100), @Offset INT, @PageSize INT',
        @StatusFilter, @KategoriFilter, @Search, @Offset, @PageSize;
END
GO