-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 26, 2026 at 10:30 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `inventory_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `activitylogs`
--

CREATE TABLE `activitylogs` (
  `log_id` int(11) NOT NULL,
  `action` varchar(45) NOT NULL,
  `status` int(11) NOT NULL,
  `timestamp` datetime NOT NULL,
  `user_id` int(11) NOT NULL,
  `donation_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activitylogs`
--

INSERT INTO `activitylogs` (`log_id`, `action`, `status`, `timestamp`, `user_id`, `donation_id`) VALUES
(2, 'CREATED_DONATION', 1, '2026-08-27 05:21:55', 1, 1),
(8, 'CREATED_DONATION', 1, '2026-08-27 05:37:00', 1, 6),
(12, 'UPDATED_DONATION', 1, '2026-08-27 05:45:30', 1, 6),
(13, 'UPDATED_DONATION', 1, '2026-08-27 05:45:38', 1, 6),
(14, 'UPDATED_DONATION', 1, '2026-08-27 05:46:39', 1, 6),
(15, 'CREATED_DONATION', 1, '2026-08-27 05:48:47', 1, 7),
(16, 'CREATED_DONATION', 1, '2026-08-27 05:49:50', 1, 8),
(17, 'CREATED_DONATION', 1, '2026-08-27 05:51:54', 1, 9),
(19, 'UPDATED_DONATION', 1, '2026-08-27 06:14:03', 3, NULL),
(20, 'CREATED_DONATION', 1, '2026-10-27 03:28:00', 4, 11),
(21, 'CREATED_DONATION', 1, '2026-10-27 03:40:44', 4, 14),
(22, 'STATUS_CHANGE_TO_Released', 1, '2026-10-27 03:44:19', 4, 12),
(23, 'UPDATED_DONATION', 1, '2026-10-27 04:07:01', 4, 14),
(24, 'UPDATED_DONATION', 1, '2026-10-27 04:07:09', 4, 14),
(25, 'STATUS_CHANGE_TO_Received', 1, '2026-10-27 04:15:20', 4, 14),
(26, 'STATUS_CHANGE_TO_Received', 1, '2026-10-27 04:15:30', 4, 13),
(27, 'STATUS_CHANGE_TO_Pending', 1, '2026-10-27 04:15:41', 4, 13),
(29, 'DELETED_DONATION', 1, '2026-10-27 04:18:58', 4, NULL),
(31, 'USER_LOGIN', 1, '2026-10-27 04:35:32', 4, NULL),
(35, 'USER_LOGIN', 1, '2026-10-27 04:41:00', 4, NULL),
(36, 'USER_LOGIN', 1, '2026-10-27 04:44:20', 4, NULL),
(37, 'USER_LOGIN', 1, '2026-10-27 04:45:53', 4, NULL),
(38, 'USER_LOGIN', 1, '2026-10-27 04:51:23', 1, NULL),
(39, 'USER_LOGIN', 1, '2026-10-27 04:52:23', 1, NULL),
(40, 'USER_LOGIN', 1, '2026-10-27 04:52:45', 1, NULL),
(41, 'USER_LOGIN', 1, '2026-10-27 04:52:54', 1, NULL),
(42, 'USER_LOGIN', 1, '2026-10-27 05:21:02', 1, NULL),
(43, 'USER_LOGIN', 1, '2026-10-27 05:21:33', 1, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(45) NOT NULL,
  `description` varchar(45) NOT NULL,
  `icon` varchar(30) NOT NULL DEFAULT '?',
  `color` varchar(30) NOT NULL DEFAULT '#2563eb'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`category_id`, `category_name`, `description`, `icon`, `color`) VALUES
(2, 'Clothing', 'Apparel, thermal blankets, footwear', 'shirt', '#7c3aed'),
(5, 'Medical', 'Medicines and health supplies', 'pill', '#2563eb'),
(6, 'Food', 'Perishable & canned goods, rice packs', 'utensils', '#ea580c'),
(8, 'Water', 'Bottled clean drinking water & filtration kit', 'droplets', '#0284c7'),
(9, 'Medicine', 'First-aid, emergency meds, health supplies', 'pill', '#dc2626'),
(10, 'Hygiene Kits', 'Soap, sanitizers, dental kits, diapers', 'sparkles', '#059669');

-- --------------------------------------------------------

--
-- Table structure for table `donations`
--

CREATE TABLE `donations` (
  `donation_id` int(11) NOT NULL,
  `item_name` varchar(100) NOT NULL,
  `quantity` int(11) NOT NULL,
  `donation_date` date NOT NULL,
  `donor_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'Received'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `donations`
