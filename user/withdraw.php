<?php
// Start session
session_start();

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database connection
include '../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
  header('Location: login.php');
  exit;
}

// Get user ID from session
$user_id = $_SESSION['user_id'];

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

// Get user's wallet balance
$balance_query = "SELECT balance FROM wallets WHERE user_id = ?";
$stmt = $conn->prepare($balance_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$balance = 0;
if ($result->num_rows > 0) {
  $wallet = $result->fetch_assoc();
  $balance = $wallet['balance'];
}

// Get user's payment methods
$payment_methods = [];
$payment_methods_query = "SELECT * FROM payment_methods WHERE user_id = ? AND is_active = 1 ORDER BY is_default DESC";
$stmt = $conn->prepare($payment_methods_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$payment_methods_result = $stmt->get_result();

while ($row = $payment_methods_result->fetch_assoc()) {
  $payment_methods[] = $row;
}

// Process withdrawal request
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['withdraw'])) {
  // Log for debugging
  error_log("Withdrawal form submitted");

  $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
  $payment_method_id = isset($_POST['payment_method']) ? intval($_POST['payment_method']) : 0;

  // Validate the withdrawal amount
  if ($amount <= 0) {
    $error_message = "Please enter a valid amount to withdraw.";
  } elseif ($amount > $balance) {
    $error_message = "Insufficient balance. Your available balance is $" . number_format($balance, 2);
  } elseif ($payment_method_id <= 0) {
    $error_message = "Please select a valid payment method.";
  } else {
    // Check daily withdrawal limit (one per day)
    $today = date('Y-m-d');
    $daily_check = "SELECT COUNT(*) as count FROM withdrawal_daily_limits 
                    WHERE user_id = ? AND withdrawal_date = ?";
    $stmt = $conn->prepare($daily_check);
    $stmt->bind_param("is", $user_id, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    $daily_count = $result->fetch_assoc()['count'];

    if ($daily_count > 0) {
      $error_message = "You can only make one withdrawal request per day. Please try again tomorrow.";
    } else {
      // Check first deposit and first withdrawal restrictions
      $first_deposit_check = "SELECT first_deposit_amount, first_withdrawal_made 
                             FROM first_deposits WHERE user_id = ?";
      $stmt = $conn->prepare($first_deposit_check);
      $stmt->bind_param("i", $user_id);
      $stmt->execute();
      $result = $stmt->get_result();

      $is_first_withdrawal = false;
      $max_first_withdrawal = 0;

      if ($result->num_rows > 0) {
        $first_deposit = $result->fetch_assoc();

        // If this is the first withdrawal, check the 50% rule
        if ($first_deposit['first_withdrawal_made'] == 0) {
          $is_first_withdrawal = true;
          $max_first_withdrawal = $first_deposit['first_deposit_amount'] / 2;

          if ($amount > $max_first_withdrawal) {
            $error_message = "Your first withdrawal cannot exceed $" . number_format($max_first_withdrawal, 2) . " (50% of your initial deposit of $" . number_format($first_deposit['first_deposit_amount'], 2) . ")";
          }
        }
      }

      if (empty($error_message)) {
        // Calculate tax (10%)
        $tax_amount = $amount * 0.10;
        $net_amount = $amount - $tax_amount;

        // Begin transaction
        $conn->begin_transaction();

        try {
          // Create withdrawal record
          $withdrawal_id = 'WD-' . rand(10000, 99999);

          // Update user's wallet (deduct full amount)
          $update_wallet = "UPDATE wallets SET balance = balance - ? WHERE user_id = ?";
          $stmt = $conn->prepare($update_wallet);
          $stmt->bind_param("di", $amount, $user_id);
          $stmt->execute();

          // Get payment method details
          $payment_method_query = "SELECT * FROM payment_methods WHERE id = ? AND user_id = ?";
          $stmt = $conn->prepare($payment_method_query);
          $stmt->bind_param("ii", $payment_method_id, $user_id);
          $stmt->execute();
          $payment_method_result = $stmt->get_result();

          if ($payment_method_result->num_rows === 0) {
            throw new Exception("Payment method not found.");
          }

          $payment_method = $payment_method_result->fetch_assoc();

          // Insert into withdrawals table
          $insert_withdrawal = "INSERT INTO withdrawals (
                    withdrawal_id, 
                    user_id, 
                    amount, 
                    tax_amount,
                    net_amount,
                    payment_method_id, 
                    account_number,
                    account_name,
                    payment_type,
                    status, 
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";

          $stmt = $conn->prepare($insert_withdrawal);
          $stmt->bind_param(
            "sidddisss",
            $withdrawal_id,
            $user_id,
            $amount,
            $tax_amount,
            $net_amount,
            $payment_method_id,
            $payment_method['account_number'],
            $payment_method['account_name'],
            $payment_method['payment_type']
          );
          $result = $stmt->execute();

          if (!$result) {
            throw new Exception("Failed to insert withdrawal: " . $stmt->error);
          }

          // Record transaction
          $transaction_query = "INSERT INTO transactions (
                    user_id, 
                    transaction_type, 
                    amount, 
                    status, 
                    description, 
                    reference_id
                ) VALUES (?, 'withdrawal', ?, 'pending', ?, ?)";

          $description = "Withdrawal Request (10% Tax Applied)";

          $stmt = $conn->prepare($transaction_query);
          $stmt->bind_param("idss", $user_id, $amount, $description, $withdrawal_id);
          $result = $stmt->execute();

          if (!$result) {
            throw new Exception("Failed to record transaction: " . $stmt->error);
          }

          // Record withdrawal request for daily limit
          $limit_query = "INSERT INTO withdrawal_daily_limits (user_id, withdrawal_date, request_count)
                        VALUES (?, ?, 1)";
          $stmt = $conn->prepare($limit_query);
          $stmt->bind_param("is", $user_id, $today);
          $stmt->execute();

          // Update first withdrawal status if applicable
          if ($is_first_withdrawal) {
            $update_first_withdrawal = "UPDATE first_deposits 
                                      SET first_withdrawal_made = 1, updated_at = NOW()
                                      WHERE user_id = ? AND first_withdrawal_made = 0";
            $stmt = $conn->prepare($update_first_withdrawal);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
          }

          // Commit transaction
          $conn->commit();

          $success_message = "Your withdrawal request for $" . number_format($amount, 2) . " has been submitted successfully. After 10% tax deduction, you will receive $" . number_format($net_amount, 2) . ". The request is pending approval.";

          // Refresh balance
          $stmt = $conn->prepare($balance_query);
          $stmt->bind_param("i", $user_id);
          $stmt->execute();
          $result = $stmt->get_result();
          if ($result->num_rows > 0) {
            $wallet = $result->fetch_assoc();
            $balance = $wallet['balance'];
          }
        } catch (Exception $e) {
          // Rollback transaction on error
          $conn->rollback();
          $error_message = "An error occurred: " . $e->getMessage();
          error_log("Withdrawal error: " . $e->getMessage());
        }
      }
    }
  }
}

