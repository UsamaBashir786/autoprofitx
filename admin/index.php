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

// Dashboard Stats
// Total Users
$users_count = 0;
$users_sql = "SELECT COUNT(*) as count FROM users";
$users_result = $conn->query($users_sql);
if ($users_result) {
  $users_count = $users_result->fetch_assoc()['count'];
}

// Total Deposits
$deposits_count = 0;
$deposits_amount = 0;
$pending_deposits = 0;

// Check if deposits table exists
$check_deposits_sql = "SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'deposits'";
$check_result = $conn->query($check_deposits_sql);
$deposits_table_exists = ($check_result && $check_result->fetch_assoc()['count'] > 0);

if ($deposits_table_exists) {
  $deposits_sql = "SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total FROM deposits";
  $deposits_result = $conn->query($deposits_sql);
  if ($deposits_result) {
    $deposits_data = $deposits_result->fetch_assoc();
    $deposits_count = $deposits_data['count'];
    $deposits_amount = $deposits_data['total'];
  }

  $pending_sql = "SELECT COUNT(*) as count FROM deposits WHERE status = 'pending'";
  $pending_result = $conn->query($pending_sql);
  if ($pending_result) {
    $pending_deposits = $pending_result->fetch_assoc()['count'];
  }
}

// Total Investments/Tickets
$investments_count = 0;
$investments_amount = 0;

// Check if investments table exists
$check_investments_sql = "SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'investments'";
$check_inv_result = $conn->query($check_investments_sql);
$investments_table_exists = ($check_inv_result && $check_inv_result->fetch_assoc()['count'] > 0);

if ($investments_table_exists) {
  $investments_sql = "SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total FROM investments";
  $investments_result = $conn->query($investments_sql);
  if ($investments_result) {
    $investments_data = $investments_result->fetch_assoc();
    $investments_count = $investments_data['count'];
    $investments_amount = $investments_data['total'];
  }
}

// Recent Users
$recent_users = [];
$recent_users_sql = "SELECT id, full_name, email, phone, registration_date, status FROM users ORDER BY registration_date DESC LIMIT 5";
$recent_users_result = $conn->query($recent_users_sql);
if ($recent_users_result) {
  while ($row = $recent_users_result->fetch_assoc()) {
    $recent_users[] = $row;
  }
}

// Recent Deposits
$recent_deposits = [];
if ($deposits_table_exists) {
  $recent_deposits_sql = "SELECT d.*, u.full_name, u.email FROM deposits d 
                         JOIN users u ON d.user_id = u.id 
                         ORDER BY d.created_at DESC LIMIT 5";
  $recent_deposits_result = $conn->query($recent_deposits_sql);
  if ($recent_deposits_result) {
    while ($row = $recent_deposits_result->fetch_assoc()) {
      $recent_deposits[] = $row;
    }
  }
}

// Create an admin_users table if it doesn't exist (for future admin management)
$check_admin_table_sql = "SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'admin_users'";
$check_admin_result = $conn->query($check_admin_table_sql);
$admin_table_exists = ($check_admin_result && $check_admin_result->fetch_assoc()['count'] > 0);

