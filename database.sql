-- ReliefFlow Logistics & Inventory Management System
-- Database Schema & Initial Data Export
-- Database: inventory_system

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+08:00";

-- --------------------------------------------------------
-- Table structure for table `users`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(45) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `uq_users_username` (`username`),
  UNIQUE KEY `uq_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Default administrator account: admin / admin123
INSERT INTO `users` (`user_id`, `username`, `password`, `email`) VALUES
(1, 'admin', '$2y$10$a9ibwBWtfg6aXNpttQTg7uTJTY/wloByifCiEbPcyY/y9J3NAhS8G', 'admin@reliefcare.org')
ON DUPLICATE KEY UPDATE `password` = VALUES(`password`);

-- --------------------------------------------------------
-- Table structure for table `categories`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
  `category_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(45) NOT NULL,
  `description` varchar(150) NOT NULL,
  `icon` varchar(30) NOT NULL DEFAULT 'package',
  `color` varchar(30) NOT NULL DEFAULT '#0284c7',
  PRIMARY KEY (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Initial Relief Categories
INSERT INTO `categories` (`category_id`, `category_name`, `description`, `icon`, `color`) VALUES
(1, 'Food', 'Canned food, ready-to-eat meals, rice sacks, high-energy biscuits', 'utensils', '#ea580c'),
(2, 'Water', 'Bottled drinking water, purification tablets, mobile filtration units', 'droplets', '#0284c7'),
(3, 'Medicine', 'First-aid trauma kits, essential medicines, medical supplies', 'pill', '#dc2626'),
(4, 'Clothing', 'Apparel, thermal fleece blankets, tarpaulins, footwear', 'shirt', '#7c3aed'),
(5, 'Hygiene Kits', 'Soap bars, sanitizers, toothbrushes, sanitary pads, diapers', 'sparkles', '#059669')
ON DUPLICATE KEY UPDATE `category_name` = VALUES(`category_name`);

-- --------------------------------------------------------
-- Table structure for table `donors`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `donors` (
  `donor_id` int(11) NOT NULL AUTO_INCREMENT,
  `contact_person` varchar(60) NOT NULL,
  `contact_number` varchar(45) NOT NULL,
  `address` varchar(150) NOT NULL,
  `user_id` int(11) NOT NULL,
  PRIMARY KEY (`donor_id`),
  KEY `FK_users_TO_donors` (`user_id`),
  CONSTRAINT `FK_users_TO_donors` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for table `donations`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `donations` (
  `donation_id` int(11) NOT NULL AUTO_INCREMENT,
  `item_name` varchar(120) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `donation_date` date NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'Received',
  `donor_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  PRIMARY KEY (`donation_id`),
  KEY `FK_donors_TO_donations` (`donor_id`),
  KEY `FK_categories_TO_donations` (`category_id`),
  CONSTRAINT `FK_donors_TO_donations` FOREIGN KEY (`donor_id`) REFERENCES `donors` (`donor_id`) ON DELETE CASCADE,
  CONSTRAINT `FK_categories_TO_donations` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for table `events`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `events` (
  `event_id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(150) NOT NULL,
  `event_date` date NOT NULL,
  `event_time` varchar(20) NOT NULL DEFAULT '09:00',
  `location` varchar(150) NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'Scheduled',
  PRIMARY KEY (`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for table `activitylogs`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `activitylogs` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `action` varchar(60) NOT NULL,
  `status` int(11) NOT NULL DEFAULT 1,
  `timestamp` datetime NOT NULL,
  `user_id` int(11) NOT NULL,
  `donation_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`log_id`),
  KEY `FK_users_TO_activityLogs` (`user_id`),
  KEY `FK_donations_TO_activityLogs` (`donation_id`),
  CONSTRAINT `FK_donations_TO_activityLogs` FOREIGN KEY (`donation_id`) REFERENCES `donations` (`donation_id`) ON DELETE SET NULL,
  CONSTRAINT `FK_users_TO_activityLogs` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for table `notifications`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notifications` (
  `notification_id` int(11) NOT NULL AUTO_INCREMENT,
  `message` varchar(255) NOT NULL,
  `event_date` date NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `user_id` int(11) NOT NULL,
  `donation_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`notification_id`),
  KEY `FK_users_TO_notifications` (`user_id`),
  KEY `FK_donations_TO_notifications` (`donation_id`),
  CONSTRAINT `FK_donations_TO_notifications` FOREIGN KEY (`donation_id`) REFERENCES `donations` (`donation_id`) ON DELETE CASCADE,
  CONSTRAINT `FK_users_TO_notifications` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;
