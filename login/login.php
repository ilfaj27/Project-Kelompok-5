<?php
// ============================================================================
// LOGIN.PHP — Halaman Login HoopBall
// ============================================================================
ob_start();
session_start();
$path_prefix = "../";
include '../includes/config.php';

// ============================================================================
// AMBIL NOTIFIKASI DARI URL PARAMETER
// ============================================================================
$notif_status = $_GET['status'] ?? '';
$notif_msg = $_GET['msg'] ?? '';

// ============================================================================
// CEK SESSION — Jika sudah login, redirect ke dashboard
// ============================================================================
if (isset($_SESSION['id_customer']) && !empty($_SESSION['id_customer'])) {
    header("Location: ../index.php");
    exit();
}
if (isset($_SESSION['id_karyawan']) && !empty($_SESSION['id_karyawan'])) {
    $role = strtolower(trim($_SESSION['role'] ?? ''));
    if ($role == 'pemilik') {
        header("Location: ../dashboard/view_pemilik.php");
    } else {
        header("Location: ../dashboard/view_admin.php");
    }
    exit();
}

$remembered_user = isset($_COOKIE['remember_me']) ? $_COOKIE['remember_me'] : '';
$error_msg = "";

if (isset($_POST['login'])) {
    $user_input = isset($_POST['user_input']) ? trim($_POST['user_input']) : '';
    $pass_input = isset($_POST['password_input']) ? $_POST['password_input'] : '';

    if (empty($user_input) || empty($pass_input)) {
        $error_msg = "Nama Pengguna/Email dan Password wajib diisi!";
    } else {
        // --- CEK KE TABEL KARYAWAN (Admin/Pemilik/Karyawan) ---
        // Mencoba SP terlebih dahulu, jika tidak ada gunakan raw SQL sebagai fallback
        $row = null;

        // Coba SP sp_AuthenticateKaryawan (jika ada)
        $sql_karyawan = "EXEC dbo.sp_AuthenticateKaryawan ?";
        $params = array($user_input);
        $stmt = sqlsrv_query($conn, $sql_karyawan, $params);

        if ($stmt) {
            $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        }

        if ($row) {
            if (password_verify($pass_input, $row['Kata_Sandi'])) {
                $_SESSION['login'] = true;
                $_SESSION['logged_in'] = true;
                $_SESSION['id_akun'] = $row['ID_Karyawan'];
                $_SESSION['id_karyawan'] = $row['ID_Karyawan'];

                $jabatan = intval($row['Jabatan']);
                if ($jabatan == 2) {
                    $_SESSION['role'] = 'pemilik';
                } elseif ($jabatan == 1) {
                    $_SESSION['role'] = 'karyawan';
                } else {
                    $_SESSION['role'] = 'karyawan';
                }

                $_SESSION['nama'] = $row['Nama_Karyawan'] ?? 'Admin';
                $_SESSION['jabatan'] = $jabatan;
                $_SESSION['Photo_Profile'] = $row['Photo_Profile'] ?? '';

                if (isset($_POST['remember'])) {
                    setcookie('remember_me', $user_input, time() + (86400 * 30), "/");
                } else {
                    setcookie('remember_me', '', time() - 3600, "/");
                }

                if ($_SESSION['role'] == 'pemilik') {
                    header("Location: ../dashboard/view_pemilik.php");
                } elseif ($_SESSION['role'] == 'karyawan') {
                    header("Location: ../dashboard/view_admin.php");
                } else {
                    header("Location: ../index.php");
                }
                exit();
            } else {
                $error_msg = "Nama Pengguna atau Kata Sandi Tidak ditemukan";
            }
        } else {
            // --- DIUBAH MENGGUNAKAN SP: Cek ke tabel Customer dengan sp_CustomerLogin ---
            // Panggil SP hanya dengan 1 parameter (Username/Email)
            $sql_customer = "EXEC dbo.sp_CustomerLogin ?";
            $params2 = array($user_input);
            $stmt2 = sqlsrv_query($conn, $sql_customer, $params2);

            $row2 = false;
            if ($stmt2) {
                $row2 = sqlsrv_fetch_array($stmt2, SQLSRV_FETCH_ASSOC);
            }

            // Lakukan verifikasi hash password menggunakan password_verify() di PHP
            if ($row2 && password_verify($pass_input, $row2['Kata_Sandi'])) {

                // Ambil status akun dari database
                $status_customer = isset($row2['Status']) ? intval($row2['Status']) : 1;

                if ($status_customer === 0) {
                    // Tampilkan pesan penonaktifan jika status bernilai 0
                    $error_msg = "Akun Anda dinonaktifkan karena melanggar syarat dan ketentuan.";
                } else {
                    // Proses login jika akun aktif (status bernilai 1)
                    $_SESSION['logged_in'] = true;
                    $_SESSION['id_customer'] = $row2['ID_Customer'];
                    $_SESSION['role'] = 'customer';
                    $_SESSION['Nama_Customer'] = $row2['Nama_Customer'] ?? 'Customer';
                    $_SESSION['nama'] = $row2['Nama_Customer'] ?? 'Customer';
                    $_SESSION['nama_user'] = $row2['Nama_Customer'] ?? 'Customer';
                    $_SESSION['jabatan'] = 'Customer';
                    $_SESSION['Profile_Photo'] = $row2['Photo_Profile'] ?? '';
                    $_SESSION['Email'] = $row2['Email'] ?? '';
                    $_SESSION['No_Telepon'] = $row2['No_Telepon'] ?? '';

                    if (isset($_POST['remember'])) {
                        setcookie('remember_me', $user_input, time() + (86400 * 30), "/");
                    } else {
                        setcookie('remember_me', '', time() - 3600, "/");
                    }

                    header("Location: ../index.php");
                    exit();
                }
            } else {
                // Pesan kesalahan jika password salah atau akun tidak ditemukan
                $error_msg = "Nama Pengguna atau Kata Sandi Tidak ditemukan.";
            }
        } // Penutup else cek customer
    } // Penutup else validasi input kosong
} // Penutup if isset POST login
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <?php include '../includes/favicon.php'; ?>
    <title>Login | HoopBall BasketPro</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="tampilan.css">
    <link rel="stylesheet" href="../asset/css/navbar_footer.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* ═══════════════════════════════════════════
           AUTH CARD ENTRANCE ANIMATIONS
           ═══════════════════════════════════════════ */
        .auth-card {
            animation: cardPopIn 0.8s cubic-bezier(0.34, 1.56, 0.64, 1) 0.3s both;
        }

        .auth-info {
            animation: slideInLeft 0.7s cubic-bezier(0.22, 1, 0.36, 1) 0.2s both;
        }


        .info-item:nth-child(1) {
            animation: fadeInUp 0.5s ease-out 0.6s both;
        }

        .info-item:nth-child(2) {
            animation: fadeInUp 0.5s ease-out 0.75s both;
        }

        .info-item:nth-child(3) {
            animation: fadeInUp 0.5s ease-out 0.9s both;
        }


        /* Input focus glow animation */
        .input-wrapper input:focus {
            border-color: var(--orange);
            box-shadow: 0 0 0 4px rgba(255, 84, 0, 0.1), 0 0 20px rgba(255, 84, 0, 0.15);
            transform: translateY(-1px);
        }

        /* Button ripple effect */
        .btn-submit {
            position: relative;
            overflow: hidden;
        }

        .btn-submit::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: width 0.6s ease, height 0.6s ease;
        }

        .btn-submit:active::after {
            width: 300px;
            height: 300px;
        }

        .btn-submit:hover {
            background: var(--orange-hover);
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(255, 84, 0, 0.35);
        }

        /* Close button pulse */
        .btn-close-auth {
            animation: pulseGlow 2s ease-in-out infinite;
        }

        /* Logo bounce on load */
        .auth-info h2 {
            animation: bounceIn 0.8s cubic-bezier(0.34, 1.56, 0.64, 1) 0.1s both;
        }

        @media (max-width: 992px) {
            .auth-info .intro-p {
                margin: 0 auto 40px auto;
            }

            .info-list {
                align-items: center;
                margin-bottom: 40px;
            }

            .auth-card-container {
                justify-content: center;
            }

        }
    </style>
