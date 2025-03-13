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
$dice_values = [];
$selected_bet = '';
$result_type = '';

// Define bet types and their payouts
$bet_types = [
  'high' => ['name' => 'High (8-12)', 'description' => 'Win if total is 8 or higher', 'payout' => 1.8],
  'low' => ['name' => 'Low (2-6)', 'description' => 'Win if total is 6 or lower', 'payout' => 1.8],
  'seven' => ['name' => 'Seven', 'description' => 'Win if total is exactly 7', 'payout' => 5.0],
  'even' => ['name' => 'Even', 'description' => 'Win if total is even (2,4,6,8,10,12)', 'payout' => 1.9],
  'odd' => ['name' => 'Odd', 'description' => 'Win if total is odd (3,5,7,9,11)', 'payout' => 1.9],
  'double' => ['name' => 'Doubles', 'description' => 'Win if both dice show the same number', 'payout' => 5.5],
];

// Process game action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['roll'])) {
  $bet_amount = floatval($_POST['bet_amount']);
  $selected_bet = trim($_POST['bet_type']);

  // Validate bet amount
  if ($bet_amount < 1) {
    $error_message = "Minimum bet amount is $1.00";
  } elseif ($bet_amount > $balance) {
    $error_message = "Insufficient balance. Your available balance is $" . number_format($balance, 2);
  } elseif ($bet_amount > 100) {
    $error_message = "Maximum bet amount is $100.00";
  } elseif (!array_key_exists($selected_bet, $bet_types)) {
    $error_message = "Please select a valid bet type.";
  } else {
    // Roll the dice (with house edge)
    rollDice($bet_amount, $selected_bet);
  }
}

// Functions for dice game
function rollDice($bet_amount, $selected_bet)
{
  global $conn, $user_id, $bet_types, $dice_values, $result_message, $result_type, $winnings, $balance;

  // Begin transaction
  $conn->begin_transaction();

  try {
    // Update wallet balance (deduct bet amount)
    $update_balance = "UPDATE wallets SET balance = balance - ? WHERE user_id = ?";
    $stmt = $conn->prepare($update_balance);
    $stmt->bind_param("di", $bet_amount, $user_id);
    $stmt->execute();

    // Roll the dice (1-6 for each die)
    // Create biased dice roll based on bet type to ensure house edge
    $dice_values = getBiasedDiceRoll($selected_bet);

    // Total of both dice
    $dice_total = $dice_values[0] + $dice_values[1];

    // Determine if player wins based on bet type
    $is_winner = false;

    switch ($selected_bet) {
      case 'high':
        $is_winner = ($dice_total >= 8);
        break;
      case 'low':
        $is_winner = ($dice_total <= 6);
        break;
      case 'seven':
        $is_winner = ($dice_total == 7);
        break;
      case 'even':
        $is_winner = ($dice_total % 2 == 0);
        break;
      case 'odd':
        $is_winner = ($dice_total % 2 == 1);
        break;
      case 'double':
        $is_winner = ($dice_values[0] == $dice_values[1]);
        break;
    }

    // Calculate winnings
    if ($is_winner) {
      $payout = $bet_types[$selected_bet]['payout'];
      $winnings = $bet_amount * $payout;
      $result_type = 'win';
      $result_message = "Congratulations! You rolled {$dice_values[0]} and {$dice_values[1]} (total: $dice_total). You win $" . number_format($winnings - $bet_amount, 2) . "!";

      // Add winnings to wallet
      $add_winnings = "UPDATE wallets SET balance = balance + ? WHERE user_id = ?";
      $stmt = $conn->prepare($add_winnings);
      $stmt->bind_param("di", $winnings, $user_id);
      $stmt->execute();
    } else {
      $winnings = 0;
      $result_type = 'lose';
      $result_message = "Sorry! You rolled {$dice_values[0]} and {$dice_values[1]} (total: $dice_total). You lose $" . number_format($bet_amount, 2) . ".";
    }

    // Check if game_history table exists
    $check_table = "SHOW TABLES LIKE 'game_history'";
    $table_result = $conn->query($check_table);

    if ($table_result->num_rows == 0) {
      // Create table if it doesn't exist
      $create_table = "CREATE TABLE game_history (
        id INT(11) NOT NULL AUTO_INCREMENT,
        user_id INT(11) NOT NULL,
        game_name VARCHAR(255) NOT NULL,
        bet_amount DECIMAL(15,2) NOT NULL,
        result ENUM('win','lose','push') NOT NULL,
        winnings DECIMAL(15,2) NOT NULL,
        details TEXT NULL,
        played_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
      )";

      $conn->query($create_table);
    }

    // Record game history
    $game_result = $is_winner ? 'win' : 'lose';
    $details = json_encode([
      'dice' => $dice_values,
      'bet_type' => $selected_bet,
      'payout' => $bet_types[$selected_bet]['payout']
    ]);

    $insert_history = "INSERT INTO game_history (user_id, game_name, bet_amount, result, winnings, details, played_at) 
                       VALUES (?, 'Dice Roll', ?, ?, ?, ?, NOW())";
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
  } catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    global $error_message;
    $error_message = "An error occurred: " . $e->getMessage();
  }
}

