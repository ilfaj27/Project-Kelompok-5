<?php
// Pastikan prefix folder terdefinisi dengan aman
$prefix = isset($path_prefix) ? $path_prefix : '';
?>
<footer id="tentang-kami">
    <div class="footer-grid">
        <div class="footer-brand">
            <a href="<?= $prefix ?>index.php" class="logo">
                <img src="<?= $prefix ?>asset/image/logo2.png" alt="HoopBall">
            </a>
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
                <div class="contact-item">
                    <i class="fa-solid fa-location-dot"></i>
                    <span>Politeknik Astra, Delta Silicon II, Cibatu, Cikarang Selatan, Bekasi, Jawa Barat 17530</span>
                </div>
            </div>
        </div>
        <div class="footer-col col-tautan">
            <h4>Tautan</h4>
            <ul class="footer-links">
                <li>
                    <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true): ?>
                        <a href="<?= $prefix ?>customer/booking_customer.php">Lapangan</a>
                    <?php else: ?>
                        <a href="<?= $prefix ?>login/login.php">Lapangan</a>
                    <?php endif; ?>
                </li>
                <li>
                    <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true): ?>
                        <a href="<?= $prefix ?>customer/booking_customer.php">Jadwal</a>
                    <?php else: ?>
                        <a href="<?= $prefix ?>login/login.php">Jadwal</a>
                    <?php endif; ?>
                </li>
                <li>
                    <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true): ?>
                        <a href="<?= $prefix ?>customer/pembelian_alat.php">Alat Basket</a>
                    <?php else: ?>
                        <a href="<?= $prefix ?>login/login.php">Alat Basket</a>
                    <?php endif; ?>
                </li>
                <li>
                    <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true): ?>
                        <a href="<?= $prefix ?>customer/langganan_customer.php">Member</a>
                    <?php else: ?>
                        <a href="<?= $prefix ?>login/login.php">Member</a>
                    <?php endif; ?>
                </li>
            </ul>
        </div>
        <div class="footer-col">
            <h4>Informasi</h4>
            <ul class="footer-links">
                <li><a href="<?= $prefix ?>syarat_ketentuan.php">Syarat & Ketentuan</a></li>
                <li><a href="<?= $prefix ?>kebijakan_privasi.php">Kebijakan Privasi</a></li>
                <li><a href="<?= $prefix ?>faq.php">FAQ</a></li>
            </ul>
        </div>
    </div>
    <div class="footer-bottom">
        <p>&copy; 2026 HoopBall. All rights reserved.</p>
    </div>
</footer>