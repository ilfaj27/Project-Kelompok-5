<?php
session_start();
include '../includes/config.php';

if (!isset($_SESSION['login']) || $_SESSION['role'] !== 'pemilik') {
    header("Location: ../login/login.php");
    exit();
}

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
$current_page = 'laporan_sewa_lapangan';

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
$lapangan_filter = $_GET['lapangan'] ?? 'all';
$status_filter = $_GET['status'] ?? 'all';

$where_clauses = ["b.Is_Deleted = 0"];
$params = [];

// Date filter
if ($filter_type === 'today') {
    $where_clauses[] = "CAST(b.Tanggal_Booking AS DATE) = CAST(GETDATE() AS DATE)";
} elseif ($filter_type === 'week') {
    $where_clauses[] = "b.Tanggal_Booking >= DATEADD(day, -7, CAST(GETDATE() AS DATE))";
} elseif ($filter_type === 'month') {
    $where_clauses[] = "MONTH(b.Tanggal_Booking) = MONTH(GETDATE()) AND YEAR(b.Tanggal_Booking) = YEAR(GETDATE())";
} elseif ($filter_type === 'year') {
    $where_clauses[] = "YEAR(b.Tanggal_Booking) = YEAR(GETDATE())";
} elseif ($filter_type === 'custom' && !empty($start_date) && !empty($end_date)) {
    $where_clauses[] = "b.Tanggal_Booking BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
}

// Lapangan filter
if ($lapangan_filter !== 'all') {
    $where_clauses[] = "l.ID_Lapangan = ?";
    $params[] = $lapangan_filter;
}

// Status filter
if ($status_filter !== 'all') {
    $where_clauses[] = "b.Status = ?";
    $params[] = $status_filter;
}

$where_sql = implode(" AND ", $where_clauses);

// ============================================
// STATISTIK
// ============================================
$total_booking = 0;
$total_berhasil = 0;
$total_selesai = 0;
$total_batal = 0;
$total_menunggu = 0;
$total_omzet = 0;
$total_refund = 0;

$stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN b.Status = 1 THEN 1 ELSE 0 END) as berhasil,
    SUM(CASE WHEN b.Status = 2 THEN 1 ELSE 0 END) as selesai,
    SUM(CASE WHEN b.Status = 3 THEN 1 ELSE 0 END) as batal,
    SUM(CASE WHEN b.Status = 0 THEN 1 ELSE 0 END) as menunggu,
    SUM(CASE WHEN b.Status IN (1,2) THEN b.Total_Bayar ELSE 0 END) as omzet,
    SUM(CASE WHEN b.Status = 3 THEN ISNULL(pb.Nominal_Refund, 0) ELSE 0 END) as refund
FROM Booking b
LEFT JOIN Pembatalan_Booking pb ON b.ID_Booking = pb.ID_Booking
LEFT JOIN Jadwal j ON b.ID_Jadwal = j.ID_Jadwal
LEFT JOIN Lapangan l ON j.ID_Lapangan = l.ID_Lapangan
WHERE " . $where_sql;

$q = safeQuery($conn, $stats_sql, $params);
$d = safeFetch($q);
if ($d) {
    $total_booking = $d['total'] ?? 0;
    $total_berhasil = $d['berhasil'] ?? 0;
    $total_selesai = $d['selesai'] ?? 0;
    $total_batal = $d['batal'] ?? 0;
    $total_menunggu = $d['menunggu'] ?? 0;
    $total_omzet = $d['omzet'] ?? 0;
    $total_refund = $d['refund'] ?? 0;
}

$omzet_bersih = $total_omzet - $total_refund;

// ============================================
// DATA BOOKING
// ============================================
$bookings = [];
$booking_sql = "SELECT 
    b.ID_Booking,
    b.Tanggal_Booking,
    b.Metode_Pembayaran,
    b.Total_Bayar,
    b.Status,
    c.Nama_Customer,
    k.Nama_Karyawan as Nama_Karyawan_Konfirm,
    l.Nama_Lapangan,
    l.Harga_Sewa,
    j.Tanggal as Tanggal_Main,
    j.Jam_Mulai,
    j.Jam_Selesai,
    p.Nama_Promo,
    p.Diskon as Diskon_Promo,
    tm.Nama_Tipe,
    tm.Potongan_Harga as Potongan_Member,
    pb.Nominal_Refund,
    pb.Biaya_Batal
