<?php
ob_start();
require_once '../login/auth_check.php';
$path_prefix = "../";

include_once '../includes/config.php';
include_once '../includes/helpers.php'; // Menggunakan include_once agar aman dari crash pemuatan ganda

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'karyawan') {
    echo "<script>alert('Akses Ditolak!'); window.location='../dashboard/dashboard.php';</script>";
    exit();
}

// ========================================================
// ⚠️ PANGGIL SENSOR AUTO LOGOUT IDLE (DENGAN PENGAMAN AJAX) ⚠️
// ========================================================
$action_value = $_GET['action'] ?? $_POST['action'] ?? '';
$is_real_ajax = ($action_value !== '' && $action_value !== 'auto_logout') || isset($_GET['ajax_detail_id']);

if (!$is_real_ajax) {
    require_once '../login/auto_logout.php';
}
// ========================================================

include '../includes/auth_profile.php';

// Pastikan variabel $nama tersedia dari auth_profile.php
if (!isset($nama) || empty($nama)) {
    $nama = $_SESSION['nama'] ?? $_SESSION['username'] ?? 'SYSTEM';
}

$current_page = 'lapangan';
$topbar_title = 'Kelola Lapangan';
$topbar_breadcrumb = 'Operasional / Lapangan';

// Helper Lokal dibungkus kondisi aman agar bebas dari redeclaration error
if (!function_exists('getPhotoUrl')) {
    function getPhotoUrl($photo_path)
    {
        if (empty($photo_path))
            return '';
        return '../' . ltrim(str_replace('../', '', $photo_path), '/');
    }
}

if (!function_exists('getUploadDirectory')) {
    function getUploadDirectory()
    {
        $upload_dir = '../asset/image/';
        if (!is_dir($upload_dir)) {
            @mkdir($upload_dir, 0755, true);
        }
        return $upload_dir;
    }
}

if (!function_exists('processPhotoUpload')) {
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
}

// Fungsi pengiriman respon AJAX aman dari pencemaran karakter siluman/whitespace
function sendAjaxResponse($data)
{
    if (ob_get_length()) {
        ob_clean(); // Bersihkan buffer keluaran secara total
    }
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

// Fungsi penanganan kueri mandiri khusus AJAX agar aman dari crash
function executeAjaxQuery($conn, $sql, $params = [])
{
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        $errors = sqlsrv_errors(SQLSRV_ERR_ALL);
        $msg = 'Database Error: ';
        if (!empty($errors)) {
            foreach ($errors as $err) {
                $msg .= $err['message'] . ' ';
            }
        } else {
            $msg .= 'Terjadi kesalahan kueri SQL Server.';
        }
        sendAjaxResponse(['success' => false, 'msg' => $msg]);
    }
    return $stmt;
}

