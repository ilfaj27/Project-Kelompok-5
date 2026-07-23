<?php
session_start();
require_once '../login/auth_check.php';
date_default_timezone_set("Asia/Jakarta");
$path_prefix = "../";

include '../includes/config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'karyawan') {
    echo "<script>alert('Akses Ditolak!'); window.location='../dashboard/dashboard.php';</script>";
    exit();
}

// ========================================================
// ⚠️ PANGGIL SENSOR AUTO LOGOUT IDLE (DENGAN PENGAMAN AJAX) ⚠️
// ========================================================
$action_value = $_GET['action'] ?? $_POST['action'] ?? $_GET['ajax_get_detail'] ?? $_POST['is_ajax_save'] ?? '';
$is_real_ajax = ($action_value !== '' && $action_value !== 'auto_logout');

if (!$is_real_ajax) {
    require_once '../login/auto_logout.php';
}
// ========================================================

$role = $_SESSION['role'];
$nama = $_SESSION['nama'] ?? 'USER';
$current_page = 'jadwal';

// ===== PHOTO PROFILE =====
$profile_photo = '';
$id_karyawan_session = $_SESSION['id_karyawan'] ?? $_SESSION['id_akun'] ?? '';
if (!empty($id_karyawan_session)) {
    $stmt_photo = sqlsrv_query($conn, "SELECT Photo_Profile FROM Karyawan WHERE ID_Karyawan = ?", array($id_karyawan_session));
    if ($stmt_photo !== false) {
        $row_photo = sqlsrv_fetch_array($stmt_photo, SQLSRV_FETCH_ASSOC);
        if ($row_photo && !empty($row_photo['Photo_Profile'])) {
            $photo_path = $row_photo['Photo_Profile'];
            if (strpos($photo_path, '../') === 0) {
                $profile_photo = $photo_path;
            } elseif (strpos($photo_path, 'uploads/') === 0) {
                $profile_photo = '../' . $photo_path;
            } else {
                $profile_photo = '../uploads/profiles/' . $photo_path;
            }
        }
    }
}

function safeQuery($conn, $sql, $params = []) {
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) return null;
    return $stmt;
}
function safeFetch($stmt) {
    if ($stmt === null) return false;
    return sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
}

function formatInputDate($d) {
    if ($d instanceof DateTime) return $d->format('Y-m-d');
    return !empty($d) ? date('Y-m-d', strtotime($d)) : '';
}
function formatInputTime($t) {
    if ($t instanceof DateTime) return $t->format('H:i');
    return !empty($t) ? date('H:i', strtotime($t)) : '';
}

$lapangan_list = [];
$q_lap = safeQuery($conn, "SELECT ID_Lapangan, Nama_Lapangan, Harga_Sewa FROM Lapangan WHERE Is_Deleted=0 AND Status=1 ORDER BY Nama_Lapangan", []);
if ($q_lap) {
    while ($row = sqlsrv_fetch_array($q_lap, SQLSRV_FETCH_ASSOC)) {
        $lapangan_list[] = $row;
    }
}

// ===== KONFIGURASI JAM OPERASIONAL =====
define('OPS_JAM_MULAI_AWAL', 8);   // 08:00
define('OPS_JAM_MULAI_AKHIR', 23); // 23:00

function hitungJamSelesai($jam_mulai) {
    list($h, $m) = explode(':', $jam_mulai);
    $h = intval($h) + 1;
    if ($h >= 24) $h = 0;
    return sprintf('%02d:%02d', $h, intval($m));
}

function daftarJamOperasional() {
    $out = [];
    for ($h = OPS_JAM_MULAI_AWAL; $h <= OPS_JAM_MULAI_AKHIR; $h++) {
        $out[] = sprintf('%02d:00', $h);
    }
    return $out;
}

$hari_pendek = ['Sun'=>'Min','Mon'=>'Sen','Tue'=>'Sel','Wed'=>'Rab','Thu'=>'Kam','Fri'=>'Jum','Sat'=>'Sab'];
$bulan_pendek = ['01'=>'Jan','02'=>'Feb','03'=>'Mar','04'=>'Apr','05'=>'Mei','06'=>'Jun','07'=>'Jul','08'=>'Agu','09'=>'Sep','10'=>'Okt','11'=>'Nov','12'=>'Des'];
function labelHariID($tgl, $hari_pendek) { return $hari_pendek[date('D', strtotime($tgl))]; }
function labelTanggalPanjang($tgl, $bulan_pendek) {
    $parts = explode('-', $tgl);
    return intval($parts[2]) . ' ' . $bulan_pendek[$parts[1]];
}

