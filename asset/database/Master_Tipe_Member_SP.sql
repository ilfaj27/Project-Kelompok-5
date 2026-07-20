-- ==========================================
-- A. TABEL LOG & TRIGGER AUDIT HISTORI
-- ==========================================

-- Pembuatan Tabel Log Riwayat Aktivitas Tipe Member
CREATE TABLE Log_Tipe_Member (
    Log_ID INT IDENTITY(1,1) PRIMARY KEY,
    ID_Tipe INT,
    Aksi VARCHAR(50),
    Nama_Tipe_Lama VARCHAR(100),
    Nama_Tipe_Baru VARCHAR(100),
    Waktu_Log DATETIME DEFAULT GETDATE(),
    Pengguna VARCHAR(100)
);
GO

-- Pembuatan Trigger Log History untuk Tipe_Member
CREATE TRIGGER trg_TipeMember_Log
ON Tipe_Member
AFTER INSERT, UPDATE
AS
BEGIN
    SET NOCOUNT ON;
    
    -- Operasi INSERT
    IF EXISTS (SELECT * FROM inserted) AND NOT EXISTS(SELECT * FROM deleted)
    BEGIN
        INSERT INTO Log_Tipe_Member (ID_Tipe, Aksi, Nama_Tipe_Lama, Nama_Tipe_Baru, Pengguna)
        SELECT ID_Tipe, 'INSERT', NULL, Nama_Tipe, Created_By
        FROM inserted;
    END
    -- Operasi UPDATE atau SOFT DELETE
    ELSE IF EXISTS (SELECT * FROM inserted) AND EXISTS(SELECT * FROM deleted)
    BEGIN
        INSERT INTO Log_Tipe_Member (ID_Tipe, Aksi, Nama_Tipe_Lama, Nama_Tipe_Baru, Pengguna)
        SELECT i.ID_Tipe, 
               CASE WHEN i.Is_Deleted = 1 THEN 'DELETE (SOFT)' ELSE 'UPDATE' END, 
               d.Nama_Tipe, 
               i.Nama_Tipe, 
               COALESCE(i.Modified_By, i.Deleted_By, 'SYSTEM')
        FROM inserted i
        INNER JOIN deleted d ON i.ID_Tipe = d.ID_Tipe;
    END
END;
GO


-- ==========================================
-- B. USER DEFINED FUNCTIONS (UDF)
-- ==========================================

-- UDF untuk Menghitung Statistik Tipe Member (Dashboard)
CREATE FUNCTION fn_GetTipeMemberStats()
RETURNS TABLE
AS
RETURN
(
    SELECT 
        (SELECT COUNT(*) FROM Tipe_Member WHERE Is_Deleted = 0 AND Status = 1) AS ActiveCount,
        (SELECT COUNT(*) FROM Tipe_Member WHERE Is_Deleted = 0 AND Status = 0) AS InactiveCount,
        (SELECT COUNT(*) FROM Tipe_Member WHERE Is_Deleted = 0) AS TotalCount
);
GO

-- UDF untuk Menghitung Jumlah Data Sesuai Filter Pencarian (Pagination)
CREATE FUNCTION fn_GetTipeMemberFilteredCount(
    @SearchVal VARCHAR(100),
    @StatusFilter INT
)
RETURNS INT
AS
BEGIN
    DECLARE @Count INT;
    SELECT @Count = COUNT(*) FROM Tipe_Member
    WHERE Is_Deleted = 0
      AND (@StatusFilter IS NULL OR Status = @StatusFilter)
      AND (@SearchVal IS NULL OR Nama_Tipe LIKE @SearchVal);
    RETURN @Count;
END;
GO

-- UDF untuk Cek Duplikasi Nama Tipe Member
CREATE FUNCTION fn_CheckTipeMemberDuplicate(
    @Nama_Tipe VARCHAR(100),
    @ID_Tipe INT
)
RETURNS INT
AS
BEGIN
    DECLARE @Result INT = 0;
    IF EXISTS (SELECT 1 FROM Tipe_Member WHERE Nama_Tipe = @Nama_Tipe AND ID_Tipe <> @ID_Tipe AND Is_Deleted = 0)
        SET @Result = 1;
    RETURN @Result;
END;
GO


-- ==========================================
-- C. STORED PROCEDURES (SP)
-- ==========================================