// ── PROSES AJAX REQUESTS ──
$is_ajax = $is_real_ajax;
if ($is_ajax) {
    // Membungkam warning PHP selama AJAX agar tidak merusak struktur JSON
    error_reporting(0);
    ini_set('display_errors', 0);

    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    // Backwards compatibility untuk modal detail & edit bawaan lama
    if (isset($_GET['ajax_detail_id'])) {
        $action = 'get_detail';
        $_GET['id'] = $_GET['ajax_detail_id'];
    }

    // Action: Ambil Detail / Edit data (AJAX)
    if ($action === 'get_detail') {
        $id = intval($_GET['id'] ?? 0);
        $r = executeAjaxQuery($conn, "EXEC dbo.sp_GetLapanganDetail ?", [$id]);
        if ($r) {
            $detail_data = sqlsrv_fetch_array($r, SQLSRV_FETCH_ASSOC);
            if ($detail_data) {
                $detail_data['Harga_Sewa_Rupiah'] = rupiah($detail_data['Harga_Sewa']);
                $detail_data['Photo_Lapangan_Url'] = getPhotoUrl($detail_data['Photo_Lapangan']);

                // AMBIL DAFTAR FASILITAS YANG TERPASANG (Result Set Ke-2 dari SP secara aman)
                $assigned_facilities = [];
                if (@sqlsrv_next_result($r)) {
                    while ($fac_row = @sqlsrv_fetch_array($r, SQLSRV_FETCH_ASSOC)) {
                        $assigned_facilities[] = [
                            'ID_Fasilitas' => intval($fac_row['ID_Fasilitas'] ?? 0),
                            'Nama_Fasilitas' => $fac_row['Nama_Fasilitas'] ?? '',
                            'Jumlah_Digunakan' => intval($fac_row['Jumlah_Digunakan'] ?? 0)
                        ];
                    }
                }
                $detail_data['Fasilitas'] = $assigned_facilities;

                sendAjaxResponse(['status' => 'success', 'success' => true, 'data' => $detail_data]);
            } else {
                sendAjaxResponse(['success' => false, 'msg' => 'Data lapangan tidak ditemukan.']);
            }
        } else {
            sendAjaxResponse(['success' => false, 'msg' => 'Gagal mengambil data lapangan dari database.']);
        }
        exit();
    }

    // Action: Simpan Data Tambah / Edit Lapangan beserta Upload File (AJAX Form Submit)
    if ($action === 'save') {
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
            $q_check = executeAjaxQuery($conn, $sql_check, [$nama_lapangan, $id]);
            if ($q_check && sqlsrv_fetch_array($q_check, SQLSRV_FETCH_ASSOC)) {
                $errors[] = 'Nama lapangan sudah terdaftar.';
            }
        }

        if (!empty($errors)) {
            sendAjaxResponse(['success' => false, 'msg' => implode(' | ', $errors)]);
            exit();
        }

        $harga_sewa = number_format(floatval($harga_raw), 2, '.', '');
        $photo_lapangan = processPhotoUpload($_FILES['photo_lapangan'] ?? null, $edit_photo_path);

        // Mengumpulkan pilihan fasilitas dari dropdown multi-select
        $selected_facilities = [];
        if (isset($_POST['use_facility']) && is_array($_POST['use_facility'])) {
            foreach ($_POST['use_facility'] as $id_fac) {
                $id_fac = intval($id_fac);
                if ($id_fac > 0) {
                    $selected_facilities[] = [
                        'id' => $id_fac,
                        'qty' => 1
                    ];
                }
            }
        }
        $facilities_json = !empty($selected_facilities) ? json_encode($selected_facilities) : null;

        if ($edit_mode && $id > 0) {
            $sql = "EXEC dbo.sp_UpdateLapangan ?, ?, ?, ?, ?, ?";
            $params = [$id, $nama_lapangan, $harga_sewa, $photo_lapangan, $nama, $facilities_json];
        } else {
            $sql = "EXEC dbo.sp_CreateLapangan ?, ?, ?, ?, ?";
            $params = [$nama_lapangan, $harga_sewa, $photo_lapangan, $nama, $facilities_json];
        }

        $result = executeAjaxQuery($conn, $sql, $params);
        if ($result !== false) {
            $msg = $edit_mode ? 'Data lapangan berhasil diperbarui!' : 'Lapangan baru berhasil ditambahkan!';
            sendAjaxResponse(['success' => true, 'msg' => $msg]);
        } else {
            sendAjaxResponse(['success' => false, 'msg' => 'Gagal menyimpan data lapangan.']);
        }
        exit();
    }

    // Action: Toggle Status Aktif / Maintenance (AJAX)
    if ($action === 'toggle') {
        $id = intval($_GET['id'] ?? 0);
        $current_status = intval($_GET['status'] ?? 0);
        $s_baru = ($current_status == 1) ? 0 : 1;

        // ============================================================================
        // TAMBAHAN: VALIDASI BOOKING AKTIF SEBELUM DINONAKTIFKAN
        // ============================================================================
        if ($s_baru == 0) { // Jika mencoba menonaktifkan lapangan (Maintenance)
            $sql_check_booking = "
                SELECT COUNT(*) as BookingCount 
                FROM Booking B
                INNER JOIN Jadwal J ON B.ID_Jadwal = J.ID_Jadwal
                WHERE J.ID_Lapangan = ? 
                  AND B.Status IN (0, 1) -- 0: Menunggu, 1: Berhasil
                  AND J.Tanggal >= CAST(GETDATE() AS DATE)
            ";
            
            $stmt_check = executeAjaxQuery($conn, $sql_check_booking, [$id]);
            $row_check = sqlsrv_fetch_array($stmt_check, SQLSRV_FETCH_ASSOC);
            
            if ($row_check && $row_check['BookingCount'] > 0) {
                sendAjaxResponse([
                    'success' => false, 
                    'msg' => 'Lapangan tidak dapat dinonaktifkan karena memiliki booking aktif yang belum selesai.'
                ]);
            }
        }
        // ============================================================================

        $result = executeAjaxQuery($conn, "EXEC dbo.sp_UpdateStatusLapangan ?, ?, ?", [$id, $s_baru, $nama]);
        if ($result !== false) {
            $msg = ($s_baru == 1) ? 'Lapangan berhasil diaktifkan!' : 'Lapangan berhasil dinonaktifkan!';
            sendAjaxResponse(['success' => true, 'msg' => $msg]);
        } else {
            sendAjaxResponse(['success' => false, 'msg' => 'Gagal mengubah status lapangan.']);
        }
        exit();
    }

    // Action: Hapus Lapangan (AJAX)
    if ($action === 'delete') {
        $id = intval($_GET['id'] ?? 0);
        $result = executeAjaxQuery($conn, "EXEC dbo.sp_DeleteLapangan ?, ?", [$id, $nama]);
        if ($result !== false) {
            sendAjaxResponse(['success' => true, 'msg' => 'Lapangan berhasil dihapus!']);
        } else {
            sendAjaxResponse(['success' => false, 'msg' => 'Gagal menghapus lapangan.']);
        }
        exit();
    }

    // Action: Ambil Data Grid, Pagination & Statistik (Dynamic AJAX Refresh)
    if ($action === 'get_table_data') {
        $f_status = $_GET['f_status'] ?? 'all';
        $f_sort = $_GET['f_sort'] ?? 'ID_Lapangan';
        $search = isset($_GET['src']) ? trim($_GET['src']) : '';
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

        $limit = 8;
        $offset = ($page - 1) * $limit;

        // Ambil Statistik Terkini via UDF
        $q_stats = executeAjaxQuery($conn, "SELECT Total, Aktif, Maintenance FROM dbo.fn_GetLapanganStats()");
        $stats = sqlsrv_fetch_array($q_stats, SQLSRV_FETCH_ASSOC);
        $cnt_ready = $stats['Aktif'] ?? 0;
        $cnt_maint = $stats['Maintenance'] ?? 0;
        $total_semua_lapangan = $stats['Total'] ?? 0;

        // Ambil Daftar Utama (SP sp_ReadLapanganListWithCount)
        $query_sql = "EXEC dbo.sp_ReadLapanganListWithCount ?, ?, ?, ?, ?";
        $params_sp = array($f_status, $f_sort, intval($offset), intval($limit), $search);
        $query = executeAjaxQuery($conn, $query_sql, $params_sp);

        $total_lapangan = 0;
        if ($query) {
            $row_count = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC);
            $total_lapangan = intval($row_count['TotalCount'] ?? 0);
            sqlsrv_next_result($query);
        }

        $total_pages = max(1, ceil($total_lapangan / $limit));
        $page = min($page, $total_pages);

        // Render HTML Grid Cards
        ob_start();
        $has_data = false;
        if ($query):
            while ($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)):
                $has_data = true;
                $photo_url = getPhotoUrl($row['Photo_Lapangan'] ?? '');
                $is_aktif = intval($row['Status']) === 1;
                ?>
                <div class="lapangan-card" data-name="<?= strtolower(htmlspecialchars($row['Nama_Lapangan'])) ?>">
                    <div class="lapangan-card-photo-wrap" onclick="showDetail('<?= intval($row['ID_Lapangan']) ?>')">
                        <div class="lapangan-card-photo-placeholder"><i class="fa-solid fa-layer-group"></i></div>
                        <?php if (!empty($photo_url)): ?>
                            <img src="<?= htmlspecialchars($photo_url) ?>" alt="<?= htmlspecialchars($row['Nama_Lapangan']) ?>"
                                loading="lazy" onerror="this.style.display='none';">
                        <?php endif; ?>
                        <span class="lapangan-card-badge <?= $is_aktif ? 'badge-aktif' : 'badge-nonaktif' ?>"><span
                                class="badge-dot"></span> <?= $is_aktif ? 'AKTIF' : 'MAINTENANCE' ?></span>
                        <div class="lapangan-card-actions">
                            <button type="button" onclick="event.stopPropagation(); showDetail('<?= intval($row['ID_Lapangan']) ?>')"
                                class="lapangan-card-action-btn ac-btn-view" title="Lihat Detail"><i
                                    class="fa-solid fa-eye"></i></button>
                            <button type="button" onclick="event.stopPropagation(); showEditForm('<?= intval($row['ID_Lapangan']) ?>')"
                                class="lapangan-card-action-btn ac-btn-edit" title="Edit Lapangan"><i
                                    class="fa-solid fa-pen-to-square"></i></button>
                            <button type="button"
                                onclick="event.stopPropagation(); doDelete(<?= intval($row['ID_Lapangan']) ?>, '<?= htmlspecialchars($row['Nama_Lapangan'], ENT_QUOTES) ?>')"
                                class="lapangan-card-action-btn ac-btn-delete" title="Hapus Lapangan"><i
                                    class="fa-solid fa-trash-can"></i></button>
                        </div>
                    </div>
                    <div class="lapangan-card-info">
                        <div class="lapangan-card-name"><?= htmlspecialchars($row['Nama_Lapangan']) ?></div>
                        <div class="lapangan-card-price"><?= rupiah($row['Harga_Sewa']) ?> <span
                                style="font-size:12px;color:var(--muted);font-weight:600;">/ jam</span></div>
                        <div class="lapangan-card-meta">
                            <span class="lapangan-card-harga"><i
                                    class="fa-solid fa-money-bill-wave"></i><?= rupiah($row['Harga_Sewa']) ?></span>
                            <div class="lapangan-card-toggle">
                                <span class="lapangan-card-toggle-label"><?= $is_aktif ? 'ON' : 'OFF' ?></span>
                                <label class="toggle-switch" title="<?= $is_aktif ? 'Nonaktifkan' : 'Aktifkan' ?>">
                                    <input type="checkbox" <?= $is_aktif ? 'checked' : '' ?>
                                        onchange="confirmToggle('<?= intval($row['ID_Lapangan']) ?>', '<?= htmlspecialchars($row['Nama_Lapangan'], ENT_QUOTES) ?>', <?= intval($row['Status']) ?>, event)">
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
                <i class="fa-solid fa-layer-group"></i>
                <div>Belum ada data lapangan</div>
                <p>Klik "+ Tambah Lapangan" untuk menambahkan lapangan baru</p>
            </div>
        <?php endif;
        $grid_html = ob_get_clean();

        // Render HTML Pagination
        ob_start();
        ?>
        <div class="pagination-info">
            <?php if ($total_lapangan > 0): ?>
                Menampilkan <strong><?= (($page - 1) * $limit) + 1 ?></strong> -
                <strong><?= min($page * $limit, $total_lapangan) ?></strong> dari <strong><?= $total_lapangan ?></strong> data
            <?php else: ?>
                Menampilkan <strong>0</strong> data
            <?php endif; ?>
        </div>
        <div class="pagination-nav">
            <button onclick="changePage(1)" class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>"><i
                    class="fa-solid fa-angles-left"></i></button>
            <button onclick="changePage(<?= max(1, $page - 1) ?>)" class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>"><i
                    class="fa-solid fa-angle-left"></i></button>
            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                <button onclick="changePage(<?= $i ?>)" class="page-btn <?= $i == $page ? 'active' : '' ?>"><?= $i ?></button>
            <?php endfor; ?>
            <button onclick="changePage(<?= min($total_pages, $page + 1) ?>)"
                class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>"><i class="fa-solid fa-angle-right"></i></button>
            <button onclick="changePage(<?= $total_pages ?>)" class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>"><i
                    class="fa-solid fa-angles-right"></i></button>
        </div>
        <?php
        $pagination_html = ob_get_clean();

        sendAjaxResponse([
            'success' => true,
            'grid' => $grid_html,
            'pagination' => $pagination_html,
            'stats' => [
                'ready' => $cnt_ready,
                'maint' => $cnt_maint,
                'total' => $total_semua_lapangan,
                'total_filtered' => $total_lapangan
            ]
        ]);
        exit();
    }
}

