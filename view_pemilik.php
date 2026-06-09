<?php
session_start();
include 'includes/config.php';

if (!isset($_SESSION['login']) || $_SESSION['role'] !== 'pemilik') {
    header("Location: login.php");
    exit();
}

$role = $_SESSION['role'];
$nama = $_SESSION['nama'];

// Get profile photo from session
$profile_photo = $_SESSION['Profile_Photo'] ?? '';
if (!empty($profile_photo) && !file_exists($profile_photo)) {
    $profile_photo = '';
}

// Helper function untuk query dengan error handling
function safeQuery($conn, $sql, $params = array()) {
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        return null;
    }
    return $stmt;
}

function safeFetch($stmt) {
    if ($stmt === null) return false;
    return sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
}

// ── STATISTIK MANAJEMEN ──

// 1. Total Akun Aktif
$q = safeQuery($conn, "SELECT COUNT(*) as total FROM Akun WHERE Status_Akun = 1");
$d = safeFetch($q);
$total_akun = $d ? ($d['total'] ?? 0) : 0;

// 2. Total Karyawan Aktif
$q = safeQuery($conn, "SELECT COUNT(*) as total FROM Karyawan k JOIN Akun a ON k.ID_Akun = a.ID_Akun WHERE a.Status_Akun = 1");
$d = safeFetch($q);
$total_karyawan = $d ? ($d['total'] ?? 0) : 0;

// 3. Total Customer Aktif
$q = safeQuery($conn, "SELECT COUNT(*) as total FROM Customer c JOIN Akun a ON c.ID_Akun = a.ID_Akun WHERE a.Status_Akun = 1");
$d = safeFetch($q);
$total_customer = $d ? ($d['total'] ?? 0) : 0;

// 4. Total Alat/Stok Rendah
$q = safeQuery($conn, "SELECT COUNT(*) as total FROM Alat WHERE Stok < 10");
$d = safeFetch($q);
$stok_rendah = $d ? ($d['total'] ?? 0) : 0;

// 5. Total Booking Hari Ini (untuk ringkasan)
$q = safeQuery($conn, "SELECT COUNT(*) as total FROM Booking WHERE CAST(Tanggal_Booking AS DATE) = CAST(GETDATE() AS DATE)");
$d = safeFetch($q);
$total_booking_today = $d ? ($d['total'] ?? 0) : 0;

// 6. Pendapatan Hari Ini
$q = safeQuery($conn, "SELECT SUM(Total_Harga) as total FROM Booking WHERE CAST(Tanggal_Booking AS DATE) = CAST(GETDATE() AS DATE) AND Status_Booking = 'confirmed'");
$d = safeFetch($q);
$pendapatan_hari = $d ? ($d['total'] ?? 0) : 0;

// 7. Pendapatan Bulan Ini
$q = safeQuery($conn, "SELECT SUM(Total_Harga) as total FROM Booking WHERE MONTH(Tanggal_Booking) = MONTH(GETDATE()) AND YEAR(Tanggal_Booking) = YEAR(GETDATE()) AND Status_Booking = 'confirmed'");
$d = safeFetch($q);
$pendapatan_bulan = $d ? ($d['total'] ?? 0) : 0;

// 8. Booking Pending
$q = safeQuery($conn, "SELECT COUNT(*) as total FROM Booking WHERE Status_Booking = 'pending'");
$d = safeFetch($q);
$total_pending = $d ? ($d['total'] ?? 0) : 0;

// 9. Booking Cancelled Bulan Ini
$q = safeQuery($conn, "SELECT COUNT(*) as total FROM Booking WHERE Status_Booking = 'cancelled' AND MONTH(Tanggal_Booking) = MONTH(GETDATE()) AND YEAR(Tanggal_Booking) = YEAR(GETDATE())");
$d = safeFetch($q);
$total_cancelled = $d ? ($d['total'] ?? 0) : 0;

// ── DATA KARYAWAN TERBARU ──
$recent_karyawan = [];
$q = safeQuery($conn, "SELECT TOP 5 k.ID_Karyawan, k.Nama_Karyawan, k.Jabatan, k.No_Telepon, a.Status_Akun 
    FROM Karyawan k 
    JOIN Akun a ON k.ID_Akun = a.ID_Akun 
    ORDER BY k.ID_Karyawan DESC");
if ($q !== null) {
    while ($row = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)) {
        $recent_karyawan[] = $row;
    }
}

