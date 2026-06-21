<?php
session_start();
include '../includes/config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'karyawan') {
    echo "<script>alert('Akses Ditolak!'); window.location='../login/login.php';</script>";
    exit();
}

$nama = $_SESSION['nama'];
$role = $_SESSION['role'];
$jabatan = $_SESSION['jabatan'] ?? 'Karyawan';
$id_karyawan = $_SESSION['id_karyawan'] ?? '';

// FIX: Ambil foto profil dari database dengan kolom Photo_Profile
$profile_photo = '';
if (!empty($id_karyawan)) {
    $stmt_photo = sqlsrv_query($conn, "SELECT Photo_Profile FROM Karyawan WHERE ID_Karyawan = ?", array($id_karyawan));
    if ($stmt_photo !== false) {
        $row_photo = sqlsrv_fetch_array($stmt_photo, SQLSRV_FETCH_ASSOC);
        if ($row_photo && !empty($row_photo['Photo_Profile'])) {
            $profile_photo = $row_photo['Photo_Profile'];
            $_SESSION['Photo_Profile'] = $profile_photo;
        }
    }
}
if (empty($profile_photo)) {
    $profile_photo = $_SESSION['Photo_Profile'] ?? '';
}

// FIX: Sesuaikan path foto untuk folder dashboard/ (sama seperti customer.php tapi path relatif berbeda)
$sidebar_photo = '';
if (!empty($profile_photo)) {
    if (strpos($profile_photo, '../') === 0) {
        $sidebar_photo = $profile_photo;
    } elseif (strpos($profile_photo, 'uploads/') === 0) {
        $sidebar_photo = '../' . $profile_photo;
    } else {
        $sidebar_photo = '../uploads/profiles/' . $profile_photo;
    }
    // Cek file exists, jika tidak ada kosongkan
    if (!file_exists($sidebar_photo)) {
        $sidebar_photo = '';
    }
}

function safeQuery($conn, $sql, $params = array()) {
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        error_log("SQL Error: " . print_r(sqlsrv_errors(), true));
        return null;
    }
    return $stmt;
}
function safeFetch($stmt) {
    if ($stmt === null) return false;
    return sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
}

// STATISTIK KARYAWAN
$total_customer = 0;
$q = safeQuery($conn, "SELECT COUNT(*) as total FROM Customer WHERE Is_Deleted = 0");
$d = safeFetch($q); if ($d) $total_customer = $d['total'] ?? 0;

$total_booking = 0;
$q = safeQuery($conn, "SELECT COUNT(*) as total FROM Booking");
$d = safeFetch($q); if ($d) $total_booking = $d['total'] ?? 0;

$total_booking_today = 0;
$q = safeQuery($conn, "SELECT COUNT(*) as total FROM Booking WHERE CAST(Tanggal_Booking AS DATE) = CAST(GETDATE() AS DATE)");
$d = safeFetch($q); if ($d) $total_booking_today = $d['total'] ?? 0;

$total_langganan = 0;
$q = safeQuery($conn, "SELECT COUNT(*) as total FROM Langganan");
$d = safeFetch($q); if ($d) $total_langganan = $d['total'] ?? 0;

$total_pembelian = 0;
$q = safeQuery($conn, "SELECT COUNT(*) as total FROM Beli_Alat");
$d = safeFetch($q); if ($d) $total_pembelian = $d['total'] ?? 0;

$total_pembatalan = 0;
$q = safeQuery($conn, "SELECT COUNT(*) as total FROM Pembatalan_Booking");
$d = safeFetch($q); if ($d) $total_pembatalan = $d['total'] ?? 0;

$total_omzet = 0;
$q = safeQuery($conn, "SELECT ISNULL(SUM(Total_Bayar), 0) as total FROM Booking WHERE Status IN (1, 2)");
$d = safeFetch($q); if ($d) $total_omzet = $d['total'] ?? 0;

$total_alat = 0; $total_alat_aktif = 0;
$q = safeQuery($conn, "SELECT COUNT(*) as total FROM Alat WHERE Is_Deleted = 0");
$d = safeFetch($q); if ($d) $total_alat = $d['total'] ?? 0;
$q = safeQuery($conn, "SELECT COUNT(*) as total FROM Alat WHERE Status = 1 AND Is_Deleted = 0");
$d = safeFetch($q); if ($d) $total_alat_aktif = $d['total'] ?? 0;

// CHART DATA
$chart_labels = ['Menunggu', 'Berhasil', 'Selesai', 'Dibatalkan'];
$chart_data = [];
for ($i = 0; $i <= 3; $i++) {
    $q = safeQuery($conn, "SELECT COUNT(*) as total FROM Booking WHERE Status = ?", array($i));
    $d = safeFetch($q);
    $chart_data[] = $d ? ($d['total'] ?? 0) : 0;
}

// DATA BOOKING TERBARU
$recent_booking = [];
$q = safeQuery($conn, "SELECT TOP 5 b.ID_Booking, c.Nama_Customer, b.Tanggal_Booking, b.Status, b.Total_Bayar FROM Booking b JOIN Customer c ON b.ID_Customer = c.ID_Customer ORDER BY b.Created_Date DESC");
if ($q !== null) {
    while ($row = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)) {
        $recent_booking[] = $row;
    }
}

function rupiahFormat($n) { return 'Rp ' . number_format($n, 0, ',', '.'); }

