<?php
require_once 'config/database.php';

// Language setup
$lang = $_GET['lang'] ?? ($_SESSION['lang'] ?? 'id');
$_SESSION['lang'] = $lang;

// Check if user is logged in
if (!isLoggedIn()) {
  header('Location: auth/login.php?redirect=profile');
  exit;
}

// Ensure photo column exists (for profile picture)
$photoColumn = $conn->query("SHOW COLUMNS FROM users LIKE 'photo'");
if ($photoColumn && $photoColumn->num_rows === 0) {
  $conn->query("ALTER TABLE users ADD COLUMN photo VARCHAR(255) NULL DEFAULT NULL AFTER address");
}

// Get current user data
$user = getCurrentUser();
$userDetails = $conn->query("SELECT * FROM users WHERE id = " . $user['id'])->fetch_assoc();

// Handle delete photo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_photo'])) {
  if (!empty($userDetails['photo']) && file_exists($userDetails['photo'])) {
    @unlink($userDetails['photo']);
  }
  
  $stmt = $conn->prepare("UPDATE users SET photo = NULL WHERE id = ?");
  $stmt->bind_param("i", $user['id']);
  if ($stmt->execute()) {
    $_SESSION['success_message'] = 'Profile photo deleted successfully!';
  } else {
    $_SESSION['error_message'] = 'Failed to delete photo';
  }
  
  header('Location: profile.php');
  exit;
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
  $fullName = trim($_POST['full_name'] ?? '');
  $phone = trim($_POST['phone'] ?? '');
  $address = trim($_POST['address'] ?? '');

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
      $mimeType = finfo_file($fileInfo, $_FILES['photo']['tmp_name']);
      finfo_close($fileInfo);

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
        $safeName = preg_replace('/[^A-Za-z0-9_\.-]/', '_', basename($_FILES['photo']['name']));
        $filename = time() . '_' . $safeName;
        $targetPath = $uploadDir . $filename;

        if (move_uploaded_file($_FILES['photo']['tmp_name'], $targetPath)) {
          // Delete old photo if exists and not same as new
          if (!empty($userDetails['photo']) && file_exists($userDetails['photo'])) {
            @unlink($userDetails['photo']);
          }
          $photoPath = $targetPath;
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

  if (strlen($newPassword) < 8) {
    $errors[] = 'New password must be at least 8 characters';
  }

  if ($newPassword !== $confirmPassword) {
    $errors[] = 'Passwords do not match';
  }

  if (empty($errors)) {
    // Verify current password
    if (password_verify($currentPassword, $userDetails['password'])) {
      if (password_verify($newPassword, $userDetails['password'])) {
        $errors[] = 'New password must be different from current password';
      } else {
        // Update password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashedPassword, $user['id']);

        if ($stmt->execute()) {
          $_SESSION['success_message'] = 'Password changed successfully!';
        } else {
          $_SESSION['error_message'] = 'Failed to change password';
        }
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
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Profile - Parfumé Lux</title>
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="auth.css">
  <link rel="stylesheet" href="perfume-cards.css">
  <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    /* Profile Page Specific Styles */
    body {
      background: linear-gradient(135deg, #fdecec 0%, #f5cdcd 50%, #fdecec 100%);
      min-height: 100vh;
    }

    .back-home {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      color: var(--text-dark);
      font-weight: 600;
      font-size: 0.95rem;
      text-decoration: none;
      transition: all 0.3s ease;
      padding: 10px 20px;
      background: var(--white);
      border-radius: 25px;
      box-shadow: 0 2px 10px rgba(205, 127, 127, 0.1);
    }

    .back-home:hover {
      color: var(--hover-color);
      transform: translateX(-5px);
      box-shadow: 0 4px 15px rgba(205, 127, 127, 0.2);
    }

    .profile-section {
      padding: 40px 0 80px;
    }

    .section-header {
      text-align: center;
      margin-bottom: 40px;
      padding: 40px 20px;
      background: var(--white);
      border-radius: 20px;
      box-shadow: 0 10px 30px rgba(205, 127, 127, 0.1);
    }

    .section-title {
      font-family: var(--header-font);
      font-size: 3rem;
      font-weight: bold;
      background: var(--gradient-color);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      margin-bottom: 10px;
      text-transform: uppercase;
      letter-spacing: 2px;
    }

    .section-subtitle {
      font-size: 1.1rem;
      color: var(--light-text);
      margin-bottom: 0;
    }

    .profile-actions {
      display: flex;
      justify-content: center;
      gap: 15px;
      margin-top: 20px;
    }

    /* Profile Container */
    .profile-container {
      display: grid;
      grid-template-columns: 350px 1fr;
      gap: 30px;
      align-items: start;
    }

    /* Profile Sidebar */
    .profile-sidebar {
      background: var(--white);
      border-radius: 20px;
      padding: 30px;
      box-shadow: 0 10px 30px rgba(205, 127, 127, 0.1);
      position: sticky;
      top: 20px;
    }

    .profile-photo {
      width: 150px;
      height: 150px;
      margin: 0 auto 25px;
      border-radius: 50%;
      overflow: hidden;
      border: 5px solid transparent;
      background: linear-gradient(white, white) padding-box,
                  var(--gradient-color) border-box;
      box-shadow: 0 10px 30px rgba(205, 127, 127, 0.2);
    }

    .profile-photo img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .btn-delete-photo {
      width: 100%;
      padding: 10px 15px;
      background: var(--white);
      color: #e74c3c;
      border: 2px solid #e74c3c;
      border-radius: 20px;
      font-weight: 600;
      font-size: 0.9rem;
      cursor: pointer;
      transition: all 0.3s ease;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .btn-delete-photo:hover {
      background: #e74c3c;
      color: var(--white);
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(231, 76, 60, 0.3);
    }

    .profile-info {
      text-align: center;
    }

    .profile-info h3 {
      font-size: 1.5rem;
      font-weight: 700;
      color: var(--text-dark);
      margin-bottom: 20px;
      font-family: var(--header-font);
    }

    .profile-info p {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      font-size: 0.95rem;
      color: var(--light-text);
      margin-bottom: 12px;
      padding: 10px;
      background: linear-gradient(135deg, #f5cdcd 0%, #fdecec 100%);
      border-radius: 10px;
    }

    .profile-info p i {
      color: var(--btn-color);
      font-size: 1.1rem;
    }

    /* Profile Main */
    .profile-main {
      display: flex;
      flex-direction: column;
      gap: 25px;
    }

    .profile-card {
      background: var(--white);
      border-radius: 20px;
      box-shadow: 0 10px 30px rgba(205, 127, 127, 0.1);
      overflow: hidden;
      transition: all 0.3s ease;
    }

    .profile-card:hover {
      box-shadow: 0 15px 40px rgba(205, 127, 127, 0.15);
    }

    .card-header {
      background: linear-gradient(135deg, #f5cdcd 0%, #cc7f7f 100%);
      padding: 20px 30px;
    }

    .card-header h3 {
      font-size: 1.3rem;
      font-weight: 700;
      color: var(--white);
      margin: 0;
      font-family: var(--header-font);
      text-transform: uppercase;
      letter-spacing: 1px;
    }

    .card-body {
      padding: 30px;
    }

    /* Form Styles */
    .form-group {
      margin-bottom: 25px;
    }

    .form-group label {
      display: block;
      font-size: 0.95rem;
      font-weight: 600;
      color: var(--text-dark);
      margin-bottom: 8px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .form-group input[type="text"],
    .form-group input[type="email"],
    .form-group input[type="tel"],
    .form-group input[type="password"],
    .form-group textarea {
      width: 100%;
      padding: 12px 15px;
      border: 2px solid #e0e0e0;
      border-radius: 10px;
      font-size: 1rem;
      color: var(--text-dark);
      background: var(--white);
      transition: all 0.3s ease;
      font-family: 'Montserrat', sans-serif;
    }

    .form-group input:focus,
    .form-group textarea:focus {
      outline: none;
      border-color: var(--btn-color);
      box-shadow: 0 0 0 3px rgba(205, 127, 127, 0.1);
    }

    .form-group input:disabled {
      background: #f5f5f5;
      cursor: not-allowed;
      color: #999;
    }

    .form-group textarea {
      resize: vertical;
      min-height: 80px;
    }

    /* File Input */
    .file-input-wrapper {
      position: relative;
    }

    .file-input-wrapper input[type="file"] {
      position: absolute;
      opacity: 0;
      width: 0;
      height: 0;
    }

    .file-input-label {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      padding: 15px 20px;
      background: linear-gradient(135deg, #f5cdcd 0%, #fdecec 100%);
      border: 2px dashed var(--btn-color);
      border-radius: 10px;
      color: var(--text-dark);
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
    }

    .file-input-label:hover {
      background: linear-gradient(135deg, #cc7f7f 0%, #f5cdcd 100%);
      color: var(--white);
      border-color: var(--hover-color);
    }

    .file-input-label i {
      font-size: 1.3rem;
    }

    /* Password Strength */
    .password-strength {
      height: 4px;
      background: #e0e0e0;
      border-radius: 2px;
      margin-top: 8px;
      transition: all 0.3s ease;
    }

    .password-strength.weak {
      background: #e74c3c;
      width: 33%;
    }

    .password-strength.medium {
      background: #f39c12;
      width: 66%;
    }

    .password-strength.strong {
      background: #27ae60;
      width: 100%;
    }

    /* Buttons */
    .btn-primary,
    .btn-secondary {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 15px 25px;
      border: none;
      border-radius: 25px;
      font-weight: 600;
      font-size: 1rem;
      cursor: pointer;
      transition: all 0.3s ease;
      text-decoration: none;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      font-family: 'Montserrat', sans-serif;
    }

    .btn-primary {
      background: linear-gradient(135deg, var(--btn-color) 0%, var(--hover-color) 100%);
      color: var(--white);
    }

    .btn-primary:hover {
      transform: translateY(-3px);
      box-shadow: 0 10px 25px rgba(205, 127, 127, 0.3);
    }

    .btn-secondary {
      background: var(--white);
      color: var(--text-dark);
      border: 2px solid var(--btn-color);
    }

    .btn-secondary:hover {
      background: var(--btn-color);
      color: var(--white);
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(205, 127, 127, 0.2);
    }

    .btn-block {
      width: 100%;
    }

    /* Notification */
    .notification {
      position: fixed;
      top: 20px;
      right: 20px;
      padding: 15px 25px;
      border-radius: 10px;
      color: var(--white);
      font-weight: 600;
      z-index: 1000;
      animation: slideInRight 0.3s ease;
      box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
      max-width: 400px;
    }

    .notification.success {
      background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
    }

    .notification.error {
      background: linear-gradient(135deg, #c0392b 0%, #e74c3c 100%);
    }

    @keyframes slideInRight {
      from {
        transform: translateX(400px);
        opacity: 0;
      }
      to {
        transform: translateX(0);
        opacity: 1;
      }
    }

    /* Responsive Design */
    @media (max-width: 1024px) {
      .profile-container {
        grid-template-columns: 1fr;
      }

      .profile-sidebar {
        position: static;
      }
    }

    @media (max-width: 768px) {
      .section-title {
        font-size: 2rem;
      }

      .section-subtitle {
        font-size: 0.95rem;
      }

      .profile-sidebar {
        padding: 25px;
      }

      .profile-photo {
        width: 120px;
        height: 120px;
      }

      .profile-info h3 {
        font-size: 1.3rem;
      }

      .profile-info p {
        font-size: 0.85rem;
        flex-direction: column;
        gap: 5px;
      }

      .card-header {
        padding: 15px 20px;
      }

      .card-header h3 {
        font-size: 1.1rem;
      }

      .card-body {
        padding: 20px;
      }

      .form-group {
        margin-bottom: 20px;
      }
    }

    @media (max-width: 480px) {
      .profile-section {
        padding: 20px 0 60px;
      }

      .section-header {
        padding: 30px 15px;
      }

      .section-title {
        font-size: 1.6rem;
      }

      .profile-sidebar {
        padding: 20px;
      }

      .profile-photo {
        width: 100px;
        height: 100px;
      }

      .card-body {
        padding: 15px;
      }

      .btn-primary,
      .btn-secondary {
        padding: 12px 20px;
        font-size: 0.9rem;
      }

      .notification {
        left: 10px;
        right: 10px;
        max-width: none;
      }
    }

    /* Animation for cards */
    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .profile-card {
      animation: fadeInUp 0.6s ease forwards;
    }

    .profile-card:nth-child(1) {
      animation-delay: 0.1s;
    }

    .profile-card:nth-child(2) {
      animation-delay: 0.2s;
    }
  </style>
</head>

<body class="<?php echo isLoggedIn() ? 'user-logged-in' : 'user-logged-out'; ?>">
  <div style="padding: 1rem 2rem;">
    <a href="index.php" class="back-home"><i class="ri-arrow-left-line"></i> Kembali ke Beranda</a>
  </div>

  <!-- Profile Section -->
  <section class="profile-section">
    <div class="container">
      <div class="section-header" style="text-align: center;">
        <h1 class="section-title">My Profile</h1>
        <p class="section-subtitle">Manage your personal information and preferences</p>
        <div class="profile-actions" style="margin-top: 12px;">
          <a href="orders.php" class="btn-secondary" style="max-width: 220px; width: 100%;">Lihat Pesanan Saya</a>
        </div>
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
          <?php if (!empty($userDetails['photo'])): ?>
            <form method="POST" style="margin-top: 15px;">
              <button type="submit" name="delete_photo" class="btn-delete-photo" onclick="return confirm('Are you sure you want to delete your profile photo?')">
                <i class="ri-delete-bin-line"></i> Delete Photo
              </button>
            </form>
          <?php endif; ?>
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

  <!-- ===== FOOTER ===== -->
  <footer class="footer">
    <div class="container">
      <div class="footer-content">
        <div class="footer-section">
          <h3>Parfum Lux</h3>
          <p>Premium parfume store dengan koleksi eksklusif wewangian berkualitas tinggi.</p>
          <div class="social-links">
            <a href="https://www.facebook.com/" target="_blank" rel="noopener noreferrer" title="Facebook"><i class="ri-facebook-line"></i></a>
            <a href="https://www.instagram.com/" target="_blank" rel="noopener noreferrer" title="Instagram"><i class="ri-instagram-line"></i></a>
            <a href="https://twitter.com/" target="_blank" rel="noopener noreferrer" title="Twitter"><i class="ri-twitter-line"></i></a>
            <a href="https://wa.me/6281234567890" target="_blank" rel="noopener noreferrer" title="WhatsApp"><i class="ri-whatsapp-line"></i></a>
          </div>
        </div>

        <div class="footer-section">
          <h4>Quick Links</h4>
          <ul>
            <li><a href="index.php#about">Tentang</a></li>
            <li><a href="index.php#products">Produk</a></li>
            <li><a href="index.php#contact">Kontak</a></li>
          </ul>
        </div>

        <div class="footer-section">
          <h4>Customer Service</h4>
          <ul>
            <li><a href="shipping-info.php">Shipping Info</a></li>
            <li><a href="faq.php">FAQ</a></li>
          </ul>
        </div>

        <div class="footer-section">
          <h4>Contact Info</h4>
          <ul>
            <li><i class="ri-map-pin-line"></i> Jl. Sudirman No. 123, Jakarta</li>
            <li><i class="ri-phone-line"></i> +62 21 1234 5678</li>
            <li><i class="ri-mail-line"></i> info@parfumlux.com</li>
          </ul>
        </div>
      </div>

      <div class="footer-bottom">
        <p>&copy; 2024 Parfumé Lux. All rights reserved. | Developed by Kelompok 2</p>
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
