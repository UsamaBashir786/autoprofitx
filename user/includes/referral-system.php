<?php
// config.php - Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'autoproftx');

// Helper functions
function connect_db()
{
  $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
  if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
  }
  return $conn;
}

function get_user_referrals($user_id)
{
  $db = connect_db();
  $stmt = $db->prepare("
        SELECT u.id, u.full_name, u.email, u.registration_date, 
               (SELECT SUM(amount) FROM investments WHERE user_id = u.id) as total_investments,
               (SELECT COUNT(*) FROM users WHERE referred_by = u.id) as their_referrals
        FROM users u 
        WHERE u.referred_by = ?
        ORDER BY u.registration_date DESC
    ");
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $result = $stmt->get_result();
  $referrals = [];
  while ($row = $result->fetch_assoc()) {
    $referrals[] = $row;
  }
  $stmt->close();
  $db->close();
  return $referrals;
}

function get_referral_earnings($user_id)
{
  $db = connect_db();
  $stmt = $db->prepare("
        SELECT rc.*, 
               u.full_name as referred_name, 
               ip.name as plan_name
        FROM referral_commissions rc
        JOIN users u ON rc.referred_id = u.id
        JOIN investments i ON rc.investment_id = i.id
        JOIN investment_plans ip ON i.plan_id = ip.id
        WHERE rc.referrer_id = ?
        ORDER BY rc.created_at DESC
    ");
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $result = $stmt->get_result();
  $earnings = [];
  while ($row = $result->fetch_assoc()) {
    $earnings[] = $row;
  }
  $stmt->close();
  $db->close();
  return $earnings;
}

function get_total_referral_earnings($user_id)
{
  $db = connect_db();
  $stmt = $db->prepare("
        SELECT COALESCE(SUM(commission_amount), 0) as total
        FROM referral_commissions
        WHERE referrer_id = ? AND status = 'paid'
    ");
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $result = $stmt->get_result();
  $row = $result->fetch_assoc();
  $stmt->close();
  $db->close();
  return $row['total'];
}

function process_referral_commission($investment_id)
{
  $db = connect_db();

  // Start transaction
  $db->begin_transaction();

  try {
    // Get investment details
    $stmt = $db->prepare("
            SELECT i.id, i.user_id, i.plan_id, i.amount, i.plan_type, 
                   u.referred_by, p.referral_commission_rate
            FROM investments i
            JOIN users u ON i.user_id = u.id
            JOIN investment_plans p ON i.plan_id = p.id
            WHERE i.id = ? AND i.referral_commission_paid = 0
        ");
    $stmt->bind_param("i", $investment_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
      // Investment not found or commission already paid
      $db->rollback();
      return false;
    }

    $investment = $result->fetch_assoc();

    // Check if user has a referrer
    if (!$investment['referred_by']) {
      // No referrer to pay commission to
      $db->rollback();
      return false;
    }

    // Calculate commission amount
    $commissionRate = $investment['referral_commission_rate'];
    $commissionAmount = ($investment['amount'] * $commissionRate) / 5;

    // Insert commission record
    $stmt = $db->prepare("
            INSERT INTO referral_commissions 
            (investment_id, referrer_id, referred_id, investment_amount, commission_rate, commission_amount, status)
            VALUES (?, ?, ?, ?, ?, ?, 'paid')
        ");

    $stmt->bind_param(
      "iiiddd",
      $investment['id'],
      $investment['referred_by'],
      $investment['user_id'],
      $investment['amount'],
      $commissionRate,
      $commissionAmount
    );

    $stmt->execute();

    // Update referrer's wallet
    $stmt = $db->prepare("
            UPDATE wallets 
            SET balance = balance + ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE user_id = ?
        ");

    $stmt->bind_param("di", $commissionAmount, $investment['referred_by']);
    $stmt->execute();

    // Get referred user's name
    $nameStmt = $db->prepare("SELECT full_name FROM users WHERE id = ?");
    $nameStmt->bind_param("i", $investment['user_id']);
    $nameStmt->execute();
    $nameResult = $nameStmt->get_result();
    $referredUser = $nameResult->fetch_assoc();

    // Record transaction
    $referenceId = 'REF-COMM-' . $investment_id;
    $description = "Referral commission from {$referredUser['full_name']} - {$investment['plan_type']} plan";

    $stmt = $db->prepare("
            INSERT INTO transactions
            (user_id, transaction_type, amount, status, description, reference_id)
            VALUES (?, 'deposit', ?, 'completed', ?, ?)
        ");

    $stmt->bind_param(
      "idss",
      $investment['referred_by'],
      $commissionAmount,
      $description,
      $referenceId
    );

    $stmt->execute();

    // Mark commission as paid
    $stmt = $db->prepare("
            UPDATE investments 
            SET referral_commission_paid = 1
            WHERE id = ?
        ");

    $stmt->bind_param("i", $investment_id);
    $stmt->execute();

    // Commit the transaction
    $db->commit();
    return true;
  } catch (Exception $e) {
    // An error occurred, rollback the transaction
    $db->rollback();
    return false;
  }
}

// Function to get user referral tree (direct referrals)
function get_referral_tree($user_id, $levels = 1, $current_level = 0)
{
  if ($current_level >= $levels) {
    return [];
  }

  $db = connect_db();
  $tree = [];

  $stmt = $db->prepare("
        SELECT u.id, u.full_name, u.email, u.referral_code, u.registration_date,
               (SELECT SUM(amount) FROM investments WHERE user_id = u.id) as total_investments,
               (SELECT COUNT(*) FROM users WHERE referred_by = u.id) as referral_count
        FROM users u
        WHERE u.referred_by = ?
        ORDER BY u.registration_date DESC
    ");

  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $result = $stmt->get_result();

  while ($row = $result->fetch_assoc()) {
    // Get children recursively
    $row['children'] = get_referral_tree($row['id'], $levels, $current_level + 1);
    $tree[] = $row;
  }

  $stmt->close();
  $db->close();

  return $tree;
}

// Function to get investment plans
function get_investment_plans()
{
  $db = connect_db();
  $query = "SELECT * FROM investment_plans WHERE is_active = 1 ORDER BY min_amount ASC";
  $result = $db->query($query);

  $plans = [];
  while ($row = $result->fetch_assoc()) {
    $plans[] = $row;
  }

  $db->close();
  return $plans;
}
