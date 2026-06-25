<?php
session_start();
// Menjaga status alur halaman utama
if (!isset($_SESSION['intro_done'])) {
    header("Location: intro.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FAQ - HoopBall</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Barlow+Condensed:wght@700;800;900&family=Barlow:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* ─── SCROLLBAR HIDDEN ─────────────────────── */
        html {
            scroll-behavior: smooth;
            scrollbar-width: none;
            -ms-overflow-style: none;
            background-color: #FFFFFF !important;
        }

        html::-webkit-scrollbar {
            display: none;
        }

        /* ─── CSS VARIABLES ───────────────────────── */
        :root {
            --orange: #FF4500;
            --orange-light: #FF6B35;
            --orange-dark: #CC3700;
            --orange-glow: rgba(255, 69, 0, 0.55);
            --dark: #0A0E17;
            --text-dark: #1E293B;
            --text-muted: #64748B;
            --bg-light: #F8FAFC;
            --border-color: #E2E8F0;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Barlow', sans-serif;
        }

        body {
            background-color: #FFFFFF !important;
            color: var(--text-dark);
            overflow-x: hidden;
            line-height: 1.5;
        }

        a {
            text-decoration: none;
            transition: all 0.3s ease;
        }

        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* ═══════════════════════════════════════════
           NAVBAR (Sama dengan Index)
           ═══════════════════════════════════════════ */
        .navbar {
            position: sticky;
            top: 0;
            background: #FFFFFF;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 76px;
            padding: 0 80px;
            border-bottom: 1px solid #E5E5EA;
            box-shadow: none;
            z-index: 1000;
            font-family: 'Plus Jakarta Sans', sans-serif;
            animation: fadeInDown 0.6s ease-out forwards !important;
        }

        .logo {
            display: flex;
            align-items: center;
            text-decoration: none;
            gap: 10px;
            transition: transform 0.3s ease;
        }

        .logo:hover {
            transform: scale(1.05);
        }

        .logo img {
            height: 70px;
            width: auto;
            object-fit: contain;
            transition: transform 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .logo:hover img {
            transform: rotate(5deg) scale(1.1);
        }

        .nav-menu {
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 8px;
            z-index: 5;
        }

        .nav-menu a {
            color: #636366;
            text-decoration: none;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 14px;
            font-weight: 500;
            padding: 8px 16px;
            border-radius: 20px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }

        .nav-menu a:hover {
            color: #1C1C1E;
            transform: translateY(-2px);
        }

        .nav-btns {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .btn-login {
            color: #1C1C1E;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-weight: 600;
            font-size: 14px;
            padding: 10px 24px;
            border-radius: 50px;
            border: 1px solid #E5E5EA;
            background: #F2F2F7;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .btn-login:hover {
            background: #E5E5EA;
            border-color: var(--orange);
            color: #1C1C1E;
            transform: scale(1.02);
            box-shadow: 0 4px 12px rgba(255, 82, 0, 0.15);
        }

        .btn-join {
            background: var(--orange);
            color: #fff;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-weight: 750;
            font-size: 14px;
            padding: 10px 24px;
            border-radius: 50px;
            box-shadow: 0 4px 14px rgba(255, 82, 0, 0.15);
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
        }

        .btn-join:hover {
            background: var(--orange-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 69, 0, 0.35);
        }

        /* PROFILE DROPDOWN */
        .profile-dropdown-container {
            position: relative;
            display: inline-block;
        }

        .btn-profile-trigger {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #F2F2F7;
            border: 1px solid #E5E5EA;
            padding: 8px 16px;
            border-radius: 50px;
            cursor: pointer;
            font-family: 'Plus Jakarta Sans', sans-serif;
            transition: all 0.3s ease;
        }

        .btn-profile-trigger:hover {
            background: #E5E5EA;
            border-color: var(--orange);
            transform: scale(1.02);
        }

        .profile-icon-orange {
            font-size: 20px;
            color: var(--orange);
        }

        .profile-trigger-name {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 14px;
            font-weight: 600;
            color: #1C1C1E;
        }

        .chevron-icon {
            font-size: 11px;
            color: var(--orange);
            transition: transform 0.3s ease;
        }

        .profile-dropdown-container.active .chevron-icon {
            transform: rotate(180deg);
        }

        .profile-dropdown-menu {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            width: 240px;
            background: #18191E;
            border-radius: 14px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
            padding: 16px;
            display: none;
            z-index: 1010;
        }

        .profile-dropdown-menu.show {
            display: block;
        }

        .profile-dropdown-header {
            display: flex;
            flex-direction: column;
            padding: 4px 10px 8px;
        }

        .profile-dropdown-header .user-fullname {
            font-size: 15px;
            font-weight: 700;
            color: #FFFFFF;
        }

        .profile-dropdown-header .user-role {
            font-size: 10px;
            font-weight: 600;
            color: #64748B;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .profile-dropdown-divider {
            height: 1px;
            background: #2D3139;
            margin: 10px 0;
        }

        .profile-dropdown-list {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .profile-dropdown-list li a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 12px;
            color: #E2E8F0;
            font-size: 13px;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.2s ease;
        }

        .profile-dropdown-list li a:hover {
            background: rgba(255, 255, 255, 0.06);
            color: #FFFFFF;
        }

        .profile-dropdown-list li a i {
            font-size: 14px;
            width: 20px;
            text-align: center;
            color: #94A3B8;
        }

        .profile-dropdown-list li a:hover i {
            color: var(--orange);
        }

        .profile-dropdown-list li a.text-danger {
            color: #EF4444;
        }

        .profile-dropdown-list li a.text-danger i {
            color: #EF4444;
        }

        .profile-dropdown-list li a.text-danger:hover {
            background: rgba(239, 68, 68, 0.1);
        }

        /* ═══════════════════════════════════════════
           CONTENT AREA (FAQ)
           ═══════════════════════════════════════════ */
        .faq-section {
            padding: 80px 8%;
            background-color: var(--bg-light);
        }

        .faq-wrapper {
            max-width: 800px;
            margin: 0 auto;
            background: #FFFFFF;
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 50px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03);
        }

        .faq-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .faq-header h1 {
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 42px;
            font-weight: 900;
            color: var(--dark);
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: -0.5px;
        }

        .faq-header p {
            color: var(--text-muted);
            font-size: 14px;
        }

        /* STYLING AKORDEON FAQ */
        .faq-container {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .faq-item {
            border: 1px solid var(--border-color);
            border-radius: 12px;
            background: #FFFFFF;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .faq-item[open] {
            border-color: var(--orange);
            box-shadow: 0 4px 20px rgba(255, 69, 0, 0.06);
        }

        .faq-question {
            padding: 20px 24px;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 15px;
            font-weight: 700;
            color: var(--dark);
            cursor: pointer;
            list-style: none;
            /* Menyembunyikan segitiga bawaan */
            display: flex;
            justify-content: space-between;
            align-items: center;
            user-select: none;
        }

        /* Menghilangkan tanda panah bawaan di Safari */
        .faq-question::-webkit-details-marker {
            display: none;
        }

        /* Membuat tanda tambah (+) atau panah kustom */
        .faq-question::after {
            content: '\f078';
            /* Ikon panah bawah FontAwesome */
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            font-size: 12px;
            color: var(--text-muted);
            transition: transform 0.3s ease, color 0.3s ease;
        }

        .faq-item[open] .faq-question::after {
            transform: rotate(180deg);
            color: var(--orange);
        }

        .faq-answer {
            padding: 0 24px 20px;
            font-size: 14px;
            line-height: 1.6;
            color: var(--text-muted);
            border-top: 1px solid var(--border-color);
            padding-top: 16px;
        }

        /* ═══════════════════════════════════════════
           FOOTER (Sama dengan Index)
           ═══════════════════════════════════════════ */
        footer {
            background: #0F172A;
            color: #94A3B8;
            padding: 80px 8% 40px;
        }

        .footer-grid {
            display: grid;
            grid-template-columns: 1.5fr 1.5fr 1.5fr 1.5fr;
            gap: 40px;
            margin-bottom: 60px;
        }

        .footer-brand .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
        }

        .footer-brand .logo img {
            width: auto;
            height: 90px;
            object-fit: contain;
            filter: drop-shadow(0 3px 6px rgba(0, 0, 0, 0.3));
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
            color: #fff;
            transition: all 0.3s ease;
        }

        .social-links a:hover {
            background: var(--orange);
        }

        .footer-col h4 {
            color: #fff;
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
            transition: color 0.3s ease;
        }

        .footer-links li a:hover {
            color: #fff;
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

        .col-tautan {
            padding-left: 50px;
            /* Menggeser isi kolom Tautan ke kanan sebesar 50px */
        }

        /* Reset padding pada layar HP agar tampilan tidak rusak di perangkat mobile */
        @media (max-width: 992px) {
            .col-tautan {
                padding-left: 0;
            }
        }

        /* RESPONSIVE */
        @media (max-width: 992px) {
            .navbar {
                padding: 0 40px;
            }

            .footer-grid {
                grid-template-columns: 1fr 1fr;
            }

            .nav-menu {
                display: none;
            }

            .faq-wrapper {
                padding: 30px;
            }
        }

        @media (max-width: 576px) {
            .footer-grid {
                grid-template-columns: 1fr;
            }

            .faq-header h1 {
                font-size: 32px;
            }
        }
    </style>
</head>

<body>

    <!-- ═══════════════════════════════════════════
     NAVBAR
     ═══════════════════════════════════════════ -->
    <nav class="navbar">
        <a href="index.php" class="logo"><img src="asset/image/logo2.png" alt="HoopBall"></a>
        <div class="nav-menu">
            <a href="customer/booking_customer.php">Booking</a>
            <a href="customer/pembatalan_customer.php">Pembatalan</a>
            <a href="customer/langganan_customer.php">Member</a>
            <a href="customer/pembelian_alat.php">Pembelian</a>
        </div>
        <div class="nav-btns">
            <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true): ?>
                <div class="profile-dropdown-container">
                    <button class="btn-profile-trigger" id="profileTrigger">
                        <i class="fa-solid fa-circle-user profile-icon-orange"></i>
                        <span
                            class="profile-trigger-name"><?= htmlspecialchars($_SESSION['Nama_Customer'] ?? 'User') ?></span>
                        <i class="fa-solid fa-chevron-down chevron-icon"></i>
                    </button>

                    <div class="profile-dropdown-menu" id="profileDropdownMenu">
                        <div class="profile-dropdown-header">
                            <span class="user-fullname"><?= htmlspecialchars($_SESSION['Nama_Customer'] ?? 'User') ?></span>
                            <span class="user-role">CUSTOMER</span>
                        </div>
                        <div class="profile-dropdown-divider"></div>
                        <ul class="profile-dropdown-list">
                            <li><a href="profile/profile_customer.php"><i class="fa-regular fa-user"></i> Profil Saya</a>
                            </li>
                        </ul>
                        <div class="profile-dropdown-divider"></div>
                        <ul class="profile-dropdown-list">
                            <li><a href="login/logout.php"><i class="fa-solid fa-arrow-right-from-bracket"></i> Keluar</a>
                            </li>
                        </ul>
                    </div>
                </div>
            <?php else: ?>
                <a href="login/login.php" class="btn-login">Masuk</a>
                <a href="login/register.php" class="btn-join">Daftar Sekarang</a>
            <?php endif; ?>
        </div>
    </nav>

    <!-- ═══════════════════════════════════════════
     CONTENT
     ═══════════════════════════════════════════ -->
    <section class="faq-section">
        <div class="faq-wrapper">
            <div class="faq-header">
                <h1>Pertanyaan Populer (FAQ)</h1>
                <p>Temukan jawaban cepat atas pertanyaan yang paling sering diajukan mengenai layanan kami.</p>
            </div>

            <div class="faq-container">
                <!-- FAQ 1 -->
                <details class="faq-item">
                    <summary class="faq-question">Bagaimana cara memesan lapangan?</summary>
                    <div class="faq-answer">
                        <p>Caranya sangat mudah. Silakan daftar/masuk ke akun Anda terlebih dahulu, lalu pilih menu
                            <strong>Booking</strong> pada navigasi atas. Pilih jenis lapangan, tentukan tanggal serta
                            jam bermain yang tersedia, lalu selesaikan pembayaran.</p>
                    </div>
                </details>

                <!-- FAQ 2 -->
                <details class="faq-item">
                    <summary class="faq-question">Apakah pembayaran bisa langsung di tempat (COD)?</summary>
                    <div class="faq-answer">
                        <p>Tidak bisa. Untuk menjamin kepastian jadwal pemakaian lapangan bagi semua pengguna, seluruh
                            pemesanan wajib diselesaikan secara online sebelum Anda datang ke lokasi lapangan.</p>
                    </div>
                </details>

                <!-- FAQ 3 -->
                <details class="faq-item">
                    <summary class="faq-question">Apakah saya bisa menjadwal ulang (reschedule)?</summary>
                    <div class="faq-answer">
                        <p>Bisa. Permohonan jadwal ulang (reschedule) dapat dilakukan secara mandiri melalui profil akun
                            Anda maksimal 24 jam sebelum jadwal bermain awal dimulai, bergantung ketersediaan slot
                            kosong lapangan.</p>
                    </div>
                </details>

                <!-- FAQ 4 -->
                <details class="faq-item">
                    <summary class="faq-question">Apakah sewa lapangan sudah termasuk peminjaman bola?</summary>
                    <div class="faq-answer">
                        <p>Ya. Setiap pemesanan 1 lapangan basket sudah mendapatkan fasilitas peminjaman 1 bola basket
                            standar secara gratis selama durasi bermain Anda.</p>
                    </div>
                </details>

                <!-- FAQ 5 -->
                <details class="faq-item">
                    <summary class="faq-question">Jenis sepatu apa yang diperbolehkan di lapangan?</summary>
                    <div class="faq-answer">
                        <p>Demi menjaga permukaan dan keawetan lapangan, pengguna wajib mengenakan sepatu olahraga
                            khusus (sepatu basket atau sepatu indoor non-marking) yang bersih dan tidak merusak
                            permukaan kayu/vinyl.</p>
                    </div>
                </details>
            </div>
        </div>
    </section>

    <!-- ═══════════════════════════════════════════
     FOOTER
     ═══════════════════════════════════════════ -->
    <footer id="tentang-kami">
        <div class="footer-grid">
            <div class="footer-brand">
                <a href="#" class="logo"><img src="asset/image/logo2.png" alt="HoopBall"></a>
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
                    <div class="contact-item"><i class="fa-solid fa-phone"></i><span>0812-3456-7890</span></div>
                    <div class="contact-item"><i class="fa-solid fa-envelope"></i><span>info@hoopball.id</span></div>
                    <div class="contact-item"><i class="fa-solid fa-location-dot"></i><span>
                            Politeknik Astra, Delta Silicon II, Cibatu, Cikarang Selatan, Bekasi, Jawa Barat
                            17530</span></div>
                </div>
            </div>
            <div class="footer-col">
                <h4>Tautan</h4>
                <ul class="footer-links">
                    <li>
                        <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true): ?>
                            <a href="customer/booking_customer.php">Lapangan</a>
                        <?php else: ?>
                            <a href="login/login.php">Lapangan</a>
                        <?php endif; ?>
                    </li>
                    <li>
                        <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true): ?>
                            <a href="customer/booking_customer.php">Jadwal</a>
                        <?php else: ?>
                            <a href="login/login.php">Jadwal</a>
                        <?php endif; ?>
                    </li>
                    <li>
                        <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true): ?>
                            <a href="customer/pembelian_alat.php">Alat Basket</a>
                        <?php else: ?>
                            <a href="login/login.php">Alat Basket</a>
                        <?php endif; ?>
                    </li>
                    <li>
                        <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true): ?>
                            <a href="customer/langganan_customer.php">Member</a>
                        <?php else: ?>
                            <a href="login/login.php">Member</a>
                        <?php endif; ?>
                    </li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Informasi</h4>
                <ul class="footer-links">
                    <li><a href="syarat_ketentuan.php">Syarat & Ketentuan</a></li>
                    <li><a href="kebijakan_privasi.php">Kebijakan Privasi</a></li>
                    <li><a href="faq.php">FAQ</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2026 HoopBall. All rights reserved.</p>
        </div>
    </footer>

    <!-- ═══════════════════════════════════════════
     SCRIPTS
     ═══════════════════════════════════════════ -->
    <script>
        // Dropdown Profil Toggle
        document.addEventListener('DOMContentLoaded', () => {
            const profileTrigger = document.getElementById('profileTrigger');
            const profileDropdownMenu = document.getElementById('profileDropdownMenu');
            const dropdownContainer = document.querySelector('.profile-dropdown-container');

            if (profileTrigger && profileDropdownMenu) {
                profileTrigger.addEventListener('click', (e) => {
                    e.stopPropagation();
                    profileDropdownMenu.classList.toggle('show');
                    dropdownContainer.classList.toggle('active');
                });

                document.addEventListener('click', (e) => {
                    if (dropdownContainer && !dropdownContainer.contains(e.target)) {
                        profileDropdownMenu.classList.remove('show');
                        dropdownContainer.classList.remove('active');
                    }
                });
            }
        });
    </script>
</body>

</html>