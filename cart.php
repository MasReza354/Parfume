<?php
require_once 'config/database.php';

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
  'bubble_wrap' => ['name' => 'Bubble Wrap Tambahan', 'price' => 5000, 'image' => 'https://via.placeholder.com/100x100?text=Bubble+Wrap', 'type' => 'Packaging', 'scent' => 'Protection'],
  'wooden_packing' => ['name' => 'Packing Kayu', 'price' => 15000, 'image' => 'https://via.placeholder.com/100x100?text=Wooden+Box', 'type' => 'Packaging', 'scent' => 'Premium']
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
  <title>Shopping Cart - Ardéliana Lux</title>
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="perfume-cards.css">
  <link rel="stylesheet" href="cart.css">
  <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    .quantity-controls {
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .quantity-btn {
      width: 32px;
      height: 32px;
      border: 2px solid #e9ecef;
      background: white;
      border-radius: 6px;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: all 0.3s ease;
    }

    .quantity-btn:hover {
      background: #f8f9fa;
      border-color: #667eea;
    }

    .quantity-input {
      width: 60px;
      height: 32px;
      text-align: center;
      border: 2px solid #e9ecef;
      border-radius: 6px;
      font-weight: 500;
    }

    .additional-items {
      background: white;
      border-radius: 10px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
      padding: 20px;
      margin-bottom: 20px;
    }

    .additional-items h3 {
      margin-bottom: 15px;
      color: #2c3e50;
    }

    .additional-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 15px;
      border: 1px solid #e9ecef;
      border-radius: 8px;
      margin-bottom: 10px;
      transition: all 0.3s ease;
    }

    .additional-item:hover {
      border-color: #667eea;
      box-shadow: 0 2px 8px rgba(102, 126, 234, 0.1);
    }

    .additional-item-info {
      display: flex;
      align-items: center;
      gap: 15px;
      flex: 1;
    }

    .additional-item img {
      width: 50px;
      height: 50px;
      border-radius: 6px;
      object-fit: cover;
    }

    .additional-item-details h4 {
      margin: 0 0 5px 0;
      font-size: 0.95rem;
      font-weight: 600;
    }

    .additional-item-details p {
      margin: 0;
      color: #6c757d;
      font-size: 0.85rem;
    }

    .additional-item-price {
      font-weight: 700;
      color: #e74c3c;
      margin-right: 15px;
    }

    .btn-add-item {
      padding: 8px 16px;
      background: #667eea;
      color: white;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      font-weight: 500;
      transition: all 0.3s ease;
    }

    .btn-add-item:hover {
      background: #5a67d8;
      transform: translateY(-1px);
    }

    @media (max-width: 768px) {
      .additional-item {
        flex-direction: column;
        align-items: stretch;
        gap: 15px;
      }

      .additional-item-info {
        justify-content: flex-start;
      }

      .additional-item-price {
        margin-right: 0;
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
            <img src="https://via.placeholder.com/50x50?text=BW" alt="Bubble Wrap">
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
            <img src="https://via.placeholder.com/50x50?text=Wood" alt="Wooden Packing">
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

  <!-- Footer -->
  <footer class="footer">
    <div class="container">
      <div class="footer-content">
        <div class="footer-section">
          <h3 data-translate="about_us">About Us</h3>
          <p data-translate="footer_description">Ardéliana Lux - Your destination for premium fragrances</p>
        </div>

        <div class="footer-section">
          <h3 data-translate="contact">Contact</h3>
          <p>Email: info@ardeliana.com<br>
            Phone: +62 21 5555 1234</p>
        </div>

        <div class="footer-section">
          <h3 data-translate="follow_us">Follow Us</h3>
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

    // Quantity control functions
    function updateQuantity(itemId, change) {
      const input = document.querySelector(`input[data-id="${itemId}"]`);
      const currentValue = parseInt(input.value);
      const newValue = Math.max(1, currentValue + change);
      const maxValue = parseInt(input.max);

      if (newValue <= maxValue) {
        input.value = newValue;
        updateSubtotal(itemId);
        updateCartTotal();
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