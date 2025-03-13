-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Mar 13, 2025 at 05:42 PM
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
DROP PROCEDURE IF EXISTS `apply_deposit_bonus`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `apply_deposit_bonus` (IN `p_user_id` INT, IN `p_deposit_amount` DECIMAL(15,2))   BEGIN
  DECLARE v_bonus_amount DECIMAL(15,2);
  
  -- Check if any bonus tier applies
  SELECT bonus_amount INTO v_bonus_amount
  FROM deposit_bonus_tiers
  WHERE p_deposit_amount >= min_amount 
  AND (max_amount IS NULL OR p_deposit_amount <= max_amount)
  AND is_active = 1
  ORDER BY min_amount DESC
  LIMIT 1;
  
  -- If bonus applies, add to wallet
  IF v_bonus_amount IS NOT NULL AND v_bonus_amount > 0 THEN
    -- Update user wallet
    UPDATE wallets SET balance = balance + v_bonus_amount, updated_at = NOW() 
    WHERE user_id = p_user_id;
    
    -- Record transaction
    INSERT INTO transactions (
      user_id, 
      transaction_type, 
      amount, 
      status, 
      description, 
      reference_id
    ) VALUES (
      p_user_id, 
      'deposit', 
      v_bonus_amount, 
      'completed', 
      CONCAT('Bonus for deposit of $', p_deposit_amount), 
      CONCAT('BONUS-', FLOOR(RAND() * 100000))
    );
  END IF;
END$$

DROP PROCEDURE IF EXISTS `build_referral_tree`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `build_referral_tree` (IN `p_user_id` INT, IN `p_referrer_id` INT)   BEGIN
  DECLARE v_level INT;
  DECLARE v_current_parent INT;
  DECLARE v_max_levels INT DEFAULT 10; -- Set a maximum depth for the tree to prevent infinite loops
  
  -- First, add the direct referral relationship (level 1)
  IF p_referrer_id IS NOT NULL THEN
    INSERT INTO referral_tree (user_id, parent_id, level)
    VALUES (p_user_id, p_referrer_id, 1);
    
    SET v_level = 2;
    SET v_current_parent = p_referrer_id;
    
    -- Then, build the tree up the chain (level 2, 3, etc.)
    tree_loop: WHILE v_level <= v_max_levels DO
      -- Find the referrer's referrer
      SELECT referred_by INTO v_current_parent
      FROM users
      WHERE id = v_current_parent;
      
      -- If we reached the top of the chain, exit
      IF v_current_parent IS NULL THEN
        LEAVE tree_loop;
      END IF;
      
      -- Add this relationship to the tree
      INSERT INTO referral_tree (user_id, parent_id, level)
      VALUES (p_user_id, v_current_parent, v_level);
      
      SET v_level = v_level + 1;
    END WHILE tree_loop;
  END IF;
END$$

DROP PROCEDURE IF EXISTS `distribute_monthly_bonuses`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `distribute_monthly_bonuses` (IN `bonus_month` VARCHAR(7))   BEGIN
  -- Same procedure with updated descriptions to use $ instead of ₹
  -- ...
END$$

DROP PROCEDURE IF EXISTS `process_ticket_profits`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `process_ticket_profits` ()   BEGIN
  DECLARE done INT DEFAULT FALSE;
  DECLARE v_purchase_id INT;
  DECLARE v_user_id INT;
  DECLARE v_expected_profit DECIMAL(15,2);
  DECLARE v_total_return DECIMAL(15,2);
  
  -- Cursor for active ticket purchases that have reached maturity date and profit not yet paid
  DECLARE cur CURSOR FOR 
    SELECT id, user_id, expected_profit, total_return 
    FROM ticket_purchases 
    WHERE status = 'active' 
    AND maturity_date <= NOW() 
    AND profit_paid = 0;
  
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
  
  OPEN cur;
  
  process_loop: LOOP
    FETCH cur INTO v_purchase_id, v_user_id, v_expected_profit, v_total_return;
    
    IF done THEN
      LEAVE process_loop;
    END IF;
    
    -- Update wallet balance
    UPDATE wallets SET 
      balance = balance + v_total_return,
      updated_at = NOW()
    WHERE user_id = v_user_id;
    
    -- Record transaction
    INSERT INTO transactions (
      user_id, 
      transaction_type, 
      amount, 
      status, 
      description, 
      reference_id
    ) VALUES (
      v_user_id, 
      'profit', 
      v_expected_profit, 
      'completed', 
      'Profit from ticket movie purchase', 
      CONCAT('TICKET-PROFIT-', v_purchase_id)
    );
    
    -- Update ticket purchase status
    UPDATE ticket_purchases SET 
      status = 'completed',
      profit_paid = 1,
      completion_date = NOW(),
      updated_at = NOW()
    WHERE id = v_purchase_id;
    
  END LOOP;
  
  CLOSE cur;
END$$

DROP PROCEDURE IF EXISTS `record_withdrawal_request`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `record_withdrawal_request` (IN `p_user_id` INT)   BEGIN
  DECLARE v_record_exists INT;
  DECLARE v_current_date DATE;
  
  -- Get current date
  SET v_current_date = CURDATE();
  
  -- Check if record exists for today
  SELECT COUNT(*) INTO v_record_exists
  FROM withdrawal_daily_limits
  WHERE user_id = p_user_id AND withdrawal_date = v_current_date;
  
  IF v_record_exists > 0 THEN
    -- Update the counter
    UPDATE withdrawal_daily_limits 
    SET request_count = request_count + 1
    WHERE user_id = p_user_id AND withdrawal_date = v_current_date;
  ELSE
    -- Create new record
    INSERT INTO withdrawal_daily_limits (user_id, withdrawal_date, request_count)
    VALUES (p_user_id, v_current_date, 1);
  END IF;
  
  -- If this is first withdrawal, mark it in first_deposits
  UPDATE first_deposits 
  SET first_withdrawal_made = 1 
  WHERE user_id = p_user_id AND first_withdrawal_made = 0;
END$$

DROP PROCEDURE IF EXISTS `select_top_depositor_investments`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `select_top_depositor_investments` ()   BEGIN
    DECLARE last_month INT;
    DECLARE last_year INT;
    
    -- Get previous month and year
    SET last_month = MONTH(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH));
    SET last_year = YEAR(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH));
    
    -- Check if top depositors already selected for last month
    IF (SELECT COUNT(*) FROM top_depositor_investments WHERE month = last_month AND year = last_year) = 0 THEN
        -- Insert top 3 depositors for previous month
        INSERT INTO top_depositor_investments 
            (month, year, user_id, position, total_deposit, prize_amount, status)
        SELECT 
            last_month AS month,
            last_year AS year,
            user_id,
            @rownum := @rownum + 1 AS position,
            total_deposited,
            CASE 
                WHEN @rownum = 1 THEN 2500.00
                WHEN @rownum = 2 THEN 2000.00
                WHEN @rownum = 3 THEN 1500.00
                ELSE 0.00
            END AS prize_amount,
            'pending' AS status
        FROM 
            (SELECT 
                d.user_id, 
                SUM(d.amount) AS total_deposited
            FROM 
                deposits d
            WHERE 
                d.status = 'approved' AND
                MONTH(d.created_at) = last_month AND
                YEAR(d.created_at) = last_year
            GROUP BY 
                d.user_id
            ORDER BY 
                total_deposited DESC
            LIMIT 3) AS top_users,
            (SELECT @rownum := 0) r;
    END IF;
END$$

