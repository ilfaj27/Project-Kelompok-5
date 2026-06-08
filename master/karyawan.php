<?php
session_start();
include '../includes/config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'pemilik') {
    echo "<script>alert('Akses Ditolak!'); window.location='../dashboard.php';</script>";
    exit();
}
$nama = $_SESSION['nama'];
$role = $_SESSION['role'];

$map_jabatan = [1=>'Admin Utama', 2=>'Pemilik / Owner', 3=>'Kasir Pembayaran', 4=>'Staf Operasional', 5=>'Keamanan'];

if (isset($_POST['add_karyawan'])) {
    $id_kry = $_POST['id_kry']; $nama_kry = $_POST['nama']; $jk = $_POST['jk']; $jabatan = $_POST['jabatan']; $telp = $_POST['telp']; $id_akun = $_POST['id_akun'];
    $checkID = sqlsrv_query($conn, "SELECT ID_Karyawan FROM Karyawan WHERE ID_Karyawan=?", array($id_kry));
    if (sqlsrv_has_rows($checkID)) { header("Location: karyawan.php?status=error&msg=ID Karyawan sudah terdaftar!"); exit(); }
    $stmt = sqlsrv_query($conn, "INSERT INTO Karyawan (ID_Karyawan,ID_Akun,Nama_Karyawan,Jenis_Kelamin,Jabatan,No_Telepon) VALUES (?,?,?,?,?,?)", array($id_kry,$id_akun,$nama_kry,$jk,$jabatan,$telp));
    header($stmt ? "Location: karyawan.php?status=success&msg=Karyawan baru berhasil didaftarkan!" : "Location: karyawan.php?status=error&msg=Gagal menambahkan data!"); exit();
}
if (isset($_POST['update_karyawan'])) {
    $stmt = sqlsrv_query($conn, "UPDATE Karyawan SET Nama_Karyawan=?,Jenis_Kelamin=?,Jabatan=?,No_Telepon=? WHERE ID_Karyawan=?", array($_POST['nama'],$_POST['jk'],$_POST['jabatan'],$_POST['telp'],$_POST['id_kry']));
    header($stmt ? "Location: karyawan.php?status=success&msg=Data staf berhasil diperbarui!" : "Location: karyawan.php?status=error&msg=Gagal memperbarui data!"); exit();
}
if (isset($_GET['delete_id'])) {
    $stmt = sqlsrv_query($conn, "DELETE FROM Karyawan WHERE ID_Karyawan=?", array($_GET['delete_id']));
    header($stmt ? "Location: karyawan.php?status=success&msg=Data karyawan dihapus permanen!" : "Location: karyawan.php?status=error&msg=Gagal hapus, data mungkin masih terikat!"); exit();
}

$edit_data = null;
if (isset($_GET['edit_id'])) {
    $r = sqlsrv_query($conn, "SELECT * FROM Karyawan WHERE ID_Karyawan=?", array($_GET['edit_id']));
    $edit_data = sqlsrv_fetch_array($r, SQLSRV_FETCH_ASSOC);
}
$show_add = isset($_GET['add']) && $_GET['add'] == '1';

$q_akun_bebas = sqlsrv_query($conn, "SELECT A.ID_Akun,A.Email FROM Akun A WHERE A.ID_Akun NOT IN (SELECT ID_Akun FROM Karyawan WHERE ID_Akun IS NOT NULL) AND A.Role != 3 ORDER BY A.ID_Akun ASC");
$akun_bebas = [];
if ($q_akun_bebas) while ($ak = sqlsrv_fetch_array($q_akun_bebas, SQLSRV_FETCH_ASSOC)) $akun_bebas[] = $ak;

$q_total = sqlsrv_query($conn, "SELECT COUNT(*) as t FROM Karyawan");
$total_kry = sqlsrv_fetch_array($q_total, SQLSRV_FETCH_ASSOC)['t'] ?? 0;

