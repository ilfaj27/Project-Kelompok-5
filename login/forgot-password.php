<?php
session_start();
include '../includes/config.php';

$res_status = "";
$res_msg = "";
$is_verified = isset($_SESSION['reset_id_customer']);

$username_input = "";
$tanggal_input = "";
$nominal_input = "";

// TAHAP 1: VERIFIKASI KEAMANAN DATA AKUN & RIWAYAT TRANSAKSI
if (isset($_POST['verify_account'])) {
    $username_input = trim($_POST['username_input'] ?? '');
    $nominal_input = trim($_POST['nominal_input'] ?? '');
    $tanggal_input = trim($_POST['tanggal_input'] ?? '');

    $sql = "SELECT ID_Customer FROM Customer 
            WHERE Username = ? AND Is_Deleted = 0";
    $params = array($username_input);
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        $res_status = "error";
        $res_msg = "Terjadi kesalahan sistem koneksi database.";
    } else {
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        if ($row) {
            $id_customer = $row['ID_Customer'];

            $sql_booking = "SELECT TOP 1 Tanggal_Booking, Total_Bayar 
                            FROM Booking 
                            WHERE ID_Customer = ? 
                            ORDER BY Tanggal_Booking DESC";
            $stmt_booking = sqlsrv_query($conn, $sql_booking, array($id_customer));

            if ($stmt_booking === false) {
                $res_status = "error";
                $res_msg = "Terjadi kesalahan saat memverifikasi riwayat transaksi.";
            } else {
                $row_booking = sqlsrv_fetch_array($stmt_booking, SQLSRV_FETCH_ASSOC);

                if ($row_booking) {
                    $db_tanggal = $row_booking['Tanggal_Booking'];
                    if ($db_tanggal instanceof DateTime) {
                        $db_tanggal_str = $db_tanggal->format('Y-m-d');
                    } else {
                        $db_tanggal_str = date('Y-m-d', strtotime($db_tanggal));
                    }

                    $db_nominal = (int)$row_booking['Total_Bayar'];
                    $clean_nominal_input = (int)preg_replace('/[^0-9]/', '', $nominal_input);

                    if ($db_tanggal_str === $tanggal_input && $db_nominal === $clean_nominal_input) {
                        $is_verified = true;
                        $_SESSION['reset_id_customer'] = $id_customer;
                    } else {
                        $res_status = "error";
                        $res_msg = "Data verifikasi salah. Detail riwayat transaksi terakhir tidak cocok!";
                    }
                } else {
                    $clean_nominal_input = (int)preg_replace('/[^0-9]/', '', $nominal_input);
                    if (empty($tanggal_input) && $clean_nominal_input === 0) {
                        $is_verified = true;
                        $_SESSION['reset_id_customer'] = $id_customer;
                    } else {
                        $res_status = "error";
                        $res_msg = "Data verifikasi salah. Akun Anda belum memiliki riwayat transaksi.";
                    }
                }
            }
        } else {
            $res_status = "error";
            $res_msg = "Data verifikasi salah. Nama pengguna tidak ditemukan!";
        }
    }
}

