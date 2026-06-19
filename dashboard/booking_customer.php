<?php
// ============================================================================
// BUFFER OUTPUT
// ============================================================================
ob_start();

session_start();
include '../includes/auth_helper.php';
include '../includes/config.php';

// ============================================================================
// CEK AKSES
// ============================================================================
cek_akses('customer');

$id_customer = $_SESSION['id_customer'] ?? $_SESSION['ID_Customer'] ?? $_SESSION['id_akun'] ?? '';
if (empty($id_customer)) {
    ob_end_clean();
    header("Location: ../login/login.php");
    exit();
}

// ============================================================================
// VALIDASI CUSTOMER AKTIF
// ============================================================================
$cek_cust = sqlsrv_query($conn,
    "SELECT Nama_Customer, Photo_Profile, Is_Deleted, Status FROM Customer WHERE ID_Customer = ?",
    array($id_customer)
);
$nama_customer = 'Pelanggan';
$photo_profile = '';
if ($cek_cust) {
    $row_c = sqlsrv_fetch_array($cek_cust, SQLSRV_FETCH_ASSOC);
    if ($row_c) {
        if ($row_c['Is_Deleted'] == 1 || $row_c['Status'] == 0) {
            $_SESSION = array();
            session_destroy();
            ob_end_clean();
            header("Location: ../login/login.php?status=error&msg=Akun Anda dinonaktifkan.");
            exit();
        }
        $nama_customer = $row_c['Nama_Customer'];
        $photo_profile = $row_c['Photo_Profile'];
    }
}

// ============================================================================
// CEK MEMBER AKTIF
// ============================================================================
$member_data = null;
$member_check = sqlsrv_query($conn,
    "SELECT TOP 1 L.ID_Langganan, T.Nama_Tipe, T.Potongan_Harga
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
$has_member    = !empty($member_data);
$member_tipe   = $has_member ? $member_data['Nama_Tipe'] : '';
$member_potong = $has_member ? floatval($member_data['Potongan_Harga']) : 0;

// ============================================================================
// AMBIL SEMUA LAPANGAN AKTIF
// ============================================================================
$lapangan_list = [];
$q_lap = sqlsrv_query($conn,
    "SELECT ID_Lapangan, Nama_Lapangan, Harga_Sewa, Photo_Lapangan
     FROM Lapangan WHERE Status = 1 AND Is_Deleted = 0 ORDER BY Nama_Lapangan ASC"
);
if ($q_lap) {
    while ($r = sqlsrv_fetch_array($q_lap, SQLSRV_FETCH_ASSOC)) {
        $lapangan_list[] = $r;
    }
}

// ============================================================================
// AMBIL PROMO AKTIF (hanya jika tidak member)
// ============================================================================
$promo_list = [];
if (!$has_member) {
    $q_promo = sqlsrv_query($conn,
        "SELECT ID_Promo, Nama_Promo, Diskon
         FROM Promo
         WHERE Status = 1 AND Is_Deleted = 0
           AND CAST(GETDATE() AS DATE) BETWEEN Tanggal_Mulai AND Tanggal_Selesai
         ORDER BY Diskon DESC"
    );
    if ($q_promo) {
        while ($r = sqlsrv_fetch_array($q_promo, SQLSRV_FETCH_ASSOC)) {
            $promo_list[] = $r;
        }
    }
}

// ============================================================================
// AMBIL JADWAL TERSEDIA
// ============================================================================
$jadwal_list = [];
$q_jadwal = sqlsrv_query($conn,
    "SELECT J.ID_Jadwal, J.ID_Lapangan, J.Tanggal, J.Jam_Mulai, J.Jam_Selesai,
            L.Nama_Lapangan, L.Harga_Sewa
     FROM Jadwal J
     INNER JOIN Lapangan L ON J.ID_Lapangan = L.ID_Lapangan
     WHERE J.Status = 1 AND J.Is_Deleted = 0
       AND L.Status = 1 AND L.Is_Deleted = 0
       AND J.ID_Jadwal NOT IN (
           SELECT ID_Jadwal FROM Booking WHERE Status IN (0,1,2)
       )
     ORDER BY J.Tanggal ASC, J.Jam_Mulai ASC"
);
if ($q_jadwal) {
    while ($r = sqlsrv_fetch_array($q_jadwal, SQLSRV_FETCH_ASSOC)) {
        $jadwal_list[] = $r;
    }
}

// ============================================================================
// AJAX: AMBIL JADWAL PER LAPANGAN
// ============================================================================
if (isset($_GET['ajax_jadwal'])) {
    header('Content-Type: application/json');
    $id_lap = intval($_GET['id_lapangan'] ?? 0);
    $result = [];
    $q = sqlsrv_query($conn,
        "SELECT J.ID_Jadwal, J.Tanggal, J.Jam_Mulai, J.Jam_Selesai, L.Harga_Sewa, L.Nama_Lapangan
         FROM Jadwal J
         INNER JOIN Lapangan L ON J.ID_Lapangan = L.ID_Lapangan
         WHERE J.Status = 1 AND J.Is_Deleted = 0 AND L.Status = 1 AND L.Is_Deleted = 0
           AND J.ID_Lapangan = ?
           AND J.ID_Jadwal NOT IN (SELECT ID_Jadwal FROM Booking WHERE Status IN (0,1,2))
         ORDER BY J.Tanggal ASC, J.Jam_Mulai ASC",
        array($id_lap)
    );
    if ($q) {
        while ($r = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)) {
            $result[] = [
                'id'        => $r['ID_Jadwal'],
                'tanggal'   => ($r['Tanggal'] instanceof DateTime) ? $r['Tanggal']->format('Y-m-d') : $r['Tanggal'],
                'jam_mulai' => ($r['Jam_Mulai'] instanceof DateTime) ? $r['Jam_Mulai']->format('H:i') : substr($r['Jam_Mulai'], 0, 5),
                'jam_selesai' => ($r['Jam_Selesai'] instanceof DateTime) ? $r['Jam_Selesai']->format('H:i') : substr($r['Jam_Selesai'], 0, 5),
                'harga'     => floatval($r['Harga_Sewa']),
                'nama_lapangan' => $r['Nama_Lapangan'],
            ];
        }
    }
    ob_end_clean();
    echo json_encode($result);
    exit();
}

// ============================================================================
// AJAX: SUBMIT BOOKING
// ============================================================================
if (isset($_POST['ajax_booking'])) {
    header('Content-Type: application/json');

    $id_jadwal   = intval($_POST['id_jadwal'] ?? 0);
    $id_promo    = intval($_POST['id_promo'] ?? 0);
    $metode      = trim($_POST['metode'] ?? '');
    $total_bayar = floatval($_POST['total_bayar'] ?? 0);

    if (!$id_jadwal || !$metode || $total_bayar <= 0) {
        ob_end_clean();
        echo json_encode(['success' => false, 'msg' => 'Data tidak lengkap.']);
        exit();
    }

    $allowed_methods = ['Virtual Account', 'QRIS'];
    if (!in_array($metode, $allowed_methods)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'msg' => 'Metode pembayaran tidak valid.']);
        exit();
    }

    $cek_jadwal = sqlsrv_query($conn,
        "SELECT ID_Jadwal FROM Jadwal WHERE ID_Jadwal = ? AND Status = 1 AND Is_Deleted = 0",
        array($id_jadwal)
    );
    if (!$cek_jadwal || !sqlsrv_fetch_array($cek_jadwal, SQLSRV_FETCH_ASSOC)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'msg' => 'Jadwal tidak tersedia atau sudah dipesan.']);
        exit();
    }

    $cek_conflict = sqlsrv_query($conn,
        "SELECT ID_Booking FROM Booking WHERE ID_Jadwal = ? AND Status IN (0,1,2)",
        array($id_jadwal)
    );
    if ($cek_conflict && sqlsrv_fetch_array($cek_conflict, SQLSRV_FETCH_ASSOC)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'msg' => 'Jadwal sudah dipesan oleh customer lain.']);
        exit();
    }

    $cek_kary = sqlsrv_query($conn,
        "SELECT TOP 1 ID_Karyawan FROM Karyawan WHERE Status = 1 AND Is_Deleted = 0 AND Jabatan = 1 ORDER BY NEWID()"
    );
    $id_karyawan = 0;
    if ($cek_kary) {
        $rk = sqlsrv_fetch_array($cek_kary, SQLSRV_FETCH_ASSOC);
        $id_karyawan = $rk ? intval($rk['ID_Karyawan']) : 0;
    }
    if (!$id_karyawan) {
        $cek_kary2 = sqlsrv_query($conn, "SELECT TOP 1 ID_Karyawan FROM Karyawan WHERE Status = 1 AND Is_Deleted = 0");
        if ($cek_kary2) {
            $rk2 = sqlsrv_fetch_array($cek_kary2, SQLSRV_FETCH_ASSOC);
            $id_karyawan = $rk2 ? intval($rk2['ID_Karyawan']) : 1;
        }
    }

    $id_promo_val = ($id_promo > 0 && !$has_member) ? $id_promo : null;
    $tanggal_booking = date('Y-m-d');
    $created_by = strval($id_customer);

    $params = array(
        $id_customer,
        $id_karyawan,
        $id_jadwal,
        $id_promo_val,
        $tanggal_booking,
        $metode,
        $total_bayar,
        0,
        $created_by
    );

    $stmt = sqlsrv_query($conn,
        "INSERT INTO Booking
         (ID_Customer, ID_Karyawan, ID_Jadwal, ID_Promo, Tanggal_Booking,
          Metode_Pembayaran, Total_Bayar, Status, Created_By, Created_Date)
         OUTPUT INSERTED.ID_Booking
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, GETDATE())",
        $params
    );

    if ($stmt) {
        $row_insert = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        $id_booking = $row_insert ? $row_insert['ID_Booking'] : 0;
        ob_end_clean();
        echo json_encode(['success' => true, 'id_booking' => $id_booking, 'msg' => 'Booking berhasil dibuat.']);
    } else {
        $errors = sqlsrv_errors();
        $err_msg = $errors ? $errors[0]['message'] : 'Unknown error';
        ob_end_clean();
        echo json_encode(['success' => false, 'msg' => 'Gagal menyimpan booking: ' . $err_msg]);
    }
    exit();
}

