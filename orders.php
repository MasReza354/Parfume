p<?php
  require_once 'config/database.php';

  // Check if user is logged in
  if (!isLoggedIn()) {
    header('Location: auth/login.php?redirect=orders');
    exit;
  }

  // Create orders table if not exists
  $conn->query("CREATE TABLE IF NOT EXISTS orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  order_number VARCHAR(50) UNIQUE NOT NULL,
  total_amount DECIMAL(10,2) NOT NULL,
  shipping_fee DECIMAL(10,2) DEFAULT 50000,
  payment_method VARCHAR(50) NOT NULL,
  payment_status ENUM('pending', 'paid', 'failed', 'refunded') DEFAULT 'pending',
  order_status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
  shipping_address TEXT NOT NULL,
  notes TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

  // Create order_items table if not exists
  $conn->query("CREATE TABLE IF NOT EXISTS order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  product_id INT NULL,
  product_name VARCHAR(255) NOT NULL,
  product_price DECIMAL(10,2) NOT NULL,
  quantity INT NOT NULL,
  subtotal DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
)");

  // Handle order creation from checkout
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_order'])) {
    $shippingAddress = $_POST['shipping_address'] ?? '';
    $paymentMethod = $_POST['payment_method'] ?? '';
    $notes = $_POST['notes'] ?? '';

    if (empty($shippingAddress) || empty($paymentMethod)) {
      $_SESSION['error_message'] = 'Shipping address and payment method are required';
      header('Location: checkout.php');
      exit;
    }

    if (empty($_SESSION['cart'])) {
      $_SESSION['error_message'] = 'Your cart is empty';
      header('Location: cart.php');
      exit;
    }

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

    $shippingFee = 50000; // Fixed shipping fee
    $grandTotal = $totalAmount + $shippingFee;

    // Generate order number
    $orderNumber = 'ORD-' . date('Ymd') . '-' . sprintf('%04d', rand(1, 9999));

    // Create order
    $insertOrder = $conn->prepare("INSERT INTO orders (user_id, order_number, total_amount, shipping_fee, payment_method, shipping_address, notes, payment_status, order_status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', 'pending')");
    $insertOrder->bind_param("isddsss", $_SESSION['user_id'], $orderNumber, $grandTotal, $shippingFee, $paymentMethod, $shippingAddress, $notes);

    if ($insertOrder->execute()) {
      $orderId = $conn->insert_id;

      // Insert order items
      $insertItem = $conn->prepare("INSERT INTO order_items (order_id, product_id, product_name, product_price, quantity, subtotal) VALUES (?, ?, ?, ?, ?, ?)");

      foreach ($orderItems as $item) {
        $insertItem->bind_param("iisdii", $orderId, $item['product_id'], $item['product_name'], $item['product_price'], $item['quantity'], $item['subtotal']);
        $insertItem->execute();
      }

      // Clear cart
      unset($_SESSION['cart']);

      $_SESSION['success_message'] = "Order #$orderNumber has been placed successfully!";
      header('Location: orders.php?order=' . $orderNumber);
      exit;
    } else {
      $_SESSION['error_message'] = 'Failed to create order. Please try again.';
      header('Location: checkout.php');
      exit;
    }
  }

  // Handle order status update (admin only handled in admin dashboard)
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order'])) {
    $orderId = $_POST['order_id'] ?? 0;

    if ($orderId > 0) {
      // Verify order belongs to current user
      $check = $conn->prepare("SELECT id FROM orders WHERE id = ? AND user_id = ? AND order_status IN ('pending', 'processing')");
      $check->bind_param("ii", $orderId, $_SESSION['user_id']);
      $check->execute();

      if ($check->get_result()->num_rows > 0) {
        $update = $conn->prepare("UPDATE orders SET order_status = 'cancelled', updated_at = NOW() WHERE id = ?");
        $update->bind_param("i", $orderId);
        $update->execute();

        $_SESSION['success_message'] = 'Order has been cancelled';
      }
    }

    header('Location: orders.php');
    exit;
  }

  // Get user orders
  $orders = [];
  $stmt = $conn->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
  $stmt->bind_param("i", $_SESSION['user_id']);
  $stmt->execute();
  $result = $stmt->get_result();

  while ($row = $result->fetch_assoc()) {
    // Get order items for each order
    $items = [];
    $itemsStmt = $conn->prepare("SELECT * FROM order_items WHERE order_id = ?");
    $itemsStmt->bind_param("i", $row['id']);
    $itemsStmt->execute();
    $itemsResult = $itemsStmt->get_result();

    while ($item = $itemsResult->fetch_assoc()) {
      $items[] = $item;
    }

    $row['items'] = $items;
    $orders[] = $row;
  }

  // Get specific order if requested
  $specificOrder = null;
  if (isset($_GET['order'])) {
    $orderNumber = $_GET['order'];
    $stmt = $conn->prepare("SELECT * FROM orders WHERE order_number = ? AND user_id = ?");
    $stmt->bind_param("si", $orderNumber, $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
      $specificOrder = $result->fetch_assoc();

      // Get order items
      $items = [];
      $itemsStmt = $conn->prepare("SELECT * FROM order_items WHERE order_id = ?");
      $itemsStmt->bind_param("i", $specificOrder['id']);
      $itemsStmt->execute();
      $itemsResult = $itemsStmt->get_result();

      while ($item = $itemsResult->fetch_assoc()) {
        $items[] = $item;
      }

      $specificOrder['items'] = $items;
    }
  }
  ?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Orders - Ardéliana Lux</title>
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="cart.css">
  <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    .orders-section {
      padding: 80px 0;
      min-height: 60vh;
    }

    .order-card {
      background: white;
      border-radius: 10px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
      margin-bottom: 20px;
      overflow: hidden;
      transition: all 0.3s ease;
    }

    .order-card:hover {
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    }

    .order-header {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 20px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 15px;
    }

    .order-number {
      font-size: 1.1rem;
      font-weight: 600;
    }

    .order-date {
      font-size: 0.9rem;
      opacity: 0.9;
    }

    .order-status {
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 0.85rem;
      font-weight: 500;
      text-transform: uppercase;
    }

    .status-pending {
      background: #ffc107;
      color: #000;
    }

    .status-processing {
      background: #17a2b8;
      color: white;
    }

    .status-shipped {
      background: #007bff;
      color: white;
    }

    .status-delivered {
      background: #28a745;
      color: white;
    }

    .status-cancelled {
      background: #dc3545;
      color: white;
    }

    .status-payment-pending {
      background: #6c757d;
      color: white;
    }

    .status-payment-paid {
      background: #28a745;
      color: white;
    }

    .order-body {
      padding: 20px;
    }

    .order-items {
      margin-bottom: 20px;
    }

    .order-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 15px 0;
      border-bottom: 1px solid #e9ecef;
    }

    .order-item:last-child {
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

    .item-quantity {
      color: #6c757d;
      margin: 0 15px;
    }

    .item-price {
      font-weight: 600;
      color: #2c3e50;
    }

    .order-summary {
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

    .order-actions {
      display: flex;
      gap: 10px;
      margin-top: 15px;
      flex-wrap: wrap;
    }

    .btn-order {
      padding: 8px 16px;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      font-weight: 500;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 5px;
      transition: all 0.3s ease;
    }

    .btn-view {
      background: #667eea;
      color: white;
    }

    .btn-view:hover {
      background: #5a67d8;
    }

    .btn-cancel {
      background: #dc3545;
      color: white;
    }

    .btn-cancel:hover {
      background: #c82333;
    }

    .btn-cancel:disabled {
      background: #6c757d;
      cursor: not-allowed;
      opacity: 0.6;
    }

    .empty-orders {
      text-align: center;
      padding: 60px 20px;
      color: #6c757d;
    }

    .empty-orders i {
      font-size: 4rem;
      margin-bottom: 20px;
      opacity: 0.5;
    }

    @media (max-width: 768px) {
      .order-header {
        flex-direction: column;
        align-items: stretch;
        text-align: center;
      }

      .order-item {
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
      }

      .item-quantity {
        margin: 0;
      }

      .order-actions {
        justify-content: center;
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
                <a href="profile.php"><i class="ri-user-line"></i> Profile</a>
                <a href="favorites.php"><i class="ri-heart-line"></i> Favorites</a>
                <a href="cart.php"><i class="ri-shopping-cart-line"></i> Cart</a>
                <a href="orders.php" class="active"><i class="ri-shopping-bag-line"></i> My Orders</a>
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

  <!-- Orders Section -->
  <section class="orders-section">
    <div class="container">
      <div class="section-header">
        <h1 class="section-title">My Orders</h1>
        <p class="section-subtitle">Track your order history and status</p>
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

      <?php if ($specificOrder): ?>
        <!-- Specific Order Detail -->
        <div class="order-card">
          <div class="order-header">
            <div>
              <div class="order-number">Order #<?php echo htmlspecialchars($specificOrder['order_number']); ?></div>
              <div class="order-date"><?php echo date('F d, Y H:i', strtotime($specificOrder['created_at'])); ?></div>
            </div>
            <div>
              <span class="order-status status-<?php echo $specificOrder['order_status']; ?>">
                <?php echo ucfirst($specificOrder['order_status']); ?>
              </span>
              <span class="order-status status-payment-<?php echo $specificOrder['payment_status']; ?>" style="margin-left: 10px;">
                <?php echo ucfirst($specificOrder['payment_status']); ?>
              </span>
            </div>
          </div>

          <div class="order-body">
            <h4>Order Items</h4>
            <div class="order-items">
              <?php foreach ($specificOrder['items'] as $item): ?>
                <div class="order-item">
                  <div class="item-info">
                    <div class="item-name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                    <div class="item-details">Price: <?php echo formatRupiah($item['product_price']); ?></div>
                  </div>
                  <div class="item-quantity">Qty: <?php echo $item['quantity']; ?></div>
                  <div class="item-price"><?php echo formatRupiah($item['subtotal']); ?></div>
                </div>
              <?php endforeach; ?>
            </div>

            <div class="order-summary">
              <div class="summary-row">
                <span>Subtotal:</span>
                <span><?php echo formatRupiah($specificOrder['total_amount'] - $specificOrder['shipping_fee']); ?></span>
              </div>
              <div class="summary-row">
                <span>Shipping Fee:</span>
                <span><?php echo formatRupiah($specificOrder['shipping_fee']); ?></span>
              </div>
              <div class="summary-row total">
                <span>Total:</span>
                <span><?php echo formatRupiah($specificOrder['total_amount']); ?></span>
              </div>
            </div>

            <?php if (!empty($specificOrder['notes'])): ?>
              <div style="margin-top: 15px;">
                <h5>Order Notes:</h5>
                <p><?php echo htmlspecialchars($specificOrder['notes']); ?></p>
              </div>
            <?php endif; ?>

            <div style="margin-top: 15px;">
              <h5>Shipping Address:</h5>
              <p><?php echo nl2br(htmlspecialchars($specificOrder['shipping_address'])); ?></p>
            </div>

            <div class="order-actions">
              <a href="orders.php" class="btn-order btn-view">
                <i class="ri-arrow-left-line"></i> Back to Orders
              </a>
              <?php if (in_array($specificOrder['order_status'], ['pending', 'processing'])): ?>
                <form method="POST" style="display: inline;">
                  <input type="hidden" name="order_id" value="<?php echo $specificOrder['id']; ?>">
                  <button type="submit" name="cancel_order" class="btn-order btn-cancel"
                    onclick="return confirm('Are you sure you want to cancel this order?')">
                    <i class="ri-close-line"></i> Cancel Order
                  </button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php elseif (empty($orders)): ?>
        <div class="empty-orders">
          <i class="ri-shopping-bag-line"></i>
          <h3>No orders yet</h3>
          <p>Start shopping to place your first order</p>
          <a href="index.php" class="btn-primary" style="margin-top: 20px;">
            <i class="ri-shopping-bag-line"></i>
            <span>Start Shopping</span>
          </a>
        </div>
      <?php else: ?>
        <!-- Order List -->
        <?php foreach ($orders as $order): ?>
          <div class="order-card">
            <div class="order-header">
              <div>
                <div class="order-number">Order #<?php echo htmlspecialchars($order['order_number']); ?></div>
                <div class="order-date"><?php echo date('M d, Y', strtotime($order['created_at'])); ?></div>
              </div>
              <div>
                <span class="order-status status-<?php echo $order['order_status']; ?>">
                  <?php echo ucfirst($order['order_status']); ?>
                </span>
              </div>
            </div>

            <div class="order-body">
              <div class="order-items">
                <?php
                $displayItems = array_slice($order['items'], 0, 3);
                foreach ($displayItems as $item): ?>
                  <div class="order-item">
                    <div class="item-info">
                      <div class="item-name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                      <div class="item-details">Qty: <?php echo $item['quantity']; ?></div>
                    </div>
                    <div class="item-price"><?php echo formatRupiah($item['subtotal']); ?></div>
                  </div>
                <?php endforeach; ?>

                <?php if (count($order['items']) > 3): ?>
                  <div class="order-item">
                    <div class="item-info">
                      <div class="item-name">+<?php echo count($order['items']) - 3; ?> more items</div>
                    </div>
                  </div>
                <?php endif; ?>
              </div>

              <div class="order-summary">
                <div class="summary-row total">
                  <span>Total:</span>
                  <span><?php echo formatRupiah($order['total_amount']); ?></span>
                </div>
              </div>

              <div class="order-actions">
                <a href="orders.php?order=<?php echo htmlspecialchars($order['order_number']); ?>" class="btn-order btn-view">
                  <i class="ri-eye-line"></i> View Details
                </a>
                <?php if (in_array($order['order_status'], ['pending', 'processing'])): ?>
                  <form method="POST" style="display: inline;">
                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                    <button type="submit" name="cancel_order" class="btn-order btn-cancel"
                      onclick="return confirm('Are you sure you want to cancel this order?')">
                      <i class="ri-close-line"></i> Cancel
                    </button>
                  </form>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
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
  </script>
</body>

</html>