<?php
// Additional helper functions for the betting games system

// Update user balance
function updateUserBalance($userId, $amount)
{
  global $conn;
  $sql = "UPDATE wallets SET balance = balance + ?, updated_at = NOW() WHERE user_id = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("di", $amount, $userId);
  return $stmt->execute();
}

// Update user bonus balance
function updateUserBonusBalance($userId, $amount)
{
  global $conn;
  $sql = "UPDATE user_bonuses SET remaining_amount = remaining_amount + ? WHERE user_id = ? AND is_active = 1 AND remaining_amount > 0 AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("di", $amount, $userId);
  return $stmt->execute();
}

// Update user wagering progress
function updateUserWageringProgress($userId, $amount)
{
  global $conn;
  $sql = "UPDATE user_bonuses SET wagered_amount = wagered_amount + ? WHERE user_id = ? AND is_active = 1 AND remaining_amount > 0 AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("di", $amount, $userId);
  return $stmt->execute();
}

// Get game details
function getGameDetails($gameId)
{
  global $conn;
  $sql = "SELECT * FROM betting_games WHERE id = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("i", $gameId);
  $stmt->execute();
  $result = $stmt->get_result();
  if ($result->num_rows > 0) {
    return $result->fetch_assoc();
  }
  return null;
}

// Get user betting statistics
function getUserBettingStats($userId)
{
  global $conn;
  $stats = [
    'total_bets' => 0,
    'wins' => 0,
    'losses' => 0,
    'consecutive_wins' => 0,
    'net_loss' => 0
  ];

  // Get overall stats
  $sql = "SELECT 
                COUNT(*) as total_bets,
                SUM(CASE WHEN outcome > 0 THEN 1 ELSE 0 END) as wins,
                SUM(CASE WHEN outcome = 0 THEN 1 ELSE 0 END) as losses,
                SUM(outcome) as total_won,
                SUM(bet_amount) as total_wagered
            FROM user_bets WHERE user_id = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("i", $userId);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows > 0) {
    $data = $result->fetch_assoc();
    $stats['total_bets'] = $data['total_bets'];
    $stats['wins'] = $data['wins'] ?? 0;
    $stats['losses'] = $data['losses'] ?? 0;
    $stats['net_loss'] = max(0, ($data['total_wagered'] ?? 0) - ($data['total_won'] ?? 0));
  }

  // Get consecutive wins
  $sql = "SELECT outcome > 0 as won FROM user_bets 
            WHERE user_id = ? ORDER BY bet_time DESC LIMIT 10";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("i", $userId);
  $stmt->execute();
  $result = $stmt->get_result();

  $consecWins = 0;
  while ($row = $result->fetch_assoc()) {
    if ($row['won']) {
      $consecWins++;
    } else {
      break;
    }
  }
  $stats['consecutive_wins'] = $consecWins;

  return $stats;
}

// Update user win streak
function updateUserWinStreak($userId, $won)
{
  // This function could update a separate table tracking user win streaks
  // For simplicity, we're just using the user_bets table for now
  return true;
}

// Generate Lucky Wheel outcome
function generateWheelOutcome($betAmount, $houseEdge)
{
  $segments = [
    ['multiplier' => 0, 'weight' => $houseEdge * 100],      // Loss
    ['multiplier' => 1.5, 'weight' => 35 - ($houseEdge * 50)],
    ['multiplier' => 2, 'weight' => 30 - ($houseEdge * 50)],
    ['multiplier' => 3, 'weight' => 20 - ($houseEdge * 30)],
    ['multiplier' => 5, 'weight' => 10 - ($houseEdge * 20)],
    ['multiplier' => 10, 'weight' => 4 - ($houseEdge * 10)],
    ['multiplier' => 20, 'weight' => 0.9 - ($houseEdge * 5)],
    ['multiplier' => 50, 'weight' => 0.1 - ($houseEdge * 0.5)]
  ];

  // Ensure weights are not negative
  foreach ($segments as &$segment) {
    $segment['weight'] = max(0.1, $segment['weight']);
  }

  // Calculate total weight
  $totalWeight = array_sum(array_column($segments, 'weight'));

  // Normalize weights
  foreach ($segments as &$segment) {
    $segment['weight'] = $segment['weight'] / $totalWeight * 100;
  }

  // Roll for outcome
  $roll = mt_rand(1, 10000) / 100;  // 0.01 to 100.00

  $cumulativeWeight = 0;
  foreach ($segments as $segment) {
    $cumulativeWeight += $segment['weight'];
    if ($roll <= $cumulativeWeight) {
      $won = $segment['multiplier'] > 0;
      $winAmount = $betAmount * $segment['multiplier'];

      // Return results including visual data for the wheel
      return [
        'won' => $won,
        'amount' => $winAmount,
        'multiplier' => $segment['multiplier'],
        'segment_index' => array_search($segment, $segments),
        'total_segments' => count($segments),
        'wheel_position' => mt_rand(1, 360)  // Visual position of wheel (degrees)
      ];
    }
  }

  // Fallback (should never happen)
  return ['won' => false, 'amount' => 0, 'multiplier' => 0];
}

// Generate Coin Flip outcome
function generateCoinFlipOutcome($betAmount, $houseEdge)
{
  // Get number of coin flips (from game parameters or default to 3)
  $numFlips = 3;

  // Base win probability for a single flip (slightly less than 50%)
  $singleFlipWinProb = (1 - $houseEdge / 2) * 0.5;

  // Calculate probability of winning all flips
  $winAllProb = pow($singleFlipWinProb, $numFlips) * 100;

  $roll = mt_rand(1, 10000) / 100;  // 0.01 to 100.00

  if ($roll <= $winAllProb) {
    // User wins
    $multiplier = pow(1.9, $numFlips);  // 1.9x for each correct flip
    $winAmount = $betAmount * $multiplier;

    return [
      'won' => true,
      'amount' => $winAmount,
      'multiplier' => $multiplier,
      'flips' => array_fill(0, $numFlips, true)  // All flips are wins
    ];
  } else {
    // User loses, but determine at which flip
    $loseAtFlip = floor($roll / ($winAllProb / $numFlips));
    $loseAtFlip = min($numFlips - 1, max(0, $loseAtFlip));

    $flips = [];
    for ($i = 0; $i < $numFlips; $i++) {
      $flips[$i] = $i < $loseAtFlip;  // Wins until the losing flip
    }

    return [
      'won' => false,
      'amount' => 0,
      'multiplier' => 0,
      'flips' => $flips,
      'failed_at' => $loseAtFlip
    ];
  }
}

// Generate Hidden Number outcome
function generateHiddenNumberOutcome($betAmount, $houseEdge)
{
  // Target number is between 1-100
  $target = mt_rand(1, 100);

  // User's guess would come from frontend, but we'll simulate it
  $guess = mt_rand(1, 100);

  // Difference between guess and target
  $difference = abs($target - $guess);

  // Win conditions (adjusted by house edge)
  $exactMatchProb = (1 - $houseEdge) * 0.01 * 100;  // Probability of exact match
  $closeMatchProb = (1 - $houseEdge) * 0.1 * 100;   // Probability of close match (within 5)

  $roll = mt_rand(1, 10000) / 100;  // 0.01 to 100.00

  if ($difference === 0 || $roll <= $exactMatchProb) {
    // Exact match (or lucky roll for high payout)
    $multiplier = 95;
    $winAmount = $betAmount * $multiplier;
    return [
      'won' => true,
      'amount' => $winAmount,
      'multiplier' => $multiplier,
      'target' => $target,
      'guess' => $target,  // Force exact match
      'difference' => 0
    ];
  } else if ($difference <= 5 || $roll <= $closeMatchProb) {
    // Close match (or lucky roll for medium payout)
    $multiplier = 15 - $difference * 2;  // 5-15x depending on how close
    $winAmount = $betAmount * $multiplier;
    return [
      'won' => true,
      'amount' => $winAmount,
      'multiplier' => $multiplier,
      'target' => $target,
      'guess' => $guess,
      'difference' => $difference
    ];
  } else {
    // Loss
    return [
      'won' => false,
      'amount' => 0,
      'multiplier' => 0,
      'target' => $target,
      'guess' => $guess,
      'difference' => $difference,
      'message' => $guess > $target ? "Too high!" : "Too low!"
    ];
  }
}

