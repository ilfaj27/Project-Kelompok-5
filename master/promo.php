<?php
session_start();
include '../includes/config.php'; 

// 1. PROTEKSI AKSES
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'karyawan' && $_SESSION['role'] !== 'pemilik')) {
    echo "<script>alert('Akses Ditolak!'); window.location='../dashboard.php';</script>";
    exit();
}

$role = $_SESSION['role'];
$nama_user = $_SESSION['nama'];

// --- 2. LOGIKA PROSES CRUD PROMO (Tetap Sama) ---
if (isset($_POST['add_promo'])) {
    $tgl_m = $_POST['tgl_m'];
    $tgl_s = $_POST['tgl_s'];
    if (strtotime($tgl_s) < strtotime($tgl_m)) {
        header("Location: promo.php?status=error&msg=Tanggal selesai tidak boleh mendahului tanggal mulai!");
        exit();
    }
    $sql = "INSERT INTO Promo (ID_Promo, Nama_Promo, Diskon, Tanggal_Mulai, Tanggal_Selesai) VALUES (?, ?, ?, ?, ?)";
    $params = array($_POST['id'], $_POST['nama'], $_POST['diskon'], $tgl_m, $tgl_s);
    if(sqlsrv_query($conn, $sql, $params)) {
        header("Location: promo.php?status=success&msg=Promo baru berhasil didaftarkan!");
    } else {
        header("Location: promo.php?status=error&msg=Gagal menambah promo. Pastikan ID unik!");
    }
    exit();
}

if (isset($_POST['update_promo'])) {
    $tgl_m = $_POST['tgl_m'];
    $tgl_s = $_POST['tgl_s'];
    if (strtotime($tgl_s) < strtotime($tgl_m)) {
        header("Location: promo.php?status=error&msg=Gagal update! Tanggal selesai tidak valid.");
        exit();
    }
    $sql = "UPDATE Promo SET Nama_Promo=?, Diskon=?, Tanggal_Mulai=?, Tanggal_Selesai=? WHERE ID_Promo=?";
    $params = array($_POST['nama'], $_POST['diskon'], $tgl_m, $tgl_s, $_POST['id']);
    if(sqlsrv_query($conn, $sql, $params)) {
        header("Location: promo.php?status=success&msg=Data promo berhasil diperbarui!");
    } else {
        header("Location: promo.php?status=error&msg=Gagal memperbarui data.");
    }
    exit();
}

if (isset($_GET['delete_id'])) {
    $stmt = sqlsrv_query($conn, "DELETE FROM Promo WHERE ID_Promo = ?", array($_GET['delete_id']));
    header($stmt ? "Location: promo.php?status=success&msg=Promo dihapus." : "Location: promo.php?status=error&msg=Gagal hapus!");
    exit();
}

$edit_data = null;
$show_modal = false;
if (isset($_GET['edit_id'])) {
    $res_edit = sqlsrv_query($conn, "SELECT * FROM Promo WHERE ID_Promo = ?", array($_GET['edit_id']));
    $edit_data = sqlsrv_fetch_array($res_edit, SQLSRV_FETCH_ASSOC);
    $show_modal = true;
}
if (isset($_GET['create']) && $_GET['create'] == '1') {
    $show_modal = true;
}

// STATISTIK
$q_active = sqlsrv_query($conn, "SELECT COUNT(*) as total FROM Promo WHERE Tanggal_Selesai >= GETDATE()");
$active_count = sqlsrv_fetch_array($q_active, SQLSRV_FETCH_ASSOC)['total'] ?? 0;
$q_expired = sqlsrv_query($conn, "SELECT COUNT(*) as total FROM Promo WHERE Tanggal_Selesai < GETDATE()");
$expired_count = sqlsrv_fetch_array($q_expired, SQLSRV_FETCH_ASSOC)['total'] ?? 0;
$q_total = sqlsrv_query($conn, "SELECT COUNT(*) as total FROM Promo");
$total_count = sqlsrv_fetch_array($q_total, SQLSRV_FETCH_ASSOC)['total'] ?? 0;

