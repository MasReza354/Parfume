<?php
require_once 'config/database.php';

// Get current language
$lang = $_GET['lang'] ?? 'id';
$_SESSION['lang'] = $lang;

// Language translations
$translations = [
  'id' => [
    'home' => 'Beranda',
    'products' => 'Produk',
    'about' => 'Tentang',
    'contact' => 'Kontak',
    'login' => 'Masuk',
    'register' => 'Daftar',
    'logout' => 'Keluar',
    'profile' => 'Profil',
    'cart' => 'Keranjang',
    'favorites' => 'Favorit',
    'add_to_cart' => 'Keranjang',
    'add_to_favorites' => 'Tambah ke Favorit',
    'quick_view' => 'Lihat Cepat',
    'search_placeholder' => 'Cari parfum...',
    'filter_by_type' => 'Filter berdasarkan Tipe',
    'filter_by_scent' => 'Filter berdasarkan Aroma',
    'sort_by' => 'Urutkan berdasarkan',
    'all_types' => 'Semua Tipe',
    'all_scents' => 'Semua Aroma',
    'price_low_high' => 'Harga: Rendah ke Tinggi',
    'price_high_low' => 'Harga: Tinggi ke Rendah',
    'no_products' => 'Tidak ada produk ditemukan',
    'try_adjusting_filters' => 'Coba sesuaikan filter untuk melihat lebih banyak hasil',
    'about_title' => 'Tentang Parfumé Lux',
    'about_description' => 'Parfumé Lux adalah toko parfum premium yang menawarkan koleksi eksklusif wewangian berkualitas tinggi dari merek-merek terkenal dunia.',
    'contact_title' => 'Hubungi Kami',
    'contact_description' => 'Hubungi kami untuk informasi lebih lanjut tentang produk dan layanan kami.',
    'our_collection' => 'Koleksi Premium Kami',
    'discover_scent' => 'Temukan aroma sempurna Anda dari koleksi parfum eksklusif kami',
    'must_login' => 'Anda harus masuk untuk menambahkan produk ke keranjang',
    'login_required' => 'Login Diperlukan'
  ],
  'en' => [
    'home' => 'Home',
    'products' => 'Products',
    'about' => 'About',
    'contact' => 'Contact',
    'login' => 'Login',
    'register' => 'Register',
    'logout' => 'Logout',
    'profile' => 'Profile',
    'cart' => 'Cart',
    'favorites' => 'Favorites',
    'add_to_cart' => 'Add to Cart',
    'add_to_favorites' => 'Add to Favorites',
    'quick_view' => 'Quick View',
    'search_placeholder' => 'Search perfume...',
    'filter_by_type' => 'Filter by Type',
    'filter_by_scent' => 'Filter by Scent',
    'sort_by' => 'Sort by',
    'all_types' => 'All Types',
    'all_scents' => 'All Scents',
    'price_low_high' => 'Price: Low to High',
    'price_high_low' => 'Price: High to Low',
    'no_products' => 'No products found',
    'try_adjusting_filters' => 'Try adjusting your filters to see more results',
    'about_title' => 'About Parfumé Lux',
    'about_description' => 'Parfumé Lux is a premium perfume store offering exclusive collections of high-quality fragrances from world-renowned brands.',
    'contact_description' => 'Contact us for more information about our products and services.',
    'our_collection' => 'Our Premium Collection',
    'discover_scent' => 'Discover your perfect scent from our exclusive parfume collection',
    'must_login' => 'You must login to add products to cart',
    'login_required' => 'Login Required'
  ]
];

$t = $translations[$lang];

// Get products from database - only active products
$products = [];
$result = $conn->query("SELECT id, name, type, scent, price, description, image FROM products WHERE status = 'active' ORDER BY name");
if ($result) {
  while ($row = $result->fetch_assoc()) {
    $products[] = $row;
  }
}

// Handle page parameter
$page = $_GET['page'] ?? 'home';
?>

