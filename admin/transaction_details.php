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

// Get transaction ID from query string
$transactionId = isset($_GET['id']) ? intval($_GET['id']) : null;

if (!$transactionId) {
  header("Location: admin_dashboard.php");
  exit();
}

// Messages
$message = '';
$error = '';

/**
 * Get transaction details
 * 
 * @param int $transactionId Transaction ID
 * @return array Transaction data
 */
function getTransactionDetails($transactionId)
{
  $conn = getConnection();

  try {
    $stmt = $conn->prepare("
      SELECT t.*, u.full_name, u.email, u.id as user_id, w.balance
      FROM transactions t
      JOIN users u ON t.user_id = u.id
      LEFT JOIN wallets w ON u.id = w.user_id
      WHERE t.id = ?
    ");
    $stmt->bind_param("i", $transactionId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
      return ['error' => 'Transaction not found'];
    }

    return $result->fetch_assoc();
  } catch (Exception $e) {
    return ['error' => $e->getMessage()];
  } finally {
    $conn->close();
  }
}

/**
 * Get transaction history for user
 * 
 * @param int $userId User ID
 * @param int $limit Number of transactions to retrieve
 * @return array Transaction history
 */
function getRecentTransactions($userId, $limit = 5)
{
  $conn = getConnection();

  try {
    $stmt = $conn->prepare("
      SELECT id, transaction_type, amount, status, created_at, description
      FROM transactions
      WHERE user_id = ?
      ORDER BY created_at DESC
      LIMIT ?
    ");
    $stmt->bind_param("ii", $userId, $limit);
    $stmt->execute();
    $result = $stmt->get_result();

    $transactions = [];
    while ($row = $result->fetch_assoc()) {
      $transactions[] = $row;
    }

    return $transactions;
  } catch (Exception $e) {
    return ['error' => $e->getMessage()];
  } finally {
    $conn->close();
  }
}

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

// Process transaction status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  $conn = getConnection();

  try {
    $conn->begin_transaction();

    // Get current transaction data
    $stmt = $conn->prepare("SELECT * FROM transactions WHERE id = ?");
    $stmt->bind_param("i", $transactionId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
      throw new Exception("Transaction not found");
    }

    $transaction = $result->fetch_assoc();
    $userId = $transaction['user_id'];
    $amount = $transaction['amount'];
    $oldStatus = $transaction['status'];
    $newStatus = $_POST['status'];
    $adminNote = trim($_POST['admin_note'] ?? '');

    // Only allow status change if current status is not completed or failed
    if ($oldStatus === 'completed' || $oldStatus === 'failed') {
      throw new Exception("Cannot change status of a transaction that is already {$oldStatus}");
    }

    // Update transaction status
    $stmt = $conn->prepare("
      UPDATE transactions 
      SET status = ?, admin_note = ?, updated_at = NOW(), admin_id = ?
      WHERE id = ?
    ");
    $adminId = $_SESSION['admin_id'];
    $stmt->bind_param("ssii", $newStatus, $adminNote, $adminId, $transactionId);
    $stmt->execute();

    // If status changed to completed, update user wallet balance
    if ($newStatus === 'completed' && $oldStatus !== 'completed') {
      // Check if the transaction affects the wallet balance
      if (in_array($transaction['transaction_type'], ['deposit', 'commission', 'bonus', 'refund'])) {
        // Increase balance
        $stmt = $conn->prepare("
          UPDATE wallets 
          SET balance = balance + ?, updated_at = NOW()
          WHERE user_id = ?
        ");
        $stmt->bind_param("di", $amount, $userId);
        $stmt->execute();

        if ($stmt->affected_rows === 0) {
          // Create wallet if it doesn't exist
          $stmt = $conn->prepare("INSERT INTO wallets (user_id, balance, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
          $stmt->bind_param("id", $userId, $amount);
          $stmt->execute();
        }
      } elseif ($transaction['transaction_type'] === 'withdrawal') {
        // Decrease balance
        $stmt = $conn->prepare("
          UPDATE wallets 
          SET balance = balance - ?, updated_at = NOW()
          WHERE user_id = ? AND balance >= ?
        ");
        $stmt->bind_param("did", $amount, $userId, $amount);
        $stmt->execute();

        if ($stmt->affected_rows === 0) {
          throw new Exception("Insufficient balance for withdrawal");
        }
      }
    }

    // If status changed from completed, revert the wallet update
    if ($oldStatus === 'completed' && $newStatus !== 'completed') {
      if (in_array($transaction['transaction_type'], ['deposit', 'commission', 'bonus', 'refund'])) {
        // Decrease balance
        $stmt = $conn->prepare("
          UPDATE wallets 
          SET balance = balance - ?, updated_at = NOW()
          WHERE user_id = ? AND balance >= ?
        ");
        $stmt->bind_param("did", $amount, $userId, $amount);
        $stmt->execute();

        if ($stmt->affected_rows === 0) {
          throw new Exception("Cannot revert transaction: insufficient balance");
        }
      } elseif ($transaction['transaction_type'] === 'withdrawal') {
        // Increase balance
        $stmt = $conn->prepare("
          UPDATE wallets 
          SET balance = balance + ?, updated_at = NOW()
          WHERE user_id = ?
        ");
        $stmt->bind_param("di", $amount, $userId);
        $stmt->execute();
      }
    }

    // Create log entry
    $adminName = $_SESSION['admin_name'] ?? 'Admin';
    $logStmt = $conn->prepare("
      INSERT INTO admin_logs (admin_id, action, description, user_id, related_id) 
      VALUES (?, 'transaction_update', ?, ?, ?)
    ");
    $description = "Transaction #{$transactionId} status updated from {$oldStatus} to {$newStatus} by {$adminName}";
    $logStmt->bind_param("isii", $adminId, $description, $userId, $transactionId);
    $logStmt->execute();

    $conn->commit();
    $message = "Transaction status updated successfully.";
  } catch (Exception $e) {
    $conn->rollback();
    $error = "Error updating transaction: " . $e->getMessage();
  } finally {
    $conn->close();
  }
}

// Get transaction data
$transactionData = getTransactionDetails($transactionId);

// Get recent transactions for the user if transaction is found
$recentTransactions = [];
if (!isset($transactionData['error']) && isset($transactionData['user_id'])) {
  $recentTransactions = getRecentTransactions($transactionData['user_id']);
}

$unreadCount = getUnreadMessagesCount();
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Transaction Details - Admin Panel</title>
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
    <div class="w-64 bg-gray-800 text-white">
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
          <h1 class="text-2xl font-bold text-gray-900">Transaction Details</h1>

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

        <?php if (isset($transactionData['error'])): ?>
          <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative">
            <span class="block sm:inline">Error: <?php echo $transactionData['error']; ?></span>
            <div class="mt-2">
              <a href="admin_dashboard.php?tab=transactions" class="bg-red-500 hover:bg-red-600 text-white py-2 px-4 rounded">
                Back to Transactions
              </a>
            </div>
          </div>
        <?php else: ?>

          <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Transaction Details Card -->
            <div class="lg:col-span-2">
              <div class="bg-white shadow rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                  <h2 class="text-xl font-semibold text-gray-800">Transaction #<?php echo $transactionId; ?></h2>

                  <div>
                    <?php
                    $statusClasses = [
                      'completed' => 'bg-green-100 text-green-800',
                      'pending' => 'bg-yellow-100 text-yellow-800',
                      'failed' => 'bg-red-100 text-red-800'
                    ];
                    $statusClass = $statusClasses[$transactionData['status']] ?? 'bg-gray-100 text-gray-800';
                    ?>
                    <span class="px-3 py-1 inline-flex text-sm rounded-full <?php echo $statusClass; ?>">
                      <?php echo ucfirst($transactionData['status']); ?>
                    </span>
                  </div>
                </div>

                <div class="p-6">
                  <div class="mb-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                      <div>
                        <p class="text-sm text-gray-500">Transaction Type</p>
                        <?php
                        $typeClasses = [
                          'deposit' => 'text-green-600',
                          'withdrawal' => 'text-red-600',
                          'commission' => 'text-purple-600',
                          'bonus' => 'text-blue-600',
                          'refund' => 'text-yellow-600'
                        ];
                        $typeClass = $typeClasses[$transactionData['transaction_type']] ?? 'text-gray-600';
                        ?>
                        <p class="text-lg font-semibold <?php echo $typeClass; ?>">
                          <?php echo ucfirst($transactionData['transaction_type']); ?>
                        </p>
                      </div>

                      <div>
                        <p class="text-sm text-gray-500">Amount</p>
                        <p class="text-lg font-semibold <?php echo $typeClass; ?>">
                          <?php
                          if (in_array($transactionData['transaction_type'], ['deposit', 'commission', 'bonus', 'refund'])) {
                            echo '+$' . number_format($transactionData['amount'], 2);
                          } else {
                            echo '-$' . number_format($transactionData['amount'], 2);
                          }
                          ?>
                        </p>
                      </div>

                      <div>
                        <p class="text-sm text-gray-500">Transaction Date</p>
                        <p class="text-base">
                          <?php echo date('M d, Y h:i A', strtotime($transactionData['created_at'])); ?>
                        </p>
                      </div>

                      <div>
                        <p class="text-sm text-gray-500">Last Updated</p>
                        <p class="text-base">
                          <?php echo date('M d, Y h:i A', strtotime($transactionData['updated_at'] ?? $transactionData['created_at'])); ?>
                        </p>
                      </div>

                      <div class="md:col-span-2">
                        <p class="text-sm text-gray-500">Description</p>
                        <p class="text-base">
                          <?php echo htmlspecialchars($transactionData['description']); ?>
                        </p>
                      </div>

                      <?php if ($transactionData['reference_id']): ?>
                        <div>
                          <p class="text-sm text-gray-500">Reference ID</p>
                          <p class="text-base font-mono">
                            <?php echo htmlspecialchars($transactionData['reference_id']); ?>
                          </p>
                        </div>
                      <?php endif; ?>

                      <?php if (!empty($transactionData['admin_note'])): ?>
                        <div class="md:col-span-2">
                          <p class="text-sm text-gray-500">Admin Note</p>
                          <p class="text-base bg-gray-50 p-2 rounded">
                            <?php echo nl2br(htmlspecialchars($transactionData['admin_note'])); ?>
                          </p>
                        </div>
                      <?php endif; ?>
                    </div>
                  </div>

                  <!-- Administrative Actions -->
                  <?php if ($transactionData['status'] === 'pending'): ?>
                    <form method="POST" action="" class="mt-6 p-4 bg-gray-50 rounded-lg">
                      <h3 class="text-lg font-medium text-gray-900 mb-3">Update Transaction Status</h3>

                      <div class="mb-4">
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-1">New Status</label>
                        <select name="status" id="status" required
                          class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring focus:ring-primary-200 focus:ring-opacity-50">
                          <option value="completed">Completed</option>
                          <option value="failed">Failed</option>
                          <option value="pending" selected>Pending (No Change)</option>
                        </select>
                      </div>

                      <div class="mb-4">
                        <label for="admin_note" class="block text-sm font-medium text-gray-700 mb-1">Admin Note</label>
                        <textarea name="admin_note" id="admin_note" rows="3"
                          class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring focus:ring-primary-200 focus:ring-opacity-50"
                          placeholder="Add a note about this status update"><?php echo htmlspecialchars($transactionData['admin_note'] ?? ''); ?></textarea>
                      </div>

                      <div class="flex justify-end space-x-3">
                        <input type="hidden" name="action" value="update_status">
                        <button type="submit" class="px-4 py-2 border border-transparent rounded-md shadow-sm text-white bg-primary-600 hover:bg-primary-700">
                          Update Status
                        </button>
                      </div>
                    </form>
                  <?php endif; ?>
                </div>

                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
                  <div class="flex justify-between items-center">
                    <a href="user_transactions.php?id=<?php echo $transactionData['user_id']; ?>" class="text-primary-600 hover:text-primary-800">
                      <i class="fas fa-arrow-left mr-1"></i> Back to User Transactions
                    </a>

                    <div class="flex space-x-2">
                      <a href="javascript:window.print()" class="inline-flex items-center px-3 py-1 border border-gray-300 rounded text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        <i class="fas fa-print mr-1"></i> Print
                      </a>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- User Information Sidebar -->
            <div class="lg:col-span-1">
              <div class="bg-white shadow rounded-lg overflow-hidden mb-6">
                <div class="px-6 py-4 border-b border-gray-200">
                  <h3 class="text-lg font-semibold text-gray-800">User Information</h3>
                </div>

                <div class="p-6">
                  <div class="flex items-center mb-4">
                    <div class="flex-shrink-0 h-10 w-10 bg-primary-100 rounded-full flex items-center justify-center">
                      <span class="text-primary-700 font-bold"><?php echo substr($transactionData['full_name'], 0, 1); ?></span>
                    </div>
                    <div class="ml-4">
                      <h4 class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($transactionData['full_name']); ?></h4>
                      <p class="text-sm text-gray-600"><?php echo htmlspecialchars($transactionData['email']); ?></p>
                    </div>
                  </div>

                  <div class="mt-4 space-y-2">
                    <div>
                      <p class="text-sm text-gray-500">User ID</p>
                      <p class="text-base"><?php echo $transactionData['user_id']; ?></p>
                    </div>

                    <div>
                      <p class="text-sm text-gray-500">Current Balance</p>
                      <p class="text-base font-semibold"><?php echo '$' . number_format($transactionData['balance'], 2); ?></p>
                    </div>
                  </div>

                  <div class="mt-6 flex flex-col space-y-2">
                    <a href="user_edit.php?id=<?php echo $transactionData['user_id']; ?>" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                      <i class="fas fa-edit mr-2"></i> Edit User
                    </a>
                    <a href="user_transactions.php?id=<?php echo $transactionData['user_id']; ?>" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                      <i class="fas fa-exchange-alt mr-2"></i> View All Transactions
                    </a>
                    <a href="user_tree.php?id=<?php echo $transactionData['user_id']; ?>" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                      <i class="fas fa-sitemap mr-2"></i> View Referral Tree
                    </a>
                  </div>
                </div>
              </div>

              <!-- Recent Transactions -->
              <?php if (!empty($recentTransactions) && !isset($recentTransactions['error'])): ?>
                <div class="bg-white shadow rounded-lg overflow-hidden">
                  <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-800">Recent Transactions</h3>
                  </div>

                  <div class="p-0">
                    <ul class="divide-y divide-gray-200">
                      <?php foreach ($recentTransactions as $transaction): ?>
                        <li class="px-6 py-3 hover:bg-gray-50">
                          <a href="transaction_details.php?id=<?php echo $transaction['id']; ?>" class="block">
                            <?php
                            $typeClasses = [
                              'deposit' => 'text-green-600',
                              'withdrawal' => 'text-red-600',
                              'commission' => 'text-purple-600',
                              'bonus' => 'text-blue-600',
                              'refund' => 'text-yellow-600'
                            ];
                            $typeClass = $typeClasses[$transaction['transaction_type']] ?? 'text-gray-600';

                            $statusClasses = [
                              'completed' => 'bg-green-100 text-green-800',
                              'pending' => 'bg-yellow-100 text-yellow-800',
                              'failed' => 'bg-red-100 text-red-800'
                            ];
                            $statusClass = $statusClasses[$transaction['status']] ?? 'bg-gray-100 text-gray-800';
                            ?>
                            <div class="flex justify-between items-center">
                              <div>
                                <p class="text-sm font-medium <?php echo $typeClass; ?>">
                                  <?php echo ucfirst($transaction['transaction_type']); ?>
                                  <?php if ($transaction['id'] == $transactionId): ?>
                                    <span class="text-xs text-gray-500">(Current)</span>
                                  <?php endif; ?>
                                </p>
                                <p class="text-xs text-gray-500">
                                  <?php echo date('M d, Y', strtotime($transaction['created_at'])); ?>
                                </p>
                              </div>
                              <div class="flex items-center space-x-2">
                                <span class="text-sm font-medium <?php echo $typeClass; ?>">
                                  <?php
                                  if (in_array($transaction['transaction_type'], ['deposit', 'commission', 'bonus', 'refund'])) {
                                    echo '+$' . number_format($transaction['amount'], 2);
                                  } else {
                                    echo '-$' . number_format($transaction['amount'], 2);
                                  }
                                  ?></span>
                                <span class="px-2 py-0.5 text-xs rounded-full <?php echo $statusClass; ?>">
                                  <?php echo ucfirst($transaction['status']); ?>
                                </span>
                              </div>
                            </div>
                          </a>
                        </li>
                      <?php endforeach; ?>
                    </ul>

                    <div class="p-4 border-t border-gray-200">
                      <a href="user_transactions.php?id=<?php echo $transactionData['user_id']; ?>" class="text-primary-600 hover:text-primary-800 text-sm">
                        View all transactions <i class="fas fa-chevron-right ml-1"></i>
                      </a>
                    </div>
                  </div>
                </div>
              <?php endif; ?>
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
    // JavaScript for additional functionality
    document.addEventListener('DOMContentLoaded', function() {
      // If status dropdown changes to 'completed', show confirmation for withdrawals
      const statusDropdown = document.getElementById('status');
      if (statusDropdown) {
        statusDropdown.addEventListener('change', function() {
          if (this.value === 'completed' && '<?php echo $transactionData['transaction_type']; ?>' === 'withdrawal') {
            const confirmed = confirm("You are about to approve a withdrawal. This will deduct funds from the user's account. Continue?");
            if (!confirmed) {
              this.value = 'pending';
            }
          }
        });
      }
    });
  </script>
</body>

</html>