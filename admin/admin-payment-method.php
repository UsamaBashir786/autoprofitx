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

// Process form submission for adding/editing payment methods
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  if (isset($_POST['action'])) {
    // Add new payment method
    if ($_POST['action'] == 'add') {
      $account_name = mysqli_real_escape_string($conn, $_POST['account_name']);
      $account_number = mysqli_real_escape_string($conn, $_POST['account_number']);
      $additional_info = mysqli_real_escape_string($conn, $_POST['additional_info']);
      $is_active = isset($_POST['is_active']) ? 1 : 0;

      // Validate TRC20 address
      if (empty($account_name)) {
        $error_message = "Account name is required";
      } elseif (empty($account_number)) {
        $error_message = "TRC20 address is required";
      } elseif (!preg_match('/^T[a-zA-Z0-9]{33}$/', $account_number)) {
        $error_message = "Invalid TRC20 address format. Must start with T and be 34 characters long.";
      } else {
        // Insert new payment method
        $sql = "INSERT INTO admin_payment_methods (payment_type, account_name, account_number, additional_info, is_active) 
                VALUES ('binance', ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssi", $account_name, $account_number, $additional_info, $is_active);

        if ($stmt->execute()) {
          $success_message = "Binance TRC20 account added successfully!";
        } else {
          $error_message = "Error adding account: " . $stmt->error;
        }

        $stmt->close();
      }
    }

    // Edit payment method
    elseif ($_POST['action'] == 'edit' && isset($_POST['id'])) {
      $id = (int)$_POST['id'];
      $account_name = mysqli_real_escape_string($conn, $_POST['account_name']);
      $account_number = mysqli_real_escape_string($conn, $_POST['account_number']);
      $additional_info = mysqli_real_escape_string($conn, $_POST['additional_info']);
      $is_active = isset($_POST['is_active']) ? 1 : 0;

      // Validate TRC20 address
      if (empty($account_name)) {
        $error_message = "Account name is required";
      } elseif (empty($account_number)) {
        $error_message = "TRC20 address is required";
      } elseif (!preg_match('/^T[a-zA-Z0-9]{33}$/', $account_number)) {
        $error_message = "Invalid TRC20 address format. Must start with T and be 34 characters long.";
      } else {
        // Update payment method
        $sql = "UPDATE admin_payment_methods SET account_name = ?, account_number = ?, additional_info = ?, is_active = ? WHERE id = ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssii", $account_name, $account_number, $additional_info, $is_active, $id);

        if ($stmt->execute()) {
          $success_message = "Binance TRC20 account updated successfully!";
        } else {
          $error_message = "Error updating account: " . $stmt->error;
        }

        $stmt->close();
      }
    }

    // Delete payment method
    elseif ($_POST['action'] == 'delete' && isset($_POST['id'])) {
      $id = (int)$_POST['id'];

      // Check if this is the only active payment method
      $check_sql = "SELECT COUNT(*) as count FROM admin_payment_methods WHERE is_active = 1";
      $check_result = $conn->query($check_sql);
      $check_data = $check_result->fetch_assoc();

      if ($check_data['count'] <= 1) {
        // Check if the one we're deleting is active
        $check_active_sql = "SELECT is_active FROM admin_payment_methods WHERE id = ?";
        $check_active_stmt = $conn->prepare($check_active_sql);
        $check_active_stmt->bind_param("i", $id);
        $check_active_stmt->execute();
        $check_active_result = $check_active_stmt->get_result();
        $check_active_data = $check_active_result->fetch_assoc();
        $check_active_stmt->close();

        if ($check_active_data['is_active'] == 1) {
          $error_message = "Cannot delete the only active payment method. Please add another active method first.";
        } else {
          // Delete payment method
          $sql = "DELETE FROM admin_payment_methods WHERE id = ?";

          $stmt = $conn->prepare($sql);
          $stmt->bind_param("i", $id);

          if ($stmt->execute()) {
            $success_message = "Binance TRC20 account deleted successfully!";
          } else {
            $error_message = "Error deleting account: " . $stmt->error;
          }

          $stmt->close();
        }
      } else {
        // Delete payment method
        $sql = "DELETE FROM admin_payment_methods WHERE id = ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
          $success_message = "Binance TRC20 account deleted successfully!";
        } else {
          $error_message = "Error deleting account: " . $stmt->error;
        }

        $stmt->close();
      }
    }

    // Toggle active status
    elseif ($_POST['action'] == 'toggle_status' && isset($_POST['id'])) {
      $id = (int)$_POST['id'];

      // Get current status
      $status_sql = "SELECT is_active FROM admin_payment_methods WHERE id = ?";
      $status_stmt = $conn->prepare($status_sql);
      $status_stmt->bind_param("i", $id);
      $status_stmt->execute();
      $status_result = $status_stmt->get_result();
      $status_data = $status_result->fetch_assoc();
      $status_stmt->close();

      $new_status = $status_data['is_active'] ? 0 : 1;

      // If deactivating, check if it's the only active one
      if ($new_status == 0) {
        $check_sql = "SELECT COUNT(*) as count FROM admin_payment_methods WHERE is_active = 1";
        $check_result = $conn->query($check_sql);
        $check_data = $check_result->fetch_assoc();

        if ($check_data['count'] <= 1) {
          $error_message = "Cannot deactivate the only active payment method. Please activate another method first.";
          goto skipToggle;
        }
      }

      // Update status
      $sql = "UPDATE admin_payment_methods SET is_active = ? WHERE id = ?";

      $stmt = $conn->prepare($sql);
      $stmt->bind_param("ii", $new_status, $id);

      if ($stmt->execute()) {
        $status_text = $new_status ? "activated" : "deactivated";
        $success_message = "Binance TRC20 account {$status_text} successfully!";
      } else {
        $error_message = "Error updating status: " . $stmt->error;
      }

      $stmt->close();
    }

    skipToggle:
  }
}

