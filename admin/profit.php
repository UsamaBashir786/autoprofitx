<?php
session_start();
include '../config/db.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
  header("Location: admin-login.php");
  exit();
}

// Get admin info
$admin_id = $_SESSION['admin_id'];
$admin_name = $_SESSION['admin_name'] ?? 'Admin';

// Handle form submissions
$success_message = '';
$error_message = '';

// Update Investment Plan Profits
if (isset($_POST['update_investment_plans'])) {
  foreach ($_POST['plan_id'] as $key => $plan_id) {
    $daily_profit_rate = floatval($_POST['daily_profit_rate'][$key]);
    $referral_commission_rate = floatval($_POST['referral_commission_rate'][$key]);

    $update_sql = "UPDATE investment_plans 
                  SET daily_profit_rate = ?, 
                      referral_commission_rate = ? 
                  WHERE id = ?";

    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("ddi", $daily_profit_rate, $referral_commission_rate, $plan_id);

    if ($stmt->execute()) {
      $success_message = "Investment plans updated successfully!";
    } else {
      $error_message = "Error updating investment plans: " . $conn->error;
      break;
    }
  }
}

// Update Staking Plans
if (isset($_POST['update_staking_plans'])) {
  foreach ($_POST['staking_id'] as $key => $staking_id) {
    $apy_rate = floatval($_POST['apy_rate'][$key]);
    $early_withdrawal_fee = floatval($_POST['early_withdrawal_fee'][$key]);

    $update_sql = "UPDATE staking_plans 
                  SET apy_rate = ?, 
                      early_withdrawal_fee = ? 
                  WHERE id = ?";

    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("ddi", $apy_rate, $early_withdrawal_fee, $staking_id);

    if ($stmt->execute()) {
      $success_message = "Staking plans updated successfully!";
    } else {
      $error_message = "Error updating staking plans: " . $conn->error;
      break;
    }
  }
}

// Update AlphaMiner Token Settings
if (isset($_POST['update_alpha_settings'])) {
  // In a real implementation, you'd save this to a settings table
  // For now, we'll just show a success message
  $alpha_profit_rate = floatval($_POST['alpha_profit_rate']);
  $alpha_holding_period = intval($_POST['alpha_holding_period']);

  // Create a settings table if it doesn't exist
  $check_table_sql = "SELECT COUNT(*) as count FROM information_schema.tables 
                      WHERE table_schema = DATABASE() AND table_name = 'system_settings'";
  $table_result = $conn->query($check_table_sql);
  $table_exists = ($table_result && $table_result->fetch_assoc()['count'] > 0);

  if (!$table_exists) {
    $create_table_sql = "CREATE TABLE system_settings (
      id INT AUTO_INCREMENT PRIMARY KEY,
      setting_key VARCHAR(100) NOT NULL UNIQUE,
      setting_value TEXT NOT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $conn->query($create_table_sql);
  }

  // Update or insert alpha miner settings
  $settings = [
    'alpha_profit_rate' => $alpha_profit_rate,
    'alpha_holding_period' => $alpha_holding_period
  ];

  foreach ($settings as $key => $value) {
    $check_sql = "SELECT id FROM system_settings WHERE setting_key = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
      // Update
      $update_sql = "UPDATE system_settings SET setting_value = ? WHERE setting_key = ?";
      $update_stmt = $conn->prepare($update_sql);
      $update_stmt->bind_param("ss", $value, $key);
      $update_stmt->execute();
    } else {
      // Insert
      $insert_sql = "INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)";
      $insert_stmt = $conn->prepare($insert_sql);
      $insert_stmt->bind_param("ss", $key, $value);
      $insert_stmt->execute();
    }
  }

  $success_message = "AlphaMiner token settings updated successfully!";
}

// Update Referral Commission Structure
if (isset($_POST['update_referral_structure'])) {
  $direct_referral_bonus = floatval($_POST['direct_referral_bonus']);
  $level1_commission = floatval($_POST['level1_commission']);
  $level2_commission = floatval($_POST['level2_commission']);
  $level3_commission = floatval($_POST['level3_commission']);

  // Create a referral_structure table if it doesn't exist
  $check_table_sql = "SELECT COUNT(*) as count FROM information_schema.tables 
                      WHERE table_schema = DATABASE() AND table_name = 'referral_structure'";
  $table_result = $conn->query($check_table_sql);
  $table_exists = ($table_result && $table_result->fetch_assoc()['count'] > 0);

  if (!$table_exists) {
    $create_table_sql = "CREATE TABLE referral_structure (
      id INT AUTO_INCREMENT PRIMARY KEY,
      level INT NOT NULL UNIQUE,
      commission_rate DECIMAL(5,2) NOT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $conn->query($create_table_sql);

    // Insert initial structure if table was just created
    $insert_sql = "INSERT INTO referral_structure (level, commission_rate) VALUES 
                  (1, ?), (2, ?), (3, ?)";
    $stmt = $conn->prepare($insert_sql);
    $stmt->bind_param("ddd", $level1_commission, $level2_commission, $level3_commission);
    $stmt->execute();
  } else {
    // Update existing structure
    for ($i = 1; $i <= 3; $i++) {
      $rate = $i == 1 ? $level1_commission : ($i == 2 ? $level2_commission : $level3_commission);
      $update_sql = "UPDATE referral_structure SET commission_rate = ? WHERE level = ?";
      $stmt = $conn->prepare($update_sql);
      $stmt->bind_param("di", $rate, $i);
      $stmt->execute();
    }
  }

  // Update default referral bonus in system_settings
  $check_sql = "SELECT id FROM system_settings WHERE setting_key = 'direct_referral_bonus'";
  $stmt = $conn->prepare($check_sql);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows > 0) {
    $update_sql = "UPDATE system_settings SET setting_value = ? WHERE setting_key = 'direct_referral_bonus'";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("s", $direct_referral_bonus);
    $update_stmt->execute();
  } else {
    $insert_sql = "INSERT INTO system_settings (setting_key, setting_value) VALUES ('direct_referral_bonus', ?)";
    $insert_stmt = $conn->prepare($insert_sql);
    $insert_stmt->bind_param("s", $direct_referral_bonus);
    $insert_stmt->execute();
  }

  $success_message = "Referral commission structure updated successfully!";
}

