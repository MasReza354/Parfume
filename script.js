// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function () {
  // Initialize all functionality
  initializeFilters();
  initializeCart();
  initializeFavorites();
  initializeQuickView();
  initializeNavigation();
  initializeUserAuth();
});

// Filter and Sort Functionality
function initializeFilters() {
  const typeFilter = document.getElementById('type-filter');
  const scentFilter = document.getElementById('scent-filter');
  const sortFilter = document.getElementById('sort-filter');
  const perfumeGrid = document.getElementById('perfume-grid');

  if (!typeFilter || !scentFilter || !sortFilter || !perfumeGrid) return;

  function filterAndSortProducts() {
    const cards = Array.from(perfumeGrid.querySelectorAll('.perfume-card'));
    const typeValue = typeFilter.value;
    const scentValue = scentFilter.value;
    const sortValue = sortFilter.value;

    // Filter products
    let visibleCards = cards.filter((card) => {
      const cardType = card.dataset.type;
      const cardScent = card.dataset.scent;

      const typeMatch = !typeValue || cardType === typeValue;
      const scentMatch = !scentValue || cardScent === scentValue;

      return typeMatch && scentMatch;
    });

    // Sort products
    visibleCards.sort((a, b) => {
      switch (sortValue) {
        case 'name':
          return a.dataset.name.localeCompare(b.dataset.name);
        case 'price-low':
          return parseInt(a.dataset.price) - parseInt(b.dataset.price);
        case 'price-high':
          return parseInt(b.dataset.price) - parseInt(a.dataset.price);
        default:
          return 0;
      }
    });

    // Hide all cards first
    cards.forEach((card) => card.classList.add('hidden'));

    // Show and reorder visible cards
    visibleCards.forEach((card) => {
      card.classList.remove('hidden');
      perfumeGrid.appendChild(card);
    });

    // Show no results message if needed
    showNoResultsMessage(visibleCards.length === 0);
  }

  // Add event listeners
  typeFilter.addEventListener('change', filterAndSortProducts);
  scentFilter.addEventListener('change', filterAndSortProducts);
  sortFilter.addEventListener('change', filterAndSortProducts);
}

// Show no results message
function showNoResultsMessage(show) {
  const perfumeGrid = document.getElementById('perfume-grid');
  let noResultsDiv = perfumeGrid.querySelector('.no-results');

  if (show && !noResultsDiv) {
    noResultsDiv = document.createElement('div');
    noResultsDiv.className = 'no-results';
    noResultsDiv.innerHTML = `
            <i class="ri-search-eye-line"></i>
            <h3>No products found</h3>
            <p>Try adjusting your filters to see more results</p>
        `;
    perfumeGrid.appendChild(noResultsDiv);
  } else if (!show && noResultsDiv) {
    noResultsDiv.remove();
  }
}

// Shopping Cart Functionality
function initializeCart() {
  const addToCartButtons = document.querySelectorAll('.add-to-cart-btn');

  addToCartButtons.forEach((button) => {
    button.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();

      const productId = this.dataset.id;
      const productCard = this.closest('.perfume-card');
      const productName =
        productCard.querySelector('.perfume-name').textContent;
      const productPrice = productCard.querySelector('.price').textContent;
      const productImage = productCard.querySelector('.card-image img').src;

      // Add to cart via server-side (will redirect to login if not logged in)
      addToCart(productId, 1);
    });
  });

  // Update cart count on page load
  updateCartCount();
}

// Add to cart function
function addToCart(productId, quantity = 1) {
  // Check if user is logged in by checking body class
  const isUserLoggedIn = document.body.classList.contains('user-logged-in');

  if (!isUserLoggedIn) {
    showLoginRequiredModal();
    return;
  }

  // Create form to submit to cart.php
  const form = document.createElement('form');
  form.method = 'POST';
  form.action = 'cart.php';
  form.style.display = 'none';

  const productIdInput = document.createElement('input');
  productIdInput.type = 'hidden';
  productIdInput.name = 'add_to_cart';
  productIdInput.value = '1';

  const productIdField = document.createElement('input');
  productIdField.type = 'hidden';
  productIdField.name = 'product_id';
  productIdField.value = productId;

  const quantityInput = document.createElement('input');
  quantityInput.type = 'hidden';
  quantityInput.name = 'quantity';
  quantityInput.value = quantity;

  form.appendChild(productIdInput);
  form.appendChild(productIdField);
  form.appendChild(quantityInput);
  document.body.appendChild(form);
  form.submit();
}

