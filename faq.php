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
    <link rel="stylesheet" href="asset/css/navbar_footer.css">
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
        /* RESPONSIVE */
        @media (max-width: 992px) {

            .faq-wrapper {
                padding: 30px;
            }
        }

        @media (max-width: 576px) {
            .faq-header h1 {
                font-size: 32px;
            }
        }
    </style>
</head>

<body>

   <?php include 'includes/navbar.php'; ?>

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

   <?php include 'includes/footer.php'; ?>

</body>

</html>