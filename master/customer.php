<?php
require_once '../login/auth_check.php';
$path_prefix = "../";

include '../includes/config.php';
include '../includes/helpers.php';

// ============================================================
// HELPER: Deklarasi Aman dengan Pengecekan Duplikasi (Bebas Eror)
// ============================================================
if (!function_exists('getInitials')) {
    function getInitials($name) {
        $clean_name = trim($name);
        $name_parts = explode(' ', $clean_name);
        if (count($name_parts) >= 2) {
            return strtoupper(substr($name_parts[0], 0, 1) . substr(end($name_parts), 0, 1));
        }
        return strtoupper(substr($clean_name, 0, 2));
    }
}

if (!function_exists('parseUmurRange')) {
    function parseUmurRange($range) {
        if ($range === 'all' || empty($range)) return null;
        $parts = explode('-', $range);
        if (count($parts) == 2) {
            return ['min' => intval($parts[0]), 'max' => intval($parts[1])];
        }
        return null;
    }
}

if (!function_exists('filterByUmur')) {
    function filterByUmur($rows, $umur_range) {
        $range = parseUmurRange($umur_range);
        if (!$range) return $rows;
        return array_filter($rows, function($row) use ($range) {
            $umur = isset($row['Umur']) ? intval($row['Umur']) : 0;
            return $umur >= $range['min'] && $umur <= $range['max'];
        });
    }
}

if (!function_exists('jk_label')) {
    function jk_label($jk) {
        return $jk == 1 ? 'Laki-Laki' : 'Perempuan';
    }
}

if (!function_exists('format_tgl_display')) {
    function format_tgl_display($date) {
        if ($date instanceof DateTime) {
            return $date->format('d-m-Y');
        }
        return date('d-m-Y', strtotime($date));
    }
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'karyawan') {
    echo "<script>alert('Akses Ditolak!'); window.location='../dashboard/dashboard.php';</script>";
    exit();
}

// ========================================================
// ⚠️ PANGGIL SENSOR AUTO LOGOUT IDLE (DENGAN PENGAMAN AJAX) ⚠️
// ========================================================
$action_value = $_GET['action'] ?? $_POST['action'] ?? '';
$is_real_ajax = ($action_value !== '' && $action_value !== 'auto_logout');

if (!$is_real_ajax) {
    require_once '../login/auto_logout.php';
}
// ========================================================

include '../includes/auth_profile.php';

$current_page = 'customer';
$topbar_title = 'Kelola Customer';
$topbar_breadcrumb = 'Operasional / Customer';

