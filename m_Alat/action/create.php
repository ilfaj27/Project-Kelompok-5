<?php
session_start();
include '../../includes/config.php'; 

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'karyawan') {
    echo "<script>alert('Akses Ditolak!'); window.location='../../dashboard.php';</script>";
    exit();
}

// Generate Auto ID (AL0001)
$q_id = sqlsrv_query($conn, "SELECT TOP 1 ID_Alat FROM Alat ORDER BY ID_Alat DESC");
$new_id = "AL0001";
if ($q_id && $row = sqlsrv_fetch_array($q_id, SQLSRV_FETCH_ASSOC)) {
    $last_id = $row['ID_Alat'];
    $num = (int)substr($last_id, 2) + 1;
    $new_id = "AL" . str_pad($num, 4, "0", STR_PAD_LEFT);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama = $_POST['nama_alat'];
    $stok = $_POST['stok'];
    $harga = $_POST['harga'];
    $user = $_SESSION['nama'] ?? 'Karyawan';
    
    // Default gambar kosong
    $foto_name = null;

    // Proses Upload Gambar
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === 0) {
        $allowed = ['jpg', 'jpeg', 'png'];
        $filename = $_FILES['foto']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed) && $_FILES['foto']['size'] <= 2097152) { // Max 2MB
            $foto_name = $new_id . "_" . time() . "." . $ext;
            move_uploaded_file($_FILES['foto']['tmp_name'], "../uploads/" . $foto_name);
        } else {
            header("Location: ../../m_Alat/index.php?status=error&msg=Gagal upload! Format gambar salah atau ukuran > 2MB.");
            exit();
        }
    }

    // Insert ke database (Ganti 'Status' menjadi 'Status_Alat' jika struktur database-mu menggunakan Status_Alat)
    $sql = "INSERT INTO Alat (ID_Alat, Nama_Alat, Stok, Harga_Alat, Status, Is_Deleted, Created_By, Created_Date, Foto_Alat) 
            VALUES (?, ?, ?, ?, 1, 0, ?, GETDATE(), ?)";
    $params = array($new_id, $nama, $stok, $harga, $user, $foto_name);
    
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt) {
        header("Location: ../../m_Alat/index.php?status=success&msg=Alat berhasil ditambahkan!");
    } else {
        header("Location: ../../m_Alat/index.php?status=error&msg=Gagal menyimpan ke database.");
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tambah Alat | HoopBall</title>
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
                    <div class="page-title">Tambah Master Alat</div>
                </div>
            </div>

            <div class="card form-card">
                <form action="" method="POST" enctype="multipart/form-data" onsubmit="return validateForm()">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">ID Alat (Otomatis)</label>
                            <input type="text" class="form-control" value="<?= $new_id ?>" readonly style="background:#F3F4F6; font-weight:bold; color:var(--orange);">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Nama Alat <span style="color:red;">*</span></label>
                            <input type="text" name="nama_alat" class="form-control" placeholder="Contoh: Bola Molten BG5000" required maxlength="25">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Stok Awal <span style="color:red;">*</span></label>
                            <input type="number" name="stok" id="stok" class="form-control" value="0" min="0" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Harga per Item (Rp) <span style="color:red;">*</span></label>
                            <input type="number" name="harga" id="harga" class="form-control" value="0" min="1" step="0.01" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Foto Alat (Opsional, Max 2MB, JPG/PNG)</label>
                        <input type="file" name="foto" id="foto" class="form-control" accept="image/jpeg, image/png">
                    </div>
                    
                    <div class="form-actions">
                        <a href="../../m_Alat/index.php" class="btn-cancel">Batal</a>
                        <button type="submit" class="btn-submit"><i class="fa-solid fa-save"></i> Simpan Data</button>
                    </div>
                </form>
            </div>
        </div>
    </main>
    <script src="../functions.js"></script>
</body>
</html>