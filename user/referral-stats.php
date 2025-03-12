<?php
// Start session
session_start();

// Database connection
include '../config/db.php';

// Include referral functions
require_once '../functions/referral_vault.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
  // Redirect to login page if not logged in
  header("Location: ../login.php");
  exit;
}

// Get the user ID from session
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['full_name'] ?? 'User';

// Get referral data
$referrals = getUserReferrals($user_id);
$pending_bonuses = getPendingReferralBonuses($user_id);
$total_pending_amount = getTotalPendingReferralAmount($user_id);
$total_referral_earnings = getTotalReferralEarnings($user_id);
$claimed_bonuses_amount = $total_referral_earnings - $total_pending_amount;

// Calculate overall statistics
$total_referrals = count($referrals);
$active_referrals = 0;
$completed_referrals = 0;
$monthly_stats = [];
$daily_stats = [];

// Process referral data for stats
if ($total_referrals > 0) {
  // Get current month and year
  $current_month = date('n');
  $current_year = date('Y');

  // Initialize monthly stats array for the last 6 months
  for ($i = 0; $i < 6; $i++) {
    $month = $current_month - $i;
    $year = $current_year;

    if ($month <= 0) {
      $month += 12;
      $year--;
    }

    $month_name = date('M', mktime(0, 0, 0, $month, 1, $year));
    $monthly_stats[$month_name] = [
      'count' => 0,
      'earnings' => 0,
      'month' => $month,
      'year' => $year
    ];
  }

  // Initialize daily stats for last 7 days
  for ($i = 0; $i < 7; $i++) {
    $day = date('Y-m-d', strtotime("-$i days"));
    $day_name = date('D', strtotime($day));
    $daily_stats[$day_name] = [
      'count' => 0,
      'earnings' => 0,
      'date' => $day
    ];
  }

  // Process each referral
  foreach ($referrals as $referral) {
    // Count active referrals (for this example, all are considered active)
    $active_referrals++;

    // Count completed (paid) referrals
    if ($referral['status'] == 'paid') {
      $completed_referrals++;
    }

    // Get referral month and year
    $ref_date = strtotime($referral['registration_date']);
    $ref_month = date('n', $ref_date);
    $ref_year = date('Y', $ref_date);
    $ref_month_name = date('M', $ref_date);

    // Add to monthly stats if within the last 6 months
    foreach ($monthly_stats as $month_name => $stats) {
      if ($stats['month'] == $ref_month && $stats['year'] == $ref_year) {
        $monthly_stats[$month_name]['count']++;
        $monthly_stats[$month_name]['earnings'] += $referral['bonus_amount'];
        break;
      }
    }

    // Add to daily stats if within the last 7 days
    $ref_day = date('Y-m-d', $ref_date);
    foreach ($daily_stats as $day_name => $stats) {
      if ($stats['date'] == $ref_day) {
        $daily_stats[$day_name]['count']++;
        $daily_stats[$day_name]['earnings'] += $referral['bonus_amount'];
        break;
      }
    }
  }
}

// Calculate completion rate
$completion_rate = $total_referrals > 0 ? round(($completed_referrals / $total_referrals) * 100) : 0;

// Reverse arrays for chronological display
$monthly_stats = array_reverse($monthly_stats);
$daily_stats = array_reverse($daily_stats);

// Get referral code
$referral_code = '';
$query = "SELECT referral_code FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
  $referral_code = $row['referral_code'];
}

// Generate referral link
$referral_link = "https://" . $_SERVER['HTTP_HOST'] . "/register.php?ref=" . $referral_code;

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <?php include 'includes/head.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    .stat-card {
      transition: all 0.3s ease;
    }

    .stat-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
    }

    .chart-container {
      position: relative;
      height: 300px;
      width: 100%;
    }

    @media (max-width: 768px) {
      .chart-container {
        height: 200px;
      }
    }
  </style>
</head>

