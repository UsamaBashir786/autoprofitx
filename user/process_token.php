<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user_id'])) {
  $_SESSION['error_message'] = "You must be logged in to perform this action.";
  header("Location: login.php");
  exit();
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';
$amount = intval($_POST['amount'] ?? 0);

if (empty($action) || $amount <= 0) {
  $_SESSION['error_message'] = "Invalid request. Please try again.";
  header("Location: index.php");
  exit();
}

$conn->begin_transaction();
try {
  if ($action === 'buy') {
    $wallet_sql = "SELECT balance FROM wallets WHERE user_id = ?";
    $stmt = $conn->prepare($wallet_sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
      throw new Exception("Wallet not found. Please contact support.");
    }

    $row = $result->fetch_assoc();
    $balance = $row['balance'];
    $total_cost = $amount * 1000;

    if ($balance < $total_cost) {
      throw new Exception("Insufficient balance. Please add funds to your wallet.");
    }

    $update_wallet_sql = "UPDATE wallets SET balance = balance - ? WHERE user_id = ?";
    $stmt = $conn->prepare($update_wallet_sql);
    $stmt->bind_param("di", $total_cost, $user_id);
    $stmt->execute();

    $current_time = date('Y-m-d H:i:s');
    $token_purchase_sql = "INSERT INTO alpha_tokens (user_id, purchase_date, purchase_amount, status) VALUES (?, ?, 1000.00, 'active')";
    $stmt = $conn->prepare($token_purchase_sql);

    for ($i = 0; $i < $amount; $i++) {
      $stmt->bind_param("is", $user_id, $current_time);
      $stmt->execute();
    }

    $_SESSION['success_message'] = "Successfully purchased $amount AlphaMiner Tokens!";
  } elseif ($action === 'sell') {
    $token_count_sql = "SELECT COUNT(*) as total FROM alpha_tokens WHERE user_id = ? AND status = 'active' AND TIMESTAMPDIFF(HOUR, purchase_date, NOW()) >= 24";
    $stmt = $conn->prepare($token_count_sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $available_tokens = $row['total'];

    if ($available_tokens < $amount) {
      throw new Exception("You can only sell tokens held for at least 24 hours. Available for sale: $available_tokens");
    }

    $get_tokens_sql = "SELECT id, purchase_date, purchase_amount FROM alpha_tokens 
                            WHERE user_id = ? AND status = 'active' 
                            AND TIMESTAMPDIFF(HOUR, purchase_date, NOW()) >= 24 
                            ORDER BY purchase_date ASC LIMIT ?";
    $stmt = $conn->prepare($get_tokens_sql);
    $stmt->bind_param("ii", $user_id, $amount);
    $stmt->execute();
    $tokens_result = $stmt->get_result();

    $total_return = 0;
    $total_profit = 0;

    while ($token = $tokens_result->fetch_assoc()) {
      $token_id = $token['id'];
      $purchase_date = new DateTime($token['purchase_date']);
      $current_date = new DateTime();
      $interval = $purchase_date->diff($current_date);
      $days_held = max(1, $interval->days);

      $token_value = $token['purchase_amount'];
      for ($i = 0; $i < $days_held; $i++) {
        $token_value += $token_value * 0.065;
      }

      $profit = $token_value - $token['purchase_amount'];
      $total_return += $token_value;
      $total_profit += $profit;

      $update_token_sql = "UPDATE alpha_tokens SET 
                                    status = 'sold', 
                                    sold_date = NOW(), 
                                    sold_amount = ?, 
                                    profit = ? 
                                    WHERE id = ?";
      $stmt = $conn->prepare($update_token_sql);
      $stmt->bind_param("ddi", $token_value, $profit, $token_id);
      $stmt->execute();
    }

    $update_wallet_sql = "UPDATE wallets SET balance = balance + ? WHERE user_id = ?";
    $stmt = $conn->prepare($update_wallet_sql);
    $stmt->bind_param("di", $total_return, $user_id);
    $stmt->execute();

    $_SESSION['success_message'] = "Successfully sold $amount tokens for $" . number_format($total_return, 2) . " (Profit: $" . number_format($total_profit, 2) . ")";  } else {
    throw new Exception("Invalid action specified.");
  }

  $conn->commit();
  header("Location: index.php");
  exit();
} catch (Exception $e) {
  $conn->rollback();
  $_SESSION['error_message'] = "Error: " . $e->getMessage();
  header("Location: index.php");
  exit();
}
