<?php
session_start();
include '../includes/config.php';

if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'karyawan' && $_SESSION['role'] !== 'pemilik')) {
    echo "<script>alert('Akses Ditolak!'); window.location='../dashboard.php';</script>";
    exit();
}
$role = $_SESSION['role'];
$nama = $_SESSION['nama'];

// --- PROSES CRUD ---
if (isset($_POST['save_lapangan'])) {
    $id   = $_POST['id_lap'];
    $nama_lapangan = $_POST['nama_arena']; 
    $harga = $_POST['harga'];
    
    if (isset($_POST['edit_mode'])) {
        sqlsrv_query($conn, "UPDATE Lapangan SET Nama_Lapangan=?, Harga_Sewa=? WHERE ID_Lapangan=?", array($nama_lapangan, $harga, $id));
        header("Location: lapangan.php?page=1&status=success&msg=Data lapangan berhasil diperbarui!");
    } else {
        $checkID = sqlsrv_query($conn, "SELECT ID_Lapangan FROM Lapangan WHERE ID_Lapangan=?", array($id));
        if (sqlsrv_has_rows($checkID)) { 
            header("Location: lapangan.php?page=1&status=error&msg=ID Lapangan sudah ada!"); 
            exit(); 
        }
        sqlsrv_query($conn, "INSERT INTO Lapangan (ID_Lapangan, Nama_Lapangan, Harga_Sewa, Status) VALUES (?,?,?,1)", array($id, $nama_lapangan, $harga));
        header("Location: lapangan.php?page=1&status=success&msg=Lapangan baru berhasil ditambahkan!");
    }
    exit();
}

if (isset($_GET['toggle_id'])) {
    $s_baru = ($_GET['s'] == 1) ? 0 : 1;
    sqlsrv_query($conn, "UPDATE Lapangan SET Status=? WHERE ID_Lapangan=?", array($s_baru, $_GET['toggle_id']));
    header("Location: lapangan.php?page=1&status=success&msg=Status lapangan berhasil diubah!"); 
    exit();
}

if (isset($_GET['delete_id'])) {
    $stmt = sqlsrv_query($conn, "DELETE FROM Lapangan WHERE ID_Lapangan=?", array($_GET['delete_id']));
    header($stmt ? "Location: lapangan.php?page=1&status=success&msg=Lapangan berhasil dihapus!" : "Location: lapangan.php?page=1&status=error&msg=Gagal hapus, data masih terikat!");
    exit();
}

$edit_data = null;
if (isset($_GET['edit_id'])) {
    $r = sqlsrv_query($conn, "SELECT * FROM Lapangan WHERE ID_Lapangan=?", array($_GET['edit_id']));
    $edit_data = sqlsrv_fetch_array($r, SQLSRV_FETCH_ASSOC);
}
$show_add = isset($_GET['add']) && $_GET['add'] == '1';

// --- STATISTIK ---
$q_ready = sqlsrv_query($conn, "SELECT COUNT(*) as t FROM Lapangan WHERE Status=1");
$cnt_ready = sqlsrv_fetch_array($q_ready, SQLSRV_FETCH_ASSOC)['t'] ?? 0;
$q_maint  = sqlsrv_query($conn, "SELECT COUNT(*) as t FROM Lapangan WHERE Status=0");
$cnt_maint = sqlsrv_fetch_array($q_maint, SQLSRV_FETCH_ASSOC)['t'] ?? 0;
$q_total = sqlsrv_query($conn, "SELECT COUNT(*) as t FROM Lapangan");
$total_lapangan = sqlsrv_fetch_array($q_total, SQLSRV_FETCH_ASSOC)['t'] ?? 0;

// --- PAGING CONFIGURATION ---
$limit = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

$total_pages = ceil($total_lapangan / $limit);
$page = min($page, max(1, $total_pages));
$offset = ($page - 1) * $limit;

// Ambil data dengan paging
$query = sqlsrv_query($conn, "SELECT * FROM Lapangan ORDER BY ID_Lapangan ASC OFFSET ? ROWS FETCH NEXT ? ROWS ONLY", array($offset, $limit));

