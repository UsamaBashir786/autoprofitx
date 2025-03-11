<?php
// Start session
session_start();
include '../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
  header("Location: ../login.php");
  exit();
}

$user_id = $_SESSION['user_id'];
$success_message = "";
$error_message = "";

// Check if payment method ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
  header("Location: payment-methods.php");
  exit();
}

$payment_id = $_GET['id'];

// Fetch payment method details
$sql = "SELECT * FROM payment_methods WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $payment_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  header("Location: payment-methods.php");
  exit();
}

$payment_method = $result->fetch_assoc();
$stmt->close();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
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

  if ($valid) {
    // If this is set as default, unset any existing default
    if ($is_default) {
      $unset_default_sql = "UPDATE payment_methods SET is_default = 0 WHERE user_id = ?";
      $stmt = $conn->prepare($unset_default_sql);
      $stmt->bind_param("i", $user_id);
      $stmt->execute();
      $stmt->close();
    }

    // Update the payment method
    $sql = "UPDATE payment_methods SET account_name = ?, account_number = ?, is_default = ? WHERE id = ? AND user_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssiii", $account_name, $account_number, $is_default, $payment_id, $user_id);
    
    if ($stmt->execute()) {
      $success_message = "Binance account updated successfully!";
      // Update the payment method variable for the form
      $payment_method['account_name'] = $account_name;
      $payment_method['account_number'] = $account_number;
      $payment_method['is_default'] = $is_default;
    } else {
      $error_message = "Error updating Binance account: " . $stmt->error;
    }
    
    $stmt->close();
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <?php include 'includes/head.php'; ?>
  <title>AutoProftX - Edit Binance Account</title>
</head>

<body class="bg-gray-900 text-white font-sans min-h-screen flex flex-col">
  <?php include 'includes/navbar.php'; ?>
  <?php include 'includes/pre-loader.php'; ?>
  <?php include 'includes/mobile-bar.php'; ?>


  <!-- Main Content -->
  <main style="display: none;" id="main-content" class="flex-grow py-6">
    <div class="container mx-auto px-4 sm:px-6 lg:px-8">
      <!-- Header Section -->
      <div class="mb-8">
        <h1 class="text-2xl font-bold">Edit Binance Account</h1>
        <p class="text-gray-400">Update your Binance TRC20 account information</p>
      </div>
      
      <!-- Edit Payment Method Form -->
      <div class="bg-gray-800 rounded-lg border border-gray-700 p-6 mb-8 max-w-2xl mx-auto">
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
        
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . '?id=' . $payment_id; ?>" method="POST">
          <div class="mb-6">
            <!-- Payment Method Type (display only) -->
            <div class="mb-6">
              <label class="block text-sm font-medium text-gray-300 mb-2">Payment Method</label>
              <div class="bg-gray-700 px-3 py-3 rounded-md text-white flex items-center">
                <i class="fas fa-coins text-blue-500 mr-2"></i>
                Binance
              </div>
            </div>
          
            <!-- Account Name -->
            <div class="mb-6">
              <label for="account_name" class="block text-sm font-medium text-gray-300 mb-2">Account Holder Name</label>
              <input type="text" id="account_name" name="account_name" required value="<?php echo htmlspecialchars($payment_method['account_name']); ?>" class="bg-gray-700 focus:ring-blue-500 focus:border-blue-500 block w-full px-3 py-3 border border-gray-600 rounded-md text-white">
              <p class="mt-1 text-xs text-gray-500">Name registered with your Binance account</p>
            </div>
            
            <!-- TRC20 Address -->
            <div class="mb-6">
              <label for="account_number" class="block text-sm font-medium text-gray-300 mb-2">TRC20 Address</label>
              <div class="relative rounded-md shadow-sm">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <i class="fas fa-wallet text-gray-500"></i>
                </div>
                <input type="text" id="account_number" name="account_number" required value="<?php echo htmlspecialchars($payment_method['account_number']); ?>" class="bg-gray-700 focus:ring-blue-500 focus:border-blue-500 block w-full pl-10 pr-3 py-3 border border-gray-600 rounded-md text-white">
              </div>
              <p class="mt-1 text-xs text-gray-500">Format: Binance TRC20 address (starts with T and is 34 characters)</p>
            </div>
            
            <!-- Default Option -->
            <div class="flex items-center mb-6">
              <label class="flex items-center cursor-pointer">
                <div class="relative">
                  <input id="is_default" name="is_default" type="checkbox" <?php echo $payment_method['is_default'] ? 'checked' : ''; ?> class="sr-only">
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
          
          <div class="flex justify-between">
            <a href="payment-methods.php" class="bg-gray-700 hover:bg-gray-600 text-white font-bold py-3 px-6 rounded-lg transition duration-300 flex items-center justify-center">
              <i class="fas fa-arrow-left mr-2"></i> Back
            </a>
            
            <button type="submit" class="bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white font-bold py-3 px-6 rounded-lg transition duration-300 flex items-center justify-center">
              <i class="fas fa-save mr-2"></i> Save Changes
            </button>
          </div>
        </form>
      </div>
    </div>
  </main>

  <?php include 'includes/footer.php'; ?>
  <?php include 'includes/js-links.php'; ?>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Show main content
      document.getElementById('main-content').style.display = 'block';
      
      // Toggle switch functionality for checkbox
      const toggleCheckbox = function() {
        const checkbox = document.getElementById('is_default');
        const dot = document.querySelector('.dot');
        
        if (checkbox.checked) {
          dot.classList.add('transform', 'translate-x-6', 'bg-blue-500');
        } else {
          dot.classList.remove('transform', 'translate-x-6', 'bg-blue-500');
        }
      };
      
      // Initialize toggle state
      toggleCheckbox();
      
      // Add event listener to checkbox
      document.getElementById('is_default').addEventListener('change', toggleCheckbox);
    });
  </script>
</body>
</html>