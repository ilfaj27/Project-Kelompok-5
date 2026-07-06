<?php
ob_start();
session_start();
include '../includes/config.php';
include '../includes/helpers.php';

if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'karyawan' && $_SESSION['role'] !== 'pemilik')) {
    echo "<script>alert('Akses Ditolak!'); window.location='../dashboard/dashboard.php';</script>";
    exit();
}

include '../includes/auth_profile.php';

$current_page = 'lapangan';
$topbar_title = 'Kelola Lapangan';
$topbar_breadcrumb = 'Operasional / Lapangan';

function getLastSqlError($conn)
{
    $errors = sqlsrv_errors(SQLSRV_ERR_ALL);
    if (!empty($errors) && isset($errors[0]['message'])) {
        return $errors[0]['message'];
    }
    return 'Unknown database error';
}

function getPhotoUrl($photo_path)
{
    if (empty($photo_path))
        return '';
    return '../' . ltrim(str_replace('../', '', $photo_path), '/');
}

function getUploadDirectory()
{
    $upload_dir = '../asset/image/';
    if (!is_dir($upload_dir)) {
        @mkdir($upload_dir, 0755, true);
    }
    return $upload_dir;
}

function processPhotoUpload($file, $fallback_photo = '')
{
    $upload_dir = getUploadDirectory();
    if (!isset($file) || empty($file['name'])) {
        return $fallback_photo;
    }

    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_ext = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    if (!in_array($file_ext, $allowed_ext)) {
        return $fallback_photo;
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        return $fallback_photo;
    }
    $new_file_name = 'lapangan_' . time() . '_' . uniqid() . '.' . $file_ext;
    $target_path = $upload_dir . $new_file_name;
    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        return 'asset/image/' . $new_file_name;
    }
    return $fallback_photo;
}

// --- (AJAX Handler Detail & Edit) ---
if (isset($_GET['ajax_detail_id'])) {
    header('Content-Type: application/json');
    $r = safeQuery($conn, "EXEC dbo.sp_GetLapanganDetail ?", [intval($_GET['ajax_detail_id'])]);
    if ($r) {
        $detail_data = safeFetch($r);
        if ($detail_data) {
            $detail_data['Harga_Sewa_Rupiah'] = rupiah($detail_data['Harga_Sewa']);
            $detail_data['Photo_Lapangan_Url'] = getPhotoUrl($detail_data['Photo_Lapangan']);

            // AMBIL DAFTAR FASILITAS YANG TERPASANG (Result Set Ke-2 dari SP)
            $assigned_facilities = [];
            if (sqlsrv_next_result($r)) {
                while ($fac_row = safeFetch($r)) {
                    $assigned_facilities[] = [
                        'ID_Fasilitas' => intval($fac_row['ID_Fasilitas']),
                        'Nama_Fasilitas' => $fac_row['Nama_Fasilitas'],
                        'Jumlah_Digunakan' => intval($fac_row['Jumlah_Digunakan'])
                    ];
                }
            }
            $detail_data['Fasilitas'] = $assigned_facilities;

            echo json_encode(['status' => 'success', 'data' => $detail_data]);
        } else {
            echo json_encode(['status' => 'error', 'msg' => 'Data lapangan tidak ditemukan.']);
        }
    } else {
        echo json_encode(['status' => 'error', 'msg' => 'Gagal mengambil data dari database.']);
    }
    exit();
}

// PROSES SIMPAN (TAMBAH/EDIT)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_lapangan'])) {
    $id = isset($_POST['id_lap']) ? intval($_POST['id_lap']) : 0;
    $nama_lapangan = trim($_POST['nama_lapangan'] ?? '');
    $harga_raw = preg_replace('/[^0-9]/', '', trim($_POST['harga_sewa'] ?? ''));
    $edit_mode = isset($_POST['edit_mode']) && $_POST['edit_mode'] == '1';
    $edit_photo_path = trim($_POST['edit_photo_path'] ?? '');

    $errors = [];
    if ($nama_lapangan === '') {
        $errors[] = 'Nama lapangan wajib diisi.';
    } elseif (strlen($nama_lapangan) < 3) {
        $errors[] = 'Nama lapangan minimal 3 karakter.';
    } elseif (strlen($nama_lapangan) > 50) {
        $errors[] = 'Nama lapangan maksimal 50 karakter.';
    } elseif (!preg_match('/^[a-zA-Z\s]+$/', $nama_lapangan)) {
        $errors[] = 'Nama lapangan hanya boleh huruf dan spasi.';
    }
    if ($harga_raw === '') {
        $errors[] = 'Harga sewa wajib diisi dan berupa angka.';
    } else {
        $harga_num = intval($harga_raw);
        if ($harga_num < 10000) {
            $errors[] = 'Harga sewa minimal Rp 10.000.';
        } elseif ($harga_num > 10000000) {
            $errors[] = 'Harga sewa maksimal Rp 10.000.000.';
        }
    }

    if (empty($errors)) {
        $sql_check = "EXEC dbo.sp_CheckLapanganDuplicate ?, ?";
        $q_check = safeQuery($conn, $sql_check, [$nama_lapangan, $id]);
        if ($q_check && safeFetch($q_check)) {
            $errors[] = 'Nama lapangan sudah terdaftar.';
        }
    }

    if (empty($errors)) {
        $harga_sewa = number_format(floatval($harga_raw), 2, '.', '');
        $photo_lapangan = processPhotoUpload($_FILES['photo_lapangan'] ?? null, $edit_photo_path);

        // Mengumpulkan pilihan fasilitas dari dropdown multi-select (tanpa input qty)
        $selected_facilities = [];
        if (isset($_POST['use_facility']) && is_array($_POST['use_facility'])) {
            foreach ($_POST['use_facility'] as $id_fac) {
                $id_fac = intval($id_fac);
                if ($id_fac > 0) {
                    $selected_facilities[] = [
                        'id' => $id_fac,
                        'qty' => 1 // Diatur default ke 1 karena pilihan jumlah stok ditiadakan
                    ];
                }
            }
        }
        $facilities_json = !empty($selected_facilities) ? json_encode($selected_facilities) : null;

        // Deklarasi parameter dasar (digunakan oleh Create dan Update)
        $params = [$nama_lapangan, $harga_sewa, $photo_lapangan, $nama, $facilities_json];

        if ($edit_mode && $id > 0) {
            $sql = "EXEC dbo.sp_UpdateLapangan ?, ?, ?, ?, ?, ?";
            array_unshift($params, $id); // Menyisipkan $id ke urutan pertama di dalam array $params
        } else {
            $sql = "EXEC dbo.sp_CreateLapangan ?, ?, ?, ?, ?";
        }

        $result = safeQuery($conn, $sql, $params);
        if ($result !== false) {
            ob_end_clean();
            $msg = $edit_mode ? 'Data lapangan berhasil diperbarui!' : 'Lapangan baru berhasil ditambahkan!';
            header("Location: lapangan.php?status=success&msg=" . urlencode($msg));
            exit();
        } else {
            $db_error = getLastSqlError($conn);
            header("Location: lapangan.php?status=error&msg=" . urlencode("Gagal menyimpan data: " . $db_error));
            exit();
        }
    } else {
        header("Location: lapangan.php?status=error&msg=" . urlencode(implode(' | ', $errors)));
        exit();
    }
}

