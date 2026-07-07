<?php
session_start();
include '../includes/config.php';
include '../includes/helpers.php';

if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'karyawan' && $_SESSION['role'] !== 'pemilik')) {
    echo "<script>alert('Akses Ditolak!'); window.location='../dashboard/dashboard.php';</script>";
    exit();
}

include '../includes/auth_profile.php';

$current_page = 'customer';
$topbar_title = 'Kelola Customer';
$topbar_breadcrumb = 'Operasional / Customer';

// --- TOGGLE STATUS ---
if (isset($_GET['toggle_id'])) {
    $toggle_id = $_GET['toggle_id'];
    $stmt_check = safe_sqlsrv_query($conn, "EXEC dbo.sp_GetCustomerDetail ?", array($toggle_id), false);
    if ($stmt_check !== false) {
        $row_check = safe_sqlsrv_fetch_array($stmt_check, SQLSRV_FETCH_ASSOC);
        if ($row_check) {
            $current_status = intval($row_check['Status'] ?? 1);
            $new_status = $current_status === 1 ? 0 : 1;
            $modified_by = $_SESSION['nama'] ?? 'SYSTEM';

            $stmt_toggle = safe_sqlsrv_query($conn, 
                "EXEC dbo.sp_UpdateStatusCustomer ?, ?, ?", 
                array($toggle_id, $new_status, $modified_by), false
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
    $r_detail = safe_sqlsrv_query($conn, "EXEC dbo.sp_GetCustomerDetail ?", array($_GET['detail_id']), false);
    if ($r_detail) {
        $detail_data = safe_sqlsrv_fetch_array($r_detail, SQLSRV_FETCH_ASSOC);
        $show_detail = true;
    }
}

// --- Tangkap parameter pencarian 'src' ---
$search = isset($_GET['src']) ? trim($_GET['src']) : '';

$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'ID_Customer';
$sort_order = isset($_GET['order']) && strtoupper($_GET['order']) == 'DESC' ? 'DESC' : 'ASC';
$filter_jk = isset($_GET['jk']) ? intval($_GET['jk']) : -1;
$filter_status = isset($_GET['status_filter']) ? $_GET['status_filter'] : 'all';

$allowed_sort = ['ID_Customer', 'Nama_Customer', 'Jenis_Kelamin', 'Alamat', 'No_Telepon', 'Email', 'Created_Date'];
if (!in_array($sort_by, $allowed_sort)) $sort_by = 'ID_Customer';

// --- Susun parameter URL untuk pagination secara dinamis ---
$filter_url = "&sort=" . urlencode($sort_by) . "&order=" . urlencode($sort_order) . "&jk=" . urlencode($filter_jk) . "&status_filter=" . urlencode($filter_status);
if (!empty($search)) {
    $filter_url .= "&src=" . urlencode($search);
}


// --- STAT COUNTS & DASHBOARD (USING UDF) ---
$q_stats = safe_sqlsrv_query($conn, "SELECT Total, Aktif, Nonaktif FROM dbo.fn_GetCustomerStats()", [], false);
$stats = safe_sqlsrv_fetch_array($q_stats, SQLSRV_FETCH_ASSOC);

$total_cust = $stats['Total'] ?? 0;
$total_aktif = $stats['Aktif'] ?? 0;
$total_nonaktif = $stats['Nonaktif'] ?? 0;

$limit = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

$query_sql = "EXEC dbo.sp_ReadCustomerListWithCount @FilterStatus = ?, @FilterJK = ?, @SortBy = ?, @SortOrder = ?, @Offset = ?, @Limit = ?, @Search = ?";
$params_sp = array($filter_status, $filter_jk, $sort_by, $sort_order, intval($offset), intval($limit), $search);

$query = safe_sqlsrv_query($conn, $query_sql, $params_sp, false);

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
} else {
    // Ambil hasil pertama dari SP (Total Count)
    $row_count = safe_sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC);
    $total_cust = intval($row_count['TotalCount'] ?? 0);

    // Geser ke hasil kedua dari SP (List Data Customer)
    sqlsrv_next_result($query);
    
    // Hitung ulang halaman berdasarkan total data terfilter
    $total_pages = max(1, ceil($total_cust / $limit));
    $page = min($page, $total_pages);
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Kelola Customer | HoopBall</title>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800;900&family=Barlow:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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


</style>
</head>
<body>
<?php include '../includes/sidebar.php'; ?>

<main class="main">
    <?php include '../includes/topbar.php'; ?>
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
                <input type="text" id="src" placeholder="Cari customer... (Tekan Enter)"
                    onkeypress="handleSearch(event)" value="<?= htmlspecialchars($_GET['src'] ?? '') ?>">

                <?php if (!empty($search)): ?>
                    <button type="button" onclick="clearSearch()" class="btn-clear-search">
                        <i class="fa-solid fa-circle-xmark"></i>
                    </button>
                <?php endif; ?>
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

        <!-- PAGINATION -->
        <div class="pagination-wrap">
            <div class="pagination-info">
                <?php if ($total_cust > 0): ?>
                    Menampilkan <strong><?= (($page - 1) * $limit) + 1 ?></strong> -
                    <strong><?= min($page * $limit, $total_cust) ?></strong> dari <strong><?= $total_cust ?></strong> data
                <?php else: ?>
                    Menampilkan <strong>0</strong> data
                <?php endif; ?>
            </div>

            <div class="pagination-nav">
                <!-- Tombol First -->
                <a href="?page=1<?= $filter_url ?>" class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>" title="Halaman Pertama">
                    <i class="fa-solid fa-angles-left"></i>
                </a>
                <!-- Tombol Prev -->
                <a href="?page=<?= max(1, $page - 1) ?><?= $filter_url ?>" class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>" title="Halaman Sebelumnya">
                    <i class="fa-solid fa-angle-left"></i>
                </a>
                <!-- Nomor Halaman -->
                <?php 
                $start_page = max(1, $page - 2); 
                $end_page = min($total_pages, $page + 2); 
                if ($end_page - $start_page < 4 && $total_pages >= 5) { 
                    if ($start_page == 1) { $end_page = min(5, $total_pages); } else { $start_page = max(1, $total_pages - 4); } 
                } 
                if ($start_page > 1): ?>
                    <a href="?page=1<?= $filter_url ?>" class="page-btn">1</a>
                    <?php if ($start_page > 2): ?><span class="page-ellipsis">...</span><?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <a href="?page=<?= $i ?><?= $filter_url ?>" class="page-btn <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>

                <?php if ($end_page < $total_pages): ?>
                    <?php if ($end_page < $total_pages - 1): ?><span class="page-ellipsis">...</span><?php endif; ?>
                    <a href="?page=<?= $total_pages ?><?= $filter_url ?>" class="page-btn"><?= $total_pages ?></a>
                <?php endif; ?>

                <!-- Tombol Next -->
                <a href="?page=<?= min($total_pages, $page + 1) ?><?= $filter_url ?>" class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>" title="Halaman Selanjutnya">
                    <i class="fa-solid fa-angle-right"></i>
                </a>
                <!-- Tombol Last -->
                <a href="?page=<?= $total_pages ?><?= $filter_url ?>" class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>" title="Halaman Terakhir">
                    <i class="fa-solid fa-angles-right"></i>
                </a>
            </div>
        </div>
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
                $jk_icon = $detail_data['Jenis_Kelamin'] == 1 ? 'fa-mars' : 'fa-venus';
                $jk_color = $detail_data['Jenis_Kelamin'] == 1 ? 'var(--blue)' : 'var(--pink)';
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
<script src="../asset/js/global.js"></script>
<script>
// MODAL FUNCTIONS
function closeDetail() { 
    window.location.href = 'customer.php'; 
}

// SEARCH FUNCTIONS
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
        window.location.href = 'customer.php?' + urlParams.toString();
    }
}

function clearSearch() {
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.delete('src'); // Hapus kata kunci pencarian
    urlParams.set('page', 1); // Reset kembali ke halaman 1
    window.location.href = 'customer.php?' + urlParams.toString();
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
</script>
</body>
</html>