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

// Handle new payment method addition
if (isset($_POST['add_payment_method'])) {
  $user_id = trim($_POST['user_id']);
  $payment_type = trim($_POST['payment_type']);
  $account_name = trim($_POST['account_name']);
  $account_number = trim($_POST['account_number']);
  $is_default = isset($_POST['is_default']) ? 1 : 0;
  $is_active = isset($_POST['is_active']) ? 1 : 0;

  // Validate input
  if (empty($account_name) || empty($account_number) || empty($payment_type)) {
    $error_message = "All required fields must be filled out!";
  } else {
    // Insert new payment method
    $sql = "INSERT INTO payment_methods (user_id, payment_type, account_name, account_number, is_default, is_active) 
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isssii", $user_id, $payment_type, $account_name, $account_number, $is_default, $is_active);

    if ($stmt->execute()) {
      $success_message = "Payment method added successfully!";
    } else {
      $error_message = "Error adding payment method: " . $stmt->error;
    }

    $stmt->close();
  }
}

// Handle payment method status update
if (isset($_POST['update_status']) && isset($_POST['payment_method_id'])) {
  $payment_method_id = $_POST['payment_method_id'];
  $status = isset($_POST['is_active']) ? 1 : 0;

  $sql = "UPDATE payment_methods SET is_active = ? WHERE id = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("ii", $status, $payment_method_id);

  if ($stmt->execute()) {
    $success_message = "Payment method status updated successfully!";
  } else {
    $error_message = "Error updating payment method status: " . $stmt->error;
  }

  $stmt->close();
}

// Handle making a payment method default
if (isset($_POST['make_default']) && isset($_POST['payment_method_id'])) {
  $payment_method_id = $_POST['payment_method_id'];
  $user_id = $_POST['user_id'];

  // Start a transaction
  $conn->begin_transaction();

  try {
    // First, set all payment methods for this user to non-default
    $reset_sql = "UPDATE payment_methods SET is_default = 0 WHERE user_id = ?";
    $reset_stmt = $conn->prepare($reset_sql);
    $reset_stmt->bind_param("i", $user_id);
    $reset_stmt->execute();
    $reset_stmt->close();

    // Then set the selected one as default
    $default_sql = "UPDATE payment_methods SET is_default = 1 WHERE id = ?";
    $default_stmt = $conn->prepare($default_sql);
    $default_stmt->bind_param("i", $payment_method_id);
    $default_stmt->execute();
    $default_stmt->close();

    // Commit the transaction
    $conn->commit();
    $success_message = "Default payment method updated successfully!";
  } catch (Exception $e) {
    // Rollback if any error occurs
    $conn->rollback();
    $error_message = "Error updating default payment method: " . $e->getMessage();
  }
}

// Handle payment method update
if (isset($_POST['edit_payment_method']) && isset($_POST['payment_method_id'])) {
  $payment_method_id = $_POST['payment_method_id'];
  $user_id = trim($_POST['user_id']);
  $payment_type = trim($_POST['payment_type']);
  $account_name = trim($_POST['account_name']);
  $account_number = trim($_POST['account_number']);
  $is_default = isset($_POST['is_default']) ? 1 : 0;
  $is_active = isset($_POST['is_active']) ? 1 : 0;

  // Validate input
  if (empty($account_name) || empty($account_number) || empty($payment_type)) {
    $error_message = "All required fields must be filled out!";
  } else {
    // Start a transaction if is_default is being set to 1
    if ($is_default == 1) {
      $conn->begin_transaction();

      try {
        // First, set all payment methods for this user to non-default
        $reset_sql = "UPDATE payment_methods SET is_default = 0 WHERE user_id = ?";
        $reset_stmt = $conn->prepare($reset_sql);
        $reset_stmt->bind_param("i", $user_id);
        $reset_stmt->execute();
        $reset_stmt->close();

        // Update payment method
        $sql = "UPDATE payment_methods SET user_id = ?, payment_type = ?, account_name = ?, 
                account_number = ?, is_default = ?, is_active = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isssiis", $user_id, $payment_type, $account_name, $account_number, $is_default, $is_active, $payment_method_id);

        if ($stmt->execute()) {
          $conn->commit();
          $success_message = "Payment method updated successfully!";
        } else {
          throw new Exception($stmt->error);
        }

        $stmt->close();
      } catch (Exception $e) {
        // Rollback if any error occurs
        $conn->rollback();
        $error_message = "Error updating payment method: " . $e->getMessage();
      }
    } else {
      // Simple update without changing default status
      $sql = "UPDATE payment_methods SET user_id = ?, payment_type = ?, account_name = ?, 
              account_number = ?, is_default = ?, is_active = ? WHERE id = ?";
      $stmt = $conn->prepare($sql);
      $stmt->bind_param("isssiis", $user_id, $payment_type, $account_name, $account_number, $is_default, $is_active, $payment_method_id);

      if ($stmt->execute()) {
        $success_message = "Payment method updated successfully!";
      } else {
        $error_message = "Error updating payment method: " . $stmt->error;
      }

      $stmt->close();
    }
  }
}

