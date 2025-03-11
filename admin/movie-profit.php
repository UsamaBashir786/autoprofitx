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
// Get admin info
$admin_id = $_SESSION['admin_id'];
$admin_name = $_SESSION['admin_name'] ?? 'Admin';
// Process manual profit payment
if (isset($_GET['process']) && is_numeric($_GET['process'])) {
  $purchase_id = $_GET['process'];

  // Get purchase details
  $stmt = $conn->prepare("SELECT p.*, u.email 
                          FROM ticket_purchases p
                          JOIN users u ON p.user_id = u.id
                          WHERE p.id = ? AND p.status = 'active' AND p.profit_paid = 0");
  $stmt->bind_param("i", $purchase_id);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows > 0) {
    $purchase = $result->fetch_assoc();

    // Begin transaction
    $conn->begin_transaction();

    try {
      // 1. Update user's wallet
      $stmt = $conn->prepare("UPDATE wallets SET balance = balance + ? WHERE user_id = ?");
      $stmt->bind_param("di", $purchase['total_return'], $purchase['user_id']);
      $stmt->execute();

      // 2. Record transaction
      $reference = "TICKET-MANUAL-" . $purchase_id;
      $description = "Manual profit payment for ticket purchase ID: " . $purchase['purchase_id'];

      $stmt = $conn->prepare("INSERT INTO transactions (user_id, transaction_type, amount, status, description, reference_id) 
                                 VALUES (?, 'profit', ?, 'completed', ?, ?)");
      $stmt->bind_param("idss", $purchase['user_id'], $purchase['expected_profit'], $description, $reference);
      $stmt->execute();

      // 3. Update purchase status
      $stmt = $conn->prepare("UPDATE ticket_purchases SET status = 'completed', profit_paid = 1, completion_date = NOW() WHERE id = ?");
      $stmt->bind_param("i", $purchase_id);
      $stmt->execute();

      // Commit transaction
      $conn->commit();

      $success_message = "Successfully processed profit for purchase ID: " . $purchase['purchase_id'];
    } catch (Exception $e) {
      // Rollback on error
      $conn->rollback();
      $error_message = "Error processing profit: " . $e->getMessage();
    }
  } else {
    $error_message = "Invalid purchase ID or purchase not eligible for profit processing";
  }
}

// Process all pending profits
if (isset($_POST['process_all'])) {
  // Call the stored procedure
  if ($conn->query("CALL process_ticket_profits()")) {
    $success_message = "All eligible profits have been processed successfully.";
  } else {
    $error_message = "Error processing profits: " . $conn->error;
  }
}

// Get statistics
$stats = [
  'total_purchases' => 0,
  'pending_profits' => 0,
  'completed_profits' => 0,
  'total_profit_paid' => 0
];

$result = $conn->query("SELECT COUNT(*) as count FROM ticket_purchases");
if ($result) {
  $stats['total_purchases'] = $result->fetch_assoc()['count'];
}

$result = $conn->query("SELECT COUNT(*) as count FROM ticket_purchases WHERE status = 'active' AND profit_paid = 0 AND maturity_date <= NOW()");
if ($result) {
  $stats['pending_profits'] = $result->fetch_assoc()['count'];
}

$result = $conn->query("SELECT COUNT(*) as count FROM ticket_purchases WHERE status = 'completed' AND profit_paid = 1");
if ($result) {
  $stats['completed_profits'] = $result->fetch_assoc()['count'];
}

$result = $conn->query("SELECT SUM(expected_profit) as total FROM ticket_purchases WHERE status = 'completed' AND profit_paid = 1");
if ($result) {
  $row = $result->fetch_assoc();
  $stats['total_profit_paid'] = $row['total'] ? $row['total'] : 0;
}

// Get purchase data with pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Filter options
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$profit_filter = isset($_GET['profit']) ? $_GET['profit'] : 'all';

// Build query with filters
$query = "SELECT p.*, t.title as ticket_title, u.email as user_email 
          FROM ticket_purchases p
          JOIN movie_tickets t ON p.ticket_id = t.id
          JOIN users u ON p.user_id = u.id
          WHERE 1=1";

$count_query = "SELECT COUNT(*) as total FROM ticket_purchases p WHERE 1=1";

if ($status_filter != 'all') {
  $query .= " AND p.status = '$status_filter'";
  $count_query .= " AND p.status = '$status_filter'";
}

if ($profit_filter != 'all') {
  if ($profit_filter == 'paid') {
    $query .= " AND p.profit_paid = 1";
    $count_query .= " AND p.profit_paid = 1";
  } else if ($profit_filter == 'unpaid') {
    $query .= " AND p.profit_paid = 0";
    $count_query .= " AND p.profit_paid = 0";
  } else if ($profit_filter == 'pending') {
    $query .= " AND p.status = 'active' AND p.profit_paid = 0 AND p.maturity_date <= NOW()";
    $count_query .= " AND p.status = 'active' AND p.profit_paid = 0 AND p.maturity_date <= NOW()";
  }
}

$query .= " ORDER BY p.purchase_date DESC LIMIT $offset, $limit";

// Get total count for pagination
$result = $conn->query($count_query);
$total_records = $result->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

// Get purchases
$purchases = [];
$result = $conn->query($query);
if ($result && $result->num_rows > 0) {
  while ($row = $result->fetch_assoc()) {
    $purchases[] = $row;
  }
}

// Include header
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Profit & Commission Management - AutoProftX</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    h4 {
      color: white;
    }

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

    /* Form styling */
    .form-input {
      background-color: #374151;
      border-color: #4B5563;
      color: #E5E7EB;
    }

    .form-input:focus {
      border-color: #F59E0B;
      box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.25);
    }
  </style>
