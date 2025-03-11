<?php
// Start session
session_start();
include '../config/db.php';

// Aggregate data for charts
// 1. Monthly User Registration
$monthly_users_sql = "SELECT 
    DATE_FORMAT(registration_date, '%Y-%m') as month, 
    COUNT(*) as user_count 
FROM users 
GROUP BY month 
ORDER BY month 
LIMIT 12";
$monthly_users_result = $conn->query($monthly_users_sql);
$monthly_users_data = [];
while ($row = $monthly_users_result->fetch_assoc()) {
  $monthly_users_data[] = $row;
}

// 2. Deposit Trends
$monthly_deposits_sql = "SELECT 
    DATE_FORMAT(created_at, '%Y-%m') as month, 
    SUM(amount) as total_deposits,
    COUNT(*) as deposit_count 
FROM deposits 
WHERE status = 'approved'
GROUP BY month 
ORDER BY month 
LIMIT 12";
$monthly_deposits_result = $conn->query($monthly_deposits_sql);
$monthly_deposits_data = [];
while ($row = $monthly_deposits_result->fetch_assoc()) {
  $monthly_deposits_data[] = $row;
}

// 3. Investment Distribution
$investment_plans_sql = "SELECT 
    plan_type, 
    COUNT(*) as plan_count, 
    SUM(amount) as total_investment 
FROM investments 
GROUP BY plan_type";
$investment_plans_result = $conn->query($investment_plans_sql);
$investment_plans_data = [];
while ($row = $investment_plans_result->fetch_assoc()) {
  $investment_plans_data[] = $row;
}

// 4. Staking Plan Analysis
$staking_plans_sql = "SELECT 
    name, 
    COUNT(s.id) as stakes_count, 
    SUM(s.amount) as total_staked 
FROM staking_plans sp
LEFT JOIN stakes s ON sp.id = s.plan_id
GROUP BY sp.id, sp.name";
$staking_plans_result = $conn->query($staking_plans_sql);
$staking_plans_data = [];
while ($row = $staking_plans_result->fetch_assoc()) {
  $staking_plans_data[] = $row;
}

// 5. User Status Distribution
$user_status_sql = "SELECT 
    status, 
    COUNT(*) as status_count 
FROM users 
GROUP BY status";
$user_status_result = $conn->query($user_status_sql);
$user_status_data = [];
while ($row = $user_status_result->fetch_assoc()) {
  $user_status_data[] = $row;
}

// 6. Referral Tracking
$total_referrals_sql = "SELECT COUNT(*) as total FROM referrals";
$paid_referrals_sql = "SELECT COUNT(*) as paid FROM referrals WHERE status = 'paid'";
$pending_referrals_sql = "SELECT COUNT(*) as pending FROM referrals WHERE status = 'pending'";

