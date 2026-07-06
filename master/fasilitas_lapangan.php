<?php
session_start();

include '../includes/config.php';
include '../includes/helpers.php';

if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'karyawan' && $_SESSION['role'] !== 'pemilik')) {
    echo "<script>alert('Akses Ditolak!'); window.location='../dashboard/dashboard.php';</script>";
    exit();
}

include '../includes/auth_profile.php';

$current_page = 'fasilitas';
$topbar_title = 'Kelola Fasilitas';
$topbar_breadcrumb = 'Operasional / Fasilitas Lapangan';

// --- (AJAX Handler Detail & Edit) ---
if (isset($_GET['ajax_detail_id'])) {
    header('Content-Type: application/json');
    $r = safeQuery($conn, "EXEC dbo.sp_GetFasilitasDetail ?", [intval($_GET['ajax_detail_id'])]);
    if ($r) {
        $detail_data = safeFetch($r);
        if ($detail_data) {
            echo json_encode(['status' => 'success', 'data' => $detail_data]);
        } else {
            echo json_encode(['status' => 'error', 'msg' => 'Data fasilitas tidak ditemukan.']);
        }
    } else {
        echo json_encode(['status' => 'error', 'msg' => 'Gagal mengambil data dari database.']);
    }
    exit();
}

// PROSES CRUD (SIMPAN / EDIT)
if (isset($_POST['save_fasilitas'])) {
    $id = isset($_POST['id_fas']) ? intval($_POST['id_fas']) : 0;
    $nama_fasilitas = trim($_POST['nama_fasilitas']);
    $detail_fasilitas = trim($_POST['detail_fasilitas'] ?? '');
    $stok_total = isset($_POST['stok_total']) ? intval($_POST['stok_total']) : 0;

    $errors = [];
    // Validasi format nama dan detail
    if (!preg_match('/^[a-zA-Z\s]+$/', $nama_fasilitas)) {
        $errors[] = "Nama fasilitas hanya boleh berisi huruf dan spasi!";
    }
    if (!preg_match('/^[a-zA-Z\s]+$/', $detail_fasilitas)) {
        $errors[] = "Detail fasilitas hanya boleh berisi huruf dan spasi!";
    }
    if ($stok_total <= 0) {
        $errors[] = "Stok total harus lebih dari 0!";
    }

    if (empty($errors)) {
        // Cek duplikasi nama fasilitas secara global (Menggunakan SP baru)
        $q_check = safeQuery($conn, "EXEC dbo.sp_CheckFasilitasDuplicate ?, ?", [$nama_fasilitas, $id]);
        if ($q_check && safeFetch($q_check)) {
            $errors[] = "Nama fasilitas sudah terdaftar di sistem!";
        }
    }

    if (empty($errors)) {
        if (isset($_POST['edit_mode']) && $id > 0) {
            // EXEC sp_UpdateFasilitas memiliki 5 parameter sekarang
            $result = safeQuery($conn, "EXEC dbo.sp_UpdateFasilitas ?, ?, ?, ?, ?", [$id, $nama_fasilitas, $detail_fasilitas, $stok_total, $nama]);
            if ($result !== false) {
                header("Location: fasilitas_lapangan.php?page=1&status=success&msg=Fasilitas berhasil diperbarui!");
            } else {
                header("Location: fasilitas_lapangan.php?page=1&status=error&msg=Gagal memperbarui fasilitas. Stok baru tidak boleh kurang dari jumlah terpasang!");
            }
        } else {
            // EXEC sp_CreateFasilitas memiliki 4 parameter sekarang (tanpa ID_Lapangan)
            safeQuery($conn, "EXEC dbo.sp_CreateFasilitas ?, ?, ?, ?", [$nama_fasilitas, $detail_fasilitas, $stok_total, $nama]);
            header("Location: fasilitas_lapangan.php?page=1&status=success&msg=Fasilitas baru berhasil ditambahkan!");
        }
    } else {
        header("Location: fasilitas_lapangan.php?page=1&status=error&msg=" . urlencode(implode(' | ', $errors)));
    }
    exit();
}

if (isset($_GET['toggle_id'])) {
    $s_baru = ($_GET['s'] == 1) ? 0 : 1;
    $stmt = safeQuery($conn, "EXEC dbo.sp_UpdateStatusFasilitas ?, ?, ?", [$_GET['toggle_id'], $s_baru, $nama]);

    // Jika diproses lewat AJAX, kembalikan respon JSON tanpa reload
    if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'msg' => 'Status fasilitas berhasil diubah!', 'new_status' => $s_baru]);
        exit();
    }

    header("Location: fasilitas_lapangan.php?page=1&status=success&msg=Status fasilitas berhasil diubah!");
    exit();
}

