<?php
session_start();
include '../includes/config.php';

if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'karyawan' && $_SESSION['role'] !== 'pemilik')) {
    echo "<script>alert('Akses Ditolak!'); window.location='../dashboard/dashboard.php';</script>";
    exit();
}
$role = $_SESSION['role'];
$nama = $_SESSION['nama'] ?? 'USER';
$map_jk = [0 => 'Laki-laki', 1 => 'Perempuan'];

// FIX: Ambil foto profil dari database dengan kolom yang benar
$profile_photo = '';
$id_karyawan_session = $_SESSION['id_karyawan'] ?? $_SESSION['id_akun'] ?? '';

if (!empty($id_karyawan_session)) {
    $stmt_photo = sqlsrv_query($conn, "SELECT Photo_Profile FROM Karyawan WHERE ID_Karyawan = ?", array($id_karyawan_session));
    if ($stmt_photo !== false) {
        $row_photo = sqlsrv_fetch_array($stmt_photo, SQLSRV_FETCH_ASSOC);
        if ($row_photo && !empty($row_photo['Photo_Profile'])) {
            $profile_photo = $row_photo['Photo_Profile'];
            $_SESSION['Photo_Profile'] = $profile_photo;
        }
    }
}

// Fallback ke session jika query gagal
if (empty($profile_photo)) {
    $profile_photo = $_SESSION['Photo_Profile'] ?? '';
}

// Cek file exists dan sesuaikan path (customer.php ada di folder master/)
$sidebar_photo = '';
if (!empty($profile_photo)) {
    if (strpos($profile_photo, '../') === 0) {
        $sidebar_photo = $profile_photo;
    } elseif (strpos($profile_photo, 'uploads/') === 0) {
        $sidebar_photo = '../' . $profile_photo;
    } else {
        $sidebar_photo = '../uploads/profiles/' . $profile_photo;
    }
    // Cek file exists, jika tidak ada kosongkan
    if (!file_exists($sidebar_photo)) {
        $sidebar_photo = '';
    }
}

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

// --- TOGGLE STATUS ---
if (isset($_GET['toggle_id'])) {
    $toggle_id = $_GET['toggle_id'];
    $stmt_check = safe_sqlsrv_query($conn, "SELECT Status, Nama_Customer FROM Customer WHERE ID_Customer = ? AND Is_Deleted = 0", array($toggle_id), false);
    if ($stmt_check !== false) {
        $row_check = safe_sqlsrv_fetch_array($stmt_check, SQLSRV_FETCH_ASSOC);
        if ($row_check) {
            $current_status = intval($row_check['Status'] ?? 1);
            $new_status = $current_status === 1 ? 0 : 1;
            $modified_by = $_SESSION['nama'] ?? 'SYSTEM';

            $stmt_toggle = safe_sqlsrv_query($conn, 
                "UPDATE Customer SET Status = ?, Modified_By = ?, Modified_Date = GETDATE() WHERE ID_Customer = ? AND Is_Deleted = 0", 
                array($new_status, $modified_by, $toggle_id), false
            );

            if ($stmt_toggle) {
                $status_text = $new_status === 1 ? 'Aktif' : 'Nonaktif';
                header("Location: customer.php?page=1&status=success&msg=Status customer berhasil diubah menjadi " . $status_text . "!");
            } else {
                header("Location: customer.php?page=1&status=error&msg=Gagal mengubah status customer!");
            }
            exit();
        } else {
            header("Location: customer.php?page=1&status=error&msg=Customer tidak ditemukan!");
            exit();
        }
    }
}

// --- DETAIL CUSTOMER ---
$detail_data = null;
$show_detail = false;
if (isset($_GET['detail_id'])) {
    $r_detail = safe_sqlsrv_query($conn, "SELECT ID_Customer, Nama_Customer, Jenis_Kelamin, Tanggal_Lahir, Tempat_Lahir, Alamat, No_Telepon, Email, Status FROM Customer WHERE ID_Customer = ? AND Is_Deleted = 0", array($_GET['detail_id']), false);
    if ($r_detail) {
        $detail_data = safe_sqlsrv_fetch_array($r_detail, SQLSRV_FETCH_ASSOC);
        $show_detail = true;
    }
}

$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'ID_Customer';
$sort_order = isset($_GET['order']) && strtoupper($_GET['order']) == 'DESC' ? 'DESC' : 'ASC';
$filter_jk = isset($_GET['jk']) ? intval($_GET['jk']) : -1;
$filter_status = isset($_GET['status_filter']) ? $_GET['status_filter'] : 'all';

$allowed_sort = ['ID_Customer', 'Nama_Customer', 'Jenis_Kelamin', 'Alamat', 'No_Telepon', 'Email', 'Created_Date'];
if (!in_array($sort_by, $allowed_sort)) $sort_by = 'ID_Customer';

$where_clauses = ["Is_Deleted = 0"];
$params = [];

if ($filter_status == 'aktif') {
    $where_clauses[] = "Status = 1";
} elseif ($filter_status == 'nonaktif') {
    $where_clauses[] = "Status = 0";
}

if ($filter_jk >= 0) {
    $where_clauses[] = "Jenis_Kelamin = ?";
    $params[] = $filter_jk;
}

$where_sql = implode(" AND ", $where_clauses);

// --- STAT COUNTS ---
$count_sql = "SELECT COUNT(*) as t FROM Customer WHERE " . $where_sql;
$q_total = safe_sqlsrv_query($conn, $count_sql, $params, false);
$total_cust = 0;
if ($q_total !== false) {
    $row_total = safe_sqlsrv_fetch_array($q_total, SQLSRV_FETCH_ASSOC);
    $total_cust = $row_total['t'] ?? 0;
}

// Total Aktif
$q_aktif = safe_sqlsrv_query($conn, "SELECT COUNT(*) as t FROM Customer WHERE Status = 1 AND Is_Deleted = 0", [], false);
$total_aktif = 0;
if ($q_aktif !== false) {
    $row_aktif = safe_sqlsrv_fetch_array($q_aktif, SQLSRV_FETCH_ASSOC);
    $total_aktif = $row_aktif['t'] ?? 0;
}

// Total Nonaktif
$q_nonaktif = safe_sqlsrv_query($conn, "SELECT COUNT(*) as t FROM Customer WHERE Status = 0 AND Is_Deleted = 0", [], false);
$total_nonaktif = 0;
if ($q_nonaktif !== false) {
    $row_nonaktif = safe_sqlsrv_fetch_array($q_nonaktif, SQLSRV_FETCH_ASSOC);
    $total_nonaktif = $row_nonaktif['t'] ?? 0;
}

$limit = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$total_pages = max(1, ceil($total_cust / $limit));
$page = min($page, $total_pages);
$offset = ($page - 1) * $limit;

$q_laki = safe_sqlsrv_query($conn, "SELECT COUNT(*) as t FROM Customer WHERE Jenis_Kelamin = 0 AND Is_Deleted = 0", [], false);
$total_laki = 0;
if ($q_laki !== false) {
    $row_laki = safe_sqlsrv_fetch_array($q_laki, SQLSRV_FETCH_ASSOC);
    $total_laki = $row_laki['t'] ?? 0;
}

