<?php
session_start();
date_default_timezone_set("Asia/Jakarta");
include '../includes/config.php';
include 'action/helper.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'karyawan') {
    echo "<script>window.location='../view_admin.php';</script>"; exit();
}

$nama = $_SESSION['nama'] ?? 'Karyawan';

// --- Query Data (Sama persis konsepnya) ---
$where = "Is_Deleted = 0";
$params = array();

if (isset($_GET['f_status']) && $_GET['f_status'] !== '') {
    $where .= " AND Status = ?";
    $params[] = $_GET['f_status'];
}

$order = "ID_Alat DESC";
if (isset($_GET['f_sort'])) {
    if ($_GET['f_sort'] == 'lama_baru') $order = "ID_Alat ASC";
    if ($_GET['f_sort'] == 'a_z') $order = "Nama_Alat ASC";
    if ($_GET['f_sort'] == 'z_a') $order = "Nama_Alat DESC";
}
$query = safeQuery($conn, "SELECT * FROM Alat WHERE $where ORDER BY $order", $params);

// --- Stats Chips ---
$q_tot = safeQuery($conn, "SELECT COUNT(*) as t FROM Alat WHERE Is_Deleted = 0");
$tot_alat = $q_tot ? safeFetch($q_tot)['t'] : 0;
$q_aktif = safeQuery($conn, "SELECT COUNT(*) as t FROM Alat WHERE Is_Deleted = 0 AND Status = 1");
$tot_aktif = $q_aktif ? safeFetch($q_aktif)['t'] : 0;
$tot_habis = $tot_alat - $tot_aktif;

// --- Auto ID (Untuk Modal Tambah) ---
$q_max = safeQuery($conn, "SELECT MAX(ID_Alat) as max_id FROM Alat");
$d_max = safeFetch($q_max);
$num = ($d_max['max_id']) ? (int)substr($d_max['max_id'], 2) + 1 : 1;
$next_id_alat = "AL" . str_pad($num, 4, "0", STR_PAD_LEFT);

// --- Get Detail & Edit Data ---
$detail_data = null;
if (isset($_GET['detail_id'])) {
    $q_det = safeQuery($conn, "SELECT * FROM Alat WHERE ID_Alat = ?", [$_GET['detail_id']]);
    if ($q_det) $detail_data = safeFetch($q_det);
}