// Update Leaderboard Bonus Settings
if (isset($_POST['update_leaderboard_bonus'])) {
  $first_place = floatval($_POST['first_place']);
  $second_place = floatval($_POST['second_place']);
  $third_place = floatval($_POST['third_place']);

  // Update the stored procedure for distributing bonuses
  $update_procedure_sql = "
  DROP PROCEDURE IF EXISTS `distribute_monthly_bonuses`;
  
  DELIMITER //
  
  CREATE PROCEDURE `distribute_monthly_bonuses` (IN `bonus_month` VARCHAR(7))
  BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_user_id INT;
    DECLARE v_rank INT;
    DECLARE v_bonus_amount DECIMAL(15,2);
    DECLARE v_reference_id VARCHAR(50);
    
    -- Cursor for top 3 depositors
    DECLARE bonus_cursor CURSOR FOR 
      SELECT user_id, `rank` FROM leaderboard_deposits 
      WHERE period = bonus_month AND `rank` <= 3
      ORDER BY `rank`;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    -- Check if bonuses already distributed
    IF (SELECT COUNT(*) FROM leaderboard_bonuses WHERE bonus_month = bonus_month) > 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Bonuses already distributed for this month';
    END IF;
    
    OPEN bonus_cursor;
    
    bonus_loop: LOOP
      FETCH bonus_cursor INTO v_user_id, v_rank;
      
      IF done THEN
        LEAVE bonus_loop;
      END IF;
      
      -- Determine bonus amount based on rank
      CASE v_rank
        WHEN 1 THEN SET v_bonus_amount = $first_place;
        WHEN 2 THEN SET v_bonus_amount = $second_place;
        WHEN 3 THEN SET v_bonus_amount = $third_place;
        ELSE SET v_bonus_amount = 0.00;
      END CASE;
      
      -- Create reference ID
      SET v_reference_id = CONCAT('LDRBNS-', bonus_month, '-', v_rank);
      
      -- Start transaction
      START TRANSACTION;
      
      -- Insert bonus record
      INSERT INTO leaderboard_bonuses (
        user_id, bonus_month, rank_position, bonus_amount, status, paid_at
      ) VALUES (
        v_user_id, bonus_month, v_rank, v_bonus_amount, 'paid', NOW()
      );
      
      -- Add to wallet
      UPDATE wallets SET 
        balance = balance + v_bonus_amount,
        updated_at = NOW()
      WHERE user_id = v_user_id;
      
      -- Add transaction record
      INSERT INTO transactions (
        user_id, transaction_type, amount, status, description, reference_id
      ) VALUES (
        v_user_id, 'deposit', v_bonus_amount, 'completed', 
        CONCAT('Leaderboard bonus for rank ', v_rank, ' (', bonus_month, ')'), 
        v_reference_id
      );
      
      COMMIT;
    END LOOP;
    
    CLOSE bonus_cursor;
  END//
  
  DELIMITER ;
  ";

  // This would need to be executed differently in a real environment
  // For now, we'll just store the settings in the system_settings table
  $settings = [
    'leaderboard_first_place' => $first_place,
    'leaderboard_second_place' => $second_place,
    'leaderboard_third_place' => $third_place
  ];

  foreach ($settings as $key => $value) {
    $check_sql = "SELECT id FROM system_settings WHERE setting_key = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
      $update_sql = "UPDATE system_settings SET setting_value = ? WHERE setting_key = ?";
      $update_stmt = $conn->prepare($update_sql);
      $update_stmt->bind_param("ss", $value, $key);
      $update_stmt->execute();
    } else {
      $insert_sql = "INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)";
      $insert_stmt = $conn->prepare($insert_sql);
      $insert_stmt->bind_param("ss", $key, $value);
      $insert_stmt->execute();
    }
  }

  $success_message = "Leaderboard bonus settings updated successfully!";
}

// Update Daily Check-in Rewards
if (isset($_POST['update_checkin_rewards'])) {
  foreach ($_POST['checkin_id'] as $key => $checkin_id) {
    $reward_amount = floatval($_POST['reward_amount'][$key]);

    $update_sql = "UPDATE checkin_rewards SET reward_amount = ? WHERE id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("di", $reward_amount, $checkin_id);

    if ($stmt->execute()) {
      $success_message = "Check-in rewards updated successfully!";
    } else {
      $error_message = "Error updating check-in rewards: " . $conn->error;
      break;
    }
  }
}

