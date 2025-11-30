// Admin Dashboard JavaScript
const DEFAULT_PRODUCT_IMAGE = 'images/perfume.png';
let productImageUploadInput = null;
let productImagePreview = null;
let imagePathInput = null;
let imageStatusText = null;

document.addEventListener('DOMContentLoaded', function () {
  initializeNavigation();
  initializeModals();
  initializeProductImageUploader();
});

// Delete product image directly
function deleteProductImageDirect(button) {
  const productId = button.getAttribute('data-product-id');
  
  console.log('deleteProductImageDirect called, product ID:', productId);
  
  if (!productId || productId === '' || productId === '0') {
    alert('Error: ID produk tidak valid. Silakan tutup modal dan coba lagi.');
    console.error('Invalid product ID:', productId);
    return false;
  }
  
  if (confirm('Yakin ingin menghapus gambar produk ini? Gambar akan diganti dengan gambar default.')) {
    console.log('Creating and submitting form for product ID:', productId);
    
    // Create form dynamically
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'dashboard.php';
    
    // Add hidden inputs
    const deleteInput = document.createElement('input');
    deleteInput.type = 'hidden';
    deleteInput.name = 'delete_product_image';
    deleteInput.value = '1';
    form.appendChild(deleteInput);
    
    const idInput = document.createElement('input');
    idInput.type = 'hidden';
    idInput.name = 'id';
    idInput.value = productId;
    form.appendChild(idInput);
    
    // Add to body and submit
    document.body.appendChild(form);
    console.log('Form created with ID:', productId);
    form.submit();
  } else {
    console.log('User cancelled deletion');
  }
}

// Navigation Management
function initializeNavigation() {
  const navItems = document.querySelectorAll('.nav-item');
  const sections = document.querySelectorAll('.content-section');

  // Handle Hash on Load
  handleHashNavigation();

  // Handle Hash Change (Browser Back/Forward)
  window.addEventListener('hashchange', handleHashNavigation);

  navItems.forEach((item) => {
    item.addEventListener('click', function (e) {
      // Allow default behavior to update URL Hash
      const targetId = this.getAttribute('href').substring(1);

      // Manual UI Update
      updateActiveSection(targetId);
    });
  });
}

function handleHashNavigation() {
  const hash = window.location.hash.substring(1) || 'dashboard'; // Default to dashboard
  updateActiveSection(hash);
}

function updateActiveSection(sectionId) {
  const navItems = document.querySelectorAll('.nav-item');
  const sections = document.querySelectorAll('.content-section');
  const targetSection = document.getElementById(sectionId + '-section');
  const targetNav = document.querySelector(`.nav-item[href="#${sectionId}"]`);

  if (targetSection && targetNav) {
    // Reset classes
    navItems.forEach((nav) => nav.classList.remove('active'));
    sections.forEach((section) => section.classList.remove('active'));

    // Set active
    targetNav.classList.add('active');
    targetSection.classList.add('active');

    // Update Header Title
    const title = targetNav.querySelector('span').innerText;
    document.querySelector('.page-title').innerText = title;
  }
}

// === MODAL FUNCTIONS ===

function initializeModals() {
  // Close modals when clicking outside
  window.onclick = function (event) {
    if (!event.target.classList.contains('modal')) {
      return;
    }

    event.target.style.display = 'none';
  };
}

function initializeProductImageUploader() {
  productImageUploadInput = document.getElementById('imageUpload');
  productImagePreview = document.getElementById('imagePreview');
  imagePathInput = document.getElementById('imagePath');
  imageStatusText = document.getElementById('imageStatusText');

  if (!productImagePreview) {
    return;
  }

  if (productImageUploadInput && !productImageUploadInput.dataset.bound) {
    productImageUploadInput.addEventListener('change', handleProductImageSelected);
    productImageUploadInput.dataset.bound = 'true';
  }

  updateImageStatus('Belum ada gambar baru');
}

function handleProductImageSelected(event) {
  const file = event.target.files[0];
  if (!file) {
    return;
  }

  if (!file.type.startsWith('image/')) {
    updateImageStatus('Format file tidak didukung.', true);
    event.target.value = '';
    return;
  }

  const maxBytes = 5 * 1024 * 1024; // 5MB limit
  if (file.size > maxBytes) {
    updateImageStatus('Ukuran gambar maksimal 5MB.', true);
    event.target.value = '';
    return;
  }

  // Directly upload the file
  uploadImageFile(file);
}