// TOGGLE STATUS
if (isset($_GET['toggle_id'])) {
    $toggle_id = intval($_GET['toggle_id']);
    $current_status = intval($_GET['s']);
    $s_baru = ($current_status == 1) ? 0 : 1;
    $result = safeQuery($conn, "EXEC dbo.sp_UpdateStatusLapangan ?, ?, ?", [$toggle_id, $s_baru, $nama]);
    if ($result !== false) {
        ob_end_clean();
        $msg = ($s_baru == 1) ? 'Lapangan berhasil diaktifkan!' : 'Lapangan berhasil dinonaktifkan!';
        header("Location: lapangan.php?status=success&msg=" . urlencode($msg));
    } else {
        ob_end_clean();
        header("Location: lapangan.php?status=error&msg=" . urlencode('Gagal mengubah status lapangan: ' . getLastSqlError($conn)));
    }
    exit();
}

// DELETE (SOFT DELETE)
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $stmt_nama = safeQuery($conn, "EXEC dbo.sp_GetLapanganDetail ?", [$delete_id]);
    $nama_lap_deleted = '';
    if ($stmt_nama) {
        $row_nama = safeFetch($stmt_nama);
        if ($row_nama)
            $nama_lap_deleted = $row_nama['Nama_Lapangan'];
    }
    $result = safeQuery($conn, "EXEC dbo.sp_DeleteLapangan ?, ?", [$delete_id, $nama]);
    if ($result !== false) {
        ob_end_clean();
        $msg = !empty($nama_lap_deleted) ? 'Lapangan "' . $nama_lap_deleted . '" berhasil dihapus!' : 'Lapangan berhasil dihapus!';
        header("Location: lapangan.php?status=success&msg=" . urlencode($msg));
    } else {
        ob_end_clean();
        header("Location: lapangan.php?status=error&msg=" . urlencode('Gagal menghapus lapangan: ' . getLastSqlError($conn)));
    }
    exit();
}


// STATISTIK
$q_stats = safeQuery($conn, "SELECT Total, Aktif, Maintenance FROM dbo.fn_GetLapanganStats()", []);
$stats = safeFetch($q_stats);

$cnt_ready = $stats['Aktif'] ?? 0;
$cnt_maint = $stats['Maintenance'] ?? 0;
$total_semua_lapangan = $stats['Total'] ?? 0;

// PAGING
$limit = 8;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

$f_status = isset($_GET['f_status']) && $_GET['f_status'] !== '' ? $_GET['f_status'] : 'all';
$f_sort = $_GET['f_sort'] ?? 'ID_Lapangan';
$search = isset($_GET['src']) ? trim($_GET['src']) : '';

$query_sql = "EXEC dbo.sp_ReadLapanganListWithCount @FilterStatus = ?, @SortBy = ?, @Offset = ?, @Limit = ?, @Search = ?";
$params_sp = array($f_status, $f_sort, intval($offset), intval($limit), $search);

$query = safeQuery($conn, $query_sql, $params_sp);
$total_lapangan = 0;

if ($query) {
    // Ambil jumlah data terfilter (Hasil 1 dari SP)
    $row_count = safeFetch($query);
    $total_lapangan = intval($row_count['TotalCount'] ?? 0);

    // Geser ke list data lapangan terpaginasi (Hasil 2 dari SP)
    sqlsrv_next_result($query);
}

// Hitung ulang halaman berdasarkan total data terfilter
$total_pages = max(1, ceil($total_lapangan / $limit));
$page = min($page, $total_pages);

$filter_url = "";
if (isset($_GET['f_sort'])) {
    $filter_url .= "&f_sort=" . urlencode($_GET['f_sort']);
}
if (isset($_GET['f_status'])) {
    $filter_url .= "&f_status=" . urlencode($_GET['f_status']);
}
if (!empty($search)) {
    $filter_url .= "&src=" . urlencode($search);
}


