<?php
// token-history.php - View AlphaMiner token history
session_start();
include '../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit();
}

$user_id = $_SESSION['user_id'];

// Get all user's token transactions
$active_tokens = [];
$sold_tokens = [];

// First check if the table exists
$check_table_sql = "SHOW TABLES LIKE 'alpha_tokens'";
$result = $conn->query($check_table_sql);

if ($result->num_rows > 0) {
  // Get active tokens
  $active_sql = "SELECT * FROM alpha_tokens WHERE user_id = ? AND status = 'active' ORDER BY purchase_date DESC";
  $stmt = $conn->prepare($active_sql);
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $active_result = $stmt->get_result();

  while ($row = $active_result->fetch_assoc()) {
    $active_tokens[] = $row;
  }

  // Get sold tokens
  $sold_sql = "SELECT * FROM alpha_tokens WHERE user_id = ? AND status = 'sold' ORDER BY sold_date DESC";
  $stmt = $conn->prepare($sold_sql);
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $sold_result = $stmt->get_result();

  while ($row = $sold_result->fetch_assoc()) {
    $sold_tokens[] = $row;
  }
}

// Calculate overall stats
$total_tokens_bought = count($active_tokens) + count($sold_tokens);
$total_active_tokens = count($active_tokens);
$total_tokens_sold = count($sold_tokens);
$total_investment = 0;
$total_current_value = 0;
$total_profit_realized = 0;
$total_profit_unrealized = 0;

// Calculate realized profit (from sold tokens)
foreach ($sold_tokens as $token) {
  $total_investment += $token['purchase_amount'];
  $total_profit_realized += $token['profit'];
}

