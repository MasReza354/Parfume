<?php
require_once 'config/database.php';

// Check if user is logged in
if (!isLoggedIn()) {
  header('Location: auth/login.php?redirect=favorites');
  exit;
}

// Handle AJAX request for add to favorites
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_favorites'])) {
  $productId = $_POST['product_id'] ?? 0;

  if ($productId > 0) {
    $product = $conn->query("SELECT * FROM products WHERE id = $productId AND status = 'active'")->fetch_assoc();
    if ($product) {
      // Create favorites table if not exists
      $conn->query("CREATE TABLE IF NOT EXISTS favorites (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        product_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
        UNIQUE KEY unique_favorite (user_id, product_id)
      )");

      // Check if already in favorites
      $check = $conn->prepare("SELECT id FROM favorites WHERE user_id = ? AND product_id = ?");
      $check->bind_param("ii", $_SESSION['user_id'], $productId);
      $check->execute();

      if ($check->get_result()->num_rows === 0) {
        // Add to favorites
        $insert = $conn->prepare("INSERT INTO favorites (user_id, product_id) VALUES (?, ?)");
        $insert->bind_param("ii", $_SESSION['user_id'], $productId);
        $insert->execute();
      }

      // Return JSON response for AJAX requests
      if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Added to favorites!']);
        exit;
      }
    }
  }
  header('Location: favorites.php');
  exit;
}

// Handle remove from favorites
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_favorite'])) {
  $productId = $_POST['product_id'] ?? 0;

  $remove = $conn->prepare("DELETE FROM favorites WHERE user_id = ? AND product_id = ?");
  $remove->bind_param("ii", $_SESSION['user_id'], $productId);
  $remove->execute();

  header('Location: favorites.php');
  exit;
}

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
  $selectedItems = $_POST['selected_items'] ?? [];
  $action = $_POST['bulk_action'];

  if (!empty($selectedItems) && !empty($action)) {
    foreach ($selectedItems as $productId) {
      if ($action === 'remove_selected') {
        $remove = $conn->prepare("DELETE FROM favorites WHERE user_id = ? AND product_id = ?");
        $remove->bind_param("ii", $_SESSION['user_id'], $productId);
        $remove->execute();
      } elseif ($action === 'add_to_cart_selected') {
        // Add selected items to cart
        if (!isset($_SESSION['cart'])) {
          $_SESSION['cart'] = [];
        }
        if (!isset($_SESSION['cart'][$productId])) {
          $_SESSION['cart'][$productId] = 1;
        }
      }
    }

    $_SESSION['success_message'] = ($action === 'remove_selected') ?
      'Selected items removed from favorites!' :
      'Selected items added to cart!';
  }
  header('Location: favorites.php');
  exit;
}

// Get favorite items from database
$favorites = [];
$conn->query("CREATE TABLE IF NOT EXISTS favorites (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  product_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  UNIQUE KEY unique_favorite (user_id, product_id)
)");

