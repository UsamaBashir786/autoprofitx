<?php
// Games Section Include - Add this file to any page to promote games

// You can customize these game options or fetch them from your database
$featured_games = [
  [
    'id' => 1,
    'title' => 'Crash Game',
    'description' => 'Watch the multiplier rise and cash out before it crashes!',
    'min_bet' => 1,
    'max_multiplier' => '1000x',
    'icon' => 'fas fa-chart-line',
    'color' => 'from-red-500 to-orange-500',
    'players_online' => rand(30, 120)
  ],
  [
    'id' => 2,
    'title' => 'Slots',
    'description' => 'Spin to win with our premium slot machines',
    'min_bet' => 0.50,
    'max_multiplier' => '500x',
    'icon' => 'fas fa-dice',
    'color' => 'from-purple-500 to-blue-600',
    'players_online' => rand(40, 200)
  ],
  [
    'id' => 3,
    'title' => 'Dice Roll',
    'description' => 'Predict the outcome and win big rewards',
    'min_bet' => 0.25,
    'max_multiplier' => '95x',
    'icon' => 'fas fa-dice-d20',
    'color' => 'from-emerald-500 to-teal-500',
    'players_online' => rand(25, 90)
  ],
  [
    'id' => 4,
    'title' => 'Roulette',
    'description' => 'Classic casino game with multiple betting options',
    'min_bet' => 1,
    'max_multiplier' => '36x',
    'icon' => 'fas fa-circle-notch',
    'color' => 'from-yellow-500 to-amber-600',
    'players_online' => rand(50, 150)
  ]
];

// Get total online players (you might want to fetch this from your database)
$total_online_players = array_sum(array_column($featured_games, 'players_online'));

// Get user stats if logged in
$user_total_games_played = 0;
$user_total_winnings = 0;

if (isset($_SESSION['user_id'])) {
  $user_id = $_SESSION['user_id'];

  // Fetch user's game statistics (these queries should be adjusted to match your database structure)
  try {
    // Total games played
    $games_query = "SELECT COUNT(*) as total_games FROM game_history WHERE user_id = ?";
    $stmt = $conn->prepare($games_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
      $user_total_games_played = $row['total_games'];
    }

    // Total winnings
    $winnings_query = "SELECT SUM(profit) as total_winnings FROM game_history WHERE user_id = ? AND profit > 0";
    $stmt = $conn->prepare($winnings_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
      $user_total_winnings = $row['total_winnings'] ?? 0;
    }
  } catch (Exception $e) {
    // If there's an error or the tables don't exist yet, use default values
    $user_total_games_played = 0;
    $user_total_winnings = 0;
  }
}

// Get top winner (you can fetch this from your database)
$top_winner = [
  'username' => 'Alex M.',
  'game' => 'Crash Game',
  'winnings' => 12500,
  'multiplier' => '125x'
];

// Function to format large numbers with K, M, etc.
function formatNumber($number)
{
  if ($number >= 1000000) {
    return round($number / 1000000, 1) . 'M';
  } elseif ($number >= 1000) {
    return round($number / 1000, 1) . 'K';
  }
  return $number;
}
?>

