<?php
session_start();
include 'config/db.php';

// Check if user is already logged in, redirect to dashboard if true
if (isset($_SESSION['user_id'])) {
  header("Location: user/index.php");
  exit();
}

// Function to verify backup code
function verifyBackupCode($code, $userId, $conn)
{
  // Clean up the code (remove dashes if user entered them)
  $cleanCode = str_replace('-', '', $code);

  // Reformat it to match database format
  $formattedCode = substr($cleanCode, 0, 4) . '-' . substr($cleanCode, 4, 4) . '-' . substr($cleanCode, 8, 2);

  // Check if code exists and is unused
  $sql = "SELECT id FROM backup_codes WHERE user_id = ? AND code = ? AND is_used = 0";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("is", $userId, $formattedCode);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows === 1) {
    $row = $result->fetch_assoc();
    $codeId = $row['id'];

    // Mark code as used
    $updateSql = "UPDATE backup_codes SET is_used = 1, used_at = NOW() WHERE id = ?";
    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->bind_param("i", $codeId);
    $updateStmt->execute();
    $updateStmt->close();

    $stmt->close();
    return true;
  }

  $stmt->close();
  return false;
}

// Function to generate new backup codes
function generateBackupCodesForUser($userId, $conn, $codeCount = 10)
{
  // First, invalidate existing codes
  $invalidateSql = "UPDATE backup_codes SET is_used = 1 WHERE user_id = ? AND is_used = 0";
  $invalidateStmt = $conn->prepare($invalidateSql);
  $invalidateStmt->bind_param("i", $userId);
  $invalidateStmt->execute();
  $invalidateStmt->close();

  // Generate new codes
  $codes = [];
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
$showEmailForm = false;
$showCodeForm = true;  // Start with this as true
$showResetForm = false;
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

// Initialize variables
$email = "";
$backupCode = "";
$emailErr = "";
$codeErr = "";
$password = "";
$confirmPassword = "";
$passwordErr = "";
$confirmPasswordErr = "";
$successMessage = "";
$errorMessage = "";
$showEmailForm = true;
$showCodeForm = false;
$showResetForm = false;
$userId = null;

// Process email verification
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['find_email'])) {
  // Get email from form
  $email = trim($_POST["email"]);

  // Validate email
  if (empty($email)) {
    $emailErr = "Email is required";
  } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $emailErr = "Invalid email format";
  }

  // If no validation errors, check if the email exists
  if (empty($emailErr)) {
    // Check if the email exists in the database
    $sql = "SELECT id, full_name FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
      // User found, show backup code form
      $user = $result->fetch_assoc();
      $userId = $user['id'];
      $showEmailForm = false;
      $showCodeForm = true;

      // Store userId in session for security
      $_SESSION['reset_user_id'] = $userId;
      $_SESSION['reset_email'] = $email;
    } else {
      // User not found
      $errorMessage = "No account found with this email address.";
    }

    $stmt->close();
  }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['verify_code'])) {
  // Get backup code from form
  $backupCode = trim($_POST["backup_code"]);
  $userId = $_SESSION['reset_user_id'] ?? 0;

  // Make sure we're maintaining the correct view state
  $showEmailForm = false;
  $showCodeForm = true;
  $showResetForm = false;

  // Validate code
  if (empty($backupCode)) {
    $codeErr = "Backup code is required";
  } else if (strlen(str_replace('-', '', $backupCode)) !== 10) {
    $codeErr = "Invalid code format. Please enter all 10 characters.";
  } else {
    // Clean up the code (remove dashes if user entered them)
    $cleanCode = str_replace('-', '', $backupCode);

    // Reformat it to match database format
    $formattedCode = substr($cleanCode, 0, 4) . '-' . substr($cleanCode, 4, 4) . '-' . substr($cleanCode, 8, 2);

    // Check if code exists and is unused
    $sql = "SELECT id FROM backup_codes WHERE user_id = ? AND code = ? AND is_used = 0";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $userId, $formattedCode);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
      $row = $result->fetch_assoc();
      $codeId = $row['id'];

      // Mark code as used
      $updateSql = "UPDATE backup_codes SET is_used = 1, used_at = NOW() WHERE id = ?";
      $updateStmt = $conn->prepare($updateSql);
      $updateStmt->bind_param("i", $codeId);
      $updateStmt->execute();
      $updateStmt->close();

      // Code is valid, show reset form
      $showCodeForm = false;
      $showResetForm = true;
    } else {
      // Invalid code
      $codeErr = "Invalid or already used backup code";
    }

    $stmt->close();
  }
}
// At the top of your file after starting the session
error_log("Session data: " . print_r($_SESSION, true));
error_log("POST data: " . print_r($_POST, true));