<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Parfumé Lux - Premium Parfume</title>

  <!-- ===== CSS LINK ===== -->
  <link rel="stylesheet" href="style.css" />
  <link rel="stylesheet" href="perfume-cards.css" />
  <link rel="stylesheet" href="auth.css" />

  <!-- ===== REMIX ICONS ===== -->
  <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet" />

  <!-- ===== GOOGLE FONTS ===== -->
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>

<body class="<?php echo isLoggedIn() ? 'user-logged-in' : 'user-logged-out'; ?>">
  <!-- ===== NAV ===== -->
  <nav>
    <div class="nav__logo">
      <a href="index.php">Parfumé<span> Lux.</span></a>
    </div>

    <ul class="nav__links">
      <li><a href="index.php" data-text="<?php echo $t['home']; ?>"><?php echo $t['home']; ?></a></li>
      <li><a href="#products" data-text="<?php echo $t['products']; ?>"><?php echo $t['products']; ?></a></li>
      <li><a href="#about" data-text="<?php echo $t['about']; ?>"><?php echo $t['about']; ?></a></li>
      <li><a href="#contact" data-text="<?php echo $t['contact']; ?>"><?php echo $t['contact']; ?></a></li>
    </ul>

    <div class="nav__btns">
      <!-- Language Switcher -->
      <div class="language-switcher">
        <a href="?lang=id" class="lang-btn <?php echo $lang === 'id' ? 'active' : ''; ?>">ID</a>
        <a href="?lang=en" class="lang-btn <?php echo $lang === 'en' ? 'active' : ''; ?>">EN</a>
      </div>

      <!-- User Status -->
      <?php if (isLoggedIn()): ?>
        <div class="user-menu">
          <div class="user-icon" onclick="toggleUserMenu()">
            <i class="ri-user-3-line"></i>
            <span class="user-name"><?php echo substr($_SESSION['full_name'], 0, 10); ?></span>
            <i class="ri-arrow-down-s-line"></i>
          </div>
          <div class="user-dropdown" id="userDropdown">
            <a href="profile.php"><i class="ri-user-line"></i> <?php echo $t['profile']; ?></a>
            <?php if (isAdmin()): ?>
              <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'karyawan'): ?>
                <a href="karyawan/dashboard.php"><i class="ri-dashboard-line"></i> Dashboard Karyawan</a>
              <?php else: ?>
                <a href="admin/dashboard.php"><i class="ri-dashboard-line"></i> Admin Dashboard</a>
              <?php endif; ?>
            <?php endif; ?>
            <a href="auth/logout.php"><i class="ri-logout-box-line"></i> <?php echo $t['logout']; ?></a>
          </div>
        </div>
      <?php else: ?>
        <button class="auth-btn" onclick="showLoginModal()">
          <i class="ri-user-3-line"></i>
          <span><?php echo $t['login']; ?></span>
        </button>
      <?php endif; ?>

      <i class="ri-heart-3-line" id="favorites-icon" onclick="window.location.href='favorites.php'" style="cursor: pointer;"></i>
      <div class="cart-container">
        <i class="ri-shopping-bag-line" id="cart-icon" onclick="window.location.href='cart.php'" style="cursor: pointer;"></i>
        <span class="cart-badge" id="cart-badge" style="display: none;">0</span>
      </div>
    </div>
  </nav>

  <?php if ($page === 'home'): ?>
    <!-- ===== HERO ===== -->
    <section class="hero">
      <div class="side-info">
        <div class="line"></div>
        <span>Developed By Kelompok 2</span>
      </div>

      <div class="content">
        <h5>We sell just</h5>
        <h1>Premium Parfume</h1>
        <h3>Viktor & Rolf Parfume Collection</h3>

        <div class="size">
          <img src="images/icon.png" class="parfume-icon" alt="" />
          <button class="active">50 ML</button>
          <button class="active">100 ML</button>
          <button class="active">150 ML</button>
        </div>

        <div class="hero-buttons">
          <button class="btn hero-add-to-cart" onclick="addToCart(1)">
            <i class="ri-shopping-cart-line"></i>
            ADD TO CART
          </button>
          <button class="btn-secondary hero-add-favorites" onclick="addToFavorites(1)">
            <i class="ri-heart-line"></i>
            <?php echo $t['add_to_favorites']; ?>
          </button>
        </div>

        <div class="footer-bar">
          <div class="footer-left">
            <span class="current">01</span>
            <span class="slash">/</span>
            <span class="total">15</span>
          </div>

          <div class="footer-right">
            <div class="navigation-dots">
              <span class="dot active"></span>
              <span class="dot"></span>
              <span class="dot"></span>
            </div>
          </div>
        </div>
      </div>

      <div class="header__image">
        <img src="images/perfume.png" class="main-img" alt="" />
        <img src="images/flower.png" class="bg-img" alt="" />
      </div>

      <div class="arrows">
        <button class="left"><i class="ri-arrow-left-line"></i></button>
        <button class="right"><i class="ri-arrow-right-line"></i></button>
      </div>
    </section>

    <!-- ===== PERFUME PRODUCTS SECTION ===== -->
    <section id="products" class="products-section">
      <div class="container">
        <div class="section-header">
          <h2><?php echo $t['our_collection']; ?></h2>
          <p><?php echo $t['discover_scent']; ?></p>
        </div>

        <div class="filter-bar">
          <div class="filter-group">
            <label for="type-filter"><?php echo $t['filter_by_type']; ?>:</label>
            <select id="type-filter">
              <option value=""><?php echo $t['all_types']; ?></option>
              <option value="Eau de Parfum">Eau de Parfum</option>
              <option value="Eau de Toilette">Eau de Toilette</option>
              <option value="Eau de Cologne">Eau de Cologne</option>
            </select>
          </div>

          <div class="filter-group">
            <label for="scent-filter"><?php echo $t['filter_by_scent']; ?>:</label>
            <select id="scent-filter">
              <option value=""><?php echo $t['all_scents']; ?></option>
              <option value="Floral">Floral</option>
              <option value="Citrus">Citrus</option>
              <option value="Woody">Woody</option>
              <option value="Marine">Marine</option>
              <option value="Gourmand">Gourmand</option>
              <option value="Spicy">Spicy</option>
            </select>
          </div>

          <div class="filter-group">
            <label for="sort-filter"><?php echo $t['sort_by']; ?>:</label>
            <select id="sort-filter">
              <option value="name"><?php echo $t['products']; ?></option>
              <option value="price-low"><?php echo $t['price_low_high']; ?></option>
              <option value="price-high"><?php echo $t['price_high_low']; ?></option>
            </select>
          </div>
        </div>

        <div class="perfume-grid" id="perfume-grid">
          <?php foreach ($products as $product): ?>
            <div class="perfume-card" data-type="<?php echo htmlspecialchars($product['type']); ?>" data-scent="<?php echo htmlspecialchars($product['scent']); ?>" data-price="<?php echo $product['price']; ?>" data-name="<?php echo htmlspecialchars($product['name']); ?>">
              <div class="card-image">
                <?php
                // Fix image path to ensure it points to the correct location
                $imagePath = $product['image'];
                if (!empty($imagePath) && $imagePath !== 'images/perfume.png') {
                  // If path starts with 'images/products/', use it as is
                  if (strpos($imagePath, 'images/products/') === 0) {
                    $displayPath = $imagePath;
                  } else {
                    // Otherwise assume it's just the filename
                    $displayPath = 'images/products/' . basename($imagePath);
                  }
                } else {
                  // Use default image
                  $displayPath = 'images/perfume.png';
                }
                ?>
                <img src="<?php echo htmlspecialchars($displayPath); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" onerror="this.src='images/perfume.png'">
                <div class="card-actions">
                  <button class="action-btn favorite-btn" data-id="<?php echo $product['id']; ?>">
                    <i class="ri-heart-line"></i>
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
                    <?php echo $t['add_to_cart']; ?>
                  </button>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <!-- ===== ABOUT SECTION ===== -->
    <section id="about" class="about-section">
      <div class="container">
        <div class="section-header">
          <h2><?php echo $t['about_title']; ?></h2>
        </div>
        <div class="about-content">
          <div class="about-text">
            <p><?php echo $t['about_description']; ?></p>
            <p>Kami menyediakan berbagai macam parfum dengan kualitas terbaik, mulai dari aroma floral yang lembut hingga woody yang maskulin. Setiap produk di koleksi kami dipilih dengan teliti untuk memastikan kualitas dan keaslian.</p>
            
            <!-- Vision & Mission -->
            <div class="about-features vision-mission-features">
              <div class="feature vm-feature">
                <i class="ri-eye-line"></i>
                <h4>Visi Kami</h4>
                <p>Menjadi destinasi utama parfum premium di Indonesia yang menghadirkan pengalaman wewangian eksklusif dan berkelas dunia</p>
              </div>
              <div class="feature vm-feature">
                <i class="ri-flag-line"></i>
                <h4>Misi Kami</h4>
                <p>Menyediakan parfum original, pelayanan excellent, dan membangun komunitas pecinta parfum yang solid</p>
              </div>
            </div>

            <div class="about-features">
              <div class="feature">
                <i class="ri-shield-check-line"></i>
                <h4>100% Original</h4>
                <p>Produk asli bergaransi</p>
              </div>
              <div class="feature">
                <i class="ri-truck-line"></i>
                <h4>Free Shipping</h4>
                <p>Gratis ongkir untuk pembelian tertentu</p>
              </div>
              <div class="feature">
                <i class="ri-customer-service-2-line"></i>
                <h4>24/7 Support</h4>
                <p>Layanan pelanggan setiap saat</p>
              </div>
            </div>
          </div>
          <div class="about-image">
            <img src="images/perfume.png" alt="About Parfumé Lux">
          </div>
        </div>
      </div>
    </section>

    <!-- ===== CONTACT SECTION ===== -->
    <section id="contact" class="contact-section">
      <div class="container">
        <div class="section-header">
          <h2><?php echo $t['contact_title']; ?></h2>
          <p><?php echo $t['contact_description']; ?></p>
        </div>
        <div class="contact-content">
          <div class="contact-info">
            <div class="contact-item">
              <i class="ri-map-pin-line"></i>
              <div>
                <h4>Alamat</h4>
                <p>Jl. Sudirman No. 123, Jakarta Pusat, 10110</p>
              </div>
            </div>
            <div class="contact-item">
              <i class="ri-phone-line"></i>
              <div>
                <h4>Telepon</h4>
                <p>+62 21 1234 5678</p>
              </div>
            </div>
            <div class="contact-item">
              <i class="ri-mail-line"></i>
              <div>
                <h4>Email</h4>
                <p>info@parfumelux.com</p>
              </div>
            </div>
            <div class="contact-item">
              <i class="ri-time-line"></i>
              <div>
                <h4>Jam Operasional</h4>
                <p>Senin - Sabtu: 09:00 - 21:00<br>Minggu: 10:00 - 19:00</p>
              </div>
            </div>
          </div>
          <div class="contact-form">
            <h3>Kirim Pesan</h3>
            <form>
              <div class="form-group">
                <input type="text" placeholder="Nama Lengkap" required>
              </div>
              <div class="form-group">
                <input type="email" placeholder="Email" required>
              </div>
              <div class="form-group">
                <input type="tel" placeholder="Nomor Telepon">
              </div>
              <div class="form-group">
                <textarea placeholder="Pesan Anda" rows="5" required></textarea>
              </div>
              <button type="submit" class="btn">Kirim Pesan</button>
            </form>
          </div>
        </div>
      </div>
    </section>
  <?php endif; ?>

  <!-- ===== FOOTER ===== -->
  <footer class="footer">
    <div class="container">
      <div class="footer-content">
        <div class="footer-section">
          <h3>Parfumé Lux</h3>
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
            <li><a href="#about"><?php echo $t['about']; ?></a></li>
            <li><a href="#products"><?php echo $t['products']; ?></a></li>
            <li><a href="#contact"><?php echo $t['contact']; ?></a></li>
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
            <li><i class="ri-mail-line"></i> info@parfumelux.com</li>
          </ul>
        </div>
      </div>

      <div class="footer-bottom">
        <p>&copy; 2024 Parfumé Lux. All rights reserved. | Developed by Kelompok 2</p>
      </div>
    </div>
  </footer>

  <!-- ===== MODALS ===== -->
  <?php if (!isLoggedIn()): ?>
    <!-- Login Modal -->
    <div class="modal" id="loginModal">
      <div class="modal-content">
        <div class="modal-header">
          <h3><?php echo $t['login']; ?></h3>
          <button class="close-btn" onclick="closeLoginModal()">&times;</button>
        </div>
        <div class="modal-body">
          <?php if (isset($_SESSION['login_errors'])): ?>
            <div class="error-messages">
              <?php foreach ($_SESSION['login_errors'] as $error): ?>
                <p><?php echo $error; ?></p>
              <?php endforeach; ?>
            </div>
            <?php unset($_SESSION['login_errors']); ?>
          <?php endif; ?>

          <form action="auth/login.php" method="POST">
            <div class="form-group">
              <label>Username atau Email</label>
              <input type="text" name="username" required>
            </div>
            <div class="form-group">
              <label>Password</label>
              <input type="password" name="password" required>
            </div>
            <button type="submit" class="btn"><?php echo $t['login']; ?></button>
          </form>

          <div class="auth-footer">
            <p>Belum punya akun? <a href="#" onclick="showRegisterModal()">Daftar sekarang</a></p>
          </div>
        </div>
      </div>
    </div>

    <!-- Register Modal -->
    <div class="modal" id="registerModal">
      <div class="modal-content">
        <div class="modal-header">
          <h3><?php echo $t['register']; ?></h3>
          <button class="close-btn" onclick="closeRegisterModal()">&times;</button>
        </div>
        <div class="modal-body">
          <?php if (isset($_SESSION['registration_errors'])): ?>
            <div class="error-messages">
              <?php foreach ($_SESSION['registration_errors'] as $error): ?>
                <p><?php echo $error; ?></p>
              <?php endforeach; ?>
            </div>
            <?php unset($_SESSION['registration_errors']); ?>
          <?php endif; ?>

          <?php if (isset($_SESSION['registration_success'])): ?>
            <div class="success-message">
              <?php
              echo $_SESSION['registration_success'];
              unset($_SESSION['registration_success']);
              ?>
            </div>
          <?php endif; ?>

          <form action="auth/register.php" method="POST">
            <div class="form-group">
              <label>Username</label>
              <input type="text" name="username" required
                value="<?php echo $_SESSION['form_data']['username'] ?? ''; ?>">
            </div>
            <div class="form-group">
              <label>Email</label>
              <input type="email" name="email" required
                value="<?php echo $_SESSION['form_data']['email'] ?? ''; ?>">
            </div>
            <div class="form-group">
              <label>Nama Lengkap</label>
              <input type="text" name="full_name" required
                value="<?php echo $_SESSION['form_data']['full_name'] ?? ''; ?>">
            </div>
            <div class="form-group">
              <label>Telepon</label>
              <input type="tel" name="phone"
                value="<?php echo $_SESSION['form_data']['phone'] ?? ''; ?>">
            </div>
            <div class="form-group">
              <label>Alamat</label>
              <textarea name="address" rows="3"><?php echo $_SESSION['form_data']['address'] ?? ''; ?></textarea>
            </div>
            <div class="form-group">
              <label>Password</label>
              <input type="password" name="password" required>
            </div>
            <div class="form-group">
              <label>Konfirmasi Password</label>
              <input type="password" name="confirm_password" required>
            </div>
            <button type="submit" class="btn"><?php echo $t['register']; ?></button>
          </form>

          <div class="auth-footer">
            <p>Sudah punya akun? <a href="#" onclick="showLoginModal()">Masuk sekarang</a></p>
          </div>
        </div>
      </div>
    </div>

    <!-- Login Required Modal -->
    <div class="modal" id="loginRequiredModal">
      <div class="modal-content">
        <div class="modal-header">
          <h3><?php echo $t['login_required']; ?></h3>
          <button class="close-btn" onclick="closeLoginRequiredModal()">&times;</button>
        </div>
        <div class="modal-body">
          <p><?php echo $t['must_login']; ?></p>
          <div class="modal-actions">
            <button class="btn" onclick="showLoginModal()"><?php echo $t['login']; ?></button>
            <button class="btn-secondary" onclick="showRegisterModal()"><?php echo $t['register']; ?></button>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <!-- Success/Error Messages -->
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

  <!-- ===== JAVASCRIPT ===== -->
  <script src="script.js"></script>
  <script>
    // Language variables
    const translations = <?php echo json_encode($translations[$lang]); ?>;

    // Modal functions
    function showLoginModal() {
      // Redirect to login page
      window.location.href = 'auth/login.php';
    }

    function closeLoginModal() {
      // Not needed since we redirect
    }

    function showRegisterModal() {
      // Redirect to register page
      window.location.href = 'auth/register.php';
    }

    function closeRegisterModal() {
      // Not needed since we redirect
    }

    function showLoginRequiredModal() {
      closeAllModals();
      document.getElementById('loginRequiredModal').style.display = 'flex';
    }

    function closeLoginRequiredModal() {
      document.getElementById('loginRequiredModal').style.display = 'none';
    }

    function closeAllModals() {
      const modals = document.querySelectorAll('.modal');
      modals.forEach(modal => modal.style.display = 'none');
    }

    // User menu toggle
    function toggleUserMenu() {
      const dropdown = document.getElementById('userDropdown');
      dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
    }

    // Close dropdown when clicking outside
    document.addEventListener('click', function(event) {
      const userMenu = document.querySelector('.user-menu');
      if (userMenu && !userMenu.contains(event.target)) {
        const dropdown = document.getElementById('userDropdown');
        if (dropdown) dropdown.style.display = 'none';
      }
    });

    // Hero section functions
    function addToCart(productId) {
      <?php if (isLoggedIn()): ?>
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
              updateCartBadge();
              showNotification(data.message, 'success');
            } else {
              showNotification('Failed to add to cart', 'error');
            }
          })
          .catch(error => {
            console.error('Error:', error);
            showNotification('Error adding to cart', 'error');
          });
      <?php else: ?>
        showLoginRequiredModal();
      <?php endif; ?>
    }

    function addToFavorites(productId) {
      <?php if (isLoggedIn()): ?>
        // AJAX add to favorites
        fetch('favorites.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded',
              'X-Requested-With': 'XMLHttpRequest'
            },
            body: `add_to_favorites=1&product_id=${productId}`
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              showNotification('Added to favorites!', 'success');
              // Update favorites icon or badge if exists
              updateFavoritesBadge();
            } else {
              showNotification('Failed to add to favorites', 'error');
            }
          })
          .catch(error => {
            console.error('Error:', error);
            showNotification('Error adding to favorites', 'error');
          });
      <?php else: ?>
        showLoginRequiredModal();
      <?php endif; ?>
    }

    function updateFavoritesBadge() {
      // Optional: Add badge update logic here if you have a favorites badge
      console.log('Favorites updated');
    }

    // Add event listeners for favorite buttons in product cards
    document.querySelectorAll('.favorite-btn').forEach(button => {
      button.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const productId = this.dataset.id;
        addToFavorites(productId);
      });
    });

    function updateCartBadge() {
      fetch('cart.php?get_count=1')
        .then(response => response.json())
        .then(data => {
          const badge = document.getElementById('cart-badge');
          if (data.count > 0) {
            badge.textContent = data.count;
            badge.style.display = 'inline-block';
          } else {
            badge.style.display = 'none';
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
          document.body.removeChild(notification);
        }, 300);
      }, 3000);
    }

    // Update cart badge on page load
    document.addEventListener('DOMContentLoaded', function() {
      updateCartBadge();
    });
  </script>
</body>

</html>