// ============================================================================
// ROUTER AJAX RESPONSES (API JSON)
// ============================================================================
if ($is_real_ajax) {
    header('Content-Type: application/json');
    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    // --- 1. AMBIL DETAIL JADWAL ---
    if ($action === 'get_detail' || isset($_GET['ajax_get_detail'])) {
        $id = intval($_GET['id'] ?? $_GET['ajax_get_detail'] ?? 0);
        $r = safeQuery($conn, "EXEC SP_Jadwal_Select @ID_Jadwal=?", [$id]);
        if ($r && $data = safeFetch($r)) {
            $data['Tanggal_Formatted'] = formatInputDate($data['Tanggal']);
            $data['Jam_Mulai_Formatted'] = formatInputTime($data['Jam_Mulai']);
            $data['Jam_Selesai_Formatted'] = formatInputTime($data['Jam_Selesai']);
            echo json_encode(['status' => 'success', 'data' => $data]);
        } else {
            echo json_encode(['status' => 'error', 'msg' => 'Data jadwal tidak ditemukan.']);
        }
        exit();
    }

    // --- 2. SIMPAN JADWAL (INSERT / EDIT) ---
    if ($action === 'save' || isset($_POST['is_ajax_save'])) {
        $id          = isset($_POST['id_jadwal']) ? trim($_POST['id_jadwal']) : '';
        $id_lapangan = $_POST['id_lapangan'] ?? '';
        $tanggal     = $_POST['tanggal'] ?? '';
        $jam_mulai   = $_POST['jam_mulai'] ?? '';
        $edit_mode   = isset($_POST['edit_mode']) && $_POST['edit_mode'] == '1';

        if (empty($id_lapangan)) { echo json_encode(['status'=>'error', 'msg'=>'Lapangan wajib dipilih.']); exit(); }
        if (empty($tanggal))     { echo json_encode(['status'=>'error', 'msg'=>'Tanggal wajib diisi.']); exit(); }
        if (empty($jam_mulai))   { echo json_encode(['status'=>'error', 'msg'=>'Jam mulai wajib dipilih.']); exit(); }

        $hari_ini = date('Y-m-d');
        if ($tanggal < $hari_ini) {
            echo json_encode(['status'=>'error', 'msg'=>'Tanggal tidak boleh kurang dari hari ini.']); exit();
        }
        if (!in_array($jam_mulai, daftarJamOperasional(), true)) {
            echo json_encode(['status'=>'error', 'msg'=>'Jam mulai harus jam bulat antara 08:00 - 23:00.']); exit();
        }
        if ($tanggal === $hari_ini) {
            $jam_sekarang = date('H:i');
            if ($jam_mulai <= $jam_sekarang) {
                echo json_encode(['status'=>'error', 'msg'=>"Jam mulai harus lebih besar dari jam sekarang ($jam_sekarang WIB)."]); exit();
            }
        }

        $jam_selesai = hitungJamSelesai($jam_mulai);

        if ($edit_mode) {
            $cek = safeQuery($conn, "SELECT ID_Jadwal, Is_Deleted FROM Jadwal WHERE ID_Lapangan=? AND Tanggal=? AND Jam_Mulai=? AND ID_Jadwal != ?", [$id_lapangan, $tanggal, $jam_mulai, $id]);
            $row = safeFetch($cek);
            
            if ($row) {
                if ($row['Is_Deleted'] == 0) {
                    echo json_encode(['status'=>'error', 'msg'=>'Gagal! Waktu tersebut sudah terisi oleh jadwal lain.']); exit();
                } else {
                    safeQuery($conn, "DELETE FROM Jadwal WHERE ID_Jadwal=?", [$row['ID_Jadwal']]);
                }
            }

            $r = safeQuery($conn, "SET NOCOUNT ON; EXEC SP_Jadwal_Update @ID_Jadwal=?, @ID_Lapangan=?, @Tanggal=?, @Jam_Mulai=?, @Jam_Selesai=?, @Modified_By=?",
                [$id, $id_lapangan, $tanggal, $jam_mulai, $jam_selesai, $nama]);
                
            if ($r === null) {
                echo json_encode(['status'=>'error', 'msg'=>'Gagal memperbarui jadwal. Jadwal mungkin bentrok.']); exit();
            }
            echo json_encode(['status'=>'success', 'msg'=>'Jadwal berhasil diperbarui!']);
            
        } else {
            $cek = safeQuery($conn, "SELECT ID_Jadwal, Is_Deleted FROM Jadwal WHERE ID_Lapangan=? AND Tanggal=? AND Jam_Mulai=?", [$id_lapangan, $tanggal, $jam_mulai]);
            $row = safeFetch($cek);
            
            if ($row) {
                if ($row['Is_Deleted'] == 0) {
                    echo json_encode(['status'=>'error', 'msg'=>'Gagal! Jadwal pada waktu tersebut sudah ada.']); exit();
                } else {
                    $r = safeQuery($conn, "UPDATE Jadwal SET Is_Deleted=0, Status=1, Modified_By=?, Modified_Date=GETDATE() WHERE ID_Jadwal=?", [$nama, $row['ID_Jadwal']]);
                    if ($r) {
                        echo json_encode(['status'=>'success', 'msg'=>"Jadwal $jam_mulai - $jam_selesai berhasil ditambahkan!"]);
                    } else {
                        echo json_encode(['status'=>'error', 'msg'=>'Gagal memulihkan jadwal.']);
                    }
                    exit();
                }
            }

            $r = safeQuery($conn, "SET NOCOUNT ON; EXEC SP_Jadwal_Insert @ID_Lapangan=?, @Tanggal=?, @Jam_Mulai=?, @Jam_Selesai=?, @Status=1, @Created_By=?",
                [$id_lapangan, $tanggal, $jam_mulai, $jam_selesai, $nama]);
                
            if ($r === null) {
                echo json_encode(['status'=>'error', 'msg'=>'Gagal menambahkan jadwal. Jadwal mungkin bentrok.']); exit();
            }
            echo json_encode(['status'=>'success', 'msg'=>"Jadwal $jam_mulai - $jam_selesai berhasil ditambahkan!"]);
        }
        exit();
    }

    // --- 3. GENERATE JADWAL OTOMATIS (BULK INSERT VIA AJAX) ---
    if ($action === 'generate_all') {
        $id_lapangan = $_POST['id_lapangan'] ?? '';
        $tanggal     = $_POST['tanggal'] ?? '';

        if (empty($id_lapangan) || empty($tanggal)) {
            echo json_encode(['status'=>'error', 'msg'=>'Lapangan & tanggal wajib dipilih.']); exit();
        }
        if ($tanggal < date('Y-m-d')) {
            echo json_encode(['status'=>'error', 'msg'=>'Tidak bisa membuat jadwal otomatis untuk tanggal lampau.']); exit();
        }

        $jam_mulai_awal = OPS_JAM_MULAI_AWAL;
        if ($tanggal === date('Y-m-d')) {
            $jam_now = intval(date('H'));
            if ($jam_now >= OPS_JAM_MULAI_AWAL && $jam_now < OPS_JAM_MULAI_AKHIR) {
                $jam_mulai_awal = $jam_now + 1;
            } elseif ($jam_now >= OPS_JAM_MULAI_AKHIR) {
                echo json_encode(['status'=>'error', 'msg'=>'Tidak ada jadwal tersisa untuk hari ini.']); exit();
            }
        }

        $target_dibuat = 0;
        for ($h = $jam_mulai_awal; $h <= OPS_JAM_MULAI_AKHIR; $h++) {
            $jm = sprintf('%02d:00', $h);
            $js = hitungJamSelesai($jm);

            $cek = safeQuery($conn, "SELECT ID_Jadwal, Is_Deleted FROM Jadwal WHERE ID_Lapangan=? AND Tanggal=? AND Jam_Mulai=?", [$id_lapangan, $tanggal, $jm]);
            $row = safeFetch($cek);

            if ($row) {
                if ($row['Is_Deleted'] == 1) {
                    safeQuery($conn, "UPDATE Jadwal SET Is_Deleted=0, Status=1, Modified_By=?, Modified_Date=GETDATE() WHERE ID_Jadwal=?", [$nama, $row['ID_Jadwal']]);
                    $target_dibuat++;
                }
            } else {
                safeQuery($conn, "INSERT INTO Jadwal (ID_Lapangan, Tanggal, Jam_Mulai, Jam_Selesai, Status, Is_Deleted, Created_By, Created_Date) VALUES (?, ?, ?, ?, 1, 0, ?, GETDATE())", 
                    [$id_lapangan, $tanggal, $jm, $js, $nama]);
                $target_dibuat++;
            }
        }

        if ($target_dibuat > 0) {
            echo json_encode(['status'=>'success', 'msg'=>$target_dibuat . ' jadwal baru berhasil dibuat otomatis!']);
        } else {
            echo json_encode(['status'=>'error', 'msg'=>'Semua jadwal sudah terisi, tidak ada yang ditambahkan.']);
        }
        exit();
    }

    // --- 4. TOGGLE STATUS JADWAL ---
    if ($action === 'toggle' || isset($_GET['toggle_id'])) {
        $id = intval($_GET['id'] ?? $_GET['toggle_id'] ?? 0);
        $r = safeQuery($conn, "SET NOCOUNT ON; EXEC SP_Jadwal_ToggleStatus @ID_Jadwal=?, @Modified_By=?", [$id, $nama]);
        if ($r !== null) {
            echo json_encode(['status' => 'success', 'msg' => 'Status jadwal berhasil diubah!']);
        } else {
            echo json_encode(['status' => 'error', 'msg' => 'Gagal mengubah status jadwal.']);
        }
        exit();
    }

    // --- 5. HAPUS JADWAL ---
    if ($action === 'delete' || isset($_GET['delete_id'])) {
        $id = intval($_GET['id'] ?? $_GET['delete_id'] ?? 0);
        $r = safeQuery($conn, "SET NOCOUNT ON; EXEC SP_Jadwal_Delete @ID_Jadwal=?, @Deleted_By=?", [$id, $nama]);
        if ($r !== null) {
            echo json_encode(['status' => 'success', 'msg' => 'Jadwal berhasil dihapus!']);
        } else {
            echo json_encode(['status' => 'error', 'msg' => 'Gagal menghapus jadwal. Pastikan jadwal tidak sedang terbooking.']);
        }
        exit();
    }

    // --- 6. RENDER MAIN PAGE CONTENT (GRID / HISTORY + STATS) ---
    if ($action === 'get_main_content') {
        $view_mode = ($_GET['view'] ?? 'upcoming') === 'history' ? 'history' : 'upcoming';
        $today = date('Y-m-d');

        $selected_date = $_GET['tanggal'] ?? $today;
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selected_date)) $selected_date = $today;
        if ($view_mode === 'upcoming' && $selected_date < $today) $selected_date = $today;

        $filter_lapangan = (isset($_GET['f_lapangan']) && $_GET['f_lapangan'] !== '') ? intval($_GET['f_lapangan']) : null;

        // Ambil Statistik Header
        $aktif_count = 0; $nonaktif_count = 0; $total_upcoming = 0; $total_history = 0;
        $q_stats = safeQuery($conn, "EXEC SP_Jadwal_GetStats", []);
        if ($q_stats && $row = safeFetch($q_stats)) {
            $aktif_count    = intval($row['Aktif']    ?? 0);
            $nonaktif_count = intval($row['Nonaktif'] ?? 0);
            $total_upcoming = intval($row['Upcoming'] ?? 0);
            $total_history  = intval($row['Riwayat']  ?? 0);
        }

        // Render HTML Content
        ob_start();
        ?>
        <!-- HEADER STATS -->
        <div class="page-header">
            <div>
                <div class="page-title-tag"></div>
                <div class="page-title">Kelola Jadwal</div>
            </div>
            <div class="stat-chips">
                <div class="stat-chip chip-green"><i class="fa-solid fa-circle-check"></i> AKTIF <span class="chip-val"><?= $aktif_count ?></span></div>
                <div class="stat-chip chip-red"><i class="fa-solid fa-circle-xmark"></i> NONAKTIF <span class="chip-val"><?= $nonaktif_count ?></span></div>
                <div class="stat-chip chip-blue"><i class="fa-solid fa-calendar-days"></i> UPCOMING <span class="chip-val"><?= $total_upcoming ?></span></div>
            </div>
        </div>

        <!-- TAB VIEW MODE -->
        <div class="jd-tabs">
            <button type="button" onclick="switchViewMode('upcoming')" class="jd-tab <?= $view_mode === 'upcoming' ? 'active' : '' ?>">
                <i class="fa-solid fa-calendar-plus"></i> Jadwal Aktif
                <span class="jd-tab-count"><?= $total_upcoming ?></span>
            </button>
            <button type="button" onclick="switchViewMode('history')" class="jd-tab <?= $view_mode === 'history' ? 'active' : '' ?>">
                <i class="fa-solid fa-clock-rotate-left"></i> Riwayat Jadwal
                <span class="jd-tab-count"><?= $total_history ?></span>
            </button>
            <div class="jd-tab-filler"></div>
            <div class="jd-lapangan-filter">
                <div class="jd-inline-form">
                    <label>Lapangan</label>
                    <select name="f_lapangan" id="f_lapangan_select" onchange="changeLapanganFilter(this.value)" class="jd-select">
                        <option value="">Semua Lapangan</option>
                        <?php foreach ($lapangan_list as $lap): ?>
                            <option value="<?= $lap['ID_Lapangan'] ?>" <?= $filter_lapangan == $lap['ID_Lapangan'] ? 'selected' : '' ?>><?= htmlspecialchars($lap['Nama_Lapangan']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <?php if ($view_mode === 'upcoming'): 
            // Ambil Slot Grid Upcoming
            $slots_by_lap = [];  
            $booking_flags = []; 
            $q_grid = safeQuery($conn, "EXEC SP_Jadwal_SelectAll @Tanggal=?, @ID_Lapangan=?, @Is_Deleted=0", [$selected_date, $filter_lapangan]);
            if ($q_grid) {
                $ids = [];
                while ($r = sqlsrv_fetch_array($q_grid, SQLSRV_FETCH_ASSOC)) {
                    $jamStr = (new DateTime($r['Jam_Mulai']->format('H:i:s')))->format('H:i');
                    $r['JamMulaiStr'] = $jamStr;
                    $slots_by_lap[$r['ID_Lapangan']][$jamStr] = $r;
                    $ids[] = $r['ID_Jadwal'];
                }
                if (!empty($ids)) {
                    $placeholders = implode(',', array_fill(0, count($ids), '?'));
                    $q_bk = safeQuery($conn, "SELECT ID_Jadwal FROM Booking WHERE ID_Jadwal IN ($placeholders) AND Status IN (0,1)", $ids);
                    if ($q_bk) {
                        while ($rb = sqlsrv_fetch_array($q_bk, SQLSRV_FETCH_ASSOC)) {
                            $booking_flags[$rb['ID_Jadwal']] = true;
                        }
                    }
                }
            }

            // Calculations for Date Scroller
            $scroller_start = new DateTime($selected_date);
            $scroller_start->modify('-3 days');
            if ($scroller_start < new DateTime($today)) $scroller_start = new DateTime($today);
            $scroller_dates = [];
            for ($i = 0; $i < 7; $i++) {
                $d = clone $scroller_start; $d->modify("+$i days");
                $scroller_dates[] = $d->format('Y-m-d');
            }
            $prev_date = (new DateTime($scroller_dates[0]))->modify('-1 day')->format('Y-m-d');
            if ($prev_date < $today) $prev_date = $today;
            $next_date = (new DateTime(end($scroller_dates)))->modify('+1 day')->format('Y-m-d');
        ?>
            <!-- DATE SCROLLER -->
            <div class="date-scroller">
                <button type="button" onclick="selectDate('<?= $prev_date ?>')" class="ds-arrow" title="Sebelumnya"><i class="fa-solid fa-angle-left"></i></button>
                <div class="ds-days">
                    <?php foreach ($scroller_dates as $d):
                        $is_today = ($d === $today);
                        $is_selected = ($d === $selected_date);
                    ?>
                        <button type="button" onclick="selectDate('<?= $d ?>')"
                           class="ds-day <?= $is_selected ? 'selected' : '' ?> <?= $is_today ? 'is-today' : '' ?>">
                            <div class="ds-hari"><?= labelHariID($d, $hari_pendek) ?><?= $is_today ? ' • Hari ini' : '' ?></div>
                            <div class="ds-tgl"><?= labelTanggalPanjang($d, $bulan_pendek) ?></div>
                        </button>
                    <?php endforeach; ?>
                </div>
                <button type="button" onclick="selectDate('<?= $next_date ?>')" class="ds-arrow" title="Selanjutnya"><i class="fa-solid fa-angle-right"></i></button>
                <div class="ds-picker">
                    <input type="date" value="<?= htmlspecialchars($selected_date) ?>" min="<?= $today ?>" onchange="selectDate(this.value)" title="Pilih tanggal">
                </div>
            </div>

            <!-- LAPANGAN SLOT GRIDS -->
            <?php
            $lapangan_shown = $filter_lapangan
                ? array_values(array_filter($lapangan_list, fn($l) => $l['ID_Lapangan'] == $filter_lapangan))
                : $lapangan_list;

            if (empty($lapangan_shown)): ?>
                <div class="jd-empty-big">
                    <i class="fa-solid fa-layer-group"></i>
                    <div>Belum ada lapangan aktif</div>
                    <p>Tambah lapangan dulu di menu "Kelola Lapangan" sebelum bikin jadwal.</p>
                </div>
            <?php else:
                foreach ($lapangan_shown as $lap):
                    $lap_id = $lap['ID_Lapangan'];
                    $slots_lap = $slots_by_lap[$lap_id] ?? [];
                    $slot_aktif_count = 0;
                    foreach ($slots_lap as $s) if ($s['Status'] == 1) $slot_aktif_count++;
                    $all_hours = daftarJamOperasional();
                    $missing_count = 0;
                    foreach ($all_hours as $jm) if (!isset($slots_lap[$jm])) $missing_count++;
                    $sekarang = date('H:i');
                    $is_today_view = ($selected_date === $today);
            ?>
                <div class="lap-block">
                    <div class="lap-block-head">
                        <div>
                            <div class="lap-name"><i class="fa-solid fa-basketball"></i> <?= htmlspecialchars($lap['Nama_Lapangan']) ?></div>
                            <div class="lap-sub">
                                <span><i class="fa-solid fa-circle-check" style="color:var(--green);"></i> <?= $slot_aktif_count ?> jadwal tersedia</span>
                                <span class="lap-sub-sep">•</span>
                                <span><i class="fa-solid fa-money-bill" style="color:var(--orange);"></i> Rp <?= number_format($lap['Harga_Sewa'], 0, ',', '.') ?> / jam</span>
                            </div>
                        </div>
                        <?php if ($missing_count > 0): ?>
                        <button type="button" class="btn-generate" onclick="confirmGenerate(<?= $lap_id ?>, '<?= htmlspecialchars($lap['Nama_Lapangan'], ENT_QUOTES) ?>', '<?= htmlspecialchars($selected_date) ?>', <?= $missing_count ?>)">
                            <i class="fa-solid fa-wand-magic-sparkles"></i> Buat Otomatis <?= $missing_count ?> Jadwal
                        </button>
                        <?php endif; ?>
                    </div>

                    <div class="slot-grid">
                        <?php foreach ($all_hours as $jm):
                            $slot = $slots_lap[$jm] ?? null;
                            $jam_selesai_lbl = hitungJamSelesai($jm);
                            $sudah_lewat = ($is_today_view && $jm <= $sekarang);
                            if ($slot):
                                $is_aktif = ($slot['Status'] == 1);
                                $is_booked = isset($booking_flags[$slot['ID_Jadwal']]);
                                $slot_cls = $is_booked ? 'slot-booked' : ($is_aktif ? 'slot-aktif' : 'slot-nonaktif');
                                if ($sudah_lewat && !$is_booked) $slot_cls .= ' slot-past';
                        ?>
                            <div class="slot-card <?= $slot_cls ?>">
                                <div class="slot-time"><?= $jm ?> <span class="slot-dash">–</span> <?= $jam_selesai_lbl ?></div>
                                <div class="slot-status">
                                    <?php if ($is_booked): ?>
                                        <span class="slot-badge sb-booked"><i class="fa-solid fa-lock"></i> Terpesan</span>
                                    <?php elseif ($is_aktif): ?>
                                        <span class="slot-badge sb-aktif"><span class="sb-dot"></span> Tersedia</span>
                                    <?php else: ?>
                                        <span class="slot-badge sb-nonaktif"><span class="sb-dot"></span> Nonaktif</span>
                                    <?php endif; ?>
                                </div>
                                <div class="slot-actions">
                                    <?php if (!$is_booked): ?>
                                        <label class="toggle-switch" title="<?= $is_aktif ? 'Nonaktifkan' : 'Aktifkan' ?>">
                                            <input type="checkbox" <?= $is_aktif ? 'checked' : '' ?> onchange="confirmToggle('<?= $slot['ID_Jadwal'] ?>', <?= $slot['Status'] ?>, event)">
                                            <span class="toggle-slider"></span>
                                        </label>
                                        <button type="button" class="btn-action btn-view" onclick="openDetailModal('<?= $slot['ID_Jadwal'] ?>')" title="Detail"><i class="fa-solid fa-eye"></i></button>
                                        <button type="button" class="btn-action btn-edit" onclick="openEditModal('<?= $slot['ID_Jadwal'] ?>')" title="Edit"><i class="fa-solid fa-pen-to-square"></i></button>
                                        <button type="button" onclick="confirmDelete('<?= $slot['ID_Jadwal'] ?>', '<?= htmlspecialchars($lap['Nama_Lapangan'], ENT_QUOTES) ?>', '<?= $jm ?>')" class="btn-action btn-delete" title="Hapus"><i class="fa-solid fa-trash-can"></i></button>
                                    <?php else: ?>
                                        <button type="button" class="btn-action btn-view" onclick="openDetailModal('<?= $slot['ID_Jadwal'] ?>')" title="Detail"><i class="fa-solid fa-eye"></i></button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php else:
                            $can_create = !$sudah_lewat;
                        ?>
                            <?php if ($can_create): ?>
                            <div class="slot-card slot-empty" onclick="openAddModal('<?= $lap_id ?>', '<?= $jm ?>', '<?= $selected_date ?>')">
                                <div class="slot-time"><?= $jm ?> <span class="slot-dash">–</span> <?= $jam_selesai_lbl ?></div>
                                <div class="slot-empty-cta"><i class="fa-solid fa-plus"></i> Buat Jadwal</div>
                            </div>
                            <?php else: ?>
                            <div class="slot-card slot-empty slot-past">
                                <div class="slot-time"><?= $jm ?> <span class="slot-dash">–</span> <?= $jam_selesai_lbl ?></div>
                                <div class="slot-empty-cta" style="color:#B0B7C3;"><i class="fa-solid fa-clock-rotate-left"></i> Lewat</div>
                            </div>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; endif; ?>

        <?php else: 
            // MODE HISTORY TABEL
            $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
            $hist_limit = 20;
            $q_h_all = safeQuery($conn, "EXEC SP_Jadwal_SelectAll @ID_Lapangan=?, @Tanggal_Sampai=?, @Is_Deleted=0", [$filter_lapangan, date('Y-m-d', strtotime('-1 day'))]);
            $all_hist = [];
            if ($q_h_all) {
                while ($r = sqlsrv_fetch_array($q_h_all, SQLSRV_FETCH_ASSOC)) $all_hist[] = $r;
            }
            $hist_total = count($all_hist);
            $hist_total_pages = max(1, (int)ceil($hist_total / $hist_limit));
            $page = min($page, $hist_total_pages);
            $offset = ($page - 1) * $hist_limit;
            $history_rows = array_slice($all_hist, $offset, $hist_limit);
        ?>
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fa-solid fa-clock-rotate-left"></i> Riwayat Jadwal</div>
                    <span class="card-badge"><?= $hist_total ?> total</span>
                </div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width: 80px;">No</th>
                                <th>Lapangan</th>
                                <th style="width: 180px;">Tanggal</th>
                                <th style="width: 200px;">Waktu (Jam)</th>
                                <th style="width: 150px;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!empty($history_rows)):
                            $no = $offset + 1;
                            foreach ($history_rows as $row):
                        ?>
                            <tr>
                                <td class="row-num"><?= $no++ ?></td>
                                <td><div class="jadwal-lapangan"><?= htmlspecialchars($row['Nama_Lapangan']) ?></div></td>
                                <td><?= formatInputDate($row['Tanggal']) ?></td>
                                <td><div class="jadwal-waktu"><?= formatInputTime($row['Jam_Mulai']) ?> - <?= formatInputTime($row['Jam_Selesai']) ?> WIB</div></td>
                                <td>
                                    <span class="status-pill <?= $row['Status'] == 1 ? 'sp-active' : 'sp-inactive' ?>">
                                        <span class="sp-dot"></span>
                                        <?= $row['Status'] == 1 ? 'AKTIF' : 'NONAKTIF' ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="5"><div class="empty-state"><i class="fa-solid fa-clock-rotate-left"></i><div>Belum ada riwayat jadwal</div></div></td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if ($hist_total_pages > 1): ?>
            <div class="pagination-wrap">
                <div class="pagination-info">Menampilkan <strong><?= (($page - 1) * $hist_limit) + 1 ?></strong> - <strong><?= min($page * $hist_limit, $hist_total) ?></strong> dari <strong><?= $hist_total ?></strong> riwayat</div>
                <div class="pagination-nav">
                    <button type="button" onclick="changeHistoryPage(1)" class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>"><i class="fa-solid fa-angles-left"></i></button>
                    <button type="button" onclick="changeHistoryPage(<?= $page - 1 ?>)" class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>"><i class="fa-solid fa-angle-left"></i></button>
                    <?php for ($i = max(1, $page - 2); $i <= min($hist_total_pages, $page + 2); $i++): ?>
                        <button type="button" onclick="changeHistoryPage(<?= $i ?>)" class="page-btn <?= $i == $page ? 'active' : '' ?>"><?= $i ?></button>
                    <?php endfor; ?>
                    <button type="button" onclick="changeHistoryPage(<?= $page + 1 ?>)" class="page-btn <?= $page >= $hist_total_pages ? 'disabled' : '' ?>"><i class="fa-solid fa-angle-right"></i></button>
                    <button type="button" onclick="changeHistoryPage(<?= $hist_total_pages ?>)" class="page-btn <?= $page >= $hist_total_pages ? 'disabled' : '' ?>"><i class="fa-solid fa-angles-right"></i></button>
                </div>
            </div>
            <?php endif; ?>
        <?php endif; ?>
        <?php
        $html = ob_get_clean();

        echo json_encode([
            'status' => 'success',
            'html'   => $html
        ]);
        exit();
    }
}

