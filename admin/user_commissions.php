<?php
// Include database connection and tree commission system
include '../config/db.php';
require_once('../tree_commission.php');

// Check admin authentication
session_start();
if (!isset($_SESSION['admin_id'])) {
  header("Location: admin_login.php");
  exit();
}

// Get user ID from query string
$userId = isset($_GET['id']) ? intval($_GET['id']) : null;

if (!$userId) {
  header("Location: admin_dashboard.php?tab=users");
  exit();
}

// Pagination parameters
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$itemsPerPage = 20;
$offset = ($page - 1) * $itemsPerPage;

// Filter parameters
$status = isset($_GET['status']) ? $_GET['status'] : '';
$dateFrom = isset($_GET['from']) ? $_GET['from'] : '';
$dateTo = isset($_GET['to']) ? $_GET['to'] : '';

// Messages
$message = '';
$error = '';

/**
 * Get user information
 * 
 * @param int $userId User ID
 * @return array User data
 */
function getUserInfo($userId)
{
  $conn = getConnection();

  try {
    $stmt = $conn->prepare("
      SELECT u.id, u.full_name, u.email, u.status, w.balance
      FROM users u
      LEFT JOIN wallets w ON u.id = w.user_id
      WHERE u.id = ?
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
      return ['error' => 'User not found'];
    }

    return $result->fetch_assoc();
  } catch (Exception $e) {
    return ['error' => $e->getMessage()];
  } finally {
    $conn->close();
  }
}

/**
 * Get user commissions with filters and pagination
 * 
 * @param int $userId User ID
 * @param string $status Status filter
 * @param string $dateFrom Start date filter
 * @param string $dateTo End date filter
 * @param int $offset Pagination offset
 * @param int $limit Pagination limit
 * @return array Commissions data
 */
function getUserCommissions($userId, $status = '', $dateFrom = '', $dateTo = '', $offset = 0, $limit = 20)
{
  $conn = getConnection();

  try {
    // Build WHERE clause with filters
    $whereClause = "WHERE tc.user_id = ?";
    $params = array($userId);
    $types = "i";

    if (!empty($status)) {
      $whereClause .= " AND tc.status = ?";
      $params[] = $status;
      $types .= "s";
    }

    if (!empty($dateFrom)) {
      $whereClause .= " AND tc.created_at >= ?";
      $params[] = $dateFrom . " 00:00:00";
      $types .= "s";
    }

    if (!empty($dateTo)) {
      $whereClause .= " AND tc.created_at <= ?";
      $params[] = $dateTo . " 23:59:59";
      $types .= "s";
    }

    // Get total count for pagination
    $countQuery = "SELECT COUNT(*) as total FROM tree_commissions tc " . $whereClause;
    $stmt = $conn->prepare($countQuery);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $countResult = $stmt->get_result();
    $totalCount = $countResult->fetch_assoc()['total'];

    // Very minimal query with only essential columns
    $query = "
      SELECT 
        tc.id, tc.commission_amount, tc.level, tc.status, tc.created_at
      FROM tree_commissions tc
      $whereClause
      ORDER BY tc.created_at DESC
      LIMIT ?, ?
    ";

    $stmt = $conn->prepare($query);
    $params[] = $offset;
    $params[] = $limit;
    $types .= "ii";
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $commissions = [];
    while ($row = $result->fetch_assoc()) {
      $commissions[] = $row;
    }

    return [
      'commissions' => $commissions,
      'total' => $totalCount
    ];
  } catch (Exception $e) {
    return ['error' => $e->getMessage()];
  } finally {
    $conn->close();
  }
}

/**
 * Get commission summary for the user
 * 
 * @param int $userId User ID
 * @return array Summary data with default values
 */