// Ensure admin_payment_methods table exists and has binance type
$check_table_sql = "SHOW TABLES LIKE 'admin_payment_methods'";
$table_exists = $conn->query($check_table_sql)->num_rows > 0;

if (!$table_exists) {
  // Create table
  $create_table_sql = "CREATE TABLE admin_payment_methods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payment_type ENUM('binance') NOT NULL DEFAULT 'binance',
    account_name VARCHAR(100) NOT NULL,
    account_number VARCHAR(100) NOT NULL,
    additional_info TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  )";

  $conn->query($create_table_sql);

  // Add default Binance account
  $default_name = "Admin Binance";
  $default_number = "TPD4HY9QsWrK6H2qS7iJbh3TSEYWuMtLRQ";
  $default_info = "Send to this Binance TRC20 address. Please include your username as reference.";

  $default_sql = "INSERT INTO admin_payment_methods (payment_type, account_name, account_number, additional_info, is_active) 
                VALUES ('binance', ?, ?, ?, 1)";

  $default_stmt = $conn->prepare($default_sql);
  $default_stmt->bind_param("sss", $default_name, $default_number, $default_info);
  $default_stmt->execute();
  $default_stmt->close();
} else {
  // Check if table needs to be modified for binance
  $check_column_sql = "SHOW COLUMNS FROM admin_payment_methods LIKE 'payment_type'";
  $column_result = $conn->query($check_column_sql);

  if ($column_result->num_rows > 0) {
    $column = $column_result->fetch_assoc();

    // If the enum doesn't include binance
    if (strpos($column['Type'], 'binance') === false) {
      // Alter the column to include binance
      $alter_sql = "ALTER TABLE admin_payment_methods MODIFY COLUMN payment_type ENUM('easypaisa','jazzcash','bank','binance') NOT NULL";
      $conn->query($alter_sql);
    }
  }
}

// Fetch all payment methods
$methods = [];
$methods_sql = "SELECT * FROM admin_payment_methods ORDER BY is_active DESC, created_at DESC";
$methods_result = $conn->query($methods_sql);

