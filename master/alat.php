<?php
ob_start();
session_start();
date_default_timezone_set("Asia/Jakarta");
include '../includes/config.php';

if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'karyawan' && $_SESSION['role'] !== 'pemilik')) {
    echo "<script>alert('Akses Ditolak!'); window.location='../dashboard/dashboard.php';</script>";
    exit();
}
$role = $_SESSION['role'];
$nama = substr($_SESSION['nama'] ?? 'USER', 0, 50);

$profile_photo = '';
$id_karyawan_session = $_SESSION['id_karyawan'] ?? $_SESSION['id_akun'] ?? '';
if (!empty($id_karyawan_session)) {
    $stmt_photo = sqlsrv_query($conn, "SELECT Photo_Profile FROM Karyawan WHERE ID_Karyawan = ?", array($id_karyawan_session));
    if ($stmt_photo !== false) {
        $row_photo = sqlsrv_fetch_array($stmt_photo, SQLSRV_FETCH_ASSOC);
        if ($row_photo && !empty($row_photo['Photo_Profile'])) {
            $photo_path = $row_photo['Photo_Profile'];
            if (strpos($photo_path, 'uploads/profiles/') !== false) {
                $profile_photo = '../' . $photo_path;
            } else {
                $profile_photo = '../uploads/profiles/' . $photo_path;
            }
        }
    }
}

function safeQuery($conn, $sql, $params = []) {
    $stmt = empty($params) ? sqlsrv_query($conn, $sql) : sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        $errors = sqlsrv_errors(SQLSRV_ERR_ALL);
        error_log("[ALAT-ERROR] SQL Error: " . print_r($errors, true));
        error_log("[ALAT-ERROR] Query: " . $sql);
        return false;
    }
    return $stmt;
}

function safeFetch($stmt) {
    if ($stmt === false || $stmt === null) return false;
    return sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
}

function getLastSqlError($conn) {
    $errors = sqlsrv_errors(SQLSRV_ERR_ALL);
    if (!empty($errors) && isset($errors[0]['message'])) {
        return $errors[0]['message'];
    }
    return 'Unknown database error';
}

function getPhotoUrl($photo_path) {
    if (empty($photo_path)) return '';
    $path = str_replace('../', '', $photo_path);
    $path = ltrim($path, '/');
    return '../' . $path;
}

function getUploadDirectory() {
    $upload_dir = '../asset/image/';
    if (!is_dir($upload_dir)) {
        @mkdir($upload_dir, 0755, true);
    }
    return $upload_dir;
}

function processPhotoUpload($file, $edit_data = null) {
    $upload_dir = getUploadDirectory();
    if (!isset($file) || empty($file['name'])) {
        if ($edit_data && !empty($edit_data['Photo_Alat'])) {
            return $edit_data['Photo_Alat'];
        }
        return false;
    }
    if (!is_dir($upload_dir)) {
        @mkdir($upload_dir, 0755, true);
    }
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_ext = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    if (!in_array($file_ext, $allowed_ext)) {
        return ($edit_data ? $edit_data['Photo_Alat'] : '');
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        return ($edit_data ? $edit_data['Photo_Alat'] : '');
    }
    $new_file_name = 'alat_' . time() . '_' . uniqid() . '.' . $file_ext;
    $target_path = $upload_dir . $new_file_name;
    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        return 'asset/image/' . $new_file_name;
    }
    return ($edit_data && !empty($edit_data['Photo_Alat'])) ? $edit_data['Photo_Alat'] : '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_alat'])) {
    $id = isset($_POST['id_alat']) ? intval($_POST['id_alat']) : 0;
    $nama_alat = trim($_POST['nama_alat'] ?? '');
    $stok_raw = preg_replace('/[^0-9]/', '', trim($_POST['stok'] ?? ''));
    $harga_raw = preg_replace('/[^0-9]/', '', trim($_POST['harga_alat'] ?? ''));
    $edit_mode = isset($_POST['edit_mode']) && $_POST['edit_mode'] == '1';
    $edit_photo_path = isset($_POST['edit_photo_path']) ? trim($_POST['edit_photo_path']) : '';

    $errors = [];
    if ($nama_alat === '') {
        $errors[] = 'Nama alat wajib diisi.';
    } elseif (strlen($nama_alat) > 25) {
        $errors[] = 'Nama alat maksimal 25 karakter.';
    }
    if ($stok_raw === '' || !is_numeric($stok_raw)) {
        $errors[] = 'Stok harus berupa angka.';
    } else {
        $stok_num = intval($stok_raw);
        if ($stok_num <= 0) {
            $errors[] = 'Stok tidak boleh 0 atau kurang dari 0.';
        } elseif ($stok_num > 9999) {
            $errors[] = 'Stok maksimal 9999.';
        }
    }
    if ($harga_raw === '' || !is_numeric($harga_raw)) {
        $errors[] = 'Harga harus berupa angka.';
    }
    if (empty($errors)) {
        $sql_check = "SELECT ID_Alat FROM Alat WHERE LOWER(Nama_Alat) = LOWER(?) AND ID_Alat <> ? AND Is_Deleted = 0";
        $q_check = safeQuery($conn, $sql_check, [$nama_alat, $id]);
        if ($q_check && safeFetch($q_check)) {
            $errors[] = 'Nama alat sudah terdaftar.';
        }
    }
    if (empty($errors)) {
        $stok = intval($stok_raw);
        $harga_alat = number_format(floatval($harga_raw), 2, '.', '');
        $edit_data_for_photo = ($edit_mode && !empty($edit_photo_path)) ? ['Photo_Alat' => $edit_photo_path] : null;
        $photo_alat = processPhotoUpload($_FILES['photo_alat'] ?? null, $edit_data_for_photo);

        if ($photo_alat === false) {
            $errors[] = 'Foto alat wajib diupload.';
        }
    }

    if (empty($errors)) {
        if ($edit_mode && $id > 0) {
            $sql = "UPDATE Alat SET Nama_Alat=?, Stok=?, Harga_Alat=?, Photo_Alat=?, Modified_By=?, Modified_Date=GETDATE() WHERE ID_Alat=?";
            $params = [$nama_alat, $stok, $harga_alat, $photo_alat, $nama, $id];
        } else {
            $sql = "INSERT INTO Alat (Nama_Alat, Stok, Harga_Alat, Photo_Alat, Status, Is_Deleted, Created_By, Created_Date) VALUES (?, ?, ?, ?, 1, 0, ?, GETDATE())";
            $params = [$nama_alat, $stok, $harga_alat, $photo_alat, $nama];
        }
        $result = safeQuery($conn, $sql, $params);
        if ($result !== false) {
            ob_end_clean();
            $msg = $edit_mode ? 'Data alat berhasil diperbarui!' : 'Alat baru berhasil ditambahkan!';
            header("Location: alat.php?status=success&msg=" . urlencode($msg));
            exit();
        } else {
            $db_error = getLastSqlError($conn);
            header("Location: alat.php?status=error&msg=" . urlencode("Gagal menyimpan data: " . $db_error));
            exit();
        }
    } else {
        header("Location: alat.php?status=error&msg=" . urlencode(implode(' | ', $errors)));
        exit();
    }
}

if (isset($_GET['toggle_id'])) {
    $toggle_id = intval($_GET['toggle_id']);
    $current_status = intval($_GET['s']);
    $s_baru = ($current_status == 1) ? 0 : 1;
    $result = safeQuery($conn, "UPDATE Alat SET Status=?, Modified_By=?, Modified_Date=GETDATE() WHERE ID_Alat=?", [$s_baru, $nama, $toggle_id]);
    if ($result !== false) {
        ob_end_clean();
        $msg = ($s_baru == 1) ? 'Alat berhasil diaktifkan!' : 'Alat berhasil dinonaktifkan!';
        header("Location: alat.php?status=success&msg=" . urlencode($msg));
    } else {
        ob_end_clean();
        header("Location: alat.php?status=error&msg=" . urlencode('Gagal mengubah status alat: ' . getLastSqlError($conn)));
    }
    exit();
}

if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $stmt_nama = safeQuery($conn, "SELECT Nama_Alat FROM Alat WHERE ID_Alat = ?", [$delete_id]);
    $nama_alat_deleted = '';
    if ($stmt_nama) {
        $row_nama = safeFetch($stmt_nama);
        if ($row_nama) $nama_alat_deleted = $row_nama['Nama_Alat'];
    }
    $result = safeQuery($conn, "UPDATE Alat SET Is_Deleted=1, Deleted_By=?, Deleted_Date=GETDATE() WHERE ID_Alat=?", [$nama, $delete_id]);
    if ($result !== false) {
        ob_end_clean();
        $msg = !empty($nama_alat_deleted) ? 'Alat "' . $nama_alat_deleted . '" berhasil dihapus!' : 'Alat berhasil dihapus!';
        header("Location: alat.php?status=success&msg=" . urlencode($msg));
    } else {
        ob_end_clean();
        header("Location: alat.php?status=error&msg=" . urlencode('Gagal menghapus alat: ' . getLastSqlError($conn)));
    }
    exit();
}

