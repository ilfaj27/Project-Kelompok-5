<?php
session_start();
$path_prefix = "../"; 
include '../includes/config.php';

if (!isset($_SESSION['login']) || $_SESSION['role'] !== 'pemilik') {
    header("Location: ../login/login.php");
    exit();
}

// ========================================================
// ⚠️ PANGGIL SENSOR AUTO LOGOUT IDLE DI SINI ⚠️
// ========================================================
require_once '../login/auto_logout.php';
// ========================================================

$role = $_SESSION['role'];
$nama = $_SESSION['nama'];

// FIX: Get profile photo from session, fallback to database
$profile_photo = $_SESSION['Photo_Profile'] ?? '';

if (empty($profile_photo) || (!empty($profile_photo) && !file_exists($profile_photo))) {
    $id_karyawan_session = $_SESSION['id_karyawan'] ?? $_SESSION['id_akun'] ?? '';
    if (!empty($id_karyawan_session)) {
        $photo_query = safeQuery($conn, "SELECT Photo_Profile FROM Karyawan WHERE ID_Karyawan = ? AND Is_Deleted = 0", array($id_karyawan_session));
        if ($photo_query !== null) {
            $row_photo = safeFetch($photo_query);
            if ($row_photo && !empty($row_photo['Photo_Profile'])) {
                $_SESSION['Photo_Profile'] = $row_photo['Photo_Profile'];
                $profile_photo = $row_photo['Photo_Profile'];
            }
        }
    }
}

if (!empty($profile_photo) && !file_exists($profile_photo)) {
    $profile_photo = '';
}

// Set sidebar variables
$sidebar_photo = $profile_photo;
$sidebar_folder = 'laporan';
$current_page = 'laporan_omzet';

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

function rupiahFormat($n) { 
    return 'Rp ' . number_format($n, 0, ',', '.'); 
}

// ============================================
// FILTER HANDLING
// ============================================
$filter_type = $_GET['filter'] ?? 'all';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

// ============================================
// STATISTIK OMZET - MENGGUNAKAN UDF
// ============================================
$omzet_booking = 0;
$omzet_langganan = 0;
$omzet_beli_alat = 0;
$total_refund = 0;
$total_batal = 0;

// UDF: fn_GetOmzetBookingStats - Statistik Omzet Booking
$q = safeQuery($conn, "SELECT * FROM dbo.fn_GetOmzetBookingStats(?, ?, ?)", array($filter_type, $start_date, $end_date));
$d = safeFetch($q);
if ($d) {
    $omzet_booking = $d['omzet'] ?? 0;
    $total_refund = $d['total_refund'] ?? 0;
    $total_batal = $d['total_biaya_batal'] ?? 0;
}

// UDF: fn_GetOmzetLanggananStats - Statistik Omzet Langganan
$q = safeQuery($conn, "SELECT * FROM dbo.fn_GetOmzetLanggananStats(?, ?, ?)", array($filter_type, $start_date, $end_date));
$d = safeFetch($q);
if ($d) {
    $omzet_langganan = $d['omzet'] ?? 0;
}

// UDF: fn_GetOmzetBeliAlatStats - Statistik Omzet Beli Alat
$q = safeQuery($conn, "SELECT * FROM dbo.fn_GetOmzetBeliAlatStats(?, ?, ?)", array($filter_type, $start_date, $end_date));
$d = safeFetch($q);
if ($d) {
    $omzet_beli_alat = $d['omzet'] ?? 0;
}

$total_omzet_kotor = $omzet_booking + $omzet_langganan + $omzet_beli_alat;
$total_omzet_bersih = $total_omzet_kotor - $total_refund;

// ============================================
// STATISTIK JUMLAH TRANSAKSI - MENGGUNAKAN UDF
// ============================================
$total_booking = 0;
$total_langganan = 0;
$total_beli = 0;
$total_batal_count = 0;

