<?php
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

// Process form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
  if ($_POST['action'] == 'add') {
    // Add new plan
    $name = $_POST['name'];
    $description = $_POST['description'];
    $min_amount = $_POST['min_amount'];
    $max_amount = $_POST['max_amount'] != "" ? $_POST['max_amount'] : NULL;
    $daily_profit_rate = $_POST['daily_profit_rate'];
    $duration_days = $_POST['duration_days'];
    $referral_commission_rate = $_POST['referral_commission_rate'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    $sql = "INSERT INTO investment_plans (name, description, min_amount, max_amount, daily_profit_rate, duration_days, referral_commission_rate, is_active) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssdddddi", $name, $description, $min_amount, $max_amount, $daily_profit_rate, $duration_days, $referral_commission_rate, $is_active);

    if ($stmt->execute()) {
      $_SESSION['success_message'] = "Investment plan added successfully!";
    } else {
      $_SESSION['error_message'] = "Error adding investment plan: " . $conn->error;
    }

    $stmt->close();

    // Redirect to prevent form resubmission
    header("Location: plans.php");
    exit();
  }
}

// Handle delete request
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
  $id = $_GET['delete'];

  $sql = "DELETE FROM investment_plans WHERE id = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("i", $id);

  if ($stmt->execute()) {
    $_SESSION['success_message'] = "Investment plan deleted successfully!";
  } else {
    $_SESSION['error_message'] = "Error deleting investment plan: " . $conn->error;
  }

  $stmt->close();

  // Redirect to prevent form resubmission
  header("Location: plans.php");
  exit();
}