// After code verification
error_log("Code verification result: " . ($showResetForm ? "SUCCESS" : "FAILED"));
// Process password reset
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['reset_password'])) {
  $password = $_POST["password"];
  $confirmPassword = $_POST["confirm_password"];
  $userId = $_SESSION['reset_user_id'] ?? 0;
  $email = $_SESSION['reset_email'] ?? '';

  // Validate userId
  if ($userId <= 0) {
    $errorMessage = "Session expired. Please start the password reset process again.";
    $showEmailForm = true;
    $showResetForm = false;
  } else {
    // Validate password
    if (empty($password)) {
      $passwordErr = "Password is required";
      $showResetForm = true;
    } elseif (strlen($password) < 8) {
      $passwordErr = "Password must be at least 8 characters long";
      $showResetForm = true;
    }

    // Validate confirm password
    if (empty($confirmPassword)) {
      $confirmPasswordErr = "Please confirm your password";
      $showResetForm = true;
    } elseif ($password !== $confirmPassword) {
      $confirmPasswordErr = "Passwords do not match";
      $showResetForm = true;
    }

    // If no validation errors, update the password
    if (empty($passwordErr) && empty($confirmPasswordErr)) {
      // Hash the new password
      $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

      // Update the user's password
      $updateSql = "UPDATE users SET password = ? WHERE id = ?";
      $updateStmt = $conn->prepare($updateSql);
      $updateStmt->bind_param("si", $hashedPassword, $userId);

      if ($updateStmt->execute()) {
        // Generate new backup codes for the user
        $newCodes = generateBackupCodesForUser($userId, $conn);

        // Store new backup codes in session
        $_SESSION['new_backup_codes'] = $newCodes;

        $successMessage = "Your password has been reset successfully.";
        $showResetForm = false;
        $showNewCodesForm = true;

        // Clear the reset session variables, but keep the codes
        unset($_SESSION['reset_user_id']);
        unset($_SESSION['reset_email']);
      } else {
        $errorMessage = "An error occurred while resetting your password. Please try again later.";
        $showResetForm = true;
      }

      $updateStmt->close();
    }
  }
}

// Display new backup codes after reset
$showNewCodesForm = isset($_SESSION['new_backup_codes']) && !empty($_SESSION['new_backup_codes']) && !$showEmailForm && !$showCodeForm && !$showResetForm;
$newBackupCodes = $_SESSION['new_backup_codes'] ?? [];

$conn->close();
?>
<!doctype html>
<html lang="en">

<head>
  <?php include 'includes/head.php'; ?>
</head>

