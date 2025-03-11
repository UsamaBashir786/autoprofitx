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

// Default to showing 3 levels
$maxDepth = isset($_GET['depth']) ? intval($_GET['depth']) : 3;
$rootId = isset($_GET['root_id']) ? intval($_GET['root_id']) : null;

// Messages
$message = '';
$error = '';

/**
 * Get root users (those without referrers)
 * 
 * @return array Root users
 */
function getRootUsers()
{
  $conn = getConnection();
  $rootUsers = [];

  try {
    $stmt = $conn->prepare("
      SELECT id, full_name, email, phone, referral_code 
      FROM users 
      WHERE referred_by IS NULL OR referred_by = 0
      ORDER BY registration_date DESC
    ");
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
      // Get referral count
      $countStmt = $conn->prepare("SELECT COUNT(*) as total FROM users WHERE referred_by = ?");
      $countStmt->bind_param("i", $row['id']);
      $countStmt->execute();
      $countResult = $countStmt->get_result();
      $countData = $countResult->fetch_assoc();

      $row['referral_count'] = $countData['total'];

      $rootUsers[] = $row;
    }

    return $rootUsers;
  } catch (Exception $e) {
    error_log("Error getting root users: " . $e->getMessage());
    return [];
  } finally {
    $conn->close();
  }
}

/**
 * Get complete tree data
 * 
 * @param int $rootId Optional root user ID to start from
 * @param int $maxDepth Maximum depth to retrieve
 * @return array Tree data structure
 */
