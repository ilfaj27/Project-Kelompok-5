<?php
session_start();
include '../includes/config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'karyawan') {
    echo "<script>alert('Akses Ditolak!'); window.location='../login.php';</script>";
    exit();
}

$nama = $_SESSION['nama'];
$role = $_SESSION['role'];
$jabatan = $_SESSION['jabatan'] ?? 'Karyawan';
$id_karyawan = $_SESSION['id_karyawan'] ?? '';

$profile_photo = $_SESSION['Profile_Photo'] ?? '';
$photo_path = '';
if (!empty($profile_photo)) {
    $photo_path = $profile_photo;
    if (!file_exists($photo_path)) $photo_path = '';
}

// Statistik
$total_alat = 0;
$total_alat_aktif = 0;
$q = sqlsrv_query($conn, "SELECT COUNT(*) as t FROM Alat");
if ($q && $r = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)) $total_alat = $r['t'];
$q = sqlsrv_query($conn, "SELECT COUNT(*) as t FROM Alat WHERE Status = 1");
if ($q && $r = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)) $total_alat_aktif = $r['t'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Dashboard Karyawan | HoopBall</title>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800;900&family=Barlow:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root {
    --orange: #FF4500; --orange-lt: rgba(255,69,0,.10); --orange-dk: #E03E00;
    --green: #10B981; --green-lt: rgba(16,185,129,.10);
    --blue: #3B82F6; --blue-lt: rgba(59,130,246,.10);
    --sidebar: #0D1117; --sidebar-w: 260px; --topbar-h: 70px;
    --card-bg: #FFFFFF; --border: #E5E7EB; --border-lt: #F3F4F6;
    --text: #111827; --muted: #6B7280; --bg: #F3F4F6;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Barlow', sans-serif; background: var(--bg); display: flex; min-height: 100vh; color: var(--text); }

.sidebar { width: var(--sidebar-w); background: var(--sidebar); height: 100vh; position: fixed; top: 0; left: 0; display: flex; flex-direction: column; padding: 28px 18px; z-index: 200; }
.sb-brand { display: flex; align-items: center; gap: 12px; padding: 0 8px; margin-bottom: 36px; text-decoration: none; }
.sb-icon { width: 40px; height: 40px; background: var(--orange); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 18px; box-shadow: 0 4px 14px rgba(255,69,0,.4); }
.sb-brand-name { font-family: 'Barlow Condensed'; font-size: 20px; font-weight: 900; color: #fff; letter-spacing: 1px; }
.sb-brand-sub { font-size: 9px; color: #4B5563; font-weight: 700; text-transform: uppercase; }
.sb-section-label { font-size: 10px; font-weight: 800; text-transform: uppercase; color: #374151; letter-spacing: .8px; padding: 0 10px; margin: 22px 0 8px; }
.sb-link { display: flex; align-items: center; gap: 12px; color: #6B7280; text-decoration: none; padding: 10px 12px; border-radius: 10px; margin-bottom: 2px; font-size: 13px; font-weight: 600; transition: all .2s; }
.sb-link .sb-icon-wrap { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 13px; background: rgba(255,255,255,.04); }
.sb-link:hover { color: #E5E7EB; background: rgba(255,255,255,.04); }
.sb-link.active { color: #fff; background: var(--orange-lt); }
.sb-link.active .sb-icon-wrap { background: var(--orange); color: #fff; }
.sb-bottom { margin-top: auto; padding-top: 20px; }
.sb-user { display: flex; align-items: center; gap: 10px; background: rgba(255,255,255,.04); border-radius: 12px; padding: 12px; border: 1px solid rgba(255,255,255,.06); }
.sb-avatar { width: 36px; height: 36px; background: var(--orange); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 14px; }
.sb-user-name { font-size: 13px; font-weight: 800; color: #E5E7EB; }
.sb-user-role { font-size: 10px; color: var(--orange); font-weight: 700; text-transform: uppercase; }
.sb-logout { margin-left: auto; color: #4B5563; font-size: 13px; transition: .2s; cursor: pointer; text-decoration: none; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 8px; }
.sb-logout:hover { color: var(--red); background: rgba(239,68,68,.1); }

.main { margin-left: var(--sidebar-w); flex: 1; display: flex; flex-direction: column; min-height: 100vh; }
.topbar { background: var(--card-bg); height: var(--topbar-h); padding: 0 40px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 100; }
.topbar-left { display: flex; flex-direction: column; }
.topbar-title { font-family: 'Barlow Condensed'; font-size: 26px; font-weight: 900; color: var(--text); }
.topbar-breadcrumb { font-size: 12px; color: var(--muted); font-weight: 600; margin-top: 2px; }
.topbar-right { display: flex; align-items: center; gap: 16px; }
.topbar-btn { width: 38px; height: 38px; border-radius: 10px; background: var(--bg); border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; color: var(--muted); cursor: pointer; font-size: 14px; transition: .2s; }
.topbar-btn:hover { border-color: var(--orange); color: var(--orange); background: var(--orange-lt); }
.dropdown-wrap { position: relative; }
.topbar-user { display: flex; align-items: center; gap: 10px; background: var(--bg); border: 1px solid var(--border); padding: 6px 14px 6px 8px; border-radius: 12px; cursor: pointer; transition: .2s; }
.topbar-user:hover { border-color: var(--orange); }
.t-avatar { width: 32px; height: 32px; background: var(--orange); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 13px; }
.t-name { font-size: 13px; font-weight: 800; color: var(--text); text-transform: uppercase; }
.t-role { font-size: 10px; color: var(--orange); font-weight: 700; text-transform: uppercase; }
.dropdown-menu { display: none; position: absolute; right: 0; top: calc(100% + 8px); background: #fff; min-width: 200px; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 15px 40px rgba(0,0,0,.12); padding: 8px 0; z-index: 999; }
.dropdown-wrap:hover .dropdown-menu { display: block; }
.dd-item { display: flex; align-items: center; gap: 10px; padding: 11px 16px; color: #444; text-decoration: none; font-size: 13px; font-weight: 700; transition: .15s; }
.dd-item:hover { background: #FFF7ED; color: var(--orange); }
.dd-divider { border: none; border-top: 1px solid #F3F4F6; margin: 4px 0; }

.content { padding: 32px 40px; flex: 1; }
.page-header { margin-bottom: 28px; }
.page-title { font-family: 'Barlow Condensed'; font-size: 30px; font-weight: 900; color: var(--text); text-transform: uppercase; }
.page-subtitle { font-size: 14px; color: var(--muted); margin-top: 4px; }

.stat-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 18px; margin-bottom: 28px; }
@media(max-width: 1100px) { .stat-grid { grid-template-columns: repeat(2, 1fr); } }
@media(max-width: 768px) { .stat-grid { grid-template-columns: 1fr; } }
.stat-card { background: var(--card-bg); border-radius: 16px; padding: 22px 24px; border: 1px solid var(--border); position: relative; overflow: hidden; transition: all .2s; }
.stat-card:hover { transform: translateY(-3px); box-shadow: 0 12px 32px rgba(0,0,0,.08); }
.stat-card::before { content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; border-radius: 4px 0 0 4px; }
.sc-orange::before { background: var(--orange); }
.sc-blue::before { background: var(--blue); }
.sc-green::before { background: var(--green); }
.stat-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
.stat-icon-wrap { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 18px; }
.si-orange { background: var(--orange-lt); color: var(--orange); }
.si-blue { background: var(--blue-lt); color: var(--blue); }
.si-green { background: var(--green-lt); color: var(--green); }
.stat-value { font-family: 'Barlow Condensed'; font-size: 30px; font-weight: 900; color: var(--text); line-height: 1; margin-bottom: 6px; }
.stat-label { font-size: 12px; color: var(--muted); font-weight: 700; text-transform: uppercase; letter-spacing: .5px; }
.stat-sublabel { font-size: 11px; color: var(--muted); margin-top: 4px; opacity: .7; }

.welcome-card { background: linear-gradient(135deg, var(--orange) 0%, var(--orange-dk) 100%); border-radius: 16px; padding: 32px; color: #fff; margin-bottom: 28px; display: flex; align-items: center; gap: 24px; }
.welcome-icon { width: 64px; height: 64px; background: rgba(255,255,255,.2); border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 28px; backdrop-filter: blur(10px); flex-shrink: 0; }
.welcome-title { font-family: 'Barlow Condensed'; font-size: 24px; font-weight: 900; margin-bottom: 4px; }
.welcome-desc { font-size: 14px; opacity: .9; }

.quick-actions { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
.qa-item { background: var(--card-bg); border-radius: 16px; border: 1px solid var(--border); padding: 24px; text-decoration: none; color: var(--text); transition: .2s; display: flex; align-items: center; gap: 16px; }
.qa-item:hover { transform: translateY(-3px); box-shadow: 0 12px 32px rgba(0,0,0,.08); border-color: var(--orange); }
.qa-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
.qa-orange { background: var(--orange-lt); color: var(--orange); }
.qa-blue { background: var(--blue-lt); color: var(--blue); }
.qa-green { background: var(--green-lt); color: var(--green); }
.qa-title { font-size: 15px; font-weight: 800; }
.qa-desc { font-size: 12px; color: var(--muted); margin-top: 2px; }
</style>
</head>
<body>

<!-- ═══ SIDEBAR ═══ -->
<aside class="sidebar">
    <a href="view_admin.php" class="sb-brand">
        <div class="sb-icon"><i class="fa-solid fa-basketball"></i></div>
        <div><div class="sb-brand-name">HOOP BALL</div><div class="sb-brand-sub">Management System</div></div>
    </a>
    <div class="sb-section-label">Menu</div>
    <nav>
        <a href="view_admin.php" class="sb-link active"><div class="sb-icon-wrap"><i class="fa-solid fa-house"></i></div> Dashboard</a>
        <a href="master/alat.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-toolbox"></i></div> Kelola Alat</a>
    </nav>
    <div class="sb-section-label">Akun</div>
    <a href="profile.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-id-badge"></i></div> Profil Saya</a>
    <div class="sb-bottom">
        <div class="sb-user">
            <div class="sb-avatar">
                <?php if ($photo_path): ?><img src="<?= $photo_path ?>" style="width:100%;height:100%;object-fit:cover;border-radius:50%;"><?php else: ?><i class="fa-solid fa-user"></i><?php endif; ?>
            </div>
            <div><div class="sb-user-name"><?= strtoupper(htmlspecialchars($nama)) ?></div><div class="sb-user-role"><?= strtoupper($jabatan) ?></div></div>
            <a href="logout.php" class="sb-logout" title="Keluar"><i class="fa-solid fa-right-from-bracket"></i></a>
        </div>
    </div>
</aside>

<!-- ═══ MAIN ═══ -->
<main class="main">
    <header class="topbar">
        <div class="topbar-left">
            <div class="topbar-title">Dashboard Karyawan</div>
            <div class="topbar-breadcrumb">Beranda / Dashboard</div>
        </div>
        <div class="topbar-right">
            <a href="#" class="topbar-btn"><i class="fa-solid fa-bell"></i></a>
            <div class="dropdown-wrap">
                <div class="topbar-user">
                    <div class="t-avatar">
                        <?php if ($photo_path): ?><img src="<?= $photo_path ?>" style="width:100%;height:100%;object-fit:cover;border-radius:50%;"><?php else: ?><i class="fa-solid fa-user"></i><?php endif; ?>
                    </div>
                    <div><div class="t-name"><?= strtoupper(htmlspecialchars($nama)) ?></div><div class="t-role"><?= strtoupper($jabatan) ?></div></div>
                </div>
                <div class="dropdown-menu">
                    <a href="profile.php" class="dd-item"><i class="fa-solid fa-id-badge"></i> Profil Saya</a>
                    <hr class="dd-divider">
                    <a href="logout.php" class="dd-item" style="color:var(--red);"><i class="fa-solid fa-right-from-bracket"></i> Keluar</a>
                </div>
            </div>
        </div>
    </header>

    <div class="content">
        <div class="welcome-card">
            <div class="welcome-icon"><i class="fa-solid fa-handshake"></i></div>
            <div>
                <div class="welcome-title">Selamat Datang, <?= htmlspecialchars($nama) ?>!</div>
                <div class="welcome-desc">Anda login sebagai <?= htmlspecialchars($jabatan) ?>. Siap melayani pelanggan HoopBall hari ini.</div>
            </div>
        </div>

        <div class="stat-grid">
            <div class="stat-card sc-orange">
                <div class="stat-header"><div class="stat-icon-wrap si-orange"><i class="fa-solid fa-toolbox"></i></div></div>
                <div class="stat-value"><?= $total_alat ?></div>
                <div class="stat-label">Total Alat</div>
                <div class="stat-sublabel">Tersedia di sistem</div>
            </div>
            <div class="stat-card sc-blue">
                <div class="stat-header"><div class="stat-icon-wrap si-blue"><i class="fa-solid fa-check-circle"></i></div></div>
                <div class="stat-value"><?= $total_alat_aktif ?></div>
                <div class="stat-label">Alat Tersedia</div>
                <div class="stat-sublabel">Siap digunakan</div>
            </div>
            <div class="stat-card sc-green">
                <div class="stat-header"><div class="stat-icon-wrap si-green"><i class="fa-solid fa-wrench"></i></div></div>
                <div class="stat-value"><?= $total_alat - $total_alat_aktif ?></div>
                <div class="stat-label">Alat Maintenance</div>
                <div class="stat-sublabel">Perlu perbaikan</div>
            </div>
        </div>

        <div class="page-header" style="margin-top: 8px;">
            <div class="page-title">Akses Cepat</div>
        </div>
        <div class="quick-actions">
            <a href="master/alat.php" class="qa-item">
                <div class="qa-icon qa-orange"><i class="fa-solid fa-toolbox"></i></div>
                <div><div class="qa-title">Kelola Alat</div><div class="qa-desc">Cek status & kelola peralatan</div></div>
            </a>
            <a href="profile.php" class="qa-item">
                <div class="qa-icon qa-blue"><i class="fa-solid fa-id-badge"></i></div>
                <div><div class="qa-title">Profil Saya</div><div class="qa-desc">Lihat & ubah data pribadi</div></div>
            </a>
        </div>
    </div>
</main>

</body>
</html>