// UDF: fn_GetTransaksiCount - Total transaksi per kategori
$q = safeQuery($conn, "SELECT * FROM dbo.fn_GetTransaksiCount(?, ?, ?)", array($filter_type, $start_date, $end_date));
$d = safeFetch($q);
if ($d) {
    $total_booking = $d['total_booking'] ?? 0;
    $total_langganan = $d['total_langganan'] ?? 0;
    $total_beli = $d['total_beli'] ?? 0;
    $total_batal_count = $d['total_batal'] ?? 0;
}

// ============================================
// CHART DATA: Omzet per Sumber per Bulan - MENGGUNAKAN UDF
// ============================================
$chart_labels = [];
$chart_booking = [];
$chart_langganan = [];
$chart_beli = [];
$chart_refund = [];

// UDF: fn_GetOmzetChartData - Data chart omzet per bulan
$q = safeQuery($conn, "SELECT * FROM dbo.fn_GetOmzetChartData(?, ?, ?) ORDER BY tahun, bulan", array($filter_type, $start_date, $end_date));
if ($q !== null) {
    while ($row = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)) {
        $monthNames = ['','Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
        $chart_labels[] = $monthNames[(int)$row['bulan']] . ' ' . $row['tahun'];
        $chart_booking[] = ($row['booking_omzet'] ?? 0) - ($row['booking_refund'] ?? 0);
        $chart_langganan[] = $row['langganan_omzet'] ?? 0;
        $chart_beli[] = $row['beli_omzet'] ?? 0;
        $chart_refund[] = $row['booking_refund'] ?? 0;
    }
}

// ============================================
// DETAIL TRANSAKSI TERBARU - MENGGUNAKAN UDF
// ============================================
// UDF: fn_GetRecentBooking - 5 Booking terbaru
$recent_bookings = [];
$q = safeQuery($conn, "SELECT * FROM dbo.fn_GetRecentBooking(?, ?, ?)", array($filter_type, $start_date, $end_date));
if ($q !== null) {
    while ($row = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)) {
        $recent_bookings[] = $row;
    }
}

// UDF: fn_GetRecentLangganan - 5 Langganan terbaru
$recent_langganan = [];
$q = safeQuery($conn, "SELECT * FROM dbo.fn_GetRecentLangganan(?, ?, ?)", array($filter_type, $start_date, $end_date));
if ($q !== null) {
    while ($row = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)) {
        $recent_langganan[] = $row;
    }
}

// UDF: fn_GetRecentBeliAlat - 5 Pembelian alat terbaru
$recent_beli = [];
$q = safeQuery($conn, "SELECT * FROM dbo.fn_GetRecentBeliAlat(?, ?, ?)", array($filter_type, $start_date, $end_date));
if ($q !== null) {
    while ($row = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)) {
        $recent_beli[] = $row;
    }
}

function statusBookingLabel($status) {
    switch($status) {
        case 0: return ['Menunggu', 'sp-pending'];
        case 1: return ['Berhasil', 'sp-active'];
        case 2: return ['Selesai', 'sp-done'];
        case 3: return ['Dibatalkan', 'sp-inactive'];
        default: return ['Unknown', 'sp-pending'];
    }
}