--

INSERT INTO `donations` (`donation_id`, `item_name`, `quantity`, `donation_date`, `donor_id`, `category_id`, `status`) VALUES
(1, 'Emergency Relief Food Packs (50 boxes)', 50, '2026-08-26', 1, 6, 'Received'),
(6, 'Bottled Mineral Water 500ml (100 cases)', 100, '2026-08-26', 1, 8, 'In Stock'),
(7, 'First Aid Emergency Trauma Kits (35 sets)', 35, '2026-08-26', 1, 9, 'In Stock'),
(8, 'Thermal Fleece Blankets (60 pcs)', 60, '2026-08-26', 1, 2, 'Received'),
(9, 'Family Sanitation & Hygiene Kits (40 packs)', 40, '2026-08-26', 3, 10, 'Received'),
(11, 'Canned Tuna', 10, '2026-10-26', 1, 6, 'Received'),
(12, 'High-Energy Biscuits (120 boxes)', 120, '2026-10-24', 1, 6, 'Released'),
(13, 'Water Purification Filter Tablets (500 packs)', 12, '2026-10-25', 1, 8, 'Pending'),
(14, 'Canned Tuna', 15, '2026-10-26', 1, 2, 'Received');

-- --------------------------------------------------------

--
-- Table structure for table `donors`
--

CREATE TABLE `donors` (
  `donor_id` int(11) NOT NULL,
  `contact_person` varchar(45) NOT NULL,
  `contact_number` varchar(45) NOT NULL,
  `address` varchar(45) NOT NULL,
  `user_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `donors`
--

INSERT INTO `donors` (`donor_id`, `contact_person`, `contact_number`, `address`, `user_id`) VALUES
(1, 'Analie Pedrano', '091656111103', 'Bog, Cebu', 1),
(3, 'Ann Batumbakal', '091656111103', 'Bog, Cebu', 1);

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `event_id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `event_date` date NOT NULL,
  `event_time` varchar(20) NOT NULL DEFAULT '09:00',
  `location` varchar(150) NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'Scheduled'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`event_id`, `title`, `event_date`, `event_time`, `location`, `status`) VALUES
(1, 'Barangay San Jose Supply Distribution', '2026-10-27', '09:30', 'North Evacuation Gym', 'Scheduled'),
(2, 'Emergency Medical Pack Dispatch', '2026-10-28', '14:00', 'Regional Health Center', 'Scheduled'),
(3, 'Drinking Water & Filtration Drive', '2026-10-30', '10:00', 'Coastal Sector Station', 'Scheduled'),
(4, 'Hygiene & Sanitation Kit Handout', '2026-11-01', '16:00', 'South Community Depot', 'Scheduled'),
(5, 'Food Pack Distribution for Sector 7', '2026-10-29', '11:00', 'San Isidro Elementary School', 'Scheduled'),
(6, 'Mobile Medical Clinic & Vaccine Mission', '2026-10-31', '08:30', 'Barangay Covered Court', 'Scheduled'),
(7, 'Thermal Blanket & Mat Handout', '2026-11-03', '15:00', 'District 2 Evacuation Depot', 'Scheduled'),
(8, 'Barangay Binabag Relief Mission', '2026-10-27', '09:00', 'Binabag, Bogo CIty', 'Scheduled');

