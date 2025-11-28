# Ardéliana Lux - Premium Parfume E-Commerce Website

A sophisticated E-Commerce website for premium perfumes built with PHP, featuring modern design and interactive functionality.

## Features Implemented

### 🌟 Core Features

- **Hero Section** with animated product showcase
- **Product Cards Section** with 6 premium perfumes
- **Responsive Design** that works on all devices
- **Indonesian Currency (Rupiah)** formatting
- **Professional Branding** with "Ardéliana Lux" theme

### 🛍️ E-Commerce Functionality

- **Shopping Cart** with localStorage persistence
- **Favorites/Wishlist** system
- **Quick View** modal for product details
- **Cart Badge** with item count
- **Add to Cart** animations and notifications

### 🔍 Product Features

- **Filter by Type**: Eau de Parfum, Eau de Toilette, Eau de Cologne
- **Filter by Scent**: Floral, Citrus, Woody, Marine, Gourmand, Spicy
- **Sort Options**: By name, price (low to high), price (high to low)
- **Product Cards** with:
  - Perfume type badge
  - Product name and description
  - Scent type with icon
  - Price in Indonesian Rupiah
  - Hover effects and animations

### 🎨 Design Features

- **Modern UI/UX** with gradient color scheme
- **Smooth Animations** and transitions
- **Card Hover Effects** with scale and shadow
- **Mobile-Responsive** grid layout
- **Professional Typography** with Google Fonts

## Technology Stack

- **Backend**: PHP 8.1+
- **Frontend**: HTML5, CSS3, JavaScript (ES6+)
- **Styling**: CSS Grid, Flexbox, CSS Variables
- **Icons**: Remix Icons
- **Fonts**: Google Fonts (Bebas Neue, Montserrat)
- **Storage**: LocalStorage for cart and favorites

## File Structure

```
part-1/
├── index.php              # Main PHP file with product data
├── style.css              # Original hero section styles
├── perfume-cards.css       # Product cards and e-commerce styles
├── script.js              # Interactive JavaScript functionality
├── images/
│   ├── perfume.png         # Product image
│   ├── flower.png         # Decorative image
│   └── icon.png          # Size icon
└── README.md              # This documentation
```

## Products Included

1. **Floral Romance** - Eau de Parfum (Rp 450,000)
2. **Citrus Fresh** - Eau de Toilette (Rp 350,000)
3. **Woody Mystery** - Eau de Parfum (Rp 550,000)
4. **Ocean Breeze** - Eau de Cologne (Rp 280,000)
5. **Sweet Vanilla** - Eau de Parfum (Rp 420,000)
6. **Spice Adventure** - Eau de Toilette (Rp 380,000)

## Getting Started

### Prerequisites

- PHP 8.1 or higher
- Modern web browser
- Local development server (optional)

### Installation

1. Clone or download the project files
2. Navigate to the `part-1` directory
3. Start the PHP development server:
   ```bash
   cd part-1
   php -S localhost:8000
   ```
4. Open your browser and visit: `http://localhost:8000`

### Alternative Setup

You can also use any local server like XAMPP, WAMP, or Laragon:

- Place the `part-1` folder in your server's web directory
- Access via your browser (e.g., `http://localhost/parfume/part-1`)

## Features Explained

### Shopping Cart

- Click "Add to Cart" on any product
- Cart items are saved in browser localStorage
- Cart badge shows total item count
- Notifications confirm additions

### Favorites

- Click the heart icon to add/remove favorites
- Favorites persist in localStorage
- Visual feedback with filled/empty heart

### Quick View

- Click the eye icon for detailed product view
- Modal shows larger image and full details
- Add to cart and favorites available from modal

### Filtering & Sorting

- Filter products by type or scent category
- Sort by name or price
- Real-time updates without page refresh
- "No results" message when filters return empty

### Responsive Design

- Desktop: 3-column grid layout
- Tablet: 2-column grid layout
- Mobile: Single column layout
- Touch-friendly buttons and interactions

## Customization

### Adding New Products

Edit the `$perfumes` array in `index.php`:

```php
[
    'id' => 7,
    'name' => 'New Perfume',
    'type' => 'Eau de Parfum',
    'scent' => 'Floral',
    'price' => 500000,
    'image' => 'images/perfume.png',
    'description' => 'Product description here'
]
```

### Changing Colors

Edit CSS variables in `style.css`:

```css
:root {
  --gradient-color: linear-gradient(
    180deg,
    #f5cdcd 0%,
    #cc7f7f 46%,
    #5d2726 100%
  );
  --bg-color: #fdecec;
  --text-dark: #3e2524;
  --light-text: #563d3d;
  --hover-color: #a74c4c;
  --btn-color: #cc7f7f;
}
```

## Future Enhancements

- [ ] Database integration (MySQL)
- [ ] User authentication system
- [ ] Product search functionality
- [ ] Shopping cart page
- [ ] Checkout and payment integration
- [ ] Product reviews and ratings
- [ ] Admin panel for product management
- [ ] Multi-language support

## Browser Support

- Chrome 60+
- Firefox 55+
- Safari 12+
- Edge 79+

## License

This project is for educational and demonstration purposes.

---

**Ardéliana Lux** - Premium Parfume Collection
_Developed with ❤️ for modern e-commerce experiences_
