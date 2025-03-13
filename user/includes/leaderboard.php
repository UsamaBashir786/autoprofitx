<?php
// Mini Leaderboard Section - Include this file on any page

// Get top 5 depositors of all time
$mini_top_depositors_query = "SELECT 
                              u.id as user_id, 
                              u.full_name, 
                              u.referral_code,
                              SUM(d.amount) as total_deposited
                          FROM 
                              deposits d
                          JOIN 
                              users u ON d.user_id = u.id
                          WHERE 
                              d.status = 'approved'
                          GROUP BY 
                              d.user_id
                          ORDER BY 
                              total_deposited DESC
                          LIMIT 5";

$mini_top_result = $conn->query($mini_top_depositors_query);
$mini_top_depositors = [];

if ($mini_top_result) {
  while ($row = $mini_top_result->fetch_assoc()) {
    $mini_top_depositors[] = $row;
  }
}

// Get the user's current ranking if logged in
$user_rank = 0;
$user_total_deposits = 0;

if (isset($_SESSION['user_id'])) {
  $user_id = $_SESSION['user_id'];

  // Get user rank
  $user_rank_query = "SELECT user_rank FROM (
                      SELECT 
                          user_id,
                          @rank := @rank + 1 as user_rank
                      FROM 
                          (SELECT 
                              d.user_id,
                              SUM(d.amount) as total_deposited
                          FROM 
                              deposits d
                          WHERE 
                              d.status = 'approved'
                          GROUP BY 
                              d.user_id
                          ORDER BY 
                              total_deposited DESC
                          ) ranked_users,
                          (SELECT @rank := 0) r
                  ) rankings
                  WHERE user_id = ?";

  $stmt = $conn->prepare($user_rank_query);
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $rank_result = $stmt->get_result();

  if ($rank_result && $row = $rank_result->fetch_assoc()) {
    $user_rank = $row['user_rank'];
  }

  // Get user's total deposits
  $user_deposits_query = "SELECT 
                            SUM(amount) as total_deposited
                          FROM 
                            deposits
                          WHERE 
                            user_id = ? AND
                            status = 'approved'";

  $stmt = $conn->prepare($user_deposits_query);
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $deposits_result = $stmt->get_result();

  if ($deposits_result && $row = $deposits_result->fetch_assoc()) {
    $user_total_deposits = $row['total_deposited'] ?? 0;
  }
}

// Function to format a number with a suffix (1st, 2nd, 3rd, etc.)
function getOrdinalSuffix($number)
{
  if (!in_array(($number % 100), array(11, 12, 13))) {
    switch ($number % 10) {
      case 1:
        return $number . 'st';
      case 2:
        return $number . 'nd';
      case 3:
        return $number . 'rd';
    }
  }
  return $number . 'th';
}
?>

