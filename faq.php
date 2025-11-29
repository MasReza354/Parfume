<?php
require_once 'config/database.php';
?>

<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>FAQ - Parfumé Lux</title>
  <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="perfume-cards.css?v=<?php echo time(); ?>">
  <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    .faq-page {
      min-height: 100vh;
      padding: 100px 0 80px;
      background: linear-gradient(135deg, #fdecec 0%, #f5cdcd 50%, #fdecec 100%);
    }

    .faq-container {
      max-width: 900px;
      margin: 0 auto;
      padding: 0 20px;
    }

    .faq-card {
      background: white;
      border-radius: 20px;
      padding: 50px;
      box-shadow: 0 15px 50px rgba(205, 127, 127, 0.15);
    }

    .faq-header {
      text-align: center;
      margin-bottom: 40px;
      padding-bottom: 30px;
      border-bottom: 3px solid rgba(205, 127, 127, 0.2);
    }

    .faq-header i {
      font-size: 4rem;
      color: #cd7f7f;
      margin-bottom: 20px;
    }

    .faq-header h1 {
      font-family: 'Montserrat', sans-serif;
      font-size: 2.5rem;
      color: #333;
      margin-bottom: 10px;
      font-weight: 700;
    }

    .faq-header p {
      color: #666;
      font-size: 1.1rem;
    }

    .faq-item {
      margin-bottom: 20px;
      border: 2px solid rgba(205, 127, 127, 0.1);
      border-radius: 15px;
      overflow: hidden;
      transition: all 0.3s ease;
    }

    .faq-item:hover {
      border-color: rgba(205, 127, 127, 0.3);
      box-shadow: 0 5px 20px rgba(205, 127, 127, 0.1);
    }

    .faq-question {
      background: linear-gradient(135deg, #fff5f5 0%, #ffe8e8 100%);
      padding: 20px 25px;
      cursor: pointer;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 15px;
      transition: all 0.3s ease;
    }

    .faq-question:hover {
      background: linear-gradient(135deg, #ffe8e8 0%, #ffd5d5 100%);
    }

    .faq-question h3 {
      font-family: 'Montserrat', sans-serif;
      font-size: 1.15rem;
      color: #b86868;
      margin: 0;
      font-weight: 700;
      flex: 1;
    }

    .faq-question i {
      font-size: 1.5rem;
      color: #cd7f7f;
      transition: transform 0.3s ease;
    }

    .faq-item.active .faq-question i {
      transform: rotate(180deg);
    }

    .faq-answer {
      max-height: 0;
      overflow: hidden;
      transition: max-height 0.3s ease;
      background: white;
    }

    .faq-item.active .faq-answer {
      max-height: 500px;
    }

    .faq-answer-content {
      padding: 25px;
      color: #555;
      font-size: 1.05rem;
      line-height: 1.8;
    }

    .faq-answer-content p {
      margin: 0 0 15px 0;
    }

    .faq-answer-content p:last-child {
      margin-bottom: 0;
    }

    .faq-answer-content ul {
      margin: 15px 0;
      padding-left: 25px;
    }

    .faq-answer-content ul li {
      margin-bottom: 10px;
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
      .faq-card {
        padding: 30px 20px;
      }

      .faq-header h1 {
        font-size: 2rem;
      }

      .faq-question {
        padding: 15px 20px;
      }

      .faq-question h3 {
        font-size: 1rem;
      }
    }
  </style>
</head>

<body>
  <div class="faq-page">
    <div class="faq-container">
      <div class="faq-card">
        <div class="faq-header">
          <i class="ri-question-answer-line"></i>
          <h1>Frequently Asked Questions</h1>
          <p>Temukan jawaban untuk pertanyaan yang sering diajukan</p>
        </div>

        <div class="faq-list">
          <div class="faq-item">
            <div class="faq-question">
              <h3>Apakah semua produk di Parfumé Lux original?</h3>
              <i class="ri-arrow-down-s-line"></i>
            </div>
            <div class="faq-answer">
              <div class="faq-answer-content">
                <p>Ya, kami menjamin 100% keaslian semua produk yang dijual di Parfumé Lux. Setiap produk dilengkapi dengan:</p>
                <ul>
                  <li>Sertifikat keaslian</li>
                  <li>Barcode yang dapat diverifikasi</li>
                  <li>Kemasan original dari brand</li>
                  <li>Garansi uang kembali jika terbukti palsu</li>
                </ul>
              </div>
            </div>
          </div>

          <div class="faq-item">
            <div class="faq-question">
              <h3>Berapa lama waktu pengiriman?</h3>
              <i class="ri-arrow-down-s-line"></i>
            </div>
            <div class="faq-answer">
              <div class="faq-answer-content">
                <p>Waktu pengiriman tergantung pada pilihan layanan:</p>
                <ul>
                  <li><strong>Reguler:</strong> 3-5 hari kerja</li>
                  <li><strong>Express:</strong> 1-2 hari kerja</li>
                  <li><strong>Same Day (Jabodetabek):</strong> Hari yang sama</li>
                </ul>
                <p>Pesanan akan diproses dalam 1-2 hari kerja setelah pembayaran dikonfirmasi.</p>
              </div>
            </div>
          </div>

          <div class="faq-item">
            <div class="faq-question">
              <h3>Bagaimana cara melakukan pembayaran?</h3>
              <i class="ri-arrow-down-s-line"></i>
            </div>
            <div class="faq-answer">
              <div class="faq-answer-content">
                <p>Kami menerima berbagai metode pembayaran:</p>
                <ul>
                  <li>Transfer Bank (BCA, Mandiri, BNI, BRI)</li>
                  <li>E-Wallet (GoPay, OVO, Dana, ShopeePay)</li>
                  <li>Kartu Kredit/Debit</li>
                  <li>COD (Cash on Delivery) untuk area tertentu</li>
                </ul>
              </div>
            </div>
          </div>

          <div class="faq-item">
            <div class="faq-question">
              <h3>Apakah bisa retur atau tukar produk?</h3>
              <i class="ri-arrow-down-s-line"></i>
            </div>
            <div class="faq-answer">
              <div class="faq-answer-content">
                <p>Ya, kami menerima retur/tukar produk dengan syarat:</p>
                <ul>
                  <li>Produk masih dalam kondisi sealed/belum dibuka</li>
                  <li>Maksimal 7 hari setelah produk diterima</li>
                  <li>Disertai bukti pembelian dan foto produk</li>
                  <li>Produk tidak rusak karena kesalahan penggunaan</li>
                </ul>
                <p>Untuk produk yang rusak/cacat saat diterima, kami akan mengganti 100% tanpa biaya tambahan.</p>
              </div>
            </div>
          </div>

          <div class="faq-item">
            <div class="faq-question">
              <h3>Bagaimana cara tracking pesanan saya?</h3>
              <i class="ri-arrow-down-s-line"></i>
            </div>
            <div class="faq-answer">
              <div class="faq-answer-content">
                <p>Setelah pesanan dikirim, Anda akan menerima:</p>
                <ul>
                  <li>Email konfirmasi pengiriman</li>
                  <li>Nomor resi pengiriman</li>
                  <li>Link tracking untuk memantau posisi paket</li>
                </ul>
                <p>Anda juga dapat melihat status pesanan di halaman "My Orders" setelah login.</p>
              </div>
            </div>
          </div>

          <div class="faq-item">
            <div class="faq-question">
              <h3>Apakah ada garansi untuk produk yang dibeli?</h3>
              <i class="ri-arrow-down-s-line"></i>
            </div>
            <div class="faq-answer">
              <div class="faq-answer-content">
                <p>Semua produk dilindungi dengan garansi keaslian. Jika produk terbukti tidak original, kami akan:</p>
                <ul>
                  <li>Mengembalikan 100% uang Anda</li>
                  <li>Memberikan kompensasi tambahan</li>
                  <li>Menanggung semua biaya pengiriman retur</li>
                </ul>
              </div>
            </div>
          </div>

          <div class="faq-item">
            <div class="faq-question">
              <h3>Bagaimana cara mendapatkan promo gratis ongkir?</h3>
              <i class="ri-arrow-down-s-line"></i>
            </div>
            <div class="faq-answer">
              <div class="faq-answer-content">
                <p>Gratis ongkir otomatis berlaku untuk:</p>
                <ul>
                  <li>Pembelian minimal Rp 500.000</li>
                  <li>Berlaku untuk pengiriman reguler ke seluruh Indonesia</li>
                  <li>Tidak dapat digabung dengan promo lainnya</li>
                </ul>
                <p>Promo akan otomatis teraplikasi saat checkout jika memenuhi syarat.</p>
              </div>
            </div>
          </div>

          <div class="faq-item">
            <div class="faq-question">
              <h3>Bagaimana cara menghubungi customer service?</h3>
              <i class="ri-arrow-down-s-line"></i>
            </div>
            <div class="faq-answer">
              <div class="faq-answer-content">
                <p>Anda dapat menghubungi kami melalui:</p>
                <ul>
                  <li><strong>WhatsApp:</strong> +62 812-3456-7890 (24/7)</li>
                  <li><strong>Email:</strong> info@parfumelux.com</li>
                  <li><strong>Telepon:</strong> +62 21 1234 5678 (Senin-Jumat, 09:00-17:00)</li>
                  <li><strong>Live Chat:</strong> Tersedia di website (09:00-21:00)</li>
                </ul>
              </div>
            </div>
          </div>
        </div>

        <a href="index.php" class="back-btn">
          <i class="ri-arrow-left-line"></i> Kembali ke Beranda
        </a>
      </div>
    </div>
  </div>

  <script>
    // FAQ Accordion
    document.querySelectorAll('.faq-question').forEach(question => {
      question.addEventListener('click', () => {
        const faqItem = question.parentElement;
        const isActive = faqItem.classList.contains('active');

        // Close all FAQ items
        document.querySelectorAll('.faq-item').forEach(item => {
          item.classList.remove('active');
        });

        // Open clicked item if it wasn't active
        if (!isActive) {
          faqItem.classList.add('active');
        }
      });
    });
  </script>
</body>

</html>
