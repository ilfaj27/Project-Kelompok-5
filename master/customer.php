<?php
session_start();
include '../includes/config.php';

if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'karyawan' && $_SESSION['role'] !== 'pemilik')) {
    echo "<script>alert('Akses Ditolak!'); window.location='../dashboard.php';</script>";
    exit();
}
$role = $_SESSION['role'];
$nama = $_SESSION['nama'];
$map_jk = [1 => 'Laki-laki', 2 => 'Perempuan'];

// ═══════════════════════════════════════════
// HELPER: Safe SQLSRV Operations
// ═══════════════════════════════════════════
function safe_sqlsrv_query($conn, $sql, $params = [], $die_on_error = true) {
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        $errors = sqlsrv_errors();
        $error_details = [];
        if ($errors) {
            foreach ($errors as $error) {
                $error_details[] = "[SQLSTATE: " . $error['SQLSTATE'] . "] [Code: " . $error['code'] . "] " . $error['message'];
            }
        }
        $error_msg = implode(" | ", $error_details);
        error_log("[SQL ERROR] " . $error_msg . " | SQL: " . $sql . " | Params: " . json_encode($params));

        if ($die_on_error) {
            echo "<div style='padding:20px;background:#fee;border:1px solid #fcc;border-radius:8px;font-family:sans-serif;margin:20px;'>
                <h3 style='color:#c00;margin:0 0 10px;'><i class='fa-solid fa-circle-exclamation'></i> Database Error</h3>
                <p style='color:#333;margin:0 0 5px;'><strong>Detail Error:</strong></p>
                <pre style='background:#fff;padding:10px;border-radius:4px;overflow-x:auto;font-size:12px;'>" . htmlspecialchars($error_msg) . "</pre>
                <p style='color:#666;font-size:12px;margin:10px 0 0;'>SQL: " . htmlspecialchars($sql) . "</p>
                <p style='color:#666;font-size:12px;margin:5px 0 0;'>Silakan periksa koneksi database atau hubungi administrator.</p>
            </div>";
            exit();
        }
        return false;
    }
    return $stmt;
}

function safe_sqlsrv_fetch_array($stmt, $fetch_type = SQLSRV_FETCH_ASSOC) {
    if ($stmt === false || $stmt === null) {
        return false;
    }
    return sqlsrv_fetch_array($stmt, $fetch_type);
}

function safe_sqlsrv_has_rows($stmt) {
    if ($stmt === false || $stmt === null) {
        return false;
    }
    return sqlsrv_has_rows($stmt);
}

if (isset($_GET['delete_id'])) {
    $deleted_by = $_SESSION['nama'] ?? 'SYSTEM';
    $stmt = safe_sqlsrv_query($conn, "UPDATE Customer SET Is_Deleted=1, Status='Dihapus',
        Deleted_By=?, Deleted_Date=GETDATE() WHERE ID_Customer=?", 
        array($deleted_by, $_GET['delete_id']), false);

    header($stmt ? "Location: customer.php?page=1&status=success&msg=Data customer telah dihapus (soft delete)!" : "Location: customer.php?page=1&status=error&msg=Gagal menghapus, data mungkin terikat transaksi!");
    exit();
}

// --- STATISTIK ---
$q_total = safe_sqlsrv_query($conn, "SELECT COUNT(*) as t FROM Customer WHERE Is_Deleted=0", [], false);
$total_cust = 0;
if ($q_total !== false) {
    $row_total = safe_sqlsrv_fetch_array($q_total, SQLSRV_FETCH_ASSOC);
    $total_cust = $row_total['t'] ?? 0;
}

$q_laki = safe_sqlsrv_query($conn, "SELECT COUNT(*) as t FROM Customer WHERE Jenis_Kelamin=1 AND Is_Deleted=0", [], false);
$total_laki = 0;
if ($q_laki !== false) {
    $row_laki = safe_sqlsrv_fetch_array($q_laki, SQLSRV_FETCH_ASSOC);
    $total_laki = $row_laki['t'] ?? 0;
}

$q_perempuan = safe_sqlsrv_query($conn, "SELECT COUNT(*) as t FROM Customer WHERE Jenis_Kelamin=2 AND Is_Deleted=0", [], false);
$total_perempuan = 0;
if ($q_perempuan !== false) {
    $row_perempuan = safe_sqlsrv_fetch_array($q_perempuan, SQLSRV_FETCH_ASSOC);
    $total_perempuan = $row_perempuan['t'] ?? 0;
}

// --- PAGING CONFIGURATION ---
$limit = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

$total_pages = max(1, ceil($total_cust / $limit));
$page = min($page, $total_pages);
$offset = ($page - 1) * $limit;

$q_pending = sqlsrv_query($conn, "SELECT COUNT(*) as t FROM Booking WHERE Status=1"); // Status 1 = pending
$total_pending = sqlsrv_fetch_array($q_pending, SQLSRV_FETCH_ASSOC)['t'] ?? 0;