function rupiah($n){ return 'Rp '.number_format($n,0,',','.'); }
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Kelola Lapangan | HoopBall</title>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800;900&family=Barlow:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
:root {
    --orange: #FF4500; --orange-lt: rgba(255,69,0,.10); --orange-dk: #E03E00;
    --green: #10B981; --green-lt: rgba(16,185,129,.10); --green-dk: #059669;
    --blue: #3B82F6; --blue-lt: rgba(59,130,246,.10);
    --purple: #8B5CF6; --purple-lt: rgba(139,92,246,.10);
    --red: #EF4444; --red-lt: rgba(239,68,68,.10); --red-dk: #DC2626;
    --yellow: #F59E0B; --yellow-lt: rgba(245,158,11,.10);
    --sidebar: #0D1117; --sidebar-w: 260px; --topbar-h: 70px;
    --card-bg: #FFFFFF; --border: #E5E7EB; --border-lt: #F3F4F6;
    --text: #111827; --text-md: #374151; --muted: #6B7280; --bg: #F3F4F6;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }
body { font-family: 'Barlow', sans-serif; background: var(--bg); display: flex; min-height: 100vh; color: var(--text); }

/* ═══ SIDEBAR ═══ */
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

/* ═══ MAIN & TOPBAR ═══ */
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
.chip-green { background: var(--green-lt); color: var(--green); }
.chip-red   { background: var(--red-lt); color: var(--red); }
.chip-blue  { background: var(--blue-lt); color: var(--blue); }
.chip-val   { font-family: 'Barlow Condensed'; font-size: 20px; font-weight: 900; }

/* ═══ BUTTON ADD ═══ */
.btn-add { display: inline-flex; align-items: center; gap: 8px; background: var(--text); color: #fff; padding: 11px 22px; border-radius: 10px; font-size: 13px; font-weight: 800; text-decoration: none; text-transform: uppercase; transition: all .2s; border: none; cursor: pointer; }
.btn-add:hover { background: var(--orange); transform: translateY(-2px); box-shadow: 0 8px 20px rgba(255,69,0,.3); }
.btn-add i { font-size: 14px; }

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
.data-table th { padding: 13px 20px; font-size: 10px; font-weight: 800; color: var(--muted); text-transform: uppercase; letter-spacing: .6px; border-bottom: 2px solid var(--border-lt); text-align: left; }
.data-table td { padding: 16px 20px; font-size: 13px; border-bottom: 1px solid var(--border-lt); vertical-align: middle; transition: background .15s; }
.data-table tbody tr:hover td { background: #FAFAFA; }
.data-table tbody tr:last-child td { border-bottom: none; }

.lap-id   { color: var(--orange); font-weight: 800; font-family: 'Barlow Condensed'; font-size: 16px; }
.lap-name { font-weight: 700; color: var(--text); font-size: 14px; }
.lap-price { font-weight: 800; font-family: 'Barlow Condensed'; font-size: 15px; color: var(--text); }

/* ═══ STATUS TOGGLE SWITCH ═══ */
.toggle-switch { position: relative; display: inline-block; width: 44px; height: 24px; cursor: pointer; }
.toggle-switch input { opacity: 0; width: 0; height: 0; }
.toggle-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: var(--red); transition: .3s; border-radius: 24px; }
.toggle-slider::before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .3s; border-radius: 50%; box-shadow: 0 2px 4px rgba(0,0,0,.2); }
.toggle-switch input:checked + .toggle-slider { background-color: var(--green); }
.toggle-switch input:checked + .toggle-slider::before { transform: translateX(20px); }
.toggle-switch:hover .toggle-slider { opacity: .9; }

.status-pill { display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: 20px; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .3px; }
.sp-ready { background: var(--green-lt); color: var(--green); }
.sp-maint { background: var(--red-lt); color: var(--red); }
.sp-dot { width: 7px; height: 7px; border-radius: 50%; display: inline-block; }
.sp-ready .sp-dot { background: var(--green); }
.sp-maint .sp-dot { background: var(--red); }

/* ═══ ACTIONS ═══ */
.actions { display: flex; gap: 6px; justify-content: flex-end; }
.btn-action {
    display: inline-flex; align-items: center; justify-content: center; gap: 6px;
    padding: 8px 14px; border-radius: 10px; font-size: 12px; font-weight: 700;
    font-family: 'Barlow', sans-serif; text-decoration: none; cursor: pointer;
    transition: all .25s cubic-bezier(.4,0,.2,1); border: 1.5px solid transparent; letter-spacing: .3px;
}
.btn-edit {
    background: linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%); color: #1E40AF; border-color: #BFDBFE;
}
.btn-edit:hover {
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

/* ═══ MODAL ═══ */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.55); backdrop-filter: blur(6px); display: none; align-items: center; justify-content: center; z-index: 2000; }
.modal-overlay.open { display: flex; }
.modal-box { background: #fff; border-radius: 20px; width: 480px; overflow: hidden; box-shadow: 0 25px 60px rgba(0,0,0,.2); position: relative; }
.modal-header { padding: 28px 32px 20px; border-bottom: 1px solid var(--border); }
.modal-subtitle { font-size: 10px; font-weight: 800; color: var(--orange); text-transform: uppercase; margin-bottom: 6px; letter-spacing: .8px; }
.modal-title { font-family: 'Barlow Condensed', sans-serif; font-size: 22px; font-weight: 900; color: var(--text); }
.modal-body { padding: 24px 32px 32px; }
.modal-label { display: block; font-size: 11px; font-weight: 800; color: var(--muted); text-transform: uppercase; letter-spacing: .5px; margin-bottom: 8px; }
.modal-label .required { color: var(--red); margin-left: 2px; font-size: 14px; font-weight: 900; }
.modal-input { width: 100%; padding: 12px 14px; border: 1.5px solid var(--border); border-radius: 10px; font-size: 13px; font-family: 'Barlow', sans-serif; margin-bottom: 16px; outline: none; transition: all .2s; color: var(--text); }
.modal-input:focus { border-color: var(--orange); box-shadow: 0 0 0 3px var(--orange-lt); }
.modal-input::placeholder { color: #9CA3AF; }
.modal-input:read-only { background: var(--border-lt); color: var(--muted); cursor: not-allowed; }
.btn-submit { width: 100%; background: var(--orange); color: #fff; border: none; padding: 14px; border-radius: 10px; font-weight: 800; font-size: 13px; cursor: pointer; transition: all .2s; text-transform: uppercase; letter-spacing: .5px; display: flex; align-items: center; justify-content: center; gap: 8px; }
.btn-submit:hover { background: var(--orange-dk); transform: translateY(-1px); box-shadow: 0 8px 20px rgba(255,69,0,.3); }
.btn-cancel { display: block; text-align: center; margin-top: 16px; color: var(--muted); text-decoration: none; font-size: 13px; font-weight: 700; transition: .2s; cursor: pointer; }
.btn-cancel:hover { color: var(--orange); }
.modal-close { position: absolute; top: 20px; right: 20px; width: 36px; height: 36px; border: none; background: var(--border-lt); border-radius: 10px; cursor: pointer; display: flex; align-items: center; justify-content: center; color: var(--muted); font-size: 16px; transition: all .2s; }
.modal-close:hover { background: var(--red-lt); color: var(--red); }

/* ═══ VALIDASI ERROR STATE ═══ */
.modal-input.error {
    border-color: var(--red) !important;
    background-color: #FEF2F2 !important;
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.15) !important;
}
.modal-input.error:focus {
    border-color: var(--red) !important;
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.25) !important;
}
.val-msg {
    font-size: 11px; color: var(--red); font-weight: 600; 
    margin-bottom: 10px; display: none; min-height: 16px;
}
.val-msg.show { display: block; }
.val-msg i { margin-right: 4px; }

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
    .modal-box { width: 90%; margin: 20px; }
}
</style>
</head>
<body>

