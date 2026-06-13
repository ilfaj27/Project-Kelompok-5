<?php
session_start();
include '../includes/config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'pemilik') {
    echo "<script>alert('Akses Ditolak!'); window.location='../dashboard.php';</script>";
    exit();
}
$nama = $_SESSION['nama'];
$role = $_SESSION['role'];

function getProfilePhotoPath()
{
    $photo = $_SESSION['Profile_Photo'] ?? '';
    if (empty($photo))
        return '';
    $current_dir = dirname($_SERVER['PHP_SELF']);
    if (strpos($current_dir, '/master/') !== false || strpos($current_dir, '/laporan/') !== false) {
        return '../' . $photo;
    }
    return $photo;
}

function getProfilePhotoAbsolutePath()
{
    $photo = $_SESSION['Profile_Photo'] ?? '';
    if (empty($photo))
        return '';
    return dirname(__DIR__) . '/' . $photo;
}

$profile_photo = getProfilePhotoPath();
$profile_photo_abs = getProfilePhotoAbsolutePath();
if (!empty($profile_photo) && !file_exists($profile_photo_abs)) {
    $profile_photo = '';
}

$map_jk = [0 => 'Perempuan', 1 => 'Laki-laki'];
$map_status = [0 => 'Nonaktif', 1 => 'Aktif'];

function safe_sqlsrv_query($conn, $sql, $params = [], $die_on_error = true)
{
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

function safe_sqlsrv_fetch_array($stmt, $fetch_type = SQLSRV_FETCH_ASSOC)
{
    if ($stmt === false || $stmt === null)
        return false;
    return sqlsrv_fetch_array($stmt, $fetch_type);
}

function safe_sqlsrv_has_rows($stmt)
{
    if ($stmt === false || $stmt === null)
        return false;
    return sqlsrv_has_rows($stmt);
}

function formatDate($date)
{
    if (!$date)
        return '-';
    if (is_object($date) && method_exists($date, 'format')) {
        return $date->format('d M Y H:i');
    }
    return $date;
}

function formatDateOnly($date)
{
    if (!$date)
        return '-';
    if (is_object($date) && method_exists($date, 'format')) {
        return $date->format('d M Y');
    }
    return $date;
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
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $email = $_POST['email'] ?? '';

    $checkID = safe_sqlsrv_query($conn, "SELECT ID_Karyawan FROM Karyawan WHERE ID_Karyawan=?", array($id_kry), false);
    if ($checkID && safe_sqlsrv_has_rows($checkID)) {
        header("Location: karyawan.php?status=error&msg=ID Karyawan sudah terdaftar!");
        exit();
    }

    $stmt = safe_sqlsrv_query(
        $conn,
        "INSERT INTO Karyawan (ID_Karyawan, Nama_Karyawan, Tanggal_Lahir, Tempat_Lahir, Alamat, Jenis_Kelamin, Is_Deleted, Jabatan, No_Telepon, Email, Username, Kata_Sandi, Status, Is_Deleted2, Created_By, Created_Date) 
        VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, 0, ?, GETDATE())",
        array($id_kry, $nama_kry, $tanggal_lahir, $tempat_lahir, $alamat, $jk, $jabatan, $telp, $email, $username, $password, $status, $created_by),
        false
    );

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
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $email = $_POST['email'] ?? '';

    $stmt = safe_sqlsrv_query(
        $conn,
        "UPDATE Karyawan SET 
            Nama_Karyawan=?, 
            Tanggal_Lahir=?, 
            Tempat_Lahir=?, 
            Alamat=?, 
            Jenis_Kelamin=?, 
            Jabatan=?, 
            No_Telepon=?, 
            Email=?,
            Username=?,
            Kata_Sandi=?,
            Status=?,
            Modified_By=?, 
            Modified_Date=GETDATE() 
        WHERE ID_Karyawan=?",
        array($_POST['nama'], $tanggal_lahir, $tempat_lahir, $alamat, $jk, $jabatan, $telp, $email, $username, $password, $status, $modified_by, $_POST['id_kry']),
        false
    );

    header($stmt ? "Location: karyawan.php?status=success&msg=Data staf berhasil diperbarui!" : "Location: karyawan.php?status=error&msg=Gagal memperbarui data!");
    exit();
}

// ============================================
// PROSES TOGGLE STATUS (AJAX) - PERBAIKAN TOGGLE 0↔1
// ============================================
if (isset($_POST['toggle_status']) && isset($_POST['id_kry'])) {
    $modified_by = $_SESSION['nama'] ?? 'SYSTEM';
    $id_kry = $_POST['id_kry'];

    // Cek status saat ini dulu
    $cek = safe_sqlsrv_query($conn, "SELECT Status FROM Karyawan WHERE ID_Karyawan = ?", array($id_kry), false);
    if ($cek === false) {
        echo json_encode(['success' => false, 'message' => 'Gagal cek status']);
        exit();
    }

    $row = safe_sqlsrv_fetch_array($cek, SQLSRV_FETCH_ASSOC);
    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Data tidak ditemukan']);
        exit();
    }

    // Toggle: kalau 1 jadi 0, kalau 0 jadi 1
    $current_status = intval($row['Status']);
    $new_status = $current_status === 1 ? 0 : 1;

    $stmt = safe_sqlsrv_query(
        $conn,
        "UPDATE Karyawan SET Status=?, Modified_By=?, Modified_Date=GETDATE() WHERE ID_Karyawan=?",
        array($new_status, $modified_by, $id_kry),
        false
    );

    echo json_encode([
        'success' => $stmt !== false,
        'status' => $new_status,
        'message' => $new_status === 1 ? 'Aktif' : 'Nonaktif'
    ]);
    exit();
}