// Ambil data dengan paging
$query = sqlsrv_query($conn, "SELECT * FROM Customer ORDER BY ID_Customer ASC OFFSET ? ROWS FETCH NEXT ? ROWS ONLY", array($offset, $limit));
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Data Customer | HoopBall</title>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800;900&family=Barlow:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
:root {
    --orange: #FF4500; --orange-lt: rgba(255,69,0,.10); --orange-dk: #E03E00;
    --green: #10B981; --green-lt: rgba(16,185,129,.10);
    --blue: #3B82F6; --blue-lt: rgba(59,130,246,.10);
    --pink: #EC4899; --pink-lt: rgba(236,72,153,.10);
    --red: #EF4444; --red-lt: rgba(239,68,68,.10);
    --sidebar: #0D1117; --sidebar-w: 260px; --topbar-h: 70px;
    --card-bg: #FFFFFF; --border: #E5E7EB; --border-lt: #F3F4F6;
    --text: #111827; --text-md: #374151; --muted: #6B7280; --bg: #F3F4F6;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }
body { font-family: 'Barlow', sans-serif; background: var(--bg); display: flex; min-height: 100vh; color: var(--text); }

/* ═══ SIDEBAR ═══ */
.sidebar { width: var(--sidebar-w);
    background: var(--sidebar);
    height: 100vh;
    position: fixed;
    top: 0;
    left: 0;
    display: flex;
    flex-direction: column;
    padding: 28px 18px;
    border-right: 1px solid rgba(255, 255, 255, 0.04);
    z-index: 200;
    overflow-y: auto;

    /* Sembunyikan scrollbar untuk Firefox & IE/Edge */
    scrollbar-width: none; 
    -ms-overflow-style: none; 
}
.sidebar::-webkit-scrollbar { display: none; /* Chrome, Safari, Opera */ }
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

/* ═══ MAIN & TOPBAR ═══ */
.main {   /* Trik tumpang-tindih 1px ke kiri untuk melenyapkan celah vertikal */
    margin-left: calc(var(--sidebar-w) - 7px); 
    flex: 1;
    display: flex;
    flex-direction: column;
    min-height: 100vh; }
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
.t-avatar { width: 32px; height: 32px; background: var(--orange); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 13px; }
.t-name { font-size: 13px; font-weight: 800; color: var(--text); line-height: 1.1; text-transform: uppercase; }
.t-role { font-size: 10px; color: var(--orange); font-weight: 700; text-transform: uppercase; }
.t-chevron { color: var(--muted); font-size: 10px; margin-left: 4px; }
.dropdown-menu { display: none; position: absolute; right: 0; top: calc(100% + 8px); background: #fff; min-width: 200px; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 15px 40px rgba(0,0,0,.12); overflow: hidden; padding: 8px 0; z-index: 999; }
.dropdown-wrap:hover .dropdown-menu { display: block; }
.dd-item { display: flex; align-items: center; gap: 10px; padding: 11px 16px; color: #444; text-decoration: none; font-size: 13px; font-weight: 700; transition: .15s; }
.dd-item:hover { background: #FFF7ED; color: var(--orange); }
.dd-item i { font-size: 14px; width: 18px; text-align: center; }
.dd-divider { border: none; border-top: 1px solid #F3F4F6; margin: 4px 0; }

/* ═══ CONTENT ═══ */
.content { padding: 32px 40px; flex: 1; }
.page-header { display: flex; align-items: flex-end; justify-content: space-between; margin-bottom: 24px; flex-wrap: wrap; gap: 16px; }
.page-title-tag { width: 36px; height: 4px; background: var(--orange); border-radius: 2px; margin-bottom: 8px; }
.page-title { font-family: 'Barlow Condensed', sans-serif; font-size: 30px; font-weight: 900; color: var(--text); text-transform: uppercase; }

/* ═══ STAT CHIPS ═══ */
.stat-chips { display: flex; gap: 10px; flex-wrap: wrap; }
.stat-chip { display: flex; align-items: center; gap: 8px; padding: 8px 18px; border-radius: 10px; font-size: 12px; font-weight: 700; transition: all .2s; }
.stat-chip:hover { transform: translateY(-2px); }
.chip-total  { background: var(--bg); color: #374151; border: 1px solid var(--border); }
.chip-blue   { background: var(--blue-lt); color: var(--blue); }
.chip-pink   { background: var(--pink-lt); color: var(--pink); }
.chip-val    { font-family: 'Barlow Condensed'; font-size: 20px; font-weight: 900; }

/* ═══ ACTION BAR ═══ */
.action-bar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; flex-wrap: wrap; gap: 12px; }
.search-box { position: relative; width: 300px; }
.search-box i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: 13px; }
.search-box input { width: 100%; padding: 10px 14px 10px 40px; background: var(--card-bg); border: 1.5px solid var(--border); border-radius: 10px; font-size: 13px; font-family: 'Barlow', sans-serif; outline: none; transition: all .2s; color: var(--text); }
.search-box input:focus { border-color: var(--orange); box-shadow: 0 0 0 3px var(--orange-lt); }
.search-box input::placeholder { color: #9CA3AF; }

/* ═══ CARD & TABLE ═══ */
.card { background: var(--card-bg); border-radius: 16px; border: 1px solid var(--border); overflow: hidden; transition: all .2s ease; }
.card:hover { box-shadow: 0 8px 24px rgba(0,0,0,.06); }
.table-wrap { overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table th { padding: 13px 20px; font-size: 13px; font-weight: 800; color: var(--muted); text-transform: uppercase; letter-spacing: .6px; border-bottom: 2px solid var(--border-lt); text-align: left; }
.data-table td { padding: 16px 20px; font-size: 13px; border-bottom: 1px solid var(--border-lt); vertical-align: middle; transition: background .15s; }
.data-table tbody tr:last-child td { border-bottom: none; }

/* ═══ ZEBRA STRIPING ═══ */
.data-table tbody tr:nth-child(odd) { background-color: #FFF7ED; }
.data-table tbody tr:nth-child(even) { background-color: #FFFFFF; }
.data-table tbody tr:hover td { background: #FFEDD5 !important; }

.cust-id   { color: var(--orange); font-weight: 800; font-family: 'Barlow Condensed'; font-size: 16px; }
.cust-name { font-weight: 700; color: var(--text); font-size: 14px; }

.gender-badge { display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: 20px; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .3px; }
.gb-laki     { background: var(--blue-lt); color: var(--blue); }
.gb-perempuan{ background: var(--pink-lt); color: var(--pink); }

/* ═══ AUDIT INFO ═══ */
.audit-info { font-size: 10px; color: var(--muted); font-weight: 600; }
.audit-info i { margin-right: 3px; }
.audit-label { font-size: 9px; text-transform: uppercase; letter-spacing: .5px; color: #9CA3AF; font-weight: 800; }
.audit-value { color: var(--text-md); font-weight: 700; }
.audit-date { font-family: monospace; font-size: 10px; }

/* ═══ ACTIONS ═══ */
.actions { display: flex; gap: 6px; justify-content: flex-end; }
.btn-action {
    display: inline-flex; align-items: center; justify-content: center; gap: 6px;
    padding: 8px 14px; border-radius: 10px; font-size: 12px; font-weight: 700;
    font-family: 'Barlow', sans-serif; text-decoration: none; cursor: pointer;
    transition: all .25s cubic-bezier(.4,0,.2,1); border: 1.5px solid transparent; letter-spacing: .3px;
}
.btn-view {
    background: linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%); color: #1E40AF; border-color: #BFDBFE;
}
.btn-view:hover {
    background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%); color: #fff; border-color: #3B82F6;
    transform: translateY(-2px); box-shadow: 0 6px 20px rgba(59,130,246,.35);
}
.btn-delete {
    background: linear-gradient(135deg, #FEF2F2 0%, #FEE2E2 100%); color: #DC2626; border-color: #FECACA;
}
.btn-delete:hover {
    background: linear-gradient(135deg, #EF4444 0%, #DC2626 100%); color: #fff; border-color: #EF4444;
    transform: translateY(-2px); box-shadow: 0 6px 20px rgba(239,68,68,.35);
}

/* ═══ PAGINATION ═══ */
.pagination-wrap { background: var(--card-bg); border: 1px solid var(--border); border-top: none; border-radius: 0 0 16px 16px; padding: 16px 24px; display: flex; align-items: center; justify-content: space-between; margin-bottom: 32px; }
.pagination-info { font-size: 12px; color: var(--muted); font-weight: 600; }
.pagination-info strong { color: var(--text); font-weight: 800; }
.pagination-nav { display: flex; align-items: center; gap: 4px; }
.page-btn { display: inline-flex; align-items: center; justify-content: center; min-width: 36px; height: 36px; padding: 0 10px; border-radius: 10px; font-size: 13px; font-weight: 700; font-family: 'Barlow', sans-serif; text-decoration: none; cursor: pointer; transition: all .2s ease; border: 1.5px solid var(--border); color: var(--text-md); background: #fff; }
.page-btn:hover:not(.disabled):not(.active) { border-color: var(--orange); color: var(--orange); background: var(--orange-lt); transform: translateY(-1px); }
.page-btn.active { background: var(--orange); color: #fff; border-color: var(--orange); box-shadow: 0 4px 12px rgba(255,69,0,.3); font-weight: 800; }
.page-btn.disabled { opacity: 0.4; cursor: not-allowed; pointer-events: none; }
.page-btn i { font-size: 11px; }
.page-ellipsis { display: inline-flex; align-items: center; justify-content: center; min-width: 36px; height: 36px; color: var(--muted); font-size: 13px; font-weight: 800; }

/* ═══ EMPTY STATE ═══ */
.empty-state { text-align: center; padding: 50px 20px; color: var(--muted); }
.empty-state i { font-size: 48px; margin-bottom: 16px; opacity: .3; display: block; }
.empty-state div { font-size: 14px; font-weight: 700; }


/* ═══ ZEBRA STRIPING ═══ */
.data-table tbody tr:nth-child(odd) { background-color: #FFF7ED; }
.data-table tbody tr:nth-child(even) { background-color: #FFFFFF; }
.data-table tbody tr:hover td { background-color: #FFEDD5 !important; }
.data-table tbody tr:nth-child(odd):hover { background-color: #FFEDD5; }
.data-table tbody tr:nth-child(even):hover { background-color: #FFEDD5; }


#clock-display { 
    display: flex; 
    align-items: center; 
    gap: 16px; 
}

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

.clock-colon { 
    color: var(--orange); 
    opacity: .5; 
    animation: blink 1s infinite; 
}

@keyframes blink { 
    0%, 100% { opacity: .5; } 
    50% { opacity: 1; } 
}

.clock-divider { 
    width: 1.5px; 
    height: 28px; 
    background-color: var(--border); 
}

.clock-date { 
    font-family: 'Barlow', sans-serif; 
    font-size: 13px; 
    font-weight: 700; 
    color: var(--muted); 
    text-transform: uppercase; 
    letter-spacing: 0.5px; 
}

.sidebar::-webkit-scrollbar { 
    display: none; 
}

html {
    scrollbar-width: none; /* Firefox */
    -ms-overflow-style: none; /* IE and Edge microost microsot*/
}

html::-webkit-scrollbar {
    display: none; /* Chrome, Safari, Opera */
}

/* ═══ RESPONSIVE ═══ */
@media(max-width: 1100px) { .page-header { flex-direction: column; align-items: flex-start; } }
@media(max-width: 768px) {
    .sidebar { width: 0; overflow: hidden; padding: 0; }
    .main { margin-left: 0; }
    .content { padding: 20px; }
    .topbar { padding: 0 20px; }
    .stat-chips { width: 100%; }
    .search-box { width: 100%; }
    .action-bar { flex-direction: column; align-items: stretch; }
    .data-table th, .data-table td { padding: 12px 16px; font-size: 12px; }
    .btn-action { padding: 6px 10px; font-size: 11px; }
    .pagination-wrap { flex-direction: column; gap: 12px; }
}
/* ═══ MODAL DETAIL ═══ */
.modal-overlay {
    display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0,0,0,.5); backdrop-filter: blur(4px); z-index: 1000;
    align-items: center; justify-content: center; padding: 20px;
    animation: fadeIn .2s ease;
}
.modal-overlay.active { display: flex; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

.modal-box {
    background: var(--card-bg); border-radius: 16px; border: 1px solid var(--border);
    width: 100%; max-width: 680px; max-height: 90vh; overflow-y: auto;
    box-shadow: 0 25px 50px rgba(0,0,0,.15); animation: slideUp .3s ease;
}
@keyframes slideUp { from { transform: translateY(30px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

.modal-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 24px 28px; border-bottom: 1px solid var(--border-lt);
}
.modal-header-left { display: flex; align-items: center; gap: 14px; }
.modal-avatar {
    width: 56px; height: 56px; background: linear-gradient(135deg, var(--orange) 0%, var(--orange-dk) 100%);
    border-radius: 12px; display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: 24px; flex-shrink: 0;
}
.modal-header-info { display: flex; flex-direction: column; }
.modal-name { font-family: 'Barlow Condensed', sans-serif; font-size: 22px; font-weight: 900; color: var(--text); }
.modal-id {
    display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 700;
    color: var(--orange); background: var(--orange-lt); padding: 3px 10px; border-radius: 6px; margin-top: 4px; width: fit-content;
}
.modal-close {
    width: 36px; height: 36px; border-radius: 10px; background: var(--bg); border: 1.5px solid var(--border);
    color: var(--muted); display: flex; align-items: center; justify-content: center; cursor: pointer;
    transition: all .2s; font-size: 14px;
}
.modal-close:hover { background: var(--red-lt); color: var(--red); border-color: var(--red); }

.modal-body { padding: 0; }
.modal-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 0; }
.modal-item { padding: 16px 24px; border-bottom: 1px solid var(--border-lt); border-right: 1px solid var(--border-lt); }
.modal-item:nth-child(2n) { border-right: none; }
.modal-item:nth-last-child(-n+2) { border-bottom: none; }
.modal-item:nth-last-child(1):nth-child(odd) { border-bottom: none; grid-column: 1 / -1; }

.modal-label {
    display: flex; align-items: center; gap: 6px; font-size: 10px; font-weight: 800;
    text-transform: uppercase; color: var(--muted); letter-spacing: .5px; margin-bottom: 6px;
}
.modal-label i { font-size: 11px; width: 14px; text-align: center; }
.modal-value { font-size: 14px; font-weight: 700; color: var(--text); line-height: 1.4; }
.modal-value-muted { color: var(--muted); font-weight: 600; }

.modal-gender {
    display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 8px;
    font-size: 12px; font-weight: 800; text-transform: uppercase;
}
.mg-laki { background: var(--blue-lt); color: var(--blue); }
.mg-perempuan { background: var(--pink-lt); color: var(--pink); }

.modal-status {
    display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 8px;
    font-size: 12px; font-weight: 800; text-transform: uppercase;
}
.ms-active { background: var(--green-lt); color: var(--green); }
.ms-deleted { background: var(--red-lt); color: var(--red); }

.modal-deleted-banner {
    margin: 16px 24px 0; padding: 12px 16px; background: var(--red-lt); border: 1px solid rgba(239,68,68,.2);
    border-radius: 10px; display: flex; align-items: center; gap: 10px;
}
.modal-deleted-banner i { color: var(--red); font-size: 16px; }
.modal-deleted-text { font-size: 12px; font-weight: 700; color: var(--red); }
.modal-deleted-date { font-size: 11px; color: var(--muted); font-weight: 600; }

.modal-audit { padding: 16px 24px; border-top: 1px solid var(--border-lt); background: #FAFAFA; }
.modal-audit-title {
    display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 800;
    color: var(--text); text-transform: uppercase; margin-bottom: 12px;
}
.modal-audit-title i { color: var(--orange); font-size: 14px; }
.modal-audit-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
.modal-audit-item { display: flex; flex-direction: column; gap: 4px; }
.modal-audit-label { font-size: 9px; font-weight: 800; text-transform: uppercase; color: var(--muted); letter-spacing: .4px; }
.modal-audit-value { font-size: 12px; font-weight: 700; color: var(--text-md); }
.modal-audit-date { font-family: monospace; font-size: 10px; color: var(--muted); }
.modal-audit-empty { color: #9CA3AF; font-style: italic; font-weight: 500; }

.modal-footer {
    padding: 16px 24px; border-top: 1px solid var(--border-lt); display: flex; gap: 10px; justify-content: flex-end;
}
.btn-modal {
    display: inline-flex; align-items: center; gap: 6px; padding: 10px 18px; border-radius: 10px;
    font-size: 12px; font-weight: 700; font-family: 'Barlow', sans-serif; cursor: pointer;
    transition: all .2s; border: 1.5px solid transparent; text-decoration: none;
}
.btn-modal-close { background: var(--bg); color: var(--text-md); border-color: var(--border); }
.btn-modal-close:hover { background: var(--text); color: #fff; border-color: var(--text); }
.btn-modal-edit { background: var(--blue-lt); color: var(--blue); border-color: #BFDBFE; }
.btn-modal-edit:hover { background: var(--blue); color: #fff; border-color: var(--blue); }

@media(max-width: 640px) {
    .modal-grid { grid-template-columns: 1fr; }
    .modal-item { border-right: none; }
    .modal-item:nth-last-child(1):nth-child(odd) { grid-column: auto; }
    .modal-audit-grid { grid-template-columns: 1fr; }
    .modal-box { max-height: 95vh; }
}
</style>
</head>
<body>

<!-- ═══ SIDEBAR CUSTOMER (SAMA DENGAN LAPANGAN.PHP & JALUR RELATIF DISESUAIKAN) ═══ -->
<aside class="sidebar">
    <a href="../dashboard_karyawan.php" class="sb-brand">
        <div class="sb-icon"><i class="fa-solid fa-basketball"></i></div>
        <div>
            <div class="sb-brand-name">HOOP BALL</div>
            <div class="sb-brand-sub">MANAGEMENT SYSTEM</div>
        </div>
    </a>

    <!-- SEKSI 1: MENU UTAMA -->
    <div class="sb-section-label">Menu Utama</div>
    <nav>
        <a href="../view_admin.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-house"></i></div>
            Dashboard
        </a>
        <a href="../booking.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-calendar-check"></i></div>
            Booking
        </a>
        <a href="lapangan.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-layer-group"></i></div>
            Lapangan
        </a>
        <!-- DISET AKTIF: Menu Customer disorot aktif khusus untuk halaman customer.php ini -->
        <a href="customer.php" class="sb-link active">
            <div class="sb-icon-wrap"><i class="fa-solid fa-users"></i></div>
            Customer
        </a>
        <a href="promo.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-tag"></i></div>
            Promo
        </a>
    </nav>

    <!-- SEKSI 2: AKUN -->
    <div class="sb-section-label">Akun</div>
    <nav>
        <a href="../profile.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-id-badge"></i></div>
            Profil Saya
        </a>
        <a href="../riwayat.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-clock-rotate-left"></i></div>
            Riwayat
        </a>
    </nav>

    <!-- BAGIAN BAWAH: USER BAR SITI / KARYAWAN -->
    <div class="sb-bottom">
        <div class="sb-user">
            <div class="sb-avatar"><i class="fa-solid fa-user"></i></div>
            <div>
                <div class="sb-user-name"><?= strtoupper(htmlspecialchars($nama)) ?></div>
                <div class="sb-user-role">KARYAWAN</div>
            </div>
            <a href="../logout.php" class="sb-logout" title="Keluar"><i class="fa-solid fa-right-from-bracket"></i></a>
        </div>
    </div>
</aside>

<!-- ═══ MAIN & TOPBAR ═══ -->
<<main class="main">
<!-- ═══ TOPBAR DATA CUSTOMER SINKRON DENGAN VIEW_ADMIN ═══ -->
    <header class="topbar">
        <div class="topbar-left">
            <div class="topbar-title">Data Customer</div>
            <div class="topbar-breadcrumb">Manajemen / Data Customer</div>
        </div>
        <div class="topbar-right">
            <!-- Jam Digital Live Persis Seperti di Gambar -->
            <div id="clock-display">
                <div class="clock-time">
                    <span id="h">00</span><span class="clock-colon">:</span><span id="m">00</span><span class="clock-colon">:</span><span id="s">00</span>
                </div>
                <div class="clock-divider"></div>
                <div class="clock-date" id="full-date">MEMUAT...</div>
            </div>
            
            <a href="#" class="topbar-btn"><i class="fa-solid fa-magnifying-glass"></i></a>
            
            <a href="#" class="topbar-btn">
                <i class="fa-solid fa-bell"></i>
                <!-- Notifikasi Dinamis dari database -->
                <?php if(isset($total_pending) && $total_pending > 0): ?><span class="notif-dot"></span><?php endif; ?>
            </a>
            
            <div class="dropdown-wrap">
                <div class="topbar-user">
                    <div class="t-avatar"><i class="fa-solid fa-user"></i></div>
                    <div>
                        <div class="t-name"><?= strtoupper(htmlspecialchars($nama)) ?></div>
                        <div class="t-role"><?= strtoupper(htmlspecialchars($role)) ?></div>
                    </div>
                    <i class="fa-solid fa-chevron-down t-chevron"></i>
                </div>
                <div class="dropdown-menu">
                    <!-- Tautan ../ tetap dipertahankan karena file customer.php ini berada di dalam subfolder master/ -->
                    <a href="../profile.php" class="dd-item"><i class="fa-solid fa-id-badge"></i> Profil Saya</a>
                    <hr class="dd-divider">
                    <a href="../logout.php" class="dd-item" style="color:var(--red);"><i class="fa-solid fa-right-from-bracket"></i> Keluar</a>
                </div>
            </div>
        </div>
    </header>

    <div class="content">
        <!-- PAGE HEADER -->
        <div class="page-header">
            <div>
                <div class="page-title-tag"></div>
                <div class="page-title">Data Customer</div>
            </div>
            <div class="stat-chips">
                <div class="stat-chip chip-total">
                    <i class="fa-solid fa-users"></i> TOTAL <span class="chip-val"><?= $total_cust ?></span>
                </div>
                <div class="stat-chip chip-blue">
                    <i class="fa-solid fa-mars"></i> LAKI-LAKI <span class="chip-val"><?= $total_laki ?></span>
                </div>
                <div class="stat-chip chip-pink">
                    <i class="fa-solid fa-venus"></i> PEREMPUAN <span class="chip-val"><?= $total_perempuan ?></span>
                </div>
            </div>
        </div>

        <!-- ACTION BAR -->
        <div class="action-bar">
            <div class="search-box">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="src" placeholder="Cari nama customer..." onkeyup="searchTable()">
            </div>
        </div>

        <!-- TABLE CARD -->
        <?php if ($query_error): ?><div style="padding:20px;background:#fee;border:1px solid #fcc;border-radius:8px;margin:20px 0;"><p style="color:#c00;font-weight:bold;margin:0;"><i class="fa-solid fa-circle-exclamation"></i> Gagal mengambil data dari database. Silakan refresh halaman atau hubungi administrator.</p><p style="color:#666;font-size:11px;margin:5px 0 0;">Error: <?php echo htmlspecialchars($query_error_msg); ?></p></div><?php else: ?><div class="card">
            <div class="table-wrap">
                <table class="data-table" id="tbl">
                    <thead>
                        <tr>
                            <th>ID Customer</th>
                            <th>Nama Lengkap</th>
                            <th>Jenis Kelamin</th>
                            <th>Alamat</th>
                            <th>No. Telepon</th>
                            <th>Audit Info</th>
                            <th style="text-align:right;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $has_data = false;
                    if (!$query_error && $query):
                    while ($row = safe_sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)):
                        $has_data = true;
                        $is_laki = ($row['Jenis_Kelamin'] == 1);
                    ?>
                        <tr>
                            <td class="cust-id"><?= htmlspecialchars($row['ID_Customer']) ?></td>
                            <td>
                                <div class="cust-name"><?= htmlspecialchars($row['Nama_Customer']) ?></div>
                            </td>
                            <td>
                                <span class="gender-badge <?= $is_laki ? 'gb-laki' : 'gb-perempuan' ?>">
                                    <i class="fa-solid <?= $is_laki ? 'fa-mars' : 'fa-venus' ?>"></i>
                                    <?= $map_jk[$row['Jenis_Kelamin']] ?? '-' ?>
                                </span>
                            </td>
                            <td style="color:var(--muted); max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= htmlspecialchars($row['Alamat']) ?>">
                                <?= htmlspecialchars($row['Alamat']) ?>
                            </td>
                            <td style="color:var(--muted); font-weight: 600;">
                                <?= htmlspecialchars($row['No_Telepon']) ?>
                            </td>
                            <td>
                                <div class="audit-info">
                                    <div class="audit-label"><i class="fa-solid fa-user-pen"></i> Dibuat</div>
                                    <div class="audit-value"><?= htmlspecialchars($row['Created_By'] ?? 'SYSTEM') ?></div>
                                    <div class="audit-date"><?= $row['Created_Date'] ? $row['Created_Date']->format('d/m/Y H:i') : '-' ?></div>
                                    <?php if (!empty($row['Modified_By'])): ?>
                                    <div class="audit-label" style="margin-top:4px;"><i class="fa-solid fa-pen-to-square"></i> Diubah</div>
                                    <div class="audit-value"><?= htmlspecialchars($row['Modified_By']) ?></div>
                                    <div class="audit-date"><?= $row['Modified_Date'] ? $row['Modified_Date']->format('d/m/Y H:i') : '-' ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($row['Deleted_By'])): ?>
                                    <div class="audit-label" style="margin-top:4px; color:var(--red);"><i class="fa-solid fa-trash"></i> Dihapus</div>
                                    <div class="audit-value" style="color:var(--red);"><?= htmlspecialchars($row['Deleted_By']) ?></div>
                                    <div class="audit-date"><?= $row['Deleted_Date'] ? $row['Deleted_Date']->format('d/m/Y H:i') : '-' ?></div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="actions">
                                    <a href="customer_detail.php?id=<?= $row['ID_Customer'] ?>" class="btn-action btn-view" title="Lihat Detail">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                    <button onclick="confirmDelete('<?= $row['ID_Customer'] ?>', '<?= htmlspecialchars($row['Nama_Customer']) ?>')" class="btn-action btn-delete" title="Hapus Data">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; endif; ?>

                    <?php if (!$has_data): ?>
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <i class="fa-solid fa-users"></i>
                                    <div>Belum ada data customer</div>
                                    <div style="font-size: 12px; font-weight: 500; margin-top: 8px; opacity: .7;">Data customer akan muncul di sini setelah registrasi</div>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div><?php endif; ?>

        <!-- PAGINATION -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination-wrap">
            <div class="pagination-info">
                Menampilkan <strong><?= (($page - 1) * $limit) + 1 ?></strong> - 
                <strong><?= min($page * $limit, $total_cust) ?></strong> dari 
                <strong><?= $total_cust ?></strong> data
            </div>
            <div class="pagination-nav">
                <a href="?page=1" class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>" title="Halaman Pertama">
                    <i class="fa-solid fa-angles-left"></i>
                </a>
                <a href="?page=<?= $page - 1 ?>" class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>" title="Halaman Sebelumnya">
                    <i class="fa-solid fa-angle-left"></i>
                </a>

                <?php 
                $start_page = max(1, $page - 2); 
                $end_page = min($total_pages, $page + 2); 
                if ($end_page - $start_page < 4 && $total_pages >= 5) { 
                    if ($start_page == 1) { 
                        $end_page = min(5, $total_pages); 
                    } else { 
                        $start_page = max(1, $total_pages - 4); 
                    } 
                } 
                if ($start_page > 1): 
                ?>
                    <a href="?page=1" class="page-btn">1</a>
                    <?php if ($start_page > 2): ?><span class="page-ellipsis">...</span><?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <a href="?page=<?= $i ?>" class="page-btn <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>

                <?php if ($end_page < $total_pages): ?>
                    <?php if ($end_page < $total_pages - 1): ?><span class="page-ellipsis">...</span><?php endif; ?>
                    <a href="?page=<?= $total_pages ?>" class="page-btn"><?= $total_pages ?></a>
                <?php endif; ?>

                <a href="?page=<?= $page + 1 ?>" class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>" title="Halaman Selanjutnya">
                    <i class="fa-solid fa-angle-right"></i>
                </a>
                <a href="?page=<?= $total_pages ?>" class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>" title="Halaman Terakhir">
                    <i class="fa-solid fa-angles-right"></i>
                </a>
            </div>
        </div>
        <?php else: ?>
        <div class="pagination-wrap">
            <div class="pagination-info">
                Menampilkan <strong>1</strong> - <strong><?= $total_cust ?></strong> dari <strong><?= $total_cust ?></strong> data
            </div>
        </div>
        <?php endif; ?>
    </div>
</main>

<script>
function searchTable() {
    var input = document.getElementById('src').value.toUpperCase();
    var rows = document.getElementById('tbl').getElementsByTagName('tr');
    var hasMatch = false;

    for (var i = 1; i < rows.length; i++) {
        var tdName = rows[i].getElementsByTagName('td')[1];
        var tdId = rows[i].getElementsByTagName('td')[0];
        var tdPhone = rows[i].getElementsByTagName('td')[4];

        if (tdName || tdId || tdPhone) {
            var match = false;
            if (tdName && tdName.textContent.toUpperCase().indexOf(input) > -1) match = true;
            if (tdId && tdId.textContent.toUpperCase().indexOf(input) > -1) match = true;
            if (tdPhone && tdPhone.textContent.toUpperCase().indexOf(input) > -1) match = true;

            rows[i].style.display = match ? '' : 'none';
            if (match) hasMatch = true;
        }
    }
}

function confirmDelete(id, name) {
    Swal.fire({
        title: 'Hapus Customer?',
        html: `Anda akan menghapus data <strong style="color:var(--orange);">${name}</strong><br>Data akan dihapus <strong style="color:var(--red);">permanen</strong> dan tidak bisa dikembalikan!`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#EF4444',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = '?delete_id=' + id;
        }
    });
}

// SweetAlert untuk URL params
const urlParams = new URLSearchParams(window.location.search);
const status = urlParams.get('status');
const msg = urlParams.get('msg');

// TAMBAHKAN FUNGSI JAM DIGITAL INI DI DALAM TAG SCRIPT PALING BAWAH
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

if (status && msg) {
    Swal.fire({
        icon: status === 'success' ? 'success' : 'error',
        title: status === 'success' ? 'Berhasil!' : 'Gagal!',
        text: msg,
        timer: 3000,
        showConfirmButton: false,
        toast: true,
        position: 'top-end',
        timerProgressBar: true
    });
    window.history.replaceState({}, document.title, window.location.pathname);
}
</script>

<!-- ═══ MODAL DETAIL CUSTOMER ═══ -->
<div class="modal-overlay" id="modalDetail" onclick="closeModal(event)">
    <div class="modal-box" onclick="event.stopPropagation()">
        <div class="modal-header">
            <div class="modal-header-left">
                <div class="modal-avatar"><i class="fa-solid fa-user"></i></div>
                <div class="modal-header-info">
                    <div class="modal-name" id="mdlNama">-</div>
                    <div class="modal-id"><i class="fa-solid fa-fingerprint"></i> <span id="mdlId">-</span></div>
                </div>
            </div>
            <button class="modal-close" onclick="closeModal()" title="Tutup"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <div class="modal-body">
            <!-- Deleted Banner -->
            <div class="modal-deleted-banner" id="mdlDeletedBanner" style="display:none;">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <div>
                    <div class="modal-deleted-text">Data telah dihapus (soft delete)</div>
                    <div class="modal-deleted-date" id="mdlDeletedInfo">-</div>
                </div>
            </div>

            <div class="modal-grid">
                <div class="modal-item">
                    <div class="modal-label"><i class="fa-solid fa-user" style="color:var(--orange);"></i> Nama Lengkap</div>
                    <div class="modal-value" id="mdlNama2">-</div>
                </div>
                <div class="modal-item">
                    <div class="modal-label"><i class="fa-solid fa-venus-mars" style="color:var(--purple);"></i> Jenis Kelamin</div>
                    <div class="modal-value" id="mdlJK">-</div>
                </div>
                <div class="modal-item">
                    <div class="modal-label"><i class="fa-solid fa-cake-candles" style="color:var(--pink);"></i> Tanggal Lahir</div>
                    <div class="modal-value" id="mdlTglLahir">-</div>
                </div>
                <div class="modal-item">
                    <div class="modal-label"><i class="fa-solid fa-location-dot" style="color:var(--red);"></i> Tempat Lahir</div>
                    <div class="modal-value" id="mdlTempatLahir">-</div>
                </div>
                <div class="modal-item">
                    <div class="modal-label"><i class="fa-solid fa-map-location-dot" style="color:var(--green);"></i> Alamat</div>
                    <div class="modal-value" id="mdlAlamat">-</div>
                </div>
                <div class="modal-item">
                    <div class="modal-label"><i class="fa-solid fa-phone" style="color:var(--blue);"></i> No. Telepon</div>
                    <div class="modal-value" id="mdlTelepon">-</div>
                </div>
                <div class="modal-item">
                    <div class="modal-label"><i class="fa-solid fa-shield-halved" style="color:var(--yellow);"></i> Status</div>
                    <div class="modal-value" id="mdlStatus">-</div>
                </div>
            </div>

            <div class="modal-audit">
                <div class="modal-audit-title"><i class="fa-solid fa-clock-rotate-left"></i> Informasi Audit</div>
                <div class="modal-audit-grid">
                    <div class="modal-audit-item">
                        <div class="modal-audit-label"><i class="fa-solid fa-user-plus" style="color:var(--green);"></i> Dibuat</div>
                        <div class="modal-audit-value" id="mdlCreatedBy">-</div>
                        <div class="modal-audit-date" id="mdlCreatedDate">-</div>
                    </div>
                    <div class="modal-audit-item">
                        <div class="modal-audit-label"><i class="fa-solid fa-user-pen" style="color:var(--blue);"></i> Diubah</div>
                        <div class="modal-audit-value" id="mdlModifiedBy">-</div>
                        <div class="modal-audit-date" id="mdlModifiedDate">-</div>
                    </div>
                    <div class="modal-audit-item">
                        <div class="modal-audit-label"><i class="fa-solid fa-user-xmark" style="color:var(--red);"></i> Dihapus</div>
                        <div class="modal-audit-value" id="mdlDeletedBy">-</div>
                        <div class="modal-audit-date" id="mdlDeletedDate">-</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal-footer">
            <button class="btn-modal btn-modal-close" onclick="closeModal()"><i class="fa-solid fa-xmark"></i> Tutup</button>
            <a href="#" class="btn-modal btn-modal-edit" id="mdlEditLink"><i class="fa-solid fa-pen-to-square"></i> Edit</a>
        </div>
    </div>
</div>

</body>
</html>