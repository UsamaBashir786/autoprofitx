<?php
// admin/token-management.php
session_start();
include '../config/db.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
  header("Location: ../login.php");
  exit();
}

// Initialize variables
$total_active_tokens = 0;
$total_sold_tokens = 0;
$total_investment = 0;
$total_current_value = 0;
$total_profit_realized = 0;
$total_profit_unrealized = 0;
$total_users_with_tokens = 0;

// Get system-wide token stats
$stats_sql = "SELECT 
                COUNT(DISTINCT user_id) as total_users_with_tokens,
                COUNT(CASE WHEN status = 'active' THEN 1 END) as total_active_tokens,
                COUNT(CASE WHEN status = 'sold' THEN 1 END) as total_sold_tokens,
                SUM(purchase_amount) as total_investment,
                SUM(CASE WHEN status = 'sold' THEN profit ELSE 0 END) as total_profit_realized
              FROM alpha_tokens";
$result = $conn->query($stats_sql);

if ($result && $row = $result->fetch_assoc()) {
  $total_users_with_tokens = $row['total_users_with_tokens'];
  $total_active_tokens = $row['total_active_tokens'];
  $total_sold_tokens = $row['total_sold_tokens'];
  $total_investment = $row['total_investment'];
  $total_profit_realized = $row['total_profit_realized'];
}

// Calculate unrealized profit for active tokens
$active_tokens_sql = "SELECT id, user_id, purchase_date, purchase_amount FROM alpha_tokens WHERE status = 'active'";
$active_result = $conn->query($active_tokens_sql);

while ($token = $active_result->fetch_assoc()) {
  $purchase_date = new DateTime($token['purchase_date']);
  $current_date = new DateTime();
  $interval = $purchase_date->diff($current_date);
  $days_held = max(1, $interval->days);

  $token_value = $token['purchase_amount'];
  for ($i = 0; $i < $days_held; $i++) {
    $token_value += $token_value * 0.065; // 6.5% daily return
  }

  $profit = $token_value - $token['purchase_amount'];
  $total_current_value += $token_value;
  $total_profit_unrealized += $profit;
}

// Get most recent token transactions
$recent_transactions_sql = "SELECT t.*, u.username 
                          FROM alpha_tokens t
                          JOIN users u ON t.user_id = u.id
                          ORDER BY 
                            CASE 
                              WHEN t.status = 'sold' THEN t.sold_date 
                              ELSE t.purchase_date 
                            END DESC
                          LIMIT 10";
$recent_result = $conn->query($recent_transactions_sql);
$recent_transactions = [];

while ($row = $recent_result->fetch_assoc()) {
  $recent_transactions[] = $row;
}

// Get top users by profit
$top_users_sql = "SELECT u.id, u.username, 
                    COUNT(t.id) as total_tokens,
                    SUM(CASE WHEN t.status = 'active' THEN 1 ELSE 0 END) as active_tokens,
                    SUM(CASE WHEN t.status = 'sold' THEN t.profit ELSE 0 END) as realized_profit
                  FROM users u
                  JOIN alpha_tokens t ON u.id = t.user_id
                  GROUP BY u.id
                  ORDER BY realized_profit DESC
                  LIMIT 5";
$top_users_result = $conn->query($top_users_sql);
$top_users = [];

while ($row = $top_users_result->fetch_assoc()) {
  // Calculate unrealized profit for each user
  $user_unrealized_sql = "SELECT id, purchase_date, purchase_amount 
                         FROM alpha_tokens 
                         WHERE user_id = ? AND status = 'active'";
  $stmt = $conn->prepare($user_unrealized_sql);
  $stmt->bind_param("i", $row['id']);
  $stmt->execute();
  $user_tokens_result = $stmt->get_result();

  $unrealized_profit = 0;

  while ($token = $user_tokens_result->fetch_assoc()) {
    $purchase_date = new DateTime($token['purchase_date']);
    $current_date = new DateTime();
    $interval = $purchase_date->diff($current_date);
    $days_held = max(1, $interval->days);

    $token_value = $token['purchase_amount'];
    for ($i = 0; $i < $days_held; $i++) {
      $token_value += $token_value * 0.065;
    }

    $unrealized_profit += ($token_value - $token['purchase_amount']);
  }

  $row['unrealized_profit'] = $unrealized_profit;
  $row['total_profit'] = $row['realized_profit'] + $unrealized_profit;

  $top_users[] = $row;
}

