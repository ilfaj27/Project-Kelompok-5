-- ============================================================
-- STORED PROCEDURES UNTUK MASTER PROMO
-- Database: Hoopball
-- ============================================================

USE Hoopball;
GO

-- ============================================================
-- 1. UDF: Cek Nama Promo Duplikat
-- ============================================================
IF OBJECT_ID('dbo.udf_CekNamaPromoDuplikat', 'FN') IS NOT NULL
    DROP FUNCTION dbo.udf_CekNamaPromoDuplikat;
GO

CREATE FUNCTION dbo.udf_CekNamaPromoDuplikat
(
    @Nama_Promo VARCHAR(50),
    @Exclude_ID INT = NULL
)
RETURNS INT
AS
BEGIN
    DECLARE @IsDuplicate INT = 0;

    IF EXISTS (
        SELECT 1 FROM Promo 
        WHERE Nama_Promo = @Nama_Promo 
        AND Is_Deleted = 0
        AND (@Exclude_ID IS NULL OR ID_Promo <> @Exclude_ID)
    )
    BEGIN
        SET @IsDuplicate = 1;
    END

    RETURN @IsDuplicate;
END;
GO

-- ============================================================
-- 2. UDF: Format Status Promo (Aktif/Kadaluarsa)
-- ============================================================
IF OBJECT_ID('dbo.udf_GetPromoStatusText', 'FN') IS NOT NULL
    DROP FUNCTION dbo.udf_GetPromoStatusText;
GO

CREATE FUNCTION dbo.udf_GetPromoStatusText
(
    @Status INT,
    @Tanggal_Selesai DATE
)
RETURNS VARCHAR(20)
AS
BEGIN
    DECLARE @StatusText VARCHAR(20);

    IF @Status = 0 OR @Tanggal_Selesai < CAST(GETDATE() AS DATE)
        SET @StatusText = 'KADALUARSA';
    ELSE
        SET @StatusText = 'AKTIF';

    RETURN @StatusText;
END;
GO

-- ============================================================
-- 3. SP: Insert Promo Baru
-- ============================================================
IF OBJECT_ID('dbo.sp_Promo_Insert', 'P') IS NOT NULL
    DROP PROCEDURE dbo.sp_Promo_Insert;
GO

CREATE PROCEDURE dbo.sp_Promo_Insert
    @Nama_Promo      VARCHAR(50),
    @Diskon          DECIMAL(18,2),
    @Tanggal_Mulai   DATE,
    @Tanggal_Selesai DATE,
    @Created_By      VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;

    BEGIN TRY
        BEGIN TRANSACTION;

        -- Validasi: Diskon harus > 0 dan <= 100
        IF @Diskon <= 0
        BEGIN
            RAISERROR('Diskon tidak boleh 0 atau kurang dari 0!', 16, 1);
            RETURN;
        END

        IF @Diskon > 100
        BEGIN
            RAISERROR('Diskon maksimal 100%%!', 16, 1);
            RETURN;
        END

        -- Validasi: Tanggal Mulai tidak boleh kurang dari hari ini
        IF @Tanggal_Mulai < CAST(GETDATE() AS DATE)
        BEGIN
            RAISERROR('Tanggal mulai tidak boleh kurang dari hari ini!', 16, 1);
            RETURN;
        END

        -- Validasi: Tanggal Selesai harus >= Tanggal Mulai
        IF @Tanggal_Selesai < @Tanggal_Mulai
        BEGIN
            RAISERROR('Tanggal selesai tidak boleh mendahului tanggal mulai!', 16, 1);
            RETURN;
        END

        -- Cek duplikat nama
        IF dbo.udf_CekNamaPromoDuplikat(@Nama_Promo, NULL) = 1
        BEGIN
            RAISERROR('Nama promo sudah tersedia!', 16, 1);
            RETURN;
        END

        -- Insert data
        INSERT INTO Promo
        (Nama_Promo, Diskon, Tanggal_Mulai, Tanggal_Selesai, Status, Is_Deleted, Created_By, Created_Date)
        VALUES
        (@Nama_Promo, @Diskon, @Tanggal_Mulai, @Tanggal_Selesai, 1, 0, @Created_By, GETDATE());

        COMMIT TRANSACTION;

        SELECT SCOPE_IDENTITY() AS ID_Promo_Baru;

    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0
            ROLLBACK TRANSACTION;

        DECLARE @ErrorMessage NVARCHAR(4000) = ERROR_MESSAGE();
        DECLARE @ErrorSeverity INT = ERROR_SEVERITY();
        DECLARE @ErrorState INT = ERROR_STATE();

        RAISERROR(@ErrorMessage, @ErrorSeverity, @ErrorState);
    END CATCH
