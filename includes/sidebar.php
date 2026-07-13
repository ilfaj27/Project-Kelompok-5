<?php
/**
 * =================================================================
 * SIDEBAR UNIFIED - HoopBall Management System
 * =================================================================
 * File    : includes/sidebar.php
 * Usage   : include 'includes/sidebar.php';  (set $current_page & $sidebar_folder first)
 * Role    : karyawan (admin) | pemilik (manager)
 * =================================================================
 */

// --- Fallback variables (jika belum di-set di halaman utama) ---
$nama           = $nama           ?? 'Pengguna';
$role           = $role           ?? '';
$sidebar_photo  = $sidebar_photo  ?? '';
$sidebar_folder = $sidebar_folder ?? '';
$current_page   = $current_page   ?? '';

// --- URL helpers ---
$hb_home_url      = ($sidebar_folder === 'dashboard') ? 'view_admin.php' : '../dashboard/view_admin.php';
$hb_profile_url   = ($sidebar_folder === 'profile')   ? 'profile.php'    : '../profile/profile.php';

// --- Untuk pemilik: URL spesifik ---
$pemilik_home_url    = ($sidebar_folder === 'dashboard') ? 'view_pemilik.php' : '../dashboard/view_pemilik.php';
$pemilik_profile_url = ($sidebar_folder === 'profile')   ? 'profile_pemilik.php' : '../profile/profile_pemilik.php';
?>

<!-- ======================= SIDEBAR ======================= -->
<aside class="sidebar">

    <!-- ======================= LOGO / BRAND ======================= -->
    <a href="<?= $hb_home_url ?>" class="sb-brand">
        <div class="sb-logo">
            <svg viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <circle cx="24" cy="24" r="20" class="hb-ball" />
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

<?php if ($role === 'pemilik' || $role === 'manajer'): ?>
    <!-- ════════════════════════════════════════════════════════════
         SIDEBAR MENU  —  PEMILIK / MANAJER
         ════════════════════════════════════════════════════════════ -->

    <div class="sb-section-label">Manajemen</div>
    <nav>
        <a href="<?= $pemilik_home_url ?>" class="sb-link <?= ($current_page === 'dashboard') ? 'active' : '' ?>">
            <div class="sb-icon-wrap"><i class="fa-solid fa-house"></i></div>
            Dashboard
        </a>
        <a href="<?= ($sidebar_folder === 'master') ? 'karyawan.php' : '../master/karyawan.php' ?>"
           class="sb-link <?= ($current_page === 'karyawan') ? 'active' : '' ?>">
            <div class="sb-icon-wrap"><i class="fa-solid fa-user-tie"></i></div>
            Kelola Karyawan
        </a>
    </nav>

    <!-- ===== LAPORAN (MINI GROUP) ===== -->
    <div class="sb-section-label">Laporan</div>
    <div class="sb-mini-group">
        <a href="<?= ($sidebar_folder === 'laporan') ? 'laporan_omzet.php' : '../laporan/laporan_omzet.php' ?>"
           class="sb-link sb-link-mini <?= ($current_page === 'laporan_omzet') ? 'active' : '' ?>">
            <div class="sb-icon-wrap"><i class="fa-solid fa-money-bill-trend-up"></i></div>
            Laporan Omzet
        </a>
        <a href="<?= ($sidebar_folder === 'laporan') ? 'laporan_langganan.php' : '../laporan/laporan_langganan.php' ?>"
           class="sb-link sb-link-mini <?= ($current_page === 'laporan_langganan') ? 'active' : '' ?>">
            <div class="sb-icon-wrap"><i class="fa-solid fa-crown"></i></div>
            Laporan Langganan
        </a>
        <a href="<?= ($sidebar_folder === 'laporan') ? 'laporan_pembelian_alat.php' : '../laporan/laporan_pembelian_alat.php' ?>"
           class="sb-link sb-link-mini <?= ($current_page === 'laporan_pembelian_alat') ? 'active' : '' ?>">
            <div class="sb-icon-wrap"><i class="fa-solid fa-cart-shopping"></i></div>
            Laporan Pembelian Alat
        </a>
        <a href="<?= ($sidebar_folder === 'laporan') ? 'laporan_sewa_lapangan.php' : '../laporan/laporan_sewa_lapangan.php' ?>"
           class="sb-link sb-link-mini <?= ($current_page === 'laporan_sewa_lapangan') ? 'active' : '' ?>">
            <div class="sb-icon-wrap"><i class="fa-solid fa-basketball"></i></div>
            Laporan Sewa Lapangan
        </a>
    </div>

    <div class="sb-section-label">Akun</div>
    <nav>
        <a href="<?= $pemilik_profile_url ?>" class="sb-link <?= ($current_page === 'profile') ? 'active' : '' ?>">
            <div class="sb-icon-wrap"><i class="fa-solid fa-id-badge"></i></div>
            Profil Saya
        </a>
    </nav>

