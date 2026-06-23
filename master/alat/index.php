<?php
// Suppress notices for undefined variables
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 1);

ob_start();
session_start();
date_default_timezone_set("Asia/Jakarta");
include '../../includes/config.php';

if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'karyawan' && $_SESSION['role'] !== 'pemilik')) {
    echo "<script>alert('Akses Ditolak!'); window.location='../dashboard/dashboard.php';</script>";
    exit();
}
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
                $profile_photo = '../../' . $photo_path;
            } else {
                $profile_photo = '../../uploads/profiles/' . $photo_path;
            }
        }
    }
}

require_once __DIR__ . '/function/helpers.php';
require_once __DIR__ . '/function/validation.php';
require_once __DIR__ . '/function/read.php';
require_once __DIR__ . '/action/create.php';
require_once __DIR__ . '/action/update.php';
require_once __DIR__ . '/action/delete.php';

$user_info = [
    'nama' => $nama,
    'id' => $id_karyawan_session,
    'role' => $role
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_alat'])) {
    $id_alat = isset($_POST['id_alat']) ? intval($_POST['id_alat']) : 0;
    $is_edit = $id_alat > 0;
    
    if ($is_edit) {
        $response = handleUpdateAlat($conn, $_POST, $_FILES, $user_info);
    } else {
        $response = handleCreateAlat($conn, $_POST, $_FILES, $user_info);
    }
    
    if ($response['success']) {
        ob_end_clean();
        $redirect = $response['redirect'] ?? 'index.php';
        $msg = $response['message'] ?? 'Operasi berhasil!';
        header("Location: $redirect?status=success&msg=" . urlencode($msg));
        exit();
    } else {
        ob_end_clean();
        $msg = $response['message'] ?? 'Gagal melakukan operasi!';
        header("Location: index.php?status=error&msg=" . urlencode($msg));
        exit();
    }
}

if (isset($_GET['toggle_id'])) {
    $toggle_id = intval($_GET['toggle_id']);
    $current_status = intval($_GET['s'] ?? 0);
    
    $response = handleToggleStatus($conn, $toggle_id, $current_status, $user_info);
    
    ob_end_clean();
    $msg = $response['message'] ?? 'Operasi berhasil!';
    $redirect = $response['redirect'] ?? 'index.php';
    header("Location: $redirect?status=" . ($response['success'] ? 'success' : 'error') . "&msg=" . urlencode($msg));
    exit();
}

if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    
    $response = handleDeleteAlat($conn, $delete_id, $user_info);
    
    ob_end_clean();
    $msg = $response['message'] ?? 'Operasi berhasil!';
    $redirect = $response['redirect'] ?? 'index.php';
    header("Location: $redirect?status=" . ($response['success'] ? 'success' : 'error') . "&msg=" . urlencode($msg));
    exit();
}

$edit_data = null;
if (isset($_GET['edit_id'])) {
    $edit_data = getEditData($conn, intval($_GET['edit_id']));
}

$detail_data = null;
$show_detail = false;
if (isset($_GET['detail_id'])) {
    $detail_data = getDetailData($conn, intval($_GET['detail_id']));
    $show_detail = ($detail_data !== false && $detail_data !== null);
}

$show_add = isset($_GET['add']) && $_GET['add'] == '1';
$show_detail = $show_detail ?? false;

// Get filters dari URL
$filters = [];
if (isset($_GET['f_status']) && $_GET['f_status'] !== '') {
    $filters['f_status'] = $_GET['f_status'];
}

// Get sort option
$sort = $_GET['f_sort'] ?? 'nama_asc';

// Get page
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

// Fetch data dengan filter & pagination
$alat_result = getAlatList($conn, [
    'filters' => $filters,
    'limit' => 12,
    'page' => $page,
    'sort' => $sort
]);

$alat_list = $alat_result['data'] ?? [];
$pagination = $alat_result['pagination'] ?? ['page' => 1, 'total_pages' => 1, 'total' => 0];

// Get statistics
$stats = getAlatStats($conn);

// Get filter URL untuk pagination
$filter_url = "";
if (isset($_GET['f_sort'])) $filter_url .= "&f_sort=" . urlencode($_GET['f_sort']);
if (isset($_GET['f_status'])) $filter_url .= "&f_status=" . urlencode($_GET['f_status']);

