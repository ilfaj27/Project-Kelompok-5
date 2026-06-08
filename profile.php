<?php
session_start();
include 'includes/config.php';

if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

$role      = $_SESSION['role'];
$nama      = $_SESSION['nama'];
$id_akun   = $_SESSION['id_akun'];

// Fetch profile data (Akun)
$res = sqlsrv_query($conn, "SELECT * FROM Akun WHERE ID_Akun = ?", array($id_akun));
$akun = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC);

// --- TAMBAHAN: Ambil data dari tabel Customer jika rolenya customer ---
$customer_data = null;
if ($role === 'customer') {
    $res_cust = sqlsrv_query($conn, "SELECT * FROM Customer WHERE ID_Akun = ?", array($id_akun));
    $customer_data = sqlsrv_fetch_array($res_cust, SQLSRV_FETCH_ASSOC);
}
// ---------------------------------------------------------------------

// Proses update profil
$success_msg = '';
$error_msg   = '';
if (isset($_POST['update_profile'])) {
    $new_email    = trim($_POST['email']);
    $new_password = trim($_POST['password']);
    $old_password = trim($_POST['old_password']);

    // Validasi password lama
    if ($akun['Kata_Sandi'] !== $old_password) {
        $error_msg = 'Password lama tidak sesuai.';
    } else {
        $final_pass = $new_password ?: $akun['Kata_Sandi'];
        sqlsrv_query($conn, "UPDATE Akun SET Email=?, Kata_Sandi=? WHERE ID_Akun=?",
            array($new_email, $final_pass, $id_akun));
        $_SESSION['nama'] = $new_email;
        $success_msg = 'Profil berhasil diperbarui!';
        // Refresh data
        $res  = sqlsrv_query($conn, "SELECT * FROM Akun WHERE ID_Akun = ?", array($id_akun));
        $akun = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC);
    }
}