// Generate Crash Game outcome
function generateCrashGameOutcome($betAmount, $houseEdge)
{
  // Calculate crash point based on house edge
  // This formula ensures the expected value matches the house edge
  $houseEdgeFactor = 1 / (1 - $houseEdge);

  // Generate a random number between 0 and 1
  $rand = mt_rand() / mt_getrandmax();

  // Calculate crash point using exponential distribution
  // This creates more frequent early crashes and rare high multipliers
  $crashPoint = $houseEdgeFactor / (1 - $rand);

  // Handle maximum multiplier (to prevent infinite growth)
  $maxMultiplier = 100;
  $crashPoint = min($crashPoint, $maxMultiplier);

  // Round to 2 decimal places
  $crashPoint = round($crashPoint, 2);

  // Simulate user cashing out at a random point
  $cashoutPoint = mt_rand(120, 350) / 100;  // Between 1.2x and 3.5x

  // Determine if user won
  $won = $cashoutPoint < $crashPoint;

  if ($won) {
    $winAmount = $betAmount * $cashoutPoint;
    return [
      'won' => true,
      'amount' => $winAmount,
      'multiplier' => $cashoutPoint,
      'crash_point' => $crashPoint,
      'cashout_point' => $cashoutPoint
    ];
  } else {
    return [
      'won' => false,
      'amount' => 0,
      'multiplier' => 0,
      'crash_point' => $crashPoint,
      'cashout_point' => null  // User didn't cash out in time
    ];
  }
}

// Add this JavaScript for the front-end to handle the games
?>