DROP PROCEDURE IF EXISTS `track_first_deposit`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `track_first_deposit` (IN `p_user_id` INT, IN `p_deposit_amount` DECIMAL(15,2))   BEGIN
  DECLARE v_first_deposit_exists INT;
  
  -- Check if this is the first deposit
  SELECT COUNT(*) INTO v_first_deposit_exists 
  FROM first_deposits 
  WHERE user_id = p_user_id;
  
  IF v_first_deposit_exists = 0 THEN
    -- Record as first deposit
    INSERT INTO first_deposits (user_id, first_deposit_amount)
    VALUES (p_user_id, p_deposit_amount);
  END IF;
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

--
-- Functions
--
DROP FUNCTION IF EXISTS `is_withdrawal_eligible`$$
CREATE DEFINER=`root`@`localhost` FUNCTION `is_withdrawal_eligible` (`p_user_id` INT, `p_withdrawal_amount` DECIMAL(15,2)) RETURNS TINYINT(1) DETERMINISTIC BEGIN
  DECLARE v_first_deposit_amount DECIMAL(15,2);
  DECLARE v_first_withdrawal_made TINYINT(1);
  DECLARE v_daily_requests INT;
  DECLARE v_current_date DATE;
  DECLARE v_min_deposit_amount DECIMAL(15,2);
  
  -- Get minimum deposit amount from settings
  SELECT CAST(setting_value AS DECIMAL(15,2)) INTO v_min_deposit_amount
  FROM system_settings 
  WHERE setting_key = 'min_deposit_amount';
  
  -- Get current date
  SET v_current_date = CURDATE();
  
  -- Check if user already made a withdrawal request today
  SELECT IFNULL(request_count, 0) INTO v_daily_requests
  FROM withdrawal_daily_limits
  WHERE user_id = p_user_id AND withdrawal_date = v_current_date;
  
  -- If already made a request today, not eligible
  IF v_daily_requests > 0 THEN
    RETURN FALSE;
  END IF;
  
  -- Check first deposit details
  SELECT first_deposit_amount, first_withdrawal_made INTO v_first_deposit_amount, v_first_withdrawal_made
  FROM first_deposits
  WHERE user_id = p_user_id;
  
  -- If this is their first withdrawal, check 50% rule
  IF v_first_withdrawal_made = 0 THEN
    -- Check if amount is more than 50% of first deposit
    IF p_withdrawal_amount > (v_first_deposit_amount / 2) THEN
      RETURN FALSE;
    END IF;
  END IF;
  
  RETURN TRUE;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `admin_messages`
--

