<?php
session_start();
include '../includes/config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'pemilik') {
    echo "<script>alert('Akses Ditolak!'); window.location='../dashboard.php';</script>";
    exit();
}
$nama = $_SESSION['nama'];
$role = $_SESSION['role'];

function getProfilePhotoPath() {
    $photo = $_SESSION['Profile_Photo'] ?? '';
    if (empty($photo)) return '';
    $current_dir = dirname($_SERVER['PHP_SELF']);
    if (strpos($current_dir, '/master/') !== false || strpos($current_dir, '/laporan/') !== false) {
        return '../' . $photo;
    }
    return $photo;
}

function getProfilePhotoAbsolutePath() {
    $photo = $_SESSION['Profile_Photo'] ?? '';
    if (empty($photo)) return '';
    return dirname(__DIR__) . '/' . $photo;
}

$profile_photo = getProfilePhotoPath();
$profile_photo_abs = getProfilePhotoAbsolutePath();
if (!empty($profile_photo) && !file_exists($profile_photo_abs)) {
    $profile_photo = '';
}

$map_jabatan = [1=>'Admin Utama', 2=>'Pemilik / Owner', 3=>'Kasir Pembayaran', 4=>'Staf Operasional', 5=>'Keamanan'];

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
    if ($stmt === false || $stmt === null) return false;
    return sqlsrv_fetch_array($stmt, $fetch_type);
}

function safe_sqlsrv_has_rows($stmt) {
    if ($stmt === false || $stmt === null) return false;
    return sqlsrv_has_rows($stmt);
}

if (isset($_POST['add_karyawan'])) {
    $id_kry = $_POST['id_kry']; 
    $nama_kry = $_POST['nama']; 
    $jk = $_POST['jk']; 
    $jabatan = $_POST['jabatan']; 
    $telp = $_POST['telp']; 
    $id_akun = $_POST['id_akun'];
    $created_by = $_SESSION['nama'] ?? 'SYSTEM';

    $checkID = safe_sqlsrv_query($conn, "SELECT ID_Karyawan FROM Karyawan WHERE ID_Karyawan=?", array($id_kry), false);
    if ($checkID && safe_sqlsrv_has_rows($checkID)) { 
        header("Location: karyawan.php?status=error&msg=ID Karyawan sudah terdaftar!"); 
        exit(); 
    }

    $tempat_lahir = $_POST['tempat_lahir'];
    $tanggal_lahir = $_POST['tanggal_lahir'];

    $stmt = safe_sqlsrv_query($conn, 
        "INSERT INTO Karyawan (ID_Karyawan, ID_Akun, Nama_Karyawan, Tempat_Lahir, Tanggal_Lahir, Jenis_Kelamin, Jabatan, No_Telepon, Status, Is_Deleted, Created_By, Created_Date) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Aktif', 0, ?, GETDATE())", 
        array($id_kry, $id_akun, $nama_kry, $tempat_lahir, $tanggal_lahir, $jk, $jabatan, $telp, $created_by), false);

    header($stmt ? "Location: karyawan.php?status=success&msg=Karyawan baru berhasil didaftarkan!" : "Location: karyawan.php?status=error&msg=Gagal menambahkan data!"); 
    exit();
}

if (isset($_POST['update_karyawan'])) {
    $modified_by = $_SESSION['nama'] ?? 'SYSTEM';
    $tempat_lahir = $_POST['tempat_lahir'];
    $tanggal_lahir = $_POST['tanggal_lahir'];

    $stmt = safe_sqlsrv_query($conn, 
        "UPDATE Karyawan SET Nama_Karyawan=?, Tempat_Lahir=?, Tanggal_Lahir=?, Jenis_Kelamin=?, Jabatan=?, No_Telepon=?,
        Modified_By=?, Modified_Date=GETDATE() WHERE ID_Karyawan=?", 
        array($_POST['nama'], $tempat_lahir, $tanggal_lahir, $_POST['jk'], $_POST['jabatan'], $_POST['telp'], $modified_by, $_POST['id_kry']), false);

    header($stmt ? "Location: karyawan.php?status=success&msg=Data staf berhasil diperbarui!" : "Location: karyawan.php?status=error&msg=Gagal memperbarui data!"); 
    exit();
}

if (isset($_GET['delete_id'])) {
    $deleted_by = $_SESSION['nama'] ?? 'SYSTEM';
    $stmt = safe_sqlsrv_query($conn, 
        "UPDATE Karyawan SET Is_Deleted=1, Status='Dihapus',
        Deleted_By=?, Deleted_Date=GETDATE() WHERE ID_Karyawan=?", 
        array($deleted_by, $_GET['delete_id']), false);

    header($stmt ? "Location: karyawan.php?status=success&msg=Data karyawan telah dihapus (soft delete)!" : "Location: karyawan.php?status=error&msg=Gagal hapus, data mungkin masih terikat!"); 
    exit();
}

$edit_data = null;
if (isset($_GET['edit_id'])) {
    $r = safe_sqlsrv_query($conn, "SELECT * FROM Karyawan WHERE ID_Karyawan=? AND Is_Deleted=0", array($_GET['edit_id']), false);
    if ($r) {
        $edit_data = safe_sqlsrv_fetch_array($r, SQLSRV_FETCH_ASSOC);
    }
}
$show_add = isset($_GET['add']) && $_GET['add'] == '1';

