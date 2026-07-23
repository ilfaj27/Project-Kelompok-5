<?php
session_start();
require_once '../login/auth_check.php';
date_default_timezone_set("Asia/Jakarta");
$path_prefix = "../";
include '../includes/config.php';

if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'karyawan' && $_SESSION['role'] !== 'pemilik')) {
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

$role = $_SESSION['role'];
$nama = substr($_SESSION['nama'] ?? 'USER', 0, 50);

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

function safeQuery($conn, $sql, $params = []) {
    $stmt = empty($params) ? sqlsrv_query($conn, $sql) : sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        $errors = sqlsrv_errors(SQLSRV_ERR_ALL);
        error_log("[ALAT-ERROR] SQL Error: " . print_r($errors, true));
        error_log("[ALAT-ERROR] Query: " . $sql);
        return false;
    }
    return $stmt;
}

function safeFetch($stmt) {
    if ($stmt === false || $stmt === null) return false;
    return sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
}

function getLastSqlError($conn) {
    $errors = sqlsrv_errors(SQLSRV_ERR_ALL);
    if (!empty($errors) && isset($errors[0]['message'])) {
        return $errors[0]['message'];
    }
    return 'Unknown database error';
}

function getPhotoUrl($photo_path) {
    if (empty($photo_path)) return '';
    $path = str_replace('../', '', $photo_path);
    $path = ltrim($path, '/');
    return '../' . $path;
}

function getUploadDirectory() {
    $upload_dir = '../asset/image/';
    if (!is_dir($upload_dir)) {
        @mkdir($upload_dir, 0755, true);
    }
    return $upload_dir;
}

function processPhotoUpload($file, $edit_photo_path = '') {
    $upload_dir = getUploadDirectory();
    if (!isset($file) || empty($file['name'])) {
        return $edit_photo_path;
    }
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_ext = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    if (!in_array($file_ext, $allowed_ext)) {
        return $edit_photo_path;
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        return $edit_photo_path;
    }
    $new_file_name = 'alat_' . time() . '_' . uniqid() . '.' . $file_ext;
    $target_path = $upload_dir . $new_file_name;
    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        return 'asset/image/' . $new_file_name;
    }
    return $edit_photo_path;
}

// === KONFIGURASI KATEGORI & UKURAN ===
$KATEGORI_SIZES = [
    'Baju'        => ['S', 'M', 'L', 'XL', 'XXL'],
    'Celana'      => ['S', 'M', 'L', 'XL', 'XXL'],
    'Bola Basket' => ['Size 5', 'Size 6', 'Size 7'],
    'Sepatu'      => ['38', '39', '40', '41', '42', '43', '44', '45'],
    'Headband'    => ['All Size'],
    'Kaos Kaki'   => ['All Size'],
    'Lainnya'     => ['All Size'],
];

function rupiah($n) { return 'Rp ' . number_format($n, 0, ',', '.'); }

function getAlatSizes($conn, $id_alat) {
    $sizes = [];
    $r = safeQuery($conn, "EXEC SP_AlatSize_SelectByAlat @ID_Alat = ?", [intval($id_alat)]);
    if ($r) {
        while ($row = sqlsrv_fetch_array($r, SQLSRV_FETCH_ASSOC)) {
            $sizes[$row['Ukuran']] = intval($row['Stok']);
        }
    }
    return $sizes;
}