// Handle payment method deletion
if (isset($_POST['delete_payment_method']) && isset($_POST['payment_method_id'])) {
  $payment_method_id = $_POST['payment_method_id'];

  // Delete from database
  $sql = "DELETE FROM payment_methods WHERE id = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("i", $payment_method_id);

  if ($stmt->execute()) {
    $success_message = "Payment method deleted successfully!";
  } else {
    $error_message = "Error deleting payment method: " . $stmt->error;
  }

  $stmt->close();
}

// Fetch all payment methods
$payment_methods_sql = "SELECT pm.*, u.full_name, u.email FROM payment_methods pm 
                        LEFT JOIN users u ON pm.user_id = u.id 
                        ORDER BY pm.is_active DESC, pm.is_default DESC, pm.account_name ASC";
$payment_methods_result = $conn->query($payment_methods_sql);
$payment_methods = [];

if ($payment_methods_result) {
  while ($row = $payment_methods_result->fetch_assoc()) {
    $payment_methods[] = $row;
  }
}

// Get all users for dropdown
$users_sql = "SELECT id, full_name, email FROM users ORDER BY full_name";
$users_result = $conn->query($users_sql);
$users = [];

if ($users_result) {
  while ($row = $users_result->fetch_assoc()) {
    $users[] = $row;
  }
}

