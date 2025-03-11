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

// Initialize variables
$message = '';
$error = '';

// Handle commission rate updates
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_commission_rates'])) {
  $conn = getConnection();

  try {
    $conn->begin_transaction();

    // Clear existing rates
    $conn->query("TRUNCATE TABLE referral_structure");

    // Insert new rates
    $stmt = $conn->prepare("INSERT INTO referral_structure (level, commission_rate) VALUES (?, ?)");

    for ($i = 1; $i <= 10; $i++) {
      if (isset($_POST["level_$i"]) && is_numeric($_POST["rate_$i"])) {
        $level = $i;
        $rate = floatval($_POST["rate_$i"]);

        if ($rate >= 0) {
          $stmt->bind_param("id", $level, $rate);
          $stmt->execute();
        }
      }
    }

    $conn->commit();
    $message = "Commission rates updated successfully.";
  } catch (Exception $e) {
    $conn->rollback();
    $error = "Error updating commission rates: " . $e->getMessage();
  } finally {
    $conn->close();
  }
}

// Get statistics for the admin dashboard
function getTreeStatistics()
{
  $conn = getConnection();
  $stats = [
    'total_users' => 0,
    'users_with_referrals' => 0,
    'total_commissions_paid' => 0,
    'avg_commission_per_user' => 0,
    'level_stats' => []
  ];

  try {
    // Total users in the system
    $result = $conn->query("SELECT COUNT(*) as total FROM users");
    if ($result && $row = $result->fetch_assoc()) {
      $stats['total_users'] = $row['total'];
    }

    // Total users with referrals
    $result = $conn->query("SELECT COUNT(DISTINCT parent_id) as total FROM referral_tree");
    if ($result && $row = $result->fetch_assoc()) {
      $stats['users_with_referrals'] = $row['total'];
    }

    // Total commissions paid
    $result = $conn->query("SELECT COALESCE(SUM(commission_amount), 0) as total FROM tree_commissions WHERE status = 'paid'");
    if ($result && $row = $result->fetch_assoc()) {
      $stats['total_commissions_paid'] = $row['total'];
    }

    // Average commission per user
    if ($stats['users_with_referrals'] > 0) {
      $stats['avg_commission_per_user'] = $stats['total_commissions_paid'] / $stats['users_with_referrals'];
    }

    // Level-wise statistics
    $result = $conn->query("
            SELECT level, 
                COUNT(*) as relationship_count, 
                COALESCE(SUM(tc.commission_amount), 0) as level_commission
            FROM referral_tree rt
            LEFT JOIN tree_commissions tc ON rt.level = tc.level
            GROUP BY level
            ORDER BY level
        ");

    if ($result) {
      $stats['level_stats'] = [];
      while ($row = $result->fetch_assoc()) {
        $stats['level_stats'][] = $row;
      }
    }

    return $stats;
  } catch (Exception $e) {
    error_log("Error in getTreeStatistics: " . $e->getMessage());
    return $stats; // Return the default stats with zeros
  } finally {
    $conn->close();
  }
}

// Get current commission rates
function getCommissionRates()
{
  $conn = getConnection();
  $rates = [];

  try {
    $result = $conn->query("SELECT level, commission_rate FROM referral_structure ORDER BY level");

    if ($result) {
      while ($row = $result->fetch_assoc()) {
        $rates[$row['level']] = $row['commission_rate'];
      }
    }

    return $rates;
  } catch (Exception $e) {
    error_log("Error in getCommissionRates: " . $e->getMessage());
    return [];
  } finally {
    $conn->close();
  }
}

// Get top referrers
function getTopReferrers($limit = 10)
{
  $conn = getConnection();
  $referrers = [];

  try {
    $result = $conn->query("
            SELECT 
                u.id,
                u.full_name,
                u.email,
                u.phone,
                u.referral_code,
                COUNT(DISTINCT rt.user_id) as referral_count,
                COALESCE(SUM(tc.commission_amount), 0) as total_earnings
            FROM 
                users u
                LEFT JOIN referral_tree rt ON u.id = rt.parent_id
                LEFT JOIN tree_commissions tc ON u.id = tc.user_id
            GROUP BY 
                u.id, u.full_name, u.email, u.phone, u.referral_code
            ORDER BY 
                referral_count DESC, total_earnings DESC
            LIMIT $limit
        ");

    if ($result) {
      while ($row = $result->fetch_assoc()) {
        $referrers[] = $row;
      }
    }

    return $referrers;
  } catch (Exception $e) {
    error_log("Error in getTopReferrers: " . $e->getMessage());
    return [];
  } finally {
    $conn->close();
  }
}

// Get recent commissions
function getRecentCommissions($limit = 20)
{
  $conn = getConnection();
  $commissions = [];

  try {
    $result = $conn->query("
            SELECT 
                tc.id,
                tc.investment_id,
                u_earner.full_name as earner_name,
                u_investor.full_name as investor_name,
                tc.level,
                tc.investment_amount,
                tc.commission_rate,
                tc.commission_amount,
                tc.status,
                tc.created_at,
                tc.paid_at
            FROM 
                tree_commissions tc
                JOIN users u_earner ON tc.user_id = u_earner.id
                JOIN users u_investor ON tc.referred_id = u_investor.id
            ORDER BY 
                tc.created_at DESC
            LIMIT $limit
        ");

    if ($result) {
      while ($row = $result->fetch_assoc()) {
        $commissions[] = $row;
      }
    }

    return $commissions;
  } catch (Exception $e) {
    error_log("Error in getRecentCommissions: " . $e->getMessage());
    return [];
  } finally {
    $conn->close();
  }
}

// Get tree data for a specific user
function getUserTreeData($userId = null)
{
  $conn = getConnection();
  $treeData = [];

  try {
    if ($userId === null) {
      // Get a user with the most referrals if no ID specified
      $result = $conn->query("
                SELECT parent_id as id
                FROM referral_tree
                GROUP BY parent_id
                ORDER BY COUNT(*) DESC
                LIMIT 1
            ");

      if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $userId = $row['id'];
      } else {
        // No referrals in the system yet
        return [];
      }
    }

    // Get user information
    $stmt = $conn->prepare("SELECT id, full_name, email, referral_code FROM users WHERE id = ?");
    if (!$stmt) {
      error_log("Failed to prepare statement: " . $conn->error);
      return ['error' => 'Failed to prepare statement'];
    }

    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
      return ['error' => 'User not found'];
    }

    $rootUser = $result->fetch_assoc();
    $treeData['root'] = $rootUser;

    // Get level 1 referrals (direct)
    $stmt = $conn->prepare("
            SELECT 
                u.id, 
                u.full_name, 
                u.email, 
                u.referral_code,
                rt.level,
                (SELECT COUNT(*) FROM referral_tree WHERE parent_id = u.id) as children_count
            FROM 
                referral_tree rt
                JOIN users u ON rt.user_id = u.id
            WHERE 
                rt.parent_id = ? AND rt.level = 1
        ");
    if (!$stmt) {
      error_log("Failed to prepare statement for level 1 referrals: " . $conn->error);
      return ['error' => 'Failed to prepare statement for level 1 referrals'];
    }

    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $treeData['level1'] = [];
    while ($row = $result->fetch_assoc()) {
      $treeData['level1'][] = $row;

      // If the user has children, get level 2 for this branch
      if ($row['children_count'] > 0) {
        $childId = $row['id'];

        $stmt2 = $conn->prepare("
                    SELECT 
                        u.id, 
                        u.full_name, 
                        u.email, 
                        u.referral_code,
                        rt.level,
                        (SELECT COUNT(*) FROM referral_tree WHERE parent_id = u.id) as children_count
                    FROM 
                        referral_tree rt
                        JOIN users u ON rt.user_id = u.id
                    WHERE 
                        rt.parent_id = ? AND rt.level = 1
                ");
        if (!$stmt2) {
          error_log("Failed to prepare statement for level 2 referrals: " . $conn->error);
          continue; // Skip this branch but continue with others
        }

        $stmt2->bind_param("i", $childId);
        $stmt2->execute();
        $result2 = $stmt2->get_result();

        $level2Children = [];
        while ($row2 = $result2->fetch_assoc()) {
          $level2Children[] = $row2;
        }

        $treeData['level2'][$childId] = $level2Children;
      }
    }

    return $treeData;
  } catch (Exception $e) {
    error_log("Error in getUserTreeData: " . $e->getMessage());
    return ['error' => $e->getMessage()];
  } finally {
    $conn->close();
  }
}

// Check and create necessary tables function
function checkAndCreateTables()
{
  $conn = getConnection();
  $tablesCreated = false;

  try {
    // Check if referral_tree table exists
    $result = $conn->query("SHOW TABLES LIKE 'referral_tree'");
    if ($result->num_rows == 0) {
      // Create referral_tree table
      $conn->query("
        CREATE TABLE IF NOT EXISTS `referral_tree` (
          `id` int NOT NULL AUTO_INCREMENT,
          `user_id` int NOT NULL,
          `parent_id` int NOT NULL,
          `level` int NOT NULL COMMENT 'Level in the referral tree',
          `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `user_parent_unique` (`user_id`,`parent_id`),
          KEY `user_id` (`user_id`),
          KEY `parent_id` (`parent_id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
      ");
      $tablesCreated = true;
    }

    // Check if tree_commissions table exists
    $result = $conn->query("SHOW TABLES LIKE 'tree_commissions'");
    if ($result->num_rows == 0) {
      // Create tree_commissions table
      $conn->query("
        CREATE TABLE IF NOT EXISTS `tree_commissions` (
          `id` int NOT NULL AUTO_INCREMENT,
          `investment_id` int NOT NULL,
          `user_id` int NOT NULL COMMENT 'User who earns the commission',
          `referred_id` int NOT NULL COMMENT 'User who made the investment',
          `level` int NOT NULL COMMENT 'Level in the referral tree',
          `investment_amount` decimal(15,2) NOT NULL,
          `commission_rate` decimal(5,2) NOT NULL,
          `commission_amount` decimal(15,2) NOT NULL,
          `status` enum('pending','paid','cancelled') NOT NULL DEFAULT 'pending',
          `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
          `paid_at` timestamp NULL DEFAULT NULL,
          PRIMARY KEY (`id`),
          KEY `investment_id` (`investment_id`),
          KEY `user_id` (`user_id`),
          KEY `referred_id` (`referred_id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
      ");
      $tablesCreated = true;
    }

    // Check if referral_structure table exists
    $result = $conn->query("SHOW TABLES LIKE 'referral_structure'");
    if ($result->num_rows == 0) {
      // Create referral_structure table
      $conn->query("
        CREATE TABLE IF NOT EXISTS `referral_structure` (
          `id` int NOT NULL AUTO_INCREMENT,
          `level` int NOT NULL,
          `commission_rate` decimal(5,2) NOT NULL,
          `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `level` (`level`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
      ");

      // Insert default commission rates
      $conn->query("
        INSERT INTO referral_structure (level, commission_rate) VALUES 
        (1, 10.00),
        (2, 5.00),
        (3, 2.50)
      ");
      $tablesCreated = true;
    }

    // Check if admin_messages table exists for top commissioner chat
    $result = $conn->query("SHOW TABLES LIKE 'admin_messages'");
    if ($result->num_rows == 0) {
      // Create admin_messages table
      $conn->query("
        CREATE TABLE IF NOT EXISTS `admin_messages` (
          `id` int NOT NULL AUTO_INCREMENT,
          `admin_id` int NOT NULL,
          `user_id` int NOT NULL,
          `message` text NOT NULL,
          `sent_by` enum('admin','user') NOT NULL,
          `read` tinyint(1) NOT NULL DEFAULT '0',
          `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `admin_id` (`admin_id`),
          KEY `user_id` (`user_id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
      ");
      $tablesCreated = true;
    }

    // Check if investments table has referral_commission_paid column
    $result = $conn->query("SHOW COLUMNS FROM investments LIKE 'referral_commission_paid'");
    if ($result->num_rows == 0) {
      // Add referral_commission_paid column to investments table
      $conn->query("
        ALTER TABLE `investments` 
        ADD COLUMN `referral_commission_paid` TINYINT(1) NOT NULL DEFAULT 0 
        COMMENT 'Whether referral commissions have been paid'
      ");
      $tablesCreated = true;
    }

    return $tablesCreated;
  } catch (Exception $e) {
    error_log("Error creating tables: " . $e->getMessage());
    return false;
  } finally {
    $conn->close();
  }
}

// Check and create necessary tables
$tablesCreated = checkAndCreateTables();
if ($tablesCreated) {
  $message = "Tree commission system tables have been initialized.";
}

// Get the statistics
$stats = getTreeStatistics();
$rates = getCommissionRates();
$topReferrers = getTopReferrers();
$recentCommissions = getRecentCommissions();

// Get user ID from query string if provided
$selectedUserId = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;
$treeData = getUserTreeData($selectedUserId);

// Handle user search
$searchResults = [];
if (isset($_GET['search']) && !empty($_GET['search'])) {
  $searchTerm = $_GET['search'];
  $conn = getConnection();

  try {
    $stmt = $conn->prepare("
            SELECT id, full_name, email, referral_code
            FROM users
            WHERE full_name LIKE ? OR email LIKE ? OR referral_code LIKE ?
            LIMIT 10
        ");
    $searchParam = "%$searchTerm%";
    $stmt->bind_param("sss", $searchParam, $searchParam, $searchParam);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
      $searchResults[] = $row;
    }
  } catch (Exception $e) {
    $error = "Search error: " . $e->getMessage();
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

$unreadCount = getUnreadMessagesCount();
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin - Tree Commission System</title>
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
</head>

<body class="bg-gray-100">
  <div class="flex min-h-screen">
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
    <div class="flex-1 overflow-x-hidden">
      <header class="bg-white shadow">
        <div class="max-w-7xl mx-auto py-4 px-6 flex justify-between items-center">
          <h1 class="text-2xl font-bold text-gray-900">Tree Commission System</h1>

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
          <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg alert">
            <div class="flex justify-between items-center">
              <span><?php echo $message; ?></span>
              <button type="button" class="close-alert text-green-700">
                <i class="fas fa-times"></i>
              </button>
            </div>
          </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
          <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg alert">
            <div class="flex justify-between items-center">
              <span><?php echo $error; ?></span>
              <button type="button" class="close-alert text-red-700">
                <i class="fas fa-times"></i>
              </button>
            </div>
          </div>
        <?php endif; ?>

        <!-- Dashboard Content -->
        <div id="dashboard-content" class="tab-content">
          <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-semibold text-gray-800">
              <i class="fas fa-tachometer-alt mr-2"></i> Dashboard Overview
            </h2>
            <span class="text-sm text-gray-500">Last updated: <?php echo date('M d, Y H:i:s'); ?></span>
          </div>

          <!-- Stats Cards -->
          <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-blue-500 text-white rounded-lg shadow-lg overflow-hidden transform transition-transform hover:scale-105">
              <div class="p-4">
                <div class="flex justify-between items-start">
                  <div>
                    <p class="text-sm font-medium opacity-75">Total Users</p>
                    <h3 class="text-3xl font-bold mt-1"><?php echo number_format($stats['total_users']); ?></h3>
                  </div>
                  <div class="bg-blue-600 p-2 rounded-lg">
                    <i class="fas fa-users text-xl"></i>
                  </div>
                </div>
                <p class="text-xs mt-2 opacity-75">Users in the system</p>
              </div>
            </div>

            <div class="bg-green-500 text-white rounded-lg shadow-lg overflow-hidden transform transition-transform hover:scale-105">
              <div class="p-4">
                <div class="flex justify-between items-start">
                  <div>
                    <p class="text-sm font-medium opacity-75">Users with Referrals</p>
                    <h3 class="text-3xl font-bold mt-1"><?php echo number_format($stats['users_with_referrals']); ?></h3>
                  </div>
                  <div class="bg-green-600 p-2 rounded-lg">
                    <i class="fas fa-user-plus text-xl"></i>
                  </div>
                </div>
                <p class="text-xs mt-2 opacity-75">Active referrers</p>
              </div>
            </div>

            <div class="bg-indigo-500 text-white rounded-lg shadow-lg overflow-hidden transform transition-transform hover:scale-105">
              <div class="p-4">
                <div class="flex justify-between items-start">
                  <div>
                    <p class="text-sm font-medium opacity-75">Total Commissions</p>
                    <h3 class="text-3xl font-bold mt-1">$<?php echo number_format($stats['total_commissions_paid'], 2); ?></h3>
                  </div>
                  <div class="bg-indigo-600 p-2 rounded-lg">
                    <i class="fas fa-dollar-sign text-xl"></i>
                  </div>
                </div>
                <p class="text-xs mt-2 opacity-75">Paid to referrers</p>
              </div>
            </div>

            <div class="bg-yellow-500 text-white rounded-lg shadow-lg overflow-hidden transform transition-transform hover:scale-105">
              <div class="p-4">
                <div class="flex justify-between items-start">
                  <div>
                    <p class="text-sm font-medium opacity-75">Avg. Commission</p>
                    <h3 class="text-3xl font-bold mt-1">$<?php echo number_format($stats['avg_commission_per_user'], 2); ?></h3>
                  </div>
                  <div class="bg-yellow-600 p-2 rounded-lg">
                    <i class="fas fa-chart-line text-xl"></i>
                  </div>
                </div>
                <p class="text-xs mt-2 opacity-75">Per referring user</p>
              </div>
            </div>
          </div>

          <!-- Quick Actions -->
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <a href="admin_full_tree.php" class="bg-white p-4 rounded-lg shadow flex items-center hover:shadow-md transition-shadow">
              <div class="p-3 bg-blue-100 text-blue-500 rounded-full mr-3">
                <i class="fas fa-project-diagram"></i>
              </div>
              <div>
                <h3 class="font-medium text-gray-900">View Full Tree</h3>
                <p class="text-sm text-gray-600">See complete commission structure</p>
              </div>
            </a>

            <a href="admin_messages.php" class="bg-white p-4 rounded-lg shadow flex items-center hover:shadow-md transition-shadow">
              <div class="p-3 bg-green-100 text-green-500 rounded-full mr-3">
                <i class="fas fa-comments"></i>
              </div>
              <div>
                <h3 class="font-medium text-gray-900">Message Top Earners</h3>
                <p class="text-sm text-gray-600">Chat with your best performers</p>
              </div>
            </a>

            <a href="#commission-rates" class="dashboard-tab bg-white p-4 rounded-lg shadow flex items-center hover:shadow-md transition-shadow" data-tab="commission-rates">
              <div class="p-3<div class=" p-3 bg-purple-100 text-purple-500 rounded-full mr-3">
                <i class="fas fa-sliders-h"></i>
              </div>
              <div>
                <h3 class="font-medium text-gray-900">Adjust Commission Rates</h3>
                <p class="text-sm text-gray-600">Set rates for each level</p>
              </div>
            </a>

            <a href="#top-referrers" class="dashboard-tab bg-white p-4 rounded-lg shadow flex items-center hover:shadow-md transition-shadow" data-tab="top-referrers">
              <div class="p-3 bg-yellow-100 text-yellow-500 rounded-full mr-3">
                <i class="fas fa-trophy"></i>
              </div>
              <div>
                <h3 class="font-medium text-gray-900">View Top Referrers</h3>
                <p class="text-sm text-gray-600">See your best performers</p>
              </div>
            </a>
          </div>

          <!-- Charts and Tables Row -->
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <div class="bg-white rounded-lg shadow-lg overflow-hidden">
              <div class="p-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800">
                  <i class="fas fa-chart-bar mr-2"></i> Level-wise Statistics
                </h3>
              </div>
              <div class="p-4">
                <div class="h-80">
                  <canvas id="levelStatsChart"></canvas>
                </div>
              </div>
            </div>

            <div class="bg-white rounded-lg shadow-lg overflow-hidden">
              <div class="p-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800">
                  <i class="fas fa-table mr-2"></i> Level-wise Relationships
                </h3>
              </div>
              <div class="p-4">
                <div class="overflow-x-auto">
                  <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                      <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Level</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Relationships</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Commission Rate</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Commission</th>
                      </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                      <?php if (isset($stats['level_stats']) && is_array($stats['level_stats']) && !empty($stats['level_stats'])): ?>
                        <?php foreach ($stats['level_stats'] as $level): ?>
                          <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">Level <?php echo $level['level']; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo number_format($level['relationship_count']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo isset($rates[$level['level']]) ? $rates[$level['level']] . '%' : 'N/A'; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">$<?php echo number_format($level['level_commission'], 2); ?></td>
                          </tr>
                        <?php endforeach; ?>
                      <?php else: ?>
                        <tr>
                          <td colspan="4" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">No level data available</td>
                        </tr>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>

          <!-- Top Commissioners Preview -->
          <div class="bg-white rounded-lg shadow-lg overflow-hidden mb-6">
            <div class="p-4 border-b border-gray-200 flex justify-between items-center">
              <h3 class="text-lg font-semibold text-gray-800">
                <i class="fas fa-crown mr-2"></i> Top Commission Earners
              </h3>
              <a href="#top-referrers" class="dashboard-tab text-sm text-blue-600 hover:text-blue-800" data-tab="top-referrers">
                View all <i class="fas fa-arrow-right ml-1"></i>
              </a>
            </div>
            <div class="p-4">
              <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                  <thead class="bg-gray-50">
                    <tr>
                      <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rank</th>
                      <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                      <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Referrals</th>
                      <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Earnings</th>
                      <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                  </thead>
                  <tbody class="bg-white divide-y divide-gray-200">
                    <?php
                    $limitedReferrers = array_slice($topReferrers, 0, 5); // Show only top 5
                    foreach ($limitedReferrers as $index => $referrer):
                    ?>
                      <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap">
                          <div class="flex items-center">
                            <?php if ($index === 0): ?>
                              <span class="text-amber-500"><i class="fas fa-trophy"></i></span>
                            <?php else: ?>
                              <span class="text-gray-900 font-medium">#<?php echo $index + 1; ?></span>
                            <?php endif; ?>
                          </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                          <div class="flex items-center">
                            <div class="flex-shrink-0 h-10 w-10 bg-gray-200 rounded-full flex items-center justify-center">
                              <span class="text-gray-600 font-semibold">
                                <?php echo substr($referrer['full_name'], 0, 1); ?>
                              </span>
                            </div>
                            <div class="ml-4">
                              <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($referrer['full_name']); ?></div>
                              <div class="text-sm text-gray-500"><?php echo htmlspecialchars($referrer['email']); ?></div>
                            </div>
                          </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                          <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                            <?php echo number_format($referrer['referral_count']); ?>
                          </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 font-medium">
                          $<?php echo number_format($referrer['total_earnings'], 2); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                          <a href="admin_user_tree.php?id=<?php echo $referrer['id']; ?>" class="text-blue-600 hover:text-blue-900 mr-3">
                            <i class="fas fa-sitemap"></i>
                          </a>
                          <a href="admin_messages.php?user_id=<?php echo $referrer['id']; ?>" class="text-green-600 hover:text-green-900">
                            <i class="fas fa-comment"></i>
                          </a>
                        </td>
                      </tr>
                    <?php endforeach; ?>

                    <?php if (empty($limitedReferrers)): ?>
                      <tr>
                        <td colspan="5" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">No commission earners found.</td>
                      </tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <!-- Tree Visualization Content -->
        <div id="visualization-content" class="tab-content hidden">
          <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-semibold text-gray-800">
              <i class="fas fa-sitemap mr-2"></i> Referral Tree Visualization
            </h2>
          </div>

          <!-- User Search Form -->
          <div class="bg-white rounded-lg shadow-lg overflow-hidden mb-6">
            <div class="p-4">
              <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="md:col-span-3">
                  <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search User</label>
                  <input type="text" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" id="search" name="search" placeholder="Name, Email or Referral Code">
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">&nbsp;</label>
                  <button type="submit" class="w-full px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <i class="fas fa-search mr-2"></i> Search
                  </button>
                </div>
                <input type="hidden" name="tab" value="visualization">
              </form>

              <?php if (!empty($searchResults)): ?>
                <div class="mt-6">
                  <h3 class="text-lg font-medium text-gray-900 mb-3">Search Results</h3>
                  <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                      <thead class="bg-gray-50">
                        <tr>
                          <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                          <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                          <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Referral Code</th>
                          <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                        </tr>
                      </thead>
                      <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($searchResults as $user): ?>
                          <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($user['full_name']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($user['email']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                              <span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100"><?php echo htmlspecialchars($user['referral_code']); ?></span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                              <a href="?user_id=<?php echo $user['id']; ?>&tab=visualization" class="text-blue-600 hover:text-blue-900 font-medium">
                                <i class="fas fa-eye mr-1"></i> View Tree
                              </a>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <!-- Tree Visualization -->
          <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <div class="p-4 border-b border-gray-200 flex justify-between items-center">
              <h3 class="text-lg font-semibold text-gray-800">
                <i class="fas fa-project-diagram mr-2"></i> Referral Tree
              </h3>
              <a href="admin_full_tree.php<?php echo $selectedUserId ? '?id=' . $selectedUserId : ''; ?>" class="text-sm text-blue-600 hover:text-blue-800">
                View Full Tree <i class="fas fa-external-link-alt ml-1"></i>
              </a>
            </div>
            <div class="p-4">
              <?php if (isset($treeData['error'])): ?>
                <div class="p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg">
                  <i class="fas fa-exclamation-triangle mr-2"></i> <?php echo $treeData['error']; ?>
                </div>
              <?php elseif (empty($treeData)): ?>
                <div class="p-4 bg-blue-100 border border-blue-400 text-blue-700 rounded-lg">
                  <i class="fas fa-info-circle mr-2"></i> No referral data found. Please select a user to view their tree.
                </div>
              <?php else: ?>
                <div class="overflow-x-auto min-h-[400px]">
                  <div class="flex flex-col items-center">
                    <div class="w-full max-w-xs">
                      <div class="bg-white border border-gray-200 rounded-lg shadow-md p-4 text-center mb-8">
                        <h4 class="font-bold text-lg"><?php echo htmlspecialchars($treeData['root']['full_name']); ?></h4>
                        <p class="text-sm text-gray-500 mb-2"><?php echo htmlspecialchars($treeData['root']['email']); ?></p>
                        <span class="inline-block px-2 py-1 text-xs font-semibold bg-gray-100 rounded-full">
                          <?php echo htmlspecialchars($treeData['root']['referral_code']); ?>
                        </span>
                      </div>
                    </div>

                    <?php if (isset($treeData['level1']) && !empty($treeData['level1'])): ?>
                      <div class="border-l-2 border-gray-300 h-8"></div>
                      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 relative">
                        <?php
                        // Add lines
                        if (count($treeData['level1']) > 1):
                        ?>
                          <div class="absolute top-0 left-1/2 right-0 border-t-2 border-gray-300 h-8 transform -translate-y-8"></div>
                        <?php endif; ?>

                        <?php foreach ($treeData['level1'] as $index => $level1User): ?>
                          <div class="flex flex-col items-center">
                            <div class="border-l-2 border-gray-300 h-8 <?php echo ($index === 0) ? 'invisible' : ''; ?>"></div>
                            <div class="bg-blue-50 border border-blue-200 rounded-lg shadow-md p-4 text-center mb-6">
                              <h5 class="font-bold"><?php echo htmlspecialchars($level1User['full_name']); ?></h5>
                              <p class="text-sm text-gray-500 mb-2"><?php echo htmlspecialchars($level1User['email']); ?></p>
                              <div class="flex flex-wrap justify-center gap-2 mb-2">
                                <span class="inline-block px-2 py-1 text-xs font-semibold bg-blue-100 text-blue-800 rounded-full">Level 1</span>
                                <a href="?user_id=<?php echo $level1User['id']; ?>&tab=visualization" class="inline-block px-2 py-1 text-xs font-semibold bg-indigo-100 text-indigo-800 rounded-full hover:bg-indigo-200">
                                  View Tree
                                </a>
                              </div>
                            </div>

                            <?php if (isset($treeData['level2'][$level1User['id']]) && !empty($treeData['level2'][$level1User['id']])): ?>
                              <div class="border-l-2 border-gray-300 h-8"></div>
                              <div class="grid grid-cols-1 gap-4 relative">
                                <?php
                                // Add lines
                                if (count($treeData['level2'][$level1User['id']]) > 1):
                                ?>
                                  <div class="absolute top-0 left-0 right-0 border-t-2 border-gray-300 h-8 transform -translate-y-8"></div>
                                <?php endif; ?>

                                <?php foreach ($treeData['level2'][$level1User['id']] as $level2Index => $level2User): ?>
                                  <div class="flex flex-col items-center">
                                    <div class="border-l-2 border-gray-300 h-8 <?php echo ($level2Index === 0) ? 'invisible' : ''; ?>"></div>
                                    <div class="bg-green-50 border border-green-200 rounded-lg shadow-md p-4 text-center">
                                      <h5 class="font-bold"><?php echo htmlspecialchars($level2User['full_name']); ?></h5>
                                      <p class="text-sm text-gray-500 mb-2"><?php echo htmlspecialchars($level2User['email']); ?></p>
                                      <div class="flex flex-wrap justify-center gap-2">
                                        <span class="inline-block px-2 py-1 text-xs font-semibold bg-green-100 text-green-800 rounded-full">Level 2</span>
                                        <a href="?user_id=<?php echo $level2User['id']; ?>&tab=visualization" class="inline-block px-2 py-1 text-xs font-semibold bg-indigo-100 text-indigo-800 rounded-full hover:bg-indigo-200">
                                          View Tree
                                        </a>
                                      </div>
                                    </div>
                                  </div>
                                <?php endforeach; ?>
                              </div>
                            <?php endif; ?>
                          </div>
                        <?php endforeach; ?>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Commission Rates Content -->
        <div id="commission-rates-content" class="tab-content hidden">
          <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-semibold text-gray-800">
              <i class="fas fa-sliders-h mr-2"></i> Commission Rate Configuration
            </h2>
          </div>

          <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <div class="p-4 border-b border-gray-200">
              <h3 class="text-lg font-semibold text-gray-800">
                <i class="fas fa-percentage mr-2"></i> Set Commission Rates by Level
              </h3>
            </div>
            <div class="p-4">
              <form action="" method="POST">
                <div class="overflow-x-auto">
                  <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                      <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Level</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Current Rate (%)</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">New Rate (%)</th>
                      </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                      <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">1</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">Direct Referrals</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo isset($rates[1]) ? $rates[1] : 'Not set'; ?></td>
                        <td class="px-6 py-4 whitespace-nowrap">
                          <input type="number" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 w-24" name="rate_1" value="<?php echo isset($rates[1]) ? $rates[1] : 10; ?>" step="0.01" min="0">
                          <input type="hidden" name="level_1" value="1">
                        </td>
                      </tr>
                      <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">2</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">Second Level (Referrals of Referrals)</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo isset($rates[2]) ? $rates[2] : 'Not set'; ?></td>
                        <td class="px-6 py-4 whitespace-nowrap">
                          <input type="number" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 w-24" name="rate_2" value="<?php echo isset($rates[2]) ? $rates[2] : 5; ?>" step="0.01" min="0">
                          <input type="hidden" name="level_2" value="2">
                        </td>
                      </tr>
                      <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">3</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">Third Level</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo isset($rates[3]) ? $rates[3] : 'Not set'; ?></td>
                        <td class="px-6 py-4 whitespace-nowrap">
                          <input type="number" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 w-24" name="rate_3" value="<?php echo isset($rates[3]) ? $rates[3] : 2.5; ?>" step="0.01" min="0">
                          <input type="hidden" name="level_3" value="3">
                        </td>
                      </tr>
                      <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">4</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">Fourth Level</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo isset($rates[4]) ? $rates[4] : 'Not set'; ?></td>
                        <td class="px-6 py-4 whitespace-nowrap">
                          <input type="number" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 w-24" name="rate_4" value="<?php echo isset($rates[4]) ? $rates[4] : 1; ?>" step="0.01" min="0">
                          <input type="hidden" name="level_4" value="4">
                        </td>
                      </tr>
                      <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">5</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">Fifth Level</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo isset($rates[5]) ? $rates[5] : 'Not set'; ?></td>
                        <td class="px-6 py-4 whitespace-nowrap">
                          <input type="number" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 w-24" name="rate_5" value="<?php echo isset($rates[5]) ? $rates[5] : 0.5; ?>" step="0.01" min="0">
                          <input type="hidden" name="level_5" value="5">
                        </td>
                      </tr>
                      <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">6</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">Sixth Level</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo isset($rates[6]) ? $rates[6] : 'Not set'; ?></td>
                        <td class="px-6 py-4 whitespace-nowrap">
                          <input type="number" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 w-24" name="rate_6" value="<?php echo isset($rates[6]) ? $rates[6] : 0.25; ?>" step="0.01" min="0">
                          <input type="hidden" name="level_6" value="6">
                        </td>
                      </tr>
                      <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">7</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">Seventh Level</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo isset($rates[7]) ? $rates[7] : 'Not set'; ?></td>
                        <td class="px-6 py-4 whitespace-nowrap">
                          <input type="number" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 w-24" name="rate_7" value="<?php echo isset($rates[7]) ? $rates[7] : 0.1; ?>" step="0.01" min="0">
                          <input type="hidden" name="level_7" value="7">
                        </td>
                      </tr>
                      <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">8</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">Eighth Level</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo isset($rates[8]) ? $rates[8] : 'Not set'; ?></td>
                        <td class="px-6 py-4 whitespace-nowrap">
                          <input type="number" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 w-24" name="rate_8" value="<?php echo isset($rates[8]) ? $rates[8] : 0.05; ?>" step="0.01" min="0">
                          <input type="hidden" name="level_8" value="8">
                        </td>
                      </tr>
                      <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">9</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">Ninth Level</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo isset($rates[9]) ? $rates[9] : 'Not set'; ?></td>
                        <td class="px-6 py-4 whitespace-nowrap">
                          <input type="number" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 w-24" name="rate_9" value="<?php echo isset($rates[9]) ? $rates[9] : 0.025; ?>" step="0.01" min="0">
                          <input type="hidden" name="level_9" value="9">
                        </td>
                      </tr>
                      <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">10</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">Tenth Level</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo isset($rates[10]) ? $rates[10] : 'Not set'; ?></td>
                        <td class="px-6 py-4 whitespace-nowrap">
                          <input type="number" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 w-24" name="rate_10" value="<?php echo isset($rates[10]) ? $rates[10] : 0.01; ?>" step="0.01" min="0">
                          <input type="hidden" name="level_10" value="10">
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>

                <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg text-blue-700">
                  <p><i class="fas fa-info-circle mr-2"></i> <strong>Note:</strong> Commission rates are expressed as percentages. For example, a rate of 10% means that the user will earn 10% of the investment amount made by their referral.</p>
                </div>

                <div class="mt-6 flex justify-end">
                  <button type="submit" name="update_commission_rates" class="px-6 py-3 bg-blue-600 text-white rounded-md shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <i class="fas fa-save mr-2"></i> Save Commission Rates
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>

        <!-- Top Referrers Content -->
        <div id="top-referrers-content" class="tab-content hidden">
          <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-semibold text-gray-800">
              <i class="fas fa-trophy mr-2"></i> Top Commission Earners
            </h2>
          </div>

          <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <div class="p-4 border-b border-gray-200">
              <h3 class="text-lg font-semibold text-gray-800">
                <i class="fas fa-star mr-2"></i> Most Active Referrers
              </h3>
            </div>
            <div class="p-4">
              <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                  <thead class="bg-gray-50">
                    <tr>
                      <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rank</th>
                      <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                      <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                      <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Referral Code</th>
                      <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Referrals</th>
                      <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Earnings</th>
                      <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                  </thead>
                  <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (!empty($topReferrers)): ?>
                      <?php foreach ($topReferrers as $index => $referrer): ?>
                        <tr class="hover:bg-gray-50">
                          <td class="px-6 py-4 whitespace-nowrap">
                            <?php if ($index === 0): ?>
                              <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-yellow-100 text-yellow-500">
                                <i class="fas fa-trophy"></i>
                              </span>
                            <?php elseif ($index === 1): ?>
                              <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-gray-200 text-gray-600">
                                <i class="fas fa-medal"></i>
                              </span>
                            <?php elseif ($index === 2): ?>
                              <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-yellow-50 text-yellow-700">
                                <i class="fas fa-award"></i>
                              </span>
                            <?php else: ?>
                              <span class="text-gray-900 font-medium">#<?php echo $index + 1; ?></span>
                            <?php endif; ?>
                          </td>
                          <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                              <div class="flex-shrink-0 h-10 w-10 bg-blue-100 rounded-full flex items-center justify-center">
                                <span class="text-blue-600 font-semibold">
                                  <?php echo substr($referrer['full_name'], 0, 1); ?>
                                </span>
                              </div>
                              <div class="ml-4">
                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($referrer['full_name']); ?></div>
                                <div class="text-xs text-gray-500">User ID: <?php echo $referrer['id']; ?></div>
                              </div>
                            </div>
                          </td>
                          <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <div>
                              <i class="fas fa-envelope mr-1 text-gray-400"></i> <?php echo htmlspecialchars($referrer['email']); ?>
                            </div>
                            <?php if (!empty($referrer['phone'])): ?>
                              <div class="mt-1">
                                <i class="fas fa-phone mr-1 text-gray-400"></i> <?php echo htmlspecialchars($referrer['phone']); ?>
                              </div>
                            <?php endif; ?>
                          </td>
                          <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <span class="inline-flex items-center px-2.5 py-1.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                              <?php echo htmlspecialchars($referrer['referral_code']); ?>
                            </span>
                          </td>
                          <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <span class="inline-flex items-center px-2.5 py-1.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                              <?php echo number_format($referrer['referral_count']); ?>
                            </span>
                          </td>
                          <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-medium">
                            $<?php echo number_format($referrer['total_earnings'], 2); ?>
                          </td>
                          <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                            <a href="admin_user_tree.php?id=<?php echo $referrer['id']; ?>" class="text-blue-600 hover:text-blue-900" title="View Tree">
                              <i class="fas fa-sitemap"></i>
                            </a>
                            <a href="admin_messages.php?user_id=<?php echo $referrer['id']; ?>" class="text-green-600 hover:text-green-900" title="Send Message">
                              <i class="fas fa-comment"></i>
                            </a>
                            <a href="admin_full_tree.php?id=<?php echo $referrer['id']; ?>" class="text-purple-600 hover:text-purple-900" title="View Full Tree">
                              <i class="fas fa-project-diagram"></i>
                            </a>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <tr>
                        <td colspan="7" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">No referrers found.</td>
                      </tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <!-- Recent Commissions Content -->
        <div id="recent-commissions-content" class="tab-content hidden">
          <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-semibold text-gray-800">
              <i class="fas fa-history mr-2"></i> Recent Commission Payouts
            </h2>
          </div>

          <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <div class="p-4 border-b border-gray-200">
              <h3 class="text-lg font-semibold text-gray-800">
                <i class="fas fa-money-bill-wave mr-2"></i> Latest Tree Commissions
              </h3>
            </div>
            <div class="p-4">
              <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                  <thead class="bg-gray-50">
                    <tr>
                      <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                      <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                      <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Earner</th>
                      <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Investor</th>
                      <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Level</th>
                      <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Investment</th>
                      <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rate</th>
                      <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Commission</th>
                      <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    </tr>
                  </thead>
                  <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (!empty($recentCommissions)): ?>
                      <?php foreach ($recentCommissions as $commission): ?>
                        <tr class="hover:bg-gray-50">
                          <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo $commission['id']; ?></td>
                          <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('M d, Y', strtotime($commission['created_at'])); ?></td>
                          <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($commission['earner_name']); ?></td>
                          <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($commission['investor_name']); ?></td>
                          <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <span class="px-2 py-1 text-xs font-semibold bg-blue-100 text-blue-800 rounded-full">Level <?php echo $commission['level']; ?></span>
                          </td>
                          <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">$<?php echo number_format($commission['investment_amount'], 2); ?></td>
                          <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $commission['commission_rate']; ?>%</td>
                          <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">$<?php echo number_format($commission['commission_amount'], 2); ?></td>
                          <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php if ($commission['status'] == 'paid'): ?>
                              <span class="px-2 py-1 text-xs font-semibold bg-green-100 text-green-800 rounded-full">Paid</span>
                            <?php elseif ($commission['status'] == 'pending'): ?>
                              <span class="px-2 py-1 text-xs font-semibold bg-yellow-100 text-yellow-800 rounded-full">Pending</span>
                            <?php else: ?>
                              <span class="px-2 py-1 text-xs font-semibold bg-red-100 text-red-800 rounded-full">Cancelled</span>
                            <?php endif; ?>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <tr>
                        <td colspan="9" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">No commission records found.</td>
                      </tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>
  </div>

  <!-- JavaScript Libraries -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

  <script>
    // Tab switching functionality
    document.addEventListener('DOMContentLoaded', function() {
      const tabLinks = document.querySelectorAll('[data-tab]');
      const tabContents = document.querySelectorAll('.tab-content');

      // Check URL for tab parameter
      const urlParams = new URLSearchParams(window.location.search);
      const tabParam = urlParams.get('tab');

      // Function to show a specific tab
      function showTab(tabId) {
        // Hide all tab contents
        tabContents.forEach(content => {
          content.classList.add('hidden');
        });

        // Remove active class from all tab links
        tabLinks.forEach(link => {
          link.classList.remove('bg-gray-700');
        });

        // Show the selected tab content
        const selectedContent = document.getElementById(tabId + '-content');
        if (selectedContent) {
          selectedContent.classList.remove('hidden');
        }

        // Add active class to the selected tab link
        const selectedLinks = document.querySelectorAll('.' + tabId + '-tab');
        selectedLinks.forEach(link => {
          link.classList.add('bg-gray-700');
        });
      }

      // Show the tab from URL parameter or default to dashboard
      if (tabParam && document.getElementById(tabParam + '-content')) {
        showTab(tabParam);
      } else {
        showTab('dashboard');
      }

      // Add click event listeners to tab links
      tabLinks.forEach(link => {
        link.addEventListener('click', function(e) {
          e.preventDefault();
          const tabId = this.getAttribute('data-tab');
          showTab(tabId);

          // Update URL without reloading the page
          const url = new URL(window.location);
          url.searchParams.set('tab', tabId);
          window.history.pushState({}, '', url);
        });
      });

      // Close alerts
      const closeButtons = document.querySelectorAll('.close-alert');
      closeButtons.forEach(button => {
        button.addEventListener('click', function() {
          this.closest('.alert').remove();
        });
      });

      // Initialize the chart
      const levelStatsCtx = document.getElementById('levelStatsChart');

      if (levelStatsCtx) {
        <?php if (isset($stats['level_stats']) && is_array($stats['level_stats']) && !empty($stats['level_stats'])): ?>
          const levelLabels = <?php echo json_encode(array_column($stats['level_stats'], 'level')); ?>;
          const relationshipCounts = <?php echo json_encode(array_column($stats['level_stats'], 'relationship_count')); ?>;
          const commissionAmounts = <?php echo json_encode(array_column($stats['level_stats'], 'level_commission')); ?>;

          new Chart(levelStatsCtx, {
            type: 'bar',
            data: {
              labels: levelLabels.map(level => 'Level ' + level),
              datasets: [{
                  label: 'Relationships',
                  data: relationshipCounts,
                  backgroundColor: 'rgba(59, 130, 246, 0.5)',
                  borderColor: 'rgba(59, 130, 246, 1)',
                  borderWidth: 1,
                  yAxisID: 'y'
                },
                {
                  label: 'Commission ($)',
                  data: commissionAmounts,
                  backgroundColor: 'rgba(16, 185, 129, 0.5)',
                  borderColor: 'rgba(16, 185, 129, 1)',
                  borderWidth: 1,
                  yAxisID: 'y1',
                  type: 'line'
                }
              ]
            },
            options: {
              responsive: true,
              maintainAspectRatio: false,
              scales: {
                y: {
                  type: 'linear',
                  display: true,
                  position: 'left',
                  title: {
                    display: true,
                    text: 'Number of Relationships'
                  }
                },
                y1: {
                  type: 'linear',
                  display: true,
                  position: 'right',
                  grid: {
                    drawOnChartArea: false
                  },
                  title: {
                    display: true,
                    text: 'Commission Amount ($)'
                  }
                }
              },
              plugins: {
                legend: {
                  position: 'top',
                },
                tooltip: {
                  callbacks: {
                    label: function(context) {
                      let label = context.dataset.label || '';
                      if (label) {
                        label += ': ';
                      }
                      if (context.parsed.y !== null) {
                        if (context.datasetIndex === 1) {
                          label += '$' + parseFloat(context.parsed.y).toFixed(2);
                        } else {
                          label += parseFloat(context.parsed.y).toFixed(0);
                        }
                      }
                      return label;
                    }
                  }
                }
              }
            }
          });
        <?php else: ?>
          // No data available
          new Chart(levelStatsCtx, {
            type: 'bar',
            data: {
              labels: ['No Data'],
              datasets: [{
                label: 'No Data Available',
                data: [0],
                backgroundColor: 'rgba(156, 163, 175, 0.5)',
                borderColor: 'rgba(156, 163, 175, 1)',
                borderWidth: 1
              }]
            },
            options: {
              responsive: true,
              maintainAspectRatio: false,
              plugins: {
                title: {
                  display: true,
                  text: 'No statistics data available'
                }
              }
            }
          });
        <?php endif; ?>
      }
    });
  </script>
</body>

</html>