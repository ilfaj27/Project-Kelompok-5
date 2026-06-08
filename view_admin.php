<?php
session_start();
include 'includes/config.php';

if (!isset($_SESSION['login']) || $_SESSION['role'] !== 'karyawan') {
    header("Location: login.php");
    exit();
}

$role = $_SESSION['role'];
$nama = $_SESSION['nama'];

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

// ── STATISTIK OPERASIONAL ──

$q = safeQuery($conn, "SELECT COUNT(*) as total FROM Booking WHERE CAST(Tanggal_Booking AS DATE) = CAST(GETDATE() AS DATE)");
$d = safeFetch($q);
$total_booking_today = $d ? ($d['total'] ?? 0) : 0;

$q = safeQuery($conn, "SELECT COUNT(*) as total FROM Booking WHERE Status_Booking = 'pending'");
$d = safeFetch($q);
$total_pending = $d ? ($d['total'] ?? 0) : 0;

$q = safeQuery($conn, "SELECT COUNT(*) as total FROM Booking WHERE CAST(Tanggal_Booking AS DATE) = CAST(GETDATE() AS DATE) AND Status_Booking = 'confirmed'");
$d = safeFetch($q);
$total_confirmed = $d ? ($d['total'] ?? 0) : 0;

$q = safeQuery($conn, "SELECT COUNT(*) as total FROM Booking WHERE CAST(Tanggal_Booking AS DATE) = CAST(GETDATE() AS DATE) AND Status_Booking = 'cancelled'");
$d = safeFetch($q);
$total_cancelled = $d ? ($d['total'] ?? 0) : 0;

$q = safeQuery($conn, "SELECT SUM(Total_Harga) as total FROM Booking WHERE CAST(Tanggal_Booking AS DATE) = CAST(GETDATE() AS DATE) AND Status_Booking = 'confirmed'");
$d = safeFetch($q);
$pendapatan_hari = $d ? ($d['total'] ?? 0) : 0;

$q = safeQuery($conn, "SELECT COUNT(*) as total FROM Customer c JOIN Akun a ON c.ID_Akun = a.ID_Akun WHERE a.Status_Akun = 1");
$d = safeFetch($q);
$total_customer = $d ? ($d['total'] ?? 0) : 0;

$q = safeQuery($conn, "SELECT COUNT(*) as total FROM Lapangan WHERE Status_Lapangan = 1");
$d = safeFetch($q);
$total_lapangan = $d ? ($d['total'] ?? 0) : 0;

$q = safeQuery($conn, "SELECT COUNT(*) as total FROM Booking WHERE CAST(Tanggal_Booking AS DATE) = CAST(GETDATE() AS DATE) AND Status_Booking = 'confirmed' AND CAST(GETDATE() AS TIME) BETWEEN Jam_Mulai AND Jam_Selesai");
$d = safeFetch($q);
$lapangan_used = $d ? ($d['total'] ?? 0) : 0;

// ── DATA BOOKING TERBARU ──
$recent_bookings = [];
$q = safeQuery($conn, "SELECT TOP 5 b.ID_Booking, c.Nama_Customer, l.Nama_Lapangan, b.Jam_Mulai, b.Jam_Selesai, b.Status_Booking, b.Total_Harga, b.Tanggal_Booking 
    FROM Booking b 
    JOIN Customer c ON b.ID_Customer = c.ID_Customer 
    JOIN Lapangan l ON b.ID_Lapangan = l.ID_Lapangan 
    ORDER BY b.Tanggal_Booking DESC");
if ($q !== null) {
    while ($row = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)) {
        $recent_bookings[] = $row;
    }
}

