-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Nov 26, 2025 at 03:48 AM
-- Server version: 5.7.39
-- PHP Version: 8.1.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `arde_lux`
--

-- --------------------------------------------------------

--
-- Table structure for table `favorites`
--

CREATE TABLE `favorites` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `order_number` varchar(50) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `shipping_fee` decimal(10,2) DEFAULT '0.00',
  `status` enum('pending','processing','shipped','delivered','cancelled') DEFAULT 'pending',
  `shipping_address` text NOT NULL,
  `notes` text,
  `payment_method` varchar(50) DEFAULT 'cod',
  `payment_status` enum('pending','paid','failed') DEFAULT 'pending',
  `order_status` enum('pending','processing','shipped','delivered','cancelled') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `user_id`, `order_number`, `total_amount`, `shipping_fee`, `status`, `shipping_address`, `notes`, `payment_method`, `payment_status`, `order_status`, `created_at`, `updated_at`) VALUES
(1, 2, 'RDL-101125001', '1350000.00', '0.00', 'processing', 'Jl. Sudirman No. 123, Jakarta Selatan, DKI Jakarta 12190', NULL, 'cod', 'pending', 'pending', '2025-11-09 21:02:53', '2025-11-11 08:40:07'),
(2, 2, 'RDL-151125188', '3530000.00', '0.00', 'pending', 'Jl. Sudirman No. 123, Jakarta Selatan, DKI Jakarta 12190', NULL, 'cod', 'pending', 'pending', '2025-11-14 20:34:38', '2025-11-23 13:29:12'),
(4, 1, 'ORD-20251123-9571', '500000.00', '50000.00', 'pending', 'Test Address', 'Test notes', 'bank_transfer', 'pending', 'pending', '2025-11-23 14:03:05', '2025-11-23 14:03:05'),
(6, 1, 'ORD-20251123-2088', '1460000.00', '50000.00', 'pending', 'Jl. Test Address No. 123, Jakarta', 'Test order please', 'bank_transfer', 'pending', 'pending', '2025-11-23 14:04:10', '2025-11-23 14:04:10'),
(8, 1, 'RDL-231125003', '605000.00', '50000.00', 'pending', 'Jl. Jakarta No. 123', 'Test checkout with new format', 'bank_transfer', 'pending', 'pending', '2025-11-23 14:08:55', '2025-11-23 14:08:55'),
(9, 1, 'RDL-231125004', '605000.00', '50000.00', 'pending', 'Jl. Test Address No. 123, Jakarta', 'Debug test order', 'bank_transfer', 'pending', 'pending', '2025-11-23 14:12:02', '2025-11-23 14:12:02'),
(10, 2, 'RDL-231125005', '605000.00', '50000.00', 'pending', 'Jl. Test CLI, Jakarta Selatan', 'CLI test order', 'bank_transfer', 'pending', 'pending', '2025-11-23 14:22:53', '2025-11-23 14:22:53');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_name` varchar(200) DEFAULT NULL,
  `product_price` decimal(10,2) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `product_name`, `product_price`, `quantity`, `price`, `subtotal`) VALUES
(2, 1, 3, 'Woody Mystery', '550000.00', 1, '550000.00', '550000.00'),
(4, 2, 6, 'Spice Adventure', '380000.00', 5, '380000.00', '1900000.00'),
(5, 2, 4, 'Ocean Breeze', '280000.00', 1, '280000.00', '280000.00'),
(9, 6, 3, 'Woody Mystery', '550000.00', 1, '550000.00', '550000.00'),
(10, 6, 5, 'Sweet Vanilla', '420000.00', 2, '420000.00', '840000.00'),
(11, 6, NULL, 'Bubble Wrap Tambahan', '5000.00', 1, '5000.00', '5000.00'),
(12, 6, NULL, 'Packing Kayu', '15000.00', 1, '15000.00', '15000.00'),
(13, 8, 3, 'Woody Mystery', '550000.00', 1, '550000.00', '550000.00'),
(14, 8, NULL, 'Bubble Wrap Tambahan', '5000.00', 1, '5000.00', '5000.00'),
(15, 9, 3, 'Woody Mystery', '550000.00', 1, '550000.00', '550000.00'),
(16, 9, NULL, 'Bubble Wrap Tambahan', '5000.00', 1, '5000.00', '5000.00'),
(17, 10, 3, 'Woody Mystery', '550000.00', 1, '550000.00', '550000.00'),
(18, 10, NULL, 'Bubble Wrap Tambahan', '5000.00', 1, '5000.00', '5000.00');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `type` varchar(50) NOT NULL,
  `scent` varchar(50) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `description` text,
  `image` varchar(255) DEFAULT 'images/perfume.png',
  `stock` int(11) DEFAULT '100',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `status` enum('active','inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `type`, `scent`, `price`, `description`, `image`, `stock`, `created_at`, `updated_at`, `status`) VALUES
(3, 'Woody Mystery', 'Eau de Parfum', 'Woody', '550000.00', 'Misteri hutan dalam botol.', 'images/perfume.png', 100, '2025-11-10 02:14:36', '2025-11-23 09:12:28', 'active'),
(4, 'Ocean Breeze', 'Eau de Cologne', 'Marine', '280000.00', 'Kesegaran ombak laut.', 'images/perfume.png', 990, '2025-11-10 02:14:36', '2025-11-23 11:38:42', 'inactive'),
(5, 'Sweet Vanilla', 'Eau de Parfum', 'Gourmand', '420000.00', 'Manisnya vanila murni.', 'images/perfume.png', 100, '2025-11-10 02:14:36', '2025-11-23 12:09:17', 'active'),
(6, 'Spice Adventure', 'Eau de Toilette', 'Spicy', '380000.00', 'Petualangan rempah eksotis.', 'images/perfume.png', 95, '2025-11-10 02:14:36', '2025-11-23 11:38:42', 'inactive'),
(7, 'Floral adsdadadsadadsad', 'Extrait de Parfume', 'Bergamot', '450000.00', 'asadas', 'images/products/product_69230999243614.46866128.jpg', 10011, '2025-11-23 06:55:44', '2025-11-23 13:36:15', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `product_branch`
--

CREATE TABLE `product_branch` (
  `id` int(11) NOT NULL,
  `branch_id` varchar(20) NOT NULL,
  `store_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `stock` int(11) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `product_branch`
