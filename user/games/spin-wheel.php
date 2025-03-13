<?php include 'top-php/spin-wheels.php'; ?>

<!DOCTYPE html>
<html lang="en">

<head>
  <?php include '../../includes/head.php'; ?>
  <script src="https://unpkg.com/@tailwindcss/browser@4"></script>

  <title>Spin & Win - AutoProftX</title>
  <style>
    /* Responsive Wheel Container - Adjusted size for better visibility */
    body {
      overflow-x: hidden;
    }

    .wheel-container {
      position: relative;
      width: min(300px, 90vw);
      height: min(300px, 90vw);
      margin: 0 auto 1.5rem auto;
    }

    .wheel {
      width: 100%;
      height: 100%;
      overflow-x: hidden;
      border-radius: 50%;
      background: conic-gradient(#e53e3e 0% 18.2%,
          #d69e2e 18.2% 31.8%,
          #38a169 31.8% 50%,
          #3182ce 50% 63.6%,
          #805ad5 63.6% 74.5%,
          #dd6b20 74.5% 81.8%,
          #0d9488 81.8% 86.4%,
          #6366f1 86.4% 89.1%,
          #7e22ce 89.1% 90.9%,
          #be123c 90.9% 91.8%,
          #fbbf24 91.8% 100%);
      position: relative;
      transition: transform 4s cubic-bezier(0.17, 0.67, 0.12, 0.99);
      box-shadow: 0 0 20px rgba(0, 0, 0, 0.5);
    }

    .wheel::before {
      content: "";
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      width: 20%;
      height: 20%;
      background-color: #1a202c;
      border-radius: 50%;
      z-index: 1;
      box-shadow: 0 0 10px rgba(0, 0, 0, 0.5);
    }

    /* Improved responsive pointer */
    .pointer {
      position: absolute;
      top: -4vw;
      left: 50%;
      transform: translateX(-50%);
      width: min(30px, 9vw);
      height: min(30px, 9vw);
      clip-path: polygon(50% 100%, 0 0, 100% 0);
      background-color: #f6e05e;
      z-index: 5;
    }

    /* Bigger, more accessible spin button for touch devices */
    .spin-btn {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      background: linear-gradient(to right, #f6ad55, #ed8936);
      color: #000;
      border: none;
      border-radius: 50%;
      width: min(70px, 22vw);
      height: min(70px, 22vw);
      font-weight: bold;
      text-transform: uppercase;
      font-size: clamp(10px, 3.5vw, 14px);
      z-index: 2;
      cursor: pointer;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
      transition: all 0.3s;
    }

    .spin-btn:hover {
      transform: translate(-50%, -50%) scale(1.1);
      box-shadow: 0 6px 8px rgba(0, 0, 0, 0.4);
    }

    /* Better touch feedback */
    .spin-btn:active {
      transform: translate(-50%, -50%) scale(1.1);
      box-shadow: 0 6px 8px rgba(0, 0, 0, 0.4);
    }

    /* Improved wheel segments for better visibility */
    .segment {
      position: absolute;
      top: 50%;
      left: 50%;
      transform-origin: 0 0;
      text-align: right;
      width: min(120px, 35vw);
      font-size: clamp(9px, 3vw, 12px);
      font-weight: bold;
      color: white;
      text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.9);
    }

    @keyframes blink {

      0%,
      100% {
        opacity: 1;
      }

      50% {
        opacity: 0.5;
      }
    }

    .result-win {
      animation: blink 1s infinite;
      color: #38a169;
      font-weight: bold;
    }

    .result-lose {
      color: #e53e3e;
      font-weight: bold;
    }

    .result-partial {
      color: #d69e2e;
      font-weight: bold;
    }

    @keyframes confetti {
      0% {
        transform: translateY(0) rotate(0);
        opacity: 1;
      }

      100% {
        transform: translateY(100vh) rotate(720deg);
        opacity: 0;
      }
    }

    .confetti {
      position: fixed;
      width: 10px;
      height: 10px;
      background-color: #f6e05e;
      opacity: 0;
      animation: confetti 5s ease-in-out forwards;
      z-index: 1000;
    }

    /* Responsive preloader */
    .wheel-preloader-container {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-color: #111827;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      z-index: 9999;
    }

    .wheel-preloader {
      width: min(120px, 30vw);
      height: min(120px, 30vw);
      border-radius: 50%;
      position: relative;
      animation: rotate 2s linear infinite;
      box-shadow: 0 0 20px rgba(0, 0, 0, 0.3);
      background: conic-gradient(#e53e3e 0% 18.2%,
          #d69e2e 18.2% 31.8%,
          #38a169 31.8% 50%,
          #3182ce 50% 63.6%,
          #805ad5 63.6% 74.5%,
          #dd6b20 74.5% 81.8%,
          #0d9488 81.8% 86.4%,
          #6366f1 86.4% 89.1%,
          #7e22ce 89.1% 90.9%,
          #be123c 90.9% 91.8%,
          #fbbf24 91.8% 100%);
    }

    .wheel-preloader::before {
      content: "";
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      width: 25%;
      height: 25%;
      background-color: #1a202c;
      border-radius: 50%;
      z-index: 2;
    }

    .wheel-preloader::after {
      content: "";
      position: absolute;
      top: min(-10px, -3vw);
      left: 50%;
      transform: translateX(-50%);
      width: min(15px, 4vw);
      height: min(15px, 4vw);
      clip-path: polygon(50% 100%, 0 0, 100% 0);
      background-color: #f6e05e;
      z-index: 3;
    }

    .loading-text {
      margin-top: 20px;
      color: #e5e7eb;
      font-size: clamp(14px, 4vw, 18px);
      font-weight: 600;
      letter-spacing: 0.05em;
    }

    .loading-dots::after {
      content: '';
      animation: dots 1.5s infinite;
    }

    @keyframes rotate {
      0% {
        transform: rotate(0deg);
      }

      100% {
        transform: rotate(360deg);
      }
    }

    @keyframes dots {

      0%,
      20% {
        content: '.';
      }

      40% {
        content: '..';
      }

      60%,
      100% {
        content: '...';
      }
    }

    /* Better mobile-specific adjustments */
    @media (max-width: 768px) {

      /* Container padding adjustments */
      .container.mx-auto.px-4 {
        padding-left: 0.75rem;
        padding-right: 0.75rem;
      }

      /* More spacing for main content */
      .flex-grow.py-6 {
        padding-top: 1rem;
        padding-bottom: 5rem;
        /* Extra space for bottom mobile nav */
      }

      /* Better card styling */
      .p-6 {
        padding: 1rem;
      }

      /* Better bet button layout on mobile */
      .flex.flex-wrap.gap-2.mb-6 {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 0.5rem;
      }

      /* Bigger, more visible bet buttons */
      .preset-bet {
        padding: 0.75rem 0;
        font-size: 1rem;
        font-weight: bold;
      }

      /* Make spin button more prominent */
      #submit-spin {
        padding: 0.875rem;
        font-size: 1.125rem;
      }

      /* Adjust table for better mobile viewing */
      .min-w-full {
        font-size: 0.8rem;
      }

      th.px-4.py-2,
      td.px-4.py-2 {
        padding: 0.5rem 0.25rem;
      }

      /* Make result message more visible */
      .text-2xl.result-win,
      .text-2xl.result-lose,
      .text-2xl.result-partial {
        font-size: 1.25rem;
        line-height: 1.75rem;
      }

      /* Improved wheel layout */
      .wheel-container {
        margin-bottom: 1rem;
      }

      /* Header adjustments */
      .mb-8 {
        margin-bottom: 1rem;
      }

      h1.text-3xl {
        font-size: 1.75rem;
      }
    }

    /* Small phone adjustments */
    @media (max-width: 375px) {

      /* Even more compact on very small screens */
      .p-6 {
        padding: 0.75rem;
      }

      .wheel-container {
        width: 85vw;
        height: 85vw;
      }

      .segment {
        width: 32vw;
        font-size: 8px;
      }

      /* Simplified table on very small screens */
      .min-w-full thead th:nth-child(1),
      .min-w-full tbody td:nth-child(1) {
        display: none;
        /* Hide date column on very small screens */
      }

      th.px-4.py-2,
      td.px-4.py-2 {
        padding: 0.375rem 0.25rem;
      }
    }

    /* Ensure everything renders correctly */
    #wheel-preloader {
      opacity: 1;
      transition: opacity 0.5s ease;
    }

    #main-content {
      display: none;
      opacity: 0;
      transition: opacity 0.5s ease;
    }

    /* Make multiplier list more visible on mobile */
    @media (max-width: 768px) {
      .space-y-2.text-sm {
        font-size: 0.875rem;
      }

      .space-y-2>div {
        padding: 0.25rem 0;
      }
    }

    /* Fix for Safari mobile */
    input[type="number"] {
      -webkit-appearance: none;
      margin: 0;
    }
  </style>