// ── PROSES AJAX REQUESTS ──
if ($is_real_ajax) {
    header('Content-Type: application/json');
    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    // Action: Ambil Detail / Edit Data
    if ($action === 'get_detail') {
        $id = intval($_GET['id'] ?? 0);
        $r = safeQuery($conn, "EXEC SP_Alat_Select @ID_Alat = ?", [$id]);
        if ($r && $data = safeFetch($r)) {
            $sizes = getAlatSizes($conn, $id);
            echo json_encode([
                'success' => true,
                'data' => $data,
                'sizes' => $sizes,
                'photo_url' => getPhotoUrl($data['Photo_Alat'] ?? '')
            ]);
        } else {
            echo json_encode(['success' => false, 'msg' => 'Data alat tidak ditemukan.']);
        }
        exit();
    }

    // Action: Simpan Data (Insert / Update)
    if ($action === 'save') {
        $id = isset($_POST['id_alat']) ? intval($_POST['id_alat']) : 0;
        $nama_alat = trim($_POST['nama_alat'] ?? '');
        $kategori = trim($_POST['kategori'] ?? '');
        $stok_size_raw = isset($_POST['stok_size']) && is_array($_POST['stok_size']) ? $_POST['stok_size'] : [];
        $harga_jual_raw = preg_replace('/[^0-9]/', '', trim($_POST['harga_jual'] ?? ''));
        $harga_beli_raw = preg_replace('/[^0-9]/', '', trim($_POST['harga_beli'] ?? ''));
        $edit_mode = isset($_POST['edit_mode']) && $_POST['edit_mode'] == '1';
        $edit_photo_path = isset($_POST['edit_photo_path']) ? trim($_POST['edit_photo_path']) : '';

        $errors = [];
        if ($nama_alat === '') {
            $errors[] = 'Nama alat wajib diisi.';
        } elseif (strlen($nama_alat) > 25) {
            $errors[] = 'Nama alat maksimal 25 karakter.';
        }
        if ($kategori === '' || !array_key_exists($kategori, $KATEGORI_SIZES)) {
            $errors[] = 'Kategori alat wajib dipilih.';
        }

        $sizes_clean = [];
        $stok_total = 0;
        if ($kategori !== '' && array_key_exists($kategori, $KATEGORI_SIZES)) {
            foreach ($KATEGORI_SIZES[$kategori] as $ukuran) {
                $val = trim($stok_size_raw[$ukuran] ?? '');
                if ($val === '') $val = '0';
                if (!preg_match('/^[0-9]+$/', $val)) {
                    $errors[] = 'Stok ukuran ' . $ukuran . ' harus berupa angka.';
                    continue;
                }
                $val_num = intval($val);
                if ($val_num > 9999) {
                    $errors[] = 'Stok ukuran ' . $ukuran . ' maksimal 9999.';
                    continue;
                }
                $sizes_clean[$ukuran] = $val_num;
                $stok_total += $val_num;
            }
            if ($stok_total < 10) {
                $errors[] = 'Total stok dari semua ukuran minimal harus 10 pcs.';
            } elseif ($stok_total > 9999) {
                $errors[] = 'Total stok semua ukuran maksimal 9999.';
            }
        }

        if ($harga_beli_raw === '' || !is_numeric($harga_beli_raw)) {
            $errors[] = 'Harga beli harus berupa angka.';
        } else if (floatval($harga_beli_raw) < 5000) {
            $errors[] = 'Harga beli minimal Rp 5.000.';
        }
        
        if ($harga_jual_raw === '' || !is_numeric($harga_jual_raw)) {
            $errors[] = 'Harga jual harus berupa angka.';
        }

        if ($harga_beli_raw !== '' && $harga_jual_raw !== '' && is_numeric($harga_beli_raw) && is_numeric($harga_jual_raw)) {
            if (floatval($harga_jual_raw) < floatval($harga_beli_raw)) {
                $errors[] = 'Harga jual tidak boleh lebih kecil dari harga beli.';
            }
        }

        if (empty($errors)) {
            $sql_check = "SELECT TOP 1 ID_Alat FROM Alat WHERE Nama_Alat = ? AND ID_Alat != ? AND Is_Deleted = 0";
            $q_check = safeQuery($conn, $sql_check, [$nama_alat, $id]);
            if ($q_check && safeFetch($q_check)) {
                $errors[] = 'Nama alat "' . $nama_alat . '" sudah terdaftar! Silakan gunakan nama lain.';
            }
        }

        $photo_alat = processPhotoUpload($_FILES['photo_alat'] ?? null, $edit_photo_path);
        if (empty($photo_alat)) {
            $errors[] = 'Foto alat wajib diupload.';
        }

        if (!empty($errors)) {
            echo json_encode(['success' => false, 'msg' => implode(' | ', $errors)]);
            exit();
        }

        $stok = $stok_total; 
        $harga_beli = number_format(floatval($harga_beli_raw), 2, '.', '');
        $harga_jual = number_format(floatval($harga_jual_raw), 2, '.', '');

        if ($edit_mode && $id > 0) {
            $sql = "EXEC SP_Alat_Update @ID_Alat=?, @Nama_Alat=?, @Stok=?, @Harga_Beli=?, @Harga_Jual=?, @Photo_Alat=?, @Modified_By=?, @Kategori=?";
            $params = [$id, $nama_alat, $stok, $harga_beli, $harga_jual, $photo_alat, $nama, $kategori];
        } else {
            $sql = "EXEC SP_Alat_Insert @Nama_Alat=?, @Stok=?, @Harga_Beli=?, @Harga_Jual=?, @Photo_Alat=?, @Status=1, @Created_By=?, @Kategori=?";
            $params = [$nama_alat, $stok, $harga_beli, $harga_jual, $photo_alat, $nama, $kategori];
        }

        $result = safeQuery($conn, $sql, $params);
        if ($result !== false) {
            $target_id = $id;
            if (!$edit_mode) {
                $row_new = safeFetch($result); 
                if ($row_new && isset($row_new['New_ID_Alat'])) {
                    $target_id = intval($row_new['New_ID_Alat']);
                }
                if ($target_id <= 0) {
                    $q_fid = safeQuery($conn, "SELECT MAX(ID_Alat) AS mid FROM Alat WHERE Nama_Alat = ? AND Is_Deleted = 0", [$nama_alat]);
                    if ($q_fid) {
                        $row_fid = safeFetch($q_fid);
                        if ($row_fid) $target_id = intval($row_fid['mid']);
                    }
                }
            }

            if ($target_id > 0) {
                safeQuery($conn, "EXEC SP_AlatSize_DeleteByAlat @ID_Alat = ?", [$target_id]);
                foreach ($sizes_clean as $ukuran => $stok_ukuran) {
                    if ($stok_ukuran > 0) {
                        safeQuery($conn, "EXEC SP_AlatSize_Insert @ID_Alat = ?, @Ukuran = ?, @Stok = ?", [$target_id, $ukuran, $stok_ukuran]);
                    }
                }
            }

            $msg = $edit_mode ? 'Data alat berhasil diperbarui!' : 'Alat baru berhasil ditambahkan!';
            echo json_encode(['success' => true, 'msg' => $msg]);
        } else {
            echo json_encode(['success' => false, 'msg' => 'Gagal menyimpan data: ' . getLastSqlError($conn)]);
        }
        exit();
    }

    // Action: Toggle Status
    if ($action === 'toggle') {
        $id = intval($_GET['id'] ?? 0);
        $current_status = intval($_GET['status'] ?? 0);
        $s_baru = ($current_status == 1) ? 0 : 1;
        $result = safeQuery($conn, "EXEC SP_Alat_Update @ID_Alat = ?, @Status = ?, @Modified_By = ?", [$id, $s_baru, $nama]);
        if ($result !== false) {
            $msg = ($s_baru == 1) ? 'Alat berhasil diaktifkan!' : 'Alat berhasil dinonaktifkan!';
            echo json_encode(['success' => true, 'msg' => $msg]);
        } else {
            echo json_encode(['success' => false, 'msg' => 'Gagal mengubah status alat.']);
        }
        exit();
    }

    // Action: Delete Alat
    if ($action === 'delete') {
        $id = intval($_GET['id'] ?? 0);
        $result = safeQuery($conn, "EXEC SP_Alat_Delete @ID_Alat=?, @Deleted_By=?", [$id, $nama]);
        if ($result !== false) {
            echo json_encode(['success' => true, 'msg' => 'Alat berhasil dihapus!']);
        } else {
            echo json_encode(['success' => false, 'msg' => 'Gagal menghapus alat: ' . getLastSqlError($conn)]);
        }
        exit();
    }

    // Action: Fetch Data Grid & Pagination
    if ($action === 'get_grid_data') {
        // Konversi string kosong menjadi NULL agar Stored Procedure SQL Server berfungsi dengan baik
        $search_raw = isset($_GET['src']) ? trim($_GET['src']) : '';
        $search = ($search_raw !== '') ? $search_raw : null;

        $kategori_raw = isset($_GET['f_kategori']) ? trim($_GET['f_kategori']) : '';
        $f_kategori = ($kategori_raw !== '') ? $kategori_raw : null;

        $f_status = (isset($_GET['f_status']) && $_GET['f_status'] !== '') ? intval($_GET['f_status']) : null;
        $f_sort = $_GET['f_sort'] ?? 'nama_asc';

        // Stats
        $aktif_count = $nonaktif_count = $total_alat = 0;
        $q_stats = safeQuery($conn, "EXEC SP_Alat_CountByStatus");
        if ($q_stats && $row_stats = safeFetch($q_stats)) {
            $aktif_count    = $row_stats['AktifCount']    ?? 0;
            $nonaktif_count = $row_stats['NonaktifCount'] ?? 0;
            $total_alat     = $row_stats['TotalCount']    ?? 0;
        }

        // Pagination (DIPERBARUI: Limit 10 alat per halaman sesuai permintaan bos)
        $limit = 10;
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $total_data = 0;
        $count_res = safeQuery($conn, "EXEC SP_Alat_Count @StatusFilter = ?, @KategoriFilter = ?, @Search = ?", [$f_status, $f_kategori, $search]);
        if ($count_res && $row = safeFetch($count_res)) {
            $total_data = $row['TotalCount'] ?? 0;
        }

        $total_pages = max(1, ceil($total_data / $limit));
        $page = min($page, $total_pages);

        $query = safeQuery($conn, "EXEC SP_Alat_SelectFiltered @StatusFilter = ?, @KategoriFilter = ?, @Search = ?, @SortBy = ?, @PageNumber = ?, @PageSize = ?", [$f_status, $f_kategori, $search, $f_sort, $page, $limit]);

        // Render Cards Grid
        ob_start();
        $has_data = false;
        if ($query):
            while ($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)):
                $has_data = true;
                $photo_url = getPhotoUrl($row['Photo_Alat'] ?? '');
                $is_aktif = intval($row['Status']) === 1;
        ?>
            <div class="alat-card" id="alat-<?= intval($row['ID_Alat']) ?>">
                <div class="alat-card-photo-wrap" onclick="viewDetail(<?= intval($row['ID_Alat']) ?>)">
                    <div class="alat-card-photo-placeholder">
                        <i class="fa-solid fa-toolbox"></i>
                    </div>
                    <?php if (!empty($photo_url)): ?>
                        <img src="<?= htmlspecialchars($photo_url) ?>"
                             alt="<?= htmlspecialchars($row['Nama_Alat']) ?>"
                             loading="lazy"
                             style="position:absolute;top:0;left:0;width:100%;height:100%;object-fit:cover;z-index:1;"
                             onerror="this.style.display='none';">
                    <?php endif; ?>
                    <span class="alat-card-badge <?= $is_aktif ? 'badge-aktif' : 'badge-nonaktif' ?>" style="z-index:2;">
                        <span class="badge-dot"></span> <?= $is_aktif ? 'AKTIF' : 'NONAKTIF' ?>
                    </span>
                    <div class="alat-card-actions" style="z-index:3;">
                        <button type="button" onclick="event.stopPropagation(); viewDetail(<?= intval($row['ID_Alat']) ?>)" class="alat-card-action-btn ac-btn-view" title="Lihat Detail"><i class="fa-solid fa-eye"></i></button>
                        <button type="button" onclick="event.stopPropagation(); openEditModal(<?= intval($row['ID_Alat']) ?>)" class="alat-card-action-btn ac-btn-edit" title="Edit Alat"><i class="fa-solid fa-pen-to-square"></i></button>
                        <button type="button" onclick="event.stopPropagation(); confirmDelete(<?= intval($row['ID_Alat']) ?>, '<?= htmlspecialchars($row['Nama_Alat'], ENT_QUOTES) ?>')" class="alat-card-action-btn ac-btn-delete" title="Hapus Alat"><i class="fa-solid fa-trash-can"></i></button>
                    </div>
                </div>
                <div class="alat-card-info">
                    <div class="alat-card-cat"><?= htmlspecialchars($row['Kategori'] ?? 'Lainnya') ?></div>
                    <div class="alat-card-name"><?= htmlspecialchars($row['Nama_Alat']) ?></div>
                    <div class="alat-card-price"><?= rupiah($row['Harga_Jual']) ?></div>
                    <div class="alat-card-price-beli" style="font-size:11px;color:var(--muted);font-weight:600;margin-top:-6px;">Modal: <?= rupiah($row['Harga_Beli']) ?></div>
                    <div class="alat-card-meta">
                        <span class="alat-card-stok">
                            <i class="fa-solid fa-boxes-stacked"></i><?= intval($row['Stok']) ?> tersedia
                        </span>
                        <div class="alat-card-toggle">
                            <span class="alat-card-toggle-label"><?= $is_aktif ? 'ON' : 'OFF' ?></span>
                            <label class="toggle-switch" title="<?= $is_aktif ? 'Nonaktifkan' : 'Aktifkan' ?>">
                                <input type="checkbox" <?= $is_aktif ? 'checked' : '' ?>
                                       onchange="confirmToggle('<?= intval($row['ID_Alat']) ?>', '<?= htmlspecialchars($row['Nama_Alat'], ENT_QUOTES) ?>', <?= intval($row['Status']) ?>, event)">
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
                <i class="fa-solid fa-toolbox"></i>
                <?php if(!empty($search_raw)): ?>
                    <div>Pencarian "<?= htmlspecialchars($search_raw) ?>" tidak ditemukan</div>
                    <p>Coba gunakan kata kunci atau filter lain.</p>
                <?php else: ?>
                    <div>Belum ada data alat</div>
                    <p>Klik "+ Tambah" untuk menambahkan alat baru</p>
                <?php endif; ?>
            </div>
        <?php endif;
        $grid_html = ob_get_clean();

        // Render Pagination
        ob_start();
        if ($total_pages > 1): ?>
            <div class="pagination-info">
                Menampilkan <strong><?= (($page-1)*$limit)+1 ?></strong> - <strong><?= min($page*$limit,$total_data) ?></strong> dari <strong><?= $total_data ?></strong> data
            </div>
            <div class="pagination-nav">
                <button onclick="changePage(1)" class="page-btn <?= $page<=1?'disabled':'' ?>"><i class="fa-solid fa-angles-left"></i></button>
                <button onclick="changePage(<?= $page-1 ?>)" class="page-btn <?= $page<=1?'disabled':'' ?>"><i class="fa-solid fa-angle-left"></i></button>
                <?php for($i=max(1,$page-2); $i<=min($total_pages,$page+2); $i++): ?>
                    <button onclick="changePage(<?= $i ?>)" class="page-btn <?= $i==$page?'active':'' ?>"><?= $i ?></button>
                <?php endfor; ?>
                <button onclick="changePage(<?= $page+1 ?>)" class="page-btn <?= $page>=$total_pages?'disabled':'' ?>"><i class="fa-solid fa-angle-right"></i></button>
                <button onclick="changePage(<?= $total_pages ?>)" class="page-btn <?= $page>=$total_pages?'disabled':'' ?>"><i class="fa-solid fa-angles-right"></i></button>
            </div>
        <?php else: ?>
            <div class="pagination-info">
                <?php if ($total_data > 0): ?>
                    Menampilkan <strong>1</strong> - <strong><?= $total_data ?></strong> dari <strong><?= $total_data ?></strong> data
                <?php else: ?>
                    Menampilkan <strong>0</strong> data
                <?php endif; ?>
            </div>
        <?php endif;
        $pagination_html = ob_get_clean();

        echo json_encode([
            'success' => true,
            'grid' => $grid_html,
            'pagination' => $pagination_html,
            'stats' => [
                'active' => $aktif_count,
                'inactive' => $nonaktif_count,
                'total' => $total_alat
            ]
        ]);
        exit();
    }
}