$edit_data = null;
if (isset($_GET['edit_id'])) {
    $q_edit = safeQuery($conn, "SELECT * FROM Alat WHERE ID_Alat = ?", [$_GET['edit_id']]);
    if ($q_edit) $edit_data = safeFetch($q_edit);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Master Alat | HoopBall</title>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800;900&family=Barlow:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="alat.css">
</head>
<body>

    <!-- MODAL TAMBAH ALAT (Desain Asli) -->
    <div class="modal-overlay" id="modalAddAlat">
        <div class="modal-box">
            <button class="modal-close" onclick="closeAddModal()"><i class="fa-solid fa-xmark"></i></button>
            <div class="modal-header">
                <div class="modal-subtitle">Master Alat</div>
                <div class="modal-title">Tambah Alat Baru</div>
            </div>
            <div class="modal-body">
                <!-- Arahkan ke action/save.php -->
                <form method="POST" action="action/save.php" enctype="multipart/form-data" id="formAddAlat">
                    <input type="hidden" name="save_alat_add" value="1">
                    
                    <label class="modal-label">ID Alat</label>
                    <input type="text" name="id_alat_add" class="modal-input" value="<?= $next_id_alat ?>" readonly>

                    <label class="modal-label">Nama Alat <span class="required">*</span></label>
                    <input type="text" name="nama_alat_add" id="nama_alat_add" class="modal-input" placeholder="Contoh: Bola Molten BG5000" required>

                    <div class="form-grid">
                        <div>
                            <label class="modal-label">Stok Awal <span class="required">*</span></label>
                            <input type="number" name="stok_add" id="stok_add" class="modal-input" placeholder="0" min="0" required>
                        </div>
                        <div>
                            <label class="modal-label">Harga Sewa (Rp) <span class="required">*</span></label>
                            <input type="number" name="harga_add" id="harga_add" class="modal-input" placeholder="25000" min="5000" required>
                        </div>
                    </div>

                    <label class="modal-label">Foto Alat <span style="color:var(--muted); font-weight:600; text-transform:none;">(Opsional, Max 2MB)</span></label>
                    <input type="file" name="foto_add" id="foto_add" class="modal-input" accept="image/jpeg, image/png" style="padding: 9px 14px; background:#fff;">

                    <button type="submit" class="btn-submit"><i class="fa-solid fa-plus"></i> Tambah Alat</button>
                    <a onclick="closeAddModal()" class="btn-cancel">Batal</a>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL EDIT ALAT (Desain Asli) -->
    <div class="modal-overlay <?= $edit_data ? 'open' : '' ?>" id="modalEditAlat">
        <div class="modal-box">
            <button class="modal-close" onclick="closeEditModal()"><i class="fa-solid fa-xmark"></i></button>
            <div class="modal-header">
                <div class="modal-subtitle">Master Alat</div>
                <div class="modal-title">Edit Data Alat</div>
            </div>
            <div class="modal-body">
                <!-- Arahkan ke action/save.php -->
                <form method="POST" action="action/save.php" enctype="multipart/form-data" id="formEditAlat">
                    <input type="hidden" name="save_alat_edit" value="1">
                    
                    <label class="modal-label">ID Alat</label>
                    <input type="text" name="id_alat_edit" class="modal-input" value="<?= $edit_data['ID_Alat'] ?? '' ?>" readonly>

                    <label class="modal-label">Nama Alat <span class="required">*</span></label>
                    <input type="text" name="nama_alat_edit" id="nama_alat_edit" class="modal-input" value="<?= htmlspecialchars($edit_data['Nama_Alat'] ?? '') ?>" required>

                    <div class="form-grid">
                        <div>
                            <label class="modal-label">Stok <span class="required">*</span></label>
                            <input type="number" name="stok_edit" id="stok_edit" class="modal-input" value="<?= $edit_data['Stok'] ?? '' ?>" min="0" required>
                        </div>
                        <div>
                            <label class="modal-label">Harga Sewa (Rp) <span class="required">*</span></label>
                            <input type="number" name="harga_edit" id="harga_edit" class="modal-input" value="<?= isset($edit_data['Harga_Alat']) ? round($edit_data['Harga_Alat']) : '' ?>" min="5000" required>
                        </div>
                    </div>

                    <label class="modal-label">Ganti Foto <span style="color:var(--muted); font-weight:600; text-transform:none;">(Opsional, Max 2MB)</span></label>
                    <?php if(!empty($edit_data['Foto_Alat'])): ?>
                        <div style="margin-bottom: 10px; display:flex; align-items:center; gap:10px;">
                            <img src="uploads/<?= htmlspecialchars($edit_data['Foto_Alat']) ?>" style="height: 40px; width:40px; border-radius: 8px; border: 1px solid var(--border); object-fit:cover;">
                            <span style="font-size:11px; color:var(--orange); font-weight:700;">(Foto saat ini)</span>
                        </div>
                        <input type="hidden" name="foto_lama" value="<?= htmlspecialchars($edit_data['Foto_Alat']) ?>">
                    <?php endif; ?>
                    <input type="file" name="foto_edit" id="foto_edit" class="modal-input" accept="image/jpeg, image/png" style="padding: 9px 14px; background:#fff;">

                    <button type="submit" class="btn-submit"><i class="fa-solid fa-floppy-disk"></i> Simpan Perubahan</button>
                    <a onclick="closeEditModal()" class="btn-cancel">Batal</a>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL DETAIL ALAT (Desain Asli) -->
    <div class="modal-overlay <?= $detail_data ? 'open' : '' ?>">
        <div class="modal-box" style="width: 440px;">
            <button class="modal-close" onclick="window.location.href='index.php'"><i class="fa-solid fa-xmark"></i></button>
            <div class="modal-header" style="border-bottom: none;">
                <div class="modal-subtitle">Informasi Alat</div>
                <div class="modal-title">Detail Alat Basket</div>
            </div>
            <div class="modal-body" style="padding-top:0;">
                <?php if($detail_data): ?>
                    <div class="detail-photo-card">
                        <?php if(!empty($detail_data['Foto_Alat'])): ?>
                            <img src="uploads/<?= htmlspecialchars($detail_data['Foto_Alat']) ?>" class="detail-img">
                        <?php else: ?>
                            <div class="alat-no-foto" style="width:100px; height:100px; font-size:40px; margin: 0 auto 16px;"><i class="fa-solid fa-box"></i></div>
                        <?php endif; ?>
                        <div class="detail-main-name"><?= htmlspecialchars($detail_data['Nama_Alat']) ?></div>
                    </div>
                    <div class="info-row">
                        <span class="info-key"><i class="fa-solid fa-fingerprint"></i> ID Alat</span>
                        <span class="info-val id-text"><?= $detail_data['ID_Alat'] ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-key"><i class="fa-solid fa-cubes"></i> Sisa Stok</span>
                        <span class="info-val"><?= $detail_data['Stok'] ?> Pcs</span>
                    </div>
                    <div class="info-row">
                        <span class="info-key"><i class="fa-solid fa-money-bill-wave"></i> Harga Sewa</span>
                        <span class="info-val" style="font-family:'Barlow Condensed'; font-size:18px; color:var(--orange); font-weight:800;">Rp <?= number_format($detail_data['Harga_Alat'],0,',','.') ?></span>
                    </div>
                    <div class="info-row" style="border:none;">
                        <span class="info-key"><i class="fa-solid fa-circle-check"></i> Status</span>
                        <span class="status-badge <?= ($detail_data['Status']==1) ? 'sb-aktif' : 'sb-habis' ?>">
                            <span class="status-dot"></span> <?= ($detail_data['Status']==1) ? 'AKTIF' : 'NONAKTIF' ?>
                        </span>
                    </div>
                    <button class="btn-kembali" onclick="window.location.href='index.php'"><i class="fa-solid fa-arrow-left"></i> Kembali Ke List</button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include 'toggle/sidebar.php'; ?>
    <main class="main">
        <?php include 'toggle/topbar.php'; ?>

        <div class="content">
            <div class="page-header">
                <div>
                    <div class="page-title-tag"></div>
                    <div class="page-title">Daftar Alat Basket</div>
                </div>
                <div class="stat-chips">
                    <div class="stat-chip chip-green"><i class="fa-solid fa-check-circle"></i> AKTIF <span class="chip-val"><?= $tot_aktif ?></span></div>
                    <div class="stat-chip chip-red"><i class="fa-solid fa-circle-xmark"></i> NONAKTIF <span class="chip-val"><?= $tot_habis ?></span></div>
                    <div class="stat-chip chip-blue"><i class="fa-solid fa-list"></i> TOTAL <span class="chip-val"><?= $tot_alat ?></span></div>
                </div>
            </div>

            <div class="action-bar">
                <div class="search-box">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="src" placeholder="Cari nama atau ID alat..." onkeyup="searchTable()">
                </div>
                <div style="display: flex; gap: 12px;">
                    <div class="filter-dropdown-wrap">
                        <button class="btn-filter" id="btnFilterToggle"><i class="fa-solid fa-filter"></i> Filter</button>
                        <div class="filter-card" id="filterCard">
                            <h4>Filter Data</h4>
                            <form method="GET" action="index.php">
                                <div class="filter-group">
                                    <label>Urut Berdasarkan</label>
                                    <select name="f_sort" class="filter-input">
                                        <option value="baru_lama" <?= ($_GET['f_sort'] ?? '') == 'baru_lama' ? 'selected' : '' ?>>Terbaru - Terlama</option>
                                        <option value="lama_baru" <?= ($_GET['f_sort'] ?? '') == 'lama_baru' ? 'selected' : '' ?>>Terlama - Terbaru</option>
                                        <option value="a_z" <?= ($_GET['f_sort'] ?? '') == 'a_z' ? 'selected' : '' ?>>Nama A - Z</option>
                                        <option value="z_a" <?= ($_GET['f_sort'] ?? '') == 'z_a' ? 'selected' : '' ?>>Nama Z - A</option>
                                    </select>
                                </div>
                                <div class="filter-group">
                                    <label>Status Alat</label>
                                    <select name="f_status" class="filter-input">
                                        <option value="">Semua Status</option>
                                        <option value="1" <?= ($_GET['f_status'] ?? '') == '1' ? 'selected' : '' ?>>AKTIF</option>
                                        <option value="0" <?= ($_GET['f_status'] ?? '') == '0' ? 'selected' : '' ?>>NONAKTIF</option>
                                    </select>
                                </div>
                                <div class="filter-buttons">
                                    <a href="index.php" class="btn-filter-reset">Reset</a>
                                    <button type="submit" class="btn-filter-apply">Terapkan</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <button class="btn-add" onclick="openAddModal()"><i class="fa-solid fa-plus"></i> Tambah Alat</button>
                </div>
            </div>

            <!-- TABEL DENGAN LEBAR PENUH SEPERTI ASLI -->
            <div class="card table-wrap">
                <table class="data-table" id="tbl">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Foto</th>
                            <th>Nama Alat</th>
                            <th>Stok</th>
                            <th>Harga Sewa</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if ($query): $no = 1;
                            while($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)): 
                                $is_aktif = ($row['Status'] == 1);
                        ?>
                        <tr>
                            <td class="id-text" style="text-align: center; color:var(--text);"><?= $no++ ?></td>
                            <td style="text-align: center;">
                                <?php if(!empty($row['Foto_Alat'])): ?>
                                    <img src="uploads/<?= htmlspecialchars($row['Foto_Alat']) ?>" class="alat-foto">
                                <?php else: ?>
                                    <div class="alat-no-foto"><i class="fa-solid fa-box"></i></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="nama-text"><?= htmlspecialchars($row['Nama_Alat']) ?></div>
                                <div style="font-size:11px; color:var(--muted); font-weight:800; font-family:'Barlow Condensed';"><?= $row['ID_Alat'] ?></div>
                            </td>
                            <td style="font-weight:800; font-size:15px; color: <?= ($row['Stok'] == 0) ? 'var(--red)' : 'var(--blue)' ?>;">
                                <?= $row['Stok'] ?> Pcs
                            </td>
                            <td class="harga-text">Rp <?= number_format($row['Harga_Alat'], 0, ',', '.') ?></td>
                            <td style="text-align: center;">
                                <span class="status-badge <?= $is_aktif ? 'sb-aktif' : 'sb-habis' ?>">
                                    <span class="status-dot"></span> <?= $is_aktif ? 'Aktif' : 'Nonaktif' ?>
                                </span>
                            </td>
                            <td>
                                <div class="actions">
                                    <a href="?detail_id=<?= $row['ID_Alat'] ?>" class="btn-action btn-view" title="Detail"><i class="fa-solid fa-eye"></i></a>
                                    <a href="?edit_id=<?= $row['ID_Alat'] ?>" class="btn-action btn-edit" title="Edit"><i class="fa-solid fa-pen-to-square"></i></a>
                                    
                                    <label class="toggle-switch" title="Ubah Status">
                                        <input type="checkbox" <?= $is_aktif ? 'checked' : '' ?> onchange="confirmToggle('<?= $row['ID_Alat'] ?>', '<?= addslashes(htmlspecialchars($row['Nama_Alat'])) ?>', <?= $row['Status'] ?>, event)">
                                        <span class="toggle-slider"></span>
                                    </label>

                                    <button onclick="confirmDelete('<?= $row['ID_Alat'] ?>', '<?= addslashes(htmlspecialchars($row['Nama_Alat'])) ?>')" class="btn-action btn-delete" title="Hapus"><i class="fa-solid fa-trash-can"></i></button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script src="function/tampilan.js"></script>
    <script src="function/togs.js"></script>
    <script src="function/delete.js"></script>
    <script src="function/form.js"></script>
</body>
</html>