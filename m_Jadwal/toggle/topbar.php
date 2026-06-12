<header class="topbar">
    <div class="topbar-left">
        <div class="topbar-title">Master Jadwal</div>
        <div class="topbar-breadcrumb">Dashboard / Master Jadwal</div>
    </div>
    <div class="topbar-right">
        <div id="clock-display">
            <div class="clock-time"><span id="h">00</span><span class="clock-colon">:</span><span id="m">00</span><span class="clock-colon">:</span><span id="s">00</span></div>
            <div class="clock-divider"></div>
            <div class="clock-date" id="full-date">MEMUAT...</div>
        </div>
        <a href="#" class="topbar-btn"><i class="fa-solid fa-bell"></i></a>
        <div class="dropdown-wrap">
            <div class="topbar-user">
                <div class="t-avatar"><i class="fa-solid fa-user"></i></div>
                <div>
                    <div class="t-name"><?= strtoupper(htmlspecialchars($_SESSION['nama'] ?? 'USER')) ?></div>
                    <div class="t-role">KARYAWAN</div>
                </div>
                <i class="fa-solid fa-chevron-down t-chevron"></i>
            </div>
            <div class="dropdown-menu">
                <a href="../profile.php" class="dd-item"><i class="fa-solid fa-id-badge"></i> Profil Saya</a>
                <hr class="dd-divider">
                <a href="../logout.php" class="dd-item" style="color:var(--red);"><i class="fa-solid fa-right-from-bracket"></i> Keluar</a>
            </div>
        </div>
    </div>
</header>