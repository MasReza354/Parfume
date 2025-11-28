<?php
require_once '../config/database.php';

// Allow access for admin, superadmin, and karyawan
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin', 'superadmin', 'karyawan'])) {
    header('Location: ../index.php');
    exit;
}

$currentUserRole = $_SESSION['user_role'];
$karyawanBranchId = null;
$productBranchError = null;

if ($currentUserRole === 'karyawan') {
    $karyawanBranchId = $_SESSION['branch_id'] ?? null;

    if (!$karyawanBranchId && isset($_SESSION['user_id'])) {
        $branchColumnCheck = $conn->query("SHOW COLUMNS FROM users LIKE 'branch_id'");
        if ($branchColumnCheck && $branchColumnCheck->num_rows > 0) {
            $branchStmt = $conn->prepare("SELECT branch_id FROM users WHERE id = ? LIMIT 1");
            $branchStmt->bind_param("i", $_SESSION['user_id']);
            $branchStmt->execute();
            $branchRow = $branchStmt->get_result()->fetch_assoc();
            if (!empty($branchRow['branch_id'])) {
                $karyawanBranchId = $branchRow['branch_id'];
            }
        }
    }

    if (!$karyawanBranchId && isset($_SESSION['full_name'])) {
        $managerStmt = $conn->prepare("SELECT branch_id FROM stores WHERE manager_name = ? LIMIT 1");
        $managerStmt->bind_param("s", $_SESSION['full_name']);
        $managerStmt->execute();
        $managerRow = $managerStmt->get_result()->fetch_assoc();
        if (!empty($managerRow['branch_id'])) {
            $karyawanBranchId = $managerRow['branch_id'];
        }
    }

    if ($karyawanBranchId) {
        $_SESSION['branch_id'] = $karyawanBranchId;
    } else {
        $productBranchError = 'Cabang Anda belum terhubung. Hubungi administrator untuk mengatur branch_id.';
    }
}

// Handle AJAX request for store details
if (isset($_GET['action']) && $_GET['action'] === 'get_store_details') {
    $storeId = intval($_GET['store_id'] ?? 0);

    $storeStmt = $conn->prepare("SELECT * FROM stores WHERE id = ? LIMIT 1");
    $storeStmt->bind_param("i", $storeId);
    $storeStmt->execute();
    $store = $storeStmt->get_result()->fetch_assoc();

    if ($store) {
        if ($currentUserRole === 'karyawan') {
            if (!$karyawanBranchId || $store['branch_id'] !== $karyawanBranchId) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit;
            }
        }

        $inventoryQuery = $conn->prepare("SELECT product_id, stock FROM product_branch WHERE branch_id = ?");
        $inventoryQuery->bind_param("s", $store['branch_id']);
        $inventoryQuery->execute();
        $inventoryResult = $inventoryQuery->get_result();

        $inventory = [];
        while ($item = $inventoryResult->fetch_assoc()) {
            $inventory[$item['product_id']] = (int) $item['stock'];
        }

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'store' => $store, 'inventory' => $inventory]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Store not found']);
    }
    exit;
}