function getCommissionSummary($userId)
{
  $conn = getConnection();

  try {
    // Initialize default values
    $summary = [
      'total_count' => 0,
      'total_earned' => 0,
      'unique_sources' => 0,
      'highest_commission' => 0,
      'average_commission' => 0,
      'level_breakdown' => []
    ];

    $stmt = $conn->prepare("
      SELECT 
        COUNT(*) as total_count,
        COALESCE(SUM(commission_amount), 0) as total_earned,
        COUNT(DISTINCT referral_source_id) as unique_sources,
        COALESCE(MAX(commission_amount), 0) as highest_commission,
        COALESCE(AVG(commission_amount), 0) as average_commission
      FROM tree_commissions
      WHERE user_id = ? AND status = 'paid'
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
      $summaryData = $result->fetch_assoc();
      // Merge with defaults to ensure all keys exist
      $summary = array_merge($summary, $summaryData);
    }

    // Get level breakdown only if there are commissions
    if ($summary['total_count'] > 0) {
      $levelStmt = $conn->prepare("
        SELECT level, COUNT(*) as count, SUM(commission_amount) as total
        FROM tree_commissions
        WHERE user_id = ? AND status = 'paid'
        GROUP BY level
        ORDER BY level
      ");
      $levelStmt->bind_param("i", $userId);
      $levelStmt->execute();
      $levelResult = $levelStmt->get_result();

      $levelBreakdown = [];
      while ($row = $levelResult->fetch_assoc()) {
        $levelBreakdown[$row['level']] = $row;
      }

      $summary['level_breakdown'] = $levelBreakdown;
    }

    return $summary;
  } catch (Exception $e) {
    return [
      'error' => $e->getMessage(),
      'total_count' => 0,
      'total_earned' => 0,
      'unique_sources' => 0,
      'highest_commission' => 0,
      'average_commission' => 0,
      'level_breakdown' => []
    ];
  } finally {
    $conn->close();
  }
}

// Get unread messages count for notification
function getUnreadMessagesCount()
{
  $conn = getConnection();
  $count = 0;

  try {
    $adminId = $_SESSION['admin_id'];
    $stmt = $conn->prepare("
      SELECT COUNT(*) as count
      FROM admin_messages
      WHERE admin_id = ? AND sent_by = 'user' AND `read` = 0
    ");
    $stmt->bind_param("i", $adminId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
      $count = $row['count'];
    }
  } catch (Exception $e) {
    error_log("Error getting unread messages: " . $e->getMessage());
  } finally {
    $conn->close();
  }

  return $count;
}

// Get commission rates for reference
function getCommissionRates()
{
  $conn = getConnection();
  $rates = [];

  try {
    $result = $conn->query("SELECT level, commission_rate FROM referral_structure ORDER BY level");

    while ($row = $result->fetch_assoc()) {
      $rates[$row['level']] = $row['commission_rate'];
    }

    return $rates;
  } catch (Exception $e) {
    return [];
  } finally {
    $conn->close();
  }
}

// Get user data and commissions
$userData = getUserInfo($userId);
$commissionData = getUserCommissions($userId, $status, $dateFrom, $dateTo, $offset, $itemsPerPage);
$commissionSummary = getCommissionSummary($userId);
$commissionRates = getCommissionRates();
$unreadCount = getUnreadMessagesCount();

// Calculate pagination
$totalItems = $commissionData['total'] ?? 0;
$totalPages = ceil($totalItems / $itemsPerPage);
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>User Commissions - Admin Panel</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary: {
              50: '#f0f9ff',
              100: '#e0f2fe',
              500: '#0ea5e9',
              600: '#0284c7',
              700: '#0369a1',
            },
          }
        }
      }
    }
  </script>
  <style>
    @media print {
      .no-print {
        display: none !important;
      }

      body {
        font-size: 12px;
      }
    }

    .level-badge-1 {
      background-color: #3b82f6;
    }

    .level-badge-2 {
      background-color: #10b981;
    }

    .level-badge-3 {
      background-color: #f59e0b;
    }

    .level-badge-4 {
      background-color: #8b5cf6;
    }

    .level-badge-5 {
      background-color: #ec4899;
    }
  </style>