<!-- Games Section with 3D Effect -->
<div class="games-section my-12">
  <!-- 3D Container with perspective effect -->
  <div class="games-3d-container">
    <!-- Section Header -->
    <div class="games-section-header">
      <div class="flex items-center justify-between">
        <div class="flex items-center">
          <div class="games-icon">
            <i class="fas fa-gamepad"></i>
          </div>
          <div>
            <h2 class="text-xl font-bold text-white">Popular Games</h2>
            <p class="text-gray-400 text-sm">Try your luck and win big rewards</p>
          </div>
        </div>

        <div class="online-players-badge">
          <i class="fas fa-users text-green-500 mr-1"></i>
          <span class="text-green-500 font-medium"><?php echo $total_online_players; ?></span>
          <span class="text-sm text-gray-400">playing now</span>
        </div>
      </div>
    </div>

    <!-- Game Cards Grid -->
    <div class="game-cards-grid">
      <?php foreach ($featured_games as $game): ?>
        <div class="game-card" onclick="window.location.href='games.php?game=<?php echo $game['id']; ?>'">
          <div class="game-card-glow bg-gradient-to-br <?php echo $game['color']; ?>"></div>

          <div class="game-card-icon bg-gradient-to-br <?php echo $game['color']; ?>">
            <i class="<?php echo $game['icon']; ?>"></i>
          </div>

          <div class="game-card-content">
            <h3 class="game-title"><?php echo $game['title']; ?></h3>
            <p class="game-description"><?php echo $game['description']; ?></p>

            <div class="game-stats">
              <div class="game-stat">
                <span class="stat-label">Min Bet</span>
                <span class="stat-value">$<?php echo $game['min_bet']; ?></span>
              </div>
              <div class="game-stat">
                <span class="stat-label">Max Win</span>
                <span class="stat-value"><?php echo $game['max_multiplier']; ?></span>
              </div>
              <div class="game-stat">
                <span class="stat-label">Players</span>
                <span class="stat-value"><?php echo $game['players_online']; ?></span>
              </div>
            </div>
          </div>

          <div class="play-now-btn">
            <span>PLAY NOW</span>
            <i class="fas fa-arrow-right ml-1"></i>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Games Section Footer -->
    <div class="games-section-footer">
      <div class="footer-content">
        <?php if (isset($_SESSION['user_id'])): ?>
          <!-- User Game Stats (Visible only when logged in) -->
          <div class="user-game-stats">
            <div class="stat-item">
              <div class="stat-icon">
                <i class="fas fa-trophy"></i>
              </div>
              <div class="stat-info">
                <span class="stat-label">Games Played</span>
                <span class="stat-value"><?php echo formatNumber($user_total_games_played); ?></span>
              </div>
            </div>
            <div class="stat-item">
              <div class="stat-icon">
                <i class="fas fa-coins"></i>
              </div>
              <div class="stat-info">
                <span class="stat-label">Total Winnings</span>
                <span class="stat-value">$<?php echo number_format($user_total_winnings, 2); ?></span>
              </div>
            </div>
          </div>
        <?php endif; ?>

        <!-- Top Winner Highlight -->
        <div class="top-winner-highlight">
          <div class="winner-badge">
            <i class="fas fa-crown"></i>
            <span>TOP WIN</span>
          </div>
          <div class="winner-info">
            <span class="winner-name"><?php echo $top_winner['username']; ?></span>
            <span class="winner-game">won <span class="text-green-500 font-bold">$<?php echo number_format($top_winner['winnings']); ?></span> on <?php echo $top_winner['game']; ?> (<?php echo $top_winner['multiplier']; ?>)</span>
          </div>
        </div>

        <!-- Main CTA Button -->
        <a href="games.php" class="all-games-btn">
          <span>VIEW ALL GAMES</span>
          <i class="fas fa-external-link-alt ml-2"></i>
        </a>
      </div>
    </div>
  </div>
</div>