END;
GO

-- ============================================================
-- 4. SP: Update Promo
-- ============================================================
IF OBJECT_ID('dbo.sp_Promo_Update', 'P') IS NOT NULL
    DROP PROCEDURE dbo.sp_Promo_Update;
GO

CREATE PROCEDURE dbo.sp_Promo_Update
    @ID_Promo        INT,
    @Nama_Promo      VARCHAR(50),
    @Diskon          DECIMAL(18,2),
    @Tanggal_Mulai   DATE,
    @Tanggal_Selesai DATE,
    @Modified_By     VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;

    BEGIN TRY
        BEGIN TRANSACTION;

        -- Validasi: Promo harus ada
        IF NOT EXISTS (SELECT 1 FROM Promo WHERE ID_Promo = @ID_Promo AND Is_Deleted = 0)
        BEGIN
            RAISERROR('Data promo tidak ditemukan!', 16, 1);
            RETURN;
        END

        -- Validasi: Diskon harus > 0 dan <= 100
        IF @Diskon <= 0
        BEGIN
            RAISERROR('Diskon tidak boleh 0 atau kurang dari 0!', 16, 1);
            RETURN;
        END

        IF @Diskon > 100
        BEGIN
            RAISERROR('Diskon maksimal 100%%!', 16, 1);
            RETURN;
        END

        -- Validasi: Tanggal Mulai tidak boleh kurang dari hari ini
        IF @Tanggal_Mulai < CAST(GETDATE() AS DATE)
        BEGIN
            RAISERROR('Tanggal mulai tidak boleh kurang dari hari ini!', 16, 1);
            RETURN;
        END

        -- Validasi: Tanggal Selesai harus >= Tanggal Mulai
        IF @Tanggal_Selesai < @Tanggal_Mulai
        BEGIN
            RAISERROR('Tanggal selesai tidak boleh mendahului tanggal mulai!', 16, 1);
            RETURN;
        END

        -- Cek duplikat nama (exclude ID yang sedang diedit)
        IF dbo.udf_CekNamaPromoDuplikat(@Nama_Promo, @ID_Promo) = 1
        BEGIN
            RAISERROR('Nama promo sudah tersedia!', 16, 1);
            RETURN;
        END

        -- Update data
        UPDATE Promo
        SET Nama_Promo = @Nama_Promo,
            Diskon = @Diskon,
            Tanggal_Mulai = @Tanggal_Mulai,
            Tanggal_Selesai = @Tanggal_Selesai,
            Modified_By = @Modified_By,
            Modified_Date = GETDATE()
        WHERE ID_Promo = @ID_Promo;

        COMMIT TRANSACTION;

    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0
            ROLLBACK TRANSACTION;

        DECLARE @ErrorMessage NVARCHAR(4000) = ERROR_MESSAGE();
        DECLARE @ErrorSeverity INT = ERROR_SEVERITY();
        DECLARE @ErrorState INT = ERROR_STATE();

        RAISERROR(@ErrorMessage, @ErrorSeverity, @ErrorState);
    END CATCH
END;
GO

-- ============================================================
-- 5. SP: Toggle Status Promo (Aktif/Nonaktif)
-- ============================================================
IF OBJECT_ID('dbo.sp_Promo_ToggleStatus', 'P') IS NOT NULL
    DROP PROCEDURE dbo.sp_Promo_ToggleStatus;
