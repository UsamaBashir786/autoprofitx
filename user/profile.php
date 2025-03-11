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

// Fetch user data from database
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  // User not found - should not happen unless session is corrupted
  session_destroy();
  header("Location: ../login.php");
  exit();
}

$user = $result->fetch_assoc();
$stmt->close();

// Process form submission for profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
  $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
  $email = mysqli_real_escape_string($conn, $_POST['email']);
  $phone = mysqli_real_escape_string($conn, $_POST['phone']);

  // Validate the input
  $valid = true;

  if (empty($full_name)) {
    $error_message = "Full name is required";
    $valid = false;
  } elseif (empty($email)) {
    $error_message = "Email is required";
    $valid = false;
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error_message = "Invalid email format";
    $valid = false;
  } elseif (empty($phone)) {
    $error_message = "Phone number is required";
    $valid = false;
  } elseif (!preg_match("/^03[0-9]{9}$/", $phone)) {
    $error_message = "Please enter a valid Pakistani mobile number (e.g., 03XXXXXXXXX)";
    $valid = false;
  }

  // Check if email is already in use by another user
  if ($valid && $email !== $user['email']) {
    $check_sql = "SELECT id FROM users WHERE email = ? AND id != ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("si", $email, $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
      $error_message = "Email address is already in use by another account";
      $valid = false;
    }

    $check_stmt->close();
  }

  if ($valid) {
    // Update user data
    $update_sql = "UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("sssi", $full_name, $email, $phone, $user_id);

    if ($update_stmt->execute()) {
      $success_message = "Profile updated successfully!";

      // Update session variables
      $_SESSION['full_name'] = $full_name;
      $_SESSION['email'] = $email;

      // Refresh user data
      $user['full_name'] = $full_name;
      $user['email'] = $email;
      $user['phone'] = $phone;
    } else {
      $error_message = "Error updating profile: " . $update_stmt->error;
    }

    $update_stmt->close();
  }
}

// Process password change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
  $current_password = $_POST['current_password'];
  $new_password = $_POST['new_password'];
  $confirm_password = $_POST['confirm_password'];

  // Validate the input
  $valid = true;

  if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
    $error_message = "All password fields are required";
    $valid = false;
  } elseif (strlen($new_password) < 8) {
    $error_message = "New password must be at least 8 characters long";
    $valid = false;
  } elseif ($new_password !== $confirm_password) {
    $error_message = "New passwords do not match";
    $valid = false;
  }

  if ($valid) {
    // Verify current password
    if (!password_verify($current_password, $user['password'])) {
      $error_message = "Current password is incorrect";
    } else {
      // Hash the new password
      $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

      // Update the password
      $update_sql = "UPDATE users SET password = ? WHERE id = ?";
      $update_stmt = $conn->prepare($update_sql);
      $update_stmt->bind_param("si", $hashed_password, $user_id);

      if ($update_stmt->execute()) {
        $success_message = "Password changed successfully!";
      } else {
        $error_message = "Error changing password: " . $update_stmt->error;
      }

      $update_stmt->close();
    }
  }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <?php include 'includes/head.php'; ?>
  <title>AutoProftX - My Profile</title>
</head>

