<?php
session_start();
include 'includes/config.php';

$res_status = "";
$res_msg = "";

if (isset($_POST['register'])) {
    $nama     = $_POST['nama'];
    $username = $_POST['username'];
    $email    = $_POST['email'];
    $telp     = $_POST['telp'];
    $jk_input = $_POST['jk']; // Bernilai '1' atau '2'
    $password = $_POST['password'];
    $alamat   = $_POST['alamat'];

    // PERBAIKAN 1: Jenis Kelamin di database baru Anda berupa INTEGER (1 = Laki-laki, 2 = Perempuan)
    $jk = (int)$jk_input;

    $sql_check = "SELECT Username, Email FROM Akun WHERE Username = ? OR Email = ?";
    $stmt_check = sqlsrv_query($conn, $sql_check, array($username, $email));

    if (sqlsrv_has_rows($stmt_check)) {
        $res_status = "error";
        $res_msg = "Username atau Email sudah terdaftar!";
    } else {
        sqlsrv_begin_transaction($conn);

        // PERBAIKAN 2: Mengikuti format ID Akun baru Anda: AK0001 (Panjang 6 digit, diawali AK + 4 digit angka)
        $q_akn = sqlsrv_query($conn, "SELECT MAX(ID_Akun) as max_id FROM Akun");
        $d_akn = sqlsrv_fetch_array($q_akn, SQLSRV_FETCH_ASSOC);
        $num_akn = ($d_akn['max_id']) ? (int) substr($d_akn['max_id'], 2) + 1 : 1;
        $id_akun_baru = "AK" . sprintf("%04d", $num_akn);

        // PERBAIKAN 3: Mengubah input Role menjadi angka 1 (Customer) dan Status menjadi 1 (Aktif) sesuai database baru
        $sql_akun = "INSERT INTO Akun (ID_Akun, Username, Email, Kata_Sandi, Role, Status, Created_By) VALUES (?,?,?,?,1,1,'System')";
        $stmt_akun = sqlsrv_query($conn, $sql_akun, array($id_akun_baru, $username, $email, $password));

        // PERBAIKAN 4: Mengikuti format ID Customer baru Anda: CS0001 (Panjang 6 digit, diawali CS + 4 digit angka)
        $q_cus = sqlsrv_query($conn, "SELECT MAX(ID_Customer) as max_id FROM Customer");
        $d_cus = sqlsrv_fetch_array($q_cus, SQLSRV_FETCH_ASSOC);
        $num_cus = ($d_cus['max_id']) ? (int) substr($d_cus['max_id'], 2) + 1 : 1;
        $id_cus_baru = "CS" . sprintf("%04d", $num_cus);

        // PERBAIKAN 5: Memasukkan data pendaftaran Customer baru
        $sql_customer = "INSERT INTO Customer (ID_Customer, ID_Akun, Nama_Customer, Jenis_Kelamin, Alamat, No_Telepon, Status, Created_By) VALUES (?,?,?,?,?,?,1,'System')";
        $stmt_customer = sqlsrv_query($conn, $sql_customer, array($id_cus_baru, $id_akun_baru, $nama, $jk, $alamat, $telp));

        if ($stmt_akun && $stmt_customer) {
            sqlsrv_commit($conn);
            $res_status = "success";
            $res_msg = "Pendaftaran Berhasil! Silahkan Login.";
        } else {
            sqlsrv_rollback($conn);
            $res_status = "error";
            $res_msg = "Terjadi kesalahan sistem saat mendaftar.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrasi | HoopBall BasketPro</title>
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

        /* TOMBOL SILANG (X) BULAT MELAYANG */
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
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            text-decoration: none;
            z-index: 100;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .btn-close-auth:hover {
            background: var(--orange);
            border-color: var(--orange);
            color: #ffffff;
            transform: scale(1.08) rotate(90deg);
            box-shadow: 0 8px 20px rgba(255, 84, 0, 0.3);
        }

        /* HERO AUTH WRAPPER (DARK BACKGROUND) */
        .auth-hero-wrapper {
  background: linear-gradient(rgba(0, 0, 0, 0.8), rgba(0, 0, 0, 0.85)), url('login.png') no-repeat center center;
    background-size: cover;
    background-attachment: fixed;
    
    /* DIUBAH: Jarak atas dikurangi lagi menjadi 60px agar kotak putih naik lebih tinggi */
    padding: 60px 8% 150px 8%; 
    
    display: grid;
    grid-template-columns: 1fr 1.3fr;
    gap: 50px;
    align-items: start; 
    min-height: 100vh;
        }

        .auth-info {
  align-self: start;
    
    /* DIUBAH: Ditingkatkan menjadi 100px agar mengompensasi tarikan naik, 
       sehingga posisi teks kiri tetap diam murni di tempatnya saat ini */
    margin-top: 100px; 
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

        /* SISI KANAN: FLOATING WHITE CARD REGISTRASI */
        .auth-card-container {
                display: flex;
    justify-content: flex-end;
    align-self: start; /* Mengunci wadah kotak putih tetap di atas */
        }

        .auth-card {
     background: #ffffff;
    border-radius: 24px;
    width: 100%;
    max-width: 450px; 
    padding: 44px 36px;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
    text-align: center;
    
    /* DIUBAH: Gunakan 'min-height' sebesar 660px (bukan 'height' kaku).
       Ini mengunci tinggi dasar di 660px agar tidak bergeser saat ganti step,
       namun otomatis melar ke bawah jika ada teks error merah yang muncul */
    min-height: 660px; 
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
            margin-bottom: 24px;
            display: block;
        }

        /* INDIKATOR LANGKAH (STEP PROGRESS INDICATOR) */
        .step-indicator {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-bottom: 32px;
        }

        .step-dot {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--bg-light);
            border: 1px solid var(--border-color);
            color: var(--text-muted);
            font-size: 13px;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .step-dot.active {
            background: var(--orange);
            border-color: var(--orange);
            color: #ffffff;
            box-shadow: 0 0 10px rgba(255, 84, 0, 0.25);
        }

        .step-line {
            width: 60px;
            height: 2px;
            background: var(--border-color);
            transition: all 0.3s ease;
        }

        .step-line.active {
            background: var(--orange);
        }

        /* TRANSISI ANIMASI PERALIHAN STEP */
        .form-step {
            display: none; 
            animation: fadeStep 0.4s ease-out forwards;
        }

        .form-step.active {
            display: block;
        }

        @keyframes fadeStep {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* FORM GRID & INPUT (LAYOUT 1 KOLOM PENUH) */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr; /* Diubah menjadi 1 kolom penuh */
            gap: 16px;
            margin-bottom: 24px;
        }

        .input-group {
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

        .input-group:focus-within label {
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

        .input-group:focus-within .input-wrapper i.icon-left {
            color: var(--orange);
        }

        .input-wrapper input,
        .input-wrapper select {
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

        .input-wrapper select {
            appearance: none;
            -webkit-appearance: none;
            cursor: pointer;
        }

        .input-wrapper input:focus,
        .input-wrapper select:focus {
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

        /* CUSTOM ERROR VALIDATION GAYA MERAH */
        .input-wrapper.error input,
        .input-wrapper.error select {
            border-color: #EF4444 !important;
            background-color: #FEF2F2 !important;
        }

        .input-wrapper.error i.icon-left {
            color: #EF4444 !important;
        }

        .input-group.error-active label {
            color: #EF4444 !important;
        }

        .error-text {
            font-size: 11px;
            color: #EF4444;
            font-weight: 600;
            margin-top: 6px;
            display: none;
            animation: fadeInError 0.2s ease-out;
        }

        @keyframes fadeInError {
            from { opacity: 0; transform: translateY(-4px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* BUTTONS */
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

        /* KELOMPOK TOMBOL STEP 2 (FLEX) */
        .step-buttons {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }

        .btn-back-step {
            background: #ffffff;
            color: var(--text-dark);
            border: 1px solid var(--border-color);
            padding: 15px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            flex: 1;
            transition: all 0.3s ease;
        }

        .btn-back-step:hover {
            background: var(--bg-light);
            border-color: #CBD5E1;
        }

        .step-buttons .btn-submit {
            flex: 2; 
        }

        /* CARD FOOTER */
        .card-footer {
            margin-top: 20px;
            font-size: 12px;
            color: var(--text-muted);
        }

        .card-footer a {
            color: var(--orange);
            text-decoration: none;
            font-weight: 700;
        }

        .card-footer a:hover {
            color: var(--orange-hover);
            text-decoration: underline !important;
        }

        /* FEATURES BAR */
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
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

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
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .feat-bar-item:hover .feat-bar-icon {
            background: var(--orange);
            color: #ffffff;
            transform: scale(1.08);
            box-shadow: 0 8px 16px rgba(255, 84, 0, 0.2);
        }

        .feat-bar-icon i {
            display: inline-block;
            transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

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

        .feat-bar-item:hover .feat-bar-text h4 {
            color: var(--orange);
        }

        .feat-bar-text p {
            font-size: 12px;
            color: var(--text-muted);
        }

        /* FOOTER */
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

              .radio-group-container {
            display: flex;
            gap: 12px;
            width: 100%;
            margin-top: 4px;
        }

        .radio-card {
            flex: 1;
            position: relative;
            cursor: pointer;
        }

        /* Menyembunyikan lingkaran bulat radio default bawaan browser */
        .radio-card input[type="radio"] {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }

        /* Kotak tombol kustom yang indah */
        .radio-custom-box {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 14px;
            background: #FFFFFF;
            border: 1.5px solid var(--border-color);
            border-radius: 12px;
            font-size: 13px;
            font-weight: 700;
            color: var(--text-dark);
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        /* Efek Hover (Kursor di atas tombol) */
        .radio-card:hover .radio-custom-box {
            border-color: #CBD5E1;
            background-color: var(--bg-light);
        }

        /* Efek Aktif/Terpilih (Warna Oranye) */
        .radio-card input[type="radio"]:checked + .radio-custom-box {
            border-color: var(--orange);
            background-color: rgba(255, 84, 0, 0.02); /* Latar belakang oranye sangat tipis */
            color: var(--orange);
            box-shadow: 0 0 12px rgba(255, 84, 0, 0.08);
        }

        .radio-custom-box i {
            font-size: 15px;
        }

        @media (max-width: 576px) {
            .footer-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

    <!-- TOMBOL SILANG (X) KEMBALI KE LOGIN -->
    <a href="login.php" class="btn-close-auth" title="Kembali ke Login">
        <i class="fa-solid fa-xmark"></i>
    </a>

    <!-- AUTH HERO SECTION -->
    <div class="auth-hero-wrapper">
        <!-- Kiri: Informasi Pendaftaran -->
        <div class="auth-info">
            <h2>Gabung<br>Sekarang di <span>HoopBall</span></h2>
            <p class="intro-p">Buat akun tim kamu, mulai dominasi lapangan, dan nikmati berbagai kemudahan booking dalam satu genggaman.</p>
            
            <div class="info-list">
                <div class="info-item">
                    <div class="info-icon"><i class="fa-solid fa-bolt"></i></div>
                    <div class="info-text">
                        <h4>Daftar gratis & cepat</h4>
                        <p>Proses pendaftaran cepat kurang dari 1 menit.</p>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-icon"><i class="fa-solid fa-tags"></i></div>
                    <div class="info-text">
                        <h4>Promo anggota baru</h4>
                        <p>Dapatkan diskon sewa perdana setelah registrasi.</p>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-icon"><i class="fa-regular fa-star"></i></div>
                    <div class="info-text">
                        <h4>Akses prioritas</h4>
                        <p>Booking lapangan favorit dengan jadwal lebih awal.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kanan: White Floating Card Registrasi -->
        <div class="auth-card-container">
            <div class="auth-card">
                <h3>Daftar Akun</h3>
                <span class="card-subtitle">Mulai buat akun tim kamu sekarang.</span>

                <!-- INDIKATOR LANGKAH (STEP PROGRESS INDICATOR) -->
                <div class="step-indicator">
                    <div class="step-dot active" id="dot1">1</div>
                    <div class="step-line" id="line1"></div>
                    <div class="step-dot" id="dot2">2</div>
                </div>

                <form method="POST" id="registerForm" novalidate>
                    
                    <!-- ======================================= -->
                    <!-- LANGKAH 1: DATA PRIBADI (NAMA, TELP, JK, ALAMAT) -->
                    <!-- ======================================= -->
                    <div class="form-step active" id="step1">
                        <div class="form-grid">
                            <!-- 1. NAMA LENGKAP -->
                            <div class="input-group">
                                <label>Nama Lengkap<span style="color: red;">*</span></label>
                                <div class="input-wrapper">
                                    <i class="fa-solid fa-user icon-left"></i>
                                    <input type="text" name="nama" id="namaField" placeholder="Budi Santoso" autocomplete="name">
                                </div>
                                <span class="error-text" id="namaError"></span>
                            </div>

                            <!-- 2. NOMOR TELEPON (TEGAK LURUS DI BAWAH NAMA LENGKAP) -->
                         <div class="input-group">
    <label>Nomor Telepon</label>
    <div class="input-wrapper">
        <i class="fa-solid fa-phone icon-left"></i>
        <input type="text" name="telp" id="telpField" placeholder="0812xxxxxxxx" autocomplete="tel" maxlength="13">
    </div>
    <span class="error-text" id="telpError"></span>
</div>

                            <!-- 3. JENIS KELAMIN -->
                           <div class="input-group">
    <label>Jenis Kelamin<span style="color: red;">*</span></label>
    <div class="radio-group-container">
        <!-- Pilihan Laki-laki (Terpilih secara default) -->
        <label class="radio-card">
            <input type="radio" name="jk" value="1" checked>
            <span class="radio-custom-box">
                <i class="fa-solid fa-mars"></i> Laki-laki
            </span>
        </label>
        <!-- Pilihan Perempuan -->
        <label class="radio-card">
            <input type="radio" name="jk" value="2">
            <span class="radio-custom-box">
                <i class="fa-solid fa-venus"></i> Perempuan
            </span>
        </label>
    </div>
    <span class="error-text" id="jkError"></span>
</div>

                            <!-- 4. ALAMAT RUMAH -->
                          <!-- Kolom Alamat Rumah dengan pembatasan fisik maksimal 255 karakter -->
<div class="input-group">
    <label>Alamat<span style="color: red;">*</span></label>
    <div class="input-wrapper">
        <i class="fa-solid fa-location-dot icon-left"></i>
        <input type="text" name="alamat" id="alamatField" placeholder="Jl. Raya Cikarang No. 123" autocomplete="off" maxlength="255">
    </div>
    <span class="error-text" id="alamatError"></span>
</div>
</div>
                        
                        <button type="button" class="btn-submit" id="btnNext" style="margin-top: 24px;">Lanjutkan</button>
                    </div>

                    <!-- ======================================= -->
                    <!-- LANGKAH 2: DATA AKUN (USERNAME, EMAIL, KATA SANDI) -->
                    <!-- ======================================= -->
                  <div class="form-step" id="step2">
                        <div class="form-grid">
                            <!-- 1. USERNAME -->
                            <div class="input-group">
                                <label>Username<span style="color: red;">*</span></label>
                                <div class="input-wrapper">
                                    <i class="fa-solid fa-signature icon-left"></i>
                                    <input type="text" name="username" id="usernameField" placeholder="budi_hoops" autocomplete="username">
                                </div>
                                <span class="error-text" id="usernameError"></span>
                            </div>

                            <!-- 2. EMAIL GMAIL -->
                            <div class="input-group">
                                <label>Email<span style="color: red;">*</span></label>
                                <div class="input-wrapper">
                                    <i class="fa-regular fa-envelope icon-left"></i>
                                    <input type="email" name="email" id="emailField" placeholder="budi@gmail.com" autocomplete="email">
                                </div>
                                <span class="error-text" id="emailError"></span>
                            </div>

                            <!-- 3. KATA SANDI -->
                            <div class="input-group">
                                <label>Kata Sandi<span style="color: red;">*</span></label>
                                <div class="input-wrapper">
                                    <i class="fa-solid fa-lock icon-left"></i>
                                    <input type="password" name="password" id="passwordInput" placeholder="Min. 6 Karakter" autocomplete="new-password">
                                    <i class="fa-solid fa-eye icon-right" id="togglePass" onclick="togglePassword()"></i>
                                </div>
                                <span class="error-text" id="passwordError"></span>
                            </div>

                            <!-- 4. TAMBAHKAN INI: KONFIRMASI KATA SANDI -->
                            <div class="input-group">
                                <label>Konfirmasi Kata Sandi<span style="color: red;">*</span></label>
                                <div class="input-wrapper">
                                    <i class="fa-solid fa-lock icon-left"></i>
                                    <input type="password" name="password_confirm" id="passwordConfirmInput" placeholder="Ulangi Kata Sandi" autocomplete="new-password">
                                    <i class="fa-solid fa-eye icon-right" id="toggleConfirmPass" onclick="toggleConfirmPassword()"></i>
                                </div>
                                <span class="error-text" id="passwordConfirmError"></span>
                            </div>
                        </div>

                        <div class="step-buttons">
                            <button type="button" class="btn-back-step" id="btnBack">Sebelumnya</button>
                            <button type="submit" name="register" class="btn-submit">Daftar Sekarang</button>
                        </div>
                    </div>

                    <p class="card-footer">Sudah memiliki akun? <a href="login.php">Masuk Sekarang</a></p>
                </form>
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
    <footer>
        <div class="footer-grid">
            <div class="footer-brand">
                <a href="#" class="logo"><i class="fa-solid fa-basketball" style="color: var(--orange)"></i>Hoop<span>Ball</span></a>
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

    <!-- SWEETALERT RESPONSE SCRIPT -->
    <script>
        <?php if ($res_status !== ""): ?>
            Swal.fire({
                icon: '<?= $res_status ?>',
                title: '<?= ($res_status == "success") ? "Berhasil!" : "Gagal!" ?>',
                text: '<?= $res_msg ?>',
                background: '#ffffff',
                color: '#1e293b',
                confirmButtonColor: '#FF5400'
            }).then(() => {
                <?php if ($res_status == "success"): ?>
                    window.location.href = 'login.php';
                <?php endif; ?>
            });
        <?php endif; ?>
    </script>

    <!-- LOGIKA INTERAKTIF MULTI-STEP REGISTER & VALIDASI -->
 <!-- KODE SCRIPT TERINTEGRASI (VALIDASI LANGKAH, MATA PASSWORD, & SENSOR ANGKA TELEPON) -->
    <script>
 document.addEventListener('DOMContentLoaded', () => {
        // Navigasi Langkah
        const step1 = document.getElementById('step1');
        const step2 = document.getElementById('step2');
        const btnNext = document.getElementById('btnNext');
        const btnBack = document.getElementById('btnBack');
        
        const dot1 = document.getElementById('dot1');
        const dot2 = document.getElementById('dot2');
        const line1 = document.getElementById('line1');

        // Elemen Input Langkah 1
        const nama = document.getElementById('namaField');
        const telp = document.getElementById('telpField');
        const alamat = document.getElementById('alamatField');
        
        // Elemen Input Langkah 2
        const username = document.getElementById('usernameField');
        const email = document.getElementById('emailField');
        const password = document.getElementById('passwordInput');
        const passwordConfirm = document.getElementById('passwordConfirmInput');
        
        // Elemen Penampung Pesan Error
        const namaError = document.getElementById('namaError');
        const telpError = document.getElementById('telpError');
        const alamatError = document.getElementById('alamatError');
        const usernameError = document.getElementById('usernameError');
        const emailError = document.getElementById('emailError');
        const passwordError = document.getElementById('passwordError');
        const passwordConfirmError = document.getElementById('passwordConfirmError');

        // FUNGSI PEMBANTU VALIDASI (Agar Kode Lebih Ramping & Rapi)
        function setValidationError(inputEl, errorEl, message) {
            inputEl.parentElement.classList.add('error');
            inputEl.parentElement.parentElement.classList.add('error-active');
            errorEl.textContent = message;
            errorEl.style.display = 'block';
        }

        function clearValidationError(inputEl, errorEl) {
            inputEl.parentElement.classList.remove('error');
            inputEl.parentElement.parentElement.classList.remove('error-active');
            errorEl.style.display = 'none';
        }

        // FILTER NAMA LENGKAP: Hanya bisa mengetik huruf dan spasi saja
        nama.addEventListener('input', () => {
            nama.value = nama.value.replace(/[^a-zA-Z\s]/g, '');
        });

        // FILTER ANGKA TELEPON: Hanya bisa mengetik angka saja
        telp.addEventListener('input', () => {
            telp.value = telp.value.replace(/[^0-9]/g, '');
        });

        // Tombol Lanjutkan (Langkah 1 -> Langkah 2)
        btnNext.addEventListener('click', () => {
            let isStep1Valid = true;

            // 1. Validasi Nama Lengkap
            if (nama.value.trim() === '') {
                setValidationError(nama, namaError, 'Nama lengkap wajib diisi.');
                isStep1Valid = false;
            } else {
                clearValidationError(nama, namaError);
            }

            // 2. Validasi No Telepon (Wajib diawali 08, hanya angka, dan panjang 10-12 digit)
            const phonePattern = /^08[0-9]{8,10}$/; 
            if (telp.value.trim() === '') {
                setValidationError(telp, telpError, 'Nomor telepon wajib diisi.');
                isStep1Valid = false;
            } else if (!phonePattern.test(telp.value.trim())) {
                setValidationError(telp, telpError, 'Nomor telepon wajib berupa angka, diawali 08, dan panjang 10-12 digit.');
                isStep1Valid = false;
            } else {
                clearValidationError(telp, telpError);
            }

            // 3. Validasi Alamat Rumah (Sesuai 5 Aturan Spesifik)
            const alamatValue = alamat.value.trim();
            const allowedCharsPattern = /^[a-zA-Z0-9\s,\.\/\-]+$/;
            const onlyNumbersPattern = /^[0-9\s]+$/;
            const onlySymbolsPattern = /^[^a-zA-Z0-9]+$/;

            if (alamatValue === '') {
                setValidationError(alamat, alamatError, 'Alamat rumah wajib diisi.');
                isStep1Valid = false;
            } else if (alamatValue.length < 10 || alamatValue.length > 255) {
                setValidationError(alamat, alamatError, 'Alamat minimal 10 karakter dan maksimal 255 karakter.');
                isStep1Valid = false;
            } else if (!allowedCharsPattern.test(alamatValue)) {
                setValidationError(alamat, alamatError, 'Alamat hanya boleh menggunakan huruf, angka, spasi, koma (,), titik (.), garis miring (/), dan tanda strip (-).');
                isStep1Valid = false;
            } else if (onlyNumbersPattern.test(alamatValue)) {
                setValidationError(alamat, alamatError, 'Alamat tidak boleh hanya berupa angka murni.');
                isStep1Valid = false;
            } else if (onlySymbolsPattern.test(alamatValue)) {
                setValidationError(alamat, alamatError, 'Alamat tidak boleh hanya berupa simbol murni.');
                isStep1Valid = false;
            } else {
                clearValidationError(alamat, alamatError);
            }

            // Jika Langkah 1 valid, beralih ke Langkah 2 dengan transisi
            if (isStep1Valid) {
                step1.classList.remove('active');
                step2.classList.add('active');
                
                dot2.classList.add('active');
                line1.classList.add('active');
            }
        });

        // Tombol Sebelumnya (Langkah 2 -> Langkah 1)
        btnBack.addEventListener('click', () => {
            step2.classList.remove('active');
            step1.classList.add('active');
            
            dot2.classList.remove('active');
            line1.classList.remove('active');
        });

        // Pengiriman Form Akhir (Validasi Langkah 2 dengan Aturan Baru)
        const form = document.getElementById('registerForm');
        form.addEventListener('submit', function (e) {
            let isStep2Valid = true;

            // 1. VALIDASI USERNAME (Aturan Baru)
            const usernameVal = username.value.trim();
            const usernamePattern = /^[a-zA-Z0-9\._]+$/; // Hanya boleh huruf, angka, titik, dan underscore

            if (usernameVal === '') {
                setValidationError(username, usernameError, 'Username wajib diisi.');
                isStep2Valid = false;
            } else if (usernameVal.length < 3 || usernameVal.length > 30) {
                setValidationError(username, usernameError, 'Username minimal 3 karakter dan maksimal 30 karakter.');
                isStep2Valid = false;
            } else if (username.value.includes(' ')) {
                setValidationError(username, usernameError, 'Username tidak boleh mengandung spasi.');
                isStep2Valid = false;
            } else if (!usernamePattern.test(usernameVal)) {
                setValidationError(username, usernameError, 'Username hanya boleh menggunakan huruf, angka, titik (.), dan underscore (_).');
                isStep2Valid = false;
            } else {
                clearValidationError(username, usernameError);
            }

            // 2. VALIDASI EMAIL GMAIL (Aturan Baru)
            const emailVal = email.value.trim();
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/; // Format email dasar

            if (emailVal === '') {
                setValidationError(email, emailError, 'Email wajib diisi.');
                isStep2Valid = false;
            } else if (!emailPattern.test(emailVal)) {
                setValidationError(email, emailError, 'Format email yang dimasukkan tidak valid.');
                isStep2Valid = false;
            } else if (!emailVal.toLowerCase().endsWith('@gmail.com')) {
                setValidationError(email, emailError, 'Email wajib menggunakan domain @gmail.com.');
                isStep2Valid = false;
            } else {
                clearValidationError(email, emailError);
            }

            // 3. VALIDASI KATA SANDI (Aturan Baru)
            const passwordVal = password.value.trim();
            const hasLetter = /[a-zA-Z]/;
            const hasNumber = /[0-9]/;
            
            // Daftar password yang dianggap terlalu mudah ditebak
            const simplePasswords = ['12345678', '87654321', 'password', 'qwertyui', '1234567890', 'hoopball', 'hoopball123'];

            if (passwordVal === '') {
                setValidationError(password, passwordError, 'Kata sandi wajib diisi.');
                isStep2Valid = false;
            } else if (passwordVal.length < 8) {
                setValidationError(password, passwordError, 'Kata sandi minimal berisi 8 karakter.');
                isStep2Valid = false;
            } else if (!hasLetter.test(passwordVal) || !hasNumber.test(passwordVal)) {
                setValidationError(password, passwordError, 'Kata sandi harus berisi kombinasi huruf dan angka.');
                isStep2Valid = false;
            } else if (simplePasswords.includes(passwordVal.toLowerCase())) {
                setValidationError(password, passwordError, 'Kata sandi terlalu mudah ditebak. Silakan gunakan kombinasi lain.');
                isStep2Valid = false;
            } else {
                clearValidationError(password, passwordError);
            }

            // 4. VALIDASI KONFIRMASI KATA SANDI
            const passwordConfirmVal = passwordConfirm.value.trim();
            if (passwordConfirmVal === '') {
                setValidationError(passwordConfirm, passwordConfirmError, 'Konfirmasi kata sandi wajib diisi.');
                isStep2Valid = false;
            } else if (passwordConfirmVal !== passwordVal) {
                setValidationError(passwordConfirm, passwordConfirmError, 'Konfirmasi kata sandi tidak cocok.');
                isStep2Valid = false;
            } else {
                clearValidationError(passwordConfirm, passwordConfirmError);
            }

            // Batalkan pengiriman form jika ada data langkah 2 yang tidak valid
            if (!isStep2Valid) {
                e.preventDefault();
            }
        });

        // PEMBERSIH ERROR STATE OTOMATIS SAAT USER MENGETIK
        const fields = [
            { el: nama, err: namaError },
            { el: telp, err: telpError },
            { el: alamat, err: alamatError },
            { el: username, err: usernameError },
            { el: email, err: emailError },
            { el: password, err: passwordError },
            { el: passwordConfirm, err: passwordConfirmError }
        ];

        fields.forEach(field => {
            field.el.addEventListener('input', () => {
                clearValidationError(field.el, field.err);
            });
        });
    });

    // SCRIPT TOGGLE TELUSUR PASSWORD UTAMA (MATA 1)
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

    // SCRIPT TOGGLE TELUSUR KONFIRMASI PASSWORD (MATA 2)
    function toggleConfirmPassword() {
        const passInput = document.getElementById('passwordConfirmInput');
        const toggleIcon = document.getElementById('toggleConfirmPass');

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
    </script>
</body>
</html>