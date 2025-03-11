<?php

/**
 * Functions for processing profits on user login or dashboard visit
 * These replace the cron job-based profit distribution system
 */

/**
 * Process profits for a specific user when they log in or access the dashboard
 * 
 * @param int $user_id The ID of the user to process profits for
 * @return array Array containing number of processed investments, tickets, and tokens
 */
function processUserProfits($user_id)
{
  global $conn;

  // Track how many items were processed
  $results = [
    'processed_investments' => 0,
    'processed_tickets' => 0,
    'processed_tokens' => 0
  ];

  // Process matured investments for this user
  $results['processed_investments'] = processUserInvestments($user_id);

  // Process matured ticket purchases for this user
  $results['processed_tickets'] = processUserTickets($user_id);

  // Process token interest if applicable, for this user
  if (function_exists('processUserTokenInterest')) {
    $results['processed_tokens'] = processUserTokenInterest($user_id);
  }

  // Update the wallet's last update timestamp
  updateWalletTimestamp($user_id);

  // Log the result to error log for debugging
  error_log("User $user_id profits processed at " . date('Y-m-d H:i:s') .
    ". Processed {$results['processed_investments']} investments, " .
    "{$results['processed_tickets']} tickets, and " .
    "{$results['processed_tokens']} tokens.");

  return $results;
}

/**
 * Update the wallet's last update timestamp
 * 
 * @param int $user_id The ID of the user whose wallet to update
 */
function updateWalletTimestamp($user_id)
{
  global $conn;

  $query = "UPDATE wallets SET updated_at = NOW() WHERE user_id = ?";
  $stmt = $conn->prepare($query);
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $stmt->close();
}

/**
 * Process matured investments for a specific user
 * 
 * @param int $user_id The ID of the user to process investments for
 * @return int Number of processed investments
 */
function processUserInvestments($user_id)
{
  global $conn;

  // Get all active investments for this user that have reached maturity date
  $matured_query = "SELECT * FROM investments 
                     WHERE user_id = ? 
                     AND status = 'active' 
                     AND maturity_date <= NOW()";

  $stmt = $conn->prepare($matured_query);
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $result = $stmt->get_result();
  $processed_count = 0;

  if ($result && $result->num_rows > 0) {
    while ($investment = $result->fetch_assoc()) {
      $conn->begin_transaction();

      try {
        $total_return = $investment['total_return'];
        $investment_id = $investment['investment_id'];
        $plan_type = $investment['plan_type'];
        $profit = $investment['expected_profit'];

        // Update user's wallet with the total return
        $update_wallet = "UPDATE wallets 
                                 SET balance = balance + ?, 
                                     updated_at = NOW() 
                                 WHERE user_id = ?";

        $stmt = $conn->prepare($update_wallet);
        $stmt->bind_param("di", $total_return, $user_id);
        $stmt->execute();

        // Update investment status
        $update_investment = "UPDATE investments 
                                     SET status = 'completed', 
                                         completion_date = NOW() 
                                     WHERE id = ?";

        $stmt = $conn->prepare($update_investment);
        $stmt->bind_param("i", $investment['id']);
        $stmt->execute();

        // Record profit payout transaction
        $transaction_query = "INSERT INTO transactions (
                        user_id, 
                        transaction_type, 
                        amount, 
                        status, 
                        description, 
                        reference_id
                    ) VALUES (?, 'profit', ?, 'completed', ?, ?)";

        $description = "Profit Payout - $plan_type Plan";

        $stmt = $conn->prepare($transaction_query);
        $stmt->bind_param("idss", $user_id, $total_return, $description, $investment_id);
        $stmt->execute();

        $conn->commit();
        $processed_count++;
      } catch (Exception $e) {
        $conn->rollback();
        // Log error
        error_log("Error processing investment ID {$investment['id']} for user $user_id: " . $e->getMessage());
      }
    }
  }

  $stmt->close();
  return $processed_count;
}

/**
 * Process matured ticket purchases for a specific user
 * 
 * @param int $user_id The ID of the user to process tickets for
 * @return int Number of processed tickets
 */