-- SP untuk Mengambil Daftar Tipe Member (Read List dengan Pagination & Filter)
ALTER PROCEDURE sp_GetTipeMemberList -- Gunakan ALTER jika ingin memperbarui SP yang sudah ada
    @SearchVal VARCHAR(100) = NULL,
    @StatusFilter INT = NULL,
    @SortBy VARCHAR(50) = 'nama_asc',
    @Offset INT = 0,
    @Limit INT = 10
AS
BEGIN
    SET NOCOUNT ON;
    
    SELECT * FROM Tipe_Member
    WHERE Is_Deleted = 0
      AND (@StatusFilter IS NULL OR Status = @StatusFilter)
      AND (@SearchVal IS NULL OR Nama_Tipe LIKE @SearchVal)
    ORDER BY 
        CASE WHEN @SortBy = 'nama_asc' THEN Nama_Tipe END ASC,
        CASE WHEN @SortBy = 'nama_desc' THEN Nama_Tipe END DESC,     -- Tambahkan baris ini
        CASE WHEN @SortBy = 'harga_desc' THEN Harga_Member END DESC,
        CASE WHEN @SortBy = 'harga_asc' THEN Harga_Member END ASC    -- Tambahkan baris ini
    OFFSET @Offset ROWS FETCH NEXT @Limit ROWS ONLY;
END;
GO

-- SP untuk Mengambil Detail Satu Data Tipe Member (Read Detail)
CREATE PROCEDURE sp_GetTipeMemberDetail
    @ID_Tipe INT
AS
BEGIN
    SET NOCOUNT ON;
    SELECT * FROM Tipe_Member WHERE ID_Tipe = @ID_Tipe AND Is_Deleted = 0;
END;
GO

-- SP untuk Menambah Data Tipe Member Baru (Create)
CREATE PROCEDURE sp_InsertTipeMember
    @Nama_Tipe VARCHAR(100),
    @Harga_Member DECIMAL(18,2),
    @Potongan_Harga DECIMAL(18,2),
    @Created_By VARCHAR(100)
AS
BEGIN
    SET NOCOUNT ON;
    INSERT INTO Tipe_Member (Nama_Tipe, Harga_Member, Potongan_Harga, Status, Is_Deleted, Created_By, Created_Date)
    VALUES (@Nama_Tipe, @Harga_Member, @Potongan_Harga, 1, 0, @Created_By, GETDATE());
END;
GO

-- SP untuk Memperbarui Data Tipe Member (Update)
CREATE PROCEDURE sp_UpdateTipeMember
    @ID_Tipe INT,
    @Nama_Tipe VARCHAR(100),
    @Harga_Member DECIMAL(18,2),
    @Potongan_Harga DECIMAL(18,2),
    @Modified_By VARCHAR(100)
AS
BEGIN
    SET NOCOUNT ON;
    UPDATE Tipe_Member
    SET Nama_Tipe = @Nama_Tipe,
        Harga_Member = @Harga_Member,
        Potongan_Harga = @Potongan_Harga,
        Modified_By = @Modified_By,
        Modified_Date = GETDATE()
    WHERE ID_Tipe = @ID_Tipe AND Is_Deleted = 0;
END;
GO

-- SP untuk Mengubah Status Aktif / Nonaktif Tipe Member
CREATE PROCEDURE sp_ToggleTipeMemberStatus
    @ID_Tipe INT,
    @CurrentStatus INT
AS
BEGIN
    SET NOCOUNT ON;
    DECLARE @NewStatus INT = CASE WHEN @CurrentStatus = 1 THEN 0 ELSE 1 END;
    UPDATE Tipe_Member
    SET Status = @NewStatus
    WHERE ID_Tipe = @ID_Tipe AND Is_Deleted = 0;
END;
GO

-- SP untuk Soft Delete Tipe Member (Delete)
CREATE PROCEDURE sp_DeleteTipeMember
    @ID_Tipe INT,
    @Deleted_By VARCHAR(100)
AS
BEGIN
    SET NOCOUNT ON;
    UPDATE Tipe_Member
    SET Is_Deleted = 1,
        Deleted_By = @Deleted_By,
        Deleted_Date = GETDATE()
    WHERE ID_Tipe = @ID_Tipe;
END;
GO
