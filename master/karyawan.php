<?php
session_start();
$path_prefix = "../";
include '../includes/config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'pemilik') {
    echo "<script>alert('Akses Ditolak!'); window.location='../dashboard/dashboard.php';</script>";
    exit();
}

// ========================================================
// ⚠️ PANGGIL SENSOR AUTO LOGOUT IDLE (DENGAN PENGAMAN AJAX) ⚠️
// ========================================================
$action_value = $_GET['action'] ?? $_POST['action'] ?? '';
$is_real_ajax = ($action_value !== '' && $action_value !== 'auto_logout') || isset($_GET['ajax_check_nik']);

if (!$is_real_ajax) {
    require_once '../login/auto_logout.php';
}
// ========================================================

$nama = $_SESSION['nama'] ?? '';
$role = $_SESSION['role'] ?? '';

// ============================================================================
// FOTO PROFIL — AMBIL LANGSUNG DARI DATABASE (TIDAK PAKAI SESSION)
// ============================================================================
$profile_photo = '';
$id_karyawan_session = $_SESSION['id_karyawan'] ?? $_SESSION['id_akun'] ?? '';

if (!empty($id_karyawan_session)) {
    $photo_stmt = sqlsrv_query(
        $conn,
        "SELECT Photo_Profile FROM Karyawan WHERE ID_Karyawan = ? AND Is_Deleted = 0",
        array($id_karyawan_session)
    );
    if ($photo_stmt && $photo_row = sqlsrv_fetch_array($photo_stmt, SQLSRV_FETCH_ASSOC)) {
        $db_photo = $photo_row['Photo_Profile'] ?? '';
        if (!empty($db_photo)) {
            $folder_name = basename(dirname($_SERVER['PHP_SELF']));
            if (in_array($folder_name, ['master', 'laporan'])) {
                $profile_photo = '../' . $db_photo;
            } else {
                $profile_photo = $db_photo;
            }
        }
    }
}

if (!empty($profile_photo)) {
    $_SESSION['Photo_Profile'] = $profile_photo;
}
$map_jk = [0 => 'Perempuan', 1 => 'Laki-laki'];
$sidebar_photo = $profile_photo;
$sidebar_folder = 'master';
$current_page = 'karyawan';

$map_jabatan = [1 => 'Karyawan', 2 => 'Manajer'];
$map_status = [0 => 'Nonaktif', 1 => 'Aktif'];

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

function formatDate($date)
{
    if (!$date)
        return '-';
    if (is_object($date) && method_exists($date, 'format')) {
        return $date->format('d M Y H:i');
    }
    return $date;
}

// ============================================
// HELPER: Generate initials from name
// ============================================
function getInitials($name)
{
    $clean_name = trim($name);
    $name_parts = explode(' ', $clean_name);
    if (count($name_parts) >= 2) {
        return strtoupper(substr($name_parts[0], 0, 1) . substr(end($name_parts), 0, 1));
    }
    return strtoupper(substr($clean_name, 0, 2));
}

