<?php
// Include database connection
include 'config/db.php';

// Check admin authentication
session_start();
if (!isset($_SESSION['admin_id'])) {
  header("Location: admin_login.php");
  exit();
}

// Check if editing self or has super admin privileges
$editId = isset($_GET['id']) ? intval($_GET['id']) : $_SESSION['admin_id'];
$isSelf = ($editId === $_SESSION['admin_id']);

// Only super admins can edit other admins
if (!$isSelf && $_SESSION['admin_role'] !== 'super_admin') {
  header("Location: admin_dashboard.php");
  exit();
}

// Messages
$message = '';
$error = '';

// Get admin information
function getAdminInfo($adminId)
{
  $conn = getConnection();

  try {
    $stmt = $conn->prepare("
      SELECT id, username, name, email, role, status, last_login 
      FROM admins 
      WHERE id = ?
    ");
    $stmt->bind_param("i", $adminId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
      return ['error' => 'Admin not found'];
    }

    return $result->fetch_assoc();
  } catch (Exception $e) {
    return ['error' => $e->getMessage()];
  } finally {
    $conn->close();
  }
}

// Update admin information
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $conn = getConnection();

  try {
    // Validate and sanitize inputs
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $role = $_POST['role'] ?? 'admin';
    $status = $_POST['status'] ?? 'active';
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Basic validation
    if (empty($name) || empty($email) || empty($username)) {
      $error = "Name, email and username are required fields.";
    } else {
      // Check if username/email already exists for another admin
      $stmt = $conn->prepare("SELECT id FROM admins WHERE (username = ? OR email = ?) AND id != ?");
      $stmt->bind_param("ssi", $username, $email, $editId);
      $stmt->execute();
      $result = $stmt->get_result();

      if ($result->num_rows > 0) {
        $error = "Username or email address is already in use by another admin.";
      } else {
        // Start transaction
        $conn->begin_transaction();

        // If changing password
        if (!empty($new_password)) {
          // Verify current password if editing self
          if ($isSelf) {
            $stmt = $conn->prepare("SELECT password FROM admins WHERE id = ?");
            $stmt->bind_param("i", $editId);
            $stmt->execute();
            $result = $stmt->get_result();
            $admin = $result->fetch_assoc();

            if (!password_verify($current_password, $admin['password'])) {
              throw new Exception("Current password is incorrect.");
            }
          }

          // Check new password and confirmation match
          if ($new_password !== $confirm_password) {
            throw new Exception("New password and confirmation do not match.");
          }

          // Password strength validation
          if (strlen($new_password) < 8) {
            throw new Exception("Password must be at least 8 characters long.");
          }

          // Hash the new password
          $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

          // Update with new password
          $stmt = $conn->prepare("
            UPDATE admins 
            SET name = ?, email = ?, username = ?, role = ?, status = ?, password = ?
            WHERE id = ?
          ");
          $stmt->bind_param("ssssssi", $name, $email, $username, $role, $status, $hashed_password, $editId);
        } else {
          // Update without changing password
          $stmt = $conn->prepare("
            UPDATE admins 
            SET name = ?, email = ?, username = ?, role = ?, status = ?
            WHERE id = ?
          ");
          $stmt->bind_param("sssssi", $name, $email, $username, $role, $status, $editId);
        }

        $stmt->execute();

        // Commit transaction
        $conn->commit();

        $message = "Admin information updated successfully.";

        // Update session variables if editing self
        if ($isSelf) {
          $_SESSION['admin_name'] = $name;
          $_SESSION['admin_role'] = $role;
        }

        // Log the action
        $adminId = $_SESSION['admin_id'];
        $adminName = $_SESSION['admin_name'] ?? 'Admin';
        $logStmt = $conn->prepare("
          INSERT INTO admin_logs (admin_id, action, description) 
          VALUES (?, 'admin_update', ?)
        ");
        $description = "Admin {$name} (ID: {$editId}) updated by {$adminName}";
        $logStmt->bind_param("is", $adminId, $description);
        $logStmt->execute();
      }
    }
  } catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    $error = "Error updating admin: " . $e->getMessage();
  } finally {
    $conn->close();
  }
}

// Get admin data
$adminData = getAdminInfo($editId);

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
  <title>Edit Admin - Admin Panel</title>
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
      <header class="bg-white shadow">
        <div class="max-w-7xl mx-auto py-4 px-6 flex justify-between items-center">
          <h1 class="text-2xl font-bold text-gray-900">
            <?php echo $isSelf ? 'Edit My Profile' : 'Edit Admin'; ?>
          </h1>

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
          <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative">
            <span class="block sm:inline"><?php echo $message; ?></span>
          </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
          <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative">
            <span class="block sm:inline"><?php echo $error; ?></span>
          </div>
        <?php endif; ?>

        <?php if (isset($adminData['error'])): ?>
          <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative">
            <span class="block sm:inline">Error: <?php echo $adminData['error']; ?></span>
            <div class="mt-2">
              <a href="admin_dashboard.php" class="bg-red-500 hover:bg-red-600 text-white py-2 px-4 rounded">
                Back to Dashboard
              </a>
            </div>
          </div>
        <?php else: ?>

          <div class="bg-white shadow rounded-lg overflow-hidden">
            <!-- Admin information header -->
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
              <div class="flex items-center justify-between">
                <div>
                  <h2 class="text-xl font-semibold text-gray-800">
                    <?php echo $isSelf ? 'My Profile' : 'Admin Profile: ' . htmlspecialchars($adminData['name']); ?>
                  </h2>
                  <p class="text-gray-600">
                    <?php if (!$isSelf): ?>
                      Admin ID: <?php echo $adminData['id']; ?> |
                    <?php endif; ?>
                    Last Login: <?php echo $adminData['last_login'] ? date('M d, Y H:i', strtotime($adminData['last_login'])) : 'Never'; ?>
                  </p>
                </div>
                <?php if (!$isSelf && $_SESSION['admin_role'] === 'super_admin'): ?>
                  <div>
                    <a href="admin_dashboard.php?tab=admins" class="bg-gray-500 hover:bg-gray-600 text-white py-2 px-4 rounded shadow">
                      <i class="fas fa-arrow-left mr-1"></i> Back to Admins
                    </a>
                  </div>
                <?php endif; ?>
              </div>
            </div>

            <!-- Edit form -->
            <form method="POST" action="" class="p-6">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                  <input type="text" name="name" id="name" value="<?php echo htmlspecialchars($adminData['name']); ?>" required
                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                </div>

                <div>
                  <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                  <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($adminData['email']); ?>" required
                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                </div>

                <div>
                  <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                  <input type="text" name="username" id="username" value="<?php echo htmlspecialchars($adminData['username']); ?>" required
                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                </div>

                <?php if ($_SESSION['admin_role'] === 'super_admin'): ?>
                  <div>
                    <label for="role" class="block text-sm font-medium text-gray-700 mb-1">Admin Role</label>
                    <select name="role" id="role"
                      class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500"
                      <?php echo ($isSelf) ? 'disabled' : ''; ?>>
                      <option value="admin" <?php echo ($adminData['role'] === 'admin') ? 'selected' : ''; ?>>Regular Admin</option>
                      <option value="super_admin" <?php echo ($adminData['role'] === 'super_admin') ? 'selected' : ''; ?>>Super Admin</option>
                    </select>
                    <?php if ($isSelf): ?>
                      <input type="hidden" name="role" value="<?php echo htmlspecialchars($adminData['role']); ?>">
                      <p class="mt-1 text-sm text-gray-500">You cannot change your own role.</p>
                    <?php endif; ?>
                  </div>

                  <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Account Status</label>
                    <select name="status" id="status"
                      class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500"
                      <?php echo ($isSelf) ? 'disabled' : ''; ?>>
                      <option value="active" <?php echo ($adminData['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                      <option value="inactive" <?php echo ($adminData['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                    <?php if ($isSelf): ?>
                      <input type="hidden" name="status" value="<?php echo htmlspecialchars($adminData['status']); ?>">
                      <p class="mt-1 text-sm text-gray-500">You cannot change your own status.</p>
                    <?php endif; ?>
                  </div>
                <?php else: ?>
                  <input type="hidden" name="role" value="<?php echo htmlspecialchars($adminData['role']); ?>">
                  <input type="hidden" name="status" value="<?php echo htmlspecialchars($adminData['status']); ?>">
                <?php endif; ?>
              </div>

              <!-- Password change section -->
              <div class="mt-8 border-t border-gray-200 pt-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Change Password</h3>

                <?php if ($isSelf): ?>
                  <div class="mb-4">
                    <label for="current_password" class="block text-sm font-medium text-gray-700 mb-1">Current Password</label>
                    <input type="password" name="current_password" id="current_password"
                      class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                    <p class="mt-1 text-sm text-gray-500">Leave blank to keep your current password.</p>
                  </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <div>
                    <label for="new_password" class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                    <input type="password" name="new_password" id="new_password"
                      class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                  </div>

                  <div>
                    <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
                    <input type="password" name="confirm_password" id="confirm_password"
                      class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                  </div>
                </div>

                <?php if (!$isSelf): ?>
                  <p class="mt-2 text-sm text-gray-500">
                    When changing another admin's password, current password verification is not required.
                  </p>
                <?php endif; ?>
              </div>

              <!-- Action buttons -->
              <div class="mt-6 flex justify-end space-x-3">
                <a href="<?php echo $isSelf ? 'admin_dashboard.php' : 'admin_dashboard.php?tab=admins'; ?>"
                  class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50">
                  Cancel
                </a>
                <button type="submit" class="px-4 py-2 border border-transparent rounded-md shadow-sm text-white bg-primary-600 hover:bg-primary-700">
                  Save Changes
                </button>
              </div>
            </form>

            <?php if (!$isSelf && $_SESSION['admin_role'] === 'super_admin'): ?>
              <!-- Additional actions section -->
              <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
                <h3 class="text-lg font-medium text-gray-900 mb-3">Additional Actions</h3>
                <div class="flex flex-wrap gap-2">
                  <!-- View activity log -->
                  <a href="admin_logs.php?admin_id=<?php echo $editId; ?>" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    <i class="fas fa-history mr-2"></i> View Activity Log
                  </a>

                  <!-- Delete admin button with confirmation dialog -->
                  <button onclick="confirmDeleteAdmin()" class="inline-flex items-center px-4 py-2 border border-red-300 rounded-md shadow-sm text-sm font-medium text-red-700 bg-red-50 hover:bg-red-100">
                    <i class="fas fa-trash-alt mr-2"></i> Delete Admin
                  </button>
                </div>
              </div>
            <?php endif; ?>
          </div>

        <?php endif; ?>
      </main>

      <footer class="bg-white border-t border-gray-200 py-4">
        <div class="max-w-7xl mx-auto px-6">
          <p class="text-gray-500 text-center">© 2023 Referral System Admin Panel. All rights reserved.</p>
        </div>
      </footer>
    </div>
  </div>

  <script>
    <?php if (!$isSelf && $_SESSION['admin_role'] === 'super_admin'): ?>
      // Confirmation dialog for deleting an admin
      function confirmDeleteAdmin() {
        if (confirm("Are you sure you want to delete this admin account? This action cannot be undone.")) {
          window.location.href = "admin_delete_admin.php?id=<?php echo $editId; ?>";
        }
      }
    <?php endif; ?>
  </script>
</body>

</html>