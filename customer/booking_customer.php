<?php
/**
 * Halaman Booking Lapangan - Customer
 * Tema visual disamakan dengan landing page (index.php): warna oranye,
 * font Barlow / Barlow Condensed, dan konvensi radius/shadow yang sama.
 *
 * Alur booking mengikuti pola "keranjang": setiap lapangan menampilkan
 * dropdown jadwal miliknya sendiri (jadwal tersedia & yang sudah dibooking
 * tampil sekaligus), pelanggan bisa langsung memilih beberapa slot jam
 * sekaligus (lintas lapangan/lintas jam) dan setiap klik langsung masuk
 * ke keranjang, baru kemudian checkout dari panel keranjang.
 */
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../includes/auth_helper.php';
include '../includes/config.php';

$current_page = basename($_SERVER['PHP_SELF']);

// =========================================================================
// Hapus Akun (soft delete)
// =========================================================================
if (isset($_GET['hapus_akun']) && $_GET['hapus_akun'] == '1') {
    $id_customer = $_SESSION['id_customer'] ?? $_SESSION['ID_Customer'] ?? $_SESSION['id_akun'] ?? '';

    if (!empty($id_customer)) {
        $modified_by = $_SESSION['nama'] ?? 'CUSTOMER';
        
        // Memanggil SP untuk melakukan soft delete
        $stmt = sqlsrv_query(
            $conn,
            "{call sp_Customer_SoftDelete(?, ?)}",
            array($id_customer, $modified_by)
        );

        if ($stmt) {
            $_SESSION = array();
            if (ini_get('session.use_cookies')) {
                $p = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
            }
            session_destroy();
            setcookie('remember_me', '', time() - 3600, "/");
            ob_end_clean();
            header("Location: ../login/login.php?status=success&msg=Akun Anda telah dihapus permanen.");
            exit();
        } else {
            ob_end_clean();
            header("Location: booking_customer.php?status=error&msg=Gagal menghapus akun.");
            exit();
        }
    }
}

cek_akses('customer');

// =========================================================================
// Data Profil Customer
// =========================================================================
$id_customer = $_SESSION['id_customer'] ?? $_SESSION['ID_Customer'] ?? $_SESSION['id_akun'] ?? '';
$nama_customer = 'Pelanggan';
$photo_profile = '';

if (!empty($id_customer)) {
    $st = sqlsrv_query($conn, "{call sp_Customer_GetProfile(?)}", array($id_customer));
    if ($st) {
        $row = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC);
        if ($row) {
            if ($row['Is_Deleted'] == 1 || $row['Status'] == 0) {
                $_SESSION = array();
                session_destroy();
                ob_end_clean();
                header("Location: ../login/login.php?status=error&msg=Akun dinonaktifkan.");
                exit();
            }
            $nama_customer = $row['Nama_Customer'] ?? 'Pelanggan';
            $photo_profile = $row['Photo_Profile'] ?? '';
        }
    }
}

// =========================================================================
// Data Membership Aktif
// =========================================================================
$member_data = null;
$mc = sqlsrv_query($conn, "{call sp_Customer_GetActiveMember(?)}", array($id_customer));
if ($mc) {
    $member_data = sqlsrv_fetch_array($mc, SQLSRV_FETCH_ASSOC);
}
$has_member = !empty($member_data);
$member_tipe = $has_member ? $member_data['Nama_Tipe'] : '';
$member_discount = $has_member ? floatval($member_data['Potongan_Harga']) : 0;

/**
 * Menyusun path foto agar selalu valid diakses dari folder customer/.
 */
function resolvePhotoPath($photo_path)
{
    if (empty($photo_path))
        return '';
    if (strpos($photo_path, 'http://') === 0 || strpos($photo_path, 'https://') === 0)
        return $photo_path;
    if (strpos($photo_path, '../') === 0)
        return $photo_path;
    if (strpos($photo_path, '/') === 0)
        return '..' . $photo_path;
    return '../' . ltrim($photo_path, '/');
}

/**
 * Generate jadwal 7 hari ke depan (07:00 - 23:00) untuk seluruh lapangan aktif,
 * hanya menyisipkan slot yang belum ada di tabel Jadwal.
 */
function generateJadwalOtomatis($conn)
{
    $q = sqlsrv_query($conn, "{call sp_Otomasi_GetLapanganAktif}");
    if (!$q)
        return;

    $list = [];
    while ($r = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)) {
        $list[] = $r['ID_Lapangan'];
    }

    $slots = [
        ['08:30:00', '09:30:00'],
        ['09:30:00', '10:30:00'],
        ['10:30:00', '11:30:00'],
        ['11:30:00', '12:30:00'],
        ['12:30:00', '13:30:00'],
        ['13:30:00', '14:30:00'],
        ['14:30:00', '15:30:00'],
        ['15:30:00', '16:30:00'],
        ['16:30:00', '17:30:00'],
        ['17:30:00', '18:30:00'],
        ['18:30:00', '19:30:00'],
        ['19:30:00', '20:30:00'],
        ['20:30:00', '21:30:00'],
        ['21:30:00', '22:30:00'],
        ['22:30:00', '23:30:00'],
        ['23:30:00', '00:00:00'],
    ];

    for ($i = 0; $i < 7; $i++) {
        $d = date('Y-m-d', strtotime("+$i days"));
        foreach ($list as $id_lap) {
            foreach ($slots as $s) {
                $cek = sqlsrv_query($conn, "{call sp_Otomasi_CekJadwal(?, ?, ?, ?)}", array($id_lap, $d, $s[0], $s[1]));
                if ($cek && !sqlsrv_fetch_array($cek, SQLSRV_FETCH_ASSOC)) {
                    sqlsrv_query($conn, "{call sp_Otomasi_InsertJadwal(?, ?, ?, ?, ?)}", array($id_lap, $d, $s[0], $s[1], 'SYSTEM_AUTO'));
                }
            }
        }
    }
}
generateJadwalOtomatis($conn);
// =========================================================================
// Endpoint AJAX (JSON)
// =========================================================================
if (isset($_GET['action'])) {
    header('Content-Type: application/json');

    // Ambil slot jadwal per court & tanggal menggunakan SP
    if ($_GET['action'] == 'get_all_slots' && isset($_GET['court_id']) && isset($_GET['tanggal'])) {
        $cid = intval($_GET['court_id']);
        $tanggal = $_GET['tanggal'];

        $st = sqlsrv_query($conn, "{call sp_Jadwal_GetSlots(?, ?)}", array($cid, $tanggal));

        $slots = [];
        $now_date = date('Y-m-d');
        $now_time = date('H:i:s');

        if ($st) {
            while ($r = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC)) {
                $mulai = ($r['Jam_Mulai'] instanceof DateTime) ? $r['Jam_Mulai']->format('H:i:s') : $r['Jam_Mulai'];
                $selesai = ($r['Jam_Selesai'] instanceof DateTime) ? $r['Jam_Selesai']->format('H:i:s') : $r['Jam_Selesai'];
                $sudahDibooking = ($r['Status'] == 0 || $r['Ada_Booking'] == 1);

                if ($sudahDibooking) {
                    $status = 'dibooking';
                } elseif ($tanggal == $now_date && $mulai <= $now_time) {
                    $status = 'lewat';
                } else {
                    $status = 'tersedia';
                }

                $slots[] = [
                    'ID_Jadwal' => $r['ID_Jadwal'],
                    'Jam_Mulai' => substr($mulai, 0, 5),
                    'Jam_Selesai' => substr($selesai, 0, 5),
                    'Status' => $status,
                ];
            }
        }
        echo json_encode($slots);
        exit();
    }

    // --- Proses checkout / pembuatan booking untuk seluruh isi keranjang ---
    // Dikirim sebagai multipart/form-data (FormData) karena menyertakan file
    // bukti pembayaran yang wajib diunggah sebelum konfirmasi.
    if ($_GET['action'] == 'checkout' && $_SERVER['REQUEST_METHOD'] == 'POST') {
        $id_jadwal_list = isset($_POST['id_jadwal_list']) ? json_decode($_POST['id_jadwal_list'], true) : [];
        if (!is_array($id_jadwal_list))
            $id_jadwal_list = [];
        $id_promo = !empty($_POST['id_promo']) ? intval($_POST['id_promo']) : null;
        $metode = htmlspecialchars($_POST['metode_pembayaran'] ?? '');
        $total = floatval($_POST['total_bayar'] ?? 0);

        if (empty($id_jadwal_list) || empty($metode) || $total <= 0) {
            echo json_encode(['success' => false, 'message' => 'Parameter input tidak valid.']);
            exit();
        }

        // --- Validasi & unggah bukti pembayaran (wajib sebelum booking dikonfirmasi) ---
        if (!isset($_FILES['bukti_pembayaran']) || $_FILES['bukti_pembayaran']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'Bukti pembayaran wajib diunggah sebelum konfirmasi.']);
            exit();
        }

        $allowed_ext = ['jpg', 'jpeg', 'png', 'pdf'];
        $file_tmp = $_FILES['bukti_pembayaran']['tmp_name'];
        $file_name_asli = $_FILES['bukti_pembayaran']['name'];
        $file_ext = strtolower(pathinfo($file_name_asli, PATHINFO_EXTENSION));

        if (!in_array($file_ext, $allowed_ext)) {
            echo json_encode(['success' => false, 'message' => 'Format bukti pembayaran harus JPG, PNG, atau PDF.']);
            exit();
        }
        if ($_FILES['bukti_pembayaran']['size'] > 5 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'Ukuran bukti pembayaran maksimal 5MB.']);
            exit();
        }

        $upload_dir = '../uploads/bukti_pembayaran/';
        if (!is_dir($upload_dir)) {
            @mkdir($upload_dir, 0755, true);
        }
        $new_file_name = 'bukti_' . $id_customer . '_' . time() . '_' . uniqid() . '.' . $file_ext;
        $target_path = $upload_dir . $new_file_name;

        if (!move_uploaded_file($file_tmp, $target_path)) {
            echo json_encode(['success' => false, 'message' => 'Gagal mengunggah bukti pembayaran. Silakan coba lagi.']);
            exit();
        }
        $bukti_pembayaran_db = 'uploads/bukti_pembayaran/' . $new_file_name;

        // Menggunakan SP untuk mengambil default Karyawan
        $kq = sqlsrv_query($conn, "{call sp_Karyawan_GetDefault}");
        $id_karyawan = 1;
        if ($kq) {
            $kd = sqlsrv_fetch_array($kq, SQLSRV_FETCH_ASSOC);
            if ($kd)
                $id_karyawan = $kd['ID_Karyawan'];
        }

        $by = $_SESSION['nama'] ?? 'CUSTOMER';
        $created_ids = [];
        $success_count = 0;
        $first_error = '';

        // Menggunakan SP untuk validasi tiap slot sebelum memanggil SP Booking
        foreach ($id_jadwal_list as $jid) {
            $chk = sqlsrv_query($conn, "{call sp_Jadwal_ValidateSlot(?)}", array($jid));
            $row = $chk ? sqlsrv_fetch_array($chk, SQLSRV_FETCH_ASSOC) : null;
            if (!$row || $row['Status'] != 1) {
                echo json_encode(['success' => false, 'message' => "Maaf, salah satu slot jadwal sudah terbooking atau tidak tersedia."]);
                exit();
            }
        }

        // Panggil sp_Booking_Create untuk setiap slot
        $promo_for_first = $id_promo;

        foreach ($id_jadwal_list as $index => $jid) {
            $current_promo = ($index === 0) ? $promo_for_first : null;

            $sp_sql = "{call sp_Booking_Create(?, ?, ?, ?, ?, ?)}";
            $sp_params = array(
                array($id_customer, SQLSRV_PARAM_IN),
                array($id_karyawan, SQLSRV_PARAM_IN),
                array($jid, SQLSRV_PARAM_IN),
                array($current_promo, SQLSRV_PARAM_IN),
                array($metode, SQLSRV_PARAM_IN),
                array($by, SQLSRV_PARAM_IN)
            );

            $sp_stmt = sqlsrv_query($conn, $sp_sql, $sp_params);

            if ($sp_stmt === false) {
                $err = sqlsrv_errors();
                $first_error = $err[0]['message'] ?? 'Gagal membuat booking.';
                break;
            }

            $sp_result = sqlsrv_fetch_array($sp_stmt, SQLSRV_FETCH_ASSOC);
            sqlsrv_free_stmt($sp_stmt);

            if (!$sp_result || $sp_result['Status'] !== 'SUCCESS') {
                $first_error = $sp_result['Message'] ?? 'Gagal membuat booking.';
                break;
            }

            $created_ids[] = $sp_result['ID_Booking'];
            $success_count++;
        }

        if ($success_count === count($id_jadwal_list)) {
            // Menggunakan SP untuk memperbarui bukti pembayaran pada booking
            foreach ($created_ids as $cb_id) {
                sqlsrv_query($conn, "{call sp_Booking_UpdateBukti(?, ?, ?)}", array($cb_id, $bukti_pembayaran_db, $by));
            }
            echo json_encode(['success' => true, 'message' => 'Pemesanan berhasil dibuat!']);
        } else {
            // Menggunakan SP untuk membatalkan booking (Rollback) jika sebagian gagal dibuat
            if (!empty($created_ids)) {
                foreach ($created_ids as $cb_id) {
                    sqlsrv_query($conn, "{call sp_Booking_SetStatusBatal(?, ?)}", array($cb_id, $by));
                }
            }
            echo json_encode(['success' => false, 'message' => $first_error ?: 'Gagal membuat booking.']);
        }
        exit();
    }
}

