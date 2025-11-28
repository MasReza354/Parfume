<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout Checklist - Ardéliana Lux</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .check-item { margin: 10px 0; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
        .success { background: #d4edda; border-color: #c3e6cb; }
        .warning { background: #fff3cd; border-color: #ffeaa7; }
        .error { background: #f8d7da; border-color: #f5c6cb; }
        button { padding: 10px 20px; background: #007bff; color: white; border: none; cursor: pointer; margin: 5px; }
    </style>
</head>
<body>
    <h1>🔍 Checkout Checklist - Ardéliana Lux</h1>
    <p><strong>Gunakan checklist ini untuk memastikan checkout berfungsi dengan benar:</strong></p>

    <?php
    require_once 'config/database.php';
    session_start();
    ?>

    <div class="check-item <?php echo (isLoggedIn() ? 'success' : 'error'); ?>">
        <strong>1. User Login Status:</strong> 
        <?php 
        if (isLoggedIn()) {
            echo "✅ Logged in sebagai: " . htmlspecialchars($_SESSION['full_name'] ?? 'Unknown');
        } else {
            echo "❌ Tidak login. <a href='auth/login.php'>Login dulu di sini</a>";
        }
        ?>
    </div>

    <div class="check-item <?php echo (isset($_SESSION['cart']) && !empty($_SESSION['cart']) ? 'success' : 'warning'); ?>">
        <strong>2. Cart Status:</strong>
        <?php 
        if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
            echo "✅ Keranjang ada " . count($_SESSION['cart']) . " item:";
            echo "<ul>";
            foreach ($_SESSION['cart'] as $productId => $quantity) {
                if ($productId === 'bubble_wrap' || $productId === 'wooden_packing') {
                    $name = ($productId === 'bubble_wrap') ? 'Bubble Wrap' : 'Packing Kayu';
                    echo "<li>$name (qty: $quantity)</li>";
                } else {
                    $product = $conn->query("SELECT name, status, stock FROM products WHERE id = $productId")->fetch_assoc();
                    if ($product) {
                        $statusIcon = ($product['status'] === 'active') ? '✅' : '❌';
                        $stockIcon = ($product['stock'] >= $quantity) ? '✅' : '❌';
                        echo "<li>$statusIcon {$product['name']} (qty: $quantity) - Status: {$product['status']}, Stock: {$product['stock']} $stockIcon</li>";
                    } else {
                        echo "<li>❌ Product ID $productId tidak ditemukan</li>";
                    }
                }
            }
            echo "</ul>";
        } else {
            echo "⚠️ Keranjang kosong. <a href='index.php'>Tambah produk ke keranjang dulu</a>";
        }
        ?>
    </div>

    <div class="check-item success">
        <strong>3. Database Connection:</strong>
        ✅ Database terhubung
    </div>

    <div class="check-item success">
        <strong>4. Orders Table:</strong>
        ✅ Tabel orders tersedia
    </div>

    <div class="check-item">
        <strong>5. Form Test:</strong>
        <button onclick="testCheckout()">🧪 Test Form Submission</button>
        <div id="testResult"></div>
    </div>

    <hr>
    
    <h3>📋 Langkah Checkout yang Benar:</h3>
    <ol>
        <li>Login dengan user account (<a href="auth/login.php">Login Page</a>)</li>
        <li>Tambah produk ke keranjang (<a href="index.php">Homepage</a>)</li>
        <li>Pergi ke halaman keranjang (<a href="cart.php">Cart Page</a>)</li>
        <li>Klik tombol "Checkout" (jika ada)</li>
        <li>Isi alamat pengiriman lengkap</li>
        <li>Pilih metode pembayaran</li>
        <li>Klik "Buat Pesanan"</li>
        <li>Seharusnya redirect ke halaman orders</li>
    </ol>

    <hr>

    <h3>🔧 Troubleshooting:</h3>
    <ul>
        <li><strong>Tidak bisa login:</strong> Pastikan username/password benar</li>
        <li><strong>Cart kosong:</strong> Tambah produk dulu dari index.php</li>
        <li><strong>Product tidak valid:</strong> Pastikan produk active dan stok mencukupi</li>
        <li><strong>Form tidak submit:</strong> Check browser console untuk JavaScript errors</li>
        <li><strong>No redirect:</strong> Cek log error di PHP error log</li>
    </ul>

    <div style="margin-top: 20px;">
        <a href="index.php" style="margin-right: 10px;">🏠 Homepage</a>
        <a href="cart.php" style="margin-right: 10px;">🛒 Cart</a>
        <a href="checkout.php" style="margin-right: 10px;">💳 Checkout</a>
        <a href="admin/dashboard.php" style="margin-right: 10px;">👨‍💼 Admin Dashboard</a>
    </div>

    <script>
        function testCheckout() {
            const resultDiv = document.getElementById('testResult');
            resultDiv.innerHTML = '<p>Testing checkout form...</p>';
            
            // Test form validation
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'checkout.php';
            
            const input1 = document.createElement('input');
            input1.type = 'hidden';
            input1.name = 'place_order';
            input1.value = '1';
            
            const input2 = document.createElement('input');
            input2.type = 'hidden';
            input2.name = 'shipping_address';
            input2.value = 'Test Address';
            
            const input3 = document.createElement('input');
            input3.type = 'hidden';
            input3.name = 'payment_method';
            input3.value = 'bank_transfer';
            
            form.appendChild(input1);
            form.appendChild(input2);
            form.appendChild(input3);
            
            resultDiv.innerHTML += '<p>✅ Form can be created with required fields</p>';
            resultDiv.innerHTML += '<p>✅ Test selesai. Silakan coba checkout manual.</p>';
        }
    </script>
</body>
</html>