<script>
  // JavaScript functions for the betting games frontend

  // Lucky Wheel game
  function playLuckyWheel(gameId, betAmount) {
    return $.ajax({
      url: 'betting-games.php',
      type: 'POST',
      data: {
        action: 'play_game',
        game_id: gameId,
        bet_amount: betAmount,
        game_params: {}
      },
      dataType: 'json'
    }).then(function(response) {
      if (response.success) {
        // Animate the wheel
        const wheelAnimation = animateWheel(response.game_data.wheel_position, response.game_data.segment_index);

        // After animation completes, show result
        setTimeout(function() {
          if (response.won) {
            showWinAnimation(response.amount, response.game_data.multiplier);
          } else {
            showLossAnimation();
          }
          updateBalance(response.used_bonus);
        }, 3000); // After wheel animation

        return response;
      } else {
        showError(response.message);
        return response;
      }
    });
  }

  // Coin Flip game
  function playCoinFlip(gameId, betAmount) {
    return $.ajax({
      url: 'betting-games.php',
      type: 'POST',
      data: {
        action: 'play_game',
        game_id: gameId,
        bet_amount: betAmount,
        game_params: {}
      },
      dataType: 'json'
    }).then(function(response) {
      if (response.success) {
        // Animate coin flips one by one
        animateCoinFlips(response.game_data.flips, function() {
          if (response.won) {
            showWinAnimation(response.amount, response.game_data.multiplier);
          } else {
            showLossAnimation();
          }
          updateBalance(response.used_bonus);
        });

        return response;
      } else {
        showError(response.message);
        return response;
      }
    });
  }

  // Hidden Number game
  function playHiddenNumber(gameId, betAmount, guess) {
    return $.ajax({
      url: 'betting-games.php',
      type: 'POST',
      data: {
        action: 'play_game',
        game_id: gameId,
        bet_amount: betAmount,
        game_params: {
          guess: guess
        }
      },
      dataType: 'json'
    }).then(function(response) {
      if (response.success) {
        // Reveal the number with animation
        animateNumberReveal(response.game_data.target, response.game_data.guess, function() {
          if (response.won) {
            showWinAnimation(response.amount, response.game_data.multiplier);
          } else {
            showLossAnimation(response.game_data.message);
          }
          updateBalance(response.used_bonus);
        });

        return response;
      } else {
        showError(response.message);
        return response;
      }
    });
  }

  // Crash Game
  function playCrashGame(gameId, betAmount, autoCashout) {
    // Set up the game state
    let gameActive = true;
    let currentMultiplier = 1.00;
    let timerInterval;

    // Show game UI
    showCrashGameUI(betAmount, autoCashout);

    // Get the crash point from server
    $.ajax({
      url: 'betting-games.php',
      type: 'POST',
      data: {
        action: 'play_game',
        game_id: gameId,
        bet_amount: betAmount,
        game_params: {
          auto_cashout: autoCashout
        }
      },
      dataType: 'json'
    }).then(function(response) {
      if (response.success) {
        const crashPoint = response.game_data.crash_point;

        // Start the multiplier animation
        timerInterval = setInterval(function() {
          if (!gameActive) return;

          // Increase multiplier
          currentMultiplier = Math.round((currentMultiplier + 0.01) * 100) / 100;
          updateMultiplierDisplay(currentMultiplier);

          // Check if crashed
          if (currentMultiplier >= crashPoint) {
            gameActive = false;
            clearInterval(timerInterval);
            showCrashAnimation();

            // Show result after crash
            setTimeout(function() {
              if (response.won) {
                showWinAnimation(response.amount, response.game_data.multiplier);
              } else {
                showLossAnimation();
              }
              updateBalance(response.used_bonus);
            }, 1000);
          }

          // Check if auto cashout triggered
          if (autoCashout && currentMultiplier >= autoCashout && gameActive) {
            manualCashout();
          }
        }, 100);

        return response;
      } else {
        showError(response.message);
        return response;
      }
    });

    // Function for manual cashout
    function manualCashout() {
      if (!gameActive) return;

      gameActive = false;
      clearInterval(timerInterval);

      // Calculate winnings
      const winnings = betAmount * currentMultiplier;

      // Record cashout with server
      $.ajax({
        url: 'betting-games.php',
        type: 'POST',
        data: {
          action: 'crash_cashout',
          game_id: gameId,
          bet_amount: betAmount,
          multiplier: currentMultiplier
        },
        dataType: 'json'
      }).then(function(response) {
        showWinAnimation(winnings, currentMultiplier);
        updateBalance(false); // Always update real balance for cashouts
      });
    }

    // Return the manual cashout function for the UI
    return {
      cashout: manualCashout
    };
  }

  // Helper functions for UI animations
  function showWinAnimation(amount, multiplier) {
    // Implement win animation
    console.log(`Win! Amount: $${amount.toFixed(2)}, Multiplier: ${multiplier}x`);

    // Create DOM elements for win popup
    const winPopup = document.createElement('div');
    winPopup.className = 'win-popup';
    winPopup.innerHTML = `
        <div class="win-amount">+$${amount.toFixed(2)}</div>
        <div class="win-multiplier">${multiplier}x Multiplier!</div>
    `;

    document.body.appendChild(winPopup);

    // Add animation classes
    setTimeout(() => {
      winPopup.classList.add('show');

      // Remove after animation
      setTimeout(() => {
        winPopup.classList.remove('show');
        setTimeout(() => {
          document.body.removeChild(winPopup);
        }, 500);
      }, 3000);
    }, 10);
  }

  function showLossAnimation(message) {
    // Implement loss animation
    console.log(`Loss! ${message || ''}`);

    // Create DOM elements for loss notification
    const lossNotif = document.createElement('div');
    lossNotif.className = 'loss-notification';
    lossNotif.innerHTML = `
        <div class="loss-icon">❌</div>
        <div class="loss-message">${message || 'Better luck next time!'}</div>
    `;

    document.body.appendChild(lossNotif);

    // Add animation classes
    setTimeout(() => {
      lossNotif.classList.add('show');

      // Remove after animation
      setTimeout(() => {
        lossNotif.classList.remove('show');
        setTimeout(() => {
          document.body.removeChild(lossNotif);
        }, 500);
      }, 2000);
    }, 10);
  }

  function updateBalance(usedBonus) {
    // Refresh balances from server
    $.ajax({
      url: 'betting-games.php',
      type: 'POST',
      data: {
        action: 'get_balance'
      },
      dataType: 'json'
    }).then(function(response) {
      if (response.success) {
        // Update balance displays
        $('#real-balance').text(`$${response.balance.toFixed(2)}`);
        $('#bonus-balance').text(`$${response.bonus_balance.toFixed(2)}`);

        // Highlight the balance that changed
        if (usedBonus) {
          $('#bonus-balance').addClass('balance-updated');
        } else {
          $('#real-balance').addClass('balance-updated');
        }

        // Remove highlight after animation
        setTimeout(() => {
          $('.balance-updated').removeClass('balance-updated');
        }, 2000);
      }
    });
  }

  function showError(message) {
    // Display error message to user
    console.error(message);

    // Create DOM elements for error notification
    const errorNotif = document.createElement('div');
    errorNotif.className = 'error-notification';
    errorNotif.textContent = message;

    document.body.appendChild(errorNotif);

    // Add animation classes
    setTimeout(() => {
      errorNotif.classList.add('show');

      // Remove after animation
      setTimeout(() => {
        errorNotif.classList.remove('show');
        setTimeout(() => {
          document.body.removeChild(errorNotif);
        }, 500);
      }, 3000);
    }, 10);
  }

  // Game-specific animation functions
  function animateWheel(wheelPosition, segmentIndex) {
    // Implementation for wheel spinning animation
    console.log(`Wheel spinning to position ${wheelPosition} (segment ${segmentIndex})`);

    // Create wheel animation - this would be more complex in production
    const wheel = document.querySelector('.wheel-container .wheel');
    if (wheel) {
      // Calculate total rotation (typically multiple spins + final position)
      const rotations = 5; // Number of full rotations
      const finalRotation = rotations * 360 + wheelPosition;

      // Apply the rotation with CSS
      wheel.style.transition = 'transform 3s cubic-bezier(0.25, 1, 0.5, 1)';
      wheel.style.transform = `rotate(${finalRotation}deg)`;

      // Return animation duration
      return 3000; // 3 seconds
    }

    return 0;
  }

  function animateCoinFlips(flips, callback) {
    // Implementation for coin flip animations
    console.log(`Animating ${flips.length} coin flips`);

    const coinElement = document.querySelector('.coin');
    let flipIndex = 0;

    function doNextFlip() {
      if (flipIndex >= flips.length) {
        if (callback) callback();
        return;
      }

      const isWin = flips[flipIndex];

      // Reset coin state
      coinElement.classList.remove('heads', 'tails', 'flipping');

      // Force reflow to restart animation
      void coinElement.offsetWidth;

      // Start flip animation
      coinElement.classList.add('flipping');

      // After animation completes, show result
      setTimeout(() => {
        coinElement.classList.remove('flipping');
        coinElement.classList.add(isWin ? 'heads' : 'tails');

        // Delay before next flip
        setTimeout(() => {
          flipIndex++;
          doNextFlip();
        }, 1000);
      }, 1500);
    }

    // Start the first flip
    doNextFlip();
  }

  function animateNumberReveal(target, guess, callback) {
    // Implementation for number reveal animation
    console.log(`Revealing target number ${target} (guess was ${guess})`);

    const numberDisplay = document.querySelector('.number-display');
    const difference = Math.abs(target - guess);

    // Create animation for number reveal
    let dots = '';
    let counter = 0;

    // Show thinking animation
    const thinkingInterval = setInterval(() => {
      dots = '.'.repeat(counter % 4);
      numberDisplay.textContent = `Searching${dots}`;
      counter++;
    }, 300);

    // After delay, reveal the number
    setTimeout(() => {
      clearInterval(thinkingInterval);

      // Rapid count animation to target
      let currentNum = guess;
      const direction = target > guess ? 1 : -1;
      const countInterval = setInterval(() => {
        numberDisplay.textContent = currentNum;

        if (currentNum === target) {
          clearInterval(countInterval);

          // Add visual feedback based on how close the guess was
          if (difference === 0) {
            numberDisplay.classList.add('perfect-match');
          } else if (difference <= 5) {
            numberDisplay.classList.add('close-match');
          } else {
            numberDisplay.classList.add('far-match');
          }

          // Call callback after animation completes
          setTimeout(() => {
            if (callback) callback();
          }, 1000);
        }

        currentNum += direction;
      }, 50);
    }, 1500);
  }

  function showCrashGameUI(betAmount, autoCashout) {
    // Implementation for crash game UI
    console.log(`Setting up crash game UI with bet $${betAmount} and auto-cashout at ${autoCashout}x`);

    // Create or reset UI elements
    const crashGameUI = document.querySelector('.crash-game-container');
    crashGameUI.innerHTML = `
        <div class="crash-graph">
            <canvas id="crash-chart"></canvas>
        </div>
        <div class="crash-multiplier">1.00x</div>
        <div class="crash-controls">
            <button class="cashout-button">Cash Out ($${(betAmount * 1).toFixed(2)})</button>
            <div class="auto-cashout">${autoCashout ? `Auto-cashout at ${autoCashout}x` : 'No auto-cashout'}</div>
        </div>
    `;

    // Initialize graph (would use a proper charting library in production)
    initCrashGraph();
  }

  function updateMultiplierDisplay(multiplier) {
    // Update multiplier display in crash game
    const multiplierElement = document.querySelector('.crash-multiplier');
    if (multiplierElement) {
      multiplierElement.textContent = `${multiplier.toFixed(2)}x`;

      // Update cashout button amount
      const betAmount = parseFloat(document.querySelector('[name="bet_amount"]').value);
      const cashoutButton = document.querySelector('.cashout-button');
      if (cashoutButton) {
        cashoutButton.textContent = `Cash Out ($${(betAmount * multiplier).toFixed(2)})`;
      }

      // Update graph
      updateCrashGraph(multiplier);
    }
  }

  function showCrashAnimation() {
    // Implement crash animation
    console.log('Crash!');

    const crashGameUI = document.querySelector('.crash-game-container');

    // Add crash visual effect
    crashGameUI.classList.add('crashed');

    // Add crash text
    const crashText = document.createElement('div');
    crashText.className = 'crash-text';
    crashText.textContent = 'CRASHED!';
    crashGameUI.appendChild(crashText);

    // Shake effect
    const shakeDuration = 500;
    const shakeStart = Date.now();
    const shakeInterval = setInterval(() => {
      const elapsed = Date.now() - shakeStart;
      if (elapsed >= shakeDuration) {
        clearInterval(shakeInterval);
        crashGameUI.style.transform = 'none';
        return;
      }

      const intensity = 10 * (1 - elapsed / shakeDuration);
      const x = Math.random() * intensity - intensity / 2;
      const y = Math.random() * intensity - intensity / 2;
      crashGameUI.style.transform = `translate(${x}px, ${y}px)`;
    }, 20);
  }

  // Graph functions for crash game
  function initCrashGraph() {
    // Initialize the crash graph (simplified version)
    const canvas = document.getElementById('crash-chart');
    const ctx = canvas.getContext('2d');

    // Set canvas dimensions
    canvas.width = canvas.parentElement.clientWidth;
    canvas.height = canvas.parentElement.clientHeight;

    // Clear canvas
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    // Draw initial state (1.00x)
    ctx.beginPath();
    ctx.moveTo(0, canvas.height);
    ctx.lineTo(0, canvas.height - 1);
    ctx.strokeStyle = '#4CAF50';
    ctx.lineWidth = 2;
    ctx.stroke();

    // Store initial graph point
    window.crashGraphPoints = [{
      x: 0,
      y: canvas.height - 1
    }];
  }

  function updateCrashGraph(multiplier) {
    const canvas = document.getElementById('crash-chart');
    if (!canvas) return;

    const ctx = canvas.getContext('2d');
    const points = window.crashGraphPoints;

    // Calculate new point
    const lastPoint = points[points.length - 1];
    const newX = lastPoint.x + 2;

    // Exponential curve (higher multipliers rise faster)
    // Map multiplier to y position (1.00x = bottom, higher = toward top)
    const maxHeight = canvas.height - 10;
    const scaleFactor = 20; // Adjust based on desired curve steepness
    const newY = canvas.height - (Math.log(multiplier) * scaleFactor);

    // Add new point
    points.push({
      x: newX,
      y: newY
    });

    // If graph reaches right edge, shift all points left
    if (newX > canvas.width) {
      const shiftAmount = 10;
      for (let i = 0; i < points.length; i++) {
        points[i].x -= shiftAmount;
      }
      // Remove points that are now off-screen
      while (points.length > 0 && points[0].x < 0) {
        points.shift();
      }
    }

    // Clear canvas and redraw all points
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    // Draw graph line
    ctx.beginPath();
    ctx.moveTo(points[0].x, points[0].y);

    for (let i = 1; i < points.length; i++) {
      ctx.lineTo(points[i].x, points[i].y);
    }

    ctx.strokeStyle = '#4CAF50';
    ctx.lineWidth = 2;
    ctx.stroke();
  }

  // Add styles for the betting games
  document.addEventListener('DOMContentLoaded', function() {
    const style = document.createElement('style');
    style.textContent = `
        /* Betting Games Styles */
        .betting-games-container {
            font-family: 'Poppins', Arial, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .balance-container {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            padding: 15px;
            background: linear-gradient(145deg, #1a237e, #283593);
            border-radius: 10px;
            color: white;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .balance-item {
            text-align: center;
        }
        
        .balance-label {
            font-size: 14px;
            opacity: 0.8;
        }
        
        .balance-value {
            font-size: 24px;
            font-weight: 700;
            margin-top: 5px;
        }
        
        .balance-updated {
            animation: pulse 1s ease-in-out;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); color: #FFEB3B; }
        }
        
        .games-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .game-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .game-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 20px rgba(0,0,0,0.15);
        }
        
        .game-header {
            padding: 15px;
            background: linear-gradient(145deg, #3949ab, #5c6bc0);
            color: white;
        }
        
        .game-title {
            font-size: 18px;
            font-weight: 600;
            margin: 0;
        }
        
        .game-body {
            padding: 20px;
        }
        
        .game-description {
            color: #555;
            font-size: 14px;
            margin-bottom: 15px;
            min-height: 60px;
        }
        
        .bet-controls {
            margin: 15px 0;
        }
        
        .bet-input {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .bet-input input {
            flex: 1;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 16px;
        }
        
        .bet-input button {
            background: none;
            border: none;
            color: #555;
            font-size: 18px;
            cursor: pointer;
            padding: 8px;
        }
        
        .bet-buttons {
            display: flex;
            gap: 10px;
        }
        
        .bet-preset {
            flex: 1;
            padding: 8px 0;
            background: #f0f0f0;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
            transition: background 0.2s;
        }
        
        .bet-preset:hover {
            background: #e0e0e0;
        }
        
        .play-button {
            width: 100%;
            padding: 12px;
            margin-top: 15px;
            background: linear-gradient(145deg, #4CAF50, #388E3C);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, background 0.2s;
        }
        
        .play-button:hover {
            transform: scale(1.02);
            background: linear-gradient(145deg, #43A047, #2E7D32);
        }
        
        .win-popup {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) scale(0.8);
            opacity: 0;
            background: linear-gradient(145deg, #673AB7, #9C27B0);
            color: white;
            padding: 30px;
            border-radius: 15px;
            text-align: center;
            z-index: 1000;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            transition: transform 0.5s, opacity 0.5s;
        }
        
        .win-popup.show {
            transform: translate(-50%, -50%) scale(1);
            opacity: 1;
        }
        
        .win-amount {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 10px;
            text-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }
        
        .win-multiplier {
            font-size: 20px;
            opacity: 0.9;
        }
        
        .loss-notification {
            position: fixed;
            top: 20%;
            left: 50%;
            transform: translate(-50%, -50%) scale(0.8);
            opacity: 0;
            background: #424242;
            color: white;
            padding: 15px 25px;
            border-radius: 30px;
            text-align: center;
            z-index: 999;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            transition: transform 0.3s, opacity 0.3s;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .loss-notification.show {
            transform: translate(-50%, -50%) scale(1);
            opacity: 0.95;
        }
        
        .error-notification {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%) translateY(100px);
            background: #f44336;
            color: white;
            padding: 12px 25px;
            border-radius: 5px;
            font-size: 14px;
            z-index: 1000;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transition: transform 0.3s;
        }
        
        .error-notification.show {
            transform: translateX(-50%) translateY(0);
        }
        
        /* Game-specific styles */
        
        /* Lucky Wheel */
        .wheel-container {
            position: relative;
            width: 240px;
            height: 240px;
            margin: 0 auto 20px;
            overflow: hidden;
        }
        
        .wheel {
            width: 100%;
            height: 100%;
            background: conic-gradient(
                #f44336 0deg 45deg,
                #9C27B0 45deg 90deg,
                #3F51B5 90deg 135deg,
                #03A9F4 135deg 180deg,
                #009688 180deg 225deg,
                #8BC34A 225deg 270deg,
                #FFEB3B 270deg 315deg,
                #FF9800 315deg 360deg
            );
            border-radius: 50%;
            position: relative;
            transition: transform 0.2s;
        }
        
        .wheel-pointer {
            position: absolute;
            top: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 20px;
            height: 30px;
            background: #263238;
            clip-path: polygon(50% 0%, 0% 100%, 100% 100%);
            z-index: 2;
        }
        
        /* Coin Flip */
        .coin-container {
            position: relative;
            width: 150px;
            height: 150px;
            margin: 0 auto 20px;
            perspective: 500px;
        }
        
        .coin {
            width: 100%;
            height: 100%;
            position: relative;
            transform-style: preserve-3d;
            transition: transform 1.5s ease-out;
        }
        
        .coin.flipping {
            animation: flip 1.5s ease-out forwards;
        }
        
        @keyframes flip {
            0% { transform: rotateY(0); }
            100% { transform: rotateY(1800deg); }
        }
        
        .coin-side {
            position: absolute;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            backface-visibility: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: bold;
        }
        
        .coin-heads {
            background: linear-gradient(145deg, #FFD700, #FFC107);
            box-shadow: inset 0 0 10px rgba(0,0,0,0.3);
        }
        
        .coin-tails {
            background: linear-gradient(145deg, #C0C0C0, #9E9E9E);
            transform: rotateY(180deg);
            box-shadow: inset 0 0 10px rgba(0,0,0,0.3);
        }
        
        .coin.heads .coin-tails {
            transform: rotateY(180deg);
        }
        
        .coin.tails {
            transform: rotateY(180deg);
        }
        
        /* Hidden Number */
        .number-game-container {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .number-display {
            font-size: 32px;
            font-weight: bold;
            margin: 20px 0;
            min-height: 40px;
            transition: color 0.3s;
        }
        
        .number-display.perfect-match {
            color: #4CAF50;
            animation: scale-bounce 0.5s ease-in-out;
        }
        
        .number-display.close-match {
            color: #2196F3;
        }
        
        .number-display.far-match {
            color: #F44336;
        }
        
        @keyframes scale-bounce {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.2); }
        }
        
        .number-input {
            display: flex;
            margin: 0 auto;
            max-width: 200px;
        }
        
        .number-input input {
            flex: 1;
            padding: 10px;
            font-size: 18px;
            border: 1px solid #ccc;
            border-radius: 5px 0 0 5px;
            text-align: center;
        }
        
        .number-input button {
            padding: 10px 15px;
            background: #2196F3;
            color: white;
            border: none;
            border-radius: 0 5px 5px 0;
            cursor: pointer;
        }
        
        /* Crash Game */
        .crash-game-container {
            position: relative;
            background: #1E1E2F;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            color: white;
            transition: transform 0.05s;
        }
        
        .crash-game-container.crashed {
            background: #B71C1C;
        }
        
        .crash-graph {
            width: 100%;
            height: 200px;
            margin-bottom: 15px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .crash-multiplier {
            font-size: 36px;
            font-weight: 700;
            text-align: center;
            margin-bottom: 15px;
        }
        
        .crash-controls {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .cashout-button {
            padding: 12px;
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .cashout-button:hover {
            background: #43A047;
        }
        
        .auto-cashout {
            text-align: center;
            font-size: 14px;
            opacity: 0.7;
        }
        
        .crash-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 48px;
            font-weight: 900;
            color: white;
            text-shadow: 0 0 10px rgba(0,0,0,0.5);
            letter-spacing: 2px;
        }
        
        /* Recent Activity Section */
        .activity-section {
            margin-top: 30px;
        }
        
        .activity-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .activity-title {
            font-size: 20px;
            font-weight: 600;
            color: #333;
        }
        
        .activity-tabs {
            display: flex;
        }
        
        .activity-tab {
            padding: 8px 15px;
            background: none;
            border: none;
            border-bottom: 2px solid transparent;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s;
        }
        
        .activity-tab.active {
            border-bottom-color: #3F51B5;
            color: #3F51B5;
            font-weight: 500;
        }
        
        .activity-content {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        
        .activity-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .activity-table th {
            background: #f5f5f5;
            padding: 12px 15px;
            text-align: left;
            font-weight: 500;
            color: #555;
            font-size: 14px;
        }
        
        .activity-table td {
            padding: 12px 15px;
            border-top: 1px solid #eee;
            font-size: 14px;
        }
        
        .activity-table tr:hover td {
            background: #f9f9f9;
        }
        
        .activity-amount {
            font-weight: 500;
        }
        
        .activity-amount.win {
            color: #4CAF50;
        }
        
        .activity-amount.loss {
            color: #F44336;
        }
        
        /* Bonus Banner */
        .bonus-banner {
            background: linear-gradient(145deg, #FF9800, #F57C00);
            color: white;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        
        .bonus-info {
            flex: 1;
        }
        
        .bonus-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .bonus-description {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .bonus-action {
            margin-left: 20px;
        }
        
        .bonus-button {
            padding: 8px 15px;
            background: white;
            color: #FF9800;
            border: none;
            border-radius: 5px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .bonus-button:hover {
            transform: scale(1.05);
        }
        
        /* Responsive Styles */
        @media (max-width: 768px) {
            .games-grid {
                grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            }
            
            .balance-container {
                flex-direction: column;
                gap: 10px;
            }
            
            .activity-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .activity-table th:nth-child(3),
            .activity-table td:nth-child(3) {
                display: none;
            }
        }
    `;
    document.head.appendChild(style);
  });