// =========================================================================
// Data untuk Tampilan Halaman
// =========================================================================
$lapanganList = [];
$ql = sqlsrv_query($conn, "{call sp_Lapangan_GetActive}");
if ($ql) {
    while ($r = sqlsrv_fetch_array($ql, SQLSRV_FETCH_ASSOC)) {
        $lapanganList[] = $r;
    }
}

$lapanganFasilitas = [];
$qf = sqlsrv_query($conn, "{call sp_Fasilitas_GetActive}");
if ($qf) {
    while ($r = sqlsrv_fetch_array($qf, SQLSRV_FETCH_ASSOC)) {
        $lapanganFasilitas[$r['ID_Lapangan']][] = $r['Nama_Fasilitas'];
    }
}

$promos = [];
if (!$has_member) {
    $qp = sqlsrv_query($conn, "{call sp_Promo_GetActive}");
    if ($qp) {
        while ($r = sqlsrv_fetch_array($qp, SQLSRV_FETCH_ASSOC)) {
            $promos[] = $r;
        }
    }
}

$dateList = [];
$hariIndo = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
$bulanIndo = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'];
for ($i = 0; $i < 7; $i++) {
    $ts = strtotime("+$i days");
    $dateList[] = [
        'value' => date('Y-m-d', $ts),
        'hari' => $hariIndo[date('w', $ts)],
        'tgl' => date('j', $ts),
        'bulan' => $bulanIndo[date('n', $ts)],
        'full' => date('d M Y', $ts),
        'isToday' => $i === 0,
    ];
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Lapangan | HoopBall Arena</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Barlow+Condensed:wght@700;800;900&family=Barlow:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../asset/css/navbar_footer.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* Variabel warna disamakan dengan tema oranye pada landing page (index.php).
   Nilai fallback ini didefinisikan ulang agar halaman tetap konsisten
   walau navbar_footer.css belum memuat variabel yang sama. */
        :root {
            --orange: #FF5400;
            --orange-dark: #E63900;
            --orange-light: rgba(255, 84, 0, 0.08);
            --orange-glow: rgba(255, 84, 0, 0.15);
            --dark: #1E293B;
            --text-dark: #1E293B;
            --text-secondary: #64748B;
            --text-muted: #94A3B8;
            --border-color: #E2E8F0;
            --border-light: #F1F5F9;
            --bg-light: #F8FAFC;
            --card-bg: #FFFFFF;
            --green: #22C55E;
            --green-light: rgba(34, 197, 94, 0.1);
            --red: #EF4444;
            --red-light: rgba(239, 68, 68, 0.1);
            --radius-sm: 10px;
            --radius-md: 14px;
            --radius-lg: 16px;
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.04);
            --shadow-md: 0 4px 20px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 12px 30px rgba(0, 0, 0, 0.08);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box
        }

        body {
            font-family: 'Barlow', sans-serif;
            background: var(--bg-light);
            color: var(--text-dark);
            -webkit-font-smoothing: antialiased
        }

        ::-webkit-scrollbar {
            width: 6px;
            height: 6px
        }

        ::-webkit-scrollbar-track {
            background: transparent
        }

        ::-webkit-scrollbar-thumb {
            background: var(--border-color);
            border-radius: 3px
        }

        /* ============ ANIMATIONS (disamakan dengan pembelian_alat.php / langganan_customer.php / pembatalan_customer.php) ============ */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(40px)
            }

            to {
                opacity: 1;
                transform: translateY(0)
            }
        }

        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-30px)
            }

            to {
                opacity: 1;
                transform: translateY(0)
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0
            }

            to {
                opacity: 1
            }
        }

        @keyframes scaleIn {
            from {
                opacity: 0;
                transform: scale(0.8)
            }

            to {
                opacity: 1;
                transform: scale(1)
            }
        }

        @keyframes pulse {

            0%,
            100% {
                transform: scale(1);
                box-shadow: 0 0 0 0 rgba(255, 84, 0, .35)
            }

            50% {
                transform: scale(1.05);
                box-shadow: 0 0 0 10px rgba(255, 84, 0, 0)
            }
        }

        @keyframes cardEnter {
            from {
                opacity: 0;
                transform: translateY(30px) scale(0.95)
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1)
            }
        }

        @keyframes iconBounce {

            0%,
            100% {
                transform: scale(1)
            }

            50% {
                transform: scale(1.15)
            }
        }

        @keyframes loaderBounce {
            from {
                transform: translateY(0)
            }

            to {
                transform: translateY(-20px)
            }
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px)
            }

            to {
                opacity: 1;
                transform: translateY(0)
            }
        }

        /* ============ PAGE LOADER ============ */
        .page-loader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: #0B0B0C;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            z-index: 99999;
            transition: opacity 0.5s ease, visibility 0.5s ease
        }

        .page-loader.hidden {
            opacity: 0;
            visibility: hidden;
            pointer-events: none
        }

        .loader-ball {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--orange);
            animation: loaderBounce 0.6s ease-in-out infinite alternate
        }

        .loader-ball:nth-child(2) {
            animation-delay: 0.2s
        }

        .loader-ball:nth-child(3) {
            animation-delay: 0.4s
        }

        /* ============ SCROLL PROGRESS ============ */
        .scroll-progress {
            position: fixed;
            top: 0;
            left: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--orange), #FF8C42);
            z-index: 9999;
            transform-origin: left;
            transform: scaleX(0);
            transition: transform 0.1s ease-out
        }

        /* ============ REVEAL ON SCROLL ============ */
        .reveal {
            opacity: 0;
            transform: translateY(40px);
            transition: all 0.8s cubic-bezier(0.16, 1, 0.3, 1)
        }

        .reveal.active {
            opacity: 1;
            transform: translateY(0)
        }

        .booking-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 24px 20px 100px
        }

        .booking-header {
            margin-bottom: 28px
        }

        .booking-header h1 {
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 26px;
            font-weight: 800;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 10px
        }

        .booking-header h1 i {
            color: var(--orange);
            font-size: 22px;
            animation: iconBounce 2s ease-in-out infinite
        }

        .booking-header p {
            font-size: 13px;
            color: var(--text-muted);
            margin-top: 4px;
            font-weight: 500
        }

        .date-section {
            margin-bottom: 28px
        }

        .date-section-label {
            font-size: 13px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 6px
        }

        .date-section-label i {
            color: var(--orange)
        }

        .date-scroll {
            display: flex;
            gap: 8px;
            overflow-x: auto;
            padding-bottom: 4px;
            scrollbar-width: none
        }

        .date-scroll::-webkit-scrollbar {
            display: none
        }

        .date-chip {
            flex-shrink: 0;
            min-width: 64px;
            padding: 12px 8px;
            border-radius: var(--radius-md);
            border: 1.5px solid var(--border-color);
            background: var(--card-bg);
            cursor: pointer;
            text-align: center;
            transition: var(--transition);
            position: relative;
            opacity: 0;
            transform: translateY(20px);
            animation: fadeInUp 0.5s ease-out forwards
        }

        .date-chip:nth-child(1) {
            animation-delay: 0.05s
        }

        .date-chip:nth-child(2) {
            animation-delay: 0.1s
        }

        .date-chip:nth-child(3) {
            animation-delay: 0.15s
        }

        .date-chip:nth-child(4) {
            animation-delay: 0.2s
        }

        .date-chip:nth-child(5) {
            animation-delay: 0.25s
        }

        .date-chip:nth-child(6) {
            animation-delay: 0.3s
        }

        .date-chip:nth-child(7) {
            animation-delay: 0.35s
        }

        .date-chip:hover {
            border-color: var(--orange);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md)
        }

        .date-chip.active {
            background: var(--orange);
            border-color: var(--orange);
            color: #fff;
            box-shadow: 0 4px 16px rgba(255, 84, 0, 0.3)
        }

        .date-chip .day-name {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 2px
        }

        .date-chip.active .day-name {
            color: rgba(255, 255, 255, 0.85)
        }

        .date-chip .day-num {
            font-size: 20px;
            font-weight: 800;
            line-height: 1
        }

        .date-chip .month-name {
            font-size: 10px;
            font-weight: 600;
            margin-top: 2px
        }

        .date-chip.active .month-name {
            color: rgba(255, 255, 255, 0.85)
        }

        .date-chip .today-badge {
            position: absolute;
            top: -6px;
            right: -6px;
            background: var(--orange);
            color: #fff;
            font-size: 8px;
            font-weight: 800;
            padding: 2px 6px;
            border-radius: 10px;
            animation: pulse 2s ease-in-out infinite
        }

        .court-section {
            margin-bottom: 28px
        }

        .court-section-label {
            font-size: 13px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 6px
        }

        .court-section-label i {
            color: var(--orange)
        }

        .court-card {
            background: var(--card-bg);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            overflow: hidden;
            margin-bottom: 16px;
            transition: var(--transition);
            opacity: 0;
            transform: translateY(30px) scale(0.95)
        }

        .court-card.visible {
            opacity: 1;
            transform: translateY(0) scale(1)
        }

        .court-card:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-4px)
        }

        .court-card.visible:hover {
            transform: translateY(-4px) scale(1)
        }

        .court-card-top {
            display: flex
        }

        .court-img-wrap {
            width: 200px;
            min-height: 160px;
            flex-shrink: 0;
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, #FFF7ED 0%, #FFEDD5 100%)
        }

        .court-img-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease
        }

        .court-card:hover .court-img-wrap img {
            transform: scale(1.05)
        }

        .court-img-badge {
            position: absolute;
            bottom: 10px;
            left: 10px;
            background: var(--green);
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 20px
        }

        .court-body {
            flex: 1;
            padding: 20px;
            display: flex;
            flex-direction: column;
            justify-content: space-between
        }

        .court-name {
            font-size: 16px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 6px
        }

        .court-desc {
            font-size: 12px;
            color: var(--text-muted);
            line-height: 1.5;
            margin-bottom: 12px
        }

        .court-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 14px
        }

        .court-meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
            color: var(--text-muted);
            font-weight: 500
        }

        .court-meta-item i {
            font-size: 11px;
            color: var(--orange)
        }

        .court-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px
        }

        .court-price {
            font-size: 18px;
            font-weight: 800;
            color: var(--orange);
            transition: var(--transition)
        }

        .court-card:hover .court-price {
            transform: scale(1.05)
        }

        .court-price span {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted)
        }

        .court-slots-toggle {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            background: var(--orange);
            color: #fff;
            padding: 12px 20px;
            margin-top: 4px;
            cursor: pointer;
            font-weight: 700;
            font-size: 13px;
            transition: var(--transition);
            position: relative;
            overflow: hidden
        }

        .court-slots-toggle::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255, 255, 255, .15);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: width .5s, height .5s
        }

        .court-slots-toggle:hover::before {
            width: 300px;
            height: 300px
        }

        .court-slots-toggle:hover {
            background: var(--orange-dark)
        }

        .court-slots-toggle .toggle-icon {
            transition: transform 0.3s ease
        }

        .court-slots-toggle .toggle-icon.rotated {
            transform: rotate(180deg)
        }

        .court-slots-grid {
            display: none;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            padding: 16px 20px 20px
        }

        .court-slots-grid.open {
            display: grid
        }

        .mini-slot {
            position: relative;
            padding: 12px 6px;
            border-radius: var(--radius-md);
            border: 1.5px solid var(--border-color);
            background: var(--card-bg);
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
            opacity: 0;
            animation: scaleIn 0.3s ease-out forwards
        }

        .mini-slot:hover {
            border-color: var(--orange);
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm)
        }

        .mini-slot.selected {
            background: var(--orange);
            border-color: var(--orange);
            color: #fff;
            box-shadow: 0 4px 16px rgba(255, 84, 0, 0.3)
        }

        .mini-slot.dibooking,
        .mini-slot.lewat {
            background: var(--border-light);
            border-color: var(--border-light);
            color: var(--text-muted);
            cursor: not-allowed
        }

        .mini-slot.dibooking:hover,
        .mini-slot.lewat:hover {
            transform: none;
            box-shadow: none;
            border-color: var(--border-light)
        }

        .mini-time {
            font-size: 12px;
            font-weight: 700
        }

        .mini-status {
            font-size: 9px;
            font-weight: 700;
            margin-top: 4px;
            text-transform: uppercase;
            letter-spacing: 0.3px
        }

        .mini-slot:not(.dibooking):not(.lewat):not(.selected) .mini-status {
            color: var(--green)
        }

        .mini-slot.selected .mini-status {
            color: rgba(255, 255, 255, 0.85)
        }

        .mini-price {
            font-size: 10px;
            font-weight: 600;
            margin-top: 4px;
            color: var(--text-muted)
        }

        .mini-slot.selected .mini-price {
            color: rgba(255, 255, 255, 0.85)
        }

        .mini-check {
            position: absolute;
            top: -7px;
            right: -7px;
            width: 18px;
            height: 18px;
            background: #fff;
            color: var(--orange);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 9px;
            box-shadow: var(--shadow-sm);
            animation: scaleIn 0.2s ease-out
        }

        .cart-fab {
            position: fixed;
            bottom: 24px;
            right: 24px;
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: var(--orange);
            color: #fff;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            cursor: pointer;
            box-shadow: var(--shadow-lg);
            transition: var(--transition);
            z-index: 200;
            animation: pulse 2.5s ease-in-out infinite
        }

        .cart-fab:hover {
            background: var(--orange-dark);
            transform: translateY(-2px)
        }

        .cart-fab.hidden {
            display: none
        }

        .cart-badge {
            position: absolute;
            top: -4px;
            right: -4px;
            background: var(--red);
            color: #fff;
            font-size: 11px;
            font-weight: 800;
            min-width: 20px;
            height: 20px;
            padding: 0 4px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center
        }

        .cart-panel-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.5);
            z-index: 300;
            display: none
        }

        .cart-panel-overlay.active {
            display: block;
            animation: fadeIn 0.2s ease-out
        }

        .cart-panel {
            position: fixed;
            top: 0;
            right: -380px;
            width: 360px;
            max-width: 90vw;
            height: 100%;
            background: var(--card-bg);
            z-index: 301;
            transition: right 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
            box-shadow: -8px 0 30px rgba(0, 0, 0, 0.15)
        }

        .cart-panel.active {
            right: 0
        }

        .cart-panel-header {
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between
        }

        .cart-panel-title {
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 18px;
            font-weight: 800;
            color: var(--dark);
            text-transform: uppercase;
            letter-spacing: 0.5px
        }

        .cart-panel-body {
            flex: 1;
            overflow-y: auto;
            padding: 16px 20px
        }

        .cart-group-title {
            font-size: 12px;
            font-weight: 700;
            color: var(--orange);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 16px 0 8px
        }

        .cart-group-title:first-child {
            margin-top: 0
        }

        .cart-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            background: var(--bg-light);
            border-radius: var(--radius-md);
            padding: 12px 14px;
            margin-bottom: 10px;
            animation: fadeInUp 0.3s ease-out
        }

        .cart-item-info {
            font-size: 12px;
            color: var(--dark);
            font-weight: 600;
            line-height: 1.5
        }

        .cart-item-info small {
            display: block;
            color: var(--text-muted);
            font-weight: 500;
            margin-top: 2px
        }

        .cart-item-remove {
            background: none;
            border: none;
            color: var(--red);
            cursor: pointer;
            font-size: 14px;
            padding: 6px;
            flex-shrink: 0;
            transition: var(--transition)
        }

        .cart-item-remove:hover {
            transform: scale(1.15)
        }

        .cart-empty {
            text-align: center;
            color: var(--text-muted);
            padding: 60px 20px;
            font-size: 13px;
            line-height: 1.6
        }

        .cart-empty i {
            display: block;
            margin-bottom: 12px;
            opacity: 0.4
        }

        .cart-panel-footer {
            padding: 16px 20px 20px;
            border-top: 1px solid var(--border-color)
        }

        .cart-total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 14px
        }

        .cart-total-label {
            font-size: 12px;
            color: var(--text-muted);
            font-weight: 600
        }

        .cart-total-value {
            font-size: 20px;
            font-weight: 800;
            color: var(--orange);
            animation: fadeInUp 0.3s ease-out
        }

        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(15, 23, 42, 0.5);
            backdrop-filter: blur(4px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 20px
        }

        .modal-overlay.active {
            display: flex;
            animation: fadeIn 0.2s ease-out
        }

        .modal-card {
            background: var(--card-bg);
            border-radius: var(--radius-lg);
            width: 100%;
            max-width: 480px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow-lg);
            animation: slideUp 0.3s ease-out
        }

        .modal-header {
            padding: 24px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between
        }

        .modal-title {
            font-size: 16px;
            font-weight: 700;
            color: var(--dark)
        }

        .modal-close {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: none;
            background: var(--border-light);
            color: var(--text-muted);
            cursor: pointer;
            font-size: 14px;
            transition: var(--transition)
        }

        .modal-close:hover {
            background: var(--red-light);
            color: var(--red);
            transform: rotate(90deg)
        }

        .modal-body {
            padding: 24px
        }

        .modal-footer {
            padding: 16px 24px 24px;
            display: flex;
            gap: 10px
        }

        .modal-footer .btn-full {
            flex: 1
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid var(--border-light)
        }

        .detail-row:last-child {
            border-bottom: none
        }

        .detail-label {
            font-size: 13px;
            color: var(--text-secondary);
            font-weight: 500
        }

        .detail-value {
            font-size: 13px;
            font-weight: 700;
            color: var(--dark);
            text-align: right
        }

        .detail-value.price {
            color: var(--orange);
            font-size: 15px
        }

        .detail-value.discount {
            color: var(--green)
        }

        .detail-total {
            background: var(--orange-light);
            border-radius: var(--radius-md);
            padding: 16px;
            margin-top: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center
        }

        .detail-total-label {
            font-size: 14px;
            font-weight: 700;
            color: var(--dark)
        }

        .detail-total-value {
            font-size: 22px;
            font-weight: 800;
            color: var(--orange)
        }

        .payment-options {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 16px
        }

        .payment-option {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px;
            border: 1.5px solid var(--border-color);
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: var(--transition);
            background: var(--card-bg)
        }

        .payment-option:hover {
            border-color: var(--orange)
        }

        .payment-option.selected {
            border-color: var(--orange);
            background: var(--orange-light)
        }

        .payment-radio {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 2px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: var(--transition)
        }

        .payment-option.selected .payment-radio {
            border-color: var(--orange)
        }

        .payment-radio::after {
            content: '';
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--orange);
            display: none
        }

        .payment-option.selected .payment-radio::after {
            display: block;
            animation: scaleIn 0.2s ease-out
        }

        .payment-info {
            flex: 1
        }

        .payment-name {
            font-size: 13px;
            font-weight: 700;
            color: var(--dark)
        }

        .payment-desc {
            font-size: 11px;
            color: var(--text-muted);
            margin-top: 2px
        }

        .payment-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: var(--orange-light);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--orange);
            font-size: 16px
        }

        .promo-section {
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid var(--border-light)
        }

        .promo-label {
            font-size: 12px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 8px
        }

        .promo-select {
            width: 100%;
            padding: 12px 14px;
            border: 1.5px solid var(--border-color);
            border-radius: var(--radius-md);
            font-family: inherit;
            font-size: 13px;
            color: var(--dark);
            background: var(--card-bg);
            outline: none;
            transition: var(--transition);
            cursor: pointer
        }

        .promo-select:focus {
            border-color: var(--orange);
            box-shadow: 0 0 0 3px var(--orange-glow)
        }

        .promo-locked {
            padding: 12px 14px;
            background: var(--border-light);
            border-radius: var(--radius-md);
            font-size: 13px;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 8px
        }

        .promo-locked i {
            color: #F59E0B
        }

        .btn-primary {
            background: var(--orange);
            color: #fff;
            border: none;
            padding: 14px 24px;
            border-radius: 12px;
            font-family: inherit;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            position: relative;
            overflow: hidden
        }

        .btn-primary::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255, 255, 255, .2);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: width .6s, height .6s
        }

        .btn-primary:hover:not(:disabled)::before {
            width: 400px;
            height: 400px
        }

        .btn-primary:hover {
            background: var(--orange-dark);
            transform: translateY(-1px);
            box-shadow: 0 4px 16px rgba(255, 84, 0, 0.3)
        }

        .btn-primary:disabled {
            background: var(--text-muted);
            cursor: not-allowed;
            transform: none;
            box-shadow: none
        }

        .btn-secondary {
            background: var(--border-light);
            color: var(--dark);
            border: none;
            padding: 14px 24px;
            border-radius: 12px;
            font-family: inherit;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition)
        }

        .btn-secondary:hover {
            background: var(--border-color)
        }

        .payment-instr-tabs {
            display: flex;
            gap: 4px;
            background: var(--border-light);
            padding: 4px;
            border-radius: 10px;
            margin-bottom: 20px
        }

        .instr-tab {
            flex: 1;
            padding: 10px;
            border: none;
            border-radius: 8px;
            font-family: inherit;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            background: transparent;
            color: var(--text-secondary);
            transition: var(--transition)
        }

        .instr-tab.active {
            background: var(--card-bg);
            color: var(--orange);
            box-shadow: var(--shadow-sm)
        }

        .va-box {
            background: var(--bg-light);
            border: 1.5px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 20px;
            text-align: center;
            margin-bottom: 16px
        }

        .va-label {
            font-size: 11px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px
        }

        .va-number {
            font-size: 22px;
            font-weight: 800;
            color: var(--dark);
            letter-spacing: 2px;
            font-family: 'Courier New', monospace
        }

        .va-copy-btn {
            margin-top: 12px;
            background: var(--orange);
            color: #fff;
            border: none;
            padding: 8px 20px;
            border-radius: 8px;
            font-family: inherit;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition)
        }

        .va-copy-btn:hover {
            background: var(--orange-dark)
        }

        .qris-box {
            text-align: center;
            padding: 20px
        }

        .qris-img {
            width: 180px;
            height: 180px;
            object-fit: contain;
            margin: 0 auto 16px;
            display: block
        }

        .countdown-box {
            background: var(--orange-light);
            border: 1.5px solid var(--orange-glow);
            border-radius: var(--radius-md);
            padding: 12px 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-bottom: 16px
        }

        .countdown-box i {
            color: var(--orange);
            animation: pulse 2s infinite
        }

        .countdown-text {
            font-size: 12px;
            font-weight: 700;
            color: var(--orange)
        }

        .total-box {
            background: var(--border-light);
            border-radius: var(--radius-md);
            padding: 16px;
            text-align: center;
            margin-bottom: 20px
        }

        .total-label {
            font-size: 11px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase
        }

        .total-amount {
            font-size: 28px;
            font-weight: 800;
            color: var(--orange);
            margin-top: 4px;
            animation: fadeInUp 0.4s ease-out
        }

        .empty-state {
            text-align: center;
            padding: 24px;
            color: var(--text-muted)
        }

        .empty-state i {
            font-size: 32px;
            margin-bottom: 10px;
            opacity: 0.5
        }

        .empty-state p {
            font-size: 13px;
            font-weight: 600
        }

        /* ============ FOOTER (disamakan dengan langganan_customer.php) ============ */
        footer {
            background: #0B0B0C;
            padding: 60px 80px 30px;
            border-top: 1px solid #222225;
            animation: fadeInUp 0.6s ease-out both
        }

        .footer-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1.5fr;
            gap: 40px;
            max-width: 1440px;
            margin: 0 auto
        }

        .footer-logo img {
            height: 50px;
            width: auto;
            margin-bottom: 16px;
            transition: transform 0.3s ease
        }

        .footer-logo:hover img {
            transform: scale(1.05)
        }

        .footer-desc {
            font-size: 14px;
            color: #8E8E93;
            line-height: 1.6;
            margin-bottom: 20px
        }

        .social-links {
            display: flex;
            gap: 10px
        }

        .social-btn {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid #222225;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #8E8E93;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s ease
        }

        .social-btn:hover {
            background: var(--orange);
            border-color: var(--orange);
            color: #fff;
            transform: translateY(-2px)
        }

        .footer-col h4 {
            font-size: 14px;
            font-weight: 700;
            color: #fff;
            margin-bottom: 20px;
            text-transform: uppercase;
            letter-spacing: 0.5px
        }

        .footer-col ul {
            list-style: none;
            padding: 0
        }

        .footer-col ul li {
            margin-bottom: 12px
        }

        .footer-col ul li a {
            color: #8E8E93;
            text-decoration: none;
            font-size: 13px;
            transition: all 0.3s ease;
            display: inline-block
        }

        .footer-col ul li a:hover {
            color: var(--orange);
            transform: translateX(4px)
        }

        .contact-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            color: #8E8E93;
            font-size: 13px;
            margin-bottom: 12px;
            line-height: 1.5
        }

        .contact-item i {
            color: var(--orange);
            font-size: 14px;
            margin-top: 2px;
            flex-shrink: 0
        }

        .footer-bottom {
            border-top: 1px solid #222225;
            margin-top: 40px;
            padding-top: 20px;
            text-align: center;
            color: #636366;
            font-size: 12px
        }

        /* ============ UPLOAD BUKTI PEMBAYARAN ============ */
        .bukti-upload-box {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 22px;
            border: 2px dashed var(--border-color);
            border-radius: var(--radius-md);
            cursor: pointer;
            text-align: center;
            color: var(--text-muted);
            font-size: 12px;
            font-weight: 600;
            transition: var(--transition)
        }

        .bukti-upload-box:hover {
            border-color: var(--orange);
            background: var(--orange-light);
            color: var(--orange)
        }

        .bukti-upload-box i {
            font-size: 22px;
            color: var(--orange)
        }

        .bukti-upload-box.filled {
            border-style: solid;
            border-color: var(--green);
            color: var(--green);
            background: var(--green-light)
        }

        .bukti-upload-box.filled i {
            color: var(--green)
        }

        /* ============ PAGINATION LAPANGAN ============ */
        .court-pagination {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            margin-top: 20px;
            flex-wrap: wrap
        }

        .court-pagination .page-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 36px;
            height: 36px;
            padding: 0 10px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
            font-family: 'Barlow', sans-serif;
            cursor: pointer;
            transition: var(--transition);
            border: 1.5px solid var(--border-color);
            color: var(--text-dark);
            background: var(--card-bg)
        }

        .court-pagination .page-btn:hover:not(.disabled):not(.active) {
            border-color: var(--orange);
            color: var(--orange);
            background: var(--orange-light)
        }

        .court-pagination .page-btn.active {
            background: var(--orange);
            color: #fff;
            border-color: var(--orange);
            box-shadow: 0 4px 12px rgba(255, 84, 0, .3)
        }

        .court-pagination .page-btn.disabled {
            opacity: .4;
            cursor: not-allowed;
            pointer-events: none
        }

        @media(max-width:768px) {
            .booking-container {
                padding: 16px 12px 100px
            }

            .court-card-top {
                flex-direction: column
            }

            .court-img-wrap {
                width: 100%;
                height: 180px
            }

            .court-slots-grid {
                grid-template-columns: repeat(3, 1fr)
            }

            .footer-grid {
                grid-template-columns: 1fr
            }

            footer {
                padding: 30px 20px
            }
        }

        @media(max-width:1100px) {
            .footer-grid {
                grid-template-columns: 1fr 1fr;
                gap: 30px
            }

            footer {
                padding: 40px
            }
        }

        @media(max-width:480px) {
            .court-slots-grid {
                grid-template-columns: repeat(2, 1fr)
            }

            .cart-panel {
                width: 100%;
                max-width: 100%
            }
        }
    