GO

CREATE PROCEDURE dbo.sp_Promo_ToggleStatus
    @ID_Promo     INT,
    @New_Status   INT,
    @Modified_By  VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;

    BEGIN TRY
        BEGIN TRANSACTION;

        -- Validasi: Promo harus ada
        IF NOT EXISTS (SELECT 1 FROM Promo WHERE ID_Promo = @ID_Promo AND Is_Deleted = 0)
        BEGIN
            RAISERROR('Data promo tidak ditemukan!', 16, 1);
            RETURN;
        END

        -- Validasi: Status hanya boleh 0 atau 1
        IF @New_Status NOT IN (0, 1)
        BEGIN
            RAISERROR('Status tidak valid! Hanya diperbolehkan 0 (Nonaktif) atau 1 (Aktif).', 16, 1);
            RETURN;
        END

        -- Update status
        UPDATE Promo
        SET Status = @New_Status,
            Modified_By = @Modified_By,
            Modified_Date = GETDATE()
        WHERE ID_Promo = @ID_Promo;

        COMMIT TRANSACTION;

    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0
            ROLLBACK TRANSACTION;

        DECLARE @ErrorMessage NVARCHAR(4000) = ERROR_MESSAGE();
        DECLARE @ErrorSeverity INT = ERROR_SEVERITY();
        DECLARE @ErrorState INT = ERROR_STATE();

        RAISERROR(@ErrorMessage, @ErrorSeverity, @ErrorState);
    END CATCH
END;
GO

-- ============================================================
-- 6. SP: Soft Delete Promo
-- ============================================================
IF OBJECT_ID('dbo.sp_Promo_Delete', 'P') IS NOT NULL
    DROP PROCEDURE dbo.sp_Promo_Delete;
GO

CREATE PROCEDURE dbo.sp_Promo_Delete
    @ID_Promo    INT,
    @Deleted_By  VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;

    BEGIN TRY
        BEGIN TRANSACTION;

        -- Validasi: Promo harus ada dan belum dihapus
        IF NOT EXISTS (SELECT 1 FROM Promo WHERE ID_Promo = @ID_Promo AND Is_Deleted = 0)
        BEGIN
            RAISERROR('Data promo tidak ditemukan atau sudah dihapus!', 16, 1);
            RETURN;
        END

        -- Cek apakah promo masih digunakan di tabel Booking
        IF EXISTS (SELECT 1 FROM Booking WHERE ID_Promo = @ID_Promo)
        BEGIN
            RAISERROR('Gagal hapus, data masih terikat dengan transaksi booking!', 16, 1);
            RETURN;
        END

        -- Soft delete
        UPDATE Promo
        SET Is_Deleted = 1,
            Deleted_By = @Deleted_By,
            Deleted_Date = GETDATE()
        WHERE ID_Promo = @ID_Promo;

        COMMIT TRANSACTION;

    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0
            ROLLBACK TRANSACTION;

        DECLARE @ErrorMessage NVARCHAR(4000) = ERROR_MESSAGE();
        DECLARE @ErrorSeverity INT = ERROR_SEVERITY();
        DECLARE @ErrorState INT = ERROR_STATE();

        RAISERROR(@ErrorMessage, @ErrorSeverity, @ErrorState);
    END CATCH
END;
GO

-- ============================================================
-- 7. SP: Get Promo By ID (Detail)
-- ============================================================
IF OBJECT_ID('dbo.sp_Promo_GetById', 'P') IS NOT NULL
    DROP PROCEDURE dbo.sp_Promo_GetById;
GO

CREATE PROCEDURE dbo.sp_Promo_GetById
    @ID_Promo INT
