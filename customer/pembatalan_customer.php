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

    if (!preg_match("/^[a-zA-Z\s]+$/", $alasan)) {
        echo json_encode(['success' => false, 'message' => 'Alasan pembatalan hanya boleh berisi huruf dan spasi, tidak boleh angka atau simbol.']);
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
    die("<pre>" . print_r(sqlsrv_errors(), true) . "</pre>");
}

if ($stmtBookings) {
    while ($row = sqlsrv_fetch_array($stmtBookings, SQLSRV_FETCH_ASSOC)) {
        $row['Tanggal_Formatted'] = ($row['Tanggal'] instanceof DateTime) ? $row['Tanggal']->format('Y-m-d') : $row['Tanggal'];
        $row['Jam_Mulai_Formatted'] = ($row['Jam_Mulai'] instanceof DateTime) ? $row['Jam_Mulai']->format('H:i') : substr($row['Jam_Mulai'], 0, 5);
        $row['Jam_Selesai_Formatted'] = ($row['Jam_Selesai'] instanceof DateTime) ? $row['Jam_Selesai']->format('H:i') : substr($row['Jam_Selesai'], 0, 5);

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

// Resolve profile photo path
$resolvedPhotoProfile = resolvePhotoPath($photo_profile);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat & Pembatalan | HoopBall Arena</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Barlow+Condensed:wght@700;800;900&family=Barlow:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../asset/css/navbar_footer.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
:root{
    --orange:#FF5400;
    --orange-dark:#E63900;
    --orange-light:rgba(255,84,0,0.08);
    --orange-glow:rgba(255,84,0,0.15);
    --dark:#1E293B;
    --text-dark:#1E293B;
    --text-secondary:#64748B;
    --text-muted:#94A3B8;
    --border-color:#E2E8F0;
    --border-light:#F1F5F9;
    --bg-light:#F8FAFC;
    --card-bg:#FFFFFF;
    --green:#22C55E;
    --green-light:rgba(34,197,94,0.1);
    --red:#EF4444;
    --red-light:rgba(239,68,68,0.1);
    --yellow:#F59E0B;
    --yellow-light:rgba(245,158,11,0.1);
    --blue:#3B82F6;
    --blue-light:rgba(59,130,246,0.1);
    --radius-sm:10px;
    --radius-md:14px;
    --radius-lg:16px;
    --shadow-sm:0 1px 3px rgba(0,0,0,0.04);
    --shadow-md:0 4px 20px rgba(0,0,0,0.06);
    --shadow-lg:0 12px 30px rgba(0,0,0,0.08);
    --transition:all 0.3s cubic-bezier(0.4,0,0.2,1);
}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Barlow',sans-serif;background:var(--bg-light);color:var(--text-dark);-webkit-font-smoothing:antialiased}
::-webkit-scrollbar{width:6px;height:6px}
::-webkit-scrollbar-track{background:transparent}
::-webkit-scrollbar-thumb{background:var(--border-color);border-radius:3px}

/* ============ ANIMATIONS (disamakan dengan booking_customer.php) ============ */
@keyframes fadeInUp{from{opacity:0;transform:translateY(40px)}to{opacity:1;transform:translateY(0)}}
@keyframes fadeInDown{from{opacity:0;transform:translateY(-30px)}to{opacity:1;transform:translateY(0)}}
@keyframes fadeIn{from{opacity:0}to{opacity:1}}
@keyframes scaleIn{from{opacity:0;transform:scale(0.8)}to{opacity:1;transform:scale(1)}}
@keyframes pulse{0%,100%{transform:scale(1);box-shadow:0 0 0 0 rgba(255,84,0,.35)}50%{transform:scale(1.05);box-shadow:0 0 0 10px rgba(255,84,0,0)}}
@keyframes cardEnter{from{opacity:0;transform:translateY(30px) scale(0.95)}to{opacity:1;transform:translateY(0) scale(1)}}
@keyframes iconBounce{0%,100%{transform:scale(1)}50%{transform:scale(1.15)}}
@keyframes loaderBounce{from{transform:translateY(0)}to{transform:translateY(-20px)}}
@keyframes slideUp{from{opacity:0;transform:translateY(30px)}to{opacity:1;transform:translateY(0)}}

/* ============ PAGE LOADER ============ */
.page-loader{position:fixed;top:0;left:0;width:100%;height:100%;background:#0B0B0C;display:flex;align-items:center;justify-content:center;gap:8px;z-index:99999;transition:opacity 0.5s ease,visibility 0.5s ease}
.page-loader.hidden{opacity:0;visibility:hidden;pointer-events:none}
.loader-ball{width:12px;height:12px;border-radius:50%;background:var(--orange);animation:loaderBounce 0.6s ease-in-out infinite alternate}
.loader-ball:nth-child(2){animation-delay:0.2s}
.loader-ball:nth-child(3){animation-delay:0.4s}

/* ============ SCROLL PROGRESS ============ */
.scroll-progress{position:fixed;top:0;left:0;height:3px;background:linear-gradient(90deg,var(--orange),#FF8C42);z-index:9999;transform-origin:left;transform:scaleX(0);transition:transform 0.1s ease-out}

/* ============ REVEAL ON SCROLL ============ */
.reveal{opacity:0;transform:translateY(40px);transition:all 0.8s cubic-bezier(0.16,1,0.3,1)}
.reveal.active{opacity:1;transform:translateY(0)}

/* ============ MAIN CONTAINER ============ */
.pembatalan-container{max-width:900px;margin:0 auto;padding:24px 20px 100px}
.pembatalan-header{margin-bottom:28px;opacity:0;animation:fadeInUp 0.6s ease-out forwards}
.pembatalan-header h1{font-family:'Barlow Condensed',sans-serif;font-size:26px;font-weight:800;color:var(--dark);display:flex;align-items:center;gap:10px}
.pembatalan-header h1 i{color:var(--orange);font-size:22px;animation:iconBounce 2s ease-in-out infinite}
.pembatalan-header p{font-size:13px;color:var(--text-muted);margin-top:4px;font-weight:500}

/* ============ INFO BANNER ============ */
.info-banner{background:var(--card-bg);border:1.5px solid var(--border-color);border-radius:var(--radius-lg);padding:20px;display:flex;gap:14px;align-items:flex-start;margin-bottom:28px;transition:var(--transition);opacity:0;animation:fadeInUp 0.6s ease-out 0.1s forwards}
.info-banner:hover{border-color:var(--orange);box-shadow:var(--shadow-md)}
.info-banner i{color:var(--orange);font-size:20px;margin-top:2px;flex-shrink:0}
.info-banner-text h5{font-size:13.5px;font-weight:800;color:var(--dark);margin-bottom:4px}
.info-banner-text p{font-size:12px;color:var(--text-secondary);line-height:1.6}
.info-banner-text strong{color:var(--orange)}

/* ============ TAB NAVIGATION ============ */
.tab-wrapper{display:flex;gap:4px;background:var(--border-light);padding:4px;border-radius:10px;margin-bottom:28px;width:fit-content;opacity:0;animation:fadeInUp 0.6s ease-out 0.2s forwards}
.tab-btn{flex:1;padding:10px 24px;border:none;border-radius:8px;font-family:inherit;font-size:13px;font-weight:700;cursor:pointer;background:transparent;color:var(--text-secondary);transition:var(--transition);white-space:nowrap;opacity:0;transform:translateY(20px);animation:fadeInUp 0.5s ease-out forwards}
.tab-btn:nth-child(1){animation-delay:0.25s}
.tab-btn:nth-child(2){animation-delay:0.3s}
.tab-btn.active{background:var(--card-bg);color:var(--orange);box-shadow:var(--shadow-sm)}
.tab-btn:hover:not(.active){color:var(--dark)}
.tab-content{display:none;grid-template-columns:repeat(auto-fit, minmax(350px, 1fr));gap:20px}
.tab-content.active{display:grid;animation:scaleIn 0.4s ease}

/* ============ BOOKING CARDS ============ */
.booking-card{background:var(--card-bg);border:1.5px solid var(--border-color);border-radius:var(--radius-lg);overflow:hidden;display:flex;flex-direction:column;transition:var(--transition);position:relative;opacity:0;transform:translateY(30px) scale(0.95);animation:cardEnter 0.5s ease-out forwards}
.booking-card:hover{transform:translateY(-5px);border-color:var(--orange);box-shadow:var(--shadow-lg)}
.booking-card.visible{opacity:1;transform:translateY(0) scale(1)}
.booking-card:nth-child(1){animation-delay:0.1s}
.booking-card:nth-child(2){animation-delay:0.15s}
.booking-card:nth-child(3){animation-delay:0.2s}
.booking-card:nth-child(4){animation-delay:0.25s}
.booking-card:nth-child(5){animation-delay:0.3s}
.booking-card:nth-child(6){animation-delay:0.35s}
.card-img-wrapper{position:relative;height:160px;overflow:hidden;background:linear-gradient(135deg,#FFF7ED 0%,#FFEDD5 100%)}
.card-img{width:100%;height:100%;object-fit:cover;transition:transform 0.5s ease}
.booking-card:hover .card-img{transform:scale(1.05)}
.status-badge{position:absolute;top:12px;right:12px;font-size:11px;font-weight:700;padding:6px 14px;border-radius:20px;text-transform:uppercase;letter-spacing:0.5px}
.status-waiting{background:var(--yellow-light);color:#B45309;border:1px solid rgba(251,191,36,0.3)}
.status-success{background:var(--green-light);color:#047857;border:1px solid rgba(34,197,94,0.3)}
.status-completed{background:var(--blue-light);color:#1E40AF;border:1px solid rgba(59,130,246,0.3)}
.status-cancelled{background:var(--red-light);color:#B91C1C;border:1px solid rgba(239,68,68,0.3)}
.card-body{padding:20px;display:flex;flex-direction:column;gap:12px;flex:1}
.court-title{font-size:16px;font-weight:700;color:var(--dark)}
.booking-date-time{display:flex;flex-direction:column;gap:6px;background:var(--bg-light);padding:12px;border-radius:var(--radius-md);border:1.5px solid var(--border-light)}
.date-time-item{display:flex;align-items:center;gap:8px;font-size:12.5px;color:var(--text-secondary);font-weight:600}
.date-time-item i{color:var(--orange);font-size:14px;width:16px;text-align:center}
.payment-summary{display:flex;justify-content:space-between;align-items:center;border-top:1.5px dashed var(--border-color);padding-top:14px;margin-top:auto}
.payment-label{font-size:12px;color:var(--text-muted);font-weight:600}
.payment-value{font-size:15px;font-weight:800;color:var(--dark)}
.refund-card-info{background:var(--red-light);border:1.5px solid rgba(239,68,68,0.2);border-radius:var(--radius-md);padding:12px 14px;margin-top:10px;font-size:11.5px;color:#991B1B}
.btn-card-action{width:100%;border:none;padding:12px;font-family:inherit;font-size:13px;font-weight:700;border-radius:var(--radius-md);cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:var(--transition);margin-top:14px}
.btn-cancel-allowed{background:var(--red-light);color:var(--red);border:1.5px solid rgba(239,68,68,0.15)}
.btn-cancel-allowed:hover{background:var(--red);color:#fff;box-shadow:0 4px 12px rgba(239,68,68,0.2)}
.btn-disabled{background:var(--border-light);color:var(--text-muted);cursor:not-allowed;border:1.5px solid var(--border-color)}

/* ============ EMPTY STATE ============ */
.empty-state{text-align:center;padding:50px 0;color:var(--text-muted);opacity:0;animation:fadeInUp 0.5s ease-out forwards}
.empty-state i{font-size:40px;margin-bottom:12px;opacity:0.5}
.empty-state p{font-size:13px;font-weight:600}

/* ============ MODAL ============ */
.modal-overlay{position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(15,23,42,0.5);backdrop-filter:blur(4px);display:none;align-items:center;justify-content:center;z-index:1000;padding:20px}
.modal-overlay.active{display:flex;animation:fadeIn 0.2s ease-out}
@keyframes fadeIn{from{opacity:0}to{opacity:1}}
.modal-card{background:var(--card-bg);border-radius:var(--radius-lg);width:100%;max-width:480px;max-height:90vh;overflow-y:auto;box-shadow:var(--shadow-lg);animation:slideUp 0.3s ease-out}
@keyframes slideUp{from{opacity:0;transform:translateY(30px)}to{opacity:1;transform:translateY(0)}}
.modal-header{padding:24px;border-bottom:1.5px solid var(--border-color);display:flex;align-items:center;justify-content:space-between}
.modal-title{font-size:16px;font-weight:700;color:var(--dark);display:flex;align-items:center;gap:8px}
.modal-close{width:32px;height:32px;border-radius:50%;border:none;background:var(--border-light);color:var(--text-muted);cursor:pointer;font-size:14px;transition:var(--transition);display:flex;align-items:center;justify-content:center}
.modal-close:hover{background:var(--red-light);color:var(--red);transform:rotate(90deg)}
.modal-body{padding:24px}
.modal-footer{padding:16px 24px 24px;display:flex;gap:10px}
.modal-footer .btn-full{flex:1}

/* ============ CANCELLATION DETAILS ============ */
.cancellation-details-box{display:flex;flex-direction:column;gap:10px;background:var(--bg-light);border:1.5px solid var(--border-color);border-radius:var(--radius-md);padding:14px;margin-bottom:18px}
.cancel-detail-row{display:flex;justify-content:space-between;font-size:12.5px;font-weight:500;color:var(--text-secondary)}
.cancel-detail-row strong{color:var(--dark);font-weight:700}
.cancellation-refund-breakdown{border-top:1.5px dashed var(--border-color);margin-top:8px;padding-top:10px;display:flex;flex-direction:column;gap:8px}
.refund-row{display:flex;justify-content:space-between;font-size:12.5px;font-weight:600}
.refund-total{font-size:15px;font-weight:800;color:var(--green)}

/* ============ FORM ============ */
.form-group{display:flex;flex-direction:column;gap:8px;margin-bottom:18px}
.form-label{font-size:12px;font-weight:700;color:var(--dark)}
.textarea-control{width:100%;border:1.5px solid var(--border-color);border-radius:var(--radius-md);padding:12px;font-family:inherit;font-size:13px;color:var(--dark);background:var(--card-bg);outline:none;transition:var(--transition);resize:none;min-height:80px}
.textarea-control:focus{border-color:var(--orange);box-shadow:0 0 0 3px var(--orange-glow)}
.textarea-control::placeholder{color:var(--text-muted)}
.checkbox-label{display:flex;align-items:flex-start;gap:10px;font-size:11.5px;color:var(--text-secondary);line-height:1.5;cursor:pointer;user-select:none}
.checkbox-label input{margin-top:2px;accent-color:var(--orange);width:16px;height:16px;flex-shrink:0}
.btn-submit-cancellation{width:100%;background:var(--red);color:#fff;border:none;border-radius:var(--radius-md);padding:14px;font-family:inherit;font-size:13.5px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;margin-top:20px;transition:var(--transition)}
.btn-submit-cancellation:hover{background:#D32F2F;box-shadow:0 8px 20px rgba(239,68,68,0.3)}
.btn-submit-cancellation:disabled{background:var(--text-muted);cursor:not-allowed;box-shadow:none}

/* ============ BUTTONS ============ */
.btn-primary{background:var(--orange);color:#fff;border:none;padding:14px 24px;border-radius:12px;font-family:inherit;font-size:14px;font-weight:700;cursor:pointer;transition:var(--transition);display:flex;align-items:center;justify-content:center;gap:8px;width:100%}
.btn-primary:hover{background:var(--orange-dark);transform:translateY(-1px);box-shadow:0 4px 16px rgba(255,84,0,0.3)}
.btn-primary:disabled{background:var(--text-muted);cursor:not-allowed;transform:none;box-shadow:none}
.btn-secondary{background:var(--border-light);color:var(--dark);border:none;padding:14px 24px;border-radius:12px;font-family:inherit;font-size:14px;font-weight:700;cursor:pointer;transition:var(--transition)}
.btn-secondary:hover{background:var(--border-color)}

/* ============ RESPONSIVE ============ */
@media(max-width:1200px){.footer-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:768px){
    .tab-content.active{grid-template-columns:1fr}
    .pembatalan-container{padding:16px 12px 100px}
}
    </style>
</head>
<body>

<!-- Page Loader -->
<div class="page-loader" id="pageLoader">
    <div class="loader-ball"></div>
    <div class="loader-ball"></div>
    <div class="loader-ball"></div>
</div>

<!-- Scroll Progress -->
<div class="scroll-progress" id="scrollProgress"></div>

<?php $path_prefix = '../'; include '../includes/navbar.php'; ?>

<div class="pembatalan-container">

    <!-- Header Page -->
    <div class="pembatalan-header reveal">
        <h1><i class="fa-solid fa-calendar-minus"></i> Riwayat & Pembatalan Sewa</h1>
        <p>Pantau status transaksi pemesanan lapangan Anda atau lakukan pengajuan pembatalan secara mandiri.</p>
    </div>

    <!-- Kebijakan Pembatalan Banner -->
    <div class="info-banner reveal">
        <i class="fa-solid fa-circle-exclamation"></i>
        <div class="info-banner-text">
            <h5>Ketentuan Pengajuan Pembatalan Sewa Lapangan</h5>
            <p>Pengajuan pembatalan secara mandiri hanya dapat dilakukan paling lambat <strong>1x24 jam</strong> sebelum jadwal bermain dimulai. Sesuai dengan peraturan yang berlaku, dana yang dikembalikan adalah sebesar <strong>50% (Refund)</strong> dari total pembayaran sewa asli, sedangkan sisa 50% akan dipotong sebagai biaya pembatalan operasional.</p>
        </div>
    </div>

    <!-- Tab Buttons -->
    <div class="tab-wrapper reveal">
        <button class="tab-btn active" onclick="switchTab(event, 'aktif')">Pemesanan Aktif (<?= count($bookings_aktif) ?>)</button>
        <button class="tab-btn" onclick="switchTab(event, 'riwayat')">Riwayat Transaksi (<?= count($bookings_riwayat) ?>)</button>
    </div>

    <!-- TAB 1: PEMESANAN AKTIF -->
    <div id="aktif" class="tab-content active">
        <?php if (!empty($bookings_aktif)): ?>
            <?php foreach ($bookings_aktif as $b): 
                $play_time = new DateTime($b['Tanggal_Formatted'] . ' ' . $b['Jam_Mulai_Formatted']);
                $now = new DateTime();
                $diff = $play_time->getTimestamp() - $now->getTimestamp();
                $can_cancel = ($diff >= 86400);
            ?>
                <div class="booking-card">
                    <div class="card-img-wrapper">
                        <?php 
                        $rawPhoto = $b['Photo_Lapangan'] ?? '';
                        $resolvedPhoto = resolvePhotoPath($rawPhoto);
                        $img = !empty($resolvedPhoto) ? htmlspecialchars($resolvedPhoto) : '';
                        ?>
                        <?php if ($img): ?>
                        <img src="<?= $img ?>" class="card-img" alt="Lapangan">
                        <?php endif; ?>

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
            <div class="empty-state" style="grid-column: span 3;">
                <i class="fa-solid fa-calendar-xmark"></i>
                <p>Tidak ada pemesanan aktif saat ini.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- TAB 2: RIWAYAT TRANSAKSI -->
    <div id="riwayat" class="tab-content">
        <?php if (!empty($bookings_riwayat)): ?>
            <?php foreach ($bookings_riwayat as $b): ?>
                <div class="booking-card">
                    <div class="card-img-wrapper">
                        <?php 
                        $rawPhoto = $b['Photo_Lapangan'] ?? '';
                        $resolvedPhoto = resolvePhotoPath($rawPhoto);
                        $img = !empty($resolvedPhoto) ? htmlspecialchars($resolvedPhoto) : '';
                        ?>
                        <?php if ($img): ?>
                            <img src="<?= $img ?>" class="card-img" alt="Lapangan">
                        <?php endif; ?>

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
            <div class="empty-state" style="grid-column: span 3;">
                <i class="fa-solid fa-clock-rotate-left"></i>
                <p>Belum ada riwayat transaksi masa lalu.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- POP-UP MODAL FORM PEMBATALAN -->
<div class="modal-overlay" id="cancelModal">
    <div class="modal-card">
        <div class="modal-header">
            <div class="modal-title"><i class="fa-solid fa-triangle-exclamation" style="color: var(--red);"></i> Konfirmasi Pembatalan</div>
            <button class="modal-close" onclick="closeCancellationModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body">
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
                <label class="form-label">Alasan Pembatalan Sewa<span style="color: var(--red);">*</span></label>
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
</div>

<?php include '../includes/footer.php'; ?>

<script>
    let activeBookingId = null;

    function switchTab(evt, tabId) {
        const tabContents = document.querySelectorAll('.tab-content');
        tabContents.forEach(content => content.classList.remove('active'));

        const tabBtns = document.querySelectorAll('.tab-btn');
        tabBtns.forEach(btn => btn.classList.remove('active'));

        document.getElementById(tabId).classList.add('active');
        evt.currentTarget.classList.add('active');
    }

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

        document.getElementById('cancelModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeCancellationModal() {
        document.getElementById('cancelModal').classList.remove('active');
        document.body.style.overflow = '';
        activeBookingId = null;
    }

    function validateForm() {
        const reasonInput = document.getElementById('txtReason');
        reasonInput.value = reasonInput.value.replace(/[^a-zA-Z\s]/g, '');
        const reason = reasonInput.value.trim();
        const isAgreed = document.getElementById('chkAgree').checked;
        const btnSubmit = document.getElementById('btnSubmitCancel');
        const isValidPattern = /^[a-zA-Z\s]+$/.test(reason);
        btnSubmit.disabled = !(reason.length >= 10 && isValidPattern && isAgreed);
    }

    function processCancellation() {
        const reasonText = document.getElementById('txtReason').value.trim();
        const btnSubmit = document.getElementById('btnSubmitCancel');

        btnSubmit.disabled = true;
        btnSubmit.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Menghubungkan...';

        fetch(window.location.pathname + '?action=submit_pembatalan', {
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
                    confirmButtonColor: '#FF5400',
                    confirmButtonText: 'Kembali'
                }).then(() => {
                    location.reload();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Permintaan Ditolak',
                    text: result.message,
                    confirmButtonColor: '#FF5400'
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
                confirmButtonColor: '#FF5400'
            });
            btnSubmit.disabled = false;
            btnSubmit.innerHTML = '<i class="fa-solid fa-circle-check"></i> Kirim Permohonan Batal';
        });
    }

    document.getElementById('cancelModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeCancellationModal();
        }
    });

    /* ============ ANIMATION JS (disamakan dengan booking_customer.php) ============ */
    document.addEventListener('DOMContentLoaded', () => {
        /* Entrance animation untuk booking cards */
        const cardObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry, index) => {
                if (entry.isIntersecting) {
                    setTimeout(() => { entry.target.classList.add('visible'); }, index * 100);
                    cardObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1 });
        document.querySelectorAll('.booking-card').forEach(card => cardObserver.observe(card));

        /* Reveal-on-scroll untuk header & elements */
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
</script>
</body>
</html>