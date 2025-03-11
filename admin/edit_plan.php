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

// Get the plan ID from URL
$plan_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Validate plan_id
if ($plan_id <= 0) {
  $_SESSION['error_message'] = 'Invalid plan ID.';
  header('Location: plans.php');
  exit;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  $name = trim($_POST['name']);
  $description = trim($_POST['description']);
  $min_amount = floatval($_POST['min_amount']);
  $max_amount = !empty($_POST['max_amount']) ? floatval($_POST['max_amount']) : null;
  $daily_profit_rate = floatval($_POST['daily_profit_rate']);
  $duration_days = intval($_POST['duration_days']);
  $referral_commission_rate = floatval($_POST['referral_commission_rate']);
  $is_active = isset($_POST['is_active']) ? 1 : 0;

  // Validate inputs
  $errors = [];

  if (empty($name)) {
    $errors[] = 'Plan name is required.';
  }

  if ($min_amount <= 0) {
    $errors[] = 'Minimum amount must be greater than zero.';
  }

  if ($max_amount !== null && $max_amount <= $min_amount) {
    $errors[] = 'Maximum amount must be greater than minimum amount.';
  }

  if ($daily_profit_rate <= 0) {
    $errors[] = 'Daily profit rate must be greater than zero.';
  }

  if ($duration_days <= 0) {
    $errors[] = 'Duration must be greater than zero days.';
  }

  if ($referral_commission_rate < 0) {
    $errors[] = 'Referral commission rate cannot be negative.';
  }

  if (empty($errors)) {
    // Update plan
    $stmt = $conn->prepare("
            UPDATE investment_plans
            SET name = ?,
                description = ?,
                min_amount = ?,
                max_amount = ?,
                daily_profit_rate = ?,
                duration_days = ?,
                referral_commission_rate = ?,
                is_active = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");

    $stmt->bind_param(
      "ssdddddii",
      $name,
      $description,
      $min_amount,
      $max_amount,
      $daily_profit_rate,
      $duration_days,
      $referral_commission_rate,
      $is_active,
      $plan_id
    );

    if ($stmt->execute()) {
      $_SESSION['success_message'] = 'Investment plan updated successfully.';
      header('Location: plans.php');
      exit;
    } else {
      $errors[] = 'Failed to update plan: ' . $conn->error;
    }
  }
}

// Get plan details
$stmt = $conn->prepare("SELECT * FROM investment_plans WHERE id = ?");
$stmt->bind_param("i", $plan_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  $_SESSION['error_message'] = 'Investment plan not found.';
  header('Location: plans.php');
  exit;
}

$plan = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Investment Plan - AutoProftX</title>
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
          <a href="plans.php" class="nav-item active flex items-center px-4 py-3 text-gray-300 hover:text-white rounded transition duration-200">
            <i class="fa-solid fa-file-invoice w-6"></i>
            <span>Plans</span>
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
          <h2 class="text-2xl font-bold">Edit Investment Plan</h2>
          <p class="text-gray-400">Update the details of the selected investment plan.</p>
        </div>
        <a href="plans.php" class="flex items-center bg-gray-800 hover:bg-gray-700 text-gray-300 py-2 px-4 rounded-md transition duration-200">
          <i class="fas fa-arrow-left mr-2"></i>
          <span>Back to Plans</span>
        </a>
      </div>

      <!-- Error Messages -->
      <?php if (!empty($errors)): ?>
        <div class="mb-6 bg-red-900 text-red-100 px-4 py-3 rounded-lg">
          <ul class="list-disc list-inside">
            <?php foreach ($errors as $error): ?>
              <li><?php echo $error; ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <!-- Edit Form -->
      <div class="bg-gray-800 rounded-lg border border-gray-700 shadow-lg p-6">
        <form method="POST" action="">
          <div class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label for="name" class="block text-sm font-medium text-gray-300 mb-1">Plan Name:</label>
                <input type="text" id="name" name="name" maxlength="50" required value="<?php echo htmlspecialchars($plan['name']); ?>"
                  class="w-full bg-gray-700 border border-gray-600 rounded-md py-2 px-3 text-white focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent">
              </div>

              <div class="flex items-center h-full pt-6">
                <input type="checkbox" id="is_active" name="is_active" <?php echo $plan['is_active'] ? 'checked' : ''; ?>
                  class="h-4 w-4 text-yellow-500 focus:ring-yellow-500 border-gray-600 rounded">
                <label for="is_active" class="ml-2 block text-sm text-gray-300">Active</label>
              </div>
            </div>

            <div>
              <label for="description" class="block text-sm font-medium text-gray-300 mb-1">Description:</label>
              <textarea id="description" name="description" rows="3"
                class="w-full bg-gray-700 border border-gray-600 rounded-md py-2 px-3 text-white focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent"><?php echo htmlspecialchars($plan['description']); ?></textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label for="min_amount" class="block text-sm font-medium text-gray-300 mb-1">Minimum Amount:</label>
                <input type="number" id="min_amount" name="min_amount" step="0.01" required value="<?php echo $plan['min_amount']; ?>"
                  class="w-full bg-gray-700 border border-gray-600 rounded-md py-2 px-3 text-white focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent">
              </div>

              <div>
                <label for="max_amount" class="block text-sm font-medium text-gray-300 mb-1">Maximum Amount:</label>
                <input type="number" id="max_amount" name="max_amount" step="0.01" value="<?php echo $plan['max_amount']; ?>"
                  class="w-full bg-gray-700 border border-gray-600 rounded-md py-2 px-3 text-white focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent"
                  placeholder="Leave empty for unlimited">
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label for="daily_profit_rate" class="block text-sm font-medium text-gray-300 mb-1">Daily Profit Rate (%):</label>
                <input type="number" id="daily_profit_rate" name="daily_profit_rate" step="0.01" required value="<?php echo $plan['daily_profit_rate']; ?>"
                  class="w-full bg-gray-700 border border-gray-600 rounded-md py-2 px-3 text-white focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent">
              </div>

              <div>
                <label for="duration_days" class="block text-sm font-medium text-gray-300 mb-1">Duration (days):</label>
                <input type="number" id="duration_days" name="duration_days" required value="<?php echo $plan['duration_days']; ?>"
                  class="w-full bg-gray-700 border border-gray-600 rounded-md py-2 px-3 text-white focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent">
              </div>
            </div>

            <div>
              <label for="referral_commission_rate" class="block text-sm font-medium text-gray-300 mb-1">Referral Commission Rate (%):</label>
              <input type="number" id="referral_commission_rate" name="referral_commission_rate" step="0.01" required value="<?php echo $plan['referral_commission_rate']; ?>"
                class="w-full bg-gray-700 border border-gray-600 rounded-md py-2 px-3 text-white focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent">
              <p class="mt-1 text-xs text-gray-400">This is the percentage of the investment amount that will be paid as commission to the referrer.</p>
            </div>
          </div>

          <div class="mt-6 flex justify-end space-x-3">
            <a href="plans.php" class="px-4 py-2 bg-gray-700 text-gray-300 rounded-md hover:bg-gray-600 transition duration-200">
              Cancel
            </a>
            <button type="submit"
              class="gold-gradient text-black font-bold py-2 px-6 rounded-md transition duration-200 hover:opacity-90">
              Update Plan
            </button>
          </div>
        </form>
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