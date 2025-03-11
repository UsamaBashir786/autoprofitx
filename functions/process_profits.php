<?php

/**
 * This file contains functions for processing profits on user login or dashboard visit.
 * It replaces the cron job-based profit distribution system.
 */

// Include the detailed profit processing functions
require_once __DIR__ . '/profit_functions.php';

/**
 * Checks if a user's profits need to be updated based on a time interval
 * 
 * @param int $user_id The ID of the user to check
 * @param int $hours_interval The interval in hours between profit updates (default 24)
 * @return bool True if profits should be updated, false otherwise
 */
function shouldUpdateProfits($user_id, $hours_interval = 24)
{
  global $conn;

  // Check if there's a record in the wallets table for this user
  $query = "SELECT updated_at FROM wallets WHERE user_id = ?";
  $stmt = $conn->prepare($query);
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows === 0) {
    // No wallet record, should update profits
    $stmt->close();
    return true;
  }

  $wallet = $result->fetch_assoc();
  $stmt->close();

  // If updated_at is NULL, should update profits
  if ($wallet['updated_at'] === null) {
    return true;
  }

  // Calculate time difference in hours
  $last_update = new DateTime($wallet['updated_at']);
  $now = new DateTime();
  $interval = $last_update->diff($now);
  $hours_diff = $interval->h + ($interval->days * 24);

  // Return true if the time difference is greater than or equal to the interval
  return $hours_diff >= $hours_interval;
}

/**
 * Main function to process profits for a specific user if needed
 * 
 * @param int $user_id The ID of the user to process profits for
 * @param int $hours_interval The interval in hours between profit updates (default 24)
 * @return array Array containing number of processed investments, tickets, and tokens
 */
function processUserProfitsIfNeeded($user_id, $hours_interval = 24)
{
  // Check if profits need to be updated
  if (shouldUpdateProfits($user_id, $hours_interval)) {
    // Process profits
    return processUserProfits($user_id);
  }

  // Return zero counts if no update was needed
  return [
    'processed_investments' => 0,
    'processed_tickets' => 0,
    'processed_tokens' => 0
  ];
}
