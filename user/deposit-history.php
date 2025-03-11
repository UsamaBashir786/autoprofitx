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
$deposits = [];
$total_pending = 0;
$total_approved = 0;
$total_rejected = 0;

// Pagination setup
$items_per_page = 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$current_page = max(1, $current_page); // Ensure page is at least 1
$offset = ($current_page - 1) * $items_per_page;

// Filter options
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Check if deposits table exists
$check_table_sql = "SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'deposits'";
$check_result = $conn->query($check_table_sql);
$table_exists = ($check_result && $check_result->fetch_assoc()['count'] > 0);

if ($table_exists) {
  // Build query with filters
  $query = "SELECT d.*, pm.payment_type as user_payment_type, apm.payment_type as admin_payment_type, 
              apm.account_name as admin_account_name 
              FROM deposits d
              LEFT JOIN payment_methods pm ON d.payment_method_id = pm.id
              LEFT JOIN admin_payment_methods apm ON d.admin_payment_id = apm.id
              WHERE d.user_id = ?";

  $params = [$user_id];
  $types = "i";

  // Add status filter if set
  if (!empty($status_filter) && in_array($status_filter, ['pending', 'approved', 'rejected'])) {
    $query .= " AND d.status = ?";
    $params[] = $status_filter;
    $types .= "s";
  }

  // Add date range filters if set
  if (!empty($date_from)) {
    $query .= " AND DATE(d.created_at) >= ?";
    $params[] = $date_from;
    $types .= "s";
  }

  if (!empty($date_to)) {
    $query .= " AND DATE(d.created_at) <= ?";
    $params[] = $date_to;
    $types .= "s";
  }

  // Get total count for pagination
  $count_query = $query;
  $stmt = $conn->prepare($count_query);
  if ($stmt) {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $total_items = $result->num_rows;
    $stmt->close();
  } else {
    $total_items = 0;
  }

  $total_pages = ceil($total_items / $items_per_page);
  $current_page = min($current_page, max(1, $total_pages)); // Adjust current page if needed

  // Add order and limit for the actual data query
  $query .= " ORDER BY d.created_at DESC LIMIT ?, ?";
  $params[] = $offset;
  $params[] = $items_per_page;
  $types .= "ii";

  // Execute the query
  $stmt = $conn->prepare($query);
  if ($stmt) {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
      $deposits[] = $row;
    }

    $stmt->close();
  }

  // Get deposit statistics
  $stats_query = "SELECT 
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
                    SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending_amount,
                    SUM(CASE WHEN status = 'approved' THEN amount ELSE 0 END) as approved_amount,
                    SUM(CASE WHEN status = 'rejected' THEN amount ELSE 0 END) as rejected_amount
                    FROM deposits WHERE user_id = ?";

  $stats_stmt = $conn->prepare($stats_query);
  if ($stats_stmt) {
    $stats_stmt->bind_param("i", $user_id);
    $stats_stmt->execute();
    $stats_result = $stats_stmt->get_result();

    if ($stats_row = $stats_result->fetch_assoc()) {
      $pending_count = $stats_row['pending_count'] ?? 0;
      $approved_count = $stats_row['approved_count'] ?? 0;
      $rejected_count = $stats_row['rejected_count'] ?? 0;
      $total_pending = $stats_row['pending_amount'] ?? 0;
      $total_approved = $stats_row['approved_amount'] ?? 0;
      $total_rejected = $stats_row['rejected_amount'] ?? 0;
    }

    $stats_stmt->close();
  }
}

// Function to format date for display
function formatDate($date)
{
  return date('M d, Y h:i A', strtotime($date));
}

// Function to safely format numeric values
function safeNumberFormat($value, $decimals = 2)
{
  // Ensure the value is a number and not null
  $value = is_null($value) ? 0 : (float)$value;
  return number_format($value, $decimals);
}

