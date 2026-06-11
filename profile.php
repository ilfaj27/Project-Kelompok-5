<?php
session_start();
include 'includes/config.php';

if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

$role = $_SESSION['role'];
$nama = $_SESSION['nama'];
$id_karyawan = $_SESSION['id_karyawan'] ?? '';

$dashboard_url = ($role === 'pemilik') ? 'view_pemilik.php' : 'view_admin.php';

$query = "SELECT * FROM Karyawan WHERE ID_Karyawan = ?";
$stmt = sqlsrv_query($conn, $query, array($id_karyawan));
$user_data = null;
if ($stmt && sqlsrv_has_rows($stmt)) {
    $user_data = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
}

if (!$user_data) {
    echo "<script>alert('Data profil tidak ditemukan!'); window.location='$dashboard_url';</script>";
    exit();
}

function fmtDate($date) {
    if (!$date) return '-';
    if (is_object($date) && method_exists($date, 'format')) return $date->format('d M Y');
    return $date;
}

$map_jk = [0 => 'Perempuan', 1 => 'Laki-laki'];

// Password change
$pass_msg = '';
if (isset($_POST['change_password'])) {
    $old = $_POST['old_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];

    if ($old !== $user_data['Kata_Sandi']) {
        $pass_msg = 'Password lama salah!';
    } elseif (strlen($new) < 8) {
        $pass_msg = 'Password baru minimal 8 karakter!';
    } elseif ($new !== $confirm) {
        $pass_msg = 'Password baru dan konfirmasi tidak cocok!';
    } else {
        $upd = sqlsrv_query($conn, "UPDATE Karyawan SET Kata_Sandi = ?, Modified_By = ?, Modified_Date = GETDATE() WHERE ID_Karyawan = ?", array($new, $nama, $id_karyawan));
        if ($upd) {
            $pass_msg = 'success';
            $stmt = sqlsrv_query($conn, $query, array($id_karyawan));
            $user_data = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        } else {
            $pass_msg = 'Gagal mengubah password!';
        }
    }
}

// Photo upload
$photo_msg = '';
if (isset($_POST['upload_photo']) && isset($_FILES['profile_photo'])) {
    $file = $_FILES['profile_photo'];
    if ($file['error'] === 0) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array($ext, $allowed)) {
            $filename = 'uploads/profiles/' . $id_karyawan . '_' . time() . '.' . $ext;
            if (!is_dir('uploads/profiles')) mkdir('uploads/profiles', 0777, true);
            if (move_uploaded_file($file['tmp_name'], $filename)) {
                $upd = sqlsrv_query($conn, "UPDATE Karyawan SET Profile_Photo = ?, Modified_By = ?, Modified_Date = GETDATE() WHERE ID_Karyawan = ?", array($filename, $nama, $id_karyawan));
                if ($upd) {
                    $_SESSION['Profile_Photo'] = $filename;
                    $photo_msg = 'success';
                    $stmt = sqlsrv_query($conn, $query, array($id_karyawan));
                    $user_data = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
                }
            }
        } else {
            $photo_msg = 'Format file tidak didukung!';
        }
    }
}

