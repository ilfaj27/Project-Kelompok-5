<?php
session_start();
include '../includes/config.php';

if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'karyawan' && $_SESSION['role'] !== 'pemilik')) {
    echo "<script>alert('Akses Ditolak!'); window.location='../dashboard.php';</script>";
    exit();
}
$role = $_SESSION['role'];
$nama = $_SESSION['nama'];

// --- PROSES CRUD ---
if (isset($_POST['save_lapangan'])) {
    $id   = $_POST['id_lap'];
    $nama_lapangan = $_POST['nama_arena']; 
    $harga = $_POST['harga'];
    
    if (isset($_POST['edit_mode'])) {
        // Perbaikan: Nama_Arena -> Nama_Lapangan
        sqlsrv_query($conn, "UPDATE Lapangan SET Nama_Lapangan=?, Harga_Sewa=? WHERE ID_Lapangan=?", array($nama_lapangan, $harga, $id));
        header("Location: lapangan.php?status=success&msg=Data lapangan berhasil diperbarui!");
    } else {
        $checkID = sqlsrv_query($conn, "SELECT ID_Lapangan FROM Lapangan WHERE ID_Lapangan=?", array($id));
        if (sqlsrv_has_rows($checkID)) { header("Location: lapangan.php?status=error&msg=ID Lapangan sudah ada!"); exit(); }
        
        // Perbaikan: Nama_Arena -> Nama_Lapangan
        sqlsrv_query($conn, "INSERT INTO Lapangan (ID_Lapangan, Nama_Lapangan, Harga_Sewa, Status) VALUES (?,?,?,1)", array($id, $nama_lapangan, $harga));
        header("Location: lapangan.php?status=success&msg=Lapangan baru berhasil ditambahkan!");
    }
    exit();
}

if (isset($_GET['toggle_id'])) {
    $s_baru = ($_GET['s'] == 1) ? 0 : 1;
    sqlsrv_query($conn, "UPDATE Lapangan SET Status=? WHERE ID_Lapangan=?", array($s_baru, $_GET['toggle_id']));
    header("Location: lapangan.php?status=success&msg=Status lapangan berhasil diubah!"); exit();
}

if (isset($_GET['delete_id'])) {
    $stmt = sqlsrv_query($conn, "DELETE FROM Lapangan WHERE ID_Lapangan=?", array($_GET['delete_id']));
    header($stmt ? "Location: lapangan.php?status=success&msg=Lapangan berhasil dihapus!" : "Location: lapangan.php?status=error&msg=Gagal hapus, data masih terikat!");
    exit();
}

$edit_data = null;
if (isset($_GET['edit_id'])) {
    $r = sqlsrv_query($conn, "SELECT * FROM Lapangan WHERE ID_Lapangan=?", array($_GET['edit_id']));
    $edit_data = sqlsrv_fetch_array($r, SQLSRV_FETCH_ASSOC);
}
$show_add = isset($_GET['add']) && $_GET['add'] == '1';

// Hitung Statistik
$q_ready = sqlsrv_query($conn, "SELECT COUNT(*) as t FROM Lapangan WHERE Status=1");
$cnt_ready = sqlsrv_fetch_array($q_ready, SQLSRV_FETCH_ASSOC)['t'] ?? 0;
$q_maint  = sqlsrv_query($conn, "SELECT COUNT(*) as t FROM Lapangan WHERE Status=0");
$cnt_maint = sqlsrv_fetch_array($q_maint, SQLSRV_FETCH_ASSOC)['t'] ?? 0;

// Perbaikan Query: Ambil data dari tabel Lapangan (tanpa JOIN Tipe)
$query = sqlsrv_query($conn, "SELECT * FROM Lapangan ORDER BY ID_Lapangan ASC");

