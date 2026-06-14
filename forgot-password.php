<?php
session_start();
include 'includes/config.php';

$res_status = "";
$res_msg = "";
$is_verified = false;

// TAHAP 1: VERIFIKASI KEAMANAN DATA AKUN
if (isset($_POST['verify_account'])) {
    $username = $_POST['username_input'];
    $email = $_POST['email_input'];
    $telp = $_POST['telp_input'];

    // Query pencocokan silang antara tabel Akun dan Customer
   // Query langsung ke tabel Customer
    $sql = "SELECT ID_Customer FROM Customer 
            WHERE Username = ? AND Email = ? AND No_Telepon = ? AND Is_Deleted = 0";
    $params = array($username, $email, $telp);
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        $res_status = "error";
        $res_msg = "Terjadi kesalahan sistem koneksi database.";
    } else {
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        if ($row) {
            $is_verified = true;
            $_SESSION['reset_id_customer'] = $row['ID_Customer']; // Simpan ID Customer sementara di sesi
        } else {
            $res_status = "error";
            $res_msg = "Data verifikasi salah. Username, Email, atau Telepon tidak cocok!";
        }
    }
}

// TAHAP 2: RESET/UPDATE KATA SANDI BARU
if (isset($_POST['reset_password'])) {
    if (isset($_SESSION['reset_id_akun'])) {
        $id_akun = $_SESSION['reset_id_akun'];
        $new_pass = $_POST['new_password'];

        // AMBIL KATA SANDI LAMA DARI DATABASE UNTUK PENGECEKAN KEAMANAN (ATURAN BARU)
        $q_old = sqlsrv_query($conn, "SELECT Kata_Sandi FROM Akun WHERE ID_Akun = ?", array($id_akun));
        $d_old = sqlsrv_fetch_array($q_old, SQLSRV_FETCH_ASSOC);
        $old_pass = $d_old['Kata_Sandi'] ?? '';

        if ($new_pass === $old_pass) {
            // ATURAN BARU: Menolak jika kata sandi baru sama dengan yang lama
            $res_status = "error";
            $res_msg = "Kata sandi baru tidak boleh sama dengan kata sandi lama Anda!";
        } else {
            $sql = "UPDATE Akun SET Kata_Sandi = ? WHERE ID_Akun = ?";
            $stmt = sqlsrv_query($conn, $sql, array($new_pass, $id_akun));

            if ($stmt !== false) {
                unset($_SESSION['reset_id_akun']); // Hapus sesi pengenal sementara
                $res_status = "success";
                $res_msg = "Kata Sandi Berhasil Diperbarui! Silakan Login Kembali.";
            } else {
                $res_status = "error";
                $res_msg = "Gagal memperbarui kata sandi di sistem database.";
            }
        }
    } else {
        $res_status = "error";
        $res_msg = "Sesi verifikasi telah kedaluwarsa. Silakan verifikasi ulang.";
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Password | HoopBall BasketPro</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap"
        rel="stylesheet">
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

        /* TOMBOL SILANG (X) KEMBALI KE LOGIN */
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

        /* HERO AUTH WRAPPER */
        .auth-hero-wrapper {
            background: linear-gradient(rgba(0, 0, 0, 0.8), rgba(0, 0, 0, 0.85)), url('login.png') no-repeat center center;
            background-size: cover;
            padding: 120px 8% 80px 8%;
            display: grid;
            grid-template-columns: 1.12fr 1fr;
            gap: 50px;
            align-items: start;
            min-height: 100vh;
        }

        .auth-info {
            align-self: start;
            /* Tetap dikunci di atas agar bebas dari pergeseran saat ganti step */

            /* DIUBAH: Ditingkatkan dari 80px menjadi 140px 
       agar posisinya turun ke bawah secara pas dan mandiri */
            margin-top: 100px;
        }

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

        /* SISI KANAN: FLOATING WHITE CARD */
        .auth-card-container {
            display: flex;
            justify-content: flex-end;
            align-self: start;
        }

        .auth-card {
            background: #ffffff;
            border-radius: 24px;
            width: 100%;
            max-width: 450px;
            padding: 44px 36px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
            text-align: center;

            /* DIUBAH: Dari 520px diturunkan menjadi 380px.
       Ini akan membuat kartu otomatis mengecil mengikuti isi Langkah 2 (tinggi ~400px)
       namun tetap bisa melar naik jika ada pesan error merah yang aktif */
            min-height: 380px;
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

        /* INPUT GROUPS */
        .input-group {
            margin-bottom: 20px;
            text-align: left;
        }

        .input-group label {
            font-size: 12px;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 8px;
            display: block;
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

        /* CUSTOM ERROR VALIDATION GAYA MERAH */
        .input-wrapper.error input {
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
            from {
                opacity: 0;
                transform: translateY(-4px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
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

        .card-footer a:hover {
            color: var(--orange-hover);
            text-decoration: underline !important;
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

        /* ========================================================== */
        /* CSS INTERAKTIF FEATURES BAR HORIZONTAL LUPA PASSWORD (BARU) */
        /* ========================================================== */
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

        @media (max-width: 992px) {
            .auth-hero-wrapper {
                grid-template-columns: 1fr;
                padding-top: 140px;
                text-align: center;
            }

            .auth-info .intro-p {
                margin: 0 auto 40px auto;
            }

            .auth-card-container {
                justify-content: center;
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

        html, body {
    /* Untuk Firefox */
    scrollbar-width: none;
    
    /* Untuk Internet Explorer dan Edge versi lama */
    -ms-overflow-style: none;
}

/* Untuk Chrome, Safari, dan Opera */
html::-webkit-scrollbar, 
body::-webkit-scrollbar {
    display: none;
}
body.swal2-shown, 
html.swal2-shown {
    padding-right: 0px !important;
}

    </style>
</head>

<body>

    <!-- TOMBOL SILANG (X) KEMBALI KE LOGIN -->
    <a href="login.php" class="btn-close-auth" title="Batal & Kembali">
        <i class="fa-solid fa-xmark"></i>
    </a>

    <!-- AUTH HERO SECTION -->
    <div class="auth-hero-wrapper">
        <div class="auth-info" style="align-self: start; margin-top: 115px;">
            <h2>Atur Ulang<br><span>Kata Sandi Anda</span></h2>
            <p class="intro-p">Verifikasi identitas kepemilikan akun terdaftar Anda terlebih dahulu untuk membuat kata
                sandi baru.</p>
        </div>

        <div class="auth-card-container">
            <div class="auth-card">
                <h3>Lupa Password</h3>

                <?php if (!$is_verified): ?>
                    <!-- TAMPILAN TAHAP 1: FORM VERIFIKASI IDENTITAS AKUN -->
                    <span class="card-subtitle">Silakan isi data keamanan akun Anda.</span>
                    <form method="POST" id="verifyForm" novalidate>
                        <div class="input-group">
                            <label>Username Terdaftar</label>
                            <div class="input-wrapper">
                                <i class="fa-solid fa-signature icon-left"></i>
                                <input type="text" name="username_input" id="usernameField" placeholder="budi_hoops">
                            </div>
                            <span class="error-text" id="usernameError"></span>
                        </div>

                        <div class="input-group">
                            <label>Email Terdaftar</label>
                            <div class="input-wrapper">
                                <i class="fa-regular fa-envelope icon-left"></i>
                                <input type="email" name="email_input" id="emailField" placeholder="budi@gmail.com">
                            </div>
                            <span class="error-text" id="emailError"></span>
                        </div>

                        <div class="input-group">
                            <label>Nomor Telepon Terdaftar</label>
                            <div class="input-wrapper">
                                <i class="fa-solid fa-phone icon-left"></i>
                                <input type="text" name="telp_input" id="telpField" placeholder="0812xxxxxxxx"
                                    autocomplete="tel" maxlength="13">
                            </div>
                            <span class="error-text" id="telpError"></span>
                        </div>

                        <button type="submit" name="verify_account" class="btn-submit" style="margin-top: 10px;">Verifikasi
                            Akun</button>
                        <p class="card-footer">Kembali ke halaman <a href="login.php">Login</a></p>
                    </form>
                <?php else: ?>
                    <!-- TAMPILAN TAHAP 2: FORM RESET PASSWORD BARU (KINI DENGAN ATURAN BARU YANG KETAT) -->
                    <span class="card-subtitle" style="color:var(--orange);"><b>Akun Terverifikasi!</b> Tulis Kata Sandi
                        baru.</span>
                    <form method="POST" id="resetForm" novalidate>
                        <div class="input-group">
                            <label>Kata Sandi Baru</label>
                            <div class="input-wrapper">
                                <i class="fa-solid fa-lock icon-left"></i>
                                <!-- Tambahkan pembatasan fisik maksimal 50 karakter -->
                                <input type="password" name="new_password" id="passwordInput"
                                    placeholder="Min. 8 Karakter (Huruf & Angka)" maxlength="50">
                                <i class="fa-solid fa-eye icon-right" id="togglePass" onclick="togglePassword()"></i>
                            </div>
                            <span class="error-text" id="passwordError"></span>
                        </div>

                        <div class="input-group">
                            <label>Konfirmasi Kata Sandi Baru</label>
                            <div class="input-wrapper">
                                <i class="fa-solid fa-lock icon-left"></i>
                                <input type="password" name="password_confirm" id="passwordConfirmInput"
                                    placeholder="Ulangi Kata Sandi" maxlength="50">
                                <i class="fa-solid fa-eye icon-right" id="toggleConfirmPass"
                                    onclick="toggleConfirmPassword()"></i>
                            </div>
                            <span class="error-text" id="passwordConfirmError"></span>
                        </div>

                        <button type="submit" name="reset_password" class="btn-submit" style="margin-top: 30px;">Reset Kata
                            Sandi</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ========================================================== -->
    <!-- TAMBAHKAN BARIS BARU INI: FEATURES BAR HORIZONTAL PUTIH -->
    <!-- ========================================================== */ -->
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

    <!-- RESPONSE SWEETALERT -->
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

    <!-- VALIDASI JAVASCRIPT & EVENT HANDLERS -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {

            // FUNGSI PEMBANTU VALIDASI
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

            // TAHAP 1: VALIDASI FORM VERIFIKASI AKUN
            const verifyForm = document.getElementById('verifyForm');
            if (verifyForm) {
                const username = document.getElementById('usernameField');
                const email = document.getElementById('emailField');
                const telp = document.getElementById('telpField');

                const usernameError = document.getElementById('usernameError');
                const emailError = document.getElementById('emailError');
                const telpError = document.getElementById('telpError');

                // FILTER REAL-TIME: Memaksa kolom telepon HANYA bisa diketik angka saja
                telp.addEventListener('input', () => {
                    telp.value = telp.value.replace(/[^0-9]/g, '');
                });

                verifyForm.addEventListener('submit', function (e) {
                    let isValid = true;

                    // 1. VALIDASI USERNAME (3-30 Karakter, No Spasi, Huruf/Angka/Titik/Underscore)
                    const usernameVal = username.value.trim();
                    const usernamePattern = /^[a-zA-Z0-9\._]+$/;

                    if (usernameVal === '') {
                        setValidationError(username, usernameError, 'Username wajib diisi.');
                        isValid = false;
                    } else if (usernameVal.length < 3 || usernameVal.length > 30) {
                        setValidationError(username, usernameError, 'Username minimal 3 karakter dan maksimal 30 karakter.');
                        isValid = false;
                    } else if (username.value.includes(' ')) {
                        setValidationError(username, usernameError, 'Username tidak boleh menggunakan spasi.');
                        isValid = false;
                    } else if (!usernamePattern.test(usernameVal)) {
                        setValidationError(username, usernameError, 'Username hanya boleh berisi huruf, angka, titik (.), dan underscore (_).');
                        isValid = false;
                    } else {
                        clearValidationError(username, usernameError);
                    }

                    // 2. VALIDASI EMAIL (Format Email Benar)
                    const emailVal = email.value.trim();
                    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

                    if (emailVal === '') {
                        setValidationError(email, emailError, 'Email wajib diisi.');
                        isValid = false;
                    } else if (!emailPattern.test(emailVal)) {
                        setValidationError(email, emailError, 'Format email yang dimasukkan tidak benar.');
                        isValid = false;
                    } else {
                        clearValidationError(email, emailError);
                    }

                    // 3. VALIDASI TELEPON (Hanya angka murni, 10-13 digit)
                    const telpVal = telp.value.trim();
                    const phonePattern = /^[0-9]{10,13}$/;

                    if (telpVal === '') {
                        setValidationError(telp, telpError, 'Nomor telepon wajib diisi.');
                        isValid = false;
                    } else if (!phonePattern.test(telpVal)) {
                        setValidationError(telp, telpError, 'Nomor telepon hanya boleh angka, minimal 10 digit, dan maksimal 13 digit.');
                        isValid = false;
                    } else {
                        clearValidationError(telp, telpError);
                    }

                    if (!isValid) e.preventDefault();
                });

                // Pembersih Error saat Mengetik (Verify Form)
                const fieldsVerify = [
                    { el: username, err: usernameError },
                    { el: email, err: emailError },
                    { el: telp, err: telpError }
                ];
                fieldsVerify.forEach(field => {
                    field.el.addEventListener('input', () => {
                        clearValidationError(field.el, field.err);
                    });
                });
            }

            // TAHAP 2: VALIDASI FORM RESET PASSWORD BARU (ATURAN KETAT TERBARU)
            const resetForm = document.getElementById('resetForm');
            if (resetForm) {
                const password = document.getElementById('passwordInput');
                const passwordConfirm = document.getElementById('passwordConfirmInput');

                const passwordError = document.getElementById('passwordError');
                const passwordConfirmError = document.getElementById('passwordConfirmError');

                resetForm.addEventListener('submit', function (e) {
                    let isValid = true;
                    const hasLetter = /[a-zA-Z]/;
                    const hasNumber = /[0-9]/;

                    // Daftar password yang dianggap terlalu mudah ditebak (seperti aturan Anda)
                    const simplePasswords = ['12345678', '87654321', 'password', 'qwertyui', '1234567890', 'password123'];

                    // 1. Validasi Kata Sandi Baru (Min 8, Max 50, Kombinasi Huruf & Angka, Tidak Mudah)
                    const passwordVal = password.value.trim();
                    if (passwordVal === '') {
                        setValidationError(password, passwordError, 'Kata sandi baru wajib diisi.');
                        isValid = false;
                    } else if (passwordVal.length < 8 || passwordVal.length > 50) {
                        setValidationError(password, passwordError, 'Kata sandi baru minimal 8 karakter dan maksimal 50 karakter.');
                        isValid = false;
                    } else if (!hasLetter.test(passwordVal) || !hasNumber.test(passwordVal)) {
                        setValidationError(password, passwordError, 'Kata sandi baru harus berisi kombinasi huruf dan angka.');
                        isValid = false;
                    } else if (simplePasswords.includes(passwordVal.toLowerCase())) {
                        setValidationError(password, passwordError, 'Kata sandi terlalu mudah ditebak (seperti 12345678 atau password123). Gunakan kombinasi lain.');
                        isValid = false;
                    } else {
                        clearValidationError(password, passwordError);
                    }

                    // 2. Validasi Konfirmasi Kata Sandi Baru (Wajib Diisi, Harus Sama)
                    const passwordConfirmVal = passwordConfirm.value.trim();
                    if (passwordConfirmVal === '') {
                        setValidationError(passwordConfirm, passwordConfirmError, 'Konfirmasi kata sandi wajib diisi.');
                        isValid = false;
                    } else if (passwordConfirmVal !== passwordVal) {
                        setValidationError(passwordConfirm, passwordConfirmError, 'Konfirmasi kata sandi tidak cocok.');
                        isValid = false;
                    } else {
                        clearValidationError(passwordConfirm, passwordConfirmError);
                    }

                    if (!isValid) e.preventDefault();
                });

                // Pembersih Error saat Mengetik (Reset Form)
                const fieldsReset = [
                    { el: password, err: passwordError },
                    { el: passwordConfirm, err: passwordConfirmError }
                ];
                fieldsReset.forEach(field => {
                    field.el.addEventListener('input', () => {
                        clearValidationError(field.el, field.err);
                    });
                });
            }
        });

        // TOGGLE MATA PASSWORD UTAMA
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

        // TOGGLE MATA KONFIRMASI PASSWORD BARU
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