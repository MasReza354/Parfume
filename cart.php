<?php
require_once 'config/database.php';

// Language setup
$lang = $_GET['lang'] ?? ($_SESSION['lang'] ?? 'id');
$_SESSION['lang'] = $lang;

// Check if user is logged in
if (!isLoggedIn()) {
  header('Location: auth/login.php?redirect=cart');
  exit;
}

// Handle AJAX request for cart count
if (isset($_GET['get_count'])) {
  $count = 0;
  if (isset($_SESSION['cart'])) {
    $count = array_sum($_SESSION['cart']);
  }
  header('Content-Type: application/json');
  echo json_encode(['count' => $count]);
  exit;
}

// Handle AJAX update quantity
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_quantity'])) {
  header('Content-Type: application/json');
  
  $productId = $_POST['product_id'] ?? '';
  $quantity = max(1, intval($_POST['quantity'] ?? 1));
  
  if (!empty($productId) && isset($_SESSION['cart'][$productId])) {
    $_SESSION['cart'][$productId] = $quantity;
    
    // Calculate new totals
    $totalAmount = 0;
    $additionalItems = [
      'bubble_wrap' => ['price' => 5000],
      'wooden_packing' => ['price' => 15000]
    ];
    
    foreach ($_SESSION['cart'] as $pid => $qty) {
      if ($pid === 'bubble_wrap' || $pid === 'wooden_packing') {
        $totalAmount += $additionalItems[$pid]['price'] * $qty;
      } else {
        $stmt = $conn->prepare("SELECT price FROM products WHERE id = ?");
        $stmt->bind_param("i", $pid);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
          $totalAmount += $row['price'] * $qty;
        }
      }
    }
    
    // Get item price
    $itemPrice = 0;
    if ($productId === 'bubble_wrap' || $productId === 'wooden_packing') {
      $itemPrice = $additionalItems[$productId]['price'];
    } else {
      $stmt = $conn->prepare("SELECT price FROM products WHERE id = ?");
      $stmt->bind_param("i", $productId);
      $stmt->execute();
      $res = $stmt->get_result();
      if ($row = $res->fetch_assoc()) {
        $itemPrice = $row['price'];
      }
    }
    
    echo json_encode([
      'success' => true,
      'itemSubtotal' => $itemPrice * $quantity,
      'totalAmount' => $totalAmount
    ]);
  } else {
    echo json_encode(['success' => false, 'message' => 'Item not found']);
  }
  exit;
}

// Handle add to cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
  $productId = $_POST['product_id'] ?? 0;
  $quantity = $_POST['quantity'] ?? 1;

  if ($productId > 0) {
    $product = $conn->query("SELECT * FROM products WHERE id = $productId AND status = 'active'")->fetch_assoc();
    if ($product) {
      if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
      }

      // Check if product already in cart
      if (isset($_SESSION['cart'][$productId])) {
        $_SESSION['cart'][$productId] += $quantity;
      } else {
        $_SESSION['cart'][$productId] = $quantity;
      }

      // Return JSON response for AJAX requests
      if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Product added to cart!']);
        exit;
      }
    }
  }
  header('Location: cart.php');
  exit;
}

// Get cart items from session or database
$cartItems = [];
$totalAmount = 0;

// Additional items (bubble wrap and wooden packing)
$additionalItems = [
  'bubble_wrap' => ['name' => 'Bubble Wrap Tambahan', 'price' => 5000, 'image' => 'images/bub-putih.jpg', 'type' => 'Packaging', 'scent' => 'Protection'],
  'wooden_packing' => ['name' => 'Packing Kayu', 'price' => 15000, 'image' => 'images/packing-kayu.jpg', 'type' => 'Packaging', 'scent' => 'Premium']
];

