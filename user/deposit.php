<?php
// Start session
session_start();
include '../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
  header("Location: ../login.php");
  exit();
}

// Initialize variables
$user_id = $_SESSION['user_id'];
$success_message = "";
$error_message = "";
$amount = "";
$payment_method_id = "";
$admin_payment_id = "";
$transaction_id = "";
$notes = "";

// Create required tables if they don't exist
// First deposits table
$check_first_deposits_table = "SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'first_deposits'";
$first_deposits_result = $conn->query($check_first_deposits_table);
$first_deposits_exists = ($first_deposits_result && $first_deposits_result->fetch_assoc()['count'] > 0);

if (!$first_deposits_exists) {
  $create_first_deposits = "CREATE TABLE IF NOT EXISTS `first_deposits` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `first_deposit_amount` DECIMAL(15,2) NOT NULL,
    `first_withdrawal_made` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `user_id` (`user_id`)
  ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;";

  $conn->query($create_first_deposits);
}

// Deposit bonus tiers table
$check_bonus_tiers_table = "SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'deposit_bonus_tiers'";
$bonus_tiers_result = $conn->query($check_bonus_tiers_table);
$bonus_tiers_exists = ($bonus_tiers_result && $bonus_tiers_result->fetch_assoc()['count'] > 0);

if (!$bonus_tiers_exists) {
  $create_bonus_tiers = "CREATE TABLE IF NOT EXISTS `deposit_bonus_tiers` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `min_amount` DECIMAL(15,2) NOT NULL,
    `max_amount` DECIMAL(15,2) DEFAULT NULL,
    `bonus_amount` DECIMAL(15,2) NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
  ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;";

  $conn->query($create_bonus_tiers);

  // Insert default bonus tiers
  $insert_tiers = "INSERT INTO `deposit_bonus_tiers` (`min_amount`, `max_amount`, `bonus_amount`, `is_active`) VALUES
    (500.00, 999.99, 100.00, 1),
    (1000.00, NULL, 200.00, 1)";

  $conn->query($insert_tiers);
}

// Withdrawal daily limits table
$check_withdrawal_limits_table = "SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'withdrawal_daily_limits'";
$withdrawal_limits_result = $conn->query($check_withdrawal_limits_table);
$withdrawal_limits_exists = ($withdrawal_limits_result && $withdrawal_limits_result->fetch_assoc()['count'] > 0);

if (!$withdrawal_limits_exists) {
  $create_withdrawal_limits = "CREATE TABLE IF NOT EXISTS `withdrawal_daily_limits` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `withdrawal_date` DATE NOT NULL,
    `request_count` INT NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `user_date_limit` (`user_id`, `withdrawal_date`)
  ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;";

  $conn->query($create_withdrawal_limits);
}

// Add min_deposit_amount to system_settings if it doesn't exist
$check_min_deposit_setting = "SELECT COUNT(*) as count FROM system_settings WHERE setting_key = 'min_deposit_amount'";
$min_deposit_result = $conn->query($check_min_deposit_setting);
$min_deposit_exists = ($min_deposit_result && $min_deposit_result->fetch_assoc()['count'] > 0);

if (!$min_deposit_exists) {
  $insert_min_deposit = "INSERT INTO system_settings (setting_key, setting_value) VALUES ('min_deposit_amount', '30')";
  $conn->query($insert_min_deposit);
}

// Get minimum deposit amount from settings
$min_deposit_query = "SELECT setting_value FROM system_settings WHERE setting_key = 'min_deposit_amount'";
$min_deposit_result = $conn->query($min_deposit_query);
$min_deposit_amount = 30; // Default

if ($min_deposit_result && $min_deposit_result->num_rows > 0) {
  $min_deposit_amount = $min_deposit_result->fetch_assoc()['setting_value'];
}

// Create admin_payment_methods table if it doesn't exist and add sample data
$check_admin_table_sql = "SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'admin_payment_methods'";
$check_admin_result = $conn->query($check_admin_table_sql);
$admin_table_exists = ($check_admin_result && $check_admin_result->fetch_assoc()['count'] > 0);

if (!$admin_table_exists) {
  $create_admin_table_sql = "CREATE TABLE admin_payment_methods (
        id INT AUTO_INCREMENT PRIMARY KEY,
        payment_type ENUM('binance') NOT NULL,
        account_name VARCHAR(100) NOT NULL,
        account_number VARCHAR(100) NOT NULL,
        additional_info TEXT,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";

  $conn->query($create_admin_table_sql);

  // Add sample admin payment method (Binance TRC20)
  $sample_methods = [
    ['binance', 'Admin Binance', 'TPD4HY9QsWrK6H2qS7iJbh3TSEYWuMtLRQ', 'Send to this Binance TRC20 address. Please include your username as reference.', 1]
  ];

  $insert_sample_sql = "INSERT INTO admin_payment_methods (payment_type, account_name, account_number, additional_info, is_active) VALUES (?, ?, ?, ?, ?)";
  $sample_stmt = $conn->prepare($insert_sample_sql);

  foreach ($sample_methods as $method) {
    $sample_stmt->bind_param("ssssi", $method[0], $method[1], $method[2], $method[3], $method[4]);
    $sample_stmt->execute();
  }

  $sample_stmt->close();
}

