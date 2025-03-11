<?php
// Start session
session_start();
include '../config/db.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
  header("Location: admin-login.php");
  exit();
}

// Get admin info
$admin_id = $_SESSION['admin_id'];
$admin_name = $_SESSION['admin_name'] ?? 'Admin';

include '../user/includes/generate-referral-code.php';

// Function to generate referral codes for all users who don't have one
function generateMissingReferralCodes($conn)
{
  // Find users without referral codes
  $query = "SELECT id FROM users WHERE referral_code IS NULL OR referral_code = ''";
  $result = $conn->query($query);

  $count = 0;
  if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
      if (assignReferralCodeToUser($conn, $row['id'])) {
        $count++;
      }
    }
  }

  return $count;
}

$count = 0;
$error = "";

// Process if form is submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['generate'])) {
  try {
    $count = generateMissingReferralCodes($conn);
    $success = true;
  } catch (Exception $e) {
    $error = $e->getMessage();
  }
}

// Get statistics
$total_users = 0;
$missing_codes = 0;

$query = "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN referral_code IS NULL OR referral_code = '' THEN 1 ELSE 0 END) as missing
          FROM users";
$result = $conn->query($query);
if ($result && $row = $result->fetch_assoc()) {
  $total_users = $row['total'];
  $missing_codes = $row['missing'];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Generate Referral Codes - AutoProftX</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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

    /* Card hover effect */
    .stat-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
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
          <div class="relative">
            <button id="notifications-btn" class="text-gray-300 hover:text-white relative">
              <i class="fas fa-bell text-xl"></i>
            </button>
          </div>

          <div class="flex items-center">
            <div class="h-8 w-8 rounded-full bg-yellow-500 flex items-center justify-center text-black font-bold">
              <?php echo strtoupper(substr($admin_name, 0, 1)); ?>
            </div>
            <span class="ml-2 hidden sm:inline-block"><?php echo htmlspecialchars($admin_name); ?></span>
          </div>
        </div>
      </div>
    </header>

    <!-- Mobile Sidebar -->
    <div id="mobile-sidebar" class="fixed inset-0 bg-gray-900 bg-opacity-90 z-50 md:hidden transform -translate-x-full transition-transform duration-300">
      <div class="flex flex-col overflow-y-scroll h-full bg-gray-800 w-64 py-8 px-6">
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
          <a href="referrals.php" class="nav-item active flex items-center px-4 py-3 text-gray-300 hover:text-white rounded transition duration-200">
            <i class="fa-solid fa-user-plus w-6"></i>
            <span>Referral</span>
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
      <div class="flex justify-between items-center mb-6">
        <div>
          <h2 class="text-2xl font-bold">Generate Referral Codes</h2>
          <p class="text-gray-400">Create missing referral codes for all users in the system.</p>
        </div>
        <a href="referrals.php" class="flex items-center bg-gray-800 hover:bg-gray-700 text-gray-300 py-2 px-4 rounded-md transition duration-200">
          <i class="fas fa-arrow-left mr-2"></i>
          <span>Back to Referrals</span>
        </a>
      </div>

      <!-- Success Message -->
      <?php if (isset($success)): ?>
        <div class="mb-6 bg-green-900 text-green-100 px-4 py-3 rounded-lg flex items-start">
          <i class="fas fa-check-circle text-green-400 mt-1 mr-3"></i>
          <div>
            <p class="font-medium">Success!</p>
            <p>Successfully generated referral codes for <?php echo $count; ?> users.</p>
          </div>
        </div>
      <?php endif; ?>

      <!-- Error Message -->
      <?php if ($error): ?>
        <div class="mb-6 bg-red-900 text-red-100 px-4 py-3 rounded-lg flex items-start">
          <i class="fas fa-exclamation-circle text-red-400 mt-1 mr-3"></i>
          <div>
            <p class="font-medium">Error!</p>
            <p><?php echo $error; ?></p>
          </div>
        </div>
      <?php endif; ?>

      <!-- Statistics Cards -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <!-- Total Users -->
        <div class="bg-gray-800 rounded-lg p-6 border border-gray-700 shadow-lg transition duration-300 stat-card">
          <div class="flex justify-between items-center">
            <div>
              <p class="text-gray-400 text-sm">Total Users</p>
              <h3 class="text-2xl font-bold mt-1"><?php echo number_format($total_users); ?></h3>
            </div>
            <div class="h-12 w-12 rounded-lg gold-gradient flex items-center justify-center">
              <i class="fas fa-users text-black"></i>
            </div>
          </div>
        </div>

        <!-- Missing Referral Codes -->
        <div class="bg-gray-800 rounded-lg p-6 border border-gray-700 shadow-lg transition duration-300 stat-card">
          <div class="flex justify-between items-center">
            <div>
              <p class="text-gray-400 text-sm">Users Missing Referral Codes</p>
              <h3 class="text-2xl font-bold mt-1"><?php echo number_format($missing_codes); ?></h3>
            </div>
            <div class="h-12 w-12 rounded-lg <?php echo $missing_codes > 0 ? 'bg-red-500' : 'bg-green-500'; ?> flex items-center justify-center">
              <i class="fas fa-tag text-black"></i>
            </div>
          </div>
        </div>
      </div>

      <!-- Generate Codes Button -->
      <div class="bg-gray-800 rounded-lg p-6 border border-gray-700 shadow-lg mb-6">
        <h3 class="text-lg font-bold mb-4">Generate Missing Referral Codes</h3>
        <p class="text-gray-400 mb-4">
          This action will generate new referral codes for all users who currently don't have one.
          Each user will receive a unique referral code that they can share with others.
        </p>

        <form method="post">
          <button type="submit" name="generate"
            class="gold-gradient text-black font-bold py-3 px-6 rounded-md transition duration-200 hover:opacity-90 flex items-center">
            <i class="fas fa-code-branch mr-2"></i>
            Generate Missing Referral Codes
          </button>
        </form>
      </div>

      <!-- Quick Notes -->
      <div class="bg-gray-800 rounded-lg p-6 border border-gray-700 shadow-lg">
        <h3 class="text-lg font-bold mb-4">Notes</h3>
        <ul class="list-disc list-inside text-gray-400 space-y-2">
          <li>Referral codes are automatically generated for new users during registration.</li>
          <li>This tool is for fixing legacy accounts or accounts that were created before the referral system was implemented.</li>
          <li>Generated codes follow the pattern specified in your system's configuration.</li>
          <li>This process may take some time if there are many users missing referral codes.</li>
        </ul>
      </div>
    </main>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Mobile sidebar toggle
      const mobileSidebar = document.getElementById('mobile-sidebar');
      const mobileMenuButton = document.getElementById('mobile-menu-button');
      const closeSidebarButton = document.getElementById('close-sidebar');

      mobileMenuButton.addEventListener('click', function() {
        mobileSidebar.classList.remove('-translate-x-full');
      });

      closeSidebarButton.addEventListener('click', function() {
        mobileSidebar.classList.add('-translate-x-full');
      });
    });
  </script>
</body>

</html>