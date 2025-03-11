<?php
// File: includes/staking-functions.php

/**
 * Get all active staking plans
 * 
 * @return array List of active staking plans
 */
function getActiveStakingPlans()
{
  global $conn;

  $plans = [];
  $query = "SELECT * FROM staking_plans WHERE is_active = 1 ORDER BY duration_days ASC";
  $result = $conn->query($query);

  if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
      $plans[] = $row;
    }
  }

  return $plans;
}

/**
 * Get a specific staking plan by ID
 * 
 * @param int $plan_id Plan ID
 * @return array|null Plan details or null if not found
 */
function getStakingPlan($plan_id)
{
  global $conn;

  $query = "SELECT * FROM staking_plans WHERE id = ? AND is_active = 1";
  $stmt = $conn->prepare($query);
  $stmt->bind_param("i", $plan_id);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result && $result->num_rows > 0) {
    return $result->fetch_assoc();
  }

  return null;
}

/**
 * Calculate expected return for a stake
 * 
 * @param float $amount Amount to stake
 * @param int $duration_days Duration in days
 * @param float $apy_rate Annual percentage yield
 * @return float Expected return amount
 */
function calculateStakeReturn($amount, $duration_days, $apy_rate)
{
  // Convert APY to daily rate
  $daily_rate = $apy_rate / 365;

  // Calculate interest for the staking period
  $interest = ($amount * $daily_rate * $duration_days) / 100;

  // Return original amount plus interest
  return $amount + $interest;
}

/**
 * Create a new stake for a user
 * 
 * @param int $user_id User ID
 * @param int $plan_id Plan ID
 * @param float $amount Amount to stake
 * @return array Result with status and message
 */
function createStake($user_id, $plan_id, $amount)
{
  global $conn;

  // Get plan details
  $plan = getStakingPlan($plan_id);
  if (!$plan) {
    return [
      'success' => false,
      'message' => 'Invalid staking plan selected.'
    ];
  }

  // Validate amount
  $amount = floatval($amount);
  if ($amount < $plan['min_amount']) {
    return [
      'success' => false,
      'message' => "Minimum stake amount for this plan is ₹" . number_format($plan['min_amount'], 2)
    ];
  }

  if ($plan['max_amount'] !== null && $amount > $plan['max_amount']) {
    return [
      'success' => false,
      'message' => "Maximum stake amount for this plan is ₹" . number_format($plan['max_amount'], 2)
    ];
  }

  // Check if user has sufficient balance
  $balance_query = "SELECT balance FROM wallets WHERE user_id = ?";
  $stmt = $conn->prepare($balance_query);
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows === 0) {
    return [
      'success' => false,
      'message' => 'No wallet found for this user.'
    ];
  }

  $wallet_data = $result->fetch_assoc();
  $current_balance = $wallet_data['balance'];

  if ($current_balance < $amount) {
    return [
      'success' => false,
      'message' => "Insufficient balance. You need ₹" . number_format($amount, 2) . " but your balance is ₹" . number_format($current_balance, 2)
    ];
  }

  // Calculate expected return
  $expected_return = calculateStakeReturn($amount, $plan['duration_days'], $plan['apy_rate']);

  // Calculate end date
  $start_date = date('Y-m-d H:i:s');
  $end_date = date('Y-m-d H:i:s', strtotime("+" . $plan['duration_days'] . " days"));

  // Generate stake ID
  $stake_id = 'STK-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));

  // Start transaction
  $conn->begin_transaction();

  try {
    // Deduct amount from user's wallet
    $update_wallet = "UPDATE wallets SET balance = balance - ? WHERE user_id = ?";
    $stmt = $conn->prepare($update_wallet);
    $stmt->bind_param("di", $amount, $user_id);
    $stmt->execute();

    // Insert stake record
    $insert_stake = "INSERT INTO stakes (
                stake_id, 
                user_id, 
                plan_id, 
                amount, 
                expected_return, 
                status, 
                start_date, 
                end_date
            ) VALUES (?, ?, ?, ?, ?, 'active', ?, ?)";

    $stmt = $conn->prepare($insert_stake);
    $stmt->bind_param("siiddss", $stake_id, $user_id, $plan_id, $amount, $expected_return, $start_date, $end_date);
    $stmt->execute();

    // Record transaction
    $transaction_query = "INSERT INTO transactions (
                user_id, 
                transaction_type, 
                amount, 
                status, 
                description, 
                reference_id
            ) VALUES (?, 'investment', ?, 'completed', ?, ?)";

    $description = "Staking Pool: " . $plan['name'];

    $stmt = $conn->prepare($transaction_query);
    $stmt->bind_param("idss", $user_id, $amount, $description, $stake_id);
    $stmt->execute();

    // Commit transaction
    $conn->commit();

    return [
      'success' => true,
      'message' => "Stake created successfully! Your funds are now locked until " . date('M d, Y', strtotime($end_date)),
      'stake_id' => $stake_id,
      'expected_return' => $expected_return
    ];
  } catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    return [
      'success' => false,
      'message' => 'An error occurred: ' . $e->getMessage()
    ];
  }
}

