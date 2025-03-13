<?php
// Start session
session_start();

// Database connection
include '../config/db.php';

// Include profit processing functions
require_once '../functions/process_profits.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
  // Redirect to login page if not logged in
  header("Location: ../login.php");
  exit;
}

// Get the user ID from session
$user_id = $_SESSION['user_id'];

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

// Process investment if form submitted
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['plan_type']) && isset($_POST['amount'])) {
  processInvestment($_POST['plan_type'], $_POST['amount']);
}

// Get user's check-in streak info (only if the functions are included)
$streak_info = [];
if (function_exists('getUserCheckinStreak')) {
  $streak_info = getUserCheckinStreak($user_id);
}

// Function to process investment
function processInvestment($plan_type, $amount)
{
  global $conn;

  // Validate user is logged in
  if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
  }

  $user_id = $_SESSION['user_id'];
  $amount = floatval($amount);

  // Validate inputs
  if (empty($plan_type) || $amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid input parameters']);
    exit;
  }

  // Check minimum investment amount based on plan type
  $min_amount = 0;
  switch ($plan_type) {
    case 'Basic':
      $min_amount = 10;
      break;
    case 'Standard':
      $min_amount = 17;
      break;
    case 'Premium':
      $min_amount = 35;
      break;
    case 'Professional':
      $min_amount = 71;
      break;
    case 'Custom':
      $min_amount = 90;
      // For custom plans, there's no maximum amount
      break;
    // Add other plans here...
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
    $create_wallet = "INSERT INTO wallets (user_id, balance, updated_at) VALUES (?, 0, NOW())";
    $stmt = $conn->prepare($create_wallet);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    echo json_encode(['success' => false, 'message' => 'Insufficient balance. Please add funds to your wallet.']);
    exit;
  }

  $wallet_data = $result->fetch_assoc();
  $current_balance = $wallet_data['balance'];

  if ($current_balance < $amount) {
    echo json_encode(['success' => false, 'message' => "Insufficient balance. You need $$amount but your balance is $$current_balance"]);
    exit;
  }

  // Start transaction
  $conn->begin_transaction();

  try {
    // Deduct amount from user's wallet
    $update_wallet = "UPDATE wallets SET balance = balance - ?, updated_at = NOW() WHERE user_id = ?";
    $stmt = $conn->prepare($update_wallet);
    $stmt->bind_param("di", $amount, $user_id);
    $stmt->execute();

    // Generate investment ID
    $investment_id = 'INV-' . rand(10000, 99999);

    // Calculate maturity date (24 hours from now)
    $start_date = date('Y-m-d H:i:s');
    $maturity_date = date('Y-m-d H:i:s', strtotime('+24 hours'));

    // Calculate expected profit (20% of investment amount)
    $profit = $amount * 0.2;
    $total_return = $amount + $profit;

    // Insert investment record
    $insert_investment = "INSERT INTO investments (
                investment_id, 
                user_id, 
                plan_type, 
                amount, 
                expected_profit, 
                total_return, 
                status, 
                start_date, 
                maturity_date
            ) VALUES (?, ?, ?, ?, ?, ?, 'active', ?, ?)";

    $stmt = $conn->prepare($insert_investment);
    $stmt->bind_param(
      "sisdddss",
      $investment_id,
      $user_id,
      $plan_type,
      $amount,
      $profit,
      $total_return,
      $start_date,
      $maturity_date
    );
    $stmt->execute();

    // Get the new investment ID
    $new_investment_id = $conn->insert_id;

    // Record transaction
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

    // Get plan ID and commission rate
    $plan_id = null;
    $commission_rate = 0;

    // Check if user was referred by someone
    $referrer_query = "SELECT referred_by FROM users WHERE id = ?";
    $stmt = $conn->prepare($referrer_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $referrer_result = $stmt->get_result();
    $referrer_data = $referrer_result->fetch_assoc();
    $referrer_id = $referrer_data['referred_by'] ?? null;

    if ($referrer_id) {
      // Get commission rate based on plan type
      $plan_query = "SELECT id, referral_commission_rate FROM investment_plans WHERE name = ?";
      $stmt = $conn->prepare($plan_query);
      $stmt->bind_param("s", $plan_type);
      $stmt->execute();
      $plan_result = $stmt->get_result();

      if ($plan_result->num_rows > 0) {
        $plan_data = $plan_result->fetch_assoc();
        $plan_id = $plan_data['id'];
        $commission_rate = $plan_data['referral_commission_rate'];

        // Calculate commission amount
        $commission_amount = ($amount * $commission_rate) / 100;

        // Check if wallet exists for referrer
        $check_wallet = "SELECT id FROM wallets WHERE user_id = ?";
        $stmt = $conn->prepare($check_wallet);
        $stmt->bind_param("i", $referrer_id);
        $stmt->execute();
        $wallet_result = $stmt->get_result();

        if ($wallet_result->num_rows > 0) {
          // Update referrer's wallet
          $update_referrer_wallet = "UPDATE wallets SET balance = balance + ?, updated_at = NOW() WHERE user_id = ?";
          $stmt = $conn->prepare($update_referrer_wallet);
          $stmt->bind_param("di", $commission_amount, $referrer_id);
          $stmt->execute();
        } else {
          // Create wallet for referrer
          $create_referrer_wallet = "INSERT INTO wallets (user_id, balance, updated_at) VALUES (?, ?, NOW())";
          $stmt = $conn->prepare($create_referrer_wallet);
          $stmt->bind_param("id", $referrer_id, $commission_amount);
          $stmt->execute();
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

        // Update investment to mark commission as paid
        $update_investment = "UPDATE investments SET referral_commission_paid = 1 WHERE id = ?";
        $stmt = $conn->prepare($update_investment);
        $stmt->bind_param("i", $new_investment_id);
        $stmt->execute();
      }
    }

    // Commit transaction
    $conn->commit();

    echo json_encode(['success' => true, 'investment_id' => $investment_id]);
    exit;
  } catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
  }
}

/**
 * @deprecated This function is replaced by processUserInvestments() which runs on user login/dashboard visit.
 * It's kept here for backward compatibility but not actively used.
 */
function processMaturedInvestments()
{
  global $conn;

  // Get all active investments that have reached maturity date
  $matured_query = "SELECT * FROM investments 
                     WHERE status = 'active' 
                     AND maturity_date <= NOW()";

  $result = $conn->query($matured_query);
  $processed_count = 0;

  if ($result && $result->num_rows > 0) {
    while ($investment = $result->fetch_assoc()) {
      $conn->begin_transaction();

      try {
        $user_id = $investment['user_id'];
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
        error_log("Error processing investment ID {$investment['id']}: " . $e->getMessage());
      }
    }
  }

  return $processed_count;
}

/**
 * @deprecated This function is replaced by processUserTickets() which runs on user login/dashboard visit.
 * It's kept here for backward compatibility but not actively used.
 */
function processMaturedTickets()
{
  global $conn;

  // Get all active ticket purchases that have reached maturity date
  $matured_query = "SELECT * FROM ticket_purchases 
                      WHERE status = 'active' 
                      AND maturity_date <= NOW()";

  $result = $conn->query($matured_query);
  $processed_count = 0;

  if ($result && $result->num_rows > 0) {
    while ($purchase = $result->fetch_assoc()) {
      $conn->begin_transaction();

      try {
        $user_id = $purchase['user_id'];
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
        error_log("Error processing ticket purchase ID {$purchase['id']}: " . $e->getMessage());
      }
    }
  }

  return $processed_count;
}

/**
 * @deprecated This function is replaced by processUserTokenInterest() which runs on user login/dashboard visit.
 * It's kept here for backward compatibility but not actively used.
 */
function processTokenInterest()
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

  // Get all active tokens
  $tokens_query = "SELECT * FROM alpha_tokens WHERE status = 'active'";
  $result = $conn->query($tokens_query);
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

        // Record transaction for the interest
        $user_id = $token['user_id'];
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

  return $processed_count;
}

// Get user's data
$user_name = $_SESSION['full_name'] ?? 'User';
$balance = 0;
$join_date = '';

// Get user's payment methods from database
$payment_methods = [];
$query = "SELECT * FROM payment_methods WHERE user_id = ? ORDER BY is_default DESC, created_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
  $payment_methods[] = $row;
}
$stmt->close();

// Function to get icon class based on payment type
function getPaymentIcon($payment_type)
{
  switch (strtolower($payment_type)) {
    case 'visa':
      return 'fab fa-cc-visa text-blue-400';
    case 'mastercard':
      return 'fab fa-cc-mastercard text-red-400';
    case 'bank':
    case 'bank account':
      return 'fas fa-university text-green-500';
    case 'bitcoin':
    case 'btc':
      return 'fab fa-bitcoin text-yellow-500';
    case 'ethereum':
    case 'eth':
      return 'fab fa-ethereum text-purple-400';
    case 'paypal':
      return 'fab fa-paypal text-blue-500';
    default:
      return 'fas fa-credit-card text-gray-400';
  }
}

// Get last 4 digits or characters of account number for display
function formatAccountNumber($account_number)
{
  $length = strlen($account_number);
  if ($length <= 4) {
    return $account_number;
  }
  return '...' . substr($account_number, -4);
}

// Check if wallets table exists
$check_wallets_sql = "SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'wallets'";
$wallets_table_exists = false;
$result = $conn->query($check_wallets_sql);
if ($result && $result->fetch_assoc()['count'] > 0) {
  $wallets_table_exists = true;
}

// If wallets table exists, get balance from there
if ($wallets_table_exists) {
  // Get balance from wallets table
  $wallet_sql = "SELECT balance FROM wallets WHERE user_id = ?";
  $stmt = $conn->prepare($wallet_sql);
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows > 0) {
    $wallet_data = $result->fetch_assoc();
    $balance = $wallet_data['balance'];
  } else {
    // No wallet record for this user
    $balance = 0;
  }
  $stmt->close();
}

// Get user's registration date
$user_sql = "SELECT registration_date FROM users WHERE id = ?";
$stmt = $conn->prepare($user_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
  $user_data = $result->fetch_assoc();
  $join_date = $user_data['registration_date'];
}
$stmt->close();