// ============================================================================
// AJAX: BATALKAN BOOKING
// ============================================================================
if (isset($_POST['ajax_batal_booking'])) {
    header('Content-Type: application/json');
    $id_booking = intval($_POST['id_booking'] ?? 0);
    
    if (!$id_booking) {
        ob_end_clean();
        echo json_encode(['success' => false, 'msg' => 'ID booking tidak valid.']);
        exit();
    }
    
    $cek = sqlsrv_query($conn,
        "SELECT ID_Booking, ID_Jadwal FROM Booking WHERE ID_Booking = ? AND ID_Customer = ? AND Status = 0",
        array($id_booking, $id_customer)
    );
    $booking = sqlsrv_fetch_array($cek, SQLSRV_FETCH_ASSOC);
    
    if (!$booking) {
        ob_end_clean();
        echo json_encode(['success' => false, 'msg' => 'Booking tidak ditemukan atau sudah tidak bisa dibatalkan.']);
        exit();
    }
    
    $del = sqlsrv_query($conn, "DELETE FROM Booking WHERE ID_Booking = ?", array($id_booking));
    
    if ($del) {
        ob_end_clean();
        echo json_encode(['success' => true, 'msg' => 'Booking berhasil dibatalkan.']);
    } else {
        $errors = sqlsrv_errors();
        $err_msg = $errors ? $errors[0]['message'] : 'Unknown error';
        ob_end_clean();
        echo json_encode(['success' => false, 'msg' => 'Gagal membatalkan booking: ' . $err_msg]);
    }
    exit();
}

