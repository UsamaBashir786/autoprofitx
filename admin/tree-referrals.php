<?php
session_start();
include '../config/db.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
  header("Location: admin-login.php");
  exit();
}
// Then verify the function exists before using it
if (!function_exists('connect_db')) {
  die("Database connection function not found. Check your configuration files.");
}

// Rest of your code...
// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
  header("Location: admin-login.php");
  exit();
}
// Database connection parameters
define('DB_HOST', 'localhost');       // Usually 'localhost' or '127.0.0.1'
define('DB_USER', 'root');            // Your database username
define('DB_PASS', '');                // Your database password
define('DB_NAME', 'autoproftx');      // Your database name

// Connect to database function
function connect_db()
{
  $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

  // Check connection
  if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
  }

  return $conn;
}

// Optional: Additional database helper functions
function query($sql, $conn = null)
{
  $connection = $conn ?: connect_db();
  $result = $connection->query($sql);

  if (!$result) {
    die("Query failed: " . $connection->error);
  }

  return $result;
}

function get_row($sql, $conn = null)
{
  $connection = $conn ?: connect_db();
  $result = query($sql, $connection);

  if ($result->num_rows > 0) {
    return $result->fetch_assoc();
  }

  return null;
}
$db = connect_db();

// Handle commission approval/rejection
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  if (isset($_POST['approve_commission'])) {
    $commission_id = intval($_POST['commission_id']);

    $stmt = $db->prepare("
            UPDATE referral_commissions 
            SET status = 'paid',
                paid_at = CURRENT_TIMESTAMP
            WHERE id = ? AND status = 'pending'
        ");

    $stmt->bind_param("i", $commission_id);
    $stmt->execute();

    // Get commission details
    $stmt = $db->prepare("
            SELECT referrer_id, commission_amount
            FROM referral_commissions
            WHERE id = ?
        ");

    $stmt->bind_param("i", $commission_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $commission = $result->fetch_assoc();

    // Update referrer's wallet
    $stmt = $db->prepare("
            UPDATE wallets 
            SET balance = balance + ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE user_id = ?
        ");

    $stmt->bind_param("di", $commission['commission_amount'], $commission['referrer_id']);
    $stmt->execute();

    $_SESSION['admin_message'] = 'Referral commission approved successfully.';
    $_SESSION['admin_message_type'] = 'success';
  } elseif (isset($_POST['reject_commission'])) {
    $commission_id = intval($_POST['commission_id']);

    $stmt = $db->prepare("
            UPDATE referral_commissions 
            SET status = 'cancelled'
            WHERE id = ? AND status = 'pending'
        ");

    $stmt->bind_param("i", $commission_id);
    $stmt->execute();

    $_SESSION['admin_message'] = 'Referral commission rejected.';
    $_SESSION['admin_message_type'] = 'warning';
  }

  header('Location: referrals.php');
  exit;
}

// Get all commissions
$commissions_query = "
    SELECT rc.*, 
           u_referrer.full_name as referrer_name, 
           u_referred.full_name as referred_name,
           ip.name as plan_name
    FROM referral_commissions rc
    JOIN users u_referrer ON rc.referrer_id = u_referrer.id
    JOIN users u_referred ON rc.referred_id = u_referred.id
    JOIN investments i ON rc.investment_id = i.id
    JOIN investment_plans ip ON i.plan_id = ip.id
    ORDER BY rc.created_at DESC
";

$result = $db->query($commissions_query);
$commissions = [];

while ($row = $result->fetch_assoc()) {
  $commissions[] = $row;
}

// Get top referrers
$top_referrers_query = "
    SELECT u.id, u.full_name, u.email, u.referral_code,
           (SELECT COUNT(*) FROM users WHERE referred_by = u.id) as total_referrals,
           (SELECT COALESCE(SUM(commission_amount), 0) FROM referral_commissions WHERE referrer_id = u.id AND status = 'paid') as total_earnings
    FROM users u
    HAVING total_referrals > 0
    ORDER BY total_referrals DESC
    LIMIT 10
";

$result = $db->query($top_referrers_query);
$top_referrers = [];

while ($row = $result->fetch_assoc()) {
  $top_referrers[] = $row;
}

$db->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Referrals - Admin Panel</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
</head>

<body class="bg-gray-100">
  <div class="min-h-screen flex">
    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="flex-grow p-6">
      <h1 class="text-2xl font-bold text-gray-800 mb-6">Manage Referrals</h1>

      <?php if (isset($_SESSION['admin_message'])): ?>
        <div class="mb-6 p-4 rounded-lg <?php echo $_SESSION['admin_message_type'] == 'success' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
          <?php echo $_SESSION['admin_message']; ?>
        </div>
        <?php unset($_SESSION['admin_message'], $_SESSION['admin_message_type']); ?>
      <?php endif; ?>

      <!-- Statistics Cards -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <div class="bg-white rounded-lg shadow p-6">
          <div class="flex items-center">
            <div class="rounded-full bg-blue-100 p-3 mr-4">
              <i class="fas fa-users text-blue-600 text-xl"></i>
            </div>
            <div>
              <h3 class="text-lg font-semibold text-gray-600">Total Referral Users</h3>
              <p class="text-2xl font-bold text-gray-800">
                <?php
                $db = connect_db();
                $result = $db->query("SELECT COUNT(*) as count FROM users WHERE referred_by IS NOT NULL");
                $row = $result->fetch_assoc();
                echo $row['count'];
                $db->close();
                ?>
              </p>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
          <div class="flex items-center">
            <div class="rounded-full bg-green-100 p-3 mr-4">
              <i class="fas fa-money-bill-wave text-green-600 text-xl"></i>
            </div>
            <div>
              <h3 class="text-lg font-semibold text-gray-600">Total Commissions Paid</h3>
              <p class="text-2xl font-bold text-gray-800">
                $<?php
                  $db = connect_db();
                  $result = $db->query("SELECT COALESCE(SUM(commission_amount), 0) as total FROM referral_commissions WHERE status = 'paid'");
                  $row = $result->fetch_assoc();
                  echo number_format($row['total'], 2);
                  $db->close();
                  ?>
              </p>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
          <div class="flex items-center">
            <div class="rounded-full bg-purple-100 p-3 mr-4">
              <i class="fas fa-chart-line text-purple-600 text-xl"></i>
            </div>
            <div>
              <h3 class="text-lg font-semibold text-gray-600">Pending Commissions</h3>
              <p class="text-2xl font-bold text-gray-800">
                $<?php
                  $db = connect_db();
                  $result = $db->query("SELECT COALESCE(SUM(commission_amount), 0) as total FROM referral_commissions WHERE status = 'pending'");
                  $row = $result->fetch_assoc();
                  echo number_format($row['total'], 2);
                  $db->close();
                  ?>
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Investment Plan Commission Settings -->
      <div class="bg-white rounded-lg shadow mb-6">
        <div class="px-6 py-4 border-b border-gray-200">
          <h2 class="text-xl font-bold text-gray-800">Investment Plan Commission Settings</h2>
        </div>

        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Plan Name</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Investment Range</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Duration</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Daily Profit</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Referral Commission</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <?php
              $db = connect_db();
              $result = $db->query("SELECT * FROM investment_plans ORDER BY min_amount ASC");
              while ($plan = $result->fetch_assoc()):
              ?>
                <tr>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm font-medium text-gray-900"><?php echo $plan['name']; ?></div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-gray-500">
                      $<?php echo number_format($plan['min_amount'], 2); ?> -
                      <?php echo $plan['max_amount'] ? '$' . number_format($plan['max_amount'], 2) : 'Unlimited'; ?>
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-gray-500"><?php echo $plan['duration_days']; ?> days</div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-gray-500"><?php echo $plan['daily_profit_rate']; ?>%</div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm font-medium text-green-600"><?php echo $plan['referral_commission_rate']; ?>%</div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $plan['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                      <?php echo $plan['is_active'] ? 'Active' : 'Inactive'; ?>
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                    <a href="edit_plan.php?id=<?php echo $plan['id']; ?>" class="text-indigo-600 hover:text-indigo-900 mr-3">Edit</a>
                    <a href="toggle_plan.php?id=<?php echo $plan['id']; ?>" class="text-<?php echo $plan['is_active'] ? 'red' : 'green'; ?>-600 hover:text-<?php echo $plan['is_active'] ? 'red' : 'green'; ?>-900">
                      <?php echo $plan['is_active'] ? 'Disable' : 'Enable'; ?>
                    </a>
                  </td>
                </tr>
              <?php endwhile; ?>
              <?php $db->close(); ?>
            </tbody>
          </table>
        </div>

        <div class="p-6 border-t border-gray-200">
          <a href="add_plan.php" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition">
            <i class="fas fa-plus mr-2"></i> Add New Plan
          </a>
        </div>
      </div>

      <!-- Top Referrers -->
      <div class="bg-white rounded-lg shadow mb-6">
        <div class="px-6 py-4 border-b border-gray-200">
          <h2 class="text-xl font-bold text-gray-800">Top Referrers</h2>
        </div>

        <?php if (count($top_referrers) > 0): ?>
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Referral Code</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Referrals</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Earnings</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($top_referrers as $referrer): ?>
                  <tr>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="text-sm font-medium text-gray-900"><?php echo $referrer['full_name']; ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="text-sm text-gray-500"><?php echo $referrer['email']; ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="text-sm text-gray-500"><?php echo $referrer['referral_code']; ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="text-sm font-medium text-gray-900"><?php echo $referrer['total_referrals']; ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="text-sm font-medium text-green-600">$<?php echo number_format($referrer['total_earnings'], 2); ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                      <a href="view_user.php?id=<?php echo $referrer['id']; ?>" class="text-indigo-600 hover:text-indigo-900 mr-3">View User</a>
                      <a href="view_referrals.php?user_id=<?php echo $referrer['id']; ?>" class="text-blue-600 hover:text-blue-900">View Referrals</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="p-6 text-center">
            <p class="text-gray-500">No referral data available yet.</p>
          </div>
        <?php endif; ?>
      </div>

      <!-- Referral Commissions -->
      <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b border-gray-200">
          <h2 class="text-xl font-bold text-gray-800">Referral Commissions</h2>
        </div>

        <?php if (count($commissions) > 0): ?>
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Referrer</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Referred User</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Plan</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Investment Amount</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Commission</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($commissions as $commission): ?>
                  <tr>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="text-sm text-gray-900"><?php echo date('M d, Y', strtotime($commission['created_at'])); ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="text-sm font-medium text-gray-900"><?php echo $commission['referrer_name']; ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="text-sm text-gray-500"><?php echo $commission['referred_name']; ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="text-sm text-gray-500"><?php echo $commission['plan_name']; ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="text-sm text-gray-900">$<?php echo number_format($commission['investment_amount'], 2); ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="text-sm font-medium text-green-600">$<?php echo number_format($commission['commission_amount'], 2); ?> (<?php echo $commission['commission_rate']; ?>%)</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    <?php echo $commission['status'] == 'paid' ? 'bg-green-100 text-green-800' : ($commission['status'] == 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'); ?>">
                        <?php echo ucfirst($commission['status']); ?>
                      </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                      <?php if ($commission['status'] == 'pending'): ?>
                        <form method="post" class="inline-block">
                          <input type="hidden" name="commission_id" value="<?php echo $commission['id']; ?>">
                          <button type="submit" name="approve_commission" class="text-green-600 hover:text-green-900 mr-3" onclick="return confirm('Are you sure you want to approve this commission?')">
                            Approve
                          </button>
                        </form>
                        <form method="post" class="inline-block">
                          <input type="hidden" name="commission_id" value="<?php echo $commission['id']; ?>">
                          <button type="submit" name="reject_commission" class="text-red-600 hover:text-red-900" onclick="return confirm('Are you sure you want to reject this commission?')">
                            Reject
                          </button>
                        </form>
                      <?php else: ?>
                        <span class="text-gray-400">No actions available</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="p-6 text-center">
            <p class="text-gray-500">No commission data available yet.</p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</body>

</html>