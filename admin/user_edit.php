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

// Messages
$message = '';
$error = '';

function getUserInfo($userId)
{
  $conn = getConnection();

  try {
    $stmt = $conn->prepare("
      SELECT u.id, u.full_name, u.email, u.phone, u.referral_code, u.referred_by, 
             u.registration_date, u.status, w.balance
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

    $userData = $result->fetch_assoc();

    // Get referring user's name if exists
    if ($userData['referred_by']) {
      $referrerStmt = $conn->prepare("SELECT full_name FROM users WHERE id = ?");
      $referrerStmt->bind_param("i", $userData['referred_by']);
      $referrerStmt->execute();
      $referrerResult = $referrerStmt->get_result();

      if ($referrerResult->num_rows > 0) {
        $referrer = $referrerResult->fetch_assoc();
        $userData['referrer_name'] = $referrer['full_name'];
      }
    }

    return $userData;
  } catch (Exception $e) {
    return ['error' => $e->getMessage()];
  } finally {
    $conn->close();
  }
}

// Update user information
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $conn = getConnection();

  try {
    // Validate and sanitize inputs
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $status = $_POST['status'] ?? 'active';
    $balance = floatval($_POST['balance'] ?? 0);
    $referralCode = trim($_POST['referral_code'] ?? '');

    // Basic validation
    if (empty($fullName) || empty($email)) {
      $error = "Name and email are required fields.";
    } else {
      // Check if email already exists for another user
      $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
      $stmt->bind_param("si", $email, $userId);
      $stmt->execute();
      $result = $stmt->get_result();

      if ($result->num_rows > 0) {
        $error = "Email address is already in use by another account.";
      } else {
        // Start transaction
        $conn->begin_transaction();

        // Update user record
        $stmt = $conn->prepare("
          UPDATE users 
          SET full_name = ?, email = ?, phone = ?, status = ?, referral_code = ?
          WHERE id = ?
        ");
        $stmt->bind_param("sssssi", $fullName, $email, $phone, $status, $referralCode, $userId);
        $stmt->execute();

        // Update wallet balance
        $stmt = $conn->prepare("
          UPDATE wallets 
          SET balance = ?
          WHERE user_id = ?
        ");
        $stmt->bind_param("di", $balance, $userId);
        $stmt->execute();

        if ($stmt->affected_rows === 0) {
          // Create wallet if it doesn't exist
          $stmt = $conn->prepare("INSERT INTO wallets (user_id, balance) VALUES (?, ?)");
          $stmt->bind_param("id", $userId, $balance);
          $stmt->execute();
        }

        // Commit transaction
        $conn->commit();

        $message = "User information updated successfully.";

        // Log the action
        $adminId = $_SESSION['admin_id'];
        $adminName = $_SESSION['admin_name'] ?? 'Admin';
        $logStmt = $conn->prepare("
          INSERT INTO admin_logs (admin_id, action, description, user_id) 
          VALUES (?, 'user_update', ?, ?)
        ");
        $description = "User {$fullName} (ID: {$userId}) updated by {$adminName}";
        $logStmt->bind_param("isi", $adminId, $description, $userId);
        $logStmt->execute();
      }
    }
  } catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    $error = "Error updating user: " . $e->getMessage();
  } finally {
    $conn->close();
  }
}

// Get user data
$userData = getUserInfo($userId);

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
  <title>Edit User - Admin Panel</title>
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
          <h1 class="text-2xl font-bold text-gray-900">Edit User</h1>

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

          <div class="bg-white shadow rounded-lg overflow-hidden">
            <!-- User information header -->
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
              <div class="flex items-center justify-between">
                <div>
                  <h2 class="text-xl font-semibold text-gray-800">
                    Editing: <?php echo htmlspecialchars($userData['full_name']); ?>
                  </h2>
                  <p class="text-gray-600">
                    User ID: <?php echo $userData['id']; ?> |
                    Registered: <?php echo date('M d, Y', strtotime($userData['registration_date'])); ?>
                  </p>
                </div>
                <div class="flex space-x-2">
                  <a href="user_tree.php?id=<?php echo $userId; ?>" class="bg-blue-500 hover:bg-blue-600 text-white py-2 px-4 rounded shadow">
                    <i class="fas fa-sitemap mr-1"></i> View Tree
                  </a>
                  <a href="admin_dashboard.php?tab=users" class="bg-gray-500 hover:bg-gray-600 text-white py-2 px-4 rounded shadow">
                    <i class="fas fa-arrow-left mr-1"></i> Back
                  </a>
                </div>
              </div>
            </div>

            <!-- Edit form -->
            <form method="POST" action="" class="p-6">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <label for="full_name" class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                  <input type="text" name="full_name" id="full_name" value="<?php echo htmlspecialchars($userData['full_name']); ?>" required
                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                </div>

                <div>
                  <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                  <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($userData['email']); ?>" required
                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                </div>

                <div>
                  <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                  <input type="text" name="phone" id="phone" value="<?php echo htmlspecialchars($userData['phone'] ?? ''); ?>"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                </div>

                <div>
                  <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Account Status</label>
                  <select name="status" id="status"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                    <option value="active" <?php echo ($userData['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo ($userData['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                    <option value="suspended" <?php echo ($userData['status'] === 'suspended') ? 'selected' : ''; ?>>Suspended</option>
                  </select>
                </div>

                <div>
                  <label for="referral_code" class="block text-sm font-medium text-gray-700 mb-1">Referral Code</label>
                  <input type="text" name="referral_code" id="referral_code" value="<?php echo htmlspecialchars($userData['referral_code']); ?>" required
                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                </div>

                <div>
                  <label for="balance" class="block text-sm font-medium text-gray-700 mb-1">Wallet Balance</label>
                  <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                      <span class="text-gray-500 sm:text-sm">$</span>
                    </div>
                    <input type="number" name="balance" id="balance" step="0.01" value="<?php echo number_format($userData['balance'] ?? 0, 2, '.', ''); ?>"
                      class="w-full pl-7 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                  </div>
                </div>
              </div>

              <!-- Referrer information (read-only) -->
              <div class="mt-6 p-4 bg-gray-50 rounded-lg">
                <h3 class="text-md font-medium text-gray-700 mb-2">Referrer Information</h3>
                <?php if ($userData['referred_by']): ?>
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <label class="block text-sm font-medium text-gray-500 mb-1">Referred By</label>
                      <div class="text-gray-800">
                        <?php echo htmlspecialchars($userData['referrer_name'] ?? 'Unknown'); ?>
                        <span class="text-gray-500">(ID: <?php echo $userData['referred_by']; ?>)</span>
                      </div>
                    </div>
                    <div>
                      <a href="user_edit.php?id=<?php echo $userData['referred_by']; ?>" class="text-blue-600 hover:underline text-sm">
                        <i class="fas fa-edit mr-1"></i> Edit Referrer
                      </a>
                      <a href="user_tree.php?id=<?php echo $userData['referred_by']; ?>" class="text-blue-600 hover:underline text-sm ml-4">
                        <i class="fas fa-sitemap mr-1"></i> View Referrer's Tree
                      </a>
                    </div>
                  </div>
                <?php else: ?>
                  <p class="text-gray-600">This user does not have a referrer (root user).</p>
                <?php endif; ?>
              </div>

              <!-- Action buttons -->
              <div class="mt-6 flex justify-end space-x-3">
                <a href="admin_dashboard.php?tab=users" class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50">
                  Cancel
                </a>
                <button type="submit" class="px-4 py-2 border border-transparent rounded-md shadow-sm text-white bg-primary-600 hover:bg-primary-700">
                  Save Changes
                </button>
              </div>
            </form>

            <!-- Additional actions section -->
            <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
              <h3 class="text-lg font-medium text-gray-900 mb-3">Additional Actions</h3>
              <div class="flex flex-wrap gap-2">
                <a href="user_transactions.php?id=<?php echo $userId; ?>" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                  <i class="fas fa-exchange-alt mr-2"></i> View Transactions
                </a>
                <a href="user_commissions.php?id=<?php echo $userId; ?>" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                  <i class="fas fa-gift mr-2"></i> View Commissions
                </a>
                <a href="admin_messages.php?user_id=<?php echo $userId; ?>" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                  <i class="fas fa-envelope mr-2"></i> Message User
                </a>
                <!-- Reset password button with confirmation dialog -->
                <button onclick="confirmResetPassword()" class="inline-flex items-center px-4 py-2 border border-yellow-300 rounded-md shadow-sm text-sm font-medium text-yellow-700 bg-yellow-50 hover:bg-yellow-100">
                  <i class="fas fa-key mr-2"></i> Reset Password
                </button>
                <!-- Delete user button with confirmation dialog -->
                <button onclick="confirmDeleteUser()" class="inline-flex items-center px-4 py-2 border border-red-300 rounded-md shadow-sm text-sm font-medium text-red-700 bg-red-50 hover:bg-red-100">
                  <i class="fas fa-trash-alt mr-2"></i> Delete User
                </button>
              </div>
            </div>
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
    // Confirmation dialogs for critical actions
    function confirmResetPassword() {
      if (confirm("Are you sure you want to reset this user's password? They will receive an email with instructions.")) {
        window.location.href = "admin_reset_password.php?id=<?php echo $userId; ?>";
      }
    }

    function confirmDeleteUser() {
      if (confirm("WARNING: Are you sure you want to delete this user? This action cannot be undone and will affect the referral tree.")) {
        if (confirm("FINAL WARNING: Deleting this user will remove all their data and may disrupt commissions. Continue?")) {
          window.location.href = "admin_delete_user.php?id=<?php echo $userId; ?>";
        }
      }
    }
  </script>
</body>

</html>