// Count total payment methods
$total_payment_methods = count($payment_methods);
$active_payment_methods = array_filter($payment_methods, function ($method) {
  return $method['is_active'] == 1;
});
$total_active = count($active_payment_methods);
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Payment Methods - AutoProftX Admin</title>
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

    /* Gold gradient */
    .gold-gradient {
      background: linear-gradient(135deg, #f59e0b, #d97706);
    }
  </style>
</head>

<body class="bg-gray-900 text-gray-100 flex min-h-screen">
  <!-- Sidebar -->
  <div class="bg-gray-800 w-64 px-6 py-8 hidden md:flex flex-col justify-between">
    <div>
      <!-- Logo -->
      <div class="flex items-center justify-center mb-8">
        <i class="fas fa-gem text-yellow-500 text-xl mr-2"></i>
        <span class="text-xl font-bold text-yellow-500">AutoProftX</span>
      </div>

      <!-- Navigation -->
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
        <a href="leaderboard.php" class="nav-item flex items-center px-4 py-3 text-gray-300 hover:text-white rounded transition duration-200">
          <i class="fa-solid fa-trophy w-6"></i>
          <span>leaderboard</span>
        </a>
        <a href="profit.php" class="nav-item active flex items-center px-4 py-3 text-gray-300 hover:text-white rounded transition duration-200">
          <i class="fas fa-percentage w-6"></i>
          <span>Profit Management</span>
        </a>
        <a href="plans.php" class="nav-item flex items-center px-4 py-3 text-gray-300 hover:text-white rounded transition duration-200">
          <i class="fa-solid fa-file-invoice w-6"></i>
          <span>Plans</span>
        </a>
        <a href="referrals.php" class="nav-item flex items-center px-4 py-3 text-gray-300 hover:text-white rounded transition duration-200">
          <i class="fa-solid fa-user-plus w-6"></i>
          <span>Referral</span>
        </a>
        <a href="ticket.php" class="nav-item flex items-center px-4 py-3 text-gray-300 hover:text-white rounded transition duration-200">
          <i class="fa-solid fa-headset w-6"></i>
          <span>Customer Support</span>
        </a>
        <a href="tree-referrals.php" class="nav-item flex items-center px-4 py-3 text-gray-300 hover:text-white rounded transition duration-200">
          <i class="fa-solid fa-headset w-6"></i>
          <span>Tree Referral</span>
        </a>
        <a href="withdraw.php" class="nav-item flex items-center px-4 py-3 text-gray-300 hover:text-white rounded transition duration-200">
          <i class="fa-solid fa-headset w-6"></i>
          <span>Withdraw</span>
        </a>
        <a href="staking.php" class="nav-item flex items-center px-4 py-3 text-gray-300 hover:text-white rounded transition duration-200">
          <i class="fas fa-landmark w-6"></i>
          <span>Staking</span>
        </a>
        <a href="investments.php" class="nav-item flex items-center px-4 py-3 text-gray-300 hover:text-white rounded transition duration-200">
          <i class="fas fa-chart-line w-6"></i>
          <span>Investments</span>
        </a>
        <a href="payment-methods.php" class="active nav-item flex items-center px-4 py-3 text-gray-300 hover:text-white rounded transition duration-200">
          <i class="fas fa-credit-card w-6"></i>
          <span>Payment Methods</span>
        </a>
        <a href="charts.php" class="nav-item flex items-center px-4 py-3 text-gray-300 hover:text-white rounded transition duration-200">
          <i class="fas fa-credit-card w-6"></i>
          <span>Chart</span>
        </a>
        <a href="settings.php" class="nav-item flex items-center px-4 py-3 text-gray-300 hover:text-white rounded transition duration-200">
          <i class="fas fa-cog w-6"></i>
          <span>Settings</span>
        </a>
        <a href="admin-logout.php" class="flex items-center px-4 py-3 text-gray-300 hover:text-white rounded transition duration-200">
          <i class="fas fa-sign-out-alt w-6"></i>
          <span>Logout</span>
        </a>
      </nav>
    </div>
    <!-- Logout Button -->
    <div>
      <a href="admin-logout.php" class="flex items-center px-4 py-3 text-gray-300 hover:text-white rounded transition duration-200">
        <i class="fas fa-sign-out-alt w-6"></i>
        <span>Logout</span>
      </a>
    </div>

    <!-- Logout Button -->
    <div>
      <a href="admin-logout.php" class="flex items-center px-4 py-3 text-gray-300 hover:text-white rounded transition duration-200">
        <i class="fas fa-sign-out-alt w-6"></i>
        <span>Logout</span>
      </a>
    </div>
  </div>

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
          <a href="staking.php" class="nav-item flex items-center px-4 py-3 text-gray-300 hover:text-white rounded transition duration-200">
            <i class="fas fa-landmark w-6"></i>
            <span>Staking</span>
          </a>
          <a href="payment-methods.php" class="nav-item active flex items-center px-4 py-3 text-gray-300 hover:text-white rounded transition duration-200">
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
        <h2 class="text-2xl font-bold">Payment Methods Management</h2>
        <p class="text-gray-400">Manage payment methods for deposits and withdrawals</p>
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
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-6">
        <!-- Total Payment Methods -->
        <div class="bg-gray-800 rounded-lg p-6 border border-gray-700 shadow-lg">
          <div class="flex justify-between">
            <div>
              <p class="text-gray-400 text-sm">Total Payment Methods</p>
              <h3 class="text-2xl font-bold mt-1"><?php echo $total_payment_methods; ?></h3>
            </div>
            <div class="h-12 w-12 rounded-lg bg-blue-500 flex items-center justify-center">
              <i class="fas fa-credit-card text-black"></i>
            </div>
          </div>
        </div>

        <!-- Active Payment Methods -->
        <div class="bg-gray-800 rounded-lg p-6 border border-gray-700 shadow-lg">
          <div class="flex justify-between">
            <div>
              <p class="text-gray-400 text-sm">Active Methods</p>
              <h3 class="text-2xl font-bold mt-1"><?php echo $total_active; ?></h3>
            </div>
            <div class="h-12 w-12 rounded-lg bg-green-500 flex items-center justify-center">
              <i class="fas fa-check text-black"></i>
            </div>
          </div>
        </div>

        <!-- Add Payment Method Button -->
        <div class="bg-gray-800 rounded-lg p-6 border border-gray-700 shadow-lg flex items-center">
          <button id="addPaymentMethodBtn" class="bg-yellow-500 hover:bg-yellow-600 text-black font-medium py-2 px-4 rounded-md transition duration-200 w-full">
            <i class="fas fa-plus-circle mr-2"></i>Add New Payment Method
          </button>
        </div>
      </div>

      <!-- Payment Methods Table -->
      <div class="w-full bg-gray-800 rounded-lg border border-gray-700 shadow-lg overflow-hidden">
        <div class="p-6 border-b border-gray-700">
          <h3 class="text-lg font-bold">Payment Methods List</h3>
          <p class="text-gray-400 text-sm mt-1">Manage all available payment methods</p>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full">
            <thead class="bg-gray-700">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">User</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Payment Type</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Account Details</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Default</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-700">
              <?php if (empty($payment_methods)): ?>
                <tr>
                  <td colspan="6" class="px-6 py-4 text-center text-gray-400">No payment methods found</td>
                </tr>
              <?php else: ?>
                <?php foreach ($payment_methods as $method): ?>
                  <tr class="hover:bg-gray-700">
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="flex items-center">
                        <?php if (!empty($method['full_name'])): ?>
                          <div class="h-8 w-8 rounded-full bg-gray-600 flex items-center justify-center text-sm font-medium">
                            <?php echo strtoupper(substr($method['full_name'] ?? 'U', 0, 1)); ?>
                          </div>
                          <div class="ml-3">
                            <div class="text-sm font-medium"><?php echo htmlspecialchars($method['full_name'] ?? 'Unknown User'); ?></div>
                            <div class="text-xs text-gray-400"><?php echo htmlspecialchars($method['email'] ?? 'No email'); ?></div>
                          </div>
                        <?php else: ?>
                          <div class="text-sm text-gray-400">System Account</div>
                        <?php endif; ?>
                      </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <?php
                      $type_icons = [
                        'easypaisa' => 'fa-wallet',
                        'jazzcash' => 'fa-wallet',
                      ];
                      $icon = isset($type_icons[$method['payment_type']]) ? $type_icons[$method['payment_type']] : 'fa-credit-card';
                      ?>
                      <div class="flex items-center">
                        <i class="fas <?php echo $icon; ?> mr-2 text-yellow-500"></i>
                        <span><?php echo htmlspecialchars(ucfirst($method['payment_type'])); ?></span>
                      </div>
                    </td>
                    <td class="px-6 py-4">
                      <div class="text-sm">
                        <div><span class="text-gray-400">Name:</span> <?php echo htmlspecialchars($method['account_name']); ?></div>
                        <div><span class="text-gray-400">Number:</span> <?php echo htmlspecialchars($method['account_number']); ?></div>
                      </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <?php if ($method['is_active']): ?>
                        <span class="px-2 py-1 text-xs rounded-full bg-green-900 text-green-400">Active</span>
                      <?php else: ?>
                        <span class="px-2 py-1 text-xs rounded-full bg-red-900 text-red-400">Inactive</span>
                      <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <?php if ($method['is_default']): ?>
                        <span class="px-2 py-1 text-xs rounded-full bg-yellow-900 text-yellow-400">Default</span>
                      <?php else: ?>
                        <form method="POST" class="inline-block">
                          <input type="hidden" name="payment_method_id" value="<?php echo $method['id']; ?>">
                          <input type="hidden" name="user_id" value="<?php echo $method['user_id']; ?>">
                          <input type="hidden" name="make_default" value="1">
                          <button type="submit" class="text-xs text-gray-400 hover:text-yellow-400">
                            Set as Default
                          </button>
                        </form>
                      <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="flex space-x-2">
                        <button class="edit-payment-method text-blue-400 hover:text-blue-300"
                          data-id="<?php echo $method['id']; ?>"
                          data-user-id="<?php echo $method['user_id']; ?>"
                          data-payment-type="<?php echo htmlspecialchars($method['payment_type']); ?>"
                          data-account-name="<?php echo htmlspecialchars($method['account_name']); ?>"
                          data-account-number="<?php echo htmlspecialchars($method['account_number']); ?>"
                          data-is-default="<?php echo $method['is_default']; ?>"
                          data-is-active="<?php echo $method['is_active']; ?>">
                          <i class="fas fa-edit"></i> Edit
                        </button>
                        <button class="delete-payment-method text-red-400 hover:text-red-300"
                          data-id="<?php echo $method['id']; ?>"
                          data-account-name="<?php echo htmlspecialchars($method['account_name']); ?>">
                          <i class="fas fa-trash-alt"></i> Delete
                        </button>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </main>
  </div>

  <!-- Add Payment Method Modal -->
  <div id="addPaymentMethodModal" class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50 hidden">
    <div class="bg-gray-800 rounded-lg w-full max-w-lg max-h-screen overflow-y-auto">
      <!-- Modal Header -->
      <div class="flex justify-between items-center p-6 border-b border-gray-700">
        <h3 class="text-xl font-bold">Add New Payment Method</h3>
        <button id="closeAddModal" class="text-gray-400 hover:text-white">
          <i class="fas fa-times text-xl"></i>
        </button>
      </div>

      <!-- Modal Content -->
      <div class="p-6">
        <form id="addPaymentMethodForm" method="POST">
          <input type="hidden" name="add_payment_method" value="1">

          <div class="grid grid-cols-1 gap-6">
            <!-- User Selection -->
            <div>
              <label for="user_id" class="block text-sm font-medium text-gray-300 mb-2">User *</label>
              <select id="user_id" name="user_id" required class="bg-gray-700 focus:ring-yellow-500 focus:border-yellow-500 block w-full rounded-md border-gray-600 text-white py-2 px-3">
                <option value="">Select User</option>
                <?php foreach ($users as $user): ?>
                  <option value="<?php echo $user['id']; ?>">
                    <?php echo htmlspecialchars($user['full_name']); ?> (<?php echo htmlspecialchars($user['email']); ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Payment Type -->
            <div>
              <label for="payment_type" class="block text-sm font-medium text-gray-300 mb-2">Payment Type *</label>
              <select id="payment_type" name="payment_type" required class="bg-gray-700 focus:ring-yellow-500 focus:border-yellow-500 block w-full rounded-md border-gray-600 text-white py-2 px-3">
                <option value="">Select Type</option>
                <option value="easypaisa">Easypaisa</option>
                <option value="jazzcash">JazzCash</option>
              </select>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <!-- Account Name -->
              <div>
                <label for="account_name" class="block text-sm font-medium text-gray-300 mb-2">Account Name *</label>
                <input type="text" id="account_name" name="account_name" required class="bg-gray-700 focus:ring-yellow-500 focus:border-yellow-500 block w-full rounded-md border-gray-600 text-white py-2 px-3" placeholder="Account Holder Name">
              </div>

              <!-- Account Number -->
              <div>
                <label for="account_number" class="block text-sm font-medium text-gray-300 mb-2">Account Number *</label>
                <input type="text" id="account_number" name="account_number" required class="bg-gray-700 focus:ring-yellow-500 focus:border-yellow-500 block w-full rounded-md border-gray-600 text-white py-2 px-3" placeholder="Account Number">
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <!-- Is Default -->
              <div class="flex items-center">
                <input type="checkbox" id="is_default" name="is_default" class="h-4 w-4 rounded border-gray-600 text-yellow-500 focus:ring-yellow-500">
                <label for="is_default" class="ml-2 block text-sm text-gray-300">
                  Set as Default Payment Method
                </label>
              </div>

              <!-- Is Active -->
              <div class="flex items-center">
                <input type="checkbox" id="is_active" name="is_active" checked class="h-4 w-4 rounded border-gray-600 text-yellow-500 focus:ring-yellow-500">
                <label for="is_active" class="ml-2 block text-sm text-gray-300">
                  Active Payment Method
                </label>
              </div>
            </div>
          </div>

          <div class="mt-6">
            <button type="submit" class="w-full bg-yellow-500 hover:bg-yellow-600 text-black font-medium py-2 px-4 rounded-md transition duration-200">
              <i class="fas fa-plus-circle mr-2"></i>Add Payment Method
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Edit Payment Method Modal -->
  <div id="editPaymentMethodModal" class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50 hidden">
    <div class="bg-gray-800 rounded-lg w-full max-w-lg max-h-screen overflow-y-auto">
      <!-- Modal Header -->
      <div class="flex justify-between items-center p-6 border-b border-gray-700">
        <h3 class="text-xl font-bold">Edit Payment Method</h3>
        <button id="closeEditModal" class="text-gray-400 hover:text-white">
          <i class="fas fa-times text-xl"></i>
        </button>
      </div>

      <!-- Modal Content -->
      <div class="p-6">
        <form id="editPaymentMethodForm" method="POST">
          <input type="hidden" name="edit_payment_method" value="1">
          <input type="hidden" id="edit_payment_method_id" name="payment_method_id" value="">

          <div class="grid grid-cols-1 gap-6">
            <!-- User Selection -->
            <div>
              <label for="edit_user_id" class="block text-sm font-medium text-gray-300 mb-2">User *</label>
              <select id="edit_user_id" name="user_id" required class="bg-gray-700 focus:ring-yellow-500 focus:border-yellow-500 block w-full rounded-md border-gray-600 text-white py-2 px-3">
                <option value="">Select User</option>
                <?php foreach ($users as $user): ?>
                  <option value="<?php echo $user['id']; ?>">
                    <?php echo htmlspecialchars($user['full_name']); ?> (<?php echo htmlspecialchars($user['email']); ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Payment Type -->
            <div>
              <label for="edit_payment_type" class="block text-sm font-medium text-gray-300 mb-2">Payment Type *</label>
              <select id="edit_payment_type" name="payment_type" required class="bg-gray-700 focus:ring-yellow-500 focus:border-yellow-500 block w-full rounded-md border-gray-600 text-white py-2 px-3">
                <option value="">Select Type</option>
                <option value="easypaisa">Easypaisa</option>
                <option value="jazzcash">JazzCash</option>
              </select>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <!-- Account Name -->
              <div>
                <label for="edit_account_name" class="block text-sm font-medium text-gray-300 mb-2">Account Name *</label>
                <input type="text" id="edit_account_name" name="account_name" required class="bg-gray-700 focus:ring-yellow-500 focus:border-yellow-500 block w-full rounded-md border-gray-600 text-white py-2 px-3" placeholder="Account Holder Name">
              </div>

              <!-- Account Number -->
              <div>
                <label for="edit_account_number" class="block text-sm font-medium text-gray-300 mb-2">Account Number *</label>
                <input type="text" id="edit_account_number" name="account_number" required class="bg-gray-700 focus:ring-yellow-500 focus:border-yellow-500 block w-full rounded-md border-gray-600 text-white py-2 px-3" placeholder="Account Number">
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <!-- Is Default -->
              <div class="flex items-center">
                <input type="checkbox" id="edit_is_default" name="is_default" class="h-4 w-4 rounded border-gray-600 text-yellow-500 focus:ring-yellow-500">
                <label for="edit_is_default" class="ml-2 block text-sm text-gray-300">
                  Set as Default Payment Method
                </label>
              </div>

              <!-- Is Active -->
              <div class="flex items-center">
                <input type="checkbox" id="edit_is_active" name="is_active" class="h-4 w-4 rounded border-gray-600 text-yellow-500 focus:ring-yellow-500">
                <label for="edit_is_active" class="ml-2 block text-sm text-gray-300">
                  Active Payment Method
                </label>
              </div>
            </div>
          </div>

          <div class="mt-6">
            <button type="submit" class="w-full bg-blue-500 hover:bg-blue-600 text-white font-medium py-2 px-4 rounded-md transition duration-200">
              <i class="fas fa-save mr-2"></i>Update Payment Method
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Delete Payment Method Confirmation Modal -->
  <div id="deletePaymentMethodModal" class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50 hidden">
    <div class="bg-gray-800 rounded-lg w-full max-w-md">
      <!-- Modal Header -->
      <div class="flex justify-between items-center p-6 border-b border-gray-700">
        <h3 class="text-xl font-bold">Confirm Deletion</h3>
        <button id="closeDeleteModal" class="text-gray-400 hover:text-white">
          <i class="fas fa-times text-xl"></i>
        </button>
      </div>

      <!-- Modal Content -->
      <div class="p-6">
        <p class="text-gray-300 mb-6">Are you sure you want to delete the payment method <span id="deletePaymentMethodName" class="font-semibold"></span>? This action cannot be undone.</p>

        <div class="flex space-x-4">
          <button id="cancelDelete" class="flex-1 bg-gray-600 hover:bg-gray-700 text-white font-medium py-2 px-4 rounded-md transition duration-200">
            <i class="fas fa-times-circle mr-2"></i>Cancel
          </button>

          <form id="deletePaymentMethodForm" method="POST" class="flex-1">
            <input type="hidden" name="delete_payment_method" value="1">
            <input type="hidden" id="delete_payment_method_id" name="payment_method_id" value="">
            <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white font-medium py-2 px-4 rounded-md transition duration-200">
              <i class="fas fa-trash-alt mr-2"></i>Delete
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <!-- JavaScript -->
  <script>
    // Mobile Sidebar Toggle
    const mobileMenuButton = document.getElementById('mobile-menu-button');
    const mobileSidebar = document.getElementById('mobile-sidebar');
    const closeSidebar = document.getElementById('close-sidebar');

    mobileMenuButton.addEventListener('click', () => {
      mobileSidebar.classList.remove('-translate-x-full');
    });

    closeSidebar.addEventListener('click', () => {
      mobileSidebar.classList.add('-translate-x-full');
    });

    // Add Payment Method Modal
    const addPaymentMethodBtn = document.getElementById('addPaymentMethodBtn');
    const addPaymentMethodModal = document.getElementById('addPaymentMethodModal');
    const closeAddModal = document.getElementById('closeAddModal');

    addPaymentMethodBtn.addEventListener('click', () => {
      addPaymentMethodModal.classList.remove('hidden');
    });

    closeAddModal.addEventListener('click', () => {
      addPaymentMethodModal.classList.add('hidden');
    });

    // Edit Payment Method Modal
    const editBtns = document.querySelectorAll('.edit-payment-method');
    const editPaymentMethodModal = document.getElementById('editPaymentMethodModal');
    const closeEditModal = document.getElementById('closeEditModal');

    editBtns.forEach(btn => {
      btn.addEventListener('click', () => {
        const id = btn.getAttribute('data-id');
        const userId = btn.getAttribute('data-user-id');
        const paymentType = btn.getAttribute('data-payment-type');
        const accountName = btn.getAttribute('data-account-name');
        const accountNumber = btn.getAttribute('data-account-number');
        const isDefault = btn.getAttribute('data-is-default');
        const isActive = btn.getAttribute('data-is-active');

        document.getElementById('edit_payment_method_id').value = id;
        document.getElementById('edit_user_id').value = userId;
        document.getElementById('edit_payment_type').value = paymentType;
        document.getElementById('edit_account_name').value = accountName;
        document.getElementById('edit_account_number').value = accountNumber;
        document.getElementById('edit_is_default').checked = isDefault === '1';
        document.getElementById('edit_is_active').checked = isActive === '1';

        editPaymentMethodModal.classList.remove('hidden');
      });
    });

    closeEditModal.addEventListener('click', () => {
      editPaymentMethodModal.classList.add('hidden');
    });

    // Delete Payment Method Modal
    const deleteBtns = document.querySelectorAll('.delete-payment-method');
    const deletePaymentMethodModal = document.getElementById('deletePaymentMethodModal');
    const closeDeleteModal = document.getElementById('closeDeleteModal');
    const cancelDelete = document.getElementById('cancelDelete');

    deleteBtns.forEach(btn => {
      btn.addEventListener('click', () => {
        const id = btn.getAttribute('data-id');
        const accountName = btn.getAttribute('data-account-name');

        document.getElementById('delete_payment_method_id').value = id;
        document.getElementById('deletePaymentMethodName').textContent = accountName;

        deletePaymentMethodModal.classList.remove('hidden');
      });
    });

    closeDeleteModal.addEventListener('click', () => {
      deletePaymentMethodModal.classList.add('hidden');
    });

    cancelDelete.addEventListener('click', () => {
      deletePaymentMethodModal.classList.add('hidden');
    });

    // Close modals when clicking outside
    window.addEventListener('click', (e) => {
      if (e.target === addPaymentMethodModal) {
        addPaymentMethodModal.classList.add('hidden');
      }
      if (e.target === editPaymentMethodModal) {
        editPaymentMethodModal.classList.add('hidden');
      }
      if (e.target === deletePaymentMethodModal) {
        deletePaymentMethodModal.classList.add('hidden');
      }
    });
  </script>
</body>

</html>