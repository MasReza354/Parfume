<?php
require_once 'config/database.php';
?>

<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Shipping Info - Parfumé Lux</title>
  <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="perfume-cards.css?v=<?php echo time(); ?>">
  <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    .info-page {
      min-height: 100vh;
      padding: 100px 0 80px;
      background: linear-gradient(135deg, #fdecec 0%, #f5cdcd 50%, #fdecec 100%);
    }

    .info-container {
      max-width: 900px;
      margin: 0 auto;
      padding: 0 20px;
    }

    .info-card {
      background: white;
      border-radius: 20px;
      padding: 50px;
      box-shadow: 0 15px 50px rgba(205, 127, 127, 0.15);
    }

    .info-header {
      text-align: center;
      margin-bottom: 40px;
      padding-bottom: 30px;
      border-bottom: 3px solid rgba(205, 127, 127, 0.2);
    }

    .info-header i {
      font-size: 4rem;
      color: #cd7f7f;
      margin-bottom: 20px;
    }

    .info-header h1 {
      font-family: 'Montserrat', sans-serif;
      font-size: 2.5rem;
      color: #333;
      margin-bottom: 10px;
      font-weight: 700;
    }

    .info-header p {
      color: #666;
      font-size: 1.1rem;
    }

    .info-section {
      margin-bottom: 40px;
    }

    .info-section h2 {
      font-family: 'Montserrat', sans-serif;
      font-size: 1.8rem;
      color: #cd7f7f;
      margin-bottom: 20px;
      font-weight: 700;
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .info-section h2 i {
      font-size: 1.5rem;
    }

    .info-section p {
      color: #555;
      font-size: 1.05rem;
      line-height: 1.8;
      margin-bottom: 15px;
    }

    .shipping-options {
      display: grid;
      gap: 20px;
      margin-top: 25px;
    }

    .shipping-option {
      background: linear-gradient(135deg, #fff5f5 0%, #ffe8e8 100%);
      border: 2px solid rgba(205, 127, 127, 0.2);
      border-radius: 15px;
      padding: 25px;
      transition: all 0.3s ease;
    }

    .shipping-option:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 30px rgba(205, 127, 127, 0.2);
    }

    .shipping-option h3 {
      font-family: 'Montserrat', sans-serif;
      font-size: 1.3rem;
      color: #b86868;
      margin-bottom: 10px;
      font-weight: 700;
    }

    .shipping-option .price {
      font-size: 1.5rem;
      color: #cd7f7f;
      font-weight: 700;
      margin-bottom: 10px;
    }

    .shipping-option .duration {
      color: #666;
      font-size: 1rem;
      margin-bottom: 15px;
    }

    .shipping-option ul {
      list-style: none;
      padding: 0;
      margin: 0;
    }

    .shipping-option ul li {
      color: #555;
      font-size: 0.95rem;
      margin-bottom: 8px;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .shipping-option ul li i {
      color: #27ae60;
      font-size: 1.1rem;
    }

    .back-btn {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      background: linear-gradient(135deg, #cd7f7f 0%, #b86868 100%);
      color: white;
      padding: 15px 30px;
      border-radius: 30px;
      text-decoration: none;
      font-weight: 600;
      transition: all 0.3s ease;
      margin-top: 30px;
      box-shadow: 0 4px 15px rgba(205, 127, 127, 0.3);
    }

    .back-btn:hover {
      transform: translateY(-3px);
      box-shadow: 0 8px 25px rgba(205, 127, 127, 0.4);
    }

    @media (max-width: 768px) {
      .info-card {
        padding: 30px 20px;
      }

      .info-header h1 {
        font-size: 2rem;
      }

      .info-section h2 {
        font-size: 1.5rem;
      }
    }
  </style>
</head>

<body>
  <div class="info-page">
    <div class="info-container">
      <div class="info-card">
        <div class="info-header">
          <i class="ri-truck-line"></i>
          <h1>Informasi Pengiriman</h1>
          <p>Ketahui lebih lanjut tentang layanan pengiriman kami</p>
        </div>

        <div class="info-section">
          <h2><i class="ri-map-pin-line"></i> Area Pengiriman</h2>
          <p>Kami melayani pengiriman ke seluruh Indonesia, termasuk daerah terpencil. Pengiriman dilakukan melalui mitra kurir terpercaya untuk memastikan produk sampai dengan aman.</p>
        </div>

        <div class="info-section">
          <h2><i class="ri-price-tag-3-line"></i> Pilihan Pengiriman</h2>
          <div class="shipping-options">
            <div class="shipping-option">
              <h3>🚚 Reguler</h3>
              <div class="price">Rp 15.000</div>
              <div class="duration">Estimasi: 3-5 hari kerja</div>
              <ul>
                <li><i class="ri-check-line"></i> Pengiriman standar</li>
                <li><i class="ri-check-line"></i> Tracking tersedia</li>
                <li><i class="ri-check-line"></i> Asuransi pengiriman</li>
              </ul>
            </div>

            <div class="shipping-option">
              <h3>⚡ Express</h3>
              <div class="price">Rp 30.000</div>
              <div class="duration">Estimasi: 1-2 hari kerja</div>
              <ul>
                <li><i class="ri-check-line"></i> Pengiriman cepat</li>
                <li><i class="ri-check-line"></i> Real-time tracking</li>
                <li><i class="ri-check-line"></i> Asuransi penuh</li>
                <li><i class="ri-check-line"></i> Prioritas pengiriman</li>
              </ul>
            </div>

            <div class="shipping-option">
              <h3>🎁 Same Day (Jabodetabek)</h3>
              <div class="price">Rp 50.000</div>
              <div class="duration">Estimasi: Hari yang sama</div>
              <ul>
                <li><i class="ri-check-line"></i> Pengiriman di hari yang sama</li>
                <li><i class="ri-check-line"></i> Khusus area Jabodetabek</li>
                <li><i class="ri-check-line"></i> Live tracking</li>
                <li><i class="ri-check-line"></i> Asuransi penuh</li>
              </ul>
            </div>
          </div>
        </div>

        <div class="info-section">
          <h2><i class="ri-gift-line"></i> Gratis Ongkir</h2>
          <p>Dapatkan gratis ongkir untuk pembelian minimal Rp 500.000 ke seluruh Indonesia! Promo ini berlaku untuk semua jenis pengiriman reguler.</p>
        </div>

        <div class="info-section">
          <h2><i class="ri-shield-check-line"></i> Kemasan & Keamanan</h2>
          <p>Setiap produk dikemas dengan bubble wrap dan kardus khusus untuk memastikan produk sampai dalam kondisi sempurna. Kami juga menyediakan asuransi pengiriman untuk semua paket.</p>
        </div>

        <div class="info-section">
          <h2><i class="ri-time-line"></i> Waktu Pemrosesan</h2>
          <p>Pesanan akan diproses dalam 1-2 hari kerja setelah pembayaran dikonfirmasi. Untuk pemesanan di hari Sabtu-Minggu akan diproses pada hari Senin.</p>
        </div>

        <a href="index.php" class="back-btn">
          <i class="ri-arrow-left-line"></i> Kembali ke Beranda
        </a>
      </div>
    </div>
  </div>
</body>

</html>
