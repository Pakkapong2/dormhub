-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 22, 2026 at 07:32 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `dorm_payment_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `booking_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `booking_date` datetime DEFAULT current_timestamp(),
  `move_in_date` date NOT NULL COMMENT 'วันที่แจ้งจะเข้าอยู่',
  `booking_fee` decimal(10,2) DEFAULT 0.00 COMMENT 'เงินมัดจำการจอง',
  `status` enum('pending','confirmed','cancelled','completed') DEFAULT 'pending',
  `slip_image` varchar(255) DEFAULT NULL COMMENT 'สลิปโอนเงินจอง'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `contracts`
--

CREATE TABLE `contracts` (
  `contract_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `deposit_amount` decimal(10,2) DEFAULT 0.00 COMMENT 'เงินประกัน',
  `status` enum('active','closed','terminated') DEFAULT 'active',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `extra_charges`
--

CREATE TABLE `extra_charges` (
  `charge_id` int(11) NOT NULL,
  `meter_id` int(11) NOT NULL,
  `description` varchar(255) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `type` enum('addition','deduction') DEFAULT 'addition' COMMENT 'บวกเพิ่ม หรือ ส่วนลด'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `maintenance`
--

CREATE TABLE `maintenance` (
  `maintenance_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL COMMENT 'หัวข้อเรื่องแจ้งซ่อม',
  `description` text DEFAULT NULL,
  `repair_image` varchar(255) DEFAULT NULL,
  `admin_note` text DEFAULT NULL,
  `status` enum('pending','in_progress','fixed') DEFAULT 'pending',
  `reported_at` datetime DEFAULT current_timestamp(),
  `fixed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `meters`
--

CREATE TABLE `meters` (
  `meter_id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `base_rent_at_time` decimal(10,2) DEFAULT 0.00,
  `billing_month` varchar(7) NOT NULL,
  `prev_water_meter` int(11) DEFAULT 0,
  `curr_water_meter` int(11) DEFAULT 0,
  `prev_electric_meter` int(11) DEFAULT 0,
  `curr_electric_meter` int(11) DEFAULT 0,
  `water_total` decimal(10,2) DEFAULT 0.00,
  `water_rate_at_time` decimal(10,2) DEFAULT 0.00,
  `electric_total` decimal(10,2) DEFAULT 0.00,
  `electric_rate_at_time` decimal(10,2) DEFAULT 0.00,
  `total_amount` decimal(10,2) DEFAULT 0.00,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `meters`
--

INSERT INTO `meters` (`meter_id`, `room_id`, `base_rent_at_time`, `billing_month`, `prev_water_meter`, `curr_water_meter`, `prev_electric_meter`, `curr_electric_meter`, `water_total`, `water_rate_at_time`, `electric_total`, `electric_rate_at_time`, `total_amount`, `created_at`) VALUES
(7, 15, 0.00, '2026-01', 0, 10, 0, 10, 180.00, 0.00, 70.00, 0.00, 250.00, '2026-01-22 17:03:17');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `meter_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_date` datetime DEFAULT current_timestamp(),
  `payment_method` enum('cash','bank','online') DEFAULT 'bank',
  `slip_image` varchar(255) DEFAULT NULL,
  `status` enum('pending','waiting','approved','rejected') DEFAULT 'pending',
  `reject_reason` text DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rooms`
--

CREATE TABLE `rooms` (
  `room_id` int(11) NOT NULL,
  `room_number` varchar(10) NOT NULL,
  `base_rent` decimal(10,2) NOT NULL DEFAULT 0.00,
  `floor` varchar(10) DEFAULT NULL,
  `price_per_month` decimal(10,2) DEFAULT 0.00,
  `status` enum('occupied','available','maintenance','booked') DEFAULT 'available',
  `room_image` varchar(255) DEFAULT 'default_room.jpg',
  `description` text DEFAULT NULL,
  `amenities` varchar(255) DEFAULT NULL,
  `latest_water_meter` int(11) DEFAULT 0,
  `latest_electric_meter` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `rooms`
--

INSERT INTO `rooms` (`room_id`, `room_number`, `base_rent`, `floor`, `price_per_month`, `status`, `room_image`, `description`, `amenities`, `latest_water_meter`, `latest_electric_meter`) VALUES
(15, '101', 2000.00, NULL, 0.00, 'occupied', 'room_1766562654.jpg', '', '', 0, 0),
(16, '102', 2000.00, NULL, 0.00, 'occupied', 'room_1766729455.webp', '', 'เก้าอี้ โต๊ะ โซฟา', 0, 0),
(17, '103', 2000.00, NULL, 0.00, 'available', 'room_1767034716.webp', '', '', 0, 0),
(18, '104', 2000.00, NULL, 0.00, 'available', 'room_1767034720.webp', '', '', 0, 0),
(19, '105', 2000.00, NULL, 0.00, 'available', 'room_1767034724.webp', '', '', 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `setting_id` int(11) NOT NULL,
  `water_rate` decimal(10,2) DEFAULT 0.00,
  `electric_rate` decimal(10,2) DEFAULT 0.00,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`setting_id`, `water_rate`, `electric_rate`, `updated_at`) VALUES
(1, 18.00, 7.00, '2025-12-29 23:55:28');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `fullname` varchar(100) DEFAULT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `room_id` int(11) DEFAULT NULL,
  `role` enum('admin','user','viewer') DEFAULT 'viewer',
  `line_user_id` varchar(100) DEFAULT NULL,
  `line_display_name` varchar(100) DEFAULT NULL,
  `line_picture_url` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password`, `fullname`, `phone`, `email`, `room_id`, `role`, `line_user_id`, `line_display_name`, `line_picture_url`, `created_at`) VALUES