// Format join date
$member_since = '';
if (!empty($join_date)) {
  $member_since = date('M Y', strtotime($join_date));
} else {
  $member_since = 'N/A';
}

// Generate a wallet ID
$wallet_id = 'W' . str_pad($user_id, 6, '0', STR_PAD_LEFT);

// Get user's active investments
$active_investments = [];
if ($user_id > 0) {
  $investments_query = "SELECT * FROM investments WHERE user_id = ? AND status = 'active' ORDER BY maturity_date ASC";
  $stmt = $conn->prepare($investments_query);
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $result = $stmt->get_result();

  while ($row = $result->fetch_assoc()) {
    $active_investments[] = $row;
  }
  $stmt->close();
}

// Get recent transactions
$recent_transactions = [];
if ($user_id > 0) {
  $transactions_query = "SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 5";
  $stmt = $conn->prepare($transactions_query);
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $result = $stmt->get_result();

  while ($row = $result->fetch_assoc()) {
    $recent_transactions[] = $row;
  }
  $stmt->close();
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

// Function to process a referral when a new user registers
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
    // Insert referral record with explicit bonus amount
    $referral_query = "INSERT INTO referrals (referrer_id, referred_id, bonus_amount) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($referral_query);
    $stmt->bind_param("iid", $referredBy, $newUserId, $bonus_amount);
    $stmt->execute();

    // Check if wallet exists for referrer
    $wallet_check = "SELECT id FROM wallets WHERE user_id = ?";
    $stmt = $conn->prepare($wallet_check);
    $stmt->bind_param("i", $referredBy);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
      // Create wallet for referrer if it doesn't exist
      $create_wallet = "INSERT INTO wallets (user_id, balance, updated_at) VALUES (?, ?, NOW())";
      $stmt = $conn->prepare($create_wallet);
      $stmt->bind_param("id", $referredBy, $bonus_amount);
      $stmt->execute();
    } else {
      // Update existing wallet with explicit amount
      $update_wallet = "UPDATE wallets SET balance = balance + 5.00, updated_at = NOW() WHERE user_id = ?";
      $stmt = $conn->prepare($update_wallet);
      $stmt->bind_param("i", $referredBy);
      $stmt->execute();
    }

    // Record transaction with explicit amount
    $transaction_query = "INSERT INTO transactions (
                      user_id, 
                      transaction_type, 
                      amount, 
                      status, 
                      description,
                      reference_id
                  ) VALUES (?, 'deposit', ?, 'completed', ?, ?)";

    $description = "Referral Bonus";
    $reference_id = "REF-" . $newUserId;

    $stmt = $conn->prepare($transaction_query);
    $stmt->bind_param("idss", $referredBy, $bonus_amount, $description, $reference_id);
    $stmt->execute();

    // Update referral status to paid
    $update_referral = "UPDATE referrals SET status = 'paid', paid_at = NOW(), bonus_amount = ? WHERE referrer_id = ? AND referred_id = ?";
    $stmt = $conn->prepare($update_referral);
    $stmt->bind_param("dii", $bonus_amount, $referredBy, $newUserId);
    $stmt->execute();

    // Double-check that no extra amount was added (safety measure)
    $transaction_check = "SELECT id, amount FROM transactions 
                          WHERE user_id = ? AND reference_id = ? AND description = 'Referral Bonus'";
    $stmt = $conn->prepare($transaction_check);
    $stmt->bind_param("is", $referredBy, $reference_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
      $transaction = $result->fetch_assoc();
      if ($transaction['amount'] != $bonus_amount) {
        // If transaction amount doesn't match bonus_amount, fix it
        $update_transaction = "UPDATE transactions SET amount = ? WHERE id = ?";
        $stmt = $conn->prepare($update_transaction);
        $stmt->bind_param("di", $bonus_amount, $transaction['id']);
        $stmt->execute();
      }
    }

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

// Function to get all referrals made by a user
function getUserReferrals($userId)
{
  global $conn;

  $referrals = [];

  $query = "SELECT r.*, u.full_name, u.email, u.registration_date 
                FROM referrals r 
                JOIN users u ON r.referred_id = u.id 
                WHERE r.referrer_id = ? 
                ORDER BY r.created_at DESC";

  $stmt = $conn->prepare($query);
  $stmt->bind_param("i", $userId);
  $stmt->execute();
  $result = $stmt->get_result();

  while ($row = $result->fetch_assoc()) {
    $referrals[] = $row;
  }

  return $referrals;
}

// Function to get total referral earnings for a user
function getTotalReferralEarnings($userId)
{
  global $conn;

  $query = "SELECT SUM(bonus_amount) as total FROM referrals WHERE referrer_id = ? AND status = 'paid'";
  $stmt = $conn->prepare($query);
  $stmt->bind_param("i", $userId);
  $stmt->execute();
  $result = $stmt->get_result();
  $data = $result->fetch_assoc();

  return $data['total'] ?? 0;
}

// Get staking stats if staking functions are included
$staking_stats = [];
if (function_exists('getUserStakingStats')) {
  $staking_stats = getUserStakingStats($user_id);
}

// Process ticket purchase
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['purchase'])) {
  $ticket_id = filter_input(INPUT_POST, 'ticket_id', FILTER_SANITIZE_NUMBER_INT);
  $quantity = filter_input(INPUT_POST, 'quantity', FILTER_SANITIZE_NUMBER_INT);

  // Validate inputs
  if (!$ticket_id || !$quantity || $quantity < 1) {
    $error = "Invalid ticket selection or quantity.";
  } else {
    // Get ticket details
    $stmt = $conn->prepare("SELECT * FROM movie_tickets WHERE id = ? AND status = 'active'");
    $stmt->bind_param("i", $ticket_id);
    $stmt->execute();
    $ticket = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$ticket) {
      $error = "Selected ticket is not available.";
    } else {
      // Calculate total amount
      $total_amount = $ticket['price'] * $quantity;

      // Check if user has enough balance
      $stmt = $conn->prepare("SELECT balance FROM wallets WHERE user_id = ?");
      $stmt->bind_param("i", $user_id);
      $stmt->execute();
      $result = $stmt->get_result();
      $wallet = $result->fetch_assoc();
      $stmt->close();

      if (!$wallet || $wallet['balance'] < $total_amount) {
        $error = "Insufficient wallet balance. Please add funds.";
      } else {
        // Begin transaction
        $conn->begin_transaction();

        try {
          // Calculate expected profit (4.5% of total amount)
          $profit_rate = 0.045; // 4.5%
          $expected_profit = $total_amount * $profit_rate;
          $total_return = $total_amount + $expected_profit;

          // Generate unique purchase ID
          $purchase_id = 'TKT-' . rand(10000, 99999);

          // Set dates
          $purchase_date = date('Y-m-d H:i:s');
          $maturity_date = date('Y-m-d H:i:s', strtotime('+1 day')); // 1 day maturity

          // Insert purchase record
          $stmt = $conn->prepare("INSERT INTO ticket_purchases 
                          (purchase_id, user_id, ticket_id, quantity, unit_price, total_amount, 
                          expected_profit, total_return, status, purchase_date, maturity_date) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, ?)");

          $stmt->bind_param(
            "siiddddsss",
            $purchase_id,
            $user_id,
            $ticket_id,
            $quantity,
            $ticket['price'],
            $total_amount,
            $expected_profit,
            $total_return,
            $purchase_date,
            $maturity_date
          );

          $stmt->execute();
          $stmt->close();

          // Deduct amount from wallet
          $stmt = $conn->prepare("UPDATE wallets SET balance = balance - ?, updated_at = NOW() WHERE user_id = ?");
          $stmt->bind_param("di", $total_amount, $user_id);
          $stmt->execute();
          $stmt->close();

          // Record transaction
          $stmt = $conn->prepare("INSERT INTO transactions 
                         (user_id, transaction_type, amount, status, description, reference_id) 
                         VALUES (?, 'investment', ?, 'completed', ?, ?)");

          $description = "Purchase of " . $quantity . " " . $ticket['title'] . " ticket(s)";
          $stmt->bind_param("idss", $user_id, $total_amount, $description, $purchase_id);
          $stmt->execute();
          $stmt->close();

          // Commit transaction
          $conn->commit();
          $success = "Purchase successful! Your profit will be added to your wallet in 24 hours.";
        } catch (Exception $e) {
          // Rollback in case of error
          $conn->rollback();
          $error = "An error occurred: " . $e->getMessage();
        }
      }
    }
  }
}

// Get all active tickets
$query = "SELECT * FROM movie_tickets WHERE status = 'active' ORDER BY created_at DESC";
$result = $conn->query($query);
$tickets = [];

if ($result && $result->num_rows > 0) {
  while ($row = $result->fetch_assoc()) {
    $tickets[] = $row;
  }
}

// Get user's wallet balance
$stmt = $conn->prepare("SELECT balance FROM wallets WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$wallet = $result->fetch_assoc();
$balance = $wallet ? $wallet['balance'] : 0;
$stmt->close();

// Get user's recent purchases
$recent_purchases = [];
if ($user_id > 0) {
  $stmt = $conn->prepare("
         SELECT p.*, t.title, t.image 
         FROM ticket_purchases p
         JOIN movie_tickets t ON p.ticket_id = t.id
         WHERE p.user_id = ?
         ORDER BY p.purchase_date DESC
         LIMIT 5
     ");
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
      $recent_purchases[] = $row;
    }
  }
  $stmt->close();
}

// Get token data if applicable
$token_count = 0;
$days_held = 0;
if ($user_id > 0) {
  // Check if alpha_tokens table exists
  $check_tokens_table = "SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'alpha_tokens'";
  $result = $conn->query($check_tokens_table);

  if ($result && $result->fetch_assoc()['count'] > 0) {
    // Get token count
    $token_query = "SELECT COUNT(*) as token_count FROM alpha_tokens WHERE user_id = ? AND status = 'active'";
    $stmt = $conn->prepare($token_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
      $token_count = $row['token_count'];
    }

    // Get oldest token date
    $tokens_query = "SELECT purchase_date FROM alpha_tokens WHERE user_id = ? AND status = 'active' ORDER BY purchase_date ASC LIMIT 1";
    $stmt = $conn->prepare($tokens_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
      $purchase_date = new DateTime($row['purchase_date']);
      $current_date = new DateTime();
      $interval = $purchase_date->diff($current_date);
      $days_held = $interval->days;
    }
  }
}

