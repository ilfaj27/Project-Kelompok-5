<?php
// ============================================================================
// BUFFER OUTPUT & SESSION SETUP
// ============================================================================
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../includes/auth_helper.php';
include '../includes/config.php'; // Berisi koneksi $conn menggunakan sqlsrv

// ============================================================================
// CEK AKSES
// ============================================================================
cek_akses('customer');

// ============================================================================
// AMBIL DATA CUSTOMER
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
                header("Location: ../login/login.php?status=error&msg=Akun Anda telah dinonaktifkan.");
                exit();
            }
            $nama_customer = $row_cust['Nama_Customer'] ?? 'Pelanggan';
            $photo_profile = $row_cust['Photo_Profile'] ?? '';
        }
    }
}

// Cek status member aktif untuk badge navigasi
$member_data = null;
$member_check = sqlsrv_query($conn, 
    "SELECT TOP 1 L.*, T.Nama_Tipe FROM Langganan L 
     INNER JOIN Tipe_Member T ON L.ID_Tipe = T.ID_Tipe 
     WHERE L.ID_Customer = ? AND L.Status = 1 
     AND GETDATE() BETWEEN L.Tanggal_Mulai AND L.Tanggal_Selesai", 
    array($id_customer)
);
if ($member_check) {
    $member_data = sqlsrv_fetch_array($member_check, SQLSRV_FETCH_ASSOC);
}
$has_member = !empty($member_data);
$member_tipe = $has_member ? $member_data['Nama_Tipe'] : '';

