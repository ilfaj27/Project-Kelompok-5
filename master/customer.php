<?php
session_start();
include '../includes/config.php';

if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'karyawan' && $_SESSION['role'] !== 'pemilik')) {
    echo "<script>alert('Akses Ditolak!'); window.location='../dashboard.php';</script>";
    exit();
}
$role = $_SESSION['role'];
$nama = $_SESSION['nama'];
$map_jk = [1 => 'Laki-laki', 2 => 'Perempuan'];

if (isset($_GET['delete_id'])) {
    $stmt = sqlsrv_query($conn, "DELETE FROM Customer WHERE ID_Customer = ?", array($_GET['delete_id']));
    header($stmt ? "Location: customer.php?status=success&msg=Data customer berhasil dihapus!" : "Location: customer.php?status=error&msg=Gagal menghapus, data mungkin terikat transaksi!");
    exit();
}

$q_total    = sqlsrv_query($conn, "SELECT COUNT(*) as t FROM Customer");
$total_cust = sqlsrv_fetch_array($q_total, SQLSRV_FETCH_ASSOC)['t'] ?? 0;
$q_laki     = sqlsrv_query($conn, "SELECT COUNT(*) as t FROM Customer WHERE Jenis_Kelamin=1");
$total_laki = sqlsrv_fetch_array($q_laki, SQLSRV_FETCH_ASSOC)['t'] ?? 0;
$q_perempuan = sqlsrv_query($conn, "SELECT COUNT(*) as t FROM Customer WHERE Jenis_Kelamin=2");
$total_perempuan = sqlsrv_fetch_array($q_perempuan, SQLSRV_FETCH_ASSOC)['t'] ?? 0;

