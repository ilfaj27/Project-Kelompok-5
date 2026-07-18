<?php
session_start();
$path_prefix = "../";
include '../includes/auth_helper.php';
include '../includes/config.php';

// ============================================================================
// CEK AKSES - KARYAWAN ONLY
// ============================================================================
cek_akses('karyawan');

$nama = $_SESSION['nama'] ?? 'Karyawan';
$role = $_SESSION['role'] ?? 'karyawan';
$id_karyawan = $_SESSION['id_karyawan'] ?? '';

// ============================================================================
// AMBIL FOTO PROFIL
// ============================================================================
$profile_photo = '';
if (!empty($id_karyawan)) {
    $stmt_photo = sqlsrv_query($conn, "{call sp_Karyawan_GetProfilePhoto(?)}", array($id_karyawan));
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
// AUTO-COMPLETE BOOKING (OTOMATIS SELESAI)
// ============================================================================
sqlsrv_query($conn, "{call sp_Booking_AutoComplete}");

// ============================================================================
// STATUS BOOKING
// ============================================================================
$status_labels = [
    0 => ['label' => 'Menunggu', 'class' => 'sp-pending', 'icon' => 'fa-clock'],
    1 => ['label' => 'Berhasil', 'class' => 'sp-active', 'icon' => 'fa-check-circle'],
    2 => ['label' => 'Selesai', 'class' => 'sp-success', 'icon' => 'fa-flag-checkered'],
    3 => ['label' => 'Dibatalkan', 'class' => 'sp-inactive', 'icon' => 'fa-ban']
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

function resolveBuktiPath($path)
{
    if (empty($path))
        return '';
    if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0)
        return $path;
    if (strpos($path, '../') === 0)
        return $path;
    if (strpos($path, '/') === 0)
        return '..' . $path;
    if (strpos($path, 'uploads/') === 0)
        return '../' . $path;
    if (strpos($path, 'asset/') === 0) {
        return '../' . $path;
    }
    return '../asset/Bukti_Pembayaran/' . ltrim($path, '/');
}

// ── PROSES AJAX REQUESTS ──
$is_ajax = isset($_GET['action']) || isset($_POST['action']);
if ($is_ajax) {
    header('Content-Type: application/json');
    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    // Action: Ambil Detail Booking (AJAX)
    if ($action === 'get_detail') {
        $id = intval($_GET['id'] ?? 0);

        // --- UBAHAN DI SINI: Kita menggunakan kueri SQL Manual (Direct JOIN) daripada Stored Procedure ---
        // Karena Stored Procedure lama masih nyangkut di database tidak membawa kolom lengkap
        $sql = "
            SELECT 
                   B.*, 
                   C.Nama_Customer, C.Email, C.No_Telepon,
                   L.Nama_Lapangan, L.Harga_Sewa,
                   J.Tanggal, J.Jam_Mulai, J.Jam_Selesai,
                   P.Nama_Promo, P.Diskon,
                   K.Nama_Karyawan as Nama_Karyawan_Input
            FROM Booking B 
            INNER JOIN Customer C ON B.ID_Customer = C.ID_Customer
            INNER JOIN Jadwal J ON B.ID_Jadwal = J.ID_Jadwal 
            INNER JOIN Lapangan L ON J.ID_Lapangan = L.ID_Lapangan
            LEFT JOIN Promo P ON B.ID_Promo = P.ID_Promo
            LEFT JOIN Karyawan K ON B.ID_Karyawan = K.ID_Karyawan
            WHERE B.ID_Booking = ?
        ";

        $q_booking = sqlsrv_query($conn, $sql, array($id));

        if ($q_booking && $booking_data = safeFetch($q_booking)) {
            // Karena SQL-nya di atas sudah lengkap, sekarang data ini tidak akan null lagi
            $booking_data['TanggalFormatted'] = formatTanggal($booking_data['Tanggal'] ?? '');
            $booking_data['TanggalBookingFormatted'] = formatTanggal($booking_data['Tanggal_Booking'] ?? '');
            $booking_data['JamMulaiFormatted'] = formatJam($booking_data['Jam_Mulai'] ?? '');
            $booking_data['JamSelesaiFormatted'] = formatJam($booking_data['Jam_Selesai'] ?? '');

            ob_clean(); // Amankan JSON
            echo @json_encode(['success' => true, 'data' => $booking_data]);
        } else {
            ob_clean();
            echo json_encode(['success' => false, 'msg' => 'Data booking tidak ditemukan atau gagal menghubungkan ke relasi Jadwal/Lapangan.']);
        }
        exit();
    }

    // Action: Konfirmasi Pembayaran (AJAX)
    if ($action === 'confirm_payment') {
        $id = intval($_GET['id'] ?? 0);
        $stmt = sqlsrv_query($conn, "{call sp_Booking_ConfirmPayment(?, ?)}", array($id, $nama));
        if ($stmt) {
            echo json_encode(['success' => true, 'msg' => 'Pembayaran berhasil dikonfirmasi.']);
        } else {
            echo json_encode(['success' => false, 'msg' => 'Gagal mengkonfirmasi pembayaran.']);
        }
        exit();
    }

    // Action: Pembatalan Booking (AJAX)
    if ($action === 'cancel_booking') {
        $id_booking = intval($_POST['id_booking'] ?? 0);
        $alasan = trim($_POST['alasan_batal'] ?? 'Dibatalkan oleh Karyawan');

        $q_booking = sqlsrv_query($conn, "{call sp_Booking_GetDetail(?)}", array($id_booking));
        $booking_data = $q_booking ? safeFetch($q_booking) : null;

        if ($booking_data) {
            $total_bayar = (float) $booking_data['Total_Bayar'];
            $biaya_batal = 0;
            $nominal_refund = $total_bayar;
            $metode_refund = $booking_data['Metode_Pembayaran'];

            $stmt_batal = sqlsrv_query(
                $conn,
                "{call sp_Booking_CancelByKaryawan(?, ?, ?, ?, ?, ?, ?)}",
                array($id_booking, $id_karyawan, $alasan, $biaya_batal, $nominal_refund, $metode_refund, $nama)
            );

            if ($stmt_batal) {
                echo json_encode(['success' => true, 'msg' => 'Booking berhasil dibatalkan.']);
            } else {
                echo json_encode(['success' => false, 'msg' => 'Gagal memproses pembatalan.']);
            }
        } else {
            echo json_encode(['success' => false, 'msg' => 'Data booking tidak ditemukan.']);
        }
        exit();
    }

    // Action: Muat Ulang Tabel & Statistik (Dynamic AJAX Refresh)
    if ($action === 'get_table_data') {
        $filter_status = isset($_GET['filter_status']) ? $_GET['filter_status'] : 'all';
        $filter_customer = isset($_GET['filter_customer']) ? trim($_GET['filter_customer']) : '';
        $filter_tanggal = isset($_GET['filter_tanggal']) ? trim($_GET['filter_tanggal']) : '';
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

        $status_val = ($filter_status === '' || $filter_status === 'all') ? -1 : (int) $filter_status;
        $tgl_val = empty($filter_tanggal) ? null : $filter_tanggal;

        // Ambil data dalam jumlah besar terlebih dahulu untuk disaring pencariannya secara gabungan (Customer ATAU Lapangan)
        $bookings_all = [];
        $q_booking = sqlsrv_query($conn, "{call sp_Booking_GetPagedList(?, ?, ?, ?, ?)}", array($status_val, '', $tgl_val, 0, 100000));
        if ($q_booking) {
            while ($row = safeFetch($q_booking)) {
                // Saring berdasarkan Nama Customer ATAU Nama Lapangan
                if ($filter_customer !== '') {
                    $cust_match = strpos(strtolower($row['Nama_Customer'] ?? ''), strtolower($filter_customer)) !== false;
                    $lap_match = strpos(strtolower($row['Nama_Lapangan'] ?? ''), strtolower($filter_customer)) !== false;
                    if (!$cust_match && !$lap_match) {
                        continue; // Lewati jika tidak cocok dua-duanya
                    }
                }
                $bookings_all[] = $row;
            }
        }

        // Hitung total data terfilter
        $total_data = count($bookings_all);

        $limit = 10;
        $total_pages = max(1, ceil($total_data / $limit));
        $page = min($page, $total_pages);
        $offset = ($page - 1) * $limit;

        // Potong array sesuai offset pagination saat ini
        $bookings = array_slice($bookings_all, $offset, $limit);

        // Ambil Statistik Terkini via UDF
        $stats = ['total' => 0, 'menunggu' => 0, 'berhasil' => 0, 'selesai' => 0, 'dibatalkan' => 0, 'total_omzet' => 0, 'total_refund' => 0];

        if ($filter_customer !== '') {
            // Apabila ada kata kunci pencarian, statistik dihitung dari array hasil filter demi akurasi total real-time
            $stats['total'] = $total_data;
            foreach ($bookings_all as $b) {
                if ($b['Status'] == 0)
                    $stats['menunggu']++;
                elseif ($b['Status'] == 1)
                    $stats['berhasil']++;
                elseif ($b['Status'] == 2)
                    $stats['selesai']++;
                elseif ($b['Status'] == 3)
                    $stats['dibatalkan']++;

                if ($b['Status'] == 1 || $b['Status'] == 2) {
                    $stats['total_omzet'] += (float) $b['Total_Bayar'];
                }
            }
        } else {
            // Jika tidak ada pencarian, gunakan hitungan cepat UDF SQL bawaan
            $q_stats = sqlsrv_query($conn, "SELECT * FROM dbo.fn_Booking_GetDashboardStats(?, ?, ?)", array($status_val, '', $tgl_val));
            if ($q_stats) {
                $row_stats = safeFetch($q_stats);
                if ($row_stats) {
                    $stats['total'] = (int) ($row_stats['Total'] ?? 0);
                    $stats['menunggu'] = (int) ($row_stats['Menunggu'] ?? 0);
                    $stats['berhasil'] = (int) ($row_stats['Berhasil'] ?? 0);
                    $stats['selesai'] = (int) ($row_stats['Selesai'] ?? 0);
                    $stats['dibatalkan'] = (int) ($row_stats['Dibatalkan'] ?? 0);
                    $stats['total_omzet'] = (float) ($row_stats['Total_Omzet'] ?? 0);
                    $stats['total_refund'] = (float) ($row_stats['Total_Refund'] ?? 0);
                }
            }
        }

        // Render HTML Tabel Body
        ob_start();
        if (count($bookings) > 0):
            $no = $offset + 1;
            foreach ($bookings as $b):
                $status = $status_labels[$b['Status']] ?? $status_labels[0];
                $tanggal_jadwal = formatTanggal($b['Tanggal']);
                $jam_mulai = formatJam($b['Jam_Mulai']);
                $jam_selesai = formatJam($b['Jam_Selesai']);
                ?>
                <tr>
                    <td style="text-align: center; font-weight: 700; color: var(--text);"><?= $no++ ?></td>
                    <!-- Customer jadi RATA KIRI -->
                    <td style="text-align: left;">
                        <div class="cell-name"><?= htmlspecialchars($b['Nama_Customer']) ?></div>
                        <div class="cell-detail"><?= htmlspecialchars($b['Email']) ?></div>
                    </td>
                    <!-- Lapangan & Jadwal jadi RATA KIRI -->
                    <td style="text-align: left;">
                        <div class="cell-name"><?= htmlspecialchars($b['Nama_Lapangan']) ?></div>
                        <div class="cell-detail"><?= $tanggal_jadwal ?> | <?= $jam_mulai ?> - <?= $jam_selesai ?></div>
                    </td>
                    <td style="text-align: right;"><?= formatTanggal($b['Tanggal_Booking']) ?></td>
                    <td style="text-align: center;"><?= htmlspecialchars($b['Metode_Pembayaran']) ?></td>
                    <!-- Total Bayar jadi RATA TENGAH -->
                    <td class="cell-price" style="text-align: center;"><?= rupiahFormat($b['Total_Bayar']) ?></td>
                    <td style="text-align: center;">
                        <span class="status-pill <?= $status['class'] ?>">
                            <i class="fa-solid <?= $status['icon'] ?>"></i> <?= $status['label'] ?>
                        </span>
                    </td>
                    <td>
                        <div class="action-btns">
                            <button type="button" class="btn-icon view" onclick="showDetail(<?= $b['ID_Booking'] ?>)" title="Detail"><i
                                    class="fa-solid fa-eye"></i></button>
                            <?php if (!empty($b['Bukti_Pembayaran'])): ?>
                                <button type="button" class="btn-icon bukti"
                                    onclick="showBukti(<?= $b['ID_Booking'] ?>, '<?= htmlspecialchars($b['Bukti_Pembayaran'], ENT_QUOTES) ?>')"
                                    title="Lihat Bukti Pembayaran"><i class="fa-solid fa-receipt"></i></button>
                            <?php endif; ?>
                            <?php if ($b['Status'] == 0): ?>
                                <button type="button" class="btn-icon success" onclick="confirmBayar(<?= $b['ID_Booking'] ?>)"
                                    title="Konfirmasi Pembayaran"><i class="fa-solid fa-check"></i></button>
                                <button type="button" class="btn-icon danger" onclick="confirmBatal(<?= $b['ID_Booking'] ?>)"
                                    title="Batalkan"><i class="fa-solid fa-xmark"></i></button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach;
        else: ?>
            <tr>
                <td colspan="8" style="text-align: center; padding: 50px; color: var(--muted);">
                    <i class="fa-solid fa-inbox" style="font-size: 40px; margin-bottom: 16px; opacity: .5; display: block;"></i>
                    <div style="font-size: 14px; font-weight: 700;">Belum ada data booking</div>
                    <div style="font-size: 12px; margin-top: 4px;">Customer belum melakukan booking</div>
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
$stats = ['total' => 0, 'menunggu' => 0, 'berhasil' => 0, 'selesai' => 0, 'dibatalkan' => 0, 'total_omzet' => 0, 'total_refund' => 0];
$q_stats = sqlsrv_query($conn, "SELECT * FROM dbo.fn_Booking_GetDashboardStats(?, ?, ?)", array(-1, '', null));
if ($q_stats) {
    $row_stats = safeFetch($q_stats);
    if ($row_stats) {
        $stats['total'] = (int) ($row_stats['Total'] ?? 0);
        $stats['menunggu'] = (int) ($row_stats['Menunggu'] ?? 0);
        $stats['berhasil'] = (int) ($row_stats['Berhasil'] ?? 0);
        $stats['selesai'] = (int) ($row_stats['Selesai'] ?? 0);
        $stats['dibatalkan'] = (int) ($row_stats['Dibatalkan'] ?? 0);
        $stats['total_omzet'] = (float) ($row_stats['Total_Omzet'] ?? 0);
        $stats['total_refund'] = (float) ($row_stats['Total_Refund'] ?? 0);
    }
}

$current_page = 'booking';
$sidebar_folder = 'transaksi';
$topbar_title = 'Kelola Booking';
$topbar_breadcrumb = 'Transaksi / Konfirmasi & Manajemen Booking';
?>
<!DOCTYPE html>
<html lang="id">

<head>
   <?php include '../includes/favicon.php'; ?>
    <title>Kelola Booking | HoopBall</title>
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
            padding: 14px 20px;
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

        /* Tambahan CSS khusus agar tombol hapus pencarian rapi */
        .btn-clear-search {
            position: absolute;
            right: 8px;
            /* Menggunakan setelan 8px agar tidak bertabrakan dengan border kanan */
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

        /* Sisi Kiri: Kotak Pencarian */
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
            /* Padding kanan diatur 40px agar muat tombol x silang */
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
                        <div class="stat-icon-wrap si-orange"><i class="fa-solid fa-calendar-check"></i></div>
                    </div>
                    <div class="stat-value" id="stat-total"><?= $stats['total'] ?></div>
                    <div class="stat-label">Total Booking</div>
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
                        <div class="stat-icon-wrap si-green"><i class="fa-solid fa-check-circle"></i></div>
                    </div>
                    <div class="stat-value" id="stat-berhasil"><?= $stats['berhasil'] ?></div>
                    <div class="stat-label">Berhasil</div>
                </div>
                <div class="stat-card sc-blue">
                    <div class="stat-header">
                        <div class="stat-icon-wrap si-blue"><i class="fa-solid fa-flag-checkered"></i></div>
                    </div>
                    <div class="stat-value" id="stat-selesai"><?= $stats['selesai'] ?></div>
                    <div class="stat-label">Selesai</div>
                </div>
                <div class="stat-card sc-red">
                    <div class="stat-header">
                        <div class="stat-icon-wrap si-red"><i class="fa-solid fa-ban"></i></div>
                    </div>
                    <div class="stat-value" id="stat-dibatalkan"><?= $stats['dibatalkan'] ?></div>
                    <div class="stat-label">Dibatalkan</div>
                </div>
            </div>

            <!-- INFO BOX -->
            <div
                style="background: var(--blue-lt); border: 1px solid var(--blue); border-radius: 12px; padding: 16px 20px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px;">
                <i class="fa-solid fa-circle-info" style="color: var(--blue); font-size: 20px;"></i>
                <div style="font-size: 13px; color: var(--text); line-height: 1.5;">
                    <strong>Peran Karyawan:</strong> Customer membuat booking melalui website. Karyawan hanya
                    mengkonfirmasi pembayaran yang sudah dilakukan customer.
                    <span style="color: var(--muted);">Booking baru dengan status "Menunggu" menunggu verifikasi
                        pembayaran Anda. Gunakan tombol <strong>Bukti Pembayaran</strong> untuk memeriksa bukti
                        transfer/QRIS yang diunggah customer sebelum mengkonfirmasi.</span>
                    <br><span style="color: var(--green); font-weight: 700;"><i class="fa-solid fa-robot"></i> Status
                        "Selesai" akan otomatis terupdate ketika waktu bermain sudah lewat.</span>
                </div>
            </div>

            <!-- FILTER BAR -->
            <div class="action-bar">
                <!-- Sisi Kiri: Kotak Pencarian dengan Icon & Tombol Silang (Sesuai Modul Master) -->
                <div class="search-box">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="src" placeholder="Cari customer atau lapangan... (Tekan Enter)"
                        onkeypress="handleSearch(event)" value="">
                    <button type="button" onclick="clearSearch()" class="btn-clear-search" id="btnClearSearch"
                        style="display: none;">
                        <i class="fa-solid fa-circle-xmark"></i>
                    </button>
                </div>

                <!-- Sisi Kanan: Filter Cepat & Reset -->
                <div class="action-right">
                    <div class="filter-group">
                        <form id="formFilter" onsubmit="handleFilterSubmit(event)"
                            style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                            <select name="filter_status" id="filter_status" class="filter-input"
                                onchange="handleFilterChange()">
                                <option value="all">Semua Status</option>
                                <option value="0">Menunggu Konfirmasi</option>
                                <option value="2">Selesai</option>
                                <option value="3">Dibatalkan</option>
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

            <!-- BOOKING TABLE -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fa-solid fa-list"></i> Daftar Booking</div>
                    <span style="font-size: 12px; color: var(--muted); font-weight: 600;" id="header-badge">0
                        total</span>
                </div>
                <div class="card-body" style="overflow-x: auto;">
                    <table class="data-table" id="tbl">
                        <thead>
                            <tr>
                                <th style="width: 70px; text-align: center;">No.</th>
                                <!-- Judul Customer jadi RATA KIRI -->
                                <th style="text-align: left;">Customer</th>
                                <!-- Judul Lapangan jadi RATA KIRI -->
                                <th style="text-align: left;">Lapangan & Jadwal</th>
                                <th style="text-align: right;">Tanggal Booking</th>
                                <th style="text-align: center;">Metode Bayar</th>
                                <!-- Judul Total Bayar jadi RATA TENGAH -->
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
                <div class="modal-title"><i class="fa-solid fa-file-invoice"></i> Detail Booking</div>
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
        <div class="modal" style="max-width: 520px">
            <div class="modal-header">
                <div class="modal-title"><i class="fa-solid fa-receipt"></i> Bukti Pembayaran</div>
                <button class="modal-close" onclick="closeModal('modalBukti')"><i
                        class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="modal-body" id="buktiContent" style="text-align:center"></div>
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


        // Fungsi mengubah angka menjadi format Rupiah (Rp)
        function formatRupiah(angka) {
            if (!angka) return 'Rp 0';
            return new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            }).format(angka);
        }

        // ============================================
        // GET DATA TABEL (AJAX REFRESH)
        // ============================================
        async function loadTableData() {
            const url = `booking.php?action=get_table_data&page=${currentPage}&filter_status=${currentStatus}&filter_customer=${encodeURIComponent(currentCustomer)}&filter_tanggal=${currentTanggal}`;
            try {
                const response = await fetch(url);
                const data = await response.json();
                if (data.success) {
                    // Update stats
                    document.getElementById('stat-total').textContent = data.stats.total;
                    document.getElementById('stat-menunggu').textContent = data.stats.menunggu;
                    document.getElementById('stat-berhasil').textContent = data.stats.berhasil;
                    document.getElementById('stat-selesai').textContent = data.stats.selesai;
                    document.getElementById('stat-dibatalkan').textContent = data.stats.dibatalkan;
                    document.getElementById('header-badge').textContent = `${data.stats.total} total`;

                    // Update Table & Pagination
                    document.querySelector('#tbl tbody').innerHTML = data.table;
                    document.querySelector('.pagination-wrap').innerHTML = data.pagination;

                    // Update Clear Search Button visibility
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
            currentCustomer = document.getElementById('src').value.trim(); // Sinkronkan dengan kotak pencarian terpadu
            currentPage = 1;
            loadTableData();
        }

        function handleFilterSubmit(event) {
            event.preventDefault();
            applyFilters();
        }

        // Search pemicu Enter keypress
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
        // DETAIL & MODAL HANDLER
        // ============================================
        function openModal(id) {
            document.getElementById(id).classList.add('active');
            document.body.style.overflow = 'hidden';
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

        // ============================================
        // AJAX DETAILED BOOKING
        // ============================================
        async function showDetail(id) {
            try {
                const response = await fetch('booking.php?action=get_detail&id=' + id);
                // Kita ambil responnya sebagai TEXT mentah dulu untuk menangkap jika ada ERROR DARI PHP/SQL
                const rawText = await response.text();

                let res;
                try {
                    res = JSON.parse(rawText); // Proses menjadi JSON
                } catch (parseError) {
                    console.error("HASIL KEMBALIAN SERVER RUSAK:", rawText);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error PHP / Database',
                        html: '<div style="text-align:left; font-size:12px; background:#f4f4f4; padding:10px; border-radius:5px; max-height:200px; overflow-y:auto; color:red;">' + rawText + '</div>',
                        confirmButtonText: 'Tutup'
                    });
                    return; // Hentikan fungsi
                }

                if (res.success) {
                    const booking = res.data;
                    const statusMap = {
                        0: { label: 'Menunggu Konfirmasi', class: 'sp-pending', icon: 'fa-clock' },
                        1: { label: 'Berhasil (Dikonfirmasi)', class: 'sp-active', icon: 'fa-check-circle' },
                        2: { label: 'Selesai', class: 'sp-success', icon: 'fa-flag-checkered' },
                        3: { label: 'Dibatalkan', class: 'sp-inactive', icon: 'fa-ban' }
                    };
                    const status = statusMap[booking.Status] || statusMap[0];

                    const buktiInfo = booking.Bukti_Pembayaran
                        ? `<div class="detail-item"><div class="detail-label">Bukti Pembayaran</div><div class="detail-value"><button class="btn-secondary" style="margin-top:4px" onclick="showBukti(${booking.ID_Booking}, '${booking.Bukti_Pembayaran}')"><i class="fa-solid fa-receipt"></i> Lihat Bukti</button></div></div>`
                        : `<div class="detail-item"><div class="detail-label">Bukti Pembayaran</div><div class="detail-value" style="color:var(--muted);font-weight:500">Belum diunggah customer</div></div>`;

                    const promoInfo = booking.Nama_Promo
                        ? `<div class="detail-item detail-full"><div class="detail-label">Promo Digunakan</div><div class="detail-value">${booking.Nama_Promo} (Diskon ${formatRupiah(booking.Diskon || 0)})</div></div>`
                        : '';

                    const html = `
                        <div class="detail-grid">
                            <div class="detail-item"><div class="detail-label">Status</div><div class="detail-value status"><span class="status-pill ${status.class}"><i class="fa-solid ${status.icon}"></i> ${status.label}</span></div></div>
                            <div class="detail-item"><div class="detail-label">Customer</div><div class="detail-value">${booking.Nama_Customer}</div><div style="font-size: 11px; color: var(--muted); margin-top: 2px;">${booking.Email || ''} | ${booking.No_Telepon || ''}</div></div>
                            <div class="detail-item"><div class="detail-label">Lapangan</div><div class="detail-value">${booking.Nama_Lapangan}</div></div>
                            <div class="detail-item"><div class="detail-label">Jadwal Bermain</div><div class="detail-value">${booking.TanggalFormatted}</div><div style="font-size: 11px; color: var(--muted); margin-top: 2px;">${booking.JamMulaiFormatted} - ${booking.JamSelesaiFormatted}</div></div>
                            <div class="detail-item"><div class="detail-label">Tanggal Booking</div><div class="detail-value">${booking.TanggalBookingFormatted}</div></div>
                            <div class="detail-item"><div class="detail-label">Metode Pembayaran</div><div class="detail-value">${booking.Metode_Pembayaran}</div></div>
                            <div class="detail-item"><div class="detail-label">Input Oleh</div><div class="detail-value">${booking.Nama_Karyawan_Input || 'System'}</div></div>
                            ${buktiInfo}
                            ${promoInfo}
                            <div class="detail-item detail-full"><div class="detail-label">Total Bayar</div><div class="detail-value price">${formatRupiah(booking.Total_Bayar)}</div></div>
                        </div>
                    `;

                    document.getElementById('detailContent').innerHTML = html;
                    // (Baris Swal.close sudah dibuang dari sini)
                    openModal('modalDetail'); // Munculkan pop up desain modal 
                } else {
                    showError('Gagal!', res.msg);
                }
            } catch (error) {
                console.error("Gagal Render UI JS:", error);
                showError('Error JS', error.message);
            }
        }

        /*} catch (error) {
                showError('Gagal!', 'Gagal mengambil detail booking.');
            }*/

        function showBukti(id, path) {
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
        }

        function resolveBuktiPath(path) {
            if (!path) return '';
            if (path.startsWith('http://') || path.startsWith('https://')) return path;
            if (path.startsWith('../')) return path;
            if (path.startsWith('/')) return '..' + path;
            if (path.startsWith('uploads/')) return '../' + path;
            if (path.startsWith('asset/')) return '../' + path;
            return '../asset/Bukti_Pembayaran/' + path;
        }

        // ============================================
        // ACTION BUTTONS WITH AJAX
        // ============================================
        function confirmBayar(id) {
            Swal.fire({
                title: 'Konfirmasi Pembayaran?',
                html: 'Customer sudah melakukan pembayaran?<br><span style="color: var(--muted); font-size: 12px;">Status booking akan berubah menjadi <strong>Berhasil</strong></span>',
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
                text: 'Mengkonfirmasi pembayaran booking',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });
            try {
                const response = await fetch('booking.php?action=confirm_payment&id=' + id, { method: 'POST' });
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

        function confirmBatal(id) {
            Swal.fire({
                title: 'Tolak Pembayaran?',
                html: '<span style="color: var(--red);"><strong>Pembayaran ini akan dibatalkan.</strong></span>',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#EF4444',
                cancelButtonColor: '#6B7280',
                confirmButtonText: 'Ya, Batalkan',
                cancelButtonText: 'Batal',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    executeCancelBooking(id, 'Dibatalkan oleh Karyawan');
                }
            });
        }

        async function executeCancelBooking(id, alasan) {
            Swal.fire({
                title: 'Memproses...',
                text: 'Membatalkan booking...',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });
            try {
                const formData = new FormData();
                formData.append('id_booking', id);
                formData.append('alasan_batal', alasan);
                formData.append('action', 'cancel_booking');

                const response = await fetch('booking.php', { method: 'POST', body: formData });
                const res = await response.json();
                if (res.success) {
                    showSuccess('Booking Dibatalkan!', res.msg);
                    loadTableData();
                } else {
                    showError('Gagal!', res.msg);
                }
            } catch (error) {
                showError('Gagal!', 'Terjadi kesalahan sistem.');
            }
        }

        // ============================================
        // INITIAL LOAD
        // ============================================
        document.addEventListener('DOMContentLoaded', function () {
            loadTableData();
        });
    </script>
</body>

</html>