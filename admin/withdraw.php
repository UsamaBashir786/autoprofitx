<?php
session_start();
include '../config/db.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
  header("Location: admin-login.php");
  exit();
}

// Get admin info
$admin_id = $_SESSION['admin_id'];
$admin_name = $_SESSION['admin_name'] ?? 'Admin';

// Process withdrawal status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['withdrawal_id'], $_POST['action'])) {
  $withdrawal_id = $_POST['withdrawal_id'];
  $action = $_POST['action'];
  $admin_notes = $_POST['admin_notes'] ?? '';

  if ($action === 'approve' || $action === 'reject') {
    $status = ($action === 'approve') ? 'approved' : 'rejected';

    // Begin transaction
    $conn->begin_transaction();

    try {
      // Update withdrawal status
      $update_withdrawal = "UPDATE withdrawals SET 
                status = ?, 
                notes = ?, 
                processed_by = ?, 
                processed_at = NOW() 
                WHERE id = ?";

      $stmt = $conn->prepare($update_withdrawal);
      $stmt->bind_param("ssii", $status, $admin_notes, $_SESSION['admin_id'], $withdrawal_id);
      $stmt->execute();

      // Get withdrawal details
      $withdrawal_query = "SELECT * FROM withdrawals WHERE id = ?";
      $stmt = $conn->prepare($withdrawal_query);
      $stmt->bind_param("i", $withdrawal_id);
      $stmt->execute();
      $result = $stmt->get_result();
      $withdrawal = $result->fetch_assoc();

      // Update transaction status
      $transaction_query = "UPDATE transactions SET 
                status = ? 
                WHERE reference_id = ? AND transaction_type = 'withdrawal'";

      $transaction_status = ($status === 'approved') ? 'completed' : 'failed';
      $stmt = $conn->prepare($transaction_query);
      $stmt->bind_param("ss", $transaction_status, $withdrawal['withdrawal_id']);
      $stmt->execute();

      // If rejected, refund the amount to user's wallet
      if ($status === 'rejected') {
        $refund_wallet = "UPDATE wallets SET 
                    balance = balance + ? 
                    WHERE user_id = ?";

        $stmt = $conn->prepare($refund_wallet);
        $stmt->bind_param("di", $withdrawal['amount'], $withdrawal['user_id']);
        $stmt->execute();

        // Add refund transaction record
        $refund_transaction = "INSERT INTO transactions (
                    user_id, 
                    transaction_type, 
                    amount, 
                    status, 
                    description, 
                    reference_id
                ) VALUES (?, 'deposit', ?, 'completed', ?, ?)";

        $description = "Refund for rejected withdrawal " . $withdrawal['withdrawal_id'];
        $reference_id = "REFUND-" . $withdrawal['withdrawal_id'];

        $stmt = $conn->prepare($refund_transaction);
        $stmt->bind_param("idss", $withdrawal['user_id'], $withdrawal['amount'], $description, $reference_id);
        $stmt->execute();
      }

      // Commit transaction
      $conn->commit();

      // Set success message
      $_SESSION['admin_message'] = "Withdrawal has been " . ($status === 'approved' ? 'approved' : 'rejected') . " successfully.";
      $_SESSION['admin_message_type'] = "success";
    } catch (Exception $e) {
      // Rollback transaction on error
      $conn->rollback();

      // Set error message
      $_SESSION['admin_message'] = "Error processing withdrawal: " . $e->getMessage();
      $_SESSION['admin_message_type'] = "error";
    }

    // Redirect to refresh the page
    header("Location: withdraw.php");
    exit;
  }
}

// Get withdrawals with pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$status_clause = '';
if ($status_filter && in_array($status_filter, ['pending', 'approved', 'rejected'])) {
  $status_clause = "WHERE w.status = '$status_filter'";
}

// Count total records for pagination
$count_query = "SELECT COUNT(*) as total FROM withdrawals w $status_clause";
$result = $conn->query($count_query);
$row = $result->fetch_assoc();
$total_records = $row['total'] ?? 0;
$total_pages = ceil($total_records / $limit);

