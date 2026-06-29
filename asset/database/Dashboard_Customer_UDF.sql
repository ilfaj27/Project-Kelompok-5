-- 1. UDF: Mengambil jumlah lapangan aktif (Scalar)
CREATE OR ALTER FUNCTION dbo.fn_GetActiveCourtCount()
RETURNS INT
AS
BEGIN
    DECLARE @Count INT;
    SELECT @Count = COUNT(*) FROM dbo.Lapangan WHERE Status = 1 AND Is_Deleted = 0;
    RETURN ISNULL(@Count, 0);
END;
GO