// ── DATA AKUN TERBARU ──
$recent_akun = [];
$q = safeQuery($conn, "SELECT TOP 5 ID_Akun, Username, Email, Role, Status_Akun FROM Akun ORDER BY ID_Akun DESC");
if ($q !== null) {
    while ($row = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)) {
        $recent_akun[] = $row;
    }
}

// ── CHART DATA: Omzet 7 Hari Terakhir ──
$chart_labels = [];
$chart_data = [];
for ($i = 6; $i >= 0; $i--) {
    $q = safeQuery($conn, "SELECT SUM(Total_Harga) as total FROM Booking WHERE CAST(Tanggal_Booking AS DATE) = CAST(DATEADD(day, -?, GETDATE()) AS DATE) AND Status_Booking = 'confirmed'", array($i));
    $d = safeFetch($q);
    $chart_data[] = $d ? ($d['total'] ?? 0) : 0;
    $chart_labels[] = date('D', strtotime("-$i days"));
}

function rupiahFormat($n) { 
    return 'Rp ' . number_format($n, 0, ',', '.'); 
}

$jabatan_map = [1 => 'Manajer', 2 => 'Supervisor', 3 => 'Kasir', 4 => 'Staf', 5 => 'Operator'];
$role_map = [1 => 'Pemilik', 2 => 'Karyawan', 3 => 'Customer'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Dashboard Pemilik | HoopBall</title>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800;900&family=Barlow:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
:root {
    --orange: #FF4500; --orange-lt: rgba(255,69,0,.10); --orange-dk: #E03E00;
    --green: #10B981; --green-lt: rgba(16,185,129,.10);
    --blue: #3B82F6; --blue-lt: rgba(59,130,246,.10);
    --purple: #8B5CF6; --purple-lt: rgba(139,92,246,.10);
    --red: #EF4444; --red-lt: rgba(239,68,68,.10);
    --yellow: #F59E0B; --yellow-lt: rgba(245,158,11,.10);
    --sidebar: #0D1117; --sidebar-w: 260px; --topbar-h: 70px;
    --card-bg: #FFFFFF; --border: #E5E7EB; --border-lt: #F3F4F6;
    --text: #111827; --text-md: #374151; --muted: #6B7280; --bg: #F3F4F6; --bg-dark: #1F2937;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }
body { font-family: 'Barlow', sans-serif; background: var(--bg); display: flex; min-height: 100vh; color: var(--text); }

/* SIDEBAR */
.sidebar { width: var(--sidebar-w); background: var(--sidebar); height: 100vh; position: fixed; top: 0; left: 0; display: flex; flex-direction: column; padding: 28px 18px; border-right: 1px solid rgba(255,255,255,.04); z-index: 200; overflow-y: auto; }
.sidebar::-webkit-scrollbar { width: 4px; }
.sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,.1); border-radius: 4px; }
.sb-brand { display: flex; align-items: center; gap: 12px; padding: 0 8px; margin-bottom: 36px; text-decoration: none; }
.sb-icon { width: 40px; height: 40px; background: var(--orange); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 18px; flex-shrink: 0; box-shadow: 0 4px 14px rgba(255,69,0,.4); }
.sb-brand-name { font-family: 'Barlow Condensed', sans-serif; font-size: 20px; font-weight: 900; color: #fff; letter-spacing: 1px; }
.sb-brand-sub { font-size: 9px; color: #4B5563; font-weight: 700; text-transform: uppercase; }
.sb-section-label { font-size: 10px; font-weight: 800; text-transform: uppercase; color: #374151; letter-spacing: .8px; padding: 0 10px; margin: 22px 0 8px; }
.sb-link { display: flex; align-items: center; gap: 12px; color: #6B7280; text-decoration: none; padding: 10px 12px; border-radius: 10px; margin-bottom: 2px; font-size: 13px; font-weight: 600; transition: all .2s ease; position: relative; }
.sb-link .sb-icon-wrap { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 13px; transition: .2s; flex-shrink: 0; background: rgba(255,255,255,.04); }
.sb-link:hover { color: #E5E7EB; background: rgba(255,255,255,.04); }
.sb-link:hover .sb-icon-wrap { background: rgba(255,255,255,.08); }
.sb-link.active { color: #fff; background: var(--orange-lt); }
.sb-link.active .sb-icon-wrap { background: var(--orange); color: #fff; }
.sb-link .badge { margin-left: auto; background: var(--orange); color: #fff; font-size: 10px; font-weight: 800; padding: 2px 8px; border-radius: 20px; }
.sb-bottom { margin-top: auto; padding-top: 20px; }
.sb-user { display: flex; align-items: center; gap: 10px; background: rgba(255,255,255,.04); border-radius: 12px; padding: 12px; border: 1px solid rgba(255,255,255,.06); }
.sb-avatar { width: 36px; height: 36px; background: var(--orange); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 14px; flex-shrink: 0; }
.sb-user-name { font-size: 13px; font-weight: 800; color: #E5E7EB; line-height: 1.1; }
.sb-user-role { font-size: 10px; color: var(--orange); font-weight: 700; text-transform: uppercase; }
.sb-logout { margin-left: auto; color: #4B5563; font-size: 13px; transition: .2s; cursor: pointer; text-decoration: none; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 8px; }
.sb-logout:hover { color: var(--red); background: rgba(239,68,68,.1); }

/* MAIN & TOPBAR */
.main { margin-left: var(--sidebar-w); flex: 1; display: flex; flex-direction: column; min-height: 100vh; }
.topbar { background: var(--card-bg); height: var(--topbar-h); padding: 0 40px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 100; box-shadow: 0 1px 0 rgba(0,0,0,.04); }
.topbar-left { display: flex; flex-direction: column; }
.topbar-title { font-family: 'Barlow Condensed', sans-serif; font-size: 26px; font-weight: 900; color: var(--text); letter-spacing: -.5px; line-height: 1; }
.topbar-right { display: flex; align-items: center; gap: 16px; }
.topbar-btn { width: 38px; height: 38px; border-radius: 10px; background: var(--bg); border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; color: var(--muted); cursor: pointer; font-size: 14px; text-decoration: none; transition: .2s; position: relative; }
.topbar-btn:hover { border-color: var(--orange); color: var(--orange); background: var(--orange-lt); }
.notif-dot { position: absolute; top: 7px; right: 7px; width: 7px; height: 7px; background: var(--orange); border-radius: 50%; border: 2px solid #fff; }
.dropdown-wrap { position: relative; }
.topbar-user { display: flex; align-items: center; gap: 10px; background: var(--bg); border: 1px solid var(--border); padding: 6px 14px 6px 8px; border-radius: 12px; cursor: pointer; transition: .2s; }
.topbar-user:hover { border-color: var(--orange); }
.t-avatar { width: 32px; height: 32px; background: var(--orange); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 13px; }
.t-name { font-size: 13px; font-weight: 800; color: var(--text); line-height: 1.1; }
.t-role { font-size: 10px; color: var(--orange); font-weight: 700; text-transform: uppercase; }
.t-chevron { color: var(--muted); font-size: 10px; margin-left: 4px; }
.dropdown-menu { display: none; position: absolute; right: 0; top: calc(100% + 8px); background: #fff; min-width: 200px; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 15px 40px rgba(0,0,0,.12); overflow: hidden; padding: 8px 0; z-index: 999; }
.dropdown-wrap:hover .dropdown-menu { display: block; }
.dd-item { display: flex; align-items: center; gap: 10px; padding: 11px 16px; color: #444; text-decoration: none; font-size: 13px; font-weight: 700; transition: .15s; }
.dd-item:hover { background: #FFF7ED; color: var(--orange); }
.dd-item i { font-size: 14px; width: 18px; text-align: center; }
.dd-divider { border: none; border-top: 1px solid #F3F4F6; margin: 4px 0; }

/* CONTENT */
.content { padding: 32px 40px; flex: 1; }
.welcome-banner { background: linear-gradient(135deg, #0D1117 0%, #1a1a2e 100%); border-radius: 20px; padding: 32px 36px; display: flex; align-items: center; justify-content: space-between; margin-bottom: 28px; overflow: hidden; position: relative; border: 1px solid rgba(255,69,0,.15); }
.wb-deco { position: absolute; right: -30px; top: -30px; width: 220px; height: 220px; border-radius: 50%; background: radial-gradient(circle, rgba(255,69,0,.18) 0%, transparent 70%); }
.wb-deco2 { position: absolute; right: 120px; bottom: -40px; width: 160px; height: 160px; border-radius: 50%; background: radial-gradient(circle, rgba(255,69,0,.08) 0%, transparent 70%); }
.wb-text { position: relative; z-index: 1; }
.wb-greeting { font-size: 13px; color: #6B7280; font-weight: 700; margin-bottom: 6px; text-transform: uppercase; letter-spacing: .8px; }
.wb-name { font-family: 'Barlow Condensed', sans-serif; font-size: 32px; font-weight: 900; color: #fff; letter-spacing: -.5px; }
.wb-sub { font-size: 14px; color: #6B7280; margin-top: 4px; }
.wb-icon { position: relative; z-index: 1; }
.wb-icon i { font-size: 64px; color: rgba(255,69,0,.25); }

/* STAT GRID */
.stat-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 18px; margin-bottom: 28px; }
.stat-card { background: var(--card-bg); border-radius: 16px; padding: 22px 24px; border: 1px solid var(--border); position: relative; overflow: hidden; transition: all .2s ease; }
.stat-card:hover { transform: translateY(-3px); box-shadow: 0 12px 32px rgba(0,0,0,.08); }
.stat-card::before { content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; border-radius: 4px 0 0 4px; }
.sc-blue::before { background: var(--blue); }
.sc-green::before { background: var(--green); }
.sc-orange::before { background: var(--orange); }
.sc-purple::before { background: var(--purple); }
.sc-red::before { background: var(--red); }
.stat-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
.stat-icon-wrap { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 18px; }
.si-blue { background: var(--blue-lt); color: var(--blue); }
.si-green { background: var(--green-lt); color: var(--green); }
.si-orange { background: var(--orange-lt); color: var(--orange); }
.si-purple { background: var(--purple-lt); color: var(--purple); }
.si-red { background: var(--red-lt); color: var(--red); }
.stat-trend { font-size: 11px; font-weight: 800; display: flex; align-items: center; gap: 3px; padding: 4px 8px; border-radius: 20px; }
.trend-up { color: var(--green); background: var(--green-lt); }
.trend-down { color: var(--red); background: var(--red-lt); }
.trend-warn { color: var(--yellow); background: var(--yellow-lt); }
.stat-value { font-family: 'Barlow Condensed', sans-serif; font-size: 30px; font-weight: 900; color: var(--text); line-height: 1; margin-bottom: 6px; }
.stat-label { font-size: 12px; color: var(--muted); font-weight: 700; text-transform: uppercase; letter-spacing: .5px; }
.stat-sublabel { font-size: 11px; color: var(--muted); margin-top: 4px; opacity: .7; }

/* CHART SECTION */
.chart-section { display: grid; grid-template-columns: 2fr 1fr; gap: 22px; margin-bottom: 28px; }
@media(max-width:1200px){ .chart-section { grid-template-columns: 1fr; } }
.chart-card { background: var(--card-bg); border-radius: 16px; border: 1px solid var(--border); padding: 24px; }
.chart-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
.chart-title { font-size: 15px; font-weight: 800; color: var(--text); display: flex; align-items: center; gap: 8px; }
.chart-title i { color: var(--orange); font-size: 14px; }
.chart-badge { background: var(--orange-lt); color: var(--orange); font-size: 11px; font-weight: 800; padding: 4px 10px; border-radius: 20px; }
.chart-container { position: relative; height: 280px; }

/* MINI STAT ROW */
.mini-stat-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.mini-stat { background: var(--border-lt); border-radius: 12px; padding: 16px; border: 1px solid var(--border); }
.mini-stat-label { font-size: 11px; color: var(--muted); font-weight: 700; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 4px; }
.mini-stat-value { font-family: 'Barlow Condensed', sans-serif; font-size: 22px; font-weight: 900; color: var(--text); }
.mini-stat-value.red { color: var(--red); }
.mini-stat-value.orange { color: var(--orange); }

/* GRID LAYOUT */
.dashboard-grid { display: grid; grid-template-columns: 1fr 340px; gap: 22px; }
@media(max-width:1100px){ .dashboard-grid { grid-template-columns: 1fr; } }

/* CARD */
.card { background: var(--card-bg); border-radius: 16px; border: 1px solid var(--border); overflow: hidden; transition: all .2s ease; }
.card:hover { box-shadow: 0 8px 24px rgba(0,0,0,.06); }
.card-header { padding: 20px 24px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
.card-title { font-size: 15px; font-weight: 800; color: var(--text); display: flex; align-items: center; gap: 8px; }
.card-title i { color: var(--orange); font-size: 14px; }
.card-badge { background: var(--orange-lt); color: var(--orange); font-size: 11px; font-weight: 800; padding: 4px 10px; border-radius: 20px; }
.card-link { font-size: 12px; font-weight: 700; color: var(--orange); text-decoration: none; display: flex; align-items: center; gap: 4px; transition: .2s; }
.card-link:hover { gap: 8px; }
.card-body { padding: 20px 24px; }

/* TABLE */
.data-table { width: 100%; border-collapse: collapse; }
.data-table th { padding: 10px 12px; font-size: 10px; font-weight: 800; color: var(--muted); text-transform: uppercase; letter-spacing: .6px; border-bottom: 2px solid var(--border-lt); text-align: left; }
.data-table td { padding: 14px 12px; font-size: 13px; border-bottom: 1px solid var(--border-lt); vertical-align: middle; }
.data-table tr:last-child td { border-bottom: none; }
.data-table tbody tr { transition: background .15s; }
.data-table tbody tr:hover td { background: #FAFAFA; }

.cell-name { font-weight: 700; color: var(--text); }
.cell-detail { font-size: 11px; color: var(--muted); font-weight: 600; margin-top: 2px; }

.status-pill { padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .3px; display: inline-block; }
.sp-active { background: var(--green-lt); color: var(--green); }
.sp-inactive { background: var(--red-lt); color: var(--red); }
.sp-pending { background: var(--yellow-lt); color: #D97706; }

/* QUICK LINKS */
.quick-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.quick-card { background: var(--bg); border: 1px solid var(--border); border-radius: 12px; padding: 20px; text-decoration: none; text-align: center; transition: all .2s ease; display: flex; flex-direction: column; align-items: center; gap: 10px; }
.quick-card:hover { border-color: var(--orange); background: var(--orange-lt); transform: translateY(-2px); box-shadow: 0 8px 20px rgba(255,69,0,.1); }
.quick-card i { font-size: 24px; transition: .2s; }
.quick-card:hover i { transform: scale(1.1); }
.quick-card span { font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .4px; }

/* ALERT BOX */
.alert-box { background: var(--red-lt); border: 1px solid rgba(239,68,68,.2); border-radius: 12px; padding: 14px 18px; display: flex; align-items: center; gap: 12px; margin-bottom: 22px; }
.alert-box i { color: var(--red); font-size: 16px; }
.alert-box span { font-size: 13px; font-weight: 700; color: var(--red); }

/* CLOCK */
#clock-display { display: flex; align-items: center; gap: 16px; }
.clock-time { font-family: 'Barlow Condensed', sans-serif; font-size: 26px; font-weight: 900; color: var(--orange); display: flex; align-items: center; gap: 6px; line-height: 1; }
.clock-colon { color: var(--orange); opacity: .5; animation: blink 1s infinite; }
@keyframes blink { 0%, 100% { opacity: .5; } 50% { opacity: 1; } }
.clock-divider { width: 1.5px; height: 28px; background-color: var(--border); }
.clock-date { font-family: 'Barlow', sans-serif; font-size: 13px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; }
</style>
</head>
<body>

<aside class="sidebar">
    <a href="dashboard.php" class="sb-brand">
        <div class="sb-icon"><i class="fa-solid fa-basketball"></i></div>
        <div><div class="sb-brand-name">HOOP BALL</div><div class="sb-brand-sub">Management System</div></div>
    </a>

    <!-- PEMILIK: MANAJEMEN ONLY -->
    <div class="sb-section-label">Manajemen</div>
    <nav>
        <a href="view_pemilik.php" class="sb-link active">
            <div class="sb-icon-wrap"><i class="fa-solid fa-house"></i></div>
            Dashboard
        </a>
        <a href="master/akun.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-user-shield"></i></div>
            Kelola Akun
        </a>
        <a href="master/karyawan.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-user-tie"></i></div>
            Kelola Karyawan
        </a>
        <a href="master/supplier.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-toolbox"></i></div>
            Kelola Alat
        </a>
        <a href="laporan/omzet.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-chart-line"></i></div>
            Laporan & Omzet
        </a>
    </nav>

    <!-- PEMILIK: AKUN -->
    <div class="sb-section-label">Akun</div>
    <a href="profile.php" class="sb-link">
        <div class="sb-icon-wrap"><i class="fa-solid fa-id-badge"></i></div>
        Profil Saya
    </a>

    <div class="sb-bottom">
        <div class="sb-user">
            <div class="sb-avatar">
                <?php if ($profile_photo): ?>
                    <img src="<?= $profile_photo ?>" alt="Profile" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
                <?php else: ?>
                    <i class="fa-solid fa-user"></i>
                <?php endif; ?>
            </div>
            <div><div class="sb-user-name"><?= strtoupper(htmlspecialchars($nama)) ?></div><div class="sb-user-role">PEMILIK</div></div>
            <a href="logout.php" class="sb-logout" title="Keluar"><i class="fa-solid fa-right-from-bracket"></i></a>
        </div>
    </div>
</aside>

<main class="main">
<header class="topbar">
    <div class="topbar-left">
        <div class="topbar-title">Dashboard Pemilik</div>
        <div class="topbar-breadcrumb">Dashboard / Overview</div>
    </div>
    <div class="topbar-right">
        <div id="clock-display">
            <div class="clock-time"><span id="h">00</span><span class="clock-colon">:</span><span id="m">00</span><span class="clock-colon">:</span><span id="s">00</span></div>
            <div class="clock-divider"></div>
            <div class="clock-date" id="full-date">MEMUAT...</div>
        </div>
        <a href="#" class="topbar-btn"><i class="fa-solid fa-magnifying-glass"></i></a>
        <a href="#" class="topbar-btn"><i class="fa-solid fa-bell"></i><?php if($total_pending > 0): ?><span class="notif-dot"></span><?php endif; ?></a>
        <div class="dropdown-wrap">
            <div class="topbar-user">
                <div class="t-avatar">
                    <?php if ($profile_photo): ?>
                        <img src="<?= $profile_photo ?>" alt="Profile" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
                    <?php else: ?>
                        <i class="fa-solid fa-user"></i>
                    <?php endif; ?>
                </div>
                <div><div class="t-name"><?= strtoupper(htmlspecialchars($nama)) ?></div><div class="t-role">PEMILIK</div></div>
                <i class="fa-solid fa-chevron-down t-chevron"></i>
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
    <?php if($stok_rendah > 0): ?>
    <div class="alert-box"><i class="fa-solid fa-triangle-exclamation"></i><span><?= $stok_rendah ?> alat memiliki stok di bawah minimum (&lt; 10 unit)</span><a href="master/supplier.php" style="color:var(--red); font-size:12px; font-weight:700; margin-left:auto; text-decoration:none;">Lihat Detail →</a></div>
    <?php endif; ?>

    <div class="welcome-banner">
        <div class="wb-deco"></div><div class="wb-deco2"></div>
        <div class="wb-text"><div class="wb-greeting">Selamat Datang Kembali</div><div class="wb-name"><?= strtoupper(htmlspecialchars($nama)) ?> 👋</div><div class="wb-sub">Pantau performa bisnis dan kelola data sistem.</div></div>
        <div class="wb-icon"><i class="fa-solid fa-chart-pie"></i></div>
    </div>

    <!-- Statistik Manajemen -->
    <div class="stat-grid">
        <div class="stat-card sc-blue">
            <div class="stat-header"><div class="stat-icon-wrap si-blue"><i class="fa-solid fa-users"></i></div><div class="stat-trend trend-up"><i class="fa-solid fa-arrow-up"></i> Total</div></div>
            <div class="stat-value"><?= $total_akun ?></div><div class="stat-label">Total Akun Aktif</div><div class="stat-sublabel"><?= $total_karyawan ?> karyawan, <?= $total_customer ?> customer</div>
        </div>
        <div class="stat-card sc-green">
            <div class="stat-header"><div class="stat-icon-wrap si-green"><i class="fa-solid fa-money-bill-wave"></i></div><div class="stat-trend trend-up"><i class="fa-solid fa-arrow-up"></i> <?= rupiahFormat($pendapatan_hari) ?></div></div>
            <div class="stat-value" style="font-size:24px;"><?= rupiahFormat($pendapatan_hari) ?></div><div class="stat-label">Pendapatan Hari Ini</div><div class="stat-sublabel"><?= rupiahFormat($pendapatan_bulan) ?> bulan ini</div>
        </div>
        <div class="stat-card sc-orange">
            <div class="stat-header"><div class="stat-icon-wrap si-orange"><i class="fa-solid fa-calendar-check"></i></div><div class="stat-trend trend-up"><i class="fa-solid fa-arrow-up"></i> Hari Ini</div></div>
            <div class="stat-value"><?= $total_booking_today ?></div><div class="stat-label">Booking Hari Ini</div><div class="stat-sublabel"><?= $total_pending ?> menunggu konfirmasi</div>
        </div>
        <div class="stat-card sc-purple">
            <div class="stat-header"><div class="stat-icon-wrap si-purple"><i class="fa-solid fa-user-tie"></i></div><div class="stat-trend trend-up"><i class="fa-solid fa-arrow-up"></i> Aktif</div></div>
            <div class="stat-value"><?= $total_karyawan ?></div><div class="stat-label">Karyawan Aktif</div><div class="stat-sublabel">Kelola di menu karyawan</div>
        </div>
    </div>

    <!-- Chart Omzet -->
    <div class="chart-section">
        <div class="chart-card">
            <div class="chart-header"><div class="chart-title"><i class="fa-solid fa-chart-column"></i> Omzet 7 Hari Terakhir</div><span class="chart-badge"><?= rupiahFormat(array_sum($chart_data)) ?> Total</span></div>
            <div class="chart-container"><canvas id="omzetChart"></canvas></div>
        </div>
        <div>
            <div class="chart-card" style="margin-bottom: 16px;">
                <div class="chart-header"><div class="chart-title"><i class="fa-solid fa-circle-exclamation"></i> Ringkasan Operasional</div></div>
                <div class="mini-stat-row">
                    <div class="mini-stat"><div class="mini-stat-label">Booking Pending</div><div class="mini-stat-value orange"><?= $total_pending ?></div></div>
                    <div class="mini-stat"><div class="mini-stat-label">Dibatalkan Bulan Ini</div><div class="mini-stat-value red"><?= $total_cancelled ?></div></div>
                    <div class="mini-stat"><div class="mini-stat-label">Stok Alat Rendah</div><div class="mini-stat-value <?= $stok_rendah > 0 ? 'red' : '' ?>"><?= $stok_rendah ?></div></div>
                    <div class="mini-stat"><div class="mini-stat-label">Pendapatan Bulan</div><div class="mini-stat-value"><?= rupiahFormat($pendapatan_bulan) ?></div></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Grid -->
    <div class="dashboard-grid">
        <!-- Karyawan Terbaru -->
        <div class="card">
            <div class="card-header"><div class="card-title"><i class="fa-solid fa-user-tie"></i> Karyawan Terbaru</div><div style="display:flex; align-items:center; gap:12px;"><span class="card-badge"><?= count($recent_karyawan) ?> data</span><a href="master/karyawan.php" class="card-link">Kelola <i class="fa-solid fa-arrow-right" style="font-size:10px;"></i></a></div></div>
            <div style="overflow-x:auto;">
                <table class="data-table">
                    <thead><tr><th>Nama</th><th>Jabatan</th><th>Telepon</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php if(count($recent_karyawan) > 0): ?>
                    <?php foreach($recent_karyawan as $k): 
                        $status_cls = $k['Status_Akun'] == 1 ? 'sp-active' : 'sp-inactive';
                        $status_lbl = $k['Status_Akun'] == 1 ? 'Aktif' : 'Nonaktif';
                    ?>
                        <tr>
                            <td><div class="cell-name"><?= htmlspecialchars($k['Nama_Karyawan']) ?></div><div class="cell-detail">#<?= $k['ID_Karyawan'] ?></div></td>
                            <td><?= $jabatan_map[$k['Jabatan']] ?? 'Unknown' ?></td>
                            <td><?= htmlspecialchars($k['No_Telepon']) ?></td>
                            <td><span class="status-pill <?= $status_cls ?>"><?= $status_lbl ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4" style="text-align:center; padding:30px; color:var(--muted);"><i class="fa-solid fa-inbox" style="font-size:32px; margin-bottom:10px; opacity:.5; display:block;"></i><div style="font-size:13px; font-weight:700;">Belum ada data karyawan</div></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Right Column -->
        <div style="display:flex; flex-direction:column; gap:20px;">
            <!-- Akses Cepat Manajemen -->
            <div class="card">
                <div class="card-header"><div class="card-title"><i class="fa-solid fa-bolt"></i> Akses Cepat</div></div>
                <div class="card-body">
                    <div class="quick-grid">
                        <a href="master/akun.php" class="quick-card" style="color:var(--blue);"><i class="fa-solid fa-user-shield"></i><span>Kelola Akun</span></a>
                        <a href="master/karyawan.php" class="quick-card" style="color:var(--green);"><i class="fa-solid fa-user-tie"></i><span>Kelola Karyawan</span></a>
                        <a href="master/supplier.php" class="quick-card" style="color:var(--orange);"><i class="fa-solid fa-truck-fast"></i><span>Kelola Alat</span></a>
                        <a href="laporan/omzet.php" class="quick-card" style="color:var(--purple);"><i class="fa-solid fa-chart-line"></i><span>Laporan & Omzet</span></a>
                    </div>
                </div>
            </div>

            <!-- Akun Terbaru -->
            <div class="card">
                <div class="card-header"><div class="card-title"><i class="fa-solid fa-users"></i> Akun Terbaru</div><span class="card-badge"><?= count($recent_akun) ?> data</span></div>
                <div class="card-body">
                    <?php if(count($recent_akun) > 0): ?>
                    <table class="data-table">
                        <thead><tr><th>Username</th><th>Role</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach($recent_akun as $a): 
                            $status_cls = $a['Status_Akun'] == 1 ? 'sp-active' : 'sp-inactive';
                            $status_lbl = $a['Status_Akun'] == 1 ? 'Aktif' : 'Nonaktif';
                        ?>
                            <tr>
                                <td><div class="cell-name"><?= htmlspecialchars($a['Username']) ?></div><div class="cell-detail"><?= htmlspecialchars($a['Email']) ?></div></td>
                                <td><?= $role_map[$a['Role']] ?? 'Unknown' ?></td>
                                <td><span class="status-pill <?= $status_cls ?>"><?= $status_lbl ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div style="text-align:center; padding:20px; color:var(--muted);"><i class="fa-solid fa-inbox" style="font-size:24px; margin-bottom:8px; opacity:.5; display:block;"></i><div style="font-size:12px; font-weight:700;">Belum ada data akun</div></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</main>

<script>
function updateClock() {
    const now = new Date();
    const h = String(now.getHours()).padStart(2, '0');
    const m = String(now.getMinutes()).padStart(2, '0');
    const s = String(now.getSeconds()).padStart(2, '0');
    document.getElementById('h').innerText = h;
    document.getElementById('m').innerText = m;
    document.getElementById('s').innerText = s;
    const days = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    const months = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    document.getElementById('full-date').innerText = `${days[now.getDay()]}, ${now.getDate()} ${months[now.getMonth()]} ${now.getFullYear()}`;
}
setInterval(updateClock, 1000);
updateClock();

const ctx = document.getElementById('omzetChart').getContext('2d');
const omzetChart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?= json_encode($chart_labels) ?>,
        datasets: [{
            label: 'Omzet',
            data: <?= json_encode($chart_data) ?>,
            backgroundColor: 'rgba(255, 69, 0, 0.8)',
            borderColor: '#FF4500',
            borderWidth: 2,
            borderRadius: 8,
            borderSkipped: false,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: '#1F2937',
                titleColor: '#fff',
                bodyColor: '#fff',
                padding: 12,
                cornerRadius: 8,
                displayColors: false,
                callbacks: {
                    label: function(context) {
                        return 'Omzet: Rp ' + context.parsed.y.toLocaleString('id-ID');
                    }
                }
            }
        },
        scales: {
            y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)', drawBorder: false }, ticks: { font: { family: 'Barlow', size: 11 }, color: '#6B7280' } },
            x: { grid: { display: false }, ticks: { font: { family: 'Barlow', size: 11 }, color: '#6B7280' } }
        }
    }
});
</script>

</body>
</html>