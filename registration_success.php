<?php
// registration_success.php
session_start();

// Redirect if not coming from registration
if (!isset($_SESSION['user_id']) || !isset($_SESSION['new_backup_codes'])) {
  header("Location: login.php");
  exit();
}

$fullName = $_SESSION['full_name'] ?? 'User';
$backupCodes = $_SESSION['new_backup_codes'];
?>
<!doctype html>
<html lang="en">

<head>
  <?php include 'includes/head.php'; ?>
</head>

<body class="bg-black text-white font-sans">
  <?php include 'includes/navbar.php'; ?>

  <!-- Registration Success Content -->
  <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
      <div class="text-center">
        <div class="mx-auto h-20 w-20 rounded-full bg-yellow-500 flex items-center justify-center">
          <i class="fas fa-check text-black text-4xl"></i>
        </div>
        <h2 class="mt-6 text-3xl font-extrabold text-white">
          Registration Successful!
        </h2>
        <p class="mt-2 text-sm text-gray-400">
          Welcome, <?php echo htmlspecialchars($fullName); ?>! Your account has been created successfully.
        </p>
      </div>

      <div class="mt-8 bg-gradient-to-b from-gray-900 to-black rounded-xl p-8 shadow-2xl border border-gray-800">
        <div class="mb-6">
          <h3 class="text-xl font-semibold text-yellow-500 mb-4">Important: Save Your Backup Codes</h3>

          <p class="text-gray-300 mb-4">
            We've generated 10 backup codes for your account. These are essential for account recovery if you forget your password.
          </p>

          <div class="bg-gray-800 p-4 rounded-md mb-4">
            <div class="grid grid-cols-2 gap-3">
              <?php foreach ($backupCodes as $code): ?>
                <div class="bg-gray-700 p-2 rounded text-center font-mono text-yellow-400"><?php echo htmlspecialchars($code); ?></div>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="bg-blue-900 bg-opacity-50 text-blue-200 p-4 rounded-md flex items-start mb-4">
            <i class="fas fa-info-circle mt-1 mr-3 text-blue-300"></i>
            <span>Each code can only be used once. Store these codes in a secure location. You will not be able to see these codes again unless you request new ones.</span>
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

        <div class="mt-6">
          <a href="user/index.php" class="w-full flex justify-center py-3 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-black bg-yellow-500 hover:bg-yellow-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500">
            Continue to Dashboard
          </a>
        </div>
      </div>
    </div>
  </div>

  <?php include 'includes/footer.php'; ?>
  <?php include 'includes/js-links.php'; ?>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Print functionality
      document.getElementById('printCodes').addEventListener('click', function() {
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
          <html>
          <head>
            <title>Backup Codes - AutoProftX</title>
            <style>
              body { font-family: Arial, sans-serif; }
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
              <?php foreach ($backupCodes as $code): ?>
              <div class="code"><?php echo htmlspecialchars($code); ?></div>
              <?php endforeach; ?>
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