DROP TABLE IF EXISTS `admin_messages`;
CREATE TABLE IF NOT EXISTS `admin_messages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `admin_id` int NOT NULL,
  `user_id` int NOT NULL,
  `message` text NOT NULL,
  `sent_by` enum('admin','user') NOT NULL,
  `read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `admin_id` (`admin_id`),
  KEY `user_id` (`user_id`)
) ENGINE=MyISAM AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_payment_methods`
--

DROP TABLE IF EXISTS `admin_payment_methods`;
CREATE TABLE IF NOT EXISTS `admin_payment_methods` (
  `id` int NOT NULL AUTO_INCREMENT,
  `payment_type` enum('binance') NOT NULL,
  `account_name` varchar(100) NOT NULL,
  `account_number` varchar(100) NOT NULL,
  `additional_info` text,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `admin_payment_methods`
--

INSERT INTO `admin_payment_methods` (`id`, `payment_type`, `account_name`, `account_number`, `additional_info`, `is_active`, `created_at`) VALUES
(1, 'binance', 'Admin', 'TPD4HY9QsWrK6H2qS7iJbh3TSEYWuMtLRQ', 'Send to this Binance TRC20 address. Please include your username as reference.', 1, '2025-03-09 10:31:03');

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
(1, 'admin', 'Administrator', 'admin@autoproftx.com', '$2y$10$FcuowkyBFb7isUYWUPzNBujVZPsEYqfEW6WBNUmKp9zMDZ6Ktpi.a', 'super_admin', 'active', '2025-03-13 17:31:31', '2025-03-07 10:36:19');

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
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
) ENGINE=MyISAM AUTO_INCREMENT=381 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `backup_codes`
--

INSERT INTO `backup_codes` (`id`, `user_id`, `code`, `is_used`, `created_at`, `used_at`) VALUES
(285, 43, '48YF-8B4E-S6', 0, '2025-03-12 08:25:47', NULL),
(284, 43, 'JF4G-MESH-9Z', 0, '2025-03-12 08:25:47', NULL),
(283, 43, 'HDS6-GNSY-GY', 0, '2025-03-12 08:25:47', NULL),
(282, 43, 'JYUW-FYR6-FV', 0, '2025-03-12 08:25:47', NULL),
(281, 43, 'YTMG-N33F-8H', 0, '2025-03-12 08:25:47', NULL),
(280, 42, '8FMS-DT26-FX', 0, '2025-03-12 08:05:15', NULL),
(279, 42, 'V3P8-R8H2-9G', 0, '2025-03-12 08:05:15', NULL),
(278, 42, 'G6QN-XND2-Z4', 0, '2025-03-12 08:05:15', NULL),
(277, 42, 'P6NW-NBWY-2Y', 0, '2025-03-12 08:05:15', NULL),
(276, 42, 'KW8C-LBGU-TH', 0, '2025-03-12 08:05:15', NULL),
(275, 42, 'N3LM-6KA4-MF', 0, '2025-03-12 08:05:15', NULL),
(274, 42, 'VAK7-AXTP-QJ', 0, '2025-03-12 08:05:15', NULL),
(273, 42, 'UWA3-37PS-TA', 0, '2025-03-12 08:05:15', NULL),
(272, 42, 'SD2L-Y9AY-MK', 0, '2025-03-12 08:05:15', NULL),
(271, 42, 'U92U-52MM-U9', 0, '2025-03-12 08:05:15', NULL),
(270, 41, '6Y5R-G97R-H2', 0, '2025-03-12 07:50:03', NULL),
(269, 41, '4PDF-XKLW-YQ', 0, '2025-03-12 07:50:03', NULL),
(268, 41, 'XHV9-7V6V-BV', 0, '2025-03-12 07:50:03', NULL),
(267, 41, 'U2GH-UTBZ-JS', 0, '2025-03-12 07:50:03', NULL),
(266, 41, 'Y6LG-RPYP-3X', 0, '2025-03-12 07:50:03', NULL),
(265, 41, '8B4C-J49K-DM', 0, '2025-03-12 07:50:03', NULL),
(264, 41, 'N7SZ-4GUW-NP', 0, '2025-03-12 07:50:03', NULL),
(263, 41, 'AVXZ-JNWM-EZ', 0, '2025-03-12 07:50:03', NULL),
(262, 41, 'SHK6-DH3M-P2', 0, '2025-03-12 07:50:03', NULL),
(261, 41, 'MKCD-2464-YY', 0, '2025-03-12 07:50:03', NULL),
(260, 40, 'BKB8-DZRP-5J', 0, '2025-03-12 07:49:02', NULL),
(259, 40, '35KA-GEW8-EN', 0, '2025-03-12 07:49:02', NULL),
(258, 40, 'RETV-REHM-JA', 0, '2025-03-12 07:49:02', NULL),
(257, 40, 'R3AT-V4UQ-RC', 0, '2025-03-12 07:49:02', NULL),
(256, 40, '7RWS-FM5U-SN', 0, '2025-03-12 07:49:02', NULL),
(101, 25, 'NJKG-KE3X-ZE', 0, '2025-03-11 22:38:31', NULL),
(102, 25, 'NJJY-9FK9-N5', 0, '2025-03-11 22:38:31', NULL),
(103, 25, '4EBK-SQV7-VN', 0, '2025-03-11 22:38:31', NULL),
(104, 25, 'XJYL-GUTD-4B', 0, '2025-03-11 22:38:31', NULL),
(105, 25, 'AQTA-PEAF-Q3', 0, '2025-03-11 22:38:31', NULL),
(106, 25, 'DJDZ-D6FT-FW', 0, '2025-03-11 22:38:31', NULL),
(107, 25, 'K5Z3-WG5A-4S', 0, '2025-03-11 22:38:31', NULL),
(108, 25, 'BERS-BM9E-WA', 0, '2025-03-11 22:38:31', NULL),
(109, 25, 'TMWH-KVR2-YB', 0, '2025-03-11 22:38:31', NULL),
(110, 25, '833B-A8Q8-95', 0, '2025-03-11 22:38:31', NULL),
(111, 26, '57VP-BKMN-SA', 0, '2025-03-11 22:50:42', NULL),
(112, 26, '8K2A-7Z2V-CM', 0, '2025-03-11 22:50:42', NULL),
(113, 26, '2TNA-8T49-GV', 0, '2025-03-11 22:50:42', NULL),
(114, 26, '9ER5-UA2H-DD', 0, '2025-03-11 22:50:42', NULL),
(115, 26, 'C5HC-MEL9-KA', 0, '2025-03-11 22:50:42', NULL),
(116, 26, '98EP-MZTK-U5', 0, '2025-03-11 22:50:42', NULL),
(117, 26, 'X4FY-Q866-VX', 0, '2025-03-11 22:50:42', NULL),
(118, 26, 'WRU5-9KPJ-MF', 0, '2025-03-11 22:50:42', NULL),
(119, 26, 'LYP7-K2V7-AF', 0, '2025-03-11 22:50:42', NULL),
(120, 26, 'YZFW-P774-JZ', 0, '2025-03-11 22:50:42', NULL),
(121, 27, '6CZT-XHMX-JK', 0, '2025-03-11 22:53:42', NULL),
(122, 27, '9PU7-7Y4M-4J', 0, '2025-03-11 22:53:42', NULL),
(123, 27, '78AA-X68F-Z8', 0, '2025-03-11 22:53:42', NULL),
(124, 27, '9DLZ-MPPB-8H', 0, '2025-03-11 22:53:42', NULL),
(125, 27, 'D6XW-6G2M-RJ', 0, '2025-03-11 22:53:42', NULL),
(126, 27, 'JQ5C-X9HF-BL', 0, '2025-03-11 22:53:42', NULL),
(127, 27, 'WTB7-BGFT-LJ', 0, '2025-03-11 22:53:42', NULL),
(128, 27, 'S98Y-DYTZ-FS', 0, '2025-03-11 22:53:42', NULL),
(129, 27, 'DP7C-F6NC-TK', 0, '2025-03-11 22:53:42', NULL),
(130, 27, 'JN3G-B2L8-B3', 0, '2025-03-11 22:53:42', NULL),
(131, 28, 'SYUY-HETV-8D', 0, '2025-03-11 22:54:51', NULL),
(132, 28, 'BLU6-V792-XZ', 0, '2025-03-11 22:54:51', NULL),
(133, 28, 'D8H9-TWTY-TH', 0, '2025-03-11 22:54:51', NULL),
(134, 28, 'DBK2-J3DX-UY', 0, '2025-03-11 22:54:51', NULL),
(135, 28, 'JXJD-W9VT-RZ', 0, '2025-03-11 22:54:51', NULL),
(136, 28, 'M7HG-WSAV-GA', 0, '2025-03-11 22:54:51', NULL),
(137, 28, 'P4AS-NTSN-G2', 0, '2025-03-11 22:54:51', NULL),
(138, 28, 'EE3Q-PRQ3-EM', 0, '2025-03-11 22:54:51', NULL),
(139, 28, 'H3K4-24AZ-3W', 0, '2025-03-11 22:54:51', NULL),
(140, 28, 'YD84-2NWS-VF', 0, '2025-03-11 22:54:51', NULL),
(141, 29, 'C2WL-RAYZ-T2', 0, '2025-03-11 22:57:20', NULL),
(142, 29, 'NH45-8NH6-X9', 0, '2025-03-11 22:57:20', NULL),
(143, 29, 'XZGN-5EBF-EY', 0, '2025-03-11 22:57:20', NULL),
(144, 29, 'V7NC-TE2U-ZC', 0, '2025-03-11 22:57:20', NULL),
(145, 29, 'ZJRS-9S5T-8H', 0, '2025-03-11 22:57:20', NULL),
(146, 29, 'S9Y4-DXTQ-KM', 0, '2025-03-11 22:57:20', NULL),
(147, 29, 'QKV2-52YD-RF', 0, '2025-03-11 22:57:20', NULL),
(148, 29, 'PRH3-HZ49-Y6', 0, '2025-03-11 22:57:20', NULL),
(149, 29, 'DDZM-7ZCY-BU', 0, '2025-03-11 22:57:20', NULL),
(150, 29, 'UL32-Z2CA-ER', 0, '2025-03-11 22:57:20', NULL),
(151, 30, 'YWXX-WZXF-3B', 0, '2025-03-11 22:59:29', NULL),
(152, 30, 'YQWB-9SXG-U3', 0, '2025-03-11 22:59:29', NULL),
(153, 30, '5NPF-W2US-LV', 0, '2025-03-11 22:59:29', NULL),
(154, 30, 'QLDF-5FEF-E8', 0, '2025-03-11 22:59:29', NULL),
(155, 30, 'TH5N-XHUL-3R', 0, '2025-03-11 22:59:29', NULL),
(156, 30, '6LX6-8HSR-ZN', 0, '2025-03-11 22:59:29', NULL),
(157, 30, '63YA-4UFW-DF', 0, '2025-03-11 22:59:29', NULL),
(158, 30, 'MTH3-LUPX-55', 0, '2025-03-11 22:59:29', NULL),
(159, 30, 'JJ6H-SEZL-XK', 0, '2025-03-11 22:59:29', NULL),
(160, 30, 'PKF4-WP46-W2', 0, '2025-03-11 22:59:29', NULL),
(161, 31, 'G7KA-WC2E-55', 0, '2025-03-11 23:05:30', NULL),
(162, 31, 'XSDU-C9Z7-8B', 0, '2025-03-11 23:05:30', NULL),
(163, 31, 'E9FW-JB3E-FH', 0, '2025-03-11 23:05:30', NULL),
(164, 31, '3DV9-MSV4-K8', 0, '2025-03-11 23:05:30', NULL),
(165, 31, '8LMW-D9J3-KV', 0, '2025-03-11 23:05:30', NULL),
(166, 31, 'FCHC-48TJ-T3', 0, '2025-03-11 23:05:30', NULL),
(167, 31, 'TBPL-3A3J-FT', 0, '2025-03-11 23:05:30', NULL),
(168, 31, 'RXK8-XAYF-6S', 0, '2025-03-11 23:05:30', NULL),
(169, 31, 'L52J-B2UB-GK', 0, '2025-03-11 23:05:30', NULL),
(170, 31, 'YCWN-NPHD-LL', 0, '2025-03-11 23:05:30', NULL),
(171, 32, 'GW4P-GUMY-FZ', 0, '2025-03-11 23:08:35', NULL),
(172, 32, '2CWN-656V-AC', 0, '2025-03-11 23:08:35', NULL),
(173, 32, 'U4MU-NGPJ-JM', 0, '2025-03-11 23:08:35', NULL),
(174, 32, 'CE5Z-JKWQ-QY', 0, '2025-03-11 23:08:35', NULL),
(175, 32, 'AHB3-ARLQ-36', 0, '2025-03-11 23:08:35', NULL),
(176, 32, 'XHBU-ZQEK-WG', 0, '2025-03-11 23:08:35', NULL),
(177, 32, 'XNFR-AF9J-ZM', 0, '2025-03-11 23:08:35', NULL),
(178, 32, '326V-6W73-JV', 0, '2025-03-11 23:08:35', NULL),
(179, 32, '6QJR-PQSX-CC', 0, '2025-03-11 23:08:35', NULL),
(180, 32, 'NSH4-GSJE-DR', 0, '2025-03-11 23:08:35', NULL),
(181, 33, 'KXDS-V8CS-PW', 0, '2025-03-11 23:09:24', NULL),
(182, 33, 'NPPU-B4ZC-L9', 0, '2025-03-11 23:09:24', NULL),
(183, 33, 'ANQJ-MH48-P2', 0, '2025-03-11 23:09:24', NULL),
(184, 33, '7X5V-6A7T-EM', 0, '2025-03-11 23:09:24', NULL),
(185, 33, 'ZKY5-MVCK-ZB', 0, '2025-03-11 23:09:24', NULL),
(186, 33, 'SXYA-CEY8-QE', 0, '2025-03-11 23:09:24', NULL),
(187, 33, 'WRVF-V767-C9', 0, '2025-03-11 23:09:24', NULL),
(188, 33, 'Y3BH-EX69-D6', 0, '2025-03-11 23:09:24', NULL),
(189, 33, 'VTL5-QGGU-TH', 0, '2025-03-11 23:09:24', NULL),
(190, 33, '6LCF-ASFF-GZ', 0, '2025-03-11 23:09:24', NULL),
(191, 34, 'DLK2-YHKP-RB', 0, '2025-03-11 23:15:16', NULL),
(192, 34, '4UTD-UZ6W-R4', 0, '2025-03-11 23:15:16', NULL),
(193, 34, '77W6-2272-Y6', 0, '2025-03-11 23:15:16', NULL),
(194, 34, 'LPT8-V2ZH-BU', 0, '2025-03-11 23:15:16', NULL),
(195, 34, 'BXSE-J2EF-TN', 0, '2025-03-11 23:15:16', NULL),
(196, 34, 'FPCY-DD2X-SQ', 0, '2025-03-11 23:15:16', NULL),
(197, 34, 'CRZH-C7UB-GA', 0, '2025-03-11 23:15:16', NULL),
(198, 34, '8FHR-C556-R5', 0, '2025-03-11 23:15:16', NULL),
(199, 34, 'SQQJ-5K8D-ZW', 0, '2025-03-11 23:15:16', NULL),
(200, 34, 'L5JX-A77M-65', 0, '2025-03-11 23:15:16', NULL),
(201, 35, '4FMU-FLXE-FH', 0, '2025-03-11 23:16:16', NULL),
(202, 35, 'WD27-FGZF-S6', 0, '2025-03-11 23:16:16', NULL),
(203, 35, 'V263-PE63-6V', 0, '2025-03-11 23:16:16', NULL),
(204, 35, 'BYT6-YUBS-CX', 0, '2025-03-11 23:16:16', NULL),
(205, 35, 'Q2N5-HRB8-JK', 0, '2025-03-11 23:16:16', NULL),
(206, 35, 'K3BW-S2BB-PC', 0, '2025-03-11 23:16:16', NULL),
(207, 35, 'NA8K-FEMG-PR', 0, '2025-03-11 23:16:16', NULL),
(208, 35, 'RQZC-GATT-NK', 0, '2025-03-11 23:16:16', NULL),
(209, 35, '4RM3-FR3P-YU', 0, '2025-03-11 23:16:16', NULL),
(210, 35, 'XNV2-2LCV-Y5', 0, '2025-03-11 23:16:16', NULL),
(211, 36, 'GWA4-HKFS-Z9', 0, '2025-03-11 23:19:33', NULL),
(212, 36, '8ESK-UWAG-FH', 0, '2025-03-11 23:19:33', NULL),
(213, 36, 'G5AJ-UW5D-MH', 0, '2025-03-11 23:19:33', NULL),
(214, 36, '3KSJ-HDHE-8V', 0, '2025-03-11 23:19:33', NULL),
(215, 36, 'PLBG-UU7L-DW', 0, '2025-03-11 23:19:33', NULL),
(216, 36, 'FL2T-HTL2-SU', 0, '2025-03-11 23:19:33', NULL),
(217, 36, 'S273-TRFX-EC', 0, '2025-03-11 23:19:33', NULL),
(218, 36, '86NN-E2MN-KJ', 0, '2025-03-11 23:19:33', NULL),
(219, 36, '77F8-CAV7-XK', 0, '2025-03-11 23:19:33', NULL),
(220, 36, 'GV5Y-2KC9-DS', 0, '2025-03-11 23:19:33', NULL),
(221, 37, '2MV2-HXWW-5P', 0, '2025-03-11 23:21:39', NULL),
(222, 37, 'LDY4-TWNH-4Y', 0, '2025-03-11 23:21:39', NULL),
(223, 37, 'Q6PL-GM3E-FX', 0, '2025-03-11 23:21:39', NULL),
(224, 37, 'RX9A-5PD5-TW', 0, '2025-03-11 23:21:39', NULL),
(225, 37, 'AE8L-4PLR-4B', 0, '2025-03-11 23:21:39', NULL),
(255, 40, 'H93G-9SYZ-AE', 0, '2025-03-12 07:49:02', NULL),
(254, 40, 'QNDZ-AJ8E-LB', 0, '2025-03-12 07:49:02', NULL),
(253, 40, 'VY7V-94A3-CR', 0, '2025-03-12 07:49:02', NULL),
(252, 40, 'SQVP-XEKT-RW', 0, '2025-03-12 07:49:02', NULL),
(251, 40, 'GMXQ-PDAQ-7A', 0, '2025-03-12 07:49:02', NULL),
(286, 43, '6576-TRB3-AL', 0, '2025-03-12 08:25:47', NULL),
(287, 43, 'WCW7-X5W9-72', 0, '2025-03-12 08:25:47', NULL),
(288, 43, 'HUEJ-QB5K-C7', 0, '2025-03-12 08:25:47', NULL),
(289, 43, 'XVHP-4A2P-J4', 0, '2025-03-12 08:25:47', NULL),
(290, 43, '64WC-HVQJ-24', 0, '2025-03-12 08:25:47', NULL),
(291, 44, '3ZEA-36RM-ZQ', 0, '2025-03-12 08:26:38', NULL),
(292, 44, 'FKBK-LMVJ-VM', 0, '2025-03-12 08:26:38', NULL),
(293, 44, 'HUKA-9528-QA', 0, '2025-03-12 08:26:38', NULL),
(294, 44, 'Q3QE-8AST-RF', 0, '2025-03-12 08:26:38', NULL),
(295, 44, 'Y3WT-QHUT-9K', 0, '2025-03-12 08:26:38', NULL),
(296, 44, 'FT4N-UQPA-GW', 0, '2025-03-12 08:26:38', NULL),
(297, 44, 'VLTU-YE4V-3W', 0, '2025-03-12 08:26:38', NULL),
(298, 44, '5A9V-RCWT-SH', 0, '2025-03-12 08:26:38', NULL),
(299, 44, 'MGYM-CJM9-JU', 0, '2025-03-12 08:26:38', NULL),
(300, 44, '2CHP-G2DB-M4', 0, '2025-03-12 08:26:38', NULL),
(301, 45, 'VFPV-J8K9-6V', 0, '2025-03-12 08:36:58', NULL),
(302, 45, '9J77-XFVS-G5', 0, '2025-03-12 08:36:58', NULL),
(303, 45, 'U5NS-MX2U-EV', 0, '2025-03-12 08:36:58', NULL),
(304, 45, 'LQCG-Y67S-6M', 0, '2025-03-12 08:36:58', NULL),
(305, 45, 'TRQ7-C3RD-7B', 0, '2025-03-12 08:36:58', NULL),
(306, 45, 'KF4P-LRCT-3W', 0, '2025-03-12 08:36:58', NULL),
(307, 45, 'UN78-QLAC-92', 0, '2025-03-12 08:36:58', NULL),
(308, 45, 'WUY2-GEPK-DU', 0, '2025-03-12 08:36:58', NULL),
(309, 45, 'HFTV-2F5Y-4Y', 0, '2025-03-12 08:36:58', NULL),
(310, 45, 'VF5A-2KM9-JD', 0, '2025-03-12 08:36:58', NULL),
(311, 46, 'MKLJ-5QL3-VQ', 0, '2025-03-12 08:45:46', NULL),
(312, 46, 'GC5W-XATE-U2', 0, '2025-03-12 08:45:46', NULL),
(313, 46, 'XA3S-MH4R-MU', 0, '2025-03-12 08:45:46', NULL),
(314, 46, 'NB3P-XMNY-HV', 0, '2025-03-12 08:45:46', NULL),
(315, 46, '638D-WFEF-CM', 0, '2025-03-12 08:45:46', NULL),
(316, 46, '7NFL-RB9P-7W', 0, '2025-03-12 08:45:46', NULL),
(317, 46, 'FS7N-6HSX-QV', 0, '2025-03-12 08:45:46', NULL),
(318, 46, 'DYD7-G3C4-4Q', 0, '2025-03-12 08:45:46', NULL),
(319, 46, 'F8K4-VBPL-D7', 0, '2025-03-12 08:45:46', NULL),
(320, 46, '32HU-GC9M-L8', 0, '2025-03-12 08:45:46', NULL),
(321, 47, 'FQQU-EHTP-KT', 0, '2025-03-12 08:52:55', NULL),
(322, 47, 'HYAL-YZ49-TK', 0, '2025-03-12 08:52:55', NULL),
(323, 47, '4RSX-APA6-TK', 0, '2025-03-12 08:52:55', NULL),
(324, 47, 'UGTS-3LAP-2L', 0, '2025-03-12 08:52:55', NULL),
(325, 47, '2DL2-DQXR-UD', 0, '2025-03-12 08:52:55', NULL),
(326, 47, 'HTTV-AJVJ-UW', 0, '2025-03-12 08:52:55', NULL),
(327, 47, '8XWA-H9V8-HE', 0, '2025-03-12 08:52:55', NULL),
(328, 47, 'T9CN-VANC-3X', 0, '2025-03-12 08:52:55', NULL),
(329, 47, 'P2W9-XCVY-E4', 0, '2025-03-12 08:52:55', NULL),
(330, 47, 'FT63-TS5W-M9', 0, '2025-03-12 08:52:55', NULL),
(331, 48, '7JSY-ZT5C-85', 0, '2025-03-12 09:01:45', NULL),
(332, 48, 'WB9Q-JJYR-79', 0, '2025-03-12 09:01:45', NULL),
(333, 48, 'PKBR-NBEM-MF', 0, '2025-03-12 09:01:45', NULL),
(334, 48, '9GW2-LXSV-35', 0, '2025-03-12 09:01:45', NULL),
(335, 48, 'E4TZ-38ZA-5V', 0, '2025-03-12 09:01:45', NULL),
(336, 48, '2G9E-46EB-DX', 0, '2025-03-12 09:01:45', NULL),
(337, 48, 'FV8N-XCL4-KV', 0, '2025-03-12 09:01:45', NULL),
(338, 48, 'WQUW-XM84-T9', 0, '2025-03-12 09:01:45', NULL),
(339, 48, 'QNFP-ZERY-PS', 0, '2025-03-12 09:01:45', NULL),
(340, 48, 'J4YM-J7MV-WX', 0, '2025-03-12 09:01:45', NULL),
(341, 49, 'VJ85-QAL8-7W', 0, '2025-03-12 09:02:22', NULL),
(342, 49, '35A8-PUZV-ZB', 0, '2025-03-12 09:02:22', NULL),
(343, 49, '4EBV-WAG2-QW', 0, '2025-03-12 09:02:22', NULL),
(344, 49, 'YBDS-PZPL-FR', 0, '2025-03-12 09:02:22', NULL),
(345, 49, 'YAGP-AN2S-85', 0, '2025-03-12 09:02:22', NULL),
(346, 49, 'YEY7-NHA2-39', 0, '2025-03-12 09:02:22', NULL),
(347, 49, '83HH-7QUD-J2', 0, '2025-03-12 09:02:22', NULL),
(348, 49, 'BTGM-F8NM-P2', 0, '2025-03-12 09:02:22', NULL),
(349, 49, 'C44Z-UBC4-ZT', 0, '2025-03-12 09:02:22', NULL),
(350, 49, '53F3-FZF5-S5', 0, '2025-03-12 09:02:22', NULL),
(370, 51, 'S6GN-MBBL-V9', 0, '2025-03-13 17:28:52', NULL),
(369, 51, 'BM97-VTXW-3F', 0, '2025-03-13 17:28:52', NULL),
(368, 51, 'AXMJ-4G74-NR', 0, '2025-03-13 17:28:52', NULL),
(367, 51, 'RQHX-2YL3-85', 0, '2025-03-13 17:28:52', NULL),
(366, 51, '4VCX-BGM9-SV', 0, '2025-03-13 17:28:52', NULL),
(365, 51, 'FU7U-UGF2-SS', 0, '2025-03-13 17:28:52', NULL),
(364, 51, 'PFR2-Q92H-M2', 0, '2025-03-13 17:28:52', NULL),
(363, 51, 'HRDM-XYNF-DQ', 0, '2025-03-13 17:28:52', NULL),
(362, 51, 'AWXE-8S6D-HT', 0, '2025-03-13 17:28:52', NULL),
(361, 51, 'L9S2-46JX-RQ', 0, '2025-03-13 17:28:52', NULL),
(371, 52, '5T3S-AJGV-N5', 0, '2025-03-13 17:29:34', NULL),
(372, 52, '5S78-WFZV-JA', 0, '2025-03-13 17:29:34', NULL),
(373, 52, '5XWL-MNVX-7Y', 0, '2025-03-13 17:29:34', NULL),
(374, 52, 'J9A6-3AKE-67', 0, '2025-03-13 17:29:34', NULL),
(375, 52, 'Z9ZV-XTWN-MJ', 0, '2025-03-13 17:29:34', NULL),
(376, 52, 'ZBLQ-23EV-FZ', 0, '2025-03-13 17:29:34', NULL),
(377, 52, 'P9HL-6V8F-V6', 0, '2025-03-13 17:29:34', NULL),
(378, 52, '8CHG-7TJL-S5', 0, '2025-03-13 17:29:34', NULL),
(379, 52, '9ZZ7-WJ9F-6Z', 0, '2025-03-13 17:29:34', NULL),
(380, 52, 'PRR8-DR4A-HW', 0, '2025-03-13 17:29:34', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `betting_games`
--

DROP TABLE IF EXISTS `betting_games`;
CREATE TABLE IF NOT EXISTS `betting_games` (
  `id` int NOT NULL AUTO_INCREMENT,
  `game_name` varchar(100) NOT NULL,
  `description` text,
  `min_bet` decimal(10,2) NOT NULL,
  `max_bet` decimal(10,2) NOT NULL,
  `house_edge` decimal(5,4) NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `betting_games`
--

INSERT INTO `betting_games` (`id`, `game_name`, `description`, `min_bet`, `max_bet`, `house_edge`, `is_active`) VALUES
(1, 'Lucky Wheel', 'Spin the wheel and win big with multipliers up to 50x your bet!', 1.00, 100.00, 0.1200, 1),
(2, 'Coin Flip Streak', 'Predict a series of coin flips and multiply your winnings with each correct guess!', 1.00, 50.00, 0.1500, 1),
(3, 'Hidden Number', 'Guess the hidden number between 1-100 and win up to 95x your bet!', 1.00, 20.00, 0.1800, 1),
(4, 'Crash Game', 'Watch the multiplier rise and cash out before it crashes! The longer you wait, the bigger the potential win!', 1.00, 200.00, 0.1300, 1);

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
(1, 1, 0.25, 1),
(2, 2, 0.40, 1),
(3, 3, 0.55, 1),
(4, 4, 0.70, 1),
(5, 5, 0.80, 1),
(6, 6, 0.85, 1),
(7, 7, 0.90, 1),
(8, 14, 0.95, 1),
(9, 21, 0.97, 1),
(10, 30, 1.00, 1);

-- --------------------------------------------------------

--
-- Table structure for table `crash_games`
--

DROP TABLE IF EXISTS `crash_games`;
CREATE TABLE IF NOT EXISTS `crash_games` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `bet_amount` decimal(15,2) NOT NULL,
  `crash_point` decimal(15,2) NOT NULL,
  `cashout_multiplier` decimal(15,2) DEFAULT NULL,
  `auto_cashout` decimal(15,2) DEFAULT NULL,
  `status` enum('active','cashed_out','crashed') NOT NULL DEFAULT 'active',
  `winnings` decimal(15,2) DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
) ENGINE=MyISAM AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `daily_checkins`
--

INSERT INTO `daily_checkins` (`id`, `user_id`, `checkin_date`, `streak_count`, `reward_amount`, `created_at`) VALUES
(15, 51, '2025-03-13', 1, 0.25, '2025-03-13 17:29:03');

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
) ENGINE=MyISAM AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `deposits`
--

INSERT INTO `deposits` (`id`, `user_id`, `amount`, `payment_method_id`, `admin_payment_id`, `transaction_id`, `proof_file`, `notes`, `status`, `created_at`, `processed_at`, `admin_notes`) VALUES
(29, 52, 30.00, 32, 1, '12345678', 'proof_52_1741887076.png', '', 'approved', '2025-03-13 17:31:16', '2025-03-13 17:31:41', '');

-- --------------------------------------------------------

--
-- Table structure for table `deposit_bonus_tiers`
--

DROP TABLE IF EXISTS `deposit_bonus_tiers`;
CREATE TABLE IF NOT EXISTS `deposit_bonus_tiers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `min_amount` decimal(15,2) NOT NULL,
  `max_amount` decimal(15,2) DEFAULT NULL,
  `bonus_amount` decimal(15,2) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `deposit_bonus_tiers`
