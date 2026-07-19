<?php
require_once '../login/auth_check.php';
$path_prefix = "../";
include '../includes/auth_helper.php';
include '../includes/config.php';

// ============================================================================
// CEK AKSES - KARYAWAN ONLY
// ============================================================================
cek_akses('karyawan');

// ========================================================
// ⚠️ PANGGIL SENSOR AUTO LOGOUT IDLE (DENGAN PENGAMAN AJAX) ⚠️
// ========================================================
$action_value = $_GET['action'] ?? $_POST['action'] ?? '';
$is_real_ajax = ($action_value !== '' && $action_value !== 'auto_logout');

if (!$is_real_ajax) {
    require_once '../login/auto_logout.php';
}
// ========================================================

$nama = $_SESSION['nama'] ?? 'Karyawan';
$role = $_SESSION['role'] ?? 'karyawan';
$id_karyawan = $_SESSION['id_karyawan'] ?? '';

// ============================================================================
// AMBIL FOTO PROFIL
// ============================================================================
$profile_photo = '';
if (!empty($id_karyawan)) {
    $stmt_photo = sqlsrv_query($conn, "SELECT Photo_Profile FROM Karyawan WHERE ID_Karyawan = ?", array($id_karyawan));
    if ($stmt_photo !== false) {
        $row_photo = sqlsrv_fetch_array($stmt_photo, SQLSRV_FETCH_ASSOC);
        if ($row_photo && !empty($row_photo['Photo_Profile'])) {
            $profile_photo = $row_photo['Photo_Profile'];
        }
    }
}

$sidebar_photo = '';
if (!empty($profile_photo)) {
    if (strpos($profile_photo, '../') === 0) {
        $sidebar_photo = $profile_photo;
    } elseif (strpos($profile_photo, 'uploads/') === 0) {
        $sidebar_photo = '../' . $profile_photo;
    } else {
        $sidebar_photo = '../uploads/profiles/' . $profile_photo;
    }
    if (!file_exists($sidebar_photo)) {
        $sidebar_photo = '';
    }
}

// ============================================================================
// AUTO EXPIRE LANGGANAN
// ============================================================================
sqlsrv_query($conn, "EXEC SP_AutoExpireLangganan @Modified_By = ?", array($nama));

// ============================================================================
// STATUS LANGGANAN
// ============================================================================
$status_labels = [
    0 => ['label' => 'Menunggu Konfirmasi', 'class' => 'sp-pending', 'icon' => 'fa-clock'],
    1 => ['label' => 'Aktif', 'class' => 'sp-active', 'icon' => 'fa-circle-check'],
    2 => ['label' => 'Berakhir', 'class' => 'sp-success', 'icon' => 'fa-circle-xmark'],
    3 => ['label' => 'Ditolak', 'class' => 'sp-inactive', 'icon' => 'fa-ban']
];

// Helper Functions
function safeFetch($stmt, $fetch_type = SQLSRV_FETCH_ASSOC)
{
    if ($stmt === false || $stmt === null)
        return false;
    return sqlsrv_fetch_array($stmt, $fetch_type);
}

function rupiahFormat($n)
{
    return 'Rp ' . number_format($n, 0, ',', '.');
}

function formatTanggal($tanggal)
{
    if (empty($tanggal))
        return '-';
    if (is_object($tanggal) && method_exists($tanggal, 'format')) {
        return $tanggal->format('d M Y');
    }
    return date('d M Y', strtotime($tanggal));
}

function formatJam($jam)
{
    if (empty($jam))
        return '-';
    if (is_object($jam) && method_exists($jam, 'format')) {
        return $jam->format('H:i');
    }
    return substr($jam, 0, 5);
}

// PERBAIKAN: Fungsi resolveBuktiPath versi PHP agar tidak Syntax Error
function resolveBuktiPathPHP($path)
{
    if (empty($path))
        return '';
    $path = str_replace('\\', '/', $path);
    if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0)
        return $path;
    if (strpos($path, '../') === 0)
        return $path;
    if (strpos($path, '/') === 0)
        return '..' . $path;
    return '../' . $path;
}

function checkBuktiExists($path)
{
    $resolved = resolveBuktiPathPHP($path);
    if (empty($resolved))
        return false;
    return file_exists($resolved);
}