$is_admin    = in_array($role, ['pemilik', 'manajer', 'karyawan']);
$role_labels = ['pemilik' => 'Pemilik', 'manajer' => 'Manajer', 'karyawan' => 'Karyawan', 'customer' => 'Customer'];
$role_label  = $role_labels[$role] ?? ucfirst($role);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Profil Saya | HoopBall</title>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800;900&family=Barlow:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
/* CSS tetap sama seperti kode Anda sebelumnya */
:root {
    --orange:     #FF4500;
    --green:      #10B981;
    --red:        #EF4444;
    --sidebar-bg: #111827;
    --sidebar-w:  260px;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Barlow', sans-serif; min-height: 100vh; }

/* ══════════ ADMIN LAYOUT ══════════ */
body.admin-layout { background: #F3F4F6; display: flex; }
.sidebar { width: var(--sidebar-w); background: var(--sidebar-bg); height: 100vh; position: fixed; padding: 30px 20px; }
.sidebar-brand { display: flex; align-items: center; gap: 10px; margin-bottom: 40px; text-decoration: none; }
.sidebar-brand i { color: var(--orange); font-size: 22px; }
.sidebar-brand span { font-family: 'Barlow Condensed', sans-serif; font-size: 22px; font-weight: 900; color: #fff; }
.nav-label { color: #4B5563; font-size: 10px; font-weight: 800; text-transform: uppercase; margin: 18px 0 8px 15px; }
.sidebar-link { display: flex; align-items: center; gap: 14px; color: #9CA3AF; text-decoration: none; padding: 11px 15px; border-radius: 10px; margin-bottom: 4px; font-size: 13px; font-weight: 600; transition: .2s; }
.sidebar-link:hover { color: #fff; background: rgba(255,255,255,.05); }
.sidebar-link.active { background: rgba(255,69,0,.12); color: #fff; }
.sidebar-link.active i { color: var(--orange); }
.admin-main { margin-left: var(--sidebar-w); flex: 1; }
.topbar { background: #fff; padding: 0 40px; height: 70px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #E5E7EB; position: sticky; top: 0; z-index: 50; }
.topbar h2 { font-weight: 900; font-size: 20px; color: #111; text-transform: uppercase; }
.user-pill { display: flex; align-items: center; gap: 12px; }
.user-pill .name-info .uname { font-size: 13px; font-weight: 800; color: #111; }
.user-pill .name-info .utag  { font-size: 9px; color: var(--orange); font-weight: 900; }
.user-avatar-sm { width: 40px; height: 40px; background: var(--orange); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; }

/* ══════════ CUSTOMER LAYOUT ══════════ */
body.customer-layout { background: #0A0A0A; }
.cust-nav { background: #111; border-bottom: 1px solid rgba(255,255,255,.07); padding: 0 60px; height: 65px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 100; }
.cust-nav-brand { display: flex; align-items: center; gap: 10px; text-decoration: none; }
.cust-nav-brand i { color: var(--orange); font-size: 20px; }
.cust-nav-brand span { font-family: 'Barlow Condensed', sans-serif; font-size: 20px; font-weight: 900; color: #fff; }
.cust-nav-links { display: flex; gap: 30px; }
.cust-nav-link { color: #9CA3AF; text-decoration: none; font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: .8px; transition: .2s; }
.cust-nav-link:hover { color: #fff; }
.cust-nav-link.active { color: var(--orange); }
.cust-nav-right { display: flex; align-items: center; gap: 14px; }
.cust-logout { color: #9CA3AF; text-decoration: none; font-size: 12px; font-weight: 700; display: flex; align-items: center; gap: 6px; transition: .2s; }
.cust-logout:hover { color: var(--red); }

/* ══════════ SHARED PROFILE CONTENT ══════════ */
.profile-content { padding: 40px; max-width: 900px; margin: 0 auto; }
.admin-layout .profile-content { margin: 0; max-width: 100%; }
.profile-hero { border-radius: 20px; padding: 40px; display: flex; align-items: center; gap: 30px; margin-bottom: 28px; position: relative; overflow: hidden; }
.admin-layout .profile-hero { background: linear-gradient(135deg, #1F2937 0%, #111827 100%); border: 1px solid #374151; }
.customer-layout .profile-hero { background: linear-gradient(135deg, #1a0a00 0%, #0A0A0A 100%); border: 1px solid rgba(255,69,0,.2); }
.avatar-ring { width: 90px; height: 90px; border-radius: 50%; background: linear-gradient(135deg, var(--orange), #ff7043); display: flex; align-items: center; justify-content: center; font-size: 36px; color: #fff; flex-shrink: 0; box-shadow: 0 8px 24px rgba(255,69,0,.4); position: relative; z-index: 1; }
.hero-name { font-family: 'Barlow Condensed', sans-serif; font-size: 28px; font-weight: 900; color: #fff; letter-spacing: .5px; }
.hero-role-badge { display: inline-block; background: rgba(255,69,0,.15); border: 1px solid rgba(255,69,0,.3); color: var(--orange); font-size: 11px; font-weight: 800; padding: 4px 12px; border-radius: 20px; text-transform: uppercase; margin-top: 6px; letter-spacing: .5px; }
.profile-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 22px; }
.p-card { border-radius: 16px; padding: 28px; }
.admin-layout .p-card { background: #fff; border: 1px solid #E5E7EB; }
.customer-layout .p-card { background: #111; border: 1px solid rgba(255,255,255,.07); }
.p-card-wide { grid-column: 1 / -1; }
.card-title { font-size: 13px; font-weight: 800; text-transform: uppercase; letter-spacing: .6px; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; }
.admin-layout .card-title { color: #111; }
.customer-layout .card-title { color: #fff; }
.card-title i { color: var(--orange); }
.field-label { font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 6px; display: block; }
.form-input { width: 100%; padding: 12px 14px; border-radius: 10px; font-size: 14px; font-family: 'Barlow', sans-serif; transition: .2s; margin-bottom: 18px; }
.btn-save { width: 100%; padding: 13px; border: none; background: var(--orange); color: #fff; font-weight: 800; font-size: 14px; border-radius: 10px; cursor: pointer; text-transform: uppercase; letter-spacing: .5px; display: flex; align-items: center; justify-content: center; gap: 8px; }
.info-row { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; }
.admin-layout .info-row { border-bottom: 1px solid #F3F4F6; }
.customer-layout .info-row { border-bottom: 1px solid rgba(255,255,255,.06); }
.info-key { font-size: 12px; font-weight: 700; color: #6B7280; }
.info-val { font-size: 14px; font-weight: 700; color: #fff; }
.admin-layout .info-val { color: #111; }
/* Label Input (Email Baru, Password, dll) */
.customer-layout .field-label { 
    color: #FFFFFF; 
    opacity: 0.9; /* Sedikit transparansi agar elegan, atau hapus jika ingin putih pekat */
}

/* Label Info (ID Akun, ID Customer, dll) */
.customer-layout .info-key { 
    color: #CCCCCC; /* Abu-abu terang agar ada kontras dengan isinya yang putih */
}

/* Judul Card */
.customer-layout .card-title { 
    color: #FFFFFF; 
}
</style>
</head>
<body class="<?= $is_admin ? 'admin-layout' : 'customer-layout' ?>">

<?php if ($is_admin): ?>
<!-- ADMIN NAV (Dihapus untuk ringkas, tetap sama seperti milik Anda) -->
<aside class="sidebar">...</aside>
<main class="admin-main">
    <header class="topbar">...</header>
<?php else: ?>
<!-- CUSTOMER NAV -->
<nav class="cust-nav">
    <a href="index.php" class="cust-nav-brand"><i class="fa-solid fa-basketball"></i><span>HOOP ARENA</span></a>
    <div class="cust-nav-links">
        <a href="view_customer.php" class="cust-nav-link">Beranda</a>
        <a href="lapangan.php" class="cust-nav-link">Lapangan</a>
        <a href="jadwal.php" class="cust-nav-link">Jadwal</a>
        <a href="booking.php" class="cust-nav-link">Booking</a>
        <a href="promo.php" class="cust-nav-link">Promo</a>
    </div>
    <div class="cust-nav-right">
        <a href="profile.php" class="cust-nav-link active"><i class="fa-solid fa-user"></i> <?= htmlspecialchars($nama) ?></a>
        <a href="logout.php" class="cust-logout"><i class="fa-solid fa-right-from-bracket"></i> Keluar</a>
    </div>
</nav>
<div style="flex:1;">
<?php endif; ?>

    <div class="profile-content">
        <!-- Hero Section -->
        <div class="profile-hero">
            <div class="avatar-ring"><i class="fa-solid fa-user"></i></div>
            <div class="hero-info">
                <div class="hero-name"><?= strtoupper(htmlspecialchars($nama)) ?></div>
                <div class="hero-role-badge"><?= $role_label ?></div>
            </div>
        </div>

        <div class="profile-grid">
            <!-- Form Ubah Akun -->
            <div class="p-card p-card-wide">
                <div class="card-title"><i class="fa-solid fa-pen-to-square"></i> Ubah Informasi Akun</div>
                <?php if ($success_msg): ?><div class="alert alert-success"><?= $success_msg ?></div><?php endif; ?>
                <form method="POST">
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                        <div>
                            <label class="field-label">Email Baru</label>
                            <input type="email" name="email" class="form-input" value="<?= htmlspecialchars($akun['Email']) ?>" required>
                        </div>
                        <div>
                            <label class="field-label">Password Lama</label>
                            <input type="password" name="old_password" class="form-input" required>
                        </div>
                    </div>
                    <button type="submit" name="update_profile" class="btn-save">Simpan Perubahan</button>
                </form>
            </div>

            <!-- --- TAMBAHAN: DATA PROFILE CUSTOMER (Hanya tampil jika role customer) --- -->
            <?php if ($role === 'customer' && $customer_data): ?>
            <div class="p-card">
                <div class="card-title"><i class="fa-solid fa-address-card"></i> Detail Profil Customer</div>
                <div class="info-row">
                    <span class="info-key">ID Customer</span>
                    <span class="info-val"><?= $customer_data['ID_Customer'] ?></span>
                </div>
                <div class="info-row">
                    <span class="info-key">Nama Lengkap</span>
                    <span class="info-val"><?= htmlspecialchars($customer_data['Nama_Customer']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-key">Jenis Kelamin</span>
                    <span class="info-val"><?= $customer_data['Jenis_Kelamin'] == 1 ? 'Laki-laki' : 'Perempuan' ?></span>
                </div>
                <div class="info-row">
                    <span class="info-key">No. Telepon</span>
                    <span class="info-val"><?= htmlspecialchars($customer_data['No_Telepon']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-key">Alamat</span>
                    <span class="info-val"><?= htmlspecialchars($customer_data['Alamat']) ?></span>
                </div>
            </div>
            <?php endif; ?>
            <!-- ----------------------------------------------------------------------- -->

            <!-- Info Akun -->
            <div class="p-card">
                <div class="card-title"><i class="fa-solid fa-id-card"></i> Info Login</div>
                <div class="info-row">
                    <span class="info-key">ID Akun</span>
                    <span class="info-val">#<?= $akun['ID_Akun'] ?></span>
                </div>
                <div class="info-row">
                    <span class="info-key">Email Login</span>
                    <span class="info-val"><?= htmlspecialchars($akun['Email']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-key">Status Akun</span>
                    <span class="info-val" style="color:var(--green)">Aktif</span>
                </div>
            </div>

        </div>
    </div>

<?php if ($is_admin): ?>
</main>
<?php else: ?>
</div>
<?php endif; ?>

</body>
</html>