<?php
require_once '../config/database.php';

// Check if user is admin
if (!isAdmin()) {
  header('Location: ../index.php');
  exit;
}

// Get current user role
$currentUserRole = $_SESSION['user_role'];

// Add status column to products table if it doesn't exist
$statusCheck = $conn->query("SHOW COLUMNS FROM products LIKE 'status'");
if ($statusCheck->num_rows == 0) {
  $conn->query("ALTER TABLE products ADD COLUMN status ENUM('active', 'inactive') DEFAULT 'active'");
}

// Create additional tables for management
$scentsTableCheck = $conn->query("SHOW TABLES LIKE 'scents'");
if ($scentsTableCheck->num_rows == 0) {
  $conn->query("CREATE TABLE scents (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  )");

  // Insert default scents
  $defaultScents = [
    ['Floral', 'Aroma bunga-bungaan yang lembut dan romantis'],
    ['Citrus', 'Aroma segar dari jeruk dan buah sitrus lainnya'],
    ['Woody', 'Aroma kayu yang hangat dan maskulin'],
    ['Marine', 'Aroma laut yang segar dan menenangkan'],
    ['Gourmand', 'Aroma manis seperti vanilla dan karamel'],
    ['Spicy', 'Aroma rempah-rempah yang eksotis']
  ];

  foreach ($defaultScents as $scent) {
    $stmt = $conn->prepare("INSERT INTO scents (name, description) VALUES (?, ?)");
    $stmt->bind_param("ss", $scent[0], $scent[1]);
    $stmt->execute();
  }
}

$typesTableCheck = $conn->query("SHOW TABLES LIKE 'types'");
if ($typesTableCheck->num_rows == 0) {
  $conn->query("CREATE TABLE types (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  )");

  // Insert default types
  $defaultTypes = [
    ['Eau de Parfum', 'Formula konsentrasi tinggi, tahan lama'],
    ['Eau de Toilette', 'Formula konsentrasi sedang, segar'],
    ['Eau de Cologne', 'Formula konsentrasi rendah, sangat segar']
  ];

  foreach ($defaultTypes as $type) {
    $stmt = $conn->prepare("INSERT INTO types (name, description) VALUES (?, ?)");
    $stmt->bind_param("ss", $type[0], $type[1]);
    $stmt->execute();
  }
}

// Create stores table with branch_id
$storesTableCheck = $conn->query("SHOW TABLES LIKE 'stores'");
if ($storesTableCheck->num_rows == 0) {
  $conn->query("CREATE TABLE stores (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    branch_id VARCHAR(20) UNIQUE,
    name VARCHAR(100) NOT NULL,
    address TEXT NOT NULL,
    phone VARCHAR(20),
    manager_name VARCHAR(100),
    total_stock INT(11) DEFAULT 0,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  )");

  // Insert default store with branch_id
  $stmt = $conn->prepare("INSERT INTO stores (branch_id, name, address, phone, manager_name, total_stock) VALUES (?, ?, ?, ?, ?, ?)");
  $branchId = 'BR001';
  $name = 'Toko Pusat';
  $address = 'Jl. Sudirman No. 123, Jakarta Selatan';
  $phone = '021-1234567';
  $manager = 'Admin Pusat';
  $totalStock = 0;
  $stmt->bind_param("sssssi", $branchId, $name, $address, $phone, $manager, $totalStock);
  $stmt->execute();
} else {
  // Check if branch_id column exists, add if not
  $branchIdCheck = $conn->query("SHOW COLUMNS FROM stores LIKE 'branch_id'");
  if ($branchIdCheck->num_rows == 0) {
    $conn->query("ALTER TABLE stores ADD COLUMN branch_id VARCHAR(20) UNIQUE AFTER id");

    // Generate branch IDs for existing stores
    $existingStores = $conn->query("SELECT id FROM stores WHERE branch_id IS NULL ORDER BY id");
    while ($store = $existingStores->fetch_assoc()) {
      $branchId = sprintf('BR%03d', $store['id']);
      $updateStmt = $conn->prepare("UPDATE stores SET branch_id = ? WHERE id = ?");
      $updateStmt->bind_param("si", $branchId, $store['id']);
      $updateStmt->execute();
    }
  }
}

// Create product_branch table
$productBranchTableCheck = $conn->query("SHOW TABLES LIKE 'product_branch'");
if ($productBranchTableCheck->num_rows == 0) {
  $conn->query("CREATE TABLE product_branch (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    branch_id VARCHAR(20) NOT NULL,
    store_id INT(11) NOT NULL,
    product_id INT(11) NOT NULL,
    stock INT(11) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY unique_branch_product (branch_id, product_id),
    INDEX idx_branch_id (branch_id),
    INDEX idx_product_id (product_id)
  )");
}

// Get dashboard statistics
$totalProducts = $conn->query("SELECT COUNT(*) as count FROM products")->fetch_assoc()['count'];
$totalOrders = $conn->query("SELECT COUNT(*) as count FROM orders")->fetch_assoc()['count'];
$totalUsers = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'user'")->fetch_assoc()['count'];
$recentOrders = $conn->query("SELECT o.*, u.full_name FROM orders o JOIN users u ON o.user_id = u.id ORDER BY o.created_at DESC LIMIT 5");
$lowStockProducts = $conn->query("SELECT * FROM products WHERE stock < 20 ORDER BY stock ASC LIMIT 5");

// Calculate total revenue
$revenueResult = $conn->query("SELECT SUM(total_amount) as total FROM orders WHERE status != 'cancelled'");
$totalRevenue = $revenueResult->fetch_assoc()['total'] ?? 0;