-- --------------------------------------------------------

--
-- Table structure for table `inv_activity_logs`
--

CREATE TABLE `inv_activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `description` varchar(500) NOT NULL,
  `ip_address` varchar(64) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inv_categories`
--

CREATE TABLE `inv_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inv_categories`
--

INSERT INTO `inv_categories` (`id`, `name`, `description`, `created_at`) VALUES
(1, 'General Supplies', 'Default starter category', '2026-08-27 05:15:31'),
(2, 'Food', '', '2026-08-27 05:17:41');

-- --------------------------------------------------------

--
-- Table structure for table `inv_items`
--

CREATE TABLE `inv_items` (
  `id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `item_name` varchar(150) NOT NULL,
  `sku` varchar(80) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `unit_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `reorder_level` int(11) NOT NULL DEFAULT 5,
  `location` varchar(120) DEFAULT NULL,
  `notes` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inv_items`
--

INSERT INTO `inv_items` (`id`, `category_id`, `item_name`, `sku`, `quantity`, `unit_price`, `reorder_level`, `location`, `notes`, `created_at`, `updated_at`) VALUES
(2, 1, 'Ass', '12', 1, 0.06, 5, 'xs', 'ww', '2026-08-27 05:18:05', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `inv_notifications`
--

CREATE TABLE `inv_notifications` (
  `id` int(11) NOT NULL,
  `title` varchar(180) NOT NULL,
  `message` varchar(500) NOT NULL,
  `type` varchar(30) NOT NULL DEFAULT 'info',
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inv_notifications`
--

INSERT INTO `inv_notifications` (`id`, `title`, `message`, `type`, `is_read`, `created_at`) VALUES
(1, 'New Item Stock', 'A new item was registered into inventory.', 'success', 1, '2026-08-27 05:15:48'),
(2, 'New category', 'A new product category was added.', 'success', 1, '2026-08-27 05:17:41'),
(3, 'New Item Stock', 'A new item was registered into inventory.', 'success', 1, '2026-08-27 05:18:05');

-- --------------------------------------------------------

--
-- Table structure for table `inv_users`
--

CREATE TABLE `inv_users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(190) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` varchar(40) NOT NULL DEFAULT 'Administrator',
  `status` varchar(20) NOT NULL DEFAULT 'Active',
  `profile_photo` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inv_users`
--

INSERT INTO `inv_users` (`id`, `full_name`, `email`, `password_hash`, `role`, `status`, `profile_photo`, `created_at`, `updated_at`) VALUES
(1, 'System Administrator', 'admin@inventory.local', '$2y$10$Vz0c2WGhitFY3V0pwiRME.4ZdmHsDt3FhXGGWXaPKZuc3rbRdRrSO', 'Administrator', 'Active', NULL, '2026-08-27 05:13:13', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `message` varchar(45) NOT NULL,
  `event_date` date NOT NULL,
  `user_id` int(11) NOT NULL,
  `donation_id` int(11) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `message`, `event_date`, `user_id`, `donation_id`, `is_read`) VALUES
(1, 'New donation item registered.', '2026-08-26', 1, 1, 1),
(6, 'New donation item registered.', '2026-08-26', 1, 6, 1),
(7, 'New donation item registered.', '2026-08-26', 1, 7, 1),
(8, 'New donation item registered.', '2026-08-26', 1, 8, 1),
(9, 'New donation item registered.', '2026-08-26', 1, 9, 1),
(11, 'New relief supply recorded: Canned Tuna (10 u', '2026-10-26', 4, 11, 1);

-- --------------------------------------------------------

--
-- Table structure for table `participants`
--

CREATE TABLE `participants` (
  `participant_id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `Email` varchar(190) NOT NULL,
  `gender` varchar(30) NOT NULL,
  `birth_date` date NOT NULL,
  `city` varchar(120) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `participants`
--

INSERT INTO `participants` (`participant_id`, `first_name`, `last_name`, `Email`, `gender`, `birth_date`, `city`) VALUES
(1, 'Analie', 'Pedrano', 'pedranoanalie@gmail.com', 'Male', '2026-07-27', 'frf');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(45) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password`, `email`) VALUES
(1, 'admin', '$2y$10$a9ibwBWtfg6aXNpttQTg7uTJTY/wloByifCiEbPcyY/y9J3NAhS8G', 'admin@events.local'),
(3, 'analieyze', '$2y$10$UCwguGiaoXOEYrxj3/nAB..XBE5Gt8gBEmoLk91ARHidCq1pxCjjG', 'pedranoanalie@gmail.com'),
(4, 'analilieyze', '$2y$10$a0pz.F6UX.jRuxN.mR1Wqum/MZ1za3h7hcesO3KrW8gJFazsh0b3O', 'analieyze@gmail.com');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activitylogs`
--
ALTER TABLE `activitylogs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `FK_users_TO_activityLogs` (`user_id`),
  ADD KEY `FK_donations_TO_activityLogs` (`donation_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`);

--
-- Indexes for table `donations`
--
ALTER TABLE `donations`
  ADD PRIMARY KEY (`donation_id`),
  ADD KEY `FK_donors_TO_donations` (`donor_id`),
  ADD KEY `FK_categories_TO_donations` (`category_id`);

--
-- Indexes for table `donors`
--
ALTER TABLE `donors`
  ADD PRIMARY KEY (`donor_id`),
  ADD KEY `FK_users_TO_donors` (`user_id`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`event_id`);

--
-- Indexes for table `inv_activity_logs`
--
ALTER TABLE `inv_activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_logs_created` (`created_at`);

--
-- Indexes for table `inv_categories`
--
ALTER TABLE `inv_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `inv_items`
--
ALTER TABLE `inv_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sku` (`sku`),
  ADD KEY `idx_item_category` (`category_id`);

--
-- Indexes for table `inv_notifications`
--
ALTER TABLE `inv_notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `inv_users`
--
ALTER TABLE `inv_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `FK_users_TO_notifications` (`user_id`),
  ADD KEY `FK_donations_TO_notifications` (`donation_id`);

--
-- Indexes for table `participants`
--
ALTER TABLE `participants`
  ADD PRIMARY KEY (`participant_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `uq_users_email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activitylogs`
--
ALTER TABLE `activitylogs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `donations`
--
ALTER TABLE `donations`
  MODIFY `donation_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `donors`
--
ALTER TABLE `donors`
  MODIFY `donor_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `event_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `inv_activity_logs`
--
ALTER TABLE `inv_activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `inv_categories`
--
ALTER TABLE `inv_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `inv_items`
--
ALTER TABLE `inv_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `inv_notifications`
--
ALTER TABLE `inv_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `inv_users`
--
ALTER TABLE `inv_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `participants`
--
ALTER TABLE `participants`
  MODIFY `participant_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activitylogs`
--
ALTER TABLE `activitylogs`
  ADD CONSTRAINT `FK_donations_TO_activityLogs` FOREIGN KEY (`donation_id`) REFERENCES `donations` (`donation_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `FK_users_TO_activityLogs` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `donations`
--
ALTER TABLE `donations`
  ADD CONSTRAINT `FK_categories_TO_donations` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`),
  ADD CONSTRAINT `FK_donors_TO_donations` FOREIGN KEY (`donor_id`) REFERENCES `donors` (`donor_id`);

--
-- Constraints for table `donors`
--
ALTER TABLE `donors`
  ADD CONSTRAINT `FK_users_TO_donors` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `FK_donations_TO_notifications` FOREIGN KEY (`donation_id`) REFERENCES `donations` (`donation_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `FK_users_TO_notifications` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
