<?php
$nama = $nama ?? 'Pengguna';
$role = $role ?? '';
$sidebar_photo = $sidebar_photo ?? '';
$sidebar_folder = $sidebar_folder ?? '';
$current_page = $current_page ?? '';

$hb_profile_url = ($sidebar_folder === 'profile') ? 'profile.php' : '../profile/profile.php';
$hb_home_url = ($sidebar_folder === 'dashboard') ? 'view_admin.php' : '../dashboard/view_admin.php';
?>
<aside class="sidebar">

    <!-- ======================= LOGO / BRAND ======================= -->
    <a href="<?= $hb_home_url ?>" class="sb-brand">
        <div class="sb-logo">
            <svg viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <!-- bola -->
                <circle cx="24" cy="24" r="20" class="hb-ball" />
                <!-- garis bola basket -->
                <g class="hb-seam">
                    <path d="M4 24h40" />
                    <path d="M24 4v40" />
                    <path d="M9 9c8 8 8 22 0 30" />
                    <path d="M39 9c-8 8-8 22 0 30" />
                </g>
            </svg>
            <span class="sb-logo-glow" aria-hidden="true"></span>
        </div>

        <div class="sb-brand-text">
            <div class="sb-brand-name">HOOP<span>BALL</span></div>
            <div class="sb-brand-sub">
                <span class="sb-brand-dot"></span>
                Sistem Manajemen
            </div>
        </div>
    </a>
    <!-- ===================== /LOGO / BRAND ======================== -->

    <!-- ===== MENU UTAMA ===== -->
    <div class="sb-section-label">Menu Utama</div>
    <nav>
        <a href="<?= $hb_home_url ?>" class="sb-link <?= ($current_page === 'dashboard') ? 'active' : '' ?>">
            <div class="sb-icon-wrap"><i class="fa-solid fa-house"></i></div>
            Dashboard
        </a>
    </nav>

    <!-- ===== GRUP: MASTER DATA ===== -->
    <div class="sb-group" data-group="master">
        <button type="button" class="sb-group-toggle" aria-expanded="false">
            <div class="sb-icon-wrap"><i class="fa-solid fa-database"></i></div>
            <span class="sb-group-title">Master Data</span>
            <span class="sb-group-count">7</span>
            <i class="fa-solid fa-chevron-down sb-group-chevron"></i>
        </button>

        <nav class="sb-group-body">
            <div class="sb-group-inner">
                <a href="<?= ($sidebar_folder === 'master') ? 'tipe_member.php' : '../master/tipe_member.php' ?>"
                    class="sb-link <?= ($current_page === 'tipe_member') ? 'active' : '' ?>">
                    <div class="sb-icon-wrap"><i class="fa-solid fa-id-card"></i></div>
                    Kelola Tipe Member
                </a>
                <a href="<?= ($sidebar_folder === 'master') ? 'fasilitas_lapangan.php' : '../master/fasilitas_lapangan.php' ?>"
                    class="sb-link <?= ($current_page === 'fasilitas') ? 'active' : '' ?>">
                    <div class="sb-icon-wrap"><i class="fa-solid fa-list-check"></i></div>
                    Kelola Fasilitas
                </a>
                <a href="<?= ($sidebar_folder === 'master') ? 'alat.php' : '../master/alat.php' ?>"
                    class="sb-link <?= ($current_page === 'alat') ? 'active' : '' ?>">
                    <div class="sb-icon-wrap"><i class="fa-solid fa-toolbox"></i></div>
                    Kelola Alat
                </a>
                <a href="<?= ($sidebar_folder === 'master') ? 'promo.php' : '../master/promo.php' ?>"
                    class="sb-link <?= ($current_page === 'promo') ? 'active' : '' ?>">
                    <div class="sb-icon-wrap"><i class="fa-solid fa-tags"></i></div>
                    Kelola Promo
                </a>
                <a href="<?= ($sidebar_folder === 'master') ? 'customer.php' : '../master/customer.php' ?>"
                    class="sb-link <?= ($current_page === 'customer') ? 'active' : '' ?>">
                    <div class="sb-icon-wrap"><i class="fa-solid fa-users"></i></div>
                    Kelola Customer
                </a>
                <a href="<?= ($sidebar_folder === 'master') ? 'lapangan.php' : '../master/lapangan.php' ?>"
                    class="sb-link <?= ($current_page === 'lapangan') ? 'active' : '' ?>">
                    <div class="sb-icon-wrap"><i class="fa-solid fa-layer-group"></i></div>
                    Kelola Lapangan
                </a>
                <a href="<?= ($sidebar_folder === 'master') ? 'jadwal.php' : '../master/jadwal.php' ?>"
                    class="sb-link <?= ($current_page === 'jadwal') ? 'active' : '' ?>">
                    <div class="sb-icon-wrap"><i class="fa-solid fa-calendar-days"></i></div>
                    Kelola Jadwal
                </a>
            </div>
        </nav>
    </div>

    <!-- ===== GRUP: TRANSAKSI ===== -->
    <div class="sb-group" data-group="transaksi">
        <button type="button" class="sb-group-toggle" aria-expanded="false">
            <div class="sb-icon-wrap"><i class="fa-solid fa-receipt"></i></div>
            <span class="sb-group-title">Transaksi</span>
            <span class="sb-group-count">4</span>
            <i class="fa-solid fa-chevron-down sb-group-chevron"></i>
        </button>

        <nav class="sb-group-body">
            <div class="sb-group-inner">
                <a href="<?= ($sidebar_folder === 'transaksi') ? 'booking.php' : '../transaksi/booking.php' ?>"
                    class="sb-link <?= ($current_page === 'booking') ? 'active' : '' ?>">
                    <div class="sb-icon-wrap"><i class="fa-solid fa-calendar-check"></i></div>
                    Kelola Booking
                </a>
                <a href="<?= ($sidebar_folder === 'transaksi') ? 'langganan.php' : '../transaksi/langganan.php' ?>"
                    class="sb-link <?= ($current_page === 'langganan') ? 'active' : '' ?>">
                    <div class="sb-icon-wrap"><i class="fa-solid fa-crown"></i></div>
                    Kelola Langganan
                </a>
                <a href="<?= ($sidebar_folder === 'transaksi') ? 'pembelian.php' : '../transaksi/pembelian.php' ?>"
                    class="sb-link <?= ($current_page === 'pembelian_alat') ? 'active' : '' ?>">
                    <div class="sb-icon-wrap"><i class="fa-solid fa-cart-shopping"></i></div>
                    Kelola Pembelian Alat
                </a>
                <a href="<?= ($sidebar_folder === 'transaksi') ? 'pembatalan.php' : '../transaksi/pembatalan.php' ?>"
                    class="sb-link <?= ($current_page === 'pembatalan') ? 'active' : '' ?>">
                    <div class="sb-icon-wrap"><i class="fa-solid fa-ban"></i></div>
                    Kelola Pembatalan
                </a>
            </div>
        </nav>
    </div>

    <!-- ===== AKUN ===== -->
    <div class="sb-section-label">Akun</div>
    <nav>
        <a href="<?= $hb_profile_url ?>" class="sb-link <?= ($current_page === 'profile') ? 'active' : '' ?>">
            <div class="sb-icon-wrap"><i class="fa-solid fa-id-badge"></i></div>
            Profil Saya
        </a>
    </nav>

    <div class="sb-bottom">
        <div class="sb-user">
            <div class="sb-avatar">
                <?php if (!empty($sidebar_photo)): ?>
                    <img src="<?= $sidebar_photo ?>" alt="Profile">
                <?php else: ?>
                    <i class="fa-solid fa-user"></i>
                <?php endif; ?>
            </div>
            <div>
                <div class="sb-user-name"><?= strtoupper(htmlspecialchars($nama)) ?></div>
                <div class="sb-user-role"><?= strtoupper(htmlspecialchars($role)) ?></div>
            </div>
            <a href="../login/logout.php" class="sb-logout" title="Keluar">
                <i class="fa-solid fa-right-from-bracket"></i>
            </a>
        </div>
    </div>