// ── PROSES AJAX REQUESTS ──
$is_ajax = $is_real_ajax;
if ($is_ajax) {
    header('Content-Type: application/json');
    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    // Action: Check NIK Duplicate (AJAX)
    if (isset($_GET['ajax_check_nik'])) {
        $nik = $_GET['nik'] ?? '';
        $exclude_id = isset($_GET['exclude_id']) ? intval($_GET['exclude_id']) : 0;
        $check = safe_sqlsrv_query($conn, "EXEC sp_Karyawan_CheckNIK ?, ?", array($nik, $exclude_id), false);
        $exists = false;
        if ($check && $row = safe_sqlsrv_fetch_array($check, SQLSRV_FETCH_ASSOC)) {
            $exists = (bool) $row['Exists_Flag'];
        }
        echo json_encode(['exists' => $exists]);
        exit();
    }

    // Action: Ambil Detail Karyawan (AJAX)
    if ($action === 'get_detail') {
        $id = intval($_GET['id'] ?? 0);
        $sql = "
            SELECT *, 
                   DATEDIFF(YEAR, Tanggal_Lahir, GETDATE()) - 
                   CASE WHEN DATEADD(YEAR, DATEDIFF(YEAR, Tanggal_Lahir, GETDATE()), Tanggal_Lahir) > GETDATE() 
                        THEN 1 ELSE 0 END AS Umur
            FROM Karyawan 
            WHERE ID_Karyawan = ? AND Is_Deleted = 0
        ";
        $r = safe_sqlsrv_query($conn, $sql, array($id), false);
        if ($r && $data = safe_sqlsrv_fetch_array($r, SQLSRV_FETCH_ASSOC)) {
            if (isset($data['Tanggal_Lahir']) && is_object($data['Tanggal_Lahir'])) {
                $data['Tanggal_Lahir_Formatted'] = $data['Tanggal_Lahir']->format('Y-m-d');
            } else {
                $data['Tanggal_Lahir_Formatted'] = $data['Tanggal_Lahir'] ?? '';
            }
            echo json_encode(['success' => true, 'data' => $data]);
        } else {
            echo json_encode(['success' => false, 'msg' => 'Karyawan tidak ditemukan.']);
        }
        exit();
    }

    // Action: Simpan Data Tambah / Edit Karyawan (AJAX)
    if ($action === 'save') {
        $id_kry = intval($_POST['id_kry'] ?? 0);
        $nik = $_POST['nik'] ?? '';
        $nama_kry = $_POST['nama'] ?? '';
        $jk = intval($_POST['jk'] ?? 1);
        $jabatan = intval($_POST['jabatan'] ?? 1);
        $telp = $_POST['telp'] ?? '';
        $status = intval($_POST['status'] ?? 1);
        $created_by = $_SESSION['nama'] ?? 'SYSTEM';
        $tempat_lahir = $_POST['tempat_lahir'] ?? '';
        $tanggal_lahir = $_POST['tanggal_lahir'] ?? '';
        $alamat = $_POST['alamat'] ?? '';
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $email = $_POST['email'] ?? '';

        // Server-side validations
        $checkNIK = safe_sqlsrv_query($conn, "EXEC sp_Karyawan_CheckNIK ?, ?", array($nik, $id_kry), false);
        if ($checkNIK && $row = safe_sqlsrv_fetch_array($checkNIK, SQLSRV_FETCH_ASSOC)) {
            if ($row['Exists_Flag'] == 1) {
                echo json_encode(['success' => false, 'msg' => 'NIK sudah terdaftar di sistem!']);
                exit();
            }
        }

        $checkUsername = safe_sqlsrv_query($conn, "EXEC sp_Karyawan_CheckUsername ?, ?", array($username, $id_kry), false);
        if ($checkUsername && $row = safe_sqlsrv_fetch_array($checkUsername, SQLSRV_FETCH_ASSOC)) {
            if ($row['Exists_Flag'] == 1) {
                echo json_encode(['success' => false, 'msg' => 'Username sudah terdaftar!']);
                exit();
            }
        }

        $checkTelp = safe_sqlsrv_query($conn, "EXEC sp_Karyawan_CheckTelp ?, ?", array($telp, $id_kry), false);
        if ($checkTelp && $row = safe_sqlsrv_fetch_array($checkTelp, SQLSRV_FETCH_ASSOC)) {
            if ($row['Exists_Flag'] == 1) {
                echo json_encode(['success' => false, 'msg' => 'Nomor telepon sudah terdaftar!']);
                exit();
            }
        }

        $checkEmail = safe_sqlsrv_query($conn, "EXEC sp_Karyawan_CheckEmail ?, ?", array($email, $id_kry), false);
        if ($checkEmail && $row = safe_sqlsrv_fetch_array($checkEmail, SQLSRV_FETCH_ASSOC)) {
            if ($row['Exists_Flag'] == 1) {
                echo json_encode(['success' => false, 'msg' => 'Email sudah terdaftar!']);
                exit();
            }
        }

        if ($id_kry > 0) {
            // Proses Update
            $params = array($id_kry, $nik, $nama_kry, $tanggal_lahir, $tempat_lahir, $alamat, $jk, $jabatan, $telp, $email, $username, $password, $status, null, $created_by);
            $stmt = sqlsrv_query($conn, "EXEC sp_Karyawan_Update ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?", $params);
            $success_msg = 'Informasi staf berhasil diperbarui!';
        } else {
            // Proses Insert
            $new_id = 0;
            $params = array(
                array(&$nik, SQLSRV_PARAM_IN),
                array(&$nama_kry, SQLSRV_PARAM_IN),
                array(&$tanggal_lahir, SQLSRV_PARAM_IN),
                array(&$tempat_lahir, SQLSRV_PARAM_IN),
                array(&$alamat, SQLSRV_PARAM_IN),
                array(&$jk, SQLSRV_PARAM_IN),
                array(&$jabatan, SQLSRV_PARAM_IN),
                array(&$telp, SQLSRV_PARAM_IN),
                array(&$email, SQLSRV_PARAM_IN),
                array(&$username, SQLSRV_PARAM_IN),
                array(&$password, SQLSRV_PARAM_IN),
                array(&$status, SQLSRV_PARAM_IN),
                array(null, SQLSRV_PARAM_IN),
                array(&$created_by, SQLSRV_PARAM_IN),
                array(&$new_id, SQLSRV_PARAM_OUT)
            );
            $stmt = sqlsrv_query($conn, "EXEC sp_Karyawan_Insert ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?", $params);
            $success_msg = 'Karyawan baru berhasil didaftarkan!';
        }

        if ($stmt) {
            echo json_encode(['success' => true, 'msg' => $success_msg]);
        } else {
            $errors = sqlsrv_errors();
            $err_msg = 'Gagal menyimpan data karyawan.';
            if ($errors) {
                $err_msg = $errors[0]['message'] ?? $err_msg;
            }
            echo json_encode(['success' => false, 'msg' => $err_msg]);
        }
        exit();
    }

    // Action: Toggle Status (AJAX)
    if ($action === 'toggle_status') {
        $id = intval($_GET['id'] ?? 0);
        $modified_by = $_SESSION['nama'] ?? 'SYSTEM';
        $stmt = safe_sqlsrv_query($conn, "EXEC sp_Karyawan_ToggleStatus ?, ?", array($id, $modified_by), false);
        if ($stmt && $row = safe_sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $status_label = $row['StatusLabel'] ?? 'diubah';
            echo json_encode(['success' => true, 'msg' => "Status karyawan berhasil diubah menjadi " . $status_label . "!"]);
        } else {
            echo json_encode(['success' => false, 'msg' => 'Gagal mengubah status karyawan di database.']);
        }
        exit();
    }

    // Action: Hapus (Soft Delete) (AJAX)
    if ($action === 'delete') {
        $id = intval($_GET['id'] ?? 0);
        $deleted_by = $_SESSION['nama'] ?? 'SYSTEM';
        $stmt = safe_sqlsrv_query($conn, "EXEC sp_Karyawan_Delete ?, ?", array($id, $deleted_by), false);
        if ($stmt) {
            echo json_encode(['success' => true, 'msg' => 'Karyawan telah berhasil dihapus dari sistem!']);
        } else {
            echo json_encode(['success' => false, 'msg' => 'Gagal menghapus data karyawan.']);
        }
        exit();
    }

    // Action: Muat Ulang Tabel & Statistik (Dynamic AJAX Refresh)
    if ($action === 'get_table_data') {
        $filter_jabatan = isset($_GET['filter_jabatan']) ? intval($_GET['filter_jabatan']) : 0;
        $filter_jk = isset($_GET['filter_jk']) ? intval($_GET['filter_jk']) : -1;
        $filter_status = isset($_GET['filter_status']) ? intval($_GET['filter_status']) : -1;
        $filter_umur = isset($_GET['filter_umur']) ? $_GET['filter_umur'] : 'all';
        $sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'Nama_Karyawan';
        $sort_order = isset($_GET['sort_order']) ? $_GET['sort_order'] : 'ASC';
        $search = isset($_GET['src']) ? trim($_GET['src']) : '';
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

        $limit = 10;

        // --- SOLUSI: Menggunakan Raw SQL Dinamis agar pencarian nama, nik, username berfungsi penuh ---
        $sql_where = " WHERE Is_Deleted = 0";
        $params_query = [];

        if ($filter_jabatan > 0) {
            $sql_where .= " AND Jabatan = ?";
            $params_query[] = $filter_jabatan;
        }
        if ($filter_jk != -1) {
            $sql_where .= " AND Jenis_Kelamin = ?";
            $params_query[] = $filter_jk;
        }
        if ($filter_status != -1) {
            $sql_where .= " AND Status = ?";
            $params_query[] = $filter_status;
        }
        if ($search !== '') {
            $sql_where .= " AND (Nama_Karyawan LIKE ? OR NIK LIKE ? OR Username LIKE ?)";
            $params_query[] = "%$search%";
            $params_query[] = "%$search%";
            $params_query[] = "%$search%";
        }

        // Filter Umur dinamis
        if ($filter_umur === 'muda') {
            $sql_where .= " AND (DATEDIFF(YEAR, Tanggal_Lahir, GETDATE()) - CASE WHEN DATEADD(YEAR, DATEDIFF(YEAR, Tanggal_Lahir, GETDATE()), Tanggal_Lahir) > GETDATE() THEN 1 ELSE 0 END) < 25";
        } elseif ($filter_umur === 'produktif') {
            $sql_where .= " AND (DATEDIFF(YEAR, Tanggal_Lahir, GETDATE()) - CASE WHEN DATEADD(YEAR, DATEDIFF(YEAR, Tanggal_Lahir, GETDATE()), Tanggal_Lahir) > GETDATE() THEN 1 ELSE 0 END) BETWEEN 25 AND 40";
        } elseif ($filter_umur === 'senior') {
            $sql_where .= " AND (DATEDIFF(YEAR, Tanggal_Lahir, GETDATE()) - CASE WHEN DATEADD(YEAR, DATEDIFF(YEAR, Tanggal_Lahir, GETDATE()), Tanggal_Lahir) > GETDATE() THEN 1 ELSE 0 END) > 40";
        }

        // PERBAIKAN URUTAN LOGIKA: Kueri total data dijalankan setelah $sql_where dibangun lengkap di atas
        $count_sql = "SELECT COUNT(*) AS Total FROM Karyawan" . $sql_where;
        $count_query = safe_sqlsrv_query($conn, $count_sql, $params_query, false);
        $total_rows = 0;
        if ($count_query !== false) {
            $count_row = safe_sqlsrv_fetch_array($count_query, SQLSRV_FETCH_ASSOC);
            $total_rows = $count_row['Total'] ?? 0;
        }

        $total_pages = max(1, ceil($total_rows / $limit));
        $page = min($page, $total_pages);
        $offset = ($page - 1) * $limit;

        // Ambil Total Aktif (UDF)
        $q_total_aktif = safe_sqlsrv_query($conn, "SELECT dbo.fn_GetTotalKaryawanAktif() AS t", [], false);
        $total_aktif = 0;
        if ($q_total_aktif !== false) {
            $row_aktif = safe_sqlsrv_fetch_array($q_total_aktif, SQLSRV_FETCH_ASSOC);
            $total_aktif = $row_aktif['t'] ?? 0;
        }

        // Mapping Kolom Sorting (Termasuk Umur)
        $allowed_sort = [
            'Nama_Karyawan' => 'Nama_Karyawan',
            'Jenis_Kelamin' => 'Jenis_Kelamin',
            'Jabatan' => 'Jabatan',
            'Status' => 'Status',
            'Umur' => 'Umur'
        ];
        $sort_column = $allowed_sort[$sort_by] ?? 'Nama_Karyawan';

        // Ambil Data List Karyawan + Kalkulasi Umur
        $sql_data = "
            SELECT *, 
                   DATEDIFF(YEAR, Tanggal_Lahir, GETDATE()) - 
                   CASE WHEN DATEADD(YEAR, DATEDIFF(YEAR, Tanggal_Lahir, GETDATE()), Tanggal_Lahir) > GETDATE() 
                        THEN 1 ELSE 0 END AS Umur
            FROM Karyawan
            " . $sql_where . "
            ORDER BY " . $sort_column . " " . $sort_order . "
            OFFSET ? ROWS FETCH NEXT ? ROWS ONLY
        ";

        $params_data = array_merge($params_query, [$offset, $limit]);
        $query = safe_sqlsrv_query($conn, $sql_data, $params_data, false);

        // Render HTML Tabel Body
        ob_start();
        $row_num = $offset;
        $has_data = false;
        if ($query):
            while ($row = safe_sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)):
                $row_num++;
                $has_data = true;
                $status_val = $row['Status'] ?? 0;
                $is_active = $status_val == 1;
                $jabatan_val = $row['Jabatan'] ?? 1;
                $jabatan_label = $map_jabatan[$jabatan_val] ?? 'Tidak diketahui';
                $jabatan_class = ($jabatan_val == 2) ? 'jabatan-manajer' : '';
                $id_karyawan = $row['ID_Karyawan'] ?? '';
                $nama_kry = $row['Nama_Karyawan'] ?? '';
                $jk = $row['Jenis_Kelamin'] ?? 1;
                $umur = $row['Umur'] ?? 0;
                $initials = getInitials($nama_kry);
                $jk_label = $map_jk[$jk] ?? 'Tidak diketahui';
                $jk_class = ($jk == 1) ? 'jk-laki' : 'jk-perempuan';
                $jk_icon = ($jk == 1) ? 'fa-mars' : 'fa-venus';
                ?>
                <tr id="row-<?= htmlspecialchars($id_karyawan) ?>" class="emp-row"
                    data-name="<?= strtolower(htmlspecialchars($nama_kry)) ?>">
                    <!-- Menambahkan properti font-weight tebal dan warna teks yang tegas -->
                    <td class="row-num" style="text-align: center; font-weight: 700; color: var(--text);"><?= $row_num ?></td>
                    </div>
                    <!-- Rata Kiri -->
                    <td style="text-align: left;">
                        <div class="emp-name-cell">
                            <div class="emp-avatar"><?= $initials ?></div>
                            <div class="emp-name"><?= htmlspecialchars($nama_kry) ?></div>
                        </div>
                    </td>
                    <!-- Rata Kiri -->
                    <td style="text-align: left;">
                        <span class="jk-badge <?= $jk_class ?>">
                            <i class="fa-solid <?= $jk_icon ?>"></i> <?= strtoupper($jk_label) ?>
                        </span>
                    </td>
                    <!-- Rata Kiri -->
                    <td style="text-align: left;">
                        <span class="jabatan-badge <?= $jabatan_class ?>"><?= htmlspecialchars($jabatan_label) ?></span>
                    </td>
                    <!-- Kolom Umur baru (Rata Tengah) -->
                    <td style="text-align: center; font-weight: 700; color: var(--text-md);"><?= $umur ?> tahun</td>
                    <td style="text-align: center;">
                        <span class="status-pill <?= $is_active ? 'sp-active' : 'sp-inactive' ?>">
                            <span class="sp-dot"></span>
                            <?= $is_active ? 'AKTIF' : 'NONAKTIF' ?>
                        </span>
                    </td>
                    <td>
                        <div class="actions">
                            <button type="button" onclick="showDetail('<?= htmlspecialchars($id_karyawan) ?>')"
                                class="btn-action btn-view" title="Lihat Detail"><i class="fa-solid fa-eye"></i></button>
                            <button type="button" onclick="showEditForm('<?= htmlspecialchars($id_karyawan) ?>')"
                                class="btn-action btn-edit" title="Edit Data"><i class="fa-solid fa-pen-to-square"></i></button>
                            <label class="toggle-switch" title="<?= $is_active ? 'Nonaktifkan' : 'Aktifkan' ?> karyawan">
                                <input type="checkbox" <?= $is_active ? 'checked' : '' ?>
                                    onchange="confirmToggle('<?= htmlspecialchars($id_karyawan) ?>', '<?= htmlspecialchars($nama_kry, ENT_QUOTES) ?>', <?= $is_active ? 'true' : 'false' ?>, this)">
                                <span class="toggle-slider"></span>
                            </label>
                            <button type="button" class="btn-action btn-delete"
                                onclick="confirmDelete('<?= $id_karyawan ?>', '<?= htmlspecialchars($nama_kry, ENT_QUOTES) ?>')"
                                title="Hapus"><i class="fa-solid fa-trash-can"></i></button>
                        </div>
                    </td>
                </tr>
                <?php
            endwhile;
        endif;

        if (!$has_data): ?>
            <tr>
                <td colspan="6">
                    <div class="empty-state"><i class="fa-solid fa-user-tie"></i>
                        <p>Belum ada data karyawan terdaftar</p>
                    </div>
                </td>
            </tr>
        <?php endif;
        $table_html = ob_get_clean();

        // Render HTML Pagination
        ob_start();
        if ($total_pages > 1): ?>
            <div class="pagination-info">Menampilkan <strong><?= (($page - 1) * $limit) + 1 ?></strong> -
                <strong><?= min($page * $limit, $total_rows) ?></strong> dari <strong><?= $total_rows ?></strong> data
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
                    class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>" title="Halaman Selanjutnya"><i
                        class="fa-solid fa-angle-right"></i></button>
                <button onclick="changePage(<?= $total_pages ?>)" class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>"
                    title="Halaman Terakhir"><i class="fa-solid fa-angles-right"></i></button>
            </div>
        <?php else: ?>
            <div class="pagination-info">Menampilkan <strong>1</strong> - <strong><?= $total_rows ?></strong> dari
                <strong><?= $total_rows ?></strong> data
            </div>
        <?php endif;
        $pagination_html = ob_get_clean();

        echo json_encode([
            'success' => true,
            'table' => $table_html,
            'pagination' => $pagination_html,
            'stats' => [
                'total' => $total_rows,
                'aktif' => $total_aktif
            ]
        ]);
        exit();
    }
}