// Status message dari redirect
$status_msg = $_GET['status'] ?? '';
$status_text = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Kelola Alat | HoopBall</title>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800;900&family=Barlow:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="alat.css">
</head>
<body>
<!-- MODAL FORM TAMBAH/EDIT ALAT -->
<div class="modal-overlay <?= ($edit_data || $show_add) ? 'open' : '' ?>" id="modalAlat">
    <div class="modal-box">
        <button type="button" class="modal-close" onclick="closeModal()" title="Tutup"><i class="fa-solid fa-xmark"></i></button>
        <div class="modal-header">
            <div class="modal-subtitle">Kelola Alat</div>
            <div class="modal-title"><?= $edit_data ? 'Edit Alat' : 'Tambah Alat Baru' ?></div>
        </div>
        <div class="modal-body">
            <form method="POST" id="formAlat" enctype="multipart/form-data" action="index.php" onsubmit="return validateForm()">
                <input type="hidden" name="save_alat" value="1">
                <?php if ($edit_data): ?>
                    <input type="hidden" name="edit_mode" value="1">
                    <input type="hidden" name="id_alat" value="<?= intval($edit_data['ID_Alat']) ?>">
                    <input type="hidden" name="edit_photo_path" value="<?= htmlspecialchars($edit_data['Photo_Alat'] ?? '') ?>">
                <?php endif; ?>

                <label class="modal-label">Foto Alat <span class="required">*</span> <span style="color:var(--muted);font-size:10px;">(Wajib, max 5MB)</span></label>
                <div class="photo-upload-area <?= ($edit_data && !empty($edit_data['Photo_Alat'])) ? 'has-image' : '' ?>" id="uploadArea">
                    <input type="file" name="photo_alat" id="photo_alat" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" onchange="handlePhotoUpload(this)" style="position:absolute;inset:0;opacity:0;cursor:pointer;z-index:5;">

                    <?php if ($edit_data && !empty($edit_data['Photo_Alat'])): ?>
                        <img src="<?= htmlspecialchars(getPhotoUrl($edit_data['Photo_Alat'])) ?>"
                             class="photo-upload-preview" id="previewImg" alt="Foto Alat"
                             onerror="this.style.display='none'; document.getElementById('uploadPlaceholder').style.display='flex';">
                        <button type="button" class="photo-upload-remove" id="removeBtn"
                                onclick="event.stopPropagation(); removePhoto();" title="Hapus Foto">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    <?php else: ?>
                        <img class="photo-upload-preview" id="previewImg" style="display:none;" alt="Preview">
                        <button type="button" class="photo-upload-remove" id="removeBtn"
                                onclick="event.stopPropagation(); removePhoto();" style="display:none;" title="Hapus Foto">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    <?php endif; ?>

                    <div class="upload-placeholder-inner" id="uploadPlaceholder"
                         style="display:<?= ($edit_data && !empty($edit_data['Photo_Alat'])) ? 'none' : 'flex' ?>; flex-direction:column; align-items:center;">
                        <i class="fa-solid fa-cloud-arrow-up upload-icon"></i>
                        <p>Klik untuk upload foto alat</p>
                        <p style="font-size:11px; margin-top:4px; opacity:.7;">JPG, PNG, GIF, WEBP (Max 5MB)</p>
                    </div>
                </div>
                <div class="val-msg" id="val-photo_alat"></div>

                <label class="modal-label">Nama Alat <span class="required">*</span></label>
                <input type="text" name="nama_alat" id="nama_alat" class="modal-input"
                       value="<?= htmlspecialchars($edit_data['Nama_Alat'] ?? '') ?>"
                       placeholder="Contoh: Bola Basket SNI" autocomplete="off" maxlength="25">
                <div class="val-msg" id="val-nama_alat"></div>

                <label class="modal-label">Stok <span class="required">*</span></label>
                <input type="number" name="stok" id="stok" class="modal-input"
                       value="<?= htmlspecialchars($edit_data['Stok'] ?? '') ?>"
                       placeholder="Contoh: 10" max="9999" autocomplete="off">
                <div class="val-msg" id="val-stok"></div>

                <label class="modal-label">Harga Jual <span class="required">*</span></label>
                <input type="number" name="harga_alat" id="harga_alat" class="modal-input"
                       value="<?= isset($edit_data['Harga_Alat']) ? intval($edit_data['Harga_Alat']) : '' ?>"
                       placeholder="Contoh: 150000" min="20000" autocomplete="off">
                <div class="val-msg" id="val-harga_alat"></div>

                <button type="submit" class="btn-submit" id="btnSubmit">
                    <i class="fa-solid fa-<?= $edit_data ? 'floppy-disk' : 'plus' ?>"></i>
                    <?= $edit_data ? 'Simpan Perubahan' : 'Tambah Alat' ?>
                </button>
                <button type="button" class="btn-cancel" onclick="closeModal()">Batal</button>
            </form>
        </div>
    </div>
