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
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register - Ardéliana Lux</title>
  <link rel="stylesheet" href="auth.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="../auth.css?v=<?php echo time(); ?>">
  <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>

<body>
  <div class="auth-container">
    <div class="auth-card">
      <div class="auth-header">
        <img src="../images/icon.png" alt="Ardéliana Lux" class="auth-logo">
        <h1 class="auth-title" data-translate="register_title">Create Account</h1>
        <p class="auth-subtitle" data-translate="register_subtitle">Join us and discover premium fragrances</p>
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

      <form method="POST" class="auth-form">
        <div class="form-group">
          <label for="full_name" data-translate="full_name">Full Name</label>
          <div class="input-with-icon">
            <i class="ri-user-line"></i>
            <input type="text" id="full_name" name="full_name" required>
          </div>
        </div>

        <div class="form-group">
          <label for="email" data-translate="email">Email Address</label>
          <div class="input-with-icon">
            <i class="ri-mail-line"></i>
            <input type="email" id="email" name="email" required>
          </div>
        </div>

        <div class="form-group">
          <label for="username" data-translate="username">Username</label>
          <div class="input-with-icon">
            <i class="ri-user-3-line"></i>
            <input type="text" id="username" name="username" required>
          </div>
        </div>

        <div class="form-group">
          <label for="phone" data-translate="phone">Phone Number</label>
          <div class="input-with-icon">
            <i class="ri-phone-line"></i>
            <input type="tel" id="phone" name="phone">
          </div>
        </div>

        <div class="form-group">
          <label for="address" data-translate="address">Shipping Address</label>
          <div class="input-with-icon">
            <i class="ri-map-pin-line"></i>
            <textarea id="address" name="address" rows="3"></textarea>
          </div>
        </div>

        <div class="form-group">
          <label for="password" data-translate="password">Password</label>
          <div class="input-with-icon">
            <i class="ri-lock-line"></i>
            <input type="password" id="password" name="password" minlength="6" required>
          </div>
        </div>

        <div class="form-group">
          <label for="confirm_password" data-translate="confirm_password">Confirm Password</label>
          <div class="input-with-icon">
            <i class="ri-lock-2-line"></i>
            <input type="password" id="confirm_password" name="confirm_password" minlength="6" required>
          </div>
        </div>

        <div class="form-info">
          <div class="info-item">
            <i class="ri-shield-check-line"></i>
            <div>
              <strong data-translate="admin_approval_required">Admin Approval Required</strong>
              <p data-translate="approval_message">Your account will be reviewed by our administrator. You'll receive an email once approved, or your account will be automatically activated after 3 business days.</p>
            </div>
          </div>
        </div>

        <div class="form-group">
          <button type="submit" class="btn-primary btn-block" data-translate="register">Create Account</button>
        </div>

        <div class="auth-links">
          <p data-translate="have_account">Already have an account?</p>
          <a href="login.php" class="link" data-translate="login_here">Login here</a>
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
    document.getElementById('confirm_password').addEventListener('input', function() {
      const password = document.getElementById('password').value;
      const confirmPassword = this.value;

      if (confirmPassword && password !== confirmPassword) {
        this.style.borderColor = '#dc3545';
      } else {
        this.style.borderColor = '#dee2e6';
      }
    });

    // Character counter for username
    document.getElementById('username').addEventListener('input', function() {
      const maxLength = 20;
      const currentLength = this.value.length;

      if (currentLength > maxLength) {
        this.value = this.value.substring(0, maxLength);
      }
    });

    // Address character limit
    document.getElementById('address').addEventListener('input', function() {
      const maxLength = 500;
      const currentLength = this.value.length;

      if (currentLength > maxLength) {
        this.value = this.value.substring(0, maxLength);
      }
    });
  </script>
</body>

</html>