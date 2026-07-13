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
$alat_filter = $_GET['alat'] ?? 'all';
$status_filter = $_GET['status'] ?? 'all';

$where_clauses = ["1=1"];
$params = [];

// Date filter
if ($filter_type === 'today') {
    $where_clauses[] = "CAST(ba.Tanggal_Beli AS DATE) = CAST(GETDATE() AS DATE)";
} elseif ($filter_type === 'week') {
    $where_clauses[] = "ba.Tanggal_Beli >= DATEADD(day, -7, CAST(GETDATE() AS DATE))";
} elseif ($filter_type === 'month') {
    $where_clauses[] = "MONTH(ba.Tanggal_Beli) = MONTH(GETDATE()) AND YEAR(ba.Tanggal_Beli) = YEAR(GETDATE())";
} elseif ($filter_type === 'year') {
    $where_clauses[] = "YEAR(ba.Tanggal_Beli) = YEAR(GETDATE())";
} elseif ($filter_type === 'custom' && !empty($start_date) && !empty($end_date)) {
    $where_clauses[] = "ba.Tanggal_Beli BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
}

// Alat filter
if ($alat_filter !== 'all') {
    $where_clauses[] = "dba.ID_Alat = ?";
    $params[] = $alat_filter;
}

// Status filter
if ($status_filter !== 'all') {
    $where_clauses[] = "ba.Status = ?";
    $params[] = $status_filter;
}

$where_sql = implode(" AND ", $where_clauses);

// ============================================
// STATISTIK
// ============================================
$total_transaksi = 0;
$total_berhasil = 0;
$total_pending = 0;
$total_pendapatan = 0;
$total_item = 0;

$stats_sql = "SELECT 
    COUNT(DISTINCT ba.ID_Beli) as total_transaksi,
    SUM(CASE WHEN ba.Status = 1 THEN 1 ELSE 0 END) as berhasil,
    SUM(CASE WHEN ba.Status = 0 THEN 1 ELSE 0 END) as pending,
    SUM(ba.Total_Bayar) as pendapatan,
    SUM(dba.Jumlah) as total_item
FROM Beli_Alat ba
LEFT JOIN Detail_Beli_Alat dba ON ba.ID_Beli = dba.ID_Beli
WHERE " . $where_sql;

$q = safeQuery($conn, $stats_sql, $params);
$d = safeFetch($q);
if ($d) {
    $total_transaksi = $d['total_transaksi'] ?? 0;
    $total_berhasil = $d['berhasil'] ?? 0;
    $total_pending = $d['pending'] ?? 0;
    $total_pendapatan = $d['pendapatan'] ?? 0;
    $total_item = $d['total_item'] ?? 0;
}

// ============================================
// DATA PEMBELIAN
// ============================================
$pembelian_list = [];
$beli_sql = "SELECT 
    ba.ID_Beli,
    ba.Tanggal_Beli,
    ba.Metode_Pembayaran,
    ba.Total_Bayar,
    ba.Status,
    c.Nama_Customer,
    c.Email,
    k.Nama_Karyawan as Nama_Karyawan_Konfirm
FROM Beli_Alat ba
LEFT JOIN Customer c ON ba.ID_Customer = c.ID_Customer
LEFT JOIN Karyawan k ON ba.ID_Karyawan = k.ID_Karyawan
WHERE " . $where_sql . "
ORDER BY ba.Tanggal_Beli DESC, ba.ID_Beli DESC";

$q = safeQuery($conn, $beli_sql, $params);
if ($q !== null) {
    while ($row = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)) {
        $pembelian_list[] = $row;
    }
}

// ============================================
// DETAIL ITEM PER TRANSAKSI
// ============================================
$detail_items = [];
$detail_sql = "SELECT 
    dba.ID_Beli,
    a.Nama_Alat,
    a.Harga_Alat,
    dba.Jumlah,
    dba.SubTotal
FROM Detail_Beli_Alat dba
LEFT JOIN Alat a ON dba.ID_Alat = a.ID_Alat
LEFT JOIN Beli_Alat ba ON dba.ID_Beli = ba.ID_Beli
WHERE " . $where_sql . "
ORDER BY dba.ID_Beli, a.Nama_Alat";

$q = safeQuery($conn, $detail_sql, $params);
if ($q !== null) {
    while ($row = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)) {
        $detail_items[$row['ID_Beli']][] = $row;
    }
}

// ============================================
// ALAT POPULER
// ============================================
$alat_populer = [];
$pop_sql = "SELECT 
    a.Nama_Alat,
    a.Harga_Alat,
    SUM(dba.Jumlah) as jumlah_terjual,
    SUM(dba.SubTotal) as total_pendapatan,
    a.Stok as stok_tersedia
