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

// Include staking functions
include '../user/includes/staking-functions.php';

// Handle creating new staking plan
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_plan'])) {
  $plan_data = [
    'name' => $_POST['name'] ?? '',
    'description' => $_POST['description'] ?? '',
    'duration_days' => $_POST['duration_days'] ?? 0,
    'apy_rate' => $_POST['apy_rate'] ?? 0,
    'min_amount' => $_POST['min_amount'] ?? 0,
    'max_amount' => !empty($_POST['max_amount']) ? $_POST['max_amount'] : null,
    'early_withdrawal_fee' => $_POST['early_withdrawal_fee'] ?? 10,
    'is_active' => isset($_POST['is_active']) ? 1 : 0
  ];

  // SQL for inserting new plan
  $sql = "INSERT INTO staking_plans (name, description, duration_days, apy_rate, min_amount, max_amount, early_withdrawal_fee, is_active)
          VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

  $stmt = $conn->prepare($sql);
  $stmt->bind_param(
    "ssiididi",
    $plan_data['name'],
    $plan_data['description'],
    $plan_data['duration_days'],
    $plan_data['apy_rate'],
    $plan_data['min_amount'],
    $plan_data['max_amount'],
    $plan_data['early_withdrawal_fee'],
    $plan_data['is_active']
  );

  if ($stmt->execute()) {
    $success_message = "Staking plan created successfully!";
  } else {
    $error_message = "Error creating staking plan: " . $stmt->error;
  }

  $stmt->close();
}

// Handle updating staking plan
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_plan'])) {
  $plan_id = $_POST['plan_id'] ?? 0;
  $is_active = isset($_POST['is_active']) ? 1 : 0;

  $sql = "UPDATE staking_plans SET 
            name = ?, 
            description = ?, 
            duration_days = ?, 
            apy_rate = ?, 
            min_amount = ?, 
            max_amount = ?, 
            early_withdrawal_fee = ?, 
            is_active = ?
          WHERE id = ?";

  $stmt = $conn->prepare($sql);
  $max_amount = !empty($_POST['max_amount']) ? $_POST['max_amount'] : null;

  $stmt->bind_param(
    "ssiididi",
    $_POST['name'],
    $_POST['description'],
    $_POST['duration_days'],
    $_POST['apy_rate'],
    $_POST['min_amount'],
    $max_amount,
    $_POST['early_withdrawal_fee'],
    $is_active,
    $plan_id
  );

  if ($stmt->execute()) {
    $success_message = "Staking plan updated successfully!";
  } else {
    $error_message = "Error updating staking plan: " . $stmt->error;
  }

  $stmt->close();
}

// Handle deleting staking plan
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_plan'])) {
  $plan_id = $_POST['plan_id'] ?? 0;

  // Check if there are active stakes using this plan
  $check_sql = "SELECT COUNT(*) AS count FROM stakes WHERE plan_id = ? AND status = 'active'";
  $check_stmt = $conn->prepare($check_sql);
  $check_stmt->bind_param("i", $plan_id);
  $check_stmt->execute();
  $result = $check_stmt->get_result();
  $data = $result->fetch_assoc();
  $check_stmt->close();

  if ($data['count'] > 0) {
    $error_message = "Cannot delete this plan. There are " . $data['count'] . " active stakes using it.";
  } else {
    // Safe to delete
    $sql = "DELETE FROM staking_plans WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $plan_id);

    if ($stmt->execute()) {
      $success_message = "Staking plan deleted successfully!";
    } else {
      $error_message = "Error deleting staking plan: " . $stmt->error;
    }

    $stmt->close();
  }
}

// Get all staking plans
$plans_sql = "SELECT * FROM staking_plans ORDER BY duration_days ASC";
$plans_result = $conn->query($plans_sql);
$staking_plans = [];

while ($row = $plans_result->fetch_assoc()) {
  $staking_plans[] = $row;
}

// Pagination settings for stakes
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
  $where_clauses[] = "s.status = ?";
  $params[] = $status_filter;
  $types .= "s";
}

if (!empty($date_from)) {
  $where_clauses[] = "DATE(s.start_date) >= ?";
  $params[] = $date_from;
  $types .= "s";
}

if (!empty($date_to)) {
  $where_clauses[] = "DATE(s.start_date) <= ?";
  $params[] = $date_to;
  $types .= "s";
}

if (!empty($search)) {
  $where_clauses[] = "(u.full_name LIKE ? OR u.email LIKE ? OR s.stake_id LIKE ?)";
  $search_param = "%$search%";
  $params[] = $search_param;
  $params[] = $search_param;
  $params[] = $search_param;
  $types .= "sss";
}

$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Count total stakes
$count_sql = "SELECT COUNT(*) as count FROM stakes s 
              JOIN users u ON s.user_id = u.id 
              JOIN staking_plans sp ON s.plan_id = sp.id
              $where_sql";

if (!empty($params)) {
  $count_stmt = $conn->prepare($count_sql);
  $count_stmt->bind_param($types, ...$params);
  $count_stmt->execute();
  $count_result = $count_stmt->get_result();
  $count_data = $count_result->fetch_assoc();
  $total_stakes = $count_data['count'];
  $count_stmt->close();
} else {
  $count_result = $conn->query($count_sql);
  $count_data = $count_result->fetch_assoc();
  $total_stakes = $count_data['count'];
}

