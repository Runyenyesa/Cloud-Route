-- ============================================================
-- CloudRoute Additions — Global Coaches Limited Requirements
-- Run this AFTER cloud_route.sql
-- ============================================================

-- 1. PARCELS TABLE
CREATE TABLE IF NOT EXISTS `parcels` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parcel_id` varchar(20) NOT NULL UNIQUE,
  `sender_name` varchar(100) NOT NULL,
  `sender_phone` varchar(15) NOT NULL,
  `recipient_name` varchar(100) NOT NULL,
  `recipient_phone` varchar(15) NOT NULL,
  `route` varchar(100) NOT NULL,
  `bus_number` varchar(20) DEFAULT NULL,
  `date` date NOT NULL,
  `description` varchar(255) NOT NULL,
  `weight_kg` decimal(5,2) DEFAULT 0.00,
  `price` decimal(10,2) DEFAULT 0.00,
  `collection_code` varchar(10) NOT NULL,
  `payment_method` enum('cash','mtn_momo','airtel_money') DEFAULT 'cash',
  `payment_status` enum('pending','paid') DEFAULT 'pending',
  `status` enum('booked','in_transit','arrived','collected','lost') DEFAULT 'booked',
  `collected_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  FOREIGN KEY (`bus_number`) REFERENCES `buses` (`bus_number`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. ADD SPEED MONITORING TO BUSES TABLE
ALTER TABLE `buses`
  ADD COLUMN IF NOT EXISTS `current_speed` decimal(5,2) DEFAULT 0.00,
  ADD COLUMN IF NOT EXISTS `speed_limit` decimal(5,2) DEFAULT 80.00,
  ADD COLUMN IF NOT EXISTS `speed_alert` tinyint(1) DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `speed_alert_at` timestamp NULL DEFAULT NULL;

-- 3. PAYMENTS TABLE
CREATE TABLE IF NOT EXISTS `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `payment_id` varchar(20) NOT NULL UNIQUE,
  `reference_id` varchar(20) NOT NULL,
  `reference_type` enum('booking','parcel') DEFAULT 'booking',
  `amount` decimal(10,2) NOT NULL,
  `method` enum('cash','mtn_momo','airtel_money') DEFAULT 'mtn_momo',
  `phone_number` varchar(15) DEFAULT NULL,
  `status` enum('pending','completed','failed') DEFAULT 'pending',
  `transaction_code` varchar(50) DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. ADD DELAY/NOTIFICATION TRIGGER COLUMNS TO BOOKINGS
ALTER TABLE `bookings`
  ADD COLUMN IF NOT EXISTS `delay_notified` tinyint(1) DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `price` decimal(10,2) DEFAULT 0.00,
  ADD COLUMN IF NOT EXISTS `payment_status` enum('pending','paid') DEFAULT 'pending',
  ADD COLUMN IF NOT EXISTS `payment_method` enum('cash','mtn_momo','airtel_money') DEFAULT 'cash';

-- 5. DELAY ALERTS TABLE
CREATE TABLE IF NOT EXISTS `delay_alerts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bus_number` varchar(20) NOT NULL,
  `route` varchar(100) NOT NULL,
  `delay_minutes` int(11) DEFAULT 0,
  `reason` varchar(255) DEFAULT NULL,
  `notified_count` int(11) DEFAULT 0,
  `resolved` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `resolved_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. ROUTE PRICES (for payment calculation)
ALTER TABLE `routes`
  ADD COLUMN IF NOT EXISTS `price` decimal(10,2) DEFAULT 5000.00;

UPDATE routes SET price = 15000 WHERE route_code LIKE '%mbarara-kampala%' OR destination LIKE '%Kampala%';
UPDATE routes SET price = 8000 WHERE route_code LIKE '%mbarara-kabale%' OR destination LIKE '%Kabale%';
UPDATE routes SET price = 10000 WHERE route_code LIKE '%mbarara-masaka%' OR destination LIKE '%Masaka%';