<!-- Leaderboard Mini Section with 3D Effect -->
<div class="leaderboard-mini-section my-8">
  <!-- 3D Perspective Container -->
  <div class="leaderboard-3d-container">
    <!-- Title Bar with 3D effect -->
    <div class="leaderboard-title-bar">
      <div class="flex items-center justify-between">
        <div class="flex items-center">
          <div class="trophy-icon">
            <i class="fas fa-trophy"></i>
          </div>
          <h3 class="text-xl font-bold">Top Investors</h3>
        </div>
        <a href="leaderboard.php" class="view-all-btn">
          View All
          <i class="fas fa-arrow-right ml-2"></i>
        </a>
      </div>
    </div>

    <!-- Top Depositors with 3D Cards -->
    <div class="leaderboard-3d-cards">
      <?php if (count($mini_top_depositors) > 0): ?>
        <?php foreach ($mini_top_depositors as $index => $depositor):
          $rank = $index + 1;
          $isCurrentUser = isset($_SESSION['user_id']) && $depositor['user_id'] == $_SESSION['user_id'];
        ?>
          <div class="leaderboard-3d-card <?php echo ($rank <= 3) ? 'top-rank rank-' . $rank : ''; ?> <?php echo $isCurrentUser ? 'current-user' : ''; ?>">
            <div class="rank-indicator">
              <span class="rank-number"><?php echo $rank; ?></span>
            </div>
            <div class="user-avatar">
              <?php echo strtoupper(substr($depositor['full_name'], 0, 1)); ?>
            </div>
            <div class="user-info">
              <div class="user-name">
                <?php
                if ($isCurrentUser) {
                  echo 'You';
                } else {
                  $name_parts = explode(' ', $depositor['full_name']);
                  echo $name_parts[0] . ' ' . substr(end($name_parts), 0, 1) . '.';
                }
                ?>
                <?php if ($isCurrentUser): ?>
                  <span class="you-badge">You</span>
                <?php endif; ?>
              </div>
              <div class="user-amount">$<?php echo number_format($depositor['total_deposited'], 0); ?></div>
            </div>
            <?php if ($rank <= 3): ?>
              <div class="rank-medal rank-<?php echo $rank; ?>">
                <i class="fas fa-<?php echo ($rank == 1) ? 'crown' : (($rank == 2) ? 'medal' : 'award'); ?>"></i>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="no-data-message">
          <i class="fas fa-chart-line"></i>
          <p>Be the first to join our leaderboard!</p>
          <a href="deposit.php" class="deposit-now-btn">Deposit Now</a>
        </div>
      <?php endif; ?>
    </div>

    <!-- User Stats Section with 3D effect -->
    <?php if (isset($_SESSION['user_id'])): ?>
      <div class="user-stats-section">
        <div class="user-rank-card">
          <div class="stat-icon">
            <i class="fas fa-signal"></i>
          </div>
          <div class="stat-info">
            <div class="stat-label">Your Rank</div>
            <div class="stat-value"><?php echo getOrdinalSuffix($user_rank); ?></div>
          </div>
        </div>
        <div class="user-amount-card">
          <div class="stat-icon">
            <i class="fas fa-wallet"></i>
          </div>
          <div class="stat-info">
            <div class="stat-label">Your Deposits</div>
            <div class="stat-value">$<?php echo number_format($user_total_deposits, 0); ?></div>
          </div>
        </div>
        <div class="monthly-prize-card">
          <div class="stat-icon">
            <i class="fas fa-gift"></i>
          </div>
          <div class="stat-info">
            <div class="stat-label">Monthly Prize</div>
            <div class="stat-value">Up to $5,000</div>
          </div>
        </div>
      </div>
    <?php else: ?>
      <div class="login-prompt">
        <p>Login to see your position on the leaderboard</p>
        <a href="login.php" class="login-btn">
          <i class="fas fa-sign-in-alt mr-2"></i>
          Login Now
        </a>
      </div>
    <?php endif; ?>

    <!-- Call to Action with 3D Button -->
    <div class="leaderboard-cta">
      <div class="cta-text">
        <h4>Compete to Win Monthly Bonuses!</h4>
        <p>Top investors receive up to $5,000 directly to their wallet</p>
      </div>
      <a href="deposit.php" class="cta-button">
        <span>Invest Now</span>
        <i class="fas fa-chevron-right"></i>
      </a>
    </div>
  </div>
</div>