</div>

<!-- MODAL DETAIL ALAT - FOTO BULAT SEPERTI LAPANGAN -->
<div class="modal-overlay <?= $show_detail ? 'open' : '' ?>" id="modalDetail">
    <div class="modal-box detail-modal-box">
        <button type="button" class="modal-close" onclick="closeModal()" title="Tutup"><i class="fa-solid fa-xmark"></i></button>
        <div class="modal-header" style="text-align: center; padding-bottom: 10px;">
            <div class="modal-subtitle">Detail Informasi</div>
            <div class="modal-title">Spesifikasi Alat</div>
        </div>
        <div class="modal-body" style="padding-top:10px;">
            <?php if ($detail_data): ?>
                <div class="detail-photo-wrap">
                    <?php
                    $detail_photo_url = getPhotoUrl($detail_data['Photo_Alat'] ?? '');
                    if (!empty($detail_photo_url)):
                    ?>
                        <img src="<?= htmlspecialchars($detail_photo_url) ?>"
                             alt="<?= htmlspecialchars($detail_data['Nama_Alat']) ?>"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <div class="detail-photo-placeholder" style="display:none;"><i class="fa-solid fa-toolbox"></i></div>
                    <?php else: ?>
                        <div class="detail-photo-placeholder"><i class="fa-solid fa-toolbox"></i></div>
                    <?php endif; ?>
                </div>

                <div style="text-align: center;">
                    <div class="detail-status-badge <?= $detail_data['Status'] == 1 ? 'badge-status-aktif' : 'badge-status-nonaktif' ?>">
                        <i class="fa-solid fa-circle"></i> <?= $detail_data['Status'] == 1 ? 'Alat Aktif' : 'Alat Nonaktif' ?>
                    </div>
                </div>

                <div class="detail-name"><?= htmlspecialchars($detail_data['Nama_Alat']) ?></div>
                <div class="detail-price"><?= rupiah($detail_data['Harga_Alat']) ?> <span style="font-size:14px;color:var(--muted);font-family:'Barlow';font-weight:600;">/ pcs</span></div>

                <div class="detail-info-grid">
                    <div class="detail-info-item">
                        <div class="detail-info-label"><i class="fa-solid fa-boxes-stacked"></i> Stok Tersedia</div>
                        <div class="detail-info-value"><?= intval($detail_data['Stok']) ?> <span style="font-size:11px; font-weight:500; color:var(--muted);">PCS</span></div>
                    </div>
                    <div class="detail-info-item">
                        <div class="detail-info-label"><i class="fa-solid fa-tag"></i> Harga Satuan</div>
                        <div class="detail-info-value" style="color:var(--shopee-orange);"><?= rupiah($detail_data['Harga_Alat']) ?></div>
                    </div>
                </div>

                <button type="button" onclick="closeModal()" class="btn-submit" style="background:#0D1117;">
                    <i class="fa-solid fa-arrow-left"></i> Kembali
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- SIDEBAR -->
<aside class="sidebar">
    <a href="../dashboard/view_admin.php" class="sb-brand">
        <div class="sb-icon"><i class="fa-solid fa-basketball"></i></div>
        <div>
            <div class="sb-brand-name">HOOP BALL</div>
            <div class="sb-brand-sub">Sistem Managemen</div>
        </div>
    </a>
    <div class="sb-section-label">Operasional</div>
    <nav>
        <a href="../dashboard/view_admin.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-house"></i></div>Dashboard</a>
        <a href="../customer.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-users"></i></div>Kelola Customer</a>
        <a href="../lapangan.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-layer-group"></i></div>Kelola Lapangan</a>
        <a href="../fasilitas_lapangan.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-list-check"></i></div>Kelola Fasilitas</a>
        <a href="../jadwal.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-calendar-days"></i></div>Kelola Jadwal</a>
        <a href="../promo.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-tags"></i></div>Kelola Promo</a>
        <a href="../tipe_member.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-id-card"></i></div>Kelola Tipe Member</a>
        <a href="index.php" class="sb-link active"><div class="sb-icon-wrap"><i class="fa-solid fa-toolbox"></i></div>Kelola Alat</a>
    </nav>
    <div class="sb-section-label">Transaksi</div>
    <nav>
        <a href="../transaksi/booking.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-calendar-check"></i></div>Kelola Booking</a>
        <a href="../transaksi/langganan.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-crown"></i></div>Kelola Langganan</a>
        <a href="../transaksi/pembelian.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-cart-shopping"></i></div>Kelola Pembelian Alat</a>
        <a href="../transaksi/pembatalan.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-ban"></i></div>Kelola Pembatalan</a>
    </nav>
    <div class="sb-section-label">Akun</div>
    <a href="../profile/profile.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-id-badge"></i></div>Profil Saya</a>
    <div class="sb-bottom">
        <div class="sb-user">
            <div class="sb-avatar">
                <?php if (!empty($profile_photo)): ?>
                    <img src="<?= htmlspecialchars($profile_photo) ?>" alt="Profile">
                <?php else: ?>
                    <i class="fa-solid fa-user"></i>
                <?php endif; ?>
            </div>
            <div>
                <div class="sb-user-name"><?= strtoupper(htmlspecialchars($nama)) ?></div>
                <div class="sb-user-role"><?= strtoupper(htmlspecialchars($role)) ?></div>
            </div>
            <a href="../login/logout.php" class="sb-logout" title="Keluar"><i class="fa-solid fa-right-from-bracket"></i></a>
        </div>
    </div>
