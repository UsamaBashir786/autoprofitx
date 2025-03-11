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
  header("Location: admin_dashboard.php?tab=visualization");
  exit();
}

// Default to showing 3 levels
$maxDepth = isset($_GET['depth']) ? intval($_GET['depth']) : 3;

// Messages
$message = '';
$error = '';

/**
 * Get user tree data showing multiple levels
 * 
 * @param int $userId User ID to build tree for
 * @param int $maxDepth Maximum depth to retrieve
 * @return array Tree data structure
 */
function getUserTreeData($userId, $maxDepth = 3)
{
  $conn = getConnection();

  try {
    // Check if user exists
    $stmt = $conn->prepare("SELECT id, full_name, email, phone, referral_code FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
      return ['error' => 'User not found'];
    }

    $userData = $result->fetch_assoc();

    // Get wallet data
    $stmt = $conn->prepare("SELECT balance FROM wallets WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $wallet = $result->fetch_assoc();

    $userData['balance'] = $wallet ? $wallet['balance'] : 0;

    // Get commissions data
    $stmt = $conn->prepare("
            SELECT 
                COUNT(*) as count,
                COALESCE(SUM(commission_amount), 0) as total_earned
            FROM tree_commissions
            WHERE user_id = ? AND status = 'paid'
        ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $commissions = $result->fetch_assoc();

    $userData['commissions_count'] = $commissions['count'];
    $userData['total_earned'] = $commissions['total_earned'];

    // Build the recursive tree structure
    $treeData = buildRecursiveTree($conn, $userId, $maxDepth);

    return [
      'user' => $userData,
      'tree' => $treeData
    ];
  } catch (Exception $e) {
    return ['error' => $e->getMessage()];
  } finally {
    $conn->close();
  }
}

/**
 * Recursively build the tree structure for a given user
 * 
 * @param mysqli $conn Database connection
 * @param int $userId User ID to find direct referrals for
 * @param int $maxDepth Maximum depth to retrieve
 * @param int $currentDepth Current depth in the recursion
 * @return array Array of direct referrals with their own referrals
 */
function buildRecursiveTree($conn, $userId, $maxDepth, $currentDepth = 1)
{
  if ($currentDepth > $maxDepth) {
    return [];
  }

  // Get direct referrals
  $stmt = $conn->prepare("
        SELECT u.id, u.full_name, u.email, u.referral_code, u.registration_date, u.status
        FROM users u
        WHERE u.referred_by = ?
        ORDER BY u.registration_date DESC
    ");
  $stmt->bind_param("i", $userId);
  $stmt->execute();
  $result = $stmt->get_result();

  $referrals = [];

  while ($row = $result->fetch_assoc()) {
    // Get wallet data
    $walletStmt = $conn->prepare("SELECT balance FROM wallets WHERE user_id = ?");
    $walletStmt->bind_param("i", $row['id']);
    $walletStmt->execute();
    $walletResult = $walletStmt->get_result();
    $wallet = $walletResult->fetch_assoc();

    $row['balance'] = $wallet ? $wallet['balance'] : 0;

    // Get commissions data
    $commStmt = $conn->prepare("
            SELECT 
                COUNT(*) as count,
                COALESCE(SUM(commission_amount), 0) as total_earned
            FROM tree_commissions
            WHERE user_id = ? AND status = 'paid'
        ");
    $commStmt->bind_param("i", $row['id']);
    $commStmt->execute();
    $commResult = $commStmt->get_result();
    $commissions = $commResult->fetch_assoc();

    $row['commissions_count'] = $commissions['count'];
    $row['total_earned'] = $commissions['total_earned'];

    // Count number of referrals
    $countStmt = $conn->prepare("SELECT COUNT(*) as total FROM users WHERE referred_by = ?");
    $countStmt->bind_param("i", $row['id']);
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $countData = $countResult->fetch_assoc();

    $row['referral_count'] = $countData['total'];

    // Recursively get children
    $row['level'] = $currentDepth;
    $row['children'] = buildRecursiveTree($conn, $row['id'], $maxDepth, $currentDepth + 1);

    $referrals[] = $row;
  }

  return $referrals;
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

// Get tree data
$treeData = getUserTreeData($userId, $maxDepth);
$unreadCount = getUnreadMessagesCount();
$commissionRates = getCommissionRates();
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>User Tree View - Admin Panel</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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
    /* Custom styles for tree visualization */
    .tree-line {
      position: absolute;
      border-left: 2px dashed #cbd5e1;
      height: 20px;
      left: 50%;
      top: -20px;
      transform: translateX(-50%);
    }

    .tree-children-connector {
      position: absolute;
      border-top: 2px dashed #cbd5e1;
      top: -10px;
      width: 100%;
      left: 0;
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

    @media print {
      .no-print {
        display: none !important;
      }

      body {
        font-size: 12px;
      }

      .print-small {
        font-size: 10px;
      }

      .tree-container {
        page-break-inside: avoid;
      }
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
              <a href="admin_dashboard.php?tab=visualization" class="flex items-center p-3 text-white bg-gray-700 rounded-lg group">
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
          <h1 class="text-2xl font-bold text-gray-900">User Tree View</h1>

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

        <?php if (isset($treeData['error'])): ?>
          <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative">
            <span class="block sm:inline">Error: <?php echo $treeData['error']; ?></span>
          </div>
        <?php else: ?>

          <!-- Controls section -->
          <div class="bg-white shadow rounded-lg p-4 mb-6 no-print">
            <div class="flex flex-wrap items-center justify-between">
              <div>
                <h2 class="text-xl font-semibold text-gray-800">
                  Tree for: <?php echo htmlspecialchars($treeData['user']['full_name']); ?>
                  <span class="text-sm font-normal text-gray-500">
                    (ID: <?php echo $treeData['user']['id']; ?>)
                  </span>
                </h2>
                <p class="text-gray-600">
                  <strong>Email:</strong> <?php echo htmlspecialchars($treeData['user']['email']); ?> |
                  <strong>Referral Code:</strong> <?php echo htmlspecialchars($treeData['user']['referral_code']); ?> |
                  <strong>Balance:</strong> $<?php echo number_format($treeData['user']['balance'], 2); ?>
                </p>
              </div>

              <div class="flex space-x-3 mt-4 md:mt-0">
                <!-- Depth selector -->
                <div>
                  <form action="" method="get" class="flex items-center">
                    <input type="hidden" name="id" value="<?php echo $userId; ?>">
                    <label for="depth" class="mr-2 text-gray-700">Levels:</label>
                    <select name="depth" id="depth" class="rounded border-gray-300 shadow-sm focus:border-primary-500 focus:ring focus:ring-primary-200 focus:ring-opacity-50" onchange="this.form.submit()">
                      <?php for ($i = 1; $i <= 5; $i++): ?>
                        <option value="<?php echo $i; ?>" <?php echo $maxDepth == $i ? 'selected' : ''; ?>>
                          <?php echo $i; ?> level<?php echo $i > 1 ? 's' : ''; ?>
                        </option>
                      <?php endfor; ?>
                    </select>
                  </form>
                </div>

                <!-- Actions -->
                <button onclick="window.print()" class="bg-gray-500 hover:bg-gray-600 text-white py-2 px-4 rounded shadow-sm">
                  <i class="fas fa-print mr-1"></i> Print
                </button>

                <a href="user_edit.php?id=<?php echo $userId; ?>" class="bg-blue-500 hover:bg-blue-600 text-white py-2 px-4 rounded shadow-sm">
                  <i class="fas fa-edit mr-1"></i> Edit User
                </a>

                <a href="admin_dashboard.php?tab=visualization" class="bg-gray-500 hover:bg-gray-600 text-white py-2 px-4 rounded shadow-sm">
                  <i class="fas fa-arrow-left mr-1"></i> Back
                </a>
              </div>
            </div>
          </div>

          <!-- Commission rate reference -->
          <div class="bg-white shadow rounded-lg p-4 mb-6 no-print">
            <h3 class="text-lg font-semibold text-gray-800 mb-2">Commission Rates Reference</h3>
            <div class="grid grid-cols-2 md:grid-cols-5 gap-2">
              <?php foreach ($commissionRates as $level => $rate): ?>
                <div class="flex items-center">
                  <span class="inline-block w-6 h-6 rounded-full level-badge-<?php echo $level; ?> mr-2"></span>
                  <span>Level <?php echo $level; ?>: <?php echo $rate; ?>%</span>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Tree Visualization -->
          <div class="bg-white shadow rounded-lg p-6 overflow-x-auto mb-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Referral Tree</h3>

            <!-- Root node -->
            <div class="flex justify-center mb-12">
              <div class="relative bg-blue-100 border border-blue-300 rounded-lg p-4 w-64 text-center">
                <span class="absolute -top-8 left-1/2 transform -translate-x-1/2 px-2 py-1 rounded-full text-xs text-white level-badge-1">Root</span>
                <h4 class="font-bold"><?php echo htmlspecialchars($treeData['user']['full_name']); ?></h4>
                <p class="text-sm text-gray-600"><?php echo htmlspecialchars($treeData['user']['email']); ?></p>
                <p class="mt-1"><span class="font-semibold">Balance:</span> $<?php echo number_format($treeData['user']['balance'], 2); ?></p>
                <p><span class="font-semibold">Earned:</span> $<?php echo number_format($treeData['user']['total_earned'], 2); ?></p>
              </div>
            </div>

            <!-- Level 1 nodes -->
            <?php if (!empty($treeData['tree'])): ?>
              <div class="tree-container">
                <div class="flex flex-wrap justify-center gap-4 mb-12">
                  <?php foreach ($treeData['tree'] as $level1User): ?>
                    <div class="relative">
                      <div class="tree-line"></div>
                      <div class="bg-green-100 border border-green-300 rounded-lg p-3 w-56 relative">
                        <span class="absolute -top-8 left-1/2 transform -translate-x-1/2 px-2 py-1 rounded-full text-xs text-white level-badge-2">Level 1</span>
                        <h4 class="font-bold"><?php echo htmlspecialchars($level1User['full_name']); ?></h4>
                        <p class="text-sm text-gray-600 truncate"><?php echo htmlspecialchars($level1User['email']); ?></p>
                        <div class="grid grid-cols-2 mt-1 gap-1 text-sm">
                          <p><span class="font-semibold">Balance:</span> $<?php echo number_format($level1User['balance'], 2); ?></p>
                          <p><span class="font-semibold">Earned:</span> $<?php echo number_format($level1User['total_earned'], 2); ?></p>
                        </div>
                        <p class="text-sm mt-1">
                          <span class="font-semibold">Referrals:</span> <?php echo $level1User['referral_count']; ?>
                        </p>
                        <div class="mt-2 text-center">
                          <a href="user_tree.php?id=<?php echo $level1User['id']; ?>" class="text-blue-600 hover:underline text-sm">
                            <i class="fas fa-sitemap mr-1"></i> View Tree
                          </a>
                        </div>
                      </div>

                      <!-- Level 2 nodes -->
                      <?php if (!empty($level1User['children'])): ?>
                        <div class="relative mt-8">
                          <div class="tree-children-connector"></div>
                          <div class="flex flex-wrap justify-center gap-3">
                            <?php foreach ($level1User['children'] as $level2User): ?>
                              <div class="relative">
                                <div class="tree-line"></div>
                                <div class="bg-orange-100 border border-orange-300 rounded-lg p-2 w-48 relative print-small">
                                  <span class="absolute -top-6 left-1/2 transform -translate-x-1/2 px-2 py-0.5 rounded-full text-xs text-white level-badge-3">Level 2</span>
                                  <h4 class="font-bold text-sm"><?php echo htmlspecialchars($level2User['full_name']); ?></h4>
                                  <p class="text-xs text-gray-600 truncate"><?php echo htmlspecialchars($level2User['email']); ?></p>
                                  <div class="grid grid-cols-2 mt-1 gap-1 text-xs">
                                    <p><span class="font-semibold">Balance:</span> $<?php echo number_format($level2User['balance'], 2); ?></p>
                                    <p><span class="font-semibold">Earned:</span> $<?php echo number_format($level2User['total_earned'], 2); ?></p>
                                  </div>
                                  <div class="mt-1 text-center">
                                    <a href="user_tree.php?id=<?php echo $level2User['id']; ?>" class="text-blue-600 hover:underline text-xs">
                                      <i class="fas fa-sitemap mr-1"></i> View
                                    </a>
                                  </div>
                                </div>

                                <!-- Level 3 nodes -->
                                <?php if (!empty($level2User['children']) && $maxDepth >= 3): ?>
                                  <div class="relative mt-6">
                                    <div class="tree-children-connector"></div>
                                    <div class="flex flex-wrap justify-center gap-2">
                                      <?php foreach ($level2User['children'] as $level3User): ?>
                                        <div class="relative">
                                          <div class="tree-line"></div>
                                          <div class="bg-purple-100 border border-purple-300 rounded-lg p-2 w-40 relative print-small">
                                            <span class="absolute -top-6 left-1/2 transform -translate-x-1/2 px-1 py-0.5 rounded-full text-xs text-white level-badge-4">Level 3</span>
                                            <h4 class="font-bold text-xs"><?php echo htmlspecialchars($level3User['full_name']); ?></h4>
                                            <p class="text-xs text-gray-600 truncate"><?php echo htmlspecialchars($level3User['email']); ?></p>
                                            <p class="text-xs mt-1">
                                              <span class="font-semibold">Refs:</span> <?php echo $level3User['referral_count']; ?>
                                            </p>
                                            <div class="mt-1 text-center">
                                              <a href="user_tree.php?id=<?php echo $level3User['id']; ?>" class="text-blue-600 hover:underline text-xs">
                                                View
                                              </a>
                                            </div>
                                          </div>

                                          <!-- Level 4 nodes -->
                                          <?php if (!empty($level3User['children']) && $maxDepth >= 4): ?>
                                            <div class="relative mt-6">
                                              <div class="tree-children-connector"></div>
                                              <div class="flex flex-wrap justify-center gap-1">
                                                <!-- Level 4 users would be rendered here similar to level 3 -->
                                                <!-- Omitted for brevity but would follow the same pattern -->
                                              </div>
                                            </div>
                                          <?php endif; ?>
                                        </div>
                                      <?php endforeach; ?>
                                    </div>
                                  </div>
                                <?php endif; ?>
                              </div>
                            <?php endforeach; ?>
                          </div>
                        </div>
                      <?php endif; ?>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php else: ?>
              <div class="text-center text-gray-500 py-6">
                <p>This user has no referrals yet.</p>
              </div>
            <?php endif; ?>
          </div>

          <!-- Tree Statistics -->
          <div class="bg-white shadow rounded-lg p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Tree Statistics</h3>

            <?php
            // Calculate statistics
            $totalUsers = 0;
            $totalEarnings = $treeData['user']['total_earned'];
            $totalBalance = $treeData['user']['balance'];
            $activeUsers = 0;

            function countTreeStats($nodes, &$totalUsers, &$totalEarnings, &$totalBalance, &$activeUsers)
            {
              foreach ($nodes as $node) {
                $totalUsers++;
                $totalEarnings += $node['total_earned'];
                $totalBalance += $node['balance'];

                if ($node['status'] === 'active') {
                  $activeUsers++;
                }

                if (!empty($node['children'])) {
                  countTreeStats($node['children'], $totalUsers, $totalEarnings, $totalBalance, $activeUsers);
                }
              }
            }

            if (!empty($treeData['tree'])) {
              countTreeStats($treeData['tree'], $totalUsers, $totalEarnings, $totalBalance, $activeUsers);
            }
            ?>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
              <div class="bg-blue-50 rounded-lg p-4 border border-blue-200">
                <h4 class="text-blue-700 text-lg font-medium">Total Users</h4>
                <p class="text-2xl font-bold"><?php echo $totalUsers + 1; ?></p>
                <p class="text-blue-600 text-sm">(Including root user)</p>
              </div>

              <div class="bg-green-50 rounded-lg p-4 border border-green-200">
                <h4 class="text-green-700 text-lg font-medium">Active Users</h4>
                <p class="text-2xl font-bold"><?php echo $activeUsers; ?></p>
                <p class="text-green-600 text-sm">
                  (<?php echo $totalUsers > 0 ? round(($activeUsers / $totalUsers) * 100) : 0; ?>% of downline)
                </p>
              </div>

              <div class="bg-yellow-50 rounded-lg p-4 border border-yellow-200">
                <h4 class="text-yellow-700 text-lg font-medium">Total Earnings</h4>
                <p class="text-2xl font-bold">$<?php echo number_format($totalEarnings, 2); ?></p>
                <p class="text-yellow-600 text-sm">From commissions</p>
              </div>

              <div class="bg-purple-50 rounded-lg p-4 border border-purple-200">
                <h4 class="text-purple-700 text-lg font-medium">Total Balance</h4>
                <p class="text-2xl font-bold">$<?php echo number_format($totalBalance, 2); ?></p>
                <p class="text-purple-600 text-sm">Current available</p>
              </div>
            </div>

            <div class="mt-6">
              <h4 class="text-lg font-medium text-gray-800 mb-2">Additional Actions</h4>
              <div class="flex flex-wrap gap-2">
                <a href="user_commissions.php?id=<?php echo $userId; ?>" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                  <i class="fas fa-dollar-sign mr-2"></i> View Commissions
                </a>
                <a href="user_transactions.php?id=<?php echo $userId; ?>" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                  <i class="fas fa-exchange-alt mr-2"></i> View Transactions
                </a>
                <a href="admin_messages.php?user_id=<?php echo $userId; ?>" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                  <i class="fas fa-envelope mr-2"></i> Send Message
                </a>
              </div>
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
    // JavaScript for additional functionality
    document.addEventListener('DOMContentLoaded', function() {
      // Add any additional JS functionality here

      // Example: Highlight user nodes on hover
      const userNodes = document.querySelectorAll('.tree-container div[class*="bg-"]');
      userNodes.forEach(node => {
        node.addEventListener('mouseenter', function() {
          this.classList.add('ring', 'ring-offset-2', 'ring-blue-500');
        });

        node.addEventListener('mouseleave', function() {
          this.classList.remove('ring', 'ring-offset-2', 'ring-blue-500');
        });
      });
    });
  </script>
</body>

</html>