<body class="bg-black text-white font-sans">
  <!-- Navigation -->
  <?php include 'includes/navbar.php'; ?>

  <!-- Mobile Menu -->
  <div id="mobile-menu" class="fixed inset-0 bg-black bg-opacity-90 z-50 transform -translate-y-full transition-transform duration-300 ease-in-out">
    <!-- Mobile menu content -->
  </div>

  <!-- Forgot Password Form -->
  <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
      <div class="text-center">
        <h2 class="mt-6 text-3xl font-extrabold text-white">
          <?php if ($showNewCodesForm): ?>
            Your New Backup Codes
          <?php else: ?>
            Forgot your password?
          <?php endif; ?>
        </h2>
        <p class="mt-2 text-sm text-gray-400">
          <?php if ($showEmailForm): ?>
            Enter your email address to reset your password
          <?php elseif ($showCodeForm): ?>
            Enter one of your backup codes
          <?php elseif ($showResetForm): ?>
            Create a new password for your account
          <?php elseif ($showNewCodesForm): ?>
            Save these codes in a secure location
          <?php endif; ?>
        </p>
      </div>

      <div class="mt-8 bg-gradient-to-b from-gray-900 to-black rounded-xl p-8 shadow-2xl border border-gray-800">
        <?php if (!empty($successMessage)): ?>
          <div class="mb-4 bg-green-900 bg-opacity-50 text-green-200 p-4 rounded-md flex items-start">
            <i class="fas fa-check-circle mt-1 mr-3"></i>
            <span><?php echo $successMessage; ?></span>
          </div>
        <?php endif; ?>

        <?php if (!empty($errorMessage)): ?>
          <div class="mb-4 bg-red-900 bg-opacity-50 text-red-200 p-4 rounded-md flex items-start">
            <i class="fas fa-exclamation-circle mt-1 mr-3"></i>
            <span><?php echo $errorMessage; ?></span>
          </div>
        <?php endif; ?>

        <?php if ($showEmailForm): ?>
          <!-- Email Verification Form -->
          <form class="space-y-6" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
            <!-- Email -->
            <div>
              <label for="email" class="block text-sm font-medium text-gray-300">Email address</label>
              <div class="mt-1 relative rounded-md shadow-sm">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <i class="fas fa-envelope text-gray-500"></i>
                </div>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" class="bg-gray-800 <?php echo !empty($emailErr) ? 'border-red-500 focus:ring-red-500 focus:border-red-500' : 'border-gray-700 focus:ring-yellow-500 focus:border-yellow-500'; ?> block w-full pl-10 pr-3 py-3 rounded-md text-white" placeholder="example@email.com" required>
              </div>
              <?php if (!empty($emailErr)): ?>
                <p class="mt-1 text-xs text-red-500"><?php echo $emailErr; ?></p>
              <?php endif; ?>
            </div>

            <div>
              <button type="submit" name="find_email" class="w-full bg-gradient-to-r from-yellow-500 to-yellow-600 hover:from-yellow-600 hover:to-yellow-700 text-black font-bold py-3 px-4 rounded-lg transition duration-300 shadow-lg">
                Continue
              </button>
            </div>
          </form>
        <?php elseif ($showCodeForm): ?>
          <!-- Backup Code Form -->
          <form class="space-y-6" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
            <!-- Backup Code -->
            <div>
              <label for="backup_code" class="block text-sm font-medium text-gray-300">Backup Code</label>
              <div class="mt-1 relative rounded-md shadow-sm">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <i class="fas fa-key text-gray-500"></i>
                </div>
                <input type="text" id="backup_code" name="backup_code" value="<?php echo htmlspecialchars($backupCode); ?>" class="bg-gray-800 <?php echo !empty($codeErr) ? 'border-red-500 focus:ring-red-500 focus:border-red-500' : 'border-gray-700 focus:ring-yellow-500 focus:border-yellow-500'; ?> block w-full pl-10 pr-3 py-3 rounded-md text-white uppercase tracking-wider" placeholder="XXXX-XXXX-XX" required>
              </div>
              <?php if (!empty($codeErr)): ?>
                <p class="mt-1 text-xs text-red-500"><?php echo $codeErr; ?></p>
              <?php else: ?>
                <p class="mt-1 text-xs text-gray-400">Enter one of your backup codes (format: XXXX-XXXX-XX)</p>
              <?php endif; ?>
            </div>

            <div>
              <button type="submit" name="verify_code" class="w-full bg-gradient-to-r from-yellow-500 to-yellow-600 hover:from-yellow-600 hover:to-yellow-700 text-black font-bold py-3 px-4 rounded-lg transition duration-300 shadow-lg">
                Verify Code
              </button>
            </div>

            <div class="mt-4 text-center">
              <a href="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="text-yellow-500 hover:text-yellow-400 text-sm">
                <i class="fas fa-arrow-left mr-1"></i> Try a different email
              </a>
            </div>
          </form>
        <?php elseif ($showResetForm): ?>
          <!-- Password Reset Form -->
          <form class="space-y-6" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
            <!-- New Password -->
            <div>
              <label for="password" class="block text-sm font-medium text-gray-300">New Password</label>
              <div class="mt-1 relative rounded-md shadow-sm">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <i class="fas fa-lock text-gray-500"></i>
                </div>
                <input type="password" id="password" name="password" class="bg-gray-800 <?php echo !empty($passwordErr) ? 'border-red-500 focus:ring-red-500 focus:border-red-500' : 'border-gray-700 focus:ring-yellow-500 focus:border-yellow-500'; ?> block w-full pl-10 pr-10 py-3 rounded-md text-white" placeholder="Enter new password" required>
                <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                  <button type="button" id="togglePassword" class="text-gray-500 hover:text-yellow-500 focus:outline-none">
                    <i class="fas fa-eye"></i>
                  </button>
                </div>
              </div>
              <?php if (!empty($passwordErr)): ?>
                <p class="mt-1 text-xs text-red-500"><?php echo $passwordErr; ?></p>
              <?php else: ?>
                <p class="mt-1 text-xs text-gray-400">Must be at least 8 characters long</p>
              <?php endif; ?>
            </div>

            <!-- Confirm Password -->
            <div>
              <label for="confirm_password" class="block text-sm font-medium text-gray-300">Confirm Password</label>
              <div class="mt-1 relative rounded-md shadow-sm">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <i class="fas fa-lock text-gray-500"></i>
                </div>
                <input type="password" id="confirm_password" name="confirm_password" class="bg-gray-800 <?php echo !empty($confirmPasswordErr) ? 'border-red-500 focus:ring-red-500 focus:border-red-500' : 'border-gray-700 focus:ring-yellow-500 focus:border-yellow-500'; ?> block w-full pl-10 pr-10 py-3 rounded-md text-white" placeholder="Confirm new password" required>
                <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                  <button type="button" id="toggleConfirmPassword" class="text-gray-500 hover:text-yellow-500 focus:outline-none">
                    <i class="fas fa-eye"></i>
                  </button>
                </div>
              </div>
              <?php if (!empty($confirmPasswordErr)): ?>
                <p class="mt-1 text-xs text-red-500"><?php echo $confirmPasswordErr; ?></p>
              <?php endif; ?>
            </div>

            <div>
              <button type="submit" name="reset_password" class="w-full bg-gradient-to-r from-yellow-500 to-yellow-600 hover:from-yellow-600 hover:to-yellow-700 text-black font-bold py-3 px-4 rounded-lg transition duration-300 shadow-lg">
                Reset Password
              </button>
            </div>
          </form>
        <?php elseif ($showNewCodesForm): ?>
          <!-- Display New Backup Codes -->
          <div class="space-y-6">
            <div>
              <p class="text-gray-300 mb-4">
                Your password has been reset successfully. Here are your new backup codes:
              </p>

              <div class="bg-gray-800 p-4 rounded-md mb-4">
                <div class="grid grid-cols-2 gap-3">
                  <?php foreach ($newBackupCodes as $code): ?>
                    <div class="bg-gray-700 p-2 rounded text-center font-mono text-yellow-400"><?php echo htmlspecialchars($code); ?></div>
                  <?php endforeach; ?>
                </div>
              </div>

              <div class="bg-blue-900 bg-opacity-50 text-blue-200 p-4 rounded-md mb-4 flex items-start">
                <i class="fas fa-exclamation-triangle mt-1 mr-3"></i>
                <span>Your previous backup codes have been invalidated. Save these new codes in a secure location as they will not be shown again.</span>
              </div>

              <div class="flex space-x-4 mt-6">
                <button id="printCodes" class="flex-1 bg-gray-700 text-gray-200 py-2 px-4 rounded-lg hover:bg-gray-600 transition-colors">
                  <i class="fas fa-print mr-2"></i> Print
                </button>
                <button id="downloadCodes" class="flex-1 bg-gray-700 text-gray-200 py-2 px-4 rounded-lg hover:bg-gray-600 transition-colors">
                  <i class="fas fa-download mr-2"></i> Download
                </button>
              </div>
            </div>

            <div class="pt-4">
              <a href="login.php" class="w-full bg-gradient-to-r from-yellow-500 to-yellow-600 hover:from-yellow-600 hover:to-yellow-700 text-black font-bold py-3 px-4 rounded-lg transition duration-300 shadow-lg flex items-center justify-center">
                <i class="fas fa-sign-in-alt mr-2"></i> Go to Login
              </a>
            </div>
          </div>
          <?php
          // Clear backup codes from session after displaying
          unset($_SESSION['new_backup_codes']);
          ?>
        <?php endif; ?>

        <?php if (!$showNewCodesForm): ?>
          <div class="mt-6">
            <div class="relative">
              <div class="absolute inset-0 flex items-center">
                <div class="w-full border-t border-gray-700"></div>
              </div>
              <div class="relative flex justify-center text-sm">
                <span class="px-2 bg-gray-900 text-gray-400">
                  Or go back to
                </span>
              </div>
            </div>

            <div class="mt-6 text-center">
              <a href="login.php" class="text-yellow-500 hover:text-yellow-400">
                <i class="fas fa-arrow-left mr-1"></i> Back to login
              </a>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php include 'includes/footer.php'; ?>
  <?php include 'includes/js-links.php'; ?>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Success message auto-hide after 5 seconds
      const successMessage = document.querySelector('.bg-green-900');
      if (successMessage) {
        setTimeout(function() {
          successMessage.style.opacity = '0';
          successMessage.style.transition = 'opacity 1s';
          setTimeout(function() {
            successMessage.style.display = 'none';
          }, 1000);
        }, 5000);
      }

      // Toggle password visibility
      const togglePassword = document.getElementById('togglePassword');
      const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
      const password = document.getElementById('password');
      const confirmPassword = document.getElementById('confirm_password');

      if (togglePassword) {
        togglePassword.addEventListener('click', function() {
          const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
          password.setAttribute('type', type);
          this.querySelector('i').classList.toggle('fa-eye');
          this.querySelector('i').classList.toggle('fa-eye-slash');
        });
      }

      if (toggleConfirmPassword) {
        toggleConfirmPassword.addEventListener('click', function() {
          const type = confirmPassword.getAttribute('type') === 'password' ? 'text' : 'password';
          confirmPassword.setAttribute('type', type);
          this.querySelector('i').classList.toggle('fa-eye');
          this.querySelector('i').classList.toggle('fa-eye-slash');
        });
      }

      // Format backup code input with dashes
      const backupCodeInput = document.getElementById('backup_code');
      if (backupCodeInput) {
        backupCodeInput.addEventListener('input', function(e) {
          let code = e.target.value.replace(/[^0-9A-Z]/g, '').substring(0, 10);

          if (code.length > 8) {
            code = code.substring(0, 4) + '-' + code.substring(4, 8) + '-' + code.substring(8);
          } else if (code.length > 4) {
            code = code.substring(0, 4) + '-' + code.substring(4);
          }

          e.target.value = code.toUpperCase();
        });
      }

      // Print functionality for new backup codes
      const printButton = document.getElementById('printCodes');
      if (printButton) {
        printButton.addEventListener('click', function() {
          const codes = <?php echo json_encode($newBackupCodes); ?>;
          const printWindow = window.open('', '_blank');
          printWindow.document.write(`
            <html>
            <head>
              <title>Backup Codes - AutoProftX</title>
              <style>
                body { font-family: Arial, sans-serif; padding: 20px; }
                h1 { text-align: center; }
                .codes-container { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin: 20px 0; }
                .code { border: 1px solid #ccc; padding: 10px; text-align: center; font-family: monospace; }
                .info { margin-top: 20px; }
              </style>
            </head>
            <body>
              <h1>Your AutoProftX Backup Codes</h1>
              <p>Store these backup codes in a safe place. Each code can be used once to reset your password.</p>
              <div class="codes-container">
                ${codes.map(code => `<div class="code">${code}</div>`).join('')}
              </div>
              <div class="info">
                <p><strong>Important:</strong></p>
                <ul>
                  <li>Each code can only be used once</li>
                  <li>Store these codes securely</li>
                  <li>If you lose access to your account and don't have these codes, you may lose access permanently</li>
                </ul>
              </div>
            </body>
            </html>
          `);
          printWindow.document.close();
          printWindow.focus();
          printWindow.print();
          printWindow.close();
        });
      }

      // Download functionality for new backup codes
      const downloadButton = document.getElementById('downloadCodes');
      if (downloadButton) {
        downloadButton.addEventListener('click', function() {
          const codes = <?php echo json_encode($newBackupCodes); ?>;
          const codesText = "Your AutoProftX Backup Codes\n\n" +
            "Store these backup codes in a safe place. Each code can be used once to reset your password.\n\n" +
            codes.join("\n") +
            "\n\nImportant:\n" +
            "- Each code can only be used once\n" +
            "- Store these codes securely\n" +
            "- If you lose access to your account and don't have these codes, you may lose access permanently";

          const element = document.createElement('a');
          element.setAttribute('href', 'data:text/plain;charset=utf-8,' + encodeURIComponent(codesText));
          element.setAttribute('download', 'autoproftx-backup-codes.txt');
          element.style.display = 'none';
          document.body.appendChild(element);
          element.click();
          document.body.removeChild(element);
        });
      }
    });
  </script>
</body>

</html>