<!-- ═══ MODAL FORM ═══ -->
<div class="modal-overlay <?= ($edit_data || $show_add) ? 'open' : '' ?>" id="modalLapangan">
    <div class="modal-box">
        <button class="modal-close" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></button>
        <div class="modal-header">
            <div class="modal-subtitle">Master Lapangan</div>
            <div class="modal-title"><?= $edit_data ? 'Edit Lapangan' : 'Tambah Lapangan Baru' ?></div>
        </div>
        <div class="modal-body">
            <form method="POST" id="formLapangan" onsubmit="return validateForm()" novalidate>
                <?php if ($edit_data): ?><input type="hidden" name="edit_mode" value="1"><?php endif; ?>
                
                <label class="modal-label">ID Lapangan <?= !$edit_data ? '<span class="required">*</span>' : '' ?></label>
                <input type="text" name="id_lap" id="id_lap" class="modal-input" 
                    value="<?= htmlspecialchars($edit_data['ID_Lapangan'] ?? '') ?>" 
                    <?= $edit_data ? 'readonly' : 'required' ?> 
                    placeholder="Contoh: LAP001">
                <div class="val-msg" id="val-id_lap"><i class="fa-solid fa-circle-exclamation"></i> ID Lapangan wajib diisi</div>

                <label class="modal-label">Nama Lapangan <span class="required">*</span></label>
                <input type="text" name="nama_arena" id="nama_arena" class="modal-input" 
                    value="<?= htmlspecialchars($edit_data['Nama_Lapangan'] ?? '') ?>" 
                    required minlength="3" maxlength="100" placeholder="Contoh: Basket Indoor Pro">
                <div class="val-msg" id="val-nama_arena"><i class="fa-solid fa-circle-exclamation"></i> Nama minimal 3 karakter</div>

               <label class="modal-label">Harga Sewa (Rp) <span class="required">*</span></label>
                <input type="number" name="harga" id="harga" class="modal-input" 
                    value="<?= (int)($edit_data['Harga_Sewa'] ?? 0) ?>" 
                    required placeholder="200000" min="0">
                <div class="val-msg" id="val-harga"><i class="fa-solid fa-circle-exclamation"></i> Harga wajib diisi (minimal 0)</div>

                <button type="submit" name="save_lapangan" class="btn-submit">
                    <i class="fa-solid fa-<?= $edit_data ? 'floppy-disk' : 'plus' ?>"></i>
                    <?= $edit_data ? 'Simpan Perubahan' : 'Tambah Lapangan' ?>
                </button>
                <a onclick="closeModal()" class="btn-cancel">Batal</a>
            </form>
        </div>
    </div>