/* Hilangkan scrollbar di modal tapi tetap bisa scroll */
.modal-card {
    scrollbar-width: none;
    -ms-overflow-style: none;
}
.modal-card::-webkit-scrollbar {
    display: none;
}
</style>
</head>

<body>

    <div class="page-loader" id="pageLoader">
        <div class="loader-ball"></div>
        <div class="loader-ball"></div>
        <div class="loader-ball"></div>
    </div>

    <div class="scroll-progress" id="scrollProgress"></div>

    <?php $path_prefix = '../';
    include '../includes/navbar.php'; ?>
    <div class="booking-container">
        <div class="booking-header reveal">
            <h1><i class="fa-solid fa-basketball"></i> Pilih Lapangan</h1>
            <p>Pilih tanggal, buka jadwal tiap lapangan, lalu klik jam yang tersedia untuk langsung menambahkannya ke
                keranjang.</p>
        </div>
        <div class="date-section reveal">
            <div class="date-section-label"><i class="fa-solid fa-calendar-days"></i> Pilih Tanggal</div>
            <div class="date-scroll" id="dateScroll">
                <?php foreach ($dateList as $idx => $d): ?>
                    <div class="date-chip <?= $idx === 0 ? 'active' : '' ?>" data-value="<?= $d['value'] ?>"
                        onclick="selectDate(this,'<?= $d['value'] ?>')">
                        <?php if ($d['isToday']): ?><span class="today-badge">Hari Ini</span><?php endif; ?>
                        <div class="day-name"><?= $d['hari'] ?></div>
                        <div class="day-num"><?= $d['tgl'] ?></div>
                        <div class="month-name"><?= $d['bulan'] ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="court-section">
            <div class="court-section-label reveal"><i class="fa-solid fa-layer-group"></i> Pilih Lapangan</div>
            <?php if (!empty($lapanganList)): ?>
                <?php foreach ($lapanganList as $idx => $lap):
                    $cId = $lap['ID_Lapangan'];
                    $cName = htmlspecialchars($lap['Nama_Lapangan']);
                    $cPrice = floatval($lap['Harga_Sewa']);
                    $rawPhoto = $lap['Photo_Lapangan'] ?? '';
                    $resolvedPhoto = resolvePhotoPath($rawPhoto);
                    $img = !empty($resolvedPhoto) ? htmlspecialchars($resolvedPhoto) : '';
                    $fasilitas = $lapanganFasilitas[$cId] ?? [];
                    ?>
                    <div class="court-card" id="court-<?= $cId ?>" data-id="<?= $cId ?>" data-price="<?= $cPrice ?>"
                        data-name="<?= $cName ?>">
                        <div class="court-card-top">
                            <div class="court-img-wrap">
                                <?php if ($img): ?><img src="<?= $img ?>" alt="<?= $cName ?>" loading="lazy">
                                <?php else: ?>
                                    <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center"><i
                                            class="fa-solid fa-basketball"
                                            style="font-size:40px;color:var(--orange);opacity:0.4"></i></div><?php endif; ?>
                                <span class="court-img-badge">Tersedia</span>
                            </div>
                            <div class="court-body">
                                <div>
                                    <div class="court-name"><?= $cName ?></div>
                                    <div class="court-desc">Lapangan basket indoor dengan fasilitas lengkap dan pencahayaan
                                        optimal untuk pengalaman bermain terbaik.</div>
                                    <div class="court-meta">
                                        <?php foreach (array_slice($fasilitas, 0, 3) as $f): ?>
                                            <div class="court-meta-item"><i class="fa-solid fa-circle-check"></i>
                                                <?= htmlspecialchars($f) ?></div>
                                        <?php endforeach; ?>
                                        <?php if (empty($fasilitas)): ?>
                                            <div class="court-meta-item"><i class="fa-solid fa-basketball"></i> Bola Standar</div>
                                            <div class="court-meta-item"><i class="fa-solid fa-lightbulb"></i> Pencahayaan LED</div>
                                            <div class="court-meta-item"><i class="fa-solid fa-wind"></i> AC</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="court-footer">
                                    <div class="court-price">Rp <?= number_format($cPrice, 0, ',', '.') ?> <span>/ jam</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="court-slots-toggle" onclick="toggleCourtSlots('<?= $cId ?>')">
                            <span><i class="fa-solid fa-calendar-check"></i> <span id="slotsCount-<?= $cId ?>">Memuat
                                    jadwal...</span></span>
                            <i class="fa-solid fa-chevron-down toggle-icon" id="toggleIcon-<?= $cId ?>"></i>
                        </div>
                        <div class="court-slots-grid" id="courtSlots-<?= $cId ?>"></div>
                    </div>
                <?php endforeach; ?>
                <div class="court-pagination" id="courtPagination"></div>
            <?php else: ?>
                <div class="empty-state"><i class="fa-solid fa-inbox"></i>
                    <p>Tidak ada lapangan aktif saat ini.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- FOOTER -->
    <?php include '../includes/footer.php'; ?>

    <button class="cart-fab hidden" id="cartFab" onclick="openCartPanel()">
        <i class="fa-solid fa-cart-shopping"></i>
        <span class="cart-badge" id="cartBadge">0</span>
    </button>

    <div class="cart-panel-overlay" id="cartPanelOverlay" onclick="closeCartPanel()"></div>
    <div class="cart-panel" id="cartPanel">
        <div class="cart-panel-header">
            <div class="cart-panel-title">Jadwal Dipilih</div>
            <button class="modal-close" onclick="closeCartPanel()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="cart-panel-body" id="cartPanelBody"></div>
        <div class="cart-panel-footer" id="cartTotalFooter" style="display:none">
            <div class="cart-total-row">
                <span class="cart-total-label" id="cartTotalCount">0 jadwal dipilih</span>
                <span class="cart-total-value" id="cartTotalValue">Rp 0</span>
            </div>
            <button class="btn-primary" onclick="openBookingModalFromCart()"><i class="fa-solid fa-arrow-right"></i>
                Selanjutnya</button>
        </div>
    </div>

    <div class="modal-overlay" id="bookingModal">
        <div class="modal-card">
            <div class="modal-header">
                <div class="modal-title">Ringkasan Booking</div>
                <button class="modal-close" onclick="closeModal('bookingModal')"><i
                        class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="modal-body">
                <div id="modalItemsList"></div>
                <?php if ($has_member): ?>
                    <div class="detail-row"><span class="detail-label">Diskon Member
                            (<?= htmlspecialchars($member_tipe) ?>)</span><span class="detail-value discount"
                            id="modalDiscount">-Rp <?= number_format($member_discount, 0, ',', '.') ?></span></div>
                    <div class="promo-section">
                        <div class="promo-locked"><i class="fa-solid fa-lock"></i> Promo tidak dapat digunakan karena member
                            aktif</div>
                    </div>
                <?php else: ?>
                    <div class="detail-row"><span class="detail-label">Potongan Promo</span><span
                            class="detail-value discount" id="modalPromoDiscount">-Rp 0</span></div>
                    <div class="promo-section">
                        <div class="promo-label">Gunakan Promo</div>
                        <select class="promo-select" id="modalPromoSelect">
                            <option value="0" data-discount="0">-- Pilih Promo --</option>
                            <?php foreach ($promos as $p): ?>
                                <option value="<?= $p['ID_Promo'] ?>" data-discount="<?= floatval($p['Diskon']) ?>">
                                    <?= htmlspecialchars($p['Nama_Promo']) ?> (-Rp
                                    <?= number_format($p['Diskon'], 0, ',', '.') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                <div class="detail-total"><span class="detail-total-label">Total Pembayaran</span><span
                        class="detail-total-value" id="modalTotal">Rp 0</span></div>
                <div style="margin-top:20px">
                    <div class="promo-label">Metode Pembayaran</div>
                    <div class="payment-options">
                        <div class="payment-option selected" data-method="Transfer Bank" onclick="selectPayment(this)">
                            <div class="payment-radio"></div>
                            <div class="payment-icon"><i class="fa-solid fa-building-columns"></i></div>
                            <div class="payment-info">
                                <div class="payment-name">Transfer Bank</div>
                                <div class="payment-desc">Virtual Account</div>
                            </div>
                        </div>
                        <div class="payment-option" data-method="QRIS" onclick="selectPayment(this)">
                            <div class="payment-radio"></div>
                            <div class="payment-icon"><i class="fa-solid fa-qrcode"></i></div>
                            <div class="payment-info">
                                <div class="payment-name">QRIS</div>
                                <div class="payment-desc">Scan & Bayar</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="closeModal('bookingModal')">Batal</button>
                <button class="btn-primary btn-full" id="btnConfirmBooking" onclick="confirmBooking()"><i
                        class="fa-solid fa-lock"></i> Bayar Sekarang</button>
            </div>
        </div>
    </div>
    <div class="modal-overlay" id="paymentModal">
        <div class="modal-card">
            <div class="modal-header">
                <div class="modal-title">Instruksi Pembayaran</div>
                <button class="modal-close" onclick="closeModal('paymentModal')"><i
                        class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="modal-body">
                <div class="countdown-box"><i class="fa-solid fa-clock"></i><span class="countdown-text">Selesaikan
                        dalam <span id="countdown">15:00</span></span></div>
                <div class="total-box">
                    <div class="total-label">Total Tagihan</div>
                    <div class="total-amount" id="paymentTotal">Rp 0</div>
                </div>
                <div class="payment-instr-tabs">
                    <button class="instr-tab active" id="tabVA" onclick="showPaymentTab('va')"><i
                            class="fa-solid fa-university"></i> Virtual Account</button>
                    <button class="instr-tab" id="tabQRIS" onclick="showPaymentTab('qris')"><i
                            class="fa-solid fa-qrcode"></i> QRIS</button>
                </div>
                <div id="instrVA">
                    <div class="va-box">
                        <div class="va-label">Nomor Virtual Account</div>
                        <div class="va-number" id="vaNumber">8801281234567890</div><button class="va-copy-btn"
                            onclick="copyVA()"><i class="fa-regular fa-copy"></i> Salin Nomor</button>
                    </div>
                    <div style="font-size:12px;color:var(--text-secondary);line-height:1.8">
                        <p style="margin-bottom:8px;font-weight:700;color:var(--dark)">Cara Pembayaran:</p>
                        <p>1. Buka aplikasi M-Banking atau ATM Anda</p>
                        <p>2. Pilih menu <strong>Transfer > Virtual Account</strong></p>
                        <p>3. Masukkan nomor VA di atas</p>
                        <p>4. Konfirmasi pembayaran</p>
                    </div>
                </div>
                <div id="instrQRIS" style="display:none">
                    <div class="qris-box">
                        <img id="qrisImage" src="" alt="QRIS Code" class="qris-img">
                        <p style="font-size:12px;color:var(--text-secondary);line-height:1.6">Buka aplikasi e-wallet
                            (GoPay, OVO, Dana, LinkAja) atau Mobile Banking,<br>pilih scan QRIS, dan arahkan kamera ke
                            kode di atas.</p>
                    </div>
                </div>
                <div class="promo-label" style="margin-top:20px">Unggah Bukti Pembayaran <span
                        style="color:var(--red)">*</span></div>
                <label for="buktiPembayaranInput" class="bukti-upload-box" id="buktiUploadBox">
                    <i class="fa-solid fa-cloud-arrow-up"></i>
                    <span id="buktiUploadText">Klik untuk pilih file (JPG, PNG, atau PDF, maks 5MB)</span>
                </label>
                <input type="file" id="buktiPembayaranInput" accept=".jpg,.jpeg,.png,.pdf" style="display:none"
                    onchange="handleBuktiFileChange(this)">
                <div id="buktiPreviewWrap" style="display:none;margin-top:10px">
                    <img id="buktiPreviewImg" src="" alt="Preview Bukti Pembayaran"
                        style="max-width:100%;max-height:180px;border-radius:var(--radius-md);border:1.5px solid var(--border-color)">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary btn-full" onclick="backToBookingModal()"><i class="fa-solid fa-arrow-left"></i> Kembali</button>
                <button class="btn-primary btn-full" onclick="finishPayment()"><i class="fa-solid fa-circle-check"></i>
                    Saya Sudah Bayar</button>
            </div>
        </div>
    </div>
    <script>
        let selectedDate = '<?= $dateList[0]['value'] ?>';
        let selectedPaymentMethod = 'Transfer Bank';
        let isMember = <?= $has_member ? 'true' : 'false' ?>;
        let memberDiscount = <?= $member_discount ?>;
        let countdownInterval;
        let cart = []; // { idJadwal, courtId, courtName, price, tanggal, tanggalLabel, jamMulai, jamSelesai }
        let buktiFile = null; // File bukti pembayaran yang dipilih customer

        // ============ PAGINATION LAPANGAN ============
        const COURTS_PER_PAGE = 4;
        let currentCourtPage = 1;

        function paginateCourts() {
            const cards = Array.from(document.querySelectorAll('.court-card'));
            const totalPages = Math.max(1, Math.ceil(cards.length / COURTS_PER_PAGE));
            if (currentCourtPage > totalPages) currentCourtPage = totalPages;

            cards.forEach((card, idx) => {
                const page = Math.floor(idx / COURTS_PER_PAGE) + 1;
                card.style.display = (page === currentCourtPage) ? '' : 'none';
            });

            renderCourtPagination(totalPages);
        }

        function renderCourtPagination(totalPages) {
            const wrap = document.getElementById('courtPagination');
            if (!wrap) return;
            if (totalPages <= 1) { wrap.innerHTML = ''; return; }

            let html = `<button class="page-btn ${currentCourtPage <= 1 ? 'disabled' : ''}" onclick="changeCourtPage(${currentCourtPage - 1})" title="Sebelumnya"><i class="fa-solid fa-angle-left"></i></button>`;
            for (let i = 1; i <= totalPages; i++) {
                html += `<button class="page-btn ${i === currentCourtPage ? 'active' : ''}" onclick="changeCourtPage(${i})">${i}</button>`;
            }
            html += `<button class="page-btn ${currentCourtPage >= totalPages ? 'disabled' : ''}" onclick="changeCourtPage(${currentCourtPage + 1})" title="Selanjutnya"><i class="fa-solid fa-angle-right"></i></button>`;
            wrap.innerHTML = html;
        }

        function changeCourtPage(p) {
            if (p < 1) return;
            currentCourtPage = p;
            paginateCourts();
            document.querySelectorAll('.court-card').forEach(c => {
                if (c.style.display !== 'none') c.classList.add('visible');
            });
            const label = document.querySelector('.court-section-label');
            if (label) label.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        // ============ UPLOAD BUKTI PEMBAYARAN ============
        function handleBuktiFileChange(input) {
            const file = input.files[0];
            const box = document.getElementById('buktiUploadBox');
            const textEl = document.getElementById('buktiUploadText');
            const previewWrap = document.getElementById('buktiPreviewWrap');
            const previewImg = document.getElementById('buktiPreviewImg');

            if (!file) { buktiFile = null; return; }

            const allowed = ['image/jpeg', 'image/png', 'application/pdf'];
            if (!allowed.includes(file.type)) {
                Swal.fire({ icon: 'warning', title: 'Format Tidak Didukung', text: 'Gunakan file JPG, PNG, atau PDF.', confirmButtonColor: 'var(--orange)' });
                input.value = '';
                buktiFile = null;
                return;
            }
            if (file.size > 5 * 1024 * 1024) {
                Swal.fire({ icon: 'warning', title: 'Ukuran Terlalu Besar', text: 'Ukuran file maksimal 5MB.', confirmButtonColor: 'var(--orange)' });
                input.value = '';
                buktiFile = null;
                return;
            }

            buktiFile = file;
            box.classList.add('filled');
            textEl.innerText = file.name;

            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = e => {
                    previewImg.src = e.target.result;
                    previewWrap.style.display = 'block';
                };
                reader.readAsDataURL(file);
            } else {
                previewWrap.style.display = 'none';
            }
        }

        function resetBuktiUpload() {
            buktiFile = null;
            const inputEl = document.getElementById('buktiPembayaranInput');
            if (inputEl) inputEl.value = '';
            const box = document.getElementById('buktiUploadBox');
            if (box) box.classList.remove('filled');
            const textEl = document.getElementById('buktiUploadText');
            if (textEl) textEl.innerText = 'Klik untuk pilih file (JPG, PNG, atau PDF, maks 5MB)';
            const previewWrap = document.getElementById('buktiPreviewWrap');
            if (previewWrap) previewWrap.style.display = 'none';
        }

        function formatRupiah(n) {
            return 'Rp ' + Math.max(0, n).toLocaleString('id-ID');
        }

        function getSelectedDateLabel() {
            const chip = document.querySelector('.date-chip.active');
            if (!chip) return selectedDate;
            return `${chip.querySelector('.day-name').innerText}, ${chip.querySelector('.day-num').innerText} ${chip.querySelector('.month-name').innerText}`;
        }

        function selectDate(el, dateVal) {
            document.querySelectorAll('.date-chip').forEach(c => c.classList.remove('active'));
            el.classList.add('active');
            selectedDate = dateVal;
            reloadAllCourtSlots();
        }

        function reloadAllCourtSlots() {
            document.querySelectorAll('.court-card').forEach(card => loadCourtSlots(card.dataset.id));
        }

        function loadCourtSlots(courtId) {
            const countLabel = document.getElementById('slotsCount-' + courtId);
            countLabel.innerText = 'Memuat jadwal...';
            fetch(`booking_customer.php?action=get_all_slots&court_id=${courtId}&tanggal=${selectedDate}`)
                .then(r => r.json())
                .then(data => renderCourtSlots(courtId, data))
                .catch(() => { countLabel.innerText = 'Gagal memuat jadwal'; });
        }

        function toggleCourtSlots(courtId) {
            const grid = document.getElementById('courtSlots-' + courtId);
            const icon = document.getElementById('toggleIcon-' + courtId);
            grid.classList.toggle('open');
            icon.classList.toggle('rotated');
        }

        function renderCourtSlots(courtId, slots) {
            const grid = document.getElementById('courtSlots-' + courtId);
            const countLabel = document.getElementById('slotsCount-' + courtId);
            const availableCount = slots.filter(s => s.Status === 'tersedia').length;
            countLabel.innerText = availableCount + ' Jadwal Tersedia';

            if (slots.length === 0) {
                grid.innerHTML = '<div class="empty-state" style="grid-column:1/-1"><i class="fa-solid fa-calendar-xmark"></i><p>Tidak ada jadwal untuk tanggal ini.</p></div>';
                return;
            }

            const courtCard = document.getElementById('court-' + courtId);
            const price = parseFloat(courtCard.dataset.price);
            const courtName = courtCard.dataset.name;

            grid.innerHTML = '';
            slots.forEach((slot, index) => {
                const el = document.createElement('div');
                const isSelected = cart.some(c => c.idJadwal == slot.ID_Jadwal);
                el.className = 'mini-slot' + (slot.Status !== 'tersedia' ? ' ' + slot.Status : '') + (isSelected ? ' selected' : '');
                el.dataset.idJadwal = slot.ID_Jadwal;
                el.style.animationDelay = (index * 0.03) + 's';

                let statusLabel = 'Tersedia';
                if (slot.Status === 'dibooking') statusLabel = 'Sedang Dibooking';
                else if (slot.Status === 'lewat') statusLabel = 'Waktu Terlewat';

                el.innerHTML = `
            ${isSelected ? '<div class="mini-check"><i class="fa-solid fa-check"></i></div>' : ''}
            <div class="mini-time">${slot.Jam_Mulai} - ${slot.Jam_Selesai}</div>
            <div class="mini-status">${statusLabel}</div>
            <div class="mini-price">${formatRupiah(price)}</div>
        `;

                if (slot.Status === 'tersedia') {
                    el.addEventListener('click', () => toggleSlotSelect(el, courtId, courtName, price, slot.ID_Jadwal, slot.Jam_Mulai, slot.Jam_Selesai));
                }
                grid.appendChild(el);
            });
        }

        function toggleSlotSelect(el, courtId, courtName, price, idJadwal, jamMulai, jamSelesai) {
            const idx = cart.findIndex(c => c.idJadwal == idJadwal);
            if (idx >= 0) {
                cart.splice(idx, 1);
                el.classList.remove('selected');
                const chk = el.querySelector('.mini-check');
                if (chk) chk.remove();
            } else {
                cart.push({
                    idJadwal, courtId, courtName, price,
                    tanggal: selectedDate,
                    tanggalLabel: getSelectedDateLabel(),
                    jamMulai, jamSelesai
                });
                el.classList.add('selected');
                const check = document.createElement('div');
                check.className = 'mini-check';
                check.innerHTML = '<i class="fa-solid fa-check"></i>';
                el.prepend(check);
            }
            updateCartUI();
        }

        function removeFromCart(idJadwal) {
            const item = cart.find(c => c.idJadwal == idJadwal);
            cart = cart.filter(c => c.idJadwal != idJadwal);
            if (item) {
                const grid = document.getElementById('courtSlots-' + item.courtId);
                const el = grid ? grid.querySelector(`[data-id-jadwal="${idJadwal}"]`) : null;
                if (el) {
                    el.classList.remove('selected');
                    const chk = el.querySelector('.mini-check');
                    if (chk) chk.remove();
                }
            }
            updateCartUI();
            renderCartPanel();
        }

        function updateCartUI() {
            const fab = document.getElementById('cartFab');
            const badge = document.getElementById('cartBadge');
            if (cart.length > 0) {
                fab.classList.remove('hidden');
                badge.innerText = cart.length;
            } else {
                fab.classList.add('hidden');
            }
            if (document.getElementById('cartPanel').classList.contains('active')) {
                renderCartPanel();
            }
        }

        function renderCartPanel() {
            const body = document.getElementById('cartPanelBody');
            const footer = document.getElementById('cartTotalFooter');

            if (cart.length === 0) {
                body.innerHTML = '<div class="cart-empty"><i class="fa-solid fa-cart-shopping" style="font-size:36px"></i>Keranjang masih kosong.<br>Pilih jadwal yang tersedia untuk mulai booking.</div>';
                footer.style.display = 'none';
                return;
            }
            footer.style.display = 'block';

            const groups = {};
            cart.forEach(item => {
                if (!groups[item.courtName]) groups[item.courtName] = [];
                groups[item.courtName].push(item);
            });

            let html = '';
            Object.keys(groups).forEach(courtName => {
                html += `<div class="cart-group-title">${courtName}</div>`;
                groups[courtName].forEach(item => {
                    html += `
                <div class="cart-item">
                    <div class="cart-item-info">${item.tanggalLabel} &bull; ${item.jamMulai} - ${item.jamSelesai}<small>${formatRupiah(item.price)}</small></div>
                    <button class="cart-item-remove" onclick="removeFromCart(${item.idJadwal})"><i class="fa-solid fa-trash"></i></button>
                </div>
            `;
                });
            });
            body.innerHTML = html;

            const total = cart.reduce((s, c) => s + c.price, 0);
            document.getElementById('cartTotalValue').innerText = formatRupiah(total);
            document.getElementById('cartTotalCount').innerText = cart.length + ' jadwal dipilih';
        }

        function openCartPanel() {
            renderCartPanel();
            document.getElementById('cartPanel').classList.add('active');
            document.getElementById('cartPanelOverlay').classList.add('active');
        }

        function closeCartPanel() {
            document.getElementById('cartPanel').classList.remove('active');
            document.getElementById('cartPanelOverlay').classList.remove('active');
        }

        function openModal(id) {
            document.getElementById(id).classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
            document.body.style.overflow = '';
            if (id === 'paymentModal') clearInterval(countdownInterval);
        }

        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', function (e) { if (e.target === this) closeModal(this.id); });
        });

        function openBookingModalFromCart() {
            if (cart.length === 0) return;
            closeCartPanel();

            let html = '';
            cart.forEach(item => {
                html += `
            <div class="detail-row">
                <span class="detail-label">${item.courtName}<br>${item.tanggalLabel}, ${item.jamMulai}-${item.jamSelesai}</span>
                <span class="detail-value">${formatRupiah(item.price)}</span>
            </div>
        `;
            });
            document.getElementById('modalItemsList').innerHTML = html;

            const basePrice = cart.reduce((s, c) => s + c.price, 0);
            let discount = isMember ? memberDiscount : 0;
            const total = Math.max(0, basePrice - discount);

            if (!isMember && promoSelect) {
                promoSelect.selectedIndex = 0;
                document.getElementById('modalPromoDiscount').innerText = '-Rp 0';
            }
            document.getElementById('modalTotal').innerText = formatRupiah(total);
            openModal('bookingModal');
        }

        function selectPayment(el) {
            document.querySelectorAll('.payment-option').forEach(p => p.classList.remove('selected'));
            el.classList.add('selected');
            selectedPaymentMethod = el.dataset.method;
        }

        const promoSelect = document.getElementById('modalPromoSelect');
        if (promoSelect) {
            promoSelect.addEventListener('change', function () {
                const opt = this.options[this.selectedIndex];
                const discount = parseFloat(opt.getAttribute('data-discount') || 0);
                const basePrice = cart.reduce((s, c) => s + c.price, 0);
                const total = Math.max(0, basePrice - discount);
                document.getElementById('modalPromoDiscount').innerText = '-Rp ' + discount.toLocaleString('id-ID');
                document.getElementById('modalTotal').innerText = formatRupiah(total);
            });
        }

        function getCheckoutBreakdown() {
            const basePrice = cart.reduce((s, c) => s + c.price, 0);
            let discount = 0, idPromo = null;
            if (isMember) {
                discount = memberDiscount;
            } else if (promoSelect) {
                const opt = promoSelect.options[promoSelect.selectedIndex];
                if (opt && opt.value !== '0') {
                    discount = parseFloat(opt.getAttribute('data-discount') || 0);
                    idPromo = opt.value;
                }
            }
            return { total: Math.max(0, basePrice - discount), idPromo };
        }

        function confirmBooking() {
            const { total } = getCheckoutBreakdown();
            closeModal('bookingModal');
            document.getElementById('paymentTotal').innerText = formatRupiah(total);

            // Reset bukti pembayaran setiap kali membuka modal pembayaran baru
            resetBuktiUpload();

            // Tampilkan instruksi sesuai metode pembayaran yang sudah dipilih customer
            // (Transfer Bank -> Virtual Account, QRIS -> QRIS) tanpa mengubah pilihan metode itu sendiri
            showPaymentTab(selectedPaymentMethod === 'QRIS' ? 'qris' : 'va');
            openModal('paymentModal');
            startCountdown(15 * 60);
        }

        function showPaymentTab(tab) {
            document.getElementById('instrVA').style.display = tab === 'va' ? 'block' : 'none';
            document.getElementById('instrQRIS').style.display = tab === 'qris' ? 'block' : 'none';
            document.getElementById('tabVA').classList.toggle('active', tab === 'va');
            document.getElementById('tabQRIS').classList.toggle('active', tab === 'qris');
            if (tab === 'qris') {
                const totalText = document.getElementById('paymentTotal').innerText;
                const totalNum = parseInt(totalText.replace(/[^0-9]/g, ''));
                const firstId = cart.length ? cart[0].idJadwal : 0;
                document.getElementById('qrisImage').src = `https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=${encodeURIComponent('HOOPBALL-PAYMENT-' + firstId + '-' + totalNum)}`;
            }
        }

        function startCountdown(seconds) {
            clearInterval(countdownInterval);
            let remaining = seconds;
            const display = document.getElementById('countdown');
            countdownInterval = setInterval(() => {
                const m = String(Math.floor(remaining / 60)).padStart(2, '0');
                const s = String(remaining % 60).padStart(2, '0');
                display.innerText = `${m}:${s}`;
                if (--remaining < 0) { clearInterval(countdownInterval); display.innerText = 'Waktu Habis'; }
            }, 1000);
        }

        function copyVA() {
            const vaNum = document.getElementById('vaNumber').innerText;
            navigator.clipboard.writeText(vaNum).then(() => {
                Swal.fire({ icon: 'success', title: 'Berhasil Disalin!', text: 'Nomor VA telah disalin ke clipboard.', confirmButtonColor: 'var(--orange)', confirmButtonText: 'OK' });
            });
        }

        function finishPayment() {
            if (!buktiFile) {
                Swal.fire({ icon: 'warning', title: 'Bukti Pembayaran Wajib', text: 'Silakan unggah bukti pembayaran terlebih dahulu sebelum konfirmasi.', confirmButtonColor: 'var(--orange)', confirmButtonText: 'OK' });

function backToBookingModal() {
    closeModal('paymentModal');
    clearInterval(countdownInterval);
    openModal('bookingModal');
}
                return;
            }

            clearInterval(countdownInterval);
            closeModal('paymentModal');
            const { total, idPromo } = getCheckoutBreakdown();

            Swal.fire({ title: 'Memproses...', text: 'Sedang mengunggah bukti pembayaran Anda', allowOutsideClick: false, allowEscapeKey: false, didOpen: () => { Swal.showLoading(); } });

            const formData = new FormData();
            formData.append('id_jadwal_list', JSON.stringify(cart.map(c => c.idJadwal)));
            if (idPromo) formData.append('id_promo', idPromo);
            formData.append('metode_pembayaran', selectedPaymentMethod);
            formData.append('total_bayar', total);
            formData.append('bukti_pembayaran', buktiFile);

            fetch('booking_customer.php?action=checkout', {
                method: 'POST',
                body: formData
            })
                .then(r => r.json())
                .then(result => {
                    if (result.success) {
                        cart = [];
                        resetBuktiUpload();
                        updateCartUI();
                        Swal.fire({ icon: 'success', title: 'Booking Berhasil!', text: 'Bukti pembayaran Anda sedang diverifikasi oleh karyawan kami. Silakan cek riwayat booking di profil Anda.', confirmButtonColor: 'var(--orange)', confirmButtonText: 'Selesai' })
                            .then(() => { location.reload(); });
                    } else {
                        Swal.fire({ icon: 'warning', title: 'Booking Gagal', text: result.message, confirmButtonColor: 'var(--orange)', confirmButtonText: 'Pilih Ulang' });
                    }
                })
                .catch(() => {
                    Swal.fire({ icon: 'error', title: 'Koneksi Terputus', text: 'Gagal terhubung ke server. Periksa koneksi internet Anda.', confirmButtonColor: 'var(--orange)', confirmButtonText: 'Coba Lagi' });
                });
        }

        document.addEventListener('DOMContentLoaded', () => {
            reloadAllCourtSlots();
            paginateCourts();

            /* Entrance animation untuk kartu lapangan */
            const cardObserver = new IntersectionObserver((entries) => {
                entries.forEach((entry, index) => {
                    if (entry.isIntersecting) {
                        setTimeout(() => { entry.target.classList.add('visible'); }, index * 100);
                        cardObserver.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.1 });
            document.querySelectorAll('.court-card').forEach(card => cardObserver.observe(card));

            /* Reveal-on-scroll untuk header & label section */
            const revealObserver = new IntersectionObserver((entries) => {
                entries.forEach(e => { if (e.isIntersecting) e.target.classList.add('active'); });
            }, { threshold: .1, rootMargin: '0px 0px -50px 0px' });
            document.querySelectorAll('.reveal').forEach(el => revealObserver.observe(el));

            /* Page loader */
            const loader = document.getElementById('pageLoader');
            if (loader) {
                setTimeout(() => { loader.classList.add('hidden'); }, 500);
            }
        });

        /* Scroll progress bar */
        window.addEventListener('scroll', () => {
            const st = document.documentElement.scrollTop || document.body.scrollTop;
            const sh = document.documentElement.scrollHeight - document.documentElement.clientHeight;
            document.getElementById('scrollProgress').style.transform = `scaleX(${sh > 0 ? st / sh : 0})`;
        });

        const urlParams = new URLSearchParams(window.location.search);
        const status = urlParams.get('status'), msg = urlParams.get('msg');
        if (status && msg) {
            const ok = status === 'success';
            Swal.fire({ icon: ok ? 'success' : 'error', title: ok ? 'Berhasil' : 'Gagal', text: msg, confirmButtonColor: 'var(--orange)', confirmButtonText: 'OK' });
            window.history.replaceState({}, document.title, window.location.pathname);
        }
    

    /* ============================================================
   KONFIRMASI SEBELUM KELUAR (LOGOUT)
   Berlaku untuk semua link yang mengarah ke logout.php,
   di sidebar maupun di dropdown topbar, pada SEMUA halaman.
   ============================================================ */
(function () {
    const SWAL_CDN = 'https://cdn.jsdelivr.net/npm/sweetalert2@11';
    let swalLoading = null;

    // Muat SweetAlert2 secara otomatis bila halaman belum memuatnya
    // (mis. dashboard/view_admin.php) supaya tampilan dialog seragam.
    function ensureSwal() {
        if (typeof Swal !== 'undefined') return Promise.resolve();
        if (swalLoading) return swalLoading;

        swalLoading = new Promise(function (resolve, reject) {
            const s = document.createElement('script');
            s.src = SWAL_CDN;
            s.onload = resolve;
            s.onerror = reject;
            document.head.appendChild(s);
        });
        return swalLoading;
    }

    function showLogoutDialog(url) {
        Swal.fire({
            title: 'Keluar dari HoopBall?',
            html: 'Apakah Anda yakin ingin keluar?<br>' +
                  '<span style="font-size:12px;color:#6B7280;">Sesi Anda akan diakhiri dan Anda perlu masuk kembali.</span>',
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
                didOpen: function () { Swal.showLoading(); }
            });

            setTimeout(function () { window.location.href = url; }, 500);
        });
    }

    document.addEventListener('click', function (e) {
        const link = e.target.closest('a[href*="logout.php"]');
        if (!link) return;

        e.preventDefault();
        const url = link.getAttribute('href');

        ensureSwal()
            .then(function () { showLogoutDialog(url); })
            .catch(function () {
                // CDN tidak bisa diakses -> jangan biarkan logout tanpa konfirmasi
                if (confirm('Apakah Anda yakin ingin keluar?')) window.location.href = url;
            });
    });
})();
</script>
</body>

</html>