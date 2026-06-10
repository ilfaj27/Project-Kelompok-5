<?php
session_start();
include '../includes/config.php'; 

// 1. PROTEKSI AKSES
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'karyawan' && $_SESSION['role'] !== 'pemilik')) {
    echo "<script>alert('Akses Ditolak!'); window.location='../dashboard.php';</script>";
    exit();
}

$role = $_SESSION['role'];
$nama_user = $_SESSION['nama'];

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

// --- 2. LOGIKA PROSES CRUD PROMO ---
if (isset($_POST['add_promo'])) {
    $tgl_m = $_POST['tgl_m'];
    $tgl_s = $_POST['tgl_s'];
    if (strtotime($tgl_s) < strtotime($tgl_m)) {
        header("Location: promo.php?status=error&msg=Tanggal selesai tidak boleh mendahului tanggal mulai!");
        exit();
    }
    $sql = "INSERT INTO Promo (ID_Promo, Nama_Promo, Diskon, Tanggal_Mulai, Tanggal_Selesai, Status, Is_Deleted, Created_By, Created_Date) VALUES (?, ?, ?, ?, ?, 1, 0, ?, GETDATE())";
    $params = array($_POST['id'], $_POST['nama'], $_POST['diskon'], $tgl_m, $tgl_s, $nama_user);
    $stmt = safe_sqlsrv_query($conn, $sql, $params, false);
    if($stmt) {
        header("Location: promo.php?status=success&msg=Promo baru berhasil didaftarkan!");
    } else {
        header("Location: promo.php?status=error&msg=Gagal menambah promo. Pastikan ID unik!");
    }
    exit();
}

if (isset($_POST['update_promo'])) {
    $tgl_m = $_POST['tgl_m'];
    $tgl_s = $_POST['tgl_s'];
    if (strtotime($tgl_s) < strtotime($tgl_m)) {
        header("Location: promo.php?status=error&msg=Gagal update! Tanggal selesai tidak valid.");
        exit();
    }
    $sql = "UPDATE Promo SET Nama_Promo=?, Diskon=?, Tanggal_Mulai=?, Tanggal_Selesai=?, Modified_By=?, Modified_Date=GETDATE() WHERE ID_Promo=?";
    $params = array($_POST['nama'], $_POST['diskon'], $tgl_m, $tgl_s, $nama_user, $_POST['id']);
    $stmt = safe_sqlsrv_query($conn, $sql, $params, false);
    if($stmt) {
        header("Location: promo.php?status=success&msg=Data promo berhasil diperbarui!");
    } else {
        header("Location: promo.php?status=error&msg=Gagal memperbarui data.");
    }
    exit();
}

if (isset($_GET['delete_id'])) {
    $stmt = safe_sqlsrv_query($conn, "DELETE FROM Promo WHERE ID_Promo = ?", array($_GET['delete_id']), false);
    header($stmt ? "Location: promo.php?status=success&msg=Promo dihapus." : "Location: promo.php?status=error&msg=Gagal hapus!");
    exit();
}

$edit_data = null;
$show_modal = false;
if (isset($_GET['edit_id'])) {
    $res_edit = safe_sqlsrv_query($conn, "SELECT * FROM Promo WHERE ID_Promo = ?", array($_GET['edit_id']), false);
    if ($res_edit) {
        $edit_data = safe_sqlsrv_fetch_array($res_edit, SQLSRV_FETCH_ASSOC);
    }
    $show_modal = true;
}
if (isset($_GET['create']) && $_GET['create'] == '1') {
    $show_modal = true;
}

// STATISTIK
$q_active = safe_sqlsrv_query($conn, "SELECT COUNT(*) as total FROM Promo WHERE Tanggal_Selesai >= CAST(GETDATE() AS DATE)", [], false);
$active_count = 0;
if ($q_active) {
    $row = safe_sqlsrv_fetch_array($q_active, SQLSRV_FETCH_ASSOC);
    $active_count = $row['total'] ?? 0;
}

$q_expired = safe_sqlsrv_query($conn, "SELECT COUNT(*) as total FROM Promo WHERE Tanggal_Selesai < CAST(GETDATE() AS DATE)", [], false);
$expired_count = 0;
if ($q_expired) {
    $row = safe_sqlsrv_fetch_array($q_expired, SQLSRV_FETCH_ASSOC);
    $expired_count = $row['total'] ?? 0;
}

