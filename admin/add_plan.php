<?php
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

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  $name = trim($_POST['name']);
  $description = trim($_POST['description']);
  $min_amount = floatval($_POST['min_amount']);
  $max_amount = !empty($_POST['max_amount']) ? floatval($_POST['max_amount']) : null;
  $daily_profit_rate = floatval($_POST['daily_profit_rate']);
  $duration_days = intval($_POST['duration_days']);
  $referral_commission_rate = floatval($_POST['referral_commission_rate']);
  $is_active = isset($_POST['is_active']) ? 1 : 0;

  // Validate inputs
  $errors = [];

  if (empty($name)) {
    $errors[] = 'Plan name is required.';
  }

  if ($min_amount <= 0) {
    $errors[] = 'Minimum amount must be greater than zero.';
  }

  if ($max_amount !== null && $max_amount <= $min_amount) {
    $errors[] = 'Maximum amount must be greater than minimum amount.';
  }

  if ($daily_profit_rate <= 0) {
    $errors[] = 'Daily profit rate must be greater than zero.';
  }

  if ($duration_days <= 0) {
    $errors[] = 'Duration must be greater than zero days.';
  }

  if ($referral_commission_rate < 0) {
    $errors[] = 'Referral commission rate cannot be negative.';
  }

  if (empty($errors)) {
    // Create new plan
    $stmt = $db->prepare("
            INSERT INTO investment_plans (
                name, description, min_amount, max_amount, daily_profit_rate, 
                duration_days, referral_commission_rate, is_active
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

    $stmt->bind_param(
      "ssdddidi",
      $name,
      $description,
      $min_amount,
      $max_amount,
      $daily_profit_rate,
      $duration_days,
      $referral_commission_rate,
      $is_active
    );

    if ($stmt->execute()) {
      $_SESSION['admin_message'] = 'New investment plan created successfully.';
      $_SESSION['admin_message_type'] = 'success';
      header('Location: referrals.php');
      exit;
    } else {
      $errors[] = 'Failed to create plan: ' . $db->error;
    }
  }
}

$db->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Add Investment Plan - Admin Panel</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
</head>

<body class="bg-gray-100">
  <div class="min-h-screen flex">
    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="flex-grow p-6">
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Add New Investment Plan</h1>
        <a href="referrals.php" class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 transition">
          <i class="fas fa-arrow-left mr-2"></i> Back to Referrals
        </a>
      </div>

      <?php if (!empty($errors)): ?>
        <div class="mb-6 p-4 bg-red-100 text-red-800 rounded-lg">
          <ul class="list-disc pl-5">
            <?php foreach ($errors as $error): ?>
              <li><?php echo $error; ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <div class="bg-white rounded-lg shadow p-6">
        <form method="post" action="">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Plan Name</label>
              <input type="text" id="name" name="name" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>"
                class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500" required>
            </div>

            <div>
              <label for="is_active" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
              <div class="flex items-center mt-2">
                <input type="checkbox" id="is_active" name="is_active" value="1" <?php echo !isset($_POST['is_active']) || $_POST['is_active'] ? 'checked' : ''; ?>
                  class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                <label for="is_active" class="ml-2 block text-sm text-gray-700">Active</label>
              </div>
            </div>

            <div class="md:col-span-2">
              <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
              <textarea id="description" name="description" rows="4"
                class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
            </div>

            <div>
              <label for="min_amount" class="block text-sm font-medium text-gray-700 mb-1">Minimum Investment Amount ($)</label>
              <input type="number" id="min_amount" name="min_amount" value="<?php echo isset($_POST['min_amount']) ? $_POST['min_amount'] : '1000'; ?>" min="0" step="0.01"
                class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500" required>
            </div>

            <div>
              <label for="max_amount" class="block text-sm font-medium text-gray-700 mb-1">Maximum Investment Amount ($)</label>
              <input type="number" id="max_amount" name="max_amount" value="<?php echo isset($_POST['max_amount']) ? $_POST['max_amount'] : ''; ?>" min="0" step="0.01" placeholder="Leave empty for unlimited"
                class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
            </div>

            <div>
              <label for="daily_profit_rate" class="block text-sm font-medium text-gray-700 mb-1">Daily Profit Rate (%)</label>
              <input type="number" id="daily_profit_rate" name="daily_profit_rate" value="<?php echo isset($_POST['daily_profit_rate']) ? $_POST['daily_profit_rate'] : '20'; ?>" min="0" max="100" step="0.01"
                class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500" required>
            </div>

            <div>
              <label for="duration_days" class="block text-sm font-medium text-gray-700 mb-1">Duration (Days)</label>
              <input type="number" id="duration_days" name="duration_days" value="<?php echo isset($_POST['duration_days']) ? $_POST['duration_days'] : '1'; ?>" min="1"
                class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500" required>
            </div>

            <div class="md:col-span-2">
              <label for="referral_commission_rate" class="block text-sm font-medium text-gray-700 mb-1">Referral Commission Rate (%)</label>
              <input type="number" id="referral_commission_rate" name="referral_commission_rate" value="<?php echo isset($_POST['referral_commission_rate']) ? $_POST['referral_commission_rate'] : '10'; ?>" min="0" max="100" step="0.01"
                class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500" required>
              <p class="mt-1 text-sm text-gray-500">This is the percentage of the investment amount that will be paid as commission to the referrer.</p>
            </div>
          </div>

          <div class="mt-6 flex items-center justify-end">
            <button type="button" onclick="window.location.href='referrals.php'" class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 transition mr-3">
              Cancel
            </button>
            <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition">
              Create Investment Plan
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</body>

</html>