if (isset($_SESSION['cart'])) {
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
        // Check if stock is sufficient
        if ($product['stock'] >= $quantity) {
          $product['quantity'] = $quantity;
          $product['subtotal'] = $product['price'] * $quantity;
          $cartItems[] = $product;
          $totalAmount += $product['subtotal'];
        } else {
          // If stock is insufficient, adjust quantity to available stock
          if ($product['stock'] > 0) {
            $_SESSION['cart'][$productId] = $product['stock'];
            $product['quantity'] = $product['stock'];
            $product['subtotal'] = $product['price'] * $product['stock'];
            $cartItems[] = $product;
            $totalAmount += $product['subtotal'];
          } else {
            // Remove from cart if no stock
            unset($_SESSION['cart'][$productId]);
          }
        }
      } else {
        // Remove from cart if product is inactive or doesn't exist
        unset($_SESSION['cart'][$productId]);
      }
    }
  }
}

// Handle cart updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (isset($_POST['update_cart'])) {
    foreach ($_POST['quantities'] as $productId => $quantity) {
      $quantity = max(0, intval($quantity));
      if ($quantity > 0) {
        $_SESSION['cart'][$productId] = $quantity;
      } else {
        unset($_SESSION['cart'][$productId]);
      }
    }
    header('Location: cart.php');
    exit;
  }

  if (isset($_POST['remove_item'])) {
    $productId = $_POST['product_id'];
    unset($_SESSION['cart'][$productId]);
    header('Location: cart.php');
    exit;
  }

  if (isset($_POST['clear_cart'])) {
    unset($_SESSION['cart']);
    header('Location: cart.php');
    exit;
  }

  if (isset($_POST['add_bubble_wrap'])) {
    if (!isset($_SESSION['cart']['bubble_wrap'])) {
      $_SESSION['cart']['bubble_wrap'] = 1;
    } else {
      $_SESSION['cart']['bubble_wrap']++;
    }
    header('Location: cart.php');
    exit;
  }

  if (isset($_POST['add_wooden_packing'])) {
    if (!isset($_SESSION['cart']['wooden_packing'])) {
      $_SESSION['cart']['wooden_packing'] = 1;
    } else {
      $_SESSION['cart']['wooden_packing']++;
    }
    header('Location: cart.php');
    exit;
  }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Shopping Cart - Parfumé Lux</title>
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="perfume-cards.css">
  <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    /* Cart Page Specific Styles */
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

    .cart-section {
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
    }

    /* Additional Items Section */
    .additional-items {
      background: var(--white);
      border-radius: 20px;
      padding: 30px;
      margin-bottom: 30px;
      box-shadow: 0 10px 30px rgba(205, 127, 127, 0.1);
    }

    .additional-items h3 {
      display: flex;
      align-items: center;
      gap: 10px;
      font-size: 1.5rem;
      color: var(--text-dark);
      margin-bottom: 25px;
      font-family: var(--header-font);
    }

    .additional-items h3 i {
      color: var(--btn-color);
      font-size: 1.8rem;
    }

    .additional-item {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 20px;
      background: linear-gradient(135deg, #f5cdcd 0%, #fdecec 100%);
      border-radius: 15px;
      margin-bottom: 15px;
      transition: all 0.3s ease;
      gap: 20px;
      flex-wrap: wrap;
    }

    .additional-item:hover {
      transform: translateY(-3px);
      box-shadow: 0 8px 20px rgba(205, 127, 127, 0.2);
    }

    .additional-item-info {
      display: flex;
      align-items: center;
      gap: 15px;
      flex: 1;
      min-width: 250px;
    }

    .additional-item-info img {
      width: 70px;
      height: 70px;
      object-fit: cover;
      border-radius: 10px;
      box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    }

    .additional-item-details h4 {
      font-size: 1.1rem;
      color: var(--text-dark);
      margin-bottom: 5px;
      font-weight: 700;
    }

    .additional-item-details p {
      font-size: 0.85rem;
      color: var(--light-text);
    }

    .additional-item-price {
      font-size: 1.3rem;
      font-weight: 700;
      color: var(--hover-color);
      font-family: var(--header-font);
    }

    .btn-add-item {
      display: flex;
      align-items: center;
      gap: 6px;
      background: linear-gradient(135deg, var(--btn-color) 0%, var(--hover-color) 100%);
      color: var(--white);
      border: none;
      padding: 10px 20px;
      border-radius: 20px;
      font-weight: 600;
      font-size: 0.9rem;
      cursor: pointer;
      transition: all 0.3s ease;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .btn-add-item:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(205, 127, 127, 0.3);
    }

    /* Empty Cart */
    .empty-cart {
      text-align: center;
      padding: 80px 20px;
      background: var(--white);
      border-radius: 20px;
      box-shadow: 0 10px 30px rgba(205, 127, 127, 0.1);
    }

    .empty-cart i {
      font-size: 5rem;
      color: var(--btn-color);
      opacity: 0.3;
      margin-bottom: 20px;
    }

    .empty-cart h3 {
      font-size: 1.8rem;
      color: var(--text-dark);
      margin-bottom: 15px;
      font-weight: 700;
    }

    .empty-cart p {
      font-size: 1rem;
      color: var(--light-text);
      margin-bottom: 30px;
    }

    /* Cart Container */
    .cart-container {
      display: grid;
      grid-template-columns: 1fr 400px;
      gap: 30px;
      align-items: start;
    }

    .cart-items {
      display: flex;
      flex-direction: column;
      gap: 20px;
    }

    /* Cart Item */
    .cart-item {
      display: grid;
      grid-template-columns: 120px 1fr auto auto auto;
      gap: 20px;
      align-items: center;
      background: var(--white);
      padding: 20px;
      border-radius: 15px;
      box-shadow: 0 5px 20px rgba(205, 127, 127, 0.1);
      transition: all 0.3s ease;
    }

    .cart-item:hover {
      transform: translateY(-3px);
      box-shadow: 0 10px 30px rgba(205, 127, 127, 0.15);
    }

    .item-image {
      width: 120px;
      height: 120px;
      border-radius: 12px;
      overflow: hidden;
      background: linear-gradient(135deg, #f5cdcd 0%, #cc7f7f 100%);
    }

    .item-image img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      transition: transform 0.3s ease;
    }

    .cart-item:hover .item-image img {
      transform: scale(1.1);
    }

    .item-details {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }

    .item-name {
      font-size: 1.3rem;
      font-weight: 700;
      color: var(--text-dark);
      font-family: var(--header-font);
      margin: 0;
    }

    .item-type {
      font-size: 0.85rem;
      color: var(--light-text);
      font-weight: 500;
      margin: 0;
    }

    .item-price {
      font-size: 1.1rem;
      font-weight: 700;
      color: var(--hover-color);
      font-family: var(--header-font);
      margin: 0;
    }

    /* Quantity Controls */
    .item-quantity {
      display: flex;
      align-items: center;
    }

    .quantity-controls {
      display: flex;
      align-items: center;
      gap: 5px;
      background: linear-gradient(135deg, #f5cdcd 0%, #fdecec 100%);
      padding: 5px;
      border-radius: 25px;
    }

    .quantity-btn {
      width: 35px;
      height: 35px;
      border: none;
      background: var(--white);
      color: var(--text-dark);
      border-radius: 50%;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: all 0.3s ease;
      font-size: 1rem;
    }

    .quantity-btn:hover {
      background: var(--btn-color);
      color: var(--white);
      transform: scale(1.1);
    }

    .quantity-input {
      width: 50px;
      text-align: center;
      border: none;
      background: transparent;
      font-size: 1rem;
      font-weight: 700;
      color: var(--text-dark);
      outline: none;
    }

    .quantity-input::-webkit-inner-spin-button,
    .quantity-input::-webkit-outer-spin-button {
      -webkit-appearance: none;
      margin: 0;
    }

    /* Item Subtotal */
    .item-subtotal {
      font-size: 1.4rem;
      font-weight: 700;
      color: var(--hover-color);
      font-family: var(--header-font);
      min-width: 120px;
      text-align: right;
    }

    /* Item Actions */
    .item-actions {
      display: flex;
      align-items: center;
    }

    .btn-remove {
      width: 40px;
      height: 40px;
      border: none;
      background: #fee;
      color: #e74c3c;
      border-radius: 50%;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: all 0.3s ease;
      font-size: 1.2rem;
    }

    .btn-remove:hover {
      background: #e74c3c;
      color: var(--white);
      transform: scale(1.1);
    }

    /* Cart Summary */
    .cart-summary {
      position: sticky;
      top: 20px;
    }

    .summary-card {
      background: var(--white);
      padding: 30px;
      border-radius: 20px;
      box-shadow: 0 10px 30px rgba(205, 127, 127, 0.15);
    }

    .summary-card h3 {
      font-size: 1.5rem;
      font-weight: 700;
      color: var(--text-dark);
      margin-bottom: 25px;
      font-family: var(--header-font);
      text-transform: uppercase;
      letter-spacing: 1px;
    }

    .summary-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 15px 0;
      border-bottom: 1px solid #f0f0f0;
      font-size: 1rem;
      color: var(--text-dark);
    }

    .summary-row.total {
      border-bottom: none;
      border-top: 2px solid var(--btn-color);
      padding-top: 20px;
      margin-top: 10px;
      font-size: 1.3rem;
      font-weight: 700;
      color: var(--hover-color);
      font-family: var(--header-font);
    }

    .cart-actions {
      display: flex;
      flex-direction: column;
      gap: 12px;
      margin-top: 25px;
    }

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

    /* Responsive Design */
    @media (max-width: 1024px) {
      .cart-container {
        grid-template-columns: 1fr;
      }

      .cart-summary {
        position: static;
      }

      .cart-item {
        grid-template-columns: 100px 1fr;
        gap: 15px;
      }

      .item-image {
        width: 100px;
        height: 100px;
      }

      .item-quantity,
      .item-subtotal,
      .item-actions {
        grid-column: 1 / -1;
        justify-self: start;
      }

      .item-quantity {
        width: 100%;
      }

      .quantity-controls {
        width: 100%;
        justify-content: space-between;
        padding: 8px 15px;
      }

      .item-subtotal {
        text-align: left;
        font-size: 1.5rem;
      }
    }

    @media (max-width: 768px) {
      .section-title {
        font-size: 2rem;
      }

      .section-subtitle {
        font-size: 0.95rem;
      }

      .additional-item {
        flex-direction: column;
        align-items: stretch;
        text-align: center;
      }

      .additional-item-info {
        flex-direction: column;
        text-align: center;
        min-width: auto;
      }

      .btn-add-item {
        width: 100%;
        justify-content: center;
      }

      .cart-item {
        padding: 15px;
      }

      .item-name {
        font-size: 1.1rem;
      }

      .summary-card {
        padding: 20px;
      }
    }

    @media (max-width: 480px) {
      .cart-section {
        padding: 20px 0 60px;
      }

      .section-header {
        padding: 30px 15px;
      }

      .section-title {
        font-size: 1.6rem;
      }

      .additional-items {
        padding: 20px;
      }

      .additional-items h3 {
        font-size: 1.2rem;
      }

      .item-image {
        width: 80px;
        height: 80px;
      }

      .empty-cart {
        padding: 60px 15px;
      }

      .empty-cart i {
        font-size: 3.5rem;
      }
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
    }

    .notification.success {
      background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
    }

    .notification.error {
      background: linear-gradient(135deg, #c0392b 0%, #e74c3c 100%);
    }

    .notification.warning {
      background: linear-gradient(135deg, #f39c12 0%, #f1c40f 100%);
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
  </style>
</head>

<body class="<?php echo isLoggedIn() ? 'user-logged-in' : 'user-logged-out'; ?>">
  <div style="padding: 1rem 2rem;">
    <a href="index.php" class="back-home"><i class="ri-arrow-left-line"></i> Kembali ke Beranda</a>
  </div>

  <!-- Cart Section -->
  <section class="cart-section">
    <div class="container">
      <div class="section-header">
        <h1 class="section-title" data-translate="cart_title">Shopping Cart</h1>
        <p class="section-subtitle" data-translate="cart_subtitle">Review your selected items before checkout</p>
      </div>

      <!-- Additional Items -->
      <div class="additional-items">
        <h3><i class="ri-gift-line"></i> Additional Packaging Options</h3>

        <div class="additional-item">
          <div class="additional-item-info">
            <img src="images/bub-putih.jpg" alt="Bubble Wrap">
            <div class="additional-item-details">
              <h4>Bubble Wrap Tambahan</h4>
              <p>Extra protection for fragile items</p>
            </div>
          </div>
          <div class="additional-item-price">
            <?php echo formatRupiah(5000); ?>
          </div>
          <form method="POST" style="margin: 0;">
            <button type="submit" name="add_bubble_wrap" class="btn-add-item">
              <i class="ri-add-line"></i> Add
            </button>
          </form>
        </div>

        <div class="additional-item">
          <div class="additional-item-info">
            <img src="images/packing-kayu.jpg" alt="Wooden Packing">
            <div class="additional-item-details">
              <h4>Packing Kayu</h4>
              <p>Premium wooden box for maximum safety</p>
            </div>
          </div>
          <div class="additional-item-price">
            <?php echo formatRupiah(15000); ?>
          </div>
          <form method="POST" style="margin: 0;">
            <button type="submit" name="add_wooden_packing" class="btn-add-item">
              <i class="ri-add-line"></i> Add
            </button>
          </form>
        </div>
      </div>

      <?php if (empty($cartItems)): ?>
        <div class="empty-cart">
          <i class="ri-shopping-cart-line"></i>
          <h3 data-translate="cart_empty">Your cart is empty</h3>
          <p data-translate="cart_empty_msg">Start shopping to add items to your cart</p>
          <a href="index.php" class="btn-primary">
            <i class="ri-shopping-bag-line"></i>
            <span data-translate="continue_shopping">Continue Shopping</span>
          </a>
        </div>
      <?php else: ?>
        <form method="POST" class="cart-form" id="cartForm">
          <div class="cart-container">
            <div class="cart-items">
              <?php foreach ($cartItems as $item): ?>
                <div class="cart-item" data-product-id="<?php echo $item['id']; ?>">
                  <div class="item-image">
                    <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                  </div>

                  <div class="item-details">
                    <h3 class="item-name"><?php echo htmlspecialchars($item['name']); ?></h3>
                    <p class="item-type"><?php echo htmlspecialchars($item['type']); ?> • <?php echo htmlspecialchars($item['scent']); ?></p>
                    <p class="item-price"><?php echo formatRupiah($item['price']); ?></p>
                  </div>

                  <div class="item-quantity">
                    <div class="quantity-controls">
                      <button type="button" class="quantity-btn decrease-qty" data-id="<?php echo $item['id']; ?>">
                        <i class="ri-subtract-line"></i>
                      </button>
                      <input type="text" inputmode="numeric"
                        name="quantities[<?php echo $item['id']; ?>]"
                        value="<?php echo $item['quantity']; ?>"
                        min="1"
                        max="<?php echo isset($item['stock']) ? $item['stock'] : 99; ?>"
                        class="quantity-input"
                        data-price="<?php echo $item['price']; ?>"
                        data-id="<?php echo $item['id']; ?>">
                      <button type="button" class="quantity-btn increase-qty" data-id="<?php echo $item['id']; ?>">
                        <i class="ri-add-line"></i>
                      </button>
                    </div>
                  </div>

                  <div class="item-subtotal" id="subtotal-<?php echo $item['id']; ?>">
                    <?php echo formatRupiah($item['subtotal']); ?>
                  </div>

                  <div class="item-actions">
                    <button type="button" class="btn-remove remove-item" data-id="<?php echo $item['id']; ?>">
                      <i class="ri-delete-bin-line"></i>
                    </button>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>

            <div class="cart-summary">
              <div class="summary-card">
                <h3 data-translate="order_summary">Order Summary</h3>

                <div class="summary-row">
                  <span data-translate="subtotal">Subtotal</span>
                  <span id="cart-total"><?php echo formatRupiah($totalAmount); ?></span>
                </div>

                <div class="summary-row">
                  <span data-translate="shipping">Shipping</span>
                  <span data-translate="calculated_at_checkout">Calculated at checkout</span>
                </div>

                <div class="summary-row total">
                  <span data-translate="total">Total</span>
                  <span id="final-total"><?php echo formatRupiah($totalAmount); ?></span>
                </div>

                <div class="cart-actions">
                  <a href="orders.php" class="btn-secondary">Lihat Pesanan Saya</a>
                  <button type="submit" name="update_cart" class="btn-secondary" data-translate="update_cart">Update Cart</button>
                  <a href="checkout.php" class="btn-primary" data-translate="proceed_to_checkout">Proceed to Checkout</a>
                </div>
              </div>
            </div>
          </div>
        </form>
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

    // Quantity control functions
    function updateQuantity(itemId, change) {
      const input = document.querySelector(`input[data-id="${itemId}"]`);
      const currentValue = parseInt(input.value);
      const newValue = Math.max(1, currentValue + change);
      const maxValue = parseInt(input.max);

      if (newValue <= maxValue) {
        // Update UI optimistically
        input.value = newValue;
        
        // Send AJAX request to update session
        const formData = new FormData();
        formData.append('update_quantity', '1');
        formData.append('product_id', itemId);
        formData.append('quantity', newValue);
        
        fetch('cart.php', {
          method: 'POST',
          body: formData
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            // Update subtotal
            const subtotalElement = document.getElementById(`subtotal-${itemId}`);
            if (subtotalElement) {
              subtotalElement.textContent = formatRupiah(data.itemSubtotal);
            }
            
            // Update totals
            document.getElementById('cart-total').textContent = formatRupiah(data.totalAmount);
            document.getElementById('final-total').textContent = formatRupiah(data.totalAmount);
          } else {
            showNotification(data.message || 'Failed to update cart', 'error');
            // Revert value
            input.value = currentValue;
          }
        })
        .catch(error => {
          console.error('Error:', error);
          showNotification('Error updating cart', 'error');
          // Revert value
          input.value = currentValue;
        });
      } else {
        showNotification('Maximum quantity reached', 'warning');
      }
    }

    function updateSubtotal(itemId) {
      const input = document.querySelector(`input[data-id="${itemId}"]`);
      const price = parseFloat(input.dataset.price);
      const quantity = parseInt(input.value);
      const subtotal = price * quantity;

      const subtotalElement = document.getElementById(`subtotal-${itemId}`);
      if (subtotalElement) {
        subtotalElement.textContent = formatRupiah(subtotal);
      }
    }

    function updateCartTotal() {
      let total = 0;
      document.querySelectorAll('.cart-item').forEach(item => {
        const itemId = item.dataset.productId;
        const input = document.querySelector(`input[data-id="${itemId}"]`);
        if (input) {
          const price = parseFloat(input.dataset.price);
          const quantity = parseInt(input.value);
          total += price * quantity;
        }
      });

      document.getElementById('cart-total').textContent = formatRupiah(total);
      document.getElementById('final-total').textContent = formatRupiah(total);
    }

    // Format Rupiah function
    function formatRupiah(amount) {
      return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0
      }).format(amount);
    }

    // Attach event listeners
    document.querySelectorAll('.decrease-qty').forEach(button => {
      button.addEventListener('click', function() {
        const itemId = this.dataset.id;
        updateQuantity(itemId, -1);
      });
    });

    document.querySelectorAll('.increase-qty').forEach(button => {
      button.addEventListener('click', function() {
        const itemId = this.dataset.id;
        updateQuantity(itemId, 1);
      });
    });

    document.querySelectorAll('.quantity-input').forEach(input => {
      input.addEventListener('change', function() {
        const itemId = this.dataset.id;
        const max = parseInt(this.max);
        const value = parseInt(this.value);

        if (value > max) {
          this.value = max;
          showNotification('Maximum quantity reached', 'warning');
        }

        updateSubtotal(itemId);
        updateCartTotal();
      });
    });

    document.querySelectorAll('.remove-item').forEach(button => {
      button.addEventListener('click', function() {
        const itemId = this.dataset.id;

        if (confirm('Remove this item?')) {
          // Create form to submit removal
          const form = document.createElement('form');
          form.method = 'POST';
          form.innerHTML = `
            <input type="hidden" name="remove_item" value="1">
            <input type="hidden" name="product_id" value="${itemId}">
          `;
          document.body.appendChild(form);
          form.submit();
        }
      });
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
        background: ${type === 'success' ? '#4CAF50' : type === 'warning' ? '#ff9800' : '#f44336'};
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
