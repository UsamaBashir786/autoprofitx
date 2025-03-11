<?php

// referrals.php - Main referral dashboard page
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
  header('Location: login.php');
  exit;
}

$user_id = $_SESSION['user_id'];

// Get user details
$db = connect_db();
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Get referral statistics
$referrals = get_user_referrals($user_id);
$referral_count = count($referrals);
$earnings = get_referral_earnings($user_id);
$total_earnings = get_total_referral_earnings($user_id);

// Get referral tree (direct referrals)
$referral_tree = get_referral_tree($user_id, 3);

// Get investment plans with commission rates
$investment_plans = get_investment_plans();

// Your referral link
$referral_link = "https://autoproftx.com/register.php?ref=" . $user['referral_code'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Referral System - AutoProfTX</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
</head>

<body class="bg-gray-100">
  <div class="min-h-screen">
    <!-- Header -->
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/mobile-bar.php'; ?>


    <!-- Main Content -->
    <div class="container mx-auto px-4 py-8">
      <h1 class="text-3xl font-bold text-gray-800 mb-6">Referral Dashboard</h1>

      <!-- Referral Statistics -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow p-6">
          <div class="flex items-center">
            <div class="rounded-full bg-blue-100 p-3 mr-4">
              <i class="fas fa-users text-blue-600 text-xl"></i>
            </div>
            <div>
              <h3 class="text-lg font-semibold text-gray-600">Total Referrals</h3>
              <p class="text-2xl font-bold text-gray-800"><?php echo $referral_count; ?></p>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
          <div class="flex items-center">
            <div class="rounded-full bg-green-100 p-3 mr-4">
              <i class="fas fa-money-bill-wave text-green-600 text-xl"></i>
            </div>
            <div>
              <h3 class="text-lg font-semibold text-gray-600">Total Earnings</h3>
              <p class="text-2xl font-bold text-gray-800">$<?php echo number_format($total_earnings, 2); ?></p>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
          <div class="flex items-center">
            <div class="rounded-full bg-purple-100 p-3 mr-4">
              <i class="fas fa-link text-purple-600 text-xl"></i>
            </div>
            <div>
              <h3 class="text-lg font-semibold text-gray-600">Your Referral Code</h3>
              <p class="text-xl font-bold text-gray-800"><?php echo $user['referral_code']; ?></p>
            </div>
          </div>
        </div>
      </div>

      <!-- Referral Link Section -->
      <div class="bg-white rounded-lg shadow p-6 mb-8">
        <h2 class="text-xl font-bold text-gray-800 mb-4">Your Referral Link</h2>
        <div class="flex flex-col md:flex-row items-center gap-4">
          <div class="flex-grow w-full">
            <input type="text" id="referral-link" value="<?php echo $referral_link; ?>" class="w-full px-4 py-2 border border-gray-300 rounded-md bg-gray-50 text-gray-800" readonly>
          </div>
          <div class="flex space-x-2">
            <button onclick="copyReferralLink()" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition">
              <i class="fas fa-copy mr-2"></i> Copy
            </button>
            <a href="https://wa.me/?text=<?php echo urlencode('Join AutoProfTX and earn profits daily! Sign up using my referral link: ' . $referral_link); ?>" target="_blank" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition">
              <i class="fab fa-whatsapp mr-2"></i> Share
            </a>
          </div>
        </div>
      </div>

      <!-- Commission Rates -->
      <div class="bg-white rounded-lg shadow p-6 mb-8">
        <h2 class="text-xl font-bold text-gray-800 mb-4">Commission Rates</h2>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Plan</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Investment Range</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Daily Profit</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Duration</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Referral Commission</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <?php foreach ($investment_plans as $plan): ?>
                <tr>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="font-medium text-gray-900"><?php echo $plan['name']; ?></div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    $<?php echo number_format($plan['min_amount'], 2); ?> -
                    <?php echo $plan['max_amount'] ? '$' . number_format($plan['max_amount'], 2) : 'Unlimited'; ?>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <?php echo $plan['daily_profit_rate']; ?>%
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <?php echo $plan['duration_days']; ?> days
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                      <?php echo $plan['referral_commission_rate']; ?>%
                    </span>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Your Referrals -->
      <div class="bg-white rounded-lg shadow mb-8">
        <div class="border-b border-gray-200 px-6 py-4">
          <h2 class="text-xl font-bold text-gray-800">Your Referrals</h2>
        </div>

        <?php if (count($referrals) > 0): ?>
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Registered</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Investment</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Their Referrals</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($referrals as $referral): ?>
                  <tr>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="flex items-center">
                        <div class="ml-4">
                          <div class="text-sm font-medium text-gray-900"><?php echo $referral['full_name']; ?></div>
                        </div>
                      </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="text-sm text-gray-500"><?php echo $referral['email']; ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="text-sm text-gray-500"><?php echo date('M d, Y', strtotime($referral['registration_date'])); ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="text-sm text-gray-900">$<?php echo number_format($referral['total_investments'] ?? 0, 2); ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      <?php echo $referral['their_referrals']; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="p-6 text-center">
            <p class="text-gray-500">You haven't referred anyone yet. Share your referral link to start earning commissions!</p>
          </div>
        <?php endif; ?>
      </div>

      <!-- Referral Earnings History -->
      <div class="bg-white rounded-lg shadow">
        <div class="border-b border-gray-200 px-6 py-4">
          <h2 class="text-xl font-bold text-gray-800">Referral Earnings History</h2>
        </div>

        <?php if (count($earnings) > 0): ?>
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Referred User</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Plan</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Investment Amount</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Commission Rate</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Commission Amount</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($earnings as $earning): ?>
                  <tr>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="text-sm text-gray-900"><?php echo date('M d, Y', strtotime($earning['created_at'])); ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="text-sm font-medium text-gray-900"><?php echo $earning['referred_name']; ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="text-sm text-gray-500"><?php echo $earning['plan_name']; ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="text-sm text-gray-900">$<?php echo number_format($earning['investment_amount'], 2); ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="text-sm text-gray-900"><?php echo $earning['commission_rate']; ?>%</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="text-sm font-medium text-green-600">$<?php echo number_format($earning['commission_amount'], 2); ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    <?php echo $earning['status'] == 'paid' ? 'bg-green-100 text-green-800' : ($earning['status'] == 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'); ?>">
                        <?php echo ucfirst($earning['status']); ?>
                      </span>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="p-6 text-center">
            <p class="text-gray-500">No commission earnings yet. Refer users who make investments to earn commissions!</p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <script>
    function copyReferralLink() {
      const referralLinkInput = document.getElementById('referral-link');
      referralLinkInput.select();
      document.execCommand('copy');

      // Show feedback
      alert('Referral link copied to clipboard!');
    }
  </script>
</body>

</html>