$edit_data = null;
if (isset($_GET['edit_id'])) {
    $r = safeQuery($conn, "SELECT * FROM Alat WHERE ID_Alat=? AND Is_Deleted=0", [intval($_GET['edit_id'])]);
    if ($r) $edit_data = safeFetch($r);
}

$detail_data = null;
$show_detail = false;
if (isset($_GET['detail_id'])) {
    $r = safeQuery($conn, "SELECT * FROM Alat WHERE ID_Alat=? AND Is_Deleted=0", [intval($_GET['detail_id'])]);
    if ($r) {
        $detail_data = safeFetch($r);
        $show_detail = ($detail_data !== false && $detail_data !== null);
    }
}

$show_add = isset($_GET['add']) && $_GET['add'] == '1';

$where_clauses = ["Is_Deleted = 0"];
$params = [];
if (isset($_GET['f_status']) && $_GET['f_status'] !== '') {
    $where_clauses[] = "Status = ?";
    $params[] = intval($_GET['f_status']);
}
$where_sql = implode(" AND ", $where_clauses);

$sort_by = "Nama_Alat ASC";
if (isset($_GET['f_sort'])) {
    switch ($_GET['f_sort']) {
        case 'nama_asc': $sort_by = "Nama_Alat ASC"; break;
        case 'stok_desc': $sort_by = "Stok DESC"; break;
        case 'harga_desc': $sort_by = "Harga_Alat DESC"; break;
        case 'harga_asc': $sort_by = "Harga_Alat ASC"; break;
    }
}

$q_total = safeQuery($conn, "SELECT COUNT(*) as t FROM Alat WHERE Is_Deleted=0");
$total_alat = 0;
if ($q_total) { $row = safeFetch($q_total); $total_alat = $row['t'] ?? 0; }

$q_aktif = safeQuery($conn, "SELECT COUNT(*) as t FROM Alat WHERE Is_Deleted=0 AND Status=1");
$aktif_count = 0;
if ($q_aktif) { $row = safeFetch($q_aktif); $aktif_count = $row['t'] ?? 0; }

$q_nonaktif = safeQuery($conn, "SELECT COUNT(*) as t FROM Alat WHERE Is_Deleted=0 AND Status=0");
$nonaktif_count = 0;
if ($q_nonaktif) { $row = safeFetch($q_nonaktif); $nonaktif_count = $row['t'] ?? 0; }

$limit = 12;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

$count_sql = "SELECT COUNT(*) as t FROM Alat WHERE $where_sql";
$count_res = safeQuery($conn, $count_sql, $params);
$total_data = 0;
if ($count_res) { $row = safeFetch($count_res); $total_data = $row['t'] ?? 0; }

$total_pages = max(1, ceil($total_data / $limit));
$page = min($page, $total_pages);
$offset = ($page - 1) * $limit;

$query_sql = "SELECT * FROM Alat WHERE $where_sql ORDER BY $sort_by OFFSET " . intval($offset) . " ROWS FETCH NEXT " . intval($limit) . " ROWS ONLY";
$query = safeQuery($conn, $query_sql, $params);

$q_pending = safeQuery($conn, "SELECT COUNT(*) as t FROM Booking WHERE Status=0");
$total_pending = 0;
if ($q_pending) { $row = safeFetch($q_pending); $total_pending = $row['t'] ?? 0; }

$filter_url = "";
if (isset($_GET['f_sort'])) $filter_url .= "&f_sort=" . urlencode($_GET['f_sort']);
if (isset($_GET['f_status'])) $filter_url .= "&f_status=" . urlencode($_GET['f_status']);

