<?php
// Include database configuration
include '../config/db.php';
session_start();
ini_set('display_errors', 1);
error_log('Process investment called');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
  echo json_encode(['success' => false, 'message' => 'User not logged in']);
  exit;
}

// Get user ID from session
$user_id = $_SESSION['user_id'];

// Get POST data
$plan_type = $_POST['plan_type'] ?? '';
$amount = floatval($_POST['amount'] ?? 0);

// For debugging
error_log("Received plan_type: $plan_type, amount: $amount");

// Validate inputs
if (empty($plan_type) || $amount <= 0) {
  echo json_encode(['success' => false, 'message' => 'Invalid input parameters']);
  exit;
}

// Check minimum investment amount based on plan type
$min_amount = 0;
switch ($plan_type) {
  case 'Basic':
    $min_amount = 3000;
    break;
  case 'Standard':
    $min_amount = 5000;
    break;
  case 'Premium':
    $min_amount = 10000;
    break;
  case 'Professional':
    $min_amount = 20000;
    break;
  case 'Custom':
    $min_amount = 25000;
    // For custom plans, there's no maximum amount
    if ($amount < $min_amount) {
      echo json_encode(['success' => false, 'message' => "Minimum investment for Custom plan is $$min_amount"]);
      exit;
    }
    break;
  default:
    echo json_encode(['success' => false, 'message' => 'Invalid plan type']);
    exit;
}
if ($amount < $min_amount) {
  echo json_encode(['success' => false, 'message' => "Minimum investment for $plan_type plan is $$min_amount"]);
  exit;
}

// Check if user has sufficient balance
$balance_query = "SELECT balance FROM wallets WHERE user_id = ?";
$stmt = $conn->prepare($balance_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  // No wallet found, create one with zero balance
  $create_wallet = "INSERT INTO wallets (user_id, balance) VALUES (?, 0)";
  $stmt = $conn->prepare($create_wallet);
  $stmt->bind_param("i", $user_id);
  $stmt->execute();

  echo json_encode(['success' => false, 'message' => 'Insufficient balance. Please add funds to your wallet.']);
  exit;
}

$wallet_data = $result->fetch_assoc();
$current_balance = $wallet_data['balance'];

if ($current_balance < $amount) {
  echo json_encode(['success' => false, 'message' => "Insufficient balance. You need $$amount but your balance is $$current_balance"]);  exit;
}