// Use the existing format_currency function from db.php
if (!function_exists('format_currency')) {
  function format_currency($amount)
  {
    return '$' . number_format($amount, 2);
  }
}
// Fetch all active investment plans
$sql = "SELECT * FROM investment_plans WHERE is_active = 1 ORDER BY min_amount ASC";
$result = $conn->query($sql);

// Store plans in an array
$plans = [];
if ($result->num_rows > 0) {
  while ($row = $result->fetch_assoc()) {
    $plans[] = $row;
  }
}

// Include the HTML part below
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <?php include 'includes/head.php'; ?>
</head>

<body class="bg-gray-900 text-white font-sans min-h-screen flex flex-col">
  <?php include 'includes/navbar.php'; ?>
  <?php include 'includes/mobile-bar.php'; ?>
  <?php include 'includes/pre-loader.php'; ?>
  <?php include 'includes/announcement.php'; ?>
  <?php include 'includes/bottom-to-top.php'; ?>

  <!-- Main Content -->
  <main style="display: none;" id="main-content" class="flex-grow py-6">
    <div class="container mx-auto px-4 sm:px-6 lg:px-8">
      <!-- Welcome Section - With Better SVG -->
      <div class="mb-6 flex items-center space-x-3 py-3 px-4 bg-gradient-to-r from-gray-800 to-gray-900 rounded-lg border-l-4 border-blue-500 shadow-md">
        <div class="bg-gradient-to-r from-blue-500 to-indigo-600 p-2 rounded-full shadow">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-6 h-6">
            <!-- Premium user icon with crown -->
            <path d="M12 2L15 6L19 7L16 11L17 15H12H7L8 11L5 7L9 6L12 2Z" fill="rgba(255,255,255,0.2)" stroke="white" />
            <circle cx="12" cy="17" r="3" fill="rgba(255,255,255,0.2)" stroke="white" />
            <path d="M19 21H5C5 21 5 21 5 21C5 18.8 8.1 17 12 17C15.9 17 19 18.8 19 21C19 21 19 21 19 21Z" fill="rgba(255,255,255,0.2)" stroke="white" />
          </svg>
        </div>
        <div>
          <h1 class="text-xl sm:text-2xl font-bold text-white">Welcome back, <?php echo htmlspecialchars($user_name); ?>!</h1>
          <p class="text-gray-400 text-xs flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1 text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
            </svg>
            Your portfolio is looking good today
          </p>
        </div>
      </div>

      <!-- Stats Overview -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Total Balance -->
        <div class="bg-gradient-to-br from-gray-900 to-gray-800 rounded-xl p-6 border border-gray-700 shadow-lg transform hover:scale-105 transition-all duration-300 relative overflow-hidden">
          <div class="absolute top-0 right-0 w-20 h-20 bg-gradient-to-br from-yellow-400 to-yellow-600 opacity-20 rounded-bl-full"></div>
          <div class="flex justify-between items-start">
            <div>
              <p class="text-gray-400 text-sm font-medium uppercase tracking-wider">Total Balance</p>
              <h3 class="text-3xl font-extrabold text-white mt-2">$<?php echo number_format($balance, 2); ?></h3>
              <p class="text-green-400 text-sm flex items-center mt-2 font-medium">
                <i class="fas fa-chart-line mr-2"></i> Your net worth
              </p>
            </div>
            <div class="h-14 w-14 rounded-full gold-gradient flex items-center justify-center shadow-lg">
              <i class="fas fa-wallet text-black text-xl"></i>
            </div>
          </div>
        </div>

        <!-- Active Investments -->
        <div class="bg-gradient-to-br from-gray-900 to-gray-800 rounded-xl p-6 border border-gray-700 shadow-lg transform hover:scale-105 transition-all duration-300 relative overflow-hidden">
          <div class="absolute top-0 right-0 w-20 h-20 bg-gradient-to-br from-blue-400 to-blue-600 opacity-20 rounded-bl-full"></div>
          <div class="flex justify-between items-start">
            <div>
              <p class="text-gray-400 text-sm font-medium uppercase tracking-wider">Active Investments</p>
              <h3 class="text-3xl font-extrabold text-white mt-2"><?php echo count($active_investments); ?> Plans</h3>
              <p class="text-blue-400 text-sm flex items-center mt-2 font-medium">
                <i class="fas fa-ticket-alt mr-2"></i> Premium & Basic
              </p>
            </div>
            <div class="h-14 w-14 rounded-full bg-gradient-to-r from-blue-500 to-indigo-600 flex items-center justify-center shadow-lg">
              <i class="fas fa-chart-line text-white text-xl"></i>
            </div>
          </div>
        </div>

        <!-- Referral Stats -->
        <div class="bg-gradient-to-br from-gray-900 to-gray-800 rounded-xl p-6 border border-gray-700 shadow-lg transform hover:scale-105 transition-all duration-300 relative overflow-hidden">
          <div class="absolute top-0 right-0 w-20 h-20 bg-gradient-to-br from-green-400 to-green-600 opacity-20 rounded-bl-full"></div>
          <div class="flex justify-between items-start">
            <div>
              <p class="text-gray-400 text-sm font-medium uppercase tracking-wider">Referral Bonus</p>
              <h3 class="text-3xl font-extrabold text-white mt-2">$<?php
                                                                    // Get total referral earnings
                                                                    $ref_earnings = getTotalReferralEarnings($user_id);
                                                                    echo number_format($ref_earnings, 2);
                                                                    ?></h3>
              <p class="text-green-400 text-sm flex items-center mt-2 font-medium">
                <i class="fas fa-users mr-2"></i> $5 per referral
              </p>
            </div>
            <div class="h-14 w-14 rounded-full bg-gradient-to-r from-green-500 to-emerald-600 flex items-center justify-center shadow-lg">
              <i class="fas fa-gift text-white text-xl"></i>
            </div>
          </div>
        </div>

        <!-- Daily Check-in -->
        <div class="bg-gradient-to-br from-gray-900 to-gray-800 rounded-xl p-6 border border-gray-700 shadow-lg transform hover:scale-105 transition-all duration-300 relative overflow-hidden">
          <div class="absolute top-0 right-0 w-20 h-20 bg-gradient-to-br from-yellow-400 to-amber-600 opacity-20 rounded-bl-full"></div>
          <div class="flex justify-between items-start">
            <div>
              <p class="text-gray-400 text-sm font-medium uppercase tracking-wider">Daily Check-in</p>
              <?php if (!empty($streak_info) && isset($streak_info['streak'])): ?>
                <h3 class="text-3xl font-extrabold text-white mt-2"><?php echo $streak_info['streak']; ?> Day Streak</h3>
                <?php if ($streak_info['checked_in_today']): ?>
                  <p class="text-green-400 text-sm flex items-center mt-2 font-medium">
                    <i class="fas fa-check-circle mr-2"></i> Checked in today
                  </p>
                <?php else: ?>
                  <p class="text-yellow-400 text-sm flex items-center mt-2 font-medium">
                    <i class="fas fa-exclamation-circle mr-2"></i> Claim $<?php echo number_format($streak_info['next_reward'], 2); ?> today
                  </p>
                <?php endif; ?>
              <?php else: ?>
                <h3 class="text-3xl font-extrabold text-white mt-2">Check In Daily</h3>
                <p class="text-yellow-400 text-sm flex items-center mt-2 font-medium">
                  <i class="fas fa-gift mr-2"></i> Earn daily rewards
                </p>
              <?php endif; ?>
            </div>
            <div class="h-14 w-14 rounded-full bg-gradient-to-r from-amber-500 to-yellow-600 flex items-center justify-center shadow-lg">
              <i class="fas fa-calendar-check text-white text-xl"></i>
            </div>
          </div>
          <?php if (empty($streak_info) || !$streak_info['checked_in_today']): ?>
            <div class="mt-5">
              <a href="daily-checkin.php" class="block w-full bg-gradient-to-r from-yellow-500 to-amber-600 hover:from-yellow-600 hover:to-amber-700 text-black text-center text-sm font-bold py-3 px-4 rounded-lg transition duration-300 transform hover:-translate-y-1 shadow-md">
                Check In Now
              </a>
            </div>
          <?php endif; ?>
        </div>
      </div>
      <!-- Divider with Icon -->
      <div class="flex items-center my-8">
        <div class="flex-grow h-px bg-gray-300"></div>
        <div class="px-4">
          <!-- You can use any icon here - this example uses a basic SVG star -->
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
          </svg>
        </div>
        <div class="flex-grow h-px bg-gray-300"></div>
      </div>
      <!-- Wallet Card -->
      <div class="bg-gradient-to-r from-gray-900 to-black rounded-xl p-6 mb-6 border border-gray-700 relative overflow-hidden w-full max-w-md mx-auto sm:max-w-lg lg:max-w-full">
        <div class="absolute top-0 right-0 mt-4 mr-4">
          <i class="fas fa-gem text-yellow-500 text-2xl"></i>
        </div>
        <p class="text-gray-400 mb-1 text-sm sm:text-base">Available Balance</p>
        <h3 class="text-2xl font-bold mt-1"><?php echo format_currency($balance); ?></h3>

        <div class="flex flex-col sm:flex-row sm:justify-between items-start sm:items-end space-y-4 sm:space-y-0">
          <div>
            <p class="text-gray-400 text-sm sm:text-base"><?php echo htmlspecialchars($user_name); ?></p>
            <p class="text-gray-500 text-xs sm:text-sm">Member since <?php echo $member_since; ?></p>
          </div>
          <div class="flex flex-wrap justify-start sm:justify-end gap-2">
            <button class="bg-gray-700 hover:bg-gray-600 text-white p-2 rounded-lg transition duration-300 w-full sm:w-auto"
              onclick="copyToClipboard('<?php echo $wallet_id; ?>')" title="Copy Wallet ID">
              <i class="fas fa-copy"></i>
            </button>
            <button class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition duration-300 w-full sm:w-auto"
              onclick="document.getElementById('depositModal').classList.remove('hidden')" title="Deposit Funds">
              Deposit
            </button>
            <button class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg transition duration-300 w-full sm:w-auto"
              onclick="window.location.href='withdraw.php?id=<?php echo $user_id; ?>'" title="Withdraw Funds">
              Withdraw
            </button>
          </div>
        </div>
      </div>
      <!-- 
        