FROM Detail_Beli_Alat dba
LEFT JOIN Alat a ON dba.ID_Alat = a.ID_Alat
LEFT JOIN Beli_Alat ba ON dba.ID_Beli = ba.ID_Beli
WHERE " . $where_sql . "
GROUP BY a.ID_Alat, a.Nama_Alat, a.Harga_Alat, a.Stok
ORDER BY jumlah_terjual DESC";

$q = safeQuery($conn, $pop_sql, $params);
if ($q !== null) {
    while ($row = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)) {
        $alat_populer[] = $row;
    }
}

// ============================================
// DAFTAR ALAT UNTUK FILTER
// ============================================
$daftar_alat = [];
$q = safeQuery($conn, "SELECT ID_Alat, Nama_Alat, Harga_Alat FROM Alat WHERE Is_Deleted = 0 ORDER BY ID_Alat");
if ($q !== null) {
    while ($row = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)) {
        $daftar_alat[] = $row;
    }
}

// ============================================
// CHART DATA: Pembelian per Bulan
// ============================================
$chart_labels = [];
$chart_data = [];
$chart_item = [];

$chart_sql = "SELECT 
    MONTH(ba.Tanggal_Beli) as bulan,
    YEAR(ba.Tanggal_Beli) as tahun,
    COUNT(DISTINCT ba.ID_Beli) as jumlah_transaksi,
    SUM(dba.Jumlah) as jumlah_item,
    SUM(ba.Total_Bayar) as total_pendapatan
FROM Beli_Alat ba
LEFT JOIN Detail_Beli_Alat dba ON ba.ID_Beli = dba.ID_Beli
WHERE " . $where_sql . "
GROUP BY MONTH(ba.Tanggal_Beli), YEAR(ba.Tanggal_Beli)
ORDER BY YEAR(ba.Tanggal_Beli), MONTH(ba.Tanggal_Beli)";

$q = safeQuery($conn, $chart_sql, $params);
if ($q !== null) {
    $monthNames = ['','Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    while ($row = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)) {
        $chart_labels[] = $monthNames[$row['bulan']] . ' ' . $row['tahun'];
        $chart_data[] = $row['total_pendapatan'] ?? 0;
        $chart_item[] = $row['jumlah_item'] ?? 0;
    }
}