// ============================================
// PROSES DELETE (SOFT DELETE)
// ============================================
if (isset($_GET['delete_id'])) {
    $deleted_by = $_SESSION['nama'] ?? 'SYSTEM';
    $stmt = safe_sqlsrv_query(
        $conn,
        "UPDATE Karyawan SET 
            Is_Deleted=1, 
            Status=0,
            Deleted_By=?, 
            Deleted_Date=GETDATE() 
        WHERE ID_Karyawan=?",
        array($deleted_by, $_GET['delete_id']),
        false
    );

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

// Get audit data for edit modal
$edit_audit = null;
if ($edit_data) {
    $edit_audit = [
        'Created_By' => $edit_data['Created_By'] ?? 'SYSTEM',
        'Created_Date' => formatDate($edit_data['Created_Date'] ?? null),
        'Modified_By' => $edit_data['Modified_By'] ?? '-',
        'Modified_Date' => formatDate($edit_data['Modified_Date'] ?? null),
        'Deleted_By' => $edit_data['Deleted_By'] ?? '-',
        'Deleted_Date' => formatDate($edit_data['Deleted_Date'] ?? null)
    ];
}

// --- GENERATE ID KARYAWAN OTOMATIS ---
$next_id_kry = 'KRY00001'; // Nilai awal jika database kosong

// Mencari ID terakhir yang terdaftar di database
$q_max_id = safe_sqlsrv_query($conn, "SELECT TOP 1 ID_Karyawan FROM Karyawan ORDER BY ID_Karyawan DESC", [], false);
if ($q_max_id && safe_sqlsrv_has_rows($q_max_id)) {
    $row_max = safe_sqlsrv_fetch_array($q_max_id, SQLSRV_FETCH_ASSOC);
    $last_id = $row_max['ID_Karyawan']; // Contoh hasil: "KRY00005"
    
    // Memisahkan teks "KRY" dan mengambil angka di belakangnya (00005 -> 5)
    $num_part = intval(substr($last_id, 3)); 
    $next_num = $num_part + 1; // Menambahkan nilai 1 (5 + 1 = 6)
    
    // Menggabungkan kembali teks "KRY" dengan angka baru berformat 5 digit (6 -> "00006")
    $next_id_kry = 'KRY' . str_pad($next_num, 5, '0', STR_PAD_LEFT); 
}

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Kelola Karyawan | HoopBall</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800;900&family=Barlow:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --orange: #FF4500;
            --orange-lt: rgba(255, 69, 0, .10);
            --orange-dk: #E03E00;
            --green: #10B981;
            --green-lt: rgba(16, 185, 129, .10);
            --blue: #3B82F6;
            --blue-lt: rgba(59, 130, 246, .10);
            --purple: #8B5CF6;
            --purple-lt: rgba(139, 92, 246, .10);
            --red: #EF4444;
            --red-lt: rgba(239, 68, 68, .10);
            --red-dk: #DC2626;
            --yellow: #F59E0B;
            --yellow-lt: rgba(245, 158, 11, .10);
            --sidebar: #0D1117;
            --sidebar-w: 260px;
            --topbar-h: 70px;
            --card-bg: #FFFFFF;
            --border: #E5E7EB;
            --border-lt: #F3F4F6;
            --text: #111827;
            --text-md: #374151;
            --muted: #6B7280;
            --bg: #F3F4F6;
            --zebra-orange: #FFF7ED;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Barlow', sans-serif;
            background: var(--bg);
            display: flex;
            min-height: 100vh;
            color: var(--text);
        }

        /* SIDEBAR */
        .sidebar {
            width: var(--sidebar-w);
            background: var(--sidebar);
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            display: flex;
            flex-direction: column;
            padding: 28px 18px;
            border-right: 1px solid rgba(255, 255, 255, .04);
            z-index: 200;
            overflow-y: auto;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        .sidebar::-webkit-scrollbar {
            display: none;
        }

        .sb-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 8px;
            margin-bottom: 36px;
            text-decoration: none;
        }

        .sb-icon {
            width: 40px;
            height: 40px;
            background: var(--orange);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 18px;
            flex-shrink: 0;
            box-shadow: 0 4px 14px rgba(255, 69, 0, .4);
        }

        .sb-brand-name {
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 20px;
            font-weight: 900;
            color: #fff;
            letter-spacing: 1px;
        }

        .sb-brand-sub {
            font-size: 9px;
            color: #4B5563;
            font-weight: 700;
            text-transform: uppercase;
        }

        .sb-section-label {
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            color: #374151;
            letter-spacing: .8px;
            padding: 0 10px;
            margin: 22px 0 8px;
        }

        .sb-link {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #6B7280;
            text-decoration: none;
            padding: 10px 12px;
            border-radius: 10px;
            margin-bottom: 2px;
            font-size: 13px;
            font-weight: 600;
            transition: all .2s ease;
            position: relative;
        }

        .sb-link .sb-icon-wrap {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            transition: .2s;
            flex-shrink: 0;
            background: rgba(255, 255, 255, .04);
        }

        .sb-link:hover {
            color: #E5E7EB;
            background: rgba(255, 255, 255, .04);
        }

        .sb-link:hover .sb-icon-wrap {
            background: rgba(255, 255, 255, .08);
        }

        .sb-link.active {
            color: #fff;
            background: var(--orange-lt);
        }

        .sb-link.active .sb-icon-wrap {
            background: var(--orange);
            color: #fff;
        }

        .sb-bottom {
            margin-top: auto;
            padding-top: 20px;
        }

        .sb-user {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(255, 255, 255, .04);
            border-radius: 12px;
            padding: 12px;
            border: 1px solid rgba(255, 255, 255, .06);
        }

        .sb-avatar {
            width: 36px;
            height: 36px;
            background: var(--orange);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 14px;
            flex-shrink: 0;
            overflow: hidden;
        }

        .sb-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }

        .sb-user-name {
            font-size: 13px;
            font-weight: 800;
            color: #E5E7EB;
            line-height: 1.1;
        }

        .sb-user-role {
            font-size: 10px;
            color: var(--orange);
            font-weight: 700;
            text-transform: uppercase;
        }

        .sb-logout {
            margin-left: auto;
            color: #4B5563;
            font-size: 13px;
            transition: .2s;
            cursor: pointer;
            text-decoration: none;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
        }

        .sb-logout:hover {
            color: var(--red);
            background: rgba(239, 68, 68, .1);
        }

        /* MAIN & TOPBAR */
        .main {
            margin-left: calc(var(--sidebar-w) - 1px);
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .topbar {
            background: var(--card-bg);
            height: var(--topbar-h);
            padding: 0 40px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid var(--border);
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 1px 0 rgba(0, 0, 0, .04);
        }

        .topbar-left {
            display: flex;
            flex-direction: column;
        }

        .topbar-title {
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 26px;
            font-weight: 900;
            color: var(--text);
            letter-spacing: -.5px;
            line-height: 1;
        }

        .topbar-breadcrumb {
            font-size: 12px;
            color: var(--muted);
            font-weight: 600;
            margin-top: 2px;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .topbar-btn {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: var(--bg);
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--muted);
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            transition: .2s;
            position: relative;
        }

        .topbar-btn:hover {
            border-color: var(--orange);
            color: var(--orange);
            background: var(--orange-lt);
        }

        .notif-dot {
            position: absolute;
            top: 7px;
            right: 7px;
            width: 7px;
            height: 7px;
            background: var(--orange);
            border-radius: 50%;
            border: 2px solid #fff;
        }

        .dropdown-wrap {
            position: relative;
        }

        .topbar-user {
            display: flex;
            align-items: center;
            gap: 10px;
            background: var(--bg);
            border: 1px solid var(--border);
            padding: 6px 14px 6px 8px;
            border-radius: 12px;
            cursor: pointer;
            transition: .2s;
        }

        .topbar-user:hover {
            border-color: var(--orange);
        }

        .t-avatar {
            width: 32px;
            height: 32px;
            background: var(--orange);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 13px;
            overflow: hidden;
        }

        .t-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }

        .t-name {
            font-size: 13px;
            font-weight: 800;
            color: var(--text);
            line-height: 1.1;
            text-transform: uppercase;
        }

        .t-role {
            font-size: 10px;
            color: var(--orange);
            font-weight: 700;
            text-transform: uppercase;
        }

        .t-chevron {
            color: var(--muted);
            font-size: 10px;
            margin-left: 4px;
        }

        .dropdown-menu {
            display: none;
            position: absolute;
            right: 0;
            top: calc(100% + 8px);
            background: #fff;
            min-width: 200px;
            border-radius: 12px;
            border: 1px solid var(--border);
            box-shadow: 0 15px 40px rgba(0, 0, 0, .12);
            overflow: hidden;
            padding: 8px 0;
            z-index: 999;
        }

        .dropdown-wrap:hover .dropdown-menu {
            display: block;
        }

        .dd-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 11px 16px;
            color: #444;
            text-decoration: none;
            font-size: 13px;
            font-weight: 700;
            transition: .15s;
        }

        .dd-item:hover {
            background: #FFF7ED;
            color: var(--orange);
        }

        .dd-item i {
            font-size: 14px;
            width: 18px;
            text-align: center;
        }

        .dd-divider {
            border: none;
            border-top: 1px solid #F3F4F6;
            margin: 4px 0;
        }

        /* CLOCK */
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

            0%,
            100% {
                opacity: .5;
            }

            50% {
                opacity: 1;
            }
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

        .content {
            padding: 32px 40px;
            flex: 1;
        }

        .page-header {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            margin-bottom: 28px;
        }

        .page-title-tag {
            width: 36px;
            height: 4px;
            background: var(--orange);
            border-radius: 2px;
            margin-bottom: 8px;
        }

        .page-title {
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 30px;
            font-weight: 900;
            color: var(--text);
            text-transform: uppercase;
            letter-spacing: -.5px;
        }

        .stat-chips {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .stat-chip {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 18px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 700;
            transition: all .2s;
        }

        .stat-chip:hover {
            transform: translateY(-2px);
        }

        .chip-green {
            background: var(--green-lt);
            color: var(--green);
        }

        .chip-red {
            background: var(--red-lt);
            color: var(--red);
        }

        .chip-blue {
            background: var(--blue-lt);
            color: var(--blue);
        }

        .chip-val {
            font-family: 'Barlow Condensed';
            font-size: 20px;
            font-weight: 900;
        }

        .stat-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
            margin-bottom: 28px;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 22px 24px;
            border: 1px solid var(--border);
            position: relative;
            overflow: hidden;
            transition: all .2s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 32px rgba(0, 0, 0, .08);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            border-radius: 4px 0 0 4px;
        }

        .sc-purple::before {
            background: var(--purple);
        }

        .sc-blue::before {
            background: var(--blue);
        }

        .sc-green::before {
            background: var(--green);
        }

        .stat-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }

        .stat-icon-wrap {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        .si-purple {
            background: var(--purple-lt);
            color: var(--purple);
        }

        .si-blue {
            background: var(--blue-lt);
            color: var(--blue);
        }

        .si-green {
            background: var(--green-lt);
            color: var(--green);
        }

        .stat-trend {
            font-size: 11px;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 3px;
            padding: 4px 8px;
            border-radius: 20px;
        }

        .trend-up {
            color: var(--green);
            background: var(--green-lt);
        }

        .stat-value {
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 30px;
            font-weight: 900;
            color: var(--text);
            line-height: 1;
            margin-bottom: 6px;
        }

        .stat-label {
            font-size: 12px;
            color: var(--muted);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        .stat-sublabel {
            font-size: 11px;
            color: var(--muted);
            margin-top: 4px;
            opacity: .7;
        }

        /* TOGGLE SWITCH */
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 44px;
            height: 24px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #E5E7EB;
            transition: .3s;
            border-radius: 24px;
        }

        .toggle-slider:before {
            position: absolute;
            content: '';
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .3s;
            border-radius: 50%;
            box-shadow: 0 2px 4px rgba(0, 0, 0, .2);
        }

        input:checked+.toggle-slider {
            background-color: var(--green);
        }

        input:checked+.toggle-slider:before {
            transform: translateX(20px);
        }

        input:disabled+.toggle-slider {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .toggle-label {
            font-size: 11px;
            font-weight: 800;
            margin-left: 8px;
        }

        /* Toggle loading state */
        .toggle-loading {
            opacity: 0.6;
            pointer-events: none;
        }

        .toggle-loading .toggle-slider {
            animation: togglePulse 1s infinite;
        }

        @keyframes togglePulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.5;
            }
        }

        /* TOOLBAR & FILTER */
        .toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .toolbar-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .filter-dropdown-wrap {
            position: relative;
            display: inline-block;
        }

        .btn-filter {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background-color: var(--orange);
            color: #ffffff !important;
            padding: 11px 20px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 800;
            text-transform: uppercase;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 4px 12px rgba(255, 69, 0, 0.2);
        }

        .btn-filter:hover {
            background-color: var(--orange-dk) !important;
            color: #ffffff !important;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(255, 69, 0, 0.35);
        }

        .btn-filter i.arrow-icon {
            font-size: 10px;
            transition: transform 0.3s;
        }

        .btn-filter.active i.arrow-icon {
            transform: rotate(180deg);
        }

        .filter-card {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid var(--border);
            padding: 24px;
            width: 300px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.12);
            z-index: 50;
            display: none;
        }

        .filter-card.open {
            display: block;
            animation: slideFilter 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        @keyframes slideFilter {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .filter-card h4 {
            font-size: 15px;
            font-weight: 800;
            color: var(--text);
            margin-bottom: 20px;
            text-align: left;
        }

        .filter-card .filter-group {
            margin-bottom: 16px;
            text-align: left;
        }

        .filter-card .filter-group label {
            display: block;
            font-size: 11px;
            font-weight: 800;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .filter-input {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            font-size: 13px;
            font-family: 'Barlow', sans-serif;
            outline: none;
            transition: all .2s;
            color: var(--text);
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236B7280' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
            padding-right: 40px;
        }

        .filter-input:focus {
            border-color: var(--orange);
        }

        .filter-buttons {
            display: flex;
            gap: 10px;
            margin-top: 24px;
        }

        .btn-filter-apply {
            flex: 1.2;
            background: var(--orange);
            color: white;
            border: none;
            padding: 12px;
            border-radius: 10px;
            font-weight: 800;
            font-size: 12px;
            text-transform: uppercase;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all .2s;
        }

        .btn-filter-apply:hover {
            background: var(--orange-dk);
        }

        .btn-filter-reset {
            flex: 1;
            background: var(--border-lt);
            color: var(--text-md);
            border: 1px solid var(--border);
            padding: 12px;
            border-radius: 10px;
            font-weight: 800;
            font-size: 12px;
            text-transform: uppercase;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all .2s;
        }

        .btn-filter-reset:hover {
            background: var(--bg);
        }

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
            box-shadow: 0 8px 20px rgba(255, 69, 0, .3) !important;
        }

        .card {
            background: var(--card-bg);
            border-radius: 16px;
            border: 1px solid var(--border);
            overflow: hidden;
            transition: all .2s ease;
        }

        .card:hover {
            box-shadow: 0 8px 24px rgba(0, 0, 0, .06);
        }

        .card-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .card-title {
            font-size: 15px;
            font-weight: 800;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .card-title i {
            color: var(--orange);
            font-size: 14px;
        }

        .card-badge {
            background: var(--orange-lt);
            color: var(--orange);
            font-size: 11px;
            font-weight: 800;
            padding: 4px 10px;
            border-radius: 20px;
        }

        .table-wrap {
            overflow-x: auto;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th {
            padding: 13px 20px;
            font-size: 10px;
            font-weight: 800;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .6px;
            border-bottom: 2px solid var(--border-lt);
            text-align: left;
        }

        .data-table td {
            padding: 16px 20px;
            font-size: 13px;
            border-bottom: 1px solid var(--border-lt);
            vertical-align: middle;
        }

        .data-table tbody tr:last-child td {
            border-bottom: none;
        }

        .data-table tbody tr:nth-child(odd) {
            background-color: var(--zebra-orange);
        }

        .data-table tbody tr:nth-child(even) {
            background-color: #FFFFFF;
        }

        .data-table tbody tr:hover td {
            background-color: #FED7AA !important;
        }

        .row-num {
            font-family: 'Barlow Condensed';
            font-weight: 800;
            color: var(--muted);
            font-size: 14px;
            text-align: center;
        }

        .emp-name {
            font-weight: 700;
            color: var(--text);
        }

        .cell-detail {
            font-size: 11px;
            color: var(--muted);
            font-weight: 600;
            margin-top: 2px;
        }

        .jabatan-badge {
            background: #EEF2FF;
            color: #4338CA;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 800;
            display: inline-block;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 800;
        }

        .status-aktif {
            background: var(--green-lt);
            color: var(--green);
        }

        .status-nonaktif {
            background: var(--red-lt);
            color: var(--red);
        }

        /* ACTIONS */
        .action-group {
            display: flex;
            gap: 8px;
            justify-content: flex-start;
            align-items: center;
        }

        .btn-action {
            width: 34px;
            height: 34px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            transition: all .25s cubic-bezier(.4, 0, .2, 1);
            border: 1.5px solid transparent;
            cursor: pointer;
        }

        .btn-edit {
            background: linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%);
            color: #1E40AF;
            border-color: #BFDBFE;
        }

        .btn-edit:hover {
            background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%);
            color: #fff;
            border-color: #3B82F6;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(59, 130, 246, .35);
        }

        .btn-delete {
            background: linear-gradient(135deg, #FEF2F2 0%, #FEE2E2 100%);
            color: #DC2626;
            border-color: #FECACA;
        }

        .btn-delete:hover {
            background: linear-gradient(135deg, #EF4444 0%, #DC2626 100%);
            color: #fff;
            border-color: #EF4444;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(239, 68, 68, .35);
        }

        .btn-view {
            background: linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%);
            color: #1E40AF;
            border-color: #BFDBFE;
        }

        .btn-view:hover {
            background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%);
            color: #fff;
            border-color: #3B82F6;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(59, 130, 246, .35);
        }

        .pagination-wrap {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-top: none;
            border-radius: 0 0 16px 16px;
            padding: 16px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 32px;
        }

        .pagination-info {
            font-size: 12px;
            color: var(--muted);
            font-weight: 600;
        }

        .pagination-info strong {
            color: var(--text);
            font-weight: 800;
        }

        .pagination-nav {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .page-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 36px;
            height: 36px;
            padding: 0 10px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
            font-family: 'Barlow', sans-serif;
            text-decoration: none;
            cursor: pointer;
            transition: all .2s ease;
            border: 1.5px solid var(--border);
            color: var(--text-md);
            background: #fff;
        }

        .page-btn:hover:not(.disabled):not(.active) {
            border-color: var(--orange);
            color: var(--orange);
            background: var(--orange-lt);
            transform: translateY(-1px);
        }

        .page-btn.active {
            background: var(--orange);
            color: #fff;
            border-color: var(--orange);
            box-shadow: 0 4px 12px rgba(255, 69, 0, .3);
            font-weight: 800;
        }

        .page-btn.disabled {
            opacity: 0.4;
            cursor: not-allowed;
            pointer-events: none;
        }

        .page-btn i {
            font-size: 11px;
        }

        .page-ellipsis {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 36px;
            height: 36px;
            color: var(--muted);
            font-size: 13px;
            font-weight: 800;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--muted);
        }

        .empty-state i {
            font-size: 40px;
            opacity: .3;
            display: block;
            margin-bottom: 14px;
        }

        .empty-state p {
            font-size: 13px;
            font-weight: 700;
        }

        /* MODALS */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .55);
            backdrop-filter: blur(6px);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 2000;
        }

        .modal-overlay.hidden {
            display: none;
        }

        .modal-box {
            background: #fff;
            border-radius: 20px;
            width: 680px;
            max-width: 95vw;
            box-shadow: 0 25px 60px rgba(0, 0, 0, .2);
            position: relative;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-head {
            padding: 28px 32px 24px;
            border-bottom: 1px solid var(--border);
        }

        .modal-tag {
            font-size: 10px;
            font-weight: 800;
            color: var(--orange);
            text-transform: uppercase;
            letter-spacing: .8px;
            margin-bottom: 6px;
        }

        .modal-title {
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 24px;
            font-weight: 900;
            color: var(--text);
            letter-spacing: -.3px;
        }

        .modal-sub {
            font-size: 13px;
            color: var(--muted);
            margin-top: 4px;
        }

        .modal-subtitle {
            font-size: 10px;
            font-weight: 800;
            color: var(--orange);
            text-transform: uppercase;
            margin-bottom: 6px;
            letter-spacing: .8px;
        }

        .modal-body {
            padding: 28px 32px 32px;
        }

        .modal-close {
            position: absolute;
            top: 20px;
            right: 20px;
            background: var(--bg);
            border: 1px solid var(--border);
            font-size: 14px;
            color: var(--muted);
            cursor: pointer;
            transition: .2s;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-close:hover {
            color: var(--red);
            border-color: var(--red);
            background: var(--red-lt);
        }

        .modal-box, 
.detail-modal-box {
    scrollbar-width: none; /* Untuk Firefox */
    -ms-overflow-style: none; /* Untuk IE dan Edge */
}

.modal-box::-webkit-scrollbar, 
.detail-modal-box::-webkit-scrollbar {
    display: none; /* Untuk Chrome, Safari, dan Opera */
}

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .full-width {
            grid-column: span 2;
        }

        .field-label {
            font-size: 11px;
            font-weight: 800;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .5px;
            display: block;
            margin-bottom: 6px;
        }

        .field-label .required {
            color: var(--red);
            margin-left: 2px;
            font-size: 14px;
            font-weight: 900;
        }

        .modal-input {
            width: 100%;
            padding: 12px 14px;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            font-size: 14px;
            font-family: 'Barlow', sans-serif;
            transition: .2s;
            background: #FAFAFA;
            color: var(--text);
            margin-bottom: 4px;
        }

        .modal-input:focus {
            outline: none;
            border-color: var(--orange);
            background: #fff;
            box-shadow: 0 0 0 3px rgba(255, 69, 0, .08);
        }

        .modal-input[type="date"] {
            cursor: pointer;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%236B7280' stroke-width='2'%3E%3Crect x='3' y='4' width='18' height='18' rx='2' ry='2'/%3E%3Cline x1='16' y1='2' x2='16' y2='6'/%3E%3Cline x1='8' y1='2' x2='8' y2='6'/%3E%3Cline x1='3' y1='10' x2='21' y2='10'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 40px;
        }

        .modal-input[type="date"]::-webkit-calendar-picker-indicator {
            opacity: 0;
            cursor: pointer;
            position: absolute;
            right: 0;
            width: 40px;
            height: 100%;
        }

        .modal-input[readonly] {
            background: var(--border-lt);
            color: var(--muted);
        }

        .btn-submit {
            grid-column: span 2;
            width: 100%;
            background: var(--orange);
            color: #fff;
            border: none;
            padding: 14px;
            border-radius: 10px;
            font-weight: 800;
            font-size: 14px;
            cursor: pointer;
            text-transform: uppercase;
            letter-spacing: .5px;
            transition: .2s;
            font-family: 'Barlow', sans-serif;
            margin-top: 8px;
        }

        .btn-submit:hover {
            background: var(--orange-dk);
            transform: translateY(-1px);
            box-shadow: 0 8px 20px rgba(255, 69, 0, .25);
        }

        .btn-submit:disabled {
            background: #D1D5DB;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .val-msg {
            font-size: 11px;
            color: var(--red);
            font-weight: 600;
            margin-bottom: 10px;
            display: none;
            min-height: 16px;
        }

        .val-msg.show {
            display: block;
        }

        .val-msg i {
            margin-right: 4px;
        }

        .modal-input.error {
            border-color: var(--red) !important;
            background-color: #FEF2F2 !important;
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.15) !important;
        }

        .modal-input.error:focus {
            border-color: var(--red) !important;
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.25) !important;
        }

        /* AUDIT TRAIL IN MODAL */
        .audit-box {
            grid-column: span 2;
            background: var(--bg);
            border-radius: 12px;
            padding: 16px;
            border: 1px solid var(--border);
            margin-top: 8px;
        }

        .audit-title {
            font-size: 10px;
            font-weight: 800;
            color: var(--orange);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .audit-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 12px;
        }

        @media(max-width: 600px) {
            .audit-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        .audit-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .audit-label {
            font-size: 10px;
            font-weight: 700;
            color: var(--muted);
            text-transform: uppercase;
        }

        .audit-value {
            font-size: 12px;
            font-weight: 700;
            color: var(--text);
        }

        .audit-value-mono {
            font-family: 'Barlow Condensed';
            font-size: 13px;
            font-weight: 800;
            color: var(--orange);
        }

        /* DETAIL MODAL - TANPA AUDIT TRAIL */
        .detail-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.55);
            backdrop-filter: blur(6px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 2000;
        }

        .detail-modal-overlay.open {
            display: flex;
        }

        .detail-modal-box {
            background: #fff;
            border-radius: 20px;
            width: 520px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.2);
            position: relative;
        }

        .detail-modal-close {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: var(--bg);
            border: 1.5px solid var(--border);
            color: var(--muted);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: .2s;
            font-size: 14px;
        }

        .detail-modal-close:hover {
            background: var(--red-lt);
            color: var(--red);
            border-color: var(--red);
        }

        .detail-photo-card {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1.5px dashed var(--border);
        }

        .detail-icon-wrap {
            width: 80px;
            height: 80px;
            background: var(--orange-lt);
            color: var(--orange);
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            margin-bottom: 16px;
            box-shadow: 0 8px 20px rgba(255, 69, 0, 0.15);
        }

        .detail-main-name {
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 24px;
            font-weight: 900;
            color: var(--text);
            text-transform: uppercase;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid var(--border-lt);
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-key {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 12px;
            font-weight: 700;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .info-key i {
            color: var(--orange);
            font-size: 14px;
            width: 18px;
            text-align: center;
        }

        .info-val {
            font-size: 13px;
            font-weight: 700;
            color: var(--text);
        }

        .info-val-mono {
            font-family: 'Barlow Condensed';
            font-size: 14px;
            font-weight: 800;
            color: var(--orange);
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .sp-ready {
            background: var(--green-lt);
            color: var(--green);
        }

        .sp-maint {
            background: var(--red-lt);
            color: var(--red);
        }

        .btn-kembali {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            background: #0D1117;
            color: #fff;
            border: none;
            padding: 14px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 800;
            font-family: 'Barlow', sans-serif;
            text-transform: uppercase;
            cursor: pointer;
            transition: .2s;
            margin-top: 20px;
        }

        .btn-kembali:hover {
            background: var(--orange);
        }

        /* SEARCH BOX */
        .search-box {
            position: relative;
            width: 300px;
        }

        .search-box i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--muted);
            font-size: 13px;
        }

        .search-box input {
            width: 100%;
            padding: 10px 14px 10px 40px;
            background: var(--card-bg);
            border: 1.5px solid var(--border);
            border-radius: 10px;
            font-size: 13px;
            font-family: 'Barlow', sans-serif;
            outline: none;
            transition: all .2s;
            color: var(--text);
        }

        .search-box input:focus {
            border-color: var(--orange);
            box-shadow: 0 0 0 3px var(--orange-lt);
        }

        .search-box input::placeholder {
            color: #9CA3AF;
        }

        .action-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 12px;
        }

        html,
        body {
            /* Untuk Firefox */
            scrollbar-width: none;

            /* Untuk Internet Explorer dan Edge versi lama */
            -ms-overflow-style: none;
        }

        /* Untuk Chrome, Safari, dan Opera */
        html::-webkit-scrollbar,
        body::-webkit-scrollbar {
            display: none;
        }

        body.swal2-shown,
        html.swal2-shown {
            padding-right: 0px !important;
        }

        @media(max-width: 1100px) {
            .stat-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media(max-width: 768px) {
            .sidebar {
                width: 0;
                overflow: hidden;
                padding: 0;
            }

            .main {
                margin-left: 0;
            }

            .stat-grid {
                grid-template-columns: 1fr;
            }

            .content {
                padding: 20px;
            }

            .topbar {
                padding: 0 20px;
            }

            .pagination-wrap {
                flex-direction: column;
                gap: 12px;
            }

            .filter-card {
                width: 100%;
                right: 0;
            }

            .audit-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <!-- MODAL TAMBAH/EDIT -->
    <div class="modal-overlay <?= ($edit_data || $show_add) ? '' : 'hidden' ?>" id="modal">
        <div class="modal-box">
            <button class="modal-close" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></button>
            <div class="modal-head">
                <div class="modal-tag">Master Karyawan</div>
                <div class="modal-title"><?= $edit_data ? 'Edit Data Staf' : 'Tambah Karyawan Baru' ?></div>
                <div class="modal-sub">
                    <?= $edit_data ? 'Perbarui informasi karyawan yang ada' : 'Daftarkan karyawan baru ke dalam sistem' ?>
                </div>
            </div>
            <div class="modal-body">
                <form method="POST" id="formKaryawan" onsubmit="return validateForm(this)">
                    <div class="form-grid">
                        <div>
    <label class="field-label">ID Karyawan <span class="required">*</span></label>
    <!-- Menggunakan value $next_id_kry untuk data baru dan dikunci dengan atribut 'readonly' -->
    <input type="text" name="id_kry" id="id_kry" class="modal-input" value="<?= $edit_data['ID_Karyawan'] ?? $next_id_kry ?>" readonly placeholder="KRY00001">
    <div class="val-msg" id="val-id_kry"><i class="fa-solid fa-circle-exclamation"></i> ID Karyawan wajib diisi</div>
</div>
                        <div>
                            <label class="field-label">Nama Lengkap <span class="required">*</span></label>
                            <input type="text" name="nama" id="nama" class="modal-input"
                                value="<?= htmlspecialchars($edit_data['Nama_Karyawan'] ?? '') ?>" required
                                minlength="3" maxlength="100" placeholder="Nama lengkap">
                            <div class="val-msg" id="val-nama"><i class="fa-solid fa-circle-exclamation"></i> Nama
                                minimal 3 karakter</div>
                        </div>
                        <div>
                            <label class="field-label">Username <span class="required">*</span></label>
                            <input type="text" name="username" id="username" class="modal-input"
                                value="<?= htmlspecialchars($edit_data['Username'] ?? '') ?>" required minlength="3"
                                maxlength="50" placeholder="username_karyawan">
                            <div class="val-msg" id="val-username"><i class="fa-solid fa-circle-exclamation"></i>
                                Username minimal 3 karakter</div>
                        </div>
                        <div>
                            <label class="field-label">Password <span class="required">*</span></label>
                            <input type="password" name="password" id="password" class="modal-input"
                                value="<?= htmlspecialchars($edit_data['Kata_Sandi'] ?? '') ?>" required minlength="6"
                                maxlength="100" placeholder="••••••">
                            <div class="val-msg" id="val-password"><i class="fa-solid fa-circle-exclamation"></i>
                                Password minimal 6 karakter</div>
                        </div>
                        <div>
                            <label class="field-label">Email <span class="required">*</span></label>
                            <input type="email" name="email" id="email" class="modal-input"
                                value="<?= htmlspecialchars($edit_data['Email'] ?? '') ?>" required
                                placeholder="email@example.com">
                            <div class="val-msg" id="val-email"><i class="fa-solid fa-circle-exclamation"></i> Email
                                wajib diisi dengan format yang valid</div>
                        </div>
                        <div>
                            <label class="field-label">Nomor Telepon <span class="required">*</span></label>
                            <input type="text" name="telp" id="telp" class="modal-input"
                                value="<?= htmlspecialchars($edit_data['No_Telepon'] ?? '') ?>"
                                placeholder="08123456789" required pattern="[0-9]{10,15}" maxlength="15">
                            <div class="val-msg" id="val-telp"><i class="fa-solid fa-circle-exclamation"></i> Nomor
                                telepon 10-15 digit</div>
                        </div>
                        <div>
                            <label class="field-label">Tempat Lahir <span class="required">*</span></label>
                            <input type="text" name="tempat_lahir" id="tempat_lahir" class="modal-input"
                                value="<?= htmlspecialchars($edit_data['Tempat_Lahir'] ?? '') ?>" required minlength="2"
                                maxlength="50" placeholder="Contoh: Jakarta, Bandung">
                            <div class="val-msg" id="val-tempat_lahir"><i class="fa-solid fa-circle-exclamation"></i>
                                Tempat lahir wajib diisi</div>
                        </div>
                        <div>
                            <label class="field-label">Tanggal Lahir <span class="required">*</span></label>
                            <input type="date" name="tanggal_lahir" id="tanggal_lahir" class="modal-input" value="<?php
                            $tgl = $edit_data['Tanggal_Lahir'] ?? '';
                            if ($tgl && is_object($tgl))
                                echo $tgl->format('Y-m-d');
                            elseif ($tgl)
                                echo htmlspecialchars($tgl);
                            ?>" required max="<?= date('Y-m-d') ?>" onchange="validateDate(this)">
                            <div class="val-msg" id="val-tanggal_lahir"><i class="fa-solid fa-circle-exclamation"></i>
                                Tanggal lahir wajib diisi dan tidak boleh di masa depan</div>
                        </div>
                        <div class="full-width">
                            <label class="field-label">Alamat Lengkap <span class="required">*</span></label>
                            <textarea name="alamat" id="alamat" class="modal-input" required rows="3"
                                placeholder="Jl. Merdeka No. 10, Jakarta Pusat"
                                style="resize: none;"><?= htmlspecialchars($edit_data['Alamat'] ?? '') ?></textarea>
                            <div class="val-msg" id="val-alamat"><i class="fa-solid fa-circle-exclamation"></i> Alamat
                                wajib diisi</div>
                        </div>
                        <div>
                            <label class="field-label">Jenis Kelamin <span class="required">*</span></label>
                            <select name="jk" id="jk" class="modal-input" required>
                                <option value="1" <?= (isset($edit_data['Jenis_Kelamin']) && $edit_data['Jenis_Kelamin'] == 1) ? 'selected' : '' ?>>Laki-laki</option>
                                <option value="0" <?= (isset($edit_data['Jenis_Kelamin']) && $edit_data['Jenis_Kelamin'] == 0) ? 'selected' : '' ?>>Perempuan</option>
                            </select>
                        </div>
                        <div>
                            <label class="field-label">Jabatan <span class="required">*</span></label>
                            <select name="jabatan" id="jabatan" class="modal-input" required>
                                <option value="Manajer" <?= (isset($edit_data['Jabatan']) && $edit_data['Jabatan'] == 'Manajer') ? 'selected' : '' ?>>Manajer</option>
                                <option value="Karyawan" <?= (isset($edit_data['Jabatan']) && $edit_data['Jabatan'] == 'Karyawan') ? 'selected' : '' ?>>Karyawan</option>
                                <option value="Kasir" <?= (isset($edit_data['Jabatan']) && $edit_data['Jabatan'] == 'Kasir') ? 'selected' : '' ?>>Kasir</option>
                                <option value="Staf" <?= (isset($edit_data['Jabatan']) && $edit_data['Jabatan'] == 'Staf') ? 'selected' : '' ?>>Staf</option>
                                <option value="Keamanan" <?= (isset($edit_data['Jabatan']) && $edit_data['Jabatan'] == 'Keamanan') ? 'selected' : '' ?>>Keamanan</option>
                            </select>
                        </div>
                        <div>
                            <label class="field-label">Status <span class="required">*</span></label>
                            <select name="status" id="status" class="modal-input" required>
                                <option value="1" <?= (isset($edit_data['Status']) && $edit_data['Status'] == 1) ? 'selected' : '' ?>>Aktif</option>
                                <option value="0" <?= (isset($edit_data['Status']) && $edit_data['Status'] == 0) ? 'selected' : '' ?>>Nonaktif</option>
                            </select>
                        </div>

                        <!-- AUDIT TRAIL - SHOW IN EDIT MODE -->
                        <?php if ($edit_data && $edit_audit): ?>
                            <div class="audit-box">
                                <div class="audit-title"><i class="fa-solid fa-clock-rotate-left"></i> Audit Trail</div>
                                <div class="audit-grid">
                                    <div class="audit-item">
                                        <span class="audit-label">Dibuat Oleh</span>
                                        <span
                                            class="audit-value-mono"><?= htmlspecialchars($edit_audit['Created_By']) ?></span>
                                    </div>
                                    <div class="audit-item">
                                        <span class="audit-label">Tanggal Dibuat</span>
                                        <span
                                            class="audit-value"><?= htmlspecialchars($edit_audit['Created_Date']) ?></span>
                                    </div>
                                    <div class="audit-item">
                                        <span class="audit-label">Diubah Oleh</span>
                                        <span
                                            class="audit-value-mono"><?= htmlspecialchars($edit_audit['Modified_By']) ?></span>
                                    </div>
                                    <div class="audit-item">
                                        <span class="audit-label">Tanggal Diubah</span>
                                        <span
                                            class="audit-value"><?= htmlspecialchars($edit_audit['Modified_Date']) ?></span>
                                    </div>
                                    <div class="audit-item">
                                        <span class="audit-label">Dihapus Oleh</span>
                                        <span
                                            class="audit-value-mono"><?= htmlspecialchars($edit_audit['Deleted_By']) ?></span>
                                    </div>
                                    <div class="audit-item">
                                        <span class="audit-label">Tanggal Dihapus</span>
                                        <span
                                            class="audit-value"><?= htmlspecialchars($edit_audit['Deleted_Date']) ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <button type="submit" name="<?= $edit_data ? 'update_karyawan' : 'add_karyawan' ?>"
                            class="btn-submit">
                            <i class="fa-solid <?= $edit_data ? 'fa-floppy-disk' : 'fa-user-plus' ?>"></i>
                            <?= $edit_data ? 'Simpan Perubahan' : 'Daftarkan Karyawan' ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- DETAIL MODAL - TANPA AUDIT TRAIL -->
    <div class="detail-modal-overlay" id="detailModal" onclick="closeDetail(event)">
        <div class="detail-modal-box" onclick="event.stopPropagation()">
            <div
                style="position: sticky; top: 0; background: white; z-index: 20; padding: 24px 28px 15px; border-bottom: 1px solid rgba(0,0,0,0.03);">
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
                        <span class="info-key"><i class="fa-solid fa-user-tag"></i> Username</span>
                        <span class="info-val-mono" id="dUsername">-</span>
                    </div>
                    <div class="info-row">
                        <span class="info-key"><i class="fa-solid fa-lock"></i> Password</span>
                        <span class="info-val-mono" id="dPassword">-</span>
                    </div>
                    <div class="info-row">
                        <span class="info-key"><i class="fa-solid fa-envelope"></i> Email</span>
                        <span class="info-val" id="dEmail">-</span>
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
                        <span class="info-val" id="dAlamat"
                            style="text-align: right; max-width: 220px; line-height: 1.4;">-</span>
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

                <button onclick="closeDetail()" class="btn-kembali">
                    <i class="fa-solid fa-arrow-left"></i> KEMBALI KE LIST
                </button>
            </div>
        </div>
    </div>

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <a href="../view_pemilik.php" class="sb-brand">
            <div class="sb-icon"><i class="fa-solid fa-basketball"></i></div>
            <div>
                <div class="sb-brand-name">HOOP BALL</div>
                <div class="sb-brand-sub">Management System</div>
            </div>
        </a>
        <div class="sb-section-label">Manajemen</div>
        <nav>
            <a href="../view_pemilik.php" class="sb-link">
                <div class="sb-icon-wrap"><i class="fa-solid fa-house"></i></div> Dashboard
            </a>
            <a href="karyawan.php" class="sb-link active">
                <div class="sb-icon-wrap"><i class="fa-solid fa-user-tie"></i></div> Kelola Karyawan
            </a>
            <a href="../laporan/omzet.php" class="sb-link">
                <div class="sb-icon-wrap"><i class="fa-solid fa-chart-line"></i></div> Laporan & Omzet
            </a>
        </nav>
        <div class="sb-section-label">Akun</div>
        <a href="../profile.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-id-badge"></i></div> Profil Saya
        </a>
        <div class="sb-bottom">
            <div class="sb-user">
                <div class="sb-avatar">
                    <?php if ($profile_photo): ?><img src="<?= $profile_photo ?>" alt="Profile"><?php else: ?><i
                            class="fa-solid fa-user"></i><?php endif; ?>
                </div>
                <div>
                    <div class="sb-user-name"><?= strtoupper(htmlspecialchars($nama)) ?></div>
                    <div class="sb-user-role">MANAJER</div>
                </div>
                <a href="../logout.php" class="sb-logout" title="Keluar"><i
                        class="fa-solid fa-right-from-bracket"></i></a>
            </div>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="main">
        <header class="topbar">
            <div class="topbar-left">
                <div class="topbar-title">Kelola Data Karyawan</div>
                <div class="topbar-breadcrumb">Karyawan</div>
            </div>
            <div class="topbar-right">
                <div id="clock-display">
                    <div class="clock-time">
                        <span id="clock-h">00</span><span class="clock-colon">:</span><span id="clock-m">00</span><span
                            class="clock-colon">:</span><span id="clock-s">00</span>
                    </div>
                    <div class="clock-divider"></div>
                    <div class="clock-date" id="clock-date">MEMUAT...</div>
                </div>
                <a href="#" class="topbar-btn"><i class="fa-solid fa-magnifying-glass"></i></a>
                <a href="#" class="topbar-btn"><i class="fa-solid fa-bell"></i><span class="notif-dot"></span></a>
                <div class="dropdown-wrap">
                    <div class="topbar-user">
                        <div class="t-avatar">
                            <?php if ($profile_photo): ?><img src="<?= $profile_photo ?>" alt="Profile"><?php else: ?><i
                                    class="fa-solid fa-user"></i><?php endif; ?>
                        </div>
                        <div>
                            <div class="t-name"><?= strtoupper(htmlspecialchars($nama)) ?></div>
                            <div class="t-role">MANAJER</div>
                        </div>
                        <i class="fa-solid fa-chevron-down t-chevron"></i>
                    </div>
                    <div class="dropdown-menu">
                        <a href="../profile.php" class="dd-item"><i class="fa-solid fa-id-badge"></i> Profil Saya</a>
                        <hr class="dd-divider">
                        <a href="../logout.php" class="dd-item" style="color:var(--red);"><i
                                class="fa-solid fa-right-from-bracket"></i> Keluar</a>
                    </div>
                </div>
            </div>
        </header>

        <div class="content">
            <div class="page-header">
                <div>
                    <div class="page-title-tag"></div>
                    <div class="page-title">Daftar Karyawan</div>
                </div>
                <div class="stat-chips">
                    <div class="stat-chip chip-blue"><i class="fa-solid fa-user-tie"></i> TOTAL <span
                            class="chip-val"><?= $total_kry ?></span></div>
                    <div class="stat-chip chip-green"><i class="fa-solid fa-users"></i> AKTIF <span
                            class="chip-val"><?= $total_aktif ?></span></div>
                    <div class="stat-chip chip-red"><i class="fa-solid fa-briefcase"></i> JABATAN <span
                            class="chip-val">5</span></div>
                </div>
            </div>

            <div class="action-bar">
                <div class="search-box">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="src" placeholder="Cari karyawan..." onkeyup="searchTable()">
                </div>

                <div style="display: flex; gap: 12px; align-items: center;">
                    <div class="filter-dropdown-wrap">
                        <button class="btn-filter" id="btnFilterToggle">
                            <i class="fa-solid fa-filter"></i> Filter <i
                                class="fa-solid fa-chevron-down arrow-icon"></i>
                        </button>
                        <div class="filter-card" id="filterCard">
                            <h4>Filter Data</h4>
                            <div class="filter-group">
                                <label>Urut Berdasarkan</label>
                                <select id="filterSortBy" class="filter-input">
                                    <option value="ID_Karyawan" <?= $sort_by == 'ID_Karyawan' ? 'selected' : '' ?>>ID
                                        Karyawan</option>
                                    <option value="Nama_Karyawan" <?= $sort_by == 'Nama_Karyawan' ? 'selected' : '' ?>>Nama
                                        Lengkap</option>
                                    <option value="Jabatan" <?= $sort_by == 'Jabatan' ? 'selected' : '' ?>>Jabatan</option>
                                    <option value="Jenis_Kelamin" <?= $sort_by == 'Jenis_Kelamin' ? 'selected' : '' ?>>
                                        Jenis Kelamin</option>
                                    <option value="No_Telepon" <?= $sort_by == 'No_Telepon' ? 'selected' : '' ?>>No.
                                        Telepon</option>
                                    <option value="Status" <?= $sort_by == 'Status' ? 'selected' : '' ?>>Status</option>
                                    <option value="Created_Date" <?= $sort_by == 'Created_Date' ? 'selected' : '' ?>>
                                        Tanggal Dibuat</option>
                                    <option value="Modified_Date" <?= $sort_by == 'Modified_Date' ? 'selected' : '' ?>>
                                        Tanggal Diubah</option>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label>Jabatan</label>
                                <select id="filterJabatan" class="filter-input">
                                    <option value="" <?= empty($filter_jabatan) ? 'selected' : '' ?>>Semua Jabatan
                                    </option>
                                    <option value="Manajer" <?= $filter_jabatan == 'Manajer' ? 'selected' : '' ?>>Manajer
                                    </option>
                                    <option value="Karyawan" <?= $filter_jabatan == 'Karyawan' ? 'selected' : '' ?>>
                                        Karyawan</option>
                                    <option value="Kasir" <?= $filter_jabatan == 'Kasir' ? 'selected' : '' ?>>Kasir
                                    </option>
                                    <option value="Staf" <?= $filter_jabatan == 'Staf' ? 'selected' : '' ?>>Staf</option>
                                    <option value="Keamanan" <?= $filter_jabatan == 'Keamanan' ? 'selected' : '' ?>>
                                        Keamanan</option>
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
                                <button type="button" class="btn-filter-reset" onclick="resetFilter()"><i
                                        class="fa-solid fa-rotate-left"></i> Reset</button>
                                <button type="button" class="btn-filter-apply" onclick="applyFilter()"><i
                                        class="fa-solid fa-check"></i> Terapkan</button>
                            </div>
                        </div>
                    </div>
                    <button class="btn-add" onclick="openModal()"><i class="fa-solid fa-plus"></i> Tambah
                        Karyawan</button>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fa-solid fa-user-tie"></i> Data Karyawan</div>
                    <span class="card-badge"><?= $total_kry ?> total</span>
                </div>
                <?php if ($query_error): ?>
                    <div style="padding:20px;background:#fee;border:1px solid #fcc;border-radius:8px;margin:20px 0;">
                        <p style="color:#c00;font-weight:bold;margin:0;"><i class="fa-solid fa-circle-exclamation"></i>
                            Gagal mengambil data dari database.</p>
                        <p style="color:#666;font-size:11px;margin:5px 0 0;">Error:
                            <?= htmlspecialchars($query_error_msg) ?></p>
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
                                    <th style="width:180px;text-align:left;padding-left:15px;">Status</th>
                                    <th style="text-align:left; width:200px;">Aksi</th>
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
                                        <tr id="row-<?= htmlspecialchars($row['ID_Karyawan']) ?>"
                                            data-status="<?= $is_active ? 'aktif' : 'nonaktif' ?>">
                                            <td class="row-num"><?= $row_num ?></td>
                                            <td>
                                                <div class="emp-name"><?= htmlspecialchars($row['Nama_Karyawan']) ?></div>
                                            </td>
                                            <td><span class="jabatan-badge"><?= htmlspecialchars($row['Jabatan']) ?></span></td>
                                            <td style="color:var(--muted); font-weight:600;">
                                                <?= htmlspecialchars($row['No_Telepon']) ?></td>
                                            <td style="text-align:left;padding-left:5px;">
                                                <?php if ($is_active): ?>
                                                    <span class="status-badge status-aktif"><i class="fa-solid fa-circle-check"></i>
                                                        Aktif</span>
                                                <?php else: ?>
                                                    <span class="status-badge status-nonaktif"><i class="fa-solid fa-circle-xmark"></i>
                                                        Nonaktif</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <!-- Mengubah class menjadi 'action-group' agar sejajar rapi sesuai CSS -->
                                                <div class="action-group">
                                                    <!-- 1. Tombol View / Lihat Detail (Mata) -->
                                                    <button onclick="openDetail(
            '<?= htmlspecialchars($row['ID_Karyawan']) ?>',
            '<?= htmlspecialchars($row['Nama_Karyawan']) ?>',
            '<?= htmlspecialchars($row['Username'] ?? '') ?>',
            '<?= htmlspecialchars($row['Kata_Sandi'] ?? '') ?>',
            '<?= htmlspecialchars($row['Email'] ?? '') ?>',
            '<?= $row['Jenis_Kelamin'] ?>',
            '<?= htmlspecialchars($row['Tempat_Lahir'] ?? '') ?>',
            '<?php $tgl = $row['Tanggal_Lahir'] ?? '';
            if ($tgl && is_object($tgl))
                echo $tgl->format('Y-m-d');
            elseif ($tgl)
                echo htmlspecialchars($tgl); ?>',
            '<?= htmlspecialchars($row['Jabatan']) ?>',
            '<?= htmlspecialchars($row['No_Telepon']) ?>',
            '<?= $status_label ?>',
            '<?= addslashes($row['Alamat'] ?? '') ?>'
        )" class="btn-action btn-view" title="Lihat Detail"><i class="fa-solid fa-eye"></i></button>

                                                    <!-- 2. Tombol Edit (Pena) -->
                                                    <a href="?page=<?= $page ?>&edit_id=<?= $row['ID_Karyawan'] ?>"
                                                        class="btn-action btn-edit" title="Edit Data"><i
                                                            class="fa-solid fa-pen-to-square"></i></a>

                                                    <!-- 3. Toggle Switch Status (Hijau/Abu-abu) -->
                                                    <div class="toggle-switch" title="Klik untuk ubah status"
                                                        onclick="handleToggleClick('<?= htmlspecialchars($row['ID_Karyawan']) ?>', '<?= htmlspecialchars($row['Nama_Karyawan']) ?>', <?= $is_active ? 'true' : 'false' ?>, this)"
                                                        style="cursor: pointer;">
                                                        <input type="checkbox" <?= $is_active ? 'checked' : '' ?>
                                                            onclick="return false;" readonly style="pointer-events: none;">
                                                        <span class="toggle-slider"></span>
                                                    </div>

                                                    <!-- 4. Tombol Delete (Tempat Sampah) -->
                                                    <button type="button" class="btn-action btn-delete"
                                                        onclick="confirmDelete('<?= $row['ID_Karyawan'] ?>', '<?= htmlspecialchars($row['Nama_Karyawan']) ?>')"
                                                        title="Hapus"><i class="fa-solid fa-trash-can"></i></button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; endif; ?>
                                <?php if (!$has_data): ?>
                                    <tr>
                                        <td colspan="5">
                                            <div class="empty-state"><i class="fa-solid fa-user-tie"></i>
                                                <p>Belum ada data karyawan terdaftar</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($total_pages > 1): ?>
                <div class="pagination-wrap">
                    <div class="pagination-info">Menampilkan <strong><?= (($page - 1) * $limit) + 1 ?></strong> -
                        <strong><?= min($page * $limit, $total_rows) ?></strong> dari <strong><?= $total_rows ?></strong>
                        data</div>
                    <div class="pagination-nav">
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>"
                            class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>"><i class="fa-solid fa-angles-left"></i></a>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>"
                            class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>"><i class="fa-solid fa-angle-left"></i></a>
                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        if ($end_page - $start_page < 4 && $total_pages >= 5) {
                            if ($start_page == 1)
                                $end_page = min(5, $total_pages);
                            else
                                $start_page = max(1, $total_pages - 4);
                        }
                        if ($start_page > 1):
                            ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>" class="page-btn">1</a>
                            <?php if ($start_page > 2): ?><span class="page-ellipsis">...</span><?php endif; ?>
                        <?php endif; ?>
                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"
                                class="page-btn <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                        <?php endfor; ?>
                        <?php if ($end_page < $total_pages): ?>
                            <?php if ($end_page < $total_pages - 1): ?><span class="page-ellipsis">...</span><?php endif; ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $total_pages])) ?>"
                                class="page-btn"><?= $total_pages ?></a>
                        <?php endif; ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>"
                            class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>"><i
                                class="fa-solid fa-angle-right"></i></a>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $total_pages])) ?>"
                            class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>"><i
                                class="fa-solid fa-angles-right"></i></a>
                    </div>
                </div>
            <?php else: ?>
                <div class="pagination-wrap">
                    <div class="pagination-info">Menampilkan <strong>1</strong> - <strong><?= $total_rows ?></strong> dari
                        <strong><?= $total_rows ?></strong> data</div>
                </div>
            <?php endif; ?>
        </div>
    </main>
    <!-- JAVASCRIPT -->
    <script>
        function openModal() { document.getElementById('modal').classList.remove('hidden'); }
        function closeModal() { window.location.href = 'karyawan.php'; }

        // ============================================
        // DELETE CONFIRMATION
        // ============================================
        function confirmDelete(id, nama) {
            Swal.fire({
                title: 'Hapus Karyawan?',
                html: 'Anda akan menghapus karyawan <strong style="color:var(--orange);">' + nama + '</strong><br><span style="font-size:12px;color:var(--muted);">Data akan dihapus secara soft-delete</span>',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#EF4444',
                cancelButtonColor: '#6B7280',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal',
                reverseButtons: true,
                allowOutsideClick: false
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Memproses...',
                        text: 'Menghapus karyawan',
                        allowOutsideClick: false,
                        didOpen: () => { Swal.showLoading(); }
                    });
                    setTimeout(() => {
                        window.location.href = '?page=<?= $page ?>&delete_id=' + id;
                    }, 600);
                }
            });
        }

        // ============================================
        // TOGGLE STATUS
        // ============================================
        function handleToggleClick(id, nama, isCurrentlyActive, wrapper) {
            const actionText = isCurrentlyActive ? 'nonaktifkan' : 'aktifkan';
            const iconType = isCurrentlyActive ? 'warning' : 'question';

            Swal.fire({
                title: 'Konfirmasi Perubahan Status',
                text: 'Apakah Anda yakin ingin ' + actionText + ' karyawan ' + nama + '?',
                icon: iconType,
                showCancelButton: true,
                confirmButtonColor: '#FF4500',
                cancelButtonColor: '#6B7280',
                confirmButtonText: 'Ya, ' + actionText + '!',
                cancelButtonText: 'Batal',
                reverseButtons: true,
                allowOutsideClick: false
            }).then((result) => {
                if (!result.isConfirmed) {
                    return;
                }

                // Show loading
                Swal.fire({
                    title: 'Memproses...',
                    text: 'Mengubah status karyawan',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
                });

                fetch('karyawan.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'toggle_status=1&id_kry=' + encodeURIComponent(id)
                })
                    .then(r => {
                        if (!r.ok) throw new Error('HTTP ' + r.status);
                        return r.json();
                    })
                    .then(data => {
                        if (!data.success) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Gagal!',
                                text: data.message || 'Gagal mengubah status',
                                timer: 3000,
                                showConfirmButton: false,
                                toast: true,
                                position: 'top-end',
                                timerProgressBar: true
                            });
                            return;
                        }

                        const isNowActive = data.status === 1;
                        const statusLabel = isNowActive ? 'Aktif' : 'Nonaktif';
                        const iconColor = isNowActive ? '#10B981' : '#EF4444';

                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil!',
                            html: '<div style="display:flex;align-items:center;justify-content:center;gap:10px;margin-top:10px;">' +
                                '<i class="fa-solid ' + (isNowActive ? 'fa-circle-check' : 'fa-circle-xmark') + '" style="color:' + iconColor + ';font-size:22px;"></i>' +
                                '<div style="text-align:left;">' +
                                '<div>Status <strong style="color:#FF4500;">' + nama + '</strong> diubah</div>' +
                                '<div style="font-size:18px;font-weight:800;color:' + iconColor + ';">' + statusLabel + '</div>' +
                                '</div></div>',
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            location.reload();
                        });
                    })
                    .catch(err => {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: 'Terjadi kesalahan koneksi: ' + err.message,
                            timer: 3000,
                            showConfirmButton: false,
                            toast: true,
                            position: 'top-end',
                            timerProgressBar: true
                        });
                    });
            });
        }

        function openDetail(id, nama, username, password, email, jk, tempatLahir, tanggalLahir, jabatan, telp, status, alamat) {
            const mapJK = { '1': 'LAKI-LAKI', '0': 'PEREMPUAN' };
            const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

            document.getElementById('dId').textContent = id;
            document.getElementById('dNameHeader').textContent = nama;
            document.getElementById('dNama').textContent = nama;
            document.getElementById('dUsername').textContent = username || '-';
            document.getElementById('dPassword').textContent = password ? '••••••••' : '-';
            document.getElementById('dEmail').textContent = email || '-';
            document.getElementById('dTempatLahir').textContent = tempatLahir || '-';
            document.getElementById('dAlamat').textContent = alamat || '-';
            document.getElementById('dTelp').textContent = telp || '-';

            if (tanggalLahir) {
                const d = new Date(tanggalLahir);
                if (!isNaN(d)) document.getElementById('dTanggalLahir').textContent = d.getDate() + ' ' + months[d.getMonth()] + ' ' + d.getFullYear();
                else document.getElementById('dTanggalLahir').textContent = tanggalLahir;
            } else { document.getElementById('dTanggalLahir').textContent = '-'; }

            const jkColor = jk == '1' ? '#3B82F6' : '#EC4899';
            const jkBg = jk == '1' ? '#EFF6FF' : '#FDF2F8';
            document.getElementById('dJK').innerHTML = `<span class="status-pill" style="background: ${jkBg}; color: ${jkColor}; padding: 6px 12px; border-radius: 8px; font-size: 11px; font-weight: 800;">${mapJK[jk] || '-'}</span>`;
            document.getElementById('dJabatan').innerHTML = `<span class="jabatan-badge">${jabatan}</span>`;

            const isAktif = (status === 'Aktif');
            document.getElementById('dStatus').innerHTML = `
        <span class="status-pill ${isAktif ? 'sp-ready' : 'sp-maint'}" style="background: ${isAktif ? 'var(--green-lt)' : 'var(--red-lt)'}; color: ${isAktif ? 'var(--green)' : 'var(--red)'}; padding: 6px 12px; border-radius: 8px; font-size: 11px; font-weight: 800; display: inline-flex; align-items: center; gap: 5px;">
            <i class="fa-solid ${isAktif ? 'fa-circle-check' : 'fa-circle-xmark'}"></i> ${status.toUpperCase()}
        </span>`;

            document.getElementById('detailModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeDetail() {
            document.getElementById('detailModal').style.display = 'none';
            document.body.style.overflow = '';
        }

        function searchTable() {
            var input = document.getElementById('src').value.toUpperCase();
            var rows = document.getElementById('tbl').getElementsByTagName('tr');
            for (var i = 1; i < rows.length; i++) {
                var tds = rows[i].getElementsByTagName('td');
                if (tds.length < 5) continue;
                var match = false;
                for (var j = 1; j <= 3; j++) {
                    if (tds[j] && tds[j].textContent.toUpperCase().indexOf(input) > -1) { match = true; break; }
                }
                rows[i].style.display = match ? '' : 'none';
            }
        }

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

        const btnFilterToggle = document.getElementById('btnFilterToggle');
        const filterCard = document.getElementById('filterCard');
        if (btnFilterToggle && filterCard) {
            btnFilterToggle.addEventListener('click', function (e) {
                e.stopPropagation();
                this.classList.toggle('active');
                filterCard.classList.toggle('open');
            });
            filterCard.addEventListener('click', function (e) { e.stopPropagation(); });
            document.addEventListener('click', function () {
                btnFilterToggle.classList.remove('active');
                filterCard.classList.remove('open');
            });
        }

        function validateDate(input) {
            const selected = new Date(input.value);
            const today = new Date(); today.setHours(0, 0, 0, 0);
            const valMsg = document.getElementById('val-tanggal_lahir');
            if (!input.value) { if (valMsg) { valMsg.classList.add('show'); input.classList.add('error'); } return false; }
            if (selected > today) { if (valMsg) { valMsg.textContent = '⚠ Tanggal lahir tidak boleh di masa depan!'; valMsg.classList.add('show'); input.classList.add('error'); } return false; }
            const age = today.getFullYear() - selected.getFullYear();
            if (age < 17) { if (valMsg) { valMsg.textContent = '⚠ Karyawan harus berusia minimal 17 tahun!'; valMsg.classList.add('show'); input.classList.add('error'); } return false; }
            if (age > 60) { if (valMsg) { valMsg.textContent = '⚠ Usia maksimal 60 tahun!'; valMsg.classList.add('show'); input.classList.add('error'); } return false; }
            if (valMsg) { valMsg.classList.remove('show'); input.classList.remove('error'); }
            return true;
        }

        // ============================================
        // VALIDASI FORM - REAL TIME & SUBMIT
        // ============================================
        function validateField(fieldId, valId, rules) {
            const field = document.getElementById(fieldId);
            if (!field) return true;
            const valMsg = document.getElementById(valId);
            const value = field.value.trim();

            field.classList.remove('error');
            if (valMsg) valMsg.classList.remove('show');

            // Required check
            if (rules.required && value === '') {
                field.classList.add('error');
                if (valMsg) {
                    valMsg.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> ' + rules.label + ' wajib diisi';
                    valMsg.classList.add('show');
                }
                return false;
            }

            // Min length check
            if (rules.minLength && value.length < rules.minLength) {
                field.classList.add('error');
                if (valMsg) {
                    valMsg.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Minimal ' + rules.minLength + ' karakter';
                    valMsg.classList.add('show');
                }
                return false;
            }

            // Max length check
            if (rules.maxLength && value.length > rules.maxLength) {
                field.classList.add('error');
                if (valMsg) {
                    valMsg.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Maksimal ' + rules.maxLength + ' karakter';
                    valMsg.classList.add('show');
                }
                return false;
            }

            // Pattern check (hanya huruf, angka, spasi, dan tanda baca umum)
            if (rules.pattern && value !== '') {
                const regex = /^[a-zA-Z0-9\s\-_.(),&/@]+$/;
                if (!regex.test(value)) {
                    field.classList.add('error');
                    if (valMsg) {
                        valMsg.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Hanya huruf, angka, spasi, dan tanda baca umum';
                        valMsg.classList.add('show');
                    }
                    return false;
                }
            }

            // Email validation
            if (rules.email && value !== '') {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(value)) {
                    field.classList.add('error');
                    if (valMsg) {
                        valMsg.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Format email tidak valid';
                        valMsg.classList.add('show');
                    }
                    return false;
                }
            }

            return true;
        }

        function validateForm(form) {
            let valid = true;

            // Validasi ID Karyawan
            if (!validateField('id_kry', 'val-id_kry', {
                required: true,
                minLength: 3,
                maxLength: 20,
                pattern: true,
                label: 'ID Karyawan'
            })) valid = false;

            // Validasi Nama
            if (!validateField('nama', 'val-nama', {
                required: true,
                minLength: 3,
                maxLength: 100,
                pattern: true,
                label: 'Nama lengkap'
            })) valid = false;

            // Validasi Username
            if (!validateField('username', 'val-username', {
                required: true,
                minLength: 3,
                maxLength: 50,
                pattern: true,
                label: 'Username'
            })) valid = false;

            // Validasi Password
            if (!validateField('password', 'val-password', {
                required: true,
                minLength: 6,
                maxLength: 100,
                label: 'Password'
            })) valid = false;

            // Validasi Email
            if (!validateField('email', 'val-email', {
                required: true,
                email: true,
                label: 'Email'
            })) valid = false;

            // Validasi No Telepon
            if (!validateField('telp', 'val-telp', {
                required: true,
                minLength: 10,
                maxLength: 15,
                label: 'Nomor telepon'
            })) valid = false;

            // Validasi Tempat Lahir
            if (!validateField('tempat_lahir', 'val-tempat_lahir', {
                required: true,
                minLength: 2,
                maxLength: 50,
                pattern: true,
                label: 'Tempat lahir'
            })) valid = false;

            // Validasi Tanggal Lahir
            const tglLahir = document.getElementById('tanggal_lahir');
            if (tglLahir && tglLahir.value) {
                if (!validateDate(tglLahir)) valid = false;
            }

            // Validasi Alamat
            if (!validateField('alamat', 'val-alamat', {
                required: true,
                minLength: 5,
                maxLength: 200,
                label: 'Alamat'
            })) valid = false;

            // Jika tidak valid, tampilkan notifikasi error
            if (!valid) {
                showToast('error', 'Validasi Gagal', 'Mohon periksa kembali form yang ditandai merah');
            }

            return valid;
        }

        // ============================================
        // REAL-TIME VALIDATION EVENT LISTENERS
        // ============================================
        document.addEventListener('DOMContentLoaded', function () {
            // Validasi real-time Nama
            const namaField = document.getElementById('nama');
            if (namaField) {
                namaField.addEventListener('blur', function () {
                    validateField('nama', 'val-nama', {
                        required: true,
                        minLength: 3,
                        maxLength: 100,
                        pattern: true,
                        label: 'Nama lengkap'
                    });
                });
                namaField.addEventListener('input', function () {
                    if (this.classList.contains('error')) {
                        validateField('nama', 'val-nama', {
                            required: true,
                            minLength: 3,
                            maxLength: 100,
                            pattern: true,
                            label: 'Nama lengkap'
                        });
                    }
                });
            }

            // Validasi real-time Username
            const usernameField = document.getElementById('username');
            if (usernameField) {
                usernameField.addEventListener('blur', function () {
                    validateField('username', 'val-username', {
                        required: true,
                        minLength: 3,
                        maxLength: 50,
                        pattern: true,
                        label: 'Username'
                    });
                });
                usernameField.addEventListener('input', function () {
                    if (this.classList.contains('error')) {
                        validateField('username', 'val-username', {
                            required: true,
                            minLength: 3,
                            maxLength: 50,
                            pattern: true,
                            label: 'Username'
                        });
                    }
                });
            }

            // Validasi real-time Password
            const passwordField = document.getElementById('password');
            if (passwordField) {
                passwordField.addEventListener('blur', function () {
                    validateField('password', 'val-password', {
                        required: true,
                        minLength: 6,
                        maxLength: 100,
                        label: 'Password'
                    });
                });
                passwordField.addEventListener('input', function () {
                    if (this.classList.contains('error')) {
                        validateField('password', 'val-password', {
                            required: true,
                            minLength: 6,
                            maxLength: 100,
                            label: 'Password'
                        });
                    }
                });
            }

            // Validasi real-time Email
            const emailField = document.getElementById('email');
            if (emailField) {
                emailField.addEventListener('blur', function () {
                    validateField('email', 'val-email', {
                        required: true,
                        email: true,
                        label: 'Email'
                    });
                });
                emailField.addEventListener('input', function () {
                    if (this.classList.contains('error')) {
                        validateField('email', 'val-email', {
                            required: true,
                            email: true,
                            label: 'Email'
                        });
                    }
                });
            }

            // Validasi real-time No Telepon
            const telpField = document.getElementById('telp');
            if (telpField) {
                telpField.addEventListener('blur', function () {
                    validateField('telp', 'val-telp', {
                        required: true,
                        minLength: 10,
                        maxLength: 15,
                        label: 'Nomor telepon'
                    });
                });
                telpField.addEventListener('input', function () {
                    if (this.classList.contains('error')) {
                        validateField('telp', 'val-telp', {
                            required: true,
                            minLength: 10,
                            maxLength: 15,
                            label: 'Nomor telepon'
                        });
                    }
                });
            }

            // Validasi real-time Tempat Lahir
            const tempatLahirField = document.getElementById('tempat_lahir');
            if (tempatLahirField) {
                tempatLahirField.addEventListener('blur', function () {
                    validateField('tempat_lahir', 'val-tempat_lahir', {
                        required: true,
                        minLength: 2,
                        maxLength: 50,
                        pattern: true,
                        label: 'Tempat lahir'
                    });
                });
                tempatLahirField.addEventListener('input', function () {
                    if (this.classList.contains('error')) {
                        validateField('tempat_lahir', 'val-tempat_lahir', {
                            required: true,
                            minLength: 2,
                            maxLength: 50,
                            pattern: true,
                            label: 'Tempat lahir'
                        });
                    }
                });
            }

            // Validasi real-time Alamat
            const alamatField = document.getElementById('alamat');
            if (alamatField) {
                alamatField.addEventListener('blur', function () {
                    validateField('alamat', 'val-alamat', {
                        required: true,
                        minLength: 5,
                        maxLength: 200,
                        label: 'Alamat'
                    });
                });
                alamatField.addEventListener('input', function () {
                    if (this.classList.contains('error')) {
                        validateField('alamat', 'val-alamat', {
                            required: true,
                            minLength: 5,
                            maxLength: 200,
                            label: 'Alamat'
                        });
                    }
                });
            }

            // Validasi real-time ID Karyawan
            const idKryField = document.getElementById('id_kry');
            if (idKryField) {
                idKryField.addEventListener('blur', function () {
                    validateField('id_kry', 'val-id_kry', {
                        required: true,
                        minLength: 3,
                        maxLength: 20,
                        pattern: true,
                        label: 'ID Karyawan'
                    });
                });
                idKryField.addEventListener('input', function () {
                    if (this.classList.contains('error')) {
                        validateField('id_kry', 'val-id_kry', {
                            required: true,
                            minLength: 3,
                            maxLength: 20,
                            pattern: true,
                            label: 'ID Karyawan'
                        });
                    }
                });
            }

            // Validasi real-time Tanggal Lahir
            const tglLahirField = document.getElementById('tanggal_lahir');
            if (tglLahirField) {
                tglLahirField.addEventListener('change', function () {
                    validateDate(this);
                });
            }
        });

        // ============================================
        // CLOCK / JAM
        // ============================================
        function updateClock() {
            try {
                const now = new Date();
                const h = String(now.getHours()).padStart(2, '0');
                const m = String(now.getMinutes()).padStart(2, '0');
                const s = String(now.getSeconds()).padStart(2, '0');

                const hEl = document.getElementById('clock-h');
                const mEl = document.getElementById('clock-m');
                const sEl = document.getElementById('clock-s');
                const dateEl = document.getElementById('clock-date');

                if (hEl) hEl.textContent = h;
                if (mEl) mEl.textContent = m;
                if (sEl) sEl.textContent = s;

                const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
                const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

                if (dateEl) dateEl.textContent = days[now.getDay()] + ', ' + now.getDate() + ' ' + months[now.getMonth()] + ' ' + now.getFullYear();
            } catch (e) {
                console.error('Clock error:', e);
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            updateClock();
            setInterval(updateClock, 1000);
        });


        // ============================================
        // NOTIFIKASI TOAST
        // ============================================
        function showToast(type, title, message) {
            Swal.fire({
                icon: type,
                title: title,
                text: message,
                timer: 3000,
                showConfirmButton: false,
                toast: true,
                position: 'top-end',
                timerProgressBar: true,
                showCloseButton: true,
                customClass: {
                    popup: 'colored-toast'
                }
            });
        }

        // ============================================
        // NOTIFIKASI TOAST
        // ============================================
        function showToast(type, title, message) {
            Swal.fire({
                icon: type,
                title: title,
                text: message,
                timer: 3000,
                showConfirmButton: false,
                toast: true,
                position: 'top-end',
                timerProgressBar: true,
                showCloseButton: true
            });
        }

        // ============================================
        // URL PARAMETER NOTIFICATION (Status & Msg)
        // ============================================
        document.addEventListener('DOMContentLoaded', function () {
            const urlParams = new URLSearchParams(window.location.search);
            const status = urlParams.get('status');
            const msg = urlParams.get('msg');

            if (status && msg) {
                const isSuccess = status === 'success';
                Swal.fire({
                    icon: isSuccess ? 'success' : 'error',
                    title: isSuccess ? 'Berhasil!' : 'Gagal!',
                    text: msg,
                    timer: 3000,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end',
                    timerProgressBar: true,
                    showCloseButton: true
                });
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        });

        window.onclick = function (e) { if (e.target == document.getElementById('modal')) closeModal(); };
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { closeDetail(); if (btnFilterToggle) btnFilterToggle.classList.remove('active'); if (filterCard) filterCard.classList.remove('open'); }
        });
    </script>
</body>

</html>