$total_pages = ceil($total_stakes / $limit);

// Get stakes
$stakes_sql = "SELECT s.*, u.full_name, u.email, sp.name as plan_name, sp.apy_rate, sp.duration_days
              FROM stakes s 
              JOIN users u ON s.user_id = u.id 
              JOIN staking_plans sp ON s.plan_id = sp.id
              $where_sql 
              ORDER BY s.created_at DESC 
              LIMIT $offset, $limit";

$stakes = [];

if (!empty($params)) {
  $stakes_stmt = $conn->prepare($stakes_sql);
  $stakes_stmt->bind_param($types, ...$params);
  $stakes_stmt->execute();
  $stakes_result = $stakes_stmt->get_result();

  while ($row = $stakes_result->fetch_assoc()) {
    $stakes[] = $row;
  }

  $stakes_stmt->close();
} else {
  $stakes_result = $conn->query($stakes_sql);

  while ($row = $stakes_result->fetch_assoc()) {
    $stakes[] = $row;
  }
}

// Get total amount per status
$total_active = 0;
$total_completed = 0;
$total_withdrawn = 0;
$total_profit = 0;

$totals_sql = "SELECT status, SUM(amount) as total_amount, SUM(expected_return - amount) as total_profit FROM stakes GROUP BY status";
$totals_result = $conn->query($totals_sql);

while ($row = $totals_result->fetch_assoc()) {
  if ($row['status'] == 'active') {
    $total_active = $row['total_amount'];
  } elseif ($row['status'] == 'completed') {
    $total_completed = $row['total_amount'];
    $total_profit += $row['total_profit'];
  } elseif ($row['status'] == 'withdrawn') {
    $total_withdrawn = $row['total_amount'];
  }
}

// Check for matured stakes (this should be in a cron job in production)
$processed_count = processCompletedStakes();