// Update cart count display
function updateCartCount() {
  // This will be handled by PHP session on server side
  const cartBadge = document.getElementById('cart-badge');
  if (cartBadge) {
    // Fetch cart count via AJAX or update via PHP
    fetch('cart.php?get_count=1')
      .then((response) => response.json())
      .then((data) => {
        if (data.count > 0) {
          cartBadge.textContent = data.count > 99 ? '99+' : data.count;
          cartBadge.style.display = 'flex';
        } else {
          cartBadge.style.display = 'none';
        }
      })
      .catch(() => {
        cartBadge.style.display = 'none';
      });
  }
}

// Favorites Functionality
function initializeFavorites() {
  const favoriteButtons = document.querySelectorAll('.favorite-btn');

  favoriteButtons.forEach((button) => {
    button.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();

      const productId = this.dataset.id;
      const productCard = this.closest('.perfume-card');
      const productName =
        productCard.querySelector('.perfume-name').textContent;

      // Toggle favorite via server-side
      toggleFavorite(productId);
    });
  });
}

// Toggle favorite function
function toggleFavorite(productId) {
  // Create form to submit to favorites.php
  const form = document.createElement('form');
  form.method = 'POST';
  form.action = 'favorites.php';
  form.style.display = 'none';

  const productIdInput = document.createElement('input');
  productIdInput.type = 'hidden';
  productIdInput.name = 'toggle_favorite';
  productIdInput.value = '1';

  const productIdField = document.createElement('input');
  productIdField.type = 'hidden';
  productIdField.name = 'product_id';
  productIdField.value = productId;

  form.appendChild(productIdInput);
  form.appendChild(productIdField);
  document.body.appendChild(form);
  form.submit();
}

// Enhanced Quick View Functionality
function initializeQuickView() {
  const quickViewButtons = document.querySelectorAll('.quick-view-btn');

  quickViewButtons.forEach((button) => {
    button.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();

      const productCard = this.closest('.perfume-card');
      const productData = extractProductData(productCard);
      showEnhancedQuickViewModal(productData);
    });
  });
}

// Extract product data from card
function extractProductData(card) {
  return {
    id: card.dataset.id,
    name: card.querySelector('.perfume-name').textContent,
    type: card.querySelector('.perfume-type').textContent,
    scent: card.querySelector('.perfume-scent span').textContent,
    description: card.querySelector('.perfume-description').textContent,
    price: card.querySelector('.price').textContent,
    image: card.querySelector('.card-image img').src,
  };
}

