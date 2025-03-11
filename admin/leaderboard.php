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

// Process manual leaderboard update if requested
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_leaderboard'])) {
  try {
    // Call the stored procedure to update leaderboards
    $conn->query("CALL update_leaderboards()");

    $_SESSION['admin_message'] = "Leaderboard rankings have been successfully updated.";
    $_SESSION['admin_message_type'] = "success";
  } catch (Exception $e) {
    $_SESSION['admin_message'] = "Error updating leaderboard: " . $e->getMessage();
    $_SESSION['admin_message_type'] = "error";
  }

  // Redirect to refresh page
  header("Location: " . $_SERVER['PHP_SELF']);
  exit();
}

// Process bonus distribution if requested
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['distribute_bonus'])) {
  $month = $_POST['bonus_month'] ?? date('Y-m');

  try {
    // Call the stored procedure to distribute bonuses
    $stmt = $conn->prepare("CALL distribute_monthly_bonuses(?)");
    $stmt->bind_param("s", $month);
    $stmt->execute();

    $_SESSION['admin_message'] = "Bonuses for $month have been successfully distributed.";
    $_SESSION['admin_message_type'] = "success";
  } catch (Exception $e) {
    $_SESSION['admin_message'] = "Error distributing bonuses: " . $e->getMessage();
    $_SESSION['admin_message_type'] = "error";
  }

  // Redirect to refresh page
  header("Location: " . $_SERVER['PHP_SELF']);
  exit();
}

// Get current month's leaderboard
$current_month = date('Y-m');
$monthly_leaderboard_query = "SELECT 
                                ld.rank, 
                                ld.user_id, 
                                u.full_name, 
                                ld.total_deposited, 
                                ld.deposit_count
                              FROM 
                                leaderboard_deposits ld
                              JOIN 
                                users u ON ld.user_id = u.id
                              WHERE 
                                ld.period = ?
                              ORDER BY 
                                ld.rank
                              LIMIT 50";

$stmt = $conn->prepare($monthly_leaderboard_query);
$stmt->bind_param("s", $current_month);
$stmt->execute();
$monthly_result = $stmt->get_result();

// Get all-time leaderboard
$alltime_leaderboard_query = "SELECT 
                                ld.rank, 
                                ld.user_id, 
                                u.full_name, 
                                ld.total_deposited, 
                                ld.deposit_count
                              FROM 
                                leaderboard_deposits ld
                              JOIN 
                                users u ON ld.user_id = u.id
                              WHERE 
                                ld.period = 'all-time'
                              ORDER BY 
                                ld.rank
                              LIMIT 50";

$alltime_result = $conn->query($alltime_leaderboard_query);

// Check if bonus has been distributed for current month
$bonus_check_query = "SELECT COUNT(*) as count FROM leaderboard_bonuses WHERE bonus_month = ?";
$stmt = $conn->prepare($bonus_check_query);
$stmt->bind_param("s", $current_month);
$stmt->execute();
$bonus_result = $stmt->get_result();
$bonus_row = $bonus_result->fetch_assoc();
$bonus_distributed = ($bonus_row['count'] > 0);

// Get bonus distribution history
$history_query = "SELECT 
                    lb.id, 
                    lb.user_id, 
                    u.full_name, 
                    lb.bonus_amount, 
                    lb.rank_position, 
                    lb.bonus_month, 
                    lb.paid_at as distributed_at
                  FROM 
                    leaderboard_bonuses lb
                  LEFT JOIN 
                    users u ON lb.user_id = u.id
                  ORDER BY 
                    lb.paid_at DESC
                  LIMIT 100";
