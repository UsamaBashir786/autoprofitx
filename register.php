<?php
include 'config/db.php';
// Include the tree commission system
require_once('tree_commission.php');

// Function to sanitize user inputs
function sanitize_input($data)
{
  $data = trim($data);
  $data = stripslashes($data);
  $data = htmlspecialchars($data);
  return $data;
}

// Function to generate a unique referral code for new users
function generateReferralCode($length = 8)
{
  $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
  $code = '';

  for ($i = 0; $i < $length; $i++) {
    $code .= $characters[rand(0, strlen($characters) - 1)];
  }

  return $code;
}

// Function to generate a backup code
function generateBackupCode($length = 10)
{
  // Use characters that are easy to read and type
  $characters = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
  $code = '';

  // Generate the code with random characters
  for ($i = 0; $i < $length; $i++) {
    $code .= $characters[rand(0, strlen($characters) - 1)];
  }

  // Format the code as XXXX-XXXX-XX for better readability
  $formatted = substr($code, 0, 4) . '-' . substr($code, 4, 4) . '-' . substr($code, 8, 2);

  return $formatted;
}

// Function to generate and store backup codes for a user
function createBackupCodesForUser($userId, $conn, $codeCount = 10)
{
  $codes = [];

  // First create table if it doesn't exist
  $tableCheck = "CREATE TABLE IF NOT EXISTS `backup_codes` (
    `id` int NOT NULL AUTO_INCREMENT,
    `user_id` int NOT NULL,
    `code` varchar(12) NOT NULL,
    `is_used` tinyint(1) NOT NULL DEFAULT '0',
    `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
    `used_at` datetime DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    KEY `code` (`code`)
  ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;";

  $conn->query($tableCheck);

  // Generate new codes
  $insertSql = "INSERT INTO backup_codes (user_id, code) VALUES (?, ?)";
  $insertStmt = $conn->prepare($insertSql);

  for ($i = 0; $i < $codeCount; $i++) {
    $code = generateBackupCode();
    $codes[] = $code;

    $insertStmt->bind_param("is", $userId, $code);
    $insertStmt->execute();
  }

  $insertStmt->close();
  return $codes;
}

// Function to process a referral when a new user registers
function processReferral($referrerId, $newUserId)
{
  global $conn;

  // Verify the referring user exists
  $check_user = "SELECT id FROM users WHERE id = ?";
  $stmt = $conn->prepare($check_user);
  $stmt->bind_param("i", $referrerId);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows === 0) {
    // Referring user doesn't exist
    return false;
  }

  // Start transaction
  $conn->begin_transaction();

  try {
    // Insert referral record
    $referral_query = "INSERT INTO referrals (referrer_id, referred_id) VALUES (?, ?)";
    $stmt = $conn->prepare($referral_query);
    $stmt->bind_param("ii", $referrerId, $newUserId);
    $stmt->execute();

    // Add bonus to referrer's wallet
    $bonus_amount = 5.00; // ₹100 bonus

    // Check if wallet exists for referrer
    $wallet_check = "SELECT id FROM wallets WHERE user_id = ?";
    $stmt = $conn->prepare($wallet_check);
    $stmt->bind_param("i", $referrerId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
      // Create wallet for referrer if it doesn't exist
      $create_wallet = "INSERT INTO wallets (user_id, balance) VALUES (?, ?)";
      $stmt = $conn->prepare($create_wallet);
      $stmt->bind_param("id", $referrerId, $bonus_amount);
      $stmt->execute();
    } else {
      // Update existing wallet
      $update_wallet = "UPDATE wallets SET balance = balance + ? WHERE user_id = ?";
      $stmt = $conn->prepare($update_wallet);
      $stmt->bind_param("di", $bonus_amount, $referrerId);
      $stmt->execute();
    }

    // Record transaction for the referral bonus
    $transaction_query = "INSERT INTO transactions (
            user_id, 
            transaction_type, 
            amount, 
            status, 
            description,
            reference_id
        ) VALUES (?, 'deposit', ?, 'completed', ?, ?)";

    $description = "Referral Bonus";
    $reference_id = "REF-" . $newUserId;

    $stmt = $conn->prepare($transaction_query);
    $stmt->bind_param("idss", $referrerId, $bonus_amount, $description, $reference_id);
    $stmt->execute();

    // Update referral status to paid
    $update_referral = "UPDATE referrals SET status = 'paid', paid_at = NOW() WHERE referrer_id = ? AND referred_id = ?";
    $stmt = $conn->prepare($update_referral);
    $stmt->bind_param("ii", $referrerId, $newUserId);
    $stmt->execute();

    // Commit transaction
    $conn->commit();

    // BUILD THE REFERRAL TREE - New addition for tree commission system
    onUserRegistration($newUserId, $referrerId);

    return true;
  } catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    error_log("Error processing referral: " . $e->getMessage());
    return false;
  }
}

// Initialize variables for form data and errors
$fullName = $email = $phone = $password = $confirmPassword = $referralCode = "";
$fullNameErr = $emailErr = $phoneErr = $passwordErr = $confirmPasswordErr = $termsErr = "";
$registrationSuccess = false;

// Get referral code from URL if present
$referralCode = isset($_GET['ref']) ? sanitize_input($_GET['ref']) : "";

// Process form when submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

  // Get referral code from form if submitted
  $referralCode = isset($_POST["referralCode"]) ? sanitize_input($_POST["referralCode"]) : "";

  // Validate Full Name
  if (empty($_POST["fullName"])) {
    $fullNameErr = "Full name is required";
  } else {
    $fullName = sanitize_input($_POST["fullName"]);
    // Check if name only contains letters and whitespace
    if (!preg_match("/^[a-zA-Z ]*$/", $fullName)) {
      $fullNameErr = "Only letters and white space allowed";
    }
  }

  // Validate Email
  if (empty($_POST["email"])) {
    $emailErr = "Email is required";
  } else {
    $email = sanitize_input($_POST["email"]);
    // Check if email is valid
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $emailErr = "Invalid email format";
    } else {
      // Check if email already exists in database
      $sql = "SELECT id FROM users WHERE email = ?";
      $stmt = $conn->prepare($sql);
      $stmt->bind_param("s", $email);
      $stmt->execute();
      $result = $stmt->get_result();

      if ($result->num_rows > 0) {
        $emailErr = "Email already exists. Please use a different email or login.";
      }
      $stmt->close();
    }
  }

  // Validate Phone Number
  if (empty($_POST["phone"])) {
    $phoneErr = "Phone number is required";
  } else {
    $phone = sanitize_input($_POST["phone"]);
    // Simple validation for phone number - adjust regex as needed for your country format
    if (!preg_match("/^\+?[0-9\s-]{10,15}$/", $phone)) {
      $phoneErr = "Invalid phone number format";
    }
  }

  // Validate Password
  if (empty($_POST["password"])) {
    $passwordErr = "Password is required";
  } else {
    $password = sanitize_input($_POST["password"]);
    // Check password strength
    if (strlen($password) < 8) {
      $passwordErr = "Password must be at least 8 characters";
    }
  }

  // Validate Confirm Password
  if (empty($_POST["confirmPassword"])) {
    $confirmPasswordErr = "Please confirm your password";
  } else {
    $confirmPassword = sanitize_input($_POST["confirmPassword"]);
    // Check if passwords match
    if ($password !== $confirmPassword) {
      $confirmPasswordErr = "Passwords do not match";
    }
  }

  // Validate Terms Checkbox
  if (!isset($_POST["terms"]) || $_POST["terms"] != "on") {
    $termsErr = "You must agree to the Terms of Service";
  }

  // If no errors, insert user into database
  if (empty($fullNameErr) && empty($emailErr) && empty($phoneErr) && empty($passwordErr) && empty($confirmPasswordErr) && empty($termsErr)) {

    // Hash the password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Generate a unique referral code for the new user
    $newReferralCode = generateReferralCode();

    // Find referrer ID if referral code provided
    $referrerId = null;
    if (!empty($referralCode)) {
      $referrerQuery = "SELECT id FROM users WHERE referral_code = ?";
      $stmt = $conn->prepare($referrerQuery);
      $stmt->bind_param("s", $referralCode);
      $stmt->execute();
      $result = $stmt->get_result();

      if ($result->num_rows > 0) {
        $referrer = $result->fetch_assoc();
        $referrerId = $referrer['id'];
      }
      $stmt->close();
    }

    // Start transaction
    $conn->begin_transaction();

    try {
      // Prepare SQL statement to prevent SQL injection
      $sql = "INSERT INTO users (full_name, email, phone, referral_code, referred_by, password, registration_date) VALUES (?, ?, ?, ?, ?, ?, NOW())";
      $stmt = $conn->prepare($sql);
      $stmt->bind_param("ssssss", $fullName, $email, $phone, $newReferralCode, $referrerId, $hashedPassword);

      // Execute the statement
      if ($stmt->execute()) {
        $registrationSuccess = true;

        // Get the new user ID
        $userId = $stmt->insert_id;

        // Create wallet record for the new user
        $createWalletSql = "INSERT INTO wallets (user_id, balance, created_at) VALUES (?, 0, NOW())";
        $walletStmt = $conn->prepare($createWalletSql);
        $walletStmt->bind_param("i", $userId);
        $walletStmt->execute();
        $walletStmt->close();

        // Process referral if applicable
        if ($referrerId) {
          processReferral($referrerId, $userId);
        }

        // Generate backup codes for the new user
        $backupCodes = createBackupCodesForUser($userId, $conn);

        // Store backup codes in session for display
        $_SESSION['new_backup_codes'] = $backupCodes;

        // Commit transaction
        $conn->commit();

        // Set session variables
        $_SESSION['user_id'] = $userId;
        $_SESSION['full_name'] = $fullName;
        $_SESSION['email'] = $email;

        // Redirect to backup codes display page
        header("Location: registration_success.php");
        exit();
      } else {
        // Rollback on error
        $conn->rollback();
        echo "Error: " . $stmt->error;
      }

      $stmt->close();
    } catch (Exception $e) {
      // Rollback transaction on error
      $conn->rollback();
      echo "Error: " . $e->getMessage();
    }
  }
}

$conn->close();
?>
<!doctype html>
<html lang="en">

<head>
  <?php include 'includes/head.php'; ?>
</head>

<body class="bg-black text-white font-sans">
  <?php include 'includes/navbar.php'; ?>

  <!-- Registration Form -->
  <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
      <div class="text-center">
        <h2 class="mt-6 text-3xl font-extrabold text-white">
          Create your account
        </h2>
        <p class="mt-2 text-sm text-gray-400">
          Join AutoProftX and start your investment journey
        </p>
      </div>
      <!-- Add this code in your registration.php where you show success messages -->
      <?php if (isset($registrationSuccess) && $registrationSuccess && !empty($referralCode) && $referrerId): ?>
        <div class="mb-4 bg-blue-900 text-blue-200 p-4 rounded-md">
          <p class="flex items-center">
            <i class="fas fa-gift mr-2 text-yellow-500"></i>
            You registered with a referral code! Your referrer has received a ₹100 bonus.
          </p>
        </div>
      <?php endif; ?>
      <div class="mt-8 bg-gradient-to-b from-gray-900 to-black rounded-xl p-8 shadow-2xl border border-gray-800">
        <?php if (isset($registrationSuccess) && $registrationSuccess): ?>
          <div class="mb-4 bg-green-900 text-green-200 p-4 rounded-md">
            Registration successful! Redirecting to dashboard...
          </div>
        <?php endif; ?>

        <form class="space-y-6" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
          <div class="grid grid-cols-1 gap-6">
            <!-- Full Name -->
            <div>
              <label for="fullName" class="block text-sm font-medium text-gray-300">Full Name</label>
              <div class="mt-1 relative rounded-md shadow-sm">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <i class="fas fa-user text-gray-500"></i>
                </div>
                <input type="text" id="fullName" name="fullName" value="<?php echo $fullName; ?>" class="bg-gray-800 <?php echo !empty($fullNameErr) ? 'border-red-500 focus:ring-red-500 focus:border-red-500' : 'border-gray-700 focus:ring-yellow-500 focus:border-yellow-500'; ?> block w-full pl-10 pr-3 py-3 rounded-md text-white" placeholder="John Doe">
              </div>
              <?php if (!empty($fullNameErr)): ?>
                <p class="mt-1 text-xs text-red-500"><?php echo $fullNameErr; ?></p>
              <?php endif; ?>
            </div>

            <!-- Email -->
            <div>
              <label for="email" class="block text-sm font-medium text-gray-300">Email address</label>
              <div class="mt-1 relative rounded-md shadow-sm">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <i class="fas fa-envelope text-gray-500"></i>
                </div>
                <input type="email" id="email" name="email" value="<?php echo $email; ?>" class="bg-gray-800 <?php echo !empty($emailErr) ? 'border-red-500 focus:ring-red-500 focus:border-red-500' : 'border-gray-700 focus:ring-yellow-500 focus:border-yellow-500'; ?> block w-full pl-10 pr-3 py-3 rounded-md text-white" placeholder="example@email.com">
              </div>
              <?php if (!empty($emailErr)): ?>
                <p class="mt-1 text-xs text-red-500"><?php echo $emailErr; ?></p>
              <?php endif; ?>
            </div>

            <!-- Phone Number -->
            <div>
              <label for="phone" class="block text-sm font-medium text-gray-300">Phone Number</label>
              <div class="mt-1 relative rounded-md shadow-sm">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <i class="fas fa-phone text-gray-500"></i>
                </div>
                <input type="tel" id="phone" name="phone" value="<?php echo $phone; ?>" class="bg-gray-800 <?php echo !empty($phoneErr) ? 'border-red-500 focus:ring-red-500 focus:border-red-500' : 'border-gray-700 focus:ring-yellow-500 focus:border-yellow-500'; ?> block w-full pl-10 pr-3 py-3 rounded-md text-white" placeholder="+92 300 1234567">
              </div>
              <?php if (!empty($phoneErr)): ?>
                <p class="mt-1 text-xs text-red-500"><?php echo $phoneErr; ?></p>
              <?php endif; ?>
            </div>

            <!-- Referral Code (Optional) -->
            <div>
              <label for="referralCode" class="block text-sm font-medium text-gray-300">Referral Code (Optional)</label>
              <div class="mt-1 relative rounded-md shadow-sm">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <i class="fas fa-users text-gray-500"></i>
                </div>
                <input type="text" id="referralCode" name="referralCode" value="<?php echo $referralCode; ?>" class="bg-gray-800 border-gray-700 focus:ring-yellow-500 focus:border-yellow-500 block w-full pl-10 pr-3 py-3 rounded-md text-white" placeholder="Enter referral code if you have one">
              </div>
              <p class="mt-1 text-xs text-gray-500">If someone referred you, enter their code here</p>
            </div>

            <!-- Password -->
            <div>
              <label for="password" class="block text-sm font-medium text-gray-300">Password</label>
              <div class="mt-1 relative rounded-md shadow-sm">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <i class="fas fa-lock text-gray-500"></i>
                </div>
                <input type="password" id="password" name="password" class="bg-gray-800 <?php echo !empty($passwordErr) ? 'border-red-500 focus:ring-red-500 focus:border-red-500' : 'border-gray-700 focus:ring-yellow-500 focus:border-yellow-500'; ?> block w-full pl-10 pr-3 py-3 rounded-md text-white" placeholder="••••••••">
                <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                  <button type="button" id="togglePassword" class="text-gray-500 hover:text-yellow-500 focus:outline-none">
                    <i class="fas fa-eye"></i>
                  </button>
                </div>
              </div>
              <?php if (!empty($passwordErr)): ?>
                <p class="mt-1 text-xs text-red-500"><?php echo $passwordErr; ?></p>
              <?php else: ?>
                <p class="mt-1 text-xs text-gray-500">Password must be at least 8 characters</p>
              <?php endif; ?>
            </div>

            <!-- Confirm Password -->
            <div>
              <label for="confirmPassword" class="block text-sm font-medium text-gray-300">Confirm Password</label>
              <div class="mt-1 relative rounded-md shadow-sm">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <i class="fas fa-lock text-gray-500"></i>
                </div>
                <input type="password" id="confirmPassword" name="confirmPassword" class="bg-gray-800 <?php echo !empty($confirmPasswordErr) ? 'border-red-500 focus:ring-red-500 focus:border-red-500' : 'border-gray-700 focus:ring-yellow-500 focus:border-yellow-500'; ?> block w-full pl-10 pr-3 py-3 rounded-md text-white" placeholder="••••••••">
              </div>
              <?php if (!empty($confirmPasswordErr)): ?>
                <p class="mt-1 text-xs text-red-500"><?php echo $confirmPasswordErr; ?></p>
              <?php endif; ?>
            </div>
          </div>

          <!-- Terms and Conditions -->
          <div class="flex items-start">
            <div class="flex items-center h-5">
              <input id="terms" name="terms" type="checkbox" class="h-4 w-4 text-yellow-500 focus:ring-yellow-500 border-gray-700 rounded <?php echo !empty($termsErr) ? 'border-red-500' : ''; ?>">
            </div>
            <div class="ml-3 text-sm">
              <label for="terms" class="text-gray-400">
                I agree to the <a href="#" class="text-yellow-500 hover:text-yellow-400">Terms of Service</a> and <a href="#" class="text-yellow-500 hover:text-yellow-400">Privacy Policy</a>
              </label>
              <?php if (!empty($termsErr)): ?>
                <p class="mt-1 text-xs text-red-500"><?php echo $termsErr; ?></p>
              <?php endif; ?>
            </div>
          </div>

          <div>
            <button type="submit" class="w-full bg-gradient-to-r from-yellow-500 to-yellow-600 hover:from-yellow-600 hover:to-yellow-700 text-black font-bold py-3 px-4 rounded-lg transition duration-300 shadow-lg">
              Create Account
            </button>
          </div>
        </form>

        <div class="mt-6">
          <div class="relative">
            <div class="absolute inset-0 flex items-center">
              <div class="w-full border-t border-gray-700"></div>
            </div>
            <div class="relative flex justify-center text-sm">
              <span class="px-2 bg-gray-900 text-gray-400">
                Already have an account?
              </span>
            </div>
          </div>

          <div class="mt-6">
            <a href="login.php" class="w-full flex justify-center py-3 px-4 border border-yellow-500 rounded-lg shadow-sm text-sm font-medium text-yellow-500 hover:bg-gray-800 transition duration-300">
              Sign in to your account
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Footer -->
  <footer class="bg-black border-t border-gray-800 py-8">
    <div class="container mx-auto px-6">
      <div class="flex flex-col md:flex-row justify-between items-center">
        <div class="flex items-center mb-6 md:mb-0">
          <i class="fas fa-gem text-yellow-500 text-xl mr-2"></i>
          <span class="text-xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-yellow-400 via-yellow-500 to-yellow-600">AutoProftX</span>
        </div>
        <div class="flex space-x-4">
          <a href="#" class="text-gray-400 hover:text-yellow-500 transition duration-300"><i class="fab fa-facebook-f"></i></a>
          <a href="#" class="text-gray-400 hover:text-yellow-500 transition duration-300"><i class="fab fa-twitter"></i></a>
          <a href="#" class="text-gray-400 hover:text-yellow-500 transition duration-300"><i class="fab fa-instagram"></i></a>
        </div>
      </div>
      <div class="text-center text-gray-600 text-sm mt-6">
        &copy; 2025 AutoProftX. All rights reserved.
      </div>
    </div>
  </footer>

  <script>
    // Mobile menu functionality
    $(document).ready(function() {
      // Toggle mobile menu
      $('#mobile-menu-button').on('click', function() {
        $('#mobile-menu').removeClass('-translate-y-full').addClass('translate-y-0');
      });

      // Close mobile menu
      $('#close-menu-button').on('click', function() {
        $('#mobile-menu').removeClass('translate-y-0').addClass('-translate-y-full');
      });

      // Close menu when clicking a link
      $('.mobile-menu-link').on('click', function() {
        setTimeout(function() {
          $('#mobile-menu').removeClass('translate-y-0').addClass('-translate-y-full');
        }, 300);
      });

      // Toggle password visibility
      $('#togglePassword').on('click', function() {
        const passwordField = document.getElementById('password');
        const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordField.setAttribute('type', type);

        // Toggle eye icon
        $(this).find('i').toggleClass('fa-eye fa-eye-slash');
      });
    });
  </script>
</body>

</html>