</aside>

<?php if (!defined('HB_SIDEBAR_ASSETS')):
    define('HB_SIDEBAR_ASSETS', true); ?>
    <style>
        /* ============================================================
           LOGO / BRAND — HoopBall
           ============================================================ */
        .sb-brand {
            display: flex;
            align-items: center;
            gap: 13px;
            padding: 4px 6px 16px;
            margin-bottom: 14px;
            text-decoration: none;
            position: relative;
            border-bottom: 1px solid rgba(255, 255, 255, .06);
            transition: transform .35s cubic-bezier(.16, 1, .3, 1);
        }

        .sb-brand:hover {
            transform: translateY(-1px);
        }

        /* ---- Ikon bola basket ---- */
        .sb-logo {
            position: relative;
            flex-shrink: 0;
            width: 44px;
            height: 44px;
            border-radius: 13px;
            background: linear-gradient(140deg, #FF6A2B 0%, var(--orange) 45%, #D93800 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 6px 18px rgba(255, 69, 0, .42), inset 0 1px 0 rgba(255, 255, 255, .28);
            overflow: hidden;
            transition: transform .45s cubic-bezier(.34, 1.56, .64, 1), box-shadow .35s ease;
        }

        .sb-brand:hover .sb-logo {
            transform: rotate(-8deg) scale(1.06);
            box-shadow: 0 10px 26px rgba(255, 69, 0, .55), inset 0 1px 0 rgba(255, 255, 255, .35);
        }

        .sb-logo svg {
            width: 25px;
            height: 25px;
            position: relative;
            z-index: 2;
            overflow: visible;
        }

        .hb-ball {
            fill: none;
            stroke: #fff;
            stroke-width: 3.2;
        }

        .hb-seam {
            fill: none;
            stroke: #fff;
            stroke-width: 2.6;
            stroke-linecap: round;
            opacity: .95;
        }

        /* Bola "berputar" pelan saat hover */
        .sb-brand:hover .sb-logo svg {
            animation: hbSpin 1.6s cubic-bezier(.16, 1, .3, 1);
        }

        @keyframes hbSpin {
            from {
                transform: rotate(0deg);
            }

            to {
                transform: rotate(360deg);
            }
        }

        /* Kilau melintas di kotak logo */
        .sb-logo-glow {
            position: absolute;
            inset: 0;
            z-index: 3;
            pointer-events: none;
            background: linear-gradient(115deg, transparent 38%, rgba(255, 255, 255, .42) 50%, transparent 62%);
            transform: translateX(-130%);
        }

        .sb-brand:hover .sb-logo-glow {
            animation: hbShine .85s ease-out;
        }

        @keyframes hbShine {
            to {
                transform: translateX(130%);
            }
        }

        /* ---- Teks brand ---- */
        .sb-brand-text {
            line-height: 1;
        }

        .sb-brand-name {
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 23px;
            font-weight: 900;
            color: #fff;
            letter-spacing: 1.4px;
            line-height: 1;
        }

        .sb-brand-name span {
            color: var(--orange);
            transition: color .3s ease, text-shadow .3s ease;
        }

        .sb-brand:hover .sb-brand-name span {
            text-shadow: 0 0 14px rgba(255, 69, 0, .6);
        }

        .sb-brand-sub {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: 5px;
            font-size: 8.5px;
            font-weight: 800;
            letter-spacing: 1.3px;
            text-transform: uppercase;
            color: #4B5563;
            transition: color .3s ease;
        }

        .sb-brand:hover .sb-brand-sub {
            color: #9CA3AF;
        }

        .sb-brand-dot {
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background: var(--green);
            flex-shrink: 0;
            box-shadow: 0 0 0 0 rgba(16, 185, 129, .6);
            animation: hbDot 2.4s ease-out infinite;
        }

        @keyframes hbDot {
            0% {
                box-shadow: 0 0 0 0 rgba(16, 185, 129, .55);
            }

            60% {
                box-shadow: 0 0 0 5px rgba(16, 185, 129, 0);
            }

            100% {
                box-shadow: 0 0 0 0 rgba(16, 185, 129, 0);
            }
        }

        /* Matikan garis bawah lama dari global.css */
        .sb-brand::after {
            display: none !important;
        }


        /* ============================================================
           SIDEBAR GROUP (DROPDOWN) — Master Data & Transaksi
           ============================================================ */
        .sb-group {
            margin: 18px 0 4px;
        }

        .sb-group-toggle {
            width: 100%;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            border-radius: 10px;
            background: transparent;
            border: none;
            cursor: pointer;
            color: #6B7280;
            font-family: 'Barlow', sans-serif;
            font-size: 13px;
            font-weight: 700;
            text-align: left;
            position: relative;
            overflow: hidden;
            transition: color .3s ease, background .3s ease;
        }

        .sb-group-toggle::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            width: 0;
            height: 100%;
            border-radius: 10px;
            z-index: 0;
            background: linear-gradient(90deg, rgba(255, 255, 255, .07), rgba(255, 255, 255, .02));
            transition: width .35s cubic-bezier(.16, 1, .3, 1);
        }

        .sb-group-toggle:hover::before {
            width: 100%;
        }

        .sb-group-toggle:hover {
            color: #E5E7EB;
        }

        .sb-group-toggle>* {
            position: relative;
            z-index: 1;
        }

        .sb-group-toggle .sb-icon-wrap {
            width: 32px;
            height: 32px;
            flex-shrink: 0;
            border-radius: 8px;
            background: rgba(255, 255, 255, .04);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            transition: all .35s cubic-bezier(.34, 1.56, .64, 1);
        }

        .sb-group-toggle:hover .sb-icon-wrap {
            background: rgba(255, 255, 255, .12);
            transform: scale(1.1);
        }

        .sb-group-title {
            flex: 1;
            letter-spacing: .2px;
        }

        .sb-group-count {
            font-size: 10px;
            font-weight: 800;
            color: #6B7280;
            background: rgba(255, 255, 255, .06);
            border-radius: 20px;
            padding: 2px 8px;
            min-width: 22px;
            text-align: center;
            transition: background .3s ease, color .3s ease;
        }

        .sb-group-chevron {
            font-size: 10px;
            color: #4B5563;
            transition: transform .35s cubic-bezier(.16, 1, .3, 1), color .3s ease;
        }

        .sb-group.open>.sb-group-toggle {
            color: #fff;
            background: rgba(255, 69, 0, .08);
        }

        .sb-group.open>.sb-group-toggle .sb-icon-wrap {
            background: var(--orange);
            color: #fff;
            box-shadow: 0 4px 12px rgba(255, 69, 0, .3);
        }

        .sb-group.open>.sb-group-toggle .sb-group-chevron {
            transform: rotate(180deg);
            color: var(--orange);
        }

        .sb-group.open>.sb-group-toggle .sb-group-count {
            background: rgba(255, 69, 0, .16);
            color: var(--orange);
        }

        .sb-group:not(.open).has-active>.sb-group-toggle {
            color: #D1D5DB;
        }

        .sb-group:not(.open).has-active>.sb-group-toggle .sb-icon-wrap {
            color: var(--orange);
        }

        .sb-group:not(.open).has-active>.sb-group-toggle::after {
            content: '';
            position: absolute;
            right: 32px;
            top: 50%;
            transform: translateY(-50%);
            z-index: 1;
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--orange);
            box-shadow: 0 0 8px rgba(255, 69, 0, .7);
        }

        .sb-group-body {
            max-height: 0;
            overflow: hidden;
            transition: max-height .4s cubic-bezier(.16, 1, .3, 1);
        }

        .sb-group-inner {
            position: relative;
            padding: 6px 0 2px 14px;
            margin-left: 15px;
            border-left: 1.5px solid rgba(255, 255, 255, .07);
        }

        .sb-group-inner .sb-link {
            opacity: 1 !important;
            animation: none !important;
            margin-bottom: 2px;
            padding: 9px 10px;
            font-size: 12.5px;
        }

        .sb-group-inner .sb-link .sb-icon-wrap {
            width: 28px;
            height: 28px;
            font-size: 11.5px;
        }

        .sb-group-inner .sb-link.active::after {
            content: '';
            position: absolute;
            left: -19px;
            right: auto;
            top: 50%;
            transform: translateY(-50%);
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--orange);
            box-shadow: 0 0 8px rgba(255, 69, 0, .7);
        }

        .sb-group.open .sb-group-inner .sb-link {
            animation: sbSubIn .35s cubic-bezier(.16, 1, .3, 1) backwards !important;
        }

        .sb-group.open .sb-group-inner .sb-link:nth-child(1) {
            animation-delay: .03s;
        }

        .sb-group.open .sb-group-inner .sb-link:nth-child(2) {
            animation-delay: .06s;
        }

        .sb-group.open .sb-group-inner .sb-link:nth-child(3) {
            animation-delay: .09s;
        }

        .sb-group.open .sb-group-inner .sb-link:nth-child(4) {
            animation-delay: .12s;
        }

        .sb-group.open .sb-group-inner .sb-link:nth-child(5) {
            animation-delay: .15s;
        }

        .sb-group.open .sb-group-inner .sb-link:nth-child(6) {
            animation-delay: .18s;
        }

        .sb-group.open .sb-group-inner .sb-link:nth-child(7) {
            animation-delay: .21s;
        }

        @keyframes sbSubIn {
            from {
                opacity: 0;
                transform: translateX(-10px);
            }

            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
    </style>

    <script>
        (function () {
            const groups = document.querySelectorAll('.sb-group');
            if (!groups.length) return;

            const STORAGE_KEY = 'hb_sidebar_groups';

            function readState() {
                try { return JSON.parse(localStorage.getItem(STORAGE_KEY)) || {}; }
                catch (e) { return {}; }
            }
            function writeState(state) {
                try { localStorage.setItem(STORAGE_KEY, JSON.stringify(state)); } catch (e) { }
            }

            const state = readState();

            groups.forEach(function (group) {
                const key = group.dataset.group;
                const toggle = group.querySelector('.sb-group-toggle');
                const body = group.querySelector('.sb-group-body');

                function setOpen(open, animate) {
                    group.classList.toggle('open', open);
                    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                    if (!animate) body.style.transition = 'none';
                    body.style.maxHeight = open ? body.scrollHeight + 'px' : '0px';
                    if (!animate) requestAnimationFrame(function () { body.style.transition = ''; });
                }

                if (group.querySelector('.sb-link.active')) {
                    group.classList.add('has-active');
                    setOpen(true, false);
                } else {
                    setOpen(state[key] === true, false);
                }

                toggle.addEventListener('click', function () {
                    const willOpen = !group.classList.contains('open');
                    setOpen(willOpen, true);
                    state[key] = willOpen;
                    writeState(state);
                });

                window.addEventListener('resize', function () {
                    if (group.classList.contains('open')) body.style.maxHeight = body.scrollHeight + 'px';
                });
            });
        })();

        /* ============================================================
           KONFIRMASI SEBELUM KELUAR (LOGOUT)
           Berlaku untuk semua link yang mengarah ke logout.php,
           di sidebar maupun di dropdown topbar, pada SEMUA halaman.
           ============================================================ */
        (function () {
            const SWAL_CDN = 'https://cdn.jsdelivr.net/npm/sweetalert2@11';
            let swalLoading = null;

            // Memastikan SweetAlert2 terpasang dengan aman
            function ensureSwal() {
                if (typeof Swal !== 'undefined' && typeof Swal.fire === 'function') {
                    return Promise.resolve();
                }
                if (swalLoading) return swalLoading;

                swalLoading = new Promise(function (resolve, reject) {
                    const s = document.createElement('script');
                    s.src = SWAL_CDN;
                    s.onload = function () {
                        if (typeof Swal !== 'undefined' && typeof Swal.fire === 'function') {
                            resolve();
                        } else {
                            reject(new Error('Swal failed'));
                        }
                    };
                    s.onerror = reject;
                    document.head.appendChild(s);
                });
                return swalLoading;
            }

            // Menampilkan dialog konfirmasi
            function showLogoutDialog(url) {
                if (typeof Swal === 'undefined' || typeof Swal.fire !== 'function') {
                    if (confirm('Apakah Anda yakin ingin keluar?')) window.location.href = url;
                    return;
                }

                Swal.fire({
                    title: 'Keluar dari HoopBall?',
                    html: 'Apakah Anda yakin ingin keluar?<br><span style="font-size:12px;color:#6B7280;">Sesi Anda akan diakhiri dan Anda perlu masuk kembali.</span>',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: '<i class="fa-solid fa-right-from-bracket"></i> Ya, Keluar',
                    cancelButtonText: 'Batal',
                    confirmButtonColor: '#EF4444',
                    cancelButtonColor: '#6B7280',
                    reverseButtons: true,
                    focusCancel: true,
                    allowOutsideClick: false
                }).then(function (result) {
                    if (!result.isConfirmed) return;

                    Swal.fire({
                        title: 'Sedang keluar...',
                        text: 'Mohon tunggu sebentar.',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        didOpen: function () {
                            if (typeof Swal.showLoading === 'function') Swal.showLoading();
                        }
                    });

                    setTimeout(function () { window.location.href = url; }, 500);
                });
            }

            // Hubungkan event listener langsung ke elemen (untuk melewati stopPropagation di global.js)
            function initLogout() {
                const logoutLinks = document.querySelectorAll('a[href*="logout.php"]');
                logoutLinks.forEach(function (link) {
                    if (link.dataset.logoutBound) return;
                    link.dataset.logoutBound = "true";

                    link.addEventListener('click', function (e) {
                        e.preventDefault();
                        const url = this.getAttribute('href');
                        ensureSwal()
                            .then(function () { showLogoutDialog(url); })
                            .catch(function () {
                                if (confirm('Apakah Anda yakin ingin keluar?')) window.location.href = url;
                            });
                    });
                });
            }

            // Jalankan langsung dan saat dokumen selesai dimuat
            initLogout();
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initLogout);
            }

            // Delegasi event cadangan di tingkat document
            document.addEventListener('click', function (e) {
                if (!e.target || typeof e.target.closest !== 'function') return;
                const link = e.target.closest('a[href*="logout.php"]');
                if (!link || link.dataset.logoutBound) return;

                e.preventDefault();
                const url = link.getAttribute('href');
                ensureSwal()
                    .then(function () { showLogoutDialog(url); })
                    .catch(function () {
                        if (confirm('Apakah Anda yakin ingin keluar?')) window.location.href = url;
                    });
            });
        })();
    </script>
<?php endif; ?>