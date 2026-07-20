<?php
session_start();
require_once '../login/auth_check.php';
$path_prefix = "../";
include '../includes/config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'karyawan') {
    echo "<script>alert('Akses Ditolak!'); window.location='../dashboard/dashboard.php';</script>";
    exit();
}

// ========================================================
// ⚠️ PANGGIL SENSOR AUTO LOGOUT IDLE (DENGAN PENGAMAN AJAX) ⚠️
// ========================================================
// Kita cek apa nilai action-nya. Jika nilainya 'auto_logout', maka ini BUKAN AJAX tabel biasa.
$action_value = $_GET['action'] ?? $_POST['action'] ?? '';
$is_real_ajax = ($action_value !== '' && $action_value !== 'auto_logout');

if (!$is_real_ajax) {
    require_once '../login/auto_logout.php';
}
// ========================================================

$role = $_SESSION['role'];
$nama = $_SESSION['nama'] ?? 'USER';
$current_page = 'tipe_member';

$profile_photo = '';
$id_karyawan_session = $_SESSION['id_karyawan'] ?? $_SESSION['id_akun'] ?? '';
if (!empty($id_karyawan_session)) {
    // Menggunakan Stored Procedure untuk mengambil foto profil Karyawan
    $stmt_photo = sqlsrv_query($conn, "EXEC sp_GetKaryawanPhoto @ID_Karyawan = ?", array($id_karyawan_session));
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
            echo "<div style='padding:20px;background:#fee;border:1px solid #fcc;border-radius:8px;font-family:sans-serif;margin:20px;'>"
                . "<h3 style='color:#c00;margin:0 0 10px;'><i class='fa-solid fa-circle-exclamation'></i> Database Error</h3>"
                . "<p style='color:#333;margin:0 0 5px;'><strong>Detail Error:</strong></p>"
                . "<pre style='background:#fff;padding:10px;border-radius:4px;overflow-x:auto;font-size:12px;'>" . htmlspecialchars($error_msg) . "</pre>"
                . "<p style='color:#666;font-size:12px;margin:10px 0 0;'>SQL: " . htmlspecialchars($sql) . "</p>"
                . "<p style='color:#666;font-size:12px;margin:5px 0 0;'>Silakan periksa koneksi database atau hubungi administrator.</p>"
                . "</div>";
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

function rupiah($n)
{
    return 'Rp ' . number_format($n, 0, ',', '.');
}

// ── PROSES AJAX REQUESTS ──
$is_ajax = $is_real_ajax;
if ($is_ajax) {
    header('Content-Type: application/json');
    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    // Action: Ambil Detail / Edit data (MENGGUNAKAN STORED PROCEDURE)
    if ($action === 'get_detail') {
        $id = intval($_GET['id'] ?? 0);
        $r = safe_sqlsrv_query($conn, "EXEC sp_GetTipeMemberDetail @ID_Tipe=?", array($id), false);
        if ($r && $row = safe_sqlsrv_fetch_array($r, SQLSRV_FETCH_ASSOC)) {
            echo json_encode([
                'success' => true,
                'data' => [
                    'ID_Tipe' => $row['ID_Tipe'],
                    'Nama_Tipe' => $row['Nama_Tipe'],
                    'Harga_Member' => (int)$row['Harga_Member'],
                    'Potongan_Harga' => (int)$row['Potongan_Harga'],
                    'Status' => (int)$row['Status']
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'msg' => 'Data tidak ditemukan atau telah dihapus.']);
        }
        exit();
    }

    // Action: Simpan Data (MENGGUNAKAN UDF & STORED PROCEDURE)
    if ($action === 'save') {
        $id = isset($_POST['id_tipe']) ? intval($_POST['id_tipe']) : 0;
        $nama_tipe = trim($_POST['nama_tipe'] ?? '');
        $harga_member_raw = $_POST['harga_member'] ?? '';
        $potongan_harga_raw = $_POST['potongan_harga'] ?? '';

        // Validasi Nama Tipe Member
        if ($nama_tipe === '') {
            echo json_encode(['success' => false, 'msg' => 'Nama tipe member wajib diisi.']); exit();
        }
        if (strlen($nama_tipe) < 3) {
            echo json_encode(['success' => false, 'msg' => 'Nama tipe member minimal 3 karakter.']); exit();
        }
        if (is_numeric($nama_tipe)) {
            echo json_encode(['success' => false, 'msg' => 'Nama tipe member tidak boleh hanya angka.']); exit();
        }

        // Validasi Harga Member
        if ($harga_member_raw === '') {
            echo json_encode(['success' => false, 'msg' => 'Harga member wajib diisi.']); exit();
        }
        if (!is_numeric($harga_member_raw)) {
            echo json_encode(['success' => false, 'msg' => 'Harga member harus berupa angka.']); exit();
        }
        $harga_member = floatval($harga_member_raw);
        if ($harga_member == 0) {
            echo json_encode(['success' => false, 'msg' => 'Harga member tidak boleh 0.']); exit();
        }
        if ($harga_member < 0) {
            echo json_encode(['success' => false, 'msg' => 'Harga member tidak boleh kurang dari 0.']); exit();
        }
        if ($harga_member < 80000) {
            echo json_encode(['success' => false, 'msg' => 'Harga member minimal 80000.']); exit();
        }

        // Validasi Potongan Harga
        if ($potongan_harga_raw === '') {
            echo json_encode(['success' => false, 'msg' => 'Potongan harga wajib diisi.']); exit();
        }
        if (!is_numeric($potongan_harga_raw)) {
            echo json_encode(['success' => false, 'msg' => 'Potongan harga harus berupa angka.']); exit();
        }
        $potongan_harga = floatval($potongan_harga_raw);
        if ($potongan_harga < 0) {
            echo json_encode(['success' => false, 'msg' => 'Potongan harga tidak boleh kurang dari 0.']); exit();
        }
        if ($potongan_harga < 50000) {
            echo json_encode(['success' => false, 'msg' => 'Potongan harga minimal 50000.']); exit();
        }
        if ($potongan_harga > $harga_member) {
            echo json_encode(['success' => false, 'msg' => 'Potongan harga tidak boleh lebih besar dari harga member.']); exit();
        }

        // Validasi duplikat nama tipe (MENGGUNAKAN USER DEFINED FUNCTION)
        $q_check_name = safe_sqlsrv_query($conn, "SELECT dbo.fn_CheckTipeMemberDuplicate(?, ?) AS is_duplicate", array($nama_tipe, $id), false);
        if ($q_check_name) {
            $row_check = safe_sqlsrv_fetch_array($q_check_name, SQLSRV_FETCH_ASSOC);
            if (($row_check['is_duplicate'] ?? 0) == 1) {
                echo json_encode(['success' => false, 'msg' => 'Nama tipe member sudah terdaftar.']); 
                exit();
            }
        }

        if (isset($_POST['edit_mode']) && $id > 0) {
            // Edit Data (MENGGUNAKAN STORED PROCEDURE)
            $stmt = safe_sqlsrv_query(
                $conn,
                "EXEC sp_UpdateTipeMember @ID_Tipe=?, @Nama_Tipe=?, @Harga_Member=?, @Potongan_Harga=?, @Modified_By=?",
                array($id, $nama_tipe, $harga_member, $potongan_harga, $nama),
                false
            );
            if ($stmt) {
                echo json_encode(['success' => true, 'msg' => 'Data tipe member berhasil diperbarui!']);
            } else {
                echo json_encode(['success' => false, 'msg' => 'Gagal memperbarui data tipe member.']);
            }
        } else {
            // Tambah Data Baru (MENGGUNAKAN STORED PROCEDURE)
            $stmt = safe_sqlsrv_query(
                $conn,
                "EXEC sp_InsertTipeMember @Nama_Tipe=?, @Harga_Member=?, @Potongan_Harga=?, @Created_By=?",
                array($nama_tipe, $harga_member, $potongan_harga, $nama),
                false
            );
            if ($stmt) {
                echo json_encode(['success' => true, 'msg' => 'Tipe member baru berhasil ditambahkan!']);
            } else {
                echo json_encode(['success' => false, 'msg' => 'Gagal menambahkan tipe member baru.']);
            }
        }
        exit();
    }

    // Action: Toggle Status (MENGGUNAKAN STORED PROCEDURE)
    if ($action === 'toggle') {
        $id = intval($_GET['id'] ?? 0);
        $current_status = intval($_GET['status'] ?? 0);
        $stmt = safe_sqlsrv_query($conn, "EXEC sp_ToggleTipeMemberStatus @ID_Tipe=?, @CurrentStatus=?", array($id, $current_status), false);
        if ($stmt) {
            echo json_encode(['success' => true, 'msg' => 'Status tipe member berhasil diubah!']);
        } else {
            echo json_encode(['success' => false, 'msg' => 'Gagal mengubah status tipe member.']);
        }
        exit();
    }

    // Action: Soft Delete Tipe Member (MENGGUNAKAN STORED PROCEDURE)
    if ($action === 'delete') {
        $id = intval($_GET['id'] ?? 0);
        $stmt = safe_sqlsrv_query($conn, "EXEC sp_DeleteTipeMember @ID_Tipe=?, @Deleted_By=?", array($id, $nama), false);
        if ($stmt) {
            echo json_encode(['success' => true, 'msg' => 'Tipe member berhasil dihapus!']);
        } else {
            echo json_encode(['success' => false, 'msg' => 'Gagal menghapus tipe member.']);
        }
        exit();
    }

    // Action: Memperbarui Data Tabel dan Pagination (MENGGUNAKAN UDF & STORED PROCEDURE)
    if ($action === 'get_table_data') {
        $search_val = isset($_GET['src']) && trim($_GET['src']) !== '' ? trim($_GET['src']) : '';
        $f_status = isset($_GET['f_status']) && $_GET['f_status'] !== '' ? intval($_GET['f_status']) : '';
        
        $search_param = ($search_val !== '') ? "%$search_val%" : null;
        $status_param = ($f_status !== '') ? $f_status : null;

        $f_sort = $_GET['f_sort'] ?? 'nama_asc';

        // Hitung Statistik Dashboard (MENGGUNAKAN USER DEFINED FUNCTION)
        $q_stats = safe_sqlsrv_query($conn, "SELECT * FROM dbo.fn_GetTipeMemberStats()", [], false);
        $active_count = 0;
        $inactive_count = 0;
        $total_data = 0;
        if ($q_stats) {
            $row_stats = safe_sqlsrv_fetch_array($q_stats, SQLSRV_FETCH_ASSOC);
            $active_count = $row_stats['ActiveCount'] ?? 0;
            $inactive_count = $row_stats['InactiveCount'] ?? 0;
        }

        // Hitung Data Filtered (MENGGUNAKAN USER DEFINED FUNCTION)
        $q_filtered = safe_sqlsrv_query($conn, "SELECT dbo.fn_GetTipeMemberFilteredCount(?, ?) AS total", array($search_param, $status_param), false);
        if ($q_filtered) {
            $row_filtered = safe_sqlsrv_fetch_array($q_filtered, SQLSRV_FETCH_ASSOC);
            $total_data = $row_filtered['total'] ?? 0;
        }

        $limit = 10;
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $total_pages = max(1, ceil($total_data / $limit));
        $page = min($page, $total_pages);
        $offset = ($page - 1) * $limit;

        // Ambil Data Berdasarkan Stored Procedure
        $query = safe_sqlsrv_query(
            $conn, 
            "EXEC sp_GetTipeMemberList @SearchVal=?, @StatusFilter=?, @SortBy=?, @Offset=?, @Limit=?", 
            array($search_param, $status_param, $f_sort, $offset, $limit), 
            false
        );

        $query_error = ($query === false);
        $query_error_msg = '';
        if ($query_error) {
            $errors = sqlsrv_errors();
            if ($errors) {
                foreach ($errors as $error) {
                    $query_error_msg .= "[" . $error['SQLSTATE'] . "] " . $error['message'] . " ";
                }
            }
        }

        // Bangun HTML Body Tabel
        ob_start();
        $has_data = false;
        $no = $offset + 1;
        if (!$query_error && $query):
            while ($row = safe_sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)):
                $has_data = true;
                $is_active = $row['Status'] == 1;
                ?>
                <tr>
                    <td class="row-num"><?= $no++ ?></td>
                    <td>
                        <div class="tipe-name"><?= htmlspecialchars($row['Nama_Tipe']) ?></div>
                    </td>
                    <td>
                        <div class="tipe-harga"><?= rupiah($row['Harga_Member']) ?></div>
                    </td>
                    <td>
                        <div class="tipe-potongan"><?= rupiah($row['Potongan_Harga']) ?></div>
                    </td>
                    <td>
                        <span class="status-pill <?= $is_active ? 'sp-active' : 'sp-inactive' ?>">
                            <span class="sp-dot"></span>
                            <?= $is_active ? 'AKTIF' : 'NONAKTIF' ?>
                        </span>
                    </td>
                    <td>
                        <div class="actions">
                            <button onclick="viewDetail(<?= $row['ID_Tipe'] ?>)" class="btn-action btn-view" title="Lihat Detail">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                            <button onclick="openEditModal(<?= $row['ID_Tipe'] ?>)" class="btn-action btn-edit" title="Edit Tipe Member">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </button>
                            <label class="toggle-switch" title="<?= $is_active ? 'Nonaktifkan' : 'Aktifkan' ?> tipe member">
                                <input type="checkbox" <?= $is_active ? 'checked' : '' ?>
                                    onchange="confirmToggle('<?= $row['ID_Tipe'] ?>', '<?= htmlspecialchars($row['Nama_Tipe'], ENT_QUOTES) ?>', <?= $row['Status'] ?>, event)">
                                <span class="toggle-slider"></span>
                            </label>
                            <button onclick="confirmDelete('<?= $row['ID_Tipe'] ?>', '<?= htmlspecialchars($row['Nama_Tipe'], ENT_QUOTES) ?>')"
                                class="btn-action btn-delete" title="Hapus Tipe Member">
                                <i class="fa-solid fa-trash-can"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            <?php endwhile; endif;

        if (!$has_data): ?>
            <tr>
                <td colspan="6">
                    <div class="empty-state">
                        <i class="fa-solid fa-star"></i>
                        <div>Belum ada data tipe member</div>
                        <div style="font-size: 12px; font-weight: 500; margin-top: 8px; opacity: .7;">
                            Tambah tipe member baru untuk memulai</div>
                    </div>
                </td>
            </tr>
        <?php endif;
        $table_html = ob_get_clean();

        // Bangun HTML Pagination
        ob_start();
        if ($total_pages > 1): ?>
            <div class="pagination-info">
                Menampilkan <strong><?= (($page - 1) * $limit) + 1 ?></strong> -
                <strong><?= min($page * $limit, $total_data) ?></strong> dari
                <strong><?= $total_data ?></strong> data
            </div>
            <div class="pagination-nav">
                <button onclick="changePage(1)" class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>" title="Halaman Pertama">
                    <i class="fa-solid fa-angles-left"></i>
                </button>
                <button onclick="changePage(<?= $page - 1 ?>)" class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>" title="Halaman Sebelumnya">
                    <i class="fa-solid fa-angle-left"></i>
                </button>

                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                if ($end_page - $start_page < 4 && $total_pages >= 5) {
                    if ($start_page == 1) {
                        $end_page = min(5, $total_pages);
                    } else {
                        $start_page = max(1, $total_pages - 4);
                    }
                }
                if ($start_page > 1):
                    ?>
                    <button onclick="changePage(1)" class="page-btn">1</button>
                    <?php if ($start_page > 2): ?><span class="page-ellipsis">...</span><?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <button onclick="changePage(<?= $i ?>)" class="page-btn <?= $i == $page ? 'active' : '' ?>"><?= $i ?></button>
                <?php endfor; ?>

                <?php if ($end_page < $total_pages): ?>
                    <?php if ($end_page < $total_pages - 1): ?><span class="page-ellipsis">...</span><?php endif; ?>
                    <button onclick="changePage(<?= $total_pages ?>)" class="page-btn"><?= $total_pages ?></button>
                <?php endif; ?>

                <button onclick="changePage(<?= $page + 1 ?>)" class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>" title="Halaman Selanjutnya">
                    <i class="fa-solid fa-angle-right"></i>
                </button>
                <button onclick="changePage(<?= $total_pages ?>)" class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>" title="Halaman Terakhir">
                    <i class="fa-solid fa-angles-right"></i>
                </button>
            </div>
        <?php else: ?>
            <div class="pagination-info">
                Menampilkan <strong>1</strong> - <strong><?= $total_data ?></strong> dari
                <strong><?= $total_data ?></strong> data
            </div>
        <?php endif;
        $pagination_html = ob_get_clean();

        echo json_encode([
            'success' => true,
            'table' => $table_html,
            'pagination' => $pagination_html,
            'stats' => [
                'active' => $active_count,
                'inactive' => $inactive_count,
                'total' => $total_data
            ],
            'error' => $query_error,
            'error_msg' => $query_error_msg
        ]);
        exit();
    }
}

// ── GET STATISTICS UNTUK AWAL PAGE LOAD (MENGGUNAKAN UDF) ──
$q_stats = safe_sqlsrv_query($conn, "SELECT * FROM dbo.fn_GetTipeMemberStats()", [], false);
$active_count = 0;
$inactive_count = 0;
$total_data = 0;
if ($q_stats) {
    $row_stats = safe_sqlsrv_fetch_array($q_stats, SQLSRV_FETCH_ASSOC);
    $active_count = $row_stats['ActiveCount'] ?? 0;
    $inactive_count = $row_stats['InactiveCount'] ?? 0;
    $total_data = $row_stats['TotalCount'] ?? 0;
}

$query_error = false;
$query_error_msg = '';

$sidebar_folder = 'master';
$sidebar_photo = $profile_photo;
$topbar_title = 'Kelola Tipe Member';
$topbar_breadcrumb = 'Operasional / Tipe Member';
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <?php include '../includes/favicon.php'; ?>
    <title>Kelola Tipe Member | HoopBall</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800;900&family=Barlow:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../asset/css/responsive_tipe_member.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        /* CSS Tambahan khusus memaksa SweetAlert2 berada di atas modal bootstrap */
        .swal2-container {
            z-index: 3000 !important;
        }

        :root {
            --orange: #FF4500;
            --orange-lt: rgba(255, 69, 0, .10);
            --orange-dk: #E03E00;
            --green: #10B981;
            --green-lt: rgba(16, 185, 129, .10);
            --green-dk: #059669;
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
            --bg: #F3F4F6;
            --card-bg: #FFFFFF;
            --border: #E5E7EB;
            --border-lt: #F3F4F6;
            --text: #111827;
            --text-md: #374151;
            --muted: #6B7280;
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
            position: relative;
            transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
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
            transition: width 0.4s cubic-bezier(0.16, 1, 0.3, 1);
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
            box-shadow: 0 4px 14px rgba(255, 69, 0, .4);
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .sb-brand:hover .sb-icon {
            transform: rotate(5deg) scale(1.1);
            box-shadow: 0 6px 20px rgba(255, 69, 0, .5);
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
            transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1);
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
            background: linear-gradient(90deg, rgba(255, 69, 0, 0.15), rgba(255, 69, 0, 0.05));
            border-radius: 10px;
            transition: width 0.35s cubic-bezier(0.16, 1, 0.3, 1);
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
            transition: all 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
            flex-shrink: 0;
            background: rgba(255, 255, 255, .04);
            position: relative;
            z-index: 1;
        }

        .sb-link:hover {
            color: #E5E7EB;
            transform: translateX(4px);
        }

        .sb-link:hover .sb-icon-wrap {
            background: rgba(255, 255, 255, .12);
            transform: scale(1.15) rotate(5deg);
        }

        .sb-link.active {
            color: #fff;
            background: var(--orange-lt);
        }

        .sb-link.active::before {
            width: 100%;
            background: linear-gradient(90deg, rgba(255, 69, 0, 0.2), rgba(255, 69, 0, 0.08));
        }

        .sb-link.active .sb-icon-wrap {
            background: var(--orange);
            color: #fff;
            transform: scale(1.1);
            box-shadow: 0 4px 12px rgba(255, 69, 0, .3);
        }

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
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
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
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            cursor: pointer;
        }

        .sb-user:hover {
            background: rgba(255, 255, 255, .08);
            border-color: rgba(255, 69, 0, .2);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, .15);
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
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .sb-user:hover .sb-avatar {
            transform: scale(1.1);
            box-shadow: 0 4px 12px rgba(255, 69, 0, .3);
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
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
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
            transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
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
            animation: sidebarSlideIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
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
            animation: menuItemFadeIn 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            opacity: 0;
        }

        .sb-brand {
            animation: menuItemFadeIn 0.5s cubic-bezier(0.16, 1, 0.3, 1) 0.1s forwards;
            opacity: 0;
        }

        .sb-section-label {
            animation: menuItemFadeIn 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
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

        .sb-section-label:nth-of-type(3)+nav .sb-link:nth-of-type(1) {
            animation-delay: 0.95s;
        }

        .sb-bottom {
            animation: menuItemFadeIn 0.5s cubic-bezier(0.16, 1, 0.3, 1) 1s forwards;
            opacity: 0;
        }

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

        .topbar-btn,
        .topbar-user {
            background-color: #FFFFFF !important;
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
            flex-shrink: 0;
        }

        .t-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
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

        .dropdown-wrap.active .dropdown-menu {
            display: block !important;
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

        /* CONTENT */
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

        /* STAT CHIPS */
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

        /* ACTION BAR */
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

        /* CARD & TABLE */
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

        .row-num {
            font-family: 'Barlow', sans-serif;
            font-weight: 800;
            color: var(--text);
            font-size: 14px;
            text-align: center;
        }

        /* TABLE COLUMN WIDTHS & ALIGNMENT */
        .data-table th:nth-child(1),
        .data-table td:nth-child(1) {
            width: 70px;
            text-align: center;
        }

        .data-table th:nth-child(2),
        .data-table td:nth-child(2) {
            text-align: left;
            padding-left: 24px;
        }

        .data-table th:nth-child(3),
        .data-table td:nth-child(3) {
            width: 180px;
            text-align: right;
            padding-right: 32px;
        }

        .data-table th:nth-child(4),
        .data-table td:nth-child(4) {
            width: 180px;
            text-align: right;
            padding-right: 32px;
        }

        .data-table th:nth-child(5),
        .data-table td:nth-child(5) {
            width: 140px;
            text-align: center;
        }

        .data-table th:nth-child(6),
        .data-table td:nth-child(6) {
            width: 180px;
            text-align: center;
        }

        .tipe-name {
            font-weight: 700;
            color: var(--text);
            font-size: 14px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 250px;
            text-align: left;
            margin: 0;
        }

        .tipe-harga {
            font-family: 'Barlow', sans-serif;
            font-weight: 700;
            font-size: 14px;
            color: var(--text);
            text-align: right;
        }

        .tipe-potongan {
            font-family: 'Barlow', sans-serif;
            font-weight: 700;
            font-size: 14px;
            color: var(--green);
            text-align: right;
        }

        /* BADGES & STATUS */
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

        /* TOGGLE SWITCH */
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

        /* MODAL */
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

        .modal-input:read-only {
            background: var(--border-lt);
            color: var(--muted);
            cursor: not-allowed;
        }

        .modal-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
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

        /* VALIDASI ERROR STYLE */
        .val-msg {
            font-size: 11px;
            color: var(--red);
            font-weight: 600;
            margin-top: -10px;
            margin-bottom: 12px;
            display: none;
            min-height: 16px;
            text-align: left;
        }

        .val-msg.show {
            display: block;
        }

        .val-msg i {
            margin-right: 4px;
        }

        .modal-input.error {
            border-color: var(--red) !important;
            box-shadow: 0 0 0 3px var(--red-lt) !important;
        }

        /* DETAIL MODAL */
        .detail-photo-card {
            text-align: center;
            margin-bottom: 24px;
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

        .info-val.price {
            font-family: 'Barlow Condensed';
            font-size: 18px;
            color: var(--orange);
            font-weight: 800;
        }

        .info-val.discount {
            font-family: 'Barlow Condensed';
            font-size: 18px;
            color: var(--green);
            font-weight: 800;
        }

        /* PAGINATION */
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

        /* EMPTY STATE */
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

        .btn-add {
            display: inline-flex !important;
            align-items: center !important;
            gap: 8px !important;
            background-color: var(--text) !important;
            color: #fff !important;
            padding: 11px 22px !important;
            border-radius: 10px !important;
            font-size: 13px !important;
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

        .btn-add i {
            font-size: 14px !important;
        }

        /* FILTER DROPDOWN */
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

        html,
        body {
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        html::-webkit-scrollbar,
        body::-webkit-scrollbar {
            display: none;
        }

        .dropdown-wrap.active .dropdown-menu {
            display: block;
        }

        .topbar-btn:hover,
        .topbar-user:hover {
            background-color: #E5E7EB !important;
            border-color: #D1D5DB !important;
            color: #4B5563 !important;
        }

        .topbar-btn:active,
        .topbar-user:active {
            background-color: #D1D5DB !important;
            border-color: #9CA3AF !important;
            color: #1F2937 !important;
        }

    
        body.swal2-shown,
        html.swal2-shown {
            padding-right: 0px !important;
            overflow-y: auto !important;
        }

        .swal2-container {
            padding-right: 0px !important;
        }

        .swal2-shown .swal2-container {
            overflow-y: auto !important;
        }

        body.swal2-height-auto {
            height: auto !important;
            padding-right: 0px !important;
        }

        html.swal2-height-auto {
            padding-right: 0px !important;
        }

        /* Tambahan CSS khusus agar tombol hapus pencarian rapi */
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

    <!-- MODAL FORM TIPE MEMBER -->
    <div class="modal-overlay" id="modalTipe">
        <div class="modal-box">
            <button class="modal-close" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></button>
            <div class="modal-header">
                <div class="modal-subtitle">Kelola Tipe Member</div>
                <div class="modal-title" id="form-modal-title">Tambah Tipe Member Baru</div>
            </div>
            <div class="modal-body">
                <form id="formTipe" onsubmit="handleFormSubmit(event)" novalidate>
                    <input type="hidden" name="id_tipe" id="id_tipe" value="">
                    <div id="additional-form-inputs"></div>

                    <label class="modal-label">Nama Tipe <span class="required">*</span></label>
                    <input type="text" name="nama_tipe" id="nama_tipe" class="modal-input"
                        placeholder="Masukkan nama tipe (misal: Silver, Gold, Platinum)" autocomplete="off"
                        value="" required minlength="3" maxlength="10">
                    <div class="val-msg" id="val-nama_tipe"></div>

                    <div class="modal-grid-2">
                        <div>
                            <label class="modal-label">Harga Member (Rp) <span class="required">*</span></label>
                            <input type="number" name="harga_member" id="harga_member" class="modal-input"
                                placeholder="Contoh: 100000" min="0" step="1000" autocomplete="off"
                                value="" required>
                            <div class="val-msg" id="val-harga_member"></div>
                        </div>
                        <div>
                            <label class="modal-label">Potongan Harga (Rp) <span class="required">*</span></label>
                            <input type="number" name="potongan_harga" id="potongan_harga" class="modal-input"
                                placeholder="Contoh: 50000" min="0" step="1000" autocomplete="off"
                                value="" required>
                            <div class="val-msg" id="val-potongan_harga"></div>
                        </div>
                    </div>

                    <button type="submit" class="btn-submit" id="btn-submit-form">
                        <i class="fa-solid fa-plus"></i> Tambah Tipe Member
                    </button>
                    <a onclick="closeModal()" class="btn-cancel">Batal</a>
                </form>
            </div>
        </div>
    </div>

    <!-- SIDEBAR -->
    <?php include '../includes/sidebar.php'; ?>

    <!-- MAIN & TOPBAR -->
    <main class="main">
        <?php include '../includes/topbar.php'; ?>

        <div class="content">
            <!-- PAGE HEADER -->
            <div class="page-header">
                <div>
                    <div class="page-title-tag"></div>
                    <div class="page-title">Kelola Tipe Member</div>
                </div>
                <div class="stat-chips">
                    <div class="stat-chip chip-green"><i class="fa-solid fa-circle-check"></i> AKTIF <span
                            class="chip-val" id="stat-aktif"><?= $active_count ?></span></div>
                    <div class="stat-chip chip-red"><i class="fa-solid fa-circle-xmark"></i> NONAKTIF <span
                            class="chip-val" id="stat-nonaktif"><?= $inactive_count ?></span></div>
                    <div class="stat-chip chip-blue"><i class="fa-solid fa-list"></i> TOTAL <span
                            class="chip-val" id="stat-total"><?= $total_data ?></span></div>
                </div>
            </div>

            <!-- ACTION BAR -->
            <div class="action-bar">
                <div class="search-box">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="src" placeholder="Cari tipe member... (Tekan Enter)" onkeypress="handleSearch(event)" value="">
                    <button type="button" onclick="clearSearch()" class="btn-clear-search" id="btnClearSearch" style="display: none;">
                        <i class="fa-solid fa-circle-xmark"></i>
                    </button>
                </div>

                <div style="display: flex; gap: 12px; align-items: center;">
                    <div class="filter-dropdown-wrap">
                        <button type="button" class="btn-filter" id="btnFilterToggleCustom" onclick="toggleCustomFilterCard(event)">
                            <i class="fa-solid fa-filter"></i> Filter <i
                                class="fa-solid fa-chevron-down arrow-icon"></i>
                        </button>

                        <div class="filter-card" id="filterCardCustom" onclick="event.stopPropagation()">
                            <h4>Filter Data</h4>
                            <form id="formFilter" onsubmit="handleFilterSubmit(event)">
                                <div class="filter-group">
                                    <label>Urut Berdasarkan</label>
                                    <select name="f_sort" class="filter-input">
                                        <option value="nama_asc">Nama A - Z</option>
                                        <option value="harga_desc">Harga Tertinggi</option>
                                    </select>
                                </div>

                                <div class="filter-group">
                                    <label>Status Tipe Member</label>
                                    <select name="f_status" class="filter-input">
                                        <option value="">Semua Status</option>
                                        <option value="1">AKTIF</option>
                                        <option value="0">NONAKTIF</option>
                                    </select>
                                </div>

                                <div class="filter-buttons">
                                    <button type="button" class="btn-filter-reset" onclick="resetFilter()"><i
                                            class="fa-solid fa-rotate-left"></i> Reset</button>
                                    <button type="submit" class="btn-filter-apply"><i class="fa-solid fa-check"></i>
                                        Terapkan</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <button onclick="openAddModal()" class="btn-add"><i class="fa-solid fa-plus"></i>Tambah</button>
                </div>
            </div>

            <!-- TABLE CARD -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fa-solid fa-star"></i> Data Tipe Member</div>
                    <span class="card-badge" id="header-badge"><?= $total_data ?> total</span>
                </div>
                <?php if ($query_error): ?>
                    <div id="db-error-banner" style="padding:20px;background:#fee;border:1px solid #fcc;border-radius:8px;margin:20px 0;">
                        <p style="color:#c00;font-weight:bold;margin:0;"><i class="fa-solid fa-circle-exclamation"></i>
                            Gagal mengambil data dari database. Silakan refresh halaman atau hubungi administrator.</p>
                        <p style="color:#666;font-size:11px;margin:5px 0 0;">Error:
                            <?php echo htmlspecialchars($query_error_msg); ?></p>
                    </div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="data-table" id="tbl">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama Tipe</th>
                                    <th>Harga Member</th>
                                    <th>Potongan Harga</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Dinamis diisi lewat AJAX Javascript -->
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- PAGINATION -->
            <div class="pagination-wrap">
                <!-- Dinamis diisi lewat AJAX Javascript -->
            </div>
        </div>
    </main>

    <!-- MODAL DETAIL TIPE MEMBER -->
    <div class="modal-overlay" id="modalDetail">
        <div class="modal-box" style="width: 440px;">
            <button class="modal-close" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></button>
            <div class="modal-header" style="border-bottom: none; padding-bottom: 0;">
                <div class="modal-subtitle">Informasi Tipe Member</div>
                <div class="modal-title">Profil Tipe Member</div>
            </div>
            <div class="modal-body" id="detail-modal-body" style="padding-top: 10px;">
                <!-- Dinamis diisi lewat AJAX Javascript -->
            </div>
        </div>
    </div>

    <script src="../asset/js/global.js"></script>
    <script>
        // State management untuk Filter & Pagination
        let currentPage = 1;
        let currentSort = 'nama_asc';
        let currentStatus = '';
        let currentSearch = '';

        // ============================================
        // GET DATA TABEL (AJAX REFRESH)
        // ============================================
        async function loadTableData() {
            const url = `tipe_member.php?action=get_table_data&page=${currentPage}&f_sort=${currentSort}&f_status=${currentStatus}&src=${encodeURIComponent(currentSearch)}`;
            try {
                const response = await fetch(url);
                const data = await response.json();
                if (data.success) {
                    // Update stats
                    document.getElementById('stat-aktif').textContent = data.stats.active;
                    document.getElementById('stat-nonaktif').textContent = data.stats.inactive;
                    document.getElementById('stat-total').textContent = data.stats.total;
                    document.getElementById('header-badge').textContent = `${data.stats.total} total`;

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
        function closeModal() {
            document.getElementById('modalTipe').classList.remove('open');
            document.getElementById('modalDetail').classList.remove('open');
        }

        function openAddModal() {
            document.getElementById('formTipe').reset();
            document.getElementById('id_tipe').value = '';
            document.getElementById('additional-form-inputs').innerHTML = '';
            
            // Bersihkan error validasi sebelumnya
            document.querySelectorAll('.val-msg').forEach(el => el.classList.remove('show'));
            document.querySelectorAll('.modal-input').forEach(el => el.classList.remove('error'));

            // Konfigurasi Title & Button Modal
            document.getElementById('form-modal-title').textContent = 'Tambah Tipe Member Baru';
            document.getElementById('btn-submit-form').innerHTML = '<i class="fa-solid fa-plus"></i> Tambah Tipe Member';

            document.getElementById('modalTipe').classList.add('open');
        }

        async function openEditModal(id) {
            // Bersihkan error validasi sebelumnya
            document.querySelectorAll('.val-msg').forEach(el => el.classList.remove('show'));
            document.querySelectorAll('.modal-input').forEach(el => el.classList.remove('error'));

            try {
                const response = await fetch(`tipe_member.php?action=get_detail&id=${id}`);
                const res = await response.json();
                if (res.success) {
                    const data = res.data;
                    document.getElementById('id_tipe').value = data.ID_Tipe;
                    document.getElementById('nama_tipe').value = data.Nama_Tipe;
                    document.getElementById('harga_member').value = data.Harga_Member;
                    document.getElementById('potongan_harga').value = data.Potongan_Harga;

                    document.getElementById('additional-form-inputs').innerHTML = `
                        <input type="hidden" name="edit_mode" value="1">
                    `;

                    // Konfigurasi Title & Button Modal
                    document.getElementById('form-modal-title').textContent = 'Edit Tipe Member';
                    document.getElementById('btn-submit-form').innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Simpan Perubahan';

                    document.getElementById('modalTipe').classList.add('open');
                } else {
                    showError('Gagal!', res.msg);
                }
            } catch (error) {
                showError('Gagal!', 'Terjadi kesalahan saat mengambil data.');
            }
        }

        async function viewDetail(id) {
            try {
                const response = await fetch(`tipe_member.php?action=get_detail&id=${id}`);
                const res = await response.json();
                if (res.success) {
                    const data = res.data;
                    const formatRupiah = (val) => 'Rp ' + new Intl.NumberFormat('id-ID').format(val);
                    const isActive = data.Status === 1;

                    const statusPill = isActive 
                        ? `<span class="status-pill sp-active"><span class="sp-dot"></span>AKTIF</span>`
                        : `<span class="status-pill sp-inactive"><span class="sp-dot"></span>NONAKTIF</span>`;

                    document.getElementById('detail-modal-body').innerHTML = `
                        <div class="detail-photo-card">
                            <div class="detail-icon-wrap"><i class="fa-solid fa-star"></i></div>
                            <div class="detail-main-name">${data.Nama_Tipe}</div>
                        </div>
                        <div class="info-row">
                            <span class="info-key"><i class="fa-solid fa-star"></i> Nama Tipe</span>
                            <span class="info-val" style="font-weight:700;">${data.Nama_Tipe}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-key"><i class="fa-solid fa-money-bill-wave"></i> Harga Member</span>
                            <span class="info-val price">${formatRupiah(data.Harga_Member)}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-key"><i class="fa-solid fa-tags"></i> Potongan Harga</span>
                            <span class="info-val discount">${formatRupiah(data.Potongan_Harga)}</span>
                        </div>
                        <div class="info-row" style="border-bottom:none;">
                            <span class="info-key"><i class="fa-solid fa-circle-check"></i> Status</span>
                            <span class="info-val">${statusPill}</span>
                        </div>
                        <button onclick="closeModal()" class="btn-submit" style="margin-top: 24px; background: #0D1117;">
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
        // AJAX SUBMIT FORM (TAMBAH / EDIT)
        // ============================================
        async function handleFormSubmit(event) {
            event.preventDefault();
            if (!validateForm()) return;

            const form = document.getElementById('formTipe');
            const formData = new FormData(form);
            formData.append('action', 'save');

            Swal.fire({
                title: 'Memproses...',
                text: 'Menyimpan data tipe member',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });

            try {
                const response = await fetch('tipe_member.php', {
                    method: 'POST',
                    body: formData
                });
                const res = await response.json();
                if (res.success) {
                    closeModal();
                    showSuccess('Berhasil!', res.msg);
                    loadTableData();
                } else {
                    showError('Gagal!', res.msg);
                }
            } catch (error) {
                showError('Gagal!', 'Gagal memproses data.');
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

        function handleFilterSubmit(event) {
            event.preventDefault();
            const form = event.target;
            currentSort = form.elements['f_sort'].value;
            currentStatus = form.elements['f_status'].value;
            currentPage = 1;
            loadTableData();
            
            // Tutup filter dropdown
            document.getElementById('btnFilterToggleCustom').classList.remove('active');
            document.getElementById('filterCardCustom').classList.remove('open');
        }

        function resetFilter() {
            document.getElementById('formFilter').reset();
            currentSort = 'nama_asc';
            currentStatus = '';
            currentSearch = '';
            document.getElementById('src').value = '';
            currentPage = 1;
            loadTableData();

            document.getElementById('btnFilterToggleCustom').classList.remove('active');
            document.getElementById('filterCardCustom').classList.remove('open');
        }

        // ============================================
        // TOGGLE STATUS (MENGGUNAKAN SP)
        // ============================================
        async function confirmToggle(id, name, currentStatus, event) {
            const checkbox = event.target;
            const newStatus = currentStatus === 1 ? 0 : 1;
            const statusText = newStatus === 1 ? 'Aktif' : 'Nonaktif';
            const icon = newStatus === 1 ? 'success' : 'warning';
            const confirmColor = newStatus === 1 ? '#10B981' : '#EF4444';

            const result = await Swal.fire({
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
            });

            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Memproses...',
                    text: 'Mengubah status tipe member',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
                });

                try {
                    const response = await fetch(`tipe_member.php?action=toggle&id=${id}&status=${currentStatus}`);
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
        // DELETE CONFIRMATION (MENGGUNAKAN SP)
        // ============================================
        async function confirmDelete(id, name) {
            const result = await Swal.fire({
                title: 'Hapus Tipe Member?',
                html: 'Anda akan menghapus tipe member <strong style="color:var(--orange);">' + name + '</strong><br><span style="font-size:12px;color:var(--muted);">Data akan dihapus secara Permanen</span>',
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
                    text: 'Menghapus tipe member',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
                });

                try {
                    const response = await fetch(`tipe_member.php?action=delete&id=${id}`);
                    const res = await response.json();
                    if (res.success) {
                        showSuccess('Terhapus!', res.msg);
                        loadTableData();
                    } else {
                        showError('Gagal!', res.msg);
                    }
                } catch (error) {
                    showError('Gagal!', 'Terjadi kesalahan saat menghapus data.');
                }
            }
        }

        // ============================================
        // FORM FIELD VALIDATION
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

            if (fieldId === 'nama_tipe') {
                if (value.length < 3) {
                    field.classList.add('error');
                    valMsg.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Nama tipe member minimal 3 karakter.';
                    valMsg.classList.add('show');
                    return false;
                }
                if (/^\d+$/.test(value)) {
                    field.classList.add('error');
                    valMsg.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Nama tipe member tidak boleh hanya angka.';
                    valMsg.classList.add('show');
                    return false;
                }
            }

            if (rules.isNumeric) {
                if (isNaN(value) || value === '') {
                    field.classList.add('error');
                    valMsg.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> ' + rules.label + ' harus berupa angka.';
                    valMsg.classList.add('show');
                    return false;
                }
                const numVal = parseFloat(value);

                if (rules.notZero && numVal === 0) {
                    field.classList.add('error');
                    valMsg.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> ' + rules.label + ' tidak boleh 0.';
                    valMsg.classList.add('show');
                    return false;
                }

                if (rules.minVal !== undefined && numVal < rules.minVal) {
                    field.classList.add('error');
                    if (fieldId === 'harga_member' && rules.minVal === 80000) {
                        valMsg.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Harga member minimal 80000.';
                    } else if (fieldId === 'potongan_harga' && rules.minVal === 50000) {
                        valMsg.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Potongan harga minimal 50000.';
                    } else {
                        valMsg.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> ' + rules.label + ' tidak boleh kurang dari ' + rules.minVal + '.';
                    }
                    valMsg.classList.add('show');
                    return false;
                }

                if (fieldId === 'potongan_harga') {
                    const hargaValRaw = document.getElementById('harga_member').value.trim();
                    const hargaVal = parseFloat(hargaValRaw);
                    if (!isNaN(hargaVal) && numVal > hargaVal) {
                        field.classList.add('error');
                        valMsg.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Potongan harga tidak boleh lebih besar dari harga member.';
                        valMsg.classList.add('show');
                        return false;
                    }
                }
            }
            return true;
        }

        function validateForm() {
            let valid = true;
            if (!validateField('nama_tipe', 'val-nama_tipe', { required: true, label: 'Nama tipe member' })) valid = false;
            if (!validateField('harga_member', 'val-harga_member', {
                required: true,
                isNumeric: true,
                notZero: true,
                minVal: 80000,
                label: 'Harga member'
            })) valid = false;
            if (!validateField('potongan_harga', 'val-potongan_harga', {
                required: true,
                isNumeric: true,
                minVal: 50000,
                label: 'Potongan harga'
            })) valid = false;
            return valid;
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
        // INITIAL LOAD & REAL-TIME VALIDATIONS
        // ============================================
        document.addEventListener('DOMContentLoaded', function () {
            // Jalankan load data tabel utama
            loadTableData();

            // Real-time validations
            const namaTipe = document.getElementById('nama_tipe');
            if (namaTipe) {
                namaTipe.addEventListener('blur', function () {
                    validateField('nama_tipe', 'val-nama_tipe', { required: true, label: 'Nama tipe member' });
                });
                namaTipe.addEventListener('input', function () {
                    if (this.classList.contains('error')) {
                        validateField('nama_tipe', 'val-nama_tipe', { required: true, label: 'Nama tipe member' });
                    }
                });
            }

            const hargaMember = document.getElementById('harga_member');
            if (hargaMember) {
                hargaMember.addEventListener('blur', function () {
                    validateField('harga_member', 'val-harga_member', {
                        required: true,
                        isNumeric: true,
                        notZero: true,
                        minVal: 80000,
                        label: 'Harga member'
                    });
                });
                hargaMember.addEventListener('input', function () {
                    if (this.classList.contains('error')) {
                        validateField('harga_member', 'val-harga_member', {
                            required: true,
                            isNumeric: true,
                            notZero: true,
                            minVal: 80000,
                            label: 'Harga member'
                        });
                    }
                });
            }

            const potonganHarga = document.getElementById('potongan_harga');
            if (potonganHarga) {
                potonganHarga.addEventListener('blur', function () {
                    validateField('potongan_harga', 'val-potongan_harga', {
                        required: true,
                        isNumeric: true,
                        minVal: 50000,
                        label: 'Potongan harga'
                    });
                });
                potonganHarga.addEventListener('input', function () {
                    if (this.classList.contains('error')) {
                        validateField('potongan_harga', 'val-potongan_harga', {
                            required: true,
                            isNumeric: true,
                            minVal: 50000,
                            label: 'Potongan harga'
                        });
                    }
                });
            }
        });
    </script>
    <!-- Panggil sensor di paling bawah sebelum body ditutup -->
    <?php if (function_exists('tampilkan_sensor_auto_logout')) tampilkan_sensor_auto_logout(); ?>
</body>

</html>