// ============================================================================
// AJAX: CEK STATUS BOOKING
// ============================================================================
if (isset($_GET['ajax_cek_status'])) {
    header('Content-Type: application/json');
    $id_booking = intval($_GET['id_booking'] ?? 0);
    $q = sqlsrv_query($conn,
        "SELECT Status FROM Booking WHERE ID_Booking = ? AND ID_Customer = ?",
        array($id_booking, $id_customer)
    );
    $status = -1;
    if ($q) {
        $r = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC);
        if ($r) $status = intval($r['Status']);
    }
    ob_end_clean();
    echo json_encode(['status' => $status]);
    exit();
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================
function formatTanggal($tanggal) {
    if (empty($tanggal)) return '-';
    if ($tanggal instanceof DateTime) return $tanggal->format('d M Y');
    return date('d M Y', strtotime($tanggal));
}
function formatJam($jam) {
    if (empty($jam)) return '-';
    if ($jam instanceof DateTime) return $jam->format('H:i');
    return substr($jam, 0, 5);
}
function rupiahFormat($n) {
    return 'Rp ' . number_format($n, 0, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Lapangan | HoopBall</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        :root {
            --primary: #FF5200;
            --primary-hover: #E04800;
            --dark-bg: #0B0B0C;
            --card-dark: #121214;
            --text-gray: #8E8E93;
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
        }
        .swal-toast-front { z-index: 100000 !important; }
        .swal2-container { z-index: 100000 !important; }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--white);
            color: #111;
            overflow-x: hidden;
        }

        /* ---- NAVBAR (PUTIH) — SAMA DENGAN VIEW_CUSTOMER ---- */
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
        .nav-logo { display: flex; align-items: center; text-decoration: none; gap: 10px; }
        .nav-logo img { height: 70px; width: auto; }
        .nav-logo span {
            color: #1C1C1E;
            font-size: 20px;
            font-weight: 800;
            letter-spacing: -0.5px;
        }
        .nav-links { display: flex; align-items: center; gap: 8px; }
        .nav-links a {
            color: #636366; text-decoration: none; font-size: 14px; font-weight: 500;
            padding: 8px 16px; border-radius: 20px; transition: all 0.2s ease;
        }
        .nav-links a:hover { color: #1C1C1E; }
        .nav-links a.active { color: var(--primary); font-weight: 600; }

        /* USER DROPDOWN */
        .nav-user-container { position: relative; height: 76px; display: flex; align-items: center; }
        .nav-user {
            background: #F2F2F7; border: 1px solid #E5E5EA; padding: 8px 16px; border-radius: 50px;
            color: #1C1C1E; font-size: 14px; font-weight: 600; cursor: pointer;
            display: flex; align-items: center; gap: 10px; transition: 0.2s;
        }
        .nav-user:hover { background: #E5E5EA; border-color: var(--primary); }
        .nav-user img.user-avatar { width: 24px; height: 24px; border-radius: 50%; object-fit: cover; }
        .nav-user i.user-icon { font-size: 16px; color: var(--primary); }
        .nav-user i.arrow { font-size: 11px; color: #8E8E93; transition: 0.3s; }
        .nav-user-container:hover i.arrow { transform: rotate(180deg); color: var(--primary); }
        .dropdown-menu {
            position: absolute; top: 85%; right: 0; background: #16161a;
            min-width: 220px; border-radius: 12px; border: 1px solid #2d2d33;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5); padding: 8px 0; display: none; z-index: 1001;
            animation: fadeIn 0.2s ease-out;
        }
        .nav-user-container:hover .dropdown-menu { display: block; }
        .dropdown-menu .user-info-header { padding: 12px 20px; border-bottom: 1px solid #2d2d33; margin-bottom: 6px; }
        .dropdown-menu .user-info-header .u-name { color: var(--white); font-size: 14px; font-weight: 700; display: block; }
        .dropdown-menu .user-info-header .u-role { color: var(--text-gray); font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 2px; display: block; }
        .dropdown-menu a { display: flex; align-items: center; gap: 12px; padding: 10px 20px; color: #c5c5ca; text-decoration: none; font-size: 13px; font-weight: 500; transition: 0.2s; }
        .dropdown-menu a i { font-size: 14px; width: 16px; text-align: center; }
        .dropdown-menu a:hover { background: #222227; color: var(--primary); }
        .dropdown-divider { height: 1px; background: #2d2d33; margin: 6px 0; }
        .dropdown-menu a.logout:hover { color: #ff3b30; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }

        /* ---- HERO SECTION — SAMA DENGAN VIEW_CUSTOMER (DARK THEME) ---- */
        .hero {
            background-color: var(--dark-bg);
            background-image: linear-gradient(180deg, rgba(11,11,12,0.6) 0%, rgba(11,11,12,0.9) 100%), url('https://images.unsplash.com/photo-1546519638-68e109498ffc?q=80&w=2000');
            background-size: cover;
            background-position: center;
            min-height: 600px;
            padding: 60px 80px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 40px;
        }
        .hero-left { max-width: 620px; }
        .hero-title {
            font-size: 54px;
            font-weight: 800;
            color: var(--white);
            line-height: 1.15;
            margin-bottom: 20px;
        }
        .hero-title span { color: var(--primary); }
        .hero-desc {
            color: #A0A0A5;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 36px;
        }
        .hero-btns { display: flex; gap: 16px; }
        .btn-primary {
            background: var(--primary);
            color: var(--white);
            border: none;
            padding: 14px 28px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 15px;
            text-decoration: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: 0.2s;
        }
        .btn-primary:hover { background: var(--primary-hover); }
        .btn-outline {
            background: transparent;
            color: var(--white);
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 14px 28px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 15px;
            text-decoration: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: 0.2s;
        }
        .btn-outline:hover { background: rgba(255, 255, 255, 0.05); border-color: var(--white); }

        /* MEMBER BADGE */
        .member-badge-hero {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--green-lt);
            border: 1px solid var(--green);
            color: var(--green);
            padding: 8px 16px;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 20px;
        }
        .member-badge-hero i { font-size: 14px; }

        /* WIDGET CARI LAPANGAN — SAMA DENGAN VIEW_CUSTOMER */
        .search-widget {
            background: rgba(18, 18, 20, 0.85);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 16px;
            width: 440px;
            padding: 28px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.4);
        }
        .widget-title {
            color: var(--white);
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 24px;
        }
        .form-group {
            margin-bottom: 16px;
            position: relative;
        }
        .form-label {
            display: block;
            color: #A0A0A5;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .input-wrapper { position: relative; }
        .input-wrapper i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #636366;
            font-size: 14px;
        }
        .form-select, .form-input {
            width: 100%;
            background: #1C1C1E;
            border: 1px solid #2C2C2E;
            border-radius: 8px;
            padding: 12px 14px 12px 40px;
            color: var(--white);
            font-size: 14px;
            font-family: inherit;
            outline: none;
            appearance: none;
            transition: 0.2s;
        }
        .form-select:focus, .form-input:focus { border-color: var(--primary); }
        .form-select {
            background-image: url("data:image/svg+xml;utf8,<svg fill='none' height='24' stroke='%238E8E93' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' viewBox='0 0 24 24' width='24' xmlns='http://www.w3.org/2000/svg'><polyline points='6 9 12 15 18 9'/></svg>");
            background-repeat: no-repeat;
            background-position: right 14px center;
            background-size: 16px;
        }
        .form-row-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        .btn-widget {
            background: var(--primary);
            color: var(--white);
            border: none;
            width: 100%;
            padding: 14px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            margin-top: 10px;
            transition: 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-widget:hover { background: var(--primary-hover); }

        /* ---- ROW FITUR — SAMA DENGAN VIEW_CUSTOMER ---- */
        .features-row {
            padding: 40px 80px;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 24px;
            background: var(--white);
            border-bottom: 1px solid #F2F2F7;
        }
        .feature-card {
            display: flex;
            gap: 16px;
            align-items: flex-start;
        }
        .feature-icon-circle {
            background: #FFF0E6;
            width: 48px;
            height: 48px;
            min-width: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .feature-icon-circle i {
            color: var(--primary);
            font-size: 20px;
        }
        .feature-text h4 {
            font-size: 15px;
            font-weight: 700;
            margin-bottom: 6px;
            color: #1C1C1E;
        }
        .feature-text p {
            font-size: 13px;
            color: #636366;
            line-height: 1.5;
        }

        /* ---- MAIN CONTAINER — SAMA DENGAN VIEW_CUSTOMER ---- */
        .main-container {
            padding: 60px 80px;
            max-width: 1440px;
            margin: 0 auto;
        }

        /* ---- SECTION HEADER — SAMA DENGAN VIEW_CUSTOMER ---- */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-bottom: 24px;
        }
        .section-title {
            font-size: 24px;
            font-weight: 800;
            color: #111;
        }
        .section-subtitle {
            font-size: 14px;
            color: #636366;
            margin-top: 4px;
        }
        .section-action {
            color: var(--primary);
            text-decoration: none;
            font-size: 14px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .section-action:hover { color: var(--primary-hover); }

        /* ---- BOOKING FORM CARD — STYLE SAMA DENGAN VIEW_CUSTOMER CARDS ---- */
        .booking-card {
            background: var(--white);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 40px;
            margin-bottom: 40px;
        }
        .booking-card-title {
            font-size: 20px;
            font-weight: 800;
            color: #1C1C1E;
            margin-bottom: 32px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .booking-card-title i { color: var(--primary); font-size: 22px; }

        /* STEP INDICATOR */
        .step-indicator {
            display: flex;
            gap: 0;
            margin-bottom: 40px;
            position: relative;
        }
        .step-indicator::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 40px;
            right: 40px;
            height: 2px;
            background: #E5E5EA;
            z-index: 0;
        }
        .step-item {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            position: relative;
            z-index: 1;
        }
        .step-circle {
            width: 40px; height: 40px;
            border-radius: 50%;
            background: #E5E5EA;
            color: #8E8E93;
            display: flex; align-items: center; justify-content: center;
            font-size: 14px; font-weight: 800;
            transition: 0.3s;
            border: 3px solid white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .step-circle.active { background: var(--primary); color: var(--white); }
        .step-circle.done { background: var(--green); color: var(--white); }
        .step-label { font-size: 12px; font-weight: 700; color: #8E8E93; text-align: center; }
        .step-label.active { color: var(--primary); }
        .step-label.done { color: var(--green); }

        /* FORM SECTION */
        .form-section { display: none; }
        .form-section.visible { display: block; }

        .form-group-booking { margin-bottom: 20px; }
        .form-label-booking {
            display: block; font-size: 12px; font-weight: 700;
            color: #1C1C1E; margin-bottom: 8px;
        }

        /* LAPANGAN CARD GRID */
        .lapangan-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            margin-top: 8px;
        }
        .lapangan-option {
            border: 2px solid var(--border-color);
            border-radius: 16px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
            background: white;
        }
        .lapangan-option:hover { 
            border-color: var(--primary); 
            background: #FFF9F6; 
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(255,82,0,0.08);
        }
        .lapangan-option.selected { 
            border-color: var(--primary); 
            background: #FFF0E6; 
        }
        .lapangan-option input[type="radio"] { display: none; }
        .lap-name { font-size: 15px; font-weight: 800; color: #1C1C1E; margin-bottom: 6px; }
        .lap-price { font-size: 14px; color: var(--primary); font-weight: 700; }
        .lap-check {
            position: absolute; top: 16px; right: 16px;
            width: 24px; height: 24px; border-radius: 50%;
            background: var(--border-color); border: none;
            display: flex; align-items: center; justify-content: center;
            transition: 0.2s;
        }
        .lapangan-option.selected .lap-check { background: var(--primary); }
        .lapangan-option.selected .lap-check::after { content: '✓'; color: white; font-size: 12px; font-weight: 800; }

        /* JADWAL SLOT */
        .jadwal-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-top: 8px;
        }
        .jadwal-slot {
            border: 2px solid #E5E5EA;
            border-radius: 12px;
            padding: 16px;
            cursor: pointer;
            text-align: center;
            transition: all 0.2s ease;
            background: white;
        }
        .jadwal-slot:hover { 
            border-color: var(--primary); 
            background: #FFF9F6; 
            transform: translateY(-2px);
        }
        .jadwal-slot.selected { 
            border-color: var(--primary); 
            background: #FFF0E6; 
        }
        .slot-tanggal { font-size: 12px; color: #8E8E93; font-weight: 600; }
        .slot-jam { font-size: 15px; font-weight: 800; color: #1C1C1E; margin: 4px 0; }
        .slot-status { font-size: 11px; color: var(--green); font-weight: 700; }
        .jadwal-empty { text-align: center; padding: 40px; color: #8E8E93; grid-column: span 3; }
        .jadwal-empty i { font-size: 32px; margin-bottom: 12px; color: #AEAEB2; display: block; }

        /* METODE PEMBAYARAN */
        .metode-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-top: 8px;
        }
        .metode-card {
            border: 2px solid var(--border-color);
            border-radius: 16px;
            padding: 24px 20px;
            cursor: pointer;
            text-align: center;
            transition: all 0.2s ease;
            background: white;
        }
        .metode-card:hover { 
            border-color: var(--primary); 
            background: #FFF9F6; 
            transform: translateY(-2px);
        }
        .metode-card.selected { 
            border-color: var(--primary); 
            background: #FFF0E6; 
        }
        .metode-card input[type="radio"] { display: none; }
        .metode-icon { font-size: 32px; margin-bottom: 10px; display: block; }
        .metode-name { font-size: 15px; font-weight: 800; color: #1C1C1E; }
        .metode-desc { font-size: 12px; color: #8E8E93; margin-top: 6px; }

        /* PROMO SELECT */
        .promo-option {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px 20px;
            border: 1.5px solid var(--border-color);
            border-radius: 12px;
            cursor: pointer;
            margin-bottom: 12px;
            transition: all 0.2s ease;
            background: white;
        }
        .promo-option:hover { border-color: var(--primary); }
        .promo-option.selected { border-color: var(--primary); background: #FFF0E6; }
        .promo-option input[type="radio"] { accent-color: var(--primary); }
        .promo-detail { flex: 1; }
        .promo-name { font-size: 14px; font-weight: 700; color: #1C1C1E; }
        .promo-disc { font-size: 13px; color: var(--green); font-weight: 700; }

        /* NAVIGATION BUTTONS */
        .btn-row {
            display: flex;
            gap: 12px;
            margin-top: 28px;
        }
        .btn-back {
            background: var(--white); color: #1C1C1E;
            border: 1.5px solid var(--border-color);
            padding: 14px 28px; border-radius: 12px;
            font-size: 14px; font-weight: 700; cursor: pointer;
            transition: 0.2s;
        }
        .btn-back:hover { background: #F2F2F7; }
        .btn-next {
            flex: 1;
            background: var(--primary); color: var(--white);
            border: none; padding: 14px 28px; border-radius: 12px;
            font-size: 14px; font-weight: 700; cursor: pointer;
            transition: all 0.2s ease; 
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .btn-next:hover { 
            background: var(--primary-hover); 
            transform: translateY(-1px);
            box-shadow: 0 4px 16px rgba(255,82,0,0.25);
        }
        .btn-next:disabled { background: #AEAEB2; cursor: not-allowed; transform: none; box-shadow: none; }

        /* ---- MODAL STYLES ---- */
        .modal-overlay {
            display: none;
            position: fixed; inset: 0;
            background: rgba(0,0,0,0.7);
            z-index: 9999;
            align-items: center; justify-content: center;
            padding: 20px;
        }
        .modal-overlay.show { display: flex; }
        .modal-box {
            background: var(--white);
            border-radius: 24px;
            padding: 0;
            max-width: 420px;
            width: 100%;
            text-align: center;
            animation: slideUp 0.3s ease-out;
            overflow: hidden;
            box-shadow: 0 25px 50px rgba(0,0,0,0.25);
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .qris-header {
            background: linear-gradient(135deg, var(--primary) 0%, #FF7A3D 100%);
            padding: 32px 24px 24px;
            color: var(--white);
        }
        .qris-header-icon {
            width: 64px; height: 64px;
            background: rgba(255,255,255,0.2);
            border-radius: 20px;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 16px;
            font-size: 32px;
            backdrop-filter: blur(10px);
        }
        .qris-header-title { font-size: 22px; font-weight: 800; margin-bottom: 6px; }
        .qris-header-sub { font-size: 14px; color: rgba(255,255,255,0.85); }
        
        .qris-body { padding: 32px 24px; }
        .qris-merchant {
            display: flex; align-items: center; justify-content: center;
            gap: 10px; margin-bottom: 24px;
        }
        .qris-merchant-logo {
            width: 36px; height: 36px;
            background: var(--primary);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            color: white; font-size: 18px;
        }
        .qris-merchant-name { font-size: 15px; font-weight: 700; color: #1C1C1E; }
        
        .qris-code-box {
            background: #FAFAFA;
            border: 2px solid #F0F0F0;
            border-radius: 20px;
            padding: 28px;
            margin-bottom: 24px;
            display: flex; flex-direction: column; align-items: center;
        }
        #qrcode-canvas {
            padding: 16px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        .qris-amount {
            font-size: 28px; font-weight: 800; color: var(--primary);
        }
        .qris-amount-label {
            font-size: 13px; color: #8E8E93; margin-top: 6px;
        }
        
        .qris-timer {
            background: #FFF5F0;
            border: 1.5px solid #FFD4C2;
            border-radius: 14px;
            padding: 16px 24px;
            margin-bottom: 24px;
            display: flex; align-items: center; justify-content: center; gap: 10px;
            font-size: 15px; font-weight: 700; color: var(--primary);
        }
        .qris-timer i { font-size: 20px; }
        .qris-timer.expired {
            background: #FFF5F5;
            border-color: #FFD1D1;
            color: var(--red);
        }
        
        .qris-actions { padding: 0 24px 28px; }
        .btn-confirm-paid {
            background: var(--green); color: var(--white);
            border: none; width: 100%; padding: 18px;
            border-radius: 14px;
            font-size: 16px; font-weight: 800;
            cursor: pointer; transition: 0.2s;
            display: flex; align-items: center; justify-content: center; gap: 10px;
            box-shadow: 0 4px 14px rgba(52,199,89,0.3);
        }
        .btn-confirm-paid:hover { background: #28A745; transform: translateY(-1px); }
        .btn-cancel-modal {
            background: none; border: none;
            color: #8E8E93; font-size: 14px; cursor: pointer;
            margin-top: 16px; display: block; width: 100%;
            font-family: inherit; padding: 12px;
            transition: 0.2s; font-weight: 600;
        }
        .btn-cancel-modal:hover { color: var(--red); }

        /* TRANSFER MODAL */
        .va-info-box {
            background: #F8F9FA; border: 1px solid var(--border-color);
            border-radius: 14px; padding: 24px; margin-bottom: 24px; text-align: left;
        }
        .va-row {
            display: flex; justify-content: space-between; align-items: center;
            padding: 12px 0; border-bottom: 1px solid #EEEEEE; font-size: 14px;
        }
        .va-row:last-child { border-bottom: none; }
        .tr-label { color: #636366; font-weight: 600; }
        .tr-value { color: #1C1C1E; font-weight: 800; }
        .tr-value.highlight { color: var(--primary); font-size: 20px; }
        .copy-btn {
            background: #F2F2F7; border: none; border-radius: 8px;
            padding: 6px 12px; font-size: 12px; font-weight: 700;
            cursor: pointer; color: #636366; transition: 0.2s;
        }
        .copy-btn:hover { background: var(--primary); color: white; }
        .btn-sudah-va {
            background: var(--primary); color: var(--white);
            border: none; width: 100%; padding: 16px;
            border-radius: 12px; font-size: 15px; font-weight: 800;
            cursor: pointer; transition: 0.2s;
        }
        .btn-sudah-va:hover { background: var(--primary-hover); }

        /* WAITING MODAL */
        .waiting-box { text-align: center; padding: 24px 0; }
        .waiting-spinner {
            width: 72px; height: 72px; border-radius: 50%;
            border: 4px solid #E5E5EA; border-top-color: var(--primary);
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .waiting-text { font-size: 18px; font-weight: 700; color: #1C1C1E; margin-bottom: 10px; }
        .waiting-sub { font-size: 14px; color: #8E8E93; }

        /* SUCCESS MODAL */
        .success-icon-big {
            width: 80px; height: 80px; border-radius: 50%;
            background: var(--green-lt); border: 2px solid var(--green);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 24px; font-size: 36px; color: var(--green);
        }

        /* ---- FOOTER — SAMA DENGAN VIEW_CUSTOMER ---- */
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

        .swal-toast { border-radius: 12px !important; font-family: 'Plus Jakarta Sans', sans-serif !important; }

        /* RESPONSIVE */
        @media(max-width: 1024px) {
            .hero { flex-direction: column; min-height: auto; padding: 40px 40px; }
            .hero-left { max-width: 100%; }
            .hero-title { font-size: 36px; }
            .search-widget { width: 100%; max-width: 440px; }
            .features-row { grid-template-columns: repeat(2, 1fr); padding: 40px 40px; }
            .main-container { padding: 40px 40px; }
            .lapangan-grid { grid-template-columns: 1fr; }
            .jadwal-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media(max-width: 768px) {
            nav { padding: 0 20px; }
            .hero { padding: 40px 20px; }
            .hero-title { font-size: 28px; }
            .features-row { grid-template-columns: 1fr; padding: 30px 20px; }
            .main-container { padding: 30px 20px; }
            .jadwal-grid { grid-template-columns: 1fr; }
            footer { padding: 40px 20px 20px; }
            .footer-grid { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body>

<!-- NAVBAR — SAMA DENGAN VIEW_CUSTOMER -->
<nav>
    <a href="../dashboard/view_customer.php" class="nav-logo">
        <img src="../asset/image/logo2.png" alt="HoopBall">
    </a>
    <div class="nav-links">
        <a href="../dashboard/view_customer.php">Beranda</a>
        <a href="booking_customer.php" class="active">Booking</a>
        <a href="#">Lapangan</a>
        <a href="#">Member</a>
        <a href="#">Pembelian</a>
        <a href="#">Tentang</a>
        <a href="#">Kontak</a>
    </div>
    <div class="nav-user-container">
        <div class="nav-user">
            <?php if (!empty($photo_profile) && file_exists($photo_profile)): ?>
                <img src="<?= htmlspecialchars($photo_profile) ?>" alt="Avatar" class="user-avatar">
            <?php else: ?>
                <i class="fa-solid fa-circle-user user-icon"></i>
            <?php endif; ?>
            <span><?= htmlspecialchars($nama_customer) ?></span>
            <i class="fa-solid fa-chevron-down arrow"></i>
        </div>
        <div class="dropdown-menu">
            <div class="user-info-header">
                <span class="u-name"><?= htmlspecialchars($nama_customer) ?></span>
                <span class="u-role">Customer</span>
            </div>
            <a href="../profile/profile_customer.php"><i class="fa-solid fa-user"></i> Profil Saya</a>
            <a href="booking_customer.php"><i class="fa-solid fa-calendar-check"></i> Riwayat Booking</a>
            <a href="#"><i class="fa-solid fa-gear"></i> Pengaturan</a>
            <div class="dropdown-divider"></div>
            <a href="../login/logout.php" class="logout"><i class="fa-solid fa-right-from-bracket"></i> Keluar</a>
        </div>
    </div>
</nav>

<!-- HERO SECTION — SAMA DENGAN VIEW_CUSTOMER -->
<header class="hero">
    <div class="hero-left">
        <?php if ($has_member): ?>
        <div class="member-badge-hero">
            <i class="fa-solid fa-crown"></i> Member <?= htmlspecialchars($member_tipe) ?> Aktif
        </div>
        <?php endif; ?>
        <h1 class="hero-title">Booking Lapangan<br>Basket Jadi<br><span>Lebih Mudah</span></h1>
        <p class="hero-desc">Pilih lapangan, jadwal, dan metode pembayaran untuk melengkapi reservasi Anda. Cek ketersediaan real-time dan nikmati promo khusus member.</p>
        <div class="hero-btns">
            <a href="#booking-form" class="btn-primary">
                <i class="fa-solid fa-calendar-check"></i> Booking Sekarang
            </a>
            <a href="../dashboard/view_customer.php" class="btn-outline">
                <i class="fa-solid fa-house"></i> Kembali ke Beranda
            </a>
        </div>
    </div>

    <!-- Search Widget — SAMA DENGAN VIEW_CUSTOMER -->
    <div class="search-widget">
        <h3 class="widget-title"><i class="fa-solid fa-magnifying-glass"></i> Cari & Booking</h3>
        <div class="form-group">
            <label class="form-label">Pilih Lapangan</label>
            <div class="input-wrapper">
                <i class="fa-solid fa-basketball"></i>
                <select class="form-select" id="hero-lapangan" onchange="pilihLapanganHero(this)">
                    <option value="">Semua Lapangan</option>
                    <?php foreach ($lapangan_list as $lap): ?>
                    <option value="<?= $lap['ID_Lapangan'] ?>" data-harga="<?= $lap['Harga_Sewa'] ?>" data-nama="<?= htmlspecialchars($lap['Nama_Lapangan']) ?>">
                        <?= htmlspecialchars($lap['Nama_Lapangan']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-row-2">
            <div class="form-group">
                <label class="form-label">Tanggal</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-calendar-days"></i>
                    <input type="date" class="form-input" id="hero-tanggal" value="<?= date('Y-m-d') ?>">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Jam</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-clock"></i>
                    <input type="time" class="form-input" id="hero-jam" value="18:00">
                </div>
            </div>
        </div>
        <button class="btn-widget" onclick="lanjutkanBooking()">
            <i class="fa-solid fa-calendar-check"></i> Lanjutkan Booking
        </button>
    </div>
</header>

<!-- FITUR ROW — SAMA DENGAN VIEW_CUSTOMER -->
<section class="features-row">
    <div class="feature-card">
        <div class="feature-icon-circle"><i class="fa-solid fa-calendar-check"></i></div>
        <div class="feature-text">
            <h4>Booking Online</h4>
            <p>Pesan lapangan kapan saja di mana saja dengan mudah dan cepat.</p>
        </div>
    </div>
    <div class="feature-card">
        <div class="feature-icon-circle"><i class="fa-solid fa-clock"></i></div>
        <div class="feature-text">
            <h4>Jadwal Real-Time</h4>
            <p>Cek ketersediaan lapangan secara real-time dan akurat setiap saat.</p>
        </div>
    </div>
    <div class="feature-card">
        <div class="feature-icon-circle"><i class="fa-solid fa-tags"></i></div>
        <div class="feature-text">
            <h4>Promo Member</h4>
            <p>Dapatkan promo menarik dan diskon eksklusif untuk member setia.</p>
        </div>
    </div>
    <div class="feature-card">
        <div class="feature-icon-circle"><i class="fa-solid fa-award"></i></div>
        <div class="feature-text">
            <h4>Lapangan Berkualitas</h4>
            <p>Lapangan terawat, nyaman, dan standar profesional untuk pengalaman terbaik.</p>
        </div>
    </div>
</section>

<!-- MAIN CONTENT — SAMA DENGAN VIEW_CUSTOMER -->
<main class="main-container" id="booking-form">

    <!-- BOOKING FORM -->
    <div class="booking-card">
        <div class="booking-card-title">
            <i class="fa-solid fa-basketball"></i>
            Form Booking Lapangan
        </div>

        <!-- STEP INDICATOR -->
        <div class="step-indicator">
            <div class="step-item">
                <div class="step-circle active" id="step-circle-1">1</div>
                <div class="step-label active" id="step-label-1">Pilih Lapangan</div>
            </div>
            <div class="step-item">
                <div class="step-circle" id="step-circle-2">2</div>
                <div class="step-label" id="step-label-2">Pilih Jadwal</div>
            </div>
            <div class="step-item">
                <div class="step-circle" id="step-circle-3">3</div>
                <div class="step-label" id="step-label-3">Promo</div>
            </div>
            <div class="step-item">
                <div class="step-circle" id="step-circle-4">4</div>
                <div class="step-label" id="step-label-4">Pembayaran</div>
            </div>
        </div>

        <!-- STEP 1: PILIH LAPANGAN -->
        <div class="form-section visible" id="step-1">
            <p style="font-size: 14px; color: #636366; margin-bottom: 20px;">Pilih lapangan basket yang ingin Anda sewa.</p>
            <div class="lapangan-grid">
                <?php foreach ($lapangan_list as $lap): ?>
                <label class="lapangan-option" onclick="pilihLapangan(<?= $lap['ID_Lapangan'] ?>, '<?= htmlspecialchars($lap['Nama_Lapangan']) ?>', <?= $lap['Harga_Sewa'] ?>)" data-id="<?= $lap['ID_Lapangan'] ?>">
                    <input type="radio" name="lapangan" value="<?= $lap['ID_Lapangan'] ?>">
                    <span class="lap-check" id="check-lap-<?= $lap['ID_Lapangan'] ?>"></span>
                    <div class="lap-name"><?= htmlspecialchars($lap['Nama_Lapangan']) ?></div>
                    <div class="lap-price"><?= rupiahFormat($lap['Harga_Sewa']) ?> / jam</div>
                </label>
                <?php endforeach; ?>
                <?php if (empty($lapangan_list)): ?>
                <div style="grid-column: span 2; text-align: center; padding: 40px; color: #8E8E93;">
                    <i class="fa-solid fa-circle-info" style="font-size: 32px; margin-bottom: 12px; color: var(--primary);"></i>
                    <p>Tidak ada lapangan aktif saat ini.</p>
                </div>
                <?php endif; ?>
            </div>
            <div class="btn-row" style="margin-top: 32px;">
                <button class="btn-next" id="btn-next-1" onclick="goStep(2)" disabled>
                    Pilih Jadwal <i class="fa-solid fa-arrow-right"></i>
                </button>
            </div>
        </div>

        <!-- STEP 2: PILIH JADWAL -->
        <div class="form-section" id="step-2">
            <p style="font-size: 14px; color: #636366; margin-bottom: 20px;">Pilih jadwal yang tersedia untuk lapangan yang dipilih.</p>
            <div class="jadwal-grid" id="jadwal-grid-container">
                <div class="jadwal-empty">
                    <i class="fa-regular fa-calendar-xmark"></i>
                    Memuat jadwal...
                </div>
            </div>
            <div class="btn-row">
                <button class="btn-back" onclick="goStep(1)"><i class="fa-solid fa-arrow-left"></i> Kembali</button>
                <button class="btn-next" id="btn-next-2" onclick="goStep(3)" disabled>
                    <?php if (!$has_member): ?>Pilih Promo<?php else: ?>Pilih Pembayaran<?php endif; ?> <i class="fa-solid fa-arrow-right"></i>
                </button>
            </div>
        </div>

        <!-- STEP 3: PROMO / LANGSUNG LANJUT JIKA MEMBER -->
        <div class="form-section" id="step-3">
            <?php if ($has_member): ?>
                <div style="text-align: center; padding: 40px 0;">
                    <div style="background: var(--green-lt); border: 1px solid var(--green); border-radius: 16px; padding: 28px; margin-bottom: 24px;">
                        <i class="fa-solid fa-crown" style="color: var(--green); font-size: 32px; margin-bottom: 14px; display: block;"></i>
                        <div style="font-weight: 800; color: var(--green); font-size: 16px;">Potongan Member <?= htmlspecialchars($member_tipe) ?> Aktif!</div>
                        <div style="font-size: 14px; color: #636366; margin-top: 8px;">Anda mendapatkan potongan <strong><?= rupiahFormat($member_potong) ?></strong> secara otomatis.</div>
                    </div>
                    <p style="font-size: 14px; color: #8E8E93;">Promo tidak dapat digunakan bersamaan dengan keuntungan member.</p>
                </div>
            <?php else: ?>
                <p style="font-size: 14px; color: #636366; margin-bottom: 20px;">Gunakan promo untuk mendapatkan potongan harga (opsional).</p>
                <?php if (!empty($promo_list)): ?>
                    <label class="promo-option" id="promo-none" onclick="pilihPromo(0, 0, 'Tanpa Promo')">
                        <input type="radio" name="promo" value="0" checked>
                        <div class="promo-detail">
                            <div class="promo-name">Tanpa Promo</div>
                            <div style="font-size: 13px; color: #8E8E93;">Bayar dengan harga normal</div>
                        </div>
                    </label>
                    <?php foreach ($promo_list as $pr): ?>
                    <label class="promo-option" id="promo-<?= $pr['ID_Promo'] ?>" onclick="pilihPromo(<?= $pr['ID_Promo'] ?>, <?= $pr['Diskon'] ?>, '<?= htmlspecialchars($pr['Nama_Promo']) ?>')">
                        <input type="radio" name="promo" value="<?= $pr['ID_Promo'] ?>">
                        <div class="promo-detail">
                            <div class="promo-name"><?= htmlspecialchars($pr['Nama_Promo']) ?></div>
                            <div class="promo-disc"><i class="fa-solid fa-tags"></i> Hemat <?= rupiahFormat($pr['Diskon']) ?></div>
                        </div>
                        <span style="font-size: 20px; font-weight: 800; color: var(--primary);">-<?= rupiahFormat($pr['Diskon']) ?></span>
                    </label>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px; background: #F8F9FA; border-radius: 16px; color: #8E8E93;">
                        <i class="fa-solid fa-tag" style="font-size: 32px; margin-bottom: 12px; display: block;"></i>
                        Tidak ada promo aktif saat ini. Harga normal akan diterapkan.
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            <div class="btn-row">
                <button class="btn-back" onclick="goStep(2)"><i class="fa-solid fa-arrow-left"></i> Kembali</button>
                <button class="btn-next" onclick="goStep(4)">
                    Pilih Pembayaran <i class="fa-solid fa-arrow-right"></i>
                </button>
            </div>
        </div>

        <!-- STEP 4: METODE PEMBAYARAN -->
        <div class="form-section" id="step-4">
            <p style="font-size: 14px; color: #636366; margin-bottom: 20px;">Pilih metode pembayaran online untuk menyelesaikan booking.</p>
            <div class="metode-grid">
                <label class="metode-card" id="card-qris" onclick="pilihMetode('QRIS', this)">
                    <input type="radio" name="metode" value="QRIS">
                    <span class="metode-icon">📱</span>
                    <div class="metode-name">QRIS</div>
                    <div class="metode-desc">Scan & bayar langsung</div>
                </label>
                <label class="metode-card" id="card-va" onclick="pilihMetode('Virtual Account', this)">
                    <input type="radio" name="metode" value="Virtual Account">
                    <span class="metode-icon">🏦</span>
                    <div class="metode-name">Virtual Account</div>
                    <div class="metode-desc">Bayar via Virtual Account</div>
                </label>
            </div>

            <!-- RINGKASAN SEBELUM BAYAR -->
            <div style="background: #F8F9FA; border-radius: 16px; padding: 24px; margin-top: 24px;" id="final-summary-box">
                <div style="font-size: 14px; font-weight: 700; color: #1C1C1E; margin-bottom: 16px;">Ringkasan Pesanan</div>
                <div style="display:flex; justify-content: space-between; font-size: 14px; margin-bottom: 10px;">
                    <span style="color: #636366;">Lapangan</span><span id="fs-lapangan" style="font-weight: 700;">-</span>
                </div>
                <div style="display:flex; justify-content: space-between; font-size: 14px; margin-bottom: 10px;">
                    <span style="color: #636366;">Jadwal</span><span id="fs-jadwal" style="font-weight: 700;">-</span>
                </div>
                <div style="display:flex; justify-content: space-between; font-size: 14px; margin-bottom: 10px;">
                    <span style="color: #636366;">Harga Sewa</span><span id="fs-harga" style="font-weight: 700;">-</span>
                </div>
                <div style="display:flex; justify-content: space-between; font-size: 14px; margin-bottom: 10px;" id="fs-potongan-row">
                    <span style="color: #636366;" id="fs-potongan-label">Potongan</span>
                    <span id="fs-potongan" style="font-weight: 700; color: var(--green);">-</span>
                </div>
                <div style="height: 1px; background: #E5E5EA; margin: 16px 0;"></div>
                <div style="display:flex; justify-content: space-between; font-size: 18px;">
                    <span style="font-weight: 800; color: #1C1C1E;">Total Bayar</span>
                    <span id="fs-total" style="font-weight: 800; color: var(--primary);">-</span>
                </div>
            </div>

            <div class="btn-row">
                <button class="btn-back" onclick="goStep(3)"><i class="fa-solid fa-arrow-left"></i> Kembali</button>
                <button class="btn-next" id="btn-bayar" onclick="prosesBooking()" disabled>
                    <i class="fa-solid fa-lock"></i> Bayar Sekarang
                </button>
            </div>
        </div>
    </div>

</main>

<!-- FOOTER — SAMA DENGAN VIEW_CUSTOMER -->
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
                <li><a href="../dashboard/view_customer.php">Beranda</a></li>
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


<!-- MODAL: QRIS PAYMENT -->
<div class="modal-overlay" id="modal-qris">
    <div class="modal-box">
        <div class="qris-header">
            <div class="qris-header-icon">📱</div>
            <div class="qris-header-title">Pembayaran QRIS</div>
            <div class="qris-header-sub">Scan kode QR untuk menyelesaikan pembayaran</div>
        </div>
        <div class="qris-body">
            <div class="qris-merchant">
                <div class="qris-merchant-logo"><i class="fa-solid fa-basketball"></i></div>
                <div class="qris-merchant-name">HoopBall Indonesia</div>
            </div>
            <div class="qris-code-box">
                <div id="qrcode-canvas"></div>
                <div class="qris-amount" id="qris-amount-display">Rp 0</div>
                <div class="qris-amount-label">Total Pembayaran</div>
            </div>
            <div class="qris-timer" id="qris-timer-box">
                <i class="fa-solid fa-clock"></i>
                <span>Batas waktu: <strong id="qris-timer-text">15:00</strong></span>
            </div>
        </div>
        <div class="qris-actions">
            <button class="btn-confirm-paid" onclick="konfirmasiSudahBayarQRIS()">
                <i class="fa-solid fa-check-circle"></i> Saya Sudah Bayar
            </button>
            <button class="btn-cancel-modal" onclick="batalBooking()">Batalkan Booking</button>
        </div>
    </div>
</div>

<!-- MODAL: TRANSFER BANK -->
<div class="modal-overlay" id="modal-va">
    <div class="modal-box">
        <div class="qris-header">
            <div class="qris-header-icon">🏦</div>
            <div class="qris-header-title">Virtual Account</div>
            <div class="qris-header-sub">Lakukan pembayaran ke Virtual Account berikut</div>
        </div>
        <div class="qris-body">
            <div class="va-info-box">
                <div class="va-row">
                    <span class="tr-label">Bank</span>
                    <span class="tr-value">BCA</span>
                </div>
                <div class="va-row">
                    <span class="tr-label">Nomor VA</span>
                    <div style="display:flex; align-items:center; gap:8px;">
                        <span class="tr-value" id="rek-number">1234567890</span>
                        <button class="copy-btn" onclick="copyToClipboard('1234567890')">Salin</button>
                    </div>
                </div>
                <div class="va-row">
                    <span class="tr-label">Atas Nama</span>
                    <span class="tr-value">HoopBall Indonesia</span>
                </div>
                <div class="va-row">
                    <span class="tr-label">Total Bayar</span>
                    <span class="tr-value highlight" id="va-amount-display">Rp 0</span>
                </div>
            </div>
            <div style="background: #FFF0E6; border-radius: 12px; padding: 16px; margin-bottom: 20px; font-size: 13px; color: #D97706; display: flex; gap: 10px; align-items: flex-start;">
                <i class="fa-solid fa-triangle-exclamation" style="margin-top: 2px;"></i>
                <span>Bayar sesuai nominal <strong>tepat</strong> untuk mempercepat konfirmasi. Booking akan dikonfirmasi oleh tim kami dalam 1×24 jam setelah pembayaran diterima.</span>
            </div>
            <button class="btn-sudah-va" onclick="konfirmasiSudahVA()">
                <i class="fa-solid fa-credit-card"></i> Saya Sudah Bayar — Tunggu Konfirmasi
            </button>
            <button class="btn-cancel-modal" onclick="batalBooking()">Batalkan Booking</button>
        </div>
    </div>
</div>

<!-- MODAL: WAITING KONFIRMASI -->
<div class="modal-overlay" id="modal-waiting">
    <div class="modal-box">
        <div class="waiting-box">
            <div class="waiting-spinner"></div>
            <div class="waiting-text">Menunggu Konfirmasi Pembayaran</div>
            <div class="waiting-sub" id="waiting-sub-text">Kami sedang memverifikasi pembayaran Anda.<br>Harap tunggu atau hubungi admin HoopBall.</div>

            <div style="background: #F8F9FA; border-radius: 16px; padding: 20px; margin-top: 24px; font-size: 14px; text-align: left;">
                <div style="font-weight: 700; color: #1C1C1E; margin-bottom: 10px;">Detail Booking</div>
                <div style="color: #636366; margin-bottom: 6px;">Lapangan: <strong id="w-lapangan" style="color: #1C1C1E;">-</strong></div>
                <div style="color: #636366; margin-bottom: 6px;">Jadwal: <strong id="w-jadwal" style="color: #1C1C1E;">-</strong></div>
                <div style="color: #636366;">Total: <strong id="w-total" style="color: var(--primary);">-</strong></div>
            </div>

            <div style="margin-top: 24px; padding: 14px; background: var(--yellow-lt); border-radius: 12px; font-size: 13px; color: #D97706;">
                <i class="fa-solid fa-info-circle"></i>
                Status booking Anda saat ini: <strong>Menunggu Konfirmasi Karyawan</strong>
            </div>

            <button onclick="selesaiMenunggu()" style="margin-top: 24px; background: var(--primary); color: white; border: none; padding: 14px 28px; border-radius: 12px; font-weight: 700; font-size: 15px; cursor: pointer; width: 100%;">
                <i class="fa-solid fa-home"></i> Kembali ke Beranda
            </button>
        </div>
    </div>
</div>

<!-- MODAL: BOOKING SUCCESS -->
<div class="modal-overlay" id="modal-success">
    <div class="modal-box">
        <div class="success-icon-big"><i class="fa-solid fa-check"></i></div>
        <div style="font-size: 22px; font-weight: 800; color: #1C1C1E; margin-bottom: 8px;">Booking Terkonfirmasi!</div>
        <div style="font-size: 14px; color: #636366; margin-bottom: 28px;">Pembayaran Anda telah dikonfirmasi. Lapangan sudah siap untuk Anda!</div>

        <div style="background: #F8F9FA; border-radius: 16px; padding: 24px; text-align: left; margin-bottom: 28px;">
            <div style="display:flex; justify-content:space-between; font-size:14px; margin-bottom: 10px;">
                <span style="color: #636366;">Lapangan</span><strong id="suc-lapangan">-</strong>
            </div>
            <div style="display:flex; justify-content:space-between; font-size:14px; margin-bottom: 10px;">
                <span style="color: #636366;">Jadwal</span><strong id="suc-jadwal">-</strong>
            </div>
            <div style="display:flex; justify-content:space-between; font-size:14px;">
                <span style="color: #636366;">Total Bayar</span><strong style="color: var(--primary);" id="suc-total">-</strong>
            </div>
        </div>

        <button onclick="window.location.href='booking_customer.php'" style="background: var(--primary); color: white; border: none; padding: 16px; width: 100%; border-radius: 12px; font-weight: 800; font-size: 15px; cursor: pointer;">
            Lihat Riwayat Booking
        </button>
    </div>
</div>


<script>
// ============================================================================
// STATE BOOKING
// ============================================================================
let state = {
    lapanganId: 0,
    lapanganNama: '',
    lapanganHarga: 0,
    jadwalId: 0,
    jadwalDisplay: '',
    jadwalTanggal: '',
    jadwalJam: '',
    promoId: 0,
    promoDiskon: 0,
    promoPilihan: 'Tanpa Promo',
    metode: '',
    totalBayar: 0,
    idBooking: 0,
};

const hasMember   = <?= $has_member ? 'true' : 'false' ?>;
const memberTipe  = "<?= htmlspecialchars($member_tipe) ?>";
const memberPotong = <?= $member_potong ?>;

// ============================================================================
// HERO INTERACTION
// ============================================================================
function pilihLapanganHero(select) {
    const option = select.options[select.selectedIndex];
    if (option.value) {
        const id = parseInt(option.value);
        const nama = option.dataset.nama;
        const harga = parseFloat(option.dataset.harga);
        
        document.getElementById('booking-form').scrollIntoView({ behavior: 'smooth' });
        
        setTimeout(() => {
            const lapOption = document.querySelector('.lapangan-option[data-id="' + id + '"]');
            if (lapOption) {
                lapOption.click();
            }
        }, 500);
    }
}

function lanjutkanBooking() {
    const select = document.getElementById('hero-lapangan');
    if (!select.value) {
        showToast('error', 'Pilih lapangan terlebih dahulu.');
        return;
    }
    document.getElementById('booking-form').scrollIntoView({ behavior: 'smooth' });
}

// ============================================================================
// STEP NAVIGATION
// ============================================================================
function goStep(step) {
    if (step === 2 && !state.lapanganId) {
        showToast('error', 'Pilih lapangan terlebih dahulu.');
        return;
    }
    if (step === 3 && !state.jadwalId) {
        showToast('error', 'Pilih jadwal terlebih dahulu.');
        return;
    }
    if (step === 4) {
        hitungTotal();
        updateFinalSummary();
    }

    [1, 2, 3, 4].forEach(s => {
        document.getElementById('step-' + s).classList.remove('visible');
        const circle = document.getElementById('step-circle-' + s);
        const label  = document.getElementById('step-label-' + s);
        if (s < step) {
            circle.className = 'step-circle done';
            circle.innerHTML = '<i class="fa-solid fa-check" style="font-size:12px;"></i>';
            label.className  = 'step-label done';
        } else if (s === step) {
            circle.className = 'step-circle active';
            circle.textContent = s;
            label.className  = 'step-label active';
        } else {
            circle.className = 'step-circle';
            circle.textContent = s;
            label.className  = 'step-label';
        }
    });
    document.getElementById('step-' + step).classList.add('visible');
}

// ============================================================================
// STEP 1: PILIH LAPANGAN
// ============================================================================
function pilihLapangan(id, nama, harga) {
    state.lapanganId    = id;
    state.lapanganNama  = nama;
    state.lapanganHarga = parseFloat(harga);
    state.jadwalId      = 0;
    state.jadwalDisplay = '';

    document.querySelectorAll('.lapangan-option').forEach(el => el.classList.remove('selected'));
    event.currentTarget.classList.add('selected');

    document.getElementById('btn-next-1').disabled = false;
    updateSummary();

    loadJadwal(id);
}

// ============================================================================
// STEP 2: JADWAL PER LAPANGAN (AJAX)
// ============================================================================
function loadJadwal(idLapangan) {
    const grid = document.getElementById('jadwal-grid-container');
    grid.innerHTML = '<div class="jadwal-empty"><i class="fa-solid fa-spinner fa-spin"></i><br>Memuat jadwal...</div>';

    fetch('booking_customer.php?ajax_jadwal=1&id_lapangan=' + idLapangan)
        .then(r => r.json())
        .then(data => {
            if (!data.length) {
                grid.innerHTML = '<div class="jadwal-empty"><i class="fa-regular fa-calendar-xmark"></i>Tidak ada jadwal tersedia untuk lapangan ini.</div>';
                return;
            }
            let html = '';
            data.forEach(j => {
                const tglFormatted = formatTanggalJS(j.tanggal);
                html += `
                    <div class="jadwal-slot" id="slot-${j.id}"
                         onclick="pilihJadwal(${j.id}, '${j.tanggal}', '${j.jam_mulai}', '${j.jam_selesai}', '${tglFormatted}', this)">
                        <div class="slot-tanggal">${tglFormatted}</div>
                        <div class="slot-jam">${j.jam_mulai} – ${j.jam_selesai}</div>
                        <div class="slot-status"><i class="fa-solid fa-circle" style="font-size:7px;"></i> Tersedia</div>
                    </div>`;
            });
            grid.innerHTML = html;
        })
        .catch(() => {
            grid.innerHTML = '<div class="jadwal-empty"><i class="fa-solid fa-circle-exclamation"></i>Gagal memuat jadwal. Coba refresh halaman.</div>';
        });
}

function pilihJadwal(id, tanggal, jamMulai, jamSelesai, tanggalDisplay, el) {
    state.jadwalId      = id;
    state.jadwalTanggal = tanggal;
    state.jadwalJam     = jamMulai + ' – ' + jamSelesai;
    state.jadwalDisplay = tanggalDisplay + ', ' + jamMulai + ' – ' + jamSelesai;

    document.querySelectorAll('.jadwal-slot').forEach(s => s.classList.remove('selected'));
    el.classList.add('selected');

    document.getElementById('btn-next-2').disabled = false;
    updateSummary();
}

// ============================================================================
// STEP 3: PROMO
// ============================================================================
function pilihPromo(id, diskon, nama) {
    state.promoId      = id;
    state.promoDiskon  = parseFloat(diskon);
    state.promoPilihan = nama;

    document.querySelectorAll('.promo-option').forEach(el => el.classList.remove('selected'));
    const el = document.getElementById(id === 0 ? 'promo-none' : 'promo-' + id);
    if (el) el.classList.add('selected');

    hitungTotal();
    updateSummary();
}

<?php if (!$has_member): ?>
pilihPromo(0, 0, 'Tanpa Promo');
<?php endif; ?>

// ============================================================================
// STEP 4: METODE PEMBAYARAN
// ============================================================================
function pilihMetode(metode, el) {
    state.metode = metode;
    document.querySelectorAll('.metode-card').forEach(c => c.classList.remove('selected'));
    if (el) {
        el.classList.add('selected');
    } else {
        document.getElementById('card-' + (metode === 'QRIS' ? 'qris' : 'va')).classList.add('selected');
    }
    document.getElementById('btn-bayar').disabled = false;
}

function updateFinalSummary() {
    document.getElementById('fs-lapangan').textContent = state.lapanganNama || '-';
    document.getElementById('fs-jadwal').textContent   = state.jadwalDisplay || '-';
    document.getElementById('fs-harga').textContent    = formatRupiah(state.lapanganHarga);
    document.getElementById('fs-total').textContent    = formatRupiah(state.totalBayar);

    const potRow = document.getElementById('fs-potongan-row');
    if ((hasMember && memberPotong > 0) || state.promoDiskon > 0) {
        potRow.style.display = 'flex';
        const potongan = hasMember ? memberPotong : state.promoDiskon;
        const label    = hasMember ? 'Potongan Member ' + memberTipe : state.promoPilihan;
        document.getElementById('fs-potongan-label').textContent = label;
        document.getElementById('fs-potongan').textContent = '- ' + formatRupiah(potongan);
    } else {
        potRow.style.display = 'none';
    }
}

// ============================================================================
// HITUNG TOTAL
// ============================================================================
function hitungTotal() {
    let total = state.lapanganHarga;
    if (hasMember && memberPotong > 0) {
        total -= memberPotong;
    } else if (!hasMember && state.promoDiskon > 0) {
        total -= state.promoDiskon;
    }
    state.totalBayar = Math.max(0, total);
    return state.totalBayar;
}

// ============================================================================
// UPDATE SIDEBAR SUMMARY
// ============================================================================
function updateSummary() {
    hitungTotal();
    const box = document.getElementById('summary-content');

    if (!state.lapanganId) {
        box.innerHTML = `<div class="summary-empty"><i class="fa-regular fa-calendar-plus"></i><p style="font-size:16px;font-weight:700;color:#636366;margin-bottom:8px;">Belum ada pilihan</p><p style="font-size:13px;color:#AEAEB2;">Pilih lapangan dan jadwal terlebih dahulu</p></div>`;
        return;
    }

    let potonganHTML = '';
    if (hasMember && memberPotong > 0) {
        potonganHTML = `
            <div class="summary-row">
                <span class="s-label">Potongan Member ${memberTipe}</span>
                <span class="s-value discount">- ${formatRupiah(memberPotong)}</span>
            </div>`;
    } else if (!hasMember && state.promoDiskon > 0) {
        potonganHTML = `
            <div class="summary-row">
                <span class="s-label">${state.promoPilihan}</span>
                <span class="s-value discount">- ${formatRupiah(state.promoDiskon)}</span>
            </div>`;
    }

    box.innerHTML = `
        <div class="summary-row">
            <span class="s-label">Lapangan</span>
            <span class="s-value">${state.lapanganNama}</span>
        </div>
        ${state.jadwalDisplay ? `<div class="summary-row"><span class="s-label">Jadwal</span><span class="s-value">${state.jadwalDisplay}</span></div>` : ''}
        <div class="summary-row">
            <span class="s-label">Harga Sewa</span>
            <span class="s-value">${formatRupiah(state.lapanganHarga)}</span>
        </div>
        ${potonganHTML}
        <div class="summary-divider"></div>
        <div class="summary-row" style="padding-top:16px;">
            <span class="s-label" style="font-weight:800; font-size:16px; color:#1C1C1E;">Total Bayar</span>
            <span class="s-value s-total">${formatRupiah(state.totalBayar)}</span>
        </div>`;
}

// ============================================================================
// PROSES BOOKING → AJAX POST
// ============================================================================
function prosesBooking() {
    if (!state.lapanganId || !state.jadwalId || !state.metode) {
        showToast('error', 'Lengkapi semua data terlebih dahulu.');
        return;
    }

    hitungTotal();

    const formData = new FormData();
    formData.append('ajax_booking', '1');
    formData.append('id_jadwal', state.jadwalId);
    formData.append('id_promo', hasMember ? 0 : state.promoId);
    formData.append('metode', state.metode);
    formData.append('total_bayar', state.totalBayar);

    fetch('booking_customer.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                state.idBooking = res.id_booking;
                if (state.metode === 'QRIS') {
                    tampilModalQRIS();
                } else {
                    tampilModalVA();
                }
            } else {
                showToast('error', res.msg || 'Gagal membuat booking.');
            }
        })
        .catch(() => showToast('error', 'Terjadi kesalahan. Coba lagi.'));
}

// ============================================================================
// MODAL QRIS
// ============================================================================
let qrisTimerInterval = null;

function tampilModalQRIS() {
    const qrContainer = document.getElementById('qrcode-canvas');
    qrContainer.innerHTML = '';
    const qrData = `hoopball://pay?booking=${state.idBooking}&amount=${state.totalBayar}&merchant=HoopBall`;
    new QRCode(qrContainer, {
        text: qrData,
        width: 200,
        height: 200,
        colorDark: '#0B0B0C',
        colorLight: '#FFFFFF',
        correctLevel: QRCode.CorrectLevel.M
    });
    document.getElementById('qris-amount-display').textContent = formatRupiah(state.totalBayar);
    document.getElementById('modal-qris').classList.add('show');

    const timerBox = document.getElementById('qris-timer-box');
    timerBox.classList.remove('expired');

    let seconds = 15 * 60;
    updateQrisTimer(seconds);
    qrisTimerInterval = setInterval(() => {
        seconds--;
        updateQrisTimer(seconds);
        if (seconds <= 0) {
            clearInterval(qrisTimerInterval);
            const tb = document.getElementById('qris-timer-box');
            tb.classList.add('expired');
            document.getElementById('qris-timer-text').textContent = 'Waktu habis';
        }
    }, 1000);
}

function updateQrisTimer(s) {
    const m = Math.floor(s / 60).toString().padStart(2, '0');
    const sec = (s % 60).toString().padStart(2, '0');
    document.getElementById('qris-timer-text').textContent = m + ':' + sec;
}

function konfirmasiSudahBayarQRIS() {
    clearInterval(qrisTimerInterval);
    document.getElementById('modal-qris').classList.remove('show');
    tampilModalMenunggu();
}

// ============================================================================
// MODAL TRANSFER BANK
// ============================================================================
function tampilModalVA() {
    document.getElementById('va-amount-display').textContent = formatRupiah(state.totalBayar);
    document.getElementById('modal-va').classList.add('show');
}

function konfirmasiSudahVA() {
    document.getElementById('modal-va').classList.remove('show');
    tampilModalMenunggu();
}

function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showToast('success', 'Nomor rekening disalin!');
    });
}

// ============================================================================
// MODAL MENUNGGU KONFIRMASI KARYAWAN
// ============================================================================
function tampilModalMenunggu() {
    document.getElementById('w-lapangan').textContent = state.lapanganNama;
    document.getElementById('w-jadwal').textContent   = state.jadwalDisplay;
    document.getElementById('w-total').textContent    = formatRupiah(state.totalBayar);

    if (state.metode === 'Virtual Account') {
        document.getElementById('waiting-sub-text').innerHTML =
            'Pembayaran Virtual Account sedang diproses. Karyawan akan mengkonfirmasi setelah pembayaran diterima.<br><small style="color:#AEAEB2;">Estimasi: 1×24 jam</small>';
    } else {
        document.getElementById('waiting-sub-text').innerHTML =
            'Pembayaran QRIS sedang diverifikasi oleh sistem. Karyawan akan mengkonfirmasi segera.<br><small style="color:#AEAEB2;">Estimasi: beberapa menit</small>';
    }
    document.getElementById('modal-waiting').classList.add('show');

    startPollingStatus();
}

let pollingInterval = null;
function startPollingStatus() {
    pollingInterval = setInterval(() => {
        if (!state.idBooking) return;
        fetch('booking_customer.php?ajax_cek_status=1&id_booking=' + state.idBooking)
            .then(r => r.json())
            .then(res => {
                if (res.status === 1) {
                    clearInterval(pollingInterval);
                    document.getElementById('modal-waiting').classList.remove('show');
                    tampilModalSuccess();
                }
            });
    }, 5000);
}

function selesaiMenunggu() {
    clearInterval(pollingInterval);
    document.getElementById('modal-waiting').classList.remove('show');
    window.location.href = 'booking_customer.php';
}

// ============================================================================
// MODAL SUCCESS
// ============================================================================
function tampilModalSuccess() {
    document.getElementById('suc-lapangan').textContent = state.lapanganNama;
    document.getElementById('suc-jadwal').textContent   = state.jadwalDisplay;
    document.getElementById('suc-total').textContent    = formatRupiah(state.totalBayar);
    document.getElementById('modal-success').classList.add('show');
}

// ============================================================================
// BATALKAN BOOKING
// ============================================================================
function batalBooking() {
    Swal.fire({
        title: 'Batalkan Booking?',
        text: 'Booking akan dibatalkan dan jadwal akan kembali tersedia.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#FF3B30',
        cancelButtonColor: '#8E8E93',
        confirmButtonText: 'Ya, Batalkan',
        cancelButtonText: 'Tidak',
    }).then(res => {
        if (res.isConfirmed) {
            if (state.idBooking > 0) {
                const formData = new FormData();
                formData.append('ajax_batal_booking', '1');
                formData.append('id_booking', state.idBooking);

                fetch('booking_customer.php', { method: 'POST', body: formData })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            clearInterval(qrisTimerInterval);
                            clearInterval(pollingInterval);
                            document.querySelectorAll('.modal-overlay').forEach(m => m.classList.remove('show'));
                            resetBookingState();
                            showToast('success', 'Booking berhasil dibatalkan. Jadwal kembali tersedia.');
                        } else {
                            showToast('error', data.msg || 'Gagal membatalkan booking.');
                        }
                    })
                    .catch(() => {
                        showToast('error', 'Terjadi kesalahan. Coba lagi.');
                    });
            } else {
                clearInterval(qrisTimerInterval);
                clearInterval(pollingInterval);
                document.querySelectorAll('.modal-overlay').forEach(m => m.classList.remove('show'));
                resetBookingState();
            }
        }
    });
}

// ============================================================================
// RESET STATE BOOKING
// ============================================================================
function resetBookingState() {
    state.metode = '';
    state.idBooking = 0;
    
    document.querySelectorAll('.metode-card').forEach(c => c.classList.remove('selected'));
    document.getElementById('btn-bayar').disabled = true;
    
    state.lapanganId = 0;
    state.lapanganNama = '';
    state.lapanganHarga = 0;
    state.jadwalId = 0;
    state.jadwalDisplay = '';
    state.jadwalTanggal = '';
    state.jadwalJam = '';
    
    state.promoId = 0;
    state.promoDiskon = 0;
    state.promoPilihan = 'Tanpa Promo';
    <?php if (!$has_member): ?>
    pilihPromo(0, 0, 'Tanpa Promo');
    <?php endif; ?>
    
    document.querySelectorAll('.lapangan-option').forEach(el => el.classList.remove('selected'));
    document.getElementById('jadwal-grid-container').innerHTML = 
        '<div class="jadwal-empty"><i class="fa-regular fa-calendar-xmark"></i>Pilih lapangan terlebih dahulu untuk melihat jadwal.</div>';
    document.getElementById('btn-next-1').disabled = true;
    document.getElementById('btn-next-2').disabled = true;
    
    updateSummary();
    goStep(1);
}

// ============================================================================
// HELPERS
// ============================================================================
function formatRupiah(n) {
    return 'Rp ' + parseFloat(n).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
}

function formatTanggalJS(dateStr) {
    if (!dateStr) return '-';
    const months = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agt','Sep','Okt','Nov','Des'];
    const d = new Date(dateStr);
    const utc = new Date(d.getTime() + d.getTimezoneOffset() * 60000);
    return utc.getDate() + ' ' + months[utc.getMonth()] + ' ' + utc.getFullYear();
}

function showToast(icon, msg) {
    Swal.fire({
        icon: icon,
        text: msg,
        toast: true,
        position: 'top-end',
        timer: 3500,
        showConfirmButton: false,
        timerProgressBar: true,
        background: '#ffffff',
        color: '#1C1C1E',
        iconColor: icon === 'success' ? '#34C759' : '#FF3B30',
        customClass: {
            popup: 'swal-toast-front'
        }
    });
}

// ============================================================================
// URL PARAMS NOTIFICATION
// ============================================================================
const urlParams = new URLSearchParams(window.location.search);
const statusParam = urlParams.get('status');
const msgParam    = urlParams.get('msg');
if (statusParam && msgParam) {
    showToast(statusParam, msgParam);
    window.history.replaceState({}, document.title, window.location.pathname);
}
</script>

</body>
</html>