// ── DATA BOOKING MENUNGGU KONFIRMASI ──
$waiting_bookings = [];
$q = safeQuery($conn, "SELECT TOP 5 b.ID_Booking, c.Nama_Customer, l.Nama_Lapangan, b.Jam_Mulai, b.Jam_Selesai, b.Total_Harga, b.Tanggal_Booking 
    FROM Booking b 
    JOIN Customer c ON b.ID_Customer = c.ID_Customer 
    JOIN Lapangan l ON b.ID_Lapangan = l.ID_Lapangan 
    WHERE b.Status_Booking = 'pending' 
    ORDER BY b.Tanggal_Booking DESC");
if ($q !== null) {
    while ($row = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)) {
        $waiting_bookings[] = $row;
    }
}

// ── DATA CUSTOMER TERBARU ──
$recent_customers = [];
$q = safeQuery($conn, "SELECT TOP 5 c.ID_Customer, c.Nama_Customer, c.No_Telepon, c.Alamat, a.Email 
    FROM Customer c 
    JOIN Akun a ON c.ID_Akun = a.ID_Akun 
    WHERE a.Status_Akun = 1 
    ORDER BY c.ID_Customer DESC");
if ($q !== null) {
    while ($row = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)) {
        $recent_customers[] = $row;
    }
}

// ── DATA LAPANGAN ──
$lapangan_list = [];
$q = safeQuery($conn, "SELECT ID_Lapangan, Nama_Lapangan, Harga_Sewa, Status_Lapangan FROM Lapangan ORDER BY ID_Lapangan");
if ($q !== null) {
    while ($row = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)) {
        $lapangan_list[] = $row;
    }
}

// ── DATA PROMO AKTIF ──
$promo_aktif = [];
$q = safeQuery($conn, "SELECT TOP 5 ID_Promo, Nama_Promo, Diskon, Tanggal_Mulai, Tanggal_Selesai FROM Promo WHERE Tanggal_Selesai >= CAST(GETDATE() AS DATE) ORDER BY Tanggal_Mulai DESC");
if ($q !== null) {
    while ($row = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)) {
        $promo_aktif[] = $row;
    }
}

function rupiahFormat($n) { 
    return 'Rp ' . number_format($n, 0, ',', '.'); 
}

function formatJam($jam) {
    if ($jam instanceof DateTime) {
        return $jam->format('H:i');
    }
    return $jam;
}

