-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Mar 08, 2025 at 05:12 PM
-- Server version: 9.1.0
-- PHP Version: 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `autoproftx`
--

DELIMITER $$
--
-- Procedures
--
DROP PROCEDURE IF EXISTS `distribute_monthly_bonuses`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `distribute_monthly_bonuses` (IN `bonus_month` VARCHAR(7))   BEGIN
  DECLARE done INT DEFAULT FALSE;
  DECLARE v_user_id INT;
  DECLARE v_rank INT;
  DECLARE v_bonus_amount DECIMAL(15,2);
  DECLARE v_reference_id VARCHAR(50);
  
  -- Cursor for top 3 depositors
  DECLARE bonus_cursor CURSOR FOR 
    SELECT user_id, `rank` FROM leaderboard_deposits 
    WHERE period = bonus_month AND `rank` <= 3
    ORDER BY `rank`;
  
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
  
  -- Check if bonuses already distributed
  IF (SELECT COUNT(*) FROM leaderboard_bonuses WHERE bonus_month = bonus_month) > 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Bonuses already distributed for this month';
  END IF;
  
  OPEN bonus_cursor;
  
  bonus_loop: LOOP
    FETCH bonus_cursor INTO v_user_id, v_rank;
    
    IF done THEN
      LEAVE bonus_loop;
    END IF;
    
    -- Determine bonus amount based on rank
    CASE v_rank
      WHEN 1 THEN SET v_bonus_amount = 5000.00;
      WHEN 2 THEN SET v_bonus_amount = 3000.00;
      WHEN 3 THEN SET v_bonus_amount = 2000.00;
      ELSE SET v_bonus_amount = 0.00;
    END CASE;
    
    -- Create reference ID
    SET v_reference_id = CONCAT('LDRBNS-', bonus_month, '-', v_rank);
    
    -- Start transaction
    START TRANSACTION;
    
    -- Insert bonus record
    INSERT INTO leaderboard_bonuses (
      user_id, bonus_month, rank_position, bonus_amount, status, paid_at
    ) VALUES (
      v_user_id, bonus_month, v_rank, v_bonus_amount, 'paid', NOW()
    );
    
    -- Add to wallet
    UPDATE wallets SET 
      balance = balance + v_bonus_amount,
      updated_at = NOW()
    WHERE user_id = v_user_id;
    
    -- Add transaction record
    INSERT INTO transactions (
      user_id, transaction_type, amount, status, description, reference_id
    ) VALUES (
      v_user_id, 'deposit', v_bonus_amount, 'completed', 
      CONCAT('Leaderboard bonus for rank ', v_rank, ' (', bonus_month, ')'), 
      v_reference_id
    );
    
    COMMIT;
  END LOOP;
  
  CLOSE bonus_cursor;
END$$

DROP PROCEDURE IF EXISTS `update_leaderboards`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `update_leaderboards` ()   BEGIN
  DECLARE current_month VARCHAR(7);
  SET current_month = DATE_FORMAT(NOW(), '%Y-%m');
  
  -- Clear previous monthly rankings
  DELETE FROM leaderboard_deposits WHERE period = current_month;
  
  -- Insert new monthly rankings
  INSERT INTO leaderboard_deposits (user_id, period, total_deposited, deposit_count, `rank`)
  SELECT 
    d.user_id,
    current_month,
    SUM(d.amount) as total_deposited,
    COUNT(d.id) as deposit_count,
    @row_num := @row_num + 1 as `rank`
  FROM 
    deposits d, 
    (SELECT @row_num := 0) r
  WHERE 
    d.status = 'approved' AND
    DATE_FORMAT(d.created_at, '%Y-%m') = current_month
  GROUP BY 
    d.user_id
  ORDER BY 
    SUM(d.amount) DESC;
    
  -- Update all-time leaderboard
  DELETE FROM leaderboard_deposits WHERE period = 'all-time';
  
  INSERT INTO leaderboard_deposits (user_id, period, total_deposited, deposit_count, `rank`)
  SELECT 
    d.user_id,
    'all-time',
    SUM(d.amount) as total_deposited,
    COUNT(d.id) as deposit_count,
    @row_num2 := @row_num2 + 1 as `rank`
  FROM 
    deposits d,
    (SELECT @row_num2 := 0) r
  WHERE 
    d.status = 'approved'
  GROUP BY 
    d.user_id
  ORDER BY 
    SUM(d.amount) DESC;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `admin_payment_methods`
--