if (isset($_GET['delete_id'])) {
    $stmt = safeQuery($conn, "EXEC dbo.sp_DeleteFasilitas ?, ?", [$_GET['delete_id'], $nama]);

    // Jika diproses lewat AJAX, kembalikan respon JSON tanpa reload
    if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'msg' => 'Fasilitas berhasil dihapus!']);
        exit();
    }

    header("Location: fasilitas_lapangan.php?page=1&status=success&msg=Fasilitas berhasil dihapus!");
    exit();
}

// Mengambil statistik menggunakan UDF
$q_stats = safeQuery($conn, "SELECT Total, Aktif, Nonaktif FROM dbo.fn_GetFasilitasStats()", []);
$stats = safeFetch($q_stats);

$total_fasilitas = $stats['Total'] ?? 0;
$aktif_count = $stats['Aktif'] ?? 0;
$nonaktif_count = $stats['Nonaktif'] ?? 0;

// Mengambil dropdown list Lapangan menggunakan SP
$lapangan_list = [];
$q_lap = safeQuery($conn, "EXEC dbo.sp_GetActiveLapanganList", []);
if ($q_lap) {
    while ($row = sqlsrv_fetch_array($q_lap, SQLSRV_FETCH_ASSOC)) {
        $lapangan_list[] = $row;
    }
}

$limit = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

$f_lapangan = isset($_GET['f_lapangan']) && $_GET['f_lapangan'] !== '' ? $_GET['f_lapangan'] : 'all';
$f_status = isset($_GET['f_status']) && $_GET['f_status'] !== '' ? $_GET['f_status'] : 'all';
$f_sort = $_GET['f_sort'] ?? 'nama_asc';

$search = isset($_GET['src']) ? trim($_GET['src']) : '';



// Menghitung offset paging
$offset = ($page - 1) * $limit;

// Memanggil SP List Utama terpaginasi dengan mengirimkan 6 parameter (termasuk $search)
$query_sql = "EXEC dbo.sp_ReadFasilitasListWithCount ?, ?, ?, ?, ?, ?";
$params_sp = array($f_lapangan, $f_status, $f_sort, intval($offset), intval($limit), $search);

$query = safeQuery($conn, $query_sql, $params_sp);

// Ambil jumlah data terfilter (Hasil 1 dari SP)
$row_count = safeFetch($query);
$total_data = intval($row_count['TotalCount'] ?? 0);

// Geser ke hasil list data fasilitas (Hasil 2 dari SP)
sqlsrv_next_result($query);

// Hitung ulang halaman berdasarkan total data terfilter
$total_pages = max(1, ceil($total_data / $limit));
$page = min($page, $total_pages);


$filter_url = "";
if (isset($_GET['f_sort']))
    $filter_url .= "&f_sort=" . urlencode($_GET['f_sort']);
if (isset($_GET['f_lapangan']))
    $filter_url .= "&f_lapangan=" . urlencode($_GET['f_lapangan']);
if (isset($_GET['f_status']))
    $filter_url .= "&f_status=" . urlencode($_GET['f_status']);
if (!empty($search)) {
    $filter_url .= "&src=" . urlencode($search);
}