</head>

<body class="bg-gray-100">
  <div class="min-h-screen flex">
    <!-- Sidebar -->
    <div class="w-64 bg-gray-800 text-white no-print">
      <div class="p-4">
        <h2 class="text-2xl font-semibold text-center mb-6">Admin Panel</h2>
        <nav>
          <ul class="space-y-2">
            <li>
              <a href="admin_dashboard.php" class="flex items-center p-3 text-white hover:bg-gray-700 rounded-lg group">
                <i class="fas fa-tachometer-alt mr-3"></i> Dashboard
              </a>
            </li>
            <li>
              <a href="admin_dashboard.php?tab=users" class="flex items-center p-3 text-white hover:bg-gray-700 rounded-lg group">
                <i class="fas fa-users mr-3"></i> Users
              </a>
            </li>
            <li>
              <a href="admin_dashboard.php?tab=visualization" class="flex items-center p-3 text-white hover:bg-gray-700 rounded-lg group">
                <i class="fas fa-sitemap mr-3"></i> Tree Visualization
              </a>
            </li>
            <li>
              <a href="admin_full_tree.php" class="flex items-center p-3 text-white hover:bg-gray-700 rounded-lg group">
                <i class="fas fa-project-diagram mr-3"></i> Full Tree View
              </a>
            </li>
            <li>
              <a href="admin_messages.php" class="flex items-center p-3 text-white hover:bg-gray-700 rounded-lg group">
                <i class="fas fa-comments mr-3"></i> Messages
                <?php if ($unreadCount > 0): ?>
                  <span class="ml-auto bg-red-500 text-white px-2 py-1 rounded-full text-xs"><?php echo $unreadCount; ?></span>
                <?php endif; ?>
              </a>
            </li>
            <li>
              <a href="admin_dashboard.php?tab=commission-rates" class="flex items-center p-3 text-white hover:bg-gray-700 rounded-lg group">
                <i class="fas fa-sliders-h mr-3"></i> Commission Rates
              </a>
            </li>
            <li>
              <a href="admin_dashboard.php?tab=top-referrers" class="flex items-center p-3 text-white hover:bg-gray-700 rounded-lg group">
                <i class="fas fa-trophy mr-3"></i> Top Referrers
              </a>
            </li>
            <li>
              <a href="admin_dashboard.php?tab=recent-commissions" class="flex items-center p-3 text-white hover:bg-gray-700 rounded-lg group">
                <i class="fas fa-history mr-3"></i> Recent Commissions
              </a>
            </li>
            <?php if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'super_admin'): ?>
              <li>
                <a href="admin_dashboard.php?tab=admins" class="flex items-center p-3 text-white hover:bg-gray-700 rounded-lg group">
                  <i class="fas fa-user-shield mr-3"></i> Admins
                </a>
              </li>
            <?php endif; ?>
            <li class="mt-10">
              <a href="admin_logout.php" class="flex items-center p-3 text-white hover:bg-red-700 rounded-lg group">
                <i class="fas fa-sign-out-alt mr-3"></i> Logout
              </a>
            </li>
          </ul>
        </nav>
      </div>
    </div>

    <!-- Main Content -->
    <div class="flex-1">
      <header class="bg-white shadow no-print">
        <div class="max-w-7xl mx-auto py-4 px-6 flex justify-between items-center">
          <h1 class="text-2xl font-bold text-gray-900">User Commissions</h1>

          <div class="flex items-center space-x-4">
            <a href="admin_messages.php" class="relative">
              <i class="fas fa-envelope text-xl text-gray-600 hover:text-gray-900"></i>
              <?php if ($unreadCount > 0): ?>
                <span class="absolute -top-2 -right-2 bg-red-500 text-white px-1.5 py-0.5 rounded-full text-xs"><?php echo $unreadCount; ?></span>
              <?php endif; ?>
            </a>
            <span class="text-gray-600">
              Welcome, <span class="font-semibold"><?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?></span>
            </span>
          </div>
        </div>
      </header>

      <main class="max-w-7xl mx-auto py-6 px-6">
        <!-- Alerts for messages and errors -->
        <?php if (!empty($message)): ?>
          <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative no-print">
            <span class="block sm:inline"><?php echo $message; ?></span>
          </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
          <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative no-print">
            <span class="block sm:inline"><?php echo $error; ?></span>
          </div>
        <?php endif; ?>

        <?php if (isset($userData['error'])): ?>
          <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative">
            <span class="block sm:inline">Error: <?php echo $userData['error']; ?></span>
            <div class="mt-2">
              <a href="admin_dashboard.php?tab=users" class="bg-red-500 hover:bg-red-600 text-white py-2 px-4 rounded">
                Back to Users
              </a>
            </div>
          </div>
        <?php else: ?>

          <!-- User info header -->
          <div class="bg-white shadow rounded-lg p-4 mb-6">
            <div class="flex flex-wrap items-center justify-between">
              <div>
                <h2 class="text-xl font-semibold text-gray-800">
                  Commissions for: <?php echo htmlspecialchars($userData['full_name']); ?>
                  <span class="text-sm font-normal text-gray-500">
                    (ID: <?php echo $userData['id']; ?>)
                  </span>
                </h2>
                <p class="text-gray-600">
                  <strong>Email:</strong> <?php echo htmlspecialchars($userData['email']); ?> |
                  <strong>Status:</strong>
                  <span class="<?php echo $userData['status'] === 'active' ? 'text-green-600' : 'text-red-600'; ?>">
                    <?php echo ucfirst($userData['status']); ?>
                  </span> |
                  <strong>Current Balance:</strong> $<?php echo number_format($userData['balance'], 2); ?>
                </p>
              </div>

              <div class="flex space-x-3 mt-4 md:mt-0">
                <button onclick="window.print()" class="bg-gray-500 hover:bg-gray-600 text-white py-2 px-4 rounded shadow-sm">
                  <i class="fas fa-print mr-1"></i> Print
                </button>

                <a href="user_edit.php?id=<?php echo $userId; ?>" class="bg-blue-500 hover:bg-blue-600 text-white py-2 px-4 rounded shadow-sm">
                  <i class="fas fa-edit mr-1"></i> Edit User
                </a>

                <a href="user_tree.php?id=<?php echo $userId; ?>" class="bg-green-500 hover:bg-green-600 text-white py-2 px-4 rounded shadow-sm">
                  <i class="fas fa-sitemap mr-1"></i> View Tree
                </a>

                <a href="admin_dashboard.php?tab=users" class="bg-gray-500 hover:bg-gray-600 text-white py-2 px-4 rounded shadow-sm">
                  <i class="fas fa-arrow-left mr-1"></i> Back
                </a>
              </div>
            </div>
          </div>

          <!-- Commission Summary Cards -->
          <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <!-- Total Commissions Earned -->
            <div class="bg-white rounded-lg shadow p-4">
              <div class="flex items-center">
                <div class="p-3 rounded-full bg-green-100 text-green-500">
                  <i class="fas fa-money-bill-wave text-xl"></i>
                </div>
                <div class="ml-4">
                  <p class="text-gray-500 text-sm">Total Earned</p>
                  <p class="text-xl font-semibold">$<?php echo number_format($commissionSummary['total_earned'], 2); ?></p>
                </div>
              </div>
            </div>

            <!-- Commission Count -->
            <div class="bg-white rounded-lg shadow p-4">
              <div class="flex items-center">
                <div class="p-3 rounded-full bg-blue-100 text-blue-500">
                  <i class="fas fa-hashtag text-xl"></i>
                </div>
                <div class="ml-4">
                  <p class="text-gray-500 text-sm">Total Commissions</p>
                  <p class="text-xl font-semibold"><?php echo number_format($commissionSummary['total_count']); ?></p>
                </div>
              </div>
            </div>

            <!-- Unique Sources -->
            <div class="bg-white rounded-lg shadow p-4">
              <div class="flex items-center">
                <div class="p-3 rounded-full bg-purple-100 text-purple-500">
                  <i class="fas fa-users text-xl"></i>
                </div>
                <div class="ml-4">
                  <p class="text-gray-500 text-sm">Unique Sources</p>
                  <p class="text-xl font-semibold"><?php echo number_format($commissionSummary['unique_sources']); ?></p>
                </div>
              </div>
            </div>

            <!-- Average Commission -->
            <div class="bg-white rounded-lg shadow p-4">
              <div class="flex items-center">
                <div class="p-3 rounded-full bg-yellow-100 text-yellow-500">
                  <i class="fas fa-calculator text-xl"></i>
                </div>
                <div class="ml-4">
                  <p class="text-gray-500 text-sm">Average Commission</p>
                  <p class="text-xl font-semibold">$<?php echo number_format($commissionSummary['average_commission'], 2); ?></p>
                </div>
              </div>
            </div>
          </div>

          <!-- Level Breakdown -->
          <div class="bg-white shadow rounded-lg p-4 mb-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Commission Level Breakdown</h3>

            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
              <?php
              $totalCommissionCount = 0;
              foreach ($commissionSummary['level_breakdown'] as $level => $data) {
                $totalCommissionCount += $data['count'];
              }

              foreach ($commissionSummary['level_breakdown'] as $level => $data):
                $percentage = $totalCommissionCount > 0 ? ($data['count'] / $totalCommissionCount) * 100 : 0;
              ?>
                <div class="border rounded-lg p-4">
                  <div class="flex items-center mb-2">
                    <span class="inline-block w-6 h-6 rounded-full level-badge-<?php echo $level; ?> mr-2"></span>
                    <h4 class="font-medium">Level <?php echo $level; ?></h4>
                  </div>
                  <div class="grid grid-cols-2 gap-1 text-sm">
                    <div class="text-gray-500">Count:</div>
                    <div class="text-right"><?php echo number_format($data['count']); ?></div>

                    <div class="text-gray-500">Total:</div>
                    <div class="text-right">$<?php echo number_format($data['total'], 2); ?></div>

                    <div class="text-gray-500">Rate:</div>
                    <div class="text-right"><?php echo isset($commissionRates[$level]) ? $commissionRates[$level] . '%' : 'N/A'; ?></div>

                    <div class="text-gray-500">Share:</div>
                    <div class="text-right"><?php echo round($percentage, 1); ?>%</div>
                  </div>
                </div>
              <?php endforeach; ?>

              <?php if (empty($commissionSummary['level_breakdown'])): ?>
                <div class="col-span-5 text-center text-gray-500 py-4">
                  No commission data available for level breakdown.
                </div>
              <?php endif; ?>
            </div>
          </div>

          <!-- Filters and Actions -->
          <div class="bg-white shadow rounded-lg p-4 mb-6 no-print">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Filter Commissions</h3>

            <form action="" method="get" class="grid grid-cols-1 md:grid-cols-4 gap-4">
              <input type="hidden" name="id" value="<?php echo $userId; ?>">

              <div>
                <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select name="status" id="status" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring focus:ring-primary-200 focus:ring-opacity-50">
                  <option value="" <?php echo $status === '' ? 'selected' : ''; ?>>All Statuses</option>
                  <option value="paid" <?php echo $status === 'paid' ? 'selected' : ''; ?>>Paid</option>
                  <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                  <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
              </div>

              <div>
                <label for="from" class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
                <input type="text" name="from" id="from" value="<?php echo htmlspecialchars($dateFrom); ?>"
                  class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring focus:ring-primary-200 focus:ring-opacity-50 datepicker">
              </div>

              <div>
                <label for="to" class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
                <input type="text" name="to" id="to" value="<?php echo htmlspecialchars($dateTo); ?>"
                  class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring focus:ring-primary-200 focus:ring-opacity-50 datepicker">
              </div>

              <div class="flex items-end space-x-2">
                <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white py-2 px-4 rounded shadow-sm">
                  <i class="fas fa-filter mr-1"></i> Apply Filters
                </button>

                <a href="user_commissions.php?id=<?php echo $userId; ?>" class="bg-gray-500 hover:bg-gray-600 text-white py-2 px-4 rounded shadow-sm">
                  <i class="fas fa-sync-alt mr-1"></i> Reset
                </a>
              </div>
            </form>
          </div>

          <!-- Commissions Table -->
          <div class="bg-white shadow rounded-lg overflow-hidden mb-6">
            <h3 class="text-lg font-semibold text-gray-800 p-4 border-b border-gray-200">Commission History</h3>

            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                  <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Level</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Source</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider no-print">Actions</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  <?php if (isset($commissionData['error'])): ?>
                    <tr>
                      <td colspan="7" class="px-6 py-4 text-center text-red-500">
                        Error loading commissions: <?php echo $commissionData['error']; ?>
                      </td>
                    </tr>
                  <?php elseif (empty($commissionData['commissions'])): ?>
                    <tr>
                      <td colspan="7" class="px-6 py-4 text-center text-gray-500">
                        No commissions found for this user.
                      </td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($commissionData['commissions'] as $commission): ?>
                      <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                          <?php echo date('M d, Y H:i', strtotime($commission['created_at'])); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                          <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full text-white level-badge-<?php echo $commission['level']; ?>">
                            Level <?php echo $commission['level']; ?>
                          </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                          <?php if ($commission['source_name']): ?>
                            <div>
                              <?php echo htmlspecialchars($commission['source_name']); ?>
                              <a href="user_tree.php?id=<?php echo $commission['referred_by']; ?>" class="text-blue-600 ml-1 text-xs hover:underline no-print">
                                <i class="fas fa-external-link-alt"></i>
                              </a>
                            </div>
                            <div class="text-xs text-gray-500">
                              <?php echo htmlspecialchars($commission['source_email']); ?>
                            </div>
                          <?php else: ?>
                            <span class="text-gray-500">Unknown Source</span>
                          <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                          <span class="text-sm text-gray-900">
                            <?php echo ucfirst($commission['commission_type'] ?? 'standard'); ?>
                          </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600 font-medium">
                          $<?php echo number_format($commission['commission_amount'], 2); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                          <?php
                          $statusClasses = [
                            'paid' => 'bg-green-100 text-green-800',
                            'pending' => 'bg-yellow-100 text-yellow-800',
                            'cancelled' => 'bg-red-100 text-red-800'
                          ];
                          $statusClass = $statusClasses[$commission['status']] ?? 'bg-gray-100 text-gray-800';
                          ?>
                          <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
                            <?php echo ucfirst($commission['status']); ?>
                          </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium no-print">
                          <a href="user_edit.php?id=<?php echo $commission['referred_by']; ?>" class="text-blue-600 hover:text-blue-900">
                            <i class="fas fa-user mr-1"></i> View Source
                          </a>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
              <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 no-print">
                <div class="flex items-center justify-between">
                  <div class="text-sm text-gray-700">
                    Showing <span class="font-medium"><?php echo $offset + 1; ?></span> to
                    <span class="font-medium"><?php echo min($offset + $itemsPerPage, $totalItems); ?></span> of
                    <span class="font-medium"><?php echo $totalItems; ?></span> commissions
                  </div>

                  <div class="flex space-x-2">
                    <?php if ($page > 1): ?>
                      <a href="user_commissions.php?id=<?php echo $userId; ?>&page=<?php echo $page - 1; ?>&status=<?php echo urlencode($status); ?>&from=<?php echo urlencode($dateFrom); ?>&to=<?php echo urlencode($dateTo); ?>"
                        class="px-3 py-1 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        Previous
                      </a>
                    <?php endif; ?>

                    <?php
                    // Show a range of pages around the current page
                    $range = 2;
                    $startPage = max(1, $page - $range);
                    $endPage = min($totalPages, $page + $range);

                    if ($startPage > 1) {
                      echo '<a href="user_commissions.php?id=' . $userId . '&page=1&status=' . urlencode($status) . '&from=' . urlencode($dateFrom) . '&to=' . urlencode($dateTo) . '" 
                                                 class="px-3 py-1 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                                 1
                                              </a>';
                      if ($startPage > 2) {
                        echo '<span class="px-3 py-1 text-gray-500">...</span>';
                      }
                    }

                    for ($i = $startPage; $i <= $endPage; $i++) {
                      echo '<a href="user_commissions.php?id=' . $userId . '&page=' . $i . '&status=' . urlencode($status) . '&from=' . urlencode($dateFrom) . '&to=' . urlencode($dateTo) . '" 
                                                 class="px-3 py-1 border border-gray-300 rounded-md text-sm font-medium ' . ($i == $page ? 'bg-blue-50 text-blue-600 border-blue-300' : 'text-gray-700 bg-white hover:bg-gray-50') . '">
                                                 ' . $i . '
                                              </a>';
                    }

                    if ($endPage < $totalPages) {
                      if ($endPage < $totalPages - 1) {
                        echo '<span class="px-3 py-1 text-gray-500">...</span>';
                      }
                      echo '<a href="user_commissions.php?id=' . $userId . '&page=' . $totalPages . '&status=' . urlencode($status) . '&from=' . urlencode($dateFrom) . '&to=' . urlencode($dateTo) . '" 
                                                 class="px-3 py-1 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                                 ' . $totalPages . '
                                              </a>';
                    }
                    ?>

                    <?php if ($page < $totalPages): ?>
                      <a href="user_commissions.php?id=<?php echo $userId; ?>&page=<?php echo $page + 1; ?>&status=<?php echo urlencode($status); ?>&from=<?php echo urlencode($dateFrom); ?>&to=<?php echo urlencode($dateTo); ?>"
                        class="px-3 py-1 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        Next
                      </a>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            <?php endif; ?>
          </div>

          <!-- Additional Actions Section -->
          <div class="bg-white shadow rounded-lg p-4 mb-6 no-print">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Additional Actions</h3>

            <div class="flex flex-wrap gap-2">
              <a href="user_transactions.php?id=<?php echo $userId; ?>" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                <i class="fas fa-exchange-alt mr-2"></i> View Transactions
              </a>
              <a href="user_tree.php?id=<?php echo $userId; ?>" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                <i class="fas fa-sitemap mr-2"></i> View Referral Tree
              </a>
              <a href="admin_messages.php?user_id=<?php echo $userId; ?>" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                <i class="fas fa-envelope mr-2"></i> Send Message
              </a>
              <a href="export_commissions.php?user_id=<?php echo $userId; ?>&status=<?php echo urlencode($status); ?>&from=<?php echo urlencode($dateFrom); ?>&to=<?php echo urlencode($dateTo); ?>" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                <i class="fas fa-file-export mr-2"></i> Export to CSV
              </a>
            </div>
          </div>

        <?php endif; ?>
      </main>

      <footer class="bg-white border-t border-gray-200 py-4 no-print">
        <div class="max-w-7xl mx-auto px-6">
          <p class="text-gray-500 text-center">© 2023 Referral System Admin Panel. All rights reserved.</p>
        </div>
      </footer>
    </div>
  </div>

  <script>
    // Initialize date pickers
    document.addEventListener('DOMContentLoaded', function() {
      flatpickr('.datepicker', {
        dateFormat: 'Y-m-d',
        allowInput: true
      });
    });
  </script>
</body>

</html>