</aside>

<!-- MAIN CONTENT -->
<main class="main">
    <header class="topbar">
        <div class="topbar-left">
            <div class="topbar-title">Kelola Alat</div>
            <div class="topbar-breadcrumb">Operasional / Alat</div>
        </div>
        <div class="topbar-right">
            <div id="clock-display">
                <div class="clock-time">
                    <span id="clock-h">00</span><span class="clock-colon">:</span>
                    <span id="clock-m">00</span><span class="clock-colon">:</span>
                    <span id="clock-s">00</span>
                </div>
                <div class="clock-divider"></div>
                <div class="clock-date" id="full-date">MEMUAT...</div>
            </div>
            <a href="#" class="topbar-btn"><i class="fa-solid fa-magnifying-glass"></i></a>
            <a href="#" class="topbar-btn">
                <i class="fa-solid fa-bell"></i>
                <?php if ($total_pending > 0): ?><span class="notif-dot"></span><?php endif; ?>
            </a>
            <div class="dropdown-wrap" id="userDropdown">
                <div class="topbar-user" onclick="toggleUserDropdown()">
                    <div class="t-avatar">
                        <?php if (!empty($profile_photo)): ?>
                            <img src="<?= htmlspecialchars($profile_photo) ?>" alt="Profile">
                        <?php else: ?>
                            <i class="fa-solid fa-user"></i>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="t-name"><?= strtoupper(htmlspecialchars($nama)) ?></div>
                        <div class="t-role"><?= strtoupper(htmlspecialchars($role)) ?></div>
                    </div>
                    <i class="fa-solid fa-chevron-down t-chevron"></i>
                </div>
                <div class="dropdown-menu">
                    <a href="../profile/profile.php" class="dd-item"><i class="fa-solid fa-id-badge"></i> Profil Saya</a>
                    <hr class="dd-divider">
                    <a href="../login/logout.php" class="dd-item" style="color:var(--red);"><i class="fa-solid fa-right-from-bracket"></i> Keluar</a>
                </div>
            </div>
        </div>
    </header>

    <div class="content">
        <div class="page-header">
            <div>
                <div class="page-title-tag"></div>
                <div class="page-title">Kelola Alat</div>
            </div>
            <div class="stat-chips">
                <div class="stat-chip chip-green"><i class="fa-solid fa-circle-check"></i> AKTIF <span class="chip-val"><?= $stats['aktif'] ?></span></div>
                <div class="stat-chip chip-red"><i class="fa-solid fa-circle-xmark"></i> NONAKTIF <span class="chip-val"><?= $stats['nonaktif'] ?></span></div>
                <div class="stat-chip chip-blue"><i class="fa-solid fa-toolbox"></i> TOTAL <span class="chip-val"><?= $stats['total'] ?></span></div>
            </div>
        </div>

        <div class="action-bar">
            <div class="search-box">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="src" placeholder="Cari alat..." onkeyup="searchGrid()">
            </div>
            <div style="display:flex;gap:12px;align-items:center;">
                <div class="filter-dropdown-wrap">
                    <button class="btn-filter" id="btnFilterToggle">
                        <i class="fa-solid fa-filter"></i> Filter <i class="fa-solid fa-chevron-down arrow-icon"></i>
                    </button>
                    <div class="filter-card" id="filterCard">
                        <h4><i class="fa-solid fa-sliders" style="margin-right:8px;color:var(--orange);"></i>Filter Data</h4>
                        <form method="GET" action="index.php">
                            <div class="filter-group">
                                <label>Status</label>
                                <select name="f_status" class="filter-input">
                                    <option value="">Semua Status</option>
                                    <option value="1" <?= ($_GET['f_status'] ?? '') === '1' ? 'selected' : '' ?>>AKTIF</option>
                                    <option value="0" <?= ($_GET['f_status'] ?? '') === '0' ? 'selected' : '' ?>>NONAKTIF</option>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label>Urutkan</label>
                                <select name="f_sort" class="filter-input">
                                    <option value="nama_asc"  <?= ($_GET['f_sort'] ?? '') === 'nama_asc'  ? 'selected' : '' ?>>Nama A-Z</option>
                                    <option value="stok_desc" <?= ($_GET['f_sort'] ?? '') === 'stok_desc' ? 'selected' : '' ?>>Stok Terbanyak</option>
                                    <option value="harga_desc"<?= ($_GET['f_sort'] ?? '') === 'harga_desc'? 'selected' : '' ?>>Harga Termahal</option>
                                    <option value="harga_asc" <?= ($_GET['f_sort'] ?? '') === 'harga_asc' ? 'selected' : '' ?>>Harga Termurah</option>
                                </select>
                            </div>
                            <div class="filter-buttons">
                                <button type="button" class="btn-filter-reset" onclick="resetFilter()"><i class="fa-solid fa-rotate-left"></i> Reset</button>
                                <button type="submit" class="btn-filter-apply"><i class="fa-solid fa-check"></i> Terapkan</button>
                            </div>
                        </form>
                    </div>
                </div>
                <a href="index.php?add=1" class="btn-add"><i class="fa-solid fa-plus"></i>Tambah</a>
            </div>
        </div>

        <!-- GRID KARTU ALAT -->
        <div class="alat-grid" id="alatGrid">
        <?php
        $has_data = false;
        if (!empty($alat_list)):
            foreach ($alat_list as $row):
                $has_data = true;
                $photo_url = getPhotoUrl($row['Photo_Alat'] ?? '');
                $is_aktif = intval($row['Status']) === 1;
        ?>
            <div class="alat-card" data-name="<?= strtolower(htmlspecialchars($row['Nama_Alat'])) ?>">
                <div class="alat-card-photo-wrap" onclick="window.location.href='?detail_id=<?= intval($row['ID_Alat']) ?>'">
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
                        <a href="?detail_id=<?= intval($row['ID_Alat']) ?>"
                           class="alat-card-action-btn ac-btn-view" title="Lihat Detail"
                           onclick="event.stopPropagation()"><i class="fa-solid fa-eye"></i></a>
                        <a href="?edit_id=<?= intval($row['ID_Alat']) ?>"
                           class="alat-card-action-btn ac-btn-edit" title="Edit Alat"
                           onclick="event.stopPropagation()"><i class="fa-solid fa-pen-to-square"></i></a>
                        <button type="button"
                                onclick="event.stopPropagation(); doDelete(<?= intval($row['ID_Alat']) ?>, '<?= htmlspecialchars($row['Nama_Alat'], ENT_QUOTES) ?>')"
                                class="alat-card-action-btn ac-btn-delete" title="Hapus Alat">
                            <i class="fa-solid fa-trash-can"></i>
                        </button>
                    </div>
                </div>
                <div class="alat-card-info">
                    <div class="alat-card-name"><?= htmlspecialchars($row['Nama_Alat']) ?></div>
                    <div class="alat-card-price"><?= rupiah($row['Harga_Alat']) ?></div>
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
            endforeach;
        endif;
        if (!$has_data): ?>
            <div class="empty-grid">
                <i class="fa-solid fa-toolbox"></i>
                <div>Belum ada data alat</div>
                <p>Klik "+ Tambah Alat" untuk menambahkan alat baru</p>
            </div>
        <?php endif; ?>
        </div>

        <!-- PAGINATION -->
        <div class="pagination-wrap">
            <div class="pagination-info">
                Menampilkan <strong><?= (($pagination['page']-1)*12)+1 ?></strong> - <strong><?= min($pagination['page']*12,$pagination['total']) ?></strong>
                dari <strong><?= $pagination['total'] ?></strong> data
            </div>
            <?php if ($pagination['total_pages'] > 1): ?>
            <div class="pagination-nav">
                <a href="?page=1<?= $filter_url ?>" class="page-btn <?= $pagination['page']<=1?'disabled':'' ?>"><i class="fa-solid fa-angles-left"></i></a>
                <a href="?page=<?= $pagination['page']-1 ?><?= $filter_url ?>" class="page-btn <?= $pagination['page']<=1?'disabled':'' ?>"><i class="fa-solid fa-angle-left"></i></a>
                <?php for($i=max(1,$pagination['page']-2);$i<=min($pagination['total_pages'],$pagination['page']+2);$i++): ?>
                    <a href="?page=<?= $i ?><?= $filter_url ?>" class="page-btn <?= $i==$pagination['page']?'active':'' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <a href="?page=<?= $pagination['page']+1 ?><?= $filter_url ?>" class="page-btn <?= $pagination['page']>=$pagination['total_pages']?'disabled':'' ?>"><i class="fa-solid fa-angle-right"></i></a>
                <a href="?page=<?= $pagination['total_pages'] ?><?= $filter_url ?>" class="page-btn <?= $pagination['page']>=$pagination['total_pages']?'disabled':'' ?>"><i class="fa-solid fa-angles-right"></i></a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>
