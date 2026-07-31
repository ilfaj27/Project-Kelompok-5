USE Hoopball;
GO

-- Fungsi: Buat nyimpen data alat/barang baru ke database.
-- Keunggulan: 
-- Di dalamnya udah ada validasi canggih. Kalau user masukin stok minus, atau harga jual lebih murah dari harga beli, 
-- SP ini bakal nolak (Error) secara otomatis. Pas berhasil, dia bakal ngembaliin ID_Alat yang baru dibikin buat dipakai nyimpen ukuran bajunya.

IF OBJECT_ID('SP_Alat_Insert', 'P') IS NOT NULL DROP PROCEDURE SP_Alat_Insert;
GO
CREATE PROCEDURE SP_Alat_Insert
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
    IF @Stok < 0 BEGIN RAISERROR('Stok tidak boleh negatif!', 16, 1); RETURN; END
    IF @Harga_Beli < 0 OR @Harga_Jual < 0 BEGIN RAISERROR('Harga beli/jual tidak boleh negatif!', 16, 1); RETURN; END
    IF @Harga_Jual < @Harga_Beli BEGIN RAISERROR('Harga jual tidak boleh lebih kecil dari harga beli!', 16, 1); RETURN; END

    INSERT INTO Alat (Nama_Alat, Stok, Harga_Beli, Harga_Jual, Photo_Alat, Status, Kategori, Is_Deleted, Created_By, Created_Date)
    VALUES (@Nama_Alat, @Stok, @Harga_Beli, @Harga_Jual, @Photo_Alat, @Status, @Kategori, 0, @Created_By, GETDATE());

    SELECT SCOPE_IDENTITY() AS New_ID_Alat;
END;
GO

-- Fungsi: Buat ngedit atau ngubah data alat yang udah ada.
-- Keunggulan: Sama kayak Insert, validasi harganya tetep jalan. Dia juga ngubah data Modified_By dan tanggal update-nya biar ketahuan siapa yang ngedit terakhir.

IF OBJECT_ID('SP_Alat_Update', 'P') IS NOT NULL DROP PROCEDURE SP_Alat_Update;
GO
CREATE PROCEDURE SP_Alat_Update
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
    BEGIN RAISERROR('Data Alat tidak ditemukan!', 16, 1); RETURN; END

    IF @Stok IS NOT NULL AND @Stok < 0 BEGIN RAISERROR('Stok tidak boleh negatif!', 16, 1); RETURN; END
    IF (@Harga_Beli IS NOT NULL AND @Harga_Beli < 0) OR (@Harga_Jual IS NOT NULL AND @Harga_Jual < 0)
    BEGIN RAISERROR('Harga beli/jual tidak boleh negatif!', 16, 1); RETURN; END

    IF EXISTS (
        SELECT 1 FROM Alat WHERE ID_Alat = @ID_Alat
        AND COALESCE(@Harga_Jual, Harga_Jual) < COALESCE(@Harga_Beli, Harga_Beli)
    ) BEGIN RAISERROR('Harga jual tidak boleh lebih kecil dari harga beli!', 16, 1); RETURN; END

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
END;
GO

-- Fungsi: Buat ngedit atau ngubah data alat yang udah ada.
-- Keunggulan: Sama kayak Insert, validasi harganya tetep jalan. Dia juga ngubah data Modified_By dan tanggal update-nya biar ketahuan siapa yang ngedit terakhir.

IF OBJECT_ID('SP_Alat_Select', 'P') IS NOT NULL DROP PROCEDURE SP_Alat_Select;
GO
CREATE PROCEDURE SP_Alat_Select
    @ID_Alat INT = NULL
AS
BEGIN
    SET NOCOUNT ON;
    SELECT ID_Alat, Nama_Alat, Kategori, Stok, Harga_Beli, Harga_Jual, Photo_Alat,
           Status, Is_Deleted, Created_By, Created_Date, Modified_By, Modified_Date
    FROM Alat
    WHERE (@ID_Alat IS NULL OR ID_Alat = @ID_Alat) AND Is_Deleted = 0
    ORDER BY Nama_Alat;
END;
GO

-- Fungsi: Otaknya halaman web lu! Ini yang ngatur tampilan Card/Grid di halaman utama.
-- Keunggulan: 
-- SP ini nanganin 4 hal sekaligus: Pencarian (Search), Filter (Kategori & Status), Sorting (Urutan harga/stok), 
-- dan Pagination (Halaman 1, 2, 3). Bikin web lu enteng karena yang diambil cuma 12 data per halaman.

