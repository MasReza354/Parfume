<?php
require_once 'config/database.php';

// Language setup
$lang = $_GET['lang'] ?? ($_SESSION['lang'] ?? 'id');
$_SESSION['lang'] = $lang;

// Check if user is logged in
if (!isLoggedIn()) {
  header('Location: auth/login.php?redirect=orders');
  exit;
}

// Lightweight status endpoint for dynamic updates
if (isset($_GET['action']) && $_GET['action'] === 'status') {
  header('Content-Type: application/json');
  $stmt = $conn->prepare("SELECT id, order_number, order_status, payment_status, updated_at FROM orders WHERE user_id = ? ORDER BY created_at DESC");
  $stmt->bind_param("i", $_SESSION['user_id']);
  $stmt->execute();
  $result = $stmt->get_result();
  $data = [];
  while ($row = $result->fetch_assoc()) {
    $data[] = $row;
  }
  echo json_encode(['orders' => $data]);
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

  // Check if 'price' column exists and drop it (legacy column)
  $checkPrice = $conn->query("SHOW COLUMNS FROM order_items LIKE 'price'");
  if ($checkPrice && $checkPrice->num_rows > 0) {
    $conn->query("ALTER TABLE order_items DROP COLUMN price");
  }

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
        $productStmt = $conn->prepare("SELECT * FROM products WHERE id = ? AND status = 'active'");
        $productStmt->bind_param("i", $productId);
        $productStmt->execute();
        $product = $productStmt->get_result()->fetch_assoc();

        if (!$product) {
          $_SESSION['error_message'] = 'Produk tidak tersedia atau nonaktif. Silakan perbarui keranjang Anda.';
          unset($_SESSION['cart'][$productId]);
          header('Location: cart.php');
          exit;
        }

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
        $update = $conn->prepare("UPDATE orders SET order_status = 'cancelled', status = 'cancelled', updated_at = NOW() WHERE id = ?");
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
  <title>My Orders - Parfum Lux</title>
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="auth.css">
  <link rel="stylesheet" href="perfume-cards.css">
  <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    /* Orders Page Specific Styles */
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

    .orders-section {
      padding: 40px 0 80px;
      min-height: 60vh;
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
    }

    .orders-wrapper {
      background: var(--white);
      border-radius: 20px;
      box-shadow: 0 10px 30px rgba(205, 127, 127, 0.1);
      padding: 30px;
    }

    .order-card {
      background: var(--white);
      border-radius: 20px;
      box-shadow: 0 10px 30px rgba(205, 127, 127, 0.1);
      margin-bottom: 25px;
      overflow: hidden;
      transition: all 0.3s ease;
      border: 2px solid transparent;
    }

    .order-card:hover {
      box-shadow: 0 15px 40px rgba(205, 127, 127, 0.15);
      transform: translateY(-5px);
      border-color: var(--btn-color);
    }

    .order-header {
      background: linear-gradient(135deg, #f5cdcd 0%, #cc7f7f 100%);
      color: var(--white);
      padding: 25px 30px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 15px;
    }

    .order-number {
      font-size: 1.3rem;
      font-weight: 700;
      text-align: left;
      display: block;
      font-family: var(--header-font);
      letter-spacing: 1px;
    }

    .order-date {
      font-size: 0.95rem;
      opacity: 0.95;
      text-align: left;
      display: block;
      margin-top: 5px;
    }

    .order-status {
      padding: 8px 16px;
      border-radius: 20px;
      font-size: 0.85rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      background: var(--white);
      color: var(--text-dark);
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
      display: inline-block;
    }

    .status-pending { 
      background: linear-gradient(135deg, #fff3cd 0%, #ffe9a0 100%); 
      color: #856404; 
    }
    .status-processing { 
      background: linear-gradient(135deg, #cfe2ff 0%, #9ec5fe 100%); 
      color: #084298; 
    }
    .status-shipped { 
      background: linear-gradient(135deg, #d1e7dd 0%, #a3cfbb 100%); 
      color: #0f5132; 
    }
    .status-delivered { 
      background: linear-gradient(135deg, #d1f2eb 0%, #a7e9d7 100%); 
      color: #0a3622; 
    }
    .status-cancelled { 
      background: linear-gradient(135deg, #f8d7da 0%, #f1aeb5 100%); 
      color: #842029; 
    }
    .status-payment-pending { 
      background: linear-gradient(135deg, #e2e3e5 0%, #d3d4d5 100%); 
      color: #41464b; 
    }
    .status-payment-paid { 
      background: linear-gradient(135deg, #d1f2eb 0%, #a7e9d7 100%); 
      color: #0a3622; 
    }

    .order-body {
      padding: 30px;
    }

    .order-body h4,
    .order-body h5 {
      color: var(--text-dark);
      font-weight: 700;
      margin-bottom: 15px;
      font-family: var(--header-font);
      text-transform: uppercase;
      letter-spacing: 1px;
    }

    .order-items {
      margin-bottom: 20px;
    }

    .order-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 15px 0;
      border-bottom: 1px solid #f0f0f0;
      gap: 15px;
    }

    .order-item:last-child {
      border-bottom: none;
    }

    .item-info {
      flex: 1;
      text-align: left;
    }

    .item-name {
      font-weight: 700;
      margin-bottom: 8px;
      color: var(--text-dark);
      font-size: 1rem;
    }

    .item-details {
      color: var(--light-text);
      font-size: 0.9rem;
    }

    .item-quantity {
      color: var(--light-text);
      margin: 0 15px;
      text-align: center;
      font-weight: 600;
      background: linear-gradient(135deg, #f5cdcd 0%, #fdecec 100%);
      padding: 5px 15px;
      border-radius: 15px;
    }

    .item-price {
      font-weight: 700;
      color: var(--hover-color);
      font-family: var(--header-font);
      font-size: 1.1rem;
      white-space: nowrap;
    }

    .order-summary {
      background: linear-gradient(135deg, #f5cdcd 0%, #fdecec 100%);
      padding: 20px;
      border-radius: 15px;
      margin-top: 20px;
      border: 2px solid var(--btn-color);
    }

    .summary-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 12px;
      color: var(--text-dark);
      font-size: 1rem;
    }

    .summary-row span:first-child {
      font-weight: 500;
    }

    .summary-row span:last-child {
      font-weight: 700;
      color: var(--hover-color);
    }

    .summary-row.total {
      font-weight: 700;
      font-size: 1.3rem;
      color: var(--text-dark);
      border-top: 2px solid var(--btn-color);
      padding-top: 12px;
      margin-top: 12px;
      margin-bottom: 0;
    }

    .summary-row.total span {
      font-family: var(--header-font);
    }

    .order-actions {
      display: flex;
      gap: 12px;
      margin-top: 25px;
      flex-wrap: wrap;
    }

    .btn-order {
      padding: 12px 20px;
      border: none;
      border-radius: 25px;
      cursor: pointer;
      font-weight: 700;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      transition: all 0.3s ease;
      font-size: 0.95rem;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      font-family: 'Montserrat', sans-serif;
    }

    .btn-view {
      background: linear-gradient(135deg, var(--btn-color) 0%, var(--hover-color) 100%);
      color: var(--white);
      box-shadow: 0 5px 15px rgba(205, 127, 127, 0.2);
    }

    .btn-view:hover {
      transform: translateY(-3px);
      box-shadow: 0 8px 20px rgba(205, 127, 127, 0.3);
    }

    .btn-cancel {
      background: var(--white);
      color: #e74c3c;
      border: 2px solid #e74c3c;
    }

    .btn-cancel:hover {
      background: #e74c3c;
      color: var(--white);
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(231, 76, 60, 0.3);
    }

    .btn-cancel:disabled {
      background: #e0e0e0;
      color: #999;
      border-color: #e0e0e0;
      cursor: not-allowed;
      opacity: 0.6;
      transform: none;
    }

    .btn-primary {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      background: linear-gradient(135deg, var(--btn-color) 0%, var(--hover-color) 100%);
      color: var(--white);
      padding: 15px 30px;
      border-radius: 30px;
      font-weight: 600;
      font-size: 1rem;
      text-decoration: none;
      transition: all 0.3s ease;
      text-transform: uppercase;
      letter-spacing: 1px;
    }

    .btn-primary:hover {
      transform: translateY(-3px);
      box-shadow: 0 10px 25px rgba(205, 127, 127, 0.3);
    }

    .empty-orders {
      text-align: center;
      padding: 80px 20px;
      background: var(--white);
      border-radius: 20px;
      box-shadow: 0 10px 30px rgba(205, 127, 127, 0.1);
    }

    .empty-orders i {
      font-size: 5rem;
      color: var(--btn-color);
      opacity: 0.3;
      margin-bottom: 20px;
    }

    .empty-orders h3 {
      font-size: 1.8rem;
      color: var(--text-dark);
      margin-bottom: 15px;
      font-weight: 700;
    }

    .empty-orders p {
      font-size: 1rem;
      color: var(--light-text);
      margin-bottom: 30px;
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
    @media (max-width: 768px) {
      .section-title {
        font-size: 2rem;
      }

      .section-subtitle {
        font-size: 0.95rem;
      }

      .order-header {
        flex-direction: column;
        align-items: flex-start;
        padding: 20px;
      }

      .order-header > div:last-child {
        width: 100%;
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
      }

      .order-body {
        padding: 20px;
      }

      .order-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
      }

      .item-quantity {
        margin: 0;
      }

      .item-price {
        font-size: 1.2rem;
      }

      .order-actions {
        flex-direction: column;
        width: 100%;
      }

      .btn-order {
        width: 100%;
        justify-content: center;
      }
    }

    @media (max-width: 480px) {
      .orders-section {
        padding: 20px 0 60px;
      }

      .section-header {
        padding: 30px 15px;
      }

      .section-title {
        font-size: 1.6rem;
      }

      .order-card {
        margin-bottom: 20px;
      }

      .order-number {
        font-size: 1.1rem;
      }

      .order-date {
        font-size: 0.85rem;
      }

      .order-status {
        padding: 6px 12px;
        font-size: 0.75rem;
      }

      .empty-orders {
        padding: 60px 15px;
      }

      .empty-orders i {
        font-size: 3.5rem;
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

    .order-card {
      animation: fadeInUp 0.6s ease forwards;
    }

    .order-card:nth-child(1) {
      animation-delay: 0.1s;
    }

    .order-card:nth-child(2) {
      animation-delay: 0.2s;
    }

    .order-card:nth-child(3) {
      animation-delay: 0.3s;
    }

    .order-card:nth-child(4) {
      animation-delay: 0.4s;
    }
  </style>
</head>

<body class="<?php echo isLoggedIn() ? 'user-logged-in' : 'user-logged-out'; ?>">
  <div style="padding: 1rem 2rem;">
    <a href="index.php" class="back-home"><i class="ri-arrow-left-line"></i> Kembali ke Beranda</a>
  </div>

  <!-- Orders Section -->
  <section class="orders-section cart-section" style="text-align: center; display: flex; justify-content: center;">
    <div class="container" style="max-width: 1400px; padding: 0 48px; width: 100%;">
      <div class="favorites-panel" style="padding: 24px; margin: 0 auto;">
        <div class="section-header" style="text-align: center;">
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
      </div>

      <?php if ($specificOrder): ?>
        <!-- Specific Order Detail -->
        <div class="order-card" data-order-id="<?php echo $specificOrder['id']; ?>">
          <div class="order-header">
            <div>
              <div class="order-number">Order #<?php echo htmlspecialchars($specificOrder['order_number']); ?></div>
              <div class="order-date"><?php echo date('F d, Y H:i', strtotime($specificOrder['created_at'])); ?></div>
            </div>
            <div>
              <span class="order-status status-<?php echo $specificOrder['order_status']; ?>" data-order-id="<?php echo $specificOrder['id']; ?>" data-type="order">
                <?php echo ucfirst($specificOrder['order_status']); ?>
              </span>
              <span class="order-status status-payment-<?php echo $specificOrder['payment_status']; ?>" style="margin-left: 10px;" data-order-id="<?php echo $specificOrder['id']; ?>" data-type="payment">
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
          <div class="order-card" data-order-id="<?php echo $order['id']; ?>">
            <div class="order-header">
              <div>
                <div class="order-number">Order #<?php echo htmlspecialchars($order['order_number']); ?></div>
                <div class="order-date"><?php echo date('M d, Y', strtotime($order['created_at'])); ?></div>
              </div>
              <div>
                <span class="order-status status-<?php echo $order['order_status']; ?>" data-order-id="<?php echo $order['id']; ?>" data-type="order">
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

    // Periodic status refresh to keep order status in sync with admin updates
    function refreshOrderStatuses() {
      fetch('orders.php?action=status')
        .then(resp => resp.json())
        .then(data => {
          if (!data.orders) return;
          data.orders.forEach(order => {
            const statusBadge = document.querySelector(`.order-status[data-order-id="${order.id}"][data-type="order"]`);
            const payBadge = document.querySelector(`.order-status[data-order-id="${order.id}"][data-type="payment"]`);

            if (statusBadge) {
              statusBadge.textContent = order.order_status.charAt(0).toUpperCase() + order.order_status.slice(1);
              statusBadge.className = `order-status status-${order.order_status}`;
            }
            if (payBadge) {
              payBadge.textContent = order.payment_status.charAt(0).toUpperCase() + order.payment_status.slice(1);
              payBadge.className = `order-status status-payment-${order.payment_status}`;
            }
          });
        })
        .catch(() => {});
    }

    setInterval(refreshOrderStatuses, 10000);
  </script>
</body>

</html>