// ── PROSES AJAX REQUESTS ──
$is_ajax = $is_real_ajax;
if ($is_ajax) {
    header('Content-Type: application/json');
    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    // Action: Ambil Detail / Edit data (AJAX)
    if ($action === 'get_detail') {
        $id = intval($_GET['id'] ?? 0);
        $r_detail = safe_sqlsrv_query($conn, "EXEC dbo.sp_GetCustomerDetail ?", array($id), false);
        if ($r_detail && $row = safe_sqlsrv_fetch_array($r_detail, SQLSRV_FETCH_ASSOC)) {
            $tgl_lahir_formatted = format_tgl_display($row['Tanggal_Lahir']);
            echo json_encode([
                'success' => true,
                'data' => [
                    'ID_Customer' => $row['ID_Customer'],
                    'Nama_Customer' => $row['Nama_Customer'],
                    'Email' => $row['Email'] ?? '-',
                    'Jenis_Kelamin' => (int)$row['Jenis_Kelamin'],
                    'JenisKelaminText' => jk_label($row['Jenis_Kelamin']),
                    'Tanggal_Lahir' => $tgl_lahir_formatted,
                    'Tempat_Lahir' => $row['Tempat_Lahir'] ?? '-',
                    'Alamat' => $row['Alamat'] ?? '-',
                    'No_Telepon' => $row['No_Telepon'] ?? '-',
                    'Status' => (int)($row['Status'] ?? 1)
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'msg' => 'Data customer tidak ditemukan atau telah dihapus.']);
        }
        exit();
    }

    // Action: Toggle Status Aktif / Nonaktif (AJAX)
    if ($action === 'toggle') {
        $id = intval($_GET['id'] ?? 0);
        $current_status = intval($_GET['status'] ?? 1);
        $new_status = $current_status === 1 ? 0 : 1;
        $modified_by = $_SESSION['nama'] ?? 'SYSTEM';

        $stmt_toggle = safe_sqlsrv_query(
            $conn,
            "EXEC dbo.sp_UpdateStatusCustomer ?, ?, ?",
            array($id, $new_status, $modified_by),
            false
        );

        if ($stmt_toggle) {
            echo json_encode(['success' => true, 'msg' => 'Status customer berhasil diperbarui!']);
        } else {
            echo json_encode(['success' => false, 'msg' => 'Gagal mengubah status customer!']);
        }
        exit();
    }

    // Action: Ambil Data Tabel, Pagination & Statistik (Dynamic AJAX Refresh)
    if ($action === 'get_table_data') {
        $filter_status = isset($_GET['status_filter']) ? $_GET['status_filter'] : 'all';
        $filter_jk = isset($_GET['jk']) ? intval($_GET['jk']) : -1;
        $sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'ID_Customer';
        $sort_order = isset($_GET['order']) && strtoupper($_GET['order']) == 'DESC' ? 'DESC' : 'ASC';
        $search = isset($_GET['src']) ? trim($_GET['src']) : '';
        $umur_range = isset($_GET['umur_range']) ? $_GET['umur_range'] : 'all';
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $limit = 10;

        // Ambil Statistik Terkini via SP
        $q_stats = safe_sqlsrv_query($conn, "EXEC dbo.sp_GetCustomerStats", [], false);
        $stats = safe_sqlsrv_fetch_array($q_stats, SQLSRV_FETCH_ASSOC);
        $total_cust = $stats['Total'] ?? 0;
        $total_aktif = $stats['Aktif'] ?? 0;
        $total_nonaktif = $stats['Nonaktif'] ?? 0;

        // Tarik seluruh data (limit tinggi) untuk memisahkan pengurutan filter umur di PHP secara dinamis
        $query_sql = "EXEC dbo.sp_ReadCustomerListWithCount @FilterStatus = ?, @FilterJK = ?, @SortBy = ?, @SortOrder = ?, @Offset = 0, @Limit = 100000, @Search = ?";
        $params_sp = array($filter_status, $filter_jk, $sort_by, $sort_order, $search);
        $query = safe_sqlsrv_query($conn, $query_sql, $params_sp, false);

        $all_rows = [];
        if ($query) {
            // Abaikan hasil pertama SP (karena kita hitung totalnya secara dinamis setelah umur disaring)
            safe_sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC);
            sqlsrv_next_result($query);
            while ($row = safe_sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)) {
                $all_rows[] = $row;
            }
        }

        // Jalankan Filter Umur (Server-Side PHP)
        if ($umur_range !== 'all' && !empty($umur_range)) {
            $all_rows = filterByUmur($all_rows, $umur_range);
            $all_rows = array_values($all_rows); // tata kembali index-nya
        }

        $total_cust_filtered = count($all_rows);
        $total_pages = max(1, ceil($total_cust_filtered / $limit));
        $page = min($page, $total_pages);
        $offset = ($page - 1) * $limit;

        // Potong array sesuai offset halaman saat ini
        $page_rows = array_slice($all_rows, $offset, $limit);

        // Render HTML Tabel Body
        ob_start();
        $has_data = false;
        $no = $offset + 1;
        foreach ($page_rows as $row):
            $has_data = true;
            $status_int = isset($row['Status']) ? intval($row['Status']) : 1;
            $jk_icon = $row['Jenis_Kelamin'] == 1 ? 'fa-mars' : 'fa-venus';
            $jk_color = $row['Jenis_Kelamin'] == 1 ? 'var(--blue)' : 'var(--pink)';
            $jk_class = $row['Jenis_Kelamin'] == 1 ? 'jk-laki' : 'jk-perempuan';
            ?>
            <tr>
                <td class="col-center row-num"><?= $no++ ?></td>
                <td class="col-left">
                    <div class="cust-name-cell">
                        <div class="cust-avatar"><?= getInitials($row['Nama_Customer']) ?></div>
                        <div class="cust-name"><?= htmlspecialchars($row['Nama_Customer']) ?></div>
                    </div>
                </td>
                <td class="col-center cust-email"><?= htmlspecialchars($row['Email'] ?? '-') ?></td>
                <td class="col-center">
                    <span class="jk-badge <?= $jk_class ?>">
                        <i class="fa-solid <?= $jk_icon ?>"></i> <?= jk_label($row['Jenis_Kelamin']) ?>
                    </span>
                </td>
                <td class="col-center"><?= htmlspecialchars($row['Umur'] ?? '-') ?> Tahun</td>
                <td class="col-center">
                    <span class="status-pill <?= $status_int === 1 ? 'sp-active' : 'sp-inactive' ?>">
                        <span class="sp-dot"></span>
                        <?= $status_int === 1 ? 'AKTIF' : 'NONAKTIF' ?>
                    </span>
                </td>
                <td class="col-center">
                    <div class="actions">
                        <button type="button" onclick="viewDetail(<?= $row['ID_Customer'] ?>)" class="btn-action btn-view" title="Lihat Detail">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                        <label class="toggle-switch" title="<?= $status_int === 1 ? 'Nonaktifkan' : 'Aktifkan' ?>">
                            <input type="checkbox" <?= $status_int === 1 ? 'checked' : '' ?>
                                onchange="confirmToggle('<?= $row['ID_Customer'] ?>', <?= $status_int ?>, event)">
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </td>
            </tr>
        <?php endforeach;

        if (!$has_data): ?>
            <tr>
                <td colspan="7">
                    <div class="empty-state"><i class="fa-solid fa-users"></i>
                        <div>Belum ada data customer</div>
                        <div style="font-size: 12px; font-weight: 500; margin-top: 8px; opacity: .7;">
                            Data customer akan muncul di sini setelah registrasi</div>
                    </div>
                </td>
            </tr>
        <?php endif;
        $table_html = ob_get_clean();

        // Render HTML Pagination
        ob_start();
        ?>
        <div class="pagination-info">
            <?php if ($total_cust_filtered > 0): ?>
                Menampilkan <strong><?= (($page - 1) * $limit) + 1 ?></strong> -
                <strong><?= min($page * $limit, $total_cust_filtered) ?></strong> dari <strong><?= $total_cust_filtered ?></strong> data
            <?php else: ?>
                Menampilkan <strong>0</strong> data
            <?php endif; ?>
        </div>
        <div class="pagination-nav">
            <button onclick="changePage(1)" class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>">
                <i class="fa-solid fa-angles-left"></i>
            </button>
            <button onclick="changePage(<?= max(1, $page - 1) ?>)" class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>">
                <i class="fa-solid fa-angle-left"></i>
            </button>
            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                <button onclick="changePage(<?= $i ?>)" class="page-btn <?= $i == $page ? 'active' : '' ?>"><?= $i ?></button>
            <?php endfor; ?>
            <button onclick="changePage(<?= min($total_pages, $page + 1) ?>)" class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>">
                <i class="fa-solid fa-angle-right"></i>
            </button>
            <button onclick="changePage(<?= $total_pages ?>)" class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>">
                <i class="fa-solid fa-angles-right"></i>
            </button>
        </div>
        <?php
        $pagination_html = ob_get_clean();

        echo json_encode([
            'success' => true,
            'table' => $table_html,
            'pagination' => $pagination_html,
            'stats' => [
                'total' => $total_cust,
                'aktif' => $total_aktif,
                'nonaktif' => $total_nonaktif,
                'total_filtered' => $total_cust_filtered
            ]
        ]);
        exit();
    }
}

