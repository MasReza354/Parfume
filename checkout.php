<?php
require_once 'config/database.php';

// Check if user is logged in
if (!isLoggedIn()) {
  header('Location: auth/login.php?redirect=checkout');
  exit;
}

// Check if cart is not empty
if (empty($_SESSION['cart'])) {
  $_SESSION['error_message'] = 'Keranjang belanja Anda kosong';
  header('Location: cart.php');
  exit;
}

// Handle order creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
  
  // Debug logging
  error_log("Checkout POST request received");
  error_log("Shipping address: " . ($_POST['shipping_address'] ?? 'not set'));
  error_log("Payment method: " . ($_POST['payment_method'] ?? 'not set'));
  error_log("User logged in: " . (isLoggedIn() ? 'yes' : 'no'));
  error_log("Cart has items: " . (isset($_SESSION['cart']) && !empty($_SESSION['cart']) ? 'yes' : 'no'));
  $shippingAddress = $_POST['shipping_address'] ?? '';
  $paymentMethod = $_POST['payment_method'] ?? '';
  $notes = $_POST['notes'] ?? '';

  $errors = [];

  if (empty($shippingAddress)) {
    $errors[] = 'Alamat pengiriman wajib diisi';
  }

  if (empty($paymentMethod)) {
    $errors[] = 'Metode pembayaran wajib dipilih';
  }

  // Validate shipping address for location-based shipping
  $javaLocations = ['jakarta', 'bogor', 'depok', 'tangerang', 'bekasi'];
  $sumatraLocations = ['medan', 'palembang', 'bandar lampung', 'batam'];
  $baliLocations = ['denpasar', 'kuta', 'seminyak', 'ubud', 'canggu'];
  $kalimantanLocations = ['balikpapan', 'samarinda', 'banjarmasin', 'pontianak'];
  $sulawesiLocations = ['makassar', 'manado', 'palu', 'kendari'];
  $papuaLocations = ['jayapura', 'sorong', 'manokwari'];
  $nttLocations = ['kupang', 'ende', 'maumere'];

  $addressLower = strtolower($shippingAddress);
  $detectedLocation = null;
  $shippingFee = 50000; // Default shipping fee

  // Detect location and set shipping fee
  foreach ($javaLocations as $location) {
    if (strpos($addressLower, $location) !== false) {
      $detectedLocation = 'Java';
      $shippingFee = 50000;
      break;
    }
  }

  if (!$detectedLocation) {
    foreach ($sumatraLocations as $location) {
      if (strpos($addressLower, $location) !== false) {
        $detectedLocation = 'Sumatra';
        $shippingFee = 75000;
        break;
      }
    }
  }

  if (!$detectedLocation) {
    foreach ($baliLocations as $location) {
      if (strpos($addressLower, $location) !== false) {
        $detectedLocation = 'Bali';
        $shippingFee = 100000;
        break;
      }
    }
  }

  if (!$detectedLocation) {
    foreach ($kalimantanLocations as $location) {
      if (strpos($addressLower, $location) !== false) {
        $detectedLocation = 'Kalimantan';
        $shippingFee = 100000;
        break;
      }
    }
  }

  if (!$detectedLocation) {
    foreach ($sulawesiLocations as $location) {
      if (strpos($addressLower, $location) !== false) {
        $detectedLocation = 'Sulawesi';
        $shippingFee = 125000;
        break;
      }
    }
  }

  if (!$detectedLocation) {
    foreach ($papuaLocations as $location) {
      if (strpos($addressLower, $location) !== false) {
        $detectedLocation = 'Papua';
        $shippingFee = 150000;
        break;
      }
    }
  }

  if (!$detectedLocation) {
    foreach ($nttLocations as $location) {
      if (strpos($addressLower, $location) !== false) {
        $detectedLocation = 'NTT';
        $shippingFee = 125000;
        break;
      }
    }
  }

  if (empty($errors)) {
    // Calculate total
    $totalAmount = 0;
    $orderItems = [];

    foreach ($_SESSION['cart'] as $productId => $quantity) {
      if ($productId === 'bubble_wrap' || $productId === 'wooden_packing') {
        $name = ($productId === 'bubble_wrap') ? 'Bubble Wrap Tambahan' : 'Packing Kayu';
        $price = ($productId === 'bubble_wrap') ? 5000 : 15000;
        $subtotal = $price * $quantity;

        $orderItems[] = [
          'product_id' => null,
          'product_name' => $name,
          'product_price' => $price,
          'quantity' => $quantity,
          'subtotal' => $subtotal
        ];
        $totalAmount += $subtotal;
      } else {
        $product = $conn->query("SELECT * FROM products WHERE id = $productId")->fetch_assoc();
        if ($product) {
          $subtotal = $product['price'] * $quantity;
          $orderItems[] = [
            'product_id' => $productId,
            'product_name' => $product['name'],
            'product_price' => $product['price'],
            'quantity' => $quantity,
            'subtotal' => $subtotal
          ];
          $totalAmount += $subtotal;
        }
      }
    }

    $grandTotal = $totalAmount + $shippingFee;

    // Generate order number with format RDL-HHBBTTXXX
    $today = date('dmY'); // Get today's date in DMYY format for counting
    $datePrefix = date('dmy'); // Format: DDMMYY (we'll use DMYY for prefix)
    
    // Count orders created today to get the sequence number
    $todayStart = date('Y-m-d 00:00:00');
    $todayEnd = date('Y-m-d 23:59:59');
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM orders WHERE created_at BETWEEN ? AND ?");
    $stmt->bind_param("ss", $todayStart, $todayEnd);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $orderCount = $result['count'];
    
    // Generate 3-digit sequence number (001, 002, etc)
    $sequence = sprintf('%03d', $orderCount + 1);
    
    // Format: RDL-HHBBTTXXX (Hari-Bulan-Tahun-Urut)
    $orderNumber = 'RDL-' . date('dmy') . $sequence;

    // Create order
    $insertOrder = $conn->prepare("INSERT INTO orders (user_id, order_number, total_amount, shipping_fee, payment_method, shipping_address, notes, payment_status, order_status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', 'pending')");
    $insertOrder->bind_param("isddsss", $_SESSION['user_id'], $orderNumber, $grandTotal, $shippingFee, $paymentMethod, $shippingAddress, $notes);

    if ($insertOrder->execute()) {
      $orderId = $conn->insert_id;

      // Insert order items
      $orderItemsSuccess = true;
      $insertItem = $conn->prepare("INSERT INTO order_items (order_id, product_id, product_name, product_price, quantity, price, subtotal) VALUES (?, ?, ?, ?, ?, ?, ?)");

      if (!$insertItem) {
        $orderItemsSuccess = false;
        $errors[] = 'Gagal menyiapkan penyimpanan item pesanan.';
        error_log('Prepare order_items failed: ' . $conn->error);
      } else {
        $itemProductId = null;
        $itemName = '';
        $itemProductPrice = 0;
        $itemQuantity = 0;
        $itemPrice = 0;
        $itemSubtotal = 0;

        $insertItem->bind_param(
          "iisdidd",
          $orderId,
          $itemProductId,
          $itemName,
          $itemProductPrice,
          $itemQuantity,
          $itemPrice,
          $itemSubtotal
        );

        foreach ($orderItems as $item) {
          $itemProductId = $item['product_id'];
          if ($itemProductId !== null) {
            $itemProductId = (int) $itemProductId;
          }
          $itemName = $item['product_name'];
          $itemProductPrice = $item['product_price'];
          $itemQuantity = $item['quantity'];
          $itemPrice = $item['product_price'];
          $itemSubtotal = $item['subtotal'];

          if (!$insertItem->execute()) {
            $orderItemsSuccess = false;
            $errors[] = 'Gagal menambahkan item pesanan. Silakan coba lagi.';
            error_log('Order item insert failed: ' . $insertItem->error);
            break;
          }
        }
      }

      if ($orderItemsSuccess) {
        // Clear cart
        unset($_SESSION['cart']);

        $_SESSION['success_message'] = "Pesanan #$orderNumber berhasil dibuat! Total dengan ongkir: " . formatRupiah($grandTotal);
        if (PHP_SAPI === 'cli') {
          echo "CLI_ORDER_SUCCESS:$orderNumber\n";
          return;
        }

        header('Location: orders.php?order=' . $orderNumber);
        exit;
      }

      // Rollback partial order if items failed
      $conn->query("DELETE FROM order_items WHERE order_id = " . (int) $orderId);
      $conn->query("DELETE FROM orders WHERE id = " . (int) $orderId);
    } else {
      $error = $insertOrder->error;
      error_log("Order creation failed: " . $error);
      $errors[] = 'Gagal membuat pesanan. Silakan coba lagi. Error: ' . $error;
    }
  }

  $_SESSION['checkout_errors'] = $errors;
  
  // Debug: Log errors for troubleshooting
  if (!empty($errors)) {
    error_log("Checkout errors: " . implode(', ', $errors));
  }
}

