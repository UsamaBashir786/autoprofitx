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

// Process user status updates
if (isset($_POST['update_status']) && isset($_POST['user_id']) && isset($_POST['status'])) {
  $user_id = intval($_POST['user_id']);
  $status = $_POST['status'];

  // Validate status
  if (in_array($status, ['active', 'inactive', 'suspended'])) {
    $update_sql = "UPDATE users SET status = ? WHERE id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("si", $status, $user_id);

    if ($stmt->execute()) {
      $status_message = "User status updated successfully.";
      $status_type = "success";
    } else {
      $status_message = "Error updating user status: " . $conn->error;
      $status_type = "error";
    }
    $stmt->close();
  }
}

// Process delete user (soft delete by setting status to 'suspended')
if (isset($_POST['delete_user']) && isset($_POST['user_id'])) {
  $user_id = intval($_POST['user_id']);

  $delete_sql = "UPDATE users SET status = 'suspended' WHERE id = ?";
  $stmt = $conn->prepare($delete_sql);
  $stmt->bind_param("i", $user_id);

  if ($stmt->execute()) {
    $status_message = "User suspended successfully.";
    $status_type = "success";
  } else {
    $status_message = "Error suspending user: " . $conn->error;
    $status_type = "error";
  }
  $stmt->close();
}

// Process user notes update
if (isset($_POST['update_notes']) && isset($_POST['user_id']) && isset($_POST['admin_notes'])) {
  $user_id = intval($_POST['user_id']);
  $admin_notes = $_POST['admin_notes'];

  $notes_sql = "UPDATE users SET admin_notes = ? WHERE id = ?";
  $stmt = $conn->prepare($notes_sql);
  $stmt->bind_param("si", $admin_notes, $user_id);

  if ($stmt->execute()) {
    $status_message = "Admin notes updated successfully.";
    $status_type = "success";
  } else {
    $status_message = "Error updating admin notes: " . $conn->error;
    $status_type = "error";
  }
  $stmt->close();
}

// Pagination settings
$records_per_page = 10;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $records_per_page;

// Filter and search functionality
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search_term = isset($_GET['search']) ? $_GET['search'] : '';

// Build query based on filters
$where_conditions = [];
$params = [];
$param_types = '';

if ($status_filter && in_array($status_filter, ['active', 'inactive', 'suspended'])) {
  $where_conditions[] = "status = ?";
  $params[] = $status_filter;
  $param_types .= 's';
}

