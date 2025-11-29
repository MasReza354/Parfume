<?php
require_once '../config/database.php';

$alertScript = ''; // Variabel untuk menyimpan skrip alert
// Language setup
$lang = $_GET['lang'] ?? ($_SESSION['lang'] ?? 'id');
$_SESSION['lang'] = $lang;

// Prevent logged-in users from accessing login again
if (isLoggedIn()) {
  if (isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], ['admin', 'superadmin'])) {
    header('Location: ../admin/dashboard.php');
  } elseif (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'karyawan') {
    header('Location: ../karyawan/dashboard.php');
  } else {
    header('Location: ../index.php');
  }
  exit;
}

// Handle login request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = $_POST['username'] ?? '';
  $password = $_POST['password'] ?? '';
  $captcha = $_POST['captcha'] ?? '';

  $errors = [];

  if (empty($username)) {
    $errors[] = 'Username wajib diisi';
  }

  if (empty($password)) {
    $errors[] = 'Password wajib diisi';
  }

  if (empty($captcha)) {
    $errors[] = 'CAPTCHA wajib diisi';
  } elseif (!isset($_SESSION['captcha']) || strtoupper($captcha) !== strtoupper($_SESSION['captcha'])) {
    $errors[] = 'CAPTCHA tidak valid';
  }

  if (empty($errors)) {
    // First check if status column exists
    $statusCheck = $conn->query("SHOW COLUMNS FROM users LIKE 'status'");
    $hasStatusColumn = $statusCheck->num_rows > 0;

    if ($hasStatusColumn) {
      $stmt = $conn->prepare("SELECT id, username, email, full_name, password, role, status FROM users WHERE username = ? OR email = ?");
    } else {
      $stmt = $conn->prepare("SELECT id, username, email, full_name, password, role FROM users WHERE username = ? OR email = ?");
    }
    $stmt->bind_param("ss", $username, $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
      $user = $result->fetch_assoc();

      // Set default status if column doesn't exist
      if (!$hasStatusColumn) {
        $user['status'] = 'active';
      }

      if ($user['status'] === 'pending') {
        // Check if account is pending (new registration)
        $errors[] = 'Akun Anda sedang menunggu persetujuan admin. Harap tunggu.';
      } elseif ($user['status'] === 'rejected') {
        $errors[] = 'Akun Anda telah ditolak. Silakan hubungi dukungan.';
      } elseif (password_verify($password, $user['password'])) {
        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['user_role'] = $user['role'];

        // Clear CAPTCHA after successful use
        unset($_SESSION['captcha']);

        $alertScript = '
        <script>
          Swal.fire({
            icon: "success",
            title: "Login Berhasil!",
            text: "Selamat datang kembali, ' . htmlspecialchars($user['full_name'], ENT_QUOTES) . '!",
            timer: 2000,
            timerProgressBar: true,
            showConfirmButton: false,
            willClose: true
          }).then((result) => {
            if (result.dismiss === Swal.DismissReason.timer || result.isConfirmed) {
              // Redirect based on role
              if (' . json_encode($user['role']) . ' === "admin" || ' . json_encode($user['role']) . ' === "superadmin") {
                window.location.href = "../admin/dashboard.php";
              } else {
                window.location.href = "../index.php";
              }
            }
          });
        </script>';
      } else {
        $errors[] = 'Password salah';
      }
    } else {
      $errors[] = 'Pengguna tidak ditemukan';
    }
  }

  // If there are errors, show SweetAlert2 error message
  if (!empty($errors)) {
    $errorText = implode('<br>', $errors);
    $alertScript = '
    <script>
      Swal.fire({
        icon: "error",
        title: "Login Gagal!",
        html: "' . addslashes($errorText) . '",
        confirmButtonText: "Coba Lagi",
        confirmButtonColor: "#e74c3c",
        backdrop: true,
        allowOutsideClick: false
      }).then((result) => {
        if (result.isConfirmed) {
          // Refresh CAPTCHA
          refreshCaptcha();
        }
      });
    </script>';
  }
}

// Pastikan session captcha ada untuk tampilan awal
if (!isset($_SESSION['captcha'])) {
  generateCaptcha(); // Panggil jika belum ada
}