<body class="bg-gray-900 text-white font-sans min-h-screen flex flex-col">
  <?php include 'includes/navbar.php'; ?>
  <?php include 'includes/mobile-bar.php'; ?>

  <?php include 'includes/pre-loader.php'; ?>

  <!-- Main Content -->
  <main style="display: none;" id="main-content" class="flex-grow py-6">
    <div class="container mx-auto px-4 sm:px-6 lg:px-8">
      <!-- Header Section -->
      <div class="mb-8">
        <h1 class="text-2xl font-bold">My Profile</h1>
        <p class="text-gray-400">View and update your personal information</p>
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

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Profile Summary Card -->
        <div class="bg-gray-800 rounded-lg border border-gray-700 p-6 lg:col-span-1">
          <div class="flex flex-col items-center text-center mb-6">
            <div class="h-24 w-24 rounded-full bg-gray-800 flex items-center justify-center border-2 border-yellow-500 text-yellow-500 mb-4">
              <i class="fas fa-user text-4xl"></i>
            </div>
            <h2 class="text-xl font-bold"><?php echo htmlspecialchars($user['full_name']); ?></h2>
            <p class="text-gray-400 text-sm">Member since <?php echo date('M d, Y', strtotime($user['registration_date'])); ?></p>

            <?php if (!empty($user['last_login'])): ?>
              <p class="text-gray-500 text-xs mt-2">Last login: <?php echo date('M d, Y H:i', strtotime($user['last_login'])); ?></p>
            <?php endif; ?>
          </div>

          <div class="border-t border-gray-700 pt-6">
            <div class="space-y-4">
              <div>
                <h3 class="text-sm font-medium text-gray-400">Email</h3>
                <p class="mt-1"><?php echo htmlspecialchars($user['email']); ?></p>
              </div>

              <div>
                <h3 class="text-sm font-medium text-gray-400">Phone Number</h3>
                <p class="mt-1"><?php echo htmlspecialchars($user['phone']); ?></p>
              </div>

              <div>
                <h3 class="text-sm font-medium text-gray-400">Account Status</h3>
                <p class="mt-1">
                  <?php if ($user['status'] == 'active'): ?>
                    <span class="px-2 py-1 text-xs rounded-full bg-green-900 text-green-400">Active</span>
                  <?php elseif ($user['status'] == 'inactive'): ?>
                    <span class="px-2 py-1 text-xs rounded-full bg-gray-700 text-gray-300">Inactive</span>
                  <?php elseif ($user['status'] == 'suspended'): ?>
                    <span class="px-2 py-1 text-xs rounded-full bg-red-900 text-red-400">Suspended</span>
                  <?php endif; ?>
                </p>
              </div>
            </div>
          </div>
        </div>

        <!-- Profile Settings -->
        <div class="bg-gray-800 rounded-lg border border-gray-700 p-6 lg:col-span-2">
          <div class="mb-6">
            <h2 class="text-xl font-bold mb-4">Edit Profile</h2>

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <!-- Full Name -->
                <div>
                  <label for="full_name" class="block text-sm font-medium text-gray-300 mb-2">Full Name</label>
                  <input type="text" id="full_name" name="full_name" required value="<?php echo htmlspecialchars($user['full_name']); ?>" class="bg-gray-800 focus:ring-yellow-500 focus:border-yellow-500 block w-full px-3 py-3 border-gray-700 rounded-md text-white">
                </div>

                <!-- Email -->
                <div>
                  <label for="email" class="block text-sm font-medium text-gray-300 mb-2">Email Address</label>
                  <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($user['email']); ?>" class="bg-gray-800 focus:ring-yellow-500 focus:border-yellow-500 block w-full px-3 py-3 border-gray-700 rounded-md text-white">
                </div>

                <!-- Phone -->
                <div>
                  <label for="phone" class="block text-sm font-medium text-gray-300 mb-2">Phone Number</label>
                  <div class="relative rounded-md shadow-sm">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                      <i class="fas fa-phone text-gray-500"></i>
                    </div>
                    <input type="text" id="phone" name="phone" required value="<?php echo htmlspecialchars($user['phone']); ?>" class="bg-gray-800 focus:ring-yellow-500 focus:border-yellow-500 block w-full pl-10 pr-3 py-3 border-gray-700 rounded-md text-white" placeholder="03XXXXXXXXX">
                  </div>
                </div>
              </div>

              <div>
                <input type="hidden" name="update_profile" value="1">
                <button type="submit" class="bg-gradient-to-r from-yellow-500 to-yellow-600 hover:from-yellow-600 hover:to-yellow-700 text-black font-bold py-3 px-6 rounded-lg transition duration-300 flex items-center justify-center">
                  <i class="fas fa-save mr-2"></i> Save Changes
                </button>
              </div>
            </form>
          </div>

          <div class="border-t border-gray-700 pt-6">
            <h2 class="text-xl font-bold mb-4">Change Password</h2>

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
              <div class="space-y-4 mb-6">
                <!-- Current Password -->
                <div>
                  <label for="current_password" class="block text-sm font-medium text-gray-300 mb-2">Current Password</label>
                  <div class="relative rounded-md shadow-sm">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                      <i class="fas fa-lock text-gray-500"></i>
                    </div>
                    <input type="password" id="current_password" name="current_password" required class="bg-gray-800 focus:ring-yellow-500 focus:border-yellow-500 block w-full pl-10 pr-3 py-3 border-gray-700 rounded-md text-white">
                  </div>
                </div>

                <!-- New Password -->
                <div>
                  <label for="new_password" class="block text-sm font-medium text-gray-300 mb-2">New Password</label>
                  <div class="relative rounded-md shadow-sm">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                      <i class="fas fa-lock text-gray-500"></i>
                    </div>
                    <input type="password" id="new_password" name="new_password" required class="bg-gray-800 focus:ring-yellow-500 focus:border-yellow-500 block w-full pl-10 pr-3 py-3 border-gray-700 rounded-md text-white">
                  </div>
                  <p class="mt-1 text-xs text-gray-500">Password must be at least 8 characters</p>
                </div>

                <!-- Confirm New Password -->
                <div>
                  <label for="confirm_password" class="block text-sm font-medium text-gray-300 mb-2">Confirm New Password</label>
                  <div class="relative rounded-md shadow-sm">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                      <i class="fas fa-lock text-gray-500"></i>
                    </div>
                    <input type="password" id="confirm_password" name="confirm_password" required class="bg-gray-800 focus:ring-yellow-500 focus:border-yellow-500 block w-full pl-10 pr-3 py-3 border-gray-700 rounded-md text-white">
                  </div>
                </div>
              </div>

              <div>
                <input type="hidden" name="change_password" value="1">
                <button type="submit" class="bg-gray-700 hover:bg-gray-600 text-white font-bold py-3 px-6 rounded-lg transition duration-300 flex items-center justify-center">
                  <i class="fas fa-key mr-2"></i> Change Password
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- Account Statistics Section -->
      <div class="mt-8 bg-gray-800 rounded-lg border border-gray-700 p-6">
        <h2 class="text-xl font-bold mb-6">Account Overview</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          <!-- Total Investments -->
          <div class="bg-gray-900 rounded-lg p-4 border border-gray-700">
            <div class="flex justify-between items-start">
              <div>
                <p class="text-gray-400 text-sm">Total Investments</p>
                <h3 class="text-2xl font-bold">
                  <?php
                  // Get total investments count - safely check if table exists first
                  $inv_count = 0;
                  $check_table_sql = "SELECT COUNT(*) as count FROM information_schema.tables 
                                  WHERE table_schema = DATABASE() 
                                  AND table_name = 'investments'";
                  $check_result = $conn->query($check_table_sql);
                  $table_exists = ($check_result && $check_result->fetch_assoc()['count'] > 0);

                  if ($table_exists) {
                    $inv_sql = "SELECT COUNT(*) as count FROM investments WHERE user_id = ?";
                    $inv_stmt = $conn->prepare($inv_sql);
                    $inv_stmt->bind_param("i", $user_id);
                    $inv_stmt->execute();
                    $inv_result = $inv_stmt->get_result();
                    $inv_count = $inv_result->fetch_assoc()['count'];
                    $inv_stmt->close();
                  }
                  echo $inv_count;
                  ?>
                </h3>
              </div>
              <div class="h-10 w-10 rounded-lg bg-gradient-to-r from-yellow-500 to-yellow-600 flex items-center justify-center">
                <i class="fas fa-chart-line text-black"></i>
              </div>
            </div>
          </div>

          <!-- Total Amount Invested -->
          <div class="bg-gray-900 rounded-lg p-4 border border-gray-700">
            <div class="flex justify-between items-start">
              <div>
                <p class="text-gray-400 text-sm">Total Invested</p>
                <h3 class="text-2xl font-bold">
                  <?php
                  // Get total amount invested - safely check if table exists first
                  $total_invested = 0;
                  if ($table_exists) {
                    $amt_sql = "SELECT COALESCE(SUM(amount), 0) as total FROM investments WHERE user_id = ?";
                    $amt_stmt = $conn->prepare($amt_sql);
                    $amt_stmt->bind_param("i", $user_id);
                    $amt_stmt->execute();
                    $amt_result = $amt_stmt->get_result();
                    $total_invested = $amt_result->fetch_assoc()['total'];
                    $amt_stmt->close();
                  }
                  echo '$' . number_format($total_invested, 0);                  ?>
                </h3>
              </div>
              <div class="h-10 w-10 rounded-lg bg-gradient-to-r from-yellow-500 to-yellow-600 flex items-center justify-center">
                <i class="fas fa-money-bill-wave text-black"></i>
              </div>
            </div>
          </div>

          <!-- Payment Methods -->
          <div class="bg-gray-900 rounded-lg p-4 border border-gray-700">
            <div class="flex justify-between items-start">
              <div>
                <p class="text-gray-400 text-sm">Payment Methods</p>
                <h3 class="text-2xl font-bold">
                  <?php
                  // Get payment methods count
                  $pay_count = 0;
                  // Check if table exists first
                  $check_pay_table_sql = "SELECT COUNT(*) as count FROM information_schema.tables 
                                  WHERE table_schema = DATABASE() 
                                  AND table_name = 'payment_methods'";
                  $check_pay_result = $conn->query($check_pay_table_sql);
                  $pay_table_exists = ($check_pay_result && $check_pay_result->fetch_assoc()['count'] > 0);

                  if ($pay_table_exists) {
                    $pay_sql = "SELECT COUNT(*) as count FROM payment_methods WHERE user_id = ?";
                    $pay_stmt = $conn->prepare($pay_sql);
                    $pay_stmt->bind_param("i", $user_id);
                    $pay_stmt->execute();
                    $pay_result = $pay_stmt->get_result();
                    $pay_count = $pay_result->fetch_assoc()['count'];
                    $pay_stmt->close();
                  }
                  echo $pay_count;
                  ?>
                </h3>
              </div>
              <div class="h-10 w-10 rounded-lg bg-gradient-to-r from-yellow-500 to-yellow-600 flex items-center justify-center">
                <i class="fas fa-credit-card text-black"></i>
              </div>
            </div>
          </div>

          <!-- Account Age -->
          <div class="bg-gray-900 rounded-lg p-4 border border-gray-700">
            <div class="flex justify-between items-start">
              <div>
                <p class="text-gray-400 text-sm">Account Age</p>
                <h3 class="text-2xl font-bold">
                  <?php
                  // Calculate account age in days
                  $reg_date = new DateTime($user['registration_date']);
                  $today = new DateTime();
                  $interval = $reg_date->diff($today);
                  echo $interval->days . ' days';
                  ?>
                </h3>
              </div>
              <div class="h-10 w-10 rounded-lg bg-gradient-to-r from-yellow-500 to-yellow-600 flex items-center justify-center">
                <i class="fas fa-calendar-alt text-black"></i>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>

  <?php include 'includes/footer.php'; ?>
  <?php include 'includes/js-links.php'; ?>
</body>

</html>