</div>

<!-- ═══ SIDEBAR ═══ -->
<<aside class="sidebar">
    <a href="../dashboard.php" class="sb-brand">
        <div class="sb-icon"><i class="fa-solid fa-basketball"></i></div>
        <div>
            <div class="sb-brand-name">HOOP BALL</div>
            <div class="sb-brand-sub">Management System</div>
        </div>
    </a>

    <div class="sb-section-label">Menu Utama</div>
    <nav>
        <a href="../dashboard.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-house"></i></div>
            Dashboard
        </a>
        <a href="../booking.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-calendar-check"></i></div>
            Booking
            <span class="badge">3</span>
        </a>
        <a href="lapangan.php" class="sb-link active">
            <div class="sb-icon-wrap"><i class="fa-solid fa-layer-group"></i></div>
            Lapangan
        </a>
        <a href="customer.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-users"></i></div>
            Customer
        </a>
        <a href="promo.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-tags"></i></div>
            Promo
        </a>
    </nav>

    <div class="sb-section-label">Akun</div>
    <a href="../profile.php" class="sb-link">
        <div class="sb-icon-wrap"><i class="fa-solid fa-id-badge"></i></div>
        Profil Saya
    </a>
    <a href="../riwayat.php" class="sb-link">
        <div class="sb-icon-wrap"><i class="fa-solid fa-clock-rotate-left"></i></div>
        Riwayat
    </a>

    <div class="sb-bottom">
        <div class="sb-user">
            <div class="sb-avatar"><i class="fa-solid fa-user"></i></div>
            <div>
                <div class="sb-user-name"><?= strtoupper(htmlspecialchars($nama)) ?></div>
                <div class="sb-user-role"><?= strtoupper($role) ?></div>
            </div>
            <a href="../logout.php" class="sb-logout" title="Keluar"><i class="fa-solid fa-right-from-bracket"></i></a>
        </div>
    </div>
</aside>

