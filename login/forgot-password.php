<?php
session_start();
$path_prefix = "../";
include '../includes/config.php';
include '../includes/mail_helper.php';

$res_status = $_SESSION['res_status'] ?? "";
$res_msg = $_SESSION['res_msg'] ?? "";
unset($_SESSION['res_status'], $_SESSION['res_msg']);

// JIKA USER SECARA EKSPLISIT MEMINTA RESET TAMPILAN (MISAL KLIK BATAL ATAU AKSES BARU)
if (isset($_GET['clean'])) {
    unset($_SESSION['simulated_otp']);
    unset($_SESSION['temp_customer_id']);
    unset($_SESSION['temp_customer_email']);
    unset($_SESSION['otp_expiry']);
    unset($_SESSION['reset_id_customer']);

    // Alihkan ke URL bersih tanpa parameter ?clean
    header("Location: forgot-password.php");
    exit();
}

$redirect_to_login = false;

// 1. Jalankan pembersihan sesi terlebih dahulu (jika akses GET segar)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_SESSION['simulated_otp'])) {
    unset($_SESSION['simulated_otp'], $_SESSION['temp_customer_id'], $_SESSION['temp_customer_email'], $_SESSION['otp_expiry'], $_SESSION['reset_id_customer']);
}

// 2. Baru tentukan status aktifnya setelah sesi dipastikan bersih
$is_verified = isset($_SESSION['reset_id_customer']);
$otp_sent = isset($_SESSION['simulated_otp']);

// TAHAP 1A: MEMINTA OTP BERDASARKAN EMAIL (CEK DATABASE)
if (isset($_POST['request_otp'])) {
    $email_input = trim($_POST['email_input'] ?? '');

    $sql = "SELECT ID_Customer, Email FROM Customer WHERE Email = ? AND Is_Deleted = 0";
    $params = array($email_input);
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        $res_status = "error";
        $res_msg = "Terjadi kesalahan sistem koneksi database.";
    } else {
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        if ($row) {
            // 1. Generate kode OTP acak 6 digit yang dinamis
            $otp_code = strval(random_int(100000, 999999));
            // 2. Panggil fungsi pengiriman email dari mail_helper.php
            $kirim_email = sendOtpEmail($row['Email'], $otp_code);

            if ($kirim_email) {
                // Sesi hanya akan disimpan jika email berhasil terkirim
                $_SESSION['simulated_otp'] = $otp_code;
                $_SESSION['temp_customer_id'] = $row['ID_Customer'];
                $_SESSION['temp_customer_email'] = $row['Email'];
                $_SESSION['otp_expiry'] = time() + (5 * 60); // Berlaku selama 5 menit

                $_SESSION['res_status'] = "success";
                $_SESSION['res_msg'] = "Kode OTP telah dikirim ke email " . htmlspecialchars($row['Email']);

                // Alihkan halaman agar siklus POST berakhir
                header("Location: forgot-password.php");
                exit();
            } else {
                // Tampilkan pesan kegagalan jika server SMTP gagal mengirim email
                $res_status = "error";
                $res_msg = "Gagal mengirimkan email verifikasi. Silakan periksa koneksi internet Anda atau coba lagi nanti.";
            }
        } else {
            // Email tidak terdaftar
            $res_status = "error";
            $res_msg = "Alamat email tidak terdaftar.";
        }
    }
}

// TAHAP 1B: VERIFIKASI INPUT KODE OTP
if (isset($_POST['verify_otp'])) {
    $otp_input = trim($_POST['otp_input'] ?? '');

    if (isset($_SESSION['simulated_otp']) && isset($_SESSION['temp_customer_id'])) {
        if (time() > $_SESSION['otp_expiry']) {
            $res_status = "error";
            $res_msg = "Kode OTP telah kedaluwarsa. Silakan ajukan kembali.";
            unset($_SESSION['simulated_otp'], $_SESSION['temp_customer_id'], $_SESSION['temp_customer_email'], $_SESSION['otp_expiry']);
            $otp_sent = false;
        } else if ($otp_input === $_SESSION['simulated_otp']) {
            $is_verified = true;
            $_SESSION['reset_id_customer'] = $_SESSION['temp_customer_id'];

            // Hapus sesi OTP sementara
            unset($_SESSION['simulated_otp'], $_SESSION['temp_customer_id'], $_SESSION['temp_customer_email'], $_SESSION['otp_expiry']);

            $res_status = "success";
            $res_msg = "Verifikasi berhasil! Silakan tentukan kata sandi baru Anda.";
        } else {
            $res_status = "error";
            $res_msg = "Kode OTP yang Anda masukkan salah.";
        }
    }
}


