<?php
session_start();
include '../includes/config.php'; 

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'karyawan') {
    echo "<script>window.location='../view_admin.php';</script>"; exit();
}

$nama = $_SESSION['nama'] ?? 'Karyawan';

// ── TOGGLE STATUS (SOFT DELETE / NONAKTIF) ──
if (isset($_GET['toggle_id']) && isset($_GET['s'])) {
    $id_toggle = $_GET['toggle_id'];
    $s_baru = ($_GET['s'] == 1) ? 0 : 1;
    $stmt = sqlsrv_query($conn, "UPDATE Alat SET Status = ? WHERE ID_Alat = ?", array($s_baru, $id_toggle));
    header("Location: index.php?status=success&msg=Status alat berhasil diubah!"); 
    exit();
}

// ── FILTER & SORTING ──
$where = "Is_Deleted = 0";
$params = array();

// Filter Status
if (isset($_GET['f_status']) && $_GET['f_status'] !== '') {
    $where .= " AND Status = ?";
    $params[] = $_GET['f_status'];
}

// Sorting
$order = "ID_Alat DESC";
if (isset($_GET['f_sort'])) {
    if ($_GET['f_sort'] == 'lama_baru') $order = "ID_Alat ASC";
    if ($_GET['f_sort'] == 'a_z') $order = "Nama_Alat ASC";
    if ($_GET['f_sort'] == 'z_a') $order = "Nama_Alat DESC";
}

$query = sqlsrv_query($conn, "SELECT * FROM Alat WHERE $where ORDER BY $order", $params);

// ── STATISTIK ──
$q_tot = sqlsrv_query($conn, "SELECT COUNT(*) as t FROM Alat WHERE Is_Deleted = 0");
$tot_alat = sqlsrv_fetch_array($q_tot, SQLSRV_FETCH_ASSOC)['t'] ?? 0;

$q_aktif = sqlsrv_query($conn, "SELECT COUNT(*) as t FROM Alat WHERE Is_Deleted = 0 AND Status = 1");
$tot_aktif = sqlsrv_fetch_array($q_aktif, SQLSRV_FETCH_ASSOC)['t'] ?? 0;
$tot_habis = $tot_alat - $tot_aktif;