// Enhanced Quick View Modal
function showEnhancedQuickViewModal(product) {
  // Remove existing modal if any
  const existingModal = document.querySelector('.quick-view-modal');
  if (existingModal) {
    existingModal.remove();
  }

  const modal = document.createElement('div');
  modal.className = 'quick-view-modal';
  modal.innerHTML = `
        <div class="modal-overlay" onclick="closeQuickViewModal()"></div>
        <div class="modal-content enhanced-quick-view">
            <button class="modal-close" onclick="closeQuickViewModal()">
                <i class="ri-close-line"></i>
            </button>
            
            <div class="quick-view-grid">
                <div class="quick-view-image-section">
                    <div class="main-image-container">
                        <img src="${product.image}" alt="${product.name}" class="main-product-image">
                        <div class="image-overlay">
                            <button class="zoom-btn" onclick="zoomImage('${product.image}')">
                                <i class="ri-search-line"></i>
                            </button>
                        </div>
                    </div>
                    <div class="thumbnail-container">
                        <img src="${product.image}" alt="${product.name}" class="thumbnail active">
                        <img src="images/flower.png" alt="Detail" class="thumbnail">
                    </div>
                </div>
                
                <div class="quick-view-details">
                    <div class="product-badge">${product.type}</div>
                    <h2 class="product-title">${product.name}</h2>
                    
                    <div class="product-meta">
                        <div class="scent-info">
                            <i class="ri-leaf-line"></i>
                            <span>${product.scent}</span>
                        </div>
                        <div class="rating">
                            <div class="stars">
                                <i class="ri-star-fill"></i>
                                <i class="ri-star-fill"></i>
                                <i class="ri-star-fill"></i>
                                <i class="ri-star-fill"></i>
                                <i class="ri-star-half-fill"></i>
                            </div>
                            <span class="rating-text">4.5 (128 reviews)</span>
                        </div>
                    </div>
                    
                    <div class="price-section">
                        <div class="current-price">${product.price}</div>
                        <div class="price-info">Free shipping on orders over Rp 500.000</div>
                    </div>
                    
                    <div class="description-section">
                        <h3>Product Description</h3>
                        <p class="product-description">${product.description}</p>
                        <div class="product-features">
                            <div class="feature-item">
                                <i class="ri-shield-check-line"></i>
                                <span>100% Original Product</span>
                            </div>
                            <div class="feature-item">
                                <i class="ri-vip-crown-line"></i>
                                <span>Premium Quality</span>
                            </div>
                            <div class="feature-item">
                                <i class="ri-gift-line"></i>
                                <span>Gift Wrapping Available</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="size-selector">
                        <h3>Choose Size</h3>
                        <div class="size-options">
                            <button class="size-btn" data-size="50ml">50 ML</button>
                            <button class="size-btn active" data-size="100ml">100 ML</button>
                            <button class="size-btn" data-size="150ml">150 ML</button>
                        </div>
                    </div>
                    
                    <div class="action-buttons">
                        <button class="add-to-cart-btn enhanced" data-id="${product.id}" onclick="addToCartFromModal('${product.id}', '${product.name}', '${product.price}', '${product.image}')">
                            <i class="ri-shopping-cart-line"></i>
                            Add to Cart
                        </button>
                        <button class="favorite-btn enhanced" data-id="${product.id}" onclick="toggleFavoriteFromModal(this, '${product.id}', '${product.name}')">
                            <i class="ri-heart-line"></i>
                        </button>
                        <button class="compare-btn">
                            <i class="ri-scales-3-line"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;

  document.body.appendChild(modal);

  // Add enhanced modal styles
  const enhancedModalStyles = `
        .enhanced-quick-view {
            max-width: 1200px;
            width: 95%;
            max-height: 95vh;
        }
        
        .quick-view-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            height: 100%;
        }
        
        .quick-view-image-section {
            position: relative;
        }
        
        .main-image-container {
            position: relative;
            border-radius: 15px;
            overflow: hidden;
            background: linear-gradient(135deg, #f5cdcd 0%, #cc7f7f 100%);
            aspect-ratio: 1;
        }
        
        .main-product-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .image-overlay {
            position: absolute;
            top: 15px;
            right: 15px;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .main-image-container:hover .image-overlay {
            opacity: 1;
        }
        
        .zoom-btn {
            background: var(--white);
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            transition: transform 0.3s ease;
        }
        
        .zoom-btn:hover {
            transform: scale(1.1);
        }
        
        .thumbnail-container {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .thumbnail {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 10px;
            cursor: pointer;
            opacity: 0.6;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .thumbnail.active,
        .thumbnail:hover {
            opacity: 1;
            border-color: var(--btn-color);
        }
        
        .product-badge {
            display: inline-block;
            background: linear-gradient(135deg, #f5cdcd 0%, #cc7f7f 100%);
            color: var(--white);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 15px;
        }
        
        .product-title {
            font-family: var(--header-font);
            font-size: 2.2rem;
            color: var(--text-dark);
            margin-bottom: 20px;
            line-height: 1.2;
        }
        
        .product-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }
        
        .scent-info {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--light-text);
            font-weight: 500;
        }
        
        .scent-info i {
            color: var(--btn-color);
            font-size: 1.2rem;
        }
        
        .rating {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .stars {
            display: flex;
            gap: 2px;
        }
        
        .stars i {
            color: #ffc107;
            font-size: 1rem;
        }
        
        .rating-text {
            color: var(--light-text);
            font-size: 0.9rem;
        }
        
        .price-section {
            margin-bottom: 30px;
        }
        
        .current-price {
            font-family: var(--header-font);
            font-size: 2rem;
            color: var(--hover-color);
            margin-bottom: 5px;
        }
        
        .price-info {
            color: var(--light-text);
            font-size: 0.9rem;
        }
        
        .description-section {
            margin-bottom: 30px;
        }
        
        .description-section h3 {
            font-family: var(--header-font);
            font-size: 1.3rem;
            color: var(--text-dark);
            margin-bottom: 15px;
        }
        
        .product-description {
            color: var(--light-text);
            line-height: 1.6;
            margin-bottom: 20px;
        }
        
        .product-features {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .feature-item {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--light-text);
            font-size: 0.9rem;
        }
        
        .feature-item i {
            color: var(--btn-color);
            font-size: 1.1rem;
        }
        
        .size-selector {
            margin-bottom: 30px;
        }
        
        .size-selector h3 {
            font-family: var(--header-font);
            font-size: 1.3rem;
            color: var(--text-dark);
            margin-bottom: 15px;
        }
        
        .size-options {
            display: flex;
            gap: 15px;
        }
        
        .size-btn {
            padding: 10px 20px;
            border: 2px solid #e0e0e0;
            background: var(--white);
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        
        .size-btn:hover,
        .size-btn.active {
            border-color: var(--btn-color);
            background: var(--btn-color);
            color: var(--white);
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .add-to-cart-btn.enhanced {
            flex: 1;
            padding: 15px 25px;
            background: linear-gradient(135deg, var(--btn-color) 0%, var(--hover-color) 100%);
            color: var(--white);
            border: none;
            border-radius: 30px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .add-to-cart-btn.enhanced:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(205, 127, 127, 0.3);
        }
        
        .favorite-btn.enhanced,
        .compare-btn {
            width: 50px;
            height: 50px;
            border: 2px solid #e0e0e0;
            background: var(--white);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .favorite-btn.enhanced:hover,
        .compare-btn:hover {
            border-color: var(--btn-color);
            color: var(--btn-color);
            transform: scale(1.1);
        }
        
        .favorite-btn.enhanced.active {
            background: var(--btn-color);
            border-color: var(--btn-color);
            color: var(--white);
        }
        
        @media (max-width: 768px) {
            .quick-view-grid {
                grid-template-columns: 1fr;
                gap: 30px;
            }
            
            .product-title {
                font-size: 1.8rem;
            }
            
            .product-meta {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .action-buttons {
                flex-wrap: wrap;
            }
            
            .add-to-cart-btn.enhanced {
                width: 100%;
                order: -1;
            }
        }
    `;

  // Add styles to head
  if (!document.querySelector('#enhanced-modal-styles')) {
    const styleSheet = document.createElement('style');
    styleSheet.id = 'enhanced-modal-styles';
    styleSheet.textContent = enhancedModalStyles;
    document.head.appendChild(styleSheet);
  }
}

// Close quick view modal
function closeQuickViewModal() {
  const modal = document.querySelector('.quick-view-modal');
  if (modal) {
    modal.remove();
  }
}

// Add to cart from modal
function addToCartFromModal(
  productId,
  productName,
  productPrice,
  productImage
) {
  // Use the same addToCart function
  addToCart(productId, 1);
  closeQuickViewModal();
}

// Toggle favorite from modal
function toggleFavoriteFromModal(button, productId, productName) {
  // Use the same toggleFavorite function
  toggleFavorite(productId);

  // Update button state temporarily
  const currentIcon = button.querySelector('i');
  if (currentIcon.classList.contains('ri-heart-line')) {
    currentIcon.classList.remove('ri-heart-line');
    currentIcon.classList.add('ri-heart-fill');
    button.classList.add('active');
  } else {
    currentIcon.classList.remove('ri-heart-fill');
    currentIcon.classList.add('ri-heart-line');
    button.classList.remove('active');
  }
}

// Zoom image function
function zoomImage(imageSrc) {
  const zoomModal = document.createElement('div');
  zoomModal.className = 'zoom-modal';
  zoomModal.innerHTML = `
        <div class="zoom-overlay" onclick="closeZoomModal()"></div>
        <div class="zoom-content">
            <img src="${imageSrc}" alt="Zoomed product">
            <button class="zoom-close" onclick="closeZoomModal()">
                <i class="ri-close-line"></i>
            </button>
        </div>
    `;

  document.body.appendChild(zoomModal);

  // Add zoom modal styles
  if (!document.querySelector('#zoom-modal-styles')) {
    const zoomStyles = document.createElement('style');
    zoomStyles.id = 'zoom-modal-styles';
    zoomStyles.textContent = `
            .zoom-modal {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                z-index: 10000;
                display: flex;
                align-items: center;
                justify-content: center;
                animation: fadeIn 0.3s ease;
            }
            
            .zoom-overlay {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.9);
            }
            
            .zoom-content {
                position: relative;
                max-width: 90%;
                max-height: 90%;
                animation: zoomIn 0.3s ease;
            }
            
            .zoom-content img {
                width: 100%;
                height: 100%;
                object-fit: contain;
                border-radius: 10px;
            }
            
            .zoom-close {
                position: absolute;
                top: -50px;
                right: 0;
                background: var(--white);
                border: none;
                width: 40px;
                height: 40px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                font-size: 1.2rem;
                color: var(--text-dark);
            }
            
            @keyframes zoomIn {
                from {
                    opacity: 0;
                    transform: scale(0.8);
                }
                to {
                    opacity: 1;
                    transform: scale(1);
                }
            }
        `;
    document.head.appendChild(zoomStyles);
  }
}

// Close zoom modal
function closeZoomModal() {
  const modal = document.querySelector('.zoom-modal');
  if (modal) {
    modal.remove();
  }
}

// User Authentication
function initializeUserAuth() {
  // Handle login/register modal switching
  const loginBtn = document.querySelector('.auth-btn');
  if (loginBtn) {
    loginBtn.addEventListener('click', showLoginModal);
  }

  // Close modals when clicking outside
  document.addEventListener('click', function (e) {
    if (e.target.classList.contains('modal-overlay')) {
      closeAllModals();
    }
  });

  // Close modals with Escape key
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      closeAllModals();
      closeQuickViewModal();
      closeZoomModal();
    }
  });
}

function closeAllModals() {
  const modals = document.querySelectorAll('.modal');
  modals.forEach((modal) => (modal.style.display = 'none'));
}

function showLoginModal() {
  // Redirect to login page
  window.location.href = 'auth/login.php';
}

function showLoginRequiredModal() {
  // Create and show login required modal
  const existingModal = document.querySelector('.login-required-modal');
  if (existingModal) {
    existingModal.remove();
  }

  const modal = document.createElement('div');
  modal.className = 'login-required-modal';
  modal.innerHTML = `
    <div class="modal-overlay" onclick="closeLoginRequiredModal()"></div>
    <div class="modal-content login-modal">
      <button class="modal-close" onclick="closeLoginRequiredModal()">
        <i class="ri-close-line"></i>
      </button>
      
      <div class="login-modal-content">
        <div class="login-icon">
          <i class="ri-user-line"></i>
        </div>
        <h3>Anda Harus Login Terlebih Dahulu</h3>
        <p>Apakah Anda Sudah Memiliki Akun?</p>
        
        <div class="login-modal-buttons">
          <button class="btn-primary" onclick="goToLogin()">
            Sudah
          </button>
          <button class="btn-secondary" onclick="goToRegister()">
            Buat Akun
          </button>
        </div>
      </div>
    </div>
  `;

  document.body.appendChild(modal);

  // Add modal styles
  if (!document.querySelector('#login-modal-styles')) {
    const modalStyles = document.createElement('style');
    modalStyles.id = 'login-modal-styles';
    modalStyles.textContent = `
      .login-required-modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 10000;
        display: flex;
        align-items: center;
        justify-content: center;
        animation: fadeIn 0.3s ease;
      }
      
      .login-modal {
        max-width: 400px;
        width: 90%;
        background: white;
        border-radius: 15px;
        padding: 30px;
        text-align: center;
        animation: slideInUp 0.3s ease;
      }
      
      .login-modal-content h3 {
        font-family: var(--header-font);
        font-size: 1.3rem;
        color: var(--text-dark);
        margin-bottom: 10px;
      }
      
      .login-modal-content p {
        color: var(--light-text);
        margin-bottom: 25px;
      }
      
      .login-icon {
        width: 60px;
        height: 60px;
        background: linear-gradient(135deg, var(--btn-color) 0%, var(--hover-color) 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 20px;
      }
      
      .login-icon i {
        font-size: 1.5rem;
        color: white;
      }
      
      .login-modal-buttons {
        display: flex;
        gap: 15px;
        justify-content: center;
      }
      
      .login-modal-buttons button {
        padding: 12px 25px;
        border-radius: 25px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
      }
      
      @keyframes slideInUp {
        from {
          opacity: 0;
          transform: translateY(30px);
        }
        to {
          opacity: 1;
          transform: translateY(0);
        }
      }
    `;
    document.head.appendChild(modalStyles);
  }
}

function closeLoginRequiredModal() {
  const modal = document.querySelector('.login-required-modal');
  if (modal) {
    modal.remove();
  }
}

function goToLogin() {
  window.location.href = 'auth/login.php';
}

function goToRegister() {
  window.location.href = 'auth/register.php';
}

// Navigation functionality
function initializeNavigation() {
  // Smooth scrolling for navigation links
  const navLinks = document.querySelectorAll('a[href^="#"]');

  navLinks.forEach((link) => {
    link.addEventListener('click', function (e) {
      e.preventDefault();
      const targetId = this.getAttribute('href');
      const targetSection = document.querySelector(targetId);

      if (targetSection) {
        targetSection.scrollIntoView({
          behavior: 'smooth',
          block: 'start',
        });
      }
    });
  });
}

// Show notification
function showNotification(message, type = 'info') {
  // Remove existing notification if any
  const existingNotification = document.querySelector('.notification');
  if (existingNotification) {
    existingNotification.remove();
  }

  const notification = document.createElement('div');
  notification.className = `notification notification-${type}`;
  notification.textContent = message;

  // Notification styles
  notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${
          type === 'success'
            ? 'var(--btn-color)'
            : type === 'error'
            ? '#e74c3c'
            : '#3498db'
        };
        color: white;
        padding: 15px 20px;
        border-radius: 8px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        z-index: 10000;
        animation: slideInRight 0.3s ease;
        max-width: 300px;
        font-weight: 500;
    `;

  document.body.appendChild(notification);

  // Auto remove after 3 seconds
  setTimeout(() => {
    notification.style.animation = 'slideOutRight 0.3s ease';
    setTimeout(() => {
      if (notification.parentNode) {
        notification.remove();
      }
    }, 300);
  }, 3000);
}

// Animate add to cart button
function animateAddToCart(button) {
  button.style.transform = 'scale(0.95)';
  button.innerHTML = '<i class="ri-check-line"></i> Added!';

  setTimeout(() => {
    button.style.transform = '';
    button.innerHTML = '<i class="ri-shopping-cart-line"></i> Add to Cart';
  }, 1500);
}

// Add notification animations to head
if (!document.querySelector('#notification-animations')) {
  const animationStyles = document.createElement('style');
  animationStyles.id = 'notification-animations';
  animationStyles.textContent = `
        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(100px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        @keyframes slideOutRight {
            from {
                opacity: 1;
                transform: translateX(0);
            }
            to {
                opacity: 0;
                transform: translateX(100px);
            }
        }
    `;
  document.head.appendChild(animationStyles);
}