// Handle token adjustments if form submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  $action = $_POST['action'];

  if ($action === 'adjust_token_rate') {
    $new_rate = floatval($_POST['daily_rate']);
    if ($new_rate > 0 && $new_rate <= 10) {
      // Update configuration in database or file
      $update_rate_sql = "UPDATE system_config SET value = ? WHERE config_key = 'token_daily_rate'";
      $stmt = $conn->prepare($update_rate_sql);
      $stmt->bind_param("d", $new_rate);

      if ($stmt->execute()) {
        $_SESSION['success_message'] = "Daily token rate updated successfully to {$new_rate}%";
      } else {
        $_SESSION['error_message'] = "Failed to update token rate: " . $conn->error;
      }
    } else {
      $_SESSION['error_message'] = "Invalid rate value. Please enter a value between 0.1 and 10.";
    }
  } elseif ($action === 'manual_token_adjustment') {
    $user_id = intval($_POST['user_id']);
    $token_count = intval($_POST['token_count']);
    $adjustment_type = $_POST['adjustment_type'];
    $reason = $_POST['reason'];

    if ($token_count <= 0) {
      $_SESSION['error_message'] = "Please enter a valid token count.";
    } else {
      $conn->begin_transaction();
      try {
        if ($adjustment_type === 'add') {
          // Add tokens to user
          $current_time = date('Y-m-d H:i:s');
          $token_add_sql = "INSERT INTO alpha_tokens (user_id, purchase_date, purchase_amount, status, admin_notes) 
                           VALUES (?, ?, 1000.00, 'active', ?)";
          $stmt = $conn->prepare($token_add_sql);

          for ($i = 0; $i < $token_count; $i++) {
            $stmt->bind_param("iss", $user_id, $current_time, $reason);
            $stmt->execute();
          }

          $_SESSION['success_message'] = "Successfully added {$token_count} tokens to user ID: {$user_id}";
        } elseif ($adjustment_type === 'remove') {
          // Check if user has enough active tokens
          $check_tokens_sql = "SELECT COUNT(*) as count FROM alpha_tokens WHERE user_id = ? AND status = 'active'";
          $stmt = $conn->prepare($check_tokens_sql);
          $stmt->bind_param("i", $user_id);
          $stmt->execute();
          $result = $stmt->get_result();
          $row = $result->fetch_assoc();

          if ($row['count'] < $token_count) {
            throw new Exception("User only has {$row['count']} active tokens. Cannot remove {$token_count} tokens.");
          }

          // Remove tokens (newest first)
          $remove_tokens_sql = "UPDATE alpha_tokens SET 
                               status = 'revoked', 
                               sold_date = NOW(),
                               admin_notes = ?
                               WHERE user_id = ? AND status = 'active' 
                               ORDER BY purchase_date DESC
                               LIMIT ?";
          $stmt = $conn->prepare($remove_tokens_sql);
          $stmt->bind_param("sii", $reason, $user_id, $token_count);
          $stmt->execute();

          $_SESSION['success_message'] = "Successfully removed {$token_count} tokens from user ID: {$user_id}";
        }

        // Log the admin action
        $admin_id = $_SESSION['user_id'];
        $action_description = "{$adjustment_type} {$token_count} tokens for user ID: {$user_id}. Reason: {$reason}";
        $log_sql = "INSERT INTO admin_logs (admin_id, action_type, description) VALUES (?, 'token_adjustment', ?)";
        $stmt = $conn->prepare($log_sql);
        $stmt->bind_param("is", $admin_id, $action_description);
        $stmt->execute();

        $conn->commit();
      } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
      }
    }
  }

  // Redirect to refresh the page and avoid form resubmission
  header("Location: token-management.php");
  exit();
}