// ── GET STATISTICS UNTUK AWAL PAGE LOAD ──
$total_rows = 0;
$count_query = safe_sqlsrv_query($conn, "SELECT COUNT(*) AS Total FROM Karyawan WHERE Is_Deleted = 0", [], false);
if ($count_query !== false) {
    $count_row = safe_sqlsrv_fetch_array($count_query, SQLSRV_FETCH_ASSOC);
    $total_rows = $count_row['Total'] ?? 0;
}

$total_aktif = 0;
$q_total_aktif = safe_sqlsrv_query($conn, "SELECT dbo.fn_GetTotalKaryawanAktif() AS t", [], false);
if ($q_total_aktif !== false) {
    $row_aktif = safe_sqlsrv_fetch_array($q_total_aktif, SQLSRV_FETCH_ASSOC);
    $total_aktif = $row_aktif['t'] ?? 0;
}

$sidebar_photo = $profile_photo;
$sidebar_folder = 'master';
$current_page = 'karyawan';
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <?php include '../includes/favicon.php'; ?>
    <title>Kelola Karyawan | HoopBall</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800;900&family=Barlow:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../asset/css/responsive_tipe_member.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
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
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        html::-webkit-scrollbar {
            display: none;
        }

        body {
            font-family: 'Barlow', sans-serif;
            background: #F3F4F6;
            display: flex;
            min-height: 100vh;
            color: var(--text);
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        body::-webkit-scrollbar {
            display: none;
        }

        /* ============================================
   SIDEBAR
   ============================================ */
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

        .sb-brand {
            animation: menuItemFadeIn 0.5s cubic-bezier(0.16, 1, 0.3, 1) 0.1s forwards;
            opacity: 0;
        }

        .sb-section-label {
            animation: menuItemFadeIn 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            opacity: 0;
        }

        .sb-link {
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

        .sb-section-label:nth-of-type(2) {
            animation-delay: 0.4s;
        }

        .sb-link:nth-of-type(4) {
            animation-delay: 0.45s;
        }

        .sb-bottom {
            animation: menuItemFadeIn 0.5s cubic-bezier(0.16, 1, 0.3, 1) 0.5s forwards;
            opacity: 0;
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
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        .main::-webkit-scrollbar {
            display: none;
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

        .topbar-btn,
        .topbar-user {
            background-color: #FFFFFF !important;
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
        }

        .t-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
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
            display: block;
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

        /* CONTENT */
        .content {
            padding: 32px 40px;
            flex: 1;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        .content::-webkit-scrollbar {
            display: none;
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

        /* CARD & TABLE */
        .card {
            background: var(--card-bg);
            border-radius: 16px;
            border: 1px solid var(--border);
            overflow: hidden;
            transition: all .2s ease;
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
            text-align: center;
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

        .data-table th:nth-child(2) {
            width: 220px;
            text-align: left;
        }

        .data-table td:nth-child(2) {
            width: 220px;
            text-align: left;
        }

        .data-table th:nth-child(3),
        .data-table td:nth-child(3) {
            width: 120px;
            text-align: left;
        }

        .data-table th:nth-child(4),
        .data-table td:nth-child(4) {
            width: 100px;
            text-align: left;
        }

        /* Umur Column width definition */
        .data-table th:nth-child(5),
        .data-table td:nth-child(5) {
            width: 80px;
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
   EMPLOYEE AVATAR & NAME - CLEANED UP
   ============================================ */
        .emp-avatar {
            width: 40px;
            height: 40px;
            min-width: 40px;
            min-height: 40px;
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

        .emp-avatar:hover {
            transform: scale(1.08);
            box-shadow: 0 4px 16px rgba(255, 69, 0, 0.35);
        }

        .emp-name-cell {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            height: 100%;
            width: 220px;
            justify-content: flex-start;
        }

        .emp-name {
            font-weight: 700;
            color: var(--text);
            font-size: 14px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 200px;
        }

        /* ============================================
   BADGES & STATUS
   ============================================ */
        .jabatan-badge {
            background: #EEF2FF;
            color: #4338CA;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .jabatan-manajer {
            background: var(--orange-lt);
            color: var(--orange);
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

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
            width: 720px;
            max-width: 95vw;
            max-height: 90vh;
            overflow-y: auto;
            scrollbar-width: none;
            -ms-overflow-style: none;
            box-shadow: 0 25px 60px rgba(0, 0, 0, .2);
            position: relative;
        }

        .modal-box::-webkit-scrollbar {
            display: none;
        }

        .modal-head {
            padding: 28px 32px 24px;
            border-bottom: 1px solid var(--border);
        }

        .modal-tag {
            font-size: 10px;
            font-weight: 800;
            color: var(--orange);
            text-transform: uppercase;
            letter-spacing: .8px;
            margin-bottom: 6px;
        }

        .modal-title {
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 24px;
            font-weight: 900;
            color: var(--text);
        }

        .modal-sub {
            font-size: 13px;
            color: var(--muted);
            margin-top: 4px;
        }

        .modal-body {
            padding: 28px 32px 32px;
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

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .full-width {
            grid-column: span 2;
        }

        .field-label {
            font-size: 11px;
            font-weight: 800;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .5px;
            display: block;
            margin-bottom: 6px;
        }

        .field-label .required {
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
            font-size: 14px;
            font-family: 'Barlow', sans-serif;
            transition: .2s;
            background: #FAFAFA;
            color: var(--text);
            margin-bottom: 4px;
        }

        .modal-input:focus {
            outline: none;
            border-color: var(--orange);
            background: #fff;
            box-shadow: 0 0 0 3px rgba(255, 69, 0, .08);
        }

        .modal-input[readonly] {
            background: var(--border-lt);
            color: var(--muted);
        }

        .btn-submit {
            grid-column: span 2;
            width: 100%;
            background: var(--orange);
            color: #fff;
            border: none;
            padding: 14px;
            border-radius: 10px;
            font-weight: 800;
            font-size: 14px;
            cursor: pointer;
            text-transform: uppercase;
            letter-spacing: .5px;
            transition: .2s;
            font-family: 'Barlow', sans-serif;
            margin-top: 8px;
        }

        .btn-submit:hover {
            background: var(--orange-dk);
            transform: translateY(-1px);
            box-shadow: 0 8px 20px rgba(255, 69, 0, .25);
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

        .modal-input.error {
            border-color: var(--red) !important;
            background-color: #FEF2F2 !important;
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.15) !important;
        }

        .modal-input.error:focus {
            border-color: var(--red) !important;
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.25) !important;
        }

        /* DETAIL MODAL */
        .detail-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.55);
            backdrop-filter: blur(6px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 2000;
        }

        .detail-modal-overlay.open {
            display: flex;
        }

        .detail-modal-box {
            background: #fff;
            border-radius: 20px;
            width: 460px;
            max-height: 95vh;
            overflow-y: auto;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.2);
            position: relative;
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        .detail-modal-box::-webkit-scrollbar {
            display: none;
        }

        .detail-modal-close {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: var(--bg);
            border: 1.5px solid var(--border);
            color: var(--muted);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: .2s;
            font-size: 14px;
        }

        .detail-modal-close:hover {
            background: var(--red-lt);
            color: var(--red);
            border-color: var(--red);
        }

        .detail-photo-card {
            text-align: center;
            margin-bottom: 12px;
            padding-bottom: 10px;
            border-bottom: 1.5px dashed var(--border);
        }

        .detail-icon-wrap {
            width: 56px;
            height: 56px;
            background: var(--orange-lt);
            color: var(--orange);
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            margin-bottom: 12px;
            box-shadow: 0 6px 16px rgba(255, 69, 0, 0.15);
            border-bottom: 3px solid var(--orange);
            padding-bottom: 4px;
        }

        .detail-main-name {
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 18px;
            font-weight: 900;
            color: var(--text);
            text-transform: uppercase;
        }

        .info-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
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

        .info-val-mono {
            font-family: 'Barlow Condensed';
            font-size: 16px;
            font-weight: 800;
            color: var(--orange);
            text-align: right;
        }

        .btn-kembali {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            background: #0D1117;
            color: #fff;
            border: none;
            padding: 12px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 800;
            font-family: 'Barlow', sans-serif;
            text-transform: uppercase;
            letter-spacing: .5px;
            cursor: pointer;
            transition: .2s;
            margin-top: 16px;
        }

        .btn-kembali:hover {
            background: var(--orange);
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
            padding: 60px 20px;
            color: var(--muted);
        }

        .empty-state i {
            font-size: 40px;
            opacity: .3;
            display: block;
            margin-bottom: 14px;
        }

        .empty-state p {
            font-size: 13px;
            font-weight: 700;
        }

        /* ADD BUTTON */
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
            box-shadow: 0 8px 20px rgba(255, 69, 0, .25);
        }

        .btn-add i {
            font-size: 14px !important;
        }

        /* RADIO CARD */
        .radio-group-container {
            display: flex;
            gap: 12px;
            width: 100%;
            margin-top: 4px;
        }

        .radio-card {
            flex: 1;
            position: relative;
            cursor: pointer;
        }

        .radio-card input[type="radio"] {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }

        .radio-custom-box {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 12px;
            background: #FFFFFF;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
            color: var(--text);
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .radio-card:hover .radio-custom-box {
            border-color: #CBD5E1;
            background-color: var(--border-lt);
        }

        .radio-card input[type="radio"]:checked+.radio-custom-box {
            border-color: var(--orange);
            background-color: rgba(255, 69, 0, 0.02);
            color: var(--orange);
            box-shadow: 0 0 12px rgba(255, 69, 0, 0.08);
        }

        .radio-custom-box i {
            font-size: 15px;
        }

        /* PERBAIKAN: Gaya khusus border merah error untuk Custom Radio Card */
        .radio-custom-box.error {
            border-color: var(--red) !important;
            background-color: #FEF2F2 !important;
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

            .form-grid {
                grid-template-columns: 1fr;
            }

            .full-width {
                grid-column: span 1;
            }
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

        /* ============================================
   MATIKAN SEMUA ANIMASI SWEETALERT2 
   ============================================ */
        .swal2-popup {
            animation: none !important;
            transition: none !important;
        }

        .swal2-icon {
            animation: none !important;
        }

        .swal2-icon.swal2-success .swal2-success-ring,
        .swal2-icon.swal2-success [class^="swal2-success-line"],
        .swal2-icon.swal2-error [class^="swal2-x-mark-line"],
        .swal2-icon.swal2-warning {
            animation: none !important;
        }

        /* cegah body/html digeser oleh kompensasi scrollbar SweetAlert */
        html.swal2-shown,
        body.swal2-shown,
        body.swal2-height-auto {
            padding-right: 0 !important;
        }
    </style>
</head>

<body>
    <!-- MODAL TAMBAH/EDIT -->
    <div class="modal-overlay" id="modal">
        <div class="modal-box">
            <button class="modal-close" onclick="closeModalDirect('modal')"><i class="fa-solid fa-xmark"></i></button>
            <div class="modal-head">
                <div class="modal-tag">Kelola Karyawan</div>
                <div class="modal-title">Tambah Karyawan Baru</div>
                <div class="modal-sub">Daftarkan karyawan baru ke dalam sistem</div>
            </div>
            <div class="modal-body">
                <form id="formKaryawan" onsubmit="handleFormSubmit(event)" novalidate>
                    <div class="form-grid">
                        <input type="hidden" name="id_kry" id="edit_id_kry" value="0">
                        <div>
                            <label class="field-label">NIK <span class="required">*</span></label>
                            <input type="text" name="nik" id="nik" class="modal-input" required minlength="16"
                                maxlength="16" placeholder="3173011203950001" oninput="checkNIKDuplicate()"
                                onblur="checkNIKDuplicate()">
                            <div class="val-msg" id="val-nik"><i class="fa-solid fa-circle-exclamation"></i> NIK harus
                                16 digit angka</div>
                            <div class="val-msg" id="val-nik-dup"><i class="fa-solid fa-circle-exclamation"></i> NIK
                                sudah terdaftar di sistem</div>
                        </div>
                        <div>
                            <label class="field-label">Nama Lengkap <span class="required">*</span></label>
                            <input type="text" name="nama" id="nama" class="modal-input" required minlength="3"
                                maxlength="20" placeholder="Nama lengkap">
                            <div class="val-msg" id="val-nama"><i class="fa-solid fa-circle-exclamation"></i> Nama
                                minimal 3 karakter</div>
                        </div>
                        <div>
                            <label class="field-label">Nama Pengguna <span class="required">*</span></label>
                            <input type="text" name="username" id="username" class="modal-input" required minlength="3"
                                maxlength="20" placeholder="nama_pengguna" autocomplete="new-username">
                            <div class="val-msg" id="val-username"><i class="fa-solid fa-circle-exclamation"></i> Nama
                                Pengguna minimal 3 karakter</div>
                        </div>
                        <div>
                            <label class="field-label">Kata Sandi <span class="required">*</span></label>
                            <div style="position: relative; width: 100%;">
                                <input type="password" name="password" id="password" class="modal-input" required
                                    minlength="8" maxlength="20" placeholder="Masukkan Kata Sandi"
                                    style="padding-right: 42px;" autocomplete="new-password">
                                <i class="fa-solid fa-eye" id="togglePass" onclick="togglePassword()"
                                    style="position: absolute; right: 14px; top: 22px; transform: translateY(-50%); cursor: pointer; color: var(--muted); z-index: 10; font-size: 14px;"></i>
                            </div>
                            <div class="val-msg" id="val-password" style="margin-top: 4px;"><i
                                    class="fa-solid fa-circle-exclamation"></i> Kata Sandi minimal 8 karakter dan tidak
                                boleh ada spasi</div>
                        </div>
                        <div>
                            <label class="field-label">Email <span class="required">*</span></label>
                            <!-- Mengubah contoh placeholder menjadi akhiran hoopball.com -->
                            <input type="email" name="email" id="email" class="modal-input"
                                value="<?= htmlspecialchars($edit_data['Email'] ?? '') ?>" required maxlength="50"
                                placeholder="staf@hoopball.com">
                            <div class="val-msg" id="val-email"><i class="fa-solid fa-circle-exclamation"></i> Format
                                email wajib menggunakan akhiran @hoopball.com</div>
                        </div>
                        <div>
                            <label class="field-label">Nomor Telepon <span class="required">*</span></label>
                            <input type="text" name="telp" id="telp" class="modal-input" placeholder="08123456789"
                                required pattern="[0-9]{10,15}" maxlength="15">
                            <div class="val-msg" id="val-telp"><i class="fa-solid fa-circle-exclamation"></i> Nomor
                                telepon 10-15 digit</div>
                        </div>
                        <div>
                            <label class="field-label">Tempat Lahir <span class="required">*</span></label>
                            <input type="text" name="tempat_lahir" id="tempat_lahir" class="modal-input" required
                                minlength="2" maxlength="50" placeholder="Contoh: Jakarta, Bandung">
                            <div class="val-msg" id="val-tempat_lahir"><i class="fa-solid fa-circle-exclamation"></i>
                                Tempat lahir wajib diisi</div>
                        </div>
                        <div>
                            <label class="field-label">Tanggal Lahir <span class="required">*</span></label>
                            <input type="date" name="tanggal_lahir" id="tanggal_lahir" class="modal-input" required
                                max="<?= date('Y-m-d') ?>" onchange="validateDate(this)">
                            <div class="val-msg" id="val-tanggal_lahir"><i class="fa-solid fa-circle-exclamation"></i>
                                Tanggal lahir wajib diisi dan tidak boleh di masa depan</div>
                        </div>
                        <div class="full-width">
                            <label class="field-label">Alamat Lengkap <span class="required">*</span></label>
                            <textarea name="alamat" id="alamat" class="modal-input" required rows="3" maxlength="100"
                                placeholder="Jl. Merdeka No. 10, Jakarta Pusat" style="resize: none;"></textarea>
                            <div class="val-msg" id="val-alamat"><i class="fa-solid fa-circle-exclamation"></i> Alamat
                                wajib diisi</div>
                        </div>
                        <div>
                            <label class="field-label">Jenis Kelamin <span class="required">*</span></label>
                            <div class="radio-group-container">
                                <label class="radio-card">
                                    <!-- PERBAIKAN: checked dibuang agar tidak langsung terpilih -->
                                    <input type="radio" name="jk" value="1">
                                    <span class="radio-custom-box"><i class="fa-solid fa-mars"></i> Laki-laki</span>
                                </label>
                                <label class="radio-card">
                                    <input type="radio" name="jk" value="0">
                                    <span class="radio-custom-box"><i class="fa-solid fa-venus"></i> Perempuan</span>
                                </label>
                            </div>
                            <!-- PERBAIKAN: Menambahkan elemen pesan validasi Jenis Kelamin -->
                            <div class="val-msg" id="val-jk" style="margin-top: 6px;"><i
                                    class="fa-solid fa-circle-exclamation"></i> Jenis kelamin wajib dipilih</div>
                        </div>
                        <div>
                            <label class="field-label">Jabatan <span class="required">*</span></label>
                            <select name="jabatan" id="jabatan" class="modal-input" required>
                                <option value="">Pilih Jabatan</option>
                                <option value="1">Karyawan</option>
                                <option value="2">Manajer</option>
                            </select>
                            <div class="val-msg" id="val-jabatan"><i class="fa-solid fa-circle-exclamation"></i> Jabatan
                                wajib dipilih</div>
                        </div>
                        <div class="full-width">
                            <label class="field-label">Status <span class="required">*</span></label>
                            <select name="status" id="status" class="modal-input" required
                                style="background-color: var(--border-lt); color: var(--muted); pointer-events: none;">
                                <option value="1" selected>Aktif</option>
                                <option value="0">Nonaktif</option>
                            </select>
                        </div>

                        <button type="submit" class="btn-submit">
                            <i class="fa-solid fa-user-plus"></i> Daftarkan Karyawan
                        </button>

                        <!-- PERBAIKAN: Menambahkan tombol Batal dengan gaya visual yang serasi -->
                        <button type="button" onclick="closeModalDirect('modal')"
                            onmouseover="this.style.color='var(--orange)'" onmouseout="this.style.color='var(--muted)'"
                            style="grid-column: span 2; background: none; border: none; color: var(--muted); font-size: 13px; font-weight: 700; cursor: pointer; margin-top: 16px; width: 100%; text-align: center; font-family: 'Barlow', sans-serif; transition: color 0.2s;">
                            Batal
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>

    <!-- DETAIL MODAL -->
    <div class="detail-modal-overlay" id="detailModal" onclick="closeDetail(event)">
        <div class="detail-modal-box" onclick="event.stopPropagation()">
            <div class="modal-head" style="padding: 20px 24px 12px;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                    <div>
                        <div class="modal-tag">Informasi Karyawan</div>
                        <div class="modal-title">Profil Karyawan</div>
                    </div>
                    <button class="detail-modal-close" onclick="closeDetail()" title="Tutup"><i
                            class="fa-solid fa-xmark"></i></button>
                </div>
            </div>
            <div class="modal-body" style="padding: 12px 24px 20px;">
                <div class="detail-photo-card">
                    <div class="detail-icon-wrap"><i class="fa-solid fa-user-tie"></i></div>
                    <div class="detail-main-name" id="dNameHeader">-</div>
                </div>
                <div style="display: flex; flex-direction: column; gap: 2px;">
                    <div class="info-row"><span class="info-key"><i class="fa-solid fa-id-card"></i> NIK</span><span
                            class="info-val-mono" id="dNIK">-</span></div>
                    <div class="info-row"><span class="info-key"><i class="fa-solid fa-user"></i> Nama
                            Lengkap</span><span class="info-val" id="dNama">-</span></div>
                    <div class="info-row"><span class="info-key"><i class="fa-solid fa-user-tag"></i> Nama
                            Pengguna</span><span class="info-val-mono" id="dUsername">-</span></div>
                    <div class="info-row"><span class="info-key"><i class="fa-solid fa-lock"></i> Kata Sandi</span><span
                            class="info-val-mono" id="dPassword">-</span></div>
                    <div class="info-row"><span class="info-key"><i class="fa-solid fa-envelope"></i> Email</span><span
                            class="info-val" id="dEmail">-</span></div>
                    <div class="info-row"><span class="info-key"><i class="fa-solid fa-location-dot"></i> Tempat
                            Lahir</span><span class="info-val" id="dTempatLahir">-</span></div>
                    <div class="info-row"><span class="info-key"><i class="fa-solid fa-calendar-day"></i> Tanggal
                            Lahir</span><span class="info-val" id="dTanggalLahir">-</span></div>
                    <div class="info-row"><span class="info-key"><i class="fa-solid fa-map-location-dot"></i>
                            Alamat</span><span class="info-val" id="dAlamat">-</span></div>
                    <div class="info-row"><span class="info-key"><i class="fa-solid fa-venus-mars"></i> Jenis
                            Kelamin</span><span class="info-val" id="dJK">-</span></div>
                    <div class="info-row"><span class="info-key"><i class="fa-solid fa-briefcase"></i>
                            Jabatan</span><span class="info-val" id="dJabatan">-</span></div>
                    <div class="info-row"><span class="info-key"><i class="fa-solid fa-phone"></i> No.
                            Telepon</span><span class="info-val" id="dTelp">-</span></div>
                    <div class="info-row" style="border-bottom: none;"><span class="info-key"><i
                                class="fa-solid fa-circle-check"></i> Status</span><span class="info-val"
                            id="dStatus">-</span></div>
                </div>
                <button onclick="closeDetail()" class="btn-kembali"><i class="fa-solid fa-arrow-left"></i> KEMBALI KE
                    LIST</button>
            </div>
        </div>
    </div>
    <!-- SIDEBAR -->
    <?php include '../includes/sidebar.php'; ?>

    <!-- MAIN CONTENT -->
    <main class="main">

        <?php
        $topbar_title = 'Kelola Data Karyawan';
        $topbar_breadcrumb = 'Karyawan';
        include '../includes/topbar.php';
        ?>

        <div class="content">
            <!-- PAGE HEADER -->
            <div class="page-header">
                <div>
                    <div class="page-title-tag"></div>
                    <div class="page-title">Daftar Karyawan</div>
                </div>
                <div class="stat-chips">
                    <div class="stat-chip chip-blue"><i class="fa-solid fa-user-tie"></i> TOTAL <span class="chip-val"
                            id="stat-total"><?= $total_rows ?></span></div>
                    <div class="stat-chip chip-green"><i class="fa-solid fa-users"></i> AKTIF <span class="chip-val"
                            id="stat-aktif"><?= $total_aktif ?></span></div>
                    <div class="stat-chip chip-red"><i class="fa-solid fa-briefcase"></i> JABATAN <span
                            class="chip-val">2</span></div>
                </div>
            </div>

            <!-- ACTION BAR -->
            <div class="action-bar">
                <!-- Sisi Kiri: Kotak Pencarian Terpadu -->
                <div class="search-box">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="src" placeholder="Cari karyawan... (Tekan Enter)"
                        onkeypress="handleSearch(event)" value="">
                    <button type="button" onclick="clearSearch()" class="btn-clear-search" id="btnClearSearch"
                        style="display: none;">
                        <i class="fa-solid fa-circle-xmark"></i>
                    </button>
                </div>
                <div style="display: flex; gap: 12px; align-items: center;">
                    <div class="filter-dropdown-wrap">
                        <button class="btn-filter" id="btnFilterToggle"><i class="fa-solid fa-filter"></i> Filter <i
                                class="fa-solid fa-chevron-down arrow-icon"></i></button>
                        <div class="filter-card" id="filterCard">
                            <h4>Filter Data</h4>
                            <div class="filter-group">
                                <label>Urut Berdasarkan</label>
                                <select id="filterSortBy" class="filter-input">
                                    <option value="Nama_Karyawan">Nama Lengkap</option>
                                    <option value="Jenis_Kelamin">Jenis Kelamin</option>
                                    <option value="Jabatan">Jabatan</option>
                                    <option value="Status">Status</option>
                                    <!-- Menambahkan pilihan urut berdasarkan Umur -->
                                    <option value="Umur">Umur</option>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label>Jabatan</label>
                                <select id="filterJabatan" class="filter-input">
                                    <option value="0">Semua Jabatan</option>
                                    <option value="2">Manajer</option>
                                    <option value="1">Karyawan</option>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label>Jenis Kelamin</label>
                                <select id="filterJK" class="filter-input">
                                    <option value="-1">Semua</option>
                                    <option value="1">Laki-laki</option>
                                    <option value="0">Perempuan</option>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label>Kategori Umur</label>
                                <select id="filterUmur" class="filter-input">
                                    <option value="all">Semua Usia</option>
                                    <option value="muda">Muda (< 25 Tahun)</option>
                                    <option value="produktif">Produktif (25 - 40 Tahun)</option>
                                    <option value="senior">Senior (> 40 Tahun)</option>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label>Status</label>
                                <select id="filterStatus" class="filter-input">
                                    <option value="-1">Semua</option>
                                    <option value="1">Aktif</option>
                                    <option value="0">Nonaktif</option>
                                </select>
                            </div>
                            <div class="filter-buttons">
                                <button type="button" class="btn-filter-reset" onclick="resetFilters()"><i
                                        class="fa-solid fa-rotate-left"></i> Reset</button>
                                <button type="button" class="btn-filter-apply" onclick="applyFilters()"><i
                                        class="fa-solid fa-check"></i> Terapkan</button>
                            </div>
                        </div>
                    </div>
                    <button class="btn-add" onclick="showAddForm()"><i class="fa-solid fa-plus"></i>Tambah</button>
                </div>
            </div>

            <!-- TABLE CARD -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fa-solid fa-user-tie"></i> Data Karyawan</div>
                    <span class="card-badge" id="header-badge">0 total</span>
                </div>
                <div class="table-wrap">
                    <table class="data-table" id="tbl">
                        <thead>
                            <tr>
                                <th style="text-align: center; width: 70px;">No</th>
                                <!-- Rata Kiri -->
                                <th style="text-align: left;">Nama Lengkap</th>
                                <th style="text-align: left;">Jenis Kelamin</th>
                                <th style="text-align: left;">Jabatan</th>
                                <!-- Kolom Umur baru (Rata Tengah) -->
                                <th style="text-align: center; width: 80px;">Umur</th>
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
    <script>
        // State management untuk Filter, Search & Pagination
        let currentPage = 1;
        let currentSort = 'Nama_Karyawan';
        let currentSortOrder = 'ASC';
        let filterJabatan = 0;
        let filterJK = -1;
        let filterStatus = -1;
        let filterUmur = 'all'; // State Kategori Umur
        let currentSearch = '';

        // ============================================
        // GET DATA TABEL (AJAX REFRESH)
        // ============================================
        async function loadTableData() {
            const url = `karyawan.php?action=get_table_data&page=${currentPage}&sort_by=${currentSort}&sort_order=${currentSortOrder}&filter_jabatan=${filterJabatan}&filter_jk=${filterJK}&filter_status=${filterStatus}&filter_umur=${filterUmur}&src=${encodeURIComponent(currentSearch)}`;
            try {
                const response = await fetch(url);
                const data = await response.json();
                if (data.success) {
                    // Update stats cards
                    document.getElementById('stat-total').textContent = data.stats.total;
                    document.getElementById('stat-aktif').textContent = data.stats.aktif;
                    document.getElementById('header-badge').textContent = `${data.stats.total} total`;

                    // Update Table & Pagination
                    document.querySelector('#tbl tbody').innerHTML = data.table;
                    document.querySelector('.pagination-wrap').innerHTML = data.pagination;

                    // Handle Clear Search visibility
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
        // EVENT FILTER & SEARCH HANDLER
        // ============================================
        function applyFilters() {
            currentSort = document.getElementById('filterSortBy').value;
            filterJabatan = document.getElementById('filterJabatan').value;
            filterJK = document.getElementById('filterJK').value;
            filterStatus = document.getElementById('filterStatus').value;
            filterUmur = document.getElementById('filterUmur').value; // Ambil nilai filter umur
            currentSearch = document.getElementById('src').value.trim();
            currentPage = 1;
            loadTableData();

            // Tutup filter card
            const btnFilterToggle = document.getElementById('btnFilterToggle');
            const filterCard = document.getElementById('filterCard');
            if (btnFilterToggle) btnFilterToggle.classList.remove('active');
            if (filterCard) filterCard.classList.remove('open');
        }

        function handleFilterSubmit(event) {
            event.preventDefault();
            applyFilters();
        }

        // Memicu pencarian ketika menekan tombol Enter (mengaktifkan fungsionalitas search)
        function handleSearch(event) {
            if (event.key === 'Enter') {
                applyFilters();
            }
        }

        function clearSearch() {
            document.getElementById('src').value = '';
            currentSearch = '';
            currentPage = 1;
            loadTableData();
        }

        function resetFilters() {
            document.getElementById('filterSortBy').value = 'Nama_Karyawan';
            document.getElementById('filterJabatan').value = '0';
            document.getElementById('filterJK').value = '-1';
            document.getElementById('filterStatus').value = '-1';
            document.getElementById('filterUmur').value = 'all'; // Reset filter umur
            document.getElementById('src').value = '';

            currentSort = 'Nama_Karyawan';
            filterJabatan = 0;
            filterJK = -1;
            filterStatus = -1;
            filterUmur = 'all';
            currentSearch = '';
            currentPage = 1;
            loadTableData();

            // Tutup filter card
            const btnFilterToggle = document.getElementById('btnFilterToggle');
            const filterCard = document.getElementById('filterCard');
            if (btnFilterToggle) btnFilterToggle.classList.remove('active');
            if (filterCard) filterCard.classList.remove('open');
        }

        // ============================================
        // ALERT HELPER FUNCTIONS
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

        // PERBAIKAN: Fungsi penampil SweetAlert error visual terpadu
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
        // NIK DUPLICATE CHECK (AJAX - REAL TIME)
        // ============================================
        let nikCheckTimeout = null;
        let nikDuplicateExists = false;

        function checkNIKDuplicate() {
            const nikInput = document.getElementById('nik');
            const nikDupMsg = document.getElementById('val-nik-dup');
            const nikFormatMsg = document.getElementById('val-nik');
            const editId = document.getElementById('edit_id_kry').value;

            if (!nikInput || !nikDupMsg) return;

            const nik = nikInput.value.trim();

            if (nikCheckTimeout) {
                clearTimeout(nikCheckTimeout);
            }

            nikDuplicateExists = false;
            nikDupMsg.classList.remove('show');
            nikInput.classList.remove('error');

            if (!nik || nik.length !== 16 || !/^[0-9]{16}$/.test(nik)) {
                return;
            }

            nikCheckTimeout = setTimeout(function () {
                const xhr = new XMLHttpRequest();
                xhr.open('GET', 'karyawan.php?ajax_check_nik=1&nik=' + encodeURIComponent(nik) + '&exclude_id=' + editId, true);
                xhr.onreadystatechange = function () {
                    if (xhr.readyState === 4 && xhr.status === 200) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.exists) {
                                nikDuplicateExists = true;
                                nikInput.classList.add('error');
                                nikDupMsg.classList.add('show');
                                if (nikFormatMsg) nikFormatMsg.classList.remove('show');
                            } else {
                                nikDuplicateExists = false;
                                nikInput.classList.remove('error');
                                nikDupMsg.classList.remove('show');
                            }
                        } catch (e) {
                            console.error('NIK check error:', e);
                        }
                    }
                };
                xhr.send();
            }, 400);
        }

        // ============================================
        // MODAL CONTROLS
        // ============================================
        function openModalDirect(id) {
            document.getElementById(id).classList.add('open');
            document.body.style.overflow = 'hidden';
        }
        function closeModalDirect(id) {
            document.getElementById(id).classList.remove('open');
            document.body.style.overflow = '';
        }

        // ============================================
        // PASSWORD TOGGLE
        // ============================================
        function togglePassword() {
            const passInput = document.getElementById('password');
            const toggleIcon = document.getElementById('togglePass');
            if (passInput.type === 'password') {
                passInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        // ============================================
        // VALIDATION HELPERS
        // ============================================
        function setValidationError(inputEl, errorEl, message) {
            if (!inputEl || !errorEl) return;
            inputEl.classList.add('error');
            errorEl.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> ' + message;
            errorEl.classList.add('show');
        }

        function clearValidationError(inputEl, errorEl) {
            if (!inputEl || !errorEl) return;
            inputEl.classList.remove('error');
            errorEl.classList.remove('show');
        }

        // ============================================
        // DATE VALIDATION
        // ============================================
        function validateDate(input) {
            const selected = new Date(input.value);
            const today = new Date(); today.setHours(0, 0, 0, 0);
            const valMsg = document.getElementById('val-tanggal_lahir');

            if (!input.value) {
                if (valMsg) setValidationError(input, valMsg, 'Tanggal lahir wajib diisi.');
                return false;
            }
            if (selected > today) {
                if (valMsg) setValidationError(input, valMsg, 'Tanggal lahir tidak boleh di masa depan!');
                return false;
            }

            const age = today.getFullYear() - selected.getFullYear();
            if (age < 17) {
                if (valMsg) setValidationError(input, valMsg, 'Karyawan harus berusia minimal 17 tahun!');
                return false;
            }
            if (age > 60) {
                if (valMsg) setValidationError(input, valMsg, 'Usia karyawan maksimal 60 tahun!');
                return false;
            }

            if (valMsg) clearValidationError(input, valMsg);
            return true;
        }

        // ============================================
        // FORM VALIDATION
        // ============================================
        function validateForm(form) {
            let valid = true;

            if (nikDuplicateExists) {
                const nikInput = document.getElementById('nik');
                const nikDupMsg = document.getElementById('val-nik-dup');
                if (nikInput && nikDupMsg) {
                    nikInput.classList.add('error');
                    nikDupMsg.classList.add('show');
                    nikInput.focus();
                }
                valid = false;
            }

            const fields = [
                { id: 'nik', err: 'val-nik', label: 'NIK', required: true, min: 16, max: 16, pattern: /^[0-9]{16}$/ },
                { id: 'nama', err: 'val-nama', label: 'Nama', required: true, min: 3, max: 20, pattern: /^[a-zA-Z\s]+$/ },
                { id: 'username', err: 'val-username', label: 'Username', required: true, min: 3, max: 20, pattern: /^[a-zA-Z0-9\._]+$/, noSpace: true },
                { id: 'password', err: 'val-password', label: 'Password', required: true, min: 8, max: 20, noSpace: true },
                { id: 'email', err: 'val-email', label: 'Email', required: true, email: true },
                { id: 'telp', err: 'val-telp', label: 'Nomor Telepon', required: true, phone: true },
                { id: 'tempat_lahir', err: 'val-tempat_lahir', label: 'Tempat Lahir', required: true, min: 3, max: 50, pattern: /^[a-zA-Z\s]+$/ },
                { id: 'alamat', err: 'val-alamat', label: 'Alamat', required: true, min: 10, max: 100 }
            ];

            fields.forEach(f => {
                const el = document.getElementById(f.id);
                const err = document.getElementById(f.err);
                if (!el || !err) return;

                const val = el.value.trim();

                if (f.required && val === '') {
                    setValidationError(el, err, f.label + ' wajib diisi.');
                    valid = false;
                    return;
                }

                // PERBAIKAN: Validasi khusus Alamat Lengkap tidak boleh hanya berisi angka
                if (f.id === 'alamat' && val !== '') {
                    const hasLetter = /[a-zA-Z]/.test(val); // Memeriksa keberadaan huruf
                    if (!hasLetter) {
                        setValidationError(el, err, 'Alamat lengkap tidak boleh hanya berisi angka.');
                        valid = false;
                        return;
                    }
                }

                if (f.min && val.length < f.min) {
                    setValidationError(el, err, f.label + ' minimal ' + f.min + ' karakter.');
                    valid = false;
                    return;
                }

                if (f.max && val.length > f.max) {
                    setValidationError(el, err, f.label + ' maksimal ' + f.max + ' karakter.');
                    valid = false;
                    return;
                }

                if (f.noSpace && val.includes(' ')) {
                    setValidationError(el, err, f.label + ' tidak boleh mengandung spasi.');
                    valid = false;
                    return;
                }

                if (f.pattern && !f.pattern.test(val)) {
                    setValidationError(el, err, f.label + ' format tidak valid.');
                    valid = false;
                    return;
                }

                if (f.email) {
                    // Pola regex khusus yang mewajibkan domain akhiran @hoopball.com (tidak sensitif huruf besar/kecil)
                    const emailPattern = /^[a-zA-Z0-9._%+-]+@hoopball\.com$/i;
                    if (!emailPattern.test(val)) {
                        setValidationError(el, err, 'Format email wajib menggunakan akhiran @hoopball.com');
                        valid = false;
                        return;
                    }
                }

                if (f.phone) {
                    const phonePattern = /^08[0-9]{8,13}$/;
                    if (!phonePattern.test(val)) {
                        setValidationError(el, err, 'Nomor telepon harus diawali 08 dan 10-15 digit.');
                        valid = false;
                        return;
                    }
                }

                clearValidationError(el, err);
            });

            const jabatanEl = document.getElementById('jabatan');
            const jabatanErr = document.getElementById('val-jabatan');
            if (jabatanEl && jabatanErr) {
                if (!jabatanEl.value) {
                    setValidationError(jabatanEl, jabatanErr, 'Jabatan wajib dipilih.');
                    valid = false;
                } else {
                    clearValidationError(jabatanEl, jabatanErr);
                }
            }

            const tglLahir = document.getElementById('tanggal_lahir');
            if (tglLahir && !validateDate(tglLahir)) {
                valid = false;
            }

            // PERBAIKAN: Validasi harus memilih salah satu jenis kelamin (Radio)
            const jkMale = document.querySelector('input[name="jk"][value="1"]');
            const jkFemale = document.querySelector('input[name="jk"][value="0"]');
            const jkErr = document.getElementById('val-jk');

            if (jkMale && jkFemale && jkErr) {
                if (!jkMale.checked && !jkFemale.checked) {
                    document.querySelectorAll('.radio-custom-box').forEach(box => box.classList.add('error'));
                    jkErr.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Jenis kelamin wajib dipilih.';
                    jkErr.classList.add('show');
                    valid = false;
                } else {
                    document.querySelectorAll('.radio-custom-box').forEach(box => box.classList.remove('error'));
                    jkErr.classList.remove('show');
                }
            }

            return valid;
        }

        // ============================================
        // AJAX FORM SUBMIT (TAMBAH / EDIT KARYAWAN)
        // ============================================
        async function handleFormSubmit(event) {
            event.preventDefault();
            if (!validateForm(event.target)) return;

            const form = document.getElementById('formKaryawan');
            const formData = new FormData(form);
            formData.append('action', 'save');

            Swal.fire({
                title: 'Memproses...',
                text: 'Menyimpan data karyawan',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });

            try {
                const response = await fetch('karyawan.php', { method: 'POST', body: formData });
                const res = await response.json();
                if (res.success) {
                    closeModalDirect('modal');
                    showSuccess('Berhasil!', res.msg);
                    loadTableData();
                } else {
                    showError('Gagal!', res.msg);
                }
            } catch (error) {
                showError('Gagal!', 'Terjadi kesalahan sistem.');
            }
        }

        // ============================================
        // AMBIL DATA EDIT KE MODAL (AJAX)
        // ============================================
        async function showEditForm(id) {
            Swal.fire({
                title: 'Memuat...',
                text: 'Mengambil data karyawan...',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });
            try {
                const response = await fetch(`karyawan.php?action=get_detail&id=${id}`);
                const res = await response.json();
                if (res.success) {
                    Swal.close();
                    const d = res.data;

                    // Reset Form & Error messages
                    document.getElementById('formKaryawan').reset();
                    document.querySelectorAll('.val-msg').forEach(el => el.classList.remove('show'));
                    document.querySelectorAll('.modal-input').forEach(el => el.classList.remove('error'));
                    document.querySelectorAll('.radio-custom-box').forEach(box => box.classList.remove('error'));

                    // Set values
                    document.getElementById('edit_id_kry').value = d.ID_Karyawan;
                    document.getElementById('nik').value = d.NIK;
                    document.getElementById('nama').value = d.Nama_Karyawan;
                    document.getElementById('username').value = d.Username;
                    document.getElementById('password').value = d.Kata_Sandi;
                    document.getElementById('email').value = d.Email;
                    document.getElementById('telp').value = d.No_Telepon;
                    document.getElementById('tempat_lahir').value = d.Tempat_Lahir;
                    document.getElementById('tanggal_lahir').value = d.Tanggal_Lahir_Formatted;
                    document.getElementById('alamat').value = d.Alamat;
                    document.getElementById('jabatan').value = d.Jabatan;

                    // Set Jenis Kelamin Radio
                    const rMale = document.querySelector('input[name="jk"][value="1"]');
                    const rFemale = document.querySelector('input[name="jk"][value="0"]');
                    if (d.Jenis_Kelamin == '1' && rMale) rMale.checked = true;
                    if (d.Jenis_Kelamin == '0' && rFemale) rFemale.checked = true;

                    // Enable Status Dropdown untuk Mode Edit
                    const statusSelect = document.getElementById('status');
                    if (statusSelect) {
                        statusSelect.style.pointerEvents = 'auto';
                        statusSelect.style.backgroundColor = '#FAFAFA';
                        statusSelect.value = d.Status;
                    }

                    // Ganti Title Modal
                    document.querySelector('.modal-title').textContent = 'Edit Data Staf';
                    document.querySelector('.modal-sub').textContent = 'Perbarui informasi karyawan yang ada';
                    document.querySelector('.btn-submit').innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Simpan Perubahan';

                    openModalDirect('modal');
                } else {
                    showError('Gagal!', res.msg);
                }
            } catch (error) {
                console.error("Gagal load edit form:", error);
                showError('Error', 'Gagal memuat form edit.');
            }
        }

        // ============================================
        // RESET FORM TAMBAH BARU (AJAX)
        // ============================================
        function showAddForm() {
            document.getElementById('formKaryawan').reset();
            document.getElementById('edit_id_kry').value = '0';
            document.querySelectorAll('.val-msg').forEach(el => el.classList.remove('show'));
            document.querySelectorAll('.modal-input').forEach(el => el.classList.remove('error'));
            document.querySelectorAll('.radio-custom-box').forEach(box => box.classList.remove('error'));

            // Status diset aktif & dibekukan untuk pendaftaran baru
            const statusSelect = document.getElementById('status');
            if (statusSelect) {
                statusSelect.value = '1';
                statusSelect.style.pointerEvents = 'none';
                statusSelect.style.backgroundColor = 'var(--border-lt)';
            }

            document.querySelector('.modal-title').textContent = 'Tambah Karyawan Baru';
            document.querySelector('.modal-sub').textContent = 'Daftarkan karyawan baru ke dalam sistem';
            document.querySelector('.btn-submit').innerHTML = '<i class="fa-solid fa-user-plus"></i> Daftarkan Karyawan';

            openModalDirect('modal');
        }

        // ============================================
        // DELETE CONFIRMATION (AJAX)
        // ============================================
        function confirmDelete(id, nama) {
            Swal.fire({
                title: 'Hapus Karyawan?',
                html: 'Anda akan menghapus karyawan <strong style="color:var(--orange);">' + nama + '</strong><br><span style="font-size:12px;color:var(--muted);">Data akan dihapus secara Permanen</span>',
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
                    executeDelete(id);
                }
            });
        }

        async function executeDelete(id) {
            Swal.fire({
                title: 'Memproses...',
                text: 'Menghapus karyawan...',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });
            try {
                const response = await fetch(`karyawan.php?action=delete&id=${id}`);
                const res = await response.json();
                if (res.success) {
                    showSuccess('Terhapus!', res.msg);
                    loadTableData();
                } else {
                    showError('Gagal!', res.msg);
                }
            } catch (error) {
                showError('Gagal!', 'Terjadi kesalahan sistem.');
            }
        }

        // ============================================
        // TOGGLE STATUS (AJAX)
        // ============================================
        async function confirmToggle(id, nama, isCurrentlyActive, checkbox) {
            const action = isCurrentlyActive ? 'nonaktifkan' : 'aktifkan';
            const iconType = isCurrentlyActive ? 'warning' : 'question';

            Swal.fire({
                title: 'Konfirmasi Perubahan Status',
                text: 'Apakah Anda yakin ingin ' + action + ' karyawan ' + nama + '?',
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
                    executeToggle(id);
                } else {
                    checkbox.checked = isCurrentlyActive;
                }
            });
        }

        async function executeToggle(id) {
            Swal.fire({
                title: 'Memproses...',
                text: 'Mengubah status...',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });
            try {
                const response = await fetch(`karyawan.php?action=toggle_status&id=${id}`);
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

        // ============ DETAIL AJAK KARYAWAN ============
        async function showDetail(id) {
            try {
                const response = await fetch(`karyawan.php?action=get_detail&id=${id}`);
                const rawText = await response.text();
                let res;
                try {
                    res = JSON.parse(rawText);
                } catch (err) {
                    console.error("RAW:", rawText);
                    showError('Error Server', 'Respon server tidak valid.');
                    return;
                }

                if (res.success) {
                    const d = res.data;
                    const mapJK = { '1': 'LAKI-LAKI', '0': 'PEREMPUAN' };
                    const mapJabatan = { '1': 'KARYAWAN', '2': 'MANAJER' };
                    const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

                    document.getElementById('dNIK').textContent = d.NIK;
                    document.getElementById('dNameHeader').textContent = d.Nama_Karyawan;
                    document.getElementById('dNama').textContent = d.Nama_Karyawan;
                    document.getElementById('dUsername').textContent = d.Username || '-';
                    document.getElementById('dPassword').textContent = d.Kata_Sandi ? '••••••••' : '-';
                    document.getElementById('dEmail').textContent = d.Email || '-';
                    document.getElementById('dTempatLahir').textContent = d.Tempat_Lahir || '-';
                    document.getElementById('dAlamat').textContent = d.Alamat || '-';
                    document.getElementById('dTelp').textContent = d.No_Telepon || '-';

                    if (d.Tanggal_Lahir_Formatted) {
                        const dateParts = d.Tanggal_Lahir_Formatted.split('-');
                        if (dateParts.length === 3) {
                            const year = parseInt(dateParts[0]);
                            const month = parseInt(dateParts[1]) - 1;
                            const day = parseInt(dateParts[2]);
                            document.getElementById('dTanggalLahir').textContent = day + ' ' + months[month] + ' ' + year;
                        } else {
                            document.getElementById('dTanggalLahir').textContent = d.Tanggal_Lahir_Formatted;
                        }
                    } else {
                        document.getElementById('dTanggalLahir').textContent = '-';
                    }

                    const jkColor = d.Jenis_Kelamin == '1' ? '#3B82F6' : '#EC4899';
                    const jkBg = d.Jenis_Kelamin == '1' ? '#EFF6FF' : '#FDF2F8';
                    document.getElementById('dJK').innerHTML = `<span class="jk-badge" style="background: ${jkBg}; color: ${jkColor}; font-weight:800; padding:5px 10px; border-radius:8px;">${mapJK[d.Jenis_Kelamin] || '-'}</span>`;

                    const jabColor = d.Jabatan == '2' ? '#FF4500' : '#4338CA';
                    const jabBg = d.Jabatan == '2' ? 'rgba(255,69,0,0.1)' : '#EEF2FF';
                    document.getElementById('dJabatan').innerHTML = `<span class="jabatan-badge" style="background: ${jabBg}; color: ${jabColor};">${mapJabatan[d.Jabatan] || 'TIDAK DIKETAHUI'}</span>`;

                    const isAktif = (parseInt(d.Status) === 1);
                    document.getElementById('dStatus').innerHTML = `
                <span class="status-pill ${isAktif ? 'sp-active' : 'sp-inactive'}" style="background: ${isAktif ? 'var(--green-lt)' : 'var(--red-lt)'}; color: ${isAktif ? 'var(--green)' : 'var(--red)'}; padding: 6px 12px; border-radius: 8px; font-size: 11px; font-weight: 800; display: inline-block;">
                    <i class="fa-solid ${isAktif ? 'fa-circle-check' : 'fa-circle-xmark'}"></i> ${isAktif ? 'AKTIF' : 'NONAKTIF'}
                </span>`;

                    document.getElementById('detailModal').classList.add('open');
                    document.body.style.overflow = 'hidden';
                } else {
                    showError('Gagal!', res.msg);
                }
            } catch (error) {
                console.error("Gagal render detail:", error);
                showError('Error', 'Gagal memuat detail karyawan.');
            }
        }

        function closeDetail(e) {
            if (e && e.target !== document.getElementById('detailModal')) return;
            document.getElementById('detailModal').classList.remove('open');
            document.body.style.overflow = '';
        }

        // ============================================
        // REAL-TIME INPUT FILTERING
        // ============================================
        document.addEventListener('DOMContentLoaded', () => {
            const namaInput = document.getElementById('nama');
            const tmpLahirInput = document.getElementById('tempat_lahir');
            const telpInput = document.getElementById('telp');
            const nikInput = document.getElementById('nik');

            if (namaInput) {
                namaInput.addEventListener('input', () => {
                    namaInput.value = namaInput.value.replace(/[^a-zA-Z\s]/g, '');
                });
            }

            if (tmpLahirInput) {
                tmpLahirInput.addEventListener('input', () => {
                    tmpLahirInput.value = tmpLahirInput.value.replace(/[^a-zA-Z\s]/g, '');
                });
            }

            if (telpInput) {
                telpInput.addEventListener('input', () => {
                    telpInput.value = telpInput.value.replace(/[^0-9]/g, '');
                });
            }

            if (nikInput) {
                nikInput.addEventListener('input', () => {
                    nikInput.value = nikInput.value.replace(/[^0-9]/g, '').substring(0, 16);
                    if (nikInput.value.length === 16) {
                        checkNIKDuplicate();
                    } else {
                        const nikDupMsg = document.getElementById('val-nik-dup');
                        if (nikDupMsg) nikDupMsg.classList.remove('show');
                        nikInput.classList.remove('error');
                        nikDuplicateExists = false;
                    }
                });
            }

            const fields = [
                { el: document.getElementById('nama'), err: document.getElementById('val-nama') },
                { el: document.getElementById('username'), err: document.getElementById('val-username') },
                { el: document.getElementById('password'), err: document.getElementById('val-password') },
                { el: document.getElementById('email'), err: document.getElementById('val-email') },
                { el: document.getElementById('telp'), err: document.getElementById('val-telp') },
                { el: document.getElementById('nik'), err: document.getElementById('val-nik') },
                { el: document.getElementById('tempat_lahir'), err: document.getElementById('val-tempat_lahir') },
                { el: document.getElementById('alamat'), err: document.getElementById('val-alamat') }
            ];

            fields.forEach(field => {
                if (field.el) {
                    field.el.addEventListener('input', () => {
                        clearValidationError(field.el, field.err);
                    });
                }
            });

            const tglLahirField = document.getElementById('tanggal_lahir');
            if (tglLahirField) {
                tglLahirField.addEventListener('change', function () {
                    validateDate(this);
                });
            }

            // PERBAIKAN: Hilangkan indikasi error visual langsung setelah radio jenis kelamin dipilih
            const jkRadios = document.querySelectorAll('input[name="jk"]');
            jkRadios.forEach(radio => {
                radio.addEventListener('change', () => {
                    const jkErr = document.getElementById('val-jk');
                    if (jkErr) jkErr.classList.remove('show');
                    document.querySelectorAll('.radio-custom-box').forEach(box => box.classList.remove('error'));
                });
            });
        });

        // ============================================
        // FILTER DROPDOWN
        // ============================================
        const btnFilterToggle = document.getElementById('btnFilterToggle');
        const filterCard = document.getElementById('filterCard');
        if (btnFilterToggle && filterCard) {
            btnFilterToggle.addEventListener('click', function (e) {
                e.stopPropagation();
                this.classList.toggle('active');
                filterCard.classList.toggle('open');
            });
            filterCard.addEventListener('click', function (e) { e.stopPropagation(); });
            document.addEventListener('click', function () {
                btnFilterToggle.classList.remove('active');
                filterCard.classList.remove('open');
            });
        }

        // ============================================
        // KEYBOARD SHORTCUTS
        // ============================================
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeDetail();
                if (btnFilterToggle) btnFilterToggle.classList.remove('active');
                if (filterCard) filterCard.classList.remove('open');
            }
        });

        window.Swal = Swal.mixin({
            scrollbarPadding: false
        });

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