<script>
function updateClock() {
    var now = new Date();
    var h = String(now.getHours()).padStart(2,'0');
    var m = String(now.getMinutes()).padStart(2,'0');
    var s = String(now.getSeconds()).padStart(2,'0');
    var hEl = document.getElementById('clock-h');
    var mEl = document.getElementById('clock-m');
    var sEl = document.getElementById('clock-s');
    var dEl = document.getElementById('full-date');
    if(hEl) hEl.textContent = h;
    if(mEl) mEl.textContent = m;
    if(sEl) sEl.textContent = s;
    var days = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    var months = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    if(dEl) dEl.textContent = days[now.getDay()] + ', ' + now.getDate() + ' ' + months[now.getMonth()] + ' ' + now.getFullYear();
}
updateClock();
setInterval(updateClock, 1000);

function closeModal() {
    window.location.href = 'index.php';
}

function handlePhotoUpload(input) {
    if (!input.files || !input.files[0]) return;

    var uploadArea = document.getElementById('uploadArea');
    var valPhoto = document.getElementById('val-photo_alat');
    if (uploadArea) uploadArea.classList.remove('error');
    if (valPhoto) { valPhoto.classList.remove('show'); valPhoto.innerHTML = ''; }

    var file = input.files[0];
    if (file.size > 5 * 1024 * 1024) {
        Swal.fire({
            icon: 'error',
            title: 'File Terlalu Besar',
            text: 'Ukuran file maksimal 5MB!',
            confirmButtonColor: '#FF4500'
        });
        input.value = '';
        return;
    }
    var reader = new FileReader();
    reader.onload = function(e) {
        var previewImg = document.getElementById('previewImg');
        var uploadPlaceholder = document.getElementById('uploadPlaceholder');
        var uploadArea = document.getElementById('uploadArea');
        var removeBtn = document.getElementById('removeBtn');
        if (previewImg) {
            previewImg.src = e.target.result;
            previewImg.style.display = 'block';
        }
        if (uploadArea) uploadArea.classList.add('has-image');
        if (uploadPlaceholder) uploadPlaceholder.style.display = 'none';
        if (removeBtn) removeBtn.style.display = 'flex';
    };
    reader.readAsDataURL(file);
}

