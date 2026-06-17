<?php
session_start();
include '../includes/config.php'; 

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'karyawan') {
    echo "<script>window.location='../view_admin.php';</script>"; exit();
}

$nama = $_SESSION['nama'] ?? 'Karyawan';

// ════════════════════════════════════════════════════════
// FUNGSI ERROR CHECKER (Mencegah Layar Putih Crash)
// ════════════════════════════════════════════════════════
function cekSqlError($stmt) {
    if ($stmt === false) {
        $err = sqlsrv_errors();
        $msg = $err[0]['message'] ?? 'Unknown DB Error';
        die("<div style='background:#FEF2F2; border:2px solid #DC2626; padding:20px; margin:20px; border-radius:12px; font-family:sans-serif;'>
                <h3 style='color:#DC2626; margin-top:0;'>🚨 Terjadi Kesalahan Database!</h3>
                <p><b>Detail:</b> " . htmlspecialchars($msg) . "</p>
                <p style='color:#7F1D1D; font-size:14px;'><i>Saran: Pastikan kolom <b>Foto_Alat</b> sudah dibuat di tabel Alat SQL Server Anda.</i></p>
                <a href='index.php' style='display:inline-block; margin-top:10px; background:#DC2626; color:white; padding:10px 20px; text-decoration:none; border-radius:8px;'>Kembali</a>
             </div>");
    }
}

// ════════════════════════════════════════════════════════
// 1. PROSES TAMBAH ALAT (ADD)
// ════════════════════════════════════════════════════════
if (isset($_POST['save_alat_add'])) {
    $id_baru = $_POST['id_alat_add'];
    $nama_alat = trim($_POST['nama_alat_add']);
    $stok = (int)$_POST['stok_add'];
    $harga = (float)$_POST['harga_add'];
    $foto_name = null;

    if (isset($_FILES['foto_add']) && $_FILES['foto_add']['error'] === 0) {
        $allowed = ['jpg', 'jpeg', 'png'];
        $ext = strtolower(pathinfo($_FILES['foto_add']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed) && $_FILES['foto_add']['size'] <= 2097152) { 
            $foto_name = $id_baru . "_" . time() . "." . $ext;
            move_uploaded_file($_FILES['foto_add']['tmp_name'], "uploads/" . $foto_name);
        } else {
            header("Location: index.php?status=error&msg=Gagal upload! Format salah atau ukuran > 2MB.");
            exit();
        }
    }

    $sql = "INSERT INTO Alat (ID_Alat, Nama_Alat, Stok, Harga_Alat, Status, Is_Deleted, Created_By, Created_Date, Foto_Alat) 
            VALUES (?, ?, ?, ?, 1, 0, ?, GETDATE(), ?)";
    $stmt = sqlsrv_query($conn, $sql, array($id_baru, $nama_alat, $stok, $harga, $nama, $foto_name));
    cekSqlError($stmt);

    header("Location: index.php?status=success&msg=Alat baru berhasil ditambahkan!");
    exit();
}

// ════════════════════════════════════════════════════════
// 2. PROSES EDIT ALAT (UPDATE)
// ════════════════════════════════════════════════════════
if (isset($_POST['save_alat_edit'])) {
    $id_edit = $_POST['id_alat_edit'];
    $nama_alat = trim($_POST['nama_alat_edit']);
    $stok = (int)$_POST['stok_edit'];
    $harga = (float)$_POST['harga_edit'];
    
    // Ambil data lama secara aman
    $q_old = sqlsrv_query($conn, "SELECT Foto_Alat FROM Alat WHERE ID_Alat = ?", array($id_edit));
    cekSqlError($q_old);
    
    $d_old = sqlsrv_fetch_array($q_old, SQLSRV_FETCH_ASSOC);
    $foto_name = $d_old['Foto_Alat'] ?? null;

    if (isset($_FILES['foto_edit']) && $_FILES['foto_edit']['error'] === 0) {
        $allowed = ['jpg', 'jpeg', 'png'];
        $ext = strtolower(pathinfo($_FILES['foto_edit']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed) && $_FILES['foto_edit']['size'] <= 2097152) { 
            $foto_name = $id_edit . "_" . time() . "." . $ext;
            move_uploaded_file($_FILES['foto_edit']['tmp_name'], "uploads/" . $foto_name);
            
            if(!empty($d_old['Foto_Alat']) && file_exists("uploads/" . $d_old['Foto_Alat'])) {
                unlink("uploads/" . $d_old['Foto_Alat']);
            }
        } else {
            header("Location: index.php?status=error&msg=Gagal upload foto edit! Periksa format & ukuran.");
            exit();
        }
    }

    $sql = "UPDATE Alat SET Nama_Alat=?, Stok=?, Harga_Alat=?, Foto_Alat=?, Modified_By=?, Modified_Date=GETDATE() WHERE ID_Alat=?";
    $stmt = sqlsrv_query($conn, $sql, array($nama_alat, $stok, $harga, $foto_name, $nama, $id_edit));
    cekSqlError($stmt);

    header("Location: index.php?status=success&msg=Alat berhasil diperbarui!");
    exit();
}

// ════════════════════════════════════════════════════════
// 3. PROSES HAPUS (SOFT DELETE) & TOGGLE STATUS
// ════════════════════════════════════════════════════════
if (isset($_GET['delete_id'])) {
    $stmt = sqlsrv_query($conn, "UPDATE Alat SET Is_Deleted = 1, Deleted_By = ?, Deleted_Date = GETDATE() WHERE ID_Alat = ?", array($nama, $_GET['delete_id']));
    cekSqlError($stmt);
    header("Location: index.php?status=success&msg=Data alat berhasil dihapus!"); exit();
}

if (isset($_GET['toggle_id']) && isset($_GET['s'])) {
    $s_baru = ($_GET['s'] == 1) ? 0 : 1;
    $stmt = sqlsrv_query($conn, "UPDATE Alat SET Status = ? WHERE ID_Alat = ?", array($s_baru, $_GET['toggle_id']));
    cekSqlError($stmt);
    header("Location: index.php?status=success&msg=Status alat berhasil diubah!"); exit();
}

// ════════════════════════════════════════════════════════
// 4. QUERY LIST TABEL, FILTER & SORTING
// ════════════════════════════════════════════════════════
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
$query = sqlsrv_query($conn, "SELECT * FROM Alat WHERE $where ORDER BY $order", $params);
cekSqlError($query);

// ════════════════════════════════════════════════════════
// 5. STATISTIK CHIPS & DATA MODAL
// ════════════════════════════════════════════════════════
$q_tot = sqlsrv_query($conn, "SELECT COUNT(*) as t FROM Alat WHERE Is_Deleted = 0");
$tot_alat = sqlsrv_fetch_array($q_tot, SQLSRV_FETCH_ASSOC)['t'] ?? 0;
$q_aktif = sqlsrv_query($conn, "SELECT COUNT(*) as t FROM Alat WHERE Is_Deleted = 0 AND Status = 1");
$tot_aktif = sqlsrv_fetch_array($q_aktif, SQLSRV_FETCH_ASSOC)['t'] ?? 0;
$tot_habis = $tot_alat - $tot_aktif;

$q_max = sqlsrv_query($conn, "SELECT MAX(ID_Alat) as max_id FROM Alat");
$d_max = sqlsrv_fetch_array($q_max, SQLSRV_FETCH_ASSOC);
$num = ($d_max['max_id']) ? (int)substr($d_max['max_id'], 2) + 1 : 1;
$next_id_alat = "AL" . str_pad($num, 4, "0", STR_PAD_LEFT);

$detail_data = null;
if (isset($_GET['detail_id'])) {
    $q_det = sqlsrv_query($conn, "SELECT * FROM Alat WHERE ID_Alat = ?", array($_GET['detail_id']));
    if ($q_det) $detail_data = sqlsrv_fetch_array($q_det, SQLSRV_FETCH_ASSOC);
}

$edit_data = null;
if (isset($_GET['edit_id'])) {
    $q_edit = sqlsrv_query($conn, "SELECT * FROM Alat WHERE ID_Alat = ?", array($_GET['edit_id']));
    if ($q_edit) $edit_data = sqlsrv_fetch_array($q_edit, SQLSRV_FETCH_ASSOC);
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

    <!-- ════════════ MODAL TAMBAH ALAT ════════════ -->
    <div class="modal-overlay" id="modalAddAlat">
        <div class="modal-box">
            <button class="modal-close" onclick="closeAddModal()"><i class="fa-solid fa-xmark"></i></button>
            <div class="modal-header">
                <div class="modal-subtitle">Master Alat</div>
                <div class="modal-title">Tambah Alat Baru</div>
            </div>
            <div class="modal-body">
                <form method="POST" enctype="multipart/form-data" id="formAddAlat" onsubmit="return validateFormAdd()" novalidate>
                    <label class="modal-label">ID Alat</label>
                    <input type="text" name="id_alat_add" class="modal-input" value="<?= $next_id_alat ?>" readonly>

                    <label class="modal-label">Nama Alat <span class="required">*</span></label>
                    <input type="text" name="nama_alat_add" id="nama_alat_add" class="modal-input" placeholder="Contoh: Bola Molten BG5000">
                    <div class="val-msg" id="val-nama_alat_add"></div>

                    <div class="form-grid">
                        <div>
                            <label class="modal-label">Stok Awal <span class="required">*</span></label>
                            <input type="number" name="stok_add" id="stok_add" class="modal-input" placeholder="0" min="0">
                            <div class="val-msg" id="val-stok_add"></div>
                        </div>
                        <div>
                            <label class="modal-label">Harga Sewa (Rp) <span class="required">*</span></label>
                            <input type="number" name="harga_add" id="harga_add" class="modal-input" placeholder="25000" min="5000">
                            <div class="val-msg" id="val-harga_add"></div>
                        </div>
                    </div>

                    <label class="modal-label">Foto Alat <span style="color:var(--muted); font-weight:600; text-transform:none;">(Opsional, Max 2MB)</span></label>
                    <input type="file" name="foto_add" id="foto_add" class="modal-input" accept="image/jpeg, image/png" style="padding: 9px 14px; background:#fff;">

                    <button type="submit" name="save_alat_add" class="btn-submit">
                        <i class="fa-solid fa-plus"></i> Tambah Alat
                    </button>
                    <a onclick="closeAddModal()" class="btn-cancel">Batal</a>
                </form>
            </div>
        </div>
    </div>

    <!-- ════════════ MODAL EDIT ALAT ════════════ -->
    <div class="modal-overlay <?= $edit_data ? 'open' : '' ?>" id="modalEditAlat">
        <div class="modal-box">
            <button class="modal-close" onclick="closeEditModal()"><i class="fa-solid fa-xmark"></i></button>
            <div class="modal-header">
                <div class="modal-subtitle">Master Alat</div>
                <div class="modal-title">Edit Data Alat</div>
            </div>
            <div class="modal-body">
                <form method="POST" enctype="multipart/form-data" id="formEditAlat" onsubmit="return validateFormEdit()" novalidate>
                    <label class="modal-label">ID Alat</label>
                    <input type="text" name="id_alat_edit" class="modal-input" value="<?= $edit_data['ID_Alat'] ?? '' ?>" readonly>

                    <label class="modal-label">Nama Alat <span class="required">*</span></label>
                    <input type="text" name="nama_alat_edit" id="nama_alat_edit" class="modal-input" value="<?= htmlspecialchars($edit_data['Nama_Alat'] ?? '') ?>" placeholder="Contoh: Bola Molten BG5000">
                    <div class="val-msg" id="val-nama_alat_edit"></div>

                    <div class="form-grid">
                        <div>
                            <label class="modal-label">Stok <span class="required">*</span></label>
                            <input type="number" name="stok_edit" id="stok_edit" class="modal-input" value="<?= $edit_data['Stok'] ?? '' ?>" min="0">
                            <div class="val-msg" id="val-stok_edit"></div>
                        </div>
                        <div>
                            <label class="modal-label">Harga Sewa (Rp) <span class="required">*</span></label>
                            <input type="number" name="harga_edit" id="harga_edit" class="modal-input" value="<?= isset($edit_data['Harga_Alat']) ? round($edit_data['Harga_Alat']) : '' ?>" min="5000">
                            <div class="val-msg" id="val-harga_edit"></div>
                        </div>
                    </div>

                    <label class="modal-label">Ganti Foto <span style="color:var(--muted); font-weight:600; text-transform:none;">(Opsional, Max 2MB)</span></label>
                    <?php if(!empty($edit_data['Foto_Alat'])): ?>
                        <div style="margin-bottom: 10px; display:flex; align-items:center; gap:10px;">
                            <img src="uploads/<?= htmlspecialchars($edit_data['Foto_Alat']) ?>" style="height: 40px; width:40px; border-radius: 8px; border: 1px solid var(--border); object-fit:cover;">
                            <span style="font-size:11px; color:var(--orange); font-weight:700;">(Foto saat ini)</span>
                        </div>
                    <?php endif; ?>
                    <input type="file" name="foto_edit" id="foto_edit" class="modal-input" accept="image/jpeg, image/png" style="padding: 9px 14px; background:#fff;">

                    <button type="submit" name="save_alat_edit" class="btn-submit">
                        <i class="fa-solid fa-floppy-disk"></i> Simpan Perubahan
                    </button>
                    <a onclick="closeEditModal()" class="btn-cancel">Batal</a>
                </form>
            </div>
        </div>
    </div>

    <!-- ════════════ MODAL DETAIL ALAT ════════════ -->
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

    <!-- ════════════ MAIN LAYOUT ════════════ -->
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

            <!-- TABEL DENGAN LEBAR PENUH (100%) -->
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
                                        <input type="checkbox" <?= $is_aktif ? 'checked' : '' ?> onchange="confirmToggle('<?= $row['ID_Alat'] ?>', <?= $row['Status'] ?>)">
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

    <!-- ════════════ PANGGIL FILE JAVASCRIPT YANG SUDAH DIPISAH ════════════ -->
    <script src="function/tampilan.js"></script>
    <script src="function/togs.js"></script>
    <script src="function/delete.js"></script>
    <script src="function/add.js"></script>
    <script src="function/edit.js"></script>
</body>
</html>