<?php
session_start();
// Tetap menjaga status intro agar konsisten dengan alur halaman utama
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
    <title>Syarat dan Ketentuan - HoopBall</title>
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
           CONTENT AREA (Syarat & Ketentuan)
           ═══════════════════════════════════════════ */
        .terms-section {
            padding: 80px 8%;
            background-color: var(--bg-light);
        }

        .terms-wrapper {
            max-width: 900px;
            margin: 0 auto;
            background: #FFFFFF;
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 50px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03);
        }

        .terms-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .terms-header h1 {
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 42px;
            font-weight: 900;
            color: var(--dark);
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: -0.5px;
        }

        .terms-header p {
            color: var(--text-muted);
            font-size: 14px;
        }

        .terms-content {
            font-size: 15px;
            color: var(--text-dark);
            line-height: 1.8;
        }

        .terms-group {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }

        .terms-group:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .terms-group h3 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 18px;
            font-weight: 700;
            color: var(--orange);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .terms-group ul,
        .terms-group ol {
            padding-left: 20px;
            margin-bottom: 15px;
        }

        .terms-group li {
            margin-bottom: 8px;
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
    <section class="terms-section">
        <div class="terms-wrapper">
            <div class="terms-header">
                <h1>Syarat dan Ketentuan</h1>
                <p>Harap baca syarat dan ketentuan ini dengan saksama sebelum memesan lapangan di HoopBall.</p>
            </div>

            <div class="terms-content">
                <div class="terms-group">
                    <h3><i class="fa-solid fa-gavel"></i> 1. Ketentuan Umum</h3>
                    <p>Dengan mengakses dan melakukan pemesanan di HoopBall, Anda setuju untuk terikat oleh aturan
                        operasional serta syarat yang berlaku di bawah ini:</p>
                    <ul>
                        <li>Pengguna wajib memberikan data informasi diri yang valid dan benar saat melakukan registrasi
                            maupun pemesanan.</li>
                        <li>Sistem pemesanan hanya diperuntukkan bagi penggunaan bermain olahraga basket secara sportif
                            dan positif.</li>
                        <li>HoopBall berhak menonaktifkan akun pengguna yang melanggar aturan penggunaan platform.</li>
                    </ul>
                </div>

                <div class="terms-group">
                    <h3><i class="fa-solid fa-calendar-check"></i> 2. Prosedur Pemesanan & Pembayaran</h3>
                    <p>Seluruh proses booking dilakukan secara online guna kenyamanan dan kepastian jadwal:</p>
                    <ul>
                        <li>Pemesanan dianggap sah/valid apabila pengguna telah menyelesaikan pembayaran secara penuh
                            sesuai instruksi metode pembayaran yang dipilih.</li>
                        <li>Batas waktu penyelesaian pembayaran adalah 15 menit setelah pemesanan dibuat. Jika melebihi
                            batas waktu, pemesanan otomatis dibatalkan sistem.</li>
                        <li>Harga sewa yang tertera dapat berubah sewaktu-waktu tergantung kebijakan pengelola atau
                            promo yang sedang berlangsung.</li>
                    </ul>
                </div>

                <div class="terms-group">
                    <h3><i class="fa-solid fa-rectangle-xmark"></i> 3. Pembatalan & Perubahan Jadwal</h3>
                    <p>Aturan pembatalan dibuat untuk memastikan ketersediaan lapangan yang adil bagi seluruh pengguna:
                    </p>
                    <ul>
                        <li>Pembatalan sepihak dari pengguna yang diajukan kurang dari 24 jam sebelum jadwal bermain
                            tidak mendapatkan pengembalian dana (refund).</li>
                        <li>HoopBall tidak menyediakan fitur perubahan jadwal (reschedule) secara langsung. Apabila
                            customer ingin mengganti jadwal, customer harus mengajukan pembatalan booking terlebih
                            dahulu paling lambat 1x24 jam sebelum jadwal bermain.</li>
                        <li>Jika pembatalan disetujui oleh karyawan, customer akan menerima refund sebesar 50% dari
                            total pembayaran. Sisa 50% dianggap sebagai biaya pembatalan, dan jadwal yang dibatalkan
                            akan kembali tersedia untuk dipesan oleh customer lain.</li>
                    </ul>
                </div>

                <div class="terms-group">
                    <h3><i class="fa-solid fa-shield"></i> 4. Aturan di Dalam Lapangan</h3>
                    <p>Setiap pemain diwajibkan menjaga ketertiban umum dan kebersihan area fasilitas bermain:</p>
                    <ul>
                        <li>Pengguna wajib menggunakan sepatu khusus olahraga (sepatu basket / olahraga indoor
                            non-marking) demi menjaga kualitas permukaan lapangan.</li>
                        <li>Dilarang keras membawa senjata tajam, minuman beralkohol, obat-obatan terlarang, serta
                            dilarang merokok di seluruh area indoor lapangan.</li>
                        <li>Kerusakan fasilitas yang disebabkan oleh kelalaian atau kesengajaan pengguna menjadi
                            tanggung jawab penuh penyewa bersangkutan.</li>
                    </ul>
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