AS
BEGIN
    SET NOCOUNT ON;

    SELECT 
        ID_Promo,
        Nama_Promo,
        Diskon,
        CONCAT(CAST(Diskon AS VARCHAR), '%') AS DiskonFormatted,
        Tanggal_Mulai,
        CONVERT(VARCHAR(10), Tanggal_Mulai, 105) AS TanggalMulaiFormatted,  -- dd-mm-yyyy
        Tanggal_Selesai,
        CONVERT(VARCHAR(10), Tanggal_Selesai, 105) AS TanggalSelesaiFormatted,  -- dd-mm-yyyy
        Status,
        dbo.udf_GetPromoStatusText(Status, Tanggal_Selesai) AS StatusText,
        Is_Deleted,
        Created_By,
        Created_Date,
        Modified_By,
        Modified_Date
    FROM Promo
    WHERE ID_Promo = @ID_Promo
    AND Is_Deleted = 0;
END;
GO

-- ============================================================
-- 8. SP: Get All Promo (Dengan Paging, Filter, Sorting)
-- ============================================================
IF OBJECT_ID('dbo.sp_Promo_GetAll', 'P') IS NOT NULL
    DROP PROCEDURE dbo.sp_Promo_GetAll;
GO

CREATE PROCEDURE dbo.sp_Promo_GetAll
    @Page         INT = 1,
    @Limit        INT = 10,
    @Filter_Status VARCHAR(10) = NULL,  -- NULL = Semua, '1' = Aktif, '0' = Kadaluarsa
    @Sort_By      VARCHAR(20) = 'nama_asc'  -- nama_asc, nama_desc
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE @Offset INT = (@Page - 1) * @Limit;

    -- Query data dengan filter dan sorting
    SELECT 
        ID_Promo,
        Nama_Promo,
        Diskon,
        CONCAT(CAST(Diskon AS VARCHAR), '%') AS DiskonFormatted,
        Tanggal_Mulai,
        CONVERT(VARCHAR(10), Tanggal_Mulai, 105) AS TanggalMulaiFormatted,
        Tanggal_Selesai,
        CONVERT(VARCHAR(10), Tanggal_Selesai, 105) AS TanggalSelesaiFormatted,
        Status,
        dbo.udf_GetPromoStatusText(Status, Tanggal_Selesai) AS StatusText,
        Is_Deleted,
        Created_By,
        Created_Date,
        Modified_By,
        Modified_Date
    FROM Promo
    WHERE Is_Deleted = 0
    AND (@Filter_Status IS NULL 
         OR (@Filter_Status = '1' AND Status = 1 AND Tanggal_Selesai >= CAST(GETDATE() AS DATE))
         OR (@Filter_Status = '0' AND (Status = 0 OR Tanggal_Selesai < CAST(GETDATE() AS DATE)))
        )
    ORDER BY 
        CASE WHEN @Sort_By = 'nama_asc' THEN Nama_Promo END ASC,
        CASE WHEN @Sort_By = 'nama_desc' THEN Nama_Promo END DESC
    OFFSET @Offset ROWS
    FETCH NEXT @Limit ROWS ONLY;
END;
GO

-- ============================================================
-- 9. SP: Get Total Count (Untuk Paging)
-- ============================================================
IF OBJECT_ID('dbo.sp_Promo_GetCount', 'P') IS NOT NULL
    DROP PROCEDURE dbo.sp_Promo_GetCount;
GO

CREATE PROCEDURE dbo.sp_Promo_GetCount
    @Filter_Status VARCHAR(10) = NULL
AS
BEGIN
    SET NOCOUNT ON;

    SELECT COUNT(*) AS Total
    FROM Promo
    WHERE Is_Deleted = 0
    AND (@Filter_Status IS NULL 
         OR (@Filter_Status = '1' AND Status = 1 AND Tanggal_Selesai >= CAST(GETDATE() AS DATE))
         OR (@Filter_Status = '0' AND (Status = 0 OR Tanggal_Selesai < CAST(GETDATE() AS DATE)))
        );
END;
GO