--

INSERT INTO `deposit_bonus_tiers` (`id`, `min_amount`, `max_amount`, `bonus_amount`, `is_active`, `created_at`, `updated_at`) VALUES
(6, 1000.00, NULL, 200.00, 1, '2025-03-10 10:57:30', '2025-03-10 10:57:30'),
(5, 500.00, 999.99, 100.00, 1, '2025-03-10 10:57:30', '2025-03-10 10:57:30'),
(4, 250.00, 499.99, 50.00, 1, '2025-03-10 10:57:30', '2025-03-10 10:57:30'),
(3, 100.00, 249.99, 20.00, 1, '2025-03-10 10:57:30', '2025-03-10 10:57:30'),
(2, 50.00, 99.99, 10.00, 1, '2025-03-10 10:57:30', '2025-03-10 10:57:30'),
(1, 30.00, 49.99, 5.00, 1, '2025-03-10 10:57:30', '2025-03-10 10:57:30');

-- --------------------------------------------------------

--
-- Table structure for table `deposit_contest_winners`
--

DROP TABLE IF EXISTS `deposit_contest_winners`;
CREATE TABLE IF NOT EXISTS `deposit_contest_winners` (
  `id` int NOT NULL AUTO_INCREMENT,
  `month` int NOT NULL,
  `year` int NOT NULL,
  `user_id` int NOT NULL,
  `position` int NOT NULL,
  `total_deposit` decimal(15,2) NOT NULL,
  `prize_amount` decimal(15,2) NOT NULL,
  `status` enum('pending','paid') NOT NULL DEFAULT 'pending',
  `paid_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `month_year_position` (`month`,`year`,`position`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `first_deposits`
--

DROP TABLE IF EXISTS `first_deposits`;
CREATE TABLE IF NOT EXISTS `first_deposits` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `first_deposit_amount` decimal(15,2) NOT NULL,
  `first_withdrawal_made` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`)
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `first_deposits`
--