<?php else: ?>
    <!-- ════════════════════════════════════════════════════════════
         SIDEBAR MENU  —  KARYAWAN (ADMIN)
         ════════════════════════════════════════════════════════════ -->

    <!-- ===== MENU UTAMA ===== -->
    <div class="sb-section-label">Menu Utama</div>
    <nav>
        <a href="<?= $hb_home_url ?>" class="sb-link <?= ($current_page === 'dashboard') ? 'active' : '' ?>">
            <div class="sb-icon-wrap"><i class="fa-solid fa-house"></i></div>
            Dashboard
        </a>
    </nav>

    <!-- ===== MASTER DATA (DROPDOWN) ===== -->
    <div class="sb-section-label">Master Data</div>
    <div class="sb-dropdown" id="dropdown-master">
        <button type="button" class="sb-dropdown-toggle <?= in_array($current_page, ['tipe_member','fasilitas','alat','promo','customer','lapangan','jadwal']) ? 'expanded' : '' ?>"
                onclick="toggleDropdown('dropdown-master')">
            <div class="sb-dropdown-left">
                <div class="sb-icon-wrap"><i class="fa-solid fa-database"></i></div>
                <span>Master Data</span>
            </div>
            <i class="fa-solid fa-chevron-down sb-dropdown-arrow"></i>
        </button>
        <div class="sb-dropdown-menu <?= in_array($current_page, ['tipe_member','fasilitas','alat','promo','customer','lapangan','jadwal']) ? 'open' : '' ?>">
            <a href="<?= ($sidebar_folder === 'master') ? 'tipe_member.php' : '../master/tipe_member.php' ?>"
               class="sb-link sb-link-mini <?= ($current_page === 'tipe_member') ? 'active' : '' ?>">
                <div class="sb-icon-wrap"><i class="fa-solid fa-id-card"></i></div>
                Kelola Tipe Member
            </a>
            <a href="<?= ($sidebar_folder === 'master') ? 'fasilitas_lapangan.php' : '../master/fasilitas_lapangan.php' ?>"
               class="sb-link sb-link-mini <?= ($current_page === 'fasilitas') ? 'active' : '' ?>">
                <div class="sb-icon-wrap"><i class="fa-solid fa-list-check"></i></div>
                Kelola Fasilitas
            </a>
            <a href="<?= ($sidebar_folder === 'master') ? 'alat.php' : '../master/alat.php' ?>"
               class="sb-link sb-link-mini <?= ($current_page === 'alat') ? 'active' : '' ?>">
                <div class="sb-icon-wrap"><i class="fa-solid fa-toolbox"></i></div>
                Kelola Alat
            </a>
            <a href="<?= ($sidebar_folder === 'master') ? 'promo.php' : '../master/promo.php' ?>"
               class="sb-link sb-link-mini <?= ($current_page === 'promo') ? 'active' : '' ?>">
                <div class="sb-icon-wrap"><i class="fa-solid fa-tags"></i></div>
                Kelola Promo
            </a>
            <a href="<?= ($sidebar_folder === 'master') ? 'customer.php' : '../master/customer.php' ?>"
               class="sb-link sb-link-mini <?= ($current_page === 'customer') ? 'active' : '' ?>">
                <div class="sb-icon-wrap"><i class="fa-solid fa-users"></i></div>
                Kelola Customer
            </a>
            <a href="<?= ($sidebar_folder === 'master') ? 'lapangan.php' : '../master/lapangan.php' ?>"
               class="sb-link sb-link-mini <?= ($current_page === 'lapangan') ? 'active' : '' ?>">
                <div class="sb-icon-wrap"><i class="fa-solid fa-layer-group"></i></div>
                Kelola Lapangan
            </a>
            <a href="<?= ($sidebar_folder === 'master') ? 'jadwal.php' : '../master/jadwal.php' ?>"
               class="sb-link sb-link-mini <?= ($current_page === 'jadwal') ? 'active' : '' ?>">
                <div class="sb-icon-wrap"><i class="fa-solid fa-calendar-days"></i></div>
                Kelola Jadwal
            </a>
        </div>
    </div>

    <!-- ===== TRANSAKSI (DROPDOWN) ===== -->
    <div class="sb-section-label">Transaksi</div>
    <div class="sb-dropdown" id="dropdown-transaksi">
        <button type="button" class="sb-dropdown-toggle <?= in_array($current_page, ['booking','langganan','pembelian_alat','pembatalan']) ? 'expanded' : '' ?>"
                onclick="toggleDropdown('dropdown-transaksi')">
            <div class="sb-dropdown-left">
                <div class="sb-icon-wrap"><i class="fa-solid fa-money-bill-transfer"></i></div>
                <span>Transaksi</span>
            </div>
            <i class="fa-solid fa-chevron-down sb-dropdown-arrow"></i>
        </button>
        <div class="sb-dropdown-menu <?= in_array($current_page, ['booking','langganan','pembelian_alat','pembatalan']) ? 'open' : '' ?>">
            <a href="<?= ($sidebar_folder === 'transaksi') ? 'booking.php' : '../transaksi/booking.php' ?>"
               class="sb-link sb-link-mini <?= ($current_page === 'booking') ? 'active' : '' ?>">
                <div class="sb-icon-wrap"><i class="fa-solid fa-calendar-check"></i></div>
                Kelola Booking
            </a>
            <a href="<?= ($sidebar_folder === 'transaksi') ? 'langganan.php' : '../transaksi/langganan.php' ?>"
               class="sb-link sb-link-mini <?= ($current_page === 'langganan') ? 'active' : '' ?>">
                <div class="sb-icon-wrap"><i class="fa-solid fa-crown"></i></div>
                Kelola Langganan
            </a>
            <a href="<?= ($sidebar_folder === 'transaksi') ? 'pembelian.php' : '../transaksi/pembelian.php' ?>"
               class="sb-link sb-link-mini <?= ($current_page === 'pembelian_alat') ? 'active' : '' ?>">
                <div class="sb-icon-wrap"><i class="fa-solid fa-cart-shopping"></i></div>
                Kelola Pembelian Alat
            </a>
            <a href="<?= ($sidebar_folder === 'transaksi') ? 'pembatalan.php' : '../transaksi/pembatalan.php' ?>"
               class="sb-link sb-link-mini <?= ($current_page === 'pembatalan') ? 'active' : '' ?>">
                <div class="sb-icon-wrap"><i class="fa-solid fa-ban"></i></div>
                Kelola Pembatalan
            </a>
        </div>
    </div>

    <!-- ===== AKUN ===== -->
    <div class="sb-section-label">Akun</div>
    <nav>
        <a href="<?= $hb_profile_url ?>" class="sb-link <?= ($current_page === 'profile') ? 'active' : '' ?>">
            <div class="sb-icon-wrap"><i class="fa-solid fa-id-badge"></i></div>
            Profil Saya
        </a>
    </nav>

