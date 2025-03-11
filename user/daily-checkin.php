<?php

// Start session
session_start();
// Database connection
include '../config/db.php';
include 'includes/checkin-functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
  header("Location: ../login.php");
  exit;
}

// Include profit processing functions
require_once '../functions/process_profits.php';

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['full_name'] ?? 'User';

// Process check-in if requested
$checkin_result = null;
if (isset($_POST['daily_checkin'])) {
  $checkin_result = processUserCheckin($user_id);
}

// Check and process profits for this user (replaces cron job)
$profits_processed = processUserProfitsIfNeeded($user_id);

// Set up notification if profits were processed
$profit_notification = "";
if (
  $profits_processed['processed_investments'] > 0 ||
  $profits_processed['processed_tickets'] > 0 ||
  $profits_processed['processed_tokens'] > 0
) {
  $total_processed = $profits_processed['processed_investments'] +
    $profits_processed['processed_tickets'] +
    $profits_processed['processed_tokens'];

  // Set a notification for the user
  $profit_notification = "You have $total_processed new profit payments added to your wallet!";
}

// Get user's current streak info
$streak_info = getUserCheckinStreak($user_id);

// Get monthly stats
$monthly_stats = getMonthlyCheckinStats($user_id);

// Get all reward levels for display
$rewards_query = "SELECT * FROM checkin_rewards WHERE is_active = 1 ORDER BY streak_day ASC";
$rewards_result = $conn->query($rewards_query);
$reward_levels = [];