function removePhoto() {
    var previewImg = document.getElementById('previewImg');
    var uploadPlaceholder = document.getElementById('uploadPlaceholder');
    var fileInput = document.getElementById('photo_alat');
    var uploadArea = document.getElementById('uploadArea');
    var removeBtn = document.getElementById('removeBtn');
    var valPhoto = document.getElementById('val-photo_alat');

    if (fileInput) fileInput.value = '';
    if (previewImg) {
        previewImg.src = '';
        previewImg.style.display = 'none';
    }
    if (uploadArea) {
        uploadArea.classList.remove('has-image');
        var isEditMode = document.querySelector('input[name="edit_mode"]') !== null;
        var editPhotoPath = document.querySelector('input[name="edit_photo_path"]');
        if (isEditMode && editPhotoPath && editPhotoPath.value) {
            uploadArea.classList.add('error');
            if (valPhoto) {
                valPhoto.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Foto alat wajib diupload.';
                valPhoto.classList.add('show');
            }
            editPhotoPath.value = '';
        }
    }
    if (uploadPlaceholder) uploadPlaceholder.style.display = 'flex';
    if (removeBtn) removeBtn.style.display = 'none';
}

function searchGrid() {
    var filter = document.getElementById('src').value.toLowerCase();
    var cards = document.querySelectorAll('.alat-card');
    cards.forEach(function(card) {
        var name = card.getAttribute('data-name') || '';
        card.style.display = name.indexOf(filter) > -1 ? '' : 'none';
    });
}

