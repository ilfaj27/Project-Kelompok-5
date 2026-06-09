<?php
session_start();
include 'includes/config.php';


// Cek Cookie untuk Fitur Ingat Saya
$remembered_user = isset($_COOKIE['remember_me']) ? $_COOKIE['remember_me'] : '';

$error_msg = "";

if (isset($_POST['login'])) {
    $user_input = $_POST['user_input'];
    $pass_input = $_POST['password_input'];

    // Query dengan prepared statement
    $sql = "SELECT * FROM Akun WHERE (Username = ? OR Email = ?) AND Status_Akun = 1";
    $params = array($user_input, $user_input);
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        $error_msg = "Terjadi kesalahan koneksi database. Silakan coba lagi.";
    } else {
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

        if ($row) {
            if ($pass_input == $row['Kata_Sandi']) {
                $_SESSION['login']   = true;
                $_SESSION['id_akun'] = $row['ID_Akun'];

                $role_map = [1 => 'pemilik', 2 => 'karyawan', 3 => 'customer'];
                $_SESSION['role'] = $role_map[$row['Role']];

                if ($row['Role'] == 1 || $row['Role'] == 2) {
                    $q_prof = sqlsrv_query($conn, "SELECT Nama_Karyawan FROM Karyawan WHERE ID_Akun = ?", array($row['ID_Akun']));
                    if ($q_prof !== false) {
                        $d_prof = sqlsrv_fetch_array($q_prof, SQLSRV_FETCH_ASSOC);
                        $_SESSION['nama'] = $d_prof['Nama_Karyawan'] ?? 'Admin';
                    } else {
                        $_SESSION['nama'] = 'Admin';
                    }
                } else {
                    $q_prof = sqlsrv_query($conn, "SELECT Nama_Customer FROM Customer WHERE ID_Akun = ?", array($row['ID_Akun']));
                    if ($q_prof !== false) {
                        $d_prof = sqlsrv_fetch_array($q_prof, SQLSRV_FETCH_ASSOC);
                        $_SESSION['nama'] = $d_prof['Nama_Customer'] ?? 'Customer';
                    } else {
                        $_SESSION['nama'] = 'Customer';
                    }
                }

                if (isset($_POST['remember'])) {
                    setcookie('remember_me', $user_input, time() + (86400 * 30), "/");
                } else {
                    setcookie('remember_me', '', time() - 3600, "/");
                }

                if ($_SESSION['role'] == 'pemilik') {
                    header("Location: view_pemilik.php");
                } elseif ($_SESSION['role'] == 'karyawan') {
                    header("Location: view_admin.php");
                } else {
                    header("Location: index.php");
                }
                exit();
            } else {
                $error_msg = "Username atau Kata Sandi yang Anda masukkan salah.";
            }
        } else {
            $error_msg = "Akun tidak ditemukan atau sedang dinonaktifkan.";
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
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --orange: #FF5400;
            --orange-hover: #E04600;
            --dark-blue: #0F172A;
            --text-dark: #1E293B;
            --text-muted: #64748B;
            --border-color: #E2E8F0;
            --bg-light: #F8FAFC;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        body {
            background-color: #FFFFFF;
            color: var(--text-dark);
            overflow-x: hidden;
        }

        /* HEADER / BACK HOME BUTTON */
    

        .btn-back-home:hover {
            background: var(--orange);
            transform: translateX(-4px);
        }

        /* HERO AUTH WRAPPER (DARK BACKGROUND) */
        .auth-hero-wrapper {
               background: linear-gradient(rgba(0, 0, 0, 0.8), rgba(0, 0, 0, 0.85)), 
                url('login.png') no-repeat center center;
    background-size: cover;
    
    /* DIUBAH: 
       - Angka pertama (90px) adalah jarak atas (dikurangi agar lebih naik ke atas)
       - Angka ketiga (150px) adalah jarak bawah (ditambah agar mendorong konten ke atas) */
    padding: 90px 8% 150px 8%; 
    
    display: grid;
    grid-template-columns: 1.12fr 1fr;
    gap: 50px;
    align-items: center;
    min-height: 90vh;
        }

        /* SISI KIRI: INFORMASI PROMO */
        .auth-info h2 {
            font-size: 48px;
            font-weight: 900;
            color: #ffffff;
            line-height: 1.15;
            margin-bottom: 16px;
        }

        .auth-info h2 span {
            color: var(--orange);
        }

        .auth-info .intro-p {
            font-size: 15px;
            color: #94A3B8;
            line-height: 1.6;
            margin-bottom: 48px;
            max-width: 500px;
        }

        /* List Keuntungan */
        .info-list {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .info-item {
               display: flex;
            align-items: center;
            gap: 16px;
            cursor: pointer;
            
            /* Transisi geser horizontal */
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

         .info-item:hover {
            transform: translateX(8px);
        }

        .info-icon {
          width: 44px;
            height: 44px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: var(--orange);
            font-size: 16px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            
            transition: all 0.3s ease;
        }

           /* Ikon berubah oranye solid saat item info dihover */
        .info-item:hover .info-icon {
            background: var(--orange);
            border-color: var(--orange);
            color: #ffffff;
            box-shadow: 0 0 12px rgba(255, 84, 0, 0.3);
        }

        .info-text h4 {
            font-size: 14px;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 2px;
        }

        .info-text p {
            font-size: 12px;
            color: #94A3B8;
        }

        /* SISI KANAN: FLOATING WHITE CARD LOGIN */
        .auth-card-container {
            display: flex;
            justify-content: flex-end;
        }

        .auth-card {
            background: #ffffff;
            border-radius: 24px;
            width: 100%;
            max-width: 440px;
            padding: 44px 36px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
            text-align: center;
        }

        .auth-card h3 {
            font-size: 26px;
            font-weight: 800;
            color: var(--dark-blue);
            margin-bottom: 6px;
        }

        .auth-card .card-subtitle {
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 32px;
            display: block;
        }

        /* FORM INPUT */
        .input-group {
          margin-bottom: 20px;
            text-align: left;
            transition: all 0.3s ease;
        }

        .input-group label {
           font-size: 12px;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 8px;
            display: block;
            transition: color 0.3s ease;
        }

         /* Ketika kolom input sedang aktif diketik (focus), ubah labelnya menjadi oranye */
        .input-group:focus-within label {
            color: var(--orange);
        }

         .input-group:focus-within .input-wrapper i.icon-left {
            color: var(--orange);
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper i.icon-left {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #94A3B8;
            font-size: 14px;
            pointer-events: none;
            transition: color 0.3s ease;
        }

        .input-wrapper input {
            width: 100%;
            padding: 14px 44px 14px 44px;
            background: #FFFFFF;
            border: 1px solid var(--border-color);
            color: var(--text-dark);
            border-radius: 12px;
            font-size: 13px;
            outline: none;
            transition: all 0.3s ease;
        }

        .input-wrapper input:focus {
            border-color: var(--orange);
            box-shadow: 0 0 12px rgba(255, 84, 0, 0.12);
        }

        .input-wrapper i.icon-right {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #94A3B8;
            font-size: 14px;
            cursor: pointer;
            padding: 4px;
        }

        /* CHECKBOX / REMEMBER ROW */
        .remember-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 16px 0 24px 0;
        }

        .remember-row .forgot-link:hover {
            color: var(--orange-hover);
            text-decoration: underline !important;
        }

        .card-footer a:hover {
            color: var(--orange-hover);
            text-decoration: underline !important;
        }

        .check-container {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .check-container label {
            color: var(--text-muted);
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            user-select: none;
        }

        input[type="checkbox"] {
            accent-color: var(--orange);
            width: 15px;
            height: 15px;
            cursor: pointer;
        }

        .remember-row .forgot-link {
            color: var(--orange);
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
        }

        /* SUBMIT BUTTON */
        .btn-submit {
            width: 100%;
            padding: 15px;
            background: var(--orange);
            color: #ffffff;
            border: none;
            border-radius: 12px;
            font-weight: 750;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-submit:hover {
            background: var(--orange-hover);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(255, 84, 0, 0.25);
        }

        /* DIVIDER */
        .divider {
            display: flex;
            align-items: center;
            margin: 24px 0;
            color: #94A3B8;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .divider::before, .divider::after {
            content: "";
            flex: 1;
            height: 1px;
            background: var(--border-color);
        }

        .divider span {
            padding: 0 12px;
        }

        /* GOOGLE LOGIN */
        .btn-google {
           width: 100%;
            padding: 13px;
            background: #ffffff;
            color: var(--text-dark);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-weight: 700;
            font-size: 13px;
            
            /* Transisi melayang */
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

         .btn-google img {
            transition: transform 0.3s ease;
        }

        .btn-google:hover {
           background: var(--bg-light);
            border-color: #CBD5E1;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.04);
        }

        .btn-google:hover img {
            transform: scale(1.1);
        }


        /* CARD FOOTER */
        .card-footer {
            margin-top: 24px;
            font-size: 12px;
            color: var(--text-muted);
        }

        .card-footer a {
            color: var(--orange);
            text-decoration: none;
            font-weight: 700;
        }


        /* FEATURES BAR (WHITE HORIZONTAL BAR) */
        .features-bar {
            padding: 40px 8%;
            background: #FFFFFF;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
            border-bottom: 1px solid var(--border-color);
        }

        .feat-bar-item {
              display: flex;
            align-items: center;
            gap: 16px;
            padding: 12px 16px;
            border-radius: 12px;
            cursor: pointer;
            
            /* Transisi super halus */
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

          /* EFEK HOVER: Item melayang naik tipis & background abu-abu sangat pudar */
        .feat-bar-item:hover {
            transform: translateY(-5px);
            background-color: var(--bg-light);
        }

        .feat-bar-icon {
           width: 52px;
            height: 52px;
            background: #FFF0E9;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            color: var(--orange);
            font-size: 18px;
            
            /* Transisi melayang ikon */
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        /* Ikon membesar, berputar tipis, dan berpendar saat dihover */
        .feat-bar-item:hover .feat-bar-icon {
            background: var(--orange);
            color: #ffffff;
            transform: scale(1.08); /* Sedikit membesar */
            box-shadow: 0 8px 16px rgba(255, 84, 0, 0.2); /* Efek pendaran cahaya oranye */
        }

        .feat-bar-icon i {
            display: inline-block;
            transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        /* Rotasi mikro ikon saat dihover */
        .feat-bar-item:hover .feat-bar-icon i {
            transform: scale(1.1) rotate(10deg);
        }

        .feat-bar-text h4 {
            font-size: 14px;
            font-weight: 700;
            color: var(--dark-blue);
            margin-bottom: 2px;
            transition: color 0.3s ease;
        }

         /* Judul teks berubah menjadi oranye saat dihover */
        .feat-bar-item:hover .feat-bar-text h4 {
            color: var(--orange);
        }

        .feat-bar-text p {
            font-size: 12px;
            color: var(--text-muted);
        }


        /* MAIN FOOTER (DARK THEME) */
        footer {
            background: #0F172A;
            color: #94A3B8;
            padding: 80px 8% 40px 8%;
        }

        .footer-grid {
            display: grid;
            grid-template-columns: 1.5fr 1fr 1fr 1fr;
            gap: 40px;
            margin-bottom: 60px;
        }

        .footer-brand .logo {
            color: white;
            margin-bottom: 20px;
            font-size: 24px;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .footer-brand .logo span {
            color: var(--orange);
        }

        .footer-brand p {
          font-size: 13px;
            line-height: 1.6;
            margin-bottom: 24px;
        }

        .social-links {
            display: flex;
            gap: 16px;
        }

        .social-links a {
              width: 36px;
    height: 36px;
    background: #1E293B;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    
    /* TAMBAHKAN DUA BARIS INI: Paksa hilangkan segala jenis garis bawah */
    text-decoration: none !important;
    border-bottom: none !important;
    
    transition: all 0.3s ease;
        }

        .social-links a:hover {
            background: var(--orange);
    text-decoration: none !important;
    border-bottom: none !important;
        }

        .footer-col h4 {
             color: white;
            font-size: 15px;
            font-weight: 700;
            margin-bottom: 24px;
        }

        .footer-links {
          list-style: none;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .footer-links li a {
            color: #94A3B8;
            font-size: 13px;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .footer-links li a:hover {
            color: white;
        }

        .footer-contact-info {
          display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .contact-item {
            display: flex;
            gap: 12px;
            font-size: 13px;
        }

        .contact-item i {
            color: var(--orange);
            margin-top: 3px;
        }

        .footer-bottom {
           padding-top: 40px;
            border-top: 1px solid #1E293B;
            text-align: center;
            font-size: 12px;
        }

         /* 1. TOMBOL SILANG (X) BULAT MELAYANG DENGAN EFEK ROTASI */
        .btn-close-auth {
            position: absolute;
            top: 30px;
            left: 40px;
            width: 44px;
            height: 44px;
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #ffffff;
            border-radius: 50%; /* Bulat Sempurna */
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            text-decoration: none;
            z-index: 100;
            cursor: pointer;
            
            /* Transisi putaran mekanis */
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }

         .btn-close-auth:hover {
            background: var(--orange);
            border-color: var(--orange);
            color: #ffffff;
            transform: scale(1.08) rotate(90deg); /* Berputar elegan */
            box-shadow: 0 8px 20px rgba(255, 84, 0, 0.3);
        }

         /* Ketika kolom error: Garis tepi berubah merah & latar merah pudar */
        .input-wrapper.error input {
            border-color: #EF4444 !important; 
            background-color: #FEF2F2 !important; 
        }

         /* Ikon dalam kolom ikut berubah merah saat error */
        .input-wrapper.error i.icon-left {
            color: #EF4444 !important;
        }

             /* Label teks di atas kolom ikut berubah merah saat error */
        .input-group.error-active label {
            color: #EF4444 !important;
        }

        /* Gaya teks peringatan "Wajib diisi" di bawah kolom */
        .error-text {
            font-size: 11px;
            color: #EF4444;
            font-weight: 600;
            margin-top: 6px;
            display: none; /* Tersembunyi secara default */
            animation: fadeInError 0.2s ease-out; /* Animasi memancar halus */
        }

        @keyframes fadeInError {
            from { opacity: 0; transform: translateY(-4px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* RESPONSIVITAS */
        @media (max-width: 992px) {
            .auth-hero-wrapper {
                grid-template-columns: 1fr;
                padding-top: 140px;
                text-align: center;
            }
            .auth-info .intro-p {
                margin: 0 auto 40px auto;
            }
            .info-list {
                align-items: center;
                margin-bottom: 40px;
            }
            .auth-card-container {
                justify-content: center;
            }
            .features-bar {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            .footer-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 576px) {
            .footer-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

    <!-- KEMBALI KE BERANDA -->
     <a href="index.php" class="btn-close-auth" title="Kembali ke Beranda">
        <i class="fa-solid fa-xmark"></i>
    </a>

    <!-- AUTH HERO SECTION -->
    <div class="auth-hero-wrapper">
        <!-- Kiri: Info Hoopball -->
        <div class="auth-info">
            <h2>Masuk ke Akun<br><span>HoopBall</span></h2>
            <p class="intro-p">Login untuk booking lapangan, cek jadwal, dan nikmati promo member dengan lebih mudah.</p>
            
            <div class="info-list">
                <div class="info-item">
                    <div class="info-icon"><i class="fa-solid fa-bolt"></i></div>
                    <div class="info-text">
                        <h4>Booking cepat</h4>
                        <p>Pesan lapangan favorit kapan saja, di mana saja.</p>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-icon"><i class="fa-regular fa-clock"></i></div>
                    <div class="info-text">
                        <h4>Jadwal real-time</h4>
                        <p>Cek ketersediaan lapangan secara akurat.</p>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-icon"><i class="fa-solid fa-tags"></i></div>
                    <div class="info-text">
                        <h4>Promo member</h4>
                        <p>Dapatkan penawaran eksklusif khusus member.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kanan: White Floating Card -->
        <div class="auth-card-container">
            <div class="auth-card">
                <h3>Login</h3>
                <span class="card-subtitle">Selamat datang kembali!</span>

          <form method="POST" action="" id="loginForm" novalidate>
    <div class="input-group">
        <label>Email<span>*</span></label>
        <div class="input-wrapper">
            <i class="fa-regular fa-envelope icon-left"></i>
            <!-- Atribut "required" dihapus karena digantikan validasi JS kustom -->
            <input type="text" name="user_input" placeholder="Masukkan email Anda" value="<?= htmlspecialchars($remembered_user) ?>">
        </div>
        <!-- Wadah pesan error email -->
        <span class="error-text" id="emailError"></span>
    </div>
    
    <div class="input-group">
        <label>Password<span>*</span></label>
        <div class="input-wrapper">
            <i class="fa-solid fa-lock icon-left"></i>
            <input type="password" name="password_input" id="passwordInput" placeholder="Masukkan password Anda">
            <i class="fa-solid fa-eye icon-right" id="togglePass" onclick="togglePassword()"></i>
        </div>
        <!-- Wadah pesan error password -->
        <span class="error-text" id="passwordError"></span>
    </div>

    <div class="remember-row">
        <div class="check-container">
            <input type="checkbox" name="remember" id="rem" <?= $remembered_user ? 'checked' : '' ?>>
            <label for="rem">Ingat saya</label>
        </div>
        <a href="#" class="forgot-link">Lupa password?</a>
    </div>

    <button type="submit" name="login" class="btn-submit">Masuk</button>
</form>

                <div class="divider"><span>atau</span></div>

                <a href="#" class="btn-google">
                    <img src="https://www.gstatic.com/images/branding/product/1x/googleg_48dp.png" alt="Google" width="16"> Masuk dengan Google
                </a>

                <p class="card-footer">Belum punya akun? <a href="register.php">Daftar sekarang</a></p>
            </div>
        </div>
    </div>

    <!-- FEATURES BAR -->
    <section class="features-bar">
        <div class="feat-bar-item">
            <div class="feat-bar-icon"><i class="fa-regular fa-circle-check"></i></div>
            <div class="feat-bar-text">
                <h4>Aman & Terpercaya</h4>
                <p>Data pribadi Anda aman dan kami lindungi.</p>
            </div>
        </div>
        <div class="feat-bar-item">
            <div class="feat-bar-icon"><i class="fa-regular fa-calendar"></i></div>
            <div class="feat-bar-text">
                <h4>Kelola dengan Mudah</h4>
                <p>Riwayat booking dan jadwal tersimpan rapi.</p>
            </div>
        </div>
        <div class="feat-bar-item">
            <div class="feat-bar-icon"><i class="fa-regular fa-star"></i></div>
            <div class="feat-bar-text">
                <h4>Pengalaman Premium</h4>
                <p>Nikmati fitur eksklusif khusus untuk member.</p>
            </div>
        </div>
    </section>

    <!-- FOOTER -->
     <footer id="tentang-kami">
        <div class="footer-grid">
            <div class="footer-brand">
                <a href="#" class="logo"><i class="fa-solid fa-basketball"
                        style="color: var(--orange)"></i>Hoop<span>Ball</span></a>
                <p>Platform penyewaan lapangan basket online yang mudah, cepat, dan terpercaya.</p>
                <div class="social-links">
                    <a href="#"><i class="fa-brands fa-instagram"></i></a>
                    <a href="#"><i class="fa-brands fa-facebook"></i></a>
                    <a href="#"><i class="fa-brands fa-youtube"></i></a>
                    <a href="#"><i class="fa-brands fa-tiktok"></i></a>
                </div>
            </div>

            <div class="footer-col">
                <h4>Kontak Kami</h4>
                <div class="footer-contact-info">
                    <div class="contact-item">
                        <i class="fa-solid fa-phone"></i>
                        <span>0812-3456-7890</span>
                    </div>
                    <div class="contact-item">
                        <i class="fa-solid fa-envelope"></i>
                        <span>info@hoopball.id</span>
                    </div>
                    <div class="contact-item">
                        <i class="fa-solid fa-location-dot"></i>
                        <span>Jl. Sunset Road No. 123, Jakarta Selatan, 12050</span>
                    </div>
                </div>
            </div>

            <div class="footer-col">
                <h4>Tautan</h4>
                <ul class="footer-links">
                    <li><a href="index.php#lapangan">Lapangan</a></li>
                    <li><a href="index.php#jadwal">Jadwal</a></li>
                    <li><a href="index.php#member">Alat Basket</a></li>
                    <li><a href="index.php#tentang-kami">Tentang Kami</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h4>Informasi</h4>
                <ul class="footer-links">
                    <li><a href="#">Cara Pemesanan</a></li>
                    <li><a href="#">Syarat & Ketentuan</a></li>
                    <li><a href="#">Kebijakan Privasi</a></li>
                    <li><a href="#">FAQ</a></li>
                    <li><a href="#">Hubungi Kami</a></li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <p>&copy; 2024 HoopBall. All rights reserved.</p>
        </div>
    </footer>

    <!-- SWEETALERT ERROR NOTIFICATION -->
    <?php if($error_msg): ?>
    <script>
        Swal.fire({
            icon: 'error',
            title: 'Login Gagal',
            text: '<?= addslashes($error_msg) ?>',
            background: '#ffffff',
            color: '#1e293b',
            confirmButtonColor: '#FF5400'
        });
    </script>
    <?php endif; ?>

    <!-- TOGGLE PASSWORD VISIBILITY SCRIPT -->
    <script>
    function togglePassword() {
        const passInput = document.getElementById('passwordInput');
        const toggleIcon = document.getElementById('togglePass');

        if (passInput.type === 'password') {
            passInput.type = 'text';
            toggleIcon.classList.remove('fa-eye');
            toggleIcon.classList.add('fa-eye-slash');
        } else {
            passInput.type = 'password';
            toggleIcon.classList.remove('fa-eye-slash');
            toggleIcon.classList.add('fa-eye');
        }
    }


    // LOGIKA VALIDASI FORM KUSTOM (AKAN BERJALAN SAAT TOMBOL MASUK DIKLIK)
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('loginForm');
            const userInput = document.querySelector('input[name="user_input"]');
            const passwordInput = document.getElementById('passwordInput');
            
            const emailError = document.getElementById('emailError');
            const passwordError = document.getElementById('passwordError');

            form.addEventListener('submit', function (e) {
                let isValid = true;

                // 1. Validasi Kolom Email / Username
                if (userInput.value.trim() === '') {
                    userInput.parentElement.classList.add('error');
                    userInput.parentElement.parentElement.classList.add('error-active');
                    emailError.textContent = 'Email atau Username wajib diisi.';
                    emailError.style.display = 'block';
                    isValid = false;
                } else {
                    userInput.parentElement.classList.remove('error');
                    userInput.parentElement.parentElement.classList.remove('error-active');
                    emailError.style.display = 'none';
                }

                // 2. Validasi Kolom Password
                if (passwordInput.value.trim() === '') {
                    passwordInput.parentElement.classList.add('error');
                    passwordInput.parentElement.parentElement.classList.add('error-active');
                    passwordError.textContent = 'Password wajib diisi.';
                    passwordError.style.display = 'block';
                    isValid = false;
                } else {
                    passwordInput.parentElement.classList.remove('error');
                    passwordInput.parentElement.parentElement.classList.remove('error-active');
                    passwordError.style.display = 'none';
                }

                // Jika ada kolom yang kosong, cegah form dikirimkan ke database
                if (!isValid) {
                    e.preventDefault();
                }
            });

            // EFEK SANGAT MULUS (CLEAR ERROR ON TYPE): 
            // Begitu pengguna mengetik di kolom tersebut, warna merah & pesan error langsung hilang otomatis
            userInput.addEventListener('input', () => {
                userInput.parentElement.classList.remove('error');
                userInput.parentElement.parentElement.classList.remove('error-active');
                emailError.style.display = 'none';
            });

            passwordInput.addEventListener('input', () => {
                passwordInput.parentElement.classList.remove('error');
                passwordInput.parentElement.parentElement.classList.remove('error-active');
                passwordError.style.display = 'none';
            });
        });
    </script>

</body>
</html>