<?php

/**
 * Complete referral system functions with strict conditions
 * This file contains all the necessary functions for managing referrals
 */

// Function to get all referrals made by a user
function getUserReferrals($userId)
{
  global $conn;

  $referrals = [];

  // First, make sure the login tracking columns exist
  ensureLoginTracking();

  // Use a query that works whether or not login_count exists
  $query = "SELECT r.*, u.full_name, u.email, u.registration_date";

  // Check if login_count column exists before including it in the query
  $check_columns = "SELECT COUNT(*) as count FROM information_schema.columns 
                   WHERE table_schema = DATABASE() 
                   AND table_name = 'users' 
                   AND column_name = 'login_count'";
  $result = $conn->query($check_columns);
  $data = $result->fetch_assoc();
  $login_tracking_available = ($data['count'] > 0);

  if ($login_tracking_available) {
    // If login_count exists, include it in the query
    $query .= ", u.login_count";
  }

  $query .= " FROM referrals r 
              JOIN users u ON r.referred_id = u.id 
              WHERE r.referrer_id = ? 
              ORDER BY r.created_at DESC";

  $stmt = $conn->prepare($query);
  $stmt->bind_param("i", $userId);
  $stmt->execute();
  $result = $stmt->get_result();

  while ($row = $result->fetch_assoc()) {
    // If login_count doesn't exist in the database, add a default value
    if (!isset($row['login_count'])) {
      $row['login_count'] = 0;
    }
    $referrals[] = $row;
  }

  return $referrals;
}

// Function to get total referral earnings for a user (both claimed and unclaimed)
function getTotalReferralEarnings($userId)
{
  global $conn;

  // Get total from referrals table
  $query = "SELECT SUM(bonus_amount) as total FROM referrals WHERE referrer_id = ?";
  $stmt = $conn->prepare($query);
  $stmt->bind_param("i", $userId);
  $stmt->execute();
  $result = $stmt->get_result();
  $data = $result->fetch_assoc();

  return $data['total'] ?? 0;
}

// Function to get pending (unclaimed) referral bonuses
function getPendingReferralBonuses($userId)
{
  global $conn;

  // First check if the referral_vault table exists
  $tableExists = tableExists('referral_vault');

  if (!$tableExists) {
    // If table doesn't exist, create it
    createReferralVaultTable();

    // Then migrate any pending referrals to the vault
    migrateReferralsToVault();
  }

  // Make sure login tracking columns exist
  ensureLoginTracking();

  // Check if login_count column exists
  $check_columns = "SELECT COUNT(*) as count FROM information_schema.columns 
                   WHERE table_schema = DATABASE() 
                   AND table_name = 'users' 
                   AND column_name = 'login_count'";
  $result = $conn->query($check_columns);
  $data = $result->fetch_assoc();
  $login_tracking_available = ($data['count'] > 0);

  // Use appropriate query based on login tracking availability
  if ($login_tracking_available) {
    $query = "SELECT rv.*, u.full_name, u.email, u.login_count, u.last_login 
              FROM referral_vault rv
              JOIN users u ON rv.referred_id = u.id
              WHERE rv.referrer_id = ? AND rv.status = 'pending'
              ORDER BY rv.created_at DESC";
  } else {
    $query = "SELECT rv.*, u.full_name, u.email
              FROM referral_vault rv
              JOIN users u ON rv.referred_id = u.id
              WHERE rv.referrer_id = ? AND rv.status = 'pending'
              ORDER BY rv.created_at DESC";
  }

  $stmt = $conn->prepare($query);
  $stmt->bind_param("i", $userId);
  $stmt->execute();
  $result = $stmt->get_result();

  $bonuses = [];
  while ($row = $result->fetch_assoc()) {
    // Add default values if login tracking isn't available
    if (!$login_tracking_available) {
      $row['login_count'] = 0;
      $row['last_login'] = null;
    }
    $bonuses[] = $row;
  }

  return $bonuses;
}

