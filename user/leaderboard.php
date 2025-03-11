<?php
// Start session
session_start();

// Include database connection
include '../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
  header('Location: login.php');
  exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['full_name'] ?? 'User';

// Get leaderboard data - Top depositors of all time
$top_depositors_query = "SELECT 
                            u.id as user_id, 
                            u.full_name, 
                            u.referral_code,
                            SUM(d.amount) as total_deposited,
                            COUNT(d.id) as deposit_count
                        FROM 
                            deposits d
                        JOIN 
                            users u ON d.user_id = u.id
                        WHERE 
                            d.status = 'approved'
                        GROUP BY 
                            d.user_id
                        ORDER BY 
                            total_deposited DESC
                        LIMIT 10";

$top_depositors_result = $conn->query($top_depositors_query);
$top_depositors = [];

if ($top_depositors_result) {
  while ($row = $top_depositors_result->fetch_assoc()) {
    $top_depositors[] = $row;
  }
}

// Get monthly leaderboard data - Top depositors this month
$current_month_start = date('Y-m-01 00:00:00');
$current_month_end = date('Y-m-t 23:59:59');

$monthly_leaders_query = "SELECT 
                            u.id as user_id, 
                            u.full_name,
                            u.referral_code,
                            SUM(d.amount) as total_deposited,
                            COUNT(d.id) as deposit_count
                        FROM 
                            deposits d
                        JOIN 
                            users u ON d.user_id = u.id
                        WHERE 
                            d.status = 'approved' AND
                            d.created_at BETWEEN ? AND ?
                        GROUP BY 
                            d.user_id
                        ORDER BY 
                            total_deposited DESC
                        LIMIT 10";

$stmt = $conn->prepare($monthly_leaders_query);
$stmt->bind_param("ss", $current_month_start, $current_month_end);
$stmt->execute();
$monthly_result = $stmt->get_result();
$monthly_leaders = [];

if ($monthly_result) {
  while ($row = $monthly_result->fetch_assoc()) {
    $monthly_leaders[] = $row;
  }
}

// Get the user's current ranking
$user_rank_query = "SELECT user_rank FROM (
                        SELECT 
                            user_id,
                            @rank := @rank + 1 as user_rank
                        FROM 
                            (SELECT 
                                d.user_id,
                                SUM(d.amount) as total_deposited
                            FROM 
                                deposits d
                            WHERE 
                                d.status = 'approved'
                            GROUP BY 
                                d.user_id
                            ORDER BY 
                                total_deposited DESC
                            ) ranked_users,
                            (SELECT @rank := 0) r
                    ) rankings
                    WHERE user_id = ?";

