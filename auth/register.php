<?php
require_once '../config/database.php';

// Handle registration request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = $_POST['username'] ?? '';
  $email = $_POST['email'] ?? '';
  $password = $_POST['password'] ?? '';
  $confirmPassword = $_POST['confirm_password'] ?? '';
  $fullName = $_POST['full_name'] ?? '';
  $phone = $_POST['phone'] ?? '';
  $address = $_POST['address'] ?? '';

  $errors = [];

  // Validation
  if (empty($username)) {
    $errors[] = 'Username is required';
  } elseif (strlen($username) < 3) {
    $errors[] = 'Username must be at least 3 characters';
  } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
    $errors[] = 'Username can only contain letters, numbers, and underscores';
  }

  if (empty($email)) {
    $errors[] = 'Email is required';
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Invalid email format';
  }

  if (empty($password)) {
    $errors[] = 'Password is required';
  } elseif (strlen($password) < 6) {
    $errors[] = 'Password must be at least 6 characters';
  }

  if ($password !== $confirmPassword) {
    $errors[] = 'Passwords do not match';
  }

  if (empty($fullName)) {
    $errors[] = 'Full name is required';
  }

  // Check if username or email already exists
  if (empty($errors)) {
    $checkStmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $checkStmt->bind_param("ss", $username, $email);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    if ($checkResult->num_rows > 0) {
      $errors[] = 'Username or email already exists';
    }
  }

  // If no errors, create user with pending status
  if (empty($errors)) {
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $status = 'pending'; // Pending admin approval
    $expiresAt = date('Y-m-d H:i:s', strtotime('+3 days')); // Auto-activate after 3 days

    $stmt = $conn->prepare("INSERT INTO users (username, email, password, full_name, phone, address, role, status, expires_at) VALUES (?, ?, ?, ?, ?, ?, 'user', ?, ?)");
    $stmt->bind_param("sssssss", $username, $email, $hashedPassword, $fullName, $phone, $address, $status, $expiresAt);

    if ($stmt->execute()) {
      $_SESSION['success_message'] = 'Registration successful! Your account is pending admin approval. You will receive an email once approved or automatically after 3 days.';
      header('Location: ../index.php?page=login');
      exit;
    } else {
      $errors[] = 'Registration failed. Please try again.';
    }
  }

  // If there are errors, redirect back with error message
  $_SESSION['register_errors'] = $errors;
  header('Location: ../index.php?page=register');
  exit;
}

// Auto-activate pending accounts (run this via cron job or on each login attempt)
function autoActivatePendingAccounts($conn)
{
  // Check if status column exists first
  $result = $conn->query("SHOW COLUMNS FROM users LIKE 'status'");
  if ($result->num_rows > 0) {
    $now = date('Y-m-d H:i:s');
    $stmt = $conn->prepare("UPDATE users SET status = 'active' WHERE status = 'pending' AND expires_at <= ? AND role = 'user'");
    $stmt->bind_param("s", $now);
    $stmt->execute();
  }
}

// Check for auto-activation on page load
autoActivatePendingAccounts($conn);
?>

<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register - Parfumé Lux</title>
  <link rel="stylesheet" href="../auth.css?v=<?php echo time(); ?>">
  <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>

