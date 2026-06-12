<?php
session_start();
include '../../includes/config.php'; 

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'karyawan') {
    echo "<script>window.location='../../view_admin.php';</script>"; exit();
}

// Get Data Lapangan Aktif untuk Dropdown
$q_lapangan = sqlsrv_query($conn, "SELECT ID_Lapangan, Nama_Lapangan FROM Lapangan WHERE Status = 1 AND Is_Deleted = 0");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $lapangan = $_POST['lapangan'];
    $tanggal = $_POST['tanggal'];
    $jam_m = $_POST['jam_mulai'];
    $jam_s = $_POST['jam_selesai'];
    $user = $_SESSION['nama'] ?? 'System';

    // 1. Validasi Bentrok Jadwal di Database
    $sql_cek = "SELECT * FROM Jadwal WHERE ID_Lapangan = ? AND Tanggal = ? AND Is_Deleted = 0 
                AND (Jam_Mulai < ? AND Jam_Selesai > ?)";
    $params_cek = array($lapangan, $tanggal, $jam_s, $jam_m);
    $q_cek = sqlsrv_query($conn, $sql_cek, $params_cek);

    if (sqlsrv_has_rows($q_cek)) {
        header("Location: ../index.php?status=error&msg=Gagal! Jam bertabrakan dengan jadwal lapangan ini.");
        exit();
    }

    // 2. Generate ID
    $q_id = sqlsrv_query($conn, "SELECT TOP 1 ID_Jadwal FROM Jadwal ORDER BY ID_Jadwal DESC");
    $new_id = "JD0001";
    if ($q_id && $row = sqlsrv_fetch_array($q_id, SQLSRV_FETCH_ASSOC)) {
        $num = (int)substr($row['ID_Jadwal'], 2) + 1;
        $new_id = "JD" . str_pad($num, 4, "0", STR_PAD_LEFT);
    }

    // 3. Insert (Ubah Status_Jadwal jadi Status jika DB kamu beda)
    $sql_in = "INSERT INTO Jadwal (ID_Jadwal, ID_Lapangan, Tanggal, Jam_Mulai, Jam_Selesai, Status, Is_Deleted, Created_By, Created_Date) 
               VALUES (?, ?, ?, ?, ?, 1, 0, ?, GETDATE())";
    $stmt = sqlsrv_query($conn, $sql_in, array($new_id, $lapangan, $tanggal, $jam_m, $jam_s, $user));

    if ($stmt) header("Location: ../index.php?status=success&msg=Jadwal berhasil ditambahkan!");
    else header("Location: ../index.php?status=error&msg=Gagal menyimpan ke database.");
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tambah Jadwal | HoopBall</title>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800;900&family=Barlow:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="../jadwal.css">
</head>
<body>
    <?php include '../toggle/sidebar.php'; ?>
    <main class="main">
        <?php include '../toggle/topbar.php'; ?>
        <div class="content">
            <div class="page-header">
                <div><div class="page-title-tag"></div><div class="page-title">Tambah Jadwal Main</div></div>
            </div>

            <div class="card form-card">
                <form action="" method="POST" onsubmit="return validateJadwal()">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Pilih Lapangan <span style="color:red;">*</span></label>
                            <select name="lapangan" class="form-control" required style="cursor:pointer;">
                                <?php while($lap = sqlsrv_fetch_array($q_lapangan, SQLSRV_FETCH_ASSOC)): ?>
                                    <option value="<?= $lap['ID_Lapangan'] ?>"><?= $lap['Nama_Lapangan'] ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Tanggal Main <span style="color:red;">*</span></label>
                            <input type="date" name="tanggal" id="tanggal" class="form-control" required>
                        </div>
                    </div>
                    
                    <div style="background:#FFF7ED; padding:15px; border-radius:10px; margin-bottom:20px; border:1px solid #FED7AA; font-size:12px; color:#92400E; font-weight:700;">
                        <i class="fa-solid fa-circle-info"></i> INFO: Durasi bermain yang diperbolehkan hanya 1 jam, 1.5 jam, 2 jam, atau 3 jam.
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Jam Mulai <span style="color:red;">*</span></label>
                            <input type="time" name="jam_mulai" id="jam_mulai" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Jam Selesai <span style="color:red;">*</span></label>
                            <input type="time" name="jam_selesai" id="jam_selesai" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <a href="../index.php" class="btn-cancel">Batal</a>
                        <button type="submit" class="btn-submit"><i class="fa-solid fa-save"></i> Simpan Jadwal</button>
                    </div>
                </form>
            </div>
        </div>
    </main>
    <script src="../functions.js"></script>
</body>
</html>