// Create deposits table if it doesn't exist
$check_table_sql = "SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'deposits'";
$check_result = $conn->query($check_table_sql);
$table_exists = ($check_result && $check_result->fetch_assoc()['count'] > 0);

if (!$table_exists) {
  $create_table_sql = "CREATE TABLE deposits (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        amount DECIMAL(15,2) NOT NULL,
        payment_method_id INT NOT NULL,
        admin_payment_id INT NOT NULL,
        transaction_id VARCHAR(255),
        proof_file VARCHAR(255) NOT NULL,
        notes TEXT,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        processed_at TIMESTAMP NULL,
        admin_notes TEXT,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";

  $conn->query($create_table_sql);
}

// Fetch user's payment methods
$payment_methods = [];
$sql = "SELECT * FROM payment_methods WHERE user_id = ? ORDER BY is_default DESC, created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
  $payment_methods[] = $row;
}
$stmt->close();

// Fetch admin payment methods
$admin_payment_methods = [];
$admin_sql = "SELECT * FROM admin_payment_methods WHERE is_active = 1 ORDER BY payment_type";
$admin_result = $conn->query($admin_sql);

if ($admin_result) {
  while ($row = $admin_result->fetch_assoc()) {
    $admin_payment_methods[] = $row;
  }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit'])) {
  // Get form data with proper validation
  $amount = isset($_POST['amount']) ? mysqli_real_escape_string($conn, $_POST['amount']) : '';
  $payment_method_id = isset($_POST['payment_method_id']) ? mysqli_real_escape_string($conn, $_POST['payment_method_id']) : '';
  $admin_payment_id = isset($_POST['admin_payment_id']) ? mysqli_real_escape_string($conn, $_POST['admin_payment_id']) : '';
  $transaction_id = isset($_POST['transaction_id']) ? mysqli_real_escape_string($conn, $_POST['transaction_id']) : '';
  $notes = isset($_POST['notes']) ? mysqli_real_escape_string($conn, $_POST['notes']) : '';

  // Validate input
  $valid = true;

  // Check minimum deposit amount
  if (empty($amount) || !is_numeric($amount) || $amount <= 0) {
    $error_message = "Please enter a valid amount";
    $valid = false;
  } elseif ($amount < $min_deposit_amount) {
    $error_message = "Minimum deposit amount is $" . $min_deposit_amount;
    $valid = false;
  } elseif (empty($payment_method_id)) {
    $error_message = "Please select your payment method";
    $valid = false;
  } elseif (empty($admin_payment_id)) {
    $error_message = "Please select where you sent the funds";
    $valid = false;
  }

  // File upload validation
  $proof_file = "";
  if ($valid && isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] == 0) {
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];
    $file_name = $_FILES['payment_proof']['name'];
    $file_size = $_FILES['payment_proof']['size'];
    $file_tmp = $_FILES['payment_proof']['tmp_name'];
    $file_type = $_FILES['payment_proof']['type'];

    $file_parts = explode('.', $file_name);
    $file_ext = strtolower(end($file_parts));

    // Check file extension
    if (!in_array($file_ext, $allowed_extensions)) {
      $error_message = "Only JPG, JPEG, PNG, and PDF files are allowed";
      $valid = false;
    }

    // Check file size (5MB max)
    if ($file_size > 5242880) {
      $error_message = "File size must be less than 5MB";
      $valid = false;
    }

    if ($valid) {
      // Create unique filename
      $new_file_name = 'proof_' . $user_id . '_' . time() . '.' . $file_ext;
      $upload_dir = '../uploads/payment_proofs/';

      // Create directory if it doesn't exist
      if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
      }

      $upload_path = $upload_dir . $new_file_name;

      // Move uploaded file
      if (move_uploaded_file($file_tmp, $upload_path)) {
        $proof_file = $new_file_name;
      } else {
        $error_message = "Failed to upload file. Please try again.";
        $valid = false;
      }
    }
  } else {
    $error_message = "Payment proof is required";
    $valid = false;
  }

  if ($valid) {
    // Check if deposit already exists with this transaction ID
    if (!empty($transaction_id)) {
      $check_sql = "SELECT id FROM deposits WHERE transaction_id = ?";
      $check_stmt = $conn->prepare($check_sql);
      $check_stmt->bind_param("s", $transaction_id);
      $check_stmt->execute();
      $check_result = $check_stmt->get_result();

      if ($check_result->num_rows > 0) {
        $error_message = "A deposit with this transaction ID already exists";
        $valid = false;
      }

      $check_stmt->close();
    }
  }

  if ($valid) {
    try {
      // Start transaction
      $conn->begin_transaction();

      // Insert deposit record
      $status = 'pending'; // Default status is pending

      $sql = "INSERT INTO deposits (user_id, amount, payment_method_id, admin_payment_id, transaction_id, proof_file, notes, status, created_at) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";

      $stmt = $conn->prepare($sql);
      $stmt->bind_param("idsiisss", $user_id, $amount, $payment_method_id, $admin_payment_id, $transaction_id, $proof_file, $notes, $status);
      $stmt->execute();
      $deposit_id = $stmt->insert_id;

      // Add transaction record
      $reference = "DEP-" . rand(10000, 99999);
      $transQuery = "INSERT INTO transactions (user_id, transaction_type, amount, status, description, reference_id) 
                    VALUES (?, 'deposit', ?, 'pending', 'Deposit Request', ?)";

      $stmt = $conn->prepare($transQuery);
      $stmt->bind_param("ids", $user_id, $amount, $reference);
      $stmt->execute();

      // Check if this is the user's first deposit and record it
      $check_first_deposit = "SELECT COUNT(*) AS count FROM first_deposits WHERE user_id = ?";
      $first_deposit_stmt = $conn->prepare($check_first_deposit);
      $first_deposit_stmt->bind_param("i", $user_id);
      $first_deposit_stmt->execute();
      $first_deposit_result = $first_deposit_stmt->get_result();
      $first_deposit_count = $first_deposit_result->fetch_assoc()['count'];

      if ($first_deposit_count == 0) {
        // Record as first deposit
        $insert_first_deposit = "INSERT INTO first_deposits (user_id, first_deposit_amount) VALUES (?, ?)";
        $first_deposit_insert_stmt = $conn->prepare($insert_first_deposit);
        $first_deposit_insert_stmt->bind_param("id", $user_id, $amount);
        $first_deposit_insert_stmt->execute();
      }

      $conn->commit();

      $success_message = "Deposit request submitted successfully! Our team will verify your payment and update your wallet balance.";
      // Clear form data
      $amount = $payment_method_id = $transaction_id = $notes = "";
    } catch (Exception $e) {
      $conn->rollback();
      $error_message = "Error submitting deposit request: " . $e->getMessage();
    }
  }
}

