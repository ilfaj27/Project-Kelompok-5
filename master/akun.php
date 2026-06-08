<?php
session_start();
include '../includes/config.php';

// 1. PROTEKSI AKSES
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'pemilik') {
    echo "<script>alert('Akses Ditolak!'); window.location='../dashboard.php';</script>";
    exit();
}

$nama_user = $_SESSION['nama'];
$role_user = $_SESSION['role'];

// --- 2. LOGIKA MAPPING ROLE ---
$current_filter = isset($_GET['role']) ? $_GET['role'] : 'all';
$role_map = ['manajer' => 1, 'karyawan' => 2, 'customer' => 3];

// --- 3. LOGIKA PROSES CRUD ---
if (isset($_POST['create_karyawan'])) {
    $email = $_POST['new_email'];
    $pass  = $_POST['new_password'];
    $checkEmail = sqlsrv_query($conn, "SELECT Email FROM Akun WHERE Email = ?", array($email));
    if (sqlsrv_has_rows($checkEmail)) {
        header("Location: akun.php?role=karyawan&status=error&msg=Email sudah terdaftar!");
        exit();
    }
    $sql_id = "SELECT TOP 1 ID_Akun FROM Akun ORDER BY ID_Akun DESC";
    $query_id = sqlsrv_query($conn, $sql_id);
    $row_id = sqlsrv_fetch_array($query_id, SQLSRV_FETCH_ASSOC);
    $new_id = $row_id ? "AKN" . str_pad((int)substr($row_id['ID_Akun'], 3) + 1, 3, "0", STR_PAD_LEFT) : "AKN001";
    $username = explode('@', $email)[0];
    $sql_cr = "INSERT INTO Akun (ID_Akun, Username, Email, Kata_Sandi, Role, Status_Akun) VALUES (?, ?, ?, ?, 2, 1)";
    if(sqlsrv_query($conn, $sql_cr, array($new_id, $username, $email, $pass))) {
        header("Location: akun.php?role=karyawan&status=success&msg=Akun $new_id berhasil dibuat!");
    } else {
        header("Location: akun.php?role=karyawan&status=error&msg=Gagal Simpan Akun!");
    }
    exit();
}

if (isset($_POST['update_akun'])) {
    $id = $_POST['id_akun'];
    $email = $_POST['email'];
    $pass = $_POST['password'];
    $role = $_POST['role'];
    $sql_up = "UPDATE Akun SET Email=?, Kata_Sandi=?, Role=? WHERE ID_Akun=?";
    sqlsrv_query($conn, $sql_up, array($email, $pass, $role, $id));
    header("Location: akun.php?role=$current_filter&status=success&msg=Data akun diperbarui!");
    exit();
}

if (isset($_GET['toggle_id'])) {
    $status_baru = ($_GET['s'] == 1) ? 0 : 1;
    sqlsrv_query($conn, "UPDATE Akun SET Status_Akun = ? WHERE ID_Akun = ?", array($status_baru, $_GET['toggle_id']));
    header("Location: akun.php?role=$current_filter&status=success&msg=Status akun berhasil diubah!");
    exit();
}

$edit_data = null;
if (isset($_GET['edit_id'])) {
    $res_edit = sqlsrv_query($conn, "SELECT * FROM Akun WHERE ID_Akun = ?", array($_GET['edit_id']));
    $edit_data = sqlsrv_fetch_array($res_edit, SQLSRV_FETCH_ASSOC);
}
$show_create = isset($_GET['create']) && $_GET['create'] == '1' && $current_filter === 'karyawan';

// STATISTIK
$q_active = sqlsrv_query($conn, "SELECT COUNT(*) as total FROM Akun WHERE Status_Akun = 1");
$active_count = sqlsrv_fetch_array($q_active, SQLSRV_FETCH_ASSOC)['total'] ?? 0;
$q_suspended = sqlsrv_query($conn, "SELECT COUNT(*) as total FROM Akun WHERE Status_Akun = 0");
$suspended_count = sqlsrv_fetch_array($q_suspended, SQLSRV_FETCH_ASSOC)['total'] ?? 0;
$q_total = sqlsrv_query($conn, "SELECT COUNT(*) as total FROM Akun");
$total_count = sqlsrv_fetch_array($q_total, SQLSRV_FETCH_ASSOC)['total'] ?? 0;

