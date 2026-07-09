-- ============================================================
-- Update_BeliAlat_StatusDitolak.sql
-- Tambah status 2 = DITOLAK untuk Beli_Alat
-- ============================================================
-- Kenapa perlu:
--   Constraint lama: CHECK (Status IN (0,1)) -> cuma Menunggu & Berhasil.
--   Akibatnya tombol "Tolak/Batalkan" lama terpaksa set Status balik ke 0,
--   sehingga pembelian yang ditolak muncul lagi sebagai "Menunggu" dan
--   bisa dikonfirmasi ulang (stok bisa balik dua kali). Ini bug.
--
-- Setelah script ini:
--   0 = Menunggu Konfirmasi
--   1 = Berhasil (dikonfirmasi karyawan)
--   2 = Ditolak (karyawan menolak, stok dikembalikan)
--
-- Jalankan SEKALI di SSMS sebelum pakai pembelian.php versi baru.
-- ============================================================

USE Hoopball;
GO

-- 1. Cari & hapus CHECK constraint lama di kolom Status (namanya auto-generate)
DECLARE @cname SYSNAME;
SELECT TOP 1 @cname = cc.name
FROM sys.check_constraints cc
WHERE cc.parent_object_id = OBJECT_ID('Beli_Alat')
  AND cc.definition LIKE '%Status%';

IF @cname IS NOT NULL
BEGIN
    EXEC('ALTER TABLE Beli_Alat DROP CONSTRAINT [' + @cname + ']');
    PRINT 'Constraint lama dihapus: ' + @cname;
END
GO

-- 2. Pasang constraint baru yang mengizinkan status 2 (Ditolak)
ALTER TABLE Beli_Alat
ADD CONSTRAINT CK_BeliAlat_Status CHECK (Status IN (0, 1, 2));
GO

PRINT 'Selesai. Status Beli_Alat sekarang: 0=Menunggu, 1=Berhasil, 2=Ditolak.';
GO