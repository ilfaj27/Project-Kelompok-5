<?php
session_start();
include '../includes/config.php';

// 1. PROTEKSI AKSES
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'pemilik') {
    echo "<script>alert('Akses Ditolak!'); window.location='../dashboard.php';</script>";
    exit();
}

$nama_user = $_SESSION['nama'];
$role_user = $_SESSION['role'];

// ═══════════════════════════════════════════
// HELPER: Get Profile Photo Path
// ═══════════════════════════════════════════
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

// --- 2. LOGIKA MAPPING ROLE ---
$current_filter = isset($_GET['role']) ? $_GET['role'] : 'all';
$role_map = ['manajer' => 1, 'karyawan' => 2, 'customer' => 3];

// --- FILTER PARAMETERS ---
$filter_status = isset($_GET['filter_status']) ? $_GET['filter_status'] : 'all';
$filter_role = isset($_GET['filter_role']) ? $_GET['filter_role'] : 'all';
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'ID_Akun';
$sort_order = isset($_GET['sort_order']) ? $_GET['sort_order'] : 'ASC';

// Validasi sort_by untuk keamanan
$allowed_sort = ['ID_Akun', 'Username', 'Email', 'Role', 'Status'];
if (!in_array($sort_by, $allowed_sort)) {
    $sort_by = 'ID_Akun';
}
$sort_order = ($sort_order === 'DESC') ? 'DESC' : 'ASC';

// --- PAGING CONFIGURATION ---
$limit = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// --- 3. LOGIKA PROSES CRUD ---

// CREATE KARYAWAN
if (isset($_POST['create_karyawan'])) {
    $email = $_POST['new_email'];
    $username = $_POST['new_username'];
    $pass  = $_POST['new_password'];
    $created_by = $_SESSION['nama'] ?? 'SYSTEM';

    // Check email exists
    $checkEmail = safe_sqlsrv_query($conn, "SELECT Email FROM Akun WHERE Email = ?", array($email), false);
    if ($checkEmail && safe_sqlsrv_has_rows($checkEmail)) {
        header("Location: akun.php?role=karyawan&status=error&msg=Email sudah terdaftar!");
        exit();
    }
    // Check username exists
    $checkUser = safe_sqlsrv_query($conn, "SELECT Username FROM Akun WHERE Username = ?", array($username), false);
    if ($checkUser && safe_sqlsrv_has_rows($checkUser)) {
        header("Location: akun.php?role=karyawan&status=error&msg=Username sudah terdaftar!");
        exit();
    }

    $sql_id = "SELECT TOP 1 ID_Akun FROM Akun ORDER BY ID_Akun DESC";
    $query_id = safe_sqlsrv_query($conn, $sql_id);
    $row_id = safe_sqlsrv_fetch_array($query_id, SQLSRV_FETCH_ASSOC);
    $new_id = $row_id ? "AKN" . str_pad((int)substr($row_id['ID_Akun'], 3) + 1, 3, "0", STR_PAD_LEFT) : "AKN001";

    $sql_cr = "INSERT INTO Akun (ID_Akun, Username, Email, Kata_Sandi, Role, Status, 
                Is_Deleted, Created_By, Created_Date) 
                VALUES (?, ?, ?, ?, 2, 1, 0, ?, GETDATE())";
    $stmt = safe_sqlsrv_query($conn, $sql_cr, array($new_id, $username, $email, $pass, $created_by), false);

    if ($stmt) {
        header("Location: akun.php?role=karyawan&status=success&msg=Akun $new_id berhasil dibuat!");
    } else {
        header("Location: akun.php?role=karyawan&status=error&msg=Gagal Simpan Akun!");
    }
    exit();
}

// UPDATE AKUN — HANYA UNTUK KARYAWAN (Role 2)
if (isset($_POST['update_akun'])) {
    $id = $_POST['id_akun'];
    $modified_by = $_SESSION['nama'] ?? 'SYSTEM';

    // Validasi: hanya karyawan yang boleh di-update via master akun
    $checkRole = safe_sqlsrv_query($conn, "SELECT Role FROM Akun WHERE ID_Akun = ?", array($id), false);
    $roleData = safe_sqlsrv_fetch_array($checkRole, SQLSRV_FETCH_ASSOC);

    if ($roleData && $roleData['Role'] != 2) {
        header("Location: akun.php?role=$current_filter&status=error&msg=Akun ini hanya dapat diubah via halaman Profil!");
        exit();
    }

    $username = $_POST['username'];
    $email = $_POST['email'];
    $pass = $_POST['password'];
    $role = $_POST['role'];
    $sql_up = "UPDATE Akun SET Username=?, Email=?, Kata_Sandi=?, Role=?, 
                Modified_By=?, Modified_Date=GETDATE() WHERE ID_Akun=?";
    safe_sqlsrv_query($conn, $sql_up, array($username, $email, $pass, $role, $modified_by, $id), false);
    header("Location: akun.php?role=$current_filter&status=success&msg=Data akun diperbarui!");
    exit();
}

// TOGGLE STATUS
if (isset($_GET['toggle_id'])) {
    $status_baru = ($_GET['s'] == 1) ? 0 : 1;
    $modified_by = $_SESSION['nama'] ?? 'SYSTEM';
    $akun_status = ($status_baru == 1) ? 'Aktif' : 'Nonaktif';

    safe_sqlsrv_query($conn, "UPDATE Akun SET Status = ?, Status = ?, 
        Modified_By = ?, Modified_Date = GETDATE() 
        WHERE ID_Akun = ?", 
        array($status_baru, $akun_status, $modified_by, $_GET['toggle_id']), false);
    header("Location: akun.php?role=$current_filter&status=success&msg=Status akun berhasil diubah!");
    exit();
}

// HARD DELETE (Soft delete dengan flag)
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    $deleted_by = $_SESSION['nama'] ?? 'SYSTEM';

    $stmt = safe_sqlsrv_query($conn, "UPDATE Akun SET Is_Deleted = 1, Status = 'Dihapus', 
        Deleted_By = ?, Deleted_Date = GETDATE() WHERE ID_Akun = ?", 
        array($deleted_by, $delete_id), false);

    if ($stmt) {
        header("Location: akun.php?role=$current_filter&status=success&msg=Akun $delete_id telah dihapus (soft delete)!");
    } else {
        header("Location: akun.php?role=$current_filter&status=error&msg=Gagal menghapus akun!");
    }
    exit();
}