// TAHAP 2: RESET/UPDATE KATA SANDI BARU
if (isset($_POST['reset_password'])) {
    if (isset($_SESSION['reset_id_customer'])) {
        $id_customer = $_SESSION['reset_id_customer'];
        $new_pass = trim($_POST['new_password']);

        $sql_old = "SELECT Kata_Sandi FROM Customer WHERE ID_Customer = ?";
        $q_old = sqlsrv_query($conn, $sql_old, array($id_customer));
        $d_old = sqlsrv_fetch_array($q_old, SQLSRV_FETCH_ASSOC);
        $old_pass = $d_old['Kata_Sandi'] ?? '';

        if (empty($new_pass)) {
            $res_status = "error";
            $res_msg = "Kata sandi baru tidak boleh kosong!";
        } else if (strlen($new_pass) < 8) {
            $res_status = "error";
            $res_msg = "Kata sandi baru minimal harus berisi 8 karakter!";
        } else if ($new_pass === $old_pass) {
            $res_status = "error";
            $res_msg = "Kata sandi baru tidak boleh sama dengan kata sandi lama Anda!";
        } else {
            $sql = "UPDATE Customer SET Kata_Sandi = ? WHERE ID_Customer = ?";
            $stmt = sqlsrv_query($conn, $sql, array($new_pass, $id_customer));

            if ($stmt !== false) {
                unset($_SESSION['reset_id_customer']);
                $res_status = "success";
                $res_msg = "Kata Sandi Berhasil Diperbarui! Silakan Login Kembali.";
            } else {
                $res_status = "error";
                $res_msg = "Gagal memperbarui kata sandi di sistem database.";
            }
        }
    } else {
        $res_status = "error";
        $res_msg = "Sesi verifikasi telah kedaluwarsa. Silakan verifikasi ulang.";
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Kata Sandi | HoopBall BasketPro</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --orange: #FF5400;
            --orange-hover: #E04600;
            --dark-blue: #0F172A;
            --text-dark: #1E293B;
            --text-muted: #64748B;
            --border-color: #E2E8F0;
            --bg-light: #F8FAFC;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { background-color: #FFFFFF; color: var(--text-dark); overflow-x: hidden; }

        .auth-hero-wrapper {
            background: linear-gradient(rgba(0, 0, 0, 0.8), rgba(0, 0, 0, 0.85)), 
                url('../asset/image/login.png') no-repeat center center;
            background-size: cover;
            min-height: 100vh; 
            padding: 80px 8% 80px 8%; 
            display: grid;
            grid-template-columns: 1.12fr 1fr;
            gap: 50px;
            align-items: center;
            position: relative;
        }

        .auth-info {
            align-self: start;
            margin-top: 115px;
            animation: slideInLeft 0.7s cubic-bezier(0.22, 1, 0.36, 1) 0.2s both;
        }

        .auth-info h2 {
            font-size: 48px;
            font-weight: 900;
            color: #ffffff;
            line-height: 1.15;
            margin-bottom: 16px;
            animation: bounceIn 0.8s cubic-bezier(0.34, 1.56, 0.64, 1) 0.1s both;
        }
        .auth-info h2 span { color: var(--orange); }
        .auth-info .intro-p {
            font-size: 15px;
            color: #94A3B8;
            line-height: 1.6;
            margin-bottom: 48px;
            max-width: 500px;
        }

        .auth-card-container { display: flex; justify-content: flex-end; }
        .auth-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            width: 100%;
            max-width: 450px;
            padding: 44px 36px;
            box-shadow: 
                0 20px 50px rgba(0, 0, 0, 0.3),
                0 0 0 1px rgba(255, 255, 255, 0.2) inset,
                0 0 80px rgba(255, 84, 0, 0.08) inset;
            text-align: center;
            position: relative;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            transform-style: preserve-3d;
            animation: cardPopIn 0.8s cubic-bezier(0.34, 1.56, 0.64, 1) 0.3s both;
        }
        .auth-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(
                45deg,
                transparent 40%,
                rgba(255, 84, 0, 0.03) 50%,
                transparent 60%
            );
            animation: cardShine 4s ease-in-out infinite;
            pointer-events: none;
        }
        @keyframes cardShine {
            0% { transform: translateX(-100%) rotate(45deg); }
            100% { transform: translateX(100%) rotate(45deg); }
        }
        .auth-card:hover {
            transform: translateY(-5px) rotateX(2deg) rotateY(-2deg);
            box-shadow: 
                0 30px 60px rgba(0, 0, 0, 0.35),
                0 0 0 1px rgba(255, 255, 255, 0.3) inset,
                0 0 100px rgba(255, 84, 0, 0.12) inset;
        }
        .auth-card h3 { font-size: 28px; font-weight: 900; color: var(--dark-blue); margin-bottom: 6px; letter-spacing: -0.5px; }
        .auth-card .card-subtitle { font-size: 13px; color: var(--text-muted); margin-bottom: 32px; display: block; }

        .card-header-decoration {
            display: flex;
            justify-content: center;
            margin-bottom: 16px;
        }
        .card-icon-wrapper {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, var(--orange) 0%, #FF6B35 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 25px rgba(255, 84, 0, 0.3);
            animation: iconPulse 2s ease-in-out infinite;
            position: relative;
        }
        .card-icon-wrapper::after {
            content: '';
            position: absolute;
            inset: -4px;
            border-radius: 50%;
            border: 2px solid rgba(255, 84, 0, 0.2);
            animation: ringExpand 2s ease-out infinite;
        }
        .card-icon-wrapper i {
            font-size: 28px;
            color: #fff;
            animation: ballSpin 3s linear infinite;
        }
        @keyframes iconPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        @keyframes ringExpand {
            0% { transform: scale(1); opacity: 1; }
            100% { transform: scale(1.5); opacity: 0; }
        }
        @keyframes ballSpin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .input-group { 
            margin-bottom: 24px; 
            text-align: left; 
            transition: all 0.3s ease;
            position: relative;
        }
        .input-group label { 
            font-size: 12px; 
            font-weight: 700; 
            color: var(--text-dark); 
            margin-bottom: 8px; 
            display: block; 
            transition: all 0.3s ease;
            position: relative;
        }
        .input-group label::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--orange);
            transition: width 0.3s ease;
        }
        .input-group:focus-within label { 
            color: var(--orange);
            transform: translateX(4px);
        }
        .input-group:focus-within label::after {
            width: 30px;
        }
        .input-group:focus-within .input-wrapper i.icon-left { 
            color: var(--orange);
            transform: scale(1.2);
        }
        .input-wrapper { 
            position: relative;
            transition: all 0.3s ease;
        }
        .input-wrapper i.icon-left {
            position: absolute; left: 16px; top: 50%; transform: translateY(-50%);
            color: #94A3B8; font-size: 14px; pointer-events: none; 
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .input-wrapper input {
            width: 100%; padding: 14px 44px 14px 44px; 
            background: linear-gradient(135deg, #FFFFFF 0%, #FAFBFC 100%);
            border: 2px solid var(--border-color); 
            color: var(--text-dark);
            border-radius: 14px; 
            font-size: 14px; 
            outline: none; 
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .input-wrapper input:focus { 
            border-color: var(--orange); 
            box-shadow: 
                0 0 0 4px rgba(255, 84, 0, 0.08),
                0 4px 20px rgba(255, 84, 0, 0.1);
            transform: translateY(-2px);
            background: #FFFFFF;
        }
        .input-wrapper input::placeholder {
            color: #CBD5E1;
            transition: all 0.3s ease;
        }
        .input-wrapper input:focus::placeholder {
            color: #E2E8F0;
            transform: translateX(5px);
        }
        .input-wrapper i.icon-right {
            position: absolute; right: 16px; top: 50%; transform: translateY(-50%);
            color: #94A3B8; font-size: 14px; cursor: pointer; padding: 4px;
            transition: all 0.3s ease;
            border-radius: 50%;
        }
        .input-wrapper i.icon-right:hover {
            color: var(--orange);
            background: rgba(255, 84, 0, 0.08);
            transform: translateY(-50%) scale(1.1);
        }

        .input-wrapper.error input { border-color: #EF4444 !important; background-color: #FEF2F2 !important; }
        .input-wrapper.error i.icon-left { color: #EF4444 !important; }
        .input-group.error-active label { color: #EF4444 !important; }
        .error-text { font-size: 11px; color: #EF4444; font-weight: 600; margin-top: 6px; display: none; animation: fadeInError 0.2s ease-out; }
        @keyframes fadeInError { from { opacity: 0; transform: translateY(-4px); } to { opacity: 1; transform: translateY(0); } }

        .btn-submit {
            width: 100%; padding: 16px; 
            background: linear-gradient(135deg, var(--orange) 0%, #FF6B35 50%, var(--orange) 100%);
            background-size: 200% 200%;
            color: #ffffff;
            border: none; 
            border-radius: 14px; 
            font-weight: 800; 
            font-size: 15px;
            cursor: pointer; 
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            position: relative;
            overflow: hidden;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .btn-submit::before {
            content: '';
            position: absolute;
            top: 0; left: -100%;
            width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.6s ease;
        }
        .btn-submit:hover::before {
            left: 100%;
        }
        .btn-submit:hover { 
            background-position: 100% 0;
            transform: translateY(-3px) scale(1.02); 
            box-shadow: 
                0 12px 30px rgba(255, 84, 0, 0.4),
                0 0 40px rgba(255, 84, 0, 0.15);
        }
        .btn-submit:active {
            transform: translateY(-1px) scale(0.98);
        }
        .btn-submit::after {
            content: '';
            position: absolute;
            top: 50%; left: 50%;
            width: 0; height: 0;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: width 0.6s ease, height 0.6s ease;
        }
        .btn-submit:active::after {
            width: 300px; height: 300px;
        }

        .card-footer { 
            margin-top: 24px; 
            font-size: 13px; 
            color: var(--text-muted); 
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
            position: relative;
        }
        .card-footer::before {
            content: '';
            position: absolute;
            top: -1px;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--orange), transparent);
        }
        .card-footer a { 
            color: var(--orange); 
            text-decoration: none; 
            font-weight: 800;
            position: relative;
            transition: all 0.3s ease;
        }
        .card-footer a::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--orange);
            transition: width 0.3s ease;
        }
        .card-footer a:hover { 
            color: var(--orange-hover);
        }
        .card-footer a:hover::after {
            width: 100%;
        }

        .features-bar {
            padding: 40px 8%; background: #FFFFFF;
            display: grid; grid-template-columns: repeat(3, 1fr); gap: 30px;
            border-bottom: 1px solid var(--border-color);
        }
        .feat-bar-item {
            display: flex; align-items: center; gap: 16px; padding: 12px 16px;
            border-radius: 12px; cursor: pointer; transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .feat-bar-item:hover { transform: translateY(-5px); background-color: var(--bg-light); }
        .feat-bar-icon {
            width: 52px; height: 52px; background: #FFF0E9; border-radius: 50%;
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
            color: var(--orange); font-size: 18px; transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .feat-bar-item:hover .feat-bar-icon {
            background: var(--orange); color: #ffffff; transform: scale(1.08);
            box-shadow: 0 8px 16px rgba(255, 84, 0, 0.2);
        }
        .feat-bar-text h4 { font-size: 14px; font-weight: 700; color: var(--dark-blue); margin-bottom: 2px; transition: color 0.3s ease; }
        .feat-bar-item:hover .feat-bar-text h4 { color: var(--orange); }
        .feat-bar-text p { font-size: 12px; color: var(--text-muted); }

        .feat-bar-item:nth-child(1) { animation: slideUp 0.5s ease-out 1s both; }
        .feat-bar-item:nth-child(2) { animation: slideUp 0.5s ease-out 1.15s both; }
        .feat-bar-item:nth-child(3) { animation: slideUp 0.5s ease-out 1.3s both; }

        footer { background: #0F172A; color: #94A3B8; padding: 80px 8% 40px 8%; animation: fadeIn 0.6s ease-out 1.2s both; }
        .footer-grid { display: grid; grid-template-columns: 1.5fr 1fr 1fr 1fr; gap: 40px; margin-bottom: 60px; }
        .footer-brand .logo {
            color: white; margin-bottom: 20px; font-size: 24px; font-weight: 800;
            display: flex; align-items: center; gap: 8px; text-decoration: none;
        }
        .footer-brand .logo span { color: var(--orange); }
        .footer-brand p { font-size: 13px; line-height: 1.6; margin-bottom: 24px; }
        .social-links { display: flex; gap: 16px; }
        .social-links a {
            width: 36px; height: 36px; background: #1E293B; border-radius: 50%;
            display: flex; align-items: center; justify-content: center; color: white;
            text-decoration: none !important; transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .social-links a:hover { background: var(--orange); transform: translateY(-5px) scale(1.1); }
        .footer-col h4 { color: white; font-size: 15px; font-weight: 700; margin-bottom: 24px; }
        .footer-links { list-style: none; display: flex; flex-direction: column; gap: 12px; }
        .footer-links li a { color: #94A3B8; font-size: 13px; text-decoration: none; transition: all 0.3s ease; }
        .footer-links li a:hover { color: white; }
        .footer-contact-info { display: flex; flex-direction: column; gap: 16px; }
        .contact-item { display: flex; gap: 12px; font-size: 13px; }
        .contact-item i { color: var(--orange); margin-top: 3px; }
        .footer-bottom { padding-top: 40px; border-top: 1px solid #1E293B; text-align: center; font-size: 12px; }

        .btn-close-auth {
            position: absolute; top: 30px; left: 40px;
            width: 44px; height: 44px; background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.1);
            color: #ffffff; border-radius: 50%; display: flex;
            align-items: center; justify-content: center; font-size: 18px;
            text-decoration: none; z-index: 100; cursor: pointer;
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            animation: pulseGlow 2s ease-in-out infinite;
        }
        .btn-close-auth:hover {
            background: var(--orange); border-color: var(--orange); color: #ffffff;
            transform: scale(1.08) rotate(90deg); box-shadow: 0 8px 20px rgba(255, 84, 0, 0.3);
        }

        .input-wrapper input[type="date"] {
            padding: 14px 44px 14px 44px;
            font-family: 'Plus Jakarta Sans', sans-serif;
            position: relative;
            z-index: 1;
        }
        .input-wrapper input[type="date"]::-webkit-calendar-picker-indicator {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            width: 18px;
            height: 18px;
            margin: 0;
            padding: 0;
            cursor: pointer;
            opacity: 1;
            filter: invert(0.4);
            z-index: 4;
        }

        /* Animations */
        @keyframes cardPopIn {
            0% { opacity: 0; transform: scale(0.8) translateY(40px); }
            100% { opacity: 1; transform: scale(1) translateY(0); }
        }
        @keyframes slideInLeft {
            0% { opacity: 0; transform: translateX(-50px); }
            100% { opacity: 1; transform: translateX(0); }
        }
        @keyframes fadeInUp {
            0% { opacity: 0; transform: translateY(20px); }
            100% { opacity: 1; transform: translateY(0); }
        }
        @keyframes slideUp {
            0% { opacity: 0; transform: translateY(30px); }
            100% { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeIn {
            0% { opacity: 0; }
            100% { opacity: 1; }
        }
        @keyframes bounceIn {
            0% { opacity: 0; transform: scale(0.3); }
            50% { transform: scale(1.05); }
            70% { transform: scale(0.9); }
            100% { opacity: 1; transform: scale(1); }
        }
        @keyframes pulseGlow {
            0%, 100% { box-shadow: 0 0 0 0 rgba(255, 84, 0, 0); }
            50% { box-shadow: 0 0 0 8px rgba(255, 84, 0, 0.15); }
        }
        .shake { animation: shake 0.5s cubic-bezier(.36,.07,.19,.97) both; }
        @keyframes shake {
            10%, 90% { transform: translate3d(-1px, 0, 0); }
            20%, 80% { transform: translate3d(2px, 0, 0); }
            30%, 50%, 70% { transform: translate3d(-4px, 0, 0); }
            40%, 60% { transform: translate3d(4px, 0, 0); }
        }

        /* Floating basketball particles */
        .floating-balls {
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: 1;
            overflow: hidden;
        }
        .float-ball {
            position: absolute;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: linear-gradient(135deg, #FF6B35, #CC3700);
            opacity: 0.08;
            animation: floatBall 15s ease-in-out infinite;
        }
        .float-ball::before {
            content: '';
            position: absolute;
            inset: 2px;
            border-radius: 50%;
            border: 1px solid rgba(0,0,0,0.2);
        }
        .float-ball:nth-child(1) { left: 10%; top: 20%; animation-delay: 0s; width: 30px; height: 30px; }
        .float-ball:nth-child(2) { left: 85%; top: 15%; animation-delay: 2s; width: 15px; height: 15px; }
        .float-ball:nth-child(3) { left: 70%; top: 60%; animation-delay: 4s; width: 25px; height: 25px; }
        .float-ball:nth-child(4) { left: 20%; top: 70%; animation-delay: 6s; width: 18px; height: 18px; }
        .float-ball:nth-child(5) { left: 50%; top: 40%; animation-delay: 8s; width: 22px; height: 22px; }
        .float-ball:nth-child(6) { left: 90%; top: 80%; animation-delay: 10s; width: 28px; height: 28px; }
        .float-ball:nth-child(7) { left: 5%; top: 50%; animation-delay: 12s; width: 16px; height: 16px; }
        .float-ball:nth-child(8) { left: 40%; top: 10%; animation-delay: 14s; width: 20px; height: 20px; }

        @keyframes floatBall {
            0%, 100% { transform: translateY(0) rotate(0deg) scale(1); }
            25% { transform: translateY(-30px) rotate(90deg) scale(1.1); }
            50% { transform: translateY(-15px) rotate(180deg) scale(0.95); }
            75% { transform: translateY(-40px) rotate(270deg) scale(1.05); }
        }

        body.swal2-shown, html.swal2-shown { padding-right: 0px !important; }

        @media (max-width: 992px) {
            .auth-hero-wrapper { grid-template-columns: 1fr; padding-top: 140px; text-align: center; }
            .auth-info .intro-p { margin: 0 auto 40px auto; }
            .auth-card-container { justify-content: center; }
            .features-bar { grid-template-columns: 1fr; gap: 20px; }
            .footer-grid { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 576px) { .footer-grid { grid-template-columns: 1fr; } }

        html { scrollbar-width: none; -ms-overflow-style: none; }
        html::-webkit-scrollbar { display: none; }
    </style>
</head>

<body>

<!-- Floating basketball particles -->
<div class="floating-balls">
    <div class="float-ball"></div>
    <div class="float-ball"></div>
    <div class="float-ball"></div>
    <div class="float-ball"></div>
    <div class="float-ball"></div>
    <div class="float-ball"></div>
    <div class="float-ball"></div>
    <div class="float-ball"></div>
</div>

    <!-- TOMBOL SILANG (X) KEMBALI KE LOGIN -->
    <a href="login.php" class="btn-close-auth" title="Batal & Kembali">
        <i class="fa-solid fa-xmark"></i>
    </a>

    <!-- AUTH HERO SECTION -->
    <div class="auth-hero-wrapper">
        <div class="auth-info">
            <h2>Atur Ulang<br><span>Kata Sandi Anda</span></h2>
            <p class="intro-p">Verifikasi identitas kepemilikan akun terdaftar Anda terlebih dahulu untuk membuat kata
                sandi baru.</p>
        </div>

        <div class="auth-card-container">
            <div class="auth-card">
                <div class="card-header-decoration">
                    <div class="card-icon-wrapper">
                        <i class="fa-solid fa-key"></i>
                    </div>
                </div>
                <h3>Lupa Kata Sandi</h3>

                <?php if (!$is_verified): ?>
                    <span class="card-subtitle">Silakan isi data keamanan akun Anda.</span>
                    <form method="POST" id="verifyForm" novalidate>
                        <!-- USERNAME -->
                        <div class="input-group">
                            <label>Nama Pengguna Terdaftar</label>
                            <div class="input-wrapper">
                                <i class="fa-solid fa-signature icon-left"></i>
                                <input type="text" name="username_input" id="usernameField" placeholder="budi_hoops" value="<?= htmlspecialchars($username_input) ?>">
                            </div>
                            <span class="error-text" id="usernameError"></span>
                        </div>

                        <!-- TANGGAL BOOKING TERAKHIR -->
                        <div class="input-group">
                            <label>Tanggal Booking Terakhir</label>
                            <div class="input-wrapper">
                                <i class="fa-regular fa-calendar-days icon-left"></i>
                                <input type="date" name="tanggal_input" id="tanggalField" value="<?= htmlspecialchars($tanggal_input) ?>">
                            </div>
                            <span class="error-text" id="tanggalError"></span>
                        </div>

                        <!-- NOMINAL PEMBAYARAN TERAKHIR -->
                        <div class="input-group">
                            <label>Nominal Pembayaran Terakhir (Rupiah)</label>
                            <div class="input-wrapper">
                                <i class="fa-solid fa-money-bill-wave icon-left"></i>
                                <input type="text" name="nominal_input" id="nominalField" placeholder="Contoh: 150000" maxlength="10" value="<?= htmlspecialchars($nominal_input) ?>">
                            </div>
                            <span class="error-text" id="nominalError"></span>
                        </div>

                        <button type="submit" name="verify_account" class="btn-submit" style="margin-top: 10px;">Verifikasi Akun</button>
                        <p class="card-footer">Kembali ke halaman <a href="login.php">Masuk</a></p>
                    </form>
                <?php else: ?>
                    <span class="card-subtitle" style="color:var(--orange);"><b>Akun Terverifikasi!</b> Tulis Kata Sandi
                        baru.</span>
                    <form method="POST" id="resetForm" novalidate>
                        <div class="input-group">
                            <label>Kata Sandi Baru</label>
                            <div class="input-wrapper">
                                <i class="fa-solid fa-lock icon-left"></i>
                                <input type="password" name="new_password" id="passwordInput"
                                    placeholder="Min. 8 Karakter (Huruf & Angka)" maxlength="50">
                                <i class="fa-solid fa-eye icon-right" id="togglePass" onclick="togglePassword()"></i>
                            </div>
                            <span class="error-text" id="passwordError"></span>
                        </div>

                        <div class="input-group">
                            <label>Konfirmasi Kata Sandi Baru</label>
                            <div class="input-wrapper">
                                <i class="fa-solid fa-lock icon-left"></i>
                                <input type="password" name="password_confirm" id="passwordConfirmInput"
                                    placeholder="Ulangi Kata Sandi" maxlength="50">
                                <i class="fa-solid fa-eye icon-right" id="toggleConfirmPass"
                                    onclick="toggleConfirmPassword()"></i>
                            </div>
                            <span class="error-text" id="passwordConfirmError"></span>
                        </div>

                        <button type="submit" name="reset_password" class="btn-submit" style="margin-top: 30px;">Reset Kata
                            Sandi</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <section class="features-bar">
        <div class="feat-bar-item">
            <div class="feat-bar-icon"><i class="fa-regular fa-circle-check"></i></div>
            <div class="feat-bar-text">
                <h4>Aman & Terpercaya</h4>
                <p>Data pribadi Anda aman dan kami lindungi.</p>
            </div>
        </div>
        <div class="feat-bar-item">
            <div class="feat-bar-icon"><i class="fa-regular fa-calendar"></i></div>
            <div class="feat-bar-text">
                <h4>Kelola dengan Mudah</h4>
                <p>Riwayat booking dan jadwal tersimpan rapi.</p>
            </div>
        </div>
        <div class="feat-bar-item">
            <div class="feat-bar-icon"><i class="fa-regular fa-star"></i></div>
            <div class="feat-bar-text">
                <h4>Pengalaman Premium</h4>
                <p>Nikmati fitur eksklusif khusus untuk member.</p>
            </div>
        </div>
    </section>

    <footer id="tentang-kami">
        <div class="footer-grid">
            <div class="footer-brand">
                <a href="#" class="logo"><i class="fa-solid fa-basketball"
                        style="color: var(--orange)"></i>Hoop<span>Ball</span></a>
                <p>Platform penyewaan lapangan basket online yang mudah, cepat, dan terpercaya.</p>
                <div class="social-links">
                    <a href="#"><i class="fa-brands fa-instagram"></i></a>
                    <a href="#"><i class="fa-brands fa-facebook"></i></a>
                    <a href="#"><i class="fa-brands fa-youtube"></i></a>
                    <a href="#"><i class="fa-brands fa-tiktok"></i></a>
                </div>
            </div>
            <div class="footer-col">
                <h4>Kontak Kami</h4>
                <div class="footer-contact-info">
                    <div class="contact-item">
                        <i class="fa-solid fa-phone"></i>
                        <span>0812-3456-7890</span>
                    </div>
                    <div class="contact-item">
                        <i class="fa-solid fa-envelope"></i>
                        <span>info@hoopball.id</span>
                    </div>
                    <div class="contact-item">
                        <i class="fa-solid fa-location-dot"></i>
                        <span>Jl. Sunset Road No. 123, Jakarta Selatan, 12050</span>
                    </div>
                </div>
            </div>
            <div class="footer-col">
                <h4>Tautan</h4>
                <ul class="footer-links">
                    <li><a href="../index.php#lapangan">Lapangan</a></li>
                    <li><a href="../index.php#jadwal">Jadwal</a></li>
                    <li><a href="../index.php#member">Alat Basket</a></li>
                    <li><a href="../index.php#tentang-kami">Tentang Kami</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Informasi</h4>
                <ul class="footer-links">
                    <li><a href="#">Cara Pemesanan</a></li>
                    <li><a href="#">Syarat & Ketentuan</a></li>
                    <li><a href="#">Kebijakan Privasi</a></li>
                    <li><a href="#">FAQ</a></li>
                    <li><a href="#">Hubungi Kami</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2024 HoopBall. All rights reserved.</p>
        </div>
    </footer>

    <!-- RESPONSE SWEETALERT -->
    <script>
        <?php if ($res_status !== ""): ?>
            Swal.fire({
                icon: '<?= $res_status ?>',
                title: '<?= ($res_status == "success") ? "Berhasil!" : "Gagal!" ?>',
                text: '<?= $res_msg ?>',
                background: '#ffffff',
                color: '#1e293b',
                confirmButtonColor: '#FF5400'
            }).then(() => {
                <?php if ($res_status == "success"): ?>
                    window.location.href = 'login.php';
                <?php endif; ?>
            });
        <?php endif; ?>
    </script>

    <!-- VALIDASI JAVASCRIPT & EVENT HANDLERS -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {

            function setValidationError(inputEl, errorEl, message) {
                inputEl.parentElement.classList.add('error');
                inputEl.parentElement.parentElement.classList.add('error-active');
                errorEl.textContent = message;
                errorEl.style.display = 'block';
            }

            function clearValidationError(inputEl, errorEl) {
                inputEl.parentElement.classList.remove('error');
                inputEl.parentElement.parentElement.classList.remove('error-active');
                errorEl.style.display = 'none';
            }

            const verifyForm = document.getElementById('verifyForm');
            if (verifyForm) {
                const username = document.getElementById('usernameField');
                const tanggal = document.getElementById('tanggalField');
                const nominal = document.getElementById('nominalField');

                const usernameError = document.getElementById('usernameError');
                const tanggalError = document.getElementById('tanggalError');
                const nominalError = document.getElementById('nominalError');

                nominal.addEventListener('input', () => {
                    nominal.value = nominal.value.replace(/[^0-9]/g, '');
                });

                verifyForm.addEventListener('submit', function (e) {
                    let isValid = true;

                    const usernameVal = username.value.trim();
                    const usernamePattern = /^[a-zA-Z0-9\._]+$/;

                    if (usernameVal === '') {
                        setValidationError(username, usernameError, 'Nama Pengguna wajib diisi.');
                        isValid = false;
                    } else if (usernameVal.length < 3 || usernameVal.length > 30) {
                        setValidationError(username, usernameError, 'Nama Pengguna minimal 3 karakter dan maksimal 30 karakter.');
                        isValid = false;
                    } else if (username.value.includes(' ')) {
                        setValidationError(username, usernameError, 'Nama Pengguna tidak boleh menggunakan spasi.');
                        isValid = false;
                    } else if (!usernamePattern.test(usernameVal)) {
                        setValidationError(username, usernameError, 'Nama Pengguna hanya boleh berisi huruf, angka, titik (.), dan underscore (_).');
                        isValid = false;
                    } else {
                        clearValidationError(username, usernameError);
                    }

                    if (tanggal.value === '') {
                        setValidationError(tanggal, tanggalError, 'Tanggal booking terakhir wajib diisi.');
                        isValid = false;
                    } else {
                        clearValidationError(tanggal, tanggalError);
                    }

                    const nominalVal = nominal.value.trim();
                    if (nominalVal === '') {
                        setValidationError(nominal, nominalError, 'Nominal pembayaran terakhir wajib diisi.');
                        isValid = false;
                    } else {
                        clearValidationError(nominal, nominalError);
                    }

                    if (!isValid) {
                        e.preventDefault();
                        const card = document.querySelector('.auth-card');
                        card.classList.remove('shake');
                        void card.offsetWidth;
                        card.classList.add('shake');
                    }
                });

                const fieldsVerify = [
                    { el: username, err: usernameError },
                    { el: tanggal, err: tanggalError },
                    { el: nominal, err: nominalError }
                ];
                fieldsVerify.forEach(field => {
                    field.el.addEventListener('input', () => {
                        clearValidationError(field.el, field.err);
                    });
                });
            }

            const resetForm = document.getElementById('resetForm');
            if (resetForm) {
                const password = document.getElementById('passwordInput');
                const passwordConfirm = document.getElementById('passwordConfirmInput');

                const passwordError = document.getElementById('passwordError');
                const passwordConfirmError = document.getElementById('passwordConfirmError');

                resetForm.addEventListener('submit', function (e) {
                    let isValid = true;
                    const hasLetter = /[a-zA-Z]/;
                    const hasNumber = /[0-9]/;
                    const simplePasswords = ['12345678', '87654321', 'password', 'qwertyui', '1234567890', 'password123'];

                    const passwordVal = password.value.trim();
                    if (passwordVal === '') {
                        setValidationError(password, passwordError, 'Kata sandi baru wajib diisi.');
                        isValid = false;
                    } else if (passwordVal.length < 8 || passwordVal.length > 50) {
                        setValidationError(password, passwordError, 'Kata sandi baru minimal 8 karakter dan maksimal 50 karakter.');
                        isValid = false;
                    } else if (!hasLetter.test(passwordVal) || !hasNumber.test(passwordVal)) {
                        setValidationError(password, passwordError, 'Kata sandi baru harus berisi kombinasi huruf dan angka.');
                        isValid = false;
                    } else if (simplePasswords.includes(passwordVal.toLowerCase())) {
                        setValidationError(password, passwordError, 'Kata sandi terlalu mudah ditebak (seperti 12345678 atau password123). Gunakan kombinasi lain.');
                        isValid = false;
                    } else {
                        clearValidationError(password, passwordError);
                    }

                    const passwordConfirmVal = passwordConfirm.value.trim();
                    if (passwordConfirmVal === '') {
                        setValidationError(passwordConfirm, passwordConfirmError, 'Konfirmasi kata sandi wajib diisi.');
                        isValid = false;
                    } else if (passwordConfirmVal !== passwordVal) {
                        setValidationError(passwordConfirm, passwordConfirmError, 'Konfirmasi kata sandi tidak cocok.');
                        isValid = false;
                    } else {
                        clearValidationError(passwordConfirm, passwordConfirmError);
                    }

                    if (!isValid) {
                        e.preventDefault();
                        const card = document.querySelector('.auth-card');
                        card.classList.remove('shake');
                        void card.offsetWidth;
                        card.classList.add('shake');
                    }
                });

                const fieldsReset = [
                    { el: password, err: passwordError },
                    { el: passwordConfirm, err: passwordConfirmError }
                ];
                fieldsReset.forEach(field => {
                    field.el.addEventListener('input', () => {
                        clearValidationError(field.el, field.err);
                    });
                });
            }
        });

        function togglePassword() {
            const passInput = document.getElementById('passwordInput');
            const toggleIcon = document.getElementById('togglePass');

            if (passInput.type === 'password') {
                passInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        function toggleConfirmPassword() {
            const passInput = document.getElementById('passwordConfirmInput');
            const toggleIcon = document.getElementById('toggleConfirmPass');

            if (passInput.type === 'password') {
                passInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
    </script>
</body>

</html>