function generateCaptcha()
{
  $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
  $length = 6;
  $captcha = '';
  for ($i = 0; $i < $length; $i++) {
    $captcha .= $chars[rand(0, strlen($chars) - 1)];
  }
  $_SESSION['captcha'] = $captcha;
  return $captcha;
}

?>

<!DOCTYPE html>
<html lang="id"> <!-- Mengubah bahasa dokumen -->

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - Parfum Lux</title>
  <link rel="stylesheet" href="../auth.css?v=<?php echo time(); ?>">
  <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <!-- SweetAlert2 -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body class="<?php echo isLoggedIn() ? 'user-logged-in' : 'user-logged-out'; ?>">
  <div style="padding: 1rem 2rem;">
    <a href="../index.php" class="back-home"><i class="ri-arrow-left-line"></i> Kembali ke Beranda</a>
  </div>

  <div class="auth-container">
    <div class="auth-card">
      <div class="auth-header">
        <img src="../images/icon.png" alt="Parfum Lux" class="auth-logo">
        <h1 class="auth-title" data-translate="login_title">Selamat Datang Kembali</h1>
        <p class="auth-subtitle" data-translate="login_subtitle">Masuk untuk mengakses akun Anda</p>
      </div>

      <form method="POST" class="auth-form login-form" action="login.php">
        <!-- Login Credentials Section -->
        <div class="form-section">
          <div class="section-header">
            <i class="ri-login-box-line"></i>
            <h3>Informasi Login</h3>
          </div>

          <div class="form-group">
            <label for="username" data-translate="username_email">
              Username atau Email <span class="required">*</span>
            </label>
            <div class="input-with-icon">
              <i class="ri-user-line"></i>
              <input type="text" id="username" name="username" placeholder="Masukkan username atau email" required autocomplete="username">
            </div>
            <small class="form-hint">Gunakan username atau alamat email yang terdaftar</small>
          </div>

          <div class="form-group">
            <label for="password" data-translate="password">
              Password <span class="required">*</span>
            </label>
            <div class="input-with-icon">
              <i class="ri-lock-line"></i>
              <input type="password" id="password" name="password" placeholder="Masukkan password" required autocomplete="current-password">
            </div>
          </div>
        </div>

        <!-- Security Verification Section -->
        <div class="form-section">
          <div class="section-header">
            <i class="ri-shield-check-line"></i>
            <h3>Verifikasi Keamanan</h3>
          </div>

          <div class="form-group">
            <label for="captcha" data-translate="captcha">
              Kode Keamanan <span class="required">*</span>
            </label>
            <div class="captcha-container">
              <div class="captcha-image">
                <img src="captcha.php" alt="CAPTCHA" id="captchaImage">
              </div>
              <div class="captcha-input">
                <input type="text" id="captcha" name="captcha" required maxlength="6" placeholder="Masukkan kode" autocomplete="off">
                <button type="button" class="btn-refresh-captcha" onclick="refreshCaptcha()" title="Muat ulang kode">
                  <i class="ri-refresh-line"></i>
                </button>
              </div>
            </div>
            <small class="form-hint" data-translate="captcha_help">
              <i class="ri-information-line"></i> Masukkan 6 karakter yang terlihat di gambar (tidak case-sensitive)
            </small>
          </div>
        </div>

        <div class="form-group">
          <button type="submit" class="btn-primary btn-block" data-translate="login">
            <i class="ri-login-circle-line"></i> Masuk Sekarang
          </button>
        </div>

        <div class="auth-links">
          <p data-translate="no_account">Belum punya akun?</p>
          <a href="register.php" class="link" data-translate="register_here">
            <i class="ri-user-add-line"></i> Daftar di sini
          </a>
        </div>
      </form>

      <div class="quick-accounts">
        <div class="quick-accounts-header">
          <i class="ri-shield-user-line"></i>
          <div>
            <p class="qa-title">Akun Siap Pakai</p>
            <p class="qa-note">Pilih akun demo untuk uji coba cepat - Klik "Isi otomatis" untuk login instan</p>
          </div>
        </div>

        <div class="quick-account-list">
          <div class="quick-account-card">
            <div>
              <p class="qa-role"><i class="ri-admin-line"></i> Admin</p>
              <p class="qa-cred"><strong>Username:</strong> admin</p>
              <p class="qa-cred"><strong>Email:</strong> admin@parfumlux.com</p>
              <p class="qa-cred"><strong>Password:</strong> admin123</p>
            </div>
            <button type="button" class="btn-secondary fill-account" data-username="admin" data-password="admin123">
              <i class="ri-login-box-line"></i> Isi otomatis
            </button>
          </div>

          <div class="quick-account-card">
            <div>
              <p class="qa-role"><i class="ri-shield-star-line"></i> Superadmin</p>
              <p class="qa-cred"><strong>Username:</strong> superadmin</p>
              <p class="qa-cred"><strong>Email:</strong> superadmin@parfumlux.com</p>
              <p class="qa-cred"><strong>Password:</strong> superadmin123</p>
            </div>
            <button type="button" class="btn-secondary fill-account" data-username="superadmin" data-password="superadmin123">
              <i class="ri-login-box-line"></i> Isi otomatis
            </button>
          </div>

          <div class="quick-account-card">
            <div>
              <p class="qa-role"><i class="ri-user-settings-line"></i> Karyawan</p>
              <p class="qa-cred"><strong>Username:</strong> cabang1</p>
              <p class="qa-cred"><strong>Email:</strong> cabang1@gmail.com</p>
              <p class="qa-cred"><strong>Password:</strong> password</p>
            </div>
            <button type="button" class="btn-secondary fill-account" data-username="cabang1" data-password="password">
              <i class="ri-login-box-line"></i> Isi otomatis
            </button>
          </div>

          <div class="quick-account-card">
            <div>
              <p class="qa-role"><i class="ri-user-smile-line"></i> Demo User</p>
              <p class="qa-cred"><strong>Username:</strong> demo</p>
              <p class="qa-cred"><strong>Email:</strong> demo@parfumlux.com</p>
              <p class="qa-cred"><strong>Password:</strong> demo123</p>
            </div>
            <button type="button" class="btn-secondary fill-account" data-username="demo" data-password="demo123">
              <i class="ri-login-box-line"></i> Isi otomatis
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script>
    // Refresh CAPTCHA function
    function refreshCaptcha() {
      const captchaImg = document.getElementById('captchaImage');
      if (captchaImg) {
        captchaImg.src = 'captcha.php?t=' + Date.now();
        // Clear captcha input
        const captchaInput = document.getElementById('captcha');
        if (captchaInput) {
          captchaInput.value = '';
          captchaInput.focus();
        }
      }
    }

    // Refresh CAPTCHA on click
    document.querySelector('.btn-refresh-captcha')?.addEventListener('click', refreshCaptcha);

    // Autofill credentials for preset accounts
    const usernameInput = document.getElementById('username');
    const passwordInput = document.getElementById('password');
    const captchaInput = document.getElementById('captcha');

    document.querySelectorAll('.fill-account').forEach((btn) => {
      btn.addEventListener('click', () => {
        if (usernameInput && passwordInput) {
          usernameInput.value = btn.dataset.username || '';
          passwordInput.value = btn.dataset.password || '';
          
          // Visual feedback
          usernameInput.style.borderColor = '#27ae60';
          passwordInput.style.borderColor = '#27ae60';
          
          // Focus on captcha input
          if (captchaInput) {
            captchaInput.focus();
          }
          
          refreshCaptcha();
        }
      });
    });

    // Input validation visual feedback
    if (usernameInput) {
      usernameInput.addEventListener('input', function() {
        if (this.value.length >= 3) {
          this.style.borderColor = '#27ae60';
        } else if (this.value.length > 0) {
          this.style.borderColor = '#f39c12';
        } else {
          this.style.borderColor = '#dee2e6';
        }
      });
    }

    if (passwordInput) {
      passwordInput.addEventListener('input', function() {
        if (this.value.length >= 6) {
          this.style.borderColor = '#27ae60';
        } else if (this.value.length > 0) {
          this.style.borderColor = '#f39c12';
        } else {
          this.style.borderColor = '#dee2e6';
        }
      });
    }

    if (captchaInput) {
      captchaInput.addEventListener('input', function() {
        // Auto uppercase
        this.value = this.value.toUpperCase();
        
        if (this.value.length === 6) {
          this.style.borderColor = '#27ae60';
        } else if (this.value.length > 0) {
          this.style.borderColor = '#f39c12';
        } else {
          this.style.borderColor = '#dee2e6';
        }
      });
    }
  </script>

  <?php
  if (!empty($alertScript)) {
    echo $alertScript;
  }
  ?>
</body>

</html>