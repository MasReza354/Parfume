<?php
require_once 'config/database.php';

// Check if user is logged in
if (!isLoggedIn()) {
  header('Location: auth/login.php?redirect=profile');
  exit;
}

// Get current user data
$user = getCurrentUser();
$userDetails = $conn->query("SELECT * FROM users WHERE id = " . $user['id'])->fetch_assoc();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
  $fullName = $_POST['full_name'] ?? '';
  $phone = $_POST['phone'] ?? '';
  $address = $_POST['address'] ?? '';

  $errors = [];

  if (empty($fullName)) {
    $errors[] = 'Full name is required';
  }

  if (empty($errors)) {
    // Handle profile photo upload
    $photoPath = $userDetails['photo'] ?? '';
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
      $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
      $maxSize = 2 * 1024 * 1024; // 2MB

      $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
      $fileInfo->file($_FILES['photo']['tmp_name']);
      $mimeType = $fileInfo->mime();

      if (!in_array($mimeType, $allowedTypes)) {
        $errors[] = 'Only JPG, PNG, and GIF images are allowed';
      } elseif ($_FILES['photo']['size'] > $maxSize) {
        $errors[] = 'Photo size must be less than 2MB';
      } else {
        // Create uploads directory if not exists
        $uploadDir = 'uploads/profiles/';
        if (!file_exists($uploadDir)) {
          mkdir($uploadDir, 0777, true);
        }

        // Generate unique filename
        $filename = time() . '_' . basename($_FILES['photo']['name']);
        $targetPath = $uploadDir . $filename;

        if (move_uploaded_file($_FILES['photo']['tmp_name'], $targetPath)) {
          $photoPath = $targetPath;

          // Delete old photo if exists
          if (!empty($userDetails['photo']) && file_exists($userDetails['photo'])) {
            unlink($userDetails['photo']);
          }
        } else {
          $errors[] = 'Failed to upload photo';
        }
      }
    }

    if (empty($errors)) {
      // Update user profile
      $stmt = $conn->prepare("UPDATE users SET full_name = ?, phone = ?, address = ?, photo = ? WHERE id = ?");
      $stmt->bind_param("ssssi", $fullName, $phone, $address, $photoPath, $user['id']);

      if ($stmt->execute()) {
        $_SESSION['success_message'] = 'Profile updated successfully!';
        $_SESSION['full_name'] = $fullName; // Update session
      } else {
        $_SESSION['error_message'] = 'Failed to update profile';
      }
    }
  } else {
    $_SESSION['error_message'] = implode('<br>', $errors);
  }

  header('Location: profile.php');
  exit;
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
  $currentPassword = $_POST['current_password'] ?? '';
  $newPassword = $_POST['new_password'] ?? '';
  $confirmPassword = $_POST['confirm_password'] ?? '';

  $errors = [];

  if (empty($currentPassword)) {
    $errors[] = 'Current password is required';
  }

  if (empty($newPassword)) {
    $errors[] = 'New password is required';
  }

  if (strlen($newPassword) < 6) {
    $errors[] = 'New password must be at least 6 characters';
  }

  if ($newPassword !== $confirmPassword) {
    $errors[] = 'Passwords do not match';
  }

  if (empty($errors)) {
    // Verify current password
    if (password_verify($currentPassword, $userDetails['password'])) {
      // Update password
      $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
      $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
      $stmt->bind_param("si", $hashedPassword, $user['id']);

      if ($stmt->execute()) {
        $_SESSION['success_message'] = 'Password changed successfully!';
      } else {
        $_SESSION['error_message'] = 'Failed to change password';
      }
    } else {
      $errors[] = 'Current password is incorrect';
    }
  }

  if (!empty($errors)) {
    $_SESSION['error_message'] = implode('<br>', $errors);
  }

  header('Location: profile.php');
  exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Profile - Ardéliana Lux</title>
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="auth.css">
  <link rel="stylesheet" href="cart.css">
  <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    .profile-section {
      padding: 80px 0;
      min-height: 60vh;
    }

    .profile-container {
      max-width: 1200px;
      margin: 0 auto;
      display: grid;
      grid-template-columns: 1fr 2fr;
      gap: 30px;
    }

    .profile-sidebar {
      background: white;
      border-radius: 10px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
      overflow: hidden;
      height: fit-content;
    }

    .profile-photo {
      text-align: center;
      padding: 30px;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }

    .profile-photo img {
      width: 150px;
      height: 150px;
      border-radius: 50%;
      border: 4px solid white;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
      object-fit: cover;
    }

    .profile-info {
      padding: 20px;
      text-align: center;
    }

    .profile-info h3 {
      margin: 0 0 10px 0;
      color: #2c3e50;
      font-size: 1.2rem;
    }

    .profile-info p {
      color: #6c757d;
      margin: 5px 0;
    }

    .profile-main {
      display: flex;
      flex-direction: column;
      gap: 20px;
    }

    .profile-card {
      background: white;
      border-radius: 10px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
      overflow: hidden;
    }

    .card-header {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 20px;
      text-align: center;
    }

    .card-header h3 {
      margin: 0;
      font-size: 1.1rem;
      font-weight: 600;
    }

    .card-body {
      padding: 25px;
    }

    .form-group {
      margin-bottom: 20px;
    }

    .form-group label {
      display: block;
      margin-bottom: 8px;
      font-weight: 500;
      color: #2c3e50;
    }

    .form-group input,
    .form-group textarea {
      width: 100%;
      padding: 12px 15px;
      border: 2px solid #e9ecef;
      border-radius: 8px;
      font-size: 1rem;
      transition: border-color 0.3s ease;
    }

    .form-group input:focus,
    .form-group textarea:focus {
      outline: none;
      border-color: #667eea;
      box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .form-group textarea {
      resize: vertical;
      min-height: 100px;
    }

    .file-input-wrapper {
      position: relative;
      overflow: hidden;
      display: inline-block;
      cursor: pointer;
      width: 100%;
    }

    .file-input-wrapper input[type=file] {
      position: absolute;
      left: -9999px;
    }

    .file-input-label {
      display: block;
      padding: 12px 15px;
      background: #f8f9fa;
      border: 2px dashed #dee2e6;
      border-radius: 8px;
      text-align: center;
      cursor: pointer;
      transition: all 0.3s ease;
    }

    .file-input-label:hover {
      background: #e9ecef;
      border-color: #667eea;
    }

    .btn-secondary {
      background: #6c757d;
      color: white;
      border: none;
      padding: 12px 20px;
      border-radius: 8px;
      cursor: pointer;
      font-size: 1rem;
      font-weight: 500;
      transition: background-color 0.3s ease;
      width: 100%;
    }

    .btn-secondary:hover {
      background: #5a6268;
    }

    .password-strength {
      margin-top: 5px;
      height: 4px;
      border-radius: 2px;
      background: #e9ecef;
      transition: background 0.3s ease;
    }

    .password-strength.weak {
      background: #dc3545;
      width: 33%;
    }

    .password-strength.medium {
      background: #ffc107;
      width: 66%;
    }

    .password-strength.strong {
      background: #28a745;
      width: 100%;
    }

    @media (max-width: 768px) {
      .profile-container {
        grid-template-columns: 1fr;
        gap: 20px;
      }

      .profile-photo img {
        width: 120px;
        height: 120px;
      }
    }
  </style>
</head>

<body>
  <!-- Navigation -->
  <nav class="navbar">
    <div class="nav-container">
      <div class="nav-logo">
        <img src="images/icon.png" alt="Ardéliana Lux">
        <span class="logo-text">Ardéliana Lux</span>
      </div>

      <ul class="nav-menu">
        <li><a href="index.php" class="nav-link">Home</a></li>
        <li><a href="index.php#about" class="nav-link">About</a></li>
        <li><a href="index.php#contact" class="nav-link">Contact</a></li>
      </ul>

      <div class="nav-actions">
        <div class="language-switcher">
          <button class="lang-btn" id="langToggle">
            <i class="ri-global-line"></i>
            <span id="currentLang">EN</span>
          </button>
        </div>

        <div class="user-menu">
          <?php if (isLoggedIn()): ?>
            <div class="user-dropdown">
              <button class="user-btn">
                <i class="ri-user-line"></i>
                <span><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                <i class="ri-arrow-down-s-line"></i>
              </button>
              <div class="dropdown-menu">
                <a href="profile.php" class="active"><i class="ri-user-line"></i> Profile</a>
                <a href="favorites.php"><i class="ri-heart-line"></i> Favorites</a>
                <a href="cart.php"><i class="ri-shopping-cart-line"></i> Cart</a>
                <a href="orders.php"><i class="ri-shopping-bag-line"></i> My Orders</a>
                <a href="auth/logout.php"><i class="ri-logout-box-line"></i> Logout</a>
              </div>
            </div>
          <?php else: ?>
            <a href="auth/login.php" class="btn-login">Login</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </nav>

  <!-- Profile Section -->
  <section class="profile-section">
    <div class="container">
      <div class="section-header">
        <h1 class="section-title">My Profile</h1>
        <p class="section-subtitle">Manage your personal information and preferences</p>
      </div>

      <?php if (isset($_SESSION['success_message'])): ?>
        <div class="notification success">
          <?php
          echo $_SESSION['success_message'];
          unset($_SESSION['success_message']);
          ?>
        </div>
      <?php endif; ?>

      <?php if (isset($_SESSION['error_message'])): ?>
        <div class="notification error">
          <?php
          echo $_SESSION['error_message'];
          unset($_SESSION['error_message']);
          ?>
        </div>
      <?php endif; ?>

      <div class="profile-container">
        <div class="profile-sidebar">
          <div class="profile-photo">
            <img src="<?php echo !empty($userDetails['photo']) ? htmlspecialchars($userDetails['photo']) : 'https://via.placeholder.com/150'; ?>" alt="Profile Photo">
          </div>
          <div class="profile-info">
            <h3><?php echo htmlspecialchars($userDetails['full_name']); ?></h3>
            <p><i class="ri-mail-line"></i> <?php echo htmlspecialchars($userDetails['email']); ?></p>
            <p><i class="ri-phone-line"></i> <?php echo htmlspecialchars($userDetails['phone'] ?? 'Not set'); ?></p>
            <p><i class="ri-map-pin-line"></i> <?php echo htmlspecialchars($userDetails['address'] ?? 'Not set'); ?></p>
            <p><i class="ri-shield-check-line"></i> <?php echo ucfirst($userDetails['role']); ?></p>
          </div>
        </div>

        <div class="profile-main">
          <!-- Update Profile Form -->
          <div class="profile-card">
            <div class="card-header">
              <h3>Update Profile Information</h3>
            </div>
            <div class="card-body">
              <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                  <label for="full_name">Full Name</label>
                  <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($userDetails['full_name']); ?>" required>
                </div>

                <div class="form-group">
                  <label for="email">Email Address</label>
                  <input type="email" id="email" value="<?php echo htmlspecialchars($userDetails['email']); ?>" disabled>
                </div>

                <div class="form-group">
                  <label for="phone">Phone Number</label>
                  <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($userDetails['phone'] ?? ''); ?>">
                </div>

                <div class="form-group">
                  <label for="address">Address</label>
                  <textarea id="address" name="address" rows="3"><?php echo htmlspecialchars($userDetails['address'] ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                  <label for="photo">Profile Photo</label>
                  <div class="file-input-wrapper">
                    <input type="file" id="photo" name="photo" accept="image/*">
                    <label for="photo" class="file-input-label">
                      <i class="ri-image-line"></i> Choose Photo or Drag & Drop
                    </label>
                  </div>
                </div>

                <button type="submit" name="update_profile" class="btn-primary btn-block">
                  <i class="ri-save-line"></i> Update Profile
                </button>
              </form>
            </div>
          </div>

          <!-- Change Password Form -->
          <div class="profile-card">
            <div class="card-header">
              <h3>Change Password</h3>
            </div>
            <div class="card-body">
              <form method="POST">
                <div class="form-group">
                  <label for="current_password">Current Password</label>
                  <input type="password" id="current_password" name="current_password" required>
                </div>

                <div class="form-group">
                  <label for="new_password">New Password</label>
                  <input type="password" id="new_password" name="new_password" required>
                  <div class="password-strength" id="passwordStrength"></div>
                </div>

                <div class="form-group">
                  <label for="confirm_password">Confirm New Password</label>
                  <input type="password" id="confirm_password" name="confirm_password" required>
                </div>

                <button type="submit" name="change_password" class="btn-secondary btn-block">
                  <i class="ri-lock-line"></i> Change Password
                </button>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Footer -->
  <footer class="footer">
    <div class="container">
      <div class="footer-content">
        <div class="footer-section">
          <h3>About Us</h3>
          <p>Ardéliana Lux - Your destination for premium fragrances</p>
        </div>

        <div class="footer-section">
          <h3>Contact</h3>
          <p>Email: info@ardeliana.com<br>
            Phone: +62 21 5555 1234</p>
        </div>

        <div class="footer-section">
          <h3>Follow Us</h3>
          <div class="social-links">
            <a href="#"><i class="ri-facebook-line"></i></a>
            <a href="#"><i class="ri-instagram-line"></i></a>
            <a href="#"><i class="ri-twitter-line"></i></a>
          </div>
        </div>
      </div>

      <div class="footer-bottom">
        <p>&copy; 2024 Ardéliana Lux. All rights reserved.</p>
      </div>
    </div>
  </footer>

  <script>
    // User dropdown functionality
    document.querySelector('.user-btn')?.addEventListener('click', function(e) {
      e.preventDefault();
      const dropdown = document.querySelector('.dropdown-menu');
      dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
      const userMenu = document.querySelector('.user-menu');
      if (userMenu && !userMenu.contains(e.target)) {
        const dropdown = document.querySelector('.dropdown-menu');
        if (dropdown) dropdown.style.display = 'none';
      }
    });

    // Password strength checker
    const newPasswordInput = document.getElementById('new_password');
    const passwordStrength = document.getElementById('passwordStrength');

    newPasswordInput?.addEventListener('input', function() {
      const password = this.value;
      let strength = 0;

      if (password.length >= 6) strength++;
      if (password.match(/[a-z]/)) strength++;
      if (password.match(/[A-Z]/)) strength++;
      if (password.match(/[0-9]/)) strength++;
      if (password.match(/[^a-zA-Z0-9]/)) strength++;

      let strengthClass = 'weak';
      let strengthWidth = '25%';

      if (strength >= 3) {
        strengthClass = 'medium';
        strengthWidth = '66%';
      }
      if (strength >= 4) {
        strengthClass = 'strong';
        strengthWidth = '100%';
      }

      passwordStrength.className = `password-strength ${strengthClass}`;
      passwordStrength.style.width = strengthWidth;
    });

    // File input preview
    const photoInput = document.getElementById('photo');
    const fileLabel = document.querySelector('.file-input-label');

    photoInput?.addEventListener('change', function(e) {
      const file = e.target.files[0];
      if (file) {
        const fileName = file.name;
        const fileSize = (file.size / 1024).toFixed(2) + ' KB';
        fileLabel.innerHTML = `<i class="ri-image-line"></i> ${fileName} (${fileSize})`;
      }
    });

    function showNotification(message, type) {
      const notification = document.createElement('div');
      notification.className = `notification ${type}`;
      notification.textContent = message;
      notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        background: ${type === 'success' ? '#4CAF50' : '#f44336'};
        color: white;
        border-radius: 5px;
        z-index: 10000;
        transition: all 0.3s ease;
      `;

      document.body.appendChild(notification);

      setTimeout(() => {
        notification.style.opacity = '0';
        setTimeout(() => {
          if (document.body.contains(notification)) {
            document.body.removeChild(notification);
          }
        }, 300);
      }, 3000);
    }
  </script>
</body>

</html>