function getFullTreeData($rootId = null, $maxDepth = 3)
{
  $conn = getConnection();

  try {
    if ($rootId) {
      // Check if specified root user exists
      $stmt = $conn->prepare("SELECT id, full_name, email, phone, referral_code FROM users WHERE id = ?");
      $stmt->bind_param("i", $rootId);
      $stmt->execute();
      $result = $stmt->get_result();

      if ($result->num_rows === 0) {
        return ['error' => 'User not found'];
      }

      $rootUser = $result->fetch_assoc();

      // Get wallet data
      $stmt = $conn->prepare("SELECT balance FROM wallets WHERE user_id = ?");
      $stmt->bind_param("i", $rootId);
      $stmt->execute();
      $result = $stmt->get_result();
      $wallet = $result->fetch_assoc();

      $rootUser['balance'] = $wallet ? $wallet['balance'] : 0;

      // Get commissions data
      $stmt = $conn->prepare("
        SELECT 
          COUNT(*) as count,
          COALESCE(SUM(commission_amount), 0) as total_earned
        FROM tree_commissions
        WHERE user_id = ? AND status = 'paid'
      ");
      $stmt->bind_param("i", $rootId);
      $stmt->execute();
      $result = $stmt->get_result();
      $commissions = $result->fetch_assoc();

      $rootUser['commissions_count'] = $commissions['count'];
      $rootUser['total_earned'] = $commissions['total_earned'];

      // Count number of referrals
      $countStmt = $conn->prepare("SELECT COUNT(*) as total FROM users WHERE referred_by = ?");
      $countStmt->bind_param("i", $rootId);
      $countStmt->execute();
      $countResult = $countStmt->get_result();
      $countData = $countResult->fetch_assoc();

      $rootUser['referral_count'] = $countData['total'];

      // Get referrals tree
      $treeData = buildRecursiveTree($conn, $rootId, $maxDepth);

      return [
        'root' => $rootUser,
        'tree' => $treeData
      ];
    } else {
      // Get all root users
      $rootUsers = getRootUsers();

      // Build tree for each root user
      $fullTree = [];
      foreach ($rootUsers as $user) {
        $userId = $user['id'];

        // Get wallet data
        $stmt = $conn->prepare("SELECT balance FROM wallets WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $wallet = $result->fetch_assoc();

        $user['balance'] = $wallet ? $wallet['balance'] : 0;

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

        $user['commissions_count'] = $commissions['count'];
        $user['total_earned'] = $commissions['total_earned'];

        // Get referrals tree (limited to 1 level for overview)
        $user['children'] = buildRecursiveTree($conn, $userId, 1);

        $fullTree[] = $user;
      }

      return [
        'roots' => $fullTree
      ];
    }
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
    SELECT u.id, u.full_name, u.email, u.referral_code, u.registration_date
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

// Get system statistics
function getSystemStats()
{
  $conn = getConnection();
  $stats = [
    'total_users' => 0,
    'total_commissions' => 0,
    'total_balance' => 0,
    'recent_registrations' => 0,
    'root_users' => 0
  ];

  try {
    // Total users
    $result = $conn->query("SELECT COUNT(*) as count FROM users");
    if ($row = $result->fetch_assoc()) {
      $stats['total_users'] = $row['count'];
    }

    // Total commissions paid
    $result = $conn->query("SELECT COALESCE(SUM(commission_amount), 0) as total FROM tree_commissions WHERE status = 'paid'");
    if ($row = $result->fetch_assoc()) {
      $stats['total_commissions'] = $row['total'];
    }

    // Total balance
    $result = $conn->query("SELECT COALESCE(SUM(balance), 0) as total FROM wallets");
    if ($row = $result->fetch_assoc()) {
      $stats['total_balance'] = $row['total'];
    }

    // Recent registrations (last 30 days)
    $result = $conn->query("SELECT COUNT(*) as count FROM users WHERE registration_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    if ($row = $result->fetch_assoc()) {
      $stats['recent_registrations'] = $row['count'];
    }

    // Root users count
    $result = $conn->query("SELECT COUNT(*) as count FROM users WHERE referred_by IS NULL OR referred_by = 0");
    if ($row = $result->fetch_assoc()) {
      $stats['root_users'] = $row['count'];
    }

    return $stats;
  } catch (Exception $e) {
    error_log("Error getting system stats: " . $e->getMessage());
    return $stats;
  } finally {
    $conn->close();
  }
}

// Get tree data
$treeData = getFullTreeData($rootId, $maxDepth);
$unreadCount = getUnreadMessagesCount();
$commissionRates = getCommissionRates();
$systemStats = getSystemStats();
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Full Tree View - Admin Panel</title>
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
          <h1 class="text-2xl font-bold text-gray-900">Full Tree View</h1>

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

          <!-- System Statistics -->
          <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow p-4">
              <div class="flex items-center">
                <div class="p-3 rounded-full bg-blue-100 text-blue-500">
                  <i class="fas fa-users text-xl"></i>
                </div>
                <div class="ml-4">
                  <p class="text-gray-500 text-sm">Total Users</p>
                  <p class="text-xl font-semibold"><?php echo number_format($systemStats['total_users']); ?></p>
                </div>
              </div>
            </div>

            <div class="bg-white rounded-lg shadow p-4">
              <div class="flex items-center">
                <div class="p-3 rounded-full bg-green-100 text-green-500">
                  <i class="fas fa-dollar-sign text-xl"></i>
                </div>
                <div class="ml-4">
                  <p class="text-gray-500 text-sm">Total Commissions Paid</p>
                  <p class="text-xl font-semibold">$<?php echo number_format($systemStats['total_commissions'], 2); ?></p>
                </div>
              </div>
            </div>

            <div class="bg-white rounded-lg shadow p-4">
              <div class="flex items-center">
                <div class="p-3 rounded-full bg-yellow-100 text-yellow-500">
                  <i class="fas fa-wallet text-xl"></i>
                </div>
                <div class="ml-4">
                  <p class="text-gray-500 text-sm">Total Balance</p>
                  <p class="text-xl font-semibold">$<?php echo number_format($systemStats['total_balance'], 2); ?></p>
                </div>
              </div>
            </div>

            <div class="bg-white rounded-lg shadow p-4">
              <div class="flex items-center">
                <div class="p-3 rounded-full bg-purple-100 text-purple-500">
                  <i class="fas fa-user-plus text-xl"></i>
                </div>
                <div class="ml-4">
                  <p class="text-gray-500 text-sm">New Users (30 Days)</p>
                  <p class="text-xl font-semibold"><?php echo number_format($systemStats['recent_registrations']); ?></p>
                </div>
              </div>
            </div>
          </div>

          <!-- Controls section -->
          <div class="bg-white shadow rounded-lg p-4 mb-6 no-print">
            <div class="flex flex-wrap items-center justify-between">
              <div>
                <h2 class="text-xl font-semibold text-gray-800">
                  <?php if ($rootId): ?>
                    Referral Tree for: <?php echo htmlspecialchars($treeData['root']['full_name']); ?>
                    <span class="text-sm font-normal text-gray-500">
                      (ID: <?php echo $treeData['root']['id']; ?>)
                    </span>
                  <?php else: ?>
                    Complete Referral Network
                    <span class="text-sm font-normal text-gray-500">
                      (<?php echo $systemStats['root_users']; ?> root users)
                    </span>
                  <?php endif; ?>
                </h2>
              </div>

              <div class="flex space-x-3 mt-4 md:mt-0">
                <?php if ($rootId): ?>
                  <!-- Depth selector -->
                  <div>
                    <form action="" method="get" class="flex items-center">
                      <input type="hidden" name="root_id" value="<?php echo $rootId; ?>">
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

                  <a href="admin_full_tree.php" class="bg-gray-500 hover:bg-gray-600 text-white py-2 px-4 rounded shadow-sm">
                    <i class="fas fa-reply mr-1"></i> Back to All Roots
                  </a>
                <?php endif; ?>

                <button onclick="window.print()" class="bg-blue-500 hover:bg-blue-600 text-white py-2 px-4 rounded shadow-sm">
                  <i class="fas fa-print mr-1"></i> Print
                </button>
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

          <?php if ($rootId): ?>
            <!-- Single Root Tree Visualization -->
            <div class="bg-white shadow rounded-lg p-6 overflow-x-auto mb-6">
              <h3 class="text-lg font-semibold text-gray-800 mb-4">Referral Tree</h3>

              <!-- Root node -->
              <div class="flex justify-center mb-12">
                <div class="relative bg-blue-100 border border-blue-300 rounded-lg p-4 w-64 text-center">
                  <span class="absolute -top-8 left-1/2 transform -translate-x-1/2 px-2 py-1 rounded-full text-xs text-white level-badge-1">Root</span>
                  <h4 class="font-bold"><?php echo htmlspecialchars($treeData['root']['full_name']); ?></h4>
                  <p class="text-sm text-gray-600"><?php echo htmlspecialchars($treeData['root']['email']); ?></p>
                  <p class="mt-1"><span class="font-semibold">Balance:</span> $<?php echo number_format($treeData['root']['balance'], 2); ?></p>
                  <p><span class="font-semibold">Earned:</span> $<?php echo number_format($treeData['root']['total_earned'], 2); ?></p>
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

                        <!-- Additional levels would be rendered recursively similar to user_tree.php -->
                        <?php if (!empty($level1User['children']) && $maxDepth >= 2): ?>
                          <!-- Similar structure to user_tree.php for additional levels -->
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
          <?php else: ?>
            <!-- Multiple Root Users Overview -->
            <div class="bg-white shadow rounded-lg p-6 mb-6">
              <h3 class="text-lg font-semibold text-gray-800 mb-4">Root Users (Top Level Network)</h3>

              <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                  <thead class="bg-gray-50">
                    <tr>
                      <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                      <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact Info</th>
                      <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Referral Code</th>
                      <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Referrals</th>
                      <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Balance</th>
                      <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Earnings</th>
                      <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                  </thead>
                  <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (!empty($treeData['roots'])): ?>
                      <?php foreach ($treeData['roots'] as $rootUser): ?>
                        <tr class="hover:bg-gray-50">
                          <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                              <div class="flex-shrink-0 h-10 w-10 bg-blue-100 rounded-full flex items-center justify-center">
                                <span class="text-blue-700 font-bold"><?php echo substr($rootUser['full_name'], 0, 1); ?></span>
                              </div>
                              <div class="ml-4">
                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($rootUser['full_name']); ?></div>
                                <div class="text-sm text-gray-500">ID: <?php echo $rootUser['id']; ?></div>
                              </div>
                            </div>
                          </td>
                          <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($rootUser['email']); ?></div>
                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($rootUser['phone'] ?? 'No phone'); ?></div>
                          </td>
                          <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                              <?php echo htmlspecialchars($rootUser['referral_code']); ?>
                            </span>
                          </td>
                          <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo $rootUser['referral_count']; ?> direct
                            <?php if ($rootUser['referral_count'] > 0): ?>
                              <div class="mt-1 text-xs">
                                <a href="admin_full_tree.php?root_id=<?php echo $rootUser['id']; ?>" class="text-blue-600 hover:underline">
                                  <i class="fas fa-sitemap mr-1"></i> View tree
                                </a>
                              </div>
                            <?php endif; ?>
                          </td>
                          <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            $<?php echo number_format($rootUser['balance'], 2); ?>
                          </td>
                          <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            $<?php echo number_format($rootUser['total_earned'], 2); ?>
                          </td>
                          <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <a href="user_tree.php?id=<?php echo $rootUser['id']; ?>" class="text-blue-600 hover:text-blue-900 mr-3">
                              <i class="fas fa-eye"></i> View
                            </a>
                            <a href="admin_dashboard.php?tab=users&action=edit&id=<?php echo $rootUser['id']; ?>" class="text-green-600 hover:text-green-900">
                              <i class="fas fa-edit"></i> Edit
                            </a>
                          </td>
                        </tr>

                        <!-- First level children if available (simplified view) -->
                        <?php if (!empty($rootUser['children'])): ?>
                          <?php foreach ($rootUser['children'] as $childUser): ?>
                            <tr class="bg-gray-50 hover:bg-gray-100">
                              <td class="px-6 py-3 whitespace-nowrap pl-16">
                                <div class="flex items-center">
                                  <div class="flex-shrink-0 h-8 w-8 bg-green-100 rounded-full flex items-center justify-center">
                                    <span class="text-green-700 font-bold text-xs"><?php echo substr($childUser['full_name'], 0, 1); ?></span>
                                  </div>
                                  <div class="ml-3">
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($childUser['full_name']); ?></div>
                                    <div class="text-xs text-gray-500">ID: <?php echo $childUser['id']; ?></div>
                                  </div>
                                </div>
                              </td>
                              <td class="px-6 py-3 whitespace-nowrap">
                                <div class="text-sm text-gray-900"><?php echo htmlspecialchars($childUser['email']); ?></div>
                              </td>
                              <td class="px-6 py-3 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                  <?php echo htmlspecialchars($childUser['referral_code']); ?>
                                </span>
                              </td>
                              <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-500">
                                <?php echo $childUser['referral_count']; ?> direct
                              </td>
                              <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-500">
                                $<?php echo number_format($childUser['balance'], 2); ?>
                              </td>
                              <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-500">
                                $<?php echo number_format($childUser['total_earned'], 2); ?>
                              </td>
                              <td class="px-6 py-3 whitespace-nowrap text-right text-sm font-medium">
                                <a href="user_tree.php?id=<?php echo $childUser['id']; ?>" class="text-blue-600 hover:text-blue-900">
                                  <i class="fas fa-eye"></i> View
                                </a>
                              </td>
                            </tr>
                          <?php endforeach; ?>
                          <?php if (count($rootUser['children']) < $rootUser['referral_count']): ?>
                            <tr class="bg-gray-50">
                              <td colspan="7" class="px-6 py-2 whitespace-nowrap pl-16 text-sm text-gray-500 italic">
                                ... and <?php echo $rootUser['referral_count'] - count($rootUser['children']); ?> more referrals.
                                <a href="admin_full_tree.php?root_id=<?php echo $rootUser['id']; ?>" class="text-blue-600 hover:underline ml-2">
                                  View full tree
                                </a>
                              </td>
                            </tr>
                          <?php endif; ?>
                        <?php endif; ?>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <tr>
                        <td colspan="7" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                          No root users found in the system.
                        </td>
                      </tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          <?php endif; ?>

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
      // Future JavaScript enhancements could be added here
    });
  </script>
</body>

</html>