</head>

<body>

    <?php include 'floating_balls.php'; ?>

    <a href="../index.php" class="btn-close-auth" title="Kembali ke Beranda">
        <i class="fa-solid fa-xmark"></i>
    </a>

    <div class="auth-hero-wrapper">
        <div class="auth-info">
            <h2>Masuk ke Akun<br><span>HoopBall</span></h2>
            <p class="intro-p">Masuk untuk booking lapangan, cek jadwal, dan nikmati promo member dengan lebih mudah.
            </p>

            <div class="info-list">
                <div class="info-item">
                    <div class="info-icon"><i class="fa-solid fa-bolt"></i></div>
                    <div class="info-text">
                        <h4>Booking cepat</h4>
                        <p>Pesan lapangan favorit kapan saja, di mana saja.</p>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-icon"><i class="fa-regular fa-clock"></i></div>
                    <div class="info-text">
                        <h4>Jadwal real-time</h4>
                        <p>Cek ketersediaan lapangan secara akurat.</p>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-icon"><i class="fa-solid fa-tags"></i></div>
                    <div class="info-text">
                        <h4>Promo member</h4>
                        <p>Dapatkan penawaran eksklusif khusus member.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="auth-card-container">
            <div class="auth-card">
                <div class="card-header-decoration">
                    <div class="card-icon-wrapper">
                        <i class="fa-solid fa-basketball"></i>
                    </div>
                </div>
                <h3>Masuk</h3>
                <span class="card-subtitle">Selamat datang kembali!</span>

                <form method="POST" action="" id="loginForm" novalidate>
                    <div class="input-group">
                        <label>Nama Pengguna / Email<span style="color: red;">*</span></label>
                        <div class="input-wrapper">
                            <i class="fa-regular fa-envelope icon-left"></i>
                            <input type="text" name="user_input" placeholder="Masukkan username atau email"
                                value="<?= htmlspecialchars($remembered_user) ?>">
                        </div>
                        <span class="error-text" id="emailError"></span>
                    </div>

                    <div class="input-group">
                        <label>Kata Sandi<span style="color: red;">*</span></label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-lock icon-left"></i>
                            <input type="password" name="password_input" id="passwordInput"
                                placeholder="Masukkan password Anda">
                            <i class="fa-solid fa-eye icon-right" id="togglePass" onclick="togglePassword()"></i>
                        </div>
                        <span class="error-text" id="passwordError"></span>
                    </div>

                    <div class="remember-row">
                        <div class="check-container">
                            <input type="checkbox" name="remember" id="rem" <?= $remembered_user ? 'checked' : '' ?>>
                            <label for="rem">Ingat saya</label>
                        </div>
                        <a href="forgot-password.php" class="forgot-link">Lupa Kata Sandi?</a>
                    </div>

                    <button type="submit" name="login" class="btn-submit">Masuk</button>

                    <p class="card-footer">Belum punya akun? <a href="register.php">Daftar sekarang</a></p>
                </form>
            </div>
        </div>
    </div>

    <?php include 'features_bar.php'; ?>

    <?php include '../includes/footer.php'; ?>

    <?php if ($error_msg): ?>
        <script>
            Swal.fire({
                icon: 'error',
                title: 'Masuk Gagal',
                text: '<?= addslashes($error_msg) ?>',
                background: '#ffffff',
                color: '#1e293b',
                confirmButtonColor: '#FF5400'
            });
        </script>
    <?php endif; ?>

    <?php if (!empty($notif_status) && !empty($notif_msg)): ?>
        <script>
            Swal.fire({
                icon: '<?= htmlspecialchars($notif_status) ?>',
                title: '<?= $notif_status === 'success' ? 'Berhasil!' : 'Informasi' ?>',
                text: '<?= addslashes($notif_msg) ?>',
                timer: 5000,
                showConfirmButton: false,
                toast: true,
                position: 'top-end',
                timerProgressBar: true,
                showCloseButton: true,
                background: '#ffffff',
                color: '#1e293b',
                iconColor: '<?= $notif_status === 'success' ? '#16A34A' : '#FF5400' ?>',
                customClass: { popup: 'swal-toast' }
            });
            // Hapus parameter dari URL agar tidak muncul lagi saat refresh
            window.history.replaceState({}, document.title, window.location.pathname);
        </script>
    <?php endif; ?>

    <script>
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

        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('loginForm');
            const userInput = document.querySelector('input[name="user_input"]');
            const passwordInput = document.getElementById('passwordInput');
            const emailError = document.getElementById('emailError');
            const passwordError = document.getElementById('passwordError');

            form.addEventListener('submit', function (e) {
                let isValid = true;
                if (userInput.value.trim() === '') {
                    userInput.parentElement.classList.add('error');
                    userInput.parentElement.parentElement.classList.add('error-active');
                    emailError.textContent = 'Nama Pengguna atau Email wajib diisi.';
                    emailError.style.display = 'block';
                    isValid = false;
                } else {
                    userInput.parentElement.classList.remove('error');
                    userInput.parentElement.parentElement.classList.remove('error-active');
                    emailError.style.display = 'none';
                }
                if (passwordInput.value.trim() === '') {
                    passwordInput.parentElement.classList.add('error');
                    passwordInput.parentElement.parentElement.classList.add('error-active');
                    passwordError.textContent = 'Kata Sandi wajib diisi.';
                    passwordError.style.display = 'block';
                    isValid = false;
                } else {
                    passwordInput.parentElement.classList.remove('error');
                    passwordInput.parentElement.parentElement.classList.remove('error-active');
                    passwordError.style.display = 'none';
                }
                if (!isValid) {
                    e.preventDefault();
                    const card = document.querySelector('.auth-card');
                    card.classList.remove('shake');
                    void card.offsetWidth; // trigger reflow
                    card.classList.add('shake');
                }
            });

            userInput.addEventListener('input', () => {
                userInput.parentElement.classList.remove('error');
                userInput.parentElement.parentElement.classList.remove('error-active');
                emailError.style.display = 'none';
            });

            passwordInput.addEventListener('input', () => {
                passwordInput.parentElement.classList.remove('error');
                passwordInput.parentElement.parentElement.classList.remove('error-active');
                passwordError.style.display = 'none';
            });
        });
    </script>

</body>

</html>