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
// AJAX REQUEST HANDLERS (Untuk interaktivitas pemilihan slot dan proses checkout)
// ============================================================================
if (isset($_GET['action'])) {
    header('Content-Type: application/json');

    // Ambil Slot Jadwal Berdasarkan Lapangan
    if ($_GET['action'] == 'get_slots' && isset($_GET['court_id'])) {
        $court_id = intval($_GET['court_id']);
        $queryJadwal = "SELECT ID_Jadwal, Tanggal, Jam_Mulai, Jam_Selesai 
                        FROM Jadwal 
                        WHERE ID_Lapangan = ? AND Status = 1 AND Is_Deleted = 0
                        ORDER BY Tanggal ASC, Jam_Mulai ASC";
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

    // Proses Transaksi Booking Baru
    if ($_GET['action'] == 'checkout' && $_SERVER['REQUEST_METHOD'] == 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }

        $id_jadwal = intval($input['id_jadwal'] ?? 0);
        $id_promo = !empty($input['id_promo']) ? intval($input['id_promo']) : null;
        $metode_pembayaran = htmlspecialchars($input['metode_pembayaran'] ?? '');
        $total_bayar = floatval($input['total_bayar'] ?? 0);

        if ($id_jadwal <= 0 || empty($metode_pembayaran) || $total_bayar <= 0) {
            echo json_encode(['success' => false, 'message' => 'Parameter input tidak valid.']);
            exit();
        }

        // Mulai transaksi SQL Server
        if (sqlsrv_begin_transaction($conn) === false) {
            echo json_encode(['success' => false, 'message' => 'Gagal menginisiasi sesi transaksi database.']);
            exit();
        }

        try {
            // 1. Verifikasi ketersediaan jadwal
            $queryCheck = "SELECT Status, ID_Lapangan FROM Jadwal WHERE ID_Jadwal = ?";
            $stmtCheck = sqlsrv_query($conn, $queryCheck, array($id_jadwal));
            $jadwal = null;
            if ($stmtCheck) {
                $jadwal = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);
            }

            if (!$jadwal || $jadwal['Status'] != 1) {
                throw new Exception("Maaf, slot jadwal ini sudah terbooking atau tidak tersedia.");
            }

            // 2. Pilih karyawan penanggung jawab transaksi secara acak/berurutan
            $queryKaryawan = "SELECT TOP 1 ID_Karyawan FROM Karyawan WHERE Status = 1 AND Is_Deleted = 0 ORDER BY ID_Karyawan ASC";
            $stmtKary = sqlsrv_query($conn, $queryKaryawan);
            $id_karyawan = 1;
            if ($stmtKary) {
                $kary = sqlsrv_fetch_array($stmtKary, SQLSRV_FETCH_ASSOC);
                if ($kary) {
                    $id_karyawan = $kary['ID_Karyawan'];
                }
            }

            // 3. Simpan data ke tabel Booking
            $created_by = $_SESSION['nama'] ?? 'CUSTOMER';
            $queryInsert = "INSERT INTO Booking 
                            (ID_Customer, ID_Karyawan, ID_Jadwal, ID_Promo, Tanggal_Booking, Metode_Pembayaran, Total_Bayar, Status, Created_By, Created_Date) 
                            VALUES (?, ?, ?, ?, CAST(GETDATE() AS DATE), ?, ?, 0, ?, GETDATE())";
            
            $stmtInsert = sqlsrv_query($conn, $queryInsert, array(
                $id_customer,
                $id_karyawan,
                $id_jadwal,
                $id_promo,
                $metode_pembayaran,
                $total_bayar,
                $created_by
            ));

            if ($stmtInsert === false) {
                throw new Exception("Kesalahan sistem saat membuat entri transaksi.");
            }

            // 4. Ubah status ketersediaan Jadwal menjadi tidak tersedia
            $queryUpdateJadwal = "UPDATE Jadwal SET Status = 0, Modified_By = ?, Modified_Date = GETDATE() WHERE ID_Jadwal = ?";
            $stmtUpdate = sqlsrv_query($conn, $queryUpdateJadwal, array($created_by, $id_jadwal));

            if ($stmtUpdate === false) {
                throw new Exception("Gagal merubah status jadwal sewa.");
            }

            sqlsrv_commit($conn);
            echo json_encode(['success' => true, 'message' => 'Pemesanan berhasil dibuat!']);
        } catch (Exception $e) {
            sqlsrv_rollback($conn);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }
}

// ============================================================================
// LOAD DATA MASTER (Lapangan, Fasilitas, & Promo)
// ============================================================================

// 1. Data Lapangan
$lapanganList = [];
$queryLapangan = sqlsrv_query($conn, "SELECT ID_Lapangan, Nama_Lapangan, Harga_Sewa, Photo_Lapangan FROM Lapangan WHERE Status = 1 AND Is_Deleted = 0");
if ($queryLapangan) {
    while ($row = sqlsrv_fetch_array($queryLapangan, SQLSRV_FETCH_ASSOC)) {
        $lapanganList[] = $row;
    }
}