$q_perempuan = safe_sqlsrv_query($conn, "SELECT COUNT(*) as t FROM Customer WHERE Jenis_Kelamin = 1 AND Is_Deleted = 0", [], false);
$total_perempuan = 0;
if ($q_perempuan !== false) {
    $row_perempuan = safe_sqlsrv_fetch_array($q_perempuan, SQLSRV_FETCH_ASSOC);
    $total_perempuan = $row_perempuan['t'] ?? 0;
}

$q_pending = sqlsrv_query($conn, "SELECT COUNT(*) as t FROM Booking WHERE Status = 1");
$total_pending = 0;
if ($q_pending !== false) {
    $row_pending = sqlsrv_fetch_array($q_pending, SQLSRV_FETCH_ASSOC);
    $total_pending = $row_pending['t'] ?? 0;
}

$query_sql = "SELECT ID_Customer, Nama_Customer, Jenis_Kelamin, Tanggal_Lahir, Tempat_Lahir, Alamat, No_Telepon, Email, Status FROM Customer WHERE " . $where_sql . " ORDER BY " . $sort_by . " " . $sort_order . " OFFSET " . intval($offset) . " ROWS FETCH NEXT " . intval($limit) . " ROWS ONLY";
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

function format_tgl_display($date) {
    if (empty($date)) return '-';
    if (is_object($date) && method_exists($date, 'format')) {
        $d = $date->format('Y-m-d');
        $bulan = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
        $parts = explode('-', $d);
        return (int)$parts[2] . ' ' . $bulan[(int)$parts[1]-1] . ' ' . $parts[0];
    }
    return $date;
}