DROP TABLE IF EXISTS `admin_payment_methods`;
CREATE TABLE IF NOT EXISTS `admin_payment_methods` (
  `id` int NOT NULL AUTO_INCREMENT,
  `payment_type` enum('easypaisa','jazzcash','bank') NOT NULL,
  `account_name` varchar(100) NOT NULL,
  `account_number` varchar(100) NOT NULL,
  `additional_info` text,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `admin_payment_methods`
--

INSERT INTO `admin_payment_methods` (`id`, `payment_type`, `account_name`, `account_number`, `additional_info`, `is_active`, `created_at`) VALUES
(1, 'easypaisa', 'Admin', '03196977218', 'Send to this Easypaisa account', 1, '2025-03-07 10:31:03'),
(2, 'jazzcash', 'Admin', '03196977218', 'Send to this JazzCash account', 1, '2025-03-07 10:31:03'),
(3, 'bank', 'Sada Pay', '03196977218', 'Bank: HBL, Branch: Main City', 1, '2025-03-07 10:31:03');

-- --------------------------------------------------------

--
-- Table structure for table `admin_users`
--

DROP TABLE IF EXISTS `admin_users`;
CREATE TABLE IF NOT EXISTS `admin_users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','super_admin') DEFAULT 'admin',
  `status` enum('active','inactive') DEFAULT 'active',
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `admin_users`
--

INSERT INTO `admin_users` (`id`, `username`, `name`, `email`, `password`, `role`, `status`, `last_login`, `created_at`) VALUES
(1, 'admin', 'Administrator', 'admin@autoproftx.com', '$2y$10$FcuowkyBFb7isUYWUPzNBujVZPsEYqfEW6WBNUmKp9zMDZ6Ktpi.a', 'super_admin', 'active', '2025-03-08 17:09:28', '2025-03-07 10:36:19');

-- --------------------------------------------------------

--
-- Table structure for table `alpha_tokens`
--

DROP TABLE IF EXISTS `alpha_tokens`;
CREATE TABLE IF NOT EXISTS `alpha_tokens` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `purchase_date` datetime NOT NULL,
  `purchase_amount` decimal(15,2) NOT NULL DEFAULT '1000.00',
  `status` enum('active','sold') NOT NULL DEFAULT 'active',
  `sold_date` datetime DEFAULT NULL,
  `sold_amount` decimal(15,2) DEFAULT NULL,
  `profit` decimal(15,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `status` (`status`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `alpha_tokens`
--

INSERT INTO `alpha_tokens` (`id`, `user_id`, `purchase_date`, `purchase_amount`, `status`, `sold_date`, `sold_amount`, `profit`, `created_at`) VALUES
(1, 14, '2025-03-08 16:45:18', 1000.00, 'sold', '2025-03-08 21:45:46', 1065.00, 65.00, '2025-03-08 16:45:18'),
(2, 14, '2025-03-08 16:45:58', 1000.00, 'sold', '2025-03-08 21:46:07', 1065.00, 65.00, '2025-03-08 16:45:58'),
(3, 14, '2025-03-08 16:48:23', 1000.00, 'active', NULL, NULL, NULL, '2025-03-08 16:48:23');

-- --------------------------------------------------------

--
-- Table structure for table `backup_codes`
--

DROP TABLE IF EXISTS `backup_codes`;
CREATE TABLE IF NOT EXISTS `backup_codes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `code` varchar(12) NOT NULL,
  `is_used` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `used_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `code` (`code`)
) ENGINE=MyISAM AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `backup_codes`
--

INSERT INTO `backup_codes` (`id`, `user_id`, `code`, `is_used`, `created_at`, `used_at`) VALUES
(1, 17, '9GCV-F3VE-PL', 1, '2025-03-08 15:49:00', NULL),
(2, 17, 'APV4-WQRR-DR', 1, '2025-03-08 15:49:00', NULL),
(3, 17, 'BYC6-95XE-G5', 1, '2025-03-08 15:49:00', NULL),
(4, 17, 'XBKY-44SG-5Q', 1, '2025-03-08 15:49:00', NULL),
(5, 17, 'TF43-K9W8-4F', 1, '2025-03-08 15:49:00', NULL),
(6, 17, '3JKB-6L8S-45', 1, '2025-03-08 15:49:00', NULL),
(7, 17, 'K29R-QBCA-B4', 1, '2025-03-08 15:49:00', NULL),
(8, 17, '3PFZ-NZJH-E6', 1, '2025-03-08 15:49:00', '2025-03-08 21:10:39'),
(9, 17, 'LLU4-FCD2-Y6', 1, '2025-03-08 15:49:00', NULL),
(10, 17, 'Z3YN-Y7GF-GN', 1, '2025-03-08 15:49:00', NULL),
(11, 17, '4LN9-SFNT-4V', 0, '2025-03-08 16:11:24', NULL),
(12, 17, '8NHF-WU4A-VL', 0, '2025-03-08 16:11:24', NULL),
(13, 17, 'TBNK-DGCF-7G', 0, '2025-03-08 16:11:24', NULL),
(14, 17, 'EZXM-NUF8-FJ', 0, '2025-03-08 16:11:24', NULL),
(15, 17, 'F5UR-CBRN-CN', 0, '2025-03-08 16:11:24', NULL),
(16, 17, 'BF8C-NMEG-BN', 0, '2025-03-08 16:11:24', NULL),
(17, 17, 'GBMV-JBLV-78', 0, '2025-03-08 16:11:24', NULL),
(18, 17, 'JRKX-QPEA-C4', 0, '2025-03-08 16:11:24', NULL),
(19, 17, 'R98X-4PEZ-CU', 0, '2025-03-08 16:11:24', NULL),
(20, 17, 'ZVVR-FRRL-6B', 0, '2025-03-08 16:11:24', NULL),
(21, 14, 'AUJ6-ASJN-WW', 0, '2025-03-08 16:15:13', NULL),
(22, 14, 'FZBS-FKKY-EL', 0, '2025-03-08 16:15:13', NULL),
(23, 14, 'P6FC-R9MB-QV', 0, '2025-03-08 16:15:13', NULL),
(24, 14, 'RRYC-PH3Y-8A', 0, '2025-03-08 16:15:13', NULL),
(25, 14, 'GLU4-MLG3-YQ', 0, '2025-03-08 16:15:13', NULL),
(26, 14, 'JV8M-GBXY-5B', 0, '2025-03-08 16:15:13', NULL),
(27, 14, 'TVWP-AKYZ-6C', 0, '2025-03-08 16:15:13', NULL),
(28, 14, 'XTR4-CYXR-MY', 0, '2025-03-08 16:15:13', NULL),
(29, 14, 'FUXH-MJ4F-97', 0, '2025-03-08 16:15:13', NULL),
(30, 14, 'HV4E-9MGZ-YH', 0, '2025-03-08 16:15:13', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `checkin_rewards`
--

DROP TABLE IF EXISTS `checkin_rewards`;
CREATE TABLE IF NOT EXISTS `checkin_rewards` (
  `id` int NOT NULL AUTO_INCREMENT,
  `streak_day` int NOT NULL,
  `reward_amount` decimal(10,2) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `streak_day` (`streak_day`)
) ENGINE=MyISAM AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `checkin_rewards`
--

INSERT INTO `checkin_rewards` (`id`, `streak_day`, `reward_amount`, `is_active`) VALUES
(1, 1, 5.00, 1),
(2, 2, 6.00, 1),
(3, 3, 7.00, 1),
(4, 4, 8.00, 1),
(5, 5, 10.00, 1),
(6, 6, 12.00, 1),
(7, 7, 15.00, 1),
(8, 14, 30.00, 1),
(9, 21, 50.00, 1),
(10, 30, 100.00, 1);

-- --------------------------------------------------------

--
-- Table structure for table `daily_checkins`
--

DROP TABLE IF EXISTS `daily_checkins`;
CREATE TABLE IF NOT EXISTS `daily_checkins` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `checkin_date` date NOT NULL,
  `streak_count` int NOT NULL DEFAULT '1',
  `reward_amount` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_date_unique` (`user_id`,`checkin_date`),
  KEY `user_id` (`user_id`)
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `daily_checkins`
--

INSERT INTO `daily_checkins` (`id`, `user_id`, `checkin_date`, `streak_count`, `reward_amount`, `created_at`) VALUES
(4, 6, '2025-03-08', 1, 5.00, '2025-03-08 05:13:01'),
(3, 8, '2025-03-08', 1, 5.00, '2025-03-08 00:11:25'),
(5, 13, '2025-03-08', 1, 5.00, '2025-03-08 12:53:43'),
(6, 14, '2025-03-08', 1, 5.00, '2025-03-08 17:01:49');

-- --------------------------------------------------------

--
-- Table structure for table `deposits`
--

DROP TABLE IF EXISTS `deposits`;
CREATE TABLE IF NOT EXISTS `deposits` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `payment_method_id` int NOT NULL,
  `admin_payment_id` int NOT NULL,
  `transaction_id` varchar(255) DEFAULT NULL,
  `proof_file` varchar(255) NOT NULL,
  `notes` text,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `processed_at` timestamp NULL DEFAULT NULL,
  `admin_notes` text,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=MyISAM AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `deposits`
--

INSERT INTO `deposits` (`id`, `user_id`, `amount`, `payment_method_id`, `admin_payment_id`, `transaction_id`, `proof_file`, `notes`, `status`, `created_at`, `processed_at`, `admin_notes`) VALUES
(19, 13, 4500.00, 17, 2, '12345678', 'proof_13_1741439306.png', '', 'approved', '2025-03-08 13:08:26', '2025-03-08 13:08:41', ''),
(18, 15, 10000.00, 16, 1, '1122', 'proof_15_1741438112.png', 'yes', 'approved', '2025-03-08 12:48:32', '2025-03-08 12:48:52', ''),
(17, 14, 5000.00, 15, 1, '1234', 'proof_14_1741437850.png', 'Nothing', 'approved', '2025-03-08 12:44:10', '2025-03-08 12:44:36', 'Ok');

-- --------------------------------------------------------

--
-- Table structure for table `investments`
--

DROP TABLE IF EXISTS `investments`;
CREATE TABLE IF NOT EXISTS `investments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `investment_id` varchar(20) NOT NULL,
  `user_id` int NOT NULL,
  `plan_type` varchar(50) NOT NULL,
  `plan_id` int DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `expected_profit` decimal(15,2) NOT NULL,
  `total_return` decimal(15,2) NOT NULL,
  `status` enum('active','completed','cancelled') NOT NULL DEFAULT 'active',
  `start_date` datetime NOT NULL,
  `maturity_date` datetime NOT NULL,
  `completion_date` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `referral_commission_paid` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=56 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `investments`
--

INSERT INTO `investments` (`id`, `investment_id`, `user_id`, `plan_type`, `plan_id`, `amount`, `expected_profit`, `total_return`, `status`, `start_date`, `maturity_date`, `completion_date`, `created_at`, `updated_at`, `referral_commission_paid`) VALUES
(55, 'INV-89125', 14, 'Standard', NULL, 5000.00, 1000.00, 6000.00, 'active', '2025-03-08 12:53:02', '2025-03-09 12:53:02', NULL, '2025-03-08 12:53:02', '2025-03-08 12:53:02', 1),
(54, 'INV-30787', 15, 'Premium', NULL, 10000.00, 2000.00, 12000.00, 'active', '2025-03-08 12:51:13', '2025-03-09 12:51:13', NULL, '2025-03-08 12:51:13', '2025-03-08 12:51:13', 1),
(29, 'TEST-33345', 6, 'Basic', NULL, 500.00, 100.00, 600.00, 'completed', '2025-03-08 11:20:33', '2025-03-08 11:21:33', '2025-03-08 16:20:34', '2025-03-08 11:20:33', '2025-03-08 11:20:34', 0),
(30, 'TEST-84046', 6, 'Basic', NULL, 500.00, 100.00, 600.00, 'completed', '2025-03-08 11:20:34', '2025-03-08 11:21:34', '2025-03-08 16:20:34', '2025-03-08 11:20:34', '2025-03-08 11:20:34', 0),
(31, 'TEST-38803', 6, 'Basic', NULL, 500.00, 100.00, 600.00, 'completed', '2025-03-08 11:20:34', '2025-03-08 11:21:34', '2025-03-08 16:22:38', '2025-03-08 11:20:34', '2025-03-08 11:22:38', 0),
(32, 'INV-11497', 6, 'Basic', NULL, 3000.00, 600.00, 3600.00, 'completed', '2025-03-08 11:22:49', '2025-03-08 11:23:49', '2025-03-08 16:22:50', '2025-03-08 11:22:49', '2025-03-08 11:22:50', 0),
(33, 'INV-79246', 6, 'Basic', NULL, 3000.00, 600.00, 3600.00, 'completed', '2025-03-08 11:23:04', '2025-03-08 11:24:04', '2025-03-08 16:23:05', '2025-03-08 11:23:04', '2025-03-08 11:23:05', 0),
(34, 'INV-82173', 6, 'Basic', NULL, 3000.00, 600.00, 3600.00, 'completed', '2025-03-08 11:23:34', '2025-03-08 11:24:34', '2025-03-08 16:23:34', '2025-03-08 11:23:34', '2025-03-08 11:23:34', 0),
(35, 'INV-88420', 6, 'Basic', NULL, 3000.00, 600.00, 3600.00, 'completed', '2025-03-08 11:26:25', '2025-03-08 11:27:25', '2025-03-08 16:26:26', '2025-03-08 11:26:25', '2025-03-08 11:26:26', 0),
(36, 'INV-99121', 6, 'Basic', NULL, 3000.00, 600.00, 3600.00, 'completed', '2025-03-08 11:26:36', '2025-03-08 11:27:36', '2025-03-08 16:26:37', '2025-03-08 11:26:36', '2025-03-08 11:26:37', 0),
(37, 'INV-80706', 6, 'Basic', NULL, 3000.00, 600.00, 3600.00, 'completed', '2025-03-08 11:28:07', '2025-03-08 11:29:07', '2025-03-08 16:28:08', '2025-03-08 11:28:07', '2025-03-08 11:28:08', 0),
(38, 'INV-22396', 6, 'Basic', NULL, 3000.00, 600.00, 3600.00, 'active', '2025-03-08 11:28:26', '2025-03-09 11:28:26', NULL, '2025-03-08 11:28:26', '2025-03-08 11:28:26', 0),
(39, 'INV-27644', 6, 'Basic', NULL, 3000.00, 600.00, 3600.00, 'active', '2025-03-08 11:28:57', '2025-03-09 11:28:57', NULL, '2025-03-08 11:28:57', '2025-03-08 11:28:57', 0),
(40, 'TEST-92798', 6, 'Basic', NULL, 500.00, 100.00, 600.00, 'completed', '2025-03-08 11:29:12', '2025-03-08 11:30:12', '2025-03-08 16:29:13', '2025-03-08 11:29:12', '2025-03-08 11:29:13', 0),
(41, 'TEST-58132', 6, 'Basic', NULL, 500.00, 100.00, 600.00, 'completed', '2025-03-08 11:29:13', '2025-03-08 11:30:13', '2025-03-08 16:29:13', '2025-03-08 11:29:13', '2025-03-08 11:29:13', 0),
(42, 'TEST-59483', 6, 'Basic', NULL, 500.00, 100.00, 600.00, 'completed', '2025-03-08 11:29:13', '2025-03-08 11:30:13', '2025-03-08 16:29:14', '2025-03-08 11:29:13', '2025-03-08 11:29:14', 0),
(43, 'TEST-15825', 6, 'Basic', NULL, 500.00, 100.00, 600.00, 'completed', '2025-03-08 11:29:14', '2025-03-08 11:30:14', '2025-03-08 16:29:14', '2025-03-08 11:29:14', '2025-03-08 11:29:14', 0),
(44, 'TEST-46948', 6, 'Basic', NULL, 500.00, 100.00, 600.00, 'completed', '2025-03-08 11:29:14', '2025-03-08 11:30:14', '2025-03-08 16:29:30', '2025-03-08 11:29:14', '2025-03-08 11:29:30', 0),
(45, 'TEST-23063', 6, 'Basic', NULL, 500.00, 100.00, 600.00, 'completed', '2025-03-08 11:30:08', '2025-03-08 11:31:08', '2025-03-08 16:30:09', '2025-03-08 11:30:08', '2025-03-08 11:30:09', 0),
(46, 'TEST-62516', 6, 'Basic', NULL, 500.00, 100.00, 600.00, 'completed', '2025-03-08 11:30:09', '2025-03-08 11:31:09', '2025-03-08 16:30:13', '2025-03-08 11:30:09', '2025-03-08 11:30:13', 0),
(47, 'INV-30779', 6, 'Professional', NULL, 20000.00, 4000.00, 24000.00, 'completed', '2025-03-08 11:35:18', '2025-03-08 11:36:18', '2025-03-08 16:35:19', '2025-03-08 11:35:18', '2025-03-08 11:35:19', 0),
(48, 'INV-20892', 6, 'Professional', NULL, 20000.00, 4000.00, 24000.00, 'completed', '2025-03-08 11:35:35', '2025-03-08 11:36:35', '2025-03-08 16:35:35', '2025-03-08 11:35:35', '2025-03-08 11:35:35', 0),
(49, 'INV-23708', 6, 'Basic', NULL, 3000.00, 600.00, 3600.00, 'completed', '2025-03-08 11:35:40', '2025-03-08 11:36:40', '2025-03-08 16:35:41', '2025-03-08 11:35:40', '2025-03-08 11:35:41', 0),
(50, 'INV-41090', 6, 'Premium', NULL, 10000.00, 2000.00, 12000.00, 'completed', '2025-03-08 11:35:49', '2025-03-08 11:36:49', '2025-03-08 16:35:50', '2025-03-08 11:35:49', '2025-03-08 11:35:50', 0),
(51, 'INV-46801', 6, 'Premium', NULL, 10000.00, 2000.00, 12000.00, 'completed', '2025-03-08 11:35:56', '2025-03-08 11:36:56', '2025-03-08 16:35:57', '2025-03-08 11:35:56', '2025-03-08 11:35:57', 0),
(52, 'INV-99904', 6, 'Professional', NULL, 20000.00, 4000.00, 24000.00, 'active', '2025-03-08 11:36:29', '2025-03-09 11:36:29', NULL, '2025-03-08 11:36:29', '2025-03-08 11:36:29', 0),
(53, 'INV-16801', 6, 'Professional', NULL, 20000.00, 4000.00, 24000.00, 'active', '2025-03-08 11:37:14', '2025-03-09 11:37:14', NULL, '2025-03-08 11:37:14', '2025-03-08 11:37:14', 0);

-- --------------------------------------------------------

--
-- Table structure for table `investment_plans`
--

DROP TABLE IF EXISTS `investment_plans`;
CREATE TABLE IF NOT EXISTS `investment_plans` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `description` text,
  `min_amount` decimal(15,2) NOT NULL,
  `max_amount` decimal(15,2) DEFAULT NULL,
  `daily_profit_rate` decimal(5,2) NOT NULL,
  `duration_days` int NOT NULL,
  `referral_commission_rate` decimal(5,2) NOT NULL COMMENT 'Commission rate for referrers in percentage',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `investment_plans`
--

INSERT INTO `investment_plans` (`id`, `name`, `description`, `min_amount`, `max_amount`, `daily_profit_rate`, `duration_days`, `referral_commission_rate`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Basic', 'Basic investment plan with 10% referral commission', 1000.00, 5000.00, 14.00, 1, 10.00, 1, '2025-03-07 17:37:00', '2025-03-07 19:08:54'),
(2, 'Premium', 'Premium investment plan with 20% referral commission', 5000.00, 15000.00, 14.00, 1, 10.00, 1, '2025-03-07 17:37:00', '2025-03-08 12:35:12'),
(3, 'Professional', 'Professional investment plan with 30% referral commission', 15001.00, 50000.00, 14.00, 1, 10.00, 1, '2025-03-07 17:37:00', '2025-03-08 12:35:18'),
(4, 'Standard', 'Standard investment plan with balanced returns', 5000.00, 9999.00, 14.00, 1, 10.00, 1, '2025-03-07 19:10:42', '2025-03-08 12:35:23'),
(5, 'Custom', 'Create your own custom investment plan', 25000.00, NULL, 14.00, 1, 10.00, 1, '2025-03-07 19:11:29', '2025-03-08 12:35:30');

-- --------------------------------------------------------

--
-- Table structure for table `leaderboard_bonuses`
--

DROP TABLE IF EXISTS `leaderboard_bonuses`;
CREATE TABLE IF NOT EXISTS `leaderboard_bonuses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `bonus_month` varchar(7) NOT NULL COMMENT 'Format: YYYY-MM',
  `rank_position` int NOT NULL,
  `bonus_amount` decimal(15,2) NOT NULL,
  `status` enum('pending','paid') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `paid_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_month_unique` (`user_id`,`bonus_month`),
  KEY `bonus_month` (`bonus_month`),
  KEY `user_id` (`user_id`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `leaderboard_deposits`
--

DROP TABLE IF EXISTS `leaderboard_deposits`;
CREATE TABLE IF NOT EXISTS `leaderboard_deposits` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `period` varchar(7) NOT NULL COMMENT 'Format: YYYY-MM for monthly or "all-time"',
  `total_deposited` decimal(15,2) NOT NULL DEFAULT '0.00',
  `deposit_count` int NOT NULL DEFAULT '0',
  `rank` int NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_period_unique` (`user_id`,`period`),
  KEY `period_rank` (`period`,`rank`)
) ENGINE=MyISAM AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `leaderboard_deposits`
--

INSERT INTO `leaderboard_deposits` (`id`, `user_id`, `period`, `total_deposited`, `deposit_count`, `rank`, `updated_at`) VALUES
(36, 6, 'all-tim', 50000.00, 1, 1, '2025-03-08 07:57:01'),
(35, 12, '2025-03', 20000.00, 1, 7, '2025-03-08 07:57:01'),
(34, 11, '2025-03', 20000.00, 1, 6, '2025-03-08 07:57:01'),
(33, 10, '2025-03', 20000.00, 1, 5, '2025-03-08 07:57:01'),
(31, 8, '2025-03', 20000.00, 1, 3, '2025-03-08 07:57:01'),
(32, 9, '2025-03', 20000.00, 1, 4, '2025-03-08 07:57:01'),
(41, 11, 'all-tim', 20000.00, 1, 6, '2025-03-08 07:57:01'),
(40, 10, 'all-tim', 20000.00, 1, 5, '2025-03-08 07:57:01'),
(39, 9, 'all-tim', 20000.00, 1, 4, '2025-03-08 07:57:01'),
(38, 8, 'all-tim', 20000.00, 1, 3, '2025-03-08 07:57:01'),
(37, 7, 'all-tim', 20000.00, 1, 2, '2025-03-08 07:57:01'),
(30, 7, '2025-03', 20000.00, 1, 2, '2025-03-08 07:57:01'),
(29, 6, '2025-03', 50000.00, 1, 1, '2025-03-08 07:57:01'),
(42, 12, 'all-tim', 20000.00, 1, 7, '2025-03-08 07:57:01');

-- --------------------------------------------------------

--
-- Table structure for table `payment_methods`
--

DROP TABLE IF EXISTS `payment_methods`;
CREATE TABLE IF NOT EXISTS `payment_methods` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `payment_type` enum('easypaisa','jazzcash') NOT NULL,
  `account_name` varchar(100) NOT NULL,
  `account_number` varchar(20) NOT NULL,
  `is_default` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_active` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=MyISAM AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `payment_methods`
--

INSERT INTO `payment_methods` (`id`, `user_id`, `payment_type`, `account_name`, `account_number`, `is_default`, `created_at`, `updated_at`, `is_active`) VALUES
(17, 13, 'jazzcash', 'first', '03196977218', 1, '2025-03-08 13:07:32', '2025-03-08 13:07:32', 1),
(16, 15, 'jazzcash', 'third', '03196977218', 1, '2025-03-08 12:48:03', '2025-03-08 12:48:03', 1),
(15, 14, 'easypaisa', 'second', '03196977218', 1, '2025-03-08 12:43:45', '2025-03-08 12:43:45', 1);

-- --------------------------------------------------------

--
-- Table structure for table `referrals`
--

DROP TABLE IF EXISTS `referrals`;
CREATE TABLE IF NOT EXISTS `referrals` (
  `id` int NOT NULL AUTO_INCREMENT,
  `referrer_id` int NOT NULL,
  `referred_id` int NOT NULL,
  `bonus_amount` decimal(15,2) NOT NULL DEFAULT '100.00',
  `status` enum('pending','paid') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `paid_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `referrer_id` (`referrer_id`),
  KEY `referred_id` (`referred_id`)
) ENGINE=MyISAM AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `referrals`
--

INSERT INTO `referrals` (`id`, `referrer_id`, `referred_id`, `bonus_amount`, `status`, `created_at`, `paid_at`) VALUES
(10, 14, 15, 100.00, 'paid', '2025-03-08 12:47:28', '2025-03-08 12:47:28'),
(9, 13, 14, 100.00, 'paid', '2025-03-08 12:42:09', '2025-03-08 12:42:09');

-- --------------------------------------------------------

--
-- Table structure for table `referral_commissions`
--

DROP TABLE IF EXISTS `referral_commissions`;
CREATE TABLE IF NOT EXISTS `referral_commissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `investment_id` int NOT NULL,
  `referrer_id` int NOT NULL,
  `referred_id` int NOT NULL,
  `investment_amount` decimal(15,2) NOT NULL,
  `commission_rate` decimal(5,2) NOT NULL,
  `commission_amount` decimal(15,2) NOT NULL,
  `status` enum('pending','paid','cancelled') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `paid_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `investment_id` (`investment_id`),
  KEY `referrer_id` (`referrer_id`),
  KEY `referred_id` (`referred_id`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `referral_commissions`
--

INSERT INTO `referral_commissions` (`id`, `investment_id`, `referrer_id`, `referred_id`, `investment_amount`, `commission_rate`, `commission_amount`, `status`, `created_at`, `paid_at`) VALUES
(4, 55, 13, 14, 5000.00, 10.00, 500.00, 'paid', '2025-03-08 12:53:02', '2025-03-08 12:53:02'),
(3, 54, 14, 15, 10000.00, 10.00, 1000.00, 'paid', '2025-03-08 12:51:13', '2025-03-08 12:51:13');

-- --------------------------------------------------------

--
-- Table structure for table `remember_tokens`
--

DROP TABLE IF EXISTS `remember_tokens`;
CREATE TABLE IF NOT EXISTS `remember_tokens` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `token` varchar(255) NOT NULL,
  `expiry_date` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stakes`
--

DROP TABLE IF EXISTS `stakes`;
CREATE TABLE IF NOT EXISTS `stakes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `stake_id` varchar(20) NOT NULL,
  `user_id` int NOT NULL,
  `plan_id` int NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `expected_return` decimal(15,2) NOT NULL,
  `status` enum('active','completed','withdrawn') NOT NULL DEFAULT 'active',
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `completion_date` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `stake_id` (`stake_id`),
  KEY `user_id` (`user_id`),
  KEY `plan_id` (`plan_id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `stakes`
--

INSERT INTO `stakes` (`id`, `stake_id`, `user_id`, `plan_id`, `amount`, `expected_return`, `status`, `start_date`, `end_date`, `completion_date`, `created_at`, `updated_at`) VALUES
(1, 'STK-422FA560', 13, 1, 5000.00, 5102.74, 'active', '2025-03-08 13:08:54', '2025-04-07 13:08:54', NULL, '2025-03-08 13:08:54', '2025-03-08 13:08:54');

-- --------------------------------------------------------

--
-- Table structure for table `staking_plans`
--

DROP TABLE IF EXISTS `staking_plans`;
CREATE TABLE IF NOT EXISTS `staking_plans` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text,
  `duration_days` int NOT NULL,
  `apy_rate` decimal(5,2) NOT NULL COMMENT 'Annual Percentage Yield',
  `min_amount` decimal(15,2) NOT NULL,
  `max_amount` decimal(15,2) DEFAULT NULL COMMENT 'NULL means no maximum',
  `early_withdrawal_fee` decimal(5,2) NOT NULL DEFAULT '10.00' COMMENT 'Percentage fee for early withdrawal',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `staking_plans`
--

INSERT INTO `staking_plans` (`id`, `name`, `description`, `duration_days`, `apy_rate`, `min_amount`, `max_amount`, `early_withdrawal_fee`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Silver Pool - 30 Days', 'Lock your funds for 30 days and earn 25% APY', 30, 25.00, 5000.00, 50000.00, 15.00, 1, '2025-03-07 15:22:06', '2025-03-07 15:22:06'),
(2, 'Gold Pool - 60 Days', 'Lock your funds for 60 days and earn 30% APY', 60, 30.00, 10000.00, 100000.00, 20.00, 1, '2025-03-07 15:22:06', '2025-03-07 15:22:06'),
(3, 'Platinum Pool - 90 Days', 'Lock your funds for 90 days and earn 40% APY', 90, 40.00, 25000.00, NULL, 25.00, 1, '2025-03-07 15:22:06', '2025-03-07 15:22:06');

-- --------------------------------------------------------

--
-- Table structure for table `support_responses`
--

DROP TABLE IF EXISTS `support_responses`;
CREATE TABLE IF NOT EXISTS `support_responses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ticket_id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `admin_id` int DEFAULT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ticket_id` (`ticket_id`),
  KEY `user_id` (`user_id`),
  KEY `admin_id` (`admin_id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `support_tickets`
--

DROP TABLE IF EXISTS `support_tickets`;
CREATE TABLE IF NOT EXISTS `support_tickets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `ticket_id` varchar(20) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `category` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `priority` enum('low','medium','high') DEFAULT 'medium',
  `status` enum('open','in_progress','resolved','closed') DEFAULT 'open',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ticket_id` (`ticket_id`),
  KEY `user_id` (`user_id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

DROP TABLE IF EXISTS `transactions`;
CREATE TABLE IF NOT EXISTS `transactions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `transaction_type` enum('deposit','withdrawal','investment','profit') NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `status` enum('pending','completed','failed') NOT NULL DEFAULT 'pending',
  `description` text,
  `reference_id` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=122 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`id`, `user_id`, `transaction_type`, `amount`, `status`, `description`, `reference_id`, `created_at`) VALUES
(121, 14, 'deposit', 5.00, 'completed', 'Daily Check-in Reward (Day 1)', 'CHECKIN-20250308-14', '2025-03-08 17:01:49'),
(120, 14, 'profit', 1065.00, 'completed', 'Sold 1 AlphaMiner Tokens (Profit: Rs:65.00)', 'AMR-SELL-1741452367-6309', '2025-03-08 16:46:07'),
(119, 14, 'investment', 1000.00, 'completed', 'Purchase of 1 AlphaMiner Tokens', 'AMR-1741452358-4208', '2025-03-08 16:45:58'),
(118, 14, 'profit', 1065.00, 'completed', 'Sold 1 AlphaMiner Tokens (Profit: Rs:65.00)', 'AMR-SELL-1741452346-2498', '2025-03-08 16:45:46'),
(117, 14, 'investment', 1000.00, 'completed', 'Purchase of 1 AlphaMiner Tokens', 'AMR-1741452318-5055', '2025-03-08 16:45:18'),
(116, 13, 'investment', 5000.00, 'completed', 'Staking Pool: Silver Pool - 30 Days', 'STK-422FA560', '2025-03-08 13:08:54'),
(115, 13, 'deposit', 5.00, 'completed', 'Daily Check-in Reward (Day 1)', 'CHECKIN-20250308-13', '2025-03-08 12:53:43'),
(114, 13, 'deposit', 500.00, 'completed', 'Investment Referral Commission', 'INVREF-55', '2025-03-08 12:53:02'),
(113, 14, 'investment', 5000.00, 'completed', 'Standard Plan Purchase', 'INV-89125', '2025-03-08 12:53:02'),
(112, 14, 'deposit', 1000.00, 'completed', 'Investment Referral Commission', 'INVREF-54', '2025-03-08 12:51:13'),
(111, 15, 'investment', 10000.00, 'completed', 'Premium Plan Purchase', 'INV-30787', '2025-03-08 12:51:13'),
(110, 14, 'deposit', 100.00, 'completed', 'Referral Bonus', 'REF-15', '2025-03-08 12:47:28'),
(109, 13, 'deposit', 100.00, 'completed', 'Referral Bonus', 'REF-14', '2025-03-08 12:42:09');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `referral_code` varchar(20) DEFAULT NULL,
  `referred_by` int DEFAULT NULL,
  `phone` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `registration_date` datetime NOT NULL,
  `last_login` datetime DEFAULT NULL,
  `status` enum('active','inactive','suspended') DEFAULT 'active',
  `admin_notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=MyISAM AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `email`, `referral_code`, `referred_by`, `phone`, `password`, `registration_date`, `last_login`, `status`, `admin_notes`, `created_at`) VALUES
(17, 'testing', 'testing@testing.com', 'WM92YKZH', NULL, '+923196977218', '$2y$10$py64aA3wq8RRfKosQ91tyu9FoHGXOcucTHn92HX14MPGvGbBSikQS', '2025-03-08 20:49:00', '2025-03-08 21:11:36', 'active', NULL, '2025-03-08 15:49:00'),
(16, 'Usama Bashir', 'jubranyounas@gmail.com', 'G5W2FCXJ', NULL, '03196977218', '$2y$10$whMEfe6EgqA8n3Nr0e6LUODJmq8QcoNxIjAzJcarH1U3drwD8ekMi', '2025-03-08 19:45:43', NULL, 'active', NULL, '2025-03-08 14:45:43'),
(15, 'third', 'third@third.com', '01VVLNQR', 14, '+923196977218', '$2y$10$ulZNqAr9ArOafaRsPH691ONZAr6T56B118b26f.dxQzlmSPxRBAXu', '2025-03-08 17:47:28', '2025-03-08 18:05:32', 'active', NULL, '2025-03-08 12:47:28'),
(14, 'second', 'second@second.com', '147QDKBN', 13, '+923196977218', '$2y$10$hGILqeIB0NGm.nlt9I6y8.86Wzp.rngUQSRgx33p/GsoWBxxS2gHy', '2025-03-08 17:42:09', '2025-03-08 21:12:17', 'active', NULL, '2025-03-08 12:42:09'),
(13, 'first', 'first@first.com', 'EL09K4SK', NULL, '+923196977218', '$2y$10$utWZ8y.Jzbvq.y/EV6YFmefTN7okYOTv0mb/R/EMVkPVy93y9G0s6', '2025-03-08 17:40:01', '2025-03-08 21:11:58', 'active', NULL, '2025-03-08 12:40:01');

-- --------------------------------------------------------

--
-- Table structure for table `user_settings`
--

DROP TABLE IF EXISTS `user_settings`;
CREATE TABLE IF NOT EXISTS `user_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `login_alerts` tinyint(1) DEFAULT '1',
  `deposit_alerts` tinyint(1) DEFAULT '1',
  `withdrawal_alerts` tinyint(1) DEFAULT '1',
  `investment_alerts` tinyint(1) DEFAULT '1',
  `promotional_emails` tinyint(1) DEFAULT '1',
  `theme` varchar(20) DEFAULT 'dark',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wallets`
--

DROP TABLE IF EXISTS `wallets`;
CREATE TABLE IF NOT EXISTS `wallets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `balance` decimal(15,2) DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=MyISAM AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `wallets`
--

INSERT INTO `wallets` (`id`, `user_id`, `balance`, `created_at`, `updated_at`) VALUES
(17, 17, 0.00, '2025-03-08 15:49:00', '2025-03-08 15:49:00'),
(16, 16, 0.00, '2025-03-08 14:45:43', '2025-03-08 14:45:43'),
(15, 15, 0.00, '2025-03-08 12:47:28', '2025-03-08 12:51:13'),
(14, 14, 235.00, '2025-03-08 12:42:09', '2025-03-08 17:01:49'),
(13, 13, 105.00, '2025-03-08 12:40:01', '2025-03-08 13:08:54');

-- --------------------------------------------------------

--
-- Table structure for table `withdrawals`
--

DROP TABLE IF EXISTS `withdrawals`;
CREATE TABLE IF NOT EXISTS `withdrawals` (
  `id` int NOT NULL AUTO_INCREMENT,
  `withdrawal_id` varchar(20) NOT NULL,
  `user_id` int NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `tax_amount` decimal(15,2) NOT NULL,
  `net_amount` decimal(15,2) NOT NULL,
  `payment_method_id` int NOT NULL,
  `account_number` varchar(50) NOT NULL,
  `account_name` varchar(100) NOT NULL,
  `payment_type` varchar(50) NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `notes` text,
  `processed_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `processed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `withdrawal_id` (`withdrawal_id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DELIMITER $$
--
-- Events
--
DROP EVENT IF EXISTS `update_leaderboards_daily`$$
CREATE DEFINER=`root`@`localhost` EVENT `update_leaderboards_daily` ON SCHEDULE EVERY 1 DAY STARTS '2025-03-08 12:47:58' ON COMPLETION NOT PRESERVE ENABLE DO CALL update_leaderboards()$$

DROP EVENT IF EXISTS `distribute_monthly_bonuses_event`$$
CREATE DEFINER=`root`@`localhost` EVENT `distribute_monthly_bonuses_event` ON SCHEDULE EVERY 1 MONTH STARTS '2025-03-09 12:47:58' ON COMPLETION NOT PRESERVE ENABLE DO BEGIN
  DECLARE last_month VARCHAR(7);
  SET last_month = DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 1 MONTH), '%Y-%m');
  
  -- Check if bonuses already distributed
  IF (SELECT COUNT(*) FROM leaderboard_bonuses WHERE bonus_month = last_month) = 0 THEN
    CALL distribute_monthly_bonuses(last_month);
  END IF;
END$$

DELIMITER ;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
