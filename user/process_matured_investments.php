<?php
// Start session
session_start();

// Include database configuration
include '../config/db.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);
error_log('Investment maturity processor started');

// Get all active investments that have reached maturity date
$matured_query = "SELECT * FROM investments 
                 WHERE status = 'active' 
                 AND maturity_date <= NOW()";

$result = $conn->query($matured_query);
$processed_count = 0;

if ($result->num_rows > 0) {
  error_log("Found {$result->num_rows} matured investments to process");

  while ($investment = $result->fetch_assoc()) {
    $conn->begin_transaction();

    try {
      $user_id = $investment['user_id'];
      $total_return = $investment['total_return'];
      $investment_id = $investment['investment_id'];
      $plan_type = $investment['plan_type'];
      $profit = $investment['expected_profit'];

      error_log("Processing investment ID: {$investment['id']}, User ID: {$user_id}, Total Return: {$total_return}");

      // Update user's wallet with the total return
      $update_wallet = "UPDATE wallets 
                             SET balance = balance + ? 
                             WHERE user_id = ?";

      $stmt = $conn->prepare($update_wallet);
      $stmt->bind_param("di", $total_return, $user_id);
      $wallet_result = $stmt->execute();

      if (!$wallet_result) {
        throw new Exception("Failed to update wallet: " . $stmt->error);
      }

      // Update investment status
      $update_investment = "UPDATE investments 
                                 SET status = 'completed', 
                                     completion_date = NOW() 
                                 WHERE id = ?";

      $stmt = $conn->prepare($update_investment);
      $stmt->bind_param("i", $investment['id']);
      $investment_result = $stmt->execute();

      if (!$investment_result) {
        throw new Exception("Failed to update investment status: " . $stmt->error);
      }

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
      $transaction_result = $stmt->execute();

      if (!$transaction_result) {
        throw new Exception("Failed to create transaction record: " . $stmt->error);
      }

      $conn->commit();
      $processed_count++;

      // Log the successful payout
      error_log("Successfully processed payout for investment ID: $investment_id, User ID: $user_id, Amount: $total_return");
    } catch (Exception $e) {
      $conn->rollback();
      // Log error
      error_log("Error processing investment ID {$investment['id']}: " . $e->getMessage());
    }
  }
}

echo "Processed $processed_count matured investments.";
error_log("Maturity processor completed: $processed_count investments processed");