// Ambil daftar fasilitas aktif untuk ditampilkan sebagai pilihan
$master_facilities = [];
$q_fac = safeQuery($conn, "SELECT ID_Fasilitas, Nama_Fasilitas FROM dbo.Fasilitas_Lapangan WHERE Is_Deleted = 0 AND Status = 1");
if ($q_fac) {
    while ($f_row = safeFetch($q_fac)) {
        $master_facilities[] = $f_row;
    }
}

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Kelola Lapangan | HoopBall</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800;900&family=Barlow:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../asset/css/global.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .content {
            padding: 32px 40px;
            flex: 1;
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
            padding: 10px 40px 10px 40px;
            /* Padding kanan diubah menjadi 40px */
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

        /* Class baru untuk tombol X */
        .btn-clear-search {
            position: absolute;
            right: 5px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--muted);
            cursor: pointer;
            font-size: 14px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* ===== CARD GRID - KONSISTEN DENGAN FASILITAS ===== */
        .lapangan-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .lapangan-card {
            background: var(--card-bg);
            border-radius: 16px;
            border: 1px solid var(--border);
            overflow: hidden;
            transition: all .25s ease;
            cursor: pointer;
            position: relative;
        }

        .lapangan-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 32px rgba(0, 0, 0, .12);
            border-color: var(--orange);
            background-color: #FFEDD5 !important;
        }

        .lapangan-card:nth-child(odd) {
            background-color: #FFF7ED;
        }

        .lapangan-card:nth-child(even) {
            background-color: #FFFFFF;
        }


        .lapangan-card-photo-wrap {
            position: relative;
            width: 100%;
            height: 160px;
            background: var(--border-lt);
            overflow: hidden;
        }

        .lapangan-card-photo-wrap img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            z-index: 1;
            transition: transform .3s ease;
            display: block;
        }

        .lapangan-card:hover .lapangan-card-photo-wrap img {
            transform: scale(1.05);
        }

        .lapangan-card-photo-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #FFF7ED 0%, #FFEDD5 100%);
            position: absolute;
            top: 0;
            left: 0;
        }

        .lapangan-card-photo-placeholder i {
            font-size: 40px;
            color: var(--orange);
            opacity: .5;
        }

        /* ===== STATUS BADGE - KONSISTEN DENGAN FASILITAS ===== */
        .lapangan-card-badge {
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

        .badge-aktif {
            background: var(--green-lt);
            color: var(--green);
            border: 1px solid rgba(16, 185, 129, .2);
        }

        .badge-nonaktif {
            background: var(--red-lt);
            color: var(--red);
            border: 1px solid rgba(239, 68, 68, .2);
        }

        .badge-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            display: inline-block;
        }

        .badge-aktif .badge-dot {
            background: var(--green);
        }

        .badge-nonaktif .badge-dot {
            background: var(--red);
        }

        /* ===== CARD ACTIONS - KONSISTEN DENGAN FASILITAS ===== */
        .lapangan-card-actions {
            position: absolute;
            top: 8px;
            right: 8px;
            display: flex;
            gap: 6px;
            opacity: 1;
            /* Selalu muncul permanen */
            z-index: 3;
        }

        .lapangan-card-action-btn {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            border: 1.5px solid transparent;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 14px;
            transition: all .25s cubic-bezier(.4, 0, .2, 1);
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
            box-shadow: 0 6px 20px rgba(59, 130, 246, .35);
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
            box-shadow: 0 6px 20px rgba(59, 130, 246, .35);
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
            box-shadow: 0 6px 20px rgba(239, 68, 68, .35);
        }

        .lapangan-card-info {
            padding: 16px 20px;
        }

        .lapangan-card-name {
            font-size: 15px;
            font-weight: 700;
            color: var(--text);
            line-height: 1.3;
            margin-bottom: 6px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            min-height: 20px;
        }

        .lapangan-card-price {
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 20px;
            font-weight: 900;
            color: var(--shopee-orange);
            margin-bottom: 8px;
        }

        .lapangan-card-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }

        .lapangan-card-harga {
            font-size: 12px;
            color: var(--muted);
            font-weight: 600;
        }

        .lapangan-card-harga i {
            color: var(--orange);
            margin-right: 4px;
        }

        .lapangan-card-toggle {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .lapangan-card-toggle-label {
            font-size: 10px;
            font-weight: 700;
            color: var(--muted);
            text-transform: uppercase;
        }

        /* ===== TOGGLE SWITCH - KONSISTEN DENGAN FASILITAS ===== */
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
            transition: all .3s cubic-bezier(.4, 0, .2, 1);
            border-radius: 24px;
            will-change: background-color;
        }

        .toggle-slider::before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: all .3s cubic-bezier(.4, 0, .2, 1);
            border-radius: 50%;
            box-shadow: 0 2px 4px rgba(0, 0, 0, .2);
            will-change: transform;
        }

        .toggle-switch input:checked+.toggle-slider {
            background-color: var(--green);
        }

        .toggle-switch input:checked+.toggle-slider::before {
            transform: translateX(20px);
        }

        .toggle-switch:hover .toggle-slider {
            opacity: .9;
        }

        .empty-grid {
            grid-column: 1 / -1;
            text-align: center;
            padding: 80px 20px;
            color: var(--muted);
        }

        .empty-grid i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: .3;
            display: block;
        }

        .empty-grid div {
            font-size: 16px;
            font-weight: 700;
        }

        .empty-grid p {
            font-size: 13px;
            font-weight: 500;
            margin-top: 8px;
            opacity: .7;
        }

        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .55);
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
            width: 520px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 25px 60px rgba(0, 0, 0, .2);
            position: relative;
        }

        .modal-header {
            padding: 28px 32px 20px;
            border-bottom: 1px solid var(--border);
        }

        .modal-subtitle {
            font-size: 10px;
            font-weight: 800;
            color: var(--orange);
            text-transform: uppercase;
            margin-bottom: 6px;
            letter-spacing: .8px;
        }

        .modal-title {
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 22px;
            font-weight: 900;
            color: var(--text);
        }

        .modal-body {
            padding: 24px 32px 32px;
        }

        .modal-label {
            display: block;
            font-size: 11px;
            font-weight: 800;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .5px;
            margin-bottom: 8px;
        }

        .modal-label .required {
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
            font-size: 13px;
            font-family: 'Barlow', sans-serif;
            margin-bottom: 18px;
            outline: none;
            transition: all .2s;
            color: var(--text);
        }

        .modal-input:focus {
            border-color: var(--orange);
            box-shadow: 0 0 0 3px var(--orange-lt);
        }

        .modal-input::placeholder {
            color: #9CA3AF;
        }

        .modal-input.error {
            border-color: var(--red);
            box-shadow: 0 0 0 3px var(--red-lt);
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
            margin-top: 20px;
        }

        .btn-submit:hover {
            background: var(--orange-dk);
            transform: translateY(-1px);
            box-shadow: 0 8px 20px rgba(255, 69, 0, .3);
        }

        .btn-cancel {
            display: block;
            text-align: center;
            margin-top: 16px;
            color: var(--muted);
            text-decoration: none;
            font-size: 13px;
            font-weight: 700;
            transition: .2s;
            cursor: pointer;
            background: none;
            border: none;
            width: 100%;
        }

        .btn-cancel:hover {
            color: var(--orange);
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
            z-index: 10;
        }

        .modal-close:hover {
            background: var(--red-lt);
            color: var(--red);
        }

        .photo-upload-area {
            width: 100%;
            height: 140px;
            border: 2px dashed var(--border);
            border-radius: 12px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all .2s ease;
            margin-bottom: 16px;
            position: relative;
            overflow: hidden;
            background: var(--border-lt);
        }

        .photo-upload-area:hover {
            border-color: var(--orange);
            background: var(--orange-lt);
        }

        .photo-upload-area.has-image {
            border-style: solid;
            border-color: var(--orange);
        }

        .photo-upload-area i.upload-icon {
            font-size: 28px;
            color: var(--orange);
            margin-bottom: 8px;
        }

        .photo-upload-area p {
            font-size: 13px;
            font-weight: 600;
            color: var(--muted);
            text-align: center;
        }

        .photo-upload-area input[type="file"] {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
            width: 100%;
            height: 100%;
            z-index: 5;
        }

        .photo-upload-preview {
            width: 100%;
            height: 100%;
            object-fit: cover;
            position: absolute;
            top: 0;
            left: 0;
            z-index: 2;
        }

        .photo-upload-remove {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 28px;
            height: 28px;
            background: rgba(239, 68, 68, .9);
            color: #fff;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
        }

        .upload-placeholder-inner {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            pointer-events: none;
            z-index: 1;
        }

        .val-msg {
            font-size: 11px;
            color: var(--red);
            font-weight: 600;
            margin-top: -12px;
            /* Menarik teks eror agar lebih dekat dengan input di atasnya */
            margin-bottom: 18px;
            /* Memberikan jarak 18px sebelum masuk ke kolom berikutnya */
            display: none;
            min-height: 16px;
        }

        .val-msg.show {
            display: block;
        }

        .val-msg i {
            margin-right: 4px;
        }

        /* ===== DETAIL MODAL - FOTO BULAT SEPERTI ALAT ===== */
        .detail-modal-box {
            width: 460px;
            border-radius: 24px;
            border: 1px solid var(--border);
            overflow-y: auto;
        }

        .detail-photo-wrap {
            width: 120px;
            height: 120px;
            margin: 0 auto 16px auto;
            background: #ffffff;
            border-radius: 50%;
            overflow: hidden;
            position: relative;
            border: 3px solid var(--orange);
            box-shadow: 0 4px 16px rgba(255, 69, 0, .2);
        }

        .detail-photo-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .detail-photo-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #FFF7ED 0%, #FFEDD5 100%);
        }

        .detail-photo-placeholder i {
            font-size: 40px;
            color: var(--orange);
            opacity: .6;
        }

        .detail-name {
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 26px;
            font-weight: 900;
            color: var(--text);
            line-height: 1.2;
            margin-bottom: 6px;
            text-transform: uppercase;
            text-align: center;
        }

        .detail-price {
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 30px;
            font-weight: 900;
            color: var(--shopee-orange);
            margin-bottom: 20px;
            border-bottom: 1px solid var(--border-lt);
            padding-bottom: 14px;
            text-align: center;
        }

        .detail-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
            margin-bottom: 20px;
        }

        .detail-info-item {
            background: #FAFBFD;
            border: 1px solid var(--border-lt);
            border-radius: 14px;
            padding: 14px;
            transition: all .2s ease;
        }

        .detail-info-label {
            font-size: 10px;
            font-weight: 800;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .5px;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .detail-info-value {
            font-size: 18px;
            font-weight: 800;
            color: var(--text);
        }

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

        .badge-status-aktif {
            background: var(--green-lt);
            color: var(--green);
            border: 1px solid rgba(16, 185, 129, .2);
        }

        .badge-status-nonaktif {
            background: var(--red-lt);
            color: var(--red);
            border: 1px solid rgba(239, 68, 68, .2);
        }

        .detail-status-badge i {
            font-size: 8px;
        }

        .detail-info-item:hover {
            background: #ffffff;
            border-color: var(--orange);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, .02);
        }

        .detail-info-label i {
            color: var(--orange);
            font-size: 12px;
        }

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

        /* ===== FILTER - KONSISTEN DENGAN FASILITAS ===== */
        .filter-dropdown-wrap {
            position: relative;
            display: inline-block;
        }

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
            box-shadow: 0 4px 12px rgba(255, 69, 0, 0.2);
        }

        .btn-filter:hover {
            background-color: var(--orange-dk);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(255, 69, 0, 0.35);
        }

        .btn-filter i.arrow-icon {
            font-size: 10px;
            transition: transform 0.3s;
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

        .filter-group {
            margin-bottom: 16px;
            text-align: left;
        }

        .filter-group label {
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
            background: #E5E7EB;
        }

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
            box-shadow: 0 8px 20px rgba(255, 69, 0, .3);
        }

        .btn-add i {
            font-size: 14px;
        }

        .modal-box {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        .modal-box::-webkit-scrollbar {
            display: none;
        }

        @media(max-width:768px) {
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

            .lapangan-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }

            .modal-box {
                width: 90%;
                margin: 20px;
            }

            .search-box {
                width: 100%;
            }

            .action-bar {
                flex-direction: column;
                align-items: stretch;
            }

            .detail-photo-wrap {
                width: 100px;
                height: 100px;
            }
        }

        @media(max-width:480px) {
            .lapangan-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Custom Multi-select Dropdown dengan Checkbox */
        .multiselect-dropdown {
            position: relative;
            width: 100%;
            margin-bottom: 18px;
            /* Menyelaraskan jarak dropdown agar konsisten 18px */
        }

        .multiselect-header {
            width: 100%;
            padding: 12px 14px;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            font-size: 13px;
            background: #fff;
            color: var(--text);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            user-select: none;
            transition: all 0.2s;
        }

        .multiselect-header:hover {
            border-color: var(--orange);
        }

        .multiselect-content {
            position: absolute;
            top: calc(100% + 4px);
            left: 0;
            width: 100%;
            background: #fff;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 100;
            display: none;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
            padding: 6px 0;
        }

        .multiselect-content.open {
            display: block;
        }

        .multiselect-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 16px;
            cursor: pointer;
            transition: background 0.2s;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-md);
            margin: 0;
        }

        .multiselect-item:hover {
            background: #FAFBFD;
        }

        .multiselect-item input[type="checkbox"] {
            cursor: pointer;
            width: 16px;
            height: 16px;
            accent-color: var(--orange);
        }

        /* Efek merah eror pada dropdown header */
        .multiselect-header.error {
            border-color: var(--red) !important;
            box-shadow: 0 0 0 3px var(--red-lt) !important;
        }


        .empty-note-facilities {
            font-size: 12px;
            color: var(--muted);
            font-style: italic;
            font-weight: 500;
        }

        /* ===== CSS BOX TUNGGAL FASILITAS (IDENTIK DENGAN BOX TARIF) ===== */
        .detail-facilities-card {
            background: #FAFBFD;
            border: 1px solid var(--border-lt);
            border-radius: 14px;
            padding: 14px;
            margin-bottom: 20px;
            transition: all .2s ease;
            text-align: left;
        }

        /* Efek Hover Oranye berpendar persis seperti box tarif di atasnya */
        .detail-facilities-card:hover {
            background: #ffffff;
            border-color: var(--orange);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, .02);
        }

        /* Layout List Teks di dalam Box tunggal */
        .detail-facilities-list {
            display: grid;
            grid-template-columns: 1fr 1fr;
            /* Membagi list menjadi 2 kolom teks yang rapi */
            gap: 8px 16px;
            margin-top: 12px;
        }

        .facility-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            font-weight: 700;
            color: var(--text);
        }

        .facility-item i {
            color: var(--orange);
            font-size: 11px;
        }
    </style>