// New function to directly upload image file
function uploadImageFile(file) {
  updateImageStatus('Mengunggah gambar...');
  
  const formData = new FormData();
  formData.append('image', file);

  fetch('upload_product_image.php', {
    method: 'POST',
    body: formData,
    credentials: 'same-origin'
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        if (imagePathInput) {
          imagePathInput.value = data.path;
        }
        updateProductImagePreview(data.path);
        updateImageStatus('Foto produk berhasil diunggah.');
        if (productImageUploadInput) {
          productImageUploadInput.value = '';
        }
      } else {
        updateImageStatus(data.message || 'Gagal mengunggah foto.', true);
      }
    })
    .catch(() => {
      updateImageStatus('Terjadi kesalahan saat mengunggah foto.', true);
    });
}

function updateProductImagePreview(path) {
  if (!productImagePreview) {
    return;
  }

  const baseSrc = getPreviewablePath(path);
  let finalSrc = baseSrc;

  if (baseSrc && !baseSrc.startsWith('data:') && !baseSrc.startsWith('blob:')) {
    const separator = baseSrc.includes('?') ? '&' : '?';
    finalSrc = `${baseSrc}${separator}v=${Date.now()}`;
  }

  productImagePreview.src = finalSrc;
  productImagePreview.style.display = 'block';
}

function getPreviewablePath(path) {
  if (!path) {
    return '../' + DEFAULT_PRODUCT_IMAGE.replace(/^\/+/, '');
  }

  if (path.startsWith('data:') || path.startsWith('blob:')) {
    return path;
  }

  if (path.startsWith('http://') || path.startsWith('https://')) {
    return path;
  }

  if (path.startsWith('../')) {
    return path;
  }

  if (path.startsWith('/')) {
    return '..' + path;
  }

  return '../' + path.replace(/^\/+/, '');
}

function updateImageStatus(message, isError) {
  if (!imageStatusText) {
    return;
  }
  imageStatusText.textContent = message;
  if (isError) {
    imageStatusText.classList.add('error');
  } else {
    imageStatusText.classList.remove('error');
  }
}

function resetProductImageUploader(initialPath, statusMessage) {
  if (productImageUploadInput) {
    productImageUploadInput.value = '';
  }
  if (imagePathInput) {
    imagePathInput.value = initialPath || DEFAULT_PRODUCT_IMAGE;
  }
  updateProductImagePreview(initialPath || DEFAULT_PRODUCT_IMAGE);
  updateImageStatus(statusMessage || 'Belum ada gambar baru');
}

// Show Add Product Modal
function showAddProductModal() {
  const modal = document.getElementById('productModal');
  const form = document.getElementById('productForm');
  const submitBtn = document.getElementById('submitBtn');
  const title = document.getElementById('modalTitle');
  const deleteImageBtn = document.getElementById('deleteImageBtn');

  form.reset();
  title.innerText = 'Tambah Produk Baru';
  submitBtn.innerText = 'Tambah';
  submitBtn.name = 'add_product';

  // Enable fields for add
  enableFormFields();
  resetProductImageUploader(DEFAULT_PRODUCT_IMAGE, 'Belum ada gambar baru');

  // Hide delete image button for new product
  if (deleteImageBtn) {
    deleteImageBtn.style.display = 'none';
    deleteImageBtn.setAttribute('data-product-id', '');
  }

  modal.style.display = 'flex';
}

