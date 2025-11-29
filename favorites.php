<?php
require_once 'config/database.php';

// Language setup
$lang = $_GET['lang'] ?? ($_SESSION['lang'] ?? 'id');
$_SESSION['lang'] = $lang;

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
  <title>My Favorites - Parfumé Lux</title>
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="perfume-cards.css">
  <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    /* Favorites Page Specific Styles */
    .favorites-section {
      min-height: 100vh;
      padding: 40px 0 80px;
      background: linear-gradient(135deg, #fdecec 0%, #f5cdcd 50%, #fdecec 100%);
    }

    .favorites-hero {
      margin-bottom: 40px;
    }

    .back-link {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      color: var(--text-dark);
      font-weight: 600;
      font-size: 0.95rem;
      margin-bottom: 30px;
      transition: all 0.3s ease;
      text-decoration: none;
    }

    .back-link:hover {
      color: var(--hover-color);
      transform: translateX(-5px);
    }

    .back-link i {
      font-size: 1.2rem;
    }

    .favorites-hero-content {
      text-align: center;
      padding: 40px 20px;
      background: var(--white);
      border-radius: 20px;
      box-shadow: 0 10px 30px rgba(205, 127, 127, 0.1);
    }

    .favorites-title {
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

    .favorites-subtitle {
      font-size: 1.1rem;
      color: var(--light-text);
      margin-bottom: 25px;
    }

    .favorites-stats {
      display: flex;
      justify-content: center;
      align-items: center;
      gap: 20px;
      margin-top: 20px;
    }

    .stat-item {
      display: flex;
      align-items: center;
      gap: 8px;
      color: var(--text-dark);
      font-weight: 600;
    }

    .stat-item i {
      font-size: 1.3rem;
      color: var(--btn-color);
    }

    .stat-divider {
      width: 1px;
      height: 20px;
      background: #e0e0e0;
    }

    .favorites-panel {
      background: var(--white);
      border-radius: 20px;
      padding: 30px;
      box-shadow: 0 10px 30px rgba(205, 127, 127, 0.1);
    }

    /* Controls */
    .favorites-controls {
      display: flex;
      align-items: center;
      gap: 20px;
      margin-bottom: 30px;
      padding: 20px;
      background: linear-gradient(135deg, #f5cdcd 0%, #fdecec 100%);
      border-radius: 15px;
      flex-wrap: wrap;
    }

    .select-all-wrapper {
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .custom-checkbox {
      width: 20px;
      height: 20px;
      cursor: pointer;
      accent-color: var(--btn-color);
    }

    .checkbox-label {
      display: flex;
      align-items: center;
      gap: 8px;
      cursor: pointer;
      font-weight: 600;
      color: var(--text-dark);
      user-select: none;
    }

    .selected-info {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 8px 16px;
      background: var(--white);
      border-radius: 20px;
      color: var(--hover-color);
      font-weight: 600;
      box-shadow: 0 2px 10px rgba(205, 127, 127, 0.15);
    }

    .selected-info i {
      font-size: 1.2rem;
    }

    .bulk-actions {
      display: flex;
      gap: 10px;
      margin-left: auto;
      opacity: 0;
      pointer-events: none;
      transition: all 0.3s ease;
    }

    .bulk-actions.active {
      opacity: 1;
      pointer-events: all;
    }

    .btn-bulk {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 10px 20px;
      border: none;
      border-radius: 25px;
      font-weight: 600;
      font-size: 0.9rem;
      cursor: pointer;
      transition: all 0.3s ease;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .btn-bulk-cart {
      background: linear-gradient(135deg, var(--btn-color) 0%, var(--hover-color) 100%);
      color: var(--white);
    }

    .btn-bulk-cart:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(205, 127, 127, 0.3);
    }

    .btn-bulk-remove {
      background: var(--white);
      color: #e74c3c;
      border: 2px solid #e74c3c;
    }

    .btn-bulk-remove:hover {
      background: #e74c3c;
      color: var(--white);
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(231, 76, 60, 0.3);
    }

    /* Favorites Grid */
    .favorites-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
      gap: 25px;
    }

    .favorite-card {
      background: var(--white);
      border-radius: 15px;
      overflow: hidden;
      box-shadow: 0 5px 20px rgba(205, 127, 127, 0.1);
      transition: all 0.3s ease;
      position: relative;
      border: 2px solid transparent;
    }

    .favorite-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 15px 35px rgba(205, 127, 127, 0.2);
      border-color: var(--btn-color);
    }

    .favorite-card-checkbox {
      position: absolute;
      top: 15px;
      left: 15px;
      z-index: 10;
      background: var(--white);
      width: 30px;
      height: 30px;
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    .favorite-card-image {
      position: relative;
      height: 280px;
      background: linear-gradient(135deg, #f5cdcd 0%, #cc7f7f 100%);
      overflow: hidden;
    }

    .favorite-card-image img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      transition: transform 0.4s ease;
    }

    .favorite-card:hover .favorite-card-image img {
      transform: scale(1.1);
    }

    .image-overlay {
      position: absolute;
      inset: 0;
      background: rgba(0, 0, 0, 0.5);
      display: flex;
      align-items: center;
      justify-content: center;
      opacity: 0;
      transition: opacity 0.3s ease;
    }

    .favorite-card:hover .image-overlay {
      opacity: 1;
    }

    .action-btn-new {
      background: var(--white);
      color: var(--text-dark);
      border: none;
      padding: 12px 24px;
      border-radius: 25px;
      font-weight: 600;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 8px;
      transition: all 0.3s ease;
    }

    .action-btn-new:hover {
      background: var(--btn-color);
      color: var(--white);
      transform: scale(1.05);
    }

    .favorite-card-content {
      padding: 20px;
    }

    .card-header-info {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 12px;
    }

    .product-type {
      display: inline-block;
      background: linear-gradient(135deg, #f5cdcd 0%, #cc7f7f 100%);
      color: var(--white);
      padding: 5px 12px;
      border-radius: 15px;
      font-size: 0.7rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 1px;
    }

    .favorite-btn-remove {
      background: none;
      border: none;
      color: #e74c3c;
      font-size: 1.5rem;
      cursor: pointer;
      transition: all 0.3s ease;
      padding: 5px;
    }

    .favorite-btn-remove:hover {
      transform: scale(1.2);
      color: #c0392b;
    }

    .product-name {
      font-size: 1.3rem;
      font-weight: 700;
      color: var(--text-dark);
      margin-bottom: 10px;
      font-family: var(--header-font);
      line-height: 1.2;
    }

    .product-scent {
      display: flex;
      align-items: center;
      gap: 8px;
      margin-bottom: 12px;
    }

    .product-scent i {
      color: var(--btn-color);
      font-size: 1rem;
    }

    .product-scent span {
      color: var(--light-text);
      font-weight: 500;
      font-size: 0.9rem;
    }

    .product-description {
      color: var(--light-text);
      font-size: 0.85rem;
      line-height: 1.5;
      margin-bottom: 15px;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }

    .card-footer-new {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding-top: 15px;
      border-top: 1px solid #f0f0f0;
      gap: 10px;
    }

    .product-price {
      font-size: 1.3rem;
      font-weight: 700;
      color: var(--hover-color);
      font-family: var(--header-font);
    }

    .btn-add-to-cart {
      background: linear-gradient(135deg, var(--btn-color) 0%, var(--hover-color) 100%);
      color: var(--white);
      border: none;
      padding: 10px 18px;
      border-radius: 20px;
      font-weight: 600;
      font-size: 0.85rem;
      cursor: pointer;
      transition: all 0.3s ease;
      display: flex;
      align-items: center;
      gap: 6px;
      white-space: nowrap;
    }

    .btn-add-to-cart:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(205, 127, 127, 0.3);
    }

    /* Empty State */
    .empty-favorites {
      text-align: center;
      padding: 80px 20px;
    }

    .empty-favorites-animation {
      position: relative;
      margin-bottom: 30px;
    }

    .heart-icon-large {
      font-size: 5rem;
      color: var(--btn-color);
      opacity: 0.3;
    }

    .floating-hearts {
      position: absolute;
      inset: 0;
    }

    .floating-hearts i {
      position: absolute;
      font-size: 1.5rem;
      color: var(--btn-color);
      opacity: 0.5;
      animation: float 3s ease-in-out infinite;
    }

    .heart-1 {
      top: 20%;
      left: 30%;
      animation-delay: 0s;
    }

    .heart-2 {
      top: 40%;
      right: 25%;
      animation-delay: 1s;
    }

    .heart-3 {
      bottom: 30%;
      left: 40%;
      animation-delay: 2s;
    }

    @keyframes float {
      0%, 100% {
        transform: translateY(0);
      }
      50% {
        transform: translateY(-20px);
      }
    }

    .empty-favorites h3 {
      font-size: 1.8rem;
      color: var(--text-dark);
      margin-bottom: 15px;
      font-weight: 700;
    }

    .empty-favorites p {
      font-size: 1rem;
      color: var(--light-text);
      margin-bottom: 30px;
      max-width: 500px;
      margin-left: auto;
      margin-right: auto;
    }

    .btn-explore {
      display: inline-flex;
      align-items: center;
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

    .btn-explore:hover {
      transform: translateY(-3px);
      box-shadow: 0 10px 25px rgba(205, 127, 127, 0.3);
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

    /* Responsive Design */
    @media (max-width: 768px) {
      .favorites-title {
        font-size: 2rem;
      }

      .favorites-subtitle {
        font-size: 0.95rem;
      }

      .favorites-stats {
        flex-direction: column;
        gap: 10px;
      }

      .stat-divider {
        display: none;
      }

      .favorites-panel {
        padding: 20px;
      }

      .favorites-controls {
        flex-direction: column;
        align-items: stretch;
      }

      .bulk-actions {
        margin-left: 0;
        width: 100%;
      }

      .btn-bulk {
        flex: 1;
      }

      .favorites-grid {
        grid-template-columns: 1fr;
        gap: 20px;
      }

      .card-footer-new {
        flex-direction: column;
        align-items: stretch;
      }

      .btn-add-to-cart {
        width: 100%;
        justify-content: center;
      }
    }

    @media (max-width: 480px) {
      .favorites-section {
        padding: 20px 0 60px;
      }

      .favorites-hero-content {
        padding: 30px 15px;
      }

      .favorites-title {
        font-size: 1.6rem;
      }

      .empty-favorites {
        padding: 60px 15px;
      }

      .heart-icon-large {
        font-size: 3.5rem;
      }
    }
  </style>
</head>

<body class="<?php echo isLoggedIn() ? 'user-logged-in' : 'user-logged-out'; ?>">
  <!-- Favorites Section -->
  <section class="favorites-section">
    <div class="container">
      <!-- Hero Header -->
      <div class="favorites-hero">
        <a href="index.php" class="back-link">
          <i class="ri-arrow-left-line"></i>
          <span>Back to Shop</span>
        </a>
        
        <div class="favorites-hero-content">
          <h1 class="favorites-title">My Favorites</h1>
          <p class="favorites-subtitle">Your curated collection of exquisite fragrances</p>
          <div class="favorites-stats">
            <div class="stat-item">
              <i class="ri-heart-line"></i>
              <span><?php echo count($favorites); ?> Items</span>
            </div>
            <div class="stat-divider"></div>
            <div class="stat-item">
              <i class="ri-star-line"></i>
              <span>Premium Selection</span>
            </div>
          </div>
        </div>
      </div>

      <div class="favorites-panel">
        <!-- Selection Controls -->
        <?php if (!empty($favorites)): ?>
        <div class="favorites-controls">
          <div class="select-all-wrapper">
            <input type="checkbox" id="selectAll" class="custom-checkbox">
            <label for="selectAll" class="checkbox-label">
              <span class="checkbox-text">Select All</span>
            </label>
          </div>
          
          <div class="selected-info" id="selectedCount" style="display: none;">
            <i class="ri-checkbox-circle-fill"></i>
            <span><strong id="selectedNumber">0</strong> items selected</span>
          </div>

          <div class="bulk-actions" id="bulkActions">
            <button type="button" class="btn-bulk btn-bulk-cart" onclick="bulkAddToCart()">
              <i class="ri-shopping-cart-line"></i>
              <span>Add to Cart</span>
            </button>
            <button type="button" class="btn-bulk btn-bulk-remove" onclick="bulkRemove()">
              <i class="ri-delete-bin-line"></i>
              <span>Remove</span>
            </button>
          </div>
        </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success_message'])): ?>
          <div class="notification success">
            <?php
            echo $_SESSION['success_message'];
            unset($_SESSION['success_message']);
            ?>
          </div>
        <?php endif; ?>

        <?php if (empty($favorites)): ?>
          <div class="empty-favorites">
            <div class="empty-favorites-animation">
              <div class="heart-icon-large">
                <i class="ri-heart-line"></i>
              </div>
              <div class="floating-hearts">
                <i class="ri-heart-fill heart-1"></i>
                <i class="ri-heart-fill heart-2"></i>
                <i class="ri-heart-fill heart-3"></i>
              </div>
            </div>
            <h3>Your Favorites Collection is Empty</h3>
            <p>Discover and save your favorite fragrances to create your personal collection</p>
            <a href="index.php" class="btn-explore">
              <i class="ri-shopping-bag-3-line"></i>
              <span>Explore Our Collection</span>
            </a>
          </div>
        <?php else: ?>
          <form method="POST" id="favoritesForm">
            <div class="favorites-grid">
              <?php foreach ($favorites as $product): ?>
                <div class="favorite-card" data-id="<?php echo $product['id']; ?>">
                  <div class="favorite-card-checkbox">
                    <input type="checkbox" class="favorite-checkbox custom-checkbox" 
                           id="fav-<?php echo $product['id']; ?>" 
                           name="selected_items[]" 
                           value="<?php echo $product['id']; ?>">
                  </div>

                  <div class="favorite-card-image">
                    <img src="<?php echo htmlspecialchars($product['image']); ?>" 
                         alt="<?php echo htmlspecialchars($product['name']); ?>">
                    <div class="image-overlay">
                      <button type="button" class="action-btn-new quick-view-btn" data-id="<?php echo $product['id']; ?>">
                        <i class="ri-eye-line"></i>
                        <span>Quick View</span>
                      </button>
                    </div>
                  </div>

                  <div class="favorite-card-content">
                    <div class="card-header-info">
                      <span class="product-type"><?php echo htmlspecialchars($product['type']); ?></span>
                      <button type="button" class="favorite-btn-remove" data-id="<?php echo $product['id']; ?>" title="Remove from favorites">
                        <i class="ri-heart-fill"></i>
                      </button>
                    </div>
                    
                    <h3 class="product-name"><?php echo htmlspecialchars($product['name']); ?></h3>
                    
                    <div class="product-scent">
                      <i class="ri-leaf-line"></i>
                      <span><?php echo htmlspecialchars($product['scent']); ?></span>
                    </div>
                    
                    <p class="product-description"><?php echo htmlspecialchars($product['description']); ?></p>
                    
                    <div class="card-footer-new">
                      <div class="product-price"><?php echo formatRupiah($product['price']); ?></div>
                      <button type="button" class="btn-add-to-cart" data-id="<?php echo $product['id']; ?>" data-name="<?php echo htmlspecialchars($product['name']); ?>">
                        <i class="ri-shopping-cart-line"></i>
                        <span>Add to Cart</span>
                      </button>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </form>
        <?php endif; ?>
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
    // Selection management
    function updateSelectionUI() {
      const checkboxes = document.querySelectorAll('.favorite-checkbox:checked');
      const selectedCount = checkboxes.length;
      const selectedCountElement = document.getElementById('selectedCount');
      const selectedNumberElement = document.getElementById('selectedNumber');
      const bulkActions = document.getElementById('bulkActions');
      const selectAll = document.getElementById('selectAll');

      if (selectedCount > 0) {
        selectedCountElement.style.display = 'flex';
        selectedNumberElement.textContent = selectedCount;
        bulkActions.classList.add('active');
      } else {
        selectedCountElement.style.display = 'none';
        bulkActions.classList.remove('active');
      }

      // Update select all checkbox
      const allCheckboxes = document.querySelectorAll('.favorite-checkbox');
      const allChecked = allCheckboxes.length > 0 && Array.from(allCheckboxes).every(cb => cb.checked);
      if (selectAll) selectAll.checked = allChecked;
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
    document.querySelectorAll('.btn-add-to-cart').forEach(button => {
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
    document.querySelectorAll('.favorite-btn-remove').forEach(button => {
      button.addEventListener('click', function() {
        const productId = this.dataset.id;

        if (confirm('Remove this item from favorites?')) {
          const form = document.createElement('form');
          form.method = 'POST';
          form.action = 'favorites.php';

          const productInput = document.createElement('input');
          productInput.type = 'hidden';
          productInput.name = 'product_id';
          productInput.value = productId;
          form.appendChild(productInput);

          const actionInput = document.createElement('input');
          actionInput.type = 'hidden';
          actionInput.name = 'remove_favorite';
          actionInput.value = '1';
          form.appendChild(actionInput);

          document.body.appendChild(form);
          form.submit();
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