function formatTanggal($tgl) {
    if ($tgl instanceof DateTime) {
        return $tgl->format('d M Y');
    }
    return $tgl;
}
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
.sb-avatar { width: 36px; height: 36px; background: var(--blue); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 14px; flex-shrink: 0; }
.sb-user-name { font-size: 13px; font-weight: 800; color: #E5E7EB; line-height: 1.1; }
.sb-user-role { font-size: 10px; color: var(--blue); font-weight: 700; text-transform: uppercase; }
.sb-logout { margin-left: auto; color: #4B5563; font-size: 13px; transition: .2s; cursor: pointer; text-decoration: none; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 8px; }
.sb-logout:hover { color: var(--red); background: rgba(239,68,68,.1); }

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
.t-avatar { width: 32px; height: 32px; background: var(--blue); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 13px; }
.t-name { font-size: 13px; font-weight: 800; color: var(--text); line-height: 1.1; }
.t-role { font-size: 10px; color: var(--blue); font-weight: 700; text-transform: uppercase; }
.t-chevron { color: var(--muted); font-size: 10px; margin-left: 4px; }
.dropdown-menu { display: none; position: absolute; right: 0; top: calc(100% + 8px); background: #fff; min-width: 200px; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 15px 40px rgba(0,0,0,.12); overflow: hidden; padding: 8px 0; z-index: 999; }
.dropdown-wrap:hover .dropdown-menu { display: block; }
.dd-item { display: flex; align-items: center; gap: 10px; padding: 11px 16px; color: #444; text-decoration: none; font-size: 13px; font-weight: 700; transition: .15s; }
.dd-item:hover { background: #FFF7ED; color: var(--orange); }
.dd-item i { font-size: 14px; width: 18px; text-align: center; }
.dd-divider { border: none; border-top: 1px solid #F3F4F6; margin: 4px 0; }

.content { padding: 32px 40px; flex: 1; }
.welcome-banner { background: linear-gradient(135deg, #0D1117 0%, #1a1a2e 100%); border-radius: 20px; padding: 32px 36px; display: flex; align-items: center; justify-content: space-between; margin-bottom: 28px; overflow: hidden; position: relative; border: 1px solid rgba(59,130,246,.15); }
.wb-deco { position: absolute; right: -30px; top: -30px; width: 220px; height: 220px; border-radius: 50%; background: radial-gradient(circle, rgba(59,130,246,.18) 0%, transparent 70%); }
.wb-deco2 { position: absolute; right: 120px; bottom: -40px; width: 160px; height: 160px; border-radius: 50%; background: radial-gradient(circle, rgba(59,130,246,.08) 0%, transparent 70%); }
.wb-text { position: relative; z-index: 1; }
.wb-greeting { font-size: 13px; color: #6B7280; font-weight: 700; margin-bottom: 6px; text-transform: uppercase; letter-spacing: .8px; }
.wb-name { font-family: 'Barlow Condensed', sans-serif; font-size: 32px; font-weight: 900; color: #fff; letter-spacing: -.5px; }
.wb-sub { font-size: 14px; color: #6B7280; margin-top: 4px; }
.wb-icon { position: relative; z-index: 1; }
.wb-icon i { font-size: 64px; color: rgba(59,130,246,.25); }

.stat-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 18px; margin-bottom: 28px; }
.stat-card { background: var(--card-bg); border-radius: 16px; padding: 22px 24px; border: 1px solid var(--border); position: relative; overflow: hidden; transition: all .2s ease; }
.stat-card:hover { transform: translateY(-3px); box-shadow: 0 12px 32px rgba(0,0,0,.08); }
.stat-card::before { content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; border-radius: 4px 0 0 4px; }
.sc-blue::before { background: var(--blue); }
.sc-green::before { background: var(--green); }
.sc-orange::before { background: var(--orange); }
.sc-purple::before { background: var(--purple); }
.sc-red::before { background: var(--red); }
.sc-yellow::before { background: var(--yellow); }
.stat-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
.stat-icon-wrap { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 18px; }
.si-blue { background: var(--blue-lt); color: var(--blue); }
.si-green { background: var(--green-lt); color: var(--green); }
.si-orange { background: var(--orange-lt); color: var(--orange); }
.si-purple { background: var(--purple-lt); color: var(--purple); }
.si-red { background: var(--red-lt); color: var(--red); }
.si-yellow { background: var(--yellow-lt); color: var(--yellow); }
.stat-trend { font-size: 11px; font-weight: 800; display: flex; align-items: center; gap: 3px; padding: 4px 8px; border-radius: 20px; }
.trend-up { color: var(--green); background: var(--green-lt); }
.trend-down { color: var(--red); background: var(--red-lt); }
.trend-warn { color: var(--yellow); background: var(--yellow-lt); }
.stat-value { font-family: 'Barlow Condensed', sans-serif; font-size: 30px; font-weight: 900; color: var(--text); line-height: 1; margin-bottom: 6px; }
.stat-label { font-size: 12px; color: var(--muted); font-weight: 700; text-transform: uppercase; letter-spacing: .5px; }
.stat-sublabel { font-size: 11px; color: var(--muted); margin-top: 4px; opacity: .7; }

.chart-section { display: grid; grid-template-columns: 1fr 2fr; gap: 22px; margin-bottom: 28px; }
@media(max-width:1200px){ .chart-section { grid-template-columns: 1fr; } }
.chart-card { background: var(--card-bg); border-radius: 16px; border: 1px solid var(--border); padding: 24px; }
.chart-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
.chart-title { font-size: 15px; font-weight: 800; color: var(--text); display: flex; align-items: center; gap: 8px; }
.chart-title i { color: var(--blue); font-size: 14px; }
.chart-badge { background: var(--blue-lt); color: var(--blue); font-size: 11px; font-weight: 800; padding: 4px 10px; border-radius: 20px; }
.chart-container { position: relative; height: 280px; }
.mini-stat-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.mini-stat { background: var(--border-lt); border-radius: 12px; padding: 16px; border: 1px solid var(--border); }
.mini-stat-label { font-size: 11px; color: var(--muted); font-weight: 700; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 4px; }
.mini-stat-value { font-family: 'Barlow Condensed', sans-serif; font-size: 22px; font-weight: 900; color: var(--text); }
.mini-stat-value.red { color: var(--red); }
.mini-stat-value.orange { color: var(--orange); }
.mini-stat-value.green { color: var(--green); }

.dashboard-grid { display: grid; grid-template-columns: 1fr 340px; gap: 22px; }
@media(max-width:1100px){ .dashboard-grid { grid-template-columns: 1fr; } }

.card { background: var(--card-bg); border-radius: 16px; border: 1px solid var(--border); overflow: hidden; transition: all .2s ease; }
.card:hover { box-shadow: 0 8px 24px rgba(0,0,0,.06); }
.card-header { padding: 20px 24px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
.card-title { font-size: 15px; font-weight: 800; color: var(--text); display: flex; align-items: center; gap: 8px; }
.card-title i { color: var(--blue); font-size: 14px; }
.card-badge { background: var(--blue-lt); color: var(--blue); font-size: 11px; font-weight: 800; padding: 4px 10px; border-radius: 20px; }
.card-badge.orange { background: var(--orange-lt); color: var(--orange); }
.card-badge.red { background: var(--red-lt); color: var(--red); }
.card-link { font-size: 12px; font-weight: 700; color: var(--blue); text-decoration: none; display: flex; align-items: center; gap: 4px; transition: .2s; }
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
.sp-confirmed { background: var(--green-lt); color: var(--green); }
.sp-pending { background: var(--yellow-lt); color: #D97706; }
.sp-cancelled { background: var(--red-lt); color: var(--red); }
.sp-active { background: var(--green-lt); color: var(--green); }
.sp-inactive { background: var(--red-lt); color: var(--red); }

.price-col { font-weight: 800; font-size: 13px; color: var(--text); font-family: 'Barlow Condensed', sans-serif; }

.quick-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.quick-card { background: var(--bg); border: 1px solid var(--border); border-radius: 12px; padding: 20px; text-decoration: none; text-align: center; transition: all .2s ease; display: flex; flex-direction: column; align-items: center; gap: 10px; }
.quick-card:hover { border-color: var(--blue); background: var(--blue-lt); transform: translateY(-2px); box-shadow: 0 8px 20px rgba(59,130,246,.1); }
.quick-card i { font-size: 24px; transition: .2s; }
.quick-card:hover i { transform: scale(1.1); }
.quick-card span { font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .4px; }

.alert-box { background: var(--yellow-lt); border: 1px solid rgba(245,158,11,.2); border-radius: 12px; padding: 14px 18px; display: flex; align-items: center; gap: 12px; margin-bottom: 22px; }
.alert-box i { color: var(--yellow); font-size: 16px; }
.alert-box span { font-size: 13px; font-weight: 700; color: #D97706; }

.waiting-item { display: flex; align-items: center; gap: 14px; padding: 14px 0; border-bottom: 1px solid var(--border-lt); }
.waiting-item:last-child { border-bottom: none; }
.waiting-avatar { width: 42px; height: 42px; border-radius: 10px; background: var(--orange-lt); color: var(--orange); display: flex; align-items: center; justify-content: center; font-size: 16px; font-weight: 800; flex-shrink: 0; }
.waiting-info { flex: 1; }
.waiting-name { font-size: 14px; font-weight: 700; color: var(--text); }
.waiting-detail { font-size: 11px; color: var(--muted); font-weight: 600; margin-top: 2px; }
.waiting-actions { display: flex; gap: 6px; }
.btn-confirm { background: var(--green); color: #fff; border: none; padding: 6px 12px; border-radius: 6px; font-size: 11px; font-weight: 700; cursor: pointer; transition: .2s; }
.btn-confirm:hover { background: #059669; }
.btn-cancel { background: var(--red-lt); color: var(--red); border: none; padding: 6px 12px; border-radius: 6px; font-size: 11px; font-weight: 700; cursor: pointer; transition: .2s; }
.btn-cancel:hover { background: var(--red); color: #fff; }

#clock-display { display: flex; align-items: center; gap: 16px; }
.clock-time { font-family: 'Barlow Condensed', sans-serif; font-size: 26px; font-weight: 900; color: var(--blue); display: flex; align-items: center; gap: 6px; line-height: 1; }
.clock-colon { color: var(--blue); opacity: .5; animation: blink 1s infinite; }
@keyframes blink { 0%, 100% { opacity: .5; } 50% { opacity: 1; } }
.clock-divider { width: 1.5px; height: 28px; background-color: var(--border); }
.clock-date { font-family: 'Barlow Condensed', sans-serif; font-size: 15px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; }
</style>
</head>
<body>

<aside class="sidebar">
    <a href="dashboard.php" class="sb-brand">
        <div class="sb-icon"><i class="fa-solid fa-basketball"></i></div>
        <div><div class="sb-brand-name">HOOP BALL</div><div class="sb-brand-sub">Staff Panel</div></div>
    </a>

    <!-- KARYAWAN: MENU UTAMA -->
    <div class="sb-section-label">Menu Utama</div>
    <nav>
        <a href="dashboard_karyawan.php" class="sb-link active">
            <div class="sb-icon-wrap"><i class="fa-solid fa-house"></i></div>
            Dashboard
        </a>
        <a href="booking.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-calendar-check"></i></div>
            Booking
            <?php if($total_pending > 0): ?><span class="badge"><?= $total_pending ?></span><?php endif; ?>
        </a>
        <a href="master/lapangan.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-layer-group"></i></div>
            Lapangan
        </a>
        <a href="master/customer.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-users"></i></div>
            Customer
        </a>
        <a href="master/promo.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-tags"></i></div>
            Promo
        </a>
    </nav>

    <!-- KARYAWAN: LAYANAN -->
    <div class="sb-section-label">Layanan</div>
    <nav>
        <a href="master/promo.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-tags"></i></div>
            Promo & Diskon
        </a>
        <a href="master/supplier.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-box"></i></div>
            Stok Alat
        </a>
    </nav>

    <!-- KARYAWAN: AKUN -->
    <div class="sb-section-label">Akun</div>
    <a href="profile.php" class="sb-link">
        <div class="sb-icon-wrap"><i class="fa-solid fa-id-badge"></i></div>
        Profil Saya
    </a>

    <div class="sb-bottom">
        <div class="sb-user">
            <div class="sb-avatar"><i class="fa-solid fa-user"></i></div>
            <div><div class="sb-user-name"><?= strtoupper(htmlspecialchars($nama)) ?></div><div class="sb-user-role">KARYAWAN</div></div>
            <a href="logout.php" class="sb-logout" title="Keluar"><i class="fa-solid fa-right-from-bracket"></i></a>
        </div>
    </div>
</aside>

<main class="main">
<header class="topbar">
    <div class="topbar-left">
        <div class="topbar-title">Dashboard Karyawan</div>
        <div class="topbar-date" id="clock-display">
            <div class="clock-time"><span id="h">00</span><span class="clock-colon">:</span><span id="m">00</span><span class="clock-colon">:</span><span id="s">00</span></div>
            <div class="clock-divider"></div>
            <div class="clock-date" id="full-date">MEMUAT...</div>
        </div>
    </div>
    <div class="topbar-right">
        <a href="#" class="topbar-btn"><i class="fa-solid fa-magnifying-glass"></i></a>
        <a href="#" class="topbar-btn"><i class="fa-solid fa-bell"></i><?php if($total_pending > 0): ?><span class="notif-dot"></span><?php endif; ?></a>
        <div class="dropdown-wrap">
            <div class="topbar-user">
                <div class="t-avatar"><i class="fa-solid fa-user"></i></div>
                <div><div class="t-name"><?= strtoupper(htmlspecialchars($nama)) ?></div><div class="t-role">KARYAWAN</div></div>
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
    <?php if($total_pending > 0): ?>
    <div class="alert-box"><i class="fa-solid fa-clock"></i><span><?= $total_pending ?> booking menunggu konfirmasi Anda</span><a href="booking.php?status=pending" style="color:#D97706; font-size:12px; font-weight:700; margin-left:auto; text-decoration:none;">Proses Sekarang →</a></div>
    <?php endif; ?>

    <div class="welcome-banner">
        <div class="wb-deco"></div><div class="wb-deco2"></div>
        <div class="wb-text"><div class="wb-greeting">Selamat Bekerja</div><div class="wb-name"><?= strtoupper(htmlspecialchars($nama)) ?> 👋</div><div class="wb-sub">Kelola operasional harian HoopBall dengan efisien.</div></div>
        <div class="wb-icon"><i class="fa-solid fa-clipboard-list"></i></div>
    </div>

    <div class="stat-grid">
        <div class="stat-card sc-blue">
            <div class="stat-header"><div class="stat-icon-wrap si-blue"><i class="fa-solid fa-calendar-day"></i></div><div class="stat-trend trend-up"><i class="fa-solid fa-arrow-up"></i> Hari Ini</div></div>
            <div class="stat-value"><?= $total_booking_today ?></div><div class="stat-label">Total Booking</div><div class="stat-sublabel"><?= $total_confirmed ?> terkonfirmasi</div>
        </div>
        <div class="stat-card sc-yellow">
            <div class="stat-header"><div class="stat-icon-wrap si-yellow"><i class="fa-solid fa-clock"></i></div><div class="stat-trend trend-warn"><i class="fa-solid fa-exclamation"></i> Perlu Aksi</div></div>
            <div class="stat-value"><?= $total_pending ?></div><div class="stat-label">Menunggu Konfirmasi</div><div class="stat-sublabel">Segera proses booking</div>
        </div>
        <div class="stat-card sc-green">
            <div class="stat-header"><div class="stat-icon-wrap si-green"><i class="fa-solid fa-money-bill-wave"></i></div><div class="stat-trend trend-up"><i class="fa-solid fa-arrow-up"></i> <?= rupiahFormat($pendapatan_hari) ?></div></div>
            <div class="stat-value" style="font-size:24px;"><?= rupiahFormat($pendapatan_hari) ?></div><div class="stat-label">Pendapatan Hari Ini</div><div class="stat-sublabel"><?= $total_confirmed ?> booking confirmed</div>
        </div>
        <div class="stat-card sc-purple">
            <div class="stat-header"><div class="stat-icon-wrap si-purple"><i class="fa-solid fa-layer-group"></i></div><div class="stat-trend trend-up"><i class="fa-solid fa-arrow-up"></i> <?= $lapangan_used ?>/<?= $total_lapangan ?></div></div>
            <div class="stat-value"><?= $total_lapangan - $lapangan_used ?></div><div class="stat-label">Lapangan Kosong</div><div class="stat-sublabel"><?= $lapangan_used ?> sedang digunakan</div>
        </div>
    </div>

    <div class="chart-section">
        <div class="chart-card">
            <div class="chart-header"><div class="chart-title"><i class="fa-solid fa-chart-pie"></i> Status Booking Hari Ini</div></div>
            <div class="chart-container" style="height:240px;"><canvas id="statusChart"></canvas></div>
            <div class="mini-stat-row" style="margin-top:16px;">
                <div class="mini-stat"><div class="mini-stat-label">Terkonfirmasi</div><div class="mini-stat-value green"><?= $total_confirmed ?></div></div>
                <div class="mini-stat"><div class="mini-stat-label">Menunggu</div><div class="mini-stat-value orange"><?= $total_pending ?></div></div>
                <div class="mini-stat"><div class="mini-stat-label">Dibatalkan</div><div class="mini-stat-value red"><?= $total_cancelled ?></div></div>
                <div class="mini-stat"><div class="mini-stat-label">Customer</div><div class="mini-stat-value"><?= $total_customer ?></div></div>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><div class="card-title"><i class="fa-solid fa-hourglass-half"></i> Menunggu Konfirmasi</div><span class="card-badge orange"><?= count($waiting_bookings) ?> data</span></div>
            <div class="card-body">
                <?php if(count($waiting_bookings) > 0): ?>
                <?php foreach($waiting_bookings as $w): ?>
                <div class="waiting-item">
                    <div class="waiting-avatar"><?= strtoupper(substr($w['Nama_Customer'], 0, 1)) ?></div>
                    <div class="waiting-info">
                        <div class="waiting-name"><?= htmlspecialchars($w['Nama_Customer']) ?></div>
                        <div class="waiting-detail"><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($w['Nama_Lapangan']) ?> • <i class="fa-regular fa-clock"></i> <?= formatJam($w['Jam_Mulai']) ?> - <?= formatJam($w['Jam_Selesai']) ?> • <?= rupiahFormat($w['Total_Harga']) ?></div>
                    </div>
                    <div class="waiting-actions">
                        <button class="btn-confirm" onclick="confirmBooking('<?= $w['ID_Booking'] ?>')">✓</button>
                        <button class="btn-cancel" onclick="cancelBooking('<?= $w['ID_Booking'] ?>')">✕</button>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php else: ?>
                <div style="text-align:center; padding:30px; color:var(--muted);"><i class="fa-solid fa-check-circle" style="font-size:32px; margin-bottom:10px; opacity:.5;"></i><div style="font-size:13px; font-weight:700;">Semua booking sudah terkonfirmasi!</div></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="dashboard-grid">
        <!-- Booking Hari Ini -->
        <div class="card">
            <div class="card-header"><div class="card-title"><i class="fa-solid fa-list-check"></i> Booking Hari Ini</div><div style="display:flex; align-items:center; gap:12px;"><span class="card-badge"><?= count($recent_bookings) ?> data</span><a href="booking.php" class="card-link">Lihat Semua <i class="fa-solid fa-arrow-right" style="font-size:10px;"></i></a></div></div>
            <div style="overflow-x:auto;">
                <table class="data-table">
                    <thead><tr><th>ID</th><th>Customer</th><th>Lapangan & Jam</th><th>Status</th><th style="text-align:right;">Harga</th></tr></thead>
                    <tbody>
                    <?php if(count($recent_bookings) > 0): ?>
                    <?php foreach($recent_bookings as $b): 
                        $status_map = ['confirmed' => ['cls'=>'sp-confirmed','lbl'=>'Terkonfirmasi'], 'pending' => ['cls'=>'sp-pending','lbl'=>'Menunggu'], 'cancelled' => ['cls'=>'sp-cancelled','lbl'=>'Dibatalkan']];
                        $status = $status_map[$b['Status_Booking']] ?? $status_map['pending'];
                    ?>
                        <tr>
                            <td><span style="font-family:'Barlow Condensed'; font-weight:800; color:var(--muted);">#<?= $b['ID_Booking'] ?></span></td>
                            <td><div class="cell-name"><?= htmlspecialchars($b['Nama_Customer']) ?></div></td>
                            <td><div class="cell-name"><?= htmlspecialchars($b['Nama_Lapangan']) ?></div><div class="cell-detail"><i class="fa-regular fa-clock"></i> <?= formatJam($b['Jam_Mulai']) ?> - <?= formatJam($b['Jam_Selesai']) ?></div></td>
                            <td><span class="status-pill <?= $status['cls'] ?>"><?= $status['lbl'] ?></span></td>
                            <td class="price-col" style="text-align:right;"><?= rupiahFormat($b['Total_Harga']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="text-align:center; padding:30px; color:var(--muted);"><i class="fa-solid fa-inbox" style="font-size:32px; margin-bottom:10px; opacity:.5; display:block;"></i><div style="font-size:13px; font-weight:700;">Belum ada data booking</div></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Right Column: Customer & Promo -->
        <div style="display:flex; flex-direction:column; gap:20px;">
            <!-- Customer Terbaru -->
            <div class="card">
                <div class="card-header"><div class="card-title"><i class="fa-solid fa-users"></i> Customer Terbaru</div><span class="card-badge"><?= count($recent_customers) ?> data</span></div>
                <div class="card-body">
                    <?php if(count($recent_customers) > 0): ?>
                    <table class="data-table">
                        <thead><tr><th>Nama</th><th>Telepon</th></tr></thead>
                        <tbody>
                        <?php foreach($recent_customers as $c): ?>
                            <tr>
                                <td><div class="cell-name"><?= htmlspecialchars($c['Nama_Customer']) ?></div><div class="cell-detail"><?= htmlspecialchars($c['Email']) ?></div></td>
                                <td><?= htmlspecialchars($c['No_Telepon']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div style="text-align:center; padding:20px; color:var(--muted);"><i class="fa-solid fa-inbox" style="font-size:24px; margin-bottom:8px; opacity:.5; display:block;"></i><div style="font-size:12px; font-weight:700;">Belum ada data customer</div></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Promo Aktif -->
            <div class="card">
                <div class="card-header"><div class="card-title"><i class="fa-solid fa-tags"></i> Promo Aktif</div><span class="card-badge orange"><?= count($promo_aktif) ?> promo</span></div>
                <div class="card-body">
                    <?php if(count($promo_aktif) > 0): ?>
                    <table class="data-table">
                        <thead><tr><th>Promo</th><th>Diskon</th><th>Berakhir</th></tr></thead>
                        <tbody>
                        <?php foreach($promo_aktif as $p): ?>
                            <tr>
                                <td><div class="cell-name"><?= htmlspecialchars($p['Nama_Promo']) ?></div></td>
                                <td><span class="price-col"><?= rupiahFormat($p['Diskon']) ?></span></td>
                                <td><span class="status-pill sp-active"><?= formatTanggal($p['Tanggal_Selesai']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div style="text-align:center; padding:20px; color:var(--muted);"><i class="fa-solid fa-inbox" style="font-size:24px; margin-bottom:8px; opacity:.5; display:block;"></i><div style="font-size:12px; font-weight:700;">Tidak ada promo aktif</div></div>
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

const ctx = document.getElementById('statusChart').getContext('2d');
const statusChart = new Chart(ctx, {
    type: 'doughnut',
    data: {
        labels: ['Terkonfirmasi', 'Menunggu', 'Dibatalkan'],
        datasets: [{
            data: [<?= $total_confirmed ?>, <?= $total_pending ?>, <?= $total_cancelled ?>],
            backgroundColor: ['#10B981', '#F59E0B', '#EF4444'],
            borderWidth: 0,
            hoverOffset: 8
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '65%',
        plugins: {
            legend: {
                position: 'bottom',
                labels: { font: { family: 'Barlow', size: 12, weight: '700' }, color: '#6B7280', padding: 20, usePointStyle: true, pointStyle: 'circle' }
            },
            tooltip: {
                backgroundColor: '#1F2937', titleColor: '#fff', bodyColor: '#fff', padding: 12, cornerRadius: 8, displayColors: false,
                callbacks: { label: function(context) { return context.label + ': ' + context.parsed + ' booking'; } }
            }
        }
    }
});

function confirmBooking(id) {
    if(confirm('Konfirmasi booking #' + id + '?')) {
        window.location.href = 'booking.php?action=confirm&id=' + id;
    }
}
function cancelBooking(id) {
    if(confirm('Batalkan booking #' + id + '?')) {
        window.location.href = 'booking.php?action=cancel&id=' + id;
    }
}
</script>

</body>
</html>