<?php
require_once '../login/auth_check.php';
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
    $where_clauses[] = "EXISTS (SELECT 1 FROM Detail_Beli_Alat dba_f WHERE dba_f.ID_Beli = ba.ID_Beli AND dba_f.ID_Alat = ?)";
    $params[] = $alat_filter;
}

$where_clauses_base = $where_clauses;
$params_base = $params;

if ($status_filter !== 'all') {
    $where_clauses[] = "ba.Status = ?";
    $params[] = $status_filter;
}

$where_sql = implode(" AND ", $where_clauses);
$where_sql_profit = implode(" AND ", array_merge($where_clauses_base, ["ba.Status = 1"]));
$params_profit = $params_base;

// ============================================
// STATISTIK
// ============================================
$total_transaksi = 0;
$total_berhasil = 0;
$total_pending = 0;
$total_ditolak = 0;
$total_pendapatan = 0;
$total_item = 0;
$total_profit = 0;

$stats_header_sql = "SELECT 
    COUNT(*) as total_transaksi,
    SUM(CASE WHEN ba.Status = 1 THEN 1 ELSE 0 END) as berhasil,
    SUM(CASE WHEN ba.Status = 0 THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN ba.Status = 2 THEN 1 ELSE 0 END) as ditolak,
    SUM(ba.Total_Bayar) as pendapatan
FROM Beli_Alat ba
WHERE " . $where_sql;

$q = safeQuery($conn, $stats_header_sql, $params);
$d = safeFetch($q);
if ($d) {
    $total_transaksi = $d['total_transaksi'] ?? 0;
    $total_berhasil = $d['berhasil'] ?? 0;
    $total_pending = $d['pending'] ?? 0;
    $total_ditolak = $d['ditolak'] ?? 0;
    $total_pendapatan = $d['pendapatan'] ?? 0;
}

$stats_item_sql = "SELECT 
    SUM(dba.Jumlah) as total_item
FROM Beli_Alat ba
INNER JOIN Detail_Beli_Alat dba ON ba.ID_Beli = dba.ID_Beli
WHERE " . $where_sql;

$q = safeQuery($conn, $stats_item_sql, $params);
$d = safeFetch($q);
if ($d) {
    $total_item = $d['total_item'] ?? 0;
}

$profit_sql = "SELECT SUM((a.Harga_Jual - a.Harga_Beli) * dba.Jumlah) as total_profit
FROM Beli_Alat ba
INNER JOIN Detail_Beli_Alat dba ON ba.ID_Beli = dba.ID_Beli
INNER JOIN Alat a ON dba.ID_Alat = a.ID_Alat
WHERE " . $where_sql_profit;

$q = safeQuery($conn, $profit_sql, $params_profit);
$d = safeFetch($q);
if ($d) {
    $total_profit = $d['total_profit'] ?? 0;
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
    a.Harga_Jual,
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
    a.Harga_Jual,
    SUM(dba.Jumlah) as jumlah_terjual,
    SUM(dba.SubTotal) as total_pendapatan,
    a.Stok as stok_tersedia
FROM Detail_Beli_Alat dba
LEFT JOIN Alat a ON dba.ID_Alat = a.ID_Alat
LEFT JOIN Beli_Alat ba ON dba.ID_Beli = ba.ID_Beli
WHERE " . $where_sql . "
GROUP BY a.ID_Alat, a.Nama_Alat, a.Harga_Jual, a.Stok
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
$q = safeQuery($conn, "SELECT ID_Alat, Nama_Alat, Harga_Jual FROM Alat WHERE Is_Deleted = 0 ORDER BY ID_Alat");
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

$chart_map = [];
$monthNames = ['','Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];

$chart_header_sql = "SELECT 
    MONTH(ba.Tanggal_Beli) as bulan,
    YEAR(ba.Tanggal_Beli) as tahun,
    SUM(ba.Total_Bayar) as total_pendapatan
FROM Beli_Alat ba
WHERE " . $where_sql . "
GROUP BY MONTH(ba.Tanggal_Beli), YEAR(ba.Tanggal_Beli)
ORDER BY YEAR(ba.Tanggal_Beli), MONTH(ba.Tanggal_Beli)";

$q = safeQuery($conn, $chart_header_sql, $params);
if ($q !== null) {
    while ($row = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)) {
        $key = (int)$row['tahun'] * 100 + (int)$row['bulan'];
        $chart_map[$key] = [
            'label' => $monthNames[$row['bulan']] . ' ' . $row['tahun'],
            'pendapatan' => $row['total_pendapatan'] ?? 0,
            'item' => 0,
        ];
    }
}

$chart_item_sql = "SELECT 
    MONTH(ba.Tanggal_Beli) as bulan,
    YEAR(ba.Tanggal_Beli) as tahun,
    SUM(dba.Jumlah) as jumlah_item
FROM Beli_Alat ba
INNER JOIN Detail_Beli_Alat dba ON ba.ID_Beli = dba.ID_Beli
WHERE " . $where_sql . "
GROUP BY MONTH(ba.Tanggal_Beli), YEAR(ba.Tanggal_Beli)";

$q = safeQuery($conn, $chart_item_sql, $params);
if ($q !== null) {
    while ($row = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)) {
        $key = (int)$row['tahun'] * 100 + (int)$row['bulan'];
        if (isset($chart_map[$key])) {
            $chart_map[$key]['item'] = $row['jumlah_item'] ?? 0;
        }
    }
}

