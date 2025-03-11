<?php

// Start session
session_start();

// Database connection
include '../config/db.php';
include 'includes/staking-functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
  header("Location: ../login.php");
  exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['full_name'] ?? 'User';

// Process stake creation if form submitted
$stake_result = null;
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_stake'])) {
  $plan_id = $_POST['plan_id'] ?? 0;
  $amount = $_POST['amount'] ?? 0;
  $stake_result = createStake($user_id, $plan_id, $amount);
}

// Process early withdrawal if requested
$withdrawal_result = null;
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['early_withdrawal'])) {
  $withdrawal_stake_id = $_POST['stake_id'] ?? '';
  $withdrawal_result = processEarlyWithdrawal($user_id, $withdrawal_stake_id);
}

// Get user's wallet balance
$balance = 0;
$wallet_sql = "SELECT balance FROM wallets WHERE user_id = ?";
$stmt = $conn->prepare($wallet_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
  $wallet_data = $result->fetch_assoc();
  $balance = $wallet_data['balance'];
}
$stmt->close();

// Get staking plans
$staking_plans = getActiveStakingPlans();

// Get user's active stakes
$active_stakes = getUserActiveStakes($user_id);

// Get user's completed stakes
$completed_stakes = getUserCompletedStakes($user_id);

// Get user's staking stats
$staking_stats = getUserStakingStats($user_id);

// Check for matured stakes (this should be in a cron job in production)
$processed_count = processCompletedStakes();