// 2. Mapping Fasilitas per Lapangan
$lapanganFasilitas = [];
$queryFasilitas = sqlsrv_query($conn, "SELECT ID_Lapangan, Nama_Fasilitas FROM Fasilitas_Lapangan WHERE Status = 1 AND Is_Deleted = 0");
if ($queryFasilitas) {
    while ($row = sqlsrv_fetch_array($queryFasilitas, SQLSRV_FETCH_ASSOC)) {
        $lapanganFasilitas[$row['ID_Lapangan']][] = $row['Nama_Fasilitas'];
    }
}

// 3. Data Promo Aktif (Hanya untuk non-member)
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
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Barlow+Condensed:wght@700;800;900&family=Barlow:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root {
            /* Palette Design System */
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

            /* Interface Builder */
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

        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg);
            color: var(--text-primary);
            -webkit-font-smoothing: antialiased;
            overflow-x: hidden;
        }

        /* ---- NAVBAR (PUTIH) ---- */
        nav {
            background: var(--white);
            padding: 0 80px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 76px;
            position: sticky;
            top: 0;
            z-index: 1000;
            border-bottom: 1px solid #E5E5EA;
        }
        .nav-logo {
            display: flex;
            align-items: center;
            text-decoration: none;
            gap: 10px;
        }
        .nav-logo img {
            height: 70px;
            width: auto;
        }
        .nav-logo span {
            color: #1C1C1E;
            font-size: 20px;
            font-weight: 800;
            letter-spacing: -0.5px;
        }
        .nav-links {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .nav-links a {
            color: #636366;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            padding: 8px 16px;
            border-radius: 20px;
            transition: all 0.2s ease;
        }
        .nav-links a:hover { color: #1C1C1E; }
        .nav-links a.active { color: var(--primary); font-weight: 600; background: var(--primary-light); }

        /* ---- USER DROPDOWN ---- */
        .nav-user-container {
            position: relative;
            height: 76px;
            display: flex;
            align-items: center;
        }
        .nav-user {
            background: #F2F2F7;
            border: 1px solid #E5E5EA;
            padding: 8px 16px;
            border-radius: 50px;
            color: #1C1C1E;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: 0.2s;
        }
        .nav-user:hover { background: #E5E5EA; border-color: var(--primary); }
        .nav-user img.user-avatar {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            object-fit: cover;
        }
        .nav-user i.user-icon {
            font-size: 16px;
            color: var(--primary);
        }
        .nav-user i.arrow { 
            font-size: 11px; 
            color: #8E8E93; 
            transition: 0.3s; 
        }
        .nav-user-container:hover i.arrow { 
            transform: rotate(180deg); 
            color: var(--primary); 
        }
        .dropdown-menu {
            position: absolute;
            top: 85%;
            right: 0;
            background: #16161a;
            min-width: 220px;
            border-radius: 12px;
            border: 1px solid #2d2d33;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            padding: 8px 0;
            display: none; 
            z-index: 1001;
            animation: fadeIn 0.2s ease-out;
        }
        .nav-user-container:hover .dropdown-menu {
            display: block;
        }
        .dropdown-menu .user-info-header {
            padding: 12px 20px;
            border-bottom: 1px solid #2d2d33;
            margin-bottom: 6px;
        }
        .dropdown-menu .user-info-header span {
            display: block;
        }
        .dropdown-menu .user-info-header .u-name {
            color: var(--white);
            font-size: 14px;
            font-weight: 700;
        }
        .dropdown-menu .user-info-header .u-role {
            color: var(--text-gray);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 2px;
        }
        .dropdown-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 20px;
            color: #c5c5ca;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: 0.2s;
        }
        .dropdown-menu a i { 
            font-size: 14px; 
            width: 16px; 
            text-align: center; 
        }
        .dropdown-menu a:hover {
            background: #222227;
            color: var(--primary);
        }
        .dropdown-divider {
            height: 1px;
            background: #2d2d33;
            margin: 6px 0;
        }
        .dropdown-menu a.logout:hover { 
            color: #ff3b30; 
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ---- CONTAINER (Kini Melebar Dinamis Sesuai Resolusi Layar) ---- */
        .container {
            width: 100%;
            max-width: 95%; /* Mengurangi ruang kosong margin kiri & kanan */
            margin: 40px auto;
            padding: 0 20px;
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        /* Wizard & Form */
        .left-col {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        /* ---- WIZARD (Panduan Statis Tanpa Animasi / Warna Melompat) ---- */
        .steps-card {
            background: #fff;
            border-radius: 16px;
            border: 1px solid var(--border);
            padding: 24px;
            position: relative;
        }

        .steps-container {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            position: relative;
        }

        .steps-line {
            position: absolute;
            top: 16px;
            left: 8%;
            right: 8%;
            height: 2px;
            background: #E2E8F0;
            z-index: 1;
        }

        .step-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            position: relative;
            z-index: 3;
            flex: 1;
        }

        .step-num {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 14px;
            margin-bottom: 8px;
            background: #F1F5F9;
            color: var(--text-secondary);
            border: 1px solid var(--border);
        }

        .step-title {
            font-size: 13px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 2px;
        }

        .step-desc {
            font-size: 11px;
            color: var(--muted);
            font-weight: 500;
        }

        .form-card {
            background: #fff;
            border-radius: 16px;
            border: 1px solid var(--border);
            padding: 30px;
        }

        .section-header {
            margin-bottom: 20px;
        }

        .section-title {
            font-size: 16px;
            font-weight: 800;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .section-subtitle {
            font-size: 12px;
            color: var(--muted);
            margin-top: 4px;
            font-weight: 500;
        }

        /* Court Selection Grid */
        .court-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); /* Ukuran kartu diperlebar */
            gap: 24px;
            margin-bottom: 30px;
        }

        .court-card {
            border: 1px solid var(--border);
            border-radius: 14px;
            overflow: hidden;
            background: #fff;
            cursor: pointer;
            position: relative;
            transition: all 0.2s ease;
        }

        .court-card:hover {
            border-color: var(--orange);
            box-shadow: 0 4px 12px var(--orange-glow);
        }

        .court-card.selected {
            border-color: var(--orange);
            box-shadow: 0 0 0 2px var(--orange);
        }

        .court-img-wrapper {
            position: relative;
            height: 200px; /* Rasio tinggi gambar ditingkatkan */
            background: #cbd5e1;
            overflow: hidden;
        }

        .court-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

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
        }

        .badge-selected-check {
            position: absolute;
            top: 12px;
            right: 12px;
            background: var(--orange);
            width: 22px;
            height: 22px;
            border-radius: 50%;
            display: none;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 10px;
            z-index: 5;
            box-shadow: 0 2px 6px rgba(0,0,0,0.15);
        }

        .court-card.selected .badge-selected-check {
            display: flex;
        }

        .court-info {
            padding: 16px;
        }

        .court-name {
            font-size: 15px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .court-price {
            font-size: 14px;
            font-weight: 700;
            color: var(--orange);
            margin: 6px 0 12px;
        }

        .court-perk-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .court-perk-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 11.5px;
            color: var(--text-secondary);
        }

        .court-perk-item i {
            color: var(--muted);
            width: 14px;
            text-align: center;
        }

        .divider {
            height: 1px;
            background: var(--border-lt);
            margin: 25px 0;
        }

        /* Schedule slot select */
        .schedule-controls {
            display: flex;
            flex-direction: column;
            gap: 16px;
            align-items: stretch;
            margin-bottom: 16px;
        }

        .input-group {
            flex: 1;
            min-width: 250px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .input-label {
            font-size: 11.5px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-wrapper i {
            position: absolute;
            left: 14px;
            color: var(--muted);
            font-size: 14px;
            pointer-events: none;
        }

        .form-control {
            width: 100%;
            padding: 11px 14px 11px 40px;
            border: 1px solid var(--border);
            border-radius: 10px;
            font-family: inherit;
            font-size: 13px;
            color: var(--text-primary);
            background: #white;
            outline: none;
            appearance: none;
        }

        .form-control:focus {
            border-color: var(--orange);
            box-shadow: 0 0 0 3px var(--orange-glow);
        }

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
        }

        .status-availability-box.empty {
            background: var(--red-lt);
            border-color: rgba(255, 59, 48, 0.15);
        }

        .status-avail-icon {
            color: var(--green);
            font-size: 18px;
        }

        .status-availability-box.empty .status-avail-icon {
            color: var(--red);
        }

        .status-avail-title {
            font-size: 12px;
            font-weight: 700;
            color: #065F46;
        }

        .status-availability-box.empty .status-avail-title {
            color: #991B1B;
        }

        .status-avail-desc {
            font-size: 10px;
            color: #047857;
            margin-top: 2px;
        }

        .status-availability-box.empty .status-avail-desc {
            color: #B91C1C;
        }

        .alert-banner {
            background: #EFF6FF;
            border: 1px solid rgba(0, 122, 255, 0.15);
            border-radius: 10px;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 16px;
        }

        .alert-banner i {
            color: var(--blue);
            font-size: 16px;
        }

        .alert-banner-text {
            font-size: 11.5px;
            color: #1E40AF;
            line-height: 1.5;
        }

        /* ---- POP-UP MODAL STYLE (PENGGANTI SIDEBAR) ---- */
        .booking-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            display: none; /* Dikontrol via JS */
            align-items: center;
            justify-content: center;
            z-index: 2000;
            padding: 20px;
            animation: fadeInModal 0.25s ease-out forwards;
        }

        @keyframes fadeInModal {
            from { opacity: 0; }
            to { opacity: 1; }
        }

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

        @keyframes slideInModal {
            from { transform: translateY(30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

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
            transition: var(--transition-smooth);
            z-index: 10;
        }

        .booking-modal-close:hover {
            background: var(--red-lt);
            color: var(--red);
        }

        /* Tombol Utama untuk membuka Modal */
        .btn-trigger-modal {
            display: flex;
            width: 100%;
            max-width: 320px;    /* Membatasi lebar tombol agar proporsional */
            margin: 30px 0 0 auto; /* Menggunakan 'auto' di sisi kiri agar tombol terdorong ke kanan */
            background: var(--orange);
            color: #fff;
            border: none;
            border-radius: 12px;
            padding: 16px;
            font-family: inherit;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: background 0.2s ease;
        }

        .btn-trigger-modal:hover:not(:disabled) {
            background: var(--orange-hover);
        }

        .btn-trigger-modal:disabled {
            background: var(--muted);
            cursor: not-allowed;
        }

        .summary-title {
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 16px;
            font-weight: 800;
            letter-spacing: 0.5px;
            color: var(--muted);
            margin-bottom: 20px;
            text-transform: uppercase;
        }

        .summary-item-info {
            display: flex;
            gap: 14px;
            margin-bottom: 20px;
        }

        .summary-thumb {
            width: 70px;
            height: 70px;
            border-radius: 10px;
            overflow: hidden;
            background: #e2e8f0;
            flex-shrink: 0;
        }

        .summary-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .summary-details {
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .summary-court-name {
            font-size: 15px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .summary-venue {
            font-size: 11px;
            color: var(--muted);
            margin-bottom: 6px;
            font-weight: 500;
        }

        .summary-meta {
            font-size: 11.5px;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: 2px;
            font-weight: 500;
        }

        .summary-meta i {
            font-size: 11px;
            color: var(--muted);
            width: 14px;
        }

        /* Member Discount Area */
        .member-block {
            border-top: 1px solid var(--border-lt);
            padding: 16px 0;
        }

        .member-status-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
        }

        .member-status-label {
            font-size: 12.5px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .badge-member-active {
            background: var(--green-lt);
            color: var(--green);
            font-size: 10px;
            font-weight: 800;
            padding: 4px 10px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .badge-member-inactive {
            background: #FFF3CD;
            color: #D97706;
            font-size: 10px;
            font-weight: 800;
            padding: 4px 10px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .member-text-congratulations {
            font-size: 11px;
            color: var(--muted);
            margin-bottom: 12px;
        }

        .discount-row {
            display: flex;
            justify-content: space-between;
            font-size: 12.5px;
        }

        .discount-label {
            color: var(--text-secondary);
            font-weight: 500;
        }

        .discount-val {
            color: var(--green);
            font-weight: 700;
        }

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
        }

        .promo-warning-box i {
            color: #D97706;
            font-size: 14px;
        }

        .promo-warning-text {
            font-size: 11px;
            color: #B45309;
            line-height: 1.4;
            font-weight: 500;
        }

        .promo-input-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-bottom: 20px;
        }

        .promo-input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .promo-input-wrapper i.prefix-icon {
            position: absolute;
            left: 14px;
            color: var(--muted);
            font-size: 13px;
        }

        .promo-input-wrapper i.lock-icon {
            position: absolute;
            right: 14px;
            color: var(--muted);
            font-size: 13px;
        }

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
        }

        .promo-input:disabled {
            background: #F8FAFC;
            color: var(--muted);
            cursor: not-allowed;
        }

        /* Price Breakdown */
        .pricing-breakdown {
            border-top: 1px solid var(--border-lt);
            padding: 16px 0;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .price-row {
            display: flex;
            justify-content: space-between;
            font-size: 12.5px;
            color: var(--text-secondary);
            font-weight: 500;
        }

        .price-row.total-row {
            margin-top: 6px;
            font-size: 14px;
            color: var(--text-primary);
            font-weight: 800;
            align-items: center;
        }

        .price-row.total-row .total-amount {
            font-size: 20px;
            color: var(--orange);
            font-weight: 900;
        }

        /* Payment Methods */
        .payment-section {
            border-top: 1px solid var(--border-lt);
            padding: 20px 0 10px;
        }

        .payment-header {
            font-size: 12.5px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .payment-header i {
            color: var(--muted);
        }

        .payment-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .payment-card {
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.2s ease;
            user-select: none;
        }

        .payment-card:hover {
            border-color: var(--orange);
        }

        .payment-card.selected {
            border-color: var(--orange);
            background: var(--orange-lt);
        }

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

        .payment-card.selected .custom-radio {
            border-color: var(--orange);
        }

        .custom-radio::after {
            content: '';
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--orange);
            display: none;
        }

        .payment-card.selected .custom-radio::after {
            display: block;
        }

        .payment-card-content {
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .payment-name {
            font-size: 11px;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.3;
        }

        .payment-sub {
            font-size: 9px;
            color: var(--muted);
            margin-top: 1px;
            font-weight: 500;
        }

        .qris-logo {
            font-family: 'Barlow Condensed', sans-serif;
            font-weight: 900;
            font-size: 14px;
            color: #000;
            letter-spacing: -0.5px;
        }

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
            transition: background 0.2s ease;
        }

        .btn-booking:hover:not(:disabled) {
            background: var(--orange-hover);
        }

        .btn-booking:disabled {
            background: var(--muted);
            cursor: not-allowed;
        }

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

        .booking-disclaimer i {
            color: var(--green);
        }

        /* Success Toast */
        .success-toast {
            position: fixed;
            bottom: 24px;
            left: 24px;
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.15);
            border: 1px solid var(--border);
            padding: 16px 20px;
            display: none;
            align-items: center;
            gap: 16px;
            z-index: 1000;
            max-width: 600px;
            animation: slideInUp 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes slideInUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .toast-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--green-lt);
            color: var(--green);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            flex-shrink: 0;
        }

        .toast-body {
            flex: 1;
        }

        .toast-title {
            font-size: 13.5px;
            font-weight: 800;
            color: var(--text-primary);
        }

        .toast-subtitle {
            font-size: 11px;
            color: var(--muted);
            margin-top: 2px;
            font-weight: 500;
        }

        .toast-meta-pills {
            display: flex;
            gap: 6px;
            margin-top: 8px;
        }

        .toast-pill {
            background: var(--border-lt);
            border: 1px solid var(--border);
            color: var(--text-secondary);
            font-size: 10px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 6px;
        }

        .btn-toast-action {
            background: var(--orange);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 8px 14px;
            font-family: inherit;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s ease;
            white-space: nowrap;
        }

        .btn-toast-action:hover {
            background: var(--orange-hover);
        }

        .toast-close {
            background: none;
            border: none;
            color: var(--muted);
            cursor: pointer;
            font-size: 14px;
            padding: 4px;
            align-self: flex-start;
        }

        .toast-close:hover {
            color: var(--red);
        }

        .swal-toast { border-radius: 12px !important; font-family: 'Plus Jakarta Sans', sans-serif !important; }

        /* ---- FOOTER ---- */
        footer {
            background: var(--dark-bg);
            color: #8E8E93;
            padding: 80px 80px 40px;
            border-top: 1px solid #1C1C1E;
        }
        .footer-grid {
            display: grid;
            grid-template-columns: 1.5fr 1fr 1fr 1.2fr;
            gap: 40px;
            margin-bottom: 60px;
        }
        .footer-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 16px;
        }
        .footer-logo img {
            height: 70px;
        }
        .footer-logo span {
            color: var(--white);
            font-size: 20px;
            font-weight: 800;
        }
        .footer-desc {
            font-size: 13px;
            line-height: 1.6;
            margin-bottom: 24px;
        }
        .social-links {
            display: flex;
            gap: 12px;
        }
        .social-btn {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #1C1C1E;
            color: var(--white);
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: 0.2s;
        }
        .social-btn:hover {
            background: var(--primary);
        }
        .footer-col h4 {
            color: var(--white);
            font-size: 15px;
            font-weight: 700;
            margin-bottom: 20px;
        }
        .footer-col ul {
            list-style: none;
        }
        .footer-col ul li {
            margin-bottom: 12px;
        }
        .footer-col ul li a {
            color: #8E8E93;
            text-decoration: none;
            font-size: 13px;
            transition: 0.2s;
        }
        .footer-col ul li a:hover {
            color: var(--white);
        }
        .contact-item {
            display: flex;
            gap: 12px;
            font-size: 13px;
            line-height: 1.5;
            margin-bottom: 16px;
        }
        .contact-item i {
            color: var(--primary);
            font-size: 14px;
            margin-top: 3px;
        }
        .footer-bottom {
            border-top: 1px solid #1C1C1E;
            padding-top: 30px;
            text-align: center;
            font-size: 13px;
        }

        /* Media queries responsive navbar */
        @media (max-width: 1200px) {
            nav { padding: 0 40px; }
        }
        @media (max-width: 768px) {
            nav {
                flex-direction: column;
                height: auto;
                padding: 15px 20px;
                gap: 15px;
            }
            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
                gap: 4px;
            }
            .nav-user-container {
                height: auto;
            }
            .dropdown-menu {
                top: 50px;
                right: 50%;
                transform: translateX(50%);
            }
        }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav>
    <a href="view_customer.php" class="nav-logo">
        <img src="../asset/image/logo2.png" alt="HoopBall">
    </a>
    <div class="nav-links">
        <a href="view_customer.php">Beranda</a>
        <a href="booking_customer.php" class="active">Booking</a>
        <a href="#">Lapangan</a>
        <a href="#">Member</a>
        <a href="#">Pembelian</a>
        <a href="#">Tentang</a>
        <a href="#">Kontak</a>
    </div>

    <!-- User Dropdown -->
    <div class="nav-user-container">
        <div class="nav-user">
            <?php if (!empty($photo_profile) && file_exists($photo_profile)): ?>
                <img src="<?php echo htmlspecialchars($photo_profile); ?>" alt="Avatar" class="user-avatar">
            <?php else: ?>
                <i class="fa-solid fa-circle-user user-icon"></i>
            <?php endif; ?>
            <span><?php echo htmlspecialchars($nama_customer); ?></span>
            <i class="fa-solid fa-chevron-down arrow"></i>
        </div>
        <div class="dropdown-menu">
            <div class="user-info-header">
                <span class="u-name"><?php echo htmlspecialchars($nama_customer); ?></span>
                <span class="u-role">Customer</span>
            </div>
            <a href="../profile/profile_customer.php"><i class="fa-solid fa-user"></i> Profil Saya</a>
            <a href="booking_customer.php"><i class="fa-solid fa-calendar-check"></i> Riwayat Booking</a>
            <a href="#"><i class="fa-solid fa-gear"></i> Pengaturan</a>
            <div class="dropdown-divider"></div>
            <a href="#" onclick="confirmHapusAkun(event)" style="color: #ff3b30;"><i class="fa-solid fa-trash-can"></i> Hapus Akun</a>
            <a href="../login/logout.php" class="logout"><i class="fa-solid fa-right-from-bracket"></i> Keluar</a>
        </div>
    </div>
</nav>

<div class="container">
    
    <!-- STEP WIZARD (Panduan Statis Tanpa Animasi / Warna Melompat) -->
    <div class="steps-card">
        <div class="steps-line"></div>
        <div class="steps-container">
            <div class="step-item">
                <div class="step-num" style="background: var(--orange); color: #fff; border-color: var(--orange);"></div>
                <div class="step-title">Pilih Lapangan</div>
                <div class="step-desc">Pilih lapangan favoritmu</div>
            </div>
            <div class="step-item">
                <div class="step-num" style="background: var(--orange); color: #fff; border-color: var(--orange);"></div>
                <div class="step-title">Pilih Jadwal</div>
                <div class="step-desc">Tentukan tanggal & waktu</div>
            </div>
            <div class="step-item">
                <div class="step-num" style="background: var(--orange); color: #fff; border-color: var(--orange);"></div>
                <div class="step-title">Pembayaran</div>
                <div class="step-desc">Pilih metode pembayaran</div>
            </div>
            <div class="step-item">
                <div class="step-num" style="background: var(--orange); color: #fff; border-color: var(--orange);"></div>
                <div class="step-title">Konfirmasi</div>
                <div class="step-desc">Menunggu konfirmasi</div>
            </div>
        </div>
    </div>

    <!-- MAIN FORM CARD -->
    <div class="form-card">
        
        <!-- 1. Pilih Lapangan -->
        <div class="section-header">
            <h2 class="section-title">1. Pilih Lapangan</h2>
            <p class="section-subtitle">Pilih lapangan basket yang aktif dan tersedia</p>
        </div>

        <div class="court-grid">
            <?php if (!empty($lapanganList)): ?>
                <?php foreach ($lapanganList as $index => $lap): 
                    $courtId = $lap['ID_Lapangan'];
                    $courtName = htmlspecialchars($lap['Nama_Lapangan']);
                    $courtPrice = floatval($lap['Harga_Sewa']);
                    $isSelected = ($index === 0) ? 'selected' : '';
                    $imgUrl = !empty($lap['Photo_Lapangan']) ? htmlspecialchars($lap['Photo_Lapangan']) : 'https://images.unsplash.com/photo-1544698310-74ea9d1c8258?q=80&w=600&auto=format&fit=crop';
                ?>
                    <div class="court-card <?= $isSelected ?>" 
                         data-id="<?= $courtId ?>" 
                         data-price="<?= $courtPrice ?>" 
                         data-name="<?= $courtName ?>" 
                         data-img="<?= $imgUrl ?>">
                        <div class="badge-selected-check"><i class="fa-solid fa-check"></i></div>
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
                <p>Tidak ada lapangan aktif yang tersedia saat ini.</p>
            <?php endif; ?>
        </div>

        <!-- TOMBOL STRATEGIS UNTUK MEMBUKA POP-UP JADWAL (MODAL 1) -->
        <button class="btn-trigger-modal" id="btnOpenSchedule" disabled>
            <i class="fa-solid fa-calendar-days"></i> Pilih Jadwal Bermain
        </button>
    </div>
</div>

<!-- POP-UP MODAL 1: PILIH JADWAL -->
<div class="booking-modal-overlay" id="scheduleModal">
    <div class="summary-card" style="max-width: 600px;">
        <button class="booking-modal-close" id="btnCloseSchedule">
            <i class="fa-solid fa-xmark"></i>
        </button>
        
        <div class="section-header">
            <h2 class="section-title">2. Pilih Jadwal Bermain</h2>
            <p class="section-subtitle">Tentukan waktu bermain berdasarkan ketersediaan jadwal lapangan</p>
        </div>

        <div class="schedule-controls">
            <div class="input-group">
                <label class="input-label">Pilih Slot Jadwal Tersedia</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-calendar-days"></i>
                    <select id="slotSelect" class="form-control">
                        <!-- Diisi secara dinamis dengan JS AJAX -->
                    </select>
                </div>
            </div>

            <!-- Status Box -->
            <div class="status-availability-box" id="availabilityBox">
                <div class="status-avail-icon" id="availIcon"><i class="fa-solid fa-circle-check"></i></div>
                <div>
                    <div class="status-avail-title" id="availTitle">Memuat slot...</div>
                    <div class="status-avail-desc" id="lblDuration">Durasi: - jam</div>
                </div>
            </div>
        </div>

        <div class="alert-banner" style="margin-top: 20px; margin-bottom: 24px;">
            <i class="fa-solid fa-circle-info"></i>
            <p class="alert-banner-text">Semua transaksi sewa disesuaikan dengan daftar slot jadwal yang dibuat oleh pihak operator HoopBall Arena.</p>
        </div>

        <!-- TOMBOL STRATEGIS UNTUK MELANJUTKAN KE MODAL RINGKASAN & BAYAR (MODAL 2) -->
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

        <!-- Status Member -->
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

        <!-- Promo Segment -->
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

        <!-- Price Breakdown -->
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

        <!-- Payment Methods -->
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

        <!-- Submit Button -->
        <button class="btn-booking" id="btnSubmit" disabled>
            <i class="fa-solid fa-lock"></i> Selesaikan Booking
        </button>
        <div class="booking-disclaimer">
            <i class="fa-solid fa-circle-check"></i> Enkripsi data aman terverifikasi
        </div>
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
                <li><a href="#">Lapangan</a></li>
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

<!-- SUCCESS TOAST POPUP (BOTTOM LEFT) -->
<div class="success-toast" id="successToast">
    <div class="toast-icon">
        <i class="fa-solid fa-circle-check"></i>
    </div>
    <div class="toast-body">
        <div class="toast-title">Booking Berhasil Dibuat!</div>
        <div class="toast-subtitle">Silakan lakukan konfirmasi pembayaran secepatnya.</div>
        <div class="toast-meta-pills">
            <span class="toast-pill" id="pillCourt">-</span>
            <span class="toast-pill" id="pillDate">-</span>
            <span class="toast-pill" id="pillTime">-</span>
        </div>
    </div>
    <button class="btn-toast-action" onclick="location.reload();">Selesai</button>
    <button class="toast-close" id="btnCloseToast"><i class="fa-solid fa-xmark"></i></button>
</div>

<script>
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

    // Elements
    const courts = document.querySelectorAll('.court-card');
    const slotSelect = document.getElementById('slotSelect');
    const promoSelect = document.getElementById('promoSelect');
    const payments = document.querySelectorAll('.payment-card');
    
    // UI Recalculation Targets
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

    // Pop-up Trigger Elements
    const btnOpenSchedule = document.getElementById('btnOpenSchedule');
    const scheduleModal = document.getElementById('scheduleModal');
    const btnCloseSchedule = document.getElementById('btnCloseSchedule');
    
    const btnGoToSummary = document.getElementById('btnGoToSummary');
    const bookingModal = document.getElementById('bookingModal');
    const btnCloseSummary = document.getElementById('btnCloseSummary');

    // Toast Elements
    const successToast = document.getElementById('successToast');
    const btnCloseToast = document.getElementById('btnCloseToast');
    const pillCourt = document.getElementById('pillCourt');
    const pillDate = document.getElementById('pillDate');
    const pillTime = document.getElementById('pillTime');

    // Formatter Rupiah
    function formatRupiah(number) {
        return 'Rp ' + Math.max(0, number).toLocaleString('id-ID');
    }

    // Modal 1 (Pilih Jadwal) Trigger Events
    btnOpenSchedule.addEventListener('click', function() {
        scheduleModal.style.display = 'flex';
    });

    btnCloseSchedule.addEventListener('click', function() {
        scheduleModal.style.display = 'none';
    });

    // Modal 2 (Ringkasan) Trigger Events
    btnGoToSummary.addEventListener('click', function() {
        scheduleModal.style.display = 'none';
        bookingModal.style.display = 'flex';
    });

    btnCloseSummary.addEventListener('click', function() {
        bookingModal.style.display = 'none';
    });

    // Tutup modal jika user klik di area luar modal
    window.addEventListener('click', function(e) {
        if (e.target === scheduleModal) {
            scheduleModal.style.display = 'none';
        }
        if (e.target === bookingModal) {
            bookingModal.style.display = 'none';
        }
    });

    // Load available slots dynamically using PHP AJAX Handler
    function loadSlots(courtId) {
        slotSelect.innerHTML = '<option value="">Memuat slot waktu...</option>';
        btnSubmit.disabled = true;
        btnOpenSchedule.disabled = true;
        btnGoToSummary.disabled = true;
        
        fetch(`booking_customer.php?action=get_slots&court_id=${courtId}`)
            .then(response => response.json())
            .then(slots => {
                slotSelect.innerHTML = '';
                if (slots.length === 0) {
                    slotSelect.innerHTML = '<option value="">Tidak ada jadwal kosong</option>';
                    showSlotStatus(false, 'Tidak ada jadwal', 'Semua slot terbooking');
                    return;
                }

                slots.forEach((slot, index) => {
                    const opt = document.createElement('option');
                    opt.value = slot.ID_Jadwal;
                    opt.setAttribute('data-tanggal', slot.Tanggal_Formatted);
                    opt.setAttribute('data-mulai', slot.Jam_Mulai);
                    opt.setAttribute('data-selesai', slot.Jam_Selesai);
                    opt.innerText = `${slot.Tanggal_Formatted} [${slot.Jam_Mulai} - ${slot.Jam_Selesai}]`;
                    slotSelect.appendChild(opt);
                });

                // Select first slot by default
                slotSelect.dispatchEvent(new Event('change'));
                btnOpenSchedule.disabled = false;
            })
            .catch(err => {
                console.error("Gagal memuat jadwal:", err);
                slotSelect.innerHTML = '<option value="">Gagal memuat jadwal</option>';
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

    // Recalculate price breakdown & UI elements
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
            discount = memberDiscount; // Flat discount
        } else if (promoSelect) {
            const selectedPromoOpt = promoSelect.options[promoSelect.selectedIndex];
            if (selectedPromoOpt) {
                discount = parseFloat(selectedPromoOpt.getAttribute('data-discount') || 0);
            }
        }

        const totalPayable = Math.max(0, basePrice - discount);

        // Update UI
        lblNormalPriceLabel.innerText = `Harga Sewa (${selectedSlotDuration} jam)`;
        lblNormalPrice.innerText = formatRupiah(basePrice);
        if (isMember) {
            // Member UI has statically linked element
        } else if (lblPromoBreakdown) {
            lblPromoBreakdown.innerText = `-Rp ${discount.toLocaleString('id-ID')}`;
        }
        lblTotalPrice.innerText = formatRupiah(totalPayable);

        // Update Sidebar Right Summaries
        sumCourtName.innerText = selectedCourtName;
        sumImg.src = selectedCourtImg;
        sumPlayDate.innerText = selectedSlotDateFormatted;
        sumTimeLabel.innerText = `${selectedSlotTimeFormatted} (${selectedSlotDuration} jam)`;

        btnSubmit.disabled = false;
        btnGoToSummary.disabled = false;
    }

    // Court selection event
    courts.forEach(court => {
        court.addEventListener('click', function() {
            courts.forEach(c => c.classList.remove('selected'));
            this.classList.add('selected');

            selectedCourtId = this.getAttribute('data-id');
            selectedCourtPrice = parseFloat(this.getAttribute('data-price'));
            selectedCourtName = this.getAttribute('data-name');
            selectedCourtImg = this.getAttribute('data-img');

            loadSlots(selectedCourtId);
        });
    });

    // Slot select trigger
    slotSelect.addEventListener('change', function() {
        const opt = this.options[this.selectedIndex];
        if (!opt || !opt.value) {
            selectedSlotId = null;
            showSlotStatus(false, 'Jadwal belum dipilih', 'Pilih slot waktu terlebih dahulu');
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

    // Promo selector event
    if (promoSelect) {
        promoSelect.addEventListener('change', calculatePrices);
    }

    // Payment Selection
    payments.forEach(payment => {
        payment.addEventListener('click', function() {
            payments.forEach(p => p.classList.remove('selected'));
            this.classList.add('selected');
            selectedPaymentMethod = this.getAttribute('data-method');
        });
    });

    // Submit Checkout
    btnSubmit.addEventListener('click', function() {
        if (!selectedSlotId) return;

        btnSubmit.disabled = true;
        btnSubmit.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Memproses...';

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
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(checkoutData)
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                // Tutup modal terlebih dahulu sebelum menampilkan animasi sukses
                bookingModal.style.display = 'none';

                // Update Toast meta-information
                pillCourt.innerText = selectedCourtName;
                pillDate.innerText = selectedSlotDateFormatted;
                pillTime.innerText = selectedSlotTimeFormatted;

                // Show success toast
                successToast.style.display = 'flex';
            } else {
                alert(result.message || "Gagal melakukan pemesanan sewa.");
                btnSubmit.disabled = false;
                btnSubmit.innerHTML = '<i class="fa-solid fa-lock"></i> Selesaikan Booking';
            }
        })
        .catch(err => {
            console.error("Kesalahan checkout:", err);
            alert("Kesalahan koneksi ke server database.");
            btnSubmit.disabled = false;
            btnSubmit.innerHTML = '<i class="fa-solid fa-lock"></i> Selesaikan Booking';
        });
    });

    btnCloseToast.addEventListener('click', function() {
        successToast.style.display = 'none';
        location.reload();
    });

    // Initialize first court selection load
    document.addEventListener("DOMContentLoaded", function() {
        const activeCourt = document.querySelector('.court-card.selected');
        if (activeCourt) {
            activeCourt.click();
        }
    });

    // ============================================================================
    // HARD DELETE AKUN CONFIRMATION
    // ============================================================================
    function confirmHapusAkun(e) {
        e.preventDefault();
        Swal.fire({
            title: 'Hapus Akun Permanen?',
            html: '<strong style="color:#FF3B30;">PERINGATAN:</strong> Tindakan ini tidak dapat dibatalkan!<br><br>' +
                  'Akun Anda akan dihapus dari sistem dan Anda harus mendaftar ulang untuk menggunakan layanan kami.<br><br>' +
                  '<span style="color:#8E8E93; font-size:12px;">Data akan dihapus secara permanen.</span>',
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
                    willClose: () => {
                        clearInterval(timerInterval);
                    }
                }).then(() => {
                    window.location.href = '?hapus_akun=1';
                });
            }
        });
    }

    // ============================================================================
    // URL PARAMETER NOTIFICATION (TOAST STYLE)
    // ============================================================================
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
</script>

</body>
</html>