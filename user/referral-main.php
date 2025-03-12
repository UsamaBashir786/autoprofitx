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

// Process claim requests
if ($_SERVER["REQUEST_METHOD"] == "POST") {
  if (isset($_POST['claim_bonus']) && isset($_POST['vault_id'])) {
    $result = claimReferralBonus($_POST['vault_id'], $user_id);

    if ($result['success']) {
      $success_message = $result['message'];
    } else {
      $error_message = $result['message'];
    }
  } else if (isset($_POST['claim_all_bonuses'])) {
    $result = claimAllReferralBonuses($user_id);

    if ($result['success']) {
      $success_message = $result['message'];
    } else {
      $error_message = $result['message'];
    }
  }
}

// Get referral data
$referrals = getUserReferrals($user_id);
$pending_bonuses = getPendingReferralBonuses($user_id);
$total_pending_amount = getTotalPendingReferralAmount($user_id);
$total_referral_earnings = getTotalReferralEarnings($user_id);
$claimed_bonuses_amount = $total_referral_earnings - $total_pending_amount;

// Get eligibility info for pending bonuses
$eligibility_info = getEligibleReferralCounts($user_id);
$eligible_count = $eligibility_info['eligible_count'];
$ineligible_count = $eligibility_info['ineligible_count'];
$eligible_amount = $eligibility_info['eligible_amount'];
$ineligible_amount = $eligibility_info['ineligible_amount'];

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

// Generate referral code if it doesn't exist
if (empty($referral_code)) {
  $referral_code = generateReferralCode();
  $update_query = "UPDATE users SET referral_code = ? WHERE id = ?";
  $stmt = $conn->prepare($update_query);
  $stmt->bind_param("si", $referral_code, $user_id);
  $stmt->execute();
}

// Generate referral link
$referral_link = "https://" . $_SERVER['HTTP_HOST'] . "/register.php?ref=" . $referral_code;

// Group pending bonuses by eligibility
$eligible_bonuses = [];
$ineligible_bonuses = [];

foreach ($pending_bonuses as $bonus) {
  // Check eligibility based on login_count
  if (isset($bonus['login_count']) && $bonus['login_count'] > 0) {
    $eligible_bonuses[] = $bonus;
  } else {
    $ineligible_bonuses[] = $bonus;
  }
}