/**
 * Process completed stakes (for cron job)
 * 
 * @return int Number of stakes processed
 */
function processCompletedStakes()
{
  global $conn;

  // Find all active stakes that have reached their end date
  $query = "SELECT s.*, sp.name as plan_name 
              FROM stakes s 
              JOIN staking_plans sp ON s.plan_id = sp.id 
              WHERE s.status = 'active' AND s.end_date <= NOW()";

  $result = $conn->query($query);
  $processed_count = 0;

  if ($result && $result->num_rows > 0) {
    while ($stake = $result->fetch_assoc()) {
      $conn->begin_transaction();

      try {
        $user_id = $stake['user_id'];
        $expected_return = $stake['expected_return'];
        $stake_id = $stake['stake_id'];
        $plan_name = $stake['plan_name'];

        // Add the return amount to user's wallet
        $update_wallet = "UPDATE wallets SET balance = balance + ? WHERE user_id = ?";
        $stmt = $conn->prepare($update_wallet);
        $stmt->bind_param("di", $expected_return, $user_id);
        $stmt->execute();

        // Update stake status
        $update_stake = "UPDATE stakes SET status = 'completed', completion_date = NOW() WHERE id = ?";
        $stmt = $conn->prepare($update_stake);
        $stmt->bind_param("i", $stake['id']);
        $stmt->execute();

        // Record profit transaction
        $profit_amount = $expected_return - $stake['amount'];
        $transaction_query = "INSERT INTO transactions (
                        user_id, 
                        transaction_type, 
                        amount, 
                        status, 
                        description, 
                        reference_id
                    ) VALUES (?, 'profit', ?, 'completed', ?, ?)";

        $description = "Staking Reward: " . $plan_name;

        $stmt = $conn->prepare($transaction_query);
        $stmt->bind_param("idss", $user_id, $expected_return, $description, $stake_id);
        $stmt->execute();

        $conn->commit();
        $processed_count++;
      } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        error_log("Error processing stake ID {$stake['id']}: " . $e->getMessage());
      }
    }
  }

  return $processed_count;
}

/**
 * Get active stakes for a user
 * 
 * @param int $user_id User ID
 * @return array List of active stakes
 */
function getUserActiveStakes($user_id)
{
  global $conn;

  $stakes = [];
  $query = "SELECT s.*, sp.name as plan_name, sp.apy_rate, sp.duration_days, sp.early_withdrawal_fee 
              FROM stakes s 
              JOIN staking_plans sp ON s.plan_id = sp.id 
              WHERE s.user_id = ? AND s.status = 'active' 
              ORDER BY s.end_date ASC";

  $stmt = $conn->prepare($query);
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
      $stakes[] = $row;
    }
  }

  return $stakes;
}

/**
 * Get completed stakes for a user
 * 
 * @param int $user_id User ID
 * @param int $limit Limit number of results
 * @return array List of completed stakes
 */
function getUserCompletedStakes($user_id, $limit = 5)
{
  global $conn;

  $stakes = [];
  $query = "SELECT s.*, sp.name as plan_name, sp.apy_rate, sp.duration_days 
              FROM stakes s 
              JOIN staking_plans sp ON s.plan_id = sp.id 
              WHERE s.user_id = ? AND s.status != 'active' 
              ORDER BY s.completion_date DESC 
              LIMIT ?";

  $stmt = $conn->prepare($query);
  $stmt->bind_param("ii", $user_id, $limit);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
      $stakes[] = $row;
    }
  }

  return $stakes;
}