// Check if this would be the first withdrawal and get max amount (for UI display)
$first_withdrawal_info = [
  'is_first' => false,
  'max_amount' => 0,
  'first_deposit' => 0
];

$first_withdrawal_check = "SELECT first_deposit_amount, first_withdrawal_made 
                          FROM first_deposits WHERE user_id = ?";
$stmt = $conn->prepare($first_withdrawal_check);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
  $first_deposit = $result->fetch_assoc();
  if ($first_deposit['first_withdrawal_made'] == 0) {
    $first_withdrawal_info['is_first'] = true;
    $first_withdrawal_info['max_amount'] = $first_deposit['first_deposit_amount'] / 2;
    $first_withdrawal_info['first_deposit'] = $first_deposit['first_deposit_amount'];
  }
}

// Check if user already made a withdrawal request today
$daily_limit_reached = false;
$today = date('Y-m-d');
$daily_check = "SELECT COUNT(*) as count FROM withdrawal_daily_limits 
                WHERE user_id = ? AND withdrawal_date = ?";
$stmt = $conn->prepare($daily_check);
$stmt->bind_param("is", $user_id, $today);
$stmt->execute();
$result = $stmt->get_result();
$daily_count = $result->fetch_assoc()['count'];

if ($daily_count > 0) {
  $daily_limit_reached = true;
}