// Function to get status badge
function getStatusBadge($status)
{
  switch ($status) {
    case 'pending':
      return '<span class="px-2 py-1 text-xs rounded-full bg-yellow-900 text-yellow-400">Pending</span>';
    case 'approved':
      return '<span class="px-2 py-1 text-xs rounded-full bg-green-900 text-green-400">Approved</span>';
    case 'rejected':
      return '<span class="px-2 py-1 text-xs rounded-full bg-red-900 text-red-400">Rejected</span>';
    default:
      return '<span class="px-2 py-1 text-xs rounded-full bg-gray-900 text-gray-400">Unknown</span>';
  }
}

// Function to get payment method icon class
function getPaymentIcon($payment_type)
{
  switch (strtolower($payment_type ?? '')) {
    case 'easypaisa':
      return 'fas fa-mobile-alt text-green-500';
    case 'jazzcash':
      return 'fas fa-money-bill text-red-500';
    case 'bank':
    case 'bank account':
      return 'fas fa-university text-blue-500';
    default:
      return 'fas fa-credit-card text-gray-400';
  }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <?php include 'includes/head.php'; ?>
  <title>AutoProftX - Deposit History</title>
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
        <h1 class="text-2xl font-bold">Deposit History</h1>
        <p class="text-gray-400">View all your deposit transactions</p>
      </div>

      <!-- Statistics Cards -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
        <!-- Pending Deposits -->
        <div class="bg-gradient-to-br from-yellow-900 to-yellow-800 bg-opacity-30 p-4 rounded-lg border border-yellow-800">
          <div class="flex justify-between items-start">
            <div>
              <p class="text-yellow-400 text-sm">Pending Deposits</p>
              <h3 class="text-2xl font-bold text-white"><?php echo isset($pending_count) ? $pending_count : 0; ?></h3>
              <p class="text-yellow-400 text-sm">$<?php echo safeNumberFormat($total_pending); ?></p>
            </div>
            <div class="p-3 bg-yellow-800 bg-opacity-50 rounded-lg">
              <i class="fas fa-clock text-yellow-400"></i>
            </div>
          </div>
        </div>

        <!-- Approved Deposits -->
        <div class="bg-gradient-to-br from-green-900 to-green-800 bg-opacity-30 p-4 rounded-lg border border-green-800">
          <div class="flex justify-between items-start">
            <div>
              <p class="text-green-400 text-sm">Approved Deposits</p>
              <h3 class="text-2xl font-bold text-white"><?php echo isset($approved_count) ? $approved_count : 0; ?></h3>
              <p class="text-green-400 text-sm">$<?php echo safeNumberFormat($total_approved); ?></p>
            </div>
            <div class="p-3 bg-green-800 bg-opacity-50 rounded-lg">
              <i class="fas fa-check-circle text-green-400"></i>
            </div>
          </div>
        </div>

        <!-- Rejected Deposits -->
        <div class="bg-gradient-to-br from-red-900 to-red-800 bg-opacity-30 p-4 rounded-lg border border-red-800">
          <div class="flex justify-between items-start">
            <div>
              <p class="text-red-400 text-sm">Rejected Deposits</p>
              <h3 class="text-2xl font-bold text-white"><?php echo isset($rejected_count) ? $rejected_count : 0; ?></h3>
              <p class="text-red-400 text-sm">$<?php echo safeNumberFormat($total_rejected); ?></p>
            </div>
            <div class="p-3 bg-red-800 bg-opacity-50 rounded-lg">
              <i class="fas fa-times-circle text-red-400"></i>
            </div>
          </div>
        </div>
      </div>

      <!-- Filter Section -->
      <div class="mb-6 bg-gray-800 rounded-lg p-4 border border-gray-700">
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div>
            <label for="status" class="block text-sm font-medium text-gray-300 mb-2">Status</label>
            <select id="status" name="status" class="bg-gray-700 border border-gray-600 rounded-md w-full py-2 px-3 text-white focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent">
              <option value="">All Status</option>
              <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
              <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
              <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
            </select>
          </div>
          <div>
            <label for="date_from" class="block text-sm font-medium text-gray-300 mb-2">From Date</label>
            <input type="date" id="date_from" name="date_from" value="<?php echo $date_from; ?>" class="bg-gray-700 border border-gray-600 rounded-md w-full py-2 px-3 text-white focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent">
          </div>
          <div>
            <label for="date_to" class="block text-sm font-medium text-gray-300 mb-2">To Date</label>
            <input type="date" id="date_to" name="date_to" value="<?php echo $date_to; ?>" class="bg-gray-700 border border-gray-600 rounded-md w-full py-2 px-3 text-white focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent">
          </div>
          <div class="flex items-end">
            <button type="submit" class="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded-md transition duration-300 w-full">
              <i class="fas fa-filter mr-2"></i> Apply Filters
            </button>
          </div>
        </form>
      </div>

      <!-- Deposits Table -->
      <div class="bg-gray-800 rounded-lg border border-gray-700 overflow-hidden mb-8">
        <?php if (empty($deposits)): ?>
          <div class="p-8 text-center">
            <div class="text-gray-500 mb-4">
              <i class="fas fa-search text-4xl"></i>
            </div>
            <h3 class="text-xl font-bold mb-2">No deposits found</h3>
            <p class="text-gray-400 mb-4">No deposit records match your search criteria</p>
            <a href="deposit.php" class="inline-block bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded-md transition duration-300">
              <i class="fas fa-plus mr-2"></i> Make a Deposit
            </a>
          </div>
        <?php else: ?>
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-700">
              <thead class="bg-gray-700">
                <tr>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">ID</th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Date</th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Amount</th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Payment Method</th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Transaction ID</th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Status</th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Actions</th>
                </tr>
              </thead>
              <tbody class="bg-gray-800 divide-y divide-gray-700">
                <?php foreach ($deposits as $deposit): ?>
                  <tr class="hover:bg-gray-750">
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="text-sm text-gray-200">#<?php echo $deposit['id']; ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="text-sm text-gray-200"><?php echo formatDate($deposit['created_at']); ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="text-sm font-semibold text-white">$<?php echo safeNumberFormat($deposit['amount']); ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="flex items-center">
                        <i class="<?php echo getPaymentIcon($deposit['user_payment_type']); ?> mr-2"></i>
                        <div class="text-sm text-gray-200">
                          <?php echo ucfirst($deposit['user_payment_type'] ?? 'N/A'); ?> →
                          <?php echo ucfirst($deposit['admin_payment_type'] ?? 'N/A'); ?>
                        </div>
                      </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="text-sm text-gray-200">
                        <?php echo !empty($deposit['transaction_id']) ? $deposit['transaction_id'] : 'N/A'; ?>
                      </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <?php echo getStatusBadge($deposit['status']); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                      <button onclick="viewDepositDetails(<?php echo $deposit['id']; ?>)" class="text-yellow-500 hover:text-yellow-400 mr-3">
                        <i class="fas fa-eye"></i> View
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

      <!-- Pagination -->
      <?php if ($total_pages > 1): ?>
        <div class="flex justify-center mt-8">
          <nav class="inline-flex rounded-md shadow-sm">
            <?php
            // Build the query string for pagination links
            $query_params = [];
            if (!empty($status_filter)) $query_params['status'] = $status_filter;
            if (!empty($date_from)) $query_params['date_from'] = $date_from;
            if (!empty($date_to)) $query_params['date_to'] = $date_to;

            // Previous Page Link
            $prev_page = max(1, $current_page - 1);
            $prev_params = array_merge($query_params, ['page' => $prev_page]);
            $prev_url = htmlspecialchars($_SERVER["PHP_SELF"]) . '?' . http_build_query($prev_params);

            // Next Page Link
            $next_page = min($total_pages, $current_page + 1);
            $next_params = array_merge($query_params, ['page' => $next_page]);
            $next_url = htmlspecialchars($_SERVER["PHP_SELF"]) . '?' . http_build_query($next_params);
            ?>

            <!-- Previous Page Button -->
            <a href="<?php echo $prev_url; ?>" class="<?php echo $current_page === 1 ? 'opacity-50 cursor-not-allowed' : ''; ?> px-4 py-2 text-sm font-medium text-gray-300 bg-gray-800 border border-gray-700 rounded-l-md hover:bg-gray-700 focus:outline-none">
              <i class="fas fa-chevron-left mr-1"></i> Previous
            </a>

            <!-- Page Info -->
            <span class="px-4 py-2 text-sm font-medium text-gray-300 bg-gray-800 border-t border-b border-gray-700">
              Page <?php echo $current_page; ?> of <?php echo $total_pages; ?>
            </span>

            <!-- Next Page Button -->
            <a href="<?php echo $next_url; ?>" class="<?php echo $current_page === $total_pages ? 'opacity-50 cursor-not-allowed' : ''; ?> px-4 py-2 text-sm font-medium text-gray-300 bg-gray-800 border border-gray-700 rounded-r-md hover:bg-gray-700 focus:outline-none">
              Next <i class="fas fa-chevron-right ml-1"></i>
            </a>
          </nav>
        </div>
      <?php endif; ?>
    </div>
  </main>

  <!-- Deposit Details Modal (Hidden by default) -->
  <div id="depositDetailsModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden overflow-y-auto p-4">
    <div class="bg-gray-800 rounded-lg shadow-lg w-full max-w-2xl max-h-[90vh] overflow-hidden flex flex-col">
      <!-- Modal Header -->
      <div class="flex justify-between items-center p-4 border-b border-gray-700">
        <h3 class="text-xl font-bold">Deposit Details</h3>
        <button onclick="closeModal()" class="text-gray-400 hover:text-white">
          <i class="fas fa-times"></i>
        </button>
      </div>

      <!-- Modal Body -->
      <div id="depositDetailsContent" class="p-6 overflow-y-auto" style="max-height: calc(90vh - 130px);">
        <!-- Content will be loaded via AJAX -->
        <div class="animate-pulse">
          <div class="h-6 bg-gray-700 rounded w-3/4 mb-4"></div>
          <div class="h-6 bg-gray-700 rounded w-1/2 mb-4"></div>
          <div class="h-6 bg-gray-700 rounded w-5/6 mb-4"></div>
          <div class="h-6 bg-gray-700 rounded w-3/4 mb-4"></div>
        </div>
      </div>

      <!-- Modal Footer -->
      <div class="p-4 border-t border-gray-700 flex justify-end">
        <button onclick="closeModal()" class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded-md transition duration-300">
          Close
        </button>
      </div>
    </div>
  </div>

  <?php include 'includes/footer.php'; ?>
  <?php include 'includes/js-links.php'; ?>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Show content once page is loaded
      setTimeout(function() {
        document.querySelector('.preloader').style.display = 'none';
        document.getElementById('main-content').style.display = 'block';
      }, 1000);
    });

    function viewDepositDetails(depositId) {
      const modal = document.getElementById('depositDetailsModal');
      const content = document.getElementById('depositDetailsContent');

      // Show modal with loading state
      modal.classList.remove('hidden');

      // Fetch deposit details
      fetch(`get-deposit-details.php?id=${depositId}`)
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            // Format date
            const createdDate = new Date(data.deposit.created_at);
            const formattedDate = createdDate.toLocaleString('en-US', {
              year: 'numeric',
              month: 'long',
              day: 'numeric',
              hour: '2-digit',
              minute: '2-digit'
            });

            // Get status class
            let statusClass = '';
            switch (data.deposit.status) {
              case 'pending':
                statusClass = 'bg-yellow-900 text-yellow-400';
                break;
              case 'approved':
                statusClass = 'bg-green-900 text-green-400';
                break;
              case 'rejected':
                statusClass = 'bg-red-900 text-red-400';
                break;
              default:
                statusClass = 'bg-gray-700 text-gray-300';
            }

            // Build content HTML
            let html = `
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div>
                  <p class="text-gray-400 text-sm mb-1">Deposit ID</p>
                  <p class="font-bold">#${data.deposit.id}</p>
                </div>
                <div>
                  <p class="text-gray-400 text-sm mb-1">Date</p>
                  <p>${formattedDate}</p>
                </div>
                <div>
                  <p class="text-gray-400 text-sm mb-1">Amount</p>
                  <p class="font-bold text-xl">$${parseFloat(data.deposit.amount || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</p>
                </div>
                <div>
                  <p class="text-gray-400 text-sm mb-1">Status</p>
                  <p><span class="px-2 py-1 rounded-full ${statusClass} text-xs">${data.deposit.status.charAt(0).toUpperCase() + data.deposit.status.slice(1)}</span></p>
                </div>
              </div>
              
              <div class="mb-6">
                <h4 class="font-bold mb-2">Payment Details</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 bg-gray-700 bg-opacity-30 p-4 rounded-lg">
                  <div>
                    <p class="text-gray-400 text-sm mb-1">From (Your Payment Method)</p>
                    <p>${data.deposit.user_payment_type ? data.deposit.user_payment_type.charAt(0).toUpperCase() + data.deposit.user_payment_type.slice(1) : 'N/A'}</p>
                  </div>
                  <div>
                    <p class="text-gray-400 text-sm mb-1">To (Admin Payment Method)</p>
                    <p>${data.deposit.admin_payment_type ? data.deposit.admin_payment_type.charAt(0).toUpperCase() + data.deposit.admin_payment_type.slice(1) : 'N/A'} - ${data.deposit.admin_account_name || 'N/A'}</p>
                  </div>
                  <div>
                    <p class="text-gray-400 text-sm mb-1">Transaction ID</p>
                    <p>${data.deposit.transaction_id || 'N/A'}</p>
                  </div>
                </div>
              </div>
            `;

            // Add payment proof section if available
            if (data.deposit.proof_file) {
              const fileExt = data.deposit.proof_file.split('.').pop().toLowerCase();
              const isImage = ['jpg', 'jpeg', 'png'].includes(fileExt);

              html += `
                <div class="mb-6">
                  <h4 class="font-bold mb-2">Payment Proof</h4>
                  <div class="bg-gray-700 bg-opacity-30 p-4 rounded-lg">
              `;

              if (isImage) {
                html += `<img src="../uploads/payment_proofs/${data.deposit.proof_file}" alt="Payment Proof" class="max-w-full h-auto rounded-lg">`;
              } else {
                html += `
                  <div class="flex items-center">
                    <i class="fas fa-file-pdf text-red-400 text-2xl mr-2"></i>
                    <a href="../uploads/payment_proofs/${data.deposit.proof_file}" target="_blank" class="text-yellow-500 hover:text-yellow-400">
                      View Payment Proof (${fileExt.toUpperCase()})
                    </a>
                  </div>
                `;
              }

              html += `
                  </div>
                </div>
              `;
            }

            // Add notes section if available
            if (data.deposit.notes) {
              html += `
                <div class="mb-6">
                  <h4 class="font-bold mb-2">Your Notes</h4>
                  <div class="bg-gray-700 bg-opacity-30 p-4 rounded-lg">
                    <p>${data.deposit.notes}</p>
                  </div>
                </div>
              `;
            }

            // Add admin notes section if available and deposit is not pending
            if (data.deposit.admin_notes && data.deposit.status !== 'pending') {
              html += `
                <div class="mb-6">
                  <h4 class="font-bold mb-2">Admin Response</h4>
                  <div class="bg-gray-700 bg-opacity-30 p-4 rounded-lg">
                    <p>${data.deposit.admin_notes}</p>
                  </div>
                </div>
              `;
            }

            content.innerHTML = html;
          } else {
            content.innerHTML = `<div class="text-center py-6 text-red-400">
              <i class="fas fa-exclamation-circle text-3xl mb-2"></i>
              <p>Error: ${data.message || 'Failed to load deposit details'}</p>
            </div>`;
          }
        })
        .catch(error => {
          content.innerHTML = `<div class="text-center py-6 text-red-400">
            <i class="fas fa-exclamation-circle text-3xl mb-2"></i>
            <p>Error: Failed to load deposit details</p>
          </div>`;
          console.error('Error fetching deposit details:', error);
        });
    }

    function closeModal() {
      document.getElementById('depositDetailsModal').classList.add('hidden');
    }
  </script>
</body>

</html>