// Calculate unrealized profit (from active tokens)
foreach ($active_tokens as $token) {
  $purchase_date = new DateTime($token['purchase_date']);
  $current_date = new DateTime();
  $interval = $purchase_date->diff($current_date);
  $days_held = max(1, $interval->days);

  $token_value = $token['purchase_amount'];
  for ($i = 0; $i < $days_held; $i++) {
    $token_value += $token_value * 0.065; // 6.5% daily return
  }

  $profit = $token_value - $token['purchase_amount'];
  $total_investment += $token['purchase_amount'];
  $total_current_value += $token_value;
  $total_profit_unrealized += $profit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <?php include 'includes/head.php'; ?>
  <title>Token History - AutoProftX</title>
</head>

<body class="bg-gray-900 text-white font-sans min-h-screen flex flex-col">
  <?php include 'includes/navbar.php'; ?>
  <?php include 'includes/mobile-bar.php'; ?>

  <!-- Main Content -->
  <main class="flex-grow py-6">
    <div class="container mx-auto px-4 sm:px-6 lg:px-8">
      <!-- Header Section -->
      <div class="mb-8">
        <h1 class="text-2xl font-bold">AlphaMiner Token History</h1>
        <p class="text-gray-400">Track your token investments and returns</p>
      </div>

      <!-- Stats Overview -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <!-- Total Tokens -->
        <div class="bg-gray-800 rounded-lg p-6 border border-gray-700 card-hover">
          <div class="flex justify-between items-start">
            <div>
              <p class="text-gray-400 text-sm">Total Tokens</p>
              <h3 class="text-2xl font-bold"><?php echo $total_active_tokens; ?> Active</h3>
              <p class="text-gray-500 text-sm flex items-center mt-1">
                <i class="fas fa-coins mr-1"></i> <?php echo $total_tokens_sold; ?> Sold
              </p>
            </div>
            <div class="h-10 w-10 rounded-lg gold-gradient flex items-center justify-center">
              <i class="fas fa-microchip text-black"></i>
            </div>
          </div>
        </div>

        <!-- Total Investment -->
        <div class="bg-gray-800 rounded-lg p-6 border border-gray-700 card-hover">
          <div class="flex justify-between items-start">
            <div>
              <p class="text-gray-400 text-sm">Total Investment</p>
              <h3 class="text-2xl font-bold">$<?php echo number_format($total_investment, 2); ?></h3>
              <p class="text-gray-500 text-sm flex items-center mt-1">
                <i class="fas fa-calendar-alt mr-1"></i> All time
              </p>
            </div>
            <div class="h-10 w-10 rounded-lg gold-gradient flex items-center justify-center">
              <i class="fas fa-money-bill-wave text-black"></i>
            </div>
          </div>
        </div>

        <!-- Current Value -->
        <div class="bg-gray-800 rounded-lg p-6 border border-gray-700 card-hover">
          <div class="flex justify-between items-start">
            <div>
              <p class="text-gray-400 text-sm">Current Value</p>
              <h3 class="text-2xl font-bold">$<?php echo number_format($total_current_value, 2); ?></h3>
              <p class="text-green-500 text-sm flex items-center mt-1">
                <i class="fas fa-chart-line mr-1"></i> +$<?php echo number_format($total_profit_unrealized, 2); ?> unrealized
              </p>
            </div>
            <div class="h-10 w-10 rounded-lg gold-gradient flex items-center justify-center">
              <i class="fas fa-sack-dollar text-black"></i>
            </div>
          </div>
        </div>

        <!-- Total Profit -->
        <div class="bg-gray-800 rounded-lg p-6 border border-gray-700 card-hover">
          <div class="flex justify-between items-start">
            <div>
              <p class="text-gray-400 text-sm">Total Profit</p>
              <h3 class="text-2xl font-bold">$<?php echo number_format($total_profit_realized + $total_profit_unrealized, 2); ?></h3>
              <p class="text-green-500 text-sm flex items-center mt-1">
                <i class="fas fa-check-circle mr-1"></i> $<?php echo number_format($total_profit_realized, 2); ?> realized
              </p>
            </div>
            <div class="h-10 w-10 rounded-lg gold-gradient flex items-center justify-center">
              <i class="fas fa-hand-holding-usd text-black"></i>
            </div>
          </div>
        </div>
      </div>

      <!-- Active Tokens Section -->
      <div class="mb-8">
        <h2 class="text-xl font-semibold mb-4">Active Tokens</h2>

        <?php if (empty($active_tokens)): ?>
          <div class="bg-gray-800 rounded-lg p-6 text-center">
            <i class="fas fa-coins text-gray-600 text-4xl mb-3"></i>
            <p class="text-gray-400">You don't have any active tokens.</p>
            <a href="index.php" class="inline-block mt-4 text-yellow-500 hover:text-yellow-400">
              Buy AlphaMiner Tokens
            </a>
          </div>
        <?php else: ?>
          <div class="overflow-x-auto bg-gray-800 rounded-lg border border-gray-700">
            <table class="min-w-full divide-y divide-gray-700">
              <thead>
                <tr>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Token ID</th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Purchase Date</th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Days Held</th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Purchase Amount</th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Current Value</th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Profit</th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">ROI</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-700">
                <?php foreach ($active_tokens as $token): ?>
                  <?php
                  // Calculate current value and profit
                  $purchase_date = new DateTime($token['purchase_date']);
                  $current_date = new DateTime();
                  $interval = $purchase_date->diff($current_date);
                  $days_held = max(1, $interval->days);

                  $token_value = $token['purchase_amount'];
                  for ($i = 0; $i < $days_held; $i++) {
                    $token_value += $token_value * 0.065; // 6.5% daily return
                  }

                  $profit = $token_value - $token['purchase_amount'];
                  $roi = ($profit / $token['purchase_amount']) * 100;
                  ?>
                  <tr>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <span class="px-2 py-1 rounded-lg bg-blue-900 text-white text-xs">AMR-<?php echo $token['id']; ?></span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                      <?php echo date('M d, Y', strtotime($token['purchase_date'])); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                      <?php echo $days_held; ?> days
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                      $<?php echo number_format($token['purchase_amount'], 2); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                      $<?php echo number_format($token_value, 2); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-green-500">
                      +$<?php echo number_format($profit, 2); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-green-500">
                      +<?php echo number_format($roi, 2); ?>%
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

      <!-- Sold Tokens Section -->
      <div>
        <h2 class="text-xl font-semibold mb-4">Sold Tokens</h2>

        <?php if (empty($sold_tokens)): ?>
          <div class="bg-gray-800 rounded-lg p-6 text-center">
            <i class="fas fa-exchange-alt text-gray-600 text-4xl mb-3"></i>
            <p class="text-gray-400">You haven't sold any tokens yet.</p>
          </div>
        <?php else: ?>
          <div class="overflow-x-auto bg-gray-800 rounded-lg border border-gray-700">
            <table class="min-w-full divide-y divide-gray-700">
              <thead>
                <tr>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Token ID</th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Purchase Date</th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Sold Date</th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Days Held</th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Purchase Amount</th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Sold Amount</th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Profit</th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">ROI</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-700">
                <?php foreach ($sold_tokens as $token): ?>
                  <?php
                  // Calculate days held and ROI
                  $purchase_date = new DateTime($token['purchase_date']);
                  $sold_date = new DateTime($token['sold_date']);
                  $interval = $purchase_date->diff($sold_date);
                  $days_held = max(1, $interval->days);

                  $roi = ($token['profit'] / $token['purchase_amount']) * 100;
                  ?>
                  <tr>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <span class="px-2 py-1 rounded-lg bg-red-900 text-white text-xs">AMR-<?php echo $token['id']; ?></span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                      <?php echo date('M d, Y', strtotime($token['purchase_date'])); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                      <?php echo date('M d, Y', strtotime($token['sold_date'])); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                      <?php echo $days_held; ?> days
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                      $<?php echo number_format($token['purchase_amount'], 2); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                      $<?php echo number_format($token['sold_amount'], 2); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-green-500">
                      +$<?php echo number_format($token['profit'], 2); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-green-500">
                      +<?php echo number_format($roi, 2); ?>%
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </main>

  <?php include 'includes/footer.php'; ?>
  <?php include 'includes/js-links.php'; ?>
</body>

</html>