function jk_label($jk) {
    return $jk == 0 ? 'Laki-laki' : ($jk == 1 ? 'Perempuan' : '-');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Kelola Customer | HoopBall</title>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800;900&family=Barlow:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
:root {
     --orange: #FF4500;
     --orange-lt: rgba(255,69,0,.10);
     --orange-dk: #E03E00;
     --green: #10B981;
     --green-lt: rgba(16,185,129,.10);
     --blue: #3B82F6;
     --blue-lt: rgba(59,130,246,.10);
     --pink: #EC4899;
     --pink-lt: rgba(236,72,153,.10);
     --red: #EF4444;
     --red-lt: rgba(239,68,68,.10);
     --purple: #8B5CF6;
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
}
 *, *::before, *::after {
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
     border-right: 1px solid rgba(255,255,255,.04);
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
     position: relative;
     transition: transform 0.3s cubic-bezier(0.34,1.56,0.64,1);
}
 .sb-brand:hover {
     transform: scale(1.02);
}
 .sb-brand::after {
     content: '';
     position: absolute;
     bottom: -8px;
     left: 0;
     width: 0;
     height: 2px;
     background: linear-gradient(90deg, var(--orange), transparent);
     transition: width 0.4s cubic-bezier(0.16,1,0.3,1);
}
 .sb-brand:hover::after {
     width: 100%;
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
     box-shadow: 0 4px 14px rgba(255,69,0,.4);
     transition: all 0.3s cubic-bezier(0.34,1.56,0.64,1);
}
 .sb-brand:hover .sb-icon {
     transform: rotate(5deg) scale(1.1);
     box-shadow: 0 6px 20px rgba(255,69,0,.5);
}
 .sb-brand-name {
     font-family: 'Barlow Condensed', sans-serif;
     font-size: 20px;
     font-weight: 900;
     color: #fff;
     letter-spacing: 1px;
     transition: color 0.3s ease;
}
 .sb-brand-sub {
     font-size: 9px;
     color: #4B5563;
     font-weight: 700;
     text-transform: uppercase;
     transition: color 0.3s ease;
}
 .sb-brand:hover .sb-brand-sub {
     color: var(--orange);
}
 .sb-section-label {
     font-size: 10px;
     font-weight: 800;
     text-transform: uppercase;
     color: #374151;
     letter-spacing: .8px;
     padding: 0 10px;
     margin: 22px 0 8px;
     position: relative;
}
 .sb-section-label::after {
     content: '';
     position: absolute;
     bottom: -4px;
     left: 10px;
     width: 20px;
     height: 2px;
     background: var(--orange);
     border-radius: 1px;
     transition: width 0.3s ease;
}
 .sb-section-label:hover::after {
     width: 40px;
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
     transition: all 0.35s cubic-bezier(0.16,1,0.3,1);
     position: relative;
     overflow: hidden;
}
 .sb-link::before {
     content: '';
     position: absolute;
     left: 0;
     top: 0;
     width: 0;
     height: 100%;
     background: linear-gradient(90deg, rgba(255,69,0,0.15), rgba(255,69,0,0.05));
     border-radius: 10px;
     transition: width 0.35s cubic-bezier(0.16,1,0.3,1);
     z-index: 0;
}
 .sb-link:hover::before {
     width: 100%;
}
 .sb-link .sb-icon-wrap {
     width: 32px;
     height: 32px;
     border-radius: 8px;
     display: flex;
     align-items: center;
     justify-content: center;
     font-size: 13px;
     transition: all 0.35s cubic-bezier(0.34,1.56,0.64,1);
     flex-shrink: 0;
     background: rgba(255,255,255,.04);
     position: relative;
     z-index: 1;
}
 .sb-link:hover {
     color: #E5E7EB;
     transform: translateX(4px);
}
 .sb-link:hover .sb-icon-wrap {
     background: rgba(255,255,255,.12);
     transform: scale(1.15) rotate(5deg);
}
 .sb-link.active {
     color: #fff;
     background: var(--orange-lt);
}
 .sb-link.active::before {
     width: 100%;
     background: linear-gradient(90deg, rgba(255,69,0,0.2), rgba(255,69,0,0.08));
}
 .sb-link.active .sb-icon-wrap {
     background: var(--orange);
     color: #fff;
     transform: scale(1.1);
     box-shadow: 0 4px 12px rgba(255,69,0,.3);
}
/* Active indicator pill */
 .sb-link.active::after {
     content: '';
     position: absolute;
     right: -18px;
     top: 50%;
     transform: translateY(-50%);
     width: 3px;
     height: 20px;
     background: var(--orange);
     border-radius: 3px 0 0 3px;
     transition: all 0.3s cubic-bezier(0.16,1,0.3,1);
}
 .sb-bottom {
     margin-top: auto;
     padding-top: 20px;
}
 .sb-user {
     display: flex;
     align-items: center;
     gap: 10px;
     background: rgba(255,255,255,.04);
     border-radius: 12px;
     padding: 12px;
     border: 1px solid rgba(255,255,255,.06);
     transition: all 0.3s cubic-bezier(0.16,1,0.3,1);
     cursor: pointer;
}
 .sb-user:hover {
     background: rgba(255,255,255,.08);
     border-color: rgba(255,69,0,.2);
     transform: translateY(-2px);
     box-shadow: 0 8px 20px rgba(0,0,0,.15);
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
     transition: all 0.3s cubic-bezier(0.34,1.56,0.64,1);
}
 .sb-user:hover .sb-avatar {
     transform: scale(1.1);
     box-shadow: 0 4px 12px rgba(255,69,0,.3);
}
 .sb-avatar img {
     width: 100%;
     height: 100%;
     object-fit: cover;
     border-radius: 50%;
     transition: transform 0.3s ease;
}
 .sb-user:hover .sb-avatar img {
     transform: scale(1.1);
}
 .sb-user-name {
     font-size: 13px;
     font-weight: 800;
     color: #E5E7EB;
     line-height: 1.1;
     transition: color 0.3s ease;
}
 .sb-user:hover .sb-user-name {
     color: #fff;
}
 .sb-user-role {
     font-size: 10px;
     color: var(--orange);
     font-weight: 700;
     text-transform: uppercase;
     transition: all 0.3s ease;
}
 .sb-user:hover .sb-user-role {
     letter-spacing: 1px;
}
 .sb-logout {
     margin-left: auto;
     color: #4B5563;
     font-size: 13px;
     transition: all 0.3s cubic-bezier(0.34,1.56,0.64,1);
     cursor: pointer;
     text-decoration: none;
     width: 32px;
     height: 32px;
     display: flex;
     align-items: center;
     justify-content: center;
     border-radius: 8px;
     position: relative;
     overflow: hidden;
}
 .sb-logout::before {
     content: '';
     position: absolute;
     inset: 0;
     background: var(--red-lt);
     border-radius: 8px;
     transform: scale(0);
     transition: transform 0.3s cubic-bezier(0.34,1.56,0.64,1);
}
 .sb-logout:hover {
     color: var(--red);
}
 .sb-logout:hover::before {
     transform: scale(1);
}
 .sb-logout i {
     position: relative;
     z-index: 1;
     transition: transform 0.3s ease;
}
 .sb-logout:hover i {
     transform: translateX(2px);
}
/* Sidebar entrance animation */
 @keyframes sidebarSlideIn {
     from {
         transform: translateX(-100%);
         opacity: 0;
    }
     to {
         transform: translateX(0);
         opacity: 1;
    }
}
 .sidebar {
     animation: sidebarSlideIn 0.6s cubic-bezier(0.16,1,0.3,1) forwards;
}
/* Staggered menu item entrance */
 @keyframes menuItemFadeIn {
     from {
         opacity: 0;
         transform: translateX(-20px);
    }
     to {
         opacity: 1;
         transform: translateX(0);
    }
}
 .sb-link {
     animation: menuItemFadeIn 0.5s cubic-bezier(0.16,1,0.3,1) forwards;
     opacity: 0;
}
 .sb-brand {
     animation: menuItemFadeIn 0.5s cubic-bezier(0.16,1,0.3,1) 0.1s forwards;
     opacity: 0;
}
 .sb-section-label {
     animation: menuItemFadeIn 0.5s cubic-bezier(0.16,1,0.3,1) forwards;
     opacity: 0;
}
 .sb-section-label:nth-of-type(1) {
     animation-delay: 0.2s;
}
 .sb-link:nth-of-type(1) {
     animation-delay: 0.25s;
}
 .sb-link:nth-of-type(2) {
     animation-delay: 0.3s;
}
 .sb-link:nth-of-type(3) {
     animation-delay: 0.35s;
}
 .sb-link:nth-of-type(4) {
     animation-delay: 0.4s;
}
 .sb-link:nth-of-type(5) {
     animation-delay: 0.45s;
}
 .sb-link:nth-of-type(6) {
     animation-delay: 0.5s;
}
 .sb-link:nth-of-type(7) {
     animation-delay: 0.55s;
}
 .sb-link:nth-of-type(8) {
     animation-delay: 0.6s;
}
 .sb-section-label:nth-of-type(2) {
     animation-delay: 0.65s;
}
 .sb-link:nth-of-type(9) {
     animation-delay: 0.7s;
}
 .sb-link:nth-of-type(10) {
     animation-delay: 0.75s;
}
 .sb-link:nth-of-type(11) {
     animation-delay: 0.8s;
}
 .sb-link:nth-of-type(12) {
     animation-delay: 0.85s;
}
 .sb-section-label:nth-of-type(3) {
     animation-delay: 0.9s;
}
 .sb-link:nth-of-type(13) {
     animation-delay: 0.95s;
}
 .sb-section-label:nth-of-type(3) + nav .sb-link:nth-of-type(1) {
     animation-delay: 0.95s;
}
 .sb-bottom {
     animation: menuItemFadeIn 0.5s cubic-bezier(0.16,1,0.3,1) 1s forwards;
     opacity: 0;
}
 .main {
     margin-left: var(--sidebar-w);
     flex: 1;
     display: flex;
     flex-direction: column;
     min-height: 100vh;
}
 .content {
     padding: 32px 40px;
     flex: 1;
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
     box-shadow: 0 1px 0 rgba(0,0,0,.04);
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
 .topbar-btn, .topbar-user {
     background-color: #FFFFFF !important;
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
     box-shadow: 0 15px 40px rgba(0,0,0,.12);
     overflow: hidden;
     padding: 8px 0;
     z-index: 999;
}
 .dropdown-wrap:hover .dropdown-menu {
     display: block;
}
 .dropdown-wrap.active .dropdown-menu {
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
     0%, 100% {
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
 .page-header {
     display: flex;
     align-items: flex-end;
     justify-content: space-between;
     margin-bottom: 24px;
     flex-wrap: wrap;
     gap: 16px;
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
 .chip-total {
     background: var(--bg);
     color: #374151;
     border: 1px solid var(--border);
}
 .chip-green {
     background: var(--green-lt);
     color: var(--green);
}
 .chip-blue {
     background: var(--blue-lt);
     color: var(--blue);
}
 .chip-red {
     background: var(--red-lt);
     color: var(--red);
}
 .chip-pink {
     background: var(--pink-lt);
     color: var(--pink);
}
 .chip-val {
     font-family: 'Barlow Condensed';
     font-size: 20px;
     font-weight: 900;
}
 .action-bar {
     display: flex;
     align-items: center;
     justify-content: space-between;
     margin-bottom: 20px;
     flex-wrap: wrap;
     gap: 12px;
}
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
 .card {
     background: var(--card-bg);
     border-radius: 16px;
     border: 1px solid var(--border);
     overflow: hidden;
     transition: all .2s ease;
     background-color: #FFFFFF !important;
}
 .main, .content {
     background-color: #F3F4F6 !important;
}
 .card:hover {
     box-shadow: 0 8px 24px rgba(0,0,0,.06);
}
 .table-wrap {
     overflow-x: auto;
}
 .data-table {
     width: 100%;
     border-collapse: collapse;
}
 .data-table th {
     font-family: 'Barlow Condensed', sans-serif !important;
     font-size: 13px !important;
     font-weight: 900 !important;
     color: var(--muted) !important;
     text-transform: uppercase !important;
     letter-spacing: 0.8px !important;
     padding: 14px 20px;
     border-bottom: 2px solid var(--border-lt);
}
 .data-table th, .data-table td {
     padding: 16px 20px;
     vertical-align: middle;
}
/* Kolom No (Rata Tengah) */
 .data-table th:nth-child(1), .data-table td:nth-child(1) {
     text-align: center !important;
     width: 8%;
     font-size: 15px;
     font-weight: 700;
}
/* Kolom Nama Customer */
 .data-table th:nth-child(2), .data-table td:nth-child(2) {
     width: 32%;
     text-align: center !important;
}
 .cust-name {
     font-weight: 700;
     color: var(--text);
     font-size: 15px;
}
/* Kolom Email */
 .data-table th:nth-child(3), .data-table td:nth-child(3) {
     width: 30%;
     text-align: center !important;
}
 .cust-email {
     font-family: 'Barlow', sans-serif;
     font-weight: 700;
     font-size: 15px;
     color: var(--text);
}
/* Kolom Status (Tengah) */
 .data-table th:nth-child(4), .data-table td:nth-child(4) {
     width: 18%;
     text-align: center !important;
}
 .data-table td:nth-child(4) {
     font-size: 0 !important;
}
 .data-table td:nth-child(4) .status-pill {
     position: relative;
     display: inline-flex !important;
     font-size: 12px !important;
     margin: 0 !important;
}
/* Kolom Aksi (Rata Kiri) */
 .data-table th:nth-child(5), .data-table td:nth-child(5) {
     width: 20%;
     text-align: center !important;
}
/* STATUS PILL */
 .status-pill {
     display: inline-flex;
     align-items: center;
     gap: 6px;
     padding: 7px 16px;
     border-radius: 20px;
     font-size: 12px;
     font-weight: 800;
     text-transform: uppercase;
     letter-spacing: .3px;
}
 .sp-active {
     background: var(--green-lt);
     color: var(--green);
}
 .sp-inactive {
     background: var(--red-lt);
     color: var(--red);
}
 .sp-dot {
     width: 7px;
     height: 7px;
     border-radius: 50%;
     display: inline-block;
}
 .sp-active .sp-dot {
     background: var(--green);
}
 .sp-inactive .sp-dot {
     background: var(--red);
}
/* STATUS BADGE (LEGACY - UNTUK MODAL DETAIL) */
 .status-badge {
     display: inline-flex;
     align-items: center;
     gap: 6px;
     padding: 6px 14px;
     border-radius: 20px;
     font-size: 11px;
     font-weight: 800;
     text-transform: uppercase;
     letter-spacing: .3px;
}
 .status-badge::before {
     content: '';
     width: 7px;
     height: 7px;
     border-radius: 50%;
     display: inline-block;
}
 .status-active {
     background: var(--green-lt);
     color: var(--green);
}
 .status-active::before {
     background: var(--green);
}
 .status-inactive {
     background: var(--red-lt);
     color: var(--red);
}
 .status-inactive::before {
     background: var(--red);
}
/* ACTIONS */
 .actions {
     display: flex;
     gap: 12px;
     justify-content: flex-start;
     align-items: center;
}
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
     cursor: pointer;
     background: none;
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
     box-shadow: 0 6px 20px rgba(59,130,246,.35);
}
/* TOGGLE SWITCH - DIPERBAIKI */
 .toggle-switch {
     position: relative;
     display: inline-flex;
     align-items: center;
     width: 44px;
     height: 24px;
     cursor: pointer;
     margin: 0;
     flex-shrink: 0;
}
 .toggle-switch input {
     opacity: 0;
     width: 0;
     height: 0;
     position: absolute;
}
 .toggle-slider {
     position: absolute;
     cursor: pointer;
     top: 0;
     left: 0;
     right: 0;
     bottom: 0;
     background-color: var(--red);
     transition: .3s;
     border-radius: 24px;
}
 .toggle-slider::before {
     position: absolute;
     content: "";
     height: 18px;
     width: 18px;
     left: 3px;
     bottom: 3px;
     background-color: white;
     transition: .3s;
     border-radius: 50%;
     box-shadow: 0 2px 4px rgba(0,0,0,.2);
}
 .toggle-switch input:checked + .toggle-slider {
     background-color: var(--green);
}
 .toggle-switch input:checked + .toggle-slider::before {
     transform: translateX(20px);
}
 .toggle-switch:hover .toggle-slider {
     opacity: .9;
}
/* ZEBRA STRIPING & HOVER */
 .data-table tbody tr:nth-child(odd) {
     background-color: #FFF7ED;
}
 .data-table tbody tr:nth-child(even) {
     background-color: #FFFFFF;
}
 .data-table tbody tr:hover td {
     background-color: #FFEDD5 !important;
}
 .data-table tbody tr:nth-child(odd):hover {
     background-color: #FFEDD5;
}
 .data-table tbody tr:nth-child(even):hover {
     background-color: #FFEDD5;
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
     box-shadow: 0 4px 12px rgba(255,69,0,.3);
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
     padding: 50px 20px;
     color: var(--muted);
}
 .empty-state i {
     font-size: 48px;
     margin-bottom: 16px;
     opacity: .3;
     display: block;
}
 .empty-state div {
     font-size: 14px;
     font-weight: 700;
}
 .modal-overlay {
     position: fixed;
     inset: 0;
     background: rgba(0,0,0,.55);
     backdrop-filter: blur(6px);
     display: none;
     align-items: center;
     justify-content: center;
     z-index: 2000;
}
 .modal-overlay.open {
     display: flex;
}
 .modal-box {
     background: #fff;
     border-radius: 20px;
     width: 420px;
     max-height: 95vh;
     overflow-y: auto;
     overflow-x: hidden;
     box-shadow: 0 25px 60px rgba(0,0,0,.2);
     position: relative;
     -ms-overflow-style: none;
     scrollbar-width: none;
}
 .modal-box::-webkit-scrollbar {
     display: none;
}
 .modal-box {
     -ms-overflow-style: none;
     scrollbar-width: none;
}
 .modal-box::-webkit-scrollbar {
     display: none;
}
 .modal-header {
     padding: 20px 24px 14px;
     border-bottom: 1px solid var(--border);
}
 .modal-subtitle {
     font-size: 10px;
     font-weight: 800;
     color: var(--orange);
     text-transform: uppercase;
     margin-bottom: 4px;
     letter-spacing: .8px;
}
 .modal-title {
     font-family: 'Barlow Condensed', sans-serif;
     font-size: 18px;
     font-weight: 900;
     color: var(--text);
}
 .modal-body {
     padding: 16px 24px 24px;
}
 .modal-close {
     position: absolute;
     top: 20px;
     right: 20px;
     width: 36px;
     height: 36px;
     border: none;
     background: var(--border-lt);
     border-radius: 10px;
     cursor: pointer;
     display: flex;
     align-items: center;
     justify-content: center;
     color: var(--muted);
     font-size: 16px;
     transition: all .2s;
}
 .modal-close:hover {
     background: var(--red-lt);
     color: var(--red);
}
 .detail-photo-card {
     text-align: center;
     margin-bottom: 16px;
     padding-bottom: 14px;
     border-bottom: 1.5px dashed var(--border);
}
 .detail-icon-wrap {
     width: 64px;
     height: 64px;
     background: var(--orange-lt);
     color: var(--orange);
     border-radius: 18px;
     display: inline-flex;
     align-items: center;
     justify-content: center;
     font-size: 26px;
     margin-bottom: 12px;
     box-shadow: 0 6px 16px rgba(255,69,0,0.15);
}
 .detail-main-name {
     font-family: 'Barlow Condensed', sans-serif;
     font-size: 22px;
     font-weight: 900;
     color: var(--text);
     text-transform: uppercase;
}
 .info-row {
     display: flex;
     align-items: flex-start;
     padding: 8px 0;
     border-bottom: 1px solid var(--border-lt);
     gap: 12px;
}
 .info-row:last-child {
     border-bottom: none;
}
 .info-key {
     display: flex;
     align-items: center;
     gap: 10px;
     font-size: 12px;
     font-weight: 800;
     color: var(--muted);
     text-transform: uppercase;
     letter-spacing: 0.5px;
     min-width: 140px;
     flex-shrink: 0;
}
 .info-key i {
     color: var(--orange);
     font-size: 14px;
     width: 18px;
     text-align: center;
     flex-shrink: 0;
}
 .info-val {
     font-size: 14px;
     font-weight: 700;
     color: var(--text);
     text-align: right;
     flex: 1;
     word-break: break-word;
}
 .info-val.id-code {
     color: var(--orange);
     font-weight: 800;
     font-family: 'Barlow Condensed';
     font-size: 16px;
}
 .btn-submit {
     width: 100%;
     background: var(--orange);
     color: #fff;
     border: none;
     padding: 14px;
     border-radius: 10px;
     font-weight: 800;
     font-size: 13px;
     cursor: pointer;
     transition: all .2s;
     text-transform: uppercase;
     letter-spacing: .5px;
     display: flex;
     align-items: center;
     justify-content: center;
     gap: 8px;
}
 .btn-submit:hover {
     background: var(--orange-dk);
     transform: translateY(-1px);
     box-shadow: 0 8px 20px rgba(255,69,0,.3);
}
 html, body {
     scrollbar-width: none;
     -ms-overflow-style: none;
}
 html::-webkit-scrollbar, body::-webkit-scrollbar {
     display: none;
}
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
 @media(max-width: 640px) {
     .modal-box {
         width: 90%;
         margin: 20px;
    }
}
 @media(max-width: 1100px) {
     .page-header {
         flex-direction: column;
         align-items: flex-start;
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
     .content {
         padding: 20px;
    }
     .topbar {
         padding: 0 20px;
    }
     .stat-chips {
         width: 100%;
    }
     .search-box {
         width: 100%;
    }
     .action-bar {
         flex-direction: column;
         align-items: stretch;
    }
     .data-table th, .data-table td {
         padding: 12px 16px;
         font-size: 12px;
    }
     .btn-action {
         padding: 6px 10px;
         font-size: 11px;
    }
     .pagination-wrap {
         flex-direction: column;
         gap: 12px;
    }
}
/* FILTER */
 .filter-wrap {
     position: relative;
     display: inline-block;
}
 .btn-filter {
     display: inline-flex;
     align-items: center;
     gap: 8px;
     background: var(--orange);
     color: #fff;
     padding: 10px 20px;
     border-radius: 10px;
     font-size: 13px;
     font-weight: 800;
     border: none;
     cursor: pointer;
     text-transform: uppercase;
     letter-spacing: .5px;
     transition: all .2s ease;
     font-family: 'Barlow', sans-serif;
}
 .btn-filter:hover {
     background: var(--orange-dk);
     transform: translateY(-1px);
     box-shadow: 0 6px 16px rgba(255,69,0,.25);
}
 .btn-filter i {
     font-size: 12px;
}
 .btn-filter .arrow-icon {
     transition: transform .2s;
}
 .btn-filter.active .arrow-icon {
     transform: rotate(180deg);
}
 .filter-card {
     position: absolute;
     top: calc(100% + 12px);
     right: 0;
     width: 320px;
     max-height: calc(100vh - 200px);
     background: #fff;
     border-radius: 16px;
     border: 1px solid var(--border-lt);
     box-shadow: 0 20px 60px rgba(0,0,0,.15);
     z-index: 3000;
     padding: 24px;
     overflow-y: auto;
     opacity: 0;
     visibility: hidden;
     transform: translateY(-10px) scale(0.98);
     transform-origin: top right;
     transition: all .2s cubic-bezier(.4,0,.2,1);
}
 .filter-card.open {
     opacity: 1;
     visibility: visible;
     transform: translateY(0) scale(1);
}
 .filter-card::before {
     content: '';
     position: absolute;
     top: -6px;
     right: 50px;
     width: 12px;
     height: 12px;
     background: #fff;
     transform: rotate(45deg);
     border-left: 1px solid var(--border-lt);
     border-top: 1px solid var(--border-lt);
}
 .filter-head {
     display: flex;
     align-items: center;
     justify-content: space-between;
     margin-bottom: 20px;
}
 .filter-title {
     font-family: 'Barlow', sans-serif;
     font-size: 16px;
     font-weight: 800;
     color: var(--text);
     letter-spacing: -.2px;
}
 .filter-close {
     width: 28px;
     height: 28px;
     border-radius: 6px;
     border: none;
     background: transparent;
     color: var(--muted);
     cursor: pointer;
     display: flex;
     align-items: center;
     justify-content: center;
     transition: .2s;
     font-size: 14px;
}
 .filter-close:hover {
     color: var(--red);
}
 .filter-group {
     margin-bottom: 16px;
}
 .filter-label {
     font-size: 11px;
     font-weight: 800;
     color: var(--text);
     text-transform: uppercase;
     letter-spacing: .8px;
     margin-bottom: 8px;
     display: block;
}
 .filter-select {
     width: 100%;
     padding: 10px 14px;
     border: 1.5px solid var(--border);
     border-radius: 10px;
     font-family: 'Barlow', sans-serif;
     font-size: 13px;
     color: var(--text-md);
     background: #fff;
     cursor: pointer;
     appearance: none;
     background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236B7280' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
     background-repeat: no-repeat;
     background-position: right 14px center;
     padding-right: 40px;
     transition: all .2s;
}
 .filter-select:focus {
     outline: none;
     border-color: var(--orange);
     box-shadow: 0 0 0 3px var(--orange-lt);
}
 .filter-select:hover {
     border-color: #D1D5DB;
}
 .filter-actions {
     display: flex;
     gap: 10px;
     margin-top: 20px;
     padding-top: 16px;
     border-top: 1px solid var(--border-lt);
}
 .btn-filter-apply {
     flex: 1;
     background: var(--orange);
     color: #fff;
     border: none;
     padding: 10px 14px;
     border-radius: 10px;
     font-family: 'Barlow', sans-serif;
     font-size: 12px;
     font-weight: 800;
     cursor: pointer;
     text-transform: uppercase;
     letter-spacing: .5px;
     transition: .2s;
     display: flex;
     align-items: center;
     justify-content: center;
     gap: 6px;
}
 .btn-filter-apply:hover {
     background: var(--orange-dk);
     transform: translateY(-1px);
     box-shadow: 0 6px 16px rgba(255,69,0,.25);
}
 .btn-filter-apply i {
     font-size: 11px;
}
 .btn-filter-reset {
     flex: 1;
     background: var(--bg);
     color: var(--text-md);
     border: 1.5px solid var(--border);
     padding: 10px 14px;
     border-radius: 10px;
     font-family: 'Barlow', sans-serif;
     font-size: 12px;
     font-weight: 800;
     cursor: pointer;
     text-transform: uppercase;
     letter-spacing: .5px;
     transition: .2s;
     display: flex;
     align-items: center;
     justify-content: center;
     gap: 6px;
     text-decoration: none;
}
 .btn-filter-reset:hover {
     background: var(--border-lt);
     border-color: var(--text-md);
}
 .btn-filter-reset i {
     font-size: 11px;
}
 .filter-active-badge {
     display: inline-flex;
     align-items: center;
     gap: 6px;
     background: var(--orange-lt);
     color: var(--orange);
     padding: 6px 14px;
     border-radius: 20px;
     font-size: 11px;
     font-weight: 800;
     text-transform: uppercase;
     letter-spacing: .5px;
     margin-right: 8px;
}
 .cust-id {
     font-family: 'Barlow Condensed', sans-serif;
     font-weight: 800;
     color: var(--orange);
     font-size: 14px;
}
 body.swal2-shown, html.swal2-shown {
     padding-right: 0px !important;
}

</style>
</head>
<body>
<aside class="sidebar">
    <a href="../dashboard/view_admin.php" class="sb-brand">
        <div class="sb-icon"><i class="fa-solid fa-basketball"></i></div>
        <div>
            <div class="sb-brand-name">HOOP BALL</div>
            <div class="sb-brand-sub">SISTEM MANAGEMEN</div>
        </div>
    </a>
    <div class="sb-section-label">Operasional</div>
    <nav>
        <a href="../dashboard/view_admin.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-house"></i></div>
            Dashboard
        </a>
        <a href="customer.php" class="sb-link active">
            <div class="sb-icon-wrap"><i class="fa-solid fa-users"></i></div>
            Kelola Customer
        </a>
        <a href="lapangan.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-layer-group"></i></div>
            Kelola Lapangan
        </a>
        <a href="fasilitas_lapangan.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-list-check"></i></div>
            Kelola Fasilitas
        </a>
        <a href="jadwal.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-calendar-days"></i></div>
            Kelola Jadwal
        </a>
        <a href="promo.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-tags"></i></div>
            Kelola Promo
        </a>
        <a href="tipe_member.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-id-card"></i></div>
            Kelola Tipe Member
        </a>
        <a href="alat.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-toolbox"></i></div>
            Kelola Pembelian Alat
        </a>
    </nav>

    <div class="sb-section-label">Transaksi</div>
    <nav>
        <a href="../transaksi/booking.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-calendar-check"></i></div>
            Kelola Booking
        </a>
        <a href="../transaksi/langganan.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-crown"></i></div>
            Kelola Langganan
        </a>
        <a href="../transaksi/pembelian.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-cart-shopping"></i></div>
            Kelola Pembelian Alat
        </a>
        <a href="../transaksi/pembatalan.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-ban"></i></div>
            Kelola Pembatalan
        </a>
    </nav>

    <div class="sb-section-label">Akun</div>
    <nav>
        <a href="../profile/profile.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-id-badge"></i></div>
            Profil Saya
        </a>
    </nav>

    <div class="sb-bottom">
        <div class="sb-user">
            <div class="sb-avatar">
                <?php if (!empty($sidebar_photo)): ?>
                    <img src="<?= $sidebar_photo ?>" alt="Profile">
                <?php else: ?>
                    <i class="fa-solid fa-user"></i>
                <?php endif; ?>
            </div>
            <div><div class="sb-user-name"><?= strtoupper(htmlspecialchars($nama)) ?></div><div class="sb-user-role">KARYAWAN</div></div>
            <a href="../login/logout.php" class="sb-logout" title="Keluar"><i class="fa-solid fa-right-from-bracket"></i></a>
        </div>
    </div>
</aside>

<main class="main">
    <header class="topbar">
        <div class="topbar-left">
            <div class="topbar-title">Kelola Customer</div>
            <div class="topbar-breadcrumb">Operasional / Customer</div>
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
            <a href="#" class="topbar-btn">
                <i class="fa-solid fa-bell"></i>
                <?php if(isset($total_pending) && $total_pending > 0): ?><span class="notif-dot"></span><?php endif; ?>
            </a>
            <div class="dropdown-wrap">
                <div class="topbar-user">
                    <div class="t-avatar">
                    <?php if (!empty($sidebar_photo)): ?>
                        <img src="<?= $sidebar_photo ?>" alt="Profile">
                    <?php else: ?>
                        <i class="fa-solid fa-user"></i>
                    <?php endif; ?>
                </div>
                    <div>
                        <div class="t-name"><?= strtoupper(htmlspecialchars($nama)) ?></div>
                        <div class="t-role"><?= strtoupper(htmlspecialchars($role)) ?></div>
                    </div>
                    <i class="fa-solid fa-chevron-down t-chevron"></i>
                </div>
                <div class="dropdown-menu">
                    <a href="../profile/profile.php" class="dd-item"><i class="fa-solid fa-id-badge"></i> Profil Saya</a>
                    <hr class="dd-divider">
                    <a href="../login/logout.php" class="dd-item" style="color:var(--red);"><i class="fa-solid fa-right-from-bracket"></i> Keluar</a>
                </div>
            </div>
        </div>
    </header>

    <div class="content">
        <div class="page-header">
            <div>
                <div class="page-title-tag"></div>
                <div class="page-title">Kelola Customer</div>
            </div>
            <div class="stat-chips">
                <div class="stat-chip chip-green"><i class="fa-solid fa-circle-check"></i> AKTIF <span class="chip-val"><?= $total_aktif ?></span></div>
                <div class="stat-chip chip-red"><i class="fa-solid fa-circle-xmark"></i> NONAKTIF <span class="chip-val"><?= $total_nonaktif ?></span></div>
                <div class="stat-chip chip-blue"><i class="fa-solid fa-users"></i> TOTAL <span class="chip-val"><?= $total_cust ?></span></div>
            </div>
        </div>

        <div class="action-bar">
            <div class="search-box">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="src" placeholder="Cari customer..." onkeyup="searchTable()">
            </div>
            <div class="action-right">
                <div class="filter-wrap">
                    <?php if ($filter_jk >= 0 || $filter_status != 'all'): ?>
                    <span class="filter-active-badge"><i class="fa-solid fa-filter"></i> Filter Aktif</span>
                    <?php endif; ?>
                    <button class="btn-filter" id="btnFilterToggle">
                        <i class="fa-solid fa-filter"></i> Filter <i class="fa-solid fa-chevron-down arrow-icon"></i>
                    </button>

                    <div class="filter-card" id="filterCard">
                        <div class="filter-head">
                            <div class="filter-title">Filter Data</div>
                            <button type="button" class="filter-close" id="filterClose">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </div>
                        <form method="GET" id="filterForm">
                            <input type="hidden" name="page" value="1">

                            <div class="filter-group">
                                <label class="filter-label">Urut Berdasarkan</label>
                                <!-- ID Customer DIHAPUS, hanya nomor urut yang tersisa -->
                                <select name="sort" class="filter-select">
                                    <option value="Nama_Customer" <?= $sort_by == 'Nama_Customer' ? 'selected' : '' ?>>Nama Lengkap</option>
                                    <option value="Jenis_Kelamin" <?= $sort_by == 'Jenis_Kelamin' ? 'selected' : '' ?>>Jenis Kelamin</option>
                                    <option value="Alamat" <?= $sort_by == 'Alamat' ? 'selected' : '' ?>>Alamat</option>
                                    <option value="No_Telepon" <?= $sort_by == 'No_Telepon' ? 'selected' : '' ?>>No. Telepon</option>
                                    <option value="Email" <?= $sort_by == 'Email' ? 'selected' : '' ?>>Email</option>
                                    <option value="Created_Date" <?= $sort_by == 'Created_Date' ? 'selected' : '' ?>>Tanggal Dibuat</option>
                                </select>
                            </div>

                            <div class="filter-group">
                                <label class="filter-label">Urutan</label>
                                <select name="order" class="filter-select">
                                    <option value="ASC" <?= $sort_order == 'ASC' ? 'selected' : '' ?>>Naik (A-Z)</option>
                                    <option value="DESC" <?= $sort_order == 'DESC' ? 'selected' : '' ?>>Turun (Z-A)</option>
                                </select>
                            </div>

                            <div class="filter-group">
                                <label class="filter-label">Jenis Kelamin</label>
                                <select name="jk" class="filter-select">
                                    <option value="-1" <?= $filter_jk == -1 ? 'selected' : '' ?>>Semua Jenis Kelamin</option>
                                    <option value="0" <?= $filter_jk == 0 ? 'selected' : '' ?>>Laki-laki</option>
                                    <option value="1" <?= $filter_jk == 1 ? 'selected' : '' ?>>Perempuan</option>
                                </select>
                            </div>

                            <div class="filter-group">
                                <label class="filter-label">Status</label>
                                <select name="status_filter" class="filter-select">
                                    <option value="all" <?= $filter_status == 'all' ? 'selected' : '' ?>>Semua Status</option>
                                    <option value="aktif" <?= $filter_status == 'aktif' ? 'selected' : '' ?>>Aktif</option>
                                    <option value="nonaktif" <?= $filter_status == 'nonaktif' ? 'selected' : '' ?>>Non Aktif</option>
                                </select>
                            </div>

                            <div class="filter-actions">
                                <a href="customer.php" class="btn-filter-reset">
                                    <i class="fa-solid fa-rotate-left"></i> Reset
                                </a>
                                <button type="submit" class="btn-filter-apply">
                                    <i class="fa-solid fa-check"></i> Terapkan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>

        <?php if ($query_error): ?>
        <div style="padding:20px;background:#fee;border:1px solid #fcc;border-radius:8px;margin:20px 0;">
            <p style="color:#c00;font-weight:bold;margin:0;"><i class="fa-solid fa-circle-exclamation"></i> Gagal mengambil data dari database. Silakan refresh halaman atau hubungi administrator.</p>
            <p style="color:#666;font-size:11px;margin:5px 0 0;">Error: <?= htmlspecialchars($query_error_msg) ?></p>
        </div>
        <?php else: ?>

        <div class="card">
            <div class="table-wrap">
                <table class="data-table" id="tbl">
                    <thead>
                        <tr>
                            <th style="width: 80px;">No</th>
                            <th>Nama</th>
                            <th style="text-align: left; width: 35%;">Email</th>
                            <th style="width: 150px;">Status</th>
                            <th style="text-align: left; width: 180px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $has_data = false;
                    $row_num = 0;
                    $no = $offset + 1;
                    if ($query):
                    while ($row = safe_sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)):
                        $has_data = true;
                        $row_num++;
                        $status_int = isset($row['Status']) ? intval($row['Status']) : 1;
                    ?>
                        <tr>
                            <td style="font-family:'Barlow'; font-weight:700; color:var(--text);"><?= $no++ ?></td>
                            <td class="cust-name"><?= htmlspecialchars($row['Nama_Customer']) ?></td>
                            <td class="cust-email"><?= htmlspecialchars($row['Email'] ?? '-') ?></td>
                            <td>
                                <span class="status-pill <?= $status_int === 1 ? 'sp-active' : 'sp-inactive' ?>">
                                    <span class="sp-dot"></span>
                                    <?= $status_int === 1 ? 'AKTIF' : 'NONAKTIF' ?>
                                </span>
                            </td>
                            <td>
                                <div class="actions">
                                    <a href="?detail_id=<?= htmlspecialchars($row['ID_Customer']) ?>" class="btn-action btn-view" title="Lihat Detail">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                    <label class="toggle-switch" title="<?= $status_int === 1 ? 'Nonaktifkan' : 'Aktifkan' ?>">
                                        <input type="checkbox" <?= $status_int === 1 ? 'checked' : '' ?> onchange="confirmToggle('<?= $row['ID_Customer'] ?>', '<?= htmlspecialchars($row['Nama_Customer']) ?>', <?= $status_int ?>, event)">
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; endif; ?>
                    <?php if (!$has_data): ?>
                        <tr><td colspan="5"><div class="empty-state"><i class="fa-solid fa-users"></i><div>Belum ada data customer</div><div style="font-size: 12px; font-weight: 500; margin-top: 8px; opacity: .7;">Data customer akan muncul di sini setelah registrasi</div></div></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($total_pages > 1): ?>
        <div class="pagination-wrap">
            <div class="pagination-info">Menampilkan <strong><?= (($page - 1) * $limit) + 1 ?></strong> - <strong><?= min($page * $limit, $total_cust) ?></strong> dari <strong><?= $total_cust ?></strong> data</div>
            <div class="pagination-nav">
                <a href="?page=1&sort=<?= $sort_by ?>&order=<?= $sort_order ?>&jk=<?= $filter_jk ?>&status_filter=<?= $filter_status ?>" class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>" title="Halaman Pertama"><i class="fa-solid fa-angles-left"></i></a>
                <a href="?page=<?= $page - 1 ?>&sort=<?= $sort_by ?>&order=<?= $sort_order ?>&jk=<?= $filter_jk ?>&status_filter=<?= $filter_status ?>" class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>" title="Halaman Sebelumnya"><i class="fa-solid fa-angle-left"></i></a>
                <?php $start_page = max(1, $page - 2); $end_page = min($total_pages, $page + 2); if ($end_page - $start_page < 4 && $total_pages >= 5) { if ($start_page == 1) { $end_page = min(5, $total_pages); } else { $start_page = max(1, $total_pages - 4); } } if ($start_page > 1): ?><a href="?page=1&sort=<?= $sort_by ?>&order=<?= $sort_order ?>&jk=<?= $filter_jk ?>&status_filter=<?= $filter_status ?>" class="page-btn">1</a><?php if ($start_page > 2): ?><span class="page-ellipsis">...</span><?php endif; ?><?php endif; ?>
                <?php for ($i = $start_page; $i <= $end_page; $i++): ?><a href="?page=<?= $i ?>&sort=<?= $sort_by ?>&order=<?= $sort_order ?>&jk=<?= $filter_jk ?>&status_filter=<?= $filter_status ?>" class="page-btn <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a><?php endfor; ?>
                <?php if ($end_page < $total_pages): ?><?php if ($end_page < $total_pages - 1): ?><span class="page-ellipsis">...</span><?php endif; ?><a href="?page=<?= $total_pages ?>&sort=<?= $sort_by ?>&order=<?= $sort_order ?>&jk=<?= $filter_jk ?>&status_filter=<?= $filter_status ?>" class="page-btn"><?= $total_pages ?></a><?php endif; ?>
                <a href="?page=<?= $page + 1 ?>&sort=<?= $sort_by ?>&order=<?= $sort_order ?>&jk=<?= $filter_jk ?>&status_filter=<?= $filter_status ?>" class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>" title="Halaman Selanjutnya"><i class="fa-solid fa-angle-right"></i></a>
                <a href="?page=<?= $total_pages ?>&sort=<?= $sort_by ?>&order=<?= $sort_order ?>&jk=<?= $filter_jk ?>&status_filter=<?= $filter_status ?>" class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>" title="Halaman Terakhir"><i class="fa-solid fa-angles-right"></i></a>
            </div>
        </div>
        <?php else: ?>
        <div class="pagination-wrap"><div class="pagination-info">Menampilkan <strong>1</strong> - <strong><?= $total_cust ?></strong> dari <strong><?= $total_cust ?></strong> data</div></div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</main>

<!-- MODAL DETAIL CUSTOMER -->
<div class="modal-overlay <?= $show_detail ? 'open' : '' ?>" id="modalDetail">
    <div class="modal-box" style="width: 420px; max-height: 95vh; overflow-y: auto;">
        <button class="modal-close" onclick="closeDetail()"><i class="fa-solid fa-xmark"></i></button>
        <div class="modal-header" style="padding: 20px 24px 10px; border-bottom: 1px solid var(--border);">
            <div class="modal-subtitle">Informasi Pelanggan</div>
            <div class="modal-title">Profil Customer</div>
        </div>
        <div class="modal-body" style="padding: 12px 24px 20px;">
            <?php if ($detail_data): 
                $is_active = $detail_data['Status'] == 1;
                $jk_icon = $detail_data['Jenis_Kelamin'] == 0 ? 'fa-mars' : 'fa-venus';
                $jk_color = $detail_data['Jenis_Kelamin'] == 0 ? 'var(--blue)' : 'var(--pink)';
            ?>
                <div class="detail-photo-card">
                    <div class="detail-icon-wrap"><i class="fa-solid fa-user"></i></div>
                    <div class="detail-main-name"><?= htmlspecialchars($detail_data['Nama_Customer']) ?></div>
                </div>

                <!-- ID Customer hidden from view -->
                <input type="hidden" value="<?= htmlspecialchars($detail_data['ID_Customer']) ?>">
                <div class="info-row">
                    <span class="info-key"><i class="fa-solid fa-user"></i> Nama Lengkap</span>
                    <span class="info-val"><?= htmlspecialchars($detail_data['Nama_Customer']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-key"><i class="fa-solid fa-envelope"></i> Email</span>
                    <span class="info-val"><?= htmlspecialchars($detail_data['Email'] ?? '-') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-key"><i class="fa-solid fa-venus-mars" style="color:<?= $jk_color ?>"></i> Jenis Kelamin</span>
                    <span class="info-val" style="color:<?= $jk_color ?>;">
                        <i class="fa-solid <?= $jk_icon ?>"></i> <?= jk_label($detail_data['Jenis_Kelamin']) ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-key"><i class="fa-solid fa-cake-candles"></i> Tanggal Lahir</span>
                    <span class="info-val"><?= format_tgl_display($detail_data['Tanggal_Lahir']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-key"><i class="fa-solid fa-location-dot"></i> Tempat Lahir</span>
                    <span class="info-val"><?= htmlspecialchars($detail_data['Tempat_Lahir'] ?? '-') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-key"><i class="fa-solid fa-map-location-dot"></i> Alamat</span>
                    <span class="info-val"><?= htmlspecialchars($detail_data['Alamat'] ?? '-') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-key"><i class="fa-solid fa-phone"></i> No. Telepon</span>
                    <span class="info-val"><?= htmlspecialchars($detail_data['No_Telepon'] ?? '-') ?></span>
                </div>
                <div class="info-row" style="border-bottom:none;">
                    <span class="info-key"><i class="fa-solid fa-shield-halved"></i> Status</span>
                    <span class="info-val">
                        <?php if ($is_active): ?>
                            <span class="status-badge status-active">AKTIF</span>
                        <?php else: ?>
                            <span class="status-badge status-inactive">NONAKTIF</span>
                        <?php endif; ?>
                    </span>
                </div>
            <?php endif; ?>

            <button onclick="closeDetail()" class="btn-submit" style="margin-top: 12px; background: #0D1117;">
                <i class="fa-solid fa-arrow-left"></i> Kembali Ke List
            </button>
        </div>
    </div>
</div>

<script>
// CLOCK
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

    const dayName = days[now.getDay()];
    const date = now.getDate();
    const monthName = months[now.getMonth()];
    const year = now.getFullYear();

    document.getElementById('full-date').innerText = dayName + ', ' + date + ' ' + monthName + ' ' + year;
}

updateClock();
setInterval(updateClock, 1000);

// MODAL FUNCTIONS
function closeDetail() { 
    window.location.href = 'customer.php'; 
}

// SEARCH TABLE
function searchTable() {
    var input = document.getElementById('src').value.toUpperCase();
    var rows = document.getElementById('tbl').getElementsByTagName('tr');
    for (var i = 1; i < rows.length; i++) {
        var tdName = rows[i].getElementsByTagName('td')[1];
        var tdEmail = rows[i].getElementsByTagName('td')[2];
        if (tdName || tdEmail) {
            var match = false;
            if (tdName && tdName.textContent.toUpperCase().indexOf(input) > -1) match = true;
            if (tdEmail && tdEmail.textContent.toUpperCase().indexOf(input) > -1) match = true;
            rows[i].style.display = match ? '' : 'none';
        }
    }
}

// TOGGLE CONFIRMATION - DIPERBAIKI
function confirmToggle(id, name, currentStatus, event) {
    const checkbox = event.target;
    const newStatus = currentStatus === 1 ? 0 : 1;
    const statusText = newStatus === 1 ? 'Aktif' : 'Nonaktif';
    const icon = newStatus === 1 ? 'success' : 'warning';
    const confirmColor = newStatus === 1 ? '#10B981' : '#EF4444';

    Swal.fire({
        title: 'Ubah Status?',
        html: 'Ubah status <strong style="color:var(--orange);">' + name + '</strong> menjadi <strong>' + statusText + '</strong>?',
        icon: icon,
        showCancelButton: true,
        confirmButtonColor: confirmColor,
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Ya, Ubah!',
        cancelButtonText: 'Batal',
        reverseButtons: true,
        allowOutsideClick: false
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Memproses...',
                text: 'Mengubah status customer',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            setTimeout(() => {
                window.location.href = '?toggle_id=' + id;
            }, 600);
        } else {
            checkbox.checked = !checkbox.checked;
        }
    });
}

// FILTER DROPDOWN
document.addEventListener('DOMContentLoaded', function() {
    const btnFilterToggle = document.getElementById('btnFilterToggle');
    const filterCard = document.getElementById('filterCard');
    const filterClose = document.getElementById('filterClose');

    if (btnFilterToggle && filterCard) {
        btnFilterToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            this.classList.toggle('active');
            filterCard.classList.toggle('open');
        });

        filterCard.addEventListener('click', function(e) {
            e.stopPropagation();
        });

        if (filterClose) {
            filterClose.addEventListener('click', function() {
                btnFilterToggle.classList.remove('active');
                filterCard.classList.remove('open');
            });
        }

        document.addEventListener('click', function() {
            btnFilterToggle.classList.remove('active');
            filterCard.classList.remove('open');
        });
    }

    // Dropdown profil user
    const userDropdown = document.querySelector('.dropdown-wrap');
    if (userDropdown) {
        userDropdown.addEventListener('click', function (e) {
            e.stopPropagation();
            this.classList.toggle('active');
        });
        document.addEventListener('click', function () {
            userDropdown.classList.remove('active');
        });
    }

    // URL PARAMETER NOTIFICATION
    const urlParams = new URLSearchParams(window.location.search);
    const status = urlParams.get('status');
    const msg = urlParams.get('msg');

    if (status && msg) {
        const isSuccess = status === 'success';

        Swal.fire({
            icon: isSuccess ? 'success' : 'error',
            title: isSuccess ? 'Berhasil!' : 'Gagal!',
            text: msg,
            showConfirmButton: true,
            confirmButtonText: 'OK',
            confirmButtonColor: isSuccess ? '#10B981' : '#EF4444',
            allowOutsideClick: false,
            allowEscapeKey: false
        });

        window.history.replaceState({}, document.title, window.location.pathname);
    }
});
</script>
</body>
</html>