$edit_data = null;
if (isset($_GET['edit_id'])) {
    $res_edit = safe_sqlsrv_query($conn, "SELECT * FROM Akun WHERE ID_Akun = ? AND Is_Deleted = 0", array($_GET['edit_id']), false);
    $edit_data = safe_sqlsrv_fetch_array($res_edit, SQLSRV_FETCH_ASSOC);

    if ($edit_data && $edit_data['Role'] != 2) {
        header("Location: akun.php?role=$current_filter&status=error&msg=Akun Manajer/Customer hanya dapat diubah via halaman Profil!");
        exit();
    }
}
$show_create = isset($_GET['create']) && $_GET['create'] == '1' && $current_filter === 'karyawan';

// STATISTIK (hanya yang belum dihapus)
$q_active = safe_sqlsrv_query($conn, "SELECT COUNT(*) as total FROM Akun WHERE Status = 1 AND Is_Deleted = 0", [], false);
$active_count = 0;
if ($q_active !== false) {
    $row_active = safe_sqlsrv_fetch_array($q_active, SQLSRV_FETCH_ASSOC);
    $active_count = $row_active['total'] ?? 0;
}

$q_suspended = safe_sqlsrv_query($conn, "SELECT COUNT(*) as total FROM Akun WHERE Status = 0 AND Is_Deleted = 0", [], false);
$suspended_count = 0;
if ($q_suspended !== false) {
    $row_suspended = safe_sqlsrv_fetch_array($q_suspended, SQLSRV_FETCH_ASSOC);
    $suspended_count = $row_suspended['total'] ?? 0;
}

$q_total = safe_sqlsrv_query($conn, "SELECT COUNT(*) as total FROM Akun WHERE Is_Deleted = 0", [], false);
$total_count = 0;
if ($q_total !== false) {
    $row_total = safe_sqlsrv_fetch_array($q_total, SQLSRV_FETCH_ASSOC);
    $total_count = $row_total['total'] ?? 0;
}

// --- BUILD FILTER QUERY ---
$where_conditions = ["Is_Deleted = 0"];
$params = [];

// Filter by role
if ($current_filter != 'all') {
    $role_id = $role_map[$current_filter] ?? null;
    if ($role_id) {
        $where_conditions[] = "Role = " . intval($role_id);
    }
}

// Filter by status
if ($filter_status != 'all') {
    $status_val = ($filter_status == 'aktif') ? 1 : 0;
    $where_conditions[] = "Status = " . intval($status_val);
}

// Filter by role dropdown
if ($filter_role != 'all') {
    $role_id = $role_map[$filter_role] ?? null;
    if ($role_id) {
        $where_conditions[] = "Role = " . intval($role_id);
    }
}

$where_clause = implode(" AND ", $where_conditions);

// --- COUNT QUERY ---
$count_sql = "SELECT COUNT(*) as total FROM Akun WHERE " . $where_clause;
$count_query = safe_sqlsrv_query($conn, $count_sql, $params, false);
$total_rows = 0;
if ($count_query !== false) {
    $count_row = safe_sqlsrv_fetch_array($count_query, SQLSRV_FETCH_ASSOC);
    $total_rows = $count_row['total'] ?? 0;
}

$total_pages = max(1, ceil($total_rows / $limit));
$page = min($page, max(1, $total_pages));
$offset = ($page - 1) * $limit;

// --- MAIN QUERY ---
$query_sql = "SELECT * FROM Akun WHERE " . $where_clause . " ORDER BY " . $sort_by . " " . $sort_order . " OFFSET " . intval($offset) . " ROWS FETCH NEXT " . intval($limit) . " ROWS ONLY";
$query = safe_sqlsrv_query($conn, $query_sql, [], false);

$query_error = ($query === false);
$query_error_msg = '';
if ($query_error) {
    $errors = sqlsrv_errors();
    if ($errors) {
        foreach ($errors as $error) {
            $query_error_msg .= "[" . $error['SQLSTATE'] . "] " . $error['message'] . " ";
        }
    }
}

$role_label_map = [1 => 'Manajer', 2 => 'Karyawan', 3 => 'Customer'];

// Helper untuk build URL dengan filter
function buildFilterUrl($params) {
    $base = 'akun.php';
    $query = http_build_query(array_merge($_GET, $params));
    return $base . '?' . $query;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Kelola Data Akun | HoopBall</title>
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
    --bg-dark: #1F2937; --zebra-orange: #FFF7ED;
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
.page-header { display: flex; align-items: flex-end; justify-content: space-between; margin-bottom: 24px; }
.page-title-tag { width: 36px; height: 4px; background: var(--orange); border-radius: 2px; margin-bottom: 8px; }
.page-title { font-family: 'Barlow Condensed', sans-serif; font-size: 30px; font-weight: 900; color: var(--text); text-transform: uppercase; }

/* ═══ STAT CARDS ═══ */
.stat-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 18px; margin-bottom: 28px; }
.stat-card { background: var(--card-bg); border-radius: 16px; padding: 22px 24px; border: 1px solid var(--border); position: relative; overflow: hidden; transition: all .2s ease; }
.stat-card:hover { transform: translateY(-3px); box-shadow: 0 12px 32px rgba(0,0,0,.08); }
.stat-card::before { content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; border-radius: 4px 0 0 4px; }
.sc-orange::before { background: var(--orange); }
.sc-green::before { background: var(--green); }
.sc-red::before { background: var(--red); }
.stat-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
.stat-icon-wrap { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 18px; }
.si-orange { background: var(--orange-lt); color: var(--orange); }
.si-green { background: var(--green-lt); color: var(--green); }
.si-red { background: var(--red-lt); color: var(--red); }
.stat-trend { font-size: 11px; font-weight: 800; display: flex; align-items: center; gap: 3px; padding: 4px 8px; border-radius: 20px; }
.trend-up { color: var(--green); background: var(--green-lt); }
.trend-down { color: var(--red); background: var(--red-lt); }
.stat-value { font-family: 'Barlow Condensed', sans-serif; font-size: 30px; font-weight: 900; color: var(--text); line-height: 1; margin-bottom: 6px; }
.stat-label { font-size: 12px; color: var(--muted); font-weight: 700; text-transform: uppercase; letter-spacing: .5px; }
.stat-sublabel { font-size: 11px; color: var(--muted); margin-top: 4px; opacity: .7; }

