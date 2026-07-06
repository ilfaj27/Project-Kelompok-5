-- ==========================================================================================
-- 1. UDF: Mengambil statistik Lapangan (untuk kartu statistik atas)
-- ==========================================================================================
CREATE OR ALTER FUNCTION dbo.fn_GetLapanganStats()
RETURNS TABLE
AS
RETURN (
    SELECT 
        COUNT(*) AS Total,
        SUM(CASE WHEN Status = 1 THEN 1 ELSE 0 END) AS Aktif,
        SUM(CASE WHEN Status = 0 THEN 1 ELSE 0 END) AS Maintenance
    FROM dbo.Lapangan 
    WHERE Is_Deleted = 0
);
GO

-- ==========================================================================================
-- 2. SP: Cek Duplikasi Nama Lapangan
-- ==========================================================================================
CREATE OR ALTER PROCEDURE dbo.sp_CheckLapanganDuplicate
    @Nama_Lapangan VARCHAR(50),
    @ID_Lapangan INT
AS
BEGIN
    SET NOCOUNT ON;
    SELECT ID_Lapangan 
    FROM dbo.Lapangan 
    WHERE LOWER(Nama_Lapangan) = LOWER(@Nama_Lapangan) 
      AND ID_Lapangan <> @ID_Lapangan 
      AND Is_Deleted = 0;
END;
GO

-- ==========================================================================================
-- 3. SP: Ambil Detail Lapangan & Daftar Fasilitas Terpasang (Multiple Result Sets)
-- ==========================================================================================
CREATE OR ALTER PROCEDURE dbo.sp_GetLapanganDetail
    @ID_Lapangan INT
AS
BEGIN
    SET NOCOUNT ON;

    -- Hasil 1: Detail Lapangan Utama
    SELECT * FROM dbo.Lapangan WHERE ID_Lapangan = @ID_Lapangan AND Is_Deleted = 0;

    -- Hasil 2: Daftar Fasilitas yang terpasang di lapangan tersebut
    SELECT LF.ID_Fasilitas, F.Nama_Fasilitas, LF.Jumlah_Digunakan
    FROM dbo.Detail_Lapangan_Fasilitas LF -- DISESUAIKAN
    JOIN dbo.Fasilitas_Lapangan F ON LF.ID_Fasilitas = F.ID_Fasilitas -- DISESUAIKAN
    WHERE LF.ID_Lapangan = @ID_Lapangan;
END;
GO

-- ==========================================================================================
-- 4. SP: Simpan Lapangan Baru & Alokasi Fasilitas (Create)
-- ==========================================================================================
CREATE OR ALTER PROCEDURE dbo.sp_CreateLapangan
    @Nama_Lapangan VARCHAR(50),
    @Harga_Sewa DECIMAL(18,2),
    @Photo_Lapangan VARCHAR(255),
    @Created_By VARCHAR(50),
    @FacilitiesJSON NVARCHAR(MAX) = NULL -- Format JSON: '[{"id":1, "qty":2}, {"id":2, "qty":1}]'
AS
BEGIN
    SET NOCOUNT ON;
    BEGIN TRANSACTION;

    BEGIN TRY
        -- 1. Insert data Lapangan utama
        DECLARE @NewID INT;
        INSERT INTO dbo.Lapangan (Nama_Lapangan, Harga_Sewa, Photo_Lapangan, Status, Is_Deleted, Created_By, Created_Date)
        VALUES (@Nama_Lapangan, @Harga_Sewa, @Photo_Lapangan, 1, 0, @Created_By, GETDATE());
        
        SET @NewID = SCOPE_IDENTITY();

        -- 2. Alokasikan fasilitas & kurangi stok master jika data dikirim
        IF @FacilitiesJSON IS NOT NULL AND ISJSON(@FacilitiesJSON) = 1
        BEGIN
            -- Validasi ketersediaan stok
            IF EXISTS (
                SELECT 1 
                FROM OPENJSON(@FacilitiesJSON)
                WITH (
                    id_fasilitas INT '$.id',
                    qty INT '$.qty'
                ) AS NewFac
                JOIN dbo.Fasilitas_Lapangan F ON F.ID_Fasilitas = NewFac.id_fasilitas -- DISESUAIKAN
                WHERE F.Stok_Tersedia < NewFac.qty
            )
            BEGIN
                THROW 50001, 'Stok salah satu fasilitas yang dipilih tidak mencukupi.', 1;
            END

            -- Hubungkan fasilitas ke lapangan di tabel penghubung
            INSERT INTO dbo.Detail_Lapangan_Fasilitas (ID_Lapangan, ID_Fasilitas, Jumlah_Digunakan) -- DISESUAIKAN
            SELECT @NewID, id_fasilitas, qty
            FROM OPENJSON(@FacilitiesJSON)
            WITH (
                id_fasilitas INT '$.id',
                qty INT '$.qty'
            );

            -- Kurangi stok pada tabel master Fasilitas
            UPDATE F
            SET F.Stok_Tersedia = F.Stok_Tersedia - NewFac.qty
            FROM dbo.Fasilitas_Lapangan F -- DISESUAIKAN
            JOIN (
                SELECT id_fasilitas, qty
                FROM OPENJSON(@FacilitiesJSON)
                WITH (
                    id_fasilitas INT '$.id',
                    qty INT '$.qty'
                )
            ) AS NewFac ON F.ID_Fasilitas = NewFac.id_fasilitas;
        END

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        ROLLBACK TRANSACTION;
        THROW;
    END CATCH