while ($row = $methods_result->fetch_assoc()) {
  $methods[] = $row;
}
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
      background: #3b82f6;
    }

    /* Active nav item */
    .nav-item.active {
      border-left: 3px solid #3b82f6;
      background-color: rgba(59, 130, 246, 0.1);
    }

    /* Blue gradient for Binance */
    .blue-gradient {
      background: linear-gradient(135deg, #1d4ed8, #3b82f6);
    }
  </style>
</head>

<body class="bg-gray-900 text-gray-100 flex min-h-screen">
  <?php include 'includes/sidebar.php'; ?>

  <!-- Main Content -->
  <div class="flex-1 flex flex-col overflow-hidden">
    <!-- Top Header -->


    <!-- Main Content Area -->
    <main class="flex-1 overflow-y-auto p-4 bg-gray-900">
      <!-- Back button -->
      <a href="index.php" class="inline-flex items-center px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg hover:bg-gray-600 transition-colors">
        <i class="fas fa-arrow-left mr-2"></i>Back to Main Site
      </a>
      <!-- Page Title -->
      <div class="mb-6">
        <h2 class="text-2xl font-bold">Binance Payment Methods</h2>
        <p class="text-gray-400">Manage TRC20 accounts that users can send deposits to</p>
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

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Add New Account Section -->
        <div class="lg:col-span-1">
          <div class="bg-gray-800 rounded-lg border border-gray-700 shadow-lg p-6">
            <h3 class="text-lg font-bold mb-4 flex items-center">
              <span class="w-8 h-8 rounded-full blue-gradient flex items-center justify-center mr-2">
                <i class="fas fa-plus text-white"></i>
              </span>
              Add TRC20 Account
            </h3>

            <form action="payment-methods.php" method="POST">
              <input type="hidden" name="action" value="add">

              <div class="mb-4">
                <label for="account_name" class="block text-sm font-medium text-gray-300 mb-2">Account Name</label>
                <input type="text" id="account_name" name="account_name" required class="w-full bg-gray-700 border border-gray-600 rounded-md py-2 px-3 text-white focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="e.g., Admin Binance">
              </div>

              <div class="mb-4">
                <label for="account_number" class="block text-sm font-medium text-gray-300 mb-2">TRC20 Address</label>
                <input type="text" id="account_number" name="account_number" required class="w-full bg-gray-700 border border-gray-600 rounded-md py-2 px-3 text-white focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="e.g., TPD4HY9QsWrK6H2qS7iJbh3TSEYWuMtLRQ">
                <p class="mt-1 text-xs text-gray-400">Must start with T and be 34 characters long</p>
              </div>

              <div class="mb-4">
                <label for="additional_info" class="block text-sm font-medium text-gray-300 mb-2">Additional Information</label>
                <textarea id="additional_info" name="additional_info" rows="3" class="w-full bg-gray-700 border border-gray-600 rounded-md py-2 px-3 text-white focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="e.g., Instructions for users when sending funds"></textarea>
              </div>

              <div class="mb-4 flex items-center">
                <input type="checkbox" id="is_active" name="is_active" checked class="h-4 w-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500">
                <label for="is_active" class="ml-2 block text-sm text-gray-300">Active</label>
              </div>

              <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md transition duration-200">
                <i class="fas fa-plus-circle mr-2"></i>Add TRC20 Account
              </button>
            </form>
          </div>
        </div>

        <!-- Existing Accounts Section -->
        <div class="lg:col-span-2">
          <div class="bg-gray-800 rounded-lg border border-gray-700 shadow-lg overflow-hidden">
            <div class="p-6 border-b border-gray-700">
              <h3 class="text-lg font-bold flex items-center">
                <span class="w-8 h-8 rounded-full blue-gradient flex items-center justify-center mr-2">
                  <i class="fas fa-coins text-white"></i>
                </span>
                Binance TRC20 Accounts
              </h3>
              <p class="text-gray-400 text-sm mt-1">Manage your Binance TRC20 accounts for accepting deposits</p>
            </div>

            <div class="overflow-x-auto">
              <?php if (empty($methods)): ?>
                <div class="p-6 text-center text-gray-400">
                  <i class="fas fa-info-circle text-3xl mb-3"></i>
                  <p>No TRC20 accounts found. Please add one using the form.</p>
                </div>
              <?php else: ?>
                <table class="w-full">
                  <thead class="bg-gray-700">
                    <tr>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Account Name</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">TRC20 Address</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Status</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Actions</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-700">
                    <?php foreach ($methods as $method): ?>
                      <tr class="hover:bg-gray-700">
                        <td class="px-6 py-4 whitespace-nowrap">
                          <div class="flex items-center">
                            <div class="h-8 w-8 rounded-full blue-gradient flex items-center justify-center text-white">
                              <i class="fas fa-coins"></i>
                            </div>
                            <div class="ml-3">
                              <div class="text-sm font-medium"><?php echo htmlspecialchars($method['account_name']); ?></div>
                              <div class="text-xs text-gray-400">Added: <?php echo date('M d, Y', strtotime($method['created_at'])); ?></div>
                            </div>
                          </div>
                        </td>
                        <td class="px-6 py-4">
                          <div class="flex items-center">
                            <div class="font-mono text-sm truncate max-w-xs">
                              <?php echo htmlspecialchars($method['account_number']); ?>
                            </div>
                            <button onclick="copyToClipboard('<?php echo htmlspecialchars($method['account_number']); ?>')" class="ml-2 text-blue-400 hover:text-blue-300">
                              <i class="fas fa-copy"></i>
                            </button>
                          </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                          <form method="POST" class="status-toggle-form">
                            <input type="hidden" name="action" value="toggle_status">
                            <input type="hidden" name="id" value="<?php echo $method['id']; ?>">
                            <button type="submit" class="<?php echo $method['is_active'] ? 'bg-blue-900 text-blue-400' : 'bg-gray-700 text-gray-400'; ?> px-3 py-1 rounded-full text-xs transition duration-200">
                              <?php echo $method['is_active'] ? 'Active' : 'Inactive'; ?>
                            </button>
                          </form>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                          <button class="text-blue-400 hover:text-blue-300 mr-2 edit-btn"
                            data-id="<?php echo $method['id']; ?>"
                            data-name="<?php echo htmlspecialchars($method['account_name']); ?>"
                            data-address="<?php echo htmlspecialchars($method['account_number']); ?>"
                            data-info="<?php echo htmlspecialchars($method['additional_info']); ?>"
                            data-active="<?php echo $method['is_active']; ?>">
                            <i class="fas fa-edit"></i>
                          </button>

                          <form method="POST" class="inline-block delete-form">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $method['id']; ?>">
                            <button type="submit" class="text-red-400 hover:text-red-300">
                              <i class="fas fa-trash-alt"></i>
                            </button>
                          </form>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>

  <!-- Edit Payment Method Modal -->
  <div id="editModal" class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50 hidden">
    <div class="bg-gray-800 rounded-lg w-full max-w-md">
      <div class="flex justify-between items-center p-6 border-b border-gray-700">
        <h3 class="text-xl font-bold">Edit TRC20 Account</h3>
        <button id="closeEditModal" class="text-gray-400 hover:text-white">
          <i class="fas fa-times text-xl"></i>
        </button>
      </div>

      <div class="p-6">
        <form id="editForm" method="POST">
          <input type="hidden" name="action" value="edit">
          <input type="hidden" id="edit_id" name="id" value="">

          <div class="mb-4">
            <label for="edit_account_name" class="block text-sm font-medium text-gray-300 mb-2">Account Name</label>
            <input type="text" id="edit_account_name" name="account_name" required class="w-full bg-gray-700 border border-gray-600 rounded-md py-2 px-3 text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
          </div>

          <div class="mb-4">
            <label for="edit_account_number" class="block text-sm font-medium text-gray-300 mb-2">TRC20 Address</label>
            <input type="text" id="edit_account_number" name="account_number" required class="w-full bg-gray-700 border border-gray-600 rounded-md py-2 px-3 text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
            <p class="mt-1 text-xs text-gray-400">Must start with T and be 34 characters long</p>
          </div>

          <div class="mb-4">
            <label for="edit_additional_info" class="block text-sm font-medium text-gray-300 mb-2">Additional Information</label>
            <textarea id="edit_additional_info" name="additional_info" rows="3" class="w-full bg-gray-700 border border-gray-600 rounded-md py-2 px-3 text-white focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
          </div>

          <div class="mb-4 flex items-center">
            <input type="checkbox" id="edit_is_active" name="is_active" class="h-4 w-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500">
            <label for="edit_is_active" class="ml-2 block text-sm text-gray-300">Active</label>
          </div>

          <div class="flex justify-end space-x-4">
            <button type="button" id="cancelEdit" class="bg-gray-700 hover:bg-gray-600 text-white font-medium py-2 px-4 rounded-md transition duration-200">
              Cancel
            </button>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md transition duration-200">
              Save Changes
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Toast Notification -->
  <div id="toast" class="fixed bottom-4 right-4 bg-gray-700 text-white py-2 px-4 rounded shadow-lg hidden"></div>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Edit modal functionality
      const editModal = document.getElementById('editModal');
      const editBtns = document.querySelectorAll('.edit-btn');
      const closeEditModal = document.getElementById('closeEditModal');
      const cancelEdit = document.getElementById('cancelEdit');

      if (editBtns.length > 0) {
        editBtns.forEach(btn => {
          btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const name = this.getAttribute('data-name');
            const address = this.getAttribute('data-address');
            const info = this.getAttribute('data-info');
            const active = this.getAttribute('data-active') === '1';

            document.getElementById('edit_id').value = id;
            document.getElementById('edit_account_name').value = name;
            document.getElementById('edit_account_number').value = address;
            document.getElementById('edit_additional_info').value = info;
            document.getElementById('edit_is_active').checked = active;

            editModal.classList.remove('hidden');
          });
        });
      }

      if (closeEditModal) {
        closeEditModal.addEventListener('click', function() {
          editModal.classList.add('hidden');
        });
      }

      if (cancelEdit) {
        cancelEdit.addEventListener('click', function() {
          editModal.classList.add('hidden');
        });
      }

      // Close modal when clicking outside
      editModal.addEventListener('click', function(e) {
        if (e.target === editModal) {
          editModal.classList.add('hidden');
        }
      });

      // Delete confirmation
      const deleteForms = document.querySelectorAll('.delete-form');

      if (deleteForms.length > 0) {
        deleteForms.forEach(form => {
          form.addEventListener('submit', function(e) {
            if (!confirm('Are you sure you want to delete this TRC20 account?')) {
              e.preventDefault();
            }
          });
        });
      }

      // Status toggle confirmation
      const statusForms = document.querySelectorAll('.status-toggle-form');

      if (statusForms.length > 0) {
        statusForms.forEach(form => {
          form.addEventListener('submit', function(e) {
            const isActive = form.querySelector('button').textContent.trim() === 'Active';

            if (isActive && !confirm('Are you sure you want to deactivate this TRC20 account?')) {
              e.preventDefault();
            }
          });
        });
      }
    });

    // Copy to clipboard function
    function copyToClipboard(text) {
      const textarea = document.createElement('textarea');
      textarea.value = text;
      document.body.appendChild(textarea);
      textarea.select();
      document.execCommand('copy');
      document.body.removeChild(textarea);

      // Show toast
      const toast = document.getElementById('toast');
      toast.textContent = 'TRC20 address copied to clipboard!';
      toast.classList.remove('hidden');

      setTimeout(() => {
        toast.classList.add('hidden');
      }, 3000);
    }
  </script>
</body>

</html>