// Function to create biased dice rolls
function getBiasedDiceRoll($bet_type)
{
  // Set bias probability - higher means more house edge
  $bias_probability = 70; // 70% chance for unfavorable outcome

  // Determine if this roll should be biased against the player
  $use_bias = (mt_rand(1, 100) <= $bias_probability);

  if (!$use_bias) {
    // Unbiased roll (30% of the time)
    return [mt_rand(1, 6), mt_rand(1, 6)];
  }

  // Create biased roll based on bet type
  switch ($bet_type) {
    case 'high': // Player bet on high (8-12)
      // Generate combinations that result in 2-7
      $possible_rolls = [];
      for ($i = 1; $i <= 6; $i++) {
        for ($j = 1; $j <= 6; $j++) {
          if ($i + $j < 8) {
            $possible_rolls[] = [$i, $j];
          }
        }
      }
      return $possible_rolls[array_rand($possible_rolls)];

    case 'low': // Player bet on low (2-6)
      // Generate combinations that result in 7-12
      $possible_rolls = [];
      for ($i = 1; $i <= 6; $i++) {
        for ($j = 1; $j <= 6; $j++) {
          if ($i + $j > 6) {
            $possible_rolls[] = [$i, $j];
          }
        }
      }
      return $possible_rolls[array_rand($possible_rolls)];

    case 'seven': // Player bet on seven
      // Generate combinations that don't result in 7
      $possible_rolls = [];
      for ($i = 1; $i <= 6; $i++) {
        for ($j = 1; $j <= 6; $j++) {
          if ($i + $j != 7) {
            $possible_rolls[] = [$i, $j];
          }
        }
      }
      return $possible_rolls[array_rand($possible_rolls)];

    case 'even': // Player bet on even
      // Generate combinations that result in odd numbers
      $possible_rolls = [];
      for ($i = 1; $i <= 6; $i++) {
        for ($j = 1; $j <= 6; $j++) {
          if (($i + $j) % 2 == 1) {
            $possible_rolls[] = [$i, $j];
          }
        }
      }
      return $possible_rolls[array_rand($possible_rolls)];

    case 'odd': // Player bet on odd
      // Generate combinations that result in even numbers
      $possible_rolls = [];
      for ($i = 1; $i <= 6; $i++) {
        for ($j = 1; $j <= 6; $j++) {
          if (($i + $j) % 2 == 0) {
            $possible_rolls[] = [$i, $j];
          }
        }
      }
      return $possible_rolls[array_rand($possible_rolls)];

    case 'double': // Player bet on doubles
      // Generate combinations that aren't doubles
      $possible_rolls = [];
      for ($i = 1; $i <= 6; $i++) {
        for ($j = 1; $j <= 6; $j++) {
          if ($i != $j) {
            $possible_rolls[] = [$i, $j];
          }
        }
      }
      return $possible_rolls[array_rand($possible_rolls)];

    default:
      // Default to random roll
      return [mt_rand(1, 6), mt_rand(1, 6)];
  }
}