// Get withdrawals data
$query = "SELECT w.*, u.full_name as user_name, u.email as user_email, 
          a.name as admin_name
          FROM withdrawals w 
          LEFT JOIN users u ON w.user_id = u.id
          LEFT JOIN admin_users a ON w.processed_by = a.id
          $status_clause
          ORDER BY w.created_at DESC
          LIMIT $offset, $limit";

$result = $conn->query($query);
$withdrawals = [];

if ($result) {
  while ($row = $result->fetch_assoc()) {
    $withdrawals[] = $row;
  }
}

// Get withdrawal statistics
// Pending withdrawals
$pending_query = "SELECT COUNT(*) as count, SUM(amount) as total FROM withdrawals WHERE status = 'pending'";
$result = $conn->query($pending_query);
$pending = $result ? $result->fetch_assoc() : ['count' => 0, 'total' => 0];

// Approved withdrawals
$approved_query = "SELECT COUNT(*) as count, SUM(net_amount) as total FROM withdrawals WHERE status = 'approved'";
$result = $conn->query($approved_query);
$approved = $result ? $result->fetch_assoc() : ['count' => 0, 'total' => 0];

// Tax collected
$tax_query = "SELECT SUM(tax_amount) as total FROM withdrawals WHERE status = 'approved'";
$result = $conn->query($tax_query);
$tax = $result ? $result->fetch_assoc() : ['total' => 0];
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Withdrawals - Admin Dashboard</title>
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
      background: #f59e0b;
    }

    /* Active nav item */
    .nav-item.active {
      border-left: 3px solid #f59e0b;
      background-color: rgba(245, 158, 11, 0.1);
    }

    /* Card hover effect */
    .stat-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    }

    /* Gold gradient */
    .gold-gradient {
      background: linear-gradient(135deg, #f59e0b, #d97706);
    }
  </style>
</head>

<body class="bg-gray-900 text-gray-100 flex min-h-screen">
  <!-- Sidebar -->
  <?php include 'includes/sidebar.php'; ?>

  <!-- Main Content -->
  <div class="flex-1 flex flex-col overflow-hidden">
    <!-- Top Navigation Bar -->
    <header class="bg-gray-800 border-b border-gray-700 shadow-md">
      <div class="flex items-center justify-between p-4">
        <!-- Mobile Menu Toggle -->
        <button id="mobile-menu-button" class="md:hidden text-gray-300 hover:text-white">
          <i class="fas fa-bars text-xl"></i>
        </button>

        <h1 class="text-xl font-bold text-white md:hidden">AutoProftX</h1>

        <!-- User Profile -->
        <div class="flex items-center space-x-4">
          <div class="relative">
            <button id="notifications-btn" class="text-gray-300 hover:text-white relative">
              <i class="fas fa-bell text-xl"></i>
              <?php if ($pending['count'] > 0): ?>
                <span class="absolute -top-2 -right-2 h-5 w-5 rounded-full bg-red-500 flex items-center justify-center text-xs"><?php echo $pending['count']; ?></span>
              <?php endif; ?>
            </button>
          </div>

          <div class="flex items-center">
            <div class="h-8 w-8 rounded-full bg-yellow-500 flex items-center justify-center text-black font-bold">
              <?php echo strtoupper(substr($admin_name, 0, 1)); ?>
            </div>
            <span class="ml-2 hidden sm:inline-block"><?php echo htmlspecialchars($admin_name); ?></span>
          </div>
        </div>
      </div>
    </header>

    <!-- Mobile Sidebar -->
    <div id="mobile-sidebar" class="fixed inset-0 bg-gray-900 bg-opacity-90 z-50 md:hidden transform -translate-x-full transition-transform duration-300">
      <div class="flex flex-col h-full bg-gray-800 w-64 py-8 px-6">
        <div class="flex justify-between items-center mb-8">
          <div class="flex items-center">
            <i class="fas fa-gem text-yellow-500 text-xl mr-2"></i>
            <span class="text-xl font-bold text-yellow-500">AutoProftX</span>
          </div>
          <button id="close-sidebar" class="text-gray-300 hover:text-white">
            <i class="fas fa-times text-xl"></i>
          </button>
        </div>

        <!-- Navigation -->
        <nav class="space-y-2">
          <a href="index.php" class="nav-item flex items-center px-4 py-3 text-gray-300 hover:text-white rounded transition duration-200">
            <i class="fas fa-tachometer-alt w-6"></i>
            <span>Dashboard</span>
          </a>
          <a href="users.php" class="nav-item flex items-center px-4 py-3 text-gray-300 hover:text-white rounded transition duration-200">
            <i class="fas fa-users w-6"></i>
            <span>Users</span>
          </a>
          <a href="deposits.php" class="nav-item flex items-center px-4 py-3 text-gray-300 hover:text-white rounded transition duration-200">
            <i class="fas fa-money-bill-wave w-6"></i>
            <span>Deposits</span>
          </a>
          <a href="leaderboard.php" class="nav-item flex items-center px-4 py-3 text-gray-300 hover:text-white rounded transition duration-200">
            <i class="fa-solid fa-trophy w-6"></i>
            <span>leaderboard</span>
          </a>
          <a href="profit.php" class="nav-item active flex items-center px-4 py-3 text-gray-300 hover:text-white rounded transition duration-200">
            <i class="fas fa-percentage w-6"></i>
            <span>Profit Management</span>
          </a>
          <a href="plans.php" class="nav-item flex items-center px-4 py-3 text-gray-300 hover:text-white rounded transition duration-200">
            <i class="fa-solid fa-file-invoice w-6"></i>
            <span>Plans</span>
          </a>
          <a href="referrals.php" class="nav-item flex items-center px-4 py-3 text-gray-300 hover:text-white rounded transition duration-200">
            <i class="fa-solid fa-user-plus w-6"></i>
            <span>Referral</span>
          </a>
          <a href="ticket.php" class="nav-item flex items-center px-4 py-3 text-gray-300 hover:text-white rounded transition duration-200">
            <i class="fa-solid fa-headset w-6"></i>
            <span>Customer Support</span>
          </a>
          <a href="tree-referrals.php" class="nav-item flex items-center px-4 py-3 text-gray-300 hover:text-white rounded transition duration-200">
            <i class="fa-solid fa-headset w-6"></i>
            <span>Tree Referral</span>
          </a>
          <a href="withdraw.php" class="active nav-item flex items-center px-4 py-3 text-gray-300 hover:text-white rounded transition duration-200">
            <i class="fa-solid fa-headset w-6"></i>
            <span>Withdraw</span>
          </a>
          <a href="staking.php" class="nav-item flex items-center px-4 py-3 text-gray-300 hover:text-white rounded transition duration-200">
            <i class="fas fa-landmark w-6"></i>
            <span>Staking</span>
          </a>
          <a href="investments.php" class="nav-item flex items-center px-4 py-3 text-gray-300 hover:text-white rounded transition duration-200">
            <i class="fas fa-chart-line w-6"></i>
            <span>Investments</span>
          </a>
          <a href="payment-methods.php" class="nav-item flex items-center px-4 py-3 text-gray-300 hover:text-white rounded transition duration-200">
            <i class="fas fa-credit-card w-6"></i>
            <span>Payment Methods</span>
          </a>
          <a href="charts.php" class="nav-item flex items-center px-4 py-3 text-gray-300 hover:text-white rounded transition duration-200">
            <i class="fas fa-credit-card w-6"></i>
            <span>Chart</span>
          </a>
          <a href="settings.php" class="nav-item flex items-center px-4 py-3 text-gray-300 hover:text-white rounded transition duration-200">
            <i class="fas fa-cog w-6"></i>
            <span>Settings</span>
          </a>
          <a href="admin-logout.php" class="flex items-center px-4 py-3 text-gray-300 hover:text-white rounded transition duration-200">
            <i class="fas fa-sign-out-alt w-6"></i>
            <span>Logout</span>
          </a>
        </nav>

        <div class="mt-auto">
          <a href="admin-logout.php" class="flex items-center px-4 py-3 text-gray-300 hover:text-white rounded transition duration-200">
            <i class="fas fa-sign-out-alt w-6"></i>
            <span>Logout</span>
          </a>
        </div>
      </div>
    </div>

    <!-- Main Content Area -->
    <main class="flex-1 overflow-y-auto p-4 bg-gray-900">
      <!-- Page Title -->
      <div class="mb-6 flex justify-between items-center">
        <div>
          <h2 class="text-2xl font-bold">Manage Withdrawals</h2>
          <p class="text-gray-400">Review and process user withdrawal requests.</p>
        </div>

        <div class="flex space-x-2">
          <a href="withdraw.php" class="px-4 py-2 <?php echo $status_filter === '' ? 'bg-yellow-500 text-black' : 'bg-gray-700 text-white'; ?> rounded-md hover:bg-yellow-600 text-sm font-medium transition duration-200">
            All
          </a>
          <a href="withdraw.php?status=pending" class="px-4 py-2 <?php echo $status_filter === 'pending' ? 'bg-yellow-500 text-black' : 'bg-gray-700 text-white'; ?> rounded-md hover:bg-yellow-600 text-sm font-medium transition duration-200">
            Pending
          </a>
          <a href="withdraw.php?status=approved" class="px-4 py-2 <?php echo $status_filter === 'approved' ? 'bg-yellow-500 text-black' : 'bg-gray-700 text-white'; ?> rounded-md hover:bg-yellow-600 text-sm font-medium transition duration-200">
            Approved
          </a>
          <a href="withdraw.php?status=rejected" class="px-4 py-2 <?php echo $status_filter === 'rejected' ? 'bg-yellow-500 text-black' : 'bg-gray-700 text-white'; ?> rounded-md hover:bg-yellow-600 text-sm font-medium transition duration-200">
            Rejected
          </a>
        </div>
      </div>

      <!-- Flash Message -->
      <?php if (isset($_SESSION['admin_message'])): ?>
        <div class="mb-6 p-4 rounded-md <?php echo ($_SESSION['admin_message_type'] === 'success') ? 'bg-green-800 text-green-100' : 'bg-red-800 text-red-100'; ?>">
          <?php echo $_SESSION['admin_message']; ?>
        </div>
        <?php unset($_SESSION['admin_message']);
        unset($_SESSION['admin_message_type']); ?>
      <?php endif; ?>

      <!-- Withdrawals Stats -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <!-- Pending Withdrawals -->
        <div class="bg-gray-800 rounded-lg p-6 border border-gray-700 shadow-lg transition duration-300 stat-card">
          <div class="flex justify-between">
            <div>
              <p class="text-gray-400 text-sm">Pending Withdrawals</p>
              <h3 class="text-2xl font-bold mt-1"><?php echo number_format($pending['count']); ?></h3>
              <p class="text-sm text-yellow-500 mt-1"><?php echo number_format($pending['total'] ?? 0, 2); ?> total</p>
            </div>
            <div class="h-12 w-12 rounded-lg bg-yellow-500 flex items-center justify-center">
              <i class="fas fa-clock text-black"></i>
            </div>
          </div>
          <div class="mt-4">
            <a href="withdraw.php?status=pending" class="text-yellow-500 hover:text-yellow-400 text-sm">
              View pending <i class="fas fa-arrow-right ml-1"></i>
            </a>
          </div>
        </div>

        <!-- Approved Withdrawals -->
        <div class="bg-gray-800 rounded-lg p-6 border border-gray-700 shadow-lg transition duration-300 stat-card">
          <div class="flex justify-between">
            <div>
              <p class="text-gray-400 text-sm">Approved Withdrawals</p>
              <h3 class="text-2xl font-bold mt-1"><?php echo number_format($approved['count']); ?></h3>
              <p class="text-sm text-green-500 mt-1"><?php echo number_format($approved['total'] ?? 0, 2); ?> paid out</p>
            </div>
            <div class="h-12 w-12 rounded-lg bg-green-500 flex items-center justify-center">
              <i class="fas fa-check text-black"></i>
            </div>
          </div>
          <div class="mt-4">
            <a href="withdraw.php?status=approved" class="text-yellow-500 hover:text-yellow-400 text-sm">
              View approved <i class="fas fa-arrow-right ml-1"></i>
            </a>
          </div>
        </div>

        <!-- Tax Collected -->
        <div class="bg-gray-800 rounded-lg p-6 border border-gray-700 shadow-lg transition duration-300 stat-card">
          <div class="flex justify-between">
            <div>
              <p class="text-gray-400 text-sm">Tax Collected</p>
              <h3 class="text-2xl font-bold mt-1"><?php echo number_format($tax['total'] ?? 0, 2); ?></h3>
              <p class="text-sm text-purple-500 mt-1">From withdrawal fees (10%)</p>
            </div>
            <div class="h-12 w-12 rounded-lg bg-purple-500 flex items-center justify-center">
              <i class="fas fa-percentage text-black"></i>
            </div>
          </div>
        </div>
      </div>

      <!-- Withdrawals Table -->
      <div class="bg-gray-800 rounded-lg border border-gray-700 shadow-lg mb-6">
        <div class="p-6 border-b border-gray-700 flex justify-between items-center">
          <h3 class="text-lg font-bold">
            <?php if ($status_filter): ?>
              <?php echo ucfirst($status_filter); ?> Withdrawals
            <?php else: ?>
              All Withdrawals
            <?php endif; ?>
          </h3>
          <span class="text-sm text-gray-400"><?php echo number_format($total_records); ?> total</span>
        </div>

        <?php if (empty($withdrawals)): ?>
          <div class="p-8 text-center text-gray-400">
            <i class="fas fa-file-invoice-dollar text-4xl mb-4"></i>
            <p>No withdrawal requests found.</p>
          </div>
        <?php else: ?>
          <div class="overflow-x-auto">
            <table class="w-full">
              <thead class="bg-gray-700">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">User</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">ID</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Date</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Amount</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Tax</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Net Amount</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Payment</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Status</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-700">
                <?php foreach ($withdrawals as $withdrawal): ?>
                  <tr class="hover:bg-gray-700">
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="flex items-center">
                        <div class="h-8 w-8 rounded-full bg-gray-600 flex items-center justify-center text-sm font-medium">
                          <?php echo strtoupper(substr($withdrawal['user_name'] ?? 'U', 0, 1)); ?>
                        </div>
                        <div class="ml-3">
                          <div class="text-sm font-medium"><?php echo htmlspecialchars($withdrawal['user_name'] ?? 'Unknown'); ?></div>
                          <div class="text-xs text-gray-400"><?php echo htmlspecialchars($withdrawal['user_email'] ?? 'No email'); ?></div>
                        </div>
                      </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                      <?php echo htmlspecialchars($withdrawal['withdrawal_id']); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                      <?php echo date('M d, Y H:i', strtotime($withdrawal['created_at'])); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                      <?php echo number_format($withdrawal['amount'], 2); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-red-400">
                      <?php echo number_format($withdrawal['tax_amount'], 2); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-green-400 font-medium">
                      <?php echo number_format($withdrawal['net_amount'], 2); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                      <div class="flex flex-col">
                        <span class="font-medium"><?php echo ucfirst($withdrawal['payment_type']); ?></span>
                        <span class="text-xs"><?php echo htmlspecialchars($withdrawal['account_name']); ?></span>
                        <span class="text-xs"><?php echo htmlspecialchars($withdrawal['account_number']); ?></span>
                      </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <?php if ($withdrawal['status'] === 'pending'): ?>
                        <span class="px-2 py-1 text-xs rounded-full bg-yellow-900 text-yellow-400">Pending</span>
                      <?php elseif ($withdrawal['status'] === 'approved'): ?>
                        <span class="px-2 py-1 text-xs rounded-full bg-green-900 text-green-400">Approved</span>
                      <?php elseif ($withdrawal['status'] === 'rejected'): ?>
                        <span class="px-2 py-1 text-xs rounded-full bg-red-900 text-red-400">Rejected</span>
                      <?php endif; ?>

                      <?php if ($withdrawal['status'] !== 'pending'): ?>
                        <div class="text-xs text-gray-400 mt-1">
                          By: <?php echo htmlspecialchars($withdrawal['admin_name'] ?? 'N/A'); ?>
                        </div>
                        <div class="text-xs text-gray-400">
                          On: <?php echo $withdrawal['processed_at'] ? date('M d, Y', strtotime($withdrawal['processed_at'])) : 'N/A'; ?>
                        </div>
                      <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <?php if ($withdrawal['status'] === 'pending'): ?>
                        <div class="flex space-x-2">
                          <button class="text-green-500 hover:text-green-400"
                            onclick="showApproveModal(<?php echo $withdrawal['id']; ?>, '<?php echo htmlspecialchars($withdrawal['withdrawal_id']); ?>', '<?php echo htmlspecialchars($withdrawal['user_name'] ?? 'Unknown'); ?>', '<?php echo $withdrawal['amount']; ?>', '<?php echo $withdrawal['net_amount']; ?>')">
                            <i class="fas fa-check-circle"></i>
                          </button>
                          <button class="text-red-500 hover:text-red-400"
                            onclick="showRejectModal(<?php echo $withdrawal['id']; ?>, '<?php echo htmlspecialchars($withdrawal['withdrawal_id']); ?>', '<?php echo htmlspecialchars($withdrawal['user_name'] ?? 'Unknown'); ?>', '<?php echo $withdrawal['amount']; ?>')">
                            <i class="fas fa-times-circle"></i>
                          </button>
                        </div>
                      <?php else: ?>
                        <button class="text-blue-500 hover:text-blue-400"
                          onclick="showDetailsModal(<?php echo $withdrawal['id']; ?>, '<?php echo htmlspecialchars($withdrawal['withdrawal_id']); ?>', '<?php echo htmlspecialchars($withdrawal['user_name'] ?? 'Unknown'); ?>', '<?php echo $withdrawal['amount']; ?>', '<?php echo $withdrawal['status']; ?>', '<?php echo htmlspecialchars($withdrawal['notes'] ?? ''); ?>')">
                          <i class="fas fa-info-circle"></i> Details
                        </button>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
          <div class="p-6 border-t border-gray-700 flex justify-center">
            <div class="flex space-x-1">
              <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?><?php echo $status_filter ? '&status=' . $status_filter : ''; ?>" class="px-4 py-2 bg-gray-700 text-white rounded-md hover:bg-yellow-600 text-sm transition duration-200">
                  <i class="fas fa-chevron-left"></i>
                </a>
              <?php endif; ?>

              <?php
              $start_page = max(1, $page - 2);
              $end_page = min($start_page + 4, $total_pages);
              if ($end_page - $start_page < 4) {
                $start_page = max(1, $end_page - 4);
              }
              ?>

              <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                <a href="?page=<?php echo $i; ?><?php echo $status_filter ? '&status=' . $status_filter : ''; ?>"
                  class="px-4 py-2 <?php echo $i === $page ? 'bg-yellow-500 text-black' : 'bg-gray-700 text-white'; ?> rounded-md hover:bg-yellow-600 text-sm transition duration-200">
                  <?php echo $i; ?>
                </a>
              <?php endfor; ?>

              <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?><?php echo $status_filter ? '&status=' . $status_filter : ''; ?>" class="px-4 py-2 bg-gray-700 text-white rounded-md hover:bg-yellow-600 text-sm transition duration-200">
                  <i class="fas fa-chevron-right"></i>
                </a>
              <?php endif; ?>
            </div>
          </div>
        <?php endif; ?>
      </div>

      <!-- Approve Withdrawal Modal -->
      <div id="approve-modal" class="fixed inset-0 bg-black bg-opacity-70 z-50 hidden flex items-center justify-center p-4">
        <div class="bg-gray-800 rounded-lg shadow-xl w-full max-w-md p-6 border border-gray-700">
          <div class="mb-4">
            <h3 class="text-xl font-bold text-green-500">Approve Withdrawal</h3>
            <p class="text-gray-400 mt-1">Please confirm withdrawal approval</p>
          </div>

          <div class="mb-6">
            <div class="flex justify-between mb-2">
              <span class="text-gray-400">Withdrawal ID:</span>
              <span id="approve-id" class="font-medium"></span>
            </div>
            <div class="flex justify-between mb-2">
              <span class="text-gray-400">User:</span>
              <span id="approve-user" class="font-medium"></span>
            </div>
            <div class="flex justify-between mb-2">
              <span class="text-gray-400">Requested Amount:</span>
              <span id="approve-amount" class="font-medium"></span>
            </div>
            <div class="flex justify-between mb-2">
              <span class="text-gray-400">Net Amount (After Tax):</span>
              <span id="approve-net-amount" class="font-medium text-green-500"></span>
            </div>
          </div>

          <div class="mb-6">
            <label for="approve-notes" class="block text-sm font-medium text-gray-400 mb-2">Admin Notes (Optional)</label>
            <textarea id="approve-notes" rows="3" class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-md text-white focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent"></textarea>
          </div>

          <div class="flex justify-end space-x-3">
            <button type="button" class="px-4 py-2 bg-gray-700 text-white rounded-md hover:bg-gray-600 transition duration-200" onclick="closeModal('approve-modal')">
              Cancel
            </button>
            <form id="approve-form" method="POST" action="">
              <input type="hidden" name="withdrawal_id" id="approve-form-id">
              <input type="hidden" name="action" value="approve">
              <input type="hidden" name="admin_notes" id="approve-form-notes">
              <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition duration-200">
                Confirm Approval
              </button>
            </form>
          </div>
        </div>
      </div>

      <!-- Reject Withdrawal Modal -->
      <div id="reject-modal" class="fixed inset-0 bg-black bg-opacity-70 z-50 hidden flex items-center justify-center p-4">
        <div class="bg-gray-800 rounded-lg shadow-xl w-full max-w-md p-6 border border-gray-700">
          <div class="mb-4">
            <h3 class="text-xl font-bold text-red-500">Reject Withdrawal</h3>
            <p class="text-gray-400 mt-1">The requested amount will be returned to user's wallet</p>
          </div>

          <div class="mb-6">
            <div class="flex justify-between mb-2">
              <span class="text-gray-400">Withdrawal ID:</span>
              <span id="reject-id" class="font-medium"></span>
            </div>
            <div class="flex justify-between mb-2">
              <span class="text-gray-400">User:</span>
              <span id="reject-user" class="font-medium"></span>
            </div>
            <div class="flex justify-between mb-2">
              <span class="text-gray-400">Amount:</span>
              <span id="reject-amount" class="font-medium"></span>
            </div>
          </div>

          <div class="mb-6">
            <label for="reject-reason" class="block text-sm font-medium text-gray-400 mb-2">Rejection Reason (Required)</label>
            <textarea id="reject-reason" rows="3" class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-md text-white focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent" required></textarea>
            <p class="text-sm text-gray-400 mt-1">This reason will be visible to the user</p>
          </div>

          <div class="flex justify-end space-x-3">
            <button type="button" class="px-4 py-2 bg-gray-700 text-white rounded-md hover:bg-gray-600 transition duration-200" onclick="closeModal('reject-modal')">
              Cancel
            </button>
            <form id="reject-form" method="POST" action="">
              <input type="hidden" name="withdrawal_id" id="reject-form-id">
              <input type="hidden" name="action" value="reject">
              <input type="hidden" name="admin_notes" id="reject-form-notes">
              <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition duration-200">
                Confirm Rejection
              </button>
            </form>
          </div>
        </div>
      </div>

      <!-- Details Modal -->
      <div id="details-modal" class="fixed inset-0 bg-black bg-opacity-70 z-50 hidden flex items-center justify-center p-4">
        <div class="bg-gray-800 rounded-lg shadow-xl w-full max-w-md p-6 border border-gray-700">
          <div class="mb-4">
            <h3 class="text-xl font-bold text-blue-500">Withdrawal Details</h3>
          </div>

          <div class="mb-6">
            <div class="flex justify-between mb-2">
              <span class="text-gray-400">Withdrawal ID:</span>
              <span id="details-id" class="font-medium"></span>
            </div>
            <div class="flex justify-between mb-2">
              <span class="text-gray-400">User:</span>
              <span id="details-user" class="font-medium"></span>
            </div>
            <div class="flex justify-between mb-2">
              <span class="text-gray-400">Amount:</span>
              <span id="details-amount" class="font-medium"></span>
            </div>
            <div class="flex justify-between mb-2">
              <span class="text-gray-400">Status:</span>
              <span id="details-status" class="font-medium"></span>
            </div>
            <div class="mt-4">
              <span class="text-gray-400 block mb-2">Admin Notes:</span>
              <div id="details-notes" class="p-3 bg-gray-700 rounded-md text-sm"></div>
            </div>
          </div>

          <div class="flex justify-end">
            <button type="button" class="px-4 py-2 bg-gray-700 text-white rounded-md hover:bg-gray-600 transition duration-200" onclick="closeModal('details-modal')">
              Close
            </button>
          </div>
        </div>
      </div>

    </main>
  </div>

  <script>
    // Mobile menu toggle
    const mobileMenuButton = document.getElementById('mobile-menu-button');
    const mobileSidebar = document.getElementById('mobile-sidebar');
    const closeSidebar = document.getElementById('close-sidebar');

    mobileMenuButton.addEventListener('click', () => {
      mobileSidebar.classList.toggle('-translate-x-full');
    });

    closeSidebar.addEventListener('click', () => {
      mobileSidebar.classList.add('-translate-x-full');
    });

    // Modal functions
    function showApproveModal(id, withdrawalId, userName, amount, netAmount) {
      document.getElementById('approve-id').textContent = withdrawalId;
      document.getElementById('approve-user').textContent = userName;
      document.getElementById('approve-amount').textContent = '' + parseFloat(amount).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      });
      document.getElementById('approve-net-amount').textContent = '' + parseFloat(netAmount).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      });
      document.getElementById('approve-form-id').value = id;

      document.getElementById('approve-modal').classList.remove('hidden');
    }

    function showRejectModal(id, withdrawalId, userName, amount) {
      document.getElementById('reject-id').textContent = withdrawalId;
      document.getElementById('reject-user').textContent = userName;
      document.getElementById('reject-amount').textContent = '' + parseFloat(amount).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      });
      document.getElementById('reject-form-id').value = id;

      document.getElementById('reject-modal').classList.remove('hidden');
    }

    function showDetailsModal(id, withdrawalId, userName, amount, status, notes) {
      document.getElementById('details-id').textContent = withdrawalId;
      document.getElementById('details-user').textContent = userName;
      document.getElementById('details-amount').textContent = '' + parseFloat(amount).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      });

      const statusElement = document.getElementById('details-status');
      statusElement.textContent = status.charAt(0).toUpperCase() + status.slice(1);

      if (status === 'approved') {
        statusElement.classList.add('text-green-500');
      } else if (status === 'rejected') {
        statusElement.classList.add('text-red-500');
      }

      document.getElementById('details-notes').textContent = notes || 'No notes provided';

      document.getElementById('details-modal').classList.remove('hidden');
    }

    function closeModal(modalId) {
      document.getElementById(modalId).classList.add('hidden');
    }

    // Form submission
    document.getElementById('approve-form').addEventListener('submit', function() {
      const notes = document.getElementById('approve-notes').value;
      document.getElementById('approve-form-notes').value = notes;
    });

    document.getElementById('reject-form').addEventListener('submit', function(e) {
      const reason = document.getElementById('reject-reason').value;
      if (!reason.trim()) {
        e.preventDefault();
        alert('Please provide a rejection reason');
        return false;
      }
      document.getElementById('reject-form-notes').value = reason;
    });

    // Close modals when clicking outside
    document.querySelectorAll('#approve-modal, #reject-modal, #details-modal').forEach(modal => {
      modal.addEventListener('click', function(e) {
        if (e.target === this) {
          this.classList.add('hidden');
        }
      });
    });
  </script>

</body>

</html>