<!-- ═══ MAIN & TOPBAR ═══ -->
<<main class="main">
    <header class="topbar">
        <div class="topbar-left">
            <div class="topbar-title">Kelola Lapangan</div>
            <div class="topbar-breadcrumb">Manajemen / Lapangan</div>
        </div>
        <div class="topbar-right">
            <a href="#" class="topbar-btn"><i class="fa-solid fa-magnifying-glass"></i></a>
            <a href="#" class="topbar-btn">
                <i class="fa-solid fa-bell"></i>
                <span class="notif-dot"></span>
            </a>
            <div class="dropdown-wrap">
                <div class="topbar-user">
                    <div class="t-avatar"><i class="fa-solid fa-user"></i></div>
                    <div>
                        <div class="t-name"><?= strtoupper(htmlspecialchars($nama)) ?></div>
                        <div class="t-role"><?= strtoupper($role) ?></div>
                    </div>
                    <i class="fa-solid fa-chevron-down t-chevron"></i>
                </div>
                <div class="dropdown-menu">
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
                <div class="page-title">Kelola Lapangan</div>
            </div>
            <div class="stat-chips">
                <div class="stat-chip chip-green">
                    <i class="fa-solid fa-circle-check"></i> READY <span class="chip-val"><?= $cnt_ready ?></span>
                </div>
                <div class="stat-chip chip-red">
                    <i class="fa-solid fa-triangle-exclamation"></i> MAINTENANCE <span class="chip-val"><?= $cnt_maint ?></span>
                </div>
                <div class="stat-chip chip-blue">
                    <i class="fa-solid fa-layer-group"></i> TOTAL <span class="chip-val"><?= $total_lapangan ?></span>
                </div>
            </div>
        </div>

        <!-- ACTION BAR -->
        <div class="action-bar">
            <div class="search-box">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="src" placeholder="Cari nama lapangan..." onkeyup="searchTable()">
            </div>
            <a href="lapangan.php?add=1" class="btn-add"><i class="fa-solid fa-plus"></i> Tambah Lapangan</a>
        </div>

        <!-- TABLE CARD -->
        <div class="card">
            <div class="table-wrap">
                <table class="data-table" id="tbl">
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th>ID Lapangan</th>
                            <th>Nama Lapangan</th>
                            <th>Harga Sewa</th>
                            <th style="text-align:right;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $has_data = false;
                    while ($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)):
                        $has_data = true;
                        $is_ready = $row['Status'] == 1;
                    ?>
                        <tr>
                            <td>
                                <span class="status-pill <?= $is_ready ? 'sp-ready' : 'sp-maint' ?>">
                                    <span class="sp-dot"></span>
                                    <?= $is_ready ? 'READY' : 'MAINTENANCE' ?>
                                </span>
                            </td>
                            <td class="lap-id"><?= htmlspecialchars($row['ID_Lapangan']) ?></td>
                            <td class="lap-name"><?= htmlspecialchars($row['Nama_Lapangan']) ?></td>
                            <td class="lap-price"><?= rupiah($row['Harga_Sewa']) ?></td>
                            <td>
                                <div class="actions">
                                    <a href="?edit_id=<?= $row['ID_Lapangan'] ?>" class="btn-action btn-edit" title="Edit Lapangan">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </a>
                                    <label class="toggle-switch" title="<?= $is_ready ? 'Nonaktifkan' : 'Aktifkan' ?> lapangan">
                                        <input type="checkbox" <?= $is_ready ? 'checked' : '' ?> onchange="confirmToggle('<?= $row['ID_Lapangan'] ?>', <?= $row['Status'] ?>)">
                                        <span class="toggle-slider"></span>
                                    </label>
                                    <button onclick="confirmDelete('<?= $row['ID_Lapangan'] ?>', '<?= htmlspecialchars($row['Nama_Lapangan']) ?>')" class="btn-action btn-delete" title="Hapus Lapangan">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    
                    <?php if (!$has_data): ?>
                        <tr>
                            <td colspan="5">
                                <div class="empty-state">
                                    <i class="fa-solid fa-layer-group"></i>
                                    <div>Belum ada data lapangan</div>
                                    <div style="font-size: 12px; font-weight: 500; margin-top: 8px; opacity: .7;">Tambah lapangan baru untuk memulai</div>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- PAGINATION -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination-wrap">
            <div class="pagination-info">
                Menampilkan <strong><?= (($page - 1) * $limit) + 1 ?></strong> - 
                <strong><?= min($page * $limit, $total_lapangan) ?></strong> dari 
                <strong><?= $total_lapangan ?></strong> data
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
                Menampilkan <strong>1</strong> - <strong><?= $total_lapangan ?></strong> dari <strong><?= $total_lapangan ?></strong> data
            </div>
        </div>
        <?php endif; ?>
    </div>
</main>

<script>
function closeModal() { 
    window.location.href = 'lapangan.php'; 
}

