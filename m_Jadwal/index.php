<?php
session_start();
include '../includes/config.php'; 

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'karyawan') {
    echo "<script>window.location='../view_admin.php';</script>"; exit();
}

$nama = $_SESSION['nama'] ?? 'Karyawan';

// Helper Cegah Layar Putih (Crash)
function cekSqlError($stmt) {
    if ($stmt === false) {
        $err = sqlsrv_errors();
        $msg = $err[0]['message'] ?? 'Unknown DB Error';
        die("<div style='background:#FEF2F2; border:2px solid #DC2626; padding:20px; margin:20px; border-radius:12px; font-family:sans-serif;'>
                <h3 style='color:#DC2626; margin-top:0;'>🚨 Terjadi Kesalahan Database!</h3>
                <p><b>Detail:</b> " . htmlspecialchars($msg) . "</p>
                <a href='index.php' style='display:inline-block; margin-top:10px; background:#DC2626; color:white; padding:10px 20px; text-decoration:none; border-radius:8px;'>Kembali</a>
             </div>");
    }
}

// ════════════════════════════════════════════════════════
// 1. PROSES TAMBAH JADWAL (ADD)
// ════════════════════════════════════════════════════════
if (isset($_POST['save_jadwal_add'])) {
    $id_lapangan = $_POST['lapangan_add'];
    $tanggal = $_POST['tanggal_add'];
    $jam_m = $_POST['jam_mulai_add'];
    $jam_s = $_POST['jam_selesai_add'];

    // Cek Bentrok Jadwal
    $sql_cek = "SELECT ID_Jadwal FROM Jadwal WHERE ID_Lapangan = ? AND Tanggal = ? AND Is_Deleted = 0 AND (Jam_Mulai < ? AND Jam_Selesai > ?)";
    $q_cek = sqlsrv_query($conn, $sql_cek, array($id_lapangan, $tanggal, $jam_s, $jam_m));
    if (sqlsrv_has_rows($q_cek)) {
        header("Location: index.php?status=error&msg=Gagal! Jam bertabrakan dengan jadwal lapangan ini."); exit();
    }

    // Generate ID Baru
    $q_id = sqlsrv_query($conn, "SELECT MAX(ID_Jadwal) as mx FROM Jadwal");
    $d_id = sqlsrv_fetch_array($q_id, SQLSRV_FETCH_ASSOC);
    $num = ($d_id['mx']) ? (int)substr($d_id['mx'], 2) + 1 : 1;
    $new_id = "JD" . str_pad($num, 4, "0", STR_PAD_LEFT);

    $sql_in = "INSERT INTO Jadwal (ID_Jadwal, ID_Lapangan, Tanggal, Jam_Mulai, Jam_Selesai, Status, Is_Deleted, Created_By, Created_Date) 
               VALUES (?, ?, ?, ?, ?, 1, 0, ?, GETDATE())";
    $stmt = sqlsrv_query($conn, $sql_in, array($new_id, $id_lapangan, $tanggal, $jam_m, $jam_s, $nama));
    cekSqlError($stmt);

    header("Location: index.php?status=success&msg=Jadwal berhasil ditambahkan!"); exit();
}

// ════════════════════════════════════════════════════════
// 2. PROSES EDIT JADWAL (UPDATE)
// ════════════════════════════════════════════════════════
if (isset($_POST['save_jadwal_edit'])) {
    $id_jadwal = $_POST['id_jadwal_edit'];
    $id_lapangan = $_POST['lapangan_edit'];
    $tanggal = $_POST['tanggal_edit'];
    $jam_m = $_POST['jam_mulai_edit'];
    $jam_s = $_POST['jam_selesai_edit'];

    // Cek Bentrok (Kecuali Dirinya Sendiri)
    $sql_cek = "SELECT ID_Jadwal FROM Jadwal WHERE ID_Lapangan = ? AND Tanggal = ? AND ID_Jadwal != ? AND Is_Deleted = 0 AND (Jam_Mulai < ? AND Jam_Selesai > ?)";
    $q_cek = sqlsrv_query($conn, $sql_cek, array($id_lapangan, $tanggal, $id_jadwal, $jam_s, $jam_m));
    if (sqlsrv_has_rows($q_cek)) {
        header("Location: index.php?status=error&msg=Gagal! Jam bertabrakan dengan jadwal lapangan ini."); exit();
    }

    $sql_up = "UPDATE Jadwal SET ID_Lapangan=?, Tanggal=?, Jam_Mulai=?, Jam_Selesai=?, Modified_By=?, Modified_Date=GETDATE() WHERE ID_Jadwal=?";
    $stmt = sqlsrv_query($conn, $sql_up, array($id_lapangan, $tanggal, $jam_m, $jam_s, $nama, $id_jadwal));
    cekSqlError($stmt);

    header("Location: index.php?status=success&msg=Jadwal berhasil diperbarui!"); exit();
}

// ════════════════════════════════════════════════════════
// 3. PROSES HAPUS & TOGGLE
// ════════════════════════════════════════════════════════
if (isset($_GET['delete_id'])) {
    $stmt = sqlsrv_query($conn, "UPDATE Jadwal SET Is_Deleted = 1, Deleted_By = ?, Deleted_Date = GETDATE() WHERE ID_Jadwal = ?", array($nama, $_GET['delete_id']));
    header("Location: index.php?status=success&msg=Jadwal disembunyikan (soft delete)."); exit();
}
if (isset($_GET['toggle_id']) && isset($_GET['s'])) {
    $s_baru = ($_GET['s'] == 1) ? 0 : 1;
    $stmt = sqlsrv_query($conn, "UPDATE Jadwal SET Status = ? WHERE ID_Jadwal = ?", array($s_baru, $_GET['toggle_id']));
    header("Location: index.php?status=success&msg=Status jadwal berhasil diubah!"); exit();
}

// ════════════════════════════════════════════════════════
// 4. QUERY LIST & STATISTIK
// ════════════════════════════════════════════════════════
$where = "J.Is_Deleted = 0";
$params = array();
if (isset($_GET['f_status']) && $_GET['f_status'] !== '') {
    $where .= " AND J.Status = ?";
    $params[] = $_GET['f_status'];
}

$order = "J.Tanggal DESC, J.Jam_Mulai ASC";
if (isset($_GET['f_sort'])) {
    if ($_GET['f_sort'] == 'baru_lama') $order = "J.Tanggal DESC, J.Jam_Mulai DESC";
    if ($_GET['f_sort'] == 'lama_baru') $order = "J.Tanggal ASC, J.Jam_Mulai ASC";
    if ($_GET['f_sort'] == 'a_z') $order = "L.Nama_Lapangan ASC";
}

$sql = "SELECT J.*, L.Nama_Lapangan FROM Jadwal J JOIN Lapangan L ON J.ID_Lapangan = L.ID_Lapangan WHERE $where ORDER BY $order";
$query = sqlsrv_query($conn, $sql, $params);
cekSqlError($query);

// STATS
$q_tot = sqlsrv_query($conn, "SELECT COUNT(*) as t FROM Jadwal WHERE Is_Deleted = 0");
$tot_jadwal = sqlsrv_fetch_array($q_tot, SQLSRV_FETCH_ASSOC)['t'] ?? 0;
$q_aktif = sqlsrv_query($conn, "SELECT COUNT(*) as t FROM Jadwal WHERE Is_Deleted = 0 AND Status = 1");
$tot_aktif = sqlsrv_fetch_array($q_aktif, SQLSRV_FETCH_ASSOC)['t'] ?? 0;
$tot_booked = $tot_jadwal - $tot_aktif;

// LAPANGAN DROPDOWN (Hanya yang aktif)
$q_lapangan = sqlsrv_query($conn, "SELECT ID_Lapangan, Nama_Lapangan FROM Lapangan WHERE Status = 1 AND Is_Deleted = 0");
$lapangan_list = [];
while($l = sqlsrv_fetch_array($q_lapangan, SQLSRV_FETCH_ASSOC)) { $lapangan_list[] = $l; }

// MODAL DATA
$detail_data = null;
if (isset($_GET['detail_id'])) {
    $q_det = sqlsrv_query($conn, "SELECT J.*, L.Nama_Lapangan FROM Jadwal J JOIN Lapangan L ON J.ID_Lapangan = L.ID_Lapangan WHERE J.ID_Jadwal = ?", array($_GET['detail_id']));
    if ($q_det) $detail_data = sqlsrv_fetch_array($q_det, SQLSRV_FETCH_ASSOC);
}
$edit_data = null;
if (isset($_GET['edit_id'])) {
    $q_edit = sqlsrv_query($conn, "SELECT * FROM Jadwal WHERE ID_Jadwal = ?", array($_GET['edit_id']));
    if ($q_edit) $edit_data = sqlsrv_fetch_array($q_edit, SQLSRV_FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Master Jadwal | HoopBall</title>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800;900&family=Barlow:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="jadwal.css">
</head>
<body>

    <!-- ════════════ MODAL TAMBAH JADWAL ════════════ -->
    <div class="modal-overlay" id="modalAddJadwal">
        <div class="modal-box">
            <button class="modal-close" onclick="closeAddModal()"><i class="fa-solid fa-xmark"></i></button>
            <div class="modal-header">
                <div class="modal-subtitle">Master Jadwal</div>
                <div class="modal-title">Tambah Jadwal Baru</div>
            </div>
            <div class="modal-body">
                <div class="info-box">
                    <i class="fa-solid fa-circle-info"></i> Durasi main wajib 1 Jam / 1.5 Jam / 2 Jam / 3 Jam.
                </div>
                <form method="POST" id="formAddJadwal" onsubmit="return validateJadwalAdd()" novalidate>
                    
                    <label class="modal-label">Pilih Lapangan <span class="required">*</span></label>
                    <select name="lapangan_add" id="lapangan_add" class="modal-input" required>
                        <?php foreach($lapangan_list as $lap): ?>
                            <option value="<?= $lap['ID_Lapangan'] ?>"><?= htmlspecialchars($lap['Nama_Lapangan']) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label class="modal-label">Tanggal Main <span class="required">*</span></label>
                    <input type="date" name="tanggal_add" id="tanggal_add" class="modal-input">
                    <div class="val-msg" id="val-tanggal_add"></div>

                    <div class="form-grid">
                        <div>
                            <label class="modal-label">Jam Mulai <span class="required">*</span></label>
                            <input type="time" name="jam_mulai_add" id="jam_mulai_add" class="modal-input">
                            <div class="val-msg" id="val-jam_mulai_add"></div>
                        </div>
                        <div>
                            <label class="modal-label">Jam Selesai <span class="required">*</span></label>
                            <input type="time" name="jam_selesai_add" id="jam_selesai_add" class="modal-input">
                            <div class="val-msg" id="val-jam_selesai_add"></div>
                        </div>
                    </div>

                    <button type="submit" name="save_jadwal_add" class="btn-submit">
                        <i class="fa-solid fa-plus"></i> Simpan Jadwal
                    </button>
                    <a onclick="closeAddModal()" class="btn-cancel">Batal</a>
                </form>
            </div>
        </div>
    </div>

    <!-- ════════════ MODAL EDIT JADWAL ════════════ -->
    <div class="modal-overlay <?= $edit_data ? 'open' : '' ?>" id="modalEditJadwal">
        <div class="modal-box">
            <button class="modal-close" onclick="closeEditModal()"><i class="fa-solid fa-xmark"></i></button>
            <div class="modal-header">
                <div class="modal-subtitle">Master Jadwal</div>
                <div class="modal-title">Edit Data Jadwal</div>
            </div>
            <div class="modal-body">
                <div class="info-box">
                    <i class="fa-solid fa-circle-info"></i> Durasi main wajib 1 Jam / 1.5 Jam / 2 Jam / 3 Jam.
                </div>
                <form method="POST" id="formEditJadwal" onsubmit="return validateJadwalEdit()" novalidate>
                    <input type="hidden" name="id_jadwal_edit" value="<?= $edit_data['ID_Jadwal'] ?? '' ?>">

                    <label class="modal-label">Pilih Lapangan <span class="required">*</span></label>
                    <select name="lapangan_edit" id="lapangan_edit" class="modal-input" required>
                        <?php foreach($lapangan_list as $lap): ?>
                            <option value="<?= $lap['ID_Lapangan'] ?>" <?= (isset($edit_data) && $edit_data['ID_Lapangan'] == $lap['ID_Lapangan']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($lap['Nama_Lapangan']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <?php 
                        $curr_tgl = isset($edit_data) && is_object($edit_data['Tanggal']) ? $edit_data['Tanggal']->format('Y-m-d') : ($edit_data['Tanggal'] ?? '');
                        $curr_jm = isset($edit_data) && is_object($edit_data['Jam_Mulai']) ? $edit_data['Jam_Mulai']->format('H:i') : (isset($edit_data) ? substr($edit_data['Jam_Mulai'], 0, 5) : '');
                        $curr_js = isset($edit_data) && is_object($edit_data['Jam_Selesai']) ? $edit_data['Jam_Selesai']->format('H:i') : (isset($edit_data) ? substr($edit_data['Jam_Selesai'], 0, 5) : '');
                    ?>

                    <label class="modal-label">Tanggal Main <span class="required">*</span></label>
                    <input type="date" name="tanggal_edit" id="tanggal_edit" class="modal-input" value="<?= $curr_tgl ?>">
                    <div class="val-msg" id="val-tanggal_edit"></div>

                    <div class="form-grid">
                        <div>
                            <label class="modal-label">Jam Mulai <span class="required">*</span></label>
                            <input type="time" name="jam_mulai_edit" id="jam_mulai_edit" class="modal-input" value="<?= $curr_jm ?>">
                            <div class="val-msg" id="val-jam_mulai_edit"></div>
                        </div>
                        <div>
                            <label class="modal-label">Jam Selesai <span class="required">*</span></label>
                            <input type="time" name="jam_selesai_edit" id="jam_selesai_edit" class="modal-input" value="<?= $curr_js ?>">
                            <div class="val-msg" id="val-jam_selesai_edit"></div>
                        </div>
                    </div>

                    <button type="submit" name="save_jadwal_edit" class="btn-submit">
                        <i class="fa-solid fa-floppy-disk"></i> Simpan Perubahan
                    </button>
                    <a onclick="closeEditModal()" class="btn-cancel">Batal</a>
                </form>
            </div>
        </div>
    </div>

    <!-- ════════════ MODAL DETAIL JADWAL ════════════ -->
    <div class="modal-overlay <?= $detail_data ? 'open' : '' ?>">
        <div class="modal-box" style="width: 440px;">
            <button class="modal-close" onclick="window.location.href='index.php'"><i class="fa-solid fa-xmark"></i></button>
            <div class="modal-header" style="border-bottom: none;">
                <div class="modal-subtitle">Informasi Jadwal</div>
                <div class="modal-title">Detail Jadwal Lapangan</div>
            </div>
            <div class="modal-body" style="padding-top:0;">
                <?php if($detail_data): 
                    $tgl = is_object($detail_data['Tanggal']) ? $detail_data['Tanggal']->format('d M Y') : $detail_data['Tanggal'];
                    $jm = is_object($detail_data['Jam_Mulai']) ? $detail_data['Jam_Mulai']->format('H:i') : substr($detail_data['Jam_Mulai'], 0, 5);
                    $js = is_object($detail_data['Jam_Selesai']) ? $detail_data['Jam_Selesai']->format('H:i') : substr($detail_data['Jam_Selesai'], 0, 5);
                ?>
                    <div class="detail-photo-card">
                        <div class="detail-icon-wrap"><i class="fa-solid fa-calendar-days"></i></div>
                        <div class="detail-main-name"><?= htmlspecialchars($detail_data['Nama_Lapangan']) ?></div>
                    </div>
                    <div class="info-row">
                        <span class="info-key"><i class="fa-solid fa-fingerprint"></i> ID Jadwal</span>
                        <span class="info-val id-text"><?= $detail_data['ID_Jadwal'] ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-key"><i class="fa-solid fa-calendar"></i> Tanggal Main</span>
                        <span class="info-val"><?= $tgl ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-key"><i class="fa-solid fa-clock"></i> Jam Main</span>
                        <span class="info-val time-text" style="font-size:20px;"><?= $jm ?> - <?= $js ?></span>
                    </div>
                    <div class="info-row" style="border:none;">
                        <span class="info-key"><i class="fa-solid fa-circle-check"></i> Status</span>
                        <span class="status-badge <?= ($detail_data['Status']==1) ? 'sb-aktif' : 'sb-habis' ?>">
                            <span class="status-dot"></span> <?= ($detail_data['Status']==1) ? 'TERSEDIA' : 'TIDAK TERSEDIA' ?>
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
                    <div class="page-title">Jadwal Lapangan Basket</div>
                </div>
                <div class="stat-chips">
                    <div class="stat-chip chip-green"><i class="fa-solid fa-check-circle"></i> TERSEDIA <span class="chip-val"><?= $tot_aktif ?></span></div>
                    <div class="stat-chip chip-red"><i class="fa-solid fa-ban"></i> BOOKED <span class="chip-val"><?= $tot_booked ?></span></div>
                    <div class="stat-chip chip-blue"><i class="fa-solid fa-calendar-days"></i> TOTAL JADWAL <span class="chip-val"><?= $tot_jadwal ?></span></div>
                </div>
            </div>

            <div class="action-bar">
                <div class="search-box">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="src" placeholder="Cari nama lapangan..." onkeyup="searchTable()">
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
                                        <option value="baru_lama" <?= ($_GET['f_sort'] ?? '') == 'baru_lama' ? 'selected' : '' ?>>Tanggal Terbaru</option>
                                        <option value="lama_baru" <?= ($_GET['f_sort'] ?? '') == 'lama_baru' ? 'selected' : '' ?>>Tanggal Terlama</option>
                                        <option value="a_z" <?= ($_GET['f_sort'] ?? '') == 'a_z' ? 'selected' : '' ?>>Nama Lapangan A-Z</option>
                                    </select>
                                </div>
                                <div class="filter-group">
                                    <label>Status Jadwal</label>
                                    <select name="f_status" class="filter-input">
                                        <option value="">Semua Status</option>
                                        <option value="1" <?= ($_GET['f_status'] ?? '') == '1' ? 'selected' : '' ?>>TERSEDIA</option>
                                        <option value="0" <?= ($_GET['f_status'] ?? '') == '0' ? 'selected' : '' ?>>TIDAK TERSEDIA</option>
                                    </select>
                                </div>
                                <div class="filter-buttons">
                                    <a href="index.php" class="btn-filter-reset">Reset</a>
                                    <button type="submit" class="btn-filter-apply">Terapkan</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <button class="btn-add" onclick="openAddModal()"><i class="fa-solid fa-plus"></i> Buat Jadwal</button>
                </div>
            </div>

            <div class="card table-wrap">
                <table class="data-table" id="tbl">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama Lapangan</th>
                            <th>Tgl & Jam Main</th>
                            <th>Durasi</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if ($query): $no = 1;
                            while($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)): 
                                $is_aktif = ($row['Status'] == 1); 
                                $tgl = is_object($row['Tanggal']) ? $row['Tanggal']->format('d M Y') : $row['Tanggal'];
                                $jm = is_object($row['Jam_Mulai']) ? $row['Jam_Mulai']->format('H:i') : substr($row['Jam_Mulai'], 0, 5);
                                $js = is_object($row['Jam_Selesai']) ? $row['Jam_Selesai']->format('H:i') : substr($row['Jam_Selesai'], 0, 5);
                                
                                // Hitung Durasi Menit
                                $start = strtotime($jm); $end = strtotime($js);
                                $mins = ($end - $start) / 60;
                                $jam = floor($mins / 60); $sisa_mnt = $mins % 60;
                                $durasi_text = $jam . ' Jam ' . ($sisa_mnt > 0 ? $sisa_mnt . ' Mnt' : '');
                        ?>
                        <tr>
                            <td class="id-text" style="text-align: center; color:var(--text);"><?= $no++ ?></td>
                            <td>
                                <div class="nama-text"><?= htmlspecialchars($row['Nama_Lapangan']) ?></div>
                                <div style="font-size:11px; color:var(--muted); font-weight:800; font-family:'Barlow Condensed';"><?= $row['ID_Jadwal'] ?></div>
                            </td>
                            <td>
                                <div style="font-weight:700; color:var(--muted); font-size:13px;"><i class="fa-solid fa-calendar-day"></i> <?= $tgl ?></div>
                                <div class="time-text"><i class="fa-regular fa-clock"></i> <?= $jm ?> - <?= $js ?></div>
                            </td>
                            <td style="font-weight:800; font-size:14px; color:var(--orange);"><?= $durasi_text ?></td>
                            <td style="text-align: center;">
                                <span class="status-badge <?= $is_aktif ? 'sb-aktif' : 'sb-habis' ?>">
                                    <span class="status-dot"></span> <?= $is_aktif ? 'Tersedia' : 'Booked' ?>
                                </span>
                            </td>
                            <td>
                                <div class="actions">
                                    <a href="?detail_id=<?= $row['ID_Jadwal'] ?>" class="btn-action btn-view" title="Detail"><i class="fa-solid fa-eye"></i></a>
                                    <a href="?edit_id=<?= $row['ID_Jadwal'] ?>" class="btn-action btn-edit" title="Edit"><i class="fa-solid fa-pen-to-square"></i></a>
                                    <label class="toggle-switch" title="Ubah Status">
                                        <input type="checkbox" <?= $is_aktif ? 'checked' : '' ?> onchange="confirmToggle('<?= $row['ID_Jadwal'] ?>', <?= $row['Status'] ?>)">
                                        <span class="toggle-slider"></span>
                                    </label>
                                    <button onclick="confirmDelete('<?= $row['ID_Jadwal'] ?>', '<?= htmlspecialchars($row['Nama_Lapangan']) ?>')" class="btn-action btn-delete" title="Hapus"><i class="fa-solid fa-trash-can"></i></button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- ════════════ SCRIPT JAVASCRIPT TERPISAH ════════════ -->
    <script src="function/tampilan.js"></script>
    <script src="function/togs.js"></script>
    <script src="function/delete.js"></script>
    <script src="function/add.js"></script>
    <script src="function/edit.js"></script>
</body>
</html>