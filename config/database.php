<?php
// Database configuration
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'arde_lux';

// Create connection
$conn = new mysqli($host, $username, $password);

// Check connection
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

// Create database if not exists
$sql = "CREATE DATABASE IF NOT EXISTS $database";
if ($conn->query($sql) === FALSE) {
  die("Error creating database: " . $conn->error);
}

// Select database
$conn->select_db($database);

// Create tables if not exist
createTables($conn);

function createTables($conn)
{
  // Users table - Remove duplicate admin and superadmin entries
  $sql = "CREATE TABLE IF NOT EXISTS users (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        full_name VARCHAR(100) NOT NULL,
        phone VARCHAR(20),
        address TEXT,
        role ENUM('user', 'admin', 'superadmin', 'partnership') DEFAULT 'user',
        branch_id VARCHAR(20) DEFAULT NULL,
        photo VARCHAR(255) DEFAULT NULL,
        status ENUM('active', 'pending', 'rejected') DEFAULT 'active',
        expires_at TIMESTAMP NULL DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";

  if ($conn->query($sql) === FALSE) {
    die("Error creating users table: " . $conn->error);
  }

  // Insert admin user (only if not exists)
  $adminUsername = 'admin';
  $checkAdmin = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE username = ?");
  $checkAdmin->bind_param("s", $adminUsername);
  $checkAdmin->execute();
  $result = $checkAdmin->get_result();
  $adminCount = $result->fetch_assoc()['count'];

  if ($adminCount == 0) {
    $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $stmtInsert = $conn->prepare("INSERT INTO users (username, email, password, full_name, phone, address, role, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
    $stmtInsert->bind_param(
      "sssssss",
      $adminUsername,
      'admin@parfumelux.com',
      $hashedPassword,
      'Administrator',
      null,
      null,
      'admin'
    );
    $stmtInsert->execute();
  }

  // Insert superadmin user (only if not exists)
  $superAdminUsername = 'superadmin';
  $checkSuperAdmin = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE username = ?");
  $checkSuperAdmin->bind_param("s", $superAdminUsername);
  $checkSuperAdmin->execute();
  $result = $checkSuperAdmin->get_result();
  $superAdminCount = $result->fetch_assoc()['count'];

  if ($superAdminCount == 0) {
    $hashedPassword = password_hash('superadmin123', PASSWORD_DEFAULT);
    $stmtInsert = $conn->prepare("INSERT INTO users (username, email, password, full_name, phone, address, role, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
    $stmtInsert->bind_param(
      "sssssss",
      $superAdminUsername,
      'superadmin@parfumelux.com',
      $hashedPassword,
      'Super Administrator',
      null,
      null,
      'superadmin'
    );
    $stmtInsert->execute();
  }

  // Products table
  $sql = "CREATE TABLE IF NOT EXISTS products (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(200) NOT NULL,
        type VARCHAR(50) NOT NULL,
        scent VARCHAR(50) NOT NULL,
        price DECIMAL(10,2) NOT NULL,
        description TEXT,
        image VARCHAR(255) DEFAULT 'images/perfume.png',
        stock INT(11) DEFAULT 100,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";

  if ($conn->query($sql) === FALSE) {
    die("Error creating products table: " . $conn->error);
  }

  // Orders table
  $sql = "CREATE TABLE IF NOT EXISTS orders (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        user_id INT(11) NOT NULL,
        order_number VARCHAR(50) UNIQUE NOT NULL,
        total_amount DECIMAL(10,2) NOT NULL,
        shipping_fee DECIMAL(10,2) DEFAULT 0,
        payment_method VARCHAR(50) DEFAULT 'cod',
        payment_status ENUM('pending', 'paid', 'failed') DEFAULT 'pending',
        order_status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
        status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
        shipping_address TEXT NOT NULL,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";

  if ($conn->query($sql) === FALSE) {
    die("Error creating orders table: " . $conn->error);
  }

  // Order items table
  $sql = "CREATE TABLE IF NOT EXISTS order_items (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        order_id INT(11) NOT NULL,
        product_id INT(11) NOT NULL,
        product_name VARCHAR(200),
        product_price DECIMAL(10,2),
        quantity INT(11) NOT NULL,
        price DECIMAL(10,2) NOT NULL,
        subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
    )";

  if ($conn->query($sql) === FALSE) {
    die("Error creating order_items table: " . $conn->error);
  }

  // Seed essential accounts only (no dummy data) - Fixed duplicate issue
  $seedUsers = [
    [
      'username' => 'partnership',
      'email' => 'partnership@parfumelux.com',
      'password' => 'password',
      'full_name' => 'Partnership Demo',
      'phone' => null,
      'address' => null,
      'role' => 'partnership',
      'branch_id' => 'BR001'
    ],
    [
      'username' => 'customer',
      'email' => 'customer@parfumelux.com',
      'password' => 'demo123',
      'full_name' => 'Customer Demo',
      'phone' => null,
      'address' => null,
      'role' => 'user',
      'branch_id' => null
    ],
  ];

  // Insert seed users (avoid duplicates)
  foreach ($seedUsers as $user) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE email = ?");
    $stmt->bind_param("s", $user['email']);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['count'];

    if ($count == 0) {
      $hashedPassword = password_hash($user['password'], PASSWORD_DEFAULT);
      $branchId = $user['branch_id'] ?? null;
      $stmtInsert = $conn->prepare("INSERT INTO users (username, email, password, full_name, phone, address, role, branch_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')");
      $stmtInsert->bind_param(
        "ssssssss",
        $user['username'],
        $user['email'],
        $hashedPassword,
        $user['full_name'],
        $user['phone'],
        $user['address'],
        $user['role'],
        $branchId
      );
      $stmtInsert->execute();
    }
  }
}

// Session management
session_start();

// Helper functions
function isLoggedIn()
{
  return isset($_SESSION['user_id']);
}

function isAdmin()
{
  return isset($_SESSION['user_role']) && ($_SESSION['user_role'] === 'admin' || $_SESSION['user_role'] === 'superadmin' || $_SESSION['user_role'] === 'partnership');
}

function getCurrentUser()
{
  if (isLoggedIn()) {
    return [
      'id' => $_SESSION['user_id'],
      'username' => $_SESSION['username'],
      'full_name' => $_SESSION['full_name'],
      'email' => $_SESSION['email'],
      'role' => $_SESSION['user_role']
    ];
  }
  return null;
}

function formatRupiah($amount)
{
  return 'Rp ' . number_format($amount, 0, ',', '.');
}