// ── GET STATISTICS UNTUK AWAL PAGE LOAD ──
$q_stats = sqlsrv_query($conn, "SELECT Total, Aktif, Maintenance FROM dbo.fn_GetLapanganStats()");
$stats = $q_stats ? sqlsrv_fetch_array($q_stats, SQLSRV_FETCH_ASSOC) : [];

$cnt_ready = $stats['Aktif'] ?? 0;
$cnt_maint = $stats['Maintenance'] ?? 0;
$total_semua_lapangan = $stats['Total'] ?? 0;

// Ambil daftar fasilitas aktif untuk ditampilkan sebagai pilihan dropdown form
$master_facilities = [];
$q_fac = sqlsrv_query($conn, "SELECT ID_Fasilitas, Nama_Fasilitas FROM dbo.Fasilitas_Lapangan WHERE Is_Deleted = 0 AND Status = 1");
if ($q_fac) {
    while ($f_row = sqlsrv_fetch_array($q_fac, SQLSRV_FETCH_ASSOC)) {
        $master_facilities[] = $f_row;
    }
}

$sidebar_folder = 'master';
$sidebar_photo = $profile_photo;
$topbar_title = 'Kelola Lapangan';
$topbar_breadcrumb = 'Operasional / Lapangan';
?>
<!DOCTYPE html>
<html lang="id">

