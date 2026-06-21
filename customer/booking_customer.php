<?php
// ============================================================================
// BUFFER OUTPUT — Agar header() bisa dipanggil kapan saja tanpa error
// ============================================================================
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../includes/auth_helper.php';
include '../includes/config.php'; // Berisi koneksi $conn menggunakan sqlsrv

// ============================================================================
// HARD DELETE AKUN CUSTOMER — Soft Delete di DB, Hard Delete di Program
// ============================================================================
if (isset($_GET['hapus_akun']) && $_GET['hapus_akun'] == '1') {
    $id_customer = $_SESSION['id_customer'] ?? $_SESSION['ID_Customer'] ?? $_SESSION['id_akun'] ?? '';

    if (!empty($id_customer)) {
        $modified_by = $_SESSION['nama'] ?? 'CUSTOMER';

        $stmt = sqlsrv_query($conn, 
            "UPDATE Customer SET 
                Is_Deleted = 1, 
                Status = 0, 
                Deleted_By = ?, 
                Deleted_Date = GETDATE() 
             WHERE ID_Customer = ? AND Is_Deleted = 0", 
            array($modified_by, $id_customer)
        );

        if ($stmt) {
            $_SESSION = array();
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params['path'], $params['domain'],
                    $params['secure'], $params['httponly']
                );
            }
            session_destroy();
            setcookie('remember_me', '', time() - 3600, "/");
            ob_end_clean();
            header("Location: ../login/login.php?status=success&msg=Akun Anda telah dihapus permanen. Anda harus mendaftar ulang untuk menggunakan layanan kami.");
            exit();
        } else {
            ob_end_clean();
            header("Location: booking_customer.php?status=error&msg=Gagal menghapus akun. Silakan coba lagi.");
            exit();
        }
    } else {
        ob_end_clean();
        header("Location: ../login/login.php?status=error&msg=Sesi tidak valid. Silakan login kembali.");
        exit();
    }
}

// ============================================================================
// CEK AKSES
// ============================================================================
cek_akses('customer');

// ============================================================================
// AMBIL DATA CUSTOMER DARI DATABASE SECARA DINAMIS
// ============================================================================
$id_customer = $_SESSION['id_customer'] ?? $_SESSION['ID_Customer'] ?? $_SESSION['id_akun'] ?? '';
$nama_customer = 'Pelanggan';
$photo_profile = '';

if (!empty($id_customer)) {
    $cek_deleted = sqlsrv_query($conn, 
        "SELECT Nama_Customer, Photo_Profile, Is_Deleted, Status FROM Customer WHERE ID_Customer = ?", 
        array($id_customer)
    );
    if ($cek_deleted) {
        $row_cust = sqlsrv_fetch_array($cek_deleted, SQLSRV_FETCH_ASSOC);
        if ($row_cust) {
            if ($row_cust['Is_Deleted'] == 1 || $row_cust['Status'] == 0) {
                $_SESSION = array();
                session_destroy();
                setcookie('remember_me', '', time() - 3600, "/");
                ob_end_clean();
                header("Location: ../login/login.php?status=error&msg=Akun Anda telah dihapus atau dinonaktifkan. Silakan hubungi admin atau daftar ulang.");
                exit();
            }
            $nama_customer = $row_cust['Nama_Customer'] ?? 'Pelanggan';
            $photo_profile = $row_cust['Photo_Profile'] ?? '';
        }
    }
}

// ============================================================================
// CHECK MEMBER STATUS
// ============================================================================
$member_data = null;
$member_check = sqlsrv_query($conn, 
    "SELECT TOP 1 L.*, T.Nama_Tipe, T.Potongan_Harga, T.Harga_Member
     FROM Langganan L 
     INNER JOIN Tipe_Member T ON L.ID_Tipe = T.ID_Tipe 
     WHERE L.ID_Customer = ? AND L.Status = 1 
     AND GETDATE() BETWEEN L.Tanggal_Mulai AND L.Tanggal_Selesai
     ORDER BY L.Tanggal_Selesai DESC", 
    array($id_customer)
);
if ($member_check) {
    $member_data = sqlsrv_fetch_array($member_check, SQLSRV_FETCH_ASSOC);
}
$has_member = !empty($member_data);
$member_tipe = $has_member ? $member_data['Nama_Tipe'] : '';
$member_discount = $has_member ? floatval($member_data['Potongan_Harga']) : 0;

// ============================================================================
// GENERATOR JADWAL OTOMATIS (Durasi Bermain: 1 JAM PER SLOT | Operasional 07:00 - 23:00)
// ============================================================================
function generateJadwalOtomatis($conn) {
    $q_lap = sqlsrv_query($conn, "SELECT ID_Lapangan FROM Lapangan WHERE Status = 1 AND Is_Deleted = 0");
    if ($q_lap === false) return;

    $daftar_lapangan = [];
    while ($row = sqlsrv_fetch_array($q_lap, SQLSRV_FETCH_ASSOC)) {
        $daftar_lapangan[] = $row['ID_Lapangan'];
    }

    $template_jam = [
        ['07:00:00', '08:00:00'], ['08:00:00', '09:00:00'], ['09:00:00', '10:00:00'],
        ['10:00:00', '11:00:00'], ['11:00:00', '12:00:00'], ['12:00:00', '13:00:00'],
        ['13:00:00', '14:00:00'], ['14:00:00', '15:00:00'], ['15:00:00', '16:00:00'],
        ['16:00:00', '17:00:00'], ['17:00:00', '18:00:00'], ['18:00:00', '19:00:00'],
        ['19:00:00', '20:00:00'], ['20:00:00', '21:00:00'], ['21:00:00', '22:00:00'],
        ['22:00:00', '23:00:00']
    ];

    for ($i = 0; $i < 7; $i++) {
        $target_date = date('Y-m-d', strtotime("+$i days"));
        foreach ($daftar_lapangan as $id_lapangan) {
            foreach ($template_jam as $jam) {
                $mulai = $jam[0]; $selesai = $jam[1];
                $cek_query = "SELECT ID_Jadwal FROM Jadwal WHERE ID_Lapangan = ? AND Tanggal = ? AND Jam_Mulai = ? AND Jam_Selesai = ?";
                $cek_stmt = sqlsrv_query($conn, $cek_query, array($id_lapangan, $target_date, $mulai, $selesai));
                if ($cek_stmt && !sqlsrv_fetch_array($cek_stmt, SQLSRV_FETCH_ASSOC)) {
                    $insert_query = "INSERT INTO Jadwal (ID_Lapangan, Tanggal, Jam_Mulai, Jam_Selesai, Status, Is_Deleted, Created_By, Created_Date) VALUES (?, ?, ?, ?, 1, 0, 'SYSTEM_AUTO', GETDATE())";
                    sqlsrv_query($conn, $insert_query, array($id_lapangan, $target_date, $mulai, $selesai));
                }
            }
        }
    }
}
generateJadwalOtomatis($conn);

// ============================================================================
// AJAX REQUEST HANDLERS
// ============================================================================
if (isset($_GET['action'])) {
    header('Content-Type: application/json');

    if ($_GET['action'] == 'get_slots' && isset($_GET['court_id'])) {
        $court_id = intval($_GET['court_id']);
        $queryJadwal = "SELECT ID_Jadwal, Tanggal, Jam_Mulai, Jam_Selesai FROM Jadwal WHERE ID_Lapangan = ? AND Status = 1 AND Is_Deleted = 0 AND ID_Jadwal NOT IN (SELECT ID_Jadwal FROM Booking) AND (Tanggal > CAST(GETDATE() AS DATE) OR (Tanggal = CAST(GETDATE() AS DATE) AND Jam_Mulai > CAST(GETDATE() AS TIME))) ORDER BY Tanggal ASC, Jam_Mulai ASC";
        $stmtJadwal = sqlsrv_query($conn, $queryJadwal, array($court_id));
        $slots = [];
        if ($stmtJadwal) {
            while ($row = sqlsrv_fetch_array($stmtJadwal, SQLSRV_FETCH_ASSOC)) {
                $tanggal_str = ($row['Tanggal'] instanceof DateTime) ? $row['Tanggal']->format('Y-m-d') : $row['Tanggal'];
                $jam_mulai = ($row['Jam_Mulai'] instanceof DateTime) ? $row['Jam_Mulai']->format('H:i') : substr($row['Jam_Mulai'], 0, 5);
                $jam_selesai = ($row['Jam_Selesai'] instanceof DateTime) ? $row['Jam_Selesai']->format('H:i') : substr($row['Jam_Selesai'], 0, 5);
                $slots[] = [
                    'ID_Jadwal' => $row['ID_Jadwal'],
                    'Tanggal' => $tanggal_str,
                    'Jam_Mulai' => $jam_mulai,
                    'Jam_Selesai' => $jam_selesai,
                    'Tanggal_Formatted' => date('d M Y', strtotime($tanggal_str))
                ];
            }
        }
        echo json_encode($slots);
        exit();
    }

    if ($_GET['action'] == 'checkout' && $_SERVER['REQUEST_METHOD'] == 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) $input = $_POST;

        $id_jadwal = intval($input['id_jadwal'] ?? 0);
        $id_promo = !empty($input['id_promo']) ? intval($input['id_promo']) : null;
        $metode_pembayaran = htmlspecialchars($input['metode_pembayaran'] ?? '');
        $total_bayar = floatval($input['total_bayar'] ?? 0);

        if ($id_jadwal <= 0 || empty($metode_pembayaran) || $total_bayar <= 0) {
            echo json_encode(['success' => false, 'message' => 'Parameter input tidak valid.']);
            exit();
        }

        if (sqlsrv_begin_transaction($conn) === false) {
            echo json_encode(['success' => false, 'message' => 'Gagal menginisiasi sesi transaksi database.']);
            exit();
        }

        try {
            $queryCheck = "SELECT Status, ID_Lapangan FROM Jadwal WHERE ID_Jadwal = ?";
            $stmtCheck = sqlsrv_query($conn, $queryCheck, array($id_jadwal));
            $jadwal = null;
            if ($stmtCheck) $jadwal = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);
            if (!$jadwal || $jadwal['Status'] != 1) throw new Exception("Maaf, slot jadwal ini sudah terbooking atau tidak tersedia.");

            $queryKaryawan = "SELECT TOP 1 ID_Karyawan FROM Karyawan WHERE Status = 1 AND Is_Deleted = 0 ORDER BY ID_Karyawan ASC";
            $stmtKary = sqlsrv_query($conn, $queryKaryawan, array());
            $id_karyawan = 1;
            if ($stmtKary) {
                $kary = sqlsrv_fetch_array($stmtKary, SQLSRV_FETCH_ASSOC);
                if ($kary) $id_karyawan = $kary['ID_Karyawan'];
            }

            $created_by = $_SESSION['nama'] ?? 'CUSTOMER';
            $queryInsert = "INSERT INTO Booking (ID_Customer, ID_Karyawan, ID_Jadwal, ID_Promo, Tanggal_Booking, Metode_Pembayaran, Total_Bayar, Status, Created_By, Created_Date) VALUES (?, ?, ?, ?, CAST(GETDATE() AS DATE), ?, ?, 0, ?, GETDATE())";
            $stmtInsert = sqlsrv_query($conn, $queryInsert, array($id_customer, $id_karyawan, $id_jadwal, $id_promo, $metode_pembayaran, $total_bayar, $created_by));

            if ($stmtInsert === false) {
                $db_errors = sqlsrv_errors();
                $customer_friendly_error = "Terjadi kendala koneksi database (Kode: " . ($db_errors[0]['code'] ?? 0) . "). Silakan hubungi operator kami untuk bantuan.";
                throw new Exception($customer_friendly_error);
            }

            $queryUpdateJadwal = "UPDATE Jadwal SET Status = 0, Modified_By = ?, Modified_Date = GETDATE() WHERE ID_Jadwal = ?";
            $stmtUpdate = sqlsrv_query($conn, $queryUpdateJadwal, array($created_by, $id_jadwal));
            if ($stmtUpdate === false) throw new Exception("Gagal merubah status jadwal sewa.");

            sqlsrv_commit($conn);
            echo json_encode(['success' => true, 'message' => 'Pemesanan berhasil dibuat!']);
        } catch (Exception $e) {
            sqlsrv_rollback($conn);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }
}

