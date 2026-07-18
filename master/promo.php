<?php
session_start();
$path_prefix = "../";
include '../includes/config.php';

if (
    !isset($_SESSION['role']) ||
    !in_array(strtolower(trim($_SESSION['role'])), ['karyawan', 'pemilik', '1', '2', 1, 2], true)
) {
    echo "<script>alert('Akses Ditolak!'); window.location='../dashboard/dashboard.php';</script>";
    exit();
}
$role = $_SESSION['role'];
$nama = $_SESSION['nama'] ?? 'USER';
$current_page = 'promo';

// DEFINISIKAN $profile_photo
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

// ============================================================
// HELPER: Safe SQLSRV Operations
// ============================================================
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

function safe_sqlsrv_fetch_array($stmt, $fetch_type = SQLSRV_FETCH_ASSOC)
{
    if ($stmt === false || $stmt === null) {
        return false;
    }
    return sqlsrv_fetch_array($stmt, $fetch_type);
}

function safe_sqlsrv_has_rows($stmt)
{
    if ($stmt === false || $stmt === null) {
        return false;
    }
    return sqlsrv_has_rows($stmt);
}

function rupiah($n)
{
    return 'Rp ' . number_format($n, 0, ',', '.');
}

// ── PROSES AJAX REQUESTS ──
$is_ajax = isset($_GET['action']) || isset($_POST['action']);
if ($is_ajax) {
    header('Content-Type: application/json');
    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    // Action: Ambil Detail / Edit data (AJAX)
    if ($action === 'get_detail') {
        $id = intval($_GET['id'] ?? 0);
        $r = safe_sqlsrv_query($conn, "EXEC sp_Promo_GetById @ID_Promo=?", array($id), false);
        if ($r && $row = safe_sqlsrv_fetch_array($r, SQLSRV_FETCH_ASSOC)) {
            $tgl_m_formatted = ($row['Tanggal_Mulai'] instanceof DateTime) ? $row['Tanggal_Mulai']->format('Y-m-d') : date('Y-m-d', strtotime($row['Tanggal_Mulai']));
            $tgl_s_formatted = ($row['Tanggal_Selesai'] instanceof DateTime) ? $row['Tanggal_Selesai']->format('Y-m-d') : date('Y-m-d', strtotime($row['Tanggal_Selesai']));

            echo json_encode([
                'success' => true,
                'data' => [
                    'ID_Promo' => $row['ID_Promo'],
                    'Nama_Promo' => $row['Nama_Promo'],
                    'Diskon' => (int)$row['Diskon'],
                    'Tanggal_Mulai' => $tgl_m_formatted,
                    'Tanggal_Selesai' => $tgl_s_formatted,
                    'StatusText' => $row['StatusText'] ?? '',
                    'DiskonFormatted' => $row['DiskonFormatted'] ?? '',
                    'TanggalMulaiFormatted' => $row['TanggalMulaiFormatted'] ?? '',
                    'TanggalSelesaiFormatted' => $row['TanggalSelesaiFormatted'] ?? ''
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'msg' => 'Data promo tidak ditemukan atau telah dihapus.']);
        }
        exit();
    }

    // Action: Simpan Data (Tambah & Edit AJAX)
    if ($action === 'save') {
        $id = isset($_POST['id_prm']) ? intval($_POST['id_prm']) : 0;
        $nama_promo = trim($_POST['nama_promo'] ?? '');
        $diskon = floatval($_POST['diskon'] ?? 0);
        $tgl_m = $_POST['tgl_m'] ?? '';
        $tgl_s = $_POST['tgl_s'] ?? '';

        // Validasi Diskon: 1-100%
        if ($diskon <= 0) {
            echo json_encode(['success' => false, 'msg' => 'Diskon tidak boleh 0 atau kurang dari 0!']); exit();
        }
        if ($diskon > 100) {
            echo json_encode(['success' => false, 'msg' => 'Diskon maksimal 100%!']); exit();
        }

        // Validasi Tanggal Mulai
        $hari_ini = date('Y-m-d');
        if ($tgl_m < $hari_ini) {
            echo json_encode(['success' => false, 'msg' => 'Tanggal mulai tidak boleh kurang dari hari ini!']); exit();
        }

        if (strtotime($tgl_s) < strtotime($tgl_m)) {
            echo json_encode(['success' => false, 'msg' => 'Tanggal selesai tidak boleh mendahului tanggal mulai!']); exit();
        }

        // CEK NAMA DUPLIKAT menggunakan UDF
        if (isset($_POST['edit_mode']) && $id > 0) {
            // UPDATE menggunakan Stored Procedure
            $stmt = safe_sqlsrv_query(
                $conn,
                "EXEC sp_Promo_Update @ID_Promo=?, @Nama_Promo=?, @Diskon=?, @Tanggal_Mulai=?, @Tanggal_Selesai=?, @Modified_By=?",
                array($id, $nama_promo, $diskon, $tgl_m, $tgl_s, $nama),
                false
            );

            if ($stmt) {
                echo json_encode(['success' => true, 'msg' => 'Data promo berhasil diperbarui!']);
            } else {
                $errors = sqlsrv_errors();
                $err_msg = "Gagal memperbarui data promo";
                if ($errors) { 
                    $err_msg = $errors[0]['message']; 
                    // Membersihkan info driver [Microsoft][ODBC...] di awal pesan
                    $err_msg = preg_replace('/^(\[[^\]]+\])+/', '', $err_msg);
                    $err_msg = trim($err_msg);
                }
                echo json_encode(['success' => false, 'msg' => $err_msg]);
            }
        } else {
            // INSERT menggunakan Stored Procedure
            $stmt = safe_sqlsrv_query(
                $conn,
                "EXEC sp_Promo_Insert @Nama_Promo=?, @Diskon=?, @Tanggal_Mulai=?, @Tanggal_Selesai=?, @Created_By=?",
                array($nama_promo, $diskon, $tgl_m, $tgl_s, $nama),
                false
            );

            if ($stmt) {
                echo json_encode(['success' => true, 'msg' => 'Promo baru berhasil ditambahkan!']);
            } else {
                $errors = sqlsrv_errors();
                $err_msg = "Gagal menambahkan promo";
                if ($errors) { 
                    $err_msg = $errors[0]['message']; 
                    // Membersihkan info driver [Microsoft][ODBC...] di awal pesan
                    $err_msg = preg_replace('/^(\[[^\]]+\])+/', '', $err_msg);
                    $err_msg = trim($err_msg);
                }
                echo json_encode(['success' => false, 'msg' => $err_msg]);
            }
        }
        exit();
    }

    // Action: Toggle Status (AJAX)
    if ($action === 'toggle') {
        $id = intval($_GET['id'] ?? 0);
        $current_status = intval($_GET['status'] ?? 0);
        $s_baru = ($current_status == 1) ? 0 : 1;

        $stmt = safe_sqlsrv_query(
            $conn,
            "EXEC sp_Promo_ToggleStatus @ID_Promo=?, @New_Status=?, @Modified_By=?",
            array($id, $s_baru, $nama),
            false
        );

        if ($stmt) {
            echo json_encode(['success' => true, 'msg' => 'Status promo berhasil diubah!']);
        } else {
            echo json_encode(['success' => false, 'msg' => 'Gagal mengubah status promo!']);
        }
        exit();
    }

    // Action: Hapus Promo (AJAX)
    if ($action === 'delete') {
        $id = intval($_GET['id'] ?? 0);
        $stmt = safe_sqlsrv_query(
            $conn,
            "EXEC sp_Promo_Delete @ID_Promo=?, @Deleted_By=?",
            array($id, $nama),
            false
        );

        if ($stmt) {
            echo json_encode(['success' => true, 'msg' => 'Promo berhasil dihapus!']);
        } else {
            $errors = sqlsrv_errors();
            $err_msg = "Gagal hapus, data masih terikat!";
            if ($errors) { $err_msg = $errors[0]['message']; }
            echo json_encode(['success' => false, 'msg' => $err_msg]);
        }
        exit();
    }

    // Action: Ambil Data Tabel, Pagination & Statistik (Dynamic AJAX Refresh)
    if ($action === 'get_table_data') {
        $filter_status = isset($_GET['f_status']) && $_GET['f_status'] !== '' ? $_GET['f_status'] : null;
        $f_sort = $_GET['f_sort'] ?? 'nama_asc';
        $search_val = isset($_GET['src']) ? trim($_GET['src']) : '';
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

        // Ambil Statistik Terkini menggunakan UDF via SP
        $q_stats = safe_sqlsrv_query($conn, "EXEC sp_Promo_GetStats", [], false);
        $active_count = 0;
        $expired_count = 0;
        $total_data = 0;
        if ($q_stats) {
            $row_stats = safe_sqlsrv_fetch_array($q_stats, SQLSRV_FETCH_ASSOC);
            $active_count = $row_stats['ActiveCount'] ?? 0;
            $inactive_count = $row_stats['ExpiredCount'] ?? 0;
            $total_data = $row_stats['TotalCount'] ?? 0;
        }

        // Ambil Seluruh Data Berdasarkan Status & Sort untuk difilter pencariannya secara dinamis di PHP
        $query_all = safe_sqlsrv_query(
            $conn,
            $filter_status !== null
            ? "EXEC sp_Promo_GetAll @Page=1, @Limit=100000, @Filter_Status=?, @Sort_By=?"
            : "EXEC sp_Promo_GetAll @Page=1, @Limit=100000, @Sort_By=?",
            $filter_status !== null ? array($filter_status, $f_sort) : array($f_sort),
            false
        );

        $all_records = [];
        if ($query_all) {
            while ($row = safe_sqlsrv_fetch_array($query_all, SQLSRV_FETCH_ASSOC)) {
                // Saring data berdasarkan kata kunci pencarian di PHP (Server-Side Search)
                if ($search_val !== '') {
                    if (strpos(strtolower($row['Nama_Promo']), strtolower($search_val)) === false) {
                        continue;
                    }
                }
                $all_records[] = $row;
            }
        }

        // ============================================
        // FALLBACK SORTING DI SISI PHP (Mengantisipasi Jika SP Belum diupdate)
        // ============================================
        usort($all_records, function($a, $b) use ($f_sort) {
            if ($f_sort === 'nama_asc') {
                return strcasecmp($a['Nama_Promo'], $b['Nama_Promo']);
            } elseif ($f_sort === 'nama_desc') {
                return strcasecmp($b['Nama_Promo'], $a['Nama_Promo']);
            } elseif ($f_sort === 'diskon_desc') {
                return floatval($b['Diskon']) <=> floatval($a['Diskon']);
            } elseif ($f_sort === 'diskon_asc') {
                return floatval($a['Diskon']) <=> floatval($b['Diskon']);
            }
            return 0;
        });

        $total_data_filtered = count($all_records);
        $limit = 10;
        $total_pages = max(1, ceil($total_data_filtered / $limit));
        $page = min($page, $total_pages);
        $offset = ($page - 1) * $limit;

        // Potong array sesuai dengan offset pagination saat ini
        $paginated_records = array_slice($all_records, $offset, $limit);

        // Render HTML Tabel Body
        ob_start();
        $has_data = false;
        $no = $offset + 1;
        
        foreach ($paginated_records as $row):
            $has_data = true;
            $is_active = ($row['StatusText'] === 'AKTIF');
            ?>
            <tr>
                <td class="col-center" style="font-family:'Barlow'; font-weight:700;"><?= $no++ ?></td>
                <td class="col-left">
                    <div class="promo-name"><?= htmlspecialchars($row['Nama_Promo']) ?></div>
                </td>
                <td class="col-center">
                    <div class="promo-disc"><?= htmlspecialchars($row['DiskonFormatted']) ?></div>
                </td>
                <td class="col-center">
                    <span class="status-pill <?= $is_active ? 'sp-active' : 'sp-inactive' ?>">
                        <span class="sp-dot"></span>
                        <?= $is_active ? 'AKTIF' : 'KADALUARSA' ?>
                    </span>
                </td>
                <td class="col-center">
                    <div class="actions">
                        <button type="button" onclick="viewDetail(<?= $row['ID_Promo'] ?>)" class="btn-action btn-view" title="Lihat Detail">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                        <button type="button" onclick="openEditModal(<?= $row['ID_Promo'] ?>)" class="btn-action btn-edit" title="Edit Promo">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                        <label class="toggle-switch" title="<?= $is_active ? 'Nonaktifkan' : 'Aktifkan' ?> promo">
                            <input type="checkbox" <?= $is_active ? 'checked' : '' ?>
                                onchange="confirmToggle('<?= $row['ID_Promo'] ?>', '<?= htmlspecialchars($row['Nama_Promo'], ENT_QUOTES) ?>', <?= $is_active ? 1 : 0 ?>, event)">
                            <span class="toggle-slider"></span>
                        </label>
                        <button type="button" onclick="confirmDelete('<?= $row['ID_Promo'] ?>', '<?= htmlspecialchars($row['Nama_Promo'], ENT_QUOTES) ?>')" class="btn-action btn-delete" title="Hapus Promo">
                            <i class="fa-solid fa-trash-can"></i>
                        </button>
                    </div>
                </td>
            </tr>
        <?php endforeach;

        if (!$has_data): ?>
            <tr>
                <td colspan="5">
                    <div class="empty-state">
                        <i class="fa-solid fa-tag"></i>
                        <div>Belum ada data promo</div>
                        <div style="font-size: 12px; font-weight: 500; margin-top: 8px; opacity: .7;">
                            Tambah promo baru untuk memulai</div>
                    </div>
                </td>
            </tr>
        <?php endif;
        $table_html = ob_get_clean();

        // Render HTML Pagination
        ob_start();
        if ($total_pages > 1): ?>
            <div class="pagination-info">
                Menampilkan <strong><?= (($page - 1) * $limit) + 1 ?></strong> -
                <strong><?= min($page * $limit, $total_data_filtered) ?></strong> dari
                <strong><?= $total_data_filtered ?></strong> data
            </div>
            <div class="pagination-nav">
                <button onclick="changePage(1)" class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>" title="Halaman Pertama">
                    <i class="fa-solid fa-angles-left"></i>
                </button>
                <button onclick="changePage(<?= $page - 1 ?>)" class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>" title="Halaman Sebelumnya">
                    <i class="fa-solid fa-angle-left"></i>
                </button>
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <button onclick="changePage(<?= $i ?>)" class="page-btn <?= $i == $page ? 'active' : '' ?>"><?= $i ?></button>
                <?php endfor; ?>
                <button onclick="changePage(<?= $page + 1 ?>)" class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>" title="Halaman Selanjutnya">
                    <i class="fa-solid fa-angle-right"></i>
                </button>
                <button onclick="changePage(<?= $total_pages ?>)" class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>" title="Halaman Terakhir">
                    <i class="fa-solid fa-angles-right"></i>
                </button>
            </div>
        <?php else: ?>
            <div class="pagination-info">
                Menampilkan <strong>1</strong> - <strong><?= $total_data_filtered ?></strong> dari
                <strong><?= $total_data_filtered ?></strong> data
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
                'total' => $total_data,
                'total_filtered' => $total_data_filtered
            ]
        ]);
        exit();
    }
}