$daftar_kategori = ['Baju', 'Celana', 'Bola Basket', 'Sepatu', 'Headband', 'Kaos Kaki', 'Lainnya'];
$current_page = 'alat';
$sidebar_folder = 'master';
$sidebar_photo = $profile_photo;
$topbar_title = 'Kelola Alat';
$topbar_breadcrumb = 'Operasional / Alat';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<?php include '../includes/favicon.php'; ?>
<title>Kelola Alat | HoopBall</title>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800;900&family=Barlow:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../asset/css/global.css">
<link rel="stylesheet" href="../asset/css/responsive_tipe_member.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
:root {
    --orange: #FF4500; --orange-lt: rgba(255,69,0,.10); --orange-dk: #E03E00;
    --green: #10B981; --green-lt: rgba(16,185,129,.10);
    --blue: #3B82F6; --blue-lt: rgba(59,130,246,.10);
    --red: #EF4444; --red-lt: rgba(239,68,68,.10);
    --sidebar: #0D1117; --sidebar-w: 260px; --topbar-h: 70px;
    --card-bg: #FFFFFF; --border: #E5E7EB; --border-lt: #F3F4F6;
    --text: #111827; --text-md: #374151; --muted: #6B7280; --bg: #F3F4F6;
    --shopee-orange: #EE4D2D;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }
body { font-family: 'Barlow', sans-serif; background: var(--bg); display: flex; min-height: 100vh; color: var(--text); }

.content { padding: 32px 40px; flex: 1; }
.page-header { display: flex; align-items: flex-end; justify-content: space-between; margin-bottom: 24px; flex-wrap: wrap; gap: 16px; }
.page-title-tag { width: 36px; height: 4px; background: var(--orange); border-radius: 2px; margin-bottom: 8px; }
.page-title { font-family: 'Barlow Condensed', sans-serif; font-size: 30px; font-weight: 900; color: var(--text); text-transform: uppercase; }

.stat-chips { display: flex; gap: 10px; flex-wrap: wrap; }
.stat-chip { display: flex; align-items: center; gap: 8px; padding: 8px 18px; border-radius: 10px; font-size: 12px; font-weight: 700; transition: all .2s; }
.stat-chip:hover { transform: translateY(-2px); }
.chip-green { background: var(--green-lt); color: var(--green); }
.chip-red { background: var(--red-lt); color: var(--red); }
.chip-blue { background: var(--blue-lt); color: var(--blue); }
.chip-val { font-family: 'Barlow Condensed'; font-size: 20px; font-weight: 900; }

.action-bar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; flex-wrap: wrap; gap: 12px; }
.search-box { position: relative; width: 300px; }
.search-box i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: 13px; }
.search-box input { width: 100%; padding: 10px 40px 10px 40px; background: var(--card-bg); border: 1.5px solid var(--border); border-radius: 10px; font-size: 13px; font-family: 'Barlow', sans-serif; outline: none; transition: all .2s; color: var(--text); }
.search-box input:focus { border-color: var(--orange); box-shadow: 0 0 0 3px var(--orange-lt); }
.search-box input::placeholder { color: #9CA3AF; }
.btn-clear-search { position: absolute; right: 8px; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--muted); cursor: pointer; font-size: 14px; padding: 0; display: none; align-items: center; justify-content: center; }