INSERT INTO `first_deposits` (`id`, `user_id`, `first_deposit_amount`, `first_withdrawal_made`, `created_at`, `updated_at`) VALUES
(6, 52, 30.00, 0, '2025-03-13 17:31:16', '2025-03-13 17:31:16');

-- --------------------------------------------------------

--
-- Table structure for table `game_history`
--

DROP TABLE IF EXISTS `game_history`;
CREATE TABLE IF NOT EXISTS `game_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `game_name` varchar(255) NOT NULL,
  `bet_amount` decimal(15,2) NOT NULL,
  `result` enum('win','lose') NOT NULL,
  `winnings` decimal(15,2) NOT NULL,
  `details` text,
  `played_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=130 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
) ENGINE=MyISAM AUTO_INCREMENT=59 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
(4, 'Standard', 'Standard investment plan with balanced returns', 10.00, 500.00, 0.40, 1, 1.00, 1, '2025-03-07 14:10:42', '2025-03-11 22:23:11'),
(3, 'Professional', 'Professional investment plan with 30% referral commission', 17.00, 1000.00, 0.20, 1, 2.00, 1, '2025-03-07 12:37:00', '2025-03-12 07:46:54'),
(1, 'Basic', 'Basic investment plan with 10% referral commission', 35.00, 1500.00, 0.10, 1, 3.00, 1, '2025-03-07 12:37:00', '2025-03-12 07:46:59'),
(2, 'Premium', 'Premium investment plan with 20% referral commission', 71.00, 2000.00, 0.50, 1, 6.00, 1, '2025-03-07 12:37:00', '2025-03-12 07:47:06'),
(5, 'Custom', 'Create your own custom investment plan', 91.00, NULL, 0.25, 1, 10.00, 1, '2025-03-07 14:11:29', '2025-03-12 07:47:14');

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
) ENGINE=MyISAM AUTO_INCREMENT=53 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `movie_tickets`
--

