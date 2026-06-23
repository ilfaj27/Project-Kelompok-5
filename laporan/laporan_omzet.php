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

$where_booking = ["1=1"];
$where_langganan = ["1=1"];
$where_beli = ["1=1"];
$params_b = [];
$params_l = [];
$params_ba = [];

// Date filter
if ($filter_type === 'today') {
    $where_booking[] = "CAST(b.Tanggal_Booking AS DATE) = CAST(GETDATE() AS DATE)";
    $where_langganan[] = "CAST(lg.Tanggal_Mulai AS DATE) = CAST(GETDATE() AS DATE)";
    $where_beli[] = "CAST(ba.Tanggal_Beli AS DATE) = CAST(GETDATE() AS DATE)";
} elseif ($filter_type === 'week') {
    $where_booking[] = "b.Tanggal_Booking >= DATEADD(day, -7, CAST(GETDATE() AS DATE))";
    $where_langganan[] = "lg.Tanggal_Mulai >= DATEADD(day, -7, CAST(GETDATE() AS DATE))";
    $where_beli[] = "ba.Tanggal_Beli >= DATEADD(day, -7, CAST(GETDATE() AS DATE))";
} elseif ($filter_type === 'month') {
    $where_booking[] = "MONTH(b.Tanggal_Booking) = MONTH(GETDATE()) AND YEAR(b.Tanggal_Booking) = YEAR(GETDATE())";
    $where_langganan[] = "MONTH(lg.Tanggal_Mulai) = MONTH(GETDATE()) AND YEAR(lg.Tanggal_Mulai) = YEAR(GETDATE())";
    $where_beli[] = "MONTH(ba.Tanggal_Beli) = MONTH(GETDATE()) AND YEAR(ba.Tanggal_Beli) = YEAR(GETDATE())";
} elseif ($filter_type === 'year') {
    $where_booking[] = "YEAR(b.Tanggal_Booking) = YEAR(GETDATE())";
    $where_langganan[] = "YEAR(lg.Tanggal_Mulai) = YEAR(GETDATE())";
    $where_beli[] = "YEAR(ba.Tanggal_Beli) = YEAR(GETDATE())";
} elseif ($filter_type === 'custom' && !empty($start_date) && !empty($end_date)) {
    $where_booking[] = "b.Tanggal_Booking BETWEEN ? AND ?";
    $where_langganan[] = "lg.Tanggal_Mulai BETWEEN ? AND ?";
    $where_beli[] = "ba.Tanggal_Beli BETWEEN ? AND ?";
    $params_b[] = $start_date; $params_b[] = $end_date;
    $params_l[] = $start_date; $params_l[] = $end_date;
    $params_ba[] = $start_date; $params_ba[] = $end_date;
}

$where_booking_sql = implode(" AND ", $where_booking);
$where_langganan_sql = implode(" AND ", $where_langganan);
$where_beli_sql = implode(" AND ", $where_beli);

// ============================================
// STATISTIK OMZET
// ============================================
$omzet_booking = 0;
$omzet_langganan = 0;
$omzet_beli_alat = 0;
$total_refund = 0;
$total_batal = 0;

// Omzet Booking (Status 1=Berhasil, 2=Selesai)
$q = safeQuery($conn, "SELECT ISNULL(SUM(Total_Bayar), 0) as total FROM Booking b WHERE b.Status IN (1,2) AND " . $where_booking_sql, $params_b);
$d = safeFetch($q);
if ($d) $omzet_booking = $d['total'] ?? 0;