// ── GET STATISTICS UNTUK AWAL PAGE LOAD ──
$q_stats = safe_sqlsrv_query($conn, "EXEC dbo.sp_GetCustomerStats", [], false);
$stats = safe_sqlsrv_fetch_array($q_stats, SQLSRV_FETCH_ASSOC);
$total_cust = $stats['Total'] ?? 0;
$total_aktif = $stats['Aktif'] ?? 0;
$total_nonaktif = $stats['Nonaktif'] ?? 0;

$query_error = false;
$query_error_msg = '';

$sidebar_folder = 'master';
$sidebar_photo = $profile_photo;
?>
<!DOCTYPE html>
<html lang="id">

<head>
   <?php include '../includes/favicon.php'; ?>
    <title>Kelola Customer | HoopBall</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800;900&family=Barlow:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../asset/css/global.css">
    <link rel="stylesheet" href="../asset/css/responsive_tipe_member.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* CSS Tambahan khusus memaksa SweetAlert2 berada di atas modal bootstrap */
        .swal2-container {
            z-index: 3000 !important;
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

        .card:hover {
            box-shadow: 0 8px 24px rgba(0, 0, 0, .06);
        }

        .table-wrap {
            overflow-x: auto;
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
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        .table-wrap::-webkit-scrollbar {
            display: none;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th {
            font-family: 'Barlow Condensed', sans-serif !important;
            font-size: 13px !important;
            font-weight: 900 !important;
            color: #FFFFFF !important;
            text-transform: uppercase !important;
            letter-spacing: 0.8px !important;
            padding: 14px 20px;
            border-bottom: 2px solid var(--border-lt);
            background: #ff6f00 !important;
        }

        .data-table td {
            padding: 14px 16px;
            vertical-align: middle;
            font-size: 13px;
        }

        .data-table tbody tr:nth-child(odd) {
            background-color: #FFF7ED;
        }

        .data-table tbody tr:nth-child(even) {
            background-color: #FFFFFF;
        }

        .data-table tbody tr:hover td {
            background-color: #FFEDD5 !important;
        }

        .data-table tbody tr {
            height: 68px;
        }

        /* ============================================
           TABLE COLUMN WIDTHS - FIXED PIXEL BASED
           ============================================ */
        .data-table th:nth-child(1),
        .data-table td:nth-child(1) {
            width: 50px;
            text-align: center;
        }

        /* Nama Customer - Rata Kiri */
        .data-table th:nth-child(2) {
            width: 280px;
            text-align: center;
        }
        .data-table td:nth-child(2) {
            width: 280px;
            text-align: center;
        }

        /* Email - Rata Kiri */
        .data-table th:nth-child(3),
        .data-table td:nth-child(3) {
            width: 120px;
            text-align: center;
        }

        .data-table th:nth-child(4),
        .data-table td:nth-child(4) {
            width: 120px;
            text-align: center;
        }

        .data-table th:nth-child(5),
        .data-table td:nth-child(5) {
            width: 100px;
            text-align: center;
        }

        .data-table th:nth-child(6),
        .data-table td:nth-child(6) {
            width: 100px;
            text-align: center;
        }

        .data-table th:nth-child(7),
        .data-table td:nth-child(7) {
            width: 160px;
            text-align: center;
        }

        /* ============================================
           CUSTOMER AVATAR & NAME - MATCH KARYAWAN STYLE
           ============================================ */
        .cust-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--orange), #ff6b35);
            color: #fff;
            font-weight: 800;
            font-size: 14px;
            flex-shrink: 0;
            box-shadow: 0 2px 8px rgba(255, 69, 0, 0.2);
            border: 2px solid #fff;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .cust-avatar:hover {
            transform: scale(1.08);
            box-shadow: 0 4px 16px rgba(255, 69, 0, 0.35);
        }

        .data-table tbody tr:hover .cust-avatar {
            transform: scale(1.08);
            box-shadow: 0 4px 16px rgba(255, 69, 0, 0.35);
        }

        .cust-name-cell {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            height: 100%;
            text-align: left;
            justify-content: flex-start;
        }

        .cust-name {
            font-weight: 700;
            color: var(--text);
            font-size: 14px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 200px;
            text-align: left;
            margin: 0;
        }

        .cust-email {
            font-family: 'Barlow', sans-serif;
            font-weight: 700;
            font-size: 13px;
            color: var(--text);
            text-align: left !important;
        }

        .row-num {
            font-family: 'Barlow', sans-serif;
            font-weight: 800;
            color: var(--text);
            font-size: 14px;
            text-align: center;
        }

        /* ============================================
           BADGES & STATUS
           ============================================ */
        .status-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 11px;
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

        /* JK BADGE */
        .jk-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            padding: 5px 10px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .jk-laki {
            background: #EFF6FF;
            color: #3B82F6;
        }

        .jk-perempuan {
            background: #FDF2F8;
            color: #EC4899;
        }

        /* ============================================
           TOGGLE SWITCH
           ============================================ */
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

        .toggle-switch input:checked+.toggle-slider {
            background-color: var(--green);
        }

        .toggle-switch input:checked+.toggle-slider::before {
            transform: translateX(20px);
        }

        .toggle-switch:hover .toggle-slider {
            opacity: .9;
        }

        /* ACTIONS */
        .actions {
            display: flex;
            gap: 8px;
            justify-content: center;
            align-items: center;
        }

        .btn-action {
            width: 32px;
            height: 32px;
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
            width: 420px;
            max-height: 95vh;
            overflow-y: auto;
            overflow-x: hidden;
            box-shadow: 0 25px 60px rgba(0, 0, 0, .2);
            position: relative;
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
            box-shadow: 0 6px 16px rgba(255, 69, 0, 0.15);
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
            box-shadow: 0 8px 20px rgba(255, 69, 0, .3);
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
            box-shadow: 0 6px 16px rgba(255, 69, 0, .25);
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
            box-shadow: 0 20px 60px rgba(0, 0, 0, .15);
            z-index: 3000;
            padding: 24px;
            overflow-y: auto;
            scrollbar-width: none;
            -ms-overflow-style: none;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px) scale(0.98);
            transform-origin: top right;
            transition: all .2s cubic-bezier(.4, 0, .2, 1);
        }

        .filter-card::-webkit-scrollbar {
            display: none;
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
            box-shadow: 0 6px 16px rgba(255, 69, 0, .25);
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
            right: 8px; /* Menggunakan setelan 8px agar tidak bertabrakan dengan border kanan */
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

        /* Kelas Penjajaran Khusus */
        .col-left {
            text-align: left !important;
            padding-left: 24px !important;
        }

        .col-center {
            text-align: center !important;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .status-active {
            background: var(--green-lt);
            color: var(--green);
        }

        .status-inactive {
            background: var(--red-lt);
            color: var(--red);
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
                    <div class="stat-chip chip-green"><i class="fa-solid fa-circle-check"></i> AKTIF <span
                            class="chip-val" id="stat-aktif">0</span></div>
                    <div class="stat-chip chip-red"><i class="fa-solid fa-circle-xmark"></i> NONAKTIF <span
                            class="chip-val" id="stat-nonaktif">0</span></div>
                    <div class="stat-chip chip-blue"><i class="fa-solid fa-users"></i> TOTAL <span
                            class="chip-val" id="stat-total">0</span></div>
                </div>
            </div>

            <div class="action-bar">
                <div class="search-box">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="src" placeholder="Cari customer... (Tekan Enter)"
                        onkeypress="handleSearch(event)" value="">
                    <button type="button" onclick="clearSearch()" class="btn-clear-search" id="btnClearSearch" style="display: none;">
                        <i class="fa-solid fa-circle-xmark"></i>
                    </button>
                </div>
                <div class="action-right">
                    <div class="filter-wrap">
                        <button type="button" class="btn-filter" id="btnFilterToggleCustom" onclick="toggleCustomFilterCard(event)">
                            <i class="fa-solid fa-filter"></i> Filter <i
                                class="fa-solid fa-chevron-down arrow-icon"></i>
                        </button>

                        <div class="filter-card" id="filterCardCustom" onclick="event.stopPropagation()">
                            <div class="filter-head">
                                <div class="filter-title">Filter Data</div>
                            </div>
                            <form id="formFilter" onsubmit="handleFilterSubmit(event)">
                                <div class="filter-group">
                                    <label class="filter-label">Urut Berdasarkan</label>
                                    <select name="sort" class="filter-select">
                                        <option value="Nama_Customer">Nama Lengkap</option>
                                        <option value="Umur">Umur</option>
                                    </select>
                                </div>

                                <div class="filter-group">
                                    <label class="filter-label">Urutan</label>
                                    <select name="order" class="filter-select">
                                        <option value="ASC">Naik (A-Z)</option>
                                        <option value="DESC">Turun (Z-A)</option>
                                    </select>
                                </div>

                                <div class="filter-group">
                                    <label class="filter-label">Jenis Kelamin</label>
                                    <select name="jk" class="filter-select">
                                        <option value="-1">Semua Jenis Kelamin</option>
                                        <option value="1">Laki-laki</option>
                                        <option value="0">Perempuan</option>
                                    </select>
                                </div>

                                <div class="filter-group">
                                    <label class="filter-label">Rentang Umur</label>
                                    <select name="umur_range" class="filter-select">
                                        <option value="all">Semua Umur</option>
                                        <option value="18-25">18 - 25 Tahun (Muda)</option>
                                        <option value="26-35">26 - 35 Tahun (Dewasa)</option>
                                        <option value="36-50">36 - 50 Tahun (Paruh Baya)</option>
                                        <option value="51-100">51+ Tahun (Lansia)</option>
                                    </select>
                                </div>

                                <div class="filter-group">
                                    <label class="filter-label">Status</label>
                                    <select name="status_filter" class="filter-select">
                                        <option value="all">Semua Status</option>
                                        <option value="aktif">Aktif</option>
                                        <option value="nonaktif">Non Aktif</option>
                                    </select>
                                </div>

                                <div class="filter-actions">
                                    <button type="submit" class="btn-filter-apply">
                                        <i class="fa-solid fa-check"></i> Terapkan
                                    </button>
                                    <button type="button" class="btn-filter-reset" onclick="resetFilter()">
                                        <i class="fa-solid fa-rotate-left"></i> Reset
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fa-solid fa-users"></i> Data Customer</div>
                    <span class="card-badge" id="header-badge">0 total</span>
                </div>
                <div class="table-wrap">
                    <table class="data-table" id="tbl">
                        <thead>
                            <tr>
                                <th style="width: 80px;" class="col-center">No</th>
                                <th class="col-center">Nama</th>
                                <th class="col-center">Email</th>
                                <th style="width: 150px;" class="col-center">Jenis Kelamin</th>
                                <th style="width: 120px;" class="col-center">Umur</th>
                                <th style="width: 120px;" class="col-center">Status</th>
                                <th style="width: 150px;" class="col-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Dinamis diisi lewat AJAX Javascript -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- PAGINATION -->
            <div class="pagination-wrap">
                <!-- Dinamis diisi lewat AJAX Javascript -->
            </div>
        </div>
    </main>

    <!-- MODAL DETAIL CUSTOMER -->
    <div class="modal-overlay" id="modalDetail">
        <div class="modal-box" style="width: 420px; max-height: 95vh; overflow-y: auto;">
            <button class="modal-close" onclick="closeDetail()"><i class="fa-solid fa-xmark"></i></button>
            <div class="modal-header" style="padding: 20px 24px 10px; border-bottom: 1px solid var(--border);">
                <div class="modal-subtitle">Informasi Pelanggan</div>
                <div class="modal-title">Profil Customer</div>
            </div>
            <div class="modal-body" id="detail-modal-body" style="padding: 12px 24px 20px;">
                <!-- Dinamis diisi lewat AJAX Javascript -->
            </div>
        </div>
    </div>
    <script src="../asset/js/global.js"></script>
    <script>
        // State management untuk Filter & Pagination
        let currentPage = 1;
        let currentSort = 'ID_Customer';
        let currentOrder = 'ASC';
        let currentJk = -1;
        let currentStatusFilter = 'all';
        let currentUmurRange = 'all';
        let currentSearch = '';

        // ============================================
        // GET DATA TABEL (AJAX REFRESH)
        // ============================================
        async function loadTableData() {
            const url = `customer.php?action=get_table_data&page=${currentPage}&sort=${currentSort}&order=${currentOrder}&jk=${currentJk}&status_filter=${currentStatusFilter}&umur_range=${currentUmurRange}&src=${encodeURIComponent(currentSearch)}`;
            try {
                const response = await fetch(url);
                const data = await response.json();
                if (data.success) {
                    // Update stats
                    document.getElementById('stat-aktif').textContent = data.stats.aktif;
                    document.getElementById('stat-nonaktif').textContent = data.stats.nonaktif;
                    document.getElementById('stat-total').textContent = data.stats.total;
                    document.getElementById('header-badge').textContent = `${data.stats.total_filtered} total`;

                    // Update Table & Pagination
                    document.querySelector('#tbl tbody').innerHTML = data.table;
                    document.querySelector('.pagination-wrap').innerHTML = data.pagination;

                    // Update Clear Search Button visibility
                    const btnClear = document.getElementById('btnClearSearch');
                    if (currentSearch !== '') {
                        btnClear.style.display = 'block';
                    } else {
                        btnClear.style.display = 'none';
                    }
                }
            } catch (error) {
                console.error("Gagal memuat data tabel:", error);
            }
        }

        function changePage(page) {
            currentPage = page;
            loadTableData();
        }

        // ============================================
        // MODAL ADD / EDIT / DETAIL CONTROL
        // ============================================
        function closeDetail() {
            document.getElementById('modalDetail').classList.remove('open');
        }

        async function viewDetail(id) {
            try {
                const response = await fetch(`customer.php?action=get_detail&id=${id}`);
                const res = await response.json();
                if (res.success) {
                    const data = res.data;
                    const is_active_detail = (data.Status === 1);
                    const jk_icon = data.Jenis_Kelamin === 1 ? 'fa-mars' : 'fa-venus';
                    const jk_color = data.Jenis_Kelamin === 1 ? 'var(--blue)' : 'var(--pink)';

                    const initials = data.Nama_Customer.trim().split(' ').slice(0, 2).map(word => word[0]).join('').toUpperCase();

                    const statusPill = is_active_detail 
                        ? `<span class="status-badge status-active">AKTIF</span>`
                        : `<span class="status-badge status-inactive">NONAKTIF</span>`;

                    document.getElementById('detail-modal-body').innerHTML = `
                        <div class="detail-photo-card">
                            <div class="detail-icon-wrap" style="background: linear-gradient(135deg, var(--orange), #ff6b35); color: #fff; font-family: 'Barlow', sans-serif; font-weight: 900; font-size: 24px; text-transform: uppercase;">
                                ${initials}
                            </div>
                            <div class="detail-main-name">${data.Nama_Customer}</div>
                        </div>
                        <div class="info-row">
                            <span class="info-key"><i class="fa-solid fa-user"></i> Nama Lengkap</span>
                            <span class="info-val">${data.Nama_Customer}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-key"><i class="fa-solid fa-envelope"></i> Email</span>
                            <span class="info-val">${data.Email}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-key"><i class="fa-solid fa-venus-mars" style="color:${jk_color}"></i> Jenis Kelamin</span>
                            <span class="info-val" style="color:${jk_color};"><i class="fa-solid ${jk_icon}"></i> ${data.JenisKelaminText}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-key"><i class="fa-solid fa-cake-candles"></i> Tanggal Lahir</span>
                            <span class="info-val">${data.Tanggal_Lahir}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-key"><i class="fa-solid fa-location-dot"></i> Tempat Lahir</span>
                            <span class="info-val">${data.Tempat_Lahir}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-key"><i class="fa-solid fa-map-location-dot"></i> Alamat</span>
                            <span class="info-val">${data.Alamat}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-key"><i class="fa-solid fa-phone"></i> No. Telepon</span>
                            <span class="info-val">${data.No_Telepon}</span>
                        </div>
                        <div class="info-row" style="border-bottom:none;">
                            <span class="info-key"><i class="fa-solid fa-shield-halved"></i> Status</span>
                            <span class="info-val">${statusPill}</span>
                        </div>
                        <button onclick="closeDetail()" class="btn-submit" style="margin-top: 12px; background: #0D1117;">
                            <i class="fa-solid fa-arrow-left"></i> Kembali Ke List
                        </button>
                    `;
                    document.getElementById('modalDetail').classList.add('open');
                } else {
                    showError('Gagal!', res.msg);
                }
            } catch (error) {
                showError('Gagal!', 'Terjadi kesalahan sistem.');
            }
        }

        // ============================================
        // ALERT HELPER FUNCTIONS (PREVENTS STUCK MODAL)
        // ============================================
        function showSuccess(title, message) {
            Swal.close(); // Tutup modal loading terlebih dahulu
            Swal.fire({
                icon: 'success',
                title: title,
                text: message,
                confirmButtonColor: '#10B981'
            });
        }

        function showError(title, message) {
            Swal.close(); // Tutup modal loading terlebih dahulu
            Swal.fire({
                icon: 'error',
                title: title,
                text: message,
                confirmButtonColor: '#EF4444'
            });
        }

        // ============================================
        // SEARCH & FILTER SYSTEM
        // ============================================
        function handleSearch(event) {
            if (event.key === 'Enter') {
                currentSearch = document.getElementById('src').value.trim();
                currentPage = 1;
                loadTableData();
            }
        }

        function clearSearch() {
            document.getElementById('src').value = '';
            currentSearch = '';
            currentPage = 1;
            loadTableData();
        }

        function handleFilterSubmit(event) {
            event.preventDefault();
            const form = event.target;
            currentSort = form.elements['sort'].value;
            currentOrder = form.elements['order'].value;
            currentJk = form.elements['jk'].value;
            currentStatusFilter = form.elements['status_filter'].value;
            currentUmurRange = form.elements['umur_range'].value;
            currentPage = 1;
            loadTableData();
            
            // Tutup filter dropdown
            document.getElementById('btnFilterToggleCustom').classList.remove('active');
            document.getElementById('filterCardCustom').classList.remove('open');
        }

        function resetFilter() {
            document.getElementById('formFilter').reset();
            currentSort = 'ID_Customer';
            currentOrder = 'ASC';
            currentJk = -1;
            currentStatusFilter = 'all';
            currentUmurRange = 'all';
            currentSearch = '';
            document.getElementById('src').value = '';
            currentPage = 1;
            loadTableData();

            document.getElementById('btnFilterToggleCustom').classList.remove('active');
            document.getElementById('filterCardCustom').classList.remove('open');
        }

        // ============================================
        // TOGGLE STATUS
        // ============================================
        async function confirmToggle(id, currentStatus, event) {
            const checkbox = event.target;
            const action = currentStatus === 1 ? 'nonaktifkan' : 'aktifkan';
            const iconType = currentStatus === 1 ? 'warning' : 'question';

            const result = await Swal.fire({
                title: 'Konfirmasi Perubahan Status',
                text: 'Apakah Anda yakin ingin ' + action + ' customer ini?',
                icon: iconType,
                showCancelButton: true,
                confirmButtonColor: '#FF4500',
                cancelButtonColor: '#6B7280',
                confirmButtonText: 'Ya, Ubah!',
                cancelButtonText: 'Batal',
                reverseButtons: true,
                allowOutsideClick: false
            });

            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Memproses...',
                    text: 'Mengubah status customer',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
                });

                try {
                    const response = await fetch(`customer.php?action=toggle&id=${id}&status=${currentStatus}`);
                    const res = await response.json();
                    if (res.success) {
                        showSuccess('Berhasil!', res.msg);
                        loadTableData();
                    } else {
                        checkbox.checked = !checkbox.checked;
                        showError('Gagal!', res.msg);
                    }
                } catch (error) {
                    checkbox.checked = !checkbox.checked;
                    showError('Gagal!', 'Terjadi kesalahan saat mengubah status.');
                }
            } else {
                checkbox.checked = !checkbox.checked;
            }
        }

        // ============================================
        // PENGENDALI EVENT KLIK FILTER (INLINE FALLBACK)
        // ============================================
        function toggleCustomFilterCard(e) {
            e.stopPropagation();
            const btn = document.getElementById('btnFilterToggleCustom');
            const card = document.getElementById('filterCardCustom');
            if (btn && card) {
                btn.classList.toggle('active');
                card.classList.toggle('open');
            }
        }

        // Tutup filter card saat pengguna klik di luar area filter
        window.addEventListener('click', function (e) {
            const btn = document.getElementById('btnFilterToggleCustom');
            const card = document.getElementById('filterCardCustom');
            if (btn && card) {
                if (!btn.contains(e.target) && !card.contains(e.target)) {
                    btn.classList.remove('active');
                    card.classList.remove('open');
                }
            }
        });

        // ============================================
        // INITIAL LOAD
        // ============================================
        document.addEventListener('DOMContentLoaded', function () {
            loadTableData();
        });
    </script>
    <?php if (function_exists('tampilkan_sensor_auto_logout')) tampilkan_sensor_auto_logout(); ?>
</body>

</html>