if ($search_term) {
  $where_conditions[] = "(full_name LIKE ? OR email LIKE ? OR phone LIKE ?)";
  $search_param = "%$search_term%";
  $params[] = $search_param;
  $params[] = $search_param;
  $params[] = $search_param;
  $param_types .= 'sss';
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(' AND ', $where_conditions) : "";

// Count total filtered records
$count_sql = "SELECT COUNT(*) as count FROM users $where_clause";
$total_records = 0;

if (!empty($params)) {
  $count_stmt = $conn->prepare($count_sql);
  $count_stmt->bind_param($param_types, ...$params);
  $count_stmt->execute();
  $count_result = $count_stmt->get_result();
  if ($count_row = $count_result->fetch_assoc()) {
    $total_records = $count_row['count'];
  }
  $count_stmt->close();
} else {
  $count_result = $conn->query($count_sql);
  if ($count_row = $count_result->fetch_assoc()) {
    $total_records = $count_row['count'];
  }
}

$total_pages = ceil($total_records / $records_per_page);

// Get users list
$users = [];
$users_sql = "SELECT * FROM users $where_clause ORDER BY registration_date DESC LIMIT ?, ?";

// Add pagination parameters
$params[] = $offset;
$params[] = $records_per_page;
$param_types .= 'ii';

$users_stmt = $conn->prepare($users_sql);
$users_stmt->bind_param($param_types, ...$params);
$users_stmt->execute();
$users_result = $users_stmt->get_result();

while ($row = $users_result->fetch_assoc()) {
  $users[] = $row;
}
$users_stmt->close();

// Get user details if viewing a specific user
$user_details = null;
if (isset($_GET['user_id'])) {
  $user_id = intval($_GET['user_id']);
  $details_sql = "SELECT * FROM users WHERE id = ?";
  $details_stmt = $conn->prepare($details_sql);
  $details_stmt->bind_param("i", $user_id);
  $details_stmt->execute();
  $details_result = $details_stmt->get_result();

  if ($details_result && $row = $details_result->fetch_assoc()) {
    $user_details = $row;
  }
  $details_stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>User Management - AutoProftX</title>
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
          <a href="users.php" class="nav-item active flex items-center px-4 py-3 text-gray-300 hover:text-white rounded transition duration-200">
            <i class="fas fa-users w-6"></i>
            <span>Users</span>
          </a>
          <a href="deposits.php" class="nav-item flex items-center px-4 py-3 text-gray-300 hover:text-white rounded transition duration-200">
            <i class="fas fa-money-bill-wave w-6"></i>
            <span>Deposits</span>
          </a>
          <a href="staking.php" class="nav-item active flex items-center px-4 py-3 text-gray-300 hover:text-white rounded transition duration-200">
            <i class="fas fa-landmark w-6"></i>
            <span>Staking</span>
          </a>
          <a href="investments.php" class="nav-item flex items-center px-4 py-3 text-gray-300 hover:text-white rounded transition duration-200">
            <i class="fas fa-chart-line w-6"></i>
            <span>Investments</span>
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
          <h2 class="text-2xl font-bold">User Management</h2>
          <p class="text-gray-400">Manage registered users and their accounts</p>
        </div>

        <!-- Add User Button (if implementing this feature) -->
        <button
          type="button"
          class="px-4 py-2 bg-yellow-600 hover:bg-yellow-700 text-white rounded-lg transition"
          onclick="document.getElementById('addUserModal').classList.remove('hidden')">
          <i class="fas fa-user-plus mr-2"></i> Add User
        </button>
      </div>

      <!-- Status Messages -->
      <?php if (isset($status_message)): ?>
        <div class="mb-4 p-4 rounded-lg <?php echo $status_type === 'success' ? 'bg-green-800 text-green-100' : 'bg-red-800 text-red-100'; ?>">
          <?php echo htmlspecialchars($status_message); ?>
        </div>
      <?php endif; ?>

      <?php if ($user_details): ?>
        <!-- User Details View -->
        <div class="bg-gray-800 rounded-lg border border-gray-700 shadow-lg mb-6">
          <div class="flex justify-between items-center p-6 border-b border-gray-700">
            <h3 class="text-lg font-bold">User Details</h3>
            <a href="users.php" class="text-yellow-500 hover:text-yellow-400">
              <i class="fas fa-arrow-left mr-1"></i> Back to Users
            </a>
          </div>

          <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <!-- User Profile Info -->
                <div class="mb-6">
                  <h4 class="text-md font-semibold text-gray-300 mb-4">Profile Information</h4>
                  <div class="bg-gray-700 rounded-lg p-4">
                    <div class="flex items-center mb-4">
                      <div class="h-16 w-16 rounded-full bg-gray-600 flex items-center justify-center text-xl font-medium">
                        <?php echo strtoupper(substr($user_details['full_name'], 0, 1)); ?>
                      </div>
                      <div class="ml-4">
                        <h5 class="text-lg font-medium"><?php echo htmlspecialchars($user_details['full_name']); ?></h5>
                        <div class="text-sm text-gray-400">
                          <span class="px-2 py-1 text-xs rounded-full 
                            <?php echo $user_details['status'] === 'active' ? 'bg-green-900 text-green-400' : ($user_details['status'] === 'inactive' ? 'bg-gray-900 text-gray-400' : 'bg-red-900 text-red-400'); ?>">
                            <?php echo ucfirst($user_details['status']); ?>
                          </span>
                        </div>
                      </div>
                    </div>

                    <div class="border-t border-gray-600 pt-4">
                      <div class="grid grid-cols-1 gap-3">
                        <div>
                          <p class="text-xs text-gray-400">Email</p>
                          <p><?php echo htmlspecialchars($user_details['email']); ?></p>
                        </div>
                        <div>
                          <p class="text-xs text-gray-400">Phone</p>
                          <p><?php echo htmlspecialchars($user_details['phone']); ?></p>
                        </div>
                        <div>
                          <p class="text-xs text-gray-400">Registered On</p>
                          <p><?php echo date('F j, Y, g:i a', strtotime($user_details['registration_date'])); ?></p>
                        </div>
                        <div>
                          <p class="text-xs text-gray-400">Last Login</p>
                          <p><?php echo $user_details['last_login'] ? date('F j, Y, g:i a', strtotime($user_details['last_login'])) : 'Never'; ?></p>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- User Actions -->
                <div>
                  <h4 class="text-md font-semibold text-gray-300 mb-4">User Actions</h4>
                  <div class="bg-gray-700 rounded-lg p-4">
                    <div class="grid grid-cols-1 gap-3">
                      <?php if ($user_details['status'] !== 'active'): ?>
                        <form method="post">
                          <input type="hidden" name="user_id" value="<?php echo $user_details['id']; ?>">
                          <input type="hidden" name="status" value="active">
                          <button type="submit" name="update_status" class="w-full py-2 bg-green-600 hover:bg-green-700 rounded-lg text-white font-medium transition">
                            <i class="fas fa-user-check mr-2"></i> Activate User
                          </button>
                        </form>
                      <?php endif; ?>

                      <?php if ($user_details['status'] !== 'inactive'): ?>
                        <form method="post">
                          <input type="hidden" name="user_id" value="<?php echo $user_details['id']; ?>">
                          <input type="hidden" name="status" value="inactive">
                          <button type="submit" name="update_status" class="w-full py-2 bg-gray-600 hover:bg-gray-700 rounded-lg text-white font-medium transition">
                            <i class="fas fa-user-clock mr-2"></i> Deactivate User
                          </button>
                        </form>
                      <?php endif; ?>

                      <?php if ($user_details['status'] !== 'suspended'): ?>
                        <form method="post">
                          <input type="hidden" name="user_id" value="<?php echo $user_details['id']; ?>">
                          <input type="hidden" name="status" value="suspended">
                          <button type="submit" name="update_status" class="w-full py-2 bg-red-600 hover:bg-red-700 rounded-lg text-white font-medium transition">
                            <i class="fas fa-user-slash mr-2"></i> Suspend User
                          </button>
                        </form>
                      <?php endif; ?>

                      <form method="post">
                        <input type="hidden" name="user_id" value="<?php echo $user_details['id']; ?>">
                        <button type="submit" name="delete_user" class="w-full py-2 bg-red-800 hover:bg-red-900 rounded-lg text-white font-medium transition"
                          onclick="return confirm('Are you sure you want to suspend this user? This action will prevent them from logging in.')">
                          <i class="fas fa-trash-alt mr-2"></i> Delete User
                        </button>
                      </form>
                    </div>
                  </div>
                </div>
              </div>

              <div>
                <!-- Admin Notes -->
                <div class="mb-6">
                  <h4 class="text-md font-semibold text-gray-300 mb-4">Admin Notes</h4>
                  <div class="bg-gray-700 rounded-lg p-4">
                    <form method="post">
                      <input type="hidden" name="user_id" value="<?php echo $user_details['id']; ?>">
                      <textarea name="admin_notes" rows="8" class="w-full bg-gray-800 border border-gray-600 rounded-lg p-3 text-gray-200"
                        placeholder="Add private notes about this user here..."><?php echo htmlspecialchars($user_details['admin_notes'] ?? ''); ?></textarea>
                      <button type="submit" name="update_notes" class="mt-3 px-4 py-2 bg-yellow-600 hover:bg-yellow-700 rounded-lg text-white font-medium transition">
                        <i class="fas fa-save mr-2"></i> Save Notes
                      </button>
                    </form>
                  </div>
                </div>

                <!-- User Activity (placeholder - expand as needed) -->
                <div>
                  <h4 class="text-md font-semibold text-gray-300 mb-4">Recent Activity</h4>
                  <div class="bg-gray-700 rounded-lg p-4">
                    <p class="text-gray-400 text-center py-4">Activity tracking to be implemented.</p>
                    <!-- Implement user activity/history here as needed -->
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      <?php else: ?>
        <!-- Users Listing -->
        <div class="bg-gray-800 rounded-lg border border-gray-700 shadow-lg mb-6">
          <!-- Filters and Search -->
          <div class="p-6 border-b border-gray-700">
            <form method="get" class="flex flex-col md:flex-row gap-4">
              <div class="flex-1">
                <input type="text" name="search" placeholder="Search by name, email or phone..."
                  value="<?php echo htmlspecialchars($search_term); ?>"
                  class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-gray-200">
              </div>
              <div class="w-full md:w-auto">
                <select name="status" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-gray-200">
                  <option value="">All Statuses</option>
                  <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                  <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                  <option value="suspended" <?php echo $status_filter === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                </select>
              </div>
              <div class="w-full md:w-auto flex gap-2">
                <button type="submit" class="flex-1 bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded-lg transition">
                  <i class="fas fa-search mr-2"></i> Search
                </button>
                <a href="users.php" class="flex-1 bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg transition text-center">
                  <i class="fas fa-redo mr-2"></i> Reset
                </a>
              </div>
            </form>
          </div>

          <!-- Users Table -->
          <div class="overflow-x-auto">
            <table class="w-full">
              <thead class="bg-gray-700">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">User</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Contact</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Joined</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Status</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-700">
                <?php if (empty($users)): ?>
                  <tr>
                    <td colspan="5" class="px-6 py-4 text-center text-gray-400">No users found matching your criteria.</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($users as $user): ?>
                    <tr class="hover:bg-gray-700">
                      <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                          <div class="h-10 w-10 rounded-full bg-gray-600 flex items-center justify-center text-sm font-medium">
                            <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                          </div>
                          <div class="ml-3">
                            <div class="text-sm font-medium"><?php echo htmlspecialchars($user['full_name']); ?></div>
                            <div class="text-xs text-gray-400">ID: <?php echo $user['id']; ?></div>
                          </div>
                        </div>
                      </td>
                      <td class="px-6 py-4">
                        <div class="text-sm text-gray-300"><?php echo htmlspecialchars($user['email']); ?></div>
                        <div class="text-xs text-gray-400"><?php echo htmlspecialchars($user['phone']); ?></div>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                        <?php echo date('M d, Y', strtotime($user['registration_date'])); ?>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 py-1 text-xs rounded-full 
                          <?php echo $user['status'] === 'active' ? 'bg-green-900 text-green-400' : ($user['status'] === 'inactive' ? 'bg-gray-900 text-gray-400' : 'bg-red-900 text-red-400'); ?>">
                          <?php echo ucfirst($user['status']); ?>
                        </span>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm">
                        <div class="flex space-x-2">
                          <a href="users.php?user_id=<?php echo $user['id']; ?>" class="text-blue-400 hover:text-blue-300">
                            <i class="fas fa-eye"></i>
                          </a>

                          <?php if ($user['status'] === 'active'): ?>
                            <form method="post" class="inline">
                              <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                              <input type="hidden" name="status" value="inactive">
                              <button type="submit" name="update_status" class="text-yellow-500 hover:text-yellow-400">
                                <i class="fas fa-user-clock"></i>
                              </button>
                            </form>
                          <?php elseif ($user['status'] === 'inactive' || $user['status'] === 'suspended'): ?>
                            <form method="post" class="inline">
                              <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                              <input type="hidden" name="status" value="active">
                              <button type="submit" name="update_status" class="text-green-500 hover:text-green-400">
                                <i class="fas fa-user-check"></i>
                              </button>
                            </form>
                          <?php endif; ?>

                          <form method="post" class="inline" onsubmit="return confirm('Are you sure you want to suspend this user?');">
                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                            <button type="submit" name="delete_user" class="text-red-500 hover:text-red-400">
                              <i class="fas fa-trash-alt"></i>
                            </button>
                          </form>
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
            <div class="px-6 py-4 border-t border-gray-700">
              <div class="flex justify-between items-center">
                <div class="text-sm text-gray-400">
                  Showing <?php echo min(($page - 1) * $records_per_page + 1, $total_records); ?> to
                  <?php echo min($page * $records_per_page, $total_records); ?> of <?php echo $total_records; ?> users
                </div>
                <div class="flex space-x-1">
                  <?php if ($page > 1): ?>
                    <a href="?page=1<?php echo $status_filter ? '&status=' . urlencode($status_filter) : ''; ?><?php echo $search_term ? '&search=' . urlencode($search_term) : ''; ?>"
                      class="px-3 py-1 bg-gray-700 hover:bg-gray-600 rounded-md transition">
                      <i class="fas fa-angle-double-left"></i>
                    </a>
                    <a href="?page=<?php echo $page - 1; ?><?php echo $status_filter ? '&status=' . urlencode($status_filter) : ''; ?><?php echo $search_term ? '&search=' . urlencode($search_term) : ''; ?>"
                      class="px-3 py-1 bg-gray-700 hover:bg-gray-600 rounded-md transition">
                      <i class="fas fa-angle-left"></i>
                    </a>
                  <?php endif; ?>

                  <?php
                  $start_page = max(1, $page - 2);
                  $end_page = min($start_page + 4, $total_pages);
                  if ($end_page - $start_page < 4 && $total_pages > 4) {
                    $start_page = max(1, $end_page - 4);
                  }

                  for ($i = $start_page; $i <= $end_page; $i++):
                  ?>
                    <a href="?page=<?php echo $i; ?><?php echo $status_filter ? '&status=' . urlencode($status_filter) : ''; ?><?php echo $search_term ? '&search=' . urlencode($search_term) : ''; ?>"
                      class="px-3 py-1 <?php echo $i === $page ? 'bg-yellow-600 text-white' : 'bg-gray-700 hover:bg-gray-600'; ?> rounded-md transition">
                      <?php echo $i; ?>
                    </a>
                  <?php endfor; ?>

                  <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?><?php echo $status_filter ? '&status=' . urlencode($status_filter) : ''; ?><?php echo $search_term ? '&search=' . urlencode($search_term) : ''; ?>"
                      class="px-3 py-1 bg-gray-700 hover:bg-gray-600 rounded-md transition">
                      <i class="fas fa-angle-right"></i>
                    </a>
                    <a href="?page=<?php echo $total_pages; ?><?php echo $status_filter ? '&status=' . urlencode($status_filter) : ''; ?><?php echo $search_term ? '&search=' . urlencode($search_term) : ''; ?>"
                      class="px-3 py-1 bg-gray-700 hover:bg-gray-600 rounded-md transition">
                      <i class="fas fa-angle-double-right"></i>
                    </a>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </main>
  </div>

  <!-- Add User Modal -->
  <div id="addUserModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-gray-800 rounded-lg shadow-lg max-w-md w-full p-6">
      <div class="flex justify-between items-center mb-4">
        <h3 class="text-xl font-bold">Add New User</h3>
        <button type="button" class="text-gray-400 hover:text-gray-200" onclick="document.getElementById('addUserModal').classList.add('hidden')">
          <i class="fas fa-times"></i>
        </button>
      </div>

      <form method="post" action="add-user.php">
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-400 mb-1">Full Name</label>
            <input type="text" name="full_name" required class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-gray-200">
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-400 mb-1">Email</label>
            <input type="email" name="email" required class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-gray-200">
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-400 mb-1">Phone</label>
            <input type="tel" name="phone" required class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-gray-200">
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-400 mb-1">Password</label>
            <input type="password" name="password" required class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-gray-200">
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-400 mb-1">Status</label>
            <select name="status" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-gray-200">
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>
        </div>

        <div class="mt-6 flex justify-end space-x-3">
          <button type="button" class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg transition"
            onclick="document.getElementById('addUserModal').classList.add('hidden')">
            Cancel
          </button>
          <button type="submit" class="px-4 py-2 bg-yellow-600 hover:bg-yellow-700 text-white rounded-lg transition">
            Add User
          </button>
        </div>
      </form>
    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Mobile sidebar toggle
      const mobileSidebar = document.getElementById('mobile-sidebar');
      const mobileMenuButton = document.getElementById('mobile-menu-button');
      const closeSidebarButton = document.getElementById('close-sidebar');

      if (mobileMenuButton) {
        mobileMenuButton.addEventListener('click', function() {
          mobileSidebar.classList.remove('-translate-x-full');
        });
      }

      if (closeSidebarButton) {
        closeSidebarButton.addEventListener('click', function() {
          mobileSidebar.classList.add('-translate-x-full');
        });
      }
    });
  </script>
</body>

</html>