END;
GO

-- ==========================================================================================
-- 5. SP: Perbarui Data Lapangan, Ubah Hubungan Fasilitas & Update Stok (Update)
-- ==========================================================================================
CREATE OR ALTER PROCEDURE dbo.sp_UpdateLapangan
    @ID_Lapangan INT,
    @Nama_Lapangan VARCHAR(50),
    @Harga_Sewa DECIMAL(18,2),
    @Photo_Lapangan VARCHAR(255),
    @Modified_By VARCHAR(50),
    @FacilitiesJSON NVARCHAR(MAX) = NULL -- Format JSON: '[{"id":1, "qty":2}]'
AS
BEGIN
    SET NOCOUNT ON;
    BEGIN TRANSACTION;

    BEGIN TRY
        -- 1. Kembalikan stok lama ke master Fasilitas
        UPDATE F
        SET F.Stok_Tersedia = F.Stok_Tersedia + LF.Jumlah_Digunakan
        FROM dbo.Fasilitas_Lapangan F -- DISESUAIKAN
        JOIN dbo.Detail_Lapangan_Fasilitas LF ON F.ID_Fasilitas = LF.ID_Fasilitas -- DISESUAIKAN
        WHERE LF.ID_Lapangan = @ID_Lapangan;

        -- 2. Hapus relasi fasilitas lama
        DELETE FROM dbo.Detail_Lapangan_Fasilitas WHERE ID_Lapangan = @ID_Lapangan; -- DISESUAIKAN

        -- 3. Alokasikan fasilitas baru jika dikirim
        IF @FacilitiesJSON IS NOT NULL AND ISJSON(@FacilitiesJSON) = 1
        BEGIN
            -- Validasi stok baru
            IF EXISTS (
                SELECT 1 
                FROM OPENJSON(@FacilitiesJSON)
                WITH (
                    id_fasilitas INT '$.id',
                    qty INT '$.qty'
                ) AS NewFac
                JOIN dbo.Fasilitas_Lapangan F ON F.ID_Fasilitas = NewFac.id_fasilitas -- DISESUAIKAN
                WHERE F.Stok_Tersedia < NewFac.qty
            )
            BEGIN
                THROW 50001, 'Stok salah satu fasilitas yang dipilih tidak mencukupi.', 1;
            END

            -- Simpan relasi baru
            INSERT INTO dbo.Detail_Lapangan_Fasilitas (ID_Lapangan, ID_Fasilitas, Jumlah_Digunakan) -- DISESUAIKAN
            SELECT @ID_Lapangan, id_fasilitas, qty
            FROM OPENJSON(@FacilitiesJSON)
            WITH (
                id_fasilitas INT '$.id',
                qty INT '$.qty'
            );

            -- Potong stok baru pada tabel master Fasilitas
            UPDATE F
            SET F.Stok_Tersedia = F.Stok_Tersedia - NewFac.qty
            FROM dbo.Fasilitas_Lapangan F -- DISESUAIKAN
            JOIN (
                SELECT id_fasilitas, qty
                FROM OPENJSON(@FacilitiesJSON)
                WITH (
                    id_fasilitas INT '$.id',
                    qty INT '$.qty'
                )
            ) AS NewFac ON F.ID_Fasilitas = NewFac.id_fasilitas;
        END

        -- 4. Update data utama Lapangan
        UPDATE dbo.Lapangan 
        SET Nama_Lapangan = @Nama_Lapangan,
            Harga_Sewa = @Harga_Sewa,
            Photo_Lapangan = @Photo_Lapangan,
            Modified_By = @Modified_By,
            Modified_Date = GETDATE()
        WHERE ID_Lapangan = @ID_Lapangan AND Is_Deleted = 0;

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        ROLLBACK TRANSACTION;
        THROW;
    END CATCH