/* ═══ TOOLBAR & FILTER ═══ */
.toolbar { background: var(--card-bg); border: 1px solid var(--border); border-radius: 16px 16px 0 0; padding: 16px 24px; display: flex; align-items: center; justify-content: space-between; border-bottom: 2px solid var(--bg); flex-wrap: wrap; gap: 12px; }
.tab-group { display: flex; gap: 4px; background: var(--bg); padding: 4px; border-radius: 10px; }
.tab-item { padding: 7px 16px; border-radius: 8px; text-decoration: none; color: var(--muted); font-size: 12px; font-weight: 700; transition: 0.2s; }
.tab-item.active { background: #fff; color: var(--text); box-shadow: 0 1px 4px rgba(0,0,0,0.1); }
.search-wrap { position: relative; }
.search-wrap i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: 12px; }
.search-input { padding: 9px 12px 9px 34px; border: 1.5px solid var(--border); border-radius: 10px; font-size: 13px; font-family: 'Barlow', sans-serif; color: var(--text); width: 220px; outline: none; transition: 0.2s; }
.search-input:focus { border-color: var(--orange); }
.btn-add { background: var(--text); color: #fff; padding: 10px 20px; border-radius: 10px; font-size: 12px; font-weight: 800; text-decoration: none; text-transform: uppercase; transition: 0.2s; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; }
.btn-add:hover { background: var(--orange); transform: translateY(-1px); }

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

/* ═══ TABLE ═══ */
.table-wrap { background: var(--card-bg); border: 1px solid var(--border); border-top: none; border-radius: 0 0 16px 16px; overflow: hidden; margin-bottom: 0; }
table { width: 100%; border-collapse: collapse; }
th { padding: 13px 20px; font-size: 10px; font-weight: 800; color: var(--muted); text-transform: uppercase; border-bottom: 1px solid var(--border); text-align: left; letter-spacing: .6px; }
td { padding: 15px 20px; font-size: 13px; border-bottom: 1px solid #F9FAFB; vertical-align: middle; }

/* ═══ ZEBRA STRIPING — ORANGE & PUTIH ═══ */
tbody tr:nth-child(odd) { background-color: var(--zebra-orange); }
tbody tr:nth-child(even) { background-color: #FFFFFF; }
tbody tr:hover td { background-color: #FED7AA !important; }

.role-badge { padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .3px; display: inline-block; }
.badge-1 { background: #FEF3C7; color: #92400E; }
.badge-2 { background: #DBEAFE; color: #1E40AF; }
.badge-3 { background: #F3F4F6; color: #4B5563; }

.status-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
.status-active { background: var(--green); }
.status-inactive { background: var(--red); }
.status-text { font-size: 11px; font-weight: 800; }
.status-text-active { color: var(--green); }
.status-text-inactive { color: var(--red); }

.id-akun { font-family: 'Barlow Condensed', sans-serif; font-weight: 800; color: var(--orange); font-size: 15px; }
.email-text { font-weight: 600; color: var(--text); }
.username-text { font-weight: 700; color: var(--text-md); font-size: 13px; }

.toggle-switch { position: relative; display: inline-block; width: 44px; height: 24px; cursor: pointer; }
.toggle-switch input { opacity: 0; width: 0; height: 0; }
.toggle-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: var(--red); transition: .3s; border-radius: 24px; }
.toggle-slider::before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .3s; border-radius: 50%; box-shadow: 0 2px 4px rgba(0,0,0,.2); }
.toggle-switch input:checked + .toggle-slider { background-color: var(--green); }
.toggle-switch input:checked + .toggle-slider::before { transform: translateX(20px); }
.toggle-switch:hover .toggle-slider { opacity: .9; }

/* ═══ ELEGANT ACTION BUTTONS ═══ */
.action-group { display: flex; align-items: center; gap: 6px; justify-content: flex-end; }
.btn-action {
    display: inline-flex; align-items: center; justify-content: center; gap: 6px;
    padding: 8px 14px; border-radius: 10px; font-size: 12px; font-weight: 700;
    font-family: 'Barlow', sans-serif; text-decoration: none; cursor: pointer;
    transition: all .25s cubic-bezier(.4,0,.2,1); border: 1.5px solid transparent; letter-spacing: .3px;
}
.btn-edit {
    background: linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%); color: #1E40AF; border-color: #BFDBFE;
}
.btn-edit i { font-size: 13px; }
.btn-edit:hover {
    background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%); color: #fff; border-color: #3B82F6;
    transform: translateY(-2px); box-shadow: 0 6px 20px rgba(59,130,246,.35);
}
.btn-edit:active { transform: translateY(0); }
.btn-delete {
    background: linear-gradient(135deg, #FEF2F2 0%, #FEE2E2 100%); color: #DC2626; border-color: #FECACA;
}
.btn-delete i { font-size: 13px; }
.btn-delete:hover {
    background: linear-gradient(135deg, #EF4444 0%, #DC2626 100%); color: #fff; border-color: #EF4444;
    transform: translateY(-2px); box-shadow: 0 6px 20px rgba(239,68,68,.35);
}
.btn-delete:active { transform: translateY(0); }

/* ═══ DETAIL BUTTON ═══ */
.btn-detail {
    background: linear-gradient(135deg, #FFF7ED 0%, #FED7AA 100%); color: #92400E; border-color: #FDBA74;
}
.btn-detail i { font-size: 13px; }
.btn-detail:hover {
    background: linear-gradient(135deg, #FF4500 0%, #E03E00 100%); color: #fff; border-color: #FF4500;
    transform: translateY(-2px); box-shadow: 0 6px 20px rgba(255,69,0,.35);
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

/* ═══ MODAL CREATE/EDIT ═══ */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(6px); display: flex; align-items: center; justify-content: center; z-index: 2000; }
.modal-overlay.hidden { display: none; }
.modal-box { background: #fff; border-radius: 20px; width: 480px; overflow: hidden; box-shadow: 0 25px 60px rgba(0,0,0,0.2); }
.modal-header { padding: 28px 32px 20px; border-bottom: 1px solid var(--border); }
.modal-subtitle { font-size: 10px; font-weight: 800; color: var(--orange); text-transform: uppercase; margin-bottom: 6px; letter-spacing: .8px; }
.modal-title { font-family: 'Barlow Condensed', sans-serif; font-size: 22px; font-weight: 900; color: var(--text); }
.modal-body { padding: 24px 32px 32px; }
.modal-label { font-size: 11px; font-weight: 800; color: var(--muted); text-transform: uppercase; display: block; margin-bottom: 6px; letter-spacing: .5px; }
.modal-label .required { color: var(--red); margin-left: 2px; font-size: 14px; font-weight: 900; }
.modal-input { width: 100%; padding: 11px 14px; border: 1.5px solid var(--border); border-radius: 10px; font-size: 13px; font-family: 'Barlow', sans-serif; margin-bottom: 4px; outline: none; transition: .2s; color: var(--text); }
.modal-input:focus { border-color: var(--orange); box-shadow: 0 0 0 3px var(--orange-lt); }
.modal-input:invalid:not(:placeholder-shown) { border-color: var(--red); }
.modal-input:valid:not(:placeholder-shown) { border-color: var(--green); }
.modal-select { width: 100%; padding: 11px 14px; border: 1.5px solid var(--border); border-radius: 10px; font-size: 13px; font-family: 'Barlow', sans-serif; margin-bottom: 4px; outline: none; background: #fff; color: var(--text); cursor: pointer; appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236B7280' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 14px center; padding-right: 40px; }
.modal-select:focus { border-color: var(--orange); }
.btn-save { background: var(--text); color: #fff; padding: 12px 20px; border-radius: 10px; font-size: 13px; font-weight: 800; text-transform: uppercase; border: none; cursor: pointer; width: 100%; transition: .2s; letter-spacing: .5px; display: flex; align-items: center; justify-content: center; gap: 8px; }
.btn-save:hover { background: var(--orange); }
.btn-save:disabled { background: #D1D5DB; cursor: not-allowed; }
.btn-cancel { display: block; text-align: center; margin-top: 12px; color: var(--muted); font-size: 12px; text-decoration: none; font-weight: 700; transition: .2s; }
.btn-cancel:hover { color: var(--orange); }

/* ═══ DETAIL MODAL ═══ */
.detail-modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.55); backdrop-filter: blur(6px); display: flex; align-items: center; justify-content: center; z-index: 2000; opacity: 0; visibility: hidden; transition: all .3s ease; }
.detail-modal-overlay.active { opacity: 1; visibility: visible; }
.detail-modal-box { background: #fff; border-radius: 20px; width: 480px; max-width: 95vw; max-height: 90vh; overflow-y: auto; box-shadow: 0 25px 60px rgba(0,0,0,0.2); transform: translateY(30px) scale(0.95); transition: all .3s ease; }
.detail-modal-overlay.active .detail-modal-box { transform: translateY(0) scale(1); }
.detail-modal-header { padding: 28px 32px 20px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
.detail-modal-header-left { display: flex; align-items: center; gap: 14px; }
.detail-modal-avatar { width: 56px; height: 56px; background: linear-gradient(135deg, var(--orange) 0%, var(--orange-dk) 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 24px; flex-shrink: 0; }
.detail-modal-info { display: flex; flex-direction: column; }
.detail-modal-name { font-family: 'Barlow Condensed', sans-serif; font-size: 22px; font-weight: 900; color: var(--text); }
.detail-modal-id { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 700; color: var(--orange); background: var(--orange-lt); padding: 3px 10px; border-radius: 6px; margin-top: 4px; width: fit-content; }
.detail-modal-close { width: 36px; height: 36px; border-radius: 10px; background: var(--bg); border: 1.5px solid var(--border); color: var(--muted); display: flex; align-items: center; justify-content: center; cursor: pointer; transition: .2s; font-size: 14px; }
.detail-modal-close:hover { background: var(--red-lt); color: var(--red); border-color: var(--red); }
.detail-modal-body { padding: 0; }
.detail-modal-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 0; }
.detail-modal-item { padding: 16px 24px; border-bottom: 1px solid var(--border-lt); border-right: 1px solid var(--border-lt); }
.detail-modal-item:nth-child(2n) { border-right: none; }
.detail-modal-item:nth-last-child(-n+2) { border-bottom: none; }
.detail-modal-item:nth-last-child(1):nth-child(odd) { border-bottom: none; grid-column: 1 / -1; }
.detail-modal-label { display: flex; align-items: center; gap: 6px; font-size: 10px; font-weight: 800; text-transform: uppercase; color: var(--muted); letter-spacing: .5px; margin-bottom: 6px; }
.detail-modal-label i { font-size: 11px; width: 14px; text-align: center; }
.detail-modal-value { font-size: 14px; font-weight: 700; color: var(--text); line-height: 1.4; }
.detail-modal-value-muted { color: var(--muted); font-weight: 600; }
.detail-modal-status { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 8px; font-size: 12px; font-weight: 800; text-transform: uppercase; }
.detail-modal-status-active { background: var(--green-lt); color: var(--green); }
.detail-modal-status-inactive { background: var(--red-lt); color: var(--red); }
.detail-modal-footer { padding: 16px 24px; border-top: 1px solid var(--border-lt); display: flex; gap: 10px; justify-content: flex-end; }
.btn-modal-close { display: inline-flex; align-items: center; gap: 6px; padding: 10px 18px; border-radius: 10px; font-size: 12px; font-weight: 700; font-family: 'Barlow', sans-serif; cursor: pointer; transition: .2s; border: 1.5px solid var(--border); color: var(--text-md); background: var(--bg); }
.btn-modal-close:hover { background: var(--text); color: #fff; border-color: var(--text); }

/* ═══ VALIDASI ERROR MESSAGE ═══ */
.val-msg { font-size: 11px; color: var(--red); font-weight: 600; margin-bottom: 12px; display: none; min-height: 16px; }
.val-msg.show { display: block; }
.val-msg i { margin-right: 4px; }

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

/* ═══ RESPONSIVE ═══ */
@media(max-width: 1100px) { .stat-grid { grid-template-columns: repeat(2, 1fr); } }
@media(max-width: 768px) {
    .sidebar { width: 0; overflow: hidden; padding: 0; }
    .main { margin-left: 0; }
    .stat-grid { grid-template-columns: 1fr; }
    .toolbar { flex-direction: column; gap: 12px; align-items: stretch; }
    .tab-group { overflow-x: auto; }
    .content { padding: 20px; }
    .topbar { padding: 0 20px; }
    .pagination-wrap { flex-direction: column; gap: 12px; }
    .detail-modal-grid { grid-template-columns: 1fr; }
    .detail-modal-item { border-right: none; }
    .detail-modal-item:nth-last-child(1):nth-child(odd) { grid-column: auto; }
    .filter-dropdown { width: 100%; right: 0; }
}
</style>
</head>
<body>

<!-- ═══ MODAL (Create / Edit Karyawan Only) ═══ -->
<div class="modal-overlay <?= ($edit_data || $show_create) ? '' : 'hidden' ?>" id="modalAkun">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-subtitle">Master Akun</div>
            <h2 class="modal-title"><?= $edit_data ? 'Edit Data Karyawan' : 'Tambah Karyawan Baru' ?></h2>
        </div>
        <div class="modal-body">
            <form method="POST" id="formAkun" onsubmit="return validateForm(this)">
                <?php if($edit_data): ?>
                    <input type="hidden" name="id_akun" value="<?= $edit_data['ID_Akun'] ?>">

                    <label class="modal-label">Username <span class="required">*</span></label>
                    <input type="text" name="username" id="username" class="modal-input" value="<?= htmlspecialchars($edit_data['Username']) ?>" required minlength="3" maxlength="50" pattern="[a-zA-Z0-9_]+" placeholder="Masukkan username (huruf, angka, underscore)">
                    <div class="val-msg" id="val-username"><i class="fa-solid fa-circle-exclamation"></i> Username minimal 3 karakter, hanya huruf, angka, dan underscore</div>

                    <label class="modal-label">Email <span class="required">*</span></label>
                    <input type="email" name="email" id="email" class="modal-input" value="<?= htmlspecialchars($edit_data['Email']) ?>" required placeholder="email@hoopball.com">
                    <div class="val-msg" id="val-email"><i class="fa-solid fa-circle-exclamation"></i> Format email tidak valid</div>

                    <label class="modal-label">Password <span class="required">*</span></label>
                    <input type="text" name="password" id="password" class="modal-input" value="<?= htmlspecialchars($edit_data['Kata_Sandi']) ?>" required minlength="6" placeholder="Minimal 6 karakter">
                    <div class="val-msg" id="val-password"><i class="fa-solid fa-circle-exclamation"></i> Password minimal 6 karakter</div>

                    <label class="modal-label">Role <span class="required">*</span></label>
                    <select name="role" class="modal-select" required>
                        <option value="2" <?= $edit_data['Role'] == 2 ? 'selected' : '' ?>>Karyawan</option>
                    </select>

                    <button type="submit" name="update_akun" class="btn-save"><i class="fa-solid fa-floppy-disk"></i> Simpan Perubahan</button>
                <?php else: ?>
                    <label class="modal-label">Username <span class="required">*</span></label>
                    <input type="text" name="new_username" id="new_username" class="modal-input" required minlength="3" maxlength="50" pattern="[a-zA-Z0-9_]+" placeholder="Masukkan username karyawan (huruf, angka, underscore)">
                    <div class="val-msg" id="val-new_username"><i class="fa-solid fa-circle-exclamation"></i> Username minimal 3 karakter, hanya huruf, angka, dan underscore</div>

                    <label class="modal-label">Email Karyawan <span class="required">*</span></label>
                    <input type="email" name="new_email" id="new_email" class="modal-input" required placeholder="email@hoopball.com">
                    <div class="val-msg" id="val-new_email"><i class="fa-solid fa-circle-exclamation"></i> Format email tidak valid</div>

                    <label class="modal-label">Password <span class="required">*</span></label>
                    <input type="password" name="new_password" id="new_password" class="modal-input" required minlength="6" placeholder="Minimal 6 karakter">
                    <div class="val-msg" id="val-new_password"><i class="fa-solid fa-circle-exclamation"></i> Password minimal 6 karakter</div>

                    <button type="submit" name="create_karyawan" class="btn-save"><i class="fa-solid fa-plus"></i> Buat Akun Karyawan</button>
                <?php endif; ?>
                <a href="akun.php?role=<?= $current_filter ?>" class="btn-cancel">Batal</a>
            </form>
        </div>
    </div>
</div>

<!-- ═══ DETAIL MODAL AKUN ═══ -->
<div class="detail-modal-overlay" id="detailModalAkun" onclick="closeDetailModal(event)">
    <div class="detail-modal-box" onclick="event.stopPropagation()">
        <div class="detail-modal-header">
            <div class="detail-modal-header-left">
                <div class="detail-modal-avatar"><i class="fa-solid fa-user-shield"></i></div>
                <div class="detail-modal-info">
                    <div class="detail-modal-name" id="detailNama">-</div>
                    <div class="detail-modal-id"><i class="fa-solid fa-fingerprint"></i> <span id="detailId">-</span></div>
                </div>
            </div>
            <button class="detail-modal-close" onclick="closeDetailModal()" title="Tutup"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="detail-modal-body">
            <div class="detail-modal-grid">
                <div class="detail-modal-item">
                    <div class="detail-modal-label"><i class="fa-solid fa-user" style="color:var(--orange);"></i> Username</div>
                    <div class="detail-modal-value" id="detailUsername">-</div>
                </div>
                <div class="detail-modal-item">
                    <div class="detail-modal-label"><i class="fa-solid fa-envelope" style="color:var(--blue);"></i> Email</div>
                    <div class="detail-modal-value" id="detailEmail">-</div>
                </div>
                <div class="detail-modal-item" id="passwordField">
                    <div class="detail-modal-label"><i class="fa-solid fa-key" style="color:var(--purple);"></i> Password</div>
                    <div class="detail-modal-value" id="detailPassword">-</div>
                </div>
                <div class="detail-modal-item">
                    <div class="detail-modal-label"><i class="fa-solid fa-shield-halved" style="color:var(--yellow);"></i> Hak Akses</div>
                    <div class="detail-modal-value" id="detailRole">-</div>
                </div>
                <div class="detail-modal-item">
                    <div class="detail-modal-label"><i class="fa-solid fa-circle-check" style="color:var(--green);"></i> Status</div>
                    <div class="detail-modal-value" id="detailStatus">-</div>
                </div>
            </div>
        </div>
        <div class="detail-modal-footer">
            <button class="btn-modal-close" onclick="closeDetailModal()"><i class="fa-solid fa-xmark"></i> Tutup</button>
        </div>
    </div>
</div>

<!-- ═══ SIDEBAR ═══ -->
<<aside class="sidebar">
    <a href="../view_pemilik.php" class="sb-brand">
        <div class="sb-icon"><i class="fa-solid fa-basketball"></i></div>
        <div>
            <div class="sb-brand-name">HOOP BALL</div>
            <div class="sb-brand-sub">Management System</div>
        </div>
    </a>
    <div class="sb-section-label">Manajemen</div>
    <nav>
        <a href="../view_pemilik.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-house"></i></div> Dashboard</a>
        <a href="akun.php" class="sb-link active"><div class="sb-icon-wrap"><i class="fa-solid fa-user-shield"></i></div> Kelola Akun</a>
        <a href="karyawan.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-user-tie"></i></div> Kelola Karyawan</a>
        <a href="alat.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-toolbox"></i></div> Kelola Alat</a>
        <a href="../laporan/omzet.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-chart-line"></i></div> Laporan & Omzet</a>
    </nav>
    <div class="sb-section-label">Akun</div>
    <a href="../profile.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-id-badge"></i></div> Profil Saya</a>
    <div class="sb-bottom">
        <div class="sb-user">
            <div class="sb-avatar">
            <?php if ($profile_photo): ?>
                <img src="<?= $profile_photo ?>" alt="Profile" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
            <?php else: ?>
                <i class="fa-solid fa-user"></i>
            <?php endif; ?>
        </div>
            <div>
                <div class="sb-user-name"><?= strtoupper(htmlspecialchars($nama_user)) ?></div>
                <div class="sb-user-role">PEMILIK</div>
            </div>
            <a href="../logout.php" class="sb-logout" title="Keluar"><i class="fa-solid fa-right-from-bracket"></i></a>
        </div>
    </div>
</aside>

<!-- ═══ MAIN & TOPBAR ═══ -->
<<main class="main">
    <header class="topbar">
        <div class="topbar-left">
            <div class="topbar-title">Kelola Data Akun</div>
            <div class="topbar-breadcrumb">Dashboard / Manajemen Akun</div>
        </div>
        <div class="topbar-right">
            <a href="#" class="topbar-btn"><i class="fa-solid fa-magnifying-glass"></i></a>
            <a href="#" class="topbar-btn"><i class="fa-solid fa-bell"></i><span class="notif-dot"></span></a>
            <div class="dropdown-wrap">
                <div class="topbar-user">
                    <div class="t-avatar">
                    <?php if ($profile_photo): ?>
                        <img src="<?= $profile_photo ?>" alt="Profile" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
                    <?php else: ?>
                        <i class="fa-solid fa-user"></i>
                    <?php endif; ?>
                </div>
                    <div>
                        <div class="t-name"><?= strtoupper(htmlspecialchars($nama_user)) ?></div>
                        <div class="t-role">PEMILIK</div>
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
        <div class="page-header">
            <div>
                <div class="page-title-tag"></div>
                <div class="page-title">Master Akun</div>
            </div>
        </div>

        <div class="stat-grid">
            <div class="stat-card sc-orange">
                <div class="stat-header">
                    <div class="stat-icon-wrap si-orange"><i class="fa-solid fa-users"></i></div>
                    <div class="stat-trend trend-up"><i class="fa-solid fa-arrow-up"></i> Total</div>
                </div>
                <div class="stat-value"><?= $total_count ?></div>
                <div class="stat-label">Total Akun</div>
                <div class="stat-sublabel">Semua role</div>
            </div>
            <div class="stat-card sc-green">
                <div class="stat-header">
                    <div class="stat-icon-wrap si-green"><i class="fa-solid fa-circle-check"></i></div>
                    <div class="stat-trend trend-up"><i class="fa-solid fa-arrow-up"></i> Aktif</div>
                </div>
                <div class="stat-value"><?= $active_count ?></div>
                <div class="stat-label">Akun Aktif</div>
                <div class="stat-sublabel">Dapat mengakses sistem</div>
            </div>
            <div class="stat-card sc-red">
                <div class="stat-header">
                    <div class="stat-icon-wrap si-red"><i class="fa-solid fa-ban"></i></div>
                    <div class="stat-trend trend-down"><i class="fa-solid fa-arrow-down"></i> Nonaktif</div>
                </div>
                <div class="stat-value"><?= $suspended_count ?></div>
                <div class="stat-label">Suspended</div>
                <div class="stat-sublabel">Tidak dapat login</div>
            </div>
        </div>

        <!-- ═══ TOOLBAR WITH FILTER ═══ -->
        <div class="toolbar">
            <div class="tab-group">
                <a href="akun.php?role=all" class="tab-item <?= $current_filter == 'all' ? 'active' : '' ?>">Semua</a>
                <a href="akun.php?role=manajer" class="tab-item <?= $current_filter == 'manajer' ? 'active' : '' ?>">Manajer</a>
                <a href="akun.php?role=karyawan" class="tab-item <?= $current_filter == 'karyawan' ? 'active' : '' ?>">Karyawan</a>
                <a href="akun.php?role=customer" class="tab-item <?= $current_filter == 'customer' ? 'active' : '' ?>">Customer</a>
            </div>
            <div style="display:flex; align-items:center; gap:12px;">
                <div class="search-wrap">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" class="search-input" id="src" placeholder="Cari username/email..." onkeyup="searchTable()">
                </div>
                
                <!-- FILTER DROPDOWN -->
                <div class="filter-wrap">
                    <button class="filter-btn" onclick="toggleFilter()" id="filterBtn">
                        <i class="fa-solid fa-filter"></i> Filter <i class="fa-solid fa-chevron-down"></i>
                    </button>
                    <div class="filter-dropdown" id="filterDropdown">
                        <div class="filter-header">
                            <div class="filter-title">Filter Data</div>
                        </div>
                        <div class="filter-body">
                            <div class="filter-group">
                                <label class="filter-label">Urut Berdasarkan</label>
                                <select class="filter-select" id="filterSortBy">
                                    <option value="ID_Akun" <?= $sort_by == 'ID_Akun' ? 'selected' : '' ?>>ID Akun <?= $sort_by == 'ID_Akun' && $sort_order == 'ASC' ? '↑' : '' ?></option>
                                    <option value="Username" <?= $sort_by == 'Username' ? 'selected' : '' ?>>Username <?= $sort_by == 'Username' && $sort_order == 'ASC' ? '↑' : '' ?></option>
                                    <option value="Email" <?= $sort_by == 'Email' ? 'selected' : '' ?>>Email <?= $sort_by == 'Email' && $sort_order == 'ASC' ? '↑' : '' ?></option>
                                    <option value="Role" <?= $sort_by == 'Role' ? 'selected' : '' ?>>Role <?= $sort_by == 'Role' && $sort_order == 'ASC' ? '↑' : '' ?></option>
                                    <option value="Status" <?= $sort_by == 'Status' ? 'selected' : '' ?>>Status <?= $sort_by == 'Status' && $sort_order == 'ASC' ? '↑' : '' ?></option>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label class="filter-label">Status</label>
                                <select class="filter-select" id="filterStatus">
                                    <option value="all" <?= $filter_status == 'all' ? 'selected' : '' ?>>Semua Status</option>
                                    <option value="aktif" <?= $filter_status == 'aktif' ? 'selected' : '' ?>>Aktif</option>
                                    <option value="nonaktif" <?= $filter_status == 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label class="filter-label">Role</label>
                                <select class="filter-select" id="filterRole">
                                    <option value="all" <?= $filter_role == 'all' ? 'selected' : '' ?>>Semua Role</option>
                                    <option value="manajer" <?= $filter_role == 'manajer' ? 'selected' : '' ?>>Manajer</option>
                                    <option value="karyawan" <?= $filter_role == 'karyawan' ? 'selected' : '' ?>>Karyawan</option>
                                    <option value="customer" <?= $filter_role == 'customer' ? 'selected' : '' ?>>Customer</option>
                                </select>
                            </div>
                        </div>
                        <div class="filter-footer">
                            <button class="btn-filter-apply" onclick="applyFilter()">
                                <i class="fa-solid fa-check"></i> Terapkan
                            </button>
                            <a href="akun.php?role=<?= $current_filter ?>" class="btn-filter-reset">
                                <i class="fa-solid fa-rotate-left"></i> Reset
                            </a>
                        </div>
                    </div>
                </div>
                
                <?php if($current_filter === 'karyawan'): ?>
                    <a href="akun.php?role=karyawan&create=1" class="btn-add"><i class="fa-solid fa-plus"></i> Tambah</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($query_error): ?><div style="padding:20px;background:#fee;border:1px solid #fcc;border-radius:8px;margin:20px 0;"><p style="color:#c00;font-weight:bold;margin:0;"><i class="fa-solid fa-circle-exclamation"></i> Gagal mengambil data dari database. Silakan refresh halaman atau hubungi administrator.</p><p style="color:#666;font-size:11px;margin:5px 0 0;">Error: <?php echo htmlspecialchars($query_error_msg); ?></p></div><?php else: ?>
            <div class="table-wrap">
            <table id="tbl">
                <thead>
                    <tr>
                        <th style="width:50px;">No</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Hak Akses</th>
                        <th>Status</th>
                        <th style="text-align:right;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $row_num = 0; $no = $offset + 1; if (!$query_error && $query): while($row = safe_sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)): $row_num++; $is_active = $row['Status'] == 1; $is_customer = $row['Role'] == 3; $is_manajer = $row['Role'] == 1; $is_karyawan = $row['Role'] == 2; ?>
                    <tr class="row-<?= $row_num % 2 == 1 ? 'odd' : 'even' ?>">
                        <td style="font-weight:800; color:var(--muted);"><?= $no++ ?></td>
                        <td class="username-text"><?= htmlspecialchars($row['Username'] ?? '-') ?></td>
                        <td class="email-text"><?= htmlspecialchars($row['Email']) ?></td>
                        <td><span class="role-badge badge-<?= $row['Role'] ?>"><?= $role_label_map[$row['Role']] ?></span></td>
                        <td>
                            <div style="display:flex; align-items:center; gap:8px;">
                                <span class="status-dot <?= $is_active ? 'status-active' : 'status-inactive' ?>"></span>
                                <span class="status-text <?= $is_active ? 'status-text-active' : 'status-text-inactive' ?>"><?= $is_active ? 'Aktif' : 'Nonaktif' ?></span>
                            </div>
                        </td>
                        <td style="text-align:right;">
                            <div class="action-group">
                                <button type="button" class="btn-action btn-detail" onclick="openDetailAkun(
                                    '<?= htmlspecialchars($row['ID_Akun']) ?>',
                                    '<?= htmlspecialchars($row['Username']) ?>',
                                    '<?= htmlspecialchars($row['Email']) ?>',
                                    '<?= htmlspecialchars($row['Kata_Sandi']) ?>',
                                    '<?= $row['Role'] ?>',
                                    '<?= $role_label_map[$row['Role']] ?>',
                                    '<?= $is_active ? 'Aktif' : 'Nonaktif' ?>',
                                    '<?= $is_active ?>'
                                )" title="Lihat Detail"><i class="fa-solid fa-eye"></i></button>
                                <?php if ($is_karyawan): ?>
                                    <a href="?role=<?= $current_filter ?>&page=<?= $page ?>&edit_id=<?= $row['ID_Akun'] ?>" class="btn-action btn-edit" title="Edit Akun Karyawan"><i class="fa-solid fa-pen-to-square"></i></a>
                                <?php elseif ($is_manajer): ?>
                                    <span style="font-size: 11px; color: var(--muted); font-weight: 600; padding: 8px 12px; background: var(--border-lt); border-radius: 8px;">
                                        <i class="fa-solid fa-lock" style="margin-right: 4px;"></i> Edit via Profil
                                    </span>
                                <?php endif; ?>

                                <?php if (!$is_customer): ?>
                                    <label class="toggle-switch" title="<?= $is_active ? 'Nonaktifkan' : 'Aktifkan' ?> akun">
                                        <input type="checkbox" <?= $is_active ? 'checked' : '' ?> onchange="confirmToggle('<?= $row['ID_Akun'] ?>', <?= $row['Status'] ?>)">
                                        <span class="toggle-slider"></span>
                                    </label>
                                <?php endif; ?>
                                <button type="button" class="btn-action btn-delete" onclick="confirmDelete('<?= $row['ID_Akun'] ?>', '<?= htmlspecialchars($row['Username']) ?>')" title="Hapus Permanen"><i class="fa-solid fa-trash-can"></i></button>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; endif; ?>
                </tbody>
            </table>
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
function searchTable() {
    var input = document.getElementById('src').value.toUpperCase();
    var rows = document.getElementById('tbl').getElementsByTagName('tr');
    for (var i = 1; i < rows.length; i++) {
        var tdUser = rows[i].getElementsByTagName('td')[1];
        var tdEmail = rows[i].getElementsByTagName('td')[2];
        var match = false;
        if (tdUser && tdUser.textContent.toUpperCase().indexOf(input) > -1) match = true;
        if (tdEmail && tdEmail.textContent.toUpperCase().indexOf(input) > -1) match = true;
        rows[i].style.display = match ? '' : 'none';
    }
}

function confirmToggle(id, current) {
    const act = (current == 1) ? 'nonaktifkan' : 'aktifkan';
    const icon = (current == 1) ? 'warning' : 'question';
    Swal.fire({
        title: 'Konfirmasi', text: 'Apakah Anda yakin ingin ' + act + ' akun ini?', icon: icon,
        showCancelButton: true, confirmButtonText: 'Ya, ' + act + '!', confirmButtonColor: '#FF4500',
        cancelButtonText: 'Batal', cancelButtonColor: '#6B7280', reverseButtons: true
    }).then((result) => {
        if(result.isConfirmed) { window.location.href = `?role=<?= $current_filter ?>&page=<?= $page ?>&toggle_id=${id}&s=${current}`; }
        else { var checkbox = document.querySelector('input[onchange*="' + id + '"'); if (checkbox) checkbox.checked = !checkbox.checked; }
    });
}

function confirmDelete(id, username) {
    Swal.fire({
        title: 'Hapus Akun Permanen?',
        html: `Akun <strong style="color:#FF4500;">${username}</strong> (${id}) akan dihapus <strong style="color:#DC2626;">secara permanen</strong>!<br><br>Data yang terkait (Karyawan/Customer) juga akan terhapus.`,
        icon: 'warning', showCancelButton: true, confirmButtonText: 'Ya, Hapus Permanen!', confirmButtonColor: '#DC2626',
        cancelButtonText: 'Batal', cancelButtonColor: '#6B7280', reverseButtons: true
    }).then((result) => { if(result.isConfirmed) { window.location.href = `?role=<?= $current_filter ?>&page=<?= $page ?>&delete_id=${id}`; } });
}

function validateForm(form) {
    let valid = true;
    const inputs = form.querySelectorAll('.modal-input[required]');
    inputs.forEach(input => {
        const valMsg = document.getElementById('val-' + input.id);
        if (!input.checkValidity()) {
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

const urlParams = new URLSearchParams(window.location.search);
if(urlParams.get('status')){
    Swal.fire({ icon: urlParams.get('status'), title: urlParams.get('msg'), showConfirmButton: false, timer: 2500, timerProgressBar: true, toast: true, position: 'top-end' });
    window.history.replaceState({}, '', window.location.pathname + "?role=<?= $current_filter ?>");
}

// ═══ FILTER FUNCTIONS ═══
function toggleFilter() {
    const dropdown = document.getElementById('filterDropdown');
    dropdown.classList.toggle('active');
}

function applyFilter() {
    const sortBy = document.getElementById('filterSortBy').value;
    const status = document.getElementById('filterStatus').value;
    const role = document.getElementById('filterRole').value;
    
    const params = new URLSearchParams(window.location.search);
    params.set('sort_by', sortBy);
    params.set('filter_status', status);
    params.set('filter_role', role);
    params.set('page', '1');
    
    window.location.href = 'akun.php?' + params.toString();
}

// Close filter dropdown when clicking outside
document.addEventListener('click', function(e) {
    const filterWrap = document.querySelector('.filter-wrap');
    const filterDropdown = document.getElementById('filterDropdown');
    if (!filterWrap.contains(e.target)) {
        filterDropdown.classList.remove('active');
    }
});

// ═══ DETAIL MODAL FUNCTIONS ═══
function openDetailAkun(id, username, email, password, role, roleLabel, status, isActive) {
    document.getElementById('detailId').textContent = id;
    document.getElementById('detailNama').textContent = username;
    document.getElementById('detailUsername').textContent = username;
    document.getElementById('detailEmail').textContent = email;
    
    // Password: tampilkan asli untuk karyawan & manajer, sembunyikan untuk customer
    if (role == '3') { // Customer
        document.getElementById('detailPassword').textContent = '•••••• (Tersembunyi)';
    } else {
        document.getElementById('detailPassword').textContent = password;
    }
    
    document.getElementById('detailRole').innerHTML = '<span class="role-badge badge-' + role + '">' + roleLabel + '</span>';
    
    const statusEl = document.getElementById('detailStatus');
    if (isActive == '1' || isActive == 1) {
        statusEl.innerHTML = '<span class="detail-modal-status detail-modal-status-active"><i class="fa-solid fa-circle-check"></i> Aktif</span>';
    } else {
        statusEl.innerHTML = '<span class="detail-modal-status detail-modal-status-inactive"><i class="fa-solid fa-circle-xmark"></i> Nonaktif</span>';
    }
    
    document.getElementById('detailModalAkun').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeDetailModal(e) {
    if (e && e.target !== e.currentTarget) return;
    document.getElementById('detailModalAkun').classList.remove('active');
    document.body.style.overflow = '';
}

// Close with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeDetailModal();
        document.getElementById('filterDropdown').classList.remove('active');
        if (!document.getElementById('modalAkun').classList.contains('hidden')) {
            window.location.href = 'akun.php?role=<?= $current_filter ?>';
        }
    }
});
</script>
</body>
</html>