// Function to get the total pending (unclaimed) amount
function getTotalPendingReferralAmount($userId)
{
  global $conn;

  // First check if the referral_vault table exists
  $tableExists = tableExists('referral_vault');

  if (!$tableExists) {
    // If table doesn't exist, create it
    createReferralVaultTable();

    // Then migrate any pending referrals to the vault
    migrateReferralsToVault();
  }

  $query = "SELECT SUM(amount) as total FROM referral_vault 
              WHERE referrer_id = ? AND status = 'pending'";

  $stmt = $conn->prepare($query);
  $stmt->bind_param("i", $userId);
  $stmt->execute();
  $result = $stmt->get_result();
  $data = $result->fetch_assoc();

  return $data['total'] ?? 0;
}

// Process a referral when a new user registers
function processReferral($referredBy, $newUserId)
{
  global $conn;

  // Verify the referring user exists
  $check_user = "SELECT id FROM users WHERE id = ?";
  $stmt = $conn->prepare($check_user);
  $stmt->bind_param("i", $referredBy);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows === 0) {
    // Referring user doesn't exist
    return false;
  }

  // Get referral bonus amount from system settings
  $bonus_query = "SELECT setting_value FROM system_settings WHERE setting_key = 'direct_referral_bonus'";
  $stmt = $conn->prepare($bonus_query);
  $stmt->execute();
  $result = $stmt->get_result();
  $setting = $result->fetch_assoc();
  $bonus_amount = $setting ? floatval($setting['setting_value']) : 5.00;

  // Start transaction
  $conn->begin_transaction();

  try {
    // Make sure referral_vault table exists
    createReferralVaultTable();

    // Insert referral record with explicit bonus amount and pending status
    $referral_query = "INSERT INTO referrals (referrer_id, referred_id, bonus_amount, status, created_at) 
                          VALUES (?, ?, ?, 'pending', NOW())";
    $stmt = $conn->prepare($referral_query);
    $stmt->bind_param("iid", $referredBy, $newUserId, $bonus_amount);
    $stmt->execute();

    // Insert the bonus amount into the referral_vault table ONLY - NOT directly to wallet
    $vault_query = "INSERT INTO referral_vault (referrer_id, referred_id, amount, status, created_at) 
                        VALUES (?, ?, ?, 'pending', NOW())";
    $stmt = $conn->prepare($vault_query);
    $stmt->bind_param("iid", $referredBy, $newUserId, $bonus_amount);
    $stmt->execute();

    // Commit transaction
    $conn->commit();

    return true;
  } catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    error_log("Error processing referral: " . $e->getMessage());
    return false;
  }
}