// Get user's recent withdrawals
$withdrawals = [];
$withdrawals_exist = true;

// First, check if the withdrawals table exists
$table_check_query = "SHOW TABLES LIKE 'withdrawals'";
$table_result = $conn->query($table_check_query);

if ($table_result->num_rows > 0) {
  // Table exists, now fetch withdrawals
  $withdrawals_query = "SELECT * FROM withdrawals WHERE user_id = ? ORDER BY created_at DESC LIMIT 10";
  $stmt = $conn->prepare($withdrawals_query);
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $withdrawals_result = $stmt->get_result();

  while ($row = $withdrawals_result->fetch_assoc()) {
    $withdrawals[] = $row;
  }
} else {
  $withdrawals_exist = false;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <?php include 'includes/head.php'; ?>
  <title>Withdraw Funds - AutoProfTX</title>
  <style>
    .withdraw-bg {
      background-image: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), url('assets/images/withdraw-bg.jpg');
      background-size: cover;
      background-position: center;
    }
  </style>
</head>

<body class="bg-gray-900 text-white font-sans min-h-screen flex flex-col">
  <?php include 'includes/navbar.php'; ?>
  <?php include 'includes/pre-loader.php'; ?>
  <?php include 'includes/mobile-bar.php'; ?>


  <!-- Main Content -->
  <main style="display: none;" id="main-content" class="flex-grow py-6">
    <div class="container mx-auto px-4 sm:px-6 lg:px-8">
      <!-- Page Header -->
      <div class="flex justify-between items-center mb-8">
        <h1 class="text-2xl font-bold">Withdraw Funds</h1>
        <a href="dashboard.php" class="text-sm bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition duration-300 flex items-center">
          <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
        </a>
      </div>

      <!-- Add a proper form element with method="post" -->
      <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="bg-gray-800 rounded-xl border border-gray-700 p-6 mb-8">

        <!-- Success and error messages -->
        <?php if (!empty($success_message)): ?>
          <div class="bg-green-900 bg-opacity-50 text-green-200 p-4 rounded-lg mb-6">
            <?php echo $success_message; ?>
          </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
          <div class="bg-red-900 bg-opacity-50 text-red-200 p-4 rounded-lg mb-6">
            <?php echo $error_message; ?>
          </div>
        <?php endif; ?>

        <!-- Amount field with label -->
        <div class="mb-6">
          <label for="amount" class="block text-sm font-medium text-gray-300 mb-2">Withdrawal Amount</label>
          <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
              <span class="text-gray-400">$</span>
            </div>
            <?php if ($first_withdrawal_info['is_first']): ?>
              <input type="number" id="amount" name="amount" min="1" max="<?php echo $first_withdrawal_info['max_amount']; ?>" step="0.01" required
                class="bg-gray-900 focus:ring-blue-500 focus:border-blue-500 block w-full pl-10 pr-4 py-3 border border-gray-600 rounded-lg text-white text-lg"
                placeholder="Enter amount">
            <?php else: ?>
              <input type="number" id="amount" name="amount" min="1" step="0.01" required
                class="bg-gray-900 focus:ring-blue-500 focus:border-blue-500 block w-full pl-10 pr-4 py-3 border border-gray-600 rounded-lg text-white text-lg"
                placeholder="Enter amount">
            <?php endif; ?>
          </div>
          <div id="tax-info" class="hidden mt-2 bg-gray-800 p-3 rounded-md border border-blue-700 border-opacity-40"></div>
        </div>

        <!-- Payment method selection -->
        <div class="mb-6">
          <label for="payment_method" class="block text-sm font-medium text-gray-300 mb-2">Payment Method</label>
          <select id="payment_method" name="payment_method" required class="bg-gray-900 focus:ring-blue-500 focus:border-blue-500 block w-full px-4 py-3 border border-gray-600 rounded-lg text-white">
            <option value="">Select payment method</option>
            <?php foreach ($payment_methods as $method): ?>
              <option value="<?php echo $method['id']; ?>">
                <?php echo ucfirst($method['payment_type']); ?> - <?php echo $method['account_name']; ?>
                (<?php echo substr($method['account_number'], 0, 5) . '...' . substr($method['account_number'], -5); ?>)
              </option>
            <?php endforeach; ?>
          </select>
          <?php if (empty($payment_methods)): ?>
            <p class="mt-2 text-sm text-red-400">You don't have any payment methods. <a href="payment-methods.php" class="text-blue-400 hover:underline">Add one now</a></p>
          <?php endif; ?>
        </div>

        <!-- Submit button -->
        <button type="submit" name="withdraw"
          class="w-full bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white font-bold py-3 px-6 rounded-lg transition duration-300 flex items-center justify-center shadow-lg transform hover:scale-[1.02]"
          <?php echo $daily_limit_reached ? 'disabled' : ''; ?>>
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
          </svg>
          Request Withdrawal
        </button>
      </form>

      <!-- Add this JavaScript to show real-time tax calculation as user enters amount -->
      <script>
        document.addEventListener('DOMContentLoaded', function() {
          const amountInput = document.getElementById('amount');
          const taxInfoDiv = document.getElementById('tax-info');

          if (amountInput && taxInfoDiv) {
            amountInput.addEventListener('input', function() {
              const amount = parseFloat(this.value) || 0;
              const taxAmount = amount * 0.1;
              const netAmount = amount - taxAmount;

              if (amount > 0) {
                taxInfoDiv.innerHTML = `
          <div class="mt-2 text-sm text-gray-300">
            <p>Amount: $${amount.toFixed(2)}</p>
            <p>Tax (10%): $${taxAmount.toFixed(2)}</p>
            <p class="font-bold">You will receive: $${netAmount.toFixed(2)}</p>
          </div>
        `;
                taxInfoDiv.classList.remove('hidden');
              } else {
                taxInfoDiv.classList.add('hidden');
              }
            });
          }
        });
      </script>

      <!-- Add this div somewhere near your amount input field -->
      <div id="tax-info" class="hidden bg-gray-800 p-3 rounded-md border border-blue-700 border-opacity-40"></div>

      <!-- Recent Withdrawals -->
      <div class="mt-10">
        <h2 class="text-xl font-bold mb-6">Recent Withdrawals</h2>

        <?php if (!$withdrawals_exist): ?>
          <div class="bg-gray-800 rounded-xl p-6 text-center border border-gray-700">
            <p class="text-gray-400">No withdrawals history found. The withdrawals table may not exist in your database yet.</p>
          </div>
        <?php elseif (empty($withdrawals)): ?>
          <div class="bg-gray-800 rounded-xl p-6 text-center border border-gray-700">
            <i class="fas fa-history text-4xl text-gray-500 mb-4"></i>
            <p class="text-gray-400">You haven't made any withdrawals yet.</p>
          </div>
        <?php else: ?>
          <div class="overflow-x-auto bg-gray-800 rounded-xl border border-gray-700">
            <table class="min-w-full divide-y divide-gray-700">
              <thead>
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Date</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Amount</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Tax</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Net Amount</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Method</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Status</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-700">
                <?php foreach ($withdrawals as $withdrawal): ?>
                  <tr class="hover:bg-gray-750">
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                      <?php echo date('M d, Y', strtotime($withdrawal['created_at'])); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                      $<?php echo number_format($withdrawal['amount'], 2); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-red-400">
                      $<?php echo number_format($withdrawal['tax_amount'], 2); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                      $<?php echo number_format($withdrawal['net_amount'], 2); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                      <?php echo ucfirst($withdrawal['payment_type']); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <?php
                      $status_color = '';
                      switch ($withdrawal['status']) {
                        case 'pending':
                          $status_color = 'bg-yellow-900 text-yellow-400';
                          break;
                        case 'approved':
                          $status_color = 'bg-green-900 text-green-400';
                          break;
                        case 'rejected':
                          $status_color = 'bg-red-900 text-red-400';
                          break;
                        default:
                          $status_color = 'bg-gray-700 text-gray-300';
                      }
                      ?>
                      <span class="px-2 py-1 text-xs rounded-full <?php echo $status_color; ?>">
                        <?php echo ucfirst($withdrawal['status']); ?>
                      </span>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </main>

  <?php include 'includes/footer.php'; ?>
  <?php include 'includes/js-links.php'; ?>

</body>

</html>