<?php endif; ?>

    <!-- ════════════════════════════════════════════════════════════
         BOTTOM USER CARD  —  SAMA UNTUK SEMUA ROLE
         ════════════════════════════════════════════════════════════ -->
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
/* ═══════════════════════════════════════════════════════════════
   ROOT VARIABLES
   ═══════════════════════════════════════════════════════════════ */
:root {
    --orange: #FF4500; --orange-lt: rgba(255,69,0,.10); --orange-dk: #E03E00;
    --green: #10B981; --green-lt: rgba(16,185,129,.10);
    --blue: #3B82F6; --blue-lt: rgba(59,130,246,.10);
    --purple: #8B5CF6; --purple-lt: rgba(139,92,246,.10);
    --red: #EF4444; --red-lt: rgba(239,68,68,.10);
    --yellow: #F59E0B; --yellow-lt: rgba(245,158,11,.10);
    --sidebar: #0D1117; --sidebar-w: 260px; --topbar-h: 70px;
    --card-bg: #FFFFFF; --border: #E5E7EB; --border-lt: #F3F4F6;
    --text: #111827; --text-md: #374151; --muted: #6B7280; --bg: #F3F4F6; --bg-dark: #1F2937;
}

/* ═══════════════════════════════════════════════════════════════
   SIDEBAR  —  BASE LAYOUT & ANIMATIONS
   ═══════════════════════════════════════════════════════════════ */