$q_akun_bebas = safe_sqlsrv_query($conn, 
    "SELECT A.ID_Akun, A.Email FROM Akun A 
    WHERE A.ID_Akun NOT IN (SELECT ID_Akun FROM Karyawan WHERE ID_Akun IS NOT NULL AND Is_Deleted=0) 
    AND A.Role != 3 AND A.Is_Deleted=0 
    ORDER BY A.ID_Akun ASC", [], false);

$akun_bebas = [];
if ($q_akun_bebas !== false) {
    while ($ak = safe_sqlsrv_fetch_array($q_akun_bebas, SQLSRV_FETCH_ASSOC)) {
        $akun_bebas[] = $ak;
    }
}

// --- FILTER PARAMETERS ---
$filter_jabatan = isset($_GET['filter_jabatan']) ? intval($_GET['filter_jabatan']) : 0;
$filter_jk = isset($_GET['filter_jk']) ? intval($_GET['filter_jk']) : 0;
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'ID_Karyawan';
$sort_order = isset($_GET['sort_order']) ? $_GET['sort_order'] : 'ASC';

// Validasi sort_by untuk keamanan
$allowed_sort = ['ID_Karyawan', 'Nama_Karyawan', 'Jabatan', 'Jenis_Kelamin', 'No_Telepon'];
if (!in_array($sort_by, $allowed_sort)) {
    $sort_by = 'ID_Karyawan';
}
$sort_order = ($sort_order === 'DESC') ? 'DESC' : 'ASC';

// --- BUILD FILTER QUERY ---
$where_conditions = ["Is_Deleted = 0"];
$params = [];

// Filter by jabatan
if ($filter_jabatan > 0) {
    $where_conditions[] = "Jabatan = " . intval($filter_jabatan);
}

// Filter by jenis kelamin
if ($filter_jk > 0) {
    $where_conditions[] = "Jenis_Kelamin = " . intval($filter_jk);
}

$where_clause = implode(" AND ", $where_conditions);

$q_total = safe_sqlsrv_query($conn, "SELECT COUNT(*) as t FROM Karyawan WHERE " . $where_clause, [], false);
$total_kry = 0;
if ($q_total !== false) {
    $row_total = safe_sqlsrv_fetch_array($q_total, SQLSRV_FETCH_ASSOC);
    $total_kry = $row_total['t'] ?? 0;
}

$q_total_akun = safe_sqlsrv_query($conn, "SELECT COUNT(*) as t FROM Akun WHERE Status = 1 AND Is_Deleted=0", [], false);
$total_akun_aktif = 0;
if ($q_total_akun !== false) {
    $row_akun = safe_sqlsrv_fetch_array($q_total_akun, SQLSRV_FETCH_ASSOC);
    $total_akun_aktif = $row_akun['t'] ?? 0;
}

$limit = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

$count_query = safe_sqlsrv_query($conn, "SELECT COUNT(*) as total FROM Karyawan WHERE " . $where_clause, [], false);
$total_rows = 0;
if ($count_query !== false) {
    $count_row = safe_sqlsrv_fetch_array($count_query, SQLSRV_FETCH_ASSOC);
    $total_rows = $count_row['total'] ?? 0;
}

$total_pages = max(1, ceil($total_rows / $limit));
$page = min($page, $total_pages);
$offset = ($page - 1) * $limit;

$query_sql = "SELECT * FROM Karyawan WHERE " . $where_clause . " ORDER BY " . $sort_by . " " . $sort_order . " OFFSET " . intval($offset) . " ROWS FETCH NEXT " . intval($limit) . " ROWS ONLY";
$query = safe_sqlsrv_query($conn, $query_sql, [], false);

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
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Kelola Karyawan | HoopBall</title>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800;900&family=Barlow:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
:root {
    --orange: #FF4500; --orange-lt: rgba(255,69,0,.10); --orange-dk: #E03E00;
    --green: #10B981; --green-lt: rgba(16,185,129,.10);
    --blue: #3B82F6; --blue-lt: rgba(59,130,246,.10);
    --purple: #8B5CF6; --purple-lt: rgba(139,92,246,.10);
    --red: #EF4444; --red-lt: rgba(239,68,68,.10); --red-dk: #DC2626;
    --yellow: #F59E0B; --yellow-lt: rgba(245,158,11,.10);
    --sidebar: #0D1117; --sidebar-w: 260px; --topbar-h: 70px;
    --card-bg: #FFFFFF; --border: #E5E7EB; --border-lt: #F3F4F6;
    --text: #111827; --text-md: #374151; --muted: #6B7280; --bg: #F3F4F6;
    --zebra-orange: #FFF7ED;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }
body { font-family: 'Barlow', sans-serif; background: var(--bg); display: flex; min-height: 100vh; color: var(--text); }

/* ═══ SIDEBAR ═══ */
.sidebar { width: var(--sidebar-w); background: var(--sidebar); height: 100vh; position: fixed; top: 0; left: 0; display: flex; flex-direction: column; padding: 28px 18px; border-right: 1px solid rgba(255,255,255,.04); z-index: 200; overflow-y: auto; scrollbar-width: none; -ms-overflow-style: none; }
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
.sb-bottom { margin-top: auto; padding-top: 20px; }
.sb-user { display: flex; align-items: center; gap: 10px; background: rgba(255,255,255,.04); border-radius: 12px; padding: 12px; border: 1px solid rgba(255,255,255,.06); }
.sb-avatar { width: 36px; height: 36px; background: var(--orange); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 14px; flex-shrink: 0; }
.sb-user-name { font-size: 13px; font-weight: 800; color: #E5E7EB; line-height: 1.1; }
.sb-user-role { font-size: 10px; color: var(--orange); font-weight: 700; text-transform: uppercase; }
.sb-logout { margin-left: auto; color: #4B5563; font-size: 13px; transition: .2s; cursor: pointer; text-decoration: none; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 8px; }
.sb-logout:hover { color: var(--red); background: rgba(239,68,68,.1); }

/* ═══ MAIN & TOPBAR ═══ */
.main { margin-left: calc(var(--sidebar-w) - 1px); flex: 1; display: flex; flex-direction: column; min-height: 100vh; }
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

/* ═══ CLOCK ═══ */
#clock-display { display: flex; align-items: center; gap: 16px; }
.clock-time { font-family: 'Barlow Condensed', sans-serif; font-size: 26px; font-weight: 900; color: var(--orange); display: flex; align-items: center; gap: 6px; line-height: 1; }
.clock-colon { color: var(--orange); opacity: .5; animation: blink 1s infinite; }
@keyframes blink { 0%, 100% { opacity: .5; } 50% { opacity: 1; } }
.clock-divider { width: 1.5px; height: 28px; background-color: var(--border); }
.clock-date { font-family: 'Barlow', sans-serif; font-size: 13px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; }

.content { padding: 32px 40px; flex: 1; }
.page-header { display: flex; align-items: flex-end; justify-content: space-between; margin-bottom: 28px; }
.page-title-tag { width: 36px; height: 4px; background: var(--orange); border-radius: 2px; margin-bottom: 8px; }
.page-title { font-family: 'Barlow Condensed', sans-serif; font-size: 30px; font-weight: 900; color: var(--text); text-transform: uppercase; letter-spacing: -.5px; }

.stat-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 18px; margin-bottom: 28px; }
.stat-card { background: var(--card-bg); border-radius: 16px; padding: 22px 24px; border: 1px solid var(--border); position: relative; overflow: hidden; transition: all .2s ease; }
.stat-card:hover { transform: translateY(-3px); box-shadow: 0 12px 32px rgba(0,0,0,.08); }
.stat-card::before { content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; border-radius: 4px 0 0 4px; }
.sc-purple::before { background: var(--purple); }
.sc-blue::before { background: var(--blue); }
.sc-green::before { background: var(--green); }
.stat-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
.stat-icon-wrap { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 18px; }
.si-purple { background: var(--purple-lt); color: var(--purple); }
.si-blue { background: var(--blue-lt); color: var(--blue); }
.si-green { background: var(--green-lt); color: var(--green); }
.stat-trend { font-size: 11px; font-weight: 800; display: flex; align-items: center; gap: 3px; padding: 4px 8px; border-radius: 20px; }
.trend-up { color: var(--green); background: var(--green-lt); }
.stat-value { font-family: 'Barlow Condensed', sans-serif; font-size: 30px; font-weight: 900; color: var(--text); line-height: 1; margin-bottom: 6px; }
.stat-label { font-size: 12px; color: var(--muted); font-weight: 700; text-transform: uppercase; letter-spacing: .5px; }
.stat-sublabel { font-size: 11px; color: var(--muted); margin-top: 4px; opacity: .7; }

/* ═══ TOOLBAR - SAMA SEPERTI LAPANGAN ═══ */
.toolbar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; flex-wrap: wrap; gap: 12px; }
.toolbar-left { display: flex; align-items: center; gap: 12px; }

/* ═══ FILTER DROPDOWN STYLES ═══ */
.filter-wrap { position: relative; }
.filter-btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; background: var(--orange); color: #fff; border: none; border-radius: 10px; font-size: 13px; font-weight: 700; cursor: pointer; transition: .2s; font-family: 'Barlow', sans-serif; }
.filter-btn:hover { background: var(--orange-dk); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(255,69,0,.3); }
.filter-btn i { font-size: 12px; }
.filter-dropdown { display: none; position: absolute; right: 0; top: calc(100% + 8px); background: #fff; width: 320px; border-radius: 16px; border: 1px solid var(--border); box-shadow: 0 20px 50px rgba(0,0,0,.15); z-index: 1000; overflow: hidden; }
.filter-dropdown.active { display: block; }
.filter-header { padding: 20px 24px 16px; border-bottom: 1px solid var(--border-lt); }
.filter-title { font-size: 16px; font-weight: 800; color: var(--text); }
.filter-body { padding: 20px 24px; display: flex; flex-direction: column; gap: 16px; }
.filter-group { display: flex; flex-direction: column; gap: 6px; }
.filter-label { font-size: 11px; font-weight: 800; color: var(--text); text-transform: uppercase; letter-spacing: .5px; }
.filter-select { padding: 10px 14px; border: 1.5px solid var(--border); border-radius: 10px; font-size: 13px; font-family: 'Barlow', sans-serif; color: var(--text); background: #fff; cursor: pointer; outline: none; transition: .2s; appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236B7280' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 14px center; padding-right: 40px; }
.filter-select:focus { border-color: var(--orange); box-shadow: 0 0 0 3px var(--orange-lt); }
.filter-footer { padding: 16px 24px 20px; border-top: 1px solid var(--border-lt); display: flex; gap: 10px; }
.btn-filter-apply { flex: 1; background: var(--orange); color: #fff; border: none; padding: 10px; border-radius: 10px; font-size: 13px; font-weight: 800; cursor: pointer; transition: .2s; font-family: 'Barlow', sans-serif; }
.btn-filter-apply:hover { background: var(--orange-dk); }
.btn-filter-reset { flex: 1; background: var(--bg); color: var(--text-md); border: 1.5px solid var(--border); padding: 10px; border-radius: 10px; font-size: 13px; font-weight: 700; cursor: pointer; transition: .2s; font-family: 'Barlow', sans-serif; text-decoration: none; text-align: center; }
.btn-filter-reset:hover { border-color: var(--red); color: var(--red); background: var(--red-lt); }

/* ═══ TOMBOL TAMBAH - SAMA SEPERTI LAPANGAN ═══ */
.btn-add { 
    display: inline-flex !important; 
    align-items: center !important; 
    gap: 8px !important; 
    background-color: var(--text) !important; 
    color: #fff !important; 
    padding: 10px 20px !important; 
    border-radius: 10px !important; 
    font-size: 12px !important; 
    font-weight: 800 !important; 
    text-decoration: none !important; 
    text-transform: uppercase !important; 
    transition: all .2s ease !important; 
    border: none !important; 
    cursor: pointer !important; 
}
.btn-add:hover { 
    background-color: var(--orange) !important; 
    transform: translateY(-2px) !important; 
    box-shadow: 0 8px 20px rgba(255,69,0,.3) !important; 
}

.card { background: var(--card-bg); border-radius: 16px; border: 1px solid var(--border); overflow: hidden; transition: all .2s ease; }
.card:hover { box-shadow: 0 8px 24px rgba(0,0,0,.06); }
.card-header { padding: 20px 24px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
.card-title { font-size: 15px; font-weight: 800; color: var(--text); display: flex; align-items: center; gap: 8px; }
.card-title i { color: var(--orange); font-size: 14px; }
.card-badge { background: var(--orange-lt); color: var(--orange); font-size: 11px; font-weight: 800; padding: 4px 10px; border-radius: 20px; }

.table-wrap { overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table th { padding: 13px 20px; font-size: 10px; font-weight: 800; color: var(--muted); text-transform: uppercase; letter-spacing: .6px; border-bottom: 2px solid var(--border-lt); text-align: left; }
.data-table td { padding: 16px 20px; font-size: 13px; border-bottom: 1px solid var(--border-lt); vertical-align: middle; }
.data-table tbody tr:last-child td { border-bottom: none; }
.data-table tbody tr:nth-child(odd) { background-color: var(--zebra-orange); }
.data-table tbody tr:nth-child(even) { background-color: #FFFFFF; }
.data-table tbody tr:hover td { background-color: #FED7AA !important; }

.row-num { font-family: 'Barlow Condensed'; font-weight: 800; color: var(--muted); font-size: 14px; text-align: center; }
.emp-name { font-weight: 700; color: var(--text); }
.cell-detail { font-size: 11px; color: var(--muted); font-weight: 600; margin-top: 2px; }

.jabatan-badge { background: #EEF2FF; color: #4338CA; padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 800; display: inline-block; }

/* ═══ ACTIONS - SAMA PERSIS DENGAN LAPANGAN.PHP ═══ */
.action-group { display: flex; gap: 12px; justify-content: flex-start; align-items: center; }
.btn-action {
    width: 38px;
    height: 38px;
    display: inline-flex; 
    align-items: center; 
    justify-content: center; 
    border-radius: 10px; 
    font-size: 14px; 
    font-weight: 700;
    transition: all .25s cubic-bezier(.4,0,.2,1); 
    border: 1.5px solid transparent; 
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
.btn-view {
    background: linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%); color: #1E40AF; border-color: #BFDBFE;
}
.btn-view:hover {
    background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%); color: #fff; border-color: #3B82F6;
    transform: translateY(-2px); box-shadow: 0 6px 20px rgba(59,130,246,.35);
}

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

.empty-state { text-align: center; padding: 60px 20px; color: var(--muted); }
.empty-state i { font-size: 40px; opacity: .3; display: block; margin-bottom: 14px; }
.empty-state p { font-size: 13px; font-weight: 700; }

.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.55); backdrop-filter: blur(6px); display: flex; justify-content: center; align-items: center; z-index: 2000; }
.modal-overlay.hidden { display: none; }
.modal-box { background: #fff; border-radius: 20px; width: 560px; max-width: 95vw; box-shadow: 0 25px 60px rgba(0,0,0,.2); position: relative; max-height: 90vh; overflow-y: auto; }
.modal-head { padding: 28px 32px 24px; border-bottom: 1px solid var(--border); }
.modal-tag { font-size: 10px; font-weight: 800; color: var(--orange); text-transform: uppercase; letter-spacing: .8px; margin-bottom: 6px; }
.modal-title { font-family: 'Barlow Condensed', sans-serif; font-size: 24px; font-weight: 900; color: var(--text); letter-spacing: -.3px; }
.modal-sub { font-size: 13px; color: var(--muted); margin-top: 4px; }
.modal-body { padding: 28px 32px 32px; }
.modal-close { position: absolute; top: 20px; right: 20px; background: var(--bg); border: 1px solid var(--border); font-size: 14px; color: var(--muted); cursor: pointer; transition: .2s; width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; }
.modal-close:hover { color: var(--red); border-color: var(--red); background: var(--red-lt); }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.full-width { grid-column: span 2; }
.field-label { font-size: 11px; font-weight: 800; color: var(--muted); text-transform: uppercase; letter-spacing: .5px; display: block; margin-bottom: 6px; }
.field-label .required { color: var(--red); margin-left: 2px; font-size: 14px; font-weight: 900; }
.modal-input { width: 100%; padding: 12px 14px; border: 1.5px solid var(--border); border-radius: 10px; font-size: 14px; font-family: 'Barlow', sans-serif; transition: .2s; background: #FAFAFA; color: var(--text); margin-bottom: 4px; }
.modal-input:focus { outline: none; border-color: var(--orange); background: #fff; box-shadow: 0 0 0 3px rgba(255,69,0,.08); }
.modal-input[type="date"] { cursor: pointer; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%236B7280' stroke-width='2'%3E%3Crect x='3' y='4' width='18' height='18' rx='2' ry='2'/%3E%3Cline x1='16' y1='2' x2='16' y2='6'/%3E%3Cline x1='8' y1='2' x2='8' y2='6'/%3E%3Cline x1='3' y1='10' x2='21' y2='10'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 12px center; padding-right: 40px; }
.modal-input[type="date"]::-webkit-calendar-picker-indicator { opacity: 0; cursor: pointer; position: absolute; right: 0; width: 40px; height: 100%; }
.modal-input:invalid:not(:placeholder-shown) { border-color: var(--red); }
.modal-input:valid:not(:placeholder-shown) { border-color: var(--green); }
.modal-input[readonly] { background: var(--border-lt); color: var(--muted); }
.akun-notice { background: #FFF7ED; border: 1px solid #FED7AA; border-radius: 10px; padding: 12px 15px; font-size: 12px; color: #92400E; font-weight: 600; display: flex; align-items: center; gap: 8px; grid-column: span 2; }
.btn-submit { grid-column: span 2; width: 100%; background: var(--orange); color: #fff; border: none; padding: 14px; border-radius: 10px; font-weight: 800; font-size: 14px; cursor: pointer; text-transform: uppercase; letter-spacing: .5px; transition: .2s; font-family: 'Barlow', sans-serif; margin-top: 8px; }
.btn-submit:hover { background: var(--orange-dk); transform: translateY(-1px); box-shadow: 0 8px 20px rgba(255,69,0,.25); }
.btn-submit:disabled { background: #D1D5DB; cursor: not-allowed; transform: none; box-shadow: none; }

.val-msg { font-size: 11px; color: var(--red); font-weight: 600; margin-bottom: 10px; display: none; min-height: 16px; }
.val-msg.show { display: block; }
.val-msg i { margin-right: 4px; }

.modal-input.error { border-color: var(--red) !important; background-color: #FEF2F2 !important; box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.15) !important; }
.modal-input.error:focus { border-color: var(--red) !important; box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.25) !important; }

.detail-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,.5); backdrop-filter: blur(4px); z-index: 1000; align-items: center; justify-content: center; padding: 20px; }
.detail-overlay.active { display: flex; }
.detail-box { background: var(--card-bg); border-radius: 16px; border: 1px solid var(--border); width: 100%; max-width: 520px; max-height: 90vh; overflow-y: auto; box-shadow: 0 25px 50px rgba(0,0,0,.15); }
.detail-header { display: flex; align-items: center; justify-content: space-between; padding: 24px 28px; border-bottom: 1px solid var(--border-lt); }
.detail-header-left { display: flex; align-items: center; gap: 14px; }
.detail-avatar { width: 56px; height: 56px; background: linear-gradient(135deg, var(--orange) 0%, var(--orange-dk) 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 24px; flex-shrink: 0; }
.detail-header-info { display: flex; flex-direction: column; }
.detail-name { font-family: 'Barlow Condensed', sans-serif; font-size: 22px; font-weight: 900; color: var(--text); }
.detail-id { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 700; color: var(--orange); background: var(--orange-lt); padding: 3px 10px; border-radius: 6px; margin-top: 4px; width: fit-content; }
.detail-close { width: 36px; height: 36px; border-radius: 10px; background: var(--bg); border: 1.5px solid var(--border); color: var(--muted); display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all .2s; font-size: 14px; }
.detail-close:hover { background: var(--red-lt); color: var(--red); border-color: var(--red); }
.detail-body { padding: 0; }
.detail-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 0; }
.detail-item { padding: 16px 24px; border-bottom: 1px solid var(--border-lt); border-right: 1px solid var(--border-lt); }
.detail-item:nth-child(2n) { border-right: none; }
.detail-item:nth-last-child(-n+2) { border-bottom: none; }
.detail-item:nth-last-child(1):nth-child(odd) { border-bottom: none; grid-column: 1 / -1; }
.detail-label { display: flex; align-items: center; gap: 6px; font-size: 10px; font-weight: 800; text-transform: uppercase; color: var(--muted); letter-spacing: .5px; margin-bottom: 6px; }
.detail-label i { font-size: 11px; width: 14px; text-align: center; }
.detail-value { font-size: 14px; font-weight: 700; color: var(--text); line-height: 1.4; }
.detail-value-muted { color: var(--muted); font-weight: 600; }
.detail-footer { padding: 16px 24px; border-top: 1px solid var(--border-lt); display: flex; gap: 10px; justify-content: flex-end; }
.btn-detail-close { display: inline-flex; align-items: center; gap: 6px; padding: 10px 18px; border-radius: 10px; font-size: 12px; font-weight: 700; font-family: 'Barlow', sans-serif; cursor: pointer; transition: all .2s; border: 1.5px solid transparent; text-decoration: none; background: var(--bg); color: var(--text-md); border-color: var(--border); }
.btn-detail-close:hover { background: var(--text); color: #fff; border-color: var(--text); }

@media(max-width: 1100px) { .stat-grid { grid-template-columns: repeat(2, 1fr); } }
@media(max-width: 768px) {
    .sidebar { width: 0; overflow: hidden; padding: 0; }
    .main { margin-left: 0; }
    .stat-grid { grid-template-columns: 1fr; }
    .content { padding: 20px; }
    .topbar { padding: 0 20px; }
    .pagination-wrap { flex-direction: column; gap: 12px; }
    .detail-grid { grid-template-columns: 1fr; }
    .detail-item { border-right: none; }
    .filter-dropdown { width: 100%; right: 0; }
}
</style>
</head>
<body>
<div class="modal-overlay <?= ($edit_data || $show_add) ? '' : 'hidden' ?>" id="modal">
    <div class="modal-box">
        <button class="modal-close" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></button>
        <div class="modal-head">
            <div class="modal-tag">Master Karyawan</div>
            <div class="modal-title"><?= $edit_data ? 'Edit Data Staf' : 'Tambah Karyawan Baru' ?></div>
            <div class="modal-sub"><?= $edit_data ? 'Perbarui informasi karyawan yang ada' : 'Daftarkan karyawan baru ke dalam sistem' ?></div>
        </div>
        <div class="modal-body">
            <form method="POST" id="formKaryawan" onsubmit="return validateForm(this)">
                <div class="form-grid">
                    <?php if (!$edit_data && empty($akun_bebas)): ?>
                    <div class="akun-notice">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        Tidak ada akun tersedia. <a href="akun.php" style="color:var(--orange);font-weight:800;margin-left:4px;">Buat akun baru</a> terlebih dahulu.
                    </div>
                    <?php endif; ?>
                    <div>
                        <label class="field-label">ID Karyawan <span class="required">*</span></label>
                        <input type="text" name="id_kry" id="id_kry" class="modal-input" value="<?= $edit_data['ID_Karyawan'] ?? '' ?>" <?= $edit_data ? 'readonly' : 'required' ?> placeholder="KRY001">
                        <div class="val-msg" id="val-id_kry"><i class="fa-solid fa-circle-exclamation"></i> ID Karyawan wajib diisi</div>
                    </div>
                    <div>
                        <label class="field-label">Nama Lengkap <span class="required">*</span></label>
                        <input type="text" name="nama" id="nama" class="modal-input" value="<?= htmlspecialchars($edit_data['Nama_Karyawan'] ?? '') ?>" required minlength="3" maxlength="100" pattern="[a-zA-Z\s]+" placeholder="Nama lengkap (huruf & spasi)">
                        <div class="val-msg" id="val-nama"><i class="fa-solid fa-circle-exclamation"></i> Nama minimal 3 karakter, hanya huruf dan spasi</div>
                    </div>
                    <div>
                        <label class="field-label">Tempat Lahir <span class="required">*</span></label>
                        <input type="text" name="tempat_lahir" id="tempat_lahir" class="modal-input" value="<?= htmlspecialchars($edit_data['Tempat_Lahir'] ?? '') ?>" required minlength="2" maxlength="100" placeholder="Contoh: Jakarta, Bandung, Surabaya">
                        <div class="val-msg" id="val-tempat_lahir"><i class="fa-solid fa-circle-exclamation"></i> Tempat lahir wajib diisi</div>
                    </div>
                    <div>
                        <label class="field-label">Tanggal Lahir <span class="required">*</span></label>
                        <input type="date" name="tanggal_lahir" id="tanggal_lahir" class="modal-input" value="<?= $edit_data['Tanggal_Lahir'] ? $edit_data['Tanggal_Lahir']->format('Y-m-d') : '' ?>" required max="<?= date('Y-m-d') ?>" onchange="validateDate(this)">
                        <div class="val-msg" id="val-tanggal_lahir"><i class="fa-solid fa-circle-exclamation"></i> Tanggal lahir wajib diisi dan tidak boleh di masa depan</div>
                    </div>
                    <div>
                        <label class="field-label">Jenis Kelamin <span class="required">*</span></label>
                        <select name="jk" id="jk" class="modal-input" required>
                            <option value="1" <?= (isset($edit_data['Jenis_Kelamin']) && $edit_data['Jenis_Kelamin']==1)?'selected':''?>>Laki-laki</option>
                            <option value="2" <?= (isset($edit_data['Jenis_Kelamin']) && $edit_data['Jenis_Kelamin']==2)?'selected':''?>>Perempuan</option>
                        </select>
                    </div>
                    <div>
                        <label class="field-label">Jabatan <span class="required">*</span></label>
                        <select name="jabatan" id="jabatan" class="modal-input" required>
                            <?php foreach ($map_jabatan as $id => $val): ?>
                            <option value="<?= $id ?>" <?= (isset($edit_data['Jabatan']) && $edit_data['Jabatan']==$id)?'selected':''?>><?= $val ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if (!$edit_data): ?>
                    <div class="full-width">
                        <label class="field-label">Akun Terhubung <span class="required">*</span></label>
                        <select name="id_akun" id="id_akun" class="modal-input" required <?= empty($akun_bebas) ? 'disabled' : '' ?>>
                            <option value="">-- Pilih Akun yang Belum Dipakai --</option>
                            <?php foreach ($akun_bebas as $ak): ?>
                            <option value="<?= $ak['ID_Akun'] ?>"><?= $ak['ID_Akun'] ?> — <?= htmlspecialchars($ak['Email']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="val-msg" id="val-id_akun"><i class="fa-solid fa-circle-exclamation"></i> Pilih akun yang terhubung</div>
                    </div>
                    <?php endif; ?>
                    <div class="full-width">
                        <label class="field-label">Nomor Telepon <span class="required">*</span></label>
                        <input type="text" name="telp" id="telp" class="modal-input" value="<?= htmlspecialchars($edit_data['No_Telepon'] ?? '') ?>" placeholder="08123456789" required pattern="[0-9]{10,14}" maxlength="14">
                        <div class="val-msg" id="val-telp"><i class="fa-solid fa-circle-exclamation"></i> Nomor telepon 10-14 digit, hanya angka</div>
                    </div>
                    <button type="submit" name="<?= $edit_data ? 'update_karyawan' : 'add_karyawan' ?>" class="btn-submit" <?= (!$edit_data && empty($akun_bebas)) ? 'disabled' : '' ?>>
                        <i class="fa-solid <?= $edit_data ? 'fa-floppy-disk' : 'fa-user-plus' ?>"></i>
                        <?= $edit_data ? 'Simpan Perubahan' : 'Daftarkan Karyawan' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="detail-overlay" id="detailModal" onclick="closeDetail(event)">
    <div class="detail-box" onclick="event.stopPropagation()">
        <div class="detail-header">
            <div class="detail-header-left">
                <div class="detail-avatar"><i class="fa-solid fa-user-tie"></i></div>
                <div class="detail-header-info">
                    <div class="detail-name" id="dName">-</div>
                    <div class="detail-id"><i class="fa-solid fa-fingerprint"></i> <span id="dId">-</span></div>
                </div>
            </div>
            <button class="detail-close" onclick="closeDetail()" title="Tutup"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="detail-body">
            <div class="detail-grid">
                <div class="detail-item"><div class="detail-label"><i class="fa-solid fa-user" style="color:var(--orange);"></i> Nama Lengkap</div><div class="detail-value" id="dNama">-</div></div>
                <div class="detail-item"><div class="detail-label"><i class="fa-solid fa-venus-mars" style="color:var(--purple);"></i> Jenis Kelamin</div><div class="detail-value" id="dJK">-</div></div>
                <div class="detail-item"><div class="detail-label"><i class="fa-solid fa-location-dot" style="color:var(--red);"></i> Tempat Lahir</div><div class="detail-value" id="dTempatLahir">-</div></div>
                <div class="detail-item"><div class="detail-label"><i class="fa-solid fa-cake-candles" style="color:var(--pink);"></i> Tanggal Lahir</div><div class="detail-value" id="dTanggalLahir">-</div></div>
                <div class="detail-item"><div class="detail-label"><i class="fa-solid fa-briefcase" style="color:var(--blue);"></i> Jabatan</div><div class="detail-value" id="dJabatan">-</div></div>
                <div class="detail-item"><div class="detail-label"><i class="fa-solid fa-phone" style="color:var(--green);"></i> No. Telepon</div><div class="detail-value" id="dTelp">-</div></div>
                <div class="detail-item"><div class="detail-label"><i class="fa-solid fa-shield-halved" style="color:var(--yellow);"></i> Status</div><div class="detail-value" id="dStatus">-</div></div>
                <div class="detail-item"><div class="detail-label"><i class="fa-solid fa-calendar" style="color:var(--pink);"></i> Tanggal Dibuat</div><div class="detail-value" id="dCreatedDate">-</div></div>
            </div>
        </div>
        <div class="detail-footer">
            <button class="btn-detail-close" onclick="closeDetail()"><i class="fa-solid fa-xmark"></i> Tutup</button>
        </div>
    </div>
</div>

<aside class="sidebar">
    <a href="../view_pemilik.php" class="sb-brand">
        <div class="sb-icon"><i class="fa-solid fa-basketball"></i></div>
        <div><div class="sb-brand-name">HOOP BALL</div><div class="sb-brand-sub">Management System</div></div>
    </a>
    <div class="sb-section-label">Manajemen</div>
    <nav>
        <a href="../view_pemilik.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-house"></i></div> Dashboard</a>
        <a href="akun.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-user-shield"></i></div> Kelola Akun</a>
        <a href="karyawan.php" class="sb-link active"><div class="sb-icon-wrap"><i class="fa-solid fa-user-tie"></i></div> Kelola Karyawan</a>
        <a href="alat.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-toolbox"></i></div> Kelola Alat</a>
        <a href="../laporan/omzet.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-chart-line"></i></div> Laporan & Omzet</a>
    </nav>
    <div class="sb-section-label">Akun</div>
    <a href="../profile.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-id-badge"></i></div> Profil Saya</a>
    <div class="sb-bottom">
        <div class="sb-user">
            <div class="sb-avatar">
            <?php if ($profile_photo): ?><img src="<?= $profile_photo ?>" alt="Profile" style="width:100%;height:100%;object-fit:cover;border-radius:50%;"><?php else: ?><i class="fa-solid fa-user"></i><?php endif; ?>
            </div>
            <div><div class="sb-user-name"><?= strtoupper(htmlspecialchars($nama)) ?></div><div class="sb-user-role">PEMILIK</div></div>
            <a href="../logout.php" class="sb-logout" title="Keluar"><i class="fa-solid fa-right-from-bracket"></i></a>
        </div>
    </div>
</aside>

<main class="main">
    <header class="topbar">
        <div class="topbar-left">
            <div class="topbar-title">Kelola Data Karyawan</div>
            <div class="topbar-breadcrumb">Manajemen / Karyawan</div>
        </div>
        <div class="topbar-right">
            <!-- JAM DIGITAL LIVE - SAMA PERSIS DENGAN LAPANGAN.PHP -->
            <div id="clock-display">
                <div class="clock-time">
                    <span id="h">00</span><span class="clock-colon">:</span><span id="m">00</span><span class="clock-colon">:</span><span id="s">00</span>
                </div>
                <div class="clock-divider"></div>
                <div class="clock-date" id="full-date">MEMUAT...</div>
            </div>
            
            <a href="#" class="topbar-btn"><i class="fa-solid fa-magnifying-glass"></i></a>
            <a href="#" class="topbar-btn"><i class="fa-solid fa-bell"></i><span class="notif-dot"></span></a>
            <div class="dropdown-wrap">
                <div class="topbar-user">
                    <div class="t-avatar">
                    <?php if ($profile_photo): ?><img src="<?= $profile_photo ?>" alt="Profile" style="width:100%;height:100%;object-fit:cover;border-radius:50%;"><?php else: ?><i class="fa-solid fa-user"></i><?php endif; ?>
                    </div>
                    <div><div class="t-name"><?= strtoupper(htmlspecialchars($nama)) ?></div><div class="t-role">PEMILIK</div></div>
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
        <div class="page-header">
            <div><div class="page-title-tag"></div><div class="page-title">Daftar Karyawan</div></div>
        </div>

        <div class="stat-grid">
            <div class="stat-card sc-purple">
                <div class="stat-header"><div class="stat-icon-wrap si-purple"><i class="fa-solid fa-user-tie"></i></div><div class="stat-trend trend-up"><i class="fa-solid fa-arrow-up"></i> Aktif</div></div>
                <div class="stat-value"><?= $total_kry ?></div><div class="stat-label">Total Karyawan</div><div class="stat-sublabel">Terdaftar di sistem</div>
            </div>
            <div class="stat-card sc-blue">
                <div class="stat-header"><div class="stat-icon-wrap si-blue"><i class="fa-solid fa-users"></i></div><div class="stat-trend trend-up"><i class="fa-solid fa-arrow-up"></i> Total</div></div>
                <div class="stat-value"><?= $total_akun_aktif ?></div><div class="stat-label">Akun Aktif</div><div class="stat-sublabel">Akun dengan status aktif</div>
            </div>
            <div class="stat-card sc-green">
                <div class="stat-header"><div class="stat-icon-wrap si-green"><i class="fa-solid fa-briefcase"></i></div><div class="stat-trend trend-up"><i class="fa-solid fa-arrow-up"></i> Posisi</div></div>
                <div class="stat-value"><?= count($map_jabatan) ?></div><div class="stat-label">Jenis Jabatan</div><div class="stat-sublabel">Posisi tersedia di sistem</div>
            </div>
        </div>

        <!-- ═══ TOOLBAR WITH FILTER - TOMBOL TAMBAH DI SAMPING KANAN FILTER ═══ -->
        <div class="toolbar">
            <div class="toolbar-left">
                <!-- FILTER DROPDOWN -->
                <div class="filter-wrap">
                    <button class="filter-btn" onclick="toggleFilter()" id="filterBtn">
                        <i class="fa-solid fa-filter"></i> Filter <i class="fa-solid fa-chevron-down"></i>
                    </button>
                    <div class="filter-dropdown" id="filterDropdown">
                        <div class="filter-header"><div class="filter-title">Filter Data</div></div>
                        <div class="filter-body">
                            <div class="filter-group">
                                <label class="filter-label">Urut Berdasarkan</label>
                                <select class="filter-select" id="filterSortBy">
                                    <option value="ID_Karyawan" <?= $sort_by == 'ID_Karyawan' ? 'selected' : '' ?>>ID Karyawan <?= $sort_by == 'ID_Karyawan' && $sort_order == 'ASC' ? '↑' : '' ?></option>
                                    <option value="Nama_Karyawan" <?= $sort_by == 'Nama_Karyawan' ? 'selected' : '' ?>>Nama Lengkap <?= $sort_by == 'Nama_Karyawan' && $sort_order == 'ASC' ? '↑' : '' ?></option>
                                    <option value="Jabatan" <?= $sort_by == 'Jabatan' ? 'selected' : '' ?>>Jabatan <?= $sort_by == 'Jabatan' && $sort_order == 'ASC' ? '↑' : '' ?></option>
                                    <option value="Jenis_Kelamin" <?= $sort_by == 'Jenis_Kelamin' ? 'selected' : '' ?>>Jenis Kelamin <?= $sort_by == 'Jenis_Kelamin' && $sort_order == 'ASC' ? '↑' : '' ?></option>
                                    <option value="No_Telepon" <?= $sort_by == 'No_Telepon' ? 'selected' : '' ?>>No. Telepon <?= $sort_by == 'No_Telepon' && $sort_order == 'ASC' ? '↑' : '' ?></option>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label class="filter-label">Jabatan</label>
                                <select class="filter-select" id="filterJabatan">
                                    <option value="0" <?= $filter_jabatan == 0 ? 'selected' : '' ?>>Semua Jabatan</option>
                                    <?php foreach ($map_jabatan as $id => $val): ?>
                                    <option value="<?= $id ?>" <?= $filter_jabatan == $id ? 'selected' : '' ?>><?= $val ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label class="filter-label">Jenis Kelamin</label>
                                <select class="filter-select" id="filterJK">
                                    <option value="0" <?= $filter_jk == 0 ? 'selected' : '' ?>>Semua Jenis Kelamin</option>
                                    <option value="1" <?= $filter_jk == 1 ? 'selected' : '' ?>>Laki-laki</option>
                                    <option value="2" <?= $filter_jk == 2 ? 'selected' : '' ?>>Perempuan</option>
                                </select>
                            </div>
                        </div>
                        <div class="filter-footer">
                            <a href="karyawan.php" class="btn-filter-reset"><i class="fa-solid fa-rotate-left"></i> Reset</a>
                            <button class="btn-filter-apply" onclick="applyFilter()"><i class="fa-solid fa-check"></i> Terapkan</button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- TOMBOL TAMBAH KARYAWAN - DI SAMPING KANAN FILTER -->
            <button class="btn-add" onclick="openModal()"><i class="fa-solid fa-plus"></i> Tambah Karyawan</button>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fa-solid fa-user-tie"></i> Data Karyawan</div>
                <span class="card-badge"><?= $total_kry ?> total</span>
            </div>
            <?php if ($query_error): ?><div style="padding:20px;background:#fee;border:1px solid #fcc;border-radius:8px;margin:20px 0;"><p style="color:#c00;font-weight:bold;margin:0;"><i class="fa-solid fa-circle-exclamation"></i> Gagal mengambil data dari database. Silakan refresh halaman atau hubungi administrator.</p><p style="color:#666;font-size:11px;margin:5px 0 0;">Error: <?php echo htmlspecialchars($query_error_msg); ?></p></div><?php else: ?><div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr><th style="width:50px;text-align:center;">No</th><th>Nama Lengkap</th><th>Jabatan</th><th>No. Telepon</th><th style="text-align:left; width:180px;">Aksi</th></tr>
                    </thead>
                    <tbody>
                    <?php $row_num = 0; $has_data = false; if (!$query_error && $query): while ($row = safe_sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)): $row_num++; $has_data = true; ?>
                        <tr class="row-<?= $row_num % 2 == 1 ? 'odd' : 'even' ?>">
                            <td class="row-num"><?= $row_num ?></td>
                            <td><div class="emp-name"><?= htmlspecialchars($row['Nama_Karyawan']) ?></div><div class="cell-detail"><?= $row['Jenis_Kelamin'] == 1 ? '♂ Laki-laki' : '♀ Perempuan' ?></div></td>
                            <td><span class="jabatan-badge"><?= $map_jabatan[$row['Jabatan']] ?? 'Staf' ?></span></td>
                            <td style="color:var(--muted); font-weight:600;"><?= htmlspecialchars($row['No_Telepon']) ?></td>
                            <td>
                                <!-- TOMBOL AKSI - SAMA PERSIS STYLE DENGAN LAPANGAN.PHP -->
                                <div class="action-group">
                                    <button onclick="openDetail('<?= htmlspecialchars($row['ID_Karyawan']) ?>','<?= htmlspecialchars($row['Nama_Karyawan']) ?>','<?= $row['Jenis_Kelamin'] ?>','<?= htmlspecialchars($row['Tempat_Lahir'] ?? '') ?>','<?= $row['Tanggal_Lahir'] ? $row['Tanggal_Lahir']->format('Y-m-d') : '' ?>','<?= $map_jabatan[$row['Jabatan']] ?? 'Staf' ?>','<?= htmlspecialchars($row['No_Telepon']) ?>','<?= htmlspecialchars($row['Status'] ?? 'Aktif') ?>','<?= $row['Created_Date'] ? $row['Created_Date']->format('d/m/Y H:i') : '-' ?>')" class="btn-action btn-view" title="Lihat Detail"><i class="fa-solid fa-eye"></i></button>
                                    <a href="?page=<?= $page ?>&edit_id=<?= $row['ID_Karyawan'] ?>" class="btn-action btn-edit" title="Edit Data"><i class="fa-solid fa-pen-to-square"></i></a>
                                    <button type="button" class="btn-action btn-delete" onclick="confirmDelete('<?= $row['ID_Karyawan'] ?>', '<?= htmlspecialchars($row['Nama_Karyawan']) ?>')" title="Hapus Permanen"><i class="fa-solid fa-trash-can"></i></button>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; endif; ?>
                    <?php if (!$has_data): ?>
                        <tr><td colspan="5"><div class="empty-state"><i class="fa-solid fa-user-tie"></i><p>Belum ada data karyawan terdaftar</p></div></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div><?php endif; ?>

        <?php if ($total_pages > 1): ?>
        <div class="pagination-wrap">
            <div class="pagination-info">Menampilkan <strong><?= (($page - 1) * $limit) + 1 ?></strong> - <strong><?= min($page * $limit, $total_rows) ?></strong> dari <strong><?= $total_rows ?></strong> data</div>
            <div class="pagination-nav">
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>" class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>" title="Halaman Pertama"><i class="fa-solid fa-angles-left"></i></a>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>" title="Halaman Sebelumnya"><i class="fa-solid fa-angle-left"></i></a>
                <?php $start_page = max(1, $page - 2); $end_page = min($total_pages, $page + 2); if ($end_page - $start_page < 4 && $total_pages >= 5) { if ($start_page == 1) { $end_page = min(5, $total_pages); } else { $start_page = max(1, $total_pages - 4); } } if ($start_page > 1): ?><a href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>" class="page-btn">1</a><?php if ($start_page > 2): ?><span class="page-ellipsis">...</span><?php endif; ?><?php endif; ?>
                <?php for ($i = $start_page; $i <= $end_page; $i++): ?><a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" class="page-btn <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a><?php endfor; ?>
                <?php if ($end_page < $total_pages): ?><?php if ($end_page < $total_pages - 1): ?><span class="page-ellipsis">...</span><?php endif; ?><a href="?<?= http_build_query(array_merge($_GET, ['page' => $total_pages])) ?>" class="page-btn"><?= $total_pages ?></a><?php endif; ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>" title="Halaman Selanjutnya"><i class="fa-solid fa-angle-right"></i></a>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $total_pages])) ?>" class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>" title="Halaman Terakhir"><i class="fa-solid fa-angles-right"></i></a>
            </div>
        </div>
        <?php else: ?>
        <div class="pagination-wrap"><div class="pagination-info">Menampilkan <strong>1</strong> - <strong><?= $total_rows ?></strong> dari <strong><?= $total_rows ?></strong> data</div></div>
        <?php endif; ?>
    </div>
</main>

<script>
function openModal()  { document.getElementById('modal').classList.remove('hidden'); }
function closeModal() { window.location.href = 'karyawan.php'; }
function confirmDelete(id, nama) {
    Swal.fire({
        title: 'Hapus Karyawan?',
        html: `Karyawan <strong style="color:#FF4500;">${nama}</strong> (${id}) akan dihapus <strong style="color:#DC2626;">secara permanen</strong>!`,
        icon: 'warning', showCancelButton: true, confirmButtonColor: '#EF4444', cancelButtonColor: '#6B7280',
        confirmButtonText: 'Ya, Hapus!', cancelButtonText: 'Batal', reverseButtons: true, borderRadius: '16px'
    }).then((r) => { if (r.isConfirmed) window.location.href = '?page=<?= $page ?>&delete_id=' + id; });
}
function openDetail(id, nama, jk, tempatLahir, tanggalLahir, jabatan, telp, status, createdDate) {
    const mapJK = { '1': 'Laki-laki', '2': 'Perempuan' };
    const months = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    document.getElementById('dId').textContent = id;
    document.getElementById('dName').textContent = nama;
    document.getElementById('dNama').textContent = nama;
    document.getElementById('dJK').innerHTML = '<span style="display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:8px;font-size:12px;font-weight:800;text-transform:uppercase;background:' + (jk == '1' ? 'var(--blue-lt);color:var(--blue)' : 'var(--pink-lt);color:var(--pink)') + '"><i class="fa-solid ' + (jk == '1' ? 'fa-mars' : 'fa-venus') + '"></i> ' + (mapJK[jk] || '-') + '</span>';
    document.getElementById('dTempatLahir').textContent = tempatLahir || '-';
    if (tanggalLahir) {
        const d = new Date(tanggalLahir);
        document.getElementById('dTanggalLahir').textContent = d.getDate() + ' ' + months[d.getMonth()] + ' ' + d.getFullYear();
    } else {
        document.getElementById('dTanggalLahir').textContent = '-';
    }
    document.getElementById('dJabatan').innerHTML = '<span class="jabatan-badge">' + jabatan + '</span>';
    document.getElementById('dTelp').textContent = telp;
    document.getElementById('dStatus').innerHTML = '<span style="display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:8px;font-size:12px;font-weight:800;text-transform:uppercase;background:' + (status === 'Aktif' ? 'var(--green-lt);color:var(--green)' : 'var(--red-lt);color:var(--red)') + '"><i class="fa-solid ' + (status === 'Aktif' ? 'fa-circle-check' : 'fa-circle-xmark') + '"></i> ' + status + '</span>';
    document.getElementById('dCreatedDate').textContent = createdDate;
    document.getElementById('detailModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}
function closeDetail(e) {
    if (e && e.target !== e.currentTarget) return;
    document.getElementById('detailModal').classList.remove('active');
    document.body.style.overflow = '';
}

// ═══ FILTER FUNCTIONS ═══
function toggleFilter() {
    const dropdown = document.getElementById('filterDropdown');
    dropdown.classList.toggle('active');
}

function applyFilter() {
    const sortBy = document.getElementById('filterSortBy').value;
    const jabatan = document.getElementById('filterJabatan').value;
    const jk = document.getElementById('filterJK').value;
    
    const params = new URLSearchParams(window.location.search);
    params.set('sort_by', sortBy);
    params.set('filter_jabatan', jabatan);
    params.set('filter_jk', jk);
    params.set('page', '1');
    
    window.location.href = 'karyawan.php?' + params.toString();
}

// Close filter dropdown when clicking outside
document.addEventListener('click', function(e) {
    const filterWrap = document.querySelector('.filter-wrap');
    const filterDropdown = document.getElementById('filterDropdown');
    if (!filterWrap.contains(e.target)) {
        filterDropdown.classList.remove('active');
    }
});

function validateDate(input) {
    const selected = new Date(input.value);
    const today = new Date();
    today.setHours(0,0,0,0);
    const valMsg = document.getElementById('val-tanggal_lahir');

    if (!input.value) {
        if (valMsg) { valMsg.classList.add('show'); input.classList.add('error'); }
        return false;
    }
    if (selected > today) {
        if (valMsg) { valMsg.textContent = '⚠ Tanggal lahir tidak boleh di masa depan!'; valMsg.classList.add('show'); input.classList.add('error'); }
        return false;
    }
    const age = today.getFullYear() - selected.getFullYear();
    if (age < 17) {
        if (valMsg) { valMsg.textContent = '⚠ Karyawan harus berusia minimal 17 tahun!'; valMsg.classList.add('show'); input.classList.add('error'); }
        return false;
    }
    if (age > 60) {
        if (valMsg) { valMsg.textContent = '⚠ Usia maksimal 60 tahun!'; valMsg.classList.add('show'); input.classList.add('error'); }
        return false;
    }
    if (valMsg) { valMsg.classList.remove('show'); input.classList.remove('error'); }
    return true;
}

function validateForm(form) {
    let valid = true;
    const inputs = form.querySelectorAll('.modal-input[required]');
    inputs.forEach(input => {
        const valMsg = document.getElementById('val-' + input.id);
        if (input.type === 'date' && input.value) {
            if (!validateDate(input)) valid = false;
        } else if (!input.checkValidity()) {
            if (valMsg) valMsg.classList.add('show');
            input.classList.add('error');
            valid = false;
        } else {
            if (valMsg) valMsg.classList.remove('show');
            input.classList.remove('error');
        }
    });
    return valid;
}
document.addEventListener('DOMContentLoaded', function() {
    const inputs = document.querySelectorAll('.modal-input');
    inputs.forEach(input => {
        input.addEventListener('input', function() {
            const valMsg = document.getElementById('val-' + this.id);
            if (valMsg) {
                if (!this.checkValidity() && this.value !== '') { valMsg.classList.add('show'); this.classList.add('error'); }
                else { valMsg.classList.remove('show'); this.classList.remove('error'); }
            }
        });
        input.addEventListener('blur', function() {
            const valMsg = document.getElementById('val-' + this.id);
            if (valMsg) {
                if (!this.checkValidity()) { valMsg.classList.add('show'); this.classList.add('error'); }
                else { valMsg.classList.remove('show'); this.classList.remove('error'); }
            }
        });
    });
});

// ═══ JAM DIGITAL LIVE - SAMA PERSIS DENGAN LAPANGAN.PHP ═══
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

const urlParams = new URLSearchParams(window.location.search);
const status = urlParams.get('status');
const msg = urlParams.get('msg');
if (status && msg) {
    Swal.fire({ icon: status === 'success' ? 'success' : 'error', title: status === 'success' ? 'Berhasil!' : 'Gagal!', text: msg, timer: 3000, showConfirmButton: false, iconColor: '#FF4500' });
    window.history.replaceState({}, document.title, window.location.pathname);
}
window.onclick = function(e) { if (e.target == document.getElementById('modal')) closeModal(); };
document.addEventListener('keydown', function(e) { 
    if (e.key === 'Escape') {
        closeDetail(); 
        document.getElementById('filterDropdown').classList.remove('active');
    }
});
</script>
</body>
</html>