// ── PROSES AJAX REQUESTS ──
$is_ajax = $is_real_ajax;
if ($is_ajax) {
    header('Content-Type: application/json');
    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    // Action: Ambil Detail Langganan (AJAX)
    if ($action === 'get_detail') {
        $id = intval($_GET['id'] ?? 0);
        $sql = "
            SELECT L.*, C.Nama_Customer, C.Email, C.No_Telepon, T.Nama_Tipe, T.Harga_Member,
                   K.Nama_Karyawan as Nama_Karyawan_Input
            FROM Langganan L
            INNER JOIN Customer C ON L.ID_Customer = C.ID_Customer
            INNER JOIN Tipe_Member T ON L.ID_Tipe = T.ID_Tipe
            LEFT JOIN Karyawan K ON L.ID_Karyawan = K.ID_Karyawan
            WHERE L.ID_Langganan = ?
        ";
        $q_langganan = sqlsrv_query($conn, $sql, array($id));
        if ($q_langganan && $langganan_data = safeFetch($q_langganan)) {
            $langganan_data['TanggalMulaiFormatted'] = formatTanggal($langganan_data['Tanggal_Mulai']);
            $langganan_data['TanggalSelesaiFormatted'] = formatTanggal($langganan_data['Tanggal_Selesai']);
            echo json_encode(['success' => true, 'data' => $langganan_data]);
        } else {
            echo json_encode(['success' => false, 'msg' => 'Data langganan tidak ditemukan.']);
        }
        exit();
    }

    // Action: Konfirmasi Pembayaran (AJAX)
    if ($action === 'confirm_payment') {
        $id_langganan = intval($_POST['id_langganan'] ?? 0);
        $stmt = sqlsrv_query(
            $conn,
            "EXEC SP_KonfirmasiLangganan @ID_Langganan = ?, @ID_Karyawan = ?, @Modified_By = ?",
            array($id_langganan, $id_karyawan, $nama)
        );

        if ($stmt) {
            $result = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            if ($result && $result['Status'] === 'SUCCESS') {
                echo json_encode(['success' => true, 'msg' => 'Pembayaran langganan berhasil dikonfirmasi!']);
            } else {
                $error_msg = $result['Message'] ?? 'Gagal mengkonfirmasi pembayaran.';
                echo json_encode(['success' => false, 'msg' => $error_msg]);
            }
        } else {
            echo json_encode(['success' => false, 'msg' => 'Gagal memproses konfirmasi ke database.']);
        }
        exit();
    }

    // Action: Tolak Pembayaran (AJAX)
    if ($action === 'tolak_bayar') {
        $id_langganan = intval($_POST['id_langganan'] ?? 0);
        $alasan = trim($_POST['alasan_tolak'] ?? 'Tidak ada alasan');

        $stmt = sqlsrv_query(
            $conn,
            "EXEC SP_TolakLangganan @ID_Langganan = ?, @ID_Karyawan = ?, @Alasan = ?, @Modified_By = ?",
            array($id_langganan, $id_karyawan, $alasan, $nama)
        );

        if ($stmt) {
            $result = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            if ($result && $result['Status'] === 'SUCCESS') {
                echo json_encode(['success' => true, 'msg' => 'Pendaftaran langganan berhasil ditolak.']);
            } else {
                $error_msg = $result['Message'] ?? 'Gagal menolak langganan.';
                echo json_encode(['success' => false, 'msg' => $error_msg]);
            }
        } else {
            echo json_encode(['success' => false, 'msg' => 'Gagal memproses penolakan ke database.']);
        }
        exit();
    }

    // Action: Muat Ulang Tabel & Statistik (Dynamic AJAX Refresh)
    if ($action === 'get_table_data') {
        $filter_status = isset($_GET['filter_status']) ? $_GET['filter_status'] : 'all';
        $filter_customer = isset($_GET['filter_customer']) ? trim($_GET['filter_customer']) : '';
        $filter_tanggal = isset($_GET['filter_tanggal']) ? trim($_GET['filter_tanggal']) : '';
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

        $filter_status_param = null;
        if ($filter_status !== '' && $filter_status !== 'all') {
            $filter_status_param = (int) $filter_status;
        }

        $limit = 10;

        // --- TAMBAHAN: Membangun kueri pencarian SQL terpadu (Bisa cari Customer ATAU Tipe Member) ---
        $sql_count = "
            SELECT COUNT(*) AS Total_Count 
            FROM Langganan L
            INNER JOIN Customer C ON L.ID_Customer = C.ID_Customer
            INNER JOIN Tipe_Member TM ON L.ID_Tipe = TM.ID_Tipe
            WHERE 1=1
        ";
        $params_count = [];
        if ($filter_status_param !== null) {
            $sql_count .= " AND L.Status = ?";
            $params_count[] = $filter_status_param;
        }
        if ($filter_customer !== '') {
            $sql_count .= " AND (C.Nama_Customer LIKE ? OR TM.Nama_Tipe LIKE ?)";
            $params_count[] = '%' . $filter_customer . '%';
            $params_count[] = '%' . $filter_customer . '%';
        }
        if ($filter_tanggal !== '') {
            $sql_count .= " AND L.Tanggal_Mulai >= ?";
            $params_count[] = $filter_tanggal;
        }

        // Ambil total data terfilter
        $count_query = sqlsrv_query($conn, $sql_count, $params_count);
        $total_data = 0;
        if ($count_query) {
            $count_row = sqlsrv_fetch_array($count_query, SQLSRV_FETCH_ASSOC);
            $total_data = $count_row['Total_Count'] ?? 0;
        }

        $total_pages = max(1, ceil($total_data / $limit));
        $page = min($page, $total_pages);
        $offset = ($page - 1) * $limit;

        // --- TAMBAHAN: Kueri detail list dengan pencarian gabungan Nama & Tipe ---
        $sql_data = "
            SELECT 
                L.ID_Langganan, L.ID_Customer, C.Nama_Customer, C.Email, L.ID_Tipe, TM.Nama_Tipe, TM.Harga_Member,
                L.Tanggal_Mulai, L.Tanggal_Selesai, L.Total_Bayar, L.Metode_Pembayaran, L.Status,
                K.Nama_Karyawan AS Nama_Karyawan_Konfirmasi, L.Created_Date, L.Modified_Date
            FROM Langganan L
            INNER JOIN Customer C ON L.ID_Customer = C.ID_Customer
            INNER JOIN Tipe_Member TM ON L.ID_Tipe = TM.ID_Tipe
            LEFT JOIN Karyawan K ON L.ID_Karyawan = K.ID_Karyawan
            WHERE 1=1
        ";
        $params_data = [];
        if ($filter_status_param !== null) {
            $sql_data .= " AND L.Status = ?";
            $params_data[] = $filter_status_param;
        }
        if ($filter_customer !== '') {
            $sql_data .= " AND (C.Nama_Customer LIKE ? OR TM.Nama_Tipe LIKE ?)";
            $params_data[] = '%' . $filter_customer . '%';
            $params_data[] = '%' . $filter_customer . '%';
        }
        if ($filter_tanggal !== '') {
            $sql_data .= " AND L.Tanggal_Mulai >= ?";
            $params_data[] = $filter_tanggal;
        }
        $sql_data .= "
            ORDER BY 
                CASE L.Status
                    WHEN 0 THEN 0
                    WHEN 1 THEN 1
                    WHEN 2 THEN 2
                    WHEN 3 THEN 3
                END ASC,
                L.Created_Date DESC
            OFFSET ? ROWS FETCH NEXT ? ROWS ONLY
        ";
        $params_data[] = $offset;
        $params_data[] = $limit;

        // Ambil data Paged list
        $langganans = [];
        $data_query = sqlsrv_query($conn, $sql_data, $params_data);
        if ($data_query) {
            while ($row = sqlsrv_fetch_array($data_query, SQLSRV_FETCH_ASSOC)) {
                $langganans[] = $row;
            }
        }

        // Fix fallback bukti pembayaran
        $bukti_map = [];
        $bukti_query = sqlsrv_query($conn, "SELECT ID_Langganan, Bukti_Pembayaran FROM Langganan WHERE Bukti_Pembayaran IS NOT NULL");
        if ($bukti_query) {
            while ($brow = sqlsrv_fetch_array($bukti_query, SQLSRV_FETCH_ASSOC)) {
                $bukti_map[$brow['ID_Langganan']] = $brow['Bukti_Pembayaran'];
            }
        }
        foreach ($langganans as &$lrow) {
            $id_l = $lrow['ID_Langganan'];
            if (empty($lrow['Bukti_Pembayaran']) && isset($bukti_map[$id_l])) {
                $lrow['Bukti_Pembayaran'] = $bukti_map[$id_l];
            }
        }
        unset($lrow);

        // Ambil statistik terkini
        $stats = ['total' => 0, 'menunggu' => 0, 'aktif' => 0, 'berakhir' => 0, 'ditolak' => 0, 'total_omzet' => 0];
        $stats_query = sqlsrv_query($conn, "EXEC SP_GetDashboardStats");
        if ($stats_query) {
            $stats_row = sqlsrv_fetch_array($stats_query, SQLSRV_FETCH_ASSOC);
            if ($stats_row) {
                $stats['total'] = $stats_row['Total_Langganan'] ?? 0;
                $stats['menunggu'] = $stats_row['Menunggu_Konfirmasi'] ?? 0;
                $stats['aktif'] = $stats_row['Aktif'] ?? 0;
                $stats['berakhir'] = $stats_row['Berakhir'] ?? 0;
                $stats['ditolak'] = $stats_row['Ditolak'] ?? 0;
                $stats['total_omzet'] = $stats_row['Total_Omzet_Aktif'] ?? 0;
            }
        }

        // Render HTML Tabel Body
        ob_start();
        if (count($langganans) > 0):
            $no = $offset + 1;
            foreach ($langganans as $l):
                $status = $status_labels[$l['Status']] ?? $status_labels[0];
                $tgl_mulai = formatTanggal($l['Tanggal_Mulai']);
                $tgl_selesai = formatTanggal($l['Tanggal_Selesai']);
                $bukti_path = $l['Bukti_Pembayaran'] ?? '';
                $bukti_exists = checkBuktiExists($bukti_path);
                ?>
                <tr>
                    <td style="text-align: center; font-weight: 700; color: var(--text);"><?= $no++ ?></td>
                    <td style="text-align: left;">
                        <div class="cell-name"><?= htmlspecialchars($l['Nama_Customer']) ?></div>
                        <div class="cell-detail"><?= htmlspecialchars($l['Email']) ?></div>
                    </td>
                    <td style="text-align: left;">
                        <div class="cell-name"><?= htmlspecialchars($l['Nama_Tipe']) ?></div>
                        <div class="cell-detail">
                            <?php if (isset($l['Harga_Member']) && $l['Harga_Member'] > 0): ?>
                                <?= rupiahFormat($l['Harga_Member']) ?> /bulan
                            <?php else: ?>
                                - /bulan
                            <?php endif; ?>
                        </div>
                    </td>
                    <td style="text-align: right;">
                        <div class="cell-name"><?= $tgl_mulai ?></div>
                        <div class="cell-detail">s/d <?= $tgl_selesai ?></div>
                    </td>
                    <td style="text-align: center;"><?= htmlspecialchars($l['Metode_Pembayaran'] ?? '-') ?></td>
                    <td class="cell-price" style="text-align: center;"><?= rupiahFormat($l['Total_Bayar'] ?? 0) ?></td>
                    <td style="text-align: center;"><span class="status-pill <?= $status['class'] ?>"><i
                                class="fa-solid <?= $status['icon'] ?>"></i> <?= $status['label'] ?></span></td>
                    <td>
                        <div class="action-btns">
                            <button type="button" class="btn-icon view" onclick="showDetail(<?= $l['ID_Langganan'] ?>)"
                                title="Detail"><i class="fa-solid fa-eye"></i></button>
                            <?php if (!empty($bukti_path)): ?>
                                <!-- Cukup kirimkan ID saja agar aman dari bentrok karakter backslash -->
                                <button type="button" class="btn-icon bukti" onclick="showBukti(<?= $l['ID_Langganan'] ?>)"
                                    title="Lihat Bukti Pembayaran"><i class="fa-solid fa-receipt"></i></button>
                            <?php endif; ?>
                            <?php if ($l['Status'] == 0): ?>
                                <!-- Mengirimkan Nama Customer langsung ke fungsi Konfirmasi & Tolak -->
                                <button type="button" class="btn-icon success"
                                    onclick="confirmKonfirmasi(<?= $l['ID_Langganan'] ?>, '<?= htmlspecialchars($l['Nama_Customer'], ENT_QUOTES) ?>')"
                                    title="Konfirmasi Pembayaran"><i class="fa-solid fa-check"></i></button>
                                <button type="button" class="btn-icon danger"
                                    onclick="confirmTolak(<?= $l['ID_Langganan'] ?>, '<?= htmlspecialchars($l['Nama_Customer'], ENT_QUOTES) ?>')"
                                    title="Tolak"><i class="fa-solid fa-xmark"></i></button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach;
        else: ?>
            <tr>
                <td colspan="8" style="text-align: center; padding: 50px; color: var(--muted);">
                    <i class="fa-solid fa-inbox" style="font-size: 40px; margin-bottom: 16px; opacity: .5; display: block;"></i>
                    <div style="font-size: 14px; font-weight: 700;">Belum ada data langganan</div>
                    <div style="font-size: 12px; margin-top: 4px;">Customer belum melakukan pendaftaran langganan</div>
                </td>
            </tr>
        <?php endif;
        $table_html = ob_get_clean();

        // Render HTML Pagination
        ob_start();
        if ($total_pages > 1): ?>
            <div class="pagination-info">Menampilkan <strong><?= (($page - 1) * $limit) + 1 ?></strong> -
                <strong><?= min($page * $limit, $total_data) ?></strong> dari <strong><?= $total_data ?></strong> data
            </div>
            <div class="pagination-nav">
                <button onclick="changePage(1)" class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>" title="Halaman Pertama"><i
                        class="fa-solid fa-angles-left"></i></button>
                <button onclick="changePage(<?= max(1, $page - 1) ?>)" class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>"
                    title="Halaman Sebelumnya"><i class="fa-solid fa-angle-left"></i></button>
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <button onclick="changePage(<?= $i ?>)" class="page-btn <?= $i == $page ? 'active' : '' ?>"><?= $i ?></button>
                <?php endfor; ?>
                <button onclick="changePage(<?= min($total_pages, $page + 1) ?>)"
                    class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>" title="Halaman Selanjutnya"><i
                        class="fa-solid fa-angle-right"></i></button>
                <button onclick="changePage(<?= $total_pages ?>)" class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>"
                    title="Halaman Terakhir"><i class="fa-solid fa-angles-right"></i></button>
            </div>
        <?php else: ?>
            <div class="pagination-info">Menampilkan <strong>1</strong> - <strong><?= $total_data ?></strong> dari
                <strong><?= $total_data ?></strong> data
            </div>
        <?php endif;
        $pagination_html = ob_get_clean();

        echo json_encode([
            'success' => true,
            'table' => $table_html,
            'pagination' => $pagination_html,
            'stats' => $stats
        ]);
        exit();
    }
}