// Check if user was referred by someone
$referrer_query = "SELECT referred_by FROM users WHERE id = ?";
$stmt = $conn->prepare($referrer_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$referrer_result = $stmt->get_result();
$referrer_data = $referrer_result->fetch_assoc();
$referrer_id = $referrer_data['referred_by'] ?? null;

error_log("User $user_id was referred by: " . ($referrer_id ?: 'None'));

// Get plan ID and commission rate
$plan_id = null;
$commission_rate = 0;

$plan_query = "SELECT id, referral_commission_rate FROM investment_plans WHERE name = ?";
$stmt = $conn->prepare($plan_query);
$stmt->bind_param("s", $plan_type);
$stmt->execute();
$plan_result = $stmt->get_result();

if ($plan_result->num_rows > 0) {
  $plan_data = $plan_result->fetch_assoc();
  $plan_id = $plan_data['id'];
  $commission_rate = $plan_data['referral_commission_rate'];
  error_log("Found plan ID: $plan_id with commission rate: $commission_rate%");
} else {
  error_log("Plan not found for type: $plan_type");
}

// Calculate commission amount
$commission_amount = 0;
if ($referrer_id && $commission_rate > 0) {
  $commission_amount = ($amount * $commission_rate) / 100;
  error_log("Calculated commission: $commission_amount for referrer ID: $referrer_id (rate: $commission_rate%)");
}

// Start transaction
$conn->begin_transaction();

try {
  // Deduct amount from user's wallet
  $update_wallet = "UPDATE wallets SET balance = balance - ? WHERE user_id = ?";
  $stmt = $conn->prepare($update_wallet);
  $stmt->bind_param("di", $amount, $user_id);
  $stmt->execute();

  // Generate investment ID
  $investment_id = 'INV-' . rand(10000, 99999);

  // Calculate maturity date (24 hours from now)
  $start_date = date('Y-m-d H:i:s');
  $maturity_date = date('Y-m-d H:i:s', strtotime('+24 hours'));

  // Calculate expected profit (20% of investment amount)
  $profit = $amount * // Calculate expected profit (0.25% of investment amount)
  $profit = $amount * 0.0025;;
  $total_return = $amount + $profit;

  // Insert investment record with plan_id
  $insert_investment = "INSERT INTO investments (
        investment_id, 
        user_id, 
        plan_type,
        plan_id, 
        amount, 
        expected_profit, 
        total_return, 
        status, 
        start_date, 
        maturity_date,
        referral_commission_paid
    ) VALUES (?, ?, ?, ?, ?, ?, ?, 'active', ?, ?, ?)";

  $commission_paid = 0; // Set initially to 0 (not paid)

  $stmt = $conn->prepare($insert_investment);
  $stmt->bind_param(
    "sisiiddsi",
    $investment_id,
    $user_id,
    $plan_type,
    $plan_id,
    $amount,
    $profit,
    $total_return,
    $start_date,
    $maturity_date,
    $commission_paid
  );
  $stmt->execute();

  $new_investment_id = $conn->insert_id;
  error_log("Created investment with ID: $new_investment_id");

  // Process referral commission if applicable
  if ($referrer_id && $commission_amount > 0) {
    error_log("Processing commission for referrer: $referrer_id");

    // Check if wallet exists for referrer
    $check_wallet = "SELECT id FROM wallets WHERE user_id = ?";
    $stmt = $conn->prepare($check_wallet);
    $stmt->bind_param("i", $referrer_id);
    $stmt->execute();
    $wallet_result = $stmt->get_result();

    if ($wallet_result->num_rows > 0) {
      // Update referrer's wallet
      $update_referrer_wallet = "UPDATE wallets SET balance = balance + ? WHERE user_id = ?";
      $stmt = $conn->prepare($update_referrer_wallet);
      $stmt->bind_param("di", $commission_amount, $referrer_id);
      $stmt->execute();
      error_log("Updated referrer's wallet with commission: $commission_amount");
    } else {
      // Create wallet for referrer
      $create_referrer_wallet = "INSERT INTO wallets (user_id, balance) VALUES (?, ?)";
      $stmt = $conn->prepare($create_referrer_wallet);
      $stmt->bind_param("id", $referrer_id, $commission_amount);
      $stmt->execute();
      error_log("Created new wallet for referrer with balance: $commission_amount");
    }

    // Record commission in referral_commissions table
    $commission_query = "INSERT INTO referral_commissions (
            investment_id,
            referrer_id,
            referred_id,
            investment_amount,
            commission_rate,
            commission_amount,
            status,
            paid_at
        ) VALUES (?, ?, ?, ?, ?, ?, 'paid', NOW())";

    $stmt = $conn->prepare($commission_query);
    $stmt->bind_param(
      "iiiddd",
      $new_investment_id,
      $referrer_id,
      $user_id,
      $amount,
      $commission_rate,
      $commission_amount
    );
    $stmt->execute();
    error_log("Recorded commission in referral_commissions table");

    // Create transaction record for commission
    $reference_id = "INVREF-" . $new_investment_id;
    $description = "Investment Referral Commission";

    $transaction_query = "INSERT INTO transactions (
            user_id,
            transaction_type,
            amount,
            status,
            description,
            reference_id
        ) VALUES (?, 'deposit', ?, 'completed', ?, ?)";

    $stmt = $conn->prepare($transaction_query);
    $stmt->bind_param("idss", $referrer_id, $commission_amount, $description, $reference_id);
    $stmt->execute();
    error_log("Created transaction record for commission");

    // Mark commission as paid in investment record
    $update_investment = "UPDATE investments SET referral_commission_paid = 1 WHERE id = ?";
    $stmt = $conn->prepare($update_investment);
    $stmt->bind_param("i", $new_investment_id);
    $stmt->execute();
    error_log("Marked commission as paid for investment ID: $new_investment_id");
  }

  // Record transaction for the investment
  $transaction_query = "INSERT INTO transactions (
        user_id, 
        transaction_type, 
        amount, 
        status, 
        description, 
        reference_id
    ) VALUES (?, 'investment', ?, 'completed', ?, ?)";

  $description = "$plan_type Plan Purchase";

  $stmt = $conn->prepare($transaction_query);
  $stmt->bind_param("idss", $user_id, $amount, $description, $investment_id);
  $stmt->execute();

  // Commit transaction
  $conn->commit();
  error_log("Transaction committed successfully");

  echo json_encode(['success' => true, 'investment_id' => $investment_id]);
} catch (Exception $e) {
  // Rollback transaction on error
  $conn->rollback();
  echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
  error_log("Investment transaction error: " . $e->getMessage());
  error_log("Error stack trace: " . $e->getTraceAsString());
}