$q_total = safe_sqlsrv_query($conn, "SELECT COUNT(*) as total FROM Promo", [], false);
$total_count = 0;
if ($q_total) {
    $row = safe_sqlsrv_fetch_array($q_total, SQLSRV_FETCH_ASSOC);
    $total_count = $row['total'] ?? 0;
}

// --- PAGING CONFIG ---
$limit = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// Hitung total data
$count_res = safe_sqlsrv_query($conn, "SELECT COUNT(*) as total FROM Promo", [], false);
$total_data = 0;
if ($count_res) {
    $total_row = safe_sqlsrv_fetch_array($count_res, SQLSRV_FETCH_ASSOC);
    $total_data = $total_row['total'] ?? 0;
}
$total_pages = max(1, ceil($total_data / $limit));

// Query dengan paging
$sql_paging = "SELECT * FROM Promo ORDER BY Tanggal_Selesai DESC OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
$query = sqlsrv_query($conn, $sql_paging, array($offset, $limit));

// PASTIKAN $query_error SELALU TERDEFINISI
$query_error = false;
$query_error_msg = '';
if ($query === false) {
    $query_error = true;
    $errors = sqlsrv_errors();
    if ($errors) {
        foreach ($errors as $error) {
            $query_error_msg .= "[" . $error['SQLSTATE'] . "] " . $error['message'] . " ";
        }
    }
}

$q_pending = sqlsrv_query($conn, "SELECT COUNT(*) as t FROM Booking WHERE Status=1"); // Status 1 = pending
$total_pending = sqlsrv_fetch_array($q_pending, SQLSRV_FETCH_ASSOC)['t'] ?? 0;

?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Master Promo | HoopBall</title>
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
.sidebar { width: var(--sidebar-w); background: var(--sidebar); height: 100vh; position: fixed; top: 0; left: 0; display: flex; flex-direction: column; padding: 28px 18px; border-right: 1px solid rgba(255,255,255,.04); z-index: 200; overflow-y: auto;  scrollbar-width: none; /* Firefox */
    -ms-overflow-style: none;  /* IE and Edge */ }
