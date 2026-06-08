<?php
session_start();
include 'includes/config.php';

// Cek Cookie untuk Fitur Ingat Saya
$remembered_user = isset($_COOKIE['remember_me']) ? $_COOKIE['remember_me'] : '';

$error_msg = ""; // Variabel untuk menyimpan pesan error

if (isset($_POST['login'])) {
    $user_input = $_POST['user_input'];
    $pass_input = $_POST['password_input'];

    $sql = "SELECT * FROM Akun WHERE (Username = ? OR Email = ?) AND Status_Akun = 1";
    $params = array($user_input, $user_input);
    $stmt = sqlsrv_query($conn, $sql, $params);
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

    if ($row) {
        if ($pass_input == $row['Kata_Sandi']) {
            $_SESSION['login']   = true;
            $_SESSION['id_akun'] = $row['ID_Akun'];
            
            $role_map = [1 => 'pemilik', 2 => 'karyawan', 3 => 'customer'];
            $_SESSION['role'] = $role_map[$row['Role']];

            if ($row['Role'] == 1 || $row['Role'] == 2) {
                $q_prof = sqlsrv_query($conn, "SELECT Nama_Karyawan FROM Karyawan WHERE ID_Akun = ?", array($row['ID_Akun']));
                $d_prof = sqlsrv_fetch_array($q_prof, SQLSRV_FETCH_ASSOC);
                $_SESSION['nama'] = $d_prof['Nama_Karyawan'];
            } else {
                $q_prof = sqlsrv_query($conn, "SELECT Nama_Customer FROM Customer WHERE ID_Akun = ?", array($row['ID_Akun']));
                $d_prof = sqlsrv_fetch_array($q_prof, SQLSRV_FETCH_ASSOC);
                $_SESSION['nama'] = $d_prof['Nama_Customer'];
            }

            if (isset($_POST['remember'])) {
                setcookie('remember_me', $user_input, time() + (86400 * 30), "/");
            } else {
                setcookie('remember_me', '', time() - 3600, "/");
            }

            header("Location: dashboard.php");
            exit();
        } else {
            $error_msg = "Username Atau Kata Sandi yang Anda masukkan salah.";
        }
    } else {
        $error_msg = "Akun tidak ditemukan atau sedang dinonaktifkan.";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Login | HoopBall BasketPro</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { --orange: #FF4500; --dark-box: rgba(15, 15, 15, 0.98); --input-bg: #1a1a1a; }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
        body { background: #000; overflow: hidden; }

        .auth-page { 
            height: 100vh; display: flex; justify-content: center; align-items: center;
            background: linear-gradient(rgba(0,0,0,0.7), rgba(0,0,0,0.9)), url('https://images.unsplash.com/photo-1546519638-68e109498ffc?q=80&w=2000');
            background-size: cover; background-position: center;
            position: relative;
        }

        /* TOMBOL KEMBALI KE LANDING PAGE */
        .btn-back-home {
            position: absolute; top: 30px; left: 40px;
            color: #fff; text-decoration: none; font-size: 14px; font-weight: 700;
            display: flex; align-items: center; gap: 10px;
            transition: 0.3s; z-index: 100;
            padding: 10px 20px; border-radius: 30px;
            background: rgba(255,255,255,0.05); backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1);
        }
        .btn-back-home:hover { background: var(--orange); color: #fff; transform: translateX(-5px); }

        .auth-box { 
            background: var(--dark-box); padding: 50px 40px; border-radius: 20px; width: 100%; max-width: 440px; 
            border-left: 6px solid var(--orange); box-shadow: 0 25px 50px rgba(0, 0, 0, 0.6); text-align: center;
            animation: fadeIn 0.8s ease-out;
        }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

        .logo-link { color: var(--orange); font-size: 45px; margin-bottom: 20px; display: inline-block; transition: 0.3s; }
        .logo-link:hover { transform: rotate(15deg) scale(1.1); }

        h1 { font-size: 28px; font-weight: 900; color: #fff; text-transform: uppercase; margin-bottom: 5px; letter-spacing: -1px; }
        .subtitle { color: #666; font-size: 13px; margin-bottom: 35px; display: block; }

        .input-group { position: relative; margin-bottom: 15px; text-align: left; }
        .input-group i { position: absolute; left: 15px; top: 15px; color: #444; font-size: 14px; }
        input { width: 100%; padding: 15px 15px 15px 45px; background: var(--input-bg); border: 1px solid #333; color: white; border-radius: 12px; font-size: 14px; outline: none; transition: 0.3s; }
        input:focus { border-color: var(--orange); background: #000; box-shadow: 0 0 10px rgba(255,69,0,0.2); }
        
        .remember-row { display: flex; align-items: center; justify-content: space-between; margin: 15px 0 25px; }
        .check-container { display: flex; align-items: center; gap: 8px; }
        .check-container label { color: #888; font-size: 13px; cursor: pointer; }
        input[type="checkbox"] { accent-color: var(--orange); width: 16px; height: 16px; cursor: pointer; }

        .btn-submit { width: 100%; padding: 16px; background: var(--orange); color: #fff; border: none; border-radius: 12px; font-weight: 900; cursor: pointer; text-transform: uppercase; transition: 0.3s; letter-spacing: 1px; }
        .btn-submit:hover { background: #e03d00; transform: translateY(-2px); box-shadow: 0 10px 20px rgba(255,69,0,0.3); }
        
        .divider { display: flex; align-items: center; margin: 25px 0; color: #222; font-size: 11px; font-weight: 800; text-transform: uppercase; }
        .divider::before, .divider::after { content: ""; flex: 1; height: 1px; background: #222; }
        .divider span { padding: 0 15px; }

        .btn-google { width: 100%; padding: 14px; background: #fff; color: #000; border-radius: 12px; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 12px; font-weight: 700; font-size: 14px; transition: 0.3s; }
        .btn-google:hover { background: #f1f1f1; }
        
        .auth-footer { margin-top: 30px; font-size: 13px; color: #555; }
        .auth-footer a { color: var(--orange); text-decoration: none; font-weight: 800; }
    </style>
</head>
<body>

<div class="auth-page">
    <!-- TOMBOL KEMBALI KE BERANDA -->
    <a href="index.php" class="btn-back-home">
        <i class="fa-solid fa-arrow-left"></i> Kembali ke Beranda
    </a>

    <div class="auth-box">
        <!-- Logo bisa diklik balik ke Landing Page -->
        <a href="index.php" class="logo-link"><i class="fa-solid fa-basketball"></i></a>
        
        <h1>HoopBall Login</h1>
        <span class="subtitle">Satu akun untuk semua akses arena.</span>

        <form method="POST">
            <div class="input-group">
                <i class="fa-solid fa-user"></i>
                <input type="text" name="user_input" placeholder="Username atau Email" value="<?= htmlspecialchars($remembered_user) ?>" required>
            </div>
            <div class="input-group">
                <i class="fa-solid fa-lock"></i>
                <input type="password" name="password_input" placeholder="Kata Sandi" required>
            </div>

            <div class="remember-row">
                <div class="check-container">
                    <input type="checkbox" name="remember" id="rem" <?= $remembered_user ? 'checked' : '' ?>>
                    <label for="rem">Ingat Saya</label>
                </div>
                <a href="#" style="color:#555; font-size:12px; text-decoration:none;">Lupa Password?</a>
            </div>

            <button type="submit" name="login" class="btn-submit">MASUK SEKARANG</button>
        </form>

        <div class="divider"><span>Atau</span></div>

        <a href="#" class="btn-google">
            <img src="https://www.gstatic.com/images/branding/product/1x/googleg_48dp.png" alt="Google" width="18"> Masuk dengan Google
        </a>

        <p class="auth-footer">Daftar sebagai tim? <a href="register.php">Daftar Sekarang</a></p>
    </div>
</div>

<!-- Logika Notifikasi SweetAlert -->
<?php if($error_msg): ?>
<script>
    Swal.fire({
        icon: 'error',
        title: 'Login Gagal',
        text: '<?= $error_msg ?>',
        background: '#111',
        color: '#fff',
        confirmButtonColor: '#FF4500'
    });
</script>
<?php endif; ?>

</body>
</html>