// Edit Product
function editProduct(btn) {
  const row = btn.closest('tr');
  // Ambil data JSON dari attribute data-product
  const data = JSON.parse(row.getAttribute('data-product'));

  const modal = document.getElementById('productModal');
  const title = document.getElementById('modalTitle');
  const submitBtn = document.getElementById('submitBtn');

  title.innerText = 'Edit Produk: ' + data.name;
  submitBtn.innerText = 'Simpan Perubahan';
  submitBtn.name = 'update_product';

  // Isi Form
  document.getElementById('productId').value = data.id;
  document.getElementById('name').value = data.name;
  document.getElementById('price').value = data.price;
  document.getElementById('stock').value = data.stock;
  document.getElementById('description').value = data.description;

  if (imagePathInput) {
    imagePathInput.value = data.image || DEFAULT_PRODUCT_IMAGE;
  }
  updateProductImagePreview(data.image || DEFAULT_PRODUCT_IMAGE);
  updateImageStatus('Menggunakan foto yang tersimpan.');

  // Show/hide delete image button
  const deleteImageBtn = document.getElementById('deleteImageBtn');
  if (deleteImageBtn) {
    deleteImageBtn.setAttribute('data-product-id', data.id);
    console.log('Setting product ID for delete:', data.id);
    console.log('Product image:', data.image);
    console.log('Is custom image:', data.image && data.image !== DEFAULT_PRODUCT_IMAGE && data.image !== 'images/perfume.png');
    
    // Show button only if image is not default
    if (data.image && data.image !== DEFAULT_PRODUCT_IMAGE && data.image !== 'images/perfume.png') {
      deleteImageBtn.style.display = 'block';
      console.log('Delete button shown for product ID:', data.id);
      console.log('Button data-product-id:', deleteImageBtn.getAttribute('data-product-id'));
    } else {
      deleteImageBtn.style.display = 'none';
      console.log('Delete button hidden');
    }
  } else {
    console.error('Delete button not found!');
  }

  // Admin dashboard - type and scent are readonly
  const typeReadonly = document.getElementById('type_readonly');
  const scentReadonly = document.getElementById('scent_readonly');
  if (typeReadonly) typeReadonly.value = data.type;
  if (scentReadonly) scentReadonly.value = data.scent;

  // Admin can edit name, price, description, image, stock (but not type/scent)
  disableFormFieldsForAdmin();

  modal.style.display = 'flex';
}

function disableFormFieldsForAdmin() {
  // Admin bisa edit name, price, description, image, dan stock
  // Tapi tidak bisa edit type dan scent
  const nameField = document.getElementById('name');
  const priceField = document.getElementById('price');
  const descField = document.getElementById('description');

  // Admin bisa edit semua field ini
  if (nameField) nameField.readOnly = false;
  if (priceField) priceField.readOnly = false;
  if (descField) descField.readOnly = false;
  // Stock tetap editable
}

function enableFormFields() {
  const nameField = document.getElementById('name');
  const priceField = document.getElementById('price');
  const descField = document.getElementById('description');

  if (nameField) nameField.readOnly = false;
  if (priceField) priceField.readOnly = false;
  if (descField) descField.readOnly = false;
}

function closeProductModal() {
  const modal = document.getElementById('productModal');
  const form = document.getElementById('productForm');

  modal.style.display = 'none';
  form.reset();

  // Reset image preview
  updateProductImagePreview(DEFAULT_PRODUCT_IMAGE);
  updateImageStatus('Belum ada gambar baru');

  // Hide delete image button
  const deleteBtn = document.getElementById('deleteImageBtn');
  if (deleteBtn) {
    deleteBtn.style.display = 'none';
    deleteBtn.setAttribute('data-product-id', '');
  }

  if (productImageUploadInput) {
    productImageUploadInput.value = '';
  }
}

function deleteProduct(id) {
  if (confirm('Yakin ingin menghapus produk ini?')) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `<input type="hidden" name="delete_product" value="1"><input type="hidden" name="id" value="${id}">`;
    document.body.appendChild(form);
    form.submit();
  }
}

function toggleProductStatus(id, checked) {
  // Partnership tidak boleh mengubah status produk
  if (CURRENT_USER_ROLE === 'partnership') {
    alert('Anda tidak memiliki izin untuk mengubah status produk.');
    return;
  }
  
  const status = checked ? 'active' : 'inactive';
  const form = document.createElement('form');
  form.method = 'POST';
  form.innerHTML = `<input type="hidden" name="toggle_product_status" value="1"><input type="hidden" name="id" value="${id}"><input type="hidden" name="status" value="${status}">`;
  document.body.appendChild(form);
  form.submit();
}

// === ORDER MANAGEMENT ===

function updateOrderStatus(id, status, selectElement) {
  // Update visual class
  if (selectElement) {
    selectElement.className = 'status-select status-' + status;
  }

  // Submit change without page reload to stay on current section
  const form = document.createElement('form');
  form.method = 'POST';
  form.innerHTML = `<input type="hidden" name="update_order_status" value="1"><input type="hidden" name="id" value="${id}"><input type="hidden" name="status" value="${status}">`;

  // Add current hash to redirect back to same section
  const currentHash = window.location.hash || '#orders';
  const actionInput = document.createElement('input');
  actionInput.type = 'hidden';
  actionInput.name = 'redirect_to';
  actionInput.value = currentHash;
  form.appendChild(actionInput);

  document.body.appendChild(form);
  form.submit();
}