.sidebar::-webkit-scrollbar { width: 4px; display: none; /* Chrome, Safari, Opera */}
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
.main {  /* Trik tumpang-tindih 1px ke kiri untuk melenyapkan celah vertikal */
    margin-left: calc(var(--sidebar-w) - 6px); 
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

/* ═══ ZEBRA STRIPING ═══ */
.data-table tbody tr:nth-child(odd) td { background: #FFFFFF; }
.data-table tbody tr:nth-child(even) td { background: #FFF7ED; }
.data-table tbody tr:hover td { background: #FFEDD5 !important; }

.promo-id   { color: var(--orange); font-weight: 800; font-family: 'Barlow Condensed'; font-size: 16px; }
.promo-name { font-weight: 700; color: var(--text); font-size: 14px; }
.promo-disc { font-weight: 800; font-family: 'Barlow Condensed'; font-size: 18px; color: var(--orange); }

/* ═══ STATUS PILLS ═══ */
.status-pill { display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: 20px; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .3px; }
.sp-active { background: var(--green-lt); color: var(--green); }
.sp-expired { background: var(--red-lt); color: var(--red); }
.sp-dot { width: 7px; height: 7px; border-radius: 50%; display: inline-block; }
.sp-active .sp-dot { background: var(--green); }
.sp-expired .sp-dot { background: var(--red); }

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
.modal-box { background: #fff; border-radius: 20px; width: 520px; overflow: hidden; box-shadow: 0 25px 60px rgba(0,0,0,.2); position: relative; }
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
.modal-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
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

/* ═══ EMPTY STATE ═══ */
.empty-state { text-align: center; padding: 50px 20px; color: var(--muted); }
.empty-state i { font-size: 48px; margin-bottom: 16px; opacity: .3; display: block; }
.empty-state div { font-size: 14px; font-weight: 700; }

/* ═══ PAGING ═══ */
.pagination-wrap { display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; border-top: 1px solid var(--border-lt); flex-wrap: wrap; gap: 12px; }
.pagination-info { font-size: 12px; color: var(--muted); font-weight: 600; }
.pagination-nav { display: flex; align-items: center; gap: 4px; }
.page-btn { display: inline-flex; align-items: center; justify-content: center; min-width: 36px; height: 36px; padding: 0 12px; border-radius: 10px; border: 1.5px solid var(--border); background: #fff; color: var(--text); font-size: 13px; font-weight: 700; font-family: 'Barlow', sans-serif; text-decoration: none; transition: all .2s; cursor: pointer; }
.page-btn:hover { border-color: var(--orange); color: var(--orange); background: var(--orange-lt); }
.page-btn.active { background: var(--orange); color: #fff; border-color: var(--orange); box-shadow: 0 4px 12px rgba(255,69,0,.3); }
.page-btn.disabled { opacity: .4; cursor: not-allowed; pointer-events: none; }
.page-btn i { font-size: 11px; }
.page-ellipsis { color: var(--muted); font-weight: 700; padding: 0 4px; font-size: 13px; }

/* ═══ ZEBRA STRIPING ═══ */
.data-table tbody tr:nth-child(odd) { background-color: #FFF7ED; }
.data-table tbody tr:nth-child(even) { background-color: #FFFFFF; }
.data-table tbody tr:hover td { background-color: #FFEDD5 !important; }

/* ═══ AUDIT INFO ═══ */
.audit-info { font-size: 10px; color: var(--muted); font-weight: 600; }
.audit-info i { margin-right: 3px; }
.audit-label { font-size: 9px; text-transform: uppercase; letter-spacing: .5px; color: #9CA3AF; font-weight: 800; }
.audit-value { color: var(--text-md); font-weight: 700; }
.audit-date { font-family: monospace; font-size: 10px; }

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
    .modal-box { width: 90%; margin: 20px; }
    .modal-grid-2 { grid-template-columns: 1fr; }
}
</style>
</head>
<body>

<!-- ═══ MODAL FORM PROMO ═══ -->
<div class="modal-overlay <?= $show_modal ? 'open' : '' ?>" id="modalPromo">
    <div class="modal-box">
        <button class="modal-close" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></button>
        <div class="modal-header">
            <div class="modal-subtitle">Master Promo</div>
            <div class="modal-title"><?= $edit_data ? 'Edit Promo' : 'Tambah Promo Baru' ?></div>
        </div>
        <div class="modal-body">
            <form method="POST" id="formPromo" onsubmit="return validateForm()" novalidate>
                <label class="modal-label">ID Promo <?= !$edit_data ? '<span class="required">*</span>' : '' ?></label>
                <input type="text" name="id" id="id" class="modal-input" 
                    placeholder="Contoh: PRM001" 
                    value="<?= htmlspecialchars($edit_data['ID_Promo'] ?? '') ?>" 
                    <?= $edit_data ? 'readonly' : 'required' ?>>
                <div class="val-msg" id="val-id"><i class="fa-solid fa-circle-exclamation"></i> ID Promo wajib diisi</div>
                
                <label class="modal-label">Nama Promo <span class="required">*</span></label>
                <input type="text" name="nama" id="nama" class="modal-input" 
                    placeholder="Masukkan nama promo (misal: Promo Akhir Tahun)" 
                    value="<?= htmlspecialchars($edit_data['Nama_Promo'] ?? '') ?>" 
                    required minlength="3" maxlength="100">
                <div class="val-msg" id="val-nama"><i class="fa-solid fa-circle-exclamation"></i> Nama minimal 3 karakter</div>
                
                <label class="modal-label">Diskon (Rp) <span class="required">*</span></label>
                <input type="number" name="diskon" id="diskon" class="modal-input" 
                    placeholder="10000" min="0"
                    value="<?= $edit_data['Diskon'] ?? '' ?>" required>
                <div class="val-msg" id="val-diskon"><i class="fa-solid fa-circle-exclamation"></i> Diskon minimal 0</div>
                
                <div class="modal-grid-2">
                    <div>
                        <label class="modal-label">Tanggal Mulai <span class="required">*</span></label>
                        <input type="date" name="tgl_m" id="tgl_m" class="modal-input" 
                            value="<?= isset($edit_data['Tanggal_Mulai']) ? $edit_data['Tanggal_Mulai']->format('Y-m-d') : '' ?>" required>
                        <div class="val-msg" id="val-tgl_m"><i class="fa-solid fa-circle-exclamation"></i> Pilih tanggal mulai</div>
                    </div>
                    <div>
                        <label class="modal-label">Tanggal Selesai <span class="required">*</span></label>
                        <input type="date" name="tgl_s" id="tgl_s" class="modal-input" 
                            value="<?= isset($edit_data['Tanggal_Selesai']) ? $edit_data['Tanggal_Selesai']->format('Y-m-d') : '' ?>" required>
                        <div class="val-msg" id="val-tgl_s"><i class="fa-solid fa-circle-exclamation"></i> Pilih tanggal selesai</div>
                    </div>
                </div>
                <!-- Pesan error tanggal -->
                <div class="val-msg" id="val-tanggal" style="grid-column: span 2; margin-top: -8px;"><i class="fa-solid fa-circle-exclamation"></i> Tanggal selesai tidak boleh mendahului tanggal mulai</div>
                
                <button type="submit" name="<?= $edit_data ? 'update_promo' : 'add_promo' ?>" class="btn-submit">
                    <i class="fa-solid fa-<?= $edit_data ? 'floppy-disk' : 'plus' ?>"></i>
                    <?= $edit_data ? 'Simpan Perubahan' : 'Tambah Promo' ?>
                </button>
                <a onclick="closeModal()" class="btn-cancel">Batal</a>
            </form>
        </div>
    </div>
</div>

<!-- ═══ SIDEBAR ═══ -->
<!-- ═══ SIDEBAR PROMO (SAMA PERSIS DENGAN KODE REVISI BARU) ═══ -->
<aside class="sidebar">
    <a href="../view_admin.php" class="sb-brand">
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
        <!-- Tanpa angka bulatan oranye (bersih sesuai permintaan baru Anda) -->
        <a href="../booking.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-calendar-check"></i></div>
            Booking
        </a>
        <a href="lapangan.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-layer-group"></i></div>
            Lapangan
        </a>
        <a href="customer.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-users"></i></div>
            Customer
        </a>
        <!-- DISET AKTIF: Menu Promo disorot aktif khusus untuk halaman promo.php ini -->
        <a href="promo.php" class="sb-link active">
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
                <div class="sb-user-name"><?= strtoupper(htmlspecialchars($nama_user)) ?></div>
                <div class="sb-user-role">KARYAWAN</div>
            </div>
            <a href="../logout.php" class="sb-logout" title="Keluar"><i class="fa-solid fa-right-from-bracket"></i></a>
        </div>
    </div>
</aside>

<!-- ═══ MAIN & TOPBAR ═══ -->
<<main class="main">
   <!-- ═══ TOPBAR MASTER PROMO SINKRON DENGAN VIEW_ADMIN ═══ -->
    <header class="topbar">
        <div class="topbar-left">
            <div class="topbar-title">Master Promo</div>
            <div class="topbar-breadcrumb">Dashboard / Promo</div>
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
                        <div class="t-name"><?= strtoupper(htmlspecialchars($nama_user)) ?></div>
                        <div class="t-role"><?= strtoupper(htmlspecialchars($role)) ?></div>
                    </div>
                    <i class="fa-solid fa-chevron-down t-chevron"></i>
                </div>
                <div class="dropdown-menu">
                    <!-- Tautan ../ tetap dipertahankan karena file ini berada di dalam subfolder master/ -->
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
                <div class="page-title">Daftar Promo</div>
            </div>
            <div class="stat-chips">
                <div class="stat-chip chip-green">
                    <i class="fa-solid fa-circle-check"></i> AKTIF <span class="chip-val"><?= $active_count ?></span>
                </div>
                <div class="stat-chip chip-red">
                    <i class="fa-solid fa-circle-xmark"></i> EXPIRED <span class="chip-val"><?= $expired_count ?></span>
                </div>
                <div class="stat-chip chip-blue">
                    <i class="fa-solid fa-list"></i> TOTAL <span class="chip-val"><?= $total_count ?></span>
                </div>
                <div class="stat-chip" style="background: var(--purple-lt); color: var(--purple);">
                    <i class="fa-solid fa-file-lines"></i> HALAMAN <span class="chip-val"><?= $page ?>/<?= $total_pages ?></span>
                </div>
            </div>
        </div>

        <!-- ACTION BAR -->
        <div class="action-bar">
            <div class="search-box">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="src" placeholder="Cari nama promo..." onkeyup="searchTable()">
            </div>
            <a href="promo.php?create=1" class="btn-add"><i class="fa-solid fa-plus"></i> Tambah Promo</a>
        </div>

        <!-- TABLE CARD -->
        <div class="card">
            <?php if ($query_error): ?><div style="padding:20px;background:#fee;border:1px solid #fcc;border-radius:8px;margin:20px 0;"><p style="color:#c00;font-weight:bold;margin:0;"><i class="fa-solid fa-circle-exclamation"></i> Gagal mengambil data dari database. Silakan refresh halaman atau hubungi administrator.</p><p style="color:#666;font-size:11px;margin:5px 0 0;">Error: <?php echo htmlspecialchars($query_error_msg); ?></p></div><?php else: ?><div class="table-wrap">
                <table class="data-table" id="tbl">
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th>ID Promo</th>
                            <th>Nama Promo</th>
                            <th>Diskon</th>
                            <th>Periode</th>
                            <th>Audit Info</th>
                            <th style="text-align:right;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $has_data = false;
                    $row_num = 0;
                    if (!$query_error && $query):
                    while ($row = safe_sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)):
                        $has_data = true;
                        $row_num++;
                        $is_active = ($row['Tanggal_Selesai'] >= new DateTime());
                    ?>
                        <tr class="row-<?= $row_num % 2 == 1 ? 'odd' : 'even' ?>">
                            <td>
                                <span class="status-pill <?= $is_active ? 'sp-active' : 'sp-expired' ?>">
                                    <span class="sp-dot"></span>
                                    <?= $is_active ? 'AKTIF' : 'EXPIRED' ?>
                                </span>
                            </td>
                            <td class="promo-id"><?= htmlspecialchars($row['ID_Promo']) ?></td>
                            <td class="promo-name"><?= htmlspecialchars($row['Nama_Promo']) ?></td>
                            <td class="promo-disc">Rp <?= number_format((float)$row['Diskon'], 0, ',', '.') ?></td>
                            <td style="font-size: 12px; color: var(--muted);">
                                <i class="fa-regular fa-calendar"></i> 
                                <?= $row['Tanggal_Mulai']->format('d M Y') ?> - 
                                <?= $row['Tanggal_Selesai']->format('d M Y') ?>
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
                                    <a href="?edit_id=<?= $row['ID_Promo'] ?>" class="btn-action btn-edit" title="Edit Promo">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </a>
                                    <button onclick="confirmDelete('<?= $row['ID_Promo'] ?>', '<?= htmlspecialchars($row['Nama_Promo']) ?>')" class="btn-action btn-delete" title="Hapus Promo">
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
                                    <i class="fa-solid fa-tag"></i>
                                    <div>Belum ada data promo</div>
                                    <div style="font-size: 12px; font-weight: 500; margin-top: 8px; opacity: .7;">Tambah promo baru untuk memulai</div>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div><?php endif; ?>

        <!-- PAGING -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination-wrap">
                <div class="pagination-info">
                    Menampilkan <?= (($page - 1) * $limit) + 1 ?> - <?= min($page * $limit, $total_data) ?> dari <?= $total_data ?> data
                </div>
                <div class="pagination-nav">
                    <!-- First -->
                    <a href="?page=1" class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>"><i class="fa-solid fa-angles-left"></i></a>
                    <!-- Prev -->
                    <a href="?page=<?= max(1, $page - 1) ?>" class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>"><i class="fa-solid fa-angle-left"></i></a>

                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);

                    if ($start_page > 1) {
                        echo '<a href="?page=1" class="page-btn">1</a>';
                        if ($start_page > 2) echo '<span class="page-ellipsis">...</span>';
                    }

                    for ($i = $start_page; $i <= $end_page; $i++) {
                        $active = ($i == $page) ? 'active' : '';
                        echo '<a href="?page=' . $i . '" class="page-btn ' . $active . '">' . $i . '</a>';
                    }

                    if ($end_page < $total_pages) {
                        if ($end_page < $total_pages - 1) echo '<span class="page-ellipsis">...</span>';
                        echo '<a href="?page=' . $total_pages . '" class="page-btn">' . $total_pages . '</a>';
                    }
                    ?>

                    <!-- Next -->
                    <a href="?page=<?= min($total_pages, $page + 1) ?>" class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>"><i class="fa-solid fa-angle-right"></i></a>
                    <!-- Last -->
                    <a href="?page=<?= $total_pages ?>" class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>"><i class="fa-solid fa-angles-right"></i></a>
                </div>
        </div>
        <?php endif; ?>
    </div>
</main>

<script>
function closeModal() { 
    window.location.href = 'promo.php'; 
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

/* ═══ VALIDASI FORM WAJIB DIISI ═══ */
function validateForm() {
    let valid = true;
    const inputs = document.querySelectorAll('#formPromo .modal-input[required]');
    
    // Reset semua error terlebih dahulu
    inputs.forEach(input => {
        input.classList.remove('error');
        const valMsg = document.getElementById('val-' + input.id);
        if (valMsg) valMsg.classList.remove('show');
    });
    document.getElementById('val-tanggal').classList.remove('show');
    
    // Validasi per field wajib
    inputs.forEach(input => {
        const valMsg = document.getElementById('val-' + input.id);
        
        if (!input.checkValidity()) {
            input.classList.add('error');
            if (valMsg) valMsg.classList.add('show');
            valid = false;
        }
    });
    
    // Validasi khusus: tanggal selesai harus >= tanggal mulai
    const tglM = document.getElementById('tgl_m').value;
    const tglS = document.getElementById('tgl_s').value;
    if (tglM && tglS && new Date(tglS) < new Date(tglM)) {
        document.getElementById('tgl_m').classList.add('error');
        document.getElementById('tgl_s').classList.add('error');
        document.getElementById('val-tanggal').classList.add('show');
        valid = false;
    }
    
    // Validasi khusus: diskon 0-100
    const diskon = document.getElementById('diskon').value;
    if (diskon !== '' && (Number(diskon) < 0 || Number(diskon) > 100)) {
        document.getElementById('diskon').classList.add('error');
        document.getElementById('val-diskon').classList.add('show');
        valid = false;
    }
    
    return valid;
}

// Live validation saat user mengetik & blur
document.addEventListener('DOMContentLoaded', function() {
    const inputs = document.querySelectorAll('#formPromo .modal-input');
    inputs.forEach(input => {
        input.addEventListener('input', function() {
            const valMsg = document.getElementById('val-' + this.id);
            
            // Hapus error saat user mulai mengetik
            if (this.value !== '') {
                this.classList.remove('error');
                if (valMsg) valMsg.classList.remove('show');
            }
            
            // Cek kembali jika masih invalid
            if (!this.checkValidity() && this.value !== '') {
                this.classList.add('error');
                if (valMsg) valMsg.classList.add('show');
            }
            
            // Cek tanggal real-time
            if (this.id === 'tgl_m' || this.id === 'tgl_s') {
                const tglM = document.getElementById('tgl_m').value;
                const tglS = document.getElementById('tgl_s').value;
                if (tglM && tglS && new Date(tglS) < new Date(tglM)) {
                    document.getElementById('tgl_m').classList.add('error');
                    document.getElementById('tgl_s').classList.add('error');
                    document.getElementById('val-tanggal').classList.add('show');
                } else {
                    document.getElementById('val-tanggal').classList.remove('show');
                    if (document.getElementById('tgl_m').checkValidity()) {
                        document.getElementById('tgl_m').classList.remove('error');
                    }
                    if (document.getElementById('tgl_s').checkValidity()) {
                        document.getElementById('tgl_s').classList.remove('error');
                    }
                }
            }
            
            // Cek diskon real-time
            if (this.id === 'diskon') {
                const val = Number(this.value);
                if (this.value !== '' && val < 0) {
                    this.classList.add('error');
                    if (valMsg) valMsg.classList.add('show');
                }
            }
        });
        
        input.addEventListener('blur', function() {
            const valMsg = document.getElementById('val-' + this.id);
            if (!this.checkValidity()) {
                this.classList.add('error');
                if (valMsg) valMsg.classList.add('show');
            } else {
                this.classList.remove('error');
                if (valMsg) valMsg.classList.remove('show');
            }
        });
    });
});

function confirmDelete(id, name) {
    Swal.fire({
        title: 'Hapus Promo?',
        html: `Anda akan menghapus promo <strong style="color:var(--orange);">${name}</strong><br>Data akan dihapus <strong style="color:var(--red);">permanen</strong>!`,
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

// SweetAlert untuk notifikasi URL params
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

</body>
</html>