// TAHAP 2: RESET/UPDATE KATA SANDI BARU
if (isset($_POST['reset_password'])) {
    if (isset($_SESSION['reset_id_customer'])) {
        $id_customer = $_SESSION['reset_id_customer'];
        $new_pass = trim($_POST['new_password']);

        // Query ke database untuk mengambil kata sandi lama telah dihapus dari sini

        if (empty($new_pass)) {
            $res_status = "error";
            $res_msg = "Kata sandi baru tidak boleh kosong!";
        } else if (strlen($new_pass) < 8) {
            $res_status = "error";
            $res_msg = "Kata sandi baru minimal harus berisi 8 karakter!";
        } else {
            // Validasi password_verify dengan kata sandi lama telah dihapus

            // Enkripsi kata sandi baru menggunakan Argon2id
            $hashed_new_pass = password_hash($new_pass, PASSWORD_ARGON2ID);

            $sql = "UPDATE Customer SET Kata_Sandi = ? WHERE ID_Customer = ?";
            $stmt = sqlsrv_query($conn, $sql, array($hashed_new_pass, $id_customer));

            if ($stmt !== false) {
                unset($_SESSION['reset_id_customer']);
                $res_status = "success";
                $res_msg = "Kata Sandi Berhasil Diperbarui! Silakan Login Kembali.";
                $redirect_to_login = true;
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
    <?php include '../includes/favicon.php'; ?>
    <title>Lupa Kata Sandi | HoopBall BasketPro</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="tampilan.css">
    <link rel="stylesheet" href="../asset/css/navbar_footer.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .auth-info {
            align-self: start !important;
            /* <-- Menempelkan posisi ke bagian atas */
            margin-top: 150px !important;
            /* <-- Mengatur jarak turunnya dari atas agar pas */
        }


        @media (max-width: 992px) {
            .auth-hero-wrapper {
                grid-template-columns: 1fr;
                padding-top: 140px;
                text-align: center;
            }

            .footer-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 576px) {
            .footer-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

    <?php include 'floating_balls.php'; ?>

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

                    <?php if (!$otp_sent): ?>
                        <!-- FORM TAHAP 1: INPUT EMAIL -->
                        <span class="card-subtitle">Masukkan alamat email Anda yang terdaftar.</span>
                        <form method="POST" id="requestOtpForm" novalidate>
                            <div class="input-group">
                                <label>Email Terdaftar</label>
                                <div class="input-wrapper">
                                    <i class="fa-solid fa-envelope icon-left"></i>
                                    <!-- Tambahkan id="emailField" di bawah ini -->
                                    <input type="text" name="email_input" id="emailField" placeholder="contoh: dimas@mail.com"
                                        required>
                                </div>
                                <!-- TAMBAHKAN ELEMEN ERROR TEXT INI -->
                                <span class="error-text" id="emailError" style="display: none;"></span>
                            </div>
                            <button type="submit" name="request_otp" class="btn-submit" style="margin-top: 10px;">Kirim
                                OTP</button>
                            <p class="card-footer">Kembali ke halaman <a href="login.php">Masuk</a></p>
                        </form>
                    <?php else: ?>
                        <!-- FORM TAHAP 2: INPUT KODE OTP -->
                        <?php
                        $display_email = $_SESSION['temp_customer_email'] ?? 'email terdaftar';
                        ?>
                        <span class="card-subtitle" style="color:var(--orange);">Kode OTP telah dikirim ke email
                            <b><?= htmlspecialchars($display_email) ?></b></span>
                        <form method="POST" id="verifyOtpForm" novalidate>
                            <div class="input-group">
                                <label>Masukkan Kode OTP</label>
                                <div class="input-wrapper">
                                    <i class="fa-solid fa-shield-halved icon-left"></i>
                                    <input type="text" name="otp_input" id="otpField" maxlength="6" placeholder="6 Digit Angka"
                                        required style="text-align: center; letter-spacing: 5px; font-weight: bold;">
                                </div>
                                <span class="error-text" id="otpError" style="display: none;"></span>
                            </div>
                            <button type="submit" name="verify_otp" class="btn-submit" style="margin-top: 10px;">Verifikasi
                                OTP</button>
                            <p class="card-footer">Ganti email? <a href="forgot-password.php?clean=1">Batal</a></p>
                        </form>
                    <?php endif; ?>

                <?php else: ?>
                    <!-- FORM TAHAP 3: ATUR ULANG KATA SANDI BARU -->
                    <span class="card-subtitle" style="color:var(--orange);"><b>Akun Terverifikasi!</b> Tulis Kata Sandi
                        baru.</span>
                    <form method="POST" id="resetForm" novalidate>
                        <div class="input-group">
                            <label>Kata Sandi Baru</label>
                            <div class="input-wrapper">
                                <i class="fa-solid fa-lock icon-left"></i>
                                <input type="password" name="new_password" id="passwordInput"
                                    placeholder="Min. 8 Karakter (Huruf & Angka)">
                                <i class="fa-solid fa-eye icon-right" id="togglePass" onclick="togglePassword()"></i>
                            </div>
                            <span class="error-text" id="passwordError"></span>
                        </div>

                        <div class="input-group">
                            <label>Konfirmasi Kata Sandi Baru</label>
                            <div class="input-wrapper">
                                <i class="fa-solid fa-lock icon-left"></i>
                                <input type="password" name="password_confirm" id="passwordConfirmInput"
                                    placeholder="Ulangi Kata Sandi">
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

    <?php include 'features_bar.php'; ?>

    <?php include '../includes/footer.php'; ?>

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
                // Hanya redirect ke login jika variabel $redirect_to_login bernilai true
                <?php if ($res_status == "success" && $redirect_to_login): ?>
                    window.location.href = 'login.php';
                <?php endif; ?>
            });
        <?php endif; ?>
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', () => {


            function setValidationError(inputEl, errorEl, message) {
                inputEl.parentElement.classList.add('error');
                inputEl.parentElement.parentElement.classList.add('error-active');
                errorEl.textContent = message;
                errorEl.style.display = 'block';
            }

            function clearValidationError(inputEl, errorEl) {
                if (inputEl.parentElement) {
                    inputEl.parentElement.classList.remove('error');
                    inputEl.parentElement.parentElement.classList.remove('error-active');
                }
                errorEl.style.display = 'none';
            }

            // ==========================================
            // VALIDASI FORM EMAIL (TAHAP 1) SECARA INLINE
            // ==========================================
            const requestOtpForm = document.getElementById('requestOtpForm');
            if (requestOtpForm) {
                const emailField = document.getElementById('emailField');
                const emailError = document.getElementById('emailError');

                requestOtpForm.addEventListener('submit', function (e) {
                    let isValid = true;
                    const emailVal = emailField.value.trim();

                    if (emailVal === '') {
                        setValidationError(emailField, emailError, 'Email wajib diisi.');
                        isValid = false;
                    } else {
                        clearValidationError(emailField, emailError);
                    }

                    // Jika tidak valid, batalkan submit dan beri efek guncang (shake)
                    if (!isValid) {
                        e.preventDefault();
                        const card = document.querySelector('.auth-card');
                        card.classList.remove('shake');
                        void card.offsetWidth;
                        card.classList.add('shake');
                    }
                });

                // Hapus warna merah saat user mulai mengetik ulang
                emailField.addEventListener('input', () => {
                    clearValidationError(emailField, emailError);
                });
            }

            // ==========================================
            // VALIDASI FORM OTP (TAHAP 2) SECARA INLINE
            // ==========================================
            const verifyOtpForm = document.getElementById('verifyOtpForm');
            if (verifyOtpForm) {
                const otpField = document.getElementById('otpField');
                const otpError = document.getElementById('otpError');

                verifyOtpForm.addEventListener('submit', function (e) {
                    let isValid = true;
                    const otpVal = otpField.value.trim();

                    if (otpVal === '') {
                        setValidationError(otpField, otpError, 'Kode OTP wajib diisi.');
                        isValid = false;
                    } else if (otpVal.length < 6) {
                        setValidationError(otpField, otpError, 'Kode OTP harus berisi 6 digit angka.');
                        isValid = false;
                    } else {
                        clearValidationError(otpField, otpError);
                    }

                    // Jika tidak valid, batalkan submit dan getarkan kartu
                    if (!isValid) {
                        e.preventDefault();
                        const card = document.querySelector('.auth-card');
                        card.classList.remove('shake');
                        void card.offsetWidth;
                        card.classList.add('shake');
                    }
                });

                // Menghapus tanda error saat user mengetik & membatasi input hanya angka
                otpField.addEventListener('input', () => {
                    clearValidationError(otpField, otpError);
                    otpField.value = otpField.value.replace(/[^0-9]/g, ''); // Hanya izinkan angka
                });
            }

            // VALIDASI FORM RESET KATA SANDI BARU
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
                    } else if (passwordVal.length < 8) {
                        setValidationError(password, passwordError, 'Kata sandi baru minimal berisi 8 karakter.');
                        isValid = false;
                    } else if (!hasLetter.test(passwordVal) || !hasNumber.test(passwordVal)) {
                        setValidationError(password, passwordError, 'Kata sandi baru harus berisi kombinasi huruf dan angka.');
                        isValid = false;
                    } else if (simplePasswords.includes(passwordVal.toLowerCase())) {
                        setValidationError(password, passwordError, 'Kata sandi terlalu mudah ditebak. Gunakan kombinasi lain.');
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