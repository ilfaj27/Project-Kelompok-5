<?php
session_start();
include '../../includes/config.php';

if (!isset($_GET['id'])) { header("Location: ../index.php"); exit(); }
$id = $_GET['id'];

// Get Current Data
$q = sqlsrv_query($conn, "SELECT * FROM Jadwal WHERE ID_Jadwal = ?", array($id));
$data = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC);
if (!$data) { header("Location: ../index.php?status=error&msg=Data tidak ditemukan!"); exit(); }

// Get Lapangan
$q_lapangan = sqlsrv_query($conn, "SELECT ID_Lapangan, Nama_Lapangan FROM Lapangan WHERE Is_Deleted = 0");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $lapangan = $_POST['lapangan'];
    $tanggal = $_POST['tanggal'];
    $jam_m = $_POST['jam_mulai'];
    $jam_s = $_POST['jam_selesai'];
    $user = $_SESSION['nama'] ?? 'System';

    // Cek Bentrok (Kecuali ID dirinya sendiri)
    $sql_cek = "SELECT * FROM Jadwal WHERE ID_Lapangan = ? AND Tanggal = ? AND ID_Jadwal != ? AND Is_Deleted = 0 
                AND (Jam_Mulai < ? AND Jam_Selesai > ?)";
    $q_cek = sqlsrv_query($conn, $sql_cek, array($lapangan, $tanggal, $id, $jam_s, $jam_m));

    if (sqlsrv_has_rows($q_cek)) {
        header("Location: ../index.php?status=error&msg=Gagal! Jam bertabrakan dengan jadwal lapangan ini."); exit();
    }

    $sql = "UPDATE Jadwal SET ID_Lapangan=?, Tanggal=?, Jam_Mulai=?, Jam_Selesai=?, Modified_By=?, Modified_Date=GETDATE() WHERE ID_Jadwal=?";
    $stmt = sqlsrv_query($conn, $sql, array($lapangan, $tanggal, $jam_m, $jam_s, $user, $id));

    if ($stmt) header("Location: ../index.php?status=success&msg=Jadwal berhasil diperbarui!");
    else header("Location: ../index.php?status=error&msg=Gagal update database.");
    exit();
}

$curr_tgl = is_object($data['Tanggal']) ? $data['Tanggal']->format('Y-m-d') : $data['Tanggal'];
$curr_jm = is_object($data['Jam_Mulai']) ? $data['Jam_Mulai']->format('H:i') : substr($data['Jam_Mulai'], 0, 5);
$curr_js = is_object($data['Jam_Selesai']) ? $data['Jam_Selesai']->format('H:i') : substr($data['Jam_Selesai'], 0, 5);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Edit Jadwal | HoopBall</title>
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
                <div><div class="page-title-tag"></div><div class="page-title">Edit Jadwal</div></div>
            </div>

            <div class="card form-card">
                <form action="" method="POST" onsubmit="return validateJadwal()">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Pilih Lapangan</label>
                            <select name="lapangan" class="form-control">
                                <?php while($lap = sqlsrv_fetch_array($q_lapangan, SQLSRV_FETCH_ASSOC)): ?>
                                    <option value="<?= $lap['ID_Lapangan'] ?>" <?= ($data['ID_Lapangan'] == $lap['ID_Lapangan']) ? 'selected' : '' ?>><?= $lap['Nama_Lapangan'] ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Tanggal Main</label>
                            <input type="date" name="tanggal" id="tanggal" class="form-control" value="<?= $curr_tgl ?>" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Jam Mulai</label>
                            <input type="time" name="jam_mulai" id="jam_mulai" class="form-control" value="<?= $curr_jm ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Jam Selesai</label>
                            <input type="time" name="jam_selesai" id="jam_selesai" class="form-control" value="<?= $curr_js ?>" required>
                        </div>
                    </div>
                    <div class="form-actions">
                        <a href="../index.php" class="btn-cancel">Batal</a>
                        <button type="submit" class="btn-submit"><i class="fa-solid fa-save"></i> Update Jadwal</button>
                    </div>
                </form>
            </div>
        </div>
    </main>
    <script src="../functions.js"></script>
</body>
</html>