</head>

<body>
    <!-- MODAL FORM TAMBAH/EDIT LAPANGAN (VERSI BARU) -->
    <div class="modal-overlay" id="modalLapangan">
        <div class="modal-box">
            <button type="button" class="modal-close" onclick="closeModalDirect('modalLapangan')" title="Tutup"><i
                    class="fa-solid fa-xmark"></i></button>
            <div class="modal-header">
                <div class="modal-subtitle">Kelola Lapangan</div>
                <div class="modal-title" id="formModalTitle">Tambah Lapangan Baru</div>
            </div>
            <div class="modal-body">
                <form method="POST" id="formLapangan" enctype="multipart/form-data" action="lapangan.php"
                    onsubmit="return validateForm()" novalidate>
                    <input type="hidden" name="save_lapangan" value="1">

                    <!-- Tempat menaruh parameter Edit Mode secara dinamis via JS -->
                    <div id="hiddenInputsArea"></div>

                    <label class="modal-label">Foto Lapangan <span style="color:var(--muted);font-size:10px;">(Opsional,
                            max 5MB)</span></label>
                    <div class="photo-upload-area" id="uploadArea">
                        <input type="file" name="photo_lapangan" id="photo_lapangan"
                            accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
                            onchange="handlePhotoUpload(this)">

                        <img class="photo-upload-preview" id="previewImg" style="display:none;" alt="Preview">
                        <button type="button" class="photo-upload-remove" id="removeBtn"
                            onclick="event.stopPropagation(); removePhoto();" style="display:none;" title="Hapus Foto">
                            <i class="fa-solid fa-xmark"></i>
                        </button>

                        <div class="upload-placeholder-inner" id="uploadPlaceholder"
                            style="display:flex; flex-direction:column; align-items:center;">
                            <i class="fa-solid fa-cloud-arrow-up upload-icon"></i>
                            <p>Klik untuk upload foto lapangan</p>
                            <p style="font-size:11px; margin-top:4px; opacity:.7;">JPG, PNG, GIF, WEBP (Max 5MB)</p>
                        </div>
                    </div>

                    <label class="modal-label">Nama Lapangan <span class="required">*</span></label>
                    <input type="text" name="nama_lapangan" id="nama_lapangan" class="modal-input"
                        placeholder="Contoh: Lapangan Indoor A" autocomplete="off" maxlength="50">
                    <div class="val-msg" id="val-nama_lapangan"></div>

                    <label class="modal-label">Harga Sewa per Jam (Rp) <span class="required">*</span></label>
                    <input type="number" name="harga_sewa" id="harga_sewa" class="modal-input"
                        placeholder="Contoh: 100000" autocomplete="off">
                    <div class="val-msg" id="val-harga_sewa"></div>

                    <!-- PILIHAN FASILITAS (DROPDOWN MULTI-SELECT) -->
                    <label class="modal-label">Fasilitas Lapangan <span style="color:red">*</span></label>
                    <div class="multiselect-dropdown" id="facilityDropdown">
                        <div class="multiselect-header" onclick="toggleMultiselect(event)">
                            <span id="multiselectLabel">Pilih Fasilitas Lapangan</span>
                            <i class="fa-solid fa-chevron-down" style="font-size: 11px; color: var(--muted);"></i>
                        </div>
                        <div class="multiselect-content" id="multiselectContent">
                            <?php if (!empty($master_facilities)): ?>
                                <?php foreach ($master_facilities as $fac): ?>
                                    <label class="multiselect-item" for="chk_fac_<?= $fac['ID_Fasilitas'] ?>">
                                        <input type="checkbox" name="use_facility[]" value="<?= $fac['ID_Fasilitas'] ?>"
                                            id="chk_fac_<?= $fac['ID_Fasilitas'] ?>" class="facility-checkbox"
                                            onchange="updateMultiselectHeader()">
                                        <?= htmlspecialchars($fac['Nama_Fasilitas']) ?>
                                    </label>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div style="padding: 12px; font-size: 12px; text-align: center; color: var(--muted);">Belum
                                    ada master fasilitas aktif terdaftar.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="val-msg" id="val-facilities"></div>
                    <button type="submit" class="btn-submit" id="btnSubmitForm">
                        <i class="fa-solid fa-plus"></i> Tambah Lapangan
                    </button>
                    <button type="button" class="btn-cancel" onclick="closeModalDirect('modalLapangan')">Batal</button>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL DETAIL LAPANGAN - FOTO BULAT SEPERTI ALAT (VERSI BARU) -->
    <div class="modal-overlay" id="modalDetail">
        <div class="modal-box detail-modal-box">
            <button type="button" class="modal-close" onclick="closeModalDirect('modalDetail')" title="Tutup"><i
                    class="fa-solid fa-xmark"></i></button>
            <div class="modal-header" style="text-align: center; padding-bottom: 10px;">
                <div class="modal-subtitle">Detail Informasi</div>
                <div class="modal-title">Spesifikasi Lapangan</div>
            </div>
            <div class="modal-body" style="padding-top:10px;">
                <div class="detail-photo-wrap">
                    <img src="" id="det_photo_img" alt="Foto Lapangan" style="display:none;">
                    <div class="detail-photo-placeholder" id="det_photo_placeholder"><i
                            class="fa-solid fa-layer-group"></i></div>
                </div>

                <div style="text-align: center;">
                    <div class="detail-status-badge" id="det_status_badge">
                        <i class="fa-solid fa-circle"></i>
                        <span id="det_status_text">Lapangan Aktif</span>
                    </div>
                </div>

                <div class="detail-name" id="det_nama_title">-</div>
                <div class="detail-price" id="det_harga">- <span
                        style="font-size:14px;color:var(--muted);font-family:'Barlow';font-weight:600;">/ jam</span>
                </div>

                <div class="detail-info-grid">
                    <div class="detail-info-item">
                        <div class="detail-info-label"><i class="fa-solid fa-money-bill-wave"></i> Harga Sewa</div>
                        <div class="detail-info-value" id="det_harga_val" style="color:var(--shopee-orange);">-</div>
                    </div>
                    <div class="detail-info-item">
                        <div class="detail-info-label"><i class="fa-solid fa-tag"></i> Tarif per Jam</div>
                        <div class="detail-info-value" id="det_tarif_secondary">- <span
                                style="font-size:11px; font-weight:500; color:var(--muted);">/jam</span></div>
                    </div>
                </div>

                <!-- FASILITAS TERPASANG DI LAPANGAN (BERSIH DARI INLINE STYLE) -->
                <div class="detail-facilities-card">
                    <div class="detail-info-label">
                        <i class="fa-solid fa-couch"></i> Fasilitas Terpasang
                    </div>
                    <div id="det_facilities_list">
                        -
                    </div>
                </div>


                <button type="button" onclick="closeModalDirect('modalDetail')" class="btn-submit"
                    style="background:#0D1117; margin-top: 10px;">
                    <i class="fa-solid fa-arrow-left"></i> Kembali
                </button>
            </div>
        </div>
    </div>

    <!-- SIDEBAR -->
    <?php include '../includes/sidebar.php'; ?>

    <!-- MAIN CONTENT -->
    <main class="main">
        <?php include '../includes/topbar.php'; ?>

        <div class="content">
            <div class="page-header">
                <div>
                    <div class="page-title-tag"></div>
                    <div class="page-title">Kelola Lapangan</div>
                </div>
                <div class="stat-chips">
                    <div class="stat-chip chip-green"><i class="fa-solid fa-circle-check"></i> AKTIF <span
                            class="chip-val"><?= $cnt_ready ?></span></div>
                    <div class="stat-chip chip-red"><i class="fa-solid fa-circle-xmark"></i> MAINTENANCE <span
                            class="chip-val"><?= $cnt_maint ?></span></div>
                    <div class="stat-chip chip-blue"><i class="fa-solid fa-layer-group"></i> TOTAL <span
                            class="chip-val"><?= $total_semua_lapangan ?></span></div>
                    <!-- Menggunakan variabel baru -->
                </div>
            </div>

            <div class="action-bar">
                <div class="search-box">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="src" placeholder="Cari lapangan... (Tekan Enter)"
                        onkeypress="handleSearch(event)" value="<?= htmlspecialchars($_GET['src'] ?? '') ?>">

                    <!-- Tombol Reset Cepat (X) bersih dari CSS Inline -->
                    <?php if (!empty($search)): ?>
                        <button type="button" onclick="clearSearch()" class="btn-clear-search">
                            <i class="fa-solid fa-circle-xmark"></i>
                        </button>
                    <?php endif; ?>
                </div>
                <div style="display:flex;gap:12px;align-items:center;">
                    <div class="filter-dropdown-wrap">
                        <button class="btn-filter" id="btnFilterToggle">
                            <i class="fa-solid fa-filter"></i> Filter <i
                                class="fa-solid fa-chevron-down arrow-icon"></i>
                        </button>
                        <div class="filter-card" id="filterCard">
                            <h4><i class="fa-solid fa-sliders" style="margin-right:8px;color:var(--orange);"></i>Filter
                                Data</h4>
                            <form method="GET" action="lapangan.php">
                                <div class="filter-group">
                                    <label>Status</label>
                                    <select name="f_status" class="filter-input">
                                        <option value="">Semua Status</option>
                                        <option value="1" <?= ($_GET['f_status'] ?? '') === '1' ? 'selected' : '' ?>>AKTIF
                                        </option>
                                        <option value="0" <?= ($_GET['f_status'] ?? '') === '0' ? 'selected' : '' ?>>
                                            MAINTENANCE</option>
                                    </select>
                                </div>
                                <div class="filter-group">
                                    <label>Urutkan</label>
                                    <select name="f_sort" class="filter-input">
                                        <option value="nama_asc" <?= ($_GET['f_sort'] ?? '') === 'nama_asc' ? 'selected' : '' ?>>Nama A-Z</option>
                                        <option value="harga_desc" <?= ($_GET['f_sort'] ?? '') === 'harga_desc' ? 'selected' : '' ?>>Harga Termahal</option>
                                        <option value="harga_asc" <?= ($_GET['f_sort'] ?? '') === 'harga_asc' ? 'selected' : '' ?>>Harga Termurah</option>
                                    </select>
                                </div>
                                <div class="filter-buttons">
                                    <button type="submit" class="btn-filter-apply"><i class="fa-solid fa-check"></i>
                                        Terapkan</button>
                                    <button type="button" class="btn-filter-reset" onclick="resetFilter()"><i
                                            class="fa-solid fa-rotate-left"></i> Reset</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <button type="button" onclick="showAddForm()" class="btn-add"><i
                            class="fa-solid fa-plus"></i>Tambah</button>
                </div>
            </div>

            <!-- GRID KARTU LAPANGAN (YANG SUDAH DIRAPIKAN TAG PENUTUPNYA) -->
            <div class="lapangan-grid" id="lapanganGrid">
                <?php
                $has_data = false;
                if ($query):
                    while ($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)):
                        $has_data = true;
                        $photo_url = getPhotoUrl($row['Photo_Lapangan'] ?? '');
                        $is_aktif = intval($row['Status']) === 1;
                        ?>
                        <div class="lapangan-card" data-name="<?= strtolower(htmlspecialchars($row['Nama_Lapangan'])) ?>">

                            <!-- 1. BAGIAN FOTO WRAP -->
                            <div class="lapangan-card-photo-wrap" onclick="showDetail('<?= intval($row['ID_Lapangan']) ?>')">
                                <div class="lapangan-card-photo-placeholder">
                                    <i class="fa-solid fa-layer-group"></i>
                                </div>
                                <?php if (!empty($photo_url)): ?>
                                    <img src="<?= htmlspecialchars($photo_url) ?>"
                                        alt="<?= htmlspecialchars($row['Nama_Lapangan']) ?>" loading="lazy"
                                        onerror="this.style.display='none';">
                                <?php endif; ?>
                                <span class="lapangan-card-badge <?= $is_aktif ? 'badge-aktif' : 'badge-nonaktif' ?>">
                                    <span class="badge-dot"></span> <?= $is_aktif ? 'AKTIF' : 'MAINTENANCE' ?>
                                </span>
                                <div class="lapangan-card-actions">
                                    <button type="button"
                                        onclick="event.stopPropagation(); showDetail('<?= intval($row['ID_Lapangan']) ?>')"
                                        class="lapangan-card-action-btn ac-btn-view" title="Lihat Detail"><i
                                            class="fa-solid fa-eye"></i></button>
                                    <button type="button"
                                        onclick="event.stopPropagation(); showEditForm('<?= intval($row['ID_Lapangan']) ?>')"
                                        class="lapangan-card-action-btn ac-btn-edit" title="Edit Lapangan"><i
                                            class="fa-solid fa-pen-to-square"></i></button>
                                    <button type="button"
                                        onclick="event.stopPropagation(); doDelete(<?= intval($row['ID_Lapangan']) ?>, '<?= htmlspecialchars($row['Nama_Lapangan'], ENT_QUOTES) ?>')"
                                        class="lapangan-card-action-btn ac-btn-delete" title="Hapus Lapangan">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                </div>
                            </div> <!-- PENUTUP LAPANGAN-CARD-PHOTO-WRAP (SANGAT PENTING) -->

                            <!-- 2. BAGIAN INFORMASI DETAIL -->
                            <div class="lapangan-card-info">
                                <div class="lapangan-card-name"><?= htmlspecialchars($row['Nama_Lapangan']) ?></div>
                                <div class="lapangan-card-price"><?= rupiah($row['Harga_Sewa']) ?> <span
                                        style="font-size:12px;color:var(--muted);font-weight:600;">/ jam</span></div>
                                <div class="lapangan-card-meta">
                                    <span class="lapangan-card-harga">
                                        <i class="fa-solid fa-money-bill-wave"></i><?= rupiah($row['Harga_Sewa']) ?>
                                    </span>
                                    <div class="lapangan-card-toggle">
                                        <span class="lapangan-card-toggle-label"><?= $is_aktif ? 'ON' : 'OFF' ?></span>
                                        <label class="toggle-switch" title="<?= $is_aktif ? 'Nonaktifkan' : 'Aktifkan' ?>">
                                            <input type="checkbox" <?= $is_aktif ? 'checked' : '' ?>
                                                onchange="confirmToggle('<?= intval($row['ID_Lapangan']) ?>', '<?= htmlspecialchars($row['Nama_Lapangan'], ENT_QUOTES) ?>', <?= intval($row['Status']) ?>, event)">
                                            <span class="toggle-slider"></span>
                                        </label>
                                    </div>
                                </div>
                            </div> <!-- PENUTUP LAPANGAN-CARD-INFO -->

                        </div> <!-- PENUTUP LAPANGAN-CARD UTAMA (SANGAT PENTING) -->
                        <?php
                    endwhile;
                endif;
                if (!$has_data): ?>
                    <div class="empty-grid">
                        <i class="fa-solid fa-layer-group"></i>
                        <div>Belum ada data lapangan</div>
                        <p>Klik "+ Tambah Lapangan" untuk menambahkan lapangan baru</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- PAGINATION -->
            <div class="pagination-wrap">
                <div class="pagination-info">
                    <?php if ($total_lapangan > 0): ?>
                        Menampilkan <strong><?= (($page - 1) * $limit) + 1 ?></strong> -
                        <strong><?= min($page * $limit, $total_lapangan) ?></strong>
                        dari <strong><?= $total_lapangan ?></strong> data
                    <?php else: ?>
                        Menampilkan <strong>0</strong> data
                    <?php endif; ?>
                </div>

                <div class="pagination-nav">
                    <!-- Tombol First/Awal -->
                    <a href="?page=1<?= $filter_url ?>" class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>">
                        <i class="fa-solid fa-angles-left"></i>
                    </a>

                    <!-- Tombol Prev/Sebelumnya -->
                    <a href="?page=<?= max(1, $page - 1) ?><?= $filter_url ?>"
                        class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>">
                        <i class="fa-solid fa-angle-left"></i>
                    </a>

                    <!-- Nomor Halaman -->
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <a href="?page=<?= $i ?><?= $filter_url ?>" class="page-btn <?= $i == $page ? 'active' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <!-- Tombol Next/Selanjutnya -->
                    <a href="?page=<?= min($total_pages, $page + 1) ?><?= $filter_url ?>"
                        class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>">
                        <i class="fa-solid fa-angle-right"></i>
                    </a>

                    <!-- Tombol Last/Akhir -->
                    <a href="?page=<?= $total_pages ?><?= $filter_url ?>"
                        class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>">
                        <i class="fa-solid fa-angles-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </main>
    <script src="../asset/js/global.js"></script>
    <script>

        function handlePhotoUpload(input) {
            if (!input.files || !input.files[0]) return;
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
            reader.onload = function (e) {
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
            var fileInput = document.getElementById('photo_lapangan');
            var uploadArea = document.getElementById('uploadArea');
            var removeBtn = document.getElementById('removeBtn');
            if (fileInput) fileInput.value = '';
            if (previewImg) {
                previewImg.src = '';
                previewImg.style.display = 'none';
            }
            if (uploadArea) uploadArea.classList.remove('has-image');
            if (uploadPlaceholder) uploadPlaceholder.style.display = 'flex';
            if (removeBtn) removeBtn.style.display = 'none';
        }

        function validateForm() {
            var valid = true;

            // 1. Reset semua status eror terlebih dahulu
            document.querySelectorAll('.modal-input').forEach(function (el) { el.classList.remove('error'); });
            document.querySelectorAll('.multiselect-header').forEach(function (el) { el.classList.remove('error'); });
            document.querySelectorAll('.val-msg').forEach(function (el) { el.classList.remove('show'); el.innerHTML = ''; });

            // 2. Validasi Nama Lapangan
            var nama = document.getElementById('nama_lapangan');
            var valNama = document.getElementById('val-nama_lapangan');
            if (nama && valNama) {
                var v = nama.value.trim();
                var errNama = '';
                if (v === '') errNama = 'Nama lapangan wajib diisi.';
                else if (v.length < 3) errNama = 'Nama lapangan minimal 3 karakter.';
                else if (v.length > 50) errNama = 'Nama lapangan maksimal 50 karakter.';
                else if (!/^[a-zA-Z\s]+$/.test(v)) errNama = 'Nama lapangan hanya boleh huruf dan spasi.';
                if (errNama) {
                    nama.classList.add('error');
                    valNama.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> ' + errNama;
                    valNama.classList.add('show');
                    valid = false;
                }
            }

            // 3. Validasi Harga Sewa
            var harga = document.getElementById('harga_sewa');
            var valHarga = document.getElementById('val-harga_sewa');
            if (harga && valHarga) {
                var vh = harga.value.trim();
                var errHarga = '';
                if (vh === '') errHarga = 'Harga sewa wajib diisi.';
                else if (isNaN(vh)) errHarga = 'Harga sewa harus berupa angka.';
                else if (parseFloat(vh) < 10000) errHarga = 'Harga sewa minimal Rp 10.000.';
                else if (parseFloat(vh) > 10000000) errHarga = 'Harga sewa maksimal Rp 10.000.000.';
                if (errHarga) {
                    harga.classList.add('error');
                    valHarga.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> ' + errHarga;
                    valHarga.classList.add('show');
                    valid = false;
                }
            }

            // 4. Validasi Fasilitas (WAJIB DI ATAS LINE RETURN FALSE)
            var checkboxes = document.querySelectorAll('.facility-checkbox');
            var valFacilities = document.getElementById('val-facilities');
            var dropdownHeader = document.querySelector('.multiselect-header');
            var anyChecked = Array.from(checkboxes).some(function (chk) { return chk.checked; });

            if (!anyChecked) {
                if (dropdownHeader && valFacilities) {
                    dropdownHeader.classList.add('error'); // Menggunakan class CSS agar konsisten
                    valFacilities.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Fasilitas lapangan wajib dipilih minimal satu.';
                    valFacilities.classList.add('show');
                }
                valid = false;
            }

            // 5. JIKA ADA YANG EROR, BARU HENTIKAN SUBMIT FORM
            if (!valid) return false;

            // Proses loading spinner tombol jika form sukses divalidasi
            var btn = document.getElementById('btnSubmitForm');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Memproses...';
            }
            return true;
        }

        document.addEventListener('DOMContentLoaded', function () {
            var namaLap = document.getElementById('nama_lapangan');
            if (namaLap) {
                namaLap.addEventListener('input', function () {
                    var valNama = document.getElementById('val-nama_lapangan');
                    var v = this.value.trim();
                    this.classList.remove('error'); valNama.classList.remove('show');
                    if (v.length > 0 && v.length < 3) {
                        this.classList.add('error');
                        valNama.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Nama lapangan minimal 3 karakter.';
                        valNama.classList.add('show');
                    } else if (v.length > 50) {
                        this.classList.add('error');
                        valNama.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Nama lapangan maksimal 50 karakter.';
                        valNama.classList.add('show');
                    }
                });
            }

            var hargaEl = document.getElementById('harga_sewa');
            if (hargaEl) {
                hargaEl.addEventListener('input', function () {
                    var valHarga = document.getElementById('val-harga_sewa');
                    var v = this.value.trim();
                    this.classList.remove('error'); valHarga.classList.remove('show');
                    if (v !== '' && !isNaN(v)) {
                        if (parseFloat(v) < 10000) {
                            this.classList.add('error');
                            valHarga.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Harga sewa minimal Rp 10.000.';
                            valHarga.classList.add('show');
                        } else if (parseFloat(v) > 10000000) {
                            this.classList.add('error');
                            valHarga.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Harga sewa maksimal Rp 10.000.000.';
                            valHarga.classList.add('show');
                        }
                    }
                });
            }
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
            }).then(function (result) {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Memproses...',
                        text: 'Mengubah status lapangan',
                        allowOutsideClick: false,
                        didOpen: function () {
                            Swal.showLoading();
                        }
                    });
                    setTimeout(function () {
                        window.location.href = '?toggle_id=' + id + '&s=' + currentStatus;
                    }, 600);
                } else {
                    checkbox.checked = !checkbox.checked;
                }
            });
        }

        function doDelete(id, name) {
            Swal.fire({
                title: 'Hapus Lapangan?',
                html: 'Anda akan menghapus lapangan <strong style="color:var(--orange);">' + name + '</strong><br><span style="font-size:12px;color:var(--muted);">Data akan dihapus secara permanen.</span>',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#EF4444',
                cancelButtonColor: '#6B7280',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal',
                reverseButtons: true
            }).then(function (result) {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Menghapus...',
                        allowOutsideClick: false,
                        didOpen: function () { Swal.showLoading(); }
                    });
                    setTimeout(function () {
                        window.location.href = 'lapangan.php?delete_id=' + id;
                    }, 500);
                }
            });
        }

        function resetFilter() {
            window.location.href = 'lapangan.php';
        }

        function showAddForm() {
            document.getElementById('formLapangan').reset();
            document.getElementById('hiddenInputsArea').innerHTML = '';
            removePhoto(); // Reset area foto preview

            // Reset Visual Error
            document.querySelectorAll('.modal-input').forEach(el => el.classList.remove('error'));
            document.querySelectorAll('.multiselect-header').forEach(el => el.classList.remove('error'));
            document.querySelectorAll('.val-msg').forEach(el => el.classList.remove('show'));

            document.getElementById('formModalTitle').innerText = 'Tambah Lapangan Baru';
            document.getElementById('btnSubmitForm').innerHTML = '<i class="fa-solid fa-plus"></i> Tambah Lapangan';

            document.getElementById('modalLapangan').classList.add('open');

            updateMultiselectHeader();
        }

        // Buka Modal Form Edit Data (AJAX)
        function showEditForm(id) {
            fetch('?ajax_detail_id=' + id)
                .then(response => response.json())
                .then(res => {
                    if (res.status === 'success') {
                        const data = res.data;

                        // Isi Form Input
                        document.getElementById('nama_lapangan').value = data.Nama_Lapangan;
                        document.getElementById('harga_sewa').value = parseInt(data.Harga_Sewa);

                        // Set area hidden input edit mode
                        document.getElementById('hiddenInputsArea').innerHTML = `
                    <input type="hidden" name="edit_mode" value="1">
                    <input type="hidden" name="id_lap" value="${data.ID_Lapangan}">
                    <input type="hidden" name="edit_photo_path" value="${data.Photo_Lapangan ? data.Photo_Lapangan : ''}">
                `;

                        // Set Preview Foto jika ada
                        if (data.Photo_Lapangan_Url && data.Photo_Lapangan) {
                            const previewImg = document.getElementById('previewImg');
                            const uploadPlaceholder = document.getElementById('uploadPlaceholder');
                            const uploadArea = document.getElementById('uploadArea');
                            const removeBtn = document.getElementById('removeBtn');

                            previewImg.src = data.Photo_Lapangan_Url;
                            previewImg.style.display = 'block';
                            uploadArea.classList.add('has-image');
                            uploadPlaceholder.style.display = 'none';
                            removeBtn.style.display = 'flex';
                        } else {
                            removePhoto();
                        }

                        // RESET SEMUA CHECKBOX FASILITAS MENJADI KOSONG (UNCHECKED)
                        document.querySelectorAll('.facility-checkbox').forEach(chk => chk.checked = false);

                        // CENTANG KEMBALI FASILITAS YANG SUDAH TERPASANG DI DATABASE
                        if (data.Fasilitas && data.Fasilitas.length > 0) {
                            data.Fasilitas.forEach(fac => {
                                const chk = document.getElementById('chk_fac_' + fac.ID_Fasilitas);
                                if (chk) {
                                    chk.checked = true;
                                }
                            });
                        }

                        // Perbarui tampilan teks header dropdown multi-select
                        updateMultiselectHeader();

                        document.getElementById('formModalTitle').innerText = 'Edit Lapangan';
                        document.getElementById('btnSubmitForm').innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Simpan Perubahan';

                        document.getElementById('modalLapangan').classList.add('open');
                    } else {
                        Swal.fire('Gagal!', res.msg, 'error');
                    }
                });
        }

        // Tampilkan Detail Lapangan (AJAX)
        function showDetail(id) {
            fetch('?ajax_detail_id=' + id)
                .then(response => response.json())
                .then(res => {
                    if (res.status === 'success') {
                        const data = res.data;

                        // Set Foto Detail
                        const detImg = document.getElementById('det_photo_img');
                        const detPlaceholder = document.getElementById('det_photo_placeholder');
                        if (data.Photo_Lapangan_Url && data.Photo_Lapangan) {
                            detImg.src = data.Photo_Lapangan_Url;
                            detImg.style.display = 'block';
                            detPlaceholder.style.display = 'none';
                        } else {
                            detImg.src = '';
                            detImg.style.display = 'none';
                            detPlaceholder.style.display = 'flex';
                        }

                        // Set Keaktifan Badge
                        const badge = document.getElementById('det_status_badge');
                        const text = document.getElementById('det_status_text');
                        if (data.Status == 1) {
                            badge.className = 'detail-status-badge badge-status-aktif';
                            text.innerText = 'Lapangan Aktif';
                        } else {
                            badge.className = 'detail-status-badge badge-status-nonaktif';
                            text.innerText = 'Lapangan Maintenance';
                        }

                        // Set Informasi Teks
                        document.getElementById('det_nama_title').innerText = data.Nama_Lapangan;
                        document.getElementById('det_harga').innerHTML = `${data.Harga_Sewa_Rupiah} <span style="font-size:14px;color:var(--muted);font-family:'Barlow';font-weight:600;">/ jam</span>`;
                        document.getElementById('det_harga_val').innerText = data.Harga_Sewa_Rupiah;
                        document.getElementById('det_tarif_secondary').innerHTML = `${data.Harga_Sewa_Rupiah} <span style="font-size:11px; font-weight:500; color:var(--muted);">/jam</span>`;

                        // RENDER DAFTAR FASILITAS YANG TERPASANG DI LAPANGAN (BARU)
                        const detFacList = document.getElementById('det_facilities_list');
                        if (detFacList) {
                            if (data.Fasilitas && data.Fasilitas.length > 0) {
                                let facHtml = '<div class="detail-facilities-list">';
                                data.Fasilitas.forEach(fac => {
                                    facHtml += `
                                        <div class="facility-item">
                                            <i class="fa-solid fa-circle-check"></i>
                                            <span>${fac.Nama_Fasilitas}</span>
                                        </div>
                                    `;
                                });
                                facHtml += '</div>';
                                detFacList.innerHTML = facHtml;
                            } else {
                                detFacList.innerHTML = '<span class="empty-note-facilities">Tidak ada fasilitas terpasang</span>';
                            }
                        }

                        document.getElementById('modalDetail').classList.add('open');
                    } else {
                        Swal.fire('Gagal!', res.msg, 'error');
                    }
                });
        }

        // Tutup Modal secara Langsung
        function closeModalDirect(modalId) {
            document.getElementById(modalId).classList.remove('open');
        }

        // Fungsi membuka & menutup dropdown list
        function toggleMultiselect(event) {
            event.stopPropagation();
            const content = document.getElementById('multiselectContent');
            if (content) {
                content.classList.toggle('open');
            }
        }

        // Fungsi memperbarui teks header dropdown berdasarkan jumlah yang diceklis
        function updateMultiselectHeader() {
            const selectedCount = document.querySelectorAll('.facility-checkbox:checked').length;
            const label = document.getElementById('multiselectLabel');
            const valFacilities = document.getElementById('val-facilities');
            const dropdownHeader = document.querySelector('.multiselect-header');

            if (label) {
                if (selectedCount === 0) {
                    label.innerText = 'Pilih Fasilitas Lapangan';
                } else {
                    label.innerText = selectedCount + ' Fasilitas Terpilih';
                }
            }

            // Hapus pesan eror secara real-time jika sudah ada yang dicentang
            if (selectedCount > 0) {
                if (valFacilities && dropdownHeader) {
                    dropdownHeader.classList.remove('error'); // Menghapus class error
                    valFacilities.innerHTML = '';
                    valFacilities.classList.remove('show');
                }
            }
        }

        // Menutup dropdown otomatis jika mengklik area lain di luar dropdown
        document.addEventListener('click', function (e) {
            const dropdown = document.getElementById('facilityDropdown');
            const content = document.getElementById('multiselectContent');
            if (dropdown && content && !dropdown.contains(e.target)) {
                content.classList.remove('open');
            }
        });

        // Fungsi pencarian global server-side dengan tombol Enter
        function handleSearch(event) {
            if (event.key === 'Enter') {
                const keyword = document.getElementById('src').value.trim();
                const urlParams = new URLSearchParams(window.location.search);

                if (keyword) {
                    urlParams.set('src', keyword);
                } else {
                    urlParams.delete('src');
                }

                urlParams.set('page', 1); // Reset ke halaman 1 setiap kali mencari data baru
                window.location.href = 'lapangan.php?' + urlParams.toString();
            }
        }

        function clearSearch() {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.delete('src'); // Hapus kata kunci pencarian
            urlParams.set('page', 1); // Reset kembali ke halaman 1
            window.location.href = 'lapangan.php?' + urlParams.toString();
        }

    </script>
</body>

</html>