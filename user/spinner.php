<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>AutoProftX - Luxury Spinner</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <style>
    /* Enhanced spinner styling */
    @keyframes spinnerGlow {

      0%,
      100% {
        box-shadow: 0 0 25px rgba(245, 158, 11, 0.6), 0 0 40px rgba(245, 158, 11, 0.3);
      }

      50% {
        box-shadow: 0 0 35px rgba(245, 158, 11, 0.8), 0 0 60px rgba(245, 158, 11, 0.4);
      }
    }

    @keyframes spin {
      0% {
        transform: rotate(0deg);
      }

      100% {
        transform: rotate(360deg);
      }
    }

    @keyframes spinToStop {
      0% {
        transform: rotate(0deg);
      }

      95% {
        transform: rotate(calc(360deg * var(--spin-multiplier)));
      }

      100% {
        transform: rotate(calc(360deg * var(--spin-multiplier) + var(--spin-result-deg)));
      }
    }

    @keyframes shimmer {
      0% {
        opacity: 0.5;
      }

      50% {
        opacity: 1;
      }

      100% {
        opacity: 0.5;
      }
    }

    @keyframes celebrationFade {
      0% {
        opacity: 0;
        transform: scale(0);
      }

      70% {
        opacity: 1;
        transform: scale(1.1);
      }

      100% {
        opacity: 1;
        transform: scale(1);
      }
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

    @keyframes float {

      0%,
      100% {
        transform: translateY(0);
      }

      50% {
        transform: translateY(-10px);
      }
    }

    @keyframes pulse {

      0%,
      100% {
        transform: scale(1);
      }

      50% {
        transform: scale(1.05);
      }
    }

    .spinner-container {
      position: relative;
      width: 340px;
      height: 340px;
      max-width: 100%;
      margin: 0 auto;
    }

    .spinner-wheel {
      width: 100%;
      height: 100%;
      position: relative;
      border-radius: 50%;
      border: 12px solid transparent;
      background-image: linear-gradient(#111827, #111827),
        linear-gradient(135deg, #f59e0b, #d97706, #eab308, #d97706);
      background-origin: border-box;
      background-clip: content-box, border-box;
      animation: spinnerGlow 3s infinite;
      transform-origin: center center;
      transition: transform 5s cubic-bezier(0.17, 0.67, 0.24, 0.99);
      box-shadow: 0 0 30px rgba(0, 0, 0, 0.5);
    }

    .spinner-wheel::before {
      content: '';
      position: absolute;
      top: -1px;
      left: -1px;
      right: -1px;
      bottom: -1px;
      border-radius: 50%;
      border: 1px solid rgba(245, 158, 11, 0.3);
    }

    .spinner-inner {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      width: 28%;
      height: 28%;
      background: radial-gradient(circle, #1f2937 0%, #111827 100%);
      border-radius: 50%;
      border: 4px solid #f59e0b;
      box-shadow:
        inset 0 0 20px rgba(0, 0, 0, 0.9),
        0 0 15px rgba(245, 158, 11, 0.5);
      z-index: 10;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .spinner-sector {
      position: absolute;
      width: 50%;
      height: 50%;
      left: 25%;
      top: 0;
      transform-origin: bottom center;
      box-sizing: border-box;
      clip-path: polygon(0% 0%, 100% 0%, 50% 100%);
      display: flex;
      align-items: flex-start;
      justify-content: center;
      padding-top: 5%;
      font-size: 1rem;
      font-weight: bold;
      text-shadow: 0 1px 2px rgba(0, 0, 0, 0.8);
    }

    .spinner-sector span {
      transform: translateY(5px) rotate(180deg);
    }

    .spinner-sector::after {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: radial-gradient(ellipse at center, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0) 70%);
    }

    .spinner-divider {
      position: absolute;
      width: 50%;
      height: 2px;
      background: rgba(255, 255, 255, 0.2);
      top: 50%;
      left: 50%;
      transform-origin: left center;
      z-index: 2;
    }

    .win-pointer {
      position: absolute;
      top: -18px;
      left: 50%;
      transform: translateX(-50%);
      width: 0;
      height: 0;
      border-left: 18px solid transparent;
      border-right: 18px solid transparent;
      border-top: 25px solid #f59e0b;
      filter: drop-shadow(0 2px 8px rgba(0, 0, 0, 0.5));
      z-index: 5;
    }

    .win-pointer::after {
      content: '';
      position: absolute;
      top: -25px;
      left: -10px;
      width: 20px;
      height: 20px;
      background: #f59e0b;
      border-radius: 50%;
    }

    .win-indicator {
      position: absolute;
      top: 20px;
      left: 50%;
      transform: translateX(-50%);
      background: linear-gradient(to bottom, #111827, #1f2937);
      border: 2px solid #f59e0b;
      padding: 8px 20px;
      border-radius: 20px;
      font-weight: bold;
      color: #f59e0b;
      display: none;
      z-index: 10;
      animation: celebrationFade 0.5s ease-out;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
    }

    .celebration {
      position: fixed;
      top: 0;
      left: 0;
      width: 100vw;
      height: 100vh;
      pointer-events: none;
      z-index: 100;
      display: none;
    }

    .confetti {
      position: absolute;
      width: 10px;
      height: 20px;
      background-color: #f59e0b;
      opacity: 0;
    }

    .spin-button {
      position: relative;
      overflow: hidden;
    }

    .spin-button::after {
      content: '';
      position: absolute;
      top: -50%;
      left: -50%;
      width: 200%;
      height: 200%;
      background: linear-gradient(to right,
          rgba(255, 255, 255, 0) 0%,
          rgba(255, 255, 255, 0.3) 50%,
          rgba(255, 255, 255, 0) 100%);
      transform: rotate(30deg);
      animation: shimmer 3s infinite;
      pointer-events: none;
    }

    .win-amount {
      font-size: 1.5rem;
      font-weight: bold;
      color: #f59e0b;
      text-shadow: 0 0 10px rgba(245, 158, 11, 0.5);
    }

    /* Enhanced shine effect for wheel */
    .shine-effect {
      position: absolute;
      top: -20%;
      left: -20%;
      width: 140%;
      height: 140%;
      background: radial-gradient(ellipse at center, rgba(255, 255, 255, 0.2) 0%, rgba(255, 255, 255, 0) 70%);
      pointer-events: none;
      opacity: 0.5;
      z-index: 1;
    }

    /* Spinning animation */
    .is-spinning {
      animation: spin 0.5s linear infinite;
    }

    @media (max-width: 640px) {
      .spinner-container {
        width: 300px;
        height: 300px;
      }

      .spinner-sector {
        font-size: 0.8rem;
        padding-top: 4%;
      }

      .spinner-sector span {
        transform: translateY(8px) rotate(180deg);
      }
    }

    @media (max-width: 380px) {
      .spinner-container {
        width: 260px;
        height: 260px;
      }

      .spinner-sector {
        font-size: 0.7rem;
      }
    }
  </style>
</head>

<body class="bg-gray-900 text-white font-sans min-h-screen flex flex-col">
  <div class="container mx-auto px-4 py-8">
    <div class="text-center mb-8">
      <h1 class="text-4xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-yellow-400 via-yellow-500 to-yellow-600 mb-2">
        AutoProftX Luxury Spinner
      </h1>
      <p class="text-gray-400">Try your luck and win up to $250!</p>
    </div>

    <!-- Wallet Balance Display -->
    <div class="mb-8 bg-gray-800 rounded-xl p-4 mx-auto max-w-md border border-gray-700 shadow-lg flex items-center justify-between">
      <div>
        <p class="text-gray-400 text-sm">Your Balance</p>
        <p class="text-2xl font-bold" id="wallet-balance">$1,000.00</p>
      </div>
      <button class="px-4 py-2 bg-gradient-to-r from-gray-700 to-gray-800 hover:from-gray-600 hover:to-gray-700 text-white rounded-lg border border-gray-600 shadow-lg flex items-center">
        <i class="fas fa-plus-circle mr-2"></i> Add Funds
      </button>
    </div>

    <!-- Spinner Section -->
    <div class="bg-gradient-to-b from-gray-800 to-gray-900 rounded-xl p-6 md:p-8 border border-gray-700 shadow-xl max-w-2xl mx-auto mb-8 relative overflow-hidden">
      <!-- Decorative elements -->
      <div class="absolute top-0 right-0 w-40 h-40 bg-yellow-500 opacity-5 rounded-full -mr-10 -mt-10"></div>
      <div class="absolute bottom-0 left-0 w-40 h-40 bg-yellow-500 opacity-5 rounded-full -ml-10 -mb-10"></div>

      <div class="relative z-10">
        <!-- Spinner Container -->
        <div class="spinner-container mb-6">
          <div class="spinner-wheel" id="spinner-wheel">
            <!-- Sectors will be added via JavaScript -->
            <div class="shine-effect"></div>
          </div>
          <div class="spinner-inner">
            <div id="inner-display" class="text-center">
              <div class="text-xs text-gray-400 mb-1">Spin to</div>
              <div class="text-xl font-bold text-yellow-500">WIN!</div>
            </div>
          </div>
          <div class="win-pointer"></div>
          <div id="win-indicator" class="win-indicator">$0</div>
        </div>

        <!-- Spinner Controls -->
        <div class="flex flex-col md:flex-row items-center space-y-4 md:space-y-0 md:space-x-4">
          <div class="w-full md:w-2/3">
            <label for="bet-amount" class="block text-sm font-medium text-gray-300 mb-2">Bet Amount ($)</label>
            <div class="relative mt-1 rounded-md shadow-sm">
              <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <span class="text-gray-400">$</span>
              </div>
              <input type="number" name="bet-amount" id="bet-amount" min="5" max="100" step="5" value="20"
                class="bg-gray-700 focus:ring-yellow-500 focus:border-yellow-500 block w-full pl-8 pr-12 py-3 border-gray-600 rounded-md text-white shadow-inner"
                placeholder="Enter amount">
              <div class="absolute inset-y-0 right-0 flex items-center">
                <div class="flex border-l border-gray-600 divide-x divide-gray-600">
                  <button type="button" id="decrease-bet" class="px-3 py-1 text-gray-400 hover:text-white bg-gray-800 focus:outline-none">
                    <i class="fas fa-minus"></i>
                  </button>
                  <button type="button" id="increase-bet" class="px-3 py-1 text-gray-400 hover:text-white bg-gray-800 focus:outline-none">
                    <i class="fas fa-plus"></i>
                  </button>
                </div>
              </div>
            </div>
          </div>
          <div class="w-full md:w-1/3">
            <button id="spin-button" class="w-full bg-gradient-to-r from-yellow-500 to-yellow-600 hover:from-yellow-600 hover:to-yellow-700 text-black font-bold py-3 px-6 rounded-lg transition duration-300 transform hover:scale-105 shadow-xl spin-button flex items-center justify-center">
              <i class="fas fa-sync-alt mr-2"></i> Spin Now
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Recent Wins Section -->
    <div class="bg-gray-800 rounded-xl p-6 max-w-2xl mx-auto border border-gray-700">
      <h2 class="text-xl font-bold mb-4 flex items-center">
        <i class="fas fa-trophy text-yellow-500 mr-2"></i> Recent Wins
      </h2>
      <div class="space-y-3" id="recent-wins">
        <div class="flex items-center justify-between border-b border-gray-700 pb-3">
          <div class="flex items-center">
            <div class="h-8 w-8 rounded-full bg-gray-700 flex items-center justify-center mr-3">
              <i class="fas fa-user text-gray-300"></i>
            </div>
            <div>
              <p class="font-semibold">John D.</p>
              <p class="text-xs text-gray-400">2 minutes ago</p>
            </div>
          </div>
          <div class="text-green-500 font-bold">$120</div>
        </div>
        <div class="flex items-center justify-between border-b border-gray-700 pb-3">
          <div class="flex items-center">
            <div class="h-8 w-8 rounded-full bg-gray-700 flex items-center justify-center mr-3">
              <i class="fas fa-user text-gray-300"></i>
            </div>
            <div>
              <p class="font-semibold">Sarah M.</p>
              <p class="text-xs text-gray-400">5 minutes ago</p>
            </div>
          </div>
          <div class="text-green-500 font-bold">$50</div>
        </div>
        <div class="flex items-center justify-between border-b border-gray-700 pb-3">
          <div class="flex items-center">
            <div class="h-8 w-8 rounded-full bg-gray-700 flex items-center justify-center mr-3">
              <i class="fas fa-user text-gray-300"></i>
            </div>
            <div>
              <p class="font-semibold">Mike R.</p>
              <p class="text-xs text-gray-400">10 minutes ago</p>
            </div>
          </div>
          <div class="text-green-500 font-bold">$200</div>
        </div>
        <div class="flex items-center justify-between">
          <div class="flex items-center">
            <div class="h-8 w-8 rounded-full bg-gray-700 flex items-center justify-center mr-3">
              <i class="fas fa-user text-gray-300"></i>
            </div>
            <div>
              <p class="font-semibold">Alex T.</p>
              <p class="text-xs text-gray-400">15 minutes ago</p>
            </div>
          </div>
          <div class="text-green-500 font-bold">$75</div>
        </div>
      </div>
    </div>

    <!-- Rules & Info Section -->
    <div class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-6 max-w-4xl mx-auto">
      <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
        <h2 class="text-xl font-bold mb-4 flex items-center">
          <i class="fas fa-question-circle text-yellow-500 mr-2"></i> How It Works
        </h2>
        <ol class="list-decimal pl-5 space-y-2 text-gray-300">
          <li>Enter the amount you want to bet (minimum $5)</li>
          <li>Click "Spin Now" to spin the wheel</li>
          <li>Wait for the wheel to stop spinning</li>
          <li>Win up to $250 instantly added to your wallet!</li>
        </ol>
      </div>
      <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
        <h2 class="text-xl font-bold mb-4 flex items-center">
          <i class="fas fa-info-circle text-yellow-500 mr-2"></i> Important Info
        </h2>
        <ul class="list-disc pl-5 space-y-2 text-gray-300">
          <li>Minimum bet amount is $5</li>
          <li>Maximum bet amount is $100</li>
          <li>All winnings are instantly credited to your wallet</li>
          <li>Your chances of winning are based on your bet amount</li>
          <li>No refunds for spins once initiated</li>
        </ul>
      </div>
    </div>
  </div>

  <!-- Celebration Elements -->
  <div id="celebration" class="celebration"></div>
</body>

</html>