// Function to claim a specific referral bonus
function claimReferralBonus($vaultId, $userId)
{
  global $conn;

  // Start transaction
  $conn->begin_transaction();

  try {
    // Get the bonus details with stricter validation
    $query = "SELECT rv.*, r.status as referral_status 
                 FROM referral_vault rv
                 JOIN referrals r ON rv.referrer_id = r.referrer_id AND rv.referred_id = r.referred_id
                 WHERE rv.id = ? AND rv.referrer_id = ? AND rv.status = 'pending'";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $vaultId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
      // No pending bonus found or not owned by this user
      $conn->rollback();
      return ["success" => false, "message" => "Bonus not found or already claimed"];
    }

    $bonus = $result->fetch_assoc();
    $amount = $bonus['amount'];
    $referred_id = $bonus['referred_id'];

    // Check if login tracking is available
    $check_columns = "SELECT COUNT(*) as count FROM information_schema.columns 
                     WHERE table_schema = DATABASE() 
                     AND table_name = 'users' 
                     AND column_name = 'login_count'";
    $result = $conn->query($check_columns);
    $data = $result->fetch_assoc();
    $login_tracking_available = ($data['count'] > 0);

    // Only check login status if the tracking columns exist
    if ($login_tracking_available) {
      // ADDITIONAL VALIDATION: Check if the referred user is active
      $referred_activity_check = "SELECT login_count, last_login FROM users WHERE id = ?";
      $stmt = $conn->prepare($referred_activity_check);
      $stmt->bind_param("i", $referred_id);
      $stmt->execute();
      $ref_result = $stmt->get_result();
      $ref_user = $ref_result->fetch_assoc();

      // STRICT CONDITION: Ensure the referred user has logged in at least once
      if ($ref_user['login_count'] < 1) {
        $conn->rollback();
        return ["success" => false, "message" => "The referred user hasn't activated their account yet. Rewards can only be claimed after they log in."];
      }
    }

    // Check if wallet exists for user
    $wallet_check = "SELECT id FROM wallets WHERE user_id = ?";
    $stmt = $conn->prepare($wallet_check);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
      // Create wallet for user if it doesn't exist
      $create_wallet = "INSERT INTO wallets (user_id, balance, updated_at) VALUES (?, ?, NOW())";
      $stmt = $conn->prepare($create_wallet);
      $stmt->bind_param("id", $userId, $amount);
      $stmt->execute();
    } else {
      // Update existing wallet
      $update_wallet = "UPDATE wallets SET balance = balance + ?, updated_at = NOW() WHERE user_id = ?";
      $stmt = $conn->prepare($update_wallet);
      $stmt->bind_param("di", $amount, $userId);
      $stmt->execute();
    }

    // Record transaction
    $reference_id = "REF-CLAIM-" . $vaultId;
    $description = "Referral Bonus Claimed";

    $transaction_query = "INSERT INTO transactions (
                            user_id, 
                            transaction_type, 
                            amount, 
                            status, 
                            description, 
                            reference_id
                        ) VALUES (?, 'deposit', ?, 'completed', ?, ?)";

    $stmt = $conn->prepare($transaction_query);
    $stmt->bind_param("idss", $userId, $amount, $description, $reference_id);
    $stmt->execute();

    // Update vault entry status
    $update_vault = "UPDATE referral_vault SET status = 'claimed', claimed_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($update_vault);
    $stmt->bind_param("i", $vaultId);
    $stmt->execute();

    // Update referral status to paid
    $update_referral = "UPDATE referrals SET status = 'paid', paid_at = NOW() 
                            WHERE referrer_id = ? AND referred_id = ?";
    $stmt = $conn->prepare($update_referral);
    $stmt->bind_param("ii", $userId, $referred_id);
    $stmt->execute();

    // Commit transaction
    $conn->commit();

    return ["success" => true, "message" => "Bonus of $" . number_format($amount, 2) . " added to your wallet"];
  } catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    error_log("Error claiming referral bonus: " . $e->getMessage());
    return ["success" => false, "message" => "Error processing your claim: " . $e->getMessage()];
  }
}