function formatTanggal($tanggal) {
    if (empty($tanggal)) return '-';
    if (is_object($tanggal) && method_exists($tanggal, 'format')) {
        return $tanggal->format('d M Y');
    }
    return date('d M Y', strtotime($tanggal));
}

$status_map = [
    0 => ['label' => 'Menunggu', 'class' => 'sp-pending'],
    1 => ['label' => 'Berhasil', 'class' => 'sp-active'],
    2 => ['label' => 'Selesai', 'class' => 'sp-active'],
    3 => ['label' => 'Dibatalkan', 'class' => 'sp-inactive']
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Dashboard Karyawan | HoopBall</title>
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

.sidebar { width: var(--sidebar-w); background: var(--sidebar); height: 100vh; position: fixed; top: 0; left: 0; display: flex; flex-direction: column; padding: 28px 18px; border-right: 1px solid rgba(255,255,255,.04); z-index: 200; overflow-y: auto; scrollbar-width: none; }
.sidebar::-webkit-scrollbar { display: none; }
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
.sb-avatar { width: 36px; height: 36px; background: var(--orange); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 14px; flex-shrink: 0; overflow: hidden; }
.sb-avatar img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
.sb-user-name { font-size: 13px; font-weight: 800; color: #E5E7EB; line-height: 1.1; }
.sb-user-role { font-size: 10px; color: var(--orange); font-weight: 700; text-transform: uppercase; }
.sb-logout { margin-left: auto; color: #4B5563; font-size: 13px; transition: .2s; cursor: pointer; text-decoration: none; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 8px; }
.sb-logout:hover { color: var(--red); background: rgba(239,68,68,.1); }

.main { margin-left: var(--sidebar-w); flex: 1; display: flex; flex-direction: column; min-height: 100vh; }
.topbar { background: var(--card-bg); height: var(--topbar-h); padding: 0 40px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 100; box-shadow: 0 1px 0 rgba(0,0,0,.04); }
.topbar-left { display: flex; flex-direction: column; }
.topbar-title { font-family: 'Barlow Condensed', sans-serif; font-size: 26px; font-weight: 900; color: var(--text); letter-spacing: -.5px; line-height: 1; }
.topbar-breadcrumb { font-size: 12px; color: var(--muted); font-weight: 600; margin-top: 2px; }
.topbar-right { display: flex; align-items: center; gap: 16px; }
.topbar-btn { width: 38px; height: 38px; border-radius: 10px; background: var(--bg); border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; color: var(--muted); cursor: pointer; font-size: 14px; text-decoration: none; transition: .2s; position: relative; }
.topbar-btn:hover { border-color: var(--orange); color: var(--orange); background: var(--orange-lt); }
.notif-dot { position: absolute; top: 7px; right: 7px; width: 7px; height: 7px; background: var(--orange); border-radius: 50%; border: 2px solid #fff; }
.dropdown-wrap { position: relative; }
.topbar-user { display: flex; align-items: center; gap: 10px; background: var(--bg); border: 1px solid var(--border); padding: 6px 14px 6px 8px; border-radius: 12px; cursor: pointer; transition: .2s; }
.topbar-user:hover { border-color: var(--orange); }
.t-avatar { width: 32px; height: 32px; background: var(--orange); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 13px; overflow: hidden; }
.t-avatar img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
.t-name { font-size: 13px; font-weight: 800; color: var(--text); line-height: 1.1; }
.t-role { font-size: 10px; color: var(--orange); font-weight: 700; text-transform: uppercase; }
.t-chevron { color: var(--muted); font-size: 10px; margin-left: 4px; }
.dropdown-menu { display: none; position: absolute; right: 0; top: calc(100% + 8px); background: #fff; min-width: 200px; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 15px 40px rgba(0,0,0,.12); overflow: hidden; padding: 8px 0; z-index: 999; }
.dropdown-wrap:hover .dropdown-menu { display: block; }
.dropdown-wrap.active .dropdown-menu { display: block; }
.dd-item { display: flex; align-items: center; gap: 10px; padding: 11px 16px; color: #444; text-decoration: none; font-size: 13px; font-weight: 700; transition: .15s; }
.dd-item:hover { background: #FFF7ED; color: var(--orange); }
.dd-item i { font-size: 14px; width: 18px; text-align: center; }
.dd-divider { border: none; border-top: 1px solid #F3F4F6; margin: 4px 0; }

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

.chart-section { display: grid; grid-template-columns: 1fr 340px; gap: 22px; margin-bottom: 28px; }
@media(max-width:1100px){ .chart-section { grid-template-columns: 1fr; } }
.chart-card { background: var(--card-bg); border-radius: 16px; border: 1px solid var(--border); padding: 24px; }
.chart-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
.chart-title { font-size: 15px; font-weight: 800; color: var(--text); display: flex; align-items: center; gap: 8px; }
.chart-title i { color: var(--orange); font-size: 14px; }
.chart-badge { background: var(--orange-lt); color: var(--orange); font-size: 11px; font-weight: 800; padding: 4px 10px; border-radius: 20px; }
.chart-container { position: relative; height: 350px; }

.mini-stat-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.mini-stat { background: var(--border-lt); border-radius: 12px; padding: 16px; border: 1px solid var(--border); }
.mini-stat-label { font-size: 11px; color: var(--muted); font-weight: 700; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 4px; }
.mini-stat-value { font-family: 'Barlow Condensed', sans-serif; font-size: 22px; font-weight: 900; color: var(--text); }
.mini-stat-value.red { color: var(--red); }
.mini-stat-value.orange { color: var(--orange); }

.dashboard-grid { display: grid; grid-template-columns: 1fr 340px; gap: 22px; }
@media(max-width:1100px){ .dashboard-grid { grid-template-columns: 1fr; } }

.card { background: var(--card-bg); border-radius: 16px; border: 1px solid var(--border); overflow: hidden; transition: all .2s ease; }
.card:hover { box-shadow: 0 8px 24px rgba(0,0,0,.06); }
.card-header { padding: 20px 24px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
.card-title { font-size: 15px; font-weight: 800; color: var(--text); display: flex; align-items: center; gap: 8px; }
.card-title i { color: var(--orange); font-size: 14px; }
.card-badge { background: var(--orange-lt); color: var(--orange); font-size: 11px; font-weight: 800; padding: 4px 10px; border-radius: 20px; }
.card-link { font-size: 12px; font-weight: 700; color: var(--orange); text-decoration: none; display: flex; align-items: center; gap: 4px; transition: .2s; }
.card-link:hover { gap: 8px; }
.card-body { padding: 20px 24px; }

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

.quick-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.quick-card { background: var(--bg); border: 1px solid var(--border); border-radius: 12px; padding: 20px; text-decoration: none; text-align: center; transition: all .2s ease; display: flex; flex-direction: column; align-items: center; gap: 10px; }
.quick-card:hover { border-color: var(--orange); background: var(--orange-lt); transform: translateY(-2px); box-shadow: 0 8px 20px rgba(255,69,0,.1); }
.quick-card i { font-size: 24px; transition: .2s; }
.quick-card:hover i { transform: scale(1.1); }
.quick-card span { font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .4px; }

#clock-display { display: flex; align-items: center; gap: 16px; }
.clock-time { font-family: 'Barlow Condensed', sans-serif; font-size: 26px; font-weight: 900; color: var(--orange); display: flex; align-items: center; gap: 6px; line-height: 1; }
.clock-colon { color: var(--orange); opacity: .5; animation: blink 1s infinite; }
@keyframes blink { 0%, 100% { opacity: .5; } 50% { opacity: 1; } }
.clock-divider { width: 1.5px; height: 28px; background-color: var(--border); }
.clock-date { font-family: 'Barlow', sans-serif; font-size: 13px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; }

html, body { scrollbar-width: none; -ms-overflow-style: none; }
html::-webkit-scrollbar, body::-webkit-scrollbar { display: none; }

@media(max-width: 768px) {
    .sidebar { width: 0; overflow: hidden; padding: 0; }
    .main { margin-left: 0; }
    .content { padding: 20px; }
    .topbar { padding: 0 20px; }
    .stat-grid { grid-template-columns: 1fr 1fr; }
}


/* ============ ANIMATIONS (from customer homepage) ============ */
/* ============ KEYFRAMES ============ */

@keyframes fadeInUp { from{opacity:0;transform:translateY(40px)} to{opacity:1;transform:translateY(0)} }
@keyframes fadeInDown { from{opacity:0;transform:translateY(-30px)} to{opacity:1;transform:translateY(0)} }
@keyframes fadeInLeft { from{opacity:0;transform:translateX(-40px)} to{opacity:1;transform:translateX(0)} }
@keyframes fadeInRight { from{opacity:0;transform:translateX(40px)} to{opacity:1;transform:translateX(0)} }
@keyframes fadeIn { from{opacity:0} to{opacity:1} }
@keyframes scaleIn { from{opacity:0;transform:scale(0.8)} to{opacity:1;transform:scale(1)} }
@keyframes slideInUp { from{opacity:0;transform:translateY(60px) scale(0.95)} to{opacity:1;transform:translateY(0) scale(1)} }
@keyframes float { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-10px)} }
@keyframes pulse { 0%,100%{transform:scale(1);box-shadow:0 0 0 0 rgba(255,82,0,0.4)} 50%{transform:scale(1.05);box-shadow:0 0 0 15px rgba(255,82,0,0)} }
@keyframes shimmer { 0%{background-position:-200% 0} 100%{background-position:200% 0} }
@keyframes bounceIn { 0%{opacity:0;transform:scale(0.3)} 50%{opacity:1;transform:scale(1.05)} 70%{transform:scale(0.9)} 100%{transform:scale(1)} }
@keyframes rotateIn { from{opacity:0;transform:rotate(-180deg) scale(0.5)} to{opacity:1;transform:rotate(0) scale(1)} }
@keyframes gradientShift { 0%{background-position:0% 50%} 50%{background-position:100% 50%} 100%{background-position:0% 50%} }
@keyframes ripple { 0%{transform:scale(1);opacity:1} 100%{transform:scale(1.5);opacity:0} }
@keyframes glow { 0%,100%{box-shadow:0 0 5px rgba(255,82,0,0.3)} 50%{box-shadow:0 0 25px rgba(255,82,0,0.6),0 0 50px rgba(255,82,0,0.2)} }
@keyframes drawLine { from{width:0} to{width:60px} }
@keyframes wave { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-15px)} }
@keyframes spinSlow { from{transform:rotate(0deg)} to{transform:rotate(360deg)} }
@keyframes countUp { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }
@keyframes shake { 0%,100%{transform:translateX(0)} 25%{transform:translateX(-5px)} 75%{transform:translateX(5px)} }
@keyframes borderGlow { 0%,100%{border-color:rgba(255,82,0,0.1)} 50%{border-color:rgba(255,82,0,0.4)} }
@keyframes textReveal { from{clip-path:inset(0 100% 0 0)} to{clip-path:inset(0 0 0 0)} }
@keyframes iconPop { 0%{transform:scale(0)} 60%{transform:scale(1.2)} 100%{transform:scale(1)} }
@keyframes neonPulse { 0%,100%{text-shadow:0 0 5px rgba(255,82,0,0.5),0 0 10px rgba(255,82,0,0.3)} 50%{text-shadow:0 0 10px rgba(255,82,0,0.8),0 0 20px rgba(255,82,0,0.5),0 0 30px rgba(255,82,0,0.3)} }
@keyframes slideDown { from{transform:translateY(-100%);opacity:0} to{transform:translateY(0);opacity:1} }
@keyframes zoomIn { from{transform:scale(0.5);opacity:0} to{transform:scale(1);opacity:1} }
@keyframes flipX { from{transform:perspective(400px) rotateX(90deg);opacity:0} to{transform:perspective(400px) rotateX(0);opacity:1} }
@keyframes flipY { from{transform:perspective(400px) rotateY(90deg);opacity:0} to{transform:perspective(400px) rotateY(0);opacity:1} }
@keyframes swing { 0%{transform:rotate(0)} 20%{transform:rotate(15deg)} 40%{transform:rotate(-10deg)} 60%{transform:rotate(5deg)} 80%{transform:rotate(-5deg)} 100%{transform:rotate(0)} }
@keyframes rubberBand { 0%{transform:scale(1)} 30%{transform:scale(1.25,0.75)} 40%{transform:scale(0.75,1.25)} 50%{transform:scale(1.15,0.85)} 65%{transform:scale(0.95,1.05)} 75%{transform:scale(1.05,0.95)} 100%{transform:scale(1)} }
@keyframes heartBeat { 0%{transform:scale(1)} 14%{transform:scale(1.3)} 28%{transform:scale(1)} 42%{transform:scale(1.3)} 70%{transform:scale(1)} }
@keyframes jello { 0%,100%{transform:skewX(0) skewY(0)} 22.2%{transform:skewX(-12.5deg) skewY(-12.5deg)} 33.3%{transform:skewX(6.25deg) skewY(6.25deg)} 44.4%{transform:skewX(-3.125deg) skewY(-3.125deg)} 55.5%{transform:skewX(1.5625deg) skewY(1.5625deg)} 66.6%{transform:skewX(-0.78125deg) skewY(-0.78125deg)} 77.7%{transform:skewX(0.390625deg) skewY(0.390625deg)} 88.8%{transform:skewX(-0.1953125deg) skewY(-0.1953125deg)} }
@keyframes rollIn { from{opacity:0;transform:translateX(-100%) rotate(-120deg)} to{opacity:1;transform:translateX(0) rotate(0)} }
@keyframes jackInTheBox { from{opacity:0;transform:scale(0.1) rotate(30deg);transform-origin:center bottom} 50%{transform:rotate(-10deg)} 70%{transform:rotate(3deg)} to{opacity:1;transform:scale(1)} }
@keyframes lightSpeedIn { from{transform:translate3d(100%,0,0) skewX(-30deg);opacity:0} 60%{transform:skewX(20deg);opacity:1} 80%{transform:skewX(-5deg)} to{transform:translate3d(0,0,0)} }


/* ============ ANIMATION CLASSES ============ */

.anim-hidden { opacity:0; }
.anim-fade-up { animation:fadeInUp 0.8s cubic-bezier(0.16,1,0.3,1) forwards; }
.anim-fade-down { animation:fadeInDown 0.8s cubic-bezier(0.16,1,0.3,1) forwards; }
.anim-fade-left { animation:fadeInLeft 0.8s cubic-bezier(0.16,1,0.3,1) forwards; }
.anim-fade-right { animation:fadeInRight 0.8s cubic-bezier(0.16,1,0.3,1) forwards; }
.anim-scale-in { animation:scaleIn 0.6s cubic-bezier(0.34,1.56,0.64,1) forwards; }
.anim-slide-up { animation:slideInUp 0.9s cubic-bezier(0.16,1,0.3,1) forwards; }
.anim-bounce-in { animation:bounceIn 0.8s cubic-bezier(0.68,-0.55,0.265,1.55) forwards; }
.anim-rotate-in { animation:rotateIn 0.7s cubic-bezier(0.16,1,0.3,1) forwards; }
.anim-card-flip { animation:cardFlip 0.8s cubic-bezier(0.16,1,0.3,1) forwards; }
.anim-text-reveal { animation:textReveal 1s cubic-bezier(0.16,1,0.3,1) forwards; }
.anim-elastic { animation:elastic 0.6s cubic-bezier(0.68,-0.55,0.265,1.55) forwards; }
.anim-zoom-in { animation:zoomIn 0.5s cubic-bezier(0.16,1,0.3,1) forwards; }
.anim-flip-x { animation:flipX 0.6s cubic-bezier(0.16,1,0.3,1) forwards; }
.anim-flip-y { animation:flipY 0.6s cubic-bezier(0.16,1,0.3,1) forwards; }
.anim-swing { animation:swing 1s ease forwards; }
.anim-rubber { animation:rubberBand 1s ease forwards; }
.anim-heart { animation:heartBeat 1.3s ease-in-out forwards; }
.anim-jello { animation:jello 0.9s ease forwards; }
.anim-roll-in { animation:rollIn 0.6s ease forwards; }
.anim-jack-in { animation:jackInTheBox 0.8s ease forwards; }
.anim-light-speed { animation:lightSpeedIn 0.8s ease forwards; }
.anim-neon { animation:neonPulse 2s ease-in-out infinite; }

.delay-100 { animation-delay:0.1s; }
.delay-200 { animation-delay:0.2s; }
.delay-300 { animation-delay:0.3s; }
.delay-400 { animation-delay:0.4s; }
.delay-500 { animation-delay:0.5s; }
.delay-600 { animation-delay:0.6s; }
.delay-700 { animation-delay:0.7s; }
.delay-800 { animation-delay:0.8s; }
.delay-900 { animation-delay:0.9s; }
.delay-1000 { animation-delay:1.0s; }
.delay-1200 { animation-delay:1.2s; }
.delay-1500 { animation-delay:1.5s; }
.delay-2000 { animation-delay:2.0s; }


/* ============ INTERSECTION OBSERVER ============ */

.reveal { opacity:0; transform:translateY(40px); transition:all 0.8s cubic-bezier(0.16,1,0.3,1); }
.reveal.active { opacity:1; transform:translateY(0); }
.reveal-left { opacity:0; transform:translateX(-50px); transition:all 0.8s cubic-bezier(0.16,1,0.3,1); }
.reveal-left.active { opacity:1; transform:translateX(0); }
.reveal-right { opacity:0; transform:translateX(50px); transition:all 0.8s cubic-bezier(0.16,1,0.3,1); }
.reveal-right.active { opacity:1; transform:translateX(0); }
.reveal-scale { opacity:0; transform:scale(0.9); transition:all 0.7s cubic-bezier(0.16,1,0.3,1); }
.reveal-scale.active { opacity:1; transform:scale(1); }
.reveal-stagger .stagger-item { opacity:0; transform:translateY(30px); transition:all 0.6s cubic-bezier(0.16,1,0.3,1); }
.reveal-stagger.active .stagger-item { opacity:1; transform:translateY(0); }
.reveal-stagger.active .stagger-item:nth-child(1){transition-delay:0s}
.reveal-stagger.active .stagger-item:nth-child(2){transition-delay:0.1s}
.reveal-stagger.active .stagger-item:nth-child(3){transition-delay:0.2s}
.reveal-stagger.active .stagger-item:nth-child(4){transition-delay:0.3s}
.reveal-stagger.active .stagger-item:nth-child(5){transition-delay:0.4s}
.reveal-flip .stagger-item { opacity:0; transform:perspective(1000px) rotateY(90deg); transition:all 0.7s cubic-bezier(0.16,1,0.3,1); }
.reveal-flip.active .stagger-item { opacity:1; transform:perspective(1000px) rotateY(0); }
.reveal-flip.active .stagger-item:nth-child(1){transition-delay:0s}
.reveal-flip.active .stagger-item:nth-child(2){transition-delay:0.15s}
.reveal-flip.active .stagger-item:nth-child(3){transition-delay:0.3s}
.reveal-flip.active .stagger-item:nth-child(4){transition-delay:0.45s}
.reveal-flip.active .stagger-item:nth-child(5){transition-delay:0.6s}
.reveal-zoom .stagger-item { opacity:0; transform:scale(0.5); transition:all 0.6s cubic-bezier(0.34,1.56,0.64,1); }
.reveal-zoom.active .stagger-item { opacity:1; transform:scale(1); }
.reveal-zoom.active .stagger-item:nth-child(1){transition-delay:0s}
.reveal-zoom.active .stagger-item:nth-child(2){transition-delay:0.1s}
.reveal-zoom.active .stagger-item:nth-child(3){transition-delay:0.2s}
.reveal-zoom.active .stagger-item:nth-child(4){transition-delay:0.3s}


/* ============ SCROLL PROGRESS BAR ============ */

.scroll-progress { position:fixed; top:0; left:0; height:3px; background:linear-gradient(90deg,var(--primary),#FF8C42); z-index:9999; transform-origin:left; transform:scaleX(0); transition:transform 0.1s ease-out; }


/* ============ FLOATING PARTICLES ============ */

.particle { position:absolute; border-radius:50%; pointer-events:none; }
.particle-1 { width:6px; height:6px; background:rgba(255,82,0,0.3); animation:float 4s ease-in-out infinite; }
.particle-2 { width:8px; height:8px; background:rgba(255,82,0,0.2); animation:float 5s ease-in-out infinite 1s; }
.particle-3 { width:4px; height:4px; background:rgba(255,82,0,0.4); animation:float 3s ease-in-out infinite 0.5s; }


/* ============ CARD SHINE ============ */

.card-shine { position:relative; overflow:hidden; }
.card-shine::before { content:''; position:absolute; top:0; left:-100%; width:100%; height:100%; background:linear-gradient(90deg,transparent,rgba(255,255,255,0.2),transparent); transition:left 0.6s ease; z-index:10; pointer-events:none; }
.card-shine:hover::before { left:100%; }


/* ============ SMOOTH SCROLL ============ */

html { scroll-behavior:smooth; }


/* ============ CUSTOM SCROLLBAR ============ */

::-webkit-scrollbar { width:8px; }
::-webkit-scrollbar-track { background:#f1f1f1; }
::-webkit-scrollbar-thumb { background:var(--primary); border-radius:4px; }
::-webkit-scrollbar-thumb:hover { background:var(--primary-hover); }


/* ============ SELECTION COLOR ============ */

::selection { background:rgba(255,82,0,0.3); color:#1C1C1E; }


/* ============ FOCUS STYLES ============ */

:focus-visible { outline:2px solid var(--primary); outline-offset:2px; }


/* ============ REDUCED MOTION ============ */

@media (prefers-reduced-motion:reduce) {
  *,*::before,*::after { animation-duration:0.01ms !important; animation-iteration-count:1 !important; transition-duration:0.01ms !important; }
}



</style>
</head>
<body>

<aside class="sidebar anim-fade-left">
    <a href="view_admin.php" class="sb-brand">
        <div class="sb-icon"><i class="fa-solid fa-basketball"></i></div>
        <div><div class="sb-brand-name">HOOP BALL</div><div class="sb-brand-sub">Sistem Managemen</div></div>
    </a>

    <div class="sb-section-label">Operasional</div>
    <nav>
        <a href="view_admin.php" class="sb-link active">
            <div class="sb-icon-wrap"><i class="fa-solid fa-house"></i></div>Dashboard
        </a>
        <a href="../master/customer.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-users"></i></div>Kelola Customer
        </a>
        <a href="../master/lapangan.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-layer-group"></i></div>Kelola Lapangan
        </a>
        <a href="../master/fasilitas_lapangan.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-list-check"></i></div>Kelola Fasilitas
        </a>
        <a href="../master/jadwal.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-calendar-days"></i></div>Kelola Jadwal
        </a>
        <a href="../master/promo.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-tags"></i></div>Kelola Promo
        </a>
        <a href="../master/tipe_member.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-id-card"></i></div>Kelola Tipe Member
        </a>
        <a href="../master/alat.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-toolbox"></i></div>Kelola Alat
        </a>
    </nav>

    <div class="sb-section-label">Transaksi</div>
    <nav>
        <a href="../transaksi/booking.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-calendar-check"></i></div>Kelola Booking
        </a>
        <a href="../transaksi/langganan.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-crown"></i></div>Kelola Langganan
        </a>
        <a href="../transaksi/beli_alat.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-cart-shopping"></i></div>Kelola Pembelian Alat
        </a>
        <a href="../transaksi/pembatalan.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-ban"></i></div>Kelola Pembatalan
        </a>
    </nav>

    <div class="sb-section-label">Akun</div>
    <a href="../profile/profile.php" class="sb-link">
        <div class="sb-icon-wrap"><i class="fa-solid fa-id-badge"></i></div>Profil Saya
    </a>

    <div class="sb-bottom">
        <div class="sb-user">
            <div class="sb-avatar">
                <?php if (!empty($sidebar_photo)): ?>
                    <img src="<?= $sidebar_photo ?>" alt="Profile">
                <?php else: ?>
                    <i class="fa-solid fa-user"></i>
                <?php endif; ?>
            </div>
            <div><div class="sb-user-name"><?= strtoupper(htmlspecialchars($nama)) ?></div><div class="sb-user-role">KARYAWAN</div></div>
            <a href="../login/logout.php" class="sb-logout" title="Keluar"><i class="fa-solid fa-right-from-bracket"></i></a>
        </div>
    </div>
</aside>

<main class="main">
<header class="topbar anim-fade-down">
    <div class="topbar-left">
        <div class="topbar-title">Dashboard Karyawan</div>
        <div class="topbar-breadcrumb">Dashboard / Ringkasan</div>
    </div>
    <div class="topbar-right">
        <div id="clock-display">
            <div class="clock-time"><span id="h">00</span><span class="clock-colon">:</span><span id="m">00</span><span class="clock-colon">:</span><span id="s">00</span></div>
            <div class="clock-divider"></div>
            <div class="clock-date" id="full-date">MEMUAT...</div>
        </div>
        <a href="#" class="topbar-btn"><i class="fa-solid fa-magnifying-glass"></i></a>
        <a href="#" class="topbar-btn"><i class="fa-solid fa-bell"></i><?php if($total_booking_today > 0): ?><span class="notif-dot"></span><?php endif; ?></a>
        <div class="dropdown-wrap">
            <div class="topbar-user">
                <div class="t-avatar">
                    <?php if (!empty($sidebar_photo)): ?>
                        <img src="<?= $sidebar_photo ?>" alt="Profile">
                    <?php else: ?>
                        <i class="fa-solid fa-user"></i>
                    <?php endif; ?>
                </div>
                <div><div class="t-name"><?= strtoupper(htmlspecialchars($nama)) ?></div><div class="t-role">KARYAWAN</div></div>
                <i class="fa-solid fa-chevron-down t-chevron"></i>
            </div>
            <div class="dropdown-menu">
                <a href="../profile/profile.php" class="dd-item"><i class="fa-solid fa-id-badge"></i> Profil Saya</a>
                <hr class="dd-divider">
                <a href="../login/logout.php" class="dd-item" style="color:var(--red);"><i class="fa-solid fa-right-from-bracket"></i> Keluar</a>
            </div>
        </div>
    </div>
</header>

<div class="content">
    <div class="welcome-banner anim-fade-up delay-200">
        <div class="wb-deco"></div><div class="wb-deco2"></div>
        <div class="wb-text"><div class="wb-greeting">Selamat Datang Kembali</div><div class="wb-name"><?= strtoupper(htmlspecialchars($nama)) ?> 👋</div><div class="wb-sub">Kelola operasional dan transaksi penyewaan lapangan.</div></div>
        <div class="wb-icon"><i class="fa-solid fa-basketball"></i></div>
    </div>

    <div class="stat-grid">
        <div class="stat-card sc-blue anim-fade-up delay-300 card-shine">
            <div class="stat-header"><div class="stat-icon-wrap si-blue"><i class="fa-solid fa-users"></i></div><div class="stat-trend trend-up"><i class="fa-solid fa-arrow-up"></i> Total</div></div>
            <div class="stat-value"><?= $total_customer ?></div><div class="stat-label">Total Customer</div><div class="stat-sublabel">Customer terdaftar</div>
        </div>
        <div class="stat-card sc-green anim-fade-up delay-400 card-shine">
            <div class="stat-header"><div class="stat-icon-wrap si-green"><i class="fa-solid fa-calendar-check"></i></div><div class="stat-trend trend-up"><i class="fa-solid fa-arrow-up"></i> Total</div></div>
            <div class="stat-value"><?= $total_booking ?></div><div class="stat-label">Total Booking</div><div class="stat-sublabel">Semua transaksi</div>
        </div>
        <div class="stat-card sc-orange anim-fade-up delay-500 card-shine">
            <div class="stat-header"><div class="stat-icon-wrap si-orange"><i class="fa-solid fa-crown"></i></div><div class="stat-trend trend-up"><i class="fa-solid fa-arrow-up"></i> Total</div></div>
            <div class="stat-value"><?= $total_langganan ?></div><div class="stat-label">Langganan Member</div><div class="stat-sublabel">Member aktif</div>
        </div>
        <div class="stat-card sc-purple anim-fade-up delay-600 card-shine">
            <div class="stat-header"><div class="stat-icon-wrap si-purple"><i class="fa-solid fa-money-bill-wave"></i></div><div class="stat-trend trend-up"><i class="fa-solid fa-arrow-up"></i> Total</div></div>
            <div class="stat-value" style="font-size:24px;"><?= rupiahFormat($total_omzet) ?></div><div class="stat-label">Total Omzet</div><div class="stat-sublabel">Pendapatan booking</div>
        </div>
    </div>

    <div class="chart-section reveal">
        <div class="chart-card hover-lift">
            <div class="chart-header">
                <div class="chart-title"><i class="fa-solid fa-chart-column"></i> Booking per Status</div>
                <span class="chart-badge"><?= array_sum($chart_data) ?> Total</span>
            </div>
            <div class="chart-container"><canvas id="bookingChart"></canvas></div>
        </div>

        <div class="chart-card" style="display: flex; flex-direction: column; height: 100%;">
            <div class="chart-header">
                <div class="chart-title"><i class="fa-solid fa-circle-exclamation"></i> Ringkasan Operasional</div>
            </div>
            <div class="mini-stat-row" style="flex-grow: 1;">
                <div class="mini-stat" style="display: flex; flex-direction: column; justify-content: center;">
                    <div class="mini-stat-label">Booking Hari Ini</div>
                    <div class="mini-stat-value <?= $total_booking_today > 0 ? 'orange' : '' ?>"><?= $total_booking_today ?></div>
                </div>
                <div class="mini-stat" style="display: flex; flex-direction: column; justify-content: center;">
                    <div class="mini-stat-label">Total Pembatalan</div>
                    <div class="mini-stat-value red"><?= $total_pembatalan ?></div>
                </div>
                <div class="mini-stat" style="display: flex; flex-direction: column; justify-content: center;">
                    <div class="mini-stat-label">Alat Tersedia</div>
                    <div class="mini-stat-value"><?= $total_alat_aktif ?> / <?= $total_alat ?></div>
                </div>
                <div class="mini-stat" style="display: flex; flex-direction: column; justify-content: center;">
                    <div class="mini-stat-label">Total Pembelian</div>
                    <div class="mini-stat-value"><?= $total_pembelian ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="dashboard-grid reveal">
        <div class="card hover-lift">
            <div class="card-header"><div class="card-title"><i class="fa-solid fa-calendar-check"></i> Booking Terbaru</div><div style="display:flex; align-items:center; gap:12px;"><span class="card-badge"><?= count($recent_booking) ?> data</span><a href="../transaksi/booking.php" class="card-link">Kelola <i class="fa-solid fa-arrow-right" style="font-size:10px;"></i></a></div></div>
            <div style="overflow-x:auto;">
                <table class="data-table">
                    <thead><tr><th>Customer</th><th>Tanggal</th><th>Status</th><th>Total</th></tr></thead>
                    <tbody>
                    <?php if(count($recent_booking) > 0): ?>
                    <?php foreach($recent_booking as $b):
                        $status = $status_map[$b['Status']] ?? ['label' => 'Unknown', 'class' => 'sp-pending'];
                    ?>
                        <tr>
                            <td><div class="cell-name"><?= htmlspecialchars($b['Nama_Customer']) ?></div><div class="cell-detail">#<?= $b['ID_Booking'] ?></div></td>
                            <td><?= formatTanggal($b['Tanggal_Booking']) ?></td>
                            <td><span class="status-pill <?= $status['class'] ?>"><?= $status['label'] ?></span></td>
                            <td style="font-weight:700;"><?= rupiahFormat($b['Total_Bayar']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4" style="text-align:center; padding:30px; color:var(--muted);"><i class="fa-solid fa-inbox" style="font-size:32px; margin-bottom:10px; opacity:.5; display:block;"></i><div style="font-size:13px; font-weight:700;">Belum ada data booking</div></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div style="display:flex; flex-direction:column; gap:20px;">
            <div class="card hover-lift">
                <div class="card-header"><div class="card-title"><i class="fa-solid fa-bolt"></i> Akses Cepat</div></div>
                <div class="card-body">
                    <div class="quick-grid">
                        <a href="../master/customer.php" class="quick-card" style="color:var(--blue);"><i class="fa-solid fa-users"></i><span>Kelola Customer</span></a>
                        <a href="../transaksi/booking.php" class="quick-card" style="color:var(--green);"><i class="fa-solid fa-calendar-check"></i><span>Kelola Booking</span></a>
                        <a href="../transaksi/langganan.php" class="quick-card" style="color:var(--purple);"><i class="fa-solid fa-crown"></i><span>Kelola Langganan</span></a>
                        <a href="../transaksi/alat.php" class="quick-card" style="color:var(--orange);"><i class="fa-solid fa-toolbox"></i><span>Kelola Alat</span></a>
                    </div>
                </div>
            </div>

            <div class="card hover-lift">
                <div class="card-header"><div class="card-title"><i class="fa-solid fa-circle-info"></i> Informasi Sistem</div></div>
                <div class="card-body">
                    <div style="display:flex; flex-direction:column; gap:12px;">
                        <div style="display:flex; align-items:center; gap:10px; padding:10px; background:var(--orange-lt); border-radius:8px;">
                            <i class="fa-solid fa-basketball" style="color:var(--orange); font-size:18px;"></i>
                            <div><div style="font-size:12px; font-weight:700; color:var(--text);">HoopBall Sistem</div><div style="font-size:11px; color:var(--muted);">v1.0 - Karyawan Dashboard</div></div>
                        </div>
                        <div style="font-size:12px; color:var(--muted); line-height:1.6;">Kelola customer, booking, langganan, alat, dan transaksi dari satu dashboard.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const userDropdown = document.querySelector('.dropdown-wrap');
    if (userDropdown) {
        userDropdown.addEventListener('click', function (e) {
            e.stopPropagation();
            this.classList.toggle('active');
        });
    }
    document.addEventListener('click', function () {
        if (userDropdown) userDropdown.classList.remove('active');
    });
});

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
    document.getElementById('full-date').innerText = days[now.getDay()] + ', ' + now.getDate() + ' ' + months[now.getMonth()] + ' ' + now.getFullYear();
}
setInterval(updateClock, 1000); updateClock();

const ctx = document.getElementById('bookingChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?= json_encode($chart_labels) ?>,
        datasets: [{
            label: 'Jumlah Booking',
            data: <?= json_encode($chart_data) ?>,
            backgroundColor: [
                'rgba(245, 158, 11, 0.8)',
                'rgba(16, 185, 129, 0.8)',
                'rgba(59, 130, 246, 0.8)',
                'rgba(239, 68, 68, 0.8)'
            ],
            borderColor: ['#F59E0B', '#10B981', '#3B82F6', '#EF4444'],
            borderWidth: 2,
            borderRadius: 8,
            borderSkipped: false,
            hoverBackgroundColor: [
                '#F59E0B',
                '#10B981',
                '#3B82F6',
                '#EF4444'
            ],
            hoverBorderWidth: 3,
            hoverBorderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: {
            duration: 1500,
            easing: 'easeOutQuart',
            delay: function(context) {
                return context.dataIndex * 200;
            }
        },
        transitions: {
            active: {
                animation: {
                    duration: 400,
                    easing: 'easeOutElastic'
                }
            }
        },
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
                        return context.label + ': ' + context.parsed.y + ' booking';
                    }
                }
            }
        },
        scales: {
            y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)', drawBorder: false }, ticks: { font: { family: 'Barlow', size: 11 }, color: '#6B7280', stepSize: 1 } },
            x: { grid: { display: false }, ticks: { font: { family: 'Barlow', size: 11 }, color: '#6B7280' } }
        },
        onHover: (event, chartElement) => {
            event.native.target.style.cursor = chartElement.length ? 'pointer' : 'default';
        }
    }
});


// ============================================================================
// INTERSECTION OBSERVER (Scroll Animations) - from customer homepage
// ============================================================================
document.addEventListener('DOMContentLoaded', function() {
    const observerOptions = {
        root: null,
        rootMargin: '0px',
        threshold: 0.1
    };

    const revealObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('active');
            }
        });
    }, observerOptions);

    // Observe all reveal elements
    document.querySelectorAll('.reveal, .reveal-left, .reveal-right, .reveal-scale, .reveal-stagger, .reveal-flip, .reveal-zoom').forEach(el => {
        revealObserver.observe(el);
    });
});

// ============================================================================
// SCROLL PROGRESS BAR
// ============================================================================
(function() {
    const progressBar = document.createElement('div');
    progressBar.className = 'scroll-progress';
    document.body.appendChild(progressBar);

    window.addEventListener('scroll', function() {
        const scrollTop = window.scrollY || document.documentElement.scrollTop;
        const scrollHeight = document.documentElement.scrollHeight - document.documentElement.clientHeight;
        const progress = scrollHeight > 0 ? scrollTop / scrollHeight : 0;
        progressBar.style.transform = 'scaleX(' + progress + ')';
    });
})();

</script>
</body>
</html>