.alat-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px; }
.alat-card { background: var(--card-bg); border-radius: 16px; border: 1px solid var(--border); overflow: hidden; transition: all .25s ease; cursor: pointer; position: relative; }
.alat-card:hover { transform: translateY(-1px); box-shadow: 0 12px 32px rgba(0,0,0,.12); border-color: var(--orange); }
.alat-card:nth-child(odd) { background-color: #FFF7ED; }
.alat-card:nth-child(even) { background-color: #FFFFFF; }
.alat-card:hover { background-color: #FFEDD5 !important; }

.alat-card-photo-wrap { position: relative; width: 100%; aspect-ratio: 1 / 1; background: var(--border-lt); overflow: hidden; }
.alat-card-photo-wrap img { width: 100%; height: 100%; object-fit: cover; transition: transform .3s ease; display: block; }
.alat-card:hover .alat-card-photo-wrap img { transform: scale(1.05); }
.alat-card-photo-placeholder { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #FFF7ED 0%, #FFEDD5 100%); position: absolute; top: 0; left: 0; }
.alat-card-photo-placeholder i { font-size: 48px; color: var(--orange); opacity: .5; }

.alat-card-badge { position: absolute; top: 8px; left: 8px; padding: 7px 16px; border-radius: 20px; font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: .3px; z-index: 2; display: inline-flex; align-items: center; gap: 6px;}
.badge-aktif { background: var(--green-lt); color: var(--green); border: 1px solid rgba(16,185,129,.2); }
.badge-nonaktif { background: var(--red-lt); color: var(--red); border: 1px solid rgba(239,68,68,.2); }
.badge-dot { width: 7px; height: 7px; border-radius: 50%; display: inline-block; }
.badge-aktif .badge-dot { background: var(--green); }
.badge-nonaktif .badge-dot { background: var(--red); }

.alat-card-actions { position: absolute; top: 8px; right: 8px; display: flex; gap: 6px; opacity: 1; transition: opacity .2s ease; z-index: 3; }
.alat-card:hover .alat-card-actions { transform: scale(1.05); }
.alat-card-action-btn { width: 38px; height: 38px; border-radius: 10px; border: 1.5px solid transparent; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 14px; transition: all .25s cubic-bezier(.4,0,.2,1); backdrop-filter: blur(4px); text-decoration: none; font-weight: 700;}
.ac-btn-view { background: linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%); color: #1E40AF; border-color: #BFDBFE;}
.ac-btn-view:hover { background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%); color: #fff; border-color: #3B82F6; transform: translateY(-2px); box-shadow: 0 6px 20px rgba(59,130,246,.35);}
.ac-btn-edit { background: linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%); color: #1E40AF; border-color: #BFDBFE;}
.ac-btn-edit:hover { background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%); color: #fff; border-color: #3B82F6; transform: translateY(-2px); box-shadow: 0 6px 20px rgba(59,130,246,.35);}
.ac-btn-delete { background: linear-gradient(135deg, #FEF2F2 0%, #FEE2E2 100%); color: #DC2626; border-color: #FECACA;}
.ac-btn-delete:hover { background: linear-gradient(135deg, #EF4444 0%, #DC2626 100%); color: #fff; border-color: #EF4444; transform: translateY(-2px); box-shadow: 0 6px 20px rgba(239,68,68,.35);}

.alat-card-info { padding: 16px 20px; }
.alat-card-name { font-size: 15px; font-weight: 700; color: var(--text); line-height: 1.3; margin-bottom: 8px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; min-height: 36px; }
.alat-card-price { font-family: 'Barlow Condensed', sans-serif; font-size: 20px; font-weight: 900; color: var(--shopee-orange); margin-bottom: 8px; }
.alat-card-meta { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.alat-card-stok { font-size: 12px; color: var(--muted); font-weight: 600; }
.alat-card-stok i { color: var(--orange); margin-right: 4px; }
.alat-card-toggle { display: flex; align-items: center; gap: 6px; }
.alat-card-toggle-label { font-size: 10px; font-weight: 700; color: var(--muted); text-transform: uppercase; }

.toggle-switch { position: relative; display: inline-flex; align-items: center; width: 44px; height: 24px; cursor: pointer; margin: 0; flex-shrink: 0; }
.toggle-switch input { opacity: 0; width: 0; height: 0; position: absolute; }
.toggle-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: var(--red); transition: all .3s cubic-bezier(.4, 0, .2, 1); border-radius: 24px; will-change: background-color; }
.toggle-slider::before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: all .3s cubic-bezier(.4, 0, .2, 1); border-radius: 50%; box-shadow: 0 2px 4px rgba(0,0,0,.2); will-change: transform; }
.toggle-switch input:checked + .toggle-slider { background-color: var(--green); }
.toggle-switch input:checked + .toggle-slider::before { transform: translateX(20px); }
.toggle-switch:hover .toggle-slider { opacity: .9; }

.empty-grid { grid-column: 1 / -1; text-align: center; padding: 80px 20px; color: var(--muted); }
.empty-grid i { font-size: 64px; margin-bottom: 20px; opacity: .3; display: block; }
.empty-grid div { font-size: 16px; font-weight: 700; }
.empty-grid p { font-size: 13px; font-weight: 500; margin-top: 8px; opacity: .7; }

.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.55); backdrop-filter: blur(6px); display: none; align-items: center; justify-content: center; z-index: 2000; }
.modal-overlay.open { display: flex; }
.modal-box { background: #fff; border-radius: 20px; width: 520px; max-height: 90vh; overflow-y: auto; box-shadow: 0 25px 60px rgba(0,0,0,.2); position: relative; }
.modal-header { padding: 28px 32px 20px; border-bottom: 1px solid var(--border); }
.modal-subtitle { font-size: 10px; font-weight: 800; color: var(--orange); text-transform: uppercase; margin-bottom: 6px; letter-spacing: .8px; }
.modal-title { font-family: 'Barlow Condensed', sans-serif; font-size: 22px; font-weight: 900; color: var(--text); }
.modal-body { padding: 24px 32px 32px; }
.modal-label { display: block; font-size: 11px; font-weight: 800; color: var(--muted); text-transform: uppercase; letter-spacing: .5px; margin-bottom: 8px; }
.modal-label .required { color: var(--red); margin-left: 2px; font-size: 14px; font-weight: 900; }
.modal-input { width: 100%; padding: 12px 14px; border: 1.5px solid var(--border); border-radius: 10px; font-size: 13px; font-family: 'Barlow', sans-serif; margin-bottom: 4px; outline: none; transition: all .2s; color: var(--text); }
select.modal-input { cursor: pointer; appearance: none; background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%236B7280' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'/%3e%3c/svg%3e"); background-repeat: no-repeat; background-position: right 14px center; background-size: 14px; }

.size-container { display: grid; grid-template-columns: repeat(auto-fill, minmax(105px, 1fr)); gap: 10px; background: var(--bg); border: 1.5px dashed var(--border); border-radius: 12px; padding: 14px; margin-bottom: 8px; }
.size-container.error { border-color: var(--red); background: var(--red-lt); }
.size-hint { grid-column: 1 / -1; font-size: 12px; color: var(--muted); font-weight: 600; text-align: center; padding: 8px 0; }
.size-hint i { color: var(--orange); margin-right: 6px; }
.size-item { background: #fff; border: 1.5px solid var(--border); border-radius: 10px; padding: 8px 10px; transition: all .2s; }
.size-item:focus-within { border-color: var(--orange); box-shadow: 0 0 0 3px var(--orange-lt); }
.size-item-label { display: block; font-family: 'Barlow Condensed', sans-serif; font-size: 13px; font-weight: 800; color: var(--text); text-transform: uppercase; letter-spacing: .4px; margin-bottom: 4px; text-align: center; }
.size-item input { width: 100%; border: none; outline: none; font-size: 14px; font-weight: 700; font-family: 'Barlow', sans-serif; text-align: center; color: var(--text); background: transparent; -moz-appearance: textfield; }
.size-item input::-webkit-outer-spin-button, .size-item input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
.size-item input::placeholder { color: #C4C9D0; font-weight: 500; }
.size-total-row { display: flex; align-items: center; justify-content: space-between; background: var(--orange-lt); border: 1px solid rgba(255,69,0,.15); border-radius: 10px; padding: 10px 14px; margin-bottom: 4px; font-size: 12px; font-weight: 800; color: var(--text); text-transform: uppercase; letter-spacing: .4px; }
.size-total-val { font-family: 'Barlow Condensed', sans-serif; font-size: 18px; font-weight: 900; color: var(--orange); }

.detail-size-label { font-size: 11px; font-weight: 800; color: var(--muted); text-transform: uppercase; letter-spacing: .5px; margin: 16px 0 10px; display: flex; align-items: center; gap: 6px; }
.detail-size-label i { color: var(--orange); font-size: 12px; }
.detail-size-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(88px, 1fr)); gap: 8px; }
.detail-size-chip { background: var(--bg); border: 1px solid var(--border-lt); border-radius: 10px; padding: 8px 6px; text-align: center; transition: all .2s; }
.detail-size-chip:hover { border-color: var(--orange); background: #fff; }
.detail-size-chip .ds-size { font-family: 'Barlow Condensed', sans-serif; font-size: 13px; font-weight: 800; color: var(--muted); text-transform: uppercase; letter-spacing: .4px; }
.detail-size-chip .ds-stok { font-family: 'Barlow Condensed', sans-serif; font-size: 18px; font-weight: 900; color: var(--text); line-height: 1.1; }
.detail-size-chip .ds-stok span { font-size: 10px; font-weight: 600; color: var(--muted); font-family: 'Barlow', sans-serif; }
.detail-kategori-badge { display: inline-flex; align-items: center; gap: 6px; background: var(--blue-lt); color: var(--blue); border: 1px solid rgba(59,130,246,.2); padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .3px; margin-left: 6px; }

.alat-card-cat { font-size: 10px; font-weight: 800; color: var(--blue); text-transform: uppercase; letter-spacing: .5px; margin-bottom: 2px; }
.modal-input:focus { border-color: var(--orange); box-shadow: 0 0 0 3px var(--orange-lt); }
.modal-input::placeholder { color: #9CA3AF; }
.modal-input.error { border-color: var(--red); box-shadow: 0 0 0 3px var(--red-lt); }
.btn-submit { width: 100%; background: var(--orange); color: #fff; border: none; padding: 14px; border-radius: 10px; font-weight: 800; font-size: 13px; cursor: pointer; transition: all .2s; text-transform: uppercase; letter-spacing: .5px; display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 18px; }
.btn-submit:hover { background: var(--orange-dk); transform: translateY(-1px); box-shadow: 0 8px 20px rgba(255,69,0,.3); }
.btn-cancel { display: block; text-align: center; margin-top: 16px; color: var(--muted); text-decoration: none; font-size: 13px; font-weight: 700; transition: .2s; cursor: pointer; background: none; border: none; width: 100%; }
.btn-cancel:hover { color: var(--orange); }
.modal-close { position: absolute; top: 20px; right: 20px; width: 36px; height: 36px; border: none; background: var(--border-lt); border-radius: 10px; cursor: pointer; display: flex; align-items: center; justify-content: center; color: var(--muted); font-size: 16px; transition: all .2s; z-index: 10; }
.modal-close:hover { background: var(--red-lt); color: var(--red); }

.photo-upload-area { width: 100%; height: 140px; border: 2px dashed var(--border); border-radius: 12px; display: flex; flex-direction: column; align-items: center; justify-content: center; cursor: pointer; transition: all .2s ease; margin-bottom: 16px; position: relative; overflow: hidden; background: var(--border-lt); }
.photo-upload-area:hover { border-color: var(--orange); background: var(--orange-lt); }
.photo-upload-area.error { border-color: var(--red); background: var(--red-lt); }
.photo-upload-area.has-image { border-style: solid; border-color: var(--orange); }
.photo-upload-area i.upload-icon { font-size: 28px; color: var(--orange); margin-bottom: 8px; }
.photo-upload-area p { font-size: 13px; font-weight: 600; color: var(--muted); text-align: center; }
.photo-upload-area input[type="file"] { position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%; z-index: 5; }
.photo-upload-preview { width: 100%; height: 100%; object-fit: cover; position: absolute; top: 0; left: 0; z-index: 2; }
.photo-upload-remove { position: absolute; top: 8px; right: 8px; width: 28px; height: 28px; background: rgba(239,68,68,.9); color: #fff; border: none; border-radius: 6px; cursor: pointer; font-size: 12px; display: flex; align-items: center; justify-content: center; z-index: 10; }
.upload-placeholder-inner { display: flex; flex-direction: column; align-items: center; justify-content: center; pointer-events: none; z-index: 1; }

.val-msg { font-size: 11px; color: var(--red); font-weight: 600; margin-bottom: 10px; display: none; min-height: 16px; }
.val-msg.show { display: block; }
.val-msg i { margin-right: 4px; }

.detail-modal-box { width: 460px; border-radius: 24px; border: 1px solid var(--border); overflow-y: auto; }
.detail-photo-wrap { width: 120px; height: 120px; margin: 0 auto 16px auto; background: #ffffff; border-radius: 50%; overflow: hidden; position: relative; border: 3px solid var(--orange); box-shadow: 0 4px 16px rgba(255,69,0,.2); }
.detail-photo-wrap img { width: 100%; height: 100%; object-fit: cover; display: block; }
.detail-photo-placeholder { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #FFF7ED 0%, #FFEDD5 100%); }
.detail-photo-placeholder i { font-size: 40px; color: var(--orange); opacity: .6; }
.detail-name { font-family: 'Barlow Condensed', sans-serif; font-size: 26px; font-weight: 900; color: var(--text); line-height: 1.2; margin-bottom: 6px; text-transform: uppercase; text-align: center; }
.detail-price { font-family: 'Barlow Condensed', sans-serif; font-size: 30px; font-weight: 900; color: var(--shopee-orange); margin-bottom: 20px; border-bottom: 1px solid var(--border-lt); padding-bottom: 14px; text-align: center; }
.detail-info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 20px; }
.detail-info-item { background: #FAFBFD; border: 1px solid var(--border-lt); border-radius: 14px; padding: 14px; transition: all .2s ease; }
.detail-info-label { font-size: 10px; font-weight: 800; color: var(--muted); text-transform: uppercase; letter-spacing: .5px; margin-bottom: 6px; display: flex; align-items: center; gap: 6px; }
.detail-info-value { font-size: 18px; font-weight: 800; color: var(--text); }
.detail-status-badge { display: inline-flex; align-items: center; gap: 6px; padding: 7px 16px; border-radius: 20px; font-size: 12px; font-weight: 800; letter-spacing: .3px; margin-bottom: 14px; text-transform: uppercase; }
.badge-status-aktif { background: var(--green-lt); color: var(--green); border: 1px solid rgba(16,185,129,.2); }
.badge-status-nonaktif { background: var(--red-lt); color: var(--red); border: 1px solid rgba(239,68,68,.2); }
.detail-status-badge i { font-size: 8px; }

.detail-info-item:hover { background: #ffffff; border-color: var(--orange); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,.02); }
.detail-info-label i { color: var(--orange); font-size: 12px; }

.pagination-wrap { background: var(--card-bg); border: 1px solid var(--border); border-radius: 12px; padding: 16px 24px; display: flex; align-items: center; justify-content: space-between; margin-bottom: 32px; }
.pagination-info { font-size: 12px; color: var(--muted); font-weight: 600; }
.pagination-info strong { color: var(--text); font-weight: 800; }
.pagination-nav { display: flex; align-items: center; gap: 4px; }
.page-btn { display: inline-flex; align-items: center; justify-content: center; min-width: 36px; height: 36px; padding: 0 10px; border-radius: 10px; font-size: 13px; font-weight: 700; font-family: 'Barlow', sans-serif; text-decoration: none; cursor: pointer; transition: all .2s ease; border: 1.5px solid var(--border); color: var(--text-md); background: #fff; }
.page-btn:hover:not(.disabled):not(.active) { border-color: var(--orange); color: var(--orange); background: var(--orange-lt); transform: translateY(-1px); }
.page-btn.active { background: var(--orange); color: #fff; border-color: var(--orange); box-shadow: 0 4px 12px rgba(255,69,0,.3); font-weight: 800; }
.page-btn.disabled { opacity: 0.4; cursor: not-allowed; pointer-events: none; }
.page-btn i { font-size: 11px; }

.filter-dropdown-wrap { position: relative; display: inline-block; }
.btn-filter { display: inline-flex; align-items: center; gap: 8px; background-color: var(--orange); color: #ffffff; padding: 11px 20px; border-radius: 10px; font-size: 13px; font-weight: 800; text-transform: uppercase; border: none; cursor: pointer; transition: all 0.2s; box-shadow: 0 4px 12px rgba(255, 69, 0, 0.2); }
.btn-filter:hover { background-color: var(--orange-dk); transform: translateY(-2px); box-shadow: 0 6px 16px rgba(255, 69, 0, 0.35); }
.btn-filter i.arrow-icon { font-size: 10px; transition: transform 0.3s; }
.btn-filter.active i.arrow-icon { transform: rotate(180deg); }
.filter-card { position: absolute; top: calc(100% + 10px); right: 0; background: #ffffff; border-radius: 16px; border: 1px solid var(--border); padding: 24px; width: 300px; box-shadow: 0 15px 35px rgba(0, 0, 0, 0.12); z-index: 50; display: none; }
.filter-card.open { display: block; animation: slideFilter 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
@keyframes slideFilter { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
.filter-card h4 { font-size: 15px; font-weight: 800; color: var(--text); margin-bottom: 20px; text-align: left; }
.filter-group { margin-bottom: 16px; text-align: left; }
.filter-group label { display: block; font-size: 11px; font-weight: 800; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; }
.filter-input { width: 100%; padding: 10px 14px; border: 1.5px solid var(--border); border-radius: 10px; font-size: 13px; font-family: 'Barlow', sans-serif; outline: none; transition: all .2s; color: var(--text); cursor: pointer; appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236B7280' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 14px center; padding-right: 40px; }
.filter-input:focus { border-color: var(--orange); }
.filter-buttons { display: flex; gap: 10px; margin-top: 24px; }
.btn-filter-apply { flex: 1.2; background: var(--orange); color: white; border: none; padding: 12px; border-radius: 10px; font-weight: 800; font-size: 12px; text-transform: uppercase; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px; transition: all .2s; }
.btn-filter-apply:hover { background: var(--orange-dk); }
.btn-filter-reset { flex: 1; background: var(--border-lt); color: var(--text-md); border: 1px solid var(--border); padding: 12px; border-radius: 10px; font-weight: 800; font-size: 12px; text-transform: uppercase; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px; transition: all .2s; }
.btn-filter-reset:hover { background: #E5E7EB; }

.btn-add { display: inline-flex; align-items: center; gap: 8px; background-color: var(--text); color: #fff; padding: 11px 22px; border-radius: 10px; font-size: 13px; font-weight: 800; text-decoration: none; text-transform: uppercase; transition: all .2s ease; border: none; cursor: pointer; }
.btn-add:hover { background-color: var(--orange); transform: translateY(-2px); box-shadow: 0 8px 20px rgba(255,69,0,.3); }
.btn-add i { font-size: 14px; }

html, body { scrollbar-width: none; -ms-overflow-style: none; }
html::-webkit-scrollbar, body::-webkit-scrollbar { display: none; }
body.swal2-shown, html.swal2-shown { padding-right: 0px !important; }

.modal-box { -ms-overflow-style: none; scrollbar-width: none; }
.modal-box::-webkit-scrollbar { display: none; }

@media(max-width:768px){
    .sidebar{width:0;overflow:hidden;padding:0;}
    .main{margin-left:0;}
    .content{padding:20px;}
    .alat-grid{grid-template-columns:repeat(2,1fr);gap:12px;}
    .modal-box{width:90%;margin:20px;}
    .search-box{width:100%;}
    .action-bar{flex-direction:column;align-items:stretch;}
}
@media(max-width:480px){.alat-grid{grid-template-columns:1fr;}}
</style>
</head>
<body>

<!-- MODAL FORM TAMBAH/EDIT ALAT -->
<div class="modal-overlay" id="modalAlat">
    <div class="modal-box">
        <button type="button" class="modal-close" onclick="closeModal()" title="Tutup"><i class="fa-solid fa-xmark"></i></button>
        <div class="modal-header">
            <div class="modal-subtitle">Kelola Alat</div>
            <div class="modal-title" id="formModalTitle">Tambah Alat Baru</div>
        </div>
        <div class="modal-body">
            <form id="formAlat" onsubmit="handleFormSubmit(event)" enctype="multipart/form-data" novalidate>
                <input type="hidden" name="id_alat" id="id_alat" value="0">
                <input type="hidden" name="edit_mode" id="edit_mode" value="0">
                <input type="hidden" name="edit_photo_path" id="edit_photo_path" value="">

                <label class="modal-label">Foto Alat <span class="required">*</span> <span style="color:var(--muted);font-size:10px;">(Wajib, max 5MB)</span></label>
                <div class="photo-upload-area" id="uploadArea">
                    <input type="file" name="photo_alat" id="photo_alat" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" onchange="handlePhotoUpload(this)" style="position:absolute;inset:0;opacity:0;cursor:pointer;z-index:5;">

                    <img class="photo-upload-preview" id="previewImg" style="display:none;" alt="Preview">
                    <button type="button" class="photo-upload-remove" id="removeBtn" onclick="event.stopPropagation(); removePhoto();" style="display:none;" title="Hapus Foto">
                        <i class="fa-solid fa-xmark"></i>
                    </button>

                    <div class="upload-placeholder-inner" id="uploadPlaceholder" style="display:flex; flex-direction:column; align-items:center;">
                        <i class="fa-solid fa-cloud-arrow-up upload-icon"></i>
                        <p>Klik untuk upload foto alat</p>
                        <p style="font-size:11px; margin-top:4px; opacity:.7;">JPG, PNG, GIF, WEBP (Max 5MB)</p>
                    </div>
                </div>
                <div class="val-msg" id="val-photo_alat"></div>

                <label class="modal-label">Nama Alat <span class="required">*</span></label>
                <input type="text" name="nama_alat" id="nama_alat" class="modal-input" placeholder="Contoh: Bola Basket SNI" autocomplete="off" maxlength="25">
                <div class="val-msg" id="val-nama_alat"></div>

                <label class="modal-label">Kategori Alat <span class="required">*</span></label>
                <select name="kategori" id="kategori" class="modal-input" onchange="renderSizeInputs(this.value, null)">
                    <option value="">-- Pilih Kategori --</option>
                    <?php foreach (array_keys($KATEGORI_SIZES) as $kat): ?>
                        <option value="<?= htmlspecialchars($kat) ?>"><?= htmlspecialchars($kat) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="val-msg" id="val-kategori"></div>

                <label class="modal-label">Stok per Ukuran <span class="required">*</span> <span style="color:var(--muted);font-size:10px;text-transform:none;letter-spacing:normal;">(Total minimal 10 pcs)</span></label>

                <div class="size-container" id="sizeContainer">
                    <div class="size-hint"><i class="fa-solid fa-circle-info"></i> Pilih kategori terlebih dahulu untuk mengisi stok per ukuran.</div>
                </div>
                
                <div class="size-total-row">
                    <span>Total Stok</span>
                    <span class="size-total-val"><span id="totalStok">0</span> pcs</span>
                </div>
                <div class="val-msg" id="val-sizes"></div>

                <label class="modal-label">Harga Beli <span class="required">*</span></label>
                <input type="number" name="harga_beli" id="harga_beli" class="modal-input" placeholder="Contoh: 100000" min="5000" autocomplete="off">
                <div class="val-msg" id="val-harga_beli"></div>

                <label class="modal-label">Harga Jual <span class="required">*</span></label>
                <input type="number" name="harga_jual" id="harga_jual" class="modal-input" placeholder="Contoh: 150000" min="20000" autocomplete="off">
                <div class="val-msg" id="val-harga_jual"></div>

                <button type="submit" class="btn-submit" id="btnSubmit">
                    <i class="fa-solid fa-plus"></i> Tambah Alat
                </button>
                <button type="button" class="btn-cancel" onclick="closeModal()">Batal</button>
            </form>
        </div>
    </div>
</div>

<!-- MODAL DETAIL ALAT -->
<div class="modal-overlay" id="modalDetail">
    <div class="modal-box detail-modal-box">
        <button type="button" class="modal-close" onclick="closeModal()" title="Tutup"><i class="fa-solid fa-xmark"></i></button>
        <div class="modal-header" style="text-align: center; padding-bottom: 10px;">
            <div class="modal-subtitle">Detail Informasi</div>
            <div class="modal-title">Spesifikasi Alat</div>
        </div>
        <div class="modal-body" id="detailModalBody" style="padding-top:10px;">
            <!-- Dinamis diisi via AJAX -->
        </div>
    </div>
</div>

<?php include '../includes/sidebar.php'; ?>

<!-- MAIN CONTENT -->
<main class="main">
    <?php include '../includes/topbar.php'; ?>

    <div class="content">
        <div class="page-header">
            <div>
                <div class="page-title-tag"></div>
                <div class="page-title">Kelola Alat</div>
            </div>
            <div class="stat-chips">
                <div class="stat-chip chip-green"><i class="fa-solid fa-circle-check"></i> AKTIF <span class="chip-val" id="stat-aktif">0</span></div>
                <div class="stat-chip chip-red"><i class="fa-solid fa-circle-xmark"></i> NONAKTIF <span class="chip-val" id="stat-nonaktif">0</span></div>
                <div class="stat-chip chip-blue"><i class="fa-solid fa-toolbox"></i> TOTAL <span class="chip-val" id="stat-total">0</span></div>
            </div>
        </div>

        <div class="action-bar">
            <div class="search-box">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="src" placeholder="Cari alat...(Tekan Enter)" onkeypress="handleSearch(event)" value="">
                <button type="button" onclick="clearSearch()" class="btn-clear-search" id="btnClearSearch"><i class="fa-solid fa-circle-xmark"></i></button>
            </div>
            <div style="display:flex;gap:12px;align-items:center;">
                <div class="filter-dropdown-wrap">
                    <button type="button" class="btn-filter" id="btnFilterToggleCustom" onclick="toggleCustomFilterCard(event)">
                        <i class="fa-solid fa-filter"></i> Filter <i class="fa-solid fa-chevron-down arrow-icon"></i>
                    </button>

                    <div class="filter-card" id="filterCardCustom" onclick="event.stopPropagation()">
                        <h4>Filter Data</h4>
                        <form id="formFilter" onsubmit="handleFilterSubmit(event)">
                            <div class="filter-group">
                                <label>Status</label>
                                <select name="f_status" class="filter-input">
                                    <option value="">Semua Status</option>
                                    <option value="1">AKTIF</option>
                                    <option value="0">NONAKTIF</option>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label>Kategori</label>
                                <select name="f_kategori" class="filter-input">
                                    <option value="">Semua Kategori</option>
                                    <?php foreach ($daftar_kategori as $kat): ?>
                                    <option value="<?= htmlspecialchars($kat) ?>"><?= htmlspecialchars($kat) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="filter-group">
                                <label>Urut Berdasarkan</label>
                                <select name="f_sort" class="filter-input">
                                    <option value="nama_asc">Nama A-Z</option>
                                    <option value="stok_desc">Stok Terbanyak</option>
                                    <option value="harga_jual_desc">Harga Jual Termahal</option>
                                    <option value="harga_jual_asc">Harga Jual Termurah</option>
                                    <option value="harga_beli_desc">Harga Beli Termahal</option>
                                    <option value="harga_beli_asc">Harga Beli Termurah</option>
                                </select>
                            </div>
                            
                            <div class="filter-buttons">
                                <button type="button" class="btn-filter-reset" onclick="resetFilter()">
                                    <i class="fa-solid fa-rotate-left"></i> Reset
                                </button>
                                <button type="submit" class="btn-filter-apply">
                                    <i class="fa-solid fa-check"></i> Terapkan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <button type="button" onclick="openAddModal()" class="btn-add"><i class="fa-solid fa-plus"></i>Tambah</button>
            </div>
        </div>

        <!-- GRID KARTU ALAT (AJAX LOADED) -->
        <div class="alat-grid" id="alatGrid"></div>

        <!-- PAGINATION (AJAX LOADED) -->
        <div class="pagination-wrap" id="paginationWrap"></div>
    </div>
</main>

<script src="../asset/js/global.js"></script>
<script>
// State Management AJAX
let currentPage = 1;
let currentSort = 'nama_asc';
let currentStatus = '';
let currentKategori = '';
let currentSearch = '';

const SIZE_SETS = <?= json_encode($KATEGORI_SIZES) ?>;

// ============================================
// GET DATA GRID (AJAX REFRESH)
// ============================================
async function loadGridData() {
    const url = `alat.php?action=get_grid_data&page=${currentPage}&f_sort=${currentSort}&f_status=${currentStatus}&f_kategori=${encodeURIComponent(currentKategori)}&src=${encodeURIComponent(currentSearch)}`;
    try {
        const response = await fetch(url);
        const data = await response.json();
        if (data.success) {
            document.getElementById('stat-aktif').textContent = data.stats.active;
            document.getElementById('stat-nonaktif').textContent = data.stats.inactive;
            document.getElementById('stat-total').textContent = data.stats.total;

            document.getElementById('alatGrid').innerHTML = data.grid;
            document.getElementById('paginationWrap').innerHTML = data.pagination;

            const btnClear = document.getElementById('btnClearSearch');
            if (currentSearch !== '') {
                btnClear.style.display = 'flex';
            } else {
                btnClear.style.display = 'none';
            }
        }
    } catch (error) {
        console.error("Gagal memuat data grid:", error);
    }
}

function changePage(page) {
    currentPage = page;
    loadGridData();
}

// ============================================
// MODAL & FORM CONTROL
// ============================================
function closeModal() {
    document.getElementById('modalAlat').classList.remove('open');
    document.getElementById('modalDetail').classList.remove('open');
}

function openAddModal() {
    document.getElementById('formAlat').reset();
    document.getElementById('id_alat').value = '0';
    document.getElementById('edit_mode').value = '0';
    document.getElementById('edit_photo_path').value = '';

    removePhoto();

    document.querySelectorAll('.val-msg').forEach(el => el.classList.remove('show'));
    document.querySelectorAll('.modal-input').forEach(el => el.classList.remove('error'));

    document.getElementById('formModalTitle').textContent = 'Tambah Alat Baru';
    document.getElementById('btnSubmit').innerHTML = '<i class="fa-solid fa-plus"></i> Tambah Alat';

    renderSizeInputs('', null);
    document.getElementById('modalAlat').classList.add('open');
}

async function openEditModal(id) {
    document.querySelectorAll('.val-msg').forEach(el => el.classList.remove('show'));
    document.querySelectorAll('.modal-input').forEach(el => el.classList.remove('error'));

    try {
        const response = await fetch(`alat.php?action=get_detail&id=${id}`);
        const res = await response.json();
        if (res.success) {
            const data = res.data;
            const sizes = res.sizes;

            document.getElementById('id_alat').value = data.ID_Alat;
            document.getElementById('edit_mode').value = '1';
            document.getElementById('edit_photo_path').value = data.Photo_Alat || '';
            document.getElementById('nama_alat').value = data.Nama_Alat;
            document.getElementById('kategori').value = data.Kategori;
            document.getElementById('harga_beli').value = parseInt(data.Harga_Beli);
            document.getElementById('harga_jual').value = parseInt(data.Harga_Jual);

            // Preview Foto
            if (data.Photo_Alat && res.photo_url) {
                const previewImg = document.getElementById('previewImg');
                const uploadPlaceholder = document.getElementById('uploadPlaceholder');
                const uploadArea = document.getElementById('uploadArea');
                const removeBtn = document.getElementById('removeBtn');

                previewImg.src = res.photo_url;
                previewImg.style.display = 'block';
                uploadArea.classList.add('has-image');
                uploadPlaceholder.style.display = 'none';
                removeBtn.style.display = 'flex';
            } else {
                removePhoto();
            }

            renderSizeInputs(data.Kategori, sizes);

            document.getElementById('formModalTitle').textContent = 'Edit Alat';
            document.getElementById('btnSubmit').innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Simpan Perubahan';

            document.getElementById('modalAlat').classList.add('open');
        } else {
            showError('Gagal!', res.msg);
        }
    } catch (error) {
        showError('Gagal!', 'Terjadi kesalahan saat mengambil data.');
    }
}

async function viewDetail(id) {
    try {
        const response = await fetch(`alat.php?action=get_detail&id=${id}`);
        const res = await response.json();
        if (res.success) {
            const data = res.data;
            const sizes = res.sizes;
            const formatRupiah = (val) => 'Rp ' + new Intl.NumberFormat('id-ID').format(val);
            const isActive = data.Status == 1;

            let sizeHtml = '';
            if (Object.keys(sizes).length > 0) {
                sizeHtml = `<div class="detail-size-label"><i class="fa-solid fa-ruler"></i> Detail Stok per Ukuran</div><div class="detail-size-grid">`;
                for (const [uk, st] of Object.entries(sizes)) {
                    sizeHtml += `<div class="detail-size-chip">
                        <div class="ds-size">${uk}</div>
                        <div class="ds-stok">${st} <span>pcs</span></div>
                    </div>`;
                }
                sizeHtml += `</div>`;
            }

            const photoHtml = res.photo_url ? `<img src="${res.photo_url}" alt="${data.Nama_Alat}">` : `<div class="detail-photo-placeholder"><i class="fa-solid fa-toolbox"></i></div>`;

            document.getElementById('detailModalBody').innerHTML = `
                <div class="detail-photo-wrap">
                    ${photoHtml}
                </div>
                <div style="text-align: center;">
                    <div class="detail-status-badge ${isActive ? 'badge-status-aktif' : 'badge-status-nonaktif'}">
                        <i class="fa-solid fa-circle"></i> ${isActive ? 'Alat Aktif' : 'Alat Nonaktif'}
                    </div>
                    <div class="detail-kategori-badge">
                        <i class="fa-solid fa-shapes"></i> ${data.Kategori || 'Lainnya'}
                    </div>
                </div>
                <div class="detail-name">${data.Nama_Alat}</div>
                <div class="detail-price">${formatRupiah(data.Harga_Jual)} <span style="font-size:14px;color:var(--muted);font-family:'Barlow';font-weight:600;">/ pcs</span></div>
                <div class="detail-info-grid">
                    <div class="detail-info-item">
                        <div class="detail-info-label"><i class="fa-solid fa-boxes-stacked"></i> Stok Tersedia</div>
                        <div class="detail-info-value">${data.Stok} <span style="font-size:11px; font-weight:500; color:var(--muted);">PCS</span></div>
                    </div>
                    <div class="detail-info-item">
                        <div class="detail-info-label"><i class="fa-solid fa-tag"></i> Harga Jual</div>
                        <div class="detail-info-value" style="color:var(--shopee-orange);">${formatRupiah(data.Harga_Jual)}</div>
                    </div>
                    <div class="detail-info-item">
                        <div class="detail-info-label"><i class="fa-solid fa-coins"></i> Harga Beli</div>
                        <div class="detail-info-value">${formatRupiah(data.Harga_Beli)}</div>
                    </div>
                    <div class="detail-info-item">
                        <div class="detail-info-label"><i class="fa-solid fa-chart-line"></i> Keuntungan / pcs</div>
                        <div class="detail-info-value" style="color:var(--green);">${formatRupiah(data.Harga_Jual - data.Harga_Beli)}</div>
                    </div>
                </div>
                ${sizeHtml}
                <button type="button" onclick="closeModal()" class="btn-submit" style="background:#0D1117; margin-top:20px;">
                    <i class="fa-solid fa-arrow-left"></i> Kembali
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
// UPLOAD FOTO & SIZES LOGIC
// ============================================
function handlePhotoUpload(input) {
    if (!input.files || !input.files[0]) return;
    const uploadArea = document.getElementById('uploadArea');
    const valPhoto = document.getElementById('val-photo_alat');
    if (uploadArea) uploadArea.classList.remove('error');
    if (valPhoto) { valPhoto.classList.remove('show'); valPhoto.innerHTML = ''; }

    const file = input.files[0];
    if (file.size > 5 * 1024 * 1024) {
        showError('File Terlalu Besar', 'Ukuran file maksimal 5MB!');
        input.value = '';
        return;
    }
    const reader = new FileReader();
    reader.onload = function(e) {
        const previewImg = document.getElementById('previewImg');
        const uploadPlaceholder = document.getElementById('uploadPlaceholder');
        const removeBtn = document.getElementById('removeBtn');
        if (previewImg) { previewImg.src = e.target.result; previewImg.style.display = 'block'; }
        if (uploadArea) uploadArea.classList.add('has-image');
        if (uploadPlaceholder) uploadPlaceholder.style.display = 'none';
        if (removeBtn) removeBtn.style.display = 'flex';
    };
    reader.readAsDataURL(file);
}

function removePhoto() {
    const previewImg = document.getElementById('previewImg');
    const uploadPlaceholder = document.getElementById('uploadPlaceholder');
    const fileInput = document.getElementById('photo_alat');
    const uploadArea = document.getElementById('uploadArea');
    const removeBtn = document.getElementById('removeBtn');

    if (fileInput) fileInput.value = '';
    if (previewImg) { previewImg.src = ''; previewImg.style.display = 'none'; }
    if (uploadArea) uploadArea.classList.remove('has-image');
    if (uploadPlaceholder) uploadPlaceholder.style.display = 'flex';
    if (removeBtn) removeBtn.style.display = 'none';

    document.getElementById('edit_photo_path').value = '';
}

function renderSizeInputs(kategori, presets) {
    const container = document.getElementById('sizeContainer');
    if (!container) return;
    container.classList.remove('error');
    const valSizes = document.getElementById('val-sizes');
    if (valSizes) { valSizes.classList.remove('show'); valSizes.innerHTML = ''; }

    if (!kategori || !SIZE_SETS[kategori]) {
        container.innerHTML = '<div class="size-hint"><i class="fa-solid fa-circle-info"></i> Pilih kategori terlebih dahulu untuk mengisi stok per ukuran.</div>';
        updateTotalStok();
        return;
    }

    let html = '';
    SIZE_SETS[kategori].forEach(function(size) {
        const val = (presets && presets[size] !== undefined) ? presets[size] : '';
        html += `<div class="size-item">
                    <label class="size-item-label">${size}</label>
                    <input type="number" name="stok_size[${size}]" class="size-stok-input" value="${val}" placeholder="0" min="0" max="9999" autocomplete="off" oninput="updateTotalStok()">
                </div>`;
    });
    container.innerHTML = html;
    updateTotalStok();
}

function updateTotalStok() {
    let total = 0;
    document.querySelectorAll('.size-stok-input').forEach(function(inp) {
        const v = parseInt(inp.value, 10);
        if (!isNaN(v) && v > 0) total += v;
    });
    const el = document.getElementById('totalStok');
    if (el) el.textContent = total;
    return total;
}

// ============================================
// AJAX FORM SUBMIT
// ============================================
async function handleFormSubmit(event) {
    event.preventDefault();
    if (!validateForm()) return;

    const form = document.getElementById('formAlat');
    const formData = new FormData(form);
    formData.append('action', 'save');

    Swal.fire({
        title: 'Memproses...',
        text: 'Menyimpan data alat',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });

    try {
        const response = await fetch('alat.php', {
            method: 'POST',
            body: formData
        });
        const res = await response.json();
        if (res.success) {
            closeModal();
            showSuccess('Berhasil!', res.msg);
            loadGridData();
        } else {
            showError('Gagal!', res.msg);
        }
    } catch (error) {
        showError('Gagal!', 'Gagal memproses data.');
    }
}

// ============================================
// TOGGLE STATUS & DELETE VIA AJAX
// ============================================
async function confirmToggle(id, name, currentStatus, event) {
    const checkbox = event.target;
    const actionText = currentStatus == 1 ? 'Menonaktifkan' : 'Mengaktifkan';

    const result = await Swal.fire({
        title: actionText + ' Alat?',
        html: `Apakah Anda yakin ingin mengubah status <b>${name}</b>?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#FF4500',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Ya, Ubah!',
        cancelButtonText: 'Batal',
        allowOutsideClick: false
    });

    if (result.isConfirmed) {
        Swal.fire({
            title: 'Memproses...',
            text: 'Mengubah status alat',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        try {
            const response = await fetch(`alat.php?action=toggle&id=${id}&status=${currentStatus}`);
            const res = await response.json();
            if (res.success) {
                showSuccess('Berhasil!', res.msg);
                loadGridData();
            } else {
                checkbox.checked = !checkbox.checked;
                showError('Gagal!', res.msg);
            }
        } catch (error) {
            checkbox.checked = !checkbox.checked;
            showError('Gagal!', 'Terjadi kesalahan sistem.');
        }
    } else {
        checkbox.checked = !checkbox.checked;
    }
}

async function confirmDelete(id, name) {
    const result = await Swal.fire({
        title: 'Hapus Permanen?',
        html: `Alat <b>${name}</b> akan dihapus dari sistem.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#EF4444',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal',
        allowOutsideClick: false
    });

    if (result.isConfirmed) {
        Swal.fire({
            title: 'Memproses...',
            text: 'Menghapus alat',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        try {
            const response = await fetch(`alat.php?action=delete&id=${id}`);
            const res = await response.json();
            if (res.success) {
                showSuccess('Terhapus!', res.msg);
                loadGridData();
            } else {
                showError('Gagal!', res.msg);
            }
        } catch (error) {
            showError('Gagal!', 'Terjadi kesalahan saat menghapus.');
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
        loadGridData();
    }
}

function clearSearch() {
    document.getElementById('src').value = '';
    currentSearch = '';
    currentPage = 1;
    loadGridData();
}

// Function Buka/Tutup Filter Card (Sama persis seperti tipe_member.php)
function toggleCustomFilterCard(e) {
    e.stopPropagation();
    const btn = document.getElementById('btnFilterToggleCustom');
    const card = document.getElementById('filterCardCustom');
    if (btn && card) {
        btn.classList.toggle('active');
        card.classList.toggle('open');
    }
}

// Function Apply Filter via AJAX
function handleFilterSubmit(event) {
    event.preventDefault();
    const form = event.target;
    currentSort = form.elements['f_sort'].value;
    currentStatus = form.elements['f_status'].value;
    currentKategori = form.elements['f_kategori'].value;
    currentPage = 1;
    loadGridData();

    // Tutup filter card
    document.getElementById('btnFilterToggleCustom').classList.remove('active');
    document.getElementById('filterCardCustom').classList.remove('open');
}

// Function Reset Filter
function resetFilter() {
    document.getElementById('formFilter').reset();
    currentSort = 'nama_asc';
    currentStatus = '';
    currentKategori = '';
    currentSearch = '';
    document.getElementById('src').value = '';
    currentPage = 1;
    loadGridData();

    document.getElementById('btnFilterToggleCustom').classList.remove('active');
    document.getElementById('filterCardCustom').classList.remove('open');
}

// Auto Close ketika diklik di luar area Filter (Bypass konflik global.js)
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
// VALIDASI FORM JS
// ============================================
function validateForm() {
    let valid = true;
    document.querySelectorAll('.modal-input').forEach(el => el.classList.remove('error'));
    document.querySelectorAll('.val-msg').forEach(el => { el.classList.remove('show'); el.innerHTML = ''; });

    const nama = document.getElementById('nama_alat');
    const valNama = document.getElementById('val-nama_alat');
    if (nama && valNama) {
        const v = nama.value.trim();
        let errNama = '';
        if (v === '') errNama = 'Nama alat wajib diisi.';
        else if (v.length < 3) errNama = 'Nama alat minimal 3 karakter.';
        else if (v.length > 25) errNama = 'Nama alat maksimal 25 karakter.';
        else if (/^\d+$/.test(v)) errNama = 'Nama alat tidak boleh hanya angka.';
        else if (!/^[a-zA-Z0-9\s\-\_]+$/.test(v)) errNama = 'Nama alat hanya boleh huruf, angka, spasi, strip, dan underscore.';
        
        if (errNama) {
            nama.classList.add('error');
            valNama.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> ' + errNama;
            valNama.classList.add('show');
            valid = false;
        }
    }

    const kategori = document.getElementById('kategori');
    const valKategori = document.getElementById('val-kategori');
    if (kategori && valKategori) {
        if (kategori.value === '') {
            kategori.classList.add('error');
            valKategori.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Kategori alat wajib dipilih.';
            valKategori.classList.add('show');
            valid = false;
        }
    }

    const sizeContainer = document.getElementById('sizeContainer');
    const valSizes = document.getElementById('val-sizes');
    if (kategori && kategori.value !== '' && sizeContainer && valSizes) {
        let errSizes = '';
        let totalStok = 0;
        let adaInputInvalid = false;
        document.querySelectorAll('.size-stok-input').forEach(function(inp) {
            const v = inp.value.trim();
            if (v === '') return; 
            if (!/^[0-9]+$/.test(v)) { adaInputInvalid = true; return; }
            const n = parseInt(v, 10);
            if (n > 9999) { adaInputInvalid = true; return; }
            totalStok += n;
        });
        if (adaInputInvalid) errSizes = 'Stok per ukuran harus berupa angka antara 0 - 9999.';
        else if (totalStok < 10) errSizes = 'Total stok dari semua ukuran minimal harus 10 pcs.';
        else if (totalStok > 9999) errSizes = 'Total stok semua ukuran maksimal 9999.';
        
        if (errSizes) {
            sizeContainer.classList.add('error');
            valSizes.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> ' + errSizes;
            valSizes.classList.add('show');
            valid = false;
        }
    }

    const hargaBeli = document.getElementById('harga_beli');
    const valHargaBeli = document.getElementById('val-harga_beli');
    let hargaBeliVal = 0;
    if (hargaBeli && valHargaBeli) {
        const vb = hargaBeli.value.trim();
        let errHargaBeli = '';
        if (vb === '') errHargaBeli = 'Harga beli wajib diisi.';
        else if (isNaN(vb)) errHargaBeli = 'Harga beli harus berupa angka.';
        else if (parseFloat(vb) < 5000) errHargaBeli = 'Harga beli minimal Rp 5.000.';
        else hargaBeliVal = parseFloat(vb);
        
        if (errHargaBeli) {
            hargaBeli.classList.add('error');
            valHargaBeli.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> ' + errHargaBeli;
            valHargaBeli.classList.add('show');
            valid = false;
        }
    }

    const harga = document.getElementById('harga_jual');
    const valHarga = document.getElementById('val-harga_jual');
    if (harga && valHarga) {
        const vh = harga.value.trim();
        let errHarga = '';
        if (vh === '') errHarga = 'Harga jual wajib diisi.';
        else if (isNaN(vh)) errHarga = 'Harga jual harus berupa angka.';
        else if (parseFloat(vh) < 20000) errHarga = 'Harga jual minimal Rp 20.000.';
        else if (parseFloat(vh) < hargaBeliVal) errHarga = 'Harga jual tidak boleh lebih kecil dari harga beli.';
        
        if (errHarga) {
            harga.classList.add('error');
            valHarga.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> ' + errHarga;
            valHarga.classList.add('show');
            valid = false;
        }
    }

    const photoInput = document.getElementById('photo_alat');
    const uploadArea = document.getElementById('uploadArea');
    const valPhoto = document.getElementById('val-photo_alat');
    const isEditMode = document.getElementById('edit_mode').value === '1';
    const hasExistingPhoto = document.getElementById('edit_photo_path').value !== '';

    let photoError = '';
    if (!isEditMode && (!photoInput || !photoInput.files || photoInput.files.length === 0)) {
        photoError = 'Foto alat wajib diupload.';
    } else if (isEditMode && !hasExistingPhoto && (!photoInput || !photoInput.files || photoInput.files.length === 0)) {
        photoError = 'Foto alat wajib diupload.';
    }

    if (photoError) {
        if (uploadArea) uploadArea.classList.add('error');
        if (valPhoto) {
            valPhoto.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> ' + photoError;
            valPhoto.classList.add('show');
        }
        valid = false;
    }

    return valid;
}

// Helpers Alert
function showSuccess(title, message) {
    Swal.close();
    Swal.fire({ icon: 'success', title: title, text: message, confirmButtonColor: '#10B981' });
}

function showError(title, message) {
    Swal.close();
    Swal.fire({ icon: 'error', title: title, text: message, confirmButtonColor: '#EF4444' });
}

// Global Event Handler
window.addEventListener('click', function(e) {
    const filterCard = document.getElementById('filterCard');
    const btnFilter = document.getElementById('btnFilterToggle');
    if (filterCard && btnFilter) {
        if (!filterCard.contains(e.target) && !btnFilter.contains(e.target)) {
            filterCard.classList.remove('open');
            btnFilter.classList.remove('active');
        }
    }
});

document.addEventListener('DOMContentLoaded', function() {
    loadGridData();
});
</script>
<?php if (function_exists('tampilkan_sensor_auto_logout')) tampilkan_sensor_auto_logout(); ?>
</body>
</html>