function statusLanggananLabel($status) {
    switch($status) {
        case 0: return ['Menunggu', 'sp-pending'];
        case 1: return ['Aktif', 'sp-active'];
        case 2: return ['Berakhir', 'sp-done'];
        case 3: return ['Ditolak', 'sp-inactive'];
        default: return ['Unknown', 'sp-pending'];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<?php include '../includes/favicon.php'; ?>
<title>Laporan Omzet | HoopBall</title>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800;900&family=Barlow:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../asset/css/responsive_tipe_member.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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

.main { margin-left: var(--sidebar-w); flex: 1; display: flex; flex-direction: column; min-height: 100vh; }
.topbar { background: var(--card-bg); height: var(--topbar-h); padding: 0 40px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 100; box-shadow: 0 1px 0 rgba(0,0,0,.04); }
.topbar-left { display: flex; flex-direction: column; }
.topbar-title { font-family: 'Barlow Condensed', sans-serif; font-size: 26px; font-weight: 900; color: var(--text); letter-spacing: -.5px; line-height: 1; }
.topbar-breadcrumb { font-size: 12px; color: var(--muted); margin-top: 4px; }
.topbar-right { display: flex; align-items: center; gap: 16px; }
.topbar-btn { width: 38px; height: 38px; border-radius: 10px; background: var(--bg); border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; color: var(--muted); cursor: pointer; font-size: 14px; text-decoration: none; transition: .2s; position: relative; }
.topbar-btn:hover { border-color: var(--orange); color: var(--orange); background: var(--orange-lt); }
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

.filter-bar { background: var(--card-bg); border-radius: 16px; border: 1px solid var(--border); padding: 20px 24px; margin-bottom: 24px; display: flex; flex-wrap: wrap; gap: 14px; align-items: end; }
.filter-group { display: flex; flex-direction: column; gap: 6px; }
.filter-group label { font-size: 11px; font-weight: 800; color: var(--muted); text-transform: uppercase; letter-spacing: .5px; }
.filter-group select, .filter-group input { padding: 10px 14px; border: 1px solid var(--border); border-radius: 10px; font-family: 'Barlow', sans-serif; font-size: 13px; font-weight: 600; color: var(--text); background: var(--bg); min-width: 160px; outline: none; transition: .2s; }
.filter-group select:focus, .filter-group input:focus { border-color: var(--orange); box-shadow: 0 0 0 3px var(--orange-lt); }
.filter-btn { padding: 10px 20px; background: var(--orange); color: #fff; border: none; border-radius: 10px; font-family: 'Barlow', sans-serif; font-size: 13px; font-weight: 800; cursor: pointer; transition: .2s; display: flex; align-items: center; gap: 8px; text-decoration: none; }
.filter-btn:hover { background: var(--orange-dk); transform: translateY(-1px); }
.filter-btn.secondary { background: var(--bg); color: var(--text); border: 1px solid var(--border); }
.filter-btn.secondary:hover { background: var(--border-lt); }

.stat-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
@media(max-width:1200px){ .stat-grid { grid-template-columns: repeat(2, 1fr); } }
@media(max-width:768px){ .stat-grid { grid-template-columns: repeat(2, 1fr); } }
.stat-card { background: var(--card-bg); border-radius: 16px; padding: 20px 22px; border: 1px solid var(--border); position: relative; overflow: hidden; transition: all .2s ease; }
.stat-card:hover { transform: translateY(-3px); box-shadow: 0 12px 32px rgba(0, 0, 0, .08); }
.stat-card::before { content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; border-radius: 4px 0 0 4px; }
.sc-orange::before { background: var(--orange); }
.sc-green::before { background: var(--green); }
.sc-blue::before { background: var(--blue); }
.sc-purple::before { background: var(--purple); }
.sc-red::before { background: var(--red); }
.stat-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; }
.stat-icon-wrap { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 16px; }
.si-orange { background: var(--orange-lt); color: var(--orange); }
.si-green { background: var(--green-lt); color: var(--green); }
.si-blue { background: var(--blue-lt); color: var(--blue); }
.si-purple { background: var(--purple-lt); color: var(--purple); }
.si-red { background: var(--red-lt); color: var(--red); }
.stat-value { font-family: 'Barlow Condensed', sans-serif; font-size: 28px; font-weight: 900; color: var(--text); line-height: 1; margin-bottom: 4px; }
.stat-label { font-size: 11px; color: var(--muted); font-weight: 700; text-transform: uppercase; letter-spacing: .5px; }

.card { background: var(--card-bg); border-radius: 16px; border: 1px solid var(--border); overflow: hidden; transition: all .2s ease; margin-bottom: 24px; }
.card:hover { box-shadow: 0 8px 24px rgba(0,0,0,.06); }
.card-header { padding: 20px 24px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
.card-title { font-size: 15px; font-weight: 800; color: var(--text); display: flex; align-items: center; gap: 8px; }
.card-title i { color: var(--orange); font-size: 14px; }
.card-badge { background: var(--orange-lt); color: var(--orange); font-size: 11px; font-weight: 800; padding: 4px 10px; border-radius: 20px; }
.card-body { padding: 20px 24px; }

.data-table { width: 100%; border-collapse: collapse; table-layout: auto; }
.data-table th { padding: 12px 14px; font-size: 10px; font-weight: 800; color: var(--muted); text-transform: uppercase; letter-spacing: .6px; border-bottom: 2px solid var(--border-lt); text-align: left; background: var(--bg); }
.data-table td { padding: 14px 14px; font-size: 13px; border-bottom: 1px solid var(--border-lt); vertical-align: middle; }
.data-table tr:last-child td { border-bottom: none; }
.data-table tbody tr { transition: background .15s; }
.data-table tbody tr:hover td { background: #FAFAFA; }

.cell-name { font-weight: 700; color: var(--text); }
.cell-detail { font-size: 11px; color: var(--muted); font-weight: 600; margin-top: 2px; }
.cell-price { font-weight: 800; color: var(--text); }
.cell-price.green { color: var(--green); }
.cell-price.red { color: var(--red); }

.status-pill { padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .3px; display: inline-block; }
.sp-active { background: var(--green-lt); color: var(--green); }
.sp-done { background: var(--blue-lt); color: var(--blue); }
.sp-inactive { background: var(--red-lt); color: var(--red); }
.sp-pending { background: var(--yellow-lt); color: #D97706; }

.chart-container { position: relative; height: 320px; }

.dashboard-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 22px; }
@media(max-width:1100px){ .dashboard-grid { grid-template-columns: 1fr; } }

.breakdown-item { display: flex; align-items: center; justify-content: space-between; padding: 16px; background: var(--bg); border-radius: 12px; margin-bottom: 10px; border: 1px solid var(--border); transition: .2s; }
.breakdown-item:hover { border-color: var(--orange); }
.breakdown-item:last-child { margin-bottom: 0; }
.breakdown-icon { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
.bi-orange { background: var(--orange-lt); color: var(--orange); }
.bi-green { background: var(--green-lt); color: var(--green); }
.bi-blue { background: var(--blue-lt); color: var(--blue); }
.bi-red { background: var(--red-lt); color: var(--red); }
.bi-purple { background: var(--purple-lt); color: var(--purple); }
.bi-yellow { background: var(--yellow-lt); color: var(--yellow); }
.breakdown-info { flex: 1; margin-left: 14px; }
.breakdown-name { font-size: 14px; font-weight: 700; color: var(--text); }
.breakdown-count { font-size: 12px; color: var(--muted); margin-top: 2px; }
.breakdown-value { font-family: 'Barlow Condensed', sans-serif; font-size: 20px; font-weight: 900; color: var(--text); }
.breakdown-value.green { color: var(--green); }
.breakdown-value.red { color: var(--red); }

@media print {
    .sidebar, .topbar, .filter-bar, .sb-bottom { display: none !important; }
    .main { margin-left: 0 !important; }
    .content { padding: 20px !important; }
    .card { break-inside: avoid; page-break-inside: avoid; }
}

#clock-display { display: flex; align-items: center; gap: 16px; }
.clock-time { font-family: 'Barlow Condensed', sans-serif; font-size: 26px; font-weight: 900; color: var(--orange); display: flex; align-items: center; gap: 6px; line-height: 1; }
.clock-colon { color: var(--orange); opacity: .5; animation: blink 1s infinite; }
@keyframes blink { 0%, 100% { opacity: .5; } 50% { opacity: 1; } }
.clock-divider { width: 1.5px; height: 28px; background-color: var(--border); }
.clock-date { font-family: 'Barlow', sans-serif; font-size: 13px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; }
html, body { scrollbar-width: none; -ms-overflow-style: none; }
html::-webkit-scrollbar, body::-webkit-scrollbar { display: none; }

.topbar-user { background-color: #FFFFFF !important; }
.topbar-btn:hover, .topbar-user:hover { background-color: #E5E7EB !important; border-color: #D1D5DB !important; color: #4B5563 !important; }
.topbar-btn:active, .topbar-user:active { background-color: #D1D5DB !important; border-color: #9CA3AF !important; color: #1F2937 !important; }

.text-center { text-align: center !important; }
.text-left { text-align: left !important; }
.text-right { text-align: right !important; }

.swal2-popup { animation: none !important; transition: none !important; }
.swal2-icon { animation: none !important; }
.swal2-icon.swal2-success .swal2-success-ring,
.swal2-icon.swal2-success [class^="swal2-success-line"],
.swal2-icon.swal2-error [class^="swal2-x-mark-line"],
.swal2-icon.swal2-warning { animation: none !important; }
html.swal2-shown, body.swal2-shown, body.swal2-height-auto { padding-right: 0 !important; }
</style>
</head>
<body>

<?php
$current_page = 'laporan_omzet';
$sidebar_folder = 'laporan';
include '../includes/sidebar.php';
?>

<main class="main">

<?php
$topbar_title = 'Laporan Omzet';
$topbar_breadcrumb = 'Laporan / Omzet';
include '../includes/topbar.php';
?>

<div class="content">
    <!-- Filter Bar -->
    <form method="GET" class="filter-bar">
        <div class="filter-group">
            <label>Periode</label>
            <select name="filter" onchange="toggleCustomDate(this)">
                <option value="all" <?= $filter_type == 'all' ? 'selected' : '' ?>>Semua Waktu</option>
                <option value="today" <?= $filter_type == 'today' ? 'selected' : '' ?>>Hari Ini</option>
                <option value="week" <?= $filter_type == 'week' ? 'selected' : '' ?>>7 Hari Terakhir</option>
                <option value="month" <?= $filter_type == 'month' ? 'selected' : '' ?>>Bulan Ini</option>
                <option value="year" <?= $filter_type == 'year' ? 'selected' : '' ?>>Tahun Ini</option>
                <option value="custom" <?= $filter_type == 'custom' ? 'selected' : '' ?>>Rentang Tanggal</option>
            </select>
        </div>
        <div class="filter-group" id="custom-start" style="display:<?= $filter_type == 'custom' ? 'flex' : 'none' ?>">
            <label>Dari Tanggal</label>
            <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
        </div>
        <div class="filter-group" id="custom-end" style="display:<?= $filter_type == 'custom' ? 'flex' : 'none' ?>">
            <label>Sampai Tanggal</label>
            <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
        </div>

        <div style="flex-grow: 1; min-width: 0;"></div>

        <div style="display: flex; gap: 6px; align-items: end; flex-wrap: wrap;">
            <button type="submit" class="filter-btn" style="background-color: var(--orange); border: none; font-family: 'Barlow', sans-serif; font-weight: 800; cursor: pointer; white-space: nowrap; padding: 10px 12px; font-size: 12px; gap: 6px;">
                <i class="fa-solid fa-magnifying-glass"></i> Cari
            </button>
            <a href="laporan_omzet.php" class="filter-btn secondary" style="text-decoration: none; white-space: nowrap; padding: 10px 12px; font-size: 12px; gap: 6px; display: inline-flex; align-items: center; background-color: var(--bg); border: 1px solid var(--border); color: var(--text);">
                <i class="fa-solid fa-rotate-right"></i> Reset
            </a>
            <div style="width: 1px; height: 32px; background-color: var(--border); margin: 0 4px;"></div>
            <a href="cetak_pdf_omzet.php?<?= $_SERVER['QUERY_STRING'] ?>" target="_blank" class="filter-btn" style="background-color: var(--red); text-decoration: none; white-space: nowrap; padding: 10px 12px; font-size: 12px; gap: 6px;">
                <i class="fa-solid fa-file-pdf"></i> Unduh PDF
            </a>
            <a href="cetak_excel_omzet.php?<?= $_SERVER['QUERY_STRING'] ?>" target="_blank" class="filter-btn" style="background-color: #10B981; text-decoration: none; white-space: nowrap; padding: 10px 12px; font-size: 12px; gap: 6px;">
                <i class="fa-solid fa-file-excel"></i> Unduh Excel
            </a>
        </div>
    </form>

    <!-- STAT GRID (4 kolom, sama seperti langganan) -->
    <div class="stat-grid">
        <div class="stat-card sc-orange">
            <div class="stat-header"><div class="stat-icon-wrap si-orange"><i class="fa-solid fa-money-bill-wave"></i></div></div>
            <div class="stat-value"><?= rupiahFormat($total_omzet_kotor) ?></div><div class="stat-label">Total Omzet Kotor</div>
        </div>
        <div class="stat-card sc-red">
            <div class="stat-header"><div class="stat-icon-wrap si-red"><i class="fa-solid fa-rotate-left"></i></div></div>
            <div class="stat-value"><?= rupiahFormat($total_refund) ?></div><div class="stat-label">Total Refund</div>
        </div>
        <div class="stat-card sc-green">
            <div class="stat-header"><div class="stat-icon-wrap si-green"><i class="fa-solid fa-wallet"></i></div></div>
            <div class="stat-value"><?= rupiahFormat($total_omzet_bersih) ?></div><div class="stat-label">Omzet Bersih</div>
        </div>
        <div class="stat-card sc-blue">
            <div class="stat-header"><div class="stat-icon-wrap si-blue"><i class="fa-solid fa-receipt"></i></div></div>
            <div class="stat-value"><?= $total_booking + $total_langganan + $total_beli ?></div><div class="stat-label">Total Transaksi</div>
        </div>
    </div>

    <!-- CHART -->
    <div class="card">
        <div class="card-header"><div class="card-title"><i class="fa-solid fa-chart-column"></i> Trend Omzet per Sumber</div></div>
        <div class="card-body"><div class="chart-container"><canvas id="omzetChart"></canvas></div></div>
    </div>

    <!-- BREAKDOWN -->
    <div class="card">
        <div class="card-header"><div class="card-title"><i class="fa-solid fa-calculator"></i> Rincian Omzet per Sumber</div></div>
        <div class="card-body">
            <div class="breakdown-item">
                <div class="breakdown-icon bi-green"><i class="fa-solid fa-calendar-check"></i></div>
                <div class="breakdown-info">
                    <div class="breakdown-name">Sewa Lapangan</div>
                    <div class="breakdown-count"><?= $total_booking ?> transaksi booking</div>
                </div>
                <div class="breakdown-value green"><?= rupiahFormat($omzet_booking) ?></div>
            </div>
            <div class="breakdown-item">
                <div class="breakdown-icon bi-blue"><i class="fa-solid fa-crown"></i></div>
                <div class="breakdown-info">
                    <div class="breakdown-name">Langganan Member</div>
                    <div class="breakdown-count"><?= $total_langganan ?> transaksi langganan</div>
                </div>
                <div class="breakdown-value"><?= rupiahFormat($omzet_langganan) ?></div>
            </div>
            <div class="breakdown-item">
                <div class="breakdown-icon bi-purple"><i class="fa-solid fa-basketball"></i></div>
                <div class="breakdown-info">
                    <div class="breakdown-name">Pembelian Alat</div>
                    <div class="breakdown-count"><?= $total_beli ?> transaksi pembelian</div>
                </div>
                <div class="breakdown-value"><?= rupiahFormat($omzet_beli_alat) ?></div>
            </div>
            <div class="breakdown-item" style="background: var(--orange-lt); border-color: rgba(255,69,0,.2);">
                <div class="breakdown-icon bi-orange"><i class="fa-solid fa-money-bill-wave"></i></div>
                <div class="breakdown-info">
                    <div class="breakdown-name">Total Omzet Kotor</div>
                    <div class="breakdown-count">Jumlah dari semua sumber</div>
                </div>
                <div class="breakdown-value" style="color:var(--orange);"><?= rupiahFormat($total_omzet_kotor) ?></div>
            </div>
            <div class="breakdown-item" style="background: var(--red-lt); border-color: rgba(239,68,68,.2);">
                <div class="breakdown-icon bi-red"><i class="fa-solid fa-rotate-left"></i></div>
                <div class="breakdown-info">
                    <div class="breakdown-name">Refund Pembatalan</div>
                    <div class="breakdown-count"><?= $total_batal_count ?> booking dibatalkan</div>
                </div>
                <div class="breakdown-value red">-<?= rupiahFormat($total_refund) ?></div>
            </div>
            <div class="breakdown-item" style="background: var(--green-lt); border-color: rgba(16,185,129,.2);">
                <div class="breakdown-icon bi-green"><i class="fa-solid fa-wallet"></i></div>
                <div class="breakdown-info">
                    <div class="breakdown-name">Omzet Bersih</div>
                    <div class="breakdown-count">Omzet Kotor - Refund</div>
                </div>
                <div class="breakdown-value green"><?= rupiahFormat($total_omzet_bersih) ?></div>
            </div>
        </div>
    </div>

    <!-- Ringkasan Keuangan -->
    <div class="card">
        <div class="card-header"><div class="card-title"><i class="fa-solid fa-calculator"></i> Ringkasan Keuangan</div></div>
        <div class="card-body">
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
                <div style="text-align: center; padding: 20px; background: var(--green-lt); border-radius: 12px; border: 1px solid rgba(16,185,129,.2);">
                    <div style="font-size: 11px; font-weight: 800; color: var(--muted); text-transform: uppercase; margin-bottom: 8px;">Total Omzet Kotor</div>
                    <div style="font-family: 'Barlow Condensed', sans-serif; font-size: 24px; font-weight: 900; color: var(--green);"><?= rupiahFormat($total_omzet_kotor) ?></div>
                </div>
                <div style="text-align: center; padding: 20px; background: var(--blue-lt); border-radius: 12px; border: 1px solid rgba(59,130,246,.2);">
                    <div style="font-size: 11px; font-weight: 800; color: var(--muted); text-transform: uppercase; margin-bottom: 8px;">Total Refund</div>
                    <div style="font-family: 'Barlow Condensed', sans-serif; font-size: 24px; font-weight: 900; color: var(--blue);"><?= rupiahFormat($total_refund) ?></div>
                </div>
                <div style="text-align: center; padding: 20px; background: var(--purple-lt); border-radius: 12px; border: 1px solid rgba(139,92,246,.2);">
                    <div style="font-size: 11px; font-weight: 800; color: var(--muted); text-transform: uppercase; margin-bottom: 8px;">Omzet Bersih</div>
                    <div style="font-family: 'Barlow Condensed', sans-serif; font-size: 24px; font-weight: 900; color: var(--purple);"><?= rupiahFormat($total_omzet_bersih) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Transaksi Terbaru -->
    <div class="dashboard-grid">
        <div class="card">
            <div class="card-header"><div class="card-title"><i class="fa-solid fa-calendar-check"></i> Booking Terbaru</div><a href="laporan_sewa_lapangan.php" class="card-badge" style="text-decoration:none;">Lihat Semua &rarr;</a></div>
            <div style="overflow-x:auto;">
                <table class="data-table">
                    <thead><tr><th>Nomor</th><th>Customer</th><th>Lapangan</th><th>Tanggal</th><th>Total</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php if(count($recent_bookings) > 0): ?>
                    <?php foreach($recent_bookings as $rb): 
                        list($status_lbl, $status_cls) = statusBookingLabel($rb['Status']);
                    ?>
                        <tr>
                            <td><div class="cell-name">#<?= $rb['ID_Booking'] ?></div></td>
                            <td><?= htmlspecialchars($rb['Nama_Customer'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($rb['Nama_Lapangan'] ?? '-') ?></td>
                            <td><?= $rb['Tanggal_Booking'] ? $rb['Tanggal_Booking']->format('d M Y') : '-' ?></td>
                            <td><div class="cell-price"><?= rupiahFormat($rb['Total_Bayar']) ?></div></td>
                            <td><span class="status-pill <?= $status_cls ?>"><?= $status_lbl ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="text-align:center; padding:30px; color:var(--muted);"><i class="fa-solid fa-inbox" style="font-size:24px; margin-bottom:8px; display:block;"></i>Belum ada data</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><div class="card-title"><i class="fa-solid fa-crown"></i> Langganan Terbaru</div><a href="laporan_langganan.php" class="card-badge" style="text-decoration:none;">Lihat Semua &rarr;</a></div>
            <div style="overflow-x:auto;">
                <table class="data-table">
                    <thead><tr><th>Nomor</th><th>Customer</th><th>Tipe</th><th>Mulai</th><th>Total</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php if(count($recent_langganan) > 0): ?>
                    <?php foreach($recent_langganan as $rl): 
                        list($status_lbl, $status_cls) = statusLanggananLabel($rl['Status']);
                    ?>
                        <tr>
                            <td><div class="cell-name">#<?= $rl['ID_Langganan'] ?></div></td>
                            <td><?= htmlspecialchars($rl['Nama_Customer'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($rl['Nama_Tipe'] ?? '-') ?> Member</td>
                            <td><?= $rl['Tanggal_Mulai'] ? $rl['Tanggal_Mulai']->format('d M Y') : '-' ?></td>
                            <td><div class="cell-price"><?= rupiahFormat($rl['Total_Bayar']) ?></div></td>
                            <td><span class="status-pill <?= $status_cls ?>"><?= $status_lbl ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="text-align:center; padding:30px; color:var(--muted);"><i class="fa-solid fa-inbox" style="font-size:24px; margin-bottom:8px; display:block;"></i>Belum ada data</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</main>

<script>
function toggleCustomDate(select) {
    const show = select.value === 'custom';
    document.getElementById('custom-start').style.display = show ? 'flex' : 'none';
    document.getElementById('custom-end').style.display = show ? 'flex' : 'none';
}

const ctx = document.getElementById('omzetChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?= json_encode($chart_labels) ?>,
        datasets: [
            {
                label: 'Sewa Lapangan (Bersih)',
                data: <?= json_encode($chart_booking) ?>,
                borderColor: '#10B981',
                backgroundColor: 'rgba(16, 185, 129, 0.08)',
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#10B981',
                pointBorderColor: '#FFFFFF',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 7,
            },
            {
                label: 'Langganan',
                data: <?= json_encode($chart_langganan) ?>,
                borderColor: '#3B82F6',
                backgroundColor: 'rgba(59, 130, 246, 0.05)',
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#3B82F6',
                pointBorderColor: '#FFFFFF',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 7,
            },
            {
                label: 'Pembelian Alat',
                data: <?= json_encode($chart_beli) ?>,
                borderColor: '#8B5CF6',
                backgroundColor: 'rgba(139, 92, 246, 0.05)',
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#8B5CF6',
                pointBorderColor: '#FFFFFF',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 7,
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { 
                position: 'top', 
                align: 'end',
                labels: { 
                    font: { family: 'Barlow', size: 12, weight: '600' }, 
                    usePointStyle: true,
                    boxWidth: 8,
                    padding: 20 
                } 
            },
            tooltip: {
                backgroundColor: '#1F2937',
                titleColor: '#fff',
                bodyColor: '#fff',
                padding: 12,
                cornerRadius: 8,
                displayColors: true,
                callbacks: {
                    label: function(context) {
                        return context.dataset.label + ': Rp ' + context.parsed.y.toLocaleString('id-ID');
                    }
                }
            }
        },
        scales: {
            y: { 
                beginAtZero: true, 
                grid: { color: 'rgba(0,0,0,0.05)', drawBorder: false }, 
                ticks: { 
                    font: { family: 'Barlow', size: 11 }, 
                    color: '#6B7280', 
                    callback: function(value) { 
                        return 'Rp ' + (value/1000000).toFixed(1) + 'jt'; 
                    } 
                } 
            },
            x: { grid: { display: false }, ticks: { font: { family: 'Barlow', size: 11 }, color: '#6B7280' } }
        }
    }
});

window.Swal = Swal.mixin({
    scrollbarPadding: false
});
</script>
    <?php if (function_exists('tampilkan_sensor_auto_logout')) tampilkan_sensor_auto_logout(); ?>
</body>
</html>