</script>

<!-- HTML structure for the betting games page -->
<div class="betting-games-container">
  <!-- Balance display -->
  <div class="balance-container">
    <div class="balance-item">
      <div class="balance-label">Wallet Balance</div>
      <div id="real-balance" class="balance-value">$<?php echo number_format($balance, 2); ?></div>
    </div>
    <div class="balance-item">
      <div class="balance-label">Bonus Balance</div>
      <div id="bonus-balance" class="balance-value">$<?php echo number_format($bonusBalance, 2); ?></div>
      <?php if (isset($wageringRemaining) && $wageringRemaining > 0): ?>
        <div class="balance-note">Wager $<?php echo number_format($wageringRemaining, 2); ?> more to withdraw</div>
      <?php endif; ?>
    </div>
    <div class="balance-item">
      <div class="balance-label">Total Bets</div>
      <div class="balance-value"><?php echo number_format($total_bets); ?></div>
    </div>
    <?php if (isset($win_rate)): ?>
      <div class="balance-item">
        <div class="balance-label">Win Rate</div>
        <div class="balance-value"><?php echo number_format($win_rate, 1); ?>%</div>
      </div>
    <?php endif; ?>
  </div>

  <!-- Welcome bonus banner (show only for new users) -->
  <?php if (isset($showWelcomeBonus) && $showWelcomeBonus): ?>
    <div class="bonus-banner">
      <div class="bonus-info">
        <div class="bonus-title">Welcome Bonus: $<?php echo number_format($bonusBalance, 2); ?></div>
        <div class="bonus-description">Play with your bonus and unlock real withdrawals after wagering <?php echo $wageringRequirement / $bonusBalance; ?>x the bonus amount.</div>
      </div>
      <div class="bonus-action">
        <button class="bonus-button">Start Playing</button>
      </div>
    </div>
  <?php endif; ?>

  <!-- Games grid -->
  <div class="games-grid">
    <?php foreach ($games as $game): ?>
      <div class="game-card" data-game-id="<?php echo $game['id']; ?>">
        <div class="game-header">
          <h3 class="game-title"><?php echo $game['game_name']; ?></h3>
        </div>
        <div class="game-body">
          <p class="game-description"><?php echo $game['description']; ?></p>

          <?php if ($game['game_name'] == 'Lucky Wheel'): ?>
            <!-- Lucky Wheel Game UI -->
            <div class="wheel-container">
              <div class="wheel"></div>
              <div class="wheel-pointer"></div>
            </div>
          <?php elseif ($game['game_name'] == 'Coin Flip Streak'): ?>
            <!-- Coin Flip Game UI -->
            <div class="coin-container">
              <div class="coin">
                <div class="coin-side coin-heads">H</div>
                <div class="coin-side coin-tails">T</div>
              </div>
            </div>
          <?php elseif ($game['game_name'] == 'Hidden Number'): ?>
            <!-- Hidden Number Game UI -->
            <div class="number-game-container">
              <div class="number-display">Guess 1-100</div>
              <div class="number-input">
                <input type="number" min="1" max="100" placeholder="Your guess" class="guess-input">
                <button class="guess-button">Guess</button>
              </div>
            </div>
          <?php elseif ($game['game_name'] == 'Crash Game'): ?>
            <!-- Crash Game UI -->
            <div class="crash-game-container">
              <div class="crash-graph">
                <canvas id="crash-chart"></canvas>
              </div>
              <div class="crash-multiplier">1.00x</div>
              <div class="crash-controls">
                <button class="cashout-button">Cash Out ($0.00)</button>
                <div class="auto-cashout">Set auto-cashout below</div>
              </div>
            </div>
          <?php endif; ?>

          <div class="bet-controls">
            <div class="bet-input">
              <button class="bet-decrease">-</button>
              <input type="number" min="<?php echo $game['min_bet']; ?>" max="<?php echo $game['max_bet']; ?>" value="<?php echo $game['min_bet']; ?>" name="bet_amount" class="bet-amount">
              <button class="bet-increase">+</button>
            </div>
            <div class="bet-buttons">
              <button class="bet-preset" data-amount="<?php echo $game['min_bet']; ?>">Min</button>
              <button class="bet-preset" data-amount="5">$5</button>
              <button class="bet-preset" data-amount="10">$10</button>
              <button class="bet-preset" data-amount="<?php echo $game['max_bet']; ?>">Max</button>
            </div>

            <?php if ($game['game_name'] == 'Crash Game'): ?>
              <div class="auto-cashout-control">
                <input type="number" min="1.1" max="10" step="0.1" value="2.0" name="auto_cashout" placeholder="Auto cash out at">
              </div>
            <?php endif; ?>
          </div>

          <button class="play-button">Play Now</button>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Recent activity section -->
  <div class="activity-section">
    <div class="activity-header">
      <h3 class="activity-title">Betting Activity</h3>
      <div class="activity-tabs">
        <button class="activity-tab active" data-tab="all">All Activity</button>
        <button class="activity-tab" data-tab="my">My Bets</button>
      </div>
    </div>

    <div class="activity-content" id="all-activity">
      <table class="activity-table">
        <thead>
          <tr>
            <th>Player</th>
            <th>Game</th>
            <th>Time</th>
            <th>Bet Amount</th>
            <th>Outcome</th>
          </tr>
        </thead>
        <tbody>
          <!-- All activity table output -->
        <tbody>
          <?php foreach ($recent_bets as $bet): ?>
            <tr>
              <td><?php echo $bet['full_name']; ?></td>
              <td><?php echo $bet['game_name']; ?></td>
              <td><?php echo date('M d, h:i A', strtotime($bet['bet_time'])); ?></td>
              <td>$<?php echo number_format($bet['bet_amount'], 2); ?></td>
              <td class="activity-amount <?php echo $bet['outcome'] > 0 ? 'win' : 'loss'; ?>">
                <?php echo $bet['outcome'] > 0 ? '+$' . number_format($bet['outcome'], 2) : '-$' . number_format($bet['bet_amount'], 2); ?>
              </td>
            </tr>
          <?php endforeach; ?>

          <?php if (empty($recent_bets)): ?>
            <tr>
              <td colspan="5" style="text-align: center;">No recent activity</td>
            </tr>
          <?php endif; ?>
        </tbody>

        </tbody>
      </table>
    </div>

    <div class="activity-content" id="my-activity" style="display: none;">
      <table class="activity-table">
        <thead>
          <tr>
            <th>Game</th>
            <th>Time</th>
            <th>Bet Amount</th>
            <th>Outcome</th>
          </tr>
        </thead>
        <!-- My activity table output -->
        <tbody>
          <?php foreach ($user_history as $bet): ?>
            <tr>
              <td><?php echo $bet['game_name']; ?></td>
              <td><?php echo date('M d, h:i A', strtotime($bet['bet_time'])); ?></td>
              <td>$<?php echo number_format($bet['bet_amount'], 2); ?></td>
              <td class="activity-amount <?php echo $bet['outcome'] > 0 ? 'win' : 'loss'; ?>">
                <?php echo $bet['outcome'] > 0 ? '+$' . number_format($bet['outcome'], 2) : '-$' . number_format($bet['bet_amount'], 2); ?>
              </td>
            </tr>
          <?php endforeach; ?>

          <?php if (empty($user_history)): ?>
            <tr>
              <td colspan="4" style="text-align: center;">You haven't placed any bets yet</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
  // Main JavaScript for page functionality
  document.addEventListener('DOMContentLoaded', function() {
    // Tab switching
    const tabButtons = document.querySelectorAll('.activity-tab');
    tabButtons.forEach(button => {
      button.addEventListener('click', function() {
        // Remove active class from all tabs
        tabButtons.forEach(btn => btn.classList.remove('active'));

        // Add active class to clicked tab
        this.classList.add('active');

        // Show corresponding content
        const tabName = this.dataset.tab;
        document.getElementById('all-activity').style.display = tabName === 'all' ? 'block' : 'none';
        document.getElementById('my-activity').style.display = tabName === 'my' ? 'block' : 'none';
      });
    });

    // Bet amount controls
    const betDecreaseButtons = document.querySelectorAll('.bet-decrease');
    const betIncreaseButtons = document.querySelectorAll('.bet-increase');
    const betPresetButtons = document.querySelectorAll('.bet-preset');

    betDecreaseButtons.forEach(button => {
      button.addEventListener('click', function() {
        const input = this.nextElementSibling;
        let value = parseFloat(input.value);
        value = Math.max(parseFloat(input.min), value - 1);
        input.value = value.toFixed(2);

        // Update cash out button in crash game
        updateCashoutButton(this.closest('.game-card'));
      });
    });

    betIncreaseButtons.forEach(button => {
      button.addEventListener('click', function() {
        const input = this.previousElementSibling;
        let value = parseFloat(input.value);
        value = Math.min(parseFloat(input.max), value + 1);
        input.value = value.toFixed(2);

        // Update cash out button in crash game
        updateCashoutButton(this.closest('.game-card'));
      });
    });

    betPresetButtons.forEach(button => {
      button.addEventListener('click', function() {
        const amount = parseFloat(this.dataset.amount);
        const input = this.closest('.bet-controls').querySelector('.bet-amount');
        input.value = amount.toFixed(2);

        // Update cash out button in crash game
        updateCashoutButton(this.closest('.game-card'));
      });
    });

    // Play buttons
    const playButtons = document.querySelectorAll('.play-button');

    playButtons.forEach(button => {
      button.addEventListener('click', function() {
        const gameCard = this.closest('.game-card');
        const gameId = gameCard.dataset.gameId;
        const gameName = gameCard.querySelector('.game-title').textContent;
        const betAmount = parseFloat(gameCard.querySelector('.bet-amount').value);

        // Validate bet amount
        if (isNaN(betAmount) || betAmount <= 0) {
          showError('Please enter a valid bet amount');
          return;
        }

        // Disable play button during gameplay
        this.disabled = true;
        this.textContent = 'Playing...';

        // Play the corresponding game
        if (gameName.includes('Lucky Wheel')) {
          playLuckyWheel(gameId, betAmount)
            .finally(() => {
              this.disabled = false;
              this.textContent = 'Play Again';
            });
        } else if (gameName.includes('Coin Flip')) {
          playCoinFlip(gameId, betAmount)
            .finally(() => {
              this.disabled = false;
              this.textContent = 'Play Again';
            });
        } else if (gameName.includes('Hidden Number')) {
          const guess = parseInt(gameCard.querySelector('.guess-input').value);

          if (isNaN(guess) || guess < 1 || guess > 100) {
            showError('Please enter a valid number between 1 and 100');
            this.disabled = false;
            this.textContent = 'Play Now';
            return;
          }

          playHiddenNumber(gameId, betAmount, guess)
            .finally(() => {
              this.disabled = false;
              this.textContent = 'Play Again';
            });
        } else if (gameName.includes('Crash')) {
          const autoCashout = parseFloat(gameCard.querySelector('[name="auto_cashout"]').value);

          // Valid auto-cashout value
          if (autoCashout && (isNaN(autoCashout) || autoCashout < 1.1)) {
            showError('Auto-cashout must be at least 1.1x');
            this.disabled = false;
            this.textContent = 'Play Now';
            return;
          }

          // Create crash game and get controller
          const crashController = playCrashGame(gameId, betAmount, autoCashout);

          // Set up cashout button
          const cashoutButton = gameCard.querySelector('.cashout-button');
          cashoutButton.addEventListener('click', function() {
            if (crashController && typeof crashController.cashout === 'function') {
              crashController.cashout();

              // Disable cashout button after use
              this.disabled = true;
              this.textContent = 'Cashed Out!';
            }
          });

          // After game completion
          setTimeout(() => {
            this.disabled = false;
            this.textContent = 'Play Again';
            cashoutButton.disabled = false;
            cashoutButton.textContent = `Cash Out ($${betAmount.toFixed(2)})`;
          }, 10000); // Timeout for crash game round
        }
      });
    });

    // Helper function to update cashout button
    function updateCashoutButton(gameCard) {
      const cashoutButton = gameCard.querySelector('.cashout-button');
      if (cashoutButton) {
        const betAmount = parseFloat(gameCard.querySelector('.bet-amount').value) || 0;
        cashoutButton.textContent = `Cash Out ($${betAmount.toFixed(2)})`;
      }
    }

    // Initialize wheels
    const wheels = document.querySelectorAll('.wheel');
    wheels.forEach(wheel => {
      // Create wheel segments
      const segmentCount = 8;
      const segmentSize = 360 / segmentCount;

      // Create wheel segments with colors and labels
      const segments = [{
          color: '#F44336',
          text: '0x',
          win: false
        },
        {
          color: '#E91E63',
          text: '1.5x',
          win: true
        },
        {
          color: '#9C27B0',
          text: '0x',
          win: false
        },
        {
          color: '#673AB7',
          text: '2x',
          win: true
        },
        {
          color: '#3F51B5',
          text: '0x',
          win: false
        },
        {
          color: '#2196F3',
          text: '3x',
          win: true
        },
        {
          color: '#03A9F4',
          text: '0x',
          win: false
        },
        {
          color: '#00BCD4',
          text: '5x',
          win: true
        }
      ];

      // Generate CSS for wheel segments
      let conicGradient = 'conic-gradient(';

      segments.forEach((segment, index) => {
        const startAngle = index * segmentSize;
        const endAngle = (index + 1) * segmentSize;
        conicGradient += `${segment.color} ${startAngle}deg ${endAngle}deg${index < segments.length - 1 ? ',' : ''}`;
      });

      conicGradient += ')';
      wheel.style.background = conicGradient;

      // Add segment labels
      segments.forEach((segment, index) => {
        const label = document.createElement('div');
        label.className = 'wheel-segment-label';
        label.textContent = segment.text;

        // Position the label
        const angle = index * segmentSize + segmentSize / 2;
        const radius = wheel.clientWidth / 2 * 0.7; // 70% of radius for label placement

        // Convert angle to radians and calculate position
        const radians = (angle - 90) * Math.PI / 180;
        const x = radius * Math.cos(radians);
        const y = radius * Math.sin(radians);

        // Center in the wheel and adjust based on calculated position
        label.style.left = `calc(50% + ${x}px)`;
        label.style.top = `calc(50% + ${y}px)`;
        label.style.transform = 'translate(-50%, -50%)';

        wheel.appendChild(label);
      });
    });

    // AJAX setup to handle the betting games responses
    $(document).ajaxSetup({
      error: function(xhr, status, error) {
        console.error('AJAX Error:', status, error);
        showError('An error occurred. Please try again.');

        // Re-enable play buttons
        $(".play-button").prop('disabled', false).text('Play Now');
      }
    });

    // Handle bonus button click
    const bonusButton = document.querySelector('.bonus-button');
    if (bonusButton) {
      bonusButton.addEventListener('click', function() {
        // Scroll to first game
        const firstGame = document.querySelector('.game-card');
        if (firstGame) {
          firstGame.scrollIntoView({
            behavior: 'smooth'
          });
        }
      });
    }
  });

  // Additional AJAX endpoint for getting current balance
  function getBalance() {
    return $.ajax({
      url: 'betting-games.php',
      type: 'POST',
      data: {
        action: 'get_balance'
      },
      dataType: 'json'
    });
  }

  // Function to reload activity tables
  function reloadActivity() {
    $.ajax({
      url: 'betting-games.php',
      type: 'POST',
      data: {
        action: 'get_activity'
      },
      dataType: 'json',
      success: function(response) {
        if (response.success) {
          // Update all activity table
          updateActivityTable('#all-activity tbody', response.all_activity);

          // Update my activity table
          updateActivityTable('#my-activity tbody', response.my_activity);
        }
      }
    });
  }

  // Helper to update activity tables
  function updateActivityTable(selector, data) {
    const tbody = document.querySelector(selector);
    if (!tbody) return;

    let html = '';

    if (data.length === 0) {
      html = `<tr><td colspan="5" style="text-align: center;">No activity to display</td></tr>`;
    } else {
      data.forEach(item => {
        const isWin = parseFloat(item.outcome) > 0;
        const outcomeDisplay = isWin ?
          `+${parseFloat(item.outcome).toFixed(2)}` :
          `-${parseFloat(item.bet_amount).toFixed(2)}`;

        html += `
                <tr>
                    ${item.full_name ? `<td>${item.full_name}</td>` : ''}
                    <td>${item.game_name}</td>
                    <td>${new Date(item.bet_time).toLocaleString()}</td>
                    <td>$${parseFloat(item.bet_amount).toFixed(2)}</td>
                    <td class="activity-amount ${isWin ? 'win' : 'loss'}">${outcomeDisplay}</td>
                </tr>
            `;
      });
    }

    tbody.innerHTML = html;
  }