$total_result = $conn->query($total_referrals_sql)->fetch_assoc()['total'];
$paid_result = $conn->query($paid_referrals_sql)->fetch_assoc()['paid'];
$pending_result = $conn->query($pending_referrals_sql)->fetch_assoc()['pending'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>AutoProftX - Database Charts</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
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
  </style>
</head>

<body class="bg-gray-900 text-gray-100 min-h-screen">
  <!-- Back to Dashboard button - place this right after the opening body tag or after the container div -->
  <div class="container mx-auto px-4 py-4">
    <a href="index.php" class="inline-flex items-center px-4 py-2 bg-gray-800 hover:bg-gray-700 text-yellow-500 border border-yellow-500 rounded-md transition-colors duration-300">
      <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
    </a>
  </div>

  <!-- Or, if you prefer a fixed floating button -->
  <!-- <a href="../dashboard/index.php" class="fixed top-4 left-4 inline-flex items-center px-4 py-2 bg-gray-800 hover:bg-gray-700 text-yellow-500 border border-yellow-500 rounded-md shadow-lg transition-colors duration-300">
  <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
</a> -->
  <div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-8 text-yellow-500 text-center">AutoProftX Database Insights</h1>

    <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
      <!-- Monthly User Registration -->
      <div class="bg-gray-800 rounded-lg p-6 border border-gray-700 shadow-lg">
        <h3 class="text-lg font-bold mb-4 text-yellow-500">Monthly User Registration</h3>
        <div class="h-64">
          <canvas id="monthlyUsersChart"></canvas>
        </div>
      </div>

      <!-- Monthly Deposits -->
      <div class="bg-gray-800 rounded-lg p-6 border border-gray-700 shadow-lg">
        <h3 class="text-lg font-bold mb-4 text-yellow-500">Monthly Deposits</h3>
        <div class="h-64">
          <canvas id="monthlyDepositsChart"></canvas>
        </div>
      </div>

      <!-- Investment Plan Distribution -->
      <div class="bg-gray-800 rounded-lg p-6 border border-gray-700 shadow-lg">
        <h3 class="text-lg font-bold mb-4 text-yellow-500">Investment Plan Distribution</h3>
        <div class="h-64">
          <canvas id="investmentPlansChart"></canvas>
        </div>
      </div>

      <!-- Staking Plans Analysis -->
      <div class="bg-gray-800 rounded-lg p-6 border border-gray-700 shadow-lg">
        <h3 class="text-lg font-bold mb-4 text-yellow-500">Staking Plans Analysis</h3>
        <div class="h-64">
          <canvas id="stakingPlansChart"></canvas>
        </div>
      </div>

      <!-- User Status Distribution -->
      <div class="bg-gray-800 rounded-lg p-6 border border-gray-700 shadow-lg">
        <h3 class="text-lg font-bold mb-4 text-yellow-500">User Status Distribution</h3>
        <div class="h-64">
          <canvas id="userStatusChart"></canvas>
        </div>
      </div>

      <!-- Referral Distribution -->
      <div class="bg-gray-800 rounded-lg p-6 border border-gray-700 shadow-lg">
        <h3 class="text-lg font-bold mb-4 text-yellow-500">Referral Insights</h3>
        <div class="h-64">
          <canvas id="referralChart"></canvas>
        </div>
      </div>
    </div>
  </div>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Monthly Users Chart
      const monthlyUsersCtx = document.getElementById('monthlyUsersChart').getContext('2d');
      new Chart(monthlyUsersCtx, {
        type: 'bar',
        data: {
          labels: <?php echo json_encode(array_column($monthly_users_data, 'month')); ?>,
          datasets: [{
            label: 'New Users',
            data: <?php echo json_encode(array_column($monthly_users_data, 'user_count')); ?>,
            backgroundColor: 'rgba(245, 158, 11, 0.7)',
            borderColor: 'rgba(245, 158, 11, 1)',
            borderWidth: 1
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              labels: {
                color: '#e5e7eb'
              }
            }
          }
        }
      });

      // Monthly Deposits Chart
      const monthlyDepositsCtx = document.getElementById('monthlyDepositsChart').getContext('2d');
      new Chart(monthlyDepositsCtx, {
        type: 'line',
        data: {
          labels: <?php echo json_encode(array_column($monthly_deposits_data, 'month')); ?>,
          datasets: [{
              label: 'Total Deposits ($)',
              data: <?php echo json_encode(array_column($monthly_deposits_data, 'total_deposits')); ?>,
              backgroundColor: 'rgba(16, 185, 129, 0.2)',
              borderColor: 'rgba(16, 185, 129, 1)',
              borderWidth: 2,
              yAxisID: 'y'
            },
            {
              label: 'Number of Deposits',
              data: <?php echo json_encode(array_column($monthly_deposits_data, 'deposit_count')); ?>,
              backgroundColor: 'rgba(59, 130, 246, 0.2)',
              borderColor: 'rgba(59, 130, 246, 1)',
              borderWidth: 2,
              yAxisID: 'y1'
            }
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: {
            y: {
              type: 'linear',
              display: true,
              position: 'left',
              ticks: {
                color: '#e5e7eb'
              },
              grid: {
                color: 'rgba(255, 255, 255, 0.1)'
              }
            },
            y1: {
              type: 'linear',
              display: true,
              position: 'right',
              ticks: {
                color: '#e5e7eb'
              },
              grid: {
                drawOnChartArea: false
              }
            }
          },
          plugins: {
            legend: {
              labels: {
                color: '#e5e7eb'
              }
            }
          }
        }
      });

      // Investment Plans Chart
      const investmentPlansCtx = document.getElementById('investmentPlansChart').getContext('2d');
      new Chart(investmentPlansCtx, {
        type: 'doughnut',
        data: {
          labels: <?php echo json_encode(array_column($investment_plans_data, 'plan_type')); ?>,
          datasets: [{
            label: 'Total Investment ($)',
            data: <?php echo json_encode(array_column($investment_plans_data, 'total_investment')); ?>,
            backgroundColor: [
              'rgba(245, 158, 11, 0.7)',
              'rgba(16, 185, 129, 0.7)',
              'rgba(59, 130, 246, 0.7)',
              'rgba(236, 72, 153, 0.7)',
              'rgba(139, 92, 246, 0.7)'
            ],
            borderColor: [
              'rgba(245, 158, 11, 1)',
              'rgba(16, 185, 129, 1)',
              'rgba(59, 130, 246, 1)',
              'rgba(236, 72, 153, 1)',
              'rgba(139, 92, 246, 1)'
            ],
            borderWidth: 1
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              position: 'right',
              labels: {
                color: '#e5e7eb'
              }
            }
          }
        }
      });

      // Staking Plans Chart
      const stakingPlansCtx = document.getElementById('stakingPlansChart').getContext('2d');
      new Chart(stakingPlansCtx, {
        type: 'bar',
        data: {
          labels: <?php echo json_encode(array_column($staking_plans_data, 'name')); ?>,
          datasets: [{
              label: 'Total Staked ($)',
              data: <?php echo json_encode(array_column($staking_plans_data, 'total_staked')); ?>,
              backgroundColor: 'rgba(139, 92, 246, 0.7)',
              borderColor: 'rgba(139, 92, 246, 1)',
              borderWidth: 1,
              order: 1
            },
            {
              label: 'Number of Stakes',
              data: <?php echo json_encode(array_column($staking_plans_data, 'stakes_count')); ?>,
              backgroundColor: 'rgba(236, 72, 153, 0.7)',
              borderColor: 'rgba(236, 72, 153, 1)',
              borderWidth: 1,
              type: 'line',
              order: 0
            }
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: {
            y: {
              beginAtZero: true,
              ticks: {
                color: '#e5e7eb'
              },
              grid: {
                color: 'rgba(255, 255, 255, 0.1)'
              }
            },
            x: {
              ticks: {
                color: '#e5e7eb'
              },
              grid: {
                color: 'rgba(255, 255, 255, 0.1)'
              }
            }
          },
          plugins: {
            legend: {
              labels: {
                color: '#e5e7eb'
              }
            }
          }
        }
      });

      // User Status Chart
      const userStatusCtx = document.getElementById('userStatusChart').getContext('2d');
      new Chart(userStatusCtx, {
        type: 'pie',
        data: {
          labels: <?php echo json_encode(array_column($user_status_data, 'status')); ?>,
          datasets: [{
            data: <?php echo json_encode(array_column($user_status_data, 'status_count')); ?>,
            backgroundColor: [
              'rgba(16, 185, 129, 0.7)',
              'rgba(245, 158, 11, 0.7)',
              'rgba(239, 68, 68, 0.7)',
              'rgba(59, 130, 246, 0.7)'
            ],
            borderColor: [
              'rgba(16, 185, 129, 1)',
              'rgba(245, 158, 11, 1)',
              'rgba(239, 68, 68, 1)',
              'rgba(59, 130, 246, 1)'
            ],
            borderWidth: 1
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              position: 'right',
              labels: {
                color: '#e5e7eb'
              }
            }
          }
        }
      });

      // Referral Chart
      const referralCtx = document.getElementById('referralChart').getContext('2d');
      new Chart(referralCtx, {
        type: 'doughnut',
        data: {
          labels: ['Paid', 'Pending'],
          datasets: [{
            data: [<?php echo $paid_result; ?>, <?php echo $pending_result; ?>],
            backgroundColor: [
              'rgba(16, 185, 129, 0.7)',
              'rgba(245, 158, 11, 0.7)'
            ],
            borderColor: [
              'rgba(16, 185, 129, 1)',
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
              position: 'right',
              labels: {
                color: '#e5e7eb'
              }
            },
            tooltip: {
              callbacks: {
                footer: function() {
                  return 'Total: <?php echo $total_result; ?> referrals';
                }
              }
            }
          }
        }
      });
    });
  </script>
</body>

</html>