DROP TABLE IF EXISTS `movie_tickets`;
CREATE TABLE IF NOT EXISTS `movie_tickets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `price` decimal(15,2) NOT NULL,
  `image` varchar(255) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `movie_tickets`
--

INSERT INTO `movie_tickets` (`id`, `title`, `description`, `price`, `image`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Inception', 'A mind-bending thriller where a skilled thief, who enters the dreams of others, must plant an idea in someone\'s subconscious while facing his own haunting past.', 10.00, 'ticket_1741638633_3004.jpg', 'active', '2025-03-10 20:30:33', '2025-03-10 20:30:33'),
(2, 'Avengers: Endgame', 'Earth’s mightiest heroes assemble one last time to undo the devastation caused by Thanos, leading to an epic battle that determines the fate of the universe.', 20.00, 'ticket_1741638691_9290.jpg', 'active', '2025-03-10 20:31:31', '2025-03-10 20:31:31'),
(3, 'The Dark Knight', 'Batman faces his greatest challenge as the Joker emerges, causing chaos in Gotham City. A gripping battle between justice and anarchy unfolds.', 30.00, 'ticket_1741638724_8587.jpg', 'active', '2025-03-10 20:32:04', '2025-03-10 20:32:04'),
(4, 'Interstellar', 'A team of astronauts ventures beyond our galaxy in search of a new home for humanity, facing time dilation, black holes, and the unknown.', 40.00, 'ticket_1741638753_3298.jpg', 'active', '2025-03-10 20:32:33', '2025-03-10 20:32:33'),
(5, 'Jurassic World', 'A futuristic theme park featuring real dinosaurs goes out of control when a genetically modified creature escapes, putting everyone in danger.', 50.00, 'ticket_1741638780_5851.jpg', 'active', '2025-03-10 20:33:00', '2025-03-10 20:33:00');

-- --------------------------------------------------------

--
-- Table structure for table `payment_methods`
--

DROP TABLE IF EXISTS `payment_methods`;
CREATE TABLE IF NOT EXISTS `payment_methods` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `payment_type` varchar(50) NOT NULL,
  `account_name` varchar(100) NOT NULL,
  `account_number` varchar(20) NOT NULL,
  `is_default` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_active` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=MyISAM AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `payment_methods`