$query = sqlsrv_query($conn, "SELECT * FROM Promo ORDER BY Tanggal_Selesai DESC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Master Promo | HoopBall</title>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800;900&family=Barlow:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
/* ═══ CSS DISAMAKAN DENGAN LAPANGAN.PHP ═══ */
:root {
    --orange:    #FF4500;
    --orange-lt: rgba(255,69,0,.12);
    --green:     #10B981;
    --blue:      #3B82F6;
    --purple:    #8B5CF6;
    --red:       #EF4444;
    --sidebar:   #0D1117;
    --sidebar-w: 260px;
    --topbar-h:  70px;
    --card-bg:   #fff;
    --border:    #E5E7EB;
    --text:      #111827;
    --muted:     #6B7280;
    --bg:        #F3F4F6;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Barlow', sans-serif; background: var(--bg); display: flex; min-height: 100vh; color: var(--text); }

.sidebar {
    width: var(--sidebar-w); background: var(--sidebar); height: 100vh;
    position: fixed; top: 0; left: 0; display: flex; flex-direction: column;
    padding: 28px 18px; border-right: 1px solid rgba(255,255,255,.04); z-index: 200;
}
.sb-brand { display: flex; align-items: center; gap: 12px; padding: 0 8px; margin-bottom: 36px; text-decoration: none; }
.sb-icon { width: 40px; height: 40px; background: var(--orange); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 18px; flex-shrink: 0; box-shadow: 0 4px 14px rgba(255,69,0,.4); }
.sb-brand-name { font-family: 'Barlow Condensed', sans-serif; font-size: 20px; font-weight: 900; color: #fff; letter-spacing: 1px; }
.sb-brand-sub { font-size: 9px; color: #4B5563; font-weight: 700; text-transform: uppercase; }
.sb-section-label { font-size: 10px; font-weight: 800; text-transform: uppercase; color: #374151; letter-spacing: .8px; padding: 0 10px; margin: 22px 0 8px; }
.sb-link { display: flex; align-items: center; gap: 12px; color: #6B7280; text-decoration: none; padding: 10px 12px; border-radius: 10px; margin-bottom: 2px; font-size: 13px; font-weight: 600; transition: background .2s, color .2s; }
.sb-link .sb-icon-wrap { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 13px; transition: .2s; flex-shrink: 0; background: rgba(255,255,255,.04); }
.sb-link:hover { color: #E5E7EB; }
.sb-link.active { color: #fff; background: rgba(255,69,0,.1); }
.sb-link.active .sb-icon-wrap { background: var(--orange); color: #fff; }
.sb-link .badge { margin-left: auto; background: var(--orange); color: #fff; font-size: 10px; font-weight: 800; padding: 2px 7px; border-radius: 20px; }
.sb-bottom { margin-top: auto; }
.sb-user { display: flex; align-items: center; gap: 10px; background: rgba(255,255,255,.04); border-radius: 12px; padding: 12px; border: 1px solid rgba(255,255,255,.06); }
.sb-avatar { width: 36px; height: 36px; background: var(--orange); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 14px; flex-shrink: 0; }
.sb-user-name { font-size: 13px; font-weight: 800; color: #E5E7EB; line-height: 1.1; }
.sb-user-role { font-size: 10px; color: var(--orange); font-weight: 700; }
.sb-logout { margin-left: auto; color: #4B5563; font-size: 13px; transition: .2s; cursor: pointer; text-decoration: none; }

.main { margin-left: var(--sidebar-w); flex: 1; display: flex; flex-direction: column; min-height: 100vh; }
.topbar { background: var(--card-bg); height: var(--topbar-h); padding: 0 40px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 100; }
.topbar-title { font-family: 'Barlow Condensed', sans-serif; font-size: 26px; font-weight: 900; color: var(--text); }
.content { padding: 32px 40px; flex: 1; }
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; }
.page-title { font-family: 'Barlow Condensed', sans-serif; font-size: 30px; font-weight: 900; color: var(--text); text-transform: uppercase; }
.stat-chips { display: flex; align-items: center; gap: 10px; }
.stat-chip { display: flex; align-items: center; gap: 8px; padding: 8px 18px; border-radius: 10px; font-size: 12px; font-weight: 700; }
.chip-green { background: rgba(16,185,129,.1); color: var(--green); }
.chip-red   { background: rgba(239,68,68,.1);  color: var(--red); }
.chip-val   { font-family: 'Barlow Condensed'; font-size: 20px; font-weight: 900; }
.btn-add { display: flex; align-items: center; gap: 8px; background: var(--text); color: #fff; padding: 11px 22px; border-radius: 10px; font-size: 13px; font-weight: 800; text-decoration: none; text-transform: uppercase; transition: .2s; }

.card { background: var(--card-bg); border-radius: 18px; border: 1px solid var(--border); overflow: hidden; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table th { padding: 13px 20px; font-size: 10px; font-weight: 800; color: var(--muted); text-transform: uppercase; border-bottom: 2px solid var(--bg); text-align: left; }
.data-table td { padding: 16px 20px; font-size: 13px; border-bottom: 1px solid var(--bg); vertical-align: middle; }

/* MODAL */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(5px); display: none; align-items: center; justify-content: center; z-index: 2000; }
.modal-overlay.open { display: flex; }
.modal-box { background: #fff; padding: 40px; border-radius: 20px; width: 480px; position: relative; }
.modal-input { width: 100%; padding: 12px 14px; border: 1.5px solid var(--border); border-radius: 10px; margin-bottom: 16px; font-family: 'Barlow'; }
</style>
</head>
<body>

<!-- MODAL FORM PROMO -->
<div class="modal-overlay <?= $show_modal ? 'open' : '' ?>" id="modalPromo">
    <div class="modal-box">
        <!-- Tombol Close (X) -->
        <button onclick="closeModal()" style="position:absolute; top:20px; right:20px; border:none; background:none; cursor:pointer; font-size:18px; color:var(--muted);">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <!-- Judul Modal -->
        <div style="font-family:'Barlow Condensed'; font-size:24px; font-weight:900; text-transform:uppercase; margin-bottom:15px;">
            <?= $edit_data ? 'Edit Promo' : 'Tambah Promo Baru' ?>
        </div>

        <form method="POST" id="formPromo">
            <!-- ID Promo -->
            <label style="font-size:10px; font-weight:800; color:var(--muted); text-transform:uppercase;">ID Promo</label>
            <input type="text" name="id" class="modal-input" 
                   placeholder="Contoh: PRM001" 
                   value="<?= $edit_data['ID_Promo'] ?? '' ?>" 
                   <?= $edit_data ? 'readonly' : 'required' ?>>
            
            <!-- Nama Promo -->
            <label style="font-size:10px; font-weight:800; color:var(--muted); text-transform:uppercase;">Nama Promo</label>
            <input type="text" name="nama" class="modal-input" 
                   placeholder="Masukkan nama promo (misal: Promo Akhir Tahun)" 
                   value="<?= htmlspecialchars($edit_data['Nama_Promo'] ?? '') ?>" required>
            
            <!-- Diskon -->
            <label style="font-size:10px; font-weight:800; color:var(--muted); text-transform:uppercase;">Diskon (%)</label>
            <input type="number" name="diskon" class="modal-input" 
                   placeholder="0" 
                   value="<?= $edit_data['Diskon'] ?? '' ?>" required>
            
            <!-- Tanggal (Grid 2 Kolom) -->
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div>
                    <label style="font-size:10px; font-weight:800; color:var(--muted); text-transform:uppercase;">Mulai</label>
                    <input type="date" name="tgl_m" id="tgl_m" class="modal-input" 
                           value="<?= isset($edit_data['Tanggal_Mulai']) ? $edit_data['Tanggal_Mulai']->format('Y-m-d') : '' ?>" required>
                </div>
                <div>
                    <label style="font-size:10px; font-weight:800; color:var(--muted); text-transform:uppercase;">Selesai</label>
                    <input type="date" name="tgl_s" id="tgl_s" class="modal-input" 
                           value="<?= isset($edit_data['Tanggal_Selesai']) ? $edit_data['Tanggal_Selesai']->format('Y-m-d') : '' ?>" required>
                </div>
            </div>
            
            <!-- Tombol Submit -->
            <button type="submit" name="<?= $edit_data ? 'update_promo' : 'add_promo' ?>" 
                    style="width:100%; background:var(--orange); color:#fff; border:none; padding:14px; border-radius:10px; font-weight:800; cursor:pointer; text-transform:uppercase; margin-top:10px;">
                <?= $edit_data ? 'SIMPAN PERUBAHAN' : 'TAMBAH' ?>
            </button>

            <!-- Tombol Batal -->
            <a onclick="closeModal()" style="display:block; text-align:center; margin-top:15px; color:var(--muted); text-decoration:none; font-size:13px; cursor:pointer; font-weight:500;">
                Batal
            </a>
        </form>
    </div>
</div>

<!-- SIDEBAR (PERSIS SAMA DENGAN LAPANGAN.PHP) -->
<aside class="sidebar">
    <a href="../dashboard.php" class="sb-brand">
        <div class="sb-icon"><i class="fa-solid fa-basketball"></i></div>
        <div>
            <div class="sb-brand-name">HOOP BALL</div>
            <div class="sb-brand-sub">Management System</div>
        </div>
    </a>
    <div class="sb-section-label">Menu Utama</div>
    <nav>
        <a href="../dashboard.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-house"></i></div> Dashboard
        </a>
        <a href="../kelola_booking.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-calendar-check"></i></div> Booking
            <span class="badge">3</span>
        </a>
        <a href="lapangan.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-layer-group"></i></div> Lapangan
        </a>
        <a href="customer.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-users"></i></div> Customer
        </a>
        <a href="promo.php" class="sb-link active">
            <div class="sb-icon-wrap"><i class="fa-solid fa-tag"></i></div> Promo
        </a>
    </nav>
    <div class="sb-section-label">Akun</div>
    <a href="../profile.php" class="sb-link">
        <div class="sb-icon-wrap"><i class="fa-solid fa-id-badge"></i></div> Profil Saya
    </a>
    <a href="../riwayat.php" class="sb-link">
        <div class="sb-icon-wrap"><i class="fa-solid fa-clock-rotate-left"></i></div> Riwayat
    </a>
    <div class="sb-bottom">
        <div class="sb-user">
            <div class="sb-avatar"><i class="fa-solid fa-user"></i></div>
            <div>
                <div class="sb-user-name"><?= strtoupper(htmlspecialchars($nama_user)) ?></div>
                <div class="sb-user-role"><?= strtoupper($role) ?></div>
            </div>
            <a href="../logout.php" class="sb-logout"><i class="fa-solid fa-right-from-bracket"></i></a>
        </div>
    </div>
</aside>

<!-- MAIN -->
<main class="main">
    <header class="topbar">
        <div style="display:flex; flex-direction:column;">
            <div class="topbar-title">Master Promo</div>
            <div style="font-size:12px; color:var(--muted); font-weight:600;">Dashboard / Promo</div>
        </div>
        <div style="display:flex; align-items:center; gap:16px;">
            <div style="display:flex; align-items:center; gap:10px; background:var(--bg); padding:6px 14px; border-radius:12px; border:1px solid var(--border);">
                <div style="background:var(--orange); color:#fff; width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:13px;"><i class="fa-solid fa-user"></i></div>
                <div>
                    <div style="font-size:13px; font-weight:800;"><?= strtoupper(htmlspecialchars($nama_user)) ?></div>
                    <div style="font-size:10px; color:var(--orange); font-weight:700;"><?= strtoupper($role) ?></div>
                </div>
            </div>
        </div>
    </header>

    <div class="content">
        <div class="page-header">
            <div class="page-title">Daftar Promo</div>
            <div style="display:flex; align-items:center; gap:16px;">
                <div class="stat-chips">
                    <div class="stat-chip chip-green">AKTIF <span class="chip-val"><?= $active_count ?></span></div>
                    <div class="stat-chip chip-red">EXPIRED <span class="chip-val"><?= $expired_count ?></span></div>
                </div>
                <a href="promo.php?create=1" class="btn-add"><i class="fa-solid fa-plus"></i> TAMBAH</a>
            </div>
        </div>

        <div class="card">
            <table class="data-table" id="tbl">
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>ID Promo</th>
                        <th>Nama Promo</th>
                        <th>Diskon</th>
                        <th>Periode</th>
                        <th style="text-align:right;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)): 
                    $is_active = ($row['Tanggal_Selesai'] >= new DateTime());
                ?>
                <tr>
                    <td>
                        <span style="font-size:10px; font-weight:800; padding:5px 12px; border-radius:20px; color:<?= $is_active ? 'var(--green)' : 'var(--red)' ?>; background:<?= $is_active ? 'rgba(16,185,129,.1)' : 'rgba(239,68,68,.1)' ?>">
                            <?= $is_active ? 'AKTIF' : 'EXPIRED' ?>
                        </span>
                    </td>
                    <td style="color:var(--orange); font-weight:800;"><?= htmlspecialchars($row['ID_Promo']) ?></td>
                    <td style="font-weight:700;"><?= htmlspecialchars($row['Nama_Promo']) ?></td>
                    <td style="font-weight:800; color:var(--orange);"><?= (int)$row['Diskon'] ?>%</td>
                    <td style="font-size:12px; color:var(--muted);"><?= $row['Tanggal_Mulai']->format('d M Y') ?> - <?= $row['Tanggal_Selesai']->format('d M Y') ?></td>
                    <td>
                        <div style="display:flex; gap:4px; justify-content:flex-end;">
                            <a href="?edit_id=<?= $row['ID_Promo'] ?>" style="color:var(--muted); font-size:14px; padding:8px;"><i class="fa-solid fa-pen-to-square"></i></a>
                            <a href="javascript:void(0)" onclick="confirmDel('<?= $row['ID_Promo'] ?>')" style="color:var(--red); font-size:14px; padding:8px;"><i class="fa-solid fa-trash-can"></i></a>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<script>
function closeModal() { window.location.href = 'promo.php'; }
function confirmDel(id) {
    Swal.fire({ title: 'Hapus?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Hapus!' })
    .then((result) => { if (result.isConfirmed) window.location.href = `?delete_id=${id}`; });
}
const urlParams = new URLSearchParams(window.location.search);
if(urlParams.get('status')){ Swal.fire({ icon: urlParams.get('status'), title: urlParams.get('msg'), timer: 2000, showConfirmButton: false }); window.history.replaceState({}, '', window.location.pathname); }
</script>
</body>
</html>