// ── GET STATISTICS UNTUK AWAL PAGE LOAD ──
$stats = ['total' => 0, 'menunggu' => 0, 'aktif' => 0, 'berakhir' => 0, 'ditolak' => 0, 'total_omzet' => 0];
$stats_query = sqlsrv_query($conn, "EXEC SP_GetDashboardStats");
if ($stats_query) {
    $stats_row = sqlsrv_fetch_array($stats_query, SQLSRV_FETCH_ASSOC);
    if ($stats_row) {
        $stats['total'] = $stats_row['Total_Langganan'] ?? 0;
        $stats['menunggu'] = $stats_row['Menunggu_Konfirmasi'] ?? 0;
        $stats['aktif'] = $stats_row['Aktif'] ?? 0;
        $stats['berakhir'] = $stats_row['Berakhir'] ?? 0;
        $stats['ditolak'] = $stats_row['Ditolak'] ?? 0;
        $stats['total_omzet'] = $stats_row['Total_Omzet_Aktif'] ?? 0;
    }
}

$current_page = 'langganan';
$sidebar_folder = 'transaksi';
$topbar_title = 'Kelola Langganan';
$topbar_breadcrumb = 'Transaksi / Konfirmasi & Manajemen Langganan';
?>
<!DOCTYPE html>
<html lang="id">