// Get cart items for display
$cartItems = [];
$totalAmount = 0;

// Additional items
$additionalItems = [
  'bubble_wrap' => ['name' => 'Bubble Wrap Tambahan', 'price' => 5000],
  'wooden_packing' => ['name' => 'Packing Kayu', 'price' => 15000]
];

foreach ($_SESSION['cart'] as $productId => $quantity) {
  if ($productId === 'bubble_wrap' || $productId === 'wooden_packing') {
    $item = $additionalItems[$productId];
    $item['id'] = $productId;
    $item['quantity'] = $quantity;
    $item['subtotal'] = $item['price'] * $quantity;
    $cartItems[] = $item;
    $totalAmount += $item['subtotal'];
  } else {
    $product = $conn->query("SELECT * FROM products WHERE id = $productId AND status = 'active'")->fetch_assoc();
    if ($product) {
      $product['quantity'] = $quantity;
      $product['subtotal'] = $product['price'] * $quantity;
      $cartItems[] = $product;
      $totalAmount += $product['subtotal'];
    }
  }
}

// Default shipping fee (will be calculated based on address)
$shippingFee = 50000;
$grandTotal = $totalAmount + $shippingFee;

// Get user details for pre-filling
$userDetails = $conn->query("SELECT * FROM users WHERE id = " . $_SESSION['user_id'])->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pembayaran - Ardéliana Lux</title>
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="cart.css">
  <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    body {
      overflow-x: hidden;
      overflow-y: auto;
    }
    
    .checkout-section {
      padding: 80px 0;
      min-height: 60vh;
    }

    .checkout-container {
      max-width: 1200px;
      margin: 0 auto;
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 30px;
    }

    .checkout-form {
      background: white;
      border-radius: 10px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
      overflow: hidden;
    }

    .form-header {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 20px;
      text-align: center;
    }

    .form-header h3 {
      margin: 0;
      font-size: 1.1rem;
      font-weight: 600;
    }

    .form-body {
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
    .form-group select,
    .form-group textarea {
      width: 100%;
      padding: 12px 15px;
      border: 2px solid #e9ecef;
      border-radius: 8px;
      font-size: 1rem;
      transition: border-color 0.3s ease;
    }

    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
      outline: none;
      border-color: #667eea;
      box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .form-group textarea {
      resize: vertical;
      min-height: 100px;
    }

    .payment-methods {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 15px;
      margin-top: 10px;
    }

    .payment-method {
      display: flex;
      align-items: center;
      padding: 15px;
      border: 2px solid #e9ecef;
      border-radius: 8px;
      cursor: pointer;
      transition: all 0.3s ease;
    }

    .payment-method:hover {
      border-color: #667eea;
      box-shadow: 0 2px 8px rgba(102, 126, 234, 0.1);
    }

    .payment-method input[type="radio"] {
      margin-right: 10px;
    }

    .payment-method.selected {
      border-color: #667eea;
      background: #f8f9ff;
    }

    .shipping-info {
      background: #e3f2fd;
      padding: 15px;
      border-radius: 8px;
      margin-top: 20px;
      border-left: 4px solid #2196f3;
    }

    .shipping-info h4 {
      margin: 0 0 10px 0;
      color: #1976d2;
      font-size: 0.95rem;
    }

    .shipping-info p {
      margin: 0;
      color: #1565c0;
      font-size: 0.85rem;
    }

    .order-summary {
      background: white;
      border-radius: 10px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
      overflow: hidden;
      position: sticky;
      top: 20px;
      max-height: calc(100vh - 100px);
      overflow-y: auto;
    }

    .summary-header {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 20px;
      text-align: center;
    }

    .summary-header h3 {
      margin: 0;
      font-size: 1.1rem;
      font-weight: 600;
    }

    .summary-body {
      padding: 25px;
    }

    .summary-items {
      margin-bottom: 20px;
    }

    .summary-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 10px 0;
      border-bottom: 1px solid #e9ecef;
    }

    .summary-item:last-child {
      border-bottom: none;
    }

    .item-info {
      flex: 1;
    }

    .item-name {
      font-weight: 600;
      margin-bottom: 5px;
    }

    .item-details {
      color: #6c757d;
      font-size: 0.9rem;
    }

    .item-price {
      font-weight: 600;
      color: #2c3e50;
    }

    .summary-totals {
      background: #f8f9fa;
      padding: 15px;
      border-radius: 8px;
      margin-top: 15px;
    }

    .summary-row {
      display: flex;
      justify-content: space-between;
      margin-bottom: 8px;
    }

    .summary-row.total {
      font-weight: 600;
      font-size: 1.1rem;
      color: #2c3e50;
      border-top: 2px solid #dee2e6;
      padding-top: 8px;
      margin-top: 8px;
    }

    .place-order-btn {
      width: 100%;
      padding: 15px;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      border: none;
      border-radius: 8px;
      font-size: 1.1rem;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      margin-top: 20px;
    }

    .place-order-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
    }

    .place-order-btn:disabled {
      background: #6c757d;
      cursor: not-allowed;
      transform: none;
      box-shadow: none;
    }

    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }
    
    .animate-spin {
      animation: spin 1s linear infinite;
      display: inline-block;
    }
    
    @media (max-width: 768px) {
      .checkout-container {
        grid-template-columns: 1fr;
        gap: 20px;
      }

      .payment-methods {
        grid-template-columns: 1fr;
      }

      .order-summary {
        position: static;
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
        <li><a href="index.php" class="nav-link">Beranda</a></li>
        <li><a href="index.php#about" class="nav-link">Tentang</a></li>
        <li><a href="index.php#contact" class="nav-link">Kontak</a></li>
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
                <a href="profile.php"><i class="ri-user-line"></i> Profil</a>
                <a href="favorites.php"><i class="ri-heart-line"></i> Favorit</a>
                <a href="cart.php"><i class="ri-shopping-cart-line"></i> Keranjang</a>
                <a href="orders.php"><i class="ri-shopping-bag-line"></i> Pesanan Saya</a>
                <a href="auth/logout.php"><i class="ri-logout-box-line"></i> Keluar</a>
              </div>
            </div>
          <?php else: ?>
            <a href="auth/login.php" class="btn-login">Login</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </nav>

  <!-- Checkout Section -->
  <section class="checkout-section">
    <div class="container">
      <div class="section-header">
        <h1 class="section-title">Pembayaran</h1>
        <p class="section-subtitle">Lengkapi detail pesanan Anda</p>
      </div>

      <?php if (isset($_SESSION['checkout_errors'])): ?>
        <div class="notification error">
          <?php
          foreach ($_SESSION['checkout_errors'] as $error) {
            echo '<p>' . htmlspecialchars($error) . '</p>';
          }
          unset($_SESSION['checkout_errors']);
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

      <form method="POST" id="checkoutForm">
        <div class="checkout-container">
          <!-- Checkout Form -->
          <div class="checkout-form">
            <div class="form-header">
              <h3>Informasi Pengiriman & Pembayaran</h3>
            </div>
            <div class="form-body">
              <div class="form-group">
                <label for="full_name">Nama Lengkap</label>
                <input type="text" id="full_name" name="full_name"
                  value="<?php echo htmlspecialchars($userDetails['full_name'] ?? ''); ?>"
                  readonly style="background: #f8f9fa;">
              </div>

              <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email"
                  value="<?php echo htmlspecialchars($userDetails['email'] ?? ''); ?>"
                  readonly style="background: #f8f9fa;">
              </div>

              <div class="form-group">
                <label for="phone">Nomor Telepon</label>
                <input type="tel" id="phone" name="phone"
                  value="<?php echo htmlspecialchars($userDetails['phone'] ?? ''); ?>"
                  readonly style="background: #f8f9fa;">
              </div>

              <div class="form-group">
                <label for="shipping_address">Alamat Pengiriman *</label>
                <textarea id="shipping_address" name="shipping_address"
                  placeholder="Masukkan alamat pengiriman lengkap (jalan, kota, kode pos)"
                  required><?php echo htmlspecialchars($userDetails['address'] ?? ''); ?></textarea>
                <small style="color: #6c757d; font-size: 0.85rem;">
                  Sertakan: Alamat jalan, Kota, Kode pos. Biaya pengiriman akan dihitung berdasarkan lokasi Anda.
                </small>
              </div>

              <div class="shipping-info">
                <h4><i class="ri-truck-line"></i> Informasi Pengiriman</h4>
                <p>
                  <strong>Pulau Jawa:</strong> Rp 50.000<br>
                  <strong>Sumatera, Bali, Kalimantan:</strong> Rp 75.000 - Rp 100.000<br>
                  <strong>Sulawesi, NTT, Papua:</strong> Rp 125.000 - Rp 150.000<br>
                  <strong>Estimasi pengiriman:</strong> 2-5 hari kerja
                </p>
              </div>

              <div class="form-group">
                <label>Metode Pembayaran *</label>
                <div class="payment-methods">
                  <label class="payment-method">
                    <input type="radio" name="payment_method" value="bank_transfer" required>
                    <div>
                      <i class="ri-bank-line"></i>
                      <strong>Transfer Bank</strong>
                      <p style="margin: 5px 0 0 0; color: #6c757d; font-size: 0.85rem;">
                        Transfer ke BCA, Mandiri, atau BNI
                      </p>
                    </div>
                  </label>

                  <label class="payment-method">
                    <input type="radio" name="payment_method" value="ewallet">
                    <div>
                      <i class="ri-wallet-3-line"></i>
                      <strong>E-Wallet</strong>
                      <p style="margin: 5px 0 0 0; color: #6c757d; font-size: 0.85rem;">
                        GoPay, OVO, DANA, ShopeePay
                      </p>
                    </div>
                  </label>

                  <label class="payment-method">
                    <input type="radio" name="payment_method" value="cod">
                    <div>
                      <i class="ri-money-dollar-circle-line"></i>
                      <strong>Bayar di Tempat</strong>
                      <p style="margin: 5px 0 0 0; color: #6c757d; font-size: 0.85rem;">
                        Bayar saat barang diterima
                      </p>
                    </div>
                  </label>

                  <label class="payment-method">
                    <input type="radio" name="payment_method" value="credit_card">
                    <div>
                      <i class="ri-bank-card-line"></i>
                      <strong>Kartu Kredit</strong>
                      <p style="margin: 5px 0 0 0; color: #6c757d; font-size: 0.85rem;">
                        Visa, Mastercard, JCB
                      </p>
                    </div>
                  </label>
                </div>
              </div>

              <div class="form-group">
                <label for="notes">Catatan Pesanan (Opsional)</label>
                <textarea id="notes" name="notes"
                  placeholder="Instruksi khusus untuk pesanan Anda..."></textarea>
              </div>
            </div>
          </div>

          <!-- Order Summary -->
          <div class="order-summary">
            <div class="summary-header">
              <h3>Ringkasan Pesanan</h3>
            </div>
            <div class="summary-body">
              <div class="summary-items">
                <?php foreach ($cartItems as $item): ?>
                  <div class="summary-item">
                    <div class="item-info">
                      <div class="item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                      <div class="item-details">Jml: <?php echo $item['quantity']; ?></div>
                    </div>
                    <div class="item-price"><?php echo formatRupiah($item['subtotal']); ?></div>
                  </div>
                <?php endforeach; ?>
              </div>

              <div class="summary-totals">
                <div class="summary-row">
                  <span>Subtotal:</span>
                  <span id="subtotal"><?php echo formatRupiah($totalAmount); ?></span>
                </div>
                <div class="summary-row">
                  <span>Biaya Pengiriman:</span>
                  <span id="shippingFee"><?php echo formatRupiah($shippingFee); ?></span>
                </div>
                <div class="summary-row total">
                  <span>Total:</span>
                  <span id="grandTotal"><?php echo formatRupiah($grandTotal); ?></span>
                </div>
              </div>

              <button type="submit" name="place_order" class="place-order-btn">
                <i class="ri-shopping-bag-line"></i> Buat Pesanan
              </button>
            </div>
          </div>
        </div>
      </form>
    </div>
  </section>

  <!-- Footer -->
  <footer class="footer">
    <div class="container">
      <div class="footer-content">
        <div class="footer-section">
          <h3>Tentang Kami</h3>
          <p>Ardéliana Lux - Destinasi Anda untuk parfum premium</p>
        </div>

        <div class="footer-section">
          <h3>Kontak</h3>
          <p>Email: info@ardeliana.com<br>
            Telepon: +62 21 5555 1234</p>
        </div>

        <div class="footer-section">
          <h3>Ikuti Kami</h3>
          <div class="social-links">
            <a href="#"><i class="ri-facebook-line"></i></a>
            <a href="#"><i class="ri-instagram-line"></i></a>
            <a href="#"><i class="ri-twitter-line"></i></a>
          </div>
        </div>
      </div>

      <div class="footer-bottom">
        <p>&copy; 2024 Ardéliana Lux. Hak cipta dilindungi.</p>
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

    // Payment method selection
    document.querySelectorAll('.payment-method input[type="radio"]').forEach(radio => {
      radio.addEventListener('change', function() {
        document.querySelectorAll('.payment-method').forEach(method => {
          method.classList.remove('selected');
        });
        this.closest('.payment-method').classList.add('selected');
      });
    });

    // Auto-select first payment method
    const firstPaymentMethod = document.querySelector('.payment-method input[type="radio"]');
    if (firstPaymentMethod) {
      firstPaymentMethod.checked = true;
      firstPaymentMethod.closest('.payment-method').classList.add('selected');
    }

    // Calculate shipping based on address
    function calculateShipping(address) {
      const javaLocations = ['jakarta', 'bogor', 'depok', 'tangerang', 'bekasi'];
      const sumatraLocations = ['medan', 'palembang', 'bandar lampung', 'batam'];
      const baliLocations = ['denpasar', 'kuta', 'seminyak', 'ubud', 'canggu'];
      const kalimantanLocations = ['balikpapan', 'samarinda', 'banjarmasin', 'pontianak'];
      const sulawesiLocations = ['makassar', 'manado', 'palu', 'kendari'];
      const papuaLocations = ['jayapura', 'sorong', 'manokwari'];
      const nttLocations = ['kupang', 'ende', 'maumere'];

      const addressLower = address.toLowerCase();
      let shippingFee = 50000; // Default

      // Detect location and set shipping fee
      for (const location of javaLocations) {
        if (addressLower.includes(location)) return 50000;
      }

      for (const location of sumatraLocations) {
        if (addressLower.includes(location)) return 75000;
      }

      for (const location of baliLocations) {
        if (addressLower.includes(location)) return 100000;
      }

      for (const location of kalimantanLocations) {
        if (addressLower.includes(location)) return 100000;
      }

      for (const location of sulawesiLocations) {
        if (addressLower.includes(location)) return 125000;
      }

      for (const location of papuaLocations) {
        if (addressLower.includes(location)) return 150000;
      }

      for (const location of nttLocations) {
        if (addressLower.includes(location)) return 125000;
      }

      return 50000; // Default
    }

    // Update shipping fee when address changes
    document.getElementById('shipping_address')?.addEventListener('input', function() {
      const address = this.value;
      const shippingFee = calculateShipping(address);
      const subtotal = <?php echo $totalAmount; ?>;
      const grandTotal = subtotal + shippingFee;

      document.getElementById('shippingFee').textContent = formatRupiah(shippingFee);
      document.getElementById('grandTotal').textContent = formatRupiah(grandTotal);
    });

    // Format Rupiah function
    function formatRupiah(amount) {
      return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0
      }).format(amount);
    }

    // Form validation
    document.getElementById('checkoutForm')?.addEventListener('submit', function(e) {
      console.log('Form submitting...');
      
      const shippingAddress = document.getElementById('shipping_address').value;
      const paymentMethod = document.querySelector('input[name="payment_method"]:checked');

      console.log('Shipping address:', shippingAddress);
      console.log('Payment method:', paymentMethod?.value);

      if (!shippingAddress.trim()) {
        e.preventDefault();
        showNotification('Alamat pengiriman wajib diisi', 'error');
        return false;
      }

      if (!paymentMethod) {
        e.preventDefault();
        showNotification('Silakan pilih metode pembayaran', 'error');
        return false;
      }

      // Show loading state
      const submitBtn = document.querySelector('.place-order-btn');
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="ri-loader-4-line animate-spin"></i> Memproses...';
      }
      
      console.log('Form validation passed, submitting...');
      return true;
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