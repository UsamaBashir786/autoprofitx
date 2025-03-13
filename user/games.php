<?php
// Start session
session_start();
include '../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
  header("Location: ../login.php");
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

// Retrieve game history if available
$game_history = [];
$history_exists = false;

// Check if the game_history table exists
$table_check_query = "SHOW TABLES LIKE 'game_history'";
$table_result = $conn->query($table_check_query);

if ($table_result->num_rows > 0) {
  $history_exists = true;
  // Table exists, get user's game history
  $history_query = "SELECT * FROM game_history WHERE user_id = ? ORDER BY played_at DESC LIMIT 5";
  $stmt = $conn->prepare($history_query);
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $history_result = $stmt->get_result();

  while ($row = $history_result->fetch_assoc()) {
    $game_history[] = $row;
  }
}

// Get total winnings if available
$total_winnings = 0;
if ($history_exists) {
  $winnings_query = "SELECT SUM(winnings) as total FROM game_history WHERE user_id = ? AND result = 'win'";
  $stmt = $conn->prepare($winnings_query);
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $winnings_result = $stmt->get_result();
  if ($row = $winnings_result->fetch_assoc()) {
    $total_winnings = $row['total'] ?? 0;
  }
}

// Handle error/success messages from other pages
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';

// Clear session messages
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <?php include 'includes/head.php'; ?>
  <title>Games - AutoProftX</title>
  <style>
    /* Game Card Hover Effects */
    .game-card {
      transition: all 0.3s ease;
      transform-style: preserve-3d;
      perspective: 1000px;
    }

    .game-card:hover {
      transform: translateY(-10px) scale(1.02);
      box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.7), 0 10px 10px -5px rgba(0, 0, 0, 0.5);
    }

    .game-image {
      transition: all 0.5s ease;
      overflow: hidden;
    }

    .game-card:hover .game-image img {
      transform: scale(1.05);
    }

    .game-tag {
      position: absolute;
      top: 15px;
      right: 15px;
      z-index: 10;
    }

    /* Progress bar animation */
    @keyframes progressAnimation {
      0% {
        width: 0%;
      }
    }

    .progress-bar {
      animation: progressAnimation 1.5s ease-out forwards;
    }

    /* Shimmer effect for featured games */
    @keyframes shimmer {
      0% {
        background-position: -100% 0;
      }

      100% {
        background-position: 200% 0;
      }
    }

    .shimmer-border {
      position: relative;
      overflow: hidden;
    }

    .shimmer-border::after {
      content: "";
      position: absolute;
      top: -50%;
      left: -50%;
      width: 200%;
      height: 200%;
      background: linear-gradient(to right,
          rgba(255, 255, 255, 0) 0%,
          rgba(255, 209, 0, 0.3) 50%,
          rgba(255, 255, 255, 0) 100%);
      transform: rotate(30deg);
      animation: shimmer 3s infinite linear;
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
      <!-- Header Section -->
      <div class="mb-8">
        <h1 class="text-3xl font-bold">Games & Entertainment</h1>
        <p class="text-gray-400 mt-2">Play games, win rewards, and have fun!</p>
      </div>

      <!-- Notification Messages -->
      <?php if (!empty($success_message)): ?>
        <div class="mb-6 bg-green-900 bg-opacity-50 text-green-200 p-4 rounded-md flex items-start">
          <i class="fas fa-check-circle mt-1 mr-3"></i>
          <span><?php echo $success_message; ?></span>
        </div>
      <?php endif; ?>

      <?php if (!empty($error_message)): ?>
        <div class="mb-6 bg-red-900 bg-opacity-50 text-red-200 p-4 rounded-md flex items-start">
          <i class="fas fa-exclamation-circle mt-1 mr-3"></i>
          <span><?php echo $error_message; ?></span>
        </div>
      <?php endif; ?>

      <!-- Stats Overview -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <!-- Wallet Balance -->
        <div class="bg-gradient-to-br from-gray-900 to-gray-800 rounded-xl p-6 border border-gray-700 shadow-lg">
          <div class="flex justify-between items-start">
            <div>
              <p class="text-gray-400 text-sm font-medium uppercase tracking-wider">Wallet Balance</p>
              <h3 class="text-3xl font-extrabold text-white mt-2">$<?php echo number_format($balance, 2); ?></h3>
              <p class="text-blue-400 text-sm flex items-center mt-2 font-medium">
                <i class="fas fa-wallet mr-2"></i> Available for games
              </p>
            </div>
            <div class="h-12 w-12 rounded-full bg-blue-900 flex items-center justify-center shadow-lg">
              <i class="fas fa-coins text-blue-400 text-lg"></i>
            </div>
          </div>
        </div>

        <!-- Total Games Played -->
        <div class="bg-gradient-to-br from-gray-900 to-gray-800 rounded-xl p-6 border border-gray-700 shadow-lg">
          <div class="flex justify-between items-start">
            <div>
              <p class="text-gray-400 text-sm font-medium uppercase tracking-wider">Games Played</p>
              <h3 class="text-3xl font-extrabold text-white mt-2"><?php echo count($game_history); ?></h3>
              <p class="text-purple-400 text-sm flex items-center mt-2 font-medium">
                <i class="fas fa-gamepad mr-2"></i> Your activity
              </p>
            </div>
            <div class="h-12 w-12 rounded-full bg-purple-900 flex items-center justify-center shadow-lg">
              <i class="fas fa-trophy text-purple-400 text-lg"></i>
            </div>
          </div>
        </div>

        <!-- Total Winnings -->
        <div class="bg-gradient-to-br from-gray-900 to-gray-800 rounded-xl p-6 border border-gray-700 shadow-lg">
          <div class="flex justify-between items-start">
            <div>
              <p class="text-gray-400 text-sm font-medium uppercase tracking-wider">Total Winnings</p>
              <h3 class="text-3xl font-extrabold text-white mt-2">$<?php echo number_format($total_winnings, 2); ?></h3>
              <p class="text-green-400 text-sm flex items-center mt-2 font-medium">
                <i class="fas fa-award mr-2"></i> Your prizes
              </p>
            </div>
            <div class="h-12 w-12 rounded-full bg-green-900 flex items-center justify-center shadow-lg">
              <i class="fas fa-dollar-sign text-green-400 text-lg"></i>
            </div>
          </div>
        </div>
      </div>

      <!-- Featured Games -->
      <h2 class="text-2xl font-bold mb-6">Featured Games</h2>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 mb-12">
        <!-- Spin & Win Game -->
        <div class="game-card shimmer-border rounded-xl overflow-hidden bg-gradient-to-b from-yellow-900 to-gray-900 border border-yellow-700 shadow-lg relative">
          <div class="game-tag px-3 py-1 bg-yellow-600 text-black rounded-full text-xs font-bold">
            FEATURED
          </div>
          <div class="game-image">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 500 500">
              <!-- Background -->
              <rect width="500" height="500" fill="#1a202c" />

              <!-- Wheel base with gradient -->
              <defs>
                <linearGradient id="wheelRim" x1="0%" y1="0%" x2="100%" y2="0%">
                  <stop offset="0%" stop-color="#4a5568" />
                  <stop offset="50%" stop-color="#718096" />
                  <stop offset="100%" stop-color="#4a5568" />
                </linearGradient>
              </defs>

              <g transform="translate(250, 250)">
                <!-- Wheel outer rim -->
                <circle cx="0" cy="0" r="200" fill="url(#wheelRim)" stroke="#2d3748" stroke-width="5" />

                <!-- Wheel segments, 8 segments -->
                <!-- Segment 1 -->
                <path d="M 0 0 L 195 0 A 195 195 0 0 0 138 -137 L 0 0" fill="#e53e3e" stroke="#2d3748" stroke-width="2" />
                <text x="120" y="-45" font-family="Arial, sans-serif" font-size="18" font-weight="bold" fill="white" transform="rotate(22.5)">$100</text>

                <!-- Segment 2 -->
                <path d="M 0 0 L 138 -137 A 195 195 0 0 0 0 -195 L 0 0" fill="#38a169" stroke="#2d3748" stroke-width="2" />
                <text x="40" y="-120" font-family="Arial, sans-serif" font-size="18" font-weight="bold" fill="white" transform="rotate(67.5)">$25</text>

                <!-- Segment 3 -->
                <path d="M 0 0 L 0 -195 A 195 195 0 0 0 -138 -137 L 0 0" fill="#3182ce" stroke="#2d3748" stroke-width="2" />
                <text x="-60" y="-110" font-family="Arial, sans-serif" font-size="18" font-weight="bold" fill="white" transform="rotate(112.5)">$50</text>

                <!-- Segment 4 -->
                <path d="M 0 0 L -138 -137 A 195 195 0 0 0 -195 0 L 0 0" fill="#805ad5" stroke="#2d3748" stroke-width="2" />
                <text x="-130" y="-40" font-family="Arial, sans-serif" font-size="18" font-weight="bold" fill="white" transform="rotate(157.5)">$10</text>

                <!-- Segment 5 -->
                <path d="M 0 0 L -195 0 A 195 195 0 0 0 -138 137 L 0 0" fill="#dd6b20" stroke="#2d3748" stroke-width="2" />
                <text x="-120" y="45" font-family="Arial, sans-serif" font-size="18" font-weight="bold" fill="white" transform="rotate(202.5)">$200</text>

                <!-- Segment 6 -->
                <path d="M 0 0 L -138 137 A 195 195 0 0 0 0 195 L 0 0" fill="#d53f8c" stroke="#2d3748" stroke-width="2" />
                <text x="-40" y="120" font-family="Arial, sans-serif" font-size="18" font-weight="bold" fill="white" transform="rotate(247.5)">$5</text>

                <!-- Segment 7 -->
                <path d="M 0 0 L 0 195 A 195 195 0 0 0 138 137 L 0 0" fill="#ecc94b" stroke="#2d3748" stroke-width="2" />
                <text x="60" y="110" font-family="Arial, sans-serif" font-size="18" font-weight="bold" fill="white" transform="rotate(292.5)">$75</text>

                <!-- Segment 8 -->
                <path d="M 0 0 L 138 137 A 195 195 0 0 0 195 0 L 0 0" fill="#4299e1" stroke="#2d3748" stroke-width="2" />
                <text x="130" y="40" font-family="Arial, sans-serif" font-size="18" font-weight="bold" fill="white" transform="rotate(337.5)">$20</text>

                <!-- Center hub -->
                <circle cx="0" cy="0" r="30" fill="#2d3748" stroke="#a0aec0" stroke-width="3" />

                <!-- Spin animation -->
                <animateTransform
                  attributeName="transform"
                  attributeType="XML"
                  type="rotate"
                  from="0 0 0"
                  to="360 0 0"
                  dur="10s"
                  repeatCount="1"
                  fill="freeze" />
              </g>

              <!-- Wheel pointer/ticker -->
              <g>
                <path d="M 250 45 L 270 10 L 230 10 Z" fill="#e53e3e" stroke="#742020" stroke-width="2" />
                <!-- Shiny effect on pointer -->
                <path d="M 245 35 L 255 35 L 250 20 Z" fill="white" opacity="0.5" />
              </g>

              <!-- Title -->
              <text x="250" y="430" font-family="Arial, sans-serif" font-size="30" font-weight="bold" text-anchor="middle" fill="white">SPIN & WIN</text>
              <text x="250" y="460" font-family="Arial, sans-serif" font-size="18" text-anchor="middle" fill="#a0aec0">Spin the wheel for big prizes!</text>

              <!-- Shine effect at the top of the wheel -->
              <ellipse cx="250" cy="80" rx="180" ry="30" fill="white" opacity="0.1" />
            </svg>
          </div>
          <div class="p-6">
            <h3 class="text-xl font-bold mb-2">Spin & Win</h3>
            <p class="text-gray-300 mb-4">Spin the wheel and win big prizes up to 100x your bet!</p>
            <div class="mb-4">
              <div class="text-xs text-gray-400 uppercase tracking-wider mb-1">Popularity</div>
              <div class="w-full bg-gray-700 rounded-full h-2">
                <div class="bg-yellow-500 h-2 rounded-full progress-bar" style="width: 90%"></div>
              </div>
            </div>
            <div class="flex justify-between items-center">
              <div>
                <span class="text-xs text-gray-400">Min Bet:</span>
                <span class="font-bold text-white">$1.00</span>
              </div>
              <a style="z-index: 9999999;" href="games/spin-wheel.php" class="px-5 py-2 bg-yellow-600 hover:bg-yellow-500 text-black font-bold rounded-lg transition duration-300">
                Play Now
              </a>
            </div>
          </div>
        </div>

        <!-- Dice Roll Game -->
        <div class="game-card rounded-xl overflow-hidden bg-gradient-to-b from-blue-900 to-gray-900 border border-blue-700 shadow-lg relative">
          <div class="game-image">
            <!-- <img src="../assets/images/games/dice-game.jpg"> -->
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 300" alt="Dice Roll" class="">
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
          </div>
          <div class="p-6">
            <h3 class="text-xl font-bold mb-2">Dice Roll</h3>
            <p class="text-gray-300 mb-4">Predict the roll and multiply your stake. Simple yet thrilling!</p>
            <div class="mb-4">
              <div class="text-xs text-gray-400 uppercase tracking-wider mb-1">Popularity</div>
              <div class="w-full bg-gray-700 rounded-full h-2">
                <div class="bg-blue-500 h-2 rounded-full progress-bar" style="width: 75%"></div>
              </div>
            </div>
            <div class="flex justify-between items-center">
              <div>
                <span class="text-xs text-gray-400">Min Bet:</span>
                <span class="font-bold text-white">$0.50</span>
              </div>
              <a href="games/dice-roll.php" class="px-5 py-2 bg-blue-600 hover:bg-blue-500 text-white font-bold rounded-lg transition duration-300">
                Play Now
              </a>
            </div>
          </div>
        </div>

      </div>


      <!-- Game History Section -->
      <?php if (!empty($game_history)): ?>
        <div class="mb-8">
          <h2 class="text-2xl font-bold mb-6">Your Recent Game Activity</h2>
          <div class="bg-gray-800 rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-gray-700">
              <thead class="bg-gray-700">
                <tr>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Game</th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Played On</th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Bet Amount</th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Result</th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Winnings</th>
                </tr>
              </thead>
              <tbody class="bg-gray-800 divide-y divide-gray-700">
                <?php foreach ($game_history as $game): ?>
                  <tr class="hover:bg-gray-750">
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="flex items-center">
                        <div class="h-10 w-10 rounded-full flex items-center justify-center bg-gray-700">
                          <i class="fas fa-gamepad text-<?php echo $game['game_name'] == 'Crash Game' ? 'red' : ($game['game_name'] == 'Spin & Win' ? 'yellow' : 'blue'); ?>-500"></i>
                        </div>
                        <div class="ml-4">
                          <div class="text-sm font-medium text-white"><?php echo $game['game_name']; ?></div>
                        </div>
                      </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                      <?php echo date('M d, Y H:i', strtotime($game['played_at'])); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                      $<?php echo number_format($game['bet_amount'], 2); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <?php if ($game['result'] == 'win'): ?>
                        <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-900 text-green-300">
                          Win
                        </span>
                      <?php else: ?>
                        <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-900 text-red-300">
                          Loss
                        </span>
                      <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                      <?php if ($game['result'] == 'win'): ?>
                        <span class="text-green-400">+$<?php echo number_format($game['winnings'], 2); ?></span>
                      <?php else: ?>
                        <span class="text-red-400">-$<?php echo number_format($game['bet_amount'], 2); ?></span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>

      <!-- Game Rules Section -->
      <div class="bg-gray-800 rounded-lg p-6 border border-gray-700 mb-8">
        <h2 class="text-xl font-bold mb-4">Game Rules & Information</h2>
        <div class="text-gray-300 space-y-4">
          <p>All games on AutoProftX are fair and transparent. Here are some general rules to keep in mind:</p>
          <ul class="list-disc pl-5 space-y-2">
            <li>You must have sufficient balance in your wallet to place bets.</li>
            <li>Minimum and maximum bet amounts vary by game.</li>
            <li>All games use a provably fair system to ensure randomness.</li>
            <li>Winnings are automatically credited to your wallet.</li>
            <li>The house edge is clearly displayed for each game.</li>
            <li>You can view your game history and statistics at any time.</li>
            <li>If you experience any issues, please contact customer support.</li>
          </ul>
          <div class="bg-blue-900 bg-opacity-30 p-4 rounded-lg mt-4">
            <h3 class="text-lg font-semibold text-blue-400 mb-2">Responsible Gaming</h3>
            <p>AutoProftX encourages responsible gaming. Set limits for yourself and play for entertainment. If you feel you're developing unhealthy gaming habits, please use our self-exclusion tools or contact support for assistance.</p>
          </div>
        </div>
      </div>
    </div>
  </main>

  <?php include 'includes/footer.php'; ?>
  <?php include 'includes/js-links.php'; ?>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Hide preloader after 1.5 seconds
      setTimeout(function() {
        document.querySelector('.preloader').style.display = 'none';
        document.getElementById('main-content').style.display = 'block';
      }, 1500);
    });
  </script>
</body>

</html>