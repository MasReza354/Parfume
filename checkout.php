<?php
require_once 'config/database.php';

// Language setup
$lang = $_GET['lang'] ?? ($_SESSION['lang'] ?? 'id');
$_SESSION['lang'] = $lang;

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

// Handle AJAX cart update
if (isset($_POST['action']) && $_POST['action'] === 'update_cart_item') {
  header('Content-Type: application/json');
  
  $productId = $_POST['product_id'] ?? 0;
  $quantity = max(1, intval($_POST['quantity'] ?? 1));

  if (isset($_SESSION['cart'][$productId])) {
    $_SESSION['cart'][$productId] = $quantity;
    
    // Recalculate totals
    $totalAmount = 0;
    $itemSubtotal = 0;
    
    // Additional items definition for calculation
    $additionalItems = [
      'bubble_wrap' => ['price' => 5000],
      'wooden_packing' => ['price' => 15000]
    ];

    foreach ($_SESSION['cart'] as $pid => $qty) {
      $price = 0;
      
      if ($pid === 'bubble_wrap' || $pid === 'wooden_packing') {
        $price = $additionalItems[$pid]['price'];
      } else {
        $stmt = $conn->prepare("SELECT price FROM products WHERE id = ?");
        $stmt->bind_param("i", $pid);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
          $price = $row['price'];
        }
      }
      
      $subtotal = $price * $qty;
      $totalAmount += $subtotal;
      
      if ($pid == $productId) {
        $itemSubtotal = $subtotal;
      }
    }
    
    echo json_encode([
      'success' => true, 
      'itemSubtotal' => $itemSubtotal, 
      'totalAmount' => $totalAmount
    ]);
  } else {
    echo json_encode(['success' => false, 'message' => 'Item not found']);
  }
  exit;
}

