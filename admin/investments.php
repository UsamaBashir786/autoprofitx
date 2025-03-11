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

// Handle investment status updates
if (isset($_POST['update_status']) && isset($_POST['investment_id']) && isset($_POST['status'])) {
  $investment_id = $_POST['investment_id'];
  $status = $_POST['status'];
  $admin_notes = isset($_POST['admin_notes']) ? $_POST['admin_notes'] : '';

  // Update the investment status
  $update_sql = "UPDATE investments SET status = ?, updated_at = NOW() WHERE id = ?";
  $update_stmt = $conn->prepare($update_sql);
  $update_stmt->bind_param("si", $status, $investment_id);

  if ($update_stmt->execute()) {
    $success_message = "Investment status updated successfully!";

    // If investment is cancelled, update completion date
    if ($status == 'cancelled') {
      $update_completion_sql = "UPDATE investments SET completion_date = NOW() WHERE id = ?";
      $update_completion_stmt = $conn->prepare($update_completion_sql);
      $update_completion_stmt->bind_param("i", $investment_id);
      $update_completion_stmt->execute();
      $update_completion_stmt->close();
    }

    // If investment is completed, update completion date
    if ($status == 'completed') {
      $update_completion_sql = "UPDATE investments SET completion_date = NOW() WHERE id = ?";
      $update_completion_stmt = $conn->prepare($update_completion_sql);
      $update_completion_stmt->bind_param("i", $investment_id);
      $update_completion_stmt->execute();
      $update_completion_stmt->close();
    }
  } else {
    $error_message = "Error updating investment status: " . $update_stmt->error;
  }

  $update_stmt->close();
}

// Manually complete investment (for testing or admin purposes)
if (isset($_POST['complete_investment']) && isset($_POST['investment_id'])) {
  $investment_id = $_POST['investment_id'];

  // Update investment status to completed
  $complete_sql = "UPDATE investments SET 
                    status = 'completed', 
                    completion_date = NOW() 
                    WHERE id = ?";
  $complete_stmt = $conn->prepare($complete_sql);
  $complete_stmt->bind_param("i", $investment_id);

  if ($complete_stmt->execute()) {
    $success_message = "Investment marked as completed successfully!";
  } else {
    $error_message = "Error completing investment: " . $complete_stmt->error;
  }

  $complete_stmt->close();
}