/**
 * Calculate early withdrawal fee
 * 
 * @param float $amount Original stake amount
 * @param float $fee_percentage Fee percentage
 * @return float Fee amount
 */
function calculateEarlyWithdrawalFee($amount, $fee_percentage)
{
  return ($amount * $fee_percentage) / 100;
}

/**
 * Process early withdrawal of a stake
 * 
 * @param int $user_id User ID
 * @param string $stake_id Stake ID
 * @return array Result with status and message
 */
function processEarlyWithdrawal($user_id, $stake_id)
{
  global $conn;

  // Get stake details
  $query = "SELECT s.*, sp.early_withdrawal_fee, sp.name as plan_name 
              FROM stakes s 
              JOIN staking_plans sp ON s.plan_id = sp.id 
              WHERE s.stake_id = ? AND s.user_id = ? AND s.status = 'active'";

  $stmt = $conn->prepare($query);
  $stmt->bind_param("si", $stake_id, $user_id);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows === 0) {
    return [
      'success' => false,
      'message' => 'Stake not found or not active.'
    ];
  }

  $stake = $result->fetch_assoc();
  $original_amount = $stake['amount'];
  $fee_percentage = $stake['early_withdrawal_fee'];
  $fee_amount = calculateEarlyWithdrawalFee($original_amount, $fee_percentage);
  $return_amount = $original_amount - $fee_amount;

  // Start transaction
  $conn->begin_transaction();

  try {
    // Add the return amount to user's wallet
    $update_wallet = "UPDATE wallets SET balance = balance + ? WHERE user_id = ?";
    $stmt = $conn->prepare($update_wallet);
    $stmt->bind_param("di", $return_amount, $user_id);
    $stmt->execute();

    // Update stake status
    $update_stake = "UPDATE stakes SET status = 'withdrawn', completion_date = NOW() WHERE id = ?";
    $stmt = $conn->prepare($update_stake);
    $stmt->bind_param("i", $stake['id']);
    $stmt->execute();

    // Record transaction
    $transaction_query = "INSERT INTO transactions (
                user_id, 
                transaction_type, 
                amount, 
                status, 
                description, 
                reference_id
            ) VALUES (?, 'withdrawal', ?, 'completed', ?, ?)";

    $description = "Early Stake Withdrawal (Fee: ₹" . number_format($fee_amount, 2) . "): " . $stake['plan_name'];

    $stmt = $conn->prepare($transaction_query);
    $stmt->bind_param("idss", $user_id, $return_amount, $description, $stake_id);
    $stmt->execute();

    // Commit transaction
    $conn->commit();

    return [
      'success' => true,
      'message' => "Early withdrawal processed. ₹" . number_format($return_amount, 2) . " has been returned to your wallet after deducting a " . $fee_percentage . "% fee (₹" . number_format($fee_amount, 2) . ").",
      'return_amount' => $return_amount,
      'fee_amount' => $fee_amount
    ];
  } catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    return [
      'success' => false,
      'message' => 'An error occurred: ' . $e->getMessage()
    ];
  }
}

/**
 * Get staking statistics for a user
 * 
 * @param int $user_id User ID
 * @return array Staking statistics
 */
function getUserStakingStats($user_id)
{
  global $conn;

  $stats = [
    'total_staked' => 0,
    'active_stakes' => 0,
    'completed_stakes' => 0,
    'total_earned' => 0,
    'current_locked' => 0
  ];

  // Get count and totals
  $query = "SELECT 
                COUNT(*) as total_stakes,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count,
                SUM(amount) as total_staked,
                SUM(CASE WHEN status = 'active' THEN amount ELSE 0 END) as current_locked,
                SUM(CASE WHEN status = 'completed' THEN (expected_return - amount) ELSE 0 END) as total_earned
              FROM stakes 
              WHERE user_id = ?";

  $stmt = $conn->prepare($query);
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result && $result->num_rows > 0) {
    $data = $result->fetch_assoc();
    $stats['total_staked'] = floatval($data['total_staked'] ?? 0);
    $stats['active_stakes'] = intval($data['active_count'] ?? 0);
    $stats['completed_stakes'] = intval($data['completed_count'] ?? 0);
    $stats['total_earned'] = floatval($data['total_earned'] ?? 0);
    $stats['current_locked'] = floatval($data['current_locked'] ?? 0);
  }

  return $stats;
}