if ($rewards_result->num_rows > 0) {
  while ($row = $rewards_result->fetch_assoc()) {
    $reward_levels[] = $row;
  }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <?php include 'includes/head.php'; ?>
  <style>
    .streak-day {
      width: 40px;
      height: 40px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 50%;
      font-weight: bold;
    }

    .streak-active {
      background: linear-gradient(to bottom right, #F59E0B, #D97706);
      color: black;
    }

    .streak-inactive {
      background-color: #374151;
      color: #9CA3AF;
    }

    .streak-future {
      background-color: #1F2937;
      color: #6B7280;
    }

    .streak-complete {
      background-color: #047857;
      color: white;
    }

    .progress-bar {
      height: 8px;
      border-radius: 4px;
      background-color: #374151;
      overflow: hidden;
    }

    .progress-value {
      height: 100%;
      background: linear-gradient(to right, #F59E0B, #D97706);
    }

    @keyframes pulse {
      0% {
        transform: scale(1);
      }

      50% {
        transform: scale(1.05);
      }

      100% {
        transform: scale(1);
      }
    }

    .pulse-animation {
      animation: pulse 1.5s infinite;
    }
  </style>
</head>

<body class="bg-gray-900 text-white font-sans min-h-screen flex flex-col">
  <?php include 'includes/navbar.php'; ?>
  <?php include 'includes/pre-loader.php'; ?>
  <?php include 'includes/mobile-bar.php'; ?>

  <!-- Main Content -->
  <main style="display: none;" id="main-content" class="flex-grow py-6">
    <div class="container mx-auto px-4 sm:px-6 lg:px-8">
      <!-- Page Header -->
      <div class="mb-8">
        <h1 class="text-2xl font-bold">Daily Check-in</h1>
        <p class="text-gray-400 mt-2">Check in daily to earn rewards and build your streak!</p>
      </div>

      <?php if (!empty($profit_notification)): ?>
        <div class="mb-6 flex items-center space-x-3 py-3 px-4 bg-gradient-to-r from-green-800 to-green-900 rounded-lg border-l-4 border-green-500 shadow-md">
          <div class="bg-gradient-to-r from-green-500 to-green-600 p-2 rounded-full shadow">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-6 h-6">
              <circle cx="12" cy="12" r="10"></circle>
              <path d="M8 14s1.5 2 4 2 4-2 4-2"></path>
              <line x1="9" y1="9" x2="9.01" y2="9"></line>
              <line x1="15" y1="9" x2="15.01" y2="9"></line>
            </svg>
          </div>
          <div>
            <p class="text-white"><?php echo htmlspecialchars($profit_notification); ?></p>
          </div>
        </div>
      <?php endif; ?>

      <?php if ($checkin_result !== null): ?>
        <div class="mb-6 rounded-lg p-4 <?php echo $checkin_result['success'] ? 'bg-green-800 text-green-100' : 'bg-red-800 text-red-100'; ?>">
          <p class="flex items-center">
            <i class="<?php echo $checkin_result['success'] ? 'fas fa-check-circle' : 'fas fa-exclamation-circle'; ?> mr-2"></i>
            <?php echo $checkin_result['message']; ?>
          </p>

          <?php if ($checkin_result['success']): ?>
            <p class="mt-2 text-sm">
              Your current streak: <?php echo $checkin_result['streak']; ?> day<?php echo $checkin_result['streak'] > 1 ? 's' : ''; ?>
            </p>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <!-- Check-in Card -->
      <div class="bg-gradient-to-r from-gray-900 to-gray-800 rounded-xl p-6 mb-8 border border-gray-700">
        <div class="flex flex-col md:flex-row md:items-center justify-between mb-6">
          <div>
            <h2 class="text-xl font-bold mb-2">Your Check-in Streak</h2>
            <p class="text-gray-400">
              Current streak: <span class="text-yellow-500 font-bold"><?php echo $streak_info['streak']; ?> day<?php echo $streak_info['streak'] > 1 ? 's' : ''; ?></span>
            </p>
          </div>

          <div class="mt-4 md:mt-0">
            <?php if ($streak_info['checked_in_today']): ?>
              <button class="bg-gray-700 text-gray-300 py-3 px-6 rounded-lg font-bold cursor-not-allowed" disabled>
                <i class="fas fa-check-circle mr-2"></i> Already Checked In
              </button>
            <?php else: ?>
              <form method="post" action="">
                <button type="submit" name="daily_checkin" class="bg-gradient-to-r from-yellow-500 to-yellow-600 hover:from-yellow-600 hover:to-yellow-700 text-black font-bold py-3 px-6 rounded-lg transition duration-300 pulse-animation">
                  <i class="fas fa-gift mr-2"></i> Check In Now ($<?php echo number_format($streak_info['next_reward'], 2); ?>) </button>
              </form>
            <?php endif; ?>
          </div>
        </div>

        <!-- Streak Calendar -->
        <div class="bg-gray-800 p-6 rounded-lg">
          <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-bold">7-Day Progress</h3>
            <span class="text-sm text-gray-400">
              <?php echo $streak_info['checked_in_today'] ? 'Come back tomorrow to continue your streak!' : 'Check in today to continue your streak!'; ?>
            </span>
          </div>

          <div class="grid grid-cols-7 gap-2 md:gap-4 mb-4">
            <?php for ($i = 1; $i <= 7; $i++): ?>
              <?php
              $class = 'streak-inactive';
              if ($streak_info['streak'] >= $i && $streak_info['checked_in_today']) {
                $class = 'streak-complete';
              } elseif ($streak_info['streak'] + 1 == $i && !$streak_info['checked_in_today']) {
                $class = 'streak-active';
              } elseif ($streak_info['streak'] < $i) {
                $class = 'streak-future';
              }
              ?>
              <div class="text-center">
                <div class="streak-day <?php echo $class; ?> mx-auto">
                  <?php echo $i; ?>
                </div>
                <p class="text-xs mt-1 text-gray-400">Day <?php echo $i; ?></p>
              </div>
            <?php endfor; ?>
          </div>

          <!-- Next Reward Info -->
          <div class="bg-gray-700 rounded-lg p-4 mt-4">
            <div class="flex justify-between items-center">
              <div>
                <p class="text-sm text-gray-300">Next reward</p>
                <p class="text-lg font-bold text-yellow-500">$<?php echo number_format($streak_info['next_reward'], 2); ?></p>
              </div>
              <?php if (!$streak_info['checked_in_today']): ?>
                <div>
                  <form method="post" action="">
                    <button type="submit" name="daily_checkin" class="bg-gradient-to-r from-yellow-500 to-yellow-600 hover:from-yellow-600 hover:to-yellow-700 text-black text-sm font-bold py-2 px-4 rounded-lg transition duration-300">
                      Claim Now
                    </button>
                  </form>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Monthly Stats -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <div class="bg-gray-800 rounded-lg p-6 border border-gray-700">
          <h2 class="text-lg font-bold mb-4">Monthly Statistics</h2>

          <div class="space-y-4">
            <div>
              <div class="flex justify-between mb-1">
                <span class="text-sm text-gray-400">Check-in Rate (<?php echo $monthly_stats['check_in_count']; ?>/<?php echo $monthly_stats['days_passed']; ?> days)</span>
                <span class="text-sm font-medium text-yellow-500"><?php echo $monthly_stats['check_in_percentage']; ?>%</span>
              </div>
              <div class="progress-bar">
                <div class="progress-value" style="width: <?php echo $monthly_stats['check_in_percentage']; ?>%"></div>
              </div>
            </div>

            <div class="border-t border-gray-700 pt-4">
              <p class="text-sm text-gray-400">Month: <?php echo $monthly_stats['month']; ?></p>
              <p class="text-sm text-gray-400">Total Rewards: <span class="text-green-500">$<?php echo number_format($monthly_stats['total_rewards'], 2); ?></span></p>
            </div>
          </div>
        </div>

        <div class="bg-gray-800 rounded-lg p-6 border border-gray-700">
          <h2 class="text-lg font-bold mb-4">Upcoming Milestones</h2>

          <div class="space-y-3">
            <?php
            // Find the next milestone
            $current_streak = $streak_info['streak'];
            $shown_milestones = 0;

            foreach ($reward_levels as $reward):
              if ($reward['streak_day'] > $current_streak && $shown_milestones < 3):
                $shown_milestones++;
                $days_left = $reward['streak_day'] - $current_streak;
            ?>
                <div class="flex justify-between items-center py-2 border-b border-gray-700">
                  <div>
                    <p class="font-medium">Day <?php echo $reward['streak_day']; ?> Milestone</p>
                    <p class="text-sm text-gray-400">
                      <?php echo $days_left; ?> day<?php echo $days_left > 1 ? 's' : ''; ?> left
                    </p>
                  </div>
                  <div class="text-right">
                    <p class="text-yellow-500 font-bold">$<?php echo number_format($reward['reward_amount'], 2); ?></p>
                  </div>
                </div>
              <?php
              endif;
            endforeach;

            if ($shown_milestones === 0):
              ?>
              <p class="text-gray-400 text-center py-4">You've reached all available milestones!</p>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Reward Levels -->
      <div class="bg-gray-800 rounded-lg p-6 border border-gray-700 mb-8">
        <h2 class="text-lg font-bold mb-4">Reward Levels</h2>

        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-700">
            <thead>
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Streak Day</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Reward</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Status</th>
              </tr>
            </thead>
            <tbody class="bg-gray-800 divide-y divide-gray-700">
              <?php foreach ($reward_levels as $reward): ?>
                <tr class="hover:bg-gray-750">
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex items-center">
                      <div class="flex-shrink-0 h-8 w-8 rounded-full bg-gray-700 flex items-center justify-center">
                        <?php echo $reward['streak_day']; ?>
                      </div>
                      <div class="ml-3">
                        <div class="text-sm font-medium">Day <?php echo $reward['streak_day']; ?></div>
                      </div>
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-yellow-500 font-bold">$<?php echo number_format($reward['reward_amount'], 2); ?></div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <?php if ($current_streak >= $reward['streak_day'] && $streak_info['checked_in_today']): ?>
                      <span class="px-2 py-1 text-xs rounded-full bg-green-900 text-green-400">Claimed</span>
                    <?php elseif ($current_streak + 1 == $reward['streak_day'] && !$streak_info['checked_in_today']): ?>
                      <span class="px-2 py-1 text-xs rounded-full bg-yellow-900 text-yellow-400">Available Today</span>
                    <?php else: ?>
                      <span class="px-2 py-1 text-xs rounded-full bg-gray-700 text-gray-400">Upcoming</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Info Box -->
      <div class="bg-blue-900 bg-opacity-20 border border-blue-800 rounded-lg p-4 mb-8">
        <div class="flex items-start">
          <div class="flex-shrink-0">
            <i class="fas fa-info-circle text-blue-500 mt-1"></i>
          </div>
          <div class="ml-3">
            <h3 class="text-sm font-medium text-blue-400">How Check-ins Work</h3>
            <div class="mt-2 text-sm text-gray-300 space-y-1">
              <p>• Check in daily to earn rewards and build your streak</p>
              <p>• Higher streaks earn bigger rewards</p>
              <p>• Missing a day will reset your streak</p>
              <p>• All rewards are added instantly to your wallet</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>

  <?php include 'includes/footer.php'; ?>
  <?php include 'includes/js-links.php'; ?>

  <script>
    // Show loading animation when page loads
    document.addEventListener('DOMContentLoaded', function() {
      // Hide preloader after 1.5 seconds
      setTimeout(function() {
        document.querySelector('.preloader').style.display = 'none';
        document.getElementById('main-content').style.display = 'block';
      }, 1500);
    });
  </script>
</body>

</html>