</head>

<body class="bg-gray-900 text-white font-sans min-h-screen flex flex-col">
  <?php include '../includes/mobile-bar.php'; ?>

  <!-- Main Content -->
  <main style="display: none;" id="main-content" class="flex-grow py-6">
    <div class="container mx-auto px-4 sm:px-6 lg:px-8">
      <?php include 'includes/go-back.php'; ?>
      <!-- Header Section -->
      <div class="mb-8">
        <h1 class="text-3xl font-bold">Spin & Win</h1>
        <p class="text-gray-400 mt-2">Spin the wheel and win up to 100x your bet!</p>
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

      <!-- Game Content -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Game Controls -->
        <div class="lg:col-span-1">
          <div class="bg-gray-800 rounded-xl border border-gray-700 p-6 mb-6">
            <h2 class="text-xl font-bold mb-4">Place Your Bet</h2>

            <div class="mb-6">
              <p class="text-sm text-gray-400 mb-2">Your Balance</p>
              <p class="text-2xl font-bold">$<?php echo number_format($balance, 2); ?></p>
            </div>

            <form method="POST" id="spin-form">
              <div class="mb-6">
                <label for="bet_amount" class="block text-sm font-medium text-gray-300 mb-2">Bet Amount</label>
                <div class="relative rounded-md shadow-sm">
                  <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <span class="text-gray-400">$</span>
                  </div>
                  <input type="number" name="bet_amount" id="bet_amount" min="1" max="100" step="0.5" value="<?php echo $bet_amount ?: 1; ?>"
                    class="bg-gray-700 focus:ring-yellow-500 focus:border-yellow-500 block w-full pl-10 pr-20 py-3 border border-gray-600 rounded-md text-white text-lg">
                  <div class="absolute inset-y-0 right-0 flex items-center">
                    <button type="button" id="bet-half" class="h-full px-3 bg-gray-600 text-white font-medium rounded-r-md hover:bg-gray-500 focus:outline-none">
                      1/2
                    </button>
                  </div>
                </div>
              </div>

              <div class="flex flex-wrap gap-2 mb-6">
                <button type="button" class="preset-bet bg-gray-700 hover:bg-gray-600 text-white px-3 py-1 rounded-md transition" data-amount="1">$1</button>
                <button type="button" class="preset-bet bg-gray-700 hover:bg-gray-600 text-white px-3 py-1 rounded-md transition" data-amount="5">$5</button>
                <button type="button" class="preset-bet bg-gray-700 hover:bg-gray-600 text-white px-3 py-1 rounded-md transition" data-amount="10">$10</button>
                <button type="button" class="preset-bet bg-gray-700 hover:bg-gray-600 text-white px-3 py-1 rounded-md transition" data-amount="25">$25</button>
                <button type="button" class="preset-bet bg-gray-700 hover:bg-gray-600 text-white px-3 py-1 rounded-md transition" data-amount="50">$50</button>
                <button type="button" class="preset-bet bg-gray-700 hover:bg-gray-600 text-white px-3 py-1 rounded-md transition" data-amount="100">$100</button>
              </div>

              <button type="submit" name="spin" id="submit-spin" class="w-full bg-gradient-to-r from-yellow-500 to-yellow-600 hover:from-yellow-600 hover:to-yellow-700 text-black font-bold py-3 px-6 rounded-lg transition duration-300 flex items-center justify-center shadow-lg">
                <i class="fas fa-sync-alt mr-2"></i> SPIN THE WHEEL
              </button>
            </form>
          </div>

          <!-- Multipliers Info -->
          <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
            <h2 class="text-lg font-bold mb-4">Multipliers</h2>
            <div class="space-y-2 text-sm">
              <div class="flex justify-between">
                <span class="text-red-500">LOSE</span>
                <span>20% chance</span>
              </div>
              <div class="flex justify-between">
                <span class="text-yellow-500">0.5x</span>
                <span>15% chance</span>
              </div>
              <div class="flex justify-between">
                <span class="text-green-500">1x</span>
                <span>20% chance</span>
              </div>
              <div class="flex justify-between">
                <span class="text-blue-500">1.5x</span>
                <span>15% chance</span>
              </div>
              <div class="flex justify-between">
                <span class="text-purple-500">2x</span>
                <span>12% chance</span>
              </div>
              <div class="flex justify-between">
                <span class="text-orange-500">3x</span>
                <span>8% chance</span>
              </div>
              <div class="flex justify-between">
                <span class="text-teal-500">5x</span>
                <span>5% chance</span>
              </div>
              <div class="flex justify-between">
                <span class="text-indigo-500">10x</span>
                <span>3% chance</span>
              </div>
              <div class="flex justify-between">
                <span class="text-purple-600">20x</span>
                <span>1.5% chance</span>
              </div>
              <div class="flex justify-between">
                <span class="text-red-600">50x</span>
                <span>0.4% chance</span>
              </div>
              <div class="flex justify-between">
                <span class="text-yellow-400">100x</span>
                <span>0.1% chance</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Wheel and Result -->
        <div class="lg:col-span-2" style="overflow: hidden;">
          <div class="bg-gray-800 rounded-xl border border-gray-700 p-6 mb-6">
            <div class="wheel-container mb-6">
              <div class="pointer"></div>
              <div style="" class="wheel" id="wheel">
                <!-- Segments will be added via JavaScript -->
              </div>
              <button class="spin-btn" id="manual-spin" type="button">SPIN</button>
            </div>

            <?php if (!empty($result_message)): ?>
              <div class="text-center py-4 mb-4 bg-gray-900 rounded-lg">
                <h3 class="text-xl font-bold mb-2">Result</h3>
                <p class="text-2xl result-<?php echo $result_type; ?>"><?php echo $result_message; ?></p>
              </div>
            <?php endif; ?>
          </div>

          <!-- Recent Spins -->
          <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
            <h2 class="text-xl font-bold mb-4">Your Recent Spins</h2>

            <?php if (empty($recent_games)): ?>
              <p class="text-gray-400 text-center py-4">No recent spins. Start playing to see your history!</p>
            <?php else: ?>
              <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-700">
                  <thead>
                    <tr>
                      <th class="px-4 py-2 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Date</th>
                      <th class="px-4 py-2 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Bet</th>
                      <th class="px-4 py-2 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Multiplier</th>
                      <th class="px-4 py-2 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Result</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-700">
                    <?php foreach ($recent_games as $game): ?>
                      <?php
                      $details = json_decode($game['details'], true);
                      $game_multiplier = $details['multiplier'] ?? 0;
                      $segment = $details['segment'] ?? 'LOSE';
                      ?>
                      <tr>
                        <td class="px-4 py-2 whitespace-nowrap text-sm">
                          <?php echo date('M d, H:i', strtotime($game['played_at'])); ?>
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap text-sm">
                          $<?php echo number_format($game['bet_amount'], 2); ?>
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap text-sm">
                          <?php echo $segment; ?>
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap text-sm">
                          <?php if ($game['result'] == 'win'): ?>
                            <span class="text-green-500">+$<?php echo number_format($game['winnings'], 2); ?></span>
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
    </div>
  </main>
  <!-- Spinner Wheel Preloader -->
  <style>

  </style>

  <div class="wheel-preloader-container" id="wheel-preloader">
    <div class="wheel-preloader"></div>
    <div class="loading-text">
      <span>Loading Spin & Win</span>
      <span class="loading-dots"></span>
    </div>
  </div>
  <?php include 'js/spin-wheel.php'; ?>
  <script>
    // Add this to your js/spin-wheel.php file
    document.addEventListener('DOMContentLoaded', function() {
      // Mobile-optimized preloader handling
      setTimeout(function() {
        // Hide both the standard preloader (if it exists) and the wheel preloader
        const standardPreloader = document.querySelector('.preloader');
        if (standardPreloader) {
          standardPreloader.style.display = 'none';
        }

        const wheelPreloader = document.getElementById('wheel-preloader');
        if (wheelPreloader) {
          wheelPreloader.style.opacity = '0';
          wheelPreloader.style.transition = 'opacity 0.5s ease';

          setTimeout(function() {
            wheelPreloader.style.display = 'none';

            const mainContent = document.getElementById('main-content');
            if (mainContent) {
              mainContent.style.display = 'block';
              // Add a fade-in effect for the main content
              setTimeout(function() {
                mainContent.style.opacity = '1';
              }, 50);
            }
          }, 500);
        }
      }, 2000);

      // Wheel segments - both visual and positioning adjustments for mobile
      const wheel = document.getElementById('wheel');
      const segments = [{
          value: 0,
          label: 'LOSE',
          color: '#e53e3e'
        },
        {
          value: 0.5,
          label: '0.5x',
          color: '#d69e2e'
        },
        {
          value: 1,
          label: '1x',
          color: '#38a169'
        },
        {
          value: 1.5,
          label: '1.5x',
          color: '#3182ce'
        },
        {
          value: 2,
          label: '2x',
          color: '#805ad5'
        },
        {
          value: 3,
          label: '3x',
          color: '#dd6b20'
        },
        {
          value: 5,
          label: '5x',
          color: '#0d9488'
        },
        {
          value: 10,
          label: '10x',
          color: '#6366f1'
        },
        {
          value: 20,
          label: '20x',
          color: '#7e22ce'
        },
        {
          value: 50,
          label: '50x',
          color: '#be123c'
        },
        {
          value: 100,
          label: '100x',
          color: '#fbbf24'
        }
      ];

      // Clear existing segments and recreate them with better mobile positioning
      const existingSegments = document.querySelectorAll('.segment');
      existingSegments.forEach(seg => seg.remove());

      // Create and position segments for all device sizes
      segments.forEach((segment, index) => {
        const angle = (index * 360 / segments.length);
        const textElem = document.createElement('div');
        textElem.className = 'segment';
        textElem.textContent = segment.label;

        // Position segments differently based on screen size
        const isMobile = window.innerWidth <= 768;
        if (isMobile) {
          // On mobile, adjust positioning to be more readable
          textElem.style.transform = `rotate(${angle}deg)`;

          // For very small screens, adjust more
          if (window.innerWidth <= 375) {
            textElem.style.width = '32vw';
          }
        } else {
          // Default positioning for larger screens
          textElem.style.transform = `rotate(${angle}deg)`;
        }

        wheel.appendChild(textElem);
      });

      // Enhanced mobile touch events for all interactive elements
      const spinBtn = document.getElementById('manual-spin');
      const submitSpinBtn = document.getElementById('submit-spin');
      const presetBtns = document.querySelectorAll('.preset-bet');
      const betHalfBtn = document.getElementById('bet-half');
      const betAmountInput = document.getElementById('bet_amount');

      // Improved mobile numeric input
      if (betAmountInput) {
        // Make bet amount input more mobile-friendly
        betAmountInput.addEventListener('focus', function() {
          if (window.innerWidth <= 768) {
            this.setAttribute('inputmode', 'decimal');
          }
        });
      }

      // Better touch feedback for main spin button
      if (spinBtn) {
        ['touchstart', 'mousedown'].forEach(evt => {
          spinBtn.addEventListener(evt, function(e) {
            this.style.transform = 'translate(-50%, -50%) scale(1.1)';
            this.style.boxShadow = '0 6px 8px rgba(0, 0, 0, 0.4)';
          });
        });

        ['touchend', 'mouseup', 'mouseleave'].forEach(evt => {
          spinBtn.addEventListener(evt, function(e) {
            this.style.transform = 'translate(-50%, -50%)';
            this.style.boxShadow = '0 4px 6px rgba(0, 0, 0, 0.3)';
          });
        });
      }

      // More responsive bet buttons
      presetBtns.forEach(btn => {
        btn.addEventListener('touchstart', function() {
          this.style.backgroundColor = '#4a5568'; // Darker feedback color
        });

        btn.addEventListener('touchend', function() {
          this.style.backgroundColor = '';

          // Set value with delay to ensure visual feedback
          setTimeout(() => {
            const amount = this.getAttribute('data-amount');
            betAmountInput.value = amount;
          }, 50);
        });
      });

      // Adjust layout for better mobile experience
      function adjustLayoutForMobile() {
        const isMobile = window.innerWidth <= 768;

        if (isMobile) {
          // Small screen optimizations
          const resultMessage = document.querySelector('.result-win, .result-lose, .result-partial');
          if (resultMessage) {
            resultMessage.style.fontSize = '1.25rem';
          }

          // Better table responsiveness
          const table = document.querySelector('.min-w-full');
          if (table) {
            if (window.innerWidth <= 375) {
              // Hide first column on very small screens
              const dateHeaders = table.querySelectorAll('th:first-child, td:first-child');
              dateHeaders.forEach(el => el.style.display = 'none');
            } else {
              // Restore if size increases
              const dateHeaders = table.querySelectorAll('th:first-child, td:first-child');
              dateHeaders.forEach(el => el.style.display = '');
            }
          }
        }
      }

      // Initial adjustment and listen for orientation changes
      adjustLayoutForMobile();
      window.addEventListener('resize', adjustLayoutForMobile);
      window.addEventListener('orientationchange', adjustLayoutForMobile);
    });
  </script>
</body>

</html>