<body>
  <div style="padding: 1rem 2rem;">
    <a href="../index.php" class="back-home"><i class="ri-arrow-left-line"></i> Kembali ke Beranda</a>
  </div>

  <div class="auth-container">
    <div class="auth-card">
      <div class="auth-header">
        <img src="../images/icon.png" alt="Parfumé Lux" class="auth-logo">
        <h1 class="auth-title" data-translate="register_title">Buat Akun Baru</h1>
        <p class="auth-subtitle" data-translate="register_subtitle">Bergabunglah dengan kami dan temukan parfum premium</p>
      </div>

      <?php if (isset($_SESSION['register_errors'])): ?>
        <div class="alert error">
          <?php
          foreach ($_SESSION['register_errors'] as $error) {
            echo '<p>' . htmlspecialchars($error) . '</p>';
          }
          unset($_SESSION['register_errors']);
          ?>
        </div>
      <?php endif; ?>

      <form method="POST" class="auth-form register-form">
        <!-- Personal Information Section -->
        <div class="form-section">
          <div class="section-header">
            <i class="ri-user-line"></i>
            <h3>Informasi Pribadi</h3>
          </div>
          
          <div class="form-group">
            <label for="full_name" data-translate="full_name">
              Nama Lengkap <span class="required">*</span>
            </label>
            <div class="input-with-icon">
              <i class="ri-user-line"></i>
              <input type="text" id="full_name" name="full_name" placeholder="Masukkan nama lengkap Anda" required>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="email" data-translate="email">
                Alamat Email <span class="required">*</span>
              </label>
              <div class="input-with-icon">
                <i class="ri-mail-line"></i>
                <input type="email" id="email" name="email" placeholder="nama@email.com" required>
              </div>
            </div>

            <div class="form-group">
              <label for="username" data-translate="username">
                Username <span class="required">*</span>
              </label>
              <div class="input-with-icon">
                <i class="ri-user-3-line"></i>
                <input type="text" id="username" name="username" placeholder="Pilih username unik" required>
              </div>
            </div>
          </div>

          <div class="form-group">
            <label for="phone" data-translate="phone">
              Nomor Telepon <span class="optional">(Opsional)</span>
            </label>
            <div class="input-with-icon">
              <i class="ri-phone-line"></i>
              <input type="tel" id="phone" name="phone" placeholder="08xxxxxxxxxx">
            </div>
          </div>
        </div>

        <!-- Shipping Information Section -->
        <div class="form-section">
          <div class="section-header">
            <i class="ri-map-pin-line"></i>
            <h3>Informasi Pengiriman</h3>
          </div>
          
          <div class="form-group">
            <label for="address" data-translate="address">
              Alamat Lengkap <span class="optional">(Opsional)</span>
            </label>
            <div class="input-with-icon textarea-wrapper">
              <i class="ri-map-pin-line"></i>
              <textarea id="address" name="address" rows="3" placeholder="Jl. Nama Jalan No. XX, Kelurahan, Kecamatan, Kota, Provinsi, Kode Pos"></textarea>
            </div>
            <small class="form-hint">Alamat ini akan digunakan sebagai alamat pengiriman default</small>
          </div>
        </div>

        <!-- Security Section -->
        <div class="form-section">
          <div class="section-header">
            <i class="ri-lock-line"></i>
            <h3>Keamanan Akun</h3>
          </div>
          
          <div class="form-row">
            <div class="form-group">
              <label for="password" data-translate="password">
                Password <span class="required">*</span>
              </label>
              <div class="input-with-icon">
                <i class="ri-lock-line"></i>
                <input type="password" id="password" name="password" minlength="6" placeholder="Minimal 6 karakter" required>
              </div>
              <small class="form-hint">Gunakan kombinasi huruf, angka, dan simbol</small>
            </div>

            <div class="form-group">
              <label for="confirm_password" data-translate="confirm_password">
                Konfirmasi Password <span class="required">*</span>
              </label>
              <div class="input-with-icon">
                <i class="ri-lock-2-line"></i>
                <input type="password" id="confirm_password" name="confirm_password" minlength="6" placeholder="Ulangi password" required>
              </div>
              <small class="form-hint password-match" style="display: none;"></small>
            </div>
          </div>
        </div>

        <!-- Info Box -->
        <div class="form-info">
          <div class="info-item">
            <i class="ri-information-line"></i>
            <div>
              <strong data-translate="admin_approval_required">📋 Persetujuan Admin Diperlukan</strong>
              <p data-translate="approval_message">Akun Anda akan ditinjau oleh administrator kami. Anda akan menerima email setelah disetujui, atau akun Anda akan diaktifkan secara otomatis setelah 3 hari kerja.</p>
            </div>
          </div>
        </div>

        <div class="form-group">
          <button type="submit" class="btn-primary btn-block" data-translate="register">
            <i class="ri-user-add-line"></i> Buat Akun Sekarang
          </button>
        </div>

        <div class="auth-links">
          <p data-translate="have_account">Sudah punya akun?</p>
          <a href="login.php" class="link" data-translate="login_here">
            <i class="ri-login-box-line"></i> Masuk di sini
          </a>
        </div>
      </form>
    </div>
  </div>

  <script>
    // Password confirmation validation
    document.querySelector('.auth-form').addEventListener('submit', function(e) {
      const password = document.getElementById('password').value;
      const confirmPassword = document.getElementById('confirm_password').value;

      if (password !== confirmPassword) {
        e.preventDefault();
        alert('Passwords do not match!');
        return false;
      }

      // Email format validation
      const email = document.getElementById('email').value;
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!emailRegex.test(email)) {
        e.preventDefault();
        alert('Please enter a valid email address!');
        return false;
      }

      // Username validation
      const username = document.getElementById('username').value;
      if (username.length < 3) {
        e.preventDefault();
        alert('Username must be at least 3 characters!');
        return false;
      }

      const usernameRegex = /^[a-zA-Z0-9_]+$/;
      if (!usernameRegex.test(username)) {
        e.preventDefault();
        alert('Username can only contain letters, numbers, and underscores!');
        return false;
      }
    });

    // Real-time validation feedback
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const passwordMatchHint = document.querySelector('.password-match');

    function checkPasswordMatch() {
      const password = passwordInput.value;
      const confirmPassword = confirmPasswordInput.value;

      if (confirmPassword.length > 0) {
        passwordMatchHint.style.display = 'block';
        if (password === confirmPassword) {
          confirmPasswordInput.style.borderColor = '#27ae60';
          passwordMatchHint.textContent = '✓ Password cocok';
          passwordMatchHint.className = 'form-hint password-match match';
        } else {
          confirmPasswordInput.style.borderColor = '#e74c3c';
          passwordMatchHint.textContent = '✗ Password tidak cocok';
          passwordMatchHint.className = 'form-hint password-match no-match';
        }
      } else {
        passwordMatchHint.style.display = 'none';
        confirmPasswordInput.style.borderColor = '#dee2e6';
      }
    }

    confirmPasswordInput.addEventListener('input', checkPasswordMatch);
    passwordInput.addEventListener('input', function() {
      if (confirmPasswordInput.value.length > 0) {
        checkPasswordMatch();
      }
    });

    // Character counter for username
    const usernameInput = document.getElementById('username');
    usernameInput.addEventListener('input', function() {
      const maxLength = 20;
      const currentLength = this.value.length;

      if (currentLength > maxLength) {
        this.value = this.value.substring(0, maxLength);
      }

      // Visual feedback for username
      if (currentLength >= 3) {
        this.style.borderColor = '#27ae60';
      } else if (currentLength > 0) {
        this.style.borderColor = '#f39c12';
      } else {
        this.style.borderColor = '#dee2e6';
      }
    });

    // Address character limit
    const addressInput = document.getElementById('address');
    addressInput.addEventListener('input', function() {
      const maxLength = 500;
      const currentLength = this.value.length;

      if (currentLength > maxLength) {
        this.value = this.value.substring(0, maxLength);
      }
    });

    // Email validation visual feedback
    const emailInput = document.getElementById('email');
    emailInput.addEventListener('blur', function() {
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (this.value.length > 0) {
        if (emailRegex.test(this.value)) {
          this.style.borderColor = '#27ae60';
        } else {
          this.style.borderColor = '#e74c3c';
        }
      } else {
        this.style.borderColor = '#dee2e6';
      }
    });

    // Password strength indicator
    passwordInput.addEventListener('input', function() {
      const password = this.value;
      if (password.length >= 8) {
        this.style.borderColor = '#27ae60';
      } else if (password.length >= 6) {
        this.style.borderColor = '#f39c12';
      } else if (password.length > 0) {
        this.style.borderColor = '#e74c3c';
      } else {
        this.style.borderColor = '#dee2e6';
      }
    });
  </script>
</body>

</html>