// View Order Details (Invoice Style)
function viewOrderDetails(orderId) {
  const modal = document.getElementById('orderModal');
  const content = document.getElementById('invoiceContent');
  modal.style.display = 'flex';
  content.innerHTML = '<div class="invoice-loader">Mengambil data...</div>';

  // Check if order was recently updated, add small delay to ensure DB is updated
  const recentlyUpdated = window.orderUpdateTimestamps && 
                          window.orderUpdateTimestamps[orderId] && 
                          (Date.now() - window.orderUpdateTimestamps[orderId]) < 1000;
  
  const fetchDelay = recentlyUpdated ? 300 : 0;
  
  setTimeout(() => {
    // Fetch API with cache busting to ensure fresh data
    const timestamp = new Date().getTime();
    fetch(`dashboard.php?action=get_order_details&order_id=${orderId}&_=${timestamp}`, {
      cache: 'no-cache',
      headers: {
        'Cache-Control': 'no-cache',
        'Pragma': 'no-cache'
      }
    })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        const o = data.order;
        const items = data.items;
        
        // Debug: Log order status
        console.log('Order Status:', o.order_status, 'Payment Status:', o.payment_status);

        let itemRows = '';
        items.forEach((item) => {
          const totalItem = item.price * item.quantity;
          itemRows += `
                    <tr>
                        <td>${item.product_name}</td>
                        <td class="text-center">${item.quantity}</td>
                        <td class="text-right">${formatCurrency(
                          item.price
                        )}</td>
                        <td class="text-right">${formatCurrency(totalItem)}</td>
                    </tr>
                `;
        });

        const html = `
                <div class="invoice-box">
                    <div class="invoice-header-box">
                        <div class="inv-logo">
                            <h2>PARFUMÉ LUX</h2>
                            <p>Exclusive Perfumery</p>
                        </div>
                        <div class="inv-meta">
                            <h1>INVOICE</h1>
                            <p><strong>No:</strong> ${o.order_number}</p>
                            <p><strong>Tanggal:</strong> ${new Date(
                              o.created_at
                            ).toLocaleDateString('id-ID')}</p>
                            <p><strong>Status:</strong> <span class="status-badge status-${
                              o.order_status
                            }">${o.order_status.toUpperCase()}</span></p>
                        </div>
                    </div>
                    
                    <div class="invoice-info-grid">
                        <div class="info-group">
                            <h4>Diterbitkan Untuk:</h4>
                            <p><strong>${o.full_name}</strong></p>
                            <p>${o.email}</p>
                            <p>${o.phone || '-'}</p>
                        </div>
                        <div class="info-group">
                            <h4>Dikirim Ke:</h4>
                            <p>${o.shipping_address}</p>
                        </div>
                    </div>

                    <table class="invoice-table">
                        <thead>
                            <tr>
                                <th>Deskripsi Item</th>
                                <th class="text-center">Qty</th>
                                <th class="text-right">Harga</th>
                                <th class="text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${itemRows}
                        </tbody>
                        <tfoot>
                            <tr class="total-row">
                                <td colspan="3" class="text-right">Total Tagihan</td>
                                <td class="text-right">${formatCurrency(
                                  o.total_amount
                                )}</td>
                            </tr>
                        </tfoot>
                    </table>

                    <div class="invoice-footer">
                        <p>Terima kasih telah berbelanja di Parfumé Lux.</p>
                        <p style="font-size: 0.8rem; color: #888;">Invoice ini dibuat secara otomatis oleh komputer.</p>
                    </div>
                </div>
            `;
        content.innerHTML = html;
      } else {
        content.innerHTML =
          '<p class="error">Data pesanan tidak ditemukan.</p>';
      }
    })
    .catch((err) => {
      console.error(err);
      content.innerHTML =
        '<p class="error">Terjadi kesalahan saat memuat data.</p>';
    });
  }, fetchDelay);
}

function closeOrderModal() {
  document.getElementById('orderModal').style.display = 'none';
}

function formatCurrency(amount) {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
  }).format(amount);
}