function processUserTickets($user_id)
{
  global $conn;

  // Get all active ticket purchases for this user that have reached maturity date
  $matured_query = "SELECT * FROM ticket_purchases 
                      WHERE user_id = ? 
                      AND status = 'active' 
                      AND maturity_date <= NOW()";

  $stmt = $conn->prepare($matured_query);
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $result = $stmt->get_result();
  $processed_count = 0;

  if ($result && $result->num_rows > 0) {
    while ($purchase = $result->fetch_assoc()) {
      $conn->begin_transaction();

      try {
        $total_return = $purchase['total_return'];
        $purchase_id = $purchase['purchase_id'];
        $expected_profit = $purchase['expected_profit'];

        // Update user's wallet with the total return
        $update_wallet = "UPDATE wallets 
                                 SET balance = balance + ?, 
                                     updated_at = NOW() 
                                 WHERE user_id = ?";

        $stmt = $conn->prepare($update_wallet);
        $stmt->bind_param("di", $total_return, $user_id);
        $stmt->execute();

        // Update purchase status
        $update_purchase = "UPDATE ticket_purchases 
                                   SET status = 'completed', 
                                       completion_date = NOW(),
                                       profit_paid = 1 
                                   WHERE id = ?";

        $stmt = $conn->prepare($update_purchase);
        $stmt->bind_param("i", $purchase['id']);
        $stmt->execute();

        // Record profit transaction
        $transaction_query = "INSERT INTO transactions (
                                    user_id, 
                                    transaction_type, 
                                    amount, 
                                    status, 
                                    description, 
                                    reference_id
                                ) VALUES (?, 'profit', ?, 'completed', ?, ?)";

        $description = "Ticket Purchase Profit";

        $stmt = $conn->prepare($transaction_query);
        $stmt->bind_param("idss", $user_id, $total_return, $description, $purchase_id);
        $stmt->execute();

        $conn->commit();
        $processed_count++;
      } catch (Exception $e) {
        $conn->rollback();
        // Log error
        error_log("Error processing ticket purchase ID {$purchase['id']} for user $user_id: " . $e->getMessage());
      }
    }
  }

  $stmt->close();
  return $processed_count;
}
function handleUserDashboardActions($user_id)
{
  // Process check-in if requested
  $checkin_result = null;
  if (isset($_POST['daily_checkin'])) {
    $checkin_result = processUserCheckin($user_id);
  }

  // Check and process profits for this user (replaces cron job)
  $profits_processed = processUserProfitsIfNeeded($user_id);

  // Set up notification if profits were processed
  $profit_notification = "";
  if (
    $profits_processed['processed_investments'] > 0 ||
    $profits_processed['processed_tickets'] > 0 ||
    $profits_processed['processed_tokens'] > 0
  ) {
    $total_processed = $profits_processed['processed_investments'] +
      $profits_processed['processed_tickets'] +
      $profits_processed['processed_tokens'];

    // Set a notification for the user
    $profit_notification = "You have $total_processed new profit payments added to your wallet!";
  }

  return [
    'checkin_result' => $checkin_result,
    'profits_processed' => $profits_processed,
    'profit_notification' => $profit_notification
  ];
}
/**
 * Process token interest for a specific user if applicable
 * 
 * @param int $user_id The ID of the user to process token interest for
 * @return int Number of processed tokens
 */
function processUserTokenInterest($user_id)
{
  global $conn;

  // Check if the alpha_tokens table exists
  $check_table = "SELECT COUNT(*) as count FROM information_schema.tables 
                    WHERE table_schema = DATABASE() AND table_name = 'alpha_tokens'";
  $result = $conn->query($check_table);

  if ($result && $result->fetch_assoc()['count'] == 0) {
    // Table doesn't exist, skip processing
    return 0;
  }

  // Get all active tokens for this user
  $tokens_query = "SELECT * FROM alpha_tokens WHERE user_id = ? AND status = 'active'";
  $stmt = $conn->prepare($tokens_query);
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $result = $stmt->get_result();
  $processed_count = 0;

  if ($result && $result->num_rows > 0) {
    while ($token = $result->fetch_assoc()) {
      // Calculate daily interest (6.5%)
      $interest_rate = 0.065;
      $current_value = $token['current_value'] ?? $token['purchase_value'];
      $interest_amount = $current_value * $interest_rate;
      $new_value = $current_value + $interest_amount;

      // Update token value
      $update_query = "UPDATE alpha_tokens 
                             SET current_value = ?, 
                                 last_interest_date = NOW() 
                             WHERE id = ?";

      $stmt = $conn->prepare($update_query);
      $stmt->bind_param("di", $new_value, $token['id']);

      if ($stmt->execute()) {
        $processed_count++;

        // Record interest transaction
        $transaction_query = "INSERT INTO transactions (
                                    user_id, 
                                    transaction_type, 
                                    amount, 
                                    status, 
                                    description, 
                                    reference_id
                                ) VALUES (?, 'profit', ?, 'completed', ?, ?)";

        $description = "Alpha Token Interest";
        $reference_id = "TOKEN-INT-" . $token['id'];

        $stmt = $conn->prepare($transaction_query);
        $stmt->bind_param("idss", $user_id, $interest_amount, $description, $reference_id);
        $stmt->execute();
      }
    }
  }

  $stmt->close();
  return $processed_count;
}