// Handle product CRUD actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Superadmin can add new products directly
    if (isset($_POST['add_product']) && $currentUserRole === 'superadmin') {
        $name = trim($_POST['name'] ?? '');
        $type = trim($_POST['type'] ?? '');
        $scent = trim($_POST['scent'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $description = $_POST['description'] ?? '';
        $image = $_POST['image'] ?? 'images/perfume.png';
        $stock = max(0, intval($_POST['stock'] ?? 0));

        $stmt = $conn->prepare("INSERT INTO products (name, type, scent, price, description, image, stock) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssdssi", $name, $type, $scent, $price, $description, $image, $stock);

        if ($stmt->execute()) {
            $_SESSION['success_message'] = 'Produk berhasil ditambahkan!';
        } else {
            $_SESSION['error_message'] = 'Gagal menambahkan produk.';
        }
        header('Location: dashboard.php#products');
        exit;
    }

    if (isset($_POST['delete_product']) && $currentUserRole === 'superadmin') {
        $productId = intval($_POST['id'] ?? 0);
        if ($productId > 0) {
            $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
            $stmt->bind_param("i", $productId);
            if ($stmt->execute()) {
                $_SESSION['success_message'] = 'Produk berhasil dihapus!';
            } else {
                $_SESSION['error_message'] = 'Gagal menghapus produk.';
            }
        } else {
            $_SESSION['error_message'] = 'Produk tidak ditemukan.';
        }
        header('Location: dashboard.php#products');
        exit;
    }

    // Update products or branch stock via modal form
    if (isset($_POST['update_product'])) {
        $productId = intval($_POST['id'] ?? 0);
        $stock = max(0, intval($_POST['stock'] ?? 0));

        if ($productId <= 0) {
            $_SESSION['error_message'] = 'Produk tidak valid.';
            header('Location: dashboard.php#products');
            exit;
        }

        if ($currentUserRole === 'superadmin') {
            $name = trim($_POST['name'] ?? '');
            $type = trim($_POST['type'] ?? '');
            $scent = trim($_POST['scent'] ?? '');
            $price = floatval($_POST['price'] ?? 0);
            $description = $_POST['description'] ?? '';
            $image = $_POST['image'] ?? 'images/perfume.png';

            $stmt = $conn->prepare("UPDATE products SET name = ?, type = ?, scent = ?, price = ?, description = ?, image = ?, stock = ? WHERE id = ?");
            $stmt->bind_param("sssdssii", $name, $type, $scent, $price, $description, $image, $stock, $productId);
            $result = $stmt->execute();
        } elseif ($currentUserRole === 'admin') {
            $stmt = $conn->prepare("UPDATE products SET stock = ? WHERE id = ?");
            $stmt->bind_param("ii", $stock, $productId);
            $result = $stmt->execute();
        } else {
            if (!$karyawanBranchId) {
                $_SESSION['error_message'] = 'Cabang Anda belum terhubung, tidak dapat memperbarui stok.';
                header('Location: dashboard.php#products');
                exit;
            }

            // Find store id for this branch
            $storeStmt = $conn->prepare("SELECT id FROM stores WHERE branch_id = ? LIMIT 1");
            $storeStmt->bind_param("s", $karyawanBranchId);
            $storeStmt->execute();
            $storeRow = $storeStmt->get_result()->fetch_assoc();
            $storeId = $storeRow['id'] ?? null;

            if (!$storeId) {
                $_SESSION['error_message'] = 'Toko cabang tidak ditemukan untuk akun Anda.';
                header('Location: dashboard.php#products');
                exit;
            }

            $stmt = $conn->prepare("INSERT INTO product_branch (branch_id, store_id, product_id, stock) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE stock = VALUES(stock)");
            $stmt->bind_param("siii", $karyawanBranchId, $storeId, $productId, $stock);
            $result = $stmt->execute();
        }

        if (!empty($result)) {
            $_SESSION['success_message'] = 'Produk berhasil diperbarui!';
        } else {
            $_SESSION['error_message'] = 'Gagal memperbarui produk.';
        }

        header('Location: dashboard.php#products');
        exit;
    }

    if (isset($_POST['add_store']) && in_array($currentUserRole, ['admin', 'superadmin'])) {
        $name = trim($_POST['name'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $managerName = trim($_POST['manager_name'] ?? '');
        $productStock = $_POST['product_stock'] ?? [];

        $stmt = $conn->prepare("INSERT INTO stores (name, address, phone, manager_name) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $name, $address, $phone, $managerName);

        if ($stmt->execute()) {
            $newStoreId = $conn->insert_id;
            $branchQuery = $conn->prepare("SELECT branch_id FROM stores WHERE id = ?");
            $branchQuery->bind_param("i", $newStoreId);
            $branchQuery->execute();
            $branchRow = $branchQuery->get_result()->fetch_assoc();
            $branchId = $branchRow['branch_id'] ?? null;

            if (!$branchId) {
                $branchId = sprintf('BR%03d', $newStoreId);
                $updateBranch = $conn->prepare("UPDATE stores SET branch_id = ? WHERE id = ?");
                $updateBranch->bind_param("si", $branchId, $newStoreId);
                $updateBranch->execute();
            }

            foreach ($productStock as $productId => $stock) {
                $stockValue = max(0, intval($stock));
                if ($stockValue > 0) {
                    $invStmt = $conn->prepare("INSERT INTO product_branch (branch_id, store_id, product_id, stock) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE stock = VALUES(stock)");
                    $invStmt->bind_param("siii", $branchId, $newStoreId, $productId, $stockValue);
                    $invStmt->execute();
                }
            }

            $_SESSION['success_message'] = 'Toko berhasil ditambahkan!';
        } else {
            $_SESSION['error_message'] = 'Gagal menambahkan toko.';
        }

        header('Location: dashboard.php#stores');
        exit;
    }

    if (isset($_POST['update_store'])) {
        $id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $managerName = trim($_POST['manager_name'] ?? '');
        $productStock = $_POST['product_stock'] ?? [];

        $canEditStore = false;
        if (in_array($currentUserRole, ['admin', 'superadmin'])) {
            $canEditStore = true;
        } elseif ($currentUserRole === 'karyawan') {
            $branchCheck = $conn->prepare("SELECT branch_id, manager_name FROM stores WHERE id = ?");
            $branchCheck->bind_param("i", $id);
            $branchCheck->execute();
            $branchInfo = $branchCheck->get_result()->fetch_assoc();
            if ($branchInfo && $karyawanBranchId && $branchInfo['branch_id'] === $karyawanBranchId) {
                $canEditStore = true;
                $managerName = $branchInfo['manager_name'] ?? $managerName;
            }
        }

        if (!$canEditStore) {
            $_SESSION['error_message'] = 'Anda tidak memiliki izin untuk mengedit toko ini.';
            header('Location: dashboard.php#stores');
            exit;
        }

        $stmt = $conn->prepare("UPDATE stores SET name = ?, address = ?, phone = ?, manager_name = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $name, $address, $phone, $managerName, $id);

        if ($stmt->execute()) {
            $branchQuery = $conn->prepare("SELECT branch_id FROM stores WHERE id = ?");
            $branchQuery->bind_param("i", $id);
            $branchQuery->execute();
            $branchRow = $branchQuery->get_result()->fetch_assoc();
            $branchId = $branchRow['branch_id'] ?? null;

            if ($branchId) {
                $deleteStmt = $conn->prepare("DELETE FROM product_branch WHERE branch_id = ?");
                $deleteStmt->bind_param("s", $branchId);
                $deleteStmt->execute();

                foreach ($productStock as $productId => $stock) {
                    $stockValue = max(0, intval($stock));
                    if ($stockValue > 0) {
                        $invStmt = $conn->prepare("INSERT INTO product_branch (branch_id, store_id, product_id, stock) VALUES (?, ?, ?, ?)");
                        $invStmt->bind_param("siii", $branchId, $id, $productId, $stockValue);
                        $invStmt->execute();
                    }
                }
            }

            $_SESSION['success_message'] = 'Toko berhasil diperbarui!';
        } else {
            $_SESSION['error_message'] = 'Gagal memperbarui toko.';
        }

        header('Location: dashboard.php#stores');
        exit;
    }

    if (isset($_POST['delete_store']) && in_array($currentUserRole, ['admin', 'superadmin'])) {
        $id = intval($_POST['id'] ?? 0);

        $stmt = $conn->prepare("DELETE FROM stores WHERE id = ?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            $_SESSION['success_message'] = 'Toko berhasil dihapus!';
        } else {
            $_SESSION['error_message'] = 'Gagal menghapus toko.';
        }

        header('Location: dashboard.php#stores');
        exit;
    }
}

// Ensure status column exists for products
$statusCheck = $conn->query("SHOW COLUMNS FROM products LIKE 'status'");
if ($statusCheck->num_rows == 0) {
    $conn->query("ALTER TABLE products ADD COLUMN status ENUM('active','inactive') DEFAULT 'active'");
}

// Dashboard statistics
$totalProducts = $conn->query("SELECT COUNT(*) as cnt FROM products")->fetch_assoc()['cnt'];
$totalOrders   = $conn->query("SELECT COUNT(*) as cnt FROM orders")->fetch_assoc()['cnt'];
$totalUsers    = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE role = 'user'")->fetch_assoc()['cnt'];
$recentOrders  = $conn->query("SELECT o.*, u.full_name FROM orders o JOIN users u ON o.user_id = u.id ORDER BY o.created_at DESC LIMIT 5");
$lowStockProducts = $conn->query("SELECT * FROM products WHERE stock < 20 ORDER BY stock ASC LIMIT 5");
$revenueResult = $conn->query("SELECT SUM(total_amount) as total FROM orders WHERE status != 'cancelled'");
$totalRevenue = $revenueResult->fetch_assoc()['total'] ?? 0;

// Fetch data for tables
if ($currentUserRole === 'karyawan') {
    if ($karyawanBranchId) {
        $productStmt = $conn->prepare("SELECT p.id, p.name, p.type, p.scent, p.price, p.description, p.image, p.status, pb.stock AS stock FROM product_branch pb INNER JOIN products p ON pb.product_id = p.id WHERE pb.branch_id = ? ORDER BY p.name");
        $productStmt->bind_param("s", $karyawanBranchId);
        $productStmt->execute();
        $products = $productStmt->get_result();
    } else {
        $products = null;
    }
} else {
    $products = $conn->query("SELECT * FROM products ORDER BY name");
}
$orders   = $conn->query("SELECT o.*, u.full_name FROM orders o JOIN users u ON o.user_id = u.id ORDER BY o.created_at DESC LIMIT 50");
$users    = $conn->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 50");
$scents   = $conn->query("SELECT * FROM scents ORDER BY name");
$types    = $conn->query("SELECT * FROM types ORDER BY name");
$stores   = $conn->query("SELECT * FROM stores ORDER BY name");
$inventoryProducts = $conn->query("SELECT id, name FROM products ORDER BY name");
$managers = $conn->query("SELECT full_name, email, role FROM users WHERE role IN ('admin','karyawan') ORDER BY full_name");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Karyawan - Ardéliana Lux</title>
    <link rel="stylesheet" href="karyawan.css">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
<script>
    const CURRENT_USER_ROLE = '<?php echo $currentUserRole; ?>';
    const CURRENT_USER_NAME = '<?php echo $_SESSION["full_name"] ?? ""; ?>';
</script>

<!-- Sidebar -->
<div class="admin-sidebar">
    <div class="sidebar-header">
        <h2>Ardéliana Lux</h2>
        <p>Panel Karyawan</p>
    </div>
    <nav class="sidebar-nav">
        <a href="#dashboard" class="nav-item active"><i class="ri-dashboard-line"></i><span>Dashboard</span></a>
        <a href="#products" class="nav-item"><i class="ri-store-2-line"></i><span>Produk</span></a>
        <?php if ($currentUserRole === 'superadmin'): ?>
            <a href="#scents" class="nav-item"><i class="ri-flower-line"></i><span>Aroma</span></a>
            <a href="#types" class="nav-item"><i class="ri-apps-line"></i><span>Tipe</span></a>
            <a href="#users" class="nav-item"><i class="ri-user-line"></i><span>Pengguna</span></a>
        <?php endif; ?>
        <a href="#stores" class="nav-item"><i class="ri-store-3-line"></i><span>Toko Cabang</span></a>
    </nav>
    <div class="sidebar-footer">
        <div class="admin-user">
            <img src="../images/icon.png" alt="Admin" class="admin-avatar">
            <div class="admin-info">
                <span class="admin-name"><?php echo $_SESSION['full_name']; ?></span>
                <span class="admin-role">
                    <?php
                    if (in_array($currentUserRole, ['admin','superadmin','karyawan'])) {
                        echo 'Karyawan';
                    } else {
                        echo ucfirst($currentUserRole);
                    }
                    ?>
                </span>
            </div>
        </div>
        <a href="../auth/logout.php" class="logout-btn"><i class="ri-logout-box-line"></i>Keluar</a>
    </div>
</div>

<!-- Main Content -->
<div class="admin-main">
    <header class="admin-header"><h1 class="page-title">Dashboard</h1></header>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert success"><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert error"><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
    <?php endif; ?>

    <section id="dashboard-section" class="content-section active">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="ri-store-2-line"></i></div>
                <div class="stat-content"><h3><?php echo $totalProducts; ?></h3><p>Total Produk</p></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="ri-shopping-bag-line"></i></div>
                <div class="stat-content"><h3><?php echo $totalOrders; ?></h3><p>Total Pesanan</p></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="ri-user-3-line"></i></div>
                <div class="stat-content"><h3><?php echo $totalUsers; ?></h3><p>Total Pelanggan</p></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="ri-money-dollar-circle-line"></i></div>
                <div class="stat-content"><h3><?php echo formatRupiah($totalRevenue); ?></h3><p>Total Pendapatan</p></div>
            </div>
        </div>
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
                            <div class="activity-meta"><i class="ri-alert-line"></i><span class="activity-date">Stok Rendah</span></div>
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
                <div class="search-box"><input type="text" id="product-search" placeholder="Cari produk..."><i class="ri-search-line"></i></div>
                <?php if ($currentUserRole === 'superadmin'): ?>
                    <button class="btn-primary" onclick="showAddProductModal()"><i class="ri-add-line"></i>Tambah Produk</button>
                <?php endif; ?>
            </div>
        </div>
        <div class="products-table-container">
            <table class="data-table">
                <thead><tr><th>Nama</th><th>Tipe</th><th>Aroma</th><th>Harga</th><th>Gambar</th><th>Status</th><th>Stok</th><th>Aksi</th></tr></thead>
                <tbody id="products-tbody">
                    <?php if ($products instanceof mysqli_result && $products->num_rows > 0): ?>
                        <?php while ($product = $products->fetch_assoc()): ?>
                            <tr data-product='<?php echo json_encode($product); ?>'>
                                <td><?php echo htmlspecialchars($product['name']); ?></td>
                                <td><?php echo htmlspecialchars($product['type']); ?></td>
                                <td><?php echo htmlspecialchars($product['scent']); ?></td>
                                <td><?php echo formatRupiah($product['price']); ?></td>
                                <td><img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-thumb"></td>
                                <td>
                                    <?php $productStatus = $product['status'] ?? 'inactive'; ?>
                                    <span class="status-badge <?php echo $productStatus === 'active' ? 'active' : 'inactive'; ?>">
                                        <?php echo $productStatus === 'active' ? 'Aktif' : 'Tidak Aktif'; ?>
                                    </span>
                                </td>
                                <td><span class="stock-badge <?php echo $product['stock'] < 20 ? 'low-stock' : ''; ?>"><?php echo $product['stock']; ?></span></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-edit" onclick="editProduct(this)"><i class="ri-edit-line"></i></button>
                                        <?php if ($currentUserRole === 'superadmin'): ?>
                                            <button class="btn-delete" onclick="deleteProduct(<?php echo $product['id']; ?>)"><i class="ri-delete-bin-line"></i></button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="empty-state">
                                <?php echo htmlspecialchars($productBranchError ?? 'Belum ada produk yang tersedia untuk ditampilkan.'); ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- Stores Section -->
    <section id="stores-section" class="content-section">
        <div class="section-header">
            <h2>Manajemen Toko Cabang</h2>
            <div class="section-actions">
                <?php if (in_array($currentUserRole, ['admin','superadmin'])): ?>
                    <button class="btn-primary" onclick="showAddStoreModal()"><i class="ri-add-line"></i>Tambah Toko</button>
                <?php endif; ?>
            </div>
        </div>
        <div class="stores-table-container">
            <table class="data-table">
                <thead><tr><th>Nama</th><th>Alamat</th><th>Telepon</th><th>Manajer</th><th>Aksi</th></tr></thead>
                <tbody>
                    <?php while ($store = $stores->fetch_assoc()): ?>
                        <?php
                            $canEditStore = false;
                            if (in_array($currentUserRole, ['admin','superadmin'])) {
                                $canEditStore = true;
                            } elseif ($currentUserRole === 'karyawan' && $karyawanBranchId) {
                                $canEditStore = ($store['branch_id'] ?? null) === $karyawanBranchId;
                            }
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($store['name']); ?></td>
                            <td><?php echo htmlspecialchars($store['address']); ?></td>
                            <td><?php echo htmlspecialchars($store['phone']); ?></td>
                            <td><?php echo htmlspecialchars($store['manager_name']); ?></td>
                            <td>
                                <?php if ($canEditStore): ?>
                                    <button class="btn-edit" onclick="editStore(<?php echo $store['id']; ?>)"><i class="ri-edit-line"></i></button>
                                <?php else: ?>
                                    &mdash;
                                <?php endif; ?>
                                <?php if (in_array($currentUserRole, ['admin','superadmin'])): ?>
                                    <button class="btn-delete" onclick="deleteStore(<?php echo $store['id']; ?>)"><i class="ri-delete-bin-line"></i></button>
                                <?php endif; ?>
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
            <button type="button" class="modal-close" onclick="closeProductModal()">&times;</button>
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
                                    <option value="<?php echo htmlspecialchars($type['name']); ?>"><?php echo htmlspecialchars($type['name']); ?></option>
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
                                    <option value="<?php echo htmlspecialchars($scent['name']); ?>"><?php echo htmlspecialchars($scent['name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="type_readonly">Tipe</label>
                            <input type="text" id="type_readonly" readonly>
                        </div>

                        <div class="form-group">
                            <label for="scent_readonly">Aroma</label>
                            <input type="text" id="scent_readonly" readonly>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="form-row">
                    <div class="form-group">
                        <label for="price">Harga (IDR)</label>
                        <input type="number" id="price" name="price" min="0" <?php echo $currentUserRole === 'superadmin' ? 'required' : 'readonly'; ?>>
                    </div>

                    <div class="form-group">
                        <label for="stock">Stok</label>
                        <input type="number" id="stock" name="stock" min="0" value="0" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="description">Deskripsi</label>
                    <textarea id="description" name="description" rows="4" <?php echo $currentUserRole === 'superadmin' ? 'required' : 'readonly'; ?>></textarea>
                </div>

                <div class="form-group">
                    <label for="image">URL Gambar</label>
                    <input type="text" id="image" name="image" value="images/perfume.png" <?php echo $currentUserRole === 'superadmin' ? '' : 'readonly'; ?>>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeProductModal()">Batal</button>
                <button type="submit" name="add_product" id="submitBtn" class="btn-primary">Tambah Produk</button>
            </div>
        </form>
    </div>
</div>

<!-- Store Modal -->
<div id="storeModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Tambah Toko Baru</h3>
            <button type="button" class="modal-close" onclick="closeStoreModal()">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <div class="form-group">
                    <label for="store-name">Nama Toko</label>
                    <input type="text" id="store-name" name="name" required>
                </div>

                <div class="form-group">
                    <label for="store-address">Alamat</label>
                    <textarea id="store-address" name="address" rows="3" required></textarea>
                </div>

                <div class="form-group">
                    <label for="store-phone">Telepon</label>
                    <input type="text" id="store-phone" name="phone">
                </div>

                <?php if (in_array($currentUserRole, ['admin','superadmin'])): ?>
                    <div class="form-group">
                        <label for="manager_name">Manager</label>
                        <select name="manager_name" id="manager_name" required>
                            <option value="">Pilih Manager</option>
                            <?php if ($managers && $managers instanceof mysqli_result): ?>
                                <?php $managers->data_seek(0); ?>
                                <?php while ($mgr = $managers->fetch_assoc()): ?>
                                    <option value="<?php echo htmlspecialchars($mgr['full_name']); ?>">
                                        <?php echo htmlspecialchars($mgr['full_name']); ?> (<?php echo htmlspecialchars($mgr['role']); ?> - <?php echo htmlspecialchars($mgr['email']); ?>)
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                <?php else: ?>
                    <div class="form-group">
                        <label>Manager</label>
                        <input type="text" id="managerNameReadonly" value="<?php echo htmlspecialchars($_SESSION['full_name'] ?? ''); ?>" readonly>
                        <input type="hidden" name="manager_name" id="managerNameHidden" value="<?php echo htmlspecialchars($_SESSION['full_name'] ?? ''); ?>">
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label>Inventaris Produk</label>
                    <div id="productInventoryList" style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; border-radius: 5px; padding: 10px;">
                        <?php if ($inventoryProducts && $inventoryProducts instanceof mysqli_result && $inventoryProducts->num_rows > 0): ?>
                            <?php $inventoryProducts->data_seek(0); ?>
                            <?php while ($productOption = $inventoryProducts->fetch_assoc()): ?>
                                <div class="product-inventory-item" style="display: flex; justify-content: space-between; align-items: center; padding: 8px; border-bottom: 1px solid #eee;">
                                    <span><?php echo htmlspecialchars($productOption['name']); ?></span>
                                    <input type="number" min="0" value="0" name="product_stock[<?php echo $productOption['id']; ?>]" class="product-stock-input" data-product-id="<?php echo $productOption['id']; ?>" style="width: 100px; padding: 5px; border: 1px solid #ddd; border-radius: 3px;">
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p class="empty-state">Belum ada produk yang dapat diatur untuk cabang.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeStoreModal()">Batal</button>
                <button type="submit" name="add_store">Tambah Toko</button>
            </div>
        </form>
    </div>
</div>

<script src="karyawan.js"></script>
</body>
</html>
