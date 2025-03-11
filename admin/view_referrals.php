<?php
// Start session
session_start();
// admin/view_referrals.php - View user's referral network
// session_start();
include '../config/db.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
  header('Location: login.php');
  exit;
}

$db = connect_db();
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

// Validate user_id
if ($user_id <= 0) {
  $_SESSION['admin_message'] = 'Invalid user ID.';
  $_SESSION['admin_message_type'] = 'error';
  header('Location: referrals.php');
  exit;
}

// Get user details
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  $_SESSION['admin_message'] = 'User not found.';
  $_SESSION['admin_message_type'] = 'error';
  header('Location: referrals.php');
  exit;
}

$user = $result->fetch_assoc();

// Get referral statistics
$stmt = $db->prepare("
    SELECT COUNT(*) as referral_count,
           (SELECT COUNT(*) FROM users WHERE referred_by = ?) as direct_referrals,
           (SELECT COALESCE(SUM(commission_amount), 0) FROM referral_commissions WHERE referrer_id = ? AND status = 'paid') as total_earnings
    FROM users
    WHERE id IN (
        SELECT DISTINCT referred_id FROM referrals WHERE referrer_id = ?
    )
");

$stmt->bind_param("iii", $user_id, $user_id, $user_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// Get referral tree (3 levels deep)
function get_referral_tree($db, $user_id, $levels = 3, $current_level = 0)
{
  if ($current_level >= $levels) {
    return [];
  }

  $tree = [];

  $stmt = $db->prepare("
        SELECT u.id, u.full_name, u.email, u.referral_code, u.registration_date,
               (SELECT SUM(amount) FROM investments WHERE user_id = u.id) as total_investments,
               (SELECT COUNT(*) FROM users WHERE referred_by = u.id) as referral_count
        FROM users u
        WHERE u.referred_by = ?
        ORDER BY u.registration_date DESC
    ");

  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $result = $stmt->get_result();

  while ($row = $result->fetch_assoc()) {
    // Get children recursively
    $row['children'] = get_referral_tree($db, $row['id'], $levels, $current_level + 1);
    $tree[] = $row;
  }

  return $tree;
}

$referral_tree = get_referral_tree($db, $user_id);

// Get direct referrals
$stmt = $db->prepare("
    SELECT u.id, u.full_name, u.email, u.referral_code, u.registration_date, u.status,
           (SELECT SUM(amount) FROM investments WHERE user_id = u.id) as total_investments,
           (SELECT COUNT(*) FROM users WHERE referred_by = u.id) as their_referrals
    FROM users u 
    WHERE u.referred_by = ?
    ORDER BY u.registration_date DESC
");

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$referrals = [];

while ($row = $result->fetch_assoc()) {
  $referrals[] = $row;
}

// Get referral earnings
$stmt = $db->prepare("
    SELECT rc.*, 
           u.full_name as referred_name, 
           ip.name as plan_name
    FROM referral_commissions rc
    JOIN users u ON rc.referred_id = u.id
    JOIN investments i ON rc.investment_id = i.id
    JOIN investment_plans ip ON i.plan_id = ip.id
    WHERE rc.referrer_id = ?
    ORDER BY rc.created_at DESC
");

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$earnings = [];

while ($row = $result->fetch_assoc()) {
  $earnings[] = $row;
}

$db->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>View Referral Network - Admin Panel</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
  <style>
    .tree {
      --spacing: 1.5rem;
      --radius: 10px;
    }

    .tree li {
      display: block;
      position: relative;
      padding-left: calc(2 * var(--spacing) - var(--radius) - 2px);
    }

    .tree ul {
      margin-left: calc(var(--radius) - var(--spacing));
      padding-left: 0;
    }

    .tree ul li {
      border-left: 2px solid #ddd;
    }

    .tree ul li:last-child {
      border-color: transparent;
    }

    .tree ul li::before {
      content: '';
      display: block;
      position: absolute;
      top: calc(var(--spacing) / -2);
      left: -2px;
      width: calc(var(--spacing) + 2px);
      height: calc(var(--spacing) + 1px);
      border: none;
      border-left: 2px solid #ddd;
      border-bottom: 2px solid #ddd;
      border-radius: 0 0 0 var(--radius);
    }

    .tree li::after {
      content: '';
      display: block;
      position: absolute;
      top: calc(var(--spacing) / 2 - 1px);
      left: calc(var(--spacing) - var(--radius) - 1px);
      width: calc(var(--radius) + 2px);
      height: 2px;
      background: #ddd;
    }

    .tree ul li:last-child::before {
      border-radius: 0 0 0 var(--radius);
    }

    .tree ul li:first-child::after {
      width: calc(var(--spacing) + 2px);
      border-radius: var(--radius) 0 0 0;
    }

    .tree ul li:last-child::after {
      width: calc(var(--spacing) + 2px);
      border-radius: 0 0 0 var(--radius);
    }
  </style>
</head>

<body class="bg-gray-100">
  <div class="min-h-screen flex">
    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>
    <!-- Main Content -->
    <div class="flex-grow p-6">
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Referral Network for <?php echo htmlspecialchars($user['full_name']); ?></h1>
        <a href="referrals.php" class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 transition">
          <i class="fas fa-arrow-left mr-2"></i> Back to Referrals
        </a>
      </div>

      <!-- User Profile Card -->
      <div class="bg-white rounded-lg shadow p-6 mb-6">
        <div class="md:flex">
          <div class="md:flex-shrink-0 flex items-center justify-center mb-4 md:mb-0">
            <div class="h-20 w-20 rounded-full bg-blue-600 flex items-center justify-center text-white text-2xl font-bold">
              <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
            </div>
          </div>
          <div class="md:ml-6">
            <h2 class="text-xl font-bold text-gray-800"><?php echo htmlspecialchars($user['full_name']); ?></h2>
            <p class="text-gray-600"><i class="fas fa-envelope mr-2"></i> <?php echo htmlspecialchars($user['email']); ?></p>
            <p class="text-gray-600"><i class="fas fa-phone mr-2"></i> <?php echo htmlspecialchars($user['phone']); ?></p>
            <p class="text-gray-600"><i class="fas fa-link mr-2"></i> Referral Code: <span class="font-semibold"><?php echo $user['referral_code']; ?></span></p>
            <p class="text-gray-600"><i class="fas fa-calendar-alt mr-2"></i> Registered: <?php echo date('M d, Y', strtotime($user['registration_date'])); ?></p>
            <p class="text-gray-600">
              <i class="fas fa-circle mr-2" style="color: <?php echo $user['status'] == 'active' ? '#16a34a' : '#ef4444'; ?>"></i>
              Status: <span class="font-semibold"><?php echo ucfirst($user['status']); ?></span>
            </p>
          </div>
        </div>
      </div>

      <!-- Referral Statistics -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <div class="bg-white rounded-lg shadow p-6">
          <div class="flex items-center">
            <div class="rounded-full bg-blue-100 p-3 mr-4">
              <i class="fas fa-users text-blue-600 text-xl"></i>
            </div>
            <div>
              <h3 class="text-lg font-semibold text-gray-600">Direct Referrals</h3>
              <p class="text-2xl font-bold text-gray-800"><?php echo $stats['direct_referrals']; ?></p>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
          <div class="flex items-center">
            <div class="rounded-full bg-purple-100 p-3 mr-4">
              <i class="fas fa-project-diagram text-purple-600 text-xl"></i>
            </div>
            <div>
              <h3 class="text-lg font-semibold text-gray-600">Total Network</h3>
              <p class="text-2xl font-bold text-gray-800"><?php echo $stats['referral_count']; ?></p>
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
              <p class="text-2xl font-bold text-gray-800">$<?php echo number_format($stats['total_earnings'], 2); ?></p>
            </div>
          </div>
        </div>
      </div>

      <!-- Referral Tree Visualization -->
      <div class="bg-white rounded-lg shadow p-6 mb-6 overflow-auto">
        <h2 class="text-xl font-bold text-gray-800 mb-4">Referral Network Tree</h2>

        <?php if (empty($referral_tree)): ?>
          <div class="text-center py-8">
            <i class="fas fa-users text-gray-300 text-5xl mb-4"></i>
            <p class="text-gray-500">No referrals found for this user.</p>
          </div>
        <?php else: ?>
          <div class="tree overflow-x-auto pb-8 pl-4" style="min-width: 800px;">
            <ul class="list-none">
              <li>
                <div class="bg-blue-600 text-white rounded-lg p-4 inline-block shadow-md">
                  <h3 class="font-bold"><?php echo $user['full_name']; ?></h3>
                  <p class="text-sm">Referral Code: <?php echo $user['referral_code']; ?></p>
                </div>

                <?php if (!empty($referral_tree)): ?>
                  <ul class="list-none mt-8">
                    <?php foreach ($referral_tree as $level1): ?>
                      <li>
                        <div class="bg-green-600 text-white rounded-lg p-3 inline-block shadow-md">
                          <h4 class="font-bold"><?php echo $level1['full_name']; ?></h4>
                          <p class="text-xs">Investments: $<?php echo number_format($level1['total_investments'] ?? 0, 2); ?></p>
                          <p class="text-xs">Referrals: <?php echo $level1['referral_count']; ?></p>
                        </div>

                        <?php if (!empty($level1['children'])): ?>
                          <ul class="list-none mt-8">
                            <?php foreach ($level1['children'] as $level2): ?>
                              <li>
                                <div class="bg-indigo-500 text-white rounded-lg p-3 inline-block shadow-md">
                                  <h4 class="font-bold"><?php echo $level2['full_name']; ?></h4>
                                  <p class="text-xs">Investments: $<?php echo number_format($level2['total_investments'] ?? 0, 2); ?></p>
                                  <p class="text-xs">Referrals: <?php echo $level2['referral_count']; ?></p>
                                </div>

                                <?php if (!empty($level2['children'])): ?>
                                  <ul class="list-none mt-8">
                                    <?php foreach ($level2['children'] as $level3): ?>
                                      <li>
                                        <div class="bg-purple-500 text-white rounded-lg p-2 inline-block shadow-md">
                                          <h4 class="font-bold"><?php echo $level3['full_name']; ?></h4>
                                          <p class="text-xs">Investments: $<?php echo number_format($level3['total_investments'] ?? 0, 2); ?></p>
                                        </div>
                                      </li>
                                    <?php endforeach; ?>
                                  </ul>
                                <?php endif; ?>
                              </li>
                            <?php endforeach; ?>
                          </ul>
                        <?php endif; ?>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                <?php endif; ?>
              </li>
            </ul>
          </div>
        <?php endif; ?>
      </div>

      <!-- Direct Referrals -->
      <div class="bg-white rounded-lg shadow mb-6">
        <div class="px-6 py-4 border-b border-gray-200">
          <h2 class="text-xl font-bold text-gray-800">Direct Referrals</h2>
        </div>

        <?php if (count($referrals) > 0): ?>
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Registered</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Investment</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Their Referrals</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($referrals as $referral): ?>
                  <tr>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="text-sm font-medium text-gray-900"><?php echo $referral['full_name']; ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="text-sm text-gray-500"><?php echo $referral['email']; ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="text-sm text-gray-500"><?php echo date('M d, Y', strtotime($referral['registration_date'])); ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php echo $referral['status'] == 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                        <?php echo ucfirst($referral['status']); ?>
                      </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="text-sm text-gray-900">$<?php echo number_format($referral['total_investments'] ?? 0, 2); ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      <?php echo $referral['their_referrals']; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                      <a href="view_user.php?id=<?php echo $referral['id']; ?>" class="text-indigo-600 hover:text-indigo-900 mr-3">View User</a>
                      <a href="view_referrals.php?user_id=<?php echo $referral['id']; ?>" class="text-blue-600 hover:text-blue-900">View Referrals</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="p-6 text-center">
            <p class="text-gray-500">No direct referrals found for this user.</p>
          </div>
        <?php endif; ?>
      </div>

      <!-- Referral Earnings -->
      <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b border-gray-200">
          <h2 class="text-xl font-bold text-gray-800">Referral Earnings</h2>
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
            <p class="text-gray-500">No commission earnings found for this user.</p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</body>

</html>