// Handle order creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
  // Reset old errors/data
  unset($_SESSION['checkout_errors'], $_SESSION['checkout_form_data']);
  
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
  $shippingFee = 15000; // Default shipping fee

  // Detect location and set shipping fee
  foreach ($javaLocations as $location) {
    if (strpos($addressLower, $location) !== false) {
      $detectedLocation = 'Java';
      $shippingFee = 15000;
      break;
    }
  }

  if (!$detectedLocation) {
    foreach ($sumatraLocations as $location) {
      if (strpos($addressLower, $location) !== false) {
        $detectedLocation = 'Sumatra';
        $shippingFee = 20000;
        break;
      }
    }
  }

  if (!$detectedLocation) {
    foreach ($baliLocations as $location) {
      if (strpos($addressLower, $location) !== false) {
        $detectedLocation = 'Bali';
        $shippingFee = 25000;
        break;
      }
    }
  }

  if (!$detectedLocation) {
    foreach ($kalimantanLocations as $location) {
      if (strpos($addressLower, $location) !== false) {
        $detectedLocation = 'Kalimantan';
        $shippingFee = 30000;
        break;
      }
    }
  }

  if (!$detectedLocation) {
    foreach ($sulawesiLocations as $location) {
      if (strpos($addressLower, $location) !== false) {
        $detectedLocation = 'Sulawesi';
        $shippingFee = 35000;
        break;
      }
    }
  }

  if (!$detectedLocation) {
    foreach ($papuaLocations as $location) {
      if (strpos($addressLower, $location) !== false) {
        $detectedLocation = 'Papua';
        $shippingFee = 40000;
        break;
      }
    }
  }

  if (!$detectedLocation) {
    foreach ($nttLocations as $location) {
      if (strpos($addressLower, $location) !== false) {
        $detectedLocation = 'NTT';
        $shippingFee = 35000;
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
        $productStmt = $conn->prepare("SELECT * FROM products WHERE id = ? AND status = 'active'");
        $productStmt->bind_param("i", $productId);
        $productStmt->execute();
        $product = $productStmt->get_result()->fetch_assoc();

        if (!$product) {
          $errors[] = 'Produk tidak tersedia atau nonaktif. Silakan perbarui keranjang Anda.';
          unset($_SESSION['cart'][$productId]);
          continue;
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

    // Jika ada produk nonaktif atau error lain, hentikan proses dan kembali ke keranjang
  if (!empty($errors)) {
    $_SESSION['checkout_errors'] = $errors;
    $_SESSION['checkout_form_data'] = [
      'shipping_address' => $shippingAddress,
      'payment_method' => $paymentMethod,
      'notes' => $notes
    ];
    header('Location: checkout.php');
    exit;
  }

    $grandTotal = $totalAmount + $shippingFee;

    // Generate order number with format RDL-HHBBTTXXX
    $today = date('dmY'); // Get today's date in DMYY format for counting
    $datePrefix = date('dmy'); // Format: DDMMYY (we'll use DMYY for prefix)
    
    // Count orders created today to get sequence number
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
      $insertItem = $conn->prepare("INSERT INTO order_items (order_id, product_id, product_name, product_price, quantity, subtotal) VALUES (?, ?, ?, ?, ?, ?)");

      if (!$insertItem) {
        $orderItemsSuccess = false;
        $errors[] = 'Gagal menyiapkan penyimpanan item pesanan.';
        error_log('Prepare order_items failed: ' . $conn->error);
      } else {
        foreach ($orderItems as $item) {
          $itemProductId = $item['product_id'];
          $itemName = $item['product_name'];
          $itemProductPrice = $item['product_price'];
          $itemQuantity = $item['quantity'];
          $itemSubtotal = $item['subtotal'];

          $insertItem->bind_param(
            "iisdid",
            $orderId,
            $itemProductId,
            $itemName,
            $itemProductPrice,
            $itemQuantity,
            $itemSubtotal
          );

          if (!$insertItem->execute()) {
            $orderItemsSuccess = false;
            $errors[] = 'Gagal menambahkan item pesanan. Silakan coba lagi.';
            error_log('Order item insert failed: ' . $insertItem->error);
            break;
          }
        }
      }

      if ($orderItemsSuccess) {
        // Reduce product stock
        $updateStock = $conn->prepare("UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?");
        foreach ($orderItems as $item) {
          $itemProductId = $item['product_id'];
          $itemQuantity = $item['quantity'];
          
          $updateStock->bind_param("iii", $itemQuantity, $itemProductId, $itemQuantity);
          $updateStock->execute();
          
          // Log if stock update failed (stock insufficient)
          if ($updateStock->affected_rows === 0) {
            error_log("Warning: Stock not reduced for product ID $itemProductId. Possible insufficient stock.");
          }
        }
        
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

  // If there are errors, persist and redirect back to checkout so form resets state
  if (!empty($errors)) {
    $_SESSION['checkout_errors'] = $errors;
    $_SESSION['checkout_form_data'] = [
      'shipping_address' => $shippingAddress,
      'payment_method' => $paymentMethod,
      'notes' => $notes
    ];
    error_log("Checkout errors: " . implode(', ', $errors));
    header('Location: checkout.php');
    exit;
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
    $productStmt = $conn->prepare("SELECT * FROM products WHERE id = ? AND status = 'active'");
    $productStmt->bind_param("i", $productId);
    $productStmt->execute();
    $product = $productStmt->get_result()->fetch_assoc();

    if (!$product) {
      // Drop invalid item and notify user on next load
      if (!isset($_SESSION['checkout_errors'])) {
        $_SESSION['checkout_errors'] = [];
      }
      $_SESSION['checkout_errors'][] = 'Produk dengan ID ' . htmlspecialchars($productId) . ' tidak tersedia. Silakan perbarui keranjang.';
      unset($_SESSION['cart'][$productId]);
      continue;
    }

    $product['quantity'] = $quantity;
    $product['subtotal'] = $product['price'] * $quantity;
    $cartItems[] = $product;
    $totalAmount += $product['subtotal'];
  }
}

// Get user details for pre-filling
$userDetails = $conn->query("SELECT * FROM users WHERE id = " . $_SESSION['user_id'])->fetch_assoc();

// Default shipping fee (will be calculated based on address)
$shippingFee = 15000;

// Pre-fill from previous attempt if exists
$savedForm = $_SESSION['checkout_form_data'] ?? [];
$prefillAddress = $savedForm['shipping_address'] ?? ($userDetails['address'] ?? '');
$prefillNotes = $savedForm['notes'] ?? '';
$prefillPayment = $savedForm['payment_method'] ?? null;

// Simple pre-calc shipping fee based on saved address
if (!empty($prefillAddress)) {
  $addrLower = strtolower($prefillAddress);
  $zones = [
    'java' => ['jakarta', 'bogor', 'depok', 'tangerang', 'bekasi'],
    'sumatra' => ['medan', 'palembang', 'bandar lampung', 'batam'],
    'bali' => ['denpasar', 'kuta', 'seminyak', 'ubud', 'canggu'],
    'kalimantan' => ['balikpapan', 'samarinda', 'banjarmasin', 'pontianak'],
    'sulawesi' => ['makassar', 'manado', 'palu', 'kendari'],
    'papua' => ['jayapura', 'sorong', 'manokwari'],
    'ntt' => ['kupang', 'ende', 'maumere']
  ];

  foreach ($zones as $zone => $cities) {
    foreach ($cities as $city) {
      if (strpos($addrLower, $city) !== false) {
        if ($zone === 'sumatra') $shippingFee = 20000;
        if ($zone === 'bali') $shippingFee = 25000;
        if ($zone === 'kalimantan') $shippingFee = 30000;
        if ($zone === 'sulawesi' || $zone === 'ntt') $shippingFee = 35000;
        if ($zone === 'papua') $shippingFee = 40000;
        break 2;
      }
    }
  }
}

// If no valid items remain, redirect to cart
if (empty($cartItems)) {
  $_SESSION['error_message'] = 'Tidak ada item valid di keranjang. Silakan pilih produk yang aktif.';
  header('Location: cart.php');
  exit;
}

$grandTotal = $totalAmount + $shippingFee;
?>

<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pembayaran - Parfumé Lux</title>
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="perfume-cards.css">
  <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    /* Checkout Page Specific Styles */
    body {
      background: linear-gradient(135deg, #fdecec 0%, #f5cdcd 50%, #fdecec 100%);
      min-height: 100vh;
      overflow-x: hidden;
      overflow-y: auto;
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
    
    .checkout-section {
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

    .checkout-container {
      max-width: 1200px;
      margin: 0 auto;
      display: grid;
      grid-template-columns: 1.2fr 1fr;
      gap: 30px;
      align-items: start;
    }

    .checkout-form {
      background: var(--white);
      border-radius: 20px;
      box-shadow: 0 10px 30px rgba(205, 127, 127, 0.1);
      overflow: hidden;
      transition: all 0.3s ease;
    }

    .checkout-form:hover {
      box-shadow: 0 15px 40px rgba(205, 127, 127, 0.15);
    }

    .form-header {
      background: linear-gradient(135deg, #f5cdcd 0%, #cc7f7f 100%);
      color: var(--white);
      padding: 25px 30px;
      text-align: center;
    }

    .form-header h3 {
      margin: 0;
      font-size: 1.3rem;
      font-weight: 700;
      font-family: var(--header-font);
      text-transform: uppercase;
      letter-spacing: 1px;
    }

    .form-body {
      padding: 30px;
    }

    .form-group {
      margin-bottom: 25px;
    }

    .form-group label {
      display: block;
      margin-bottom: 10px;
      font-weight: 600;
      color: var(--text-dark);
      font-size: 0.95rem;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .form-group input,
    .form-group select,
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
    .form-group select:focus,
    .form-group textarea:focus {
      outline: none;
      border-color: var(--btn-color);
      box-shadow: 0 0 0 3px rgba(205, 127, 127, 0.1);
    }

    .form-group input:read-only,
    .form-group input[readonly] {
      background: #f5f5f5;
      cursor: not-allowed;
      color: #999;
    }

    .form-group textarea {
      resize: vertical;
      min-height: 100px;
    }

    .form-group small {
      display: block;
      margin-top: 5px;
      color: var(--light-text);
      font-size: 0.85rem;
    }

    .payment-methods {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 15px;
      margin-top: 15px;
    }

    .payment-method {
      display: flex;
      align-items: flex-start;
      padding: 18px;
      border: 2px solid #e0e0e0;
      border-radius: 12px;
      cursor: pointer;
      transition: all 0.3s ease;
      background: var(--white);
    }

    .payment-method:hover {
      border-color: var(--btn-color);
      box-shadow: 0 5px 15px rgba(205, 127, 127, 0.15);
      transform: translateY(-2px);
    }

    .payment-method input[type="radio"] {
      margin-right: 12px;
      margin-top: 2px;
      width: 18px;
      height: 18px;
      cursor: pointer;
      accent-color: var(--btn-color);
    }

    .payment-method div {
      flex: 1;
    }

    .payment-method i {
      font-size: 1.3rem;
      color: var(--btn-color);
      margin-right: 8px;
    }

    .payment-method strong {
      display: block;
      color: var(--text-dark);
      font-size: 1rem;
      margin-bottom: 5px;
    }

    .payment-method p {
      margin: 5px 0 0 0;
      color: var(--light-text);
      font-size: 0.85rem;
    }

    .payment-method.selected {
      border-color: var(--btn-color);
      background: linear-gradient(135deg, #fff7f7 0%, #fdecec 100%);
      box-shadow: 0 5px 15px rgba(205, 127, 127, 0.2);
    }

    .shipping-info {
      background: linear-gradient(135deg, #f5cdcd 0%, #fdecec 100%);
      padding: 20px;
      border-radius: 12px;
      margin-top: 20px;
      border: 2px solid var(--btn-color);
    }

    .shipping-info h4 {
      margin: 0 0 12px 0;
      color: var(--text-dark);
      font-size: 1rem;
      font-weight: 700;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .shipping-info h4 i {
      color: var(--btn-color);
      font-size: 1.2rem;
    }

    .shipping-info p {
      margin: 0;
      color: var(--light-text);
      font-size: 0.9rem;
      line-height: 1.6;
    }

    .shipping-info strong {
      color: var(--text-dark);
    }

    .order-summary {
      background: var(--white);
      border-radius: 20px;
      box-shadow: 0 10px 30px rgba(205, 127, 127, 0.15);
      overflow: hidden;
      position: sticky;
      top: 20px;
      max-height: calc(100vh - 40px);
      overflow-y: auto;
      transition: all 0.3s ease;
    }

    .order-summary:hover {
      box-shadow: 0 15px 40px rgba(205, 127, 127, 0.2);
    }

    .summary-header {
      background: linear-gradient(135deg, #f5cdcd 0%, #cc7f7f 100%);
      color: var(--white);
      padding: 25px 30px;
      text-align: center;
    }

    .summary-header h3 {
      margin: 0;
      font-size: 1.3rem;
      font-weight: 700;
      font-family: var(--header-font);
      text-transform: uppercase;
      letter-spacing: 1px;
    }

    .summary-body {
      padding: 30px;
    }

    .summary-items {
      margin-bottom: 25px;
    }

    .summary-item {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      padding: 15px 0;
      border-bottom: 1px solid #f0f0f0;
      gap: 15px;
    }

    .summary-item:last-child {
      border-bottom: none;
    }

    .item-info {
      flex: 1;
    }

    .item-name {
      font-weight: 700;
      margin-bottom: 8px;
      color: var(--text-dark);
      font-size: 1rem;
    }

    .item-details {
      color: var(--light-text);
      font-size: 0.85rem;
      margin-bottom: 5px;
    }

    .item-price {
      font-weight: 700;
      color: var(--hover-color);
      font-family: var(--header-font);
      font-size: 1.1rem;
      white-space: nowrap;
    }

    .summary-totals {
      background: linear-gradient(135deg, #f5cdcd 0%, #fdecec 100%);
      padding: 20px;
      border-radius: 12px;
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

    .place-order-btn {
      width: 100%;
      padding: 18px 25px;
      background: linear-gradient(135deg, var(--btn-color) 0%, var(--hover-color) 100%);
      color: var(--white);
      border: none;
      border-radius: 25px;
      font-size: 1.1rem;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.3s ease;
      margin-top: 25px;
      text-transform: uppercase;
      letter-spacing: 1px;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      font-family: 'Montserrat', sans-serif;
    }

    .place-order-btn:hover {
      transform: translateY(-3px);
      box-shadow: 0 10px 25px rgba(205, 127, 127, 0.3);
    }

    .place-order-btn:disabled {
      background: linear-gradient(135deg, #999 0%, #777 100%);
      cursor: not-allowed;
      transform: none;
      box-shadow: none;
      opacity: 0.7;
    }

    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }
    
    .animate-spin {
      animation: spin 1s linear infinite;
      display: inline-block;
    }

    /* Quantity Controls */
    .quantity-controls {
      display: flex;
      align-items: center;
      gap: 8px;
      margin-top: 8px;
      background: linear-gradient(135deg, #f5cdcd 0%, #fdecec 100%);
      padding: 5px 10px;
      border-radius: 20px;
      width: fit-content;
    }

    .qty-btn {
      width: 28px;
      height: 28px;
      border: none;
      background: var(--white);
      border-radius: 50%;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 16px;
      font-weight: 700;
      color: var(--btn-color);
      transition: all 0.3s ease;
      box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    }

    .qty-btn:hover {
      background: var(--btn-color);
      color: var(--white);
      transform: scale(1.1);
    }

    .qty-btn:disabled {
      opacity: 0.5;
      cursor: not-allowed;
      transform: none;
    }

    .qty-input {
      width: 40px;
      height: 28px;
      text-align: center;
      border: none;
      background: transparent;
      border-radius: 5px;
      font-size: 14px;
      font-weight: 700;
      padding: 0;
      color: var(--text-dark);
    }
    
    .qty-input:focus {
      outline: none;
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
      .checkout-container {
        grid-template-columns: 1fr;
      }

      .order-summary {
        position: static;
        max-height: none;
      }
    }

    @media (max-width: 768px) {
      .section-title {
        font-size: 2rem;
      }

      .section-subtitle {
        font-size: 0.95rem;
      }

      .payment-methods {
        grid-template-columns: 1fr;
      }

      .form-body,
      .summary-body {
        padding: 20px;
      }

      .form-header,
      .summary-header {
        padding: 20px;
      }

      .form-header h3,
      .summary-header h3 {
        font-size: 1.1rem;
      }
    }

    @media (max-width: 480px) {
      .checkout-section {
        padding: 20px 0 60px;
      }

      .section-header {
        padding: 30px 15px;
      }

      .section-title {
        font-size: 1.6rem;
      }

      .form-body,
      .summary-body {
        padding: 15px;
      }

      .payment-method {
        padding: 15px;
      }

      .shipping-info {
        padding: 15px;
      }

      .place-order-btn {
        padding: 15px 20px;
        font-size: 1rem;
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

    .checkout-form,
    .order-summary {
      animation: fadeInUp 0.6s ease forwards;
    }

    .checkout-form {
      animation-delay: 0.1s;
    }

    .order-summary {
      animation-delay: 0.2s;
    }
  </style>
</head>

<body class="<?php echo isLoggedIn() ? 'user-logged-in' : 'user-logged-out'; ?>">
  <div style="padding: 1rem 2rem;">
    <a href="index.php" class="back-home"><i class="ri-arrow-left-line"></i> Kembali ke Beranda</a>
  </div>

  <!-- Checkout Section -->
  <section class="checkout-section">
    <div class="container">
      <div class="section-header" style="text-align: center;">
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
                  required><?php echo htmlspecialchars($prefillAddress); ?></textarea>
                <small style="color: #6c757d; font-size: 0.85rem;">
                  Sertakan: Alamat jalan, Kota, Kode pos. Biaya pengiriman akan dihitung berdasarkan lokasi Anda.
                </small>
              </div>

              <div class="shipping-info">
                <h4><i class="ri-truck-line"></i> Informasi Pengiriman</h4>
                <p>
                  <strong>Pulau Jawa:</strong> Rp 15.000<br>
                  <strong>Sumatera:</strong> Rp 20.000<br>
                  <strong>Bali:</strong> Rp 25.000<br>
                  <strong>Kalimantan:</strong> Rp 30.000<br>
                  <strong>Sulawesi & NTT:</strong> Rp 35.000<br>
                  <strong>Papua:</strong> Rp 40.000<br>
                  <strong>Estimasi pengiriman:</strong> 2-5 hari kerja
                </p>
              </div>

              <div class="form-group">
                <label>Metode Pembayaran *</label>
                <div class="payment-methods">
                  <label class="payment-method">
                    <input type="radio" name="payment_method" value="bank_transfer" required <?php echo $prefillPayment === 'bank_transfer' ? 'checked' : ''; ?>>
                    <div>
                      <i class="ri-bank-line"></i>
                      <strong>Transfer Bank</strong>
                      <p style="margin: 5px 0 0 0; color: #6c757d; font-size: 0.85rem;">
                        Transfer ke BCA, Mandiri, atau BNI
                      </p>
                    </div>
                  </label>

                  <label class="payment-method">
                    <input type="radio" name="payment_method" value="ewallet" <?php echo $prefillPayment === 'ewallet' ? 'checked' : ''; ?>>
                    <div>
                      <i class="ri-wallet-3-line"></i>
                      <strong>E-Wallet</strong>
                      <p style="margin: 5px 0 0 0; color: #6c757d; font-size: 0.85rem;">
                        GoPay, OVO, DANA, ShopeePay
                      </p>
                    </div>
                  </label>

                  <label class="payment-method">
                    <input type="radio" name="payment_method" value="cod" <?php echo $prefillPayment === 'cod' ? 'checked' : ''; ?>>
                    <div>
                      <i class="ri-money-dollar-circle-line"></i>
                      <strong>Bayar di Tempat</strong>
                      <p style="margin: 5px 0 0 0; color: #6c757d; font-size: 0.85rem;">
                        Bayar saat barang diterima
                      </p>
                    </div>
                  </label>

                  <label class="payment-method">
                    <input type="radio" name="payment_method" value="credit_card" <?php echo $prefillPayment === 'credit_card' ? 'checked' : ''; ?>>
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
                  placeholder="Instruksi khusus untuk pesanan Anda..."><?php echo htmlspecialchars($prefillNotes); ?></textarea>
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
                      <div class="quantity-controls">
                        <button type="button" class="qty-btn decrease" data-id="<?php echo $item['id']; ?>">-</button>
                        <input type="number" class="qty-input" data-id="<?php echo $item['id']; ?>" value="<?php echo $item['quantity']; ?>" min="1" readonly>
                        <button type="button" class="qty-btn increase" data-id="<?php echo $item['id']; ?>">+</button>
                      </div>
                    </div>
                    <div class="item-price" id="item-subtotal-<?php echo $item['id']; ?>"><?php echo formatRupiah($item['subtotal']); ?></div>
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

      // Detect location and set shipping fee
      for (const location of javaLocations) {
        if (addressLower.includes(location)) return 15000;
      }

      for (const location of sumatraLocations) {
        if (addressLower.includes(location)) return 20000;
      }

      for (const location of baliLocations) {
        if (addressLower.includes(location)) return 25000;
      }

      for (const location of kalimantanLocations) {
        if (addressLower.includes(location)) return 30000;
      }

      for (const location of sulawesiLocations) {
        if (addressLower.includes(location)) return 35000;
      }

      for (const location of papuaLocations) {
        if (addressLower.includes(location)) return 40000;
      }

      for (const location of nttLocations) {
        if (addressLower.includes(location)) return 35000;
      }

      return 15000; // Default
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

      // Ensure place_order flag is submitted even if button disabled
      let placeOrderField = document.querySelector('input[name="place_order"]');
      if (!placeOrderField) {
        placeOrderField = document.createElement('input');
        placeOrderField.type = 'hidden';
        placeOrderField.name = 'place_order';
        placeOrderField.value = '1';
        this.appendChild(placeOrderField);
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

    // Dynamic Quantity Update
    document.querySelectorAll('.qty-btn').forEach(btn => {
      btn.addEventListener('click', function() {
        const id = this.dataset.id;
        const isIncrease = this.classList.contains('increase');
        const input = document.querySelector(`.qty-input[data-id="${id}"]`);
        let currentQty = parseInt(input.value);
        
        if (isIncrease) {
          currentQty++;
        } else {
          if (currentQty > 1) currentQty--;
          else return; // Don't go below 1
        }
        
        // Optimistic UI update
        input.value = currentQty;
        
        // Disable buttons temporarily
        const btns = document.querySelectorAll(`.qty-btn[data-id="${id}"]`);
        btns.forEach(b => b.disabled = true);
        
        // Send AJAX
        const formData = new FormData();
        formData.append('action', 'update_cart_item');
        formData.append('product_id', id);
        formData.append('quantity', currentQty);
        
        fetch('checkout.php', {
          method: 'POST',
          body: formData
        })
        .then(res => res.json())
        .then(data => {
          btns.forEach(b => b.disabled = false);
          
          if (data.success) {
            // Update item subtotal
            const itemSubtotalEl = document.getElementById(`item-subtotal-${id}`);
            if (itemSubtotalEl) {
              itemSubtotalEl.textContent = formatRupiah(data.itemSubtotal);
            }
            
            // Update Subtotal
            const subtotalEl = document.getElementById('subtotal');
            if (subtotalEl) {
              subtotalEl.textContent = formatRupiah(data.totalAmount);
            }
            
            // Recalculate Grand Total
            // We need to get current shipping fee
            const shippingFeeText = document.getElementById('shippingFee').textContent;
            // Parse shipping fee from string (remove non-digits)
            const shippingFee = parseInt(shippingFeeText.replace(/[^0-9]/g, '')) || 0;
            
            const grandTotal = data.totalAmount + shippingFee;
            const grandTotalEl = document.getElementById('grandTotal');
            if (grandTotalEl) {
              grandTotalEl.textContent = formatRupiah(grandTotal);
            }
            
            // Update hidden inputs if any (not needed here as we use session)
          } else {
            showNotification(data.message || 'Gagal mengupdate keranjang', 'error');
            // Revert value
            input.value = isIncrease ? currentQty - 1 : currentQty + 1;
          }
        })
        .catch(err => {
          btns.forEach(b => b.disabled = false);
          console.error(err);
          showNotification('Terjadi kesalahan koneksi', 'error');
          // Revert value
          input.value = isIncrease ? currentQty - 1 : currentQty + 1;
        });
      });
    });
  </script>
</body>

</html>