<head>
   <?php include '../includes/favicon.php'; ?>
    <title>Kelola Lapangan | HoopBall</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800;900&family=Barlow:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link class="swal2-container" rel="stylesheet" href="../asset/css/global.css">
    <link rel="stylesheet" href="../asset/css/responsive_tipe_member.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* CSS Tambahan khusus memaksa SweetAlert2 berada di atas modal bootstrap */
        .swal2-container {
            z-index: 3000 !important;
        }

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

        .lapangan-card-actions {
            position: absolute;
            top: 8px;
            right: 8px;
            display: flex;
            gap: 6px;
            opacity: 1;
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
            margin-bottom: 18px;
            display: none;
            min-height: 16px;
        }

        .val-msg.show {
            display: block;
        }

        .val-msg i {
            margin-right: 4px;
        }

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

        .multiselect-dropdown {
            position: relative;
            width: 100%;
            margin-bottom: 18px;
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

        .detail-facilities-card {
            background: #FAFBFD;
            border: 1px solid var(--border-lt);
            border-radius: 14px;
            padding: 14px;
            margin-bottom: 20px;
            transition: all .2s ease;
            text-align: left;
        }

        .detail-facilities-card:hover {
            background: #ffffff;
            border-color: var(--orange);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, .02);
        }

        .detail-facilities-list {
            display: grid;
            grid-template-columns: 1fr 1fr;
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
    <div class="modal-overlay" id="modalLapangan">
        <div class="modal-box">
            <button type="button" class="modal-close" onclick="closeModalDirect('modalLapangan')" title="Tutup"><i
                    class="fa-solid fa-xmark"></i></button>
            <div class="modal-header">
                <div class="modal-subtitle">Kelola Lapangan</div>
                <div class="modal-title" id="formModalTitle">Tambah Lapangan Baru</div>
            </div>
            <div class="modal-body">
                <!-- ID Kontainer hiddenInputsArea diperbaiki di sini -->
                <form id="formLapangan" enctype="multipart/form-data" onsubmit="handleFormSubmit(event)" novalidate>
                    <div id="hiddenInputsArea"></div>
                    <label class="modal-label">Foto Lapangan <span style="color:var(--muted);font-size:10px;">(Opsional,
                            max 5MB)</span></label>
                    <div class="photo-upload-area" id="uploadArea">
                        <input type="file" name="photo_lapangan" id="photo_lapangan"
                            accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
                            onchange="handlePhotoUpload(this)">
                        <img class="photo-upload-preview" id="previewImg" style="display:none;" alt="Preview">
                        <button type="button" class="photo-upload-remove" id="removeBtn"
                            onclick="event.stopPropagation(); removePhoto();" style="display:none;"
                            title="Hapus Foto"><i class="fa-solid fa-xmark"></i></button>
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
                    <button type="submit" class="btn-submit" id="btnSubmitForm"><i class="fa-solid fa-plus"></i> Tambah
                        Lapangan</button>
                    <button type="button" class="btn-cancel" onclick="closeModalDirect('modalLapangan')">Batal</button>
                </form>
            </div>
        </div>
    </div>

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
                    <div class="detail-status-badge" id="det_status_badge"><i class="fa-solid fa-circle"></i><span
                            id="det_status_text">Lapangan Aktif</span></div>
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
                <div class="detail-facilities-card">
                    <div class="detail-info-label"><i class="fa-solid fa-couch"></i> Fasilitas Terpasang</div>
                    <div id="det_facilities_list">-</div>
                </div>
                <button type="button" onclick="closeModalDirect('modalDetail')" class="btn-submit"
                    style="background:#0D1117; margin-top: 10px;"><i class="fa-solid fa-arrow-left"></i>
                    Kembali</button>
            </div>
        </div>
    </div>

    <?php include '../includes/sidebar.php'; ?>
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
                            class="chip-val" id="stat-aktif"><?= $cnt_ready ?></span></div>
                    <div class="stat-chip chip-red"><i class="fa-solid fa-circle-xmark"></i> MAINTENANCE <span
                            class="chip-val" id="stat-nonaktif"><?= $cnt_maint ?></span></div>
                    <div class="stat-chip chip-blue"><i class="fa-solid fa-layer-group"></i> TOTAL <span
                            class="chip-val" id="stat-total"><?= $total_semua_lapangan ?></span></div>
                </div>
            </div>
            <div class="action-bar">
                <div class="search-box">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="src" placeholder="Cari lapangan... (Tekan Enter)"
                        onkeypress="handleSearch(event)" value="">
                    <button type="button" onclick="clearSearch()" class="btn-clear-search" id="btnClearSearch"
                        style="display:none;"><i class="fa-solid fa-circle-xmark"></i></button>
                </div>
                <div style="display:flex;gap:12px;align-items:center;">
                    <div class="filter-dropdown-wrap">
                        <!-- Perbaikan ID custom dan onclick khusus untuk bypass tabrakan event global.js -->
                        <button type="button" class="btn-filter" id="btnFilterToggleCustom"
                            onclick="toggleCustomFilterCard(event)"><i class="fa-solid fa-filter"></i> Filter <i
                                class="fa-solid fa-chevron-down arrow-icon"></i></button>
                        <div class="filter-card" id="filterCardCustom" onclick="event.stopPropagation()">
                            <h4><i class="fa-solid fa-sliders" style="margin-right:8px;color:var(--orange);"></i>Filter
                                Data</h4>
                            <form id="formFilter" onsubmit="handleFilterSubmit(event)">
                                <div class="filter-group">
                                    <label>Status</label>
                                    <select name="f_status" class="filter-input">
                                        <option value="all">Semua Status</option>
                                        <option value="aktif">AKTIF</option>
                                        <option value="nonaktif">MAINTENANCE</option>
                                    </select>
                                </div>
                                <div class="filter-group">
                                    <label>Urut Berdasarkan</label>
                                    <select name="f_sort" class="filter-input">
                                        <option value="nama_asc">Nama A-Z</option>
                                        <option value="harga_desc">Harga Termahal</option>
                                        <option value="harga_asc">Harga Termurah</option>
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

            <div class="lapangan-grid" id="lapanganGrid">
                <!-- Dinamis diisi lewat AJAX Javascript -->
            </div>

            <div class="pagination-wrap">
                <!-- Dinamis diisi lewat AJAX Javascript -->
            </div>
        </div>
    </main>
    <script src="../asset/js/global.js"></script>
    <script>
        // State management untuk Filter & Pagination
        let currentPage = 1;
        let currentSort = 'ID_Lapangan';
        let currentStatus = 'all';
        let currentSearch = '';

        // ============================================
        // GET DATA GRID (AJAX REFRESH)
        // ============================================
        async function loadTableData() {
            const url = `lapangan.php?action=get_table_data&page=${currentPage}&f_sort=${currentSort}&f_status=${currentStatus}&src=${encodeURIComponent(currentSearch)}`;
            try {
                const response = await fetch(url);
                const text = await response.text(); // Ambil mentahan respon teks terlebih dahulu untuk debug
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        // Update stats
                        document.getElementById('stat-aktif').textContent = data.stats.ready;
                        document.getElementById('stat-nonaktif').textContent = data.stats.maint;
                        document.getElementById('stat-total').textContent = data.stats.total;

                        // Update Grid & Pagination
                        document.getElementById('lapanganGrid').innerHTML = data.grid;
                        document.querySelector('.pagination-wrap').innerHTML = data.pagination;

                        // Update Clear Search Button visibility
                        const btnClear = document.getElementById('btnClearSearch');
                        if (currentSearch !== '') {
                            btnClear.style.display = 'block';
                        } else {
                            btnClear.style.display = 'none';
                        }
                    } else {
                        showError('Gagal!', data.msg);
                    }
                } catch (jsonErr) {
                    // Cetak mentahan respon ke konsol browser jika format JSON tidak valid
                    console.error("Keluaran Mentah Server (Raw Response):", text);
                    showError('Format Respon Salah!', 'Respon Server: ' + text.substring(0, 300));
                }
            } catch (error) {
                console.error("Gagal memuat data grid lapangan:", error);
                showError('Koneksi Gagal!', 'Gagal menghubungkan ke server untuk memuat data.');
            }
        }

        function changePage(page) {
            currentPage = page;
            loadTableData();
        }

        // ============================================
        // PHOTO UPLOAD PREVIEW & REMOVE
        // ============================================
        function handlePhotoUpload(input) {
            if (!input.files || !input.files[0]) return;
            var file = input.files[0];
            if (file.size > 5 * 1024 * 1024) {
                Swal.fire({ icon: 'error', title: 'File Terlalu Besar', text: 'Ukuran file maksimal 5MB!', confirmButtonColor: '#FF4500' });
                input.value = '';
                return;
            }
            var reader = new FileReader();
            reader.onload = function (e) {
                var previewImg = document.getElementById('previewImg');
                var uploadPlaceholder = document.getElementById('uploadPlaceholder');
                var uploadArea = document.getElementById('uploadArea');
                var removeBtn = document.getElementById('removeBtn');
                if (previewImg) { previewImg.src = e.target.result; previewImg.style.display = 'block'; }
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
            if (previewImg) { previewImg.src = ''; previewImg.style.display = 'none'; }
            if (uploadArea) uploadArea.classList.remove('has-image');
            if (uploadPlaceholder) uploadPlaceholder.style.display = 'flex';
            if (removeBtn) removeBtn.style.display = 'none';
        }

        // ============================================
        // VALIDASI FORM
        // ============================================
        function validateField(fieldId, valId, rules) {
            const field = document.getElementById(fieldId);
            const valMsg = document.getElementById(valId);
            const value = field.value.trim();

            field.classList.remove('error');
            valMsg.classList.remove('show');

            if (rules.required && value === '') {
                field.classList.add('error');
                valMsg.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> ' + rules.label + ' wajib diisi.';
                valMsg.classList.add('show');
                return false;
            }

            if (rules.minLength && value.length < rules.minLength) {
                field.classList.add('error');
                valMsg.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> ' + rules.label + ' minimal ' + rules.minLength + ' karakter.';
                valMsg.classList.add('show');
                return false;
            }

            if (rules.maxLength && value.length > rules.maxLength) {
                field.classList.add('error');
                valMsg.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> ' + rules.label + ' maksimal ' + rules.maxLength + ' karakter.';
                valMsg.classList.add('show');
                return false;
            }

            if (rules.pattern && value !== '') {
                const regex = /^[a-zA-Z\s]+$/;
                if (!regex.test(value)) {
                    field.classList.add('error');
                    valMsg.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> ' + rules.label + ' hanya boleh berisi huruf dan spasi.';
                    valMsg.classList.add('show');
                    return false;
                }
            }

            return true;
        }

        function validateForm() {
            var valid = true;

            var checkboxes = document.querySelectorAll('.facility-checkbox');
            var valFacilities = document.getElementById('val-facilities');
            var dropdownHeader = document.querySelector('.multiselect-header');
            var anyChecked = Array.from(checkboxes).some(function (chk) { return chk.checked; });

            if (!validateField('nama_lapangan', 'val-nama_lapangan', {
                required: true,
                minLength: 3,
                maxLength: 50,
                pattern: true,
                label: 'Nama lapangan'
            })) valid = false;

            if (!validateField('harga_sewa', 'val-harga_sewa', {
                required: true,
                label: 'Harga sewa'
            })) valid = false;

            // Validasi manual khusus untuk range angka
            const harga = document.getElementById('harga_sewa');
            const valHarga = document.getElementById('val-harga_sewa');
            if (harga && valHarga && harga.value.trim() !== '') {
                const v = parseFloat(harga.value.trim());
                if (v < 10000) {
                    harga.classList.add('error');
                    valHarga.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Harga sewa minimal Rp 10.000.';
                    valHarga.classList.add('show');
                    valid = false;
                } else if (v > 10000000) {
                    harga.classList.add('error');
                    valHarga.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Harga sewa maksimal Rp 10.000.000.';
                    valHarga.classList.add('show');
                    valid = false;
                }
            }

            if (!anyChecked) {
                if (dropdownHeader && valFacilities) {
                    dropdownHeader.classList.add('error');
                    valFacilities.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Fasilitas lapangan wajib dipilih minimal satu.';
                    valFacilities.classList.add('show');
                }
                valid = false;
            }

            return valid;
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
        // AJAX SUBMIT FORM (TAMBAH / EDIT DENGAN UPLOAD FOTO)
        // ============================================
        async function handleFormSubmit(event) {
            event.preventDefault();
            if (!validateForm()) return;

            const form = document.getElementById('formLapangan');
            const formData = new FormData(form);
            formData.append('action', 'save');

            Swal.fire({
                title: 'Memproses...',
                text: 'Menyimpan data lapangan',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });

            try {
                const response = await fetch('lapangan.php', {
                    method: 'POST',
                    body: formData
                });
                const text = await response.text();
                try {
                    const res = JSON.parse(text);
                    if (res.success) {
                        closeModalDirect('modalLapangan');
                        showSuccess('Berhasil!', res.msg);
                        loadTableData();
                    } else {
                        showError('Gagal!', res.msg);
                    }
                } catch (jsonErr) {
                    console.error("Keluaran Mentah Server (Raw Response):", text);
                    showError('Gagal Simpan!', 'Respon dari server tidak valid. Buka F12 untuk melihat pesan eror aslinya.');
                }
            } catch (error) {
                showError('Gagal!', 'Gagal memproses data.');
            }
        }

        // ============================================
        // TOGGLE STATUS
        // ============================================
        async function confirmToggle(id, name, currentStatus, event) {
            const checkbox = event.target;
            const action = currentStatus === 1 ? 'nonaktifkan' : 'aktifkan';
            const iconType = currentStatus === 1 ? 'warning' : 'question';

            const result = await Swal.fire({
                title: 'Ubah Status?',
                html: 'Ubah status <strong style="color:var(--orange);">' + name + '</strong>?',
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
                    text: 'Mengubah status lapangan',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
                });

                try {
                    const response = await fetch(`lapangan.php?action=toggle&id=${id}&status=${currentStatus}`);
                    const text = await response.text();
                    try {
                        const res = JSON.parse(text);
                        if (res.success) {
                            showSuccess('Berhasil!', res.msg);
                            loadTableData();
                        } else {
                            checkbox.checked = !checkbox.checked;
                            showError('Gagal!', res.msg);
                        }
                    } catch (jsonErr) {
                        console.error("Keluaran Mentah Server (Raw Response):", text);
                        checkbox.checked = !checkbox.checked;
                        showError('Gagal Toggle!', 'Format respon server salah.');
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
        // DELETE CONFIRMATION
        // ============================================
        async function doDelete(id, name) {
            const result = await Swal.fire({
                title: 'Hapus Lapangan?',
                html: 'Anda akan menghapus lapangan <strong style="color:var(--orange);">' + name + '</strong><br><span style="font-size:12px;color:var(--muted);">Data akan dihapus secara permanen.</span>',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#EF4444',
                cancelButtonColor: '#6B7280',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal',
                reverseButtons: true,
                allowOutsideClick: false
            });

            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Memproses...',
                    text: 'Menghapus lapangan',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
                });

                try {
                    const response = await fetch(`lapangan.php?action=delete&id=${id}`);
                    const text = await response.text();
                    try {
                        const res = JSON.parse(text);
                        if (res.success) {
                            showSuccess('Terhapus!', res.msg);
                            loadTableData();
                        } else {
                            showError('Gagal!', res.msg);
                        }
                    } catch (jsonErr) {
                        console.error("Keluaran Mentah Server (Raw Response):", text);
                        showError('Gagal Hapus!', 'Format respon server salah.');
                    }
                } catch (error) {
                    showError('Gagal!', 'Terjadi kesalahan saat menghapus data.');
                }
            }
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

        // Alias fungsi agar tombol Tambah bawaan lama di HTML tetap dapat dibuka
        function openAddModal() {
            showAddForm();
        }

        // ============================================
        // KONTROL MODAL (BUKA / TUTUP)
        // ============================================
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('open');
            document.body.style.overflow = 'hidden'; // Mencegah background di-scroll
        }

        function closeModalDirect(modalId) {
            document.getElementById(modalId).classList.remove('open');
            document.body.style.overflow = '';
        }

        // ============================================
        // MODAL TAMBAH DATA
        // ============================================
        function showAddForm() {
            const form = document.getElementById('formLapangan');
            form.reset(); // Kosongkan inputan
            document.getElementById('hiddenInputsArea').innerHTML = ''; // Hapus inputan edit
            removePhoto(); // Kembalikan ke UI upload kosong

            // Ubah teks judul dan tombol
            document.getElementById('formModalTitle').innerText = 'Tambah Lapangan Baru';
            document.getElementById('btnSubmitForm').innerHTML = '<i class="fa-solid fa-plus"></i> Tambah Lapangan';

            // Hapus pesan error validasi sebelumnya
            document.querySelectorAll('.modal-input').forEach(el => el.classList.remove('error'));
            document.querySelectorAll('.val-msg').forEach(el => el.classList.remove('show'));
            document.querySelector('.multiselect-header').classList.remove('error');

            // Reset dropdown fasilitas
            document.querySelectorAll('.facility-checkbox').forEach(chk => chk.checked = false);
            updateMultiselectHeader();

            openModal('modalLapangan');
        }

        // ============================================
        // MODAL EDIT DATA (AMBIL VIA AJAX)
        // ============================================
        async function showEditForm(id) {
            try {
                const response = await fetch(`lapangan.php?action=get_detail&id=${id}`);
                const res = await response.json();
                
                if (res.success) {
                    const d = res.data;
                    
                    showAddForm(); // Reset UI form

                    // Ubah Judul & Tombol
                    document.getElementById('formModalTitle').innerText = 'Edit Data Lapangan';
                    document.getElementById('btnSubmitForm').innerHTML = '<i class="fa-solid fa-save"></i> Simpan Perubahan';

                    // Isi value input
                    document.getElementById('nama_lapangan').value = d.Nama_Lapangan;
                    document.getElementById('harga_sewa').value = parseInt(d.Harga_Sewa);

                    // Buat input tersembunyi
                    document.getElementById('hiddenInputsArea').innerHTML = `
                        <input type="hidden" name="id_lap" value="${d.ID_Lapangan}">
                        <input type="hidden" name="edit_mode" value="1">
                        <input type="hidden" name="edit_photo_path" value="${d.Photo_Lapangan}">
                    `;

                    // Tampilkan foto jika ada
                    if (d.Photo_Lapangan_Url) {
                        document.getElementById('previewImg').src = d.Photo_Lapangan_Url;
                        document.getElementById('previewImg').style.display = 'block';
                        document.getElementById('uploadArea').classList.add('has-image');
                        document.getElementById('uploadPlaceholder').style.display = 'none';
                        document.getElementById('removeBtn').style.display = 'flex';
                    }

                    // Centang fasilitas
                    if (d.Fasilitas && d.Fasilitas.length > 0) {
                        d.Fasilitas.forEach(fac => {
                            const chk = document.getElementById('chk_fac_' + fac.ID_Fasilitas);
                            if (chk) chk.checked = true;
                        });
                        updateMultiselectHeader();
                    }

                    openModal('modalLapangan');
                } else {
                    showError('Gagal!', res.msg);
                }
            } catch (error) {
                showError('Error!', 'Gagal menghubungkan ke server.');
            }
        }

        // ============================================
        // MODAL DETAIL DATA (AMBIL VIA AJAX)
        // ============================================
        async function showDetail(id) {
            try {
                const response = await fetch(`lapangan.php?action=get_detail&id=${id}`);
                const res = await response.json();
                
                if (res.success) {
                    const d = res.data;
                    
                    document.getElementById('det_nama_title').innerText = d.Nama_Lapangan;
                    document.getElementById('det_harga').innerHTML = d.Harga_Sewa_Rupiah + ' <span style="font-size:14px;color:var(--muted);font-family:\'Barlow\';font-weight:600;">/ jam</span>';
                    document.getElementById('det_harga_val').innerText = d.Harga_Sewa_Rupiah;
                    document.getElementById('det_tarif_secondary').innerHTML = d.Harga_Sewa_Rupiah + ' <span style="font-size:11px; font-weight:500; color:var(--muted);">/jam</span>';

                    // Foto
                    if (d.Photo_Lapangan_Url) {
                        document.getElementById('det_photo_img').src = d.Photo_Lapangan_Url;
                        document.getElementById('det_photo_img').style.display = 'block';
                        document.getElementById('det_photo_placeholder').style.display = 'none';
                    } else {
                        document.getElementById('det_photo_img').style.display = 'none';
                        document.getElementById('det_photo_placeholder').style.display = 'flex';
                    }

                    // Badge Status
                    const badge = document.getElementById('det_status_badge');
                    const badgeText = document.getElementById('det_status_text');
                    if (parseInt(d.Status) === 1) {
                        badge.className = 'detail-status-badge badge-status-aktif';
                        badgeText.innerText = 'Lapangan Aktif';
                    } else {
                        badge.className = 'detail-status-badge badge-status-nonaktif';
                        badgeText.innerText = 'Maintenance';
                    }

                    // Render Fasilitas
                    let facHtml = '<div class="detail-facilities-list">';
                    if (d.Fasilitas && d.Fasilitas.length > 0) {
                        d.Fasilitas.forEach(f => {
                            facHtml += `<div class="facility-item"><i class="fa-solid fa-check-circle"></i> ${f.Nama_Fasilitas}</div>`;
                        });
                    } else {
                        facHtml += '<div class="empty-note-facilities">Tidak ada fasilitas terdaftar pada lapangan ini.</div>';
                    }
                    facHtml += '</div>';
                    document.getElementById('det_facilities_list').innerHTML = facHtml;

                    openModal('modalDetail');
                } else {
                    showError('Gagal!', res.msg);
                }
            } catch (error) {
                showError('Error!', 'Gagal menghubungkan ke server.');
            }
        }

        // ============================================
        // LOGIKA DROPDOWN FASILITAS (MULTISELECT)
        // ============================================
        function toggleMultiselect(event) {
            event.stopPropagation();
            document.getElementById('multiselectContent').classList.toggle('open');
        }

        function updateMultiselectHeader() {
            const checkboxes = document.querySelectorAll('.facility-checkbox');
            const header = document.getElementById('multiselectLabel');
            let count = 0;

            checkboxes.forEach(chk => { if (chk.checked) count++; });

            if (count > 0) {
                header.innerHTML = `<span style="font-weight:700; color:var(--orange);">${count} Fasilitas Dipilih</span>`;
            } else {
                header.innerHTML = 'Pilih Fasilitas Lapangan';
            }
        }

        // Tutup dropdown fasilitas saat klik di luar
        window.addEventListener('click', function (e) {
            const content = document.getElementById('multiselectContent');
            const dropdown = document.getElementById('facilityDropdown');
            if (dropdown && content && !dropdown.contains(e.target)) {
                content.classList.remove('open');
            }
        });

        // ============================================
        // FILTER DATA GRID
        // ============================================
        function handleFilterSubmit(event) {
            event.preventDefault();
            const form = event.target;
            currentStatus = form.f_status.value;
            currentSort = form.f_sort.value;
            currentPage = 1;
            loadTableData();
            document.getElementById('filterCardCustom').classList.remove('open');
            document.getElementById('btnFilterToggleCustom').classList.remove('active');
        }

        function resetFilter() {
            document.getElementById('formFilter').reset();
            currentStatus = 'all';
            currentSort = 'ID_Lapangan';
            currentPage = 1;
            loadTableData();
            document.getElementById('filterCardCustom').classList.remove('open');
            document.getElementById('btnFilterToggleCustom').classList.remove('active');
        }

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