</script>

<?php
// Add these functions to your betting-games.php file

// Get user's wallet balance
function getUserBalance($userId)
{
  global $conn;
  $sql = "SELECT balance FROM wallets WHERE user_id = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("i", $userId);
  $stmt->execute();
  $result = $stmt->get_result();
  if ($result->num_rows > 0) {
    $data = $result->fetch_assoc();
    return $data['balance'];
  }
  return 0;
}

// Get user's bonus balance
function getUserBonusBalance($userId)
{
  global $conn;
  $sql = "SELECT remaining_amount FROM user_bonuses WHERE user_id = ? AND is_active = 1 AND remaining_amount > 0 AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("i", $userId);
  $stmt->execute();
  $result = $stmt->get_result();
  if ($result->num_rows > 0) {
    $data = $result->fetch_assoc();
    return $data['remaining_amount'];
  }
  return 0;
}

// Process a bet
function processBet($userId, $gameId, $betAmount, $gameParams = [])
{
  global $conn;
  // Begin transaction
  $conn->begin_transaction();
  try {
    // Check if user has sufficient balance
    $userBalance = getUserBalance($userId);
    $bonusBalance = getUserBonusBalance($userId);

    // Determine if bet should use bonus or real money
    $useBonusForBet = ($bonusBalance >= $betAmount);
    $balanceToUse = $useBonusForBet ? $bonusBalance : $userBalance;
    if ($balanceToUse < $betAmount) {
      $conn->rollback();
      return ["success" => false, "message" => "Insufficient funds"];
    }

    // Generate game outcome
    $outcome = generateGameOutcome($gameId, $betAmount, $userId);
    $won = $outcome['won'];
    $winAmount = $outcome['amount'];

    // Record bet
    $sql = "INSERT INTO user_bets (user_id, game_id, bet_amount, potential_win, outcome, is_bonus_bet) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $potentialWin = $won ? $winAmount : 0;
    $isBonusBet = $useBonusForBet ? 1 : 0;
    $stmt->bind_param("iidddi", $userId, $gameId, $betAmount, $potentialWin, $won ? $winAmount : 0, $isBonusBet);
    $stmt->execute();

    // Update user balance
    if ($useBonusForBet) {
      // Update bonus balance and wagering progress
      updateUserBonusBalance($userId, -$betAmount);
      updateUserWageringProgress($userId, $betAmount);
      if ($won) {
        // Winnings go to real balance
        updateUserBalance($userId, $winAmount);
      }
    } else {
      // Real money bet
      updateUserBalance($userId, -$betAmount);
      if ($won) {
        updateUserBalance($userId, $winAmount);
      }
    }

    // Update user statistics
    updateUserWinStreak($userId, $won);

    // Commit transaction
    $conn->commit();
    return [
      "success" => true,
      "won" => $won,
      "amount" => $won ? $winAmount : 0,
      "used_bonus" => $useBonusForBet,
      "game_data" => $outcome
    ];
  } catch (Exception $e) {
    $conn->rollback();
    return ["success" => false, "message" => "Error: " . $e->getMessage()];
  }
}