// Handle AJAX request for order details
if (isset($_GET['action']) && $_GET['action'] === 'get_order_details') {
  // Prevent caching
  header('Cache-Control: no-cache, no-store, must-revalidate');
  header('Pragma: no-cache');
  header('Expires: 0');
  header('Content-Type: application/json');
  
  $orderId = $_GET['order_id'] ?? 0;

  $orderQuery = $conn->prepare("SELECT o.*, u.full_name, u.email, u.phone FROM orders o JOIN users u ON o.user_id = u.id WHERE o.id = ?");
  $orderQuery->bind_param("i", $orderId);
  $orderQuery->execute();
  $order = $orderQuery->get_result()->fetch_assoc();

  if ($order) {
    // Use LEFT JOIN to include items without product_id (like bubble wrap, wooden packing)
    $itemsQuery = $conn->prepare("SELECT oi.*, COALESCE(p.name, oi.product_name) as product_name, oi.product_price as price, oi.quantity, oi.subtotal FROM order_items oi LEFT JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
    $itemsQuery->bind_param("i", $orderId);
    $itemsQuery->execute();
    $items = $itemsQuery->get_result()->fetch_all(MYSQLI_ASSOC);

    echo json_encode(['success' => true, 'order' => $order, 'items' => $items]);
  } else {
    echo json_encode(['success' => false]);
  }
  exit;
}

// Handle AJAX request for user details
if (isset($_GET['action']) && $_GET['action'] === 'get_user_details') {
  $userId = $_GET['user_id'] ?? 0;

  $userQuery = $conn->prepare("SELECT * FROM users WHERE id = ?");
  $userQuery->bind_param("i", $userId);
  $userQuery->execute();
  $user = $userQuery->get_result()->fetch_assoc();

  if ($user) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'user' => $user]);
  } else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false]);
  }
  exit;
}

