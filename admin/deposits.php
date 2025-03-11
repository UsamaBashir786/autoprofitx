<?php
// Start session
session_start();
include '../config/db.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
  header("Location: admin-login.php");
  exit();
}

// Initialize variables
$admin_id = $_SESSION['admin_id'];
$admin_name = $_SESSION['admin_name'] ?? 'Admin';
$success_message = "";
$error_message = "";

// Handle deposit status updates
if (isset($_POST['update_status']) && isset($_POST['deposit_id']) && isset($_POST['status'])) {
  $deposit_id = $_POST['deposit_id'];
  $status = $_POST['status'];
  $admin_notes = isset($_POST['admin_notes']) ? $_POST['admin_notes'] : '';

  // Begin transaction
  $conn->begin_transaction();

  try {
    // Update the deposit status
    $update_sql = "UPDATE deposits SET status = ?, admin_notes = ?, processed_at = NOW() WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("ssi", $status, $admin_notes, $deposit_id);
    $update_stmt->execute();
    $update_stmt->close();

    // If deposit is approved, update user's wallet balance and apply bonus if applicable
    if ($status == 'approved') {
      // Get deposit amount and user id
      $get_deposit_sql = "SELECT user_id, amount FROM deposits WHERE id = ?";
      $get_stmt = $conn->prepare($get_deposit_sql);
      $get_stmt->bind_param("i", $deposit_id);
      $get_stmt->execute();
      $deposit_result = $get_stmt->get_result();
      $deposit_data = $deposit_result->fetch_assoc();
      $get_stmt->close();

      if ($deposit_data) {
        $user_id = $deposit_data['user_id'];
        $amount = $deposit_data['amount'];
        $bonus_amount = 0;

        // Check if wallet exists
        $check_wallet_sql = "SELECT id, balance FROM wallets WHERE user_id = ?";
        $check_wallet_stmt = $conn->prepare($check_wallet_sql);
        $check_wallet_stmt->bind_param("i", $user_id);
        $check_wallet_stmt->execute();
        $wallet_result = $check_wallet_stmt->get_result();

        if ($wallet_result->num_rows > 0) {
          // Update existing wallet
          $wallet_data = $wallet_result->fetch_assoc();
          $new_balance = $wallet_data['balance'] + $amount;

          $update_wallet_sql = "UPDATE wallets SET balance = ?, updated_at = NOW() WHERE id = ?";
          $update_wallet_stmt = $conn->prepare($update_wallet_sql);
          $update_wallet_stmt->bind_param("di", $new_balance, $wallet_data['id']);
          $update_wallet_stmt->execute();
          $update_wallet_stmt->close();
        } else {
          // Create new wallet
          $create_wallet_sql = "INSERT INTO wallets (user_id, balance, created_at) VALUES (?, ?, NOW())";
          $create_wallet_stmt = $conn->prepare($create_wallet_sql);
          $create_wallet_stmt->bind_param("id", $user_id, $amount);
          $create_wallet_stmt->execute();
          $create_wallet_stmt->close();
        }

        $check_wallet_stmt->close();

        // Update transaction status
        $update_transaction_sql = "UPDATE transactions SET status = 'completed' 
                               WHERE user_id = ? AND transaction_type = 'deposit' AND reference_id LIKE 'DEP-%'
                               AND status = 'pending'
                               ORDER BY created_at DESC LIMIT 1";
        $update_trans_stmt = $conn->prepare($update_transaction_sql);
        $update_trans_stmt->bind_param("i", $user_id);
        $update_trans_stmt->execute();
        $update_trans_stmt->close();

        if ($amount >= 30) {  // Changed minimum threshold to $30
          // Check which bonus tier applies
          $bonus_query = "SELECT bonus_amount FROM deposit_bonus_tiers 
                         WHERE ? >= min_amount 
                         AND (max_amount IS NULL OR ? <= max_amount)
                         AND is_active = 1
                         ORDER BY min_amount DESC
                         LIMIT 1";

          $bonus_stmt = $conn->prepare($bonus_query);
          $bonus_stmt->bind_param("dd", $amount, $amount);
          $bonus_stmt->execute();
          $bonus_result = $bonus_stmt->get_result();

          if ($bonus_result->num_rows > 0) {
            $bonus_amount = $bonus_result->fetch_assoc()['bonus_amount'];

            // Update user wallet with bonus
            $wallet_update = "UPDATE wallets SET balance = balance + ?, updated_at = NOW() WHERE user_id = ?";
            $wallet_stmt = $conn->prepare($wallet_update);
            $wallet_stmt->bind_param("di", $bonus_amount, $user_id);
            $wallet_stmt->execute();
            $wallet_stmt->close();

            // Record bonus transaction
            $reference = "BONUS-" . rand(10000, 99999);
            $trans_query = "INSERT INTO transactions (
                          user_id, transaction_type, amount, status, description, reference_id
                        ) VALUES (?, 'deposit', ?, 'completed', ?, ?)";

            $description = "Bonus for deposit of $" . number_format($amount, 2);

            $trans_stmt = $conn->prepare($trans_query);
            $trans_stmt->bind_param("idss", $user_id, $bonus_amount, $description, $reference);
            $trans_stmt->execute();
            $trans_stmt->close();
          }
          $bonus_stmt->close();
        }

        // Success message with bonus information
        if ($bonus_amount > 0) {
          $success_message = "Deposit #$deposit_id approved successfully! User's wallet has been credited with $" . number_format($amount, 2) .
            " plus a bonus of $" . number_format($bonus_amount, 2) . ".";
        } else {
          $success_message = "Deposit status updated successfully!";
        }
      }
    } else {
      $success_message = "Deposit status updated successfully!";
    }

    $conn->commit();
  } catch (Exception $e) {
    $conn->rollback();
    $error_message = "Error updating deposit status: " . $e->getMessage();
  }
}