if (!$admin_table_exists) {
  $create_admin_table_sql = "CREATE TABLE admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'super_admin') DEFAULT 'admin',
    status ENUM('active', 'inactive') DEFAULT 'active',
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  )";

  $conn->query($create_admin_table_sql);

  // Insert a default admin if none exists
  $default_admin_username = 'admin';
  $default_admin_password = password_hash('admin123', PASSWORD_DEFAULT);
  $insert_admin_sql = "INSERT INTO admin_users (username, name, email, password, role) 
                      VALUES ('admin', 'Administrator', 'admin@autoproftx.com', ?, 'super_admin')";
  $admin_stmt = $conn->prepare($insert_admin_sql);
  $admin_stmt->bind_param("s", $default_admin_password);
  $admin_stmt->execute();
  $admin_stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard - AutoProftX</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
          <div class="relative">
            <button id="notifications-btn" class="text-gray-300 hover:text-white relative">
              <i class="fas fa-bell text-xl"></i>
              <?php if ($pending_deposits > 0): ?>
                <span class="absolute -top-2 -right-2 h-5 w-5 rounded-full bg-red-500 flex items-center justify-center text-xs"><?php echo $pending_deposits; ?></span>
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
      <div class="flex flex-col overflow-y-scroll h-full bg-gray-800 w-64 py-8 px-6">
        <div class="flex justify-between items-center mb-8">
          <div class="flex items-center">
            <i class="fas fa-gem text-yellow-500 text-xl mr-2"></i>
            <span class="text-xl font-bold text-yellow-500">AutoProftX</span>
          </div>
          <button id="close-sidebar" class="text-gray-300 hover:text-white">
            <i class="fas fa-times text-xl"></i>
          </button>
        </div>

        <nav class="space-y-2">
          <a href="index.php" class="nav-item active flex items-center px-4 py-3 text-gray-300 hover:text-white rounded transition duration-200">
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
          <a href="investments.php" class="nav-item flex items-center px-4 py-3 text-gray-300 hover:text-white rounded transition duration-200">
            <i class="fas fa-chart-line w-6"></i>
            <span>Investments</span>
          </a>
          <a href="staking.php" class="nav-item flex items-center px-4 py-3 text-gray-300 hover:text-white rounded transition duration-200">
            <i class="fas fa-landmark w-6"></i>
            <span>Staking</span>
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
      <div class="mb-6">
        <h2 class="text-2xl font-bold">Dashboard</h2>
        <p class="text-gray-400">Welcome back, <?php echo htmlspecialchars($admin_name); ?>. Here's what's happening today.</p>
      </div>

      <!-- Stats Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
        <!-- Total Users -->
        <div class="bg-gray-800 rounded-lg p-6 border border-gray-700 shadow-lg transition duration-300 stat-card">
          <div class="flex justify-between">
            <div>
              <p class="text-gray-400 text-sm">Total Users</p>
              <h3 class="text-2xl font-bold mt-1"><?php echo number_format($users_count); ?></h3>
            </div>
            <div class="h-12 w-12 rounded-lg gold-gradient flex items-center justify-center">
              <i class="fas fa-users text-black"></i>
            </div>
          </div>
          <div class="mt-4">
            <a href="users.php" class="text-yellow-500 hover:text-yellow-400 text-sm">
              View all users <i class="fas fa-arrow-right ml-1"></i>
            </a>
          </div>
        </div>

        <!-- Total Deposits -->
        <div class="bg-gray-800 rounded-lg p-6 border border-gray-700 shadow-lg transition duration-300 stat-card">
          <div class="flex justify-between">
            <div>
              <p class="text-gray-400 text-sm">Total Deposits</p>
              <h3 class="text-2xl font-bold mt-1"><?php echo number_format($deposits_amount, 0); ?></h3>
            </div>
            <div class="h-12 w-12 rounded-lg gold-gradient flex items-center justify-center">
              <i class="fas fa-money-bill-wave text-black"></i>
            </div>
          </div>
          <div class="mt-4">
            <a href="deposits.php" class="text-yellow-500 hover:text-yellow-400 text-sm">
              View all deposits <i class="fas fa-arrow-right ml-1"></i>
            </a>
          </div>
        </div>

        <!-- Total Investments -->
        <div class="bg-gray-800 rounded-lg p-6 border border-gray-700 shadow-lg transition duration-300 stat-card">
          <div class="flex justify-between">
            <div>
              <p class="text-gray-400 text-sm">Total Investments</p>
              <h3 class="text-2xl font-bold mt-1"><?php echo number_format($investments_amount, 0); ?></h3>
            </div>
            <div class="h-12 w-12 rounded-lg gold-gradient flex items-center justify-center">
              <i class="fas fa-chart-line text-black"></i>
            </div>
          </div>
          <div class="mt-4">
            <a href="investments.php" class="text-yellow-500 hover:text-yellow-400 text-sm">
              View all investments <i class="fas fa-arrow-right ml-1"></i>
            </a>
          </div>
        </div>

        <!-- Pending Deposits -->
        <div class="bg-gray-800 rounded-lg p-6 border border-gray-700 shadow-lg transition duration-300 stat-card">
          <div class="flex justify-between">
            <div>
              <p class="text-gray-400 text-sm">Pending Deposits</p>
              <h3 class="text-2xl font-bold mt-1"><?php echo number_format($pending_deposits); ?></h3>
            </div>
            <div class="h-12 w-12 rounded-lg <?php echo $pending_deposits > 0 ? 'bg-red-500' : 'bg-green-500'; ?> flex items-center justify-center">
              <i class="fas fa-clock text-black"></i>
            </div>
          </div>
          <div class="mt-4">
            <a href="deposits.php?status=pending" class="text-yellow-500 hover:text-yellow-400 text-sm">
              View pending deposits <i class="fas fa-arrow-right ml-1"></i>
            </a>
          </div>
        </div>
      </div>

      <!-- Charts Section -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Revenue Chart -->
        <div class="bg-gray-800 rounded-lg p-6 border border-gray-700 shadow-lg">
          <h3 class="text-lg font-bold mb-4">Revenue Overview</h3>
          <div class="h-64">
            <canvas id="revenueChart"></canvas>
          </div>
        </div>

        <!-- Users Chart -->
        <div class="bg-gray-800 rounded-lg p-6 border border-gray-700 shadow-lg">
          <h3 class="text-lg font-bold mb-4">User Growth</h3>
          <div class="h-64">
            <canvas id="usersChart"></canvas>
          </div>
        </div>
      </div>

      <!-- Recent Activity Section -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Recent Users -->
        <div class="bg-gray-800 rounded-lg border border-gray-700 shadow-lg">
          <div class="flex justify-between items-center p-6 border-b border-gray-700">
            <h3 class="text-lg font-bold">Recent Users</h3>
            <a href="users.php" class="text-yellow-500 hover:text-yellow-400 text-sm">View All</a>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full">
              <thead class="bg-gray-700">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">User</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Email</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Joined</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Status</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-700">
                <?php if (empty($recent_users)): ?>
                  <tr>
                    <td colspan="4" class="px-6 py-4 text-center text-gray-400">No users found</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($recent_users as $user): ?>
                    <tr class="hover:bg-gray-700">
                      <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                          <div class="h-8 w-8 rounded-full bg-gray-600 flex items-center justify-center text-sm font-medium">
                            <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                          </div>
                          <div class="ml-3">
                            <div class="text-sm font-medium"><?php echo htmlspecialchars($user['full_name']); ?></div>
                          </div>
                        </div>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300"><?php echo htmlspecialchars($user['email']); ?></td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300"><?php echo date('M d, Y', strtotime($user['registration_date'])); ?></td>
                      <td class="px-6 py-4 whitespace-nowrap">
                        <?php if ($user['status'] == 'active'): ?>
                          <span class="px-2 py-1 text-xs rounded-full bg-green-900 text-green-400">Active</span>
                        <?php elseif ($user['status'] == 'inactive'): ?>
                          <span class="px-2 py-1 text-xs rounded-full bg-gray-900 text-gray-400">Inactive</span>
                        <?php elseif ($user['status'] == 'suspended'): ?>
                          <span class="px-2 py-1 text-xs rounded-full bg-red-900 text-red-400">Suspended</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Recent Deposits -->
        <div class="bg-gray-800 rounded-lg border border-gray-700 shadow-lg">
          <div class="flex justify-between items-center p-6 border-b border-gray-700">
            <h3 class="text-lg font-bold">Recent Deposits</h3>
            <a href="deposits.php" class="text-yellow-500 hover:text-yellow-400 text-sm">View All</a>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full">
              <thead class="bg-gray-700">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">User</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Amount</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Date</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Status</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-700">
                <?php if (empty($recent_deposits)): ?>
                  <tr>
                    <td colspan="4" class="px-6 py-4 text-center text-gray-400">No deposits found</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($recent_deposits as $deposit): ?>
                    <tr class="hover:bg-gray-700">
                      <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                          <div class="h-8 w-8 rounded-full bg-gray-600 flex items-center justify-center text-sm font-medium">
                            <?php echo strtoupper(substr($deposit['full_name'], 0, 1)); ?>
                          </div>
                          <div class="ml-3">
                            <div class="text-sm font-medium"><?php echo htmlspecialchars($deposit['full_name']); ?></div>
                            <div class="text-xs text-gray-400"><?php echo htmlspecialchars($deposit['email']); ?></div>
                          </div>
                        </div>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm font-medium"><?php echo number_format($deposit['amount'], 0); ?></td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300"><?php echo date('M d, Y', strtotime($deposit['created_at'])); ?></td>
                      <td class="px-6 py-4 whitespace-nowrap">
                        <?php if ($deposit['status'] == 'pending'): ?>
                          <span class="px-2 py-1 text-xs rounded-full bg-yellow-900 text-yellow-400">Pending</span>
                        <?php elseif ($deposit['status'] == 'approved'): ?>
                          <span class="px-2 py-1 text-xs rounded-full bg-green-900 text-green-400">Approved</span>
                        <?php elseif ($deposit['status'] == 'rejected'): ?>
                          <span class="px-2 py-1 text-xs rounded-full bg-red-900 text-red-400">Rejected</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </main>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Mobile sidebar toggle
      const mobileSidebar = document.getElementById('mobile-sidebar');
      const mobileMenuButton = document.getElementById('mobile-menu-button');
      const closeSidebarButton = document.getElementById('close-sidebar');

      mobileMenuButton.addEventListener('click', function() {
        mobileSidebar.classList.remove('-translate-x-full');
      });

      closeSidebarButton.addEventListener('click', function() {
        mobileSidebar.classList.add('-translate-x-full');
      });

      // Revenue Chart
      const revenueCtx = document.getElementById('revenueChart').getContext('2d');
      const revenueChart = new Chart(revenueCtx, {
        type: 'line',
        data: {
          labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
          datasets: [{
              label: 'Deposits',
              data: [5000, 7500, 10000, 8000, 12000, 15000],
              borderColor: '#f59e0b',
              backgroundColor: 'rgba(245, 158, 11, 0.1)',
              tension: 0.3,
              fill: true
            },
            {
              label: 'Investments',
              data: [3000, 6000, 8000, 9500, 11000, 13000],
              borderColor: '#10b981',
              backgroundColor: 'rgba(16, 185, 129, 0.1)',
              tension: 0.3,
              fill: true
            }
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              position: 'top',
              labels: {
                color: '#e5e7eb'
              }
            }
          },
          scales: {
            x: {
              grid: {
                color: 'rgba(75, 85, 99, 0.2)'
              },
              ticks: {
                color: '#9ca3af'
              }
            },
            y: {
              beginAtZero: true,
              grid: {
                color: 'rgba(75, 85, 99, 0.2)'
              },
              ticks: {
                color: '#9ca3af',
                callback: function(value) {
                  return '' + value;
                }
              }
            }
          }
        }
      });

      // Users Chart
      const usersCtx = document.getElementById('usersChart').getContext('2d');
      const usersChart = new Chart(usersCtx, {
        type: 'bar',
        data: {
          labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
          datasets: [{
            label: 'New Users',
            data: [15, 25, 20, 30, 35, 28],
            backgroundColor: [
              'rgba(245, 158, 11, 0.8)',
              'rgba(245, 158, 11, 0.8)',
              'rgba(245, 158, 11, 0.8)',
              'rgba(245, 158, 11, 0.8)',
              'rgba(245, 158, 11, 0.8)',
              'rgba(245, 158, 11, 0.8)'
            ],
            borderColor: [
              'rgba(245, 158, 11, 1)',
              'rgba(245, 158, 11, 1)',
              'rgba(245, 158, 11, 1)',
              'rgba(245, 158, 11, 1)',
              'rgba(245, 158, 11, 1)',
              'rgba(245, 158, 11, 1)'
            ],
            borderWidth: 1
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              position: 'top',
              labels: {
                color: '#e5e7eb'
              }
            }
          },
          scales: {
            x: {
              grid: {
                color: 'rgba(75, 85, 99, 0.2)'
              },
              ticks: {
                color: '#9ca3af'
              }
            },
            y: {
              beginAtZero: true,
              grid: {
                color: 'rgba(75, 85, 99, 0.2)'
              },
              ticks: {
                color: '#9ca3af'
              }
            }
          }
        }
      });

      // Notifications dropdown (simple toggle)
      const notificationsBtn = document.getElementById('notifications-btn');
      if (notificationsBtn) {
        notificationsBtn.addEventListener('click', function() {
          // Here you would implement your notifications dropdown
          alert('Pending deposits require your attention!');
        });
      }
    });
  </script>
</body>

</html>