$query = sqlsrv_query($conn, "SELECT * FROM Karyawan ORDER BY ID_Karyawan ASC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Kelola Karyawan | HoopBall</title>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800;900&family=Barlow:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
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

/* SIDEBAR */
.sidebar { width: var(--sidebar-w); background: var(--sidebar); height: 100vh; position: fixed; top: 0; left: 0; display: flex; flex-direction: column; padding: 28px 18px; border-right: 1px solid rgba(255,255,255,.04); z-index: 200; }
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
.sb-bottom { margin-top: auto; }
.sb-user { display: flex; align-items: center; gap: 10px; background: rgba(255,255,255,.04); border-radius: 12px; padding: 12px; border: 1px solid rgba(255,255,255,.06); }
.sb-avatar { width: 36px; height: 36px; background: var(--orange); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 14px; flex-shrink: 0; }
.sb-user-name { font-size: 13px; font-weight: 800; color: #E5E7EB; line-height: 1.1; }
.sb-user-role { font-size: 10px; color: var(--orange); font-weight: 700; }
.sb-logout { margin-left: auto; color: #4B5563; font-size: 13px; transition: .2s; cursor: pointer; text-decoration: none; }
.sb-logout:hover { color: var(--red); }

/* MAIN */
.main { margin-left: var(--sidebar-w); flex: 1; display: flex; flex-direction: column; min-height: 100vh; }

/* TOPBAR */
.topbar { background: var(--card-bg); height: var(--topbar-h); padding: 0 40px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 100; }
.topbar-left { display: flex; flex-direction: column; }
.topbar-title { font-family: 'Barlow Condensed', sans-serif; font-size: 26px; font-weight: 900; color: var(--text); letter-spacing: -.5px; line-height: 1; }
.topbar-breadcrumb { font-size: 12px; color: var(--muted); font-weight: 600; margin-top: 2px; }
.topbar-right { display: flex; align-items: center; gap: 16px; }
.topbar-btn { width: 38px; height: 38px; border-radius: 10px; background: var(--bg); border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; color: var(--muted); cursor: pointer; font-size: 14px; text-decoration: none; transition: .2s; position: relative; }
.topbar-btn:hover { border-color: var(--orange); color: var(--orange); }
.notif-dot { position: absolute; top: 7px; right: 7px; width: 7px; height: 7px; background: var(--orange); border-radius: 50%; border: 2px solid #fff; }
.dropdown-wrap { position: relative; }
.topbar-user { display: flex; align-items: center; gap: 10px; background: var(--bg); border: 1px solid var(--border); padding: 6px 14px 6px 8px; border-radius: 12px; cursor: pointer; transition: .2s; }
.topbar-user:hover { border-color: var(--orange); }
.t-avatar { width: 32px; height: 32px; background: var(--orange); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 13px; }
.t-name { font-size: 13px; font-weight: 800; color: var(--text); line-height: 1.1; }
.t-role { font-size: 10px; color: var(--orange); font-weight: 700; }
.t-chevron { color: var(--muted); font-size: 10px; margin-left: 4px; }
.dropdown-menu { display: none; position: absolute; right: 0; top: calc(100% + 8px); background: #fff; min-width: 180px; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 15px 40px rgba(0,0,0,.12); overflow: hidden; padding: 8px 0; z-index: 999; }
.dropdown-wrap:hover .dropdown-menu { display: block; }
.dd-item { display: flex; align-items: center; gap: 10px; padding: 11px 16px; color: #444; text-decoration: none; font-size: 13px; font-weight: 700; transition: .15s; }
.dd-item:hover { background: #FFF7ED; color: var(--orange); }
.dd-divider { border: none; border-top: 1px solid #F3F4F6; margin: 4px 0; }

/* CONTENT */
.content { padding: 32px 40px; flex: 1; }
.page-header { display: flex; align-items: flex-end; justify-content: space-between; margin-bottom: 24px; }
.page-title-tag { width: 36px; height: 4px; background: var(--orange); border-radius: 2px; margin-bottom: 8px; }
.page-title { font-family: 'Barlow Condensed', sans-serif; font-size: 30px; font-weight: 900; color: var(--text); text-transform: uppercase; }

/* STAT CHIPS */
.stat-chips { display: flex; gap: 10px; align-items: center; }
.stat-chip { display: flex; align-items: center; gap: 8px; padding: 8px 18px; border-radius: 10px; font-size: 12px; font-weight: 700; }
.chip-purple { background: rgba(139,92,246,.1); color: var(--purple); }
.chip-val    { font-family: 'Barlow Condensed'; font-size: 20px; font-weight: 900; }

.btn-add { display: flex; align-items: center; gap: 8px; background: var(--text); color: #fff; padding: 11px 22px; border-radius: 10px; font-size: 13px; font-weight: 800; border: none; cursor: pointer; text-transform: uppercase; letter-spacing: .5px; transition: .2s; }
.btn-add:hover { background: var(--orange); transform: translateY(-1px); }

/* TABLE */
.card { background: var(--card-bg); border-radius: 18px; border: 1px solid var(--border); overflow: hidden; }
.table-wrap { overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table th { padding: 13px 20px; font-size: 10px; font-weight: 800; color: var(--muted); text-transform: uppercase; letter-spacing: .6px; border-bottom: 2px solid var(--bg); text-align: left; }
.data-table td { padding: 16px 20px; font-size: 13px; border-bottom: 1px solid var(--bg); vertical-align: middle; }
.data-table tr:last-child td { border-bottom: none; }
.data-table tbody tr:hover td { background: #FAFAFA; }
.emp-id   { color: var(--orange); font-weight: 800; font-family: 'Barlow Condensed'; font-size: 16px; }
.emp-name { font-weight: 700; color: var(--text); }
.jabatan-badge { background: #EEF2FF; color: #4338CA; padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; }
.actions { display: flex; gap: 4px; justify-content: flex-end; }
.btn-act { width: 34px; height: 34px; border: none; background: none; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; color: var(--muted); text-decoration: none; cursor: pointer; font-size: 14px; transition: .2s; }
.btn-act:hover      { background: var(--bg); }
.btn-act.edit:hover { color: var(--orange); }
.btn-act.del:hover  { color: var(--red); background: #FEF2F2; }

/* MODAL */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.55); backdrop-filter: blur(5px); display: flex; justify-content: center; align-items: center; z-index: 2000; }
.modal-overlay.hidden { display: none; }
.modal-box { background: #fff; padding: 40px; border-radius: 20px; width: 560px; max-width: 95vw; box-shadow: 0 25px 60px rgba(0,0,0,.2); position: relative; max-height: 90vh; overflow-y: auto; }
.modal-close { position: absolute; top: 20px; right: 20px; background: none; border: none; font-size: 20px; color: var(--muted); cursor: pointer; transition: .2s; }
.modal-close:hover { color: var(--red); }
.modal-title { font-family: 'Barlow Condensed', sans-serif; font-size: 24px; font-weight: 900; color: var(--text); text-transform: uppercase; margin-bottom: 4px; }
.modal-sub   { font-size: 13px; color: var(--muted); margin-bottom: 24px; }
.form-grid   { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.full-width  { grid-column: span 2; }
.field-label { font-size: 11px; font-weight: 800; color: var(--muted); text-transform: uppercase; letter-spacing: .5px; display: block; margin-bottom: 6px; }
.modal-input { width: 100%; padding: 12px 14px; border: 1.5px solid var(--border); border-radius: 10px; font-size: 14px; font-family: 'Barlow', sans-serif; transition: .2s; background: #FAFAFA; }
.modal-input:focus { outline: none; border-color: var(--orange); background: #fff; box-shadow: 0 0 0 3px rgba(255,69,0,.1); }
.akun-notice { background: #FFF7ED; border: 1px solid #FED7AA; border-radius: 10px; padding: 12px 15px; font-size: 12px; color: #92400E; font-weight: 600; display: flex; align-items: center; gap: 8px; margin-bottom: 20px; grid-column: span 2; }
.btn-submit { grid-column: span 2; width: 100%; background: var(--orange); color: #fff; border: none; padding: 14px; border-radius: 10px; font-weight: 800; font-size: 14px; cursor: pointer; text-transform: uppercase; letter-spacing: .5px; transition: .2s; font-family: 'Barlow', sans-serif; margin-top: 8px; }
.btn-submit:hover { background: #e03d00; }
.btn-submit:disabled { background: #ccc; cursor: not-allowed; }
</style>
</head>
<body>

<!-- ═══ MODAL ═══ -->
<div class="modal-overlay <?= ($edit_data || $show_add) ? '' : 'hidden' ?>" id="modal">
    <div class="modal-box">
        <button class="modal-close" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></button>
        <div class="modal-title"><?= $edit_data ? 'Edit Data Staf' : 'Tambah Staf Baru' ?></div>
        <div class="modal-sub"><?= $edit_data ? 'Perbarui informasi karyawan' : 'Daftarkan karyawan baru ke sistem' ?></div>
        <form method="POST">
            <div class="form-grid">
                <?php if (!$edit_data && empty($akun_bebas)): ?>
                <div class="akun-notice">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    Tidak ada akun tersedia. <a href="akun.php" style="color:var(--orange);font-weight:800;">Buat akun baru</a> terlebih dahulu.
                </div>
                <?php endif; ?>
                <div>
                    <label class="field-label">ID Karyawan</label>
                    <input type="text" name="id_kry" class="modal-input" value="<?= $edit_data['ID_Karyawan'] ?? '' ?>" <?= $edit_data ? 'readonly' : 'required' ?> placeholder="KRY001">
                </div>
                <div>
                    <label class="field-label">Nama Lengkap</label>
                    <input type="text" name="nama" class="modal-input" value="<?= htmlspecialchars($edit_data['Nama_Karyawan'] ?? '') ?>" required placeholder="Nama lengkap...">
                </div>
                <div>
                    <label class="field-label">Jenis Kelamin</label>
                    <select name="jk" class="modal-input">
                        <option value="1" <?= (isset($edit_data['Jenis_Kelamin']) && $edit_data['Jenis_Kelamin']==1)?'selected':''?>>Laki-laki</option>
                        <option value="2" <?= (isset($edit_data['Jenis_Kelamin']) && $edit_data['Jenis_Kelamin']==2)?'selected':''?>>Perempuan</option>
                    </select>
                </div>
                <div>
                    <label class="field-label">Jabatan</label>
                    <select name="jabatan" class="modal-input">
                        <?php foreach ($map_jabatan as $id => $val): ?>
                        <option value="<?= $id ?>" <?= (isset($edit_data['Jabatan']) && $edit_data['Jabatan']==$id)?'selected':''?>><?= $val ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if (!$edit_data): ?>
                <div class="full-width">
                    <label class="field-label">Pilih Akun Terhubung <span style="color:var(--orange);">*</span></label>
                    <select name="id_akun" class="modal-input" required <?= empty($akun_bebas) ? 'disabled' : '' ?>>
                        <option value="">-- Pilih Akun yang Belum Dipakai --</option>
                        <?php foreach ($akun_bebas as $ak): ?>
                        <option value="<?= $ak['ID_Akun'] ?>"><?= $ak['ID_Akun'] ?> — <?= htmlspecialchars($ak['Email']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="full-width">
                    <label class="field-label">Nomor Telepon</label>
                    <input type="text" name="telp" class="modal-input" value="<?= htmlspecialchars($edit_data['No_Telepon'] ?? '') ?>" placeholder="08123456789" required pattern="[0-9]{10,14}">
                </div>
                <button type="submit" name="<?= $edit_data ? 'update_karyawan' : 'add_karyawan' ?>" class="btn-submit" <?= (!$edit_data && empty($akun_bebas)) ? 'disabled' : '' ?>>
                    <?= $edit_data ? 'Simpan Perubahan' : 'Daftarkan Karyawan' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ═══ SIDEBAR ═══ -->
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
        <a href="akun.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-user-shield"></i></div> Kelola Data Akun
        </a>
        <a href="karyawan.php" class="sb-link active">
            <div class="sb-icon-wrap"><i class="fa-solid fa-user-tie"></i></div> Kelola Data Karyawan
        </a>
        <a href="supplier.php" class="sb-link">
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
            <div>
                <div class="sb-user-name"><?= strtoupper(htmlspecialchars($nama)) ?></div>
                <div class="sb-user-role">PEMILIK</div>
            </div>
            <a href="../logout.php" class="sb-logout" title="Keluar"><i class="fa-solid fa-right-from-bracket"></i></a>
        </div>
    </div>
</aside>

<!-- ═══ MAIN ═══ -->
<main class="main">
    <header class="topbar">
        <div class="topbar-left">
            <div class="topbar-title">Kelola Data Karyawan</div>
            <div class="topbar-breadcrumb">Manajemen Karyawan</div>
        </div>
        <div class="topbar-right">
            <a href="#" class="topbar-btn"><i class="fa-solid fa-magnifying-glass"></i></a>
            <a href="#" class="topbar-btn">
                <i class="fa-solid fa-bell"></i>
                <span class="notif-dot"></span>
            </a>
            <div class="dropdown-wrap">
                <div class="topbar-user">
                    <div class="t-avatar"><i class="fa-solid fa-user"></i></div>
                    <div>
                        <div class="t-name"><?= strtoupper(htmlspecialchars($nama)) ?></div>
                        <div class="t-role">PEMILIK</div>
                    </div>
                    <i class="fa-solid fa-chevron-down t-chevron"></i>
                </div>
                <div class="dropdown-menu">
                    <a href="../profile.php" class="dd-item"><i class="fa-solid fa-id-badge"></i> Profil Saya</a>
                    <hr class="dd-divider">
                    <a href="../logout.php" class="dd-item" style="color:var(--red);"><i class="fa-solid fa-right-from-bracket"></i> Keluar</a>
                </div>
            </div>
        </div>
    </header>

    <div class="content">
        <div class="page-header">
            <div>
                <div class="page-title-tag"></div>
                <div class="page-title">Daftar Karyawan</div>
            </div>
            <div style="display:flex; align-items:center; gap:16px;">
                <div class="stat-chips">
                    <div class="stat-chip chip-purple">
                        <i class="fa-solid fa-user-tie"></i> TOTAL KARYAWAN <span class="chip-val"><?= $total_kry ?></span>
                    </div>
                </div>
                <button class="btn-add" onclick="openModal()"><i class="fa-solid fa-plus"></i> Tambah Karyawan</button>
            </div>
        </div>

        <div class="card">
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nama Lengkap</th>
                            <th>Jabatan</th>
                            <th>No. Telepon</th>
                            <th style="text-align:right;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $has_data = false;
                    while ($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)):
                        $has_data = true;
                    ?>
                        <tr>
                            <td class="emp-id"><?= htmlspecialchars($row['ID_Karyawan']) ?></td>
                            <td class="emp-name"><?= htmlspecialchars($row['Nama_Karyawan']) ?></td>
                            <td><span class="jabatan-badge"><?= $map_jabatan[$row['Jabatan']] ?? 'Staf' ?></span></td>
                            <td style="color:var(--muted);"><?= htmlspecialchars($row['No_Telepon']) ?></td>
                            <td>
                                <div class="actions">
                                    <a href="?edit_id=<?= $row['ID_Karyawan'] ?>" class="btn-act edit" title="Edit"><i class="fa-solid fa-pen-to-square"></i></a>
                                    <button onclick="confirmDelete('<?= $row['ID_Karyawan'] ?>')" class="btn-act del" title="Hapus"><i class="fa-solid fa-trash-can"></i></button>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    <?php if (!$has_data): ?>
                        <tr><td colspan="5" style="text-align:center; padding:50px; color:var(--muted);">
                            <i class="fa-solid fa-user-tie" style="font-size:32px; opacity:.3; display:block; margin-bottom:12px;"></i>
                            Belum ada data karyawan
                        </td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<script>
function openModal()  { document.getElementById('modal').classList.remove('hidden'); }
function closeModal() { window.location.href = 'karyawan.php'; }

function confirmDelete(id) {
    Swal.fire({ title:'Hapus Karyawan?', text:'Data staf akan dihapus permanen!', icon:'warning', showCancelButton:true, confirmButtonColor:'#EF4444', cancelButtonColor:'#6B7280', confirmButtonText:'Ya, Hapus!', cancelButtonText:'Batal' })
    .then((r) => { if (r.isConfirmed) window.location.href = '?delete_id=' + id; });
}

const urlParams = new URLSearchParams(window.location.search);
const status = urlParams.get('status');
const msg    = urlParams.get('msg');
if (status && msg) {
    Swal.fire({ icon: status==='success'?'success':'error', title: status==='success'?'Berhasil!':'Gagal!', text: msg, timer: 3000, showConfirmButton: false, iconColor: '#FF4500' });
    window.history.replaceState({}, document.title, window.location.pathname);
}

window.onclick = function(e) {
    if (e.target == document.getElementById('modal')) closeModal();
};
</script>
</body>
</html>