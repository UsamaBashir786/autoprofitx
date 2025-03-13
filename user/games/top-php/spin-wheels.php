<?php
// Start session
session_start();
include '../../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
  header("Location: ../../login.php");
  exit;
}

// Get the user ID from session
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['full_name'] ?? 'User';

// Get user's wallet balance
$balance_query = "SELECT balance FROM wallets WHERE user_id = ?";
$stmt = $conn->prepare($balance_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$balance = 0;
if ($result->num_rows > 0) {
  $wallet = $result->fetch_assoc();
  $balance = $wallet['balance'];
}

// Initialize variables
$bet_amount = 0;
$success_message = '';
$error_message = '';
$result_message = '';
$winnings = 0;
$multiplier = 0;
$result_type = '';
$segment_value = '';

// Process spin if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['spin'])) {
  $bet_amount = floatval($_POST['bet_amount']);

  // Validate bet amount
  if ($bet_amount < 1) {
    $error_message = "Minimum bet amount is $1.00";
  } elseif ($bet_amount > $balance) {
    $error_message = "Insufficient balance. Your available balance is $" . number_format($balance, 2);
  } elseif ($bet_amount > 100) {
    $error_message = "Maximum bet amount is $100.00";
  } else {
    // MODIFIED WHEEL SEGMENTS - Higher probability for losses, limited small wins
    $wheel_segments = [
      ['value' => 0, 'label' => 'LOSE', 'color' => '#e53e3e', 'probability' => 65], // 65% chance to lose
      ['value' => 0.5, 'label' => '0.5x', 'color' => '#d69e2e', 'probability' => 20], // 20% chance to get half back
      ['value' => 1, 'label' => '1x', 'color' => '#38a169', 'probability' => 10], // 10% chance to break even
      ['value' => 1.2, 'label' => '1.5x', 'color' => '#3182ce', 'probability' => 5], // 5% chance for small profit (display as 1.5x)
      ['value' => 0, 'label' => 'LOSE', 'color' => '#e53e3e', 'probability' => 0], // Disabled
      ['value' => 0, 'label' => 'LOSE', 'color' => '#e53e3e', 'probability' => 0], // Disabled
      ['value' => 0, 'label' => 'LOSE', 'color' => '#e53e3e', 'probability' => 0], // Disabled
      ['value' => 0, 'label' => 'LOSE', 'color' => '#e53e3e', 'probability' => 0], // Disabled
      ['value' => 0, 'label' => 'LOSE', 'color' => '#e53e3e', 'probability' => 0], // Disabled
      ['value' => 0, 'label' => 'LOSE', 'color' => '#e53e3e', 'probability' => 0], // Disabled
      ['value' => 0, 'label' => 'LOSE', 'color' => '#e53e3e', 'probability' => 0], // Disabled
    ];

    // Calculate cumulative probabilities
    $cumulative_prob = [];
    $cum_sum = 0;
    $active_segments = 0;

    // Only include segments with probability > 0
    $active_wheel_segments = [];
    foreach ($wheel_segments as $segment) {
      if ($segment['probability'] > 0) {
        $cum_sum += $segment['probability'];
        $cumulative_prob[] = $cum_sum;
        $active_wheel_segments[] = $segment;
        $active_segments++;
      }
    }

    // Random number between 0-100
    $random_number = mt_rand(0, 10000) / 100;
    $selected_index = 0;

    // Find the segment based on random number using our rigged probabilities
    for ($i = 0; $i < count($cumulative_prob); $i++) {
      if ($random_number <= $cumulative_prob[$i]) {
        $selected_index = $i;
        break;
      }
    }

    // Get actual segment from our modified probability table
    $selected_segment = $active_wheel_segments[$selected_index];
    $actual_multiplier = $selected_segment['value'];
    $actual_segment_value = $selected_segment['label'];

    // Visual segments (these are just for display - to match what users see)
    $visual_wheel_segments = [
      ['value' => 0, 'label' => 'LOSE', 'color' => '#e53e3e'],
      ['value' => 0.5, 'label' => '0.5x', 'color' => '#d69e2e'],
      ['value' => 1, 'label' => '1x', 'color' => '#38a169'],
      ['value' => 1.5, 'label' => '1.5x', 'color' => '#3182ce'],
      ['value' => 2, 'label' => '2x', 'color' => '#805ad5'],
      ['value' => 3, 'label' => '3x', 'color' => '#dd6b20'],
      ['value' => 5, 'label' => '5x', 'color' => '#0d9488'],
      ['value' => 10, 'label' => '10x', 'color' => '#6366f1'],
      ['value' => 20, 'label' => '20x', 'color' => '#7e22ce'],
      ['value' => 50, 'label' => '50x', 'color' => '#be123c'],
      ['value' => 100, 'label' => '100x', 'color' => '#fbbf24']
    ];

    // For visualization, find a corresponding visual segment
    $visual_segment = null;
    $visual_index = 0;

    // Match the actual result to a visual segment
    if ($actual_multiplier === 0) {
      // For lose, use the visual LOSE segment
      $visual_segment = $visual_wheel_segments[0];
      $visual_index = 0;
    } elseif ($actual_multiplier === 0.5) {
      // For 0.5x, use the visual 0.5x segment
      $visual_segment = $visual_wheel_segments[1];
      $visual_index = 1;
    } elseif ($actual_multiplier === 1) {
      // For 1x, use the visual 1x segment
      $visual_segment = $visual_wheel_segments[2];
      $visual_index = 2;
    } elseif ($actual_multiplier === 1.2) {
      // For the small win (1.2x), show as 1.5x
      $visual_segment = $visual_wheel_segments[3];
      $visual_index = 3;
    }

    // Use the actual multiplier for calculations but show the visual multiplier
    $multiplier = $actual_multiplier;
    $segment_value = $visual_segment['label'];
    $selected_index = $visual_index; // This is for the wheel animation

    // Calculate actual winnings using our actual multiplier
    $winnings = $bet_amount * $multiplier;

    // Determine result type
    if ($multiplier === 0) {
      $result_type = 'lose';
      $result_message = "You landed on LOSE! Better luck next time.";
    } else if ($multiplier < 1) {
      $result_type = 'partial';
      $result_message = "You landed on $segment_value! You get back $" . number_format($winnings, 2);
    } else {
      $result_type = 'win';
      $result_message = "Congratulations! You landed on $segment_value and won $" . number_format($winnings, 2) . "!";
    }

    // Begin transaction
    $conn->begin_transaction();

    try {
      // Update wallet balance (deduct bet amount)
      $update_balance = "UPDATE wallets SET balance = balance - ? WHERE user_id = ?";
      $stmt = $conn->prepare($update_balance);
      $stmt->bind_param("di", $bet_amount, $user_id);
      $stmt->execute();

      // Add winnings if any
      if ($winnings > 0) {
        $add_winnings = "UPDATE wallets SET balance = balance + ? WHERE user_id = ?";
        $stmt = $conn->prepare($add_winnings);
        $stmt->bind_param("di", $winnings, $user_id);
        $stmt->execute();
      }

      // Create or check if game_history table exists
      $check_table = "SHOW TABLES LIKE 'game_history'";
      $table_result = $conn->query($check_table);

      if ($table_result->num_rows == 0) {
        // Create table if it doesn't exist
        $create_table = "CREATE TABLE game_history (
          id INT(11) NOT NULL AUTO_INCREMENT,
          user_id INT(11) NOT NULL,
          game_name VARCHAR(255) NOT NULL,
          bet_amount DECIMAL(15,2) NOT NULL,
          result ENUM('win','lose') NOT NULL,
          winnings DECIMAL(15,2) NOT NULL,
          details TEXT NULL,
          played_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (id)
        )";

        $conn->query($create_table);
      }

      // Record game history
      $game_result = ($multiplier > 0) ? 'win' : 'lose';
      $details = json_encode([
        'multiplier' => $multiplier,
        'segment' => $segment_value,
        'selected_index' => $selected_index
      ]);

      $insert_history = "INSERT INTO game_history (user_id, game_name, bet_amount, result, winnings, details, played_at) 
                         VALUES (?, 'Spin & Win', ?, ?, ?, ?, NOW())";
      $stmt = $conn->prepare($insert_history);
      $stmt->bind_param("idsds", $user_id, $bet_amount, $game_result, $winnings, $details);
      $stmt->execute();

      // Commit transaction
      $conn->commit();

      // Get updated balance
      $balance_query = "SELECT balance FROM wallets WHERE user_id = ?";
      $stmt = $conn->prepare($balance_query);
      $stmt->bind_param("i", $user_id);
      $stmt->execute();
      $result = $stmt->get_result();

      if ($result->num_rows > 0) {
        $wallet = $result->fetch_assoc();
        $balance = $wallet['balance'];
      }

      $success_message = "";
    } catch (Exception $e) {
      // Rollback on error
      $conn->rollback();
      $error_message = "An error occurred: " . $e->getMessage();
    }
  }
}

// Check if game_history table exists before querying
$check_table = "SHOW TABLES LIKE 'game_history'";
$table_result = $conn->query($check_table);
$table_exists = ($table_result->num_rows > 0);

// Get recent game history only if the table exists
$recent_games = [];
if ($table_exists) {
  $history_query = "SELECT * FROM game_history WHERE user_id = ? AND game_name = 'Spin & Win' ORDER BY played_at DESC LIMIT 5";
  $stmt = $conn->prepare($history_query);
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $history_result = $stmt->get_result();

  while ($row = $history_result->fetch_assoc()) {
    $recent_games[] = $row;
  }
}