=============================================



-->

      <!-- FIND: A good spot in your dashboard HTML to display notifications -->
      <!-- ADD THIS CODE: -->
      <?php if (!empty($profit_notification)): ?>
        <div class="mb-6 flex items-center space-x-3 py-3 px-4 bg-gradient-to-r from-green-800 to-green-900 rounded-lg border-l-4 border-green-500 shadow-md">
          <div class="bg-gradient-to-r from-green-500 to-green-600 p-2 rounded-full shadow">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-6 h-6">
              <circle cx="12" cy="12" r="10"></circle>
              <path d="M8 14s1.5 2 4 2 4-2 4-2"></path>
              <line x1="9" y1="9" x2="9.01" y2="9"></line>
              <line x1="15" y1="9" x2="15.01" y2="9"></line>
            </svg>
          </div>
          <div>
            <p class="text-white"><?php echo htmlspecialchars($profit_notification); ?></p>
          </div>
        </div>
      <?php endif; ?>








      <!-- Divider with Icon -->
      <div class="flex items-center my-8">
        <div class="flex-grow h-px bg-gray-300"></div>
        <div class="px-4">
          <!-- You can use any icon here - this example uses a basic SVG star -->
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
          </svg>
        </div>
        <div class="flex-grow h-px bg-gray-300"></div>
      </div>

      <!-- Movie Tickets Section -->
      <div class="w-full bg-gray-900 text-white min-h-screen">
        <div class="container mx-auto px-4 py-8">
          <h1 class="text-3xl font-bold mb-8">Movie Tickets</h1>

          <?php if (isset($error)): ?>
            <div class="bg-red-500 text-white p-4 rounded-lg mb-6">
              <?php echo htmlspecialchars($error); ?>
            </div>
          <?php endif; ?>

          <?php if (isset($success)): ?>
            <div class="bg-green-500 text-white p-4 rounded-lg mb-6">
              <?php echo htmlspecialchars($success); ?>
            </div>
          <?php endif; ?>

          <!-- Recent Purchases -->
          <?php if (!empty($recent_purchases)): ?>
            <div class="mb-8">
              <h2 class="text-2xl font-bold mb-4">Your Recent Purchases</h2>

              <!-- Desktop View (md and above) -->
              <div class="hidden md:block bg-gray-800 rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                  <table class="w-full">
                    <thead class="bg-gray-700">
                      <tr>
                        <th class="px-4 py-2 text-left">Ticket</th>
                        <th class="px-4 py-2 text-left">Quantity</th>
                        <th class="px-4 py-2 text-left">Total</th>
                        <th class="px-4 py-2 text-left">Expected Profit</th>
                        <th class="px-4 py-2 text-left">Maturity Date</th>
                        <th class="px-4 py-2 text-left">Status</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($recent_purchases as $purchase): ?>
                        <tr class="border-t border-gray-700">
                          <td class="px-4 py-3 flex items-center">
                            <img src="../uploads/tickets/<?php echo htmlspecialchars($purchase['image']); ?>"
                              alt="<?php echo htmlspecialchars($purchase['title']); ?>"
                              class="w-10 h-10 object-cover rounded mr-2">
                            <?php echo htmlspecialchars($purchase['title']); ?>
                          </td>
                          <td class="px-4 py-3"><?php echo $purchase['quantity']; ?></td>
                          <td class="px-4 py-3">$<?php echo number_format($purchase['total_amount'], 2); ?></td>
                          <td class="px-4 py-3 text-green-500">+$<?php echo number_format($purchase['expected_profit'], 2); ?></td>
                          <td class="px-4 py-3"><?php echo date('M d, Y H:i', strtotime($purchase['maturity_date'])); ?></td>
                          <td class="px-4 py-3">
                            <?php if ($purchase['status'] === 'active'): ?>
                              <span class="px-2 py-1 bg-blue-500 text-white rounded-full text-xs">Active</span>
                            <?php elseif ($purchase['status'] === 'completed'): ?>
                              <span class="px-2 py-1 bg-green-500 text-white rounded-full text-xs">Completed</span>
                            <?php else: ?>
                              <span class="px-2 py-1 bg-red-500 text-white rounded-full text-xs">Cancelled</span>
                            <?php endif; ?>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>

              <!-- Mobile View (sm and below) -->
              <div class="md:hidden space-y-4">
                <?php foreach ($recent_purchases as $purchase): ?>
                  <div class="bg-gray-800 rounded-lg p-4">
                    <div class="flex items-center mb-3">
                      <img src="../uploads/tickets/<?php echo htmlspecialchars($purchase['image']); ?>"
                        alt="<?php echo htmlspecialchars($purchase['title']); ?>"
                        class="w-12 h-12 object-cover rounded mr-3">
                      <div>
                        <h3 class="font-semibold"><?php echo htmlspecialchars($purchase['title']); ?></h3>
                        <?php if ($purchase['status'] === 'active'): ?>
                          <span class="px-2 py-1 bg-blue-500 text-white rounded-full text-xs">Active</span>
                        <?php elseif ($purchase['status'] === 'completed'): ?>
                          <span class="px-2 py-1 bg-green-500 text-white rounded-full text-xs">Completed</span>
                        <?php else: ?>
                          <span class="px-2 py-1 bg-red-500 text-white rounded-full text-xs">Cancelled</span>
                        <?php endif; ?>
                      </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2 text-sm">
                      <div>
                        <p class="text-gray-400">Quantity</p>
                        <p><?php echo $purchase['quantity']; ?></p>
                      </div>
                      <div>
                        <p class="text-gray-400">Total</p>
                        <p>$<?php echo number_format($purchase['total_amount'], 2); ?></p>
                      </div>
                      <div>
                        <p class="text-gray-400">Expected Profit</p>
                        <p class="text-green-500">+$<?php echo number_format($purchase['expected_profit'], 2); ?></p>
                      </div>
                      <div>
                        <p class="text-gray-400">Maturity Date</p>
                        <p><?php echo date('M d, Y', strtotime($purchase['maturity_date'])); ?></p>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>

              <div class="mt-4 text-right">
                <a href="ticket_history.php" class="text-yellow-500 hover:text-yellow-400">View All Purchases →</a>
              </div>
            </div>
          <?php endif; ?>

          <!-- Available Tickets -->
          <h2 class="text-2xl font-bold mb-4">Available Tickets</h2>

          <?php if (empty($tickets)): ?>
            <div class="bg-gray-800 rounded-lg p-8 text-center">
              <p class="text-lg text-gray-400">No tickets available at the moment. Please check back later.</p>
            </div>
          <?php else: ?>
            <!-- Desktop view (md and up): Standard grid layout -->
            <div class="hidden md:grid md:grid-cols-3 lg:grid-cols-4 gap-6">
              <?php foreach ($tickets as $ticket): ?>
                <div class="bg-gray-800 rounded-lg overflow-hidden shadow-lg flex flex-col h-full">
                  <div class="aspect-[3/2] w-full">
                    <img src="../uploads/tickets/<?php echo htmlspecialchars($ticket['image']); ?>"
                      alt="<?php echo htmlspecialchars($ticket['title']); ?>"
                      class="w-full h-full object-cover">
                  </div>

                  <div class="p-4 flex-grow flex flex-col">
                    <h3 class="text-xl font-bold mb-2"><?php echo htmlspecialchars($ticket['title']); ?></h3>

                    <div class="mb-4 flex-grow">
                      <p class="text-gray-400"><?php echo nl2br(htmlspecialchars($ticket['description'])); ?></p>
                    </div>

                    <div class="flex justify-between items-center mb-4">
                      <div>
                        <p class="text-lg font-bold text-yellow-500">$<?php echo number_format($ticket['price'], 2); ?></p>
                        <p class="text-sm text-green-500">+4.5% profit in 24 hours</p>
                      </div>
                    </div>

                    <form method="POST" class="flex items-center">
                      <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                      <input type="number" name="quantity" min="1" value="1"
                        class="w-20 bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 mr-2 text-white">
                      <button type="submit" name="purchase"
                        class="flex-grow bg-yellow-500 hover:bg-yellow-600 text-black font-bold py-2 px-4 rounded-lg transition duration-300">
                        Purchase
                      </button>
                    </form>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>

            <!-- Mobile view (sm and below): Horizontal card layout -->
            <div class="md:hidden space-y-4">
              <?php foreach ($tickets as $ticket): ?>
                <div class="bg-gray-800 rounded-lg overflow-hidden shadow-lg">
                  <div class="flex h-32">
                    <!-- Left side: Image with fixed height -->
                    <div class="w-2/5">
                      <img src="../uploads/tickets/<?php echo htmlspecialchars($ticket['image']); ?>"
                        alt="<?php echo htmlspecialchars($ticket['title']); ?>"
                        class="w-full h-32 object-cover">
                    </div>

                    <!-- Right side: Content -->
                    <div class="w-3/5 p-3 flex flex-col justify-between">
                      <div>
                        <h3 class="text-base font-bold mb-1 line-clamp-1"><?php echo htmlspecialchars($ticket['title']); ?></h3>
                        <p class="text-gray-400 text-xs line-clamp-2 mb-2">
                          <?php
                          // Truncate description to 60 characters
                          $short_desc = strlen($ticket['description']) > 60 ?
                            substr($ticket['description'], 0, 60) . '...' :
                            $ticket['description'];
                          echo nl2br(htmlspecialchars($short_desc));
                          ?>
                        </p>
                      </div>

                      <div>
                        <p class="text-base font-bold text-yellow-500">$<?php echo number_format($ticket['price'], 2); ?></p>
                        <p class="text-xs text-green-500">+4.5% profit in 24 hours</p>
                      </div>
                    </div>
                  </div>
                  <hr>
                  <!-- Purchase form with plus/minus controls -->
                  <div class="bg-gray-850 p-3 rounded-b-lg">
                    <form method="POST" class="flex items-center gap-3">
                      <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">

                      <!-- Quantity selector with plus/minus -->
                      <div class="flex items-center h-10 bg-gray-900 rounded-lg">
                        <button type="button" onclick="decrementQuantity(this)" class="w-8 h-full flex items-center justify-center text-gray-400 hover:text-white focus:outline-none">
                          <i class="fas fa-minus text-xs"></i>
                        </button>

                        <input type="number" name="quantity" min="1" value="1" readonly
                          class="w-8 h-full bg-transparent border-0 text-center text-white text-sm focus:outline-none"
                          oninput="this.value = this.value.replace(/[^0-9]/g, '')">

                        <button type="button" onclick="incrementQuantity(this)" class="w-8 h-full flex items-center justify-center text-gray-400 hover:text-white focus:outline-none">
                          <i class="fas fa-plus text-xs"></i>
                        </button>
                      </div>

                      <!-- Purchase button with custom style -->
                      <button type="submit" name="purchase"
                        class="flex-grow h-10 bg-black border-2 border-yellow-500 text-yellow-500 font-medium rounded-lg hover:bg-yellow-500 hover:text-black transition-colors duration-300">
                        <span style="font-size: smaller;">BuyTicket</span>
                      </button>
                    </form>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>

            <script>
              function incrementQuantity(button) {
                const input = button.parentNode.querySelector('input[type=number]');
                input.value = parseInt(input.value) + 1;
              }

              function decrementQuantity(button) {
                const input = button.parentNode.querySelector('input[type=number]');
                const value = parseInt(input.value);
                if (value > 1) {
                  input.value = value - 1;
                }
              }
            </script>
          <?php endif; ?>
        </div>
      </div>
      <!-- Divider with Icon -->
      <div class="flex items-center my-8">
        <div class="flex-grow h-px bg-gray-300"></div>
        <div class="px-4">
          <!-- You can use any icon here - this example uses a basic SVG star -->
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
          </svg>
        </div>
        <div class="flex-grow h-px bg-gray-300"></div>
      </div>
      <!-- Buttons with Check-in Button added -->
      <div class="flex flex-col md:flex-row justify-center space-y-4 md:space-y-0 md:space-x-4 mt-6">
        <a href="referrals.php" class="bg-gray-700 hover:bg-gray-600 text-white font-bold py-3 px-6 rounded-lg transition duration-300 flex items-center justify-center">
          <i class="fas fa-user-plus mr-2"></i> Invite Friends
        </a>
        <a href="daily-checkin.php" class="bg-gray-700 hover:bg-gray-600 text-white font-bold py-3 px-6 rounded-lg transition duration-300 flex items-center justify-center">
          <i class="fas fa-calendar-check mr-2"></i> Daily Check-in
        </a>
      </div>

      <!-- Loading indicator (hidden by default) -->
      <div id="loading-indicator" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-gray-800 p-6 rounded-lg shadow-lg text-center">
          <div class="animate-spin rounded-full h-10 w-10 border-t-2 border-b-2 border-yellow-500 mx-auto mb-4"></div>
          <p class="text-white">Processing your investment...</p>
        </div>
      </div>


      <!-- Loading indicator (hidden by default) -->
      <div id="loading-indicator" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-gray-800 p-6 rounded-lg shadow-lg text-center">
          <div class="animate-spin rounded-full h-10 w-10 border-t-2 border-b-2 border-yellow-500 mx-auto mb-4"></div>
          <p class="text-white">Processing your investment...</p>
        </div>
      </div>
      <!-- Divider with Icon -->
      <div class="flex items-center my-8">
        <div class="flex-grow h-px bg-gray-300"></div>
        <div class="px-4">
          <!-- You can use any icon here - this example uses a basic SVG star -->
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
          </svg>
        </div>
        <div class="flex-grow h-px bg-gray-300"></div>
      </div>
      <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-center mb-8">Our Investment Plans</h1>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <?php
          // Display the first three regular plans (excluding Custom)
          $planCounter = 0;
          $customPlan = null;

          foreach ($plans as $plan) {
            // Store the Custom plan separately
            if ($plan['name'] === 'Custom') {
              $customPlan = $plan;
              continue;
            }

            // Handle Professional plan separately
            if ($plan['name'] === 'Professional') {
              continue;
            }

            // Only show first 3 non-Custom, non-Professional plans in the grid
            if ($planCounter < 3) {
              $isPremium = $plan['name'] === 'Premium';
              $planCounter++;
          ?>

              <!-- <?php echo $plan['name']; ?> Plan -->
              <div class="<?php echo $isPremium ? 'bg-gradient-to-b from-gray-900 to-black rounded-xl shadow-2xl overflow-hidden border border-yellow-500 transform scale-105 relative' : 'bg-gray-800 bg-opacity-50 backdrop-filter backdrop-blur-sm rounded-xl shadow-lg overflow-hidden border border-gray-700 hover:border-yellow-500 transition-all duration-300 card-hover'; ?>">
                <?php if ($isPremium) { ?>
                  <div class="absolute top-0 right-0 bg-yellow-500 text-black font-bold px-4 py-1">
                    Most Popular
                  </div>
                <?php } ?>
                <div class="p-6">
                  <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-bold"><?php echo $plan['name']; ?></h3>
                    <span class="<?php echo $isPremium ? 'bg-blue-600' : ($plan['name'] === 'Standard' ? 'bg-green-800' : 'bg-blue-800'); ?> rounded-full px-3 py-1 text-sm font-semibold">
                      <?php echo $isPremium ? 'Recommended' : ($plan['name'] === 'Standard' ? 'Popular' : 'Starter'); ?>
                    </span>
                  </div>
                  <div class="mb-4 flex items-baseline">
                    <span class="text-3xl font-bold"><?php echo $plan['daily_profit_rate']; ?>%</span>
                    <span class="text-lg text-gray-400 ml-2">return</span>
                  </div>
                  <ul class="space-y-2 mb-6">
                    <li class="flex items-center">
                      <i class="fas fa-check text-yellow-500 mt-1 mr-3"></i>
                      <span class="text-gray-300"><?php echo $plan['min_amount']; ?>$ minimum investment</span>
                    </li>
                    <li class="flex items-center">
                      <i class="fas fa-check text-yellow-500 mt-1 mr-3"></i>
                      <span class="text-gray-300">Profit after 24 hours</span>
                    </li>
                    <li class="flex items-center">
                      <i class="fas fa-check text-yellow-500 mt-1 mr-3"></i>
                      <span class="text-gray-300"><?php echo $plan['name'] === 'Basic' ? 'Basic' : ($plan['name'] === 'Standard' ? 'Enhanced' : 'Priority'); ?> support</span>
                    </li>
                    <?php if ($plan['name'] !== 'Basic') { ?>
                      <li class="flex items-center">
                        <i class="fas fa-check text-yellow-500 mt-1 mr-3"></i>
                        <span class="text-gray-300"><?php echo $plan['name'] === 'Standard' ? 'Email notifications' : 'Early access to new plans'; ?></span>
                      </li>
                    <?php } ?>
                  </ul>
                  <button onclick="activatePlan('<?php echo $plan['name']; ?>', <?php echo $plan['min_amount']; ?>)"
                    class="w-full <?php echo $isPremium ? 'bg-gradient-to-r from-yellow-500 to-yellow-600 hover:from-yellow-600 hover:to-yellow-700 text-black' : 'bg-gray-700 hover:bg-gradient-to-r hover:from-yellow-500 hover:to-yellow-600 hover:text-black text-white'; ?> font-bold py-3 px-4 rounded-lg transition duration-300 <?php echo $isPremium ? 'shadow-lg' : ''; ?>">
                    Buy Now
                  </button>
                </div>
              </div>
            <?php
            }
          }

          // Display Professional Plan
          $professionalPlan = null;
          foreach ($plans as $plan) {
            if ($plan['name'] === 'Professional') {
              $professionalPlan = $plan;
              break;
            }
          }

          if ($professionalPlan) {
            ?>
            <!-- Professional Plan -->
            <div class="w-full max-w-md mx-auto gradient-border rounded-2xl shadow-2xl overflow-hidden bg-gray-900 bg-opacity-80 backdrop-filter backdrop-blur-lg border border-gray-700 hover:border-yellow-500 transition-all duration-300 card-hover">
              <div class="p-8 relative">
                <!-- Elegant Header -->
                <div class="flex justify-between items-center mb-6">
                  <div class="flex items-center">
                    <h3 class="text-3xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-yellow-400 to-yellow-600">Professional</h3>
                    <span class="ml-4 bg-purple-800 bg-opacity-50 text-purple-200 rounded-full px-4 py-1 text-sm font-semibold uppercase tracking-wider">Elite</span>
                  </div>
                  <div class="text-yellow-400">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" viewBox="0 0 20 20" fill="currentColor">
                      <path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.532 1.532 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.532 1.532 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd" />
                    </svg>
                  </div>
                </div>

                <!-- Pricing Section -->
                <div class="mb-6 flex items-baseline">
                  <span class="text-5xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-yellow-400 to-yellow-600"><?php echo $professionalPlan['daily_profit_rate']; ?>%</span>
                  <span class="text-xl text-gray-400 ml-3 tracking-wider">return</span>
                </div>

                <!-- Features List -->
                <ul class="space-y-4 mb-8">
                  <li class="flex items-center group">
                    <div class="mr-4 transform group-hover:scale-110 transition-transform duration-300">
                      <i class="fas fa-check-circle text-yellow-500 text-2xl"></i>
                    </div>
                    <span class="text-gray-300 group-hover:text-white transition-colors duration-300"><?php echo $professionalPlan['min_amount']; ?>$ minimum investment</span>
                  </li>
                  <li class="flex items-center group">
                    <div class="mr-4 transform group-hover:scale-110 transition-transform duration-300">
                      <i class="fas fa-check-circle text-yellow-500 text-2xl"></i>
                    </div>
                    <span class="text-gray-300 group-hover:text-white transition-colors duration-300">Profit after 24 hours</span>
                  </li>
                  <li class="flex items-center group">
                    <div class="mr-4 transform group-hover:scale-110 transition-transform duration-300">
                      <i class="fas fa-check-circle text-yellow-500 text-2xl"></i>
                    </div>
                    <span class="text-gray-300 group-hover:text-white transition-colors duration-300">24/7 VIP dedicated support</span>
                  </li>
                  <li class="flex items-center group">
                    <div class="mr-4 transform group-hover:scale-110 transition-transform duration-300">
                      <i class="fas fa-check-circle text-yellow-500 text-2xl"></i>
                    </div>
                    <span class="text-gray-300 group-hover:text-white transition-colors duration-300">Custom support</span>
                  </li>
                </ul>

                <!-- Call to Action Button -->
                <button onclick="activatePlan('Professional', <?php echo $professionalPlan['min_amount']; ?>)" class="w-full relative overflow-hidden rounded-xl py-4 px-6 text-lg font-bold text-white transition-all duration-300 
                        bg-gradient-to-r from-yellow-500 to-yellow-600 
                        hover:from-yellow-600 hover:to-yellow-700 
                        focus:outline-none focus:ring-4 focus:ring-yellow-300
                        transform hover:scale-105 active:scale-95
                        shadow-xl hover:shadow-2xl">
                  <span class="absolute top-0 left-0 w-full h-full opacity-0 group-hover:opacity-10 transition-opacity duration-300"></span>
                  Buy Now
                </button>
              </div>
            </div>
          <?php } ?>
        </div>

        <?php if ($customPlan) { ?>
          <!-- Custom Plan Row -->
          <div class="mt-8">
            <div id="premium-card" class="bg-gradient-to-r from-gray-800 to-gray-900 rounded-xl shadow-lg overflow-hidden border border-yellow-700 hover:border-yellow-500 transition-all duration-300 premium-card gold-shimmer">
              <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                  <h3 class="text-xl font-bold"><?php echo $customPlan['name']; ?></h3>
                  <span class="bg-yellow-800 rounded-full px-3 py-1 text-sm font-semibold flex items-center">
                    <i class="fas fa-crown text-yellow-400 mr-1 text-xs"></i>
                    Exclusive
                  </span>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <div class="float-effect">
                    <div class="mb-4 flex items-baseline bg-black bg-opacity-20 p-3 rounded-lg border-l-2 border-yellow-600">
                      <span class="text-3xl font-bold"><?php echo $customPlan['daily_profit_rate']; ?>% Profit</span>
                      <span class="text-lg text-yellow-400 ml-2">return</span>
                    </div>
                    <ul class="space-y-2 mb-6">
                      <li class="flex items-center">
                        <i class="fas fa-check text-yellow-500 mt-1 mr-3"></i>
                        <span class="text-gray-300"><?php echo $customPlan['min_amount']; ?>$ minimum investment</span>
                      </li>
                      <li class="flex items-center">
                        <i class="fas fa-check text-yellow-500 mt-1 mr-3"></i>
                        <span class="text-gray-300">No maximum limit</span>
                      </li>
                      <li class="flex items-center">
                        <i class="fas fa-check text-yellow-500 mt-1 mr-3"></i>
                        <span class="text-gray-300">Profit after 24 hours</span>
                      </li>
                      <li class="flex items-center">
                        <i class="fas fa-check text-yellow-500 mt-1 mr-3"></i>
                        <span class="text-gray-300">Premium 24/7 support</span>
                      </li>
                    </ul>
                  </div>
                  <div>
                    <div class="mb-4">
                      <label for="custom-amount" class="block text-sm font-medium text-yellow-300 mb-2">Investment Amount $</label>
                      <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                          <span class="text-gray-400">$</span>
                        </div>
                        <input type="number" id="custom-amount" min="<?php echo $customPlan['min_amount']; ?>"
                          step="1000" value="<?php echo $customPlan['min_amount']; ?>"
                          class="pl-10 bg-gray-700 border border-gray-600 rounded-md py-2 px-4 w-full text-white focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent input-glow">
                      </div>
                      <p class="text-xs text-gray-400 mt-1">Minimum: <?php echo $customPlan['min_amount']; ?>$</p>
                    </div>
                    <div class="mb-6">
                      <div class="bg-black bg-opacity-30 rounded-md p-4 border border-gray-800">
                        <div class="flex justify-between items-center mb-2 border-b border-gray-700 pb-2">
                          <span class="text-gray-400">Your investment:</span>
                          <span class="text-white font-bold" id="investment-display"><?php echo $customPlan['min_amount']; ?>$</span>
                        </div>
                        <div class="flex justify-between items-center mb-2 border-b border-gray-700 pb-2">
                          <span class="text-gray-400">Expected profit:</span>
                          <span class="text-green-500 font-bold" id="profit-display">$<?php echo number_format($customPlan['min_amount'] * ($customPlan['daily_profit_rate'] / 100), 2); ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                          <span class="text-gray-400">Total return:</span>
                          <span class="text-yellow-500 font-bold" id="return-display">$<?php echo number_format($customPlan['min_amount'] * (1 + $customPlan['daily_profit_rate'] / 100), 2); ?></span>
                        </div>
                      </div>
                    </div>
                    <button id="custom-plan-btn" onclick="activatePlan('Custom', document.getElementById('custom-amount').value)"
                      class="w-full bg-gradient-to-r from-yellow-500 to-yellow-600 hover:from-yellow-600 hover:to-yellow-700 text-black font-bold py-3 px-4 rounded-lg transition duration-300 shadow-lg button-shine">
                      Create Custom Plan
                      <i class="fas fa-arrow-right ml-2"></i>
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        <?php } ?>
      </div>

      <script>
        document.addEventListener('DOMContentLoaded', function() {
          const customAmountInput = document.getElementById('custom-amount');
          if (customAmountInput) {
            const investmentDisplay = document.getElementById('investment-display');
            const profitDisplay = document.getElementById('profit-display');
            const returnDisplay = document.getElementById('return-display');
            const profitRate = <?php echo isset($customPlan) ? $customPlan['daily_profit_rate'] / 100 : 0.004; ?>;

            customAmountInput.addEventListener('input', function() {
              const amount = parseFloat(this.value);
              if (isNaN(amount)) return;

              const profit = amount * profitRate;

              investmentDisplay.textContent = `${amount.toFixed(2)}$`;
              profitDisplay.textContent = `$${profit.toFixed(2)}`;
              returnDisplay.textContent = `$${(amount + profit).toFixed(2)}`;
            });
          }
        });

        function activatePlan(planName, amount) {
          console.log(`Activating ${planName} plan with $${amount}`);
          // Add your activation logic here
          // For example, redirect to a payment page or show a modal
          // window.location.href = `/activate-plan.php?plan=${planName}&amount=${amount}`;
        }
      </script>


      <!-- Your Investments Section -->
      <div class="mt-8">
        <div class="flex justify-between items-center mb-6">
          <h2 class="text-xl font-bold">Your Active Investments</h2>
          <a href="#" class="text-yellow-500 hover:text-yellow-400 transition duration-300 text-sm flex items-center">
            View All <i class="fas fa-arrow-right ml-2"></i>
          </a>
        </div>

        <!-- Desktop View (md and above) -->
        <div class="hidden md:block overflow-x-auto">
          <table class="w-full whitespace-nowrap">
            <thead>
              <tr class="bg-gray-800 text-left">
                <th class="px-6 py-3 rounded-l-lg text-xs font-medium text-gray-400 uppercase tracking-wider">Plan</th>
                <th class="px-6 py-3 text-xs font-medium text-gray-400 uppercase tracking-wider">Investment</th>
                <th class="px-6 py-3 text-xs font-medium text-gray-400 uppercase tracking-wider">Return</th>
                <th class="px-6 py-3 text-xs font-medium text-gray-400 uppercase tracking-wider">Next Payout</th>
                <th class="px-6 py-3 text-xs font-medium text-gray-400 uppercase tracking-wider">Duration</th>
                <th class="px-6 py-3 rounded-r-lg text-xs font-medium text-gray-400 uppercase tracking-wider">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-700 bg-gray-800 rounded-lg overflow-hidden">
              <?php if (empty($active_investments)): ?>
                <tr>
                  <td colspan="6" class="px-6 py-4 text-center text-gray-400">
                    No active investments found. Buy a plan to get started!
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($active_investments as $investment): ?>
                  <?php
                  // Calculate progress percentage
                  $start = strtotime($investment['start_date']);
                  $end = strtotime($investment['maturity_date']);
                  $now = time();
                  $progress = min(100, max(0, (($now - $start) / ($end - $start)) * 100));

                  // Calculate time remaining
                  $remaining = $end - $now;
                  $hours_remaining = floor($remaining / 3600);
                  $minutes_remaining = floor(($remaining % 3600) / 60);

                  // Get appropriate background color for plan type
                  $plan_color = '';
                  switch ($investment['plan_type']) {
                    case 'Basic':
                      $plan_color = 'bg-green-800';
                      break;
                    case 'Premium':
                      $plan_color = 'bg-blue-800';
                      break;
                    case 'Professional':
                      $plan_color = 'bg-purple-800';
                      break;
                    default:
                      $plan_color = 'bg-gray-800';
                  }
                  ?>
                  <tr class="group hover:bg-gray-750">
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="flex items-center">
                        <div class="h-10 w-10 rounded-full <?php echo $plan_color; ?> flex items-center justify-center">
                          <i class="fas fa-ticket-alt text-white"></i>
                        </div>
                        <div class="ml-4">
                          <div class="text-sm font-medium"><?php echo htmlspecialchars($investment['plan_type']); ?> Plan</div>
                          <div class="text-xs text-gray-400">#<?php echo htmlspecialchars($investment['investment_id']); ?></div>
                        </div>
                      </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="text-sm">$<?php echo number_format($investment['amount'], 2); ?></div>
                      <div class="text-xs text-gray-400">Invested: <?php echo date('M d, Y', strtotime($investment['start_date'])); ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="text-sm">$<?php echo number_format($investment['expected_profit'], 2); ?></div>
                      <div class="text-xs text-green-500">4.5% profit</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="text-sm">$<?php echo number_format($investment['total_return'], 2); ?></div>
                      <div class="text-xs text-yellow-500">
                        <?php if ($remaining > 0): ?>
                          in <?php echo $hours_remaining; ?> hours <?php echo $minutes_remaining; ?> min
                        <?php else: ?>
                          processing...
                        <?php endif; ?>
                      </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="flex items-center">
                        <div class="w-16 bg-gray-700 rounded-full h-2 mr-2">
                          <div class="bg-yellow-500 h-2 rounded-full" style="width: <?php echo $progress; ?>%"></div>
                        </div>
                        <span class="text-xs text-gray-400"><?php echo round($progress); ?>%</span>
                      </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <span class="px-2 py-1 text-xs rounded-full bg-green-900 text-green-400">Active</span>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <!-- Mobile View (sm and below) -->
        <div class="md:hidden">
          <?php if (empty($active_investments)): ?>
            <div class="bg-gray-800 rounded-lg p-4 text-center text-gray-400">
              No active investments found. Buy a plan to get started!
            </div>
          <?php else: ?>
            <div class="space-y-4">
              <?php foreach ($active_investments as $investment): ?>
                <?php
                // Calculate progress percentage
                $start = strtotime($investment['start_date']);
                $end = strtotime($investment['maturity_date']);
                $now = time();
                $progress = min(100, max(0, (($now - $start) / ($end - $start)) * 100));

                // Calculate time remaining
                $remaining = $end - $now;
                $hours_remaining = floor($remaining / 3600);
                $minutes_remaining = floor(($remaining % 3600) / 60);

                // Get appropriate background color for plan type
                $plan_color = '';
                switch ($investment['plan_type']) {
                  case 'Basic':
                    $plan_color = 'bg-green-800';
                    break;
                  case 'Premium':
                    $plan_color = 'bg-blue-800';
                    break;
                  case 'Professional':
                    $plan_color = 'bg-purple-800';
                    break;
                  default:
                    $plan_color = 'bg-gray-800';
                }
                ?>
                <div class="bg-gray-800 rounded-lg p-4">
                  <div class="flex items-center">
                    <div class="h-10 w-10 rounded-full <?php echo $plan_color; ?> flex items-center justify-center flex-shrink-0">
                      <i class="fas fa-ticket-alt text-white"></i>
                    </div>
                    <div class="ml-3">
                      <div class="flex items-center justify-between w-full">
                        <div>
                          <div class="text-sm font-medium"><?php echo htmlspecialchars($investment['plan_type']); ?> Plan</div>
                          <div class="text-xs text-gray-400">#<?php echo htmlspecialchars($investment['investment_id']); ?></div>
                        </div>
                        <span class="px-2 py-1 text-xs rounded-full bg-green-900 text-green-400">Active</span>
                      </div>
                    </div>
                  </div>

                  <div class="mt-4 grid grid-cols-2 gap-3">
                    <div>
                      <div class="text-xs text-gray-400">Investment</div>
                      <div class="text-sm">$<?php echo number_format($investment['amount'], 2); ?></div>
                      <div class="text-xs text-gray-400">Invested: <?php echo date('M d, Y', strtotime($investment['start_date'])); ?></div>
                    </div>

                    <div>
                      <div class="text-xs text-gray-400">Return</div>
                      <div class="text-sm">$<?php echo number_format($investment['expected_profit'], 2); ?></div>
                      <div class="text-xs text-green-500">4.5% profit</div>
                    </div>

                    <div>
                      <div class="text-xs text-gray-400">Next Payout</div>
                      <div class="text-sm">$<?php echo number_format($investment['total_return'], 2); ?></div>
                      <div class="text-xs text-yellow-500">
                        <?php if ($remaining > 0): ?>
                          in <?php echo $hours_remaining; ?>h <?php echo $minutes_remaining; ?>m
                        <?php else: ?>
                          processing...
                        <?php endif; ?>
                      </div>
                    </div>

                    <div>
                      <div class="text-xs text-gray-400">Progress</div>
                      <div class="flex items-center mt-1">
                        <div class="w-16 bg-gray-700 rounded-full h-2 mr-2">
                          <div class="bg-yellow-500 h-2 rounded-full" style="width: <?php echo $progress; ?>%"></div>
                        </div>
                        <span class="text-xs text-gray-400"><?php echo round($progress); ?>%</span>
                      </div>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Divider with Icon -->
      <div class="flex items-center my-8">
        <div class="flex-grow h-px bg-gray-300"></div>
        <div class="px-4">
          <!-- You can use any icon here - this example uses a basic SVG star -->
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
          </svg>
        </div>
        <div class="flex-grow h-px bg-gray-300"></div>
      </div>
      <?php include 'includes/games.php' ?>
      <!-- Divider with Icon -->
      <div class="flex items-center my-8">
        <div class="flex-grow h-px bg-gray-300"></div>
        <div class="px-4">
          <!-- You can use any icon here - this example uses a basic SVG star -->
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
          </svg>
        </div>
        <div class="flex-grow h-px bg-gray-300"></div>
      </div>
      <?php include 'includes/leaderboard.php' ?>
      <!-- Divider with Icon -->
      <div class="flex items-center my-8">
        <div class="flex-grow h-px bg-gray-300"></div>
        <div class="px-4">
          <!-- You can use any icon here - this example uses a basic SVG star -->
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
          </svg>
        </div>
        <div class="flex-grow h-px bg-gray-300"></div>
      </div>
      <!-- Payment Methods Section -->
      <div class="mt-12">
        <div class="flex justify-between items-center mb-6">
          <h2 class="text-xl font-bold">Payment Methods</h2>
          <button class="text-sm bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition duration-300 flex items-center"
            onclick="window.location.href='payment-methods.php'">
            <i class="fas fa-plus mr-2"></i> Add New
          </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <?php if (empty($payment_methods)): ?>
            <!-- No payment methods message -->
            <div class="col-span-full bg-gray-800 rounded-lg p-6 text-center border border-gray-700">
              <i class="fas fa-credit-card text-4xl text-gray-500 mb-4"></i>
              <p class="text-gray-400">You haven't added any payment methods yet.</p>
              <button onclick="window.location.href='payment-methods.php'"
                class="mt-4 text-yellow-500 hover:text-yellow-400 transition duration-300">
                <i class="fas fa-plus mr-1"></i> Add a payment method
              </button>
            </div>
          <?php else: ?>
            <?php foreach ($payment_methods as $method): ?>
              <!-- Payment Method Card -->
              <div class="bg-gradient-to-r from-gray-800 to-gray-900 rounded-lg p-5 border border-gray-700 hover:border-yellow-500 transition duration-300 card-hover relative">
                <div class="absolute top-4 right-4">
                  <div class="dropdown inline-block relative">
                    <button class="text-gray-400 hover:text-white">
                      <i class="fas fa-ellipsis-v"></i>
                    </button>
                    <div class="dropdown-content hidden absolute right-0 mt-2 w-36 bg-gray-800 rounded-lg shadow-lg z-10 py-2 border border-gray-700">
                      <a href="edit-payment.php" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-700">Edit</a>
                      <?php if (!$method['is_default']): ?>
                        <form method="post" action="">
                          <input type="hidden" name="payment_id" value="<?php echo $method['id']; ?>">
                          <button type="submit" name="set_default" class="w-full text-left px-4 py-2 text-sm text-gray-300 hover:bg-gray-700">Set as default</button>
                        </form>
                      <?php endif; ?>
                      <form method="post" action="delete-payment.php?id=<?php echo $method['id']; ?>" onsubmit="return confirm('Are you sure you want to delete this payment method?');">
                        <input type="hidden" name="payment_id" value="<?php echo $method['id']; ?>">
                        <button type="submit" name="delete_payment" class="w-full text-left px-4 py-2 text-sm text-red-400 hover:bg-gray-700">Remove</button>
                      </form>
                    </div>
                  </div>
                </div>

                <div class="flex items-center mb-4">
                  <div class="h-10 w-10 rounded-full flex items-center justify-center mr-3">
                    <i class="<?php echo getPaymentIcon($method['payment_type']); ?> text-2xl"></i>
                  </div>
                  <div>
                    <h4 class="text-sm font-bold">
                      <?php echo htmlspecialchars($method['payment_type']); ?>
                      <?php echo $method['payment_type'] != $method['account_name'] ? '- ' . htmlspecialchars($method['account_name']) : ''; ?>
                    </h4>
                    <p class="text-gray-400 text-xs">
                      <?php
                      if (strtolower($method['payment_type']) == 'bank account' || strtolower($method['payment_type']) == 'bank') {
                        echo htmlspecialchars($method['account_name']) . ' - Ending in ' . formatAccountNumber($method['account_number']);
                      } else {
                        echo 'Ending in ' . formatAccountNumber($method['account_number']);
                      }
                      ?>
                    </p>
                  </div>
                </div>

                <div class="flex items-center justify-between">
                  <?php if ($method['is_default']): ?>
                    <span class="bg-green-900 text-green-400 px-2 py-1 text-xs rounded-full">Default</span>
                  <?php else: ?>
                    <span class="bg-gray-700 text-gray-300 px-2 py-1 text-xs rounded-full">Connected</span>
                  <?php endif; ?>
                  <span class="text-xs text-gray-400">
                    Added: <?php echo date('M d, Y', strtotime($method['created_at'])); ?>
                  </span>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
      <!-- Divider with Icon -->
      <div class="flex items-center my-8">
        <div class="flex-grow h-px bg-gray-300"></div>
        <div class="px-4">
          <!-- You can use any icon here - this example uses a basic SVG star -->
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
          </svg>
        </div>
        <div class="flex-grow h-px bg-gray-300"></div>
      </div>
      <!-- Recent Transactions Section -->
      <div class="mt-12">
        <div class="flex justify-between items-center mb-6">
          <h2 class="text-xl font-bold">Recent Transactions</h2>
          <a href="#" class="text-yellow-500 hover:text-yellow-400 transition duration-300 text-sm flex items-center">
            View All <i class="fas fa-arrow-right ml-2"></i>
          </a>
        </div>

        <!-- Desktop View (md and above) -->
        <div class="hidden md:block bg-gray-800 rounded-lg border border-gray-700 overflow-hidden">
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-700">
              <thead>
                <tr>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Transaction</th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Date</th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Amount</th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Status</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-700">
                <?php if (empty($recent_transactions)): ?>
                  <tr>
                    <td colspan="4" class="px-6 py-4 text-center text-gray-400">
                      No transactions found. Start investing to see your transactions!
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($recent_transactions as $transaction): ?>
                    <?php
                    // Set icon and color based on transaction type
                    $icon_class = '';
                    $bg_color = '';
                    $text_color = '';
                    $sign = '';

                    switch ($transaction['transaction_type']) {
                      case 'deposit':
                        $icon_class = 'fas fa-arrow-down';
                        $bg_color = 'bg-green-100';
                        $text_color = 'text-green-600';
                        $amount_color = 'text-green-500';
                        $sign = '+';
                        break;
                      case 'withdrawal':
                        $icon_class = 'fas fa-arrow-up';
                        $bg_color = 'bg-red-100';
                        $text_color = 'text-red-600';
                        $amount_color = 'text-red-500';
                        $sign = '-';
                        break;
                      case 'investment':
                        $icon_class = 'fas fa-ticket-alt';
                        $bg_color = 'bg-blue-100';
                        $text_color = 'text-blue-600';
                        $amount_color = 'text-red-500';
                        $sign = '-';
                        break;
                      case 'profit':
                        $icon_class = 'fas fa-coins';
                        $bg_color = 'bg-yellow-100';
                        $text_color = 'text-yellow-600';
                        $amount_color = 'text-green-500';
                        $sign = '+';
                        break;
                      default:
                        $icon_class = 'fas fa-exchange-alt';
                        $bg_color = 'bg-gray-100';
                        $text_color = 'text-gray-600';
                        $amount_color = 'text-gray-500';
                        $sign = '';
                    }
                    ?>
                    <tr class="group hover:bg-gray-750 transition duration-150">
                      <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                          <div class="h-8 w-8 rounded-full <?php echo $bg_color; ?> flex items-center justify-center <?php echo $text_color; ?>">
                            <i class="<?php echo $icon_class; ?>"></i>
                          </div>
                          <div class="ml-4">
                            <div class="text-sm font-medium"><?php echo ucfirst($transaction['transaction_type']); ?></div>
                            <div class="text-xs text-gray-400"><?php echo htmlspecialchars($transaction['description']); ?></div>
                          </div>
                        </div>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm"><?php echo date('M d, Y', strtotime($transaction['created_at'])); ?></td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm <?php echo $amount_color; ?>"><?php echo $sign; ?>$<?php echo number_format($transaction['amount'], 2); ?></td>
                      <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 py-1 text-xs rounded-full bg-green-900 text-green-400"><?php echo ucfirst($transaction['status']); ?></span>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Mobile View (sm and below) -->
        <div class="md:hidden">
          <?php if (empty($recent_transactions)): ?>
            <div class="bg-gray-800 rounded-lg border border-gray-700 p-4 text-center text-gray-400">
              No transactions found. Start investing to see your transactions!
            </div>
          <?php else: ?>
            <div class="space-y-3">
              <?php foreach ($recent_transactions as $transaction): ?>
                <?php
                // Set icon and color based on transaction type
                $icon_class = '';
                $bg_color = '';
                $text_color = '';
                $sign = '';

                switch ($transaction['transaction_type']) {
                  case 'deposit':
                    $icon_class = 'fas fa-arrow-down';
                    $bg_color = 'bg-green-100';
                    $text_color = 'text-green-600';
                    $amount_color = 'text-green-500';
                    $sign = '+';
                    break;
                  case 'withdrawal':
                    $icon_class = 'fas fa-arrow-up';
                    $bg_color = 'bg-red-100';
                    $text_color = 'text-red-600';
                    $amount_color = 'text-red-500';
                    $sign = '-';
                    break;
                  case 'investment':
                    $icon_class = 'fas fa-ticket-alt';
                    $bg_color = 'bg-blue-100';
                    $text_color = 'text-blue-600';
                    $amount_color = 'text-red-500';
                    $sign = '-';
                    break;
                  case 'profit':
                    $icon_class = 'fas fa-coins';
                    $bg_color = 'bg-yellow-100';
                    $text_color = 'text-yellow-600';
                    $amount_color = 'text-green-500';
                    $sign = '+';
                    break;
                  default:
                    $icon_class = 'fas fa-exchange-alt';
                    $bg_color = 'bg-gray-100';
                    $text_color = 'text-gray-600';
                    $amount_color = 'text-gray-500';
                    $sign = '';
                }
                ?>
                <div class="bg-gray-800 rounded-lg border border-gray-700 p-4">
                  <div class="flex items-center justify-between">
                    <div class="flex items-center">
                      <div class="h-8 w-8 rounded-full <?php echo $bg_color; ?> flex items-center justify-center <?php echo $text_color; ?>">
                        <i class="<?php echo $icon_class; ?>"></i>
                      </div>
                      <div class="ml-3">
                        <div class="text-sm font-medium"><?php echo ucfirst($transaction['transaction_type']); ?></div>
                        <div class="text-xs text-gray-400"><?php echo htmlspecialchars($transaction['description']); ?></div>
                      </div>
                    </div>
                    <div class="text-right">
                      <div class="text-sm font-medium <?php echo $amount_color; ?>"><?php echo $sign; ?>$<?php echo number_format($transaction['amount'], 2); ?></div>
                      <div class="text-xs text-gray-400"><?php echo date('M d, Y', strtotime($transaction['created_at'])); ?></div>
                    </div>
                  </div>
                  <div class="mt-3 flex justify-between items-center">
                    <span class="px-2 py-1 text-xs rounded-full bg-green-900 text-green-400"><?php echo ucfirst($transaction['status']); ?></span>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </main>
  <!-- Divider with Icon -->
  <div class="flex items-center my-8">
    <div class="flex-grow h-px bg-gray-300"></div>
    <div class="px-4">
      <!-- You can use any icon here - this example uses a basic SVG star -->
      <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
      </svg>
    </div>
    <div class="flex-grow h-px bg-gray-300"></div>
  </div>
  <?php include 'includes/footer.php'; ?>
  <?php include 'includes/js-links.php'; ?>

  <!-- Add Deposit Modal (Hidden by default) -->
  <div id="depositModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-gray-800 p-6 rounded-lg shadow-lg w-full max-w-md">
      <div class="flex justify-between items-center mb-4">
        <h3 class="text-xl font-bold">Add Funds</h3>
        <button onclick="document.getElementById('depositModal').classList.add('hidden')" class="text-gray-400 hover:text-white">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <form action="deposit.php" method="post">
        <button type="submit" class="w-full bg-gradient-to-r from-yellow-500 to-yellow-600 hover:from-yellow-600 hover:to-yellow-700 text-black font-bold py-3 px-4 rounded-lg transition duration-300">
          Add Funds
        </button>
      </form>
    </div>
  </div>

  <script>
    // Show loading animation when page loads
    document.addEventListener('DOMContentLoaded', function() {
      // Hide preloader after 1.5 seconds
      setTimeout(function() {
        document.querySelector('.preloader').style.display = 'none';
        document.getElementById('main-content').style.display = 'block';
      }, 1500);

      // Show/hide dropdowns
      const dropdownButtons = document.querySelectorAll('.dropdown button');

      dropdownButtons.forEach(button => {
        button.addEventListener('click', function(e) {
          e.stopPropagation();
          const content = this.nextElementSibling;

          // Close all other dropdowns
          document.querySelectorAll('.dropdown-content').forEach(dropdown => {
            if (dropdown !== content) {
              dropdown.classList.add('hidden');
            }
          });

          // Toggle current dropdown
          content.classList.toggle('hidden');
        });
      });

      // Close dropdowns when clicking elsewhere
      document.addEventListener('click', function() {
        document.querySelectorAll('.dropdown-content').forEach(dropdown => {
          dropdown.classList.add('hidden');
        });
      });
    });

    function copyToClipboard(text) {
      // Create a temporary input
      const input = document.createElement('input');
      input.setAttribute('value', text);
      document.body.appendChild(input);

      // Select and copy
      input.select();
      document.execCommand('copy');

      // Remove temporary input
      document.body.removeChild(input);

      // Show copied notification (you can replace with a better UI)
      alert('Wallet ID copied to clipboard: ' + text);
    }

    function activatePlan(planType, amount) {
      // Check if user has sufficient balance first
      if (confirm("Are you sure you want to invest $" + amount + " in the " + planType + " plan?")) {
        // Show loading indicator
        document.getElementById('loading-indicator').classList.remove('hidden');

        // Send AJAX request to process the investment
        fetch(window.location.href, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded',
              'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'plan_type=' + encodeURIComponent(planType) +
              '&amount=' + encodeURIComponent(amount)
          })
          .then(response => {
            if (!response.ok) {
              throw new Error('Network response was not ok');
            }
            return response.json();
          })
          .then(data => {
            // Hide loading indicator
            document.getElementById('loading-indicator').classList.add('hidden');

            if (data.success) {
              alert("Investment successful! You will receive your profit in 24 hours!");
              window.location.reload();
            } else {
              alert("Error: " + data.message);
            }
          })
          .catch(error => {
            // Hide loading indicator
            document.getElementById('loading-indicator').classList.add('hidden');

            console.error('Error:', error);
            alert("An error occurred. Please try again.");
          });
      }
    }
  </script>
  <!-- Add this script to your page for smooth scrolling on mobile devices -->
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Only apply smooth scrolling on mobile devices (screen width less than 768px)
      function initSmoothScroll() {
        if (window.innerWidth < 768) {
          // Apply smooth scroll behavior to the HTML element
          document.documentElement.style.scrollBehavior = 'smooth';

          // Find all anchor links that point to IDs on the page
          const anchorLinks = document.querySelectorAll('a[href^="#"]');

          anchorLinks.forEach(link => {
            link.addEventListener('click', function(e) {
              // Prevent default anchor behavior
              e.preventDefault();

              // Get the target element
              const targetId = this.getAttribute('href');
              if (targetId === '#') return; // Skip if href is just "#"

              const targetElement = document.querySelector(targetId);
              if (!targetElement) return; // Skip if target element doesn't exist

              // Calculate scroll position with offset (optional - adjust the 60px as needed)
              const offsetTop = targetElement.getBoundingClientRect().top + window.pageYOffset - 60;

              // Smooth scroll to the target
              window.scrollTo({
                top: offsetTop,
                behavior: 'smooth'
              });
            });
          });
        }
      }

      // Initialize on page load
      initSmoothScroll();

      // Reinitialize when window is resized
      window.addEventListener('resize', function() {
        initSmoothScroll();
      });
    });
  </script>
</body>

</html>