ksort($chart_map);
foreach ($chart_map as $row) {
    $chart_labels[] = $row['label'];
    $chart_data[] = $row['pendapatan'];
    $chart_item[] = $row['item'];
}

function statusBeliLabel($status) {
    switch ((int)$status) {
        case 1: return ['Berhasil', 'sp-active'];
        case 2: return ['Ditolak', 'sp-rejected'];
        default: return ['Menunggu', 'sp-pending'];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<?php include '../includes/favicon.php'; ?>
<title>Laporan Pembelian Alat | HoopBall</title>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800;900&family=Barlow:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../asset/css/responsive_tipe_member.css?v=<?= time() ?>">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
.stat-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; }
.stat-icon-wrap { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 16px; }
.si-blue { background: var(--blue-lt); color: var(--blue); }
.si-green { background: var(--green-lt); color: var(--green); }
.si-orange { background: var(--orange-lt); color: var(--orange); }
.si-purple { background: var(--purple-lt); color: var(--purple); }
.si-red { background: var(--red-lt); color: var(--red); }
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
.data-table { width: 100%; border-collapse: collapse; table-layout: auto; }
.data-table th { padding: 12px 14px; font-size: 10px; font-weight: 800; color: var(--muted); text-transform: uppercase; letter-spacing: .6px; border-bottom: 2px solid var(--border-lt); text-align: left; background: var(--bg); white-space: nowrap; }
.data-table td { padding: 14px 14px; font-size: 13px; border-bottom: 1px solid var(--border-lt); vertical-align: middle; }
.data-table tr:last-child td { border-bottom: none; }
.data-table tbody tr { transition: background .15s; }
.data-table tbody tr:hover td { background: #FAFAFA; }
.cell-name { font-weight: 700; color: var(--text); }
.cell-detail { font-size: 11px; color: var(--muted); font-weight: 600; margin-top: 2px; }
.cell-price { font-weight: 800; color: var(--text); white-space: nowrap; }

.status-pill { padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .3px; display: inline-block; }
.sp-active { background: var(--green-lt); color: var(--green); }
.sp-pending { background: var(--yellow-lt); color: #D97706; }
.sp-rejected { background: var(--red-lt); color: var(--red); }

/* CHART */
.chart-container { position: relative; height: 320px; }

/* GRID LAYOUT */
.dashboard-grid { display: grid; grid-template-columns: 1fr; gap: 22px; }

/* POPULAR ITEM */
.popular-list { max-height: 400px; overflow-y: auto; padding-right: 6px; margin-right: -6px; }
.popular-list::-webkit-scrollbar { width: 6px; }
.popular-list::-webkit-scrollbar-thumb { background: var(--border); border-radius: 10px; }
.popular-list::-webkit-scrollbar-thumb:hover { background: #C7CBD1; }
.popular-list::-webkit-scrollbar-track { background: transparent; }
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

/* ALIGNMENT UTILITIES */
.text-center { text-align: center !important; }
.text-left { text-align: left !important; }
.text-right { text-align: right !important; }

/* PRINT HEADER */
.print-header { display: none; }
.print-header-inner { display: flex; align-items: center; justify-content: space-between; padding-bottom: 16px; margin-bottom: 20px; border-bottom: 2px solid var(--border); }
.print-logo { display: flex; align-items: center; gap: 12px; }
.print-logo-icon { width: 44px; height: 44px; background: var(--orange); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 20px; flex-shrink: 0; }
.print-logo-text { font-family: 'Barlow Condensed', sans-serif; font-size: 22px; font-weight: 900; color: var(--text); letter-spacing: -.3px; line-height: 1; }
.print-logo-text span { color: var(--orange); }
.print-logo-sub { font-size: 10px; color: var(--muted); font-weight: 700; text-transform: uppercase; letter-spacing: .6px; margin-top: 2px; }
.print-meta { text-align: right; }
.print-title { font-family: 'Barlow Condensed', sans-serif; font-size: 18px; font-weight: 800; color: var(--text); }
.print-date { font-size: 11px; color: var(--muted); font-weight: 600; margin-top: 2px; }

/* PRINT */
@media print {
    .sidebar, .topbar, .filter-bar, .sb-bottom { display: none !important; }
    .main { margin-left: 0 !important; }
    .content { padding: 20px !important; }
    .card { break-inside: avoid; page-break-inside: avoid; }
    .print-header { display: block !important; }
    .popular-list { max-height: none !important; overflow: visible !important; padding-right: 0 !important; margin-right: 0 !important; }
    .stat-grid { grid-template-columns: repeat(3, 1fr) !important; }
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

/* ============================================
   MATIKAN SEMUA ANIMASI SWEETALERT2 
   ============================================ */
.swal2-popup {
    animation: none !important;
    transition: none !important;
}
.swal2-icon {
    animation: none !important;
}
.swal2-icon.swal2-success .swal2-success-ring,
.swal2-icon.swal2-success [class^="swal2-success-line"],
.swal2-icon.swal2-error [class^="swal2-x-mark-line"],
.swal2-icon.swal2-warning {
    animation: none !important;
}

html.swal2-shown,
body.swal2-shown,
body.swal2-height-auto {
    padding-right: 0 !important;
}

/* === Swal Popup Konsisten === */
.swal-popup-konsisten {
    border-radius: 16px !important;
    font-family: 'Barlow', sans-serif !important;
}
.swal-popup-konsisten .swal2-title {
    font-size: 18px !important;
    font-weight: 800 !important;
    color: #111827 !important;
}
.swal-popup-konsisten .swal2-html-container {
    font-size: 14px !important;
    font-weight: 600 !important;
    color: #374151 !important;
}
.swal-popup-konsisten .swal2-confirm,
.swal-popup-konsisten .swal2-cancel {
    font-family: 'Barlow', sans-serif !important;
    font-weight: 700 !important;
    font-size: 13px !important;
    border-radius: 10px !important;
    padding: 10px 20px !important;
}
</style>
</head>
<body>

<?php
$current_page = 'laporan_pembelian_alat';
$sidebar_folder = 'laporan';
include '../includes/sidebar.php';
?>
<main class="main">
<?php
$topbar_title = 'Laporan Pembelian Alat';
$topbar_breadcrumb = 'Laporan / Pembelian Alat';
include '../includes/topbar.php';
?>

<div class="content">
    <!-- Print Header -->
    <div class="print-header">
        <div class="print-header-inner">
            <div class="print-logo">
                <div class="print-logo-icon"><i class="fa-solid fa-basketball"></i></div>
                <div>
                    <div class="print-logo-text">HOOP<span>BALL</span></div>
                    <div class="print-logo-sub">Sistem Manajemen</div>
                </div>
            </div>
            <div class="print-meta">
                <div class="print-title">Laporan Pembelian Alat</div>
                <div class="print-date"><?= date('d M Y, H:i') ?> WIB</div>
            </div>
        </div>
    </div>

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
                <option value="2" <?= $status_filter == '2' ? 'selected' : '' ?>>Ditolak</option>
            </select>
        </div>

        <!-- Spacer -->
        <div style="flex-grow: 1; min-width: 0;"></div>

        <!-- Action Buttons -->
        <div style="display: flex; gap: 6px; align-items: end; flex-wrap: wrap;">
            <button type="submit" class="filter-btn" style="white-space: nowrap; padding: 10px 12px; font-size: 12px; gap: 6px;">
                <i class="fa-solid fa-magnifying-glass"></i> Cari
            </button>
            <a href="laporan_pembelian_alat.php" class="filter-btn secondary" style="text-decoration: none; white-space: nowrap; padding: 10px 12px; font-size: 12px; gap: 6px; display: inline-flex; align-items: center;">
                <i class="fa-solid fa-rotate-right"></i> Reset
            </a>
            <div style="width: 1px; height: 32px; background-color: var(--border); margin: 0 4px;"></div>
            <button type="button" onclick="confirmDownload('pdf')" class="filter-btn" style="background-color: var(--red); border: none; font-family: 'Barlow', sans-serif; font-weight: 800; cursor: pointer; white-space: nowrap; padding: 10px 12px; font-size: 12px; gap: 6px;">
                <i class="fa-solid fa-file-pdf"></i> Unduh PDF
            </button>
            <button type="button" onclick="confirmDownload('excel')" class="filter-btn" style="background-color: #10B981; border: none; font-family: 'Barlow', sans-serif; font-weight: 800; cursor: pointer; white-space: nowrap; padding: 10px 12px; font-size: 12px; gap: 6px;">
                <i class="fa-solid fa-file-excel"></i> Unduh Excel
            </button>
        </div>
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
        <div class="stat-card sc-red">
            <div class="stat-header"><div class="stat-icon-wrap si-red"><i class="fa-solid fa-circle-xmark"></i></div></div>
            <div class="stat-value"><?= $total_ditolak ?></div><div class="stat-label">Ditolak</div>
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
    <div class="dashboard-grid" style="grid-template-columns: 2fr 1fr;">
        <div class="card" style="margin-bottom: 0;">
            <div class="card-header"><div class="card-title"><i class="fa-solid fa-chart-column"></i> Trend Pembelian per Periode</div></div>
            <div class="card-body"><div class="chart-container"><canvas id="beliChart"></canvas></div></div>
        </div>
        <div class="card" style="margin-bottom: 0;">
            <div class="card-header"><div class="card-title"><i class="fa-solid fa-trophy" style="color:var(--orange);"></i> Alat Terlaris</div></div>
            <div class="card-body" style="padding-top: 14px; max-height: 385px; overflow-y: auto;">
                <?php if(count($alat_populer) > 0): ?>
                <div class="popular-list">
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
                </div>
                <?php else: ?>
                <div style="text-align:center; padding:30px 0; color:var(--muted); font-size:13px; font-weight:600;">
                    Belum ada data pada periode ini
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <br>

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
                        <th class="text-center" style="width: 80px;">ID</th>
                        <th class="text-left" style="width: 200px;">Customer</th>
                        <th class="text-center" style="width: 120px;">Tanggal</th>
                        <th class="text-left" style="width: 220px;">Item Dibeli</th>
                        <th class="text-left" style="width: 140px;">Metode Bayar</th>
                        <th class="text-right" style="width: 140px;">Total Bayar</th>
                        <th class="text-center" style="width: 110px;">Status</th>
                        <th class="text-left" style="width: 160px;">Karyawan</th>
                    </tr>
                </thead>
                <tbody>
                <?php if(count($pembelian_list) > 0): ?>
                <?php foreach($pembelian_list as $ba): 
                    list($status_lbl, $status_cls) = statusBeliLabel($ba['Status']);
                    $items = $detail_items[$ba['ID_Beli']] ?? [];
                ?>
                    <tr>
                        <td class="text-center"><div class="cell-name">#<?= $ba['ID_Beli'] ?></div></td>
                        <td>
                            <div class="cell-name"><?= htmlspecialchars($ba['Nama_Customer']) ?></div>
                            <div class="cell-detail"><?= htmlspecialchars($ba['Email'] ?? '') ?></div>
                        </td>
                        <td class="text-center"><?= $ba['Tanggal_Beli'] ? $ba['Tanggal_Beli']->format('d M Y') : '-' ?></td>
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
                        <td class="text-right"><div class="cell-price"><?= rupiahFormat($ba['Total_Bayar']) ?></div></td>
                        <td class="text-center"><span class="status-pill <?= $status_cls ?>"><?= $status_lbl ?></span></td>
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
                    <div style="font-size: 11px; font-weight: 800; color: var(--muted); text-transform: uppercase; margin-bottom: 8px;">Total Profit</div>
                    <div style="font-family: 'Barlow Condensed', sans-serif; font-size: 24px; font-weight: 900; color: var(--blue);"><?= rupiahFormat($total_profit) ?></div>
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

const ctx = document.getElementById('beliChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?= json_encode($chart_labels) ?>,
        datasets: [
            {
                label: 'Pendapatan',
                data: <?= json_encode($chart_data) ?>,
                borderColor: '#FF4500',
                backgroundColor: 'rgba(255, 69, 0, 0.1)',
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#FF4500',
                pointBorderColor: '#FFFFFF',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 7,
                yAxisID: 'y'
            },
            {
                label: 'Item Terjual',
                data: <?= json_encode($chart_item) ?>,
                borderColor: '#3B82F6',
                backgroundColor: 'rgba(59, 130, 246, 0.05)',
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#3B82F6',
                pointBorderColor: '#FFFFFF',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 7,
                yAxisID: 'y1'
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
                ticks: { 
                    font: { family: 'Barlow', size: 11 }, 
                    color: '#6B7280', 
                    callback: function(value) { 
                        return 'Rp ' + (value/1000000).toFixed(1) + 'jt'; 
                    } 
                } 
            },
            y1: { 
                type: 'linear', 
                display: true, 
                position: 'right',
                beginAtZero: true, 
                grid: { drawOnChartArea: false },
                ticks: { 
                    font: { family: 'Barlow', size: 11 }, 
                    color: '#6B7280',
                    callback: function(value) {
                        if (Math.floor(value) === value) {
                            return value + ' unit';
                        }
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

// ============================================
// POPUP KONFIRMASI DOWNLOAD LAPORAN
// ============================================
function confirmDownload(type) {
    const isPdf = type === 'pdf';
    const title = isPdf ? 'Unduh Laporan PDF' : 'Unduh Laporan Excel';
    const text = isPdf 
        ? 'Laporan Pembelian Alat akan diunduh dalam format PDF. Lanjutkan?' 
        : 'Laporan Pembelian Alat akan diunduh dalam format Excel. Lanjutkan?';
    const confirmColor = isPdf ? '#EF4444' : '#10B981';
    const url = isPdf 
        ? 'cetak_pdf_pembelian.php?<?= $_SERVER['QUERY_STRING'] ?>' 
        : 'cetak_excel_pembelian.php?<?= $_SERVER['QUERY_STRING'] ?>';

    Swal.fire({
        title: title,
        text: text,
        icon: 'question',
        iconColor: confirmColor,
        showCancelButton: true,
        confirmButtonText: 'Ya, Unduh',
        cancelButtonText: 'Batal',
        confirmButtonColor: confirmColor,
        cancelButtonColor: '#6B7280',
        allowOutsideClick: false,
        allowEscapeKey: false,
        scrollbarPadding: false,
        customClass: {
            popup: 'swal-popup-konsisten'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            window.open(url, '_blank');
        }
    });
}
</script>
    <?php if (function_exists('tampilkan_sensor_auto_logout')) tampilkan_sensor_auto_logout(); ?>
</body>
</html>