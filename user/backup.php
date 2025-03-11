<?php
// backup_codes.php - Page for users to view and manage their backup codes
session_start();
include '../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
  header("Location: ../login.php");
  exit();
}

$userId = $_SESSION['user_id'];
$backupCodes = [];
$successMessage = "";
$errorMessage = "";

// Function to generate a backup code
function generateBackupCode($length = 10)
{
  $characters = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
  $code = '';

  for ($i = 0; $i < $length; $i++) {
    $code .= $characters[rand(0, strlen($characters) - 1)];
  }

  // Format the code as XXXX-XXXX-XX for better readability
  $formatted = substr($code, 0, 4) . '-' . substr($code, 4, 4) . '-' . substr($code, 8, 2);

  return $formatted;
}

// Function to generate new backup codes
function generateNewBackupCodes($userId, $conn, $codeCount = 10)
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

// Handle regenerate backup codes request
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['regenerate_codes'])) {
  // Check the user's password for security
  $password = $_POST['password'];

  // Get the user's stored password
  $checkPasswordSql = "SELECT password FROM users WHERE id = ?";
  $checkStmt = $conn->prepare($checkPasswordSql);
  $checkStmt->bind_param("i", $userId);
  $checkStmt->execute();
  $result = $checkStmt->get_result();

  if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();

    // Verify password
    if (password_verify($password, $user['password'])) {
      // Password is correct, generate new codes
      $backupCodes = generateNewBackupCodes($userId, $conn);
      $successMessage = "New backup codes have been generated successfully.";
    } else {
      $errorMessage = "Incorrect password. Please try again.";
    }
  } else {
    $errorMessage = "User account not found.";
  }

  $checkStmt->close();
}

// Get user's active backup codes
$getCodesSql = "SELECT code FROM backup_codes WHERE user_id = ? AND is_used = 0 ORDER BY created_at DESC";
$codesStmt = $conn->prepare($getCodesSql);
$codesStmt->bind_param("i", $userId);
$codesStmt->execute();
$codesResult = $codesStmt->get_result();

while ($row = $codesResult->fetch_assoc()) {
  $backupCodes[] = $row['code'];
}

$codesStmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <?php include 'includes/head.php'; ?>
  <title>Backup Codes - AutoProftX</title>
</head>