function rupiah($n) { return 'Rp ' . number_format($n, 0, ',', '.'); }
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Kelola Alat | HoopBall</title>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800;900&family=Barlow:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
:root {
    --orange: #FF4500; --orange-lt: rgba(255,69,0,.10); --orange-dk: #E03E00;
    --green: #10B981; --green-lt: rgba(16,185,129,.10);
    --blue: #3B82F6; --blue-lt: rgba(59,130,246,.10);
    --red: #EF4444; --red-lt: rgba(239,68,68,.10);
    --sidebar: #0D1117; --sidebar-w: 260px; --topbar-h: 70px;
    --card-bg: #FFFFFF; --border: #E5E7EB; --border-lt: #F3F4F6;
    --text: #111827; --text-md: #374151; --muted: #6B7280; --bg: #F3F4F6;
    --shopee-orange: #EE4D2D;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }
body { font-family: 'Barlow', sans-serif; background: var(--bg); display: flex; min-height: 100vh; color: var(--text); }

.sidebar { width: var(--sidebar-w); background: var(--sidebar); height: 100vh; position: fixed; top: 0; left: 0; display: flex; flex-direction: column; padding: 28px 18px; border-right: 1px solid rgba(255,255,255,.04); z-index: 200; overflow-y: auto; scrollbar-width: none; }
.sidebar::-webkit-scrollbar { display: none; }
.sb-brand { display: flex; align-items: center; gap: 12px; padding: 0 8px; margin-bottom: 36px; text-decoration: none; }
.sb-icon { width: 40px; height: 40px; background: var(--orange); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 18px; flex-shrink: 0; box-shadow: 0 4px 14px rgba(255,69,0,.4); }
.sb-brand-name { font-family: 'Barlow Condensed', sans-serif; font-size: 20px; font-weight: 900; color: #fff; letter-spacing: 1px; }
.sb-brand-sub { font-size: 9px; color: #4B5563; font-weight: 700; text-transform: uppercase; }
.sb-section-label { font-size: 10px; font-weight: 800; text-transform: uppercase; color: #374151; letter-spacing: .8px; padding: 0 10px; margin: 22px 0 8px; }
.sb-link { display: flex; align-items: center; gap: 12px; color: #6B7280; text-decoration: none; padding: 10px 12px; border-radius: 10px; margin-bottom: 2px; font-size: 13px; font-weight: 600; transition: all .2s ease; }
.sb-link .sb-icon-wrap { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 13px; transition: .2s; flex-shrink: 0; background: rgba(255,255,255,.04); }
.sb-link:hover { color: #E5E7EB; background: rgba(255,255,255,.04); }
.sb-link:hover .sb-icon-wrap { background: rgba(255,255,255,.08); }
.sb-link.active { color: #fff; background: var(--orange-lt); }
.sb-link.active .sb-icon-wrap { background: var(--orange); color: #fff; }
.sb-bottom { margin-top: auto; padding-top: 20px; }
.sb-user { display: flex; align-items: center; gap: 10px; background: rgba(255,255,255,.04); border-radius: 12px; padding: 12px; border: 1px solid rgba(255,255,255,.06); }
.sb-avatar { width: 36px; height: 36px; background: var(--orange); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 14px; flex-shrink: 0; overflow: hidden; }
.sb-avatar img { width: 100%; height: 100%; object-fit: cover; }
.sb-user-name { font-size: 13px; font-weight: 800; color: #E5E7EB; line-height: 1.1; }
.sb-user-role { font-size: 10px; color: var(--orange); font-weight: 700; text-transform: uppercase; }
.sb-logout { margin-left: auto; color: #4B5563; font-size: 13px; transition: .2s; cursor: pointer; text-decoration: none; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 8px; }
.sb-logout:hover { color: var(--red); background: rgba(239,68,68,.1); }

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
.topbar-user { display: flex; align-items: center; gap: 10px; background: #fff; border: 1px solid var(--border); padding: 6px 14px 6px 8px; border-radius: 12px; cursor: pointer; transition: .2s; }
.topbar-user:hover { border-color: var(--orange); }
.t-avatar { width: 32px; height: 32px; background: var(--orange); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 13px; overflow: hidden; flex-shrink: 0; }
.t-avatar img { width: 100%; height: 100%; object-fit: cover; }
.t-name { font-size: 13px; font-weight: 800; color: var(--text); line-height: 1.1; text-transform: uppercase; }
.t-role { font-size: 10px; color: var(--orange); font-weight: 700; text-transform: uppercase; }
.t-chevron { color: var(--muted); font-size: 10px; margin-left: 4px; }
.dropdown-menu { display: none; position: absolute; right: 0; top: calc(100% + 8px); background: #fff; min-width: 200px; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 15px 40px rgba(0,0,0,.12); overflow: hidden; padding: 8px 0; z-index: 999; }
.dropdown-wrap.active .dropdown-menu { display: block; }
.dd-item { display: flex; align-items: center; gap: 10px; padding: 11px 16px; color: #444; text-decoration: none; font-size: 13px; font-weight: 700; transition: .15s; }
.dd-item:hover { background: #FFF7ED; color: var(--orange); }
.dd-item i { font-size: 14px; width: 18px; text-align: center; }
.dd-divider { border: none; border-top: 1px solid #F3F4F6; margin: 4px 0; }

.content { padding: 32px 40px; flex: 1; }
.page-header { display: flex; align-items: flex-end; justify-content: space-between; margin-bottom: 24px; flex-wrap: wrap; gap: 16px; }
.page-title-tag { width: 36px; height: 4px; background: var(--orange); border-radius: 2px; margin-bottom: 8px; }
.page-title { font-family: 'Barlow Condensed', sans-serif; font-size: 30px; font-weight: 900; color: var(--text); text-transform: uppercase; }

.stat-chips { display: flex; gap: 10px; flex-wrap: wrap; }
.stat-chip { display: flex; align-items: center; gap: 8px; padding: 8px 18px; border-radius: 10px; font-size: 12px; font-weight: 700; transition: all .2s; }
.stat-chip:hover { transform: translateY(-2px); }
.chip-green { background: var(--green-lt); color: var(--green); }
.chip-red { background: var(--red-lt); color: var(--red); }
.chip-blue { background: var(--blue-lt); color: var(--blue); }
.chip-val { font-family: 'Barlow Condensed'; font-size: 20px; font-weight: 900; }

.action-bar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; flex-wrap: wrap; gap: 12px; }
.search-box { position: relative; width: 300px; }
.search-box i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: 13px; }
.search-box input { width: 100%; padding: 10px 14px 10px 40px; background: var(--card-bg); border: 1.5px solid var(--border); border-radius: 10px; font-size: 13px; font-family: 'Barlow', sans-serif; outline: none; transition: all .2s; color: var(--text); }
.search-box input:focus { border-color: var(--orange); box-shadow: 0 0 0 3px var(--orange-lt); }
.search-box input::placeholder { color: #9CA3AF; }

/* ===== CARD GRID - KONSISTEN DENGAN FASILITAS ===== */
.alat-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px; }
.alat-card { 
    background: var(--card-bg); 
    border-radius: 16px; 
    border: 1px solid var(--border); 
    overflow: hidden; 
    transition: all .25s ease; 
    cursor: pointer; 
    position: relative; 
}
.alat-card:hover { 
    transform: translateY(-4px); 
    box-shadow: 0 12px 32px rgba(0,0,0,.12); 
    border-color: var(--orange); 
}
.alat-card:nth-child(odd) { background-color: #FFF7ED; }
.alat-card:nth-child(even) { background-color: #FFFFFF; }
.alat-card:hover { background-color: #FFEDD5 !important; }

.alat-card-photo-wrap { position: relative; width: 100%; aspect-ratio: 1 / 1; background: var(--border-lt); overflow: hidden; }
.alat-card-photo-wrap img { width: 100%; height: 100%; object-fit: cover; transition: transform .3s ease; display: block; }
.alat-card:hover .alat-card-photo-wrap img { transform: scale(1.05); }
.alat-card-photo-placeholder { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #FFF7ED 0%, #FFEDD5 100%); position: absolute; top: 0; left: 0; }
.alat-card-photo-placeholder i { font-size: 48px; color: var(--orange); opacity: .5; }

/* ===== STATUS BADGE - KONSISTEN DENGAN FASILITAS ===== */
.alat-card-badge { 
    position: absolute; 
    top: 8px; 
    left: 8px; 
    padding: 7px 16px; 
    border-radius: 20px; 
    font-size: 12px; 
    font-weight: 800; 
    text-transform: uppercase; 
    letter-spacing: .3px; 
    z-index: 2; 
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.badge-aktif { background: var(--green-lt); color: var(--green); border: 1px solid rgba(16,185,129,.2); }
.badge-nonaktif { background: var(--red-lt); color: var(--red); border: 1px solid rgba(239,68,68,.2); }
.badge-dot { width: 7px; height: 7px; border-radius: 50%; display: inline-block; }
.badge-aktif .badge-dot { background: var(--green); }
.badge-nonaktif .badge-dot { background: var(--red); }

/* ===== CARD ACTIONS - KONSISTEN DENGAN FASILITAS ===== */
.alat-card-actions { position: absolute; top: 8px; right: 8px; display: flex; gap: 6px; opacity: 0; transition: opacity .2s ease; z-index: 3; }
.alat-card:hover .alat-card-actions { opacity: 1; }
.alat-card-action-btn { 
    width: 38px; 
    height: 38px; 
    border-radius: 10px; 
    border: 1.5px solid transparent; 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    cursor: pointer; 
    font-size: 14px; 
    transition: all .25s cubic-bezier(.4,0,.2,1); 
    backdrop-filter: blur(4px); 
    text-decoration: none; 
    font-weight: 700;
}
.ac-btn-view { 
    background: linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%); 
    color: #1E40AF; 
    border-color: #BFDBFE;
}
.ac-btn-view:hover { 
    background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%); 
    color: #fff; 
    border-color: #3B82F6;
    transform: translateY(-2px); 
    box-shadow: 0 6px 20px rgba(59,130,246,.35);
}
.ac-btn-edit { 
    background: linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%); 
    color: #1E40AF; 
    border-color: #BFDBFE;
}
.ac-btn-edit:hover { 
    background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%); 
    color: #fff; 
    border-color: #3B82F6;
    transform: translateY(-2px); 
    box-shadow: 0 6px 20px rgba(59,130,246,.35);
}
.ac-btn-delete { 
    background: linear-gradient(135deg, #FEF2F2 0%, #FEE2E2 100%); 
    color: #DC2626; 
    border-color: #FECACA;
}
.ac-btn-delete:hover { 
    background: linear-gradient(135deg, #EF4444 0%, #DC2626 100%); 
    color: #fff; 
    border-color: #EF4444;
    transform: translateY(-2px); 
    box-shadow: 0 6px 20px rgba(239,68,68,.35);
}

.alat-card-info { padding: 16px 20px; }
.alat-card-name { font-size: 15px; font-weight: 700; color: var(--text); line-height: 1.3; margin-bottom: 8px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; min-height: 36px; }
.alat-card-price { font-family: 'Barlow Condensed', sans-serif; font-size: 20px; font-weight: 900; color: var(--shopee-orange); margin-bottom: 8px; }
.alat-card-meta { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.alat-card-stok { font-size: 12px; color: var(--muted); font-weight: 600; }
.alat-card-stok i { color: var(--orange); margin-right: 4px; }
.alat-card-toggle { display: flex; align-items: center; gap: 6px; }
.alat-card-toggle-label { font-size: 10px; font-weight: 700; color: var(--muted); text-transform: uppercase; }

/* ===== TOGGLE SWITCH - KONSISTEN DENGAN FASILITAS ===== */
.toggle-switch { position: relative; display: inline-flex; align-items: center; width: 44px; height: 24px; cursor: pointer; margin: 0; flex-shrink: 0; }
.toggle-switch input { opacity: 0; width: 0; height: 0; position: absolute; }
.toggle-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: var(--red); transition: all .3s cubic-bezier(.4, 0, .2, 1); border-radius: 24px; will-change: background-color; }
.toggle-slider::before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: all .3s cubic-bezier(.4, 0, .2, 1); border-radius: 50%; box-shadow: 0 2px 4px rgba(0,0,0,.2); will-change: transform; }
.toggle-switch input:checked + .toggle-slider { background-color: var(--green); }
.toggle-switch input:checked + .toggle-slider::before { transform: translateX(20px); }
.toggle-switch:hover .toggle-slider { opacity: .9; }

.empty-grid { grid-column: 1 / -1; text-align: center; padding: 80px 20px; color: var(--muted); }
.empty-grid i { font-size: 64px; margin-bottom: 20px; opacity: .3; display: block; }
.empty-grid div { font-size: 16px; font-weight: 700; }
.empty-grid p { font-size: 13px; font-weight: 500; margin-top: 8px; opacity: .7; }

.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.55); backdrop-filter: blur(6px); display: none; align-items: center; justify-content: center; z-index: 2000; }
.modal-overlay.open { display: flex; }
.modal-box { background: #fff; border-radius: 20px; width: 520px; max-height: 90vh; overflow-y: auto; box-shadow: 0 25px 60px rgba(0,0,0,.2); position: relative; }
.modal-header { padding: 28px 32px 20px; border-bottom: 1px solid var(--border); }
.modal-subtitle { font-size: 10px; font-weight: 800; color: var(--orange); text-transform: uppercase; margin-bottom: 6px; letter-spacing: .8px; }
.modal-title { font-family: 'Barlow Condensed', sans-serif; font-size: 22px; font-weight: 900; color: var(--text); }
.modal-body { padding: 24px 32px 32px; }
.modal-label { display: block; font-size: 11px; font-weight: 800; color: var(--muted); text-transform: uppercase; letter-spacing: .5px; margin-bottom: 8px; }
.modal-label .required { color: var(--red); margin-left: 2px; font-size: 14px; font-weight: 900; }
.modal-input { width: 100%; padding: 12px 14px; border: 1.5px solid var(--border); border-radius: 10px; font-size: 13px; font-family: 'Barlow', sans-serif; margin-bottom: 4px; outline: none; transition: all .2s; color: var(--text); }
.modal-input:focus { border-color: var(--orange); box-shadow: 0 0 0 3px var(--orange-lt); }
.modal-input::placeholder { color: #9CA3AF; }
.modal-input.error { border-color: var(--red); box-shadow: 0 0 0 3px var(--red-lt); }
.btn-submit { width: 100%; background: var(--orange); color: #fff; border: none; padding: 14px; border-radius: 10px; font-weight: 800; font-size: 13px; cursor: pointer; transition: all .2s; text-transform: uppercase; letter-spacing: .5px; display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 18px; }
.btn-submit:hover { background: var(--orange-dk); transform: translateY(-1px); box-shadow: 0 8px 20px rgba(255,69,0,.3); }
.btn-cancel { display: block; text-align: center; margin-top: 16px; color: var(--muted); text-decoration: none; font-size: 13px; font-weight: 700; transition: .2s; cursor: pointer; background: none; border: none; width: 100%; }
.btn-cancel:hover { color: var(--orange); }
.modal-close { position: absolute; top: 20px; right: 20px; width: 36px; height: 36px; border: none; background: var(--border-lt); border-radius: 10px; cursor: pointer; display: flex; align-items: center; justify-content: center; color: var(--muted); font-size: 16px; transition: all .2s; z-index: 10; }
.modal-close:hover { background: var(--red-lt); color: var(--red); }

/* ===== PHOTO UPLOAD AREA ===== */
.photo-upload-area { width: 100%; height: 140px; border: 2px dashed var(--border); border-radius: 12px; display: flex; flex-direction: column; align-items: center; justify-content: center; cursor: pointer; transition: all .2s ease; margin-bottom: 16px; position: relative; overflow: hidden; background: var(--border-lt); }
.photo-upload-area:hover { border-color: var(--orange); background: var(--orange-lt); }
.photo-upload-area.error { border-color: var(--red); background: var(--red-lt); }
.photo-upload-area.error:hover { border-color: var(--red); background: var(--red-lt); }
.photo-upload-area.has-image { border-style: solid; border-color: var(--orange); }
.photo-upload-area i.upload-icon { font-size: 28px; color: var(--orange); margin-bottom: 8px; }
.photo-upload-area p { font-size: 13px; font-weight: 600; color: var(--muted); text-align: center; }
.photo-upload-area input[type="file"] { position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%; z-index: 5; }
.photo-upload-preview { width: 100%; height: 100%; object-fit: cover; position: absolute; top: 0; left: 0; z-index: 2; }
.photo-upload-remove { position: absolute; top: 8px; right: 8px; width: 28px; height: 28px; background: rgba(239,68,68,.9); color: #fff; border: none; border-radius: 6px; cursor: pointer; font-size: 12px; display: flex; align-items: center; justify-content: center; z-index: 10; }
.upload-placeholder-inner { display: flex; flex-direction: column; align-items: center; justify-content: center; pointer-events: none; z-index: 1; }

.val-msg { font-size: 11px; color: var(--red); font-weight: 600; margin-bottom: 10px; display: none; min-height: 16px; }
.val-msg.show { display: block; }
.val-msg i { margin-right: 4px; }

/* ===== DETAIL MODAL - FOTO BULAT SEPERTI LAPANGAN ===== */
.detail-modal-box { width: 460px; border-radius: 24px; border: 1px solid var(--border); overflow-y: auto; }
.detail-photo-wrap { width: 120px; height: 120px; margin: 0 auto 16px auto; background: #ffffff; border-radius: 50%; overflow: hidden; position: relative; border: 3px solid var(--orange); box-shadow: 0 4px 16px rgba(255,69,0,.2); }
.detail-photo-wrap img { width: 100%; height: 100%; object-fit: cover; display: block; }
.detail-photo-placeholder { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #FFF7ED 0%, #FFEDD5 100%); }
.detail-photo-placeholder i { font-size: 40px; color: var(--orange); opacity: .6; }
.detail-name { font-family: 'Barlow Condensed', sans-serif; font-size: 26px; font-weight: 900; color: var(--text); line-height: 1.2; margin-bottom: 6px; text-transform: uppercase; text-align: center; }
.detail-price { font-family: 'Barlow Condensed', sans-serif; font-size: 30px; font-weight: 900; color: var(--shopee-orange); margin-bottom: 20px; border-bottom: 1px solid var(--border-lt); padding-bottom: 14px; text-align: center; }
.detail-info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 20px; }
.detail-info-item { background: #FAFBFD; border: 1px solid var(--border-lt); border-radius: 14px; padding: 14px; transition: all .2s ease; }
.detail-info-label { font-size: 10px; font-weight: 800; color: var(--muted); text-transform: uppercase; letter-spacing: .5px; margin-bottom: 6px; display: flex; align-items: center; gap: 6px; }
.detail-info-value { font-size: 18px; font-weight: 800; color: var(--text); }
.detail-status-badge { 
    display: inline-flex; 
    align-items: center; 
    gap: 6px; 
    padding: 7px 16px; 
    border-radius: 20px; 
    font-size: 12px; 
    font-weight: 800; 
    letter-spacing: .3px; 
    margin-bottom: 14px; 
    text-transform: uppercase; 
}
.badge-status-aktif { background: var(--green-lt); color: var(--green); border: 1px solid rgba(16,185,129,.2); }
.badge-status-nonaktif { background: var(--red-lt); color: var(--red); border: 1px solid rgba(239,68,68,.2); }
.detail-status-badge i { font-size: 8px; }

.detail-info-item:hover { background: #ffffff; border-color: var(--orange); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,.02); }
.detail-info-label i { color: var(--orange); font-size: 12px; }

/* ===== PAGINATION - KONSISTEN DENGAN FASILITAS ===== */
.pagination-wrap { 
    background: var(--card-bg); 
    border: 1px solid var(--border); 
    border-radius: 12px; 
    padding: 16px 24px; 
    display: flex; 
    align-items: center; 
    justify-content: space-between; 
    margin-bottom: 32px; 
}
.pagination-info { font-size: 12px; color: var(--muted); font-weight: 600; }
.pagination-info strong { color: var(--text); font-weight: 800; }
.pagination-nav { display: flex; align-items: center; gap: 4px; }
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
.page-btn.disabled { opacity: 0.4; cursor: not-allowed; pointer-events: none; }
.page-btn i { font-size: 11px; }
.page-ellipsis { display: inline-flex; align-items: center; justify-content: center; min-width: 36px; height: 36px; color: var(--muted); font-size: 13px; font-weight: 800; }

/* ===== FILTER - KONSISTEN DENGAN FASILITAS ===== */
.filter-dropdown-wrap { position: relative; display: inline-block; }
.btn-filter { 
    display: inline-flex; 
    align-items: center; 
    gap: 8px; 
    background-color: var(--orange); 
    color: #ffffff; 
    padding: 11px 20px; 
    border-radius: 10px; 
    font-size: 13px; 
    font-weight: 800; 
    text-transform: uppercase; 
    border: none; 
    cursor: pointer; 
    transition: all 0.2s; 
    box-shadow: 0 4px 12px rgba(255,69,0,0.2); 
}
.btn-filter:hover { 
    background-color: var(--orange-dk); 
    transform: translateY(-2px); 
    box-shadow: 0 6px 16px rgba(255,69,0,0.35); 
}
.btn-filter i.arrow-icon { font-size: 10px; transition: transform 0.3s; }
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
.filter-card.open { display: block; animation: slideFilter 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
@keyframes slideFilter { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
.filter-card h4 { font-size: 15px; font-weight: 800; color: var(--text); margin-bottom: 20px; text-align: left; }
.filter-group { margin-bottom: 16px; text-align: left; }
.filter-group label { display: block; font-size: 11px; font-weight: 800; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; }
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
.filter-input:focus { border-color: var(--orange); }
.filter-buttons { display: flex; gap: 10px; margin-top: 24px; }
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
.btn-filter-apply:hover { background: var(--orange-dk); }
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
.btn-filter-reset:hover { background: #E5E7EB; }

/* ===== TOMBOL TAMBAH - KONSISTEN DENGAN FASILITAS ===== */
.btn-add { 
    display: inline-flex; 
    align-items: center; 
    gap: 8px; 
    background-color: var(--text); 
    color: #fff; 
    padding: 11px 22px; 
    border-radius: 10px; 
    font-size: 13px; 
    font-weight: 800; 
    text-decoration: none; 
    text-transform: uppercase; 
    transition: all .2s ease; 
    border: none; 
    cursor: pointer; 
}
.btn-add:hover { 
    background-color: var(--orange); 
    transform: translateY(-2px); 
    box-shadow: 0 8px 20px rgba(255,69,0,.3); 
}
.btn-add i { font-size: 14px; }

#clock-display { display: flex; align-items: center; gap: 16px; }
.clock-time { font-family: 'Barlow Condensed', sans-serif; font-size: 26px; font-weight: 900; color: var(--orange); display: flex; align-items: center; gap: 6px; line-height: 1; }
.clock-colon { color: var(--orange); opacity: .5; animation: blink 1s infinite; }
@keyframes blink { 0%, 100% { opacity: .5; } 50% { opacity: 1; } }
.clock-divider { width: 1.5px; height: 28px; background-color: var(--border); }
.clock-date { font-family: 'Barlow', sans-serif; font-size: 13px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; }

html, body { scrollbar-width: none; -ms-overflow-style: none; }
html::-webkit-scrollbar, body::-webkit-scrollbar { display: none; }
body.swal2-shown, html.swal2-shown { padding-right: 0px !important; }

.modal-box {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
.modal-box::-webkit-scrollbar {
    display: none;
}

@media(max-width:768px){
    .sidebar{width:0;overflow:hidden;padding:0;}
    .main{margin-left:0;}
    .content{padding:20px;}
    .alat-grid{grid-template-columns:repeat(2,1fr);gap:12px;}
    .modal-box{width:90%;margin:20px;}
    .search-box{width:100%;}
    .action-bar{flex-direction:column;align-items:stretch;}
}
@media(max-width:480px){.alat-grid{grid-template-columns:1fr;}}
</style>
</head>
<body>
<!-- MODAL FORM TAMBAH/EDIT ALAT -->
<div class="modal-overlay <?= ($edit_data || $show_add) ? 'open' : '' ?>" id="modalAlat">
    <div class="modal-box">
        <button type="button" class="modal-close" onclick="closeModal()" title="Tutup"><i class="fa-solid fa-xmark"></i></button>
        <div class="modal-header">
            <div class="modal-subtitle">Kelola Alat</div>
            <div class="modal-title"><?= $edit_data ? 'Edit Alat' : 'Tambah Alat Baru' ?></div>
        </div>
        <div class="modal-body">
            <form method="POST" id="formAlat" enctype="multipart/form-data" action="alat.php" onsubmit="return validateForm()">
                <input type="hidden" name="save_alat" value="1">
                <?php if ($edit_data): ?>
                    <input type="hidden" name="edit_mode" value="1">
                    <input type="hidden" name="id_alat" value="<?= intval($edit_data['ID_Alat']) ?>">
                    <input type="hidden" name="edit_photo_path" value="<?= htmlspecialchars($edit_data['Photo_Alat'] ?? '') ?>">
                <?php endif; ?>

                <label class="modal-label">Foto Alat <span class="required">*</span> <span style="color:var(--muted);font-size:10px;">(Wajib, max 5MB)</span></label>
                <div class="photo-upload-area <?= ($edit_data && !empty($edit_data['Photo_Alat'])) ? 'has-image' : '' ?>" id="uploadArea">
                    <input type="file" name="photo_alat" id="photo_alat" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" onchange="handlePhotoUpload(this)" style="position:absolute;inset:0;opacity:0;cursor:pointer;z-index:5;">

                    <?php if ($edit_data && !empty($edit_data['Photo_Alat'])): ?>
                        <img src="<?= htmlspecialchars(getPhotoUrl($edit_data['Photo_Alat'])) ?>"
                             class="photo-upload-preview" id="previewImg" alt="Foto Alat"
                             onerror="this.style.display='none'; document.getElementById('uploadPlaceholder').style.display='flex';">
                        <button type="button" class="photo-upload-remove" id="removeBtn"
                                onclick="event.stopPropagation(); removePhoto();" title="Hapus Foto">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    <?php else: ?>
                        <img class="photo-upload-preview" id="previewImg" style="display:none;" alt="Preview">
                        <button type="button" class="photo-upload-remove" id="removeBtn"
                                onclick="event.stopPropagation(); removePhoto();" style="display:none;" title="Hapus Foto">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    <?php endif; ?>

                    <div class="upload-placeholder-inner" id="uploadPlaceholder"
                         style="display:<?= ($edit_data && !empty($edit_data['Photo_Alat'])) ? 'none' : 'flex' ?>; flex-direction:column; align-items:center;">
                        <i class="fa-solid fa-cloud-arrow-up upload-icon"></i>
                        <p>Klik untuk upload foto alat</p>
                        <p style="font-size:11px; margin-top:4px; opacity:.7;">JPG, PNG, GIF, WEBP (Max 5MB)</p>
                    </div>
                </div>
                <div class="val-msg" id="val-photo_alat"></div>

                <label class="modal-label">Nama Alat <span class="required">*</span></label>
                <input type="text" name="nama_alat" id="nama_alat" class="modal-input"
                       value="<?= htmlspecialchars($edit_data['Nama_Alat'] ?? '') ?>"
                       placeholder="Contoh: Bola Basket SNI" autocomplete="off" maxlength="25">
                <div class="val-msg" id="val-nama_alat"></div>

                <label class="modal-label">Stok <span class="required">*</span></label>
                <input type="number" name="stok" id="stok" class="modal-input"
                       value="<?= htmlspecialchars($edit_data['Stok'] ?? '') ?>"
                       placeholder="Contoh: 10" max="9999" autocomplete="off">
                <div class="val-msg" id="val-stok"></div>

                <label class="modal-label">Harga Jual <span class="required">*</span></label>
                <input type="number" name="harga_alat" id="harga_alat" class="modal-input"
                       value="<?= isset($edit_data['Harga_Alat']) ? intval($edit_data['Harga_Alat']) : '' ?>"
                       placeholder="Contoh: 150000" min="20000" autocomplete="off">
                <div class="val-msg" id="val-harga_alat"></div>

                <button type="submit" class="btn-submit" id="btnSubmit">
                    <i class="fa-solid fa-<?= $edit_data ? 'floppy-disk' : 'plus' ?>"></i>
                    <?= $edit_data ? 'Simpan Perubahan' : 'Tambah Alat' ?>
                </button>
                <button type="button" class="btn-cancel" onclick="closeModal()">Batal</button>
            </form>
        </div>
    </div>
</div>

<!-- MODAL DETAIL ALAT - FOTO BULAT SEPERTI LAPANGAN -->
<div class="modal-overlay <?= $show_detail ? 'open' : '' ?>" id="modalDetail">
    <div class="modal-box detail-modal-box">
        <button type="button" class="modal-close" onclick="closeModal()" title="Tutup"><i class="fa-solid fa-xmark"></i></button>
        <div class="modal-header" style="text-align: center; padding-bottom: 10px;">
            <div class="modal-subtitle">Detail Informasi</div>
            <div class="modal-title">Spesifikasi Alat</div>
        </div>
        <div class="modal-body" style="padding-top:10px;">
            <?php if ($detail_data): ?>
                <div class="detail-photo-wrap">
                    <?php
                    $detail_photo_url = getPhotoUrl($detail_data['Photo_Alat'] ?? '');
                    if (!empty($detail_photo_url)):
                    ?>
                        <img src="<?= htmlspecialchars($detail_photo_url) ?>"
                             alt="<?= htmlspecialchars($detail_data['Nama_Alat']) ?>"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <div class="detail-photo-placeholder" style="display:none;"><i class="fa-solid fa-toolbox"></i></div>
                    <?php else: ?>
                        <div class="detail-photo-placeholder"><i class="fa-solid fa-toolbox"></i></div>
                    <?php endif; ?>
                </div>

                <div style="text-align: center;">
                    <div class="detail-status-badge <?= $detail_data['Status'] == 1 ? 'badge-status-aktif' : 'badge-status-nonaktif' ?>">
                        <i class="fa-solid fa-circle"></i> <?= $detail_data['Status'] == 1 ? 'Alat Aktif' : 'Alat Nonaktif' ?>
                    </div>
                </div>

                <div class="detail-name"><?= htmlspecialchars($detail_data['Nama_Alat']) ?></div>
                <div class="detail-price"><?= rupiah($detail_data['Harga_Alat']) ?> <span style="font-size:14px;color:var(--muted);font-family:'Barlow';font-weight:600;">/ pcs</span></div>

                <div class="detail-info-grid">
                    <div class="detail-info-item">
                        <div class="detail-info-label"><i class="fa-solid fa-boxes-stacked"></i> Stok Tersedia</div>
                        <div class="detail-info-value"><?= intval($detail_data['Stok']) ?> <span style="font-size:11px; font-weight:500; color:var(--muted);">PCS</span></div>
                    </div>
                    <div class="detail-info-item">
                        <div class="detail-info-label"><i class="fa-solid fa-tag"></i> Harga Satuan</div>
                        <div class="detail-info-value" style="color:var(--shopee-orange);"><?= rupiah($detail_data['Harga_Alat']) ?></div>
                    </div>
                </div>

                <button type="button" onclick="closeModal()" class="btn-submit" style="background:#0D1117;">
                    <i class="fa-solid fa-arrow-left"></i> Kembali
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- SIDEBAR -->
<aside class="sidebar">
    <a href="../dashboard/view_admin.php" class="sb-brand">
        <div class="sb-icon"><i class="fa-solid fa-basketball"></i></div>
        <div>
            <div class="sb-brand-name">HOOP BALL</div>
            <div class="sb-brand-sub">Sistem Managemen</div>
        </div>
    </a>
    <div class="sb-section-label">Operasional</div>
    <nav>
        <a href="../dashboard/view_admin.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-house"></i></div>Dashboard</a>
        <a href="customer.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-users"></i></div>Kelola Customer</a>
        <a href="lapangan.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-layer-group"></i></div>Kelola Lapangan</a>
        <a href="fasilitas_lapangan.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-list-check"></i></div>Kelola Fasilitas</a>
        <a href="jadwal.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-calendar-days"></i></div>Kelola Jadwal</a>
        <a href="promo.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-tags"></i></div>Kelola Promo</a>
        <a href="tipe_member.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-id-card"></i></div>Kelola Tipe Member</a>
        <a href="alat.php" class="sb-link active"><div class="sb-icon-wrap"><i class="fa-solid fa-toolbox"></i></div>Kelola Alat</a>
    </nav>
    <div class="sb-section-label">Transaksi</div>
    <nav>
        <a href="../transaksi/booking.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-calendar-check"></i></div>Kelola Booking</a>
        <a href="../transaksi/langganan.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-crown"></i></div>Kelola Langganan</a>
        <a href="../transaksi/pembelian.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-cart-shopping"></i></div>Kelola Pembelian Alat</a>
        <a href="../transaksi/pembatalan.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-ban"></i></div>Kelola Pembatalan</a>
    </nav>
    <div class="sb-section-label">Akun</div>
    <a href="../profile/profile.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-id-badge"></i></div>Profil Saya</a>
    <div class="sb-bottom">
        <div class="sb-user">
            <div class="sb-avatar">
                <?php if (!empty($profile_photo)): ?>
                    <img src="<?= htmlspecialchars($profile_photo) ?>" alt="Profile">
                <?php else: ?>
                    <i class="fa-solid fa-user"></i>
                <?php endif; ?>
            </div>
            <div>
                <div class="sb-user-name"><?= strtoupper(htmlspecialchars($nama)) ?></div>
                <div class="sb-user-role"><?= strtoupper(htmlspecialchars($role)) ?></div>
            </div>
            <a href="../login/logout.php" class="sb-logout" title="Keluar"><i class="fa-solid fa-right-from-bracket"></i></a>
        </div>
    </div>
</aside>

<!-- MAIN CONTENT -->
<main class="main">
    <header class="topbar">
        <div class="topbar-left">
            <div class="topbar-title">Kelola Alat</div>
            <div class="topbar-breadcrumb">Operasional / Kelola Alat</div>
        </div>
        <div class="topbar-right">
            <div id="clock-display">
                <div class="clock-time">
                    <span id="clock-h">00</span><span class="clock-colon">:</span>
                    <span id="clock-m">00</span><span class="clock-colon">:</span>
                    <span id="clock-s">00</span>
                </div>
                <div class="clock-divider"></div>
                <div class="clock-date" id="full-date">MEMUAT...</div>
            </div>
            <a href="#" class="topbar-btn"><i class="fa-solid fa-magnifying-glass"></i></a>
            <a href="#" class="topbar-btn">
                <i class="fa-solid fa-bell"></i>
                <?php if ($total_pending > 0): ?><span class="notif-dot"></span><?php endif; ?>
            </a>
            <div class="dropdown-wrap" id="userDropdown">
                <div class="topbar-user" onclick="toggleUserDropdown()">
                    <div class="t-avatar">
                        <?php if (!empty($profile_photo)): ?>
                            <img src="<?= htmlspecialchars($profile_photo) ?>" alt="Profile">
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
                <div class="page-title">Kelola Alat</div>
            </div>
            <div class="stat-chips">
                <div class="stat-chip chip-green"><i class="fa-solid fa-circle-check"></i> AKTIF <span class="chip-val"><?= $aktif_count ?></span></div>
                <div class="stat-chip chip-red"><i class="fa-solid fa-circle-xmark"></i> NONAKTIF <span class="chip-val"><?= $nonaktif_count ?></span></div>
                <div class="stat-chip chip-blue"><i class="fa-solid fa-toolbox"></i> TOTAL <span class="chip-val"><?= $total_alat ?></span></div>
            </div>
        </div>

        <div class="action-bar">
            <div class="search-box">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="src" placeholder="Cari alat..." onkeyup="searchGrid()">
            </div>
            <div style="display:flex;gap:12px;align-items:center;">
                <div class="filter-dropdown-wrap">
                    <button class="btn-filter" id="btnFilterToggle">
                        <i class="fa-solid fa-filter"></i> Filter <i class="fa-solid fa-chevron-down arrow-icon"></i>
                    </button>
                    <div class="filter-card" id="filterCard">
                        <h4><i class="fa-solid fa-sliders" style="margin-right:8px;color:var(--orange);"></i>Filter Data</h4>
                        <form method="GET" action="alat.php">
                            <div class="filter-group">
                                <label>Status</label>
                                <select name="f_status" class="filter-input">
                                    <option value="">Semua Status</option>
                                    <option value="1" <?= ($_GET['f_status'] ?? '') === '1' ? 'selected' : '' ?>>AKTIF</option>
                                    <option value="0" <?= ($_GET['f_status'] ?? '') === '0' ? 'selected' : '' ?>>NONAKTIF</option>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label>Urutkan</label>
                                <select name="f_sort" class="filter-input">
                                    <option value="nama_asc"  <?= ($_GET['f_sort'] ?? '') === 'nama_asc'  ? 'selected' : '' ?>>Nama A-Z</option>
                                    <option value="stok_desc" <?= ($_GET['f_sort'] ?? '') === 'stok_desc' ? 'selected' : '' ?>>Stok Terbanyak</option>
                                    <option value="harga_desc"<?= ($_GET['f_sort'] ?? '') === 'harga_desc'? 'selected' : '' ?>>Harga Termahal</option>
                                    <option value="harga_asc" <?= ($_GET['f_sort'] ?? '') === 'harga_asc' ? 'selected' : '' ?>>Harga Termurah</option>
                                </select>
                            </div>
                            <div class="filter-buttons">
                                <button type="button" class="btn-filter-reset" onclick="resetFilter()"><i class="fa-solid fa-rotate-left"></i> Reset</button>
                                <button type="submit" class="btn-filter-apply"><i class="fa-solid fa-check"></i> Terapkan</button>
                            </div>
                        </form>
                    </div>
                </div>
                <a href="alat.php?add=1" class="btn-add"><i class="fa-solid fa-plus"></i>Tambah</a>
            </div>
        </div>

        <!-- GRID KARTU ALAT -->
        <div class="alat-grid" id="alatGrid">
        <?php
        $has_data = false;
        if ($query):
            while ($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)):
                $has_data = true;
                $photo_url = getPhotoUrl($row['Photo_Alat'] ?? '');
                $is_aktif = intval($row['Status']) === 1;
        ?>
            <div class="alat-card" data-name="<?= strtolower(htmlspecialchars($row['Nama_Alat'])) ?>">
                <div class="alat-card-photo-wrap" onclick="window.location.href='?detail_id=<?= intval($row['ID_Alat']) ?>'">
                    <div class="alat-card-photo-placeholder">
                        <i class="fa-solid fa-toolbox"></i>
                    </div>
                    <?php if (!empty($photo_url)): ?>
                        <img src="<?= htmlspecialchars($photo_url) ?>"
                             alt="<?= htmlspecialchars($row['Nama_Alat']) ?>"
                             loading="lazy"
                             style="position:absolute;top:0;left:0;width:100%;height:100%;object-fit:cover;z-index:1;"
                             onerror="this.style.display='none';">
                    <?php endif; ?>
                    <span class="alat-card-badge <?= $is_aktif ? 'badge-aktif' : 'badge-nonaktif' ?>" style="z-index:2;">
                        <span class="badge-dot"></span> <?= $is_aktif ? 'AKTIF' : 'NONAKTIF' ?>
                    </span>
                    <div class="alat-card-actions" style="z-index:3;">
                        <a href="?detail_id=<?= intval($row['ID_Alat']) ?>"
                           class="alat-card-action-btn ac-btn-view" title="Lihat Detail"
                           onclick="event.stopPropagation()"><i class="fa-solid fa-eye"></i></a>
                        <a href="?edit_id=<?= intval($row['ID_Alat']) ?>"
                           class="alat-card-action-btn ac-btn-edit" title="Edit Alat"
                           onclick="event.stopPropagation()"><i class="fa-solid fa-pen-to-square"></i></a>
                        <button type="button"
                                onclick="event.stopPropagation(); doDelete(<?= intval($row['ID_Alat']) ?>, '<?= htmlspecialchars($row['Nama_Alat'], ENT_QUOTES) ?>')"
                                class="alat-card-action-btn ac-btn-delete" title="Hapus Alat">
                            <i class="fa-solid fa-trash-can"></i>
                        </button>
                    </div>
                </div>
                <div class="alat-card-info">
                    <div class="alat-card-name"><?= htmlspecialchars($row['Nama_Alat']) ?></div>
                    <div class="alat-card-price"><?= rupiah($row['Harga_Alat']) ?></div>
                    <div class="alat-card-meta">
                        <span class="alat-card-stok">
                            <i class="fa-solid fa-boxes-stacked"></i><?= intval($row['Stok']) ?> tersedia
                        </span>
                        <div class="alat-card-toggle">
                            <span class="alat-card-toggle-label"><?= $is_aktif ? 'ON' : 'OFF' ?></span>
                            <label class="toggle-switch" title="<?= $is_aktif ? 'Nonaktifkan' : 'Aktifkan' ?>">
                                <input type="checkbox" <?= $is_aktif ? 'checked' : '' ?>
                                       onchange="confirmToggle('<?= intval($row['ID_Alat']) ?>', '<?= htmlspecialchars($row['Nama_Alat'], ENT_QUOTES) ?>', <?= intval($row['Status']) ?>, event)">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        <?php
            endwhile;
        endif;
        if (!$has_data): ?>
            <div class="empty-grid">
                <i class="fa-solid fa-toolbox"></i>
                <div>Belum ada data alat</div>
                <p>Klik "+ Tambah Alat" untuk menambahkan alat baru</p>
            </div>
        <?php endif; ?>
        </div>

        <!-- PAGINATION -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination-wrap">
            <div class="pagination-info">
                Menampilkan <strong><?= (($page-1)*$limit)+1 ?></strong> - <strong><?= min($page*$limit,$total_data) ?></strong>
                dari <strong><?= $total_data ?></strong> data
            </div>
            <div class="pagination-nav">
                <a href="?page=1<?= $filter_url ?>" class="page-btn <?= $page<=1?'disabled':'' ?>"><i class="fa-solid fa-angles-left"></i></a>
                <a href="?page=<?= $page-1 ?><?= $filter_url ?>" class="page-btn <?= $page<=1?'disabled':'' ?>"><i class="fa-solid fa-angle-left"></i></a>
                <?php for($i=max(1,$page-2);$i<=min($total_pages,$page+2);$i++): ?>
                    <a href="?page=<?= $i ?><?= $filter_url ?>" class="page-btn <?= $i==$page?'active':'' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <a href="?page=<?= $page+1 ?><?= $filter_url ?>" class="page-btn <?= $page>=$total_pages?'disabled':'' ?>"><i class="fa-solid fa-angle-right"></i></a>
                <a href="?page=<?= $total_pages ?><?= $filter_url ?>" class="page-btn <?= $page>=$total_pages?'disabled':'' ?>"><i class="fa-solid fa-angles-right"></i></a>
            </div>
        </div>
        <?php else: ?>
        <div class="pagination-wrap">
            <div class="pagination-info">
                Menampilkan <strong>1</strong> - <strong><?= $total_data ?></strong>
                dari <strong><?= $total_data ?></strong> data
            </div>
        </div>
        <?php endif; ?>
    </div>
</main>
<script>
function updateClock() {
    var now = new Date();
    var h = String(now.getHours()).padStart(2,'0');
    var m = String(now.getMinutes()).padStart(2,'0');
    var s = String(now.getSeconds()).padStart(2,'0');
    var hEl = document.getElementById('clock-h');
    var mEl = document.getElementById('clock-m');
    var sEl = document.getElementById('clock-s');
    var dEl = document.getElementById('full-date');
    if(hEl) hEl.textContent = h;
    if(mEl) mEl.textContent = m;
    if(sEl) sEl.textContent = s;
    var days = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    var months = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    if(dEl) dEl.textContent = days[now.getDay()] + ', ' + now.getDate() + ' ' + months[now.getMonth()] + ' ' + now.getFullYear();
}
updateClock();
setInterval(updateClock, 1000);

function closeModal() {
    window.location.href = 'alat.php';
}

function handlePhotoUpload(input) {
    if (!input.files || !input.files[0]) return;

    var uploadArea = document.getElementById('uploadArea');
    var valPhoto = document.getElementById('val-photo_alat');
    if (uploadArea) uploadArea.classList.remove('error');
    if (valPhoto) { valPhoto.classList.remove('show'); valPhoto.innerHTML = ''; }

    var file = input.files[0];
    if (file.size > 5 * 1024 * 1024) {
        Swal.fire({
            icon: 'error',
            title: 'File Terlalu Besar',
            text: 'Ukuran file maksimal 5MB!',
            confirmButtonColor: '#FF4500'
        });
        input.value = '';
        return;
    }
    var reader = new FileReader();
    reader.onload = function(e) {
        var previewImg = document.getElementById('previewImg');
        var uploadPlaceholder = document.getElementById('uploadPlaceholder');
        var uploadArea = document.getElementById('uploadArea');
        var removeBtn = document.getElementById('removeBtn');
        if (previewImg) {
            previewImg.src = e.target.result;
            previewImg.style.display = 'block';
        }
        if (uploadArea) uploadArea.classList.add('has-image');
        if (uploadPlaceholder) uploadPlaceholder.style.display = 'none';
        if (removeBtn) removeBtn.style.display = 'flex';
    };
    reader.readAsDataURL(file);
}

function removePhoto() {
    var previewImg = document.getElementById('previewImg');
    var uploadPlaceholder = document.getElementById('uploadPlaceholder');
    var fileInput = document.getElementById('photo_alat');
    var uploadArea = document.getElementById('uploadArea');
    var removeBtn = document.getElementById('removeBtn');
    var valPhoto = document.getElementById('val-photo_alat');

    if (fileInput) fileInput.value = '';
    if (previewImg) {
        previewImg.src = '';
        previewImg.style.display = 'none';
    }
    if (uploadArea) {
        uploadArea.classList.remove('has-image');
        var isEditMode = document.querySelector('input[name="edit_mode"]') !== null;
        var editPhotoPath = document.querySelector('input[name="edit_photo_path"]');
        if (isEditMode && editPhotoPath && editPhotoPath.value) {
            uploadArea.classList.add('error');
            if (valPhoto) {
                valPhoto.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Foto alat wajib diupload.';
                valPhoto.classList.add('show');
            }
            editPhotoPath.value = '';
        }
    }
    if (uploadPlaceholder) uploadPlaceholder.style.display = 'flex';
    if (removeBtn) removeBtn.style.display = 'none';
}

function searchGrid() {
    var filter = document.getElementById('src').value.toLowerCase();
    var cards = document.querySelectorAll('.alat-card');
    cards.forEach(function(card) {
        var name = card.getAttribute('data-name') || '';
        card.style.display = name.indexOf(filter) > -1 ? '' : 'none';
    });
}

function validateForm() {
    var valid = true;
    document.querySelectorAll('.modal-input').forEach(function(el) { el.classList.remove('error'); });
    document.querySelectorAll('.val-msg').forEach(function(el) { el.classList.remove('show'); el.innerHTML = ''; });

    var nama = document.getElementById('nama_alat');
    var valNama = document.getElementById('val-nama_alat');
    if (nama && valNama) {
        var v = nama.value.trim();
        var errNama = '';
        if (v === '') errNama = 'Nama alat wajib diisi.';
        else if (v.length < 3) errNama = 'Nama alat minimal 3 karakter.';
        else if (v.length > 25) errNama = 'Nama alat maksimal 25 karakter.';
        else if (/^\d+$/.test(v)) errNama = 'Nama alat tidak boleh hanya angka.';
        else if (!/^[a-zA-Z0-9\s\-\_]+$/.test(v)) errNama = 'Nama alat hanya boleh huruf, angka, spasi, strip, dan underscore.';
        if (errNama) {
            nama.classList.add('error');
            valNama.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> ' + errNama;
            valNama.classList.add('show');
            valid = false;
        }
    }

    var stok = document.getElementById('stok');
    var valStok = document.getElementById('val-stok');
    if (stok && valStok) {
        var vs = stok.value.trim();
        var errStok = '';
        if (vs === '') errStok = 'Stok wajib diisi.';
        else if (!/^[0-9]+$/.test(vs)) errStok = 'Stok harus di atas 0.';
        else if (parseInt(vs) <= 0) errStok = 'Stok tidak boleh 0 atau kurang dari 0.';
        else if (parseInt(vs) > 9999) errStok = 'Stok maksimal 9999.';
        if (errStok) {
            stok.classList.add('error');
            valStok.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> ' + errStok;
            valStok.classList.add('show');
            valid = false;
        }
    }

    var harga = document.getElementById('harga_alat');
    var valHarga = document.getElementById('val-harga_alat');
    if (harga && valHarga) {
        var vh = harga.value.trim();
        var errHarga = '';
        if (vh === '') errHarga = 'Harga jual wajib diisi.';
        else if (isNaN(vh)) errHarga = 'Harga jual harus berupa angka.';
        else if (parseFloat(vh) < 20000) errHarga = 'Harga jual minimal Rp 20.000.';
        else if (parseFloat(vh) > 999999999) errHarga = 'Harga jual terlalu besar.';
        if (errHarga) {
            harga.classList.add('error');
            valHarga.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> ' + errHarga;
            valHarga.classList.add('show');
            valid = false;
        }
    }

    var photoInput = document.getElementById('photo_alat');
    var previewImg = document.getElementById('previewImg');
    var uploadArea = document.getElementById('uploadArea');
    var valPhoto = document.getElementById('val-photo_alat');
    var isEditMode = document.querySelector('input[name="edit_mode"]') !== null;
    var hasExistingPhoto = previewImg && previewImg.style.display !== 'none' && previewImg.src && previewImg.src !== '' && !previewImg.src.includes('data:image');

    var photoError = '';
    if (!isEditMode && (!photoInput || !photoInput.files || photoInput.files.length === 0)) {
        photoError = 'Foto alat wajib diupload.';
    } else if (isEditMode && !hasExistingPhoto && (!photoInput || !photoInput.files || photoInput.files.length === 0)) {
        photoError = 'Foto alat wajib diupload.';
    }

    if (photoError) {
        if (uploadArea) uploadArea.classList.add('error');
        if (valPhoto) {
            valPhoto.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> ' + photoError;
            valPhoto.classList.add('show');
        }
        valid = false;
    } else {
        if (uploadArea) uploadArea.classList.remove('error');
        if (valPhoto) valPhoto.classList.remove('show');
    }

    if (!valid) return false;

    var btn = document.getElementById('btnSubmit');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Memproses...';
    }
    return true;
}

document.addEventListener('DOMContentLoaded', function() {
    var namaAlat = document.getElementById('nama_alat');
    if (namaAlat) {
        namaAlat.addEventListener('input', function() {
            var valNama = document.getElementById('val-nama_alat');
            var v = this.value.trim();
            this.classList.remove('error'); valNama.classList.remove('show');
            if (v.length > 0 && v.length < 3) {
                this.classList.add('error');
                valNama.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Nama alat minimal 3 karakter.';
                valNama.classList.add('show');
            } else if (v.length > 25) {
                this.classList.add('error');
                valNama.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Nama alat maksimal 25 karakter.';
                valNama.classList.add('show');
            }
        });
    }

    var stokEl = document.getElementById('stok');
    if (stokEl) {
        stokEl.addEventListener('input', function() {
            var valStok = document.getElementById('val-stok');
            var v = this.value.trim();
            this.classList.remove('error'); valStok.classList.remove('show');
            if (v !== '' && /^[0-9]+$/.test(v)) {
                if (parseInt(v) <= 0) {
                    this.classList.add('error');
                    valStok.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Stok tidak boleh 0 atau kurang dari 0.';
                    valStok.classList.add('show');
                } else if (parseInt(v) > 9999) {
                    this.classList.add('error');
                    valStok.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Stok maksimal 9999.';
                    valStok.classList.add('show');
                }
            }
        });
    }

    var hargaEl = document.getElementById('harga_alat');
    if (hargaEl) {
        hargaEl.addEventListener('input', function() {
            var valHarga = document.getElementById('val-harga_alat');
            var v = this.value.trim();
            this.classList.remove('error'); valHarga.classList.remove('show');
            if (v !== '' && !isNaN(v)) {
                if (parseFloat(v) < 20000) {
                    this.classList.add('error');
                    valHarga.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Harga jual minimal Rp 20.000.';
                    valHarga.classList.add('show');
                }
            }
        });
    }

    var urlParams = new URLSearchParams(window.location.search);
    var status = urlParams.get('status');
    var msg = urlParams.get('msg');

    if (status && msg) {
        var isSuccess = status === 'success';
        Swal.fire({
            icon: isSuccess ? 'success' : 'error',
            title: isSuccess ? 'Berhasil!' : 'Gagal!',
            text: msg,
            timer: 3000,
            showConfirmButton: false,
            toast: true,
            position: 'top-end',
            timerProgressBar: true,
            showCloseButton: true,
            didOpen: function(toast) {
                toast.addEventListener('mouseenter', Swal.stopTimer);
                toast.addEventListener('mouseleave', Swal.resumeTimer);
            }
        });
        var cleanUrl = window.location.pathname;
        window.history.replaceState({}, document.title, cleanUrl);
    }

    var btnFilterToggle = document.getElementById('btnFilterToggle');
    var filterCard = document.getElementById('filterCard');
    if (btnFilterToggle && filterCard) {
        btnFilterToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            filterCard.classList.toggle('open');
        });
        filterCard.addEventListener('click', function(e) { e.stopPropagation(); });
        document.addEventListener('click', function() { filterCard.classList.remove('open'); });
    }
});

function toggleUserDropdown() {
    var dd = document.getElementById('userDropdown');
    if (dd) dd.classList.toggle('active');
}
document.addEventListener('click', function(e) {
    var dd = document.getElementById('userDropdown');
    if (dd && !dd.contains(e.target)) dd.classList.remove('active');
});

function confirmToggle(id, name, currentStatus, event) {
    var checkbox = event.target;
    var newStatus = currentStatus === 1 ? 0 : 1;
    var statusText = newStatus === 1 ? 'Aktif' : 'Nonaktif';
    var icon = newStatus === 1 ? 'success' : 'warning';
    var confirmColor = newStatus === 1 ? '#10B981' : '#EF4444';

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
    }).then(function(result) {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Memproses...',
                text: 'Mengubah status alat',
                allowOutsideClick: false,
                didOpen: function() {
                    Swal.showLoading();
                }
            });
            setTimeout(function() {
                window.location.href = '?toggle_id=' + id + '&s=' + currentStatus;
            }, 600);
        } else {
            checkbox.checked = !checkbox.checked;
        }
    });
}

function doDelete(id, name) {
    Swal.fire({
        title: 'Hapus Alat?',
        html: 'Anda akan menghapus alat <strong style="color:var(--orange);">' + name + '</strong><br><span style="font-size:12px;color:var(--muted);">Data akan dihapus secara permanen.</span>',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#EF4444',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal',
        reverseButtons: true
    }).then(function(result) {
        if (result.isConfirmed) {
            Swal.fire({ 
                title: 'Menghapus...', 
                allowOutsideClick: false, 
                didOpen: function() { Swal.showLoading(); } 
            });
            setTimeout(function() {
                window.location.href = 'alat.php?delete_id=' + id;
            }, 500);
        }
    });
}

function resetFilter() {
    window.location.href = 'alat.php';
}
</script>
</body>
</html>