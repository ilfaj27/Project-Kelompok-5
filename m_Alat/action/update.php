<?php
session_start();
include '../../includes/config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'karyawan') {
    echo "<script>alert('Akses Ditolak!'); window.location='../dashboard.php';</script>";
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: ../index.php"); exit();
}

$id = $_GET['id'];
$q = sqlsrv_query($conn, "SELECT * FROM Alat WHERE ID_Alat = ?", array($id));
$data = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC);

if (!$data) {
    header("Location: ../index.php?status=error&msg=Data tidak ditemukan!"); exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama = $_POST['nama_alat'];
    $stok = $_POST['stok'];
    $harga = $_POST['harga'];
    $user = $_SESSION['nama'] ?? 'Karyawan';
    
    // Pertahankan foto lama
    $foto_name = $data['Foto_Alat'];

    // Jika upload foto baru
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === 0) {
        $allowed = ['jpg', 'jpeg', 'png'];
        $filename = $_FILES['foto']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed) && $_FILES['foto']['size'] <= 2097152) {
            $foto_name = $id . "_" . time() . "." . $ext;
            move_uploaded_file($_FILES['foto']['tmp_name'], "../uploads/" . $foto_name);
            
            // Hapus foto lama jika ada
            if(!empty($data['Foto_Alat']) && file_exists("../uploads/" . $data['Foto_Alat'])) {
                unlink("../uploads/" . $data['Foto_Alat']);
            }
        } else {
            header("Location: ../index.php?status=error&msg=Gagal upload! Format gambar salah atau ukuran > 2MB.");
            exit();
        }
    }

    $sql = "UPDATE Alat SET Nama_Alat=?, Stok=?, Harga_Alat=?, Foto_Alat=?, Modified_By=?, Modified_Date=GETDATE() WHERE ID_Alat=?";
    $params = array($nama, $stok, $harga, $foto_name, $user, $id);
    
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt) {
        header("Location: ../index.php?status=success&msg=Alat berhasil diperbarui!");
    } else {
        header("Location: ../index.php?status=error&msg=Gagal memperbarui ke database.");
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Edit Alat | HoopBall</title>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800;900&family=Barlow:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="../alat.css">
</head>
<body>
    <?php include '../toggle/sidebar.php'; ?>
    <main class="main">
        <?php include '../toggle/topbar.php'; ?>
        <div class="content">
            <div class="page-header">
                <div>
                    <div class="page-title-tag"></div>
                    <div class="page-title">Edit Master Alat</div>
                </div>
            </div>

            <div class="card form-card">
                <form action="" method="POST" enctype="multipart/form-data" onsubmit="return validateForm()">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">ID Alat</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($data['ID_Alat']) ?>" readonly style="background:#F3F4F6; font-weight:bold; color:var(--orange);">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Nama Alat <span style="color:red;">*</span></label>
                            <input type="text" name="nama_alat" class="form-control" value="<?= htmlspecialchars($data['Nama_Alat']) ?>" required maxlength="25">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Stok <span style="color:red;">*</span></label>
                            <input type="number" name="stok" id="stok" class="form-control" value="<?= $data['Stok'] ?>" min="0" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Harga per Item (Rp) <span style="color:red;">*</span></label>
                            <!-- Number format dihapus dari value agar tidak error saat post/edit -->
                            <input type="number" name="harga" id="harga" class="form-control" value="<?= round($data['Harga_Alat']) ?>" min="1" step="0.01" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Ganti Foto Alat (Opsional)</label>
                        <?php if(!empty($data['Foto_Alat'])): ?>
                            <div style="margin-bottom: 10px;">
                                <img src="../uploads/<?= htmlspecialchars($data['Foto_Alat']) ?>" alt="Foto Saat Ini" style="height: 60px; border-radius: 8px; border: 1px solid var(--border);">
                                <span style="font-size:12px; color:var(--muted); margin-left: 10px;">(Foto saat ini)</span>
                            </div>
                        <?php endif; ?>
                        <input type="file" name="foto" id="foto" class="form-control" accept="image/jpeg, image/png">
                    </div>
                    
                    <div class="form-actions">
                        <a href="../index.php" class="btn-cancel">Batal</a>
                        <button type="submit" class="btn-submit"><i class="fa-solid fa-save"></i> Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </main>
    <script src="../functions.js"></script>
</body>
</html>