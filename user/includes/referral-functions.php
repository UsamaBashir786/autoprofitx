<?php
// File: includes/referral-functions.php

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

  // Start transaction
  $conn->begin_transaction();

  try {
    // Set bonus amount FIRST before using it
    $bonus_amount = 5.00; // 5 bonus

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
      $create_wallet = "INSERT INTO wallets (user_id, balance) VALUES (?, ?)";
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

    // Record transaction for the referral bonus
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
    $update_referral = "UPDATE referrals SET status = 'paid', paid_at = NOW() WHERE referrer_id = ? AND referred_id = ?";
    $stmt = $conn->prepare($update_referral);
    $stmt->bind_param("ii", $referredBy, $newUserId);
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

// Function to process referral commission when a referred user makes an investment
function processInvestmentReferralCommission($investmentId)
{
  global $conn;

  // Start transaction
  $conn->begin_transaction();

  try {
    // Get investment details
    $investment_query = "SELECT i.*, u.referred_by 
                            FROM investments i 
                            JOIN users u ON i.user_id = u.id 
                            WHERE i.id = ? AND i.referral_commission_paid = 0";
    $stmt = $conn->prepare($investment_query);
    $stmt->bind_param("i", $investmentId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
      // Investment not found or commission already paid
      return false;
    }

    $investment = $result->fetch_assoc();
    $referrerId = $investment['referred_by'];

    // If user was not referred, exit
    if (!$referrerId) {
      return false;
    }

    // Get investment plan details to determine commission rate
    // For Professional plan, get rate from investment_plans table
    if ($investment['plan_type'] === 'Basic' || $investment['plan_type'] === 'Premium' || $investment['plan_type'] === 'Professional') {
      $plan_query = "SELECT referral_commission_rate FROM investment_plans WHERE name = ?";
      $stmt = $conn->prepare($plan_query);
      $stmt->bind_param("s", $investment['plan_type']);
      $stmt->execute();
      $plan_result = $stmt->get_result();

      if ($plan_result->num_rows === 0) {
        // Plan not found
        $conn->rollback();
        return false;
      }

      $plan = $plan_result->fetch_assoc();
      $commission_rate = $plan['referral_commission_rate'];
    } else {
      // Default commission rate if plan type not found
      $commission_rate = 10.00;
    }

    // Calculate commission amount
    $commission_amount = ($investment['amount'] * $commission_rate) / 5;

    // Add commission to referrer's wallet
    $update_wallet = "UPDATE wallets SET balance = balance + ? WHERE user_id = ?";
    $stmt = $conn->prepare($update_wallet);
    $stmt->bind_param("di", $commission_amount, $referrerId);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
      // Create wallet for referrer if it doesn't exist
      $create_wallet = "INSERT INTO wallets (user_id, balance) VALUES (?, ?)";
      $stmt = $conn->prepare($create_wallet);
      $stmt->bind_param("id", $referrerId, $commission_amount);
      $stmt->execute();
    }

    // Record referral commission in referral_commissions table
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
      $investmentId,
      $referrerId,
      $investment['user_id'],
      $investment['amount'],
      $commission_rate,
      $commission_amount
    );
    $stmt->execute();

    // Record transaction for the referral commission
    $transaction_query = "INSERT INTO transactions (
                user_id, 
                transaction_type, 
                amount, 
                status, 
                description,
                reference_id
            ) VALUES (?, 'deposit', ?, 'completed', ?, ?)";

    $description = "Investment Referral Commission";
    $reference_id = "INVREF-" . $investmentId;

    $stmt = $conn->prepare($transaction_query);
    $stmt->bind_param("idss", $referrerId, $commission_amount, $description, $reference_id);
    $stmt->execute();

    // Update investment to mark commission as paid
    $update_investment = "UPDATE investments SET referral_commission_paid = 1 WHERE id = ?";
    $stmt = $conn->prepare($update_investment);
    $stmt->bind_param("i", $investmentId);
    $stmt->execute();

    // Commit transaction
    $conn->commit();

    return true;
  } catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    error_log("Error processing investment referral commission: " . $e->getMessage());
    return false;
  }
}

// Function to get a user's total investment referral earnings
function getTotalInvestmentReferralEarnings($userId)
{
  global $conn;

  $query = "SELECT SUM(commission_amount) as total FROM referral_commissions WHERE referrer_id = ? AND status = 'paid'";
  $stmt = $conn->prepare($query);
  $stmt->bind_param("i", $userId);
  $stmt->execute();
  $result = $stmt->get_result();
  $data = $result->fetch_assoc();

  return $data['total'] ?? 0;
}

// Function to get all investment commission earnings for a user with details
function getInvestmentReferralCommissions($userId)
{
  global $conn;

  $commissions = [];

  $query = "SELECT rc.*, u.full_name, i.investment_id as inv_number, i.plan_type 
              FROM referral_commissions rc 
              JOIN users u ON rc.referred_id = u.id 
              JOIN investments i ON rc.investment_id = i.id 
              WHERE rc.referrer_id = ? AND rc.status = 'paid' 
              ORDER BY rc.created_at DESC";

  $stmt = $conn->prepare($query);
  $stmt->bind_param("i", $userId);
  $stmt->execute();
  $result = $stmt->get_result();

  while ($row = $result->fetch_assoc()) {
    $commissions[] = $row;
  }

  return $commissions;
}

// Function to get combined total of all referral earnings (signup + investment)
function getTotalCombinedReferralEarnings($userId)
{
  $signupEarnings = getTotalReferralEarnings($userId);
  $investmentEarnings = getTotalInvestmentReferralEarnings($userId);

  return $signupEarnings + $investmentEarnings;
}