// Check if game_history table exists before querying
$check_table = "SHOW TABLES LIKE 'game_history'";
$table_result = $conn->query($check_table);
$table_exists = ($table_result->num_rows > 0);

// Get recent game history only if the table exists
$recent_games = [];
if ($table_exists) {
  $history_query = "SELECT * FROM game_history WHERE user_id = ? AND game_name = 'Dice Roll' ORDER BY played_at DESC LIMIT 5";
  $stmt = $conn->prepare($history_query);
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $history_result = $stmt->get_result();

  while ($row = $history_result->fetch_assoc()) {
    $recent_games[] = $row;
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <?php include '../includes/head.php'; ?>
  <title>Dice Roll - AutoProftX</title>
  <script src="https://unpkg.com/@tailwindcss/browser@4"></script>

  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <style>
    /* Game Styles */
    .dice-container {
      display: flex;
      justify-content: center;
      gap: 1.5rem;
      margin: 1.5rem 0;
    }

    .dice {
      width: 70px;
      height: 70px;
      background-color: #fff;
      border-radius: 10px;
      display: flex;
      justify-content: center;
      align-items: center;
      font-size: 2rem;
      color: #1a202c;
      font-weight: bold;
      box-shadow: 0 6px 12px rgba(0, 0, 0, 0.3);
      position: relative;
      transition: all 0.3s ease;
    }

    .dice.rolling {
      animation: diceRoll 0.5s infinite alternate;
    }

    @keyframes diceRoll {
      0% {
        transform: rotateX(0deg) rotateY(0deg) translateY(0);
      }

      50% {
        transform: rotateX(180deg) rotateY(90deg) translateY(-20px);
      }

      100% {
        transform: rotateX(360deg) rotateY(180deg) translateY(0);
      }
    }

    /* Dot positions remain the same */
    .dice-dot {
      width: 12px;
      height: 12px;
      background-color: #1a202c;
      border-radius: 50%;
      position: absolute;
    }

    .dice-1 .dot-center {
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
    }

    .dice-2 .dot-top-right {
      top: 20%;
      right: 20%;
    }

    .dice-2 .dot-bottom-left {
      bottom: 20%;
      left: 20%;
    }

    .dice-3 .dot-top-right {
      top: 20%;
      right: 20%;
    }

    .dice-3 .dot-center {
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
    }

    .dice-3 .dot-bottom-left {
      bottom: 20%;
      left: 20%;
    }

    .dice-4 .dot-top-left {
      top: 20%;
      left: 20%;
    }

    .dice-4 .dot-top-right {
      top: 20%;
      right: 20%;
    }

    .dice-4 .dot-bottom-left {
      bottom: 20%;
      left: 20%;
    }

    .dice-4 .dot-bottom-right {
      bottom: 20%;
      right: 20%;
    }

    .dice-5 .dot-top-left {
      top: 20%;
      left: 20%;
    }

    .dice-5 .dot-top-right {
      top: 20%;
      right: 20%;
    }

    .dice-5 .dot-center {
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
    }

    .dice-5 .dot-bottom-left {
      bottom: 20%;
      left: 20%;
    }

    .dice-5 .dot-bottom-right {
      bottom: 20%;
      right: 20%;
    }

    .dice-6 .dot-top-left {
      top: 20%;
      left: 20%;
    }

    .dice-6 .dot-top-right {
      top: 20%;
      right: 20%;
    }

    .dice-6 .dot-middle-left {
      top: 50%;
      left: 20%;
      transform: translateY(-50%);
    }

    .dice-6 .dot-middle-right {
      top: 50%;
      right: 20%;
      transform: translateY(-50%);
    }

    .dice-6 .dot-bottom-left {
      bottom: 20%;
      left: 20%;
    }

    .dice-6 .dot-bottom-right {
      bottom: 20%;
      right: 20%;
    }

    .dice-total {
      font-size: 1.25rem;
      font-weight: bold;
      margin-top: 1rem;
      text-align: center;
    }

    /* Fixed betting options - ensure they're clickable */
    .bet-options {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 0.75rem;
      margin: 1rem 0;
    }

    .bet-option {
      background-color: #2d3748;
      border: 2px solid transparent;
      border-radius: 0.5rem;
      padding: 0.75rem;
      cursor: pointer;
      position: relative;
      min-height: 70px;
      display: flex;
      flex-direction: column;
      justify-content: center;
      /* Remove transitions for better mobile performance */
    }

    .bet-option:hover {
      background-color: #3c4a5f;
    }

    .bet-option.selected {
      border-color: #4c1d95;
      background-color: #3c4a5f;
    }

    .bet-option-name {
      font-weight: bold;
      color: #e2e8f0;
      margin-bottom: 0.25rem;
      font-size: 0.95rem;
    }

    .bet-option-description {
      font-size: 0.75rem;
      color: #a0aec0;
    }

    .bet-option-payout {
      position: absolute;
      top: 0.25rem;
      right: 0.25rem;
      background-color: #4c1d95;
      color: white;
      font-size: 0.7rem;
      font-weight: bold;
      padding: 0.2rem 0.4rem;
      border-radius: 9999px;
    }

    /* Fixed preset bet buttons */
    .preset-bet {
      padding: 0.75rem 0;
      font-size: 0.95rem;
      font-weight: bold;
      width: 45px;
      height: 40px;
      cursor: pointer;
    }

    /* Fixed roll button */
    #roll-btn {
      height: 56px;
      font-size: 1.1rem;
      cursor: pointer;
    }

    .result-message {
      font-size: 1.3rem;
      font-weight: bold;
      text-align: center;
      margin: 1.25rem 0;
      padding: 0.75rem;
      border-radius: 0.5rem;
    }

    .win-message {
      background-color: rgba(72, 187, 120, 0.2);
      color: #48bb78;
    }

    .lose-message {
      background-color: rgba(245, 101, 101, 0.2);
      color: #f56565;
    }

    /* Properly styled back button */
    .go-back-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background-color: #4a5568;
      color: white;
      padding: 0.5rem 1rem;
      border-radius: 0.5rem;
      font-weight: 600;
      margin-bottom: 1rem;
      border: none;
      cursor: pointer;
      text-decoration: none;
    }

    .go-back-btn i {
      margin-right: 0.5rem;
    }

    /* Responsive table */
    .history-table {
      font-size: 0.75rem;
      width: 100%;
    }

    .history-table th,
    .history-table td {
      padding: 0.5rem 0.25rem;
      white-space: nowrap;
    }

    /* Mobile-specific adjustments */
    @media (max-width: 480px) {
      .dice {
        width: 60px;
        height: 60px;
      }

      .dice-dot {
        width: 10px;
        height: 10px;
      }

      /* Show dice first on mobile */
      .game-section {
        order: 1;
      }

      .controls-section {
        order: 2;
      }

      .history-section {
        order: 3;
      }

      /* Adjust header */
      .header-section h1 {
        font-size: 1.5rem;
      }

      /* Use a more compact layout */
      .bet-options {
        gap: 0.5rem;
      }

      .bet-option {
        min-height: 65px;
        padding: 0.5rem;
      }

      /* Fix touch targets */
      .preset-bet {
        min-width: 40px;
        min-height: 40px;
      }
    }
  </style>
