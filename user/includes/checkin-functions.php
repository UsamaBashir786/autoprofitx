<?php
// File: includes/checkin-functions.php

/**
 * Process a user's daily check-in
 * 
 * @param int $user_id User ID
 * @return array Result with status and message
 */
function processUserCheckin($user_id)
{
  global $conn;

  // Get today's date
  $today = date('Y-m-d');

  // Check if user already checked in today
  $check_query = "SELECT id FROM daily_checkins WHERE user_id = ? AND checkin_date = ?";
  $stmt = $conn->prepare($check_query);
  $stmt->bind_param("is", $user_id, $today);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows > 0) {
    return [
      'success' => false,
      'message' => 'You have already checked in today. Come back tomorrow!'
    ];
  }

  // Get user's last check-in to calculate streak
  $streak_query = "SELECT checkin_date, streak_count FROM daily_checkins 
                     WHERE user_id = ? 
                     ORDER BY checkin_date DESC 
                     LIMIT 1";
  $stmt = $conn->prepare($streak_query);
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $streak_result = $stmt->get_result();

  // Calculate current streak
  $streak_count = 1; // Default is 1 (first day)
  $yesterday = date('Y-m-d', strtotime('-1 day'));

  if ($streak_result->num_rows > 0) {
    $last_checkin = $streak_result->fetch_assoc();

    // If user checked in yesterday, increment the streak
    if ($last_checkin['checkin_date'] == $yesterday) {
      $streak_count = $last_checkin['streak_count'] + 1;
    }
  }

  // Get reward amount based on streak count
  $reward_amount = getRewardForStreak($streak_count);

  // Begin transaction
  $conn->begin_transaction();

  try {
    // Record the check-in
    $insert_query = "INSERT INTO daily_checkins (user_id, checkin_date, streak_count, reward_amount) 
                         VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($insert_query);
    $stmt->bind_param("isid", $user_id, $today, $streak_count, $reward_amount);
    $stmt->execute();

    // Update user's wallet balance
    $update_wallet = "UPDATE wallets SET balance = balance + ? WHERE user_id = ?";
    $stmt = $conn->prepare($update_wallet);
    $stmt->bind_param("di", $reward_amount, $user_id);
    $stmt->execute();

    // Record transaction
    $description = "Daily Check-in Reward (Day $streak_count)";
    $reference_id = "CHECKIN-" . date('Ymd') . "-" . $user_id;

    $transaction_query = "INSERT INTO transactions (
                user_id, 
                transaction_type, 
                amount, 
                status, 
                description, 
                reference_id
            ) VALUES (?, 'deposit', ?, 'completed', ?, ?)";

    $stmt = $conn->prepare($transaction_query);
    $stmt->bind_param("idss", $user_id, $reward_amount, $description, $reference_id);
    $stmt->execute();

    // Commit the transaction
    $conn->commit();

    return [
      'success' => true,
      'message' => "Check-in successful! You earned ₹$reward_amount.",
      'streak' => $streak_count,
      'reward' => $reward_amount
    ];
  } catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    return [
      'success' => false,
      'message' => "Error processing check-in: " . $e->getMessage()
    ];
  }
}

/**
 * Get the reward amount for a specific streak day
 * 
 * @param int $streak_count Current streak count
 * @return float Reward amount
 */
function getRewardForStreak($streak_count)
{
  global $conn;

  // First try to get an exact match for the streak day
  $query = "SELECT reward_amount FROM checkin_rewards 
              WHERE streak_day = ? AND is_active = 1";
  $stmt = $conn->prepare($query);
  $stmt->bind_param("i", $streak_count);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    return floatval($row['reward_amount']);
  }

  // If no exact match, find the closest lower streak day
  $query = "SELECT reward_amount FROM checkin_rewards 
              WHERE streak_day < ? AND is_active = 1
              ORDER BY streak_day DESC 
              LIMIT 1";
  $stmt = $conn->prepare($query);
  $stmt->bind_param("i", $streak_count);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    return floatval($row['reward_amount']);
  }

  // Default fallback reward
  return 5.00;
}

/**
 * Get user's current check-in streak
 * 
 * @param int $user_id User ID
 * @return array Streak information
 */
function getUserCheckinStreak($user_id)
{
  global $conn;

  // Get user's last check-in
  $query = "SELECT checkin_date, streak_count FROM daily_checkins 
              WHERE user_id = ? 
              ORDER BY checkin_date DESC 
              LIMIT 1";
  $stmt = $conn->prepare($query);
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $result = $stmt->get_result();

  $today = date('Y-m-d');
  $yesterday = date('Y-m-d', strtotime('-1 day'));

  if ($result->num_rows > 0) {
    $last_checkin = $result->fetch_assoc();
    $current_streak = $last_checkin['streak_count'];

    // If last check-in was today, return current streak
    if ($last_checkin['checkin_date'] == $today) {
      return [
        'streak' => $current_streak,
        'checked_in_today' => true,
        'next_reward' => getRewardForStreak($current_streak + 1)
      ];
    }

    // If last check-in was yesterday, streak is still active but not checked in today
    if ($last_checkin['checkin_date'] == $yesterday) {
      return [
        'streak' => $current_streak,
        'checked_in_today' => false,
        'next_reward' => getRewardForStreak($current_streak + 1)
      ];
    }

    // Streak was broken (last check-in more than 1 day ago)
    return [
      'streak' => 0,
      'checked_in_today' => false,
      'next_reward' => getRewardForStreak(1)
    ];
  }

  // User has never checked in
  return [
    'streak' => 0,
    'checked_in_today' => false,
    'next_reward' => getRewardForStreak(1)
  ];
}

/**
 * Get monthly check-in statistics for a user
 * 
 * @param int $user_id User ID
 * @return array Monthly statistics
 */
function getMonthlyCheckinStats($user_id)
{
  global $conn;

  $current_month = date('Y-m');
  $days_in_month = date('t');
  $today = date('d');

  $query = "SELECT COUNT(*) as check_in_count, SUM(reward_amount) as total_rewards 
              FROM daily_checkins 
              WHERE user_id = ? AND checkin_date LIKE '$current_month%'";
  $stmt = $conn->prepare($query);
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $result = $stmt->get_result();
  $stats = $result->fetch_assoc();

  return [
    'month' => date('F Y'),
    'days_in_month' => intval($days_in_month),
    'days_passed' => intval($today),
    'check_in_count' => intval($stats['check_in_count']),
    'check_in_percentage' => ($today > 0) ? round((intval($stats['check_in_count']) / intval($today)) * 100) : 0,
    'total_rewards' => floatval($stats['total_rewards'] ?? 0)
  ];
}