// Get current token rate from configuration
$rate_sql = "SELECT value FROM system_config WHERE config_key = 'token_daily_rate'";
$rate_result = $conn->query($rate_sql);
$daily_rate = 6.5; // Default value

if ($rate_result && $row = $rate_result->fetch_assoc()) {
  $daily_rate = floatval($row['value']);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <?php include 'includes/admin-head.php'; ?>
  <title>Token Management - Admin Dashboard</title>
</head>

<body class="bg-gray-900 text-white font-sans min-h-screen flex flex-col">
  <?php include 'includes/admin-navbar.php'; ?>
  <?php include 'includes/admin-sidebar.php'; ?>

  <!-- Main Content -->
  <main class="flex-grow py-6 md:ml-64">
    <div class="container mx-auto px-4 sm:px-6 lg:px-8">
      <!-- Header Section -->
      <div class="mb-8 flex justify-between items-center">
        <div>
          <h1 class="text-2xl font-bold">AlphaMiner Token Management</h1>
          <p class="text-gray-400">Monitor and manage token operations across the platform</p>
        </div>
        <div>
          <button type="button" data-modal-target="adjustRateModal" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
            <i class="fas fa-sliders-h mr-2"></i>Adjust Token Rate
          </button>
        </div>
      </div>

      <!-- Alert Messages -->
      <?php if (isset($_SESSION['success_message'])): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded">
          <div class="flex">
            <div class="py-1"><i class="fas fa-check-circle text-green-500"></i></div>
            <div class="ml-3">
              <p class="text-sm"><?php echo $_SESSION['success_message']; ?></p>
            </div>
          </div>
        </div>
        <?php unset($_SESSION['success_message']); ?>
      <?php endif; ?>

      <?php if (isset($_SESSION['error_message'])): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded">
          <div class="flex">
            <div class="py-1"><i class="fas fa-exclamation-circle text-red-500"></i></div>
            <div class="ml-3">
              <p class="text-sm"><?php echo $_SESSION['error_message']; ?></p>
            </div>
          </div>
        </div>
        <?php unset($_SESSION['error_message']); ?>
      <?php endif; ?>

      <!-- Stats Overview -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <!-- Total Tokens -->
        <div class="bg-gray-800 rounded-lg p-6 border border-gray-700">
          <div class="flex justify-between items-start">
            <div>
              <p class="text-gray-400 text-sm">Total Tokens</p>
              <h3 class="text-2xl font-bold"><?php echo number_format($total_active_tokens); ?> Active</h3>
              <p class="text-gray-500 text-sm flex items-center mt-1">
                <i class="fas fa-coins mr-1"></i> <?php echo number_format($total_sold_tokens); ?> Sold
              </p>
            </div>
            <div class="h-10 w-10 rounded-lg bg-indigo-500 flex items-center justify-center">
              <i class="fas fa-microchip text-white"></i>
            </div>
          </div>
        </div>

        <!-- Total Investment -->
        <div class="bg-gray-800 rounded-lg p-6 border border-gray-700">
          <div class="flex justify-between items-start">
            <div>
              <p class="text-gray-400 text-sm">Total Investment</p>
              <h3 class="text-2xl font-bold">Rs:<?php echo number_format($total_investment, 2); ?></h3>
              <p class="text-gray-500 text-sm flex items-center mt-1">
                <i class="fas fa-users mr-1"></i> <?php echo number_format($total_users_with_tokens); ?> Users
              </p>
            </div>
            <div class="h-10 w-10 rounded-lg bg-blue-500 flex items-center justify-center">
              <i class="fas fa-money-bill-wave text-white"></i>
            </div>
          </div>
        </div>

        <!-- Total Value -->
        <div class="bg-gray-800 rounded-lg p-6 border border-gray-700">
          <div class="flex justify-between items-start">
            <div>
              <p class="text-gray-400 text-sm">Total Value</p>
              <h3 class="text-2xl font-bold">Rs:<?php echo number_format($total_current_value, 2); ?></h3>
              <p class="text-gray-500 text-sm flex items-center mt-1">
                <i class="fas fa-chart-line mr-1"></i> <?php echo number_format($daily_rate, 2); ?>% Daily Rate
              </p>
            </div>
            <div class="h-10 w-10 rounded-lg bg-green-500 flex items-center justify-center">
              <i class="fas fa-sack-dollar text-white"></i>
            </div>
          </div>
        </div>

        <!-- Total Profit -->
        <div class="bg-gray-800 rounded-lg p-6 border border-gray-700">
          <div class="flex justify-between items-start">
            <div>
              <p class="text-gray-400 text-sm">Total Profit</p>
              <h3 class="text-2xl font-bold">Rs:<?php echo number_format($total_profit_realized + $total_profit_unrealized, 2); ?></h3>
              <div class="flex text-sm mt-1">
                <span class="text-green-500 mr-2"><i class="fas fa-check-circle mr-1"></i>Rs:<?php echo number_format($total_profit_realized, 2); ?></span>
                <span class="text-blue-400"><i class="fas fa-clock mr-1"></i>Rs:<?php echo number_format($total_profit_unrealized, 2); ?></span>
              </div>
            </div>
            <div class="h-10 w-10 rounded-lg bg-yellow-500 flex items-center justify-center">
              <i class="fas fa-hand-holding-usd text-white"></i>
            </div>
          </div>
        </div>
      </div>

      <!-- Token Operations and User Search Section -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Token Operations -->
        <div class="bg-gray-800 rounded-lg p-6 border border-gray-700">
          <h2 class="text-xl font-semibold mb-4">Token Operations</h2>
          <form action="token-management.php" method="POST">
            <input type="hidden" name="action" value="adjust_token_rate">
            <div class="mb-4">
              <label class="block text-gray-400 text-sm mb-1" for="daily_rate">Daily Return Rate (%)</label>
              <input type="number" id="daily_rate" name="daily_rate" value="<?php echo $daily_rate; ?>" step="0.1" min="0.1" max="10" required
                class="w-full bg-gray-700 border border-gray-600 rounded-lg py-2 px-3 text-white focus:outline-none focus:border-indigo-500">
              <p class="text-gray-500 text-xs mt-1">Current rate: <?php echo $daily_rate; ?>% daily return</p>
            </div>
            <div class="flex justify-end space-x-3">
              <button type="button" data-modal-close="adjustRateModal" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                Cancel
              </button>
              <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                Save Changes
              </button>
            </div>
          </form>
        </div>
      </div>

      <?php include 'includes/admin-footer.php'; ?>

      <script>
        // Modal functionality
        document.addEventListener('DOMContentLoaded', function() {
          // Open modals
          document.querySelectorAll('[data-modal-target]').forEach(button => {
            button.addEventListener('click', () => {
              const modalId = button.getAttribute('data-modal-target');
              document.getElementById(modalId).classList.remove('hidden');
            });
          });

          // Close modals
          document.querySelectorAll('[data-modal-close]').forEach(button => {
            button.addEventListener('click', () => {
              const modalId = button.getAttribute('data-modal-close');
              document.getElementById(modalId).classList.add('hidden');
            });
          });

          // User search functionality
          const searchInput = document.getElementById('search_user');
          const searchResults = document.getElementById('search_results');

          searchInput.addEventListener('keyup', function(e) {
            const query = this.value.trim();

            if (query.length < 3) {
              searchResults.innerHTML = `
            <div class="text-center py-8 text-gray-500">
              <i class="fas fa-user-search text-5xl mb-3"></i>
              <p>Enter at least 3 characters to search</p>
            </div>
          `;
              return;
            }

            // Show loading indicator
            searchResults.innerHTML = `
          <div class="text-center py-8 text-gray-500">
            <i class="fas fa-spinner fa-spin text-3xl mb-3"></i>
            <p>Searching...</p>
          </div>
        `;

            // Fetch results from server
            fetch(`ajax/search-users.php?query=${encodeURIComponent(query)}`)
              .then(response => response.json())
              .then(data => {
                if (data.length === 0) {
                  searchResults.innerHTML = `
                <div class="text-center py-8 text-gray-500">
                  <i class="fas fa-user-slash text-3xl mb-3"></i>
                  <p>No users found</p>
                </div>
              `;
                } else {
                  let resultsHtml = `<div class="divide-y divide-gray-700">`;

                  data.forEach(user => {
                    resultsHtml += `
                  <div class="py-3 flex justify-between items-center">
                    <div>
                      <div class="font-medium">${user.username}</div>
                      <div class="text-sm text-gray-400">${user.email}</div>
                    </div>
                    <a href="user-details.php?id=${user.id}" class="inline-flex items-center px-3 py-1 border border-transparent text-sm leading-4 font-medium rounded-md text-indigo-700 bg-indigo-100 hover:bg-indigo-200">
                      View
                    </a>
                  </div>
                `;
                  });

                  resultsHtml += `</div>`;
                  searchResults.innerHTML = resultsHtml;
                }
              })
              .catch(error => {
                searchResults.innerHTML = `
              <div class="text-center py-8 text-red-500">
                <i class="fas fa-exclamation-circle text-3xl mb-3"></i>
                <p>Error searching users</p>
              </div>
            `;
                console.error('Search error:', error);
              });
          });
        });
      </script>
</body>

</html>-management.php" method="POST">
<input type="hidden" name="action" value="manual_token_adjustment">
<div class="mb-3">
  <label class="block text-gray-400 text-sm mb-1" for="user_id">User ID</label>
  <input type="number" id="user_id" name="user_id" required
    class="w-full bg-gray-700 border border-gray-600 rounded-lg py-2 px-3 text-white focus:outline-none focus:border-indigo-500">
</div>
<div class="mb-3">
  <label class="block text-gray-400 text-sm mb-1" for="token_count">Token Count</label>
  <input type="number" id="token_count" name="token_count" min="1" required
    class="w-full bg-gray-700 border border-gray-600 rounded-lg py-2 px-3 text-white focus:outline-none focus:border-indigo-500">
</div>
<div class="mb-3">
  <label class="block text-gray-400 text-sm mb-1" for="adjustment_type">Action</label>
  <select id="adjustment_type" name="adjustment_type" required
    class="w-full bg-gray-700 border border-gray-600 rounded-lg py-2 px-3 text-white focus:outline-none focus:border-indigo-500">
    <option value="add">Add Tokens</option>
    <option value="remove">Remove Tokens</option>
  </select>
</div>
<div class="mb-4">
  <label class="block text-gray-400 text-sm mb-1" for="reason">Reason for Adjustment</label>
  <textarea id="reason" name="reason" required
    class="w-full bg-gray-700 border border-gray-600 rounded-lg py-2 px-3 text-white focus:outline-none focus:border-indigo-500"
    rows="2"></textarea>
</div>
<button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2 px-4 rounded-lg">
  Submit Token Adjustment
</button>
</form>
</div>

<!-- User Search and Quick Stats -->
<div class="bg-gray-800 rounded-lg p-6 border border-gray-700">
  <h2 class="text-xl font-semibold mb-4">User Lookup</h2>
  <div class="mb-4">
    <div class="relative">
      <input type="text" id="search_user" placeholder="Search by username or email"
        class="w-full bg-gray-700 border border-gray-600 rounded-lg py-2 pl-10 pr-3 text-white focus:outline-none focus:border-indigo-500">
      <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
        <i class="fas fa-search text-gray-400"></i>
      </div>
    </div>
  </div>
  <div id="search_results" class="mt-4">
    <div class="text-center py-8 text-gray-500">
      <i class="fas fa-user-search text-5xl mb-3"></i>
      <p>Enter a username or email to search</p>
    </div>
  </div>
</div>
</div>

<!-- Recent Transactions -->
<div class="mb-8">
  <h2 class="text-xl font-semibold mb-4">Recent Transactions</h2>
  <div class="overflow-x-auto bg-gray-800 rounded-lg border border-gray-700">
    <table class="min-w-full divide-y divide-gray-700">
      <thead>
        <tr>
          <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Token ID</th>
          <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">User</th>
          <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Type</th>
          <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Date</th>
          <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Amount</th>
          <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Profit</th>
          <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-700">
        <?php foreach ($recent_transactions as $transaction): ?>
          <tr>
            <td class="px-6 py-4 whitespace-nowrap">
              <span class="px-2 py-1 rounded-lg <?php echo $transaction['status'] == 'active' ? 'bg-blue-900' : 'bg-red-900'; ?> text-white text-xs">
                AMR-<?php echo $transaction['id']; ?>
              </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm">
              <a href="user-details.php?id=<?php echo $transaction['user_id']; ?>" class="text-indigo-400 hover:underline">
                <?php echo htmlspecialchars($transaction['username']); ?> (#<?php echo $transaction['user_id']; ?>)
              </a>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <?php if ($transaction['status'] == 'active'): ?>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                  Active
                </span>
              <?php elseif ($transaction['status'] == 'sold'): ?>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                  Sold
                </span>
              <?php elseif ($transaction['status'] == 'revoked'): ?>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                  Revoked
                </span>
              <?php endif; ?>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm">
              <?php
              $date = $transaction['status'] == 'active'
                ? date('M d, Y H:i', strtotime($transaction['purchase_date']))
                : date('M d, Y H:i', strtotime($transaction['sold_date']));
              echo $date;
              ?>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm">
              Rs:<?php echo number_format($transaction['purchase_amount'], 2); ?>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm">
              <?php if ($transaction['status'] == 'sold'): ?>
                <span class="text-green-500">
                  +Rs:<?php echo number_format($transaction['profit'], 2); ?>
                </span>
              <?php else: ?>
                <span class="text-gray-500">--</span>
              <?php endif; ?>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-400">
              <button class="text-indigo-400 hover:text-indigo-300 mr-3" title="View Details">
                <i class="fas fa-eye"></i>
              </button>
              <?php if ($transaction['status'] == 'active'): ?>
                <button class="text-red-400 hover:text-red-300" title="Revoke Token">
                  <i class="fas fa-ban"></i>
                </button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="mt-4 text-right">
    <a href="token-transactions.php" class="text-indigo-400 hover:text-indigo-300">
      View All Transactions <i class="fas fa-arrow-right ml-1"></i>
    </a>
  </div>
</div>

<!-- Top Users -->
<div>
  <h2 class="text-xl font-semibold mb-4">Top Users by Profit</h2>
  <div class="overflow-x-auto bg-gray-800 rounded-lg border border-gray-700">
    <table class="min-w-full divide-y divide-gray-700">
      <thead>
        <tr>
          <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">User</th>
          <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Total Tokens</th>
          <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Active Tokens</th>
          <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Realized Profit</th>
          <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Unrealized Profit</th>
          <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Total Profit</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-700">
        <?php foreach ($top_users as $user): ?>
          <tr>
            <td class="px-6 py-4 whitespace-nowrap">
              <a href="user-details.php?id=<?php echo $user['id']; ?>" class="text-indigo-400 hover:underline">
                <?php echo htmlspecialchars($user['username']); ?> (#<?php echo $user['id']; ?>)
              </a>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm">
              <?php echo number_format($user['total_tokens']); ?>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm">
              <?php echo number_format($user['active_tokens']); ?>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-green-500">
              +Rs:<?php echo number_format($user['realized_profit'], 2); ?>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-blue-400">
              +Rs:<?php echo number_format($user['unrealized_profit'], 2); ?>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-green-500 font-medium">
              +Rs:<?php echo number_format($user['total_profit'], 2); ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="mt-4 text-right">
    <a href="user-analytics.php" class="text-indigo-400 hover:text-indigo-300">
      View All Users <i class="fas fa-arrow-right ml-1"></i>
    </a>
  </div>
</div>
</div>
</main>

<!-- Adjust Token Rate Modal -->
<div id="adjustRateModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center hidden">
  <div class="bg-gray-800 rounded-lg p-6 w-full max-w-md">
    <div class="flex justify-between items-center mb-4">
      <h3 class="text-xl font-semibold">Adjust Token Daily Rate</h3>
      <button type="button" data-modal-close="adjustRateModal" class="text-gray-400 hover:text-white">
        <i class="fas fa-times"></i>
      </button>
    </div>
    <form action="token