function statusBeliLabel($status) {
    return $status == 1 ? ['Berhasil', 'sp-active'] : ['Menunggu', 'sp-pending'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Laporan Pembelian Alat | HoopBall</title>
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
.stat-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
@media(max-width:1200px){ .stat-grid { grid-template-columns: repeat(2, 1fr); } }
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

.status-pill { padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .3px; display: inline-block; }
.sp-active { background: var(--green-lt); color: var(--green); }
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

/* ITEM LIST */
.item-list { display: flex; flex-wrap: wrap; gap: 6px; }
.item-tag { background: var(--orange-lt); color: var(--orange); padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }

/* PRINT */
@media print {
    .sidebar, .topbar, .filter-bar, .sb-bottom { display: none !important; }
    .main { margin-left: 0 !important; }
    .content { padding: 20px !important; }
    .card { break-inside: avoid; page-break-inside: avoid; }
}

html, body { scrollbar-width: none; -ms-overflow-style: none; }
html::-webkit-scrollbar, body::-webkit-scrollbar { display: none; }
</style>
</head>
<body>

<?php
$current_page = 'laporan_pembelian_alat';
$sidebar_folder = 'laporan';
include '../includes/sidebar.php';
?>
<main class="main">
<header class="topbar">
    <div class="topbar-left">
        <div class="topbar-title">Laporan Pembelian Alat</div>
        <div class="topbar-breadcrumb">Laporan / Pembelian Alat</div>
    </div>
    <div class="topbar-right">
        <button class="topbar-btn" onclick="window.print()" title="Cetak Laporan"><i class="fa-solid fa-print"></i></button>
        <div class="dropdown-wrap">
            <div class="topbar-user">
                <div class="t-avatar">
                    <?php if ($profile_photo): ?>
                        <img src="<?= $profile_photo ?>" alt="Profile">
                    <?php else: ?>
                        <i class="fa-solid fa-user"></i>
                    <?php endif; ?>
                </div>
                <div><div class="t-name"><?= strtoupper(htmlspecialchars($nama)) ?></div><div class="t-role">MANAJER</div></div>
                <i class="fa-solid fa-chevron-down t-chevron"></i>
            </div>
            <div class="dropdown-menu">
                <a href="../profile/profile_pemilik.php" class="dd-item"><i class="fa-solid fa-id-badge"></i> Profil Saya</a>
                <hr class="dd-divider">
                <a href="../login/logout.php" class="dd-item" style="color:var(--red);"><i class="fa-solid fa-right-from-bracket"></i> Keluar</a>
            </div>
        </div>
    </div>
</header>

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
            <label>Alat</label>
            <select name="alat">
                <option value="all" <?= $alat_filter == 'all' ? 'selected' : '' ?>>Semua Alat</option>
                <?php foreach($daftar_alat as $a): ?>
                <option value="<?= $a['ID_Alat'] ?>" <?= $alat_filter == $a['ID_Alat'] ? 'selected' : '' ?>><?= htmlspecialchars($a['Nama_Alat']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label>Status</label>
            <select name="status">
                <option value="all" <?= $status_filter == 'all' ? 'selected' : '' ?>>Semua Status</option>
                <option value="0" <?= $status_filter == '0' ? 'selected' : '' ?>>Menunggu</option>
                <option value="1" <?= $status_filter == '1' ? 'selected' : '' ?>>Berhasil</option>
            </select>
        </div>
        <button type="submit" class="filter-btn"><i class="fa-solid fa-filter"></i> Terapkan</button>
        <a href="laporan_pembelian_alat.php" class="filter-btn secondary"><i class="fa-solid fa-rotate-right"></i> Reset</a>
    </form>

    <!-- Statistik -->
    <div class="stat-grid">
        <div class="stat-card sc-blue">
            <div class="stat-header"><div class="stat-icon-wrap si-blue"><i class="fa-solid fa-cart-shopping"></i></div></div>
            <div class="stat-value"><?= $total_transaksi ?></div><div class="stat-label">Total Transaksi</div>
        </div>
        <div class="stat-card sc-green">
            <div class="stat-header"><div class="stat-icon-wrap si-green"><i class="fa-solid fa-check-circle"></i></div></div>
            <div class="stat-value"><?= $total_berhasil ?></div><div class="stat-label">Berhasil</div>
        </div>
        <div class="stat-card sc-orange">
            <div class="stat-header"><div class="stat-icon-wrap si-orange"><i class="fa-solid fa-boxes-stacked"></i></div></div>
            <div class="stat-value"><?= $total_item ?></div><div class="stat-label">Item Terjual</div>
        </div>
        <div class="stat-card sc-purple">
            <div class="stat-header"><div class="stat-icon-wrap si-purple"><i class="fa-solid fa-money-bill-wave"></i></div></div>
            <div class="stat-value" style="font-size:22px;"><?= rupiahFormat($total_pendapatan) ?></div><div class="stat-label">Total Pendapatan</div>
        </div>
    </div>

    <!-- Chart & Populer -->
    <div class="dashboard-grid">
        <div class="card">
            <div class="card-header"><div class="card-title"><i class="fa-solid fa-chart-column"></i> Trend Pembelian per Periode</div></div>
            <div class="card-body"><div class="chart-container"><canvas id="beliChart"></canvas></div></div>
        </div>
        <div class="card">
            <div class="card-header"><div class="card-title"><i class="fa-solid fa-trophy"></i> Alat Terlaris</div></div>
            <div class="card-body">
                <?php if(count($alat_populer) > 0): ?>
                <?php $rank = 1; foreach($alat_populer as $ap): ?>
                <div class="popular-item">
                    <div class="popular-rank"><?= $rank++ ?></div>
                    <div class="popular-info">
                        <div class="popular-name"><?= htmlspecialchars($ap['Nama_Alat']) ?></div>
                        <div class="popular-count"><?= $ap['jumlah_terjual'] ?> terjual | Stok: <?= $ap['stok_tersedia'] ?> unit</div>
                    </div>
                    <div class="popular-omzet"><?= rupiahFormat($ap['total_pendapatan']) ?></div>
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

    <!-- Tabel Detail Pembelian -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fa-solid fa-list"></i> Detail Transaksi Pembelian Alat</div>
            <span class="card-badge"><?= count($pembelian_list) ?> transaksi</span>
        </div>
        <div style="overflow-x:auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Nomor</th>
                        <th>Customer</th>
                        <th>Tanggal Beli</th>
                        <th>Item Dibeli</th>
                        <th>Metode Bayar</th>
                        <th>Total Bayar</th>
                        <th>Status</th>
                        <th>Karyawan</th>
                    </tr>
                </thead>
                <tbody>
                <?php if(count($pembelian_list) > 0): ?>
                <?php foreach($pembelian_list as $ba): 
                    list($status_lbl, $status_cls) = statusBeliLabel($ba['Status']);
                    $items = $detail_items[$ba['ID_Beli']] ?? [];
                ?>
                    <tr>
                        <td><div class="cell-name">#<?= $ba['ID_Beli'] ?></div></td>
                        <td>
                            <div class="cell-name"><?= htmlspecialchars($ba['Nama_Customer']) ?></div>
                            <div class="cell-detail"><?= htmlspecialchars($ba['Email'] ?? '') ?></div>
                        </td>
                        <td><?= $ba['Tanggal_Beli'] ? $ba['Tanggal_Beli']->format('d M Y') : '-' ?></td>
                        <td>
                            <?php if(count($items) > 0): ?>
                            <div class="item-list">
                                <?php foreach($items as $it): ?>
                                <span class="item-tag"><?= htmlspecialchars($it['Nama_Alat']) ?> x<?= $it['Jumlah'] ?></span>
                                <?php endforeach; ?>
                            </div>
                            <?php else: ?>
                            <div class="cell-detail">-</div>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($ba['Metode_Pembayaran']) ?></td>
                        <td><div class="cell-price"><?= rupiahFormat($ba['Total_Bayar']) ?></div></td>
                        <td><span class="status-pill <?= $status_cls ?>"><?= $status_lbl ?></span></td>
                        <td><div class="cell-name"><?= htmlspecialchars($ba['Nama_Karyawan_Konfirm'] ?? '-') ?></div></td>
                    </tr>
                <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="8" style="text-align:center; padding:40px; color:var(--muted);">
                        <i class="fa-solid fa-inbox" style="font-size:40px; margin-bottom:12px; opacity:.5; display:block;"></i>
                        <div style="font-size:14px; font-weight:700;">Tidak ada data pembelian untuk filter yang dipilih</div>
                    </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Ringkasan Keuangan -->
    <div class="card">
        <div class="card-header"><div class="card-title"><i class="fa-solid fa-calculator"></i> Ringkasan Keuangan Pembelian Alat</div></div>
        <div class="card-body">
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
                <div style="text-align: center; padding: 20px; background: var(--green-lt); border-radius: 12px; border: 1px solid rgba(16,185,129,.2);">
                    <div style="font-size: 11px; font-weight: 800; color: var(--muted); text-transform: uppercase; margin-bottom: 8px;">Total Pendapatan</div>
                    <div style="font-family: 'Barlow Condensed', sans-serif; font-size: 24px; font-weight: 900; color: var(--green);"><?= rupiahFormat($total_pendapatan) ?></div>
                </div>
                <div style="text-align: center; padding: 20px; background: var(--blue-lt); border-radius: 12px; border: 1px solid rgba(59,130,246,.2);">
                    <div style="font-size: 11px; font-weight: 800; color: var(--muted); text-transform: uppercase; margin-bottom: 8px;">Rata-rata per Transaksi</div>
                    <div style="font-family: 'Barlow Condensed', sans-serif; font-size: 24px; font-weight: 900; color: var(--blue);"><?= $total_transaksi > 0 ? rupiahFormat($total_pendapatan / $total_transaksi) : rupiahFormat(0) ?></div>
                </div>
                <div style="text-align: center; padding: 20px; background: var(--purple-lt); border-radius: 12px; border: 1px solid rgba(139,92,246,.2);">
                    <div style="font-size: 11px; font-weight: 800; color: var(--muted); text-transform: uppercase; margin-bottom: 8px;">Item Terjual</div>
                    <div style="font-family: 'Barlow Condensed', sans-serif; font-size: 24px; font-weight: 900; color: var(--purple);"><?= $total_item ?> unit</div>
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

const ctx = document.getElementById('beliChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?= json_encode($chart_labels) ?>,
        datasets: [
            {
                label: 'Pendapatan',
                data: <?= json_encode($chart_data) ?>,
                backgroundColor: 'rgba(255, 69, 0, 0.8)',
                borderColor: '#FF4500',
                borderWidth: 2,
                borderRadius: 6,
                yAxisID: 'y'
            },
            {
                label: 'Item Terjual',
                data: <?= json_encode($chart_item) ?>,
                backgroundColor: 'rgba(59, 130, 246, 0.8)',
                borderColor: '#3B82F6',
                borderWidth: 2,
                borderRadius: 6,
                yAxisID: 'y1'
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
                callbacks: {
                    label: function(context) {
                        if (context.datasetIndex === 0) {
                            return 'Pendapatan: Rp ' + context.parsed.y.toLocaleString('id-ID');
                        }
                        return 'Item: ' + context.parsed.y + ' unit';
                    }
                }
            }
        },
        scales: {
            y: { 
                type: 'linear', 
                display: true, 
                position: 'left',
                beginAtZero: true, 
                grid: { color: 'rgba(0,0,0,0.05)', drawBorder: false }, 
                ticks: { font: { family: 'Barlow', size: 11 }, color: '#6B7280', callback: function(value) { return 'Rp ' + (value/1000000).toFixed(1) + 'jt'; } } 
            },
            y1: { 
                type: 'linear', 
                display: true, 
                position: 'right',
                beginAtZero: true, 
                grid: { drawOnChartArea: false }, 
                ticks: { font: { family: 'Barlow', size: 11 }, color: '#6B7280' } 
            },
            x: { grid: { display: false }, ticks: { font: { family: 'Barlow', size: 11 }, color: '#6B7280' } }
        }
    }
});
</script>
</body>
</html>