// Function to claim all pending referral bonuses at once
function claimAllReferralBonuses($userId)
{
  global $conn;

  // Start transaction
  $conn->begin_transaction();

  try {
    // Check if login tracking is available
    $check_columns = "SELECT COUNT(*) as count FROM information_schema.columns 
                     WHERE table_schema = DATABASE() 
                     AND table_name = 'users' 
                     AND column_name = 'login_count'";
    $result = $conn->query($check_columns);
    $data = $result->fetch_assoc();
    $login_tracking_available = ($data['count'] > 0);

    // Use appropriate query based on login tracking availability
    if ($login_tracking_available) {
      $query = "SELECT rv.*, u.login_count, u.last_login
                 FROM referral_vault rv
                 JOIN users u ON rv.referred_id = u.id
                 WHERE rv.referrer_id = ? AND rv.status = 'pending'";
    } else {
      $query = "SELECT rv.*
                 FROM referral_vault rv
                 WHERE rv.referrer_id = ? AND rv.status = 'pending'";
    }

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
      // No pending bonuses
      $conn->rollback();
      return ["success" => false, "message" => "No pending bonuses to claim"];
    }

    // Calculate total amount to add and track eligible bonuses
    $total_amount = 0;
    $vault_ids = [];
    $referred_ids = [];
    $ineligible_count = 0;

    while ($bonus = $result->fetch_assoc()) {
      // If login tracking exists, check eligibility
      if ($login_tracking_available) {
        // STRICT CONDITION: Only include bonuses where referred user has logged in
        if ($bonus['login_count'] > 0) {
          $total_amount += $bonus['amount'];
          $vault_ids[] = $bonus['id'];
          $referred_ids[] = $bonus['referred_id'];
        } else {
          $ineligible_count++;
        }
      } else {
        // If login tracking isn't available, include all bonuses
        $total_amount += $bonus['amount'];
        $vault_ids[] = $bonus['id'];
        $referred_ids[] = $bonus['referred_id'];
      }
    }

    // Check if there are any eligible bonuses to claim
    if (empty($vault_ids)) {
      $conn->rollback();

      if ($login_tracking_available) {
        return ["success" => false, "message" => "None of your referrals are eligible for claiming yet. They need to log in at least once."];
      } else {
        return ["success" => false, "message" => "No bonuses available to claim."];
      }
    }

    // Check if wallet exists for user
    $wallet_check = "SELECT id FROM wallets WHERE user_id = ?";
    $stmt = $conn->prepare($wallet_check);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
      // Create wallet for user if it doesn't exist
      $create_wallet = "INSERT INTO wallets (user_id, balance, updated_at) VALUES (?, ?, NOW())";
      $stmt = $conn->prepare($create_wallet);
      $stmt->bind_param("id", $userId, $total_amount);
      $stmt->execute();
    } else {
      // Update existing wallet
      $update_wallet = "UPDATE wallets SET balance = balance + ?, updated_at = NOW() WHERE user_id = ?";
      $stmt = $conn->prepare($update_wallet);
      $stmt->bind_param("di", $total_amount, $userId);
      $stmt->execute();
    }

    // Record single transaction for all claimed bonuses
    $reference_id = "REF-CLAIM-ALL-" . time();
    $description = "Claimed All Eligible Referral Bonuses";

    $transaction_query = "INSERT INTO transactions (
                            user_id, 
                            transaction_type, 
                            amount, 
                            status, 
                            description, 
                            reference_id
                        ) VALUES (?, 'deposit', ?, 'completed', ?, ?)";

    $stmt = $conn->prepare($transaction_query);
    $stmt->bind_param("idss", $userId, $total_amount, $description, $reference_id);
    $stmt->execute();

    // Update vault entries for eligible bonuses
    if (!empty($vault_ids)) {
      $placeholders = implode(',', array_fill(0, count($vault_ids), '?'));
      $types = str_repeat('i', count($vault_ids));

      $update_vault = "UPDATE referral_vault SET status = 'claimed', claimed_at = NOW() 
                            WHERE id IN ($placeholders)";

      $stmt = $conn->prepare($update_vault);

      // Dynamically bind parameters
      $bind_params = array($types);
      foreach ($vault_ids as $key => $value) {
        $bind_params[] = &$vault_ids[$key];
      }

      call_user_func_array(array($stmt, 'bind_param'), $bind_params);
      $stmt->execute();
    }

    // Update referrals table for eligible referrals
    foreach ($referred_ids as $referred_id) {
      $update_referral = "UPDATE referrals SET status = 'paid', paid_at = NOW() 
                              WHERE referrer_id = ? AND referred_id = ?";
      $stmt = $conn->prepare($update_referral);
      $stmt->bind_param("ii", $userId, $referred_id);
      $stmt->execute();
    }

    // Commit transaction
    $conn->commit();

    // Create appropriate success message
    $message = "Total bonus of $" . number_format($total_amount, 2) . " added to your wallet";
    if ($login_tracking_available && $ineligible_count > 0) {
      $message .= ". Note: $ineligible_count referrals were not eligible for claiming yet.";
    }

    return [
      "success" => true,
      "message" => $message,
      "count" => count($vault_ids),
      "amount" => $total_amount,
      "ineligible" => $ineligible_count
    ];
  } catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    error_log("Error claiming all referral bonuses: " . $e->getMessage());
    return ["success" => false, "message" => "Error processing your claim: " . $e->getMessage()];
  }
}