// Export Orders to Excel
function exportOrders() {
  // Create a clean table for export
  const table = document.querySelector('#orders-section table');
  const clone = table.cloneNode(true);

  // Remove action column
  const headerRow = clone.querySelector('thead tr');
  const actionHeaderIndex = Array.from(headerRow.children).findIndex(
    (th) => th.textContent.trim() === 'Aksi'
  );
  if (actionHeaderIndex > -1) {
    headerRow.deleteCell(actionHeaderIndex);
  }

  // Remove action column from data rows
  const dataRows = clone.querySelectorAll('tbody tr');
  dataRows.forEach((row) => {
    if (actionHeaderIndex > -1) {
      row.deleteCell(actionHeaderIndex);
    }
  });

  // Add some Excel formatting
  const html = `
    <html>
      <head>
        <style>
          table { border-collapse: collapse; }
          th, td { border: 1px solid #ddd; padding: 8px; }
          th { background-color: #f2f2f2; font-weight: bold; }
          .status-pending { background-color: #fff3cd; }
          .status-processing { background-color: #cce5ff; }
          .status-shipped { background-color: #d1ecf1; }
          .status-delivered { background-color: #d4edda; }
          .status-cancelled { background-color: #f8d7da; }
        </style>
      </head>
      <body>
        <h2>Laporan Pesanan - Parfumé Lux</h2>
        <p>Tanggal Export: ${new Date().toLocaleDateString('id-ID')}</p>
        ${clone.outerHTML}
      </body>
    </html>
  `;

  const blob = new Blob([html], { type: 'application/vnd.ms-excel' });
  const url = window.URL.createObjectURL(blob);
  const downloadLink = document.createElement('a');
  document.body.appendChild(downloadLink);

  downloadLink.href = url;
  downloadLink.download = `Laporan_Pesanan_ParfumeLux_${
    new Date().toISOString().split('T')[0]
  }.xls`;
  downloadLink.click();

  document.body.removeChild(downloadLink);
  window.URL.revokeObjectURL(url);
}

// Export Users to Excel
function exportUsers() {
  // Admin cannot export users (only superadmin can)
  alert('Hanya Super Admin yang bisa export data pengguna.');
  return;

  const table = document.querySelector('#users-section table');
  const clone = table.cloneNode(true);

  // Remove action column
  const headerRow = clone.querySelector('thead tr');
  const actionHeaderIndex = Array.from(headerRow.children).findIndex(
    (th) => th.textContent.trim() === 'Aksi'
  );
  if (actionHeaderIndex > -1) {
    headerRow.deleteCell(actionHeaderIndex);
  }

  // Remove action column from data rows
  const dataRows = clone.querySelectorAll('tbody tr');
  dataRows.forEach((row) => {
    if (actionHeaderIndex > -1) {
      row.deleteCell(actionHeaderIndex);
    }
  });

  const html = `
    <html>
      <head>
        <style>
          table { border-collapse: collapse; }
          th, td { border: 1px solid #ddd; padding: 8px; }
          th { background-color: #f2f2f2; font-weight: bold; }
          .role-admin { background-color: #d1ecf1; }
          .role-superadmin { background-color: #f8d7da; }
        </style>
      </head>
      <body>
        <h2>Laporan Pengguna - Parfumé Lux</h2>
        <p>Tanggal Export: ${new Date().toLocaleDateString('id-ID')}</p>
        ${clone.outerHTML}
      </body>
    </html>
  `;

  const blob = new Blob([html], { type: 'application/vnd.ms-excel' });
  const url = window.URL.createObjectURL(blob);
  const downloadLink = document.createElement('a');
  document.body.appendChild(downloadLink);

  downloadLink.href = url;
  downloadLink.download = `Laporan_Pengguna_ParfumeLux_${
    new Date().toISOString().split('T')[0]
  }.xls`;
  downloadLink.click();

  document.body.removeChild(downloadLink);
  window.URL.revokeObjectURL(url);
}

// === USER MANAGEMENT ===

function showAddUserModal() {
  // Admin cannot add users (only superadmin can)
  alert('Hanya Super Admin yang bisa menambah pengguna.');
  return;
  document.getElementById('userModal').style.display = 'flex';
}

function closeUserModal() {
  document.getElementById('userModal').style.display = 'none';
}