$stmt = $conn->prepare($user_rank_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$rank_result = $stmt->get_result();
$user_rank = 0;

if ($rank_result && $row = $rank_result->fetch_assoc()) {
  $user_rank = $row['user_rank'];
}

// Get user's total deposits
$user_deposits_query = "SELECT 
                            SUM(amount) as total_deposited
                        FROM 
                            deposits
                        WHERE 
                            user_id = ? AND
                            status = 'approved'";

$stmt = $conn->prepare($user_deposits_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$deposits_result = $stmt->get_result();
$user_total_deposits = 0;

if ($deposits_result && $row = $deposits_result->fetch_assoc()) {
  $user_total_deposits = $row['total_deposited'] ?? 0;
}

// Check if bonuses were already distributed for the current month
$current_month = date('Y-m');
$bonus_check_query = "SELECT id FROM leaderboard_bonuses WHERE bonus_month = ? LIMIT 1";
$stmt = $conn->prepare($bonus_check_query);
$stmt->bind_param("s", $current_month);
$stmt->execute();
$bonus_result = $stmt->get_result();
$bonuses_distributed = ($bonus_result->num_rows > 0);

// Function to format a number with a suffix (1st, 2nd, 3rd, etc.)
function getOrdinalSuffix($number)
{
  if (!in_array(($number % 100), array(11, 12, 13))) {
    switch ($number % 10) {
      case 1:
        return $number . 'st';
      case 2:
        return $number . 'nd';
      case 3:
        return $number . 'rd';
    }
  }
  return $number . 'th';
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <?php include 'includes/head.php'; ?>
  <title>Leaderboard - AutoProftX</title>
  <style>
    .leaderboard-bg {
      background: linear-gradient(to bottom, rgba(17, 24, 39, 0.8), rgba(17, 24, 39, 1)), url('assets/images/leaderboard-bg.jpg');
      background-size: cover;
      background-position: center;
    }

    .rank-1 {
      background: linear-gradient(135deg, #FFD700, #FFA500);
      color: #000;
    }

    .rank-2 {
      background: linear-gradient(135deg, #C0C0C0, #A9A9A9);
      color: #000;
    }

    .rank-3 {
      background: linear-gradient(135deg, #CD7F32, #8B4513);
      color: #fff;
    }

    .trophy-icon {
      position: absolute;
      top: -10px;
      right: -10px;
      width: 40px;
      height: 40px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 10;
    }

    .rank-card {
      transition: all 0.3s ease;
    }

    .rank-card:hover {
      transform: translateY(-5px);
    }

    /* Fix for overflow issues */
    .container,
    main,
    .grid,
    select,
    input,
    textarea {
      max-width: 100%;
      overflow-x: hidden;
    }

    select {
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    /* Leaderboard specific styling */
    .leaderboard-bg {
      background: linear-gradient(to right, rgba(30, 41, 59, 0.95), rgba(30, 41, 59, 0.8)),
        url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100"><rect width="100" height="100" fill="none"/><path d="M0 0L100 100M100 0L0 100" stroke="rgba(255,255,255,0.05)" stroke-width="1"/></svg>');
      background-size: cover;
      box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3);
    }

    .stat-card {
      transition: all 0.3s ease;
      transform: translateY(0);
    }

    .stat-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.4);
    }

    .prize-card {
      background: linear-gradient(135deg, #92400e, #78350f);
      box-shadow: 0 10px 15px -3px rgba(146, 64, 14, 0.3);
      position: relative;
      overflow: hidden;
      transition: all 0.3s ease;
    }

    .prize-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 15px 20px -5px rgba(146, 64, 14, 0.4);
    }

    .prize-card::after {
      content: "";
      position: absolute;
      top: -50%;
      left: -50%;
      width: 200%;
      height: 200%;
      background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
      opacity: 0;
      transition: opacity 0.6s ease;
    }

    .prize-card:hover::after {
      opacity: 1;
    }

    .ribbon {
      position: absolute;
      top: 0;
      right: 0;
      width: 150px;
      height: 150px;
      overflow: hidden;
    }

    .ribbon-content {
      position: absolute;
      top: 30px;
      right: -35px;
      background-color: #eab308;
      color: #000;
      padding: 5px 40px;
      transform: rotate(45deg);
      font-weight: bold;
      font-size: 0.75rem;
      box-shadow: 0 3px 10px rgba(0, 0, 0, 0.3);
    }

    /* Responsive adjustments for small screens */
    @media (max-width: 640px) {
      .leaderboard-bg {
        padding: 1.5rem !important;
      }

      .stat-card {
        width: 100%;
        max-width: 100%;
      }
    }
  </style>
</head>

<body class="bg-gray-900 text-white font-sans min-h-screen flex flex-col">
  <?php include 'includes/navbar.php'; ?>
  <?php include 'includes/pre-loader.php'; ?>

  <!-- Main Content -->
  <main style="display: none;" id="main-content" class="flex-grow py-6">
    <div class="container mx-auto px-4 sm:px-6 lg:px-8">
      <!-- CSS Fixes for Overflow and Enhanced Styling -->
      <style>
        /* Fix for overflow issues */
        .container,
        main,
        .grid,
        select,
        input,
        textarea {
          max-width: 100%;
          overflow-x: hidden;
        }

        select {
          text-overflow: ellipsis;
          white-space: nowrap;
        }

        /* Leaderboard specific styling */
        .leaderboard-bg {
          background: linear-gradient(to right, rgba(30, 41, 59, 0.95), rgba(30, 41, 59, 0.8)),
            url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100"><rect width="100" height="100" fill="none"/><path d="M0 0L100 100M100 0L0 100" stroke="rgba(255,255,255,0.05)" stroke-width="1"/></svg>');
          background-size: cover;
          box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3);
        }

        .stat-card {
          transition: all 0.3s ease;
          transform: translateY(0);
        }

        .stat-card:hover {
          transform: translateY(-5px);
          box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.4);
        }

        .prize-card {
          background: linear-gradient(135deg, #92400e, #78350f);
          box-shadow: 0 10px 15px -3px rgba(146, 64, 14, 0.3);
          position: relative;
          overflow: hidden;
          transition: all 0.3s ease;
        }

        .prize-card:hover {
          transform: translateY(-5px);
          box-shadow: 0 15px 20px -5px rgba(146, 64, 14, 0.4);
        }

        .prize-card::after {
          content: "";
          position: absolute;
          top: -50%;
          left: -50%;
          width: 200%;
          height: 200%;
          background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
          opacity: 0;
          transition: opacity 0.6s ease;
        }

        .prize-card:hover::after {
          opacity: 1;
        }

        .ribbon {
          position: absolute;
          top: 0;
          right: 0;
          width: 150px;
          height: 150px;
          overflow: hidden;
        }

        .ribbon-content {
          position: absolute;
          top: 30px;
          right: -35px;
          background-color: #eab308;
          color: #000;
          padding: 5px 40px;
          transform: rotate(45deg);
          font-weight: bold;
          font-size: 0.75rem;
          box-shadow: 0 3px 10px rgba(0, 0, 0, 0.3);
        }

        /* Responsive adjustments for small screens */
        @media (max-width: 640px) {
          .leaderboard-bg {
            padding: 1.5rem !important;
          }

          .stat-card {
            width: 100%;
            max-width: 100%;
          }
        }
      </style>

      <!-- Improved Leaderboard Section -->
      <div class="leaderboard-bg rounded-xl p-6 sm:p-8 mb-8 text-center relative overflow-hidden">
        <?php include 'includes/mobile-bar.php'; ?>

        <!-- Subtle animated background effect -->
        <div class="absolute inset-0 bg-yellow-500 opacity-5 animate-pulse"></div>

        <!-- Title section with icon -->
        <div class="relative z-10 mb-6">
          <div class="inline-flex items-center justify-center mb-3 bg-yellow-500 text-black p-2 rounded-full">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
            </svg>
          </div>
          <h1 class="text-2xl sm:text-3xl md:text-4xl font-bold mb-2">Deposit Leaderboard</h1>
          <p class="text-base sm:text-lg text-gray-300 max-w-2xl mx-auto">Compete with other investors and win exclusive bonuses!</p>
        </div>

        <div class="flex flex-col sm:flex-row justify-center items-stretch space-y-4 sm:space-y-0 sm:space-x-4 md:space-x-6">
          <!-- User Rank Card -->
          <div class="stat-card bg-gray-800 bg-opacity-80 backdrop-blur-sm p-4 sm:p-6 rounded-xl border border-gray-700 text-center flex-1 flex flex-col justify-between">
            <div class="mb-2">
              <div class="flex items-center justify-center mb-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-yellow-500 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                </svg>
                <p class="text-gray-400 text-sm sm:text-base">Your Rank</p>
              </div>
            </div>
            <h2 class="text-3xl sm:text-4xl font-bold mb-2"><?php echo getOrdinalSuffix($user_rank); ?></h2>
            <p class="text-xs sm:text-sm text-gray-400">Out of all depositors</p>
          </div>

          <!-- User Total Deposits -->
          <div class="stat-card bg-gray-800 bg-opacity-80 backdrop-blur-sm p-4 sm:p-6 rounded-xl border border-gray-700 text-center flex-1 flex flex-col justify-between">
            <div class="mb-2">
              <div class="flex items-center justify-center mb-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-yellow-500 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                <p class="text-gray-400 text-sm sm:text-base">Your Total Deposits</p>
              </div>
            </div>
            <h2 class="text-3xl sm:text-4xl font-bold mb-2">$<?php echo number_format($user_total_deposits, 0); ?></h2>
            <p class="text-xs sm:text-sm text-gray-400">Keep depositing to rank higher!</p>
          </div>

          <!-- Prize Info with Ribbon -->
          <div class="prize-card p-4 sm:p-6 rounded-xl border border-yellow-700 text-center flex-1 flex flex-col justify-between relative">
            <!-- Ribbon for extra attention -->
            <div class="ribbon hidden sm:block">
              <div class="ribbon-content text-xs">TOP PRIZE</div>
            </div>

            <div class="mb-2">
              <div class="flex items-center justify-center mb-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-yellow-300 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <p class="text-yellow-300 text-sm sm:text-base">Monthly Prizes</p>
              </div>
            </div>
            <h2 class="text-2xl sm:text-3xl font-bold mb-2">Up to $5,000</h2>
            <p class="text-xs sm:text-sm text-yellow-300">For top 3 depositors each month</p>
          </div>
        </div>
      </div>

      <!-- Tabs -->
      <div class="mb-8">
        <div class="flex border-b border-gray-700">
          <button class="py-2 px-4 font-medium text-yellow-500 border-b-2 border-yellow-500 focus:outline-none tab-btn active" data-tab="all-time">
            All-Time Leaders
          </button>
          <button class="py-2 px-4 font-medium text-gray-400 hover:text-yellow-500 focus:outline-none tab-btn" data-tab="monthly">
            This Month
          </button>
          <button class="py-2 px-4 font-medium text-gray-400 hover:text-yellow-500 focus:outline-none tab-btn" data-tab="rules">
            Rules & Rewards
          </button>
        </div>
      </div>

      <!-- All-Time Leaderboard Tab -->
      <div id="all-time-tab" class="tab-content">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
          <?php if (count($top_depositors) > 0): ?>
            <!-- Top 3 Cards -->
            <?php for ($i = 0; $i < min(3, count($top_depositors)); $i++):
              $rank = $i + 1;
              $depositor = $top_depositors[$i];
            ?>
              <div class="rank-card bg-gray-800 rounded-xl shadow-lg overflow-hidden border border-gray-700 relative">
                <!-- Trophy Icon -->
                <div class="trophy-icon rank-<?php echo $rank; ?>">
                  <i class="fas fa-trophy"></i>
                </div>

                <!-- Rank Header -->
                <div class="p-4 rank-<?php echo $rank; ?> flex justify-between items-center">
                  <h3 class="font-bold"><?php echo getOrdinalSuffix($rank); ?> Place</h3>
                  <span class="text-sm"><?php echo ($depositor['user_id'] == $user_id) ? 'You' : ''; ?></span>
                </div>

                <!-- User Info -->
                <div class="p-6">
                  <div class="flex items-center mb-4">
                    <div class="h-12 w-12 rounded-full bg-gray-700 flex items-center justify-center text-xl font-bold mr-4">
                      <?php echo strtoupper(substr($depositor['full_name'], 0, 1)); ?>
                    </div>
                    <div>
                      <h4 class="font-bold"><?php
                                            if ($depositor['user_id'] == $user_id) {
                                              echo 'You';
                                            } else {
                                              $name_parts = explode(' ', $depositor['full_name']);
                                              echo $name_parts[0] . ' ' . substr($name_parts[count($name_parts) - 1], 0, 1) . '.';
                                            }
                                            ?></h4>
                      <p class="text-gray-400 text-sm">ID: <?php echo substr($depositor['referral_code'], 0, 4) . '...'; ?></p>
                    </div>
                  </div>

                  <div class="flex justify-between items-center text-center">
                    <div>
                      <p class="text-gray-400 text-xs">Total Deposits</p>
                      <p class="text-xl font-bold text-yellow-500">$<?php echo number_format($depositor['total_deposited'], 0); ?></p>
                    </div>
                    <div>
                      <p class="text-gray-400 text-xs">Deposit Count</p>
                      <p class="text-xl font-bold"><?php echo $depositor['deposit_count']; ?></p>
                    </div>
                  </div>
                </div>
              </div>
            <?php endfor; ?>
          <?php else: ?>
            <div class="col-span-3 text-center py-12 bg-gray-800 rounded-xl border border-gray-700">
              <i class="fas fa-trophy text-4xl text-gray-600 mb-4"></i>
              <p class="text-gray-400">No deposit data available yet. Start depositing to appear on the leaderboard!</p>
            </div>
          <?php endif; ?>
        </div>
        <!-- Leaderboard Table with Enhanced User Experience -->
        <div class="bg-gray-800 rounded-xl shadow-lg overflow-hidden border border-gray-700">
          <div class="p-4 sm:p-6 border-b border-gray-700 flex justify-between items-center">
            <h3 class="font-bold text-lg flex items-center">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
              </svg>
              All-Time Top Depositors
            </h3>
            <div class="hidden sm:block">
              <span class="text-xs text-gray-400">Updated daily</span>
            </div>
          </div>

          <?php if (count($top_depositors) > 0): ?>
            <!-- Mobile view (card layout) -->
            <div class="sm:hidden">
              <?php foreach ($top_depositors as $index => $depositor): ?>
                <div class="p-4 border-b border-gray-700 <?php echo ($depositor['user_id'] == $user_id) ? 'bg-gray-700 bg-opacity-50' : ''; ?>">
                  <div class="flex justify-between items-center mb-2">
                    <div class="flex items-center">
                      <span class="flex items-center justify-center h-6 w-6 rounded-full <?php echo ($index < 3) ? 'bg-yellow-500 text-black' : 'bg-gray-700 text-gray-300'; ?> text-xs font-bold mr-2">
                        <?php echo $index + 1; ?>
                      </span>
                      <div class="h-8 w-8 rounded-full bg-gray-700 flex items-center justify-center font-medium mr-2">
                        <?php echo strtoupper(substr($depositor['full_name'], 0, 1)); ?>
                      </div>
                      <div>
                        <div class="font-medium">
                          <?php
                          if ($depositor['user_id'] == $user_id) {
                            echo 'You';
                          } else {
                            $name_parts = explode(' ', $depositor['full_name']);
                            echo $name_parts[0] . ' ' . substr($name_parts[count($name_parts) - 1], 0, 1) . '.';
                          }
                          ?>
                          <?php if ($depositor['user_id'] == $user_id): ?>
                            <span class="ml-1 px-1.5 py-0.5 text-xs rounded-full bg-yellow-900 text-yellow-300">You</span>
                          <?php endif; ?>
                        </div>
                        <div class="text-xs text-gray-400">ID: <?php echo substr($depositor['referral_code'], 0, 4) . '...'; ?></div>
                      </div>
                    </div>
                    <div class="text-right">
                      <div class="text-sm font-medium text-yellow-500">$<?php echo number_format($depositor['total_deposited'], 0); ?></div>
                      <div class="text-xs text-gray-400"><?php echo $depositor['deposit_count']; ?> deposits</div>
                    </div>
                  </div>
                  <?php if ($index < 3): ?>
                    <div class="mt-1 text-xs text-yellow-400 flex items-center">
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                      </svg>
                      <?php if ($index === 0): ?>
                        Gold Trophy Winner
                      <?php elseif ($index === 1): ?>
                        Silver Trophy Winner
                      <?php else: ?>
                        Bronze Trophy Winner
                      <?php endif; ?>
                    </div>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>

            <!-- Desktop view (table layout) -->
            <div class="hidden sm:block overflow-x-auto">
              <table class="w-full">
                <thead class="bg-gray-900">
                  <tr>
                    <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Rank</th>
                    <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">User</th>
                    <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Total Deposited</th>
                    <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Deposits</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-700">
                  <?php foreach ($top_depositors as $index => $depositor): ?>
                    <tr class="<?php echo ($depositor['user_id'] == $user_id) ? 'bg-gray-700 bg-opacity-50' : ''; ?> hover:bg-gray-700 transition duration-150">
                      <td class="px-4 sm:px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                          <?php if ($index < 3): ?>
                            <div class="flex items-center justify-center h-6 w-6 rounded-full 
                      <?php echo ($index === 0) ? 'bg-yellow-500' : (($index === 1) ? 'bg-gray-300' : 'bg-yellow-700'); ?> 
                      text-black text-xs font-bold mr-2">
                              <?php echo $index + 1; ?>
                            </div>
                            <span class="text-sm font-medium
                      <?php echo ($index === 0) ? 'text-yellow-500' : (($index === 1) ? 'text-gray-300' : 'text-yellow-700'); ?>">
                              <?php if ($index === 0): ?>
                                <span class="hidden md:inline">Gold</span> Trophy
                              <?php elseif ($index === 1): ?>
                                <span class="hidden md:inline">Silver</span> Trophy
                              <?php else: ?>
                                <span class="hidden md:inline">Bronze</span> Trophy
                              <?php endif; ?>
                            </span>
                          <?php else: ?>
                            <span class="text-sm font-medium text-gray-300">
                              <?php echo getOrdinalSuffix($index + 1); ?>
                            </span>
                          <?php endif; ?>
                        </div>
                      </td>
                      <td class="px-4 sm:px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                          <div class="h-8 w-8 rounded-full bg-gray-700 flex items-center justify-center font-medium mr-3">
                            <?php echo strtoupper(substr($depositor['full_name'], 0, 1)); ?>
                          </div>
                          <div>
                            <div class="text-sm font-medium">
                              <?php
                              if ($depositor['user_id'] == $user_id) {
                                echo 'You';
                              } else {
                                $name_parts = explode(' ', $depositor['full_name']);
                                echo $name_parts[0] . ' ' . substr($name_parts[count($name_parts) - 1], 0, 1) . '.';
                              }
                              ?>
                              <?php if ($depositor['user_id'] == $user_id): ?>
                                <span class="ml-2 px-2 py-0.5 text-xs rounded-full bg-yellow-900 text-yellow-300">You</span>
                              <?php endif; ?>
                            </div>
                            <div class="text-xs text-gray-400">ID: <?php echo substr($depositor['referral_code'], 0, 4) . '...'; ?></div>
                          </div>
                        </div>
                      </td>
                      <td class="px-4 sm:px-6 py-4 whitespace-nowrap">
                        <span class="text-sm font-medium text-yellow-500">$<?php echo number_format($depositor['total_deposited'], 0); ?></span>
                      </td>
                      <td class="px-4 sm:px-6 py-4 whitespace-nowrap">
                        <span class="text-sm"><?php echo $depositor['deposit_count']; ?> deposits</span>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <div class="p-8 text-center">
              <div class="inline-flex rounded-full bg-gray-900 p-4 mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                </svg>
              </div>
              <p class="text-gray-400 mb-2">No deposit data available yet.</p>
              <p class="text-gray-500 text-sm">Start depositing to appear on the leaderboard!</p>
              <button class="mt-4 bg-yellow-600 hover:bg-yellow-700 text-black font-medium py-2 px-4 rounded-lg transition-all duration-200 text-sm">
                Make Your First Deposit
              </button>
            </div>
          <?php endif; ?>
        </div>

        <!-- CSS for better mobile experience -->
        <style>
          /* Prevent horizontal overflow */
          .overflow-x-auto {
            -webkit-overflow-scrolling: touch;
          }

          /* Better mobile tap targets */
          @media (max-width: 640px) {
            .p-4 {
              padding: 1rem !important;
            }

            /* Add gradient indicators for horizontal scroll */
            .overflow-x-auto {
              position: relative;
            }

            .overflow-x-auto::after {
              content: "";
              position: absolute;
              top: 0;
              right: 0;
              bottom: 0;
              width: 20px;
              background: linear-gradient(to right, rgba(31, 41, 55, 0), rgba(31, 41, 55, 0.7));
              pointer-events: none;
              opacity: 0;
              transition: opacity 0.3s ease;
            }

            .overflow-x-auto:not(.at-end)::after {
              opacity: 1;
            }
          }
        </style>

        <!-- Optional JavaScript for horizontal scroll indicators -->
        <script>
          // Check if we need to show the scroll indicator
          document.addEventListener('DOMContentLoaded', function() {
            const scrollContainers = document.querySelectorAll('.overflow-x-auto');

            scrollContainers.forEach(container => {
              const checkScroll = () => {
                if (container.scrollWidth > container.clientWidth) {
                  if (container.scrollLeft + container.clientWidth >= container.scrollWidth - 5) {
                    container.classList.add('at-end');
                  } else {
                    container.classList.remove('at-end');
                  }
                }
              };

              container.addEventListener('scroll', checkScroll);
              checkScroll(); // Initial check

              // Recheck on window resize
              window.addEventListener('resize', checkScroll);
            });
          });
        </script>
      </div>

      <!-- Monthly Leaderboard Tab -->
      <div id="monthly-tab" class="tab-content hidden">
        <div class="bg-yellow-900 bg-opacity-20 border border-yellow-800 text-yellow-300 px-4 py-3 rounded-md mb-6 flex items-start">
          <i class="fas fa-info-circle mt-1 mr-3"></i>
          <div>
            <p class="font-medium">Monthly Bonus Distribution</p>
            <p class="text-sm mt-1">
              <?php if ($bonuses_distributed): ?>
                Bonuses for <?php echo date('F Y'); ?> have already been distributed to the top depositors.
              <?php else: ?>
                Bonuses for the top 3 depositors will be distributed at the end of <?php echo date('F Y'); ?>.
              <?php endif; ?>
            </p>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
          <?php if (count($monthly_leaders) > 0): ?>
            <!-- Top 3 Cards -->
            <?php for ($i = 0; $i < min(3, count($monthly_leaders)); $i++):
              $rank = $i + 1;
              $depositor = $monthly_leaders[$i];

              // Determine bonus amount based on rank
              $bonus_amount = 0;
              switch ($rank) {
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
              <div class="rank-card bg-gray-800 rounded-xl shadow-lg overflow-hidden border border-gray-700 relative">
                <!-- Trophy Icon -->
                <div class="trophy-icon rank-<?php echo $rank; ?>">
                  <i class="fas fa-trophy"></i>
                </div>

                <!-- Rank Header -->
                <div class="p-4 rank-<?php echo $rank; ?> flex justify-between items-center">
                  <h3 class="font-bold"><?php echo getOrdinalSuffix($rank); ?> Place</h3>
                  <span class="text-sm"><?php echo ($depositor['user_id'] == $user_id) ? 'You' : ''; ?></span>
                </div>

                <!-- User Info -->
                <div class="p-6">
                  <div class="flex items-center mb-4">
                    <div class="h-12 w-12 rounded-full bg-gray-700 flex items-center justify-center text-xl font-bold mr-4">
                      <?php echo strtoupper(substr($depositor['full_name'], 0, 1)); ?>
                    </div>
                    <div>
                      <h4 class="font-bold"><?php
                                            if ($depositor['user_id'] == $user_id) {
                                              echo 'You';
                                            } else {
                                              $name_parts = explode(' ', $depositor['full_name']);
                                              echo $name_parts[0] . ' ' . substr($name_parts[count($name_parts) - 1], 0, 1) . '.';
                                            }
                                            ?></h4>
                      <p class="text-gray-400 text-sm">ID: <?php echo substr($depositor['referral_code'], 0, 4) . '...'; ?></p>
                    </div>
                  </div>

                  <div class="flex justify-between items-center text-center">
                    <div>
                      <p class="text-gray-400 text-xs">Monthly Deposits</p>
                      <p class="text-xl font-bold text-yellow-500">Rs:<?php echo number_format($depositor['total_deposited'], 0); ?></p>
                    </div>
                    <div>
                      <p class="text-yellow-400 text-xs">Bonus Prize</p>
                      <p class="text-xl font-bold">Rs:<?php echo number_format($bonus_amount, 0); ?></p>
                    </div>
                  </div>
                </div>
              </div>
            <?php endfor; ?>
          <?php else: ?>
            <div class="col-span-3 text-center py-12 bg-gray-800 rounded-xl border border-gray-700">
              <i class="fas fa-trophy text-4xl text-gray-600 mb-4"></i>
              <p class="text-gray-400">No deposit data available for this month yet. Start depositing to appear on the leaderboard!</p>
            </div>
          <?php endif; ?>
        </div>

        <!-- Monthly Leaderboard Table -->
        <div class="bg-gray-800 rounded-xl shadow-lg overflow-hidden border border-gray-700">
          <div class="p-6 border-b border-gray-700">
            <h3 class="font-bold">This Month's Top Depositors (<?php echo date('F Y'); ?>)</h3>
          </div>

          <?php if (count($monthly_leaders) > 0): ?>
            <div class="overflow-x-auto">
              <table class="w-full">
                <thead class="bg-gray-900">
                  <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Rank</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">User</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Monthly Deposits</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Bonus Prize</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-700">
                  <?php foreach ($monthly_leaders as $index => $depositor):
                    // Determine bonus amount based on rank
                    $bonus_amount = 0;
                    switch ($index + 1) {
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
                    <tr class="<?php echo ($depositor['user_id'] == $user_id) ? 'bg-gray-700 bg-opacity-50' : ''; ?> hover:bg-gray-700">
                      <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                          <span class="text-sm font-medium <?php echo ($index < 3) ? 'text-yellow-500' : 'text-gray-300'; ?>">
                            <?php echo getOrdinalSuffix($index + 1); ?>
                            <?php if ($index < 3): ?>
                              <i class="fas fa-trophy ml-1"></i>
                            <?php endif; ?>
                          </span>
                        </div>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                          <div class="h-8 w-8 rounded-full bg-gray-700 flex items-center justify-center font-medium mr-3">
                            <?php echo strtoupper(substr($depositor['full_name'], 0, 1)); ?>
                          </div>
                          <div>
                            <div class="text-sm font-medium">
                              <?php
                              if ($depositor['user_id'] == $user_id) {
                                echo 'You';
                              } else {
                                $name_parts = explode(' ', $depositor['full_name']);
                                echo $name_parts[0] . ' ' . substr($name_parts[count($name_parts) - 1], 0, 1) . '.';
                              }
                              ?>
                              <?php if ($depositor['user_id'] == $user_id): ?>
                                <span class="ml-2 px-2 py-0.5 text-xs rounded-full bg-yellow-900 text-yellow-300">You</span>
                              <?php endif; ?>
                            </div>
                            <div class="text-xs text-gray-400">ID: <?php echo substr($depositor['referral_code'], 0, 4) . '...'; ?></div>
                          </div>
                        </div>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap">
                        <span class="text-sm font-medium text-yellow-500">Rs:<?php echo number_format($depositor['total_deposited'], 0); ?></span>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap">
                        <?php if ($index < 3): ?>
                          <span class="text-sm font-medium text-green-400">Rs:<?php echo number_format($bonus_amount, 0); ?></span>
                        <?php else: ?>
                          <span class="text-sm text-gray-400">-</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <div class="p-8 text-center">
              <p class="text-gray-400">No deposit data available for this month yet. Start depositing to appear on the leaderboard!</p>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Rules & Rewards Tab -->
      <div id="rules-tab" class="tab-content hidden">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
          <!-- Rules Section -->
          <div class="bg-gray-800 rounded-xl shadow-lg overflow-hidden border border-gray-700">
            <div class="p-6 border-b border-gray-700">
              <h3 class="font-bold flex items-center">
                <i class="fas fa-scroll text-yellow-500 mr-2"></i>
                Leaderboard Rules
              </h3>
            </div>
            <div class="p-6">
              <ul class="space-y-4">
                <li class="flex">
                  <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                  <div>
                    <p class="font-medium">Eligibility</p>
                    <p class="text-sm text-gray-400">All registered users are automatically entered into the competition upon making a deposit.</p>
                  </div>
                </li>
                <li class="flex">
                  <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                  <div>
                    <p class="font-medium">Deposits Count</p>
                    <p class="text-sm text-gray-400">Only approved deposits are counted towards the leaderboard ranking.</p>
                  </div>
                </li>
                <li class="flex">
                  <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                  <div>
                    <p class="font-medium">Calculation Period</p>
                    <p class="text-sm text-gray-400">Monthly leaderboard resets on the 1st day of each month at 00:00. All-time leaderboard is cumulative.</p>
                  </div>
                </li>
                <li class="flex">
                  <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                  <div>
                    <p class="font-medium">Ranking Criteria</p>
                    <p class="text-sm text-gray-400">Users are ranked based on the total deposit amount. In case of a tie, the user who reached the amount first wins.</p>
                  </div>
                </li>
                <li class="flex">
                  <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                  <div>
                    <p class="font-medium">Verification</p>
                    <p class="text-sm text-gray-400">All deposit transactions are verified by our system. Any fraudulent activity will result in disqualification.</p>
                  </div>
                </li>
                <li class="flex">
                  <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                  <div>
                    <p class="font-medium">Rewards Distribution</p>
                    <p class="text-sm text-gray-400">Monthly rewards are distributed automatically within the first 3 days of the following month.</p>
                  </div>
                </li>
              </ul>
            </div>
          </div>

          <!-- Rewards Section -->
          <div class="bg-gray-800 rounded-xl shadow-lg overflow-hidden border border-gray-700">
            <div class="p-6 border-b border-gray-700">
              <h3 class="font-bold flex items-center">
                <i class="fas fa-gift text-yellow-500 mr-2"></i>
                Monthly Rewards
              </h3>
            </div>
            <div class="p-6">
              <div class="space-y-6">
                <!-- 1st Place Reward -->
                <div class="bg-gray-700 rounded-lg p-4 border border-gray-600 relative overflow-hidden">
                  <div class="absolute top-0 right-0 h-16 w-16">
                    <div class="absolute transform rotate-45 bg-yellow-500 text-gray-900 font-bold text-xs py-1 right-[-35px] top-[32px] w-[170px] text-center">
                      1st PLACE
                    </div>
                  </div>

                  <div class="flex items-center mb-3">
                    <div class="h-12 w-12 rounded-full bg-yellow-600 flex items-center justify-center text-yellow-200 mr-4">
                      <i class="fas fa-trophy text-xl"></i>
                    </div>
                    <div>
                      <h4 class="font-bold text-xl">Rs:5,000 Bonus</h4>
                      <p class="text-gray-400 text-sm">Top depositor of the month</p>
                    </div>
                  </div>

                  <ul class="text-sm text-gray-300 space-y-2 pl-4">
                    <li class="flex items-start">
                      <i class="fas fa-check text-green-500 mt-1 mr-2"></i>
                      <span>Cash bonus credited directly to your wallet</span>
                    </li>
                    <li class="flex items-start">
                      <i class="fas fa-check text-green-500 mt-1 mr-2"></i>
                      <span>No withdrawal restrictions on bonus amount</span>
                    </li>
                    <li class="flex items-start">
                      <i class="fas fa-check text-green-500 mt-1 mr-2"></i>
                      <span>Exclusive 1st place badge displayed on your profile for the month</span>
                    </li>
                  </ul>
                </div>

                <!-- 2nd Place Reward -->
                <div class="bg-gray-700 rounded-lg p-4 border border-gray-600 relative overflow-hidden">
                  <div class="absolute top-0 right-0 h-16 w-16">
                    <div class="absolute transform rotate-45 bg-gray-400 text-gray-900 font-bold text-xs py-1 right-[-35px] top-[32px] w-[170px] text-center">
                      2nd PLACE
                    </div>
                  </div>

                  <div class="flex items-center mb-3">
                    <div class="h-12 w-12 rounded-full bg-gray-500 flex items-center justify-center text-gray-200 mr-4">
                      <i class="fas fa-award text-xl"></i>
                    </div>
                    <div>
                      <h4 class="font-bold text-xl">Rs:3,000 Bonus</h4>
                      <p class="text-gray-400 text-sm">Second highest depositor</p>
                    </div>
                  </div>

                  <ul class="text-sm text-gray-300 space-y-2 pl-4">
                    <li class="flex items-start">
                      <i class="fas fa-check text-green-500 mt-1 mr-2"></i>
                      <span>Cash bonus credited directly to your wallet</span>
                    </li>
                    <li class="flex items-start">
                      <i class="fas fa-check text-green-500 mt-1 mr-2"></i>
                      <span>No withdrawal restrictions on bonus amount</span>
                    </li>
                  </ul>
                </div>

                <!-- 3rd Place Reward -->
                <div class="bg-gray-700 rounded-lg p-4 border border-gray-600 relative overflow-hidden">
                  <div class="absolute top-0 right-0 h-16 w-16">
                    <div class="absolute transform rotate-45 bg-yellow-700 text-gray-200 font-bold text-xs py-1 right-[-35px] top-[32px] w-[170px] text-center">
                      3rd PLACE
                    </div>
                  </div>

                  <div class="flex items-center mb-3">
                    <div class="h-12 w-12 rounded-full bg-yellow-800 flex items-center justify-center text-yellow-200 mr-4">
                      <i class="fas fa-medal text-xl"></i>
                    </div>
                    <div>
                      <h4 class="font-bold text-xl">Rs:2,000 Bonus</h4>
                      <p class="text-gray-400 text-sm">Third highest depositor</p>
                    </div>
                  </div>

                  <ul class="text-sm text-gray-300 space-y-2 pl-4">
                    <li class="flex items-start">
                      <i class="fas fa-check text-green-500 mt-1 mr-2"></i>
                      <span>Cash bonus credited directly to your wallet</span>
                    </li>
                    <li class="flex items-start">
                      <i class="fas fa-check text-green-500 mt-1 mr-2"></i>
                      <span>No withdrawal restrictions on bonus amount</span>
                    </li>
                  </ul>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- FAQ Section -->
        <div class="mt-8 bg-gray-800 rounded-xl shadow-lg overflow-hidden border border-gray-700">
          <div class="p-6 border-b border-gray-700">
            <h3 class="font-bold flex items-center">
              <i class="fas fa-question-circle text-yellow-500 mr-2"></i>
              Frequently Asked Questions
            </h3>
          </div>
          <div class="p-6">
            <div class="space-y-6">
              <div>
                <h4 class="font-medium text-yellow-500 mb-2">How is my rank calculated?</h4>
                <p class="text-sm text-gray-300">Your rank is determined by the total amount you have deposited. The more you deposit, the higher your ranking on the leaderboard.</p>
              </div>

              <div>
                <h4 class="font-medium text-yellow-500 mb-2">When do monthly rewards get distributed?</h4>
                <p class="text-sm text-gray-300">Monthly rewards are automatically distributed within the first 3 days of the following month directly to your account wallet.</p>
              </div>

              <div>
                <h4 class="font-medium text-yellow-500 mb-2">Can I withdraw my bonus immediately?</h4>
                <p class="text-sm text-gray-300">Yes, all leaderboard bonuses are credited without any wagering requirements. You can withdraw them immediately or use them for investments.</p>
              </div>

              <div>
                <h4 class="font-medium text-yellow-500 mb-2">Do all deposits count towards the leaderboard?</h4>
                <p class="text-sm text-gray-300">Only approved deposits count towards your ranking. Pending or rejected deposits are not included in the calculation.</p>
              </div>

              <div>
                <h4 class="font-medium text-yellow-500 mb-2">What happens if there's a tie between users?</h4>
                <p class="text-sm text-gray-300">In case of a tie in deposit amounts, the user who reached that amount first will be ranked higher on the leaderboard.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>

  <?php include 'includes/footer.php'; ?>

  <script>
    $(document).ready(function() {
      // Show main content when page is fully loaded
      $("#pre-loader").fadeOut(500, function() {
        $("#main-content").fadeIn(500);
      });

      // Tab switching functionality
      $(".tab-btn").click(function() {
        var tab = $(this).data("tab");

        // Update active button
        $(".tab-btn").removeClass("active text-yellow-500 border-yellow-500").addClass("text-gray-400");
        $(this).removeClass("text-gray-400").addClass("active text-yellow-500 border-yellow-500");

        // Show active tab
        $(".tab-content").addClass("hidden");
        $("#" + tab + "-tab").removeClass("hidden");
      });
    });
  </script>
</body>

</html>