</head>

<body class="bg-gray-900 text-gray-100 flex min-h-screen">
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

        <h1 class="text-xl font-bold text-white md:hidden">Profit Management</h1>

        <!-- User Profile -->
        <div class="flex items-center space-x-4">
          <div class="flex items-center">
            <div class="h-8 w-8 rounded-full bg-yellow-500 flex items-center justify-center text-black font-bold">
              <?php echo strtoupper(substr($admin_name, 0, 1)); ?>
            </div>
            <span class="ml-2 hidden sm:inline-block"><?php echo htmlspecialchars($admin_name); ?></span>
          </div>
        </div>
      </div>
    </header>


    <!-- Main Content Area -->
    <main class="flex-1 overflow-y-auto p-4 bg-gray-900">

      <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold  mb-6">Movie Ticket Profit Management</h1>

        <!-- Alert Messages -->
        <?php if (isset($success_message)): ?>
          <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
            <p><?php echo htmlspecialchars($success_message); ?></p>
          </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
          <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
            <p><?php echo htmlspecialchars($error_message); ?></p>
          </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
          <!-- Total Purchases -->
          <div class="bg-white rounded-lg shadow-md overflow-hidden border-l-4 border-blue-500">
            <div class="p-5 flex justify-between items-center">
              <div>
                <p class="text-xs font-semibold text-blue-500 uppercase mb-1">Total Purchases</p>
                <p class="text-2xl font-bold text-gray-800"><?php echo $stats['total_purchases']; ?></p>
              </div>
              <div class="text-gray-400">
                <i class="fas fa-ticket-alt text-3xl"></i>
              </div>
            </div>
          </div>

          <!-- Pending Profits -->
          <div class="bg-white rounded-lg shadow-md overflow-hidden border-l-4 border-yellow-500">
            <div class="p-5 flex justify-between items-center">
              <div>
                <p class="text-xs font-semibold text-yellow-500 uppercase mb-1">Pending Profits</p>
                <p class="text-2xl font-bold text-gray-800"><?php echo $stats['pending_profits']; ?></p>
              </div>
              <div class="text-gray-400">
                <i class="fas fa-clock text-3xl"></i>
              </div>
            </div>
          </div>

          <!-- Completed Profits -->
          <div class="bg-white rounded-lg shadow-md overflow-hidden border-l-4 border-green-500">
            <div class="p-5 flex justify-between items-center">
              <div>
                <p class="text-xs font-semibold text-green-500 uppercase mb-1">Completed Profits</p>
                <p class="text-2xl font-bold text-gray-800"><?php echo $stats['completed_profits']; ?></p>
              </div>
              <div class="text-gray-400">
                <i class="fas fa-check-circle text-3xl"></i>
              </div>
            </div>
          </div>

          <!-- Total Profit Paid -->
          <div class="bg-white rounded-lg shadow-md overflow-hidden border-l-4 border-indigo-500">
            <div class="p-5 flex justify-between items-center">
              <div>
                <p class="text-xs font-semibold text-indigo-500 uppercase mb-1">Total Profit Paid</p>
                <p class="text-2xl font-bold text-gray-800">$<?php echo number_format($stats['total_profit_paid'], 2); ?></p>
              </div>
              <div class="text-gray-400">
                <i class="fas fa-dollar-sign text-3xl"></i>
              </div>
            </div>
          </div>
        </div>

        <!-- Process All Profits Button -->
        <div class="mb-8">
          <form method="post">
            <button type="submit" name="process_all" class="flex items-center px-4 py-2 bg-green-600 text-white font-medium rounded-lg hover:bg-green-700 transition-colors duration-300 <?php echo $stats['pending_profits'] == 0 ? 'opacity-50 cursor-not-allowed' : ''; ?>" <?php echo $stats['pending_profits'] == 0 ? 'disabled' : ''; ?>>
              <i class="fas fa-sync-alt mr-2"></i>
              <span>Process All Pending Profits (<?php echo $stats['pending_profits']; ?>)</span>
            </button>
          </form>
        </div>

        <!-- Purchases Table Card -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
          <div class="bg-white p-5 border-b flex justify-between items-center">
            <h2 class="text-xl font-semibold text-gray-800">Ticket Purchases</h2>
            <!-- Filter Dropdown -->
            <div class="relative inline-block text-left">
              <button type="button" id="filterDropdown" class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                <i class="fas fa-filter mr-2"></i>
                Filters
                <i class="fas fa-chevron-down ml-2"></i>
              </button>

              <div id="filterMenu" class="hidden origin-top-right absolute right-0 mt-2 w-56 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 focus:outline-none z-10" role="menu">
                <div class="py-1">
                  <div class="px-3 py-2 text-xs font-semibold text-gray-500">Status</div>
                  <a href="?status=all&profit=<?php echo $profit_filter; ?>" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 <?php echo $status_filter == 'all' ? 'bg-gray-100' : ''; ?>">All</a>
                  <a href="?status=active&profit=<?php echo $profit_filter; ?>" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 <?php echo $status_filter == 'active' ? 'bg-gray-100' : ''; ?>">Active</a>
                  <a href="?status=completed&profit=<?php echo $profit_filter; ?>" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 <?php echo $status_filter == 'completed' ? 'bg-gray-100' : ''; ?>">Completed</a>

                  <div class="border-t border-gray-100 my-2"></div>

                  <div class="px-3 py-2 text-xs font-semibold text-gray-500">Profit Status</div>
                  <a href="?status=<?php echo $status_filter; ?>&profit=all" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 <?php echo $profit_filter == 'all' ? 'bg-gray-100' : ''; ?>">All</a>
                  <a href="?status=<?php echo $status_filter; ?>&profit=paid" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 <?php echo $profit_filter == 'paid' ? 'bg-gray-100' : ''; ?>">Paid</a>
                  <a href="?status=<?php echo $status_filter; ?>&profit=unpaid" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 <?php echo $profit_filter == 'unpaid' ? 'bg-gray-100' : ''; ?>">Unpaid</a>
                  <a href="?status=<?php echo $status_filter; ?>&profit=pending" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 <?php echo $profit_filter == 'pending' ? 'bg-gray-100' : ''; ?>">Pending (Due)</a>
                </div>
              </div>
            </div>
          </div>

          <!-- Active Filters Display -->
          <div class="bg-gray-50 px-5 py-3 flex flex-wrap gap-2">
            <span class="text-sm text-gray-600">Active Filters:</span>
            <?php if ($status_filter != 'all'): ?>
              <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                Status: <?php echo ucfirst($status_filter); ?>
                <a href="?status=all&profit=<?php echo $profit_filter; ?>" class="ml-1 text-blue-600 hover:text-blue-800">
                  <i class="fas fa-times-circle"></i>
                </a>
              </span>
            <?php endif; ?>

            <?php if ($profit_filter != 'all'): ?>
              <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                Profit: <?php echo ucfirst($profit_filter); ?>
                <a href="?status=<?php echo $status_filter; ?>&profit=all" class="ml-1 text-purple-600 hover:text-purple-800">
                  <i class="fas fa-times-circle"></i>
                </a>
              </span>
            <?php endif; ?>

            <?php if ($status_filter != 'all' || $profit_filter != 'all'): ?>
              <a href="?status=all&profit=all" class="text-sm text-red-600 hover:text-red-800 ml-2">Clear All</a>
            <?php else: ?>
              <span class="text-sm text-gray-500">None</span>
            <?php endif; ?>
          </div>

          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ticket</th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Profit (4.5%)</th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Purchase Date</th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Maturity Date</th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($purchases)): ?>
                  <tr>
                    <td colspan="10" class="px-6 py-4 text-center text-sm text-gray-500">No purchases found</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($purchases as $purchase): ?>
                    <tr class="hover:bg-gray-50">
                      <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($purchase['purchase_id']); ?></td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($purchase['user_email']); ?></td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($purchase['ticket_title']); ?></td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($purchase['quantity']); ?></td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">$<?php echo number_format($purchase['total_amount'], 2); ?></td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">$<?php echo number_format($purchase['expected_profit'], 2); ?></td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('Y-m-d H:i', strtotime($purchase['purchase_date'])); ?></td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('Y-m-d H:i', strtotime($purchase['maturity_date'])); ?></td>
                      <td class="px-6 py-4 whitespace-nowrap">
                        <?php if ($purchase['status'] == 'active'): ?>
                          <?php if ($purchase['maturity_date'] <= date('Y-m-d H:i:s')): ?>
                            <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">Due for Profit</span>
                          <?php else: ?>
                            <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">Active</span>
                          <?php endif; ?>
                        <?php elseif ($purchase['status'] == 'completed'): ?>
                          <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Completed</span>
                        <?php else: ?>
                          <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Cancelled</span>
                        <?php endif; ?>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        <?php if ($purchase['status'] == 'active' && $purchase['profit_paid'] == 0 && $purchase['maturity_date'] <= date('Y-m-d H:i:s')): ?>
                          <a href="?process=<?php echo $purchase['id']; ?>" class="inline-flex items-center px-3 py-1 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                            <i class="fas fa-money-bill mr-1"></i> Process Profit
                          </a>
                        <?php elseif ($purchase['status'] == 'completed'): ?>
                          <span class="inline-flex items-center text-green-600">
                            <i class="fas fa-check-circle mr-1"></i> Profit Paid
                          </span>
                        <?php else: ?>
                          <span class="text-gray-400">No Action</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <?php if ($total_pages > 1): ?>
            <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
              <div class="flex-1 flex justify-between sm:hidden">
                <?php if ($page > 1): ?>
                  <a href="?page=<?php echo $page - 1; ?>&status=<?php echo $status_filter; ?>&profit=<?php echo $profit_filter; ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">Previous</a>
                <?php endif; ?>
                <?php if ($page < $total_pages): ?>
                  <a href="?page=<?php echo $page + 1; ?>&status=<?php echo $status_filter; ?>&profit=<?php echo $profit_filter; ?>" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">Next</a>
                <?php endif; ?>
              </div>
              <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-center">
                <div>
                  <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                    <?php if ($page > 1): ?>
                      <a href="?page=<?php echo $page - 1; ?>&status=<?php echo $status_filter; ?>&profit=<?php echo $profit_filter; ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                        <span class="sr-only">Previous</span>
                        <i class="fas fa-chevron-left"></i>
                      </a>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                      <a href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>&profit=<?php echo $profit_filter; ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium <?php echo $i == $page ? 'text-indigo-600 bg-indigo-50 border-indigo-500 z-10' : 'text-gray-500 hover:bg-gray-50'; ?>">
                        <?php echo $i; ?>
                      </a>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                      <a href="?page=<?php echo $page + 1; ?>&status=<?php echo $status_filter; ?>&profit=<?php echo $profit_filter; ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                        <span class="sr-only">Next</span>
                        <i class="fas fa-chevron-right"></i>
                      </a>
                    <?php endif; ?>
                  </nav>
                </div>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- JavaScript for Dropdown Toggle -->
      <script>
        document.addEventListener('DOMContentLoaded', function() {
          const filterButton = document.getElementById('filterDropdown');
          const filterMenu = document.getElementById('filterMenu');

          filterButton.addEventListener('click', function() {
            filterMenu.classList.toggle('hidden');
          });

          // Close dropdown when clicking outside
          document.addEventListener('click', function(event) {
            if (!filterButton.contains(event.target) && !filterMenu.contains(event.target)) {
              filterMenu.classList.add('hidden');
            }
          });
        });
      </script>
    </main>
  </div>
</body>

</html>