// ── GET STATISTICS UNTUK AWAL PAGE LOAD ──
$q_stats = safe_sqlsrv_query($conn, "EXEC sp_Promo_GetStats", [], false);
$active_count = 0;
$expired_count = 0;
$total_data = 0;
if ($q_stats) {
    $row_stats = safe_sqlsrv_fetch_array($q_stats, SQLSRV_FETCH_ASSOC);
    $active_count = $row_stats['ActiveCount'] ?? 0;
    $expired_count = $row_stats['ExpiredCount'] ?? 0;
    $total_data = $row_stats['TotalCount'] ?? 0;
}

$query_error = false;
$query_error_msg = '';

$sidebar_folder = 'master';
$sidebar_photo = $profile_photo;
$topbar_title = 'Kelola Promo';
$topbar_breadcrumb = 'Operasional / Promo';
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <?php include '../includes/favicon.php'; ?>
    <title>Kelola Promo | HoopBall</title>
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

        .main,
        .content {
            background-color: #F3F4F6 !important;
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

        /* Nama Promo - Rata Kiri */
        .data-table th:nth-child(2),
        .data-table td:nth-child(2) {
            text-align: left;
            padding-left: 24px;
        }

        /* Diskon - Rata Tengah */
        .data-table th:nth-child(3),
        .data-table td:nth-child(3) {
            width: 150px;
            text-align: center;
        }

        .data-table th:nth-child(4),
        .data-table td:nth-child(4) {
            width: 150px;
            text-align: center;
        }

        .data-table th:nth-child(5),
        .data-table td:nth-child(5) {
            width: 180px;
            text-align: center;
        }

        .promo-name {
            font-weight: 700;
            color: var(--text);
            font-size: 14px;
            text-align: left;
        }

        .promo-disc {
            font-family: 'Barlow', sans-serif;
            font-weight: 700;
            font-size: 14px;
            color: var(--text);
            text-align: center;
        }

        .promo-id-badge {
            color: var(--orange);
            font-weight: 800;
            font-family: 'Barlow Condensed';
            font-size: 16px;
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

        .status-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 7px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .3px;
            min-width: 100px;
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

        .actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            align-items: center;
            min-width: 180px;
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

        .modal-input.error {
            border-color: var(--red) !important;
            background-color: #FEF2F2 !important;
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.15) !important;
        }

        .modal-input.error:focus {
            border-color: var(--red) !important;
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.25) !important;
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

        /* RESPONSIVE */
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

            .btn-action {
                padding: 6px 10px;
                font-size: 11px;
            }

            .pagination-wrap {
                flex-direction: column;
                gap: 12px;
            }

            .modal-box {
                width: 90%;
                margin: 20px;
            }
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
    </style>
</head>

<body>

    <!-- MODAL FORM PROMO -->
    <div class="modal-overlay" id="modalPromo">
        <div class="modal-box">
            <button class="modal-close" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></button>
            <div class="modal-header">
                <div class="modal-subtitle">Kelola Promo</div>
                <div class="modal-title" id="form-modal-title">Tambah Promo Baru</div>
            </div>
            <div class="modal-body">
                <form id="formPromo" onsubmit="handleFormSubmit(event)" novalidate>
                    <input type="hidden" name="id_prm" id="id_prm" value="">
                    <div id="additional-form-inputs"></div>

                    <label class="modal-label">Nama Promo <span class="required">*</span></label>
                    <input type="text" name="nama_promo" id="nama_promo" class="modal-input"
                        placeholder="Masukkan nama promo (misal: Promo Akhir Tahun)" autocomplete="off"
                        value="" required minlength="3" maxlength="100">
                    <div class="val-msg" id="val-nama_promo"></div>

                    <label class="modal-label">Diskon (%) <span class="required">*</span></label>
                    <input type="number" name="diskon" id="diskon" class="modal-input" placeholder="10" max="100"
                        autocomplete="off" value="" required>
                    <div class="val-msg" id="val-diskon"></div>

                    <div class="modal-grid-2">
                        <div>
                            <label class="modal-label">Tanggal Mulai <span class="required">*</span></label>
                            <input type="date" name="tgl_m" id="tgl_m" class="modal-input" value="" required>
                            <div class="val-msg" id="val-tgl_m"></div>
                        </div>
                        <div>
                            <label class="modal-label">Tanggal Selesai <span class="required">*</span></label>
                            <input type="date" name="tgl_s" id="tgl_s" class="modal-input" value="" required>
                            <div class="val-msg" id="val-tgl_s"></div>
                        </div>
                    </div>
                    <div class="val-msg" id="val-tanggal" style="margin-top: -8px;"></div>

                    <button type="submit" class="btn-submit" id="btn-submit-form">
                        <i class="fa-solid fa-plus"></i> Tambah Promo
                    </button>
                    <a onclick="closeModal()" class="btn-cancel">Batal</a>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL DETAIL PROMO -->
    <div class="modal-overlay" id="modalDetail">
        <div class="modal-box" style="width: 440px;">
            <button class="modal-close" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></button>
            <div class="modal-header" style="border-bottom: none; padding-bottom: 0;">
                <div class="modal-subtitle">Informasi Promo</div>
                <div class="modal-title">Profil Promo</div>
            </div>
            <div class="modal-body" id="detail-modal-body" style="padding-top: 10px;">
                <!-- Dinamis diisi lewat AJAX Javascript -->
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
                    <div class="page-title">Kelola Promo</div>
                </div>
                <div class="stat-chips">
                    <div class="stat-chip chip-green"><i class="fa-solid fa-circle-check"></i> AKTIF <span
                            class="chip-val" id="stat-aktif"><?= $active_count ?></span></div>
                    <div class="stat-chip chip-red"><i class="fa-solid fa-circle-xmark"></i> KADALUARSA <span
                            class="chip-val" id="stat-nonaktif"><?= $expired_count ?></span></div>
                    <div class="stat-chip chip-blue"><i class="fa-solid fa-list"></i> TOTAL <span
                            class="chip-val" id="stat-total"><?= $total_data ?></span></div>
                </div>
            </div>

            <!-- ACTION BAR -->
            <div class="action-bar">
                <div class="search-box">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="src" placeholder="Cari promo... (Tekan Enter)" onkeypress="handleSearch(event)" value="">
                    <button type="button" onclick="clearSearch()" class="btn-clear-search" id="btnClearSearch" style="display: none;">
                        <i class="fa-solid fa-circle-xmark"></i>
                    </button>
                </div>

                <div style="display: flex; gap: 12px; align-items: center;">
                    <div class="filter-dropdown-wrap">
                        <!-- Perbaikan ID custom dan onclick khusus untuk bypass tabrakan event global.js -->
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
                                        <option value="nama_desc">Nama Z - A</option>
                                        <option value="diskon_desc">Diskon Terbesar ke Terkecil</option>
                                        <option value="diskon_asc">Diskon Terkecil ke Terbesar</option>
                                    </select>
                                </div>

                                <div class="filter-group">
                                    <label>Status Promo</label>
                                    <select name="f_status" class="filter-input">
                                        <option value="">Semua Status</option>
                                        <option value="1">AKTIF</option>
                                        <option value="0">KADALUARSA</option>
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
                    <div class="card-title"><i class="fa-solid fa-tag"></i> Data Promo</div>
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
                                    <th style="width: 80px;" class="col-center">No</th>
                                    <th class="col-left">Nama Promo</th>
                                    <th style="width: 150px;" class="col-center">Diskon</th>
                                    <th style="width: 150px;" class="col-center">Status</th>
                                    <th style="text-align: center; width: 180px;" class="col-center">Aksi</th>
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
            const url = `promo.php?action=get_table_data&page=${currentPage}&f_sort=${currentSort}&f_status=${currentStatus}&src=${encodeURIComponent(currentSearch)}`;
            try {
                const response = await fetch(url);
                const data = await response.json();
                if (data.success) {
                    // Update stats
                    document.getElementById('stat-aktif').textContent = data.stats.active;
                    document.getElementById('stat-nonaktif').textContent = data.stats.inactive;
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
        function closeModal() {
            document.getElementById('modalPromo').classList.remove('open');
            document.getElementById('modalDetail').classList.remove('open');
        }

        function openAddModal() {
            document.getElementById('formPromo').reset();
            document.getElementById('id_prm').value = '';
            document.getElementById('additional-form-inputs').innerHTML = '';
            
            // Bersihkan error validasi sebelumnya
            document.querySelectorAll('.val-msg').forEach(el => el.classList.remove('show'));
            document.querySelectorAll('.modal-input').forEach(el => el.classList.remove('error'));

            // Konfigurasi Title & Button Modal
            document.getElementById('form-modal-title').textContent = 'Tambah Promo Baru';
            document.getElementById('btn-submit-form').innerHTML = '<i class="fa-solid fa-plus"></i> Tambah Promo';

            document.getElementById('modalPromo').classList.add('open');
        }

        async function openEditModal(id) {
            // Bersihkan error validasi sebelumnya
            document.querySelectorAll('.val-msg').forEach(el => el.classList.remove('show'));
            document.querySelectorAll('.modal-input').forEach(el => el.classList.remove('error'));

            try {
                const response = await fetch(`promo.php?action=get_detail&id=${id}`);
                const res = await response.json();
                if (res.success) {
                    const data = res.data;
                    document.getElementById('id_prm').value = data.ID_Promo;
                    document.getElementById('nama_promo').value = data.Nama_Promo;
                    document.getElementById('diskon').value = data.Diskon;
                    document.getElementById('tgl_m').value = data.Tanggal_Mulai;
                    document.getElementById('tgl_s').value = data.Tanggal_Selesai;

                    document.getElementById('additional-form-inputs').innerHTML = `
                        <input type="hidden" name="edit_mode" value="1">
                    `;

                    // Konfigurasi Title & Button Modal
                    document.getElementById('form-modal-title').textContent = 'Edit Promo';
                    document.getElementById('btn-submit-form').innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Simpan Perubahan';

                    document.getElementById('modalPromo').classList.add('open');
                } else {
                    showError('Gagal!', res.msg);
                }
            } catch (error) {
                showError('Gagal!', 'Terjadi kesalahan saat mengambil data.');
            }
        }

        async function viewDetail(id) {
            try {
                const response = await fetch(`promo.php?action=get_detail&id=${id}`);
                const res = await response.json();
                if (res.success) {
                    const data = res.data;
                    const is_active_detail = (data.StatusText === 'AKTIF');

                    const statusPill = is_active_detail 
                        ? `<span class="status-pill sp-active"><span class="sp-dot"></span>AKTIF</span>`
                        : `<span class="status-pill sp-inactive"><span class="sp-dot"></span>KADALUARSA</span>`;

                    document.getElementById('detail-modal-body').innerHTML = `
                        <div class="detail-photo-card">
                            <div class="detail-icon-wrap"><i class="fa-solid fa-tag"></i></div>
                            <div class="detail-main-name">${data.Nama_Promo}</div>
                        </div>
                        <div class="info-row">
                            <span class="info-key"><i class="fa-solid fa-tag"></i> Nama Promo</span>
                            <span class="info-val" style="font-weight:700;">${data.Nama_Promo}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-key"><i class="fa-solid fa-percent"></i> Diskon</span>
                            <span class="info-val price" style="font-family:'Barlow Condensed'; font-size:18px; color:var(--orange); font-weight:800;">${data.DiskonFormatted}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-key"><i class="fa-solid fa-calendar-days"></i> Periode Mulai</span>
                            <span class="info-val" style="font-weight:700;">${data.TanggalMulaiFormatted}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-key"><i class="fa-solid fa-calendar-xmark"></i> Periode Selesai</span>
                            <span class="info-val" style="font-weight:700;">${data.TanggalSelesaiFormatted}</span>
                        </div>
                        <div class="info-row" style="border-bottom:none;">
                            <span class="info-key"><i class="fa-solid fa-circle-check"></i> Status Promo</span>
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

        // ============================================
        // AJAX SUBMIT FORM (TAMBAH / EDIT)
        // ============================================
        async function handleFormSubmit(event) {
            event.preventDefault();
            if (!validateForm()) return;

            const form = document.getElementById('formPromo');
            const formData = new FormData(form);
            formData.append('action', 'save');

            Swal.fire({
                title: 'Memproses...',
                text: 'Menyimpan data promo',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });

            try {
                const response = await fetch('promo.php', {
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
            const icon = newStatus === 1 ? 'success' : 'warning';
            const confirmColor = newStatus === 1 ? '#10B981' : '#EF4444';

            const result = await Swal.fire({
                title: 'Ubah Status?',
                html: 'Ubah status <strong style="color:var(--orange);">' + name + '</strong>?',
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
                    text: 'Mengubah status promo',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
                });

                try {
                    const response = await fetch(`promo.php?action=toggle&id=${id}&status=${currentStatus}`);
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
                title: 'Hapus Promo?',
                html: 'Anda akan menghapus promo <strong style="color:var(--orange);">' + name + '</strong><br><span style="font-size:12px;color:var(--muted);">Data akan dihapus secara Permanen</span>',
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
                    text: 'Menghapus promo',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
                });

                try {
                    const response = await fetch(`promo.php?action=delete&id=${id}`);
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

            if (fieldId === 'nama_promo') {
                if (value.length < 3) {
                    field.classList.add('error');
                    valMsg.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Nama promo minimal 3 karakter.';
                    valMsg.classList.add('show');
                    return false;
                }
                if (/^\d+$/.test(value)) {
                    field.classList.add('error');
                    valMsg.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Nama promo tidak boleh hanya angka.';
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
            }

            if (rules.minNum !== undefined && value !== '') {
                const num = Number(value);
                if (num < rules.minNum) {
                    field.classList.add('error');
                    valMsg.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Minimal ' + rules.minNum;
                    valMsg.classList.add('show');
                    return false;
                }
            }

            if (rules.maxNum !== undefined && value !== '') {
                const num = Number(value);
                if (num > rules.maxNum) {
                    field.classList.add('error');
                    valMsg.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Maksimal ' + rules.maxNum;
                    valMsg.classList.add('show');
                    return false;
                }
            }

            return true;
        }

        function validateForm() {
            let valid = true;

            if (!validateField('nama_promo', 'val-nama_promo', {
                required: true,
                minLength: 3,
                maxLength: 100,
                pattern: true,
                label: 'Nama promo'
            })) valid = false;

            if (!validateField('diskon', 'val-diskon', {
                required: true,
                isNumeric: true,
                minNum: 1,
                maxNum: 100,
                label: 'Diskon'
            })) valid = false;

            const tgl_m = document.getElementById('tgl_m');
            const tgl_s = document.getElementById('tgl_s');
            const valTanggal = document.getElementById('val-tanggal');

            if (!validateField('tgl_m', 'val-tgl_m', {
                required: true,
                label: 'Tanggal mulai'
            })) valid = false;

            if (tgl_m.value) {
                const hariIni = new Date().toISOString().split('T')[0];
                if (tgl_m.value < hariIni) {
                    tgl_m.classList.add('error');
                    document.getElementById('val-tgl_m').innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Tanggal mulai tidak boleh kurang dari hari ini';
                    document.getElementById('val-tgl_m').classList.add('show');
                    valid = false;
                }
            }

            if (!validateField('tgl_s', 'val-tgl_s', {
                required: true,
                label: 'Tanggal selesai'
            })) valid = false;

            if (tgl_m.value && tgl_s.value && new Date(tgl_s.value) < new Date(tgl_m.value)) {
                tgl_m.classList.add('error');
                tgl_s.classList.add('error');
                valTanggal.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Tanggal selesai tidak boleh mendahului tanggal mulai';
                valTanggal.classList.add('show');
                valid = false;
            }

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
            const namaPromo = document.getElementById('nama_promo');
            if (namaPromo) {
                namaPromo.addEventListener('blur', function () {
                    validateField('nama_promo', 'val-nama_promo', {
                        required: true,
                        minLength: 3,
                        maxLength: 100,
                        pattern: true,
                        label: 'Nama promo'
                    });
                });
                namaPromo.addEventListener('input', function () {
                    if (this.classList.contains('error')) {
                        validateField('nama_promo', 'val-nama_promo', {
                            required: true,
                            minLength: 3,
                            maxLength: 100,
                            pattern: true,
                            label: 'Nama promo'
                        });
                    }
                });
            }

            const diskon = document.getElementById('diskon');
            if (diskon) {
                diskon.addEventListener('blur', function () {
                    validateField('diskon', 'val-diskon', {
                        required: true,
                        isNumeric: true,
                        minNum: 1,
                        maxNum: 100,
                        label: 'Diskon'
                    });
                });
                diskon.addEventListener('input', function () {
                    if (this.classList.contains('error')) {
                        validateField('diskon', 'val-diskon', {
                            required: true,
                            isNumeric: true,
                            minNum: 1,
                            maxNum: 100,
                            label: 'Diskon'
                        });
                    }
                });
            }

            const tglM = document.getElementById('tgl_m');
            const tglS = document.getElementById('tgl_s');
            if (tglM) {
                tglM.addEventListener('change', function () {
                    validateField('tgl_m', 'val-tgl_m', {
                        required: true,
                        label: 'Tanggal mulai'
                    });
                });
            }
            if (tglS) {
                tglS.addEventListener('change', function () {
                    validateField('tgl_s', 'val-tgl_s', {
                        required: true,
                        label: 'Tanggal selesai'
                    });
                });
            }
        });
    </script>
</body>

</html>