// ===========================================================================
// LOAD DATA MASTER
// ===========================================================================
$lapanganList = [];
$queryLapangan = sqlsrv_query($conn, "SELECT ID_Lapangan, Nama_Lapangan, Harga_Sewa, Photo_Lapangan FROM Lapangan WHERE Status = 1 AND Is_Deleted = 0");
if ($queryLapangan) {
    while ($row = sqlsrv_fetch_array($queryLapangan, SQLSRV_FETCH_ASSOC)) {
        $lapanganList[] = $row;
    }
}

$lapanganFasilitas = [];
$queryFasilitas = sqlsrv_query($conn, "SELECT ID_Lapangan, Nama_Fasilitas FROM Fasilitas_Lapangan WHERE Status = 1 AND Is_Deleted = 0");
if ($queryFasilitas) {
    while ($row = sqlsrv_fetch_array($queryFasilitas, SQLSRV_FETCH_ASSOC)) {
        $lapanganFasilitas[$row['ID_Lapangan']][] = $row['Nama_Fasilitas'];
    }
}

$promos = [];
if (!$has_member) {
    $queryPromo = sqlsrv_query($conn, "SELECT ID_Promo, Nama_Promo, Diskon FROM Promo WHERE Status = 1 AND Is_Deleted = 0 AND CAST(GETDATE() AS DATE) BETWEEN Tanggal_Mulai AND Tanggal_Selesai");
    if ($queryPromo) {
        while ($row = sqlsrv_fetch_array($queryPromo, SQLSRV_FETCH_ASSOC)) {
            $promos[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Lapangan | HoopBall Arena</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Barlow+Condensed:wght@700;800;900&family=Barlow:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root {
            --primary: #FF5200;
            --primary-hover: #E04800;
            --primary-light: rgba(255, 82, 0, 0.1);
            --dark-bg: #0B0B0C;
            --card-dark: #121214;
            --text-gray: #8E8E93;
            --text-dark: #1C1C1E;
            --border-color: #E5E5EA;
            --white: #FFFFFF;
            --light-bg: #F8F9FA;
            --green: #34C759;
            --green-lt: rgba(52,199,89,.10);
            --yellow: #FFCC00;
            --yellow-lt: rgba(255,204,0,.10);
            --red: #FF3B30;
            --red-lt: rgba(255,59,48,.10);
            --blue: #007AFF;
            --blue-lt: rgba(0,122,255,.10);
            --transition-smooth: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --orange: #FF5A1F;
            --orange-hover: #E0440E;
            --orange-lt: rgba(255, 90, 31, 0.06);
            --orange-glow: rgba(255, 90, 31, 0.15);
            --border: #E2E8F0;
            --border-lt: #F1F5F9;
            --text-primary: #0F172A;
            --text-secondary: #475569;
            --muted: #94A3B8;
            --bg: #F8FAFC;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg);
            color: var(--text-primary);
            -webkit-font-smoothing: antialiased;
            overflow-x: hidden;
        }

        /* ============ KEYFRAMES ============ */
        @keyframes fadeInUp { from{opacity:0;transform:translateY(40px)} to{opacity:1;transform:translateY(0)} }
        @keyframes fadeInDown { from{opacity:0;transform:translateY(-30px)} to{opacity:1;transform:translateY(0)} }
        @keyframes fadeInLeft { from{opacity:0;transform:translateX(-40px)} to{opacity:1;transform:translateX(0)} }
        @keyframes fadeInRight { from{opacity:0;transform:translateX(40px)} to{opacity:1;transform:translateX(0)} }
        @keyframes fadeIn { from{opacity:0} to{opacity:1} }
        @keyframes scaleIn { from{opacity:0;transform:scale(0.8)} to{opacity:1;transform:scale(1)} }
        @keyframes slideInUp { from{opacity:0;transform:translateY(60px) scale(0.95)} to{opacity:1;transform:translateY(0) scale(1)} }
        @keyframes float { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-10px)} }
        @keyframes pulse { 0%,100%{transform:scale(1);box-shadow:0 0 0 0 rgba(255,82,0,0.4)} 50%{transform:scale(1.05);box-shadow:0 0 0 15px rgba(255,82,0,0)} }
        @keyframes shimmer { 0%{background-position:-200% 0} 100%{background-position:200% 0} }
        @keyframes bounceIn { 0%{opacity:0;transform:scale(0.3)} 50%{opacity:1;transform:scale(1.05)} 70%{transform:scale(0.9)} 100%{transform:scale(1)} }
        @keyframes rotateIn { from{opacity:0;transform:rotate(-180deg) scale(0.5)} to{opacity:1;transform:rotate(0) scale(1)} }
        @keyframes gradientShift { 0%{background-position:0% 50%} 50%{background-position:100% 50%} 100%{background-position:0% 50%} }
        @keyframes ripple { 0%{transform:scale(1);opacity:1} 100%{transform:scale(1.5);opacity:0} }
        @keyframes glow { 0%,100%{box-shadow:0 0 5px rgba(255,82,0,0.3)} 50%{box-shadow:0 0 25px rgba(255,82,0,0.6),0 0 50px rgba(255,82,0,0.2)} }
        @keyframes drawLine { from{width:0} to{width:60px} }
        @keyframes wave { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-15px)} }
        @keyframes spinSlow { from{transform:rotate(0deg)} to{transform:rotate(360deg)} }
        @keyframes countUp { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }
        @keyframes shake { 0%,100%{transform:translateX(0)} 25%{transform:translateX(-5px)} 75%{transform:translateX(5px)} }
        @keyframes borderGlow { 0%,100%{border-color:rgba(255,82,0,0.1)} 50%{border-color:rgba(255,82,0,0.4)} }
        @keyframes textReveal { from{clip-path:inset(0 100% 0 0)} to{clip-path:inset(0 0 0 0)} }
        @keyframes iconPop { 0%{transform:scale(0)} 60%{transform:scale(1.2)} 100%{transform:scale(1)} }
        @keyframes neonPulse { 0%,100%{text-shadow:0 0 5px rgba(255,82,0,0.5),0 0 10px rgba(255,82,0,0.3)} 50%{text-shadow:0 0 10px rgba(255,82,0,0.8),0 0 20px rgba(255,82,0,0.5),0 0 30px rgba(255,82,0,0.3)} }
        @keyframes slideDown { from{transform:translateY(-100%);opacity:0} to{transform:translateY(0);opacity:1} }
        @keyframes zoomIn { from{transform:scale(0.5);opacity:0} to{transform:scale(1);opacity:1} }
        @keyframes flipX { from{transform:perspective(400px) rotateX(90deg);opacity:0} to{transform:perspective(400px) rotateX(0);opacity:1} }
        @keyframes flipY { from{transform:perspective(400px) rotateY(90deg);opacity:0} to{transform:perspective(400px) rotateY(0);opacity:1} }
        @keyframes swing { 0%{transform:rotate(0)} 20%{transform:rotate(15deg)} 40%{transform:rotate(-10deg)} 60%{transform:rotate(5deg)} 80%{transform:rotate(-5deg)} 100%{transform:rotate(0)} }
        @keyframes rubberBand { 0%{transform:scale(1)} 30%{transform:scale(1.25,0.75)} 40%{transform:scale(0.75,1.25)} 50%{transform:scale(1.15,0.85)} 65%{transform:scale(0.95,1.05)} 75%{transform:scale(1.05,0.95)} 100%{transform:scale(1)} }
        @keyframes heartBeat { 0%{transform:scale(1)} 14%{transform:scale(1.3)} 28%{transform:scale(1)} 42%{transform:scale(1.3)} 70%{transform:scale(1)} }
        @keyframes jello { 0%,100%{transform:skewX(0) skewY(0)} 22.2%{transform:skewX(-12.5deg) skewY(-12.5deg)} 33.3%{transform:skewX(6.25deg) skewY(6.25deg)} 44.4%{transform:skewX(-3.125deg) skewY(-3.125deg)} 55.5%{transform:skewX(1.5625deg) skewY(1.5625deg)} 66.6%{transform:skewX(-0.78125deg) skewY(-0.78125deg)} 77.7%{transform:skewX(0.390625deg) skewY(0.390625deg)} 88.8%{transform:skewX(-0.1953125deg) skewY(-0.1953125deg)} }
        @keyframes rollIn { from{opacity:0;transform:translateX(-100%) rotate(-120deg)} to{opacity:1;transform:translateX(0) rotate(0)} }
        @keyframes jackInTheBox { from{opacity:0;transform:scale(0.1) rotate(30deg);transform-origin:center bottom} 50%{transform:rotate(-10deg)} 70%{transform:rotate(3deg)} to{opacity:1;transform:scale(1)} }
        @keyframes lightSpeedIn { from{transform:translate3d(100%,0,0) skewX(-30deg);opacity:0} 60%{transform:skewX(20deg);opacity:1} 80%{transform:skewX(-5deg)} to{transform:translate3d(0,0,0)} }

        /* ============ ANIMATION CLASSES ============ */
        .anim-hidden { opacity:0; }
        .anim-fade-up { animation:fadeInUp 0.8s cubic-bezier(0.16,1,0.3,1) forwards; }
        .anim-fade-down { animation:fadeInDown 0.8s cubic-bezier(0.16,1,0.3,1) forwards; }
        .anim-fade-left { animation:fadeInLeft 0.8s cubic-bezier(0.16,1,0.3,1) forwards; }
        .anim-fade-right { animation:fadeInRight 0.8s cubic-bezier(0.16,1,0.3,1) forwards; }
        .anim-scale-in { animation:scaleIn 0.6s cubic-bezier(0.34,1.56,0.64,1) forwards; }
        .anim-slide-up { animation:slideInUp 0.9s cubic-bezier(0.16,1,0.3,1) forwards; }
        .anim-bounce-in { animation:bounceIn 0.8s cubic-bezier(0.68,-0.55,0.265,1.55) forwards; }
        .anim-rotate-in { animation:rotateIn 0.7s cubic-bezier(0.16,1,0.3,1) forwards; }
        .anim-text-reveal { animation:textReveal 1s cubic-bezier(0.16,1,0.3,1) forwards; }
        .anim-zoom-in { animation:zoomIn 0.5s cubic-bezier(0.16,1,0.3,1) forwards; }
        .anim-flip-x { animation:flipX 0.6s cubic-bezier(0.16,1,0.3,1) forwards; }
        .anim-flip-y { animation:flipY 0.6s cubic-bezier(0.16,1,0.3,1) forwards; }
        .anim-swing { animation:swing 1s ease forwards; }
        .anim-rubber { animation:rubberBand 1s ease forwards; }
        .anim-heart { animation:heartBeat 1.3s ease-in-out forwards; }
        .anim-jello { animation:jello 0.9s ease forwards; }
        .anim-roll-in { animation:rollIn 0.6s ease forwards; }
        .anim-jack-in { animation:jackInTheBox 0.8s ease forwards; }
        .anim-light-speed { animation:lightSpeedIn 0.8s ease forwards; }
        .anim-neon { animation:neonPulse 2s ease-in-out infinite; }

        .delay-100 { animation-delay:0.1s; }
        .delay-200 { animation-delay:0.2s; }
        .delay-300 { animation-delay:0.3s; }
        .delay-400 { animation-delay:0.4s; }
        .delay-500 { animation-delay:0.5s; }
        .delay-600 { animation-delay:0.6s; }
        .delay-700 { animation-delay:0.7s; }
        .delay-800 { animation-delay:0.8s; }
        .delay-900 { animation-delay:0.9s; }
        .delay-1000 { animation-delay:1.0s; }
        .delay-1200 { animation-delay:1.2s; }
        .delay-1500 { animation-delay:1.5s; }
        .delay-2000 { animation-delay:2.0s; }

        /* ============ INTERSECTION OBSERVER ============ */
        .reveal { opacity:0; transform:translateY(40px); transition:all 0.8s cubic-bezier(0.16,1,0.3,1); }
        .reveal.active { opacity:1; transform:translateY(0); }
        .reveal-left { opacity:0; transform:translateX(-50px); transition:all 0.8s cubic-bezier(0.16,1,0.3,1); }
        .reveal-left.active { opacity:1; transform:translateX(0); }
        .reveal-right { opacity:0; transform:translateX(50px); transition:all 0.8s cubic-bezier(0.16,1,0.3,1); }
        .reveal-right.active { opacity:1; transform:translateX(0); }
        .reveal-scale { opacity:0; transform:scale(0.9); transition:all 0.7s cubic-bezier(0.16,1,0.3,1); }
        .reveal-scale.active { opacity:1; transform:scale(1); }
        .reveal-stagger .stagger-item { opacity:0; transform:translateY(30px); transition:all 0.6s cubic-bezier(0.16,1,0.3,1); }
        .reveal-stagger.active .stagger-item { opacity:1; transform:translateY(0); }
        .reveal-stagger.active .stagger-item:nth-child(1){transition-delay:0s}
        .reveal-stagger.active .stagger-item:nth-child(2){transition-delay:0.1s}
        .reveal-stagger.active .stagger-item:nth-child(3){transition-delay:0.2s}
        .reveal-stagger.active .stagger-item:nth-child(4){transition-delay:0.3s}
        .reveal-stagger.active .stagger-item:nth-child(5){transition-delay:0.4s}
        .reveal-flip .stagger-item { opacity:0; transform:perspective(1000px) rotateY(90deg); transition:all 0.7s cubic-bezier(0.16,1,0.3,1); }
        .reveal-flip.active .stagger-item { opacity:1; transform:perspective(1000px) rotateY(0); }
        .reveal-flip.active .stagger-item:nth-child(1){transition-delay:0s}
        .reveal-flip.active .stagger-item:nth-child(2){transition-delay:0.15s}
        .reveal-flip.active .stagger-item:nth-child(3){transition-delay:0.3s}
        .reveal-flip.active .stagger-item:nth-child(4){transition-delay:0.45s}
        .reveal-flip.active .stagger-item:nth-child(5){transition-delay:0.6s}
        .reveal-zoom .stagger-item { opacity:0; transform:scale(0.5); transition:all 0.6s cubic-bezier(0.34,1.56,0.64,1); }
        .reveal-zoom.active .stagger-item { opacity:1; transform:scale(1); }
        .reveal-zoom.active .stagger-item:nth-child(1){transition-delay:0s}
        .reveal-zoom.active .stagger-item:nth-child(2){transition-delay:0.1s}
        .reveal-zoom.active .stagger-item:nth-child(3){transition-delay:0.2s}
        .reveal-zoom.active .stagger-item:nth-child(4){transition-delay:0.3s}

        /* ============ NAVBAR ============ */
        nav { background:var(--white); padding:0 80px; display:flex; justify-content:space-between; align-items:center; height:76px; position:sticky; top:0; z-index:1000; border-bottom:1px solid #E5E5EA; animation:fadeInDown 0.6s ease-out forwards; }
        .nav-logo { display:flex; align-items:center; text-decoration:none; gap:10px; transition:transform 0.3s ease; }
        .nav-logo:hover { transform:scale(1.05); }
        .nav-logo img { height:70px; width:auto; transition:transform 0.5s cubic-bezier(0.34,1.56,0.64,1); }
        .nav-logo:hover img { transform:rotate(5deg) scale(1.1); }
        .nav-logo span { color:#1C1C1E; font-size:20px; font-weight:800; letter-spacing:-0.5px; }
        .nav-links { display:flex; align-items:center; gap:8px; }
        .nav-links a { color:#636366; text-decoration:none; font-size:14px; font-weight:500; padding:8px 16px; border-radius:20px; transition:all 0.3s cubic-bezier(0.16,1,0.3,1); position:relative; overflow:hidden; }
        .nav-links a::before { content:''; position:absolute; bottom:0; left:50%; width:0; height:2px; background:var(--primary); transition:all 0.3s cubic-bezier(0.16,1,0.3,1); transform:translateX(-50%); }
        .nav-links a:hover { color:#1C1C1E; transform:translateY(-2px); }
        .nav-links a:hover::before { width:60%; }
        .nav-links a.active { color:var(--primary); font-weight:600; }
        .nav-links a.active::before { width:60%; }

        .nav-user-container { position:relative; height:76px; display:flex; align-items:center; }
        .nav-user { background:#F2F2F7; border:1px solid #E5E5EA; padding:8px 16px; border-radius:50px; color:#1C1C1E; font-size:14px; font-weight:600; cursor:pointer; display:flex; align-items:center; gap:10px; transition:all 0.3s cubic-bezier(0.16,1,0.3,1); }
        .nav-user:hover { background:#E5E5EA; border-color:var(--primary); transform:scale(1.02); box-shadow:0 4px 12px rgba(255,82,0,0.15); }
        .nav-user img.user-avatar { width:24px; height:24px; border-radius:50%; object-fit:cover; transition:transform 0.3s ease; }
        .nav-user:hover img.user-avatar { transform:scale(1.15); }
        .nav-user i.user-icon { font-size:16px; color:var(--primary); transition:transform 0.3s ease; }
        .nav-user:hover i.user-icon { transform:scale(1.2); }
        .nav-user i.arrow { font-size:11px; color:#8E8E93; transition:0.3s cubic-bezier(0.34,1.56,0.64,1); }
        .nav-user-container:hover i.arrow { transform:rotate(180deg); color:var(--primary); }
        .dropdown-menu { position:absolute; top:85%; right:0; background:#16161a; min-width:220px; border-radius:12px; border:1px solid #2d2d33; box-shadow:0 10px 30px rgba(0,0,0,0.5); padding:8px 0; display:none; z-index:1001; transform-origin:top right; }
        .nav-user-container:hover .dropdown-menu { display:block; animation:fadeInUp 0.3s cubic-bezier(0.16,1,0.3,1) forwards; }
        .dropdown-menu .user-info-header { padding:12px 20px; border-bottom:1px solid #2d2d33; margin-bottom:6px; animation:fadeInDown 0.3s ease-out; }
        .dropdown-menu .user-info-header span { display:block; }
        .dropdown-menu .user-info-header .u-name { color:var(--white); font-size:14px; font-weight:700; }
        .dropdown-menu .user-info-header .u-role { color:var(--text-gray); font-size:11px; text-transform:uppercase; letter-spacing:0.5px; margin-top:2px; }
        .dropdown-menu a { display:flex; align-items:center; gap:12px; padding:10px 20px; color:#c5c5ca; text-decoration:none; font-size:13px; font-weight:500; transition:all 0.25s cubic-bezier(0.16,1,0.3,1); position:relative; overflow:hidden; }
        .dropdown-menu a::after { content:''; position:absolute; left:0; top:0; width:3px; height:100%; background:var(--primary); transform:scaleY(0); transition:transform 0.25s cubic-bezier(0.16,1,0.3,1); }
        .dropdown-menu a i { font-size:14px; width:16px; text-align:center; transition:transform 0.3s ease; }
        .dropdown-menu a:hover { background:#222227; color:var(--primary); padding-left:28px; }
        .dropdown-menu a:hover::after { transform:scaleY(1); }
        .dropdown-menu a:hover i { transform:scale(1.2); }
        .dropdown-divider { height:1px; background:#2d2d33; margin:6px 0; }
        .dropdown-menu a.logout:hover { color:#ff3b30; }
        .dropdown-menu a.logout:hover::after { background:#ff3b30; }

        .member-badge-nav { display:inline-flex; align-items:center; gap:4px; background:var(--primary); color:var(--white); font-size:10px; font-weight:700; padding:2px 8px; border-radius:12px; margin-left:4px; }

        /* Animasi Transisi Halus */
        @keyframes fadeInDown { from { opacity: 0; transform: translateY(-30px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(40px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }

        /* ---- CONTAINER ---- */
        .container { width: 100%; max-width: 95%; margin: 40px auto; padding: 0 20px; display: flex; flex-direction: column; gap: 24px; }

        .section-header { margin-bottom: 20px; }
        .section-title { font-size: 16px; font-weight: 800; color: var(--text-primary); display: flex; align-items: center; gap: 8px; }
        .section-subtitle { font-size: 12px; color: var(--muted); margin-top: 4px; font-weight: 500; }

        /* Court Selection Grid */
        .court-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 24px; margin-bottom: 30px; }

        .court-card {
            border: 1px solid var(--border);
            border-radius: 14px;
            overflow: hidden;
            background: #fff;
            cursor: pointer;
            position: relative;
            transition: all 0.2s ease;
            opacity: 0;
            transform: translateY(30px);
        }
        .court-card.reveal { opacity: 0; transform: translateY(30px); transition: all 0.6s cubic-bezier(0.16,1,0.3,1); }
        .court-card.reveal.active { opacity: 1; transform: translateY(0); }
        .court-card:hover { border-color: var(--orange); box-shadow: 0 4px 12px var(--orange-glow); transform: translateY(-5px); }
        .court-card.selected { border-color: var(--orange); box-shadow: 0 0 0 2px var(--orange); }

        .court-img-wrapper { position: relative; height: 200px; background: #cbd5e1; overflow: hidden; }
        .court-img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.6s cubic-bezier(0.16,1,0.3,1); }
        .court-card:hover .court-img { transform: scale(1.08); }

        .badge-available {
            position: absolute;
            bottom: 12px;
            left: 12px;
            background: var(--green-lt);
            color: #34C759;
            font-size: 10px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 20px;
            border: 1px solid rgba(52, 199, 89, 0.2);
            animation: fadeIn 0.5s ease-out;
        }

        .court-info { padding: 16px; }
        .court-name { font-size: 15px; font-weight: 700; color: var(--text-primary); }
        .court-price { font-size: 14px; font-weight: 700; color: var(--orange); margin: 6px 0 12px; }
        .court-perk-list { list-style: none; display: flex; flex-direction: column; gap: 6px; }
        .court-perk-item { display: flex; align-items: center; gap: 8px; font-size: 11.5px; color: var(--text-secondary); }
        .court-perk-item i { color: var(--muted); width: 14px; text-align: center; }

        .divider { height: 1px; background: var(--border-lt); margin: 25px 0; }

        /* Schedule slot select */
        .schedule-controls { display: flex; flex-direction: column; gap: 16px; align-items: stretch; margin-bottom: 16px; }
        .input-group { flex: 1; min-width: 250px; display: flex; flex-direction: column; gap: 6px; }
        .input-label { font-size: 11.5px; font-weight: 700; color: var(--text-primary); }
        .input-wrapper { position: relative; display: flex; align-items: center; }
        .input-wrapper i { position: absolute; left: 14px; color: var(--muted); font-size: 14px; pointer-events: none; }

        .form-control {
            width: 100%;
            padding: 11px 40px 11px 40px; 
            border: 1px solid var(--border);
            border-radius: 10px;
            font-family: inherit;
            font-size: 13px;
            color: var(--text-primary);
            background-color: #fff;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2394A3B8' stroke-width='2.5'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M19.5 8.25l-7.5 7.5-7.5-7.5'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
            background-size: 14px;
            outline: none;
            appearance: none;
            transition: all 0.3s cubic-bezier(0.16,1,0.3,1);
        }
        .form-control:focus { border-color: var(--orange); box-shadow: 0 0 0 3px var(--orange-glow); }

        /* Status Availability */
        .status-availability-box {
            background: var(--green-lt);
            border: 1px solid rgba(52, 199, 89, 0.15);
            border-radius: 12px;
            padding: 10px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            min-height: 48px;
            transition: all 0.3s ease;
        }
        .status-availability-box.empty { background: var(--red-lt); border-color: rgba(255, 59, 48, 0.15); }
        .status-avail-icon { color: var(--green); font-size: 18px; transition: transform 0.3s ease; }
        .status-availability-box.empty .status-avail-icon { color: var(--red); }
        .status-avail-title { font-size: 12px; font-weight: 700; color: #065F46; }
        .status-availability-box.empty .status-avail-title { color: #991B1B; }
        .status-avail-desc { font-size: 10px; color: #047857; margin-top: 2px; }
        .status-availability-box.empty .status-avail-desc { color: #B91C1C; }

        .alert-banner {
            background: #EFF6FF;
            border: 1px solid rgba(0, 122, 255, 0.15);
            border-radius: 10px;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 16px;
            animation: fadeInUp 0.5s ease-out;
        }
        .alert-banner i { color: var(--blue); font-size: 16px; animation: float 2s ease-in-out infinite; }
        .alert-banner-text { font-size: 11.5px; color: #1E40AF; line-height: 1.5; }

        /* ---- POP-UP MODAL STYLE ---- */
        .booking-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            padding: 20px;
            animation: fadeInModal 0.25s ease-out forwards;
        }
        @keyframes fadeInModal { from { opacity: 0; } to { opacity: 1; } }

        .summary-card {
            background: #fff;
            border-radius: 20px !important;
            border: none !important;
            padding: 30px !important;
            width: 100%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15) !important;
            animation: slideInModal 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
        @keyframes slideInModal { from { transform: translateY(30px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

        .booking-modal-close {
            position: absolute;
            top: 20px;
            right: 20px;
            background: var(--border-lt);
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: var(--text-secondary);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 10;
        }
        .booking-modal-close:hover { background: var(--red-lt); color: var(--red); transform: rotate(90deg); }

        .btn-trigger-modal {
            display: flex;
            width: 100%;
            max-width: 100%;
            margin: 24px 0 0 0;
            background: var(--orange);
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 12px 20px;
            font-family: inherit;
            font-size: 13.5px;
            font-weight: 700;
            cursor: pointer;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.3s cubic-bezier(0.16,1,0.3,1);
            position: relative;
            overflow: hidden;
        }
        .btn-trigger-modal::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            transform: translate(-50%,-50%);
            transition: width 0.6s, height 0.6s;
        }
        .btn-trigger-modal:hover::before { width: 300px; height: 300px; }
        .btn-trigger-modal:hover:not(:disabled) { background: var(--orange-hover); transform: translateY(-2px); box-shadow: 0 8px 25px rgba(255,90,31,0.4); }
        .btn-trigger-modal:disabled { background: var(--muted); cursor: not-allowed; }

        .summary-title {
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 16px;
            font-weight: 800;
            letter-spacing: 0.5px;
            color: var(--muted);
            margin-bottom: 20px;
            text-transform: uppercase;
        }

        .summary-item-info { display: flex; gap: 14px; margin-bottom: 20px; }
        .summary-thumb { width: 70px; height: 70px; border-radius: 10px; overflow: hidden; background: #e2e8f0; flex-shrink: 0; }
        .summary-thumb img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.3s ease; }
        .summary-thumb:hover img { transform: scale(1.1); }
        .summary-details { display: flex; flex-direction: column; justify-content: center; }
        .summary-court-name { font-size: 15px; font-weight: 700; color: var(--text-primary); }
        .summary-venue { font-size: 11px; color: var(--muted); margin-bottom: 6px; font-weight: 500; }
        .summary-meta { font-size: 11.5px; color: var(--text-secondary); display: flex; align-items: center; gap: 6px; margin-top: 2px; font-weight: 500; }
        .summary-meta i { font-size: 11px; color: var(--muted); width: 14px; }

        /* Member Discount Area */
        .member-block { border-top: 1px solid var(--border-lt); padding: 16px 0; }
        .member-status-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
        .member-status-label { font-size: 12.5px; font-weight: 700; color: var(--text-primary); }
        .badge-member-active { background: var(--green-lt); color: var(--green); font-size: 10px; font-weight: 800; padding: 4px 10px; border-radius: 20px; display: inline-flex; align-items: center; gap: 4px; animation: pulse 2s ease-in-out infinite; }
        .badge-member-inactive { background: #FFF3CD; color: #D97706; font-size: 10px; font-weight: 800; padding: 4px 10px; border-radius: 20px; display: inline-flex; align-items: center; gap: 4px; }
        .member-text-congratulations { font-size: 11px; color: var(--muted); margin-bottom: 12px; }
        .discount-row { display: flex; justify-content: space-between; font-size: 12.5px; }
        .discount-label { color: var(--text-secondary); font-weight: 500; }
        .discount-val { color: var(--green); font-weight: 700; }

        /* Promo Styles */
        .promo-warning-box {
            background: #FFF3CD;
            border: 1px solid rgba(245, 158, 11, 0.2);
            border-radius: 10px;
            padding: 10px 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 16px;
            animation: fadeInUp 0.4s ease-out;
        }
        .promo-warning-box i { color: #D97706; font-size: 14px; animation: swing 1s ease-in-out; }
        .promo-warning-text { font-size: 11px; color: #B45309; line-height: 1.4; font-weight: 500; }

        .promo-input-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 20px; }
        .promo-input-wrapper { position: relative; display: flex; align-items: center; }
        .promo-input-wrapper i.prefix-icon { position: absolute; left: 14px; color: var(--muted); font-size: 13px; }
        .promo-input-wrapper i.lock-icon { position: absolute; right: 14px; color: var(--muted); font-size: 13px; }
        .promo-input {
            width: 100%;
            padding: 10px 36px;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 12.5px;
            color: var(--text-primary);
            outline: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .promo-input:focus { border-color: var(--orange); box-shadow: 0 0 0 3px var(--orange-glow); }
        .promo-input:disabled { background: #F8FAFC; color: var(--muted); cursor: not-allowed; }

        /* Price Breakdown */
        .pricing-breakdown { border-top: 1px solid var(--border-lt); padding: 16px 0; display: flex; flex-direction: column; gap: 10px; }
        .price-row { display: flex; justify-content: space-between; font-size: 12.5px; color: var(--text-secondary); font-weight: 500; transition: all 0.3s ease; }
        .price-row:hover { transform: translateX(5px); }
        .price-row.total-row { margin-top: 6px; font-size: 14px; color: var(--text-primary); font-weight: 800; align-items: center; }
        .price-row.total-row .total-amount { font-size: 20px; color: var(--orange); font-weight: 900; animation: countUp 0.5s ease-out; }

        /* Payment Methods */
        .payment-section { border-top: 1px solid var(--border-lt); padding: 20px 0 10px; }
        .payment-header { font-size: 12.5px; font-weight: 700; color: var(--text-primary); margin-bottom: 12px; display: flex; align-items: center; gap: 6px; }
        .payment-header i { color: var(--muted); }
        .payment-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .payment-card {
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s cubic-bezier(0.16,1,0.3,1);
            user-select: none;
            position: relative;
            overflow: hidden;
        }
        .payment-card::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255,90,31,0.1);
            border-radius: 50%;
            transform: translate(-50%,-50%);
            transition: width 0.4s, height 0.4s;
        }
        .payment-card:hover::before { width: 200px; height: 200px; }
        .payment-card:hover { border-color: var(--orange); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(255,90,31,0.1); }
        .payment-card.selected { border-color: var(--orange); background: var(--orange-lt); }

        .custom-radio {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            border: 1.5px solid var(--muted);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: 0.2s;
        }
        .payment-card.selected .custom-radio { border-color: var(--orange); }
        .custom-radio::after { content: ''; width: 8px; height: 8px; border-radius: 50%; background: var(--orange); display: none; }
        .payment-card.selected .custom-radio::after { display: block; animation: scaleIn 0.2s ease-out; }

        .payment-card-content { display: flex; flex-direction: column; justify-content: center; }
        .payment-name { font-size: 11px; font-weight: 700; color: var(--text-primary); line-height: 1.3; }
        .payment-sub { font-size: 9px; color: var(--muted); margin-top: 1px; font-weight: 500; }
        .qris-logo { font-family: 'Barlow Condensed', sans-serif; font-weight: 900; font-size: 14px; color: #000; letter-spacing: -0.5px; }

        .btn-booking {
            width: 100%;
            background: var(--orange);
            color: #fff;
            border: none;
            border-radius: 12px;
            padding: 14px;
            font-family: inherit;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 16px;
            transition: all 0.3s cubic-bezier(0.16,1,0.3,1);
            position: relative;
            overflow: hidden;
        }
        .btn-booking::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            transform: translate(-50%,-50%);
            transition: width 0.6s, height 0.6s;
        }
        .btn-booking:hover::before { width: 400px; height: 400px; }
        .btn-booking:hover:not(:disabled) { background: var(--orange-hover); transform: translateY(-2px); box-shadow: 0 10px 30px rgba(255,90,31,0.4); }
        .btn-booking:disabled { background: var(--muted); cursor: not-allowed; }

        .booking-disclaimer {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            font-size: 11px;
            color: var(--muted);
            margin-top: 10px;
            font-weight: 500;
        }
        .booking-disclaimer i { color: var(--green); animation: pulse 2s ease-in-out infinite; }

        .swal-toast { border-radius: 12px !important; font-family: 'Plus Jakarta Sans', sans-serif !important; }

        /* ---- FOOTER ---- */
        footer { background: var(--dark-bg); color: #8E8E93; padding: 80px 80px 40px; border-top: 1px solid #1C1C1E; position: relative; overflow: hidden; }
        footer::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 1px; background: linear-gradient(90deg, transparent, var(--primary), transparent); animation: shimmer 3s linear infinite; background-size: 200% 100%; }
        .footer-grid { display: grid; grid-template-columns: 1.5fr 1fr 1fr 1.2fr; gap: 40px; margin-bottom: 60px; }
        .footer-logo { display: flex; align-items: center; gap: 10px; margin-bottom: 16px; transition: transform 0.3s ease; }
        .footer-logo:hover { transform: scale(1.05); }
        .footer-logo img { height: 70px; transition: transform 0.5s ease; }
        .footer-logo:hover img { transform: rotate(5deg); }
        .footer-logo span { color: var(--white); font-size: 20px; font-weight: 800; }
        .footer-desc { font-size: 13px; line-height: 1.6; margin-bottom: 24px; }
        .social-links { display: flex; gap: 12px; }
        .social-btn { width: 36px; height: 36px; border-radius: 50%; background: #1C1C1E; color: var(--white); display: flex; align-items: center; justify-content: center; text-decoration: none; transition: all 0.3s cubic-bezier(0.34,1.56,0.64,1); }
        .social-btn:hover { background: var(--primary); transform: translateY(-3px) scale(1.1); box-shadow: 0 8px 20px rgba(255,82,0,0.3); }
        .social-btn:active { transform: scale(0.95); }
        .footer-col h4 { color: var(--white); font-size: 15px; font-weight: 700; margin-bottom: 20px; position: relative; display: inline-block; }
        .footer-col h4::after { content: ''; position: absolute; bottom: -4px; left: 0; width: 30px; height: 2px; background: var(--primary); transition: width 0.3s ease; }
        .footer-col:hover h4::after { width: 100%; }
        .footer-col ul { list-style: none; }
        .footer-col ul li { margin-bottom: 12px; }
        .footer-col ul li a { color: #8E8E93; text-decoration: none; font-size: 13px; transition: all 0.3s ease; display: inline-block; position: relative; }
        .footer-col ul li a::after { content: ''; position: absolute; bottom: -2px; left: 0; width: 0; height: 1px; background: var(--primary); transition: width 0.3s ease; }
        .footer-col ul li a:hover { color: var(--white); transform: translateX(5px); }
        .footer-col ul li a:hover::after { width: 100%; }
        .contact-item { display: flex; gap: 12px; font-size: 13px; line-height: 1.5; margin-bottom: 16px; transition: all 0.3s ease; padding: 4px; border-radius: 6px; }
        .contact-item:hover { background: rgba(255,82,0,0.05); transform: translateX(5px); }
        .contact-item i { color: var(--primary); font-size: 14px; margin-top: 3px; transition: transform 0.3s ease; }
        .contact-item:hover i { transform: scale(1.2); }
        .footer-bottom { border-top: 1px solid #1C1C1E; padding-top: 30px; text-align: center; font-size: 13px; position: relative; }

        /* Media queries responsive navbar */
        @media (max-width: 1200px) { nav { padding: 0 40px; } .footer-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 768px) {
            nav { flex-direction: column; height: auto; padding: 15px 20px; gap: 15px; }
            .nav-links { flex-wrap: wrap; justify-content: center; gap: 4px; }
            .nav-user-container { height: auto; }
            .dropdown-menu { top: 50px; right: 50%; transform: translateX(50%); }
            .footer-grid { grid-template-columns: 1fr; }
            .court-grid { grid-template-columns: 1fr; }
        }

        /* 1. Menghilangkan scrollbar secara global */
        ::-webkit-scrollbar { display: none; }
        html, body, .summary-card { -ms-overflow-style: none; scrollbar-width: none; }

        /* ============ SCROLL PROGRESS BAR ============ */
        .scroll-progress { position: fixed; top: 0; left: 0; height: 3px; background: linear-gradient(90deg, var(--primary), #FF8C42); z-index: 9999; transform-origin: left; transform: scaleX(0); transition: transform 0.1s ease-out; }

        /* ============ CARD SHINE ============ */
        .card-shine { position: relative; overflow: hidden; }
        .card-shine::before { content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 100%; background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent); transition: left 0.6s ease; z-index: 10; pointer-events: none; }
        .card-shine:hover::before { left: 100%; }

        /* ============ HOVER LIFT ============ */
        .hover-lift { transition: all 0.4s cubic-bezier(0.16,1,0.3,1); }
        .hover-lift:hover { transform: translateY(-5px); box-shadow: 0 15px 30px rgba(0,0,0,0.1); }

        /* ============ GLOWING BORDER ============ */
        .glow-border { animation: borderGlow 2s ease-in-out infinite; }

        /* ============ SMOOTH SCROLL ============ */
        html { scroll-behavior: smooth; }

        /* ============ CUSTOM SCROLLBAR ============ */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; }
        ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--primary-hover); }

        /* ============ SELECTION COLOR ============ */
        ::selection { background: rgba(255,82,0,0.3); color: #1C1C1E; }

        /* ============ FOCUS STYLES ============ */
        :focus-visible { outline: 2px solid var(--primary); outline-offset: 2px; }

        /* ============ REDUCED MOTION ============ */
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation-duration: 0.01ms !important; animation-iteration-count: 1 !important; transition-duration: 0.01ms !important; }
        }
    </style>
</head>
<body>

<!-- SCROLL PROGRESS BAR -->
<div class="scroll-progress" id="scrollProgress"></div>

<!-- NAVBAR -->
<nav>
    <a href="view_customer.php" class="nav-logo">
        <img src="../asset/image/logo2.png" alt="HoopBall">
    </a>
    <div class="nav-links">
        <a href="view_customer.php">Beranda</a>
        <a href="booking_customer.php" class="active">Booking</a>
        <a href="pembatalan_customer.php">Pembatalan</a>
        <a href="langganan_customer.php">Member</a>
        <a href="pembelian_customer.php">Pembelian</a>
        <a href="#">Tentang</a>
        <a href="#">Kontak</a>
    </div>

    <div class="nav-user-container">
        <div class="nav-user">
            <?php if (!empty($photo_profile) && file_exists($photo_profile)): ?>
                <img src="<?php echo htmlspecialchars($photo_profile); ?>" alt="Avatar" class="user-avatar">
            <?php else: ?>
                <i class="fa-solid fa-circle-user user-icon"></i>
            <?php endif; ?>
            <span><?php echo htmlspecialchars($nama_customer); ?></span>
            <?php if ($has_member): ?>
                <span class="member-badge-nav"><i class="fa-solid fa-crown"></i> <?php echo htmlspecialchars($member_tipe); ?></span>
            <?php endif; ?>
            <i class="fa-solid fa-chevron-down arrow"></i>
        </div>
        <div class="dropdown-menu">
            <div class="user-info-header">
                <span class="u-name"><?php echo htmlspecialchars($nama_customer); ?></span>
                <span class="u-role">Customer <?php echo $has_member ? '• Member ' . htmlspecialchars($member_tipe) : ''; ?></span>
            </div>
            <a href="../profile/profile_customer.php"><i class="fa-solid fa-user"></i> Profil Saya</a>
            <a href="booking_customer.php"><i class="fa-solid fa-calendar-check"></i> Riwayat Booking</a>
            <a href="../customer/langganan_customer.php"><i class="fa-solid fa-crown"></i> Langganan Member</a>
            <div class="dropdown-divider"></div>
            <a href="#" onclick="confirmHapusAkun(event)" style="color: #ff3b30;"><i class="fa-solid fa-trash-can"></i> Hapus Akun</a>
            <a href="../login/logout.php" class="logout"><i class="fa-solid fa-right-from-bracket"></i> Keluar</a>
        </div>
    </div>
</nav>

<div class="container">
    
    <!-- 1. Pilih Lapangan -->
    <div class="section-header reveal">
        <h2 class="section-title"><i class="fa-solid fa-basketball" style="color: var(--primary);"></i> Pilih Lapangan</h2>
        <p class="section-subtitle">Pilih lapangan basket yang aktif dan tersedia. Klik sekali untuk memilih lapangan, klik sekali lagi untuk melanjutkan.</p>
    </div>

    <div class="court-grid reveal-stagger">
        <?php if (!empty($lapanganList)): ?>
            <?php foreach ($lapanganList as $index => $lap): 
                $courtId = $lap['ID_Lapangan'];
                $courtName = htmlspecialchars($lap['Nama_Lapangan']);
                $courtPrice = floatval($lap['Harga_Sewa']);
                $isSelected = ($index === 0) ? 'selected' : '';
                $imgUrl = !empty($lap['Photo_Lapangan']) ? htmlspecialchars($lap['Photo_Lapangan']) : 'https://images.unsplash.com/photo-1544698310-74ea9d1c8258?q=80&w=600&auto=format&fit=crop';
            ?>
                <div class="court-card stagger-item card-shine <?= $isSelected ?>" 
                     data-id="<?= $courtId ?>" 
                     data-price="<?= $courtPrice ?>" 
                     data-name="<?= $courtName ?>" 
                     data-img="<?= $imgUrl ?>">
                    <div class="court-img-wrapper">
                        <img src="<?= $imgUrl ?>" alt="<?= $courtName ?>" class="court-img">
                        <span class="badge-available">Tersedia</span>
                    </div>
                    <div class="court-info">
                        <h3 class="court-name"><?= $courtName ?></h3>
                        <p class="court-price">Rp <?= number_format($courtPrice, 0, ',', '.') ?> / jam</p>
                        <ul class="court-perk-list">
                            <?php if (isset($lapanganFasilitas[$courtId])): ?>
                                <?php foreach ($lapanganFasilitas[$courtId] as $fas): ?>
                                    <li class="court-perk-item">
                                        <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($fas) ?>
                                    </li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li class="court-perk-item"><i class="fa-solid fa-basketball"></i> Bola Basket Standar</li>
                                <li class="court-perk-item"><i class="fa-solid fa-lightbulb"></i> Pencahayaan Terang</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="grid-column: span 3; text-align: center; color: var(--muted); padding: 40px;">Tidak ada lapangan aktif yang tersedia saat ini.</p>
        <?php endif; ?>
    </div>
</div>

<!-- POP-UP MODAL 1: PILIH JADWAL -->
<div class="booking-modal-overlay" id="scheduleModal">
    <div class="summary-card" style="max-width: 600px;">
        <button class="booking-modal-close" id="btnCloseSchedule">
            <i class="fa-solid fa-xmark"></i>
        </button>
        
        <div class="section-header">
            <h2 class="section-title"><i class="fa-solid fa-calendar-days" style="color: var(--primary);"></i> Pilih Jadwal Bermain</h2>
            <p class="section-subtitle">Tentukan waktu bermain berdasarkan ketersediaan jadwal lapangan</p>
        </div>

        <div class="schedule-controls">
            <div class="input-group">
                <label class="input-label">Pilih Tanggal Bermain</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-calendar-days"></i>
                    <select id="dateSelect" class="form-control">
                        <option value="">Silakan pilih tanggal...</option>
                    </select>
                </div>
            </div>

            <div class="input-group">
                <label class="input-label">Pilih Jam Bermain</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-clock"></i>
                    <select id="timeSelect" class="form-control" disabled>
                        <option value="">Pilih tanggal terlebih dahulu...</option>
                    </select>
                </div>
            </div>

            <div class="status-availability-box" id="availabilityBox">
                <div class="status-avail-icon" id="availIcon"><i class="fa-solid fa-circle-check"></i></div>
                <div>
                    <div class="status-avail-title" id="availTitle">Memuat slot...</div>
                    <div class="status-avail-desc" id="lblDuration">Durasi: - jam</div>
                </div>
            </div>
        </div>

        <div class="alert-banner">
            <i class="fa-solid fa-circle-info"></i>
            <p class="alert-banner-text">Semua transaksi sewa disesuaikan dengan daftar slot jadwal yang dibuat oleh pihak operator HoopBall Arena.</p>
        </div>

        <button class="btn-trigger-modal" id="btnGoToSummary" disabled>
            Lanjut ke Tinjau & Pembayaran <i class="fa-solid fa-arrow-right"></i>
        </button>
    </div>
</div>

<!-- POP-UP MODAL 2: KONTEN RINGKASAN BOOKING & METODE PEMBAYARAN -->
<div class="booking-modal-overlay" id="bookingModal">
    <div class="summary-card">
        <button class="booking-modal-close" id="btnCloseSummary">
            <i class="fa-solid fa-xmark"></i>
        </button>
        
        <h2 class="summary-title">Ringkasan Booking</h2>
        
        <div class="summary-item-info">
            <div class="summary-thumb">
                <img id="sumImg" src="" alt="Thumbnail">
            </div>
            <div class="summary-details">
                <div class="summary-court-name" id="sumCourtName">-</div>
                <div class="summary-venue">HoopBall Arena</div>
                <div class="summary-meta"><i class="fa-solid fa-calendar"></i> <span id="sumPlayDate">-</span></div>
                <div class="summary-meta"><i class="fa-solid fa-clock"></i> <span id="sumTimeLabel">-</span></div>
            </div>
        </div>

        <div class="member-block">
            <div class="member-status-header">
                <span class="member-status-label">Status Member</span>
                <?php if ($has_member): ?>
                    <span class="badge-member-active">Member <?php echo htmlspecialchars($member_tipe); ?> <i class="fa-solid fa-crown"></i></span>
                <?php else: ?>
                    <span class="badge-member-inactive">Bukan Member <i class="fa-solid fa-user"></i></span>
                <?php endif; ?>
            </div>
            
            <?php if ($has_member): ?>
                <p class="member-text-congratulations">Selamat! Anda berhak mendapatkan potongan harga member aktif.</p>
                <div class="discount-row">
                    <span class="discount-label">Diskon Member (<?php echo htmlspecialchars($member_tipe); ?>)</span>
                    <span class="discount-val" id="lblDiscountPercent">-Rp <?php echo number_format($member_discount, 0, ',', '.'); ?></span>
                </div>
            <?php else: ?>
                <p class="member-text-congratulations">Gunakan kode promo aktif jika Anda bukan member.</p>
            <?php endif; ?>
        </div>

        <?php if ($has_member): ?>
            <div class="promo-warning-box">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <div class="promo-warning-text">Promo dinonaktifkan.<br>Potongan member otomatis diterapkan.</div>
            </div>
            <div class="promo-input-group">
                <label class="input-label">Promo</label>
                <div class="promo-input-wrapper">
                    <i class="fa-solid fa-ticket prefix-icon"></i>
                    <input type="text" class="promo-input" value="Tidak dapat digunakan" readonly disabled>
                    <i class="fa-solid fa-lock lock-icon"></i>
                </div>
            </div>
        <?php else: ?>
            <div class="promo-input-group">
                <label class="input-label">Gunakan Promo Aktif</label>
                <div class="promo-input-wrapper">
                    <i class="fa-solid fa-ticket prefix-icon"></i>
                    <select id="promoSelect" class="form-control" style="padding-left: 36px; padding-right: 14px;">
                        <option value="0" data-discount="0">-- Pilih Promo Tersedia --</option>
                        <?php foreach ($promos as $pro): ?>
                            <option value="<?php echo $pro['ID_Promo']; ?>" data-discount="<?php echo floatval($pro['Diskon']); ?>">
                                <?php echo htmlspecialchars($pro['Nama_Promo']); ?> (-Rp <?php echo number_format($pro['Diskon'], 0, ',', '.'); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        <?php endif; ?>

        <div class="pricing-breakdown">
            <div class="price-row">
                <span id="lblNormalPriceLabel">Harga Sewa</span>
                <span id="lblNormalPrice">Rp 0</span>
            </div>
            <?php if ($has_member): ?>
                <div class="price-row">
                    <span>Potongan Member</span>
                    <span class="discount-val" id="lblDiscountBreakdown">-Rp <?php echo number_format($member_discount, 0, ',', '.'); ?></span>
                </div>
            <?php else: ?>
                <div class="price-row">
                    <span>Potongan Promo</span>
                    <span class="discount-val" id="lblPromoBreakdown">-Rp 0</span>
                </div>
            <?php endif; ?>
            <div class="price-row total-row">
                <span>Total Pembayaran</span>
                <span class="total-amount" id="lblTotalPrice">Rp 0</span>
            </div>
        </div>

        <div class="payment-section">
            <div class="payment-header">
                <i class="fa-solid fa-wallet"></i> Metode Pembayaran
            </div>
            <div class="payment-grid">
                <div class="payment-card selected" data-method="Transfer Bank">
                    <div class="custom-radio"></div>
                    <div class="payment-card-content">
                        <span class="payment-name">Transfer Bank</span>
                        <span class="payment-sub">Virtual Account</span>
                    </div>
                </div>
                <div class="payment-card" data-method="QRIS">
                    <div class="custom-radio"></div>
                    <div class="payment-card-content">
                        <span class="payment-name qris-logo">QRIS</span>
                        <span class="payment-sub">Scan & Bayar Instan</span>
                    </div>
                </div>
            </div>
        </div>

        <button class="btn-booking" id="btnSubmit" disabled>
            <i class="fa-solid fa-lock"></i> Selesaikan Booking
        </button>
        <div class="booking-disclaimer">
            <i class="fa-solid fa-circle-check"></i> Enkripsi data aman terverifikasi
        </div>
    </div>
</div>

<!-- MODAL 3: INSTRUKSI PEMBAYARAN -->
<div class="booking-modal-overlay" id="paymentInstructionModal">
    <div class="summary-card" style="max-width: 460px; text-align: center;">
        <h2 class="summary-title" style="margin-bottom: 12px; text-align: center;">Instruksi Pembayaran</h2>

        <div style="display: flex; gap: 6px; margin-bottom: 16px; background: var(--border-lt); padding: 4px; border-radius: 10px; border: 1px solid var(--border);">
            <button id="btnSwitchVA" style="flex: 1; padding: 10px; border: none; border-radius: 8px; font-family: inherit; font-size: 12px; font-weight: 700; cursor: pointer; transition: var(--transition-smooth); background: transparent; color: var(--text-secondary);">
                <i class="fa-solid fa-university" style="margin-right: 4px;"></i> Virtual Account
            </button>
            <button id="btnSwitchQRIS" style="flex: 1; padding: 10px; border: none; border-radius: 8px; font-family: inherit; font-size: 12px; font-weight: 700; cursor: pointer; transition: var(--transition-smooth); background: transparent; color: var(--text-secondary);">
                <i class="fa-solid fa-qrcode" style="margin-right: 4px;"></i> QRIS Scan
            </button>
        </div>
        
        <div class="alert-banner" style="background: var(--orange-lt); border: 1px solid rgba(255, 90, 31, 0.15); margin-top: 0; margin-bottom: 20px; justify-content: center;">
            <i class="fa-solid fa-clock" style="color: var(--orange); animation: pulse 2s ease-in-out infinite;"></i>
            <p class="alert-banner-text" style="color: var(--orange-hover); font-weight: 700; font-size: 12px;">
                Selesaikan pembayaran dalam <span id="paymentCountdown">15:00</span>
            </p>
        </div>

        <div style="background: var(--bg); padding: 14px 18px; border-radius: 12px; margin-bottom: 20px; border: 1px solid var(--border); animation: fadeInUp 0.5s ease-out;">
            <div style="font-size: 11px; color: var(--text-secondary); font-weight: 600; text-transform: uppercase;">Total Tagihan</div>
            <div id="paymentTotalAmount" style="font-size: 24px; color: var(--orange); font-weight: 900; margin-top: 4px;">Rp 0</div>
        </div>

        <div id="instruksiTransfer" style="display: none;">
            <div style="font-size: 12.5px; font-weight: 700; color: var(--text-primary); margin-bottom: 8px; text-align: left;">Nomor Virtual Account (Mandiri / BCA)</div>
            <div style="display: flex; gap: 8px; margin-bottom: 16px;">
                <input type="text" id="vaNumber" value="8801281234567890" class="form-control" style="padding: 10px 14px; font-weight: 800; text-align: center; font-size: 15px; letter-spacing: 1px; color: var(--text-primary); border-color: var(--border);" readonly>
                <button class="btn-toast-action" id="btnCopyVA" style="border-radius: 10px; font-size: 12px;"><i class="fa-regular fa-copy"></i> Salin</button>
            </div>
            <ul style="text-align: left; font-size: 11.5px; color: var(--text-secondary); padding-left: 20px; line-height: 1.6; display: flex; flex-direction: column; gap: 6px;">
                <li>Pilih menu <strong>Transfer > Virtual Account</strong> pada aplikasi M-Banking atau ATM Anda.</li>
                <li>Masukkan nomor Virtual Account kustom di atas.</li>
                <li>Nominal pembayaran akan otomatis muncul sesuai total tagihan.</li>
            </ul>
        </div>

        <div id="instruksiQRIS" style="display: none; align-items: center; flex-direction: column;">
            <div style="font-size: 12.5px; font-weight: 700; color: var(--text-primary); margin-bottom: 12px;">Pindai Kode QRIS Resmi HoopBall</div>
            <div style="background: #fff; padding: 12px; border: 1px solid var(--border); border-radius: 12px; width: fit-content; margin-bottom: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); animation: fadeInUp 0.5s ease-out;">
                <img id="qrisImage" src="" alt="QRIS Code" style="display: block; width: 170px; height: 180px; object-fit: contain;">
            </div>
            <ul style="text-align: left; font-size: 11.5px; color: var(--text-secondary); padding-left: 20px; line-height: 1.6; display: flex; flex-direction: column; gap: 6px; width: 100%;">
                <li>Buka aplikasi e-wallet Anda (GoPay, OVO, Dana, LinkAja) atau Mobile Banking.</li>
                <li>Pilih opsi <strong>Scan / Bayar QRIS</strong>.</li>
                <li>Arahkan kamera smartphone ke kode QR di atas, lalu selesaikan pembayaran.</li>
            </ul>
        </div>

        <hr class="divider" style="margin: 20px 0;">
        
        <button class="btn-booking" id="btnDonePayment" style="margin-top: 0;">
            Saya Sudah Bayar <i class="fa-solid fa-circle-check"></i>
        </button>
    </div>
</div>

<!-- FOOTER -->
<footer>
    <div class="footer-grid">
        <div>
            <div class="footer-logo">
                <img src="../asset/image/logo.png" alt="HoopBall">
            </div>
            <p class="footer-desc">HoopBall adalah platform penyewaan lapangan basket online yang mudah, cepat, dan terpercaya.</p>
            <div class="social-links">
                <a href="#" class="social-btn"><i class="fa-brands fa-instagram"></i></a>
                <a href="#" class="social-btn"><i class="fa-brands fa-facebook-f"></i></a>
                <a href="#" class="social-btn"><i class="fa-brands fa-tiktok"></i></a>
                <a href="#" class="social-btn"><i class="fa-brands fa-youtube"></i></a>
            </div>
        </div>

        <div class="footer-col">
            <h4>Navigasi</h4>
            <ul>
                <li><a href="view_customer.php">Beranda</a></li>
                <li><a href="booking_customer.php">Booking</a></li>
                <li><a href="pembatalan_customer.php">Pembatalan</a></li>
                <li><a href="#">Member</a></li>
                <li><a href="#">Pembelian</a></li>
            </ul>
        </div>

        <div class="footer-col">
            <h4>Informasi</h4>
            <ul>
                <li><a href="#">Cara Booking</a></li>
                <li><a href="#">Syarat & Ketentuan</a></li>
                <li><a href="#">Kebijakan Privasi</a></li>
                <li><a href="#">FAQ</a></li>
            </ul>
        </div>

        <div class="footer-col">
            <h4>Hubungi Kami</h4>
            <div class="contact-item">
                <i class="fa-solid fa-location-dot"></i>
                Jl. Olahraga No. 10, Kebayoran Baru, Jakarta Selatan 12190
            </div>
            <div class="contact-item">
                <i class="fa-solid fa-phone"></i>
                +62 812-3456-7890
            </div>
            <div class="contact-item">
                <i class="fa-solid fa-envelope"></i>
                info@hoopball.id
            </div>
            <div class="contact-item">
                <i class="fa-solid fa-clock"></i>
                Setiap hari 07:00 - 23:00 WIB
            </div>
        </div>
    </div>
    <div class="footer-bottom">
        <p>&copy; 2025 HoopBall. All rights reserved.</p>
    </div>
</footer>

<script>
    // Scroll Progress Bar
    window.addEventListener('scroll', () => {
        const scrollTop = document.documentElement.scrollTop || document.body.scrollTop;
        const scrollHeight = document.documentElement.scrollHeight - document.documentElement.clientHeight;
        const scrolled = scrollTop / scrollHeight;
        document.getElementById('scrollProgress').style.transform = `scaleX(${scrolled})`;
    });

    // Intersection Observer for reveal animations
    const observerOptions = { threshold: 0.1, rootMargin: '0px 0px -50px 0px' };
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('active');
            }
        });
    }, observerOptions);

    document.querySelectorAll('.reveal, .reveal-left, .reveal-right, .reveal-scale, .reveal-stagger, .reveal-flip, .reveal-zoom').forEach(el => {
        observer.observe(el);
    });

    // State management
    let selectedCourtId = null;
    let selectedCourtPrice = 0;
    let selectedCourtName = '';
    let selectedCourtImg = '';
    let isMember = <?php echo $has_member ? 'true' : 'false'; ?>;
    let memberDiscount = <?php echo $member_discount; ?>;
    let selectedSlotId = null;
    let selectedSlotDuration = 0; 
    let selectedSlotDateFormatted = '';
    let selectedSlotTimeFormatted = '';
    let selectedPaymentMethod = 'Transfer Bank';

    const courts = document.querySelectorAll('.court-card');
    const dateSelect = document.getElementById('dateSelect');
    const timeSelect = document.getElementById('timeSelect');
    const promoSelect = document.getElementById('promoSelect');
    const payments = document.querySelectorAll('.payment-card');
    
    const availabilityBox = document.getElementById('availabilityBox');
    const availTitle = document.getElementById('availTitle');
    const availIcon = document.getElementById('availIcon');
    const lblDuration = document.getElementById('lblDuration');
    const sumCourtName = document.getElementById('sumCourtName');
    const sumImg = document.getElementById('sumImg');
    const sumPlayDate = document.getElementById('sumPlayDate');
    const sumTimeLabel = document.getElementById('sumTimeLabel');
    const lblNormalPriceLabel = document.getElementById('lblNormalPriceLabel');
    const lblNormalPrice = document.getElementById('lblNormalPrice');
    const lblPromoBreakdown = document.getElementById('lblPromoBreakdown');
    const lblTotalPrice = document.getElementById('lblTotalPrice');
    const btnSubmit = document.getElementById('btnSubmit');

    const scheduleModal = document.getElementById('scheduleModal');
    const btnCloseSchedule = document.getElementById('btnCloseSchedule');
    const btnGoToSummary = document.getElementById('btnGoToSummary');
    const bookingModal = document.getElementById('bookingModal');
    const btnCloseSummary = document.getElementById('btnCloseSummary');

    function formatRupiah(number) {
        return 'Rp ' + Math.max(0, number).toLocaleString('id-ID');
    }

    btnCloseSchedule.addEventListener('click', function() {
        scheduleModal.style.display = 'none';
    });

    btnGoToSummary.addEventListener('click', function() {
        scheduleModal.style.display = 'none';
        bookingModal.style.display = 'flex';
    });

    btnCloseSummary.addEventListener('click', function() {
        bookingModal.style.display = 'none';
    });

    window.addEventListener('click', function(e) {
        if (e.target === scheduleModal) scheduleModal.style.display = 'none';
        if (e.target === bookingModal) bookingModal.style.display = 'none';
    });

    function loadSlots(courtId) {
        dateSelect.innerHTML = '<option value="">Memuat tanggal...</option>';
        timeSelect.innerHTML = '<option value="">Menunggu tanggal...</option>';
        timeSelect.disabled = true;
        btnSubmit.disabled = true;
        btnGoToSummary.disabled = true;
        currentCourtSlots = [];
        
        return fetch(`booking_customer.php?action=get_slots&court_id=${courtId}`)
            .then(response => response.json())
            .then(slots => {
                currentCourtSlots = slots;
                dateSelect.innerHTML = '';
                
                if (slots.length === 0) {
                    dateSelect.innerHTML = '<option value="">Tidak ada jadwal kosong</option>';
                    showSlotStatus(false, 'Tidak ada jadwal', 'Semua slot terbooking');
                    return false;
                }

                const uniqueDates = [];
                slots.forEach(slot => {
                    if (!uniqueDates.includes(slot.Tanggal_Formatted)) uniqueDates.push(slot.Tanggal_Formatted);
                });

                const defaultOpt = document.createElement('option');
                defaultOpt.value = "";
                defaultOpt.innerText = "-- Pilih Tanggal Bermain --";
                dateSelect.appendChild(defaultOpt);

                uniqueDates.forEach(dateStr => {
                    const opt = document.createElement('option');
                    opt.value = dateStr;
                    opt.innerText = dateStr;
                    dateSelect.appendChild(opt);
                });

                if (uniqueDates.length > 0) {
                    dateSelect.selectedIndex = 1;
                    dateSelect.dispatchEvent(new Event('change'));
                }
                return true;
            })
            .catch(err => {
                console.error("Gagal memuat jadwal:", err);
                dateSelect.innerHTML = '<option value="">Gagal memuat jadwal</option>';
                return false;
            });
    }

    function showSlotStatus(isAvailable, title, desc) {
        if (isAvailable) {
            availabilityBox.classList.remove('empty');
            availIcon.innerHTML = '<i class="fa-solid fa-circle-check"></i>';
            availTitle.innerText = title;
            lblDuration.innerText = desc;
        } else {
            availabilityBox.classList.add('empty');
            availIcon.innerHTML = '<i class="fa-solid fa-circle-xmark"></i>';
            availTitle.innerText = title;
            lblDuration.innerText = desc;
        }
    }

    function calculatePrices() {
        if (!selectedSlotId) {
            lblNormalPrice.innerText = 'Rp 0';
            lblTotalPrice.innerText = 'Rp 0';
            btnSubmit.disabled = true;
            btnGoToSummary.disabled = true;
            return;
        }

        const basePrice = selectedCourtPrice * selectedSlotDuration;
        let discount = 0;

        if (isMember) {
            discount = memberDiscount;
        } else if (promoSelect) {
            const selectedPromoOpt = promoSelect.options[promoSelect.selectedIndex];
            if (selectedPromoOpt) discount = parseFloat(selectedPromoOpt.getAttribute('data-discount') || 0);
        }

        const totalPayable = Math.max(0, basePrice - discount);

        lblNormalPriceLabel.innerText = `Harga Sewa (${selectedSlotDuration} jam)`;
        lblNormalPrice.innerText = formatRupiah(basePrice);
        if (!isMember && lblPromoBreakdown) lblPromoBreakdown.innerText = `-Rp ${discount.toLocaleString('id-ID')}`;
        lblTotalPrice.innerText = formatRupiah(totalPayable);

        sumCourtName.innerText = selectedCourtName;
        sumImg.src = selectedCourtImg;
        sumPlayDate.innerText = selectedSlotDateFormatted;
        sumTimeLabel.innerText = `${selectedSlotTimeFormatted} (${selectedSlotDuration} jam)`;

        btnSubmit.disabled = false;
        btnGoToSummary.disabled = false;
    }

    if (courts && courts.length > 0) {
        courts.forEach(court => {
            court.addEventListener('click', function(e) {
                e.preventDefault();
                const isAlreadySelected = this.classList.contains('selected');

                if (!isAlreadySelected) {
                    courts.forEach(c => c.classList.remove('selected'));
                    this.classList.add('selected');

                    selectedCourtId = this.getAttribute('data-id') || null;
                    selectedCourtPrice = parseFloat(this.getAttribute('data-price') || 0);
                    selectedCourtName = this.getAttribute('data-name') || '';
                    selectedCourtImg = this.getAttribute('data-img') || '';

                    if (selectedCourtId) loadSlots(selectedCourtId);
                } else {
                    if (selectedCourtId && scheduleModal) scheduleModal.style.display = 'flex';
                }
            });
        });
    }

    dateSelect.addEventListener('change', function() {
        const selectedDate = this.value;
        timeSelect.innerHTML = '';
        
        if (!selectedDate) {
            timeSelect.innerHTML = '<option value="">Pilih tanggal terlebih dahulu...</option>';
            timeSelect.disabled = true;
            selectedSlotId = null;
            showSlotStatus(false, 'Tanggal belum dipilih', 'Silakan pilih tanggal terlebih dahulu');
            calculatePrices();
            return;
        }

        const filteredSlots = currentCourtSlots.filter(slot => slot.Tanggal_Formatted === selectedDate);
        
        const defaultOpt = document.createElement('option');
        defaultOpt.value = "";
        defaultOpt.innerText = "-- Pilih Jam Bermain --";
        timeSelect.appendChild(defaultOpt);

        filteredSlots.forEach(slot => {
            const opt = document.createElement('option');
            opt.value = slot.ID_Jadwal;
            opt.setAttribute('data-tanggal', slot.Tanggal_Formatted);
            opt.setAttribute('data-mulai', slot.Jam_Mulai);
            opt.setAttribute('data-selesai', slot.Jam_Selesai);
            opt.innerText = `${slot.Jam_Mulai} - ${slot.Jam_Selesai}`;
            timeSelect.appendChild(opt);
        });

        timeSelect.disabled = false;

        if (filteredSlots.length > 0) {
            timeSelect.selectedIndex = 1;
            timeSelect.dispatchEvent(new Event('change'));
        }
    });

    timeSelect.addEventListener('change', function() {
        const opt = this.options[this.selectedIndex];
        if (!opt || !opt.value) {
            selectedSlotId = null;
            showSlotStatus(false, 'Jam belum dipilih', 'Silakan pilih jam bermain Anda');
            calculatePrices();
            return;
        }

        selectedSlotId = opt.value;
        const startHour = parseInt(opt.getAttribute('data-mulai').split(':')[0]);
        const endHour = parseInt(opt.getAttribute('data-selesai').split(':')[0]);
        selectedSlotDuration = endHour - startHour;
        if (selectedSlotDuration <= 0) selectedSlotDuration = 1;

        selectedSlotDateFormatted = opt.getAttribute('data-tanggal');
        selectedSlotTimeFormatted = `${opt.getAttribute('data-mulai')} - ${opt.getAttribute('data-selesai')}`;

        showSlotStatus(true, 'Slot Terkonfirmasi', `Durasi: ${selectedSlotDuration} jam`);
        calculatePrices();
    });

    if (promoSelect) promoSelect.addEventListener('change', calculatePrices);

    payments.forEach(payment => {
        payment.addEventListener('click', function() {
            payments.forEach(p => p.classList.remove('selected'));
            this.classList.add('selected');
            selectedPaymentMethod = this.getAttribute('data-method');
        });
    });

    // ====== PERUBAHAN: Flow sama dengan langganan ======
    // Klik "Selesaikan Booking" → langsung muncul modal instruksi pembayaran
    btnSubmit.addEventListener('click', function() {
        if (!selectedSlotId) return;

        // Langsung tampilkan modal instruksi pembayaran (tanpa nunggu server)
        bookingModal.style.display = 'none';
        
        const basePrice = selectedCourtPrice * selectedSlotDuration;
        let discount = 0;
        let idPromo = null;

        if (isMember) {
            discount = memberDiscount;
        } else if (promoSelect) {
            const selectedPromoOpt = promoSelect.options[promoSelect.selectedIndex];
            if (selectedPromoOpt && selectedPromoOpt.value !== '0') {
                discount = parseFloat(selectedPromoOpt.getAttribute('data-discount') || 0);
                idPromo = selectedPromoOpt.value;
            }
        }

        const finalAmount = Math.max(0, basePrice - discount);

        document.getElementById('paymentTotalAmount').innerText = formatRupiah(finalAmount);
        showPaymentMethodInstructions(selectedPaymentMethod);
        document.getElementById('paymentInstructionModal').style.display = 'flex';
        startPaymentCountdown(15 * 60);
    });

    document.addEventListener("DOMContentLoaded", function() {
        const activeCourt = document.querySelector('.court-card.selected');
        if (activeCourt) {
            selectedCourtId = activeCourt.getAttribute('data-id');
            selectedCourtPrice = parseFloat(activeCourt.getAttribute('data-price'));
            selectedCourtName = activeCourt.getAttribute('data-name');
            selectedCourtImg = activeCourt.getAttribute('data-img');
            loadSlots(selectedCourtId);
        }
    });

    function confirmHapusAkun(e) {
        e.preventDefault();
        Swal.fire({
            title: 'Hapus Akun Permanen?',
            html: '<strong style="color:#FF3B30;">PERINGATAN:</strong> Tindakan ini tidak dapat dibatalkan!<br><br>Akun Anda akan dihapus dari sistem dan Anda harus mendaftar ulang untuk menggunakan layanan kami.<br><br><span style="color:#8E8E93; font-size:12px;">Data akan dihapus secara permanen.</span>',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#FF3B30',
            cancelButtonColor: '#8E8E93',
            confirmButtonText: 'Ya, Hapus Akun Saya',
            cancelButtonText: 'Batal',
            reverseButtons: true,
            allowOutsideClick: false,
            allowEscapeKey: false
        }).then((result) => {
            if (result.isConfirmed) {
                let timerInterval;
                Swal.fire({
                    title: 'Menghapus Akun...',
                    html: 'Mohon tunggu, proses penghapusan data sedang berlangsung.<br><b></b>',
                    timer: 2000,
                    timerProgressBar: true,
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    didOpen: () => {
                        Swal.showLoading();
                        const timer = Swal.getHtmlContainer().querySelector('b');
                        timerInterval = setInterval(() => {
                            timer.textContent = Math.ceil(Swal.getTimerLeft() / 1000) + ' detik';
                        }, 100);
                    },
                    willClose: () => clearInterval(timerInterval)
                }).then(() => {
                    window.location.href = '?hapus_akun=1';
                });
            }
        });
    }

    const urlParams = new URLSearchParams(window.location.search);
    const status = urlParams.get('status');
    const msg = urlParams.get('msg');

    if (status && msg) {
        const isSuccess = status === 'success';
        Swal.fire({
            icon: isSuccess ? 'success' : 'error',
            title: isSuccess ? 'Berhasil' : 'Kendala Sistem',
            text: msg,
            timer: 5000,
            showConfirmButton: false,
            toast: true,
            position: 'top-end',
            timerProgressBar: true,
            showCloseButton: true,
            background: '#ffffff',
            color: '#1c1c1e',
            iconColor: isSuccess ? '#34C759' : '#FF3B30',
            customClass: { popup: 'swal-toast' }
        });
        window.history.replaceState({}, document.title, window.location.pathname);
    }

    let countdownInterval;
    function startPaymentCountdown(duration) {
        let timer = duration, minutes, seconds;
        const display = document.getElementById('paymentCountdown');
        clearInterval(countdownInterval);
        
        countdownInterval = setInterval(function () {
            minutes = parseInt(timer / 60, 10);
            seconds = parseInt(timer % 60, 10);
            minutes = minutes < 10 ? "0" + minutes : minutes;
            seconds = seconds < 10 ? "0" + seconds : seconds;
            display.textContent = minutes + ":" + seconds;
            if (--timer < 0) {
                clearInterval(countdownInterval);
                display.textContent = "Waktu Pembayaran Habis";
                document.getElementById('btnDonePayment').disabled = true;
            }
        }, 1000);
    }

    document.getElementById('btnCopyVA').addEventListener('click', function() {
        const vaInput = document.getElementById('vaNumber');
        vaInput.select();
        vaInput.setSelectionRange(0, 99999);
        navigator.clipboard.writeText(vaInput.value).then(() => {
            Swal.fire({
                icon: 'success',
                title: 'Berhasil Disalin!',
                text: 'Nomor Virtual Account disalin ke papan klip Anda.',
                timer: 1500,
                showConfirmButton: false,
                toast: true,
                position: 'top-end',
                customClass: { popup: 'swal-toast' }
            });
        });
    });

    // ====== PERUBAHAN: Klik "Saya Sudah Bayar" → baru kirim ke server ======
    document.getElementById('btnDonePayment').addEventListener('click', function() {
        clearInterval(countdownInterval);
        document.getElementById('paymentInstructionModal').style.display = 'none';

        // Kirim data ke server via fetch (sama seperti langganan)
        const basePrice = selectedCourtPrice * selectedSlotDuration;
        let discount = 0;
        let idPromo = null;

        if (isMember) {
            discount = memberDiscount;
        } else if (promoSelect) {
            const selectedPromoOpt = promoSelect.options[promoSelect.selectedIndex];
            if (selectedPromoOpt && selectedPromoOpt.value !== '0') {
                discount = parseFloat(selectedPromoOpt.getAttribute('data-discount') || 0);
                idPromo = selectedPromoOpt.value;
            }
        }

        const finalAmount = Math.max(0, basePrice - discount);

        const checkoutData = {
            id_jadwal: selectedSlotId,
            id_promo: idPromo,
            metode_pembayaran: selectedPaymentMethod,
            total_bayar: finalAmount
        };

        fetch('booking_customer.php?action=checkout', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(checkoutData)
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Booking Berhasil!',
                    text: 'Pemesanan lapangan berhasil dibuat.',
                    confirmButtonColor: 'var(--orange)',
                    confirmButtonText: 'OK'
                }).then(() => {
                    location.reload();
                });
            } else {
                Swal.fire({
                    icon: 'warning',
                    title: 'Jadwal Tidak Tersedia',
                    text: result.message,
                    confirmButtonColor: 'var(--orange)',
                    confirmButtonText: 'Pilih Jadwal Lain'
                });
            }
        })
        .catch(err => {
            console.error("Kesalahan checkout:", err);
            Swal.fire({
                icon: 'error',
                title: 'Koneksi Terputus',
                text: 'Gagal terhubung ke sistem server. Pastikan koneksi internet Anda stabil lalu coba kembali.',
                confirmButtonColor: 'var(--orange)',
                confirmButtonText: 'Coba Lagi'
            });
        });
    });

    const btnSwitchVA = document.getElementById('btnSwitchVA');
    const btnSwitchQRIS = document.getElementById('btnSwitchQRIS');

    function showPaymentMethodInstructions(method) {
        selectedPaymentMethod = method;
        if (method === 'Transfer Bank') {
            btnSwitchVA.style.backgroundColor = '#fff';
            btnSwitchVA.style.color = 'var(--orange)';
            btnSwitchVA.style.boxShadow = '0 2px 6px rgba(0,0,0,0.05)';
            btnSwitchQRIS.style.backgroundColor = 'transparent';
            btnSwitchQRIS.style.color = 'var(--text-secondary)';
            btnSwitchQRIS.style.boxShadow = 'none';
            document.getElementById('instruksiTransfer').style.display = 'block';
            document.getElementById('instruksiQRIS').style.display = 'none';
        } else {
            btnSwitchQRIS.style.backgroundColor = '#fff';
            btnSwitchQRIS.style.color = 'var(--orange)';
            btnSwitchQRIS.style.boxShadow = '0 2px 6px rgba(0,0,0,0.05)';
            btnSwitchVA.style.backgroundColor = 'transparent';
            btnSwitchVA.style.color = 'var(--text-secondary)';
            btnSwitchVA.style.boxShadow = 'none';
            document.getElementById('instruksiTransfer').style.display = 'none';
            document.getElementById('instruksiQRIS').style.display = 'flex';
            const currentTotal = parseFloat(document.getElementById('paymentTotalAmount').innerText.replace(/[^0-9]/g, ''));
            const qrPayload = `HOOPBALL-PAYMENT-${selectedSlotId}-${currentTotal}`;
            document.getElementById('qrisImage').src = `https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=${encodeURIComponent(qrPayload)}`;
        }
    }

    btnSwitchVA.addEventListener('click', () => showPaymentMethodInstructions('Transfer Bank'));
    btnSwitchQRIS.addEventListener('click', () => showPaymentMethodInstructions('QRIS'));
</script>

</body>
</html>