function rupiah($n){ return 'Rp '.number_format($n,0,',','.'); }
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Kelola Lapangan | HoopBall</title>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800;900&family=Barlow:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
/* ═══ CSS ASLI ANDA (Layout Tetap) ═══ */
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
.sb-link:hover .sb-icon-wrap { background: rgba(255,255,255,.08); }
.sb-link.active { color: #fff; background: rgba(255,69,0,.1); }
.sb-link.active .sb-icon-wrap { background: var(--orange); color: #fff; }
.sb-link .badge { margin-left: auto; background: var(--orange); color: #fff; font-size: 10px; font-weight: 800; padding: 2px 7px; border-radius: 20px; }
.sb-bottom { margin-top: auto; }
.sb-user { display: flex; align-items: center; gap: 10px; background: rgba(255,255,255,.04); border-radius: 12px; padding: 12px; border: 1px solid rgba(255,255,255,.06); }
.sb-avatar { width: 36px; height: 36px; background: var(--orange); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 14px; flex-shrink: 0; }
.sb-user-name { font-size: 13px; font-weight: 800; color: #E5E7EB; line-height: 1.1; }
.sb-user-role { font-size: 10px; color: var(--orange); font-weight: 700; }
.sb-logout { margin-left: auto; color: #4B5563; font-size: 13px; transition: .2s; cursor: pointer; text-decoration: none; }

/* Styles lainnya tetap sama */
.main { margin-left: var(--sidebar-w); flex: 1; display: flex; flex-direction: column; min-height: 100vh; }
.topbar { background: var(--card-bg); height: var(--topbar-h); padding: 0 40px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 100; }
.topbar-title { font-family: 'Barlow Condensed', sans-serif; font-size: 26px; font-weight: 900; color: var(--text); }
.topbar-breadcrumb { font-size: 12px; color: var(--muted); font-weight: 600; }
.content { padding: 32px 40px; flex: 1; }
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; }
.page-title { font-family: 'Barlow Condensed', sans-serif; font-size: 30px; font-weight: 900; color: var(--text); text-transform: uppercase; }
.stat-chip { display: flex; align-items: center; gap: 8px; padding: 8px 18px; border-radius: 10px; font-size: 12px; font-weight: 700; }
.chip-green { background: rgba(16,185,129,.1); color: var(--green); }
.chip-red { background: rgba(239,68,68,.1); color: var(--red); }
.chip-val { font-family: 'Barlow Condensed'; font-size: 20px; font-weight: 900; }
.btn-add { display: flex; align-items: center; gap: 8px; background: var(--text); color: #fff; padding: 11px 22px; border-radius: 10px; font-size: 13px; font-weight: 800; text-decoration: none; text-transform: uppercase; }
.search-box { position: relative; width: 300px; margin-bottom: 20px; }
.search-box input { width: 100%; padding: 10px 14px 10px 40px; background: var(--card-bg); border: 1.5px solid var(--border); border-radius: 10px; font-size: 13px; outline: none; }
.card { background: var(--card-bg); border-radius: 18px; border: 1px solid var(--border); overflow: hidden; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table th { padding: 13px 20px; font-size: 10px; font-weight: 800; color: var(--muted); text-transform: uppercase; border-bottom: 2px solid var(--bg); text-align: left; }
.data-table td { padding: 16px 20px; font-size: 13px; border-bottom: 1px solid var(--bg); }
.status-pill { display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 800; }
.sp-ready { background: rgba(16,185,129,.1); color: var(--green); }
.sp-maint { background: rgba(239,68,68,.1); color: var(--red); }
.sp-dot { width: 7px; height: 7px; border-radius: 50%; }
.sp-ready .sp-dot { background: var(--green); }
.sp-maint .sp-dot { background: var(--red); }
.actions { display: flex; gap: 4px; justify-content: flex-end; }
.btn-act { width: 34px; height: 34px; border: none; background: none; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; color: var(--muted); cursor: pointer; transition: .2s; }
.btn-act:hover { background: var(--bg); }
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.55); backdrop-filter: blur(5px); display: flex; justify-content: center; align-items: center; z-index: 2000; }
.modal-overlay.hidden { display: none; }
.modal-box { background: #fff; padding: 40px; border-radius: 20px; width: 480px; position: relative; }
.modal-input { width: 100%; padding: 12px 14px; border: 1.5px solid var(--border); border-radius: 10px; margin-bottom: 16px; }
.btn-submit { width: 100%; background: var(--orange); color: #fff; border: none; padding: 14px; border-radius: 10px; font-weight: 800; cursor: pointer; }
</style>
</head>
<body>

<!-- MODAL -->
<div class="modal-overlay <?= ($edit_data || $show_add) ? '' : 'hidden' ?>" id="modal">
    <div class="modal-box">
        <button class="modal-close" onclick="closeModal()" style="position:absolute; top:20px; right:20px; border:none; background:none; cursor:pointer;"><i class="fa-solid fa-xmark"></i></button>
        <div class="modal-title" style="font-family:'Barlow Condensed'; font-size:24px; font-weight:900; text-transform:uppercase;"><?= $edit_data ? 'Edit Lapangan' : 'Tambah Arena' ?></div>
        <br>
        <form method="POST">
            <?php if ($edit_data): ?><input type="hidden" name="edit_mode" value="1"><?php endif; ?>
            
            <label style="font-size:11px; font-weight:800; color:var(--muted); text-transform:uppercase;">ID Lapangan</label>
            <input type="text" name="id_lap" class="modal-input" value="<?= $edit_data['ID_Lapangan'] ?? '' ?>" <?= $edit_data ? 'readonly' : 'required' ?> placeholder="LAP001">

            <label style="font-size:11px; font-weight:800; color:var(--muted); text-transform:uppercase;">Nama Arena</label>
            <input type="text" name="nama_arena" class="modal-input" value="<?= htmlspecialchars($edit_data['Nama_Lapangan'] ?? '') ?>" required placeholder="Contoh: Basket Indoor Pro">

            <label style="font-size:11px; font-weight:800; color:var(--muted); text-transform:uppercase;">Harga Sewa (Rp)</label>
            <input type="number" name="harga" class="modal-input" value="<?= (int)($edit_data['Harga_Sewa'] ?? 0) ?>" required placeholder="200000">

            <button type="submit" name="save_lapangan" class="btn-submit"><?= $edit_data ? 'Simpan Perubahan' : 'Tambah Lapangan' ?></button>
            <a onclick="closeModal()" style="display:block; text-align:center; margin-top:12px; color:var(--muted); text-decoration:none; font-size:13px; cursor:pointer; font-weight:600;">Batal</a>
        </form>
    </div>
</div>

<!-- ═══ SIDEBAR LENGKAP ═══ -->
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
        <a href="lapangan.php" class="sb-link active">
            <div class="sb-icon-wrap"><i class="fa-solid fa-layer-group"></i></div> Lapangan
        </a>
        <a href="customer.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-users"></i></div> Customer
        </a>
        <a href="promo.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-tags"></i></div> Promo
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
                <div class="sb-user-name"><?= strtoupper(htmlspecialchars($nama)) ?></div>
                <div class="sb-user-role"><?= strtoupper($role) ?></div>
            </div>
            <a href="../logout.php" class="sb-logout" title="Keluar"><i class="fa-solid fa-right-from-bracket"></i></a>
        </div>
    </div>
</aside>

<!-- MAIN -->
<main class="main">
    <header class="topbar">
        <div class="topbar-left">
            <div class="topbar-title">Kelola Lapangan</div>
            <div class="topbar-breadcrumb">Manajemen Arena / Lapangan</div>
        </div>
        <div class="topbar-right" style="display:flex; align-items:center; gap:16px;">
            <a href="#" class="topbar-btn" style="width:38px; height:38px; border:1px solid var(--border); display:flex; align-items:center; justify-content:center; border-radius:10px; color:var(--muted);"><i class="fa-solid fa-magnifying-glass"></i></a>
            <a href="#" class="topbar-btn" style="width:38px; height:38px; border:1px solid var(--border); display:flex; align-items:center; justify-content:center; border-radius:10px; color:var(--muted); position:relative;">
                <i class="fa-solid fa-bell"></i><span style="position:absolute; top:7px; right:7px; width:7px; height:7px; background:var(--orange); border-radius:50%; border:2px solid #fff;"></span>
            </a>
            <div class="topbar-user" style="display:flex; align-items:center; gap:10px; background:var(--bg); padding:6px 14px; border-radius:12px; border:1px solid var(--border);">
                <div style="background:var(--orange); color:#fff; width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:13px;"><i class="fa-solid fa-user"></i></div>
                <div>
                    <div style="font-size:13px; font-weight:800;"><?= strtoupper(htmlspecialchars($nama)) ?></div>
                    <div style="font-size:10px; color:var(--orange); font-weight:700;"><?= strtoupper($role) ?></div>
                </div>
            </div>
        </div>
    </header>

    <div class="content">
        <div class="page-header">
            <div class="page-title">Kelola Lapangan</div>
            <div style="display:flex; align-items:center; gap:16px;">
                <div class="stat-chip chip-green">READY <span class="chip-val"><?= $cnt_ready ?></span></div>
                <div class="stat-chip chip-red">MAINTENANCE <span class="chip-val"><?= $cnt_maint ?></span></div>
                <a href="lapangan.php?add=1" class="btn-add"><i class="fa-solid fa-plus"></i> TAMBAH</a>
            </div>
        </div>

        <div class="search-box">
            <i class="fa-solid fa-magnifying-glass" style="position:absolute; left:14px; top:12px; color:var(--muted);"></i>
            <input type="text" id="src" placeholder="Cari nama arena..." onkeyup="searchTable()">
        </div>

        <div class="card">
            <table class="data-table" id="tbl">
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>ID</th>
                        <th>Nama Lapangan</th>
                        <th>Harga Sewa</th>
                        <th style="text-align:right;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                if ($query) {
                    while ($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)):
                        $is_ready = $row['Status'] == 1;
                ?>
                    <tr>
                        <td><span class="status-pill <?= $is_ready ? 'sp-ready' : 'sp-maint' ?>"><span class="sp-dot"></span><?= $is_ready ? 'READY' : 'MAINTENANCE' ?></span></td>
                        <td style="color:var(--orange); font-weight:800;"><?= htmlspecialchars($row['ID_Lapangan']) ?></td>
                        <td style="font-weight:700;"><?= htmlspecialchars($row['Nama_Lapangan']) ?></td>
                        <td style="font-weight:800;"><?= rupiah($row['Harga_Sewa']) ?></td>
                        <td>
                            <div class="actions">
                                <a href="?edit_id=<?= $row['ID_Lapangan'] ?>" class="btn-act" title="Edit"><i class="fa-solid fa-pen-to-square"></i></a>
                                <a href="javascript:void(0)" onclick="confirmToggle('<?= $row['ID_Lapangan'] ?>','<?= $row['Status'] ?>')" class="btn-act" title="Ubah Status"><i class="fa-solid fa-right-from-bracket"></i></a>
                                <button onclick="confirmDelete('<?= $row['ID_Lapangan'] ?>')" class="btn-act" style="color:var(--red);"><i class="fa-solid fa-trash-can"></i></button>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; } ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<script>
function closeModal() { window.location.href = 'lapangan.php'; }
function searchTable() {
    var input = document.getElementById('src').value.toUpperCase();
    var rows = document.getElementById('tbl').getElementsByTagName('tr');
    for (var i = 1; i < rows.length; i++) {
        var td = rows[i].getElementsByTagName('td')[2];
        if (td) rows[i].style.display = td.textContent.toUpperCase().indexOf(input) > -1 ? '' : 'none';
    }
}
function confirmToggle(id, status) {
    Swal.fire({ title: 'Ubah Status?', icon: 'question', showCancelButton: true, confirmButtonText: 'Ya!' })
    .then((result) => { if (result.isConfirmed) window.location.href = `?toggle_id=${id}&s=${status}`; });
}
function confirmDelete(id) {
    Swal.fire({ title: 'Hapus Data?', text:'Permanen!', icon: 'warning', showCancelButton: true, confirmButtonText: 'Ya, Hapus!' })
    .then((result) => { if (result.isConfirmed) window.location.href = `?delete_id=${id}`; });
}
const urlParams = new URLSearchParams(window.location.search);
if(urlParams.get('status')){ Swal.fire({ icon: urlParams.get('status'), title: urlParams.get('msg'), timer: 2000, showConfirmButton: false }); window.history.replaceState({}, '', window.location.pathname); }
</script>
</body>
</html>