// Total Refund dari Pembatalan
$q = safeQuery($conn, "SELECT ISNULL(SUM(pb.Nominal_Refund), 0) as total FROM Pembatalan_Booking pb 
    LEFT JOIN Booking b ON pb.ID_Booking = b.ID_Booking WHERE " . $where_booking_sql, $params_b);
$d = safeFetch($q);
if ($d) $total_refund = $d['total'] ?? 0;

// Total Biaya Batal (50% yang tetap jadi omzet)
$q = safeQuery($conn, "SELECT ISNULL(SUM(pb.Biaya_Batal), 0) as total FROM Pembatalan_Booking pb 
    LEFT JOIN Booking b ON pb.ID_Booking = b.ID_Booking WHERE " . $where_booking_sql, $params_b);
$d = safeFetch($q);
if ($d) $total_batal = $d['total'] ?? 0;

// Omzet Langganan
$q = safeQuery($conn, "SELECT ISNULL(SUM(Total_Bayar), 0) as total FROM Langganan lg WHERE " . $where_langganan_sql, $params_l);
$d = safeFetch($q);
if ($d) $omzet_langganan = $d['total'] ?? 0;

// Omzet Beli Alat
$q = safeQuery($conn, "SELECT ISNULL(SUM(Total_Bayar), 0) as total FROM Beli_Alat ba WHERE ba.Status = 1 AND " . $where_beli_sql, $params_ba);
$d = safeFetch($q);
if ($d) $omzet_beli_alat = $d['total'] ?? 0;

$total_omzet_kotor = $omzet_booking + $omzet_langganan + $omzet_beli_alat;
$total_omzet_bersih = $total_omzet_kotor - $total_refund;

// ============================================
// STATISTIK JUMLAH TRANSAKSI
// ============================================
$total_booking = 0;
$total_langganan = 0;
$total_beli = 0;
$total_batal_count = 0;

$q = safeQuery($conn, "SELECT COUNT(*) as total FROM Booking b WHERE " . $where_booking_sql, $params_b);
$d = safeFetch($q);
if ($d) $total_booking = $d['total'] ?? 0;

$q = safeQuery($conn, "SELECT COUNT(*) as total FROM Langganan lg WHERE " . $where_langganan_sql, $params_l);
$d = safeFetch($q);
if ($d) $total_langganan = $d['total'] ?? 0;

$q = safeQuery($conn, "SELECT COUNT(*) as total FROM Beli_Alat ba WHERE ba.Status = 1 AND " . $where_beli_sql, $params_ba);
$d = safeFetch($q);
if ($d) $total_beli = $d['total'] ?? 0;

$q = safeQuery($conn, "SELECT COUNT(*) as total FROM Booking b WHERE b.Status = 3 AND " . $where_booking_sql, $params_b);
$d = safeFetch($q);
if ($d) $total_batal_count = $d['total'] ?? 0;

// ============================================
// CHART DATA: Omzet per Sumber per Bulan
// ============================================
$chart_labels = [];
$chart_booking = [];
$chart_langganan = [];
$chart_beli = [];
$chart_refund = [];

// Booking per bulan
$booking_months = [];
$q = safeQuery($conn, 
    "SELECT MONTH(b.Tanggal_Booking) as bulan, YEAR(b.Tanggal_Booking) as tahun,
     ISNULL(SUM(CASE WHEN b.Status IN (1,2) THEN b.Total_Bayar ELSE 0 END), 0) as omzet,
     ISNULL(SUM(pb.Nominal_Refund), 0) as refund
     FROM Booking b
     LEFT JOIN Pembatalan_Booking pb ON b.ID_Booking = pb.ID_Booking
     WHERE " . $where_booking_sql . "
     GROUP BY MONTH(b.Tanggal_Booking), YEAR(b.Tanggal_Booking)
     ORDER BY YEAR(b.Tanggal_Booking), MONTH(b.Tanggal_Booking)", $params_b);
if ($q !== null) {
    while ($row = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)) {
        $key = $row['tahun'] . '-' . str_pad($row['bulan'], 2, '0', STR_PAD_LEFT);
        $booking_months[$key] = ['omzet' => $row['omzet'] ?? 0, 'refund' => $row['refund'] ?? 0];
    }
}

// Langganan per bulan
$langganan_months = [];
$q = safeQuery($conn, 
    "SELECT MONTH(lg.Tanggal_Mulai) as bulan, YEAR(lg.Tanggal_Mulai) as tahun,
     ISNULL(SUM(lg.Total_Bayar), 0) as omzet
     FROM Langganan lg
     WHERE " . $where_langganan_sql . "
     GROUP BY MONTH(lg.Tanggal_Mulai), YEAR(lg.Tanggal_Mulai)
     ORDER BY YEAR(lg.Tanggal_Mulai), MONTH(lg.Tanggal_Mulai)", $params_l);
if ($q !== null) {
    while ($row = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)) {
        $key = $row['tahun'] . '-' . str_pad($row['bulan'], 2, '0', STR_PAD_LEFT);
        $langganan_months[$key] = $row['omzet'] ?? 0;
    }
}

// Beli Alat per bulan
$beli_months = [];
$q = safeQuery($conn, 
    "SELECT MONTH(ba.Tanggal_Beli) as bulan, YEAR(ba.Tanggal_Beli) as tahun,
     ISNULL(SUM(ba.Total_Bayar), 0) as omzet
     FROM Beli_Alat ba
     WHERE ba.Status = 1 AND " . $where_beli_sql . "
     GROUP BY MONTH(ba.Tanggal_Beli), YEAR(ba.Tanggal_Beli)
     ORDER BY YEAR(ba.Tanggal_Beli), MONTH(ba.Tanggal_Beli)", $params_ba);
if ($q !== null) {
    while ($row = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)) {
        $key = $row['tahun'] . '-' . str_pad($row['bulan'], 2, '0', STR_PAD_LEFT);
        $beli_months[$key] = $row['omzet'] ?? 0;
    }
}

// Merge all months
$all_months = array_unique(array_merge(array_keys($booking_months), array_keys($langganan_months), array_keys($beli_months)));
sort($all_months);

$monthNames = ['','Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
foreach ($all_months as $m) {
    $parts = explode('-', $m);
    $chart_labels[] = $monthNames[(int)$parts[1]] . ' ' . $parts[0];
    $chart_booking[] = ($booking_months[$m]['omzet'] ?? 0) - ($booking_months[$m]['refund'] ?? 0);
    $chart_langganan[] = $langganan_months[$m] ?? 0;
    $chart_beli[] = $beli_months[$m] ?? 0;
    $chart_refund[] = $booking_months[$m]['refund'] ?? 0;
}

// ============================================
// DETAIL TRANSAKSI TERBARU
// ============================================
$recent_bookings = [];
$q = safeQuery($conn, 
    "SELECT TOP 5 b.ID_Booking, b.Tanggal_Booking, b.Total_Bayar, b.Status, b.Metode_Pembayaran,
     c.Nama_Customer, l.Nama_Lapangan
     FROM Booking b
     LEFT JOIN Customer c ON b.ID_Customer = c.ID_Customer
     LEFT JOIN Jadwal j ON b.ID_Jadwal = j.ID_Jadwal
     LEFT JOIN Lapangan l ON j.ID_Lapangan = l.ID_Lapangan
     WHERE " . $where_booking_sql . "
     ORDER BY b.Tanggal_Booking DESC", $params_b);
if ($q !== null) {
    while ($row = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)) {
        $recent_bookings[] = $row;
    }
}

$recent_langganan = [];
$q = safeQuery($conn, 
    "SELECT TOP 5 lg.ID_Langganan, lg.Tanggal_Mulai, lg.Total_Bayar, lg.Status,
     c.Nama_Customer, tm.Nama_Tipe
     FROM Langganan lg
     LEFT JOIN Customer c ON lg.ID_Customer = c.ID_Customer
     LEFT JOIN Tipe_Member tm ON lg.ID_Tipe = tm.ID_Tipe
     WHERE " . $where_langganan_sql . "
     ORDER BY lg.Tanggal_Mulai DESC", $params_l);
if ($q !== null) {
    while ($row = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)) {
        $recent_langganan[] = $row;
    }
}

$recent_beli = [];
$q = safeQuery($conn, 
    "SELECT TOP 5 ba.ID_Beli, ba.Tanggal_Beli, ba.Total_Bayar, ba.Metode_Pembayaran,
     c.Nama_Customer
     FROM Beli_Alat ba
     LEFT JOIN Customer c ON ba.ID_Customer = c.ID_Customer
     WHERE ba.Status = 1 AND " . $where_beli_sql . "
     ORDER BY ba.Tanggal_Beli DESC", $params_ba);
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
<meta charset="UTF-8">
<title>Laporan Omzet | HoopBall</title>
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
.sb-avatar { width: 36px; height: 36px; background: var(--orange); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 14px; flex-shrink: 0; overflow: hidden; }
.sb-avatar img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
.sb-user-name { font-size: 13px; font-weight: 800; color: #E5E7EB; line-height: 1.1; }
.sb-user-role { font-size: 10px; color: var(--orange); font-weight: 700; text-transform: uppercase; }
.sb-logout { margin-left: auto; color: #4B5563; font-size: 13px; transition: .2s; cursor: pointer; text-decoration: none; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 8px; }
.sb-logout:hover { color: var(--red); background: rgba(239,68,68,.1); }

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

/* STAT GRID - BIG */
.stat-grid-big { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 24px; }
@media(max-width:1100px){ .stat-grid-big { grid-template-columns: 1fr; } }
.stat-card-big { background: var(--card-bg); border-radius: 20px; padding: 28px 32px; border: 1px solid var(--border); position: relative; overflow: hidden; transition: all .2s ease; }
.stat-card-big:hover { transform: translateY(-3px); box-shadow: 0 16px 40px rgba(0,0,0,.1); }
.stat-card-big::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 4px; border-radius: 4px 4px 0 0; }
.scb-orange::before { background: var(--orange); }
.scb-green::before { background: var(--green); }
.scb-blue::before { background: var(--blue); }
.scb-purple::before { background: var(--purple); }
.scb-red::before { background: var(--red); }
.scb-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
.scb-icon-wrap { width: 52px; height: 52px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 22px; }
.scbi-orange { background: var(--orange-lt); color: var(--orange); }
.scbi-green { background: var(--green-lt); color: var(--green); }
.scbi-blue { background: var(--blue-lt); color: var(--blue); }
.scbi-purple { background: var(--purple-lt); color: var(--purple); }
.scbi-red { background: var(--red-lt); color: var(--red); }
.scbi-yellow { background: var(--yellow-lt); color: var(--yellow); }
.scb-value { font-family: 'Barlow Condensed', sans-serif; font-size: 36px; font-weight: 900; color: var(--text); line-height: 1; margin-bottom: 8px; }
.scb-label { font-size: 13px; color: var(--muted); font-weight: 700; text-transform: uppercase; letter-spacing: .5px; }
.scb-detail { font-size: 12px; color: var(--muted); margin-top: 6px; font-weight: 600; }

/* STAT GRID SMALL */
.stat-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
@media(max-width:1100px){ .stat-grid { grid-template-columns: repeat(2, 1fr); } }
.stat-card { background: var(--card-bg); border-radius: 16px; padding: 20px 22px; border: 1px solid var(--border); position: relative; overflow: hidden; transition: all .2s ease; }
.stat-card:hover { transform: translateY(-3px); box-shadow: 0 12px 32px rgba(0,0,0,.08); }
.stat-card::before { content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; border-radius: 4px 0 0 4px; }
.sc-green::before { background: var(--green); }
.sc-blue::before { background: var(--blue); }
.sc-purple::before { background: var(--purple); }
.sc-red::before { background: var(--red); }
.stat-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; }
.stat-icon-wrap { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 16px; }
.si-green { background: var(--green-lt); color: var(--green); }
.si-blue { background: var(--blue-lt); color: var(--blue); }
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
.data-table { width: 100%; border-collapse: collapse; }
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

/* CHART */
.chart-container { position: relative; height: 380px; }

/* GRID LAYOUT */
.dashboard-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 22px; }
@media(max-width:1100px){ .dashboard-grid { grid-template: 1fr; } }

/* BREAKDOWN */
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

<aside class="sidebar">
    <a href="..dashboard/view_pemilik.php" class="sb-brand">
        <div class="sb-icon"><i class="fa-solid fa-basketball"></i></div>
        <div><div class="sb-brand-name">HOOP BALL</div><div class="sb-brand-sub">Sistem Managemen</div></div>
    </a>

    <div class="sb-section-label">Manajemen</div>
    <nav>
        <a href="../dashboard/view_pemilik.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-house"></i></div>
            Dashboard
        </a>
        <a href="../master/karyawan.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-user-tie"></i></div>
            Kelola Karyawan
        </a>
        <a href="laporan_omzet.php" class="sb-link active">
            <div class="sb-icon-wrap"><i class="fa-solid fa-chart-line"></i></div>
            Laporan & Omzet
        </a>
    </nav>

    <div class="sb-section-label">Akun</div>
    <a href="../profile/profile_pemilik.php" class="sb-link">
        <div class="sb-icon-wrap"><i class="fa-solid fa-id-badge"></i></div>
        Profil Saya
    </a>

    <div class="sb-bottom">
        <div class="sb-user">
            <div class="sb-avatar">
                <?php if ($profile_photo): ?>
                    <img src="<?= $profile_photo ?>" alt="Profile">
                <?php else: ?>
                    <i class="fa-solid fa-user"></i>
                <?php endif; ?>
            </div>
            <div><div class="sb-user-name"><?= strtoupper(htmlspecialchars($nama)) ?></div><div class="sb-user-role">MANAJER</div></div>
            <a href="../login/logout.php" class="sb-logout" title="Keluar"><i class="fa-solid fa-right-from-bracket"></i></a>
        </div>
    </div>
</aside>

<main class="main">
<header class="topbar">
    <div class="topbar-left">
        <div class="topbar-title">Laporan Omzet</div>
        <div class="topbar-breadcrumb">Laporan / Omzet</div>
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
        <button type="submit" class="filter-btn"><i class="fa-solid fa-filter"></i> Terapkan</button>
        <a href="laporan_omzet.php" class="filter-btn secondary"><i class="fa-solid fa-rotate-right"></i> Reset</a>
    </form>

    <!-- BIG STAT CARDS -->
    <div class="stat-grid-big">
        <div class="stat-card-big scb-orange">
            <div class="scb-header">
                <div class="scb-icon-wrap scbi-orange"><i class="fa-solid fa-money-bill-wave"></i></div>
                <span class="card-badge">TOTAL</span>
            </div>
            <div class="scb-value"><?= rupiahFormat($total_omzet_kotor) ?></div>
            <div class="scb-label">Total Omzet Kotor</div>
            <div class="scb-detail">Dari <?= $total_booking + $total_langganan + $total_beli ?> transaksi</div>
        </div>
        <div class="stat-card-big scb-red">
            <div class="scb-header">
                <div class="scb-icon-wrap scbi-red"><i class="fa-solid fa-rotate-left"></i></div>
                <span class="card-badge">REFUND</span>
            </div>
            <div class="scb-value"><?= rupiahFormat($total_refund) ?></div>
            <div class="scb-label">Total Refund (Pembatalan)</div>
            <div class="scb-detail"><?= $total_batal_count ?> booking dibatalkan</div>
        </div>
        <div class="stat-card-big scb-green">
            <div class="scb-header">
                <div class="scb-icon-wrap scbi-green"><i class="fa-solid fa-wallet"></i></div>
                <span class="card-badge">BERSIH</span>
            </div>
            <div class="scb-value"><?= rupiahFormat($total_omzet_bersih) ?></div>
            <div class="scb-label">Omzet Bersih</div>
            <div class="scb-detail">Omzet Kotor - Refund</div>
        </div>
    </div>

    <!-- SMALL STAT CARDS -->
    <div class="stat-grid">
        <div class="stat-card sc-green">
            <div class="stat-header"><div class="stat-icon-wrap si-green"><i class="fa-solid fa-calendar-check"></i></div></div>
            <div class="stat-value" style="font-size:22px;"><?= rupiahFormat($omzet_booking) ?></div><div class="stat-label">Omzet Sewa Lapangan</div>
        </div>
        <div class="stat-card sc-blue">
            <div class="stat-header"><div class="stat-icon-wrap si-blue"><i class="fa-solid fa-crown"></i></div></div>
            <div class="stat-value" style="font-size:22px;"><?= rupiahFormat($omzet_langganan) ?></div><div class="stat-label">Omzet Langganan</div>
        </div>
        <div class="stat-card sc-purple">
            <div class="stat-header"><div class="stat-icon-wrap si-purple"><i class="fa-solid fa-basketball"></i></div></div>
            <div class="stat-value" style="font-size:22px;"><?= rupiahFormat($omzet_beli_alat) ?></div><div class="stat-label">Omzet Pembelian Alat</div>
        </div>
        <div class="stat-card sc-red">
            <div class="stat-header"><div class="stat-icon-wrap si-red"><i class="fa-solid fa-ban"></i></div></div>
            <div class="stat-value" style="font-size:22px;"><?= rupiahFormat($total_batal) ?></div><div class="stat-label">Biaya Pembatalan (50%)</div>
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

    <!-- Transaksi Terbaru -->
    <div class="dashboard-grid">
        <div class="card">
            <div class="card-header"><div class="card-title"><i class="fa-solid fa-calendar-check"></i> Booking Terbaru</div><a href="laporan_sewa_lapangan.php" class="card-badge" style="text-decoration:none;">Lihat Semua →</a></div>
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
            <div class="card-header"><div class="card-title"><i class="fa-solid fa-crown"></i> Langganan Terbaru</div><a href="laporan_langganan.php" class="card-badge" style="text-decoration:none;">Lihat Semua →</a></div>
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

const ctx = document.getElementById('omzetChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?= json_encode($chart_labels) ?>,
        datasets: [
            {
                label: 'Sewa Lapangan (Bersih)',
                data: <?= json_encode($chart_booking) ?>,
                backgroundColor: 'rgba(16, 185, 129, 0.8)',
                borderColor: '#10B981',
                borderWidth: 2,
                borderRadius: 6,
            },
            {
                label: 'Langganan',
                data: <?= json_encode($chart_langganan) ?>,
                backgroundColor: 'rgba(59, 130, 246, 0.8)',
                borderColor: '#3B82F6',
                borderWidth: 2,
                borderRadius: 6,
            },
            {
                label: 'Pembelian Alat',
                data: <?= json_encode($chart_beli) ?>,
                backgroundColor: 'rgba(139, 92, 246, 0.8)',
                borderColor: '#8B5CF6',
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
                callbacks: {
                    label: function(context) {
                        return context.dataset.label + ': Rp ' + context.parsed.y.toLocaleString('id-ID');
                    }
                }
            }
        },
        scales: {
            y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)', drawBorder: false }, ticks: { font: { family: 'Barlow', size: 11 }, color: '#6B7280', callback: function(value) { return 'Rp ' + (value/1000000).toFixed(1) + 'jt'; } } },
            x: { grid: { display: false }, ticks: { font: { family: 'Barlow', size: 11 }, color: '#6B7280' } }
        }
    }
});
</script>
</body>
</html>