<?php
// Menggunakan __DIR__ agar path file PHPMailer selalu tepat dan tidak terpengaruh lokasi pemanggilan
require_once __DIR__ . '/../PHPMailer-master/src/Exception.php';
require_once __DIR__ . '/../PHPMailer-master/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer-master/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;  

/**
 * Fungsi untuk mengirim email OTP
 * 
 * @param string $toEmail Email penerima
 * @param string $otpCode Kode OTP 6 digit
 * @return bool True jika berhasil, False jika gagal
 */
function sendOtpEmail($toEmail, $otpCode) {
    $mail = new PHPMailer(true);

    try {
        // Konfigurasi SMTP
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';                     
        $mail->SMTPAuth   = true;
        $mail->Username   = 'hoopball9@gmail.com';             // Email pengirim Anda
        $mail->Password   = 'obna felj nlhs beui';              // GANTI DENGAN 16 KARAKTER SANDI APLIKASI GOOGLE ANDA (bukan HoopBall123)
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Penerima
        $mail->setFrom('hoopball9@gmail.com', 'HoopBall BasketPro'); // SUDAH DISUAIKAN: Menggunakan email hoopball9@gmail.com
        $mail->addAddress($toEmail);                                 // Alamat penerima otomatis dari database

        // Konten Email
        $mail->isHTML(true);
        $mail->Subject = 'Kode OTP Atur Ulang Kata Sandi - HoopBall BasketPro';
        $mail->Body    = "
            <div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px; max-width: 500px;'>
                <h2 style='color: #FF5400;'>Atur Ulang Kata Sandi</h2>
                <p>Halo,</p>
                <p>Kami menerima permintaan untuk mengatur ulang kata sandi akun HoopBall BasketPro Anda.</p>
                <p>Gunakan kode OTP berikut untuk melanjutkan proses verifikasi Anda:</p>
                <div style='background-color: #f8fafc; padding: 15px; margin: 20px 0; text-align: center; font-size: 28px; font-weight: bold; letter-spacing: 5px; color: #1e293b; border-radius: 6px; border: 1px dashed #cbd5e1;'>
                    $otpCode
                </div>
                <p style='color: #ef4444; font-size: 13px;'>*Kode OTP ini berlaku selama 5 menit. Jangan bagikan kode ini kepada siapa pun.</p>
                <p style='font-size: 13px; color: #64748b; margin-top: 25px;'>Jika Anda tidak meminta perubahan kata sandi ini, silakan abaikan email ini dengan aman.</p>
                <hr style='border: none; border-top: 1px solid #e2e8f0; margin: 20px 0;'>
                <p style='font-size: 11px; color: #94a3b8; text-align: center;'>HoopBall BasketPro &copy; " . date('Y') . "</p>
            </div>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        // Jika gagal kirim, Anda dapat melihat detail error dengan mengaktifkan baris di bawah ini untuk kebutuhan perbaikan:
        // error_log($mail->ErrorInfo);
        return false;
    }
}