function searchTable() {
    var input = document.getElementById('src').value.toUpperCase();
    var rows = document.getElementById('tbl').getElementsByTagName('tr');
    
    for (var i = 1; i < rows.length; i++) {
        var tdName = rows[i].getElementsByTagName('td')[2];
        var tdId = rows[i].getElementsByTagName('td')[1];
        
        if (tdName || tdId) {
            var match = false;
            if (tdName && tdName.textContent.toUpperCase().indexOf(input) > -1) match = true;
            if (tdId && tdId.textContent.toUpperCase().indexOf(input) > -1) match = true;
            
            rows[i].style.display = match ? '' : 'none';
        }
    }
}

function confirmToggle(id, status) {
    const action = status == 1 ? 'nonaktifkan' : 'aktifkan';
    const icon = status == 1 ? 'warning' : 'question';
    
    Swal.fire({
        title: 'Konfirmasi',
        text: `Apakah Anda yakin ingin ${action} lapangan ini?`,
        icon: icon,
        showCancelButton: true,
        confirmButtonColor: '#FF4500',
        cancelButtonColor: '#6B7280',
        confirmButtonText: `Ya, ${action}!`,
        cancelButtonText: 'Batal',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `?toggle_id=${id}&s=${status}`;
        } else {
            var checkbox = document.querySelector(`input[onchange*="confirmToggle('${id}'"]`);
            if (checkbox) checkbox.checked = !checkbox.checked;
        }
    });
}

function confirmDelete(id, name) {
    Swal.fire({
        title: 'Hapus Lapangan?',
        html: `Anda akan menghapus lapangan <strong style="color:var(--orange);">${name}</strong><br>Data akan dihapus <strong style="color:var(--red);">permanen</strong>!`,
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

/* ═══ VALIDASI FORM WAJIB DIISI ═══ */
function validateForm() {
    let valid = true;
    const inputs = document.querySelectorAll('#formLapangan .modal-input[required]');
    
    inputs.forEach(input => {
        const valMsg = document.getElementById('val-' + input.id);
        
        // Reset error dulu
        input.classList.remove('error');
        if (valMsg) valMsg.classList.remove('show');
        
        // Cek kosong
        if (!input.value.trim()) {
            input.classList.add('error');
            if (valMsg) valMsg.classList.add('show');
            valid = false;
            return;
        }
        
        // Validasi khusus harga tidak boleh negatif
        if (input.name === 'harga' && Number(input.value) < 0) {
            input.classList.add('error');
            if (valMsg) valMsg.classList.add('show');
            valid = false;
            return;
        }
        
        // Cek pattern/minlength dll
        if (!input.checkValidity()) {
            input.classList.add('error');
            if (valMsg) valMsg.classList.add('show');
            valid = false;
        }
    });
    
    return valid;
}

// Live validation saat user mengetik & blur
document.addEventListener('DOMContentLoaded', function() {
    const inputs = document.querySelectorAll('#formLapangan .modal-input');
    inputs.forEach(input => {
        input.addEventListener('input', function() {
            const valMsg = document.getElementById('val-' + this.id);
            
            // Hapus error saat user mengetik
            this.classList.remove('error');
            if (valMsg) valMsg.classList.remove('show');
            
            // Cek kembali jika invalid
            if (!this.checkValidity() && this.value !== '') {
                this.classList.add('error');
                if (valMsg) valMsg.classList.add('show');
            }
            
            // Validasi khusus harga negatif
            if (this.name === 'harga' && this.value !== '' && Number(this.value) < 0) {
                this.classList.add('error');
                if (valMsg) valMsg.classList.add('show');
            }
        });
        
        input.addEventListener('blur', function() {
            const valMsg = document.getElementById('val-' + this.id);
            
            if (!this.value.trim() || !this.checkValidity()) {
                this.classList.add('error');
                if (valMsg) valMsg.classList.add('show');
            } else {
                this.classList.remove('error');
                if (valMsg) valMsg.classList.remove('show');
            }
            
            // Cek harga negatif saat blur
            if (this.name === 'harga' && Number(this.value) < 0) {
                this.classList.add('error');
                if (valMsg) valMsg.classList.add('show');
            }
        });
    });
});

// SweetAlert untuk notifikasi URL params
const urlParams = new URLSearchParams(window.location.search);
const status = urlParams.get('status');
const msg = urlParams.get('msg');

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
</body>
</html>