(4, '0111111111', '$2y$10$EjIexvdK766BtFqIxmCNjOe.AcZdGUL0lx88TqMIZV2PuPIuA01BG', 'arisa', '0111111111', NULL, 15, 'user', NULL, NULL, NULL, '2025-12-25 13:28:33'),
(5, '1111111111', '$2y$10$mkkW5qnnqj1gaBHe3q2AHe3nZ7l2v6meK8DQHQg5.yPoR1JPFvo/G', 'pakkapong', '1111111111', NULL, 16, 'user', NULL, NULL, NULL, '2025-12-30 01:59:01'),
(8, 'admin01', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', NULL, NULL, NULL, 'admin', NULL, NULL, NULL, '2026-01-23 00:19:13'),
(9, 'U99b7c3faa96d7b6a11d7837f12ea54c6', NULL, 'Pakkapong', NULL, NULL, NULL, 'viewer', 'U99b7c3faa96d7b6a11d7837f12ea54c6', NULL, 'https://profile.line-scdn.net/0hUTuvuQhdCntYDxvOpMR0BChfCRF7flNpJDxNGm8KB0I2bRl4c2hDGG4IABxhN00uI2hFHG8LABhUHH0dRln2T18_V0pkNkgocmBFlQ', '2026-01-23 01:17:57');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`booking_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `room_id` (`room_id`);

--
-- Indexes for table `contracts`
--
ALTER TABLE `contracts`
  ADD PRIMARY KEY (`contract_id`),
  ADD KEY `fk_contract_user` (`user_id`),
  ADD KEY `fk_contract_room` (`room_id`);

--
-- Indexes for table `extra_charges`
--
ALTER TABLE `extra_charges`
  ADD PRIMARY KEY (`charge_id`),
  ADD KEY `fk_charge_meter` (`meter_id`);

--
-- Indexes for table `maintenance`
--
ALTER TABLE `maintenance`
  ADD PRIMARY KEY (`maintenance_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `room_id` (`room_id`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `meters`
--
ALTER TABLE `meters`
  ADD PRIMARY KEY (`meter_id`),
  ADD UNIQUE KEY `unique_room_month` (`room_id`,`billing_month`),
  ADD KEY `fk_room_meters_new` (`room_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `meter_id` (`meter_id`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `rooms`
--
ALTER TABLE `rooms`
  ADD PRIMARY KEY (`room_id`),
  ADD UNIQUE KEY `room_number` (`room_number`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`setting_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `line_user_id` (`line_user_id`),
  ADD KEY `fk_user_room` (`room_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `booking_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `contracts`
--
ALTER TABLE `contracts`
  MODIFY `contract_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `extra_charges`
--
ALTER TABLE `extra_charges`
  MODIFY `charge_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `maintenance`
--
ALTER TABLE `maintenance`
  MODIFY `maintenance_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `meters`
--
ALTER TABLE `meters`
  MODIFY `meter_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `rooms`
--
ALTER TABLE `rooms`
  MODIFY `room_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `setting_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`room_id`) ON DELETE CASCADE;

--
-- Constraints for table `contracts`
--
ALTER TABLE `contracts`
  ADD CONSTRAINT `fk_contract_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`room_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_contract_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `extra_charges`
--
ALTER TABLE `extra_charges`
  ADD CONSTRAINT `fk_charge_meter` FOREIGN KEY (`meter_id`) REFERENCES `meters` (`meter_id`) ON DELETE CASCADE;

--
-- Constraints for table `meters`
--
ALTER TABLE `meters`
  ADD CONSTRAINT `fk_room_meters_new` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`room_id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_payment_meter_link` FOREIGN KEY (`meter_id`) REFERENCES `meters` (`meter_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_user_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`room_id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