<!-- CSS for 3D Leaderboard Mini Section -->
<style>
  /* Base styles for container */
  .leaderboard-mini-section {
    width: 100%;
    max-width: 100%;
    margin: 2rem 0;
    font-family: inherit;
  }

  .leaderboard-3d-container {
    background: linear-gradient(145deg, #1e2a3a, #131c28);
    border-radius: 16px;
    overflow: hidden;
    box-shadow:
      0 15px 35px rgba(0, 0, 0, 0.3),
      0 3px 10px rgba(0, 0, 0, 0.1);
    transform-style: preserve-3d;
    transform: perspective(1000px) rotateX(2deg);
    transition: all 0.5s ease;
    position: relative;
  }

  .leaderboard-3d-container::before {
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

  .leaderboard-3d-container:hover {
    transform: perspective(1000px) rotateX(0deg);
    box-shadow:
      0 20px 40px rgba(0, 0, 0, 0.4),
      0 5px 15px rgba(0, 0, 0, 0.1);
  }

  /* Title Bar */
  .leaderboard-title-bar {
    background: linear-gradient(90deg, #111827, #131c28);
    padding: 1.25rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    position: relative;
    z-index: 2;
    transform: translateZ(20px);
  }

  .trophy-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: linear-gradient(135deg, #FFD700, #FFA500);
    color: #000;
    margin-right: 0.75rem;
    position: relative;
    box-shadow: 0 3px 10px rgba(255, 215, 0, 0.3);
  }

  .trophy-icon::after {
    content: '';
    position: absolute;
    top: -2px;
    left: -2px;
    right: -2px;
    bottom: -2px;
    border-radius: 50%;
    background: linear-gradient(135deg, rgba(255, 215, 0, 0.5), rgba(255, 165, 0, 0.5));
    filter: blur(4px);
    z-index: -1;
  }

  .view-all-btn {
    display: inline-flex;
    align-items: center;
    font-size: 0.9rem;
    font-weight: 600;
    color: #FFD700;
    transition: all 0.3s ease;
    border-radius: 20px;
    padding: 0.35rem 0.75rem;
    background: rgba(255, 215, 0, 0.1);
  }

  .view-all-btn:hover {
    background: rgba(255, 215, 0, 0.2);
    transform: translateX(3px);
  }

  /* Leaderboard Cards */
  .leaderboard-3d-cards {
    padding: 1rem;
    position: relative;
    z-index: 2;
    transform: translateZ(10px);
  }

  .leaderboard-3d-card {
    display: flex;
    align-items: center;
    padding: 0.75rem 1rem;
    margin-bottom: 0.75rem;
    border-radius: 10px;
    background: rgba(24, 36, 51, 0.7);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.05);
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
    transform-style: preserve-3d;
    transform: translateZ(5px);
  }

  .leaderboard-3d-card:hover {
    transform: translateZ(15px) scale(1.01);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    background: rgba(30, 45, 65, 0.9);
    border-color: rgba(255, 215, 0, 0.2);
  }

  .leaderboard-3d-card.top-rank {
    background: linear-gradient(90deg, rgba(30, 45, 65, 0.95), rgba(24, 36, 51, 0.7));
  }

  .leaderboard-3d-card.rank-1 {
    border-left: 3px solid #FFD700;
    box-shadow: 0 0 20px rgba(255, 215, 0, 0.15);
  }

  .leaderboard-3d-card.rank-2 {
    border-left: 3px solid #C0C0C0;
    box-shadow: 0 0 15px rgba(192, 192, 192, 0.1);
  }

  .leaderboard-3d-card.rank-3 {
    border-left: 3px solid #CD7F32;
    box-shadow: 0 0 15px rgba(205, 127, 50, 0.1);
  }

  .leaderboard-3d-card.current-user {
    border: 1px solid rgba(255, 215, 0, 0.3);
    background: rgba(40, 60, 85, 0.8);
  }

  .rank-indicator {
    width: 26px;
    height: 26px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.1);
    color: rgba(255, 255, 255, 0.7);
    font-weight: 700;
    font-size: 0.8rem;
    margin-right: 0.75rem;
  }

  .rank-1 .rank-indicator {
    background: linear-gradient(135deg, #FFD700, #FFA500);
    color: #000;
  }

  .rank-2 .rank-indicator {
    background: linear-gradient(135deg, #C0C0C0, #A9A9A9);
    color: #000;
  }

  .rank-3 .rank-indicator {
    background: linear-gradient(135deg, #CD7F32, #8B4513);
    color: #fff;
  }

  .user-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(145deg, #263648, #1c2936);
    color: #fff;
    font-weight: 600;
    font-size: 1rem;
    margin-right: 0.75rem;
    box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.25);
    border: 1px solid rgba(255, 255, 255, 0.1);
  }

  .user-info {
    flex: 1;
  }

  .user-name {
    font-weight: 600;
    color: #fff;
    display: flex;
    align-items: center;
  }

  .you-badge {
    font-size: 0.65rem;
    padding: 0.1rem 0.4rem;
    border-radius: 20px;
    background: rgba(255, 215, 0, 0.2);
    color: #FFD700;
    margin-left: 0.4rem;
    font-weight: 700;
    text-transform: uppercase;
  }

  .user-amount {
    font-size: 0.85rem;
    color: #FFD700;
    font-weight: 700;
  }

  .rank-medal {
    position: absolute;
    top: -6px;
    right: -6px;
    width: 25px;
    height: 25px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    font-size: 0.75rem;
    z-index: 2;
    transform: rotate(15deg);
    box-shadow: 0 3px 8px rgba(0, 0, 0, 0.2);
  }

  .rank-medal.rank-1 {
    background: linear-gradient(135deg, #FFD700, #FFA500);
    color: #000;
  }

  .rank-medal.rank-2 {
    background: linear-gradient(135deg, #C0C0C0, #A9A9A9);
    color: #000;
  }

  .rank-medal.rank-3 {
    background: linear-gradient(135deg, #CD7F32, #8B4513);
    color: #fff;
  }

  /* No Data Message */
  .no-data-message {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 2rem 1rem;
    text-align: center;
  }

  .no-data-message i {
    font-size: 2.5rem;
    color: rgba(255, 255, 255, 0.2);
    margin-bottom: 1rem;
  }

  .no-data-message p {
    color: rgba(255, 255, 255, 0.6);
    margin-bottom: 1rem;
  }

  .deposit-now-btn {
    display: inline-flex;
    background: linear-gradient(135deg, #FFD700, #FFA500);
    color: #000;
    font-weight: 600;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    transition: all 0.3s ease;
    box-shadow: 0 4px 10px rgba(255, 215, 0, 0.3);
    transform: translateZ(5px);
  }

  .deposit-now-btn:hover {
    transform: translateZ(10px) translateY(-2px);
    box-shadow: 0 6px 15px rgba(255, 215, 0, 0.4);
  }

  /* User Stats Section */
  .user-stats-section {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.75rem;
    padding: 0 1rem 1rem;
    position: relative;
    z-index: 2;
    transform: translateZ(10px);
  }

  .user-rank-card,
  .user-amount-card,
  .monthly-prize-card {
    display: flex;
    align-items: center;
    padding: 0.75rem;
    border-radius: 8px;
    background: rgba(20, 30, 46, 0.8);
    border: 1px solid rgba(255, 255, 255, 0.05);
    transition: all 0.3s ease;
    transform-style: preserve-3d;
    transform: translateZ(5px);
  }

  .user-rank-card:hover,
  .user-amount-card:hover,
  .monthly-prize-card:hover {
    transform: translateZ(15px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    background: rgba(25, 38, 55, 0.9);
  }

  .stat-icon {
    width: 32px;
    height: 32px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 0.75rem;
    font-size: 0.9rem;
    box-shadow: 0 3px 8px rgba(0, 0, 0, 0.15);
  }

  .user-rank-card .stat-icon {
    background: linear-gradient(135deg, #3B82F6, #2563EB);
    color: #fff;
  }

  .user-amount-card .stat-icon {
    background: linear-gradient(135deg, #10B981, #059669);
    color: #fff;
  }

  .monthly-prize-card .stat-icon {
    background: linear-gradient(135deg, #FBBF24, #D97706);
    color: #fff;
  }

  .stat-info {
    flex: 1;
  }

  .stat-label {
    font-size: 0.7rem;
    color: rgba(255, 255, 255, 0.6);
  }

  .stat-value {
    font-size: 0.9rem;
    font-weight: 700;
    color: #fff;
  }

  /* Login Prompt */
  .login-prompt {
    padding: 1.25rem;
    text-align: center;
    color: rgba(255, 255, 255, 0.7);
    background: rgba(20, 30, 46, 0.6);
    margin: 0 1rem 1rem;
    border-radius: 8px;
    border: 1px dashed rgba(255, 255, 255, 0.15);
  }

  .login-btn {
    display: inline-flex;
    align-items: center;
    background: rgba(255, 255, 255, 0.1);
    color: #fff;
    padding: 0.4rem 1rem;
    border-radius: 20px;
    margin-top: 0.75rem;
    font-weight: 500;
    transition: all 0.3s ease;
  }

  .login-btn:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: translateY(-2px);
  }

  /* CTA Section */
  .leaderboard-cta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1.25rem;
    background: linear-gradient(90deg, rgba(30, 45, 65, 0.9), rgba(17, 24, 39, 0.9));
    border-top: 1px solid rgba(255, 255, 255, 0.05);
    position: relative;
    z-index: 2;
    transform: translateZ(20px);
  }

  .cta-text h4 {
    font-weight: 700;
    color: #FFD700;
    margin-bottom: 0.25rem;
  }

  .cta-text p {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.7);
  }

  .cta-button {
    display: inline-flex;
    align-items: center;
    background: linear-gradient(135deg, #FFD700, #FFA500);
    color: #000;
    font-weight: 600;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    box-shadow:
      0 4px 12px rgba(255, 215, 0, 0.3),
      0 0 0 1px rgba(255, 215, 0, 0.1);
    transition: all 0.3s ease;
    transform-style: preserve-3d;
    transform: perspective(500px) rotateX(5deg);
  }

  .cta-button:hover {
    transform: perspective(500px) rotateX(0);
    box-shadow:
      0 6px 15px rgba(255, 215, 0, 0.4),
      0 0 0 1px rgba(255, 215, 0, 0.3);
  }

  .cta-button i {
    margin-left: 0.5rem;
    transition: transform 0.3s ease;
  }

  .cta-button:hover i {
    transform: translateX(3px);
  }

  /* Responsive adjustments */
  @media (max-width: 768px) {
    .user-stats-section {
      grid-template-columns: 1fr;
      gap: 0.5rem;
    }

    .leaderboard-cta {
      flex-direction: column;
      text-align: center;
    }

    .cta-text {
      margin-bottom: 1rem;
    }

    .leaderboard-3d-container {
      transform: perspective(1000px) rotateX(0);
    }
  }

  @media (min-width: 769px) and (max-width: 1024px) {
    .user-stats-section {
      grid-template-columns: repeat(3, 1fr);
    }

    .stat-label {
      font-size: 0.65rem;
    }

    .stat-value {
      font-size: 0.85rem;
    }
  }
</style>

<script>
  // Add some subtle animation effects when scrolling
  document.addEventListener('DOMContentLoaded', function() {
    const leaderboardContainer = document.querySelector('.leaderboard-3d-container');
    const cards = document.querySelectorAll('.leaderboard-3d-card');

    if (!leaderboardContainer) return;

    // Subtle tilt effect on mouse move
    leaderboardContainer.addEventListener('mousemove', function(e) {
      const rect = leaderboardContainer.getBoundingClientRect();
      const x = (e.clientX - rect.left) / rect.width;
      const y = (e.clientY - rect.top) / rect.height;

      // Calculate rotation values
      const maxRotation = 1.5;
      const rotateY = (x - 0.5) * maxRotation;
      const rotateX = (0.5 - y) * maxRotation;

      // Apply rotation
      leaderboardContainer.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg)`;

      // Add slight movement to cards for parallax effect
      cards.forEach((card, index) => {
        const depth = 1 + (index * 0.05);
        card.style.transform = `translateZ(${5 * depth}px) translateX(${rotateY * 2}px) translateY(${rotateX * 2}px)`;
      });
    });

    // Reset position on mouse leave
    leaderboardContainer.addEventListener('mouseleave', function() {
      leaderboardContainer.style.transform = 'perspective(1000px) rotateX(2deg) rotateY(0deg)';

      cards.forEach(card => {
        card.style.transform = 'translateZ(5px)';
      });
    });
  });
</script>