--

INSERT INTO `product_branch` (`id`, `branch_id`, `store_id`, `product_id`, `stock`, `created_at`, `updated_at`) VALUES
(5, 'BR003', 3, 7, 1, '2025-11-23 09:10:05', '2025-11-23 09:10:05'),
(6, 'BR003', 3, 4, 12, '2025-11-23 09:10:05', '2025-11-23 09:10:05'),
(7, 'BR003', 3, 6, 8, '2025-11-23 09:10:05', '2025-11-23 09:10:05'),
(8, 'BR003', 3, 5, 2, '2025-11-23 09:10:05', '2025-11-23 09:10:05'),
(9, 'BR003', 3, 3, 2, '2025-11-23 09:10:05', '2025-11-23 09:10:05'),
(10, 'BR002', 2, 7, 22, '2025-11-23 09:14:47', '2025-11-23 09:14:47'),
(11, 'BR002', 2, 6, 13, '2025-11-23 09:14:47', '2025-11-23 11:27:10'),
(12, 'BR002', 2, 5, 222, '2025-11-23 09:14:47', '2025-11-23 11:10:39'),
(13, 'BR002', 2, 3, 8, '2025-11-23 09:14:47', '2025-11-23 09:14:47');

-- --------------------------------------------------------

--
-- Table structure for table `scents`
--

CREATE TABLE `scents` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `scents`
--

INSERT INTO `scents` (`id`, `name`, `description`, `created_at`) VALUES
(1, 'Floral', 'Aroma bunga yang lembut dan feminin', '2025-11-23 04:39:35'),
(2, 'Woody', 'Aroma kayu yang hangat dan maskulin', '2025-11-23 04:39:35'),
(3, 'Citrus', 'Aroma jeruk yang segar dan energik', '2025-11-23 04:39:35'),
(4, 'Marine', 'Aroma laut yang segar', '2025-11-23 04:39:35'),
(5, 'Gourmand', 'Aroma manis seperti makanan penutup', '2025-11-23 04:39:35'),
(6, 'Spicy', 'Aroma rempah yang tajam dan eksotis', '2025-11-23 04:39:35'),
(7, 'Bergamot', 'Bergamot', '2025-11-23 05:00:17');

-- --------------------------------------------------------

--
-- Table structure for table `stores`
--