function editUser(id) {
  // Admin cannot edit users (only superadmin can)
  alert('Hanya Super Admin yang bisa edit pengguna.');
  return;

  // Fetch user data via AJAX
  fetch(`dashboard.php?action=get_user_details&user_id=${id}`)
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        const user = data.user;
        const modal = document.getElementById('userModal');
        const title = modal.querySelector('h3');
        const submitBtn = modal.querySelector('button[type="submit"]');

        title.textContent = 'Edit Pengguna';
        submitBtn.textContent = 'Update Pengguna';
        submitBtn.name = 'update_user';

        // Populate form
        modal.querySelector('input[name="username"]').value = user.username;
        modal.querySelector('input[name="email"]').value = user.email;
        modal.querySelector('input[name="full_name"]').value = user.full_name;
        modal.querySelector('input[name="phone"]').value = user.phone || '';
        modal.querySelector('textarea[name="address"]').value =
          user.address || '';
        modal.querySelector('select[name="role"]').value = user.role;

        // Add hidden input for user ID
        let existingIdInput = modal.querySelector('input[name="id"]');
        if (!existingIdInput) {
          const idInput = document.createElement('input');
          idInput.type = 'hidden';
          idInput.name = 'id';
          modal.querySelector('form').appendChild(idInput);
        }
        modal.querySelector('input[name="id"]').value = user.id;

        modal.style.display = 'flex';
      } else {
        alert('Data pengguna tidak ditemukan.');
      }
    })
    .catch((err) => {
      console.error('Error:', err);
      alert('Terjadi kesalahan saat mengambil data pengguna.');
    });
}

function deleteUser(id) {
  // Admin cannot delete users (only superadmin can)
  alert('Hanya Super Admin yang bisa menghapus pengguna.');
  return;

  if (confirm('Apakah Anda yakin ingin menghapus pengguna ini?')) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `<input type="hidden" name="delete_user" value="1"><input type="hidden" name="id" value="${id}">`;
    document.body.appendChild(form);
    form.submit();
  }
}

// === SCENT MANAGEMENT ===

function showAddScentModal() {
  // Admin dashboard - no permission check needed
  const modal = document.getElementById('scentModal');
  const form = modal.querySelector('form');
  const title = modal.querySelector('h3');
  const submitBtn = modal.querySelector('button[type="submit"]');
  
  form.reset();
  title.textContent = 'Tambah Aroma Baru';
  submitBtn.textContent = 'Tambah Aroma';
  submitBtn.name = 'add_scent';
  
  // Remove hidden id if exists
  const existingId = modal.querySelector('input[name="id"]');
  if (existingId) existingId.remove();
  
  modal.style.display = 'flex';
}

function editScent(id) {
  // Admin dashboard - no permission check needed
  
  // Fetch scent data via AJAX
  fetch(`dashboard.php?action=get_scent_details&scent_id=${id}`)
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        const scent = data.scent;
        const modal = document.getElementById('scentModal');
        const title = modal.querySelector('h3');
        const submitBtn = modal.querySelector('button[type="submit"]');
        
        title.textContent = 'Edit Aroma';
        submitBtn.textContent = 'Update Aroma';
        submitBtn.name = 'update_scent';
        
        // Populate form
        modal.querySelector('input[name="name"]').value = scent.name;
        modal.querySelector('textarea[name="description"]').value = scent.description;
        
        // Add hidden input for id
        let existingIdInput = modal.querySelector('input[name="id"]');
        if (!existingIdInput) {
          const idInput = document.createElement('input');
          idInput.type = 'hidden';
          idInput.name = 'id';
          modal.querySelector('form').appendChild(idInput);
        }
        modal.querySelector('input[name="id"]').value = scent.id;
        
        modal.style.display = 'flex';
      } else {
        alert('Data aroma tidak ditemukan.');
      }
    })
    .catch((err) => {
      console.error('Error:', err);
      alert('Terjadi kesalahan saat mengambil data aroma.');
    });
}

function deleteScent(id) {
  // Admin cannot delete scents (only superadmin can)
  alert('Hanya Super Admin yang bisa menghapus aroma.');
  return;
  
  if (confirm('Apakah Anda yakin ingin menghapus aroma ini?')) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `<input type="hidden" name="delete_scent" value="1"><input type="hidden" name="id" value="${id}">`;
    document.body.appendChild(form);
    form.submit();
  }
}

function closeScentModal() {
  document.getElementById('scentModal').style.display = 'none';
}

// === TYPE MANAGEMENT ===

function showAddTypeModal() {
  // Admin dashboard - no permission check needed
  const modal = document.getElementById('typeModal');
  const form = modal.querySelector('form');
  const title = modal.querySelector('h3');
  const submitBtn = modal.querySelector('button[type="submit"]');
  
  form.reset();
  title.textContent = 'Tambah Tipe Baru';
  submitBtn.textContent = 'Tambah Tipe';
  submitBtn.name = 'add_type';
  
  // Remove hidden id if exists
  const existingId = modal.querySelector('input[name="id"]');
  if (existingId) existingId.remove();
  
  modal.style.display = 'flex';
}