// Auto-refresh page if stakes were processed
if ($processed_count > 0) {
  echo '<meta http-equiv="refresh" content="1;url=staking.php">';
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <?php include 'includes/head.php'; ?>
  <style>
    .plan-card {
      transition: all 0.3s ease;
    }

    .plan-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
    }

    .active-plan {
      border: 2px solid #F59E0B !important;
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

    .apy-badge {
      position: absolute;
      top: -10px;
      right: 20px;
      padding: 5px 10px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: bold;
      background: linear-gradient(to right, #F59E0B, #D97706);
      color: black;
      box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
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
        <h1 class="text-2xl font-bold">Staking Pool</h1>
        <p class="text-gray-400 mt-2">Lock your funds for longer periods to earn higher returns</p>
      </div>

      <?php if ($stake_result !== null): ?>
        <div class="mb-6 rounded-lg p-4 <?php echo $stake_result['success'] ? 'bg-green-800 text-green-100' : 'bg-red-800 text-red-100'; ?>">
          <p class="flex items-center">
            <i class="<?php echo $stake_result['success'] ? 'fas fa-check-circle' : 'fas fa-exclamation-circle'; ?> mr-2"></i>
            <?php echo $stake_result['message']; ?>
          </p>

          <?php if ($stake_result['success']): ?>
            <p class="mt-2 text-sm">
              Expected return: $<?php echo number_format($stake_result['expected_return'], 2); ?>
            </p>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if ($withdrawal_result !== null): ?>
        <div class="mb-6 rounded-lg p-4 <?php echo $withdrawal_result['success'] ? 'bg-blue-800 text-blue-100' : 'bg-red-800 text-red-100'; ?>">
          <p class="flex items-center">
            <i class="<?php echo $withdrawal_result['success'] ? 'fas fa-check-circle' : 'fas fa-exclamation-circle'; ?> mr-2"></i>
            <?php echo $withdrawal_result['message']; ?>
          </p>
        </div>
      <?php endif; ?>

      <!-- Stats Overview -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <!-- Available Balance -->
        <div class="bg-gray-800 rounded-lg p-6 border border-gray-700 card-hover">
          <div class="flex justify-between items-start">
            <div>
              <p class="text-gray-400 text-sm">Available Balance</p>
              <h3 class="text-2xl font-bold">$<?php echo number_format($balance, 2); ?></h3>
              <p class="text-gray-400 text-sm flex items-center mt-1">
                <i class="fas fa-wallet mr-1"></i> Available to stake
              </p>
            </div>
            <div class="h-10 w-10 rounded-lg gold-gradient flex items-center justify-center">
              <i class="fas fa-wallet text-black"></i>
            </div>
          </div>
        </div>

        <!-- Total Staked -->
        <div class="bg-gray-800 rounded-lg p-6 border border-gray-700 card-hover">
          <div class="flex justify-between items-start">
            <div>
              <p class="text-gray-400 text-sm">Total Staked</p>
              <h3 class="text-2xl font-bold">$<?php echo number_format($staking_stats['total_staked'], 2); ?></h3>
              <p class="text-blue-500 text-sm flex items-center mt-1">
                <i class="fas fa-chart-pie mr-1"></i> <?php echo $staking_stats['active_stakes'] + $staking_stats['completed_stakes']; ?> stakes total
              </p>
            </div>
            <div class="h-10 w-10 rounded-lg gold-gradient flex items-center justify-center">
              <i class="fas fa-chart-line text-black"></i>
            </div>
          </div>
        </div>

        <!-- Currently Locked -->
        <div class="bg-gray-800 rounded-lg p-6 border border-gray-700 card-hover">
          <div class="flex justify-between items-start">
            <div>
              <p class="text-gray-400 text-sm">Currently Locked</p>
              <h3 class="text-2xl font-bold">$<?php echo number_format($staking_stats['current_locked'], 2); ?></h3>
              <p class="text-yellow-500 text-sm flex items-center mt-1">
                <i class="fas fa-lock mr-1"></i> <?php echo $staking_stats['active_stakes']; ?> active stakes
              </p>
            </div>
            <div class="h-10 w-10 rounded-lg gold-gradient flex items-center justify-center">
              <i class="fas fa-lock text-black"></i>
            </div>
          </div>
        </div>

        <!-- Total Earned -->
        <div class="bg-gray-800 rounded-lg p-6 border border-gray-700 card-hover">
          <div class="flex justify-between items-start">
            <div>
              <p class="text-gray-400 text-sm">Total Earned</p>
              <h3 class="text-2xl font-bold">$<?php echo number_format($staking_stats['total_earned'], 2); ?></h3> <i class="fas fa-coins mr-1"></i> From staking rewards
              </p>
            </div>
            <div class="h-10 w-10 rounded-lg gold-gradient flex items-center justify-center">
              <i class="fas fa-hand-holding-dollar text-black"></i>
            </div>
          </div>
        </div>
      </div>

      <!-- Staking Plans Section -->
      <div class="mb-8">
        <h2 class="text-xl font-bold mb-6">Staking Plans</h2>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <?php foreach ($staking_plans as $index => $plan): ?>
            <div id="plan-<?php echo $plan['id']; ?>" class="bg-gray-800 rounded-xl p-6 border border-gray-700 plan-card relative overflow-hidden">
              <div class="apy-badge">
                <?php echo number_format($plan['apy_rate'], 2); ?>% APY
              </div>

              <h3 class="text-xl font-bold mb-2"><?php echo htmlspecialchars($plan['name']); ?></h3>
              <p class="text-gray-400 text-sm mb-4"><?php echo htmlspecialchars($plan['description']); ?></p>

              <div class="space-y-3 mb-6">
                <div class="flex justify-between">
                  <span class="text-gray-400">Lock Period</span>
                  <span class="font-medium"><?php echo $plan['duration_days']; ?> Days</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-400">Min Investment</span>
                  <span class="font-medium">$<?php echo number_format($plan['min_amount'], 2); ?></span>
                </div>
                <?php if ($plan['max_amount']): ?>
                  <div class="flex justify-between">
                    <span class="text-gray-400">Max Investment</span>
                    <span class="font-medium">$<?php echo number_format($plan['max_amount'], 2); ?></span>
                  </div>
                <?php endif; ?>
                <div class="flex justify-between">
                  <span class="text-gray-400">Early Withdrawal Fee</span>
                  <span class="font-medium"><?php echo number_format($plan['early_withdrawal_fee'], 2); ?>%</span>
                </div>
              </div>

              <button onclick="selectPlan(<?php echo $plan['id']; ?>, '<?php echo addslashes($plan['name']); ?>', <?php echo $plan['min_amount']; ?>, <?php echo ($plan['max_amount'] ? $plan['max_amount'] : 'null'); ?>, <?php echo $plan['apy_rate']; ?>, <?php echo $plan['duration_days']; ?>)" class="w-full bg-gray-700 hover:bg-gradient-to-r hover:from-yellow-500 hover:to-yellow-600 hover:text-black text-white font-bold py-3 px-4 rounded-lg transition duration-300">
                Select Plan
              </button>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Create Stake Form -->
      <div id="stake-form-container" class="bg-gradient-to-r from-gray-900 to-gray-800 rounded-xl p-6 mb-8 border border-gray-700 hidden">
        <h2 class="text-xl font-bold mb-4">Create New Stake</h2>
        <p id="selected-plan-name" class="text-gray-400 mb-4"></p>

        <form id="stake-form" method="post" action="">
          <input type="hidden" id="plan_id" name="plan_id" value="">

          <div class="mb-4">
            <label for="amount" class="block text-sm font-medium text-gray-300 mb-2">Amount to Stake</label>
            <div class="relative">
              <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <span class="text-gray-400">$</span>
              </div>
              <input type="number" id="amount" name="amount" min="1000" step="100" class="bg-gray-700 border border-gray-600 rounded-lg py-3 pl-8 pr-20 w-full text-white focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent" placeholder="Enter amount">
              <div class="absolute inset-y-0 right-0 flex items-center">
                <button type="button" onclick="setMaxAmount()" class="h-full bg-gray-600 hover:bg-gray-500 text-white px-3 rounded-r-lg transition duration-300">MAX</button>
              </div>
            </div>
            <p id="amount-error" class="mt-1 text-xs text-red-500 hidden"></p>
          </div>

          <div class="mb-6 bg-gray-700 rounded-lg p-4">
            <div class="flex justify-between mb-2">
              <span class="text-gray-300">Balance</span>
              <span class="text-white">$<?php echo number_format($balance, 2); ?></span>
            </div>
            <div class="flex justify-between mb-2">
              <span class="text-gray-300">APY Rate</span>
              <span id="apy-display" class="text-yellow-500">-</span>
            </div>
            <div class="flex justify-between mb-2">
              <span class="text-gray-300">Lock Period</span>
              <span id="duration-display" class="text-white">-</span>
            </div>
            <div class="flex justify-between mb-2">
              <span class="text-gray-300">Expected Return</span>
              <span id="return-display" class="text-green-500">$0.00</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-300">Release Date</span>
              <span id="release-date-display" class="text-white">-</span>
            </div>
          </div>

          <div class="flex space-x-4">
            <button type="submit" name="create_stake" class="bg-gradient-to-r from-yellow-500 to-yellow-600 hover:from-yellow-600 hover:to-yellow-700 text-black font-bold py-3 px-6 rounded-lg transition duration-300 flex-grow">
              Stake Now
            </button>
            <button type="button" onclick="cancelStake()" class="bg-gray-700 hover:bg-gray-600 text-white font-bold py-3 px-6 rounded-lg transition duration-300">
              Cancel
            </button>
          </div>
        </form>
      </div>

      <!-- Your Active Stakes Section -->
      <div class="mb-8">
        <div class="flex justify-between items-center mb-6">
          <h2 class="text-xl font-bold">Your Active Stakes</h2>
        </div>

        <?php if (empty($active_stakes)): ?>
          <div class="bg-gray-800 rounded-lg p-6 text-center border border-gray-700">
            <i class="fas fa-chart-line text-4xl text-gray-500 mb-4"></i>
            <p class="text-gray-400">You don't have any active stakes yet.</p>
            <p class="text-gray-500 mt-2">Choose a staking plan to start earning passive income.</p>
          </div>
        <?php else: ?>
          <div class="overflow-x-auto">
            <table class="min-w-full bg-gray-800 rounded-lg overflow-hidden">
              <thead class="bg-gray-700">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Plan</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Amount</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Expected Return</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Progress</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Release Date</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Action</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-700">
                <?php foreach ($active_stakes as $stake): ?>
                  <?php
                  // Calculate progress percentage
                  $start = strtotime($stake['start_date']);
                  $end = strtotime($stake['end_date']);
                  $now = time();
                  $progress = min(100, max(0, (($now - $start) / ($end - $start)) * 100));

                  // Calculate time remaining
                  $remaining = $end - $now;
                  $days_remaining = floor($remaining / 86400);
                  $hours_remaining = floor(($remaining % 86400) / 3600);
                  ?>
                  <tr class="hover:bg-gray-750">
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="flex items-center">
                        <div class="h-10 w-10 rounded-full bg-yellow-600 flex items-center justify-center text-black font-bold">
                          <?php echo substr($stake['plan_name'], 0, 1); ?>
                        </div>
                        <div class="ml-4">
                          <div class="text-sm font-medium"><?php echo htmlspecialchars($stake['plan_name']); ?></div>
                          <div class="text-xs text-gray-400"><?php echo $stake['apy_rate']; ?>% APY</div>
                        </div>
                      </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="text-sm">:<?php echo number_format($stake['amount'], 2); ?></div>
                      <div class="text-xs text-gray-400">Locked: <?php echo date('M d, Y', strtotime($stake['start_date'])); ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="text-sm text-green-500">:<?php echo number_format($stake['expected_return'], 2); ?></div>
                      <div class="text-xs text-gray-400">+:<?php echo number_format($stake['expected_return'] - $stake['amount'], 2); ?> profit</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="flex items-center">
                        <div class="w-24 bg-gray-700 rounded-full h-2 mr-2">
                          <div class="bg-yellow-500 h-2 rounded-full" style="width: <?php echo $progress; ?>%"></div>
                        </div>
                        <span class="text-xs text-gray-400"><?php echo round($progress); ?>%</span>
                      </div>
                      <div class="text-xs text-gray-400 mt-1"><?php echo $days_remaining; ?> days <?php echo $hours_remaining; ?> hrs left</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="text-sm"><?php echo date('M d, Y', strtotime($stake['end_date'])); ?></div>
                      <div class="text-xs text-gray-400"><?php echo date('h:i A', strtotime($stake['end_date'])); ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <form method="post" action="" onsubmit="return confirmWithdrawal('<?php echo htmlspecialchars($stake['plan_name']); ?>', <?php echo $stake['amount']; ?>, <?php echo $stake['early_withdrawal_fee']; ?>);">
                        <input type="hidden" name="stake_id" value="<?php echo $stake['stake_id']; ?>">
                        <button type="submit" name="early_withdrawal" class="bg-red-700 hover:bg-red-600 text-white text-xs px-3 py-2 rounded transition duration-300">
                          Early Withdrawal
                        </button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

      <!-- Completed Stakes -->
      <?php if (!empty($completed_stakes)): ?>
        <div class="mb-8">
          <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-bold">Completed Stakes</h2>
          </div>

          <div class="overflow-x-auto">
            <table class="min-w-full bg-gray-800 rounded-lg overflow-hidden">
              <thead class="bg-gray-700">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Plan</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Amount</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Return</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Profit</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Duration</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Status</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-700">
                <?php foreach ($completed_stakes as $stake): ?>
                  <tr class="hover:bg-gray-750">
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="flex items-center">
                        <div class="h-10 w-10 rounded-full bg-yellow-600 flex items-center justify-center text-black font-bold">
                          <?php echo substr($stake['plan_name'], 0, 1); ?>
                        </div>
                        <div class="ml-4">
                          <div class="text-sm font-medium"><?php echo htmlspecialchars($stake['plan_name']); ?></div>
                          <div class="text-xs text-gray-400"><?php echo $stake['apy_rate']; ?>% APY</div>
                        </div>
                      </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="text-sm">:<?php echo number_format($stake['amount'], 2); ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="text-sm text-green-500">:<?php echo number_format($stake['expected_return'], 2); ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="text-sm text-green-500">+:<?php echo number_format($stake['expected_return'] - $stake['amount'], 2); ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="text-sm"><?php echo $stake['duration_days']; ?> days</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <?php if ($stake['status'] == 'completed'): ?>
                        <span class="px-2 py-1 text-xs rounded-full bg-green-900 text-green-400">Completed</span>
                      <?php else: ?>
                        <span class="px-2 py-1 text-xs rounded-full bg-red-900 text-red-400">Withdrawn</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>

      <!-- Info Box -->
      <div class="bg-blue-900 bg-opacity-20 border border-blue-800 rounded-lg p-4 mb-8">
        <div class="flex items-start">
          <div class="flex-shrink-0">
            <i class="fas fa-info-circle text-blue-500 mt-1"></i>
          </div>
          <div class="ml-3">
            <h3 class="text-sm font-medium text-blue-400">How Staking Works</h3>
            <div class="mt-2 text-sm text-gray-300 space-y-1">
              <p>• Lock your funds for a fixed period to earn passive income</p>
              <p>• Choose from plans with different durations and APY rates</p>
              <p>• Funds with accrued interest are automatically released at maturity</p>
              <p>• Early withdrawal is available but subject to fees</p>
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

    // Staking form functionality
    let selectedPlanId = 0;
    let minAmount = 0;
    let maxAmount = 0;
    let apyRate = 0;
    let durationDays = 0;
    const userBalance = <?php echo $balance; ?>;

    function selectPlan(planId, planName, min, max, apy, duration) {
      // Reset previous selection
      document.querySelectorAll('.plan-card').forEach(card => {
        card.classList.remove('active-plan');
      });

      // Highlight selected plan
      document.getElementById('plan-' + planId).classList.add('active-plan');

      // Show stake form
      document.getElementById('stake-form-container').classList.remove('hidden');

      // Scroll to form
      document.getElementById('stake-form-container').scrollIntoView({
        behavior: 'smooth',
        block: 'start'
      });

      // Update form values
      selectedPlanId = planId;
      minAmount = min;
      maxAmount = max;
      apyRate = apy;
      durationDays = duration;

      document.getElementById('plan_id').value = planId;
      document.getElementById('selected-plan-name').textContent = 'You selected: ' + planName;
      document.getElementById('amount').min = minAmount;
      if (maxAmount) {
        document.getElementById('amount').max = maxAmount;
      } else {
        document.getElementById('amount').removeAttribute('max');
      }

      // Update display values
      document.getElementById('apy-display').textContent = apy + '% APY';
      document.getElementById('duration-display').textContent = duration + ' days';

      // Set default amount to minimum
      document.getElementById('amount').value = minAmount;
      updateReturnCalc(minAmount);

      // Add input event listener
      document.getElementById('amount').addEventListener('input', function() {
        const amount = parseFloat(this.value) || 0;
        updateReturnCalc(amount);
      });
    }

    function updateReturnCalc(amount) {
      // Validate amount
      const amountError = document.getElementById('amount-error');

      if (amount < minAmount) {
        amountError.textContent = 'Minimum stake amount is :' + minAmount.toLocaleString();
        amountError.classList.remove('hidden');
        return;
      }

      if (maxAmount && amount > maxAmount) {
        amountError.textContent = 'Maximum stake amount is :' + maxAmount.toLocaleString();
        amountError.classList.remove('hidden');
        return;
      }

      if (amount > userBalance) {
        amountError.textContent = 'Insufficient balance. You only have :' + userBalance.toLocaleString();
        amountError.classList.remove('hidden');
        return;
      }

      amountError.classList.add('hidden');

      // Calculate expected return
      const dailyRate = apyRate / 365;
      const interest = (amount * dailyRate * durationDays) / 100;
      const expectedReturn = amount + interest;

      // Update display
      document.getElementById('return-display').textContent = ':' + expectedReturn.toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      });

      // Calculate release date
      const now = new Date();
      const releaseDate = new Date(now.getTime() + (durationDays * 24 * 60 * 60 * 1000));
      const options = {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
      };
      document.getElementById('release-date-display').textContent = releaseDate.toLocaleDateString('en-US', options);
    }

    function setMaxAmount() {
      const max = maxAmount ? Math.min(userBalance, maxAmount) : userBalance;
      document.getElementById('amount').value = max;
      updateReturnCalc(max);
    }

    function cancelStake() {
      // Hide form
      document.getElementById('stake-form-container').classList.add('hidden');

      // Reset selection
      document.querySelectorAll('.plan-card').forEach(card => {
        card.classList.remove('active-plan');
      });
    }

    function confirmWithdrawal(planName, amount, fee) {
      const feeAmount = (amount * fee) / 100;
      const returnAmount = amount - feeAmount;

      return confirm(
        'Are you sure you want to withdraw from ' + planName + ' early?\n\n' +
        'Original stake: :' + amount.toLocaleString() + '\n' +
        'Early withdrawal fee (' + fee + '%): :' + feeAmount.toLocaleString() + '\n' +
        'You will receive: :' + returnAmount.toLocaleString() + '\n\n' +
        'This action cannot be undone.'
      );
    }
  </script>
</body>

</html>