// Pagination settings
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Filters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build the query
$where_clauses = [];
$params = [];
$types = "";

if (!empty($status_filter)) {
  $where_clauses[] = "d.status = ?";
  $params[] = $status_filter;
  $types .= "s";
}

if (!empty($date_from)) {
  $where_clauses[] = "DATE(d.created_at) >= ?";
  $params[] = $date_from;
  $types .= "s";
}

if (!empty($date_to)) {
  $where_clauses[] = "DATE(d.created_at) <= ?";
  $params[] = $date_to;
  $types .= "s";
}

if (!empty($search)) {
  $where_clauses[] = "(u.full_name LIKE ? OR u.email LIKE ? OR d.transaction_id LIKE ?)";
  $search_param = "%$search%";
  $params[] = $search_param;
  $params[] = $search_param;
  $params[] = $search_param;
  $types .= "sss";
}

$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Count total deposits
$count_sql = "SELECT COUNT(*) as count FROM deposits d 
              JOIN users u ON d.user_id = u.id 
              $where_sql";

if (!empty($params)) {
  $count_stmt = $conn->prepare($count_sql);
  $count_stmt->bind_param($types, ...$params);
  $count_stmt->execute();
  $count_result = $count_stmt->get_result();
  $count_data = $count_result->fetch_assoc();
  $total_deposits = $count_data['count'];
  $count_stmt->close();
} else {
  $count_result = $conn->query($count_sql);
  $count_data = $count_result->fetch_assoc();
  $total_deposits = $count_data['count'];
}

$total_pages = ceil($total_deposits / $limit);

// Get deposits
$deposits_sql = "SELECT d.*, u.full_name, u.email, pm.payment_type as user_payment_type, 
                 apm.account_name as admin_account_name, apm.payment_type as admin_payment_type 
                 FROM deposits d 
                 JOIN users u ON d.user_id = u.id 
                 LEFT JOIN payment_methods pm ON d.payment_method_id = pm.id 
                 LEFT JOIN admin_payment_methods apm ON d.admin_payment_id = apm.id 
                 $where_sql 
                 ORDER BY d.created_at DESC 
                 LIMIT $offset, $limit";

$deposits = [];

if (!empty($params)) {
  $deposits_stmt = $conn->prepare($deposits_sql);
  $deposits_stmt->bind_param($types, ...$params);
  $deposits_stmt->execute();
  $deposits_result = $deposits_stmt->get_result();

  while ($row = $deposits_result->fetch_assoc()) {
    $deposits[] = $row;
  }

  $deposits_stmt->close();
} else {
  $deposits_result = $conn->query($deposits_sql);

  while ($row = $deposits_result->fetch_assoc()) {
    $deposits[] = $row;
  }
}

// Get total amount per status
$total_pending = 0;
$total_approved = 0;
$total_rejected = 0;

$totals_sql = "SELECT status, SUM(amount) as total FROM deposits GROUP BY status";
$totals_result = $conn->query($totals_sql);

while ($row = $totals_result->fetch_assoc()) {
  if ($row['status'] == 'pending') {
    $total_pending = $row['total'];
  } elseif ($row['status'] == 'approved') {
    $total_approved = $row['total'];
  } elseif ($row['status'] == 'rejected') {
    $total_rejected = $row['total'];
  }
}