CREATE TABLE `stores` (
  `id` int(11) NOT NULL,
  `branch_id` varchar(20) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `address` text NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `manager_name` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('active','inactive') DEFAULT 'active',
  `total_stock` int(11) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `stores`
--

INSERT INTO `stores` (`id`, `branch_id`, `name`, `address`, `phone`, `manager_name`, `created_at`, `status`, `total_stock`) VALUES
(1, 'BR001', 'Toko Pusat', 'Jl. Sudirman No. 123, Jakarta Selatan', '021-1234567', 'Budi Santoso', '2025-11-23 04:39:36', 'active', 0),
(2, 'BR002', 'Cabang 1', 'Jl. ME Wira', '0895347216499', 'UDIN KURNIAWAN', '2025-11-23 07:51:51', 'active', 0),
(3, 'BR003', 'Cabang 2', 'JALAN KAMPUNG DUREN', '0895347216499', 'Administrator', '2025-11-23 08:18:46', 'active', 0);

-- --------------------------------------------------------

--
-- Table structure for table `types`
--

CREATE TABLE `types` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `concentration` varchar(50) DEFAULT NULL,
  `description` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `types`
--

INSERT INTO `types` (`id`, `name`, `concentration`, `description`, `created_at`) VALUES
(1, 'Eau de Parfum', '15-20%', 'Konsentrasi minyak wangi tinggi, tahan lama.', '2025-11-23 04:39:35'),
(2, 'Eau de Toilette', '5-15%', 'Konsentrasi sedang, cocok untuk sehari-hari.', '2025-11-23 04:39:35'),
(3, 'Eau de Cologne', '2-4%', 'Konsentrasi rendah, ringan dan segar.', '2025-11-23 04:39:35'),
(4, 'Extrait de Parfume', NULL, 'KENCENG', '2025-11-23 05:00:35');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text,
  `role` enum('user','admin','superadmin','karyawan') DEFAULT 'user',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `status` enum('active','pending','rejected') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `full_name`, `phone`, `address`, `role`, `created_at`, `updated_at`, `status`) VALUES
(1, 'admin', 'admin@ardeliana.com', '$2y$10$HXVGEXcvJoeWbrQRpVOsv.aCH13OphXNP4RKyQ3FK7pmtNG8DVax6', 'Administrator', NULL, NULL, 'admin', '2025-11-10 02:14:36', '2025-11-10 02:14:36', 'active'),
(2, 'customer', 'customer@ardeliana.com', '$2y$10$HewlHUAkUHHfjoT0NrhbMevA3XY4shqUyyxIG7pEWu./TQg6hyh3i', 'Testing', '08123456789', 'Jl. Sudirman No. 123, Jakarta Selatan, DKI Jakarta 12190', 'user', '2025-11-10 04:00:04', '2025-11-23 08:25:12', 'active'),
(4, 'superadmin', 'superadmin@ardeliana.com', '$2y$10$vo3Yfi25iiIF9GROF3HJfevNkJJQNZmzg1aGc2En9GlbNC7rCTz2.', 'Super Administrator', NULL, NULL, 'superadmin', '2025-11-11 08:51:04', '2025-11-11 08:51:04', 'active'),
(5, 'cabang1', 'cabang1@gmail.com', '$2y$10$lQGk4QiEbqnLO/RGLheXye5LDHm/7L2tN3VIIzNVpAZTNF4/scD0u', 'UDIN KURNIAWAN', '123123123', 'Jl. ME WIRA', 'karyawan', '2025-11-23 08:27:08', '2025-11-23 09:14:24', 'active'),
(6, 'testing', 'testing@mail.com', '$2y$10$Cc1lzP7qa0Cvj.cafRAW.ekFjV7z/NkPCD8vJRy4QZJrvjG9Z9ALG', 'TESTING USER', '09876567890', 'Jl. ME WIRA', 'user', '2025-11-23 13:32:06', '2025-11-23 13:32:06', 'active');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `favorites`
--
ALTER TABLE `favorites`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_favorite` (`user_id`,`product_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `product_branch`
--
ALTER TABLE `product_branch`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_branch_product` (`branch_id`,`product_id`),
  ADD KEY `store_id` (`store_id`),
  ADD KEY `idx_branch_id` (`branch_id`),
  ADD KEY `idx_product_id` (`product_id`);

--
-- Indexes for table `scents`
--
ALTER TABLE `scents`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `stores`
--
ALTER TABLE `stores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `branch_id` (`branch_id`);

--
-- Indexes for table `types`
--
ALTER TABLE `types`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `favorites`
--
ALTER TABLE `favorites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `product_branch`
--
ALTER TABLE `product_branch`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `scents`
--
ALTER TABLE `scents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `stores`
--
ALTER TABLE `stores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `types`
--
ALTER TABLE `types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `favorites`
--
ALTER TABLE `favorites`
  ADD CONSTRAINT `favorites_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `favorites_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `product_branch`
--
ALTER TABLE `product_branch`
  ADD CONSTRAINT `product_branch_ibfk_1` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_branch_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