<!-- CSS for 3D Games Section -->
<style>
  /* Base container styles */
  .games-section {
    width: 100%;
    max-width: 100%;
    font-family: inherit;
  }

  .games-3d-container {
    background: linear-gradient(145deg, #1a2436, #0f1825);
    border-radius: 16px;
    overflow: hidden;
    box-shadow:
      0 15px 35px rgba(0, 0, 0, 0.3),
      0 3px 10px rgba(0, 0, 0, 0.1);
    transform-style: preserve-3d;
    transform: perspective(1200px) rotateX(2deg);
    transition: all 0.5s ease;
    position: relative;
  }

  .games-3d-container::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 100%;
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.08) 0%, rgba(255, 255, 255, 0) 100%);
    pointer-events: none;
    z-index: 1;
  }

  .games-3d-container:hover {
    transform: perspective(1200px) rotateX(0deg);
    box-shadow:
      0 20px 40px rgba(0, 0, 0, 0.4),
      0 5px 15px rgba(0, 0, 0, 0.1);
  }

  /* Header styles */
  .games-section-header {
    background: linear-gradient(90deg, rgba(22, 33, 51, 0.95), rgba(12, 20, 30, 0.9));
    padding: 1.5rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    position: relative;
    z-index: 2;
    transform: translateZ(20px);
  }

  .games-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 12px;
    background: linear-gradient(135deg, #3b82f6, #1d4ed8);
    color: white;
    margin-right: 1rem;
    position: relative;
    font-size: 1.25rem;
    box-shadow: 0 4px 10px rgba(59, 130, 246, 0.4);
  }

  .games-icon::after {
    content: '';
    position: absolute;
    top: -3px;
    left: -3px;
    right: -3px;
    bottom: -3px;
    border-radius: 14px;
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.4), rgba(29, 78, 216, 0.4));
    filter: blur(6px);
    z-index: -1;
  }

  .online-players-badge {
    display: inline-flex;
    align-items: center;
    background: rgba(0, 0, 0, 0.25);
    border: 1px solid rgba(22, 163, 74, 0.3);
    padding: 0.4rem 0.8rem;
    border-radius: 20px;
    font-size: 0.9rem;
  }

  /* Game Cards Grid */
  .game-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 1.5rem;
    padding: 1.5rem;
    position: relative;
    z-index: 2;
    transform: translateZ(10px);
  }

  .game-card {
    background: rgba(17, 24, 39, 0.7);
    backdrop-filter: blur(10px);
    border-radius: 12px;
    padding: 1.5rem;
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
    cursor: pointer;
    transform-style: preserve-3d;
    transform: translateZ(5px);
    border: 1px solid rgba(255, 255, 255, 0.05);
    height: 100%;
    display: flex;
    flex-direction: column;
  }

  .game-card:hover {
    transform: translateZ(15px) scale(1.02);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
  }

  .game-card-glow {
    position: absolute;
    top: -30%;
    right: -30%;
    width: 120px;
    height: 120px;
    border-radius: 50%;
    filter: blur(40px);
    opacity: 0.15;
    z-index: 0;
    transition: all 0.5s ease;
  }

  .game-card:hover .game-card-glow {
    opacity: 0.25;
    transform: scale(1.2);
  }

  .game-card-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1rem;
    position: relative;
    z-index: 1;
    font-size: 1.5rem;
    color: white;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
  }

  .game-card-content {
    position: relative;
    z-index: 1;
    flex: 1;
  }

  .game-title {
    font-size: 1.25rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    color: white;
  }

  .game-description {
    font-size: 0.9rem;
    color: rgba(255, 255, 255, 0.7);
    margin-bottom: 1.25rem;
    line-height: 1.4;
  }

  .game-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.5rem;
    margin-bottom: 1.5rem;
  }

  .game-stat {
    display: flex;
    flex-direction: column;
    background: rgba(0, 0, 0, 0.2);
    padding: 0.5rem;
    border-radius: 8px;
    text-align: center;
  }

  .stat-label {
    font-size: 0.7rem;
    color: rgba(255, 255, 255, 0.5);
    margin-bottom: 0.2rem;
  }

  .stat-value {
    font-size: 0.85rem;
    font-weight: 600;
    color: white;
  }

  .play-now-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    background: rgba(255, 255, 255, 0.1);
    color: white;
    font-weight: 600;
    font-size: 0.9rem;
    padding: 0.75rem;
    border-radius: 8px;
    transition: all 0.3s ease;
    position: relative;
    z-index: 1;
    overflow: hidden;
    border: 1px solid rgba(255, 255, 255, 0.05);
  }

  .play-now-btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(45deg, rgba(255, 255, 255, 0), rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0));
    transform: translateX(-100%);
    transition: transform 0.6s ease;
    z-index: -1;
  }

  .game-card:hover .play-now-btn {
    background: linear-gradient(90deg, #3b82f6, #1d4ed8);
    border-color: rgba(255, 255, 255, 0.2);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
  }

  .game-card:hover .play-now-btn::before {
    transform: translateX(100%);
  }

  /* Footer styles */
  .games-section-footer {
    background: linear-gradient(90deg, rgba(17, 24, 39, 0.95), rgba(12, 20, 30, 0.9));
    padding: 1.5rem;
    border-top: 1px solid rgba(255, 255, 255, 0.05);
    position: relative;
    z-index: 2;
    transform: translateZ(20px);
  }

  .footer-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1rem;
  }

  .user-game-stats {
    display: flex;
    gap: 1.25rem;
  }

  .stat-item {
    display: flex;
    align-items: center;
  }

  .stat-icon {
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    margin-right: 0.75rem;
    font-size: 1rem;
  }

  .user-game-stats .stat-icon {
    background: rgba(255, 255, 255, 0.1);
    color: #FFD700;
  }

  .stat-info {
    display: flex;
    flex-direction: column;
  }

  .top-winner-highlight {
    display: flex;
    align-items: center;
    padding: 0.5rem 1rem;
    background: rgba(0, 0, 0, 0.2);
    border-radius: 30px;
    border: 1px solid rgba(255, 215, 0, 0.2);
  }

  .winner-badge {
    display: inline-flex;
    align-items: center;
    background: linear-gradient(135deg, #FFD700, #FFA500);
    color: black;
    font-weight: 700;
    font-size: 0.75rem;
    padding: 0.3rem 0.6rem;
    border-radius: 20px;
    margin-right: 0.75rem;
  }

  .winner-badge i {
    margin-right: 0.3rem;
  }

  .winner-info {
    display: flex;
    flex-direction: column;
  }

  .winner-name {
    font-weight: 600;
    color: white;
    font-size: 0.9rem;
  }

  .winner-game {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.7);
  }

  .all-games-btn {
    display: inline-flex;
    align-items: center;
    background: linear-gradient(90deg, #3b82f6, #1d4ed8);
    color: white;
    font-weight: 600;
    padding: 0.75rem 1.25rem;
    border-radius: 8px;
    transition: all 0.3s ease;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
    transform-style: preserve-3d;
    transform: translateZ(5px);
  }

  .all-games-btn:hover {
    transform: translateZ(10px) translateY(-2px);
    box-shadow: 0 6px 15px rgba(59, 130, 246, 0.4);
  }

  /* Responsive adjustments */
  @media (max-width: 768px) {
    .games-3d-container {
      transform: perspective(1200px) rotateX(0);
    }

    .game-cards-grid {
      grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    }

    .footer-content {
      flex-direction: column;
      align-items: flex-start;
    }

    .top-winner-highlight,
    .all-games-btn {
      width: 100%;
      justify-content: center;
      margin-top: 0.75rem;
    }

    .user-game-stats {
      width: 100%;
      justify-content: space-between;
    }
  }

  @media (max-width: 640px) {
    .game-cards-grid {
      grid-template-columns: 1fr;
    }

    .games-section-header {
      flex-direction: column;
      align-items: flex-start;
    }

    .online-players-badge {
      margin-top: 0.75rem;
    }
  }
</style>

<script>
  // Add subtle animation effects 
  document.addEventListener('DOMContentLoaded', function() {
    const gamesContainer = document.querySelector('.games-3d-container');
    const gameCards = document.querySelectorAll('.game-card');

    if (!gamesContainer) return;

    // Add subtle tilt effect on mouse move
    gamesContainer.addEventListener('mousemove', function(e) {
      const rect = gamesContainer.getBoundingClientRect();
      const x = (e.clientX - rect.left) / rect.width;
      const y = (e.clientY - rect.top) / rect.height;

      // Calculate rotation values
      const maxRotation = 1;
      const rotateY = (x - 0.5) * maxRotation;
      const rotateX = (0.5 - y) * maxRotation;

      // Apply rotation
      gamesContainer.style.transform = `perspective(1200px) rotateX(${1 + rotateX}deg) rotateY(${rotateY}deg)`;

      // Add subtle parallax to cards
      gameCards.forEach((card, index) => {
        const depth = 1 + (index * 0.05);
        card.style.transform = `translateZ(${5 * depth}px) translateX(${rotateY * 3}px) translateY(${rotateX * 3}px)`;
      });
    });

    // Reset on mouse leave
    gamesContainer.addEventListener('mouseleave', function() {
      gamesContainer.style.transform = 'perspective(1200px) rotateX(2deg) rotateY(0deg)';

      gameCards.forEach(card => {
        card.style.transform = 'translateZ(5px)';
      });
    });
  });
</script>