<body class="bg-gray-900 text-white font-sans min-h-screen flex flex-col">
  <?php include 'includes/navbar.php'; ?>
  <?php include 'includes/mobile-bar.php'; ?>
  <?php include 'includes/pre-loader.php'; ?>

  <!-- Main Content -->
  <main style="display: none;" id="main-content" class="flex-grow py-6">
    <div class="container mx-auto px-4 sm:px-6 lg:px-8">
      <!-- Page Header -->
      <div class="mb-8">
        <h1 class="text-3xl font-bold">Referral Statistics</h1>
        <p class="text-gray-400 mt-2">Track your referral performance and earnings</p>
      </div>

      <!-- Stats Overview -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Total Referrals -->
        <div class="bg-gradient-to-br from-gray-900 to-gray-800 rounded-xl p-6 border border-gray-700 shadow-lg stat-card">
          <div class="flex justify-between items-start">
            <div>
              <p class="text-gray-400 text-sm font-medium uppercase tracking-wider">Total Referrals</p>
              <h3 class="text-3xl font-extrabold text-white mt-2"><?php echo $total_referrals; ?></h3>
              <p class="text-blue-400 text-sm flex items-center mt-2 font-medium">
                <i class="fas fa-users mr-2"></i> All-time invites
              </p>
            </div>
            <div class="h-12 w-12 rounded-full bg-blue-900 flex items-center justify-center shadow-lg">
              <i class="fas fa-user-friends text-blue-400 text-lg"></i>
            </div>
          </div>
        </div>

        <!-- Active Referrals -->
        <div class="bg-gradient-to-br from-gray-900 to-gray-800 rounded-xl p-6 border border-gray-700 shadow-lg stat-card">
          <div class="flex justify-between items-start">
            <div>
              <p class="text-gray-400 text-sm font-medium uppercase tracking-wider">Active Referrals</p>
              <h3 class="text-3xl font-extrabold text-white mt-2"><?php echo $active_referrals; ?></h3>
              <p class="text-green-400 text-sm flex items-center mt-2 font-medium">
                <i class="fas fa-user-check mr-2"></i> Currently active
              </p>
            </div>
            <div class="h-12 w-12 rounded-full bg-green-900 flex items-center justify-center shadow-lg">
              <i class="fas fa-user-check text-green-400 text-lg"></i>
            </div>
          </div>
        </div>

        <!-- Total Earnings -->
        <div class="bg-gradient-to-br from-gray-900 to-gray-800 rounded-xl p-6 border border-gray-700 shadow-lg stat-card">
          <div class="flex justify-between items-start">
            <div>
              <p class="text-gray-400 text-sm font-medium uppercase tracking-wider">Total Earnings</p>
              <h3 class="text-3xl font-extrabold text-white mt-2">$<?php echo number_format($total_referral_earnings, 2); ?></h3>
              <p class="text-yellow-500 text-sm flex items-center mt-2 font-medium">
                <i class="fas fa-coins mr-2"></i> All-time revenue
              </p>
            </div>
            <div class="h-12 w-12 rounded-full bg-yellow-900 flex items-center justify-center shadow-lg">
              <i class="fas fa-dollar-sign text-yellow-400 text-lg"></i>
            </div>
          </div>
        </div>

        <!-- Completion Rate -->
        <div class="bg-gradient-to-br from-gray-900 to-gray-800 rounded-xl p-6 border border-gray-700 shadow-lg stat-card">
          <div class="flex justify-between items-start">
            <div>
              <p class="text-gray-400 text-sm font-medium uppercase tracking-wider">Completion Rate</p>
              <h3 class="text-3xl font-extrabold text-white mt-2"><?php echo $completion_rate; ?>%</h3>
              <p class="text-purple-400 text-sm flex items-center mt-2 font-medium">
                <i class="fas fa-chart-pie mr-2"></i> Paid referrals
              </p>
            </div>
            <div class="h-12 w-12 rounded-full bg-purple-900 flex items-center justify-center shadow-lg">
              <i class="fas fa-percentage text-purple-400 text-lg"></i>
            </div>
          </div>
        </div>
      </div>

      <!-- Charts Section -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Monthly Referrals Chart -->
        <div class="bg-gray-800 rounded-lg border border-gray-700 p-6">
          <h2 class="text-xl font-bold mb-4">Monthly Referrals</h2>
          <div class="chart-container">
            <canvas id="monthlyChart"></canvas>
          </div>
        </div>

        <!-- Daily Referrals Chart -->
        <div class="bg-gray-800 rounded-lg border border-gray-700 p-6">
          <h2 class="text-xl font-bold mb-4">Last 7 Days</h2>
          <div class="chart-container">
            <canvas id="dailyChart"></canvas>
          </div>
        </div>
      </div>

      <!-- Earnings Breakdown -->
      <div class="bg-gray-800 rounded-lg border border-gray-700 p-6 mb-8">
        <h2 class="text-xl font-bold mb-4">Earnings Breakdown</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <!-- Earnings Progress -->
          <div>
            <div class="mb-6">
              <div class="flex justify-between items-center mb-2">
                <span class="text-sm text-gray-400">Claimed</span>
                <span class="text-sm font-medium">$<?php echo number_format($claimed_bonuses_amount, 2); ?></span>
              </div>
              <div class="w-full bg-gray-700 rounded-full h-2.5">
                <?php
                $claimed_percentage = $total_referral_earnings > 0 ? ($claimed_bonuses_amount / $total_referral_earnings) * 100 : 0;
                ?>
                <div class="bg-green-600 h-2.5 rounded-full" style="width: <?php echo $claimed_percentage; ?>%"></div>
              </div>
            </div>

            <div class="mb-6">
              <div class="flex justify-between items-center mb-2">
                <span class="text-sm text-gray-400">Pending</span>
                <span class="text-sm font-medium">$<?php echo number_format($total_pending_amount, 2); ?></span>
              </div>
              <div class="w-full bg-gray-700 rounded-full h-2.5">
                <?php
                $pending_percentage = $total_referral_earnings > 0 ? ($total_pending_amount / $total_referral_earnings) * 100 : 0;
                ?>
                <div class="bg-yellow-500 h-2.5 rounded-full" style="width: <?php echo $pending_percentage; ?>%"></div>
              </div>
            </div>

            <?php if ($total_pending_amount > 0): ?>
              <div class="mt-4">
                <a href="refer-collect.php" class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-yellow-500 to-yellow-600 hover:from-yellow-600 hover:to-yellow-700 text-black font-medium rounded-lg transition duration-300">
                  <i class="fas fa-hand-holding-usd mr-2"></i> Claim Pending Rewards
                </a>
              </div>
            <?php endif; ?>
          </div>

          <!-- Monthly Earnings Comparison -->
          <div>
            <div class="chart-container">
              <canvas id="earningsChart"></canvas>
            </div>
          </div>
        </div>
      </div>

      <!-- Performance Metrics -->
      <div class="bg-gray-800 rounded-lg border border-gray-700 p-6 mb-8">
        <h2 class="text-xl font-bold mb-6">Performance Metrics</h2>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <!-- Average Earning per Referral -->
          <div class="bg-gray-750 rounded-lg p-5 text-center">
            <div class="h-16 w-16 rounded-full bg-indigo-900 flex items-center justify-center mx-auto mb-4">
              <i class="fas fa-dollar-sign text-indigo-400 text-xl"></i>
            </div>
            <h3 class="text-lg font-bold text-gray-300 mb-1">Avg. Earning</h3>
            <p class="text-2xl font-bold text-indigo-400">
              $<?php echo $total_referrals > 0 ? number_format($total_referral_earnings / $total_referrals, 2) : '0.00'; ?>
            </p>
            <p class="text-xs text-gray-400 mt-2">Per referral</p>
          </div>

          <!-- Best Performing Month -->
          <div class="bg-gray-750 rounded-lg p-5 text-center">
            <div class="h-16 w-16 rounded-full bg-green-900 flex items-center justify-center mx-auto mb-4">
              <i class="fas fa-calendar-check text-green-400 text-xl"></i>
            </div>
            <h3 class="text-lg font-bold text-gray-300 mb-1">Best Month</h3>
            <?php
            $best_month = '';
            $best_month_count = 0;

            foreach ($monthly_stats as $month => $data) {
              if ($data['count'] > $best_month_count) {
                $best_month = $month;
                $best_month_count = $data['count'];
              }
            }
            ?>
            <p class="text-2xl font-bold text-green-400">
              <?php echo $best_month ? $best_month : 'N/A'; ?>
            </p>
            <p class="text-xs text-gray-400 mt-2">
              <?php echo $best_month_count; ?> referrals
            </p>
          </div>

          <!-- Conversion Rate -->
          <div class="bg-gray-750 rounded-lg p-5 text-center">
            <div class="h-16 w-16 rounded-full bg-blue-900 flex items-center justify-center mx-auto mb-4">
              <i class="fas fa-exchange-alt text-blue-400 text-xl"></i>
            </div>
            <h3 class="text-lg font-bold text-gray-300 mb-1">Conversion Rate</h3>
            <p class="text-2xl font-bold text-blue-400">
              <?php echo $completion_rate; ?>%
            </p>
            <p class="text-xs text-gray-400 mt-2">
              <?php echo $completed_referrals; ?> of <?php echo $total_referrals; ?> completed
            </p>
          </div>
        </div>
      </div>

      <!-- Referral Link Section -->
      <div class="bg-gray-800 rounded-lg p-6 border border-gray-700 mb-8">
        <h2 class="text-xl font-bold mb-4">Your Referral Link</h2>
        <div class="flex flex-col md:flex-row md:items-center mb-4">
          <div class="relative flex-1 mb-4 md:mb-0">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
              <i class="fas fa-link text-gray-400"></i>
            </div>
            <input type="text" id="referral-link" value="<?php echo $referral_link; ?>" class="bg-gray-700 border border-gray-600 text-white pl-10 pr-16 py-3 rounded-lg w-full" readonly>
            <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
              <button onclick="copyReferralLink()" class="text-yellow-500 hover:text-yellow-400">
                <i class="fas fa-copy"></i>
              </button>
            </div>
          </div>
          <button onclick="shareReferral()" class="bg-yellow-600 hover:bg-yellow-700 text-white px-6 py-3 rounded-lg transition duration-300 md:ml-4 flex items-center justify-center">
            <i class="fas fa-share-alt mr-2"></i> Share
          </button>
        </div>
        <p class="text-sm text-gray-400">
          Share this link with friends to earn $5 for each successful referral. Track your performance on this page.
        </p>
      </div>

      <!-- Tips for Increasing Referrals -->
      <div class="bg-gray-800 rounded-lg border border-gray-700 p-6">
        <h2 class="text-xl font-bold mb-4">Tips for Increasing Referrals</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <div class="bg-gray-750 rounded-lg p-5">
            <div class="flex items-center mb-3">
              <div class="h-10 w-10 rounded-full bg-blue-900 flex items-center justify-center mr-3">
                <i class="fas fa-bullhorn text-blue-400"></i>
              </div>
              <h3 class="text-lg font-bold">Share on Social Media</h3>
            </div>
            <p class="text-gray-400 text-sm">
              Regular posting on social media platforms can help you reach a wider audience and attract more referrals.
            </p>
          </div>

          <div class="bg-gray-750 rounded-lg p-5">
            <div class="flex items-center mb-3">
              <div class="h-10 w-10 rounded-full bg-green-900 flex items-center justify-center mr-3">
                <i class="fas fa-users text-green-400"></i>
              </div>
              <h3 class="text-lg font-bold">Engage with Community</h3>
            </div>
            <p class="text-gray-400 text-sm">
              Join investment communities and forums to share your experiences and guide others to join using your referral link.
            </p>
          </div>

          <div class="bg-gray-750 rounded-lg p-5">
            <div class="flex items-center mb-3">
              <div class="h-10 w-10 rounded-full bg-purple-900 flex items-center justify-center mr-3">
                <i class="fas fa-video text-purple-400"></i>
              </div>
              <h3 class="text-lg font-bold">Create Content</h3>
            </div>
            <p class="text-gray-400 text-sm">
              Create videos, blogs, or reviews about your experience to establish trust and encourage sign-ups through your link.
            </p>
          </div>
        </div>
      </div>
    </div>
  </main>

  <?php include 'includes/footer.php'; ?>
  <?php include 'includes/js-links.php'; ?>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Hide preloader after 1 second
      setTimeout(function() {
        document.querySelector('.preloader').style.display = 'none';
        document.getElementById('main-content').style.display = 'block';
      }, 1000);

      // Copy referral link function
      window.copyReferralLink = function() {
        const referralLink = document.getElementById('referral-link');
        referralLink.select();
        document.execCommand('copy');
        alert('Referral link copied to clipboard!');
      };

      // Share referral function
      window.shareReferral = function() {
        if (navigator.share) {
          navigator.share({
              title: 'Join me on AutoProfitX',
              text: 'Check out this amazing platform! Join using my referral link:',
              url: '<?php echo $referral_link; ?>'
            })
            .then(() => console.log('Successful share'))
            .catch((error) => console.log('Error sharing:', error));
        } else {
          // Fallback for browsers that don't support Web Share API
          copyReferralLink();
          alert('Link copied to clipboard! Share it with your friends.');
        }
      };

      // Chart.js - Monthly Referrals Chart
      const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
      const monthlyChart = new Chart(monthlyCtx, {
        type: 'bar',
        data: {
          labels: [
            <?php
            $months = array_keys($monthly_stats);
            echo "'" . implode("', '", $months) . "'";
            ?>
          ],
          datasets: [{
            label: 'Referrals',
            data: [
              <?php
              $counts = array_column($monthly_stats, 'count');
              echo implode(', ', $counts);
              ?>
            ],
            backgroundColor: 'rgba(59, 130, 246, 0.5)',
            borderColor: 'rgb(59, 130, 246)',
            borderWidth: 1
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: {
            y: {
              beginAtZero: true,
              ticks: {
                precision: 0,
                color: '#9ca3af'
              },
              grid: {
                color: 'rgba(75, 85, 99, 0.2)'
              }
            },
            x: {
              ticks: {
                color: '#9ca3af'
              },
              grid: {
                display: false
              }
            }
          },
          plugins: {
            legend: {
              display: false
            }
          }
        }
      });

      // Chart.js - Daily Referrals Chart
      const dailyCtx = document.getElementById('dailyChart').getContext('2d');
      const dailyChart = new Chart(dailyCtx, {
        type: 'line',
        data: {
          labels: [
            <?php
            $days = array_keys($daily_stats);
            echo "'" . implode("', '", $days) . "'";
            ?>
          ],
          datasets: [{
            label: 'Referrals',
            data: [
              <?php
              $daily_counts = array_column($daily_stats, 'count');
              echo implode(', ', $daily_counts);
              ?>
            ],
            backgroundColor: 'rgba(16, 185, 129, 0.2)',
            borderColor: 'rgb(16, 185, 129)',
            borderWidth: 2,
            tension: 0.4,
            pointBackgroundColor: 'rgb(16, 185, 129)',
            pointRadius: 4
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: {
            y: {
              beginAtZero: true,
              ticks: {
                precision: 0,
                color: '#9ca3af'
              },
              grid: {
                color: 'rgba(75, 85, 99, 0.2)'
              }
            },
            x: {
              ticks: {
                color: '#9ca3af'
              },
              grid: {
                display: false
              }
            }
          },
          plugins: {
            legend: {
              display: false
            }
          }
        }
      });

      // Chart.js - Earnings Chart
      const earningsCtx = document.getElementById('earningsChart').getContext('2d');
      const earningsChart = new Chart(earningsCtx, {
        type: 'bar',
        data: {
          labels: [
            <?php
            echo "'" . implode("', '", $months) . "'";
            ?>
          ],
          datasets: [{
            label: 'Earnings',
            data: [
              <?php
              $earnings = array_column($monthly_stats, 'earnings');
              echo implode(', ', $earnings);
              ?>
            ],
            backgroundColor: 'rgba(245, 158, 11, 0.5)',
            borderColor: 'rgb(245, 158, 11)',
            borderWidth: 1
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: {
            y: {
              beginAtZero: true,
              ticks: {
                callback: function(value) {
                  return '$' + value;
                },
                color: '#9ca3af'
              },
              grid: {
                color: 'rgba(75, 85, 99, 0.2)'
              }
            },
            x: {
              ticks: {
                color: '#9ca3af'
              },
              grid: {
                display: false
              }
            }
          },
          plugins: {
            legend: {
              display: false
            },
            tooltip: {
              callbacks: {
                label: function(context) {
                  return '$' + context.raw.toFixed(2);
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