END;
GO

-- ==========================================================================================
-- 6. SP: Ubah Status Lapangan (Toggle Status)
-- ==========================================================================================
CREATE OR ALTER PROCEDURE dbo.sp_UpdateStatusLapangan
    @ID_Lapangan INT,
    @Status INT,
    @Modified_By VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;
    UPDATE dbo.Lapangan 
    SET Status = @Status,
        Modified_By = @Modified_By,
        Modified_Date = GETDATE()
    WHERE ID_Lapangan = @ID_Lapangan AND Is_Deleted = 0;
END;
GO

-- ==========================================================================================
-- 7. SP: Soft Delete Lapangan & Pengembalian Stok Fasilitas (Delete)
-- ==========================================================================================
CREATE OR ALTER PROCEDURE dbo.sp_DeleteLapangan
    @ID_Lapangan INT,
    @Deleted_By VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;
    BEGIN TRANSACTION;

    BEGIN TRY
        -- 1. Kembalikan semua stok fasilitas teralokasi ke master Fasilitas
        UPDATE F
        SET F.Stok_Tersedia = F.Stok_Tersedia + LF.Jumlah_Digunakan
        FROM dbo.Fasilitas_Lapangan F -- DISESUAIKAN
        JOIN dbo.Detail_Lapangan_Fasilitas LF ON F.ID_Fasilitas = LF.ID_Fasilitas -- DISESUAIKAN
        WHERE LF.ID_Lapangan = @ID_Lapangan;

        -- 2. Putus hubungan fasilitas dengan menghapus baris di tabel penghubung
        DELETE FROM dbo.Detail_Lapangan_Fasilitas WHERE ID_Lapangan = @ID_Lapangan; -- DISESUAIKAN

        -- 3. Set flag soft delete pada data Lapangan
        UPDATE dbo.Lapangan 
        SET Is_Deleted = 1,
            Deleted_By = @Deleted_By,
            Deleted_Date = GETDATE()
        WHERE ID_Lapangan = @ID_Lapangan;

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        ROLLBACK TRANSACTION;
        THROW;
    END CATCH
END;
GO

-- ==========================================================================================
-- 8. SP: Membaca list lapangan terpaginasi sekaligus menghitung total datanya (Read)
-- ==========================================================================================
CREATE OR ALTER PROCEDURE dbo.sp_ReadLapanganListWithCount
    @FilterStatus VARCHAR(10),
    @SortBy VARCHAR(50),
    @Offset INT,
    @Limit INT,
    @Search VARCHAR(100) = '' -- 1. TAMBAHKAN PARAMETER PENCARIAN INI (Sesuai kiriman PHP kemarin)
AS
BEGIN
    SET NOCOUNT ON;

    -- Hasil 1: Total Record terfilter (Pencarian & Status)
    SELECT COUNT(*) AS TotalCount
    FROM dbo.Lapangan
    WHERE Is_Deleted = 0
      -- 2. DISESUAIKAN: Menggunakan status '1' dan '0' agar cocok dengan nilai input <select> di PHP
      AND (@FilterStatus = 'all' OR (@FilterStatus = '1' AND Status = 1) OR (@FilterStatus = '0' AND Status = 0))
      -- 3. TAMBAHKAN: Filter pencarian parsial berdasarkan nama lapangan
      AND (@Search = '' OR Nama_Lapangan LIKE '%' + @Search + '%');

    -- Hasil 2: List Data terpaginasi (Pencarian & Status)
    SELECT * 
    FROM dbo.Lapangan
    WHERE Is_Deleted = 0
      -- Diselaraskan dengan status '1' dan '0'
      AND (@FilterStatus = 'all' OR (@FilterStatus = '1' AND Status = 1) OR (@FilterStatus = '0' AND Status = 0))
      -- Filter pencarian parsial berdasarkan nama lapangan
      AND (@Search = '' OR Nama_Lapangan LIKE '%' + @Search + '%')
    ORDER BY 
        CASE WHEN @SortBy = 'nama_asc' THEN Nama_Lapangan END ASC,
        CASE WHEN @SortBy = 'harga_desc' THEN Harga_Sewa END DESC,
        CASE WHEN @SortBy = 'harga_asc' THEN Harga_Sewa END ASC,
        CASE WHEN @SortBy = 'ID_Lapangan' THEN ID_Lapangan END ASC
    OFFSET @Offset ROWS FETCH NEXT @Limit ROWS ONLY;
END;
GO