FROM Booking b
LEFT JOIN Customer c ON b.ID_Customer = c.ID_Customer
LEFT JOIN Karyawan k ON b.ID_Karyawan = k.ID_Karyawan
LEFT JOIN Jadwal j ON b.ID_Jadwal = j.ID_Jadwal
LEFT JOIN Lapangan l ON j.ID_Lapangan = l.ID_Lapangan
LEFT JOIN Promo p ON b.ID_Promo = p.ID_Promo
LEFT JOIN Langganan lg ON (lg.ID_Customer = b.ID_Customer AND lg.Status = 1 
    AND b.Tanggal_Booking BETWEEN lg.Tanggal_Mulai AND lg.Tanggal_Selesai)
LEFT JOIN Tipe_Member tm ON lg.ID_Tipe = tm.ID_Tipe
LEFT JOIN Pembatalan_Booking pb ON b.ID_Booking = pb.ID_Booking
WHERE " . $where_sql . "
ORDER BY b.Tanggal_Booking DESC, b.ID_Booking DESC";

$q = safeQuery($conn, $booking_sql, $params);
if ($q !== null) {
    while ($row = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)) {
        $bookings[] = $row;
    }
}

// ============================================
// LAPANGAN POPULER
// ============================================
$lapangan_populer = [];
$pop_sql = "SELECT 
    l.Nama_Lapangan,
    COUNT(*) as jumlah_booking,
    SUM(CASE WHEN b.Status IN (1,2) THEN b.Total_Bayar ELSE 0 END) as total_omzet
FROM Booking b
LEFT JOIN Jadwal j ON b.ID_Jadwal = j.ID_Jadwal
LEFT JOIN Lapangan l ON j.ID_Lapangan = l.ID_Lapangan
WHERE " . $where_sql . "
GROUP BY l.ID_Lapangan, l.Nama_Lapangan
ORDER BY jumlah_booking DESC";

$q = safeQuery($conn, $pop_sql, $params);
if ($q !== null) {
    while ($row = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)) {
        $lapangan_populer[] = $row;
    }
}

// ============================================
// DAFTAR LAPANGAN UNTUK FILTER
// ============================================
$daftar_lapangan = [];
$q = safeQuery($conn, "SELECT ID_Lapangan, Nama_Lapangan FROM Lapangan WHERE Is_Deleted = 0 ORDER BY ID_Lapangan");
if ($q !== null) {
    while ($row = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)) {
        $daftar_lapangan[] = $row;
    }
}

// ============================================
// CHART DATA: Booking per Bulan
// ============================================
$chart_labels = [];
$chart_data_berhasil = [];
$chart_data_batal = [];
$chart_data_menunggu = [];

$chart_sql = "SELECT 
    MONTH(b.Tanggal_Booking) as bulan,
    YEAR(b.Tanggal_Booking) as tahun,
    SUM(CASE WHEN b.Status = 1 THEN 1 ELSE 0 END) as berhasil,
    SUM(CASE WHEN b.Status = 2 THEN 1 ELSE 0 END) as selesai,
    SUM(CASE WHEN b.Status = 3 THEN 1 ELSE 0 END) as batal,
    SUM(CASE WHEN b.Status = 0 THEN 1 ELSE 0 END) as menunggu
FROM Booking b
LEFT JOIN Jadwal j ON b.ID_Jadwal = j.ID_Jadwal
LEFT JOIN Lapangan l ON j.ID_Lapangan = l.ID_Lapangan
WHERE " . $where_sql . "
GROUP BY MONTH(b.Tanggal_Booking), YEAR(b.Tanggal_Booking)
ORDER BY YEAR(b.Tanggal_Booking), MONTH(b.Tanggal_Booking)";