// Function to check referral eligibility without claiming
function checkReferralEligibility($vaultId, $userId)
{
  global $conn;

  // Check if login tracking is available
  $check_columns = "SELECT COUNT(*) as count FROM information_schema.columns 
                   WHERE table_schema = DATABASE() 
                   AND table_name = 'users' 
                   AND column_name = 'login_count'";
  $result = $conn->query($check_columns);
  $data = $result->fetch_assoc();
  $login_tracking_available = ($data['count'] > 0);

  // Get the bonus details
  if ($login_tracking_available) {
    $query = "SELECT rv.*, r.status as referral_status, u.login_count, u.last_login 
               FROM referral_vault rv
               JOIN referrals r ON rv.referrer_id = r.referrer_id AND rv.referred_id = r.referred_id
               JOIN users u ON rv.referred_id = u.id
               WHERE rv.id = ? AND rv.referrer_id = ? AND rv.status = 'pending'";
  } else {
    $query = "SELECT rv.*, r.status as referral_status
               FROM referral_vault rv
               JOIN referrals r ON rv.referrer_id = r.referrer_id AND rv.referred_id = r.referred_id
               WHERE rv.id = ? AND rv.referrer_id = ? AND rv.status = 'pending'";
  }

  $stmt = $conn->prepare($query);
  $stmt->bind_param("ii", $vaultId, $userId);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows === 0) {
    return ["eligible" => false, "reason" => "Bonus not found or already claimed"];
  }

  $bonus = $result->fetch_assoc();

  // Only check login status if tracking is available
  if ($login_tracking_available) {
    // Check if referred user has logged in
    if ($bonus['login_count'] < 1) {
      return [
        "eligible" => false,
        "reason" => "The referred user hasn't activated their account yet",
        "referred_id" => $bonus['referred_id'],
        "amount" => $bonus['amount']
      ];
    }
  }

  return [
    "eligible" => true,
    "amount" => $bonus['amount'],
    "referred_id" => $bonus['referred_id']
  ];
}

// Function to generate a unique referral code for new users
function generateReferralCode($length = 8)
{
  $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
  $code = '';

  for ($i = 0; $i < $length; $i++) {
    $code .= $characters[rand(0, strlen($characters) - 1)];
  }

  return $code;
}