// Function to apply deposit bonuses (will be called when admin approves deposit)
function applyDepositBonus($conn, $userId, $depositAmount)
{
  // Check if any bonus tier applies
  $bonus_query = "SELECT bonus_amount FROM deposit_bonus_tiers 
                 WHERE ? >= min_amount 
                 AND (max_amount IS NULL OR ? <= max_amount)
                 AND is_active = 1
                 ORDER BY min_amount DESC
                 LIMIT 1";

  $bonus_stmt = $conn->prepare($bonus_query);
  $bonus_stmt->bind_param("dd", $depositAmount, $depositAmount);
  $bonus_stmt->execute();
  $bonus_result = $bonus_stmt->get_result();

  if ($bonus_result->num_rows > 0) {
    $bonus_amount = $bonus_result->fetch_assoc()['bonus_amount'];

    // Update user wallet
    $wallet_update = "UPDATE wallets SET balance = balance + ?, updated_at = NOW() WHERE user_id = ?";
    $wallet_stmt = $conn->prepare($wallet_update);
    $wallet_stmt->bind_param("di", $bonus_amount, $userId);
    $wallet_stmt->execute();

    // Record transaction
    $reference = "BONUS-" . rand(10000, 99999);
    $trans_query = "INSERT INTO transactions (
                    user_id, transaction_type, amount, status, description, reference_id
                  ) VALUES (?, 'deposit', ?, 'completed', ?, ?)";

    $description = "Bonus for deposit of $" . number_format($depositAmount, 2);

    $trans_stmt = $conn->prepare($trans_query);
    $trans_stmt->bind_param("idss", $userId, $bonus_amount, $description, $reference);
    $trans_stmt->execute();

    return $bonus_amount;
  }

  return 0;
}

// Fetch recent deposits
$recent_deposits = [];
$deposits_table_exists = false;

// Check if deposits table exists before querying
$check_deposits_table_sql = "SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'deposits'";
$check_deposits_result = $conn->query($check_deposits_table_sql);
$deposits_table_exists = ($check_deposits_result && $check_deposits_result->fetch_assoc()['count'] > 0);

