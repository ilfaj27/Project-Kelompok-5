<?php
// ============================================================
// TOPBAR UNIFIED - HoopBall Management System
// ============================================================

// --- Fallback variabel dari session jika belum di-set ---
$role           = $role           ?? $_SESSION['role']           ?? '';
$nama           = $nama           ?? $_SESSION['nama']           ?? '';
$id_karyawan    = $id_karyawan    ?? $_SESSION['id_karyawan']  ?? $_SESSION['id_akun'] ?? '';

$topbar_title       = $topbar_title       ?? 'HoopBall';
$topbar_breadcrumb  = $topbar_breadcrumb  ?? '';

// Deteksi role otomatis
$is_pemilik = (strtolower($role) === 'pemilik');

if ($is_pemilik) {
    $display_role = 'MANAJER';
    $profile_link = '../profile/profile_pemilik.php';
    if ($topbar_title === 'HoopBall') {
        $topbar_title = 'Dashboard Manajer';
    }
} else {
    // Map jabatan INT ke string role
    $jabatan = $_SESSION['jabatan'] ?? '';
    if ($jabatan == 2) {
        $display_role = 'MANAJER';
    } elseif ($jabatan == 1) {
        $display_role = 'KARYAWAN';
    } else {
        $display_role = strtoupper(htmlspecialchars($role ?: 'KARYAWAN'));
    }
    $profile_link = '../profile/profile.php';
}

// ============================================================
// AMBIL FOTO PROFIL LANGSUNG DARI DATABASE + CONVERT BASE64
// ============================================================
$topbar_photo_base64 = '';
$topbar_initials = '';

// Ambil ID karyawan dari session (fallback ke variabel lokal)
$topbar_id_karyawan = $id_karyawan;
$topbar_nama = $_SESSION['nama'] ?? $nama ?? 'USER';

// Inisial untuk fallback
$topbar_initials = strtoupper(substr($topbar_nama, 0, 1));

// FIX: sebelumnya "is_resource($conn)" bisa false untuk koneksi sqlsrv
// tergantung versi PHP/driver, sehingga blok ini (dan foto profil) dilewati.
// Cukup cek $conn ada dan truthy.
if (!empty($topbar_id_karyawan) && isset($conn) && $conn) {
    $query_photo = "SELECT Photo_Profile, Nama_Karyawan FROM Karyawan WHERE ID_Karyawan = ?";
    $stmt_photo = @sqlsrv_query($conn, $query_photo, array($topbar_id_karyawan));

    if ($stmt_photo && sqlsrv_has_rows($stmt_photo)) {
        $row_photo = sqlsrv_fetch_array($stmt_photo, SQLSRV_FETCH_ASSOC);
        $photo_path = $row_photo['Photo_Profile'] ?? '';
        $topbar_nama = $row_photo['Nama_Karyawan'] ?? $topbar_nama;
        $topbar_initials = strtoupper(substr($topbar_nama, 0, 1));

        if (!empty($photo_path)) {
            // === CARI FILE DI SEMUA KEMUNGKINAN PATH ===
            $paths_to_try = [];

            // 1. Path asli dari database
            $paths_to_try[] = $photo_path;

            // 2. Naik 1 level (dari master/, transaksi/, profile/)
            $paths_to_try[] = '../' . $photo_path;

            // 3. Naik 2 level (dari master/lapangan/ dsb)
            $paths_to_try[] = '../../' . $photo_path;

            // 4. Dari folder script saat ini
            $script_dir = dirname($_SERVER['SCRIPT_FILENAME'] ?? '');
            $paths_to_try[] = $script_dir . '/' . $photo_path;
            $paths_to_try[] = dirname($script_dir) . '/' . $photo_path;
            $paths_to_try[] = dirname(dirname($script_dir)) . '/' . $photo_path;

            // 5. Dari DOCUMENT_ROOT
            $doc_root = $_SERVER['DOCUMENT_ROOT'] ?? '';
            if (!empty($doc_root)) {
                $paths_to_try[] = $doc_root . '/' . $photo_path;
                $paths_to_try[] = $doc_root . '/Project-Kelompok-5/' . $photo_path;
                $paths_to_try[] = $doc_root . '/Project-Kelompok-5/uploads/profiles/' . basename($photo_path);
                $paths_to_try[] = $doc_root . '/uploads/profiles/' . basename($photo_path);
            }

            // 6. Dari folder profile/ (karena foto diupload dari profile.php atau profile_pemilik.php)
            $paths_to_try[] = dirname($script_dir) . '/profile/' . $photo_path;
            $paths_to_try[] = dirname(dirname($script_dir)) . '/profile/' . $photo_path;
            $paths_to_try[] = $doc_root . '/Project-Kelompok-5/profile/' . $photo_path;
            $paths_to_try[] = $doc_root . '/Project-Kelompok-5/profile/uploads/profiles/' . basename($photo_path);

            // 7. Cari file dengan nama yang sama di seluruh project (fallback terakhir)
            if (!empty($doc_root)) {
                $project_dirs = [
                    $doc_root . '/Project-Kelompok-5/',
                    $doc_root . '/',
                ];
                $basename = basename($photo_path);
                if (!empty($basename)) {
                    foreach ($project_dirs as $pdir) {
                        if (is_dir($pdir)) {
                            try {
                                $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($pdir));
                                foreach ($rii as $file) {
                                    if ($file->isFile() && $file->getFilename() === $basename) {
                                        $paths_to_try[] = $file->getPathname();
                                        break 2;
                                    }
                                }
                            } catch (Exception $e) {
                                // Skip jika tidak bisa scan direktori
                            }
                        }
                    }
                }
            }

            $found_path = '';
            foreach ($paths_to_try as $p) {
                if (!empty($p) && file_exists($p) && is_file($p)) {
                    $found_path = $p;
                    break;
                }
            }

            if (!empty($found_path)) {
                $mime = mime_content_type($found_path) ?: 'image/jpeg';
                $data = @file_get_contents($found_path);
                if ($data !== false) {
                    $topbar_photo_base64 = 'data:' . $mime . ';base64,' . base64_encode($data);
                    // Update session
                    $_SESSION['Photo_Profile'] = $photo_path;
                }
            }
        }
    }
}
?>