// Auto-refresh page if stakes were processed
if ($processed_count > 0) {
  echo '<meta http-equiv="refresh" content="1;url=stak.php">';
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Staking Management - AutoProftX Admin</title>
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
  <!-- Sidebar -->
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
          <a href="deposits.php" class="nav-item flex items-center px-4 py-3 text-gray-300 hover:text-white rounded transition duration-200">
            <i class="fas fa-money-bill-wave w-6"></i>
            <span>Deposits</span>
          </a>
          <a href="investments.php" class="nav-item flex items-center px-4 py-3 text-gray-300 hover:text-white rounded transition duration-200">
            <i class="fas fa-chart-line w-6"></i>
            <span>Investments</span>
          </a>
          <a href="staking.php" class="nav-item active flex items-center px-4 py-3 text-gray-300 hover:text-white rounded transition duration-200">
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
        <h2 class="text-2xl font-bold">Staking Management</h2>
        <p class="text-gray-400">Manage staking plans and monitor user stakes</p>
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
      <div class="grid grid-cols-1 sm:grid-cols-4 gap-6 mb-6">
        <!-- Active Stakes -->
        <div class="bg-gray-800 rounded-lg p-6 border border-gray-700 shadow-lg">
          <div class="flex justify-between">
            <div>
              <p class="text-gray-400 text-sm">Active Stakes</p>
              <h3 class="text-2xl font-bold mt-1"><?php echo number_format($total_active, 0); ?></h3>
            </div>
            <div class="h-12 w-12 rounded-lg bg-blue-500 flex items-center justify-center">
              <i class="fas fa-lock text-black"></i>
            </div>
          </div>
          <div class="mt-4">
            <a href="staking.php?status=active" class="text-blue-500 hover:text-blue-400 text-sm">
              View active stakes <i class="fas fa-arrow-right ml-1"></i>
            </a>
          </div>
        </div>

        <!-- Completed Stakes -->
        <div class="bg-gray-800 rounded-lg p-6 border border-gray-700 shadow-lg">
          <div class="flex justify-between">
            <div>
              <p class="text-gray-400 text-sm">Completed Stakes</p>
              <h3 class="text-2xl font-bold mt-1"><?php echo number_format($total_completed, 0); ?></h3>
            </div>
            <div class="h-12 w-12 rounded-lg bg-green-500 flex items-center justify-center">
              <i class="fas fa-check text-black"></i>
            </div>
          </div>
          <div class="mt-4">
            <a href="staking.php?status=completed" class="text-green-500 hover:text-green-400 text-sm">
              View completed stakes <i class="fas fa-arrow-right ml-1"></i>
            </a>
          </div>
        </div>

        <!-- Early Withdrawals -->
        <div class="bg-gray-800 rounded-lg p-6 border border-gray-700 shadow-lg">
          <div class="flex justify-between">
            <div>
              <p class="text-gray-400 text-sm">Early Withdrawals</p>
              <h3 class="text-2xl font-bold mt-1"><?php echo number_format($total_withdrawn, 0); ?></h3>
            </div>
            <div class="h-12 w-12 rounded-lg bg-yellow-500 flex items-center justify-center">
              <i class="fas fa-undo text-black"></i>
            </div>
          </div>
          <div class="mt-4">
            <a href="staking.php?status=withdrawn" class="text-yellow-500 hover:text-yellow-400 text-sm">
              View early withdrawals <i class="fas fa-arrow-right ml-1"></i>
            </a>
          </div>
        </div>

        <!-- Total Profit -->
        <div class="bg-gray-800 rounded-lg p-6 border border-gray-700 shadow-lg">
          <div class="flex justify-between">
            <div>
              <p class="text-gray-400 text-sm">Total Profit Paid</p>
              <h3 class="text-2xl font-bold mt-1"><?php echo number_format($total_profit, 0); ?></h3>
            </div>
            <div class="h-12 w-12 rounded-lg bg-purple-500 flex items-center justify-center">
              <i class="fas fa-coins text-black"></i>
            </div>
          </div>
          <div class="mt-4">
            <a href="staking.php" class="text-purple-500 hover:text-purple-400 text-sm">
              View all stakes <i class="fas fa-arrow-right ml-1"></i>
            </a>
          </div>
        </div>
      </div>

      <!-- Plan Management Section -->
      <div class="mb-6">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-xl font-bold">Staking Plans</h3>
          <button onclick="document.getElementById('newPlanModal').classList.remove('hidden')" class="bg-yellow-500 hover:bg-yellow-600 text-black font-medium py-2 px-4 rounded-md transition duration-200 flex items-center">
            <i class="fas fa-plus mr-2"></i>Add New Plan
          </button>
        </div>

        <!-- Staking Plans Table -->
        <div class="bg-gray-800 rounded-lg shadow-lg overflow-hidden border border-gray-700">
          <div class="overflow-x-auto">
            <table class="w-full">
              <thead class="bg-gray-700">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Name</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Duration</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">APY Rate</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Min Amount</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Max Amount</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Withdrawal Fee</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Status</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-700">
                <?php if (empty($staking_plans)): ?>
                  <tr>
                    <td colspan="8" class="px-6 py-4 text-center text-gray-400">No staking plans found</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($staking_plans as $plan): ?>
                    <tr class="hover:bg-gray-750">
                      <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium"><?php echo htmlspecialchars($plan['name']); ?></div>
                        <div class="text-xs text-gray-400"><?php echo substr(htmlspecialchars($plan['description']), 0, 50) . (strlen($plan['description']) > 50 ? '...' : ''); ?></div>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm"><?php echo $plan['duration_days']; ?> days</td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm"><?php echo $plan['apy_rate']; ?>%</td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm"><?php echo number_format($plan['min_amount'], 0); ?></td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm"><?php echo ($plan['max_amount'] ? '' . number_format($plan['max_amount'], 0) : 'No limit'); ?></td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm"><?php echo $plan['early_withdrawal_fee']; ?>%</td>
                      <td class="px-6 py-4 whitespace-nowrap">
                        <?php if ($plan['is_active']): ?>
                          <span class="px-2 py-1 text-xs rounded-full bg-green-900 text-green-400">Active</span>
                        <?php else: ?>
                          <span class="px-2 py-1 text-xs rounded-full bg-gray-700 text-gray-400">Inactive</span>
                        <?php endif; ?>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm">
                        <button class="edit-plan text-blue-400 hover:text-blue-300 mr-3"
                          data-id="<?php echo $plan['id']; ?>"
                          data-name="<?php echo htmlspecialchars($plan['name']); ?>"
                          data-description="<?php echo htmlspecialchars($plan['description']); ?>"
                          data-duration="<?php echo $plan['duration_days']; ?>"
                          data-apy="<?php echo $plan['apy_rate']; ?>"
                          data-min="<?php echo $plan['min_amount']; ?>"
                          data-max="<?php echo $plan['max_amount']; ?>"
                          data-fee="<?php echo $plan['early_withdrawal_fee']; ?>"
                          data-active="<?php echo $plan['is_active']; ?>">
                          <i class="fas fa-edit"></i>
                        </button>
                        <button class="delete-plan text-red-400 hover:text-red-300" data-id="<?php echo $plan['id']; ?>">
                          <i class="fas fa-trash"></i>
                        </button>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Filters Section -->
      <div class="bg-gray-800 rounded-lg border border-gray-700 p-6 mb-6">
        <h3 class="text-lg font-bold mb-4">Filter Stakes</h3>

        <form action="staking.php" method="GET" class="space-y-4 md:space-y-0 md:grid md:grid-cols-4 md:gap-4">
          <!-- Status Filter -->
          <div>
            <label for="status" class="block text-sm font-medium text-gray-300 mb-2">Status</label>
            <select id="status" name="status" class="bg-gray-700 focus:ring-yellow-500 focus:border-yellow-500 block w-full rounded-md border-gray-600 text-white py-2 px-3">
              <option value="">All Statuses</option>
              <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
              <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
              <option value="withdrawn" <?php echo $status_filter == 'withdrawn' ? 'selected' : ''; ?>>Withdrawn</option>
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
              <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" class="bg-gray-700 focus:ring-yellow-500 focus:border-yellow-500 block w-full rounded-md border-gray-600 text-white py-2 pl-10 pr-3" placeholder="Name, Email, ID">
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
              <a href="staking.php" class="ml-3 bg-gray-700 hover:bg-gray-600 text-white font-medium py-2 px-4 rounded-md transition duration-200">
                <i class="fas fa-times mr-2"></i>Clear Filters
              </a>
            <?php endif; ?>
          </div>
        </form>
      </div>

      <!-- Stakes Table -->
      <div class="w-full bg-gray-800 rounded-lg border border-gray-700 shadow-lg overflow-hidden mb-6">
        <div class="p-6 border-b border-gray-700">
          <h3 class="text-lg font-bold">Stakes List</h3>
          <p class="text-gray-400 text-sm mt-1">Showing <?php echo min($total_stakes, $limit); ?> of <?php echo $total_stakes; ?> stakes</p>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full">
            <thead class="bg-gray-700">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">User</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Plan</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Amount</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Expected Return</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Start Date</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">End Date</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-700">
              <?php if (empty($stakes)): ?>
                <tr>
                  <td colspan="8" class="px-6 py-4 text-center text-gray-400">No stakes found</td>
                </tr>
              <?php else: ?>
                <?php foreach ($stakes as $stake): ?>
                  <tr class="hover:bg-gray-700">
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="flex items-center">
                        <div class="h-8 w-8 rounded-full bg-gray-600 flex items-center justify-center text-sm font-medium">
                          <?php echo strtoupper(substr($stake['full_name'], 0, 1)); ?>
                        </div>
                        <div class="ml-3">
                          <div class="text-sm font-medium"><?php echo htmlspecialchars($stake['full_name']); ?></div>
                          <div class="text-xs text-gray-400"><?php echo htmlspecialchars($stake['email']); ?></div>
                        </div>
                      </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="text-sm font-medium"><?php echo htmlspecialchars($stake['plan_name']); ?></div>
                      <div class="text-xs text-gray-400"><?php echo $stake['apy_rate']; ?>% APY / <?php echo $stake['duration_days']; ?> days</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium"><?php echo number_format($stake['amount'], 0); ?></td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="text-sm"><?php echo number_format($stake['expected_return'], 0); ?></div>
                      <div class="text-xs text-green-500">+<?php echo number_format($stake['expected_return'] - $stake['amount'], 0); ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300"><?php echo date('M d, Y H:i', strtotime($stake['start_date'])); ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300"><?php echo date('M d, Y H:i', strtotime($stake['end_date'])); ?></td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <?php if ($stake['status'] == 'active'): ?>
                        <span class="px-2 py-1 text-xs rounded-full bg-blue-900 text-blue-400">Active</span>
                      <?php elseif ($stake['status'] == 'completed'): ?>
                        <span class="px-2 py-1 text-xs rounded-full bg-green-900 text-green-400">Completed</span>
                      <?php elseif ($stake['status'] == 'withdrawn'): ?>
                        <span class="px-2 py-1 text-xs rounded-full bg-yellow-900 text-yellow-400">Withdrawn</span>
                      <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                      <button class="view-stake text-blue-400 hover:text-blue-300"
                        data-id="<?php echo $stake['id']; ?>"
                        data-stake-id="<?php echo htmlspecialchars($stake['stake_id']); ?>"
                        data-user="<?php echo htmlspecialchars($stake['full_name']); ?>"
                        data-email="<?php echo htmlspecialchars($stake['email']); ?>"
                        data-plan="<?php echo htmlspecialchars($stake['plan_name']); ?>"
                        data-amount="<?php echo number_format($stake['amount'], 0); ?>"
                        data-return="<?php echo number_format($stake['expected_return'], 0); ?>"
                        data-start="<?php echo date('M d, Y H:i', strtotime($stake['start_date'])); ?>"
                        data-end="<?php echo date('M d, Y H:i', strtotime($stake['end_date'])); ?>"
                        data-status="<?php echo $stake['status']; ?>"
                        data-completed="<?php echo $stake['completion_date'] ? date('M d, Y H:i', strtotime($stake['completion_date'])) : ''; ?>">
                        <i class="fas fa-eye"></i>
                      </button>
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
        <div class="px-6 py-4 bg-gray-800 border border-gray-700 rounded-lg">
          <div class="flex justify-between items-center">
            <div class="text-sm text-gray-400">
              Showing <?php echo ($page - 1) * $limit + 1; ?> to <?php echo min($page * $limit, $total_stakes); ?> of <?php echo $total_stakes; ?> stakes
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

  <!-- New Plan Modal (Fullscreen) -->
  <div id="newPlanModal" class="fixed inset-0 bg-black bg-opacity-90 flex items-center justify-center z-50 hidden overflow-y-auto">
    <div class="bg-gray-800 rounded-lg w-full max-w-5xl mx-4 my-4 md:mx-auto md:my-8 max-h-full overflow-y-auto">
      <!-- Modal Header -->
      <div class="sticky top-0 bg-gray-800 flex justify-between items-center p-6 border-b border-gray-700 z-10">
        <h3 class="text-xl font-bold">Create New Staking Plan</h3>
        <button id="closeNewPlanModal" class="text-gray-400 hover:text-white">
          <i class="fas fa-times text-xl"></i>
        </button>
      </div>

      <!-- Modal Content -->
      <div class="p-6">
        <form method="POST" action="">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
              <label for="name" class="block text-sm font-medium text-gray-300 mb-2">Plan Name</label>
              <input type="text" id="name" name="name" required class="bg-gray-700 focus:ring-yellow-500 focus:border-yellow-500 block w-full rounded-md border-gray-600 text-white py-2 px-3" placeholder="e.g. Silver - 30 Days">
            </div>

            <div>
              <label for="duration_days" class="block text-sm font-medium text-gray-300 mb-2">Duration (Days)</label>
              <input type="number" id="duration_days" name="duration_days" required min="1" class="bg-gray-700 focus:ring-yellow-500 focus:border-yellow-500 block w-full rounded-md border-gray-600 text-white py-2 px-3" placeholder="e.g. 30">
            </div>

            <div>
              <label for="apy_rate" class="block text-sm font-medium text-gray-300 mb-2">APY Rate (%)</label>
              <input type="number" id="apy_rate" name="apy_rate" required min="0.01" step="0.01" class="bg-gray-700 focus:ring-yellow-500 focus:border-yellow-500 block w-full rounded-md border-gray-600 text-white py-2 px-3" placeholder="e.g. 25.00">
            </div>

            <div>
              <label for="min_amount" class="block text-sm font-medium text-gray-300 mb-2">Minimum Amount ()</label>
              <input type="number" id="min_amount" name="min_amount" required min="1" class="bg-gray-700 focus:ring-yellow-500 focus:border-yellow-500 block w-full rounded-md border-gray-600 text-white py-2 px-3" placeholder="e.g. 5000">
            </div>

            <div>
              <label for="max_amount" class="block text-sm font-medium text-gray-300 mb-2">Maximum Amount (, optional)</label>
              <input type="number" id="max_amount" name="max_amount" min="0" class="bg-gray-700 focus:ring-yellow-500 focus:border-yellow-500 block w-full rounded-md border-gray-600 text-white py-2 px-3" placeholder="Leave empty for no limit">
            </div>

            <div>
              <label for="early_withdrawal_fee" class="block text-sm font-medium text-gray-300 mb-2">Early Withdrawal Fee (%)</label>
              <input type="number" id="early_withdrawal_fee" name="early_withdrawal_fee" required min="0" max="100" step="0.01" value="10" class="bg-gray-700 focus:ring-yellow-500 focus:border-yellow-500 block w-full rounded-md border-gray-600 text-white py-2 px-3" placeholder="e.g. 10.00">
            </div>

            <div class="md:col-span-2">
              <label for="description" class="block text-sm font-medium text-gray-300 mb-2">Description</label>
              <textarea id="description" name="description" rows="5" class="bg-gray-700 focus:ring-yellow-500 focus:border-yellow-500 block w-full rounded-md border-gray-600 text-white py-2 px-3" placeholder="Enter plan description"></textarea>
            </div>

            <div class="md:col-span-2">
              <label class="flex items-center">
                <input type="checkbox" name="is_active" checked class="form-checkbox h-5 w-5 text-yellow-500 rounded focus:ring-yellow-500 focus:ring-opacity-50">
                <span class="ml-2 text-gray-300">Active</span>
              </label>
            </div>
          </div>

          <div class="flex justify-end space-x-4 pt-4 border-t border-gray-700">
            <button type="button" onclick="document.getElementById('newPlanModal').classList.add('hidden')" class="bg-gray-700 hover:bg-gray-600 text-white font-medium py-3 px-6 rounded-lg transition duration-200">
              Cancel
            </button>
            <button type="submit" name="create_plan" class="bg-yellow-500 hover:bg-yellow-600 text-black font-medium py-3 px-6 rounded-lg transition duration-200">
              Create Plan
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Edit Plan Modal (Fullscreen) -->
  <div id="editPlanModal" class="fixed inset-0 bg-black bg-opacity-90 flex items-center justify-center z-50 hidden overflow-y-auto">
    <div class="bg-gray-800 rounded-lg w-full max-w-5xl mx-4 my-4 md:mx-auto md:my-8 max-h-full overflow-y-auto">
      <!-- Modal Header -->
      <div class="sticky top-0 bg-gray-800 flex justify-between items-center p-6 border-b border-gray-700 z-10">
        <h3 class="text-xl font-bold">Edit Staking Plan</h3>
        <button id="closeEditPlanModal" class="text-gray-400 hover:text-white">
          <i class="fas fa-times text-xl"></i>
        </button>
      </div>

      <!-- Modal Content -->
      <div class="p-6">
        <form method="POST" action="">
          <input type="hidden" id="edit_plan_id" name="plan_id">

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
              <label for="edit_name" class="block text-sm font-medium text-gray-300 mb-2">Plan Name</label>
              <input type="text" id="edit_name" name="name" required class="bg-gray-700 focus:ring-yellow-500 focus:border-yellow-500 block w-full rounded-md border-gray-600 text-white py-2 px-3">
            </div>

            <div>
              <label for="edit_duration_days" class="block text-sm font-medium text-gray-300 mb-2">Duration (Days)</label>
              <input type="number" id="edit_duration_days" name="duration_days" required min="1" class="bg-gray-700 focus:ring-yellow-500 focus:border-yellow-500 block w-full rounded-md border-gray-600 text-white py-2 px-3">
            </div>

            <div>
              <label for="edit_apy_rate" class="block text-sm font-medium text-gray-300 mb-2">APY Rate (%)</label>
              <input type="number" id="edit_apy_rate" name="apy_rate" required min="0.01" step="0.01" class="bg-gray-700 focus:ring-yellow-500 focus:border-yellow-500 block w-full rounded-md border-gray-600 text-white py-2 px-3">
            </div>

            <div>
              <label for="edit_min_amount" class="block text-sm font-medium text-gray-300 mb-2">Minimum Amount ()</label>
              <input type="number" id="edit_min_amount" name="min_amount" required min="1" class="bg-gray-700 focus:ring-yellow-500 focus:border-yellow-500 block w-full rounded-md border-gray-600 text-white py-2 px-3">
            </div>

            <div>
              <label for="edit_max_amount" class="block text-sm font-medium text-gray-300 mb-2">Maximum Amount (, optional)</label>
              <input type="number" id="edit_max_amount" name="max_amount" min="0" class="bg-gray-700 focus:ring-yellow-500 focus:border-yellow-500 block w-full rounded-md border-gray-600 text-white py-2 px-3" placeholder="Leave empty for no limit">
            </div>

            <div>
              <label for="edit_early_withdrawal_fee" class="block text-sm font-medium text-gray-300 mb-2">Early Withdrawal Fee (%)</label>
              <input type="number" id="edit_early_withdrawal_fee" name="early_withdrawal_fee" required min="0" max="100" step="0.01" class="bg-gray-700 focus:ring-yellow-500 focus:border-yellow-500 block w-full rounded-md border-gray-600 text-white py-2 px-3">
            </div>

            <div class="md:col-span-2">
              <label for="edit_description" class="block text-sm font-medium text-gray-300 mb-2">Description</label>
              <textarea id="edit_description" name="description" rows="5" class="bg-gray-700 focus:ring-yellow-500 focus:border-yellow-500 block w-full rounded-md border-gray-600 text-white py-2 px-3"></textarea>
            </div>

            <div class="md:col-span-2">
              <label class="flex items-center">
                <input type="checkbox" id="edit_is_active" name="is_active" class="form-checkbox h-5 w-5 text-yellow-500 rounded focus:ring-yellow-500 focus:ring-opacity-50">
                <span class="ml-2 text-gray-300">Active</span>
              </label>
            </div>
          </div>

          <div class="flex justify-end space-x-4 pt-4 border-t border-gray-700">
            <button type="button" onclick="document.getElementById('editPlanModal').classList.add('hidden')" class="bg-gray-700 hover:bg-gray-600 text-white font-medium py-3 px-6 rounded-lg transition duration-200">
              Cancel
            </button>
            <button type="submit" name="update_plan" class="bg-yellow-500 hover:bg-yellow-600 text-black font-medium py-3 px-6 rounded-lg transition duration-200">
              Update Plan
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Delete Plan Modal (Fullscreen) -->
  <div id="deletePlanModal" class="fixed inset-0 bg-black bg-opacity-90 flex items-center justify-center z-50 hidden overflow-y-auto">
    <div class="bg-gray-800 rounded-lg w-full max-w-3xl mx-4 my-4 md:mx-auto md:my-8 max-h-full overflow-y-auto">
      <!-- Modal Header -->
      <div class="sticky top-0 bg-gray-800 flex justify-between items-center p-6 border-b border-gray-700 z-10">
        <h3 class="text-xl font-bold">Delete Staking Plan</h3>
        <button id="closeDeletePlanModal" class="text-gray-400 hover:text-white">
          <i class="fas fa-times text-xl"></i>
        </button>
      </div>

      <!-- Modal Content -->
      <div class="p-6">
        <form method="POST" action="">
          <input type="hidden" id="delete_plan_id" name="plan_id">

          <div class="mb-6">
            <div class="bg-red-900 bg-opacity-20 border border-red-700 rounded-lg p-4 mb-6">
              <div class="flex items-start">
                <div class="flex-shrink-0">
                  <i class="fas fa-exclamation-triangle text-red-500 mt-1"></i>
                </div>
                <div class="ml-3">
                  <h3 class="text-md font-medium text-red-400">Warning: This action cannot be undone</h3>
                  <div class="mt-2 text-sm text-gray-300">
                    <p>Deleting this staking plan will permanently remove it from the system.</p>
                  </div>
                </div>
              </div>
            </div>

            <p class="text-gray-300 mb-4">Are you sure you want to delete this staking plan?</p>
            <p class="text-yellow-500 text-sm">Note: You cannot delete plans that have active stakes associated with them.</p>
          </div>

          <div class="flex justify-end space-x-4 pt-4 border-t border-gray-700">
            <button type="button" onclick="document.getElementById('deletePlanModal').classList.add('hidden')" class="bg-gray-700 hover:bg-gray-600 text-white font-medium py-3 px-6 rounded-lg transition duration-200">
              Cancel
            </button>
            <button type="submit" name="delete_plan" class="bg-red-600 hover:bg-red-700 text-white font-medium py-3 px-6 rounded-lg transition duration-200">
              Delete Plan
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- View Stake Modal (Fullscreen) -->
  <div id="viewStakeModal" class="fixed inset-0 bg-black bg-opacity-90 flex items-center justify-center z-50 hidden overflow-y-auto">
    <div class="bg-gray-800 rounded-lg w-full max-w-5xl mx-4 my-4 md:mx-auto md:my-8 max-h-full overflow-y-auto">
      <!-- Modal Header -->
      <div class="sticky top-0 bg-gray-800 flex justify-between items-center p-6 border-b border-gray-700 z-10">
        <h3 class="text-xl font-bold">Stake Details</h3>
        <button id="closeViewStakeModal" class="text-gray-400 hover:text-white">
          <i class="fas fa-times text-xl"></i>
        </button>
      </div>

      <!-- Modal Content -->
      <div class="p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
          <!-- User Info -->
          <div>
            <h4 class="text-sm font-medium text-gray-400 mb-2">User Information</h4>
            <div class="bg-gray-700 rounded-lg p-6">
              <div class="flex items-center mb-4">
                <div class="h-14 w-14 rounded-full bg-gray-600 flex items-center justify-center text-white text-xl font-bold mr-4" id="stakeUserInitial"></div>
                <div>
                  <div class="text-lg font-medium" id="stakeUserName"></div>
                  <div class="text-sm text-gray-400" id="stakeUserEmail"></div>
                </div>
              </div>
            </div>
          </div>

          <!-- Stake Info -->
          <div>
            <h4 class="text-sm font-medium text-gray-400 mb-2">Stake Information</h4>
            <div class="bg-gray-700 rounded-lg p-6">
              <div class="grid grid-cols-2 gap-3">
                <div class="text-sm text-gray-400">Stake ID:</div>
                <div class="text-sm text-right font-medium" id="stakeId"></div>

                <div class="text-sm text-gray-400">Status:</div>
                <div class="text-right" id="stakeStatus"></div>

                <div class="text-sm text-gray-400">Plan:</div>
                <div class="text-sm text-right font-medium" id="stakePlan"></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Financial Details -->
        <div class="mb-6">
          <h4 class="text-sm font-medium text-gray-400 mb-2">Financial Details</h4>
          <div class="bg-gray-700 rounded-lg p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div class="bg-gray-800 p-4 rounded-lg text-center">
                <div class="text-sm text-gray-400 mb-1">Amount Staked</div>
                <div class="text-xl font-bold" id="stakeAmount"></div>
              </div>

              <div class="bg-gray-800 p-4 rounded-lg text-center">
                <div class="text-sm text-gray-400 mb-1">Expected Return</div>
                <div class="text-xl font-bold" id="stakeReturn"></div>
              </div>

              <div class="bg-gray-800 p-4 rounded-lg text-center">
                <div class="text-sm text-gray-400 mb-1">Profit</div>
                <div class="text-xl font-bold text-green-500" id="stakeProfit"></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Timeline -->
        <div class="mb-6">
          <h4 class="text-sm font-medium text-gray-400 mb-2">Timeline</h4>
          <div class="bg-gray-700 rounded-lg p-6">
            <div class="flex flex-col space-y-4">
              <div class="flex items-center">
                <div class="h-10 w-10 rounded-full bg-blue-600 flex items-center justify-center mr-4">
                  <i class="fas fa-play text-white"></i>
                </div>
                <div class="flex-1">
                  <div class="text-sm font-medium">Start Date</div>
                  <div class="text-lg" id="stakeStartDate"></div>
                </div>
              </div>

              <div class="ml-5 h-12 w-0.5 bg-gray-600"></div>

              <div class="flex items-center">
                <div class="h-10 w-10 rounded-full bg-yellow-600 flex items-center justify-center mr-4">
                  <i class="fas fa-hourglass-end text-white"></i>
                </div>
                <div class="flex-1">
                  <div class="text-sm font-medium">End Date</div>
                  <div class="text-lg" id="stakeEndDate"></div>
                </div>
              </div>

              <div class="ml-5 h-12 w-0.5 bg-gray-600 completion-row"></div>

              <div class="flex items-center completion-row">
                <div class="h-10 w-10 rounded-full bg-green-600 flex items-center justify-center mr-4">
                  <i class="fas fa-check text-white"></i>
                </div>
                <div class="flex-1">
                  <div class="text-sm font-medium">Completion Date</div>
                  <div class="text-lg" id="stakeCompletionDate"></div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="flex justify-end pt-4 border-t border-gray-700">
          <button type="button" onclick="document.getElementById('viewStakeModal').classList.add('hidden')" class="bg-gray-700 hover:bg-gray-600 text-white font-medium py-3 px-6 rounded-lg transition duration-200">
            Close
          </button>
        </div>
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

      // New Plan Modal
      const newPlanModal = document.getElementById('newPlanModal');
      const closeNewPlanModalBtn = document.getElementById('closeNewPlanModal');

      if (closeNewPlanModalBtn) {
        closeNewPlanModalBtn.addEventListener('click', function() {
          newPlanModal.classList.add('hidden');
        });
      }

      newPlanModal.addEventListener('click', function(event) {
        if (event.target === newPlanModal) {
          newPlanModal.classList.add('hidden');
        }
      });

      // Edit Plan Modal
      const editPlanModal = document.getElementById('editPlanModal');
      const closeEditPlanModalBtn = document.getElementById('closeEditPlanModal');
      const editPlanBtns = document.querySelectorAll('.edit-plan');

      if (closeEditPlanModalBtn) {
        closeEditPlanModalBtn.addEventListener('click', function() {
          editPlanModal.classList.add('hidden');
        });
      }

      editPlanModal.addEventListener('click', function(event) {
        if (event.target === editPlanModal) {
          editPlanModal.classList.add('hidden');
        }
      });

      if (editPlanBtns.length > 0) {
        editPlanBtns.forEach(btn => {
          btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const name = this.getAttribute('data-name');
            const description = this.getAttribute('data-description');
            const duration = this.getAttribute('data-duration');
            const apy = this.getAttribute('data-apy');
            const min = this.getAttribute('data-min');
            const max = this.getAttribute('data-max');
            const fee = this.getAttribute('data-fee');
            const active = this.getAttribute('data-active') === '1';

            document.getElementById('edit_plan_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_description').value = description;
            document.getElementById('edit_duration_days').value = duration;
            document.getElementById('edit_apy_rate').value = apy;
            document.getElementById('edit_min_amount').value = min;
            document.getElementById('edit_max_amount').value = max ? max : '';
            document.getElementById('edit_early_withdrawal_fee').value = fee;
            document.getElementById('edit_is_active').checked = active;

            editPlanModal.classList.remove('hidden');
          });
        });
      }

      // Delete Plan Modal
      const deletePlanModal = document.getElementById('deletePlanModal');
      const closeDeletePlanModalBtn = document.getElementById('closeDeletePlanModal');
      const deletePlanBtns = document.querySelectorAll('.delete-plan');

      if (closeDeletePlanModalBtn) {
        closeDeletePlanModalBtn.addEventListener('click', function() {
          deletePlanModal.classList.add('hidden');
        });
      }

      deletePlanModal.addEventListener('click', function(event) {
        if (event.target === deletePlanModal) {
          deletePlanModal.classList.add('hidden');
        }
      });

      if (deletePlanBtns.length > 0) {
        deletePlanBtns.forEach(btn => {
          btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            document.getElementById('delete_plan_id').value = id;
            deletePlanModal.classList.remove('hidden');
          });
        });
      }

      // View Stake Modal
      const viewStakeModal = document.getElementById('viewStakeModal');
      const closeViewStakeModalBtn = document.getElementById('closeViewStakeModal');
      const viewStakeBtns = document.querySelectorAll('.view-stake');

      if (closeViewStakeModalBtn) {
        closeViewStakeModalBtn.addEventListener('click', function() {
          viewStakeModal.classList.add('hidden');
        });
      }

      viewStakeModal.addEventListener('click', function(event) {
        if (event.target === viewStakeModal) {
          viewStakeModal.classList.add('hidden');
        }
      });

      if (viewStakeBtns.length > 0) {
        viewStakeBtns.forEach(btn => {
          btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const stakeId = this.getAttribute('data-stake-id');
            const user = this.getAttribute('data-user');
            const email = this.getAttribute('data-email');
            const plan = this.getAttribute('data-plan');
            const amount = this.getAttribute('data-amount');
            const returnAmount = this.getAttribute('data-return');
            const start = this.getAttribute('data-start');
            const end = this.getAttribute('data-end');
            const status = this.getAttribute('data-status');
            const completed = this.getAttribute('data-completed');

            // Calculate profit
            const profit = parseInt(returnAmount.replace(/,/g, '')) - parseInt(amount.replace(/,/g, ''));

            // Populate modal
            document.getElementById('stakeUserInitial').textContent = user.charAt(0).toUpperCase();
            document.getElementById('stakeUserName').textContent = user;
            document.getElementById('stakeUserEmail').textContent = email;
            document.getElementById('stakeId').textContent = stakeId;
            document.getElementById('stakePlan').textContent = plan;
            document.getElementById('stakeAmount').textContent = '' + amount;
            document.getElementById('stakeReturn').textContent = '' + returnAmount;
            document.getElementById('stakeProfit').textContent = '+' + profit.toLocaleString();
            document.getElementById('stakeStartDate').textContent = start;
            document.getElementById('stakeEndDate').textContent = end;

            // Show status with appropriate color
            const statusElement = document.getElementById('stakeStatus');
            if (status === 'active') {
              statusElement.innerHTML = '<span class="px-2 py-1 text-xs rounded-full bg-blue-900 text-blue-400">Active</span>';
            } else if (status === 'completed') {
              statusElement.innerHTML = '<span class="px-2 py-1 text-xs rounded-full bg-green-900 text-green-400">Completed</span>';
            } else if (status === 'withdrawn') {
              statusElement.innerHTML = '<span class="px-2 py-1 text-xs rounded-full bg-yellow-900 text-yellow-400">Withdrawn</span>';
            }

            // Show completion date if available
            const completionRows = document.querySelectorAll('.completion-row');
            if (completed) {
              completionRows.forEach(row => row.classList.remove('hidden'));
              document.getElementById('stakeCompletionDate').textContent = completed;
            } else {
              completionRows.forEach(row => row.classList.add('hidden'));
            }

            viewStakeModal.classList.remove('hidden');
          });
        });
      }

      // Run stake maturity process if needed
      <?php if ($processed_count > 0): ?>
        console.log("Processed <?php echo $processed_count; ?> matured stakes!");
      <?php endif; ?>
    });
  </script>
</body>

</html>