// Pagination settings
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Filters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$plan_filter = isset($_GET['plan_type']) ? $_GET['plan_type'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build the query
$where_clauses = [];
$params = [];
$types = "";

if (!empty($status_filter)) {
  $where_clauses[] = "i.status = ?";
  $params[] = $status_filter;
  $types .= "s";
}

if (!empty($plan_filter)) {
  $where_clauses[] = "i.plan_type = ?";
  $params[] = $plan_filter;
  $types .= "s";
}

if (!empty($date_from)) {
  $where_clauses[] = "DATE(i.created_at) >= ?";
  $params[] = $date_from;
  $types .= "s";
}

if (!empty($date_to)) {
  $where_clauses[] = "DATE(i.created_at) <= ?";
  $params[] = $date_to;
  $types .= "s";
}

if (!empty($search)) {
  $where_clauses[] = "(u.full_name LIKE ? OR u.email LIKE ? OR i.investment_id LIKE ?)";
  $search_param = "%$search%";
  $params[] = $search_param;
  $params[] = $search_param;
  $params[] = $search_param;
  $types .= "sss";
}

$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Count total investments
$count_sql = "SELECT COUNT(*) as count FROM investments i 
              JOIN users u ON i.user_id = u.id 
              $where_sql";

if (!empty($params)) {
  $count_stmt = $conn->prepare($count_sql);
  $count_stmt->bind_param($types, ...$params);
  $count_stmt->execute();
  $count_result = $count_stmt->get_result();
  $count_data = $count_result->fetch_assoc();
  $total_investments = $count_data['count'];
  $count_stmt->close();
} else {
  $count_result = $conn->query($count_sql);
  $count_data = $count_result->fetch_assoc();
  $total_investments = $count_data['count'];
}

$total_pages = ceil($total_investments / $limit);

// Get investments
$investments_sql = "SELECT i.*, u.full_name, u.email 
                    FROM investments i 
                    JOIN users u ON i.user_id = u.id 
                    $where_sql 
                    ORDER BY i.created_at DESC 
                    LIMIT $offset, $limit";

$investments = [];

if (!empty($params)) {
  $investments_stmt = $conn->prepare($investments_sql);
  $investments_stmt->bind_param($types, ...$params);
  $investments_stmt->execute();
  $investments_result = $investments_stmt->get_result();

  while ($row = $investments_result->fetch_assoc()) {
    $investments[] = $row;
  }

  $investments_stmt->close();
} else {
  $investments_result = $conn->query($investments_sql);

  while ($row = $investments_result->fetch_assoc()) {
    $investments[] = $row;
  }
}

// Get unique plan types for filter dropdown
$plans_sql = "SELECT DISTINCT plan_type FROM investments ORDER BY plan_type";
$plans_result = $conn->query($plans_sql);
$plan_types = [];

while ($row = $plans_result->fetch_assoc()) {
  $plan_types[] = $row['plan_type'];
}

// Get total amount per status
$total_active = 0;
$total_completed = 0;
$total_cancelled = 0;

$totals_sql = "SELECT status, SUM(amount) as total FROM investments GROUP BY status";
$totals_result = $conn->query($totals_sql);

while ($row = $totals_result->fetch_assoc()) {
  if ($row['status'] == 'active') {
    $total_active = $row['total'];
  } elseif ($row['status'] == 'completed') {
    $total_completed = $row['total'];
  } elseif ($row['status'] == 'cancelled') {
    $total_cancelled = $row['total'];
  }
}

// Calculate expected profit for active investments
$active_profit_sql = "SELECT SUM(expected_profit) as total FROM investments WHERE status = 'active'";
$active_profit_result = $conn->query($active_profit_sql);
$active_profit_data = $active_profit_result->fetch_assoc();
$total_expected_profit = $active_profit_data['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Investments Management - AutoProftX Admin</title>
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
          <a href="deposits.php" class="nav-item flex items-center px-4 py-3 text-gray-300 hover:text-white rounded transition duration-200">
            <i class="fas fa-money-bill-wave w-6"></i>
            <span>Deposits</span>
          </a>
          <a href="investments.php" class="nav-item active flex items-center px-4 py-3 text-gray-300 hover:text-white rounded transition duration-200">
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
        <h2 class="text-2xl font-bold">Investments Management</h2>
        <p class="text-gray-400">Manage and track user investment plans</p>
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
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
        <!-- Active Investments -->
        <div class="bg-gray-800 rounded-lg p-6 border border-gray-700 shadow-lg">
          <div class="flex justify-between">
            <div>
              <p class="text-gray-400 text-sm">Active</p>
              <h3 class="text-2xl font-bold mt-1"><?php echo number_format($total_active, 0); ?></h3>
            </div>
            <div class="h-12 w-12 rounded-lg bg-blue-500 flex items-center justify-center">
              <i class="fas fa-play text-black"></i>
            </div>
          </div>
          <div class="mt-4">
            <a href="investments.php?status=active" class="text-blue-500 hover:text-blue-400 text-sm">
              View active investments <i class="fas fa-arrow-right ml-1"></i>
            </a>
          </div>
        </div>

        <!-- Completed Investments -->
        <div class="bg-gray-800 rounded-lg p-6 border border-gray-700 shadow-lg">
          <div class="flex justify-between">
            <div>
              <p class="text-gray-400 text-sm">Completed</p>
              <h3 class="text-2xl font-bold mt-1"><?php echo number_format($total_completed, 0); ?></h3>
            </div>
            <div class="h-12 w-12 rounded-lg bg-green-500 flex items-center justify-center">
              <i class="fas fa-check text-black"></i>
            </div>
          </div>
          <div class="mt-4">
            <a href="investments.php?status=completed" class="text-green-500 hover:text-green-400 text-sm">
              View completed investments <i class="fas fa-arrow-right ml-1"></i>
            </a>
          </div>
        </div>

        <!-- Cancelled Investments -->
        <div class="bg-gray-800 rounded-lg p-6 border border-gray-700 shadow-lg">
          <div class="flex justify-between">
            <div>
              <p class="text-gray-400 text-sm">Cancelled</p>
              <h3 class="text-2xl font-bold mt-1"><?php echo number_format($total_cancelled, 0); ?></h3>
            </div>
            <div class="h-12 w-12 rounded-lg bg-red-500 flex items-center justify-center">
              <i class="fas fa-times text-black"></i>
            </div>
          </div>
          <div class="mt-4">
            <a href="investments.php?status=cancelled" class="text-red-500 hover:text-red-400 text-sm">
              View cancelled investments <i class="fas fa-arrow-right ml-1"></i>
            </a>
          </div>
        </div>

        <!-- Expected Profit -->
        <div class="bg-gray-800 rounded-lg p-6 border border-gray-700 shadow-lg">
          <div class="flex justify-between">
            <div>
              <p class="text-gray-400 text-sm">Expected Profit</p>
              <h3 class="text-2xl font-bold mt-1 text-green-400"><?php echo number_format($total_expected_profit, 0); ?></h3>
            </div>
            <div class="h-12 w-12 rounded-lg bg-green-500 flex items-center justify-center">
              <i class="fas fa-chart-line text-black"></i>
            </div>
          </div>
          <div class="mt-4">
            <p class="text-gray-400 text-sm">From active investments</p>
          </div>
        </div>
      </div>

      <!-- Filters Section -->
      <div class="bg-gray-800 rounded-lg border border-gray-700 p-6 mb-6">
        <h3 class="text-lg font-bold mb-4">Filter Investments</h3>

        <form action="investments.php" method="GET" class="space-y-4 md:space-y-0 md:grid md:grid-cols-4 md:gap-4">
          <!-- Status Filter -->
          <div>
            <label for="status" class="block text-sm font-medium text-gray-300 mb-2">Status</label>
            <select id="status" name="status" class="bg-gray-700 focus:ring-yellow-500 focus:border-yellow-500 block w-full rounded-md border-gray-600 text-white py-2 px-3">
              <option value="">All Statuses</option>
              <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
              <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
              <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
            </select>
          </div>

          <!-- Investment Plan Filter -->
          <div>
            <label for="plan_type" class="block text-sm font-medium text-gray-300 mb-2">Investment Plan</label>
            <select id="plan_type" name="plan_type" class="bg-gray-700 focus:ring-yellow-500 focus:border-yellow-500 block w-full rounded-md border-gray-600 text-white py-2 px-3">
              <option value="">All Plans</option>
              <?php foreach ($plan_types as $plan): ?>
                <option value="<?php echo $plan; ?>" <?php echo $plan_filter == $plan ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($plan); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Date Range -->
          <div>
            <label for="date_from" class="block text-sm font-medium text-gray-300 mb-2">Date From</label>
            <input type="text" id="date_from" name="date_from" value="<?php echo $date_from; ?>" class="bg-gray-700 focus:ring-yellow-500 focus:border-yellow-500 block w-full rounded-md border-gray-600 text-white py-2 px-3" placeholder="YYYY-MM-DD">
          </div>

          <div>
            <label for="date_to" class="block text-sm font-medium text-gray-300 mb-2">Date To</label>
            <input type="text" id="date_to" name="date_to" value="<?php echo $date_to; ?>" class="bg-gray-700 focus:ring-yellow-500 focus:border-yellow-500 block w-full rounded-md border-gray-600 text-white py-2 px-3" placeholder="YYYY-MM-DD">
          </div>

          <!-- Search -->
          <div class="md:col-span-4">
            <label for="search" class="block text-sm font-medium text-gray-300 mb-2">Search</label>
            <div class="relative">
              <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" class="bg-gray-700 focus:ring-yellow-500 focus:border-yellow-500 block w-full rounded-md border-gray-600 text-white py-2 pl-10 pr-3" placeholder="Name, Email, Investment ID">
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

            <?php if (!empty($status_filter) || !empty($plan_filter) || !empty($date_from) || !empty($date_to) || !empty($search)): ?>
              <a href="investments.php" class="ml-3 bg-gray-700 hover:bg-gray-600 text-white font-medium py-2 px-4 rounded-md transition duration-200">
                <i class="fas fa-times mr-2"></i>Clear Filters
              </a>
            <?php endif; ?>
          </div>
        </form>
      </div>

      <!-- Investments Table -->
      <div class="w-full bg-gray-800 rounded-lg border border-gray-700 shadow-lg overflow-hidden">
        <div class="p-6 border-b border-gray-700">
          <h3 class="text-lg font-bold">Investments List</h3>
          <p class="text-gray-400 text-sm mt-1">Showing <?php echo min($total_investments, $limit); ?> of <?php echo $total_investments; ?> investments</p>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full">
            <thead class="bg-gray-700">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">User</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Investment ID</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Plan</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Amount</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Expected Profit</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Duration</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-700">
              <?php if (empty($investments)): ?>
                <tr>
                  <td colspan="8" class="px-6 py-4 text-center text-gray-400">No investments found</td>
                </tr>
              <?php else: ?>
                <?php foreach ($investments as $investment): ?>
                  <tr class="hover:bg-gray-700">
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="flex items-center">
                        <div class="h-8 w-8 rounded-full bg-gray-600 flex items-center justify-center text-sm font-medium">
                          <?php echo strtoupper(substr($investment['full_name'], 0, 1)); ?>
                        </div>
                        <div class="ml-3">
                          <div class="text-sm font-medium"><?php echo htmlspecialchars($investment['full_name']); ?></div>
                          <div class="text-xs text-gray-400"><?php echo htmlspecialchars($investment['email']); ?></div>
                        </div>
                      </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                      <?php echo htmlspecialchars($investment['investment_id']); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                      <?php echo htmlspecialchars($investment['plan_type']); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                      <?php echo number_format($investment['amount'], 0); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-green-400">
                      <?php echo number_format($investment['expected_profit'], 0); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                      <?php
                      $start_date = new DateTime($investment['start_date']);
                      $maturity_date = new DateTime($investment['maturity_date']);
                      $interval = $start_date->diff($maturity_date);
                      $days = $interval->days;

                      if ($investment['status'] == 'active') {
                        $current_date = new DateTime();
                        $days_passed = $current_date->diff($start_date)->days;
                        $progress = min(100, round(($days_passed / $days) * 100));
                      ?>
                        <div class="text-xs text-gray-400 mb-1">
                          <?php echo $days_passed; ?> of <?php echo $days; ?> days (<?php echo $progress; ?>%)
                        </div>
                        <div class="w-full bg-gray-600 rounded-full h-1.5">
                          <div class="bg-yellow-500 h-1.5 rounded-full" style="width: <?php echo $progress; ?>%"></div>
                        </div>
                      <?php } else { ?>
                        <?php echo $days; ?> days
                      <?php } ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <?php if ($investment['status'] == 'active'): ?>
                        <span class="px-2 py-1 text-xs rounded-full bg-blue-900 text-blue-400">Active</span>
                      <?php elseif ($investment['status'] == 'completed'): ?>
                        <span class="px-2 py-1 text-xs rounded-full bg-green-900 text-green-400">Completed</span>
                      <?php elseif ($investment['status'] == 'cancelled'): ?>
                        <span class="px-2 py-1 text-xs rounded-full bg-red-900 text-red-400">Cancelled</span>
                      <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="flex space-x-2 justify-end">
                        <button class="view-investment text-blue-400 hover:text-blue-300"
                          data-id="<?php echo $investment['id']; ?>"
                          data-investment-id="<?php echo htmlspecialchars($investment['investment_id']); ?>"
                          data-user="<?php echo htmlspecialchars($investment['full_name']); ?>"
                          data-email="<?php echo htmlspecialchars($investment['email']); ?>"
                          data-amount="<?php echo number_format($investment['amount'], 0); ?>"
                          data-plan="<?php echo htmlspecialchars($investment['plan_type']); ?>"
                          data-profit="<?php echo number_format($investment['expected_profit'], 0); ?>"
                          data-total="<?php echo number_format($investment['total_return'], 0); ?>"
                          data-start="<?php echo date('M d, Y', strtotime($investment['start_date'])); ?>"
                          data-maturity="<?php echo date('M d, Y', strtotime($investment['maturity_date'])); ?>"
                          data-completion="<?php echo $investment['completion_date'] ? date('M d, Y', strtotime($investment['completion_date'])) : 'Not completed'; ?>"
                          data-created="<?php echo date('M d, Y', strtotime($investment['created_at'])); ?>"
                          data-status="<?php echo $investment['status']; ?>">
                          <i class="fas fa-eye"></i> View
                        </button>

                        <?php if ($investment['status'] == 'active'): ?>
                          <button class="complete-investment text-green-400 hover:text-green-300"
                            data-id="<?php echo $investment['id']; ?>"
                            data-amount="<?php echo $investment['amount']; ?>"
                            data-profit="<?php echo $investment['expected_profit']; ?>">
                            <i class="fas fa-check"></i> Complete
                          </button>

                          <button class="cancel-investment text-red-400 hover:text-red-300"
                            data-id="<?php echo $investment['id']; ?>">
                            <i class="fas fa-times"></i> Cancel
                          </button>
                        <?php endif; ?>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
          <div class="px-6 py-4 bg-gray-800 border-t border-gray-700">
            <div class="flex justify-between items-center">
              <div class="text-sm text-gray-400">
                Showing <?php echo ($page - 1) * $limit + 1; ?> to <?php echo min($page * $limit, $total_investments); ?> of <?php echo $total_investments; ?> investments
              </div>
              <div class="flex space-x-2">
                <?php if ($page > 1): ?>
                  <a href="?page=<?php echo $page - 1; ?><?php echo !empty($status_filter) ? '&status=' . $status_filter : ''; ?><?php echo !empty($plan_filter) ? '&plan_type=' . urlencode($plan_filter) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . $date_from : ''; ?><?php echo !empty($date_to) ? '&date_to=' . $date_to : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="px-3 py-1 bg-gray-700 hover:bg-gray-600 rounded-md text-sm text-gray-300">
                    Previous
                  </a>
                <?php endif; ?>

                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                  <a href="?page=<?php echo $i; ?><?php echo !empty($status_filter) ? '&status=' . $status_filter : ''; ?><?php echo !empty($plan_filter) ? '&plan_type=' . urlencode($plan_filter) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . $date_from : ''; ?><?php echo !empty($date_to) ? '&date_to=' . $date_to : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="px-3 py-1 <?php echo $i == $page ? 'bg-yellow-500 text-black' : 'bg-gray-700 hover:bg-gray-600 text-gray-300'; ?> rounded-md text-sm">
                    <?php echo $i; ?>
                  </a>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                  <a href="?page=<?php echo $page + 1; ?><?php echo !empty($status_filter) ? '&status=' . $status_filter : ''; ?><?php echo !empty($plan_filter) ? '&plan_type=' . urlencode($plan_filter) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . $date_from : ''; ?><?php echo !empty($date_to) ? '&date_to=' . $date_to : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="px-3 py-1 bg-gray-700 hover:bg-gray-600 rounded-md text-sm text-gray-300">
                    Next
                  </a>
                <?php endif; ?>
              </div>
            </div>
          </div>
      </div>
    <?php endif; ?>
  </div>
  </main>
  </div>

  <!-- View Investment Modal -->
  <div id="viewInvestmentModal" class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50 hidden">
    <div class="bg-gray-800 rounded-lg w-full max-w-2xl max-h-screen overflow-y-auto">
      <!-- Modal Header -->
      <div class="flex justify-between items-center p-6 border-b border-gray-700">
        <h3 class="text-xl font-bold">Investment Details</h3>
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

          <!-- Investment Info -->
          <div>
            <h4 class="text-sm font-medium text-gray-400 mb-2">Investment Information</h4>
            <div class="bg-gray-700 rounded-lg p-4">
              <div class="grid grid-cols-2 gap-2">
                <div class="text-sm text-gray-400">Investment ID:</div>
                <div class="text-sm font-bold text-right" id="investmentID"></div>

                <div class="text-sm text-gray-400">Plan:</div>
                <div class="text-sm text-right" id="planName"></div>

                <div class="text-sm text-gray-400">Status:</div>
                <div class="text-right" id="investmentStatus"></div>

                <div class="text-sm text-gray-400">Created:</div>
                <div class="text-sm text-right" id="investmentDate"></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Financial Details -->
        <div class="mb-6">
          <h4 class="text-sm font-medium text-gray-400 mb-2">Financial Details</h4>
          <div class="bg-gray-700 rounded-lg p-4">
            <div class="grid grid-cols-2 gap-2">
              <div class="text-sm text-gray-400">Principal Amount:</div>
              <div class="text-sm text-right font-medium" id="principalAmount"></div>

              <div class="text-sm text-gray-400">Expected Profit:</div>
              <div class="text-sm text-right text-green-400 font-medium" id="expectedProfit"></div>

              <div class="text-sm text-gray-400">Total Return:</div>
              <div class="text-sm text-right text-yellow-400 font-medium" id="totalReturn"></div>
            </div>
          </div>
        </div>

        <!-- Investment Timeline -->
        <div class="mb-6" id="timelineSection">
          <h4 class="text-sm font-medium text-gray-400 mb-2">Investment Timeline</h4>
          <div class="bg-gray-700 rounded-lg p-4">
            <div class="grid grid-cols-2 gap-2">
              <div class="text-sm text-gray-400">Start Date:</div>
              <div class="text-sm text-right" id="startDate"></div>

              <div class="text-sm text-gray-400">Maturity Date:</div>
              <div class="text-sm text-right" id="maturityDate"></div>

              <div class="text-sm text-gray-400" id="completionLabel">Completion Date:</div>
              <div class="text-sm text-right" id="completionDate"></div>
            </div>

            <div class="mt-4" id="progressBarContainer">
              <div class="text-sm text-gray-400 mb-1" id="progressLabel">Investment Progress:</div>
              <div class="w-full bg-gray-600 rounded-full h-2.5">
                <div id="progressBar" class="bg-yellow-500 h-2.5 rounded-full" style="width: 0%"></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Complete Investment Modal -->
  <div id="completeInvestmentModal" class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50 hidden">
    <div class="bg-gray-800 rounded-lg w-full max-w-md">
      <!-- Modal Header -->
      <div class="flex justify-between items-center p-6 border-b border-gray-700">
        <h3 class="text-xl font-bold">Complete Investment</h3>
        <button class="closeCompleteModal text-gray-400 hover:text-white">
          <i class="fas fa-times text-xl"></i>
        </button>
      </div>

      <!-- Modal Content -->
      <div class="p-6">
        <form id="completeForm" method="POST">
          <input type="hidden" id="completeInvestmentId" name="investment_id">
          <input type="hidden" name="complete_investment" value="1">

          <div class="mb-6">
            <p class="text-gray-300 mb-4">Are you sure you want to mark this investment as completed? This will change the status to completed and record the completion date.</p>

            <div class="bg-gray-700 p-4 rounded-md mb-4">
              <div class="flex justify-between mb-2">
                <span class="text-gray-400">Principal:</span>
                <span id="completeAmount" class="font-medium"></span>
              </div>
              <div class="flex justify-between mb-2">
                <span class="text-gray-400">Profit:</span>
                <span id="completeProfit" class="font-medium text-green-400"></span>
              </div>
              <div class="border-t border-gray-600 my-2"></div>
              <div class="flex justify-between">
                <span class="text-gray-300 font-medium">Total Return:</span>
                <span id="completeTotal" class="font-bold text-yellow-500"></span>
              </div>
            </div>
          </div>

          <div class="flex justify-end space-x-4">
            <button type="button" class="closeCompleteModal bg-gray-700 hover:bg-gray-600 text-white font-medium py-2 px-4 rounded-md transition duration-200">
              Cancel
            </button>
            <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-md transition duration-200">
              Complete Investment
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Cancel Investment Modal -->
  <div id="cancelInvestmentModal" class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50 hidden">
    <div class="bg-gray-800 rounded-lg w-full max-w-md">
      <!-- Modal Header -->
      <div class="flex justify-between items-center p-6 border-b border-gray-700">
        <h3 class="text-xl font-bold">Cancel Investment</h3>
        <button class="closeCancelModal text-gray-400 hover:text-white">
          <i class="fas fa-times text-xl"></i>
        </button>
      </div>

      <!-- Modal Content -->
      <div class="p-6">
        <form id="cancelForm" method="POST">
          <input type="hidden" id="cancelInvestmentId" name="investment_id">
          <input type="hidden" name="status" value="cancelled">
          <input type="hidden" name="update_status" value="1">

          <div class="mb-6">
            <p class="text-gray-300 mb-4">Are you sure you want to cancel this investment? This action cannot be undone.</p>

            <div class="mt-4">
              <label for="admin_notes" class="block text-sm font-medium text-gray-300 mb-2">Cancellation Reason (Optional)</label>
              <textarea id="admin_notes" name="admin_notes" rows="3" class="bg-gray-700 focus:ring-yellow-500 focus:border-yellow-500 block w-full rounded-md border-gray-600 text-white py-2 px-3" placeholder="Add notes about why this investment is being cancelled..."></textarea>
            </div>
          </div>

          <div class="flex justify-end space-x-4">
            <button type="button" class="closeCancelModal bg-gray-700 hover:bg-gray-600 text-white font-medium py-2 px-4 rounded-md transition duration-200">
              No, Keep Investment
            </button>
            <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-medium py-2 px-4 rounded-md transition duration-200">
              Yes, Cancel Investment
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

      // View Investment Modal
      const viewInvestmentModal = document.getElementById('viewInvestmentModal');
      const viewInvestmentBtns = document.querySelectorAll('.view-investment');
      const closeViewModalBtn = document.getElementById('closeViewModal');

      if (viewInvestmentBtns.length > 0 && closeViewModalBtn) {
        viewInvestmentBtns.forEach(btn => {
          btn.addEventListener('click', function(event) {
            // Prevent default anchor behavior if it's a link
            event.preventDefault();

            // Prevent event from bubbling up
            event.stopPropagation();

            const id = this.getAttribute('data-id');
            const investmentID = this.getAttribute('data-investment-id') || 'Unknown';
            const user = this.getAttribute('data-user') || 'Unknown';
            const email = this.getAttribute('data-email') || 'No email';
            const amount = this.getAttribute('data-amount') || '0';
            const plan = this.getAttribute('data-plan') || 'Unknown';
            const profit = this.getAttribute('data-profit') || '0';
            const total = this.getAttribute('data-total') || '0';
            const start = this.getAttribute('data-start') || 'Unknown';
            const maturity = this.getAttribute('data-maturity') || 'Unknown';
            const completion = this.getAttribute('data-completion') || 'Not completed';
            const created = this.getAttribute('data-created') || 'Unknown';
            const status = this.getAttribute('data-status') || 'active';

            // Populate modal
            document.getElementById('userInitial').textContent = user.charAt(0).toUpperCase();
            document.getElementById('userName').textContent = user;
            document.getElementById('userEmail').textContent = email;
            document.getElementById('investmentID').textContent = investmentID;
            document.getElementById('planName').textContent = plan;
            document.getElementById('investmentDate').textContent = created;
            document.getElementById('principalAmount').textContent = amount;
            document.getElementById('expectedProfit').textContent = profit;
            document.getElementById('totalReturn').textContent = total;
            document.getElementById('startDate').textContent = start;
            document.getElementById('maturityDate').textContent = maturity;

            // Set completion date display
            const completionLabel = document.getElementById('completionLabel');
            const completionDate = document.getElementById('completionDate');

            if (status === 'active') {
              completionLabel.textContent = 'Expected Completion:';
              completionDate.textContent = maturity;
            } else {
              completionLabel.textContent = 'Completion Date:';
              completionDate.textContent = completion;
            }

            // Show status with appropriate color
            const statusElement = document.getElementById('investmentStatus');
            if (status === 'active') {
              statusElement.innerHTML = '<span class="px-2 py-1 text-xs rounded-full bg-blue-900 text-blue-400">Active</span>';

              // Calculate and show progress
              const progressBarContainer = document.getElementById('progressBarContainer');
              const progressBar = document.getElementById('progressBar');
              const progressLabel = document.getElementById('progressLabel');

              // Get dates for calculation
              const startDate = new Date(start);
              const maturityDate = new Date(maturity);
              const currentDate = new Date();

              // Calculate total days and days passed
              const totalDays = Math.floor((maturityDate - startDate) / (1000 * 60 * 60 * 24));
              const daysPassed = Math.floor((currentDate - startDate) / (1000 * 60 * 60 * 24));
              const progress = Math.min(100, Math.round((daysPassed / totalDays) * 100));

              // Set progress bar
              progressLabel.textContent = `Investment Progress: ${daysPassed} of ${totalDays} days (${progress}%)`;
              progressBar.style.width = `${progress}%`;
              progressBarContainer.classList.remove('hidden');
            } else if (status === 'completed') {
              statusElement.innerHTML = '<span class="px-2 py-1 text-xs rounded-full bg-green-900 text-green-400">Completed</span>';
              document.getElementById('progressBarContainer').classList.add('hidden');
            } else if (status === 'cancelled') {
              statusElement.innerHTML = '<span class="px-2 py-1 text-xs rounded-full bg-red-900 text-red-400">Cancelled</span>';
              document.getElementById('progressBarContainer').classList.add('hidden');
            }

            // Show modal
            viewInvestmentModal.classList.remove('hidden');
          });
        });

        // Handle modal close button
        closeViewModalBtn.addEventListener('click', function(event) {
          event.preventDefault();
          event.stopPropagation();
          viewInvestmentModal.classList.add('hidden');
        });

        // Stop propagation on the modal content to prevent clicks inside from closing
        const modalContent = viewInvestmentModal.querySelector('.bg-gray-800');
        if (modalContent) {
          modalContent.addEventListener('click', function(event) {
            event.stopPropagation();
          });
        }
      }

      // Complete Investment Modal
      const completeInvestmentModal = document.getElementById('completeInvestmentModal');
      const completeBtns = document.querySelectorAll('.complete-investment');
      const closeCompleteBtns = document.querySelectorAll('.closeCompleteModal');

      if (completeBtns.length > 0 && closeCompleteBtns.length > 0 && completeInvestmentModal) {
        completeBtns.forEach(btn => {
          btn.addEventListener('click', function(event) {
            event.preventDefault();
            event.stopPropagation();

            const id = this.getAttribute('data-id');
            const amount = parseFloat(this.getAttribute('data-amount')) || 0;
            const profit = parseFloat(this.getAttribute('data-profit')) || 0;
            const total = amount + profit;

            document.getElementById('completeInvestmentId').value = id;
            document.getElementById('completeAmount').textContent = '' + amount.toLocaleString();
            document.getElementById('completeProfit').textContent = '' + profit.toLocaleString();
            document.getElementById('completeTotal').textContent = '' + total.toLocaleString();

            completeInvestmentModal.classList.remove('hidden');
          });
        });

        closeCompleteBtns.forEach(btn => {
          btn.addEventListener('click', function(event) {
            event.preventDefault();
            event.stopPropagation();
            completeInvestmentModal.classList.add('hidden');
          });
        });

        // Stop propagation on modal content
        const completeModalContent = completeInvestmentModal.querySelector('.bg-gray-800');
        if (completeModalContent) {
          completeModalContent.addEventListener('click', function(event) {
            event.stopPropagation();
          });
        }
      }

      // Cancel Investment Modal
      const cancelInvestmentModal = document.getElementById('cancelInvestmentModal');
      const cancelBtns = document.querySelectorAll('.cancel-investment');
      const closeCancelBtns = document.querySelectorAll('.closeCancelModal');

      if (cancelBtns.length > 0 && closeCancelBtns.length > 0 && cancelInvestmentModal) {
        cancelBtns.forEach(btn => {
          btn.addEventListener('click', function(event) {
            event.preventDefault();
            event.stopPropagation();

            const id = this.getAttribute('data-id');
            document.getElementById('cancelInvestmentId').value = id;
            cancelInvestmentModal.classList.remove('hidden');
          });
        });

        closeCancelBtns.forEach(btn => {
          btn.addEventListener('click', function(event) {
            event.preventDefault();
            event.stopPropagation();
            cancelInvestmentModal.classList.add('hidden');
          });
        });

        // Stop propagation on modal content
        const cancelModalContent = cancelInvestmentModal.querySelector('.bg-gray-800');
        if (cancelModalContent) {
          cancelModalContent.addEventListener('click', function(event) {
            event.stopPropagation();
          });
        }
      }

      // Only close modals when specifically clicking the backdrop
      if (viewInvestmentModal) {
        viewInvestmentModal.addEventListener('click', function(event) {
          if (event.target === viewInvestmentModal) {
            viewInvestmentModal.classList.add('hidden');
          }
        });
      }

      if (completeInvestmentModal) {
        completeInvestmentModal.addEventListener('click', function(event) {
          if (event.target === completeInvestmentModal) {
            completeInvestmentModal.classList.add('hidden');
          }
        });
      }

      if (cancelInvestmentModal) {
        cancelInvestmentModal.addEventListener('click', function(event) {
          if (event.target === cancelInvestmentModal) {
            cancelInvestmentModal.classList.add('hidden');
          }
        });
      }

      // Form submission handlers
      const completeForm = document.getElementById('completeForm');
      const cancelForm = document.getElementById('cancelForm');

      if (completeForm) {
        completeForm.addEventListener('submit', function() {
          console.log("Submitting complete form");
        });
      }

      if (cancelForm) {
        cancelForm.addEventListener('submit', function() {
          console.log("Submitting cancel form");
        });
      }
    });
  </script>
</body>

</html>