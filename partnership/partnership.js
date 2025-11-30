// Admin Dashboard JavaScript
document.addEventListener('DOMContentLoaded', function () {
  initializeNavigation();
  initializeModals();
});

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
    if (event.target.classList.contains('modal')) {
      event.target.style.display = 'none';
    }
  };
}

// Show Add Product Modal
function showAddProductModal() {
  const modal = document.getElementById('productModal');
  const form = document.getElementById('productForm');
  const submitBtn = document.getElementById('submitBtn');
  const title = document.getElementById('modalTitle');

  form.reset();
  title.innerText = 'Tambah Produk Baru';
  submitBtn.innerText = 'Tambah';
  submitBtn.name = 'add_product';

  // Enable fields for add
  enableFormFields();

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
  document.getElementById('image').value = data.image;

  // Handle Selects for Superadmin, Inputs for Admin
  if (CURRENT_USER_ROLE === 'superadmin') {
    document.getElementById('type').value = data.type;
    document.getElementById('scent').value = data.scent;
  } else {
    // Untuk Admin, field ini readonly text input
    document.getElementById('type_readonly').value = data.type;
    document.getElementById('scent_readonly').value = data.scent;
  }

  // Jika Role Admin, nonaktifkan field tertentu
  if (CURRENT_USER_ROLE !== 'superadmin') {
    disableFormFieldsForAdmin();
  }

  modal.style.display = 'flex';
}

// Edit Stock (Partnership only)
function editStock(btn) {
  const row = btn.closest('tr');
  // Ambil data JSON dari attribute data-product
  const data = JSON.parse(row.getAttribute('data-product'));

  const modal = document.getElementById('productModal');
  const title = document.getElementById('modalTitle');
  const submitBtn = document.getElementById('submitBtn');

  title.innerText = 'Edit Stok: ' + data.name;
  submitBtn.innerText = 'Update Stok';
  submitBtn.name = 'update_product';

  // Isi Form - Partnership hanya bisa edit stock
  document.getElementById('productId').value = data.id;
  document.getElementById('name').value = data.name;
  document.getElementById('price').value = data.price;
  document.getElementById('stock').value = data.stock;
  document.getElementById('description').value = data.description;
  document.getElementById('image').value = data.image;

  // Field readonly untuk Partnership
  document.getElementById('type_readonly').value = data.type;
  document.getElementById('scent_readonly').value = data.scent;
  
  // Nonaktifkan semua field kecuali stock
  document.getElementById('name').readOnly = true;
  document.getElementById('price').readOnly = true;
  document.getElementById('description').readOnly = true;
  document.getElementById('image').readOnly = true;

  modal.style.display = 'flex';
}

function disableFormFieldsForAdmin() {
  // Admin cuma bisa edit stock (dan status via toggle luar)
  // Field lain sudah di set readonly di HTML via PHP, tapi kita pastikan lagi
  const nameField = document.getElementById('name');
  const priceField = document.getElementById('price');
  const descField = document.getElementById('description');

  if (nameField) nameField.readOnly = true;
  if (priceField) priceField.readOnly = true;
  if (descField) descField.readOnly = true;
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
  document.getElementById('productModal').style.display = 'none';
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

  // Fetch API
  fetch(`dashboard.php?action=get_order_details&order_id=${orderId}`)
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        const o = data.order;
        const items = data.items;

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
                              o.status
                            }">${o.status.toUpperCase()}</span></p>
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
  if (CURRENT_USER_ROLE !== 'superadmin') {
    alert('Anda tidak memiliki akses untuk fitur ini.');
    return;
  }

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
  if (CURRENT_USER_ROLE !== 'superadmin') {
    alert('Anda tidak memiliki akses untuk fitur ini.');
    return;
  }
  document.getElementById('userModal').style.display = 'flex';
}

function closeUserModal() {
  document.getElementById('userModal').style.display = 'none';
}

function editUser(id) {
  if (CURRENT_USER_ROLE !== 'superadmin') {
    alert('Anda tidak memiliki akses untuk fitur ini.');
    return;
  }

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
  if (CURRENT_USER_ROLE !== 'superadmin') {
    alert('Anda tidak memiliki akses untuk fitur ini.');
    return;
  }

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
  if (CURRENT_USER_ROLE !== 'superadmin') {
    alert('Anda tidak memiliki akses untuk fitur ini.');
    return;
  }
  document.getElementById('scentModal').style.display = 'flex';
}

function closeScentModal() {
  document.getElementById('scentModal').style.display = 'none';
}

// === TYPE MANAGEMENT ===

function showAddTypeModal() {
  if (CURRENT_USER_ROLE !== 'superadmin') {
    alert('Anda tidak memiliki akses untuk fitur ini.');
    return;
  }
  document.getElementById('typeModal').style.display = 'flex';
}

function closeTypeModal() {
  document.getElementById('typeModal').style.display = 'none';
}

// === STORE MANAGEMENT ===

function showAddStoreModal() {
  if (CURRENT_USER_ROLE !== 'superadmin' && CURRENT_USER_ROLE !== 'admin') {
    alert('Anda tidak memiliki akses untuk fitur ini.');
    return;
  }
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

        title.textContent = 'Edit Toko';
        submitBtn.textContent = 'Update Toko';
        submitBtn.name = 'update_store';

        // Populate form
        modal.querySelector('input[name="name"]').value = store.name;
        modal.querySelector('textarea[name="address"]').value = store.address;
        modal.querySelector('input[name="phone"]').value = store.phone || '';
        
        // Handle Manager Select
        const managerSelect = modal.querySelector('select[name="manager_name"]');
        if (managerSelect) {
            managerSelect.value = store.manager_name || '';
        }
        const managerHidden = modal.querySelector('input[name="manager_name"]');
        const managerReadonly = document.getElementById('managerNameReadonly');
        if (!managerSelect && managerHidden) {
            managerHidden.value = store.manager_name || '';
            if (managerReadonly) {
                managerReadonly.value = store.manager_name || '';
            }
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
  if (CURRENT_USER_ROLE !== 'superadmin') {
    alert('Anda tidak memiliki akses untuk fitur ini.');
    return;
  }

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