function editType(id) {
  // Admin dashboard - no permission check needed
  
  // Fetch type data via AJAX
  fetch(`dashboard.php?action=get_type_details&type_id=${id}`)
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        const type = data.type;
        const modal = document.getElementById('typeModal');
        const title = modal.querySelector('h3');
        const submitBtn = modal.querySelector('button[type="submit"]');
        
        title.textContent = 'Edit Tipe';
        submitBtn.textContent = 'Update Tipe';
        submitBtn.name = 'update_type';
        
        // Populate form
        modal.querySelector('input[name="name"]').value = type.name;
        modal.querySelector('textarea[name="description"]').value = type.description;
        
        // Add hidden input for id
        let existingIdInput = modal.querySelector('input[name="id"]');
        if (!existingIdInput) {
          const idInput = document.createElement('input');
          idInput.type = 'hidden';
          idInput.name = 'id';
          modal.querySelector('form').appendChild(idInput);
        }
        modal.querySelector('input[name="id"]').value = type.id;
        
        modal.style.display = 'flex';
      } else {
        alert('Data tipe tidak ditemukan.');
      }
    })
    .catch((err) => {
      console.error('Error:', err);
      alert('Terjadi kesalahan saat mengambil data tipe.');
    });
}

function deleteType(id) {
  // Admin cannot delete types (only superadmin can)
  alert('Hanya Super Admin yang bisa menghapus tipe.');
  return;
  
  if (confirm('Apakah Anda yakin ingin menghapus tipe ini?')) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `<input type="hidden" name="delete_type" value="1"><input type="hidden" name="id" value="${id}">`;
    document.body.appendChild(form);
    form.submit();
  }
}

function closeTypeModal() {
  document.getElementById('typeModal').style.display = 'none';
}

// === STORE MANAGEMENT ===

function showAddStoreModal() {
  // Admin cannot add stores (view only)
  alert('Admin tidak bisa menambah toko cabang. Hubungi Super Admin.');
  return;
  const modal = document.getElementById('storeModal');
  const form = modal.querySelector('form');
  form.reset();
  
  // Reset title and button
  modal.querySelector('h3').textContent = 'Tambah Toko Baru';
  const submitBtn = modal.querySelector('button[type="submit"]');
  submitBtn.textContent = 'Tambah Toko';
  submitBtn.name = 'add_store';

  // Remove hidden id if exists
  const existingId = modal.querySelector('input[name="id"]');
  if (existingId) existingId.remove();

  modal.style.display = 'flex';
}

function closeStoreModal() {
  document.getElementById('storeModal').style.display = 'none';
}

function editStore(id) {
  // Permission check is now handled in PHP for rendering the button, 
  // but we keep a basic check here or allow it since the button wouldn't exist otherwise.
  // However, Partnership can edit their own, so we relax this check.
  
  // Fetch store data via AJAX
  fetch(`dashboard.php?action=get_store_details&store_id=${id}`)
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        const store = data.store;
        const modal = document.getElementById('storeModal');
        
        if (!modal) {
          console.error('Store modal not found!');
          alert('Error: Modal toko tidak ditemukan.');
          return;
        }
        
        const title = modal.querySelector('h3');
        const submitBtn = modal.querySelector('button[type="submit"]');

        if (title) {
          title.textContent = 'Edit Toko';
        }
        if (submitBtn) {
          submitBtn.textContent = 'Update Toko';
          submitBtn.name = 'update_store';
        }

        // Populate form
        const nameInput = modal.querySelector('input[name="name"]');
        const addressInput = modal.querySelector('textarea[name="address"]');
        const phoneInput = modal.querySelector('input[name="phone"]');
        
        if (nameInput) nameInput.value = store.name;
        if (addressInput) addressInput.value = store.address;
        if (phoneInput) phoneInput.value = store.phone || '';
        
        // Handle Manager Select
        const managerSelect = modal.querySelector('select[name="manager_name"]');
        if (managerSelect) {
            managerSelect.value = store.manager_name || '';
        }

        
        // Handle Product Inventory
        const inventory = data.inventory || {};
        const productInputs = modal.querySelectorAll('.product-stock-input');
        productInputs.forEach(input => {
          const productId = input.getAttribute('data-product-id');
          input.value = inventory[productId] || 0;
        });

        // Add hidden input for store ID
        let existingIdInput = modal.querySelector('input[name="id"]');
        if (!existingIdInput) {
          const idInput = document.createElement('input');
          idInput.type = 'hidden';
          idInput.name = 'id';
          modal.querySelector('form').appendChild(idInput);
        }
        modal.querySelector('input[name="id"]').value = store.id;

        modal.style.display = 'flex';
      } else {
        alert('Data toko tidak ditemukan.');
      }
    })
    .catch((err) => {
      console.error('Error:', err);
      alert('Terjadi kesalahan saat mengambil data toko.');
    });
}