<body class="bg-black text-white">
  <?php include 'includes/navbar.php'; ?>
  <?php include 'includes/mobile-bar.php'; ?>


  <div class="min-h-screen p-4">
    <div class="container mx-auto  py-8">
      <!-- Header -->
      <div class="mb-8">
        <h1 class="text-3xl font-bold">Account Security</h1>
        <p class="text-gray-400 mt-1">Manage your backup codes for account recovery</p>
      </div>

      <?php if (!empty($successMessage)): ?>
        <div class="mb-6 bg-green-900 bg-opacity-50 text-green-200 p-4 rounded-md flex items-start">
          <i class="fas fa-check-circle mt-1 mr-3"></i>
          <span><?php echo $successMessage; ?></span>
        </div>
      <?php endif; ?>

      <?php if (!empty($errorMessage)): ?>
        <div class="mb-6 bg-red-900 bg-opacity-50 text-red-200 p-4 rounded-md flex items-start">
          <i class="fas fa-exclamation-circle mt-1 mr-3"></i>
          <span><?php echo $errorMessage; ?></span>
        </div>
      <?php endif; ?>

      <!-- Backup Codes Section -->
      <div class="bg-gradient-to-b from-gray-900 to-black rounded-xl p-6 shadow-xl border border-gray-800">
        <div class="flex items-center justify-between mb-6">
          <h2 class="text-xl font-semibold text-yellow-500">Backup Codes</h2>

          <button id="regenerateButton" class="px-4 py-2 bg-yellow-600 text-black rounded-lg hover:bg-yellow-500 transition-colors font-medium text-sm">
            <i class="fas fa-sync-alt mr-2"></i> Generate New Codes
          </button>
        </div>

        <?php if (count($backupCodes) > 0): ?>
          <div class="mb-4">
            <p class="text-gray-300 mb-4">
              These backup codes can be used to recover your account if you forget your password. Each code can only be used once.
            </p>

            <div class="bg-gray-800 p-4 rounded-md mb-6">
              <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 gap-3">
                <?php foreach ($backupCodes as $code): ?>
                  <div class="bg-gray-700 p-2 rounded text-center font-mono text-yellow-400"><?php echo htmlspecialchars($code); ?></div>
                <?php endforeach; ?>
              </div>
            </div>

            <div class="bg-blue-900 bg-opacity-50 text-blue-200 p-4 rounded-md mb-6">
              <div class="flex items-start">
                <i class="fas fa-info-circle mt-1 mr-3 text-blue-300"></i>
                <span>Store these codes in a secure location like a password manager. If you lose access to your account, you can use one of these codes to reset your password.</span>
              </div>
            </div>

            <div class="flex space-x-4">
              <button id="printCodes" class="flex-1 bg-gray-700 text-gray-200 py-2 px-4 rounded-lg hover:bg-gray-600 transition-colors">
                <i class="fas fa-print mr-2"></i> Print
              </button>
              <button id="downloadCodes" class="flex-1 bg-gray-700 text-gray-200 py-2 px-4 rounded-lg hover:bg-gray-600 transition-colors">
                <i class="fas fa-download mr-2"></i> Download
              </button>
            </div>
          </div>
        <?php else: ?>
          <div class="bg-gray-800 p-6 rounded-md mb-4 text-center">
            <i class="fas fa-key text-yellow-500 text-3xl mb-3"></i>
            <p class="text-gray-300">You don't have any active backup codes.</p>
            <p class="text-gray-400 text-sm mt-2">Generate new codes to protect your account.</p>
          </div>
        <?php endif; ?>

        <!-- Security Warning -->
        <div class="mt-6 border-t border-gray-700 pt-6">
          <h3 class="text-lg font-medium mb-2">Important Security Information</h3>
          <ul class="text-gray-400 text-sm space-y-2">
            <li class="flex items-start">
              <i class="fas fa-shield-alt text-yellow-500 mt-1 mr-2"></i>
              <span>Each backup code can only be used once for account recovery.</span>
            </li>
            <li class="flex items-start">
              <i class="fas fa-exclamation-triangle text-yellow-500 mt-1 mr-2"></i>
              <span>Generating new codes will invalidate all your previous backup codes.</span>
            </li>
            <li class="flex items-start">
              <i class="fas fa-lock text-yellow-500 mt-1 mr-2"></i>
              <span>Store these codes securely, separate from your password.</span>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </div>

  <!-- Regenerate Codes Modal -->
  <div id="regenerateModal" class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50 hidden">
    <div class="bg-gray-900 rounded-lg p-8 max-w-md w-full mx-4 border border-gray-800 shadow-2xl">
      <h3 class="text-xl font-bold text-white mb-4">Generate New Backup Codes</h3>

      <p class="text-gray-300 mb-4">
        This will invalidate all your existing backup codes. Please enter your password to confirm.
      </p>

      <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="space-y-4">
        <div>
          <label for="password" class="block text-sm font-medium text-gray-300 mb-1">Password</label>
          <div class="relative rounded-md shadow-sm">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
              <i class="fas fa-lock text-gray-500"></i>
            </div>
            <input type="password" id="modal-password" name="password" class="bg-gray-800 border-gray-700 focus:ring-yellow-500 focus:border-yellow-500 block w-full pl-10 pr-10 py-3 rounded-md text-white" placeholder="Enter your password" required>
            <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
              <button type="button" id="toggleModalPassword" class="text-gray-500 hover:text-yellow-500 focus:outline-none">
                <i class="fas fa-eye"></i>
              </button>
            </div>
          </div>
        </div>

        <div class="flex space-x-4 pt-2">
          <button type="button" id="cancelRegenerate" class="flex-1 bg-gray-700 text-gray-200 py-2 px-4 rounded-lg hover:bg-gray-600 transition-colors">
            Cancel
          </button>
          <button type="submit" name="regenerate_codes" class="flex-1 bg-yellow-600 text-black py-2 px-4 rounded-lg hover:bg-yellow-500 transition-colors font-medium">
            Generate New Codes
          </button>
        </div>
      </form>
    </div>
  </div>

  <?php include '../includes/footer.php'; ?>
  <?php include '../includes/js-links.php'; ?>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Regenerate codes modal controls
      const regenerateButton = document.getElementById('regenerateButton');
      const regenerateModal = document.getElementById('regenerateModal');
      const cancelRegenerate = document.getElementById('cancelRegenerate');

      regenerateButton.addEventListener('click', function() {
        regenerateModal.classList.remove('hidden');
      });

      cancelRegenerate.addEventListener('click', function() {
        regenerateModal.classList.add('hidden');
      });

      // Toggle password visibility
      const toggleModalPassword = document.getElementById('toggleModalPassword');
      const modalPassword = document.getElementById('modal-password');

      if (toggleModalPassword) {
        toggleModalPassword.addEventListener('click', function() {
          const type = modalPassword.getAttribute('type') === 'password' ? 'text' : 'password';
          modalPassword.setAttribute('type', type);
          this.querySelector('i').classList.toggle('fa-eye');
          this.querySelector('i').classList.toggle('fa-eye-slash');
        });
      }

      // Print functionality
      document.getElementById('printCodes').addEventListener('click', function() {
        const codes = <?php echo json_encode($backupCodes); ?>;
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

      // Download functionality
      document.getElementById('downloadCodes').addEventListener('click', function() {
        const codes = <?php echo json_encode($backupCodes); ?>;
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
    });
  </script>

</body>

</html>