.sidebar {
    width: var(--sidebar-w);
    background: var(--sidebar);
    height: 100vh;
    position: fixed;
    top: 0;
    left: 0;
    display: flex;
    flex-direction: column;
    padding: 28px 18px;
    border-right: 1px solid rgba(255,255,255,.04);
    z-index: 200;
    overflow-y: auto;
    scrollbar-width: none;
    -ms-overflow-style: none;
}
.sidebar::-webkit-scrollbar { display: none; }

@keyframes sidebarSlideIn {
    from { transform: translateX(-100%); opacity: 0; }
    to   { transform: translateX(0);     opacity: 1; }
}
.sidebar { animation: sidebarSlideIn 0.6s cubic-bezier(0.16,1,0.3,1) forwards; }

@keyframes menuItemFadeIn {
    from { opacity: 0; transform: translateX(-20px); }
    to   { opacity: 1; transform: translateX(0); }
}

/* ═══════════════════════════════════════════════════════════════
   LOGO / BRAND
   ═══════════════════════════════════════════════════════════════ */
.sb-brand {
    display: flex;
    align-items: center;
    gap: 13px;
    padding: 4px 6px 16px;
    margin-bottom: 14px;
    text-decoration: none;
    position: relative;
    border-bottom: 1px solid rgba(255,255,255,.06);
    transition: transform .35s cubic-bezier(.16,1,.3,1);
    animation: menuItemFadeIn 0.5s cubic-bezier(0.16,1,0.3,1) 0.1s forwards;
    opacity: 0;
}
.sb-brand:hover { transform: translateY(-1px); }

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
    box-shadow: 0 6px 18px rgba(255,69,0,.42), inset 0 1px 0 rgba(255,255,255,.28);
    overflow: hidden;
    transition: transform .45s cubic-bezier(.34,1.56,.64,1), box-shadow .35s ease;
}
.sb-brand:hover .sb-logo {
    transform: rotate(-8deg) scale(1.06);
    box-shadow: 0 10px 26px rgba(255,69,0,.55), inset 0 1px 0 rgba(255,255,255,.35);
}
.sb-logo svg { width: 25px; height: 25px; position: relative; z-index: 2; overflow: visible; }
.hb-ball { fill: none; stroke: #fff; stroke-width: 3.2; }
.hb-seam { fill: none; stroke: #fff; stroke-width: 2.6; stroke-linecap: round; opacity: .95; }

.sb-brand:hover .sb-logo svg { animation: hbSpin 1.6s cubic-bezier(.16,1,.3,1); }
@keyframes hbSpin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

.sb-logo-glow {
    position: absolute; inset: 0; z-index: 3; pointer-events: none;
    background: linear-gradient(115deg, transparent 38%, rgba(255,255,255,.42) 50%, transparent 62%);
    transform: translateX(-130%);
}
.sb-brand:hover .sb-logo-glow { animation: hbShine .85s ease-out; }
@keyframes hbShine { to { transform: translateX(130%); } }

.sb-brand-text { line-height: 1; }
.sb-brand-name {
    font-family: 'Barlow Condensed', sans-serif;
    font-size: 23px; font-weight: 900; color: #fff;
    letter-spacing: 1.4px; line-height: 1;
}
.sb-brand-name span { color: var(--orange); transition: color .3s ease, text-shadow .3s ease; }
.sb-brand:hover .sb-brand-name span { text-shadow: 0 0 14px rgba(255,69,0,.6); }

.sb-brand-sub {
    display: flex; align-items: center; gap: 6px;
    margin-top: 5px;
    font-size: 8.5px; font-weight: 800; letter-spacing: 1.3px;
    text-transform: uppercase; color: #4B5563;
    transition: color .3s ease;
}
.sb-brand:hover .sb-brand-sub { color: #9CA3AF; }

.sb-brand-dot {
    width: 5px; height: 5px; border-radius: 50%;
    background: var(--green); flex-shrink: 0;
    box-shadow: 0 0 0 0 rgba(16,185,129,.6);
    animation: hbDot 2.4s ease-out infinite;
}
@keyframes hbDot {
    0%   { box-shadow: 0 0 0 0 rgba(16,185,129,.55); }
    60%  { box-shadow: 0 0 0 5px rgba(16,185,129,0); }
    100% { box-shadow: 0 0 0 0 rgba(16,185,129,0); }
}

/* ═══════════════════════════════════════════════════════════════
   SECTION LABEL
   ═══════════════════════════════════════════════════════════════ */
.sb-section-label {
    font-size: 10px; font-weight: 800; text-transform: uppercase;
    color: #374151; letter-spacing: .8px;
    padding: 0 10px; margin: 22px 0 8px;
    position: relative;
    animation: menuItemFadeIn 0.5s cubic-bezier(0.16,1,0.3,1) forwards;
    opacity: 0;
}
.sb-section-label::after {
    content: ''; position: absolute; bottom: -4px; left: 10px;
    width: 20px; height: 2px; background: var(--orange); border-radius: 1px;
    transition: width 0.3s ease;
}
.sb-section-label:hover::after { width: 40px; }

/* ═══════════════════════════════════════════════════════════════
   SIDEBAR LINK  —  NORMAL SIZE
   ═══════════════════════════════════════════════════════════════ */
.sb-link {
    display: flex; align-items: center; gap: 12px;
    color: #6B7280; text-decoration: none;
    padding: 10px 12px; border-radius: 10px; margin-bottom: 2px;
    font-size: 13px; font-weight: 600;
    transition: all 0.35s cubic-bezier(0.16,1,0.3,1);
    position: relative; overflow: hidden;
    animation: menuItemFadeIn 0.5s cubic-bezier(0.16,1,0.3,1) forwards;
    opacity: 0;
}
.sb-link::before {
    content: ''; position: absolute; left: 0; top: 0;
    width: 0; height: 100%;
    background: linear-gradient(90deg, rgba(255,69,0,0.15), rgba(255,69,0,0.05));
    border-radius: 10px;
    transition: width 0.35s cubic-bezier(0.16,1,0.3,1);
    z-index: 0;
}
.sb-link:hover::before { width: 100%; }
.sb-link .sb-icon-wrap {
    width: 32px; height: 32px; border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: 13px;
    transition: all 0.35s cubic-bezier(0.34,1.56,0.64,1);
    flex-shrink: 0; background: rgba(255,255,255,.04);
    position: relative; z-index: 1;
}
.sb-link:hover { color: #E5E7EB; transform: translateX(4px); }
.sb-link:hover .sb-icon-wrap {
    background: rgba(255,255,255,.12);
    transform: scale(1.15) rotate(5deg);
}

/* ── Active state ── */
.sb-link.active { color: #fff; background: var(--orange-lt); }
.sb-link.active::before {
    width: 100%;
    background: linear-gradient(90deg, rgba(255,69,0,0.2), rgba(255,69,0,0.08));
}
.sb-link.active .sb-icon-wrap {
    background: var(--orange); color: #fff;
    transform: scale(1.1);
    box-shadow: 0 4px 12px rgba(255,69,0,.3);
}

/* Active indicator pill (kanan) */
.sb-link.active::after {
    content: ''; position: absolute; right: -18px; top: 50%;
    transform: translateY(-50%);
    width: 3px; height: 20px; background: var(--orange);
    border-radius: 3px 0 0 3px;
    transition: all 0.3s cubic-bezier(0.16,1,0.3,1);
}

/* ═══════════════════════════════════════════════════════════════
   DROPDOWN  —  MASTER DATA & TRANSAKSI
   ═══════════════════════════════════════════════════════════════ */
.sb-dropdown {
    margin-bottom: 4px;
    animation: menuItemFadeIn 0.5s cubic-bezier(0.16,1,0.3,1) forwards;
    opacity: 0;
}

.sb-dropdown-toggle {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    gap: 12px;
    color: #6B7280;
    padding: 10px 12px;
    border-radius: 10px;
    margin-bottom: 2px;
    font-size: 13px;
    font-weight: 600;
    transition: all 0.35s cubic-bezier(0.16,1,0.3,1);
    position: relative;
    overflow: hidden;
    background: transparent;
    border: none;
    cursor: pointer;
    font-family: inherit;
    text-align: left;
}
.sb-dropdown-toggle::before {
    content: '';
    position: absolute;
    left: 0; top: 0;
    width: 0; height: 100%;
    background: linear-gradient(90deg, rgba(255,69,0,0.15), rgba(255,69,0,0.05));
    border-radius: 10px;
    transition: width 0.35s cubic-bezier(0.16,1,0.3,1);
    z-index: 0;
}
.sb-dropdown-toggle:hover::before { width: 100%; }
.sb-dropdown-toggle:hover {
    color: #E5E7EB;
    transform: translateX(4px);
}
.sb-dropdown-toggle:hover .sb-icon-wrap {
    background: rgba(255,255,255,.12);
    transform: scale(1.15) rotate(5deg);
}
.sb-dropdown-toggle .sb-icon-wrap {
    width: 32px; height: 32px; border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: 13px;
    transition: all 0.35s cubic-bezier(0.34,1.56,0.64,1);
    flex-shrink: 0;
    background: rgba(255,255,255,.04);
    position: relative;
    z-index: 1;
}

.sb-dropdown-left {
    display: flex;
    align-items: center;
    gap: 12px;
    position: relative;
    z-index: 1;
}

.sb-dropdown-arrow {
    font-size: 11px;
    transition: transform 0.35s cubic-bezier(0.34,1.56,0.64,1);
    position: relative;
    z-index: 1;
    color: #4B5563;
}
.sb-dropdown-toggle:hover .sb-dropdown-arrow { color: #9CA3AF; }

/* Expanded state */
.sb-dropdown-toggle.expanded .sb-dropdown-arrow {
    transform: rotate(180deg);
}

/* Active child indicator on toggle */
.sb-dropdown-toggle.has-active {
    color: #E5E7EB;
}
.sb-dropdown-toggle.has-active .sb-icon-wrap {
    background: var(--orange);
    color: #fff;
    box-shadow: 0 4px 12px rgba(255,69,0,.3);
}

/* Dropdown menu */
.sb-dropdown-menu {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.45s cubic-bezier(0.16,1,0.3,1), opacity 0.35s ease, padding 0.35s ease;
    opacity: 0;
    padding-left: 14px;
    margin-left: 8px;
    border-left: 2px solid rgba(255,255,255,.08);
}
.sb-dropdown-menu.open {
    max-height: 600px;
    opacity: 1;
    padding-top: 4px;
    padding-bottom: 4px;
}

/* Mini link inside dropdown */
.sb-dropdown-menu .sb-link.sb-link-mini {
    padding: 7px 10px;
    font-size: 12px;
    margin-bottom: 1px;
    border-radius: 8px;
}
.sb-dropdown-menu .sb-link.sb-link-mini .sb-icon-wrap {
    width: 26px;
    height: 26px;
    border-radius: 6px;
    font-size: 11px;
}
.sb-dropdown-menu .sb-link.sb-link-mini:hover {
    transform: translateX(3px);
}
.sb-dropdown-menu .sb-link.sb-link-mini:hover .sb-icon-wrap {
    transform: scale(1.1);
}

/* Active state for mini inside dropdown */
.sb-dropdown-menu .sb-link.sb-link-mini.active::after {
    right: -14px;
    width: 2px;
    height: 16px;
}

/* ═══════════════════════════════════════════════════════════════
   MINI GROUP  —  COMPACT ITEMS WITH LEFT BORDER (LAPORAN - tetap)
   ═══════════════════════════════════════════════════════════════ */
.sb-mini-group {
    position: relative;
    margin-left: 8px;
    padding-left: 14px;
    border-left: 2px solid rgba(255,255,255,.08);
    margin-bottom: 4px;
}

/* Mini link — smaller than normal */
.sb-link.sb-link-mini {
    padding: 7px 10px;
    font-size: 12px;
    margin-bottom: 1px;
    border-radius: 8px;
}
.sb-link.sb-link-mini .sb-icon-wrap {
    width: 26px;
    height: 26px;
    border-radius: 6px;
    font-size: 11px;
}
.sb-link.sb-link-mini:hover {
    transform: translateX(3px);
}
.sb-link.sb-link-mini:hover .sb-icon-wrap {
    transform: scale(1.1);
}

/* Active state for mini */
.sb-link.sb-link-mini.active::after {
    right: -14px;
    width: 2px;
    height: 16px;
}

/* ═══════════════════════════════════════════════════════════════
   BOTTOM USER CARD
   ═══════════════════════════════════════════════════════════════ */
.sb-bottom {
    margin-top: auto; padding-top: 20px;
    animation: menuItemFadeIn 0.5s cubic-bezier(0.16,1,0.3,1) 0.5s forwards;
    opacity: 0;
}

.sb-user {
    display: flex; align-items: center; gap: 10px;
    background: rgba(255,255,255,.04); border-radius: 12px;
    padding: 12px; border: 1px solid rgba(255,255,255,.06);
    transition: all 0.3s cubic-bezier(0.16,1,0.3,1);
    cursor: pointer;
}
.sb-user:hover {
    background: rgba(255,255,255,.08);
    border-color: rgba(255,69,0,.2);
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0,0,0,.15);
}

.sb-avatar {
    width: 36px; height: 36px; background: var(--orange);
    border-radius: 50%; display: flex;
    align-items: center; justify-content: center;
    color: #fff; font-size: 14px; flex-shrink: 0;
    overflow: hidden;
    transition: all 0.3s cubic-bezier(0.34,1.56,0.64,1);
}
.sb-user:hover .sb-avatar {
    transform: scale(1.1);
    box-shadow: 0 4px 12px rgba(255,69,0,.3);
}
.sb-avatar img {
    width: 100%; height: 100%; object-fit: cover; border-radius: 50%;
    transition: transform 0.3s ease;
}
.sb-user:hover .sb-avatar img { transform: scale(1.1); }

.sb-user-name {
    font-size: 13px; font-weight: 800; color: #E5E7EB;
    line-height: 1.1; transition: color 0.3s ease;
}
.sb-user:hover .sb-user-name { color: #fff; }

.sb-user-role {
    font-size: 10px; color: var(--orange); font-weight: 700;
    text-transform: uppercase; transition: all 0.3s ease;
}
.sb-user:hover .sb-user-role { letter-spacing: 1px; }

.sb-logout {
    margin-left: auto; color: #4B5563; font-size: 13px;
    transition: all 0.3s cubic-bezier(0.34,1.56,0.64,1);
    cursor: pointer; text-decoration: none;
    width: 32px; height: 32px;
    display: flex; align-items: center; justify-content: center;
    border-radius: 8px; position: relative; overflow: hidden;
}
.sb-logout::before {
    content: ''; position: absolute; inset: 0;
    background: var(--red-lt); border-radius: 8px;
    transform: scale(0);
    transition: transform 0.3s cubic-bezier(0.34,1.56,0.64,1);
}
.sb-logout:hover { color: var(--red); }
.sb-logout:hover::before { transform: scale(1); }
.sb-logout i { position: relative; z-index: 1; transition: transform 0.3s ease; }
.sb-logout:hover i { transform: translateX(2px); }

/* ═══════════════════════════════════════════════════════════════
   STAGGERED ANIMATION DELAYS
   ═══════════════════════════════════════════════════════════════ */
.sb-section-label:nth-of-type(1) { animation-delay: 0.15s; }
.sb-section-label:nth-of-type(2) { animation-delay: 0.35s; }
.sb-section-label:nth-of-type(3) { animation-delay: 0.55s; }
.sb-section-label:nth-of-type(4) { animation-delay: 0.75s; }

/* Normal nav links */
nav:nth-of-type(1) .sb-link:nth-child(1) { animation-delay: 0.20s; }
nav:nth-of-type(1) .sb-link:nth-child(2) { animation-delay: 0.25s; }

/* Dropdown toggles */
.sb-dropdown:nth-of-type(1) { animation-delay: 0.40s; }
.sb-dropdown:nth-of-type(2) { animation-delay: 0.60s; }

/* Dropdown menu items - staggered */
#dropdown-master .sb-dropdown-menu .sb-link:nth-child(1) { animation-delay: 0.44s; }
#dropdown-master .sb-dropdown-menu .sb-link:nth-child(2) { animation-delay: 0.48s; }
#dropdown-master .sb-dropdown-menu .sb-link:nth-child(3) { animation-delay: 0.52s; }
#dropdown-master .sb-dropdown-menu .sb-link:nth-child(4) { animation-delay: 0.56s; }
#dropdown-master .sb-dropdown-menu .sb-link:nth-child(5) { animation-delay: 0.60s; }
#dropdown-master .sb-dropdown-menu .sb-link:nth-child(6) { animation-delay: 0.64s; }
#dropdown-master .sb-dropdown-menu .sb-link:nth-child(7) { animation-delay: 0.68s; }

#dropdown-transaksi .sb-dropdown-menu .sb-link:nth-child(1) { animation-delay: 0.64s; }
#dropdown-transaksi .sb-dropdown-menu .sb-link:nth-child(2) { animation-delay: 0.68s; }
#dropdown-transaksi .sb-dropdown-menu .sb-link:nth-child(3) { animation-delay: 0.72s; }
#dropdown-transaksi .sb-dropdown-menu .sb-link:nth-child(4) { animation-delay: 0.76s; }

/* Akun nav */
nav:nth-of-type(2) .sb-link:nth-child(1) { animation-delay: 0.80s; }

/* ═══════════════════════════════════════════════════════════════
   RESPONSIVE
   ═══════════════════════════════════════════════════════════════ */
@media(max-width: 768px) {
    .sidebar { width: 0; overflow: hidden; padding: 0; }
}
</style>

<script>
/* ============================================================
   DROPDOWN TOGGLE
   ============================================================ */
function toggleDropdown(id) {
    const dropdown = document.getElementById(id);
    if (!dropdown) return;
    const menu = dropdown.querySelector('.sb-dropdown-menu');
    const toggle = dropdown.querySelector('.sb-dropdown-toggle');
    if (menu.classList.contains('open')) {
        menu.classList.remove('open');
        toggle.classList.remove('expanded');
    } else {
        menu.classList.add('open');
        toggle.classList.add('expanded');
    }
}

/* ============================================================
   KONFIRMASI LOGOUT  (SweetAlert2)
   ============================================================ */
(function () {
    const SWAL_CDN = 'https://cdn.jsdelivr.net/npm/sweetalert2@11';
    let swalLoading = null;

    function ensureSwal() {
        if (typeof Swal !== 'undefined' && typeof Swal.fire === 'function') {
            return Promise.resolve();
        }
        if (swalLoading) return swalLoading;
        swalLoading = new Promise(function (resolve, reject) {
            const s = document.createElement('script');
            s.src = SWAL_CDN;
            s.onload  = function () {
                if (typeof Swal !== 'undefined' && typeof Swal.fire === 'function') resolve();
                else reject(new Error('Swal failed'));
            };
            s.onerror = reject;
            document.head.appendChild(s);
        });
        return swalLoading;
    }

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
                didOpen: function () { if (typeof Swal.showLoading === 'function') Swal.showLoading(); }
            });
            setTimeout(function () { window.location.href = url; }, 500);
        });
    }

    function initLogout() {
        document.querySelectorAll('a[href*="logout.php"]').forEach(function (link) {
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

    initLogout();
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initLogout);
    }

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