--

INSERT INTO `payment_methods` (`id`, `user_id`, `payment_type`, `account_name`, `account_number`, `is_default`, `created_at`, `updated_at`, `is_active`) VALUES
(32, 52, 'binance', 'second', 'TPD4HY9QsWrK6H2qS7iJ', 1, '2025-03-13 17:30:19', '2025-03-13 17:30:19', 1);

-- --------------------------------------------------------

--
-- Table structure for table `referrals`
--

DROP TABLE IF EXISTS `referrals`;
CREATE TABLE IF NOT EXISTS `referrals` (
  `id` int NOT NULL AUTO_INCREMENT,
  `referrer_id` int NOT NULL,
  `referred_id` int NOT NULL,
  `bonus_amount` decimal(15,2) NOT NULL DEFAULT '5.00',
  `status` enum('pending','paid') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `paid_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `referrer_id` (`referrer_id`),
  KEY `referred_id` (`referred_id`)
) ENGINE=MyISAM AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `referrals`
--

INSERT INTO `referrals` (`id`, `referrer_id`, `referred_id`, `bonus_amount`, `status`, `created_at`, `paid_at`) VALUES
(33, 51, 52, 5.00, 'pending', '2025-03-13 17:29:33', NULL);

--
-- Triggers `referrals`
--
DROP TRIGGER IF EXISTS `before_referral_insert`;
DELIMITER $$
CREATE TRIGGER `before_referral_insert` BEFORE INSERT ON `referrals` FOR EACH ROW BEGIN
    SET NEW.bonus_amount = 5.00;
END
$$
DELIMITER ;

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

-- --------------------------------------------------------

--
-- Table structure for table `referral_structure`
--

DROP TABLE IF EXISTS `referral_structure`;
CREATE TABLE IF NOT EXISTS `referral_structure` (
  `id` int NOT NULL AUTO_INCREMENT,
  `level` int NOT NULL,
  `commission_rate` decimal(5,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `level` (`level`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `referral_structure`
--

INSERT INTO `referral_structure` (`id`, `level`, `commission_rate`, `created_at`, `updated_at`) VALUES
(1, 1, 10.00, '2025-03-10 17:20:44', '2025-03-10 17:20:44'),
(2, 2, 5.00, '2025-03-10 17:20:44', '2025-03-10 17:20:44'),
(3, 3, 2.50, '2025-03-10 17:20:44', '2025-03-10 17:20:44'),
(4, 4, 1.25, '2025-03-10 17:20:44', '2025-03-10 17:20:44'),
(5, 5, 0.62, '2025-03-10 17:20:44', '2025-03-10 17:20:44');

-- --------------------------------------------------------

--
-- Table structure for table `referral_tree`
--

DROP TABLE IF EXISTS `referral_tree`;
CREATE TABLE IF NOT EXISTS `referral_tree` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `parent_id` int NOT NULL,
  `level` int NOT NULL COMMENT 'Level in the referral tree',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_parent_unique` (`user_id`,`parent_id`),
  KEY `user_id` (`user_id`),
  KEY `parent_id` (`parent_id`)
) ENGINE=MyISAM AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `referral_tree`
--

INSERT INTO `referral_tree` (`id`, `user_id`, `parent_id`, `level`, `created_at`) VALUES
(24, 52, 51, 1, '2025-03-13 17:29:33');

-- --------------------------------------------------------

--
-- Table structure for table `referral_vault`
--

DROP TABLE IF EXISTS `referral_vault`;
CREATE TABLE IF NOT EXISTS `referral_vault` (
  `id` int NOT NULL AUTO_INCREMENT,
  `referrer_id` int NOT NULL,
  `referred_id` int NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `status` enum('pending','claimed') NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL,
  `claimed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `referrer_id` (`referrer_id`),
  KEY `referred_id` (`referred_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
) ENGINE=MyISAM AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `remember_tokens`
--

INSERT INTO `remember_tokens` (`id`, `user_id`, `token`, `expiry_date`, `created_at`) VALUES
(15, 51, '34a50da8f66e373a36977cecec5e0723601f77ae157441d5149ad67aa2db5a28', '2025-04-12 22:28:57', '2025-03-13 17:28:57');

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
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
(1, 'Silver Pool - 30 Days', 'Lock your funds for 30 days and earn 25% APY', 30, 25.00, 18.00, 10000000.00, 15.00, 1, '2025-03-07 15:22:06', '2025-03-09 19:49:53'),
(2, 'Gold Pool - 60 Days', 'Lock your funds for 60 days and earn 30% APY', 60, 30.00, 35.00, 100000.00, 20.00, 1, '2025-03-07 15:22:06', '2025-03-09 19:50:22'),
(3, 'Platinum Pool - 90 Days', 'Lock your funds for 90 days and earn 40% APY', 90, 40.00, 90.00, NULL, 25.00, 1, '2025-03-07 15:22:06', '2025-03-09 19:50:36');

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
-- Table structure for table `system_settings`
--

DROP TABLE IF EXISTS `system_settings`;
CREATE TABLE IF NOT EXISTS `system_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=MyISAM AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `created_at`, `updated_at`) VALUES
(13, 'min_deposit_amount', '30', '2025-03-13 17:30:23', '2025-03-13 17:30:23');

-- --------------------------------------------------------

--
-- Table structure for table `ticket_movies`
--

DROP TABLE IF EXISTS `ticket_movies`;
CREATE TABLE IF NOT EXISTS `ticket_movies` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text,
  `price` decimal(15,2) NOT NULL,
  `image` varchar(255) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ticket_purchases`
--