$stmt = $conn->prepare("SELECT p.* FROM products p 
                   INNER JOIN favorites f ON p.id = f.product_id 
                   WHERE f.user_id = ? AND p.status = 'active' 
                   ORDER BY f.created_at DESC");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
  $favorites[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Favorites - Ardéliana Lux</title>
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="perfume-cards.css">
  <link rel="stylesheet" href="cart.css">
  <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    .favorites-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 30px;
      flex-wrap: wrap;
      gap: 20px;
    }

    .favorites-actions {
      display: flex;
      gap: 10px;
      align-items: center;
    }

    .bulk-actions {
      display: none;
      gap: 10px;
      align-items: center;
    }

    .bulk-actions.active {
      display: flex;
    }

    .select-all-container {
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .select-all {
      width: 20px;
      height: 20px;
      cursor: pointer;
    }

    .selected-count {
      background: #667eea;
      color: white;
      padding: 4px 8px;
      border-radius: 4px;
      font-size: 0.9rem;
      font-weight: 500;
    }

    .btn-bulk {
      padding: 8px 16px;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      font-weight: 500;
      transition: all 0.3s ease;
    }

    .btn-bulk-remove {
      background: #dc3545;
      color: white;
    }

    .btn-bulk-remove:hover {
      background: #c82333;
    }

    .btn-bulk-cart {
      background: #28a745;
      color: white;
    }

    .btn-bulk-cart:hover {
      background: #218838;
    }

    .btn-bulk:disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }

    .perfume-card {
      position: relative;
    }

    .favorite-checkbox {
      position: absolute;
      top: 10px;
      left: 10px;
      width: 20px;
      height: 20px;
      cursor: pointer;
      z-index: 10;
      background: white;
      border: 2px solid #e9ecef;
      border-radius: 4px;
    }

    .favorite-checkbox:checked {
      background: #667eea;
      border-color: #667eea;
    }

    .perfume-card.has-selection .card-image {
      padding-top: 35px;
    }

    @media (max-width: 768px) {
      .favorites-header {
        flex-direction: column;
        align-items: stretch;
      }

      .favorites-actions {
        justify-content: center;
        flex-wrap: wrap;
      }

      .bulk-actions {
        width: 100%;
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
                <a href="favorites.php" class="active"><i class="ri-heart-line"></i> Favorites</a>
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

  <!-- Favorites Section -->
  <section class="cart-section">
    <div class="container">
      <div class="favorites-header">
        <div>
          <h1 class="section-title">My Favorites</h1>
          <p class="section-subtitle">Your favorite fragrance collection</p>
        </div>

        <div class="favorites-actions">
          <div class="select-all-container">
            <input type="checkbox" id="selectAll" class="select-all">
            <label for="selectAll">Select All</label>
          </div>
          <div class="selected-count" id="selectedCount" style="display: none;">
            <span id="selectedNumber">0</span> selected
          </div>
        </div>

        <div class="bulk-actions" id="bulkActions">
          <button type="button" class="btn-bulk btn-bulk-cart" onclick="bulkAddToCart()">
            <i class="ri-shopping-cart-line"></i> Add to Cart
          </button>
          <button type="button" class="btn-bulk btn-bulk-remove" onclick="bulkRemove()">
            <i class="ri-delete-bin-line"></i> Remove
          </button>
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

      <?php if (empty($favorites)): ?>
        <div class="empty-cart">
          <i class="ri-heart-line"></i>
          <h3>No favorites yet</h3>
          <p>Start adding your favorite fragrances to this collection</p>
          <a href="index.php" class="btn-primary">
            <i class="ri-shopping-bag-line"></i>
            <span>Explore Products</span>
          </a>
        </div>
      <?php else: ?>
        <form method="POST" id="favoritesForm">
          <div class="perfume-grid">
            <?php foreach ($favorites as $product): ?>
              <div class="perfume-card has-selection" data-id="<?php echo $product['id']; ?>">
                <input type="checkbox" class="favorite-checkbox" name="selected_items[]" value="<?php echo $product['id']; ?>">

                <div class="card-image">
                  <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                  <div class="card-actions">
                    <button class="action-btn favorite-btn active" data-id="<?php echo $product['id']; ?>">
                      <i class="ri-heart-fill"></i>
                    </button>
                    <button class="action-btn quick-view-btn" data-id="<?php echo $product['id']; ?>">
                      <i class="ri-eye-line"></i>
                    </button>
                  </div>
                </div>

                <div class="card-content">
                  <div class="perfume-type"><?php echo htmlspecialchars($product['type']); ?></div>
                  <h3 class="perfume-name"><?php echo htmlspecialchars($product['name']); ?></h3>
                  <div class="perfume-scent">
                    <i class="ri-leaf-line"></i>
                    <span><?php echo htmlspecialchars($product['scent']); ?></span>
                  </div>
                  <p class="perfume-description"><?php echo htmlspecialchars($product['description']); ?></p>
                  <div class="card-footer">
                    <div class="price"><?php echo formatRupiah($product['price']); ?></div>
                    <button class="add-to-cart-btn" data-id="<?php echo $product['id']; ?>" data-name="<?php echo htmlspecialchars($product['name']); ?>">
                      <i class="ri-shopping-cart-line"></i>
                      Add to Cart
                    </button>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>

          <div class="bulk-actions" style="margin-top: 20px;">
            <button type="button" class="btn-bulk btn-bulk-cart" onclick="bulkAddToCart()">
              <i class="ri-shopping-cart-line"></i> Add Selected to Cart
            </button>
            <button type="button" class="btn-bulk btn-bulk-remove" onclick="bulkRemove()">
              <i class="ri-delete-bin-line"></i> Remove Selected
            </button>
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

    // Selection management
    function updateSelectionUI() {
      const checkboxes = document.querySelectorAll('.favorite-checkbox:checked');
      const selectedCount = checkboxes.length;
      const selectedCountElement = document.getElementById('selectedCount');
      const selectedNumberElement = document.getElementById('selectedNumber');
      const bulkActions = document.getElementById('bulkActions');
      const selectAll = document.getElementById('selectAll');

      if (selectedCount > 0) {
        selectedCountElement.style.display = 'block';
        selectedNumberElement.textContent = selectedCount;
        bulkActions.classList.add('active');
      } else {
        selectedCountElement.style.display = 'none';
        bulkActions.classList.remove('active');
      }

      // Update select all checkbox
      const allCheckboxes = document.querySelectorAll('.favorite-checkbox');
      const allChecked = Array.from(allCheckboxes).every(cb => cb.checked);
      selectAll.checked = allChecked;
    }

    // Select all functionality
    document.getElementById('selectAll')?.addEventListener('change', function() {
      const checkboxes = document.querySelectorAll('.favorite-checkbox');
      checkboxes.forEach(cb => cb.checked = this.checked);
      updateSelectionUI();
    });

    // Individual checkbox change
    document.querySelectorAll('.favorite-checkbox').forEach(checkbox => {
      checkbox.addEventListener('change', updateSelectionUI);
    });

    // Bulk actions
    function bulkAddToCart() {
      const checkboxes = document.querySelectorAll('.favorite-checkbox:checked');
      const productIds = Array.from(checkboxes).map(cb => cb.value);

      if (productIds.length === 0) {
        showNotification('Please select items to add to cart', 'warning');
        return;
      }

      // Create form to add multiple items to cart
      const form = document.createElement('form');
      form.method = 'POST';
      form.action = 'cart.php';

      productIds.forEach(id => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'bulk_items[]';
        input.value = id;
        form.appendChild(input);
      });

      const actionInput = document.createElement('input');
      actionInput.type = 'hidden';
      actionInput.name = 'bulk_add_to_cart';
      actionInput.value = '1';
      form.appendChild(actionInput);

      document.body.appendChild(form);
      form.submit();
    }

    function bulkRemove() {
      const checkboxes = document.querySelectorAll('.favorite-checkbox:checked');
      const productIds = Array.from(checkboxes).map(cb => cb.value);

      if (productIds.length === 0) {
        showNotification('Please select items to remove', 'warning');
        return;
      }

      if (confirm(`Remove ${productIds.length} item(s) from favorites?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'favorites.php';

        productIds.forEach(id => {
          const input = document.createElement('input');
          input.type = 'hidden';
          input.name = 'selected_items[]';
          input.value = id;
          form.appendChild(input);
        });

        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'bulk_action';
        actionInput.value = 'remove_selected';
        form.appendChild(actionInput);

        document.body.appendChild(form);
        form.submit();
      }
    }

    // Add to cart functionality (single item)
    document.querySelectorAll('.add-to-cart-btn').forEach(button => {
      button.addEventListener('click', function() {
        const productId = this.dataset.id;
        const productName = this.dataset.name;

        // AJAX add to cart
        fetch('cart.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded',
              'X-Requested-With': 'XMLHttpRequest'
            },
            body: `add_to_cart=1&product_id=${productId}&quantity=1`
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              showNotification(`${productName} added to cart!`, 'success');
              updateCartBadge();
            } else {
              showNotification('Failed to add to cart', 'error');
            }
          })
          .catch(error => {
            console.error('Error:', error);
            showNotification('Error adding to cart', 'error');
          });
      });
    });

    // Remove from favorites functionality (single item)
    document.querySelectorAll('.favorite-btn.active').forEach(button => {
      button.addEventListener('click', function() {
        const productId = this.dataset.id;

        if (confirm('Remove this item from favorites?')) {
          // AJAX remove from favorites
          fetch('favorites.php', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
              },
              body: `remove_favorite=1&product_id=${productId}`
            })
            .then(response => response.json())
            .then(data => {
              if (data.success) {
                location.reload();
              } else {
                showNotification('Failed to remove from favorites', 'error');
              }
            })
            .catch(error => {
              console.error('Error:', error);
              showNotification('Error removing from favorites', 'error');
            });
        }
      });
    });

    function updateCartBadge() {
      fetch('cart.php?get_count=1')
        .then(response => response.json())
        .then(data => {
          const badge = document.querySelector('.cart-badge');
          if (badge) {
            if (data.count > 0) {
              badge.textContent = data.count;
              badge.style.display = 'inline-block';
            } else {
              badge.style.display = 'none';
            }
          }
        })
        .catch(error => {
          console.error('Error updating cart badge:', error);
        });
    }

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

    // Update cart badge on page load
    document.addEventListener('DOMContentLoaded', function() {
      updateCartBadge();
      updateSelectionUI();
    });
  </script>
</body>

</html>