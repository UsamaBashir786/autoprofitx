<?php
// Start session
session_start();
include '../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
  header("Location: ../login.php");
  exit();
}

// Initialize variables
$user_id = $_SESSION['user_id'];
$success_message = "";
$error_message = "";
$account_name = "";
$account_number = "";
$is_default = false;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  $payment_type = 'binance'; // Always binance
  $account_name = mysqli_real_escape_string($conn, $_POST['account_name']);
  $account_number = mysqli_real_escape_string($conn, $_POST['account_number']);
  $is_default = isset($_POST['is_default']) ? 1 : 0;

  // Validate the input
  $valid = true;

  if (empty($account_name)) {
    $error_message = "Account name is required";
    $valid = false;
  } elseif (empty($account_number)) {
    $error_message = "TRC20 address is required";
    $valid = false;
  }

  // TRC20 address validation
  if ($valid) {
    // Basic TRC20 address validation - starts with T and is 34 characters
    if (!preg_match('/^T[a-zA-Z0-9]{33}$/', $account_number)) {
      $error_message = "Please enter a valid Binance TRC20 address (starts with T and is 34 characters)";
      $valid = false;
    }
  }

  // Check if a payment method already exists
  if ($valid) {
    $check_sql = "SELECT COUNT(*) as count FROM payment_methods WHERE user_id = ? AND payment_type = 'binance'";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $check_row = $check_result->fetch_assoc();
    $check_stmt->close();

    if ($check_row['count'] > 0) {
      $error_message = "You already have a Binance account added. Please edit or delete the existing one.";
      $valid = false;
    }
  }

  if ($valid) {
    // If this is set as default, unset any existing default
    if ($is_default) {
      $unset_default_sql = "UPDATE payment_methods SET is_default = 0 WHERE user_id = ?";
      $stmt = $conn->prepare($unset_default_sql);
      $stmt->bind_param("i", $user_id);
      $stmt->execute();
      $stmt->close();
    }

    // Insert the new payment method
    $sql = "INSERT INTO payment_methods (user_id, payment_type, account_name, account_number, is_default, created_at) 
                VALUES (?, 'binance', ?, ?, ?, NOW())";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issi", $user_id, $account_name, $account_number, $is_default);

    if ($stmt->execute()) {
      $success_message = "Binance account added successfully!";
      // Clear the form fields after successful submission
      $account_name = "";
      $account_number = "";
      $is_default = false;

      // Use the Post/Redirect/Get pattern to prevent form resubmission
      header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
      exit();
    } else {
      $error_message = "Error adding Binance account: " . $stmt->error;
    }

    $stmt->close();
  }
}

// Check for success messages in the URL
if (isset($_GET['success']) && $_GET['success'] == '1') {
  $success_message = "Binance account added successfully!";
} elseif (isset($_GET['default_set']) && $_GET['default_set'] == '1') {
  $success_message = "Default Binance account set successfully!";
} elseif (isset($_GET['deleted']) && $_GET['deleted'] == '1') {
  $success_message = "Binance account deleted successfully!";
} elseif (isset($_GET['error']) && $_GET['error'] == '1') {
  $error_message = "An error occurred. Please try again.";
}

// CREATE OR MODIFY THE PAYMENT_METHODS TABLE IF NEEDED
$check_table_sql = "SHOW TABLES LIKE 'payment_methods'";
$result = $conn->query($check_table_sql);
if ($result->num_rows == 0) {
  // Table doesn't exist, create it
  $create_table_sql = "CREATE TABLE payment_methods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    payment_type VARCHAR(50) NOT NULL,
    account_name VARCHAR(100) NOT NULL,
    account_number VARCHAR(100) NOT NULL,
    is_default TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  )";
  $conn->query($create_table_sql);
}