// Fetch Investment Plans
$investment_plans = [];
$plans_sql = "SELECT * FROM investment_plans ORDER BY min_amount";
$plans_result = $conn->query($plans_sql);
if ($plans_result) {
  while ($row = $plans_result->fetch_assoc()) {
    $investment_plans[] = $row;
  }
}

// Fetch Staking Plans
$staking_plans = [];
$staking_sql = "SELECT * FROM staking_plans ORDER BY duration_days";
$staking_result = $conn->query($staking_sql);
if ($staking_result) {
  while ($row = $staking_result->fetch_assoc()) {
    $staking_plans[] = $row;
  }
}

// Fetch AlphaMiner Token Settings
$alpha_profit_rate = 6.5; // Default value
$alpha_holding_period = 24; // Default hours

$alpha_sql = "SELECT * FROM system_settings WHERE setting_key IN ('alpha_profit_rate', 'alpha_holding_period')";
$alpha_result = $conn->query($alpha_sql);
if ($alpha_result) {
  while ($row = $alpha_result->fetch_assoc()) {
    if ($row['setting_key'] == 'alpha_profit_rate') {
      $alpha_profit_rate = floatval($row['setting_value']);
    } else if ($row['setting_key'] == 'alpha_holding_period') {
      $alpha_holding_period = intval($row['setting_value']);
    }
  }
}

// Fetch Referral Structure
$direct_referral_bonus = 100; // Default value
$level1_commission = 10; // Default value
$level2_commission = 5; // Default value
$level3_commission = 2; // Default value

// Check if table exists
$check_table_sql = "SELECT COUNT(*) as count FROM information_schema.tables 
                    WHERE table_schema = DATABASE() AND table_name = 'referral_structure'";
$table_result = $conn->query($check_table_sql);
$table_exists = ($table_result && $table_result->fetch_assoc()['count'] > 0);

if ($table_exists) {
  $referral_sql = "SELECT * FROM referral_structure ORDER BY level";
  $referral_result = $conn->query($referral_sql);
  if ($referral_result) {
    while ($row = $referral_result->fetch_assoc()) {
      switch ($row['level']) {
        case 1:
          $level1_commission = $row['commission_rate'];
          break;
        case 2:
          $level2_commission = $row['commission_rate'];
          break;
        case 3:
          $level3_commission = $row['commission_rate'];
          break;
      }
    }
  }
}

// Get direct referral bonus from system_settings
$ref_bonus_sql = "SELECT setting_value FROM system_settings WHERE setting_key = 'direct_referral_bonus'";
$ref_bonus_result = $conn->query($ref_bonus_sql);
if ($ref_bonus_result && $ref_bonus_result->num_rows > 0) {
  $direct_referral_bonus = floatval($ref_bonus_result->fetch_assoc()['setting_value']);
}

// Fetch Leaderboard Bonus Settings
$first_place = 2500; // Default value
$second_place = 2000; // Default value
$third_place = 1500; // Default value

$leaderboard_sql = "SELECT * FROM system_settings WHERE setting_key IN ('leaderboard_first_place', 'leaderboard_second_place', 'leaderboard_third_place')";
$leaderboard_result = $conn->query($leaderboard_sql);
if ($leaderboard_result) {
  while ($row = $leaderboard_result->fetch_assoc()) {
    if ($row['setting_key'] == 'leaderboard_first_place') {
      $first_place = floatval($row['setting_value']);
    } else if ($row['setting_key'] == 'leaderboard_second_place') {
      $second_place = floatval($row['setting_value']);
    } else if ($row['setting_key'] == 'leaderboard_third_place') {
      $third_place = floatval($row['setting_value']);
    }
  }
}

// Fetch Daily Check-in Rewards
$checkin_rewards = [];
$checkin_sql = "SELECT * FROM checkin_rewards ORDER BY streak_day";
$checkin_result = $conn->query($checkin_sql);
if ($checkin_result) {
  while ($row = $checkin_result->fetch_assoc()) {
    $checkin_rewards[] = $row;
  }
}

// Get profit statistics
$total_profit_paid = 0;
$profit_sql = "SELECT SUM(amount) as total FROM transactions WHERE transaction_type = 'profit' AND status = 'completed'";
$profit_result = $conn->query($profit_sql);
if ($profit_result && $profit_result->num_rows > 0) {
  $row = $profit_result->fetch_assoc();
  $total_profit_paid = $row['total'] ?? 0;
}

$referral_commission_paid = 0;
$commission_sql = "SELECT SUM(commission_amount) as total FROM referral_commissions WHERE status = 'paid'";
$commission_result = $conn->query($commission_sql);
if ($commission_result && $commission_result->num_rows > 0) {
  $row = $commission_result->fetch_assoc();
  $referral_commission_paid = $row['total'] ?? 0;
}

$leaderboard_paid = 0;
$leaderboard_sql = "SELECT SUM(bonus_amount) as total FROM leaderboard_bonuses WHERE status = 'paid'";
$leaderboard_result = $conn->query($leaderboard_sql);
if ($leaderboard_result && $leaderboard_result->num_rows > 0) {
  $row = $leaderboard_result->fetch_assoc();
  $leaderboard_paid = $row['total'] ?? 0;
}