// QUERY DATA TABEL
if ($current_filter == 'all') {
    $query = sqlsrv_query($conn, "SELECT * FROM Akun ORDER BY Role ASC");
} else {
    $role_id = $role_map[$current_filter] ?? null;
    $query = sqlsrv_query($conn, "SELECT * FROM Akun WHERE Role = ? ORDER BY ID_Akun ASC", array($role_id));
}
$role_label_map = [1 => 'Manajer', 2 => 'Karyawan', 3 => 'Customer'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Kelola Data Akun | HoopBall</title>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800;900&family=Barlow:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
    /* ═══ CSS DISAMAKAN DENGAN KARYAWAN.PHP ═══ */
    :root {
        --orange:    #FF4500;
        --orange-lt: rgba(255,69,0,.12);
        --green:     #10B981;
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

    /* SIDEBAR (Match Karyawan) */
    .sidebar { width: var(--sidebar-w); background: var(--sidebar); height: 100vh; position: fixed; top: 0; left: 0; display: flex; flex-direction: column; padding: 28px 18px; border-right: 1px solid rgba(255,255,255,.04); z-index: 200; }
    .sb-brand { display: flex; align-items: center; gap: 12px; padding: 0 8px; margin-bottom: 36px; text-decoration: none; }
    .sb-icon { width: 40px; height: 40px; background: var(--orange); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 18px; flex-shrink: 0; box-shadow: 0 4px 14px rgba(255,69,0,.4); }
    .sb-brand-name { font-family: 'Barlow Condensed', sans-serif; font-size: 20px; font-weight: 900; color: #fff; letter-spacing: 1px; }
    .sb-brand-sub { font-size: 9px; color: #4B5563; font-weight: 700; text-transform: uppercase; }
    .sb-section-label { font-size: 10px; font-weight: 800; text-transform: uppercase; color: #374151; letter-spacing: .8px; padding: 0 10px; margin: 22px 0 8px; }
    .sb-link { display: flex; align-items: center; gap: 12px; color: #6B7280; text-decoration: none; padding: 10px 12px; border-radius: 10px; margin-bottom: 2px; font-size: 13px; font-weight: 600; transition: .2s; }
    .sb-link .sb-icon-wrap { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 13px; flex-shrink: 0; background: rgba(255,255,255,.04); }
    .sb-link:hover { color: #E5E7EB; }
    .sb-link.active { color: #fff; background: rgba(255,69,0,.1); }
    .sb-link.active .sb-icon-wrap { background: var(--orange); color: #fff; }
    .sb-bottom { margin-top: auto; }
    .sb-user { display: flex; align-items: center; gap: 10px; background: rgba(255,255,255,.04); border-radius: 12px; padding: 12px; border: 1px solid rgba(255,255,255,.06); }
    .sb-avatar { width: 34px; height: 34px; background: var(--orange); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 14px; flex-shrink: 0; }
    .sb-user-name { font-size: 12px; font-weight: 800; color: #E5E7EB; line-height: 1.1; }
    .sb-user-role { font-size: 9px; color: var(--orange); font-weight: 700; text-transform: uppercase; }
    .sb-logout { margin-left: auto; color: #4B5563; font-size: 14px; transition: .2s; cursor: pointer; text-decoration: none; }

    /* MAIN */
    .main { margin-left: var(--sidebar-w); flex: 1; display: flex; flex-direction: column; min-height: 100vh; }

    /* TOPBAR (Match Karyawan) */
    .topbar { background: var(--card-bg); height: var(--topbar-h); padding: 0 40px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 100; }
    .topbar-left { display: flex; flex-direction: column; }
    .topbar-title { font-family: 'Barlow Condensed', sans-serif; font-size: 26px; font-weight: 900; color: var(--text); letter-spacing: -.5px; line-height: 1; }
    .topbar-breadcrumb { font-size: 12px; color: var(--muted); font-weight: 600; margin-top: 2px; }
    .topbar-right { display: flex; align-items: center; gap: 16px; }
    .topbar-btn { width: 38px; height: 38px; border-radius: 10px; background: var(--bg); border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; color: var(--muted); cursor: pointer; font-size: 14px; position: relative; }
    .topbar-user { display: flex; align-items: center; gap: 10px; background: var(--bg); border: 1px solid var(--border); padding: 6px 14px 6px 8px; border-radius: 12px; cursor: pointer; transition: .2s; }
    .t-avatar { width: 32px; height: 32px; background: var(--orange); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 13px; }
    .dropdown-wrap { position: relative; }
    .dropdown-menu { display: none; position: absolute; right: 0; top: calc(100% + 8px); background: #fff; min-width: 180px; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 15px 40px rgba(0,0,0,.12); overflow: hidden; padding: 8px 0; z-index: 999; }
    .dropdown-wrap:hover .dropdown-menu { display: block; }
    .dd-item { display: flex; align-items: center; gap: 10px; padding: 11px 16px; color: #444; text-decoration: none; font-size: 13px; font-weight: 700; transition: .15s; }
    .dd-item:hover { background: #FFF7ED; color: var(--orange); }

    /* CONTENT */
    .content { padding: 32px 40px; flex: 1; }
    .page-header { display: flex; align-items: flex-end; justify-content: space-between; margin-bottom: 24px; }
    .page-title-tag { width: 36px; height: 4px; background: var(--orange); border-radius: 2px; margin-bottom: 8px; }
    .page-title { font-family: 'Barlow Condensed', sans-serif; font-size: 30px; font-weight: 900; color: var(--text); text-transform: uppercase; }
    
    /* STAT CARDS */
    .stat-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 24px; }
    .stat-card { background: var(--card-bg); border: 1px solid var(--border); border-radius: 16px; padding: 20px 24px; display: flex; align-items: center; gap: 16px; }
    .stat-card .icon-wrap { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 18px; }
    .stat-card .icon-wrap.orange { background: rgba(255,69,0,0.1); color: var(--orange); }

    /* TOOLBAR (Filter & Search) */
    .toolbar { background: var(--card-bg); border: 1px solid var(--border); border-radius: 16px 16px 0 0; padding: 16px 24px; display: flex; align-items: center; justify-content: space-between; border-bottom: 2px solid var(--bg); }
    .tab-group { display: flex; gap: 4px; background: var(--bg); padding: 4px; border-radius: 10px; }
    .tab-item { padding: 7px 16px; border-radius: 8px; text-decoration: none; color: var(--muted); font-size: 12px; font-weight: 700; transition: 0.2s; }
    .tab-item.active { background: #fff; color: var(--text); box-shadow: 0 1px 4px rgba(0,0,0,0.1); }
    
    /* SEARCH BOX */
    .search-wrap { position: relative; }
    .search-wrap i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: 12px; }
    .search-input { padding: 9px 12px 9px 34px; border: 1.5px solid var(--border); border-radius: 10px; font-size: 13px; font-family: 'Barlow', sans-serif; color: var(--text); width: 220px; outline: none; transition: 0.2s; }
    .search-input:focus { border-color: var(--orange); }

    .btn-add { background: var(--text); color: #fff; padding: 10px 20px; border-radius: 10px; font-size: 12px; font-weight: 800; text-decoration: none; text-transform: uppercase; transition: 0.2s; border: none; cursor: pointer; }
    .btn-add:hover { background: var(--orange); transform: translateY(-1px); }

    /* TABLE */
    .table-wrap { background: var(--card-bg); border: 1px solid var(--border); border-top: none; border-radius: 0 0 16px 16px; overflow: hidden; margin-bottom: 32px; }
    table { width: 100%; border-collapse: collapse; }
    th { padding: 13px 20px; font-size: 10px; font-weight: 800; color: var(--muted); text-transform: uppercase; border-bottom: 1px solid var(--border); text-align: left; }
    td { padding: 15px 20px; font-size: 13px; border-bottom: 1px solid #F9FAFB; }
    .role-badge { padding: 4px 10px; border-radius: 6px; font-size: 10px; font-weight: 800; text-transform: uppercase; }
    .badge-1 { background: #FEF3C7; color: #92400E; }
    .badge-2 { background: #DBEAFE; color: #1E40AF; }
    .badge-3 { background: #F3F4F6; color: #4B5563; }

    /* MODAL */
    .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(6px); display: flex; align-items: center; justify-content: center; z-index: 2000; }
    .modal-overlay.hidden { display: none; }
    .modal-box { background: #fff; border-radius: 20px; width: 460px; overflow: hidden; box-shadow: 0 25px 60px rgba(0,0,0,0.2); }
    .modal-input { width: 100%; padding: 11px 14px; border: 1.5px solid var(--border); border-radius: 10px; font-size: 13px; margin-bottom: 16px; outline: none; }

    /* CSS untuk memperbaiki tampilan profile di topbar */
/* CSS untuk menyamakan tampilan profile sesuai gambar */
.topbar-user { 
    display: flex; 
    align-items: center; 
    gap: 12px; 
    background: var(--bg); 
    border: 1.5px solid var(--border); 
    padding: 6px 14px 6px 8px; 
    border-radius: 14px; 
    cursor: pointer; 
    transition: .2s;
}

.t-avatar { 
    width: 36px; 
    height: 36px; 
    background: var(--orange); 
    border-radius: 50%; /* Membuat lingkaran sempurna */
    display: flex; 
    align-items: center; 
    justify-content: center; 
    color: #fff; 
    font-size: 14px; 
    flex-shrink: 0;
}

.t-info {
    display: flex;
    flex-direction: column;
    line-height: 1.2;
}

.t-name { 
    font-size: 13px; 
    font-weight: 800; /* Lebih tebal */
    color: #111827; /* Warna gelap/hitam */
    text-transform: uppercase;
}

.t-role { 
    font-size: 11px; 
    color: var(--orange); /* Warna orange sesuai gambar */
    font-weight: 800; /* Tebal sesuai gambar */
    text-transform: uppercase;
}

.t-chevron { 
    color: var(--muted); 
    font-size: 11px; 
    margin-left: 5px; 
}

</style>
</head>
<body>

<!-- MODAL (Logika Tetap) -->
<div class="modal-overlay <?= ($edit_data || $show_create) ? '' : 'hidden' ?>" id="modalAkun">
    <div class="modal-box">
        <div style="padding: 28px 32px 20px; border-bottom: 1px solid var(--border);">
            <div style="font-size:10px; font-weight:800; color:var(--orange); text-transform:uppercase; margin-bottom:6px;">Master Akun</div>
            <h2 style="font-family:'Barlow Condensed'; font-size:22px; font-weight:900;"><?= $edit_data ? 'Edit Akses Akun' : 'Tambah Karyawan Baru' ?></h2>
        </div>
        <div style="padding: 24px 32px 32px;">
            <form method="POST">
                <?php if($edit_data): ?>
                    <input type="hidden" name="id_akun" value="<?= $edit_data['ID_Akun'] ?>">
                    <label style="font-size:11px; font-weight:800; color:var(--muted); text-transform:uppercase; display:block; margin-bottom:6px;">Email</label>
                    <input type="email" name="email" class="modal-input" value="<?= $edit_data['Email'] ?>" required>
                    <label style="font-size:11px; font-weight:800; color:var(--muted); text-transform:uppercase; display:block; margin-bottom:6px;">Password</label>
                    <input type="password" name="password" class="modal-input" value="<?= $edit_data['Kata_Sandi'] ?>" required>
                    <label style="font-size:11px; font-weight:800; color:var(--muted); text-transform:uppercase; display:block; margin-bottom:6px;">Role</label>
                    <select name="role" class="modal-input">
                        <option value="1" <?= $edit_data['Role'] == 1 ? 'selected' : '' ?>>Manajer</option>
                        <option value="2" <?= $edit_data['Role'] == 2 ? 'selected' : '' ?>>Karyawan</option>
                        <option value="3" <?= $edit_data['Role'] == 3 ? 'selected' : '' ?>>Customer</option>
                    </select>
                    <button type="submit" name="update_akun" class="btn-add" style="width:100%; justify-content:center;">Simpan Perubahan</button>
                <?php else: ?>
                    <label style="font-size:11px; font-weight:800; color:var(--muted); text-transform:uppercase; display:block; margin-bottom:6px;">Email Karyawan</label>
                    <input type="email" name="new_email" class="modal-input" placeholder="email@hoopball.com" required>
                    <label style="font-size:11px; font-weight:800; color:var(--muted); text-transform:uppercase; display:block; margin-bottom:6px;">Password</label>
                    <input type="password" name="new_password" class="modal-input" placeholder="Minimal 6 karakter" required minlength="6">
                    <button type="submit" name="create_karyawan" class="btn-add" style="width:100%; justify-content:center;">Buat Akun Karyawan</button>
                <?php endif; ?>
                <a href="akun.php?role=<?= $current_filter ?>" style="display:block; text-align:center; margin-top:12px; color:var(--muted); font-size:12px; text-decoration:none; font-weight:600;">Batal</a>
            </form>
        </div>
    </div>
</div>

<!-- SIDEBAR (Match Karyawan) -->
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
        <a href="#" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-calendar-check"></i></div> Booking
        </a>
        <div class="sb-section-label">Owner Only</div>
        <a href="akun.php" class="sb-link active">
            <div class="sb-icon-wrap"><i class="fa-solid fa-user-shield"></i></div> Kelola Data Akun
        </a>
        <a href="karyawan.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-user-tie"></i></div> Kelola Data Karyawan
        </a>
        <a href="alat.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-toolbox"></i></div> Kelola Alat
        </a>
        <a href="../laporan/omzet.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-chart-line"></i></div> Laporan
        </a>
    </nav>
    <div class="sb-section-label">Akun</div>
    <a href="../profile.php" class="sb-link">
        <div class="sb-icon-wrap"><i class="fa-solid fa-id-badge"></i></div> Profil Saya
    </a>
    <div class="sb-bottom">
        <div class="sb-user">
            <div class="sb-avatar"><i class="fa-solid fa-user"></i></div>
            <div class="info">
                <div class="sb-user-name"><?= strtoupper(htmlspecialchars($nama_user)) ?></div>
                <div class="sb-user-role">PEMILIK</div>
            </div>
            <a href="../logout.php" class="sb-logout"><i class="fa-solid fa-right-from-bracket"></i></a>
        </div>
    </div>
</aside>

<!-- MAIN -->
<main class="main">
    <header class="topbar">
        <div class="topbar-left">
            <div class="topbar-title">Kelola Data Akun</div>
            <div class="topbar-breadcrumb">Dashboard / Manajemen Akun</div>
        </div>
        <div class="topbar-right">
            <a href="#" class="topbar-btn"><i class="fa-solid fa-magnifying-glass"></i></a>
            <a href="#" class="topbar-btn"><i class="fa-solid fa-bell"></i><span style="position:absolute; top:7px; right:7px; width:7px; height:7px; background:var(--orange); border-radius:50%; border:2px solid #fff;"></span></a>
            <div class="dropdown-wrap">
                <div class="topbar-user">
                    <div class="t-avatar">
                        <i class="fa-solid fa-user"></i>
                    </div>
                    <div class="t-info">
                        <div class="t-name"><?= htmlspecialchars($nama_user) ?></div>
                        <div class="t-role"><?= strtoupper($role_user) ?></div>
                    </div>
                    <i class="fa-solid fa-chevron-down t-chevron"></i>
                </div>
                
                <div class="dropdown-menu">
                    <a href="../profile.php" class="dd-item"><i class="fa-solid fa-id-badge"></i> Profil Saya</a>
                    <hr style="border:none; border-top:1px solid #F3F4F6; margin:4px 0;">
                    <a href="../logout.php" class="dd-item" style="color:var(--red);"><i class="fa-solid fa-right-from-bracket"></i> Keluar</a>
                </div>
            </div>
        </div>
    </header>

    <div class="content">
        <!-- PAGE HEADER -->
        <div class="page-header">
            <div>
                <div class="page-title-tag"></div>
                <div class="page-title">Master Akun</div>
            </div>
        </div>

        <!-- STAT CARDS -->
        <div class="stat-grid">
            <div class="stat-card">
                <div class="icon-wrap orange"><i class="fa-solid fa-users"></i></div>
                <div><div style="font-size:11px; color:var(--text-faint); font-weight:700;">Total Akun</div><div style="font-family:'Barlow Condensed'; font-size:28px; font-weight:900;"><?= $total_count ?></div></div>
            </div>
            <div class="stat-card">
                <div class="icon-wrap" style="background:rgba(16,185,129,0.1); color:var(--green);"><i class="fa-solid fa-circle-check"></i></div>
                <div><div style="font-size:11px; color:var(--text-faint); font-weight:700;">Akun Aktif</div><div style="font-family:'Barlow Condensed'; font-size:28px; font-weight:900;"><?= $active_count ?></div></div>
            </div>
            <div class="stat-card">
                <div class="icon-wrap" style="background:rgba(239,68,68,0.1); color:var(--red);"><i class="fa-solid fa-ban"></i></div>
                <div><div style="font-size:11px; color:var(--text-faint); font-weight:700;">Suspended</div><div style="font-family:'Barlow Condensed'; font-size:28px; font-weight:900;"><?= $suspended_count ?></div></div>
            </div>
        </div>

        <!-- TOOLBAR (FILTER + SEARCH) -->
        <div class="toolbar">
            <div class="tab-group">
                <a href="akun.php?role=all"      class="tab-item <?= $current_filter == 'all' ? 'active' : '' ?>">Semua</a>
                <a href="akun.php?role=manajer"  class="tab-item <?= $current_filter == 'manajer' ? 'active' : '' ?>">Manajer</a>
                <a href="akun.php?role=karyawan" class="tab-item <?= $current_filter == 'karyawan' ? 'active' : '' ?>">Karyawan</a>
                <a href="akun.php?role=customer" class="tab-item <?= $current_filter == 'customer' ? 'active' : '' ?>">Customer</a>
            </div>
            <div style="display:flex; align-items:center; gap:12px;">
                <div class="search-wrap">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" class="search-input" id="src" placeholder="Cari email..." onkeyup="searchTable()">
                </div>
                <?php if($current_filter === 'karyawan'): ?>
                    <a href="akun.php?role=karyawan&create=1" class="btn-add"><i class="fa-solid fa-plus"></i> Tambah</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="table-wrap">
            <table id="tbl">
                <thead>
                    <tr><th>Status</th><th>ID Akun</th><th>Email</th><th>Hak Akses</th><th style="text-align:right;">Aksi</th></tr>
                </thead>
                <tbody>
                    <?php while($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)): $is_active = $row['Status_Akun'] == 1; ?>
                    <tr>
                        <td><div style="display:flex; align-items:center; gap:8px;"><span style="width:8px; height:8px; border-radius:50%; background:<?= $is_active ? 'var(--green)' : 'var(--red)' ?>"></span><span style="font-size:11px; font-weight:800; color:<?= $is_active ? 'var(--green)' : 'var(--red)' ?>"><?= $is_active ? 'Aktif' : 'Suspended' ?></span></div></td>
                        <td style="font-family:'Barlow Condensed'; font-weight:800; color:var(--orange); font-size:15px;"><?= $row['ID_Akun'] ?></td>
                        <td style="font-weight:600;"><?= $row['Email'] ?></td>
                        <td><span class="role-badge badge-<?= $row['Role'] ?>"><?= $role_label_map[$row['Role']] ?></span></td>
                        <td style="text-align:right;">
                            <div style="display:flex; justify-content:flex-end; gap:4px;">
                                <a href="?role=<?= $current_filter ?>&edit_id=<?= $row['ID_Akun'] ?>" style="color:var(--muted); padding:8px;"><i class="fa-solid fa-pen-to-square"></i></a>
                                <a href="javascript:void(0)" onclick="confirmStatus('<?= $row['ID_Akun'] ?>', '<?= $row['Status_Akun'] ?>')" style="color:<?= $is_active ? 'var(--red)' : 'var(--green)' ?>; padding:8px;"><i class="fa-solid <?= $is_active ? 'fa-ban' : 'fa-circle-check' ?>"></i></a>
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
function searchTable() {
    var input = document.getElementById('src').value.toUpperCase();
    var rows = document.getElementById('tbl').getElementsByTagName('tr');
    for (var i = 1; i < rows.length; i++) {
        var td = rows[i].getElementsByTagName('td')[2]; // Kolom Email
        if (td) rows[i].style.display = td.textContent.toUpperCase().indexOf(input) > -1 ? '' : 'none';
    }
}

function confirmStatus(id, current) {
    const act = (current == 1) ? 'Tangguhkan' : 'Aktifkan';
    Swal.fire({ title: act + ' Akun?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Ya!', confirmButtonColor: '#FF4500' })
    .then((result) => { if(result.isConfirmed) window.location.href = `?role=<?= $current_filter ?>&toggle_id=${id}&s=${current}`; });
}
const urlParams = new URLSearchParams(window.location.search);
if(urlParams.get('status')){ Swal.fire({ icon: urlParams.get('status'), title: urlParams.get('msg'), showConfirmButton: false, timer: 2000 }); window.history.replaceState({}, '', window.location.pathname + "?role=<?= $current_filter ?>"); }
</script>
</body>
</html>