$history_result = $conn->query($history_query);
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Deposit Leaderboard - Admin Dashboard</title>
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
          <div class="h-8 w-8 rounded-full bg-yellow-500 flex items-center justify-center text-black font-bold">
            <?php echo strtoupper(substr($admin_name, 0, 1)); ?>
          </div>
          <span class="ml-2 hidden sm:inline-block"><?php echo htmlspecialchars($admin_name); ?></span>
        </div>
      </div>
    </header>

    <!-- Mobile Sidebar -->
    <div id="mobile-sidebar" class="fixed inset-0 bg-gray-900 bg-opacity-90 z-50 md:hidden transform -translate-x-full transition-transform duration-300">
      <!-- Mobile menu content (same as sidebar) -->
    </div>

    <!-- Main Content Area -->
    <main class="flex-1 overflow-y-auto p-4 bg-gray-900">
      <!-- Page Title -->
      <div class="mb-6">
        <h2 class="text-2xl font-bold">Deposit Leaderboard Management</h2>
        <p class="text-gray-400">Manage deposit leaderboard rankings and bonus distribution</p>
      </div>

      <!-- Flash Message -->
      <?php if (isset($_SESSION['admin_message'])): ?>
        <div class="mb-6 p-4 rounded-md <?php echo ($_SESSION['admin_message_type'] === 'success') ? 'bg-green-800 text-green-100' : 'bg-red-800 text-red-100'; ?>">
          <?php
          echo $_SESSION['admin_message'];
          unset($_SESSION['admin_message']);
          unset($_SESSION['admin_message_type']);
          ?>
        </div>
      <?php endif; ?>

      <!-- Control Cards -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <!-- Update Leaderboard -->
        <div class="bg-gray-800 rounded-lg p-6 border border-gray-700 shadow-lg">
          <h3 class="text-lg font-bold mb-4">Update Leaderboard Rankings</h3>
          <p class="text-gray-400 mb-4">Manually update the leaderboard rankings based on current deposit data.</p>

          <form method="POST" onsubmit="return confirm('Are you sure you want to update the leaderboard rankings?');">
            <button type="submit" name="update_leaderboard" class="px-4 py-2 bg-yellow-600 text-white rounded-md hover:bg-yellow-700 transition duration-200">
              <i class="fas fa-sync-alt mr-2"></i> Update Rankings
            </button>
          </form>
        </div>

        <!-- Distribute Bonuses -->
        <div class="bg-gray-800 rounded-lg p-6 border border-gray-700 shadow-lg">
          <h3 class="text-lg font-bold mb-4">Distribute Monthly Bonuses</h3>
          <p class="text-gray-400 mb-4">
            <?php if ($bonus_distributed): ?>
              Bonuses for <?php echo date('F Y', strtotime($current_month)); ?> have already been distributed.
            <?php else: ?>
              Distribute bonuses to top 3 depositors: Rs:5,000 (1st), Rs:3,000 (2nd), Rs:2,000 (3rd).
            <?php endif; ?>
          </p>

          <form method="POST" onsubmit="return confirm('Are you sure you want to distribute bonuses? This cannot be undone.');">
            <div class="flex items-end space-x-4">
              <div>
                <label for="bonus_month" class="block text-sm font-medium text-gray-400 mb-2">Select Month</label>
                <input type="month" id="bonus_month" name="bonus_month" class="bg-gray-700 border border-gray-600 rounded-md text-white px-3 py-2" value="<?php echo $current_month; ?>">
              </div>

              <button type="submit" name="distribute_bonus" class="px-4 py-2 bg-yellow-600 text-white rounded-md hover:bg-yellow-700 transition duration-200" <?php echo $bonus_distributed ? 'disabled' : ''; ?>>
                <i class="fas fa-gift mr-2"></i> Distribute Bonuses
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Tabs -->
      <div class="mb-6">
        <div class="flex border-b border-gray-700">
          <button class="py-2 px-4 font-medium text-yellow-500 border-b-2 border-yellow-500 focus:outline-none tab-btn active" data-tab="monthly">
            Monthly Leaderboard
          </button>
          <button class="py-2 px-4 font-medium text-gray-400 hover:text-yellow-500 focus:outline-none tab-btn" data-tab="alltime">
            All-Time Leaderboard
          </button>
          <button class="py-2 px-4 font-medium text-gray-400 hover:text-yellow-500 focus:outline-none tab-btn" data-tab="history">
            Bonus History
          </button>
        </div>
      </div>

      <!-- Monthly Leaderboard Tab -->
      <div id="monthly-tab" class="tab-content">
        <div class="bg-gray-800 rounded-lg shadow-lg overflow-hidden border border-gray-700">
          <div class="p-6 border-b border-gray-700 flex justify-between items-center">
            <h3 class="text-lg font-bold">Monthly Top Depositors (<?php echo date('F Y', strtotime($current_month)); ?>)</h3>
            <span class="text-sm text-gray-400">Last updated: <?php echo date('M d, Y H:i'); ?></span>
          </div>

          <div class="overflow-x-auto">
            <table class="w-full">
              <thead class="bg-gray-700">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Rank</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">User</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Total Deposited</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Deposit Count</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Bonus Status</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-700">
                <?php if ($monthly_result && $monthly_result->num_rows > 0): ?>
                  <?php while ($row = $monthly_result->fetch_assoc()): ?>
                    <tr class="<?php echo ($row['rank'] <= 3) ? 'bg-yellow-900 bg-opacity-20 hover:bg-yellow-900 hover:bg-opacity-30' : 'hover:bg-gray-700'; ?>">
                      <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                          <span class="<?php echo ($row['rank'] <= 3) ? 'text-yellow-500 font-bold' : 'text-gray-300'; ?>">
                            <?php echo $row['rank']; ?>
                            <?php if ($row['rank'] <= 3): ?>
                              <i class="fas fa-trophy ml-1 text-yellow-500"></i>
                            <?php endif; ?>
                          </span>
                        </div>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                          <div class="h-8 w-8 rounded-full bg-gray-700 flex items-center justify-center text-sm font-medium mr-3">
                            <?php echo strtoupper(substr($row['full_name'] ?? 'U', 0, 1)); ?>
                          </div>
                          <div>
                            <div class="text-sm font-medium"><?php echo htmlspecialchars($row['full_name'] ?? 'Unknown'); ?></div>
                            <div class="text-xs text-gray-400">ID: <?php echo $row['user_id']; ?></div>
                          </div>
                        </div>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap">
                        <span class="text-sm font-medium"><?php echo 'Rs:' . number_format($row['total_deposited'], 2); ?></span>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap">
                        <span class="text-sm"><?php echo $row['deposit_count']; ?></span>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap">
                        <?php if ($row['rank'] <= 3): ?>
                          <?php
                          $bonus_amount = 0;
                          switch ($row['rank']) {
                            case 1:
                              $bonus_amount = 5000;
                              break;
                            case 2:
                              $bonus_amount = 3000;
                              break;
                            case 3:
                              $bonus_amount = 2000;
                              break;
                          }
                          ?>
                          <?php if ($bonus_distributed): ?>
                            <span class="px-2 py-1 text-xs rounded-full bg-green-900 text-green-400">
                              Received Rs:<?php echo number_format($bonus_amount, 0); ?>
                            </span>
                          <?php else: ?>
                            <span class="px-2 py-1 text-xs rounded-full bg-yellow-900 text-yellow-400">
                              Eligible for Rs:<?php echo number_format($bonus_amount, 0); ?>
                            </span>
                          <?php endif; ?>
                        <?php else: ?>
                          <span class="text-gray-400">Not eligible</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="5" class="px-6 py-4 text-center text-gray-400">No data available for this month.</td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- All-Time Leaderboard Tab -->
      <div id="alltime-tab" class="tab-content hidden">
        <div class="bg-gray-800 rounded-lg shadow-lg overflow-hidden border border-gray-700">
          <div class="p-6 border-b border-gray-700 flex justify-between items-center">
            <h3 class="text-lg font-bold">All-Time Top Depositors</h3>
            <span class="text-sm text-gray-400">Last updated: <?php echo date('M d, Y H:i'); ?></span>
          </div>

          <div class="overflow-x-auto">
            <table class="w-full">
              <thead class="bg-gray-700">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Rank</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">User</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Total Deposited</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Deposit Count</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-700">
                <?php if ($alltime_result && $alltime_result->num_rows > 0): ?>
                  <?php while ($row = $alltime_result->fetch_assoc()): ?>
                    <tr class="<?php echo ($row['rank'] <= 3) ? 'bg-gray-700 bg-opacity-50 hover:bg-gray-700' : 'hover:bg-gray-700'; ?>">
                      <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                          <span class="<?php echo ($row['rank'] <= 3) ? 'text-yellow-500 font-bold' : 'text-gray-300'; ?>">
                            <?php echo $row['rank']; ?>
                            <?php if ($row['rank'] <= 3): ?>
                              <i class="fas fa-trophy ml-1 text-yellow-500"></i>
                            <?php endif; ?>
                          </span>
                        </div>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                          <div class="h-8 w-8 rounded-full bg-gray-700 flex items-center justify-center text-sm font-medium mr-3">
                            <?php echo strtoupper(substr($row['full_name'] ?? 'U', 0, 1)); ?>
                          </div>
                          <div>
                            <div class="text-sm font-medium"><?php echo htmlspecialchars($row['full_name'] ?? 'Unknown'); ?></div>
                            <div class="text-xs text-gray-400">ID: <?php echo $row['user_id']; ?></div>
                          </div>
                        </div>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap">
                        <span class="text-sm font-medium"><?php echo 'Rs:' . number_format($row['total_deposited'], 2); ?></span>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap">
                        <span class="text-sm"><?php echo $row['deposit_count']; ?></span>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="4" class="px-6 py-4 text-center text-gray-400">No data available.</td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Bonus History Tab -->
      <div id="history-tab" class="tab-content hidden">
        <div class="bg-gray-800 rounded-lg shadow-lg overflow-hidden border border-gray-700">
          <div class="p-6 border-b border-gray-700">
            <h3 class="text-lg font-bold">Bonus Distribution History</h3>
          </div>

          <div class="overflow-x-auto">
            <table class="w-full">
              <thead class="bg-gray-700">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">ID</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">User</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Bonus Amount</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Rank</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Month</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Distributed At</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-700">
                <?php if ($history_result && $history_result->num_rows > 0): ?>
                  <?php while ($row = $history_result->fetch_assoc()): ?>
                    <tr class="hover:bg-gray-700">
                      <td class="px-6 py-4 whitespace-nowrap">
                        <?php echo $row['id']; ?>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                          <div class="h-8 w-8 rounded-full bg-gray-700 flex items-center justify-center text-sm font-medium mr-3">
                            <?php echo strtoupper(substr($row['full_name'] ?? 'U', 0, 1)); ?>
                          </div>
                          <div>
                            <div class="text-sm font-medium"><?php echo htmlspecialchars($row['full_name'] ?? 'Unknown'); ?></div>
                            <div class="text-xs text-gray-400">ID: <?php echo $row['user_id']; ?></div>
                          </div>
                        </div>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap">
                        <span class="text-sm font-medium text-green-400">Rs:<?php echo number_format($row['bonus_amount'], 2); ?></span>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap">
                        <span class="<?php echo ($row['rank_position'] <= 3) ? 'text-yellow-500 font-bold' : 'text-gray-300'; ?>">
                          #<?php echo $row['rank_position']; ?>
                        </span>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap">
                        <?php echo date('F Y', strtotime($row['bonus_month'] . '-01')); ?>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap">
                        <?php echo date('M d, Y H:i', strtotime($row['distributed_at'])); ?>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="6" class="px-6 py-4 text-center text-gray-400">No bonus distribution history found.</td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </main>
  </div>

  <script>
    // Mobile menu toggle
    const mobileMenuButton = document.getElementById('mobile-menu-button');
    const mobileSidebar = document.getElementById('mobile-sidebar');

    if (mobileMenuButton && mobileSidebar) {
      mobileMenuButton.addEventListener('click', () => {
        mobileSidebar.classList.toggle('-translate-x-full');
      });
    }

    // Tab switching functionality
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');

    tabBtns.forEach(btn => {
      btn.addEventListener('click', () => {
        // Remove active class from all buttons
        tabBtns.forEach(b => {
          b.classList.remove('active', 'text-yellow-500', 'border-yellow-500');
          b.classList.add('text-gray-400');
        });

        // Add active class to clicked button
        btn.classList.add('active', 'text-yellow-500', 'border-yellow-500');
        btn.classList.remove('text-gray-400');

        // Hide all tab contents
        tabContents.forEach(content => {
          content.classList.add('hidden');
        });
        // Show selected tab content
        const tabId = btn.getAttribute('data-tab');
        document.getElementById(`${tabId}-tab`).classList.remove('hidden');
      });
    });

    // Set the first tab as active by default
    if (tabBtns.length > 0 && tabContents.length > 0) {
      tabBtns[0].classList.add('active', 'text-yellow-500', 'border-yellow-500');
      tabBtns[0].classList.remove('text-gray-400');
      tabContents[0].classList.remove('hidden');
    }
  </script>
</body>

</html>