$today = date('Y-m-d');
$current_page = 'jadwal';
$sidebar_folder = 'master';
$sidebar_photo = $profile_photo;
$topbar_title = 'Kelola Jadwal';
$topbar_breadcrumb = 'Operasional / Jadwal';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<?php include '../includes/favicon.php'; ?>
<title>Kelola Jadwal | HoopBall</title>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800;900&family=Barlow:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../asset/css/global.css">
<link rel="stylesheet" href="../asset/css/responsive_tipe_member.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
:root {
    --orange: #FF4500; --orange-lt: rgba(255,69,0,.10); --orange-dk: #E03E00;
    --green: #10B981; --green-lt: rgba(16,185,129,.10);
    --blue: #3B82F6; --blue-lt: rgba(59,130,246,.10);
    --red: #EF4444; --red-lt: rgba(239,68,68,.10);
    --sidebar: #0D1117; --sidebar-w: 260px; --topbar-h: 70px;
    --card-bg: #FFFFFF; --border: #E5E7EB; --border-lt: #F3F4F6;
    --text: #111827; --text-md: #374151; --muted: #6B7280; --bg: #F3F4F6;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }
body { font-family: 'Barlow', sans-serif; background: var(--bg); display: flex; min-height: 100vh; color: var(--text); }

.sidebar { width: var(--sidebar-w); background: var(--sidebar); height: 100vh; position: fixed; top: 0; left: 0; display: flex; flex-direction: column; padding: 28px 18px; border-right: 1px solid rgba(255,255,255,.04); z-index: 200; overflow-y: auto; scrollbar-width: none; -ms-overflow-style: none; }
.sidebar::-webkit-scrollbar { display: none; }

.main { margin-left: calc(var(--sidebar-w) - 1px); flex: 1; display: flex; flex-direction: column; min-height: 100vh; }
.content { padding: 32px 40px; flex: 1; }
.page-header { display: flex; align-items: flex-end; justify-content: space-between; margin-bottom: 24px; flex-wrap: wrap; gap: 16px; }
.page-title-tag { width: 36px; height: 4px; background: var(--orange); border-radius: 2px; margin-bottom: 8px; }
.page-title { font-family: 'Barlow Condensed', sans-serif; font-size: 30px; font-weight: 900; color: var(--text); text-transform: uppercase; }

.stat-chips { display: flex; gap: 10px; flex-wrap: wrap; }
.stat-chip { display: flex; align-items: center; gap: 8px; padding: 8px 18px; border-radius: 10px; font-size: 12px; font-weight: 700; transition: all .2s; }
.stat-chip:hover { transform: translateY(-2px); }
.chip-green { background: var(--green-lt); color: var(--green); }
.chip-red { background: var(--red-lt); color: var(--red); }
.chip-blue { background: var(--blue-lt); color: var(--blue); }
.chip-val { font-family: 'Barlow Condensed'; font-size: 20px; font-weight: 900; }

.card { background: var(--card-bg); border-radius: 16px; border: 1px solid var(--border); overflow: hidden; transition: all .2s ease; background-color: #FFFFFF !important; }
.main, .content { background-color: #F3F4F6 !important; }
.card:hover { box-shadow: 0 8px 24px rgba(0,0,0,.06); }
.card-header { padding: 20px 24px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
.card-title { font-size: 15px; font-weight: 800; color: var(--text); display: flex; align-items: center; gap: 8px; }
.card-title i { color: var(--orange); font-size: 14px; }
.card-badge { background: var(--orange-lt); color: var(--orange); font-size: 11px; font-weight: 800; padding: 4px 10px; border-radius: 20px; }

.table-wrap { overflow-x: auto; scrollbar-width: none; -ms-overflow-style: none; }
.table-wrap::-webkit-scrollbar { display: none; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table th { font-family: 'Barlow Condensed', sans-serif !important; font-size: 13px !important; font-weight: 900 !important; color: #FFFFFF !important; text-transform: uppercase !important; letter-spacing: 0.8px !important; padding: 14px 20px; border-bottom: 2px solid var(--border-lt); background: #ff6f00 !important; }
.data-table td { padding: 14px 16px; vertical-align: middle; font-size: 13px; text-align: center; }
.data-table tbody tr { height: 68px; }
.jadwal-lapangan { font-weight: 700; color: var(--text); font-size: 14px; }
.jadwal-waktu { font-family: 'Barlow Condensed', sans-serif; font-weight: 800; font-size: 14px; color: var(--orange); }
.row-num { font-family: 'Barlow', sans-serif; font-weight: 800; color: var(--text); font-size: 14px; }

.status-pill { display: inline-flex; align-items: center; gap: 6px; padding: 7px 16px; border-radius: 20px; font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: .3px; }
.sp-active { background: var(--green-lt); color: var(--green); }
.sp-inactive { background: var(--red-lt); color: var(--red); }
.sp-dot { width: 7px; height: 7px; border-radius: 50%; display: inline-block; }
.sp-active .sp-dot { background: var(--green); }
.sp-inactive .sp-dot { background: var(--red); }

.actions { display: flex; gap: 8px; justify-content: center; align-items: center; }
.btn-action { width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; font-size: 13px; font-weight: 700; transition: all .25s cubic-bezier(.4,0,.2,1); border: 1.5px solid transparent; cursor: pointer; }
.btn-view { background: linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%); color: #1E40AF; border-color: #BFDBFE; }
.btn-view:hover { background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%); color: #fff; border-color: #3B82F6; transform: translateY(-2px); box-shadow: 0 6px 20px rgba(59,130,246,.35); }
.btn-edit { background: linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%); color: #1E40AF; border-color: #BFDBFE; }
.btn-edit:hover { background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%); color: #fff; border-color: #3B82F6; transform: translateY(-2px); box-shadow: 0 6px 20px rgba(59,130,246,.35); }
.btn-delete { background: linear-gradient(135deg, #FEF2F2 0%, #FEE2E2 100%); color: #DC2626; border-color: #FECACA; }
.btn-delete:hover { background: linear-gradient(135deg, #EF4444 0%, #DC2626 100%); color: #fff; border-color: #EF4444; transform: translateY(-2px); box-shadow: 0 6px 20px rgba(239,68,68,.35); }

.toggle-switch { position: relative; display: inline-flex; align-items: center; width: 44px; height: 24px; cursor: pointer; margin: 0; }
.toggle-switch input { opacity: 0; width: 0; height: 0; }
.toggle-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: var(--red); transition: .3s; border-radius: 24px; }
.toggle-slider::before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .3s; border-radius: 50%; box-shadow: 0 2px 4px rgba(0,0,0,.2); }
.toggle-switch input:checked + .toggle-slider { background-color: var(--green); }
.toggle-switch input:checked + .toggle-slider::before { transform: translateX(20px); }
.toggle-switch:hover .toggle-slider { opacity: .9; }

.data-table tbody tr:nth-child(odd) { background-color: #FFF7ED; }
.data-table tbody tr:nth-child(even) { background-color: #FFFFFF; }
.data-table tbody tr:hover td { background-color: #FFEDD5 !important; }

/* ========= TAB VIEW ========= */
.jd-tabs { display: flex; align-items: center; gap: 8px; margin-bottom: 20px; background: var(--card-bg); border: 1px solid var(--border); border-radius: 14px; padding: 8px; flex-wrap: wrap; }
.jd-tab { display: inline-flex; align-items: center; gap: 8px; padding: 10px 16px; border-radius: 10px; text-decoration: none; color: var(--muted); font-size: 13px; font-weight: 700; transition: all .2s; border:none; background:transparent; cursor:pointer; font-family:'Barlow',sans-serif; }
.jd-tab:hover { color: var(--text); background: var(--bg); }
.jd-tab.active { background: var(--orange); color: #fff; box-shadow: 0 4px 12px rgba(255,69,0,.25); }
.jd-tab-count { background: rgba(255,255,255,.28); color: inherit; padding: 2px 8px; border-radius: 20px; font-size: 11px; font-family: 'Barlow Condensed'; font-weight: 800; }
.jd-tab:not(.active) .jd-tab-count { background: var(--bg); color: var(--muted); }
.jd-tab-filler { flex: 1; }
.jd-inline-form { display: flex; align-items: center; gap: 10px; }
.jd-inline-form label { font-size: 11px; font-weight: 800; color: var(--muted); text-transform: uppercase; letter-spacing: .4px; }
.jd-select { padding: 8px 32px 8px 12px; border: 1.5px solid var(--border); border-radius: 10px; background: #fff; font-size: 13px; font-family: 'Barlow'; font-weight: 700; color: var(--text); cursor: pointer; outline: none; appearance: none; background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%236B7280' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'/%3e%3c/svg%3e"); background-repeat: no-repeat; background-position: right 10px center; background-size: 12px; }
.jd-select:focus { border-color: var(--orange); box-shadow: 0 0 0 3px var(--orange-lt); }

/* ========= DATE SCROLLER ========= */
.date-scroller { display: flex; align-items: stretch; gap: 8px; background: var(--card-bg); border: 1px solid var(--border); border-radius: 14px; padding: 10px; margin-bottom: 20px; }
.ds-arrow { display: flex; align-items: center; justify-content: center; width: 40px; border-radius: 10px; background: var(--bg); border: 1px solid var(--border); color: var(--text-md); text-decoration: none; transition: all .2s; flex-shrink: 0; cursor:pointer; }
.ds-arrow:hover { background: var(--orange-lt); color: var(--orange); border-color: var(--orange); }
.ds-days { display: grid; grid-template-columns: repeat(7, 1fr); gap: 6px; flex: 1; }
.ds-day { text-align: center; padding: 10px 6px; border-radius: 10px; text-decoration: none; color: var(--text-md); transition: all .2s; border: 1.5px solid transparent; cursor: pointer; background:transparent; font-family:'Barlow',sans-serif; }
.ds-day:hover { background: var(--bg); }
.ds-day.selected { background: var(--orange); color: #fff; border-color: var(--orange); box-shadow: 0 4px 14px rgba(255,69,0,.28); }
.ds-day.is-today:not(.selected) { border-color: var(--orange-lt); background: var(--orange-lt); color: var(--orange); }
.ds-hari { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; opacity: .8; }
.ds-tgl { font-family: 'Barlow Condensed'; font-size: 20px; font-weight: 900; margin-top: 2px; letter-spacing: -.5px; }
.ds-picker { display: flex; align-items: center; }
.ds-picker input[type="date"] { padding: 10px 12px; border: 1.5px solid var(--border); border-radius: 10px; background: #fff; font-size: 12px; font-family: 'Barlow'; font-weight: 700; color: var(--text); outline: none; cursor: pointer; }
.ds-picker input[type="date"]:focus { border-color: var(--orange); box-shadow: 0 0 0 3px var(--orange-lt); }

/* ========= LAPANGAN BLOCK ========= */
.lap-block { background: var(--card-bg); border: 1px solid var(--border); border-radius: 16px; padding: 22px; margin-bottom: 20px; box-shadow: 0 1px 2px rgba(0,0,0,.02); }
.lap-block-head { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding-bottom: 16px; margin-bottom: 16px; border-bottom: 1.5px dashed var(--border); flex-wrap: wrap; }
.lap-name { font-family: 'Barlow Condensed', sans-serif; font-size: 22px; font-weight: 900; color: var(--text); letter-spacing: -.3px; display: flex; align-items: center; gap: 10px; }
.lap-name i { color: var(--orange); font-size: 18px; }
.lap-sub { display: flex; align-items: center; gap: 8px; font-size: 12px; color: var(--muted); font-weight: 600; margin-top: 4px; }
.lap-sub i { margin-right: 4px; }
.lap-sub-sep { color: var(--border); }
.btn-generate { display: inline-flex; align-items: center; gap: 8px; padding: 10px 16px; border-radius: 10px; background: linear-gradient(135deg, var(--orange), var(--orange-dk)); color: #fff; border: none; font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: .3px; cursor: pointer; transition: all .2s; box-shadow: 0 4px 12px rgba(255,69,0,.25); font-family: 'Barlow'; }
.btn-generate:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(255,69,0,.35); }
.btn-generate i { font-size: 13px; }

/* ========= SLOT GRID ========= */
.slot-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 10px; }
.slot-card { display: flex; flex-direction: column; gap: 8px; padding: 12px 14px; border-radius: 12px; border: 1.5px solid var(--border); background: #fff; transition: all .2s; position: relative; text-decoration: none; cursor: default; }
.slot-card .slot-time { font-family: 'Barlow Condensed', sans-serif; font-size: 18px; font-weight: 900; color: var(--text); letter-spacing: -.3px; }
.slot-card .slot-dash { color: var(--muted); font-weight: 700; margin: 0 4px; }
.slot-badge { display: inline-flex; align-items: center; gap: 6px; padding: 3px 10px; border-radius: 20px; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .3px; }
.sb-dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
.sb-aktif { background: var(--green-lt); color: var(--green); }
.sb-nonaktif { background: var(--red-lt); color: var(--red); }
.sb-booked { background: rgba(107,114,128,.15); color: #4B5563; }
.slot-actions { display: flex; align-items: center; gap: 8px; margin-top: auto; padding-top: 6px; border-top: 1px dashed var(--border-lt); }
.slot-actions .btn-action { width: 28px; height: 28px; font-size: 11px; }
.slot-aktif { border-color: rgba(16,185,129,.35); background: linear-gradient(180deg, rgba(16,185,129,.04), #fff 60%); }
.slot-aktif:hover { border-color: var(--green); box-shadow: 0 4px 14px rgba(16,185,129,.15); transform: translateY(-1px); }
.slot-nonaktif { border-color: rgba(239,68,68,.25); background: linear-gradient(180deg, rgba(239,68,68,.04), #fff 60%); }
.slot-nonaktif:hover { border-color: var(--red); }
.slot-booked { border-color: rgba(107,114,128,.25); background: linear-gradient(180deg, rgba(107,114,128,.06), #fff 60%); }
.slot-past { opacity: .55; }
.slot-past .slot-actions { pointer-events: none; }
.slot-empty { border-style: dashed; background: var(--bg); color: var(--muted); align-items: center; justify-content: center; text-align: center; min-height: 96px; cursor: pointer; }
.slot-empty:hover { border-color: var(--orange); background: var(--orange-lt); color: var(--orange); }
.slot-empty .slot-time { color: inherit; opacity: .9; }
.slot-empty-cta { font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: .3px; }
.slot-empty-cta i { margin-right: 4px; }

.jd-empty-big { text-align: center; padding: 60px 20px; background: var(--card-bg); border: 1px dashed var(--border); border-radius: 16px; color: var(--muted); }
.jd-empty-big i { font-size: 48px; color: var(--border); margin-bottom: 12px; display: block; }
.jd-empty-big div { font-size: 16px; font-weight: 700; color: var(--text); }
.jd-empty-big p { font-size: 13px; margin-top: 6px; }

select.modal-input { cursor: pointer; appearance: none; background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%236B7280' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'/%3e%3c/svg%3e"); background-repeat: no-repeat; background-position: right 14px center; background-size: 14px; padding-right: 40px; }

.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.55); backdrop-filter: blur(6px); display: none; align-items: center; justify-content: center; z-index: 2000; }
.modal-overlay.open { display: flex; }
.modal-box { background: #fff; border-radius: 20px; width: 480px; overflow: hidden; box-shadow: 0 25px 60px rgba(0,0,0,.2); position: relative; }
.modal-header { padding: 28px 32px 20px; border-bottom: 1px solid var(--border); }
.modal-subtitle { font-size: 10px; font-weight: 800; color: var(--orange); text-transform: uppercase; margin-bottom: 6px; letter-spacing: .8px; }
.modal-title { font-family: 'Barlow Condensed', sans-serif; font-size: 22px; font-weight: 900; color: var(--text); }
.modal-body { padding: 24px 32px 32px; }
.modal-label { display: block; font-size: 11px; font-weight: 800; color: var(--muted); text-transform: uppercase; letter-spacing: .5px; margin-bottom: 8px; }
.modal-label .required { color: var(--red); margin-left: 2px; font-size: 14px; font-weight: 900; }
.modal-input { width: 100%; padding: 12px 14px; border: 1.5px solid var(--border); border-radius: 10px; font-size: 13px; font-family: 'Barlow', sans-serif; margin-bottom: 16px; outline: none; transition: all .2s; color: var(--text); }
.modal-input:focus { border-color: var(--orange); box-shadow: 0 0 0 3px var(--orange-lt); }
.modal-input::placeholder { color: #9CA3AF; }
.modal-input.error { border-color: var(--red); box-shadow: 0 0 0 3px var(--red-lt); }

.btn-submit { width: 100%; background: var(--orange); color: #fff; border: none; padding: 14px; border-radius: 10px; font-weight: 800; font-size: 13px; cursor: pointer; transition: all .2s; text-transform: uppercase; letter-spacing: .5px; display: flex; align-items: center; justify-content: center; gap: 8px; }
.btn-submit:hover { background: var(--orange-dk); transform: translateY(-1px); box-shadow: 0 8px 20px rgba(255,69,0,.3); }
.btn-cancel { display: block; text-align: center; margin-top: 16px; color: var(--muted); text-decoration: none; font-size: 13px; font-weight: 700; transition: .2s; cursor: pointer; }
.btn-cancel:hover { color: var(--orange); }
.modal-close { position: absolute; top: 20px; right: 20px; width: 36px; height: 36px; border: none; background: var(--border-lt); border-radius: 10px; cursor: pointer; display: flex; align-items: center; justify-content: center; color: var(--muted); font-size: 16px; transition: all .2s; }
.modal-close:hover { background: var(--red-lt); color: var(--red); }

.val-msg { font-size: 11px; color: var(--red); font-weight: 600; margin-bottom: 10px; display: none; min-height: 16px; }
.val-msg.show { display: block; }
.val-msg i { margin-right: 4px; }

.durasi-info-box { display: flex; align-items: center; gap: 8px; padding: 10px 14px; background: var(--green-lt); border: 1px solid rgba(16,185,129,.2); border-radius: 10px; margin-bottom: 16px; font-size: 12px; font-weight: 700; color: var(--green); }
.durasi-info-box i { font-size: 14px; }

.pagination-wrap { background: var(--card-bg); border: 1px solid var(--border); border-top: none; border-radius: 0 0 16px 16px; padding: 16px 24px; display: flex; align-items: center; justify-content: space-between; margin-bottom: 32px; }
.pagination-info { font-size: 12px; color: var(--muted); font-weight: 600; }
.pagination-info strong { color: var(--text); font-weight: 800; }
.pagination-nav { display: flex; align-items: center; gap: 4px; }
.page-btn { display: inline-flex; align-items: center; justify-content: center; min-width: 36px; height: 36px; padding: 0 10px; border-radius: 10px; font-size: 13px; font-weight: 700; font-family: 'Barlow', sans-serif; text-decoration: none; cursor: pointer; transition: all .2s ease; border: 1.5px solid var(--border); color: var(--text-md); background: #fff; }
.page-btn:hover:not(.disabled):not(.active) { border-color: var(--orange); color: var(--orange); background: var(--orange-lt); transform: translateY(-1px); }
.page-btn.active { background: var(--orange); color: #fff; border-color: var(--orange); box-shadow: 0 4px 12px rgba(255,69,0,.3); font-weight: 800; }
.page-btn.disabled { opacity: 0.4; cursor: not-allowed; pointer-events: none; }
.page-btn i { font-size: 11px; }

.empty-state { text-align: center; padding: 50px 20px; color: var(--muted); }
.empty-state i { font-size: 48px; margin-bottom: 16px; opacity: .3; display: block; }
.empty-state div { font-size: 14px; font-weight: 700; }

.detail-icon-wrap { width: 80px; height: 80px; background: var(--orange-lt); color: var(--orange); border-radius: 20px; display: inline-flex; align-items: center; justify-content: center; font-size: 32px; margin-bottom: 16px; box-shadow: 0 8px 20px rgba(255,69,0,0.15); }
.detail-main-name { font-family: 'Barlow Condensed', sans-serif; font-size: 24px; font-weight: 900; color: var(--text); text-transform: uppercase; }
.info-row { display: flex; justify-content: space-between; align-items: center; padding: 14px 0; border-bottom: 1px solid var(--border-lt); }
.info-row:last-child { border-bottom: none; }
.info-key { display: flex; align-items: center; gap: 10px; font-size: 13px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: 0.3px; }
.info-key i { color: var(--orange); font-size: 14px; width: 18px; text-align: center; }
.info-val { font-size: 14px; font-weight: 700; color: var(--text); }

html, body { scrollbar-width: none; -ms-overflow-style: none; }
html::-webkit-scrollbar, body::-webkit-scrollbar { display: none; }

.swal2-container { z-index: 99999 !important; }
.swal2-popup { font-family: 'Barlow', sans-serif !important; }
.swal2-title { font-family: 'Barlow Condensed', sans-serif !important; font-size: 26px !important; }

@media(max-width: 768px) {
    .sidebar { width: 0; overflow: hidden; padding: 0; }
    .main { margin-left: 0; }
    .content { padding: 20px; }
    .modal-box { width: 90%; margin: 20px; }
}
</style>
</head>
<body>

<!-- MODAL FORM JADWAL -->
<div class="modal-overlay" id="modalJadwal">
    <div class="modal-box">
        <button type="button" class="modal-close" onclick="closeModal('modalJadwal')"><i class="fa-solid fa-xmark"></i></button>
        <div class="modal-header">
            <div class="modal-subtitle">Kelola Jadwal</div>
            <div class="modal-title" id="formModalTitle">Tambah Jadwal Baru</div>
        </div>
        <div class="modal-body">
            <form id="formJadwal" onsubmit="return submitJadwalAjax(event)" novalidate>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="edit_mode" id="edit_mode" value="0">
                <input type="hidden" name="id_jadwal" id="id_jadwal" value="">

                <label class="modal-label">Lapangan <span class="required">*</span></label>
                <select name="id_lapangan" id="id_lapangan" class="modal-input" required>
                    <option value="">Pilih Lapangan</option>
                    <?php foreach ($lapangan_list as $lap): ?>
                        <option value="<?= $lap['ID_Lapangan'] ?>"><?= htmlspecialchars($lap['Nama_Lapangan']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="val-msg" id="val-id_lapangan"></div>

                <label class="modal-label">Tanggal <span class="required">*</span></label>
                <input type="date" name="tanggal" id="tanggal" class="modal-input" min="<?= $today ?>" required>
                <div class="val-msg" id="val-tanggal"></div>

                <label class="modal-label">Jam Mulai <span class="required">*</span></label>
                <select name="jam_mulai" id="jam_mulai" class="modal-input" required>
                    <option value="">Pilih jam mulai</option>
                    <?php foreach (daftarJamOperasional() as $jm): $jsel = hitungJamSelesai($jm); ?>
                        <option value="<?= $jm ?>"><?= $jm ?> – <?= $jsel ?> WIB</option>
                    <?php endforeach; ?>
                </select>
                <div class="val-msg" id="val-jam_mulai"></div>

                <div class="durasi-info-box">
                    <i class="fa-solid fa-clock"></i>
                    <span>Durasi otomatis <strong>1 jam</strong>. Jam operasional <strong>08:00 – 00:00 WIB</strong>.</span>
                </div>

                <button type="submit" id="btnSubmitForm" class="btn-submit">
                    <i class="fa-solid fa-plus"></i> Tambah Jadwal
                </button>
                <a onclick="closeModal('modalJadwal')" class="btn-cancel">Batal</a>
            </form>
        </div>
    </div>
</div>

<!-- MODAL DETAIL JADWAL -->
<div class="modal-overlay" id="modalDetail">
    <div class="modal-box" style="width: 440px;">
        <button type="button" class="modal-close" onclick="closeModal('modalDetail')"><i class="fa-solid fa-xmark"></i></button>
        <div class="modal-header" style="border-bottom: none; padding-bottom: 0;">
            <div class="modal-subtitle">Informasi Jadwal</div>
            <div class="modal-title">Detail Jadwal</div>
        </div>
        <div class="modal-body" style="padding-top: 10px;">
            <div style="text-align: center; margin-bottom: 24px; padding-bottom: 20px; border-bottom: 1.5px dashed var(--border);">
                <div class="detail-icon-wrap"><i class="fa-solid fa-calendar-days"></i></div>
                <div class="detail-main-name" id="det_lapangan">-</div>
            </div>
            
            <div class="info-row">
                <span class="info-key"><i class="fa-solid fa-calendar-day"></i> Tanggal</span>
                <span class="info-val" id="det_tanggal" style="font-weight:700;">-</span>
            </div>
            <div class="info-row">
                <span class="info-key"><i class="fa-solid fa-clock"></i> Jam Operasional</span>
                <span class="info-val" id="det_jam" style="font-family:'Barlow Condensed'; font-size:18px; color:var(--orange); font-weight:800;">- WIB</span>
            </div>
            <div class="info-row" style="border-bottom:none;">
                <span class="info-key"><i class="fa-solid fa-circle-check"></i> Status</span>
                <span class="info-val" id="det_status_pill">
                    <span class="status-pill sp-active"><span class="sp-dot"></span> -</span>
                </span>
            </div>
            
            <button type="button" onclick="closeModal('modalDetail')" class="btn-submit" style="margin-top: 24px; background: #0D1117;">
                <i class="fa-solid fa-arrow-left"></i> Kembali Ke Daftar Jadwal
            </button>
        </div>
    </div>
</div>

<!-- SIDEBAR -->
<?php include '../includes/sidebar.php'; ?>

<!-- MAIN CONTENT -->
<main class="main">
    <?php include '../includes/topbar.php'; ?>

    <div class="content" id="mainContentWrap">
        <!-- DYNAMICALLY LOADED VIA AJAX -->
    </div>
</main>

<script src="../asset/js/global.js"></script>
<script>
// State Management
let currentViewMode = 'upcoming';
let selectedDate = '<?= $today ?>';
let filterLapangan = '';
let currentHistoryPage = 1;

// ============================================
// MAIN PAGE LOADER (FULL ASYNC REFRESH)
// ============================================
async function loadMainContent() {
    const url = `jadwal.php?action=get_main_content&view=${currentViewMode}&tanggal=${selectedDate}&f_lapangan=${filterLapangan}&page=${currentHistoryPage}`;
    try {
        const response = await fetch(url);
        const data = await response.json();
        if (data.status === 'success') {
            document.getElementById('mainContentWrap').innerHTML = data.html;
        }
    } catch (err) {
        console.error("Gagal memuat konten jadwal:", err);
    }
}

function switchViewMode(mode) {
    currentViewMode = mode;
    currentHistoryPage = 1;
    loadMainContent();
}

function selectDate(dateStr) {
    selectedDate = dateStr;
    loadMainContent();
}

function changeLapanganFilter(lapId) {
    filterLapangan = lapId;
    currentHistoryPage = 1;
    loadMainContent();
}

function changeHistoryPage(page) {
    currentHistoryPage = page;
    loadMainContent();
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('open');
}

// ============================================
// MODAL & CRUD ACTIONS
// ============================================
function openAddModal(lapId, jamMulai, tanggal) {
    document.getElementById('formJadwal').reset();
    document.getElementById('edit_mode').value = '0';
    document.getElementById('id_jadwal').value = '';
    
    document.getElementById('id_lapangan').value = lapId;
    document.getElementById('tanggal').value = tanggal;
    document.getElementById('jam_mulai').value = jamMulai;
    
    document.querySelectorAll('.modal-input').forEach(el => el.classList.remove('error'));
    document.querySelectorAll('.val-msg').forEach(el => el.classList.remove('show'));

    document.getElementById('formModalTitle').innerText = 'Tambah Jadwal Baru';
    document.getElementById('btnSubmitForm').innerHTML = '<i class="fa-solid fa-plus"></i> Tambah Jadwal';
    
    document.getElementById('modalJadwal').classList.add('open');
}

async function openEditModal(id) {
    showLoading();
    try {
        const response = await fetch(`jadwal.php?action=get_detail&id=${id}`);
        const res = await response.json();
        Swal.close();
        if (res.status === 'success') {
            document.getElementById('edit_mode').value = '1';
            document.getElementById('id_jadwal').value = res.data.ID_Jadwal;
            
            document.getElementById('id_lapangan').value = res.data.ID_Lapangan;
            document.getElementById('tanggal').value = res.data.Tanggal_Formatted;
            document.getElementById('jam_mulai').value = res.data.Jam_Mulai_Formatted;
            
            document.querySelectorAll('.modal-input').forEach(el => el.classList.remove('error'));
            document.querySelectorAll('.val-msg').forEach(el => el.classList.remove('show'));

            document.getElementById('formModalTitle').innerText = 'Edit Jadwal';
            document.getElementById('btnSubmitForm').innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Simpan Perubahan';
            
            document.getElementById('modalJadwal').classList.add('open');
        } else {
            showError('Gagal!', res.msg);
        }
    } catch (err) {
        showError('Error!', 'Kesalahan koneksi jaringan.');
    }
}

async function openDetailModal(id) {
    showLoading();
    try {
        const response = await fetch(`jadwal.php?action=get_detail&id=${id}`);
        const res = await response.json();
        Swal.close();
        if (res.status === 'success') {
            document.getElementById('det_lapangan').innerText = res.data.Nama_Lapangan;
            document.getElementById('det_tanggal').innerText = res.data.Tanggal_Formatted;
            document.getElementById('det_jam').innerText = res.data.Jam_Mulai_Formatted + ' - ' + res.data.Jam_Selesai_Formatted + ' WIB';
            
            let pill = document.getElementById('det_status_pill');
            if (res.data.Status == 1) {
                pill.innerHTML = '<span class="status-pill sp-active"><span class="sp-dot"></span> AKTIF</span>';
            } else {
                pill.innerHTML = '<span class="status-pill sp-inactive"><span class="sp-dot"></span> NONAKTIF</span>';
            }
            document.getElementById('modalDetail').classList.add('open');
        } else {
            showError('Gagal!', res.msg);
        }
    } catch (err) {
        showError('Error!', 'Kesalahan koneksi jaringan.');
    }
}

async function submitJadwalAjax(e) {
    e.preventDefault();
    if (!validateForm()) return false;
    
    const formData = new FormData(document.getElementById('formJadwal'));
    showLoading('Menyimpan data jadwal...');

    try {
        const response = await fetch('jadwal.php', { method: 'POST', body: formData });
        const res = await response.json();
        if (res.status === 'success') {
            closeModal('modalJadwal');
            showSuccess('Berhasil!', res.msg);
            loadMainContent();
        } else {
            showError('Gagal!', res.msg);
        }
    } catch (err) {
        showError('Error!', 'Terjadi kesalahan sistem.');
    }
    return false;
}

// ============================================
// GENERATE ALL JADWAL (1-KLIK)
// ============================================
async function confirmGenerate(lapId, namaLap, tanggal, jumlah) {
    const result = await Swal.fire({
        title: 'Buat Otomatis ' + jumlah + ' Jadwal?',
        html: 'Semua jadwal 1-jam yang masih kosong untuk <strong style="color:var(--orange)">' + namaLap + '</strong> pada tanggal ini akan dibuat sekaligus (status Aktif).',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#FF4500',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Ya, Buat Sekarang!',
        cancelButtonText: 'Batal',
        reverseButtons: true,
        allowOutsideClick: false
    });

    if (result.isConfirmed) {
        showLoading('Membuat jadwal otomatis...');
        const formData = new FormData();
        formData.append('action', 'generate_all');
        formData.append('id_lapangan', lapId);
        formData.append('tanggal', tanggal);

        try {
            const response = await fetch('jadwal.php', { method: 'POST', body: formData });
            const res = await response.json();
            if (res.status === 'success') {
                showSuccess('Berhasil!', res.msg);
                loadMainContent();
            } else {
                showError('Gagal!', res.msg);
            }
        } catch (err) {
            showError('Error!', 'Terjadi kesalahan saat memproses.');
        }
    }
}

// ============================================
// TOGGLE & DELETE VIA AJAX
// ============================================
async function confirmToggle(id, currentStatus, event) {
    const checkbox = event.target;
    const actionText = currentStatus == 1 ? 'Menonaktifkan' : 'Mengaktifkan';

    const result = await Swal.fire({
        title: actionText + ' Jadwal?',
        text: 'Apakah Anda yakin ingin mengubah status jadwal ini?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#FF4500',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Ya, Ubah!',
        cancelButtonText: 'Batal',
        allowOutsideClick: false
    });

    if (result.isConfirmed) {
        showLoading('Mengubah status...');
        try {
            const response = await fetch(`jadwal.php?action=toggle&id=${id}`);
            const res = await response.json();
            if (res.status === 'success') {
                showSuccess('Berhasil!', res.msg);
                loadMainContent();
            } else {
                checkbox.checked = !checkbox.checked;
                showError('Gagal!', res.msg);
            }
        } catch (err) {
            checkbox.checked = !checkbox.checked;
            showError('Error!', 'Terjadi kesalahan sistem.');
        }
    } else {
        checkbox.checked = !checkbox.checked;
    }
}

async function confirmDelete(id, lapName, jamMulai) {
    const result = await Swal.fire({
        title: 'Hapus Jadwal?',
        html: 'Anda akan menghapus jadwal <strong style="color:var(--orange);">' + lapName + ' pukul ' + jamMulai + '</strong><br><span style="font-size:12px;color:var(--muted);">Data akan dihapus secara Permanen</span>',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#EF4444',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal',
        allowOutsideClick: false
    });

    if (result.isConfirmed) {
        showLoading('Menghapus data...');
        try {
            const response = await fetch(`jadwal.php?action=delete&id=${id}`);
            const res = await response.json();
            if (res.status === 'success') {
                showSuccess('Terhapus!', res.msg);
                loadMainContent();
            } else {
                showError('Gagal!', res.msg);
            }
        } catch (err) {
            showError('Error!', 'Terjadi kesalahan sistem.');
        }
    }
}

// ============================================
// FORM VALIDATION
// ============================================
function validateField(fieldId, valId, rules) {
    const field = document.getElementById(fieldId);
    const valMsg = document.getElementById(valId);
    if (!field || !valMsg) return true;
    const value = field.value.trim();
    field.classList.remove('error');
    valMsg.classList.remove('show');
    if (rules.required && value === '') {
        field.classList.add('error');
        valMsg.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> ' + rules.label + ' wajib diisi.';
        valMsg.classList.add('show');
        return false;
    }
    return true;
}

function getCurrentTime() {
    const now = new Date();
    return String(now.getHours()).padStart(2,'0') + ':' + String(now.getMinutes()).padStart(2,'0');
}

function validateForm() {
    let valid = true;
    document.querySelectorAll('.modal-input').forEach(el => el.classList.remove('error'));
    document.querySelectorAll('.val-msg').forEach(el => el.classList.remove('show'));

    if (!validateField('id_lapangan', 'val-id_lapangan', { required: true, label: 'Lapangan' })) valid = false;
    if (!validateField('tanggal',     'val-tanggal',     { required: true, label: 'Tanggal'  })) valid = false;
    if (!validateField('jam_mulai',   'val-jam_mulai',   { required: true, label: 'Jam mulai' })) valid = false;
    if (!valid) return false;

    const tanggalField = document.getElementById('tanggal');
    const tanggalVal = tanggalField.value;
    const hariIni = new Date().toISOString().split('T')[0];

    if (tanggalVal < hariIni) {
        tanggalField.classList.add('error');
        const vm = document.getElementById('val-tanggal');
        vm.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Tanggal tidak boleh kurang dari hari ini.';
        vm.classList.add('show');
        valid = false;
    }

    if (tanggalVal === hariIni) {
        const currentTime = getCurrentTime();
        const mulai = document.getElementById('jam_mulai').value;
        if (mulai <= currentTime) {
            document.getElementById('jam_mulai').classList.add('error');
            const vm = document.getElementById('val-jam_mulai');
            vm.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Jam mulai harus lebih besar dari jam sekarang (' + currentTime + ' WIB).';
            vm.classList.add('show');
            valid = false;
        }
    }
    return valid;
}

// ============================================
// HELPER SWEETALERT
// ============================================
function showLoading(msg = 'Memproses...') {
    Swal.fire({ title: msg, allowOutsideClick: false, didOpen: () => Swal.showLoading() });
}

function showSuccess(title, message) {
    Swal.close();
    Swal.fire({ icon: 'success', title: title, text: message, confirmButtonColor: '#10B981' });
}

function showError(title, message) {
    Swal.close();
    Swal.fire({ icon: 'error', title: title, text: message, confirmButtonColor: '#EF4444' });
}

document.addEventListener('DOMContentLoaded', function() {
    loadMainContent();

    ['id_lapangan','tanggal','jam_mulai'].forEach(function(fid) {
        var el = document.getElementById(fid);
        if (el) el.addEventListener('change', function() {
            var label = { id_lapangan: 'Lapangan', tanggal: 'Tanggal', jam_mulai: 'Jam mulai' }[fid];
            validateField(fid, 'val-' + fid, { required: true, label: label });
        });
    });
});
</script>
<?php if (function_exists('tampilkan_sensor_auto_logout')) tampilkan_sensor_auto_logout(); ?>
</body>
</html>