function validateForm() {
    var valid = true;
    document.querySelectorAll('.modal-input').forEach(function(el) { el.classList.remove('error'); });
    document.querySelectorAll('.val-msg').forEach(function(el) { el.classList.remove('show'); el.innerHTML = ''; });

    var nama = document.getElementById('nama_alat');
    var valNama = document.getElementById('val-nama_alat');
    if (nama && valNama) {
        var v = nama.value.trim();
        var errNama = '';
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

    var stok = document.getElementById('stok');
    var valStok = document.getElementById('val-stok');
    if (stok && valStok) {
        var vs = stok.value.trim();
        var errStok = '';
        if (vs === '') errStok = 'Stok wajib diisi.';
        else if (!/^[0-9]+$/.test(vs)) errStok = 'Stok harus di atas 0.';
        else if (parseInt(vs) <= 0) errStok = 'Stok tidak boleh 0 atau kurang dari 0.';
        else if (parseInt(vs) > 9999) errStok = 'Stok maksimal 9999.';
        if (errStok) {
            stok.classList.add('error');
            valStok.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> ' + errStok;
            valStok.classList.add('show');
            valid = false;
        }
    }

    var harga = document.getElementById('harga_alat');
    var valHarga = document.getElementById('val-harga_alat');
    if (harga && valHarga) {
        var vh = harga.value.trim();
        var errHarga = '';
        if (vh === '') errHarga = 'Harga jual wajib diisi.';
        else if (isNaN(vh)) errHarga = 'Harga jual harus berupa angka.';
        else if (parseFloat(vh) < 20000) errHarga = 'Harga jual minimal Rp 20.000.';
        else if (parseFloat(vh) > 999999999) errHarga = 'Harga jual terlalu besar.';
        if (errHarga) {
            harga.classList.add('error');
            valHarga.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> ' + errHarga;
            valHarga.classList.add('show');
            valid = false;
        }
    }

    var photoInput = document.getElementById('photo_alat');
    var previewImg = document.getElementById('previewImg');
    var uploadArea = document.getElementById('uploadArea');
    var valPhoto = document.getElementById('val-photo_alat');
    var isEditMode = document.querySelector('input[name="edit_mode"]') !== null;
    var hasExistingPhoto = previewImg && previewImg.style.display !== 'none' && previewImg.src && previewImg.src !== '' && !previewImg.src.includes('data:image');

    var photoError = '';
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
    } else {
        if (uploadArea) uploadArea.classList.remove('error');
        if (valPhoto) valPhoto.classList.remove('show');
    }

    if (!valid) return false;

    var btn = document.getElementById('btnSubmit');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Memproses...';
    }
    return true;
}