<header class="topbar">
    <div class="topbar-left">
        <div class="topbar-title"><?= htmlspecialchars($topbar_title) ?></div>
        <?php if (!empty($topbar_breadcrumb)): ?>
        <div class="topbar-breadcrumb"><?= htmlspecialchars($topbar_breadcrumb) ?></div>
        <?php endif; ?>
    </div>
    <div class="topbar-right">
        <div id="clock-display">
            <div class="clock-time">
                <span id="h">00</span><span class="clock-colon">:</span>
                <span id="m">00</span><span class="clock-colon">:</span>
                <span id="s">00</span>
            </div>
            <div class="clock-divider"></div>
            <div class="clock-date" id="full-date">MEMUAT...</div>
        </div>
        <div class="dropdown-wrap" id="userDropdown">
            <div class="topbar-user">
                <div class="t-avatar">
                    <?php if (!empty($topbar_photo_base64)): ?>
                        <img src="<?= $topbar_photo_base64 ?>" alt="Profile">
                    <?php else: ?>
                        <span style="font-size:13px; font-weight:800;"><?= $topbar_initials ?></span>
                    <?php endif; ?>
                </div>
                <div>
                    <div class="t-name"><?= strtoupper(htmlspecialchars($topbar_nama)) ?></div>
                    <div class="t-role"><?= $display_role ?></div>
                </div>
                <i class="fa-solid fa-chevron-down t-chevron"></i>
            </div>
            <div class="dropdown-menu">
                <a href="<?= $profile_link ?>" class="dd-item">
                    <i class="fa-solid fa-id-badge"></i> Profil Saya
                </a>
                <hr class="dd-divider">
                <a href="../login/logout.php" class="dd-item" style="color:var(--red);">
                    <i class="fa-solid fa-right-from-bracket"></i> Keluar
                </a>
            </div>
        </div>
    </div>
</header>

<script>
(function() {
    // ============================================
    // 1. FITUR JAM DAN TANGGAL REAL-TIME
    // ============================================
    function updateClock() {
        const now = new Date();
        const h = String(now.getHours()).padStart(2, '0');
        const m = String(now.getMinutes()).padStart(2, '0');
        const s = String(now.getSeconds()).padStart(2, '0');
        if (document.getElementById('h')) document.getElementById('h').innerText = h;
        if (document.getElementById('m')) document.getElementById('m').innerText = m;
        if (document.getElementById('s')) document.getElementById('s').innerText = s;
        const days = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
        const months = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
        const dEl = document.getElementById('full-date');
        if (dEl) dEl.innerText = days[now.getDay()] + ', ' + now.getDate() + ' ' + months[now.getMonth()] + ' ' + now.getFullYear();
    }
    setInterval(updateClock, 1000);
    updateClock();

    // ============================================
    // 2. FITUR TOGGLE DROPDOWN (ANTI-BENTROK)
    // ============================================
    const userDropdown = document.getElementById('userDropdown');
    if (userDropdown) {
        // Kita hanya picu klik pada bagian header profil (tombol pemicu)
        const trigger = userDropdown.querySelector('.topbar-user');
        
        if (trigger) {
            trigger.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation(); // Stop agar tidak langsung ditutup oleh document click listener
                userDropdown.classList.toggle('active');
            });
        }

        // Menutup dropdown hanya jika klik di luar area menu dropdown
        document.addEventListener('click', function (e) {
            if (!userDropdown.contains(e.target)) {
                userDropdown.classList.remove('active');
            }
        });
    }
})();
</script>