// ============================================================================
// HANDLER AJAX: PROSES PEMBATALAN BOOKING (POST)
// ============================================================================
if (isset($_GET['action']) && $_GET['action'] == 'submit_pembatalan' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) $input = $_POST;

    $id_booking = intval($input['id_booking'] ?? 0);
    $alasan = htmlspecialchars($input['alasan'] ?? '');

    if ($id_booking <= 0 || empty($alasan)) {
        echo json_encode(['success' => false, 'message' => 'Parameter input pembatalan tidak lengkap.']);
        exit();
    }

    // Ambil data booking untuk verifikasi kepemilikan, batas waktu 24 jam, dan ID_Karyawan
    $queryCheck = "SELECT B.ID_Booking, B.ID_Jadwal, B.ID_Karyawan, B.Total_Bayar, B.Metode_Pembayaran, B.Status AS StatusBooking,
                          J.Tanggal, J.Jam_Mulai 
                   FROM Booking B
                   INNER JOIN Jadwal J ON B.ID_Jadwal = J.ID_Jadwal
                   WHERE B.ID_Booking = ? AND B.ID_Customer = ?";
    $stmtCheck = sqlsrv_query($conn, $queryCheck, array($id_booking, $id_customer));
    
    if ($stmtCheck === false) {
        echo json_encode(['success' => false, 'message' => 'Gagal melakukan verifikasi pemesanan.']);
        exit();
    }

    $booking = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);
    if (!$booking) {
        echo json_encode(['success' => false, 'message' => 'Data pemesanan tidak ditemukan atau bukan milik Anda.']);
        exit();
    }

    // Validasi status sewa (status 3 = Dibatalkan)
    if ($booking['StatusBooking'] == 3) {
        echo json_encode(['success' => false, 'message' => 'Pemesanan ini sudah dibatalkan sebelumnya.']);
        exit();
    }

    // Hitung batas waktu pembatalan (minimal 1x24 jam sebelum jadwal bermain)
    $tanggal_str = ($booking['Tanggal'] instanceof DateTime) ? $booking['Tanggal']->format('Y-m-d') : $booking['Tanggal'];
    $mulai_str = ($booking['Jam_Mulai'] instanceof DateTime) ? $booking['Jam_Mulai']->format('H:i:s') : $booking['Jam_Mulai'];
    
    $play_datetime = new DateTime($tanggal_str . ' ' . $mulai_str);
    $now = new DateTime();
    $diff_seconds = $play_datetime->getTimestamp() - $now->getTimestamp();

    if ($diff_seconds < 86400) {
        echo json_encode(['success' => false, 'message' => 'Pembatalan ditolak. Batas waktu pembatalan paling lambat adalah 24 jam sebelum jadwal bermain.']);
        exit();
    }

    // Mulai Transaksi Database
    if (sqlsrv_begin_transaction($conn) === false) {
        echo json_encode(['success' => false, 'message' => 'Gagal menginisiasi transaksi database.']);
        exit();
    }

    try {
        $id_karyawan = intval($booking['ID_Karyawan']);
        $total_bayar = floatval($booking['Total_Bayar']);
        $biaya_batal = $total_bayar * 0.50; // Denda 50%
        $nominal_refund = $total_bayar * 0.50;    // Pengembalian dana 50%
        $metode_refund = $booking['Metode_Pembayaran'];
        $created_by = $nama_customer;

        // 1. Simpan rekam data ke tabel Pembatalan_Booking
        $queryInsertCancel = "INSERT INTO Pembatalan_Booking 
            (ID_Booking, ID_Karyawan, Tanggal_Batal, Alasan, Biaya_Batal, Nominal_Refund, Metode_Refund, Status, Created_By, Created_Date) 
            VALUES (?, ?, CAST(GETDATE() AS DATE), ?, ?, ?, ?, 0, ?, GETDATE())";
        
        $stmtInsertCancel = sqlsrv_query($conn, $queryInsertCancel, array(
            $id_booking, $id_karyawan, $alasan, $biaya_batal, $nominal_refund, $metode_refund, $created_by
        ));

        if ($stmtInsertCancel === false) {
            $errors = sqlsrv_errors();
            throw new Exception("Gagal menyimpan rincian pengajuan pembatalan ke tabel Pembatalan_Booking. Error: " . ($errors[0]['message'] ?? 'Unknown'));
        }

        // 2. Update status Booking menjadi 3 (Dibatalkan)
        $queryUpdateBooking = "UPDATE Booking SET Status = 3, Modified_By = ?, Modified_Date = GETDATE() WHERE ID_Booking = ?";
        $stmtUpdateBooking = sqlsrv_query($conn, $queryUpdateBooking, array($created_by, $id_booking));
        if ($stmtUpdateBooking === false) {
            throw new Exception("Gagal memperbarui status transaksi pemesanan.");
        }

        // 3. Kembalikan status Jadwal Lapangan menjadi 1 (Tersedia kembali)
        $id_jadwal = $booking['ID_Jadwal'];
        $queryUpdateJadwal = "UPDATE Jadwal SET Status = 1, Modified_By = ?, Modified_Date = GETDATE() WHERE ID_Jadwal = ?";
        $stmtUpdateJadwal = sqlsrv_query($conn, $queryUpdateJadwal, array($created_by, $id_jadwal));
        if ($stmtUpdateJadwal === false) {
            throw new Exception("Gagal mengembalikan status slot jadwal ke sistem.");
        }

        sqlsrv_commit($conn);
        echo json_encode(['success' => true, 'message' => 'Pembatalan booking berhasil diproses. Dana refund 50% akan segera diproses oleh operator.']);
    } catch (Exception $e) {
        sqlsrv_rollback($conn);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// ============================================================================
// AMBIL SEMUA DATA BOOKING CUSTOMER (AKTIF & RIWAYAT) DENGAN PEMBATALAN_BOOKING
// ============================================================================
$bookings_aktif = [];
$bookings_riwayat = [];

$queryGetBookings = "
    SELECT B.ID_Booking, B.ID_Jadwal, B.Tanggal_Booking, B.Metode_Pembayaran, B.Total_Bayar, B.Status AS StatusBooking,
           J.Tanggal, J.Jam_Mulai, J.Jam_Selesai, L.Nama_Lapangan, L.Photo_Lapangan, L.Harga_Sewa,
           P.Nominal_Refund, P.Biaya_Batal, P.Status AS StatusRefund
    FROM Booking B
    INNER JOIN Jadwal J ON B.ID_Jadwal = J.ID_Jadwal
    INNER JOIN Lapangan L ON J.ID_Lapangan = L.ID_Lapangan
    LEFT JOIN Pembatalan_Booking P ON B.ID_Booking = P.ID_Booking
    WHERE B.ID_Customer = ?
    ORDER BY J.Tanggal DESC, J.Jam_Mulai DESC";

$stmtBookings = sqlsrv_query($conn, $queryGetBookings, array($id_customer));
if ($stmtBookings === false) {
    // Menampilkan error jika query bermasalah saat proses development
    die("<pre>" . print_r(sqlsrv_errors(), true) . "</pre>");
}

if ($stmtBookings) {
    while ($row = sqlsrv_fetch_array($stmtBookings, SQLSRV_FETCH_ASSOC)) {
        // Konversi tipe data Tanggal & Jam dari SQL Server ke string PHP
        $row['Tanggal_Formatted'] = ($row['Tanggal'] instanceof DateTime) ? $row['Tanggal']->format('Y-m-d') : $row['Tanggal'];
        $row['Jam_Mulai_Formatted'] = ($row['Jam_Mulai'] instanceof DateTime) ? $row['Jam_Mulai']->format('H:i') : substr($row['Jam_Mulai'], 0, 5);
        $row['Jam_Selesai_Formatted'] = ($row['Jam_Selesai'] instanceof DateTime) ? $row['Jam_Selesai']->format('H:i') : substr($row['Jam_Selesai'], 0, 5);
        
        // Klasifikasi tab aktif vs riwayat (Status Booking: 0 = Menunggu Konfirmasi, 1 = Berhasil, 2 = Selesai, 3 = Dibatalkan)
        if ($row['StatusBooking'] == 0 || $row['StatusBooking'] == 1) {
            $bookings_aktif[] = $row;
        } else {
            $bookings_riwayat[] = $row;
        }
    }
}


/* ─── HELPER: RESOLVE PHOTO PATH ─── */
function resolvePhotoPath($photo_path) {
    if (empty($photo_path)) return '';
    if (strpos($photo_path, 'http://') === 0 || strpos($photo_path, 'https://') === 0) {
        return $photo_path;
    }
    if (strpos($photo_path, '../') === 0) {
        return $photo_path;
    }
    if (strpos($photo_path, '/') === 0) {
        return '..' . $photo_path;
    }
    return '../' . ltrim($photo_path, '/');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat & Pembatalan | HoopBall Arena</title>
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

        /* Menghilangkan scrollbar secara global */
        ::-webkit-scrollbar { display: none; }
        html, body { -ms-overflow-style: none; scrollbar-width: none; }

        /* ============ KEYFRAMES & ANIMATIONS ============ */
        @keyframes fadeInUp { from{opacity:0;transform:translateY(30px)} to{opacity:1;transform:translateY(0)} }
        @keyframes fadeInDown { from{opacity:0;transform:translateY(-30px)} to{opacity:1;transform:translateY(0)} }
        @keyframes scaleIn { from{opacity:0;transform:scale(0.95)} to{opacity:1;transform:scale(1)} }
        @keyframes pulse{0%,100%{transform:scale(1);box-shadow:0 0 0 0 rgba(52,199,89,.4)}50%{transform:scale(1.05);box-shadow:0 0 0 15px rgba(52,199,89,0)}}

        .anim-fade-up { animation: fadeInUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
        .anim-scale-in { animation: scaleIn 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards; }

        /* ============ NAVBAR ============ */
        nav { background:var(--white); padding:0 80px; display:flex; justify-content:space-between; align-items:center; height:76px; position:sticky; top:0; z-index:1000; border-bottom:1px solid #E5E5EA; animation:fadeInDown 0.6s ease-out forwards; }
        .nav-logo { display:flex; align-items:center; text-decoration:none; gap:10px; transition:transform 0.3s ease; }
        .nav-logo:hover { transform:scale(1.05); }
        .nav-logo img { height:70px; width:auto; transition:transform 0.5s cubic-bezier(0.34,1.56,0.64,1); }
        .nav-logo:hover img { transform:rotate(5deg) scale(1.1); }
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
        .nav-user img.user-avatar { width:24px; height:24px; border-radius:50%; object-fit:cover; }
        .nav-user i.user-icon { font-size:16px; color:var(--primary); }
        .nav-user i.arrow { font-size:11px; color:#8E8E93; transition:0.3s cubic-bezier(0.34,1.56,0.64,1); }
        .nav-user-container:hover i.arrow { transform:rotate(180deg); color:var(--primary); }
        .dropdown-menu { position:absolute; top:85%; right:0; background:#16161a; min-width:220px; border-radius:12px; border:1px solid #2d2d33; box-shadow:0 10px 30px rgba(0,0,0,0.5); padding:8px 0; display:none; z-index:1001; transform-origin:top right; }
        .nav-user-container:hover .dropdown-menu { display:block; animation:fadeInUp 0.3s cubic-bezier(0.16,1,0.3,1) forwards; }
        .dropdown-menu .user-info-header { padding:12px 20px; border-bottom:1px solid #2d2d33; margin-bottom:6px; }
        .dropdown-menu .user-info-header span { display:block; }
        .dropdown-menu .user-info-header .u-name { color:var(--white); font-size:14px; font-weight:700; }
        .dropdown-menu .user-info-header .u-role { color:var(--text-gray); font-size:11px; text-transform:uppercase; letter-spacing:0.5px; margin-top:2px; }
        .dropdown-menu a { display:flex; align-items:center; gap:12px; padding:10px 20px; color:#c5c5ca; text-decoration:none; font-size:13px; font-weight:500; transition:all 0.25s cubic-bezier(0.16,1,0.3,1); }
        .dropdown-menu a:hover { background:#222227; color:var(--primary); padding-left:28px; }
        .dropdown-divider { height:1px; background:#2d2d33; margin:6px 0; }
        .member-badge-nav { display:inline-flex; align-items:center; gap:6px; background:var(--green-lt); border:1px solid var(--green); color:var(--green); padding:4px 12px; border-radius:50px; font-size:11px; font-weight:700; margin-left:8px; animation:pulse 2s ease-in-out infinite; }

        /* ============ CONTAINER & TABS ============ */
        .container { width: 100%; max-width: 90%; margin: 40px auto; padding: 0 20px; display: flex; flex-direction: column; gap: 30px; min-height: 60vh; }
        
        .section-header { display: flex; flex-direction: column; gap: 6px; }
        .section-title { font-size: 22px; font-weight: 800; color: var(--text-primary); display: flex; align-items: center; gap: 10px; }
        .section-subtitle { font-size: 13px; color: var(--text-secondary); font-weight: 500; }

        /* Tab Navigation Styling */
        .tab-wrapper { display: flex; gap: 12px; border-bottom: 2px solid var(--border-lt); padding-bottom: 2px; }
        .tab-btn { background: none; border: none; font-family: inherit; font-size: 14px; font-weight: 700; color: var(--text-secondary); padding: 12px 24px; cursor: pointer; transition: var(--transition-smooth); position: relative; }
        .tab-btn.active { color: var(--orange); }
        .tab-btn.active::after { content: ''; position: absolute; bottom: -4px; left: 0; width: 100%; height: 3px; background: var(--orange); border-radius: 10px; }

        .tab-content { display: none; }
        .tab-content.active { display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 24px; animation: scaleIn 0.4s ease; }

        /* ============ BOOKING CARDS ============ */
        .booking-card { background: #fff; border: 1px solid var(--border); border-radius: 16px; overflow: hidden; display: flex; flex-direction: column; transition: var(--transition-smooth); position: relative; }
        .booking-card:hover { transform: translateY(-5px); border-color: var(--orange); box-shadow: 0 10px 20px rgba(0,0,0,0.05); }
        
        .card-img-wrapper { position: relative; height: 160px; overflow: hidden; background: #cbd5e1; }
        .card-img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.6s ease; }
        .booking-card:hover .card-img { transform: scale(1.05); }

        /* Badges */
        .status-badge { position: absolute; top: 12px; right: 12px; font-size: 11px; font-weight: 700; padding: 6px 14px; border-radius: 20px; text-transform: uppercase; letter-spacing: 0.5px; }
        .status-waiting { background: var(--yellow-lt); color: #B45309; border: 1px solid rgba(251, 191, 36, 0.3); }
        .status-success { background: var(--green-lt); color: #047857; border: 1px solid rgba(52, 199, 89, 0.3); }
        .status-completed { background: var(--blue-lt); color: #1E40AF; border: 1px solid rgba(0, 122, 255, 0.3); }
        .status-cancelled { background: var(--red-lt); color: #B91C1C; border: 1px solid rgba(255, 59, 48, 0.3); }

        .card-body { padding: 20px; display: flex; flex-direction: column; gap: 12px; flex: 1; }
        .court-title { font-size: 16px; font-weight: 800; color: var(--text-primary); }
        .booking-date-time { display: flex; flex-direction: column; gap: 6px; background: var(--bg); padding: 12px; border-radius: 10px; border: 1px solid var(--border-lt); }
        .date-time-item { display: flex; align-items: center; gap: 8px; font-size: 12.5px; color: var(--text-secondary); font-weight: 600; }
        .date-time-item i { color: var(--orange); font-size: 14px; width: 16px; text-align: center; }

        .payment-summary { display: flex; justify-content: space-between; align-items: center; border-top: 1px dashed var(--border); padding-top: 14px; margin-top: auto; }
        .payment-label { font-size: 12px; color: var(--muted); font-weight: 600; }
        .payment-value { font-size: 15px; font-weight: 800; color: var(--text-primary); }

        /* Refund Breakdown Display inside Card */
        .refund-card-info { background: #FEF2F2; border: 1px solid #FCA5A5; border-radius: 10px; padding: 10px 14px; margin-top: 10px; font-size: 11.5px; color: #991B1B; }

        .btn-card-action { width: 100%; border: none; padding: 12px; font-family: inherit; font-size: 13px; font-weight: 700; border-radius: 10px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: var(--transition-smooth); margin-top: 14px; }
        .btn-cancel-allowed { background: var(--red-lt); color: var(--red); border: 1px solid rgba(255,59,48,0.15); }
        .btn-cancel-allowed:hover { background: var(--red); color: #fff; box-shadow: 0 4px 12px rgba(255, 59, 48, 0.2); }
        .btn-disabled { background: var(--border-lt); color: var(--muted); cursor: not-allowed; border: 1px solid var(--border); }

        /* Info Rules banner */
        .info-rules-banner { background: #EFF6FF; border: 1px solid rgba(0, 122, 255, 0.15); border-radius: 14px; padding: 16px 20px; display: flex; gap: 14px; align-items: flex-start; }
        .info-rules-banner i { color: var(--blue); font-size: 20px; margin-top: 2px; animation: pulse 2s ease-in-out infinite; }
        .info-rules-text h5 { font-size: 13.5px; font-weight: 800; color: #1E40AF; margin-bottom: 4px; }
        .info-rules-text p { font-size: 12px; color: #1E40AF; line-height: 1.6; }

        /* ============ MODAL WINDOW ============ */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); display: none; align-items: center; justify-content: center; z-index: 2000; padding: 20px; animation: fadeInModal 0.25s ease-out forwards; }
        @keyframes fadeInModal { from { opacity: 0; } to { opacity: 1; } }

        .modal-card { background: #fff; border-radius: 20px; width: 100%; max-width: 480px; max-height: 90vh; overflow-y: auto; position: relative; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15); padding: 30px; animation: slideInModal 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
        @keyframes slideInModal { from { transform: translateY(30px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

        .modal-close { position: absolute; top: 20px; right: 20px; background: var(--border-lt); border: none; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; color: var(--text-secondary); transition: var(--transition-smooth); }
        .modal-close:hover { background: var(--red-lt); color: var(--red); transform: rotate(90deg); }

        .modal-title { font-size: 18px; font-weight: 800; color: var(--text-primary); margin-bottom: 20px; display: flex; align-items: center; gap: 8px; }
        
        .cancellation-details-box { display: flex; flex-direction: column; gap: 10px; background: var(--bg); border: 1px solid var(--border); border-radius: 12px; padding: 14px; margin-bottom: 18px; }
        .cancel-detail-row { display: flex; justify-content: space-between; font-size: 12.5px; font-weight: 500; color: var(--text-secondary); }
        .cancel-detail-row strong { color: var(--text-primary); font-weight: 700; }

        .cancellation-refund-breakdown { border-top: 1px dashed var(--border); margin-top: 8px; padding-top: 10px; display: flex; flex-direction: column; gap: 8px; }
        .refund-row { display: flex; justify-content: space-between; font-size: 12.5px; font-weight: 600; }
        .refund-total { font-size: 15px; font-weight: 800; color: var(--green); }

        .form-group { display: flex; flex-direction: column; gap: 8px; margin-bottom: 18px; }
        .form-label { font-size: 12px; font-weight: 700; color: var(--text-primary); }
        .textarea-control { width: 100%; border: 1px solid var(--border); border-radius: 10px; padding: 12px; font-family: inherit; font-size: 13px; outline: none; transition: var(--transition-smooth); resize: none; min-height: 80px; }
        .textarea-control:focus { border-color: var(--orange); box-shadow: 0 0 0 3px var(--orange-glow); }

        .checkbox-label { display: flex; align-items: flex-start; gap: 10px; font-size: 11.5px; color: var(--text-secondary); line-height: 1.5; cursor: pointer; user-select: none; }
        .checkbox-label input { margin-top: 2px; accent-color: var(--orange); }

        .btn-submit-cancellation { width: 100%; background: var(--red); color: #fff; border: none; border-radius: 12px; padding: 14px; font-family: inherit; font-size: 13.5px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 20px; transition: var(--transition-smooth); }
        .btn-submit-cancellation:hover { background: #D32F2F; box-shadow: 0 8px 20px rgba(255, 59, 48, 0.3); }
        .btn-submit-cancellation:disabled { background: var(--muted); cursor: not-allowed; box-shadow: none; }

        /* ============ FOOTER ============ */
        footer { background: var(--dark-bg); color: #8E8E93; padding: 80px 80px 40px; border-top: 1px solid #1C1C1E; position: relative; overflow: hidden; }
        footer::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 1px; background: linear-gradient(90deg, transparent, var(--primary), transparent); background-size: 200% 100%; }
        .footer-grid { display: grid; grid-template-columns: 1.5fr 1fr 1fr 1.2fr; gap: 40px; margin-bottom: 60px; }
        .footer-logo { display: flex; align-items: center; gap: 10px; margin-bottom: 16px; transition: transform 0.3s ease; }
        .footer-logo img { height: 70px; }
        .footer-desc { font-size: 13px; line-height: 1.6; margin-bottom: 24px; }
        .social-links { display: flex; gap: 12px; }
        .social-btn { width: 36px; height: 36px; border-radius: 50%; background: #1C1C1E; color: var(--white); display: flex; align-items: center; justify-content: center; text-decoration: none; transition: all 0.3s cubic-bezier(0.34,1.56,0.64,1); }
        .social-btn:hover { background: var(--primary); transform: translateY(-3px) scale(1.1); box-shadow: 0 8px 20px rgba(255,82,0,0.3); }
        .footer-col h4 { color: var(--white); font-size: 15px; font-weight: 700; margin-bottom: 20px; position: relative; display: inline-block; }
        .footer-col h4::after { content: ''; position: absolute; bottom: -4px; left: 0; width: 30px; height: 2px; background: var(--primary); }
        .footer-col ul { list-style: none; }
        .footer-col ul li { margin-bottom: 12px; }
        .footer-col ul li a { color: #8E8E93; text-decoration: none; font-size: 13px; transition: all 0.3s ease; display: inline-block; }
        .footer-col ul li a:hover { color: var(--white); transform: translateX(5px); }
        .contact-item { display: flex; gap: 12px; font-size: 13px; line-height: 1.5; margin-bottom: 16px; padding: 4px; }
        .contact-item i { color: var(--primary); font-size: 14px; margin-top: 3px; }
        .footer-bottom { border-top: 1px solid #1C1C1E; padding-top: 30px; text-align: center; font-size: 13px; position: relative; }

        @media (max-width: 1200px) { nav { padding: 0 40px; } .footer-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 768px) {
            nav { flex-direction: column; height: auto; padding: 15px 20px; gap: 15px; }
            .nav-links { flex-wrap: wrap; justify-content: center; gap: 4px; }
            .nav-user-container { height: auto; }
            .dropdown-menu { top: 50px; right: 50%; transform: translateX(50%); }
            .footer-grid { grid-template-columns: 1fr; }
            .tab-content.active { grid-template-columns: 1fr; }
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
        <a href="booking_customer.php">Booking</a>
        <a href="pembatalan_customer.php" class="active">Pembatalan</a>
        <a href="langganan_customer.php">Member</a>
        <a href="pembelian_alat.php">Pembelian</a>
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
            <a href="langganan_customer.php"><i class="fa-solid fa-crown"></i> Langganan Member</a>
            <a href="pembelian_alat.php"><i class="fa-solid fa-cart-shopping"></i> Pembelian Alat</a>
            <div class="dropdown-divider"></div>
            <a href="../login/logout.php" class="logout"><i class="fa-solid fa-right-from-bracket"></i> Keluar</a>
        </div>
    </div>
</nav>

<div class="container">
    
    <!-- Header Page -->
    <div class="section-header anim-fade-up">
        <h2 class="section-title"><i class="fa-solid fa-calendar-minus" style="color: var(--orange);"></i> Riwayat & Pembatalan Sewa</h2>
        <p class="section-subtitle">Pantau status transaksi pemesanan lapangan Anda atau lakukan pengajuan pembatalan secara mandiri.</p>
    </div>

    <!-- Kebijakan Pembatalan Banner -->
    <div class="info-rules-banner anim-fade-up">
        <i class="fa-solid fa-circle-exclamation"></i>
        <div class="info-rules-text">
            <h5>Ketentuan Pengajuan Pembatalan Sewa Lapangan</h5>
            <p>Pengajuan pembatalan secara mandiri hanya dapat dilakukan paling lambat <strong>1x24 jam</strong> sebelum jadwal bermain dimulai. Sesuai dengan peraturan yang berlaku, dana yang dikembalikan adalah sebesar <strong>50% (Refund)</strong> dari total pembayaran sewa asli, sedangkan sisa 50% akan dipotong sebagai biaya pembatalan operasional.</p>
        </div>
    </div>

    <!-- Tab Buttons -->
    <div class="tab-wrapper anim-fade-up">
        <button class="tab-btn active" onclick="switchTab(event, 'aktif')">Pemesanan Aktif (<?= count($bookings_aktif) ?>)</button>
        <button class="tab-btn" onclick="switchTab(event, 'riwayat')">Riwayat Transaksi (<?= count($bookings_riwayat) ?>)</button>
    </div>

    <!-- TAB 1: PEMESANAN AKTIF -->
    <div id="aktif" class="tab-content active anim-scale-in">
        <?php if (!empty($bookings_aktif)): ?>
            <?php foreach ($bookings_aktif as $b): 
                // Cek sisa waktu bermain untuk tombol pembatalan
                $play_time = new DateTime($b['Tanggal_Formatted'] . ' ' . $b['Jam_Mulai_Formatted']);
                $now = new DateTime();
                $diff = $play_time->getTimestamp() - $now->getTimestamp();
                $can_cancel = ($diff >= 86400); // 24 Jam dalam detik
            ?>
                <div class="booking-card">
                    <div class="card-img-wrapper">
                        <?php 
                        $rawPhoto = $b['Photo_Lapangan'] ?? '';
                        $resolvedPhoto = resolvePhotoPath($rawPhoto);
                        $img = !empty($resolvedPhoto) ? htmlspecialchars($resolvedPhoto) : 'https://images.unsplash.com/photo-1544698310-74ea9d1c8258?q=80&w=600&auto=format&fit=crop';
                        ?>
                        <img src="<?= $img ?>" class="card-img" alt="Lapangan" onerror="this.src='https://images.unsplash.com/photo-1544698310-74ea9d1c8258?q=80&w=600&auto=format&fit=crop'">
                        
                        <?php if ($b['StatusBooking'] == 0): ?>
                            <span class="status-badge status-waiting">Menunggu Konfirmasi</span>
                        <?php elseif ($b['StatusBooking'] == 1): ?>
                            <span class="status-badge status-success">Pembayaran Berhasil</span>
                        <?php endif; ?>
                    </div>

                    <div class="card-body">
                        <h3 class="court-title"><?= htmlspecialchars($b['Nama_Lapangan']) ?></h3>
                        
                        <div class="booking-date-time">
                            <div class="date-time-item">
                                <i class="fa-solid fa-calendar-day"></i>
                                <span><?= date('d M Y', strtotime($b['Tanggal_Formatted'])) ?></span>
                            </div>
                            <div class="date-time-item">
                                <i class="fa-solid fa-clock"></i>
                                <span><?= $b['Jam_Mulai_Formatted'] ?> - <?= $b['Jam_Selesai_Formatted'] ?> WIB</span>
                            </div>
                        </div>

                        <div class="payment-summary">
                            <span class="payment-label">Total Pembayaran</span>
                            <span class="payment-value">Rp <?= number_format($b['Total_Bayar'], 0, ',', '.') ?></span>
                        </div>

                        <?php if ($can_cancel): ?>
                            <button class="btn-card-action btn-cancel-allowed" 
                                    onclick="openCancellationModal(<?= $b['ID_Booking'] ?>, '<?= htmlspecialchars($b['Nama_Lapangan']) ?>', '<?= date('d M Y', strtotime($b['Tanggal_Formatted'])) ?>', '<?= $b['Jam_Mulai_Formatted'] ?>', <?= $b['Total_Bayar'] ?>, '<?= htmlspecialchars($b['Metode_Pembayaran']) ?>')">
                                <i class="fa-solid fa-rectangle-xmark"></i> Ajukan Pembatalan
                            </button>
                        <?php else: ?>
                            <button class="btn-card-action btn-disabled" disabled>
                                <i class="fa-solid fa-ban"></i> Pembatalan Dikunci (Sisa &lt; 24 Jam)
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="grid-column: span 3; text-align: center; color: var(--muted); padding: 50px 0;">
                <i class="fa-solid fa-calendar-xmark" style="font-size: 40px; margin-bottom: 12px; color: var(--muted);"></i>
                <p style="font-size: 13px; font-weight: 500;">Tidak ada pemesanan aktif saat ini.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- TAB 2: RIWAYAT TRANSAKSI -->
    <div id="riwayat" class="tab-content anim-scale-in">
        <?php if (!empty($bookings_riwayat)): ?>
            <?php foreach ($bookings_riwayat as $b): ?>
                <div class="booking-card">
                    <div class="card-img-wrapper">
                        <?php 
                        $rawPhoto = $b['Photo_Lapangan'] ?? '';
                        $resolvedPhoto = resolvePhotoPath($rawPhoto);
                        $img = !empty($resolvedPhoto) ? htmlspecialchars($resolvedPhoto) : 'https://images.unsplash.com/photo-1544698310-74ea9d1c8258?q=80&w=600&auto=format&fit=crop';
                        ?>
                        <img src="<?= $img ?>" class="card-img" alt="Lapangan" onerror="this.src='https://images.unsplash.com/photo-1544698310-74ea9d1c8258?q=80&w=600&auto=format&fit=crop'">
                        
                        <?php if ($b['StatusBooking'] == 2): ?>
                            <span class="status-badge status-completed">Selesai</span>
                        <?php elseif ($b['StatusBooking'] == 3): ?>
                            <span class="status-badge status-cancelled">Dibatalkan</span>
                        <?php endif; ?>
                    </div>

                    <div class="card-body">
                        <h3 class="court-title"><?= htmlspecialchars($b['Nama_Lapangan']) ?></h3>
                        
                        <div class="booking-date-time">
                            <div class="date-time-item">
                                <i class="fa-solid fa-calendar-check"></i>
                                <span><?= date('d M Y', strtotime($b['Tanggal_Formatted'])) ?></span>
                            </div>
                            <div class="date-time-item">
                                <i class="fa-solid fa-clock"></i>
                                <span><?= $b['Jam_Mulai_Formatted'] ?> - <?= $b['Jam_Selesai_Formatted'] ?> WIB</span>
                            </div>
                        </div>

                        <div class="payment-summary">
                            <span class="payment-label">Pembayaran Asli</span>
                            <span class="payment-value">Rp <?= number_format($b['Total_Bayar'], 0, ',', '.') ?></span>
                        </div>

                        <?php if ($b['StatusBooking'] == 3): ?>
                            <div class="refund-card-info">
                                <div style="display: flex; justify-content: space-between; font-weight: 700;">
                                    <span>Dana Direfund (50%):</span>
                                    <span>Rp <?= number_format($b['Nominal_Refund'] ?? ($b['Total_Bayar'] * 0.5), 0, ',', '.') ?></span>
                                </div>
                                <div style="display: flex; justify-content: space-between; font-size: 10px; margin-top: 4px; opacity: 0.8;">
                                    <span>Metode Refund:</span>
                                    <span><?= htmlspecialchars($b['Metode_Pembayaran']) ?></span>
                                </div>
                                <div style="display: flex; justify-content: space-between; font-size: 10px; margin-top: 2px; opacity: 0.8;">
                                    <span>Status Refund:</span>
                                    <span><?= isset($b['StatusRefund']) && $b['StatusRefund'] == 1 ? 'Selesai Ditransfer' : 'Menunggu Transfer' ?></span>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="grid-column: span 3; text-align: center; color: var(--muted); padding: 50px 0;">
                <i class="fa-solid fa-clock-rotate-left" style="font-size: 40px; margin-bottom: 12px; color: var(--muted);"></i>
                <p style="font-size: 13px; font-weight: 500;">Belum ada riwayat transaksi masa lalu.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- POP-UP MODAL FORM PEMBATALAN -->
<div class="modal-overlay" id="cancelModal">
    <div class="modal-card">
        <button class="modal-close" onclick="closeCancellationModal()"><i class="fa-solid fa-xmark"></i></button>
        
        <h2 class="modal-title"><i class="fa-solid fa-triangle-exclamation" style="color: var(--red);"></i> Konfirmasi Pembatalan</h2>
        
        <div class="cancellation-details-box">
            <div class="cancel-detail-row"><span>Lapangan:</span><strong id="mCourtName">-</strong></div>
            <div class="cancel-detail-row"><span>Tanggal Bermain:</span><strong id="mPlayDate">-</strong></div>
            <div class="cancel-detail-row"><span>Waktu Mulai:</span><strong id="mPlayTime">-</strong></div>
            <div class="cancel-detail-row"><span>Metode Pengembalian:</span><strong id="mRefundMethod">-</strong></div>
            
            <div class="cancellation-refund-breakdown">
                <div class="cancel-detail-row">
                    <span>Biaya Sewa Awal</span>
                    <span>Rp <span id="mOriginalPrice">0</span></span>
                </div>
                <div class="cancel-detail-row" style="color: var(--red);">
                    <span>Denda Pembatalan (50%)</span>
                    <span>-Rp <span id="mFee">0</span></span>
                </div>
                <div class="refund-row">
                    <span>Estimasi Dana Refund (50%)</span>
                    <span class="refund-total">Rp <span id="mRefundVal">0</span></span>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Alasan Pembatalan Sewa</label>
            <textarea id="txtReason" class="textarea-control" placeholder="Tuliskan alasan rasional mengapa Anda membatalkan sewa ini..." oninput="validateForm()"></textarea>
        </div>

        <label class="checkbox-label">
            <input type="checkbox" id="chkAgree" onchange="validateForm()">
            <span>Saya menyetujui denda pemotongan biaya pembatalan sewa sebesar 50% dari total transaksi pembayaran awal.</span>
        </label>

        <button class="btn-submit-cancellation" id="btnSubmitCancel" onclick="processCancellation()" disabled>
            <i class="fa-solid fa-circle-check"></i> Kirim Permohonan Batal
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
                <li><a href="langganan_customer.php">Member</a></li>
                <li><a href="pembelian_customer.php">Pembelian</a></li>
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
        </div>
    </div>
    <div class="footer-bottom">
        <p>&copy; 2025 HoopBall. All rights reserved.</p>
    </div>
</footer>

<script>
    // System State
    let activeBookingId = null;

    // Switch Tabs Active & History
    function switchTab(evt, tabId) {
        const tabContents = document.querySelectorAll('.tab-content');
        tabContents.forEach(content => content.classList.remove('active'));

        const tabBtns = document.querySelectorAll('.tab-btn');
        tabBtns.forEach(btn => btn.classList.remove('active'));

        document.getElementById(tabId).classList.add('active');
        evt.currentTarget.classList.add('active');
    }

    // Modal Control
    function openCancellationModal(idBooking, courtName, playDate, playTime, originalPrice, refundMethod) {
        activeBookingId = idBooking;
        
        document.getElementById('mCourtName').innerText = courtName;
        document.getElementById('mPlayDate').innerText = playDate;
        document.getElementById('mPlayTime').innerText = playTime + ' WIB';
        document.getElementById('mRefundMethod').innerText = refundMethod;
        
        const halfPrice = originalPrice * 0.50;
        document.getElementById('mOriginalPrice').innerText = originalPrice.toLocaleString('id-ID');
        document.getElementById('mFee').innerText = halfPrice.toLocaleString('id-ID');
        document.getElementById('mRefundVal').innerText = halfPrice.toLocaleString('id-ID');

        document.getElementById('txtReason').value = '';
        document.getElementById('chkAgree').checked = false;
        document.getElementById('btnSubmitCancel').disabled = true;

        document.getElementById('cancelModal').style.display = 'flex';
    }

    function closeCancellationModal() {
        document.getElementById('cancelModal').style.display = 'none';
        activeBookingId = null;
    }

    // Modal Form Validator
    function validateForm() {
        const reason = document.getElementById('txtReason').value.trim();
        const isAgreed = document.getElementById('chkAgree').checked;
        const btnSubmit = document.getElementById('btnSubmitCancel');

        if (reason.length >= 10 && isAgreed) {
            btnSubmit.disabled = false;
        } else {
            btnSubmit.disabled = true;
        }
    }

    // AJAX Request Execution
    function processCancellation() {
        const reasonText = document.getElementById('txtReason').value.trim();
        const btnSubmit = document.getElementById('btnSubmitCancel');

        btnSubmit.disabled = true;
        btnSubmit.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Menghubungkan...';

        fetch('pembatalan_booking.php?action=submit_pembatalan', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id_booking: activeBookingId,
                alasan: reasonText
            })
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                closeCancellationModal();
                Swal.fire({
                    icon: 'success',
                    title: 'Pembatalan Diproses',
                    text: result.message,
                    confirmButtonColor: 'var(--orange)',
                    confirmButtonText: 'Kembali Ke Riwayat'
                }).then(() => {
                    location.reload();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Permintaan Ditolak',
                    text: result.message,
                    confirmButtonColor: 'var(--orange)'
                });
                btnSubmit.disabled = false;
                btnSubmit.innerHTML = '<i class="fa-solid fa-circle-check"></i> Kirim Permohonan Batal';
            }
        })
        .catch(err => {
            console.error("Koneksi gagal:", err);
            Swal.fire({
                icon: 'error',
                title: 'Gangguan Sistem',
                text: 'Terjadi kegagalan komunikasi dengan server. Pastikan koneksi internet stabil.',
                confirmButtonColor: 'var(--orange)'
            });
            btnSubmit.disabled = false;
            btnSubmit.innerHTML = '<i class="fa-solid fa-circle-check"></i> Kirim Permohonan Batal';
        });
    }

    // Tutup modal jika mengklik area luar modal
    window.addEventListener('click', function(e) {
        const cancelModal = document.getElementById('cancelModal');
        if (e.target === cancelModal) {
            closeCancellationModal();
        }
    });
</script>
</body>
</html>