// Helper function to create the referral vault table if needed
function createReferralVaultTable()
{
  global $conn;

  $sql = "CREATE TABLE IF NOT EXISTS `referral_vault` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `referrer_id` int(11) NOT NULL,
      `referred_id` int(11) NOT NULL,
      `amount` decimal(10,2) NOT NULL DEFAULT '0.00',
      `status` enum('pending','claimed') NOT NULL DEFAULT 'pending',
      `created_at` datetime NOT NULL,
      `claimed_at` datetime DEFAULT NULL,
      PRIMARY KEY (`id`),
      KEY `referrer_id` (`referrer_id`),
      KEY `referred_id` (`referred_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

  return $conn->query($sql);
}

// Check if table exists
function tableExists($tableName)
{
  global $conn;

  $check_table = "SELECT COUNT(*) as count FROM information_schema.tables 
                    WHERE table_schema = DATABASE() AND table_name = ?";
  $stmt = $conn->prepare($check_table);
  $stmt->bind_param("s", $tableName);
  $stmt->execute();
  $result = $stmt->get_result();
  $data = $result->fetch_assoc();

  return ($data['count'] > 0);
}

// Migrate existing referrals to the vault
function migrateReferralsToVault()
{
  global $conn;

  // Check if referrals and status columns exists
  $referrals_exist = tableExists('referrals');

  if (!$referrals_exist) {
    return false;
  }

  // Check if status column exists in referrals table
  $check_column = "SELECT COUNT(*) as count FROM information_schema.columns 
                     WHERE table_schema = DATABASE() 
                     AND table_name = 'referrals' 
                     AND column_name = 'status'";
  $result = $conn->query($check_column);
  $data = $result->fetch_assoc();

  if ($data['count'] == 0) {
    // Add status column if it doesn't exist
    $conn->query("ALTER TABLE referrals ADD COLUMN status ENUM('pending', 'paid') NOT NULL DEFAULT 'pending' AFTER bonus_amount");
    $conn->query("ALTER TABLE referrals ADD COLUMN paid_at DATETIME NULL AFTER status");
  }

  // Migrate pending referrals to the vault
  $migrate_query = "INSERT INTO referral_vault (referrer_id, referred_id, amount, status, created_at, claimed_at)
                     SELECT r.referrer_id, r.referred_id, r.bonus_amount, 
                            IF(r.status = 'paid', 'claimed', 'pending') as status,
                            IFNULL(r.created_at, NOW()) as created_at,
                            r.paid_at
                     FROM referrals r
                     LEFT JOIN referral_vault rv ON r.referrer_id = rv.referrer_id AND r.referred_id = rv.referred_id
                     WHERE rv.id IS NULL";

  return $conn->query($migrate_query);
}

// Function to modify user table to ensure login tracking
function ensureLoginTracking()
{
  global $conn;

  // Check login_count and last_login columns separately
  $check_login_count = "SELECT COUNT(*) as count FROM information_schema.columns 
                        WHERE table_schema = DATABASE() 
                        AND table_name = 'users' 
                        AND column_name = 'login_count'";
  $result = $conn->query($check_login_count);
  $data = $result->fetch_assoc();
  $has_login_count = ($data['count'] > 0);

  $check_last_login = "SELECT COUNT(*) as count FROM information_schema.columns 
                       WHERE table_schema = DATABASE() 
                       AND table_name = 'users' 
                       AND column_name = 'last_login'";
  $result = $conn->query($check_last_login);
  $data = $result->fetch_assoc();
  $has_last_login = ($data['count'] > 0);

  // Add each column individually if they don't exist
  if (!$has_login_count) {
    $sql = "ALTER TABLE users ADD COLUMN login_count INT NOT NULL DEFAULT 0";
    $conn->query($sql);
  }

  if (!$has_last_login) {
    $sql = "ALTER TABLE users ADD COLUMN last_login DATETIME NULL";
    $conn->query($sql);
  }

  return true;
}
// Update user login information
function updateUserLoginInfo($userId)
{
  global $conn;

  // Make sure the columns exist
  ensureLoginTracking();

  // Update login count and timestamp
  $sql = "UPDATE users SET 
           login_count = login_count + 1,
           last_login = NOW()
           WHERE id = ?";

  $stmt = $conn->prepare($sql);
  $stmt->bind_param("i", $userId);
  $stmt->execute();

  return $stmt->affected_rows > 0;
}

// Get count of eligible and ineligible bonuses
function getEligibleReferralCounts($userId)
{
  global $conn;

  // Check if login tracking is available
  $check_columns = "SELECT COUNT(*) as count FROM information_schema.columns 
                   WHERE table_schema = DATABASE() 
                   AND table_name = 'users' 
                   AND column_name = 'login_count'";
  $result = $conn->query($check_columns);
  $data = $result->fetch_assoc();
  $login_tracking_available = ($data['count'] > 0);

  // Default values
  $eligible_count = 0;
  $ineligible_count = 0;
  $eligible_amount = 0;
  $ineligible_amount = 0;

  // Get all pending bonuses
  $query = "SELECT rv.id, rv.amount FROM referral_vault rv
           WHERE rv.referrer_id = ? AND rv.status = 'pending'";

  $stmt = $conn->prepare($query);
  $stmt->bind_param("i", $userId);
  $stmt->execute();
  $result = $stmt->get_result();

  // If login tracking is not available, consider all bonuses eligible
  if (!$login_tracking_available) {
    while ($bonus = $result->fetch_assoc()) {
      $eligible_count++;
      $eligible_amount += $bonus['amount'];
    }
  } else {
    // If login tracking is available, check each bonus
    $query = "SELECT rv.id, rv.amount, u.login_count 
             FROM referral_vault rv
             JOIN users u ON rv.referred_id = u.id
             WHERE rv.referrer_id = ? AND rv.status = 'pending'";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($bonus = $result->fetch_assoc()) {
      if ($bonus['login_count'] > 0) {
        $eligible_count++;
        $eligible_amount += $bonus['amount'];
      } else {
        $ineligible_count++;
        $ineligible_amount += $bonus['amount'];
      }
    }
  }

  return [
    'eligible_count' => $eligible_count,
    'ineligible_count' => $ineligible_count,
    'eligible_amount' => $eligible_amount,
    'ineligible_amount' => $ineligible_amount
  ];
}