// Function to generate new referral code button
function generateNewReferralCodeButton()
{
  return '<button id="generate-new-code" class="ml-2 text-xs bg-gray-700 hover:bg-gray-600 text-white px-3 py-1 rounded-full transition duration-300">
    <i class="fas fa-sync-alt"></i> New Code
  </button>';
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <?php include 'includes/head.php'; ?>
  <style>
    .copy-tooltip {
      position: relative;
      display: inline-block;
    }

    .copy-tooltip .tooltip-text {
      visibility: hidden;
      width: 120px;
      background-color: #333;
      color: #fff;
      text-align: center;
      border-radius: 6px;
      padding: 5px;
      position: absolute;
      z-index: 1;
      bottom: 125%;
      left: 50%;
      margin-left: -60px;
      opacity: 0;
      transition: opacity 0.3s;
    }

    .copy-tooltip .tooltip-text::after {
      content: "";
      position: absolute;
      top: 100%;
      left: 50%;
      margin-left: -5px;
      border-width: 5px;
      border-style: solid;
      border-color: #333 transparent transparent transparent;
    }

    .copy-tooltip:hover .tooltip-text {
      visibility: visible;
      opacity: 1;
    }

    /* Status badges */
    .badge-eligible {
      background-color: rgba(16, 185, 129, 0.2);
      color: #10b981;
    }

    .badge-ineligible {
      background-color: rgba(239, 68, 68, 0.2);
      color: #ef4444;
    }

    /* Info tooltip */
    .info-tooltip {
      position: relative;
      display: inline-block;
      cursor: help;
    }

    .info-tooltip .tooltip-text {
      visibility: hidden;
      width: 220px;
      background-color: #333;
      color: #fff;
      text-align: center;
      border-radius: 6px;
      padding: 8px;
      position: absolute;
      z-index: 1;
      bottom: 125%;
      left: 50%;
      margin-left: -110px;
      opacity: 0;
      transition: opacity 0.3s;
      font-size: 0.75rem;
    }

    .info-tooltip .tooltip-text::after {
      content: "";
      position: absolute;
      top: 100%;
      left: 50%;
      margin-left: -5px;
      border-width: 5px;
      border-style: solid;
      border-color: #333 transparent transparent transparent;
    }

    .info-tooltip:hover .tooltip-text {
      visibility: visible;
      opacity: 1;
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
        <h1 class="text-3xl font-bold">My Referrals</h1>
        <p class="text-gray-400 mt-2">Invite friends and earn $5 for each referral after they join!</p>
      </div>

      <!-- Display Messages -->
      <?php if (isset($success_message)): ?>
        <div class="mb-6 bg-green-900 border-l-4 border-green-500 text-white p-4 rounded-md">
          <div class="flex">
            <div class="flex-shrink-0">
              <i class="fas fa-check-circle text-green-400"></i>
            </div>
            <div class="ml-3">
              <p class="text-sm"><?php echo $success_message; ?></p>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <?php if (isset($error_message)): ?>
        <div class="mb-6 bg-red-900 border-l-4 border-red-500 text-white p-4 rounded-md">
          <div class="flex">
            <div class="flex-shrink-0">
              <i class="fas fa-exclamation-circle text-red-400"></i>
            </div>
            <div class="ml-3">
              <p class="text-sm"><?php echo $error_message; ?></p>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <!-- Referral Rules Explanation -->
      <div class="bg-gray-800 bg-opacity-50 border border-gray-700 rounded-lg p-4 mb-8">
        <div class="flex items-start">
          <div class="flex-shrink-0 mt-1">
            <i class="fas fa-info-circle text-blue-400 text-lg"></i>
          </div>
          <div class="ml-3">
            <h3 class="text-lg font-medium text-white">Referral Reward Rules</h3>
            <p class="mt-1 text-sm text-gray-300">
              Before you can claim referral bonuses, your referred users must log in at least once.
              This ensures that only real, active referrals are rewarded. Once your referrals are
              eligible, you can claim their bonuses to your wallet.
            </p>
          </div>
        </div>
      </div>

      <!-- Stats Overview -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Total Referrals -->
        <div class="bg-gradient-to-br from-gray-900 to-gray-800 rounded-xl p-6 border border-gray-700 shadow-lg">
          <div class="flex justify-between items-start">
            <div>
              <p class="text-gray-400 text-sm font-medium uppercase tracking-wider">Total Referrals</p>
              <h3 class="text-3xl font-extrabold text-white mt-2"><?php echo count($referrals); ?></h3>
              <p class="text-blue-400 text-sm flex items-center mt-2 font-medium">
                <i class="fas fa-users mr-2"></i> Friends invited
              </p>
            </div>
            <div class="h-12 w-12 rounded-full bg-blue-900 flex items-center justify-center shadow-lg">
              <i class="fas fa-user-friends text-blue-400 text-lg"></i>
            </div>
          </div>
        </div>

        <!-- Earned Rewards -->
        <div class="bg-gradient-to-br from-gray-900 to-gray-800 rounded-xl p-6 border border-gray-700 shadow-lg">
          <div class="flex justify-between items-start">
            <div>
              <p class="text-gray-400 text-sm font-medium uppercase tracking-wider">Earned Rewards</p>
              <h3 class="text-3xl font-extrabold text-white mt-2">$<?php echo number_format($claimed_bonuses_amount, 2); ?></h3>
              <p class="text-green-400 text-sm flex items-center mt-2 font-medium">
                <i class="fas fa-money-bill-wave mr-2"></i> Already claimed
              </p>
            </div>
            <div class="h-12 w-12 rounded-full bg-green-900 flex items-center justify-center shadow-lg">
              <i class="fas fa-dollar-sign text-green-400 text-lg"></i>
            </div>
          </div>
        </div>

        <!-- Pending Rewards -->
        <div class="bg-gradient-to-br from-gray-900 to-gray-800 rounded-xl p-6 border border-gray-700 shadow-lg">
          <div class="flex justify-between items-start">
            <div>
              <p class="text-gray-400 text-sm font-medium uppercase tracking-wider">Pending Rewards</p>
              <h3 class="text-3xl font-extrabold text-white mt-2">$<?php echo number_format($total_pending_amount, 2); ?></h3>
              <div class="flex flex-wrap gap-2 mt-2">
                <?php if ($eligible_count > 0): ?>
                  <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full badge-eligible">
                    $<?php echo number_format($eligible_amount, 2); ?> eligible
                  </span>
                <?php endif; ?>

                <?php if ($ineligible_count > 0): ?>
                  <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full badge-ineligible">
                    $<?php echo number_format($ineligible_amount, 2); ?> waiting
                  </span>
                <?php endif; ?>
              </div>

              <?php if ($eligible_amount > 0): ?>
                <button onclick="document.getElementById('claimAllModal').classList.remove('hidden')"
                  class="mt-3 text-xs bg-yellow-600 hover:bg-yellow-700 text-white px-3 py-1 rounded-full transition duration-300">
                  Claim Eligible Rewards
                </button>
              <?php endif; ?>
            </div>
            <div class="h-12 w-12 rounded-full bg-yellow-900 flex items-center justify-center shadow-lg">
              <i class="fas fa-gift text-yellow-400 text-lg"></i>
            </div>
          </div>
        </div>

        <!-- Referral Rate -->
        <div class="bg-gradient-to-br from-gray-900 to-gray-800 rounded-xl p-6 border border-gray-700 shadow-lg">
          <div class="flex justify-between items-start">
            <div>
              <p class="text-gray-400 text-sm font-medium uppercase tracking-wider">Reward Per Referral</p>
              <h3 class="text-3xl font-extrabold text-white mt-2">$5.00</h3>
              <p class="text-purple-400 text-sm flex items-center mt-2 font-medium">
                <i class="fas fa-star mr-2"></i> Fixed bonus
              </p>
            </div>
            <div class="h-12 w-12 rounded-full bg-purple-900 flex items-center justify-center shadow-lg">
              <i class="fas fa-gem text-purple-400 text-lg"></i>
            </div>
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
              <div class="copy-tooltip">
                <button onclick="copyReferralLink()" class="text-yellow-500 hover:text-yellow-400">
                  <i class="fas fa-copy"></i>
                </button>
                <span class="tooltip-text" id="copy-tooltip">Copy to clipboard</span>
              </div>
            </div>
          </div>
          <button onclick="shareReferral()" class="bg-yellow-600 hover:bg-yellow-700 text-white px-6 py-3 rounded-lg transition duration-300 md:ml-4 flex items-center justify-center">
            <i class="fas fa-share-alt mr-2"></i> Share
          </button>
        </div>

        <div class="mb-4">
          <h3 class="text-lg font-medium mb-2">Your Referral Code</h3>
          <div class="flex items-center">
            <div class="bg-gray-900 text-center px-4 py-2 rounded-lg border border-gray-700">
              <span class="text-xl font-mono tracking-wide"><?php echo $referral_code; ?></span>
            </div>
            <?php echo generateNewReferralCodeButton(); ?>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($referral_link); ?>" target="_blank" class="bg-blue-600 hover:bg-blue-700 text-white p-3 rounded-lg transition duration-300 flex items-center justify-center">
            <i class="fab fa-facebook-f mr-2"></i> Share on Facebook
          </a>
          <a href="https://twitter.com/intent/tweet?text=Join me on this amazing platform and get started today!&url=<?php echo urlencode($referral_link); ?>" target="_blank" class="bg-blue-400 hover:bg-blue-500 text-white p-3 rounded-lg transition duration-300 flex items-center justify-center">
            <i class="fab fa-twitter mr-2"></i> Share on Twitter
          </a>
          <a href="https://wa.me/?text=Check out this amazing platform! Join using my referral link: <?php echo urlencode($referral_link); ?>" target="_blank" class="bg-green-600 hover:bg-green-700 text-white p-3 rounded-lg transition duration-300 flex items-center justify-center">
            <i class="fab fa-whatsapp mr-2"></i> Share on WhatsApp
          </a>
        </div>
      </div>

      <!-- Eligible Rewards Section -->
      <?php if (!empty($eligible_bonuses)): ?>
        <div class="mb-8">
          <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold">
              Eligible Rewards
              <span class="text-sm font-normal text-green-400">(Ready to claim)</span>
            </h2>
            <?php if (count($eligible_bonuses) > 1): ?>
              <button onclick="document.getElementById('claimAllModal').classList.remove('hidden')"
                class="text-sm bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition duration-300 flex items-center">
                <i class="fas fa-coins mr-2"></i> Claim All ($<?php echo number_format($eligible_amount, 2); ?>)
              </button>
            <?php endif; ?>
          </div>

          <div class="bg-gray-800 rounded-lg border border-gray-700 overflow-hidden">
            <!-- Desktop view -->
            <div class="hidden md:block">
              <table class="min-w-full divide-y divide-gray-700">
                <thead class="bg-gray-750">
                  <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Referred User</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Date</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Amount</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Status</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Action</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-700">
                  <?php foreach ($eligible_bonuses as $bonus): ?>
                    <tr>
                      <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                          <div class="h-10 w-10 rounded-full bg-gray-700 flex items-center justify-center">
                            <i class="fas fa-user text-gray-300"></i>
                          </div>
                          <div class="ml-4">
                            <div class="text-sm font-medium"><?php echo htmlspecialchars($bonus['full_name']); ?></div>
                            <div class="text-sm text-gray-400"><?php echo htmlspecialchars($bonus['email']); ?></div>
                          </div>
                        </div>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                        <?php echo date('M d, Y', strtotime($bonus['created_at'])); ?>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-green-500 font-bold">$<?php echo number_format($bonus['amount'], 2); ?></div>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full badge-eligible">
                          Eligible
                        </span>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                        <form method="POST" action="">
                          <input type="hidden" name="vault_id" value="<?php echo $bonus['id']; ?>">
                          <button type="submit" name="claim_bonus" class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded-md transition duration-300">
                            Claim Now
                          </button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>

            <!-- Mobile view -->
            <div class="md:hidden divide-y divide-gray-700">
              <?php foreach ($eligible_bonuses as $bonus): ?>
                <div class="p-4">
                  <div class="flex items-center mb-3">
                    <div class="h-10 w-10 rounded-full bg-gray-700 flex items-center justify-center">
                      <i class="fas fa-user text-gray-300"></i>
                    </div>
                    <div class="ml-3">
                      <div class="text-sm font-medium"><?php echo htmlspecialchars($bonus['full_name']); ?></div>
                      <div class="text-xs text-gray-400"><?php echo htmlspecialchars($bonus['email']); ?></div>
                    </div>
                  </div>
                  <div class="flex justify-between items-center mb-3">
                    <div>
                      <div class="text-xs text-gray-400">Date:</div>
                      <div class="text-sm"><?php echo date('M d, Y', strtotime($bonus['created_at'])); ?></div>
                    </div>
                    <div>
                      <div class="text-xs text-gray-400">Amount:</div>
                      <div class="text-lg text-green-500 font-bold">$<?php echo number_format($bonus['amount'], 2); ?></div>
                    </div>
                  </div>
                  <div class="flex justify-between items-center">
                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full badge-eligible">
                      Eligible
                    </span>
                    <form method="POST" action="">
                      <input type="hidden" name="vault_id" value="<?php echo $bonus['id']; ?>">
                      <button type="submit" name="claim_bonus" class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded-md transition duration-300">
                        Claim Now
                      </button>
                    </form>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <!-- Ineligible Rewards Section -->
      <?php if (!empty($ineligible_bonuses)): ?>
        <div class="mb-8">
          <div class="flex items-center mb-4">
            <h2 class="text-xl font-bold">
              Pending Rewards
              <span class="text-sm font-normal text-yellow-500">(Waiting for activation)</span>
            </h2>
            <div class="ml-2 info-tooltip">
              <i class="fas fa-question-circle text-gray-400"></i>
              <span class="tooltip-text">These rewards will become available when the referred users log in to their accounts for the first time.</span>
            </div>
          </div>

          <div class="bg-gray-800 rounded-lg border border-gray-700 overflow-hidden">
            <!-- Desktop view -->
            <div class="hidden md:block">
              <table class="min-w-full divide-y divide-gray-700">
                <thead class="bg-gray-750">
                  <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Referred User</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Date</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Amount</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Status</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Action</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-700">
                  <?php foreach ($ineligible_bonuses as $bonus): ?>
                    <tr>
                      <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                          <div class="h-10 w-10 rounded-full bg-gray-700 flex items-center justify-center">
                            <i class="fas fa-user text-gray-300"></i>
                          </div>
                          <div class="ml-4">
                            <div class="text-sm font-medium"><?php echo htmlspecialchars($bonus['full_name']); ?></div>
                            <div class="text-sm text-gray-400"><?php echo htmlspecialchars($bonus['email']); ?></div>
                          </div>
                        </div>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                        <?php echo date('M d, Y', strtotime($bonus['created_at'])); ?>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-yellow-500 font-bold">$<?php echo number_format($bonus['amount'], 2); ?></div>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full badge-ineligible">
                          Awaiting activation
                        </span>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-400">
                        <button disabled class="bg-gray-700 text-gray-500 cursor-not-allowed px-3 py-1 rounded-md">
                          Waiting
                        </button>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>

            <!-- Mobile view -->
            <div class="md:hidden divide-y divide-gray-700">
              <?php foreach ($ineligible_bonuses as $bonus): ?>
                <div class="p-4">
                  <div class="flex items-center mb-3">
                    <div class="h-10 w-10 rounded-full bg-gray-700 flex items-center justify-center">
                      <i class="fas fa-user text-gray-300"></i>
                    </div>
                    <div class="ml-3">
                      <div class="text-sm font-medium"><?php echo htmlspecialchars($bonus['full_name']); ?></div>
                      <div class="text-xs text-gray-400"><?php echo htmlspecialchars($bonus['email']); ?></div>
                    </div>
                  </div>
                  <div class="flex justify-between items-center mb-3">
                    <div>
                      <div class="text-xs text-gray-400">Date:</div>
                      <div class="text-sm"><?php echo date('M d, Y', strtotime($bonus['created_at'])); ?></div>
                    </div>
                    <div>
                      <div class="text-xs text-gray-400">Amount:</div>
                      <div class="text-lg text-yellow-500 font-bold">$<?php echo number_format($bonus['amount'], 2); ?></div>
                    </div>
                  </div>
                  <div class="flex justify-between items-center">
                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full badge-ineligible">
                      Awaiting activation
                    </span>
                    <button disabled class="bg-gray-700 text-gray-500 cursor-not-allowed px-3 py-1 rounded-md">
                      Waiting
                    </button>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <!-- Your Referrals Section -->
      <div>
        <h2 class="text-xl font-bold mb-4">Your Referrals</h2>

        <?php if (empty($referrals)): ?>
          <div class="bg-gray-800 rounded-lg p-8 text-center border border-gray-700">
            <div class="h-20 w-20 mx-auto mb-4 rounded-full bg-gray-700 flex items-center justify-center">
              <i class="fas fa-users text-gray-500 text-3xl"></i>
            </div>
            <h3 class="text-xl font-bold mb-2">No referrals yet</h3>
            <p class="text-gray-400 mb-6">Share your referral link with friends to earn rewards!</p>
            <button onclick="shareReferral()" class="bg-yellow-600 hover:bg-yellow-700 text-white px-6 py-3 rounded-lg transition duration-300 flex items-center justify-center mx-auto">
              <i class="fas fa-share-alt mr-2"></i> Share Your Link
            </button>
          </div>
        <?php else: ?>
          <!-- Referrals Table -->
          <div class="bg-gray-800 rounded-lg border border-gray-700 overflow-hidden">
            <!-- Desktop view -->
            <div class="hidden md:block overflow-x-auto">
              <table class="min-w-full divide-y divide-gray-700">
                <thead class="bg-gray-750">
                  <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">User</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Joined Date</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Login Status</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Bonus</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Status</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-700">
                  <?php foreach ($referrals as $referral): ?>
                    <tr>
                      <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                          <div class="h-10 w-10 rounded-full bg-gray-700 flex items-center justify-center">
                            <i class="fas fa-user text-gray-300"></i>
                          </div>
                          <div class="ml-4">
                            <div class="text-sm font-medium"><?php echo htmlspecialchars($referral['full_name']); ?></div>
                            <div class="text-sm text-gray-400"><?php echo htmlspecialchars($referral['email']); ?></div>
                          </div>
                        </div>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                        <?php echo date('M d, Y', strtotime($referral['registration_date'])); ?>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap">
                        <?php if (isset($referral['login_count']) && $referral['login_count'] > 0): ?>
                          <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full badge-eligible">
                            Active
                          </span>
                        <?php else: ?>
                          <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full badge-ineligible">
                            Pending login
                          </span>
                        <?php endif; ?>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-green-500 font-bold">$<?php echo number_format($referral['bonus_amount'], 2); ?></div>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap">
                        <?php if ($referral['status'] == 'paid'): ?>
                          <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-900 text-green-300">
                            Claimed <?php echo !empty($referral['paid_at']) ? 'on ' . date('M d, Y', strtotime($referral['paid_at'])) : ''; ?>
                          </span>
                        <?php else: ?>
                          <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-900 text-yellow-300">
                            Pending
                          </span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>

            <!-- Mobile view -->
            <div class="md:hidden divide-y divide-gray-700">
              <?php foreach ($referrals as $referral): ?>
                <div class="p-4">
                  <div class="flex items-center mb-3">
                    <div class="h-10 w-10 rounded-full bg-gray-700 flex items-center justify-center">
                      <i class="fas fa-user text-gray-300"></i>
                    </div>
                    <div class="ml-3">
                      <div class="text-sm font-medium"><?php echo htmlspecialchars($referral['full_name']); ?></div>
                      <div class="text-xs text-gray-400"><?php echo htmlspecialchars($referral['email']); ?></div>
                    </div>
                  </div>
                  <div class="grid grid-cols-2 gap-3 mb-3">
                    <div>
                      <div class="text-xs text-gray-400">Joined:</div>
                      <div class="text-sm"><?php echo date('M d, Y', strtotime($referral['registration_date'])); ?></div>
                    </div>
                    <div>
                      <div class="text-xs text-gray-400">Bonus:</div>
                      <div class="text-lg text-green-500 font-bold">$<?php echo number_format($referral['bonus_amount'], 2); ?></div>
                    </div>
                  </div>
                  <div class="flex justify-between items-center">
                    <?php if (isset($referral['login_count']) && $referral['login_count'] > 0): ?>
                      <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full badge-eligible">
                        Active
                      </span>
                    <?php else: ?>
                      <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full badge-ineligible">
                        Pending login
                      </span>
                    <?php endif; ?>

                    <?php if ($referral['status'] == 'paid'): ?>
                      <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-900 text-green-300">
                        Claimed
                      </span>
                    <?php else: ?>
                      <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-900 text-yellow-300">
                        Pending
                      </span>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </main>

  <!-- Claim All Modal -->
  <div id="claimAllModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-gray-800 p-6 rounded-lg shadow-lg max-w-md mx-4">
      <div class="text-center mb-6">
        <div class="h-16 w-16 rounded-full bg-yellow-900 flex items-center justify-center mx-auto mb-4">
          <i class="fas fa-gift text-yellow-400 text-2xl"></i>
        </div>
        <h3 class="text-xl font-bold mb-2">Claim Eligible Rewards</h3>
        <p class="text-gray-400">You are about to claim <span class="text-yellow-500 font-bold">$<?php echo number_format($eligible_amount, 2); ?></span> in referral rewards. This amount will be added to your wallet balance.</p>
        <?php if ($ineligible_count > 0): ?>
          <p class="mt-2 text-sm text-gray-500">Note: $<?php echo number_format($ineligible_amount, 2); ?> in rewards are not yet eligible for claiming.</p>
        <?php endif; ?>
      </div>

      <div class="flex flex-col-reverse sm:flex-row sm:justify-between gap-3">
        <button onclick="document.getElementById('claimAllModal').classList.add('hidden')"
          class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition duration-300">
          Cancel
        </button>
        <form method="POST" action="" class="w-full sm:w-auto">
          <input type="hidden" name="claim_all_bonuses" value="1">
          <button type="submit" class="w-full sm:w-auto px-6 py-2 bg-green-600 hover:bg-green-700 text-white font-medium rounded-lg transition duration-300">
            Claim $<?php echo number_format($eligible_amount, 2); ?>
          </button>
        </form>
      </div>
    </div>
  </div>

  <?php include 'includes/footer.php'; ?>
  <?php include 'includes/js-links.php'; ?>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Hide preloader after 1 second
      setTimeout(function() {
        document.querySelector('.preloader').style.display = 'none';
        document.getElementById('main-content').style.display = 'block';
      }, 1000);

      // New referral code button
      const newCodeBtn = document.getElementById('generate-new-code');
      if (newCodeBtn) {
        newCodeBtn.addEventListener('click', function() {
          if (confirm('Are you sure you want to generate a new referral code? Your old code will no longer work.')) {
            // Send request to generate new code
            fetch('generate-new-code.php', {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/x-www-form-urlencoded',
                },
              })
              .then(response => response.json())
              .then(data => {
                if (data.success) {
                  alert('New referral code generated successfully!');
                  location.reload();
                } else {
                  alert('Error: ' + data.message);
                }
              })
              .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
              });
          }
        });
      }
    });

    function copyReferralLink() {
      const referralLink = document.getElementById('referral-link');
      referralLink.select();
      document.execCommand('copy');

      // Update tooltip text
      const tooltip = document.getElementById('copy-tooltip');
      tooltip.innerHTML = "Copied!";

      // Reset tooltip text after 2 seconds
      setTimeout(function() {
        tooltip.innerHTML = "Copy to clipboard";
      }, 2000);
    }

    function shareReferral() {
      if (navigator.share) {
        navigator.share({
            title: 'Join me on this amazing platform',
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
    }
  </script>
</body>

</html>