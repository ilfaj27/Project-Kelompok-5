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

// ============================================
// MAPPING SESUAI DATABASE
// ============================================
$map_jk = [0 => 'Perempuan', 1 => 'Laki-laki'];
$map_status = [0 => 'Nonaktif', 1 => 'Aktif'];

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

// ============================================
// PROSES TAMBAH KARYAWAN
// ============================================
if (isset($_POST['add_karyawan'])) {
    $id_kry = $_POST['id_kry']; 
    $nama_kry = $_POST['nama']; 
    $jk = intval($_POST['jk']);
    $jabatan = $_POST['jabatan'];
    $telp = $_POST['telp']; 
    $status = intval($_POST['status']);
    $created_by = $_SESSION['nama'] ?? 'SYSTEM';
    $tempat_lahir = $_POST['tempat_lahir'];
    $tanggal_lahir = $_POST['tanggal_lahir'];
    $alamat = $_POST['alamat'];

    $checkID = safe_sqlsrv_query($conn, "SELECT ID_Karyawan FROM Karyawan WHERE ID_Karyawan=?", array($id_kry), false);
    if ($checkID && safe_sqlsrv_has_rows($checkID)) { 
        header("Location: karyawan.php?status=error&msg=ID Karyawan sudah terdaftar!"); 
        exit(); 
    }

    $stmt = safe_sqlsrv_query($conn, 
        "INSERT INTO Karyawan (ID_Karyawan, Nama_Karyawan, Tanggal_Lahir, Tempat_Lahir, Alamat, Jenis_Kelamin, Is_Deleted, Jabatan, No_Telepon, Email, Username, Kata_Sandi, Status, Is_Deleted2, Created_By, Created_Date) 
        VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, '', '', '', ?, 0, ?, GETDATE())", 
        array($id_kry, $nama_kry, $tanggal_lahir, $tempat_lahir, $alamat, $jk, $jabatan, $telp, $status, $created_by), false);

    header($stmt ? "Location: karyawan.php?status=success&msg=Karyawan baru berhasil didaftarkan!" : "Location: karyawan.php?status=error&msg=Gagal menambahkan data!"); 
    exit();
}

// ============================================
// PROSES UPDATE KARYAWAN
// ============================================
if (isset($_POST['update_karyawan'])) {
    $modified_by = $_SESSION['nama'] ?? 'SYSTEM';
    $tempat_lahir = $_POST['tempat_lahir'];
    $tanggal_lahir = $_POST['tanggal_lahir'];
    $alamat = $_POST['alamat'];
    $jk = intval($_POST['jk']);
    $jabatan = $_POST['jabatan'];
    $telp = $_POST['telp'];
    $status = intval($_POST['status']);

    $stmt = safe_sqlsrv_query($conn, 
        "UPDATE Karyawan SET 
            Nama_Karyawan=?, 
            Tanggal_Lahir=?, 
            Tempat_Lahir=?, 
            Alamat=?, 
            Jenis_Kelamin=?, 
            Jabatan=?, 
            No_Telepon=?, 
            Status=?,
            Modified_By=?, 
            Modified_Date=GETDATE() 
        WHERE ID_Karyawan=?", 
        array($_POST['nama'], $tanggal_lahir, $tempat_lahir, $alamat, $jk, $jabatan, $telp, $status, $modified_by, $_POST['id_kry']), false);

    header($stmt ? "Location: karyawan.php?status=success&msg=Data staf berhasil diperbarui!" : "Location: karyawan.php?status=error&msg=Gagal memperbarui data!"); 
    exit();
}