<head>
   <?php include '../includes/favicon.php'; ?>
    <title>Kelola Langganan | HoopBall</title>
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

        :root {
            --gray: #6B7280;
            --gray-lt: rgba(107, 114, 128, .10);
        }

        .content {
            padding: 32px 40px;
            flex: 1;
        }

        /* ---- STAT CARDS ---- */
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 16px;
            margin-bottom: 28px;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: 14px;
            padding: 20px;
            border: 1px solid var(--border);
            position: relative;
            overflow: hidden;
            transition: all .2s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, .08);
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

        .sc-orange::before {
            background: var(--orange);
        }

        .sc-yellow::before {
            background: var(--yellow);
        }

        .sc-green::before {
            background: var(--green);
        }

        .sc-blue::before {
            background: var(--blue);
        }

        .sc-red::before {
            background: var(--red);
        }

        .stat-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }

        .stat-icon-wrap {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }

        .si-orange {
            background: var(--orange-lt);
            color: var(--orange);
        }

        .si-yellow {
            background: var(--yellow-lt);
            color: #D97706;
        }

        .si-green {
            background: var(--green-lt);
            color: var(--green);
        }

        .si-blue {
            background: var(--blue-lt);
            color: var(--blue);
        }

        .si-red {
            background: var(--red-lt);
            color: var(--red);
        }

        .stat-value {
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 28px;
            font-weight: 900;
            color: var(--text);
            line-height: 1;
            margin-bottom: 4px;
        }

        .stat-label {
            font-size: 11px;
            color: var(--muted);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        /* ---- FILTER BAR ---- */
        .action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            gap: 16px;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-input {
            padding: 10px 14px;
            border: 1px solid var(--border);
            border-radius: 10px;
            font-size: 13px;
            font-family: inherit;
            background: var(--card-bg);
            color: var(--text);
            outline: none;
            transition: .2s;
        }

        .filter-input:focus {
            border-color: var(--orange);
            box-shadow: 0 0 0 3px var(--orange-lt);
        }

        .btn-secondary {
            background: var(--card-bg);
            color: var(--text);
            border: 1px solid var(--border);
            padding: 10px 18px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: .2s;
            text-decoration: none;
        }

        .btn-secondary:hover {
            border-color: var(--orange);
            color: var(--orange);
        }

        /* ---- TABLE ---- */
        .card {
            background: var(--card-bg);
            border-radius: 16px;
            border: 1px solid var(--border);
            overflow: hidden;
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

        .card-body {
            padding: 0;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th {
            padding: 14px 16px;
            font-size: 11px;
            font-weight: 800;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .6px;
            border-bottom: 2px solid var(--border-lt);
            text-align: left;
            background: #FAFAFA;
        }

        .data-table td {
            padding: 14px 16px;
            font-size: 13px;
            border-bottom: 1px solid var(--border-lt);
            vertical-align: middle;
        }

        .data-table tbody tr {
            transition: background .15s;
        }

        .data-table tbody tr:hover {
            background: #FAFAFA;
        }

        .data-table tbody tr:last-child td {
            border-bottom: none;
        }

        .cell-name {
            font-weight: 700;
            color: var(--text);
        }

        .cell-detail {
            font-size: 11px;
            color: var(--muted);
            font-weight: 600;
            margin-top: 2px;
        }

        .cell-price {
            font-weight: 800;
            color: var(--orange);
        }

        .status-pill {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .3px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .sp-active {
            background: var(--green-lt);
            color: var(--green);
        }

        .sp-success {
            background: var(--blue-lt);
            color: var(--blue);
        }

        .sp-pending {
            background: var(--yellow-lt);
            color: #D97706;
        }

        .sp-inactive {
            background: var(--red-lt);
            color: var(--red);
        }

        .action-btns {
            display: flex;
            gap: 6px;
        }

        .btn-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: var(--card-bg);
            color: var(--muted);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 13px;
            transition: all .25s cubic-bezier(0.34, 1.56, 0.64, 1);
            position: relative;
            overflow: hidden;
        }

        .btn-icon:hover {
            transform: translateY(-2px) scale(1.08);
            box-shadow: 0 4px 12px rgba(0, 0, 0, .1);
        }

        .btn-icon:active {
            transform: scale(0.95);
        }

        .btn-icon.view {
            color: var(--blue);
            border-color: rgba(59, 130, 246, .25);
            background: var(--blue-lt);
        }

        .btn-icon.view:hover {
            background: var(--blue);
            color: #fff;
            border-color: var(--blue);
            box-shadow: 0 4px 14px rgba(59, 130, 246, .35);
        }

        .btn-icon.bukti {
            color: #8B5CF6;
            border-color: rgba(139, 92, 246, .25);
            background: rgba(139, 92, 246, .1);
        }

        .btn-icon.bukti:hover {
            background: #8B5CF6;
            color: #fff;
            border-color: #8B5CF6;
            box-shadow: 0 4px 14px rgba(139, 92, 246, .35);
        }

        .btn-icon.success {
            color: var(--green);
            border-color: rgba(16, 185, 129, .25);
            background: var(--green-lt);
        }

        .btn-icon.success:hover {
            background: var(--green);
            color: #fff;
            border-color: var(--green);
            box-shadow: 0 4px 14px rgba(16, 185, 129, .35);
        }

        .btn-icon.danger {
            color: var(--red);
            border-color: rgba(239, 68, 68, .25);
            background: var(--red-lt);
        }

        .btn-icon.danger:hover {
            background: var(--red);
            color: #fff;
            border-color: var(--red);
            box-shadow: 0 4px 14px rgba(239, 68, 68, .35);
        }

        /* ---- PAGINATION ---- */
        .pagination-wrap {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-top: none;
            border-radius: 0 0 16px 16px;
            padding: 16px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
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

        /* ---- MODAL DETAIL ---- */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, .5);
            z-index: 1000;
            backdrop-filter: blur(4px);
            align-items: center;
            justify-content: center;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal {
            background: var(--card-bg);
            border-radius: 16px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, .2);
            animation: modalIn .3s ease-out;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        .modal::-webkit-scrollbar {
            display: none;
        }

        @keyframes modalIn {
            from {
                opacity: 0;
                transform: translateY(20px) scale(.95);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .modal-header {
            padding: 24px 28px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-title {
            font-size: 18px;
            font-weight: 800;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-title i {
            color: var(--orange);
        }

        .modal-close {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            border: none;
            background: var(--bg);
            color: var(--muted);
            cursor: pointer;
            font-size: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: .2s;
        }

        .modal-close:hover {
            background: var(--red-lt);
            color: var(--red);
        }

        .modal-body {
            padding: 24px 28px;
        }

        .modal-footer {
            padding: 20px 28px;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .detail-item {
            padding: 12px;
            background: var(--bg);
            border-radius: 10px;
        }

        .detail-label {
            font-size: 11px;
            font-weight: 700;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .5px;
            margin-bottom: 4px;
        }

        .detail-value {
            font-size: 14px;
            font-weight: 700;
            color: var(--text);
        }

        .detail-value.price {
            color: var(--orange);
            font-size: 16px;
        }

        .detail-full {
            grid-column: span 2;
        }

        @media(max-width: 1200px) {
            .stat-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media(max-width: 768px) {
            .content {
                padding: 20px;
            }

            .stat-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .action-bar {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-group {
                width: 100%;
            }

            .detail-grid {
                grid-template-columns: 1fr;
            }

            .detail-full {
                grid-column: span 1;
            }

            .pagination-wrap {
                flex-direction: column;
                gap: 12px;
            }
        }

        /* Tambahan CSS khusus kotak pencarian agar sejajar */
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
            right: 8px;
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

    <!-- SIDEBAR -->
    <?php include '../includes/sidebar.php'; ?>

    <main class="main">
        <?php include '../includes/topbar.php'; ?>

        <div class="content">
            <!-- STAT CARDS -->
            <div class="stat-grid">
                <div class="stat-card sc-orange">
                    <div class="stat-header">
                        <div class="stat-icon-wrap si-orange"><i class="fa-solid fa-crown"></i></div>
                    </div>
                    <div class="stat-value" id="stat-total"><?= $stats['total'] ?></div>
                    <div class="stat-label">Total Langganan</div>
                </div>
                <div class="stat-card sc-yellow">
                    <div class="stat-header">
                        <div class="stat-icon-wrap si-yellow"><i class="fa-solid fa-clock"></i></div>
                    </div>
                    <div class="stat-value" id="stat-menunggu"><?= $stats['menunggu'] ?></div>
                    <div class="stat-label">Menunggu Konfirmasi</div>
                </div>
                <div class="stat-card sc-green">
                    <div class="stat-header">
                        <div class="stat-icon-wrap si-green"><i class="fa-solid fa-circle-check"></i></div>
                    </div>
                    <div class="stat-value" id="stat-aktif"><?= $stats['aktif'] ?></div>
                    <div class="stat-label">Aktif</div>
                </div>
                <div class="stat-card sc-blue">
                    <div class="stat-header">
                        <div class="stat-icon-wrap si-blue"><i class="fa-solid fa-circle-xmark"></i></div>
                    </div>
                    <div class="stat-value" id="stat-berakhir"><?= $stats['berakhir'] ?></div>
                    <div class="stat-label">Berakhir</div>
                </div>
                <div class="stat-card sc-red">
                    <div class="stat-header">
                        <div class="stat-icon-wrap si-red"><i class="fa-solid fa-ban"></i></div>
                    </div>
                    <div class="stat-value" id="stat-ditolak"><?= $stats['ditolak'] ?></div>
                    <div class="stat-label">Ditolak</div>
                </div>
            </div>

            <!-- INFO BOX -->
            <div
                style="background: var(--blue-lt); border: 1px solid var(--blue); border-radius: 12px; padding: 16px 20px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px;">
                <i class="fa-solid fa-circle-info" style="color: var(--blue); font-size: 20px;"></i>
                <div style="font-size: 13px; color: var(--text); line-height: 1.5;">
                    <strong>Peran Karyawan:</strong> Customer mendaftar langganan member melalui website. Karyawan hanya
                    mengkonfirmasi pembayaran yang sudah dilakukan customer.
                    <span style="color: var(--muted);">Langganan baru dengan status "Menunggu" menunggu verifikasi
                        pembayaran Anda. Gunakan tombol <strong>Bukti Pembayaran</strong> untuk memeriksa bukti
                        transfer/QRIS yang diunggah customer sebelum mengkonfirmasi.</span>
                </div>
            </div>

            <!-- FILTER BAR -->
            <div class="action-bar">
                <!-- Sisi Kiri: Kotak Pencarian Terpadu -->
                <div class="search-box">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="src" placeholder="Cari customer atau tipe member... (Tekan Enter)"
                        onkeypress="handleSearch(event)" value="">
                    <button type="button" onclick="clearSearch()" class="btn-clear-search" id="btnClearSearch"
                        style="display: none;">
                        <i class="fa-solid fa-circle-xmark"></i>
                    </button>
                </div>

                <!-- Sisi Kanan: Filter Dropdown & Tanggal -->
                <div class="action-right">
                    <div class="filter-group">
                        <form id="formFilter" onsubmit="handleFilterSubmit(event)"
                            style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                            <select name="filter_status" id="filter_status" class="filter-input"
                                onchange="handleFilterChange()">
                                <option value="all">Semua Status</option>
                                <option value="0">Menunggu Konfirmasi</option>
                                <option value="1">Aktif</option>
                                <option value="2">Berakhir</option>
                                <option value="3">Ditolak</option>
                            </select>
                            <input type="date" name="filter_tanggal" id="filter_tanggal" class="filter-input"
                                onchange="handleFilterChange()">
                            <button type="submit" class="btn-secondary"><i class="fa-solid fa-filter"></i>
                                Filter</button>
                            <button type="button" onclick="resetFilters()" class="btn-secondary"><i
                                    class="fa-solid fa-rotate-left"></i> Reset</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- LANGGANAN TABLE -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fa-solid fa-list"></i> Daftar Langganan</div>
                    <span style="font-size: 12px; color: var(--muted); font-weight: 600;" id="header-badge">0 data
                        ditemukan</span>
                </div>
                <div class="card-body" style="overflow-x: auto;">
                    <table class="data-table" id="tbl">
                        <thead>
                            <tr>
                                <th style="width: 70px; text-align: center;">No.</th>
                                <!-- Rata Kiri -->
                                <th style="text-align: left;">Customer</th>
                                <th style="text-align: left;">Tipe Member</th>
                                <th style="text-align: right;">Periode</th>
                                <th style="text-align: center;">Metode Bayar</th>
                                <!-- Rata Tengah -->
                                <th style="text-align: center;">Total Bayar</th>
                                <th style="text-align: center;">Status</th>
                                <th style="text-align: center;">Aksi</th>
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

    <!-- MODAL DETAIL -->
    <div class="modal-overlay" id="modalDetail">
        <div class="modal">
            <div class="modal-header">
                <div class="modal-title"><i class="fa-solid fa-file-invoice"></i> Detail Langganan</div>
                <button class="modal-close" onclick="closeModal('modalDetail')"><i
                        class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="modal-body" id="detailContent"></div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('modalDetail')"><i
                        class="fa-solid fa-xmark"></i> Tutup</button>
            </div>
        </div>
    </div>

    <!-- MODAL BUKTI PEMBAYARAN -->
    <div class="modal-overlay" id="modalBukti">
        <div class="modal" style="max-width: 520px;">
            <div class="modal-header">
                <div class="modal-title"><i class="fa-solid fa-receipt"></i> Bukti Pembayaran</div>
                <button class="modal-close" onclick="closeModal('modalBukti')"><i
                        class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="modal-body" id="buktiContent" style="text-align: center;"></div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('modalBukti')"><i
                        class="fa-solid fa-xmark"></i> Tutup</button>
            </div>
        </div>
    </div>

    <script src="../asset/js/global.js"></script>

    <script>
        // State management untuk Filter & Pagination
        let currentPage = 1;
        let currentStatus = 'all';
        let currentCustomer = '';
        let currentTanggal = '';

        // ============================================
        // GET DATA TABEL (AJAX REFRESH)
        // ============================================
        async function loadTableData() {
            const url = `langganan.php?action=get_table_data&page=${currentPage}&filter_status=${currentStatus}&filter_customer=${encodeURIComponent(currentCustomer)}&filter_tanggal=${currentTanggal}`;
            try {
                const response = await fetch(url);
                const data = await response.json();
                if (data.success) {
                    // Update stats cards
                    document.getElementById('stat-total').textContent = data.stats.total;
                    document.getElementById('stat-menunggu').textContent = data.stats.menunggu;
                    document.getElementById('stat-aktif').textContent = data.stats.aktif;
                    document.getElementById('stat-berakhir').textContent = data.stats.berakhir;
                    document.getElementById('stat-ditolak').textContent = data.stats.ditolak;
                    document.getElementById('header-badge').textContent = `${data.stats.total} data ditemukan`;

                    // Update Table & Pagination
                    document.querySelector('#tbl tbody').innerHTML = data.table;
                    document.querySelector('.pagination-wrap').innerHTML = data.pagination;

                    // Handle Clear Search visibility
                    const btnClear = document.getElementById('btnClearSearch');
                    if (currentCustomer !== '') {
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
        // EVENT FILTER & SEARCH HANDLER
        // ============================================
        function handleFilterChange() {
            currentStatus = document.getElementById('filter_status').value;
            currentTanggal = document.getElementById('filter_tanggal').value;
            currentPage = 1;
            loadTableData();
        }

        function applyFilters() {
            currentStatus = document.getElementById('filter_status').value;
            currentTanggal = document.getElementById('filter_tanggal').value;
            currentCustomer = document.getElementById('src').value.trim();
            currentPage = 1;
            loadTableData();
        }

        // ============ BUKTI PEMBAYARAN RESOLVE (JS-SIDE) ============
        function resolveBuktiPath(path) {
            if (!path) return '';
            // Ganti karakter backslash (\) khas Windows menjadi forward slash (/) agar bisa dibaca browser
            path = path.replace(/\\/g, '/');
            if (path.startsWith('http://') || path.startsWith('https://')) return path;
            if (path.startsWith('../')) return path;
            if (path.startsWith('/')) return '..' + path;
            return '../' + path;
        }

        function handleFilterSubmit(event) {
            event.preventDefault();
            applyFilters();
        }

        function handleSearch(event) {
            if (event.key === 'Enter') {
                applyFilters();
            }
        }

        function clearSearch() {
            document.getElementById('src').value = '';
            currentCustomer = '';
            currentPage = 1;
            loadTableData();
        }

        function resetFilters() {
            document.getElementById('filter_status').value = 'all';
            document.getElementById('filter_tanggal').value = '';
            document.getElementById('src').value = '';
            currentStatus = 'all';
            currentTanggal = '';
            currentCustomer = '';
            currentPage = 1;
            loadTableData();
        }

        // ============================================
        // ALERT HELPER FUNCTIONS (PREVENTS STUCK MODAL)
        // ============================================
        function showSuccess(title, message) {
            Swal.close();
            Swal.fire({
                icon: 'success',
                title: title,
                text: message,
                confirmButtonColor: '#10B981'
            });
        }

        function showError(title, message) {
            Swal.close();
            Swal.fire({
                icon: 'error',
                title: title,
                text: message,
                confirmButtonColor: '#EF4444'
            });
        }

        // ============================================
        // DETAIL & MODAL HANDLER
        // ============================================
        function openModal(id) {
            document.getElementById(id).classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        // Parameter namaCustomer ditambahkan ke confirmTolak & confirmKonfirmasi agar tidak crash
        function confirmTolak(id, namaCustomer) {
            const customer = namaCustomer || 'Customer';

            Swal.fire({
                title: 'Tolak Pembayaran?',
                html: `Anda yakin ingin <strong style="color: var(--red);">MENOLAK</strong> pendaftaran langganan dari <strong>${customer}</strong>?<br><span style="color: var(--red); font-size: 12px;">Pendaftaran akan dibatalkan permanen.</span>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#EF4444',
                cancelButtonColor: '#6B7280',
                confirmButtonText: 'Ya, Tolak',
                cancelButtonText: 'Batal',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    // Otomatis mengirimkan alasan default 'Ditolak oleh Karyawan' ke database
                    executeRejectPayment(id, 'Ditolak oleh Karyawan');
                }
            });
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
            document.body.style.overflow = '';
        }

        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', function (e) {
                if (e.target === this) {
                    this.classList.remove('active');
                    document.body.style.overflow = '';
                }
            });
        });

        // ============ DETAIL AJAK LANGGANAN ============
        async function showDetail(id) {
            try {
                const response = await fetch(`langganan.php?action=get_detail&id=${id}`);
                const rawText = await response.text();
                let res;
                try {
                    res = JSON.parse(rawText);
                } catch (err) {
                    console.error("RAW:", rawText);
                    Swal.fire({ icon: 'error', title: 'Server Error', html: `<div style="text-align:left;font-size:12px;color:red;">${rawText}</div>` });
                    return;
                }

                if (res.success) {
                    const l = res.data;
                    const statusMap = {
                        0: { label: 'Menunggu Konfirmasi', class: 'sp-pending', icon: 'fa-clock' },
                        1: { label: 'Aktif', class: 'sp-active', icon: 'fa-circle-check' },
                        2: { label: 'Berakhir', class: 'sp-success', icon: 'fa-circle-xmark' },
                        3: { label: 'Ditolak', class: 'sp-inactive', icon: 'fa-ban' }
                    };
                    const status = statusMap[l.Status] || statusMap[0];

                    const tglMulai = l.Tanggal_Mulai ? new Date(l.Tanggal_Mulai.date || l.Tanggal_Mulai).toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) : '-';
                    const tglSelesai = l.Tanggal_Selesai ? new Date(l.Tanggal_Selesai.date || l.Tanggal_Selesai).toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) : '-';

                    const hargaMember = (l.Harga_Member !== undefined && l.Harga_Member !== null) ? formatRupiah(l.Harga_Member) : '-';
                    const potongan = (l.Potongan_Harga !== undefined && l.Potongan_Harga !== null) ? formatRupiah(l.Potongan_Harga) : '-';

                    const buktiInfo = l.Bukti_Pembayaran
                        ? `<div class="detail-item"><div class="detail-label">Bukti Pembayaran</div><div class="detail-value"><button class="btn-secondary" style="margin-top:4px" onclick="closeModal('modalDetail'); showBukti(${l.ID_Langganan}, '${l.Bukti_Pembayaran}');"><i class="fa-solid fa-receipt"></i> Lihat Bukti Pembayaran</button></div></div>`
                        : `<div class="detail-item"><div class="detail-label">Bukti Pembayaran</div><div class="detail-value" style="color:var(--muted);font-weight:500">Belum diunggah customer</div></div>`;

                    const html = `
                <div class="detail-grid">
                    <div class="detail-item"><div class="detail-label">Status</div><div class="detail-value status"><span class="status-pill ${status.class}"><i class="fa-solid ${status.icon}"></i> ${status.label}</span></div></div>
                    <div class="detail-item"><div class="detail-label">Customer</div><div class="detail-value">${l.Nama_Customer}</div><div style="font-size: 11px; color: var(--muted); margin-top: 2px;">${l.Email || '-'} | ${l.No_Telepon || '-'}</div></div>
                    <div class="detail-item"><div class="detail-label">Tipe Member</div><div class="detail-value">${l.Nama_Tipe}</div></div>
                    <div class="detail-item"><div class="detail-label">Tanggal Mulai</div><div class="detail-value">${tglMulai}</div></div>
                    <div class="detail-item"><div class="detail-label">Tanggal Selesai</div><div class="detail-value">${tglSelesai}</div></div>
                    <div class="detail-item"><div class="detail-label">Metode Pembayaran</div><div class="detail-value">${l.Metode_Pembayaran || '-'}</div></div>
                    <div class="detail-item"><div class="detail-label">Input Oleh</div><div class="detail-value">${l.Nama_Karyawan_Input || 'System'}</div></div>
                    <div class="detail-item"><div class="detail-label">Harga Member</div><div class="detail-value">${hargaMember}</div></div>
                    <div class="detail-item"><div class="detail-label">Potongan Harga</div><div class="detail-value">${potongan}</div></div>
                    ${buktiInfo}
                    <div class="detail-item detail-full"><div class="detail-label">Total Bayar</div><div class="detail-value price">${formatRupiah(l.Total_Bayar || 0)}</div></div>
                </div>
            `;

                    document.getElementById('detailContent').innerHTML = html;
                    openModal('modalDetail');
                } else {
                    showError('Gagal!', res.msg);
                }
            } catch (err) {
                console.error("Gagal showDetail AJAX:", err);
                showError('Error', 'Gagal memproses data detail.');
            }
        }

        // ============ BUKTI PEMBAYARAN ============
        async function showBukti(id) {
            try {
                // Ambil data path bukti terbaru langsung dari server menggunakan AJAX secara instan
                const response = await fetch(`langganan.php?action=get_detail&id=${id}`);
                const res = await response.json();

                if (res.success) {
                    const path = res.data.Bukti_Pembayaran;

                    if (!path) {
                        Swal.fire({
                            icon: 'info',
                            title: 'Belum Ada Bukti',
                            text: 'Bukti pembayaran tidak ditemukan untuk langganan ini.',
                            confirmButtonColor: 'var(--orange)'
                        });
                        return;
                    }

                    const url = resolveBuktiPath(path);
                    const ext = url.split('.').pop().toLowerCase();
                    let html = '';

                    if (ext === 'pdf') {
                        html = `<iframe src="${url}" style="width:100%;height:480px;border:1px solid var(--border);border-radius:10px"></iframe>
                        <div style="margin-top:14px"><a href="${url}" target="_blank" class="btn-secondary"><i class="fa-solid fa-up-right-from-square"></i> Buka di Tab Baru</a></div>`;
                    } else {
                        html = `<img src="${url}" alt="Bukti Pembayaran" style="max-width:100%;border-radius:10px;border:1px solid var(--border)">
                        <div style="margin-top:14px"><a href="${url}" target="_blank" class="btn-secondary"><i class="fa-solid fa-up-right-from-square"></i> Buka di Tab Baru</a></div>`;
                    }

                    document.getElementById('buktiContent').innerHTML = html;
                    openModal('modalBukti');
                } else {
                    showError('Gagal!', res.msg);
                }
            } catch (error) {
                console.error("Gagal load bukti:", error);
                showError('Error', 'Gagal memuat bukti pembayaran.');
            }
        }

        // ============ PROSES AJAX TRANSAKSI (KONFIRMASI / TOLAK) ============
        function confirmKonfirmasi(id, namaCustomer) {
            const customer = namaCustomer || 'Customer';

            Swal.fire({
                title: 'Konfirmasi Pembayaran?',
                html: `Customer <strong>${customer}</strong> sudah melakukan pembayaran?<br><span style="color: var(--muted); font-size: 12px;">Status langganan akan berubah menjadi <strong>Aktif</strong></span>`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#10B981',
                cancelButtonColor: '#6B7280',
                confirmButtonText: 'Ya, Konfirmasi',
                cancelButtonText: 'Batal',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    executeConfirmPayment(id);
                }
            });
        }

        async function executeConfirmPayment(id) {
            Swal.fire({
                title: 'Memproses...',
                text: 'Mengonfirmasi langganan...',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });
            try {
                const formData = new FormData();
                formData.append('id_langganan', id);
                formData.append('action', 'confirm_payment');

                const response = await fetch('langganan.php', { method: 'POST', body: formData });
                const res = await response.json();
                if (res.success) {
                    showSuccess('Berhasil!', res.msg);
                    loadTableData();
                } else {
                    showError('Gagal!', res.msg);
                }
            } catch (error) {
                showError('Gagal!', 'Terjadi kesalahan sistem.');
            }
        }

        async function executeRejectPayment(id, alasan) {
            Swal.fire({
                title: 'Memproses...',
                text: 'Menolak langganan...',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });
            try {
                const formData = new FormData();
                formData.append('id_langganan', id);
                formData.append('alasan_tolak', alasan);
                formData.append('action', 'tolak_bayar');

                const response = await fetch('langganan.php', { method: 'POST', body: formData });
                const res = await response.json();
                if (res.success) {
                    showSuccess('Berhasil!', res.msg);
                    loadTableData();
                } else {
                    showError('Gagal!', res.msg);
                }
            } catch (error) {
                showError('Gagal!', 'Terjadi kesalahan sistem.');
            }
        }

        function formatRupiah(angka) {
            const num = parseFloat(angka);
            return 'Rp ' + (isNaN(num) ? 0 : num).toLocaleString('id-ID');
        }

        // ============================================
        // INITIAL LOAD (PENGGERAK AWAL TABEL)
        // ============================================
        document.addEventListener('DOMContentLoaded', function () {
            loadTableData();
        });
    </script>
    <?php if (function_exists('tampilkan_sensor_auto_logout')) tampilkan_sensor_auto_logout(); ?>
</body>

</html>