$query = sqlsrv_query($conn, "SELECT * FROM Customer ORDER BY ID_Customer ASC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Data Customer | HoopBall</title>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800;900&family=Barlow:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
:root {
    --orange:    #FF4500;
    --orange-lt: rgba(255,69,0,.12);
    --green:     #10B981;
    --blue:      #3B82F6;
    --pink:      #EC4899;
    --red:       #EF4444;
    --sidebar:   #0D1117;
    --sidebar-w: 260px;
    --topbar-h:  70px;
    --card-bg:   #fff;
    --border:    #E5E7EB;
    --text:      #111827;
    --muted:     #6B7280;
    --bg:        #F3F4F6;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Barlow', sans-serif; background: var(--bg); display: flex; min-height: 100vh; color: var(--text); }

/* SIDEBAR */
.sidebar { width: var(--sidebar-w); background: var(--sidebar); height: 100vh; position: fixed; top: 0; left: 0; display: flex; flex-direction: column; padding: 28px 18px; border-right: 1px solid rgba(255,255,255,.04); z-index: 200; }
.sb-brand { display: flex; align-items: center; gap: 12px; padding: 0 8px; margin-bottom: 36px; text-decoration: none; }
.sb-icon { width: 40px; height: 40px; background: var(--orange); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 18px; flex-shrink: 0; box-shadow: 0 4px 14px rgba(255,69,0,.4); }
.sb-brand-name { font-family: 'Barlow Condensed', sans-serif; font-size: 20px; font-weight: 900; color: #fff; letter-spacing: 1px; }
.sb-brand-sub { font-size: 9px; color: #4B5563; font-weight: 700; text-transform: uppercase; }
.sb-section-label { font-size: 10px; font-weight: 800; text-transform: uppercase; color: #374151; letter-spacing: .8px; padding: 0 10px; margin: 22px 0 8px; }
.sb-link { display: flex; align-items: center; gap: 12px; color: #6B7280; text-decoration: none; padding: 10px 12px; border-radius: 10px; margin-bottom: 2px; font-size: 13px; font-weight: 600; transition: background .2s, color .2s; }
.sb-link .sb-icon-wrap { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 13px; transition: .2s; flex-shrink: 0; background: rgba(255,255,255,.04); }
.sb-link:hover { color: #E5E7EB; }
.sb-link:hover .sb-icon-wrap { background: rgba(255,255,255,.08); }
.sb-link.active { color: #fff; background: rgba(255,69,0,.1); }
.sb-link.active .sb-icon-wrap { background: var(--orange); color: #fff; }
.sb-link .badge { margin-left: auto; background: var(--orange); color: #fff; font-size: 10px; font-weight: 800; padding: 2px 7px; border-radius: 20px; }
.sb-bottom { margin-top: auto; }
.sb-user { display: flex; align-items: center; gap: 10px; background: rgba(255,255,255,.04); border-radius: 12px; padding: 12px; border: 1px solid rgba(255,255,255,.06); }
.sb-avatar { width: 36px; height: 36px; background: var(--orange); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 14px; flex-shrink: 0; }
.sb-user-name { font-size: 13px; font-weight: 800; color: #E5E7EB; line-height: 1.1; }
.sb-user-role { font-size: 10px; color: var(--orange); font-weight: 700; }
.sb-logout { margin-left: auto; color: #4B5563; font-size: 13px; transition: .2s; cursor: pointer; text-decoration: none; }
.sb-logout:hover { color: var(--red); }

/* MAIN */
.main { margin-left: var(--sidebar-w); flex: 1; display: flex; flex-direction: column; min-height: 100vh; }

/* TOPBAR */
.topbar { background: var(--card-bg); height: var(--topbar-h); padding: 0 40px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 100; }
.topbar-left { display: flex; flex-direction: column; }
.topbar-title { font-family: 'Barlow Condensed', sans-serif; font-size: 26px; font-weight: 900; color: var(--text); letter-spacing: -.5px; line-height: 1; }
.topbar-breadcrumb { font-size: 12px; color: var(--muted); font-weight: 600; margin-top: 2px; }
.topbar-right { display: flex; align-items: center; gap: 16px; }
.topbar-btn { width: 38px; height: 38px; border-radius: 10px; background: var(--bg); border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; color: var(--muted); cursor: pointer; font-size: 14px; text-decoration: none; transition: .2s; position: relative; }
.topbar-btn:hover { border-color: var(--orange); color: var(--orange); }
.notif-dot { position: absolute; top: 7px; right: 7px; width: 7px; height: 7px; background: var(--orange); border-radius: 50%; border: 2px solid #fff; }
.dropdown-wrap { position: relative; }
.topbar-user { display: flex; align-items: center; gap: 10px; background: var(--bg); border: 1px solid var(--border); padding: 6px 14px 6px 8px; border-radius: 12px; cursor: pointer; transition: .2s; }
.topbar-user:hover { border-color: var(--orange); }
.t-avatar { width: 32px; height: 32px; background: var(--orange); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 13px; }
.t-name { font-size: 13px; font-weight: 800; color: var(--text); line-height: 1.1; }
.t-role { font-size: 10px; color: var(--orange); font-weight: 700; }
.t-chevron { color: var(--muted); font-size: 10px; margin-left: 4px; }
.dropdown-menu { display: none; position: absolute; right: 0; top: calc(100% + 8px); background: #fff; min-width: 180px; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 15px 40px rgba(0,0,0,.12); overflow: hidden; padding: 8px 0; z-index: 999; }
.dropdown-wrap:hover .dropdown-menu { display: block; }
.dd-item { display: flex; align-items: center; gap: 10px; padding: 11px 16px; color: #444; text-decoration: none; font-size: 13px; font-weight: 700; transition: .15s; }
.dd-item:hover { background: #FFF7ED; color: var(--orange); }
.dd-item i { font-size: 14px; width: 18px; text-align: center; }
.dd-divider { border: none; border-top: 1px solid #F3F4F6; margin: 4px 0; }

/* CONTENT */
.content { padding: 32px 40px; flex: 1; }
.page-header { display: flex; align-items: flex-end; justify-content: space-between; margin-bottom: 24px; }
.page-title-tag { width: 36px; height: 4px; background: var(--orange); border-radius: 2px; margin-bottom: 8px; }
.page-title { font-family: 'Barlow Condensed', sans-serif; font-size: 30px; font-weight: 900; color: var(--text); text-transform: uppercase; }

/* STAT CHIPS */
.stat-chips { display: flex; gap: 10px; }
.stat-chip { display: flex; align-items: center; gap: 8px; padding: 8px 18px; border-radius: 10px; font-size: 12px; font-weight: 700; }
.chip-total  { background: var(--bg);                   color: #374151; border: 1px solid var(--border); }
.chip-blue   { background: rgba(59,130,246,.1);         color: var(--blue); }
.chip-pink   { background: rgba(236,72,153,.1);         color: var(--pink); }
.chip-val    { font-family: 'Barlow Condensed'; font-size: 20px; font-weight: 900; }

/* ACTION BAR */
.action-bar { margin-bottom: 20px; }
.search-box { position: relative; width: 300px; }
.search-box i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: 13px; }
.search-box input { width: 100%; padding: 10px 14px 10px 40px; background: var(--card-bg); border: 1.5px solid var(--border); border-radius: 10px; font-size: 13px; font-family: 'Barlow', sans-serif; outline: none; transition: .2s; }
.search-box input:focus { border-color: var(--orange); }

/* TABLE */
.card { background: var(--card-bg); border-radius: 18px; border: 1px solid var(--border); overflow: hidden; }
.table-wrap { overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table th { padding: 13px 20px; font-size: 10px; font-weight: 800; color: var(--muted); text-transform: uppercase; letter-spacing: .6px; border-bottom: 2px solid var(--bg); text-align: left; }
.data-table td { padding: 16px 20px; font-size: 13px; border-bottom: 1px solid var(--bg); vertical-align: middle; }
.data-table tr:last-child td { border-bottom: none; }
.data-table tbody tr:hover td { background: #FAFAFA; }
.cust-id   { color: var(--orange); font-weight: 800; font-family: 'Barlow Condensed'; font-size: 16px; }
.cust-name { font-weight: 700; color: var(--text); }
.gender-badge { display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; border-radius: 8px; font-size: 11px; font-weight: 700; }
.gb-laki     { background: rgba(59,130,246,.1);  color: var(--blue); }
.gb-perempuan{ background: rgba(236,72,153,.1);  color: var(--pink); }
.actions { display: flex; gap: 4px; justify-content: flex-end; }
.btn-act { width: 34px; height: 34px; border: none; background: none; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; color: var(--muted); cursor: pointer; font-size: 14px; transition: .2s; }
.btn-act:hover     { background: var(--bg); }
.btn-act.del:hover { color: var(--red); background: #FEF2F2; }
</style>
</head>
<body>

<!-- ═══ SIDEBAR ═══ -->
<aside class="sidebar">
    <a href="../dashboard.php" class="sb-brand">
        <div class="sb-icon"><i class="fa-solid fa-basketball"></i></div>
        <div>
            <div class="sb-brand-name">HOOP BALL</div>
            <div class="sb-brand-sub">Management System</div>
        </div>
    </a>
    <div class="sb-section-label">Menu Utama</div>
    <nav>
        <a href="../dashboard.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-house"></i></div> Dashboard
        </a>
        <a href="../kelola_booking.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-calendar-check"></i></div> Booking
            <span class="badge">3</span>
        </a>
        <a href="lapangan.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-layer-group"></i></div> Lapangan
        </a>
        <a href="customer.php" class="sb-link active">
            <div class="sb-icon-wrap"><i class="fa-solid fa-users"></i></div> Customer
        </a>
        <a href="promo.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-tags"></i></div> Promo
        </a>
    </nav>
    <div class="sb-section-label">Akun</div>
    <a href="../profile.php" class="sb-link">
        <div class="sb-icon-wrap"><i class="fa-solid fa-id-badge"></i></div> Profil Saya
    </a>
    <a href="../riwayat.php" class="sb-link">
        <div class="sb-icon-wrap"><i class="fa-solid fa-clock-rotate-left"></i></div> Riwayat
    </a>
    <div class="sb-bottom">
        <div class="sb-user">
            <div class="sb-avatar"><i class="fa-solid fa-user"></i></div>
            <div>
                <div class="sb-user-name"><?= strtoupper(htmlspecialchars($nama)) ?></div>
                <div class="sb-user-role"><?= strtoupper($role) ?></div>
            </div>
            <a href="../logout.php" class="sb-logout" title="Keluar"><i class="fa-solid fa-right-from-bracket"></i></a>
        </div>
    </div>
</aside>

<!-- ═══ MAIN ═══ -->
<main class="main">
    <header class="topbar">
        <div class="topbar-left">
            <div class="topbar-title">Data Customer</div>
            <div class="topbar-breadcrumb">Manajemen / Data Customer</div>
        </div>
        <div class="topbar-right">
            <a href="#" class="topbar-btn"><i class="fa-solid fa-magnifying-glass"></i></a>
            <a href="#" class="topbar-btn">
                <i class="fa-solid fa-bell"></i>
                <span class="notif-dot"></span>
            </a>
            <div class="dropdown-wrap">
                <div class="topbar-user">
                    <div class="t-avatar"><i class="fa-solid fa-user"></i></div>
                    <div>
                        <div class="t-name"><?= strtoupper(htmlspecialchars($nama)) ?></div>
                        <div class="t-role"><?= strtoupper($role) ?></div>
                    </div>
                    <i class="fa-solid fa-chevron-down t-chevron"></i>
                </div>
                <div class="dropdown-menu">
                    <a href="../profile.php" class="dd-item"><i class="fa-solid fa-id-badge"></i> Profil Saya</a>
                    <hr class="dd-divider">
                    <a href="../logout.php" class="dd-item" style="color:var(--red);"><i class="fa-solid fa-right-from-bracket"></i> Keluar</a>
                </div>
            </div>
        </div>
    </header>

    <div class="content">
        <div class="page-header">
            <div>
                <div class="page-title-tag"></div>
                <div class="page-title">Data Customer</div>
            </div>
            <div class="stat-chips">
                <div class="stat-chip chip-total">
                    <i class="fa-solid fa-users"></i> TOTAL <span class="chip-val"><?= $total_cust ?></span>
                </div>
                <div class="stat-chip chip-blue">
                    <i class="fa-solid fa-mars"></i> LAKI-LAKI <span class="chip-val"><?= $total_laki ?></span>
                </div>
                <div class="stat-chip chip-pink">
                    <i class="fa-solid fa-venus"></i> PEREMPUAN <span class="chip-val"><?= $total_perempuan ?></span>
                </div>
            </div>
        </div>

        <div class="action-bar">
            <div class="search-box">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="src" placeholder="Cari nama customer..." onkeyup="searchTable()">
            </div>
        </div>

        <div class="card">
            <div class="table-wrap">
                <table class="data-table" id="tbl">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nama Customer</th>
                            <th>Jenis Kelamin</th>
                            <th>Alamat</th>
                            <th>No. Telepon</th>
                            <th style="text-align:right;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $has_data = false;
                    while ($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)):
                        $has_data = true;
                        $is_laki = ($row['Jenis_Kelamin'] == 1);
                    ?>
                        <tr>
                            <td class="cust-id"><?= htmlspecialchars($row['ID_Customer']) ?></td>
                            <td class="cust-name"><?= htmlspecialchars($row['Nama_Customer']) ?></td>
                            <td>
                                <span class="gender-badge <?= $is_laki ? 'gb-laki' : 'gb-perempuan' ?>">
                                    <i class="fa-solid <?= $is_laki ? 'fa-mars' : 'fa-venus' ?>"></i>
                                    <?= $map_jk[$row['Jenis_Kelamin']] ?? '-' ?>
                                </span>
                            </td>
                            <td style="color:var(--muted);"><?= htmlspecialchars($row['Alamat']) ?></td>
                            <td style="color:var(--muted);"><?= htmlspecialchars($row['No_Telepon']) ?></td>
                            <td>
                                <div class="actions">
                                    <button onclick="confirmDelete('<?= $row['ID_Customer'] ?>')" class="btn-act del" title="Hapus">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    <?php if (!$has_data): ?>
                        <tr><td colspan="6" style="text-align:center; padding:50px; color:var(--muted);">
                            <i class="fa-solid fa-users" style="font-size:32px; opacity:.3; display:block; margin-bottom:12px;"></i>
                            Belum ada data customer
                        </td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<script>
function searchTable() {
    var input = document.getElementById('src').value.toUpperCase();
    var rows = document.getElementById('tbl').getElementsByTagName('tr');
    for (var i = 1; i < rows.length; i++) {
        var td = rows[i].getElementsByTagName('td')[1];
        if (td) rows[i].style.display = td.textContent.toUpperCase().indexOf(input) > -1 ? '' : 'none';
    }
}

function confirmDelete(id) {
    Swal.fire({
        title: 'Hapus Customer?',
        text: 'Data akan dihapus permanen dan tidak bisa dikembalikan!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#EF4444',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) window.location.href = '?delete_id=' + id;
    });
}

const urlParams = new URLSearchParams(window.location.search);
const status = urlParams.get('status');
const msg = urlParams.get('msg');
if (status && msg) {
    Swal.fire({ icon: status === 'success' ? 'success' : 'error', title: status === 'success' ? 'Berhasil!' : 'Gagal!', text: msg, timer: 3000, showConfirmButton: false });
    window.history.replaceState({}, document.title, window.location.pathname);
}
</script>
</body>
</html>