// Check if the payment_type column needs to be modified for binance
$check_column_sql = "SHOW COLUMNS FROM payment_methods LIKE 'payment_type'";
$result = $conn->query($check_column_sql);
if ($result->num_rows > 0) {
  $column = $result->fetch_assoc();
  // If the column is an enum that doesn't include 'binance'
  if (strpos($column['Type'], "enum") !== false && strpos($column['Type'], "binance") === false) {
    // Alter the column to be a VARCHAR instead of an enum
    $alter_sql = "ALTER TABLE payment_methods MODIFY COLUMN payment_type VARCHAR(50) NOT NULL";
    $conn->query($alter_sql);
  }
}

// Fetch existing binance accounts for this user
$binance_methods = [];

$sql = "SELECT * FROM payment_methods WHERE user_id = ? AND payment_type = 'binance' ORDER BY is_default DESC, created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
  $binance_methods[] = $row;
}

$stmt->close();

// Fetch admin's Binance account (for display in information section)
$admin_binance = null;
$admin_sql = "SELECT * FROM admin_payment_methods WHERE payment_type = 'binance' AND is_active = 1 LIMIT 1";
$admin_result = $conn->query($admin_sql);
if ($admin_result && $admin_result->num_rows > 0) {
  $admin_binance = $admin_result->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <?php include 'includes/head.php'; ?>
  <title>AutoProftX - Binance Accounts</title>
</head>

<body class="bg-gray-900 text-white font-sans min-h-screen flex flex-col">
  <?php include 'includes/navbar.php'; ?>
  <?php include 'includes/mobile-bar.php'; ?>

  <?php include 'includes/pre-loader.php'; ?>

  <!-- Main Content -->
  <main id="main-content" class="flex-grow py-6 bg-gray-900">
    <div class="container mx-auto px-4 sm:px-6 lg:px-8 max-w-6xl">
      <!-- Header Section with improved visual hierarchy -->
      <div class="mb-8 border-b border-gray-700 pb-4">
        <h1 class="text-2xl md:text-3xl font-bold text-white">Binance Accounts</h1>
        <p class="text-gray-400 mt-2">Manage your Binance TRC20 accounts securely</p>
      </div>

      <?php if (!empty($success_message)): ?>
        <div class="bg-green-900 bg-opacity-50 text-green-200 p-4 rounded-md mb-6 flex items-start animate-fadeIn">
          <i class="fas fa-check-circle mt-1 mr-3"></i>
          <span><?php echo $success_message; ?></span>
        </div>
      <?php endif; ?>

      <?php if (!empty($error_message)): ?>
        <div class="bg-red-900 bg-opacity-50 text-red-200 p-4 rounded-md mb-6 flex items-start animate-fadeIn">
          <i class="fas fa-exclamation-circle mt-1 mr-3"></i>
          <span><?php echo $error_message; ?></span>
        </div>
      <?php endif; ?>


      <!-- Tabs for better organization -->
      <div class="mb-8">
        <div class="flex space-x-1 border-b border-gray-700">
          <button id="tab-add" class="py-3 px-6 font-medium text-white bg-gray-800 rounded-t-lg border-b-2 border-blue-500 focus:outline-none">
            Add New
          </button>
          <button id="tab-manage" class="py-3 px-6 font-medium text-gray-400 hover:text-white focus:outline-none">
            Manage Existing
          </button>
        </div>
      </div>

      <!-- Add Binance Account Form - Section 1 -->
      <div id="section-add" class="bg-gray-800 rounded-lg border border-gray-700 p-6 mb-8 shadow-lg">
        <div class="flex items-center mb-6">
          <div class="h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center mr-3">
            <i class="fas fa-coins text-blue-600"></i>
          </div>
          <h2 class="text-xl font-bold">Add New Binance Account</h2>
        </div>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <!-- Account Name -->
            <div>
              <label for="account_name" class="block text-sm font-medium text-gray-300 mb-2">
                Account Holder Name <span class="text-red-400">*</span>
              </label>
              <input type="text" id="account_name" name="account_name" required value="<?php echo htmlspecialchars($account_name); ?>"
                class="bg-gray-700 focus:ring-blue-500 focus:border-blue-500 block w-full px-3 py-3 border border-gray-600 rounded-md text-white shadow-sm transition-all duration-200 hover:border-gray-500"
                placeholder="Enter your full name">
              <p class="mt-1 text-xs text-gray-500">Name registered with your Binance account</p>
            </div>

            <!-- TRC20 Address -->
            <div>
              <label for="account_number" class="block text-sm font-medium text-gray-300 mb-2">
                TRC20 Address <span class="text-red-400">*</span>
              </label>
              <div class="mt-1 relative rounded-md shadow-sm">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <i class="fas fa-wallet text-gray-500"></i>
                </div>
                <input type="text" id="account_number" name="account_number" required
                  value="<?php echo htmlspecialchars($account_number); ?>"
                  class="bg-gray-700 focus:ring-blue-500 focus:border-blue-500 block w-full pl-10 pr-3 py-3 border border-gray-600 rounded-md text-white shadow-sm transition-all duration-200 hover:border-gray-500"
                  placeholder="e.g., TPD4HY9QsWrK6H2qS7iJbh3TSEYWuMtLRQ">
              </div>
              <p class="mt-1 text-xs text-gray-500">Format: Binance TRC20 address (starts with T and is 34 characters)</p>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <!-- Default Option with better styling -->
            <div class="flex items-center pt-8">
              <label class="flex items-center cursor-pointer">
                <div class="relative">
                  <input id="is_default" name="is_default" type="checkbox" <?php echo $is_default ? 'checked' : ''; ?>
                    class="sr-only">
                  <div class="block w-14 h-8 bg-gray-600 rounded-full"></div>
                  <div class="dot absolute left-1 top-1 bg-gray-400 w-6 h-6 rounded-full transition"></div>
                </div>
                <div class="ml-3 text-sm text-gray-400">
                  Set as default payment method
                  <p class="text-xs text-gray-500 mt-1">Use this account for all transactions</p>
                </div>
              </label>
            </div>
          </div>

          <div>
            <button type="submit"
              class="bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white font-bold py-3 px-6 rounded-lg transition duration-300 flex items-center justify-center w-full md:w-auto shadow-lg transform hover:scale-105">
              <i class="fas fa-plus-circle mr-2"></i> Add Binance Account
            </button>
          </div>
        </form>
      </div>

      <!-- Existing Binance Accounts Section - Section 2 -->
      <div id="section-manage" class="hidden">
        <!-- Binance Section with improved cards -->
        <div class="bg-gray-800 rounded-lg border border-gray-700 p-6 shadow-lg">
          <div class="flex items-center mb-6">
            <div class="h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center mr-3">
              <i class="fas fa-coins text-blue-600"></i>
            </div>
            <h2 class="text-xl font-bold">Binance Accounts</h2>
          </div>

          <?php if (empty($binance_methods)): ?>
            <div class="bg-gray-900 rounded-lg p-6 text-center text-gray-400 border border-dashed border-gray-700">
              <i class="fas fa-info-circle text-2xl mb-2"></i>
              <p>You haven't added any Binance accounts yet.</p>
              <button id="add-binance-btn" class="mt-3 text-blue-500 hover:text-blue-400 text-sm flex items-center mx-auto">
                <i class="fas fa-plus-circle mr-1"></i> Add Binance Account
              </button>
            </div>
          <?php else: ?>
            <div class="space-y-4">
              <?php foreach ($binance_methods as $method): ?>
                <div class="bg-gray-900 rounded-lg p-4 border border-gray-700 hover:border-blue-500 transition duration-300 hover:shadow-lg relative">
                  <!-- Payment method actions dropdown with better usability -->
                  <div class="absolute top-4 right-4">
                    <div class="dropdown inline-block relative">
                      <button class="text-gray-400 hover:text-white p-1 rounded-full hover:bg-gray-700">
                        <i class="fas fa-ellipsis-v"></i>
                      </button>
                      <div class="dropdown-content hidden absolute right-0 mt-2 w-48 bg-gray-800 rounded-lg shadow-lg z-10 py-2 border border-gray-700">
                        <a href="edit-payment.php?id=<?php echo $method['id']; ?>" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-700 flex items-center">
                          <i class="fas fa-edit mr-2 text-gray-500"></i> Edit
                        </a>
                        <?php if (!$method['is_default']): ?>
                          <a href="set-default-payment.php?id=<?php echo $method['id']; ?>" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-700 flex items-center">
                            <i class="fas fa-star mr-2 text-blue-500"></i> Set as default
                          </a>
                        <?php endif; ?>
                        <div class="border-t border-gray-700 my-1"></div>
                        <a href="delete-payment.php?id=<?php echo $method['id']; ?>" class="block px-4 py-2 text-sm text-red-400 hover:bg-gray-700 flex items-center"
                          >
                          <i class="fas fa-trash-alt mr-2"></i> Remove
                        </a>
                      </div>
                    </div>
                  </div>

                  <div class="flex items-start mb-2">
                    <div class="flex-shrink-0 mt-1">
                      <div class="h-8 w-8 rounded-full bg-blue-800 bg-opacity-30 flex items-center justify-center">
                        <i class="fas fa-wallet text-blue-500"></i>
                      </div>
                    </div>
                    <div class="ml-3 flex-grow">
                      <h4 class="text-md font-bold text-white"><?php echo htmlspecialchars($method['account_name']); ?></h4>
                      <p class="text-gray-400 text-sm flex items-center">
                        <i class="fas fa-wallet mr-2 text-xs text-gray-500"></i>
                        <!-- Show first 8 and last 8 characters of TRC20 address -->
                        <?php echo substr($method['account_number'], 0, 8) . '...' . substr($method['account_number'], -8); ?>
                        <button onclick="copyToClipboard('<?php echo htmlspecialchars($method['account_number']); ?>')"
                          class="ml-2 text-blue-400 hover:text-blue-300 copy-btn" data-address="<?php echo htmlspecialchars($method['account_number']); ?>">
                          <i class="fas fa-copy"></i>
                        </button>
                      </p>
                    </div>
                  </div>

                  <div class="flex items-center justify-between pt-2 border-t border-gray-800">
                    <?php if ($method['is_default']): ?>
                      <span class="bg-blue-900 text-blue-400 px-3 py-1 text-xs rounded-full flex items-center">
                        <i class="fas fa-check-circle mr-1"></i> Default
                      </span>
                    <?php else: ?>
                      <span class="bg-gray-700 text-gray-300 px-3 py-1 text-xs rounded-full">Added</span>
                    <?php endif; ?>
                    <span class="text-xs text-gray-400 flex items-center">
                      <i class="far fa-calendar-alt mr-1"></i>
                      <?php echo date('M d, Y', strtotime($method['created_at'])); ?>
                    </span>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </main>

  <!-- Toast Notification for Copy Success -->
  <div id="copy-toast" class="fixed bottom-4 right-4 bg-blue-600 text-white px-4 py-2 rounded-lg shadow-lg hidden transform transition-transform duration-300 translate-y-full">
    <div class="flex items-center">
      <i class="fas fa-check-circle mr-2"></i>
      <span id="copy-toast-message">Address copied!</span>
    </div>
  </div>

  <!-- JavaScript for tab functionality -->
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const tabAdd = document.getElementById('tab-add');
      const tabManage = document.getElementById('tab-manage');
      const sectionAdd = document.getElementById('section-add');
      const sectionManage = document.getElementById('section-manage');

      // Toggle switch functionality for checkbox
      const toggleCheckbox = function() {
        const checkbox = document.getElementById('is_default');
        const dot = document.querySelector('.dot');

        if (checkbox && dot) {
          if (checkbox.checked) {
            dot.classList.add('transform', 'translate-x-6', 'bg-blue-500');
          } else {
            dot.classList.remove('transform', 'translate-x-6', 'bg-blue-500');
          }
        }
      };

      // Initialize toggle
      toggleCheckbox();

      // Add event listener to checkbox
      const defaultCheckbox = document.getElementById('is_default');
      if (defaultCheckbox) {
        defaultCheckbox.addEventListener('change', toggleCheckbox);
      }

      // Tab switching functionality
      if (tabAdd && tabManage && sectionAdd && sectionManage) {
        // Check if we should show the manage tab based on URL parameter
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('success') === '1' || urlParams.get('default_set') === '1' || urlParams.get('deleted') === '1') {
          // Show the manage tab if an action was just completed
          sectionAdd.classList.add('hidden');
          sectionManage.classList.remove('hidden');
          tabManage.classList.add('border-blue-500', 'bg-gray-800', 'text-white');
          tabManage.classList.remove('text-gray-400');
          tabAdd.classList.remove('border-blue-500', 'bg-gray-800', 'text-white');
          tabAdd.classList.add('text-gray-400');
        }

        tabAdd.addEventListener('click', function() {
          sectionAdd.classList.remove('hidden');
          sectionManage.classList.add('hidden');
          tabAdd.classList.add('border-blue-500', 'bg-gray-800', 'text-white');
          tabAdd.classList.remove('text-gray-400');
          tabManage.classList.remove('border-blue-500', 'bg-gray-800', 'text-white');
          tabManage.classList.add('text-gray-400');
        });

        tabManage.addEventListener('click', function() {
          sectionAdd.classList.add('hidden');
          sectionManage.classList.remove('hidden');
          tabManage.classList.add('border-blue-500', 'bg-gray-800', 'text-white');
          tabManage.classList.remove('text-gray-400');
          tabAdd.classList.remove('border-blue-500', 'bg-gray-800', 'text-white');
          tabAdd.classList.add('text-gray-400');
        });
      }

      // Quick add button
      const addBinanceBtn = document.getElementById('add-binance-btn');
      if (addBinanceBtn) {
        addBinanceBtn.addEventListener('click', function() {
          tabAdd.click();
        });
      }

      // Show main content (remove the initial style="display: none;")
      const mainContent = document.getElementById('main-content');
      if (mainContent) {
        mainContent.style.display = 'block';
      }

      // Initialize copy buttons
      const copyBtns = document.querySelectorAll('.copy-btn');
      copyBtns.forEach(btn => {
        btn.addEventListener('click', function(e) {
          e.preventDefault();
          e.stopPropagation();
          const address = this.getAttribute('data-address');
          copyToClipboard(address);
        });
      });
    });

    // Toast notification function
    function showToast(message) {
      const toast = document.getElementById('copy-toast');
      const messageEl = document.getElementById('copy-toast-message');

      if (toast && messageEl) {
        messageEl.textContent = message;
        toast.classList.remove('hidden', 'translate-y-full');

        setTimeout(() => {
          toast.classList.add('translate-y-full');
          setTimeout(() => {
            toast.classList.add('hidden');
          }, 300);
        }, 2000);
      }
    }

    // Copy to clipboard function
    function copyToClipboard(text) {
      const textarea = document.createElement('textarea');
      textarea.value = text;
      document.body.appendChild(textarea);
      textarea.select();
      document.execCommand('copy');
      document.body.removeChild(textarea);

      // Show toast notification
      showToast('Address copied to clipboard!');
    }

    // Copy admin address
    function copyAdminAddress(text) {
      copyToClipboard(text);
    }
  </script>

  <?php include 'includes/footer.php'; ?>
  <?php include 'includes/js-links.php'; ?>
  <script>
    // Dropdown toggle functionality
    document.addEventListener('DOMContentLoaded', function() {
      // Get all dropdown buttons
      const dropdownButtons = document.querySelectorAll('.dropdown button');

      // Add click event to each dropdown button
      dropdownButtons.forEach(button => {
        button.addEventListener('click', function(e) {
          e.stopPropagation(); // Prevent event from bubbling up

          // Get the dropdown content element
          const dropdownContent = this.nextElementSibling;

          // Close all other dropdowns first
          document.querySelectorAll('.dropdown-content').forEach(content => {
            if (content !== dropdownContent) {
              content.classList.add('hidden');
            }
          });

          // Toggle current dropdown
          dropdownContent.classList.toggle('hidden');
        });
      });

      // Close dropdowns when clicking outside
      document.addEventListener('click', function() {
        document.querySelectorAll('.dropdown-content').forEach(content => {
          content.classList.add('hidden');
        });
      });
    });
  </script>
</body>

</html>