// Helper function to calculate the bonus a deposit would receive
function getDepositBonus($conn, $amount)
{
  if ($amount < 30) {
    return 0;  // No bonus for deposits less than $30
  }

  $bonus_query = "SELECT bonus_amount FROM deposit_bonus_tiers 
                 WHERE ? >= min_amount 
                 AND (max_amount IS NULL OR ? <= max_amount)
                 AND is_active = 1
                 ORDER BY min_amount DESC
                 LIMIT 1";

  $stmt = $conn->prepare($bonus_query);
  $stmt->bind_param("dd", $amount, $amount);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows > 0) {
    return $result->fetch_assoc()['bonus_amount'];
  }

  return 0;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Deposits Management - AutoProftX Admin</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
  <style>
    /* Custom Scrollbar */
    ::-webkit-scrollbar {
      width: 8px;
      height: 8px;
    }

    ::-webkit-scrollbar-track {
      background: #1f2937;
    }

    ::-webkit-scrollbar-thumb {
      background: #4b5563;
      border-radius: 4px;
    }

    ::-webkit-scrollbar-thumb:hover {
      background: #f59e0b;
    }

    /* Active nav item */
    .nav-item.active {
      border-left: 3px solid #f59e0b;
      background-color: rgba(245, 158, 11, 0.1);
    }

    /* Gold gradient */
    .gold-gradient {
      background: linear-gradient(135deg, #f59e0b, #d97706);
    }
  </style>
</head>

<body class="bg-gray-900 text-gray-100 flex min-h-screen">
  <?php include 'includes/sidebar.php'; ?>

  <!-- Main Content -->
  <div class="flex-1 flex flex-col overflow-hidden">
    <!-- Top Navigation Bar -->
    <header class="bg-gray-800 border-b border-gray-700 shadow-md">
      <div class="flex items-center justify-between p-4">
        <!-- Mobile Menu Toggle -->
        <button id="mobile-menu-button" class="md:hidden text-gray-300 hover:text-white">
          <i class="fas fa-bars text-xl"></i>
        </button>

        <h1 class="text-xl font-bold text-white md:hidden">AutoProftX</h1>

        <!-- User Profile -->
        <div class="flex items-center space-x-4">
          <div class="h-8 w-8 rounded-full bg-yellow-500 flex items-center justify-center text-black font-bold">
            <?php echo strtoupper(substr($admin_name, 0, 1)); ?>
          </div>
          <span class="ml-2 hidden sm:inline-block"><?php echo htmlspecialchars($admin_name); ?></span>
        </div>
      </div>
    </header>

    <!-- Mobile Sidebar -->
    <div id="mobile-sidebar" class="fixed inset-0 bg-gray-900 bg-opacity-90 z-50 md:hidden transform -translate-x-full transition-transform duration-300">
      <div class="flex flex-col h-full bg-gray-800 w-64 py-8 px-6">
        <div class="flex justify-between items-center mb-8">
          <div class="flex items-center">
            <i class="fas fa-gem text-yellow-500 text-xl mr-2"></i>
            <span class="text-xl font-bold text-yellow-500">AutoProftX</span>
          </div>
          <button id="close-sidebar" class="text-gray-300 hover:text-white">
            <i class="fas fa-times text-xl"></i>
          </button>
        </div>

        <nav class="space-y-2">
          <a href="index.php" class="nav-item flex items-center px-4 py-3 text-gray-300 hover:text-white rounded transition duration-200">
            <i class="fas fa-tachometer-alt w-6"></i>
            <span>Dashboard</span>
          </a>
          <a href="users.php" class="nav-item flex items-center px-4 py-3 text-gray-300 hover:text-white rounded transition duration-200">
            <i class="fas fa-users w-6"></i>
            <span>Users</span>
          </a>
          <a href="deposits.php" class="nav-item active flex items-center px-4 py-3 text-gray-300 hover:text-white rounded transition duration-200">
            <i class="fas fa-money-bill-wave w-6"></i>
            <span>Deposits</span>
          </a>
          <a href="investments.php" class="nav-item flex items-center px-4 py-3 text-gray-300 hover:text-white rounded transition duration-200">
            <i class="fas fa-chart-line w-6"></i>
            <span>Investments</span>
          </a>
          <a href="staking.php" class="nav-item flex items-center px-4 py-3 text-gray-300 hover:text-white rounded transition duration-200">
            <i class="fas fa-landmark w-6"></i>
            <span>Staking</span>
          </a>
          <a href="payment-methods.php" class="nav-item flex items-center px-4 py-3 text-gray-300 hover:text-white rounded transition duration-200">
            <i class="fas fa-credit-card w-6"></i>
            <span>Payment Methods</span>
          </a>
          <a href="settings.php" class="nav-item flex items-center px-4 py-3 text-gray-300 hover:text-white rounded transition duration-200">
            <i class="fas fa-cog w-6"></i>
            <span>Settings</span>
          </a>
        </nav>

        <div class="mt-auto">
          <a href="admin-logout.php" class="flex items-center px-4 py-3 text-gray-300 hover:text-white rounded transition duration-200">
            <i class="fas fa-sign-out-alt w-6"></i>
            <span>Logout</span>
          </a>
        </div>
      </div>
    </div>

    <!-- Main Content Area -->
    <main class="flex-1 overflow-y-auto p-4 bg-gray-900">
      <!-- Page Title -->
      <div class="mb-6">
        <h2 class="text-2xl font-bold">Deposits Management</h2>
        <p class="text-gray-400">Manage and review all deposit requests</p>
      </div>

      <!-- Notification Messages -->
      <?php if (!empty($success_message)): ?>
        <div class="bg-green-900 bg-opacity-50 text-green-200 p-4 rounded-md mb-6 flex items-start">
          <i class="fas fa-check-circle mt-1 mr-3"></i>
          <span><?php echo $success_message; ?></span>
        </div>
      <?php endif; ?>

      <?php if (!empty($error_message)): ?>
        <div class="bg-red-900 bg-opacity-50 text-red-200 p-4 rounded-md mb-6 flex items-start">
          <i class="fas fa-exclamation-circle mt-1 mr-3"></i>
          <span><?php echo $error_message; ?></span>
        </div>
      <?php endif; ?>

      <!-- Stats Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-6">
        <!-- Pending Deposits -->
        <div class="bg-gray-800 rounded-lg p-6 border border-gray-700 shadow-lg">
          <div class="flex justify-between">
            <div>
              <p class="text-gray-400 text-sm">Pending Deposits</p>
              <h3 class="text-2xl font-bold mt-1"><?php echo number_format($total_pending, 0); ?></h3>
            </div>
            <div class="h-12 w-12 rounded-lg bg-yellow-500 flex items-center justify-center">
              <i class="fas fa-clock text-black"></i>
            </div>
          </div>
          <div class="mt-4">
            <a href="deposits.php?status=pending" class="text-yellow-500 hover:text-yellow-400 text-sm">
              View pending deposits <i class="fas fa-arrow-right ml-1"></i>
            </a>
          </div>
        </div>

        <!-- Approved Deposits -->
        <div class="bg-gray-800 rounded-lg p-6 border border-gray-700 shadow-lg">
          <div class="flex justify-between">
            <div>
              <p class="text-gray-400 text-sm">Approved Deposits</p>
              <h3 class="text-2xl font-bold mt-1"><?php echo number_format($total_approved, 0); ?></h3>
            </div>
            <div class="h-12 w-12 rounded-lg bg-green-500 flex items-center justify-center">
              <i class="fas fa-check text-black"></i>
            </div>
          </div>
          <div class="mt-4">
            <a href="deposits.php?status=approved" class="text-green-500 hover:text-green-400 text-sm">
              View approved deposits <i class="fas fa-arrow-right ml-1"></i>
            </a>
          </div>
        </div>

        <!-- Rejected Deposits -->
        <div class="bg-gray-800 rounded-lg p-6 border border-gray-700 shadow-lg">
          <div class="flex justify-between">
            <div>
              <p class="text-gray-400 text-sm">Rejected Deposits</p>
              <h3 class="text-2xl font-bold mt-1"><?php echo number_format($total_rejected, 0); ?></h3>
            </div>
            <div class="h-12 w-12 rounded-lg bg-red-500 flex items-center justify-center">
              <i class="fas fa-times text-black"></i>
            </div>
          </div>
          <div class="mt-4">
            <a href="deposits.php?status=rejected" class="text-red-500 hover:text-red-400 text-sm">
              View rejected deposits <i class="fas fa-arrow-right ml-1"></i>
            </a>
          </div>
        </div>
      </div>
      <!-- Filters Section -->
      <div class="bg-gray-800 rounded-lg border border-gray-700 p-6 mb-6">
        <h3 class="text-lg font-bold mb-4">Filter Deposits</h3>

        <form action="deposits.php" method="GET" class="space-y-4 md:space-y-0 md:grid md:grid-cols-4 md:gap-4">
          <!-- Status Filter -->
          <div>
            <label for="status" class="block text-sm font-medium text-gray-300 mb-2">Status</label>
            <select id="status" name="status" class="bg-gray-700 focus:ring-yellow-500 focus:border-yellow-500 block w-full rounded-md border-gray-600 text-white py-2 px-3">
              <option value="">All Statuses</option>
              <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
              <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>Approved</option>
              <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
            </select>
          </div>

          <!-- Date From -->
          <div>
            <label for="date_from" class="block text-sm font-medium text-gray-300 mb-2">Date From</label>
            <input type="text" id="date_from" name="date_from" value="<?php echo $date_from; ?>" class="bg-gray-700 focus:ring-yellow-500 focus:border-yellow-500 block w-full rounded-md border-gray-600 text-white py-2 px-3" placeholder="YYYY-MM-DD">
          </div>

          <!-- Date To -->
          <div>
            <label for="date_to" class="block text-sm font-medium text-gray-300 mb-2">Date To</label>
            <input type="text" id="date_to" name="date_to" value="<?php echo $date_to; ?>" class="bg-gray-700 focus:ring-yellow-500 focus:border-yellow-500 block w-full rounded-md border-gray-600 text-white py-2 px-3" placeholder="YYYY-MM-DD">
          </div>

          <!-- Search -->
          <div>
            <label for="search" class="block text-sm font-medium text-gray-300 mb-2">Search</label>
            <div class="relative">
              <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" class="bg-gray-700 focus:ring-yellow-500 focus:border-yellow-500 block w-full rounded-md border-gray-600 text-white py-2 pl-10 pr-3" placeholder="Name, Email, Tx ID">
              <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <i class="fas fa-search text-gray-400"></i>
              </div>
            </div>
          </div>

          <!-- Filter Button -->
          <div class="md:col-span-4 mt-4 md:mt-2 flex justify-end">
            <button type="submit" class="bg-yellow-500 hover:bg-yellow-600 text-black font-medium py-2 px-4 rounded-md transition duration-200">
              <i class="fas fa-filter mr-2"></i>Apply Filters
            </button>

            <?php if (!empty($status_filter) || !empty($date_from) || !empty($date_to) || !empty($search)): ?>
              <a href="deposits.php" class="ml-3 bg-gray-700 hover:bg-gray-600 text-white font-medium py-2 px-4 rounded-md transition duration-200">
                <i class="fas fa-times mr-2"></i>Clear Filters
              </a>
            <?php endif; ?>
          </div>
        </form>
      </div>
      <!-- Deposits Table -->
      <div class="w-full bg-gray-800 rounded-lg border border-gray-700 shadow-lg overflow-hidden">
        <div class="p-6 border-b border-gray-700">
          <h3 class="text-lg font-bold">Deposits List</h3>
          <p class="text-gray-400 text-sm mt-1">Showing <?php echo min($total_deposits, $limit); ?> of <?php echo $total_deposits; ?> deposits</p>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full">
            <thead class="bg-gray-700">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">User</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Amount</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Payment Method</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Date</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-700">
              <?php if (empty($deposits)): ?>
                <tr>
                  <td colspan="6" class="px-6 py-4 text-center text-gray-400">No deposits found</td>
                </tr>
              <?php else: ?>
                <?php foreach ($deposits as $deposit): ?>
                  <tr class="hover:bg-gray-700">
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="flex items-center">
                        <div class="h-8 w-8 rounded-full bg-gray-600 flex items-center justify-center text-sm font-medium">
                          <?php echo strtoupper(substr($deposit['full_name'], 0, 1)); ?>
                        </div>
                        <div class="ml-3">
                          <div class="text-sm font-medium"><?php echo htmlspecialchars($deposit['full_name']); ?></div>
                          <div class="text-xs text-gray-400"><?php echo htmlspecialchars($deposit['email']); ?></div>
                        </div>
                      </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium"><?php echo number_format($deposit['amount'], 0); ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                      <span class="flex items-center">
                        Binance
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mx-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                        TRC20
                      </span>
                      <?php if (!empty($deposit['transaction_id'])): ?>
                        <div class="text-xs text-blue-400 mt-1">
                          <span class="font-mono">TX: <?php echo substr($deposit['transaction_id'], 0, 8) . '...' . substr($deposit['transaction_id'], -8); ?></span>
                        </div>
                      <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300"><?php echo date('M d, Y H:i', strtotime($deposit['created_at'])); ?></td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <?php if ($deposit['status'] == 'pending'): ?>
                        <span class="px-2 py-1 text-xs rounded-full bg-yellow-900 text-yellow-400 flex items-center">
                          <span class="h-1.5 w-1.5 bg-yellow-400 rounded-full mr-1"></span>
                          Pending
                        </span>
                      <?php elseif ($deposit['status'] == 'approved'): ?>
                        <span class="px-2 py-1 text-xs rounded-full bg-green-900 text-green-400 flex items-center">
                          <span class="h-1.5 w-1.5 bg-green-400 rounded-full mr-1"></span>
                          Approved
                        </span>
                      <?php elseif ($deposit['status'] == 'rejected'): ?>
                        <span class="px-2 py-1 text-xs rounded-full bg-red-900 text-red-400 flex items-center">
                          <span class="h-1.5 w-1.5 bg-red-400 rounded-full mr-1"></span>
                          Rejected
                        </span>
                      <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                      <button class="view-deposit text-blue-400 hover:text-blue-300 mr-3"
                        data-id="<?php echo $deposit['id']; ?>"
                        data-user="<?php echo htmlspecialchars($deposit['full_name'] ?? ''); ?>"
                        data-email="<?php echo htmlspecialchars($deposit['email'] ?? ''); ?>"
                        data-amount="<?php echo number_format($deposit['amount'] ?? 0, 0); ?>"
                        data-method="Binance"
                        data-admin-method="TRC20"
                        data-transaction-id="<?php echo htmlspecialchars($deposit['transaction_id'] ?? ''); ?>"
                        data-created="<?php echo date('M d, Y H:i', strtotime($deposit['created_at'])); ?>"
                        data-proof="<?php echo htmlspecialchars($deposit['proof_file'] ?? ''); ?>"
                        data-status="<?php echo $deposit['status']; ?>"
                        data-notes="<?php echo htmlspecialchars($deposit['notes'] ?? ''); ?>"
                        data-admin-notes="<?php echo htmlspecialchars($deposit['admin_notes'] ?? ''); ?>">
                        <i class="fas fa-eye"></i>
                      </button>

                      <?php if ($deposit['status'] == 'pending'): ?>
                        <button class="approve-deposit text-green-400 hover:text-green-300 mr-3" data-id="<?php echo $deposit['id']; ?>">
                          <i class="fas fa-check"></i>
                        </button>
                        <button class="reject-deposit text-red-400 hover:text-red-300" data-id="<?php echo $deposit['id']; ?>">
                          <i class="fas fa-times"></i>
                        </button>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
      <!-- Pagination -->
      <?php if ($total_pages > 1): ?>
        <div class="px-6 py-4 bg-gray-800 border-t border-gray-700">
          <div class="flex justify-between items-center">
            <div class="text-sm text-gray-400">
              Showing <?php echo ($page - 1) * $limit + 1; ?> to <?php echo min($page * $limit, $total_deposits); ?> of <?php echo $total_deposits; ?> deposits
            </div>
            <div class="flex space-x-2">
              <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?><?php echo !empty($status_filter) ? '&status=' . $status_filter : ''; ?><?php echo !empty($date_from) ? '&date_from=' . $date_from : ''; ?><?php echo !empty($date_to) ? '&date_to=' . $date_to : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="px-3 py-1 bg-gray-700 hover:bg-gray-600 rounded-md text-sm text-gray-300">
                  Previous
                </a>
              <?php endif; ?>

              <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                <a href="?page=<?php echo $i; ?><?php echo !empty($status_filter) ? '&status=' . $status_filter : ''; ?><?php echo !empty($date_from) ? '&date_from=' . $date_from : ''; ?><?php echo !empty($date_to) ? '&date_to=' . $date_to : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="px-3 py-1 <?php echo $i == $page ? 'bg-yellow-500 text-black' : 'bg-gray-700 hover:bg-gray-600 text-gray-300'; ?> rounded-md text-sm">
                  <?php echo $i; ?>
                </a>
              <?php endfor; ?>

              <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?><?php echo !empty($status_filter) ? '&status=' . $status_filter : ''; ?><?php echo !empty($date_from) ? '&date_from=' . $date_from : ''; ?><?php echo !empty($date_to) ? '&date_to=' . $date_to : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="px-3 py-1 bg-gray-700 hover:bg-gray-600 rounded-md text-sm text-gray-300">
                  Next
                </a>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </main>
  </div>

  <!-- View Deposit Modal -->
  <!-- View Deposit Modal -->
  <div id="viewDepositModal" class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50 hidden">
    <div class="bg-gray-800 rounded-lg w-full max-w-2xl max-h-screen overflow-y-auto">
      <!-- Modal Header -->
      <div class="flex justify-between items-center p-6 border-b border-gray-700">
        <h3 class="text-xl font-bold">Deposit Details</h3>
        <button id="closeViewModal" class="text-gray-400 hover:text-white">
          <i class="fas fa-times text-xl"></i>
        </button>
      </div>

      <!-- Modal Content -->
      <div class="p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
          <!-- User Info -->
          <div>
            <h4 class="text-sm font-medium text-gray-400 mb-2">User Information</h4>
            <div class="bg-gray-700 rounded-lg p-4">
              <div class="flex items-center mb-4">
                <div class="h-10 w-10 rounded-full bg-gray-600 flex items-center justify-center text-white font-bold mr-3" id="userInitial"></div>
                <div>
                  <div class="font-medium" id="userName"></div>
                  <div class="text-sm text-gray-400" id="userEmail"></div>
                </div>
              </div>
            </div>
          </div>

          <!-- Deposit Info -->
          <div>
            <h4 class="text-sm font-medium text-gray-400 mb-2">Deposit Information</h4>
            <div class="bg-gray-700 rounded-lg p-4">
              <div class="grid grid-cols-2 gap-2">
                <div class="text-sm text-gray-400">Amount:</div>
                <div class="text-sm font-bold text-right" id="depositAmount"></div>

                <div class="text-sm text-gray-400">Status:</div>
                <div class="text-right" id="depositStatus"></div>

                <div class="text-sm text-gray-400">Date:</div>
                <div class="text-sm text-right" id="depositDate"></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Payment Details -->
        <div class="mb-6">
          <h4 class="text-sm font-medium text-gray-400 mb-2">Payment Details</h4>
          <div class="bg-gray-700 rounded-lg p-4">
            <div class="grid grid-cols-2 gap-2">
              <div class="text-sm text-gray-400">Payment Method:</div>
              <div class="text-sm text-right"><span class="bg-blue-900 text-blue-400 px-2 py-1 text-xs rounded">Binance</span></div>

              <div class="text-sm text-gray-400">Deposit Type:</div>
              <div class="text-sm text-right">TRC20 USDT</div>

              <div class="text-sm text-gray-400">Transaction ID:</div>
              <div class="text-sm text-right font-mono text-blue-400 break-all" id="transactionId"></div>
            </div>
          </div>
        </div>

        <!-- Binance Address Copy -->
        <div class="mb-6" id="binanceAddressSection">
          <h4 class="text-sm font-medium text-gray-400 mb-2">TRC20 Address</h4>
          <div class="bg-gray-700 rounded-lg p-4">
            <div class="flex items-center justify-between mb-2">
              <span class="text-sm text-gray-400">Admin TRC20 Address:</span>
              <span class="text-sm text-right font-mono text-blue-400 break-all" id="adminTrc20Address">TPD4HY9QsWrK6H2qS7iJbh3TSEYWuMtLRQ</span>
            </div>
            <button onclick="copyToClipboard('TPD4HY9QsWrK6H2qS7iJbh3TSEYWuMtLRQ')" class="mt-2 w-full bg-blue-900 hover:bg-blue-800 text-blue-300 px-3 py-1.5 rounded text-xs flex items-center justify-center">
              <i class="fas fa-copy mr-2"></i> Copy TRC20 Address
            </button>
          </div>
        </div>

        <!-- Payment Proof -->
        <div class="mb-6">
          <h4 class="text-sm font-medium text-gray-400 mb-2">Payment Proof</h4>
          <div class="bg-gray-700 rounded-lg p-4 text-center">
            <div id="proofPreview" class="mb-4"></div>
            <a id="downloadProof" href="#" class="text-blue-500 hover:text-blue-400 text-sm" target="_blank">
              <i class="fas fa-download mr-1"></i> Download Proof
            </a>
          </div>
        </div>

        <!-- Notes -->
        <div class="mb-6">
          <h4 class="text-sm font-medium text-gray-400 mb-2">User Notes</h4>
          <div class="bg-gray-700 rounded-lg p-4">
            <p id="userNotes" class="text-sm"></p>
          </div>
        </div>

        <!-- Admin Notes -->
        <div id="adminNotesSection">
          <h4 class="text-sm font-medium text-gray-400 mb-2">Admin Notes</h4>
          <div class="bg-gray-700 rounded-lg p-4">
            <p id="adminNotes" class="text-sm"></p>
          </div>
        </div>
      </div>
    </div>
  </div>


  <!-- Approve Deposit Modal -->
  <div id="approveDepositModal" class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50 hidden">
    <div class="bg-gray-800 rounded-lg w-full max-w-md">
      <!-- Modal Header -->
      <div class="flex justify-between items-center p-6 border-b border-gray-700">
        <h3 class="text-xl font-bold">Approve Deposit</h3>
        <button class="closeApproveModal text-gray-400 hover:text-white">
          <i class="fas fa-times text-xl"></i>
        </button>
      </div>

      <!-- Modal Content -->
      <div class="p-6">
        <form id="approveForm" method="POST">
          <input type="hidden" id="approveDepositId" name="deposit_id">
          <input type="hidden" name="status" value="approved">

          <div class="mb-6">
            <p class="text-gray-300 mb-4">Are you sure you want to approve this deposit? This will add the amount to the user's wallet balance.</p>

            <label for="approveNotes" class="block text-sm font-medium text-gray-400 mb-2">Admin Notes (Optional)</label>
            <textarea id="approveNotes" name="admin_notes" rows="3" class="bg-gray-700 focus:ring-yellow-500 focus:border-yellow-500 block w-full rounded-md border-gray-600 text-white" placeholder="Add any notes about this approval"></textarea>
          </div>

          <div class="flex justify-end space-x-4">
            <button type="button" class="closeApproveModal bg-gray-700 hover:bg-gray-600 text-white font-medium py-2 px-4 rounded-md transition duration-200">
              Cancel
            </button>
            <button type="submit" name="update_status" class="bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-md transition duration-200">
              Approve Deposit
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Reject Deposit Modal -->
  <div id="rejectDepositModal" class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50 hidden">
    <div class="bg-gray-800 rounded-lg w-full max-w-md">
      <!-- Modal Header -->
      <div class="flex justify-between items-center p-6 border-b border-gray-700">
        <h3 class="text-xl font-bold">Reject Deposit</h3>
        <button class="closeRejectModal text-gray-400 hover:text-white">
          <i class="fas fa-times text-xl"></i>
        </button>
      </div>

      <!-- Modal Content -->
      <div class="p-6">
        <form id="rejectForm" method="POST">
          <input type="hidden" id="rejectDepositId" name="deposit_id">
          <input type="hidden" name="status" value="rejected">

          <div class="mb-6">
            <p class="text-gray-300 mb-4">Are you sure you want to reject this deposit?</p>

            <label for="rejectNotes" class="block text-sm font-medium text-gray-400 mb-2">Admin Notes (Required)</label>
            <textarea id="rejectNotes" name="admin_notes" rows="3" required class="bg-gray-700 focus:ring-yellow-500 focus:border-yellow-500 block w-full rounded-md border-gray-600 text-white" placeholder="Please provide a reason for rejecting this deposit"></textarea>
          </div>

          <div class="flex justify-end space-x-4">
            <button type="button" class="closeRejectModal bg-gray-700 hover:bg-gray-600 text-white font-medium py-2 px-4 rounded-md transition duration-200">
              Cancel
            </button>
            <button type="submit" name="update_status" class="bg-red-600 hover:bg-red-700 text-white font-medium py-2 px-4 rounded-md transition duration-200">
              Reject Deposit
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Mobile sidebar toggle
      const mobileSidebar = document.getElementById('mobile-sidebar');
      const mobileMenuButton = document.getElementById('mobile-menu-button');
      const closeSidebarButton = document.getElementById('close-sidebar');

      if (mobileMenuButton && closeSidebarButton) {
        mobileMenuButton.addEventListener('click', function() {
          mobileSidebar.classList.remove('-translate-x-full');
        });

        closeSidebarButton.addEventListener('click', function() {
          mobileSidebar.classList.add('-translate-x-full');
        });
      }

      // Date pickers
      if (typeof flatpickr !== 'undefined') {
        flatpickr("#date_from", {
          dateFormat: "Y-m-d",
          altInput: true,
          altFormat: "F j, Y",
          theme: "dark"
        });

        flatpickr("#date_to", {
          dateFormat: "Y-m-d",
          altInput: true,
          altFormat: "F j, Y",
          theme: "dark"
        });
      }

      // View Deposit Modal
      const viewDepositModal = document.getElementById('viewDepositModal');
      const viewDepositBtns = document.querySelectorAll('.view-deposit');
      const closeViewModalBtn = document.getElementById('closeViewModal');

      if (viewDepositBtns.length > 0 && closeViewModalBtn) {
        viewDepositBtns.forEach(btn => {
          btn.addEventListener('click', function(event) {
            // Prevent default anchor behavior if it's a link
            event.preventDefault();

            // Prevent event from bubbling up
            event.stopPropagation();

            const id = this.getAttribute('data-id');
            const user = this.getAttribute('data-user') || 'Unknown';
            const email = this.getAttribute('data-email') || 'No email';
            const amount = this.getAttribute('data-amount') || '0';
            const method = this.getAttribute('data-method') || 'Unknown';
            const adminMethod = this.getAttribute('data-admin-method') || 'Unknown';
            const transactionId = this.getAttribute('data-transaction-id') || 'Not provided';
            const created = this.getAttribute('data-created') || 'Unknown';
            const proof = this.getAttribute('data-proof') || '';
            const status = this.getAttribute('data-status') || 'pending';
            const notes = this.getAttribute('data-notes') || 'No notes provided';
            const adminNotes = this.getAttribute('data-admin-notes') || 'No admin notes';

            console.log("Modal Data:", {
              id,
              user,
              email,
              amount,
              status
            });

            // Populate modal
            document.getElementById('userInitial').textContent = user.charAt(0).toUpperCase();
            document.getElementById('userName').textContent = user;
            document.getElementById('userEmail').textContent = email;
            document.getElementById('depositAmount').textContent = amount;
            document.getElementById('depositDate').textContent = created;
            document.getElementById('transactionId').textContent = transactionId || 'Not provided';

            // Show binance address section only if status is pending
            const binanceAddressSection = document.getElementById('binanceAddressSection');
            if (status === 'pending') {
              binanceAddressSection.classList.remove('hidden');
            } else {
              binanceAddressSection.classList.add('hidden');
            }

            document.getElementById('userNotes').textContent = notes;
            document.getElementById('adminNotes').textContent = adminNotes;

            // Set download link for proof
            const downloadLink = document.getElementById('downloadProof');
            if (proof && proof !== '') {
              downloadLink.href = '../uploads/payment_proofs/' + proof;
              downloadLink.classList.remove('hidden');
            } else {
              downloadLink.href = '#';
              downloadLink.classList.add('hidden');
            }

            // Show status with appropriate color
            const statusElement = document.getElementById('depositStatus');
            if (status === 'pending') {
              statusElement.innerHTML = '<span class="px-2 py-1 text-xs rounded-full bg-yellow-900 text-yellow-400 flex items-center"><span class="h-1.5 w-1.5 bg-yellow-400 rounded-full mr-1"></span>Pending</span>';
            } else if (status === 'approved') {
              statusElement.innerHTML = '<span class="px-2 py-1 text-xs rounded-full bg-green-900 text-green-400 flex items-center"><span class="h-1.5 w-1.5 bg-green-400 rounded-full mr-1"></span>Approved</span>';
            } else if (status === 'rejected') {
              statusElement.innerHTML = '<span class="px-2 py-1 text-xs rounded-full bg-red-900 text-red-400 flex items-center"><span class="h-1.5 w-1.5 bg-red-400 rounded-full mr-1"></span>Rejected</span>';
            }

            // Show admin notes section only if not pending
            const adminNotesSection = document.getElementById('adminNotesSection');
            if (status === 'pending') {
              adminNotesSection.classList.add('hidden');
            } else {
              adminNotesSection.classList.remove('hidden');
            }

            // Show proof preview (if image)
            const proofPreview = document.getElementById('proofPreview');
            if (proof && proof !== '') {
              const fileExt = proof.split('.').pop().toLowerCase();

              if (fileExt === 'jpg' || fileExt === 'jpeg' || fileExt === 'png' || fileExt === 'gif') {
                proofPreview.innerHTML = `<img src="../uploads/payment_proofs/${proof}" class="max-w-full max-h-64 mx-auto" alt="Payment Proof">`;
              } else if (fileExt === 'pdf') {
                proofPreview.innerHTML = `<div class="flex items-center justify-center p-4 bg-gray-900 rounded-lg">
            <i class="fas fa-file-pdf text-red-500 text-4xl mr-3"></i>
            <span class="text-gray-300">PDF Document</span>
          </div>`;
              } else {
                proofPreview.innerHTML = `<div class="flex items-center justify-center p-4 bg-gray-900 rounded-lg">
            <i class="fas fa-file text-gray-500 text-4xl mr-3"></i>
            <span class="text-gray-300">File</span>
          </div>`;
              }
            } else {
              proofPreview.innerHTML = `<div class="flex items-center justify-center p-4 bg-gray-900 rounded-lg">
          <span class="text-gray-300">No proof file uploaded</span>
        </div>`;
            }

            // Force a repaint to help with any rendering issues
            setTimeout(function() {
              // Show modal
              viewDepositModal.classList.remove('hidden');
              console.log("Modal opened");
            }, 50);
          });
        });

        // Handle modal close button
        closeViewModalBtn.addEventListener('click', function(event) {
          event.preventDefault();
          event.stopPropagation();
          viewDepositModal.classList.add('hidden');
          console.log("Modal closed via button");
        });

        // Stop propagation on the modal content to prevent clicks inside from closing
        const modalContent = viewDepositModal.querySelector('.bg-gray-800');
        if (modalContent) {
          modalContent.addEventListener('click', function(event) {
            event.stopPropagation();
          });
        }
      } else {
        console.error("View deposit buttons or close button not found");
      }

      // Approve Deposit Modal
      const approveDepositModal = document.getElementById('approveDepositModal');
      const approveBtns = document.querySelectorAll('.approve-deposit');
      const closeApproveBtns = document.querySelectorAll('.closeApproveModal');

      if (approveBtns.length > 0 && closeApproveBtns.length > 0 && approveDepositModal) {
        approveBtns.forEach(btn => {
          btn.addEventListener('click', function(event) {
            event.preventDefault();
            event.stopPropagation();

            const id = this.getAttribute('data-id');
            document.getElementById('approveDepositId').value = id;
            approveDepositModal.classList.remove('hidden');
            console.log("Approve modal opened for ID:", id);
          });
        });

        closeApproveBtns.forEach(btn => {
          btn.addEventListener('click', function(event) {
            event.preventDefault();
            event.stopPropagation();
            approveDepositModal.classList.add('hidden');
          });
        });

        // Stop propagation on modal content
        const approveModalContent = approveDepositModal.querySelector('.bg-gray-800');
        if (approveModalContent) {
          approveModalContent.addEventListener('click', function(event) {
            event.stopPropagation();
          });
        }
      }

      // Reject Deposit Modal
      const rejectDepositModal = document.getElementById('rejectDepositModal');
      const rejectBtns = document.querySelectorAll('.reject-deposit');
      const closeRejectBtns = document.querySelectorAll('.closeRejectModal');

      if (rejectBtns.length > 0 && closeRejectBtns.length > 0 && rejectDepositModal) {
        rejectBtns.forEach(btn => {
          btn.addEventListener('click', function(event) {
            event.preventDefault();
            event.stopPropagation();

            const id = this.getAttribute('data-id');
            document.getElementById('rejectDepositId').value = id;
            rejectDepositModal.classList.remove('hidden');
            console.log("Reject modal opened for ID:", id);
          });
        });

        closeRejectBtns.forEach(btn => {
          btn.addEventListener('click', function(event) {
            event.preventDefault();
            event.stopPropagation();
            rejectDepositModal.classList.add('hidden');
          });
        });

        // Stop propagation on modal content
        const rejectModalContent = rejectDepositModal.querySelector('.bg-gray-800');
        if (rejectModalContent) {
          rejectModalContent.addEventListener('click', function(event) {
            event.stopPropagation();
          });
        }
      }

      // Only close modals when specifically clicking the backdrop
      if (viewDepositModal) {
        viewDepositModal.addEventListener('click', function(event) {
          if (event.target === viewDepositModal) {
            viewDepositModal.classList.add('hidden');
            console.log("Modal closed via backdrop");
          }
        });
      }

      if (approveDepositModal) {
        approveDepositModal.addEventListener('click', function(event) {
          if (event.target === approveDepositModal) {
            approveDepositModal.classList.add('hidden');
          }
        });
      }

      if (rejectDepositModal) {
        rejectDepositModal.addEventListener('click', function(event) {
          if (event.target === rejectDepositModal) {
            rejectDepositModal.classList.add('hidden');
          }
        });
      }

      // Form submission handlers
      const approveForm = document.getElementById('approveForm');
      const rejectForm = document.getElementById('rejectForm');

      if (approveForm) {
        approveForm.addEventListener('submit', function() {
          console.log("Submitting approve form");
        });
      }

      if (rejectForm) {
        rejectForm.addEventListener('submit', function() {
          console.log("Submitting reject form");
        });
      }
    });

    // Copy TRC20 address to clipboard
    function copyToClipboard(text) {
      const textarea = document.createElement('textarea');
      textarea.value = text;
      document.body.appendChild(textarea);
      textarea.select();
      document.execCommand('copy');
      document.body.removeChild(textarea);

      // Show toast notification
      const toast = document.createElement('div');
      toast.className = 'fixed bottom-4 right-4 bg-blue-900 text-blue-200 py-2 px-4 rounded shadow-lg flex items-center';
      toast.innerHTML = '<i class="fas fa-check-circle mr-2"></i> TRC20 address copied!';
      document.body.appendChild(toast);

      // Remove toast after 3 seconds
      setTimeout(() => {
        toast.remove();
      }, 3000);
    }
  </script>
</body>

</html>