DROP TABLE IF EXISTS `ticket_purchases`;
CREATE TABLE IF NOT EXISTS `ticket_purchases` (
  `id` int NOT NULL AUTO_INCREMENT,
  `purchase_id` varchar(20) NOT NULL,
  `user_id` int NOT NULL,
  `ticket_id` int NOT NULL,
  `quantity` int NOT NULL DEFAULT '1',
  `unit_price` decimal(15,2) NOT NULL,
  `total_amount` decimal(15,2) NOT NULL,
  `expected_profit` decimal(15,2) NOT NULL,
  `total_return` decimal(15,2) NOT NULL,
  `status` enum('active','completed','cancelled') NOT NULL DEFAULT 'active',
  `purchase_date` datetime NOT NULL,
  `maturity_date` datetime NOT NULL,
  `completion_date` datetime DEFAULT NULL,
  `profit_paid` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `purchase_id` (`purchase_id`),
  KEY `user_id` (`user_id`),
  KEY `ticket_id` (`ticket_id`)
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `top_depositor_investments`
--

DROP TABLE IF EXISTS `top_depositor_investments`;
CREATE TABLE IF NOT EXISTS `top_depositor_investments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `month` int NOT NULL,
  `year` int NOT NULL,
  `user_id` int NOT NULL,
  `position` int NOT NULL,
  `total_deposit` decimal(15,2) NOT NULL,
  `prize_amount` decimal(15,2) NOT NULL,
  `bonus_percentage` decimal(5,2) DEFAULT NULL,
  `investment_plan` varchar(50) DEFAULT NULL,
  `status` enum('pending','awarded','paid') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `awarded_at` timestamp NULL DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `month_year_position` (`month`,`year`,`position`),
  KEY `user_idx` (`user_id`),
  KEY `month_year_idx` (`month`,`year`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Triggers `top_depositor_investments`
--
DROP TRIGGER IF EXISTS `after_top_depositor_payment`;
DELIMITER $$
CREATE TRIGGER `after_top_depositor_payment` AFTER UPDATE ON `top_depositor_investments` FOR EACH ROW BEGIN
    -- If status changed to paid, add amount to user's wallet
    IF NEW.status = 'paid' AND OLD.status != 'paid' THEN
        -- Update user wallet
        UPDATE wallets 
        SET balance = balance + NEW.prize_amount, 
            updated_at = NOW() 
        WHERE user_id = NEW.user_id;
        
        -- Record transaction
        INSERT INTO transactions (
            user_id,
            transaction_type,
            amount,
            status,
            description,
            reference_id
        ) VALUES (
            NEW.user_id,
            'deposit',
            NEW.prize_amount,
            'completed',
            CONCAT('Top Depositor Prize - ', 
                  CASE 
                      WHEN NEW.position = 1 THEN '1st' 
                      WHEN NEW.position = 2 THEN '2nd' 
                      WHEN NEW.position = 3 THEN '3rd' 
                      ELSE CONCAT(NEW.position, 'th')
                  END,
                  ' Place for ', 
                  MONTHNAME(CONCAT(NEW.year, '-', NEW.month, '-01')), 
                  ' ', NEW.year),
            CONCAT('TOPDEP-', NEW.id)
        );
    END IF;
END
$$
DELIMITER ;

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
) ENGINE=MyISAM AUTO_INCREMENT=208 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`id`, `user_id`, `transaction_type`, `amount`, `status`, `description`, `reference_id`, `created_at`) VALUES
(207, 52, 'deposit', 5.00, 'completed', 'Bonus for deposit of $30.00', 'BONUS-78004', '2025-03-13 17:31:41'),
(206, 52, 'deposit', 30.00, 'completed', 'Deposit Request', 'DEP-56396', '2025-03-13 17:31:16'),
(205, 51, 'deposit', 0.25, 'completed', 'Daily Check-in Reward (Day 1)', 'CHECKIN-20250313-51', '2025-03-13 17:29:04');

--
-- Triggers `transactions`
--
DROP TRIGGER IF EXISTS `before_transaction_insert`;
DELIMITER $$
CREATE TRIGGER `before_transaction_insert` BEFORE INSERT ON `transactions` FOR EACH ROW BEGIN
    IF NEW.description = 'Referral Bonus' THEN
        SET NEW.amount = 5.00;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `tree_commissions`
--

DROP TABLE IF EXISTS `tree_commissions`;
CREATE TABLE IF NOT EXISTS `tree_commissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `investment_id` int NOT NULL,
  `user_id` int NOT NULL COMMENT 'User who earns the commission',
  `referred_id` int NOT NULL COMMENT 'User who made the investment',
  `level` int NOT NULL COMMENT 'Level in the referral tree',
  `investment_amount` decimal(15,2) NOT NULL,
  `commission_rate` decimal(5,2) NOT NULL,
  `commission_amount` decimal(15,2) NOT NULL,
  `status` enum('pending','paid','cancelled') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `paid_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `investment_id` (`investment_id`),
  KEY `user_id` (`user_id`),
  KEY `referred_id` (`referred_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
  `login_count` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=MyISAM AUTO_INCREMENT=53 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `email`, `referral_code`, `referred_by`, `phone`, `password`, `registration_date`, `last_login`, `status`, `admin_notes`, `created_at`, `login_count`) VALUES
(51, 'first', 'first@first.com', 'D97Q7NE4', NULL, '+923196977218', '$2y$10$MedVXEbQKEAMVAN8GQVo2OqeVXeojQtxc8mwzysUJKC/GZcz1baWK', '2025-03-13 22:28:51', '2025-03-13 22:31:52', 'active', NULL, '2025-03-13 17:28:51', 0),
(52, 'second', 'second@second.com', 'XVE482PM', 51, '+923196977218', '$2y$10$WrDhiaHCS2dTjzNBzq/32.cbIDvGhGqeAycOazBrSARTqlj47aMAO', '2025-03-13 22:29:33', '2025-03-13 22:30:03', 'active', NULL, '2025-03-13 17:29:33', 0);

-- --------------------------------------------------------

--
-- Table structure for table `user_bets`
--

DROP TABLE IF EXISTS `user_bets`;
CREATE TABLE IF NOT EXISTS `user_bets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `game_id` int NOT NULL,
  `bet_amount` decimal(10,2) NOT NULL,
  `potential_win` decimal(10,2) NOT NULL,
  `outcome` decimal(10,2) NOT NULL,
  `bet_time` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `is_bonus_bet` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `game_id` (`game_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_bonuses`
--

DROP TABLE IF EXISTS `user_bonuses`;
CREATE TABLE IF NOT EXISTS `user_bonuses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `bonus_amount` decimal(10,2) NOT NULL,
  `remaining_amount` decimal(10,2) NOT NULL,
  `wagering_requirement` decimal(10,2) NOT NULL,
  `wagered_amount` decimal(10,2) DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
) ENGINE=MyISAM AUTO_INCREMENT=53 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `wallets`
--

INSERT INTO `wallets` (`id`, `user_id`, `balance`, `created_at`, `updated_at`) VALUES
(51, 51, 0.25, '2025-03-13 17:28:51', '2025-03-13 17:29:04'),
(52, 52, 35.00, '2025-03-13 17:29:33', '2025-03-13 17:31:41');

--
-- Triggers `wallets`
--
DROP TRIGGER IF EXISTS `before_wallet_update`;
DELIMITER $$
CREATE TRIGGER `before_wallet_update` BEFORE UPDATE ON `wallets` FOR EACH ROW BEGIN
    DECLARE last_transaction_desc VARCHAR(255);
    DECLARE last_amount DECIMAL(15,2);
    
    -- Get the most recent transaction for this user that's a referral bonus
    SELECT description, amount INTO last_transaction_desc, last_amount
    FROM transactions 
    WHERE user_id = NEW.user_id 
    AND description = 'Referral Bonus'
    ORDER BY created_at DESC
    LIMIT 1;
    
    -- If this is a referral bonus update and the difference is around 100
    IF last_transaction_desc = 'Referral Bonus' AND (NEW.balance - OLD.balance) > 90.00 THEN
        -- Force the increment to be 5.00 instead
        SET NEW.balance = OLD.balance + 5.00;
    END IF;
END
$$
DELIMITER ;

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
) ENGINE=MyISAM AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `withdrawal_daily_limits`
--

DROP TABLE IF EXISTS `withdrawal_daily_limits`;
CREATE TABLE IF NOT EXISTS `withdrawal_daily_limits` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `withdrawal_date` date NOT NULL,
  `request_count` int NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_date_limit` (`user_id`,`withdrawal_date`)
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

DROP EVENT IF EXISTS `process_ticket_profits_daily`$$
CREATE DEFINER=`root`@`localhost` EVENT `process_ticket_profits_daily` ON SCHEDULE EVERY 1 DAY STARTS '2025-03-11 01:12:20' ON COMPLETION NOT PRESERVE ENABLE DO CALL process_ticket_profits()$$

DROP EVENT IF EXISTS `monthly_top_depositor_investments`$$
CREATE DEFINER=`root`@`localhost` EVENT `monthly_top_depositor_investments` ON SCHEDULE EVERY 1 MONTH STARTS '2025-04-01 00:05:00' ON COMPLETION NOT PRESERVE ENABLE DO BEGIN
    -- Call the procedure to select top depositors
    CALL select_top_depositor_investments();
END$$

DELIMITER ;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