// Generate game outcome with house edge
function generateGameOutcome($gameId, $betAmount, $userId)
{
  global $conn;
  // Get user's history to adjust difficulty
  $userStats = getUserBettingStats($userId);
  $totalBets = $userStats['total_bets'] ?? 0;
  $consecutiveWins = $userStats['consecutive_wins'] ?? 0;
  $totalLoss = $userStats['net_loss'] ?? 0;

  // Get game parameters
  $game = getGameDetails($gameId);
  $baseHouseEdge = $game['house_edge'];

  // Adjust house edge based on user history
  $adjustedHouseEdge = $baseHouseEdge;

  // If user is on a winning streak, make it harder to win
  if ($consecutiveWins > 2) {
    $adjustedHouseEdge *= (1 + $consecutiveWins * 0.05);
  }

  // If user has lost a lot, occasionally give a win to keep engagement
  if ($totalLoss > 50 && $totalBets > 10 && rand(1, 20) == 1) {
    $adjustedHouseEdge *= 0.7;
  }

  // For new users, slightly reduce house edge to encourage continued play
  if ($totalBets < 5) {
    $adjustedHouseEdge *= 0.9;
  }

  // Ensure house edge stays reasonable
  $adjustedHouseEdge = min(0.40, max(0.05, $adjustedHouseEdge));

  // Generate outcome based on game type and adjusted house edge
  $gameType = strtolower($game['game_name']);
  switch ($gameType) {
    case 'lucky wheel':
      return generateWheelOutcome($betAmount, $adjustedHouseEdge);
    case 'coin flip streak':
      return generateCoinFlipOutcome($betAmount, $adjustedHouseEdge);
    case 'hidden number':
      return generateHiddenNumberOutcome($betAmount, $adjustedHouseEdge);
    case 'crash game':
      return generateCrashGameOutcome($betAmount, $adjustedHouseEdge);
    default:
      // Generic outcome
      $winChance = (1 - $adjustedHouseEdge) * 100;
      $roll = mt_rand(1, 10000) / 100; // More precision
      if ($roll <= $winChance) {
        // Win - multiplier between 1.2x and 2.0x
        $multiplier = 1.2 + (mt_rand(0, 80) / 100);
        $winAmount = $betAmount * $multiplier;
        return ['won' => true, 'amount' => $winAmount, 'multiplier' => $multiplier];
      } else {
        // Loss
        return ['won' => false, 'amount' => 0, 'multiplier' => 0];
      }
  }
}
// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Check if this is an AJAX request
  if (isset($_POST['action'])) {
    header('Content-Type: application/json');

    switch ($_POST['action']) {
      case 'get_balance':
        // Return user balance information
        $response = [
          'success' => true,
          'balance' => getUserBalance($user_id),
          'bonus_balance' => getUserBonusBalance($user_id)
        ];
        echo json_encode($response);
        exit;

      case 'get_activity':
        // Get recent activity for all users
        $all_activity = [];
        $all_activity_sql = "SELECT ub.*, bg.game_name, u.full_name FROM user_bets ub JOIN betting_games bg ON ub.game_id = bg.id JOIN users u ON ub.user_id = u.id ORDER BY ub.bet_time DESC LIMIT 10";
        $all_result = $conn->query($all_activity_sql);
        if ($all_result && $all_result->num_rows > 0) {
          while ($row = $all_result->fetch_assoc()) {
            $all_activity[] = $row;
          }
        }

        // Get user's activity
        $my_activity = [];
        $my_activity_sql = "SELECT ub.*, bg.game_name FROM user_bets ub JOIN betting_games bg ON ub.game_id = bg.id WHERE ub.user_id = ? ORDER BY ub.bet_time DESC LIMIT 10";
        $stmt = $conn->prepare($my_activity_sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $my_result = $stmt->get_result();
        while ($row = $my_result->fetch_assoc()) {
          $my_activity[] = $row;
        }
        $stmt->close();

        $response = [
          'success' => true,
          'all_activity' => $all_activity,
          'my_activity' => $my_activity
        ];
        echo json_encode($response);
        exit;

      case 'crash_cashout':
        // Handle manual cashout for crash game
        $gameId = isset($_POST['game_id']) ? intval($_POST['game_id']) : 0;
        $betAmount = isset($_POST['bet_amount']) ? floatval($_POST['bet_amount']) : 0;
        $multiplier = isset($_POST['multiplier']) ? floatval($_POST['multiplier']) : 0;

        if ($gameId <= 0 || $betAmount <= 0 || $multiplier <= 1) {
          echo json_encode(['success' => false, 'message' => 'Invalid cashout parameters']);
          exit;
        }

        // Calculate winnings
        $winnings = $betAmount * $multiplier;

        // Record win
        $sql = "INSERT INTO user_bets (user_id, game_id, bet_amount, potential_win, outcome) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiddd", $user_id, $gameId, $betAmount, $winnings, $winnings);
        $stmt->execute();

        // Update user balance
        updateUserBalance($user_id, $winnings);

        echo json_encode([
          'success' => true,
          'won' => true,
          'amount' => $winnings,
          'multiplier' => $multiplier
        ]);
        exit;
    }
  }
}
?>