$profile_photo = $user_data['Profile_Photo'] ?? '';
$photo_path = '';
if (!empty($profile_photo)) {
    $photo_path = $profile_photo;
    if (!file_exists($photo_path)) $photo_path = '';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Profil Saya | HoopBall</title>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800;900&family=Barlow:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
:root {
    --orange: #FF4500; --orange-lt: rgba(255,69,0,.10); --orange-dk: #E03E00;
    --green: #10B981; --green-lt: rgba(16,185,129,.10);
    --blue: #3B82F6; --blue-lt: rgba(59,130,246,.10);
    --red: #EF4444; --red-lt: rgba(239,68,68,.10);
    --sidebar: #0D1117; --sidebar-w: 260px; --topbar-h: 70px;
    --card-bg: #FFFFFF; --border: #E5E7EB; --border-lt: #F3F4F6;
    --text: #111827; --muted: #6B7280; --bg: #F3F4F6;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Barlow', sans-serif; background: var(--bg); display: flex; min-height: 100vh; color: var(--text); }

.sidebar { width: var(--sidebar-w); background: var(--sidebar); height: 100vh; position: fixed; top: 0; left: 0; display: flex; flex-direction: column; padding: 28px 18px; z-index: 200; }
.sb-brand { display: flex; align-items: center; gap: 12px; padding: 0 8px; margin-bottom: 36px; text-decoration: none; }
.sb-icon { width: 40px; height: 40px; background: var(--orange); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 18px; }
.sb-brand-name { font-family: 'Barlow Condensed'; font-size: 20px; font-weight: 900; color: #fff; letter-spacing: 1px; }
.sb-brand-sub { font-size: 9px; color: #4B5563; font-weight: 700; text-transform: uppercase; }
.sb-section-label { font-size: 10px; font-weight: 800; text-transform: uppercase; color: #374151; letter-spacing: .8px; padding: 0 10px; margin: 22px 0 8px; }
.sb-link { display: flex; align-items: center; gap: 12px; color: #6B7280; text-decoration: none; padding: 10px 12px; border-radius: 10px; margin-bottom: 2px; font-size: 13px; font-weight: 600; transition: all .2s; }
.sb-link .sb-icon-wrap { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 13px; background: rgba(255,255,255,.04); }
.sb-link:hover { color: #E5E7EB; background: rgba(255,255,255,.04); }
.sb-link.active { color: #fff; background: var(--orange-lt); }
.sb-link.active .sb-icon-wrap { background: var(--orange); color: #fff; }
.sb-bottom { margin-top: auto; padding-top: 20px; }
.sb-user { display: flex; align-items: center; gap: 10px; background: rgba(255,255,255,.04); border-radius: 12px; padding: 12px; border: 1px solid rgba(255,255,255,.06); }
.sb-avatar { width: 36px; height: 36px; background: var(--orange); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 14px; }
.sb-user-name { font-size: 13px; font-weight: 800; color: #E5E7EB; }
.sb-user-role { font-size: 10px; color: var(--orange); font-weight: 700; text-transform: uppercase; }
.sb-logout { margin-left: auto; color: #4B5563; font-size: 13px; transition: .2s; cursor: pointer; text-decoration: none; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 8px; }
.sb-logout:hover { color: var(--red); background: rgba(239,68,68,.1); }

.main { margin-left: var(--sidebar-w); flex: 1; display: flex; flex-direction: column; min-height: 100vh; }
.topbar { background: var(--card-bg); height: var(--topbar-h); padding: 0 40px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 100; }
.topbar-left { display: flex; flex-direction: column; }
.topbar-title { font-family: 'Barlow Condensed'; font-size: 26px; font-weight: 900; color: var(--text); }
.topbar-breadcrumb { font-size: 12px; color: var(--muted); font-weight: 600; margin-top: 2px; }

.content { padding: 32px 40px; flex: 1; }
.page-header { margin-bottom: 28px; }
.page-title-tag { width: 36px; height: 4px; background: var(--orange); border-radius: 2px; margin-bottom: 8px; }
.page-title { font-family: 'Barlow Condensed'; font-size: 30px; font-weight: 900; color: var(--text); text-transform: uppercase; }

.profile-grid { display: grid; grid-template-columns: 320px 1fr; gap: 24px; }
@media(max-width: 1100px) { .profile-grid { grid-template-columns: 1fr; } }

.profile-card { background: var(--card-bg); border-radius: 16px; border: 1px solid var(--border); padding: 28px; text-align: center; }
.profile-photo-wrap { width: 120px; height: 120px; border-radius: 50%; background: var(--orange-lt); display: inline-flex; align-items: center; justify-content: center; margin-bottom: 16px; position: relative; overflow: hidden; }
.profile-photo-wrap img { width: 100%; height: 100%; object-fit: cover; }
.profile-photo-wrap i { font-size: 48px; color: var(--orange); }
.profile-name { font-family: 'Barlow Condensed'; font-size: 22px; font-weight: 900; color: var(--text); text-transform: uppercase; }
.profile-role { font-size: 12px; color: var(--orange); font-weight: 800; text-transform: uppercase; margin-top: 4px; }
.profile-id { font-size: 11px; color: var(--muted); font-weight: 700; margin-top: 6px; font-family: 'Barlow Condensed'; }
.profile-status { display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 800; margin-top: 12px; }
.status-active { background: var(--green-lt); color: var(--green); }
.status-inactive { background: var(--red-lt); color: var(--red); }

.photo-upload { margin-top: 20px; }
.photo-upload input[type="file"] { display: none; }
.btn-upload { display: inline-flex; align-items: center; gap: 8px; background: var(--bg); border: 1.5px solid var(--border); color: var(--text-md); padding: 10px 18px; border-radius: 10px; font-size: 12px; font-weight: 800; cursor: pointer; transition: .2s; }
.btn-upload:hover { border-color: var(--orange); color: var(--orange); background: var(--orange-lt); }

.info-card { background: var(--card-bg); border-radius: 16px; border: 1px solid var(--border); padding: 28px; }
.info-card-title { font-size: 15px; font-weight: 800; color: var(--text); display: flex; align-items: center; gap: 8px; margin-bottom: 20px; }
.info-card-title i { color: var(--orange); }
.info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
@media(max-width: 768px) { .info-grid { grid-template-columns: 1fr; } }
.info-item { padding: 14px 16px; background: var(--bg); border-radius: 12px; border: 1px solid var(--border-lt); }
.info-label { font-size: 10px; font-weight: 800; color: var(--muted); text-transform: uppercase; letter-spacing: .5px; margin-bottom: 6px; display: flex; align-items: center; gap: 6px; }
.info-label i { color: var(--orange); font-size: 11px; }
.info-value { font-size: 14px; font-weight: 700; color: var(--text); }
.info-value-mono { font-family: 'Barlow Condensed'; font-size: 15px; font-weight: 800; color: var(--orange); }
.info-full { grid-column: span 2; }
@media(max-width: 768px) { .info-full { grid-column: span 1; } }

.password-card { background: var(--card-bg); border-radius: 16px; border: 1px solid var(--border); padding: 28px; margin-top: 24px; }
.password-title { font-size: 15px; font-weight: 800; color: var(--text); display: flex; align-items: center; gap: 8px; margin-bottom: 20px; }
.password-title i { color: var(--orange); }
.form-group { margin-bottom: 16px; }
.form-label { font-size: 11px; font-weight: 800; color: var(--muted); text-transform: uppercase; letter-spacing: .5px; display: block; margin-bottom: 6px; }
.form-input { width: 100%; padding: 12px 14px; border: 1.5px solid var(--border); border-radius: 10px; font-size: 14px; font-family: 'Barlow'; transition: .2s; background: #FAFAFA; }
.form-input:focus { outline: none; border-color: var(--orange); background: #fff; box-shadow: 0 0 0 3px rgba(255,69,0,.08); }
.btn-save { background: var(--orange); color: #fff; border: none; padding: 12px 24px; border-radius: 10px; font-weight: 800; font-size: 13px; cursor: pointer; transition: .2s; text-transform: uppercase; }
.btn-save:hover { background: var(--orange-dk); transform: translateY(-1px); box-shadow: 0 8px 20px rgba(255,69,0,.25); }

.msg-success { background: var(--green-lt); color: var(--green); padding: 12px 16px; border-radius: 10px; font-size: 13px; font-weight: 700; margin-bottom: 16px; border: 1px solid rgba(16,185,129,.2); }
.msg-error { background: var(--red-lt); color: var(--red); padding: 12px 16px; border-radius: 10px; font-size: 13px; font-weight: 700; margin-bottom: 16px; border: 1px solid rgba(239,68,68,.2); }
</style>
</head>
<body>

<aside class="sidebar">
    <a href="<?= $dashboard_url ?>" class="sb-brand">
        <div class="sb-icon"><i class="fa-solid fa-basketball"></i></div>
        <div><div class="sb-brand-name">HOOP BALL</div><div class="sb-brand-sub">Management System</div></div>
    </a>
    <div class="sb-section-label">Manajemen</div>
    <nav>
        <a href="<?= $dashboard_url ?>" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-house"></i></div> Dashboard</a>
        <?php if ($role === 'pemilik'): ?>
        <a href="master/karyawan.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-user-tie"></i></div> Kelola Karyawan</a>
        <a href="master/alat.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-toolbox"></i></div> Kelola Alat</a>
        <a href="laporan/omzet.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-chart-line"></i></div> Laporan & Omzet</a>
        <?php endif; ?>
    </nav>
    <div class="sb-section-label">Akun</div>
    <a href="profile.php" class="sb-link active"><div class="sb-icon-wrap"><i class="fa-solid fa-id-badge"></i></div> Profil Saya</a>
    <div class="sb-bottom">
        <div class="sb-user">
            <div class="sb-avatar">
                <?php if ($photo_path): ?><img src="<?= $photo_path ?>" style="width:100%;height:100%;object-fit:cover;border-radius:50%;"><?php else: ?><i class="fa-solid fa-user"></i><?php endif; ?>
            </div>
            <div><div class="sb-user-name"><?= strtoupper(htmlspecialchars($nama)) ?></div><div class="sb-user-role"><?= strtoupper($role) ?></div></div>
            <a href="logout.php" class="sb-logout" title="Keluar"><i class="fa-solid fa-right-from-bracket"></i></a>
        </div>
    </div>
</aside>

<main class="main">
    <header class="topbar">
        <div class="topbar-left">
            <div class="topbar-title">Profil Saya</div>
            <div class="topbar-breadcrumb">Akun / Profil</div>
        </div>
    </header>

    <div class="content">
        <div class="page-header">
            <div class="page-title-tag"></div>
            <div class="page-title">Informasi Profil</div>
        </div>

        <div class="profile-grid">
            <div>
                <div class="profile-card">
                    <div class="profile-photo-wrap">
                        <?php if ($photo_path): ?>
                            <img src="<?= $photo_path ?>" alt="Profile">
                        <?php else: ?>
                            <i class="fa-solid fa-user-tie"></i>
                        <?php endif; ?>
                    </div>
                    <div class="profile-name"><?= strtoupper(htmlspecialchars($user_data['Nama_Karyawan'])) ?></div>
                    <div class="profile-role"><?= strtoupper(htmlspecialchars($user_data['Jabatan'])) ?></div>
                    <div class="profile-id"><?= htmlspecialchars($user_data['ID_Karyawan']) ?></div>
                    <div class="profile-status <?= ($user_data['Status'] == 1) ? 'status-active' : 'status-inactive' ?>">
                        <i class="fa-solid <?= ($user_data['Status'] == 1) ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i>
                        <?= ($user_data['Status'] == 1) ? 'AKTIF' : 'NONAKTIF' ?>
                    </div>
                    <form method="POST" enctype="multipart/form-data" class="photo-upload">
                        <input type="file" name="profile_photo" id="profile_photo" accept="image/*" onchange="this.form.submit()">
                        <input type="hidden" name="upload_photo" value="1">
                        <label for="profile_photo" class="btn-upload">
                            <i class="fa-solid fa-camera"></i> Ganti Foto
                        </label>
                    </form>
                    <?php if ($photo_msg === 'success'): ?>
                        <div class="msg-success" style="margin-top:12px;"><i class="fa-solid fa-check-circle"></i> Foto berhasil diperbarui!</div>
                    <?php elseif ($photo_msg): ?>
                        <div class="msg-error" style="margin-top:12px;"><i class="fa-solid fa-circle-exclamation"></i> <?= $photo_msg ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div>
                <div class="info-card">
                    <div class="info-card-title"><i class="fa-solid fa-id-card"></i> Data Pribadi</div>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label"><i class="fa-solid fa-fingerprint"></i> ID Karyawan</div>
                            <div class="info-value-mono"><?= htmlspecialchars($user_data['ID_Karyawan']) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label"><i class="fa-solid fa-user"></i> Nama Lengkap</div>
                            <div class="info-value"><?= htmlspecialchars($user_data['Nama_Karyawan']) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label"><i class="fa-solid fa-venus-mars"></i> Jenis Kelamin</div>
                            <div class="info-value"><?= $map_jk[$user_data['Jenis_Kelamin']] ?? 'Tidak diketahui' ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label"><i class="fa-solid fa-calendar-day"></i> Tanggal Lahir</div>
                            <div class="info-value"><?= fmtDate($user_data['Tanggal_Lahir']) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label"><i class="fa-solid fa-location-dot"></i> Tempat Lahir</div>
                            <div class="info-value"><?= htmlspecialchars($user_data['Tempat_Lahir'] ?? '-') ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label"><i class="fa-solid fa-phone"></i> No. Telepon</div>
                            <div class="info-value"><?= htmlspecialchars($user_data['No_Telepon'] ?? '-') ?></div>
                        </div>
                        <div class="info-item info-full">
                            <div class="info-label"><i class="fa-solid fa-map-location-dot"></i> Alamat Lengkap</div>
                            <div class="info-value"><?= htmlspecialchars($user_data['Alamat'] ?? '-') ?></div>
                        </div>
                    </div>
                </div>

                <div class="password-card">
                    <div class="password-title"><i class="fa-solid fa-lock"></i> Ganti Password</div>
                    <?php if ($pass_msg === 'success'): ?>
                        <div class="msg-success"><i class="fa-solid fa-check-circle"></i> Password berhasil diubah!</div>
                    <?php elseif ($pass_msg): ?>
                        <div class="msg-error"><i class="fa-solid fa-circle-exclamation"></i> <?= $pass_msg ?></div>
                    <?php endif; ?>
                    <form method="POST">
                        <div class="info-grid">
                            <div class="form-group">
                                <label class="form-label">Password Lama</label>
                                <input type="password" name="old_password" class="form-input" required placeholder="Masukkan password lama">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Password Baru</label>
                                <input type="password" name="new_password" class="form-input" required minlength="8" placeholder="Minimal 8 karakter">
                            </div>
                            <div class="form-group" style="grid-column: span 2;">
                                <label class="form-label">Konfirmasi Password Baru</label>
                                <input type="password" name="confirm_password" class="form-input" required placeholder="Ulangi password baru">
                            </div>
                        </div>
                        <button type="submit" name="change_password" class="btn-save" style="margin-top: 8px;">
                            <i class="fa-solid fa-key"></i> Ubah Password
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
<?php if ($pass_msg === 'success' || $photo_msg === 'success'): ?>
Swal.fire({
    icon: 'success',
    title: 'Berhasil!',
    text: '<?= ($pass_msg === 'success') ? 'Password berhasil diubah!' : 'Foto profil berhasil diperbarui!' ?>',
    timer: 2000,
    showConfirmButton: false,
    iconColor: '#FF4500'
});
<?php endif; ?>
</script>
</body>
</html>