document.addEventListener('DOMContentLoaded', function() {
    var namaAlat = document.getElementById('nama_alat');
    if (namaAlat) {
        namaAlat.addEventListener('input', function() {
            var valNama = document.getElementById('val-nama_alat');
            var v = this.value.trim();
            this.classList.remove('error'); valNama.classList.remove('show');
            if (v.length > 0 && v.length < 3) {
                this.classList.add('error');
                valNama.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Nama alat minimal 3 karakter.';
                valNama.classList.add('show');
            } else if (v.length > 25) {
                this.classList.add('error');
                valNama.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Nama alat maksimal 25 karakter.';
                valNama.classList.add('show');
            }
        });
    }

    var stokEl = document.getElementById('stok');
    if (stokEl) {
        stokEl.addEventListener('input', function() {
            var valStok = document.getElementById('val-stok');
            var v = this.value.trim();
            this.classList.remove('error'); valStok.classList.remove('show');
            if (v !== '' && /^[0-9]+$/.test(v)) {
                if (parseInt(v) <= 0) {
                    this.classList.add('error');
                    valStok.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Stok tidak boleh 0 atau kurang dari 0.';
                    valStok.classList.add('show');
                } else if (parseInt(v) > 9999) {
                    this.classList.add('error');
                    valStok.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Stok maksimal 9999.';
                    valStok.classList.add('show');
                }
            }
        });
    }

    var hargaEl = document.getElementById('harga_alat');
    if (hargaEl) {
        hargaEl.addEventListener('input', function() {
            var valHarga = document.getElementById('val-harga_alat');
            var v = this.value.trim();
            this.classList.remove('error'); valHarga.classList.remove('show');
            if (v !== '' && !isNaN(v)) {
                if (parseFloat(v) < 20000) {
                    this.classList.add('error');
                    valHarga.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Harga jual minimal Rp 20.000.';
                    valHarga.classList.add('show');
                }
            }
        });
    }

    var urlParams = new URLSearchParams(window.location.search);
    var status = urlParams.get('status');
    var msg = urlParams.get('msg');

    if (status && msg) {
        var isSuccess = status === 'success';
        Swal.fire({
            icon: isSuccess ? 'success' : 'error',
            title: isSuccess ? 'Berhasil!' : 'Gagal!',
            text: msg,
            timer: 3000,
            showConfirmButton: false,
            toast: true,
            position: 'top-end',
            timerProgressBar: true,
            showCloseButton: true,
            didOpen: function(toast) {
                toast.addEventListener('mouseenter', Swal.stopTimer);
                toast.addEventListener('mouseleave', Swal.resumeTimer);
            }
        });
        var cleanUrl = window.location.pathname;
        window.history.replaceState({}, document.title, cleanUrl);
    }

    var btnFilterToggle = document.getElementById('btnFilterToggle');
    var filterCard = document.getElementById('filterCard');
    if (btnFilterToggle && filterCard) {
        btnFilterToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            filterCard.classList.toggle('open');
        });
        filterCard.addEventListener('click', function(e) { e.stopPropagation(); });
        document.addEventListener('click', function() { filterCard.classList.remove('open'); });
    }
});

function toggleUserDropdown() {
    var dd = document.getElementById('userDropdown');
    if (dd) dd.classList.toggle('active');
}
document.addEventListener('click', function(e) {
    var dd = document.getElementById('userDropdown');
    if (dd && !dd.contains(e.target)) dd.classList.remove('active');
});

function confirmToggle(id, name, currentStatus, event) {
    var checkbox = event.target;
    var newStatus = currentStatus === 1 ? 0 : 1;
    var statusText = newStatus === 1 ? 'Aktif' : 'Nonaktif';
    var icon = newStatus === 1 ? 'success' : 'warning';
    var confirmColor = newStatus === 1 ? '#10B981' : '#EF4444';

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
    }).then(function(result) {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Memproses...',
                text: 'Mengubah status alat',
                allowOutsideClick: false,
                didOpen: function() {
                    Swal.showLoading();
                }
            });
            setTimeout(function() {
                window.location.href = '?toggle_id=' + id + '&s=' + currentStatus;
            }, 600);
        } else {
            checkbox.checked = !checkbox.checked;
        }
    });
}

function doDelete(id, name) {
    Swal.fire({
        title: 'Hapus Alat?',
        html: 'Anda akan menghapus alat <strong style="color:var(--orange);">' + name + '</strong><br><span style="font-size:12px;color:var(--muted);">Data akan dihapus secara permanen.</span>',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#EF4444',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal',
        reverseButtons: true
    }).then(function(result) {
        if (result.isConfirmed) {
            Swal.fire({ 
                title: 'Menghapus...', 
                allowOutsideClick: false, 
                didOpen: function() { Swal.showLoading(); } 
            });
            setTimeout(function() {
                window.location.href = 'index.php?delete_id=' + id;
            }, 500);
        }
    });
}

function resetFilter() {
    window.location.href = 'index.php';
}
</script>
</body>
</html>