// ============================================
// PROSES TOGGLE STATUS (AJAX)
// ============================================
if (isset($_POST['toggle_status']) && isset($_POST['id_kry'])) {
    $modified_by = $_SESSION['nama'] ?? 'SYSTEM';
    $id_kry = $_POST['id_kry'];

    // Ambil status sekarang
    $check = safe_sqlsrv_query($conn, "SELECT Status FROM Karyawan WHERE ID_Karyawan=?", array($id_kry), false);
    if ($check && $row = safe_sqlsrv_fetch_array($check, SQLSRV_FETCH_ASSOC)) {
        $new_status = $row['Status'] == 1 ? 0 : 1;
        $stmt = safe_sqlsrv_query($conn, 
            "UPDATE Karyawan SET Status=?, Modified_By=?, Modified_Date=GETDATE() WHERE ID_Karyawan=?",
            array($new_status, $modified_by, $id_kry), false);
        echo json_encode(['success' => $stmt !== false, 'status' => $new_status]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit();
}

// ============================================
// PROSES DELETE (SOFT DELETE)
// ============================================
if (isset($_GET['delete_id'])) {
    $deleted_by = $_SESSION['nama'] ?? 'SYSTEM';
    $stmt = safe_sqlsrv_query($conn, 
        "UPDATE Karyawan SET 
            Is_Deleted=1, 
            Status=0,
            Deleted_By=?, 
            Deleted_Date=GETDATE() 
        WHERE ID_Karyawan=?", 
        array($deleted_by, $_GET['delete_id']), false);

    header($stmt ? "Location: karyawan.php?status=success&msg=Data karyawan telah dihapus!" : "Location: karyawan.php?status=error&msg=Gagal hapus!"); 
    exit();
}

// ============================================
// AMBIL DATA EDIT
// ============================================
$edit_data = null;
if (isset($_GET['edit_id'])) {
    $r = safe_sqlsrv_query($conn, "SELECT * FROM Karyawan WHERE ID_Karyawan=? AND Is_Deleted=0", array($_GET['edit_id']), false);
    if ($r) {
        $edit_data = safe_sqlsrv_fetch_array($r, SQLSRV_FETCH_ASSOC);
    }
}
$show_add = isset($_GET['add']) && $_GET['add'] == '1';

// ============================================
// FILTER & SORTING
// ============================================
$filter_jabatan = isset($_GET['filter_jabatan']) ? $_GET['filter_jabatan'] : '';
$filter_jk = isset($_GET['filter_jk']) ? intval($_GET['filter_jk']) : -1;
$filter_status = isset($_GET['filter_status']) ? intval($_GET['filter_status']) : -1;
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'ID_Karyawan';
$sort_order = isset($_GET['sort_order']) ? $_GET['sort_order'] : 'ASC';

$allowed_sort = ['ID_Karyawan', 'Nama_Karyawan', 'Jabatan', 'Jenis_Kelamin', 'No_Telepon', 'Status', 'Created_Date', 'Modified_Date'];
if (!in_array($sort_by, $allowed_sort)) {
    $sort_by = 'ID_Karyawan';
}
$sort_order = ($sort_order === 'DESC') ? 'DESC' : 'ASC';

// ============================================
// BUILD FILTER QUERY
// ============================================
$where_conditions = ["Is_Deleted = 0"];
$params = [];

if (!empty($filter_jabatan)) {
    $where_conditions[] = "Jabatan = ?";
    $params[] = $filter_jabatan;
}
if ($filter_jk >= 0) {
    $where_conditions[] = "Jenis_Kelamin = " . intval($filter_jk);
}
if ($filter_status >= 0) {
    $where_conditions[] = "Status = " . intval($filter_status);
}

$where_clause = implode(" AND ", $where_conditions);

// ============================================
// HITUNG TOTAL
// ============================================
$q_total = safe_sqlsrv_query($conn, "SELECT COUNT(*) as t FROM Karyawan WHERE " . $where_clause, $params, false);
$total_kry = 0;
if ($q_total !== false) {
    $row_total = safe_sqlsrv_fetch_array($q_total, SQLSRV_FETCH_ASSOC);
    $total_kry = $row_total['t'] ?? 0;
}

$q_total_aktif = safe_sqlsrv_query($conn, "SELECT COUNT(*) as t FROM Karyawan WHERE Status = 1 AND Is_Deleted = 0", [], false);
$total_aktif = 0;
if ($q_total_aktif !== false) {
    $row_aktif = safe_sqlsrv_fetch_array($q_total_aktif, SQLSRV_FETCH_ASSOC);
    $total_aktif = $row_aktif['t'] ?? 0;
}

// ============================================
// PAGINATION
// ============================================
$limit = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

$count_query = safe_sqlsrv_query($conn, "SELECT COUNT(*) as total FROM Karyawan WHERE " . $where_clause, $params, false);
$total_rows = 0;
if ($count_query !== false) {
    $count_row = safe_sqlsrv_fetch_array($count_query, SQLSRV_FETCH_ASSOC);
    $total_rows = $count_row['total'] ?? 0;
}

$total_pages = max(1, ceil($total_rows / $limit));
$page = min($page, $total_pages);
$offset = ($page - 1) * $limit;

// ============================================
// AMBIL DATA
// ============================================
$query_sql = "SELECT * FROM Karyawan WHERE " . $where_clause . " ORDER BY " . $sort_by . " " . $sort_order . " OFFSET " . intval($offset) . " ROWS FETCH NEXT " . intval($limit) . " ROWS ONLY";
$query = safe_sqlsrv_query($conn, $query_sql, $params, false);

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

// Helper format tanggal
function formatDate($date) {
    if (!$date) return '-';
    if (is_object($date) && method_exists($date, 'format')) {
        return $date->format('d M Y H:i');
    }
    return $date;
}

function formatDateOnly($date) {
    if (!$date) return '-';
    if (is_object($date) && method_exists($date, 'format')) {
        return $date->format('d M Y');
    }
    return $date;
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

/* ═══ TOGGLE SWITCH ═══ */
.toggle-switch { position: relative; display: inline-block; width: 44px; height: 24px; }
.toggle-switch input { opacity: 0; width: 0; height: 0; }
.toggle-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #E5E7EB; transition: .3s; border-radius: 24px; }
.toggle-slider:before { position: absolute; content: ''; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .3s; border-radius: 50%; box-shadow: 0 2px 4px rgba(0,0,0,.2); }
input:checked + .toggle-slider { background-color: var(--green); }
input:checked + .toggle-slider:before { transform: translateX(20px); }
input:disabled + .toggle-slider { opacity: 0.5; cursor: not-allowed; }
.toggle-label { font-size: 11px; font-weight: 800; margin-left: 8px; }

/* ═══ TOOLBAR & FILTER ═══ */
.toolbar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; flex-wrap: wrap; gap: 12px; }
.toolbar-left { display: flex; align-items: center; gap: 12px; }
.filter-dropdown-wrap { position: relative; display: inline-block; }
.btn-filter { display: inline-flex; align-items: center; gap: 8px; background-color: var(--orange); color: #ffffff !important; padding: 11px 20px; border-radius: 10px; font-size: 13px; font-weight: 800; text-transform: uppercase; border: none; cursor: pointer; transition: all 0.2s; box-shadow: 0 4px 12px rgba(255,69,0,0.2); }
.btn-filter:hover { background-color: var(--orange-dk) !important; color: #ffffff !important; transform: translateY(-2px); box-shadow: 0 6px 16px rgba(255,69,0,0.35); }
.btn-filter i.arrow-icon { font-size: 10px; transition: transform 0.3s; }
.btn-filter.active i.arrow-icon { transform: rotate(180deg); }
.filter-card { position: absolute; top: calc(100% + 10px); right: 0; background: #ffffff; border-radius: 16px; border: 1px solid var(--border); padding: 24px; width: 300px; box-shadow: 0 15px 35px rgba(0, 0, 0, 0.12); z-index: 50; display: none; }
.filter-card.open { display: block; animation: slideFilter 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
@keyframes slideFilter { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
.filter-card h4 { font-size: 15px; font-weight: 800; color: var(--text); margin-bottom: 20px; text-align: left; }
.filter-card .filter-group { margin-bottom: 16px; text-align: left; }
.filter-card .filter-group label { display: block; font-size: 11px; font-weight: 800; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; }
.filter-input { width: 100%; padding: 10px 14px; border: 1.5px solid var(--border); border-radius: 10px; font-size: 13px; font-family: 'Barlow', sans-serif; outline: none; transition: all .2s; color: var(--text); cursor: pointer; appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236B7280' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 14px center; padding-right: 40px; }
.filter-input:focus { border-color: var(--orange); }
.filter-buttons { display: flex; gap: 10px; margin-top: 24px; }
.btn-filter-apply { flex: 1.2; background: var(--orange); color: white; border: none; padding: 12px; border-radius: 10px; font-weight: 800; font-size: 12px; text-transform: uppercase; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px; transition: all .2s; }
.btn-filter-apply:hover { background: var(--orange-dk); }
.btn-filter-reset { flex: 1; background: var(--border-lt); color: var(--text-md); border: 1px solid var(--border); padding: 12px; border-radius: 10px; font-weight: 800; font-size: 12px; text-transform: uppercase; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px; transition: all .2s; }
.btn-filter-reset:hover { background: var(--bg); }

.btn-add { display: inline-flex !important; align-items: center !important; gap: 8px !important; background-color: var(--text) !important; color: #fff !important; padding: 10px 20px !important; border-radius: 10px !important; font-size: 12px !important; font-weight: 800 !important; text-decoration: none !important; text-transform: uppercase !important; transition: all .2s ease !important; border: none !important; cursor: pointer !important; }
.btn-add:hover { background-color: var(--orange) !important; transform: translateY(-2px) !important; box-shadow: 0 8px 20px rgba(255,69,0,.3) !important; }

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

/* ═══ ACTIONS ═══ */
.action-group { display: flex; gap: 8px; justify-content: flex-start; align-items: center; }
.btn-action { width: 34px; height: 34px; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; font-size: 13px; font-weight: 700; transition: all .25s cubic-bezier(.4,0,.2,1); border: 1.5px solid transparent; }
.btn-edit { background: linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%); color: #1E40AF; border-color: #BFDBFE; }
.btn-edit:hover { background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%); color: #fff; border-color: #3B82F6; transform: translateY(-2px); box-shadow: 0 6px 20px rgba(59,130,246,.35); }
.btn-delete { background: linear-gradient(135deg, #FEF2F2 0%, #FEE2E2 100%); color: #DC2626; border-color: #FECACA; }
.btn-delete:hover { background: linear-gradient(135deg, #EF4444 0%, #DC2626 100%); color: #fff; border-color: #EF4444; transform: translateY(-2px); box-shadow: 0 6px 20px rgba(239,68,68,.35); }
.btn-view { background: linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%); color: #1E40AF; border-color: #BFDBFE; }
.btn-view:hover { background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%); color: #fff; border-color: #3B82F6; transform: translateY(-2px); box-shadow: 0 6px 20px rgba(59,130,246,.35); }

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

/* ═══ MODALS ═══ */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.55); backdrop-filter: blur(6px); display: flex; justify-content: center; align-items: center; z-index: 2000; }
.modal-overlay.hidden { display: none; }
.modal-box { background: #fff; border-radius: 20px; width: 640px; max-width: 95vw; box-shadow: 0 25px 60px rgba(0,0,0,.2); position: relative; max-height: 90vh; overflow-y: auto; }
.modal-head { padding: 28px 32px 24px; border-bottom: 1px solid var(--border); }
.modal-tag { font-size: 10px; font-weight: 800; color: var(--orange); text-transform: uppercase; letter-spacing: .8px; margin-bottom: 6px; }
.modal-title { font-family: 'Barlow Condensed', sans-serif; font-size: 24px; font-weight: 900; color: var(--text); letter-spacing: -.3px; }
.modal-sub { font-size: 13px; color: var(--muted); margin-top: 4px; }
.modal-subtitle { font-size: 10px; font-weight: 800; color: var(--orange); text-transform: uppercase; margin-bottom: 6px; letter-spacing: .8px; }
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
.modal-input[readonly] { background: var(--border-lt); color: var(--muted); }
.btn-submit { grid-column: span 2; width: 100%; background: var(--orange); color: #fff; border: none; padding: 14px; border-radius: 10px; font-weight: 800; font-size: 14px; cursor: pointer; text-transform: uppercase; letter-spacing: .5px; transition: .2s; font-family: 'Barlow', sans-serif; margin-top: 8px; }
.btn-submit:hover { background: var(--orange-dk); transform: translateY(-1px); box-shadow: 0 8px 20px rgba(255,69,0,.25); }
.btn-submit:disabled { background: #D1D5DB; cursor: not-allowed; transform: none; box-shadow: none; }

.val-msg { font-size: 11px; color: var(--red); font-weight: 600; margin-bottom: 10px; display: none; min-height: 16px; }
.val-msg.show { display: block; }
.val-msg i { margin-right: 4px; }
.modal-input.error { border-color: var(--red) !important; background-color: #FEF2F2 !important; box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.15) !important; }
.modal-input.error:focus { border-color: var(--red) !important; box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.25) !important; }

/* ═══ DETAIL MODAL ═══ */
.detail-modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.55); backdrop-filter: blur(6px); display: none; align-items: center; justify-content: center; z-index: 2000; }
.detail-modal-overlay.open { display: flex; }
.detail-modal-box { background: #fff; border-radius: 20px; width: 480px; max-height: 90vh; overflow-y: auto; box-shadow: 0 25px 60px rgba(0,0,0,0.2); position: relative; }
.detail-modal-close { width: 36px; height: 36px; border-radius: 10px; background: var(--bg); border: 1.5px solid var(--border); color: var(--muted); display: flex; align-items: center; justify-content: center; cursor: pointer; transition: .2s; font-size: 14px; }
.detail-modal-close:hover { background: var(--red-lt); color: var(--red); border-color: var(--red); }
.detail-photo-card { text-align: center; margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1.5px dashed var(--border); }
.detail-icon-wrap { width: 80px; height: 80px; background: var(--orange-lt); color: var(--orange); border-radius: 20px; display: inline-flex; align-items: center; justify-content: center; font-size: 32px; margin-bottom: 16px; box-shadow: 0 8px 20px rgba(255,69,0,0.15); }
.detail-main-name { font-family: 'Barlow Condensed', sans-serif; font-size: 24px; font-weight: 900; color: var(--text); text-transform: uppercase; }

.info-row { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid var(--border-lt); }
.info-row:last-child { border-bottom: none; }
.info-key { display: flex; align-items: center; gap: 10px; font-size: 12px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: 0.3px; }
.info-key i { color: var(--orange); font-size: 14px; width: 18px; text-align: center; }
.info-val { font-size: 13px; font-weight: 700; color: var(--text); }
.info-val-mono { font-family: 'Barlow Condensed'; font-size: 14px; font-weight: 800; color: var(--orange); }
.audit-section { margin-top: 20px; padding-top: 16px; border-top: 2px dashed var(--border); }
.audit-title { font-size: 10px; font-weight: 800; color: var(--orange); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 12px; }
.audit-row { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid var(--border-lt); }
.audit-row:last-child { border-bottom: none; }
.audit-key { font-size: 11px; font-weight: 700; color: var(--muted); text-transform: uppercase; }
.audit-val { font-size: 12px; font-weight: 700; color: var(--text); }

.status-pill { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 8px; font-size: 12px; font-weight: 800; text-transform: uppercase; }
.sp-ready { background: var(--green-lt); color: var(--green); }
.sp-maint { background: var(--red-lt); color: var(--red); }

.btn-kembali { display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%; background: #0D1117; color: #fff; border: none; padding: 14px; border-radius: 12px; font-size: 14px; font-weight: 800; font-family: 'Barlow', sans-serif; text-transform: uppercase; cursor: pointer; transition: .2s; margin-top: 20px; }
.btn-kembali:hover { background: var(--orange); }

/* ═══ SEARCH BOX ═══ */
.search-box { position: relative; width: 300px; }
.search-box i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: 13px; }
.search-box input { width: 100%; padding: 10px 14px 10px 40px; background: var(--card-bg); border: 1.5px solid var(--border); border-radius: 10px; font-size: 13px; font-family: 'Barlow', sans-serif; outline: none; transition: all .2s; color: var(--text); }
.search-box input:focus { border-color: var(--orange); box-shadow: 0 0 0 3px var(--orange-lt); }
.search-box input::placeholder { color: #9CA3AF; }

.action-bar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; flex-wrap: wrap; gap: 12px; }
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
    .filter-card { width: 100%; right: 0; }
}
</style>
</head>
<body>
<!-- ═══ MODAL TAMBAH/EDIT ═══ -->
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
                    <div>
                        <label class="field-label">ID Karyawan <span class="required">*</span></label>
                        <input type="text" name="id_kry" id="id_kry" class="modal-input" value="<?= $edit_data['ID_Karyawan'] ?? '' ?>" <?= $edit_data ? 'readonly' : 'required' ?> placeholder="KRY00001">
                        <div class="val-msg" id="val-id_kry"><i class="fa-solid fa-circle-exclamation"></i> ID Karyawan wajib diisi</div>
                    </div>
                    <div>
                        <label class="field-label">Nama Lengkap <span class="required">*</span></label>
                        <input type="text" name="nama" id="nama" class="modal-input" value="<?= htmlspecialchars($edit_data['Nama_Karyawan'] ?? '') ?>" required minlength="3" maxlength="100" placeholder="Nama lengkap">
                        <div class="val-msg" id="val-nama"><i class="fa-solid fa-circle-exclamation"></i> Nama minimal 3 karakter</div>
                    </div>
                    <div>
                        <label class="field-label">Tempat Lahir <span class="required">*</span></label>
                        <input type="text" name="tempat_lahir" id="tempat_lahir" class="modal-input" value="<?= htmlspecialchars($edit_data['Tempat_Lahir'] ?? '') ?>" required minlength="2" maxlength="50" placeholder="Contoh: Jakarta, Bandung">
                        <div class="val-msg" id="val-tempat_lahir"><i class="fa-solid fa-circle-exclamation"></i> Tempat lahir wajib diisi</div>
                    </div>
                    <div>
                        <label class="field-label">Tanggal Lahir <span class="required">*</span></label>
                        <input type="date" name="tanggal_lahir" id="tanggal_lahir" class="modal-input" value="<?php 
                            $tgl = $edit_data['Tanggal_Lahir'] ?? '';
                            if ($tgl && is_object($tgl)) echo $tgl->format('Y-m-d');
                            elseif ($tgl) echo htmlspecialchars($tgl);
                        ?>" required max="<?= date('Y-m-d') ?>" onchange="validateDate(this)">
                        <div class="val-msg" id="val-tanggal_lahir"><i class="fa-solid fa-circle-exclamation"></i> Tanggal lahir wajib diisi dan tidak boleh di masa depan</div>
                    </div>
                    <div class="full-width">
                        <label class="field-label">Alamat Lengkap <span class="required">*</span></label>
                        <textarea name="alamat" id="alamat" class="modal-input" required rows="3" placeholder="Jl. Merdeka No. 10, Jakarta Pusat" style="resize: none;"><?= htmlspecialchars($edit_data['Alamat'] ?? '') ?></textarea>
                        <div class="val-msg" id="val-alamat"><i class="fa-solid fa-circle-exclamation"></i> Alamat wajib diisi</div>
                    </div>
                    <div>
                        <label class="field-label">Jenis Kelamin <span class="required">*</span></label>
                        <select name="jk" id="jk" class="modal-input" required>
                            <option value="1" <?= (isset($edit_data['Jenis_Kelamin']) && $edit_data['Jenis_Kelamin']==1)?'selected':''?>>Laki-laki</option>
                            <option value="0" <?= (isset($edit_data['Jenis_Kelamin']) && $edit_data['Jenis_Kelamin']==0)?'selected':''?>>Perempuan</option>
                        </select>
                    </div>
                    <div>
                        <label class="field-label">Jabatan <span class="required">*</span></label>
                        <select name="jabatan" id="jabatan" class="modal-input" required>
                            <option value="Manajer" <?= (isset($edit_data['Jabatan']) && $edit_data['Jabatan']=='Manajer')?'selected':''?>>Manajer</option>
                            <option value="Karyawan" <?= (isset($edit_data['Jabatan']) && $edit_data['Jabatan']=='Karyawan')?'selected':''?>>Karyawan</option>
                            <option value="Kasir" <?= (isset($edit_data['Jabatan']) && $edit_data['Jabatan']=='Kasir')?'selected':''?>>Kasir</option>
                            <option value="Staf" <?= (isset($edit_data['Jabatan']) && $edit_data['Jabatan']=='Staf')?'selected':''?>>Staf</option>
                            <option value="Keamanan" <?= (isset($edit_data['Jabatan']) && $edit_data['Jabatan']=='Keamanan')?'selected':''?>>Keamanan</option>
                        </select>
                    </div>
                    <div>
                        <label class="field-label">Nomor Telepon <span class="required">*</span></label>
                        <input type="text" name="telp" id="telp" class="modal-input" value="<?= htmlspecialchars($edit_data['No_Telepon'] ?? '') ?>" placeholder="08123456789" required pattern="[0-9]{10,15}" maxlength="15">
                        <div class="val-msg" id="val-telp"><i class="fa-solid fa-circle-exclamation"></i> Nomor telepon 10-15 digit</div>
                    </div>
                    <div>
                        <label class="field-label">Status <span class="required">*</span></label>
                        <select name="status" id="status" class="modal-input" required>
                            <option value="1" <?= (isset($edit_data['Status']) && $edit_data['Status']==1)?'selected':''?>>Aktif</option>
                            <option value="0" <?= (isset($edit_data['Status']) && $edit_data['Status']==0)?'selected':''?>>Nonaktif</option>
                        </select>
                    </div>
                    <button type="submit" name="<?= $edit_data ? 'update_karyawan' : 'add_karyawan' ?>" class="btn-submit">
                        <i class="fa-solid <?= $edit_data ? 'fa-floppy-disk' : 'fa-user-plus' ?>"></i>
                        <?= $edit_data ? 'Simpan Perubahan' : 'Daftarkan Karyawan' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ═══ DETAIL MODAL ═══ -->
<div class="detail-modal-overlay" id="detailModal" onclick="closeDetail(event)">
    <div class="detail-modal-box" onclick="event.stopPropagation()">
        <div style="position: sticky; top: 0; background: white; z-index: 20; padding: 24px 28px 15px; border-bottom: 1px solid rgba(0,0,0,0.03);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <div class="modal-subtitle" style="letter-spacing: 1px;">Informasi Karyawan</div>
                    <div class="modal-title" style="line-height: 1;">Profil Karyawan</div>
                </div>
                <button class="detail-modal-close" onclick="closeDetail()" title="Tutup">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
        </div>

        <div style="padding: 10px 28px 28px;">
            <div class="detail-photo-card">
                <div class="detail-icon-wrap"><i class="fa-solid fa-user-tie"></i></div>
                <div class="detail-main-name" id="dNameHeader">-</div>
            </div>

            <div style="display: flex; flex-direction: column; gap: 2px;">
                <div class="info-row">
                    <span class="info-key"><i class="fa-solid fa-fingerprint"></i> ID Karyawan</span>
                    <span class="info-val-mono" id="dId">-</span>
                </div>
                <div class="info-row">
                    <span class="info-key"><i class="fa-solid fa-user"></i> Nama Lengkap</span>
                    <span class="info-val" id="dNama">-</span>
                </div>
                <div class="info-row">
                    <span class="info-key"><i class="fa-solid fa-location-dot"></i> Tempat Lahir</span>
                    <span class="info-val" id="dTempatLahir">-</span>
                </div>
                <div class="info-row">
                    <span class="info-key"><i class="fa-solid fa-calendar-day"></i> Tanggal Lahir</span>
                    <span class="info-val" id="dTanggalLahir">-</span>
                </div>
                <div class="info-row" style="align-items: flex-start;">
                    <span class="info-key"><i class="fa-solid fa-map-location-dot"></i> Alamat</span>
                    <span class="info-val" id="dAlamat" style="text-align: right; max-width: 220px; line-height: 1.4;">-</span>
                </div>
                <div class="info-row">
                    <span class="info-key"><i class="fa-solid fa-venus-mars"></i> Jenis Kelamin</span>
                    <span class="info-val" id="dJK">-</span>
                </div>
                <div class="info-row">
                    <span class="info-key"><i class="fa-solid fa-briefcase"></i> Jabatan</span>
                    <span class="info-val" id="dJabatan">-</span>
                </div>
                <div class="info-row">
                    <span class="info-key"><i class="fa-solid fa-phone"></i> No. Telepon</span>
                    <span class="info-val" id="dTelp">-</span>
                </div>
                <div class="info-row" style="border-bottom: none;">
                    <span class="info-key"><i class="fa-solid fa-circle-check"></i> Status</span>
                    <span class="info-val" id="dStatus">-</span>
                </div>
            </div>

            <!-- AUDIT TRAIL SECTION -->
            <div class="audit-section">
                <div class="audit-title"><i class="fa-solid fa-clock-rotate-left"></i> Audit Trail</div>
                <div class="audit-row">
                    <span class="audit-key">Dibuat Oleh</span>
                    <span class="audit-val" id="dCreatedBy">-</span>
                </div>
                <div class="audit-row">
                    <span class="audit-key">Tanggal Dibuat</span>
                    <span class="audit-val" id="dCreatedDate">-</span>
                </div>
                <div class="audit-row">
                    <span class="audit-key">Diubah Oleh</span>
                    <span class="audit-val" id="dModifiedBy">-</span>
                </div>
                <div class="audit-row">
                    <span class="audit-key">Tanggal Diubah</span>
                    <span class="audit-val" id="dModifiedDate">-</span>
                </div>
                <div class="audit-row" style="border-bottom: none;">
                    <span class="audit-key">Dihapus Oleh</span>
                    <span class="audit-val" id="dDeletedBy">-</span>
                </div>
            </div>

            <button onclick="closeDetail()" class="btn-kembali">
                <i class="fa-solid fa-arrow-left"></i> KEMBALI KE LIST
            </button>
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
<!-- ═══ SIDEBAR ═══ -->
<aside class="sidebar">
    <a href="../view_pemilik.php" class="sb-brand">
        <div class="sb-icon"><i class="fa-solid fa-basketball"></i></div>
        <div><div class="sb-brand-name">HOOP BALL</div><div class="sb-brand-sub">Management System</div></div>
    </a>
    <div class="sb-section-label">Manajemen</div>
    <nav>
        <a href="../view_pemilik.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-house"></i></div> Dashboard</a>
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

<!-- ═══ MAIN CONTENT ═══ -->
<main class="main">
    <header class="topbar">
        <div class="topbar-left">
            <div class="topbar-title">Kelola Data Karyawan</div>
            <div class="topbar-breadcrumb">Manajemen / Karyawan</div>
        </div>
        <div class="topbar-right">
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

        <!-- STAT CARDS -->
        <div class="stat-grid">
            <div class="stat-card sc-purple">
                <div class="stat-header"><div class="stat-icon-wrap si-purple"><i class="fa-solid fa-user-tie"></i></div><div class="stat-trend trend-up"><i class="fa-solid fa-arrow-up"></i> Aktif</div></div>
                <div class="stat-value"><?= $total_kry ?></div><div class="stat-label">Total Karyawan</div><div class="stat-sublabel">Terdaftar di sistem</div>
            </div>
            <div class="stat-card sc-blue">
                <div class="stat-header"><div class="stat-icon-wrap si-blue"><i class="fa-solid fa-users"></i></div><div class="stat-trend trend-up"><i class="fa-solid fa-arrow-up"></i> Total</div></div>
                <div class="stat-value"><?= $total_aktif ?></div><div class="stat-label">Karyawan Aktif</div><div class="stat-sublabel">Status aktif saat ini</div>
            </div>
            <div class="stat-card sc-green">
                <div class="stat-header"><div class="stat-icon-wrap si-green"><i class="fa-solid fa-briefcase"></i></div><div class="stat-trend trend-up"><i class="fa-solid fa-arrow-up"></i> Posisi</div></div>
                <div class="stat-value">5</div><div class="stat-label">Jenis Jabatan</div><div class="stat-sublabel">Posisi tersedia di sistem</div>
            </div>
        </div>

        <!-- ACTION BAR -->
        <div class="action-bar">
            <div class="search-box">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="src" placeholder="Cari nama karyawan..." onkeyup="searchTable()">
            </div>

            <div style="display: flex; gap: 12px; align-items: center;">
                <div class="filter-dropdown-wrap">
                    <button class="btn-filter" id="btnFilterToggle">
                        <i class="fa-solid fa-filter"></i> Filter <i class="fa-solid fa-chevron-down arrow-icon"></i>
                    </button>
                    <div class="filter-card" id="filterCard">
                        <h4>Filter Data</h4>
                        <div class="filter-group">
                            <label>Urut Berdasarkan</label>
                            <select id="filterSortBy" class="filter-input">
                                <option value="ID_Karyawan" <?= $sort_by == 'ID_Karyawan' ? 'selected' : '' ?>>ID Karyawan</option>
                                <option value="Nama_Karyawan" <?= $sort_by == 'Nama_Karyawan' ? 'selected' : '' ?>>Nama Lengkap</option>
                                <option value="Jabatan" <?= $sort_by == 'Jabatan' ? 'selected' : '' ?>>Jabatan</option>
                                <option value="Jenis_Kelamin" <?= $sort_by == 'Jenis_Kelamin' ? 'selected' : '' ?>>Jenis Kelamin</option>
                                <option value="No_Telepon" <?= $sort_by == 'No_Telepon' ? 'selected' : '' ?>>No. Telepon</option>
                                <option value="Status" <?= $sort_by == 'Status' ? 'selected' : '' ?>>Status</option>
                                <option value="Created_Date" <?= $sort_by == 'Created_Date' ? 'selected' : '' ?>>Tanggal Dibuat</option>
                                <option value="Modified_Date" <?= $sort_by == 'Modified_Date' ? 'selected' : '' ?>>Tanggal Diubah</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Jabatan</label>
                            <select id="filterJabatan" class="filter-input">
                                <option value="" <?= empty($filter_jabatan) ? 'selected' : '' ?>>Semua Jabatan</option>
                                <option value="Manajer" <?= $filter_jabatan == 'Manajer' ? 'selected' : '' ?>>Manajer</option>
                                <option value="Karyawan" <?= $filter_jabatan == 'Karyawan' ? 'selected' : '' ?>>Karyawan</option>
                                <option value="Kasir" <?= $filter_jabatan == 'Kasir' ? 'selected' : '' ?>>Kasir</option>
                                <option value="Staf" <?= $filter_jabatan == 'Staf' ? 'selected' : '' ?>>Staf</option>
                                <option value="Keamanan" <?= $filter_jabatan == 'Keamanan' ? 'selected' : '' ?>>Keamanan</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Jenis Kelamin</label>
                            <select id="filterJK" class="filter-input">
                                <option value="-1" <?= $filter_jk == -1 ? 'selected' : '' ?>>Semua</option>
                                <option value="1" <?= $filter_jk == 1 ? 'selected' : '' ?>>Laki-laki</option>
                                <option value="0" <?= $filter_jk == 0 ? 'selected' : '' ?>>Perempuan</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Status</label>
                            <select id="filterStatus" class="filter-input">
                                <option value="-1" <?= $filter_status == -1 ? 'selected' : '' ?>>Semua</option>
                                <option value="1" <?= $filter_status == 1 ? 'selected' : '' ?>>Aktif</option>
                                <option value="0" <?= $filter_status == 0 ? 'selected' : '' ?>>Nonaktif</option>
                            </select>
                        </div>
                        <div class="filter-buttons">
                            <button type="button" class="btn-filter-reset" onclick="resetFilter()"><i class="fa-solid fa-rotate-left"></i> Reset</button>
                            <button type="button" class="btn-filter-apply" onclick="applyFilter()"><i class="fa-solid fa-check"></i> Terapkan</button>
                        </div>
                    </div>
                </div>
                <button class="btn-add" onclick="openModal()"><i class="fa-solid fa-plus"></i> Tambah Karyawan</button>
            </div>
        </div>

        <!-- TABLE -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fa-solid fa-user-tie"></i> Data Karyawan</div>
                <span class="card-badge"><?= $total_kry ?> total</span>
            </div>
            <?php if ($query_error): ?>
            <div style="padding:20px;background:#fee;border:1px solid #fcc;border-radius:8px;margin:20px 0;">
                <p style="color:#c00;font-weight:bold;margin:0;"><i class="fa-solid fa-circle-exclamation"></i> Gagal mengambil data dari database.</p>
                <p style="color:#666;font-size:11px;margin:5px 0 0;">Error: <?= htmlspecialchars($query_error_msg) ?></p>
            </div>
            <?php else: ?>
            <div class="table-wrap">
                <table class="data-table" id="tbl">
                    <thead>
                        <tr>
                            <th style="width:50px;text-align:center;">No</th>
                            <th>Nama Lengkap</th>
                            <th>Jabatan</th>
                            <th>No. Telepon</th>
                            <th style="text-align:center;">Status</th>
                            <th style="text-align:left; width:160px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php 
                    $row_num = 0; 
                    $has_data = false; 
                    if (!$query_error && $query): 
                        while ($row = safe_sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)): 
                            $row_num++; 
                            $has_data = true; 
                            $status_label = $map_status[$row['Status']] ?? 'Tidak diketahui';
                            $is_active = $row['Status'] == 1;
                    ?>
                        <tr class="row-<?= $row_num % 2 == 1 ? 'odd' : 'even' ?>" id="row-<?= htmlspecialchars($row['ID_Karyawan']) ?>">
                            <td class="row-num"><?= $row_num ?></td>
                            <td>
                                <div class="emp-name"><?= htmlspecialchars($row['Nama_Karyawan']) ?></div>
                                <div class="cell-detail"><?= $row['Jenis_Kelamin'] == 1 ? '♂ Laki-laki' : '♀ Perempuan' ?> &bull; <?= htmlspecialchars($row['ID_Karyawan']) ?></div>
                            </td>
                            <td><span class="jabatan-badge"><?= htmlspecialchars($row['Jabatan']) ?></span></td>
                            <td style="color:var(--muted); font-weight:600;"><?= htmlspecialchars($row['No_Telepon']) ?></td>
                            <td style="text-align:center;">
                                <label class="toggle-switch" title="Klik untuk toggle status">
                                    <input type="checkbox" <?= $is_active ? 'checked' : '' ?> onchange="toggleStatus('<?= htmlspecialchars($row['ID_Karyawan']) ?>', this)">
                                    <span class="toggle-slider"></span>
                                </label>
                                <div class="toggle-label" id="label-<?= htmlspecialchars($row['ID_Karyawan']) ?>" style="color: <?= $is_active ? 'var(--green)' : 'var(--red)' ?>;"><?= strtoupper($status_label) ?></div>
                            </td>
                            <td>
                                <div class="action-group">
                                    <button onclick="openDetail(
                                        '<?= htmlspecialchars($row['ID_Karyawan']) ?>',
                                        '<?= htmlspecialchars($row['Nama_Karyawan']) ?>',
                                        '<?= $row['Jenis_Kelamin'] ?>',
                                        '<?= htmlspecialchars($row['Tempat_Lahir'] ?? '') ?>',
                                        '<?php $tgl = $row['Tanggal_Lahir'] ?? ''; if ($tgl && is_object($tgl)) echo $tgl->format('Y-m-d'); elseif ($tgl) echo htmlspecialchars($tgl); ?>',
                                        '<?= htmlspecialchars($row['Jabatan']) ?>',
                                        '<?= htmlspecialchars($row['No_Telepon']) ?>',
                                        '<?= $status_label ?>',
                                        '<?= addslashes($row['Alamat'] ?? '') ?>',
                                        '<?= htmlspecialchars($row['Created_By'] ?? 'SYSTEM') ?>',
                                        '<?= formatDate($row['Created_Date'] ?? null) ?>',
                                        '<?= htmlspecialchars($row['Modified_By'] ?? '-') ?>',
                                        '<?= formatDate($row['Modified_Date'] ?? null) ?>',
                                        '<?= htmlspecialchars($row['Deleted_By'] ?? '-') ?>',
                                        '<?= formatDate($row['Deleted_Date'] ?? null) ?>'
                                    )" class="btn-action btn-view" title="Lihat Detail"><i class="fa-solid fa-eye"></i></button>
                                    <button onclick="openDetail('<?= htmlspecialchars($row['ID_Karyawan']) ?>','<?= htmlspecialchars($row['Nama_Karyawan']) ?>','<?= $row['Jenis_Kelamin'] ?>','<?= htmlspecialchars($row['Tempat_Lahir'] ?? '') ?>','<?= $row['Tanggal_Lahir'] ? $row['Tanggal_Lahir']->format('Y-m-d') : '' ?>','<?= $map_jabatan[$row['Jabatan']] ?? 'Staf' ?>','<?= htmlspecialchars($row['No_Telepon']) ?>','<?= htmlspecialchars($row['Status'] ?? 'Aktif') ?>','<?= $row['Created_Date'] ? $row['Created_Date']->format('d/m/Y H:i') : '-' ?>')" class="btn-action btn-view" title="Lihat Detail"><i class="fa-solid fa-eye"></i></button>
                                    <a href="?page=<?= $page ?>&edit_id=<?= $row['ID_Karyawan'] ?>" class="btn-action btn-edit" title="Edit Data"><i class="fa-solid fa-pen-to-square"></i></a>
                                    <button type="button" class="btn-action btn-delete" onclick="confirmDelete('<?= $row['ID_Karyawan'] ?>', '<?= htmlspecialchars($row['Nama_Karyawan']) ?>')" title="Hapus"><i class="fa-solid fa-trash-can"></i></button>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; endif; ?>
                    <?php if (!$has_data): ?>
                        <tr><td colspan="6"><div class="empty-state"><i class="fa-solid fa-user-tie"></i><p>Belum ada data karyawan terdaftar</p></div></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- PAGINATION -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination-wrap">
            <div class="pagination-info">Menampilkan <strong><?= (($page - 1) * $limit) + 1 ?></strong> - <strong><?= min($page * $limit, $total_rows) ?></strong> dari <strong><?= $total_rows ?></strong> data</div>
            <div class="pagination-nav">
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>" class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>"><i class="fa-solid fa-angles-left"></i></a>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>"><i class="fa-solid fa-angle-left"></i></a>
                <?php 
                $start_page = max(1, $page - 2); 
                $end_page = min($total_pages, $page + 2); 
                if ($end_page - $start_page < 4 && $total_pages >= 5) { 
                    if ($start_page == 1) $end_page = min(5, $total_pages); 
                    else $start_page = max(1, $total_pages - 4); 
                } 
                if ($start_page > 1): 
                ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>" class="page-btn">1</a>
                <?php if ($start_page > 2): ?><span class="page-ellipsis">...</span><?php endif; ?>
                <?php endif; ?>
                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" class="page-btn <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <?php if ($end_page < $total_pages): ?>
                <?php if ($end_page < $total_pages - 1): ?><span class="page-ellipsis">...</span><?php endif; ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $total_pages])) ?>" class="page-btn"><?= $total_pages ?></a>
                <?php endif; ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>"><i class="fa-solid fa-angle-right"></i></a>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $total_pages])) ?>" class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>"><i class="fa-solid fa-angles-right"></i></a>
            </div>
        </div>
        <?php else: ?>
        <div class="pagination-wrap">
            <div class="pagination-info">Menampilkan <strong>1</strong> - <strong><?= $total_rows ?></strong> dari <strong><?= $total_rows ?></strong> data</div>
        </div>
        <?php endif; ?>
    </div>
</main>
<script>
// ═══ MODAL FUNCTIONS ═══
function openModal()  { document.getElementById('modal').classList.remove('hidden'); }
function closeModal() { window.location.href = 'karyawan.php'; }

function confirmDelete(id, nama) {
    Swal.fire({
        title: 'Hapus Karyawan?',
        html: `Karyawan <strong style="color:#FF4500;">${nama}</strong> (${id}) akan dihapus <strong style="color:#DC2626;">(soft delete)</strong>!`,
        icon: 'warning', showCancelButton: true, confirmButtonColor: '#EF4444', cancelButtonColor: '#6B7280',
        confirmButtonText: 'Ya, Hapus!', cancelButtonText: 'Batal', reverseButtons: true, borderRadius: '16px'
    }).then((r) => { if (r.isConfirmed) window.location.href = '?page=<?= $page ?>&delete_id=' + id; });
}

// ═══ TOGGLE STATUS (AJAX) ═══
function toggleStatus(id, checkbox) {
    const label = document.getElementById('label-' + id);
    checkbox.disabled = true;

    fetch('karyawan.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'toggle_status=1&id_kry=' + encodeURIComponent(id)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const isActive = data.status == 1;
            label.textContent = isActive ? 'AKTIF' : 'NONAKTIF';
            label.style.color = isActive ? 'var(--green)' : 'var(--red)';
            checkbox.checked = isActive;
            Swal.fire({
                icon: 'success', title: 'Berhasil!', 
                text: 'Status berhasil diubah menjadi ' + (isActive ? 'Aktif' : 'Nonaktif'),
                timer: 1500, showConfirmButton: false, iconColor: '#FF4500'
            });
        } else {
            checkbox.checked = !checkbox.checked;
            Swal.fire({ icon: 'error', title: 'Gagal!', text: 'Gagal mengubah status', timer: 2000, showConfirmButton: false });
        }
    })
    .catch(err => {
        checkbox.checked = !checkbox.checked;
        Swal.fire({ icon: 'error', title: 'Error!', text: 'Terjadi kesalahan koneksi', timer: 2000, showConfirmButton: false });
    })
    .finally(() => { checkbox.disabled = false; });
}

// ═══ DETAIL MODAL ═══
function openDetail(id, nama, jk, tempatLahir, tanggalLahir, jabatan, telp, status, alamat, createdBy, createdDate, modifiedBy, modifiedDate, deletedBy, deletedDate) {
    const mapJK = { '1': 'LAKI-LAKI', '0': 'PEREMPUAN' };
function openDetail(id, nama, jk, tempatLahir, tanggalLahir, jabatan, telp, status, createdDate) {
    const mapJK = { '1': 'Laki-laki', '2': 'Perempuan' };
    const months = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    document.getElementById('dId').textContent = id;
    document.getElementById('dName').textContent = nama;
    document.getElementById('dNama').textContent = nama;
    document.getElementById('dJK').innerHTML = '<span style="display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:8px;font-size:12px;font-weight:800;text-transform:uppercase;background:' + (jk == '1' ? 'var(--blue-lt);color:var(--blue)' : 'var(--pink-lt);color:var(--pink)') + '"><i class="fa-solid ' + (jk == '1' ? 'fa-mars' : 'fa-venus') + '"></i> ' + (mapJK[jk] || '-') + '</span>';
    document.getElementById('dTempatLahir').textContent = tempatLahir || '-';
    document.getElementById('dAlamat').textContent = alamat || '-';
    document.getElementById('dTelp').textContent = telp || '-';

    if (tanggalLahir) {
        const d = new Date(tanggalLahir);
        if (!isNaN(d)) document.getElementById('dTanggalLahir').textContent = d.getDate() + ' ' + months[d.getMonth()] + ' ' + d.getFullYear();
        else document.getElementById('dTanggalLahir').textContent = tanggalLahir;
    } else {
        document.getElementById('dTanggalLahir').textContent = '-';
    }

    const jkColor = jk == '1' ? '#3B82F6' : '#EC4899';
    const jkBg = jk == '1' ? '#EFF6FF' : '#FDF2F8';
    document.getElementById('dJK').innerHTML = `<span class="status-pill" style="background: ${jkBg}; color: ${jkColor}; padding: 6px 12px; border-radius: 8px; font-size: 11px; font-weight: 800;">${mapJK[jk] || '-'}</span>`;
    document.getElementById('dJabatan').innerHTML = `<span class="jabatan-badge">${jabatan}</span>`;

    const isAktif = (status === 'Aktif');
    document.getElementById('dStatus').innerHTML = `
        <span class="status-pill ${isAktif ? 'sp-ready' : 'sp-maint'}" style="background: ${isAktif ? 'var(--green-lt)' : 'var(--red-lt)'}; color: ${isAktif ? 'var(--green)' : 'var(--red)'}; padding: 6px 12px; border-radius: 8px; font-size: 11px; font-weight: 800; display: inline-flex; align-items: center; gap: 5px;">
            <i class="fa-solid ${isAktif ? 'fa-circle-check' : 'fa-circle-xmark'}"></i> ${status.toUpperCase()}
        </span>`;

    // Audit Trail
    document.getElementById('dCreatedBy').textContent = createdBy || 'SYSTEM';
    document.getElementById('dCreatedDate').textContent = createdDate || '-';
    document.getElementById('dModifiedBy').textContent = modifiedBy || '-';
    document.getElementById('dModifiedDate').textContent = modifiedDate || '-';
    document.getElementById('dDeletedBy').textContent = deletedBy || '-';

    document.getElementById('detailModal').style.display = 'flex';
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

// ═══ SEARCH TABLE ═══
function searchTable() {
    var input = document.getElementById('src').value.toUpperCase();
    var rows = document.getElementById('tbl').getElementsByTagName('tr');
    for (var i = 1; i < rows.length; i++) {
        var tds = rows[i].getElementsByTagName('td');
        if (tds.length < 6) continue;
        var match = false;
        for (var j = 1; j <= 3; j++) {
            if (tds[j] && tds[j].textContent.toUpperCase().indexOf(input) > -1) { match = true; break; }
        }
        rows[i].style.display = match ? '' : 'none';
    }
}

// ═══ FILTER FUNCTIONS ═══
function applyFilter() {
    const sortBy = document.getElementById('filterSortBy').value;
    const jabatan = document.getElementById('filterJabatan').value;
    const jk = document.getElementById('filterJK').value;
    const status = document.getElementById('filterStatus').value;
    const params = new URLSearchParams(window.location.search);
    params.set('sort_by', sortBy);
    params.set('filter_jabatan', jabatan);
    params.set('filter_jk', jk);
    params.set('filter_status', status);
    params.set('page', '1');
    window.location.href = 'karyawan.php?' + params.toString();
}
function resetFilter() { window.location.href = 'karyawan.php'; }

// ═══ FILTER TOGGLE ═══
const btnFilterToggle = document.getElementById('btnFilterToggle');
const filterCard = document.getElementById('filterCard');
if (btnFilterToggle && filterCard) {
    btnFilterToggle.addEventListener('click', function(e) {
        e.stopPropagation();
        this.classList.toggle('active');
        filterCard.classList.toggle('open');
    });
    filterCard.addEventListener('click', function(e) { e.stopPropagation(); });
    document.addEventListener('click', function() {
        btnFilterToggle.classList.remove('active');
        filterCard.classList.remove('open');
    });
}

// ═══ VALIDATION ═══
function validateDate(input) {
    const selected = new Date(input.value);
    const today = new Date(); today.setHours(0,0,0,0);
    const valMsg = document.getElementById('val-tanggal_lahir');
    if (!input.value) { if (valMsg) { valMsg.classList.add('show'); input.classList.add('error'); } return false; }
    if (selected > today) { if (valMsg) { valMsg.textContent = '⚠ Tanggal lahir tidak boleh di masa depan!'; valMsg.classList.add('show'); input.classList.add('error'); } return false; }
    const age = today.getFullYear() - selected.getFullYear();
    if (age < 17) { if (valMsg) { valMsg.textContent = '⚠ Karyawan harus berusia minimal 17 tahun!'; valMsg.classList.add('show'); input.classList.add('error'); } return false; }
    if (age > 60) { if (valMsg) { valMsg.textContent = '⚠ Usia maksimal 60 tahun!'; valMsg.classList.add('show'); input.classList.add('error'); } return false; }
    if (valMsg) { valMsg.classList.remove('show'); input.classList.remove('error'); }
    return true;
}

function validateForm(form) {
    let valid = true;
    const inputs = form.querySelectorAll('.modal-input[required]');
    inputs.forEach(input => {
        const valMsg = document.getElementById('val-' + input.id);
        if (input.type === 'date' && input.value) { if (!validateDate(input)) valid = false; }
        else if (!input.checkValidity()) { if (valMsg) valMsg.classList.add('show'); input.classList.add('error'); valid = false; }
        else { if (valMsg) valMsg.classList.remove('show'); input.classList.remove('error'); }
    });
    return valid;
}

document.addEventListener('DOMContentLoaded', function() {
    const inputs = document.querySelectorAll('.modal-input');
    inputs.forEach(input => {
        input.addEventListener('input', function() {
            const valMsg = document.getElementById('val-' + this.id);
            if (valMsg) { if (!this.checkValidity() && this.value !== '') { valMsg.classList.add('show'); this.classList.add('error'); } else { valMsg.classList.remove('show'); this.classList.remove('error'); } }
        });
        input.addEventListener('blur', function() {
            const valMsg = document.getElementById('val-' + this.id);
            if (valMsg) { if (!this.checkValidity()) { valMsg.classList.add('show'); this.classList.add('error'); } else { valMsg.classList.remove('show'); this.classList.remove('error'); } }
        });
    });
});

// ═══ CLOCK ═══
function updateClock() {
    const now = new Date();
    document.getElementById('h').innerText = String(now.getHours()).padStart(2, '0');
    document.getElementById('m').innerText = String(now.getMinutes()).padStart(2, '0');
    document.getElementById('s').innerText = String(now.getSeconds()).padStart(2, '0');
    const days = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    const months = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    document.getElementById('full-date').innerText = `${days[now.getDay()]}, ${now.getDate()} ${months[now.getMonth()]} ${now.getFullYear()}`;
}
setInterval(updateClock, 1000); updateClock();

// ═══ SWEET ALERT ═══
const urlParams = new URLSearchParams(window.location.search);
const status = urlParams.get('status');
const msg = urlParams.get('msg');
if (status && msg) {
    Swal.fire({ icon: status === 'success' ? 'success' : 'error', title: status === 'success' ? 'Berhasil!' : 'Gagal!', text: msg, timer: 3000, showConfirmButton: false, iconColor: '#FF4500' });
    window.history.replaceState({}, document.title, window.location.pathname);
}

// ═══ KEYBOARD SHORTCUTS ═══
window.onclick = function(e) { if (e.target == document.getElementById('modal')) closeModal(); };
document.addEventListener('keydown', function(e) { 
    if (e.key === 'Escape') { closeDetail(); if (btnFilterToggle) btnFilterToggle.classList.remove('active'); if (filterCard) filterCard.classList.remove('open'); }
});
</script>
</body>
</html>