if ($deposits_table_exists) {
  $recent_sql = "SELECT d.*, pm.payment_type as user_payment_type, apm.payment_type as admin_payment_type, 
                   apm.account_name as admin_account_name 
                   FROM deposits d
                   LEFT JOIN payment_methods pm ON d.payment_method_id = pm.id
                   LEFT JOIN admin_payment_methods apm ON d.admin_payment_id = apm.id
                   WHERE d.user_id = ?
                   ORDER BY d.created_at DESC LIMIT 5";

  $recent_stmt = $conn->prepare($recent_sql);
  $recent_stmt->bind_param("i", $user_id);
  $recent_stmt->execute();
  $recent_result = $recent_stmt->get_result();

  while ($row = $recent_result->fetch_assoc()) {
    $recent_deposits[] = $row;
  }
  $recent_stmt->close();
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
  <?php include 'includes/head.php'; ?>
  <title>AutoProftX - Deposit Funds</title>
</head>

<body class="bg-gray-900 text-white font-sans min-h-screen flex flex-col">
  <?php include 'includes/navbar.php'; ?>
  <?php include 'includes/mobile-bar.php'; ?>
  <?php include 'includes/pre-loader.php'; ?>

  <!-- Main Content -->
  <main id="main-content" class="flex-grow py-6 bg-gray-900 overflow-hidden">
    <div class="container mx-auto px-4 sm:px-6 lg:px-8 max-w-6xl">
      <!-- Header Section -->
      <div class="mb-8 text-center sm:text-left">
        <h1 class="text-2xl sm:text-3xl font-bold text-white mb-2">Deposit Funds</h1>
        <p class="text-gray-400 text-sm sm:text-base">Securely add money to your AutoProftX wallet</p>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 overflow-x-hidden">
        <!-- Deposit Form -->
        <div class="lg:col-span-2 order-2 lg:order-1 overflow-x-hidden">
          <div class="bg-gray-800 rounded-xl border border-gray-700 p-6 shadow-xl overflow-hidden relative">
            <!-- Blue accent line at top (changed from yellow for Binance) -->
            <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-blue-400 to-blue-600"></div>

            <h2 class="text-xl sm:text-2xl font-bold mb-6 text-white flex items-center">
              <span class="bg-blue-500 text-black p-1.5 rounded-md mr-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </span>
              Add Money to Wallet
            </h2>

            <!-- Add this to your deposit page to show the new bonus tiers -->
            <div class="bg-blue-900 bg-opacity-50 text-blue-200 p-4 rounded-lg mb-6 flex items-start border-l-4 border-blue-500">
              <svg class="w-6 h-6 mr-3 flex-shrink-0 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
              <div>
                <p class="text-sm sm:text-base font-medium">Deposit Bonus Program</p>
                <p class="text-xs sm:text-sm mt-1">Receive an instant bonus when your deposit is approved:</p>
                <div class="grid grid-cols-2 gap-2 mt-2">
                  <div class="text-xs">$30 - $49.99</div>
                  <div class="text-xs font-bold">$5 Bonus</div>

                  <div class="text-xs">$50 - $99.99</div>
                  <div class="text-xs font-bold">$10 Bonus</div>

                  <div class="text-xs">$100 - $249.99</div>
                  <div class="text-xs font-bold">$20 Bonus</div>

                  <div class="text-xs">$250 - $499.99</div>
                  <div class="text-xs font-bold">$50 Bonus</div>

                  <div class="text-xs">$500 - $999.99</div>
                  <div class="text-xs font-bold">$100 Bonus</div>

                  <div class="text-xs">$1000+</div>
                  <div class="text-xs font-bold">$200 Bonus</div>
                </div>
              </div>
            </div>

            <?php if (!empty($success_message)): ?>
              <div class="bg-green-900 bg-opacity-50 text-green-200 p-4 rounded-lg mb-6 flex items-start border-l-4 border-green-500">
                <svg class="w-6 h-6 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span class="text-sm sm:text-base"><?php echo $success_message; ?></span>
              </div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
              <div class="bg-red-900 bg-opacity-50 text-red-200 p-4 rounded-lg mb-6 flex items-start border-l-4 border-red-500">
                <svg class="w-6 h-6 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span class="text-sm sm:text-base"><?php echo $error_message; ?></span>
              </div>
            <?php endif; ?>

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" enctype="multipart/form-data" class="space-y-6">
              <!-- Amount -->
              <div class="relative">
                <label for="amount" class="block text-sm font-medium text-gray-300 mb-2">
                  <span class="flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    Amount (USD)
                  </span>
                </label>
                <div class="mt-1 relative rounded-md shadow-sm">
                  <div class="absolute inset-y-0 left-0 flex items-center pointer-events-none">
                    <span class="text-gray-400">$</span>
                  </div>
                  <input type="number" id="amount" name="amount" min="<?php echo $min_deposit_amount; ?>" required value="<?php echo htmlspecialchars($amount); ?>"
                    class="bg-gray-900 focus:ring-blue-500 focus:border-blue-500 block w-full pl-10 pr-4 py-3 border border-gray-600 rounded-lg text-white text-lg"
                    placeholder="<?php echo $min_deposit_amount; ?>">

                </div>
                <script>
                  // Add this script to your deposit page to show real-time bonus calculation
                  document.addEventListener('DOMContentLoaded', function() {
                    const amountInput = document.getElementById('amount');
                    const bonusInfo = document.getElementById('bonus-info');

                    if (amountInput && bonusInfo) {
                      // Function to calculate bonus based on amount
                      function calculateBonus(amount) {
                        if (amount < 30) return 0;
                        if (amount < 50) return 5;
                        if (amount < 100) return 10;
                        if (amount < 250) return 20;
                        if (amount < 500) return 50;
                        if (amount < 1000) return 100;
                        return 200; // $1000+
                      }

                      // Update bonus info as user types
                      amountInput.addEventListener('input', function() {
                        const amount = parseFloat(this.value) || 0;
                        const bonus = calculateBonus(amount);

                        if (amount >= 30) {
                          bonusInfo.innerHTML = `
          <div class="bg-green-900 bg-opacity-50 text-green-200 p-3 rounded-lg mt-2 flex items-center">
            <svg class="w-5 h-5 mr-2 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <div>
              <span class="font-medium">Your deposit of $${amount.toFixed(2)} qualifies for a:</span>
              <span class="block text-lg font-bold">$${bonus.toFixed(2)} Bonus!</span>
            </div>
          </div>
        `;
                          bonusInfo.classList.remove('hidden');
                        } else if (amount > 0) {
                          bonusInfo.innerHTML = `
          <div class="bg-yellow-900 bg-opacity-50 text-yellow-200 p-3 rounded-lg mt-2 flex items-center">
            <svg class="w-5 h-5 mr-2 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>Deposit at least $30 to receive a bonus</span>
          </div>
        `;
                          bonusInfo.classList.remove('hidden');
                        } else {
                          bonusInfo.classList.add('hidden');
                        }
                      });
                    }
                  });
                </script>

                <!-- Add this div after your amount input field -->
                <div id="bonus-info" class="hidden"></div>
                <p class="mt-1.5 text-xs text-gray-400 flex items-center">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  Minimum deposit: $<?php echo $min_deposit_amount; ?>
                </p>
              </div>

              <!-- From (Your Payment Method) -->
              <div>
                <label for="payment_method_id" class="block text-sm font-medium text-gray-300 mb-2">
                  <span class="flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                    </svg>
                    From (Your Binance Account)
                  </span>
                </label>
                <div class="relative">
                  <select id="payment_method_id" name="payment_method_id" required
                    class="bg-gray-900 focus:ring-blue-500 focus:border-blue-500 block w-full px-4 py-3 border border-gray-600 rounded-lg text-white appearance-none text-sm truncate">
                    <option value="" disabled <?php echo empty($payment_method_id) ? 'selected' : ''; ?>>Select your Binance account</option>
                    <?php foreach ($payment_methods as $method): ?>
                      <option value="<?php echo $method['id']; ?>" <?php echo ($payment_method_id == $method['id']) ? 'selected' : ''; ?> class="truncate">
                        Binance - <?php echo htmlspecialchars($method['account_name']); ?> (<?php echo substr($method['account_number'], 0, 8) . '...' . substr($method['account_number'], -8); ?>)
                        <?php echo $method['is_default'] ? '(Default)' : ''; ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <div class="absolute inset-y-0 right-0 flex items-center px-3 pointer-events-none">
                    <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                  </div>
                </div>
                <?php if (empty($payment_methods)): ?>
                  <p class="mt-1.5 text-xs text-red-400 flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    You don't have any Binance accounts yet.
                    <a href="payment-methods.php" class="text-blue-500 hover:text-blue-400 ml-1 font-medium">Add one now</a>
                  </p>
                <?php endif; ?>
              </div>

              <!-- To (Admin Payment Method) -->
              <div>
                <label for="admin_payment_id" class="block text-sm font-medium text-gray-300 mb-2">
                  <span class="flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                    To (Send funds to)
                  </span>
                </label>
                <div class="relative">
                  <select id="admin_payment_id" name="admin_payment_id" required
                    class="bg-gray-900 focus:ring-blue-500 focus:border-blue-500 block w-full px-4 py-3 border border-gray-600 rounded-lg text-white appearance-none text-sm truncate">
                    <option value="" disabled selected>Select where to send funds</option>
                    <?php foreach ($admin_payment_methods as $method): ?>
                      <option value="<?php echo $method['id']; ?>" data-type="<?php echo $method['payment_type']; ?>" data-info="<?php echo htmlspecialchars($method['additional_info']); ?>" data-address="<?php echo htmlspecialchars($method['account_number']); ?>" class="truncate">
                        Binance - <?php echo htmlspecialchars($method['account_name']); ?> (<?php echo substr($method['account_number'], 0, 8) . '...' . substr($method['account_number'], -8); ?>)
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <div class="absolute inset-y-0 right-0 flex items-center px-3 pointer-events-none">
                    <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                  </div>
                </div>
              </div>

              <!-- Payment Information Display -->
              <div id="payment_info" class="bg-gray-900 bg-opacity-70 p-4 rounded-lg mb-6 hidden border border-blue-600 border-opacity-50">
                <h3 class="font-bold mb-2 text-blue-500 flex items-center text-sm">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  Payment Information
                </h3>
                <div id="payment_details" class="text-sm text-gray-300 ml-6"></div>

                <!-- Admin TRC20 Address Display -->
                <div id="admin_address_container" class="mt-3 ml-6">
                  <div class="bg-gray-800 p-2 rounded-md flex items-center justify-between mb-2 border border-gray-700">
                    <div class="truncate mr-2" id="admin_address_display"></div>
                    <button type="button" id="copy_address_btn" class="bg-blue-700 hover:bg-blue-600 text-white text-xs py-1 px-3 rounded-md flex items-center flex-shrink-0">
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-4M16 1v3m0 0v3m0-3h3m-3 0h-3" />
                      </svg>
                      Copy
                    </button>
                  </div>
                  <p class="text-xs text-blue-400">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 inline mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Important: Send only TRC20 USDT to this address
                  </p>
                </div>
              </div>

              <!-- Transaction ID -->
              <div>
                <label for="transaction_id" class="block text-sm font-medium text-gray-300 mb-2">
                  <span class="flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                    Transaction ID (Optional)
                  </span>
                </label>
                <input type="text" id="transaction_id" name="transaction_id" value="<?php echo htmlspecialchars($transaction_id); ?>"
                  class="bg-gray-900 focus:ring-blue-500 focus:border-blue-500 block w-full px-4 py-3 border border-gray-600 rounded-lg text-white"
                  placeholder="Enter transaction ID from Binance">
                <p class="mt-1.5 text-xs text-gray-400 flex items-center">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  Enter the transaction ID from your Binance account (if available)
                </p>
              </div>

              <!-- Payment Proof -->
              <div>
                <label for="payment_proof" class="block text-sm font-medium text-gray-300 mb-2">
                  <span class="flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    Payment Proof
                  </span>
                </label>
                <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-700 border-dashed rounded-lg bg-gray-900 bg-opacity-50 hover:bg-opacity-70 transition duration-300">
                  <div class="space-y-1 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-500" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                      <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H8m36-12h-4m4 0H20" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    <div class="flex flex-col sm:flex-row justify-center text-sm text-gray-400">
                      <label for="payment_proof" class="relative cursor-pointer bg-blue-500 hover:bg-blue-600 rounded-md font-medium text-white px-3 py-1.5 transition duration-300">
                        <span>Upload a file</span>
                        <input id="payment_proof" name="payment_proof" type="file" class="sr-only" accept=".jpg,.jpeg,.png,.pdf">
                      </label>
                      <p class="sm:pl-1 mt-2 sm:mt-0 sm:pt-1">or drag and drop</p>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">
                      PNG, JPG, JPEG or PDF up to 5MB
                    </p>
                  </div>
                </div>
                <div id="file_name" class="mt-2 text-sm text-gray-300 flex items-center"></div>
              </div>

              <!-- Notes -->
              <div>
                <label for="notes" class="block text-sm font-medium text-gray-300 mb-2">
                  <span class="flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    Additional Notes (Optional)
                  </span>
                </label>
                <textarea id="notes" name="notes" rows="3"
                  class="bg-gray-900 focus:ring-blue-500 focus:border-blue-500 block w-full px-4 py-3 border border-gray-600 rounded-lg text-white"
                  placeholder="Any additional information you'd like to provide"><?php echo htmlspecialchars($notes); ?></textarea>
              </div>

              <div class="pt-2">
                <button type="submit" name="submit"
                  class="w-full bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white font-bold py-3 px-6 rounded-lg transition duration-300 flex items-center justify-center shadow-lg transform hover:scale-[1.02]">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  Submit Deposit Request
                </button>
              </div>
            </form>
          </div>
        </div>

        <!-- Recent Deposits & Instructions -->
        <div class="lg:col-span-1 space-y-6 order-1 lg:order-2 mb-8 lg:mb-0 overflow-x-hidden">
          <!-- Process Instructions -->
          <div class="bg-gray-800 rounded-xl border border-gray-700 p-6 shadow-xl overflow-hidden relative">
            <!-- Blue accent line at top -->
            <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-blue-400 to-blue-600"></div>

            <h3 class="text-lg font-bold mb-5 text-white flex items-center">
              <span class="bg-blue-500 text-white p-1.5 rounded-md mr-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                </svg>
              </span>
              How It Works
            </h3>

            <div class="space-y-4">
              <div class="flex items-start bg-gray-900 bg-opacity-60 p-3 rounded-lg hover:bg-opacity-80 transition duration-300">
                <div class="h-8 w-8 rounded-full bg-blue-500 text-white flex items-center justify-center mr-3 flex-shrink-0 shadow-md">
                  1
                </div>
                <p class="text-gray-300 text-sm">Select the amount you wish to deposit (min. $<?php echo $min_deposit_amount; ?>)</p>
              </div>
              <div class="flex items-start bg-gray-900 bg-opacity-60 p-3 rounded-lg hover:bg-opacity-80 transition duration-300">
                <div class="h-8 w-8 rounded-full bg-blue-500 text-white flex items-center justify-center mr-3 flex-shrink-0 shadow-md">
                  2
                </div>
                <p class="text-gray-300 text-sm">Choose your Binance account and where to send funds</p>
              </div>
              <div class="flex items-start bg-gray-900 bg-opacity-60 p-3 rounded-lg hover:bg-opacity-80 transition duration-300">
                <div class="h-8 w-8 rounded-full bg-blue-500 text-white flex items-center justify-center mr-3 flex-shrink-0 shadow-md">
                  3
                </div>
                <p class="text-gray-300 text-sm">Send TRC20 USDT from your account to our Binance address</p>
              </div>
              <div class="flex items-start bg-gray-900 bg-opacity-60 p-3 rounded-lg hover:bg-opacity-80 transition duration-300">
                <div class="h-8 w-8 rounded-full bg-blue-500 text-white flex items-center justify-center mr-3 flex-shrink-0 shadow-md">
                  4
                </div>
                <p class="text-gray-300 text-sm">Upload proof of payment (screenshot from Binance)</p>
              </div>
              <div class="flex items-start bg-gray-900 bg-opacity-60 p-3 rounded-lg hover:bg-opacity-80 transition duration-300">
                <div class="h-8 w-8 rounded-full bg-blue-500 text-white flex items-center justify-center mr-3 flex-shrink-0 shadow-md">
                  5
                </div>
                <p class="text-gray-300 text-sm">Wait for confirmation (usually within 24 hours)</p>
              </div>
            </div>
          </div>

          <!-- Bonus Information Card -->
          <div class="bg-gray-800 rounded-xl border border-gray-700 p-6 shadow-xl overflow-hidden relative">
            <!-- Accent line at top -->
            <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-green-400 to-green-600"></div>

            <h3 class="text-lg font-bold mb-5 text-white flex items-center">
              <span class="bg-green-500 text-white p-1.5 rounded-md mr-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </span>
              Deposit Bonuses
            </h3>

            <div class="space-y-3">
              <div class="bg-gray-900 bg-opacity-60 p-4 rounded-lg border border-green-600 border-opacity-30">
                <div class="flex justify-between items-center">
                  <div class="flex items-center">
                    <div class="h-8 w-8 rounded-full bg-green-600 text-white flex items-center justify-center mr-3">
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12" />
                      </svg>
                    </div>
                    <span class="text-sm font-bold text-white">$500+ Deposit</span>
                  </div>
                  <div class="bg-green-900 px-3 py-1 rounded-full text-green-400 text-xs font-medium border border-green-600 border-opacity-30">
                    $100 Bonus
                  </div>
                </div>
              </div>

              <div class="bg-gray-900 bg-opacity-60 p-4 rounded-lg border border-green-600 border-opacity-30">
                <div class="flex justify-between items-center">
                  <div class="flex items-center">
                    <div class="h-8 w-8 rounded-full bg-green-600 text-white flex items-center justify-center mr-3">
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12" />
                      </svg>
                    </div>
                    <span class="text-sm font-bold text-white">$1000+ Deposit</span>
                  </div>
                  <div class="bg-green-900 px-3 py-1 rounded-full text-green-400 text-xs font-medium border border-green-600 border-opacity-30">
                    $200 Bonus
                  </div>
                </div>
              </div>

              <div class="bg-gray-900 bg-opacity-60 p-4 rounded-lg border border-yellow-600 border-opacity-30">
                <div class="flex items-center">
                  <div class="h-8 w-8 rounded-full bg-yellow-600 text-white flex items-center justify-center mr-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                  </div>
                  <div>
                    <span class="text-sm font-bold text-white">First Withdrawal</span>
                    <p class="text-xs text-gray-400 mt-1">Limited to 50% of your first deposit</p>
                  </div>
                </div>
              </div>

              <div class="bg-gray-900 bg-opacity-60 p-4 rounded-lg border border-yellow-600 border-opacity-30">
                <div class="flex items-center">
                  <div class="h-8 w-8 rounded-full bg-yellow-600 text-white flex items-center justify-center mr-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                  </div>
                  <div>
                    <span class="text-sm font-bold text-white">Withdrawal Limit</span>
                    <p class="text-xs text-gray-400 mt-1">One withdrawal request per day</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Recent Deposits -->
          <div class="bg-gray-800 rounded-xl border border-gray-700 p-6 shadow-xl overflow-hidden relative">
            <!-- Blue accent line at top -->
            <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-blue-400 to-blue-600"></div>

            <h3 class="text-lg font-bold mb-5 text-white flex items-center">
              <span class="bg-blue-500 text-white p-1.5 rounded-md mr-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </span>
              Recent Deposits
            </h3>

            <?php if (empty($recent_deposits)): ?>
              <div class="text-center py-8 bg-gray-900 bg-opacity-50 rounded-lg">
                <div class="text-gray-500 mb-3">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </div>
                <p class="text-gray-400 text-sm">No recent deposits found</p>
                <p class="text-gray-500 text-xs mt-1">Your deposit history will appear here</p>
              </div>
            <?php else: ?>
              <div class="space-y-3">
                <?php foreach ($recent_deposits as $deposit): ?>
                  <div class="bg-gray-900 rounded-lg p-4 border border-gray-700 hover:border-gray-600 transition duration-300 hover:shadow-md transform hover:translate-y-[-2px]">
                    <div class="flex justify-between items-start mb-2">
                      <div>
                        <h4 class="font-bold text-lg">$<?php echo number_format($deposit['amount'], 2); ?></h4>
                        <p class="text-xs text-gray-400">
                          <?php echo date('M d, Y h:i A', strtotime($deposit['created_at'])); ?>
                        </p>
                      </div>
                      <div>
                        <?php if ($deposit['status'] == 'pending'): ?>
                          <span class="px-2.5 py-1 text-xs rounded-full bg-yellow-900 text-yellow-400 flex items-center border border-yellow-800">
                            <span class="h-1.5 w-1.5 bg-yellow-400 rounded-full mr-1"></span>
                            Pending
                          </span>
                        <?php elseif ($deposit['status'] == 'approved'): ?>
                          <span class="px-2.5 py-1 text-xs rounded-full bg-green-900 text-green-400 flex items-center border border-green-800">
                            <span class="h-1.5 w-1.5 bg-green-400 rounded-full mr-1"></span>
                            Approved
                          </span>
                        <?php elseif ($deposit['status'] == 'rejected'): ?>
                          <span class="px-2.5 py-1 text-xs rounded-full bg-red-900 text-red-400 flex items-center border border-red-800">
                            <span class="h-1.5 w-1.5 bg-red-400 rounded-full mr-1"></span>
                            Rejected
                          </span>
                        <?php endif; ?>
                      </div>
                    </div>
                    <div class="text-xs sm:text-sm text-gray-400 flex items-center">
                      <span class="flex items-center">
                        Binance
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mx-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                        <?php echo ucfirst($deposit['admin_payment_type']); ?>
                      </span>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>

              <div class="mt-6 text-center">
                <a href="deposit-history.php" class="text-blue-500 hover:text-blue-400 bg-gray-900 hover:bg-gray-800 text-sm py-2 px-4 rounded-lg inline-flex items-center transition duration-300 border border-gray-700">
                  View All Deposits
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 ml-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                  </svg>
                </a>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </main>
  <!-- Add this script at the end of your document or in your script section -->
  <script>
    // Show selected file name with icon
    document.getElementById('payment_proof').addEventListener('change', function(e) {
      const file = e.target.files[0];
      if (file) {
        let fileIcon = '';
        const fileExt = file.name.split('.').pop().toLowerCase();

        if (['jpg', 'jpeg', 'png'].includes(fileExt)) {
          fileIcon = '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>';
        } else if (fileExt === 'pdf') {
          fileIcon = '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>';
        } else {
          fileIcon = '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>';
        }

        document.getElementById('file_name').innerHTML = fileIcon + file.name +
          ' <span class="text-xs text-gray-500 ml-2">(' + formatFileSize(file.size) + ')</span>';
      } else {
        document.getElementById('file_name').textContent = '';
      }
    });

    // Format file size
    function formatFileSize(bytes) {
      if (bytes < 1024) return bytes + ' bytes';
      else if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
      else return (bytes / 1048576).toFixed(1) + ' MB';
    }

    // Show payment info when admin payment method is selected
    document.getElementById('admin_payment_id').addEventListener('change', function() {
      const selectedOption = this.options[this.selectedIndex];
      const paymentType = selectedOption.getAttribute('data-type');
      const paymentInfo = selectedOption.getAttribute('data-info');
      const address = selectedOption.getAttribute('data-address');

      if (selectedOption.value) {
        let detailsHtml = `<div class="text-sm text-gray-300">`;
        detailsHtml += `<p><strong>Binance TRC20 Address:</strong></p>`;
        detailsHtml += `</div>`;

        document.getElementById('payment_details').innerHTML = detailsHtml;
        document.getElementById('admin_address_display').textContent = address;
        document.getElementById('admin_address_container').setAttribute('data-address', address);
        document.getElementById('payment_info').classList.remove('hidden');

        if (paymentInfo) {
          document.getElementById('payment_details').innerHTML += `<p class="mt-2">${paymentInfo}</p>`;
        }
      } else {
        document.getElementById('payment_info').classList.add('hidden');
      }
    });

    // Copy TRC20 address to clipboard
    document.getElementById('copy_address_btn').addEventListener('click', function() {
      const address = document.getElementById('admin_address_container').getAttribute('data-address');

      // Create temporary input element to copy from
      const tempInput = document.createElement('input');
      tempInput.value = address;
      document.body.appendChild(tempInput);
      tempInput.select();
      document.execCommand('copy');
      document.body.removeChild(tempInput);

      // Show feedback that address was copied
      const originalText = this.innerHTML;
      this.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg> Copied!';

      setTimeout(() => {
        this.innerHTML = originalText;
      }, 2000);
    });
  </script>

  <?php include 'includes/footer.php'; ?>
  <?php include 'includes/js-links.php'; ?>
</body>

</html>