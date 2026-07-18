<?php
session_start();
// Menjaga status intro agar tetap konsisten dengan alur halaman utama
if (!isset($_SESSION['intro_done'])) {
    header("Location: intro.php");
    exit();
}

// ========================================================
// PANGGIL AUTO LOGOUT - HANYA UNTUK USER YANG SUDAH LOGIN
// ========================================================
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    // Karena index.php di luar dan file di dalam folder 'login'
    require_once 'login/auto_logout.php';
}

?>
<!DOCTYPE html>
<html lang="id">

<head>
   <?php include 'includes/favicon.php'; ?>
    <title>Kebijakan Privasi - HoopBall</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Barlow+Condensed:wght@700;800;900&family=Barlow:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="asset/css/navbar_footer.css">
    <link rel="stylesheet" href="asset/css/responsive_informasi.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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
           CONTENT AREA (Kebijakan Privasi)
           ═══════════════════════════════════════════ */
        .privacy-section {
            padding: 80px 8%;
            background-color: var(--bg-light);
        }

        .privacy-wrapper {
            max-width: 900px;
            margin: 0 auto;
            background: #FFFFFF;
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 50px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03);
        }

        .privacy-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .privacy-header h1 {
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 42px;
            font-weight: 900;
            color: var(--dark);
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: -0.5px;
        }

        .privacy-header p {
            color: var(--text-muted);
            font-size: 14px;
        }

        .privacy-content {
            font-size: 15px;
            color: var(--text-dark);
            line-height: 1.8;
        }

        .privacy-group {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }

        .privacy-group:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .privacy-group h3 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 18px;
            font-weight: 700;
            color: var(--orange);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .privacy-group p {
            margin-bottom: 15px;
        }

        .privacy-group ul {
            padding-left: 20px;
            margin-bottom: 15px;
        }

        .privacy-group li {
            margin-bottom: 8px;
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

            .privacy-wrapper {
                padding: 30px;
            }
        }

        @media (max-width: 576px) {
            .footer-grid {
                grid-template-columns: 1fr;
            }

            .privacy-header h1 {
                font-size: 32px;
            }
        }

         /* ============================================
   MATIKAN SEMUA ANIMASI SWEETALERT2 
   ============================================ */
        .swal2-popup {
            animation: none !important;
            transition: none !important;
        }

        .swal2-icon {
            animation: none !important;
        }

        .swal2-icon.swal2-success .swal2-success-ring,
        .swal2-icon.swal2-success [class^="swal2-success-line"],
        .swal2-icon.swal2-error [class^="swal2-x-mark-line"],
        .swal2-icon.swal2-warning {
            animation: none !important;
        }

        /* cegah body/html digeser oleh kompensasi scrollbar SweetAlert */
        html.swal2-shown,
        body.swal2-shown,
        body.swal2-height-auto {
            padding-right: 0 !important;
   }
    </style>
</head>

<body>

    <?php include 'includes/navbar.php'; ?>

    <!-- ═══════════════════════════════════════════
     CONTENT
     ═══════════════════════════════════════════ -->
    <section class="privacy-section">
        <div class="privacy-wrapper">
            <div class="privacy-header">
                <h1>Kebijakan Privasi</h1>
                <p>Bagaimana HoopBall mengumpulkan, melindungi, dan memperlakukan informasi pribadi Anda.</p>
            </div>

            <div class="privacy-content">
                <div class="privacy-group">
                    <h3><i class="fa-solid fa-user-shield"></i> 1. Pengumpulan Informasi</h3>
                    <p>Kami mengumpulkan beberapa informasi pribadi Anda ketika Anda mendaftar akun atau melakukan
                        reservasi lapangan di HoopBall:</p>
                    <ul>
                        <li><strong>Informasi Identitas:</strong> Nama lengkap, alamat email, serta nomor telepon aktif.
                        </li>
                        <li><strong>Riwayat Pemesanan:</strong> Detail pemesanan lapangan, langganan member, pembelian
                            alat basket, serta nominal transaksi pembayaran Anda.</li>
                    </ul>
                </div>

                <div class="privacy-group">
                    <h3><i class="fa-solid fa-key"></i> 2. Penggunaan Informasi Pribadi</h3>
                    <p>Informasi yang kami peroleh dari Anda digunakan semata-mata untuk meningkatkan kualitas layanan
                        HoopBall, meliputi:</p>
                    <ul>
                        <li>Memproses dan melacak status pemesanan lapangan serta transaksi pembayaran Anda secara
                            akurat.</li>
                        <li>Menyediakan layanan bantuan yang cepat ketika Anda menghubungi tim layanan pelanggan
                            HoopBall.</li>
                    </ul>
                </div>

                <div class="privacy-group">
                    <h3><i class="fa-solid fa-lock"></i> 3. Perlindungan Keamanan Data</h3>
                    <p>Kami menerapkan prosedur keamanan standar untuk melindungi informasi pribadi Anda dari akses yang
                        tidak sah, penyalahgunaan, kehilangan, atau pengubahan data:</p>
                    <ul>
                        <li>Database sistem disimpan pada server yang aman dan hanya dapat diakses oleh administrator
                            sistem yang memiliki otorisasi khusus.</li>
                        <li>Kami tidak pernah menyimpan informasi kartu kredit atau detail perbankan Anda dalam database
                            sistem kami; seluruh transaksi pembayaran diproses melalui gerbang pembayaran (payment
                            gateway) pihak ketiga yang terenkripsi aman.</li>
                    </ul>
                </div>

                <div class="privacy-group">
                    <h3><i class="fa-solid fa-handshake-slash"></i> 4. Pengungkapan Kepada Pihak Ketiga</h3>
                    <p>Kami sangat menghargai privasi Anda. HoopBall berkomitmen tidak akan menjual, menyewakan,
                        membagikan, atau memperdagangkan informasi identitas pribadi Anda kepada pihak eksternal mana
                        pun di luar sistem aplikasi kami.</p>
                </div>

                <div class="privacy-group">
                    <h3><i class="fa-solid fa-circle-check"></i> 5. Persetujuan Pengguna</h3>
                    <p>Dengan tetap mengakses, mendaftar, dan memesan lapangan melalui platform HoopBall, Anda
                        menyatakan telah membaca, memahami, serta memberikan persetujuan penuh terhadap ketentuan
                        Kebijakan Privasi yang tercantum di halaman ini.</p>
                </div>
            </div>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>

    <script>
            window.Swal = Swal.mixin({
            scrollbarPadding: false
        });
    </script>
    <?php if (function_exists('tampilkan_sensor_auto_logout')) tampilkan_sensor_auto_logout(); ?>
</body>

</html>