// ── DETAIL DATA (MODAL) ──
$detail_data = null;
if (isset($_GET['detail_id'])) {
    $q_det = sqlsrv_query($conn, "SELECT * FROM Alat WHERE ID_Alat = ?", array($_GET['detail_id']));
    if ($q_det) $detail_data = sqlsrv_fetch_array($q_det, SQLSRV_FETCH_ASSOC);
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
                    <a href="action/create.php" class="btn-add"><i class="fa-solid fa-plus"></i> Tambah Alat</a>
                </div>
            </div>

            <div class="card">
                <table class="data-table" id="tbl">
                    <thead>
                        <tr>
                            <th style="width: 50px;">No</th>
                            <th style="width: 70px; text-align: center;">Foto</th>
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
                            <td class="id-text" style="color:var(--text);"><?= $no++ ?></td>
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
                            <td>
                                <span class="status-badge <?= $is_aktif ? 'sb-aktif' : 'sb-habis' ?>">
                                    <span class="status-dot"></span> <?= $is_aktif ? 'Aktif' : 'Nonaktif' ?>
                                </span>
                            </td>
                            <td>
                                <div class="actions">
                                    <a href="?detail_id=<?= $row['ID_Alat'] ?>" class="btn-action btn-view" title="Detail"><i class="fa-solid fa-eye"></i></a>
                                    <a href="action/update.php?id=<?= $row['ID_Alat'] ?>" class="btn-action btn-edit" title="Edit"><i class="fa-solid fa-pen-to-square"></i></a>
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

    <!-- MODAL DETAIL -->
    <div class="modal-overlay <?= $detail_data ? 'open' : '' ?>">
        <div class="modal-box">
            <button class="modal-close" onclick="window.location.href='index.php'"><i class="fa-solid fa-xmark"></i></button>
            <div class="modal-header">
                <div class="modal-subtitle">Informasi Alat</div>
                <div class="modal-title">Detail Alat Basket</div>
            </div>
            <div class="modal-body">
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
                        <span class="info-val harga-text">Rp <?= number_format($detail_data['Harga_Alat'],0,',','.') ?></span>
                    </div>
                    <div class="info-row" style="border:none;">
                        <span class="info-key"><i class="fa-solid fa-circle-check"></i> Status</span>
                        <span class="status-badge <?= ($detail_data['Status']==1) ? 'sb-aktif' : 'sb-habis' ?>">
                            <span class="status-dot"></span> <?= ($detail_data['Status']==1) ? 'AKTIF' : 'NONAKTIF' ?>
                        </span>
                    </div>
                    <button class="btn-kembali" onclick="window.location.href='index.php'"><i class="fa-solid fa-arrow-left"></i> Kembali</button>
                <?php endif; ?>
            </div>
        </div>
    </div>

<script>
// Jam Live
function updateClock() {
    const now = new Date();
    document.getElementById('h').innerText = String(now.getHours()).padStart(2, '0');
    document.getElementById('m').innerText = String(now.getMinutes()).padStart(2, '0');
    document.getElementById('s').innerText = String(now.getSeconds()).padStart(2, '0');
    const d = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    const m = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    document.getElementById('full-date').innerText = `${d[now.getDay()]}, ${now.getDate()} ${m[now.getMonth()]} ${now.getFullYear()}`;
}
setInterval(updateClock, 1000); updateClock();

// Search Table
function searchTable() {
    var input = document.getElementById('src').value.toUpperCase();
    var rows = document.getElementById('tbl').getElementsByTagName('tr');
    for (var i = 1; i < rows.length; i++) {
        var tdName = rows[i].getElementsByTagName('td')[2];
        if (tdName) {
            rows[i].style.display = (tdName.textContent.toUpperCase().indexOf(input) > -1) ? '' : 'none';
        }
    }
}

// Filter Dropdown
document.getElementById('btnFilterToggle').addEventListener('click', function(e) {
    e.stopPropagation(); document.getElementById('filterCard').classList.toggle('open');
});
document.addEventListener('click', function() { document.getElementById('filterCard').classList.remove('open'); });
document.getElementById('filterCard').addEventListener('click', function(e) { e.stopPropagation(); });

// Toggle & Delete
function confirmToggle(id, status) {
    let action = status == 1 ? 'menonaktifkan' : 'mengaktifkan';
    Swal.fire({
        title: 'Konfirmasi', text: `Yakin ingin ${action} alat ini?`, icon: 'warning',
        showCancelButton: true, confirmButtonColor: '#FF4500', confirmButtonText: 'Ya, Ubah!', cancelButtonText: 'Batal'
    }).then((res) => {
        if(res.isConfirmed) window.location.href = `?toggle_id=${id}&s=${status}`;
        else { let cb = document.querySelector(`input[onchange*="${id}"]`); if(cb) cb.checked = !cb.checked; }
    });
}
function confirmDelete(id, name) {
    Swal.fire({
        title: 'Hapus Alat?', html: `Hapus <strong style="color:red">${name}</strong> secara permanen?`, icon: 'error',
        showCancelButton: true, confirmButtonColor: '#EF4444', confirmButtonText: 'Hapus', cancelButtonText: 'Batal'
    }).then((res) => { if(res.isConfirmed) window.location.href = `action/delete.php?id=${id}`; });
}

// SweetAlert Msg
const urlParams = new URLSearchParams(window.location.search);
if(urlParams.get('status')) {
    Swal.fire({ icon: urlParams.get('status'), title: urlParams.get('msg'), toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 });
    window.history.replaceState(null, null, window.location.pathname);
}
</script>
</body>
</html>