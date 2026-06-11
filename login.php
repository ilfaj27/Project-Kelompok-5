<?php
session_start();
include 'includes/config.php';

$error = '';

if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = 'Username dan password wajib diisi!';
    } else {
        // Cek langsung ke tabel Karyawan (bukan Akun)
        $query = "SELECT * FROM Karyawan WHERE Username = ? AND Kata_Sandi = ? AND Is_Deleted = 0";
        $stmt = sqlsrv_query($conn, $query, array($username, $password));

        if ($stmt === false) {
            $error = 'Terjadi kesalahan koneksi database.';
        } elseif (sqlsrv_has_rows($stmt)) {
            $user = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

            // Set session dari data Karyawan
            $_SESSION['id_karyawan'] = $user['ID_Karyawan'];
            $_SESSION['nama'] = $user['Nama_Karyawan'];
            $_SESSION['username'] = $user['Username'];
            $_SESSION['jabatan'] = $user['Jabatan'];
            $_SESSION['Profile_Photo'] = $user['Profile_Photo'] ?? '';

            // Role mapping: Manajer = pemilik, lainnya = karyawan
            $_SESSION['role'] = ($user['Jabatan'] === 'Manajer') ? 'pemilik' : 'karyawan';

            // Redirect
            if ($_SESSION['role'] === 'pemilik') {
                header("Location: view_pemilik.php");
            } else {
                header("Location: view_admin.php");
            }
            exit();
        } else {
            $error = 'Username atau password salah!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login | HoopBall BasketPro</title>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800;900&family=Barlow:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root { --orange: #FF4500; --orange-lt: rgba(255,69,0,.08); --orange-dk: #E03E00; --red: #EF4444; --text: #111827; --muted: #6B7280; --bg: #F3F4F6; }
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Barlow', sans-serif; background: linear-gradient(135deg, #0D1117 0%, #1a1f2e 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; color: var(--text); }
.login-container { display: flex; width: 900px; max-width: 95vw; background: #fff; border-radius: 24px; overflow: hidden; box-shadow: 0 25px 80px rgba(0,0,0,.4); }
.login-left { flex: 1; background: linear-gradient(135deg, var(--orange) 0%, var(--orange-dk) 100%); padding: 60px 40px; display: flex; flex-direction: column; justify-content: center; color: #fff; position: relative; overflow: hidden; }
.login-left::before { content: ''; position: absolute; top: -50%; right: -50%; width: 100%; height: 100%; background: radial-gradient(circle, rgba(255,255,255,.1) 0%, transparent 70%); }
.brand-icon { width: 60px; height: 60px; background: rgba(255,255,255,.2); border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 28px; margin-bottom: 24px; backdrop-filter: blur(10px); }
.brand-title { font-family: 'Barlow Condensed'; font-size: 36px; font-weight: 900; letter-spacing: 1px; margin-bottom: 8px; }
.brand-sub { font-size: 14px; opacity: .9; font-weight: 500; }
.login-right { flex: 1; padding: 60px 48px; display: flex; flex-direction: column; justify-content: center; }
.login-header { margin-bottom: 32px; }
.login-title { font-family: 'Barlow Condensed'; font-size: 28px; font-weight: 900; color: var(--text); margin-bottom: 6px; }
.login-desc { font-size: 14px; color: var(--muted); font-weight: 500; }
.form-group { margin-bottom: 20px; }
.form-label { display: block; font-size: 12px; font-weight: 800; color: var(--muted); text-transform: uppercase; letter-spacing: .5px; margin-bottom: 8px; }
.form-input { width: 100%; padding: 14px 16px; border: 2px solid #E5E7EB; border-radius: 12px; font-size: 14px; font-family: 'Barlow'; transition: .2s; background: #FAFAFA; }
.form-input:focus { outline: none; border-color: var(--orange); background: #fff; box-shadow: 0 0 0 4px var(--orange-lt); }
.btn-login { width: 100%; background: var(--orange); color: #fff; border: none; padding: 16px; border-radius: 12px; font-size: 14px; font-weight: 800; cursor: pointer; transition: .2s; text-transform: uppercase; letter-spacing: .5px; font-family: 'Barlow'; margin-top: 8px; }
.btn-login:hover { background: var(--orange-dk); transform: translateY(-2px); box-shadow: 0 12px 30px rgba(255,69,0,.3); }
.error-msg { background: #FEF2F2; color: var(--red); padding: 12px 16px; border-radius: 10px; font-size: 13px; font-weight: 700; margin-bottom: 20px; border: 1px solid #FECACA; display: flex; align-items: center; gap: 8px; }
.footer-text { text-align: center; margin-top: 24px; font-size: 12px; color: var(--muted); font-weight: 600; }
@media(max-width: 768px) { .login-container { flex-direction: column; } .login-left { padding: 40px 30px; min-height: 200px; } .login-right { padding: 40px 30px; } }
</style>
</head>
<body>
<div class="login-container">
    <div class="login-left">
        <div class="brand-icon"><i class="fa-solid fa-basketball"></i></div>
        <div class="brand-title">HOOP BALL</div>
        <div class="brand-sub">Basketball Court Management System</div>
    </div>
    <div class="login-right">
        <div class="login-header">
            <div class="login-title">Selamat Datang</div>
            <div class="login-desc">Masukkan username dan password untuk melanjutkan</div>
        </div>
        <?php if ($error): ?>
        <div class="error-msg"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-input" placeholder="Masukkan username" required autofocus>
            </div>
            <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-input" placeholder="Masukkan password" required>
            </div>
            <button type="submit" name="login" class="btn-login"><i class="fa-solid fa-right-to-bracket"></i> Masuk</button>
        </form>
        <div class="footer-text">HoopBall BasketPro &copy; 2024</div>
    </div>
</div>
</body>
</html>