function deleteStore(id) {
  // Admin cannot delete stores (only superadmin can)
  alert('Hanya Super Admin yang bisa menghapus toko cabang.');
  return;

  if (confirm('Apakah Anda yakin ingin menghapus toko ini?')) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `<input type="hidden" name="delete_store" value="1"><input type="hidden" name="id" value="${id}">`;
    document.body.appendChild(form);
    form.submit();
  }
}

// === REFRESH FUNCTIONS ===

function refreshOrders() {
  // Reload current section without losing hash
  const currentHash = window.location.hash || '#orders';
  window.location.href = 'dashboard.php' + currentHash;
}

// === SEARCH FUNCTIONALITY ===

document.addEventListener('DOMContentLoaded', function () {
  const searchInput = document.getElementById('product-search');
  if (searchInput) {
    searchInput.addEventListener('input', function (e) {
      const searchTerm = e.target.value.toLowerCase();
      const rows = document.querySelectorAll('#products-tbody tr');

      rows.forEach((row) => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
      });
    });
  }
});

// === HANDLE REDIRECTS AFTER FORM SUBMISSION ===

// Check for redirect_to parameter and update hash
window.addEventListener('load', function () {
  const urlParams = new URLSearchParams(window.location.search);
  const redirectTo = urlParams.get('redirect_to');

  if (redirectTo) {
    // Remove the parameter from URL
    const newUrl = window.location.pathname + window.location.hash;
    window.history.replaceState({}, '', newUrl);

    // Update active section
    const sectionId = redirectTo.substring(1); // Remove #
    if (sectionId) {
      updateActiveSection(sectionId);
    }
  }
});


// === STORE PRODUCTS VIEW ===

function viewStoreProducts(storeId, storeName) {
  const modal = document.getElementById('storeProductsModal');
  const title = document.getElementById('storeProductsTitle');
  const content = document.getElementById('storeProductsContent');
  
  if (!modal) {
    alert('Modal tidak ditemukan');
    return;
  }
  
  title.textContent = `Produk - ${storeName}`;
  content.innerHTML = '<p style="text-align: center; padding: 20px;">Memuat data...</p>';
  modal.style.display = 'flex';
  
  // Fetch store products
  fetch(`dashboard.php?action=get_store_products&store_id=${storeId}`)
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        const products = data.products || [];
        
        if (products.length === 0) {
          content.innerHTML = '<p style="text-align: center; padding: 20px;">Tidak ada produk di toko ini.</p>';
          return;
        }
        
        let html = `
          <table class="data-table" style="width: 100%;">
            <thead>
              <tr>
                <th>Nama Produk</th>
                <th>Tipe</th>
                <th>Aroma</th>
                <th>Harga</th>
                <th>Stok</th>
              </tr>
            </thead>
            <tbody>
        `;
        
        products.forEach(product => {
          html += `
            <tr>
              <td>${product.name}</td>
              <td>${product.type}</td>
              <td>${product.scent}</td>
              <td>${formatCurrency(product.price)}</td>
              <td><span class="stock-badge ${product.stock < 20 ? 'low-stock' : ''}">${product.stock}</span></td>
            </tr>
          `;
        });
        
        html += `
            </tbody>
          </table>
        `;
        
        content.innerHTML = html;
      } else {
        content.innerHTML = '<p style="text-align: center; padding: 20px; color: red;">Gagal memuat data produk.</p>';
      }
    })
    .catch(error => {
      console.error('Error:', error);
      content.innerHTML = '<p style="text-align: center; padding: 20px; color: red;">Terjadi kesalahan saat memuat data.</p>';
    });
}

function closeStoreProductsModal() {
  const modal = document.getElementById('storeProductsModal');
  if (modal) {
    modal.style.display = 'none';
  }
}