$q = safeQuery($conn, $chart_sql, $params);
if ($q !== null) {
    $monthNames = ['','Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    while ($row = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)) {
        $chart_labels[] = $monthNames[$row['bulan']] . ' ' . $row['tahun'];
        $chart_data_berhasil[] = ($row['berhasil'] ?? 0) + ($row['selesai'] ?? 0);
        $chart_data_batal[] = $row['batal'] ?? 0;
        $chart_data_menunggu[] = $row['menunggu'] ?? 0;
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
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Laporan Sewa Lapangan | HoopBall</title>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800;900&family=Barlow:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
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

/* MAIN & TOPBAR */
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

/* CONTENT */
.content { padding: 32px 40px; flex: 1; }

/* FILTER BAR */
.filter-bar { background: var(--card-bg); border-radius: 16px; border: 1px solid var(--border); padding: 20px 24px; margin-bottom: 24px; display: flex; flex-wrap: wrap; gap: 14px; align-items: end; }
.filter-group { display: flex; flex-direction: column; gap: 6px; }
.filter-group label { font-size: 11px; font-weight: 800; color: var(--muted); text-transform: uppercase; letter-spacing: .5px; }
.filter-group select, .filter-group input { padding: 10px 14px; border: 1px solid var(--border); border-radius: 10px; font-family: 'Barlow', sans-serif; font-size: 13px; font-weight: 600; color: var(--text); background: var(--bg); min-width: 160px; outline: none; transition: .2s; }
.filter-group select:focus, .filter-group input:focus { border-color: var(--orange); box-shadow: 0 0 0 3px var(--orange-lt); }
.filter-btn { padding: 10px 20px; background: var(--orange); color: #fff; border: none; border-radius: 10px; font-family: 'Barlow', sans-serif; font-size: 13px; font-weight: 800; cursor: pointer; transition: .2s; display: flex; align-items: center; gap: 8px; }
.filter-btn:hover { background: var(--orange-dk); transform: translateY(-1px); }
.filter-btn.secondary { background: var(--bg); color: var(--text); border: 1px solid var(--border); }
.filter-btn.secondary:hover { background: var(--border-lt); }

/* STAT GRID */
.stat-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 16px; margin-bottom: 24px; }
@media(max-width:1200px){ .stat-grid { grid-template-columns: repeat(3, 1fr); } }
@media(max-width:768px){ .stat-grid { grid-template-columns: repeat(2, 1fr); } }
.stat-card { background: var(--card-bg); border-radius: 16px; padding: 20px 22px; border: 1px solid var(--border); position: relative; overflow: hidden; transition: all .2s ease; }
.stat-card:hover { transform: translateY(-3px); box-shadow: 0 12px 32px rgba(0,0,0,.08); }
.stat-card::before { content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; border-radius: 4px 0 0 4px; }
.sc-blue::before { background: var(--blue); }
.sc-green::before { background: var(--green); }
.sc-orange::before { background: var(--orange); }
.sc-purple::before { background: var(--purple); }
.sc-red::before { background: var(--red); }
.sc-yellow::before { background: var(--yellow); }
.stat-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; }
.stat-icon-wrap { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 16px; }
.si-blue { background: var(--blue-lt); color: var(--blue); }
.si-green { background: var(--green-lt); color: var(--green); }
.si-orange { background: var(--orange-lt); color: var(--orange); }
.si-purple { background: var(--purple-lt); color: var(--purple); }
.si-red { background: var(--red-lt); color: var(--red); }
.si-yellow { background: var(--yellow-lt); color: var(--yellow); }
.stat-value { font-family: 'Barlow Condensed', sans-serif; font-size: 28px; font-weight: 900; color: var(--text); line-height: 1; margin-bottom: 4px; }
.stat-label { font-size: 11px; color: var(--muted); font-weight: 700; text-transform: uppercase; letter-spacing: .5px; }

/* CARD */
.card { background: var(--card-bg); border-radius: 16px; border: 1px solid var(--border); overflow: hidden; transition: all .2s ease; margin-bottom: 24px; }
.card:hover { box-shadow: 0 8px 24px rgba(0,0,0,.06); }
.card-header { padding: 20px 24px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
.card-title { font-size: 15px; font-weight: 800; color: var(--text); display: flex; align-items: center; gap: 8px; }
.card-title i { color: var(--orange); font-size: 14px; }
.card-badge { background: var(--orange-lt); color: var(--orange); font-size: 11px; font-weight: 800; padding: 4px 10px; border-radius: 20px; }
.card-body { padding: 20px 24px; }

/* TABLE */
.data-table { width: 100%; border-collapse: collapse; }
.data-table th { padding: 12px 14px; font-size: 10px; font-weight: 800; color: var(--muted); text-transform: uppercase; letter-spacing: .6px; border-bottom: 2px solid var(--border-lt); text-align: left; background: var(--bg); }
.data-table td { padding: 14px 14px; font-size: 13px; border-bottom: 1px solid var(--border-lt); vertical-align: middle; }
.data-table tr:last-child td { border-bottom: none; }
.data-table tbody tr { transition: background .15s; }
.data-table tbody tr:hover td { background: #FAFAFA; }

.cell-name { font-weight: 700; color: var(--text); }
.cell-detail { font-size: 11px; color: var(--muted); font-weight: 600; margin-top: 2px; }
.cell-price { font-weight: 800; color: var(--text); }
.cell-price.discount { color: var(--green); }
.cell-price.refund { color: var(--red); }

.status-pill { padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .3px; display: inline-block; }
.sp-active { background: var(--green-lt); color: var(--green); }
.sp-done { background: var(--blue-lt); color: var(--blue); }
.sp-inactive { background: var(--red-lt); color: var(--red); }
.sp-pending { background: var(--yellow-lt); color: #D97706; }

/* CHART */
.chart-container { position: relative; height: 320px; }

/* GRID LAYOUT */
.dashboard-grid { display: grid; grid-template-columns: 1fr 340px; gap: 22px; }
@media(max-width:1100px){ .dashboard-grid { grid-template-columns: 1fr; } }

/* POPULAR ITEM */
.popular-item { display: flex; align-items: center; justify-content: space-between; padding: 14px; background: var(--bg); border-radius: 10px; margin-bottom: 10px; border: 1px solid var(--border); transition: .2s; }
.popular-item:hover { border-color: var(--orange); background: var(--orange-lt); }
.popular-item:last-child { margin-bottom: 0; }
.popular-rank { width: 32px; height: 32px; background: var(--orange); color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 13px; flex-shrink: 0; }
.popular-info { flex: 1; margin-left: 12px; }
.popular-name { font-size: 13px; font-weight: 700; color: var(--text); }
.popular-count { font-size: 11px; color: var(--muted); margin-top: 2px; }
.popular-omzet { font-size: 14px; font-weight: 800; color: var(--orange); }

/* PRINT */
@media print {
    .sidebar, .topbar, .filter-bar, .sb-bottom { display: none !important; }
    .main { margin-left: 0 !important; }
    .content { padding: 20px !important; }
    .card { break-inside: avoid; page-break-inside: avoid; }
}

html, body { scrollbar-width: none; -ms-overflow-style: none; }
html::-webkit-scrollbar, body::-webkit-scrollbar { display: none; }
/* CLOCK */
#clock-display { display: flex; align-items: center; gap: 16px; }
.clock-time { 
    font-family: 'Barlow Condensed', sans-serif; 
    font-size: 26px; 
    font-weight: 900; 
    color: var(--orange); 
    display: flex; 
    align-items: center; 
    gap: 6px; 
    line-height: 1; 
}
.clock-colon { color: var(--orange); opacity: .5; animation: blink 1s infinite; }
@keyframes blink { 0%, 100% { opacity: .5; } 50% { opacity: 1; } }
.clock-divider { width: 1.5px; height: 28px; background-color: var(--border); }
.clock-date { 
    font-family: 'Barlow', sans-serif; 
    font-size: 13px; 
    font-weight: 700; 
    color: var(--muted); 
    text-transform: uppercase; 
    letter-spacing: 0.5px; 
}



.topbar-user { background-color: #FFFFFF !important; }

.topbar-btn:hover, .topbar-user:hover {
    background-color: #E5E7EB !important;
    border-color: #D1D5DB !important;
    color: #4B5563 !important;
}

.topbar-btn:active, .topbar-user:active {
    background-color: #D1D5DB !important;
    border-color: #9CA3AF !important;
    color: #1F2937 !important;
}
</style>
</head>
<body>

<?php include '../includes/sidebar.php'; ?>

<main class="main">
<?php
// ============================================================
// SET TOPBAR VARIABLES & INCLUDE UNIFIED TOPBAR
// ============================================================
$topbar_title = 'Laporan Sewa Lapangan';
$topbar_breadcrumb = 'Laporan / Sewa Lapangan';
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
        <div class="filter-group">
            <label>Lapangan</label>
            <select name="lapangan">
                <option value="all" <?= $lapangan_filter == 'all' ? 'selected' : '' ?>>Semua Lapangan</option>
                <?php foreach($daftar_lapangan as $lap): ?>
                <option value="<?= $lap['ID_Lapangan'] ?>" <?= $lapangan_filter == $lap['ID_Lapangan'] ? 'selected' : '' ?>><?= htmlspecialchars($lap['Nama_Lapangan']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label>Status</label>
            <select name="status">
                <option value="all" <?= $status_filter == 'all' ? 'selected' : '' ?>>Semua Status</option>
                <option value="0" <?= $status_filter == '0' ? 'selected' : '' ?>>Menunggu</option>
                <option value="1" <?= $status_filter == '1' ? 'selected' : '' ?>>Berhasil</option>
                <option value="2" <?= $status_filter == '2' ? 'selected' : '' ?>>Selesai</option>
                <option value="3" <?= $status_filter == '3' ? 'selected' : '' ?>>Dibatalkan</option>
            </select>
        </div>
        <button type="button" class="filter-btn secondary" onclick="window.print()" title="Cetak Laporan" style="margin-left:auto;"><i class="fa-solid fa-print"></i> Cetak</button>
        <button type="submit" class="filter-btn"><i class="fa-solid fa-filter"></i> Terapkan</button>
        <a href="laporan_sewa_lapangan.php" class="filter-btn secondary"><i class="fa-solid fa-rotate-right"></i> Reset</a>
    </form>

    <!-- Statistik -->
    <div class="stat-grid">
        <div class="stat-card sc-blue">
            <div class="stat-header"><div class="stat-icon-wrap si-blue"><i class="fa-solid fa-calendar-check"></i></div></div>
            <div class="stat-value"><?= $total_booking ?></div><div class="stat-label">Total Booking</div>
        </div>
        <div class="stat-card sc-green">
            <div class="stat-header"><div class="stat-icon-wrap si-green"><i class="fa-solid fa-check-circle"></i></div></div>
            <div class="stat-value"><?= $total_berhasil + $total_selesai ?></div><div class="stat-label">Berhasil/Selesai</div>
        </div>
        <div class="stat-card sc-yellow">
            <div class="stat-header"><div class="stat-icon-wrap si-yellow"><i class="fa-solid fa-clock"></i></div></div>
            <div class="stat-value"><?= $total_menunggu ?></div><div class="stat-label">Menunggu</div>
        </div>
        <div class="stat-card sc-red">
            <div class="stat-header"><div class="stat-icon-wrap si-red"><i class="fa-solid fa-ban"></i></div></div>
            <div class="stat-value"><?= $total_batal ?></div><div class="stat-label">Dibatalkan</div>
        </div>
        <div class="stat-card sc-purple">
            <div class="stat-header"><div class="stat-icon-wrap si-purple"><i class="fa-solid fa-money-bill-wave"></i></div></div>
            <div class="stat-value" style="font-size:22px;"><?= rupiahFormat($total_omzet) ?></div><div class="stat-label">Total Omzet</div>
        </div>
    </div>

    <!-- Chart & Populer -->
    <div class="dashboard-grid">
        <div class="card">
            <div class="card-header"><div class="card-title"><i class="fa-solid fa-chart-column"></i> Trend Booking per Periode</div></div>
            <div class="card-body"><div class="chart-container"><canvas id="bookingChart"></canvas></div></div>
        </div>
        <div class="card">
            <div class="card-header"><div class="card-title"><i class="fa-solid fa-trophy"></i> Lapangan Populer</div></div>
            <div class="card-body">
                <?php if(count($lapangan_populer) > 0): ?>
                <?php $rank = 1; foreach($lapangan_populer as $lp): ?>
                <div class="popular-item">
                    <div class="popular-rank"><?= $rank++ ?></div>
                    <div class="popular-info">
                        <div class="popular-name"><?= htmlspecialchars($lp['Nama_Lapangan']) ?></div>
                        <div class="popular-count"><?= $lp['jumlah_booking'] ?> booking</div>
                    </div>
                    <div class="popular-omzet"><?= rupiahFormat($lp['total_omzet']) ?></div>
                </div>
                <?php endforeach; ?>
                <?php else: ?>
                <div style="text-align:center; padding:30px; color:var(--muted);">
                    <i class="fa-solid fa-inbox" style="font-size:32px; margin-bottom:10px; opacity:.5; display:block;"></i>
                    <div style="font-size:13px; font-weight:700;">Belum ada data</div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Tabel Detail Booking -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fa-solid fa-list"></i> Detail Transaksi Sewa Lapangan</div>
            <span class="card-badge"><?= count($bookings) ?> transaksi</span>
        </div>
        <div style="overflow-x:auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Nomor</th>
                        <th>Customer</th>
                        <th>Lapangan</th>
                        <th>Jadwal Main</th>
                        <th>Tanggal Booking</th>
                        <th>Metode Bayar</th>
                        <th>Diskon</th>
                        <th>Total Bayar</th>
                        <th>Status</th>
                        <th>Karyawan</th>
                    </tr>
                </thead>
                <tbody>
                <?php if(count($bookings) > 0): ?>
                <?php foreach($bookings as $b): 
                    list($status_lbl, $status_cls) = statusBookingLabel($b['Status']);
                    $diskon_info = '';
                    if (!empty($b['Nama_Tipe'])) {
                        $diskon_info = 'Member ' . htmlspecialchars($b['Nama_Tipe']) . ' (-' . rupiahFormat($b['Potongan_Member']) . ')';
                    } elseif (!empty($b['Nama_Promo'])) {
                        $diskon_info = htmlspecialchars($b['Nama_Promo']) . ' (-' . rupiahFormat($b['Diskon_Promo']) . ')';
                    } else {
                        $diskon_info = '-';
                    }
                ?>
                    <tr>
                        <td><div class="cell-name">#<?= $b['ID_Booking'] ?></div></td>
                        <td><div class="cell-name"><?= htmlspecialchars($b['Nama_Customer']) ?></div></td>
                        <td>
                            <div class="cell-name"><?= htmlspecialchars($b['Nama_Lapangan']) ?></div>
                            <div class="cell-detail"><?= rupiahFormat($b['Harga_Sewa']) ?>/jam</div>
                        </td>
                        <td>
                            <div class="cell-name"><?= $b['Tanggal_Main'] ? $b['Tanggal_Main']->format('d M Y') : '-' ?></div>
                            <div class="cell-detail"><?= $b['Jam_Mulai'] ? $b['Jam_Mulai']->format('H:i') : '-' ?> - <?= $b['Jam_Selesai'] ? $b['Jam_Selesai']->format('H:i') : '-' ?></div>
                        </td>
                        <td><?= $b['Tanggal_Booking'] ? $b['Tanggal_Booking']->format('d M Y') : '-' ?></td>
                        <td><?= htmlspecialchars($b['Metode_Pembayaran']) ?></td>
                        <td><div class="cell-detail"><?= $diskon_info ?></div></td>
                        <td>
                            <div class="cell-price"><?= rupiahFormat($b['Total_Bayar']) ?></div>
                            <?php if($b['Status'] == 3 && $b['Nominal_Refund'] > 0): ?>
                            <div class="cell-detail" style="color:var(--red);">Refund: <?= rupiahFormat($b['Nominal_Refund']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><span class="status-pill <?= $status_cls ?>"><?= $status_lbl ?></span></td>
                        <td><div class="cell-name"><?= htmlspecialchars($b['Nama_Karyawan_Konfirm'] ?? '-') ?></div></td>
                    </tr>
                <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="10" style="text-align:center; padding:40px; color:var(--muted);">
                        <i class="fa-solid fa-inbox" style="font-size:40px; margin-bottom:12px; opacity:.5; display:block;"></i>
                        <div style="font-size:14px; font-weight:700;">Tidak ada data booking untuk filter yang dipilih</div>
                    </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Ringkasan Omzet -->
    <div class="card">
        <div class="card-header"><div class="card-title"><i class="fa-solid fa-calculator"></i> Ringkasan Keuangan</div></div>
        <div class="card-body">
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px;">
                <div style="text-align: center; padding: 20px; background: var(--green-lt); border-radius: 12px; border: 1px solid rgba(16,185,129,.2);">
                    <div style="font-size: 11px; font-weight: 800; color: var(--muted); text-transform: uppercase; margin-bottom: 8px;">Omzet Booking</div>
                    <div style="font-family: 'Barlow Condensed', sans-serif; font-size: 24px; font-weight: 900; color: var(--green);"><?= rupiahFormat($total_omzet) ?></div>
                </div>
                <div style="text-align: center; padding: 20px; background: var(--red-lt); border-radius: 12px; border: 1px solid rgba(239,68,68,.2);">
                    <div style="font-size: 11px; font-weight: 800; color: var(--muted); text-transform: uppercase; margin-bottom: 8px;">Total Refund</div>
                    <div style="font-family: 'Barlow Condensed', sans-serif; font-size: 24px; font-weight: 900; color: var(--red);"><?= rupiahFormat($total_refund) ?></div>
                </div>
                <div style="text-align: center; padding: 20px; background: var(--blue-lt); border-radius: 12px; border: 1px solid rgba(59,130,246,.2);">
                    <div style="font-size: 11px; font-weight: 800; color: var(--muted); text-transform: uppercase; margin-bottom: 8px;">Omzet Bersih</div>
                    <div style="font-family: 'Barlow Condensed', sans-serif; font-size: 24px; font-weight: 900; color: var(--blue);"><?= rupiahFormat($omzet_bersih) ?></div>
                </div>
                <div style="text-align: center; padding: 20px; background: var(--purple-lt); border-radius: 12px; border: 1px solid rgba(139,92,246,.2);">
                    <div style="font-size: 11px; font-weight: 800; color: var(--muted); text-transform: uppercase; margin-bottom: 8px;">Rata-rata/Booking</div>
                    <div style="font-family: 'Barlow Condensed', sans-serif; font-size: 24px; font-weight: 900; color: var(--purple);"><?= $total_booking > 0 ? rupiahFormat($total_omzet / $total_booking) : rupiahFormat(0) ?></div>
                </div>
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

const ctx = document.getElementById('bookingChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?= json_encode($chart_labels) ?>,
        datasets: [
            {
                label: 'Berhasil/Selesai',
                data: <?= json_encode($chart_data_berhasil) ?>,
                backgroundColor: 'rgba(16, 185, 129, 0.8)',
                borderColor: '#10B981',
                borderWidth: 2,
                borderRadius: 6,
            },
            {
                label: 'Dibatalkan',
                data: <?= json_encode($chart_data_batal) ?>,
                backgroundColor: 'rgba(239, 68, 68, 0.8)',
                borderColor: '#EF4444',
                borderWidth: 2,
                borderRadius: 6,
            },
            {
                label: 'Menunggu',
                data: <?= json_encode($chart_data_menunggu) ?>,
                backgroundColor: 'rgba(245, 158, 11, 0.8)',
                borderColor: '#F59E0B',
                borderWidth: 2,
                borderRadius: 6,
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'top', labels: { font: { family: 'Barlow', size: 12 }, usePointStyle: true, padding: 20 } },
            tooltip: {
                backgroundColor: '#1F2937',
                titleColor: '#fff',
                bodyColor: '#fff',
                padding: 12,
                cornerRadius: 8,
                displayColors: true,
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