?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Kelola Fasilitas Lapangan | HoopBall</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800;900&family=Barlow:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../asset/css/global.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
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
            font-family: 'Barlow Condensed', sans-serif;
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

        /* ═══ CARD & TABLE (SINKRON DENGAN LAPANGAN) ═══ */
        .card {
            background: var(--card-bg);
            border-radius: 16px;
            border: 1px solid var(--border);
            overflow: hidden;
            transition: all .2s ease;
            background-color: #FFFFFF !important;
        }


        .card:hover {
            box-shadow: 0 8px 24px rgba(0, 0, 0, .06);
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

        .data-table th,
        .data-table td {
            padding: 16px 20px;
            vertical-align: middle;
        }

        /* 1. Kolom No (Rata Tengah) */
        .data-table th:nth-child(1),
        .data-table td:nth-child(1) {
            text-align: center !important;
            padding-left: 65px !important;
            width: 8%;
            font-size: 15px;
            font-weight: 700;
        }

        /* 2. Kolom Nama Fasilitas */
        .data-table th:nth-child(2),
        .data-table td:nth-child(2) {
            width: 32%;
            text-align: left !important;
            padding-left: 135px !important;
        }

        .fas-name {
            font-weight: 700;
            color: var(--text);
            font-size: 15px;
        }

        .fas-detail {
            font-size: 12px;
            color: var(--muted);
            margin-top: 2px;
        }

        /* 3. Kolom Stok (Sisa/Total) */
        .data-table th:nth-child(3),
        .data-table td:nth-child(3) {
            width: 22%;
            text-align: center !important;
        }

        /* 4. Kolom Status (Tengah Presisi) */
        .data-table th:nth-child(4),
        .data-table td:nth-child(4) {
            width: 18%;
            text-align: center !important;
            padding-left: 0 !important;
        }

        .data-table th:nth-child(4) {
            position: relative;
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

        /* 5. Kolom Aksi (Rata Kiri) */
        .data-table th:nth-child(5),
        .data-table td:nth-child(5) {
            width: 20%;
            text-align: Center !important;
            /* Menggunakan CENTER */
        }

        /* ═══ STATUS PILL (SAMAKAN DENGAN LAPANGAN) ═══ */
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

        /* ═══ ACTIONS ═══ */
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
            transition: all .25s cubic-bezier(.4, 0, .2, 1);
            border: 1.5px solid transparent;
            cursor: pointer;
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

        /* ═══ TOGGLE SWITCH (SAMAKAN DENGAN LAPANGAN) ═══ */
        .toggle-switch {
            position: relative;
            display: inline-flex;
            align-items: center;
            width: 44px;
            height: 24px;
            cursor: pointer;
            margin: 0;
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
            box-shadow: 0 2px 4px rgba(0, 0, 0, .2);
        }

        .toggle-switch:hover .toggle-slider {
            opacity: .9;
        }

        /* ═══ ZEBRA STRIPING & HOVER (SAMAKAN DENGAN LAPANGAN) ═══ */
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


        .toggle-switch input:checked+.toggle-slider {
            background-color: var(--green);
        }

        .toggle-switch input:checked+.toggle-slider::before {
            transform: translateX(20px);
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
            width: 480px;
            overflow: hidden;
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
            margin-bottom: 16px;
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
        }

        .modal-close:hover {
            background: var(--red-lt);
            color: var(--red);
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
            padding: 14px 0;
            border-bottom: 1px solid var(--border-lt);
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-key {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
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
            font-size: 14px;
            font-weight: 700;
            color: var(--text);
        }


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

            .modal-box {
                width: 90%;
                margin: 20px;
            }
        }
    </style>
</head>

<body>
    <?php include '../includes/sidebar.php'; ?>
    <!-- MODAL FORM -->
    <div class="modal-overlay" id="modalFasilitas">
        <div class="modal-box">
            <button type="button" class="modal-close" onclick="closeModalDirect('modalFasilitas')"><i
                    class="fa-solid fa-xmark"></i></button>
            <div class="modal-header">
                <div class="modal-subtitle">Kelola Fasilitas</div>
                <div class="modal-title" id="formModalTitle">Tambah Fasilitas Baru</div>
            </div>
            <div class="modal-body">
                <form method="POST" id="formFasilitas" onsubmit="return validateForm()" novalidate>
                    <!-- Hidden inputs untuk edit mode -->
                    <div id="hiddenInputsArea"></div>

                    <label class="modal-label">Stok Total <span class="required">*</span></label>
                    <input type="number" name="stok_total" id="stok_total" class="modal-input" required min="1"
                        placeholder="Contoh: 15" autocomplete="off">
                    <div class="val-msg" id="val-stok_total"></div>

                    <label class="modal-label">Nama Fasilitas <span class="required">*</span></label>
                    <input type="text" name="nama_fasilitas" id="nama_fasilitas" class="modal-input" required
                        minlength="3" maxlength="50" placeholder="Contoh: Bola Basket Spalding" autocomplete="off">
                    <div class="val-msg" id="val-nama_fasilitas"></div>

                    <label class="modal-label">Detail Fasilitas <span class="required">*</span></label>
                    <input type="text" name="detail_fasilitas" id="detail_fasilitas" class="modal-input" required
                        maxlength="50" placeholder="Contoh: Bola basket standar SNI" autocomplete="off">
                    <div class="val-msg" id="val-detail_fasilitas"></div>

                    <button type="submit" name="save_fasilitas" class="btn-submit" id="btnSubmitForm">
                        <i class="fa-solid fa-plus"></i> Tambah Fasilitas
                    </button>
                    <a onclick="closeModalDirect('modalFasilitas')" class="btn-cancel">Batal</a>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL DETAIL -->
    <div class="modal-overlay" id="modalDetail">
        <div class="modal-box" style="width: 440px;">
            <button type="button" class="modal-close" onclick="closeModalDirect('modalDetail')"><i
                    class="fa-solid fa-xmark"></i></button>
            <div class="modal-header" style="border-bottom: none; padding-bottom: 0;">
                <div class="modal-subtitle">Informasi Fasilitas</div>
                <div class="modal-title">Detail Fasilitas</div>
            </div>
            <div class="modal-body" style="padding-top: 10px;">
                <div
                    style="text-align: center; margin-bottom: 24px; padding-bottom: 20px; border-bottom: 1.5px dashed var(--border);">
                    <div class="detail-icon-wrap"><i class="fa-solid fa-list-check"></i></div>
                    <div class="detail-main-name" id="det_nama_title">-</div>
                </div>
                <div class="info-row">
                    <span class="info-key"><i class="fa-solid fa-layer-group"></i> Stok Total</span>
                    <span class="info-val" id="det_stok_total" style="font-weight:800; color:var(--text);">-</span>
                </div>
                <div class="info-row">
                    <span class="info-key"><i class="fa-solid fa-box-archive"></i> Stok Tersedia</span>
                    <span class="info-val" id="det_stok_tersedia" style="font-weight:800; color:var(--green);">-</span>
                </div>
                <div class="info-row">
                    <span class="info-key"><i class="fa-solid fa-circle-info"></i> Detail Fasilitas</span>
                    <span class="info-val" id="det_detail" style="font-weight:700;">-</span>
                </div>
                <div class="info-row" style="border-bottom:none;">
                    <span class="info-key"><i class="fa-solid fa-circle-check"></i> Status Fasilitas</span>
                    <span class="info-val" id="det_status_pill_wrap">
                        <span class="status-pill sp-active" id="det_status_pill">
                            <span class="sp-dot"></span>
                            <span id="det_status_text">AKTIF</span>
                        </span>
                    </span>
                </div>
                <button type="button" onclick="closeModalDirect('modalDetail')" class="btn-submit"
                    style="margin-top: 24px; background: #0D1117;">
                    <i class="fa-solid fa-arrow-left"></i> Kembali Ke List
                </button>
            </div>
        </div>
    </div>

    <!-- MAIN -->
    <main class="main">
        <?php include '../includes/topbar.php'; ?>
        <div class="content">
            <div class="page-header">
                <div>
                    <div class="page-title-tag"></div>
                    <div class="page-title">Kelola Fasilitas</div>
                </div>
                <div class="stat-chips">
                    <div class="stat-chip chip-green"><i class="fa-solid fa-circle-check"></i> AKTIF <span
                            class="chip-val"><?= $aktif_count ?></span></div>
                    <div class="stat-chip chip-red"><i class="fa-solid fa-circle-xmark"></i> NONAKTIF <span
                            class="chip-val"><?= $nonaktif_count ?></span></div>
                    <div class="stat-chip chip-blue"><i class="fa-solid fa-list"></i> TOTAL <span
                            class="chip-val"><?= $total_fasilitas ?></span></div>
                </div>
            </div>

            <div class="action-bar">
                <div class="search-box">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="src" placeholder="Cari fasilitas... (Tekan Enter)"
                        onkeypress="handleSearch(event)" value="<?= htmlspecialchars($_GET['src'] ?? '') ?>">

                    <?php if (!empty($search)): ?>
                        <button type="button" onclick="clearSearch()" class="btn-clear-search">
                            <i class="fa-solid fa-circle-xmark"></i>
                        </button>
                    <?php endif; ?>
                </div>
                <div style="display: flex; gap: 12px; align-items: center;">
                    <div class="filter-dropdown-wrap">
                        <button class="btn-filter" id="btnFilterToggle">
                            <i class="fa-solid fa-filter"></i> Filter <i
                                class="fa-solid fa-chevron-down arrow-icon"></i>
                        </button>
                        <div class="filter-card" id="filterCard">
                            <h4>Filter Data</h4>
                            <form method="GET" action="fasilitas_lapangan.php">
                                <div class="filter-group">
                                    <label>Lapangan</label>
                                    <select name="f_lapangan" class="filter-input">
                                        <option value="">Semua Lapangan</option>
                                        <?php foreach ($lapangan_list as $lap): ?>
                                            <option value="<?= $lap['ID_Lapangan'] ?>" <?= ($_GET['f_lapangan'] ?? '') == $lap['ID_Lapangan'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($lap['Nama_Lapangan']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="filter-group">
                                    <label>Status</label>
                                    <select name="f_status" class="filter-input">
                                        <option value="">Semua Status</option>
                                        <option value="1" <?= ($_GET['f_status'] ?? '') === '1' ? 'selected' : '' ?>>AKTIF
                                        </option>
                                        <option value="0" <?= ($_GET['f_status'] ?? '') === '0' ? 'selected' : '' ?>>
                                            NONAKTIF</option>
                                    </select>
                                </div>
                                <div class="filter-group">
                                    <label>Urutkan</label>
                                    <select name="f_sort" class="filter-input">
                                        <option value="nama_asc" <?= ($_GET['f_sort'] ?? '') === 'nama_asc' ? 'selected' : '' ?>>Nama Fasilitas (A - Z)</option>
                                        <option value="nama_desc" <?= ($_GET['f_sort'] ?? '') === 'nama_desc' ? 'selected' : '' ?>>Nama Fasilitas (Z - A)</option>
                                        <option value="stok_desc" <?= ($_GET['f_sort'] ?? '') === 'stok_desc' ? 'selected' : '' ?>>Stok Terbanyak</option>
                                        <option value="stok_asc" <?= ($_GET['f_sort'] ?? '') === 'stok_asc' ? 'selected' : '' ?>>Stok Tersedikit</option>
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

            <div class="card">
                <div class="table-wrap">
                    <table class="data-table" id="tbl">
                        <thead>
                            <tr>
                                <th style="width: 80px;">No</th>
                                <th>Nama Fasilitas</th>
                                <th style="width: 150px;">Stok (Sisa/Total)</th>
                                <th style="width: 150px;">Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $has_data = false;
                            $no = $offset + 1;
                            if ($query):
                                while ($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)):
                                    $has_data = true;
                                    ?>
                                    <tr>
                                        <td
                                            style="font-family:'Barlow Condensed', sans-serif; font-weight:700; color:var(--text);">
                                            <?= $no++ ?>
                                        </td>
                                        <td>
                                            <div class="fas-name"><?= htmlspecialchars($row['Nama_Fasilitas']) ?></div>
                                        </td>
                                        <td>
                                            <div
                                                style="font-family:'Barlow Condensed', sans-serif; font-weight:700; font-size:15px; color:var(--text);">
                                                <span style="color: var(--green);"><?= intval($row['Stok_Tersedia']) ?></span> /
                                                <span><?= intval($row['Stok_Total']) ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="status-pill <?= $row['Status'] == 1 ? 'sp-active' : 'sp-inactive' ?>">
                                                <span class="sp-dot"></span>
                                                <?= $row['Status'] == 1 ? 'AKTIF' : 'NONAKTIF' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="actions">
                                                <button type="button" onclick="showDetail('<?= $row['ID_Fasilitas'] ?>')"
                                                    class="btn-action btn-view" title="Lihat Detail"><i
                                                        class="fa-solid fa-eye"></i></button>
                                                <button type="button" onclick="showEditForm('<?= $row['ID_Fasilitas'] ?>')"
                                                    class="btn-action btn-edit" title="Edit Fasilitas"><i
                                                        class="fa-solid fa-pen-to-square"></i></button>
                                                <label class="toggle-switch"
                                                    title="<?= $row['Status'] == 1 ? 'Nonaktifkan' : 'Aktifkan' ?>">
                                                    <input type="checkbox" <?= $row['Status'] == 1 ? 'checked' : '' ?>
                                                        onchange="confirmToggle('<?= $row['ID_Fasilitas'] ?>', <?= $row['Status'] ?>)">
                                                    <span class="toggle-slider"></span>
                                                </label>
                                                <button
                                                    onclick="confirmDelete('<?= $row['ID_Fasilitas'] ?>', '<?= htmlspecialchars($row['Nama_Fasilitas']) ?>')"
                                                    class="btn-action btn-delete" title="Hapus Fasilitas"><i
                                                        class="fa-solid fa-trash-can"></i></button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; endif; ?>
                            <?php if (!$has_data): ?>
                                <tr>
                                    <td colspan="5">
                                        <div class="empty-state">
                                            <i class="fa-solid fa-list-check"></i>
                                            <div>Belum ada data fasilitas</div>
                                            <div style="font-size: 12px; font-weight: 500; margin-top: 8px; opacity: .7;">
                                                Tambah fasilitas baru untuk memulai</div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- PAGINATION -->
            <div class="pagination-wrap">
                <div class="pagination-info">
                    <?php if ($total_data > 0): ?>
                        Menampilkan <strong><?= (($page - 1) * $limit) + 1 ?></strong> -
                        <strong><?= min($page * $limit, $total_data) ?></strong> dari <strong><?= $total_data ?></strong>
                        data
                    <?php else: ?>
                        Menampilkan <strong>0</strong> data
                    <?php endif; ?>
                </div>

                <div class="pagination-nav">
                    <!-- Tombol First -->
                    <a href="?page=1<?= $filter_url ?>" class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>">
                        <i class="fa-solid fa-angles-left"></i>
                    </a>
                    <!-- Tombol Prev -->
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
                    <!-- Tombol Next -->
                    <a href="?page=<?= min($total_pages, $page + 1) ?><?= $filter_url ?>"
                        class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>">
                        <i class="fa-solid fa-angle-right"></i>
                    </a>
                    <!-- Tombol Last -->
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
        // ============================================
        // VALIDASI FORM - REAL TIME & SUBMIT
        // ============================================
        function validateField(fieldId, valId, rules) {
            const field = document.getElementById(fieldId);
            const valMsg = document.getElementById(valId);
            const value = field.value.trim();

            field.classList.remove('error');
            valMsg.classList.remove('show');

            if (rules.required && value === '') {
                field.classList.add('error');
                valMsg.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> ' + rules.label + ' wajib diisi';
                valMsg.classList.add('show');
                return false;
            }

            if (rules.minLength && value.length < rules.minLength) {
                field.classList.add('error');
                valMsg.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Minimal ' + rules.minLength + ' karakter';
                valMsg.classList.add('show');
                return false;
            }

            if (rules.maxLength && value.length > rules.maxLength) {
                field.classList.add('error');
                valMsg.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Maksimal ' + rules.maxLength + ' karakter';
                valMsg.classList.add('show');
                return false;
            }

            if (rules.pattern && value !== '') {
                const regex = /^[a-zA-Z\s]+$/; // <--- HANYA HURUF DAN SPASI
                if (!regex.test(value)) {
                    field.classList.add('error');
                    valMsg.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> ' + rules.label + ' hanya boleh berisi huruf';
                    valMsg.classList.add('show');
                    return false;
                }
            }

            return true;
        }

        function validateForm() {
            let valid = true;

            // Validasi Input Stok Total (Baru)
            if (!validateField('stok_total', 'val-stok_total', {
                required: true,
                label: 'Stok total'
            })) valid = false;

            if (!validateField('nama_fasilitas', 'val-nama_fasilitas', {
                required: true,
                minLength: 3,
                maxLength: 50,
                pattern: true,
                label: 'Nama fasilitas'
            })) valid = false;

            if (!validateField('detail_fasilitas', 'val-detail_fasilitas', {
                required: true,
                maxLength: 50,
                pattern: true,
                label: 'Detail fasilitas'
            })) valid = false;

            return valid;
        }

        // ============================================
        // TOGGLE STATUS
        // ============================================
        function confirmToggle(id, status) {
            const action = status == 1 ? 'nonaktifkan' : 'aktifkan';
            const iconType = status == 1 ? 'warning' : 'question';
            const newStatus = status == 1 ? 0 : 1;

            Swal.fire({
                title: 'Konfirmasi Perubahan Status',
                text: 'Apakah Anda yakin ingin ' + action + ' fasilitas ini?',
                icon: iconType,
                showCancelButton: true,
                confirmButtonColor: '#FF4500',
                cancelButtonColor: '#6B7280',
                confirmButtonText: 'Ya, ' + action + '!',
                cancelButtonText: 'Batal',
                reverseButtons: true,
                allowOutsideClick: false
            }).then((result) => {
                if (result.isConfirmed) {
                    // Mengirim request ke server di latar belakang menggunakan AJAX
                    fetch('?toggle_id=' + id + '&s=' + status + '&ajax=1')
                        .then(response => response.json())
                        .then(data => {
                            if (data.status === 'success') {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Berhasil!',
                                    text: data.msg,
                                    timer: 1500,
                                    showConfirmButton: false
                                });

                                // Cari baris tabel tempat tombol ini diklik
                                const checkbox = document.querySelector('input[onchange*="confirmToggle(\'' + id + '\'"]');
                                const row = checkbox.closest('tr');
                                const pill = row.querySelector('.status-pill');

                                // Perbarui UI secara langsung tanpa memuat ulang halaman
                                if (newStatus === 1) {
                                    pill.className = 'status-pill sp-active';
                                    pill.innerHTML = '<span class="sp-dot"></span> AKTIF';
                                    checkbox.checked = true;
                                    checkbox.setAttribute('onchange', "confirmToggle('" + id + "', 1)");
                                } else {
                                    pill.className = 'status-pill sp-inactive';
                                    pill.innerHTML = '<span class="sp-dot"></span> NONAKTIF';
                                    checkbox.checked = false;
                                    checkbox.setAttribute('onchange', "confirmToggle('" + id + "', 0)");
                                }
                            } else {
                                Swal.fire('Gagal!', data.msg, 'error');
                                const checkbox = document.querySelector('input[onchange*="confirmToggle(\'' + id + '\'"]');
                                if (checkbox) checkbox.checked = !checkbox.checked;
                            }
                        });
                } else {
                    // Reset checkbox jika batal
                    const checkbox = document.querySelector('input[onchange*="confirmToggle(\'' + id + '\'"]');
                    if (checkbox) checkbox.checked = !checkbox.checked;
                }
            });
        }

        // ============================================
        // DELETE CONFIRMATION
        // ============================================
        function confirmDelete(id, name) {
            Swal.fire({
                title: 'Hapus Fasilitas?',
                html: 'Anda akan menghapus fasilitas <strong style="color:var(--orange);">' + name + '</strong><br><span style="font-size:12px;color:var(--muted);">Data akan dihapus secara Permanen</span>',
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
                    // Mengirim request hapus data ke server di latar belakang
                    fetch('?delete_id=' + id + '&ajax=1')
                        .then(response => response.json())
                        .then(data => {
                            if (data.status === 'success') {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Terhapus!',
                                    text: data.msg,
                                    timer: 1500,
                                    showConfirmButton: false
                                });

                                // Cari baris tombol hapus tempat diklik
                                const btnDelete = document.querySelector('button[onclick*="confirmDelete(\'' + id + '\'"]');
                                if (btnDelete) {
                                    const row = btnDelete.closest('tr');

                                    // Berikan animasi geser & memudar sebelum dihapus secara live
                                    row.style.transition = 'all 0.5s ease';
                                    row.style.opacity = '0';
                                    row.style.transform = 'translateX(-20px)';
                                    setTimeout(() => {
                                        row.remove();
                                    }, 500);
                                }
                            } else {
                                Swal.fire('Gagal!', data.msg, 'error');
                            }
                        });
                }
            });
        }

        function resetFilter() {
            window.location.href = 'fasilitas_lapangan.php';
        }

        // ============================================
        // REAL-TIME VALIDATION EVENT LISTENERS
        // ============================================
        document.addEventListener('DOMContentLoaded', function () {
            const namaFas = document.getElementById('nama_fasilitas');
            if (namaFas) {
                namaFas.addEventListener('blur', function () {
                    validateField('nama_fasilitas', 'val-nama_fasilitas', {
                        required: true,
                        minLength: 3,
                        maxLength: 50,
                        pattern: true,
                        label: 'Nama fasilitas'
                    });
                });
                namaFas.addEventListener('input', function () {
                    if (this.classList.contains('error')) {
                        validateField('nama_fasilitas', 'val-nama_fasilitas', {
                            required: true,
                            minLength: 3,
                            maxLength: 50,
                            pattern: true,
                            label: 'Nama fasilitas'
                        });
                    }
                });
            }

            const detailFas = document.getElementById('detail_fasilitas');
            if (detailFas) {
                detailFas.addEventListener('blur', function () {
                    validateField('detail_fasilitas', 'val-detail_fasilitas', {
                        required: true,
                        maxLength: 50,
                        pattern: true,
                        label: 'Detail fasilitas'
                    });
                });
                detailFas.addEventListener('input', function () {
                    if (this.classList.contains('error')) {
                        validateField('detail_fasilitas', 'val-detail_fasilitas', {
                            required: true,
                            maxLength: 50,
                            pattern: true,
                            label: 'Detail fasilitas'
                        });
                    }
                });
            }
        });

        // Fungsi Buka Form Tambah Baru
        function showAddForm() {
            // Reset Form Input
            document.getElementById('formFasilitas').reset();
            document.getElementById('hiddenInputsArea').innerHTML = '';

            // Reset Error Visuals
            document.querySelectorAll('.modal-input').forEach(el => el.classList.remove('error'));
            document.querySelectorAll('.val-msg').forEach(el => el.classList.remove('show'));

            // Set Title & Button Icon
            document.getElementById('formModalTitle').innerText = 'Tambah Fasilitas Baru';
            document.getElementById('btnSubmitForm').innerHTML = '<i class="fa-solid fa-plus"></i> Tambah Fasilitas';

            // Buka Modal
            document.getElementById('modalFasilitas').classList.add('open');
        }

        // Fungsi Buka Form Edit Data (AJAX)
        function showEditForm(id) {
            // Ambil data fasilitas dari latar belakang menggunakan Fetch API
            fetch('?ajax_detail_id=' + id)
                .then(response => response.json())
                .then(res => {
                    if (res.status === 'success') {
                        const data = res.data;

                        document.getElementById('stok_total').value = data.Stok_Total; // Isi ke Stok Total
                        document.getElementById('nama_fasilitas').value = data.Nama_Fasilitas;
                        document.getElementById('detail_fasilitas').value = data.Detail_Fasilitas;

                        // Set hidden input agar form dikirim sebagai EDIT MODE
                        document.getElementById('hiddenInputsArea').innerHTML = `
                    <input type="hidden" name="edit_mode" value="1">
                    <input type="hidden" name="id_fas" id="id_fas" value="${data.ID_Fasilitas}">
                `;

                        // Ubah Title & Button Icon
                        document.getElementById('formModalTitle').innerText = 'Edit Fasilitas';
                        document.getElementById('btnSubmitForm').innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Simpan Perubahan';

                        // Reset visual error jika ada bekas validasi sebelumnya
                        document.querySelectorAll('.modal-input').forEach(el => el.classList.remove('error'));
                        document.querySelectorAll('.val-msg').forEach(el => el.classList.remove('show'));

                        // Buka Modal
                        document.getElementById('modalFasilitas').classList.add('open');
                    } else {
                        Swal.fire('Gagal!', res.msg, 'error');
                    }
                });
        }

        // Fungsi Tampilkan Detail Fasilitas (AJAX)
        function showDetail(id) {
            fetch('?ajax_detail_id=' + id)
                .then(response => response.json())
                .then(res => {
                    if (res.status === 'success') {
                        const data = res.data;

                        // Tulis nilai ke dalam kolom modal detail secara dinamis
                        document.getElementById('det_nama_title').innerText = data.Nama_Fasilitas;
                        document.getElementById('det_detail').innerText = data.Detail_Fasilitas ? data.Detail_Fasilitas : '-';
                        document.getElementById('det_stok_total').innerText = data.Stok_Total + ' unit';
                        document.getElementById('det_stok_tersedia').innerText = data.Stok_Tersedia + ' unit';

                        // Pengaturan warna badge status keaktifan
                        const pill = document.getElementById('det_status_pill');
                        const text = document.getElementById('det_status_text');
                        if (data.Status == 1) {
                            pill.className = 'status-pill sp-active';
                            text.innerText = 'AKTIF';
                        } else {
                            pill.className = 'status-pill sp-inactive';
                            text.innerText = 'NONAKTIF';
                        }

                        // Buka Modal Detail
                        document.getElementById('modalDetail').classList.add('open');
                    } else {
                        Swal.fire('Gagal!', res.msg, 'error');
                    }
                });
        }

        // Fungsi Menutup Modal Secara Langsung
        function closeModalDirect(modalId) {
            document.getElementById(modalId).classList.remove('open');
        }

        function handleSearch(event) {
            if (event.key === 'Enter') {
                const keyword = document.getElementById('src').value.trim();
                const urlParams = new URLSearchParams(window.location.search);

                if (keyword) {
                    urlParams.set('src', keyword);
                } else {
                    urlParams.delete('src');
                }

                urlParams.set('page', 1); // Reset kembali ke halaman 1
                window.location.href = 'fasilitas_lapangan.php?' + urlParams.toString();
            }
        }

        function clearSearch() {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.delete('src'); // Hapus kata kunci pencarian
            urlParams.set('page', 1); // Reset kembali ke halaman 1
            window.location.href = 'fasilitas_lapangan.php?' + urlParams.toString();
        }

    </script>
</body>

</html>