</head>

<body class="bg-gray-900 text-white font-sans min-h-screen flex flex-col">
  <?php include '../includes/navbar.php'; ?>
  <?php include '../includes/pre-loader.php'; ?>

  <!-- Main Content -->
  <main style="display: none;" id="main-content" class="flex-grow py-4">
    <div class="container mx-auto px-4">

      <!-- Back Button -->
      <a href="../games.php" class="go-back-btn">
        <i class="fas fa-arrow-left"></i> Back to Games
      </a>

      <!-- Header Section -->
      <div class="mb-4 header-section">
        <h1 class="text-2xl font-bold">Dice Roll</h1>
        <p class="text-gray-400 mt-1">Place your bet and roll the dice!</p>
      </div>

      <!-- Notification Messages -->
      <?php if (!empty($success_message)): ?>
        <div class="mb-4 bg-green-900 bg-opacity-50 text-green-200 p-3 rounded-md flex items-start">
          <i class="fas fa-check-circle mt-1 mr-3"></i>
          <span><?php echo $success_message; ?></span>
        </div>
      <?php endif; ?>

      <?php if (!empty($error_message)): ?>
        <div class="mb-4 bg-red-900 bg-opacity-50 text-red-200 p-3 rounded-md flex items-start">
          <i class="fas fa-exclamation-circle mt-1 mr-3"></i>
          <span><?php echo $error_message; ?></span>
        </div>
      <?php endif; ?>

      <!-- Mobile-first layout with proper order -->
      <div class="flex flex-col space-y-4">
        <!-- Dice Display - Shown first on mobile -->
        <div class="bg-gray-800 rounded-xl border border-gray-700 p-4 game-section">
          <?php if (!empty($dice_values)): ?>
            <div class="dice-container">
              <!-- First Die -->
              <div class="dice dice-<?php echo $dice_values[0]; ?>">
                <?php if ($dice_values[0] == 1): ?>
                  <div class="dice-dot dot-center"></div>
                <?php elseif ($dice_values[0] == 2): ?>
                  <div class="dice-dot dot-top-right"></div>
                  <div class="dice-dot dot-bottom-left"></div>
                <?php elseif ($dice_values[0] == 3): ?>
                  <div class="dice-dot dot-top-right"></div>
                  <div class="dice-dot dot-center"></div>
                  <div class="dice-dot dot-bottom-left"></div>
                <?php elseif ($dice_values[0] == 4): ?>
                  <div class="dice-dot dot-top-left"></div>
                  <div class="dice-dot dot-top-right"></div>
                  <div class="dice-dot dot-bottom-left"></div>
                  <div class="dice-dot dot-bottom-right"></div>
                <?php elseif ($dice_values[0] == 5): ?>
                  <div class="dice-dot dot-top-left"></div>
                  <div class="dice-dot dot-top-right"></div>
                  <div class="dice-dot dot-center"></div>
                  <div class="dice-dot dot-bottom-left"></div>
                  <div class="dice-dot dot-bottom-right"></div>
                <?php elseif ($dice_values[0] == 6): ?>
                  <div class="dice-dot dot-top-left"></div>
                  <div class="dice-dot dot-top-right"></div>
                  <div class="dice-dot dot-middle-left"></div>
                  <div class="dice-dot dot-middle-right"></div>
                  <div class="dice-dot dot-bottom-left"></div>
                  <div class="dice-dot dot-bottom-right"></div>
                <?php endif; ?>
              </div>

              <!-- Second Die -->
              <div class="dice dice-<?php echo $dice_values[1]; ?>">
                <?php if ($dice_values[1] == 1): ?>
                  <div class="dice-dot dot-center"></div>
                <?php elseif ($dice_values[1] == 2): ?>
                  <div class="dice-dot dot-top-right"></div>
                  <div class="dice-dot dot-bottom-left"></div>
                <?php elseif ($dice_values[1] == 3): ?>
                  <div class="dice-dot dot-top-right"></div>
                  <div class="dice-dot dot-center"></div>
                  <div class="dice-dot dot-bottom-left"></div>
                <?php elseif ($dice_values[1] == 4): ?>
                  <div class="dice-dot dot-top-left"></div>
                  <div class="dice-dot dot-top-right"></div>
                  <div class="dice-dot dot-bottom-left"></div>
                  <div class="dice-dot dot-bottom-right"></div>
                <?php elseif ($dice_values[1] == 5): ?>
                  <div class="dice-dot dot-top-left"></div>
                  <div class="dice-dot dot-top-right"></div>
                  <div class="dice-dot dot-center"></div>
                  <div class="dice-dot dot-bottom-left"></div>
                  <div class="dice-dot dot-bottom-right"></div>
                <?php elseif ($dice_values[1] == 6): ?>
                  <div class="dice-dot dot-top-left"></div>
                  <div class="dice-dot dot-top-right"></div>
                  <div class="dice-dot dot-middle-left"></div>
                  <div class="dice-dot dot-middle-right"></div>
                  <div class="dice-dot dot-bottom-left"></div>
                  <div class="dice-dot dot-bottom-right"></div>
                <?php endif; ?>
              </div>
            </div>

            <div class="dice-total">
              Total: <span class="font-bold"><?php echo $dice_values[0] + $dice_values[1]; ?></span>
            </div>

            <?php if (!empty($result_message)): ?>
              <div class="result-message <?php echo $result_type === 'win' ? 'win-message' : 'lose-message'; ?>">
                <?php echo $result_message; ?>
              </div>
            <?php endif; ?>
          <?php else: ?>
            <div class="text-center py-8">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 300" alt="Dice Roll" class="h-37 mx-auto">
                <!-- Background -->
                <rect width="400" height="300" fill="#1a202c" rx="10" ry="10" />

                <!-- Dice 1 - Red -->
                <g id="dice1" transform="translate(100, 150)">
                  <rect width="80" height="80" rx="15" ry="15" fill="#e53e3e" stroke="#742020" stroke-width="2" />
                  <!-- Dots for 5 -->
                  <circle cx="20" cy="20" r="7" fill="white" />
                  <circle cx="60" cy="20" r="7" fill="white" />
                  <circle cx="40" cy="40" r="7" fill="white" />
                  <circle cx="20" cy="60" r="7" fill="white" />
                  <circle cx="60" cy="60" r="7" fill="white" />
                </g>

                <!-- Dice 2 - Blue -->
                <g id="dice2" transform="translate(220, 150)">
                  <rect width="80" height="80" rx="15" ry="15" fill="#3182ce" stroke="#1e4e8c" stroke-width="2" />
                  <!-- Dots for 6 -->
                  <circle cx="20" cy="20" r="7" fill="white" />
                  <circle cx="20" cy="40" r="7" fill="white" />
                  <circle cx="20" cy="60" r="7" fill="white" />
                  <circle cx="60" cy="20" r="7" fill="white" />
                  <circle cx="60" cy="40" r="7" fill="white" />
                  <circle cx="60" cy="60" r="7" fill="white" />
                </g>

                <!-- Shine/Reflection on dice -->
                <path d="M105 140 L115 130 L130 130 L120 140 Z" fill="white" opacity="0.2" />
                <path d="M225 140 L235 130 L250 130 L240 140 Z" fill="white" opacity="0.2" />

                <!-- Shadow under dice -->
                <ellipse cx="140" cy="245" rx="45" ry="8" fill="black" opacity="0.3" />
                <ellipse cx="260" cy="245" rx="45" ry="8" fill="black" opacity="0.3" />

                <!-- Animation for dice 1 -->
                <animateTransform
                  xlink:href="#dice1"
                  attributeName="transform"
                  type="rotate"
                  from="0 140 150"
                  to="360 140 150"
                  dur="1.5s"
                  begin="0s"
                  repeatCount="1"
                  fill="freeze"
                  additive="sum" />

                <!-- Animation for dice 2 with slight delay -->
                <animateTransform
                  xlink:href="#dice2"
                  attributeName="transform"
                  type="rotate"
                  from="0 260 150"
                  to="360 260 150"
                  dur="1.5s"
                  begin="0.2s"
                  repeatCount="1"
                  fill="freeze"
                  additive="sum" />

                <!-- Text -->
                <text x="200" y="80" font-family="Arial, sans-serif" font-size="24" font-weight="bold" text-anchor="middle" fill="white">ROLL THE DICE</text>
                <text x="200" y="105" font-family="Arial, sans-serif" font-size="16" text-anchor="middle" fill="#a0aec0">Try your luck!</text>
              </svg>
              <p class="text-gray-400">Choose your bet and roll!</p>
            </div>
          <?php endif; ?>
        </div>

        <!-- Game Controls -->
        <div class="bg-gray-800 rounded-xl border border-gray-700 p-4 controls-section">
          <form method="POST" id="dice-form">
            <!-- Balance and Bet Amount -->
            <div class="flex justify-between items-center mb-4">
              <div>
                <p class="text-sm text-gray-400 mb-1">Your Balance</p>
                <p class="text-xl font-bold">$<?php echo number_format($balance, 2); ?></p>
              </div>

              <div class="relative">
                <input type="number" name="bet_amount" id="bet_amount" min="1" max="100" step="0.5" value="<?php echo $bet_amount ?: 1; ?>"
                  class="bg-gray-700 block pl-8 pr-4 py-2 border border-gray-600 rounded-md text-white text-base w-24">
                <div class="absolute inset-y-0 left-0 pl-2 flex items-center pointer-events-none">
                  <span class="text-gray-400">$</span>
                </div>
              </div>
            </div>

            <!-- Preset Bet Buttons -->
            <div class="grid grid-cols-3 gap-2 mb-4">
              <button type="button" class="preset-bet bg-gray-700 hover:bg-gray-600 text-white rounded-md" data-amount="1">$1</button>
              <button type="button" class="preset-bet bg-gray-700 hover:bg-gray-600 text-white rounded-md" data-amount="5">$5</button>
              <button type="button" class="preset-bet bg-gray-700 hover:bg-gray-600 text-white rounded-md" data-amount="10">$10</button>
              <button type="button" class="preset-bet bg-gray-700 hover:bg-gray-600 text-white rounded-md" data-amount="25">$25</button>
              <button type="button" class="preset-bet bg-gray-700 hover:bg-gray-600 text-white rounded-md" data-amount="50">$50</button>
              <button type="button" class="preset-bet bg-gray-700 hover:bg-gray-600 text-white rounded-md" data-amount="100">$100</button>
            </div>

            <!-- Bet Options -->
            <div class="mb-4">
              <p class="text-sm font-medium text-gray-300 mb-2">Choose Your Bet</p>
              <div class="bet-options">
                <?php foreach ($bet_types as $key => $bet): ?>
                  <div class="bet-option<?php echo $key === $selected_bet ? ' selected' : ''; ?>" data-bet="<?php echo $key; ?>">
                    <div class="bet-option-name"><?php echo $bet['name']; ?></div>
                    <div class="bet-option-description"><?php echo $bet['description']; ?></div>
                    <div class="bet-option-payout"><?php echo $bet['payout']; ?>x</div>
                    <input type="radio" name="bet_type" value="<?php echo $key; ?>" <?php echo $key === $selected_bet ? 'checked' : ''; ?> class="hidden">
                  </div>
                <?php endforeach; ?>
              </div>
            </div>

            <!-- Roll Button -->
            <input type="hidden" name="roll" value="1">
            <button type="submit" id="roll-btn" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-6 rounded-lg shadow-lg">
              <i class="fas fa-dice mr-2"></i> ROLL DICE
            </button>
          </form>
        </div>

        <!-- Recent Games History -->
        <div class="bg-gray-800 rounded-xl border border-gray-700 p-4 history-section">
          <h2 class="text-lg font-bold mb-3">Your Recent Games</h2>

          <?php if (empty($recent_games)): ?>
            <p class="text-gray-400 text-center py-2">No games yet. Start playing!</p>
          <?php else: ?>
            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-gray-700 history-table">
                <thead>
                  <tr>
                    <th class="text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Bet</th>
                    <th class="text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Type</th>
                    <th class="text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Dice</th>
                    <th class="text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Result</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-700">
                  <?php foreach ($recent_games as $game): ?>
                    <?php
                    $details = json_decode($game['details'], true);
                    $dice = $details['dice'] ?? [0, 0];
                    $bet_type_key = $details['bet_type'] ?? '';
                    $bet_type_name = isset($bet_types[$bet_type_key]) ? $bet_types[$bet_type_key]['name'] : '';
                    ?>
                    <tr>
                      <td class="whitespace-nowrap text-sm">
                        $<?php echo number_format($game['bet_amount'], 2); ?>
                      </td>
                      <td class="whitespace-nowrap text-sm">
                        <?php echo explode(' ', $bet_type_name)[0]; ?>
                      </td>
                      <td class="whitespace-nowrap text-sm">
                        <?php echo $dice[0]; ?>, <?php echo $dice[1]; ?>
                      </td>
                      <td class="whitespace-nowrap text-sm">
                        <?php if ($game['result'] == 'win'): ?>
                          <span class="text-green-500">+$<?php echo number_format($game['winnings'] - $game['bet_amount'], 2); ?></span>
                        <?php else: ?>
                          <span class="text-red-500">-$<?php echo number_format($game['bet_amount'], 2); ?></span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </main>

  <?php include '../includes/mobile-bar.php'; ?>
  <?php include '../includes/footer.php'; ?>
  <?php include '../includes/js-links.php'; ?>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Hide preloader
      setTimeout(function() {
        document.querySelector('.preloader').style.display = 'none';
        document.getElementById('main-content').style.display = 'block';
      }, 1500);

      // Betting functionality
      const betAmountInput = document.getElementById('bet_amount');
      const presetBtns = document.querySelectorAll('.preset-bet');
      const betOptions = document.querySelectorAll('.bet-option');
      const diceForm = document.getElementById('dice-form');
      const rollBtn = document.getElementById('roll-btn');

      // Simple click handler for preset bet buttons
      if (presetBtns) {
        presetBtns.forEach(btn => {
          btn.addEventListener('click', function() {
            const amount = this.getAttribute('data-amount');
            if (betAmountInput) {
              betAmountInput.value = amount;
            }
          });
        });
      }

      // Simple click handler for bet options
      if (betOptions) {
        betOptions.forEach(option => {
          option.addEventListener('click', function() {
            // Remove selected class from all options
            betOptions.forEach(opt => opt.classList.remove('selected'));

            // Add selected class to this option
            this.classList.add('selected');

            // Update hidden input
            const betType = this.getAttribute('data-bet');
            const radioInput = document.querySelector(`input[name="bet_type"][value="${betType}"]`);
            if (radioInput) {
              radioInput.checked = true;
            }
          });
        });
      }

      // Handle form submission
      if (diceForm) {
        diceForm.addEventListener('submit', function(e) {
          // Check if a bet option is selected
          const selectedBet = document.querySelector('input[name="bet_type"]:checked');
          if (!selectedBet) {
            e.preventDefault();
            alert('Please select a bet type.');
            return;
          }

          // Show rolling animation
          if (rollBtn) {
            rollBtn.disabled = true;
            rollBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> ROLLING...';
          }
        });
      }

      // Dice rolling animation
      <?php if (!empty($dice_values)): ?>
        // Animate dice when page loads
        const dice = document.querySelectorAll('.dice');
        if (dice) {
          dice.forEach(die => {
            // Store original class to restore later
            const originalClass = die.className;

            // Add rolling animation
            die.className = originalClass + ' rolling';

            // Remove animation after 1 second
            setTimeout(() => {
              die.className = originalClass;
            }, 1000);
          });
        }
      <?php endif; ?>
    });
  </script>
</body>

</html>