// Add a function to safely format numbers
function safeNumberFormat($value, $decimals = 0)
{
  // Ensure the value is a number and not null
  $value = is_null($value) ? 0 : (float)$value;
  return number_format($value, $decimals);
}

// Close connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Profit & Commission Management - AutoProftX</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    /* Custom Scrollbar */
    ::-webkit-scrollbar {
      width: 8px;
      height: 8px;
    }

    ::-webkit-scrollbar-track {
      background: #1f2937;
    }

    ::-webkit-scrollbar-thumb {
      background: #4b5563;
      border-radius: 4px;
    }

    ::-webkit-scrollbar-thumb:hover {
      background: #f59e0b;
    }

    /* Active nav item */
    .nav-item.active {
      border-left: 3px solid #f59e0b;
      background-color: rgba(245, 158, 11, 0.1);
    }

    /* Card hover effect */
    .stat-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    }

    /* Gold gradient */
    .gold-gradient {
      background: linear-gradient(135deg, #f59e0b, #d97706);
    }

    /* Form styling */
    .form-input {
      background-color: #374151;
      border-color: #4B5563;
      color: #E5E7EB;
    }

    .form-input:focus {
      border-color: #F59E0B;
      box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.25);
    }
  </style>
</head>

<body class="bg-gray-900 text-gray-100 flex min-h-screen">
  <?php include 'includes/sidebar.php'; ?>

  <!-- Main Content -->
  <div class="flex-1 flex flex-col overflow-hidden">
    <!-- Top Navigation Bar -->
    <header class="bg-gray-800 border-b border-gray-700 shadow-md">
      <div class="flex items-center justify-between p-4">
        <!-- Mobile Menu Toggle -->
        <button id="mobile-menu-button" class="md:hidden text-gray-300 hover:text-white">
          <i class="fas fa-bars text-xl"></i>
        </button>

        <h1 class="text-xl font-bold text-white md:hidden">Profit Management</h1>

        <!-- User Profile -->
        <div class="flex items-center space-x-4">
          <div class="flex items-center">
            <div class="h-8 w-8 rounded-full bg-yellow-500 flex items-center justify-center text-black font-bold">
              <?php echo strtoupper(substr($admin_name, 0, 1)); ?>
            </div>
            <span class="ml-2 hidden sm:inline-block"><?php echo htmlspecialchars($admin_name); ?></span>
          </div>
        </div>
      </div>
    </header>

    <!-- Mobile Sidebar -->
    <div id="mobile-sidebar" class="fixed inset-0 bg-gray-900 bg-opacity-90 z-50 md:hidden transform -translate-x-full transition-transform duration-300">
      <div class="flex flex-col overflow-y-scroll h-full bg-gray-800 w-64 py-8 px-6">
        <div class="flex justify-between items-center mb-8">
          <div class="flex items-center">
            <i class="fas fa-gem text-yellow-500 text-xl mr-2"></i>
            <span class="text-xl font-bold text-yellow-500">AutoProftX</span>
          </div>
          <button id="close-sidebar" class="text-gray-300 hover:text-white">
            <i class="fas fa-times text-xl"></i>
          </button>
        </div>

        <nav class="space-y-2">
          <a href="index.php" class="nav-item flex items-center px-4 py-3 text-gray-300 hover:text-white rounded transition duration-200">
            <i class="fas fa-tachometer-alt w-6"></i>
            <span>Dashboard</span>
          </a>
          <a href="profit-management.php" class="nav-item active flex items-center px-4 py-3 text-gray-300 hover:text-white rounded transition duration-200">
            <i class="fas fa-percentage w-6"></i>
            <span>Profit Management</span>
          </a>
          <!-- Add other nav items similar to the sidebar -->
        </nav>
      </div>
    </div>

    <!-- Main Content Area -->
    <main class="flex-1 overflow-y-auto p-4 bg-gray-900">
      <!-- Page Title -->
      <div class="mb-6">
        <h2 class="text-2xl font-bold">Profit & Commission Management</h2>
        <p class="text-gray-400">Configure profit rates, commission structures, and rewards.</p>
      </div>

      <!-- Success/Error Messages -->
      <?php if (!empty($success_message)): ?>
        <div class="bg-green-800 text-green-100 p-4 rounded-lg mb-6">
          <div class="flex">
            <div class="flex-shrink-0">
              <i class="fas fa-check-circle"></i>
            </div>
            <div class="ml-3">
              <p class="text-sm"><?php echo $success_message; ?></p>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <?php if (!empty($error_message)): ?>
        <div class="bg-red-800 text-red-100 p-4 rounded-lg mb-6">
          <div class="flex">
            <div class="flex-shrink-0">
              <i class="fas fa-exclamation-circle"></i>
            </div>
            <div class="ml-3">
              <p class="text-sm"><?php echo $error_message; ?></p>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <!-- Stats Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
        <!-- Total Profit Paid -->
        <div class="bg-gray-800 rounded-lg p-6 border border-gray-700 shadow-lg transition duration-300 stat-card">
          <div class="flex justify-between">
            <div>
              <p class="text-gray-400 text-sm">Total Profit Paid</p>
              <h3 class="text-2xl font-bold mt-1"><?php echo number_format($total_profit_paid, 0); ?></h3>
            </div>
            <div class="h-12 w-12 rounded-lg gold-gradient flex items-center justify-center">
              <i class="fas fa-chart-line text-black"></i>
            </div>
          </div>
          <div class="mt-2">
            <p class="text-xs text-gray-400">All-time investment profits</p>
          </div>
        </div>

        <!-- Referral Commission Paid -->
        <div class="bg-gray-800 rounded-lg p-6 border border-gray-700 shadow-lg transition duration-300 stat-card">
          <div class="flex justify-between">
            <div>
              <p class="text-gray-400 text-sm">Referral Commissions</p>
              <h3 class="text-2xl font-bold mt-1"><?php echo number_format($referral_commission_paid, 0); ?></h3>
            </div>
            <div class="h-12 w-12 rounded-lg gold-gradient flex items-center justify-center">
              <i class="fas fa-user-plus text-black"></i>
            </div>
          </div>
          <div class="mt-2">
            <p class="text-xs text-gray-400">Total commissions paid</p>
          </div>
        </div>

        <!-- Leaderboard Bonuses Paid -->
        <div class="bg-gray-800 rounded-lg p-6 border border-gray-700 shadow-lg transition duration-300 stat-card">
          <div class="flex justify-between">
            <div>
              <p class="text-gray-400 text-sm">Leaderboard Bonuses</p>
              <h3 class="text-2xl font-bold mt-1"><?php
                                                  $total_profit = isset($total_profit_paid) && is_numeric($total_profit_paid) ? $total_profit_paid : 0;
                                                  $total_commission = isset($referral_commission_paid) && is_numeric($referral_commission_paid) ? $referral_commission_paid : 0;
                                                  $total_leaderboard = isset($leaderboard_paid) && is_numeric($leaderboard_paid) ? $leaderboard_paid : 0;
                                                  echo number_format($total_profit + $total_commission + $total_leaderboard, 0);
                                                  ?></h3>
            </div>
            <div class="h-12 w-12 rounded-lg gold-gradient flex items-center justify-center">
              <i class="fas fa-trophy text-black"></i>
            </div>
          </div>
          <div class="mt-2">
            <p class="text-xs text-gray-400">Total bonuses paid</p>
          </div>
        </div>

        <!-- Daily Rewards Paid -->
        <div class="bg-gray-800 rounded-lg p-6 border border-gray-700 shadow-lg transition duration-300 stat-card">
          <div class="flex justify-between">
            <div>
              <p class="text-gray-400 text-sm">Check-in Rewards</p>
              <h3 class="text-2xl font-bold mt-1"><?php echo number_format($total_profit_paid + $referral_commission_paid + $leaderboard_paid, 0); ?></h3>
            </div>
            <div class="h-12 w-12 rounded-lg gold-gradient flex items-center justify-center">
              <i class="fas fa-calendar-check text-black"></i>
            </div>
          </div>
          <div class="mt-2">
            <p class="text-xs text-gray-400">Total system payouts</p>
          </div>
        </div>
      </div>

      <!-- Tabs for different sections -->
      <div class="mb-6">
        <div class="border-b border-gray-700">
          <nav class="-mb-px flex space-x-8">
            <a href="#investment-plans" class="tab-link whitespace-nowrap py-4 px-1 border-b-2 border-yellow-500 font-medium text-sm text-yellow-500" onclick="showTab('investment-plans')">
              Investment Plans
            </a>
            <a href="#staking-plans" class="tab-link whitespace-nowrap py-4 px-1 border-b-2 border-transparent font-medium text-sm text-gray-400 hover:text-gray-300 hover:border-gray-400" onclick="showTab('staking-plans')">
              Staking Plans
            </a>
            <a href="#alpha-tokens" class="tab-link whitespace-nowrap py-4 px-1 border-b-2 border-transparent font-medium text-sm text-gray-400 hover:text-gray-300 hover:border-gray-400" onclick="showTab('alpha-tokens')">
              AlphaMiner Tokens
            </a>
            <a href="#referral-system" class="tab-link whitespace-nowrap py-4 px-1 border-b-2 border-transparent font-medium text-sm text-gray-400 hover:text-gray-300 hover:border-gray-400" onclick="showTab('referral-system')">
              Referral System
            </a>
            <a href="#leaderboard-rewards" class="tab-link whitespace-nowrap py-4 px-1 border-b-2 border-transparent font-medium text-sm text-gray-400 hover:text-gray-300 hover:border-gray-400" onclick="showTab('leaderboard-rewards')">
              Leaderboard Rewards
            </a>
            <a href="#daily-checkin" class="tab-link whitespace-nowrap py-4 px-1 border-b-2 border-transparent font-medium text-sm text-gray-400 hover:text-gray-300 hover:border-gray-400" onclick="showTab('daily-checkin')">
              Daily Check-in
            </a>
          </nav>
        </div>
      </div>

      <!-- Tab Content Sections -->
      <div class="tab-content" id="investment-plans-content">
        <div class="bg-gray-800 rounded-lg border border-gray-700 shadow-lg p-6 mb-6">
          <h3 class="text-lg font-bold mb-4">Investment Plans Profit Configuration</h3>
          <form method="post" action="">
            <div class="overflow-x-auto">
              <table class="w-full mb-4">
                <thead class="bg-gray-700">
                  <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Plan</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Min-Max Amount</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Daily Profit Rate (%)</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Referral Commission (%)</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-700">
                  <?php foreach ($investment_plans as $plan): ?>
                    <tr class="hover:bg-gray-700">
                      <td class="px-4 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium"><?php echo htmlspecialchars($plan['name']); ?></div>
                        <div class="text-xs text-gray-400"><?php echo htmlspecialchars($plan['description'] ?? ''); ?></div>
                        <input type="hidden" name="plan_id[]" value="<?php echo $plan['id']; ?>">
                      </td>
                      <td class="px-4 py-4 whitespace-nowrap">
                        <div class="text-sm"><?php echo number_format($plan['min_amount']); ?></div>
                        <div class="text-xs text-gray-400">
                          <?php if ($plan['max_amount']): ?>
                            to <?php echo number_format($plan['max_amount']); ?>
                          <?php else: ?>
                            and above
                          <?php endif; ?>
                        </div>
                      </td>
                      <td class="px-4 py-4 whitespace-nowrap">
                        <input type="number" step="0.01" min="0" max="100" class="w-full form-input px-3 py-2 rounded-md" name="daily_profit_rate[]" value="<?php echo $plan['daily_profit_rate']; ?>">
                      </td>
                      <td class="px-4 py-4 whitespace-nowrap">
                        <input type="number" step="0.01" min="0" max="100" class="w-full form-input px-3 py-2 rounded-md" name="referral_commission_rate[]" value="<?php echo $plan['referral_commission_rate']; ?>">
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>

            <p class="text-sm text-gray-400 mb-4">
              <i class="fas fa-info-circle mr-1"></i> Changes will affect future investments only. Existing investments will maintain their original rates.
            </p>

            <div class="flex justify-end">
              <button type="submit" name="update_investment_plans" class="px-4 py-2 bg-yellow-600 hover:bg-yellow-700 rounded-md text-white font-medium transition duration-200">
                <i class="fas fa-save mr-2"></i> Save Changes
              </button>
            </div>
          </form>
        </div>
      </div>

      <div class="tab-content hidden" id="staking-plans-content">
        <div class="bg-gray-800 rounded-lg border border-gray-700 shadow-lg p-6 mb-6">
          <h3 class="text-lg font-bold mb-4">Staking Plans Configuration</h3>
          <form method="post" action="">
            <div class="overflow-x-auto">
              <table class="w-full mb-4">
                <thead class="bg-gray-700">
                  <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Plan</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Duration</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Min-Max Amount</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">APY Rate (%)</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Early Withdrawal Fee (%)</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-700">
                  <?php foreach ($staking_plans as $plan): ?>
                    <tr class="hover:bg-gray-700">
                      <td class="px-4 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium"><?php echo htmlspecialchars($plan['name']); ?></div>
                        <div class="text-xs text-gray-400"><?php echo htmlspecialchars($plan['description'] ?? ''); ?></div>
                        <input type="hidden" name="staking_id[]" value="<?php echo $plan['id']; ?>">
                      </td>
                      <td class="px-4 py-4 whitespace-nowrap text-sm">
                        <?php echo $plan['duration_days']; ?> days
                      </td>
                      <td class="px-4 py-4 whitespace-nowrap">
                        <div class="text-sm"><?php echo number_format($plan['min_amount']); ?></div>
                        <div class="text-xs text-gray-400">
                          <?php if ($plan['max_amount']): ?>
                            to <?php echo number_format($plan['max_amount']); ?>
                          <?php else: ?>
                            and above
                          <?php endif; ?>
                        </div>
                      </td>
                      <td class="px-4 py-4 whitespace-nowrap">
                        <input type="number" step="0.01" min="0" max="100" class="w-full form-input px-3 py-2 rounded-md" name="apy_rate[]" value="<?php echo $plan['apy_rate']; ?>">
                      </td>
                      <td class="px-4 py-4 whitespace-nowrap">
                        <input type="number" step="0.01" min="0" max="100" class="w-full form-input px-3 py-2 rounded-md" name="early_withdrawal_fee[]" value="<?php echo $plan['early_withdrawal_fee']; ?>">
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>

            <p class="text-sm text-gray-400 mb-4">
              <i class="fas fa-info-circle mr-1"></i> Changes will apply to new stakes only. Existing stakes will maintain their original rates.
            </p>

            <div class="flex justify-end">
              <button type="submit" name="update_staking_plans" class="px-4 py-2 bg-yellow-600 hover:bg-yellow-700 rounded-md text-white font-medium transition duration-200">
                <i class="fas fa-save mr-2"></i> Save Changes
              </button>
            </div>
          </form>
        </div>
      </div>

      <div class="tab-content hidden" id="alpha-tokens-content">
        <div class="bg-gray-800 rounded-lg border border-gray-700 shadow-lg p-6 mb-6">
          <h3 class="text-lg font-bold mb-4">AlphaMiner Token Configuration</h3>
          <form method="post" action="">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Token Profit Percentage</label>
                <div class="relative rounded-md shadow-sm">
                  <input type="number" step="0.01" min="0" max="100" name="alpha_profit_rate" value="<?php echo $alpha_profit_rate; ?>" class="form-input block w-full sm:text-sm rounded-md py-3 px-4">
                  <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                    <span class="text-gray-400 sm:text-sm">%</span>
                  </div>
                </div>
                <p class="mt-2 text-sm text-gray-400">Profit percentage earned per token when sold.</p>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Minimum Holding Period</label>
                <div class="relative rounded-md shadow-sm">
                  <input type="number" step="1" min="1" name="alpha_holding_period" value="<?php echo $alpha_holding_period; ?>" class="form-input block w-full sm:text-sm rounded-md py-3 px-4">
                  <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                    <span class="text-gray-400 sm:text-sm">hours</span>
                  </div>
                </div>
                <p class="mt-2 text-sm text-gray-400">Minimum time before tokens can be sold to earn profit.</p>
              </div>
            </div>

            <div class="flex justify-end">
              <button type="submit" name="update_alpha_settings" class="px-4 py-2 bg-yellow-600 hover:bg-yellow-700 rounded-md text-white font-medium transition duration-200">
                <i class="fas fa-save mr-2"></i> Save Changes
              </button>
            </div>
          </form>
        </div>
      </div>

      <div class="tab-content hidden" id="referral-system-content">
        <div class="bg-gray-800 rounded-lg border border-gray-700 shadow-lg p-6 mb-6">
          <h3 class="text-lg font-bold mb-4">Referral & Commission Structure</h3>
          <form method="post" action="">
            <div class="mb-6">
              <h4 class="text-md font-medium text-yellow-500 mb-3">Direct Referral Bonus</h4>
              <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                  <label class="block text-sm font-medium text-gray-300 mb-2">Registration Bonus Amount</label>
                  <div class="relative rounded-md shadow-sm">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                      <span class="text-gray-400 sm:text-sm"></span>
                    </div>
                    <input type="number" step="1" min="0" name="direct_referral_bonus" value="<?php echo $direct_referral_bonus; ?>" class="form-input block w-full pl-10 sm:text-sm rounded-md py-3">
                  </div>
                  <p class="mt-2 text-sm text-gray-400">Bonus paid to referrer when new user registers.</p>
                </div>
              </div>
            </div>

            <div class="mb-6">
              <h4 class="text-md font-medium text-yellow-500 mb-3">Multi-Level Commission Structure</h4>
              <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                  <label class="block text-sm font-medium text-gray-300 mb-2">Level 1 (Direct) Commission</label>
                  <div class="relative rounded-md shadow-sm">
                    <input type="number" step="0.01" min="0" max="100" name="level1_commission" value="<?php echo $level1_commission; ?>" class="form-input block w-full sm:text-sm rounded-md py-3 px-4">
                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                      <span class="text-gray-400 sm:text-sm">%</span>
                    </div>
                  </div>
                  <p class="mt-2 text-sm text-gray-400">Commission from direct referral investments.</p>
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-300 mb-2">Level 2 Commission</label>
                  <div class="relative rounded-md shadow-sm">
                    <input type="number" step="0.01" min="0" max="100" name="level2_commission" value="<?php echo $level2_commission; ?>" class="form-input block w-full sm:text-sm rounded-md py-3 px-4">
                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                      <span class="text-gray-400 sm:text-sm">%</span>
                    </div>
                  </div>
                  <p class="mt-2 text-sm text-gray-400">Commission from level 2 referral investments.</p>
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-300 mb-2">Level 3 Commission</label>
                  <div class="relative rounded-md shadow-sm">
                    <input type="number" step="0.01" min="0" max="100" name="level3_commission" value="<?php echo $level3_commission; ?>" class="form-input block w-full sm:text-sm rounded-md py-3 px-4">
                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                      <span class="text-gray-400 sm:text-sm">%</span>
                    </div>
                  </div>
                  <p class="mt-2 text-sm text-gray-400">Commission from level 3 referral investments.</p>
                </div>
              </div>
            </div>

            <div class="flex justify-end">
              <button type="submit" name="update_referral_structure" class="px-4 py-2 bg-yellow-600 hover:bg-yellow-700 rounded-md text-white font-medium transition duration-200">
                <i class="fas fa-save mr-2"></i> Save Changes
              </button>
            </div>
          </form>
        </div>
      </div>

      <div class="tab-content hidden" id="leaderboard-rewards-content">
        <div class="bg-gray-800 rounded-lg border border-gray-700 shadow-lg p-6 mb-6">
          <h3 class="text-lg font-bold mb-4">Monthly Leaderboard Rewards</h3>
          <form method="post" action="">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">1st Place Reward</label>
                <div class="relative rounded-md shadow-sm">
                  <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <span class="text-gray-400 sm:text-sm"></span>
                  </div>
                  <input type="number" step="1" min="0" name="first_place" value="<?php echo $first_place; ?>" class="form-input block w-full pl-10 sm:text-sm rounded-md py-3">
                </div>
                <p class="mt-2 text-sm text-gray-400">Bonus for the top depositor of the month.</p>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">2nd Place Reward</label>
                <div class="relative rounded-md shadow-sm">
                  <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <span class="text-gray-400 sm:text-sm"></span>
                  </div>
                  <input type="number" step="1" min="0" name="second_place" value="<?php echo $second_place; ?>" class="form-input block w-full pl-10 sm:text-sm rounded-md py-3">
                </div>
                <p class="mt-2 text-sm text-gray-400">Bonus for the second top depositor.</p>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">3rd Place Reward</label>
                <div class="relative rounded-md shadow-sm">
                  <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <span class="text-gray-400 sm:text-sm"></span>
                  </div>
                  <input type="number" step="1" min="0" name="third_place" value="<?php echo $third_place; ?>" class="form-input block w-full pl-10 sm:text-sm rounded-md py-3">
                </div>
                <p class="mt-2 text-sm text-gray-400">Bonus for the third top depositor.</p>
              </div>
            </div>

            <p class="text-sm text-gray-400 mb-4">
              <i class="fas fa-info-circle mr-1"></i> Changes will apply to future monthly rewards distribution. The system automatically distributes rewards at the end of each month.
            </p>

            <div class="flex justify-end">
              <button type="submit" name="update_leaderboard_bonus" class="px-4 py-2 bg-yellow-600 hover:bg-yellow-700 rounded-md text-white font-medium transition duration-200">
                <i class="fas fa-save mr-2"></i> Save Changes
              </button>
            </div>
          </form>
        </div>
      </div>

      <div class="tab-content hidden" id="daily-checkin-content">
        <div class="bg-gray-800 rounded-lg border border-gray-700 shadow-lg p-6 mb-6">
          <h3 class="text-lg font-bold mb-4">Daily Check-in Rewards</h3>
          <form method="post" action="">
            <div class="overflow-x-auto">
              <table class="w-full mb-4">
                <thead class="bg-gray-700">
                  <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Streak Day</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Reward Amount ()</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Status</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-700">
                  <?php foreach ($checkin_rewards as $reward): ?>
                    <tr class="hover:bg-gray-700">
                      <td class="px-4 py-4 whitespace-nowrap text-sm">
                        Day <?php echo $reward['streak_day']; ?>
                        <input type="hidden" name="checkin_id[]" value="<?php echo $reward['id']; ?>">
                      </td>
                      <td class="px-4 py-4 whitespace-nowrap">
                        <div class="relative rounded-md shadow-sm">
                          <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <span class="text-gray-400 sm:text-sm"></span>
                          </div>
                          <input type="number" step="0.01" min="0" name="reward_amount[]" value="<?php echo $reward['reward_amount']; ?>" class="form-input block w-full pl-10 sm:text-sm rounded-md py-2">
                        </div>
                      </td>
                      <td class="px-4 py-4 whitespace-nowrap text-sm">
                        <?php if ($reward['is_active']): ?>
                          <span class="px-2 py-1 text-xs rounded-full bg-green-900 text-green-400">Active</span>
                        <?php else: ?>
                          <span class="px-2 py-1 text-xs rounded-full bg-red-900 text-red-400">Inactive</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>

            <p class="text-sm text-gray-400 mb-4">
              <i class="fas fa-info-circle mr-1"></i> Changes to reward amounts will take effect immediately for future check-ins.
            </p>

            <div class="flex justify-end">
              <button type="submit" name="update_checkin_rewards" class="px-4 py-2 bg-yellow-600 hover:bg-yellow-700 rounded-md text-white font-medium transition duration-200">
                <i class="fas fa-save mr-2"></i> Save Changes
              </button>
            </div>
          </form>
        </div>
      </div>
    </main>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Mobile sidebar toggle
      const mobileSidebar = document.getElementById('mobile-sidebar');
      const mobileMenuButton = document.getElementById('mobile-menu-button');
      const closeSidebarButton = document.getElementById('close-sidebar');

      if (mobileMenuButton) {
        mobileMenuButton.addEventListener('click', function() {
          mobileSidebar.classList.remove('-translate-x-full');
        });
      }

      if (closeSidebarButton) {
        closeSidebarButton.addEventListener('click', function() {
          mobileSidebar.classList.add('-translate-x-full');
        });
      }

      // Tab functionality
      window.showTab = function(tabId) {
        // Hide all tab contents
        document.querySelectorAll('.tab-content').forEach(content => {
          content.classList.add('hidden');
        });

        // Remove active status from all tab links
        document.querySelectorAll('.tab-link').forEach(link => {
          link.classList.remove('border-yellow-500', 'text-yellow-500');
          link.classList.add('border-transparent', 'text-gray-400');
        });

        // Show selected tab content
        document.getElementById(tabId + '-content').classList.remove('hidden');

        // Set active status on selected tab link
        document.querySelector(`a[href="#${tabId}"]`).classList.remove('border-transparent', 'text-gray-400');
        document.querySelector(`a[href="#${tabId}"]`).classList.add('border-yellow-500', 'text-yellow-500');
      };

      // Check if URL has a hash and activate that tab
      if (window.location.hash) {
        const tabId = window.location.hash.substring(1);
        if (document.getElementById(tabId + '-content')) {
          showTab(tabId);
        }
      }

      // Listen for hash changes
      window.addEventListener('hashchange', function() {
        if (window.location.hash) {
          const tabId = window.location.hash.substring(1);
          if (document.getElementById(tabId + '-content')) {
            showTab(tabId);
          }
        }
      });
    });
  </script>
</body>

</html>