-- ============================================================
-- 10. SP: Get Statistics (Aktif, Expired, Total)
-- ============================================================
IF OBJECT_ID('dbo.sp_Promo_GetStats', 'P') IS NOT NULL
    DROP PROCEDURE dbo.sp_Promo_GetStats;
GO

CREATE PROCEDURE dbo.sp_Promo_GetStats
AS
BEGIN
    SET NOCOUNT ON;

    SELECT 
        (SELECT COUNT(*) FROM Promo 
         WHERE Is_Deleted = 0 
         AND Status = 1 
         AND Tanggal_Selesai >= CAST(GETDATE() AS DATE)) AS ActiveCount,

        (SELECT COUNT(*) FROM Promo 
         WHERE Is_Deleted = 0 
         AND (Status = 0 OR Tanggal_Selesai < CAST(GETDATE() AS DATE))) AS ExpiredCount,

        (SELECT COUNT(*) FROM Promo WHERE Is_Deleted = 0) AS TotalCount;
END;
GO

-- ============================================================
-- 11. SP: Get Active Promo List (Untuk Dropdown/Booking)
-- ============================================================
IF OBJECT_ID('dbo.sp_Promo_GetActiveList', 'P') IS NOT NULL
    DROP PROCEDURE dbo.sp_Promo_GetActiveList;
GO

CREATE PROCEDURE dbo.sp_Promo_GetActiveList
AS
BEGIN
    SET NOCOUNT ON;

    SELECT 
        ID_Promo,
        Nama_Promo,
        Diskon,
        CONCAT(CAST(Diskon AS VARCHAR), '%') AS DiskonFormatted,
        Tanggal_Mulai,
        Tanggal_Selesai
    FROM Promo
    WHERE Is_Deleted = 0
    AND Status = 1
    AND Tanggal_Mulai <= CAST(GETDATE() AS DATE)
    AND Tanggal_Selesai >= CAST(GETDATE() AS DATE)
    ORDER BY Nama_Promo ASC;
END;
GO

-- ============================================================
-- 12. SP: Restore Deleted Promo (Opsional)
-- ============================================================
IF OBJECT_ID('dbo.sp_Promo_Restore', 'P') IS NOT NULL
    DROP PROCEDURE dbo.sp_Promo_Restore;
GO

CREATE PROCEDURE dbo.sp_Promo_Restore
    @ID_Promo     INT,
    @Modified_By  VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;

    BEGIN TRY
        BEGIN TRANSACTION;

        -- Validasi: Promo harus ada dan sudah dihapus
        IF NOT EXISTS (SELECT 1 FROM Promo WHERE ID_Promo = @ID_Promo AND Is_Deleted = 1)
        BEGIN
            RAISERROR('Data promo tidak ditemukan atau belum dihapus!', 16, 1);
            RETURN;
        END

        -- Cek duplikat nama setelah restore
        DECLARE @Nama_Promo VARCHAR(50);
        SELECT @Nama_Promo = Nama_Promo FROM Promo WHERE ID_Promo = @ID_Promo;

        IF dbo.udf_CekNamaPromoDuplikat(@Nama_Promo, @ID_Promo) = 1
        BEGIN
            RAISERROR('Nama promo sudah digunakan oleh data aktif lain!', 16, 1);
            RETURN;
        END

        -- Restore
        UPDATE Promo
        SET Is_Deleted = 0,
            Deleted_By = NULL,
            Deleted_Date = NULL,
            Modified_By = @Modified_By,
            Modified_Date = GETDATE()
        WHERE ID_Promo = @ID_Promo;

        COMMIT TRANSACTION;

    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0
            ROLLBACK TRANSACTION;

        DECLARE @ErrorMessage NVARCHAR(4000) = ERROR_MESSAGE();
        DECLARE @ErrorSeverity INT = ERROR_SEVERITY();
        DECLARE @ErrorState INT = ERROR_STATE();

        RAISERROR(@ErrorMessage, @ErrorSeverity, @ErrorState);
    END CATCH
END;
GO

PRINT 'Semua Stored Procedure untuk Master Promo berhasil dibuat!';
GO