// Get all investment plans
$sql = "SELECT * FROM investment_plans ORDER BY is_active DESC, name ASC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Investment Plans - AutoProftX</title>
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
      <div class="mb-6">
        <h2 class="text-2xl font-bold">Manage Investment Plans</h2>
        <p class="text-gray-400">Create and manage your platform's investment plans.</p>
      </div>

      <!-- Success / Error Messages -->
      <?php if (isset($_SESSION['success_message'])): ?>
        <div class="mb-4 bg-green-900 text-green-100 px-4 py-3 rounded relative" role="alert">
          <span class="block sm:inline"><?php echo $_SESSION['success_message']; ?></span>
          <?php unset($_SESSION['success_message']); ?>
        </div>
      <?php endif; ?>

      <?php if (isset($_SESSION['error_message'])): ?>
        <div class="mb-4 bg-red-900 text-red-100 px-4 py-3 rounded relative" role="alert">
          <span class="block sm:inline"><?php echo $_SESSION['error_message']; ?></span>
          <?php unset($_SESSION['error_message']); ?>
        </div>
      <?php endif; ?>

      <!-- Content Grid -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Add New Plan Form -->
        <div class="bg-gray-800 rounded-lg border border-gray-700 shadow-lg p-6">
          <h3 class="text-xl font-bold mb-6 text-yellow-500">Add New Plan</h3>

          <form method="POST" action="">
            <input type="hidden" name="action" value="add">

            <div class="space-y-4">
              <div>
                <label for="name" class="block text-sm font-medium text-gray-300 mb-1">Plan Name:</label>
                <input type="text" id="name" name="name" maxlength="50" required
                  class="w-full bg-gray-700 border border-gray-600 rounded-md py-2 px-3 text-white focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent">
              </div>

              <div>
                <label for="description" class="block text-sm font-medium text-gray-300 mb-1">Description:</label>
                <textarea id="description" name="description" rows="3"
                  class="w-full bg-gray-700 border border-gray-600 rounded-md py-2 px-3 text-white focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent"></textarea>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label for="min_amount" class="block text-sm font-medium text-gray-300 mb-1">Minimum Amount:</label>
                  <input type="number" id="min_amount" name="min_amount" step="0.01" required
                    class="w-full bg-gray-700 border border-gray-600 rounded-md py-2 px-3 text-white focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent">
                </div>

                <div>
                  <label for="max_amount" class="block text-sm font-medium text-gray-300 mb-1">Maximum Amount:</label>
                  <input type="number" id="max_amount" name="max_amount" step="0.01"
                    class="w-full bg-gray-700 border border-gray-600 rounded-md py-2 px-3 text-white focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent"
                    placeholder="Leave empty for unlimited">
                </div>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label for="daily_profit_rate" class="block text-sm font-medium text-gray-300 mb-1">Daily Profit Rate (%):</label>
                  <input type="number" id="daily_profit_rate" name="daily_profit_rate" step="0.01" required
                    class="w-full bg-gray-700 border border-gray-600 rounded-md py-2 px-3 text-white focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent">
                </div>

                <div>
                  <label for="duration_days" class="block text-sm font-medium text-gray-300 mb-1">Duration (days):</label>
                  <input type="number" id="duration_days" name="duration_days" required
                    class="w-full bg-gray-700 border border-gray-600 rounded-md py-2 px-3 text-white focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent">
                </div>
              </div>

              <div>
                <label for="referral_commission_rate" class="block text-sm font-medium text-gray-300 mb-1">Referral Commission Rate (%):</label>
                <input type="number" id="referral_commission_rate" name="referral_commission_rate" step="0.01" required
                  class="w-full bg-gray-700 border border-gray-600 rounded-md py-2 px-3 text-white focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent">
              </div>

              <div class="flex items-center">
                <input type="checkbox" id="is_active" name="is_active" checked
                  class="h-4 w-4 text-yellow-500 focus:ring-yellow-500 border-gray-600 rounded">
                <label for="is_active" class="ml-2 block text-sm text-gray-300">Active</label>
              </div>
            </div>

            <div class="mt-6">
              <button type="submit"
                class="w-full gold-gradient text-black font-bold py-2 px-4 rounded-md transition duration-200 hover:opacity-90">
                Add Plan
              </button>
            </div>
          </form>
        </div>

        <!-- Plans List -->
        <div class="bg-gray-800 rounded-lg border border-gray-700 shadow-lg overflow-hidden">
          <div class="p-6 border-b border-gray-700">
            <h3 class="text-xl font-bold text-yellow-500">Existing Plans</h3>
          </div>

          <div class="overflow-x-auto">
            <table class="w-full">
              <thead class="bg-gray-700">
                <tr>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Name</th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Min-Max</th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Daily Rate</th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Duration</th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Referral</th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Status</th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-700">
                <?php if ($result && $result->num_rows > 0): ?>
                  <?php while ($plan = $result->fetch_assoc()): ?>
                    <tr class="hover:bg-gray-700">
                      <td class="px-4 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-white"><?php echo htmlspecialchars($plan['name']); ?></div>
                        <?php if (!empty($plan['description'])): ?>
                          <div class="text-xs text-gray-400 truncate max-w-xs"><?php echo htmlspecialchars($plan['description']); ?></div>
                        <?php endif; ?>
                      </td>
                      <td class="px-4 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-300">$<?php echo htmlspecialchars($plan['min_amount']); ?></div>
                        <?php if ($plan['max_amount']): ?>
                          <div class="text-xs text-gray-400">to $<?php echo htmlspecialchars($plan['max_amount']); ?></div>
                        <?php else: ?>
                          <div class="text-xs text-gray-400">to Unlimited</div>
                        <?php endif; ?>
                      </td>
                      <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-300">
                        <?php echo htmlspecialchars($plan['daily_profit_rate']); ?>%
                      </td>
                      <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-300">
                        <?php echo htmlspecialchars($plan['duration_days']); ?> days
                      </td>
                      <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-300">
                        <?php echo htmlspecialchars($plan['referral_commission_rate']); ?>%
                      </td>
                      <td class="px-4 py-4 whitespace-nowrap">
                        <?php if ($plan['is_active']): ?>
                          <span class="px-2 py-1 text-xs rounded-full bg-green-900 text-green-400">Active</span>
                        <?php else: ?>
                          <span class="px-2 py-1 text-xs rounded-full bg-red-900 text-red-400">Inactive</span>
                        <?php endif; ?>
                      </td>
                      <td class="px-4 py-4 whitespace-nowrap text-sm font-medium">
                        <a href="edit_plan.php?id=<?php echo $plan['id']; ?>" class="text-yellow-500 hover:text-yellow-400 mr-3">
                          <i class="fas fa-edit"></i> Edit
                        </a>
                        <a href="?delete=<?php echo $plan['id']; ?>" class="text-red-500 hover:text-red-400"
                          onclick="return confirm('Are you sure you want to delete this plan?')">
                          <i class="fas fa-trash-alt"></i> Delete
                        </a>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="7" class="px-4 py-6 text-center text-gray-400">
                      No investment plans found. Use the form to add your first plan.
                    </td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
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

      // Notifications dropdown (simple toggle)
      const notificationsBtn = document.getElementById('notifications-btn');
      if (notificationsBtn) {
        notificationsBtn.addEventListener('click', function() {
          // Here you would implement your notifications dropdown
          alert('You have notification alerts!');
        });
      }
    });
  </script>
</body>

</html>