IF OBJECT_ID('SP_Alat_SelectFiltered', 'P') IS NOT NULL DROP PROCEDURE SP_Alat_SelectFiltered;
GO
CREATE PROCEDURE SP_Alat_SelectFiltered
    @StatusFilter    INT = NULL,
    @KategoriFilter  VARCHAR(50) = NULL,
    @Search          VARCHAR(100) = NULL,
    @SortBy          VARCHAR(20) = 'terbaru',
    @PageNumber      INT = 1,
    @PageSize        INT = 12
AS
BEGIN
    SET NOCOUNT ON;
    DECLARE @Offset INT = (@PageNumber - 1) * @PageSize;
    DECLARE @SortSQL NVARCHAR(100);
    DECLARE @SQL NVARCHAR(MAX);

    SET @SortSQL = CASE @SortBy
        WHEN 'nama_asc'         THEN 'Nama_Alat ASC'
        WHEN 'nama_desc'        THEN 'Nama_Alat DESC'
        WHEN 'stok_desc'        THEN 'Stok DESC'
        WHEN 'harga_jual_desc'  THEN 'Harga_Jual DESC'
        WHEN 'harga_jual_asc'   THEN 'Harga_Jual ASC'
        WHEN 'harga_beli_desc'  THEN 'Harga_Beli DESC'
        WHEN 'harga_beli_asc'   THEN 'Harga_Beli ASC'
        ELSE 'ID_Alat DESC' -- Default 'terbaru' (Alat baru paling atas)
    END;

    SET @SQL = N'
        SELECT ID_Alat, Nama_Alat, Kategori, Stok, Harga_Beli, Harga_Jual, Photo_Alat,
               Status, Is_Deleted
        FROM Alat
        WHERE Is_Deleted = 0
          AND (@StatusFilter IS NULL OR Status = @StatusFilter)
          AND (@KategoriFilter IS NULL OR @KategoriFilter = '''' OR Kategori = @KategoriFilter)
          AND (@Search IS NULL OR @Search = '''' OR Nama_Alat LIKE ''%'' + @Search + ''%'')
        ORDER BY Status DESC, ' + @SortSQL + '
        OFFSET @Offset ROWS FETCH NEXT @PageSize ROWS ONLY;';

    EXEC sp_executesql @SQL,
        N'@StatusFilter INT, @KategoriFilter VARCHAR(50), @Search VARCHAR(100), @Offset INT, @PageSize INT',
        @StatusFilter, @KategoriFilter, @Search, @Offset, @PageSize;
END;
GO

-- Fungsi: Buat ngecek apakah nama alat yang mau diinput udah pernah dipakai atau belum.
-- Keunggulan: Biar gak ada nama barang yang kembar. Kalau lagi mode "Edit", dia cukup pintar buat ngecualiin nama barang itu sendiri biar gak dibilang duplikat.

IF OBJECT_ID('SP_Alat_CheckDuplicate', 'P') IS NOT NULL DROP PROCEDURE SP_Alat_CheckDuplicate;
GO
CREATE PROCEDURE SP_Alat_CheckDuplicate
    @Nama_Alat  VARCHAR(25),
    @ExcludeID  INT = 0
AS
BEGIN
    SET NOCOUNT ON;
    SELECT TOP 1 ID_Alat, Nama_Alat FROM Alat
    WHERE Nama_Alat = @Nama_Alat AND Is_Deleted = 0 AND ID_Alat <> @ExcludeID;
END;
GO

-- Fungsi: Buat ngehapus alat.
-- Keunggulan: 
-- Ini pakai sistem Soft Delete. Artinya datanya gak beneran hilang/musnah dari database, cuma status Is_Deleted-nya diubah jadi 1. 
-- Ini aman banget kalau misal atasan minta datanya dipulihin lagi suatu saat.

IF OBJECT_ID('SP_Alat_Delete', 'P') IS NOT NULL DROP PROCEDURE SP_Alat_Delete;
GO
CREATE PROCEDURE SP_Alat_Delete
    @ID_Alat     INT,
    @Deleted_By  VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;
    IF NOT EXISTS (SELECT 1 FROM Alat WHERE ID_Alat = @ID_Alat AND Is_Deleted = 0)
    BEGIN RAISERROR('Data Alat tidak ditemukan atau sudah dihapus!', 16, 1); RETURN; END

    UPDATE Alat SET Is_Deleted = 1, Deleted_By = @Deleted_By, Deleted_Date = GETDATE() WHERE ID_Alat = @ID_Alat;
END;
GO

-- Fungsi: Ngitung total alat berdasarkan pencarian atau filter yang lagi aktif.
-- Keunggulan: Dipakai sama PHP lu buat ngitung total Halaman (Pagination). Misalnya total ada 50 barang, dibagi 10 barang per halaman, berarti butuh 5 halaman.

IF OBJECT_ID('SP_Alat_Count', 'P') IS NOT NULL DROP PROCEDURE SP_Alat_Count;
GO
CREATE PROCEDURE SP_Alat_Count
    @StatusFilter   INT = NULL,
    @KategoriFilter VARCHAR(50) = NULL,
    @Search         VARCHAR(100) = NULL
AS
BEGIN
    SET NOCOUNT ON;
    SELECT COUNT(*) AS TotalCount FROM Alat
    WHERE Is_Deleted = 0
      AND (@StatusFilter IS NULL OR Status = @StatusFilter)
      AND (@KategoriFilter IS NULL OR @KategoriFilter = '' OR Kategori = @KategoriFilter)
      AND (@Search IS NULL OR @Search = '' OR Nama_Alat LIKE '%' + @Search + '%');
END;
GO

-- Fungsi: Ngitung statistik total barang.
-- Keunggulan: 
-- Ini yang ngisi angka di kotak-kotak atas halaman lu (Berapa yang AKTIF, berapa yang NONAKTIF, dan TOTAL SEMUA). 
-- Sekali eksekusi (query), langsung dapat 3 angka sekaligus. Jauh lebih cepat dari query biasa.

IF OBJECT_ID('SP_Alat_CountByStatus', 'P') IS NOT NULL DROP PROCEDURE SP_Alat_CountByStatus;
GO
CREATE PROCEDURE SP_Alat_CountByStatus
AS
BEGIN
    SET NOCOUNT ON;
    SELECT
        COUNT(*) AS TotalCount,
        SUM(CASE WHEN Status = 1 THEN 1 ELSE 0 END) AS AktifCount,
        SUM(CASE WHEN Status = 0 THEN 1 ELSE 0 END) AS NonaktifCount
    FROM Alat WHERE Is_Deleted = 0;
END;
GO

-- Fungsi: Nyimpen jumlah stok untuk masing-masing ukuran (Misal: Sepatu ukuran 40 stoknya 5, ukuran 41 stoknya 10).
-- Keunggulan: Pakai sistem Upsert. Kalau ukurannya belum ada, dia nambahin baru (Insert). Kalau udah ada, dia cukup ngubah jumlah stoknya (Update).

IF OBJECT_ID('SP_AlatSize_Insert', 'P') IS NOT NULL DROP PROCEDURE SP_AlatSize_Insert;
GO
CREATE PROCEDURE SP_AlatSize_Insert
    @ID_Alat  INT,
    @Ukuran   VARCHAR(15),
    @Stok     INT
AS
BEGIN
    SET NOCOUNT ON;
    IF @Stok < 0 BEGIN RAISERROR('Stok ukuran tidak boleh negatif!', 16, 1); RETURN; END

    IF EXISTS (SELECT 1 FROM Alat_Size WHERE ID_Alat = @ID_Alat AND Ukuran = @Ukuran)
    BEGIN UPDATE Alat_Size SET Stok = @Stok WHERE ID_Alat = @ID_Alat AND Ukuran = @Ukuran; END
    ELSE
    BEGIN INSERT INTO Alat_Size (ID_Alat, Ukuran, Stok) VALUES (@ID_Alat, @Ukuran, @Stok); END
END;
GO

-- Fungsi: Menghapus semua ukuran yang nempel di 1 alat.
-- Keunggulan: 
-- Dipakai pas lagi ngedit barang. Daripada pusing milih ukuran mana yang dihapus/ditambah, 
-- PHP lu bakal manggil SP ini buat ngebersihin semua ukuran lama, baru di-insert ulang ukuran yang baru. Lebih bersih dan anti-bug.

IF OBJECT_ID('SP_AlatSize_DeleteByAlat', 'P') IS NOT NULL DROP PROCEDURE SP_AlatSize_DeleteByAlat;
GO
CREATE PROCEDURE SP_AlatSize_DeleteByAlat
    @ID_Alat INT
AS
BEGIN
    SET NOCOUNT ON;
    DELETE FROM Alat_Size WHERE ID_Alat = @ID_Alat;
END;
GO

-- Fungsi: Ngambil daftar ukuran dan stoknya untuk 1 alat.
-- Keunggulan: Dipakai di popup "View Detail", biar atasan lu bisa ngeliat breakdown stoknya (Oh, baju ini M sisa 5, L sisa 10).

IF OBJECT_ID('SP_AlatSize_SelectByAlat', 'P') IS NOT NULL DROP PROCEDURE SP_AlatSize_SelectByAlat;
GO
CREATE PROCEDURE SP_AlatSize_SelectByAlat
    @ID_Alat INT
AS
BEGIN
    SET NOCOUNT ON;
    SELECT ID_Alat_Size, ID_Alat, Ukuran, Stok FROM Alat_Size WHERE ID_Alat = @ID_Alat ORDER BY ID_Alat_Size;
END;
GO