// Handle AJAX request for store details
if (isset($_GET['action']) && $_GET['action'] === 'get_store_details') {
  $storeId = $_GET['store_id'] ?? 0;

  $storeQuery = $conn->prepare("SELECT * FROM stores WHERE id = ?");
  $storeQuery->bind_param("i", $storeId);
  $storeQuery->execute();
  $store = $storeQuery->get_result()->fetch_assoc();

  if ($store) {
    // Get product inventory for this branch
    $inventoryQuery = $conn->prepare("SELECT product_id, stock FROM product_branch WHERE branch_id = ?");
    $inventoryQuery->bind_param("s", $store['branch_id']);
    $inventoryQuery->execute();
    $inventoryResult = $inventoryQuery->get_result();

    $inventory = [];
    while ($item = $inventoryResult->fetch_assoc()) {
      $inventory[$item['product_id']] = $item['stock'];
    }

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'store' => $store, 'inventory' => $inventory]);
  } else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false]);
  }
  exit;
}

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Add new product
  if (isset($_POST['add_product']) && $currentUserRole === 'superadmin') {
    $name = $_POST['name'] ?? '';
    $type = $_POST['type'] ?? '';
    $scent = $_POST['scent'] ?? '';
    $price = $_POST['price'] ?? 0;
    $description = $_POST['description'] ?? '';
    $image = $_POST['image'] ?? 'images/perfume.png';
    $stock = $_POST['stock'] ?? 0;

    $stmt = $conn->prepare("INSERT INTO products (name, type, scent, price, description, image, stock) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssdssi", $name, $type, $scent, $price, $description, $image, $stock);

    if ($stmt->execute()) {
      $_SESSION['success_message'] = 'Produk berhasil ditambahkan!';
      header("Location: dashboard.php#products");
      exit;
    } else {
      $_SESSION['error_message'] = 'Gagal menambahkan produk.';
      header("Location: dashboard.php#products");
      exit;
    }
  }

  // Update product
  if (isset($_POST['update_product'])) {
    $id = $_POST['id'] ?? 0;
    $name = $_POST['name'] ?? '';
    $price = $_POST['price'] ?? 0;
    $description = $_POST['description'] ?? '';
    $image = $_POST['image'] ?? 'images/perfume.png';
    $stock = $_POST['stock'] ?? 0;

    // Only superadmin can update type and scent
    if ($currentUserRole === 'superadmin') {
      $type = $_POST['type'] ?? '';
      $scent = $_POST['scent'] ?? '';
      $stmt = $conn->prepare("UPDATE products SET name = ?, type = ?, scent = ?, price = ?, description = ?, image = ?, stock = ? WHERE id = ?");
      $stmt->bind_param("sssdssii", $name, $type, $scent, $price, $description, $image, $stock, $id);
    } else {
      // Admin can only update stock
      $stmt = $conn->prepare("UPDATE products SET stock = ? WHERE id = ?");
      $stmt->bind_param("ii", $stock, $id);
    }

    if ($stmt->execute()) {
      $_SESSION['success_message'] = 'Produk berhasil diperbarui!';
    } else {
      $_SESSION['error_message'] = 'Gagal memperbarui produk.';
    }
    header("Location: dashboard.php#products");
    exit;
  }

  // Delete product (only superadmin)
  if (isset($_POST['delete_product']) && $currentUserRole === 'superadmin') {
    $id = $_POST['id'] ?? 0;

    $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
      $_SESSION['success_message'] = 'Produk berhasil dihapus!';
    } else {
      $_SESSION['error_message'] = 'Gagal menghapus produk.';
    }
    header("Location: dashboard.php#products");
    exit;
  }

  // Update order status
  if (isset($_POST['update_order_status'])) {
    $id = $_POST['id'] ?? 0;
    $orderStatus = $_POST['order_status'] ?? 'pending';

    $stmt = $conn->prepare("UPDATE orders SET order_status = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("si", $orderStatus, $id);

    // Check if this is an AJAX request
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    if ($stmt->execute()) {
      if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Status berhasil diperbarui', 'order_status' => $orderStatus]);
        exit;
      }
      $_SESSION['success_message'] = 'Status pesanan berhasil diperbarui!';
    } else {
      if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Gagal memperbarui status']);
        exit;
      }
      $_SESSION['error_message'] = 'Gagal memperbarui status pesanan.';
    }
    header("Location: dashboard.php#orders");
    exit;
  }

  // Toggle product status
  if (isset($_POST['toggle_product_status'])) {
    $id = $_POST['id'] ?? 0;
    $status = $_POST['status'] ?? 'active';

    $stmt = $conn->prepare("UPDATE products SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $id);

    if ($stmt->execute()) {
      $_SESSION['success_message'] = 'Status produk berhasil diperbarui!';
    } else {
      $_SESSION['error_message'] = 'Gagal memperbarui status produk.';
    }
    header("Location: dashboard.php#products");
    exit;
  }

  // Add scent (only superadmin)
  if (isset($_POST['add_scent']) && $currentUserRole === 'superadmin') {
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';

    $stmt = $conn->prepare("INSERT INTO scents (name, description) VALUES (?, ?)");
    $stmt->bind_param("ss", $name, $description);

    if ($stmt->execute()) {
      $_SESSION['success_message'] = 'Aroma berhasil ditambahkan!';
    } else {
      $_SESSION['error_message'] = 'Gagal menambahkan aroma.';
    }
    header("Location: dashboard.php#scents");
    exit;
  }

  // Add type (only superadmin)
  if (isset($_POST['add_type']) && $currentUserRole === 'superadmin') {
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';

    $stmt = $conn->prepare("INSERT INTO types (name, description) VALUES (?, ?)");
    $stmt->bind_param("ss", $name, $description);

    if ($stmt->execute()) {
      $_SESSION['success_message'] = 'Tipe berhasil ditambahkan!';
    } else {
      $_SESSION['error_message'] = 'Gagal menambahkan tipe.';
    }
    header("Location: dashboard.php#types");
    exit;
  }

  // Add user (only superadmin)
  if (isset($_POST['add_user']) && $currentUserRole === 'superadmin') {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $full_name = $_POST['full_name'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $address = $_POST['address'] ?? '';
    $role = $_POST['role'] ?? 'user';

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (username, email, password, full_name, phone, address, role) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssss", $username, $email, $hashedPassword, $full_name, $phone, $address, $role);

    if ($stmt->execute()) {
      $_SESSION['success_message'] = 'Pengguna berhasil ditambahkan!';
    } else {
      $_SESSION['error_message'] = 'Gagal menambahkan pengguna.';
    }
    header("Location: dashboard.php#users");
    exit;
  }

  // Update user (only superadmin)
  if (isset($_POST['update_user']) && $currentUserRole === 'superadmin') {
    $id = $_POST['id'] ?? 0;
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $full_name = $_POST['full_name'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $address = $_POST['address'] ?? '';
    $role = $_POST['role'] ?? 'user';
    $status = $_POST['status'] ?? 'active';

    $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, full_name = ?, phone = ?, address = ?, role = ?, status = ? WHERE id = ?");
    $stmt->bind_param("sssssssi", $username, $email, $full_name, $phone, $address, $role, $status, $id);

    if ($stmt->execute()) {
      $_SESSION['success_message'] = 'Pengguna berhasil diperbarui!';
    } else {
      $_SESSION['error_message'] = 'Gagal memperbarui pengguna.';
    }
    header("Location: dashboard.php#users");
    exit;
  }

  // Delete user (only superadmin)
  if (isset($_POST['delete_user']) && $currentUserRole === 'superadmin') {
    $id = $_POST['id'] ?? 0;

    $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role NOT IN ('admin', 'superadmin')");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
      $_SESSION['success_message'] = 'Pengguna berhasil dihapus!';
    } else {
      $_SESSION['error_message'] = 'Gagal menghapus pengguna.';
    }
    header("Location: dashboard.php#users");
    exit;
  }

  // Add store (only superadmin)
  if (isset($_POST['add_store']) && ($currentUserRole === 'superadmin' || $currentUserRole === 'admin')) {
    $name = $_POST['name'] ?? '';
    $address = $_POST['address'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $manager_name = $_POST['manager_name'] ?? '';
    $product_stock = $_POST['product_stock'] ?? [];

    // Insert store without total_stock
    $stmt = $conn->prepare("INSERT INTO stores (name, address, phone, manager_name) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $name, $address, $phone, $manager_name);

    if ($stmt->execute()) {
      $newStoreId = $conn->insert_id;

      // Get the branch_id for this store
      $branchQuery = $conn->prepare("SELECT branch_id FROM stores WHERE id = ?");
      $branchQuery->bind_param("i", $newStoreId);
      $branchQuery->execute();
      $branchId = $branchQuery->get_result()->fetch_assoc()['branch_id'];

      // If no branch_id exists, generate one
      if (!$branchId) {
        $branchId = sprintf('BR%03d', $newStoreId);
        $updateBranch = $conn->prepare("UPDATE stores SET branch_id = ? WHERE id = ?");
        $updateBranch->bind_param("si", $branchId, $newStoreId);
        $updateBranch->execute();
      }

      // Insert product inventory
      foreach ($product_stock as $productId => $stock) {
        if ($stock > 0) {
          $invStmt = $conn->prepare("INSERT INTO product_branch (branch_id, store_id, product_id, stock) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE stock = ?");
          $invStmt->bind_param("siiii", $branchId, $newStoreId, $productId, $stock, $stock);
          $invStmt->execute();
        }
      }

      $_SESSION['success_message'] = 'Toko berhasil ditambahkan!';
    } else {
      $_SESSION['error_message'] = 'Gagal menambahkan toko.';
    }
    header("Location: dashboard.php#stores");
    exit;
  }

  // Update store
  if (isset($_POST['update_store'])) {
    $id = $_POST['id'] ?? 0;
    $name = $_POST['name'] ?? '';
    $address = $_POST['address'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $manager_name = $_POST['manager_name'] ?? '';
    $product_stock = $_POST['product_stock'] ?? [];

    // Check permission: Superadmin or Manager of the store
    $canEdit = false;
    if ($currentUserRole === 'superadmin' || $currentUserRole === 'admin') {
      $canEdit = true;
    } else if ($currentUserRole === 'karyawan') {
      // Check if user is the manager
      $checkStmt = $conn->prepare("SELECT manager_name, branch_id FROM stores WHERE id = ?");
      $checkStmt->bind_param("i", $id);
      $checkStmt->execute();
      $storeData = $checkStmt->get_result()->fetch_assoc();
      if ($storeData && $storeData['manager_name'] === $_SESSION['full_name']) {
        $canEdit = true;
      }
    }

    if ($canEdit) {
      // Update store info without total_stock
      $stmt = $conn->prepare("UPDATE stores SET name = ?, address = ?, phone = ?, manager_name = ? WHERE id = ?");
      $stmt->bind_param("ssssi", $name, $address, $phone, $manager_name, $id);

      if ($stmt->execute()) {
        // Get branch_id
        $branchQuery = $conn->prepare("SELECT branch_id FROM stores WHERE id = ?");
        $branchQuery->bind_param("i", $id);
        $branchQuery->execute();
        $branchId = $branchQuery->get_result()->fetch_assoc()['branch_id'];

        // Update product inventory
        // First, delete existing inventory for this branch
        $deleteStmt = $conn->prepare("DELETE FROM product_branch WHERE branch_id = ?");
        $deleteStmt->bind_param("s", $branchId);
        $deleteStmt->execute();

        // Then insert new inventory
        foreach ($product_stock as $productId => $stock) {
          if ($stock > 0) {
            $invStmt = $conn->prepare("INSERT INTO product_branch (branch_id, store_id, product_id, stock) VALUES (?, ?, ?, ?)");
            $invStmt->bind_param("siii", $branchId, $id, $productId, $stock);
            $invStmt->execute();
          }
        }

        $_SESSION['success_message'] = 'Toko berhasil diperbarui!';
      } else {
        $_SESSION['error_message'] = 'Gagal memperbarui toko.';
      }
    } else {
      $_SESSION['error_message'] = 'Anda tidak memiliki izin untuk mengedit toko ini.';
    }
    header("Location: dashboard.php#stores");
    exit;
  }

  // Delete store (only superadmin)
  if (isset($_POST['delete_store']) && $currentUserRole === 'superadmin') {
    $id = $_POST['id'] ?? 0;

    $stmt = $conn->prepare("DELETE FROM stores WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
      $_SESSION['success_message'] = 'Toko berhasil dihapus!';
    } else {
      $_SESSION['error_message'] = 'Gagal menghapus toko.';
    }
    header("Location: dashboard.php#stores");
    exit;
  }
}

// Get all products for management
$products = $conn->query("SELECT * FROM products ORDER BY name");
$orders = $conn->query("SELECT o.*, u.full_name FROM orders o JOIN users u ON o.user_id = u.id ORDER BY o.created_at DESC LIMIT 50");
$users = $conn->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 50");
$scents = $conn->query("SELECT * FROM scents ORDER BY name");
$types = $conn->query("SELECT * FROM types ORDER BY name");
$stores = $conn->query("SELECT * FROM stores ORDER BY name");

// Calculate Central Store Stock
$centralStockResult = $conn->query("SELECT SUM(stock) as total FROM products");
$centralStock = $centralStockResult->fetch_assoc()['total'] ?? 0;

// Fetch eligible managers (Admin & Karyawan)
$managers = $conn->query("SELECT full_name, email, role FROM users WHERE role IN ('admin', 'karyawan') ORDER BY full_name");
?>

<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard Admin - Parfumé Lux</title>
  <link rel="stylesheet" href="admin.css">
  <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>

<body>
  <script>
    // Pass current user role and name to JavaScript
    const CURRENT_USER_ROLE = '<?php echo $currentUserRole; ?>';
    const CURRENT_USER_NAME = '<?php echo $_SESSION['full_name'] ?? ''; ?>';
  </script>

  <!-- Admin Sidebar -->
  <div class="admin-sidebar">
    <div class="sidebar-header">
      <h2>Parfumé Lux</h2>
      <p>Panel Admin</p>
    </div>

    <nav class="sidebar-nav">
      <a href="#dashboard" class="nav-item active">
        <i class="ri-dashboard-line"></i>
        <span>Dashboard</span>
      </a>
      <a href="#products" class="nav-item">
        <i class="ri-store-2-line"></i>
        <span>Produk</span>
      </a>
      <a href="#orders" class="nav-item">
        <i class="ri-shopping-cart-line"></i>
        <span>Pesanan</span>
      </a>

      <?php if ($currentUserRole === 'superadmin'): ?>
        <a href="#scents" class="nav-item">
          <i class="ri-flower-line"></i>
          <span>Aroma</span>
        </a>
        <a href="#types" class="nav-item">
          <i class="ri-apps-line"></i>
          <span>Tipe</span>
        </a>
        <a href="#users" class="nav-item">
          <i class="ri-user-line"></i>
          <span>Pengguna</span>
        </a>
      <?php endif; ?>

      <a href="#stores" class="nav-item">
        <i class="ri-store-3-line"></i>
        <span>Toko Cabang</span>
      </a>
      
      <a href="../index.php" class="nav-item" style="margin-top: 10px; background: linear-gradient(135deg, #f5cdcd 0%, #cc7f7f 100%); color: white;">
        <i class="ri-arrow-left-line"></i>
        <span>Kembali ke Toko</span>
      </a>
    </nav>

    <div class="sidebar-footer">
      <div class="admin-user">
        <img src="../images/icon.png" alt="Admin" class="admin-avatar">
        <div class="admin-info">
          <span class="admin-name"><?php echo $_SESSION['full_name']; ?></span>
          <span class="admin-role"><?php
                                    if ($currentUserRole === 'superadmin') {
                                      echo 'Super Admin';
                                    } elseif ($currentUserRole === 'admin') {
                                      echo 'Administrator';
                                    } elseif ($currentUserRole === 'karyawan') {
                                      echo 'Karyawan';
                                    }
                                    ?></span>
        </div>
      </div>
      <a href="../auth/logout.php" class="logout-btn">
        <i class="ri-logout-box-line"></i>
        Keluar
      </a>
    </div>
  </div>

  <!-- Main Content -->
  <div class="admin-main">
    <!-- Header -->
    <header class="admin-header">
      <h1 class="page-title">Dashboard</h1>
    </header>

    <!-- Success/Error Messages -->
    <?php if (isset($_SESSION['success_message'])): ?>
      <div class="alert success">
        <?php
        echo $_SESSION['success_message'];
        unset($_SESSION['success_message']);
        ?>
      </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
      <div class="alert error">
        <?php
        echo $_SESSION['error_message'];
        unset($_SESSION['error_message']);
        ?>
      </div>
    <?php endif; ?>

    <!-- Dashboard Section -->
    <section id="dashboard-section" class="content-section active">
      <!-- Statistics Cards -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-icon">
            <i class="ri-store-2-line"></i>
          </div>
          <div class="stat-content">
            <h3><?php echo $totalProducts; ?></h3>
            <p>Total Produk</p>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-icon">
            <i class="ri-shopping-bag-line"></i>
          </div>
          <div class="stat-content">
            <h3><?php echo $totalOrders; ?></h3>
            <p>Total Pesanan</p>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-icon">
            <i class="ri-user-3-line"></i>
          </div>
          <div class="stat-content">
            <h3><?php echo $totalUsers; ?></h3>
            <p>Total Pelanggan</p>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-icon">
            <i class="ri-money-dollar-circle-line"></i>
          </div>
          <div class="stat-content">
            <h3><?php echo formatRupiah($totalRevenue); ?></h3>
            <p>Total Pendapatan</p>
          </div>
        </div>
      </div>

      <!-- Recent Activity -->
      <div class="activity-grid">
        <div class="activity-card">
          <h3>Pesanan Terbaru</h3>
          <div class="activity-list">
            <?php while ($order = $recentOrders->fetch_assoc()): ?>
              <div class="activity-item">
                <div class="activity-info">
                  <span class="activity-title"><?php echo htmlspecialchars($order['full_name']); ?></span>
                  <span class="activity-desc">Pesanan #<?php echo $order['order_number']; ?></span>
                </div>
                <div class="activity-meta">
                  <span class="activity-amount"><?php echo formatRupiah($order['total_amount']); ?></span>
                  <span class="activity-date"><?php echo date('d M Y', strtotime($order['created_at'])); ?></span>
                </div>
              </div>
            <?php endwhile; ?>
          </div>
        </div>

        <div class="activity-card">
          <h3>Peringatan Stok Rendah</h3>
          <div class="activity-list">
            <?php while ($product = $lowStockProducts->fetch_assoc()): ?>
              <div class="activity-item warning">
                <div class="activity-info">
                  <span class="activity-title"><?php echo htmlspecialchars($product['name']); ?></span>
                  <span class="activity-desc">Stok: <?php echo $product['stock']; ?></span>
                </div>
                <div class="activity-meta">
                  <i class="ri-alert-line"></i>
                  <span class="activity-date">Stok Rendah</span>
                </div>
              </div>
            <?php endwhile; ?>
          </div>
        </div>
      </div>
    </section>

    <!-- Products Section -->
    <section id="products-section" class="content-section">
      <div class="section-header">
        <h2>Manajemen Produk</h2>
        <div class="section-actions">
          <div class="search-box">
            <input type="text" id="product-search" placeholder="Cari produk...">
            <i class="ri-search-line"></i>
          </div>
          <?php if ($currentUserRole === 'superadmin'): ?>
            <button class="btn-primary" onclick="showAddProductModal()">
              <i class="ri-add-line"></i>
              Tambah Produk
            </button>
          <?php endif; ?>
        </div>
      </div>

      <div class="products-table-container">
        <table class="data-table">
          <thead>
            <tr>
              <th>Nama</th>
              <th>Tipe</th>
              <th>Aroma</th>
              <th>Harga</th>
              <th>Gambar</th>
              <th>Status</th>
              <th>Stok</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody id="products-tbody">
            <?php while ($product = $products->fetch_assoc()): ?>
              <tr data-product='<?php echo json_encode($product); ?>'>
                <td><?php echo htmlspecialchars($product['name']); ?></td>
                <td><?php echo htmlspecialchars($product['type']); ?></td>
                <td><?php echo htmlspecialchars($product['scent']); ?></td>
                <td><?php echo formatRupiah($product['price']); ?></td>
                <td>
                  <?php
                  // Fix image path to ensure it points to the correct location
                  $imagePath = $product['image'];
                  if (!empty($imagePath) && $imagePath !== 'images/perfume.png') {
                    // If path starts with 'images/products/', use it as is
                    if (strpos($imagePath, 'images/products/') === 0) {
                      $displayPath = '../' . $imagePath;
                    } else {
                      // Otherwise assume it's just the filename
                      $displayPath = '../images/products/' . basename($imagePath);
                    }
                  } else {
                    // Use default image
                    $displayPath = '../images/perfume.png';
                  }
                  ?>
                  <img src="<?php echo htmlspecialchars($displayPath); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-thumb" onerror="this.src='../images/perfume.png'">
                </td>
                <td>
                  <label class="toggle-switch">
                    <input type="checkbox" <?php echo ($product['status'] ?? 'active') === 'active' ? 'checked' : ''; ?>
                      onchange="toggleProductStatus(<?php echo $product['id']; ?>, this.checked)">
                    <span class="toggle-slider"></span>
                  </label>
                </td>
                <td>
                  <span class="stock-badge <?php echo $product['stock'] < 20 ? 'low-stock' : ''; ?>">
                    <?php echo $product['stock']; ?>
                  </span>
                </td>
                <td>
                  <div class="action-buttons">
                    <button class="btn-edit" onclick="editProduct(this)">
                      <i class="ri-edit-line"></i>
                    </button>
                    <?php if ($currentUserRole === 'superadmin'): ?>
                      <button class="btn-delete" onclick="deleteProduct(<?php echo $product['id']; ?>)">
                        <i class="ri-delete-bin-line"></i>
                      </button>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Orders Section -->
    <section id="orders-section" class="content-section">
      <div class="section-header">
        <h2>Manajemen Pesanan</h2>
        <div class="section-actions">
          <button class="btn-secondary" onclick="exportOrders()">
            <i class="ri-download-line"></i>
            Export Pesanan
          </button>
          <button class="btn-primary" onclick="refreshOrders()">
            <i class="ri-refresh-line"></i>
            Refresh
          </button>
        </div>
      </div>

      <div class="orders-table-container">
        <table class="data-table">
          <thead>
            <tr>
              <th>Pesanan #</th>
              <th>Pelanggan</th>
              <th>Tanggal</th>
              <th>Jumlah</th>
              <th>Status</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $orders->data_seek(0); // Reset pointer
            while ($order = $orders->fetch_assoc()):
            ?>
              <tr>
                <td>#<?php echo htmlspecialchars($order['order_number']); ?></td>
                <td><?php echo htmlspecialchars($order['full_name']); ?></td>
                <td><?php echo date('d M Y', strtotime($order['created_at'])); ?></td>
                <td><?php echo formatRupiah($order['total_amount']); ?></td>
                <td>
                  <select class="status-select status-<?php echo $order['order_status']; ?>" data-order-id="<?php echo $order['id']; ?>">
                    <option value="pending" <?php echo $order['order_status'] === 'pending' ? 'selected' : ''; ?>>Menunggu</option>
                    <option value="processing" <?php echo $order['order_status'] === 'processing' ? 'selected' : ''; ?>>Diproses</option>
                    <option value="shipped" <?php echo $order['order_status'] === 'shipped' ? 'selected' : ''; ?>>Dikirim</option>
                    <option value="delivered" <?php echo $order['order_status'] === 'delivered' ? 'selected' : ''; ?>>Terkirim</option>
                    <option value="cancelled" <?php echo $order['order_status'] === 'cancelled' ? 'selected' : ''; ?>>Dibatalkan</option>
                  </select>
                </td>
                <td>
                  <button class="btn-view" onclick="viewOrderDetails(<?php echo $order['id']; ?>)">
                    <i class="ri-eye-line"></i>
                  </button>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </section>

    <?php if ($currentUserRole === 'superadmin'): ?>
      <!-- Users Section -->
      <section id="users-section" class="content-section">
        <div class="section-header">
          <h2>Manajemen Pengguna</h2>
          <div class="section-actions">
            <button class="btn-secondary" onclick="showAddUserModal()">
              <i class="ri-user-add-line"></i>
              Tambah Pengguna
            </button>
            <button class="btn-primary" onclick="exportUsers()">
              <i class="ri-download-line"></i>
              Export Pengguna
            </button>
          </div>
        </div>

        <div class="users-table-container">
          <table class="data-table">
            <thead>
              <tr>
                <th>Nama</th>
                <th>Email</th>
                <th>Telepon</th>
                <th>Role</th>
                <th>Terdaftar</th>
                <th>Status</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $users->data_seek(0); // Reset pointer
              while ($user = $users->fetch_assoc()):
              ?>
                <tr>
                  <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                  <td><?php echo htmlspecialchars($user['email']); ?></td>
                  <td><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></td>
                  <td><span class="role-badge role-<?php echo $user['role']; ?>"><?php echo ucfirst($user['role']); ?></span></td>
                  <td><?php echo date('d M Y', strtotime($user['created_at'])); ?></td>
                  <td>
                    <span class="status-badge <?php echo ($user['status'] ?? 'active') === 'active' ? 'active' : 'inactive'; ?>">
                      <?php echo ($user['status'] ?? 'active') === 'active' ? 'Aktif' : 'Tidak Aktif'; ?>
                    </span>
                  </td>
                  <td>
                    <div class="action-buttons">
                      <button class="btn-edit" onclick="editUser(<?php echo $user['id']; ?>)">
                        <i class="ri-edit-line"></i>
                      </button>
                      <?php if ($user['role'] !== 'admin' && $user['role'] !== 'superadmin'): ?>
                        <button class="btn-delete" onclick="deleteUser(<?php echo $user['id']; ?>)">
                          <i class="ri-delete-bin-line"></i>
                        </button>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </section>

      <!-- Scents Section -->
      <section id="scents-section" class="content-section">
        <div class="section-header">
          <h2>Manajemen Aroma</h2>
          <div class="section-actions">
            <button class="btn-primary" onclick="showAddScentModal()">
              <i class="ri-add-line"></i>
              Tambah Aroma
            </button>
          </div>
        </div>

        <div class="scents-grid">
          <?php
          $scents->data_seek(0); // Reset pointer
          while ($scent = $scents->fetch_assoc()):
          ?>
            <div class="scent-card">
              <div class="scent-icon">
                <i class="ri-flower-line"></i>
              </div>
              <h3><?php echo htmlspecialchars($scent['name']); ?></h3>
              <p><?php echo htmlspecialchars($scent['description']); ?></p>
            </div>
          <?php endwhile; ?>
        </div>
      </section>

      <!-- Types Section -->
      <section id="types-section" class="content-section">
        <div class="section-header">
          <h2>Manajemen Tipe</h2>
          <div class="section-actions">
            <button class="btn-primary" onclick="showAddTypeModal()">
              <i class="ri-add-line"></i>
              Tambah Tipe
            </button>
          </div>
        </div>

        <div class="types-grid">
          <?php
          $types->data_seek(0); // Reset pointer
          while ($type = $types->fetch_assoc()):
          ?>
            <div class="type-card">
              <div class="type-icon">
                <i class="ri-vial-line"></i>
              </div>
              <h3><?php echo htmlspecialchars($type['name']); ?></h3>
              <p><?php echo htmlspecialchars($type['description']); ?></p>
            </div>
          <?php endwhile; ?>
        </div>
      </section>
    <?php endif; ?>

    <!-- Stores Section -->
    <section id="stores-section" class="content-section">
      <div class="section-header">
        <h2>Manajemen Toko Cabang</h2>
        <div class="section-actions">
          <?php if ($currentUserRole === 'superadmin'): ?>
            <button class="btn-primary" onclick="showAddStoreModal()">
              <i class="ri-add-line"></i>
              Tambah Toko
            </button>
          <?php endif; ?>
        </div>
      </div>

      <div class="stores-table-container">
        <table class="data-table">
          <thead>
            <tr>
              <th>Nama Toko</th>
              <th>Alamat</th>
              <th>Telepon</th>
              <th>Manager</th>
              <th>Stok Total</th>
              <th>Status</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $stores->data_seek(0); // Reset pointer
            while ($store = $stores->fetch_assoc()):
            ?>
              <tr>
                <td><?php echo htmlspecialchars($store['name']); ?></td>
                <td><?php echo htmlspecialchars($store['address']); ?></td>
                <td><?php echo htmlspecialchars($store['phone']); ?></td>
                <td><?php echo htmlspecialchars($store['manager_name']); ?></td>
                <?php
                // Calculate total stock based on store type
                if ($store['name'] === 'Toko Pusat') {
                  // For central store, get total from products table
                  $centralStockQuery = $conn->query("SELECT SUM(stock) as total FROM products");
                  $totalStock = $centralStockQuery->fetch_assoc()['total'] ?? 0;
                } else {
                  // For branches, get total from product_branch table
                  $branchStockQuery = $conn->prepare("SELECT SUM(stock) as total FROM product_branch WHERE branch_id = ?");
                  $branchStockQuery->bind_param("s", $store['branch_id']);
                  $branchStockQuery->execute();
                  $totalStock = $branchStockQuery->get_result()->fetch_assoc()['total'] ?? 0;
                }
                ?>
                <td><?php echo number_format($totalStock, 0, ',', '.'); ?></td>
                <td><span class="status-badge <?php echo $store['status'] === 'active' ? 'active' : 'inactive'; ?>"><?php echo $store['status'] === 'active' ? 'Aktif' : 'Tidak Aktif'; ?></span></td>
                <td>
                  <div class="action-buttons">
                    <button class="btn-edit" onclick="editStore(<?php echo $store['id']; ?>)">
                      <i class="ri-edit-line"></i>
                    </button>
                  </div>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </section>
  </div>

  <!-- Product Modal -->
  <div id="productModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h3 id="modalTitle">Tambah Produk Baru</h3>
        <button class="modal-close" onclick="closeProductModal()">&times;</button>
      </div>
      <form id="productForm" method="POST">
        <div class="modal-body">
          <input type="hidden" id="productId" name="id">

          <div class="form-group">
            <label for="name">Nama Produk</label>
            <input type="text" id="name" name="name" required>
          </div>

          <?php if ($currentUserRole === 'superadmin'): ?>
            <div class="form-row">
              <div class="form-group">
                <label for="type">Tipe</label>
                <select id="type" name="type" required>
                  <?php
                  $types->data_seek(0);
                  while ($type = $types->fetch_assoc()):
                  ?>
                    <option value="<?php echo $type['name']; ?>"><?php echo htmlspecialchars($type['name']); ?></option>
                  <?php endwhile; ?>
                </select>
              </div>

              <div class="form-group">
                <label for="scent">Aroma</label>
                <select id="scent" name="scent" required>
                  <?php
                  $scents->data_seek(0);
                  while ($scent = $scents->fetch_assoc()):
                  ?>
                    <option value="<?php echo $scent['name']; ?>"><?php echo htmlspecialchars($scent['name']); ?></option>
                  <?php endwhile; ?>
                </select>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label for="price">Harga (IDR)</label>
                <input type="number" id="price" name="price" min="0" required>
              </div>

              <div class="form-group">
                <label for="stock">Stok</label>
                <input type="number" id="stock" name="stock" min="0" value="0" required>
              </div>
            </div>

            <div class="form-group">
              <label for="description">Deskripsi</label>
              <textarea id="description" name="description" rows="4" required></textarea>
            </div>
          <?php else: ?>
            <!-- Admin only fields -->
            <div class="form-row">
              <div class="form-group">
                <label>Tipe</label>
                <input type="text" id="type_readonly" readonly>
              </div>

              <div class="form-group">
                <label>Aroma</label>
                <input type="text" id="scent_readonly" readonly>
              </div>
            </div>

            <div class="form-group">
              <label for="stock">Stok</label>
              <input type="number" id="stock" name="stock" min="0" required>
            </div>
          <?php endif; ?>

          <div class="form-group">
            <label for="imageUploadTrigger">Upload Foto Produk dari Perangkat</label>
            <div class="image-upload-wrapper <?php echo $currentUserRole !== 'superadmin' ? 'is-disabled' : ''; ?>">
              <input type="hidden" id="imagePath" name="image" value="images/perfume.png">
              <input type="file" id="imageUpload" accept="image/*" <?php echo $currentUserRole !== 'superadmin' ? 'disabled' : ''; ?>>
              <label id="imageUploadTrigger" for="imageUpload" class="upload-dropzone">
                <i class="ri-upload-2-line"></i>
                <div>
                  <p>Pilih foto dari perangkat</p>
                  <small>Format JPG atau PNG, ukuran maks 5MB</small>
                </div>
              </label>
              <div class="image-preview" aria-live="polite">
                <img id="imagePreview" src="images/perfume.png" alt="Preview Foto Produk">
              </div>
              <p class="image-status" id="imageStatusText">Belum ada gambar baru</p>
            </div>
            <?php if ($currentUserRole !== 'superadmin'): ?>
              <small class="form-text text-muted">Hubungi Super Admin untuk mengganti foto produk.</small>
            <?php endif; ?>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn-secondary" onclick="closeProductModal()">Batal</button>
          <button type="submit" name="add_product" id="submitBtn">Tambah Produk</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Order Details Modal (Invoice Style) -->
  <div id="orderModal" class="modal">
    <div class="modal-content invoice-modal">
      <div class="modal-header">
        <h3>Detail Pesanan</h3>
        <button class="modal-close" onclick="closeOrderModal()">&times;</button>
      </div>
      <div class="modal-body">
        <div id="invoiceContent">
          <div class="invoice-loader">Mengambil data...</div>
        </div>
      </div>
    </div>
  </div>

  <?php if ($currentUserRole === 'superadmin'): ?>
    <!-- User Modal -->
    <div id="userModal" class="modal">
      <div class="modal-content">
        <div class="modal-header">
          <h3>Tambah Pengguna Baru</h3>
          <button class="modal-close" onclick="closeUserModal()">&times;</button>
        </div>
        <form method="POST">
          <div class="modal-body">
            <div class="form-group">
              <label for="username">Username</label>
              <input type="text" name="username" required>
            </div>

            <div class="form-group">
              <label for="email">Email</label>
              <input type="email" name="email" required>
            </div>

            <div class="form-group">
              <label for="password">Password</label>
              <input type="password" name="password" required>
            </div>

            <div class="form-group">
              <label for="full_name">Nama Lengkap</label>
              <input type="text" name="full_name" required>
            </div>

            <div class="form-group">
              <label for="phone">Telepon</label>
              <input type="text" name="phone">
            </div>

            <div class="form-group">
              <label for="address">Alamat</label>
              <textarea name="address" rows="3"></textarea>
            </div>

            <div class="form-group">
              <label for="role">Role</label>
              <select name="role" required>
                <option value="user">User</option>
                <option value="karyawan">Karyawan</option>
                <option value="admin">Admin</option>
                <option value="superadmin">Super Admin</option>
              </select>
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn-secondary" onclick="closeUserModal()">Batal</button>
            <button type="submit" name="add_user">Tambah Pengguna</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Scent Modal -->
    <div id="scentModal" class="modal">
      <div class="modal-content">
        <div class="modal-header">
          <h3>Tambah Aroma Baru</h3>
          <button class="modal-close" onclick="closeScentModal()">&times;</button>
        </div>
        <form method="POST">
          <div class="modal-body">
            <div class="form-group">
              <label for="name">Nama Aroma</label>
              <input type="text" name="name" required>
            </div>

            <div class="form-group">
              <label for="description">Deskripsi</label>
              <textarea name="description" rows="3" required></textarea>
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn-secondary" onclick="closeScentModal()">Batal</button>
            <button type="submit" name="add_scent">Tambah Aroma</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Type Modal -->
    <div id="typeModal" class="modal">
      <div class="modal-content">
        <div class="modal-header">
          <h3>Tambah Tipe Baru</h3>
          <button class="modal-close" onclick="closeTypeModal()">&times;</button>
        </div>
        <form method="POST">
          <div class="modal-body">
            <div class="form-group">
              <label for="name">Nama Tipe</label>
              <input type="text" name="name" required>
            </div>

            <div class="form-group">
              <label for="description">Deskripsi</label>
              <textarea name="description" rows="3" required></textarea>
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn-secondary" onclick="closeTypeModal()">Batal</button>
            <button type="submit" name="add_type">Tambah Tipe</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Store Modal -->
    <div id="storeModal" class="modal">
      <div class="modal-content">
        <div class="modal-header">
          <h3>Tambah Toko Baru</h3>
          <button class="modal-close" onclick="closeStoreModal()">&times;</button>
        </div>
        <form method="POST">
          <div class="modal-body">
            <div class="form-group">
              <label for="name">Nama Toko</label>
              <input type="text" name="name" required>
            </div>

            <div class="form-group">
              <label for="address">Alamat</label>
              <textarea name="address" rows="3" required></textarea>
            </div>

            <div class="form-group">
              <label for="phone">Telepon</label>
              <input type="text" name="phone">
            </div>

            <div class="form-group">
              <label for="manager_name">Manager</label>
              <select name="manager_name" id="manager_name" required>
                <option value="">Pilih Manager</option>
                <?php
                $managers->data_seek(0);
                while ($mgr = $managers->fetch_assoc()):
                ?>
                  <option value="<?php echo htmlspecialchars($mgr['full_name']); ?>">
                    <?php echo htmlspecialchars($mgr['full_name']); ?> (<?php echo htmlspecialchars($mgr['role']); ?> - <?php echo htmlspecialchars($mgr['email']); ?>)
                  </option>
                <?php endwhile; ?>
              </select>
            </div>


            <div class="form-group">
              <label>Inventaris Produk</label>
              <div id="productInventoryList" style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; border-radius: 5px; padding: 10px;">
                <?php
                $products->data_seek(0);
                while ($product = $products->fetch_assoc()):
                ?>
                  <div class="product-inventory-item" style="display: flex; justify-content: space-between; align-items: center; padding: 8px; border-bottom: 1px solid #eee;">
                    <span style="flex: 1;"><?php echo htmlspecialchars($product['name']); ?></span>
                    <input
                      type="number"
                      name="product_stock[<?php echo $product['id']; ?>]"
                      class="product-stock-input"
                      data-product-id="<?php echo $product['id']; ?>"
                      min="0"
                      value="0"
                      style="width: 100px; padding: 5px; border: 1px solid #ddd; border-radius: 3px;"
                      placeholder="Stok">
                  </div>
                <?php endwhile; ?>
              </div>
              <small class="form-text text-muted">Masukkan jumlah stok untuk setiap produk di cabang ini.</small>
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn-secondary" onclick="closeStoreModal()">Batal</button>
            <button type="submit" name="add_store">Tambah Toko</button>
          </div>
        </form>
      </div>
    </div>
  <?php endif; ?>

  <script src="admin.js"></script>
  <script>
    // Handle order status change
    document.querySelectorAll('.status-select').forEach(select => {
      select.addEventListener('change', function() {
        const orderId = this.dataset.orderId;
        const newValue = this.value;
        
        // Send update request
        const formData = new FormData();
        formData.append('update_order_status', '1');
        formData.append('id', orderId);
        formData.append('order_status', newValue);
        
        // Disable select while updating
        this.disabled = true;
        
        fetch('dashboard.php', {
          method: 'POST',
          body: formData,
          headers: {
            'X-Requested-With': 'XMLHttpRequest'
          }
        })
        .then(response => response.json())
        .then(data => {
          // Re-enable select
          this.disabled = false;
          
          if (data.success) {
            // Update select styling
            this.className = `status-select status-${newValue}`;
            
            // Show success message
            showNotification('Status berhasil diperbarui!', 'success');
            
            // Store update timestamp for this order
            if (!window.orderUpdateTimestamps) {
              window.orderUpdateTimestamps = {};
            }
            window.orderUpdateTimestamps[orderId] = Date.now();
            
            console.log('Status updated for order:', orderId, 'to', newValue, 'at', new Date().toISOString());
          } else {
            showNotification(data.message || 'Gagal memperbarui status', 'error');
          }
        })
        .catch(error => {
          // Re-enable select
          this.disabled = false;
          
          console.error('Error:', error);
          showNotification('Terjadi kesalahan', 'error');
        });
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
        padding: 15px 25px;
        background: ${type === 'success' ? '#4CAF50' : '#f44336'};
        color: white;
        border-radius: 10px;
        z-index: 10000;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
      `;
      
      document.body.appendChild(notification);
      
      setTimeout(() => {
        notification.style.opacity = '0';
        notification.style.transition = 'opacity 0.3s';
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